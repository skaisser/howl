<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Skaisser\Howl\Drivers\DiscordDriver;
use Skaisser\Howl\Support\Payload;

function discordPayload(array $overrides = []): Payload
{
    return new Payload(
        title: $overrides['title'] ?? 'Test',
        description: $overrides['description'] ?? null,
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
        app: $overrides['app'] ?? 'MyApp',
        env: $overrides['env'] ?? 'production',
        timestamp: $overrides['timestamp'] ?? new DateTimeImmutable('2026-05-11 06:57:00'),
        forceSync: $overrides['forceSync'] ?? false,
    );
}

// -------------------------------------------------------------------------
// HTTP status tests
// -------------------------------------------------------------------------

it('returns true on 204 No Content (Discord canonical success)', function () {
    config(['howl.drivers.discord.webhook_url' => 'https://discord.test/webhook']);
    config(['howl.drivers.discord.channels' => []]);
    config(['howl.drivers.discord.threads' => []]);

    Http::fake(['*' => Http::response('', 204)]);

    $driver = new DiscordDriver;
    $result = $driver->send(discordPayload(['channel' => 'general']));

    expect($result)->toBeTrue();
    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'discord.test'));
});

it('returns true on generic 200 response', function () {
    config(['howl.drivers.discord.webhook_url' => 'https://discord.test/webhook']);
    config(['howl.drivers.discord.channels' => []]);
    config(['howl.drivers.discord.threads' => []]);

    Http::fake(['*' => Http::response('{"id":"123"}', 200)]);

    $driver = new DiscordDriver;
    $result = $driver->send(discordPayload(['channel' => 'general']));

    expect($result)->toBeTrue();
});

it('returns false on 500 server error', function () {
    config(['howl.drivers.discord.webhook_url' => 'https://discord.test/webhook']);
    config(['howl.drivers.discord.channels' => []]);
    config(['howl.drivers.discord.threads' => []]);

    Http::fake(['*' => Http::response('Internal Server Error', 500)]);

    $driver = new DiscordDriver;
    $result = $driver->send(discordPayload(['channel' => 'general']));

    expect($result)->toBeFalse();
});

it('returns false on connection timeout exception', function () {
    config(['howl.drivers.discord.webhook_url' => 'https://discord.test/webhook']);
    config(['howl.drivers.discord.channels' => []]);
    config(['howl.drivers.discord.threads' => []]);

    Http::fake(['*' => function () {
        throw new ConnectionException('Connection timed out');
    }]);

    $driver = new DiscordDriver;
    $result = $driver->send(discordPayload(['channel' => 'general']));

    expect($result)->toBeFalse();
});

// -------------------------------------------------------------------------
// Thread routing resolution tests
// -------------------------------------------------------------------------

it('uses per-category webhook URL directly (no thread_id) when channels config is set', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.test/default-webhook',
        'howl.drivers.discord.channels' => [
            'errors' => 'https://discord.test/errors-webhook',
        ],
        'howl.drivers.discord.threads' => [
            'errors' => '99999',
        ],
    ]);

    Http::fake(['*' => Http::response('', 204)]);

    $driver = new DiscordDriver;
    $driver->send(discordPayload(['channel' => 'errors', 'threadId' => null]));

    Http::assertSent(function (Request $req) {
        // Must use the per-category URL
        expect($req->url())->toContain('errors-webhook')
            ->and($req->url())->not->toContain('thread_id');

        return true;
    });
});

it('uses default webhook URL + thread_id query param when channels config is empty', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.test/default-webhook',
        'howl.drivers.discord.channels' => [
            'errors' => null,  // not set
        ],
        'howl.drivers.discord.threads' => [
            'errors' => '12345',
        ],
    ]);

    Http::fake(['*' => Http::response('', 204)]);

    $driver = new DiscordDriver;
    $driver->send(discordPayload(['channel' => 'errors', 'threadId' => null]));

    Http::assertSent(function (Request $req) {
        expect($req->url())->toContain('default-webhook')
            ->and($req->url())->toContain('thread_id=12345');

        return true;
    });
});

it('posts to channel root (no thread_id) when neither channels nor threads config is set', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.test/default-webhook',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [],
    ]);

    Http::fake(['*' => Http::response('', 204)]);

    $driver = new DiscordDriver;
    $driver->send(discordPayload(['channel' => 'general', 'threadId' => null]));

    Http::assertSent(function (Request $req) {
        expect($req->url())->not->toContain('thread_id');

        return true;
    });
});

it('explicit ->thread() payload threadId overrides the config-resolved thread', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.test/default-webhook',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [
            'errors' => '11111',
        ],
    ]);

    Http::fake(['*' => Http::response('', 204)]);

    $driver = new DiscordDriver;
    // Explicit threadId=99999 in payload overrides the config thread 11111
    $driver->send(discordPayload(['channel' => 'errors', 'threadId' => '99999']));

    Http::assertSent(function (Request $req) {
        expect($req->url())->toContain('thread_id=99999')
            ->and($req->url())->not->toContain('thread_id=11111');

        return true;
    });
});

it('honors timeout from config', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.test/webhook',
        'howl.drivers.discord.timeout' => 5,
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [],
    ]);

    Http::fake(['*' => Http::response('', 204)]);

    $driver = new DiscordDriver;
    $result = $driver->send(discordPayload(['channel' => 'general']));

    // Just confirm it sends without error; timeout is set internally
    expect($result)->toBeTrue();
});

// -------------------------------------------------------------------------
// Multipart (attachment) path — exercises sendMultipart()
// -------------------------------------------------------------------------

it('uses multipart POST when payload has attachments', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.test/webhook',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [],
    ]);

    // Create a temporary file to attach
    $tmpFile = tempnam(sys_get_temp_dir(), 'howl_test_');
    file_put_contents($tmpFile, 'test attachment content');

    Http::fake(['*' => Http::response('', 204)]);

    $driver = new DiscordDriver;
    $result = $driver->send(discordPayload(['attachments' => [$tmpFile]]));

    unlink($tmpFile);

    expect($result)->toBeTrue();
    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'discord.test'));
});

it('multipart path with thread_id appends thread to URL', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.test/webhook',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => ['errors' => '55555'],
    ]);

    $tmpFile = tempnam(sys_get_temp_dir(), 'howl_test_');
    file_put_contents($tmpFile, 'log content');

    Http::fake(['*' => Http::response('', 204)]);

    $driver = new DiscordDriver;
    $driver->send(discordPayload(['channel' => 'errors', 'attachments' => [$tmpFile]]));

    unlink($tmpFile);

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'thread_id=55555'));
});

// -------------------------------------------------------------------------
// Driver identity
// -------------------------------------------------------------------------

it('has name discord', function () {
    expect((new DiscordDriver)->name())->toBe('discord');
});
