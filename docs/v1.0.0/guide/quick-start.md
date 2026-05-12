# Quick Start

This page shows the most common Howl usage patterns. All examples assume you've completed [installation](/next/guide/installation).

## Direct Severity Verbs

The simplest way to send a notification is to call a severity verb directly on the facade:

```php
use Skaisser\Howl\Facades\Howl;

// Send an error notification with a string title
Howl::error('Database connection failed');

// Send an info notification
Howl::info('Cache warmed successfully');

// Other severity verbs
Howl::warning('Memory usage above 80%');
Howl::success('Backup completed');
Howl::audit('User settings updated');
Howl::deployment('v1.2.0 deployed to production');
```

All severity verbs accept either a plain string (used as the notification title) or a `HowlEvent` instance.

## Using Event Objects

For richer notifications with fields, code blocks, and structured data, pass a `HowlEvent` instance:

```php
use Skaisser\Howl\Events\GenericExceptionEvent;

try {
    // ... your code
} catch (\Throwable $e) {
    Howl::error(new GenericExceptionEvent($e));
}
```

The `GenericExceptionEvent` automatically populates the title with the exception class, the description with the exception message, and a code block with the stack trace.

## Channel Override

Send to a specific channel instead of the default:

```php
// Override the channel for this notification only
Howl::on('audits')->audit(new AuditEvent($user, 'settings.updated'));

// null restores the config default channel
Howl::on('errors')->error($exception);
Howl::on('deployments')->deployment('v1.0.0 released');
```

Channel routing precedence (highest to lowest):
1. Per-call `Howl::on($channel)`
2. `HowlEvent::channel()` return value
3. `config('howl.channel')` default

## Driver Override

Send via a specific driver for this notification only:

```php
// Force Slack for this one notification
Howl::driver('slack')->info('Scheduled report generated');

// Force Telegram
Howl::driver('telegram')->error('Critical: payment processor down');

// Chain with channel override
Howl::driver('slack')->on('alerts')->error($event);
```

## Fluent Builder

For complex notifications, use the fluent builder:

```php
Howl::on('errors')
    ->title('Database Migration Failed')
    ->description('Migration `2026_05_12_add_payments_table` failed on production.')
    ->field('Environment', 'production')
    ->field('Database', 'mysql-primary')
    ->codeBlock('Error', $exception->getMessage())
    ->button('View Logs', 'https://your-app.com/logs')
    ->error();
```

## String Title Shortcut

When you only need a title (no event, no extra fields), pass a string directly to any severity verb:

```php
Howl::error('Payment gateway timeout');
Howl::info('Cron: send-digests completed in 1.2s');
Howl::deployment('v1.0.0 → production');
```

## Testing with HowlFake

In your tests, use `Howl::fake()` to capture notifications without real HTTP calls:

```php
use Skaisser\Howl\Facades\Howl;

it('sends an error notification on exception', function () {
    $fake = Howl::fake();

    // Trigger the code under test
    app(MyService::class)->doSomethingDangerous();

    // Assert the notification was sent
    $fake->assertSent(function ($payload) {
        return $payload->severity === 'error'
            && str_contains($payload->title, 'Exception');
    });
});
```

See [HowlFake](/next/testing/howl-fake) for the full assertion API.

## What's Next

- [Configuration Reference](/next/configuration/reference) — all config keys
- [Channel Routing](/next/configuration/channel-routing) — routing precedence explained
- [Discord Driver](/next/drivers/discord) — Discord webhook and thread setup
- [Slack Driver](/next/drivers/slack) — Slack App OAuth and Block Kit
- [Telegram Driver](/next/drivers/telegram) — Telegram Bot and Forum topics
- [Built-in Events](/next/events/built-in) — production-ready event templates
- [Builder Methods](/next/extension/builder-methods) — full fluent builder API reference
