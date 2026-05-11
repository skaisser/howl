<?php

namespace Skaisser\Howl\Events;

/**
 * Catch-all event for one-off informational notifications.
 *
 * Severity, title, description and fields are all supplied via the constructor,
 * making this a thin wrapper that requires no domain subclass.
 *
 * Constructor:
 *   string $title           — embed title (typically includes emoji)
 *   string $description     — embed description / body
 *   array  $fields          — optional inline fields
 *   string $severity        — one of error|warning|info|success|audit|deployment
 *   array  $links           — optional Discord embed links
 *   array  $meta            — optional metadata (trace, etc.)
 */
class GenericInfoEvent extends HowlEvent
{
    /** @var array<string, string> */
    private static array $emojiMap = [
        'error' => '🚨',
        'warning' => '🟡',
        'info' => 'ℹ️',
        'success' => '✅',
        'audit' => '🔒',
        'deployment' => '🚀',
    ];

    public function __construct(
        protected readonly string $eventTitle,
        protected readonly string $eventDescription,
        protected readonly array $eventFields = [],
        protected readonly string $eventSeverity = 'info',
        array $links = [],
        array $meta = [],
    ) {
        parent::__construct($links, $meta);
    }

    public function severity(): string
    {
        return $this->eventSeverity;
    }

    public function emoji(): string
    {
        return self::$emojiMap[$this->eventSeverity] ?? 'ℹ️';
    }

    public function title(): string
    {
        return $this->eventTitle;
    }

    public function description(): string
    {
        return $this->eventDescription;
    }

    public function fields(): array
    {
        return $this->eventFields;
    }

    public function codeBlocks(): array
    {
        return [];
    }

    public function footerMeta(): array
    {
        return [];
    }

    public function channel(): ?string
    {
        return match ($this->eventSeverity) {
            'error' => 'errors',
            'warning' => 'warnings',
            'info' => 'info',
            'success' => 'info',
            'audit' => 'audit',
            'deployment' => 'deployments',
            default => 'info',
        };
    }
}
