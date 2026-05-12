# Discord Driver

The Discord driver sends notifications via webhook URLs to Discord channels. It renders rich [Discord embed](https://discord.com/developers/docs/resources/channel#embed-object) objects with color-coded severity, fields, code blocks, action buttons, and mentions.

## Setup

### Step 1: Create a webhook in Discord

1. Open your Discord server settings → **Integrations** → **Webhooks**
2. Click **New Webhook**
3. Name it (e.g. `Howl Notifications`)
4. Select the channel to post to
5. Click **Copy Webhook URL**

### Step 2: Configure your `.env`

```env
HOWL_DRIVER=discord
HOWL_DISCORD_DEFAULT=https://discord.com/api/webhooks/123456/your-token-here
```

### Step 3: Verify

```bash
php artisan tinker
```

```php
Howl::info('Discord driver connected!');
```

## Thread Routing (Forum Channels)

Discord forum channels and text channels with threads allow routing notifications to specific threads by ID. Howl appends `?thread_id=N` to the webhook URL when a thread ID is configured.

### Thread ID lookup

To get a thread ID:
1. Enable **Developer Mode** in Discord (User Settings → Advanced → Developer Mode)
2. Right-click the thread → **Copy Thread ID**

### Configure thread IDs

```env
HOWL_DISCORD_THREAD_ERRORS=1234567890123456789
HOWL_DISCORD_THREAD_WARNINGS=2345678901234567890
HOWL_DISCORD_THREAD_INFO=3456789012345678901
HOWL_DISCORD_THREAD_AUDIT=4567890123456789012
HOWL_DISCORD_THREAD_DEPLOYMENTS=5678901234567890123
```

Or in `config/howl.php`:

```php
'drivers' => [
    'discord' => [
        'threads' => [
            'errors'      => env('HOWL_DISCORD_THREAD_ERRORS'),
            'warnings'    => env('HOWL_DISCORD_THREAD_WARNINGS'),
            'info'        => env('HOWL_DISCORD_THREAD_INFO'),
            'audit'       => env('HOWL_DISCORD_THREAD_AUDIT'),
            'deployments' => env('HOWL_DISCORD_THREAD_DEPLOYMENTS'),
        ],
    ],
],
```

### How thread routing works

When Howl resolves a channel name (e.g. `'errors'`):

1. **Per-category webhook** — checks `drivers.discord.channels.errors` first. If set, uses that dedicated webhook URL (no thread_id appended).
2. **Thread routing** — checks `drivers.discord.threads.errors`. If set, appends `?thread_id=N` to the default webhook URL.
3. **Channel root** — uses `drivers.discord.webhook_url` as-is (posts to the channel root, no thread).

## Per-Category Webhook URLs (Progressive Enhancement)

For greater isolation, you can assign a dedicated webhook URL per Howl channel. This is optional — thread IDs on a single webhook work well for most setups.

```env
HOWL_DISCORD_ERRORS=https://discord.com/api/webhooks/111/errors-webhook-token
HOWL_DISCORD_DEPLOYMENTS=https://discord.com/api/webhooks/222/deployments-webhook-token
```

Per-category webhooks take priority over thread routing.

## Embed Format

Discord notifications render as an embed with:

| Field | Source |
|---|---|
| **Author** | Emoji + event title |
| **Description** | Event description |
| **Color** | Severity color (`config('howl.colors.{severity}')`) |
| **Fields** | Inline and block fields from the builder or event |
| **Code blocks** | Rendered as Discord code blocks in the embed description |
| **Footer** | `app_name · app_env · channel · timestamp` |
| **Username** | From `username_format` template |

## Mentions

Add mentions to notify specific users or roles. Mentions appear in the `content` field (outside the embed, triggering push notifications).

```php
// Mention a specific user
Howl::on('errors')
    ->mention('user', '123456789012345678')
    ->error($event);

// Mention a role
Howl::on('errors')
    ->mention('role', '987654321098765432')
    ->error($event);

// Ping @here (all online members in the channel)
Howl::on('errors')->mention('here')->error($event);

// Ping @everyone
Howl::on('errors')->mention('everyone')->error($event);
```

Mention ID format in Discord messages:
- Users: `<@!userId>`
- Roles: `<@&roleId>`
- Here: `@here`
- Everyone: `@everyone`

You can also configure static per-channel mentions in `config/howl.php`:

```php
'mentions' => [
    'errors' => env('HOWL_MENTION_ERRORS'), // e.g. '<@&ONCALL_ROLE_ID>'
],
```

## Attachments

Send files (logs, screenshots, exports) alongside the notification:

```php
Howl::on('errors')
    ->attach('/tmp/error-export.csv')
    ->error($event);
```

Discord attachment size limits:
- **Free servers**: 25 MB per file
- **Nitro Basic**: 50 MB per file
- **Nitro**: 500 MB per file

## Avatar URL

Customize the webhook bot's avatar:

```env
HOWL_DISCORD_AVATAR_URL=https://your-cdn.com/howl-avatar.png
```

## HTTP Details

- Discord returns **HTTP 204 No Content** on successful webhook POST (not 200). The driver correctly treats `204` as success.
- Timeout defaults to 10 seconds (`HOWL_DISCORD_TIMEOUT`).

## Custom Channel Names

Howl's channel names don't have to be `errors`/`warnings`/etc. You can define any string as a channel name and configure it in the threads or channels map:

```php
'threads' => [
    'payments' => env('HOWL_DISCORD_THREAD_PAYMENTS'),
    'auth'     => env('HOWL_DISCORD_THREAD_AUTH'),
],
```

Then route per-call:

```php
Howl::on('payments')->error($paymentFailedEvent);
```
