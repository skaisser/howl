<?php

use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// 1. emoji
// ---------------------------------------------------------------------------

it('emoji() returns 🚨', function () {
    $event = new GenericExceptionEvent(new RuntimeException('boom'));

    expect($event->emoji())->toBe('🚨');
});

// ---------------------------------------------------------------------------
// 2. severity / channel
// ---------------------------------------------------------------------------

it('severity() returns error', function () {
    $event = new GenericExceptionEvent(new RuntimeException('boom'));

    expect($event->severity())->toBe('error');
});

it('channel() returns errors', function () {
    $event = new GenericExceptionEvent(new RuntimeException('boom'));

    expect($event->channel())->toBe('errors');
});

// ---------------------------------------------------------------------------
// 3. title — default and override
// ---------------------------------------------------------------------------

it('title() defaults to class-basename of the exception', function () {
    $event = new GenericExceptionEvent(new RuntimeException('boom'));

    expect($event->title())->toBe('Exception: RuntimeException');
});

it('title() uses custom title when provided', function () {
    $event = new GenericExceptionEvent(new RuntimeException('boom'), 'My custom title');

    expect($event->title())->toBe('My custom title');
});

// ---------------------------------------------------------------------------
// 4. description — default and override
// ---------------------------------------------------------------------------

it('description() defaults to exception message', function () {
    $event = new GenericExceptionEvent(new RuntimeException('Database connection lost'));

    expect($event->description())->toBe('Database connection lost');
});

it('description() uses custom description when provided', function () {
    $event = new GenericExceptionEvent(
        new RuntimeException('original message'),
        null,
        'Custom description text',
    );

    expect($event->description())->toBe('Custom description text');
});

it('description() truncates messages longer than 1024 chars', function () {
    $long = str_repeat('x', 2000);
    $event = new GenericExceptionEvent(new RuntimeException($long));

    expect(strlen($event->description()))->toBe(1024);
});

// ---------------------------------------------------------------------------
// 5. fields — always empty (source is in codeBlocks)
// ---------------------------------------------------------------------------

it('fields() returns an empty array', function () {
    $event = new GenericExceptionEvent(new RuntimeException('test'));

    expect($event->fields())->toBe([]);
});

// ---------------------------------------------------------------------------
// 6. codeBlocks
// ---------------------------------------------------------------------------

it('codeBlocks() returns exactly one entry with name "📁 Source" and lang "php"', function () {
    $event = new GenericExceptionEvent(new RuntimeException('test'));

    $blocks = $event->codeBlocks();

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['name'])->toBe('📁 Source')
        ->and($blocks[0]['lang'])->toBe('php')
        ->and($blocks[0]['code'])->toContain(':');
});

it('codeBlocks() source does not start with /Users/', function () {
    $event = new GenericExceptionEvent(new RuntimeException('test'));

    expect($event->codeBlocks()[0]['code'])->not->toStartWith('/Users/');
});

// ---------------------------------------------------------------------------
// 7. footerMeta
// ---------------------------------------------------------------------------

it('footerMeta() contains class, file, and line keys', function () {
    $event = new GenericExceptionEvent(new RuntimeException('test'));

    $footer = $event->footerMeta();

    expect($footer)->toHaveKeys(['class', 'file', 'line'])
        ->and($footer['class'])->toBe('RuntimeException');
});

it('footerMeta() file does not start with /Users/', function () {
    $event = new GenericExceptionEvent(new RuntimeException('test'));

    expect($event->footerMeta()['file'])->not->toStartWith('/Users/');
});

// ---------------------------------------------------------------------------
// 8. toPayload end-to-end
// ---------------------------------------------------------------------------

it('toPayload() has severity=error, title containing RuntimeException, one codeBlock, and class in meta', function () {
    $event = new GenericExceptionEvent(new RuntimeException('Something broke'));

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->severity)->toBe('error')
        ->and($payload->title)->toContain('RuntimeException')
        ->and($payload->codeBlocks)->toHaveCount(1)
        ->and($payload->meta)->toHaveKey('class');
});

it('toPayload() fields count equals renderLinks count when $links provided', function () {
    $event = new GenericExceptionEvent(
        new RuntimeException('test'),
        null,
        null,
        ['order' => 'https://example.com/orders/1'],
    );

    $payload = $event->toPayload();

    // fields() returns [] — only renderLinks() fields appear
    expect($payload->fields)->toHaveCount(1)
        ->and($payload->fields[0]['name'])->toBe('📦 Order');
});

it('toPayload() meta includes base footer keys and subclass keys', function () {
    $event = new GenericExceptionEvent(new RuntimeException('test'));

    $meta = $event->toPayload()->meta;

    expect($meta)->toHaveKeys(['event', 'severity', 'env', 'trace', 'timestamp', 'class', 'file', 'line']);
});
