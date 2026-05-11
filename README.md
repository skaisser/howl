<div align="center">

# 🐺 Howl

**Multi-driver Laravel notifier with Discord-first rich embeds, mentions, and bot-parseable metadata.**

*When something goes wrong, your app should howl into the night.*

[![Latest Version](https://img.shields.io/packagist/v/skaisser/howl.svg?style=flat-square&color=ED4245)](https://packagist.org/packages/skaisser/howl)
[![PHP Version](https://img.shields.io/packagist/php-v/skaisser/howl.svg?style=flat-square&color=777BB4)](https://packagist.org/packages/skaisser/howl)
[![Laravel](https://img.shields.io/badge/Laravel-13.x%20%7C%2012.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Total Downloads](https://img.shields.io/packagist/dt/skaisser/howl.svg?style=flat-square&color=57F287)](https://packagist.org/packages/skaisser/howl)
[![License](https://img.shields.io/packagist/l/skaisser/howl.svg?style=flat-square)](LICENSE)

</div>

---

## Why Howl?

Most Laravel notification packages either lock you into Laravel's `Notification` channel pipeline, dump JSON-shaped payloads into Discord, or both. Howl is the **observability howler** — a single fluent API that turns exceptions, job failures, audit events, and deployments into **rich, scannable, bot-parseable embeds**.

Built for production teams who run on Discord (or want to) and need:

- 🚨 **Embeds that read like dashboards**, not stack-trace pastebins
- 🔔 **Smart mentions** — `@oncall` on errors, no accidental `@everyone`
- 🤖 **Bot-parseable footer metadata** — file GitHub issues from Discord with `split(' · ')`
- 🔁 **Driver fallback chain** — Discord down? Telegram catches it
- 🧪 **`Howl::fake()`** — full Laravel-idiomatic test helper
- ⚡ **Sync or queued** — fire-and-forget by default, queue-mode for high volume
- 🎯 **First-class event templates** — seven generic templates (`GenericExceptionEvent`, `GenericInfoEvent`, `AuditEvent`, `DeploymentEvent`, `CronHeartbeatEvent`, `JobRetryExhaustedEvent`, `ManualOperationEvent`); extend `HowlEvent` for your own domain events

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [The Fluent API](#the-fluent-api)
- [Severities & Channels](#severities--channels)
- [First-Class Event Templates](#first-class-event-templates)
- [Mentions, Buttons & Threads](#mentions-buttons--threads)
- [Bot Integration — the Footer Contract](#bot-integration--the-footer-contract)
- [Fallback Drivers](#fallback-drivers)
- [Testing](#testing)
- [Queue Mode](#queue-mode)
- [Configuration](#configuration)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [License](#license)

---

## Installation

```bash
composer require skaisser/howl
```

Publish the config:

```bash
php artisan vendor:publish --tag=howl-config
```

Add the Discord webhook URL to your `.env`:

```env
HOWL_DISCORD_DEFAULT=https://discord.com/api/webhooks/123.../abc...

# Optional: split categories to separate channels
HOWL_DISCORD_ERRORS=https://discord.com/api/webhooks/456.../def...
HOWL_DISCORD_DEPLOYMENTS=https://discord.com/api/webhooks/789.../ghi...

# Optional: ping a role on errors (no @everyone, ever)
HOWL_MENTION_ERRORS=987654321098765432
```

Laravel auto-discovers the service provider and `Howl` facade — you're ready to howl.

---

## Quick Start

### One-liner

```php
use Howl;

Howl::onDiscord()->error('Payment Failed', "Order #{$id} could not be charged.");
```

### Rich path

```php
Howl::onDiscord()
    ->channel('errors')
    ->title('🚨 External API call failed')
    ->description('Connection timed out after 30s calling the payment gateway.')
    ->field('Service', $serviceName, inline: true)
    ->field('Status Code', $statusCode, inline: true)
    ->codeBlock('📁 Source', "{$file}:{$line}", lang: 'php')
    ->mention('role', config('howl.mentions.errors'))
    ->meta('event', 'api.call.failed')
    ->meta('trace', $traceId)
    ->error();
```

---

## The Fluent API

Howl uses a **hybrid builder**: severity methods are terminal (they send and return `void`), while everything else chains.

### Terminal severity methods

```php
->error()     // 🚨 #ED4245 — critical, requires intervention
->warning()   // 🟡 #FFC000 — degraded, not down
->info()      // ℹ️ #4169E1 — general activity
->success()   // ✅ #57F287 — happy path completed
->send($severity = 'info')  // escape hatch
```

### Chainable builder methods

| Method | Purpose |
|---|---|
| `->title($t)` | Embed title (1 line) |
| `->description($d)` / `->body($d)` | Main body (1–2 sentences) |
| `->field($name, $value, bool $inline = true)` | Inline or block field |
| `->codeBlock($name, $code, string $lang = '')` | Field with fenced code block |
| `->channel($c)` | Route to a category (`errors`, `warnings`, etc.) |
| `->mention($type, $id)` | `user`, `role`, `here`, `everyone` |
| `->meta($key, $value)` | Append to the bot-parseable footer |
| `->button($label, $url)` | Link button (Discord style 5) |
| `->attach($path)` | File attachment (multipart) |
| `->thread($threadId)` | Route into an existing Discord thread |
| `->username($override)` | Override the webhook username |
| `->withFallback($driver)` | Override the fallback chain |

---

## Severities & Channels

Channels are **routing categories** — each maps to an embed color, an emoji prefix, and (optionally) a separate webhook URL.

| Channel | Color | Emoji | Use case |
|---|---|---|---|
| `errors` | `#ED4245` | 🚨 | Critical failures, exceptions |
| `warnings` | `#FFC000` | 🟡 | Degraded state, retries, soft failures |
| `info` | `#4169E1` | ℹ️ | General activity, manual ops |
| `success` | `#57F287` | ✅ | Completed jobs, recoveries |
| `audit` | `#9B59B6` | 🔒 | Security, compliance, admin actions |
| `deployments` | `#1ABC9C` | 🚀 | CI/CD, releases, schema migrations |

**Progressive enhancement:** ship with one webhook URL (`HOWL_DISCORD_DEFAULT`), and channels become labeled prefixes in that single feed. When traffic grows, set `HOWL_DISCORD_ERRORS` and the `errors` category splits off — no code changes.

---

## First-Class Event Templates

Howl ships seven typed events that auto-extract fields and work for any Laravel app:

| Event class | Emoji | Auto-extracts | Notes |
|---|---|---|---|
| `GenericExceptionEvent` | 🚨 | message, file:line, sanitized trace | Pass any `\Throwable` |
| `GenericInfoEvent` | ℹ️ | title, description | Parameterized info catch-all |
| `AuditEvent` | 🔒 | actor, action, target, before/after | Generic audit trail |
| `DeploymentEvent` | 🚀 | version, env, commit, branch, duration | v0.2.0: `(version, env, commit, ?branch, ?duration)` |
| `CronHeartbeatEvent` | ⏱️ | schedule name, last_run_at, status | Schedule health checks |
| `JobRetryExhaustedEvent` | 🚨 | job class, exception message, attempt count | Laravel queue failures |
| `ManualOperationEvent` | ℹ️ | operator, action description, affected entity | CLI / admin actions |

```php
use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Events\JobRetryExhaustedEvent;
use Skaisser\Howl\Events\ManualOperationEvent;

// Catch any throwable:
try {
    $client->charge($payment);
} catch (\Throwable $e) {
    Howl::onDiscord()->error(new GenericExceptionEvent($e));
}

// Queue job exhausted retries:
Howl::onDiscord()->send(new JobRetryExhaustedEvent($job, $exception));

// Manual admin operation:
Howl::onDiscord()->send(new ManualOperationEvent('admin@example.com', 'Reprocessed failed orders', 'batch:1234'));
```

### Extending the Event Layer

When the generic templates don't fit your domain (e.g. you need 2+ domain-specific fields with a custom emoji), extend `HowlEvent` directly in your app:

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

    public function severity(): string { return 'info'; }
    public function emoji(): string { return '📦'; }
    public function title(): string { return "{$this->emoji()} Order #{$this->orderId} shipped via {$this->carrier}"; }
    public function description(): string { return "Tracking number: {$this->trackingNumber}."; }

    public function fields(): array
    {
        return [
            ['name' => '📦 Order',    'value' => (string) $this->orderId,  'inline' => true],
            ['name' => '🚚 Carrier',  'value' => $this->carrier,           'inline' => true],
            ['name' => '🔗 Tracking', 'value' => $this->trackingNumber,    'inline' => true],
        ];
    }

    public function footerMeta(): array { return ['order_id' => $this->orderId, 'carrier' => $this->carrier]; }
    public function channel(): ?string { return null; }
}
```

Dispatch with `->send()` to defer to the event's own `severity()`:

```php
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

The 8-method contract (`severity`, `title`, `description`, `fields`, `emoji`, `footerMeta`, `channel`, `codeBlocks`) plus the universal `__construct(array $links = [], array $meta = [])` are documented in full in:

- **[docs/extending-templates.md](docs/extending-templates.md)** — contract walkthrough, `links` convention, footer extension pattern, common gotchas, testing recipes
- **[docs/example-app-template.md](docs/example-app-template.md)** — complete `OrderShippedEvent` worked example with Pest tests and dispatch site

The builder remains the escape hatch for one-off cases where a full subclass is not warranted.

---

## Mentions, Buttons & Threads

### Mentions (with `@everyone` safety)

Howl uses Discord's `allowed_mentions` payload field, so you can't accidentally ping the whole server:

```php
->mention('user', '123456789012345678')   // @user
->mention('role', '987654321098765432')   // @role (oncall, devops…)
->mention('here')                          // @here (channel only)
->mention('everyone')                      // @everyone (requires explicit opt-in)
```

### Link buttons

```php
->button('View order', "https://yourapp.com/orders/{$id}")
->button('View service logs', "https://yourapp.com/logs?service={$service}")
```

### Threads

```php
->thread($threadId)  // routes message into ?thread_id=...
```

### File attachments

```php
->attach(storage_path("logs/stacktrace-{$traceId}.log"))
```

---

## Bot Integration — the Footer Contract

Every embed includes a **pipe-delimited footer** that's trivially parseable from a Discord bot:

```
event:api.call.failed · severity:error · env:production · trace:01HXY3K… · 11/05/2026 06:57
```

Consume from a Discord bot in three lines:

```js
const meta = embed.footer.text.split(' · ').reduce((acc, kv) => {
  const [k, v] = kv.split(':');
  acc[k] = v;
  return acc;
}, {});
// → { event, severity, env, trace, ... }
```

This is what lets a downstream bot **auto-file GitHub issues** from error embeds — `event` maps to an issue label, `trace` dedupes, `severity` decides whether to file at all.

---

## Fallback Drivers

When Discord 503's, fail over to Telegram (or any other configured driver):

```php
// Global default in config/howl.php
'driver' => 'discord',
'fallback' => 'telegram',

// Or per-call
Howl::onDiscord()->withFallback('telegram')->error(...);
```

- Triggers **only on transport failure** (non-2xx HTTP, timeout, network)
- If the whole chain fails, logs everything and **returns silently** — never throws
- Queue-failure events skip the queue entirely to avoid recursive loops

---

## Testing

`Howl::fake()` mirrors Laravel's `Notification::fake()` idiom — same shape, same intuitions:

```php
use Howl;

beforeEach(fn () => Howl::fake());

it('howls when payment fails', function () {
    $this->artisan('payment:charge', ['order' => 42])->assertFailed();

    Howl::assertSent(fn ($payload) => str_contains($payload->title, 'payment'));
    Howl::assertSentOnChannel('errors', fn ($p) => $p->severity === 'error');
    Howl::assertSentEvent('generic_exception');
});

it('stays quiet on happy path', function () {
    $this->artisan('payment:charge', ['order' => 1])->assertSuccessful();
    Howl::assertNothingSent();
});
```

### The five assertions

| Assertion | What it checks |
|---|---|
| `Howl::assertSent($callback)` | At least one payload where `$callback($payload)` returns truthy |
| `Howl::assertSentOnChannel($channel, $callback)` | At least one payload on `$channel` matching the callback |
| `Howl::assertSentEvent($eventName)` | A snake_case event name (e.g. `'generic_exception'`) appears in `$payload->meta['event']`. Pass the snake_case basename, NOT the FQCN. |
| `Howl::assertNothingSent()` | Zero payloads were dispatched in this test |
| `Howl::sent($channel = null)` | Return array of captured payloads, optionally filtered by channel |

### `Howl::fake()` patterns

**Swap once in `beforeEach`, assert anywhere:**

```php
beforeEach(fn () => Howl::fake());

it('sends to the errors channel on failure', function () {
    triggerSomethingThatFails();

    Howl::assertSentOnChannel('errors', fn ($p) => $p->severity === 'error');
});
```

**Inspect the captured payload directly:**

```php
Howl::fake();

Howl::onDiscord('audit')->audit('Admin impersonated user', 'User #42 by admin #1');

$payloads = Howl::sent('audit');
expect($payloads)->toHaveCount(1);
expect($payloads[0]->title)->toBe('Admin impersonated user');
```

**Assert nothing was sent on the happy path:**

```php
Howl::fake();

runHappyPathCode();

Howl::assertNothingSent();
```

### Using `tests/Fixtures/embeds/` seeds

The `tests/Fixtures/embeds/` directory ships three pre-validated embed JSON payloads as canonical references. Use them in your own tests:

| File | Scenario |
|---|---|
| `info_deployment-started.json` | Deployment started (info, blue) |
| `success_recovery.json` | Operation recovered (success, green) |
| `audit_admin-impersonation.json` | Admin impersonation audit trail (purple) |

```php
it('color matches the canonical embed fixture', function () {
    $fixture = json_decode(
        file_get_contents(__DIR__.'/../Fixtures/embeds/success_recovery.json'),
        true,
    );

    $body = EmbedBuilder::build($myPayload);

    expect($body['embeds'][0]['color'])->toBe($fixture['embeds'][0]['color']);
});
```

### HTTP response fixtures

`tests/Fixtures/` also provides helper files for mocking Discord's HTTP responses:

| File | Simulates |
|---|---|
| `discord_204.json` | Discord 204 No Content (success) |
| `discord_429.json` | Discord rate-limit response |
| `discord_500.json` | Discord 500 Internal Server Error |
| `discord_timeout.php` | Returns a closure that throws `ConnectionException` |

```php
use Illuminate\Support\Facades\Http;

it('treats 204 as a successful send', function () {
    Http::fake(['*' => Http::response('', 204)]);

    $result = (new DiscordDriver)->send($payload);

    expect($result)->toBeTrue();
});

it('swallows connection timeouts and returns false', function () {
    $timeout = require __DIR__.'/../Fixtures/discord_timeout.php';
    Http::fake(['*' => fn () => $timeout()]);

    $result = (new DiscordDriver)->send($payload);

    expect($result)->toBeFalse();
});
```

---

## Queue Mode

Sync by default (zero queue dependency). Flip one config key to queue every send:

```php
// config/howl.php
'queue' => env('HOWL_QUEUE', true),
'queue_connection' => 'redis',
'queue_name' => 'notifications',
```

- 3 retries with exponential backoff (1s → 4s → 16s)
- Queue-failure events automatically force-sync to avoid recursive loops

---

## Configuration

The full `config/howl.php` is published via `vendor:publish --tag=howl-config`. Highlights:

```php
return [
    'driver'   => env('HOWL_DRIVER', 'discord'),
    'fallback' => env('HOWL_FALLBACK', null),
    'queue'    => env('HOWL_QUEUE', false),

    'app_name' => env('HOWL_APP_NAME', env('APP_NAME', 'App')),
    'username_format' => '{severity_emoji} {app} · {env} · {channel}',
    // → "🚨 MyApp · production · errors"

    'drivers' => [
        'discord' => [
            'webhook_url' => env('HOWL_DISCORD_DEFAULT'),
            'channels' => [
                'errors'      => env('HOWL_DISCORD_ERRORS'),
                'warnings'    => env('HOWL_DISCORD_WARNINGS'),
                'info'        => env('HOWL_DISCORD_INFO'),
                'audit'       => env('HOWL_DISCORD_AUDIT'),
                'deployments' => env('HOWL_DISCORD_DEPLOYMENTS'),
            ],
            'timeout' => 10,
        ],
    ],

    'skip_environments' => ['testing'],
];
```

### Spatie Settings integration

Already storing webhooks in `spatie/laravel-settings`? Override config in `AppServiceProvider::boot()`:

```php
config(['howl.drivers.discord.webhook_url' => settings('howl_discord_url')]);
```

No hard dependency — Howl never imports Spatie.

---

## What an Embed Looks Like

```
┌─ 🔴 (vertical red color bar) ──────────────────────────────────┐
│                                                                │
│  ◉ 🚨 MyApp · production · errors                     06:57   │
│                                                                │
│  🚨 External API call failed                                   │
│                                                                │
│  Connection timed out after 30s while calling the              │
│  payment gateway charge endpoint.                              │
│                                                                │
│  ┌──────────────────┬──────────────────────┐                  │
│  │ Severity         │ ERROR                │                  │
│  │ Service          │ PaymentGateway       │                  │
│  │ Status Code      │ 504                  │                  │
│  │ Attempt          │ 3                    │                  │
│  └──────────────────┴──────────────────────┘                  │
│                                                                │
│  📁 Source                                                     │
│  ```php                                                        │
│  app/Services/PaymentGatewayClient.php:142                     │
│  ```                                                           │
│                                                                │
│  🔗 Trace ID                                                   │
│  `01JXAMPLE`                                                   │
│                                                                │
│  [View service logs] [View order]                              │
│                                                                │
│  ──────────────────────────────────────────────────────────── │
│  event:api.call.failed · severity:error                        │
│  · env:production · trace:01JXAMPLE… · 11/05/2026 06:57       │
└────────────────────────────────────────────────────────────────┘
```

---

## Roadmap

- **v0.1.0** — Discord driver, 4 generic event templates, `Howl::fake()`, queue mode, fallback chain
- **v0.2.0** — Per-channel webhook splitting verified in the wild, rate-limit header parsing
- **v0.3.0** — Avatar customization per severity
- **v1.0.0** — API frozen, semver-stable
- **v2.0.0** — `TelegramDriver`, `SlackDriver` (interface and namespace already reserved)
- **v3.0.0** *(maybe)* — Discord **bot** with gateway connection: 👍-to-ack, `/howl` slash commands

---

## Contributing

PRs welcome. Howl is opinionated but not closed — if a builder method or event template would simplify your codebase, open an issue first to discuss the shape.

```bash
git clone git@github.com:skaisser/howl.git
cd howl
composer install
vendor/bin/pest
vendor/bin/pint
```

Tested locally on PHP 8.3 / 8.4 × Laravel 12 / 13. Laravel 13 is the primary target. Run `vendor/bin/pest --parallel --processes=10` before opening a PR.

---

## License

MIT © [Shirleyson Kaisser](https://github.com/skaisser)

---

<div align="center">

*Built for production Laravel apps. Tested across PHP 8.3–8.4 × Laravel 12–13 (Laravel 13 primary).*

**If a backronym is ever needed:** **H**ealthy **O**utbound **W**ebhook **L**ayer.

</div>
