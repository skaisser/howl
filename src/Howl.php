<?php

namespace Skaisser\Howl;

use BadMethodCallException;
use Illuminate\Support\Facades\Log;
use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Drivers\DiscordDriver;
use Skaisser\Howl\Drivers\NullDriver;
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

    /**
     * Begin building a notification for the Discord driver.
     */
    public function onDiscord(?string $channel = null): PendingNotification
    {
        $notification = new PendingNotification;

        if ($channel !== null) {
            $notification = $notification->channel($channel);
        }

        return $notification;
    }

    /**
     * Reserved for v2 — Slack driver not yet implemented.
     *
     * @throws BadMethodCallException
     */
    public function onSlack(?string $channel = null): PendingNotification
    {
        throw new BadMethodCallException('onSlack() is reserved for v2. Only onDiscord() is available in v1.');
    }

    /**
     * Reserved for v2 — Telegram driver not yet implemented.
     *
     * @throws BadMethodCallException
     */
    public function onTelegram(?string $channel = null): PendingNotification
    {
        throw new BadMethodCallException('onTelegram() is reserved for v2. Only onDiscord() is available in v1.');
    }

    /**
     * Dispatch a fully-built payload to the configured driver.
     *
     * - Short-circuits when the current environment is in skip_environments.
     * - When queue mode is on and forceSync is false, dispatches a SendHowlJob.
     * - Otherwise sends synchronously, walking the fallback chain on failure.
     * - Never propagates exceptions; logs each failure and returns false silently.
     */
    public function dispatch(Payload $payload): bool
    {
        $skip = $this->config['skip_environments'] ?? ['testing'];
        $env = $this->config['app_env'] ?? config('app.env', 'local');

        if (in_array($env, $skip, true)) {
            return true;
        }

        $primary = $this->config['driver'] ?? 'discord';

        // Queue branch — dispatch async unless forceSync is set
        if (($this->config['queue'] ?? false) && ! $payload->forceSync) {
            SendHowlJob::dispatch($payload, $primary)
                ->onConnection($this->config['queue_connection'] ?? null)
                ->onQueue($this->config['queue_name'] ?? 'default');

            return true;
        }

        // Sync path — walk the fallback chain, swallowing all exceptions
        // Per-call fallback (from Payload) takes priority over config fallback.
        $fallback = $payload->fallback ?? $this->config['fallback'] ?? null;

        // array_unique ensures we never call the same driver name twice
        $chain = array_values(array_unique(array_filter([$primary, $fallback])));

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
            'null' => new NullDriver,
            default => throw new \InvalidArgumentException("Howl: unknown driver '{$name}'."),
        };
    }
}
