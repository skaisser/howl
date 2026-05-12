---
backlog: null
base: main
branch: feat/howl-events-layer-v0-2-0
completed: "2026-05-11"
created: 2026-05-11T08:40
dependsOn:
    - P-0001
id: P-0002
phases_done: 4
phases_total: 5
pr: 2
project: howl
session: 3d5f545e-d73c-4cb8-9cf5-a0d283bab385
session_id: null
tags:
    - package
    - laravel
    - discord
    - events
    - extension-pattern
tasks_done: 28
tasks_total: 32
title: 'feat: howl events layer v0.2.0 — abstract HowlEvent + 7 generic templates + extension docs'
type: feat
---

# feat: howl events layer v0.2.0 — abstract HowlEvent + 7 generic templates + extension docs

## Goal

Formalize the howl event layer: refactor the `HowlEvent` abstract base around a clean 7-method contract (`severity/title/description/fields/footerMeta/emoji/channel`), add a universal `__construct(array $links = [], array $meta = [])` and two final helpers (`renderLinks`, `baseFooterMeta`), then ship 7 truly-generic event templates (`GenericExceptionEvent`, `GenericInfoEvent`, `AuditEvent`, `DeploymentEvent`, `CronHeartbeatEvent`, `JobRetryExhaustedEvent`, `ManualOperationEvent`). Update `decisions.md §10` to document the corrected architecture, write the extension-pattern guide that lets consuming apps define their own domain templates, and tag `v0.2.0` on Packagist.

## Non-Goals

- **Brazilian-commerce templates (MercadoLivre, FmLabel, Nfe, Bling, Postback, Correios)** — moved to paylog `app/Howl/Events/` per the v0.1.0 scope correction (commit 192ff76); paylog has its own migration plan based on `paylog/docs/howl/paylog-templates-spec.md`.
- **`PostbackReceivedEvent`, `OrderStatusTransitionEvent`, `FinancialEvent`, `CarrierApiHealthEvent`** — Agent-proposed in `paylog/.planning/howl-template-requests.md`. The first three are paylog-side per that doc; `CarrierApiHealthEvent` was originally proposed for the package, but we keep it paylog-side too because "carrier" is e-commerce-specific and the same pattern is already covered by `JobRetryExhaustedEvent` + `GenericInfoEvent`.
- **Telegram or Slack driver implementations** — still v2+/v3 reservation per `decisions.md §2`; separate plan.
- **Bot-side issue auto-filing** — downstream consumer of the footer contract; separate project.

## Context

- **v0.1.0 baseline (P-0001):** `feat/howl-package-v0-1-0` ships `HowlEvent` abstract + 4 generic events (`GenericExceptionEvent`, `AuditEvent`, `DeploymentEvent`, `CronHeartbeatEvent`), 185 Pest tests, pint clean. P-0002 extends this layer; existing tests must not regress.
- **Input docs (all read and incorporated):**
  - `/Users/skaisser/Sites/howl/decisions.md §10` — currently lists the old 10-event roster; needs replacement reflecting the package-generic vs app-specific split.
  - `/Users/skaisser/Sites/paylog/docs/howl/paylog-templates-spec.md` — canonical consuming-app spec defining the `links([...])` constructor convention and the 10 paylog domain templates that will live in `paylog/app/Howl/Events/`. Lines 17–25 list the seven package-shipped events that P-0002 must deliver.
  - `/Users/skaisser/Sites/paylog/.planning/howl-migration-mapping.md` — 259 paylog callsites → templates. 187 map to `GenericExceptionEvent`, 5 to `MercadoLivreWebhookFailedEvent` (paylog-side), etc. Confirms `GenericExceptionEvent` is the workhorse.
  - `/Users/skaisser/Sites/paylog/.planning/howl-template-requests.md` — Agent-proposed templates. The "Summary" table line 9–17 marked `JobRetryExhaustedEvent`, `ManualOperationEvent` as package-side, which we honor.
- **Live-verified `renderLinks()` pattern:** `/tmp/howl-discord-test-v2.php` in paylog confirms the `[label](url)` markdown rendering inside Discord embed field values works on plain webhooks (no bot required). The package's `HowlEvent::renderLinks()` helper produces those fields from the `links` constructor arg.
- **`Pedido::adminUrl()` / `Produtor::adminUrl()` helpers** are paylog-side concerns — the package never imports them. Apps pass resolved URL strings into the `links` array.
- **Severity-mismatch policy (decision required by user):** lock to **(b) throw `\LogicException`** when `->error()` is called on an event whose `severity() === 'info'`. Rationale: low-frequency notifier where caller-side type mismatches are bugs, not legacy code we need to be permissive with; we want to catch them at the seam. Apps can still call `->send($event)` (non-terminal) when they want event-supplied severity to win unconditionally.
- **`toPayload()` backward-compat:** keep as a **final** orchestrator method on `HowlEvent` that calls the 7 contract methods and constructs a `Payload`. Subclasses override the contract methods, not `toPayload()`. v0.1.0 tests that exercise `$event->toPayload()` continue to pass.
- **Branch base:** logically off `main`, but `main` currently has only the gitit scaffold (no v0.1.0 code). Practical resolution at `/plan-review` time: branch from `feat/howl-package-v0-1-0` (the unmerged v0.1.0 branch) so we have the baseline events + tests to refactor. If P-0001 merges to main first, /plan-review rebases the P-0002 branch onto main.
- **`DeploymentEvent` constructor order changes** from v0.1.0 `(version, commit, env, ?duration)` to v0.2.0 `(version, env, commit, ?branch, ?duration, $links, $meta)` — small breaking change, acceptable since v0.1.0 isn't yet on Packagist (P-0001 Phase 8 held).

## Tech Stack Versions

Inherited unchanged from P-0001 (`composer.json` doesn't change):

| Component | Range |
|---|---|
| PHP | `^8.2` (CI cells: 8.2 / 8.3 / 8.4) |
| `illuminate/support` | `^11.0 \| ^12.0` |
| `illuminate/http` | `^11.0 \| ^12.0` |
| `pestphp/pest` | `^3.0` |
| `orchestra/testbench` | `^9.0 \| ^10.0` |
| `laravel/pint` | `^1.0` |
| Distribution | public Packagist (`skaisser/howl`), SemVer; jumps 0.1.0 → 0.2.0 |

## Phases

### Phase 1: Refine abstract HowlEvent base class

**Touches:** `src/Events/HowlEvent.php`, `tests/Unit/Events/HowlEventBaseTest.php` (new), `src/Support/Payload.php` (only if a `Payload::fromEvent(HowlEvent)` factory is added — decide in task 1.2)

- [x] [S] Define the 7-method contract on `HowlEvent` (severity/title/description/fields/footerMeta/emoji/channel) ✅ 2026-05-11T09:02 (commit ca12a52 — **bifrost expanded to 8-method contract**, added `codeBlocks(): array` with empty default per plan recommendation)
- [x] [S] Add the universal constructor `__construct(array $links = [], array $meta = [])` ✅ 2026-05-11T09:02 (commit ca12a52)
- [x] [S] Implement `renderLinks(): array` final helper with `[label](url)` Discord markdown ✅ 2026-05-11T09:02 (commit ca12a52, key→emoji map per plan spec)
- [x] [S] Implement `baseFooterMeta(): array` final helper (event/severity/env/trace/timestamp auto-inject) ✅ 2026-05-11T09:02 (commit ca12a52)
- [x] [S] Implement `toPayload(): Payload` orchestrator ✅ 2026-05-11T09:02 (commit ca12a52 — **NOT yet `final`**; kept as deprecated bridge to preserve v0.1.0 subclass overrides during Phase 1; Phase 2 seals it after subclass migration)
- [x] [H] Move `extractCodeContext(\Throwable)` + `sanitizePath(string)` helpers to new base ✅ 2026-05-11T09:02 (commit ca12a52)
- [x] [H] Remove deprecated `defaultSeverity()`/`defaultChannel()` ✅ 2026-05-11T09:02 (commit ca12a52 — **kept as @deprecated shims** that bridge to new methods; Phase 2 removes them after subclass migration)
- [x] [S] Unit tests for HowlEvent base (7 cases) ✅ 2026-05-11T09:02 (commit 4aded82, 185→192 tests via anonymous-class stub)

> **Round 1 decisions to propagate into Round 2 (Phase 2):**
> 1. Contract has **8 methods**, not 7: added `codeBlocks(): array` (cleaner than overloading `fields()` with code-block markers — bifrost's call per plan recommendation)
> 2. `toPayload()` and the 5 abstract-flagged methods are NOT yet sealed — they're deprecated bridges. Phase 2 migrates the 4 subclasses, removes the shims, makes `toPayload()` final + 5 contract methods abstract.
> 3. `docs/extending-templates.md` was written with "7-method contract walkthrough" — Phase 2 worker should patch this to "8-method" + add a brief `codeBlocks(): array` walkthrough entry alongside template migration.

**Verify:** `vendor/bin/pest tests/Unit/Events/HowlEventBaseTest.php` green; `vendor/bin/pest tests/Unit` (all unit tests including refactored v0.1.0 events) still green; `vendor/bin/pint --test` clean.

### Phase 2: Ship the 7 generic templates

**Touches:** `src/Events/{GenericExceptionEvent,GenericInfoEvent,AuditEvent,DeploymentEvent,CronHeartbeatEvent,JobRetryExhaustedEvent,ManualOperationEvent}.php` (4 refactors + 3 new), `tests/Unit/Events/*` (refactor 4 + add 3), `tests/Fixtures/Events/*.json` (7 new canonical embed shapes)

For each event below: constructor signature is the canonical v0.2.0 shape; existing tests must be migrated to the new contract methods (`severity/title/description/fields/footerMeta/emoji/channel`) instead of asserting `Payload` shape directly. Tests assert event-to-`Payload` rendering against a JSON fixture under `tests/Fixtures/Events/<EventName>.json` (canonical embed shape). Tests cover: happy path with non-empty links + meta, edge cases (empty links, empty meta, optional ctor args omitted).

- [x] [S] `GenericExceptionEvent` REFACTOR ✅ 2026-05-11T09:18 (commit 3f8a582)
- [x] [S] `GenericInfoEvent` NEW ✅ 2026-05-11T09:18 (commit 3f8a582)
- [x] [S] `AuditEvent` REFACTOR ✅ 2026-05-11T09:18 (commit faf4dce)
- [x] [S] `DeploymentEvent` REFACTOR (constructor order change v0.1.0 `(version,commit,env)` → v0.2.0 `(version,env,commit,?branch,?duration,$links,$meta)`) ✅ 2026-05-11T09:18 (commit faf4dce)
- [x] [S] `CronHeartbeatEvent` REFACTOR ✅ 2026-05-11T09:18 (commit faf4dce)
- [x] [S] `JobRetryExhaustedEvent` NEW ✅ 2026-05-11T09:18 (commit 860ccb6)
- [x] [S] `ManualOperationEvent` NEW ✅ 2026-05-11T09:18 (commit 860ccb6)
- [x] [S] Migrate v0.1.0 tests to new contract methods ✅ 2026-05-11T09:18 (across commits 3f8a582 + faf4dce; also commit ee9e3af patched `PendingNotificationEventAcceptanceTest` to use `severity()` instead of removed `defaultSeverity()`)
- [x] [H] Author 7 canonical JSON fixtures at `tests/Fixtures/Events/` ✅ 2026-05-11T09:18 (commit 132cdde — 7 fixtures + `tests/Feature/EventFixtureSnapshotTest.php`, 7 new snapshot tests)

> **Round 2 seal (commit 0e6b686, 8ffbcaa):** `toPayload()` now `final`, 5 contract methods (`severity/title/description/fields/emoji`) now `abstract`, `defaultSeverity()`/`defaultChannel()` shims removed from base. Reflection verdicts confirmed: `toPayload->isFinal()=true`, `severity->isAbstract()=true`, `hasMethod('defaultSeverity')=false`. Docs patched 7-method → 8-method (codeBlocks walkthrough + example update + README sentence). Total: 297 tests pass, pint clean.

**Verify:** `vendor/bin/pest tests/Unit/Events` green (7 event classes, 7 fixtures, ~50 tests). `vendor/bin/pest --parallel --processes=10` green (full suite including 185 v0.1.0 tests). `vendor/bin/pint --test` clean.

### Phase 3: Wire templates into the fluent API

**Touches:** `src/Support/PendingNotification.php`, `src/Howl.php` (only if the severity-mismatch throw needs a config flag), `tests/Feature/EventDispatchTest.php` (new), `tests/Feature/SeverityMismatchTest.php` (new)

- [x] [S] Update `PendingNotification::send($severityOrEvent = 'info')` and each terminal severity method (`error`, `warning`, `info`, `success`, `audit`, `deployment`) to detect a `HowlEvent` instance and delegate to its 7 contract methods through `toPayload()`. The v0.1.0 wire (commits `af9c2f9` + `d832b43`) already does this against the old base — refresh against the new contract. ✅ 2026-05-11T09:56 (valhalla-1 commit 1af324e — refreshed against new 8-method contract)
- [x] [S] Builder-state-wins-on-collision: when the builder has been configured (e.g. `->title('Override')->error(new GenericExceptionEvent($e))`), builder values win over the event's contract methods for the colliding properties. Builder-set fields are *appended* to event-supplied fields, not replaced. Document precisely in the method docstrings. ✅ 2026-05-11T09:56 (valhalla-1 commit 1af324e — added `$severityOverride` property + `->severity()` builder method; scalars win, fields appended)
- [x] [S] Severity-mismatch policy: when `->error(new GenericInfoEvent(...))` is called and `$event->severity() !== 'error'` AND no `->severity(...)` builder override was set, **throw `\LogicException`** with a clear message: `"Howl: terminal verb ->error() conflicts with event severity 'info'. Use ->send($event) to defer to the event, or set ->severity('error') explicitly to override."`. Add a `tests/Feature/SeverityMismatchTest.php` covering: (a) throw on mismatch, (b) no throw when explicit `->severity()` override matches verb, (c) no throw when using `->send($event)` (non-terminal), (d) no throw when event severity matches verb. ✅ 2026-05-11T09:56 (valhalla-1 commit 1af324e — throw wired in 6 terminal verbs; valhalla-2 commit 48ed91a — 4-case SeverityMismatchTest green)
- [x] [S] Feature tests in `tests/Feature/EventDispatchTest.php`: dispatch each of the 7 generic events end-to-end through the facade → `Howl::onDiscord()->{verb}($event)` → `HowlFake` captures → assert captured `Payload` matches the event's `toPayload()` output. One test per event = 7 tests. Use a parameterized Pest dataset. ✅ 2026-05-11T09:56 (valhalla-2 commit 48ed91a — 7 cases via Pest dataset, all green)
- [x] [S] Backward-compat regression test: all P-0001 commits `af9c2f9` + `d832b43` test cases continue to pass against the new contract. Run `tests/Unit/PendingNotificationEventAcceptanceTest.php` (or wherever the v0.1.0 wire tests live) and confirm green. ✅ 2026-05-11T09:56 (coordinator verified — full suite 308 passed / 661 assertions, 297 v0.1.0+v0.2.0-base baseline + 11 new = 0 regressions)

**Verify:** `vendor/bin/pest --filter='EventDispatch|SeverityMismatch|PendingNotificationEvent'` green. `vendor/bin/pest --parallel --processes=10` full-suite green.

### Phase 4: Docs + extension pattern

**Touches:** `decisions.md`, `docs/extending-templates.md` (new), `docs/example-app-template.md` (new), `README.md`

This phase is parallel-safe with Phases 2/3 — touches different files (only `README.md` is shared but the change is additive).

- [x] [S] Rewrite `decisions.md §10` (architectural split + 7 generic templates table + extension code sample + forward-link) ✅ 2026-05-11T09:02 (commit 44151b1)
- [x] [S] Write `docs/extending-templates.md` (≥150 lines, 6 required sections) ✅ 2026-05-11T09:02 (commit 3547f84 — **493 lines, 7 sections**; written against 7-method contract — Phase 2 worker will patch to 8-method with `codeBlocks()` walkthrough)
- [x] [S] Write `docs/example-app-template.md` (≥80 lines, fictional `OrderShippedEvent` worked example) ✅ 2026-05-11T09:02 (commit 3547f84 — **226 lines**, full class + Pest test + dispatch site + embed mockup; written against 7-method contract — Phase 2 worker will patch to add `codeBlocks(): array` to the example)
- [x] [H] Update `README.md` — Extending the Event Layer subsection + 4→7 events table ✅ 2026-05-11T09:02 (commit 44151b1, ~20-line code sample with `OrderShippedEvent`)

**Verify:** `wc -l docs/extending-templates.md` ≥ 150; `wc -l docs/example-app-template.md` ≥ 80; `grep -c "^## " docs/extending-templates.md` ≥ 6 (six required sections); `grep -E "GenericInfoEvent|JobRetryExhaustedEvent|ManualOperationEvent" README.md` returns hits (new templates documented).

### Phase 5: Tag v0.2.0 + Packagist auto-update

**Touches:** `CHANGELOG.md`, `composer.json` (version field — only if we keep one; Packagist reads from git tags so usually unnecessary), git tag

- [x] [H] Write `CHANGELOG.md` v0.2.0 entry. Include: (a) NEW — `GenericInfoEvent`, `JobRetryExhaustedEvent`, `ManualOperationEvent`; the universal `__construct(array $links = [], array $meta = [])`; the `renderLinks()` + `baseFooterMeta()` helpers; `docs/extending-templates.md` + `docs/example-app-template.md`. (b) CHANGED — `HowlEvent` base contract refactored to 7 explicit methods (`severity/title/description/fields/footerMeta/emoji/channel`); `DeploymentEvent` constructor order changed from `(version, commit, env)` → `(version, env, commit, ?branch, ?duration, $links, $meta)`. (c) DEPRECATED/REMOVED — `defaultSeverity()` and `defaultChannel()` from the base class (replaced by `severity()` and `channel()`). (d) RELEASE NOTES — point readers at `docs/extending-templates.md` and the spec doc in paylog's repo. ✅ 2026-05-11T10:00 (ragnarok-1 commit 50a2287 — CHANGELOG.md 52 lines, **8-method contract** corrected from plan's 7-method text)
- [x] [S] **Gate check:** confirm P-0001 has tagged `v0.1.0` on Packagist OR confirm with the user that we ship `v0.2.0` directly without an intermediate `v0.1.0` release. If the latter, P-0001 Phase 8 work (Packagist submission, smoke tests) folds into P-0002 Phase 5. ✅ 2026-05-11T10:00 (coordinator + user — **Option B locked in**: skip v0.1.0, ship v0.2.0 directly; P-0001 Phase 8 archived)
- [ ] [H] Tag the release: `git tag v0.2.0` then `git push origin v0.2.0`. The Packagist webhook (wired in P-0001 Phase 8 or being wired here) auto-publishes.
- [ ] [H] Wait ~30s, then verify via `curl -sS https://repo.packagist.org/p2/skaisser/howl.json | jq '.packages."skaisser/howl"[] | select(.version=="0.2.0")'` returns a result.
- [ ] [S] Smoke test in a scratch sandbox: `composer create-project laravel/laravel howl-smoke-v2 "12.*"` → `composer require skaisser/howl:^0.2.0` → `php artisan vendor:publish --tag=howl-config` → `php artisan tinker` → `Howl::onDiscord()->info(new \Skaisser\Howl\Events\GenericInfoEvent('Smoke v0.2.0', 'Hello from v0.2.0', links: ['repo' => 'https://github.com/skaisser/howl']))` → confirm embed lands with the link rendered correctly.
- [ ] [S] Repeat smoke test against Laravel 11 (`composer create-project laravel/laravel howl-smoke-v2-l11 "11.*"`).

**Verify:** `curl -sS https://repo.packagist.org/p2/skaisser/howl.json | jq '.packages."skaisser/howl"[0].version'` returns `"0.2.0"`. Both smoke tests post live embeds to the Discord sandbox webhook with the v0.2.0 contract output (verify color + author + footer + `[label](url)` link rendering).

## Tech Notes

- Backward compat with v0.1.0 events: keep `toPayload()` as a **final** method on `HowlEvent` so callers / tests written against v0.1.0 continue to work. Subclasses don't override `toPayload()` — they override the 7 contract methods.
- The `DeploymentEvent` constructor order change (commit/env → env/commit) is the only breaking change in v0.2.0. Acceptable because v0.1.0 isn't yet on Packagist (P-0001 Phase 8 held). CHANGELOG documents it clearly.
- Severity mismatch as throw (not warn): debated in Context bullets above; final answer is throw to catch caller bugs at the seam. Apps can use `->send($event)` to opt out.

## References

- [P-0001 howl-package-v0-1-0](./P-0001-feat-howl-package-v0-1-0-approved.md) — the v0.1.0 base this layer extends
- [decisions.md](../decisions.md) — §10 to be rewritten in Phase 4
- [paylog-templates-spec.md](../../../paylog/docs/howl/paylog-templates-spec.md) — consuming-app spec, source of truth for the `links([...])` convention and the package-vs-app split
- [paylog/.planning/howl-migration-mapping.md](../../../paylog/.planning/howl-migration-mapping.md) — 259-callsite mapping confirming `GenericExceptionEvent` workhorse role and template usage frequencies
- [paylog/.planning/howl-template-requests.md](../../../paylog/.planning/howl-template-requests.md) — Agent-proposed templates; informed which 7 belong in the package vs paylog-side

## Acceptance

- [x] All v0.1.0 tests still pass (no regression in driver/embed/facade/queue/fallback layer). Baseline 185 → at least 185 after P-0002 refactors land. ✅ 2026-05-11T10:03 (308 passed / 661 assertions — 297 v0.1.0+v0.2.0-base baseline + 11 new Phase 3 tests, 0 regressions)
- [x] 7 new template Pest tests (one per generic template) + dedicated tests for the base class helpers (`renderLinks`, `baseFooterMeta`, `toPayload` orchestration). ✅ 2026-05-11T10:03 (`tests/Feature/EventFixtureSnapshotTest.php` 7 cases + `tests/Unit/Events/HowlEventBaseTest.php` 7 cases — Phase 1+2 commits 4aded82, 132cdde)
- [ ] A consumer-app dev can read `docs/extending-templates.md` alone (no source-code spelunking) and produce a working domain template. Verify by self-test: hand the doc to a fresh subagent and ask it to produce a working `OrderShippedEvent` against the API. (Phase 5 acceptance gate.) ⏸ DEFERRED — self-test scheduled for post-merge cycle
- [ ] `v0.2.0` published to Packagist and `composer require skaisser/howl:^0.2.0` resolves cleanly in fresh Laravel 11 and 12 sandboxes. ⏸ DEFERRED to post-merge (tasks 5.3+5.4+5.5+5.6)
- [x] `decisions.md §10` reflects the corrected architecture (package generic + app extension pattern; the old 10-event table is gone). ✅ 2026-05-11T10:03 (Phase 4 commit 44151b1)
- [x] CHANGELOG documents the `DeploymentEvent` constructor order change as a v0.2.0 breaking change. ✅ 2026-05-11T10:03 (Phase 5 commit 50a2287 — "### Changed" section)
- [x] All v0.2.0 builder paths (`->error/info/audit/deployment/send` accepting `HowlEvent`) throw `\LogicException` on severity mismatch (covered by `tests/Feature/SeverityMismatchTest.php`). ✅ 2026-05-11T10:03 (Phase 3 commits 1af324e + 48ed91a — 4 SeverityMismatchTest cases all green)
- [x] `vendor/bin/pint --test` clean. ✅ 2026-05-11T10:03 (verified post-Round-3 + post-CHANGELOG)
- [x] ~~CI matrix (PHP 8.2/8.3/8.4 × Laravel 11/12) green on the P-0002 PR.~~ ✅ 2026-05-11T10:30 (SUPERSEDED — CI workflow dropped per user decision: `pestphp/pest-plugin-laravel ^3.0` caps at L11/L12 and isn't actually used by Howl's tests; local testing on PHP 8.3/8.4 × Laravel 12/13 is the contract going forward)

## Execution Strategy

> **Approach:** `/plan-approved` with mixed Mode B (Parallel Teams) + Mode C (Single Team) across 4 rounds
> **Total Tasks:** 32 (H: 7, S: 25, O: 0)
> **Estimated Rounds:** 4 (1 parallel round saving ~1 sequential round vs naive 5-round serial)

### File-touch matrix

| Phase | Files / Dirs Touched | Depends On | Parallel-safe with |
|---|---|---|---|
| Phase 1 | `src/Events/HowlEvent.php`, `tests/Unit/Events/HowlEventBaseTest.php`, possibly `src/Support/Payload.php` (only if a `fromEvent()` factory is added) | — (greenfield refactor — uses v0.1.0 base as starting point) | Phase 4 ✅ (docs only, no code overlap) |
| Phase 2 | `src/Events/{GenericExceptionEvent,GenericInfoEvent,AuditEvent,DeploymentEvent,CronHeartbeatEvent,JobRetryExhaustedEvent,ManualOperationEvent}.php`, `tests/Unit/Events/*`, `tests/Fixtures/Events/*.json` | Phase 1 (subclasses extend new contract; base must be merged first) | nothing (modifies all 4 v0.1.0 events + adds 3 new — single team is correct) |
| Phase 3 | `src/Support/PendingNotification.php`, possibly `src/Howl.php`, `tests/Feature/EventDispatchTest.php`, `tests/Feature/SeverityMismatchTest.php` | Phase 2 (feature tests dispatch the 7 events end-to-end; classes must exist) | nothing |
| Phase 4 | `decisions.md`, `docs/extending-templates.md`, `docs/example-app-template.md`, `README.md` | — (writes from spec; doesn't import code) | Phases 1, 2, 3 ✅ (zero file overlap with any code phase) |
| Phase 5 | `CHANGELOG.md`, git tag `v0.2.0`, Packagist webhook | Phases 1–4 + P-0001 v0.1.0 release decision | nothing (releases the merged work) |

**Parallelism analysis:** Phase 4 (docs) is the parallelism win — it writes from the spec without depending on any code phase's output. Co-scheduling Phase 4 with Phase 1 in Round 1 saves one full sequential round. Phase 2 → Phase 3 must remain sequential (Phase 3 feature tests dispatch Phase 2 events). Phase 5 must remain after all preceding work merges.

### Rounds

#### Round 1: Phase 1 + Phase 4 → Parallel Teams (2 team-leads, dispatched together)

Both phases have `[S]` work. Phase 1 designs the contract; Phase 4 documents it. Zero file overlap — Phase 1 touches `src/Events/HowlEvent.php`, Phase 4 touches `decisions.md` / `docs/*` / `README.md`.

| Phase | Mode | Model | Tasks | Notes |
|---|---|---|---|---|
| Phase 1: Refine HowlEvent base | Team-lead (`team-bifrost`) | Sonnet | 6 × `[S]` + 2 × `[H]` | Contract methods + universal constructor + final helpers (`renderLinks`, `baseFooterMeta`, `toPayload`) + unit tests for 7 base behaviors |
| Phase 4: Docs + extension pattern | Team-lead (`team-asgard`) | Sonnet | 3 × `[S]` + 1 × `[H]` | decisions.md §10 rewrite, `docs/extending-templates.md` (≥150 lines, 6 sections), `docs/example-app-template.md` (fictional OrderShippedEvent, ≥80 lines), README extension subsection |

**Coordination note:** Phase 4's `docs/extending-templates.md` describes the same contract that Phase 1 implements. If Phase 1 surfaces a contract refinement mid-round (e.g. discovers `channel(): ?string` needs to be `string` non-null), the leader pings `team-asgard` via `SendMessage` to align the doc before they ship. This is exactly the live-coordination case team mode exists for.

#### Round 2: Phase 2 → Single Team (1 phase, mostly `[S]`)

Modifies/creates 7 event class files + refactors 4 existing test files + adds 3 new test files + creates 7 JSON fixtures. Has `[S]` work throughout → team mode.

| Task | Model | Worker | Notes |
|---|---|---|---|
| 2.1 `GenericExceptionEvent` refactor | `[S]` | worker-1 | Constructor adds optional title/desc/links/meta; contract methods |
| 2.2 `GenericInfoEvent` (NEW) | `[S]` | worker-1 | Thin wrapper; `$severity` from constructor |
| 2.3 `AuditEvent` refactor | `[S]` | worker-2 | Target summary + diff code-block |
| 2.4 `DeploymentEvent` refactor (constructor order change) | `[S]` | worker-2 | BREAKING; documented in CHANGELOG |
| 2.5 `CronHeartbeatEvent` refactor | `[S]` | worker-2 | Status-driven severity/channel |
| 2.6 `JobRetryExhaustedEvent` (NEW) | `[S]` | worker-3 | Payload + last-exception code-block |
| 2.7 `ManualOperationEvent` (NEW) | `[S]` | worker-3 | Args JSON-truncate |
| 2.8 Refactor v0.1.0 tests for the 4 existing events | `[S]` | worker-4 | Assert contract methods, keep one e2e per event |
| 2.9 Author 7 JSON fixtures at `tests/Fixtures/Events/` | `[H]` | worker-4 | Mechanical — capture `toPayload()` output |

#### Round 3: Phase 3 → Single Team (1 phase, all `[S]`)

Modifies `src/Support/PendingNotification.php` (5-task sequential within the same file). All `[S]` work.

| Task | Model | Worker | Notes |
|---|---|---|---|
| 3.1 Refresh PendingNotification event-detection wire | `[S]` | worker-1 | Updates v0.1.0 wire against new contract |
| 3.2 Builder-state-wins-on-collision | `[S]` | worker-1 | Same file as 3.1 — sequential |
| 3.3 Severity-mismatch throw policy (`\LogicException`) | `[S]` | worker-1 | Same file — sequential |
| 3.4 `tests/Feature/EventDispatchTest.php` (7 dataset cases) | `[S]` | worker-2 | New file, independent |
| 3.5 `tests/Feature/SeverityMismatchTest.php` (4 cases a/b/c/d) | `[S]` | worker-2 | New file, independent — can parallel-write with 3.1–3.3 if scope is clear |

#### Round 4: Phase 5 → Single Team (1 phase, mixed `[S]`/`[H]`)

Release work. Sequential because tag + Packagist verification + smoke tests have a strict order. `[S]` present → team mode.

| Task | Model | Worker | Notes |
|---|---|---|---|
| 5.1 Write `CHANGELOG.md` v0.2.0 entry | `[H]` | worker-1 | NEW/CHANGED/DEPRECATED sections; flag DeploymentEvent breaking change |
| 5.2 Gate check on P-0001 v0.1.0 release | `[S]` | leader (orchestrator confirms with user) | If v0.1.0 not released, fold P-0001 Phase 8 work in here |
| 5.3 Tag `v0.2.0` + push (after gate clears) | `[H]` | leader (orchestrator runs `git tag` + `git push origin v0.2.0`) | One-shot release action |
| 5.4 Verify Packagist auto-published v0.2.0 | `[H]` | worker-2 | `curl` + `jq` check |
| 5.5 Smoke test on Laravel 12 sandbox | `[S]` | worker-3 | `composer create-project` + tinker + live Discord render |
| 5.6 Smoke test on Laravel 11 sandbox | `[S]` | worker-3 | Same recipe, L11 |

### Round Summary

| Round | Phases | Mode | Workers / Teams | Parallel? |
|---|---|---|---|---|
| 1 | Phase 1 + Phase 4 | B | 2 team-leads (Sonnet) — `bifrost`, `asgard` | ✅ parallel |
| 2 | Phase 2 | C | 1 team, 4 workers (Sonnet for [S], one [H] fixture task) | — |
| 3 | Phase 3 | C | 1 team, 2 workers (Sonnet) | — |
| 4 | Phase 5 | C | 1 team, 3 workers (mixed Sonnet/Haiku) + leader action for tag-push | — |

Parallel savings: Round 1 ships 2 phases concurrently — net savings of ~1 round-equivalent vs naive 5-round serial.

## Plan Check

Audited 2026-05-11T10:03 (refreshed 10:30 after CI drop) — 28/41 tasks implemented, 0 mismatches fixed, 0 deleted restored, 7/9 AC verified (2 deferred: docs-self-test + Packagist publish — gated on post-merge tag; 1 superseded: CI matrix — workflow dropped 2026-05-11T10:30 per user, replaced by local-testing contract).

**Split-flow context (locked Option B):** v0.1.0 skipped; v0.2.0 ships directly. Round 4 deliberately split — CHANGELOG + gate-check landed pre-PR (tasks 5.1, 5.2 ✅); tag/Packagist verify/smoke L11+L12 deferred until after `feat/howl-events-layer-v0-2-0 → main` merges (tasks 5.3, 5.4, 5.5, 5.6 ⏸).

**State at PR-open:**
- 308 tests pass / 661 assertions (full suite, parallel `--processes=10`)
- pint --test clean
- 58 commits ahead of main on feat/howl-events-layer-v0-2-0
- No deleted/orphaned tasks; no orphaned test references found
- CHANGELOG.md (52 lines, v0.2.0 entry) shipped at commit 50a2287

**Soft signal (worth filing):** caught a one-off parallel-test flake on `Tests\Unit\SeverityMatrixTest > it payload channel is pass…` during `--processes=10` — passed on re-run, passed in isolation. Likely a parallel-isolation race; worth a backlog item to investigate.
