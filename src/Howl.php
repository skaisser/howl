<?php

namespace Skaisser\Howl;

use Illuminate\Support\Facades\Log;
use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Drivers\DiscordDriver;
use Skaisser\Howl\Drivers\NullDriver;
use Skaisser\Howl\Drivers\SlackDriver;
use Skaisser\Howl\Drivers\TelegramDriver;
use Skaisser\Howl\Events\HowlEvent;
use Skaisser\Howl\Jobs\SendHowlJob;
use Skaisser\Howl\Support\Payload;
use Skaisser\Howl\Support\PendingNotification;
use Skaisser\Howl\Testing\HowlFake;

class Howl
{
    protected Driver $driver;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->driver = $this->resolveDriver($config['driver'] ?? 'discord');
    }

    // -----------------------------------------------------------------------
    // Driver-agnostic entry points (Phase 1)
    // -----------------------------------------------------------------------

    /**
     * Begin building a driver-agnostic notification with an optional channel override.
     * Precedence: per-call ($channel) > HowlEvent::channel() > config('howl.channel').
     */
    public function on(?string $channel = null): PendingNotification
    {
        $notification = new PendingNotification;

        if ($channel !== null) {
            $notification = $notification->channel($channel);
        }

        return $notification;
    }

    /**
     * Select the driver for this notification via a fluent pre-chain builder.
     * Returns a PendingNotification with the driver override pre-set.
     */
    public function driver(string $name): PendingNotification
    {
        return (new PendingNotification)->driver($name);
    }

    /**
     * Dispatch an 'error' severity notification.
     */
    public function error(HowlEvent|string $titleOrEvent = ''): bool
    {
        return $this->dispatchSeverity('error', $titleOrEvent);
    }

    /**
     * Dispatch a 'warning' severity notification.
     */
    public function warning(HowlEvent|string $titleOrEvent = ''): bool
    {
        return $this->dispatchSeverity('warning', $titleOrEvent);
    }

    /**
     * Dispatch an 'info' severity notification.
     */
    public function info(HowlEvent|string $titleOrEvent = ''): bool
    {
        return $this->dispatchSeverity('info', $titleOrEvent);
    }

    /**
     * Dispatch an 'audit' severity notification.
     */
    public function audit(HowlEvent|string $titleOrEvent = ''): bool
    {
        return $this->dispatchSeverity('audit', $titleOrEvent);
    }

    /**
     * Dispatch a 'deployment' severity notification.
     */
    public function deployment(HowlEvent|string $titleOrEvent = ''): bool
    {
        return $this->dispatchSeverity('deployment', $titleOrEvent);
    }

    /**
     * Dispatch a 'success' severity notification.
     */
    public function success(HowlEvent|string $titleOrEvent = ''): bool
    {
        return $this->dispatchSeverity('success', $titleOrEvent);
    }

    /**
     * Shared severity dispatcher — the six severity entry methods delegate here.
     */
    private function dispatchSeverity(string $severity, HowlEvent|string $titleOrEvent): bool
    {
        return (new PendingNotification)->{$severity}($titleOrEvent);
    }

    /**
     * Dispatch a fully-built payload to the configured driver.
     *
     * - Short-circuits when the current environment is in skip_environments.
     * - When queue mode is on and forceSync is false, dispatches a SendHowlJob.
     * - Otherwise sends synchronously, applying channel failover/fan-out semantics,
     *   then walking the driver-level fallback chain on per-channel failure.
     * - Never propagates exceptions; logs each failure and returns false silently.
     */
    public function dispatch(Payload $payload): bool
    {
        $skip = $this->config['skip_environments'] ?? ['testing'];
        $env = $this->config['app_env'] ?? config('app.env', 'local');

        if (in_array($env, $skip, true)) {
            return true;
        }

        // Resolve the effective primary driver (per-call override beats config)
        $primary = $payload->driver ?? $this->config['driver'] ?? 'discord';

        // Queue branch — dispatch async unless forceSync is set
        if (($this->config['queue'] ?? false) && ! $payload->forceSync) {
            SendHowlJob::dispatch($payload, $primary)
                ->onConnection($this->config['queue_connection'] ?? null)
                ->onQueue($this->config['queue_name'] ?? 'default');

            return true;
        }

        // Resolve channel routing configuration
        // Channel precedence: per-call payload->channel > config('howl.channel')
        $primaryChannel = $payload->channel ?? $this->config['channel'] ?? null;
        $backupChannel = $this->config['channel_backup'] ?? null;
        $mode = $this->config['channel_mode'] ?? 'failover';

        // Build a channel-pinned payload for the primary channel
        $primaryPayload = $primaryChannel !== null
            ? $this->clonePayloadWithChannel($payload, $primaryChannel)
            : $payload;

        if ($mode === 'fan_out') {
            // Fan-out: dispatch to both channels; true iff at least one succeeds
            $primaryResult = $this->dispatchToDriverOnChannel($primaryPayload, $primary);

            if ($backupChannel !== null) {
                $backupPayload = $this->clonePayloadWithChannel($payload, $backupChannel);
                $backupResult = $this->dispatchToDriverOnChannel($backupPayload, $primary);

                return $primaryResult || $backupResult;
            }

            return $primaryResult;
        }

        // Failover (default): try primary; on failure, try backup once
        if ($this->dispatchToDriverOnChannel($primaryPayload, $primary)) {
            return true;
        }

        if ($backupChannel !== null) {
            $backupPayload = $this->clonePayloadWithChannel($payload, $backupChannel);

            return $this->dispatchToDriverOnChannel($backupPayload, $primary);
        }

        return false;
    }

    /**
     * Swap the container binding with a HowlFake and return it.
     * Call this at the top of a test to capture all dispatched payloads.
     */
    public static function fake(): HowlFake
    {
        $config = app('howl')->config;
        $fake = new HowlFake($config);
        app()->instance('howl', $fake);

        // Clear the facade's resolved-instance cache so subsequent
        // Howl::xxx() calls go through the new fake binding.
        Facades\Howl::clearResolvedInstances();

        return $fake;
    }

    /**
     * Return the underlying driver (useful for assertions / testing).
     */
    public function getDriver(): Driver
    {
        return $this->driver;
    }

    /**
     * Expose config for sub-classes (e.g. HowlFake).
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Resolve a driver by name. Public so SendHowlJob can use it via DI.
     */
    public function resolveDriver(string $name): Driver
    {
        return match ($name) {
            'discord' => new DiscordDriver,
            'slack' => new SlackDriver,
            'telegram' => new TelegramDriver,
            'null' => new NullDriver,
            default => throw new \InvalidArgumentException("Howl: unknown driver '{$name}'."),
        };
    }

    // -----------------------------------------------------------------------
    // Private channel-dispatch helpers (Phase 3)
    // -----------------------------------------------------------------------

    /**
     * Dispatch a payload to a specific channel by cloning the payload with a
     * channel override, then walking the driver-level fallback chain.
     */
    private function dispatchToDriverOnChannel(Payload $payload, string $driverName): bool
    {
        // Per-call fallback (from Payload) takes priority over config fallback.
        $fallback = $payload->fallback ?? $this->config['fallback'] ?? null;

        // array_unique ensures we never call the same driver name twice
        $chain = array_values(array_unique(array_filter([$driverName, $fallback])));

        foreach ($chain as $name) {
            try {
                $driver = $this->resolveDriver($name);

                if ($driver->send($payload)) {
                    return true;
                }

                Log::error("Howl driver [{$name}] returned false", [
                    'driver' => $name,
                    'title' => $payload->title,
                    'severity' => $payload->severity,
                ]);
            } catch (\Throwable $e) {
                Log::error("Howl driver [{$name}] threw: {$e->getMessage()}", [
                    'driver' => $name,
                    'exception' => $e->getMessage(),
                    'title' => $payload->title,
                    'severity' => $payload->severity,
                ]);
            }
        }

        return false;
    }

    /**
     * Clone a payload with a different channel value (readonly-safe).
     */
    private function clonePayloadWithChannel(Payload $payload, string $channel): Payload
    {
        return new Payload(
            title: $payload->title,
            description: $payload->description,
            severity: $payload->severity,
            channel: $channel,
            fields: $payload->fields,
            codeBlocks: $payload->codeBlocks,
            mentions: $payload->mentions,
            meta: $payload->meta,
            buttons: $payload->buttons,
            attachments: $payload->attachments,
            threadId: $payload->threadId,
            username: $payload->username,
            app: $payload->app,
            env: $payload->env,
            timestamp: $payload->timestamp,
            forceSync: $payload->forceSync,
            fallback: $payload->fallback,
            driver: $payload->driver,
        );
    }
}
