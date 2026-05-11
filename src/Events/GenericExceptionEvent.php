<?php

namespace Skaisser\Howl\Events;

/**
 * Generic fallback event for any untyped Throwable.
 *
 * v0.2.0 contract: implements all 8 methods; codeBlocks() emits
 * a '📁 Source' entry with sanitized file:line from the exception.
 *
 * Constructor:
 *   \Throwable $exception       — the caught exception
 *   ?string    $title           — optional override (defaults to class basename)
 *   ?string    $description     — optional override (defaults to exception message)
 *   array      $links           — optional Discord embed links
 *   array      $meta            — optional metadata (trace, queue, etc.)
 */
class GenericExceptionEvent extends HowlEvent
{
    public function __construct(
        protected readonly \Throwable $exception,
        protected readonly ?string $customTitle = null,
        protected readonly ?string $customDescription = null,
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
        return $this->customTitle ?? '🚨 Exception: '.class_basename($this->exception);
    }

    public function description(): string
    {
        return $this->customDescription ?? substr($this->exception->getMessage(), 0, 1024);
    }

    public function fields(): array
    {
        return [];
    }

    public function codeBlocks(): array
    {
        [$file, $line] = $this->extractCodeContext($this->exception);

        return [
            ['name' => '📁 Source', 'code' => "{$file}:{$line}", 'lang' => 'php'],
        ];
    }

    public function footerMeta(): array
    {
        [$file, $line] = $this->extractCodeContext($this->exception);

        return [
            'class' => class_basename($this->exception),
            'file' => $file,
            'line' => $line,
        ];
    }

    public function channel(): ?string
    {
        return 'errors';
    }
}
