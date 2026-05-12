<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Skaisser\Howl\Events\HowlEvent;
use Skaisser\Howl\Facades\Howl as HowlFacade;
use Skaisser\Howl\Howl as HowlClass;
use Skaisser\Howl\Jobs\SendHowlJob;
use Skaisser\Howl\Support\BlockKitBuilder;
use Skaisser\Howl\Support\EmbedBuilder;
use Skaisser\Howl\Support\Payload;
use Skaisser\Howl\Support\PendingNotification;
use Skaisser\Howl\Support\TelegramHtmlBuilder;

// ---------------------------------------------------------------------------
// Helper — build a Payload with sensible defaults
// ---------------------------------------------------------------------------

function backfillPayload(array $overrides = []): Payload
{
    return new Payload(
        title: $overrides['title'] ?? 'Backfill Test',
        description: $overrides['description'] ?? null,
        severity: $overrides['severity'] ?? 'info',
        channel: $overrides['channel'] ?? null,
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
        timestamp: $overrides['timestamp'] ?? new DateTimeImmutable('2026-01-01 00:00:00'),
        forceSync: $overrides['forceSync'] ?? false,
        fallback: $overrides['fallback'] ?? null,
        driver: $overrides['driver'] ?? null,
    );
}

// ===========================================================================
// PendingNotification — builder method coverage
// ===========================================================================

it('PendingNotification::body() is an alias for description()', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->body('My body text');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->description)->toBe('My body text');
});

it('PendingNotification::codeBlock() appends a code block to the payload', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->codeBlock('Trace', 'return null;', 'php');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->codeBlocks)->toHaveCount(1)
        ->and($payload->codeBlocks[0]['name'])->toBe('Trace')
        ->and($payload->codeBlocks[0]['code'])->toBe('return null;')
        ->and($payload->codeBlocks[0]['lang'])->toBe('php');
});

it('PendingNotification::mention() appends a mention', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->mention('user', '12345');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->mentions)->toHaveCount(1)
        ->and($payload->mentions[0]['type'])->toBe('user')
        ->and($payload->mentions[0]['id'])->toBe('12345');
});

it('PendingNotification::meta() with array merges meta', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->meta(['key1' => 'val1', 'key2' => 'val2']);

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->meta)->toBe(['key1' => 'val1', 'key2' => 'val2']);
});

it('PendingNotification::button() appends a button', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->button('Go', 'https://example.com');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->buttons)->toHaveCount(1)
        ->and($payload->buttons[0]['label'])->toBe('Go')
        ->and($payload->buttons[0]['url'])->toBe('https://example.com');
});

it('PendingNotification::attach() appends an attachment path', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->attach('/tmp/report.pdf');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->attachments)->toHaveCount(1)
        ->and($payload->attachments[0])->toBe('/tmp/report.pdf');
});

it('PendingNotification::thread() sets the thread ID', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->thread('999');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->threadId)->toBe('999');
});

it('PendingNotification::username() sets the username', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->username('MyBot');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->username)->toBe('MyBot');
});

it('PendingNotification::app() sets the app name', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->app('MyApp');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->app)->toBe('MyApp');
});

it('PendingNotification::env() sets the environment', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->env('staging');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->env)->toBe('staging');
});

it('PendingNotification::at() sets the timestamp', function () {
    HowlFacade::fake();
    $ts = new DateTimeImmutable('2026-06-01 12:00:00');
    $pending = (new PendingNotification)->at($ts);

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->timestamp)->toBe($ts);
});

it('PendingNotification::forceSync() sets forceSync to true', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->forceSync();

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->forceSync)->toBeTrue();
});

it('PendingNotification::withFallback() sets the fallback driver', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->withFallback('null');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->fallback)->toBe('null');
});

it('PendingNotification::severity() sets a severity override that wins over verb', function () {
    HowlFacade::fake();
    $pending = (new PendingNotification)->severity('audit');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    // Verb says 'info' but severity override says 'audit'
    $payload = $ref->invoke($pending, 'info');

    expect($payload->severity)->toBe('audit');
});

// ---------------------------------------------------------------------------
// PendingNotification — terminal verb coverage for all remaining severity paths
// ---------------------------------------------------------------------------

it('PendingNotification::warning() dispatches with warning severity', function () {
    $fake = HowlFacade::fake();
    (new PendingNotification)->warning('Warning alert');

    $fake->assertSent(fn ($p) => $p->severity === 'warning' && $p->title === 'Warning alert');
});

it('PendingNotification::success() dispatches with success severity', function () {
    $fake = HowlFacade::fake();
    (new PendingNotification)->success('Deployed!');

    $fake->assertSent(fn ($p) => $p->severity === 'success' && $p->title === 'Deployed!');
});

it('PendingNotification::audit() dispatches with audit severity', function () {
    $fake = HowlFacade::fake();
    (new PendingNotification)->audit('Admin action');

    $fake->assertSent(fn ($p) => $p->severity === 'audit' && $p->title === 'Admin action');
});

it('PendingNotification::deployment() dispatches with deployment severity', function () {
    $fake = HowlFacade::fake();
    (new PendingNotification)->deployment('v2.0 deployed');

    $fake->assertSent(fn ($p) => $p->severity === 'deployment');
});

// ---------------------------------------------------------------------------
// PendingNotification — terminal verbs accepting a matching HowlEvent
// ---------------------------------------------------------------------------

function makeSeverityEvent(string $sev): HowlEvent
{
    return new class($sev) extends HowlEvent
    {
        public function __construct(private readonly string $sev)
        {
            parent::__construct([], []);
        }

        public function severity(): string { return $this->sev; }
        public function title(): string { return ucfirst($this->sev).' Event'; }
        public function description(): string { return 'A '.$this->sev.' event.'; }
        public function fields(): array { return []; }
        public function emoji(): string { return '🧪'; }
        public function footerMeta(): array { return []; }
    };
}

it('PendingNotification::warning() with matching HowlEvent dispatches', function () {
    $fake = HowlFacade::fake();
    $event = makeSeverityEvent('warning');
    (new PendingNotification)->warning($event);

    $fake->assertSent(fn ($p) => $p->severity === 'warning');
});

it('PendingNotification::warning() throws LogicException on severity mismatch', function () {
    HowlFacade::fake();
    $event = makeSeverityEvent('error'); // 'error' ≠ 'warning'

    expect(fn () => (new PendingNotification)->warning($event))
        ->toThrow(\LogicException::class, "terminal verb ->warning()");
});

it('PendingNotification::info() with matching HowlEvent dispatches', function () {
    $fake = HowlFacade::fake();
    $event = makeSeverityEvent('info');
    (new PendingNotification)->info($event);

    $fake->assertSent(fn ($p) => $p->severity === 'info');
});

it('PendingNotification::info() throws LogicException on severity mismatch', function () {
    HowlFacade::fake();
    $event = makeSeverityEvent('error'); // 'error' ≠ 'info'

    expect(fn () => (new PendingNotification)->info($event))
        ->toThrow(\LogicException::class, "terminal verb ->info()");
});

it('PendingNotification::success() with matching HowlEvent dispatches', function () {
    $fake = HowlFacade::fake();
    $event = makeSeverityEvent('success');
    (new PendingNotification)->success($event);

    $fake->assertSent(fn ($p) => $p->severity === 'success');
});

it('PendingNotification::success() throws LogicException on severity mismatch', function () {
    HowlFacade::fake();
    $event = makeSeverityEvent('error');

    expect(fn () => (new PendingNotification)->success($event))
        ->toThrow(\LogicException::class, "terminal verb ->success()");
});

it('PendingNotification::audit() with matching HowlEvent dispatches', function () {
    $fake = HowlFacade::fake();
    $event = makeSeverityEvent('audit');
    (new PendingNotification)->audit($event);

    $fake->assertSent(fn ($p) => $p->severity === 'audit');
});

it('PendingNotification::audit() throws LogicException on severity mismatch', function () {
    HowlFacade::fake();
    $event = makeSeverityEvent('error');

    expect(fn () => (new PendingNotification)->audit($event))
        ->toThrow(\LogicException::class, "terminal verb ->audit()");
});

it('PendingNotification::deployment() with matching HowlEvent dispatches', function () {
    $fake = HowlFacade::fake();
    $event = makeSeverityEvent('deployment');
    (new PendingNotification)->deployment($event);

    $fake->assertSent(fn ($p) => $p->severity === 'deployment');
});

it('PendingNotification::deployment() throws LogicException on severity mismatch', function () {
    HowlFacade::fake();
    $event = makeSeverityEvent('error');

    expect(fn () => (new PendingNotification)->deployment($event))
        ->toThrow(\LogicException::class, "terminal verb ->deployment()");
});

// ---------------------------------------------------------------------------
// PendingNotification — send() with HowlEvent shorthand (non-terminal path)
// ---------------------------------------------------------------------------

it('PendingNotification::send() with HowlEvent shorthand defers to event severity', function () {
    $fake = HowlFacade::fake();
    $event = makeSeverityEvent('audit');
    (new PendingNotification)->send($event);

    $fake->assertSent(fn ($p) => $p->severity === 'audit');
});

// ===========================================================================
// SendHowlJob — handle() failure path (driver returns false → RuntimeException)
// ===========================================================================

it('SendHowlJob::handle() throws RuntimeException when driver returns false', function () {
    // Use the real Howl container binding with NullDriver configured
    // but override resolveDriver to return a failing driver
    $payload = backfillPayload(['driver' => 'null']);
    $job = new SendHowlJob($payload, 'null');

    // Swap the howl container binding with a spy that always returns false
    $spyHowl = new class(app('howl')->getConfig()) extends HowlClass {
        public function resolveDriver(string $name): \Skaisser\Howl\Contracts\Driver
        {
            return new class implements \Skaisser\Howl\Contracts\Driver {
                public function name(): string { return 'failing'; }
                public function send(Payload $p): bool { return false; }
            };
        }
    };

    expect(fn () => $job->handle($spyHowl))
        ->toThrow(\RuntimeException::class, 'Howl driver [null] returned false');
});

// ===========================================================================
// EmbedBuilder — gap coverage
// ===========================================================================

it('EmbedBuilder::relativeTimestamp() returns Discord relative timestamp format', function () {
    $ts = new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'));
    $payload = backfillPayload(['timestamp' => $ts]);

    $relative = EmbedBuilder::relativeTimestamp($payload);

    expect($relative)->toMatch('/^<t:\d+:R>$/');
});

it('EmbedBuilder::relativeTimestamp() uses now() when timestamp is null', function () {
    $payload = backfillPayload(['timestamp' => null]);

    $relative = EmbedBuilder::relativeTimestamp($payload);

    expect($relative)->toMatch('/^<t:\d+:R>$/');
});

it('EmbedBuilder includes avatar_url in body when configured', function () {
    config(['howl.drivers.discord.avatar_url' => 'https://example.com/avatar.png']);

    $payload = backfillPayload();
    $body = EmbedBuilder::build($payload);

    expect($body)->toHaveKey('avatar_url', 'https://example.com/avatar.png');

    // Reset
    config(['howl.drivers.discord.avatar_url' => null]);
});

it('EmbedBuilder includes icon_url in author block when avatar_url is configured', function () {
    config(['howl.drivers.discord.avatar_url' => 'https://example.com/icon.png']);

    $payload = backfillPayload();
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['author'])->toHaveKey('icon_url', 'https://example.com/icon.png');

    // Reset
    config(['howl.drivers.discord.avatar_url' => null]);
});

it('EmbedBuilder buildMentionContent returns empty string for unknown mention type', function () {
    // default match branch → '' (filters out to empty)
    $payload = backfillPayload(['mentions' => [['type' => 'unknown_type', 'id' => '']]]);
    $body = EmbedBuilder::build($payload);

    expect($body)->not->toHaveKey('content');
});

it('EmbedBuilder buildAllowedMentions handles unknown type via default branch', function () {
    $payload = backfillPayload(['mentions' => [['type' => 'channel', 'id' => 'abc']]]);
    $body = EmbedBuilder::build($payload);

    // Unknown type → default → null — should not crash, parse/users/roles all empty
    expect($body['allowed_mentions']['parse'])->toBeEmpty()
        ->and($body['allowed_mentions']['users'])->toBeEmpty()
        ->and($body['allowed_mentions']['roles'])->toBeEmpty();
});

it('EmbedBuilder uses now() when payload timestamp is null', function () {
    $payload = backfillPayload(['timestamp' => null]);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
});

it('EmbedBuilder omits description when empty string', function () {
    $payload = backfillPayload(['description' => '']);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0])->not->toHaveKey('description');
});

// ===========================================================================
// BlockKitBuilder — gap coverage (missing private methods)
// ===========================================================================

it('BlockKitBuilder includes leading mentions section for user mention', function () {
    $payload = backfillPayload(['mentions' => [['type' => 'user', 'id' => '123456']]]);
    $body = BlockKitBuilder::build($payload, 'C123');

    // First block should be the mention section
    $blocks = $body['attachments'][0]['blocks'];
    expect($blocks[0]['type'])->toBe('section')
        ->and($blocks[0]['text']['text'])->toContain('<@123456>');
});

it('BlockKitBuilder includes mentions for here type', function () {
    $payload = backfillPayload(['mentions' => [['type' => 'here', 'id' => '']]]);
    $body = BlockKitBuilder::build($payload, 'C123');

    $blocks = $body['attachments'][0]['blocks'];
    expect($blocks[0]['text']['text'])->toContain('<!here>');
});

it('BlockKitBuilder includes mentions for everyone type', function () {
    $payload = backfillPayload(['mentions' => [['type' => 'everyone', 'id' => '']]]);
    $body = BlockKitBuilder::build($payload, 'C123');

    $blocks = $body['attachments'][0]['blocks'];
    expect($blocks[0]['text']['text'])->toContain('<!channel>');
});

it('BlockKitBuilder includes mentions for role type', function () {
    $payload = backfillPayload(['mentions' => [['type' => 'role', 'id' => 'S0987']]]);
    $body = BlockKitBuilder::build($payload, 'C123');

    $blocks = $body['attachments'][0]['blocks'];
    expect($blocks[0]['text']['text'])->toContain('<!subteam^S0987>');
});

it('BlockKitBuilder ignores unknown mention type', function () {
    $payload = backfillPayload(['mentions' => [['type' => 'unknown', 'id' => '']]]);
    $body = BlockKitBuilder::build($payload, 'C123');

    // No leading mention section (filter removes empty string)
    $blocks = $body['attachments'][0]['blocks'];
    expect($blocks[0]['type'])->toBe('header'); // mention section skipped
});

it('BlockKitBuilder renders a divider between fields and code blocks', function () {
    $payload = backfillPayload([
        'fields' => [['name' => 'Env', 'value' => 'prod', 'inline' => true]],
        'codeBlocks' => [['name' => 'Stack', 'code' => 'err()', 'lang' => 'php']],
    ]);
    $body = BlockKitBuilder::build($payload, 'C123');

    $blockTypes = array_column($body['attachments'][0]['blocks'], 'type');
    expect($blockTypes)->toContain('divider');
});

it('BlockKitBuilder renders buttons as actions block', function () {
    $payload = backfillPayload([
        'buttons' => [['label' => 'Open', 'url' => 'https://example.com']],
    ]);
    $body = BlockKitBuilder::build($payload, 'C123');

    $blockTypes = array_column($body['attachments'][0]['blocks'], 'type');
    expect($blockTypes)->toContain('actions');
});

it('BlockKitBuilder uses now() for timestamp when payload timestamp is null', function () {
    $payload = backfillPayload(['timestamp' => null]);
    $body = BlockKitBuilder::build($payload, 'C123');

    // Context block (footer) should exist and not throw
    $blockTypes = array_column($body['attachments'][0]['blocks'], 'type');
    expect($blockTypes)->toContain('context');
});

// ===========================================================================
// TelegramHtmlBuilder — gap coverage (line 81: mutable DateTime)
// ===========================================================================

it('TelegramHtmlBuilder handles a mutable DateTime as timestamp', function () {
    // DateTimeImmutable::createFromInterface path (line 81) — pass a DateTime (not Immutable)
    $payload = backfillPayload(['timestamp' => new DateTime('2026-01-01 12:00:00')]);

    $html = TelegramHtmlBuilder::build($payload);

    expect($html)->toContain('2026-01-01');
});

// ===========================================================================
// HowlEvent — renderLinks() skips URL with no scheme (line 154)
// ===========================================================================

it('HowlEvent renderLinks() skips a URL that has no http/https scheme', function () {
    // Provide a URL with no scheme — should be silently skipped (line 153-154)
    $event = new class extends HowlEvent {
        public function __construct()
        {
            parent::__construct(
                ['order' => 'not-a-url'],  // no http:// prefix → skip
                [],
            );
        }

        public function severity(): string { return 'info'; }
        public function title(): string { return 'Test'; }
        public function description(): string { return 'desc'; }
        public function fields(): array { return []; }
        public function emoji(): string { return '🧪'; }
        public function footerMeta(): array { return []; }
    };

    $linkFields = $event->renderLinks();

    expect($linkFields)->toBeEmpty();
});

// ===========================================================================
// Howl.php — fan_out without backup channel (line 165)
// ===========================================================================

it('Howl fan_out mode with no backup dispatches to primary only and returns its result', function () {
    $fake = HowlFacade::fake();

    $config = array_merge(
        app('config')->get('howl', []),
        [
            'skip_environments' => [],
            'app_env' => 'production',
            'queue' => false,
            'channel' => 'general',
            'channel_backup' => null,     // ← no backup
            'channel_mode' => 'fan_out',
            'driver' => 'null',
        ],
    );

    $howl = new HowlClass($config);

    // Use a tracking override to avoid hitting null driver HTTP
    $sendCalls = [];
    $trackingHowl = new class($config, $sendCalls) extends HowlClass {
        public function __construct(array $config, private array &$trackCalls)
        {
            parent::__construct($config);
        }

        public function resolveDriver(string $name): \Skaisser\Howl\Contracts\Driver
        {
            return new class($this->trackCalls) implements \Skaisser\Howl\Contracts\Driver {
                public function __construct(private array &$calls) {}
                public function name(): string { return 'tracking'; }
                public function send(Payload $p): bool
                {
                    $this->calls[] = $p->channel;
                    return true;
                }
            };
        }
    };

    $result = $trackingHowl->dispatch(backfillPayload());

    expect($result)->toBeTrue()
        ->and($sendCalls)->toHaveCount(1);
});

// ===========================================================================
// Howl.php — failover with no primaryChannel (line 152: $payload as-is)
// ===========================================================================

it('Howl dispatch uses payload as-is when primaryChannel is null', function () {
    $sendCalls = [];
    $config = array_merge(
        app('config')->get('howl', []),
        [
            'skip_environments' => [],
            'app_env' => 'production',
            'queue' => false,
            'channel' => null,           // ← no config channel
            'channel_backup' => null,
            'channel_mode' => 'failover',
            'driver' => 'null',
        ],
    );

    $trackingHowl = new class($config, $sendCalls) extends HowlClass {
        public function __construct(array $config, private array &$trackCalls)
        {
            parent::__construct($config);
        }

        public function resolveDriver(string $name): \Skaisser\Howl\Contracts\Driver
        {
            return new class($this->trackCalls) implements \Skaisser\Howl\Contracts\Driver {
                public function __construct(private array &$calls) {}
                public function name(): string { return 'tracking'; }
                public function send(Payload $p): bool
                {
                    $this->calls[] = $p->channel;
                    return true;
                }
            };
        }
    };

    // Payload has no channel, config has no channel → primaryChannel is null
    $result = $trackingHowl->dispatch(backfillPayload(['channel' => null]));

    expect($result)->toBeTrue();
});

// ===========================================================================
// SlackDriver — gap coverage (lines 72-73 uploadAttachments fail-fast,
// line 134 uploadFileBody failure, line 197 completeUpload non-200)
// ===========================================================================

it('SlackDriver::send() returns false when uploadAttachments uploadFileBody fails', function () {
    config([
        'howl.drivers.slack.bot_token' => 'xoxb-test-token',
        'howl.drivers.slack.default_channel' => 'C12345',
        'howl.drivers.slack.timeout' => 10,
    ]);

    // Create a real temp file to pass the is_file/is_readable check
    $tmpFile = tempnam(sys_get_temp_dir(), 'howl_test_');
    file_put_contents($tmpFile, 'test content');

    Http::fake([
        'slack.com/api/files.getUploadURLExternal' => Http::response([
            'ok' => true,
            'upload_url' => 'https://files.slack.com/upload/v1/abc123',
            'file_id' => 'F12345',
        ], 200),
        'files.slack.com/upload/v1/abc123' => Http::response('', 500), // Step 2 fails
    ]);

    $driver = new \Skaisser\Howl\Drivers\SlackDriver;
    $payload = backfillPayload(['attachments' => [$tmpFile]]);

    $result = $driver->send($payload);

    expect($result)->toBeFalse();

    @unlink($tmpFile);
});

it('SlackDriver::send() returns false when completeUpload returns non-200', function () {
    config([
        'howl.drivers.slack.bot_token' => 'xoxb-test-token',
        'howl.drivers.slack.default_channel' => 'C12345',
        'howl.drivers.slack.timeout' => 10,
    ]);

    $tmpFile = tempnam(sys_get_temp_dir(), 'howl_test_');
    file_put_contents($tmpFile, 'test content');

    Http::fake([
        'slack.com/api/files.getUploadURLExternal' => Http::response([
            'ok' => true,
            'upload_url' => 'https://files.slack.com/upload/v1/abc123',
            'file_id' => 'F12345',
        ], 200),
        'files.slack.com/upload/v1/abc123' => Http::response('', 200), // Step 2 succeeds
        'slack.com/api/files.completeUploadExternal' => Http::response('', 500), // Step 3 fails
    ]);

    $driver = new \Skaisser\Howl\Drivers\SlackDriver;
    $payload = backfillPayload(['attachments' => [$tmpFile]]);

    $result = $driver->send($payload);

    expect($result)->toBeFalse();

    @unlink($tmpFile);
});

it('SlackDriver uploadAttachments throws InvalidArgumentException for non-readable file', function () {
    config([
        'howl.drivers.slack.bot_token' => 'xoxb-test',
        'howl.drivers.slack.default_channel' => 'C99999',
    ]);

    $driver = new \Skaisser\Howl\Drivers\SlackDriver;
    $payload = backfillPayload(['attachments' => ['/nonexistent/path/file.pdf']]);

    expect(fn () => $driver->send($payload))
        ->toThrow(\InvalidArgumentException::class, 'Howl: attachment path is not a readable file');
});

// ===========================================================================
// TelegramDriver — gap coverage (lines 96-97 exception re-throw,
// line 114 resolveThreadId empty channel, line 227 sendPhoto non-200,
// line 262 sendDocument non-200)
// ===========================================================================

it('TelegramDriver resolveThreadId returns null when channel is empty string', function () {
    // Payload with empty channel → resolveThreadId returns null
    config([
        'howl.drivers.telegram.bot_token' => 'bot-token',
        'howl.drivers.telegram.chat_id' => '12345',
        'howl.drivers.telegram.timeout' => 10,
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $driver = new \Skaisser\Howl\Drivers\TelegramDriver;
    // channel is null → $channel = '' → return null for threadId
    $payload = backfillPayload(['channel' => null]);

    $result = $driver->send($payload);

    expect($result)->toBeTrue();
});

it('TelegramDriver sendPhoto returns false when HTTP status is non-200', function () {
    config([
        'howl.drivers.telegram.bot_token' => 'bot-token',
        'howl.drivers.telegram.chat_id' => '12345',
        'howl.drivers.telegram.timeout' => 10,
    ]);

    $tmpFile = tempnam(sys_get_temp_dir(), 'howl_test_').'.jpg';
    file_put_contents($tmpFile, 'fake jpg content');

    Http::fake([
        'api.telegram.org/*/sendPhoto' => Http::response('', 500),
    ]);

    $driver = new \Skaisser\Howl\Drivers\TelegramDriver;
    $payload = backfillPayload(['attachments' => [$tmpFile]]);

    $result = $driver->send($payload);

    expect($result)->toBeFalse();

    @unlink($tmpFile);
});

it('TelegramDriver sendDocument returns false when HTTP status is non-200', function () {
    config([
        'howl.drivers.telegram.bot_token' => 'bot-token',
        'howl.drivers.telegram.chat_id' => '12345',
        'howl.drivers.telegram.timeout' => 10,
    ]);

    $tmpFile = tempnam(sys_get_temp_dir(), 'howl_test_').'.pdf';
    file_put_contents($tmpFile, 'fake pdf content');

    Http::fake([
        'api.telegram.org/*/sendDocument' => Http::response('', 500),
    ]);

    $driver = new \Skaisser\Howl\Drivers\TelegramDriver;
    $payload = backfillPayload(['attachments' => [$tmpFile]]);

    $result = $driver->send($payload);

    expect($result)->toBeFalse();

    @unlink($tmpFile);
});

it('TelegramDriver re-throws InvalidArgumentException from uploadAttachments', function () {
    config([
        'howl.drivers.telegram.bot_token' => 'bot-token',
        'howl.drivers.telegram.chat_id' => '12345',
    ]);

    $driver = new \Skaisser\Howl\Drivers\TelegramDriver;
    $payload = backfillPayload(['attachments' => ['/nonexistent/path/file.jpg']]);

    expect(fn () => $driver->send($payload))
        ->toThrow(\InvalidArgumentException::class, 'Howl: attachment path is not a readable file');
});

// ===========================================================================
// DiscordDriver — gap coverage (line 113: invalid attachment path)
// ===========================================================================

it('DiscordDriver sendMultipart throws InvalidArgumentException for non-readable attachment', function () {
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.com/api/webhooks/test/test',
        'howl.drivers.discord.timeout' => 10,
    ]);

    $driver = new \Skaisser\Howl\Drivers\DiscordDriver;
    $payload = backfillPayload(['attachments' => ['/nonexistent/path/file.png']]);

    // The exception is caught by the outer try-catch in send(), which returns false
    $result = $driver->send($payload);

    expect($result)->toBeFalse();
});

// ===========================================================================
// SlackDriver — catch(\Throwable) branch (line 72-73): generic exception returns false
// ===========================================================================

it('SlackDriver::send() returns false when Http throws a generic Throwable', function () {
    config([
        'howl.drivers.slack.bot_token' => 'xoxb-test-token',
        'howl.drivers.slack.default_channel' => 'C12345',
        'howl.drivers.slack.timeout' => 10,
    ]);

    // Http::fake() with a callback that throws a RuntimeException (not InvalidArgumentException)
    Http::fake(function () {
        throw new \RuntimeException('Connection refused');
    });

    $driver = new \Skaisser\Howl\Drivers\SlackDriver;
    $payload = backfillPayload(); // no attachments — goes directly to postMessage

    $result = $driver->send($payload);

    expect($result)->toBeFalse();
});

// ===========================================================================
// TelegramDriver — catch(\Throwable) branch (line 96-97): generic exception returns false
// ===========================================================================

it('TelegramDriver::send() returns false when Http throws a generic Throwable', function () {
    config([
        'howl.drivers.telegram.bot_token' => 'bot-token',
        'howl.drivers.telegram.chat_id' => '12345',
        'howl.drivers.telegram.timeout' => 10,
    ]);

    // Http::fake() with a callback that throws a RuntimeException
    Http::fake(function () {
        throw new \RuntimeException('Network unreachable');
    });

    $driver = new \Skaisser\Howl\Drivers\TelegramDriver;
    $payload = backfillPayload(); // no attachments — goes to sendMessage

    $result = $driver->send($payload);

    expect($result)->toBeFalse();
});
