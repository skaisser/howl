<?php

namespace Skaisser\Howl\Support;

use DateTimeInterface;

/**
 * Serializes payload metadata into the bot-parseable pipe-delimited footer.
 *
 * Output format:
 *   event:X · severity:Y · env:Z · trace:W · <user-meta...> · dd/mm/yyyy hh:mm
 *
 * Rules:
 *  - Values containing '·' are sanitized (replaced with '•').
 *  - Auto-injected keys (event, severity, env, trace) always come first.
 *  - User-supplied meta keys follow in insertion order.
 *  - Timestamp is always last.
 */
final class FooterSerializer
{
    private const SEPARATOR = ' · ';

    private const SANITIZE_FROM = '·';

    private const SANITIZE_TO = '•';

    /** @param array<string, mixed> $meta */
    public static function serialize(
        string $severity,
        array $meta = [],
        ?string $env = null,
        ?DateTimeInterface $timestamp = null,
    ): string {
        $parts = [];

        // Auto-inject: event (if present in meta)
        if (isset($meta['event'])) {
            $parts[] = 'event:'.self::sanitize((string) $meta['event']);
        }

        // Auto-inject: severity (always)
        $parts[] = 'severity:'.self::sanitize($severity);

        // Auto-inject: env (always, even if null — show dash)
        $resolvedEnv = $env ?? config('app.env', 'local');
        $parts[] = 'env:'.self::sanitize((string) $resolvedEnv);

        // Auto-inject: trace (if present in meta)
        if (isset($meta['trace'])) {
            $parts[] = 'trace:'.self::sanitize((string) $meta['trace']);
        }

        // User-supplied meta keys — insertion order, skip auto-injected ones.
        // baseFooterMeta() injects 'event', 'severity', 'env', 'trace', 'timestamp' into
        // event payloads' meta; without skipping all of them here, the serializer would
        // emit duplicate keys (e.g. severity:X · ... · severity:X again from meta).
        $autoKeys = ['event', 'severity', 'env', 'trace', 'timestamp'];
        foreach ($meta as $key => $value) {
            if (in_array($key, $autoKeys, strict: true)) {
                continue;
            }
            $parts[] = self::sanitize((string) $key).':'.self::sanitize((string) $value);
        }

        // Timestamp — always last
        $ts = $timestamp ?? now();
        $parts[] = $ts->format('d/m/Y H:i');

        return implode(self::SEPARATOR, $parts);
    }

    private static function sanitize(string $value): string
    {
        return str_replace(self::SANITIZE_FROM, self::SANITIZE_TO, $value);
    }
}
