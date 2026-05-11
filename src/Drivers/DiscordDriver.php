<?php

namespace Skaisser\Howl\Drivers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Support\EmbedBuilder;
use Skaisser\Howl\Support\Payload;

/**
 * Discord webhook driver with thread-routing-first architecture.
 *
 * Thread resolution order:
 *  1. If config('howl.drivers.discord.channels.<channel>') is non-empty
 *     → use that per-category webhook URL directly (no thread_id query param).
 *  2. Otherwise → use config('howl.drivers.discord.webhook_url') + ?thread_id=N
 *     where N = config('howl.drivers.discord.threads.<channel>').
 *     If no thread ID configured → post to channel root (omit ?thread_id).
 *  3. If $payload->threadId is explicitly set via ->thread() → override resolved thread_id.
 *
 * HTTP success: 204 No Content (Discord canonical) OR any other 2xx.
 * Anything else → false. All exceptions caught → false.
 */
class DiscordDriver implements Driver
{
    public function name(): string
    {
        return 'discord';
    }

    public function send(Payload $payload): bool
    {
        try {
            [$url, $threadId] = $this->resolveEndpoint($payload);

            $body = EmbedBuilder::build($payload);
            $timeout = (int) config('howl.drivers.discord.timeout', 10);

            if (! empty($payload->attachments)) {
                $response = $this->sendMultipart($url, $threadId, $body, $payload->attachments, $timeout);
            } else {
                $targetUrl = $threadId ? $url.'?thread_id='.$threadId : $url;
                $response = Http::timeout($timeout)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($targetUrl, $body);
            }

            $status = $response->status();

            // 204 is Discord's canonical success; accept any 2xx for safety.
            return $status === 204 || ($status >= 200 && $status < 300);
        } catch (\Throwable) {
            return false;
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve [url, threadId|null] using the 3-step resolution order.
     *
     * @return array{0: string, 1: string|null}
     */
    private function resolveEndpoint(Payload $payload): array
    {
        $channel = $payload->channel ?? 'general';

        // Step 1 — per-category webhook URL (progressive enhancement)
        $channelUrl = config('howl.drivers.discord.channels.'.$channel);
        if (! empty($channelUrl)) {
            // Per-category URL: no thread routing, but still honour explicit ->thread()
            $threadId = $payload->threadId ?: null;

            return [$channelUrl, $threadId];
        }

        // Step 2 — default webhook URL + thread from config
        $defaultUrl = config('howl.drivers.discord.webhook_url', '');
        $configThread = config('howl.drivers.discord.threads.'.$channel);
        $threadId = $configThread ? (string) $configThread : null;

        // Step 3 — per-call ->thread() override
        if ($payload->threadId !== null) {
            $threadId = $payload->threadId;
        }

        return [$defaultUrl, $threadId];
    }

    /**
     * Send multipart/form-data request when attachments are present.
     *
     * @param  array<int, string>  $attachments
     */
    private function sendMultipart(
        string $url,
        ?string $threadId,
        array $body,
        array $attachments,
        int $timeout,
    ): Response {
        $targetUrl = $threadId ? $url.'?thread_id='.$threadId : $url;
        $request = Http::timeout($timeout)->asMultipart();

        // payload_json field carries the embed body
        $request = $request->attach('payload_json', json_encode($body), null, ['Content-Type' => 'application/json']);

        foreach ($attachments as $index => $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new \InvalidArgumentException("Howl: attachment path is not a readable file: {$path}");
            }
            $filename = basename($path);
            $request = $request->attach('files['.$index.']', file_get_contents($path), $filename);
        }

        return $request->post($targetUrl);
    }
}
