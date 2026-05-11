<?php

use Skaisser\Howl\Events\GenericInfoEvent;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// 1. emoji — severity-mapped
// ---------------------------------------------------------------------------

it('emoji() returns ℹ️ for default severity info', function () {
    $event = new GenericInfoEvent('Title', 'Desc');

    expect($event->emoji())->toBe('ℹ️');
});

it('emoji() returns 🚨 when severity is error', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'error');

    expect($event->emoji())->toBe('🚨');
});

it('emoji() returns ✅ when severity is success', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'success');

    expect($event->emoji())->toBe('✅');
});

it('emoji() returns 🟡 when severity is warning', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'warning');

    expect($event->emoji())->toBe('🟡');
});

it('emoji() returns 🔒 when severity is audit', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'audit');

    expect($event->emoji())->toBe('🔒');
});

it('emoji() returns 🚀 when severity is deployment', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'deployment');

    expect($event->emoji())->toBe('🚀');
});

it('emoji() falls back to ℹ️ for unknown severity', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'unknown');

    expect($event->emoji())->toBe('ℹ️');
});

// ---------------------------------------------------------------------------
// 2. severity
// ---------------------------------------------------------------------------

it('severity() returns info by default', function () {
    $event = new GenericInfoEvent('Title', 'Desc');

    expect($event->severity())->toBe('info');
});

it('severity() returns the custom severity passed to constructor', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'error');

    expect($event->severity())->toBe('error');
});

// ---------------------------------------------------------------------------
// 3. title / description / fields
// ---------------------------------------------------------------------------

it('title() returns the constructor title', function () {
    $event = new GenericInfoEvent('My Notification', 'Body text');

    expect($event->title())->toBe('My Notification');
});

it('description() returns the constructor description', function () {
    $event = new GenericInfoEvent('Title', 'Body text here');

    expect($event->description())->toBe('Body text here');
});

it('fields() returns the constructor fields', function () {
    $fields = [
        ['name' => '📊 Status', 'value' => 'ok', 'inline' => true],
    ];
    $event = new GenericInfoEvent('Title', 'Desc', $fields);

    expect($event->fields())->toBe($fields);
});

it('fields() returns empty array when none provided', function () {
    $event = new GenericInfoEvent('Title', 'Desc');

    expect($event->fields())->toBe([]);
});

// ---------------------------------------------------------------------------
// 4. channel / footerMeta / codeBlocks
// ---------------------------------------------------------------------------

it('channel() returns info for default severity info', function () {
    $event = new GenericInfoEvent('Title', 'Desc');

    expect($event->channel())->toBe('info');
});

it('channel() returns errors when severity is error', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'error');

    expect($event->channel())->toBe('errors');
});

it('channel() returns warnings when severity is warning', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'warning');

    expect($event->channel())->toBe('warnings');
});

it('channel() returns info when severity is success', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'success');

    expect($event->channel())->toBe('info');
});

it('channel() returns audit when severity is audit', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'audit');

    expect($event->channel())->toBe('audit');
});

it('channel() returns deployments when severity is deployment', function () {
    $event = new GenericInfoEvent('Title', 'Desc', [], 'deployment');

    expect($event->channel())->toBe('deployments');
});

it('footerMeta() returns an empty array', function () {
    $event = new GenericInfoEvent('Title', 'Desc');

    expect($event->footerMeta())->toBe([]);
});

it('codeBlocks() returns an empty array', function () {
    $event = new GenericInfoEvent('Title', 'Desc');

    expect($event->codeBlocks())->toBe([]);
});

// ---------------------------------------------------------------------------
// 5. toPayload end-to-end
// ---------------------------------------------------------------------------

it('toPayload() has correct severity and title', function () {
    $event = new GenericInfoEvent('Hello World', 'A description', [], 'success');

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->severity)->toBe('success')
        ->and($payload->title)->toBe('Hello World');
});

it('toPayload() fields count equals constructor fields + renderLinks count', function () {
    $fields = [
        ['name' => '📊 Status', 'value' => 'ok', 'inline' => true],
    ];
    $event = new GenericInfoEvent(
        'Title',
        'Desc',
        $fields,
        'info',
        ['order' => 'https://example.com/orders/1'],
    );

    $payload = $event->toPayload();

    // 1 constructor field + 1 renderLinks field
    expect($payload->fields)->toHaveCount(2)
        ->and($payload->fields[0]['name'])->toBe('📊 Status')
        ->and($payload->fields[1]['name'])->toBe('📦 Order');
});

it('toPayload() meta contains base footer keys with no subclass additions', function () {
    $event = new GenericInfoEvent('Title', 'Desc');

    $meta = $event->toPayload()->meta;

    expect($meta)->toHaveKeys(['event', 'severity', 'env', 'trace', 'timestamp']);
});
