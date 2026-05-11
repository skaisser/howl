<?php

namespace Skaisser\Howl\Events;

use Illuminate\Support\Str;
use Skaisser\Howl\Support\Payload;

/**
 * Abstract base class for all Howl notification events.
 *
 * ## 8-method contract (5 abstract, 3 with defaults)
 *
 *   severity(): string         — 'error' | 'warning' | 'info' | 'audit' | …  [abstract]
 *   title(): string            — embed title (typically includes emoji)        [abstract]
 *   description(): string      — embed description / body                      [abstract]
 *   fields(): array            — embed inline/block fields                     [abstract]
 *   emoji(): string            — primary event emoji identifier                [abstract]
 *   footerMeta(): array        — extra footer KV (merged on top of baseFooterMeta) [default: []]
 *   channel(): ?string         — null = dispatcher resolves from severity      [default: null]
 *   codeBlocks(): array        — code-fence blocks separate from fields        [default: []]
 *
 * ## Universal constructor
 *
 *   __construct(array $links = [], array $meta = [])
 *
 * Subclasses define their own constructors taking domain args + `array $links = [], array $meta = []`
 * last, and call parent::__construct($links, $meta).
 *
 * ## Final helpers (subclasses MUST NOT override)
 *
 *   renderLinks(): array       — converts $links to Discord embed fields
 *   baseFooterMeta(): array    — auto-injects event / severity / env / trace / timestamp
 *   toPayload(): Payload       — orchestrates all contract methods into the final Payload
 */
abstract class HowlEvent
{
    public function __construct(
        protected readonly array $links = [],
        protected readonly array $meta = [],
    ) {}

    // -------------------------------------------------------------------------
    // Abstract contract methods — every subclass MUST implement all 5
    // -------------------------------------------------------------------------

    /**
     * Primary emoji that identifies this event type.
     */
    abstract public function emoji(): string;

    /**
     * Severity level for this event.
     * Returns one of: error | warning | info | success | audit | deployment
     */
    abstract public function severity(): string;

    /**
     * Embed title (typically includes the emoji). Keep under 80 characters.
     */
    abstract public function title(): string;

    /**
     * Embed description / body text. 1-2 sentences; no JSON dumps.
     */
    abstract public function description(): string;

    /**
     * Embed inline/block fields returned by this event.
     * Each entry: ['name' => string, 'value' => string, 'inline' => bool]
     */
    abstract public function fields(): array;

    // -------------------------------------------------------------------------
    // Override hooks — non-abstract, subclasses may override when needed
    // -------------------------------------------------------------------------

    /**
     * Extra footer key-value metadata added by the subclass.
     * Merged on top of baseFooterMeta() — subclass wins on key collision.
     */
    public function footerMeta(): array
    {
        return [];
    }

    /**
     * Target channel. Returning null lets the dispatcher resolve from severity().
     */
    public function channel(): ?string
    {
        return null;
    }

    /**
     * Code-fence blocks (separate from inline fields).
     * Each entry: ['name' => '…', 'code' => '…', 'lang' => '…']
     */
    public function codeBlocks(): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // Final helpers — may NOT be overridden by subclasses
    // -------------------------------------------------------------------------

    /**
     * Convert $this->links to an array of Discord embed inline field dicts.
     *
     * Accepted forms per key:
     *   - Bare URL string:   ['producer' => 'https://…']
     *   - Override map:      ['producer' => ['text' => 'Acme Corp', 'url' => 'https://…']]
     *
     * Keys with a null / empty URL are skipped entirely.
     * Keys whose value is a string without an http/https scheme are also skipped.
     *
     * @return array<int, array{name: string, value: string, inline: bool}>
     */
    final public function renderLinks(): array
    {
        $labelMap = [
            'producer' => '🏢 Producer',
            'pedido' => '📦 Order',
            'order' => '📦 Order',
            'payment' => '💳 Payment',
            'user' => '👤 User',
            'customer' => '👤 User',
            'webhook' => '📬 Webhook',
            'tracking' => '🚚 Tracking',
            'label' => '🏷️ Label',
            'nfe' => '🇧🇷 Invoice',
            'invoice' => '🇧🇷 Invoice',
        ];

        $fields = [];

        foreach ($this->links as $key => $value) {
            // Resolve text and URL from either form
            if (is_array($value)) {
                $text = $value['text'] ?? ucfirst((string) $key);
                $url = $value['url'] ?? null;
            } else {
                $text = ucfirst((string) $key);
                $url = $value;
            }

            // Skip null / empty URLs
            if (empty($url)) {
                continue;
            }

            // If there is no URL scheme, treat the value as plain text and skip
            if (is_string($url) && ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                continue;
            }

            $fieldName = $labelMap[$key] ?? ('🔗 '.ucfirst((string) $key));
            $fieldValue = "[{$text}]({$url})";

            $fields[] = [
                'name' => $fieldName,
                'value' => $fieldValue,
                'inline' => true,
            ];
        }

        return $fields;
    }

    /**
     * Standard footer metadata injected into every payload.
     *
     * Keys:
     *   event      — snake_case class basename without 'Event' suffix
     *   severity   — from severity()
     *   env        — config('app.env')
     *   trace      — $meta['trace'] if provided, else a truncated ULID (8 chars)
     *   timestamp  — dd/mm/yyyy hh:mm
     *
     * @return array<string, mixed>
     */
    final protected function baseFooterMeta(): array
    {
        return [
            'event' => $this->snakeCaseClassBasename(),
            'severity' => $this->severity(),
            'env' => config('app.env'),
            'trace' => $this->meta['trace'] ?? substr((string) Str::ulid(), 0, 8),
            'timestamp' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * Orchestrate all contract methods into a fully-populated Payload.
     *
     * Field order: subclass fields() first, then renderLinks() appended.
     * Footer meta: baseFooterMeta() merged with footerMeta() — subclass wins on collision.
     *
     * Declared final — subclasses must not override this method.
     * Use the 8 contract methods to customise the output.
     */
    final public function toPayload(): Payload
    {
        $fields = array_merge($this->fields(), $this->renderLinks());
        $footerMeta = array_merge($this->baseFooterMeta(), $this->footerMeta());

        return new Payload(
            title: $this->title(),
            description: $this->description(),
            severity: $this->severity(),
            channel: $this->channel(),
            fields: $fields,
            codeBlocks: $this->codeBlocks(),
            mentions: [],
            meta: $footerMeta,
            buttons: [],
            attachments: [],
            threadId: null,
            username: null,
            app: config('app.name'),
            env: config('app.env'),
            timestamp: now()->toImmutable(),
            forceSync: false,
            fallback: null,
        );
    }

    // -------------------------------------------------------------------------
    // Protected helpers — useful for exception-bearing subclasses
    // -------------------------------------------------------------------------

    /**
     * Extract sanitized file path and line number from a Throwable.
     *
     * @return array{0: string, 1: int}
     */
    protected function extractCodeContext(\Throwable $e): array
    {
        $file = $this->sanitizePath($e->getFile());

        return [$file, $e->getLine()];
    }

    /**
     * Strip absolute home-directory prefixes so paths are project-relative.
     * Removes /Users/<name>/ and /home/<name>/ prefixes.
     */
    protected function sanitizePath(string $path): string
    {
        return (string) preg_replace('#^/(Users|home)/[^/]+/#', '', $path);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Convert the class basename to snake_case, stripping the trailing 'Event' suffix.
     *
     * Examples:
     *   GenericExceptionEvent  → generic_exception
     *   JobRetryExhaustedEvent → job_retry_exhausted
     *   OrderShippedEvent      → order_shipped
     */
    private function snakeCaseClassBasename(): string
    {
        $class = class_basename(static::class);

        // Strip trailing 'Event' suffix if present
        if (str_ends_with($class, 'Event')) {
            $class = substr($class, 0, -5);
        }

        // PascalCase → snake_case
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
    }
}
