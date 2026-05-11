<?php

use Skaisser\Howl\Drivers\NullDriver;
use Skaisser\Howl\Support\Payload;

function makePayload(string $severity = 'info', string $title = 'Test'): Payload
{
    return new Payload(
        title: $title,
        description: null,
        severity: $severity,
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
}

it('has name null', function () {
    expect((new NullDriver)->name())->toBe('null');
});

it('always returns true', function () {
    $driver = new NullDriver;
    $result = $driver->send(makePayload());
    expect($result)->toBeTrue();
});

it('records each sent payload', function () {
    $driver = new NullDriver;

    $p1 = makePayload('error', 'Error one');
    $p2 = makePayload('info', 'Info two');

    $driver->send($p1);
    $driver->send($p2);

    expect($driver->sent)->toHaveCount(2)
        ->and($driver->sent[0])->toBe($p1)
        ->and($driver->sent[1])->toBe($p2);
});

it('last() returns the most recently sent payload', function () {
    $driver = new NullDriver;
    expect($driver->last())->toBeNull();

    $p = makePayload('warning', 'Last payload');
    $driver->send($p);

    expect($driver->last())->toBe($p);
});

it('reset() clears all recorded payloads', function () {
    $driver = new NullDriver;
    $driver->send(makePayload());
    $driver->reset();

    expect($driver->sent)->toBeEmpty()
        ->and($driver->last())->toBeNull();
});
