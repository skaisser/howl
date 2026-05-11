# Changelog

All notable changes to `skaisser/howl` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.1] - 2026-05-11

### Fixed

- `GenericInfoEvent::channel()` now returns a severity-based channel string (`error→errors`, `warning→warnings`, `info/success→info`, `audit→audit`, `deployment→deployments`) instead of `null`. Previously, info/warning/success-severity events bypassed `?thread_id=` routing and posted to the webhook channel root.

### Tests

- Added 6 channel-mapping unit test cases on `GenericInfoEvent` covering all documented severities.
- Added 1 driver integration test (`tests/Feature/DiscordDriverTest.php`) — a dataset of 6 cases asserting `Http::fake()` captures `?thread_id=<expected>` for each severity through the full event → payload → driver resolution path.

### Migration Note

- Consumer apps already calling `Howl::onDiscord()->info(new GenericInfoEvent(...))` will start landing in the configured `#info` thread instead of the channel root once they upgrade to `^0.2.1`. No code change required on the consumer side; verify `HOWL_DISCORD_THREAD_INFO` (or equivalent severity-matched env var) is set in `.env`.

## [0.2.0] — 2026-05-11

First public Packagist release. Establishes the extensible event-template layer.

### Added

- **`HowlEvent` abstract base** with an 8-method contract: `severity()`, `title()`, `description()`, `fields()`, `emoji()`, `codeBlocks()`, `footerMeta()`, `channel()`. `toPayload()` is `final` and orchestrates the contract.
- **Universal constructor** on all events: `__construct(array $links = [], array $meta = [])` for caller-supplied action links + arbitrary metadata.
- **Final helpers on the base:** `renderLinks(): array` (Discord-markdown `[label](url)` rendering with key→emoji map) + `baseFooterMeta(): array` (auto-injects event/severity/env/trace/timestamp).
- **3 new generic events:** `GenericInfoEvent`, `JobRetryExhaustedEvent`, `ManualOperationEvent`.
- **`docs/extending-templates.md`** — 493-line guide to authoring domain templates against the 8-method contract (includes the `links([...])` convention and `footerMeta` extension pattern).
- **`docs/example-app-template.md`** — worked example: a fictional `OrderShippedEvent` end-to-end with class, Pest test, dispatch site, and embed mockup.
- **Fluent API event detection:** `Howl::onDiscord()->{verb}($event)` and `->send($event)` now accept `HowlEvent` instances and route through `toPayload()`. Builder values (`->title()`, `->channel()`, `->severity()`, etc.) win on collision; builder-supplied `->field()` calls are *appended* to event-supplied fields.
- **Severity-mismatch enforcement:** terminal verbs throw `\LogicException` when called with an event whose `severity()` doesn't match the verb, unless an explicit `->severity()` override was set. `->send($event)` (non-terminal) never throws — defers to event's severity.
- **`tests/Fixtures/Events/*.json`** — 7 canonical snapshot fixtures (one per generic event).
- **`tests/Feature/EventDispatchTest.php`** — end-to-end dispatch coverage for all 7 generic events (parameterized Pest dataset).
- **`tests/Feature/SeverityMismatchTest.php`** — 4 cases covering the throw policy.

### Changed

- **`HowlEvent` base contract refactored** from the v0.1.0 ad-hoc shape to the explicit 8-method contract listed above. `toPayload()` is now `final` and `severity()` is `abstract` — subclasses MUST declare their severity.
- **`DeploymentEvent` constructor order changed** (BREAKING for direct instantiators): v0.1.0 `(string $version, string $commit, string $env, ?int $duration)` → v0.2.0 `(string $version, string $env, string $commit, ?string $branch = null, ?int $durationSeconds = null, array $links = [], array $meta = [])`. Reorder any direct `new DeploymentEvent(...)` call sites; the v0.2.0 order matches the "what shipped where" reading order (version, env, commit).
- **`GenericExceptionEvent`, `AuditEvent`, `CronHeartbeatEvent`** refactored onto the new contract. Public API unchanged for the common cases; only contract-method override sites need adjustment.

### Deprecated

- *(none — deprecations from v0.1.0 are removed in this release; see below)*

### Removed

- **`HowlEvent::defaultSeverity()`** — replaced by abstract `severity()`. Subclasses must declare severity explicitly.
- **`HowlEvent::defaultChannel()`** — replaced by `channel(): ?string` on the contract.

### Migration notes

- If you authored a subclass against the v0.1.0 base, swap `defaultSeverity()` → `severity()` and `defaultChannel()` → `channel()`. Add `codeBlocks(): array` returning `[]` if you don't need code blocks.
- If you instantiate `DeploymentEvent` directly, reorder the constructor args (see Changed above).
- Builder collision rules: builder values win for scalars (`title`, `description`, `channel`, `severity`); builder-supplied `->field()` calls are appended, not replacing event fields.

### Release notes

- See [`docs/extending-templates.md`](docs/extending-templates.md) for the full author guide.
- See [`docs/example-app-template.md`](docs/example-app-template.md) for a worked example: a fictional `OrderShippedEvent` end-to-end.

[0.2.0]: https://github.com/skaisser/howl/releases/tag/v0.2.0
