<?php

use Skaisser\Howl\Events\JobRetryExhaustedEvent;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// 1. emoji / severity / channel
// ---------------------------------------------------------------------------

it('emoji() returns 🚨', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: ['order_id' => 42],
        attempts: 3,
        lastException: new RuntimeException('Connection timeout'),
    );

    expect($event->emoji())->toBe('🚨');
});

it('severity() returns error', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    expect($event->severity())->toBe('error');
});

it('channel() returns errors', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    expect($event->channel())->toBe('errors');
});

// ---------------------------------------------------------------------------
// 2. title
// ---------------------------------------------------------------------------

it('title() uses class_basename of the job class', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    expect($event->title())->toBe('Job retry exhausted: ProcessPayment');
});

it('title() works with a bare class name (no namespace)', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'SendEmail',
        payload: [],
        attempts: 1,
        lastException: new RuntimeException('boom'),
    );

    expect($event->title())->toBe('Job retry exhausted: SendEmail');
});

// ---------------------------------------------------------------------------
// 3. description
// ---------------------------------------------------------------------------

it('description() returns the exception message', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('Database connection lost'),
    );

    expect($event->description())->toBe('Database connection lost');
});

it('description() truncates messages longer than 1024 chars', function () {
    $long = str_repeat('x', 2000);
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException($long),
    );

    // Str::limit appends '...' so string will be <= 1024+3 but the visible content is trimmed
    expect(strlen($event->description()))->toBeLessThanOrEqual(1027);
});

// ---------------------------------------------------------------------------
// 4. fields
// ---------------------------------------------------------------------------

it('fields() always contains Job and Attempts', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 5,
        lastException: new RuntimeException('boom'),
    );

    $fields = $event->fields();

    expect($fields)->toHaveCount(2)
        ->and($fields[0])->toBe(['name' => '🔧 Job', 'value' => 'ProcessPayment', 'inline' => true])
        ->and($fields[1])->toBe(['name' => '🔄 Attempts', 'value' => '5', 'inline' => true]);
});

it('fields() includes Queue field when meta[queue] is provided', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\SendEmail',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
        meta: ['queue' => 'high-priority'],
    );

    $fields = $event->fields();

    expect($fields)->toHaveCount(3)
        ->and($fields[2])->toBe(['name' => '📥 Queue', 'value' => 'high-priority', 'inline' => true]);
});

it('fields() omits Queue field when meta[queue] is not set', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\SendEmail',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    expect($event->fields())->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// 5. codeBlocks
// ---------------------------------------------------------------------------

it('codeBlocks() returns exactly two entries: Source and Payload', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: ['order_id' => 42],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    $blocks = $event->codeBlocks();

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['name'])->toBe('📁 Source')
        ->and($blocks[0]['lang'])->toBe('php')
        ->and($blocks[1]['name'])->toBe('📦 Payload')
        ->and($blocks[1]['lang'])->toBe('json');
});

it('codeBlocks() Source entry does not start with /Users/', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    expect($event->codeBlocks()[0]['code'])->not->toStartWith('/Users/');
});

it('codeBlocks() Source entry contains file:line format', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    expect($event->codeBlocks()[0]['code'])->toContain(':');
});

it('codeBlocks() Payload entry is valid JSON', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: ['order_id' => 42, 'amount' => 9.99],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    $code = $event->codeBlocks()[1]['code'];

    expect(json_decode($code, true))->not->toBeNull();
});

it('codeBlocks() Payload for empty payload encodes as empty JSON object/array', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    expect($event->codeBlocks()[1]['code'])->toBe('[]');
});

it('codeBlocks() Payload is truncated to 1000 chars for very large payloads', function () {
    $largePayload = array_fill(0, 200, str_repeat('x', 50));
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: $largePayload,
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    expect(strlen($event->codeBlocks()[1]['code']))->toBeLessThanOrEqual(1000);
});

// ---------------------------------------------------------------------------
// 6. footerMeta
// ---------------------------------------------------------------------------

it('footerMeta() contains job_class and attempts', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 7,
        lastException: new RuntimeException('boom'),
    );

    $footer = $event->footerMeta();

    expect($footer)->toHaveKeys(['job_class', 'attempts'])
        ->and($footer['job_class'])->toBe('App\\Jobs\\ProcessPayment')
        ->and($footer['attempts'])->toBe(7);
});

// ---------------------------------------------------------------------------
// 7. links rendered
// ---------------------------------------------------------------------------

it('toPayload() appends renderLinks fields after own fields', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
        links: ['order' => 'https://example.com/orders/1'],
    );

    $payload = $event->toPayload();

    // 2 own fields + 1 link = 3 total
    expect($payload->fields)->toHaveCount(3)
        ->and($payload->fields[2]['name'])->toBe('📦 Order');
});

// ---------------------------------------------------------------------------
// 8. toPayload end-to-end
// ---------------------------------------------------------------------------

it('toPayload() produces a Payload with correct shape', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: ['order_id' => 42],
        attempts: 3,
        lastException: new RuntimeException('Connection lost'),
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->severity)->toBe('error')
        ->and($payload->title)->toContain('ProcessPayment')
        ->and($payload->codeBlocks)->toHaveCount(2);
});

it('toPayload() meta includes base footer keys merged with subclass keys', function () {
    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\ProcessPayment',
        payload: [],
        attempts: 3,
        lastException: new RuntimeException('boom'),
    );

    $meta = $event->toPayload()->meta;

    expect($meta)->toHaveKeys(['event', 'severity', 'env', 'trace', 'timestamp', 'job_class', 'attempts']);
});
