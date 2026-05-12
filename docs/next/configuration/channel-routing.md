# Channel Routing

Howl's channel routing system determines *which* channel a notification is delivered to. A "channel" is an abstract name (like `'errors'` or `'deployments'`) that each driver maps to its own platform-specific destination.

## What is a Channel?

A **channel name** in Howl is a string key — typically `'errors'`, `'warnings'`, `'info'`, `'audit'`, or `'deployments'`. Each driver maps these names to:

- **Discord**: a thread ID (`?thread_id=N`) or per-category webhook URL
- **Slack**: a Slack channel ID (e.g. `C0123ABCDEF`)
- **Telegram**: a Forum topic ID (integer)

You define your own channel names and map them in `config/howl.php` under each driver's `threads` or `channels` block.

## Routing Precedence

Channel resolution uses a three-level precedence chain (highest wins):

```
1. Per-call override:    Howl::on('channel-name')
2. Event contract:       HowlEvent::channel() return value
3. Config default:       config('howl.channel')  (env: HOWL_DEFAULT_CHANNEL)
```

### Level 1: Per-Call Override

```php
Howl::on('deployments')->deployment($event);
```

`Howl::on('deployments')` pins the channel for this call only. It does not affect other notifications. If the event's `channel()` method returns a different value, the per-call value wins.

### Level 2: Event Contract

If no per-call override is set, Howl calls `$event->channel()` on the event. For built-in events, `channel()` typically returns a severity-based channel name:

```php
// GenericInfoEvent maps severity → channel automatically:
// 'error' → 'errors', 'warning' → 'warnings', 'info' → 'info', etc.
```

For custom events, override `channel()` to return a fixed channel:

```php
class MyCustomEvent extends HowlEvent
{
    public function channel(): ?string
    {
        return 'custom-channel'; // always routes here unless per-call override is set
    }
}
```

Returning `null` from `channel()` defers to the config default.

### Level 3: Config Default

When neither the per-call override nor the event contract provides a channel, Howl falls back to:

```php
config('howl.channel') // env: HOWL_DEFAULT_CHANNEL, default: 'errors'
```

## Diagram

```
Howl::on('alerts')->error($event)
        │
        ▼
  per-call channel? ──yes──► 'alerts' ─► driver channel lookup ─► send
        │
        no
        ▼
  $event->channel()? ──non-null──► channel value ─► driver lookup ─► send
        │
        null
        ▼
  config('howl.channel') ─────────────► channel value ─► driver lookup ─► send
```

## Driver Channel Lookup

After channel resolution, the driver looks up the platform destination for that channel name.

### Discord lookup

```php
// Priority: channels.$name (per-category webhook) > threads.$name (thread_id) > webhook_url (root)
$webhookUrl = config("howl.drivers.discord.channels.{$channel}")
           ?? config('howl.drivers.discord.webhook_url');
$threadId   = config("howl.drivers.discord.threads.{$channel}");
```

### Slack lookup

```php
// Priority: channels.$name > default_channel
$channelId = config("howl.drivers.slack.channels.{$channel}")
          ?? config('howl.drivers.slack.default_channel');
```

### Telegram lookup

```php
// Optional thread routing via message_thread_id
$threadId = config("howl.drivers.telegram.threads.{$channel}");
```

## Common Patterns

### Route by severity (default behaviour)

Leave the `channel` key at `'errors'` and configure your driver's `threads`/`channels` for `errors`, `warnings`, `info`, etc. Built-in events with a severity-based `channel()` implementation (like `GenericInfoEvent`) route themselves automatically.

### Route by domain

Create named channels for different functional areas:

```env
# In your .env
HOWL_SLACK_CHANNEL_PAYMENTS=C0123ABCDEF
HOWL_SLACK_CHANNEL_AUTH=C0234BCDEF0
HOWL_SLACK_CHANNEL_DEPLOYMENTS=C0345CDEF01
```

Then route per-call:

```php
Howl::on('payments')->error($paymentFailedEvent);
Howl::on('auth')->warning($suspiciousLoginEvent);
```

### Force the default channel

Pass `null` to `Howl::on()` to explicitly defer to config:

```php
Howl::on(null)->info($event); // uses config('howl.channel')
```
