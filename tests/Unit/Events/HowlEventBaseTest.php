<?php

use Skaisser\Howl\Events\HowlEvent;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Minimal anonymous-class stub used by all tests in this file.
//
// Satisfies the 8-method contract with predictable return values so that
// the final helpers (renderLinks, baseFooterMeta, toPayload) can be tested
// in isolation without touching any v0.1.0 concrete subclass.
// ---------------------------------------------------------------------------

/**
 * Build a minimal HowlEvent stub with optional constructor overrides.
 *
 * @param  array  $links  Passed to the universal constructor.
 * @param  array  $meta  Passed to the universal constructor.
 * @param  array  $fields  Returned from fields().
 * @param  array  $footer  Returned from footerMeta() — subclass override.
 */
function makeStubEvent(
    array $links = [],
    array $meta = [],
    array $fields = [],
    array $footer = [],
): HowlEvent {
    return new class($links, $meta, $fields, $footer) extends HowlEvent
    {
        public function __construct(
            array $links,
            array $meta,
            private readonly array $stubFields,
            private readonly array $stubFooter,
        ) {
            parent::__construct($links, $meta);
        }

        public function severity(): string
        {
            return 'info';
        }

        public function title(): string
        {
            return '🧪 Stub Event';
        }

        public function description(): string
        {
            return 'Stub description.';
        }

        public function fields(): array
        {
            return $this->stubFields;
        }

        public function emoji(): string
        {
            return '🧪';
        }

        public function footerMeta(): array
        {
            return $this->stubFooter;
        }
    };
}

// ---------------------------------------------------------------------------
// 1. renderLinks() — bare URL string produces [text](url) Discord markdown
// ---------------------------------------------------------------------------

it('renderLinks() produces correct markdown field for a bare URL string', function () {
    $event = makeStubEvent(links: [
        'producer' => 'https://example.com/admin/producers/42',
    ]);

    $linkFields = $event->renderLinks();

    expect($linkFields)->toHaveCount(1)
        ->and($linkFields[0]['name'])->toBe('🏢 Producer')
        ->and($linkFields[0]['value'])->toBe('[Producer](https://example.com/admin/producers/42)')
        ->and($linkFields[0]['inline'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// 2. renderLinks() — override map honors custom text label
// ---------------------------------------------------------------------------

it('renderLinks() honors [text, url] override map form', function () {
    $event = makeStubEvent(links: [
        'producer' => ['text' => 'Acme Corp', 'url' => 'https://example.com/admin/producers/99'],
    ]);

    $linkFields = $event->renderLinks();

    expect($linkFields)->toHaveCount(1)
        ->and($linkFields[0]['name'])->toBe('🏢 Producer')
        ->and($linkFields[0]['value'])->toBe('[Acme Corp](https://example.com/admin/producers/99)')
        ->and($linkFields[0]['inline'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// 3. renderLinks() — null / empty URL is skipped entirely
// ---------------------------------------------------------------------------

it('renderLinks() skips entries with null or empty URL', function () {
    $event = makeStubEvent(links: [
        'producer' => null,
        'order' => '',
        'payment' => 'https://example.com/payments/7',
    ]);

    $linkFields = $event->renderLinks();

    // Only the payment link with a valid URL should appear
    expect($linkFields)->toHaveCount(1)
        ->and($linkFields[0]['name'])->toBe('💳 Payment');
});

// ---------------------------------------------------------------------------
// 4. baseFooterMeta() — all 5 keys present with expected formats
// ---------------------------------------------------------------------------

it('baseFooterMeta() includes all 5 keys with expected formats', function () {
    // Use a named anonymous subclass in a closure via makeStubEvent
    $event = makeStubEvent();

    // baseFooterMeta() is final protected — invoke via Reflection
    $ref = new ReflectionMethod($event, 'baseFooterMeta');
    $ref->setAccessible(true);
    $meta = $ref->invoke($event);

    // event key: snake_case without 'Event' suffix
    expect($meta)->toHaveKeys(['event', 'severity', 'env', 'trace', 'timestamp'])
        ->and($meta['severity'])->toBe('info')
        ->and($meta['trace'])->toBeString()->toHaveLength(8)
        ->and($meta['timestamp'])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/');
});

// ---------------------------------------------------------------------------
// 5. baseFooterMeta() — honors caller-supplied trace in $meta
// ---------------------------------------------------------------------------

it('baseFooterMeta() uses caller-supplied trace from $meta constructor arg', function () {
    $event = makeStubEvent(meta: ['trace' => 'MY-TRACE-ID']);

    $ref = new ReflectionMethod($event, 'baseFooterMeta');
    $ref->setAccessible(true);
    $meta = $ref->invoke($event);

    expect($meta['trace'])->toBe('MY-TRACE-ID');
});

// ---------------------------------------------------------------------------
// 6. toPayload() — footerMeta() merges on top of baseFooterMeta() (subclass wins)
// ---------------------------------------------------------------------------

it('toPayload() merges baseFooterMeta and footerMeta with subclass winning on collision', function () {
    // The stub's footerMeta() returns a 'severity' key that should override the base
    $event = makeStubEvent(footer: [
        'severity' => 'OVERRIDDEN',
        'custom_key' => 'custom_value',
    ]);

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        // subclass 'severity' wins over base 'info'
        ->and($payload->meta['severity'])->toBe('OVERRIDDEN')
        // base keys still present
        ->and($payload->meta)->toHaveKey('event')
        ->and($payload->meta)->toHaveKey('trace')
        ->and($payload->meta)->toHaveKey('timestamp')
        // subclass-only key is merged in
        ->and($payload->meta['custom_key'])->toBe('custom_value');
});

// ---------------------------------------------------------------------------
// 7. toPayload() — renderLinks() fields are appended AFTER subclass fields()
// ---------------------------------------------------------------------------

it('toPayload() appends renderLinks() fields after subclass fields()', function () {
    $subclassFields = [
        ['name' => '📊 Status', 'value' => 'ok', 'inline' => true],
    ];

    $event = makeStubEvent(
        links: ['producer' => 'https://example.com/admin/producers/1'],
        fields: $subclassFields,
    );

    $payload = $event->toPayload();

    expect($payload->fields)->toHaveCount(2)
        // First field is from fields()
        ->and($payload->fields[0]['name'])->toBe('📊 Status')
        // Second field is the renderLinks() producer link
        ->and($payload->fields[1]['name'])->toBe('🏢 Producer')
        ->and($payload->fields[1]['value'])->toBe('[Producer](https://example.com/admin/producers/1)');
});
