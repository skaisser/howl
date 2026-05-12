<?php

namespace Skaisser\Howl\Events;

/**
 * Emitted for CLI/admin actions — e.g. artisan commands or admin panel operations.
 *
 * Generic to any admin-action pattern in a Laravel application.
 *
 * Constructor:
 *   string   $operator        — identifier of who triggered the operation (user, system, etc.)
 *   string   $command         — the command or action that was run
 *   array    $args            — optional command arguments / parameters
 *   ?int     $durationSeconds — optional execution duration in seconds
 *   array    $links           — optional Discord embed links
 *   array    $meta            — optional metadata (trace, etc.)
 */
class ManualOperationEvent extends HowlEvent
{
    public function __construct(
        protected readonly string $operator,
        protected readonly string $command,
        protected readonly array $args = [],
        protected readonly ?int $durationSeconds = null,
        array $links = [],
        array $meta = [],
    ) {
        parent::__construct($links, $meta);
    }

    public function severity(): string
    {
        return 'info';
    }

    public function emoji(): string
    {
        return 'ℹ️';
    }

    public function title(): string
    {
        return "Manual op: {$this->command} by {$this->operator}";
    }

    public function description(): string
    {
        return "Operator '{$this->operator}' triggered manual command '{$this->command}'.";
    }

    public function fields(): array
    {
        $fields = [
            ['name' => '👤 Operator', 'value' => $this->operator, 'inline' => true],
            ['name' => '⚡ Command', 'value' => $this->command, 'inline' => true],
        ];

        if (! is_null($this->durationSeconds)) {
            $fields[] = [
                'name' => '⏱️ Duration',
                'value' => $this->formatDuration($this->durationSeconds),
                'inline' => true,
            ];
        }

        return $fields;
    }

    public function codeBlocks(): array
    {
        if (empty($this->args)) {
            return [];
        }

        return [
            [
                'name' => '⚙️ Args',
                'code' => substr(json_encode($this->args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 0, 600),
                'lang' => 'json',
            ],
        ];
    }

    public function footerMeta(): array
    {
        return [
            'operator' => $this->operator,
            'command' => $this->command,
        ];
    }

    public function channel(): ?string
    {
        return 'info';
    }

    private function formatDuration(int $seconds): string
    {
        return $seconds < 60
            ? "{$seconds}s"
            : sprintf('%dm %ds', intdiv($seconds, 60), $seconds % 60);
    }
}
