# Specs: full-user-browser-audit-2026-08-05

Parent index for the spec artifacts produced by this change. Each child document is a new capability spec (no MODIFIED deltas — the proposal lists no Modified Capabilities).

## Source evidence

- `OdontoSuite/resources/js/modules/dashboard/DashboardPage.vue` lines 398–406 — `cashBalanceText` interpolates `S/ ${formatCurrency(...)}` while `formatCurrency` already prepends `S/`, producing a double prefix `S/ S/ 759.00` on `/dashboard`.
- `OdontoSuite/resources/js/modules/cash-register/components/SessionList.vue` lines 161, 173, 184 — same pattern across opening amount, closing amount, and difference rows.
- `OdontoSuite/app/Http/Resources/PatientResource.php` — `birth_date` is exposed but no derived `age` field exists. `getPatient().age` is always undefined for the frontend.
- `OdontoSuite/resources/js/components/ui/PatientSelector.vue` line 51 — `{{ patient.age || 'N/A' }} años` renders `N/A años` for every patient (confirmed in screenshots 07 and 10).
- `OdontoSuite/resources/js/modules/patients/PatientsPage.vue` lines 144–158 — patient table has no `Edad` column.
- `OdontoSuite/database/seeders/SpecialtyRecordSeeder.php` — uses `user_id`, `medical_record_id`, `start_date`, `initial_diagnosis`, `treatment_goals`, `next_adjustment_date` for Orthodontics (schema requires `created_by`, `treatment_type`, `treatment_start_date`, `treatment_phase`, `treatment_objectives`); same pattern of mismatches for Endodontics, Rehabilitation, OralSurgery. Schema columns verified against `database/migrations/2025_10_24_20240{5,21,39,56}_create_*_records_table.php` and each model's `$fillable` array.
- `OdontoSuite/database/migrations/2026_08_05_000000_add_audit_log_immutable.php` — already anchored on `user_agent`; `AuditLogMigrationTest` regression-guards the anchor. **No further work required. Explicit non-goal.**
- `OdontoSuite/CREDENTIALS.md` — credentials confirmed working (admin role `AT Admin Test`). **Explicit non-goal — credential documentation is already correct.**

## Non-goals (explicit)

- Audit-log migration anchor change (already shipped and regression-guarded).
- Credential documentation drift (already accurate).
- Payment gateway, auth rework, new modules, accessibility refactor beyond slice 07.
- Migration of existing seeded data; the seeder rewrite is idempotent on fresh seed runs only.

## Children

| Spec | Type | Description |
|------|------|-------------|
| `specs/currency-format-helper/spec.md` | NEW | Single source of truth for PEN rendering, removes duplicate `S/` prefix. |
| `specs/patient-age-accessor/spec.md` | NEW | Backend-derived `age` field on `PatientResource`; `Edad` column on `PatientsPage`; `PatientSelector` fallback removal. |
| `specs/specialty-record-seeder-contract/spec.md` | NEW | Parser-based field-contract test plus seeder re-alignment to `$fillable`/schema. |
| `specs/module-validation-tests/spec.md` | NEW | Tests for the 5 pending modules: catalog filter, BI report, reminder dispatch, specialty records round-trip, cash close + closure-report. |

## Cross-cutting TDD rules

- Test oracle: `php artisan test` (Strict TDD, per `openspec/config.yaml` `strict_tdd: true`).
- All scenarios MUST be executable as PHPUnit `Feature` or `Unit` tests (frontend display correctness is asserted via DOM-rendering snapshots, not via live browser session).
- Every scenario MUST include a happy-path, an empty/partial state, an error state, and a permission check where applicable.
- Rollback invariants: each slice reverts independently; the seeder rewrite is idempotent on fresh seed runs.

## Review budget

- 400 lines per slice (`review_budget_lines=400`).
- Slice plan lives in `proposal.md` §"Slice Plan".

---

## PR5 Bounded Delta (production + test-infrastructure)

Three NEW domain specs added as a bounded delta to this change. The proposal still lists zero `Modified Capabilities`; these are NEW capabilities surfaced by PR5 `apply-progress.md` and constrained to the defects the existing test suite proved real. Permissions, migrations, and existing error envelopes are preserved. Production fixes are split from test-infrastructure fixes.

| Spec | Type | Fix class | Description |
|------|------|-----------|-------------|
| `specs/reminder-schedule-write-contract/spec.md` | NEW | PRODUCTION | Align `ReminderSchedule::$fillable` + `ReminderService::scheduleReminder/createCustomReminder/sendImmediateReminder` to the live `reminder_schedules` schema (write `hours_before`, not `anticipation_hours`/`type`); make scheduling idempotent on `(appointment_id, hours_before)`; preserves 24h/48h/72h cadence. |
| `specs/api-authentication-error-envelope/spec.md` | NEW | PRODUCTION | Register an explicit `Illuminate\Auth\AuthenticationException` render handler BEFORE the generic `\Throwable` handler in `bootstrap/app.php` so Sanctum 401 stops leaking as 500. Preserves 422 / 404 / 403 / 500 envelopes unchanged. |
| `specs/test-fixture-user-uniqueness/spec.md` | NEW | TEST-INFRASTRUCTURE | Add a unique non-null `username` to `UserFactory::definition()` so `User::factory()->create()` succeeds against MySQL strict mode (NOT NULL on `users.username`). Production user creation flow untouched. |

### Non-negotiable invariants for the PR5 delta

- **No new permissions.** Roles (`administrador`, `odontologo`, `recepcionista`, `cajero`) and middleware aliases (`auth:sanctum`, `role:`, `throttle.login`, `cash.session`) MUST stay byte-identical.
- **No new migrations.** `reminder_schedules` columns are already present in the live schema; `users.username` is already present. The PR5 delta aligns CODE to schema, not the other way around.
- **Existing error envelopes preserved.** 422 (`ValidationException`), 404 (`ModelNotFoundException` / `NotFoundHttpException`), 401 (`UnauthorizedHttpException`), 403 (`AccessDeniedHttpException` / generic `HttpException` 403), and 500 (`Throwable`) JSON shapes MUST NOT change. Only the `AuthenticationException` path flips from 500 → 401.
- **Routes and controllers untouched.** The reminder fix is bounded to model + service; the auth fix is bounded to one new render handler in `bootstrap/app.php`; the UserFactory fix is bounded to `database/factories/UserFactory.php` only.

### Scope tags

- `reminder-schedule-write-contract` — PRODUCTION FIX (model + service)
- `api-authentication-error-envelope` — PRODUCTION FIX (bootstrap render order)
- `test-fixture-user-uniqueness` — TEST-INFRASTRUCTURE FIX (factory only; production user creation untouched)
