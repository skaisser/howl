<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Skaisser\Howl\Drivers\TelegramDriver;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function telegramAttachmentPayload(array $overrides = []): Payload
{
    return new Payload(
        title: $overrides['title'] ?? 'Attachment Test',
        description: $overrides['description'] ?? 'Body text',
        severity: $overrides['severity'] ?? 'error',
        channel: $overrides['channel'] ?? 'errors',
        fields: $overrides['fields'] ?? [],
        codeBlocks: $overrides['codeBlocks'] ?? [],
        mentions: $overrides['mentions'] ?? [],
        meta: $overrides['meta'] ?? [],
        buttons: $overrides['buttons'] ?? [],
        attachments: $overrides['attachments'] ?? [],
        threadId: $overrides['threadId'] ?? null,
        username: $overrides['username'] ?? null,
        app: $overrides['app'] ?? 'TestApp',
        env: $overrides['env'] ?? 'testing',
        timestamp: $overrides['timestamp'] ?? null,
        forceSync: $overrides['forceSync'] ?? false,
    );
}

function telegramAttachmentDriverConfig(array $extra = []): void
{
    config(array_merge([
        'howl.drivers.telegram.bot_token' => '123456:ATTACH-TOKEN',
        'howl.drivers.telegram.chat_id' => '-1001234567890',
        'howl.drivers.telegram.threads' => ['errors' => 42],
        'howl.drivers.telegram.timeout' => 10,
        'howl.emojis.error' => '🚨',
        'howl.colors.error' => 15548997,
    ], $extra));
}

function makeTempAttachment(string $name, string $content = 'test'): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
    file_put_contents($path, $content);

    return $path;
}

/**
 * Extract a named field from a multipart request's data array.
 * Laravel's Http fake stores multipart parts as [{name, contents, ...}, ...].
 */
function multipartField(Request $req, string $fieldName): ?string
{
    foreach ($req->data() as $part) {
        if (is_array($part) && ($part['name'] ?? '') === $fieldName) {
            return (string) $part['contents'];
        }
    }

    return null;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('sends an image attachment via sendPhoto endpoint', function () {
    telegramAttachmentDriverConfig();

    $file = makeTempAttachment('howl-test.png', "\x89PNG\r\n\x1a\n");

    Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

    $driver = new TelegramDriver;
    $result = $driver->send(telegramAttachmentPayload(['attachments' => [$file]]));

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $req) {
        return str_contains($req->url(), 'sendPhoto');
    });

    unlink($file);
});

it('sends a document attachment via sendDocument endpoint', function () {
    telegramAttachmentDriverConfig();

    $file = makeTempAttachment('howl-test.log', 'error log content');

    Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

    $driver = new TelegramDriver;
    $result = $driver->send(telegramAttachmentPayload(['attachments' => [$file]]));

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $req) {
        return str_contains($req->url(), 'sendDocument');
    });

    unlink($file);
});

it('routes image .jpg extension to sendPhoto', function () {
    telegramAttachmentDriverConfig();

    $file = makeTempAttachment('howl-photo.jpg', 'fake jpg');

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramAttachmentPayload(['attachments' => [$file]]));

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'sendPhoto'));

    unlink($file);
});

it('routes .JPG (uppercase extension) to sendPhoto', function () {
    telegramAttachmentDriverConfig();

    // PHP sys_get_temp_dir returns a temp path; the extension is part of the base name
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'howl-UPPER.JPG';
    file_put_contents($path, 'fake jpg uppercase');

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramAttachmentPayload(['attachments' => [$path]]));

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'sendPhoto'));

    unlink($path);
});

it('first attachment carries caption, subsequent have empty caption', function () {
    telegramAttachmentDriverConfig();

    $file1 = makeTempAttachment('howl-first.png', 'img');
    $file2 = makeTempAttachment('howl-second.png', 'img2');

    $captionsSent = [];

    Http::fake([
        '*' => function (Request $req) use (&$captionsSent) {
            // Multipart data: extract caption field by name
            $captionsSent[] = multipartField($req, 'caption') ?? '';

            return Http::response(['ok' => true], 200);
        },
    ]);

    $driver = new TelegramDriver;
    $driver->send(telegramAttachmentPayload(['attachments' => [$file1, $file2]]));

    expect(count($captionsSent))->toBe(2);
    expect($captionsSent[0])->not->toBeEmpty(); // first carries body
    expect($captionsSent[1])->toBeEmpty();      // second is empty

    unlink($file1);
    unlink($file2);
});

it('mixed image and document: calls sendPhoto then sendDocument', function () {
    telegramAttachmentDriverConfig();

    $img = makeTempAttachment('howl-img.png', 'img');
    $doc = makeTempAttachment('howl-doc.zip', 'zip');

    $endpoints = [];

    Http::fake([
        '*' => function (Request $req) use (&$endpoints) {
            $endpoints[] = basename(parse_url($req->url(), PHP_URL_PATH));

            return Http::response(['ok' => true], 200);
        },
    ]);

    $driver = new TelegramDriver;
    $driver->send(telegramAttachmentPayload(['attachments' => [$img, $doc]]));

    expect($endpoints)->toContain('sendPhoto');
    expect($endpoints)->toContain('sendDocument');

    unlink($img);
    unlink($doc);
});

it('includes message_thread_id in multipart attachment requests', function () {
    telegramAttachmentDriverConfig(['howl.drivers.telegram.threads' => ['errors' => 99]]);

    $file = makeTempAttachment('howl-threaded.png', 'img');

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramAttachmentPayload(['channel' => 'errors', 'attachments' => [$file]]));

    Http::assertSent(function (Request $req) {
        // Multipart parts are stored as [{name, contents, ...}] — use helper to extract by name
        $threadId = multipartField($req, 'message_thread_id');
        expect($threadId)->toBe('99'); // multipart sends as string

        return true;
    });

    unlink($file);
});

it('returns false and does not attempt subsequent uploads when first upload fails', function () {
    telegramAttachmentDriverConfig();

    $file1 = makeTempAttachment('howl-fail1.png', 'img');
    $file2 = makeTempAttachment('howl-fail2.png', 'img');

    Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400], 200)]);

    $driver = new TelegramDriver;
    $result = $driver->send(telegramAttachmentPayload(['attachments' => [$file1, $file2]]));

    expect($result)->toBeFalse();
    Http::assertSentCount(1); // only the first upload attempted

    unlink($file1);
    unlink($file2);
});

it('throws InvalidArgumentException for non-readable attachment path', function () {
    telegramAttachmentDriverConfig();

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;

    expect(fn () => $driver->send(telegramAttachmentPayload([
        'attachments' => ['/tmp/howl-nonexistent-xyz123.png'],
    ])))->toThrow(\InvalidArgumentException::class, 'not a readable file');
});

it('does not call sendMessage when attachments are present', function () {
    telegramAttachmentDriverConfig();

    $file = makeTempAttachment('howl-no-msg.png', 'img');

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramAttachmentPayload(['attachments' => [$file]]));

    Http::assertNotSent(function (Request $req) {
        return str_contains($req->url(), 'sendMessage');
    });

    unlink($file);
});
