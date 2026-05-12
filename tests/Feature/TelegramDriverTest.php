<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Skaisser\Howl\Drivers\TelegramDriver;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function telegramPayload(array $overrides = []): Payload
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

function telegramDriverConfig(array $extra = []): void
{
    config(array_merge([
        'howl.drivers.telegram.bot_token' => '123456:ABC-DEF-TOKEN',
        'howl.drivers.telegram.chat_id' => '-1001234567890',
        'howl.drivers.telegram.threads' => [
            'errors' => 42,
            'warnings' => 43,
        ],
        'howl.drivers.telegram.timeout' => 10,
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
        ],
    ], $extra));
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('sends a basic Telegram message and returns true on ok:true', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

    $driver = new TelegramDriver;
    $result = $driver->send(telegramPayload());

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $req) {
        return str_contains($req->url(), 'sendMessage');
    });
});

it('POSTs to the correct URL including the bot token', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload());

    Http::assertSent(function (Request $req) {
        return str_contains($req->url(), 'bot123456:ABC-DEF-TOKEN/sendMessage');
    });
});

it('includes parse_mode HTML in the request', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload());

    Http::assertSent(function (Request $req) {
        return $req->data()['parse_mode'] === 'HTML';
    });
});

it('wraps title in <b> tags with severity emoji', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['severity' => 'error', 'title' => 'Server Down']));

    Http::assertSent(function (Request $req) {
        $text = $req->data()['text'] ?? '';
        expect($text)->toContain('<b>🚨 Server Down</b>');

        return true;
    });
});

it('includes fields formatted as <b>Name:</b> value', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload([
        'fields' => [
            ['name' => 'Host', 'value' => 'web-01', 'inline' => true],
        ],
    ]));

    Http::assertSent(function (Request $req) {
        $text = $req->data()['text'] ?? '';
        expect($text)->toContain('<b>Host: </b>web-01');

        return true;
    });
});

it('wraps code blocks in <pre><code class="language-php"> tags', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload([
        'codeBlocks' => [
            ['name' => 'Trace', 'code' => 'echo "hello";', 'lang' => 'php'],
        ],
    ]));

    Http::assertSent(function (Request $req) {
        $text = $req->data()['text'] ?? '';
        expect($text)->toContain('<pre><code class="language-php">');
        expect($text)->toContain('echo "hello";');

        return true;
    });
});

it('routes to the correct thread via message_thread_id as integer', function () {
    telegramDriverConfig(['howl.drivers.telegram.threads' => ['errors' => 42]]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['channel' => 'errors']));

    Http::assertSent(function (Request $req) {
        expect($req->data()['message_thread_id'])->toBe(42);

        return true;
    });
});

it('casts string thread id from env to integer', function () {
    telegramDriverConfig(['howl.drivers.telegram.threads' => ['errors' => '42']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['channel' => 'errors']));

    Http::assertSent(function (Request $req) {
        expect($req->data()['message_thread_id'])->toBe(42);

        return true;
    });
});

it('omits message_thread_id when no thread mapping exists', function () {
    telegramDriverConfig(['howl.drivers.telegram.threads' => []]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['channel' => 'unknown']));

    Http::assertSent(function (Request $req) {
        expect($req->data())->not->toHaveKey('message_thread_id');

        return true;
    });
});

it('HTML-escapes title containing script tags', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['title' => '<script>alert(1)</script>']));

    Http::assertSent(function (Request $req) {
        $text = $req->data()['text'] ?? '';
        expect($text)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
        expect($text)->not->toContain('<script>');

        return true;
    });
});

it('renders user mention as <a href="tg://user?id=...">user</a>', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['mentions' => [['type' => 'user', 'id' => '123456']]]));

    Http::assertSent(function (Request $req) {
        $text = $req->data()['text'] ?? '';
        expect($text)->toContain('<a href="tg://user?id=123456">user</a>');

        return true;
    });
});

it('skips here mention — no @here equivalent in Telegram', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['mentions' => [['type' => 'here', 'id' => '']]]));

    Http::assertSent(function (Request $req) {
        $text = $req->data()['text'] ?? '';
        expect($text)->not->toContain('@here');
        expect($text)->not->toContain('tg://');

        return true;
    });
});

it('skips everyone mention — no @everyone equivalent in Telegram', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['mentions' => [['type' => 'everyone', 'id' => '']]]));

    Http::assertSent(function (Request $req) {
        $text = $req->data()['text'] ?? '';
        expect($text)->not->toContain('@everyone');
        expect($text)->not->toContain('@channel');

        return true;
    });
});

it('skips role mention — no role concept in Telegram', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload(['mentions' => [['type' => 'role', 'id' => 'some-role']]]));

    Http::assertSent(function (Request $req) {
        $text = $req->data()['text'] ?? '';
        expect($text)->not->toContain('some-role');

        return true;
    });
});

it('renders URL buttons as inline_keyboard in reply_markup', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload([
        'buttons' => [['label' => 'View Error', 'url' => 'https://example.com/errors/1']],
    ]));

    Http::assertSent(function (Request $req) {
        $markup = $req->data()['reply_markup'] ?? null;
        expect($markup)->not->toBeNull();
        expect($markup['inline_keyboard'][0][0]['text'])->toBe('View Error');
        expect($markup['inline_keyboard'][0][0]['url'])->toBe('https://example.com/errors/1');

        return true;
    });
});

it('returns false when Telegram returns ok:false with status 200', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request'], 200)]);

    $driver = new TelegramDriver;
    $result = $driver->send(telegramPayload());

    expect($result)->toBeFalse();
});

it('returns false when Telegram returns HTTP 401', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)]);

    $driver = new TelegramDriver;
    $result = $driver->send(telegramPayload());

    expect($result)->toBeFalse();
});

it('returns false and logs error when chat_id is not configured', function () {
    telegramDriverConfig(['howl.drivers.telegram.chat_id' => null]);

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'chat_id is not configured'));

    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $result = $driver->send(telegramPayload());

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

it('disables link preview via link_preview_options', function () {
    telegramDriverConfig();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $driver = new TelegramDriver;
    $driver->send(telegramPayload());

    Http::assertSent(function (Request $req) {
        $data = $req->data();
        expect($data['link_preview_options']['is_disabled'])->toBeTrue();

        return true;
    });
});

it('returns the driver name telegram', function () {
    expect((new TelegramDriver)->name())->toBe('telegram');
});
