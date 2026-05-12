# Installation

## Requirements

- PHP **8.3** or **8.4**
- Laravel **12** or **13**
- Composer 2.x

## Install via Composer

```bash
composer require skaisser/howl
```

Laravel's package auto-discovery registers the service provider and `Howl` facade automatically. No manual registration needed.

## Publish the Configuration

```bash
php artisan vendor:publish --tag=howl-config
```

This creates `config/howl.php` in your application. Review it — you'll configure your driver choice and webhook credentials here.

## Environment Variables

Add the appropriate environment variables to your `.env` file based on the driver(s) you intend to use.

### Discord (default driver)

```env
HOWL_DRIVER=discord
HOWL_DISCORD_DEFAULT=https://discord.com/api/webhooks/YOUR_ID/YOUR_TOKEN

# Optional: per-severity thread IDs for forum channels
HOWL_DISCORD_THREAD_ERRORS=123456789
HOWL_DISCORD_THREAD_WARNINGS=234567890
HOWL_DISCORD_THREAD_INFO=345678901
HOWL_DISCORD_THREAD_AUDIT=456789012
HOWL_DISCORD_THREAD_DEPLOYMENTS=567890123
```

### Slack

```env
HOWL_DRIVER=slack
HOWL_SLACK_BOT_TOKEN=xoxb-your-bot-token
HOWL_SLACK_DEFAULT_CHANNEL=C0123ABCDEF

# Optional: per-channel routing
HOWL_SLACK_CHANNEL_ERRORS=C0123ABCDEF
HOWL_SLACK_CHANNEL_WARNINGS=C0234BCDEF0
HOWL_SLACK_CHANNEL_DEPLOYMENTS=C0345CDEF01
```

### Telegram

```env
HOWL_DRIVER=telegram
HOWL_TELEGRAM_BOT_TOKEN=123456789:ABC-your-bot-token
HOWL_TELEGRAM_CHAT_ID=-1001234567890

# Optional: per-severity Forum topic IDs
HOWL_TELEGRAM_THREAD_ERRORS=1
HOWL_TELEGRAM_THREAD_WARNINGS=2
HOWL_TELEGRAM_THREAD_INFO=3
HOWL_TELEGRAM_THREAD_AUDIT=4
HOWL_TELEGRAM_THREAD_DEPLOYMENTS=5
```

### Common optional settings

```env
# Channel defaults
HOWL_DEFAULT_CHANNEL=errors
HOWL_BACKUP_CHANNEL=
HOWL_CHANNEL_MODE=failover   # failover or fan_out

# Queue mode (async dispatch)
HOWL_QUEUE=false
HOWL_QUEUE_CONNECTION=redis
HOWL_QUEUE_NAME=notifications

# Rate limiting (requires Redis + registered RateLimiter)
HOWL_RATE_LIMITER_KEY=

# App branding in embed footers
HOWL_APP_NAME=MyApp
HOWL_APP_ENV=production
```

## Sanity Check

After configuring your `.env`, verify Howl can reach your driver:

```bash
php artisan tinker
```

```php
Howl::info('Hello from Howl!');
```

You should see a notification appear in your configured Discord channel, Slack workspace, or Telegram chat within a few seconds.

::: tip Testing environments
Howl skips sending in environments listed under `skip_environments` in `config/howl.php`. The default is `['testing']`, which means `php artisan test` runs will never fire real webhooks.
:::

## Next Steps

- [Quick Start](/next/guide/quick-start) — common usage patterns
- [Configuration Reference](/next/configuration/reference) — all config keys documented
- [Discord Driver](/next/drivers/discord) — Discord-specific setup
- [Slack Driver](/next/drivers/slack) — Slack App setup and OAuth
- [Telegram Driver](/next/drivers/telegram) — Bot and Forum topic setup
