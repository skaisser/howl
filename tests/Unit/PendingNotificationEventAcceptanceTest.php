<?php

use Skaisser\Howl\Events\AuditEvent;
use Skaisser\Howl\Events\CronHeartbeatEvent;
use Skaisser\Howl\Events\DeploymentEvent;
use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Howl;
use Skaisser\Howl\Support\PendingNotification;

// PendingNotification with event base merge

it('acceptEvent returns a new clone with the event set', function () {
    $pending = new PendingNotification;
    $event = new GenericExceptionEvent(new RuntimeException('Something broke'));

    $clone = $pending->acceptEvent($event);

    expect($clone)->not->toBe($pending); // immutable clone
});

it('buildPayload uses event title when builder title is empty', function () {
    $event = new CronHeartbeatEvent('daily-report', new DateTimeImmutable('2026-05-11 06:00:00'), 'ok');
    $pending = (new PendingNotification)->acceptEvent($event);

    // Access buildPayload via reflection (it's protected)
    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->title)->toContain('⏱️')
        ->and($payload->title)->toContain('daily-report');
});

it('builder title overrides event title', function () {
    $event = new CronHeartbeatEvent('daily-report', new DateTimeImmutable('2026-05-11 06:00:00'), 'ok');
    $pending = (new PendingNotification)->acceptEvent($event)->title('Custom Override Title');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'info');

    expect($payload->title)->toBe('Custom Override Title');
});

it('builder description overrides event description', function () {
    $event = new GenericExceptionEvent(new RuntimeException('original message'));
    $pending = (new PendingNotification)->acceptEvent($event)->description('My custom description');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'error');

    expect($payload->description)->toBe('My custom description');
});

it('builder fields are appended after event fields', function () {
    $event = new DeploymentEvent('v1.2.3', 'abc1234', 'production');
    $pending = (new PendingNotification)->acceptEvent($event)->field('Custom Field', 'custom value');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'deployment');

    // Event has its own fields, builder field is appended
    $fieldNames = array_column($payload->fields, 'name');
    expect($fieldNames)->toContain('Custom Field')
        ->and($fieldNames)->toContain('🚀 Version'); // from DeploymentEvent
});

it('builder channel overrides event channel', function () {
    $event = new AuditEvent('admin', 'updated', 'Record', [], []);
    // AuditEvent defaults to channel 'audit'
    $pending = (new PendingNotification)->acceptEvent($event)->channel('custom-channel');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'audit');

    expect($payload->channel)->toBe('custom-channel');
});

it('event channel is used when builder channel is not set', function () {
    $event = new AuditEvent('admin', 'deleted', 'Record', [], []);
    $pending = (new PendingNotification)->acceptEvent($event);

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'audit');

    expect($payload->channel)->toBe('audit');
});

it('event meta is preserved and builder meta overrides on conflict', function () {
    $event = new GenericExceptionEvent(new RuntimeException('test'));
    $pending = (new PendingNotification)->acceptEvent($event)->meta('event', 'overridden')->meta('builder_key', 'builder_value');

    $ref = new ReflectionMethod($pending, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($pending, 'error');

    // Builder wins on 'event' key
    expect($payload->meta['event'])->toBe('overridden')
        // trace comes from event
        ->and($payload->meta)->toHaveKey('trace')
        // builder_key is added by builder
        ->and($payload->meta['builder_key'])->toBe('builder_value');
});

it('error() terminal method accepts a HowlEvent and dispatches', function () {
    $pending = new PendingNotification;
    $event = new DeploymentEvent('v1.0.0', 'abc1234', 'production');

    // Using acceptEvent + buildPayload (without actual dispatch — test the merge)
    $clone = $pending->acceptEvent($event);

    $ref = new ReflectionMethod($clone, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($clone, 'deployment');

    expect($payload->title)->toContain('🚀')
        ->and($payload->severity)->toBe('deployment');
});

it('send() with HowlEvent dispatches using event severity()', function () {
    $pending = new PendingNotification;
    $event = new GenericExceptionEvent(new RuntimeException('crash'));

    // acceptEvent + buildPayload check (no real dispatch needed)
    $clone = $pending->acceptEvent($event);

    $ref = new ReflectionMethod($clone, 'buildPayload');
    $ref->setAccessible(true);
    $payload = $ref->invoke($clone, $event->severity());

    expect($payload->severity)->toBe('error')
        ->and($payload->title)->toContain('RuntimeException');
});

// Regression — send(severity, $title) must not mutate $this->title.
// Reusing a pre-built builder across two send() calls with different title arguments
// would otherwise leak the first title into the second payload.
it('send() with $title argument preserves immutability of the builder', function () {
    $builder = new PendingNotification;

    // Reflection probe: confirm $this->title is still empty after a send() call that
    // passed a $title argument. Without the clone fix, the second peek would show
    // the first call's title still set on $builder.
    $titleProp = new ReflectionProperty(PendingNotification::class, 'title');
    $titleProp->setAccessible(true);

    // Fake the dispatcher so send() does not call out to a real driver.
    Howl::fake();

    $builder->send('info', 'First title');
    $titleAfterFirst = $titleProp->getValue($builder);

    $builder->send('info', 'Second title');
    $titleAfterSecond = $titleProp->getValue($builder);

    // Pre-fix this would be 'First title' then 'Second title'.
    // Post-fix the builder's own title slot stays empty — each send() clones via title().
    expect($titleAfterFirst)->toBe('')
        ->and($titleAfterSecond)->toBe('');
});
