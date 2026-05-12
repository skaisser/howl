# Architecture Tests

Howl ships a `tests/Architecture/PackageStructureTest.php` file that enforces structural invariants across the `src/` namespace using Pest's `arch()` feature. These tests run in CI on every push and ensure the package never regresses on its internal contracts.

## Built-in rules

### Events extend HowlEvent

All classes in `Skaisser\Howl\Events` must extend `HowlEvent` (the abstract base is itself excluded from the check).

```php
test('all event classes extend HowlEvent')
    ->expect('Skaisser\Howl\Events')
    ->classes()
    ->toExtend('Skaisser\Howl\Events\HowlEvent')
    ->ignoring('Skaisser\Howl\Events\HowlEvent');
```

This catches a custom event accidentally extending the wrong base class.

### Drivers implement the Driver contract

All classes in `Skaisser\Howl\Drivers` must implement `Skaisser\Howl\Contracts\Driver`.

```php
test('all driver classes implement Driver contract')
    ->expect('Skaisser\Howl\Drivers')
    ->classes()
    ->toImplement('Skaisser\Howl\Contracts\Driver');
```

### No debug calls in src/

`dd()`, `dump()`, `die()`, `var_dump()`, and `print_r()` must not appear anywhere in source.

```php
test('no debug calls leaked into src/')
    ->expect(['dd', 'dump', 'die', 'var_dump', 'print_r'])
    ->not->toBeUsed();
```

### Payload is final and readonly

`Skaisser\Howl\Support\Payload` must be declared `final readonly` — this enforces the immutable value-object pattern that the builder relies on.

```php
test('Payload is final readonly')
    ->expect('Skaisser\Howl\Support\Payload')
    ->toBeFinal()
    ->toBeReadonly();
```

### Contracts namespace contains only interfaces

```php
test('contracts namespace has only interfaces')
    ->expect('Skaisser\Howl\Contracts')
    ->toBeInterfaces();
```

## Extending the architecture tests

Add your own rules in `tests/Architecture/PackageStructureTest.php`. Pest's `arch()` supports a wide range of assertions. Useful additions for Howl consumers:

### Enforce your own event namespace

If you keep application events in `App\Howl`, ensure they all extend `HowlEvent`:

```php
test('app howl events extend HowlEvent')
    ->expect('App\Howl')
    ->classes()
    ->toExtend('Skaisser\Howl\Events\HowlEvent');
```

### Restrict driver access to service classes

Prevent driver classes from being instantiated directly outside the Howl dispatcher:

```php
test('drivers are not used directly outside Howl core')
    ->expect('Skaisser\Howl\Drivers')
    ->not->toBeUsed()
    ->ignoring(['Skaisser\Howl\Howl', 'Skaisser\Howl\Jobs\SendHowlJob']);
```

### No HTTP calls in event classes

Events are value objects — they should never reach out to the network:

```php
test('event classes do not make HTTP calls')
    ->expect('Skaisser\Howl\Events')
    ->not->toUse('Illuminate\Support\Facades\Http');
```

## CI matrix

The architecture tests run as part of the standard Pest suite on every matrix combination (PHP 8.3/8.4 × Laravel 12/13). They add virtually no overhead since they are static reflection checks, not runtime tests.

```bash
./vendor/bin/pest tests/Architecture/
```
