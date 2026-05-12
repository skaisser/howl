# Configuration Reference

After running `php artisan vendor:publish --tag=howl-config`, your application will have `config/howl.php`. Every key is documented below.

## Top-Level Keys

### `driver`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `driver` | `HOWL_DRIVER` | `string` | `'discord'` |

The default driver used when no per-call `Howl::driver()` override is set.

Accepted values: `'discord'`, `'slack'`, `'telegram'`, `'null'`

```php
'driver' => env('HOWL_DRIVER', 'discord'),
```

### `fallback`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `fallback` | `HOWL_FALLBACK` | `?string` | `null` |

If the primary driver fails (non-2xx HTTP, timeout, network error), Howl will retry with this fallback driver. Set to `null` to disable driver-level fallback.

```php
'fallback' => env('HOWL_FALLBACK', null),
```

### `channel`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `channel` | `HOWL_DEFAULT_CHANNEL` | `string` | `'errors'` |

The default channel name when no per-call `Howl::on($channel)` override is set and the event's `channel()` method returns `null`.

Channel names are mapped to driver-specific thread IDs or channel IDs via the driver's `threads` or `channels` config block.

### `channel_backup`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `channel_backup` | `HOWL_BACKUP_CHANNEL` | `?string` | `null` |

Optional second channel for failover or fan-out behaviour. When set, Howl uses the `channel_mode` setting to determine whether to fall back to this channel on failure (`failover`) or dispatch to both channels sequentially (`fan_out`).

### `channel_mode`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `channel_mode` | `HOWL_CHANNEL_MODE` | `string` | `'failover'` |

Controls how the primary and backup channels interact when `channel_backup` is non-null.

| Value | Behaviour |
|---|---|
| `failover` | Try primary channel; on failure, try backup once. Returns `true` on first success. |
| `fan_out` | Dispatch to **both** channels sequentially. Returns `true` if at least one succeeds. Note: doubles rate-limit consumption. |

See [Failover & Fan-Out](/next/configuration/failover-and-fan-out) for detailed examples.

### `queue`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `queue` | `HOWL_QUEUE` | `bool` | `false` |

When `true`, notification sends are dispatched as `SendHowlJob` queue jobs (3 retries, exponential backoff). When `false`, sends are synchronous.

Queue-failure events always force sync to avoid recursive loops.

### `queue_connection`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `queue_connection` | `HOWL_QUEUE_CONNECTION` | `?string` | `null` |

The queue connection to use for `SendHowlJob`. When `null`, uses Laravel's default queue connection.

### `queue_name`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `queue_name` | `HOWL_QUEUE_NAME` | `string` | `'default'` |

The queue name for `SendHowlJob`. Allows routing Howl jobs to a dedicated queue (e.g. `'notifications'`).

### `rate_limiter_key`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `rate_limiter_key` | `HOWL_RATE_LIMITER_KEY` | `?string` | `null` |

When non-null, `SendHowlJob` wraps each job with `RateLimitedWithRedis` using this key. You must register the limiter in `AppServiceProvider::boot()`:

```php
RateLimiter::for('howl-discord', fn () => Limit::perMinute(28));
```

See [Rate Limiting](/next/configuration/rate-limiting) for the full setup guide.

### `app_name`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `app_name` | `HOWL_APP_NAME` | `string` | `env('APP_NAME', 'App')` |

The application name used in webhook usernames and embed footers. Defaults to your Laravel `APP_NAME`.

### `app_env`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `app_env` | `HOWL_APP_ENV` | `string` | `env('APP_ENV', 'local')` |

The environment label displayed in footers. Defaults to your Laravel `APP_ENV`.

### `username_format`

| Key | Env Var | Type | Default |
|---|---|---|---|
| `username_format` | `HOWL_USERNAME_FORMAT` | `string` | `'{severity_emoji} {app} · {env} · {channel}'` |

Template for the webhook username. Supported tokens: `{severity_emoji}`, `{app}`, `{env}`, `{channel}`.

### `skip_environments`

| Key | Type | Default |
|---|---|---|
| `skip_environments` | `array<string>` | `['testing']` |

Howl silently no-ops when `APP_ENV` is in this list. Keeps test suites clean without mocking.

```php
'skip_environments' => ['testing'],
```

## Drivers Configuration

### `drivers.discord`

| Sub-key | Env Var | Type | Default |
|---|---|---|---|
| `webhook_url` | `HOWL_DISCORD_DEFAULT` | `?string` | `null` |
| `threads.errors` | `HOWL_DISCORD_THREAD_ERRORS` | `?string` | `null` |
| `threads.warnings` | `HOWL_DISCORD_THREAD_WARNINGS` | `?string` | `null` |
| `threads.info` | `HOWL_DISCORD_THREAD_INFO` | `?string` | `null` |
| `threads.audit` | `HOWL_DISCORD_THREAD_AUDIT` | `?string` | `null` |
| `threads.deployments` | `HOWL_DISCORD_THREAD_DEPLOYMENTS` | `?string` | `null` |
| `channels.errors` | `HOWL_DISCORD_ERRORS` | `?string` | `null` |
| `channels.warnings` | `HOWL_DISCORD_WARNINGS` | `?string` | `null` |
| `channels.info` | `HOWL_DISCORD_INFO` | `?string` | `null` |
| `channels.audit` | `HOWL_DISCORD_AUDIT` | `?string` | `null` |
| `channels.deployments` | `HOWL_DISCORD_DEPLOYMENTS` | `?string` | `null` |
| `timeout` | `HOWL_DISCORD_TIMEOUT` | `int` | `10` |
| `avatar_url` | `HOWL_DISCORD_AVATAR_URL` | `?string` | `null` |

See [Discord Driver](/next/drivers/discord) for complete Discord setup documentation.

### `drivers.slack`

| Sub-key | Env Var | Type | Default |
|---|---|---|---|
| `bot_token` | `HOWL_SLACK_BOT_TOKEN` | `?string` | `null` |
| `default_channel` | `HOWL_SLACK_DEFAULT_CHANNEL` | `?string` | `null` |
| `channels.errors` | `HOWL_SLACK_CHANNEL_ERRORS` | `?string` | `null` |
| `channels.warnings` | `HOWL_SLACK_CHANNEL_WARNINGS` | `?string` | `null` |
| `channels.info` | `HOWL_SLACK_CHANNEL_INFO` | `?string` | `null` |
| `channels.audit` | `HOWL_SLACK_CHANNEL_AUDIT` | `?string` | `null` |
| `channels.deployments` | `HOWL_SLACK_CHANNEL_DEPLOYMENTS` | `?string` | `null` |
| `timeout` | `HOWL_SLACK_TIMEOUT` | `int` | `10` |

See [Slack Driver](/next/drivers/slack) for complete Slack App setup documentation.

### `drivers.telegram`

| Sub-key | Env Var | Type | Default |
|---|---|---|---|
| `bot_token` | `HOWL_TELEGRAM_BOT_TOKEN` | `?string` | `null` |
| `chat_id` | `HOWL_TELEGRAM_CHAT_ID` | `?string` | `null` |
| `threads.errors` | `HOWL_TELEGRAM_THREAD_ERRORS` | `?string` | `null` |
| `threads.warnings` | `HOWL_TELEGRAM_THREAD_WARNINGS` | `?string` | `null` |
| `threads.info` | `HOWL_TELEGRAM_THREAD_INFO` | `?string` | `null` |
| `threads.audit` | `HOWL_TELEGRAM_THREAD_AUDIT` | `?string` | `null` |
| `threads.deployments` | `HOWL_TELEGRAM_THREAD_DEPLOYMENTS` | `?string` | `null` |
| `timeout` | `HOWL_TELEGRAM_TIMEOUT` | `int` | `10` |

See [Telegram Driver](/next/drivers/telegram) for complete Telegram Bot setup documentation.

## Color and Emoji Maps

### `colors`

Maps severity names to Discord embed colors (decimal RGB). Not used by Slack or Telegram drivers.

| Severity | Default Color | Hex |
|---|---|---|
| `error` | `15548997` | `#ED4245` |
| `warning` | `16765440` | `#FFC000` |
| `info` | `3447003` | `#4169E1` |
| `success` | `5763719` | `#57F287` |
| `audit` | `10181046` | `#9B59B6` |
| `deployment` | `1752220` | `#1ABC9C` |

### `emojis`

Maps severity names to emoji characters used in usernames and Telegram messages.

| Severity | Default Emoji |
|---|---|
| `error` | 🚨 |
| `warning` | 🟡 |
| `info` | ℹ️ |
| `success` | ✅ |
| `audit` | 🔒 |
| `deployment` | 🚀 |

### `mentions`

Per-channel mention IDs added to Discord embed `content` fields. Format is driver-specific — Discord `<@!userId>` or `<@&roleId>` strings.

```php
'mentions' => [
    'errors'      => env('HOWL_MENTION_ERRORS'),
    'warnings'    => env('HOWL_MENTION_WARNINGS'),
    'audit'       => env('HOWL_MENTION_AUDIT'),
    'deployments' => env('HOWL_MENTION_DEPLOYMENTS'),
],
```
