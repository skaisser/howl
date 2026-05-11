<?php

use Illuminate\Support\Facades\Log;
use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Drivers\NullDriver;
use Skaisser\Howl\Howl as HowlClass;
use Skaisser\Howl\Support\Payload;
use Skaisser\Howl\Tests\Fixtures\RaisingDriver;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fallbackPayload(?string $fallback = null, bool $forceSync = false): Payload
{
    return new Payload(
        title: 'Fallback Test',
        description: null,
        severity: 'error',
        channel: null,
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
        forceSync: $forceSync,
        fallback: $fallback,
    );
}

/**
 * Build a Howl instance with production-like settings (no skip_environments, no queue).
 * Accepts a driver factory callable keyed by driver name so we can inject test doubles.
 */
function fallbackHowl(array $configOverrides = [], ?callable $driverFactory = null): HowlClass
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

    if ($driverFactory !== null) {
        // Partial mock: anonymous subclass that overrides resolveDriver.
        // factory must be set BEFORE parent::__construct() because the parent
        // calls resolveDriver() during construction.
        return new class($config, $driverFactory) extends HowlClass
        {
            private $factory;

            public function __construct(array $config, callable $factory)
            {
                $this->factory = $factory;
                parent::__construct($config);
            }

            public function resolveDriver(string $name): Driver
            {
                return ($this->factory)($name);
            }
        };
    }

    return new HowlClass($config);
}

// ---------------------------------------------------------------------------
// Fallback chain tests
// ---------------------------------------------------------------------------

it('uses fallback driver when primary returns false', function () {
    $fallback = new NullDriver;

    $howl = fallbackHowl(
        configOverrides: ['driver' => 'raising', 'fallback' => 'null'],
        driverFactory: function (string $name) use ($fallback) {
            return match ($name) {
                'null' => $fallback,
                default => new RaisingDriver(shouldThrow: false),
            };
        },
    );

    $result = $howl->dispatch(fallbackPayload());

    expect($result)->toBeTrue();
    expect($fallback->sent)->toHaveCount(1);
    expect($fallback->sent[0]->title)->toBe('Fallback Test');
});

it('uses fallback driver when primary throws an exception', function () {
    $fallback = new NullDriver;

    $howl = fallbackHowl(
        configOverrides: ['driver' => 'raising', 'fallback' => 'null'],
        driverFactory: function (string $name) use ($fallback) {
            return match ($name) {
                'null' => $fallback,
                default => new RaisingDriver(shouldThrow: true),
            };
        },
    );

    $result = $howl->dispatch(fallbackPayload());

    expect($result)->toBeTrue();
    expect($fallback->sent)->toHaveCount(1);
});

it('logs and returns false when both primary and fallback fail', function () {
    Log::spy();

    $howl = fallbackHowl(
        configOverrides: ['driver' => 'raising', 'fallback' => 'raising2'],
        driverFactory: fn (string $name) => new RaisingDriver(shouldThrow: false, driverName: $name),
    );

    $result = $howl->dispatch(fallbackPayload());

    expect($result)->toBeFalse();

    Log::shouldHaveReceived('error')->twice();
});

it('does not throw when both primary and fallback throw exceptions', function () {
    Log::spy();

    $howl = fallbackHowl(
        configOverrides: ['driver' => 'raising', 'fallback' => 'raising2'],
        driverFactory: fn (string $name) => new RaisingDriver(shouldThrow: true, driverName: $name),
    );

    // Should never throw — exceptions are swallowed
    $result = $howl->dispatch(fallbackPayload());

    expect($result)->toBeFalse();
});

it('uses per-call withFallback() payload field over config fallback', function () {
    $perCallFallback = new NullDriver;
    $configFallback = new NullDriver;

    $howl = fallbackHowl(
        configOverrides: ['driver' => 'raising', 'fallback' => 'config-fallback'],
        driverFactory: function (string $name) use ($perCallFallback, $configFallback) {
            return match ($name) {
                'per-call' => $perCallFallback,
                'config-fallback' => $configFallback,
                default => new RaisingDriver(shouldThrow: false),
            };
        },
    );

    // Payload has per-call fallback set — should win over config fallback
    $payload = fallbackPayload(fallback: 'per-call');
    $result = $howl->dispatch($payload);

    expect($result)->toBeTrue();
    expect($perCallFallback->sent)->toHaveCount(1);
    expect($configFallback->sent)->toHaveCount(0);
});

it('does not call the same driver twice when primary and fallback names match', function () {
    // Use a shared driver instance so we can count send() calls
    $sharedDriver = new NullDriver;

    $howl = fallbackHowl(
        // Primary and fallback are the same name — should be deduped by array_unique
        configOverrides: ['driver' => 'null', 'fallback' => 'null'],
        driverFactory: fn (string $name) => $sharedDriver,
    );

    $result = $howl->dispatch(fallbackPayload());

    // send() called exactly once (dedup prevents second attempt on the same driver name)
    expect($sharedDriver->sent)->toHaveCount(1);
    expect($result)->toBeTrue();
});

it('returns true immediately when primary driver succeeds without consulting fallback', function () {
    $fallbackCalled = false;

    $howl = fallbackHowl(
        configOverrides: ['driver' => 'null', 'fallback' => 'should-not-run'],
        driverFactory: function (string $name) use (&$fallbackCalled) {
            if ($name === 'should-not-run') {
                $fallbackCalled = true;

                return new NullDriver;
            }

            return new NullDriver; // primary succeeds
        },
    );

    $result = $howl->dispatch(fallbackPayload());

    expect($result)->toBeTrue();
    expect($fallbackCalled)->toBeFalse();
});

it('forceSync payload still respects the fallback chain on sync path', function () {
    $fallback = new NullDriver;

    $howl = fallbackHowl(
        configOverrides: ['driver' => 'raising', 'fallback' => 'null', 'queue' => true],
        driverFactory: function (string $name) use ($fallback) {
            return match ($name) {
                'null' => $fallback,
                default => new RaisingDriver(shouldThrow: false),
            };
        },
    );

    // forceSync bypasses queue AND still benefits from fallback on sync path
    $result = $howl->dispatch(fallbackPayload(forceSync: true));

    expect($result)->toBeTrue();
    expect($fallback->sent)->toHaveCount(1);
});
