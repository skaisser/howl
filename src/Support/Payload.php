<?php

namespace Skaisser\Howl\Support;

use DateTimeInterface;

final readonly class Payload
{
    /**
     * @param  array<int, array{name: string, value: string, inline: bool}>  $fields
     * @param  array<int, array{name: string, code: string, lang: string}>  $codeBlocks
     * @param  array<int, array{type: string, id: string}>  $mentions
     * @param  array<string, mixed>  $meta
     * @param  array<int, array{label: string, url: string}>  $buttons
     * @param  array<int, string>  $attachments
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public string $severity,
        public ?string $channel,
        public array $fields,
        public array $codeBlocks,
        public array $mentions,
        public array $meta,
        public array $buttons,
        public array $attachments,
        public ?string $threadId,
        public ?string $username,
        public ?string $app,
        public ?string $env,
        public ?DateTimeInterface $timestamp,
        public bool $forceSync,
        public ?string $fallback = null,
    ) {}
}
