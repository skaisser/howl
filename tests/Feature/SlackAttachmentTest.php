<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Skaisser\Howl\Drivers\SlackDriver;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function slackAttachmentPayload(array $overrides = []): Payload
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

function slackAttachmentDriverConfig(): void
{
    config([
        'howl.drivers.slack.bot_token' => 'xoxb-test-token',
        'howl.drivers.slack.default_channel' => 'C0DEFAULT',
        'howl.drivers.slack.channels' => ['errors' => 'C0ERRORS'],
        'howl.drivers.slack.timeout' => 10,
        'howl.emojis.error' => '🚨',
        'howl.colors.error' => 15548997,
    ]);
}

/**
 * Create a temporary file for attachment tests.
 * Returns the path; caller must unlink after test.
 */
function makeTempFile(string $name = 'test.txt', string $content = 'test content'): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
    file_put_contents($path, $content);

    return $path;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('hits all 3 upload endpoints in order for a single attachment', function () {
    slackAttachmentDriverConfig();

    $tmpFile = makeTempFile('howl-test.txt', 'log content');

    Http::fake([
        'slack.com/api/files.getUploadURLExternal' => Http::response([
            'ok' => true,
            'upload_url' => 'https://files.slack.com/upload/v1/ABC123',
            'file_id' => 'F0TEST',
        ], 200),
        'files.slack.com/upload/v1/ABC123' => Http::response('', 200),
        'slack.com/api/files.completeUploadExternal' => Http::response(['ok' => true], 200),
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true], 200),
    ]);

    $driver = new SlackDriver;
    $result = $driver->send(slackAttachmentPayload(['attachments' => [$tmpFile]]));

    expect($result)->toBeTrue();

    Http::assertSentCount(4); // getUploadURL + uploadBody + completeUpload + chatPostMessage

    unlink($tmpFile);
});

it('hits 6 upload calls for 2 attachments plus one final chat.postMessage', function () {
    slackAttachmentDriverConfig();

    $file1 = makeTempFile('howl-a.txt', 'file a');
    $file2 = makeTempFile('howl-b.txt', 'file b');

    Http::fake([
        'slack.com/api/files.getUploadURLExternal' => Http::sequence()
            ->push(['ok' => true, 'upload_url' => 'https://files.slack.com/upload/v1/A', 'file_id' => 'FA'], 200)
            ->push(['ok' => true, 'upload_url' => 'https://files.slack.com/upload/v1/B', 'file_id' => 'FB'], 200),
        'files.slack.com/upload/v1/A' => Http::response('', 200),
        'files.slack.com/upload/v1/B' => Http::response('', 200),
        'slack.com/api/files.completeUploadExternal' => Http::sequence()
            ->push(['ok' => true], 200)
            ->push(['ok' => true], 200),
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true], 200),
    ]);

    $driver = new SlackDriver;
    $result = $driver->send(slackAttachmentPayload(['attachments' => [$file1, $file2]]));

    expect($result)->toBeTrue();
    Http::assertSentCount(7); // (3 × 2) + 1 postMessage

    unlink($file1);
    unlink($file2);
});

it('returns false and stops after getUploadURLExternal returns ok:false', function () {
    slackAttachmentDriverConfig();

    $tmpFile = makeTempFile('howl-fail.txt');

    Http::fake([
        'slack.com/api/files.getUploadURLExternal' => Http::response(['ok' => false, 'error' => 'invalid_token'], 200),
    ]);

    $driver = new SlackDriver;
    $result = $driver->send(slackAttachmentPayload(['attachments' => [$tmpFile]]));

    expect($result)->toBeFalse();
    Http::assertSentCount(1); // only the getUploadURL call was made

    unlink($tmpFile);
});

it('returns false when completeUploadExternal returns ok:false', function () {
    slackAttachmentDriverConfig();

    $tmpFile = makeTempFile('howl-complete-fail.txt');

    Http::fake([
        'slack.com/api/files.getUploadURLExternal' => Http::response([
            'ok' => true,
            'upload_url' => 'https://files.slack.com/upload/v1/XYZ',
            'file_id' => 'FXYZ',
        ], 200),
        'files.slack.com/upload/v1/XYZ' => Http::response('', 200),
        'slack.com/api/files.completeUploadExternal' => Http::response(['ok' => false, 'error' => 'error_uploading'], 200),
    ]);

    $driver = new SlackDriver;
    $result = $driver->send(slackAttachmentPayload(['attachments' => [$tmpFile]]));

    expect($result)->toBeFalse();
    Http::assertSentCount(3); // getUploadURL + uploadBody + completeUpload (no postMessage)

    unlink($tmpFile);
});

it('throws InvalidArgumentException for non-readable attachment path', function () {
    slackAttachmentDriverConfig();

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;

    expect(fn () => $driver->send(slackAttachmentPayload([
        'attachments' => ['/tmp/howl-nonexistent-file-xyz.txt'],
    ])))->toThrow(\InvalidArgumentException::class, 'not a readable file');
});

it('uploads attachments first then sends chat.postMessage with blocks', function () {
    slackAttachmentDriverConfig();

    $tmpFile = makeTempFile('howl-order.txt');

    $callOrder = [];

    Http::fake([
        'slack.com/api/files.getUploadURLExternal' => function () use (&$callOrder) {
            $callOrder[] = 'getUploadURL';

            return Http::response(['ok' => true, 'upload_url' => 'https://files.slack.com/upload/v1/ORD', 'file_id' => 'FORD'], 200);
        },
        'files.slack.com/upload/v1/ORD' => function () use (&$callOrder) {
            $callOrder[] = 'uploadBody';

            return Http::response('', 200);
        },
        'slack.com/api/files.completeUploadExternal' => function () use (&$callOrder) {
            $callOrder[] = 'completeUpload';

            return Http::response(['ok' => true], 200);
        },
        'slack.com/api/chat.postMessage' => function () use (&$callOrder) {
            $callOrder[] = 'chatPostMessage';

            return Http::response(['ok' => true], 200);
        },
    ]);

    $driver = new SlackDriver;
    $driver->send(slackAttachmentPayload(['attachments' => [$tmpFile]]));

    expect($callOrder)->toBe(['getUploadURL', 'uploadBody', 'completeUpload', 'chatPostMessage']);

    unlink($tmpFile);
});
