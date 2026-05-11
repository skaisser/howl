<?php

use Illuminate\Support\Facades\Queue;
use Skaisser\Howl\Howl as HowlClass;
use Skaisser\Howl\Jobs\SendHowlJob;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Helpers scoped to this file via dataset / closures
// ---------------------------------------------------------------------------

function queueTestPayload(bool $forceSync = false): Payload
{
    return new Payload(
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
        forceSync: $forceSync,
        fallback: null,
    );
}

function queueTestHowl(array $overrides = []): HowlClass
{
    $config = array_merge(
        app('config')->get('howl', []),
        ['skip_environments' => [], 'app_env' => 'production'],
        $overrides,
    );

    return new HowlClass($config);
}

// ---------------------------------------------------------------------------
// Queue mode tests
// ---------------------------------------------------------------------------

it('dispatches a job when queue mode is enabled', function () {
    Queue::fake();

    $howl = queueTestHowl(['queue' => true, 'driver' => 'null']);
    $howl->dispatch(queueTestPayload());

    Queue::assertPushed(SendHowlJob::class, 1);
});

it('calls driver synchronously when queue mode is disabled (default)', function () {
    $howl = queueTestHowl(['queue' => false, 'driver' => 'null']);

    $result = $howl->dispatch(queueTestPayload());

    expect($result)->toBeTrue();
});

it('respects forceSync flag — never enqueues even when queue mode is on', function () {
    Queue::fake();

    $howl = queueTestHowl(['queue' => true, 'driver' => 'null']);
    $result = $howl->dispatch(queueTestPayload(forceSync: true));

    // No job pushed, and sync driver was called (NullDriver returns true)
    Queue::assertNothingPushed();
    expect($result)->toBeTrue();
});

it('configures the job with correct retries and backoff', function () {
    $payload = queueTestPayload();
    $job = new SendHowlJob($payload, 'discord');

    expect($job->tries)->toBe(3);
    expect($job->backoff())->toBe([1, 4, 16]);
});

it('job carries the correct payload and driver name', function () {
    $payload = queueTestPayload();
    $job = new SendHowlJob($payload, 'null');

    expect($job->payload)->toBe($payload);
    expect($job->driverName)->toBe('null');
});

it('dispatches job to the configured connection and queue', function () {
    Queue::fake();

    $howl = queueTestHowl([
        'queue' => true,
        'driver' => 'null',
        'queue_connection' => 'redis',
        'queue_name' => 'notifications',
    ]);

    $howl->dispatch(queueTestPayload());

    Queue::assertPushedOn('notifications', SendHowlJob::class);
});

it('does not dispatch a job when env is in skip_environments', function () {
    Queue::fake();

    // skip_environments check happens before queue branch
    $howl = queueTestHowl([
        'queue' => true,
        'driver' => 'null',
        'app_env' => 'testing',
        'skip_environments' => ['testing'],
    ]);

    $result = $howl->dispatch(queueTestPayload());

    Queue::assertNothingPushed();
    expect($result)->toBeTrue(); // short-circuits as success
});
