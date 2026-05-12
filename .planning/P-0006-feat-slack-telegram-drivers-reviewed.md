---
id: "P-0006"
title: "feat: Slack + Telegram drivers with cross-driver feature parity"
type: feat
project: howl
branch: feat/slack-telegram-drivers
base: homolog
tags: [drivers, slack, telegram, blockkit, html-mode, attachments, mentions, path-to-v1]
backlog: null
dependsOn: ["P-0005"]
created: 2026-05-12T01:14
session_id: null
session: "ca1c12c4-eca2-423c-a1da-0ec265f7a0c4"
---

# feat: Slack + Telegram drivers with cross-driver feature parity

## Goal

Add two production-grade transport drivers — **Slack** (Block Kit format, bot-token + `chat.postMessage` Web API) and **Telegram** (HTML parse_mode, Forum topics via `message_thread_id`) — that match the existing Discord driver's feature surface (rich format, attachments, mentions, channel routing) and integrate with both the existing driver-level fallback chain (`config('howl.fallback')`) and the new channel-level failover/fan-out landed in P-0005. Cross-driver feature parity is the explicit goal: `Howl::driver('slack')->error($event)` and `Howl::driver('telegram')->error($event)` must produce visually-equivalent rich messages including attachments and translated mentions.

## Non-Goals

- **Do NOT tag a release.** P-0006 merges to `main` as unreleased work. `v1.0.0` tag happens only at end of P-0008.
- **Do NOT add VitePress docs, README updates, or `llms.txt`.** Those land in P-0008. Inline config comments in `config/howl.php` serve as the seed for future docs but are the only docs added here.
- **Do NOT add 100% coverage gates or the Pest 3/4 CI matrix.** Those land in P-0007. P-0006 adds driver-specific tests that exercise the happy path + critical failure paths but does NOT enforce a coverage percentage.
- **Do NOT add HowlFake per-driver assertions** (`assertSentVia('slack', ...)`). P-0007 owns the HowlFake granularity decision.
- **Do NOT add Slack interactive components** (buttons with `callback_data`, modals, slash commands). Only outgoing rich rendering. `PendingNotification::button()` renders as URL-only Block Kit `actions` block.
- **Do NOT add Telegram callback buttons.** `PendingNotification::button()` renders as `reply_markup.inline_keyboard` with URL buttons only (no `callback_data`).
- **Do NOT enforce file size limits on attachments.** Telegram caps photos at 10MB and documents at 50MB for bots; Slack at 1GB. Document the caps in inline comments but don't pre-validate — let the upstream API return the error.
- **Do NOT add driver-side metrics, structured logging, or telemetry beyond the existing `Log::error` pattern** that `DiscordDriver` uses on failure.
- **Do NOT change the existing `Driver` contract at `src/Contracts/Driver.php`.** Both new drivers implement the existing 2-method interface (`name()`, `send(Payload): bool`).

## Context

- **Path to v1.0.0 (4-plan sequence):** P-0005 (driver-agnostic API, done) → **P-0006 (this plan)** → P-0007 (100% coverage + Pest 3/4 CI matrix) → P-0008 (VitePress docs + LLM docs + release artifacts → tag v1.0.0).
- `src/Contracts/Driver.php:7-19` — 2-method interface. Both new drivers implement: `name(): string` returns driver key, `send(Payload): bool` returns transport success.
- `src/Drivers/DiscordDriver.php` — reference implementation (122 lines). Slack + Telegram drivers MUST follow the same shape: `try { ... } catch (\Throwable) { return false; }` outer envelope, private helpers for endpoint/body resolution, HTTP `Http::timeout(...)` with Laravel HTTP client, multipart only when attachments present.
- `src/Support/EmbedBuilder.php` — reference Discord JSON body builder. `BlockKitBuilder` and `TelegramHtmlBuilder` mirror its API: `static build(Payload): array` (Slack) / `static build(Payload): string` (Telegram).
- `src/Howl.php:158-165` — `resolveDriver()` currently registers `discord` and `null` only. Phase 5 adds `slack` and `telegram`.
- `config/howl.php:86-95` — `'telegram'` and `'slack'` driver scaffolding (stubs). Phase 5 expands each into full config shape with `channels`/`threads` maps and explanatory comments that double as install-doc seed material.
- `config/howl.php:22` — existing `'fallback'` driver-level key. Channel-level failover from P-0005 runs FIRST; driver-level fallback runs on primary channel after channel-set failure. New drivers must integrate with this chain.
- **Slack: bot-token + `chat.postMessage` Web API**:
  - Endpoint: `https://slack.com/api/chat.postMessage` (JSON POST, `Authorization: Bearer <xoxb-token>`).
  - Token from `config('howl.drivers.slack.bot_token')` via env `HOWL_SLACK_BOT_TOKEN`.
  - Required OAuth scope: `chat:write`. Optional: `files:write` for attachments.
  - Per-Howl-channel mapping: `config('howl.drivers.slack.channels')` — Howl channel name (e.g. `'errors'`) → Slack channel ID (e.g. `'C012345ABC'`).
  - Fallback when no mapping: `config('howl.drivers.slack.default_channel')`. If that's also null → driver returns false + `Log::error`.
  - HTTP success: 200 status code AND `{ok: true}` in response JSON. `200 + {ok: false}` is a failure (Slack returns 200 even on API errors).
- **Slack: Block Kit format**:
  - Top-level body: `{channel, attachments: [{color, blocks: [...]}]}`.
  - `color: '#RRGGBB'` (hex) maps from existing `config('howl.colors')` decimal values via `sprintf('#%06X', $decimal)`.
  - `blocks[]`: leading `section` with mentions (if any), `header` with `plain_text` (severity emoji + title), `section` with `mrkdwn` description, `section` with `fields[]` for tabular data, `divider`, `section` with triple-backtick mrkdwn for each code block, `actions` block with URL buttons (if any), `context` block as footer with `app · env · timestamp`.
- **Slack: attachments via `files.upload v2` (3-step per file)**:
  1. POST `https://slack.com/api/files.getUploadURLExternal` with `length` + `filename` form params → returns `{ok, upload_url, file_id}`.
  2. POST file body (multipart) to the returned `upload_url` → returns raw OK.
  3. POST `https://slack.com/api/files.completeUploadExternal` with `files: [{id, title}]` + `channel_id` → returns `{ok}`.
  - Each step's `ok: false` → driver returns false, no further calls (fail fast).
- **Slack: mentions translation**:
  - `mention('here')` → `<!here>` (notifies online users in channel).
  - `mention('everyone')` → `<!channel>` (Slack's @everyone equivalent — pings ALL channel members).
  - `mention('role', 'S0123')` → `<!subteam^S0123>` (user-group / subteam ping; consumer provides Slack subteam ID).
  - `mention('user', 'U0123')` → `<@U0123>` (consumer provides Slack user ID).
  - Mentions render as a leading section block before the header.
- **Telegram: bot HTTP API**:
  - Endpoint: `https://api.telegram.org/bot{token}/{method}` (POST form-data or multipart).
  - Token from `config('howl.drivers.telegram.bot_token')` via env `HOWL_TELEGRAM_BOT_TOKEN`. Format `123456:ABC-DEF...` (colon is part of token, URL-safe).
  - HTTP success: 200 status AND `{ok: true}` in body. Same pattern as Slack — 200+`ok:false` is a failure.
- **Telegram: thread routing via `message_thread_id`** (REQUIRES supergroup + Forum mode enabled, see Tech Notes for install path):
  - `config('howl.drivers.telegram.chat_id')` — the supergroup ID (e.g. `-1001234567890`).
  - `config('howl.drivers.telegram.threads')` — map: Howl channel name → forum topic ID (integer, e.g. `['errors' => 42, 'audits' => 43]`).
  - When `$payload->channel` resolves to a topic ID → include `message_thread_id` in request. No mapping → omit (lands in General topic).
  - `message_thread_id` MUST be cast to `(int)` before sending (env strings → integer).
- **Telegram: HTML parse_mode format**:
  - `parse_mode='HTML'` on `sendMessage`/`sendPhoto`/`sendDocument`.
  - Supported tags: `<b>`, `<i>`, `<code>`, `<pre>`, `<a href="...">`, `<u>`, `<s>`. Code blocks: `<pre><code class="language-{lang}">...</code></pre>` (syntax highlight supported since 2023).
  - HTML-escape user-provided strings before tag wrapping: only `<`, `>`, `&` need escaping (use `htmlspecialchars($s, ENT_NOQUOTES | ENT_HTML5)`).
  - `link_preview_options[is_disabled]=true` to suppress auto-preview on URLs in body.
- **Telegram: attachments via `sendDocument` / `sendPhoto`**:
  - Image extensions (`.jpg`, `.jpeg`, `.png`, `.gif`, `.webp` — case-insensitive) → `sendPhoto`.
  - Everything else → `sendDocument`.
  - First attachment carries the rich body as `caption` (with `parse_mode=HTML`); subsequent attachments have empty caption.
  - When attachments are present, the standalone `sendMessage` call is SKIPPED (first attachment is the message).
  - Multipart form-data: `chat_id`, `message_thread_id` (if set), `photo`/`document`, `caption`, `parse_mode`.
- **Telegram: mentions translation**:
  - `mention('here')` → gracefully skipped (no @here equivalent).
  - `mention('everyone')` → gracefully skipped (no @everyone equivalent).
  - `mention('role', ...)` → gracefully skipped (no role concept).
  - `mention('user', '123456')` → `<a href="tg://user?id=123456">user</a>` (id MUST be numeric Telegram user_id; rendered as a tappable link that mentions the user).
- **Driver fallback chain integration**: existing `config('howl.fallback')` driver-level chain (`Howl::dispatch()` lines 89-117) walks `[primary, fallback]` on failure. Both new drivers must integrate so `config('howl.fallback') = 'slack'` or `'telegram'` works end-to-end.
- **Test baseline:** ~350 tests post-P-0005 (per P-0005 plan estimate). Phase 6 expects +30-40 new tests across the 5 new test files = ~380-390 total.

## Phases

### Phase 1: SlackDriver core — BlockKitBuilder + basic send + mentions + channel resolution

**Touches:** `src/Drivers/SlackDriver.php` (new), `src/Support/BlockKitBuilder.php` (new), `tests/Feature/SlackDriverTest.php` (new)

- [ ] [H] Create `src/Support/BlockKitBuilder.php` with `final class BlockKitBuilder` exposing `public static function build(Payload $payload): array`. Returns the full `chat.postMessage` body: `['channel' => $channelId, 'attachments' => [['color' => '#RRGGBB', 'blocks' => [...]]]]`. Channel ID is injected at the driver layer; BlockKitBuilder leaves `'channel'` placeholder or accepts it as a second param — pick the second-param approach for testability: `build(Payload $payload, string $channelId): array`.
- [ ] [S] Block assembly inside `BlockKitBuilder`:
  - Leading `section` block with mrkdwn mentions (when `$payload->mentions` non-empty).
  - `header` block with `plain_text` = severity emoji + space + title.
  - `section` block with `mrkdwn` description (when `$payload->description` non-empty).
  - `section` block with `fields[]` array (each entry `{type: mrkdwn, text: '*name*\nvalue'}`) for `$payload->fields`.
  - `divider` block between fields and code blocks if both present.
  - `section` block with triple-backtick mrkdwn for each `$payload->codeBlocks` entry (language hint goes inside the fence).
  - `actions` block with URL buttons for each `$payload->buttons` entry (`{type: button, text: {type: plain_text, text: label}, url: url}`).
  - `context` block as footer: `{type: mrkdwn, text: '{app} · {env} · {timestamp}'}` derived from payload + config.
- [ ] [H] Color helper: `private static function color(string $severity): string` returns `sprintf('#%06X', config('howl.colors.'.$severity, 0))`.
- [ ] [S] Mentions translation logic inside `BlockKitBuilder::buildMentions()`: produce a single mrkdwn string per the mapping in Context.
- [ ] [S] Create `src/Drivers/SlackDriver.php` implementing `Skaisser\Howl\Contracts\Driver`:
  - `name(): string` returns `'slack'`.
  - `send(Payload $payload): bool` resolves channel ID via `resolveChannelId()` helper, builds body via `BlockKitBuilder::build($payload, $channelId)`, POSTs to `https://slack.com/api/chat.postMessage` with `Authorization: Bearer <token>` and JSON content-type, returns `true` iff `status === 200 && json.ok === true`.
  - `try/catch (\Throwable) { return false; }` outer envelope.
  - Timeout from `config('howl.drivers.slack.timeout', 10)`.
- [ ] [H] `private function resolveChannelId(Payload $payload): ?string` — reads `$payload->channel`, looks up `config('howl.drivers.slack.channels.'.$channel)`; on null falls back to `config('howl.drivers.slack.default_channel')`. Returns null if both null (caller logs and returns false).
- [ ] [H] Null channel ID handling: in `send()`, if `resolveChannelId()` returns null → `Log::error('Howl: Slack channel id unresolved', ['channel' => $payload->channel])`; return false. Matches DiscordDriver missing-config behavior.
- [ ] [S] Create `tests/Feature/SlackDriverTest.php` with `Http::fake()`:
  - Basic send: payload with title/description/severity → POST to correct URL with correct channel; returns true on `{ok:true}`.
  - Block Kit shape: header block contains severity emoji + title; section block has description; color hex matches severity.
  - Fields: payload with 2 inline fields renders as section block with `fields[]` array of length 2.
  - Code blocks: triple-backtick + language hint in mrkdwn.
  - Mentions: each of `here/everyone/role/user` renders correctly in leading section.
  - Channel resolution: `Howl::on('errors')->error($event)` with `config('howl.drivers.slack.channels.errors' => 'CXXX')` POSTs with `channel: 'CXXX'`.
  - Default channel fallback: no per-Howl-channel mapping but `default_channel` set → uses default.
  - Failure: 200 + `{ok: false}` returns false; 401 returns false; timeout returns false.
  - Channel-id unresolved: both channels and default null → `Log::error` fired, returns false, no HTTP call made.

**Verify:** `vendor/bin/pest --filter="SlackDriver"` AND `vendor/bin/pest --parallel`.

### Phase 2: SlackDriver attachments via `files.upload v2`

**Touches:** `src/Drivers/SlackDriver.php` (extend), `tests/Feature/SlackAttachmentTest.php` (new)

- [ ] [S] Add `private function uploadAttachments(array $paths, string $channelId, string $token, int $timeout): bool` to `SlackDriver`. Loops the 3-step flow per file. Returns false on any step's `ok: false` (fail fast). Returns true when all files uploaded.
- [ ] [S] Step 1 helper: `private function getUploadUrl(string $filename, int $length, string $token, int $timeout): ?array` returns `['upload_url' => ..., 'file_id' => ...]` or null on failure.
- [ ] [S] Step 2 helper: `private function uploadFileBody(string $uploadUrl, string $path, int $timeout): bool` POSTs multipart with file body, returns true on 2xx.
- [ ] [S] Step 3 helper: `private function completeUpload(string $fileId, string $title, string $channelId, string $token, int $timeout): bool` calls `files.completeUploadExternal`, returns true on `{ok:true}`.
- [ ] [S] Modify `SlackDriver::send()`: when `$payload->attachments` non-empty, call `uploadAttachments()` BEFORE the `chat.postMessage` call. If upload returns false, return false without posting message. (Slack auto-shares completed-upload files to the named channel, so they appear as standalone file messages adjacent to the rich-block message — acceptable for v1.0.0.)
- [ ] [H] Edge case: non-readable file path → `throw new \InvalidArgumentException("Howl: attachment path is not a readable file: {$path}")` (mirrors DiscordDriver line 113).
- [ ] [S] Create `tests/Feature/SlackAttachmentTest.php` with `Http::fake()`:
  - Single attachment: verify 3 endpoint URLs hit in order via `Http::assertSent()` with index-based assertions.
  - Multiple attachments (e.g. 2 files): verify 6 calls total (3 per file) + 1 final `chat.postMessage`.
  - `files.getUploadURLExternal` returns `{ok:false}` → 1 call made, driver returns false.
  - `files.completeUploadExternal` returns `{ok:false}` → 3 calls per file made, final chat.postMessage skipped, driver returns false.
  - Non-readable file path → `InvalidArgumentException` thrown.
  - Attachment + message: when both attachments + rich body present, file uploads happen first, then `chat.postMessage` posts the blocks.

**Verify:** `vendor/bin/pest --filter="SlackAttachment"` AND `vendor/bin/pest --parallel`.

### Phase 3: TelegramDriver core — TelegramHtmlBuilder + sendMessage + thread routing + mentions + HTML escaping

**Touches:** `src/Drivers/TelegramDriver.php` (new), `src/Support/TelegramHtmlBuilder.php` (new), `tests/Feature/TelegramDriverTest.php` (new)

- [ ] [H] Create `src/Support/TelegramHtmlBuilder.php` with `final class TelegramHtmlBuilder` exposing `public static function build(Payload $payload): string`. Returns the HTML body string for `text` field of `sendMessage` (or `caption` of `sendPhoto`/`sendDocument`).
- [ ] [S] HTML body structure (in order):
  - Leading paragraph with mentions if any (user mentions only; here/everyone/role skipped).
  - `<b>{emoji} {escape(title)}</b>` line.
  - Blank line.
  - `{escape(description)}` (if present).
  - Each field: `<b>{escape(name)}:</b> {escape(value)}` on its own line.
  - Each code block: `<pre><code class="language-{lang}">{escape(code)}</code></pre>` (note: escape function still runs on `code`).
  - Each button: `[{escape(label)}]({url})` rendered as `<a href="{url}">{escape(label)}</a>` (URL buttons only at body level; inline keyboard handled at form-data level via `reply_markup`).
  - Footer: `\n<i>{app} · {env} · {timestamp formatted}</i>`.
- [ ] [H] HTML-escape helper: `private static function escape(?string $s): string` using `htmlspecialchars($s ?? '', ENT_NOQUOTES | ENT_HTML5, 'UTF-8')`.
- [ ] [S] Mentions logic inside `TelegramHtmlBuilder::buildMentions()`: iterate `$payload->mentions`; for `type === 'user'` produce `<a href="tg://user?id={id}">user</a>` separated by spaces; skip everything else.
- [ ] [S] Create `src/Drivers/TelegramDriver.php` implementing `Skaisser\Howl\Contracts\Driver`:
  - `name(): string` returns `'telegram'`.
  - `send(Payload $payload): bool`:
    - Resolves `$chatId = config('howl.drivers.telegram.chat_id')`. Null → `Log::error`, return false.
    - Resolves `$threadId` via `resolveThreadId($payload)` helper. Returns null if no mapping.
    - Builds body via `TelegramHtmlBuilder::build($payload)`.
    - If `$payload->attachments` non-empty → delegate to `uploadAttachments()` (Phase 4 adds this; Phase 3 stubs the call returning false until Phase 4 lands the implementation).
    - Else → POST form-data to `https://api.telegram.org/bot{token}/sendMessage` with fields: `chat_id`, `message_thread_id` (cast to int if set), `text`, `parse_mode=HTML`, `link_preview_options[is_disabled]=true`, and `reply_markup` (URL buttons as `inline_keyboard`) if `$payload->buttons` non-empty.
    - Returns `true` iff `status === 200 && json.ok === true`.
  - `try/catch (\Throwable) { return false; }` outer envelope.
  - Timeout from `config('howl.drivers.telegram.timeout', 10)`.
- [ ] [H] `private function resolveThreadId(Payload $payload): ?int` — reads `$payload->channel`, looks up `config('howl.drivers.telegram.threads.'.$channel)`, returns `(int)` cast or null.
- [ ] [S] `private function buildReplyMarkup(array $buttons): ?array` — returns `['inline_keyboard' => [[{text, url}, ...]]]` or null if empty. URL-only, no `callback_data`.
- [ ] [S] Create `tests/Feature/TelegramDriverTest.php` with `Http::fake()`:
  - Basic send: POST to `/bot{token}/sendMessage` with `text` + `parse_mode=HTML`; returns true on 200+`{ok:true}`.
  - Bot token in URL: verify the token appears in the URL path correctly (handles the `:` character).
  - HTML body: title wrapped in `<b>`; severity emoji prefixed; fields formatted; code blocks in `<pre><code>`.
  - Thread routing: `Howl::on('errors')->error($event)` with `threads.errors = 42` → form field `message_thread_id = 42` (integer).
  - No thread mapping: no `message_thread_id` field in form data.
  - HTML escaping: title containing `<script>alert(1)</script>` is escaped to `&lt;script&gt;alert(1)&lt;/script&gt;`.
  - User mention: renders `<a href="tg://user?id=123">user</a>` in body.
  - Skipped mentions: `here/everyone/role` produce empty mentions leading paragraph.
  - URL button: `->button('Open', 'https://...')` renders as `reply_markup.inline_keyboard` URL button.
  - Failure: 200 + `{ok: false}` returns false; 401 returns false.
  - Null chat_id: `Log::error` fired, returns false, no HTTP call made.

**Verify:** `vendor/bin/pest --filter="TelegramDriver"` AND `vendor/bin/pest --parallel`.

### Phase 4: TelegramDriver attachments via `sendDocument` / `sendPhoto`

**Touches:** `src/Drivers/TelegramDriver.php` (extend), `tests/Feature/TelegramAttachmentTest.php` (new)

- [ ] [S] Add `private function uploadAttachments(array $paths, string $chatId, ?int $threadId, string $body, string $token, int $timeout): bool` to `TelegramDriver`. Loops paths; per path detects image vs document via lowercased extension; sends accordingly. First attachment carries `$body` as `caption`; subsequent attachments have empty caption.
- [ ] [H] Image detection: `private static function isImage(string $path): bool` returns true if `strtolower(pathinfo($path, PATHINFO_EXTENSION))` ∈ `['jpg', 'jpeg', 'png', 'gif', 'webp']`.
- [ ] [S] `private function sendDocument(string $path, string $chatId, ?int $threadId, string $caption, string $token, int $timeout): bool` — multipart POST to `/bot{token}/sendDocument` with `chat_id`, `message_thread_id` (if set), `document` (file body), `caption`, `parse_mode=HTML`.
- [ ] [S] `private function sendPhoto(string $path, string $chatId, ?int $threadId, string $caption, string $token, int $timeout): bool` — multipart POST to `/bot{token}/sendPhoto` with `chat_id`, `message_thread_id` (if set), `photo` (file body), `caption`, `parse_mode=HTML`.
- [ ] [S] Modify `TelegramDriver::send()` to actually call `uploadAttachments()` (stub from Phase 3 replaced). When attachments present, the standalone `sendMessage` is SKIPPED (first attachment caption IS the message).
- [ ] [H] Edge case: non-readable file path → `throw new \InvalidArgumentException("Howl: attachment path is not a readable file: {$path}")`.
- [ ] [S] Create `tests/Feature/TelegramAttachmentTest.php` with `Http::fake()`:
  - Image attachment (`.png`): POSTs to `/sendPhoto` with multipart `photo` field; caption carries body; returns true on `{ok:true}`.
  - Document attachment (`.log`): POSTs to `/sendDocument` with multipart `document` field; same caption pattern.
  - Mixed: 1 image + 1 document → 2 calls to different endpoints; first carries caption, second has empty caption.
  - Extension case-insensitivity: `.JPG` and `.jpg` both route to `sendPhoto`.
  - Threaded attachments: `message_thread_id` flows through to multipart form.
  - Failure on any upload: subsequent uploads NOT attempted (fail fast); driver returns false.
  - Non-readable file → `InvalidArgumentException` thrown.
  - No standalone `sendMessage`: when attachments present, verify zero calls to `/sendMessage`.

**Verify:** `vendor/bin/pest --filter="TelegramAttachment"` AND `vendor/bin/pest --parallel`.

### Phase 5: Driver registration + config expansion + cross-driver fallback test

**Touches:** `src/Howl.php`, `config/howl.php`, `tests/Feature/CrossDriverFallbackTest.php` (new)

- [ ] [H] Update `Howl::resolveDriver()` at `src/Howl.php:158-165`: add `'slack' => new SlackDriver`, `'telegram' => new TelegramDriver` to the match expression.
- [ ] [H] Expand `config/howl.php` `drivers.slack` section (currently lines 92-94, single stub key). Final shape:
  ```php
  'slack' => [
      // Slack App OAuth bot token. Requires `chat:write` scope minimum;
      // `files:write` scope additionally needed for ->attach() support.
      // Create at https://api.slack.com/apps → OAuth & Permissions → Install to Workspace.
      'bot_token' => env('HOWL_SLACK_BOT_TOKEN'),

      // Default Slack channel ID (e.g. 'C0123ABC') when no per-Howl-channel mapping matches.
      // Get channel IDs by right-clicking the channel in Slack → Copy → Copy link (ID is the last segment).
      'default_channel' => env('HOWL_SLACK_DEFAULT_CHANNEL'),

      // Map Howl channel name → Slack channel ID for routing.
      'channels' => [
          'errors'      => env('HOWL_SLACK_CHANNEL_ERRORS'),
          'warnings'    => env('HOWL_SLACK_CHANNEL_WARNINGS'),
          'info'        => env('HOWL_SLACK_CHANNEL_INFO'),
          'audit'       => env('HOWL_SLACK_CHANNEL_AUDIT'),
          'deployments' => env('HOWL_SLACK_CHANNEL_DEPLOYMENTS'),
      ],

      'timeout' => env('HOWL_SLACK_TIMEOUT', 10),
  ],
  ```
- [ ] [H] Expand `config/howl.php` `drivers.telegram` section (currently lines 87-90, two stub keys). Final shape:
  ```php
  'telegram' => [
      // Telegram bot token from @BotFather (format: '123456:ABC-DEF...').
      'bot_token' => env('HOWL_TELEGRAM_BOT_TOKEN'),

      // Telegram supergroup chat_id (e.g. '-1001234567890').
      //
      // REQUIRED SETUP for thread routing:
      //   1. Create a supergroup (NOT a regular group — must convert to supergroup).
      //   2. Enable Forum mode: Group settings → Topics → toggle "Topics" on.
      //   3. Add your bot to the supergroup with at least Read access.
      //   4. Create one topic per Howl channel (errors, audits, etc.).
      //   5. Get each topic's numeric ID via the Telegram Bot API getUpdates,
      //      or by right-clicking the topic → Copy Link → the trailing number is the topic ID.
      //
      // If you don't need thread routing, leave `threads` empty and messages
      // land in the supergroup's General topic.
      'chat_id' => env('HOWL_TELEGRAM_CHAT_ID'),

      // Map Howl channel name → Telegram forum topic ID (integer).
      // Empty map = no thread routing = all messages land in General.
      'threads' => [
          'errors'      => env('HOWL_TELEGRAM_THREAD_ERRORS'),
          'warnings'    => env('HOWL_TELEGRAM_THREAD_WARNINGS'),
          'info'        => env('HOWL_TELEGRAM_THREAD_INFO'),
          'audit'       => env('HOWL_TELEGRAM_THREAD_AUDIT'),
          'deployments' => env('HOWL_TELEGRAM_THREAD_DEPLOYMENTS'),
      ],

      'timeout' => env('HOWL_TELEGRAM_TIMEOUT', 10),
  ],
  ```
- [ ] [S] Create `tests/Feature/CrossDriverFallbackTest.php`:
  - `config(['howl.driver' => 'discord', 'howl.fallback' => 'slack'])` + Discord webhook returns 404 + Slack returns 200/`ok:true` → returns true, 1 call each to Discord and Slack.
  - `config(['howl.driver' => 'discord', 'howl.fallback' => 'telegram'])` + Discord 404 + Telegram 200/`ok:true` → returns true.
  - `config(['howl.driver' => 'slack', 'howl.fallback' => 'discord'])` + Slack 200/`ok:false` + Discord 204 → returns true, fallback walked.
  - `config(['howl.driver' => 'telegram', 'howl.fallback' => 'discord'])` + Telegram 200/`ok:false` + Discord 204 → returns true.
  - All-fail scenario: Discord 404 + Slack `ok:false` → returns false; both endpoints called once.

**Verify:** `vendor/bin/pest --filter="CrossDriverFallback"` AND `vendor/bin/pest --parallel`.

### Phase 6: Full regression + handoff to P-0007

**Touches:** none (regression + handoff prose only)

- [ ] [H] Full regression: `vendor/bin/pest --parallel` exits 0 with zero failures.
- [ ] [H] Test count check: baseline ~350 post-P-0005, expect ~380-390 after P-0006 additions (+30-40 driver tests).
- [ ] [H] Verify no `BadMethodCallException` patterns remain anywhere: `grep -rn "reserved for v2\|BadMethodCallException" src/ tests/` should return only references inside test assertions for unknown driver names (e.g. `Howl::driver('mythical')` test).
- [ ] [H] Verify `composer.json` constraints unchanged (no version bump, no new prod deps; only test-time `Http::fake()` usage which is Laravel built-in).
- [ ] [H] Add a "Handoff to P-0007" section at the end of this plan stating: "All 3 drivers implemented and tested via `Http::fake()` happy + critical-failure paths. P-0007 should: (1) add Pest 3/4 cross-version CI matrix (`PHP 8.3/8.4 × Laravel 12/13` = 4 jobs); (2) add coverage tooling and 100% line coverage gate; (3) add HowlFake per-driver assertions (`assertSentVia('slack', ...)`); (4) backfill any edge-case tests that coverage report flags as uncovered."
- [ ] [H] DO NOT tag a release. DO NOT bump composer.json version. DO NOT update CHANGELOG. All release artifacts land in P-0008.

**Verify:** `git log --oneline homolog..HEAD` shows clean commit history per phase; `vendor/bin/pest --parallel` returns exit 0.

## Execution Strategy

> **Approach:** `/plan-approved` with maximum parallelism — Slack and Telegram drivers are fully independent (zero file overlap), so both drivers + both attachment phases run in parallel pairs.
> **Total Tasks:** 33 (H: 11, S: 22, O: 0)
> **Estimated Rounds:** 4 (2 parallel + 2 sequential)
> **Parallel Savings:** 2 rounds saved over sequential execution

### File-Touch Matrix

| Phase | Files/Dirs Touched | Depends On |
|-------|-------------------|------------|
| Phase 1 (Slack core) | `src/Drivers/SlackDriver.php` (new), `src/Support/BlockKitBuilder.php` (new), `tests/Feature/SlackDriverTest.php` (new) | — |
| Phase 2 (Slack attach) | `src/Drivers/SlackDriver.php` (extend Phase 1), `tests/Feature/SlackAttachmentTest.php` (new) | Phase 1 |
| Phase 3 (Telegram core) | `src/Drivers/TelegramDriver.php` (new), `src/Support/TelegramHtmlBuilder.php` (new), `tests/Feature/TelegramDriverTest.php` (new) | — |
| Phase 4 (Telegram attach) | `src/Drivers/TelegramDriver.php` (extend Phase 3), `tests/Feature/TelegramAttachmentTest.php` (new) | Phase 3 |
| Phase 5 (registration + config + cross-driver test) | `src/Howl.php` (resolveDriver), `config/howl.php` (expand drivers), `tests/Feature/CrossDriverFallbackTest.php` (new) | Phases 1 + 3 (both drivers must exist) |
| Phase 6 (regression + handoff) | None (regression + prose) | All prior |

**Parallelism opportunity:** Slack and Telegram driver implementations have ZERO file overlap. Both core phases (P1 + P3) run together as Round 1; both attachment extensions (P2 + P4) run together as Round 2.

### Round 1: Phase 1 + Phase 3 → Parallel Teams (Mode B — 2 team-leads dispatched together)

Independent driver implementations, both with `[S]` tasks (HTTP integration + format builders + tests). Zero file overlap.

| Phase | Mode | Model | Tasks | Notes |
|-------|------|-------|-------|-------|
| Phase 1: SlackDriver core | Team-lead | Sonnet | 1.1-1.8 (4×[H] + 4×[S]) | Team `bifrost` — Block Kit body, channel resolution, mentions translation, 9 tests |
| Phase 3: TelegramDriver core | Team-lead | Sonnet | 3.1-3.8 (2×[H] + 6×[S]) | Team `asgard` — HTML body, thread routing, HTML escape, mentions, 10 tests |

### Round 2: Phase 2 + Phase 4 → Parallel Teams (Mode B — 2 team-leads dispatched together)

Both phases extend their respective driver files from Round 1. Still zero overlap between the two driver files.

| Phase | Mode | Model | Tasks | Notes |
|-------|------|-------|-------|-------|
| Phase 2: Slack attachments | Team-lead | Sonnet | 2.1-2.7 (1×[H] + 6×[S]) | Team `mjolnir` — files.upload v2 3-step flow, 6 tests |
| Phase 4: Telegram attachments | Team-lead | Sonnet | 4.1-4.7 (2×[H] + 5×[S]) | Team `valhalla` — sendDocument/sendPhoto with auto-detection, 8 tests |

### Round 3: Phase 5 → Single Team (Mode C — depends on Rounds 1 + 2)

Driver registration in `resolveDriver()` + config expansion + cross-driver fallback tests. Requires both Slack and Telegram drivers to exist.

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 5.1 Update Howl::resolveDriver() match | [H] | ragnarok-1 | Add `'slack' => new SlackDriver`, `'telegram' => new TelegramDriver` |
| 5.2 Expand drivers.slack config | [H] | ragnarok-1 | Bot token, default_channel, channels map, comments |
| 5.3 Expand drivers.telegram config | [H] | ragnarok-1 | Bot token, chat_id, threads map, supergroup setup comments |
| 5.4 Create CrossDriverFallbackTest | [S] | ragnarok-2 | 5 cross-driver fallback scenarios via Http::fake() |

### Round 4: Phase 6 → Single Subagent (Mode F — all [H], regression + handoff)

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 6.1 pest --parallel regression | [H] | worker-1 | Full suite green |
| 6.2 Test count check | [H] | worker-1 | Baseline +30-40 |
| 6.3 Verify no BadMethodCallException | [H] | worker-1 | Grep + read remaining hits |
| 6.4 Verify composer.json unchanged | [H] | worker-1 | No version bump, no new prod deps |
| 6.5 Handoff to P-0007 section | [H] | worker-1 | Append to plan |
| 6.6 Verify no release artifacts | [H] | worker-1 | Sanity check |

## Tech Notes

- **Telegram supergroup + Forum mode is a real install gotcha** — the inline config comment in `config/howl.php` is the FIRST place a Laravel user discovers this requirement. P-0008 will lift this to a dedicated `/docs/drivers/telegram.md` page on the VitePress site, but the inline comment must be self-sufficient for users who haven't found the docs yet.
- **Slack bot token security**: `Authorization: Bearer xoxb-...` must never appear in `Log::error` payloads. The existing `DiscordDriver` doesn't log the webhook URL on failure — mirror that discipline. Test failure messages should also not dump the token.
- **`message_thread_id` integer cast**: Env values are always strings. `(int) $threadIdString` works for normal cases. Test with `threads.errors = '42'` (string in env) → form data sees `42` (int).
- **Code block syntax highlighting**: Telegram's `<pre><code class="language-php">...</code></pre>` is supported but renders identically to plain `<pre>` in clients older than ~2023. Worth noting in comments but not blocking.
- **PHP `^8.3`, Laravel `^12.0 || ^13.0`** — composer constraints unchanged.
- **No new prod dependencies**. All HTTP via Laravel's built-in `Http` facade. All multipart via `Http::asMultipart()`.
- **`HowlFake` continues to work unchanged** — fake intercepts `dispatch()` upstream of driver `send()`, so new drivers don't break fake assertions. Per-driver `assertSentVia` API is deferred to P-0007.

## References

- [P-0005 — driver-agnostic API + channel modes + rate-limit middleware](./P-0005-feat-driver-agnostic-api-and-channel-modes-todo.md) — prerequisite; `Howl::driver()` builder and per-call driver override plumbing landed there
- [P-0001](./P-0001-feat-howl-package-v0-1-0-approved.md) — original Discord driver shape that Slack/Telegram drivers mirror
- [P-0002](./P-0002-feat-howl-events-layer-v0-2-0-complete.md) — `HowlEvent` 8-method contract; driver builders consume the payload it produces
- Future: P-0007 (100% coverage + Pest 3/4 CI matrix + HowlFake per-driver assertions), P-0008 (VitePress docs + LLM docs + release → tag v1.0.0)
- Slack API: https://api.slack.com/methods/chat.postMessage, https://api.slack.com/methods/files.getUploadURLExternal
- Telegram Bot API: https://core.telegram.org/bots/api#sendmessage, https://core.telegram.org/bots/api#forum-topics

## Acceptance

- [ ] `Howl::driver('slack')->error($event)` dispatches a Block Kit attachment via `chat.postMessage` to the configured Slack channel; returns `true` on 200 + `{ok:true}`.
- [ ] `Howl::driver('telegram')->error($event)` dispatches an HTML-formatted message via `sendMessage` to the configured Telegram supergroup, threaded to the matched forum topic; returns `true` on 200 + `{ok:true}`.
- [ ] Both drivers support `PendingNotification::attach($path)`: Slack via 3-step `files.upload v2` flow, Telegram via `sendDocument`/`sendPhoto` with extension auto-detection.
- [ ] Mentions translate correctly across all 3 drivers: a `HowlEvent::mentions()` returning `[['type'=>'user','id'=>'X']]` renders as `<@X>` (Slack), `<a href="tg://user?id=X">user</a>` (Telegram), and the existing Discord rendering — all in the same dispatch call when `channel_mode = 'fan_out'`.
- [ ] `config('howl.fallback') = 'slack'` or `'telegram'` correctly chains: primary driver failure → fallback driver attempted with same payload. Same for `slack → discord` and `telegram → discord` chains.
- [ ] `config('howl.drivers.slack')` and `config('howl.drivers.telegram')` documented with explanatory inline comments (these comments seed P-0008's VitePress docs).
- [ ] Telegram config comments explicitly walk through the supergroup + Forum mode + bot membership + topic ID lookup setup steps.
- [ ] HTML escaping: a payload title containing `<script>alert(1)</script>` is escaped in the Telegram body output (cannot inject HTML).
- [ ] Slack bot token does NOT appear in any `Log::error` call or test failure message.
- [ ] Full Pest suite green: `vendor/bin/pest --parallel` exits 0; total test count baseline (~350) + ~30-40 = ~380-390.
- [ ] `composer.json` `require` / `require-dev` unchanged (no new prod deps).
- [ ] NO release tag, NO CHANGELOG entry, NO composer.json version bump. All release artifacts deferred to P-0008.
- [ ] Handoff note at end of plan flags scope for P-0007: Pest 3/4 CI matrix, 100% coverage gate, HowlFake per-driver assertions.
