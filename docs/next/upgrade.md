# Upgrade Guide

## Upgrading from v0.x to v1.0

This guide covers upgrading a v0.x howl installation to v1.0.0. The upgrade is **low-risk** for most applications — one breaking change (method rename) affects callers that used the old `Howl::onDiscord()` / `Howl::onSlack()` / `Howl::onTelegram()` facade shortcuts. Everything else is purely additive.

---

## Updating Dependencies

Update the constraint in `composer.json` to require v1:

```bash
composer require skaisser/howl:^1.0
```

Then re-publish the config if you want the new v1 config keys (Slack, Telegram, rate limiter):

```bash
php artisan vendor:publish --tag=howl-config --force
```

> **Tip:** Review the published config against your existing `config/howl.php` before overwriting — add only the new keys you need rather than replacing the whole file.

---

## High Impact Changes

### `Howl::onDiscord()`, `Howl::onSlack()`, `Howl::onTelegram()` removed

**Impact: High — any caller using these methods gets a `BadMethodCallException` at runtime.**

These methods were removed in favor of the new driver-agnostic API (`Howl::on()` + `Howl::driver()`). The migration is a mechanical search-and-replace.

#### Migration recipe

**Old code (v0.x):**
```php
Howl::onDiscord('errors')->error($event);
Howl::onSlack('audits')->audit($event);
Howl::onTelegram('deployments')->deployment($event);
```

**New code (v1.0):**
```php
// Channel routing — use Howl::on() regardless of driver
Howl::on('errors')->error($event);

// Same channel routing — driver is set in config or via ->driver()
Howl::on('audits')->audit($event);
Howl::on('deployments')->deployment($event);

// To explicitly target a specific driver per-call:
Howl::driver('slack')->on('audits')->audit($event);
Howl::driver('telegram')->on('deployments')->deployment($event);
```

#### Automated codemod

Run the following from your project root to replace all occurrences automatically:

**macOS (BSD sed):**
```bash
find app -name "*.php" -exec sed -i '' \
  -e 's/Howl::onDiscord(/Howl::on(/g' \
  -e 's/Howl::onSlack(/Howl::on(/g' \
  -e 's/Howl::onTelegram(/Howl::on(/g' \
  {} +
```

**Linux (GNU sed):**
```bash
find app -name "*.php" -exec sed -i \
  -e 's/Howl::onDiscord(/Howl::on(/g' \
  -e 's/Howl::onSlack(/Howl::on(/g' \
  -e 's/Howl::onTelegram(/Howl::on(/g' \
  {} +
```

After running the codemod, verify with:
```bash
grep -rn "Howl::on\(Discord\|Slack\|Telegram\)" app/
```
The above should return zero results.

---

## Medium Impact Changes

### New environment variables

v1.0.0 introduces several new env vars for Slack and Telegram drivers, plus channel modes and rate limiting. If you only use Discord, you can ignore the Slack/Telegram vars. All new vars have sensible defaults.

| New var | Default | Notes |
|---|---|---|
| `HOWL_DEFAULT_CHANNEL` | `'errors'` | Replaces the hard-coded default channel |
| `HOWL_BACKUP_CHANNEL` | `null` | Optional second channel for failover/fan-out |
| `HOWL_CHANNEL_MODE` | `'failover'` | `'failover'` or `'fan_out'` |
| `HOWL_RATE_LIMITER_KEY` | `null` | Key for `RateLimitedWithRedis` middleware |
| `HOWL_SLACK_BOT_TOKEN` | — | Required for Slack driver |
| `HOWL_SLACK_DEFAULT_CHANNEL` | — | Slack fallback channel ID |
| `HOWL_TELEGRAM_BOT_TOKEN` | — | Required for Telegram driver |
| `HOWL_TELEGRAM_CHAT_ID` | — | Telegram supergroup chat ID |

### New config keys

`config/howl.php` gains the following top-level keys:

```php
'channel'          => env('HOWL_DEFAULT_CHANNEL', 'errors'),
'channel_backup'   => env('HOWL_BACKUP_CHANNEL', null),
'channel_mode'     => env('HOWL_CHANNEL_MODE', 'failover'),
'rate_limiter_key' => env('HOWL_RATE_LIMITER_KEY', null),
```

And two new driver blocks:
```php
'drivers' => [
    'discord'  => [ /* unchanged */ ],
    'slack'    => [ 'bot_token' => ..., 'channels' => [...] ],
    'telegram' => [ 'bot_token' => ..., 'chat_id' => ..., 'threads' => [...] ],
],
```

---

## Low Impact / Additive Changes

These are new features added in v1.0.0. Existing code does not need to change to adopt them.

### Direct severity verbs

```php
// v1.0 — severity as a direct facade method, no ->on() chain required
Howl::error($event);
Howl::info('Scheduled job completed');
Howl::deployment($event);
```

### Fluent builder entry points

```php
Howl::on('errors')->error($event);           // channel routing
Howl::driver('slack')->info('System OK');     // driver override
Howl::driver('telegram')->on('audits')->audit($event); // both
```

### Channel failover and fan-out

Set `HOWL_BACKUP_CHANNEL` + `HOWL_CHANNEL_MODE` to enable automatic failover or simultaneous fan-out across two channels. See [Failover & Fan-Out](/next/configuration/failover-and-fan-out).

### Rate-limit middleware

Set `HOWL_RATE_LIMITER_KEY` and register a `RateLimiter::for()` in `AppServiceProvider` to cap notification throughput. See [Rate Limiting](/next/configuration/rate-limiting).

### HowlFake per-driver assertions

```php
$fake->assertSentVia('slack', fn ($p) => $p->severity === 'info');
$fake->assertSentViaNothing('telegram');
$payloads = $fake->sentVia('discord');
```

### Pest 3/4 compatibility

A single howl v1.0.0 install works on Laravel 12 (ships with Pest 3) **and** Laravel 13 (ships with Pest 4) — Composer resolves the correct Pest version automatically. No consumer test-suite changes are required.

---

## Version support table

| howl version | PHP | Laravel | Pest |
|---|---|---|---|
| **v1.0.0** | 8.3, 8.4 | 12, 13 | 3 (L12), 4 (L13) |
| v0.2.x | 8.2, 8.3 | 11, 12 | — |
| v0.1.0 | 8.1+ | 10+ | — |

Pre-1.0 releases (`v0.1.0`, `v0.2.0`, `v0.2.1`) remain on Packagist for any pinned consumers but are superseded by v1.0.0.
