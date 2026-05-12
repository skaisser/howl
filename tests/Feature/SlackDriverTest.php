<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Skaisser\Howl\Drivers\SlackDriver;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Helper: build a minimal Payload for Slack tests
// ---------------------------------------------------------------------------

function slackPayload(array $overrides = []): Payload
{
    return new Payload(
        title: $overrides['title'] ?? 'Test Title',
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
        app: $overrides['app'] ?? 'TestApp',
        env: $overrides['env'] ?? 'testing',
        timestamp: $overrides['timestamp'] ?? null,
        forceSync: $overrides['forceSync'] ?? false,
    );
}

function slackDriverConfig(array $extra = []): void
{
    config(array_merge([
        'howl.drivers.slack.bot_token' => 'xoxb-test-token',
        'howl.drivers.slack.default_channel' => 'C0DEFAULT',
        'howl.drivers.slack.channels' => [
            'errors' => 'C0ERRORS',
            'warnings' => 'C0WARNINGS',
        ],
        'howl.drivers.slack.timeout' => 10,
        'howl.emojis' => [
            'error' => '🚨',
            'warning' => '🟡',
            'info' => 'ℹ️',
            'success' => '✅',
            'audit' => '🔒',
            'deployment' => '🚀',
        ],
        'howl.colors' => [
            'error' => 15548997,
            'warning' => 16765440,
            'info' => 3447003,
            'success' => 5763719,
            'audit' => 10181046,
            'deployment' => 1752220,
        ],
    ], $extra));
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('sends a basic Slack message and returns true on ok:true', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $result = $driver->send(slackPayload());

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $req) {
        return str_contains($req->url(), 'chat.postMessage');
    });
});

it('POSTs to the correct Slack endpoint with Bearer token', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload());

    Http::assertSent(function (Request $req) {
        return $req->url() === 'https://slack.com/api/chat.postMessage'
            && $req->header('Authorization')[0] === 'Bearer xoxb-test-token';
    });
});

it('includes severity emoji and title in the header block', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['severity' => 'error', 'title' => 'Production Down']));

    Http::assertSent(function (Request $req) {
        $body = $req->data();
        $blocks = $body['attachments'][0]['blocks'] ?? [];

        $headerBlock = collect($blocks)->firstWhere('type', 'header');
        expect($headerBlock)->not->toBeNull();
        expect($headerBlock['text']['text'])->toContain('🚨');
        expect($headerBlock['text']['text'])->toContain('Production Down');

        return true;
    });
});

it('uses correct hex color for severity', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['severity' => 'error']));

    Http::assertSent(function (Request $req) {
        $color = $req->data()['attachments'][0]['color'] ?? null;
        expect($color)->toBe('#ED4245'); // 15548997 → #ED4245

        return true;
    });
});

it('includes description in a section mrkdwn block', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['description' => 'Something went wrong']));

    Http::assertSent(function (Request $req) {
        $blocks = $req->data()['attachments'][0]['blocks'] ?? [];
        $sections = collect($blocks)->where('type', 'section')->values();
        $found = $sections->first(fn ($b) =>
            isset($b['text']) && str_contains($b['text']['text'] ?? '', 'Something went wrong')
        );
        expect($found)->not->toBeNull();

        return true;
    });
});

it('renders fields as a section block with fields array', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload([
        'fields' => [
            ['name' => 'Host', 'value' => 'web-01', 'inline' => true],
            ['name' => 'Code', 'value' => '500', 'inline' => true],
        ],
    ]));

    Http::assertSent(function (Request $req) {
        $blocks = $req->data()['attachments'][0]['blocks'] ?? [];
        $fieldBlock = collect($blocks)->first(fn ($b) => isset($b['fields']));
        expect($fieldBlock)->not->toBeNull();
        expect(count($fieldBlock['fields']))->toBe(2);

        return true;
    });
});

it('renders code blocks as triple-backtick mrkdwn sections', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload([
        'codeBlocks' => [
            ['name' => 'Trace', 'code' => 'Error at line 42', 'lang' => 'php'],
        ],
    ]));

    Http::assertSent(function (Request $req) {
        $blocks = $req->data()['attachments'][0]['blocks'] ?? [];
        $codeSection = collect($blocks)->first(fn ($b) =>
            ($b['type'] ?? '') === 'section'
            && str_contains($b['text']['text'] ?? '', '```php')
        );
        expect($codeSection)->not->toBeNull();

        return true;
    });
});

it('renders here mention as <!here>', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['mentions' => [['type' => 'here', 'id' => '']]]));

    Http::assertSent(function (Request $req) {
        $blocks = $req->data()['attachments'][0]['blocks'] ?? [];
        $mentionBlock = $blocks[0] ?? null;
        expect($mentionBlock['type'])->toBe('section');
        expect($mentionBlock['text']['text'])->toContain('<!here>');

        return true;
    });
});

it('renders everyone mention as <!channel>', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['mentions' => [['type' => 'everyone', 'id' => '']]]));

    Http::assertSent(function (Request $req) {
        $blocks = $req->data()['attachments'][0]['blocks'] ?? [];
        $text = $blocks[0]['text']['text'] ?? '';
        expect($text)->toContain('<!channel>');

        return true;
    });
});

it('renders role mention as <!subteam^ID>', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['mentions' => [['type' => 'role', 'id' => 'S0123']]]));

    Http::assertSent(function (Request $req) {
        $blocks = $req->data()['attachments'][0]['blocks'] ?? [];
        $text = $blocks[0]['text']['text'] ?? '';
        expect($text)->toContain('<!subteam^S0123>');

        return true;
    });
});

it('renders user mention as <@ID>', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['mentions' => [['type' => 'user', 'id' => 'U0123']]]));

    Http::assertSent(function (Request $req) {
        $blocks = $req->data()['attachments'][0]['blocks'] ?? [];
        $text = $blocks[0]['text']['text'] ?? '';
        expect($text)->toContain('<@U0123>');

        return true;
    });
});

it('routes to mapped channel ID from config', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['channel' => 'errors']));

    Http::assertSent(function (Request $req) {
        expect($req->data()['channel'])->toBe('C0ERRORS');

        return true;
    });
});

it('falls back to default_channel when no per-channel mapping', function () {
    slackDriverConfig(['howl.drivers.slack.channels' => []]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new SlackDriver;
    $driver->send(slackPayload(['channel' => 'unknown']));

    Http::assertSent(function (Request $req) {
        expect($req->data()['channel'])->toBe('C0DEFAULT');

        return true;
    });
});

it('returns false when Slack returns ok:false with status 200', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => false, 'error' => 'channel_not_found'], 200)]);

    $driver = new SlackDriver;
    $result = $driver->send(slackPayload());

    expect($result)->toBeFalse();
});

it('returns false when Slack returns HTTP 401', function () {
    slackDriverConfig();
    Http::fake(['*' => Http::response(['ok' => false, 'error' => 'not_authed'], 401)]);

    $driver = new SlackDriver;
    $result = $driver->send(slackPayload());

    expect($result)->toBeFalse();
});

it('returns false and logs error when channel id cannot be resolved', function () {
    slackDriverConfig([
        'howl.drivers.slack.channels' => [],
        'howl.drivers.slack.default_channel' => null,
    ]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'Slack channel id unresolved'));

    $driver = new SlackDriver;
    $result = $driver->send(slackPayload(['channel' => 'unknown']));

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

it('returns the driver name slack', function () {
    expect((new SlackDriver)->name())->toBe('slack');
});
