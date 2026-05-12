<?php

namespace Skaisser\Howl\Events;

use Illuminate\Support\Str;

/**
 * Emitted when a queued job's failed(\Throwable) hook fires after exhausting $tries.
 *
 * Truly Laravel-generic — applies to any queued application.
 *
 * Constructor:
 *   string     $jobClass       — fully-qualified class name of the failed job
 *   array      $payload        — the serialized job payload (for diagnostics)
 *   int        $attempts       — number of attempts made before giving up
 *   \Throwable $lastException  — the final exception that caused the failure
 *   array      $links          — optional Discord embed links
 *   array      $meta           — optional metadata (queue, trace, etc.)
 */
class JobRetryExhaustedEvent extends HowlEvent
{
    public function __construct(
        protected readonly string $jobClass,
        protected readonly array $payload,
        protected readonly int $attempts,
        protected readonly \Throwable $lastException,
        array $links = [],
        array $meta = [],
    ) {
        parent::__construct($links, $meta);
    }

    public function severity(): string
    {
        return 'error';
    }

    public function emoji(): string
    {
        return '🚨';
    }

    public function title(): string
    {
        return 'Job retry exhausted: '.class_basename($this->jobClass);
    }

    public function description(): string
    {
        return Str::limit($this->lastException->getMessage(), 1024);
    }

    public function fields(): array
    {
        $fields = [
            ['name' => '🔧 Job', 'value' => class_basename($this->jobClass), 'inline' => true],
            ['name' => '🔄 Attempts', 'value' => (string) $this->attempts, 'inline' => true],
        ];

        if (! is_null($this->meta['queue'] ?? null)) {
            $fields[] = ['name' => '📥 Queue', 'value' => $this->meta['queue'], 'inline' => true];
        }

        return $fields;
    }

    public function codeBlocks(): array
    {
        return [
            [
                'name' => '📁 Source',
                'code' => $this->sanitizePath($this->lastException->getFile()).':'.$this->lastException->getLine(),
                'lang' => 'php',
            ],
            [
                'name' => '📦 Payload',
                'code' => substr(json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 0, 1000),
                'lang' => 'json',
            ],
        ];
    }

    public function footerMeta(): array
    {
        return [
            'job_class' => $this->jobClass,
            'attempts' => $this->attempts,
        ];
    }

    public function channel(): ?string
    {
        return 'errors';
    }
}
