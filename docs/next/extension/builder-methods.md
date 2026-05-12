# Builder Methods

`PendingNotification` is the fluent builder returned by `Howl::on()`, `Howl::driver()`, and the terminal severity verbs. Every method returns a new immutable clone — the builder is safe to re-use and pre-configure.

## Overview

```php
Howl::on('errors')           // PendingNotification (channel pre-set)
    ->title('Deploy failed')
    ->description('The staging deploy failed with exit code 1.')
    ->field('Environment', 'staging')
    ->field('Branch', 'feat/payments')
    ->codeBlock('Error output', $output, 'bash')
    ->mention('here')
    ->button('View CI run', 'https://github.com/...')
    ->attach(storage_path('logs/deploy.log'))
    ->error();               // terminal verb — builds Payload and dispatches
```

## Entry point methods

These are called directly on the `Howl` facade and return a fresh `PendingNotification`.

| Method | Returns | Description |
|---|---|---|
| `Howl::on(?string $channel)` | `PendingNotification` | Begin a notification, optionally pre-set the channel |
| `Howl::driver(string $name)` | `PendingNotification` | Begin a notification, pre-set the driver (e.g. `'slack'`, `'telegram'`) |

## Scalar setters

| Method | Description |
|---|---|
| `title(string $title)` | Set the notification title |
| `description(string $description)` | Set the body text |
| `body(string $body)` | Alias for `description()` |
| `channel(string $channel)` | Override the channel — beats `HowlEvent::channel()` and `config('howl.channel')` |
| `driver(string $name)` | Override the driver for this call only |
| `username(string $username)` | Override the sender display name (Discord webhook username, Telegram bot name) |
| `app(string $app)` | App name shown in the footer meta |
| `env(string $env)` | Environment name shown in the footer meta |
| `at(\DateTimeInterface $timestamp)` | Timestamp shown in the embed footer |

## Content methods

| Method | Description |
|---|---|
| `field(string $name, string $value, bool $inline = true)` | Add an embed field (Discord/Slack fields, Telegram bold+value pairs) |
| `codeBlock(string $name, string $code, string $lang = 'php')` | Append a fenced code block (Discord `` ``` ``, Slack triple-backtick, Telegram `<pre>`) |
| `mention(string $type, string $id = '')` | Add a mention: `'user'`, `'role'`, `'here'`, `'everyone'` |
| `meta(array\|string $key, mixed $value = null)` | Add key-value metadata to the embed footer |
| `button(string $label, string $url)` | Append a link button (Discord `components`, Slack `actions` block, Telegram `inline_keyboard`) |
| `attach(string $path)` | Attach a local file path (Discord file attachment, Slack `files.upload`, Telegram `sendDocument`/`sendPhoto`) |
| `thread(string $threadId)` | Override the thread/topic ID (Discord `?thread_id`, Telegram `message_thread_id`) |

## Control methods

| Method | Description |
|---|---|
| `severity(string $severity)` | Explicitly set the severity, overriding both the terminal verb and `HowlEvent::severity()`. Also suppresses the severity-mismatch `LogicException`. |
| `acceptEvent(HowlEvent $event)` | Load a `HowlEvent` as the base; builder values WIN over event contract methods on collision |
| `forceSync()` | Bypass the queue and dispatch synchronously, even when `config('howl.queue') = true` |
| `withFallback(string $driver)` | Per-call fallback driver override — tried before the `config('howl.fallback')` if the primary fails |

## Terminal methods

Terminal methods build the `Payload` and dispatch it. They return `bool` (`true` = dispatched or queued successfully).

| Method | Description |
|---|---|
| `error(HowlEvent\|string $event = '')` | Dispatch with `severity = 'error'` |
| `warning(HowlEvent\|string $event = '')` | Dispatch with `severity = 'warning'` |
| `info(HowlEvent\|string $event = '')` | Dispatch with `severity = 'info'` |
| `success(HowlEvent\|string $event = '')` | Dispatch with `severity = 'success'` |
| `audit(HowlEvent\|string $event = '')` | Dispatch with `severity = 'audit'` |
| `deployment(HowlEvent\|string $event = '')` | Dispatch with `severity = 'deployment'` |
| `send(HowlEvent\|string $severity = 'info', string $title = '')` | Generic send — pass severity as string, or pass a `HowlEvent` to defer to its severity |

## Builder-state-wins precedence

When a `HowlEvent` is passed to a terminal verb (or `acceptEvent()` is called), builder scalar values **win** over the event's contract methods on collision:

```php
$event = new DeploymentEvent('v1.2.3', 'production');

// Builder title overrides DeploymentEvent::title()
Howl::title('Custom override title')->deployment($event);
```

For collections (fields, codeBlocks, mentions, meta, buttons, attachments): event values come **first**, builder values are **appended**. This lets you add context on top of a pre-built event.

## Severity-mismatch guard

Passing a `HowlEvent` to the wrong terminal verb throws a `\LogicException` by default:

```php
// Throws: terminal verb ->error() conflicts with event severity 'info'
Howl::error(new GenericInfoEvent('All clear'));
```

To silence the guard, either:
- Use `->send($event)` (defers to the event's own severity), or
- Set `->severity('error')` explicitly before the terminal verb.

## Immutability example

Because every method clones, you can safely re-use a pre-configured base builder:

```php
$base = Howl::on('errors')
    ->app(config('app.name'))
    ->env(app()->environment());

// Each call produces an independent clone — $base is never mutated
$base->title('DB timeout')->error();
$base->title('Redis down')->error();
```
