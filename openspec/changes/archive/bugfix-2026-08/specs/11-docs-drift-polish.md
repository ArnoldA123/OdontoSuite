# Delta for Docs Drift, Seeders, Low-Priority Polish — Slice 11

Resolves docs-drift findings (drift between `AGENTS.md`, `composer.json`, `package.json` and the actual code), seeders polish (idempotency, deterministic order), and the low-severity polish fixes (39 items rolled into this slice per user approval: inline in slice of the parent module). Covers the tail of UXV/UXF/UXT items and the visual-flow remainder.

## ADDED Requirements

### Requirement: AGENTS.md Matches Code Reality

The system MUST keep `AGENTS.md` synchronized with: the real stack versions (Laravel 12, Vue 3.3, Tailwind 3.3, Sanctum 4, Reverb 1.6), the real test commands, and the real folder layout. The system MUST publish a doc-drift check in CI.

Evidence: AGENTS.md linked to a non-existent `tokens.js`; package manager mentions npm but project uses pnpm.

#### Scenario: doc check passes

- WHEN `pnpm docs:check` runs
- THEN every code reference in AGENTS.md resolves

Test obligation: CI gate + manual review.

---

### Requirement: Seeders Are Idempotent

Every seeder MUST be safe to run multiple times without creating duplicates. The system MUST use `updateOrCreate` keyed on a stable business key (e.g. `slug`, `code`) instead of `create`.

#### Scenario: re-run safe

- WHEN `php artisan db:seed --class=RoleSeeder` runs twice
- THEN no duplicate rows exist

Test obligation: Integration test running seeder twice + row count assertion.

---

### Requirement: Seeders Have Deterministic Order

Seeders MUST declare their dependencies explicitly (e.g. via the `DatabaseSeeder::$depends` array or comment header). The system MUST refuse to run a seeder whose dependency is not yet present.

#### Scenario: dependency ordering enforced

- WHEN `php artisan db:seed` runs
- THEN seeders run in dependency order

Test obligation: Integration test on `DatabaseSeeder`.

---

### Requirement: Seeder Output Audit Trail

Each seeder MUST log a summary of inserts/updates/skips so production seeding is observable.

#### Scenario: log emitted

- WHEN `db:seed` runs
- THEN logs include counts per seeder

Test obligation: Log assertion.

---

### Requirement: Low-Priority Polish Bundle (39 items)

The 39 low-priority polish items (tooltips, label copy, alignment, missing aria attributes, hover states, focus rings) MUST be fixed in this slice. Each fix MUST be scoped to a single module to keep diffs reviewable.

Evidence: User decision — low-severity items rolled into the slice that owns the parent module.

#### Scenario: polish manifest present

- WHEN `openspec/changes/bugfix-2026-08/findings-map.md` is read
- THEN the `low-polish` subsection enumerates every one of the 39 items with its owning module

Test obligation: Manual review + visual regression.

---

### Requirement: Visual Regression Baseline

The system MUST run a baseline visual regression check (snapshot or percy/chromatic equivalent) before and after the polish fixes to catch unintended visual drift.

#### Scenario: baseline established

- WHEN `pnpm visual:baseline` runs
- THEN a `tests/visual/__snapshots__` set exists
- AND `pnpm visual:check` after the polish fixes reports zero unintended diffs

Test obligation: Build artifact + manual review.

---

### Requirement: Empty i18n Keys Flagged

The system MUST run a CI step `pnpm i18n:check` that fails if any i18n key referenced from a template is missing from the locale files. The system MUST ship an `es` locale file with all keys present.

#### Scenario: missing key fails CI

- WHEN a template references `t('cash.session.close')` and the locale file has no such key
- THEN the CI step fails

Test obligation: CI gate.

---

## MODIFIED Requirements

### Requirement: package.json Scripts Cover New Gates

`package.json` MUST include `docs:check`, `visual:baseline`, `visual:check`, `i18n:check`, `rbac:check`, and `sdd:check-events` scripts.

(Previously: only lint/format/build/test.)

#### Scenario: scripts present

- WHEN `package.json` is read
- THEN each new gate has a `pnpm` script entry

Test obligation: Static.

---

### Requirement: composer.json Exposes sdd:check-* Commands

`composer.json` MUST autoload `App\Console\Commands\SddCheckMigrations`, `SddCheckEvents`, and similar artisan commands.

#### Scenario: command registered

- WHEN `php artisan list` runs
- THEN `sdd:check-migrations` and `sdd:check-events` appear

Test obligation: Artisan smoke.

---

## REMOVED Requirements

None for this slice.

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| AGENTS.md Matches Reality | CI doc check | `pnpm docs:check` |
| Seeders Idempotent | Integration | `tests/Integration/SeedersTest.php` |
| Seeders Deterministic Order | Integration | `tests/Integration/SeedersTest.php` |
| Seeder Output Audit Trail | Integration | `tests/Integration/SeedersLogTest.php` |
| Low-Priority Polish Bundle | Visual regression | `tests/visual/` |
| Visual Regression Baseline | Snapshot | `tests/visual/__snapshots__` |
| Empty i18n Keys Flagged | CI gate | `pnpm i18n:check` |
| package.json Scripts | Static | review |
| composer.json sdd Commands | Artisan smoke | `tests/Feature/Console/SddCommandsTest.php` |
