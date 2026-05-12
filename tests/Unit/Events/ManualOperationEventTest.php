<?php

use Skaisser\Howl\Events\ManualOperationEvent;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// 1. emoji / severity / channel
// ---------------------------------------------------------------------------

it('emoji() returns ℹ️', function () {
    $event = new ManualOperationEvent('admin@example.com', 'howl:test-webhook');

    expect($event->emoji())->toBe('ℹ️');
});

it('severity() returns info', function () {
    $event = new ManualOperationEvent('admin@example.com', 'howl:test-webhook');

    expect($event->severity())->toBe('info');
});

it('channel() returns info', function () {
    $event = new ManualOperationEvent('admin@example.com', 'howl:test-webhook');

    expect($event->channel())->toBe('info');
});

// ---------------------------------------------------------------------------
// 2. title
// ---------------------------------------------------------------------------

it('title() includes command and operator', function () {
    $event = new ManualOperationEvent('john', 'orders:reprocess');

    expect($event->title())->toBe('Manual op: orders:reprocess by john');
});

// ---------------------------------------------------------------------------
// 3. description
// ---------------------------------------------------------------------------

it('description() returns a sensible default string', function () {
    $event = new ManualOperationEvent('john', 'orders:reprocess');

    expect($event->description())->toContain('john')
        ->and($event->description())->toContain('orders:reprocess');
});

// ---------------------------------------------------------------------------
// 4. fields
// ---------------------------------------------------------------------------

it('fields() always contains Operator and Command', function () {
    $event = new ManualOperationEvent('john', 'orders:reprocess');

    $fields = $event->fields();

    expect($fields)->toHaveCount(2)
        ->and($fields[0])->toBe(['name' => '👤 Operator', 'value' => 'john', 'inline' => true])
        ->and($fields[1])->toBe(['name' => '⚡ Command', 'value' => 'orders:reprocess', 'inline' => true]);
});

it('fields() includes Duration when durationSeconds is provided', function () {
    $event = new ManualOperationEvent(
        operator: 'john',
        command: 'orders:reprocess',
        durationSeconds: 45,
    );

    $fields = $event->fields();

    expect($fields)->toHaveCount(3)
        ->and($fields[2]['name'])->toBe('⏱️ Duration')
        ->and($fields[2]['value'])->toBe('45s')
        ->and($fields[2]['inline'])->toBeTrue();
});

it('fields() omits Duration when durationSeconds is null', function () {
    $event = new ManualOperationEvent('john', 'orders:reprocess');

    expect($event->fields())->toHaveCount(2);
});

it('fields() formats duration in minutes and seconds when >= 60s', function () {
    $event = new ManualOperationEvent(
        operator: 'john',
        command: 'orders:reprocess',
        durationSeconds: 125,
    );

    $fields = $event->fields();
    $durationField = collect($fields)->firstWhere('name', '⏱️ Duration');

    expect($durationField['value'])->toBe('2m 5s');
});

it('fields() formats exactly 60 seconds as 1m 0s', function () {
    $event = new ManualOperationEvent(
        operator: 'john',
        command: 'orders:reprocess',
        durationSeconds: 60,
    );

    $fields = $event->fields();
    $durationField = collect($fields)->firstWhere('name', '⏱️ Duration');

    expect($durationField['value'])->toBe('1m 0s');
});

// ---------------------------------------------------------------------------
// 5. codeBlocks
// ---------------------------------------------------------------------------

it('codeBlocks() returns empty array when args is empty', function () {
    $event = new ManualOperationEvent('john', 'orders:reprocess');

    expect($event->codeBlocks())->toBe([]);
});

it('codeBlocks() returns one Args entry when args are provided', function () {
    $event = new ManualOperationEvent(
        operator: 'john',
        command: 'orders:reprocess',
        args: ['--dry-run' => true, '--limit' => 100],
    );

    $blocks = $event->codeBlocks();

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['name'])->toBe('⚙️ Args')
        ->and($blocks[0]['lang'])->toBe('json');
});

it('codeBlocks() Args entry is valid JSON', function () {
    $event = new ManualOperationEvent(
        operator: 'john',
        command: 'orders:reprocess',
        args: ['--limit' => 100, '--queue' => 'default'],
    );

    $code = $event->codeBlocks()[0]['code'];

    expect(json_decode($code, true))->not->toBeNull();
});

it('codeBlocks() truncates very large args to 600 chars', function () {
    $largeArgs = array_fill(0, 100, str_repeat('x', 50));
    $event = new ManualOperationEvent(
        operator: 'john',
        command: 'orders:reprocess',
        args: $largeArgs,
    );

    expect(strlen($event->codeBlocks()[0]['code']))->toBeLessThanOrEqual(600);
});

// ---------------------------------------------------------------------------
// 6. footerMeta
// ---------------------------------------------------------------------------

it('footerMeta() contains operator and command', function () {
    $event = new ManualOperationEvent('john', 'orders:reprocess');

    $footer = $event->footerMeta();

    expect($footer)->toHaveKeys(['operator', 'command'])
        ->and($footer['operator'])->toBe('john')
        ->and($footer['command'])->toBe('orders:reprocess');
});

// ---------------------------------------------------------------------------
// 7. links rendered
// ---------------------------------------------------------------------------

it('toPayload() appends renderLinks fields after own fields', function () {
    $event = new ManualOperationEvent(
        operator: 'john',
        command: 'orders:reprocess',
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
    $event = new ManualOperationEvent('john', 'orders:reprocess');

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->severity)->toBe('info')
        ->and($payload->title)->toContain('orders:reprocess')
        ->and($payload->title)->toContain('john');
});

it('toPayload() meta includes base footer keys merged with subclass keys', function () {
    $event = new ManualOperationEvent('john', 'orders:reprocess');

    $meta = $event->toPayload()->meta;

    expect($meta)->toHaveKeys(['event', 'severity', 'env', 'trace', 'timestamp', 'operator', 'command']);
});
