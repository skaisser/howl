---
id: "P-0005"
title: "feat: driver-agnostic facade API + channel failover/fan-out + rate-limit middleware"
type: feat
project: howl
branch: feat/driver-agnostic-api
base: homolog
tags: [api-redesign, facade, drivers, channel-fallback, rate-limit, hard-cut, path-to-v1]
backlog: null
dependsOn: ["P-0003"]
created: 2026-05-12T01:04
session_id: null
session: "ca1c12c4-eca2-423c-a1da-0ec265f7a0c4"
---

# feat: driver-agnostic facade API + channel failover/fan-out + rate-limit middleware

## Goal

Replace the misleading per-driver facade methods (`Howl::onDiscord/onSlack/onTelegram`) with a driver-agnostic, severity-terminal API on the `Howl` class itself (`Howl::error/warning/info/audit/deployment/success(HowlEvent|string)` plus `Howl::on(?string)` and `Howl::driver(string)` pre-chain builders), add a default + optional backup channel with config-selected `failover` or `fan_out` semantics, and ship an opt-in queue rate-limit middleware on `SendHowlJob` so consumers running queued Howl can respect per-transport rate limits. **Hard-cut** the three legacy `onX()` methods — delete entirely, no deprecation aliases — since paylog is the only known consumer and its migration is a one-shot sed (handled in a separate paylog-side commit).

## Non-Goals

- **Do NOT add Slack or Telegram driver implementations.** Those land in P-0006. `Howl::driver('slack')->error(...)` will throw `InvalidArgumentException("unknown driver 'slack'")` at dispatch time (matches current `resolveDriver()` behavior at `src/Howl.php:158-165`). This plan only stops baking driver names into method signatures.
- **Do NOT ship deprecation aliases.** Hard-cut means hard-cut. No `trigger_error(..., E_USER_DEPRECATED)`. No static guards. No `@deprecated` annotations. The 38 callsites across `src/` and `tests/` are migrated in Phase 4.
- **Do NOT tag a release in this plan.** P-0005 merges to `main` as unreleased work. `v1.0.0` tag happens only at the end of P-0008 (docs + release plan), after Slack/Telegram drivers (P-0006) and 100% test coverage (P-0007) land.
- **Do NOT touch the existing `'fallback'` driver-level config key.** Driver-to-driver failover (`config('howl.fallback')` at `config/howl.php:22`) stays untouched and is orthogonal to the new channel-level failover/fan-out. Channel logic runs first; on full channel-set failure, the existing driver fallback chain runs on the primary channel only (avoid combinatorial blow-up).
- **Do NOT change `HowlEvent::channel()` semantics.** Event-defined channel still wins over `config('howl.channel')`. The new `Howl::on($channel)` override wins over BOTH — explicit per-call beats event-defaulted beats config-defaulted.
- **Do NOT add per-severity driver routing.** Single `'driver'` config key stays. Multi-driver consumers either swap config or use per-call `Howl::driver(...)` overrides.
- **Do NOT add HowlFake per-driver assertions (`assertSentVia`).** Those land in P-0007 (coverage plan).

## Context

- `src/Howl.php:30-59` — `onDiscord()` returns `PendingNotification`; `onSlack()` / `onTelegram()` throw `BadMethodCallException`. All three methods to be DELETED in Phase 4.
- `src/Howl.php:69-120` — `dispatch(Payload $payload)` is the central path. Line 78 resolves the driver from `$this->config['driver']`. Lines 89-117 walk the existing driver-level fallback chain. Phase 2 plumbs `$payload->driver` override; Phase 3 wraps the chain in per-channel dispatch.
- `src/Howl.php:158-165` — `resolveDriver()` only knows `discord` and `null`. `slack`/`telegram` throw `InvalidArgumentException` until P-0006 lands.
- `src/Support/PendingNotification.php:121-127` — `channel()` builder (clone-and-set). New sibling `driver()` follows the same pattern in Phase 2.
- `src/Support/PendingNotification.php:264-417` — Severity terminal methods (`error/warning/info/success/audit/deployment`) ALREADY EXIST on the builder. Phase 1 adds delegating proxies on the `Howl` class itself so consumers can call `Howl::error($event)` without first chaining via `Howl::onDiscord()`.
- `src/Support/PendingNotification.php:430` — comment "Allow `Howl::onDiscord()->send(new SomeEvent())` shorthand" is stale — Phase 4 cleanup.
- `src/Support/PendingNotification.php:449-504` — `buildPayload()`. Phase 3 adds `config('howl.channel')` as the final fallback in the channel precedence chain.
- `src/Support/Payload.php:7-36` — readonly Payload. Phase 2 adds `?string $driver = null` field at the end of the constructor (preserves backward compatibility for any direct Payload instantiation in tests).
- `src/Facades/Howl.php:8-10` — `@method` PHPDoc for `onDiscord/onSlack/onTelegram`. Phase 1 ADDS new methods; Phase 4 REMOVES the three legacy entries.
- `src/Testing/HowlFake.php:13` — `class HowlFake extends Howl` → Phase 1's new severity methods on `Howl` auto-inherit into the fake. No fake-side changes needed.
- `src/Jobs/SendHowlJob.php` — currently has no `middleware()` method. Phase 5 adds it.
- `config/howl.php:9` — comment "Howl::onDiscord()->error(...)" is stale — Phase 4 cleanup.
- `config/howl.php:86-95` — `'telegram'` and `'slack'` driver config scaffolding exists (reserved for v2). Keep untouched; P-0006 populates.
- **Test callsites of legacy methods:** 38 total across 5 test files:
  - `tests/Feature/HowlFakeTest.php` — 13 callsites
  - `tests/Feature/FacadeTest.php` — 9 callsites
  - `tests/Feature/SeverityMismatchTest.php` — 4 callsites
  - `tests/Feature/EventDispatchTest.php` — 2 callsites
  - `tests/Feature/SkipEnvironmentsTest.php` — 1 callsite
  - All migrate in Phase 4 via mechanical `Howl::onDiscord(` → `Howl::on(` replacement, with deletion of any tests asserting `onSlack/onTelegram` throw `BadMethodCallException`.
- **Channel precedence (locked):** explicit per-call `Howl::on($c)` > `HowlEvent::channel()` > `config('howl.channel')`. Documented in Phase 3 tests.
- **Failover semantics:** try primary; on driver-reported delivery failure, retry once on backup channel; if both fail, walk driver fallback chain on the primary channel only.
- **Fan-out semantics:** dispatch sequentially to both channels in the same request; result `true` iff at least one channel succeeded. Per-channel failures logged via existing `Log::error` path. Doubles rate-limit consumption — documented as a caveat in P-0008.
- **Rate-limit middleware:** `Illuminate\Queue\Middleware\RateLimitedWithRedis` ships with Laravel, stable since L8. Composes with `$tries`/`backoff()` — rate-limit releases do NOT count against `$tries`. Opt-in via new `config('howl.rate_limiter_key')`; `null` preserves today's no-throttle behavior.
- **Test baseline:** 322 tests / 692 assertions (per `vendor/bin/pest --parallel`). Expect baseline + ~25-30 net new tests (additions across Phases 1/2/3/5 minus a handful of deleted `onSlack/onTelegram throws` assertions in Phase 4).
- **Path to v1.0.0 (4-plan sequence):** P-0005 (this) → P-0006 (Slack + Telegram drivers) → P-0007 (100% coverage + Pest 3/4 CI matrix) → P-0008 (VitePress docs + LLM docs + release artifacts → tag v1.0.0).

## Phases

### Phase 1: Severity-terminal API on `Howl` class + facade

**Touches:** `src/Howl.php`, `src/Facades/Howl.php`, `tests/Unit/HowlFacadeTest.php` (new)

- [ ] [S] Add 6 severity entry methods on `Howl` class: `error()`, `warning()`, `info()`, `audit()`, `deployment()`, `success()`. Each accepts `HowlEvent|string $titleOrEvent`, builds a fresh `PendingNotification`, delegates to its existing terminal method (`src/Support/PendingNotification.php:264-417`), returns `bool`.
- [ ] [S] Extract private `dispatchSeverity(string $severity, mixed $titleOrEvent): bool` helper on `Howl` that the six methods delegate to.
- [ ] [H] Add `Howl::on(?string $channel = null): PendingNotification` — returns a fresh `PendingNotification` with `->channel($channel)` applied if non-null.
- [ ] [H] Add `Howl::driver(string $name): PendingNotification` — returns a fresh `PendingNotification` with driver override pre-set (depends on Phase 2 plumbing; method signature lands here, behavior wired in Phase 2).
- [ ] [H] Update facade `@method` PHPDoc in `src/Facades/Howl.php` — ADD the 6 severity methods + `on()` + `driver()`. Do NOT remove `onDiscord/onSlack/onTelegram` yet — Phase 4 does that.
- [ ] [S] Create `tests/Unit/HowlFacadeTest.php` — assert: `Howl::error($event)` dispatches and returns `bool`; `Howl::error('Title')` dispatches with a built payload; `Howl::on('errors')->error($event)` chain sets channel before severity; chain order independence (`driver()->on()` ≡ `on()->driver()`); `Howl::warning/info/audit/deployment/success` each dispatch with correct severity.

**Verify:** `vendor/bin/pest --filter="HowlFacade"` AND `vendor/bin/pest --parallel` (full regression — no existing tests should break since Phase 1 is purely additive).

### Phase 2: Per-call driver override plumbing

**Touches:** `src/Support/PendingNotification.php`, `src/Support/Payload.php`, `src/Howl.php`, `tests/Unit/DriverOverrideTest.php` (new)

- [ ] [H] Add `PendingNotification::driver(string $name): static` — clone-and-set pattern matching `channel()` at line 121.
- [ ] [H] Add protected `?string $driver = null` field to `PendingNotification` (sibling of `$channel` field at line 15).
- [ ] [H] Add `?string $driver = null` final parameter to `Payload` constructor at `src/Support/Payload.php:17-35` (preserves named-arg backward compatibility).
- [ ] [S] Update `PendingNotification::buildPayload()` (both event-base and no-event branches at lines 454 and 484) to plumb `$this->driver` into the new `Payload->driver` field.
- [ ] [S] Modify `Howl::dispatch()` at `src/Howl.php:78`: change `$primary = $this->config['driver'] ?? 'discord';` to `$primary = $payload->driver ?? $this->config['driver'] ?? 'discord';`. Existing driver-level fallback chain at lines 89-117 stays unchanged.
- [ ] [S] Create `tests/Unit/DriverOverrideTest.php` — assert: `Howl::driver('null')->error($event)` resolves to `null` driver; `Howl::error($event)` resolves to configured driver when no override; per-call override does NOT mutate the singleton's `$this->driver` (check `app('howl')->getDriver()` identity before/after); `Howl::driver('slack')->error($event)` throws `InvalidArgumentException` from `resolveDriver()` (proves driver name flows through correctly).

**Verify:** `vendor/bin/pest --filter="DriverOverride"` AND `vendor/bin/pest --parallel`.

### Phase 3: Default + backup channel with failover / fan-out

**Touches:** `config/howl.php`, `src/Howl.php`, `src/Support/PendingNotification.php`, `tests/Feature/ChannelFallbackTest.php` (new)

- [ ] [H] Add three keys to `config/howl.php` (each with explanatory comment + `fan_out` rate-limit caveat):
  ```php
  'channel'        => env('HOWL_DEFAULT_CHANNEL', 'errors'),
  'channel_backup' => env('HOWL_BACKUP_CHANNEL', null),
  'channel_mode'   => env('HOWL_CHANNEL_MODE', 'failover'),  // 'failover' | 'fan_out'
  ```
- [ ] [S] Update channel resolution in `PendingNotification::buildPayload()` so that when neither builder-set channel nor event channel is present, `config('howl.channel')` is the final fallback. Cements precedence: per-call > event > config.
- [ ] [S] Refactor `Howl::dispatch()`:
  - Extract private `dispatchToDriverOnChannel(Payload $payload, string $channelOverride): bool` helper. Inside: clone the payload with the channel field swapped (readonly-safe — build a fresh `Payload` via `new Payload(..., channel: $channelOverride, ...)`), then walk the existing driver-level try-chain at current lines 89-117.
  - At the top of `dispatch()` (after the skip-environments check), resolve `[primary, backup, mode]` from payload + config.
  - `mode === 'failover'`: try primary via helper; if false, try backup (if non-null) via helper; return true on first success, false if both fail.
  - `mode === 'fan_out'`: call helper for primary, then backup (if non-null); return `true` iff at least one succeeded; log per-channel failures via existing `Log::error` pattern.
- [ ] [S] Create `tests/Feature/ChannelFallbackTest.php` with six `Http::fake()` scenarios:
  - failover, primary 404 + backup 204 → 2 sends, returns true, backup received
  - failover, primary 204 → 1 send, returns true, backup never hit
  - failover, primary 404 + backup 500 → 2 sends, returns false, driver fallback chain walked once on primary channel
  - fan_out, both 204 → 2 sends to different thread IDs, returns true
  - fan_out, primary 204 + backup 500 → 2 sends, returns true, error logged
  - backup null + failover → 1 send, no backup attempt
  - explicit `Howl::on('audits')->error($event)` overrides config primary AND uses config backup
- [ ] [S] Add a 7th test asserting channel precedence ordering: per-call > event > config.

**Verify:** `vendor/bin/pest --filter="ChannelFallback"` AND `vendor/bin/pest --parallel`.

### Phase 4: Hard-cut deletion of `onDiscord` / `onSlack` / `onTelegram`

**Touches:** `src/Howl.php`, `src/Facades/Howl.php`, `src/Support/PendingNotification.php`, `config/howl.php`, all 5 test files listed in Context

- [ ] [H] Delete `Howl::onDiscord()`, `Howl::onSlack()`, `Howl::onTelegram()` methods from `src/Howl.php` (lines 30-59).
- [ ] [H] Delete the three `@method ... onDiscord/onSlack/onTelegram` lines from `src/Facades/Howl.php:8-10`.
- [ ] [H] Fix stale comment at `src/Support/PendingNotification.php:430`: change `// Allow Howl::onDiscord()->send(new SomeEvent()) shorthand` to `// Allow (new PendingNotification)->send(new SomeEvent()) shorthand`.
- [ ] [H] Fix stale comment at `config/howl.php:9`: change `Howl::onDiscord()->error(...)` reference to `Howl::error(...)`.
- [ ] [S] Migrate `tests/Feature/HowlFakeTest.php` (13 callsites): mechanical replacement `Howl::onDiscord(` → `Howl::on(`. Any test asserting `onSlack()`/`onTelegram()` throw `BadMethodCallException` → DELETE the test (assertion no longer applies; methods don't exist).
- [ ] [S] Migrate `tests/Feature/FacadeTest.php` (9 callsites): same mechanical replacement; delete `onSlack/onTelegram` throw tests.
- [ ] [H] Migrate `tests/Feature/SeverityMismatchTest.php` (4 callsites): same mechanical replacement.
- [ ] [H] Migrate `tests/Feature/EventDispatchTest.php` (2 callsites): same mechanical replacement.
- [ ] [H] Migrate `tests/Feature/SkipEnvironmentsTest.php` (1 callsite): same mechanical replacement.
- [ ] [H] Verify zero `onDiscord/onSlack/onTelegram` references remain: `grep -rn "onDiscord\|onSlack\|onTelegram" src/ tests/` should return empty.

**Verify:** `vendor/bin/pest --parallel` — full suite green; baseline ~322 tests, expect to be net positive after Phases 1/2/3 additions minus the small set of deleted onSlack/onTelegram throw tests.

### Phase 5: Opt-in queue rate-limit middleware on `SendHowlJob`

**Touches:** `src/Jobs/SendHowlJob.php`, `config/howl.php`, `tests/Feature/SendHowlJobRateLimitTest.php` (new)

- [ ] [H] Add `'rate_limiter_key' => env('HOWL_RATE_LIMITER_KEY', null)` to `config/howl.php` with explanatory comment + example consumer-side `RateLimiter::for('howl-discord', fn () => Limit::perMinute(28))` registration recipe.
- [ ] [S] Add `public function middleware(): array` to `src/Jobs/SendHowlJob.php`:
  ```php
  public function middleware(): array
  {
      $key = config('howl.rate_limiter_key');
      return $key !== null
          ? [new \Illuminate\Queue\Middleware\RateLimitedWithRedis($key)]
          : [];
  }
  ```
- [ ] [H] Verify `$tries = 3` and `backoff() = [1, 4, 16]` stay unchanged.
- [ ] [S] Create `tests/Feature/SendHowlJobRateLimitTest.php`:
  - Default config (`rate_limiter_key` null) → `(new SendHowlJob(...))->middleware()` returns `[]`.
  - `config(['howl.rate_limiter_key' => 'howl-test'])` → `middleware()` returns array with one `RateLimitedWithRedis` instance whose key equals `'howl-test'`.
  - Driver-side failure inside the job still triggers the existing `$tries` exponential backoff (existing behavior unchanged).

**Verify:** `vendor/bin/pest --filter="SendHowlJobRateLimit"` AND `vendor/bin/pest --parallel`.

### Phase 6: src/CLAUDE.md policy file + supersede old P-0004 + full regression + handoff to P-0006

**Touches:** `src/CLAUDE.md` (new), `.planning/P-0004-feat-driver-agnostic-api-todo.md` (rename + supersede note)

- [ ] [H] **Create `src/CLAUDE.md`** with the API-change + docs-versioning policy. Required content (Laravel-docs / Spatie-docs style):
  - **The rule**: any change under `src/` MUST be accompanied by a matching update to the public API documentation under `docs/`. Triggers a docs update: new public method / class / behavior, removed or renamed public method, changed parameter signatures or return types, new config keys, new env vars. Does NOT trigger: private helper renames, internal-only refactors, typo fixes in comments. When in doubt, update the docs.
  - **Versioning policy**: docs are separated per release. Each tagged release (`v1.0.0`, `v1.1.0`, `v2.0.0`, ...) has its own snapshot under `/docs/v{N.M}/` (full sidebar, full content). The `/docs/` root mirrors the `latest` released version. Pre-release work happens on `/docs/next/` and is promoted to `/docs/v{N.M}/` when the release tag is cut. **Pattern matches Laravel's docs** (`laravel.com/docs/13.x`, `12.x`, `11.x` — each version full content; version dropdown in nav).
  - **Copy-then-diff workflow for new version docs** (the recipe for v1.1.0, v2.0.0, and every future version):
    1. Copy the previous version's docs as the baseline: `cp -R docs/v{N-1}/ docs/v{N}/` (or `cp -R docs/next/ docs/v{N}/` if `next/` is up-to-date).
    2. Update ONLY the pages that changed for the new version — leave unchanged pages identical to the previous version's snapshot.
    3. Author / update the **Upgrade Guide** page at `docs/v{N}/upgrade.md` — surfaces breaking changes, migration steps, new env vars, removed APIs. This is the FIRST sidebar entry for every version (Laravel docs pattern — `/docs/13.x/upgrade` is the prominent upgrade page).
    4. Author / update the **Release Notes** page at `docs/v{N}/releases.md` — full additive feature list (Laravel docs pattern — `/docs/13.x/releases`).
    5. Update VitePress config to add `/v{N}/` to the version dropdown.
    6. Reset `/docs/next/` to mirror the just-released version as the next cycle's starting point.
    7. Regenerate `/llms.txt` + `/llms-full.txt` to reference the new latest URLs (frozen `/v{N}/` snapshot for stability).
  - **Breaking-change surfacing rule**: when a release contains breaking changes (any removed public method, changed signature, env-var rename, config-key rename), the Upgrade Guide page MUST be linked from the docs site landing page hero AND from the README. Non-breaking releases get a less-prominent Release Notes link.
  - **How Claude should apply it when editing `src/`**: before submitting any `src/` change: identify the affected docs page(s) under `/docs/next/`; edit them alongside the code change in the SAME PR; if no doc exists yet, create one under the appropriate section; if unsure where the doc lives, ask the user. If the change is breaking, also add an entry to `/docs/next/upgrade.md` describing the migration path. Internal-only refactors with no public API impact get a brief PR description note: "src/ change does not affect public API — no docs update required."
- [ ] [H] Full regression: `vendor/bin/pest --parallel` — assert zero failures, total test count baseline + ~25-30.
- [ ] [H] Run `kaisser plan advance P-0004 --to cancelled` to move the old plan file to `-cancelled.md` (since this plan supersedes it). If `kaisser plan advance` doesn't support `cancelled` target, fall back to manual `mv` and add a top-of-file note linking to P-0005.
- [ ] [H] Add a top-of-file note to the now-cancelled P-0004 stating: "Superseded by P-0005 — interview decisions reshaped scope (hard-cut deprecation, drivers split to P-0006, docs split to P-0008, v1.0.0 destination)."
- [ ] [H] Add a "Handoff to P-0006" section at the END of this plan stating: "API surface now driver-agnostic; `Howl::driver('slack')` / `Howl::driver('telegram')` flow through correctly but throw `InvalidArgumentException` until P-0006 registers the drivers in `resolveDriver()`. Note: `src/CLAUDE.md` now governs API-change → docs-update discipline AND versioned docs policy for all subsequent plans; P-0008 must implement the VitePress versioning infrastructure to honor this rule."
- [ ] [H] DO NOT tag a release. DO NOT bump composer.json version. DO NOT update CHANGELOG. All release artifacts land in P-0008.

**Verify:** `git log --oneline main..HEAD` shows clean commit history per phase; `ls .planning/P-0004*` shows the cancelled rename; `cat src/CLAUDE.md` shows the full policy.

## Execution Strategy

> **Approach:** `/plan-approved` with mixed parallel + sequential rounds
> **Total Tasks:** 33 (H: 18, S: 15, O: 0)
> **Estimated Rounds:** 5 (1 parallel + 4 sequential)
> **Parallel Savings:** 1 round saved (Phase 5 runs concurrently with Phase 1 in Round 1)

### File-Touch Matrix

| Phase | Files/Dirs Touched | Depends On |
|-------|-------------------|------------|
| Phase 1 | `src/Howl.php` (new methods), `src/Facades/Howl.php` (add @method), `tests/Unit/HowlFacadeTest.php` | — |
| Phase 2 | `src/Support/PendingNotification.php`, `src/Support/Payload.php`, `src/Howl.php` (line 78), `tests/Unit/DriverOverrideTest.php` | Phase 1 (`Howl::driver` scaffold) |
| Phase 3 | `config/howl.php`, `src/Howl.php` (refactor), `src/Support/PendingNotification.php`, `tests/Feature/ChannelFallbackTest.php` | Phase 2 (`Payload::driver` field) |
| Phase 4 | `src/Howl.php` (delete), `src/Facades/Howl.php` (delete), `src/Support/PendingNotification.php` (comment), `config/howl.php` (comment), 5 test files | Phase 1 (new API exists) + Phase 3 (config/howl.php conflicts) |
| Phase 5 | `src/Jobs/SendHowlJob.php`, `config/howl.php` (add `rate_limiter_key`), `tests/Feature/SendHowlJobRateLimitTest.php` | — (only `config/howl.php` overlaps P3/P4, but adds a different key — parallel with P1 is safe) |
| Phase 6 | `src/CLAUDE.md` (new), `.planning/P-0004*` (rename) | All prior phases |

**Parallelism opportunity:** Phase 1 and Phase 5 have ZERO file overlap — Phase 1 doesn't touch `config/howl.php` and Phase 5 doesn't touch `src/Howl.php`. They run concurrently as Round 1. All other phases serialize through `src/Howl.php` and `config/howl.php` chains.

### Round 1: Phase 1 + Phase 5 → Parallel Teams (Mode B — 2 team-leads dispatched together)

Independent phases, both with `[S]` tasks — zero file overlap. Phase 1 adds new API surface (purely additive); Phase 5 adds opt-in rate-limit middleware on a separate job file.

| Phase | Mode | Model | Tasks | Notes |
|-------|------|-------|-------|-------|
| Phase 1: Severity API on Howl class | Team-lead | Sonnet | 1.1-1.6 (4×[H] + 2×[S]) | Team-lead spawns workers; severity verb dispatch flow + facade-aware tests |
| Phase 5: Rate-limit middleware | Team-lead | Sonnet | 5.1-5.4 (2×[H] + 2×[S]) | Team-lead spawns workers; Laravel queue middleware integration + opt-in config |

### Round 2: Phase 2 → Single Team (Mode C — depends on Round 1)

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 2.1 PendingNotification::driver() clone-and-set | [H] | bifrost-1 | Builder method mirroring channel() pattern |
| 2.2 PendingNotification $driver field | [H] | bifrost-1 | Sibling protected field |
| 2.3 Payload::driver constructor param | [H] | bifrost-1 | Append `?string $driver = null` |
| 2.4 buildPayload() plumb driver | [S] | bifrost-2 | Both event-base and no-event branches |
| 2.5 Howl::dispatch() line 78 change | [S] | bifrost-2 | Prefer `$payload->driver` over config |
| 2.6 DriverOverrideTest | [S] | bifrost-3 | Singleton non-mutation + flow assertions |

### Round 3: Phase 3 → Single Team (Mode C — depends on Round 2)

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 3.1 Add 3 config keys to config/howl.php | [H] | asgard-1 | `channel`, `channel_backup`, `channel_mode` |
| 3.2 Channel precedence in buildPayload | [S] | asgard-1 | Per-call > event > config |
| 3.3 dispatch() refactor + failover/fan_out helpers | [S] | asgard-2 | Extract dispatchToDriverOnChannel + new resolution logic |
| 3.4 ChannelFallbackTest 6 scenarios | [S] | asgard-3 | Http::fake() scenarios |
| 3.5 Precedence ordering test | [S] | asgard-3 | 7th test |

### Round 4: Phase 4 → Single Team (Mode C — depends on Rounds 1 + 3)

Hard-cut deletion. Mostly mechanical [H] but the test-file migrations (HowlFakeTest, FacadeTest) include reasoning about which `onSlack/onTelegram throw BadMethodCallException` tests to delete — those are [S].

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 4.1 Delete onDiscord/onSlack/onTelegram methods | [H] | mjolnir-1 | src/Howl.php:30-59 |
| 4.2 Delete @method docblocks | [H] | mjolnir-1 | src/Facades/Howl.php:8-10 |
| 4.3 Fix stale comment PendingNotification:430 | [H] | mjolnir-1 | One-line edit |
| 4.4 Fix stale comment config/howl.php:9 | [H] | mjolnir-1 | One-line edit |
| 4.5 Migrate HowlFakeTest (13 callsites) | [S] | mjolnir-2 | Sed + delete throw assertions |
| 4.6 Migrate FacadeTest (9 callsites) | [S] | mjolnir-2 | Sed + delete throw assertions |
| 4.7 Migrate SeverityMismatchTest (4 callsites) | [H] | mjolnir-3 | Pure sed |
| 4.8 Migrate EventDispatchTest (2 callsites) | [H] | mjolnir-3 | Pure sed |
| 4.9 Migrate SkipEnvironmentsTest (1 callsite) | [H] | mjolnir-3 | Pure sed |
| 4.10 Verify grep returns empty | [H] | mjolnir-3 | Final smoke check |

### Round 5: Phase 6 → Single Subagent (Mode F — all [H], final wrap-up)

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 6.1 Create src/CLAUDE.md | [H] | worker-1 | Markdown content fully spec'd in plan |
| 6.2 Full regression pest --parallel | [H] | worker-1 | Smoke check |
| 6.3 kaisser plan advance P-0004 --to cancelled | [H] | worker-1 | Plan housekeeping |
| 6.4 Top-of-file note on cancelled P-0004 | [H] | worker-1 | Supersession marker |
| 6.5 Handoff to P-0006 section | [H] | worker-1 | Append to this plan |
| 6.6 Verify no release artifacts touched | [H] | worker-1 | Sanity check |

## Tech Notes

- **PHP `^8.3`, Laravel `^12.0 || ^13.0`** — composer constraints unchanged.
- **`RateLimitedWithRedis` requires Redis at runtime in CI** — but the middleware tests assert array contents only (not actual rate-limit behavior), so no Redis dependency for the test suite. Consumers register their own limiter via `RateLimiter::for()` in their `AppServiceProvider::boot()`.
- **`HowlFake extends Howl`** — Phase 1's new severity methods auto-inherit. No fake-side changes needed in this plan. Per-driver fake assertions (`assertSentVia`) deferred to P-0007 coverage plan.
- **Channel precedence chain runs in `PendingNotification::buildPayload()`** — final null-coalesce against `config('howl.channel')` is added in Phase 3. Tests assert all three layers compose correctly.

## References

- [P-0001](./P-0001-feat-howl-package-v0-1-0-approved.md) — original facade shape with `onDiscord/onSlack/onTelegram` (now being removed)
- [P-0002](./P-0002-feat-howl-events-layer-v0-2-0-complete.md) — `HowlEvent` 8-method contract (channel precedence depends on this)
- [P-0003](./P-0003-fix-generic-info-event-channel-complete.md) — `GenericInfoEvent::channel()` severity mapping (channel precedence rules formalized here)
- [P-0004 (cancelled)](./P-0004-feat-driver-agnostic-api-todo.md) — superseded by this plan; interview locked hard-cut deprecation and 4-plan path-to-v1.0.0
- Future: P-0006 (Slack + Telegram drivers), P-0007 (100% coverage + Pest 3/4 CI matrix), P-0008 (VitePress docs + LLM docs + release → tag v1.0.0)

## Acceptance

- [x] `src/CLAUDE.md` exists and documents both the "code change requires docs update" rule AND the versioned-docs policy (`/docs/v{N.M}/`, `/docs/next/`, promotion-on-tag flow). This file becomes the governing policy for P-0006/P-0007/P-0008 and all post-v1.0 work.
- [x] Six severity methods (`Howl::error/warning/info/audit/deployment/success`) + `Howl::on(?string)` + `Howl::driver(string)` present on `Howl` class and documented in `src/Facades/Howl.php` `@method` PHPDoc.
- [x] Channel precedence enforced and asserted: per-call `Howl::on($c)` > `HowlEvent::channel()` > `config('howl.channel')`.
- [x] Backup channel + `channel_mode` (`failover` + `fan_out`) implemented; integration tests cover all six scenarios in Phase 3.
- [x] Per-call `Howl::driver($name)->...` overrides the configured driver on dispatch; singleton state unchanged.
- [x] `onDiscord/onSlack/onTelegram` methods GONE from `src/Howl.php` (no aliases, no `@deprecated`, no `trigger_error`). `grep -rn "onDiscord\|onSlack\|onTelegram" src/ tests/` returns empty.
- [x] All 38 legacy callsites in test files migrated to the new API; tests that asserted `onSlack/onTelegram throw BadMethodCallException` are deleted (assertion no longer applies).
- [x] `SendHowlJob::middleware()` returns `[RateLimitedWithRedis(key)]` when `config('howl.rate_limiter_key')` is non-null, else `[]`. Default null preserves today's no-throttle dispatch.
- [x] Stale comments fixed: `src/Support/PendingNotification.php:430` and `config/howl.php:9` no longer reference `Howl::onDiscord(...)`.
- [x] Full regression green: `vendor/bin/pest --parallel` exits 0; total test count 346 / 751 assertions (baseline 322 / 692 + 24 net new tests).
- [x] Old P-0004 file moved to `-cancelled.md` suffix with a top-of-file supersession note linking to this plan.
- [x] Handoff note at end of this plan flags that `Howl::driver('slack')` / `Howl::driver('telegram')` paths are wired but throw `InvalidArgumentException` until P-0006 registers the drivers.
- [x] NO release tag cut. NO CHANGELOG entry. NO composer.json version bump. All release artifacts deferred to P-0008.

## Handoff to P-0006

API surface is now driver-agnostic. `Howl::driver('slack')` and `Howl::driver('telegram')` flow through the new dispatch path correctly but throw `InvalidArgumentException("Howl: unknown driver 'slack'.")` until P-0006 registers the drivers in `resolveDriver()`.

`src/CLAUDE.md` now governs API-change → docs-update discipline AND versioned docs policy for all subsequent plans. P-0008 must implement the VitePress versioning infrastructure to honor this rule.
