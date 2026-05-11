<?php

use Skaisser\Howl\Support\EmbedBuilder;
use Skaisser\Howl\Support\Payload;

function embedPayload(array $overrides = []): Payload
{
    return new Payload(
        title: $overrides['title'] ?? 'Test notification',
        description: $overrides['description'] ?? null,
        severity: $overrides['severity'] ?? 'info',
        channel: $overrides['channel'] ?? 'errors',
        fields: $overrides['fields'] ?? [],
        codeBlocks: $overrides['codeBlocks'] ?? [],
        mentions: $overrides['mentions'] ?? [],
        meta: $overrides['meta'] ?? [],
        buttons: $overrides['buttons'] ?? [],
        attachments: $overrides['attachments'] ?? [],
        threadId: $overrides['threadId'] ?? null,
        username: $overrides['username'] ?? null,
        app: $overrides['app'] ?? 'MyApp',
        env: $overrides['env'] ?? 'production',
        timestamp: $overrides['timestamp'] ?? new DateTimeImmutable('2026-05-11 06:57:00'),
        forceSync: $overrides['forceSync'] ?? false,
    );
}

// --- Severity × color + emoji dataset ---

dataset('severities', [
    'error' => ['error',      15548997, '🚨'],
    'warning' => ['warning',    16765440, '🟡'],
    'info' => ['info',       3447003,  'ℹ️'],
    'success' => ['success',    5763719,  '✅'],
    'audit' => ['audit',      10181046, '🔒'],
    'deployment' => ['deployment', 1752220,  '🚀'],
]);

it('produces correct color for severity', function (string $severity, int $color, string $emoji) {
    $payload = embedPayload(['severity' => $severity]);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['color'])->toBe($color);
})->with('severities');

it('produces correct emoji prefix in title for severity', function (string $severity, int $color, string $emoji) {
    $payload = embedPayload(['severity' => $severity, 'title' => 'Some event']);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['title'])->toStartWith($emoji);
})->with('severities');

it('includes author block with severity emoji and app/env/channel', function () {
    $payload = embedPayload(['severity' => 'error', 'channel' => 'errors']);
    $body = EmbedBuilder::build($payload);

    $author = $body['embeds'][0]['author']['name'];
    expect($author)->toContain('🚨')
        ->and($author)->toContain('MyApp')
        ->and($author)->toContain('production')
        ->and($author)->toContain('errors');
});

it('renders inline fields with inline:true', function () {
    $payload = embedPayload([
        'fields' => [
            ['name' => 'Shipment', 'value' => '47002638038', 'inline' => true],
        ],
    ]);

    $body = EmbedBuilder::build($payload);
    $field = $body['embeds'][0]['fields'][0];

    expect($field['inline'])->toBeTrue()
        ->and($field['name'])->toBe('Shipment')
        ->and($field['value'])->toBe('47002638038');
});

it('renders non-inline fields with inline:false', function () {
    $payload = embedPayload([
        'fields' => [
            ['name' => 'Source', 'value' => 'file.php:99', 'inline' => false],
        ],
    ]);

    $body = EmbedBuilder::build($payload);
    $field = $body['embeds'][0]['fields'][0];

    expect($field['inline'])->toBeFalse();
});

it('renders code blocks as non-inline fields with fenced syntax', function () {
    $payload = embedPayload([
        'codeBlocks' => [
            ['name' => 'Source', 'code' => 'return null;', 'lang' => 'php'],
        ],
    ]);

    $body = EmbedBuilder::build($payload);
    $field = $body['embeds'][0]['fields'][0];

    expect($field['name'])->toBe('Source')
        ->and($field['value'])->toContain('```php')
        ->and($field['value'])->toContain('return null;')
        ->and($field['inline'])->toBeFalse();
});

it('includes footer text', function () {
    $payload = embedPayload(['meta' => ['event' => 'test.event']]);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['footer']['text'])->toContain('severity:info');
});

it('includes timestamp in ISO 8601 format at embed level', function () {
    $ts = new DateTimeImmutable('2026-05-11 06:57:00', new DateTimeZone('UTC'));
    $payload = embedPayload(['timestamp' => $ts]);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['timestamp'])->toContain('2026-05-11');
});

// --- allowed_mentions safety tests ---

it('blocks @everyone by default (empty parse array)', function () {
    $payload = embedPayload(['mentions' => []]);
    $body = EmbedBuilder::build($payload);

    expect($body['allowed_mentions']['parse'])->toBeEmpty()
        ->and($body['allowed_mentions']['users'])->toBeEmpty()
        ->and($body['allowed_mentions']['roles'])->toBeEmpty();
});

it('allows @everyone only when explicitly mentioned', function () {
    $payload = embedPayload(['mentions' => [['type' => 'everyone', 'id' => '']]]);
    $body = EmbedBuilder::build($payload);

    expect($body['allowed_mentions']['parse'])->toContain('everyone');
});

it('allows @here only when explicitly mentioned', function () {
    $payload = embedPayload(['mentions' => [['type' => 'here', 'id' => '']]]);
    $body = EmbedBuilder::build($payload);

    expect($body['allowed_mentions']['parse'])->toContain('here');
});

it('includes user ID in allowed_mentions users array', function () {
    $payload = embedPayload(['mentions' => [['type' => 'user', 'id' => '111222333']]]);
    $body = EmbedBuilder::build($payload);

    expect($body['allowed_mentions']['users'])->toContain('111222333')
        ->and($body['allowed_mentions']['parse'])->toBeEmpty();
});

it('includes role ID in allowed_mentions roles array', function () {
    $payload = embedPayload(['mentions' => [['type' => 'role', 'id' => '444555666']]]);
    $body = EmbedBuilder::build($payload);

    expect($body['allowed_mentions']['roles'])->toContain('444555666')
        ->and($body['allowed_mentions']['parse'])->toBeEmpty();
});

it('renders link buttons as action row components', function () {
    $payload = embedPayload([
        'buttons' => [
            ['label' => 'View Admin', 'url' => 'https://example.com/admin'],
        ],
    ]);

    $body = EmbedBuilder::build($payload);

    expect($body['components'])->toHaveCount(1);
    $row = $body['components'][0];
    expect($row['type'])->toBe(1);
    $btn = $row['components'][0];
    expect($btn['type'])->toBe(2)
        ->and($btn['style'])->toBe(5)
        ->and($btn['label'])->toBe('View Admin')
        ->and($btn['url'])->toBe('https://example.com/admin');
});

it('omits components key when no buttons', function () {
    $payload = embedPayload(['buttons' => []]);
    $body = EmbedBuilder::build($payload);

    expect($body)->not->toHaveKey('components');
});

it('uses per-call username override', function () {
    $payload = embedPayload(['username' => 'Custom Bot Name']);
    $body = EmbedBuilder::build($payload);

    expect($body['username'])->toBe('Custom Bot Name');
});

it('generates content string with mention text', function () {
    $payload = embedPayload([
        'mentions' => [['type' => 'user', 'id' => '987654321']],
    ]);

    $body = EmbedBuilder::build($payload);

    expect($body['content'])->toBe('<@987654321>');
});
