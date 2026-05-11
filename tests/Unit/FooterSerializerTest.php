<?php

use Skaisser\Howl\Support\FooterSerializer;

it('includes severity always', function () {
    $result = FooterSerializer::serialize(
        severity: 'error',
        meta: [],
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    expect($result)->toContain('severity:error');
});

it('includes env always', function () {
    $result = FooterSerializer::serialize(
        severity: 'info',
        meta: [],
        env: 'staging',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    expect($result)->toContain('env:staging');
});

it('auto-injects event when present in meta', function () {
    $result = FooterSerializer::serialize(
        severity: 'error',
        meta: ['event' => 'meli.webhook.process_failed'],
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    expect($result)->toStartWith('event:meli.webhook.process_failed · ');
});

it('auto-injects trace when present in meta', function () {
    $result = FooterSerializer::serialize(
        severity: 'error',
        meta: ['trace' => '01HXY3K'],
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    expect($result)->toContain('trace:01HXY3K');
});

it('produces correct insertion order: event > severity > env > trace > user-meta > timestamp', function () {
    $result = FooterSerializer::serialize(
        severity: 'error',
        meta: [
            'event' => 'my.event',
            'trace' => 'TRC-1',
            'pedido_id' => '99',
        ],
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    $parts = explode(' · ', $result);

    expect($parts[0])->toBe('event:my.event')
        ->and($parts[1])->toBe('severity:error')
        ->and($parts[2])->toBe('env:production')
        ->and($parts[3])->toBe('trace:TRC-1')
        ->and($parts[4])->toBe('pedido_id:99')
        ->and(end($parts))->toBe('11/05/2026 06:57');
});

it('appends timestamp in dd/mm/yyyy hh:mm format', function () {
    $result = FooterSerializer::serialize(
        severity: 'info',
        meta: [],
        env: 'local',
        timestamp: new DateTimeImmutable('2026-05-11 14:30:00'),
    );

    expect($result)->toEndWith('11/05/2026 14:30');
});

it('sanitizes values containing the separator character', function () {
    $result = FooterSerializer::serialize(
        severity: 'error',
        meta: ['note' => 'before·after'],
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    // The separator · in a value must become •
    expect($result)->toContain('note:before•after')
        ->and($result)->not->toContain('note:before·after');
});

it('sanitizes keys containing the separator character', function () {
    $result = FooterSerializer::serialize(
        severity: 'error',
        meta: ['key·name' => 'value'],
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    expect($result)->toContain('key•name:value');
});

it('does not include event or trace when not in meta', function () {
    $result = FooterSerializer::serialize(
        severity: 'info',
        meta: [],
        env: 'local',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    expect($result)->not->toContain('event:')
        ->and($result)->not->toContain('trace:');
});

it('user meta keys appear in insertion order', function () {
    $result = FooterSerializer::serialize(
        severity: 'info',
        meta: [
            'zebra' => 'z',
            'apple' => 'a',
            'mango' => 'm',
        ],
        env: 'local',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
    );

    $pos_zebra = strpos($result, 'zebra:z');
    $pos_apple = strpos($result, 'apple:a');
    $pos_mango = strpos($result, 'mango:m');

    expect($pos_zebra)->toBeLessThan($pos_apple)
        ->and($pos_apple)->toBeLessThan($pos_mango);
});

// Regression — HowlEvent::baseFooterMeta() injects severity/env/timestamp into $payload->meta.
// FooterSerializer must skip these auto-injected keys when iterating user meta, otherwise the
// footer emits duplicates (severity:X · ... · severity:X again).
it('does not emit duplicate severity/env/timestamp when an event payload meta contains them', function () {
    $result = FooterSerializer::serialize(
        severity: 'error',
        meta: [
            // Simulate what HowlEvent::baseFooterMeta() injects into $payload->meta:
            'event' => 'generic_exception',
            'severity' => 'error',
            'env' => 'production',
            'trace' => 'ABC12345',
            'timestamp' => '11/05/2026 14:30',
            // User-supplied extra:
            'class' => 'RuntimeException',
        ],
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 14:30:00'),
    );

    // Each auto-injected key should appear exactly once in the footer string.
    expect(substr_count($result, 'severity:'))->toBe(1)
        ->and(substr_count($result, 'env:'))->toBe(1)
        ->and(substr_count($result, 'event:'))->toBe(1)
        ->and(substr_count($result, 'trace:'))->toBe(1)
        // User-supplied 'class' key flows through normally:
        ->and($result)->toContain('class:RuntimeException');
});
