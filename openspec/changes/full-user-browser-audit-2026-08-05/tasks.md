# Tasks: full-user-browser-audit-2026-08-05

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~650 across 6 PRs (~10/~40/~80/~170/~140/~210) |
| 400-line budget risk | Low |
| Chained PRs recommended | Yes |
| Suggested split | PR1 currency, PR2 patient age, PR3 seeder RED, PR4 seeder GREEN, PR5 module tests A, PR6 module tests B |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Currency `S/` dedup via helper | PR 1 | `pnpm build && pnpm lint:check` + browser re-screenshot | Manual walkthrough `/dashboard`, `/cash-register` | Revert 2 Vue files; degrades to old `S/ S/` bug |
| 2 | Patient `age` resource field | PR 2 | `php artisan test --filter=PatientResourceAgeTest` | Manual walkthrough `/patients` + selector modal | Revert 1 PHP + 2 Vue; removes `age` field |
| 3a | Seeder field-contract RED test | PR 3 | `php artisan test --filter=SpecialtyRecordSeederFieldContractTest` (must fail) | n/a (RED only) | Revert 1 test file |
| 3b | Seeder rewrite + DentalPieceSeeder | PR 4 | `php artisan migrate:fresh --seed` + contract test green | Real `db:seed` against MySQL/SQLite | Revert 2 seeders + chain update |
| 4a | Catalog + Reminder module tests | PR 5 | `php artisan test --filter='CatalogFilterTest|ReminderDispatchTest'` | Feature tests with `RefreshDatabase` | Revert 2 test files |
| 4b | BI + Specialty + Cash module tests | PR 6 | `php artisan test --filter='BusinessIntelligenceRenderTest|SpecialtyRecordsRoundTripTest|CashCloseAndClosureReportTest'` | Feature tests with `RefreshDatabase` | Revert 3 test files |

## Phase 1: Foundation / Frontend Cosmetic Fixes

- [x] 1.1 RED: add `tests/Unit/Composables/FormatPENLabelTest.php` (PHPUnit + Node loader) asserting positive/zero/negative/null/undefined/string inputs produce single `S/` prefix; RED via assertion that the helper file is missing
- [x] 1.2 GREEN: add `formatPENLabel(amount)` to `resources/js/composables/useFormatters.js` wrapping `Intl.NumberFormat('es-PE','PEN', minimumFractionDigits:2)`; non-numeric inputs collapse to 0
- [x] 1.3 GREEN: edit `resources/js/modules/dashboard/DashboardPage.vue` cashBalanceText to call `formatPENLabel`; drop literal `S/` prefix; remove the local `formatCurrency` helper
- [x] 1.4 GREEN: edit `resources/js/modules/cash-register/components/SessionList.vue` lines 161, 173, 184 to call `formatPENLabel`; drop literal `S/` prefixes; remove the now-dead local `formatCurrency` alias
- [x] 1.5 REFACTOR: confirm `formatPENLabel` is the only `Intl.NumberFormat(...,currency:'PEN')` call site in the two slice-owned files (DashboardPage.vue + SessionList.vue); other modules out of scope for PR1
- [x] 1.6 Browser acceptance: re-screenshot `/dashboard` and `/cash-register` via agent-browser; accessibility tree confirms `Saldo: S/759.00` (single prefix) and `S/50.00` cell labels

## Phase 2: Patient Age Accessor

- [x] 2.1 RED: create `tests/Feature/Api/PatientResourceAgeTest.php` covering adult/day-of-birth/null/timezone scenarios; run against current `PatientResource` and expect missing `age` key
- [x] 2.2 GREEN: append `'age' => $this->birth_date?->diffInYears(now())` to `app/Http/Resources/PatientResource.php::toArray()`
- [x] 2.3 GREEN: add `<th>Edad</th>` and `<td>{{ patient.age ?? '—' }}</td>` in `resources/js/modules/patients/PatientsPage.vue` between Fecha de Nacimiento and Estado; mirror in mobile card view
- [x] 2.4 VERIFY: `resources/js/components/ui/PatientSelector.vue` line 51 already reads `patient.age || 'N/A'`; no code change beyond browser confirm
- [x] 2.5 Browser acceptance: re-screenshot `/patients`; assert no `N/A años` on patient with `birth_date` and visible `Edad` column

### Phase 2b: PR2 Bounded Follow-up — Wire PatientResource into the controller (verify #337 CRITICAL)

> Verify report #337 marked PR2 as FAIL because the API list / show / store / update
> endpoints returned raw Eloquent models and never invoked `PatientResource`, so
> `age` never reached the JSON contract. Bounded follow-up: rewire the controller
> while preserving pagination + existing JSON envelope + permissions. Under 400 LOC.

- [x] 2b.1 RED: create `tests/Feature/Api/PatientControllerAgeTest.php` exercising the real controller via Sanctum-actor; assert `age` is an integer in `GET /api/patients` data[0], `age` is an integer in `GET /api/patients/{id}` data, and `age: null` for a patient with `birth_date = null`. Test MUST fail against the current controller (raw model returns)
- [x] 2b.2 RED: confirm `php artisan test --filter=PatientControllerAgeTest` exits non-zero and reports missing `age` key
- [x] 2b.3 GREEN: update `PatientController::index()` to wrap `$patients->items()` in `PatientResource::collection(...)` while preserving `meta.{current_page,last_page,per_page,total,active_count,inactive_count}` pagination shape
- [x] 2b.4 GREEN: update `PatientController::show()` to return `PatientResource::make($patient)` instead of the raw model
- [x] 2b.5 GREEN: update `PatientController::store()` to wrap the created patient in `PatientResource` inside the 201 envelope
- [x] 2b.6 GREEN: update `PatientController::update()` to wrap the refreshed patient in `PatientResource` inside the 200 envelope
- [x] 2b.7 VERIFY: re-run `php artisan test --filter=PatientControllerAgeTest` and `PatientResourceAgeTest`; both suites green
- [x] 2b.8 Browser acceptance: re-walk `/patients` and PatientSelector via agent-browser; assert visible integer age (e.g. `36 años`) on at least one row, NOT `—` or `N/A años`
- [x] 2b.9 VERIFY: full backend suite green for the touched layers (`php artisan test --filter='PatientController|PatientResource|PatientResourceAgeTest|PatientControllerAgeTest'`)

## Phase 3: Specialty Record Seeder Rewrite (RED then GREEN, separate PRs)

- [x] 3a.1 RED: create `tests/Unit/Seeders/SpecialtyRecordSeederFieldContractTest.php` mirroring `AuditLogMigrationTest::migrationUpBody()` strip pattern; assert current seeder keys ⊆ each `$fillable`; assert legacy-key deny list (`user_id`, `medical_record_id`, `diagnosis`, etc.); test MUST fail on current seeder
- [x] 3a.2 VERIFY: `php artisan test --filter=SpecialtyRecordSeederFieldContractTest` exits non-zero; capture failing message naming offender
- [ ] 3b.1 GREEN: rewrite `OrthodonticsRecord::create([...])` block using only `$fillable` keys (`created_by`, `treatment_type`, `treatment_start_date`, `treatment_phase`, `treatment_objectives`) with enum-safe phase values
- [ ] 3b.2 GREEN: rewrite `EndodonticsRecord::create([...])` block (`created_by`, `tooth_number`, `canal_count`, `pulp_diagnosis`, `treatment_status`, optional `dental_piece_id`)
- [ ] 3b.3 GREEN: rewrite `RehabilitationRecord::create([...])` block (`created_by`, `prosthesis_type`, `material_type`, `laboratory_name`, `impression_date`, `delivery_date`, `cementation_date`, `shade_selection`)
- [ ] 3b.4 GREEN: rewrite `OralSurgeryRecord::create([...])` block (`created_by`, `procedure_type`, `surgery_site`, `surgical_technique`, `surgery_start_time`, `surgery_end_time`, `surgery_duration_minutes`)
- [ ] 3b.5 GREEN: confirm `ImplantologyRecord::create([...])` already aligned; no change
- [ ] 3b.6 GREEN: add `database/seeders/DentalPieceSeeder.php` (32 FDI-notated rows) if no real seeder exists outside `_legacy/`; update `database/seeders/DatabaseSeeder.php` to call it before `SpecialtyRecordSeeder`
- [ ] 3b.7 VERIFY: `php artisan migrate:fresh --seed` completes with exit 0; no SQLSTATE 42S22; field-contract test passes

## Phase 4: Module Validation Tests (split across 2 PRs)

- [x] 4a.1 RED then GREEN: create `tests/Feature/Modules/CatalogFilterTest.php` covering category filter, empty filter, unknown category, 401 unauthenticated
- [x] 4a.2 RED then GREEN: create `tests/Feature/Modules/ReminderDispatchTest.php` covering 24h-queue, past-noop, idempotency, missing-appointment exception
- [x] 4b.1 RED then GREEN: create `tests/Feature/Modules/BusinessIntelligenceRenderTest.php` covering required-sections, empty-dataset, 401 envelope (3 tests, GREEN under MySQL harness)
- [x] 4b.2 RED then GREEN: create `tests/Feature/Modules/SpecialtyRecordsRoundTripTest.php` covering POST+GET round-trip on ImplantologyRecord, 422 invalid payload, 403 unauthorized, 401 unauthenticated (4 tests, GREEN under MySQL harness)
- [x] 4b.3 RED then GREEN: create `tests/Feature/Modules/CashCloseAndClosureReportTest.php` covering close-200, PDF generation, 409 no-open-session (actual surface 422 via service ValidationException), 422 wrong-amount, 422 missing-session-id, 403 role, 401 unauthenticated (7 tests, GREEN under MySQL harness)

## Phase 4c: Slice 07a — Reminder Schedule Write Contract (PR5 Bounded Correction)

> Production fix for the defect exposed by PR5 `ReminderDispatchTest`. Aligns `ReminderSchedule::$fillable` and `ReminderService` writes to the canonical `reminder_schedules` schema. NO new migration. Idempotency on `(appointment_id, hours_before)` via `updateOrCreate`. Under 400 LOC. Does NOT touch `bootstrap/app.php` (slice 07b), `UserFactory` (slice 07c), or unrelated modules.

- [x] 7a.1 RED: create `tests/Unit/Models/ReminderScheduleFillableContractTest.php` asserting the union of migration columns matches `$fillable`, the model contains no phantom `type`/`anticipation_hours` keys, the service writes `hours_before` and never `type`/`anticipation_hours`, and `scopeOfType` is removed
- [x] 7a.2 RED: confirm `php artisan test --filter=ReminderScheduleFillableContractTest` exits non-zero with `tests failed: 10` (one sanity check passes; the 10 contract assertions fail red)
- [x] 7a.3 RED: tighten `ReminderDispatchTest::test_24h_reminder_creates_one_schedule_at_minus_one_hour` to assert `hours_before == 24`; tighten `test_redispatch_does_not_duplicate_reminder` to also assert `hours_before == 24`; confirm both fail RED against the column defect
- [x] 7a.4 GREEN: trim `ReminderSchedule::$fillable` to the canonical columns (drop `type`, `anticipation_hours`; add `hours_before`); update docblock; preserve `STATUS_TRANSITIONS` and remaining scopes
- [x] 7a.5 GREEN: delete `ReminderSchedule::scopeOfType()` (zero callers verified by grep; redundant after `type` removal)
- [x] 7a.6 GREEN: rewrite `ReminderService::scheduleReminder()` to use `updateOrCreate` on `(appointment_id, hours_before)` with `hours_before => $hoursBefore` payload (was `'type' => $type, 'anticipation_hours' => $hoursBefore`)
- [x] 7a.7 GREEN: rewrite `ReminderService::createCustomReminder()` to use `updateOrCreate` on `(appointment_id, hours_before)` with `hours_before => $hoursBefore` payload
- [x] 7a.8 GREEN: rewrite `ReminderService::sendImmediateReminder()` to use `hours_before => 0` payload (preserves the original `create()` semantics; idempotency NOT required per spec)
- [x] 7a.9 GREEN: replace `isPast()` with `lessThan(now()->subSecond())` in `scheduleReminder()` so the microsecond clock drift between appointment creation and the service call does NOT silently swallow the 24h-boundary case
- [x] 7a.10 VERIFY: `vendor/bin/phpunit -c phpunit.mysql.xml --filter='ReminderScheduleFillableContractTest|ReminderDispatchTest'` exits 0 with 15 tests, 58 assertions, all green
- [x] 7a.11 VERIFY: `vendor/bin/phpunit -c phpunit.mysql.xml --filter='CatalogFilterTest|ReminderScheduleFillableContractTest|ReminderDispatchTest'` exits 0 with 19 tests, 84 assertions, all green (no PR5 module test regressed)
- [x] 7a.12 VERIFY: pre-existing `AuditLogMigrationTest::test_migration_source_anchors_on_existing_user_agent_column` failure is unchanged by this slice (confirmed by `git stash` baseline run; pre-existing test bug in the string-stripping logic)
- [x] 7a.13 VERIFY: pre-existing `UserFactory` "Field 'username' doesn't have a default value" breakage that affects `AppointmentTest` / `AppointmentServiceTest` / `CalendarServiceTest` is unchanged (slice 07c; explicitly out of scope here)
- [x] 7a.14 VERIFY: dev API `GET /api/reminders` (no bearer) still returns the pre-existing 500 envelope from the bootstrap `Throwable` catch-all (slice 07b fix; explicitly preserved). My slice does NOT touch the bootstrap, routes, or envelope shapes.

## Phase 4c (cont.) — Slice 07b: AuthenticationException Renderer (PR7b, bounded)

> Production fix for the San-ctum 401 vs 500 defect surfaced by `AuthenticationEnvelopeTest::test_unauthenticated_sanctum_request_returns_canonical_401_envelope`. Registers an explicit `Illuminate\Auth\AuthenticationException` render handler BEFORE the catch-all `Throwable` handler in `bootstrap/app.php`. Returns the canonical 401 JSON envelope (with `WWW-Authenticate: Bearer realm="api"`) for `expectsJson()` or `api/*` requests; returns null for web (HTML) requests so Laravel's default `Authenticate::redirectTo()` keeps redirecting to `route('login')`. Under 400 LOC; does NOT touch `UserFactory`, `ReminderSchedule`, or unrelated modules.

- [x] 7b.1 RED: create `tests/Feature/Api/AuthenticationEnvelopeTest.php` asserting (a) `GET /api/auth/me` (no bearer) → 401 + canonical envelope + `WWW-Authenticate: Bearer realm="api"`, (b) `api/*` with `Accept: text/html` still returns 401 JSON, (c) HTML request on `web` middleware must NOT emit 500 nor 401, (d) the new renderer is registered before the generic `Throwable` renderer
- [x] 7b.2 RED: confirm `vendor/bin/phpunit -c phpunit.mysql.xml --filter=AuthenticationEnvelopeTest` exits non-zero (5/9 fail with `Expected response status code [401] but received 500`); the 4 unchanged-envelope tests pass
- [x] 7b.3 GREEN: register `$exceptions->render(\Illuminate\Auth\AuthenticationException::class, ...)` in `bootstrap/app.php` immediately before the existing `Throwable` renderer; body returns 401 JSON envelope with `WWW-Authenticate` header for `expectsJson() || is('api/*')`; returns null for HTML/web requests (Laravel default redirect preserved)
- [x] 7b.4 GREEN: re-run focused tests; all 9 AuthenticationEnvelopeTest cases pass under MySQL: 9/9, 33 assertions, ~0.7s
- [x] 7b.5 TIGHTEN: re-tighten `CatalogFilterTest::test_unauthenticated_request_is_rejected_with_401` from lenient `assertGreaterThanOrEqual(400)` to exact 401 + canonical envelope + `WWW-Authenticate` header; preserve `assertNull(data)` for no-leak contract
- [x] 7b.6 TIGHTEN: re-tighten `PatientControllerAgeTest::test_index_rejects_unauthenticated_with_401` to assert exact 401 + canonical envelope + `WWW-Authenticate` header
- [x] 7b.7 VERIFY focused: `vendor/bin/phpunit -c phpunit.mysql.xml --filter='AuthenticationEnvelopeTest|test_unauthenticated_request_is_rejected_with_401|test_index_rejects_unauthenticated_with_401|ReminderDispatchTest|ReminderScheduleFillableContractTest'` → 20/20, 81 assertions, OK (no PR5 regression)
- [x] 7b.8 VERIFY runtime: live `php artisan serve --host=127.0.0.1 --port=18000` + `curl -H 'Accept: application/json' http://127.0.0.1:18000/api/auth/me` → `HTTP 401` + body `{"message":"No autenticado.","error":"Unauthenticated."}` + header `WWW-Authenticate: Bearer realm="api"`; with `Authorization: Bearer test-no-real-token` → identical 401 envelope; web `curl -H 'Accept: text/html' http://127.0.0.1:18000/dashboard` → `HTTP 200` (welcome view, no catch-all 500)
- [x] 7b.9 VERIFY: pre-existing `bootstrap/app.php` 500 vs 401 defect is now resolved for Sanctum bearer absence; pre-existing `UserFactory` "Field 'username' doesn't have a default value" defect still affects `AppointmentTest` / `AppointmentServiceTest` / `CalendarServiceTest` (slice 07c; explicitly out of scope here); no route, controller, middleware alias, role, or policy was added/renamed/removed
- [x] 7b.10 VERIFY: pre-existing `AuditLogMigrationTest::test_migration_source_anchors_on_existing_user_agent_column` failure is unchanged by this slice (confirmed by `git stash` baseline; pre-existing test bug in the string-stripping logic)

## Phase 4c (cont.) — Slice 07c: UserFactory Username (PR7c, bounded)

> Test-infrastructure fix for the `Field 'username' doesn't have a default value` defect that blocked every `User::factory()->create()` against MySQL strict mode. `users.username` is `varchar(255) NOT NULL UNIQUE` (migration `2025_09_13_151927`). Only `database/factories/UserFactory.php` is edited; `AuthController`, `UserController`, and `RoleBasedUsersSeeder` stay byte-identical. No migration, no schema change, no permission change. Under 400 LOC; does NOT touch `bootstrap/app.php` (slice 07b) or `ReminderSchedule` (slice 07a).

- [x] 7c.1 RED: create `tests/Unit/Database/UserFactoryContractTest.php` (15 tests) asserting (a) `definition()` emits a non-null, non-blank string `username`; (b) the value fits `varchar(255)` and is non-empty; (c) the value is human-readable ASCII `/^[a-z0-9._-]+$/`; (d) two consecutive `make()` calls do not collide; (e) `create()` persists without integrity violation; (f) two consecutive `create()` calls persist distinct usernames; (g) `count(5)->create()` yields `assertDatabaseCount('users', 5)` + 5 distinct usernames; (h) an explicit duplicate still raises `QueryException`; (i) `unverified()` still emits a username; (j) default role stays `user` and `role` override still wins; (k) `username` override still wins; (l) `User::create(['username' => ...])` fixtures still work; (m) production paths never call `User::factory()`; (n) `RoleBasedUsersSeeder` keeps explicit usernames + `firstOrCreate`; (o) `users.username` is still NOT NULL
- [x] 7c.2 RED: confirm `vendor/bin/phpunit -c phpunit.mysql.xml --filter='UserFactoryContractTest'` exits non-zero — `Tests: 15, Assertions: 18, Errors: 5, Failures: 4`; the 9 contract assertions fail with `SQLSTATE[HY000]: General error: 1364 Field 'username' doesn't have a default value`; the 6 unchanged-behaviour guards pass
- [x] 7c.3 GREEN: add `'username' => fake()->unique()->userName()` to `UserFactory::definition()` (placed after `name`, mirroring the `users` column order) plus a docblock naming the migration and the NOT NULL UNIQUE constraint
- [x] 7c.4 GREEN: re-run focused tests — `Tests: 15, Assertions: 35`, OK, 15/15 green under MySQL
- [x] 7c.5 VERIFY byte-identity: `git hash-object` on `app/Http/Controllers/Api/AuthController.php` (`45df5622…`), `database/seeders/RoleBasedUsersSeeder.php` (`3141c499…`), `app/Http/Controllers/Api/UserController.php` (`faac40ee…`) is identical pre- and post-slice; `git diff --numstat` shows `database/factories/UserFactory.php  6  0` as the only production-side change
- [x] 7c.6 VERIFY regression (Feature): `vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature` — baseline 147 tests / 63 green / 84 red / 154 `username` errors → post-slice 147 tests / 108 green / 39 red / 0 `username` errors. Name-level diff of green sets: **zero regressions**, +45 newly green
- [x] 7c.7 VERIFY regression (Feature/Api): `vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/Api` — baseline 54 green / 83 red (179 assertions) → post-slice 99 green / 38 red (288 assertions); zero green→red transitions
- [x] 7c.8 VERIFY regression (Unit): `vendor/bin/phpunit -c phpunit.mysql.xml tests/Unit` — baseline 210 tests / 185 green / 25 red / 44 `username` errors → post-slice 225 tests / 200 green / 25 red / 0 `username` errors; the same 25 pre-existing red remain; zero regressions
- [x] 7c.9 VERIFY prior slices: `vendor/bin/phpunit -c phpunit.mysql.xml --filter='UserFactoryContractTest|AuthenticationEnvelopeTest|ReminderDispatchTest|ReminderScheduleFillableContractTest|CatalogFilterTest'` → 37/37, 133 assertions, OK. Slices 07a and 07b unaffected
- [x] 7c.10 VERIFY runtime: real `php artisan tinker` bootstrap against MySQL `odontosuite_test` (outside PHPUnit) — `User::factory()->count(5)->create()` → 5 created / 5 distinct / 0 null / maxlen 18 / role `user`; 5 rows read back via `DB::table('users')`; transaction rolled back to 0 rows
- [x] 7c.11 VERIFY runtime (production path): `Artisan::call('db:seed', ['--class' => 'RoleBasedUsersSeeder'])` inside a rolled-back transaction → 15 users seeded, explicit usernames `ever` / `admin_test` / `brenda` all present, 0 null usernames; rollback restores 0 rows
- [x] 7c.12 VERIFY scope: the `username` blocker is gone from `AppointmentTest` / `AppointmentServiceTest` / `CalendarServiceTest` (44 → 0 occurrences). Those suites stay red on two DIFFERENT pre-existing defects previously masked by it — `patients.first_name` has no default (missing/incomplete `PatientFactory`) and `AppointmentService::__construct()` arity in `tests/Unit/Services/AppointmentServiceTest.php:25`. Both are out of scope for slice 07c
- [x] 7c.13 DEVIATION recorded: the spec names `AuthController::register` and `database/seeders/UserSeeder.php`; neither exists in this codebase. The real production user-creation paths are `UserController::store()` (`User::create($validated)`, line 69) and `RoleBasedUsersSeeder` (`User::firstOrCreate`). The byte-identity guard targets the paths that actually exist
- [x] 7c.14 DEVIATION recorded: the spec's scenario 4 names the regression test `UserFactoryUsernameTest`; the design names it `tests/Unit/Database/UserFactoryContractTest.php`. The design name was used (single class, no duplicated coverage); the `--filter='UserFactory'` regex matches either intent

## Phase 5: Cleanup / Out-of-Scope Confirmation

- [ ] 5.1 Confirm `audit_log_immutable` migration anchor untouched (already guarded by `AuditLogMigrationTest`)
- [ ] 5.2 Confirm `CREDENTIALS.md` not modified (already accurate per spec)
- [ ] 5.3 Log sidebar overflow claim to follow-up backlog only; NO code change without reproduction script
- [ ] 5.4 Final `php artisan test` green run across whole suite; capture line counts per slice to confirm 400-budget compliance
