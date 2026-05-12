# Slack Driver

The Slack driver sends notifications via the `chat.postMessage` API using a Slack Bot OAuth token. It renders [Block Kit](https://api.slack.com/block-kit) attachments with a color-coded sidebar, rich text sections, fields, context footers, buttons, and file attachments.

## Setup

### Step 1: Create a Slack App

1. Go to [https://api.slack.com/apps](https://api.slack.com/apps)
2. Click **Create New App** → **From scratch**
3. Name your app (e.g. `Howl Notifier`) and choose your workspace

### Step 2: Configure OAuth Scopes

In your app settings → **OAuth & Permissions** → **Bot Token Scopes**, add:

| Scope | Required for |
|---|---|
| `chat:write` | Sending messages |
| `files:write` | Sending file attachments via `->attach()` |

### Step 3: Install to Workspace

1. In **OAuth & Permissions**, click **Install to Workspace**
2. Click **Allow**
3. Copy the **Bot User OAuth Token** (starts with `xoxb-`)

### Step 4: Find Channel IDs

Channel IDs (not names) are required. To get a channel ID:

1. Right-click the channel in Slack sidebar → **View channel details**
2. Scroll to the bottom — you'll see the Channel ID (e.g. `C0123ABCDEF`)

Or via URL: open the channel in Slack Web → the URL ends with `/CXXXXXXXXXX` — that's the ID.

**Important:** The bot must be **added to the channel** before it can post. In the channel, type `/invite @YourBotName`.

### Step 5: Configure `.env`

```env
HOWL_DRIVER=slack
HOWL_SLACK_BOT_TOKEN=xoxb-your-bot-oauth-token
HOWL_SLACK_DEFAULT_CHANNEL=C0123ABCDEF

# Optional: per-Howl-channel routing
HOWL_SLACK_CHANNEL_ERRORS=C0123ABCDEF
HOWL_SLACK_CHANNEL_WARNINGS=C0234BCDEF0
HOWL_SLACK_CHANNEL_INFO=C0345CDEF01
HOWL_SLACK_CHANNEL_AUDIT=C0456DEF012
HOWL_SLACK_CHANNEL_DEPLOYMENTS=C0567EF0123
```

### Step 6: Verify

```bash
php artisan tinker
```

```php
Howl::info('Slack driver connected!');
```

## Block Kit Format

Slack notifications render as Block Kit `attachments` with:

| Element | Source |
|---|---|
| **Color sidebar** | Severity color (hex) |
| **Header** | `{emoji} {severity} — {title}` |
| **Body section** | Event description (Markdown) |
| **Fields** | Key/value inline pairs |
| **Code blocks** | Rendered as ` ```code``` ` in description |
| **Context block** | Footer with app name, env, channel, timestamp |
| **Actions block** | URL buttons from `->button()` |

## Channel Routing

Slack channel routing uses the `channels` map in `config/howl.php`:

```php
'drivers' => [
    'slack' => [
        'channels' => [
            'errors'      => env('HOWL_SLACK_CHANNEL_ERRORS'),
            'warnings'    => env('HOWL_SLACK_CHANNEL_WARNINGS'),
            'info'        => env('HOWL_SLACK_CHANNEL_INFO'),
            'audit'       => env('HOWL_SLACK_CHANNEL_AUDIT'),
            'deployments' => env('HOWL_SLACK_CHANNEL_DEPLOYMENTS'),
        ],
        'default_channel' => env('HOWL_SLACK_DEFAULT_CHANNEL'),
    ],
],
```

Resolution priority: `channels.$name` → `default_channel`.

## Mentions

Slack mention syntax differs from Discord. Howl translates abstract mention types to Slack-native syntax:

| Howl `mention()` type | Slack Syntax |
|---|---|
| `here` | `<!here>` |
| `everyone` | `<!channel>` |
| `role` with ID | `<!subteam^ID>` |
| `user` with ID | `<@USER_ID>` |

```php
// Ping @here
Howl::on('errors')->mention('here')->error($event);

// Mention a Slack user (use their Slack member ID, not username)
Howl::on('errors')->mention('user', 'U0123456789')->error($event);

// Mention a Slack User Group (subteam)
Howl::on('errors')->mention('role', 'S0123456789')->error($event);
```

## Attachments

Send files alongside the notification using Slack's `files.upload` v2 API (3-step flow):

1. Get an upload URL from `files.getUploadURLExternal`
2. Upload the file bytes to the returned URL
3. Complete the upload with `files.completeUploadExternal`

This happens automatically when you call `->attach()`:

```php
Howl::on('errors')
    ->attach('/tmp/error-report.csv')
    ->error($event);
```

Slack file size limits: 1 GB per file (practical limit depends on workspace plan). The `files:write` scope must be added to your Slack App.

## Buttons

Add URL buttons to Slack messages via Block Kit `actions` blocks:

```php
Howl::on('errors')
    ->button('View Error', 'https://app.example.com/errors/123')
    ->button('Acknowledge', 'https://app.example.com/errors/123/ack')
    ->error($event);
```

Slack buttons are URL-type actions only (no interactive callbacks without Slack Events API).

## Custom Channel Names

Like all Howl drivers, you can define custom channel names:

```php
// config/howl.php
'drivers' => [
    'slack' => [
        'channels' => [
            'payments' => env('HOWL_SLACK_CHANNEL_PAYMENTS'),
            'security' => env('HOWL_SLACK_CHANNEL_SECURITY'),
        ],
    ],
],
```

```php
Howl::on('security')->warning($suspiciousLoginEvent);
```
