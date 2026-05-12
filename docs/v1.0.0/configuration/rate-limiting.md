# Rate Limiting

Howl includes opt-in Redis-backed rate limiting for queue-dispatched notifications. This prevents your application from overwhelming Discord, Slack, or Telegram with bursts of rapid-fire alerts.

## Prerequisites

Rate limiting requires:
1. **Queue mode enabled** — `HOWL_QUEUE=true`
2. **Redis** available at runtime (the `RateLimitedWithRedis` middleware requires Redis)
3. **A registered `RateLimiter`** in your `AppServiceProvider`

## Setup

### Step 1: Enable queue mode

```env
HOWL_QUEUE=true
HOWL_QUEUE_CONNECTION=redis
HOWL_QUEUE_NAME=notifications
```

### Step 2: Set the rate limiter key

```env
HOWL_RATE_LIMITER_KEY=howl-discord
```

The key must match what you register in `RateLimiter::for()`. Use a driver-specific name (e.g. `howl-discord`, `howl-slack`) if you run multiple drivers.

### Step 3: Register the limiter

In `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('howl-discord', function () {
        return Limit::perMinute(28);
    });
}
```

Discord's webhook rate limit is 30 requests per minute per webhook. Setting the Howl limiter to 28 leaves a 2-request buffer for safety.

## Platform Rate Limits Reference

| Platform | Limit | Recommended Howl Setting |
|---|---|---|
| Discord webhooks | 30 req/min per webhook | `Limit::perMinute(28)` |
| Slack `chat.postMessage` | ~1 req/sec tier-1 | `Limit::perSecond(1)` |
| Telegram `sendMessage` | 30 messages/sec per bot | `Limit::perSecond(25)` |

These are the documented limits at the time of writing. Always verify against the official platform docs.

## How It Works Internally

When `rate_limiter_key` is non-null, Howl's `SendHowlJob::middleware()` returns a `RateLimitedWithRedis` middleware instance:

```php
// From SendHowlJob (simplified)
public function middleware(): array
{
    $key = config('howl.rate_limiter_key');

    return $key ? [new RateLimitedWithRedis($key)] : [];
}
```

When the rate limit is hit, the job is automatically released back to the queue with a backoff delay (default: 5 seconds). The job will retry up to its maximum retry count (3 retries with exponential backoff).

## Horizon Pairing

If you use [Laravel Horizon](https://laravel.com/docs/horizon), configure a dedicated supervisor for the notifications queue:

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-notifications' => [
            'maxProcesses' => 2,
            'balance' => 'simple',
            'queue' => ['notifications'],
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
],
```

Keeping Howl notifications on a dedicated queue with limited worker processes prevents notification bursts from consuming resources needed by other jobs.

## Fan-Out and Rate Limits

If `channel_mode` is `fan_out`, each `Howl::error()` call dispatches **two** jobs (one per channel). With fan-out enabled, divide your rate limit by 2:

```php
// Discord limit 30 req/min, fan_out enabled:
// Each call = 2 jobs → set limit to 14 (14 × 2 = 28, leaves 2 buffer)
RateLimiter::for('howl-discord', fn () => Limit::perMinute(14));
```

## Testing with Rate Limiting

Rate limiting uses Redis. In tests, Howl runs in `skip_environments: ['testing']` so no real dispatch happens — the rate limiter is never invoked. No special mocking required.

If you write integration tests that need to exercise the queue path, use `HOWL_RATE_LIMITER_KEY=` (empty) in your test environment to disable rate limiting without code changes.
