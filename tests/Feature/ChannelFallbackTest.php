<?php

use Illuminate\Support\Facades\Log;
use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Drivers\NullDriver;
use Skaisser\Howl\Facades\Howl as HowlFacade;
use Skaisser\Howl\Howl as HowlClass;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function channelPayload(?string $channel = null): Payload
{
    return new Payload(
        title: 'Channel Test',
        description: null,
        severity: 'error',
        channel: $channel,
        fields: [],
        codeBlocks: [],
        mentions: [],
        meta: [],
        buttons: [],
        attachments: [],
        threadId: null,
        username: null,
        app: null,
        env: null,
        timestamp: null,
        forceSync: false,
    );
}

/**
 * Build a Howl instance with a tracking driver.
 * The tracking driver records every channel it receives and can return success or failure.
 * $failOn controls which attempt number (1-indexed) should fail; null means always succeed.
 */
function trackingHowl(array $configOverrides, array &$sendCalls, ?array $failAttempts = null): HowlClass
{
    $base = array_merge(
        app('config')->get('howl', []),
        [
            'skip_environments' => [],
            'app_env' => 'production',
            'queue' => false,
            'driver' => 'tracking',
        ],
        $configOverrides,
    );

    return new class($base, $sendCalls, $failAttempts ?? []) extends HowlClass
    {
        private array $failAttempts;

        public function __construct(array $config, array &$sendCalls, array $failAttempts)
        {
            $this->sendCalls = &$sendCalls;
            $this->failAttempts = $failAttempts;
            $this->attempt = 0;
            parent::__construct($config);
        }

        public function resolveDriver(string $name): Driver
        {
            return new class($this->sendCalls, $this->attempt, $this->failAttempts) implements Driver
            {
                public function __construct(
                    private array &$calls,
                    private int &$attempt,
                    private array $failAttempts
                ) {}

                public function name(): string
                {
                    return 'tracking';
                }

                public function send(Payload $payload): bool
                {
                    $this->attempt++;
                    $this->calls[] = $payload->channel;
                    // Fail if current attempt is in the fail list
                    return ! in_array($this->attempt, $this->failAttempts, true);
                }
            };
        }
    };
}

// ---------------------------------------------------------------------------
// Failover scenarios
// ---------------------------------------------------------------------------

it('failover: primary fails, backup succeeds — 2 sends, returns true, backup received', function () {
    $sendCalls = [];
    $howl = trackingHowl(
        configOverrides: [
            'channel' => 'errors',
            'channel_backup' => 'warnings',
            'channel_mode' => 'failover',
        ],
        sendCalls: $sendCalls,
        failAttempts: [1], // first attempt (primary) fails
    );

    $result = $howl->dispatch(channelPayload());

    expect($result)->toBeTrue()
        ->and($sendCalls)->toHaveCount(2)
        ->and($sendCalls[0])->toBe('errors')
        ->and($sendCalls[1])->toBe('warnings');
});

it('failover: primary succeeds — 1 send, returns true, backup never hit', function () {
    $sendCalls = [];
    $howl = trackingHowl(
        configOverrides: [
            'channel' => 'errors',
            'channel_backup' => 'warnings',
            'channel_mode' => 'failover',
        ],
        sendCalls: $sendCalls,
        failAttempts: [], // always succeed
    );

    $result = $howl->dispatch(channelPayload());

    expect($result)->toBeTrue()
        ->and($sendCalls)->toHaveCount(1)
        ->and($sendCalls[0])->toBe('errors');
});

it('failover: primary fails, backup also fails — 2 sends, returns false', function () {
    $sendCalls = [];
    $howl = trackingHowl(
        configOverrides: [
            'channel' => 'errors',
            'channel_backup' => 'warnings',
            'channel_mode' => 'failover',
        ],
        sendCalls: $sendCalls,
        failAttempts: [1, 2], // both fail
    );

    $result = $howl->dispatch(channelPayload());

    expect($result)->toBeFalse()
        ->and($sendCalls)->toHaveCount(2)
        ->and($sendCalls[0])->toBe('errors')
        ->and($sendCalls[1])->toBe('warnings');
});

// ---------------------------------------------------------------------------
// Fan-out scenarios
// ---------------------------------------------------------------------------

it('fan_out: both channels succeed — 2 sends to different channels, returns true', function () {
    $sendCalls = [];
    $howl = trackingHowl(
        configOverrides: [
            'channel' => 'errors',
            'channel_backup' => 'audit',
            'channel_mode' => 'fan_out',
        ],
        sendCalls: $sendCalls,
        failAttempts: [], // both succeed
    );

    $result = $howl->dispatch(channelPayload());

    expect($result)->toBeTrue()
        ->and($sendCalls)->toHaveCount(2)
        ->and($sendCalls[0])->toBe('errors')
        ->and($sendCalls[1])->toBe('audit');
});

it('fan_out: primary succeeds, backup fails — 2 sends, returns true (at least one succeeded)', function () {
    $sendCalls = [];
    $howl = trackingHowl(
        configOverrides: [
            'channel' => 'errors',
            'channel_backup' => 'audit',
            'channel_mode' => 'fan_out',
        ],
        sendCalls: $sendCalls,
        failAttempts: [2], // second attempt (backup) fails
    );

    $result = $howl->dispatch(channelPayload());

    expect($result)->toBeTrue()
        ->and($sendCalls)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// No backup configured
// ---------------------------------------------------------------------------

it('no backup + failover: 1 send only, no backup attempt', function () {
    $sendCalls = [];
    $howl = trackingHowl(
        configOverrides: [
            'channel' => 'errors',
            'channel_backup' => null,
            'channel_mode' => 'failover',
        ],
        sendCalls: $sendCalls,
        failAttempts: [1], // primary fails — but no backup configured
    );

    $result = $howl->dispatch(channelPayload());

    expect($result)->toBeFalse()
        ->and($sendCalls)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Per-call channel override beats config primary
// ---------------------------------------------------------------------------

it('explicit per-call channel overrides config primary AND uses config backup on failover', function () {
    $sendCalls = [];
    $howl = trackingHowl(
        configOverrides: [
            'channel' => 'errors',         // config default — should be overridden
            'channel_backup' => 'warnings',
            'channel_mode' => 'failover',
        ],
        sendCalls: $sendCalls,
        failAttempts: [1], // primary (per-call channel) fails → try backup
    );

    // Per-call channel 'audits' beats config primary 'errors'
    $result = $howl->dispatch(channelPayload('audits'));

    expect($result)->toBeTrue()
        ->and($sendCalls)->toHaveCount(2)
        ->and($sendCalls[0])->toBe('audits')    // per-call wins for primary
        ->and($sendCalls[1])->toBe('warnings'); // config backup used as failover
});

// ---------------------------------------------------------------------------
// Channel precedence ordering test
// ---------------------------------------------------------------------------

it('channel precedence: per-call > HowlEvent::channel() > config("howl.channel")', function () {
    $fake = HowlFacade::fake();

    // 1. Config-level default — no per-call, no event channel
    config(['howl.channel' => 'config-default']);

    HowlFacade::info('From config default');
    expect($fake->sent()[0]->channel)->toBe('config-default');

    // 2. Event-level channel wins over config
    $event = new class extends \Skaisser\Howl\Events\HowlEvent {
        public function emoji(): string { return 'ℹ️'; }
        public function severity(): string { return 'info'; }
        public function title(): string { return 'Test'; }
        public function description(): string { return 'Test description'; }
        public function fields(): array { return []; }
        public function channel(): string { return 'event-channel'; }
    };

    HowlFacade::on()->info($event);
    expect($fake->sent()[1]->channel)->toBe('event-channel');

    // 3. Per-call channel beats both event and config
    HowlFacade::on('per-call-channel')->info($event);
    expect($fake->sent()[2]->channel)->toBe('per-call-channel');
});
