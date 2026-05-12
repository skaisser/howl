# Telegram Driver

The Telegram driver sends notifications via the [Telegram Bot API](https://core.telegram.org/bots/api) using `sendMessage` (HTML parse_mode), `sendDocument`, and `sendPhoto`. It supports Forum topic routing, mentions, file attachments, and URL buttons via inline keyboards.

## Setup

### Step 1: Create a Telegram Bot

1. Open Telegram and search for `@BotFather`
2. Send `/newbot` and follow the prompts
3. Choose a name (display name) and a username (must end in `bot`)
4. BotFather replies with your **bot token** (format: `123456789:ABC-DEF...`)

### Step 2: Set up a Supergroup with Forum Mode

Howl uses Forum topics for channel routing. This requires a **supergroup** with Topics enabled.

::: warning Regular groups won't work for thread routing
Regular groups and channels don't support forum topics. You must use a **supergroup** with Forum mode enabled.
:::

1. Create a new group or convert an existing one to a supergroup (add 2 members → open group settings → any admin action triggers conversion)
2. Open group **Settings** → **Topics** → toggle **Topics** on
3. Add your bot to the group: search for your bot username → add as member
4. Optional: give the bot **admin rights** with at least "Post Messages" permission for reliable delivery

### Step 3: Get the Chat ID

The chat ID for a supergroup is a negative number (e.g. `-1001234567890`).

To find it:
1. Add `@userinfobot` or `@RawDataBot` to your group temporarily
2. Send any message in the group
3. The bot will reply with the group's chat ID
4. Remove the helper bot

### Step 4: Create Forum Topics and Get Topic IDs

For each Howl channel you want to route (errors, warnings, etc.):

1. In the supergroup, tap **≡ Topics** → **New Topic**
2. Create topics for: `Errors`, `Warnings`, `Info`, `Audit`, `Deployments`
3. To get the topic ID:
   - Right-click the topic → **Copy Link** → URL ends with `?thread=N` — `N` is the topic ID
   - Or use the Bot API: `GET https://api.telegram.org/bot{TOKEN}/getUpdates` and look for `message_thread_id` in a message sent in that topic

### Step 5: Configure `.env`

```env
HOWL_DRIVER=telegram
HOWL_TELEGRAM_BOT_TOKEN=123456789:ABC-your-bot-token
HOWL_TELEGRAM_CHAT_ID=-1001234567890

# Optional: per-Howl-channel Forum topic IDs
HOWL_TELEGRAM_THREAD_ERRORS=1
HOWL_TELEGRAM_THREAD_WARNINGS=2
HOWL_TELEGRAM_THREAD_INFO=3
HOWL_TELEGRAM_THREAD_AUDIT=4
HOWL_TELEGRAM_THREAD_DEPLOYMENTS=5
```

### Step 6: Verify

```bash
php artisan tinker
```

```php
Howl::info('Telegram driver connected!');
```

## Message Format (HTML)

Telegram notifications use `parse_mode: HTML` and render as:

```
🚨 <b>error</b> — Exception in PaymentProcessor

An unexpected error occurred while processing payment #12345.

<b>User ID:</b> 67890
<b>Amount:</b> $99.00

<code>Illuminate\Database\QueryException
SQLSTATE[23000]: Integrity constraint violation...</code>

MyApp · production · errors · 2026-05-12 14:30:00 UTC
```

HTML entities (`<`, `>`, `&`) are automatically escaped in user-supplied content.

## Thread (Topic) Routing

When a topic ID is configured for the resolved channel name, Howl sets `message_thread_id` in the API request, routing the message to the correct Forum topic.

```php
// config/howl.php
'drivers' => [
    'telegram' => [
        'threads' => [
            'errors'      => env('HOWL_TELEGRAM_THREAD_ERRORS'),
            'warnings'    => env('HOWL_TELEGRAM_THREAD_WARNINGS'),
            'info'        => env('HOWL_TELEGRAM_THREAD_INFO'),
            'audit'       => env('HOWL_TELEGRAM_THREAD_AUDIT'),
            'deployments' => env('HOWL_TELEGRAM_THREAD_DEPLOYMENTS'),
        ],
    ],
],
```

If no thread ID is configured for the channel, the message lands in the supergroup's **General** topic.

## Mentions

::: warning Limited mention support
Telegram only supports mentioning **individual users by their numeric user ID**. `@here`, `@everyone`, and role mentions are silently dropped by the Telegram driver.
:::

| Howl `mention()` type | Telegram behaviour |
|---|---|
| `user` with numeric ID | Rendered as `<a href="tg://user?id=ID">mention</a>` |
| `here` | **Silently dropped** |
| `everyone` | **Silently dropped** |
| `role` | **Silently dropped** |

```php
// Mention a user by their numeric Telegram user ID
Howl::on('errors')
    ->mention('user', '123456789')
    ->error($event);
```

To get a user's numeric ID, ask them to forward a message to `@userinfobot`.

## Attachments

Howl auto-detects the file type by extension:

- **Images** (`.jpg`, `.jpeg`, `.png`, `.gif`, `.webp`) → sent via `sendPhoto`
- **All other files** → sent via `sendDocument`

```php
// Send a log file
Howl::on('errors')
    ->attach('/tmp/app.log')
    ->error($event);

// Send a screenshot
Howl::on('errors')
    ->attach('/tmp/error-screenshot.png')
    ->error($event);
```

Telegram Bot API file size limits:
- **sendPhoto**: 10 MB per image
- **sendDocument**: 50 MB per file

## Buttons (Inline Keyboard)

Add URL buttons via Telegram's `reply_markup.inline_keyboard`:

```php
Howl::on('errors')
    ->button('View Error', 'https://app.example.com/errors/123')
    ->button('Acknowledge', 'https://app.example.com/errors/123/ack')
    ->error($event);
```

Buttons appear below the message as an inline keyboard with URL buttons.

## Common Issues

### Message not delivered to the right topic

- Confirm the topic ID is correct (right-click → Copy Link → trailing number)
- Confirm the bot has permission to post in the topic
- If the supergroup has restricted topic posting to admins, grant the bot admin rights

### "Bad Request: message thread not found"

The topic ID doesn't exist in the supergroup. Either the topic was deleted, or the topic ID is wrong. Verify with `getUpdates` or recreate the topic.

### Bot can't post in the group

- The bot must be **added as a member** to the supergroup
- Ensure the bot token is correct (starts with digits, colon, then alphanumeric string)
- Check that the chat ID is the supergroup ID (negative number, starts with `-100`)
