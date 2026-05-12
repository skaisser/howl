# Custom Events

Custom events let you encode domain-specific notification structure — the exact fields, title format, severity, and channel routing — in one reusable class.

## Creating a Custom Event

Extend `HowlEvent` and implement the 8-method contract:

```php
<?php

namespace App\Howl\Events;

use Skaisser\Howl\Events\HowlEvent;

class PaymentFailedEvent extends HowlEvent
{
    public function __construct(
        private readonly \Throwable $exception,
        private readonly int $paymentId,
        private readonly string $gateway,
    ) {}

    public function severity(): string
    {
        return 'error';
    }

    public function title(): string
    {
        return "Payment Failed: #{$this->paymentId}";
    }

    public function description(): ?string
    {
        return $this->exception->getMessage();
    }

    public function fields(): array
    {
        return [
            ['name' => 'Payment ID', 'value' => (string) $this->paymentId, 'inline' => true],
            ['name' => 'Gateway',    'value' => $this->gateway, 'inline' => true],
        ];
    }

    public function emoji(): string
    {
        return '💳';
    }

    public function codeBlocks(): array
    {
        return [
            ['name' => 'Exception', 'code' => $this->exception->getMessage(), 'lang' => 'text'],
        ];
    }

    public function footerMeta(): array
    {
        return ['gateway' => $this->gateway];
    }

    public function channel(): ?string
    {
        return 'payments';
    }
}
```

Usage:

```php
Howl::error(new PaymentFailedEvent($exception, $payment->id, 'stripe'));
```

## Builder-State-Wins Override Patterns

When you pass a custom event through a fluent builder, builder values win on collision:

```php
// Override title only — keep all event fields, channel, etc.
Howl::title('Payment Gateway Timeout')->error(new PaymentFailedEvent($e, $id, $gateway));

// Override channel — route to a different channel than the event declares
Howl::on('oncall')->error(new PaymentFailedEvent($e, $id, $gateway));

// Append fields to the event's fields (event fields come first)
Howl::field('Request ID', $requestId)->error(new PaymentFailedEvent($e, $id, $gateway));
```

## Using the Universal Constructor

Instead of a custom constructor, you can use the inherited universal constructor for simple events:

```php
class MaintenanceModeEvent extends HowlEvent
{
    // Use parent __construct(array $links = [], array $meta = [])

    public function severity(): string { return 'warning'; }
    public function title(): string { return 'Maintenance Mode Activated'; }
    public function description(): ?string { return 'The application is entering maintenance mode.'; }
    public function fields(): array { return []; }
    public function emoji(): string { return '🔧'; }
    public function codeBlocks(): array { return []; }
    public function footerMeta(): array { return []; }
    public function channel(): ?string { return 'deployments'; }
}

// With links (rendered as buttons)
Howl::deployment(new MaintenanceModeEvent(
    links: [['label' => 'Status Page', 'url' => 'https://status.example.com']],
    meta:  ['reason' => 'scheduled upgrade'],
));
```

## Sending via `->send()` Without Severity Mismatch Checks

Terminal verbs (`->error()`, `->warning()`, etc.) throw a `LogicException` if the event's `severity()` doesn't match the verb. To defer completely to the event's severity:

```php
// Uses whatever $event->severity() returns — no LogicException
Howl::send($event);

// Equivalent via PendingNotification
(new PendingNotification)->send($event);
```

Or suppress the check explicitly with `->severity()`:

```php
// Send a 'warning'-severity event as 'error' — override suppresses the check
Howl::severity('error')->warning($event);
```

## Channel Resolution for Custom Events

Channel precedence for custom events follows the same chain as all notifications:

1. `Howl::on('channel')` per-call override (wins)
2. `$event->channel()` return value
3. `config('howl.channel')` fallback

Return `null` from `channel()` to always defer to per-call or config:

```php
public function channel(): ?string
{
    // Defer channel choice to the caller or config default
    return null;
}
```

## Type Safety with Terminal Verbs

Terminal verbs enforce severity matching at call time. If your event's `severity()` always returns `'error'`, callers should always use `->error($event)` — it's a compile-time-readable contract:

```php
// Correct: PaymentFailedEvent::severity() returns 'error'
Howl::error(new PaymentFailedEvent($e, $id, $gateway));

// Wrong: throws LogicException at runtime
// Howl::info(new PaymentFailedEvent($e, $id, $gateway));
```

This prevents accidentally sending a critical payment failure as a low-priority info notification.
