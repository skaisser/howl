<?php

namespace Skaisser\Howl\Events;

/**
 * Fired to confirm a scheduled job ran (heartbeat / dead-man's switch).
 *
 * v0.2.0 contract: implements all 8 methods. Severity and channel flip to
 * 'error' / 'errors' when status === 'failed', otherwise 'info' / 'info'.
 *
 * Constructor:
 *   string             $scheduleName     — the cron schedule or job name
 *   \DateTimeInterface $lastRunAt        — when it last ran
 *   string             $status           — e.g. "ok", "late", "missed", "failed"
 *   ?int               $durationSeconds  — how long the job took, in seconds
 *   array              $links            — optional Discord embed links
 *   array              $meta             — optional metadata
 */
class CronHeartbeatEvent extends HowlEvent
{
    public function __construct(
        protected readonly string $scheduleName,
        protected readonly \DateTimeInterface $lastRunAt,
        protected readonly string $status,
        protected readonly ?int $durationSeconds = null,
        array $links = [],
        array $meta = [],
    ) {
        parent::__construct($links, $meta);
    }

    public function severity(): string
    {
        return $this->status === 'failed' ? 'error' : 'info';
    }

    public function emoji(): string
    {
        return '⏱️';
    }

    public function title(): string
    {
        return "Cron: {$this->scheduleName} — {$this->status}";
    }

    public function description(): string
    {
        return "Scheduled task '{$this->scheduleName}' reported status: {$this->status}.";
    }

    public function fields(): array
    {
        $fields = [
            ['name' => '📋 Schedule', 'value' => $this->scheduleName,                                       'inline' => true],
            ['name' => '📊 Status',   'value' => $this->status,                                             'inline' => true],
            ['name' => '🕐 Last Run', 'value' => '<t:'.$this->lastRunAt->getTimestamp().':R>', 'inline' => true],
        ];

        if ($this->durationSeconds !== null) {
            $fields[] = ['name' => '⏱️ Duration', 'value' => $this->formatDuration($this->durationSeconds), 'inline' => true];
        }

        return $fields;
    }

    public function codeBlocks(): array
    {
        return [];
    }

    public function footerMeta(): array
    {
        return [
            'schedule' => $this->scheduleName,
            'status' => $this->status,
        ];
    }

    public function channel(): ?string
    {
        return $this->status === 'failed' ? 'errors' : 'info';
    }

    // -------------------------------------------------------------------------
    // Private helper
    // -------------------------------------------------------------------------

    /**
     * Format a duration in seconds as a human-readable string.
     * e.g. 42 → "42s", 65 → "1m 5s"
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return $remaining > 0 ? "{$minutes}m {$remaining}s" : "{$minutes}m";
    }
}
