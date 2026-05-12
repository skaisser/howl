<?php

namespace Skaisser\Howl\Support;

/**
 * Converts a Payload into an HTML-formatted string for Telegram's sendMessage API.
 *
 * Telegram supports the following HTML tags in parse_mode=HTML:
 *   <b>, <i>, <code>, <pre>, <a href="...">, <u>, <s>
 *   <pre><code class="language-{lang}">...</code></pre> for code blocks.
 *
 * All user-supplied strings are HTML-escaped before tag wrapping.
 * Only <, >, and & require escaping per Telegram's specification.
 *
 * Mention translation:
 *   user      → <a href="tg://user?id={id}">user</a>
 *   here      → skipped (no @here equivalent in Telegram)
 *   everyone  → skipped (no @everyone equivalent in Telegram)
 *   role      → skipped (no role concept in Telegram)
 */
final class TelegramHtmlBuilder
{
    /**
     * Build the HTML message body string.
     *
     * This string is used as the `text` field of sendMessage, or as the
     * `caption` field of sendPhoto/sendDocument (first attachment only).
     */
    public static function build(Payload $payload): string
    {
        $severity = $payload->severity;
        $emoji = self::emoji($severity);

        $lines = [];

        // Leading mentions paragraph (user mentions only)
        $mentionText = self::buildMentions($payload);
        if ($mentionText !== '') {
            $lines[] = $mentionText;
        }

        // Title line: <b>{emoji} {title}</b>
        $lines[] = '<b>'.self::escape($emoji.' '.$payload->title).'</b>';

        // Blank separator before description/fields
        if ($payload->description !== null && $payload->description !== '') {
            $lines[] = '';
            $lines[] = self::escape($payload->description);
        }

        // Fields — each on its own line: <b>Name:</b> value
        if (! empty($payload->fields)) {
            $lines[] = '';
            foreach ($payload->fields as $field) {
                $lines[] = '<b>'.self::escape($field['name'] ?? '').': </b>'.self::escape($field['value'] ?? '');
            }
        }

        // Code blocks — <pre><code class="language-{lang}">...</code></pre>
        foreach ($payload->codeBlocks as $block) {
            $lang = $block['lang'] ?? '';
            $code = $block['code'] ?? '';
            $classAttr = $lang !== '' ? ' class="language-'.$lang.'"' : '';
            $lines[] = '';
            $lines[] = '<pre><code'.$classAttr.'>'.self::escape($code).'</code></pre>';
        }

        // URL buttons — rendered as <a href="...">label</a>
        if (! empty($payload->buttons)) {
            $lines[] = '';
            foreach ($payload->buttons as $button) {
                $lines[] = '<a href="'.self::escape($button['url'] ?? '').'">'.self::escape($button['label'] ?? '').'</a>';
            }
        }

        // Footer: app · env · timestamp in italic
        $app = $payload->app ?? config('howl.app_name', config('app.name', 'App'));
        $env = $payload->env ?? config('howl.app_env', config('app.env', 'local'));
        $ts = $payload->timestamp ?? now();
        $tsFormatted = ($ts instanceof \DateTimeImmutable
            ? $ts
            : \DateTimeImmutable::createFromInterface($ts))->format('Y-m-d H:i:s \U\T\C');

        $lines[] = '';
        $lines[] = '<i>'.self::escape("{$app} · {$env} · {$tsFormatted}").'</i>';

        return implode("\n", $lines);
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
     * HTML-escape user-supplied content for Telegram's HTML parse mode.
     * Only <, >, and & need escaping per Telegram spec.
     */
    private static function escape(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Build the leading mentions paragraph.
     * Only `user` type is supported; all other types are silently skipped.
     */
    private static function buildMentions(Payload $payload): string
    {
        $parts = [];

        foreach ($payload->mentions as $mention) {
            if (($mention['type'] ?? '') === 'user') {
                $id = $mention['id'] ?? '';
                $parts[] = '<a href="tg://user?id='.$id.'">user</a>';
            }
            // here, everyone, role → silently skipped
        }

        return implode(' ', $parts);
    }
}
