# howl/src — API Change Policy & Versioned Docs Governance

## Rule: any `src/` change that affects the public API MUST include a matching docs update

Triggers a docs update (changes under `docs/next/`):
- New public method, class, or interface
- Removed or renamed public method or class
- Changed parameter signatures or return types
- New config keys (`config/howl.php`)
- New environment variables (`env('HOWL_*', ...)`)
- New behavior on existing public APIs

Does NOT trigger a docs update:
- Private or protected helper renames with no public surface change
- Internal-only refactors (e.g. extracting a private method inside Howl::dispatch())
- Typo fixes in code comments
- Test-only changes

When in doubt, update the docs.

**Breaking change rule**: when a release contains breaking changes (removed public method, changed signature, env-var rename, config-key rename), the Upgrade Guide page (`docs/next/upgrade.md`) MUST be linked from the docs site landing page hero AND from `README.md`. Non-breaking releases get a less-prominent Release Notes link.

**How to apply when editing `src/`**: before submitting any `src/` change:
1. Identify the affected docs page(s) under `docs/next/`.
2. Edit them alongside the code change in the same PR.
3. If no doc page exists yet, create one under the appropriate section.
4. If unsure where the doc lives, ask.
5. If the change is breaking, add an entry to `docs/next/upgrade.md` describing the migration path.
6. Internal-only refactors with no public API impact: note in the PR description: "src/ change does not affect public API — no docs update required."

---

## Versioned docs policy

### Structure
- `/docs/next/` — the working copy for the next unreleased version. All pre-release work happens here.
- `/docs/v{N.M}/` — frozen snapshot for each released version (e.g. `docs/v1.0/`, `docs/v1.1/`, `docs/v2.0/`).
- `/docs/` root — mirrors the `latest` released version (symlink or VitePress redirect).

Pattern matches Laravel's versioned docs (laravel.com/docs/13.x, 12.x, 11.x — each version with full content, version dropdown in nav).

### Recipe: authoring a new version's docs (v1.1.0, v2.0.0, ...)

1. Copy the previous version's docs as the baseline:
   ```
   cp -R docs/v{N-1}/ docs/v{N}/
   # or: cp -R docs/next/ docs/v{N}/ (if next/ is up-to-date)
   ```
2. Update ONLY the pages that changed for the new version — leave unchanged pages identical to the previous snapshot.
3. Author/update the **Upgrade Guide** at `docs/v{N}/upgrade.md` — surfaces breaking changes, migration steps, new env vars, removed APIs. This is the FIRST sidebar entry for every version (Laravel docs pattern).
4. Author/update the **Release Notes** at `docs/v{N}/releases.md` — full additive feature list.
5. Update the VitePress config to add `/v{N}/` to the version dropdown.
6. Reset `/docs/next/` to mirror the just-released version as the next cycle's starting point.
7. Regenerate `/llms.txt` + `/llms-full.txt` to reference the new latest URLs (frozen `/v{N}/` snapshot for stability).

### Docs infrastructure responsibility
VitePress setup + versioning infrastructure is owned by **P-0008** (the release plan). Until P-0008 lands, all pre-v1.0 docs work goes into `docs/next/`. The `src/CLAUDE.md` policy takes effect starting with P-0006 — any new public API added by P-0006 must have a corresponding page or section under `docs/next/`.

---

## Handoff to P-0006

API surface is now driver-agnostic. `Howl::driver('slack')` and `Howl::driver('telegram')` flow through correctly but throw `InvalidArgumentException("Howl: unknown driver 'slack'.")` until P-0006 registers the drivers in `resolveDriver()`.

Note: `src/CLAUDE.md` now governs API-change → docs-update discipline AND versioned docs policy for all subsequent plans. P-0008 must implement the VitePress versioning infrastructure to honor this rule.
