# Example App Template — `OrderShippedEvent`

This document walks through a complete, fictional e-commerce example of an app-specific Howl event template. Use it as a starting point when writing your own domain events. The fictional app ships orders and wants a rich Discord notification when an order is handed to a carrier.

See `docs/extending-templates.md` for the full extension guide and contract reference.

---

## The Class File

Create the event in your app's `App\Howl\Events\` namespace:

```php
<?php

namespace App\Howl\Events;

use Skaisser\Howl\Events\HowlEvent;

class OrderShippedEvent extends HowlEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $trackingNumber,
        public readonly string $carrier,
        array $links = [],
        array $meta = [],
    ) {
        parent::__construct($links, $meta);
    }

    public function severity(): string
    {
        return 'info';
    }

    public function emoji(): string
    {
        return '📦';
    }

    public function title(): string
    {
        return "{$this->emoji()} Order #{$this->orderId} shipped via {$this->carrier}";
    }

    public function description(): string
    {
        return "Tracking number: {$this->trackingNumber}. The order has left the warehouse and is on its way.";
    }

    public function fields(): array
    {
        return [
            ['name' => '📦 Order',    'value' => (string) $this->orderId,       'inline' => true],
            ['name' => '🚚 Carrier',  'value' => $this->carrier,                'inline' => true],
            ['name' => '🔗 Tracking', 'value' => $this->trackingNumber,         'inline' => true],
        ];
    }

    public function footerMeta(): array
    {
        return [
            'order_id' => $this->orderId,
            'carrier'  => $this->carrier,
        ];
    }

    public function codeBlocks(): array
    {
        return []; // no fenced code blocks needed for this event
    }

    public function channel(): ?string
    {
        return null; // defer to severity-based routing → 'info' channel
    }
}
```

### What each method provides

| Method | What it contributes |
|---|---|
| `severity()` | Embed color (blue/info), default channel routing |
| `emoji()` | Visual identity — used in `title()` and can prefix field names |
| `title()` | Bold embed heading, includes order ID and carrier |
| `description()` | 1-2 sentence body; no JSON dumps |
| `fields()` | Three inline fields: order ID, carrier, tracking number |
| `footerMeta()` | Bot-parseable keys `order_id` and `carrier` appended to footer |
| `codeBlocks()` | Fenced code-block entries (empty here; override for exception traces, diffs, JSON payloads) |
| `channel()` | `null` = defer to severity routing; return `'info'` to hard-pin |

---

## The Dispatch Site

In whatever service or listener fires the notification:

```php
use App\Howl\Events\OrderShippedEvent;
use Skaisser\Howl\Facades\Howl;

// After persisting the shipment record:
Howl::onDiscord()->send(new OrderShippedEvent(
    orderId: 42,
    trackingNumber: 'TRK-987654',
    carrier: 'FedEx',
    links: [
        'order'    => 'https://shop.example.com/admin/orders/42',
        'tracking' => 'https://www.fedex.com/track/TRK-987654',
    ],
));
```

`->send()` defers to the event's own `severity()` — no terminal-verb mismatch risk. The rendered footer will include:

```
event:order.shipped · severity:info · env:production · trace:01HXY3K... · order_id:42 · carrier:FedEx · 11/05/2026 14:30
```

---

## The Pest Test

Place this in `tests/Events/OrderShippedEventTest.php`:

```php
<?php

use App\Howl\Events\OrderShippedEvent;
use Skaisser\Howl\Facades\Howl;

beforeEach(function () {
    Howl::fake();
});

it('dispatches with info severity', function () {
    $event = new OrderShippedEvent(42, 'TRK-987654', 'FedEx');

    Howl::onDiscord()->send($event);

    Howl::assertSentEvent('order_shipped');
    Howl::assertSent(fn ($payload) => $payload->severity === 'info');
});

it('title contains order ID and carrier', function () {
    Howl::onDiscord()->send(new OrderShippedEvent(42, 'TRK-987654', 'FedEx'));

    Howl::assertSent(fn ($payload) =>
        str_contains($payload->title, 'Order #42') &&
        str_contains($payload->title, 'FedEx')
    );
});

it('fields include order, carrier, and tracking', function () {
    Howl::onDiscord()->send(new OrderShippedEvent(42, 'TRK-987654', 'FedEx'));

    Howl::assertSent(function ($payload) {
        $names = collect($payload->fields)->pluck('name');
        return $names->contains('📦 Order')
            && $names->contains('🚚 Carrier')
            && $names->contains('🔗 Tracking');
    });
});

it('footer includes domain meta keys', function () {
    Howl::onDiscord()->send(new OrderShippedEvent(42, 'TRK-987654', 'FedEx'));

    Howl::assertSent(function ($payload) {
        $footer = $payload->footer['text'] ?? '';
        return str_contains($footer, 'order_id:42')
            && str_contains($footer, 'carrier:FedEx');
    });
});

it('links are rendered in the payload', function () {
    Howl::onDiscord()->send(new OrderShippedEvent(
        orderId: 42,
        trackingNumber: 'TRK-987654',
        carrier: 'FedEx',
        links: [
            'order'    => 'https://shop.example.com/admin/orders/42',
            'tracking' => 'https://www.fedex.com/track/TRK-987654',
        ],
    ));

    Howl::assertSent(fn ($payload) =>
        str_contains($payload->linksField ?? '', 'shop.example.com')
    );
});
```

---

## What the Discord Embed Looks Like

```
┌─ 🔵 (vertical blue color bar) ─────────────────────────────────────┐
│                                                                     │
│  ◉ ℹ️ MyShop · production · info                          14:30    │
│                                                                     │
│  📦 Order #42 shipped via FedEx                                     │
│                                                                     │
│  Tracking number: TRK-987654. The order has left the warehouse.     │
│                                                                     │
│  ┌────────────────┬─────────────────┬───────────────────┐          │
│  │ 📦 Order       │ 🚚 Carrier      │ 🔗 Tracking       │          │
│  │ 42             │ FedEx           │ TRK-987654        │          │
│  └────────────────┴─────────────────┴───────────────────┘          │
│                                                                     │
│  [📦 order](https://shop.../orders/42)                              │
│  [🔗 tracking](https://fedex.com/track/...)                         │
│                                                                     │
│  ─────────────────────────────────────────────────────────────── │
│  event:order.shipped · severity:info · env:production · trace:01H   │
│  · order_id:42 · carrier:FedEx · 11/05/2026 14:30                  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Next Steps

Now adapt this for your domain — replace `OrderShippedEvent` with your own subclass per Section 2 of `extending-templates.md`.

A few starting points:

- **Exception events:** extend with `public readonly \Throwable $exception` and extract `getFile()` + `getLine()` into fields. Consider whether `GenericExceptionEvent` already covers your needs first.
- **Audit events:** if `AuditEvent`'s `actor/action/target` shape fits, use it. Extend only when you need additional domain fields (e.g., `before_state`, `after_state` as structured arrays).
- **Queued jobs:** pass IDs, not models — see §5 "Async-safe constructors" in `extending-templates.md`.
- **Multiple events per domain entity:** one event per business outcome (shipped, delivered, returned) rather than one event with a `$status` string. Keeps `severity()` correct per outcome.
