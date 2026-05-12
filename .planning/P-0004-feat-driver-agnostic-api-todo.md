---
id: "P-0004"
title: "feat: driver-agnostic facade API + channel failover/fan-out (v0.3.0)"
type: feat
project: howl
branch: feat/driver-agnostic-api
base: main
tags: [api-redesign, facade, drivers, channel-fallback, deprecation, v0-3-0]
backlog: null
dependsOn: [P-0003]
created: 2026-05-12T00:30
session_id: null
---

# feat: driver-agnostic facade API + channel failover/fan-out (v0.3.0)

## Goal

Replace the misleading per-driver facade methods (`Howl::onDiscord()`, `Howl::onSlack()`, `Howl::onTelegram()`) with a driver-agnostic, severity-terminal API (`Howl::error($event)`, `Howl::info($event)`, plus `Howl::on($channel)` and `Howl::driver($name)` pre-chain builders) that scales O(1) to any transport, add a default + optional backup channel with config-selected failover-or-fan-out semantics, and ship an opt-in queue rate-limit middleware on `SendHowlJob` so consumers running queued Howl can respect per-transport rate limits (Discord 30/min, Slack ~60/min, Telegram 30/sec). All bundled in **v0.3.0** with full backwards-compatibility — the three legacy `onX()` methods become deprecated aliases emitting `E_USER_DEPRECATED` until they're removed at v1.0.

## Non-Goals

- **Do NOT add per-severity driver routing in this PR.** Single `'driver'` config key stays. Multi-driver consumers either swap config or use per-call `Howl::driver(...)` overrides. Per-severity routing is a deferred follow-up.
- **Do NOT remove `onDiscord/onSlack/onTelegram` in this release.** They become deprecated aliases; removal lands at v1.0.0.
- **Do NOT touch the existing `'fallback'` driver-level config key.** Driver-to-driver failover (Discord → Slack on driver-side total failure) stays untouched and is orthogonal to the new channel-level failover/fan-out. Channel logic runs first; driver fallback chain runs second.
- **Do NOT bump to v1.0.0 in this PR.** v1.0.0 is reserved for "deprecated methods removed." Bumping now would force same-release migration and leave the deprecation parked at v1.x with no removal version before v2.0.0.
- **Do NOT change `HowlEvent::channel()` semantics.** Event-defined channel wins over `config('howl.channel')`. The new `Howl::on($channel)` override wins over BOTH — explicit per-call beats event-defaulted beats config-defaulted.
- **Do NOT alter existing `PendingNotification` builder methods** (`->channel(string)`, `->forceSync()`, `->meta()`, etc.). Adding `->driver(string)` is the only builder-side change.
- **Do NOT add a Slack or Telegram driver in this PR.** `Howl::driver('slack')` and `Howl::driver('telegram')` continue throwing `BadMethodCallException` ("driver not registered") until v2 driver work ships. This plan only stops baking driver names into method signatures.

## Context

- `src/Howl.php:30-59` — `onDiscord()` returns a `PendingNotification` (working); `onSlack()` / `onTelegram()` throw `BadMethodCallException` (reserved-for-v2 stubs from v0.1). The dispatcher resolves the driver from `config('howl.driver')` regardless of which `onX()` is called; method name has been decorative-misleading since v0.1.
- `src/Facades/Howl.php:8-10` — facade `@method` PHPDoc currently advertises all three per-driver methods; needs updating to add new methods + mark legacy ones `@deprecated`.
- `src/Support/PendingNotification.php:121-127` — `->channel(string)` builder stays unchanged. New sibling `->driver(string)` will be added.
- `src/Support/Payload.php` — needs new optional `?string $driver` field to carry the per-call driver override into `Howl::dispatch()`.
- `src/Howl.php:69-119` — `dispatch(Payload $payload)` is the central path. Needs refactor to: (1) prefer `$payload->driver` over `$this->config['driver']`; (2) implement channel-level failover / fan-out before falling through to the existing driver-level fallback chain on line 91.
- `config/howl.php` — add three new keys: `'channel'` (default channel, defaults to `'errors'` to keep current behavior), `'channel_backup'` (optional, default `null`), `'channel_mode'` (`'failover'` | `'fan_out'`, default `'failover'`).
- Existing `'fallback'` config key (line 22 of `config/howl.php`) is driver-level; do not conflate with new channel-level keys. Channel logic runs first; on full channel-set failure, walk the driver fallback chain on the primary channel only (avoid combinatorial blow-up).
- **Channel precedence (locked):** explicit per-call `Howl::on($c)` > `HowlEvent::channel()` > `config('howl.channel')`. Document in README; assert in tests.
- **Failover semantics:** try primary; on driver-reported delivery failure (4xx/5xx/timeout from `DiscordDriver::send()` returning false or throwing), retry once on backup channel; if both fail, walk driver fallback chain on the primary channel only.
- **Fan-out semantics:** dispatch sequentially to both channels in the same request; result `true` iff at least one channel succeeded. Per-channel failures logged via existing `Log::error` path. Doubles rate-limit consumption — flag this in README + CHANGELOG since downstream paylog will cap Discord at 28 msg/min (paylog P-0222 Phase 2.6).
- **Deprecation mechanism:** `trigger_error('Howl::onX() is deprecated since v0.3.0 — use ... instead. Removed at v1.0.', E_USER_DEPRECATED)`, guarded by a static-per-method flag so the notice fires exactly once per process to avoid log spam.
- **Downstream:** paylog has 259+ callsites currently using `Howl::onDiscord(...)`. Migration is a one-shot sed (`Howl::onDiscord(` → `Howl::on(`) plus manual review of any `onSlack/onTelegram` calls. Paylog will run the migration in a separate paylog-side commit after this PR lands.
- **Test count baseline (post-P-0003):** 322 tests / 692 assertions per `vendor/bin/pest --parallel`. Expect baseline + ~35 new tests across Phases 1-5.
- **Rate-limit middleware (Phase 5):** `Illuminate\Queue\Middleware\RateLimitedWithRedis` ships with Laravel, has been stable since L8. Composes cleanly with `$tries`/`backoff()` — rate-limit releases do NOT count against `$tries`. Opt-in via new `config('howl.rate_limiter_key')`; null preserves today's no-throttle behavior. Downstream paylog will register the named limiter in `AppServiceProvider::boot()` and pair it with a `maxProcesses: 1` Horizon supervisor on a dedicated `howl-alerts` queue (paylog P-0222 Phase 2.6).

## Phases

### Phase 1: Core severity-terminal API on `Howl` class + facade

**Touches:** `src/Howl.php`, `src/Facades/Howl.php`, `tests/Unit/HowlFacadeTest.php` (new or extended)

- [ ] Add direct severity entry methods on `Howl`: `error()`, `warning()`, `info()`, `audit()`, `deployment()`, `success()`. Each accepts `HowlEvent|string $titleOrEvent` and returns `bool`.
- [ ] Extract private `dispatchSeverity(string $severity, HowlEvent|string $titleOrEvent): bool` that the six methods delegate to — builds a fresh `PendingNotification`, accepts the event-or-title, dispatches via existing path.
- [ ] Add `Howl::on(?string $channel = null): PendingNotification` returning a new `PendingNotification` with optional channel set.
- [ ] Add `Howl::driver(string $name): PendingNotification` returning a new `PendingNotification` with the driver name attached as per-call override.
- [ ] Update facade `@method` PHPDoc in `src/Facades/Howl.php` — add the 6 severity methods + `on()` + `driver()`; keep `onDiscord/onSlack/onTelegram` with `@deprecated` annotation pointing at replacements.
- [ ] Tests: `Howl::error($event)` dispatches and returns bool; `Howl::error('Title')` dispatches with a built payload; `Howl::on('errors')->error($event)` chain sets channel pre-severity; `Howl::driver('discord')->error($event)` chain sets driver pre-severity; chain order independence (`driver()->on()` ≡ `on()->driver()`).

**Verify:** `vendor/bin/pest --filter="HowlFacade"`

### Phase 2: Per-call driver override plumbing

**Touches:** `src/Support/PendingNotification.php`, `src/Support/Payload.php`, `src/Howl.php`, `tests/Unit/DriverOverrideTest.php` (new)

- [ ] Add `PendingNotification::driver(string $name): static` — stores driver override on the pending instance (clone-and-set pattern matching `channel()`).
- [ ] Add `?string $driver = null` field to `Payload` constructor and immutable accessors.
- [ ] Plumb driver override into `PendingNotification::buildPayload()` so per-call driver lands on the `Payload`.
- [ ] Update `Howl::dispatch(Payload $payload)` line 78 to prefer `$payload->driver ?? $this->config['driver']` when resolving the primary driver. Existing driver-fallback chain on line 91 stays unchanged.
- [ ] Tests: `Howl::driver('slack')->error($event)` resolves to `slack` regardless of `config('howl.driver')`; `Howl::error($event)` resolves to the configured driver when no override set; per-call override does NOT mutate the singleton's `$this->driver`.

**Verify:** `vendor/bin/pest --filter="DriverOverride"`

### Phase 3: Default + backup channel with failover / fan-out

**Touches:** `config/howl.php`, `src/Howl.php`, `src/Support/PendingNotification.php`, `tests/Feature/ChannelFallbackTest.php` (new)

- [ ] Add three keys to `config/howl.php` (each with explanatory comment + rate-limit caveat for `fan_out`):
  ```php
  'channel'         => env('HOWL_DEFAULT_CHANNEL', 'errors'),
  'channel_backup'  => env('HOWL_BACKUP_CHANNEL', null),
  'channel_mode'    => env('HOWL_CHANNEL_MODE', 'failover'),  // 'failover' | 'fan_out'
  ```
- [ ] Update channel resolution in `PendingNotification::buildPayload()` so that when no explicit channel was set AND the event's `channel()` returned null, `config('howl.channel')` is applied. Cements precedence: per-call > event > config.
- [ ] Refactor `Howl::dispatch()`:
  - Extract a `dispatchToDriverOnChannel(Payload $payload, string $channelOverride): bool` helper that produces a per-channel `Payload` clone and walks the existing driver-level try-chain.
  - At the top of `dispatch()`, resolve `[primary, backup, mode]` from payload + config.
  - `mode === 'failover'`: try primary; if helper returns false, try backup (if non-null); if both false, return false. Return true on first success.
  - `mode === 'fan_out'`: call helper for primary, then backup (if non-null); return true if either succeeded; log per-channel failures.
- [ ] Tests via `Http::fake()`:
  - failover, primary 404 + backup 204 → 2 sends, returns true, backup received
  - failover, primary 204 → 1 send, returns true, backup never hit
  - failover, primary 404 + backup 500 → 2 sends, returns false, driver fallback chain walked once on primary channel
  - fan_out, both 204 → 2 sends to different thread IDs, returns true
  - fan_out, primary 204 + backup 500 → 2 sends, returns true, error logged
  - backup `null` + failover → 1 send, no backup attempt
  - explicit `Howl::on('audits')->error($event)` overrides the config primary AND uses the same backup config (if configured)

**Verify:** `vendor/bin/pest --filter="ChannelFallback"`

### Phase 4: Deprecation aliases for `onDiscord` / `onSlack` / `onTelegram`

**Touches:** `src/Howl.php`, `src/Facades/Howl.php`, `tests/Unit/DeprecationTest.php` (new)

- [ ] `onDiscord(?string $channel = null)`: thin alias for `on($channel)`. Emits `trigger_error('Howl::onDiscord() is deprecated since v0.3.0 — use Howl::on() instead. Removed at v1.0.', E_USER_DEPRECATED)` exactly once per process (static guard).
- [ ] `onSlack(?string $channel = null)`: thin alias for `driver('slack')->on($channel)`. Same deprecation pattern. Still throws `BadMethodCallException` when dispatched if Slack driver isn't registered (preserves today's behavior).
- [ ] `onTelegram(?string $channel = null)`: thin alias for `driver('telegram')->on($channel)`. Same.
- [ ] Update facade `@method` PHPDoc: mark all three `@deprecated since v0.3.0`, point at replacements, note "removed at v1.0".
- [ ] Tests: `Howl::onDiscord('errors')->error($event)` works AND triggers `E_USER_DEPRECATED`; `Howl::onSlack('alerts')` triggers deprecation AND throws driver-not-registered on dispatch (matches today); static guard verified — calling deprecated method 10x triggers notice exactly 1x per process; `HowlFake` recording captures dispatches via deprecated alias under the same channel as the new equivalent.

**Verify:** `vendor/bin/pest --filter="Deprecation"`

### Phase 5: Opt-in queue rate-limit middleware on `SendHowlJob`

**Touches:** `src/Jobs/SendHowlJob.php`, `config/howl.php`, `tests/Feature/SendHowlJobRateLimitTest.php` (new), `README.md` § "Rate limiting" (new sub-section in the eventual Phase 7 doc pass — only the code lands here)

**Rationale:** Discord webhooks cap at 30 msg/min per webhook; Slack at ~1 msg/sec; Telegram at 30/sec. Consumers running Howl on a queue (`config('howl.queue') === true`) need to throttle `SendHowlJob` against their transport's limit. Today `SendHowlJob` has no `middleware()` method, so even a `maxProcesses: 1` Horizon worker can burst above the cap when responses are fast. Add an opt-in `RateLimitedWithRedis` middleware keyed by a new config knob. Consumers register the named limiter in their service provider; null = no rate limiting (today's behavior).

- [ ] Add `'rate_limiter_key' => env('HOWL_RATE_LIMITER_KEY', null)` to `config/howl.php` with comment explaining the opt-in contract and an example consumer-side `RateLimiter::for('howl-discord', fn () => Limit::perMinute(28))` registration.
- [ ] Add `public function middleware(): array` to `src/Jobs/SendHowlJob.php`:
  ```php
  public function middleware(): array
  {
      $key = config('howl.rate_limiter_key');
      return $key !== null
          ? [new \Illuminate\Queue\Middleware\RateLimitedWithRedis($key)]
          : [];
  }
  ```
- [ ] Verify `$tries = 3` and `backoff() = [1, 4, 16]` stay unchanged — `RateLimitedWithRedis` releases the job back to the queue with its own per-limiter `retryAfter`, which composes correctly with the existing retry chain (rate-limit releases don't count toward `$tries`).
- [ ] Tests:
  - Default config (`rate_limiter_key` null) → `middleware()` returns `[]`; `SendHowlJob` dispatches inline without throttling.
  - Custom key set + `RateLimiter::for('howl-discord', fn () => Limit::perMinute(2))` registered → dispatching 5 jobs in rapid succession releases the 3rd/4th/5th back to the queue with `Retry-After`.
  - Rate-limit release does NOT consume retries: a job that's released by the limiter and later succeeds still shows `attempt = 1`.
  - Driver-side failure inside the rate-limited job still triggers the existing `$tries` exponential backoff.
- [ ] No README change in this phase — README docs roll up in Phase 7 alongside the new "Rate limiting" section that ties config + consumer-side `RateLimiter::for()` + Horizon supervisor recommendation together.

**Verify:** `vendor/bin/pest --filter="SendHowlJobRateLimit"`

### Phase 6: Migration codemod + consumer guide

**Touches:** `docs/migration/0.2-to-0.3.md` (new), `README.md`

- [ ] Migration doc with portable BSD-safe sed codemod for the 95% case (`Howl::onDiscord(` → `Howl::on(`).
- [ ] Document the 5% manual-review edge cases: bare `Howl::onDiscord()` → flatten to direct `Howl::error($event)` once consumers eyeball; `onSlack(...)` / `onTelegram(...)` → `driver('slack')->on(...)` / `driver('telegram')->on(...)`.
- [ ] Update `README.md` § "Quick start" to lead with the new API; add a "Legacy / migrating from v0.2" subsection.
- [ ] Add the channel-precedence rule + failover-vs-fan_out comparison + rate-limit caveat to README "Channel routing".
- [ ] Add a new README § "Rate limiting" subsection: `rate_limiter_key` opt-in contract, example `RateLimiter::for('howl-discord', fn () => Limit::perMinute(28))` registration in `AppServiceProvider::boot()`, recommended Horizon supervisor pairing (single worker on a dedicated queue + named limiter = 28/min effective cap).
- [ ] Coordination note: paylog will codemod its 259+ callsites + flip its config in a separate paylog-side commit after this PR lands.

**Verify:** Read-through; run the sed codemod on a copy of paylog's `app/`, spot-check 5 random callsites compile and pass `vendor/bin/pint --test`.

### Phase 7: README, CHANGELOG, tag v0.3.0

**Touches:** `README.md`, `CHANGELOG.md`, git tag `v0.3.0`

- [ ] CHANGELOG entry under `## [0.3.0]` with sections: **Added** (severity-terminal API; `on()`/`driver()` builders; `channel_backup` + `channel_mode` config; failover + fan_out semantics; opt-in queue rate-limit middleware via `rate_limiter_key`), **Deprecated** (`onDiscord`/`onSlack`/`onTelegram`), **Migration** (link to `docs/migration/0.2-to-0.3.md`).
- [ ] README "Quick start" leads with new API.
- [ ] README "Configuration" documents `channel`, `channel_backup`, `channel_mode`, `rate_limiter_key`.
- [ ] README "Rate limiting" section ties the three pieces together: package config (`rate_limiter_key`) + consumer-side `RateLimiter::for('howl-discord', fn () => Limit::perMinute(28))` + recommended Horizon supervisor pairing (1 worker on dedicated queue).
- [ ] Full regression: `vendor/bin/pest --parallel` — baseline 322, expect ~357. Zero regressions in existing tests.
- [ ] **PAUSE POINT.** Coordinator stops here and asks user for explicit go-ahead before pushing the tag.
- [ ] On approval: `git tag v0.3.0 && git push --tags`.

**Verify:** `git tag -l v0.3.0` shows the tag; `composer show skaisser/howl` from a downstream worktree resolves to `0.3.0` within ~5 minutes of Packagist polling.

## Tech Notes

- **Deprecation testing:** `trigger_error(..., E_USER_DEPRECATED)` is captured by Pest via `set_error_handler` in a test scope. Add a custom expectation `expect(fn() => Howl::onDiscord())->toTriggerDeprecation()` in `tests/Pest.php` if not present.
- **Version bump policy locked:** 0.3.0 (additive + deprecations) → v0.3.x (consumers migrate at leisure) → 1.0.0 (deprecations removed, clean cut). Do NOT skip directly to 1.0.0 in this PR.
- **`HowlFake` parity:** assertions like `Howl::fake()->assertSent(...)` must work identically whether the callsite used the deprecated alias or the new method. Tested in Phase 4.

## References

- [P-0001](./P-0001-feat-howl-package-v0-1-0-approved.md) — original facade shape with `onDiscord/onSlack/onTelegram`
- [P-0002](./P-0002-feat-howl-events-layer-v0-2-0-complete.md) — `HowlEvent` 8-method contract introduced (`channel()` precedence depends on this)
- [P-0003](./P-0003-fix-generic-info-event-channel-complete.md) — `GenericInfoEvent::channel()` severity mapping (channel-precedence rules formalized here)
- Downstream consumer plan: `~/Sites/paylog222/.planning/P-0222-chore-howl-alert-quality-audit-todo.md` Phase 2.6 — paylog awaits this release for `rate_limiter_key` config + new API migration

## Acceptance

- [ ] New facade methods present: `Howl::error/warning/info/audit/deployment/success(HowlEvent|string)`, `Howl::on(?string)`, `Howl::driver(string)`. All documented in facade `@method` PHPDoc.
- [ ] Channel precedence enforced and asserted: explicit per-call > `HowlEvent::channel()` > `config('howl.channel')`.
- [ ] Backup channel + mode (`failover` + `fan_out`) implemented; `Http::fake()` integration tests cover all six scenarios listed in Phase 3.
- [ ] Per-call `Howl::driver($name)->...` overrides the configured driver on dispatch; singleton state unchanged.
- [ ] `onDiscord/onSlack/onTelegram` work AND trigger `E_USER_DEPRECATED` exactly once per process per method. Facade PHPDoc marks them `@deprecated since v0.3.0, removed at v1.0`.
- [ ] **Rate-limit middleware:** `SendHowlJob::middleware()` returns `[RateLimitedWithRedis(key)]` when `config('howl.rate_limiter_key')` is non-null, else `[]`. Limiter releases do NOT consume `$tries`. Default behavior (null key) preserves today's no-throttle dispatch.
- [ ] `docs/migration/0.2-to-0.3.md` ships with sed codemod + edge-case notes. README links to it.
- [ ] CHANGELOG v0.3.0 entry covers Added / Deprecated / Migration sections (rate-limit middleware listed under Added).
- [ ] Full regression green; total test count baseline + ~35.
- [ ] `v0.3.0` tag pushed only AFTER explicit user approval at the Phase 6 pause point; Packagist resolves `composer require skaisser/howl:^0.3` from a downstream worktree.
- [ ] Paylog P-0222 Phase 2.6 cross-reference updated to point at the released Howl version.
