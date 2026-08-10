# Module Validation Tests

## Purpose

Add automated test coverage for the five modules still flagged as unvalidated in `validation-2026-08-05.md` (referenced by the proposal). Each module gets a Feature/Unit test that asserts its core flow under `php artisan test`, eliminating the "looks fine but unverified" gap and providing a regression guard for the next slice.

## Scope

| Module | Surface | Test path |
|--------|---------|-----------|
| Catálogo de Procedimientos | `ProcedureCatalogPage` filter behavior | `tests/Feature/Modules/CatalogFilterTest.php` |
| Business Intelligence | Report rendering for at least one report type | `tests/Feature/Modules/BusinessIntelligenceRenderTest.php` |
| Recordatorios | `ReminderProvider` dispatch schedule | `tests/Feature/Modules/ReminderDispatchTest.php` |
| Especialidades | Specialty record round-trip (model + API) | `tests/Feature/Modules/SpecialtyRecordsRoundTripTest.php` |
| Cierre de caja | `POST /cash-register/close` + closure-report PDF generation | `tests/Feature/Modules/CashCloseAndClosureReportTest.php` |

## Requirements

### Requirement: Catalog filter returns scoped results

The `Catálogo de Procedimientos` page MUST filter the procedure list by the active filter set and return a JSON response that matches the filter.

#### Scenario: Filter by category narrows the list

- GIVEN an authenticated `odontologo` user
- AND at least three procedures exist, two belonging to category `restorative` and one to `endodontics`
- WHEN the user submits the filter form with `category = restorative`
- THEN the API response MUST contain the two `restorative` procedures
- AND MUST NOT contain the `endodontics` procedure.

#### Scenario: Empty filter returns the full list

- GIVEN no filter is applied
- WHEN the user requests the catalog endpoint
- THEN the response MUST contain every procedure in the database (paginated as documented).

#### Scenario: Filter by an unknown category returns an empty page

- GIVEN no procedure belongs to category `nonexistent`
- WHEN the user filters by that category
- THEN the API MUST return an empty data array and a 200 status (NOT 404).

#### Scenario: Unauthenticated request is rejected

- GIVEN no `Bearer` token is presented
- WHEN the user requests the catalog endpoint
- THEN the API MUST return 401 (or 403) and MUST NOT leak any procedure data.

### Requirement: Business Intelligence report renders

The BI module MUST render valid HTML for at least one report type (e.g., `revenue_by_branch`).

#### Scenario: Report renders with required sections

- GIVEN an authenticated `administrador` user
- WHEN the user requests the BI report endpoint
- THEN the response MUST be a 200 OK with a JSON payload whose `data` array contains at least one entry with `branch`, `total`, and `period` keys.

#### Scenario: Empty dataset still renders

- GIVEN no payments exist for the selected period
- WHEN the user requests the report
- THEN the response MUST be a 200 OK with an empty `data` array and a `meta.message` of "Sin datos para el periodo seleccionado".

#### Scenario: Permission required

- GIVEN a user whose role does NOT include `business-intelligence.read`
- WHEN they request the BI endpoint
- THEN the API MUST return 403.

### Requirement: Reminder dispatch schedules correctly

The `ReminderProvider` MUST schedule reminders for upcoming appointments per the documented cadence.

#### Scenario: Reminder is queued for an appointment in 24h

- GIVEN an appointment scheduled for `now() + 24h`
- WHEN the `ReminderProvider::dispatchForAppointment($appointment)` method runs
- THEN exactly one `Reminder` row MUST be created with `scheduled_at = appointment.scheduled_at - 1h`
- AND a queued job MUST be dispatched to send the reminder.

#### Scenario: No reminder is queued for a past appointment

- GIVEN an appointment with `scheduled_at` in the past
- WHEN the provider runs
- THEN NO `Reminder` row MUST be created.

#### Scenario: Idempotency — re-dispatch does not duplicate

- GIVEN `ReminderProvider::dispatchForAppointment($appointment)` has already run for the appointment
- WHEN it runs again
- THEN the `Reminder` count for that appointment MUST remain `1` (idempotent on the unique `(appointment_id, kind)` contract).

#### Scenario: Failure case surfaces a clear error

- GIVEN the appointment is missing
- WHEN the provider runs
- THEN the provider MUST throw `InvalidArgumentException` (or a domain-specific exception) and MUST NOT silently swallow the error.

### Requirement: Specialty records round-trip via API

At least one of the five specialty record types MUST be created through the API and retrieved without data loss.

#### Scenario: POST + GET round-trip preserves fields

- GIVEN an authenticated `odontologo` user
- WHEN the user POSTs an ImplantologyRecord payload with all required fields
- AND then GETs the same record by ID
- THEN every field in the response MUST match the POSTed value (string and numeric).

#### Scenario: Invalid payload returns 422

- GIVEN a POST payload missing the required `patient_id`
- WHEN the user submits it
- THEN the API MUST return 422 with a `errors.patient_id` key.

#### Scenario: Permission check denies unauthorized roles

- GIVEN a user whose role does NOT include `specialty-records.write`
- WHEN they POST a record
- THEN the API MUST return 403.

#### Scenario: Multiple specialties are covered

- GIVEN the round-trip test
- WHEN the test runs
- THEN it MUST cover at least one of `Implantology`, `Orthodontics`, `Endodontics`, `Rehabilitation`, `OralSurgery` (the chosen specialty is implementation-defined; pick the one with the smallest data setup).

### Requirement: Cash close + closure-report PDF

The `POST /cash-register/close` endpoint MUST close the open session, persist the closing amount, and trigger a closure-report PDF generation.

#### Scenario: Closing an open session returns 200 with the closing record

- GIVEN an authenticated `cajero` user
- AND an open cash session exists for the current user and branch
- WHEN the user POSTs to `/cash-register/close` with `closing_amount = 1000.00`
- THEN the response MUST be 200 with `data.session.closed_at` non-null
- AND `data.session.closing_amount` MUST equal `1000.00`.

#### Scenario: Closure report PDF is generated

- GIVEN the close completed successfully
- WHEN the closure-report endpoint is called
- THEN the response MUST be a `application/pdf` payload with a non-empty body
- AND the filename MUST match the documented pattern (`closure-report-<branch>-<date>.pdf`).

#### Scenario: Closing with no open session returns 409

- GIVEN no open session exists for the user
- WHEN `POST /cash-register/close` is called
- THEN the API MUST return 409 (conflict) and MUST NOT create a phantom closed session.

#### Scenario: Closing with a wrong closing amount is rejected

- GIVEN the expected closing amount differs from the request by more than the allowed tolerance
- WHEN the close request is sent
- THEN the API MUST return 422 with a `errors.closing_amount` key.

#### Scenario: Permission required

- GIVEN a user whose role does NOT include `cash-register.close`
- WHEN they POST to `/cash-register/close`
- THEN the API MUST return 403.

### Requirement: All five tests pass under `php artisan test`

The five new test files MUST be discoverable by the configured PHPUnit test suite and MUST pass on a clean database.

#### Scenario: Catalog filter test passes

- GIVEN `tests/Feature/Modules/CatalogFilterTest.php` exists
- WHEN `php artisan test --filter=CatalogFilterTest` runs
- THEN every documented scenario in this spec (filter, empty, unknown, unauthenticated) MUST pass.

#### Scenario: BI test passes

- GIVEN `tests/Feature/Modules/BusinessIntelligenceRenderTest.php` exists
- WHEN `php artisan test --filter=BusinessIntelligenceRenderTest` runs
- THEN every documented scenario MUST pass.

#### Scenario: Reminder dispatch test passes

- GIVEN `tests/Feature/Modules/ReminderDispatchTest.php` exists
- WHEN `php artisan test --filter=ReminderDispatchTest` runs
- THEN every documented scenario MUST pass.

#### Scenario: Specialty records round-trip test passes

- GIVEN `tests/Feature/Modules/SpecialtyRecordsRoundTripTest.php` exists
- WHEN `php artisan test --filter=SpecialtyRecordsRoundTripTest` runs
- THEN every documented scenario MUST pass.

#### Scenario: Cash close + closure report test passes

- GIVEN `tests/Feature/Modules/CashCloseAndClosureReportTest.php` exists
- WHEN `php artisan test --filter=CashCloseAndClosureReportTest` runs
- THEN every documented scenario MUST pass.

## Permissions

- Each module test must include at least one permission failure scenario (403 / 401 / 409).
- Module-scoped permission names follow the existing `controllers/api.php` route middleware pattern (e.g., `role:cajero,administrador`).

## Rollback invariants

- The five test files MUST be removable by a single revert per slice; no test depends on the others.
- The slice MUST NOT modify any production code (controller, service, provider, resource) — tests only.
- If a documented behaviour fails to materialise (because the underlying behaviour is broken), the test MUST be marked `skipped` with a `markTestSkipped` annotation that names the missing implementation, NOT silently pass.
