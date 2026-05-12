<?php

namespace Skaisser\Howl\Support;

/**
 * Converts a Payload into a Slack Block Kit request body for chat.postMessage.
 *
 * Block layout (in order, only non-empty blocks emitted):
 *  1. section (mrkdwn) — leading mentions paragraph (when mentions present)
 *  2. header (plain_text) — severity emoji + title
 *  3. section (mrkdwn) — description (when present)
 *  4. section with fields[] — tabular data (when fields present)
 *  5. divider — between fields and code blocks (when both present)
 *  6. section (mrkdwn) — triple-backtick code block per entry
 *  7. actions — URL buttons (when buttons present)
 *  8. context — app · env · timestamp footer
 */
final class BlockKitBuilder
{
    /**
     * Build the full chat.postMessage request body.
     *
     * @return array{channel: string, attachments: array<int, mixed>}
     */
    public static function build(Payload $payload, string $channelId): array
    {
        $severity = $payload->severity;
        $emoji = self::emoji($severity);
        $color = self::color($severity);

        $blocks = [];

        // 1. Leading mentions block
        $mentionText = self::buildMentions($payload);
        if ($mentionText !== '') {
            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $mentionText],
            ];
        }

        // 2. Header block — severity emoji + title
        $blocks[] = [
            'type' => 'header',
            'text' => ['type' => 'plain_text', 'text' => $emoji.' '.$payload->title, 'emoji' => true],
        ];

        // 3. Description section
        if ($payload->description !== null && $payload->description !== '') {
            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $payload->description],
            ];
        }

        // 4. Fields as mrkdwn fields array
        if (! empty($payload->fields)) {
            $fields = [];
            foreach ($payload->fields as $field) {
                $fields[] = [
                    'type' => 'mrkdwn',
                    'text' => '*'.($field['name'] ?? '').':*'."\n".($field['value'] ?? ''),
                ];
            }
            $blocks[] = [
                'type' => 'section',
                'fields' => $fields,
            ];
        }

        // 5. Divider between fields and code blocks (only when both present)
        if (! empty($payload->fields) && ! empty($payload->codeBlocks)) {
            $blocks[] = ['type' => 'divider'];
        }

        // 6. Code blocks — one section per code block
        foreach ($payload->codeBlocks as $block) {
            $lang = $block['lang'] ?? '';
            $code = $block['code'] ?? '';
            $fence = '```'.($lang !== '' ? $lang : '')."\n".$code."\n```";
            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $fence],
            ];
        }

        // 7. Actions block — URL buttons
        if (! empty($payload->buttons)) {
            $elements = [];
            foreach ($payload->buttons as $button) {
                $elements[] = [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => $button['label'], 'emoji' => false],
                    'url' => $button['url'],
                ];
            }
            $blocks[] = [
                'type' => 'actions',
                'elements' => $elements,
            ];
        }

        // 8. Context footer — app · env · timestamp
        $app = $payload->app ?? config('howl.app_name', config('app.name', 'App'));
        $env = $payload->env ?? config('howl.app_env', config('app.env', 'local'));
        $ts = $payload->timestamp ?? now();
        $tsFormatted = ($ts instanceof \DateTimeImmutable
            ? $ts
            : \DateTimeImmutable::createFromInterface($ts))->format('Y-m-d H:i:s \U\T\C');

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                ['type' => 'mrkdwn', 'text' => "{$app} · {$env} · {$tsFormatted}"],
            ],
        ];

        return [
            'channel' => $channelId,
            'attachments' => [
                [
                    'color' => $color,
                    'blocks' => $blocks,
                ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private static function emoji(string $severity): string
    {
        /** @var array<string, string> $map */
        $map = config('howl.emojis', []);

        return $map[$severity] ?? '🔔';
    }

    /**
     * Return hex color string from decimal color config value.
     */
    private static function color(string $severity): string
    {
        $decimal = config('howl.colors.'.$severity, 0);

        return sprintf('#%06X', (int) $decimal);
    }

    /**
     * Build the leading mentions mrkdwn string.
     * Mention types:
     *   here      → <!here>
     *   everyone  → <!channel>
     *   role      → <!subteam^{id}>
     *   user      → <@{id}>
     */
    private static function buildMentions(Payload $payload): string
    {
        $parts = [];

        foreach ($payload->mentions as $mention) {
            $type = $mention['type'] ?? '';
            $id = $mention['id'] ?? '';

            $parts[] = match ($type) {
                'here' => '<!here>',
                'everyone' => '<!channel>',
                'role' => "<!subteam^{$id}>",
                'user' => "<@{$id}>",
                default => '',
            };
        }

        return implode(' ', array_filter($parts));
    }
}
