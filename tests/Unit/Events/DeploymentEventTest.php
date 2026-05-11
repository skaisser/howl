<?php

use Skaisser\Howl\Events\DeploymentEvent;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// 1. emoji
// ---------------------------------------------------------------------------

it('emoji() returns 🚀', function () {
    $event = new DeploymentEvent('v1.2.3', 'production', 'abc1234');

    expect($event->emoji())->toBe('🚀');
});

// ---------------------------------------------------------------------------
// 2. severity / channel
// ---------------------------------------------------------------------------

it('severity() returns deployment', function () {
    $event = new DeploymentEvent('v1.0.0', 'staging', 'abc');

    expect($event->severity())->toBe('deployment');
});

it('channel() returns deployments', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc');

    expect($event->channel())->toBe('deployments');
});

// ---------------------------------------------------------------------------
// 3. v0.2.0 constructor order (BREAKING CHANGE from v0.1.0)
//    v0.1.0: (version, commit, env, ...)
//    v0.2.0: (version, env, commit, ...)
// ---------------------------------------------------------------------------

it('v0.2.0 positional constructor has env SECOND, commit THIRD', function () {
    // Positional: version='1.0.0', env='production', commit='abc1234'
    $event = new DeploymentEvent('1.0.0', 'production', 'abc1234');

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($event->title())->toContain('production')
        ->and($fieldValues['🔗 Commit'])->toBe('abc1234');
});

it('v0.2.0 named-arg constructor works correctly', function () {
    $event = new DeploymentEvent(version: '1.0.0', env: 'production', commit: 'abc1234');

    expect($event->severity())->toBe('deployment')
        ->and($event->channel())->toBe('deployments')
        ->and($event->title())->toContain('1.0.0')
        ->and($event->title())->toContain('production');
});

// ---------------------------------------------------------------------------
// 4. title
// ---------------------------------------------------------------------------

it('title() includes version and env', function () {
    $event = new DeploymentEvent('v2.0.0', 'production', 'deadbeef');

    expect($event->title())
        ->toContain('🚀')
        ->toContain('v2.0.0')
        ->toContain('production');
});

// ---------------------------------------------------------------------------
// 5. description
// ---------------------------------------------------------------------------

it('description() contains the version and branch (defaults to main)', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc1234');

    expect($event->description())
        ->toContain('v1.0.0')
        ->toContain('main');
});

it('description() uses provided branch name', function () {
    $event = new DeploymentEvent('v1.0.0', 'staging', 'abc1234', 'feature/awesome');

    expect($event->description())->toContain('feature/awesome');
});

// ---------------------------------------------------------------------------
// 6. fields
// ---------------------------------------------------------------------------

it('fields() contains Version, Env, and Commit (short SHA)', function () {
    $event = new DeploymentEvent('v1.4.2', 'staging', 'cafe1234567890');

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['🚀 Version'])->toBe('v1.4.2')
        ->and($fieldValues['🌐 Env'])->toBe('staging')
        ->and($fieldValues['🔗 Commit'])->toBe('cafe123');   // 7-char short SHA
});

it('fields() includes Branch when provided', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc', 'main');

    $fieldNames = array_column($event->fields(), 'name');

    expect($fieldNames)->toContain('🌿 Branch');
});

it('fields() omits Branch when not provided', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc');

    $fieldNames = array_column($event->fields(), 'name');

    expect($fieldNames)->not->toContain('🌿 Branch');
});

it('fields() includes Duration when durationSeconds provided', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc', null, 120);

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['⏱️ Duration'])->toBe('2m');
});

it('fields() formats sub-minute duration as seconds', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc', null, 42);

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['⏱️ Duration'])->toBe('42s');
});

it('fields() formats mixed minute+second duration correctly', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc', null, 65);

    $fieldValues = array_combine(
        array_column($event->fields(), 'name'),
        array_column($event->fields(), 'value'),
    );

    expect($fieldValues['⏱️ Duration'])->toBe('1m 5s');
});

it('fields() omits Duration when not provided', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc');

    $fieldNames = array_column($event->fields(), 'name');

    expect($fieldNames)->not->toContain('⏱️ Duration');
});

// ---------------------------------------------------------------------------
// 7. codeBlocks — always empty
// ---------------------------------------------------------------------------

it('codeBlocks() returns empty array', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc1234');

    expect($event->codeBlocks())->toBe([]);
});

// ---------------------------------------------------------------------------
// 8. footerMeta
// ---------------------------------------------------------------------------

it('footerMeta() contains version, env and commit_short keys', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc1234567890');

    $footer = $event->footerMeta();

    expect($footer)->toHaveKeys(['version', 'env', 'commit_short'])
        ->and($footer['version'])->toBe('v1.0.0')
        ->and($footer['env'])->toBe('production')
        ->and($footer['commit_short'])->toBe('abc1234');
});

// ---------------------------------------------------------------------------
// 9. links rendered as fields
// ---------------------------------------------------------------------------

it('toPayload() appends link fields from $links', function () {
    $event = new DeploymentEvent(
        'v1.0.0', 'production', 'abc', null, null,
        ['order' => 'https://example.com/orders/1'],
    );

    $fieldNames = array_column($event->toPayload()->fields, 'name');

    expect($fieldNames)->toContain('📦 Order');
});

// ---------------------------------------------------------------------------
// 10. end-to-end toPayload()
// ---------------------------------------------------------------------------

it('toPayload() returns a Payload with severity=deployment and correct structure', function () {
    $event = new DeploymentEvent('v2.0.0', 'production', 'deadbeef');

    $payload = $event->toPayload();

    expect($payload)->toBeInstanceOf(Payload::class)
        ->and($payload->severity)->toBe('deployment')
        ->and($payload->title)->toContain('v2.0.0')
        ->and($payload->title)->toContain('production')
        ->and($payload->codeBlocks)->toBe([])
        ->and($payload->meta)->toHaveKey('version');
});

it('toPayload() meta includes base footer keys and subclass keys', function () {
    $event = new DeploymentEvent('v1.0.0', 'production', 'abc1234');

    $meta = $event->toPayload()->meta;

    expect($meta)->toHaveKeys(['event', 'severity', 'env', 'trace', 'timestamp', 'version', 'env', 'commit_short']);
});
