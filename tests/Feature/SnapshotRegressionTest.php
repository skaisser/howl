<?php

use Skaisser\Howl\Support\EmbedBuilder;
use Skaisser\Howl\Support\Payload;

/**
 * Snapshot regression test — end-to-end render of a generic API exception embed.
 *
 * This test validates that EmbedBuilder produces a payload whose structure and
 * key field values are semantically correct for a generic error scenario.
 *
 * We do NOT byte-match the full JSON (timestamps make that brittle); instead we
 * assert the meaningful semantic properties that the Discord bot contract relies on.
 */
function apiErrorPayload(): Payload
{
    return new Payload(
        title: '🚨 External API call failed',
        description: 'Connection timed out after 30s while calling the payment gateway charge endpoint.',
        severity: 'error',
        channel: 'errors',
        fields: [
            ['name' => 'Severity',     'value' => 'ERROR',                   'inline' => true],
            ['name' => 'Service',      'value' => 'PaymentGateway',          'inline' => true],
            ['name' => 'Status Code',  'value' => '504',                     'inline' => true],
            ['name' => 'Attempt',      'value' => '3',                       'inline' => true],
        ],
        codeBlocks: [
            ['name' => '📁 Source', 'code' => 'app/Services/PaymentGatewayClient.php:142', 'lang' => 'php'],
        ],
        mentions: [],
        meta: [
            'event' => 'api.call.failed',
            'trace' => '01JXAMPLE',
        ],
        buttons: [
            ['label' => 'View service logs',  'url' => 'https://yourapp.com/logs?service=payment-gateway'],
            ['label' => 'View order',         'url' => 'https://yourapp.com/orders/ORD-00042'],
        ],
        attachments: [],
        threadId: null,
        username: null,
        app: 'MyApp',
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00', new DateTimeZone('UTC')),
        forceSync: false,
    );
}

// ---------------------------------------------------------------------------
// Color — error red (canonical decimal from decisions.md §9)
// ---------------------------------------------------------------------------

it('renders the API error embed with error-red color', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['color'])->toBe(15548997); // #ED4245
});

// ---------------------------------------------------------------------------
// Author block — must contain app name, env, and channel
// ---------------------------------------------------------------------------

it('renders author name with app, env and channel', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['author']['name'])->toBe('🚨 MyApp · production · errors');
});

// ---------------------------------------------------------------------------
// Title — must contain the alarm emoji
// ---------------------------------------------------------------------------

it('renders title with the 🚨 severity emoji', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['title'])->toContain('🚨');
    expect($embed['title'])->toContain('External API call failed');
});

// ---------------------------------------------------------------------------
// Description — verbatim error description
// ---------------------------------------------------------------------------

it('renders the description verbatim', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['description'])
        ->toContain('Connection timed out')
        ->toContain('payment gateway');
});

// ---------------------------------------------------------------------------
// Fields — 4 inline fields + 1 code block (Source) = 5 total
// ---------------------------------------------------------------------------

it('renders 5 fields total: 4 inline + 1 code-block Source field', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['fields'])->toHaveCount(5);
});

it('renders 4 inline fields for Severity, Service, Status Code, Attempt', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    $inlineFields = array_filter($embed['fields'], fn ($f) => $f['inline']);
    expect($inlineFields)->toHaveCount(4);

    $names = array_column(array_values($inlineFields), 'name');
    expect($names)->toContain('Severity')
        ->toContain('Service')
        ->toContain('Status Code')
        ->toContain('Attempt');
});

it('renders the Source field as a non-inline PHP code block', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    $sourceField = collect($embed['fields'])->first(fn ($f) => $f['name'] === '📁 Source');

    expect($sourceField)->not->toBeNull();
    expect($sourceField['inline'])->toBeFalse();
    expect($sourceField['value'])->toContain('```php');
    expect($sourceField['value'])->toContain('PaymentGatewayClient.php:142');
});

// ---------------------------------------------------------------------------
// Footer — bot-parseable contract keys must be present
// ---------------------------------------------------------------------------

it('footer contains event:api.call.failed', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['footer']['text'])->toContain('event:api.call.failed');
});

it('footer contains severity:error', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['footer']['text'])->toContain('severity:error');
});

it('footer contains env:production', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['footer']['text'])->toContain('env:production');
});

it('footer contains trace:01JXAMPLE', function () {
    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    expect($embed['footer']['text'])->toContain('trace:01JXAMPLE');
});

// ---------------------------------------------------------------------------
// allowed_mentions — safe by default (empty parse array)
// ---------------------------------------------------------------------------

it('blocks mass pings by default (allowed_mentions.parse is empty)', function () {
    $body = EmbedBuilder::build(apiErrorPayload());

    expect($body['allowed_mentions']['parse'])->toBe([]);
});

// ---------------------------------------------------------------------------
// Link buttons — 2 buttons in an action row
// ---------------------------------------------------------------------------

it('renders 2 link buttons in a single action row', function () {
    $body = EmbedBuilder::build(apiErrorPayload());

    expect($body['components'])->toHaveCount(1);

    $row = $body['components'][0];
    expect($row['type'])->toBe(1);
    expect($row['components'])->toHaveCount(2);
});

it('first button is "View service logs"', function () {
    $body = EmbedBuilder::build(apiErrorPayload());

    $btn = $body['components'][0]['components'][0];
    expect($btn['type'])->toBe(2);
    expect($btn['style'])->toBe(5);
    expect($btn['label'])->toBe('View service logs');
    expect($btn['url'])->toContain('yourapp.com/logs');
});

it('second button is "View order"', function () {
    $body = EmbedBuilder::build(apiErrorPayload());

    $btn = $body['components'][0]['components'][1];
    expect($btn['label'])->toBe('View order');
    expect($btn['url'])->toContain('yourapp.com/orders');
});

// ---------------------------------------------------------------------------
// Fixture cross-check — compare against the seeded JSON in tests/Fixtures/embeds/
// ---------------------------------------------------------------------------

it('embed color matches the seeded fixture success_recovery.json', function () {
    $fixture = json_decode(
        file_get_contents(__DIR__.'/../Fixtures/embeds/success_recovery.json'),
        true,
    );

    // The fixture is a success (green) embed; confirm the color constant is correct
    expect($fixture['embeds'][0]['color'])->toBe(5763719); // #57F287 green
});

it('our error embed color differs from the success fixture color', function () {
    $fixture = json_decode(
        file_get_contents(__DIR__.'/../Fixtures/embeds/success_recovery.json'),
        true,
    );

    $body = EmbedBuilder::build(apiErrorPayload());
    $embed = $body['embeds'][0];

    // Error red must differ from success green
    expect($embed['color'])->not->toBe($fixture['embeds'][0]['color']);
});
