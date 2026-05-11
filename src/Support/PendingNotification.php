<?php

namespace Skaisser\Howl\Support;

use DateTimeInterface;
use Skaisser\Howl\Events\HowlEvent;
use Skaisser\Howl\Facades\Howl;

class PendingNotification
{
    protected string $title = '';

    protected ?string $description = null;

    protected ?string $channel = null;

    /** @var array<int, array{name: string, value: string, inline: bool}> */
    protected array $fields = [];

    /** @var array<int, array{name: string, code: string, lang: string}> */
    protected array $codeBlocks = [];

    /** @var array<int, array{type: string, id: string}> */
    protected array $mentions = [];

    /** @var array<string, mixed> */
    protected array $meta = [];

    /** @var array<int, array{label: string, url: string}> */
    protected array $buttons = [];

    /** @var array<int, string> */
    protected array $attachments = [];

    protected ?string $threadId = null;

    protected ?string $username = null;

    protected ?string $app = null;

    protected ?string $env = null;

    protected ?DateTimeInterface $timestamp = null;

    protected bool $forceSync = false;

    protected ?string $fallback = null;

    protected ?HowlEvent $event = null;

    /**
     * Explicit severity override set via ->severity().
     *
     * When non-null, this value wins over both the terminal verb severity and the
     * event's severity() contract method. It also suppresses the severity-mismatch
     * LogicException that would otherwise be thrown when a terminal verb conflicts
     * with an event's severity().
     */
    protected ?string $severityOverride = null;

    // -----------------------------------------------------------------------
    // Event acceptance — Phase 4
    // -----------------------------------------------------------------------

    /**
     * Accept a HowlEvent instance to use as the base for the notification.
     *
     * When send() is called, the event's toPayload() provides defaults;
     * any builder-set values (title, description, fields, etc.) WIN on conflict.
     */
    public function acceptEvent(HowlEvent $event): static
    {
        $clone = clone $this;
        $clone->event = $event;

        return $clone;
    }

    // -----------------------------------------------------------------------
    // Fluent builder
    // -----------------------------------------------------------------------

    public function title(string $title): static
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function description(string $description): static
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    /** Alias for description(). */
    public function body(string $body): static
    {
        return $this->description($body);
    }

    public function field(string $name, string $value, bool $inline = true): static
    {
        $clone = clone $this;
        $clone->fields[] = ['name' => $name, 'value' => $value, 'inline' => $inline];

        return $clone;
    }

    public function codeBlock(string $name, string $code, string $lang = 'php'): static
    {
        $clone = clone $this;
        $clone->codeBlocks[] = ['name' => $name, 'code' => $code, 'lang' => $lang];

        return $clone;
    }

    public function channel(string $channel): static
    {
        $clone = clone $this;
        $clone->channel = $channel;

        return $clone;
    }

    /**
     * Add a mention. $type is 'user' | 'role' | 'here' | 'everyone'.
     * For 'here'/'everyone' pass an empty string for $id.
     */
    public function mention(string $type, string $id = ''): static
    {
        $clone = clone $this;
        $clone->mentions[] = ['type' => $type, 'id' => $id];

        return $clone;
    }

    /** @param array<string, mixed>|string $keyOrArray */
    public function meta(array|string $keyOrArray, mixed $value = null): static
    {
        $clone = clone $this;

        if (is_array($keyOrArray)) {
            $clone->meta = array_merge($clone->meta, $keyOrArray);
        } else {
            $clone->meta[$keyOrArray] = $value;
        }

        return $clone;
    }

    public function button(string $label, string $url): static
    {
        $clone = clone $this;
        $clone->buttons[] = ['label' => $label, 'url' => $url];

        return $clone;
    }

    public function attach(string $path): static
    {
        $clone = clone $this;
        $clone->attachments[] = $path;

        return $clone;
    }

    public function thread(string $threadId): static
    {
        $clone = clone $this;
        $clone->threadId = $threadId;

        return $clone;
    }

    public function username(string $username): static
    {
        $clone = clone $this;
        $clone->username = $username;

        return $clone;
    }

    public function app(string $app): static
    {
        $clone = clone $this;
        $clone->app = $app;

        return $clone;
    }

    public function env(string $env): static
    {
        $clone = clone $this;
        $clone->env = $env;

        return $clone;
    }

    public function at(DateTimeInterface $timestamp): static
    {
        $clone = clone $this;
        $clone->timestamp = $timestamp;

        return $clone;
    }

    public function forceSync(): static
    {
        $clone = clone $this;
        $clone->forceSync = true;

        return $clone;
    }

    /**
     * Override the fallback driver for this notification only.
     * If the primary driver fails, this driver is tried before the config fallback.
     */
    public function withFallback(string $driver): static
    {
        $clone = clone $this;
        $clone->fallback = $driver;

        return $clone;
    }

    /**
     * Explicitly set the notification severity.
     *
     * When set, this value wins over both the terminal verb severity and the event's
     * severity() contract method in buildPayload(). It also suppresses the
     * severity-mismatch LogicException — use this to intentionally send an event
     * under a different severity than the event declares.
     */
    public function severity(string $severity): static
    {
        $clone = clone $this;
        $clone->severityOverride = $severity;

        return $clone;
    }

    // -----------------------------------------------------------------------
    // Terminal methods — build Payload and dispatch
    // -----------------------------------------------------------------------

    /**
     * Send as an 'error' notification.
     *
     * **Builder-state-wins:** when the builder has been pre-configured
     * (e.g. `->title('Override')->error($event)`), builder scalar values
     * (title, description, channel) WIN over the event's contract methods.
     * Builder-set fields are APPENDED after event-supplied fields, not replaced.
     *
     * @throws \LogicException when $titleOrEvent is a HowlEvent whose severity()
     *                         !== 'error' and no ->severity() override has been set on the builder.
     *                         Use ->send($event) to defer to the event's own severity, or
     *                         ->severity('error') to silence the check.
     */
    public function error(mixed $titleOrEvent = ''): bool
    {
        if ($titleOrEvent instanceof HowlEvent) {
            if ($this->severityOverride === null && $titleOrEvent->severity() !== 'error') {
                throw new \LogicException(
                    "Howl: terminal verb ->error() conflicts with event severity '{$titleOrEvent->severity()}'. Use ->send(\$event) to defer to the event, or set ->severity('error') explicitly to override."
                );
            }

            return $this->acceptEvent($titleOrEvent)->send('error');
        }

        return $this->send('error', $titleOrEvent);
    }

    /**
     * Send as a 'warning' notification.
     *
     * **Builder-state-wins:** when the builder has been pre-configured
     * (e.g. `->title('Override')->warning($event)`), builder scalar values
     * (title, description, channel) WIN over the event's contract methods.
     * Builder-set fields are APPENDED after event-supplied fields, not replaced.
     *
     * @throws \LogicException when $titleOrEvent is a HowlEvent whose severity()
     *                         !== 'warning' and no ->severity() override has been set on the builder.
     *                         Use ->send($event) to defer to the event's own severity, or
     *                         ->severity('warning') to silence the check.
     */
    public function warning(mixed $titleOrEvent = ''): bool
    {
        if ($titleOrEvent instanceof HowlEvent) {
            if ($this->severityOverride === null && $titleOrEvent->severity() !== 'warning') {
                throw new \LogicException(
                    "Howl: terminal verb ->warning() conflicts with event severity '{$titleOrEvent->severity()}'. Use ->send(\$event) to defer to the event, or set ->severity('warning') explicitly to override."
                );
            }

            return $this->acceptEvent($titleOrEvent)->send('warning');
        }

        return $this->send('warning', $titleOrEvent);
    }

    /**
     * Send as an 'info' notification.
     *
     * **Builder-state-wins:** when the builder has been pre-configured
     * (e.g. `->title('Override')->info($event)`), builder scalar values
     * (title, description, channel) WIN over the event's contract methods.
     * Builder-set fields are APPENDED after event-supplied fields, not replaced.
     *
     * @throws \LogicException when $titleOrEvent is a HowlEvent whose severity()
     *                         !== 'info' and no ->severity() override has been set on the builder.
     *                         Use ->send($event) to defer to the event's own severity, or
     *                         ->severity('info') to silence the check.
     */
    public function info(mixed $titleOrEvent = ''): bool
    {
        if ($titleOrEvent instanceof HowlEvent) {
            if ($this->severityOverride === null && $titleOrEvent->severity() !== 'info') {
                throw new \LogicException(
                    "Howl: terminal verb ->info() conflicts with event severity '{$titleOrEvent->severity()}'. Use ->send(\$event) to defer to the event, or set ->severity('info') explicitly to override."
                );
            }

            return $this->acceptEvent($titleOrEvent)->send('info');
        }

        return $this->send('info', $titleOrEvent);
    }

    /**
     * Send as a 'success' notification.
     *
     * **Builder-state-wins:** when the builder has been pre-configured
     * (e.g. `->title('Override')->success($event)`), builder scalar values
     * (title, description, channel) WIN over the event's contract methods.
     * Builder-set fields are APPENDED after event-supplied fields, not replaced.
     *
     * @throws \LogicException when $titleOrEvent is a HowlEvent whose severity()
     *                         !== 'success' and no ->severity() override has been set on the builder.
     *                         Use ->send($event) to defer to the event's own severity, or
     *                         ->severity('success') to silence the check.
     */
    public function success(mixed $titleOrEvent = ''): bool
    {
        if ($titleOrEvent instanceof HowlEvent) {
            if ($this->severityOverride === null && $titleOrEvent->severity() !== 'success') {
                throw new \LogicException(
                    "Howl: terminal verb ->success() conflicts with event severity '{$titleOrEvent->severity()}'. Use ->send(\$event) to defer to the event, or set ->severity('success') explicitly to override."
                );
            }

            return $this->acceptEvent($titleOrEvent)->send('success');
        }

        return $this->send('success', $titleOrEvent);
    }

    /**
     * Send as an 'audit' notification.
     *
     * **Builder-state-wins:** when the builder has been pre-configured
     * (e.g. `->title('Override')->audit($event)`), builder scalar values
     * (title, description, channel) WIN over the event's contract methods.
     * Builder-set fields are APPENDED after event-supplied fields, not replaced.
     *
     * @throws \LogicException when $titleOrEvent is a HowlEvent whose severity()
     *                         !== 'audit' and no ->severity() override has been set on the builder.
     *                         Use ->send($event) to defer to the event's own severity, or
     *                         ->severity('audit') to silence the check.
     */
    public function audit(mixed $titleOrEvent = ''): bool
    {
        if ($titleOrEvent instanceof HowlEvent) {
            if ($this->severityOverride === null && $titleOrEvent->severity() !== 'audit') {
                throw new \LogicException(
                    "Howl: terminal verb ->audit() conflicts with event severity '{$titleOrEvent->severity()}'. Use ->send(\$event) to defer to the event, or set ->severity('audit') explicitly to override."
                );
            }

            return $this->acceptEvent($titleOrEvent)->send('audit');
        }

        return $this->send('audit', $titleOrEvent);
    }

    /**
     * Send as a 'deployment' notification.
     *
     * **Builder-state-wins:** when the builder has been pre-configured
     * (e.g. `->title('Override')->deployment($event)`), builder scalar values
     * (title, description, channel) WIN over the event's contract methods.
     * Builder-set fields are APPENDED after event-supplied fields, not replaced.
     *
     * @throws \LogicException when $titleOrEvent is a HowlEvent whose severity()
     *                         !== 'deployment' and no ->severity() override has been set on the builder.
     *                         Use ->send($event) to defer to the event's own severity, or
     *                         ->severity('deployment') to silence the check.
     */
    public function deployment(mixed $titleOrEvent = ''): bool
    {
        if ($titleOrEvent instanceof HowlEvent) {
            if ($this->severityOverride === null && $titleOrEvent->severity() !== 'deployment') {
                throw new \LogicException(
                    "Howl: terminal verb ->deployment() conflicts with event severity '{$titleOrEvent->severity()}'. Use ->send(\$event) to defer to the event, or set ->severity('deployment') explicitly to override."
                );
            }

            return $this->acceptEvent($titleOrEvent)->send('deployment');
        }

        return $this->send('deployment', $titleOrEvent);
    }

    /**
     * Send the notification.
     *
     * When passed a HowlEvent directly (non-terminal path), defers to the event's
     * own severity() — never throws a severity-mismatch exception. Builder-set
     * scalar values (title, description, channel) still WIN over the event's
     * contract methods when pre-configured, and builder fields are APPENDED after
     * event-supplied fields.
     */
    public function send(mixed $severityOrEvent = 'info', string $title = ''): bool
    {
        // Allow Howl::onDiscord()->send(new SomeEvent()) shorthand — defers to event severity, never throws
        if ($severityOrEvent instanceof HowlEvent) {
            return $this->acceptEvent($severityOrEvent)->send($severityOrEvent->severity());
        }

        $severity = (string) $severityOrEvent;

        // Preserve fluent immutability: clone via title() if a title arg was passed,
        // otherwise dispatch on $this. Direct mutation of $this->title would leak
        // state across re-uses of a pre-built builder instance.
        $instance = $title !== '' ? $this->title($title) : $this;

        return Howl::dispatch($instance->buildPayload($severity));
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    protected function buildPayload(string $severity): Payload
    {
        // When an event is set, use its payload as the base.
        // Builder-set scalar values WIN over event contract methods on collision.
        // Event fields/codeBlocks/mentions/meta come first; builder values are appended/merged on top.
        if ($this->event !== null) {
            $base = $this->event->toPayload();

            return new Payload(
                // Builder title wins if explicitly set (non-empty), otherwise event title
                title: $this->title !== '' ? $this->title : $base->title,
                // Builder description wins if explicitly set, otherwise event description
                description: $this->description ?? $base->description,
                // Explicit ->severity() override wins; else verb severity; else event severity
                severity: $this->severityOverride ?? ($severity !== '' ? $severity : $base->severity),
                // Builder channel wins if explicitly set, otherwise event channel
                channel: $this->channel ?? $base->channel,
                // Event fields first, builder fields APPENDED (not replaced)
                fields: array_merge($base->fields, $this->fields),
                codeBlocks: array_merge($base->codeBlocks, $this->codeBlocks),
                mentions: array_merge($base->mentions, $this->mentions),
                // Event meta first, builder meta wins on key collision
                meta: array_merge($base->meta, $this->meta),
                buttons: array_merge($base->buttons, $this->buttons),
                attachments: array_merge($base->attachments, $this->attachments),
                threadId: $this->threadId ?? $base->threadId,
                username: $this->username ?? $base->username,
                app: $this->app ?? $base->app,
                env: $this->env ?? $base->env,
                timestamp: $this->timestamp ?? $base->timestamp,
                forceSync: $this->forceSync || $base->forceSync,
                fallback: $this->fallback ?? $base->fallback,
            );
        }

        return new Payload(
            title: $this->title,
            description: $this->description,
            // Explicit ->severity() override wins over verb severity
            severity: $this->severityOverride ?? $severity,
            channel: $this->channel,
            fields: $this->fields,
            codeBlocks: $this->codeBlocks,
            mentions: $this->mentions,
            meta: $this->meta,
            buttons: $this->buttons,
            attachments: $this->attachments,
            threadId: $this->threadId,
            username: $this->username,
            app: $this->app,
            env: $this->env,
            timestamp: $this->timestamp,
            forceSync: $this->forceSync,
            fallback: $this->fallback,
        );
    }
}
