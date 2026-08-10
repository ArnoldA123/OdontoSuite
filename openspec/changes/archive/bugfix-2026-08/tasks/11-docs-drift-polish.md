# Slice 11 — Docs Drift + Polish

> Findings: AGENTS.md drift + seeders + 39 low-priority polish items
> Cluster: docs-drift
> LOC est: ~390 · Budget risk: High · Depends on: S01, S06
> Spec: [../specs/11-docs-drift-polish.md](../specs/11-docs-drift-polish.md)

## Per-slice forecast

Decision needed before apply: Yes (39 polish items scope confirmation)
Chained PRs recommended: Yes
Chain strategy: stacked-to-main (per-module polish)
400-line budget risk: High (whole slice) — Low per module

## Acceptance Criteria

- `AGENTS.md` matches real stack versions (Laravel 12, Vue 3.3, Tailwind 3.3, Sanctum 4, Reverb 1.6).
- `AGENTS.md §3` documents `pnpm dev` (not npm).
- `AGENTS.md §4` adds `PaymentMethodSeeder` if in `DatabaseSeeder`.
- `AGENTS.md §4` updates listener cableos from "7" to "7 listener classes, 11 cableos".
- `AGENTS.md §6` clarifies SQLite workaround (docker-compose or `@group mysql`).
- `composer.json scripts.dev` verifies `pnpm dev`.
- `ProcedureCatalogController@destroy` documented as soft (deactivate).
- Seeders are idempotent (updateOrCreate on stable key).
- Seeders declare dependencies.
- 39 polish items each scoped to a single module, manifest in `findings-map.md`.
- `PacienteDetail` `format=zip` export verified/removed.
- `ProcedureCatalog` search specialty not sent by FE — documented or removed.
- `ExportPatientFile` `format=zip` verified.
- Visual regression baseline + diff.
- `pnpm i18n:check` gate.

## Tasks

- [x] **T-11.1** `AGENTS.md §4` add `PaymentMethodSeeder` if present in `DatabaseSeeder`. Description: Doc sync. Files: `AGENTS.md`. AC: section lists seeder. Estimated LOC: ~3. Depends on: —. Parallelizable: yes.
- [x] **T-11.2** `AGENTS.md §4` update listener count from "7" to "7 listener classes, 11 cableos". Description: Doc sync. Files: `AGENTS.md`. AC: count matches code. Estimated LOC: ~3. Depends on: S10. Parallelizable: yes.
- [x] **T-11.3** `composer.json scripts.dev` verify `'pnpm dev'` (replace if `npm run dev`). Description: Stack alignment. Files: `composer.json`. AC: `composer dump-autoload` green. Estimated LOC: ~3. Depends on: —. Parallelizable: yes.
- [x] **T-11.4** Document `ProcedureCatalogController@destroy` as soft (deactivate) in `AGENTS.md`. Description: API contract. Files: `AGENTS.md`, possibly inline PHPDoc. AC: doc + test confirms soft. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-11.5** `AGENTS.md §6` fix SQLite suggestion — `docker-compose up mysql` OR `@group mysql` annotation. Description: Dev workflow clarity. Files: `AGENTS.md`. AC: clear instructions. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-11.6** Make every seeder idempotent (updateOrCreate on stable business key). Description: Seed hygiene. Files: `database/seeders/*.php`. AC: `tests/Integration/SeedersTest.php` runs twice + row count unchanged. Estimated LOC: ~60. Depends on: —. Parallelizable: yes (per-seeder).
- [x] **T-11.7** Declare seeder dependencies; refuse to run seeder whose dependency missing. Description: Deterministic order. Files: seeders. AC: `SeedersTest.php` enforces. Estimated LOC: ~40. Depends on: T-11.6. Parallelizable: yes.
- [x] **T-11.8** Seeder output audit trail — log inserts/updates/skips. Description: Observability. Files: seeders. AC: `SeedersLogTest.php` asserts log. Estimated LOC: ~30. Depends on: T-11.6. Parallelizable: yes.
- [x] **T-11.9** 39 polish items — each scoped to its parent module, manifest in `findings-map.md` low-polish subsection. Description: Polish sweep. Files: across components. AC: `findings-map.md` lists each BF/FF/UXF/UXV with module + commit. Estimated LOC: ~150. Depends on: —. Parallelizable: yes (per-module).
- [x] **T-11.10** Visual regression baseline + diff. Description: Snapshots. Files: `tests/visual/__snapshots__/`. AC: `pnpm visual:check` passes. Estimated LOC: ~30. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-11.11** `PacienteDetail` `format=zip` export — verify backend supports OR remove from UI. Description: Polish + UX. Files: page + backend. AC: smoke confirms works or button removed. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-11.12** `ProcedureCatalog` `specialty` search not sent by FE — document or remove from API. Description: Dead param. Files: backend + docs. AC: param removed or FE sends. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-11.13** `ExportPatientFile` `format=zip` verify. Description: Polish. Files: page + backend. AC: works or removed. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-11.14** `pnpm i18n:check` CI gate — fails if template key missing in locale files. Description: i18n integrity. Files: `package.json`, `scripts/build/i18n-check.mjs` (new). AC: missing key fails CI. Estimated LOC: ~25. Depends on: —. Parallelizable: yes.
- [x] **T-11.15** Add `package.json` scripts: `docs:check`, `visual:baseline`, `visual:check`, `i18n:check`, `rbac:check`, `sdd:check-events`. Description: Tooling. Files: `package.json`. AC: scripts listed. Estimated LOC: ~10. Depends on: T-11.10, T-11.14. Parallelizable: yes.
- [x] **T-11.16** `composer.json` autoload `App\Console\Commands\SddCheckMigrations`, `SddCheckEvents`. Description: Tooling. Files: `composer.json`. AC: `composer dump-autoload` green. Estimated LOC: ~5. Depends on: T-05.5, T-10.6. Parallelizable: yes.
- [x] **T-11.17** Various polish findings (BF-019 broadcasting 503, BF-021 singletons redundants, BF-024 estilo inyección, BF-025 closure broadcasting extraer, BF-026 dashboard today vs appointments-today dup, BF-015 broadcasting/auth a `Broadcast::auth`, etc.) — group into separate commits per concern. Description: Polish sweep. Files: per concern. AC: each commit standalone revertible. Estimated LOC: ~80. Depends on: T-11.9. Parallelizable: yes.
- [x] **T-11.18** Write `tests/Integration/SeedersTest.php`, `SeedersLogTest.php`. Description: Strict TDD. Files: tests. AC: Tests fail on `main`; pass after this slice. Estimated LOC: ~80. Depends on: T-11.6..T-11.8. Parallelizable: yes.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| 39 polish items scatter across modules | Per-module commits; manifest in findings-map.md |
| Visual regression baseline shifts baseline | Locked baseline + manual review |
| Seeder idempotency loses seed data | updateOrCreate keyed on stable business key (verified) |
| `composer.json` autoload breaks | `composer dump-autoload` gate |
| High LOC budget (390) — at edge | Split per-module; some sub-slices may need to merge with slice 7 |
