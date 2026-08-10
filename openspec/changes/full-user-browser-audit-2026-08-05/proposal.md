# Proposal: full-user-browser-audit-2026-08-05

## Intent

Fix visible cosmetic defects, silent data-loader failures, and unresolved module validation gaps surfaced by the 2026-08-05 admin-role browser walkthrough (15 PNGs under `.atl/qa-evidence/screenshots/`). Restore trust in currency rendering, patient age display, and specialty-record seeding without altering shipped UX scope.

## Scope

### In Scope
- Remove duplicated `S/` currency prefix in `DashboardPage.vue` (lines 398–406) and `SessionList.vue` (lines 161, 173, 184).
- Expose `age` on `PatientResource` derived from `birth_date`; add `Edad` column to `PatientsPage.vue`; replace `patient.age` fallback in `PatientSelector.vue` (line 51).
- Rewrite `database/seeders/SpecialtyRecordSeeder.php` to match `$fillable` and schema columns for Orthodontics, Endodontics, Rehabilitation, OralSurgery (Implantology already aligned).
- Add `tests/Unit/Seeders/SpecialtyRecordSeederFieldContractTest.php` asserting seeder keys are a subset of each model's `$fillable`.
- Validate the 5 pending modules via Feature/Unit tests: catalog filter, BI report render, reminder dispatch, specialty records round-trip, cash close + closure-report.

### Out of Scope
- Audit-log anchor (already hotfixed by `2026_08_05_000000_add_audit_log_immutable.php`; guarded by `AuditLogMigrationTest`).
- Sidebar overflow UI change (conditional on scroll-test; logged, not built).
- New modules, auth rework, payment gateway changes, accessibility refactor beyond existing slice 07 scope.
- Migration of existing seeded data; seeder rewrite is idempotent on fresh seed runs only.

## Capabilities

### New Capabilities
- `currency-format-helper`: single source of truth for PEN rendering, wraps `Intl.NumberFormat('es-PE','PEN')` and exposes `formatPENLabel`.
- `patient-age-accessor`: backend-derived `age` field on `PatientResource` with frontend helper fallback.
- `specialty-record-seeder-contract`: parser-based test that fails CI when seeder uses keys outside each model's `$fillable`.

### Modified Capabilities
- None — these are bug fixes and test additions, not requirement changes.

## Approach

Five atomic capabilities, each shipped as its own slice to respect the 400-line review budget. Frontend fixes (01, 02) ship first; backend seeder rewrite (03) follows once `SpecialtyRecordSeederFieldContractTest` is in place; module validation (04) lands last behind TDD. Strict TDD with `php artisan test` is the oracle for every slice.

## Affected Areas

| Area | Impact |
|------|--------|
| `resources/js/modules/dashboard/DashboardPage.vue` | Modified — drop `S/` literal |
| `resources/js/modules/cash-register/components/SessionList.vue` | Modified — drop `S/` literal (3 lines) |
| `app/Http/Resources/PatientResource.php` | Modified — add `age` accessor |
| `resources/js/modules/patients/PatientsPage.vue` | Modified — add `Edad` column |
| `resources/js/components/ui/PatientSelector.vue` | Modified — use `age` accessor |
| `database/seeders/SpecialtyRecordSeeder.php` | Modified — align to `$fillable` |
| `tests/Unit/Seeders/SpecialtyRecordSeederFieldContractTest.php` | New |
| 5 module test files under `tests/Feature/Modules/` | New |

## Risks

| Risk | Mitigation |
|------|------------|
| Seeder FK violations on `dental_piece_id` | Seed `dental_pieces` before patients in test setup |
| Future components re-introduce `S/` literal | `currency-format-helper` centralizes rendering |
| Sidebar overflow claim unverified | Defer UI change; rely on scroll-screenshot evidence |
| 5 modules may already be validated post-bugfix | Confirm via `validation-2026-08-05.md` before allocating capacity |

## Rollback Plan

Revert commits slice by slice. All changes are additive UI/bug fixes plus seeder rewrite. Frontend fixes rollback is a single revert; seeder rewrite is idempotent — restore prior seeder file from git.

## Dependencies

- Existing `bugfix-2026-08` audit evidence baseline (138 findings remediated).
- Existing test infrastructure (`AuditLogMigrationTest` as regression oracle template).
- Confirmed BD credentials (`ever/password123`, `admin_test/password123`) per `CREDENTIALS.md`.

## Success Criteria

- [ ] `DashboardPage.vue` renders `S/ 759.00` (no double prefix) on `/dashboard`.
- [ ] `SessionList.vue` rows render single `S/` prefix.
- [ ] `PatientResource` returns integer `age`; `PatientsPage` shows `Edad` column; `PatientSelector` no longer renders `N/A años`.
- [ ] `php artisan db:seed` completes without `Column not found` on specialty tables.
- [ ] `SpecialtyRecordSeederFieldContractTest` passes.
- [ ] 5 module validation tests pass under `php artisan test`.
- [ ] All slices land under 400 changed lines individually.

## Slice Plan (under 400-line review budget)

| Slice | Capability | Files | Approx LOC | Risk |
|-------|-----------|-------|------------|------|
| 01 | currency-prefix-dedup | 2 Vue files | ~10 | Low |
| 02 | patient-age-accessor | 1 PHP resource + 2 Vue | ~40 | Low |
| 03 | specialty-record-seeder-contract | 1 seeder + 1 test | ~250 | Med |
| 04 | module-validation | 5 test files | ~350 | Med |
| 05 | (conditional) sidebar overflow | 1 Vue | ~30 | Low |
