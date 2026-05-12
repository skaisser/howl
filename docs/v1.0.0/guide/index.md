# Introduction

**Howl** is an open-source Laravel package that sends rich notifications to Discord, Slack, and Telegram from a single, unified API. It was built to solve a common observability problem: scattered notification code calling different webhook URLs, different SDKs, and different payload formats for each target platform.

## The Problem Howl Solves

Most Laravel applications need alerts for:

- **Exceptions and errors** — when a critical exception is caught in production
- **Deployments** — when a new version goes live
- **Cron heartbeats** — confirming that scheduled jobs ran on time
- **Audit trail** — who changed what and when
- **Job failures** — when a queued job exhausts its retry budget

Without Howl, you end up with:
- Discord webhooks wired up in one service class
- Slack notifications in another
- Telegram bots scattered across multiple event listeners
- Three different payload formats to maintain
- No unified testing strategy

**Howl replaces all of that with one package.**

## What Howl Provides

### A single facade for all three drivers

```php
// Same API, regardless of driver
Howl::error(new GenericExceptionEvent($exception));
Howl::info('Deployment complete');
Howl::on('audits')->audit(new AuditEvent($user, 'settings.updated'));
```

### Driver-agnostic routing

Switch between Discord, Slack, and Telegram per-call without touching application code:

```php
Howl::driver('slack')->error($event);
Howl::driver('telegram')->info('Health check OK');
```

Or configure a default driver and forget about it in application code.

### Rich, platform-native payloads

Howl renders the same notification data as a native embed for each driver:

- **Discord** — Embeds with color-coded severity, fields, code blocks, and action buttons
- **Slack** — Block Kit attachments with color sidebars, sections, fields, context footers
- **Telegram** — HTML-formatted messages with bold severity headers and inline keyboard buttons

### Built-in event templates

Seven production-ready event classes so you don't write boilerplate:

| Event | Default Severity |
|---|---|
| `GenericExceptionEvent` | error |
| `GenericInfoEvent` | configurable |
| `AuditEvent` | audit |
| `DeploymentEvent` | deployment |
| `CronHeartbeatEvent` | info |
| `JobRetryExhaustedEvent` | error |
| `ManualOperationEvent` | info |

### First-class testing support

```php
$fake = Howl::fake();

// ... trigger code that calls Howl

$fake->assertSent(fn ($payload) => $payload->severity === 'error');
$fake->assertSentVia('discord');
$fake->assertNothingSent(); // when expected to be silent
```

## Supported Versions

| Package | PHP | Laravel |
|---|---|---|
| v1.0.0 | 8.3, 8.4 | 12, 13 |

Howl requires PHP 8.3+ and Laravel 12 or 13. Older PHP and Laravel versions are not supported.

## License

Howl is open-source software released under the [MIT license](https://github.com/skaisser/howl/blob/main/LICENSE).
