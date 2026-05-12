---
id: "P-0008"
title: "docs: VitePress docs site + llms.txt + README/CHANGELOG/migration → tag v1.0.0"
type: docs
project: howl
branch: docs/vitepress-site-and-v1-release
base: homolog
tags: [docs, vitepress, cloudflare-pages, llms-txt, changelog, migration, release, v1-0-0, path-to-v1]
backlog: null
dependsOn: ["P-0005", "P-0006", "P-0007"]
created: 2026-05-12T01:21
session_id: null
session: "ca1c12c4-eca2-423c-a1da-0ec265f7a0c4"
---

# docs: VitePress documentation site + llms.txt + README/CHANGELOG/migration → tag v1.0.0

## Goal

Ship the public face of howl — a polished VitePress documentation site at `howl.skaisser.dev` (Laravel-docs style, hosted on Cloudflare Pages), root-level `llms.txt` + `llms-full.txt` for LLM tool discovery, a rewritten README that leads to the docs site, a consolidated `[1.0.0]` CHANGELOG entry, a migration guide for v0.x consumers, GitHub repo polish (description / topics / social preview), and finally tag and push **`v1.0.0`** as the first stable public release of `skaisser/howl`. After this plan merges and tags, `composer require skaisser/howl:^1.0` is the recommended install for any Laravel 12/13 project.

## Non-Goals

- **Do NOT add or modify package source code (`src/`).** No `composer.json` `require` changes. This plan is documentation + release artifacts only.
- **Do NOT delete or yank pre-1.0 releases.** `v0.1.0`, `v0.2.0`, `v0.2.1` stay on GitHub Releases and Packagist (user locked: "keep them — history matters").
- **Do NOT add mutation testing (Infection), translated docs, video tutorials, or demo public bots.** All deferred to post-v1.0.
- **Do NOT auto-tag via CI.** Manual tag for v1.0.0 — safer for the first stable release; user is explicit on the tag command.
- **Do NOT bump `composer.json` to a higher Laravel/PHP floor.** Constraints stay `^8.3` PHP, `^12.0 || ^13.0` Laravel.
- **Do NOT add new tests.** P-0007 already locked 100% coverage; this plan doesn't touch the test suite.
- **Do NOT redesign howl's public API in response to docs-writing pain.** If documenting something is awkward, file a post-v1 backlog item; don't refactor here.

## Context

- **Path to v1.0.0 (4-plan sequence):** P-0005 (done) → P-0006 (done) → P-0007 (done) → **P-0008 (this plan, release)**.
- This is the FIRST plan that touches user-facing release artifacts. P-0005, P-0006, and P-0007 all explicitly deferred release work here.
- **Locked from interview:**
  - **Tool**: VitePress (Spatie's docs are VitePress — proves the tool delivers the Laravel-docs aesthetic). Vue/Vite ecosystem aligns with Laravel community. Built-in support for typography, sidebar, search, version dropdown.
  - **Design lineage**: Laravel docs (`laravel.com/docs/13.x`) + Spatie package docs. Both have: prominent Upgrade Guide and Release Notes per version, detailed sidebar organization, every API surface documented, version dropdown in nav.
  - VitePress site location: `/docs/` in the howl repo (same-repo strategy).
  - Hosting: Cloudflare Pages with custom domain `howl.skaisser.dev`.
  - LLM docs: BOTH `llms.txt` (index) AND `llms-full.txt` (inline) at repo root + served at the site.
  - All P-0008 scope items: core docs + release artifacts (README/CHANGELOG/migration) + paylog cross-ref + GitHub polish.
  - Release version: **v1.0.0** (clean break, multi-driver shipped, API stable).
- **Governing policy (from `src/CLAUDE.md`, landed in P-0005 Phase 6):** docs are **versioned per release**, mirroring Laravel docs structure. Each tagged release has its own complete snapshot under `/docs/v{N.M}/`. The `/docs/` root mirrors `latest`. Pre-release work happens on `/docs/next/` and is promoted via **copy-then-diff** when the release tag is cut (copy previous version, edit only what changed). Every version's sidebar leads with an **Upgrade Guide** page (breaking changes + migration) and a **Release Notes** page (additive features) — both follow the Laravel docs pattern at `/docs/13.x/upgrade` and `/docs/13.x/releases`. This plan implements the v1.0.0 versioning infrastructure (Phase 1) and ships v1.0.0 docs as the first frozen snapshot (Phase 9).
- `README.md` — currently rich (~500 lines) with full inline docs. Phase 6 rewrites to a tight quick-start that links to the docs site.
- `CHANGELOG.md` — Keep a Changelog format, current entries `[0.2.0]`, `[0.2.1]`. Phase 6 prepends `[1.0.0]` entry.
- `.github/workflows/` — contains `claude.yml` only (currently). Phase 1 adds `deploy-docs.yml` for Cloudflare Pages.
- `composer.json:6` — `"homepage": "https://github.com/skaisser/howl"`. Phase 8 updates to `https://howl.skaisser.dev`.
- `composer.json` — `require`/`require-dev` UNCHANGED in this plan.
- `~/Sites/paylog/.planning/P-0222-chore-howl-alert-quality-audit-todo.md` — paylog's plan that references "Howl v0.3.0" (from old assumptions). Phase 7 updates the reference to "v1.0.0". This is a DIFFERENT working directory than howl — Phase 7 explicitly handles the cwd change.
- **Cloudflare Pages setup**: requires a Cloudflare account, a Pages project linked to the GitHub repo, and a CNAME for `howl.skaisser.dev` pointing to the Pages domain. The `/cloudflare-auto-deploy` skill handles this end-to-end but the manual steps are: (1) create Pages project, (2) point to `docs/.vitepress/dist` as build output, (3) build command `npm run docs:build`, (4) add custom domain.
- **VitePress install footprint**: `package.json` + `node_modules/` only at the repo root (no impact on PHP/Composer). VitePress 1.x + Vue 3 are dev-only. Build output is a static site (no runtime needed).
- **llmstxt.org spec**: plain text, structured headers (`# Project`, `## Section`), each line `- [title](url): description`. `llms.txt` is the index; `llms-full.txt` inlines all linked pages.
- **GitHub repo polish via `gh` CLI**: `gh repo edit --description "..." --homepage "..." --add-topic ...` for repo metadata. Social preview is uploaded manually via Settings → Options → Social preview (no `gh` API for that as of 2026).

## Phases

### Phase 1: VitePress scaffold + versioning infrastructure + Cloudflare Pages deployment workflow + DNS verification

**Touches:** `docs/.vitepress/config.ts` (new), `docs/index.md` (new placeholder), `docs/next/` (new directory for in-flight v1.0.0 work), `package.json` (new), `package-lock.json` (new), `.gitignore` (extend), `.github/workflows/deploy-docs.yml` (new), Cloudflare Pages dashboard (external)

- [ ] [H] `npm init -y` then `npm install -D vitepress vue` at repo root. Add `"docs:dev": "vitepress dev docs"`, `"docs:build": "vitepress build docs"`, `"docs:preview": "vitepress preview docs"` to `package.json` scripts.
- [ ] [H] **Versioning structure**: create the per-version directory layout from the start so the docs render correctly even at v1.0.0:
  - `docs/next/` — where Phases 2-5 author the v1.0.0 content while it's still in-flight on this branch.
  - `docs/v1.0.0/` — does NOT exist yet; Phase 9 creates it by copying `docs/next/` at tag time.
  - `docs/` root (e.g. `docs/index.md`, `docs/guide/`, etc.) — points to the LATEST released version (mirrors `docs/v1.0.0/` after Phase 9). Until v1.0.0 is tagged, the root mirrors `docs/next/`.
- [ ] [S] Create `docs/.vitepress/config.ts` with the following:
  - Title `howl`, description `Multi-driver Laravel notifier`, base `/`.
  - Version dropdown in `nav` showing `latest (v1.0.0)` and `next` (pre-release) — even though only `next` exists today, scaffold the dropdown shape now so adding `v1.0.0` in Phase 9 is a one-line config change.
  - Laravel-docs-style theme tokens (red/burgundy accent matching howl branding).
- [ ] [S] **Pre-build the COMPLETE sidebar skeleton in `config.ts`** with ALL groups and ALL page paths stubbed upfront — Phases 2-5 then only write the content files at the pre-defined paths and do NOT touch `config.ts`. This unlocks parallel content-writing rounds. Sidebar shape (Laravel-docs-style ordering):
  ```ts
  themeConfig: {
    nav: [{ text: 'next', items: [...] }, { text: 'latest', link: '/v1.0.0/' } /* placeholder */],
    sidebar: {
      '/next/': [
        // Top-of-sidebar (Laravel docs convention)
        { text: 'Prologue', items: [
          { text: 'Upgrade Guide', link: '/next/upgrade' },
          { text: 'Release Notes', link: '/next/releases' },
        ]},
        // Then standard groups
        { text: 'Getting Started', items: [
          { text: 'Introduction', link: '/next/guide/' },
          { text: 'Installation', link: '/next/guide/installation' },
          { text: 'Quick Start', link: '/next/guide/quick-start' },
        ]},
        { text: 'Configuration', items: [
          { text: 'Reference', link: '/next/configuration/reference' },
          { text: 'Channel Routing', link: '/next/configuration/channel-routing' },
          { text: 'Failover & Fan-Out', link: '/next/configuration/failover-and-fan-out' },
          { text: 'Rate Limiting', link: '/next/configuration/rate-limiting' },
        ]},
        { text: 'Drivers', items: [
          { text: 'Discord', link: '/next/drivers/discord' },
          { text: 'Slack', link: '/next/drivers/slack' },
          { text: 'Telegram', link: '/next/drivers/telegram' },
        ]},
        { text: 'Events', items: [
          { text: 'HowlEvent Contract', link: '/next/events/contract' },
          { text: 'Built-in Events', link: '/next/events/built-in' },
          { text: 'Custom Events', link: '/next/events/custom' },
        ]},
        { text: 'Testing', items: [
          { text: 'HowlFake', link: '/next/testing/howl-fake' },
          { text: 'Architecture Tests', link: '/next/testing/architecture' },
        ]},
        { text: 'Extension', items: [
          { text: 'Custom Drivers', link: '/next/extension/custom-driver' },
          { text: 'Builder Methods', link: '/next/extension/builder-methods' },
          { text: 'Queue & Rate Limit', link: '/next/extension/queue-and-rate-limit' },
        ]},
        { text: 'Reference', items: [
          { text: 'API Reference', link: '/next/reference/api' },
        ]},
      ],
      // Same shape for '/v1.0.0/' after Phase 9 promotion (copy-paste at promote time).
    },
  }
  ```
  - VitePress is OK rendering sidebar entries that link to pages-not-yet-written during dev (404s on click); content phases will fill them in. This unlocks Phases 2-5 to run in parallel.
- [ ] [H] Create placeholder `docs/index.md` with hero section that links to `/next/guide/` for now (will flip to `/v1.0.0/guide/` in Phase 9). Hero: `hero.name = 'Howl'`, `hero.text = 'Multi-driver Laravel notifier'`, `hero.actions = [{theme: 'brand', text: 'Get Started', link: '/next/guide/'}]`.
- [ ] [H] Extend `.gitignore`: add `node_modules/`, `docs/.vitepress/dist/`, `docs/.vitepress/cache/`.
- [ ] [S] Create `.github/workflows/deploy-docs.yml` that on `push` to `main` builds docs via `npm ci && npm run docs:build` and deploys to Cloudflare Pages (use `cloudflare/pages-action@v1` with Pages project name + API token from secrets `CLOUDFLARE_API_TOKEN` + `CLOUDFLARE_ACCOUNT_ID`).
- [ ] [S] Manual Cloudflare side: create Pages project linked to `skaisser/howl` repo OR using `wrangler pages deploy` (skill `/cloudflare-auto-deploy` can drive this); set build command `npm run docs:build`, build output `docs/.vitepress/dist`; add custom domain `howl.skaisser.dev` and configure DNS CNAME at the registrar of `skaisser.dev`.
- [ ] [H] Add `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID` to GitHub repo secrets (manual via GitHub UI or `gh secret set`).
- [ ] [S] Push the branch; verify the workflow runs green and the placeholder index.md is served at `https://howl.skaisser.dev`. Capture the HTTPS cert handshake (LetsEncrypt via Cloudflare) succeeds.

**Verify:** `curl -sI https://howl.skaisser.dev | head -1` returns `HTTP/2 200`; the page title is "Howl" (placeholder); workflow run is green on the PR; the version dropdown is visible in site nav (even with only `next` populated).

### Phase 2: Guide section pages

**Touches:** `docs/next/guide/index.md` (new), `docs/next/guide/installation.md` (new), `docs/next/guide/quick-start.md` (new). NO `config.ts` touch (sidebar skeleton pre-built in Phase 1).

- [ ] [S] `docs/next/guide/index.md` — Introduction: what is howl, the problem it solves (observability howler vs Laravel Notification channel pipeline), why use it, the 3 supported drivers at a glance.
- [ ] [S] `docs/next/guide/installation.md` — install steps:
  - `composer require skaisser/howl`
  - `php artisan vendor:publish --tag=howl-config`
  - `.env` baseline keys (`HOWL_DRIVER`, `HOWL_DEFAULT_CHANNEL`, etc.)
  - Quick sanity check: `php artisan tinker` → `Howl::info('Hello, howl!')` lands on configured driver.
- [ ] [S] `docs/next/guide/quick-start.md` — 5-minute walkthrough:
  - `Howl::error($event)` — direct severity verb
  - `Howl::on('errors')->error($event)` — channel override
  - `Howl::driver('slack')->info('System OK')` — driver override
  - `Howl::error('Title only')` — string title shortcut
  - HowlFake test snippet
- [ ] [S] Verify `npm run docs:dev` renders each page without errors; sidebar links from Phase 1's skeleton resolve correctly.

**Verify:** Local `npm run docs:dev` shows all 3 Guide pages in sidebar and renders content.

### Phase 3: Configuration section pages

**Touches:** `docs/next/configuration/reference.md` (new), `docs/next/configuration/channel-routing.md` (new), `docs/next/configuration/failover-and-fan-out.md` (new), `docs/next/configuration/rate-limiting.md` (new). NO `config.ts` touch.

- [ ] [S] `docs/next/configuration/reference.md` — every key in `config/howl.php` documented in a table (key, env var, type, default, description). Includes new v1 keys: `channel`, `channel_backup`, `channel_mode`, `rate_limiter_key`, all `drivers.slack.*`, all `drivers.telegram.*`.
- [ ] [S] `docs/next/configuration/channel-routing.md` — precedence chain explained with diagram + examples: per-call `Howl::on($c)` > `HowlEvent::channel()` > `config('howl.channel')`.
- [ ] [S] `docs/next/configuration/failover-and-fan-out.md` — failover vs fan_out semantics, when to pick which, rate-limit-consumption caveat for fan_out.
- [ ] [S] `docs/next/configuration/rate-limiting.md` — `rate_limiter_key` opt-in contract, consumer-side `RateLimiter::for('howl-discord', fn () => Limit::perMinute(28))` recipe, Horizon supervisor pairing recommendation.

**Verify:** Local `npm run docs:dev`; all 4 config pages render; sidebar nav from Phase 1's skeleton works.

### Phase 4: Drivers section pages (Discord, Slack, Telegram)

**Touches:** `docs/next/drivers/discord.md` (new), `docs/next/drivers/slack.md` (new), `docs/next/drivers/telegram.md` (new). NO `config.ts` touch.

- [ ] [S] `docs/next/drivers/discord.md` — full Discord driver guide:
  - Setup: create webhook in a Discord channel, paste URL into `HOWL_DISCORD_DEFAULT`.
  - Threads: forum vs text channel threads, `?thread_id=N` routing, env-var map per Howl channel.
  - Per-category webhook URLs (progressive enhancement).
  - Mentions: `<@!userId>`, `<@&roleId>`, `@here`, `@everyone`.
  - Attachments: file size limits (Discord = 25 MB free, 50 MB Nitro tier).
  - Example payloads (Block Kit-style screenshot or rendered embed).
- [ ] [S] `docs/next/drivers/slack.md` — full Slack driver guide:
  - Setup: Slack App creation, `chat:write` + `files:write` scopes, install to workspace, copy bot OAuth token.
  - Channel ID lookup (right-click channel → Copy → Copy link → trailing ID).
  - `HOWL_SLACK_BOT_TOKEN` env + `drivers.slack.channels` map.
  - Block Kit rendering (rich attachment with color sidebar + sections + fields + context footer).
  - Mentions translation: `<!here>`, `<!channel>`, `<!subteam^ID>`, `<@UID>`.
  - Attachments via `files.upload v2` (3-step flow described).
  - Buttons → URL-only Block Kit `actions` block.
- [ ] [S] `docs/next/drivers/telegram.md` — full Telegram driver guide:
  - Setup: create bot via @BotFather, copy token.
  - **Critical setup**: supergroup + Forum mode toggle + bot membership + topic creation + topic ID lookup. Step-by-step screenshots if possible.
  - `HOWL_TELEGRAM_BOT_TOKEN` + `HOWL_TELEGRAM_CHAT_ID` + `drivers.telegram.threads` map.
  - HTML format rendering example.
  - Mentions: only `user` (numeric Telegram user_id) supported; `here`/`everyone`/`role` silently dropped (document this).
  - Attachments: `sendDocument` / `sendPhoto` auto-detection by extension; file size caps (10 MB photos, 50 MB documents for bots).
  - Buttons → `reply_markup.inline_keyboard` URL buttons.
**Verify:** Local `npm run docs:dev`; all 3 driver pages render with code samples + tables; sidebar links from Phase 1's skeleton resolve.

### Phase 5: Events + Testing + Extension + Reference + Upgrade Guide + Release Notes pages

**Touches:** `docs/next/events/*.md`, `docs/next/testing/*.md`, `docs/next/extension/*.md`, `docs/next/reference/api.md`, `docs/next/upgrade.md`, `docs/next/releases.md` (11 new pages). NO `config.ts` touch.

- [ ] [S] **Events section** (3 pages):
  - `docs/events/contract.md` — `HowlEvent` abstract base + 8 contract methods (`severity()`, `title()`, `description()`, `fields()`, `emoji()`, `codeBlocks()`, `footerMeta()`, `channel()`).
  - `docs/events/built-in.md` — catalog of 7 built-in events with example usage: `GenericExceptionEvent`, `GenericInfoEvent`, `AuditEvent`, `DeploymentEvent`, `CronHeartbeatEvent`, `JobRetryExhaustedEvent`, `ManualOperationEvent`.
  - `docs/events/custom.md` — writing custom events, override patterns, builder-state-wins precedence with terminal verbs.
- [ ] [S] **Testing section** (2 pages):
  - `docs/testing/howl-fake.md` — `Howl::fake()`, `assertSent()`, `assertSentOnChannel()`, `assertSentEvent()`, `assertNothingSent()`, `sent()`, AND the new per-driver assertions `assertSentVia()`, `assertSentViaNothing()`, `sentVia()` from P-0007.
  - `docs/testing/architecture.md` — `Pest::arch()` rules from P-0007; how to extend them.
- [ ] [S] **Extension section** (3 pages):
  - `docs/extension/custom-driver.md` — writing a custom driver implementing `Skaisser\Howl\Contracts\Driver`. Use `NullDriver` as the minimal example.
  - `docs/extension/builder-methods.md` — all fluent `PendingNotification` builder methods (`title`, `description`, `field`, `codeBlock`, `mention`, `meta`, `button`, `attach`, `thread`, `username`, `app`, `env`, `at`, `forceSync`, `withFallback`, `severity`, `acceptEvent`, `driver`, `channel`).
  - `docs/extension/queue-and-rate-limit.md` — queue mode (`config('howl.queue')`), `SendHowlJob`, `rate_limiter_key` opt-in, Horizon recipe.
- [ ] [S] **Reference section** (1 page):
  - `docs/reference/api.md` — full API reference: facade `@method` list with one-line descriptions, fluent builder method list with signatures.
- [ ] [S] **Upgrade Guide page** (Laravel docs pattern — `/docs/13.x/upgrade`):
  - `docs/next/upgrade.md` — written FROM the perspective of someone on v0.2.x upgrading to v1.0.0. Lives at the TOP of the sidebar, above Guide. Content:
    - "High Impact Changes" section (Laravel docs convention): hard-cut of `onDiscord/onSlack/onTelegram` methods. Sed codemod (BSD-safe variant): `find app -name "*.php" -exec sed -i '' 's/Howl::onDiscord(/Howl::on(/g' {} +` for macOS, GNU sed variant for Linux.
    - "Medium Impact Changes": new env vars introduced (`HOWL_SLACK_BOT_TOKEN`, `HOWL_SLACK_DEFAULT_CHANNEL`, `HOWL_TELEGRAM_BOT_TOKEN`, `HOWL_TELEGRAM_CHAT_ID`, `HOWL_DEFAULT_CHANNEL`, `HOWL_BACKUP_CHANNEL`, `HOWL_CHANNEL_MODE`, `HOWL_RATE_LIMITER_KEY`); new config keys `drivers.slack.*` + `drivers.telegram.*`.
    - "Low Impact / Additive": severity terminal verbs (`Howl::error/info/...`), `Howl::on()`, `Howl::driver()` builders, channel failover/fan_out modes, rate-limit middleware, HowlFake per-driver assertions, attachments parity on all 3 drivers.
    - "Updating Dependencies" section: `composer require skaisser/howl:^1.0` — single command, replaces `:^0.2`.
    - Step-by-step migration recipe with code samples for `Howl::onSlack('alerts')` → `Howl::driver('slack')->on('alerts')` and the Telegram equivalent.
    - Pest 3/4 compatibility note: single howl release works on Laravel 12 (Pest 3) AND Laravel 13 (Pest 4); composer resolves automatically — no consumer-side test-suite changes required.
- [ ] [S] **Release Notes page** (Laravel docs pattern — `/docs/13.x/releases`):
  - `docs/next/releases.md` — additive feature catalog. Mirrors the `[1.0.0]` CHANGELOG entry but with expanded prose, code samples, and links into the detailed Drivers / Configuration / Testing pages. Lives second in the sidebar, just below Upgrade Guide.
  - Sections: "What's New in v1.0.0", "Slack Driver", "Telegram Driver", "Channel Failover and Fan-Out", "Rate Limiting", "Documentation Site", with each section linking out to the deep page.
**Verify:** Local `npm run docs:dev`; all 11 pages render at the Phase 1 skeleton sidebar slots; Upgrade Guide is the first sidebar entry (Prologue group); Release Notes second; cross-links between Upgrade Guide and Drivers pages work.

### Phase 6: llms.txt + llms-full.txt generation

**Touches:** `llms.txt` (new), `llms-full.txt` (new), `docs/.vitepress/config.ts` (add public route for both files)

- [ ] [S] Write `llms.txt` at repo root following the llmstxt.org spec:
  ```
  # Howl

  > Multi-driver Laravel notifier (Discord, Slack, Telegram) with rich embeds, channel failover, and queue-aware dispatch. PHP 8.3+, Laravel 12/13.

  ## Quick Start
  - [Installation](https://howl.skaisser.dev/guide/installation): composer require, env setup
  - [Quick Start](https://howl.skaisser.dev/guide/quick-start): 5-minute walkthrough

  ## Drivers
  - [Discord](https://howl.skaisser.dev/drivers/discord): webhook + thread routing
  - [Slack](https://howl.skaisser.dev/drivers/slack): Block Kit + bot OAuth
  - [Telegram](https://howl.skaisser.dev/drivers/telegram): HTML + Forum topics

  ## Configuration
  - [Reference](https://howl.skaisser.dev/configuration/reference)
  - [Channel routing](https://howl.skaisser.dev/configuration/channel-routing)
  - [Failover & fan-out](https://howl.skaisser.dev/configuration/failover-and-fan-out)
  - [Rate limiting](https://howl.skaisser.dev/configuration/rate-limiting)

  ## Events, Testing, Extension
  - [Event contract](https://howl.skaisser.dev/events/contract)
  - [Built-in events](https://howl.skaisser.dev/events/built-in)
  - [Custom events](https://howl.skaisser.dev/events/custom)
  - [HowlFake testing](https://howl.skaisser.dev/testing/howl-fake)
  - [Architecture tests](https://howl.skaisser.dev/testing/architecture)
  - [Custom drivers](https://howl.skaisser.dev/extension/custom-driver)
  - [Builder methods](https://howl.skaisser.dev/extension/builder-methods)
  - [Queue & rate limit](https://howl.skaisser.dev/extension/queue-and-rate-limit)

  ## Reference
  - [API reference](https://howl.skaisser.dev/reference/api)
  - [Migration v0.x → v1.0](https://howl.skaisser.dev/migration/v0-x-to-v1)
  ```
- [ ] [S] Write `llms-full.txt` at repo root: same index header, then inline the full content of every page concatenated with `---` separators and `## File: <path>` headers. Generate via a small Node script (`docs/.vitepress/scripts/build-llms-full.mjs`) that reads each `.md` file in `docs/` and concatenates. Add `npm run docs:build:llms` to package.json that runs the script.
- [ ] [S] Wire the `llms.txt` and `llms-full.txt` files to be served at the docs site root:
  - Place them in `docs/public/llms.txt` and `docs/public/llms-full.txt` (VitePress's `public/` directory is copied to dist root as-is).
  - Update `docs/.vitepress/scripts/build-llms-full.mjs` to write to BOTH `<repo-root>/llms-full.txt` AND `docs/public/llms-full.txt` (single source, dual destination).
  - Same for `llms.txt`: maintain at repo root, copy to `docs/public/llms.txt` during build.
- [ ] [S] Verify after build: `curl https://howl.skaisser.dev/llms.txt` returns the index file; `curl https://howl.skaisser.dev/llms-full.txt` returns the inlined content.

**Verify:** `cat llms.txt` shows the index; `wc -l llms-full.txt` returns >1000 lines; both files served at the production URLs after the next docs deploy.

### Phase 7: README rewrite + CHANGELOG v1.0.0 entry + paylog cross-reference update

**Touches:** `README.md` (rewrite), `CHANGELOG.md` (prepend [1.0.0]), `~/Sites/paylog/.planning/P-0222-chore-howl-alert-quality-audit-todo.md` (different repo!)

- [ ] [S] **README.md rewrite** — replace current ~500-line README with a tight 1-page version:
  - Keep the centered `<div align="center">` header with logo emoji, badges, and tagline.
  - Update badges: add a test workflow badge (`https://github.com/skaisser/howl/actions/workflows/test.yml/badge.svg`) and a Codecov badge (`https://codecov.io/gh/skaisser/howl/branch/main/graph/badge.svg`); keep Packagist/PHP/Laravel/Downloads/License.
  - Shorten the "Why Howl?" section to 3-4 bullet points.
  - **Quick Start**: 30-line code example showing install + `Howl::error($event)` + `Howl::on('audits')->info($event)`.
  - Big **prominent** link box pointing to `https://howl.skaisser.dev` for full docs.
  - Remove all inline docs sections (they live on the site now): The Fluent API, Severities & Channels, First-Class Event Templates, Mentions/Buttons/Threads, Bot Integration, Fallback Drivers, Testing, Queue Mode, Configuration, Roadmap.
  - Keep: Contributing (small section), License (one line).
  - Verify final README is < 150 lines.
- [ ] [S] **CHANGELOG.md** — prepend a new `[1.0.0]` entry above the existing `[0.2.1]` entry:
  ```markdown
  ## [1.0.0] — 2026-MM-DD

  First stable public release. Multi-driver, queue-aware, with a full docs site at https://howl.skaisser.dev.

  ### Added

  - **Driver-agnostic API**: `Howl::error/warning/info/audit/deployment/success(HowlEvent|string)` direct severity verbs; `Howl::on(?string)` channel builder; `Howl::driver(string)` per-call driver override (P-0005).
  - **Slack driver** (Block Kit format, bot OAuth + `chat.postMessage`, channel-ID routing, files.upload v2 attachments, mentions translation) (P-0006).
  - **Telegram driver** (HTML parse_mode, Forum topic routing via `message_thread_id`, sendDocument/sendPhoto attachments with extension auto-detection, user-mention translation) (P-0006).
  - **Cross-driver attachment parity** — `PendingNotification::attach($path)` now works on all 3 drivers (P-0006).
  - **Cross-driver mention translation** — abstract `mention()` intent translated to driver-specific syntax (P-0006).
  - **Channel-level failover and fan-out** — `channel_backup` + `channel_mode` config (P-0005).
  - **Opt-in queue rate-limit middleware** — `rate_limiter_key` config + `RateLimitedWithRedis` on `SendHowlJob` (P-0005).
  - **HowlFake per-driver assertions** — `assertSentVia()`, `assertSentViaNothing()`, `sentVia()` (P-0007).
  - **100% line coverage gate** + Pest 3/4 cross-version CI matrix (PHP 8.3/8.4 × Laravel 12/13 = 4 jobs) (P-0007).
  - **Architecture tests** via `Pest::arch()` enforcing event hierarchy, driver contract, no debug calls (P-0007).
  - **VitePress documentation site** at https://howl.skaisser.dev (this plan).
  - **LLM-friendly docs** — `llms.txt` (index) + `llms-full.txt` (inlined) served from repo root and the docs site (this plan).

  ### Removed

  - `Howl::onDiscord()`, `Howl::onSlack()`, `Howl::onTelegram()` facade methods — replaced by `Howl::on(?string)` + `Howl::driver(string)` + direct severity verbs. See the [migration guide](https://howl.skaisser.dev/migration/v0-x-to-v1) for the sed codemod (P-0005).

  ### Documentation

  - Full VitePress docs site at https://howl.skaisser.dev with Guide / Configuration / Drivers / Events / Testing / Extension / Reference sections.
  - Migration guide for v0.x consumers at https://howl.skaisser.dev/migration/v0-x-to-v1.
  - `llms.txt` + `llms-full.txt` for LLM tool discoverability.

  ### Note

  - First stable public release. Pre-1.0 versions (`v0.1.0`, `v0.2.0`, `v0.2.1`) remain on Packagist and GitHub Releases for any pinned consumers but are superseded by v1.0.0.
  ```
- [ ] [S] **Paylog cross-reference update** (different repo!):
  - From within `/Users/skaisser/Sites/howl`, run: `(cd ~/Sites/paylog && grep -rn "howl v0.3.0\|howl 0.3.0" .planning/)` to find references.
  - Switch directories: `cd ~/Sites/paylog` (or open a separate Bash session there).
  - Update the matched lines in `.planning/P-0222-...md` from `v0.3.0` to `v1.0.0`.
  - Commit in the paylog repo: `git add .planning/P-0222*.md && git commit -m "docs: bump howl version reference v0.3.0 → v1.0.0"`.
  - DO NOT push paylog changes from this plan — leave it on a paylog feature branch for user review.
  - Switch back to howl: `cd ~/Sites/howl`.

**Verify:** README < 150 lines; CHANGELOG has `[1.0.0]` entry above `[0.2.1]`; `grep "v0.3.0" ~/Sites/paylog/.planning/` returns zero matches.

### Phase 8: GitHub repo polish — description, topics, homepage, social preview

**Touches:** GitHub repo metadata (via `gh repo edit`), social preview image upload (manual)

- [ ] [H] Update repo description + homepage via `gh`:
  ```bash
  gh repo edit skaisser/howl \
    --description "Beautiful, multi-driver Laravel notifier — Discord, Slack, Telegram. Rich embeds, channel failover, queue-aware. PHP 8.3+, Laravel 12 & 13." \
    --homepage "https://howl.skaisser.dev"
  ```
- [ ] [H] Add GitHub topics via `gh`:
  ```bash
  gh repo edit skaisser/howl \
    --add-topic laravel \
    --add-topic php \
    --add-topic discord \
    --add-topic slack \
    --add-topic telegram \
    --add-topic notifications \
    --add-topic observability \
    --add-topic alerts \
    --add-topic webhooks \
    --add-topic monitoring \
    --add-topic queue \
    --add-topic laravel-package
  ```
- [ ] [H] Update `composer.json` `homepage` field from `https://github.com/skaisser/howl` to `https://howl.skaisser.dev`.
- [ ] [S] Create a 1280×640 PNG social preview banner. Options:
  - Use the `/airbrush` skill to generate via AirBrush API (Howl wolf icon + tagline + Discord/Slack/Telegram logos).
  - Use Figma/Canva manually.
  - Use a simple Tailwind/HTML page rendered to PNG via `puppeteer` or screenshot.
- [ ] [H] Upload the social preview manually via GitHub repo Settings → Options → Social preview (no `gh` API for this as of 2026).
- [ ] [H] Verify on GitHub: repo description matches, homepage links to docs site, topics show, social preview renders when sharing the URL on Twitter/Discord/Slack.

**Verify:** `gh repo view skaisser/howl --json description,homepageUrl,repositoryTopics` returns the updated metadata; opening `https://github.com/skaisser/howl` in incognito shows the social preview banner in the repo header (or in OG card when shared).

### Phase 9: Promote /next/ → /v1.0.0/ + tag v1.0.0 + GitHub Release + post-release verification

**Touches:** `docs/v1.0.0/` (new — copy of `docs/next/`), `docs/.vitepress/config.ts` (add `v1.0.0` to version dropdown), `docs/index.md` (flip hero CTA to `/v1.0.0/guide/`), `llms.txt` + `llms-full.txt` (update to reference `/v1.0.0/` URLs), git tag, GitHub Release (via `gh release create`), this plan

- [ ] [S] **Promote v1.0.0 docs snapshot** (honors the policy in `src/CLAUDE.md`):
  - `cp -R docs/next docs/v1.0.0` to freeze the v1.0.0 docs snapshot.
  - Update `docs/.vitepress/config.ts`: add `/v1.0.0/` to the version dropdown as `latest`, mark `/next/` as `pre-release (next)`.
  - Flip `docs/index.md` hero CTA from `/next/guide/` to `/v1.0.0/guide/`.
  - Regenerate `llms.txt` and `llms-full.txt` so URLs point at `https://howl.skaisser.dev/v1.0.0/...` (the frozen v1.0.0 snapshot) for stable LLM references.
  - Verify `docs/next/` continues to mirror `docs/v1.0.0/` content (no divergence yet); future work happens here.
  - Verify `npm run docs:build` succeeds with both `/v1.0.0/` and `/next/` rendered.
- [ ] [H] Pre-flight: confirm `homolog` is fully green (last CI run from P-0007 passed, no pending PRs); confirm the deploy-docs workflow is active and `howl.skaisser.dev` is serving the latest docs from `homolog`.
- [ ] [H] **Pause point**: explicit user approval gate. The coordinator stops here and asks the user "ready to merge homolog → main and tag v1.0.0?" via `AskUserQuestion`.
- [ ] [S] **Strip `.planning/` from the homolog → main merge** (critical public-repo hygiene step):
  - Check out `main`: `git checkout main && git pull`.
  - Merge `homolog` with a no-commit flag so we can strip `.planning/` before the commit: `git merge --no-commit --no-ff homolog`.
  - Remove planning artifacts from the staged tree: `git rm -r --cached .planning/`.
  - Verify nothing inappropriate is staged: `git diff --cached --stat | grep -E "\.planning/|BOOTSTRAP|decisions|session-handoff"` must return empty.
  - Verify expected release artifacts ARE staged: `docs/v1.0.0/`, `docs/.vitepress/`, `README.md`, `CHANGELOG.md`, `llms.txt`, `llms-full.txt`, `composer.json`, `src/`, `tests/`, `phpunit.xml`, `.github/workflows/`.
  - Commit the atomic release merge: `git commit -m "$(cat <<'EOF'
🚀 release: v1.0.0 — first stable public release

Merges homolog into main with .planning/ stripped per public-repo policy.
Multi-driver Laravel notifier (Discord, Slack, Telegram), 100% line coverage,
Pest 3/4 CI matrix, VitePress docs at howl.skaisser.dev.

Path-to-v1.0.0 sequence: P-0005 (driver-agnostic API) → P-0006 (Slack + Telegram drivers)
→ P-0007 (100% coverage + CI matrix) → P-0008 (this — VitePress docs + release).
EOF
)"`.
  - Push: `git push origin main`.
- [ ] [S] On user approval, tag from `main`:
  ```bash
  git checkout main && git pull
  git tag -a v1.0.0 -m "v1.0.0 — first stable public release of skaisser/howl

  Multi-driver Laravel notifier (Discord, Slack, Telegram) with rich embeds,
  channel failover, queue-aware dispatch, 100% test coverage, and full docs at
  https://howl.skaisser.dev."
  git push origin v1.0.0
  ```
- [ ] [H] Wait for Packagist polling (~5 minutes) then verify:
  ```bash
  composer show skaisser/howl --all | head -5  # from a fresh worktree
  ```
  Should show `1.0.0` as the latest stable.
- [ ] [S] In a throwaway directory, run a smoke install:
  ```bash
  mkdir /tmp/howl-smoke && cd /tmp/howl-smoke
  composer init --name=test/howl-smoke --no-interaction
  composer require skaisser/howl:^1.0
  composer show skaisser/howl  # expect 1.0.0
  ```
- [ ] [S] Create the GitHub Release with the CHANGELOG entry as release notes:
  ```bash
  gh release create v1.0.0 \
    --title "v1.0.0 — first stable public release" \
    --notes-file <(awk '/^## \[1\.0\.0\]/,/^## \[0\.2\.1\]/' CHANGELOG.md | head -n -1) \
    --verify-tag
  ```
- [ ] [H] Verify the GitHub Release renders correctly at `https://github.com/skaisser/howl/releases/tag/v1.0.0` with the full CHANGELOG content.
- [ ] [H] Add a final "v1.0.0 RELEASED" handoff note at the end of this plan stating: "skaisser/howl v1.0.0 tagged and shipped on YYYY-MM-DD. Docs live at https://howl.skaisser.dev. Packagist: https://packagist.org/packages/skaisser/howl. Plans P-0005 → P-0008 form the path-to-v1.0.0 release sequence and are complete."

**Verify:** `git tag -l v1.0.0` shows the tag; `gh release view v1.0.0` shows the release with notes; `composer require skaisser/howl:^1.0` resolves to `1.0.0` from a fresh worktree; `https://howl.skaisser.dev` serves the docs; `https://github.com/skaisser/howl/releases/tag/v1.0.0` shows the release.

## Execution Strategy

> **Approach:** `/plan-approved` with sequential foundation → parallel content writing → sequential release-artifact + GitHub polish → atomic homolog→main merge & tag v1.0.0
> **Total Tasks:** ~50 (H: 23, S: 27, O: 0)
> **Estimated Rounds:** 6 (1 parallel + 5 sequential)
> **Parallel Savings:** 3 rounds saved (P2/P3/P4/P5 content phases collapse into 1 parallel round via Phase 1's pre-built sidebar skeleton)

### File-Touch Matrix

| Phase | Files Touched | Depends On |
|-------|-------------------|------------|
| Phase 1 | `docs/.vitepress/config.ts` (full skeleton), `docs/index.md`, `docs/next/` dir, `package.json`, `.gitignore`, `.github/workflows/deploy-docs.yml`, Cloudflare (external) | — |
| Phase 2 | `docs/next/guide/*.md` | Phase 1 |
| Phase 3 | `docs/next/configuration/*.md` | Phase 1 |
| Phase 4 | `docs/next/drivers/*.md` | Phase 1 |
| Phase 5 | `docs/next/events/*.md`, `docs/next/testing/*.md`, `docs/next/extension/*.md`, `docs/next/reference/*.md`, `docs/next/upgrade.md`, `docs/next/releases.md` | Phase 1 |
| Phase 6 | `llms.txt`, `llms-full.txt`, `docs/public/*`, `docs/.vitepress/scripts/build-llms-full.mjs`, `package.json` (add script) | Phases 2-5 (reads all docs content) |
| Phase 7 | `README.md`, `CHANGELOG.md`, paylog repo file (different cwd) | Phases 2-5 (README links to docs site pages); Phase 6 (CHANGELOG mentions llms files) |
| Phase 8 | GitHub repo metadata (external), social preview (external), `composer.json` (homepage field) | Phase 6 (homepage URL) |
| Phase 9 | `docs/v1.0.0/` (cp -R from next), `docs/.vitepress/config.ts` (version dropdown), `docs/index.md`, `llms.txt`/`llms-full.txt` regen, git ops (strip `.planning/`, merge, tag) | All prior phases |

**Sidebar conflict resolved:** Phase 1's new task pre-builds the COMPLETE sidebar skeleton in `config.ts` upfront. Phases 2-5 only write content files at the pre-defined paths — they do NOT touch `config.ts`. This unlocks parallel content writing without race conditions.

### Round 1: Phase 1 → Single Team (Mode C — foundation + sidebar skeleton)

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 1.1 npm install vitepress + scripts | [H] | bifrost-1 | Initial Node setup |
| 1.2 Create docs/next/ + version directory layout | [H] | bifrost-1 | Per-version dir structure |
| 1.3 Create docs/.vitepress/config.ts (base) | [S] | bifrost-2 | Title, description, nav scaffold, version dropdown |
| 1.4 Pre-build COMPLETE sidebar skeleton with all groups + page paths | [S] | bifrost-2 | Unlocks Round 2 parallelism |
| 1.5 Placeholder docs/index.md with hero | [H] | bifrost-1 | Hero CTA → /next/guide/ |
| 1.6 Extend .gitignore for node_modules + .vitepress/dist | [H] | bifrost-1 | Mechanical |
| 1.7 Create deploy-docs.yml GH Action | [S] | bifrost-2 | CF Pages deployment workflow |
| 1.8 Cloudflare Pages setup (manual, external) | [S] | bifrost-3 | Pages project + custom domain + DNS CNAME |
| 1.9 Add CF secrets to repo | [H] | bifrost-3 | CLOUDFLARE_API_TOKEN + ACCOUNT_ID |
| 1.10 Push + verify deploy works | [S] | bifrost-3 | curl howl.skaisser.dev returns 200 |

### Round 2: Phase 2 + Phase 3 + Phase 4 + Phase 5 → Parallel Teams (Mode B — 4 team-leads dispatched together)

All independent. Each phase writes only its own content directory under `docs/next/`. Zero file overlap thanks to Phase 1's pre-built sidebar skeleton.

| Phase | Mode | Model | Tasks | Notes |
|-------|------|-------|-------|-------|
| Phase 2: Guide section (3 pages) | Team-lead | Sonnet | 2.1-2.4 (4×[S]) | Team `asgard` — Introduction, Installation, Quick Start |
| Phase 3: Configuration section (4 pages) | Team-lead | Sonnet | 3.1-3.4 (4×[S]) | Team `mjolnir` — Reference, Channel routing, Failover, Rate limiting |
| Phase 4: Drivers section (3 pages) | Team-lead | Sonnet | 4.1-4.3 (3×[S]) | Team `valhalla` — Discord, Slack, Telegram each with full setup + format + mentions + attachments |
| Phase 5: Events/Testing/Extension/Reference/Upgrade/Releases (11 pages) | Team-lead | Sonnet | 5.1-5.7 (7×[S]) | Team `ragnarok` — biggest content phase: Upgrade Guide (Laravel-docs style High/Medium/Low impact) + Release Notes + 3 Events pages + 2 Testing pages + 3 Extension pages + 1 Reference page |

### Round 3: Phase 6 → Single Team (Mode C — depends on Round 2)

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 6.1 Write llms.txt (index) | [S] | nexus-1 | llmstxt.org spec — links to all docs pages |
| 6.2 Write build-llms-full.mjs script | [S] | nexus-2 | Node script: concatenates docs/* into llms-full.txt with `## File:` headers |
| 6.3 Run script; wire docs/public/ for serving | [S] | nexus-1 | Dual destination: repo root + docs/public/ |
| 6.4 Verify both files served at howl.skaisser.dev | [S] | nexus-1 | curl checks after CF deploy |

### Round 4: Phase 7 → Single Team (Mode C — depends on Round 3)

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 7.1 README.md rewrite (<150 lines) | [S] | quantum-1 | Tight quick-start + badges + link to howl.skaisser.dev |
| 7.2 CHANGELOG.md prepend [1.0.0] entry | [S] | quantum-2 | Consolidated Added/Removed/Documentation/Note sections |
| 7.3 Paylog cross-reference update (different repo!) | [S] | quantum-3 | cd ~/Sites/paylog, grep + edit + commit (don't push) |

### Round 5: Phase 8 → Single Team (Mode C — has [S] for social preview)

GitHub polish — most tasks are [H] mechanical (gh repo edit + topic add + upload). Social preview generation is [S].

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 8.1 gh repo edit description + homepage | [H] | titan-1 | One CLI call |
| 8.2 gh repo edit add topics (12 topics) | [H] | titan-1 | One CLI call with multiple --add-topic |
| 8.3 composer.json homepage field update | [H] | titan-1 | Single field edit |
| 8.4 Generate social preview banner (1280×640 PNG) | [S] | titan-2 | Via /airbrush skill OR manual Figma/Canva |
| 8.5 Upload preview manually (no gh API) | [H] | titan-2 | Settings → Options → Social preview |
| 8.6 Verify metadata via gh repo view | [H] | titan-1 | description, homepageUrl, topics |

### Round 6: Phase 9 → Single Team (Mode C — sensitive release; HAS PAUSE POINT for user approval)

This round mixes [S] release operations with [H] verification steps. Single team because EVERY task depends on the previous — `cp -R`, then config update, then index update, then llms regen, then user-approval gate, then `git rm -r --cached .planning/`, then merge commit, then push, then tag, then GitHub Release. Strict sequential within the team.

| Task | Model | Worker | Notes |
|------|-------|--------|-------|
| 9.1 Promote /next/ → /v1.0.0/ snapshot | [S] | release-1 | cp -R + config.ts version dropdown + index.md flip + llms regen + npm build verify |
| 9.2 Pre-flight: homolog green | [H] | release-1 | gh pr checks + CF docs deploy check |
| 9.3 **PAUSE POINT** (AskUserQuestion for tag approval) | [H] | release-1 | Explicit user gate |
| 9.4 Strip .planning/ from homolog→main merge | [S] | release-2 | git checkout main + merge --no-commit + git rm -r --cached .planning/ + verify staged tree + atomic commit + push |
| 9.5 Tag v1.0.0 + push tag | [S] | release-2 | git tag -a v1.0.0 + push origin v1.0.0 |
| 9.6 Wait for Packagist polling + verify | [H] | release-3 | composer show skaisser/howl shows 1.0.0 |
| 9.7 Fresh-worktree smoke install | [S] | release-3 | mkdir /tmp + composer init + composer require :^1.0 |
| 9.8 gh release create with CHANGELOG notes | [S] | release-3 | awk-extract [1.0.0] section + gh release create |
| 9.9 Verify GitHub Release renders correctly | [H] | release-3 | Open release URL, confirm content |
| 9.10 Final "v1.0.0 RELEASED" handoff note | [H] | release-3 | Append to plan body |

## Tech Notes

- **VitePress vs alternatives**: VitePress is the user's choice (locked in interview). Built on Vite + Vue 3. Markdown-driven, very fast HMR, easy theming. Laravel-style sidebar nav is straightforward.
- **Cloudflare Pages free tier**: 500 builds/month is more than enough for docs. Build time for VitePress on a small site: ~20 seconds. Static output cached at CF edge.
- **Custom domain DNS**: `howl.skaisser.dev` needs a CNAME at the `skaisser.dev` DNS provider pointing to `<pages-project>.pages.dev`. Cloudflare auto-provisions an SSL cert via Let's Encrypt within ~5 minutes of DNS propagation.
- **llmstxt.org caveat**: the spec is emerging (2024+) and not yet universally adopted. Some LLM tools may not auto-discover it yet, but adoption is growing rapidly (Cloudflare, Anthropic, Stripe ship them). Hedge: also reference `llms.txt` from the README + the site footer for explicit discoverability.
- **`gh release create --notes-file` with awk extraction**: the awk filter extracts lines between `## [1.0.0]` and `## [0.2.1]` then strips the trailing match. Verify the output before piping to `gh` — wrong patterns produce empty notes.
- **Paylog repo edit happens in a DIFFERENT cwd**: explicit task to `cd ~/Sites/paylog` before editing. Don't try to edit paylog files from inside the howl repo (relative paths break).
- **Social preview image dimensions**: GitHub Open Graph spec is 1280×640 PNG, < 1 MB. Anything else either gets cropped or rejected.

## References

- [P-0005 — driver-agnostic API + channel modes + rate-limit middleware](./P-0005-feat-driver-agnostic-api-and-channel-modes-todo.md)
- [P-0006 — Slack + Telegram drivers](./P-0006-feat-slack-telegram-drivers-todo.md)
- [P-0007 — 100% coverage + Pest 3/4 CI matrix + HowlFake per-driver assertions](./P-0007-test-coverage-and-ci-matrix-todo.md)
- VitePress: https://vitepress.dev/
- llmstxt.org: https://llmstxt.org/
- Cloudflare Pages: https://developers.cloudflare.com/pages/
- Laravel docs (style reference): https://github.com/laravel/docs

## Acceptance

- [ ] VitePress site builds locally (`npm run docs:build` exits 0) and serves locally (`npm run docs:dev`).
- [ ] **Versioned-docs structure** honors `src/CLAUDE.md` policy: `docs/v1.0.0/` exists as the frozen v1.0.0 snapshot; `docs/next/` exists for the pre-release / future-version authoring; `docs/.vitepress/config.ts` has a version dropdown showing both.
- [ ] All 24+ docs pages exist and render in **Laravel-docs-style sidebar order**: Upgrade Guide (1st), Release Notes (2nd), then 3 Guide, 4 Configuration, 3 Drivers, 3 Events, 2 Testing, 3 Extension, 1 Reference.
- [ ] **Upgrade Guide** is structured with Laravel's "High Impact / Medium Impact / Low Impact" change classification.
- [ ] **Release Notes** mirrors the Laravel-docs additive-feature-catalog format with section-level links into deep pages.
- [ ] Cloudflare Pages deployment workflow runs on push to main and deploys `docs/.vitepress/dist` to `howl.skaisser.dev`.
- [ ] `https://howl.skaisser.dev` serves the docs (HTTP 200, correct content).
- [ ] `https://howl.skaisser.dev/llms.txt` and `https://howl.skaisser.dev/llms-full.txt` are served (verified with curl).
- [ ] Root-level `llms.txt` and `llms-full.txt` exist in repo.
- [ ] `README.md` is rewritten as a tight (<150 lines) quick-start that links prominently to `howl.skaisser.dev`.
- [ ] `CHANGELOG.md` has a consolidated `[1.0.0]` entry above `[0.2.1]`.
- [ ] `/docs/v1.0.0/upgrade.md` (after Phase 9 promotion) and `/docs/next/upgrade.md` (during Phases 1-8) exist with sed codemod + High/Medium/Low impact change classification + new env var table.
- [ ] `/docs/v1.0.0/releases.md` and `/docs/next/releases.md` exist as additive-feature catalogs linking into deep pages.
- [ ] GitHub repo description, topics, homepage updated to point at docs site.
- [ ] Social preview banner uploaded (1280×640 PNG).
- [ ] `composer.json` `homepage` field updated to `https://howl.skaisser.dev`.
- [ ] Paylog `P-0222` Phase 2.6 cross-reference updated to `v1.0.0` in a separate paylog-side commit (not pushed by this plan).
- [ ] `v1.0.0` git tag created and pushed to origin.
- [ ] GitHub Release at `v1.0.0` exists with CHANGELOG `[1.0.0]` section as release notes.
- [ ] `composer require skaisser/howl:^1.0` from a fresh worktree resolves to `1.0.0` within ~5 minutes of pushing the tag.
- [ ] Pre-1.0 releases (`v0.1.0`, `v0.2.0`, `v0.2.1`) still present on Packagist and GitHub Releases — NOT deleted.
- [ ] Final "v1.0.0 RELEASED" handoff note added to this plan stating completion.
- [ ] `composer.json` `require`/`require-dev` UNCHANGED in this plan (no version bumps to dependencies).
- [ ] No tests modified or removed in this plan; P-0007's 100% coverage gate stays green throughout.
