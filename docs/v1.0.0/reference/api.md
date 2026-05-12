# API Reference

Complete reference for the `Howl` facade and `PendingNotification` fluent builder.

## Howl facade

The `Skaisser\Howl\Facades\Howl` facade proxies to the `howl` IoC binding (`Skaisser\Howl\Howl`).

### Entry points

| Signature | Returns | Description |
|---|---|---|
| `Howl::on(?string $channel = null)` | `PendingNotification` | Begin a notification with an optional channel override |
| `Howl::driver(string $name)` | `PendingNotification` | Begin a notification with a per-call driver override |

### Direct severity dispatch

These are shortcuts for `(new PendingNotification)->error(...)` etc. They accept either a `HowlEvent` instance or a plain string title.

| Signature | Returns | Description |
|---|---|---|
| `Howl::error(HowlEvent\|string $titleOrEvent = '')` | `bool` | Send with `severity = 'error'` |
| `Howl::warning(HowlEvent\|string $titleOrEvent = '')` | `bool` | Send with `severity = 'warning'` |
| `Howl::info(HowlEvent\|string $titleOrEvent = '')` | `bool` | Send with `severity = 'info'` |
| `Howl::success(HowlEvent\|string $titleOrEvent = '')` | `bool` | Send with `severity = 'success'` |
| `Howl::audit(HowlEvent\|string $titleOrEvent = '')` | `bool` | Send with `severity = 'audit'` |
| `Howl::deployment(HowlEvent\|string $titleOrEvent = '')` | `bool` | Send with `severity = 'deployment'` |

### Core dispatch

| Signature | Returns | Description |
|---|---|---|
| `Howl::dispatch(Payload $payload)` | `bool` | Dispatch a pre-built payload (low-level; prefer the builder) |

### Testing

| Signature | Returns | Description |
|---|---|---|
| `Howl::fake()` | `HowlFake` | Swap the IoC binding with a fake; returns the fake for assertions |
| `Howl::assertSent(callable $callback)` | `void` | Assert any payload satisfies the callback |
| `Howl::assertSentOnChannel(string $channel, callable $callback)` | `void` | Assert a payload on the given channel satisfies the callback |
| `Howl::assertSentEvent(string $eventName)` | `void` | Assert a payload with `meta['event'] === $eventName` was sent |
| `Howl::assertNothingSent()` | `void` | Assert no payloads were sent |
| `Howl::assertSentVia(string $driver, callable $callback)` | `void` | Assert a payload via the given driver satisfies the callback |
| `Howl::assertSentViaNothing(string $driver)` | `void` | Assert no payloads were sent via the given driver |
| `Howl::sent(?string $channel = null)` | `Payload[]` | Return captured payloads (all, or filtered by channel) |
| `Howl::sentVia(string $driver)` | `Payload[]` | Return captured payloads via the given driver |

---

## PendingNotification builder

`Skaisser\Howl\Support\PendingNotification` — immutable fluent builder returned by `Howl::on()` and `Howl::driver()`. Every method returns a new clone.

### Scalar setters

| Signature | Description |
|---|---|
| `title(string $title)` | Set the notification title |
| `description(string $description)` | Set the body text |
| `body(string $body)` | Alias for `description()` |
| `channel(string $channel)` | Set the Howl channel (highest precedence) |
| `driver(string $name)` | Override the driver for this call |
| `username(string $username)` | Override the sender display name |
| `app(string $app)` | App name in footer meta |
| `env(string $env)` | Environment name in footer meta |
| `at(\DateTimeInterface $timestamp)` | Embed timestamp |

### Content methods

| Signature | Description |
|---|---|
| `field(string $name, string $value, bool $inline = true)` | Add an embed field |
| `codeBlock(string $name, string $code, string $lang = 'php')` | Append a code block |
| `mention(string $type, string $id = '')` | Add a mention: `'user'`, `'role'`, `'here'`, `'everyone'` |
| `meta(array\|string $key, mixed $value = null)` | Add footer key-value metadata |
| `button(string $label, string $url)` | Append a link button |
| `attach(string $path)` | Attach a local file |
| `thread(string $threadId)` | Set thread/topic routing ID |

### Control methods

| Signature | Description |
|---|---|
| `severity(string $severity)` | Explicitly set severity, overrides verb and event |
| `acceptEvent(HowlEvent $event)` | Load a HowlEvent as the base payload |
| `forceSync()` | Bypass queue and send synchronously |
| `withFallback(string $driver)` | Per-call fallback driver override |

### Terminal methods

| Signature | Returns | Description |
|---|---|---|
| `error(HowlEvent\|string $event = '')` | `bool` | Build payload and dispatch with severity `'error'` |
| `warning(HowlEvent\|string $event = '')` | `bool` | Build payload and dispatch with severity `'warning'` |
| `info(HowlEvent\|string $event = '')` | `bool` | Build payload and dispatch with severity `'info'` |
| `success(HowlEvent\|string $event = '')` | `bool` | Build payload and dispatch with severity `'success'` |
| `audit(HowlEvent\|string $event = '')` | `bool` | Build payload and dispatch with severity `'audit'` |
| `deployment(HowlEvent\|string $event = '')` | `bool` | Build payload and dispatch with severity `'deployment'` |
| `send(HowlEvent\|string $severity = 'info', string $title = '')` | `bool` | Generic send — severity as string or pass HowlEvent to defer |

---

## Payload

`Skaisser\Howl\Support\Payload` — final readonly value object. All properties are public.

| Property | Type | Description |
|---|---|---|
| `$title` | `string` | Notification title |
| `$description` | `string\|null` | Body text |
| `$severity` | `string` | Severity level |
| `$channel` | `string\|null` | Howl channel name |
| `$driver` | `string\|null` | Per-call driver override |
| `$fields` | `array` | Embed fields: `[['name'=>string, 'value'=>string, 'inline'=>bool]]` |
| `$codeBlocks` | `array` | Code blocks: `[['name'=>string, 'code'=>string, 'lang'=>string]]` |
| `$mentions` | `array` | Mentions: `[['type'=>string, 'id'=>string]]` |
| `$buttons` | `array` | Buttons: `[['label'=>string, 'url'=>string]]` |
| `$attachments` | `array` | File paths |
| `$meta` | `array` | Footer metadata (includes auto-injected `event`, `severity`, `env`, `trace`) |
| `$threadId` | `string\|null` | Thread/topic routing ID |
| `$username` | `string\|null` | Sender display name override |
| `$app` | `string\|null` | App name override |
| `$env` | `string\|null` | Environment name override |
| `$timestamp` | `\DateTimeInterface\|null` | Embed timestamp |
| `$forceSync` | `bool` | Whether to bypass the queue |
| `$fallback` | `string\|null` | Per-call fallback driver |

---

## Driver contract

`Skaisser\Howl\Contracts\Driver` — interface for all built-in and custom drivers.

| Method | Returns | Description |
|---|---|---|
| `name()` | `string` | Unique lowercase driver name (`'discord'`, `'slack'`, `'telegram'`, `'null'`) |
| `send(Payload $payload)` | `bool` | Send the payload — `true` on success, `false` on failure; throwing is allowed |

---

## HowlEvent

`Skaisser\Howl\Events\HowlEvent` — abstract base for all notification events. See the [Event Contract](/next/events/contract) page for the full 8-method contract and `toPayload()` orchestration.
