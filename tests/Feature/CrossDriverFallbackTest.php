<?php

use Illuminate\Support\Facades\Http;
use Skaisser\Howl\Facades\Howl as HowlFacade;
use Skaisser\Howl\Howl as HowlClass;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function crossDriverPayload(?string $fallback = null): Payload
{
    return new Payload(
        title: 'Cross-Driver Test',
        description: 'Testing driver fallback chain',
        severity: 'error',
        channel: 'errors',
        fields: [],
        codeBlocks: [],
        mentions: [],
        meta: [],
        buttons: [],
        attachments: [],
        threadId: null,
        username: null,
        app: 'TestApp',
        env: 'production',
        timestamp: null,
        forceSync: true,
        fallback: $fallback,
    );
}

/**
 * Build a Howl instance with no skip_environments and no queue (sync dispatch).
 */
function crossDriverHowl(array $configOverrides = []): HowlClass
{
    $config = array_merge(
        app('config')->get('howl', []),
        [
            'skip_environments' => [],
            'app_env' => 'production',
            'queue' => false,
        ],
        $configOverrides,
    );

    return new HowlClass($config);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('discord → slack fallback: discord 404, slack ok:true → returns true', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.com/api/webhooks/fail/abc',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [],
        'howl.drivers.slack.bot_token' => 'xoxb-fallback-token',
        'howl.drivers.slack.default_channel' => 'C0FALLBACK',
        'howl.drivers.slack.channels' => [],
        'howl.drivers.slack.timeout' => 10,
    ]);

    Http::fake([
        'discord.com/*' => Http::response('', 404),
        'slack.com/*' => Http::response(['ok' => true], 200),
    ]);

    $howl = crossDriverHowl([
        'driver' => 'discord',
        'fallback' => 'slack',
    ]);

    $result = $howl->dispatch(crossDriverPayload());

    expect($result)->toBeTrue();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'discord.com'));
    Http::assertSent(fn ($req) => str_contains($req->url(), 'chat.postMessage'));
});

it('discord → telegram fallback: discord 404, telegram ok:true → returns true', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.com/api/webhooks/fail/abc',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [],
        'howl.drivers.telegram.bot_token' => '123456:FALLBACK-TOKEN',
        'howl.drivers.telegram.chat_id' => '-1001234567890',
        'howl.drivers.telegram.threads' => [],
        'howl.drivers.telegram.timeout' => 10,
    ]);

    Http::fake([
        'discord.com/*' => Http::response('', 404),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $howl = crossDriverHowl([
        'driver' => 'discord',
        'fallback' => 'telegram',
    ]);

    $result = $howl->dispatch(crossDriverPayload());

    expect($result)->toBeTrue();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'discord.com'));
    Http::assertSent(fn ($req) => str_contains($req->url(), 'api.telegram.org'));
});

it('slack → discord fallback: slack ok:false, discord 204 → returns true', function () {
    config([
        'howl.drivers.slack.bot_token' => 'xoxb-primary',
        'howl.drivers.slack.default_channel' => 'C0PRIMARY',
        'howl.drivers.slack.channels' => [],
        'howl.drivers.slack.timeout' => 10,
        'howl.drivers.discord.webhook_url' => 'https://discord.com/api/webhooks/ok/xyz',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [],
    ]);

    Http::fake([
        'slack.com/*' => Http::response(['ok' => false, 'error' => 'channel_not_found'], 200),
        'discord.com/*' => Http::response('', 204),
    ]);

    $howl = crossDriverHowl([
        'driver' => 'slack',
        'fallback' => 'discord',
    ]);

    $result = $howl->dispatch(crossDriverPayload());

    expect($result)->toBeTrue();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'chat.postMessage'));
    Http::assertSent(fn ($req) => str_contains($req->url(), 'discord.com'));
});

it('telegram → discord fallback: telegram ok:false, discord 204 → returns true', function () {
    config([
        'howl.drivers.telegram.bot_token' => '123456:PRIMARY-TOKEN',
        'howl.drivers.telegram.chat_id' => '-1001234567890',
        'howl.drivers.telegram.threads' => [],
        'howl.drivers.telegram.timeout' => 10,
        'howl.drivers.discord.webhook_url' => 'https://discord.com/api/webhooks/ok/xyz',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [],
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 400], 200),
        'discord.com/*' => Http::response('', 204),
    ]);

    $howl = crossDriverHowl([
        'driver' => 'telegram',
        'fallback' => 'discord',
    ]);

    $result = $howl->dispatch(crossDriverPayload());

    expect($result)->toBeTrue();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'api.telegram.org'));
    Http::assertSent(fn ($req) => str_contains($req->url(), 'discord.com'));
});

it('all-fail scenario: discord 404, slack ok:false → returns false, both endpoints called once', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.com/api/webhooks/fail/abc',
        'howl.drivers.discord.channels' => [],
        'howl.drivers.discord.threads' => [],
        'howl.drivers.slack.bot_token' => 'xoxb-allfail',
        'howl.drivers.slack.default_channel' => 'C0ALLFAIL',
        'howl.drivers.slack.channels' => [],
        'howl.drivers.slack.timeout' => 10,
    ]);

    Http::fake([
        'discord.com/*' => Http::response('', 404),
        'slack.com/*' => Http::response(['ok' => false, 'error' => 'channel_not_found'], 200),
    ]);

    $howl = crossDriverHowl([
        'driver' => 'discord',
        'fallback' => 'slack',
    ]);

    $result = $howl->dispatch(crossDriverPayload());

    expect($result)->toBeFalse();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'discord.com'));
    Http::assertSent(fn ($req) => str_contains($req->url(), 'chat.postMessage'));
});
