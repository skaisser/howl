<?php

use Skaisser\Howl\Events\AuditEvent;
use Skaisser\Howl\Events\CronHeartbeatEvent;
use Skaisser\Howl\Events\DeploymentEvent;
use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Events\GenericInfoEvent;
use Skaisser\Howl\Events\JobRetryExhaustedEvent;
use Skaisser\Howl\Events\ManualOperationEvent;
use Skaisser\Howl\Facades\Howl;

// ---------------------------------------------------------------------------
// Dataset — one entry per generic event; each entry provides:
//   [Closure $factory, string $verb]
//
//   $factory is a closure so each test instantiates a fresh event.
//   $verb is the terminal severity method whose name matches the event's
//   own severity(), satisfying the mismatch guard once the wire is live.
// ---------------------------------------------------------------------------

dataset('generic_events', [
    'generic-exception' => [
        fn () => new GenericExceptionEvent(new RuntimeException('Test exception')),
        'error',
    ],
    'generic-info' => [
        fn () => new GenericInfoEvent('📢 Info title', 'Info description'),
        'info',
    ],
    'audit' => [
        fn () => new AuditEvent('admin@example.com', 'updated', 'Post'),
        'audit',
    ],
    'deployment' => [
        fn () => new DeploymentEvent('v1.0.0', 'production', 'abc1234'),
        'deployment',
    ],
    'cron-heartbeat' => [
        fn () => new CronHeartbeatEvent('daily-report', new DateTimeImmutable('2026-01-01 00:00:00'), 'ok'),
        'info',
    ],
    'job-retry-exhausted' => [
        fn () => new JobRetryExhaustedEvent(
            'App\Jobs\SendEmail',
            ['email' => 'user@example.com'],
            3,
            new RuntimeException('Mail failed'),
        ),
        'error',
    ],
    'manual-operation' => [
        fn () => new ManualOperationEvent('admin', 'artisan:migrate'),
        'info',
    ],
]);

// ---------------------------------------------------------------------------
// End-to-end dispatch test (1 test × 7 dataset entries = 7 cases)
//
// Flow: Howl::onDiscord()->{verb}($event)
//   → terminal method detects HowlEvent → delegates to ->send()
//   → buildPayload() calls $event->toPayload() for base fields
//   → HowlFake captures the resulting Payload
//
// Assertion: the captured Payload's stable fields (title, description,
//   severity, channel) match those returned by $event->toPayload().
//   Dynamic fields (trace ULID, timestamp) are intentionally excluded
//   because toPayload() regenerates them on every call.
// ---------------------------------------------------------------------------

it('dispatches generic event end-to-end: captured payload matches event->toPayload()', function (Closure $factory, string $verb) {
    $fake = Howl::fake();
    $event = $factory();

    Howl::onDiscord()->{$verb}($event);

    $captured = $fake->sent()[0];
    $expected = $event->toPayload();

    expect($captured->title)->toBe($expected->title)
        ->and($captured->description)->toBe($expected->description)
        ->and($captured->severity)->toBe($expected->severity)
        ->and($captured->channel)->toBe($expected->channel);
})->with('generic_events');
