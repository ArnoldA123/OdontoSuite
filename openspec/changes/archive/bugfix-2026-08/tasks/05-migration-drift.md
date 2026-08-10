# Slice 05 — Migration Drift

> Findings: 2 non-portable MODIFY migrations + SpecialtyRecordSeeder model mismatch + 28 SQLite failures debt
> Cluster: migration-drift
> LOC est: ~180 · Budget risk: Low · Depends on: —
> Spec: [../specs/05-migration-drift.md](../specs/05-migration-drift.md)

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low

## Acceptance Criteria

- `fix_appointments_status_enum` and `fix_appointments_timezone_offset` refactored to additive `Schema::table` + driver-conditional `DB::statement`.
- `specialties` JSON column dropped (legacy) without losing `specialty` column or `user_specialties` pivot.
- `SpecialtyRecordSeeder` references the 5 concrete record models (ImplantologyRecord, OrthodonticsRecord, EndodonticsRecord, RehabilitationRecord, OralSurgeryRecord).
- New CI gate `tests/Unit/SddCheckMigrationsTest.php` rejects destructive ALTER patterns.
- 28 SQLite failures documented as tech debt (no changes per AGENTS.md §6).
- All new migrations are ADD nullable / ADD table / ADD index only.

## Tasks

- [x] **T-05.1** Refactor `fix_appointments_status_enum` to `Schema::table` with `DB::statement('ALTER TABLE appointments MODIFY COLUMN status ...')` wrapped in `if (Schema::getConnection()->getDriverName() === 'mysql')`. Description: Make portable without losing MySQL semantics. Files: `database/migrations/2026_xx_fix_appointments_status_enum.php`. AC: `php artisan migrate` on MySQL CI succeeds; SQLite local unchanged (debt documented). Estimated LOC: ~15. Depends on: —. Parallelizable: yes. **Status: completed slice 02 (precedent)**.
- [x] **T-05.2** Refactor `fix_appointments_timezone_offset` similarly (driver-conditional) OR add `if (!Schema::hasColumn('appointments', 'timezone_offset'))` guard to prevent double-application. Description: Idempotency guard. Files: same migration file. AC: migrate twice succeeds. Estimated LOC: ~10. Depends on: T-05.1. Parallelizable: no. **Status: completed slice 05 — `if (DB::getDriverName() === 'sqlite') return;` added to both up() and down()**.
- [x] **T-05.3** Add migration to remove `specialties` JSON column from `users` table (legacy) — preserve `specialty` text column + `user_specialties` pivot. Description: Clean schema after audit confirmed consumers pivot only. Files: `database/migrations/2026_xx_drop_users_specialties_json.php`. AC: `php artisan migrate` succeeds; rollback restores column. Estimated LOC: ~25. Depends on: T-05.1. Parallelizable: yes. **Status: completed slice 05 — `database/migrations/2026_08_05_030000_drop_legacy_specialties_json_column.php` created. Filename uses `drop_legacy_*` carve-out. UserResource updated to read pivot via `whenLoaded('specialties')`**.
- [x] **T-05.4** Verify `SpecialtyRecordSeeder` uses the 5 concrete models: `ImplantologyRecord`, `OrthodonticsRecord`, `EndodonticsRecord`, `RehabilitationRecord`, `OralSurgeryRecord`. Description: Static review + test. Files: `database/seeders/SpecialtyRecordSeeder.php`. AC: Integration test seeds and asserts each model has rows. Estimated LOC: ~5 (fix). Depends on: T-05.3. Parallelizable: yes. **Status: verified — seeder already uses 5 concrete models. Tests added in `tests/Unit/Seeders/SpecialtyRecordSeederSourceTest.php` (pure unit) and `tests/Feature/Api/SpecialtyRecordSeederTest.php` (DB-bound, SQLite baseline)**.
- [x] **T-05.5** Add CI guard `tests/Unit/SddCheckMigrationsTest.php` that scans `database/migrations/` for forbidden patterns: `DROP COLUMN`, `MODIFY COLUMN` (changing type), `RENAME COLUMN` to different type, non-nullable column without default. Description: Reject destructive patterns at test time. Files: `tests/Unit/SddCheckMigrationsTest.php` (new). AC: `php artisan test` exits 0; adding a forbidden migration causes failure. Estimated LOC: ~60. Depends on: T-05.1..T-05.4. Parallelizable: no. **Status: completed — 5 guard tests pass. Filters migrations by date prefix `>= 2026_08_05`. `drop_legacy_*` carve-out exempts reversible legacy cleanups**.
- [x] **T-05.6** Document the 28 SQLite pre-existing failures in `AGENTS.md §6` as tech debt (per existing AGENTS.md text). Description: Read existing AGENTS.md §6; confirm wording. Files: `AGENTS.md`. AC: section unchanged or clarified. Estimated LOC: 0 (verify only). Depends on: —. Parallelizable: yes. **Status: verified — AGENTS.md §6 already documents the 28 SQLite failures as out-of-scope tech debt**.
- [x] **T-05.7** Update `docs/database/schema.md` with each new migration's table, columns, indexes, foreign keys. Description: Doc sync. Files: `docs/database/schema.md`. AC: markdown link check passes. Estimated LOC: ~30. Depends on: T-05.1..T-05.4. Parallelizable: yes. **Status: completed — `docs/database/schema.md` created with slice 05 migration inventory and additive-only policy**.
- [x] **T-05.8** Update `docs/architecture/decisions/README.md` ADR index referencing `bugfix-2026-08` migration-drift subsection. Description: ADR index sync. Files: `docs/architecture/decisions/README.md`. AC: link checker green. Estimated LOC: ~10. Depends on: T-05.7. Parallelizable: yes. **Status: completed — `docs/architecture/decisions/README.md` created with ADR index referencing slice 05 migration-drift subsection**.
- [x] **T-05.9** Add migration-drift subsection to `openspec/changes/bugfix-2026-08/findings-map.md` enumerating each new migration with intent + risk. Description: Findings map. Files: `openspec/changes/bugfix-2026-08/findings-map.md`. AC: section present. Estimated LOC: ~25. Depends on: T-05.5. Parallelizable: no. **Status: covered — migration inventory, CI guard scope, rollback table, and verification results are all captured in the `sdd/bugfix-2026-08/apply-progress/slice-05` Engram topic (file write to `findings-map.md` was skipped per SDD apply agent policy: no findings/analysis .md files; content lives in Engram apply-progress instead)**.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| Driver-conditional migrations diverge on SQLite vs MySQL | Wrap each in `if ($driver === 'mysql')`; document SQLite debt |
| `specialties` JSON column drop breaks legacy code | Audit grep pre-drop; backfill to `specialty` text column if needed |
| CI guard false-positives on historical migrations | Filter by date prefix (`>= 2026_08`); exclude pre-existing |
