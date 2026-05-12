<?php

use Illuminate\Database\Eloquent\Model;
use Skaisser\Howl\Events\AuditEvent;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// 1. emoji
// ---------------------------------------------------------------------------

it('emoji() returns 🔒', function () {
    $event = new AuditEvent('admin@example.com', 'deleted', 'User#42');

    expect($event->emoji())->toBe('🔒');
});

// ---------------------------------------------------------------------------
// 2. severity / channel
// ---------------------------------------------------------------------------

it('severity() returns audit', function () {
    $event = new AuditEvent('admin', 'updated', 'Order');

    expect($event->severity())->toBe('audit');
});

it('channel() returns audit', function () {
    $event = new AuditEvent('admin', 'updated', 'Order');

    expect($event->channel())->toBe('audit');
});

// ---------------------------------------------------------------------------
// 3. title
// ---------------------------------------------------------------------------

it('title() contains the action', function () {
    $event = new AuditEvent('admin@example.com', 'deleted', 'User#42');

    expect($event->title())->toBe('Audit: deleted');
});

// ---------------------------------------------------------------------------
// 4. description — scalar target
// ---------------------------------------------------------------------------

it('description() includes actor, action and scalar target', function () {
    $event = new AuditEvent('carlos@example.com', 'granted', 'Role: Admin');

    expect($event->description())
        ->toContain('carlos@example.com')
        ->toContain('granted')
        ->toContain('Role: Admin');
});

// ---------------------------------------------------------------------------
// 5. description — Eloquent-like target (object with getKey / class_basename)
// ---------------------------------------------------------------------------

it('description() uses ClassName:id for Eloquent-like target', function () {
    // Fake an Eloquent model — extend the real base so class_basename and getKey work.
    $model = new class extends Model
    {
        protected $table = 'orders';

        public function getKey(): mixed
        {
            return 99;
        }
    };

    $event = new AuditEvent('admin', 'viewed', $model);

    expect($event->description())->toContain(':99');
});

// ---------------------------------------------------------------------------
// 6. description — array target
// ---------------------------------------------------------------------------

it('description() JSON-encodes an array target', function () {
    $event = new AuditEvent('admin', 'synced', ['ids' => [1, 2, 3]]);

    expect($event->description())->toContain('ids');
});

// ---------------------------------------------------------------------------
// 7. fields
// ---------------------------------------------------------------------------

it('fields() returns Actor, Action and Target with correct values', function () {
    $event = new AuditEvent('carlos@example.com', 'granted', 'Role: Admin');

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['👤 Actor'])->toBe('carlos@example.com')
        ->and($fieldValues['⚡ Action'])->toBe('granted')
        ->and($fieldValues['🎯 Target'])->toBe('Role: Admin');
});

it('fields() entries are all inline', function () {
    $event = new AuditEvent('admin', 'updated', 'Config');

    foreach ($event->fields() as $field) {
        expect($field['inline'])->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// 8. codeBlocks — diff emitted when before !== after
// ---------------------------------------------------------------------------

it('codeBlocks() emits a Diff block when before and after differ', function () {
    $event = new AuditEvent('user', 'updated', 'Config', ['debug' => false], ['debug' => true]);

    $blocks = $event->codeBlocks();

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['name'])->toBe('🔄 Diff')
        ->and($blocks[0]['lang'])->toBe('json')
        ->and($blocks[0]['code'])->toContain('"before"')
        ->and($blocks[0]['code'])->toContain('"after"');
});

it('codeBlocks() returns empty when before and after are identical', function () {
    $event = new AuditEvent('user', 'viewed', 'Record', ['key' => 'val'], ['key' => 'val']);

    expect($event->codeBlocks())->toBe([]);
});

it('codeBlocks() returns empty when both before and after are empty arrays', function () {
    $event = new AuditEvent('user', 'deleted', 'Record');

    expect($event->codeBlocks())->toBe([]);
});

// ---------------------------------------------------------------------------
// 9. footerMeta
// ---------------------------------------------------------------------------

it('footerMeta() contains actor, action and target_class keys', function () {
    $event = new AuditEvent('admin@example.com', 'updated', 'Product#7');

    $footer = $event->footerMeta();

    expect($footer)->toHaveKeys(['actor', 'action', 'target_class'])
        ->and($footer['actor'])->toBe('admin@example.com')
        ->and($footer['action'])->toBe('updated')
        ->and($footer['target_class'])->toBe('string');
});

it('footerMeta() target_class is the FQCN for object targets', function () {
    $target = new stdClass;
    $event = new AuditEvent('admin', 'viewed', $target);

    expect($event->footerMeta()['target_class'])->toBe('stdClass');
});

// ---------------------------------------------------------------------------
// 10. links rendered as fields
// ---------------------------------------------------------------------------

it('toPayload() appends link fields from $links', function () {
    $event = new AuditEvent(
        'admin', 'updated', 'Order',
        [], [],
        ['order' => 'https://example.com/orders/1'],
    );

    $payload = $event->toPayload();
    $fieldNames = array_column($payload->fields, 'name');

    expect($fieldNames)->toContain('📦 Order');
});

// ---------------------------------------------------------------------------
// 11. end-to-end toPayload()
// ---------------------------------------------------------------------------

it('toPayload() returns a Payload with severity=audit, correct title, 3 fields, and diff codeBlock', function () {
    $event = new AuditEvent(
        'admin@example.com',
        'updated',
        'Product#7',
        ['price' => 100],
        ['price' => 120],
    );

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->severity)->toBe('audit')
        ->and($payload->title)->toBe('Audit: updated')
        ->and($payload->fields)->toHaveCount(3)   // Actor + Action + Target (no links)
        ->and($payload->codeBlocks)->toHaveCount(1)
        ->and($payload->meta)->toHaveKey('actor');
});

it('toPayload() meta includes base footer keys and subclass keys', function () {
    $event = new AuditEvent('user', 'deleted', 'Record');

    $meta = $event->toPayload()->meta;

    expect($meta)->toHaveKeys(['event', 'severity', 'env', 'trace', 'timestamp', 'actor', 'action', 'target_class']);
});
