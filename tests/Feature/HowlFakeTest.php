<?php

use PHPUnit\Framework\AssertionFailedError;
use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Facades\Howl;
use Skaisser\Howl\Support\Payload;
use Skaisser\Howl\Testing\HowlFake;

// ---------------------------------------------------------------------------
// Howl::fake() bootstrap
// ---------------------------------------------------------------------------

it('Howl::fake() returns a HowlFake instance', function () {
    $fake = Howl::fake();

    expect($fake)->toBeInstanceOf(HowlFake::class);
});

it('Howl::fake() swaps the container binding so dispatches are captured', function () {
    Howl::fake();

    Howl::on('errors')->error('Something broke');

    expect(Howl::sent())->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// assertSent — positive + negative paths
// ---------------------------------------------------------------------------

it('assertSent passes when at least one payload matches the callback', function () {
    $fake = Howl::fake();

    Howl::on()->error('NFe processing failed');

    $fake->assertSent(fn ($p) => str_contains($p->title, 'NFe'));
});

it('assertSent fails when no payload matches', function () {
    $fake = Howl::fake();

    Howl::on()->error('Other error');

    expect(fn () => $fake->assertSent(fn ($p) => $p->title === 'NFe'))
        ->toThrow(AssertionFailedError::class);
});

// ---------------------------------------------------------------------------
// assertSentOnChannel — positive + negative paths
// ---------------------------------------------------------------------------

it('assertSentOnChannel passes when a matching payload was sent to the given channel', function () {
    $fake = Howl::fake();

    Howl::on('errors')->error('Critical failure');

    $fake->assertSentOnChannel('errors', fn ($p) => $p->severity === 'error');
});

it('assertSentOnChannel fails when no payload was sent to that channel', function () {
    $fake = Howl::fake();

    Howl::on('warnings')->warning('Minor issue');

    expect(fn () => $fake->assertSentOnChannel('errors', fn ($p) => true))
        ->toThrow(AssertionFailedError::class);
});

// ---------------------------------------------------------------------------
// assertSentEvent — positive + negative paths
// ---------------------------------------------------------------------------

it('assertSentEvent passes when a payload carries the expected snake_case event name in meta', function () {
    $fake = Howl::fake();

    // Manually-constructed payload with the snake_case name format that
    // HowlEvent::baseFooterMeta() emits at runtime.
    $payload = new Payload(
        title: 'Webhook failed',
        description: null,
        severity: 'error',
        channel: 'errors',
        fields: [],
        codeBlocks: [],
        mentions: [],
        meta: ['event' => 'webhook_failed'],
        buttons: [],
        attachments: [],
        threadId: null,
        username: null,
        app: null,
        env: null,
        timestamp: null,
        forceSync: false,
    );

    app('howl')->dispatch($payload);

    $fake->assertSentEvent('webhook_failed');
});

// Regression — assertSentEvent must work through the real event pipeline.
// Previously the test side-stepped this by constructing meta manually with FQCN,
// which masked the fact that the live pipeline emits snake_case basenames.
it('assertSentEvent passes when dispatched via the real HowlEvent pipeline', function () {
    $fake = Howl::fake();

    Howl::on()->error(new GenericExceptionEvent(new RuntimeException('boom')));

    // GenericExceptionEvent → 'generic_exception' (snake_case basename)
    $fake->assertSentEvent('generic_exception');
});

it('assertSentEvent fails when no payload carries the expected event name', function () {
    $fake = Howl::fake();

    Howl::on()->error('Generic error');

    expect(fn () => $fake->assertSentEvent('some_other_event'))
        ->toThrow(AssertionFailedError::class);
});

// ---------------------------------------------------------------------------
// assertNothingSent — positive + negative paths
// ---------------------------------------------------------------------------

it('assertNothingSent passes when nothing has been dispatched', function () {
    $fake = Howl::fake();

    $fake->assertNothingSent();
});

it('assertNothingSent fails when at least one payload was dispatched', function () {
    $fake = Howl::fake();

    Howl::on()->info('Something happened');

    expect(fn () => $fake->assertNothingSent())
        ->toThrow(AssertionFailedError::class);
});

// ---------------------------------------------------------------------------
// sent() helper
// ---------------------------------------------------------------------------

it('sent() with no argument returns all captured payloads flat', function () {
    $fake = Howl::fake();

    Howl::on('errors')->error('Error one');
    Howl::on('warnings')->warning('Warning one');

    expect($fake->sent())->toHaveCount(2);
});

it('sent() with a channel name returns only payloads for that channel', function () {
    $fake = Howl::fake();

    Howl::on('errors')->error('Error one');
    Howl::on('warnings')->warning('Warning one');

    expect($fake->sent('errors'))->toHaveCount(1)
        ->and($fake->sent('errors')[0]->channel)->toBe('errors');
});

it('sent() returns empty array for a channel that received nothing', function () {
    $fake = Howl::fake();

    Howl::on('errors')->error('Only errors');

    expect($fake->sent('warnings'))->toBeEmpty();
});
