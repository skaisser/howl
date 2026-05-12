# Failover & Fan-Out

Howl supports two multi-channel dispatch modes controlled by `channel_backup` and `channel_mode` in `config/howl.php`. Both modes require a backup channel to be configured.

## Configuration

```php
// config/howl.php
'channel'        => env('HOWL_DEFAULT_CHANNEL', 'errors'),
'channel_backup' => env('HOWL_BACKUP_CHANNEL', null),
'channel_mode'   => env('HOWL_CHANNEL_MODE', 'failover'),
```

```env
HOWL_DEFAULT_CHANNEL=errors
HOWL_BACKUP_CHANNEL=errors-backup
HOWL_CHANNEL_MODE=failover
```

When `channel_backup` is `null`, both modes behave identically: single-channel dispatch only.

## Failover Mode (default)

**When to use:** You want high-reliability alerting. If the primary channel fails (thread not found, rate limited, network error), Howl automatically retries with the backup channel.

```env
HOWL_CHANNEL_MODE=failover
```

### How it works

1. Howl dispatches to the **primary channel** (resolved via the precedence chain).
2. If the primary channel's driver returns `false` or throws an exception, Howl dispatches to the **backup channel**.
3. The overall `dispatch()` call returns `true` if either channel succeeded.

```
primary channel ──success──► return true
     │
   failure
     │
     ▼
backup channel ──success──► return true
     │
   failure
     │
     ▼
   return false (both failed)
```

### Failover example

```php
// Notification goes to 'errors' thread.
// If the 'errors' Discord thread is unavailable, tries 'errors-backup' thread.
Howl::error(new GenericExceptionEvent($e));
```

### Failover with per-call driver override

When you use `Howl::driver('slack')`, the failover still operates at the **channel** level within that driver. The driver-level fallback (`config('howl.fallback')`) is separate from channel failover.

## Fan-Out Mode

**When to use:** Critical notifications that must reach multiple channels simultaneously — for example, posting to both a team-specific channel and a global #incidents channel.

```env
HOWL_CHANNEL_MODE=fan_out
```

### How it works

1. Howl dispatches to the **primary channel**.
2. Howl dispatches to the **backup channel** (regardless of whether primary succeeded or failed).
3. Returns `true` if at least one channel succeeded.

```
primary channel ──► dispatch (result A)
     │
     ▼
backup channel ──► dispatch (result B)
     │
     ▼
   return A || B
```

### Fan-Out example

```php
// Posts to both 'errors' AND 'errors-oncall' simultaneously
Howl::error(new CriticalPaymentFailureEvent($payment));
```

### Rate-limit caveat

Fan-out **doubles your rate-limit consumption** because it dispatches to two channels instead of one. If you have a rate limiter configured via `rate_limiter_key`, each fan-out notification consumes two slots.

Size your `RateLimiter::for()` quota accordingly:

```php
// If fan_out is on and you want to stay under Discord's 30 req/min:
RateLimiter::for('howl-discord', fn () => Limit::perMinute(14)); // 14×2 = 28
```

## Choosing Between Modes

| Scenario | Recommended Mode |
|---|---|
| You want a safety net if the primary channel is unavailable | `failover` |
| You need the same alert in multiple channels simultaneously | `fan_out` |
| You only have one channel configured | Either (backup ignored if `null`) |
| You use rate limiting and high volume | `failover` (half the rate-limit consumption) |

## Channel Failover vs Driver Fallback

These are two separate systems:

| System | Config Key | Purpose |
|---|---|---|
| **Channel failover/fan-out** | `channel_backup` + `channel_mode` | Route within the same driver to a backup channel |
| **Driver fallback** | `fallback` | If the entire driver fails, try a different driver |

You can combine them:

```env
# Primary: discord → errors thread. Backup: discord → errors-backup thread.
HOWL_BACKUP_CHANNEL=errors-backup
HOWL_CHANNEL_MODE=failover

# If discord entirely fails, try telegram:
HOWL_FALLBACK=telegram
```
