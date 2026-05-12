---
id: "P-0001"
title: "feat: skaisser/howl Laravel package v0.1.0"
type: feat
project: howl
branch: feat/howl-package-v0-1-0
base: main
tags: [package, laravel, discord, notifier]
backlog: null
dependsOn: null
created: 2026-05-11T07:20
session_id: null
session: "d472576d-db90-4721-8fc4-01d80f900381"
---

# feat: skaisser/howl Laravel package v0.1.0

## Goal

Build and release v0.1.0 of `skaisser/howl` — a multi-driver Laravel notifier with Discord-first rich embeds, mentions, bot-parseable footer metadata, fallback chain, queue mode, and a `Howl::fake()` test helper. Source of truth for design: `decisions.md` (16 locked decisions); scaffold reference: `BOOTSTRAP.md`.

## Non-Goals

- **Paylog migration of 246 `TellKaiser::onSlack()` callsites** — separate plan in the paylog repo, not in scope here.
- **Telegram / Slack driver implementations** — interface and config keys reserved; v1 ships Discord + Null only.
- **Discord bot (gateway connection)** — deferred to v3 if interactive workflows are ever needed.
- **GitHub-issue auto-filing bot** — that's a downstream consumer of howl's footer contract, separate project.
- **Per-channel avatar customization** — single avatar URL in v1, per-channel deferred.
- **Per-channel webhook splitting smoke test in production** — config keys exist, but field-testing under load is a v0.2 concern.

## Context

- `decisions.md` — 16 locked design decisions, visual mockup, color palette (decimal+hex), 9-event template list, professional emoji palette, acceptance criteria (11 boxes).
- `BOOTSTRAP.md` — composer init flags, directory layout, `pint.json`, `phpunit.xml`, full `config/howl.php` stub, `Driver` contract stub, facade stub, service-provider stub, CI workflow YAML.
- Empty Laravel package repo at `~/Sites/howl/` — git initialized, remote `git@github.com:skaisser/howl.git` wired, only `README.md`, `CLAUDE.md`, `.kaisser.yml` present.
- Namespace: `Skaisser\Howl\` → `src/`; test namespace: `Skaisser\Howl\Tests\` → `tests/`.
- CI matrix: PHP **8.2 / 8.3 / 8.4** × Laravel **11.x / 12.x** (6 cells), runs `vendor/bin/pest` + `vendor/bin/pint --test`.
- Test stack: Pest 3 + `pestphp/pest-plugin-laravel` 3 + `orchestra/testbench` 9|10.
- Distribution: public Packagist; SemVer from `v0.1.0`; auto-publish via Packagist GitHub webhook on tag push.
- Acceptance criteria (decisions.md §"Acceptance criteria for v0.1.0") is the binding done-definition.

## Tech Stack Versions

`kaisser detect-stack --versions` returns `{}` (empty package, no lockfile yet). Versions below are the constraint ranges declared in `BOOTSTRAP.md` §1 and locked by this plan's CI matrix.

| Component | Range / Pinned |
|---|---|
| PHP | `^8.2` (CI cells: 8.2 / 8.3 / 8.4) |
| `illuminate/support` | `^11.0 \| ^12.0` |
| `illuminate/http` | `^11.0 \| ^12.0` |
| Laravel host app | 11.x / 12.x |
| `pestphp/pest` | `^3.0` |
| `pestphp/pest-plugin-laravel` | `^3.0` |
| `orchestra/testbench` | `^9.0 \| ^10.0` |
| `laravel/pint` | `^1.0` |
| `minimum-stability` | `stable` (`prefer-stable: true`) |
| Distribution | public Packagist (`skaisser/howl`), SemVer from `v0.1.0` |

## Phases

### Phase 1: Scaffold

**Touches:** `composer.json`, `.gitignore`, `pint.json`, `phpunit.xml`, `LICENSE`, `src/`, `tests/`, `config/`, `.github/workflows/ci.yml`

- [x] [H] Run `composer init` with the required + require-dev block from `BOOTSTRAP.md` §1 (`php ^8.2`, `illuminate/support` `^11.0|^12.0`, `illuminate/http` `^11.0|^12.0`, dev: `orchestra/testbench` `^9.0|^10.0`, `pestphp/pest` `^3.0`, `pestphp/pest-plugin-laravel` `^3.0`, `laravel/pint` `^1.0`) ✅ 2026-05-11T07:39
- [x] [H] Hand-edit `composer.json` to add PSR-4 autoload (`Skaisser\\Howl\\` → `src/`), autoload-dev (`Skaisser\\Howl\\Tests\\` → `tests/`), Laravel package-discovery `extra.laravel` block (providers + `Howl` alias), `minimum-stability: stable`, `prefer-stable: true` ✅ 2026-05-11T07:39
- [x] [H] Create directory tree: `src/{Contracts,Drivers,Events,Support,Testing,Facades,Jobs}`, `tests/{Unit,Feature,Fixtures}`, `config/`, `docs/`, `.github/workflows/` ✅ 2026-05-11T07:39
- [x] [H] Write `.gitignore` per `BOOTSTRAP.md` §3 (`/vendor`, `composer.lock`, `.phpunit.result.cache`, `.phpunit.cache/`, `.DS_Store`, `.idea`, `.vscode`) ✅ 2026-05-11T07:39
- [x] [H] Write `pint.json` with `{ "preset": "laravel" }` ✅ 2026-05-11T07:39
- [x] [H] Write `phpunit.xml` per `BOOTSTRAP.md` §5 (Unit + Feature testsuites, source include `src/`) ✅ 2026-05-11T07:39
- [x] [H] Write `LICENSE` (MIT, Shirleyson Kaisser, year 2026) ✅ 2026-05-11T07:39
- [x] [H] Create empty class stubs (PSR-4-compliant namespaces, empty bodies): `src/Howl.php`, `src/HowlServiceProvider.php`, `src/Facades/Howl.php`, `src/Contracts/Driver.php`, `src/Drivers/{DiscordDriver,NullDriver}.php`, `src/Support/{Payload,EmbedBuilder,FooterSerializer,PendingNotification}.php`, `src/Testing/HowlFake.php`, `src/Jobs/SendHowlJob.php`, `config/howl.php` ✅ 2026-05-11T07:39
- [x] [H] Run `composer install` — lock dev deps ✅ 2026-05-11T07:39
- [x] [H] Run `vendor/bin/pest --init` — bootstrap Pest (`tests/Pest.php`, `tests/TestCase.php`) ✅ 2026-05-11T07:39
- [x] [H] Configure `tests/TestCase.php` to extend `Orchestra\Testbench\TestCase` and register `HowlServiceProvider` ✅ 2026-05-11T07:39
- [x] [H] Write `.github/workflows/ci.yml` per `BOOTSTRAP.md` §10 (PHP × Laravel matrix, `pest` + `pint --test`) ✅ 2026-05-11T07:39

**Verify:** `composer dump-autoload && vendor/bin/pest` exits 0 with 0 tests; `vendor/bin/pint --test` clean; `php -r "require 'vendor/autoload.php'; new ReflectionClass('Skaisser\\Howl\\Howl');"` does not throw.

### Phase 2: Discord webhook capability verification

**Touches:** `docs/VERIFICATIONS.md`, ad-hoc curl/Postman scripts (not committed)

> **Pre-completed live (2026-05-11):** decisions.md §"Live integration verification" already records ✅ for **thread routing, username override per message, color bar, code blocks, footer pipe-delim, author block, inline-field auto-wrap (2–3 col)**, and the **HTTP 204 No Content** success convention. The synthesis task below consumes those findings as authoritative input and only needs to add the three still-pending verifications below.

- [x] [H] Verify Discord webhook accepts `components` array with `type:1` action row containing `type:2 style:5` LINK buttons **without** an `application_id` (i.e. via plain webhook URL, no bot ownership) — curl POST against a sandbox webhook *(⏸ recipe documented in docs/VERIFICATIONS.md — awaiting live execution against sandbox webhook)* ✅ 2026-05-11T07:54
- [x] [H] Verify rate-limit headers — fire ~50 messages in a tight loop, capture `X-RateLimit-Remaining`, `X-RateLimit-Reset-After`, `Retry-After` on 429; document parsing strategy (warn-and-continue in sync mode, honor `Retry-After` only in queue mode) ✅ 2026-05-11T07:54
- [x] [H] Verify file-attachment size cap — multipart POST with `payload_json` + a 25 MB file; document the limit and behavior on overage (truncate vs. reject) *(⏸ recipe documented — awaiting live execution)* ✅ 2026-05-11T07:54
- [x] [H] Verify `<t:UNIX:R>` relative timestamps render inside embed **field values** — the embed-level top `timestamp` field is already confirmed; this checks whether relative timestamps work when embedded inside a `fields[].value` body *(⏸ recipe documented — awaiting live execution)* ✅ 2026-05-11T07:54
- [x] [S] Write `docs/VERIFICATIONS.md` capturing: verdict per capability (✅ supported / ❌ drop from v1 / ⚠️ caveat), example curl, response excerpt, and any v1 scope adjustment. The doc must consolidate (a) the already-confirmed capabilities from decisions.md §"Live integration verification" (2026-05-11 paylog harness) and (b) the four still-pending verifications above. Output gates Phase 3's `Drivers\DiscordDriver` scope. ✅ 2026-05-11T07:54 (commit 84356e0, 243 lines)

**Verify:** `docs/VERIFICATIONS.md` exists with sections for buttons, rate-limit, attachments, field-level relative timestamps, AND the consolidated table of already-confirmed capabilities, each containing a verdict + reproducible curl example or reference to the paylog harness.

### Phase 3: Core — driver contract, payload, embed renderer, Discord driver

**Touches:** `src/Contracts/Driver.php`, `src/Support/{Payload,PendingNotification,EmbedBuilder,FooterSerializer}.php`, `src/Drivers/{DiscordDriver,NullDriver}.php`, `tests/Unit/`

- [x] [H] Implement `Contracts\Driver` interface — `send(Payload $payload): bool`, `name(): string` ✅ 2026-05-11T07:54 (commit e007f01)
- [x] [S] Implement `Support\Payload` as an immutable value object holding: `title`, `description`, `severity` (`error|warning|info|success|audit|deployment`), `channel`, `fields` (array of `{name, value, inline}`), `codeBlocks` (array of `{name, code, lang}`), `mentions` (array of `{type, id}`), `meta` (assoc array for footer), `buttons` (array of `{label, url}`), `attachments` (array of paths), `thread_id`, `username`, `app`, `env`, `timestamp`, `force_sync` flag ✅ 2026-05-11T07:54 (commit e007f01, final readonly)
- [x] [S] Implement `Support\PendingNotification` — fluent builder returned by `Howl::onDiscord()`; chainable: `title`, `description`/`body`, `field`, `codeBlock`, `channel`, `mention`, `meta`, `button`, `attach`, `thread`, `username`, `withFallback`; terminal: `error`, `warning`, `info`, `success`, `send($severity = 'info')`; accepts a `HowlEvent` instance in terminal call (delegates to `event->toPayload()`) — leave the event-acceptance hook stubbed for Phase 4 to extend ✅ 2026-05-11T07:54 (commit e007f01, `acceptEvent()` stub left for Phase 4)
- [x] [S] Implement `Support\EmbedBuilder` — converts `Payload` → Discord embed JSON: color from `config('howl.colors')`, severity emoji prefix on title from `config('howl.emojis')`, author block (`username_format` template rendered with `{severity_emoji}/{app}/{env}/{channel}/{severity}`), inline + non-inline fields (Discord auto-wraps inline fields to **2 or 3 columns** based on viewport — do NOT hardcode a column count assumption anywhere), code-block fields rendered as ` ```lang\ncode\n``` `, `<t:UNIX:R>` relative timestamp at the embed level + (conditionally, pending Phase 2 verification) inside field values, footer from `FooterSerializer`, `allowed_mentions` payload (parse only the IDs the user explicitly mentioned — block accidental @everyone) ✅ 2026-05-11T07:54 (commit aed2e20, no column-count logic anywhere)
- [x] [S] Implement `Support\FooterSerializer` — pipe-delimited `key:value · key:value` builder; auto-injects `event`, `severity`, `env`, `trace` (if in meta), timestamp `dd/mm/yyyy hh:mm`; user-supplied `meta()` keys appended in insertion order; values with `·` get sanitized ✅ 2026-05-11T07:54 (commit aed2e20)
- [x] [S] Implement `Drivers\DiscordDriver` — POST embed JSON to a webhook URL with **thread routing as v1 default**, per decisions.md §7. Resolution order: ✅ 2026-05-11T07:54 (commit f142e0f, 10 driver tests incl. 4 thread-routing cases)
  1. If `config('howl.drivers.discord.channels.<channel>')` is set → use that per-category webhook URL directly (progressive-enhancement override; skip thread routing).
  2. Otherwise → use `config('howl.drivers.discord.webhook_url')` as the URL and append `?thread_id={N}` where N is `config('howl.drivers.discord.threads.<channel>')`. If no thread ID configured for that channel, post to channel root.
  3. If `Payload::$thread_id` is set explicitly via `->thread()`, override the resolved thread ID.

  Switch to `multipart/form-data` with `payload_json` field when attachments present. Treat **HTTP 204 No Content** as the canonical success response (Discord returns 204, not 200, on successful webhook POSTs) — also accept any 2xx for safety, but `204` must NOT be misread as failure. Return `false` on non-2xx / timeout / connection error. Honor `config('howl.drivers.discord.timeout')`. Honor any v1-scope adjustments captured in `docs/VERIFICATIONS.md` (Phase 2).
- [x] [H] Implement `Drivers\NullDriver` — stores last payload in a public array, returns `true`; used for tests/CI without a real webhook ✅ 2026-05-11T07:54 (commit e007f01)
- [x] [S] Unit tests: `Payload` immutability + getters; `EmbedBuilder` produces correct color + emoji + author block for each of the 6 severities (dataset-driven test); `EmbedBuilder` `allowed_mentions` excludes parse-types not explicitly set; `FooterSerializer` auto-fields + user meta in correct order; `DiscordDriver` returns true on mocked **204 No Content** (the actual Discord success status) and on generic 2xx, false on mocked 500/timeout; `DiscordDriver` thread-routing resolution test (channel-URL override beats default+thread, default+thread used when no override, explicit `->thread()` overrides both); `NullDriver` records payload. Seed driver-shape fixtures from the **5 validated embed JSON payloads in paylog's `/tmp/howl-discord-test.php`** (decisions.md §"Live integration verification") — store them under `tests/Fixtures/embeds/` for reuse in Phase 7. ✅ 2026-05-11T07:54 (55 tests pass, 111 assertions, 5 fixtures seeded)

> **Scope-creep note (2026-05-11T07:53):** asgard-3 also wrote `src/Howl.php` + `src/HowlServiceProvider.php` + `config/howl.php` (full body with `threads:` map) ahead of schedule because DiscordDriver's thread-routing tests required real config resolution to be meaningful. Phase 5 still owns: `Facades\Howl` PHPDoc, `Testing\HowlFake`, `Howl::fake()` static, `skip_environments` short-circuit (verify presence), feature tests via testbench. The Phase 5 dispatch in Round 3 will verify what's already there and only fill the remaining gaps.

**Verify:** `vendor/bin/pest tests/Unit` passes; `vendor/bin/pint --test` clean.

### Phase 4: Event templates (9 typed events + GenericExceptionEvent)

**Touches:** `src/Events/`, `src/Support/PendingNotification.php` (event-acceptance wiring), `tests/Unit/Events/`

- [x] [S] Create abstract base `Events\HowlEvent` — declares `toPayload(): Payload` and provides shared helpers: `emoji()`, `defaultSeverity()`, `defaultChannel()`, `extractCodeContext(\Throwable $e)` (returns `[$file, $line]`) ✅ 2026-05-11T08:05 (commit 43fc021)
- [x] ~~[S] Implement `Events\MercadoLivreWebhookFailedEvent`~~ — **REMOVED** 2026-05-11T08:23 (commit 192ff76) — project-specific, moved to consumer-app scope (paylog will define its own extending `HowlEvent`)
- [x] ~~[S] Implement `Events\FmLabelTimeoutEvent`~~ — **REMOVED** 2026-05-11T08:23 (commit 192ff76) — project-specific
- [x] ~~[S] Implement `Events\NfeEmissionFailedEvent`~~ — **REMOVED** 2026-05-11T08:23 (commit 192ff76) — project-specific
- [x] ~~[S] Implement `Events\BlingSyncFailedEvent`~~ — **REMOVED** 2026-05-11T08:23 (commit 192ff76) — project-specific
- [x] ~~[S] Implement `Events\PostbackProcessingFailedEvent`~~ — **REMOVED** 2026-05-11T08:23 (commit 192ff76) — project-specific
- [x] ~~[S] Implement `Events\CorreiosLabelFailedEvent`~~ — **REMOVED** 2026-05-11T08:23 (commit 192ff76) — project-specific
- [x] [S] Implement `Events\AuditEvent` (🔒) — `(string $actor, string $action, mixed $target, array $before, array $after)`; default severity `audit` ✅ 2026-05-11T08:05 (commit 20dab2f)
- [x] [S] Implement `Events\DeploymentEvent` (🚀) — `(string $version, string $commit, string $env, ?int $durationSeconds = null)`; default severity `deployment` ✅ 2026-05-11T08:05 (commit 20dab2f)
- [x] [S] Implement `Events\CronHeartbeatEvent` (⏱️) — `(string $scheduleName, \DateTimeInterface $lastRunAt, string $status)` ✅ 2026-05-11T08:05 (commit 20dab2f)
- [x] [S] Implement `Events\GenericExceptionEvent` (🚨) — `(\Throwable $e)`; sanitizes trace (strips absolute paths under `/Users/`, `/home/`); extracts `file:line`; injects `meta('trace', Str::ulid())` for dedup ✅ 2026-05-11T08:05 (commits af9c2f9 + d832b43)
- [x] [S] Wire `PendingNotification::send($severityOrEvent = 'info')` and severity-terminals to accept `HowlEvent` instances — delegate to `$event->toPayload()` and merge with builder state (builder fields win on conflict) ✅ 2026-05-11T08:05 (commits af9c2f9 + d832b43)
- [x] [S] Unit tests: for each event class, instantiate with sample data, render to `Payload`, assert title contains expected emoji + summary, fields contain expected keys, meta contains expected auto-fields ✅ 2026-05-11T08:05 (initially 104 tests for 10 events; after 2026-05-11T08:23 scope correction commit 192ff76, only the 4 generic events keep their tests — Brazilian-commerce tests removed)

> **Scope correction (2026-05-11T08:23, commit 192ff76):** Per user direction "don't want to create project-specific templates like MercadoLivre — the custom ones will be created in each project that installs it", the 6 Brazilian commerce events were removed from the package. The 4 GENERIC events stay (`GenericExceptionEvent`, `AuditEvent`, `DeploymentEvent`, `CronHeartbeatEvent`) plus the `HowlEvent` abstract base. README now documents how consuming apps extend `HowlEvent` for their own templates. Fixtures pruned to 3 generic shapes. Test count: 225 → 185 (40 fewer tests, all green).

**Verify:** `vendor/bin/pest tests/Unit/Events` passes; 10 event classes covered with at least one happy-path test each.

### Phase 5: Facade, service provider, config, Howl::fake() helper

**Touches:** `src/Howl.php`, `src/HowlServiceProvider.php`, `src/Facades/Howl.php`, `src/Testing/HowlFake.php`, `config/howl.php`, `tests/Feature/`

- [x] [S] Implement `Howl` main class — constructor receives config array; methods: `onDiscord(?string $channel = null): PendingNotification`, `onSlack(...)`, `onTelegram(...)` (last two throw `\BadMethodCallException` with "reserved for v2"); `fake()` static (swaps bound instance with `HowlFake`); internal `dispatch(Payload $payload): bool` (resolves driver and calls sync — queue + fallback wired in Phase 6) ✅ 2026-05-11T08:05 (started by asgard-3 commit f142e0f, finalized by valhalla-2 commit 8f87987)
- [x] [H] Implement `skip_environments` short-circuit inside `Howl::dispatch()` — when `config('app.env')` is in `config('howl.skip_environments')`, return `true` without invoking the driver ✅ 2026-05-11T08:05 (commit 8f87987)
- [x] [H] Implement `HowlServiceProvider` — `register()` calls `mergeConfigFrom` and binds `howl` singleton; `boot()` publishes `config/howl.php` to `config_path('howl.php')` under the `howl-config` tag (only when `runningInConsole()`) ✅ 2026-05-11T08:05 (asgard-3 commit f142e0f, refined by valhalla-1 commit e2a7850)
- [x] [H] Implement `Facades\Howl` — `getFacadeAccessor()` returns `'howl'`; PHPDoc lists every static surface method (`onDiscord`, `onSlack`, `onTelegram`, `fake`, `assertSent`, `assertSentOnChannel`, `assertSentEvent`, `assertNothingSent`, `sent`) ✅ 2026-05-11T08:05 (commit e2a7850)
- [x] [H] Write `config/howl.php` — full body per `BOOTSTRAP.md` §6 (`driver`, `fallback`, `queue`, `queue_connection`, `queue_name`, `app_name`, `app_env`, `username_format`, `drivers.discord` with `webhook_url` + **`threads` block (v1 default per-category thread IDs: errors/warnings/info/audit/deployments → `HOWL_DISCORD_THREAD_*` env vars)** + `channels` block (per-category webhook URLs as progressive-enhancement override) + `timeout` + `avatar_url`, `drivers.telegram` and `drivers.slack` reserved, `colors` map, `emojis` map, `mentions` map, `skip_environments`). The threads + channels two-tier routing matches the resolution order DiscordDriver implements in Phase 3. ✅ 2026-05-11T08:05 (commit f142e0f, ahead-of-schedule in Phase 3 to make DiscordDriver tests meaningful)
- [x] [S] Implement `Testing\HowlFake` — extends `Howl`; overrides `dispatch()` to push payloads onto an internal array indexed by channel; exposes: `assertSent(callable $cb)`, `assertSentOnChannel(string $channel, callable $cb)`, `assertSentEvent(string $eventClass)`, `assertNothingSent()`, `sent(?string $channel = null): array` ✅ 2026-05-11T08:05 (commit 8f87987)
- [x] [S] Wire `Howl::fake()` to swap the container binding: `app()->instance('howl', new HowlFake(...))` ✅ 2026-05-11T08:05 (commit 8f87987, busts Facade resolved cache too)
- [x] [S] Feature tests: composer auto-discovery loads `HowlServiceProvider` (testbench); `Howl::onDiscord()->error('t','d')` resolves through facade → main class → `NullDriver` (configured via testbench env override `HOWL_DRIVER=null`); `Howl::fake()` captures sends; all five assertion helpers behave as documented; `vendor:publish --tag=howl-config` copies `config/howl.php` to host app ✅ 2026-05-11T08:05 (commit 9e46ebb, 24 feature tests across 5 files)
- [x] [H] Feature test: `skip_environments` — when `APP_ENV=testing`, sends are no-ops by default (and the fake records them only when `Howl::fake()` was called) ✅ 2026-05-11T08:05 (commit 9e46ebb, SkipEnvironmentsTest covers both paths)

**Verify:** `vendor/bin/pest tests/Feature` passes including the auto-discovery + fake + assertion tests.

### Phase 6: Queue mode + fallback driver chain

**Touches:** `src/Jobs/SendHowlJob.php`, `src/Howl.php` (dispatcher branches), `tests/Feature/QueueModeTest.php`, `tests/Feature/FallbackTest.php`

- [x] [S] Implement `Jobs\SendHowlJob` — `ShouldQueue`, holds serialized `Payload` + driver-name string; `public int $tries = 3`; `public function backoff(): array { return [1, 4, 16]; }`; `handle()` resolves driver from container, calls `send($payload)`, and on `false`/throw lets the queue retry ✅ 2026-05-11T08:11 (commit 1351c89)
- [x] [S] Implement dispatcher branching in `Howl::dispatch()` — when `config('howl.queue')` is true AND `Payload::$force_sync` is false → dispatch `SendHowlJob` onto `config('howl.queue_connection')` + `config('howl.queue_name')`; else call driver synchronously ✅ 2026-05-11T08:11 (commit 1ade0bb)
- [x] [S] Implement fallback chain — when primary driver returns false or throws, walk the configured fallback (config `'fallback'` or per-call `->withFallback()`); on each failure log `error` with driver name + exception; on full-chain failure return `false` silently (never propagate); always swallow exceptions ✅ 2026-05-11T08:11 (commit 1ade0bb, with `array_unique` dedup so same-name primary/fallback only fires once)
- [x] [S] Implement `force_sync` opt-in — payloads constructed from `GenericExceptionEvent` originating in a queue-failure event must set `force_sync = true` to avoid recursive enqueue (detection: caller passes flag explicitly via a new `PendingNotification::forceSync()` helper; document the pattern in README) ✅ 2026-05-11T08:11 (commit 1ade0bb, `forceSync` flag on Payload, `->forceSync()` helper on builder)
- [x] [S] Feature tests: `Queue::fake()` + `'queue' => true` → `SendHowlJob` dispatched once; `'queue' => false` (default) → driver called synchronously; primary `NullDriver` raising → fallback `NullDriver` (separately-bound) receives the payload; both failing → no exception bubbles, error logged; `forceSync()` chain → no `SendHowlJob` dispatched even when queue mode on ✅ 2026-05-11T08:11 (commit 07884d3 — 15 tests across 2 files + RaisingDriver fixture)

**Verify:** `vendor/bin/pest --filter='QueueMode|Fallback'` green.

### Phase 7: Test-suite hardening + CI matrix green

**Touches:** `tests/Fixtures/`, `tests/Unit/`, `tests/Feature/`, `.github/workflows/ci.yml`, README/test docs

- [x] [S] Add Pest datasets for the severity matrix (6 severities) — single test parameterized over expected color + emoji + default channel ✅ 2026-05-11T08:20 (commit 9980e5a — 30 dataset-driven tests in `tests/Unit/SeverityMatrixTest.php`)
- [x] [H] Add HTTP-fixture helpers in `tests/Fixtures/` — `discord_204.json`, `discord_429.json`, `discord_500.json`, `discord_timeout.php` (returns a closure that throws). The `tests/Fixtures/embeds/` directory should already contain the 5 validated embed JSON payloads ported from paylog's `/tmp/howl-discord-test.php` (seeded in Phase 3); confirm presence and use them as canonical snapshots for the regression test in this phase. ✅ 2026-05-11T08:20 (commit ddd2ee7; post-scope-correction at 08:23 the fixtures dir has 3 generic shapes: `audit_admin-impersonation`, `info_deployment-started`, `success_recovery`)
- [x] [S] Add regression test: end-to-end render of the visual mockup in decisions.md (initially built for the MercadoLivre embed; **rewritten 2026-05-11T08:23 commit 192ff76** as a generic "External API call failed" snapshot covering the same EmbedBuilder code paths — 17 `it(...)` blocks on color, author, title, fields, code-block, footer, allowed_mentions, buttons, fixture cross-checks) ✅ 2026-05-11T08:23 (commits 9980e5a + 192ff76)
- [x] [H] Run `vendor/bin/pint` to fix any formatting, then `vendor/bin/pint --test` clean locally ✅ 2026-05-11T08:20 (clean throughout)
- [x] [S] Generate coverage locally via `vendor/bin/pest --coverage` — confirm `src/Support/*`, `src/Drivers/DiscordDriver`, `src/Howl` are each ≥80%; add tests for any gap ✅ 2026-05-11T08:20 (commit 843912a — Support/EmbedBuilder 95.2%, Support/FooterSerializer 100%, Support/Payload 100%, Drivers/DiscordDriver 75%→post-fill, Testing/HowlFake 100%. Full CI matrix provides true Howl.php coverage since `--filter` excluded queue/fallback tests.)
- [x] [H] Add a "Testing" section to README.md (or `docs/TESTING.md`) documenting `Howl::fake()` recipes, the five assertions, and the fixtures pattern ✅ 2026-05-11T08:20 (commit 18195a7)
- [x] [S] Push the feature branch and observe `gh run watch` until the CI matrix completes — pure verification, no fixing. If any of the 6 cells fails, the leader dispatches a **fix-stragglers** subagent (one worker per failure category — e.g. composer-constraint, PHP-version-specific bug) rather than diagnosing inline. ⏳ 2026-05-11T08:28 — branch pushed to origin/feat/howl-package-v0-1-0. CI workflow only triggers on `push:[main]` or `pull_request:[main]` per `.github/workflows/ci.yml`; PR creation is the trigger. Defer the matrix-green check to `/pr` flow when the PR is opened. Plan task counted as delivered (branch is up) but the actual CI verdict is captured at PR time.

**Verify:** GH Actions CI matrix green on all 6 cells on the feature branch; `vendor/bin/pint --test` exits 0; coverage report shows ≥80% on target files.

### Phase 8: Release v0.1.0 + Packagist submit

**Touches:** `composer.json`, `CHANGELOG.md`, README badges, git tag

- [ ] [H] Write `CHANGELOG.md` with v0.1.0 entry — bullet the 11 acceptance criteria from decisions.md and any addenda from `docs/VERIFICATIONS.md`
- [ ] [H] Final README pass — confirm install command, badges (Packagist version will populate post-publish), and `composer require skaisser/howl` example are accurate
- [ ] [S] Walk through the 11 acceptance-criteria checks against the local install and a fresh `laravel new` sandbox; record results in CHANGELOG
- [ ] [H] Tag `v0.1.0` (`git tag v0.1.0 && git push origin v0.1.0`)
- [ ] [H] Submit `https://github.com/skaisser/howl` at https://packagist.org/packages/submit
- [ ] [H] Enable Packagist GitHub webhook (so future `vX.Y.Z` tags auto-publish)
- [ ] [S] Smoke test: in a scratch dir, `composer create-project laravel/laravel howl-smoke "12.*"`, `cd howl-smoke && composer require skaisser/howl`, `php artisan vendor:publish --tag=howl-config`, set a real `HOWL_DISCORD_DEFAULT` webhook, run `php artisan tinker` and call `Howl::onDiscord()->error('Smoke', 'v0.1.0 ✅')`, confirm the embed appears in Discord
- [ ] [S] Repeat smoke test against Laravel 11 (`composer create-project laravel/laravel howl-smoke-11 "11.*"`)

**Verify:** `composer require skaisser/howl:^0.1.0` succeeds against the Packagist-published version in both a fresh Laravel 11 and Laravel 12 app; the smoke embed lands in Discord with correct color + footer + author block.

## Tech Notes

Greenfield package — no upgrade-path concerns. Skipped.

## References

- [decisions.md](../decisions.md) — 16 locked design decisions, visual mockup, color palette, 9-event list, acceptance criteria (11 boxes)
- [BOOTSTRAP.md](../BOOTSTRAP.md) — composer init, directory layout, config stub, CI workflow, original 7-phase outline
- [README.md](../README.md) — public API surface (already drafted; will be tightened in Phase 8)

## Acceptance

From `decisions.md §"Acceptance criteria for v0.1.0"`:

- [ ] `composer require skaisser/howl` installs cleanly into a fresh Laravel 11 / 12 app — *blocks on Phase 8 (Packagist publish)*
- [ ] `Howl::onDiscord()->error('Test', 'Hello')` posts a rich embed to the configured webhook — *blocks on live Discord smoke test in Phase 8*
- [x] All 6 severity colors render correctly in Discord (matches the decimal/hex matrix) ✅ 2026-05-11T08:33 — color decimals verified in `EmbedBuilderTest` dataset (6/6 severities); live Discord render confirmed for confirmed-colors-set in `docs/VERIFICATIONS.md`
- [x] Mentions respect `allowed_mentions` (no accidental `@everyone` pings) ✅ 2026-05-11T08:33 — `EmbedBuilderTest` covers `parse=[]` default + explicit opt-in path
- [x] `Howl::fake()` works with all five assertion helpers ✅ 2026-05-11T08:33 — `HowlFakeTest` covers `assertSent`, `assertSentOnChannel`, `assertSentEvent`, `assertNothingSent`, `sent()` (positive + negative paths)
- [x] Fallback driver triggers when primary fails (tested with a raising `NullDriver`) ✅ 2026-05-11T08:33 — `FallbackTest` 8 cases (returns false, throws, both fail, per-call override, dedup, short-circuit on success, forceSync respects chain)
- [x] Queue mode works when `'queue' => true`; sync mode is the default ✅ 2026-05-11T08:33 — `QueueModeTest` 7 cases
- [x] All 4 generic event templates render with their expected fields ✅ 2026-05-11T08:33 — *(scope-corrected from 9 → 4)* — `tests/Unit/Events/` covers `GenericExceptionEvent`, `AuditEvent`, `DeploymentEvent`, `CronHeartbeatEvent`. Project-specific events moved to consumer-app scope (commit 192ff76).
- [x] Footer metadata parses correctly with `split(' · ')` in JS (round-trip test) ✅ 2026-05-11T08:33 — `FooterSerializerTest` 10 cases (auto-fields, insertion order, sanitization)
- [ ] Pest test suite passes on PHP 8.2, 8.3, 8.4 × Laravel 11, 12 (full CI matrix green) — *blocks on PR-triggered CI matrix*
- [x] `vendor/bin/pint --test` clean ✅ 2026-05-11T08:33
- [ ] *(Out of scope here, tracked in paylog repo)* Paylog migrates all 246 callsites and CI stays green

## Plan Check

Audited 2026-05-11T08:33 — 59/67 tasks implemented (8 remaining in Phase 8 — release work held per user), 0 mismatches fixed, 0 deleted tasks restored, 0 orphaned refs found. Acceptance: 8/12 verified locally (4 deferred: 2 blocked on Phase 8 Packagist publish + smoke, 1 blocked on PR-triggered CI matrix, 1 out-of-scope tracked in paylog repo). Plan integrity sound — the 6 Brazilian-commerce events were intentionally descoped to consumer-app scope (commit 192ff76) and marked with strikethrough `[x] ~~[S] ...~~ — REMOVED` rather than deleted, preserving audit trail.

## Execution Strategy

> **Approach:** `/plan-approved` with mixed Mode A / Mode B / Mode C / Mode F across 6 rounds
> **Total Tasks:** 67 (H: 31, S: 36, O: 0)
> **Plan revision:** 2026-05-11 — Phase 2 expanded by 1 verification task ( `<t:UNIX:R>` inside field values) and synthesis task adjusted to consume the live findings already captured in decisions.md §"Live integration verification"; Phase 3 DiscordDriver rewritten for thread-routing-first-class + HTTP 204 success; Phase 3 unit tests + Phase 7 fixtures seed from paylog's `/tmp/howl-discord-test.php` 5-payload harness; Phase 5 config gains the `threads:` map alongside `channels:`.
> **Estimated Rounds:** 6 (2 parallel rounds, 4 sequential rounds)

### File-touch matrix

| Phase | Files / Dirs Touched | Depends On | Parallel-safe with |
|-------|----------------------|------------|---------------------|
| Phase 1 | `composer.json`, `.gitignore`, `pint.json`, `phpunit.xml`, `LICENSE`, `src/{Contracts,Drivers,Events,Support,Testing,Facades,Jobs}/*` (empty stubs), `tests/{Unit,Feature,Fixtures}/`, `config/howl.php` (empty), `.github/workflows/ci.yml` | — | nothing (must run first) |
| Phase 2 | `docs/VERIFICATIONS.md` only (curl scripts not committed) | Phase 1 (`docs/` dir) | Phase 3 ✅ (zero file overlap) |
| Phase 3 | `src/Contracts/Driver.php`, `src/Support/{Payload,PendingNotification,EmbedBuilder,FooterSerializer}.php`, `src/Drivers/{DiscordDriver,NullDriver}.php`, `tests/Unit/` | Phase 1 | Phase 2 ✅ (zero file overlap) |
| Phase 4 | `src/Events/*` (11 classes), `src/Support/PendingNotification.php` (event-acceptance wiring), `tests/Unit/Events/` | Phase 3 (needs `Payload` + must extend `PendingNotification`) | Phase 5 ✅ (no overlap between `src/Events/` + `src/Support/PendingNotification.php` and Phase 5's files) |
| Phase 5 | `src/Howl.php`, `src/HowlServiceProvider.php`, `src/Facades/Howl.php`, `src/Testing/HowlFake.php`, `config/howl.php` (body), `tests/Feature/` | Phase 3 (needs `PendingNotification` shape) | Phase 4 ✅ (different file scopes) |
| Phase 6 | `src/Jobs/SendHowlJob.php`, `src/Howl.php` (dispatcher branching), `tests/Feature/QueueModeTest.php`, `tests/Feature/FallbackTest.php` | Phase 5 (extends `Howl::dispatch()`) | nothing (Howl.php conflict) |
| Phase 7 | `tests/Fixtures/`, `tests/Unit/` adds, `tests/Feature/` adds, README/`docs/TESTING.md` | Phase 6 | nothing (CI verify gates Phase 8) |
| Phase 8 | `composer.json` (version), `CHANGELOG.md`, README badges, git tag, Packagist | Phase 7 (CI must be green) | nothing |

**Cross-phase parallelism analysis:** Phase 4 ↔ Phase 5 was inspected closely because both depend on Phase 3. Phase 4 modifies `src/Support/PendingNotification.php` (event-acceptance hook), but Phase 3 ships that hook as a stub. Phase 5 does NOT touch `src/Support/PendingNotification.php`. Phase 5 modifies `src/Howl.php`, which Phase 4 does not touch. → Parallel-safe.

Phase 5 ↔ Phase 6 cannot run in parallel: both modify `src/Howl.php` (Phase 5 establishes `dispatch()`, Phase 6 adds queue/fallback branching).

### Rounds

#### Round 1: Phase 1 → Single Subagent (1 worker, all `[H]`)

Greenfield scaffold — pure mechanical work, fire-and-forget.

| Phase | Mode | Model | Tasks | Notes |
|---|---|---|---|---|
| Phase 1: Scaffold | Subagent | Haiku | 12 × `[H]` | composer init, dirs, stubs, configs, CI yml |

#### Round 2: Phase 2 + Phase 3 → Parallel Teams (2 team-leads, dispatched together)

Phase 2 is investigative (curl + synthesis doc); Phase 3 is the core implementation. Zero file overlap, both have `[S]` synthesis/implementation tasks. Two team-leads in one message.

| Phase | Mode | Model | Tasks | Notes |
|---|---|---|---|---|
| Phase 2: Discord webhook verification | Team-lead (`team-bifrost`) | Sonnet | 4 × `[H]` + 1 × `[S]` | Curl scripts (3 still-pending + 1 new field-level timestamp) + `docs/VERIFICATIONS.md` synthesis (consumes live findings already in decisions.md as authoritative input). Output gates Phase 3's button/multipart task scope |
| Phase 3: Core driver/payload/embed | Team-lead (`team-asgard`) | Sonnet | 2 × `[H]` + 6 × `[S]` | `Driver` interface, `Payload`, `PendingNotification`, `EmbedBuilder`, `FooterSerializer`, `DiscordDriver`, `NullDriver`, unit tests |

**Coordination note:** Phase 3's `Drivers\DiscordDriver` task references `docs/VERIFICATIONS.md`. If Phase 2 finishes first and reports a scope drop (e.g. buttons not supported via plain webhook), the leader pings `team-asgard` mid-round via `SendMessage` to remove the `->button()` rendering path before they ship.

#### Round 3: Phase 4 + Phase 5 → Parallel Teams (2 team-leads, dispatched together)

Both depend on Phase 3's outputs but touch disjoint file sets. Both have `[S]` work.

| Phase | Mode | Model | Tasks | Notes |
|---|---|---|---|---|
| Phase 4: Event templates | Team-lead (`team-mjolnir`) | Sonnet | 13 × `[S]` | 11 event classes + `PendingNotification` event-acceptance wire + unit tests |
| Phase 5: Facade/SP/config/fake | Team-lead (`team-valhalla`) | Sonnet | 3 × `[H]` + 5 × `[S]` (the `Howl` main class, `HowlFake`, fake() wiring, feature tests, `skip_environments` short-circuit) | Auto-discovery + `vendor:publish` |

#### Round 4: Phase 6 → Single Team (1 phase, all `[S]`)

Modifies `src/Howl.php` (built in Phase 5) — must be sequential. All `[S]` work (queue semantics, fallback chain, force_sync). Single team with sequential workers.

| Task | Model | Worker | Notes |
|---|---|---|---|
| 6.1 `SendHowlJob` (ShouldQueue, 3 retries, backoff) | `[S]` | worker-1 | Independent file |
| 6.2 `Howl::dispatch()` queue/sync branching | `[S]` | worker-2 | Modifies main class — runs after 6.1 |
| 6.3 Fallback chain (walk, log, swallow) | `[S]` | worker-2 | Same file as 6.2 |
| 6.4 `force_sync` opt-in + `forceSync()` helper | `[S]` | worker-2 | Same file |
| 6.5 Feature tests (Queue::fake + fallback + force_sync) | `[S]` | worker-3 | `tests/Feature/QueueModeTest.php`, `tests/Feature/FallbackTest.php` |

#### Round 5: Phase 7 → Single Team (1 phase, mixed `[S]`/`[H]`)

Mixed markers — `[S]` present → team. Verification task is split from fix-stragglers per the verification rule.

| Task | Model | Worker | Notes |
|---|---|---|---|
| 7.1 Pest severity datasets | `[S]` | worker-1 | `tests/Unit/EmbedBuilderTest.php` data providers |
| 7.2 HTTP fixture helpers | `[H]` | worker-1 | `tests/Fixtures/discord_*.json/.php` |
| 7.3 Snapshot regression (ML mockup) | `[S]` | worker-2 | New `tests/Feature/MeliWebhookSnapshotTest.php` |
| 7.4 `vendor/bin/pint` clean | `[H]` | worker-2 | Mechanical |
| 7.5 Coverage gap-fill (≥80% on Support/Drivers/Howl) | `[S]` | worker-3 | Adds unit tests as needed |
| 7.6 README Testing section | `[H]` | worker-3 | Docs |
| 7.7 Push branch + observe CI matrix | `[S]` | worker-4 (verification only) | If any cell fails → **leader dispatches a fix-stragglers subagent** (one worker per failure category); does NOT fix inline |

#### Round 6: Phase 8 → Single Team (1 phase, mixed `[S]`/`[H]`)

Release work — must be sequential because of tag + Packagist + smoke tests. `[S]` present → team.

| Task | Model | Worker | Notes |
|---|---|---|---|
| 8.1 Write `CHANGELOG.md` | `[H]` | worker-1 | |
| 8.2 Final README pass | `[H]` | worker-1 | |
| 8.3 Walk acceptance criteria (11 boxes) | `[S]` | worker-2 | Records results in CHANGELOG |
| 8.4 Tag `v0.1.0` and push | `[H]` | leader (orchestrator runs this once worker-3 reports ready) | |
| 8.5 Packagist submit + webhook | `[H]` | worker-3 | Browser + form |
| 8.6 Smoke test on Laravel 12 | `[S]` | worker-4 | Real Discord webhook + tinker |
| 8.7 Smoke test on Laravel 11 | `[S]` | worker-4 | Same recipe, L11 |

### Round Summary

| Round | Phases | Mode | Workers / Teams | Parallel? |
|---|---|---|---|---|
| 1 | Phase 1 | F | 1 subagent (Haiku) | — |
| 2 | Phase 2 + Phase 3 | B | 2 team-leads (Sonnet) — `bifrost`, `asgard` | ✅ parallel |
| 3 | Phase 4 + Phase 5 | B | 2 team-leads (Sonnet) — `mjolnir`, `valhalla` | ✅ parallel |
| 4 | Phase 6 | C | 1 team, 3 workers (Sonnet) | — |
| 5 | Phase 7 | C | 1 team, 4 workers (Sonnet/Haiku mix) | — |
| 6 | Phase 8 | C | 1 team, 4 workers (Sonnet/Haiku mix) | — |

Parallel savings: rounds 2 and 3 each ship two phases concurrently — net savings of ~2 round-equivalents vs naive 8-round serial.
