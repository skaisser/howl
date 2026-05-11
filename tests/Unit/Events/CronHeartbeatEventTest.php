<?php

use Skaisser\Howl\Events\CronHeartbeatEvent;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// 1. emoji
// ---------------------------------------------------------------------------

it('emoji() returns ⏱️', function () {
    $event = new CronHeartbeatEvent('invoices:send', new DateTime('2026-05-11 08:00:00'), 'ok');

    expect($event->emoji())->toBe('⏱️');
});

// ---------------------------------------------------------------------------
// 2. severity / channel — flip on 'failed'
// ---------------------------------------------------------------------------

it('severity() returns info for non-failed status', function () {
    $event = new CronHeartbeatEvent('test', new DateTime, 'ok');

    expect($event->severity())->toBe('info');
});

it('severity() returns error when status is failed', function () {
    $event = new CronHeartbeatEvent('test', new DateTime, 'failed');

    expect($event->severity())->toBe('error');
});

it('channel() returns info for non-failed status', function () {
    $event = new CronHeartbeatEvent('test', new DateTime, 'late');

    expect($event->channel())->toBe('info');
});

it('channel() returns errors when status is failed', function () {
    $event = new CronHeartbeatEvent('test', new DateTime, 'failed');

    expect($event->channel())->toBe('errors');
});

// ---------------------------------------------------------------------------
// 3. title
// ---------------------------------------------------------------------------

it('title() contains schedule name and status', function () {
    $event = new CronHeartbeatEvent('invoices:send', new DateTime('2026-05-11 08:00:00'), 'ok');

    expect($event->title())
        ->toContain('⏱️')
        ->toContain('invoices:send')
        ->toContain('ok');
});

// ---------------------------------------------------------------------------
// 4. description
// ---------------------------------------------------------------------------

it('description() mentions the schedule name and status', function () {
    $event = new CronHeartbeatEvent('sync:orders', new DateTime, 'late');

    expect($event->description())
        ->toContain('sync:orders')
        ->toContain('late');
});

// ---------------------------------------------------------------------------
// 5. fields — Last Run uses Discord relative timestamp
// ---------------------------------------------------------------------------

it('fields() contains Schedule, Status and Last Run with relative timestamp', function () {
    $dt = new DateTime('2026-05-11 08:00:00');
    $event = new CronHeartbeatEvent('sync:orders', $dt, 'late');

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['📋 Schedule'])->toBe('sync:orders')
        ->and($fieldValues['📊 Status'])->toBe('late')
        ->and($fieldValues['🕐 Last Run'])->toStartWith('<t:')
        ->and($fieldValues['🕐 Last Run'])->toEndWith(':R>');
});

it('fields() Last Run relative timestamp embeds the correct Unix timestamp', function () {
    $dt = new DateTime('@1000000000');   // known Unix timestamp
    $event = new CronHeartbeatEvent('heartbeat', $dt, 'ok');

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['🕐 Last Run'])->toContain('1000000000');
});

it('fields() includes Duration when provided', function () {
    $event = new CronHeartbeatEvent('heartbeat', new DateTime, 'ok', 90);

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['⏱️ Duration'])->toBe('1m 30s');
});

it('fields() formats sub-minute duration as seconds', function () {
    $event = new CronHeartbeatEvent('heartbeat', new DateTime, 'ok', 45);

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['⏱️ Duration'])->toBe('45s');
});

it('fields() omits Duration when not provided', function () {
    $event = new CronHeartbeatEvent('heartbeat', new DateTime, 'ok');

    $fieldNames = array_column($event->fields(), 'name');

    expect($fieldNames)->not->toContain('⏱️ Duration');
});

// ---------------------------------------------------------------------------
// 6. codeBlocks — always empty
// ---------------------------------------------------------------------------

it('codeBlocks() returns empty array', function () {
    $event = new CronHeartbeatEvent('invoices:send', new DateTime, 'ok');

    expect($event->codeBlocks())->toBe([]);
});

// ---------------------------------------------------------------------------
// 7. footerMeta
// ---------------------------------------------------------------------------

it('footerMeta() contains schedule and status keys', function () {
    $event = new CronHeartbeatEvent('invoices:send', new DateTime, 'ok');

    $footer = $event->footerMeta();

    expect($footer)->toHaveKeys(['schedule', 'status'])
        ->and($footer['schedule'])->toBe('invoices:send')
        ->and($footer['status'])->toBe('ok');
});

// ---------------------------------------------------------------------------
// 8. links rendered as fields
// ---------------------------------------------------------------------------

it('toPayload() appends link fields from $links', function () {
    $event = new CronHeartbeatEvent(
        'invoices:send', new DateTime, 'ok', null,
        ['order' => 'https://example.com/orders/1'],
    );

    $fieldNames = array_column($event->toPayload()->fields, 'name');

    expect($fieldNames)->toContain('📦 Order');
});

// ---------------------------------------------------------------------------
// 9. end-to-end toPayload()
// ---------------------------------------------------------------------------

it('toPayload() returns a Payload with severity=info and correct structure for ok status', function () {
    $event = new CronHeartbeatEvent('invoices:send', new DateTime('2026-05-11 08:00:00'), 'ok');

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->severity)->toBe('info')
        ->and($payload->title)->toContain('invoices:send')
        ->and($payload->codeBlocks)->toBe([])
        ->and($payload->meta)->toHaveKey('schedule');
});

it('toPayload() returns severity=error and channel=errors for failed status', function () {
    $event = new CronHeartbeatEvent('invoices:send', new DateTime, 'failed');

    $payload = $event->toPayload();

    expect($payload->severity)->toBe('error')
        ->and($payload->channel)->toBe('errors');
});

it('toPayload() meta includes base footer keys and subclass keys', function () {
    $event = new CronHeartbeatEvent('heartbeat', new DateTime, 'ok');

    $meta = $event->toPayload()->meta;

    expect($meta)->toHaveKeys(['event', 'severity', 'env', 'trace', 'timestamp', 'schedule', 'status']);
});
