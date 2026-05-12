<div align="center">

# 🐺 Howl

**Multi-driver Laravel notifier — Discord, Slack, and Telegram with rich embeds.**

*When something goes wrong, your app should howl into the night.*

[![Latest Version](https://img.shields.io/packagist/v/skaisser/howl.svg?style=flat-square&color=ED4245)](https://packagist.org/packages/skaisser/howl)
[![PHP Version](https://img.shields.io/packagist/php-v/skaisser/howl.svg?style=flat-square&color=777BB4)](https://packagist.org/packages/skaisser/howl)
[![Laravel](https://img.shields.io/badge/Laravel-13.x%20%7C%2012.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Tests](https://github.com/skaisser/howl/actions/workflows/test.yml/badge.svg)](https://github.com/skaisser/howl/actions/workflows/test.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/skaisser/howl.svg?style=flat-square&color=57F287)](https://packagist.org/packages/skaisser/howl)
[![License](https://img.shields.io/packagist/l/skaisser/howl.svg?style=flat-square)](LICENSE)

**[Full documentation at howl.skaisser.dev →](https://howl.skaisser.dev)**

</div>

---

## Why Howl?

A single driver-agnostic API for Discord, Slack, and Telegram. Rich embeds, channel failover, queue-aware dispatch, 100% test coverage, and full docs.

- One fluent API for all three drivers — switch per-call without changing business logic
- Rich embeds: Block Kit (Slack), Discord embeds, Telegram HTML — fields, code blocks, buttons, attachments
- Seven built-in event templates: exceptions, deployments, audits, cron heartbeats, job failures, and more
- Channel failover and fan-out — automatic backup channel dispatch
- HowlFake test helper — assert notifications without real HTTP calls
- Queue-aware with exponential backoff and opt-in Redis rate limiting

---

## Installation

```bash
composer require skaisser/howl
php artisan vendor:publish --tag=howl-config
```

Add your driver credentials to `.env`:

```env
HOWL_DRIVER=discord
HOWL_DISCORD_DEFAULT=https://discord.com/api/webhooks/...
```

---

## Quick Start

```php
use Skaisser\Howl\Facades\Howl;
use Skaisser\Howl\Events\GenericExceptionEvent;

// Direct severity verb — uses config('howl.driver') by default
Howl::error(new GenericExceptionEvent($exception));
Howl::info('Scheduled job completed');

// Channel routing
Howl::on('audits')->audit($event);

// Per-call driver override
Howl::driver('slack')->info('Deploy succeeded');

// In tests — no real HTTP calls made
$fake = Howl::fake();
Howl::error('Something broke');
$fake->assertSent(fn ($p) => $p->severity === 'error');
```

---

## Documentation

The full documentation is at **[howl.skaisser.dev](https://howl.skaisser.dev)** and covers:

- [Installation & Quick Start](https://howl.skaisser.dev/v1.0.0/guide/installation)
- [Discord driver](https://howl.skaisser.dev/v1.0.0/drivers/discord) · [Slack driver](https://howl.skaisser.dev/v1.0.0/drivers/slack) · [Telegram driver](https://howl.skaisser.dev/v1.0.0/drivers/telegram)
- [Configuration reference](https://howl.skaisser.dev/v1.0.0/configuration/reference)
- [HowlFake testing](https://howl.skaisser.dev/v1.0.0/testing/howl-fake)
- [API reference](https://howl.skaisser.dev/v1.0.0/reference/api)
- [Upgrade guide v0.x → v1.0](https://howl.skaisser.dev/v1.0.0/upgrade)

LLM-friendly: [llms.txt](https://howl.skaisser.dev/llms.txt) · [llms-full.txt](https://howl.skaisser.dev/llms-full.txt)

---

## Contributing

Issues and pull requests are welcome at [github.com/skaisser/howl](https://github.com/skaisser/howl).

## License

MIT — see [LICENSE](LICENSE).
