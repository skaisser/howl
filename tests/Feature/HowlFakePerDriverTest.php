<?php

use PHPUnit\Framework\AssertionFailedError;
use Skaisser\Howl\Facades\Howl;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// assertSentVia — positive paths
// ---------------------------------------------------------------------------

it('assertSentVia passes when a payload was dispatched via the given driver', function () {
    $fake = Howl::fake();

    // Default driver is 'discord' per config
    Howl::on()->error('Discord alert');

    $fake->assertSentVia('discord', fn ($p) => $p->severity === 'error');
});

it('assertSentVia passes when a payload was dispatched via explicit driver override', function () {
    $fake = Howl::fake();

    Howl::driver('slack')->error('Slack alert');

    $fake->assertSentVia('slack', fn ($p) => $p->severity === 'error');
});

it('assertSentVia passes when a payload was dispatched via telegram driver', function () {
    $fake = Howl::fake();

    Howl::driver('telegram')->info('Telegram info');

    $fake->assertSentVia('telegram', fn ($p) => $p->severity === 'info');
});

// ---------------------------------------------------------------------------
// assertSentVia — negative paths
// ---------------------------------------------------------------------------

it('assertSentVia fails when dispatched via different driver', function () {
    $fake = Howl::fake();

    Howl::on()->error('Discord alert');

    expect(fn () => $fake->assertSentVia('slack', fn ($p) => true))
        ->toThrow(AssertionFailedError::class, "Sent 0 payload(s) via that driver");
});

it('assertSentVia fails when callback does not match', function () {
    $fake = Howl::fake();

    Howl::driver('slack')->error('Slack error');

    expect(fn () => $fake->assertSentVia('slack', fn ($p) => $p->severity === 'info'))
        ->toThrow(AssertionFailedError::class, "Sent 1 payload(s) via that driver");
});

// ---------------------------------------------------------------------------
// assertSentViaNothing — positive paths
// ---------------------------------------------------------------------------

it('assertSentViaNothing passes when no payloads dispatched at all', function () {
    $fake = Howl::fake();

    $fake->assertSentViaNothing('discord');
    $fake->assertSentViaNothing('slack');
    $fake->assertSentViaNothing('telegram');
});

it('assertSentViaNothing passes for a driver that received no payloads', function () {
    $fake = Howl::fake();

    Howl::driver('slack')->error('Only Slack');

    $fake->assertSentViaNothing('discord');
    $fake->assertSentViaNothing('telegram');
});

// ---------------------------------------------------------------------------
// assertSentViaNothing — negative paths
// ---------------------------------------------------------------------------

it('assertSentViaNothing fails with correct message when payload was sent via driver', function () {
    $fake = Howl::fake();

    Howl::driver('slack')->error('Slack error');

    expect(fn () => $fake->assertSentViaNothing('slack'))
        ->toThrow(AssertionFailedError::class, "1 payload(s) were captured");
});

// ---------------------------------------------------------------------------
// sentVia() helper — mixed driver dispatches
// ---------------------------------------------------------------------------

it('sentVia returns all payloads routed via the given driver', function () {
    $fake = Howl::fake();

    Howl::on()->error('Discord one');
    Howl::on()->warning('Discord two');
    Howl::driver('slack')->info('Slack one');

    expect($fake->sentVia('discord'))->toHaveCount(2)
        ->and($fake->sentVia('slack'))->toHaveCount(1)
        ->and($fake->sentVia('telegram'))->toBeEmpty();
});

it('sentVia returns empty array for driver that received no payloads', function () {
    $fake = Howl::fake();

    Howl::on()->error('Discord only');

    expect($fake->sentVia('slack'))->toBeEmpty()
        ->and($fake->sentVia('telegram'))->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Driver detection via Payload::driver field vs config fallback
// ---------------------------------------------------------------------------

it('records driver from payload driver field when explicitly set', function () {
    $fake = Howl::fake();

    // Dispatch a payload that has driver field set explicitly
    $payload = new Payload(
        title: 'Test',
        description: null,
        severity: 'info',
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
        forceSync: false,
        driver: 'telegram',
    );

    app('howl')->dispatch($payload);

    expect($fake->sentVia('telegram'))->toHaveCount(1)
        ->and($fake->sentVia('discord'))->toBeEmpty();
});
