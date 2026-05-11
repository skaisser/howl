<?php

use Skaisser\Howl\Events\AuditEvent;
use Skaisser\Howl\Events\CronHeartbeatEvent;
use Skaisser\Howl\Events\DeploymentEvent;
use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Events\GenericInfoEvent;
use Skaisser\Howl\Events\JobRetryExhaustedEvent;
use Skaisser\Howl\Events\ManualOperationEvent;
use Skaisser\Howl\Support\Payload;

/**
 * Canonical fixture snapshot tests for the 7 generic event classes.
 *
 * Each test loads the matching JSON fixture from tests/Fixtures/Events/ and
 * asserts the event's toPayload() output matches on STABLE properties.
 * Volatile fields (trace, timestamp, file:line from live stack) are either
 * checked by type/format only, or replaced with placeholders in the fixtures.
 *
 * These tests make the fixtures load-bearing: if a contract method's output
 * changes, both the fixture and this test must be updated intentionally.
 */
function loadFixture(string $name): array
{
    $path = __DIR__.'/../Fixtures/Events/'.$name.'.json';

    return json_decode(file_get_contents($path), true);
}

// ---------------------------------------------------------------------------
// 1. GenericExceptionEvent
// ---------------------------------------------------------------------------

it('GenericExceptionEvent toPayload() matches fixture on stable properties', function () {
    $fixture = loadFixture('GenericExceptionEvent');

    $event = new GenericExceptionEvent(
        exception: new RuntimeException('Connection timed out after 30s', 503),
        links: ['dashboard' => 'https://admin.example.com/health'],
        meta: ['trace' => 'TRACE001'],
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->title)->toBe($fixture['title'])
        ->and($payload->description)->toBe($fixture['description'])
        ->and($payload->severity)->toBe($fixture['severity'])
        ->and($payload->channel)->toBe($fixture['channel'])
        ->and($payload->codeBlocks)->toHaveCount(count($fixture['codeBlocks']))
        ->and($payload->codeBlocks[0]['lang'])->toBe('php')
        ->and($payload->meta['event'])->toBe($fixture['meta']['event'])
        ->and($payload->meta['severity'])->toBe($fixture['meta']['severity'])
        ->and($payload->meta['class'])->toBe('RuntimeException');
});

// ---------------------------------------------------------------------------
// 2. GenericInfoEvent
// ---------------------------------------------------------------------------

it('GenericInfoEvent toPayload() matches fixture on stable properties', function () {
    $fixture = loadFixture('GenericInfoEvent');

    $event = new GenericInfoEvent(
        eventTitle: 'ℹ️ Background sync started',
        eventDescription: 'Nightly inventory sync kicked off.',
        eventFields: [['name' => '🔢 Records', 'value' => '12,481', 'inline' => true]],
        eventSeverity: 'info',
        links: ['dashboard' => 'https://admin.example.com/sync/42'],
        meta: ['trace' => 'TRACE002'],
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->title)->toBe($fixture['title'])
        ->and($payload->description)->toBe($fixture['description'])
        ->and($payload->severity)->toBe($fixture['severity'])
        ->and($payload->channel)->toBe($fixture['channel'])
        ->and($payload->fields)->toHaveCount(count($fixture['fields']))
        ->and($payload->fields[0]['name'])->toBe('🔢 Records')
        ->and($payload->meta['event'])->toBe($fixture['meta']['event'])
        ->and($payload->meta['severity'])->toBe('info');
});

// ---------------------------------------------------------------------------
// 3. AuditEvent
// ---------------------------------------------------------------------------

it('AuditEvent toPayload() matches fixture on stable properties', function () {
    $fixture = loadFixture('AuditEvent');

    $event = new AuditEvent(
        actor: 'admin@example.com',
        action: 'updated',
        target: 'User:99',
        before: ['role' => 'viewer'],
        after: ['role' => 'editor'],
        links: ['user' => 'https://admin.example.com/users/99'],
        meta: ['trace' => 'TRACE003'],
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->title)->toBe($fixture['title'])
        ->and($payload->description)->toBe($fixture['description'])
        ->and($payload->severity)->toBe($fixture['severity'])
        ->and($payload->channel)->toBe($fixture['channel'])
        // 3 domain fields + 1 link field
        ->and($payload->fields)->toHaveCount(count($fixture['fields']))
        // diff emitted because before !== after
        ->and($payload->codeBlocks)->toHaveCount(1)
        ->and($payload->codeBlocks[0]['name'])->toBe('🔄 Diff')
        ->and($payload->codeBlocks[0]['lang'])->toBe('json')
        ->and($payload->meta['actor'])->toBe('admin@example.com')
        ->and($payload->meta['action'])->toBe('updated');
});

// ---------------------------------------------------------------------------
// 4. DeploymentEvent
// ---------------------------------------------------------------------------

it('DeploymentEvent toPayload() matches fixture on stable properties', function () {
    $fixture = loadFixture('DeploymentEvent');

    $event = new DeploymentEvent(
        version: 'v1.4.2',
        env: 'production',
        commit: 'a3f8d2c1b4e5f6a7',
        branch: 'main',
        durationSeconds: 87,
        meta: ['trace' => 'TRACE004'],
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->title)->toBe($fixture['title'])
        ->and($payload->description)->toBe($fixture['description'])
        ->and($payload->severity)->toBe($fixture['severity'])
        ->and($payload->channel)->toBe($fixture['channel'])
        ->and($payload->fields)->toHaveCount(count($fixture['fields']))
        ->and($payload->meta['version'])->toBe('v1.4.2')
        ->and($payload->meta['env'])->toBe('production')
        ->and($payload->meta['commit_short'])->toBe('a3f8d2c');
});

// ---------------------------------------------------------------------------
// 5. CronHeartbeatEvent
// ---------------------------------------------------------------------------

it('CronHeartbeatEvent toPayload() matches fixture on stable properties', function () {
    $fixture = loadFixture('CronHeartbeatEvent');

    $lastRunAt = new DateTimeImmutable('2026-05-11T14:00:00+00:00');

    $event = new CronHeartbeatEvent(
        scheduleName: 'inventory:sync',
        lastRunAt: $lastRunAt,
        status: 'ok',
        durationSeconds: 23,
        meta: ['trace' => 'TRACE005'],
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->title)->toBe($fixture['title'])
        ->and($payload->description)->toBe($fixture['description'])
        ->and($payload->severity)->toBe($fixture['severity'])
        ->and($payload->channel)->toBe($fixture['channel'])
        ->and($payload->fields)->toHaveCount(count($fixture['fields']))
        ->and($payload->codeBlocks)->toBeEmpty()
        ->and($payload->meta['schedule'])->toBe('inventory:sync')
        ->and($payload->meta['status'])->toBe('ok');
});

// ---------------------------------------------------------------------------
// 6. JobRetryExhaustedEvent
// ---------------------------------------------------------------------------

it('JobRetryExhaustedEvent toPayload() matches fixture on stable properties', function () {
    $fixture = loadFixture('JobRetryExhaustedEvent');

    $exc = new RuntimeException('AMQP connection refused after 3 retries');

    $event = new JobRetryExhaustedEvent(
        jobClass: 'App\\Jobs\\SyncInventoryJob',
        payload: ['orderId' => 42, 'sku' => 'WIDGET-RED'],
        attempts: 3,
        lastException: $exc,
        meta: ['trace' => 'TRACE006', 'queue' => 'default'],
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->title)->toBe($fixture['title'])
        ->and($payload->description)->toBe($fixture['description'])
        ->and($payload->severity)->toBe($fixture['severity'])
        ->and($payload->channel)->toBe($fixture['channel'])
        // fields: Job + Attempts + Queue (from meta)
        ->and($payload->fields)->toHaveCount(count($fixture['fields']))
        // 2 codeBlocks: Source + Payload
        ->and($payload->codeBlocks)->toHaveCount(2)
        ->and($payload->codeBlocks[0]['name'])->toBe('📁 Source')
        ->and($payload->codeBlocks[1]['name'])->toBe('📦 Payload')
        ->and($payload->meta['job_class'])->toBe('App\\Jobs\\SyncInventoryJob')
        ->and($payload->meta['attempts'])->toBe(3);
});

// ---------------------------------------------------------------------------
// 7. ManualOperationEvent
// ---------------------------------------------------------------------------

it('ManualOperationEvent toPayload() matches fixture on stable properties', function () {
    $fixture = loadFixture('ManualOperationEvent');

    $event = new ManualOperationEvent(
        operator: 'admin@example.com',
        command: 'orders:reprocess-failed',
        args: ['--from' => '2026-05-01', '--limit' => 200],
        durationSeconds: 14,
        meta: ['trace' => 'TRACE007'],
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->title)->toBe($fixture['title'])
        ->and($payload->description)->toBe($fixture['description'])
        ->and($payload->severity)->toBe($fixture['severity'])
        ->and($payload->channel)->toBe($fixture['channel'])
        ->and($payload->fields)->toHaveCount(count($fixture['fields']))
        // args non-empty → 1 codeBlock
        ->and($payload->codeBlocks)->toHaveCount(1)
        ->and($payload->codeBlocks[0]['name'])->toBe('⚙️ Args')
        ->and($payload->codeBlocks[0]['lang'])->toBe('json')
        ->and($payload->meta['operator'])->toBe('admin@example.com')
        ->and($payload->meta['command'])->toBe('orders:reprocess-failed');
});
