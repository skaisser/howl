<?php

namespace Skaisser\Howl\Support;

/**
 * Converts a Payload into a Discord webhook request array (embed + metadata).
 *
 * Callers receive the full request body array ready to be JSON-encoded.
 *
 * IMPORTANT: inline fields get `"inline": true` and nothing else.
 * Discord auto-wraps to 2 or 3 columns depending on viewport width.
 * Do NOT add any column-counting logic here.
 */
final class EmbedBuilder
{
    /**
     * Build the complete Discord webhook request body for the given payload.
     *
     * @return array{username?: string, avatar_url?: string, embeds: array<int, mixed>, components?: array<int, mixed>, allowed_mentions: array<string, mixed>}
     */
    public static function build(Payload $payload): array
    {
        $severity = $payload->severity;
        $emoji = self::emoji($severity);
        $color = self::color($severity);

        $embed = [];

        // Author block: "{emoji} {app} · {env} · {channel}"
        $embed['author'] = self::buildAuthor($payload, $emoji);

        // Title with severity emoji prefix
        $embed['title'] = $emoji.' '.$payload->title;

        // Description
        if ($payload->description !== null && $payload->description !== '') {
            $embed['description'] = $payload->description;
        }

        // Color bar (decimal)
        $embed['color'] = $color;

        // Relative timestamp at embed level: <t:UNIX:R>
        $ts = $payload->timestamp ?? now();
        $embed['timestamp'] = ($ts instanceof \DateTimeImmutable ? $ts : \DateTimeImmutable::createFromInterface($ts))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');

        // Fields — inline and non-inline
        $fields = [];
        foreach ($payload->fields as $field) {
            $fields[] = [
                'name' => $field['name'],
                'value' => $field['value'],
                'inline' => (bool) $field['inline'],
            ];
        }

        // Code blocks rendered as non-inline fields
        foreach ($payload->codeBlocks as $block) {
            $fields[] = [
                'name' => $block['name'],
                'value' => '```'.$block['lang']."\n".$block['code']."\n```",
                'inline' => false,
            ];
        }

        if (! empty($fields)) {
            $embed['fields'] = $fields;
        }

        // Footer (bot-parseable contract)
        $embed['footer'] = [
            'text' => FooterSerializer::serialize(
                severity: $severity,
                meta: $payload->meta,
                env: $payload->env ?? config('app.env', 'local'),
                timestamp: $payload->timestamp,
            ),
        ];

        // Build request body
        $body = [];

        // Username (webhook display name)
        $username = self::buildUsername($payload, $emoji);
        if ($username !== '') {
            $body['username'] = $username;
        }

        // Avatar URL (optional)
        $avatarUrl = config('howl.drivers.discord.avatar_url');
        if ($avatarUrl) {
            $body['avatar_url'] = $avatarUrl;
        }

        // Content — mention text (rendered before the embed)
        $mentionContent = self::buildMentionContent($payload);
        if ($mentionContent !== '') {
            $body['content'] = $mentionContent;
        }

        $body['embeds'] = [$embed];

        // Link buttons as action row components
        if (! empty($payload->buttons)) {
            $components = [];
            foreach ($payload->buttons as $button) {
                $components[] = [
                    'type' => 2,       // BUTTON
                    'style' => 5,      // LINK
                    'label' => $button['label'],
                    'url' => $button['url'],
                ];
            }
            $body['components'] = [
                [
                    'type' => 1,       // ACTION_ROW
                    'components' => $components,
                ],
            ];
        }

        // Relative timestamp string for field use: <t:UNIX:R>
        // Available as a helper but not injected automatically to avoid duplication.

        // allowed_mentions — safety block
        $body['allowed_mentions'] = self::buildAllowedMentions($payload);

        return $body;
    }

    // -----------------------------------------------------------------------
    // Public helpers
    // -----------------------------------------------------------------------

    /**
     * Return <t:UNIX:R> relative timestamp string for use in field values.
     */
    public static function relativeTimestamp(Payload $payload): string
    {
        $ts = $payload->timestamp ?? now();

        return '<t:'.$ts->getTimestamp().':R>';
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

    private static function color(string $severity): int
    {
        /** @var array<string, int> $map */
        $map = config('howl.colors', []);

        return $map[$severity] ?? 0;
    }

    /** @return array{name: string, icon_url?: string} */
    private static function buildAuthor(Payload $payload, string $emoji): array
    {
        $format = config('howl.username_format', '{severity_emoji} {app} · {env} · {channel}');
        $app = $payload->app ?? config('howl.app_name', config('app.name', 'App'));
        $env = $payload->env ?? config('howl.app_env', config('app.env', 'local'));
        $channel = $payload->channel ?? 'general';

        $name = str_replace(
            ['{severity_emoji}', '{app}', '{env}', '{channel}', '{severity}'],
            [$emoji, $app, $env, $channel, $payload->severity],
            $format,
        );

        $author = ['name' => $name];

        $avatarUrl = config('howl.drivers.discord.avatar_url');
        if ($avatarUrl) {
            $author['icon_url'] = $avatarUrl;
        }

        return $author;
    }

    private static function buildUsername(Payload $payload, string $emoji): string
    {
        // Per-call override takes precedence
        if ($payload->username !== null && $payload->username !== '') {
            return $payload->username;
        }

        $format = config('howl.username_format', '{severity_emoji} {app} · {env} · {channel}');
        $app = $payload->app ?? config('howl.app_name', config('app.name', 'App'));
        $env = $payload->env ?? config('howl.app_env', config('app.env', 'local'));
        $channel = $payload->channel ?? 'general';

        return str_replace(
            ['{severity_emoji}', '{app}', '{env}', '{channel}', '{severity}'],
            [$emoji, $app, $env, $channel, $payload->severity],
            $format,
        );
    }

    private static function buildMentionContent(Payload $payload): string
    {
        $parts = [];
        foreach ($payload->mentions as $mention) {
            $type = $mention['type'];
            $id = $mention['id'] ?? '';

            $parts[] = match ($type) {
                'user' => "<@{$id}>",
                'role' => "<@&{$id}>",
                'here' => '@here',
                'everyone' => '@everyone',
                default => '',
            };
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * Build the allowed_mentions block.
     *
     * Policy:
     *  - parse is empty by default (blocks @everyone / @here mass pings).
     *  - Only IDs the user explicitly mentioned are passed through.
     *  - If the user explicitly called ->mention('everyone'), add 'everyone' to parse.
     *  - If the user explicitly called ->mention('here'), add 'here' to parse.
     *
     * @return array{parse: array<int, string>, users: array<int, string>, roles: array<int, string>}
     */
    private static function buildAllowedMentions(Payload $payload): array
    {
        $parse = [];
        $userIds = [];
        $roleIds = [];

        foreach ($payload->mentions as $mention) {
            $type = $mention['type'];
            $id = $mention['id'] ?? '';

            match ($type) {
                'user' => $userIds[] = $id,
                'role' => $roleIds[] = $id,
                'everyone' => $parse[] = 'everyone',
                'here' => $parse[] = 'here',
                default => null,
            };
        }

        return [
            'parse' => array_values(array_unique($parse)),
            'users' => array_values(array_unique($userIds)),
            'roles' => array_values(array_unique($roleIds)),
        ];
    }
}
