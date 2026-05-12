# HowlFake

Howl ships a first-class testing helper — `HowlFake` — that captures dispatched payloads without making real HTTP requests to Discord, Slack, or Telegram. Use it in Pest/PHPUnit tests to assert the right notifications were sent, on the right channel, via the right driver.

## Setup

Call `Howl::fake()` at the top of a test. It swaps the IoC binding for the current test, returns the `HowlFake` instance, and clears the facade cache so all subsequent `Howl::*()` calls are intercepted.

```php
use Skaisser\Howl\Facades\Howl;
use Skaisser\Howl\Testing\HowlFake;

it('sends an error notification', function () {
    $fake = Howl::fake();

    Howl::error('Something broke');

    $fake->assertSent(fn ($payload) => $payload->severity === 'error');
});
```

> **Note:** `HowlFake` intentionally skips the `skip_environments` check so tests always capture payloads, even when `APP_ENV=testing` is in the `skip_environments` config list.

## Assertions

### `assertSent(callable $callback): void`

Asserts that at least one dispatched payload satisfies the given callback.

```php
$fake->assertSent(function ($payload) {
    return $payload->title === 'Deploy failed'
        && $payload->severity === 'error';
});
```

The callback receives a `Skaisser\Howl\Support\Payload` instance and must return `bool`.

### `assertSentOnChannel(string $channel, callable $callback): void`

Asserts that at least one payload sent to the given channel satisfies the callback.

```php
Howl::on('deployments')->deployment($event);

$fake->assertSentOnChannel('deployments', function ($payload) {
    return $payload->severity === 'deployment';
});
```

### `assertSentEvent(string $eventName): void`

Asserts that at least one dispatched payload carries the given event name in `meta['event']`. The event name is the snake_case class basename, auto-injected by `HowlEvent::baseFooterMeta()`.

```php
use App\Howl\OrderFailedEvent;

Howl::error(new OrderFailedEvent($order));

$fake->assertSentEvent('order_failed');  // snake_case basename
```

### `assertNothingSent(): void`

Asserts that no payloads have been dispatched during the test.

```php
$fake = Howl::fake();

// … code that should NOT send notifications …

$fake->assertNothingSent();
```

### `assertSentVia(string $driver, callable $callback): void`

Asserts that at least one payload dispatched **via the given driver** satisfies the callback. Use this when your code explicitly calls `Howl::driver('slack')->info(...)`.

```php
Howl::driver('slack')->info('System OK');

$fake->assertSentVia('slack', fn ($payload) => $payload->severity === 'info');
```

### `assertSentViaNothing(string $driver): void`

Asserts that **no** payloads were dispatched via the given driver.

```php
// Set up a fake, run some code, then assert Telegram was never used
$fake->assertSentViaNothing('telegram');
```

## Accessors

### `sent(?string $channel = null): array`

Returns captured `Payload` objects. Pass a channel name to filter by channel; omit to get all payloads.

```php
$payloads = $fake->sent();           // all payloads
$errors   = $fake->sent('errors');   // only payloads on the 'errors' channel
```

### `sentVia(string $driver): array`

Returns captured `Payload` objects routed via the given driver.

```php
$slackPayloads = $fake->sentVia('slack');

expect($slackPayloads)->toHaveCount(1);
expect($slackPayloads[0]->severity)->toBe('info');
```

## Payload shape

Every captured `Payload` is a readonly value object. Key properties you can assert on:

| Property | Type | Description |
|---|---|---|
| `$title` | `string` | Notification title |
| `$description` | `string\|null` | Body text |
| `$severity` | `string` | `error`, `warning`, `info`, `success`, `audit`, `deployment` |
| `$channel` | `string\|null` | Howl channel name (e.g. `'errors'`) |
| `$driver` | `string\|null` | Per-call driver override (`'discord'`, `'slack'`, `'telegram'`) |
| `$fields` | `array` | Embed fields — `[['name', 'value', 'inline']]` |
| `$codeBlocks` | `array` | Code blocks — `[['name', 'code', 'lang']]` |
| `$mentions` | `array` | Mentions — `[['type', 'id']]` |
| `$buttons` | `array` | Buttons — `[['label', 'url']]` |
| `$attachments` | `array` | File paths |
| `$meta` | `array` | Footer key-value metadata (includes auto-injected `event`, `severity`, `env`, `trace`) |
| `$forceSync` | `bool` | Whether `forceSync()` was called on the builder |

## Full example

```php
use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Facades\Howl;

it('notifies on exceptions with full payload detail', function () {
    $fake = Howl::fake();

    $exception = new \RuntimeException('DB connection lost');
    Howl::on('errors')->error(new GenericExceptionEvent($exception));

    // Assert something was sent
    $fake->assertSent(fn ($p) => $p->severity === 'error');

    // Assert on the right channel
    $fake->assertSentOnChannel('errors', fn ($p) => str_contains($p->title, 'RuntimeException'));

    // Assert the event type is tagged in meta
    $fake->assertSentEvent('generic_exception');

    // Assert NOT sent via telegram
    $fake->assertSentViaNothing('telegram');
});
```
