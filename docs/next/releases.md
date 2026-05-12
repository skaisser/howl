# Release Notes

## What's New in v1.0.0

v1.0.0 is the first stable public release of `skaisser/howl`. This version ships the complete multi-driver notification system with a production-ready API, full documentation, and a 100% line coverage test suite.

The v1.0 release is the culmination of four focused plans (P-0005 → P-0008):

- **P-0005** — driver-agnostic API, channel failover, rate-limit middleware
- **P-0006** — Slack driver (Block Kit), Telegram driver (HTML + Forum topics)
- **P-0007** — 100% line coverage gate, Pest 3/4 CI matrix, HowlFake per-driver assertions, architecture tests
- **P-0008** — VitePress documentation site, llms.txt, README rewrite, this release

---

## Driver-Agnostic API

**[P-0005]** A single unified interface for all three notification drivers. Switch drivers per-call without changing application logic.

```php
// Direct severity verb — uses config('howl.driver') by default
Howl::error($event);
Howl::info('Scheduled job completed');

// Channel routing — driver-agnostic
Howl::on('errors')->error($event);

// Per-call driver override
Howl::driver('slack')->info('System OK');

// Combined: specific driver + specific channel
Howl::driver('telegram')->on('deployments')->deployment($event);
```

Six severity verbs: `error`, `warning`, `info`, `success`, `audit`, `deployment`. Each accepts a `HowlEvent` instance or a plain string title.

See: [Quick Start](/next/guide/quick-start) · [Builder Methods](/next/extension/builder-methods)

---

## Slack Driver

**[P-0006]** Full Slack driver using Block Kit format and bot OAuth (`chat.postMessage`).

- **Block Kit rendering** — rich message with color sidebar, section blocks, fields, context footer
- **Channel routing** via `drivers.slack.channels` map (Howl channel name → Slack channel ID)
- **Mentions translation** — `here` → `<!here>`, `everyone` → `<!channel>`, `role` → `<!subteam^ID>`, `user` → `<@UID>`
- **Attachments** via `files.upload v2` (3-step: `getUploadURLExternal` → `POST file` → `completeUploadExternal`)
- **Buttons** → URL-only `actions` block

Setup: [Slack Driver](/next/drivers/slack)

---

## Telegram Driver

**[P-0006]** Full Telegram driver using HTML parse mode and Forum topic routing.

- **HTML rendering** — bold/italic/code/pre formatting with monospace code blocks
- **Forum topic routing** via `drivers.telegram.threads` map (Howl channel → topic ID)
- **Attachments** — `sendDocument`/`sendPhoto` auto-detected by file extension (`.jpg`, `.png`, `.gif`, `.webp` → photo; others → document)
- **Mentions** — `user` type supported via `<a href="tg://user?id=UID">name</a>`; `here`/`everyone`/`role` silently dropped (Telegram has no equivalent)
- **Buttons** → `reply_markup.inline_keyboard` URL buttons

Setup: [Telegram Driver](/next/drivers/telegram)

---

## Channel Failover and Fan-Out

**[P-0005]** Two-channel dispatch modes configurable per application.

**Failover (default):** Dispatch to the primary channel; on failure, automatically retry on the backup channel.

```env
HOWL_BACKUP_CHANNEL=alerts-backup
HOWL_CHANNEL_MODE=failover
```

**Fan-Out:** Dispatch to both channels simultaneously. Returns `true` if at least one succeeds.

```env
HOWL_BACKUP_CHANNEL=audit-mirror
HOWL_CHANNEL_MODE=fan_out
```

See: [Failover & Fan-Out](/next/configuration/failover-and-fan-out)

---

## Rate Limiting

**[P-0005]** Opt-in Redis-backed rate limiting on queued notification jobs.

```env
HOWL_RATE_LIMITER_KEY=howl-discord
```

```php
// AppServiceProvider::boot()
RateLimiter::for('howl-discord', fn () => Limit::perMinute(28));
```

Rate-limit releases do not consume retry attempts. See: [Rate Limiting](/next/configuration/rate-limiting)

---

## HowlFake Per-Driver Assertions

**[P-0007]** Three new `HowlFake` assertions for driver-level testing:

```php
$fake->assertSentVia('slack', fn ($p) => $p->severity === 'info');
$fake->assertSentViaNothing('telegram');
$payloads = $fake->sentVia('discord');
```

See: [HowlFake](/next/testing/howl-fake)

---

## Architecture Tests

**[P-0007]** Pest `arch()` rules enforcing structural invariants:

- All event classes extend `HowlEvent`
- All driver classes implement the `Driver` contract
- No debug calls (`dd`, `dump`, `var_dump`) in `src/`
- `Payload` is `final readonly`
- `Contracts` namespace contains only interfaces

See: [Architecture Tests](/next/testing/architecture)

---

## Documentation Site

**[P-0008]** Full VitePress documentation site at [howl.skaisser.dev](https://howl.skaisser.dev).

- Laravel-docs-style sidebar with Upgrade Guide, Release Notes, and per-section deep pages
- Versioned docs: `/next/` for pre-release authoring, `/v1.0.0/` for the frozen stable snapshot
- Cloudflare Pages hosting with automatic deploys on push to `main`

---

## LLM-Friendly Docs

**[P-0008]** `llms.txt` and `llms-full.txt` served from the repo root and the docs site for LLM tool discovery.

```bash
curl https://howl.skaisser.dev/llms.txt
curl https://howl.skaisser.dev/llms-full.txt
```

---

## Breaking Changes

- `Howl::onDiscord()`, `Howl::onSlack()`, `Howl::onTelegram()` removed — see the [Upgrade Guide](/next/upgrade) for the migration codemod.

## Supported versions

| PHP | Laravel | Pest |
|---|---|---|
| 8.3, 8.4 | 12, 13 | 3 (L12), 4 (L13) |
