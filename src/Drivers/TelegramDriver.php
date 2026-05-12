<?php

namespace Skaisser\Howl\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Support\Payload;
use Skaisser\Howl\Support\TelegramHtmlBuilder;

/**
 * Telegram driver — Bot HTTP API with HTML parse_mode.
 *
 * HTTP success definition: status 200 AND response JSON {ok: true}.
 * Telegram returns 200 even on API errors; always check the `ok` field.
 *
 * Thread routing via message_thread_id:
 *   Requires a supergroup with Forum mode enabled. The bot must be a member.
 *   map: config('howl.drivers.telegram.threads.<channel>') → integer topic ID.
 *   If no mapping exists, the message lands in the General topic (no thread_id).
 *
 * Attachment routing:
 *   Images (.jpg/.jpeg/.png/.gif/.webp) → sendPhoto
 *   Everything else                      → sendDocument
 *   First attachment carries the full HTML body as caption.
 *   When attachments are present, the standalone sendMessage call is SKIPPED.
 *
 * URL buttons render as reply_markup.inline_keyboard URL buttons (no callback_data).
 */
class TelegramDriver implements Driver
{
    public function name(): string
    {
        return 'telegram';
    }

    public function send(Payload $payload): bool
    {
        try {
            $token = config('howl.drivers.telegram.bot_token', '');
            $timeout = (int) config('howl.drivers.telegram.timeout', 10);

            $chatId = config('howl.drivers.telegram.chat_id');
            if (empty($chatId)) {
                Log::error('Howl: Telegram chat_id is not configured', [
                    'channel' => $payload->channel,
                ]);

                return false;
            }

            $threadId = $this->resolveThreadId($payload);
            $body = TelegramHtmlBuilder::build($payload);

            // When attachments are present, delegate entirely to uploadAttachments.
            // The first attachment carries $body as caption; sendMessage is SKIPPED.
            if (! empty($payload->attachments)) {
                return $this->uploadAttachments(
                    $payload->attachments,
                    (string) $chatId,
                    $threadId,
                    $body,
                    $token,
                    $timeout
                );
            }

            // No attachments — send a plain HTML message
            $params = [
                'chat_id' => $chatId,
                'text' => $body,
                'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true],
            ];

            if ($threadId !== null) {
                $params['message_thread_id'] = $threadId;
            }

            $replyMarkup = $this->buildReplyMarkup($payload->buttons);
            if ($replyMarkup !== null) {
                $params['reply_markup'] = $replyMarkup;
            }

            $response = Http::timeout($timeout)
                ->asForm()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $params);

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
     * Resolve forum thread ID from the payload's channel name.
     * Returns null when no mapping is configured (message lands in General).
     * Always cast to int — env values arrive as strings.
     */
    private function resolveThreadId(Payload $payload): ?int
    {
        $channel = $payload->channel ?? '';
        if ($channel === '') {
            return null;
        }

        $threadId = config('howl.drivers.telegram.threads.'.$channel);
        if ($threadId === null || $threadId === '') {
            return null;
        }

        return (int) $threadId;
    }

    /**
     * Build the reply_markup array for URL buttons (no callback_data).
     *
     * @param  array<int, array{label: string, url: string}>  $buttons
     * @return array{inline_keyboard: array<int, array<int, array{text: string, url: string}>>}|null
     */
    private function buildReplyMarkup(array $buttons): ?array
    {
        if (empty($buttons)) {
            return null;
        }

        $row = [];
        foreach ($buttons as $button) {
            $row[] = [
                'text' => $button['label'],
                'url' => $button['url'],
            ];
        }

        return ['inline_keyboard' => [$row]];
    }

    /**
     * Detect whether a file path is an image by extension.
     * Image extensions: jpg, jpeg, png, gif, webp (case-insensitive).
     */
    private static function isImage(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /**
     * Upload one or more attachments using sendPhoto or sendDocument.
     * First attachment carries the full body as caption; subsequent ones have empty caption.
     * Fails fast on any upload error (subsequent uploads are not attempted).
     *
     * @param  array<int, string>  $paths
     */
    private function uploadAttachments(
        array $paths,
        string $chatId,
        ?int $threadId,
        string $body,
        string $token,
        int $timeout
    ): bool {
        $isFirst = true;

        foreach ($paths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new \InvalidArgumentException(
                    "Howl: attachment path is not a readable file: {$path}"
                );
            }

            $caption = $isFirst ? $body : '';
            $isFirst = false;

            $success = self::isImage($path)
                ? $this->sendPhoto($path, $chatId, $threadId, $caption, $token, $timeout)
                : $this->sendDocument($path, $chatId, $threadId, $caption, $token, $timeout);

            if (! $success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send an image file via sendPhoto.
     */
    private function sendPhoto(
        string $path,
        string $chatId,
        ?int $threadId,
        string $caption,
        string $token,
        int $timeout
    ): bool {
        $request = Http::timeout($timeout)->asMultipart();
        $request = $request->attach('chat_id', $chatId);

        if ($threadId !== null) {
            $request = $request->attach('message_thread_id', (string) $threadId);
        }

        $request = $request->attach('photo', file_get_contents($path), basename($path));

        // Only attach caption when non-empty — Laravel's array_filter in attach()
        // drops entries with empty string values, which causes Guzzle to reject the part.
        if ($caption !== '') {
            $request = $request->attach('caption', $caption)->attach('parse_mode', 'HTML');
        }

        $response = $request->post("https://api.telegram.org/bot{$token}/sendPhoto");

        if ($response->status() !== 200) {
            return false;
        }

        return (bool) $response->json('ok', false);
    }

    /**
     * Send a non-image file via sendDocument.
     */
    private function sendDocument(
        string $path,
        string $chatId,
        ?int $threadId,
        string $caption,
        string $token,
        int $timeout
    ): bool {
        $request = Http::timeout($timeout)->asMultipart();
        $request = $request->attach('chat_id', $chatId);

        if ($threadId !== null) {
            $request = $request->attach('message_thread_id', (string) $threadId);
        }

        $request = $request->attach('document', file_get_contents($path), basename($path));

        // Only attach caption when non-empty — Laravel's array_filter in attach()
        // drops entries with empty string values, which causes Guzzle to reject the part.
        if ($caption !== '') {
            $request = $request->attach('caption', $caption)->attach('parse_mode', 'HTML');
        }

        $response = $request->post("https://api.telegram.org/bot{$token}/sendDocument");

        if ($response->status() !== 200) {
            return false;
        }

        return (bool) $response->json('ok', false);
    }
}
