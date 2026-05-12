---
id: "P-0003"
title: "fix: GenericInfoEvent::channel() returns null — info/warning/success severities skip thread routing"
type: fix
project: howl
branch: fix/generic-info-event-channel
base: main
tags: [bugfix, thread-routing, generic-info-event, v0-2-1]
backlog: null
dependsOn: [P-0002]
created: 2026-05-11T11:09
session_id: null
---

# fix: GenericInfoEvent::channel() returns null — info/warning/success severities skip thread routing

## Goal

`GenericInfoEvent::channel()` currently returns `null` for every severity, which falls through `DiscordDriver::resolveEndpoint()` to the `'general'` default — and since no `threads.general` config entry exists, the driver posts to the webhook channel root with no `?thread_id=` query parameter. Effect: every `Howl::onDiscord()->info(new GenericInfoEvent(...))` lands in the channel root instead of the configured per-severity thread. Fix by deriving `channel()` from `$eventSeverity` (the only one of the seven shipped event classes still returning `null`), ship as a patch release v0.2.1, and refresh README + CHANGELOG to document the corrected routing behavior.

## Non-Goals

- **Do NOT change `DiscordDriver::resolveEndpoint()`** — the driver's 3-step resolution (per-category webhook → main webhook + `?thread_id=N` → explicit `->thread()` override) is correct as designed. The bug is in the event's channel mapping, not the driver.
- **Do NOT add a `'general'` key to the `threads` config array** — that would mask the bug for `GenericInfoEvent` but leave every other consumer who subclasses `HowlEvent` and forgets to override `channel()` silently routing to whatever `general` is configured to. Better to require explicit channel return values OR for the catch-all event to derive from severity.
- **Do NOT change `HowlEvent::channel()` base default** — it's an abstract base; returning `null` is correct since subclasses MUST decide their own routing. The contract stays as-is.
- **Do NOT touch the other six event classes** — `GenericExceptionEvent`, `AuditEvent`, `DeploymentEvent`, `JobRetryExhaustedEvent`, `ManualOperationEvent`, `CronHeartbeatEvent` all return explicit channel strings already (audited via grep across `src/Events/` — only `GenericInfoEvent` is broken).
- **Do NOT introduce a new severity → channel mapping config in `config/howl.php`** — keep the fix self-contained in the event class.
- **Do NOT push the tag until the user reviews the branch + CHANGELOG locally.** Tasks 2.2+2.3 stay local until explicit user approval; coordinator MUST stop after task 2.1 and confirm before pushing.

## Context

- **Discovered during downstream-consumer integration smoke test (2026-05-11).** Consumer ran `composer require skaisser/howl:^0.2.0`, configured `HOWL_DISCORD_DEFAULT` + 5 `HOWL_DISCORD_THREAD_*` env vars, and fired `\Howl::onDiscord()->info(new GenericInfoEvent('Smoke', '...'))`. HTTP returned 204, no log warnings, facade returned `true` — but the embed landed in the webhook's channel root, NOT the configured `#info` thread.
- **Root cause (verified via `Http::fake()` capture):** the captured POST URL was the webhook URL with **no `?thread_id=` query param**. Driver code at `src/Drivers/DiscordDriver.php:43` only appends `?thread_id=` when `$threadId` is non-null. `$threadId` is resolved at line 82–83 via `config('howl.drivers.discord.threads.'.$channel)` where `$channel = $payload->channel ?? 'general'`. Since `GenericInfoEvent::channel()` returns `null`, `$channel` becomes `'general'`, `config(...threads.general)` is unset, and `$threadId` is `null` → URL has no thread routing.
- **Affected file:** `src/Events/GenericInfoEvent.php` (the only event currently returning `null` from `channel()`).
- **Severity set per `EmbedBuilder` color map:** `error`, `warning`, `info`, `success`, `audit`, `deployment` — six values. The thread map in `config/howl.php` exposes five thread keys (`errors`, `warnings`, `info`, `audit`, `deployments`) — no dedicated `success` thread. The fix consolidates: `success` → `info` thread (closest semantic match) and `deployment` (singular severity) → `deployments` (plural config key).
- **No API change:** patch-level release; method signatures unchanged. Behavior change is only the return value of `GenericInfoEvent::channel()` going from `null` to a severity-derived string.
- **`composer.json` version field is not used** — Packagist reads tags. No file change needed for the bump.

## Tech Stack Versions

- **PHP:** `^8.3` (supports 8.3, 8.4 — per `composer.json` + memory note)
- **Laravel (illuminate/*):** `^12.0|^13.0` (8.2 / L11 explicitly out of support)
- **Test runner:** Pest `^3.0` + pest-plugin-laravel `^3.0`
- **Test harness:** orchestra/testbench `^10.0|^11.0`
- **Formatter:** Laravel Pint `^1.0` (invoke with `--format agent` for machine-readable output)
- **HTTP test fakes:** `Illuminate\Support\Facades\Http::fake()` — Laravel 12 syntax, supports URL/method-specific stubs and `assertSent`/`assertNothingSent`.
- **`match` expression:** PHP 8.0+ feature; strict comparison and exhaustive patterns. Use a `default` arm to cover unknown severities and keep `channel()` non-null in all paths.

## Phases

### Phase 1: Fix `GenericInfoEvent::channel()` mapping + regression tests

**Touches:** `src/Events/GenericInfoEvent.php`, `tests/Unit/Events/GenericInfoEventTest.php` (or wherever existing GenericInfoEvent tests live — verify via `grep -rn GenericInfoEvent tests/`), `tests/Feature/DiscordDriverTest.php` (or equivalent driver-level test), `tests/Fixtures/Howl/generic_info_event.json` (if the fixture captures channel as a payload field — verify; only touch if snapshot diff demands it)

- [x] [H] Replace `GenericInfoEvent::channel()` body with a severity-based `match` expression mapping `error→errors`, `warning→warnings`, `info→info`, `success→info`, `audit→audit`, `deployment→deployments`, default `→info` ✅ 2026-05-11T11:21 (bifrost-1, commit 5882517)
- [x] [H] Locate existing GenericInfoEvent test file (`tests/Unit/Events/GenericInfoEventTest.php` confirmed present); add 6 channel-mapping assertions (one per documented severity) ✅ 2026-05-11T11:21 (bifrost-1, commit 5882517 — 25 passed / 41 assertions)
- [x] [S] Add a DiscordDriver integration test that uses `Http::fake()`, fires a `GenericInfoEvent` of each severity, and asserts the captured request URL contains the matching `?thread_id=<expected>` from a stubbed config — guards against future regressions in driver resolution flow ✅ 2026-05-11T11:21 (bifrost-2, commit 6924c6d — `tests/Feature/DiscordDriverTest.php` covers all 6 severities; bypasses facade short-circuit by direct driver instantiation)
- [x] [H] If `tests/Fixtures/Howl/generic_info_event.json` references channel, update fixture; otherwise leave untouched ✅ 2026-05-11T11:21 (bifrost-1, commit 5882517 — fixture lives at `tests/Fixtures/Events/GenericInfoEvent.json`; channel field updated null → "info")
- [x] [H] Run targeted: `vendor/bin/pest --filter=GenericInfoEvent` then `vendor/bin/pest --filter=DiscordDriver` — both green ✅ 2026-05-11T11:22 (bifrost-verify — 31 passed / 59 assertions)
- [x] [H] Full regression: `vendor/bin/pest --parallel` — baseline post-P-0002 was 308 tests; expect 308 + 7 new (6 channel cases + 1 driver integration) = ~315 tests; zero existing regressions ✅ 2026-05-11T11:22 (bifrost-verify — 322 tests passed / 692 assertions; zero regressions; full count includes Pest dataset expansions)
- [x] [H] `vendor/bin/pint --format agent` clean ✅ 2026-05-11T11:22 (bifrost-verify — `pint --format agent --test` reports no files need formatting)

**Verify:** `vendor/bin/pest --parallel && vendor/bin/pint --test --format agent`

### Phase 2: README + CHANGELOG + tag v0.2.1 + Packagist verify (PUSH GATED on user approval)

**Touches:** `README.md`, `CHANGELOG.md`, git tag

- [x] [H] Update CHANGELOG with a complete `## [0.2.1] - YYYY-MM-DD` section (use `kaisser time --format date-only`):
  - **Fixed** — `GenericInfoEvent::channel()` now returns a severity-based channel string (`error→errors`, `warning→warnings`, `info/success→info`, `audit→audit`, `deployment→deployments`) instead of `null`. Previously, info/warning/success-severity events bypassed `?thread_id=` routing and posted to the webhook channel root.
  - **Tests** — added 6 channel-mapping cases on `GenericInfoEvent` + 1 integration test on `DiscordDriver` asserting `?thread_id=` is applied for `GenericInfoEvent` fires.
  - **Migration note** — consumer apps already calling `Howl::onDiscord()->info(new GenericInfoEvent(...))` will start landing in the configured `#info` thread instead of the channel root once they upgrade to `^0.2.1`. No code change required on the consumer side; verify `HOWL_DISCORD_THREAD_INFO` (or equivalent severity-matched env var) is set. ✅ 2026-05-11T11:23 (round2a-docs, commit 2053f8a)
- [x] [H] Update README: scan for any GenericInfoEvent usage example, severity → thread routing claim, or "post to channel root" language and reconcile with the corrected behavior. Specifically check the "Extending the Event Layer" section + the 7-events table for accuracy. ✅ 2026-05-11T11:23 (round2a-docs — audit clean, no stale claims found; README is correctly silent on GenericInfoEvent channel specifics)
- [x] [H] **GATE — STOP HERE (coordinator+user action, not worker).** Worker reports back with branch summary; coordinator runs `git status` + `git log --oneline main..HEAD`, shows the user the full diff (branch state + CHANGELOG + README + src diff), and asks for explicit approval before re-dispatching for tag + push. **No `git tag` or `git push` until the user confirms.** ✅ 2026-05-11T11:24 (coordinator+user — user approved "tag + push main + Packagist verify")
- [x] [H] After user approval: `git tag v0.2.1 && git push origin v0.2.1`. Packagist auto-publishes via the webhook configured during P-0002 (verified working for v0.2.0). ✅ 2026-05-11T11:25 (round2b-release — annotated tag at 2053f8a, tag object 518881d; pushed both main + v0.2.1 to origin)
- [x] [H] Wait ~30s, verify via `curl -sS https://repo.packagist.org/p2/skaisser/howl.json | jq '.packages."skaisser/howl"[] | select(.version=="0.2.1") | .version'` — returns `"0.2.1"`. ✅ 2026-05-11T11:25 (round2b-release — Packagist returned `0.2.1` on first poll at 30s)

**Verify:** Packagist returns `"0.2.1"`; CHANGELOG has the new section; README scan complete with no stale claims about channel routing.

## Execution Strategy

> **Approach:** `/plan-approved` with Single Team for Phase 1, Single Subagent (gated) for Phase 2
> **Total Tasks:** 12 (H: 11, S: 1, O: 0)
> **Estimated Rounds:** 3 logical (Round 1 + gate + Round 2b) — strict sequential, no cross-phase parallelism possible

### File-Touch Matrix

| Phase | Files / Dirs Touched | Depends On |
|-------|----------------------|------------|
| Phase 1 | `src/Events/GenericInfoEvent.php`, `tests/Unit/Events/GenericInfoEventTest.php`, new `tests/Feature/DiscordDriverTest.php` (or extend existing `tests/Feature/EventDispatchTest.php`), optional `tests/Fixtures/Howl/generic_info_event.json` | — |
| Phase 2 | `README.md`, `CHANGELOG.md`, git tag `v0.2.1` | Phase 1 (release must follow the committed fix) |

Zero file overlap between phases, but Phase 2 is hard-blocked behind Phase 1 (can't release v0.2.1 before the fix lands in the branch). No round-1 parallelism across phases is possible.

### Round 1: Phase 1 → Single Team (Mode C)

Phase 1 contains 1 `[S]` task (driver integration test) plus 6 `[H]` tasks — team mode is mandatory per the marker rule. Team codename: `team-bifrost`. Team-lead is Sonnet (highest marker in phase).

| Task | Marker | Worker | Notes |
|------|--------|--------|-------|
| 1.1 Replace `channel()` body with severity-based `match` | [H] | bifrost-1 (Haiku) | Single-method edit in `src/Events/GenericInfoEvent.php` |
| 1.2 Add 6 channel-mapping assertions to existing test file | [H] | bifrost-1 (Haiku) | Same worker — co-located with the fix it covers |
| 1.4 Fixture check (skip if not channel-referencing) | [H] | bifrost-1 (Haiku) | Trivial; same worker |
| 1.3 DiscordDriver `Http::fake()` integration test | [S] | bifrost-2 (Sonnet) | Independent test file; requires care with config stubs + URL assertion. Can be authored in parallel with bifrost-1's fix; test will pass once 1.1 lands |
| 1.5 Targeted pest (`GenericInfoEvent` + `DiscordDriver` filters) | [H] | bifrost-1 (Haiku) | Verification, run after both workers' diffs are staged |
| 1.6 Full regression `vendor/bin/pest --parallel` | [H] | bifrost-1 (Haiku) | Confirm ~315 tests pass with zero regressions |
| 1.7 `vendor/bin/pint --format agent` clean | [H] | bifrost-1 (Haiku) | Format check |

**Workers within team-bifrost:**
- `bifrost-1` (Haiku): 1.1 → 1.2 → 1.4 (sequential within worker), then runs verification 1.5 / 1.6 / 1.7 after team-lead confirms bifrost-2 is done
- `bifrost-2` (Sonnet): 1.3 (independent; writes the integration test that will go green once bifrost-1's fix is committed)

Team-lead waits for both workers, then runs verification suite via bifrost-1 (one worker, sequential tasks) — no separate verification worker needed since 1.5/1.6/1.7 are pure check-and-report.

### Gate: User Approval (coordinator + user — no worker)

Between Round 1 (done) and Round 2b (tag/publish), the worker for Phase 2 STOPS after tasks 2.1 + 2.2 (CHANGELOG + README) and reports back. The coordinator:
1. Runs `git status` + `git log --oneline main..HEAD`
2. Shows the user the full diff (branch state + CHANGELOG + README + src diff)
3. Asks for explicit approval via `AskUserQuestion`

No `git tag` or `git push` until the user confirms.

### Round 2a: Phase 2 docs (tasks 2.1 + 2.2) → Single Subagent (Mode F)

| Task | Marker | Worker | Notes |
|------|--------|--------|-------|
| 2.1 CHANGELOG `## [0.2.1]` section | [H] | worker-1 (Haiku) | Use `kaisser time --format date-only` for the date |
| 2.2 README audit + reconciliation | [H] | worker-1 (Haiku) | Scan + edit `README.md` for stale routing claims |

Worker reports back to coordinator; coordinator runs the gate (see above).

### Round 2b: Phase 2 release (tasks 2.4 + 2.5) → Single Subagent (Mode F, post-gate)

| Task | Marker | Worker | Notes |
|------|--------|--------|-------|
| 2.4 `git tag v0.2.1 && git push origin v0.2.1` | [H] | worker-1 (Haiku) | Fresh dispatch after user approval |
| 2.5 Packagist verify (`curl` + `jq`) | [H] | worker-1 (Haiku) | Returns `"0.2.1"` on success |

Task 2.3 (the GATE) is owned by the coordinator, not a worker — included in the table above as a coordinator action.

### Parallelism Analysis

- **Cross-phase:** None possible. Phase 2's release tasks (tag/push/Packagist) are gated on Phase 1's fix being committed and on user approval after docs are written.
- **Within Phase 1:** bifrost-1 and bifrost-2 dispatch in parallel (different files, no shared imports). bifrost-2's integration test is authored against expected behavior; once bifrost-1 commits the fix, the test goes green during the team-lead's verification step.
- **Within Phase 2:** Strictly sequential — docs → gate → release.

## References

- [P-0002 howl-events-layer-v0-2-0](./P-0002-feat-howl-events-layer-v0-2-0-complete.md) — the v0.2.0 release this patches
- [decisions.md §10 — Architecture](../decisions.md) — generic templates vs consumer-app extension pattern; no edits needed
- [decisions.md §11 — Severity → emoji map](../decisions.md) — informational; channel mapping derives from same severity vocabulary
- `src/Drivers/DiscordDriver.php:67-91` — `resolveEndpoint()` method whose `'general'` fallback exposes this bug
- `src/Events/GenericInfoEvent.php:77-80` — the broken method being patched

## Acceptance

- [x] `GenericInfoEvent::channel()` returns a non-null severity-specific string for all six documented severities (`error`, `warning`, `info`, `success`, `audit`, `deployment`). ✅ 2026-05-11T11:26 (plan-check verified via `src/Events/GenericInfoEvent.php` diff — `match` covers all 6 severities + `default → 'info'`)
- [x] 6 new channel-mapping unit tests + 1 driver integration test all pass. ✅ 2026-05-11T11:26 (plan-check — bifrost-verify ran `pest --filter=GenericInfoEvent` and `pest --filter=DiscordDriver`: 31 passed / 59 assertions)
- [x] Full pest suite stays green (308 baseline + 7 new = ~315 tests). ✅ 2026-05-11T11:26 (plan-check — bifrost-verify ran `pest --parallel`: **322 passed / 692 assertions**, zero regressions; higher than ~315 estimate due to Pest dataset expansion of the 6-case driver test)
- [x] `vendor/bin/pint --test --format agent` clean. ✅ 2026-05-11T11:26 (plan-check — bifrost-verify reported clean)
- [x] `CHANGELOG.md` has a complete `## [0.2.1]` section per Phase 2 spec. ✅ 2026-05-11T11:26 (plan-check — section present at lines 8-22 with Fixed / Tests / Migration Note subsections; committed in 2053f8a)
- [x] `README.md` has been audited for stale routing claims; any found are corrected. ✅ 2026-05-11T11:26 (plan-check — round2a-docs audited "Extending the Event Layer" + 7-events table + usage examples; no stale claims found; README correctly silent on GenericInfoEvent channel specifics)
- [x] **User has explicitly approved the branch state before tag/push.** ✅ 2026-05-11T11:26 (plan-check — coordinator presented diff summary at 2026-05-11T11:23 gate; user selected "Approve — tag v0.2.1, push tag + main, verify Packagist")
- [x] `git tag v0.2.1` pushed; Packagist returns `0.2.1`. ✅ 2026-05-11T11:26 (plan-check — round2b-release pushed tag v0.2.1 → object 518881d; Packagist returned `0.2.1` at 30s poll)
- [ ] Post-publish: `composer require skaisser/howl:^0.2.1` in a fresh sandbox + fire `Howl::onDiscord()->info(new GenericInfoEvent('post-fix smoke', '...'))` → `Http::fake()` capture confirms URL contains `?thread_id=` + the stubbed info thread ID. **(Manual follow-up — not auto-verified by plan-check; in-repo equivalent is `tests/Feature/DiscordDriverTest.php` which exercises the same `Http::fake()` + thread_id capture path. Sandbox verification is the user's discretion as a smoke test against the published package.)**

## Plan Check

Audited 2026-05-11T11:26 — 12/12 implementation tasks complete, 0 mismatches found, 0 deleted tasks restored, 8/9 acceptance criteria verified (the 9th is a manual post-publish smoke test against the published `^0.2.1` package, deferred to user discretion). All planned Touches files were modified as expected (minor scope notes: fixture path was `tests/Fixtures/Events/` not `tests/Fixtures/Howl/`; README correctly required no changes after audit). 3 commits since v0.2.0 baseline (5882517, 6924c6d, 2053f8a) plus tag v0.2.1 pushed; Packagist confirmed published. No orphaned test references; no test file at plan-review baseline contained removed patterns that were left behind.
