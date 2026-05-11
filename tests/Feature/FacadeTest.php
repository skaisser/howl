<?php

use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Facades\Howl;
use Skaisser\Howl\Support\PendingNotification;

it('Howl::onDiscord() resolves through the facade and returns a PendingNotification', function () {
    // The real driver would short-circuit in testing env — that is fine here;
    // we are only asserting the return type, not actually dispatching.
    $pending = Howl::onDiscord();

    expect($pending)->toBeInstanceOf(PendingNotification::class);
});

it('Howl::onDiscord() with a channel sets the channel on the PendingNotification', function () {
    $pending = Howl::onDiscord('errors');

    // PendingNotification::channel is protected, but send() builds the Payload —
    // we can verify by calling Howl::fake() first and checking the captured payload.
    $fake = Howl::fake();

    Howl::onDiscord('errors')->info('test title');

    $sent = $fake->sent('errors');
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->channel)->toBe('errors');
});

it('onSlack() throws BadMethodCallException (reserved for v2)', function () {
    expect(fn () => Howl::onSlack())->toThrow(BadMethodCallException::class);
});

it('onTelegram() throws BadMethodCallException (reserved for v2)', function () {
    expect(fn () => Howl::onTelegram())->toThrow(BadMethodCallException::class);
});

it('getDriver() returns a Driver instance', function () {
    $howl = app('howl');

    expect($howl->getDriver())->toBeInstanceOf(Driver::class);
});

it('getConfig() returns the resolved configuration array', function () {
    $howl = app('howl');
    $config = $howl->getConfig();

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('driver');
});
