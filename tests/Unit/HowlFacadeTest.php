<?php

use Skaisser\Howl\Events\AuditEvent;
use Skaisser\Howl\Events\DeploymentEvent;
use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Events\GenericInfoEvent;
use Skaisser\Howl\Facades\Howl;
use Skaisser\Howl\Support\PendingNotification;

// ---------------------------------------------------------------------------
// Phase 1 — Severity-terminal API on Howl class (all entry points)
// ---------------------------------------------------------------------------

it('Howl::error($event) dispatches and returns bool', function () {
    $fake = Howl::fake();

    $result = Howl::error(new GenericExceptionEvent(new RuntimeException('test')));

    expect($result)->toBeBool()
        ->and($fake->sent())->toHaveCount(1)
        ->and($fake->sent()[0]->severity)->toBe('error');
});

it('Howl::error("Title") dispatches with a string-built payload', function () {
    $fake = Howl::fake();

    $result = Howl::error('Something broke');

    expect($result)->toBeBool()
        ->and($fake->sent())->toHaveCount(1)
        ->and($fake->sent()[0]->title)->toBe('Something broke')
        ->and($fake->sent()[0]->severity)->toBe('error');
});

it('Howl::warning dispatches with warning severity', function () {
    $fake = Howl::fake();

    Howl::warning('Watch out');

    expect($fake->sent()[0]->severity)->toBe('warning');
});

it('Howl::info dispatches with info severity', function () {
    $fake = Howl::fake();

    Howl::info('FYI');

    expect($fake->sent()[0]->severity)->toBe('info');
});

it('Howl::audit dispatches with audit severity', function () {
    $fake = Howl::fake();

    Howl::audit(new AuditEvent('admin@example.com', 'updated', 'Post'));

    expect($fake->sent()[0]->severity)->toBe('audit');
});

it('Howl::deployment dispatches with deployment severity', function () {
    $fake = Howl::fake();

    Howl::deployment(new DeploymentEvent('v1.0.0', 'prod', 'abc123'));

    expect($fake->sent()[0]->severity)->toBe('deployment');
});

it('Howl::success dispatches with success severity', function () {
    $fake = Howl::fake();

    Howl::success('All good');

    expect($fake->sent()[0]->severity)->toBe('success');
});

it('Howl::on($channel)->error($event) chain sets channel before severity terminal', function () {
    $fake = Howl::fake();

    Howl::on('errors')->error(new GenericExceptionEvent(new RuntimeException('boom')));

    expect($fake->sent())->toHaveCount(1)
        ->and($fake->sent()[0]->channel)->toBe('errors')
        ->and($fake->sent()[0]->severity)->toBe('error');
});

it('Howl::on() returns a PendingNotification', function () {
    $pending = Howl::on();

    expect($pending)->toBeInstanceOf(PendingNotification::class);
});

it('Howl::driver() returns a PendingNotification', function () {
    $pending = Howl::driver('null');

    expect($pending)->toBeInstanceOf(PendingNotification::class);
});

it('chain order independence: driver()->channel() produces same result as on()->driver()', function () {
    $fake = Howl::fake();

    // driver()->channel()->info()
    Howl::driver('null')->channel('audits')->info('Test A');
    // on()->driver()->info()
    Howl::on('audits')->driver('null')->info('Test B');

    $sent = $fake->sent();
    expect($sent)->toHaveCount(2)
        ->and($sent[0]->channel)->toBe('audits')
        ->and($sent[0]->driver)->toBe('null')
        ->and($sent[1]->channel)->toBe('audits')
        ->and($sent[1]->driver)->toBe('null');
});
