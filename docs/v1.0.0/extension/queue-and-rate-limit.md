# Queue & Rate Limiting

Howl supports asynchronous dispatch via Laravel's queue system and opt-in per-driver rate limiting via Redis. Both are off by default and require explicit configuration.

## Queue mode

### Enabling the queue

Set `HOWL_QUEUE=true` in your `.env` or set `'queue' => true` in `config/howl.php`. When enabled, every `Howl::*()` call dispatches a `SendHowlJob` to the queue instead of sending synchronously.

```env
HOWL_QUEUE=true
HOWL_QUEUE_CONNECTION=redis      # optional — defaults to the app default connection
HOWL_QUEUE_NAME=howl             # optional — defaults to 'default'
```

```php
// config/howl.php
'queue'            => env('HOWL_QUEUE', false),
'queue_connection' => env('HOWL_QUEUE_CONNECTION', null),
'queue_name'       => env('HOWL_QUEUE_NAME', 'default'),
```

### SendHowlJob

`Skaisser\Howl\Jobs\SendHowlJob` is the queued job class. Key characteristics:

- **3 tries** with exponential backoff: 1 s, 4 s, 16 s.
- **Serializes** the `Payload` and driver name. `Payload` is a readonly value object — safe to serialize.
- On a `false` return from the driver, re-throws as `RuntimeException` to trigger a retry. Failed jobs after all retries appear in the `failed_jobs` table.

### Bypassing the queue per-call

Use `->forceSync()` to dispatch synchronously even when queue mode is on:

```php
// Critical alert — bypass queue, send now
Howl::forceSync()->error('Payment gateway down');
```

## Rate limiting

Howl supports opt-in per-driver rate limiting using Laravel's `RateLimitedWithRedis` middleware on `SendHowlJob`. This is useful for Discord webhooks (429 rate limit: 30 requests/60 s per channel) and Telegram bots (30 messages/second globally).

### Configuring the rate limiter

**Step 1 — Set the key in `config/howl.php`:**

```php
'rate_limiter_key' => env('HOWL_RATE_LIMITER_KEY', null),
```

```env
HOWL_RATE_LIMITER_KEY=howl-discord
```

When `rate_limiter_key` is `null` (the default), no rate limiting is applied.

**Step 2 — Register the limiter in `AppServiceProvider::boot()`:**

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Discord allows 30 requests/60 s per channel; use 28 to leave headroom
    RateLimiter::for('howl-discord', fn () => Limit::perMinute(28));
}
```

The key you pass to `RateLimiter::for()` must match the `rate_limiter_key` config value exactly.

### How it works

`SendHowlJob::middleware()` checks `config('howl.rate_limiter_key')`. When non-null, it wraps the job with `RateLimitedWithRedis($key)`. This releases the job back to the queue without consuming a retry attempt when the rate limit is hit — the job waits and retries automatically once the rate window clears.

```php
// From SendHowlJob:
public function middleware(): array
{
    $key = config('howl.rate_limiter_key');

    return $key !== null
        ? [new \Illuminate\Queue\Middleware\RateLimitedWithRedis($key)]
        : [];
}
```

> **Important:** Rate-limit releases do NOT count against `$tries`. Only actual driver failures (non-200 responses, exceptions) consume retry attempts.

### Fan-out caveat

When `channel_mode = 'fan_out'`, each notification dispatches to **two** channels. If both channels share the same rate limiter key, your effective throughput is halved. Size the `Limit::perMinute()` quota to account for the fan-out multiplier, or use separate limiter keys per channel.

## Horizon integration

If you run Laravel Horizon, dedicate a supervisor to the Howl queue to prevent it from blocking application jobs:

```yaml
# horizon.php (example)
'supervisors' => [
    [
        'connection' => 'redis',
        'queue'      => ['howl'],
        'processes'  => 2,
        'tries'      => 3,
    ],
],
```

## Checking failed jobs

Howl failures after all retries land in `failed_jobs`. Query them:

```bash
php artisan queue:failed
php artisan queue:retry <id>
```

Or configure a `failed` listener in `AppServiceProvider` to re-alert on a backup channel:

```php
Queue::failing(function (JobFailed $event) {
    if ($event->job->resolveName() === SendHowlJob::class) {
        // Use forceSync so this alert doesn't re-queue
        Howl::driver('telegram')->forceSync()->error('Howl job failed — ' . $event->exception->getMessage());
    }
});
```
