<?php

namespace Skaisser\Howl\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Support\BlockKitBuilder;
use Skaisser\Howl\Support\Payload;

/**
 * Slack driver — bot-token + chat.postMessage Web API.
 *
 * HTTP success definition: status 200 AND response JSON {ok: true}.
 * Slack returns 200 even on API errors; always check the `ok` field.
 *
 * Attachment uploads use the files.upload v2 three-step flow:
 *   1. files.getUploadURLExternal — obtain a pre-signed upload URL + file_id.
 *   2. PUT/POST file body to the pre-signed upload_url.
 *   3. files.completeUploadExternal — commit the upload and share to channel.
 *
 * Token security: the bot token (xoxb-...) must never appear in Log::error
 * payloads. Only log the driver name, channel, and a generic error message.
 */
class SlackDriver implements Driver
{
    public function name(): string
    {
        return 'slack';
    }

    public function send(Payload $payload): bool
    {
        try {
            $token = config('howl.drivers.slack.bot_token', '');
            $timeout = (int) config('howl.drivers.slack.timeout', 10);

            $channelId = $this->resolveChannelId($payload);

            if ($channelId === null) {
                Log::error('Howl: Slack channel id unresolved', [
                    'channel' => $payload->channel,
                ]);

                return false;
            }

            // Upload attachments before the main message (fail fast on upload error)
            if (! empty($payload->attachments)) {
                if (! $this->uploadAttachments($payload->attachments, $channelId, $token, $timeout)) {
                    return false;
                }
            }

            // POST the Block Kit message
            $body = BlockKitBuilder::build($payload, $channelId);

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://slack.com/api/chat.postMessage', $body);

            if ($response->status() !== 200) {
                return false;
            }

            return (bool) $response->json('ok', false);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable) {
            return false;
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve the Slack channel ID for the current payload channel.
     *
     * Precedence:
     *  1. Per-Howl-channel mapping: config('howl.drivers.slack.channels.<channel>')
     *  2. Default channel: config('howl.drivers.slack.default_channel')
     *  3. Null (caller logs and returns false)
     */
    private function resolveChannelId(Payload $payload): ?string
    {
        $channel = $payload->channel ?? '';

        if ($channel !== '') {
            $mapped = config('howl.drivers.slack.channels.'.$channel);
            if (! empty($mapped)) {
                return (string) $mapped;
            }
        }

        $default = config('howl.drivers.slack.default_channel');
        if (! empty($default)) {
            return (string) $default;
        }

        return null;
    }

    /**
     * Upload one or more attachment files via the files.upload v2 three-step flow.
     * Returns false immediately on any step failure (fail fast).
     *
     * @param  array<int, string>  $paths
     */
    private function uploadAttachments(array $paths, string $channelId, string $token, int $timeout): bool
    {
        foreach ($paths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new \InvalidArgumentException(
                    "Howl: attachment path is not a readable file: {$path}"
                );
            }

            $filename = basename($path);
            $length = filesize($path);

            // Step 1 — get pre-signed upload URL
            $upload = $this->getUploadUrl($filename, (int) $length, $token, $timeout);
            if ($upload === null) {
                return false;
            }

            // Step 2 — upload file body to pre-signed URL
            if (! $this->uploadFileBody($upload['upload_url'], $path, $timeout)) {
                return false;
            }

            // Step 3 — complete upload and share to channel
            if (! $this->completeUpload($upload['file_id'], $filename, $channelId, $token, $timeout)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Step 1: Obtain pre-signed upload URL and file_id.
     *
     * @return array{upload_url: string, file_id: string}|null
     */
    private function getUploadUrl(string $filename, int $length, string $token, int $timeout): ?array
    {
        $response = Http::timeout($timeout)
            ->withHeaders(['Authorization' => 'Bearer '.$token])
            ->asForm()
            ->post('https://slack.com/api/files.getUploadURLExternal', [
                'filename' => $filename,
                'length' => $length,
            ]);

        if ($response->status() !== 200 || ! $response->json('ok', false)) {
            return null;
        }

        return [
            'upload_url' => $response->json('upload_url'),
            'file_id' => $response->json('file_id'),
        ];
    }

    /**
     * Step 2: Upload the raw file body to the pre-signed URL.
     */
    private function uploadFileBody(string $uploadUrl, string $path, int $timeout): bool
    {
        $response = Http::timeout($timeout)
            ->asMultipart()
            ->attach('file', file_get_contents($path), basename($path))
            ->post($uploadUrl);

        return $response->successful();
    }

    /**
     * Step 3: Complete the upload and share it to the target channel.
     */
    private function completeUpload(string $fileId, string $title, string $channelId, string $token, int $timeout): bool
    {
        $response = Http::timeout($timeout)
            ->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post('https://slack.com/api/files.completeUploadExternal', [
                'files' => [['id' => $fileId, 'title' => $title]],
                'channel_id' => $channelId,
            ]);

        if ($response->status() !== 200) {
            return false;
        }

        return (bool) $response->json('ok', false);
    }
}
