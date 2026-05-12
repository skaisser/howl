<?php

declare(strict_types=1);

test('all event classes extend HowlEvent')
    ->expect('Skaisser\Howl\Events')
    ->classes()
    ->toExtend('Skaisser\Howl\Events\HowlEvent')
    ->ignoring('Skaisser\Howl\Events\HowlEvent');

test('all driver classes implement Driver contract')
    ->expect('Skaisser\Howl\Drivers')
    ->classes()
    ->toImplement('Skaisser\Howl\Contracts\Driver');

test('no debug calls leaked into src/')
    ->expect(['dd', 'dump', 'die', 'var_dump', 'print_r'])
    ->not->toBeUsed();

test('Payload is final readonly')
    ->expect('Skaisser\Howl\Support\Payload')
    ->toBeFinal()
    ->toBeReadonly();

test('contracts namespace has only interfaces')
    ->expect('Skaisser\Howl\Contracts')
    ->toBeInterfaces();
