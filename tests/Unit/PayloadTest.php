<?php

use Skaisser\Howl\Support\Payload;

it('holds all fields as readonly properties', function () {
    $ts = new DateTimeImmutable('2026-05-11 06:57:00');

    $payload = new Payload(
        title: 'Test error',
        description: 'Something went wrong',
        severity: 'error',
        channel: 'errors',
        fields: [['name' => 'Field', 'value' => 'Value', 'inline' => true]],
        codeBlocks: [['name' => 'Source', 'code' => 'line 1', 'lang' => 'php']],
        mentions: [['type' => 'user', 'id' => '123456']],
        meta: ['trace' => 'abc123'],
        buttons: [['label' => 'View', 'url' => 'https://example.com']],
        attachments: ['/tmp/log.txt'],
        threadId: '9876',
        username: 'Custom Bot',
        app: 'MyApp',
        env: 'production',
        timestamp: $ts,
        forceSync: true,
    );

    expect($payload->title)->toBe('Test error')
        ->and($payload->description)->toBe('Something went wrong')
        ->and($payload->severity)->toBe('error')
        ->and($payload->channel)->toBe('errors')
        ->and($payload->fields)->toBe([['name' => 'Field', 'value' => 'Value', 'inline' => true]])
        ->and($payload->codeBlocks)->toBe([['name' => 'Source', 'code' => 'line 1', 'lang' => 'php']])
        ->and($payload->mentions)->toBe([['type' => 'user', 'id' => '123456']])
        ->and($payload->meta)->toBe(['trace' => 'abc123'])
        ->and($payload->buttons)->toBe([['label' => 'View', 'url' => 'https://example.com']])
        ->and($payload->attachments)->toBe(['/tmp/log.txt'])
        ->and($payload->threadId)->toBe('9876')
        ->and($payload->username)->toBe('Custom Bot')
        ->and($payload->app)->toBe('MyApp')
        ->and($payload->env)->toBe('production')
        ->and($payload->timestamp)->toBe($ts)
        ->and($payload->forceSync)->toBeTrue();
});

it('is immutable — readonly class cannot be modified', function () {
    $payload = new Payload(
        title: 'Original',
        description: null,
        severity: 'info',
        channel: null,
        fields: [],
        codeBlocks: [],
        mentions: [],
        meta: [],
        buttons: [],
        attachments: [],
        threadId: null,
        username: null,
        app: null,
        env: null,
        timestamp: null,
        forceSync: false,
    );

    expect(fn () => $payload->title = 'Modified')
        ->toThrow(Error::class);
});

it('accepts null optional fields', function () {
    $payload = new Payload(
        title: 'Minimal',
        description: null,
        severity: 'info',
        channel: null,
        fields: [],
        codeBlocks: [],
        mentions: [],
        meta: [],
        buttons: [],
        attachments: [],
        threadId: null,
        username: null,
        app: null,
        env: null,
        timestamp: null,
        forceSync: false,
    );

    expect($payload->description)->toBeNull()
        ->and($payload->channel)->toBeNull()
        ->and($payload->threadId)->toBeNull()
        ->and($payload->timestamp)->toBeNull()
        ->and($payload->forceSync)->toBeFalse();
});
