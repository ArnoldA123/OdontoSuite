# Reminder Schedule Write Contract (NEW — PRODUCTION FIX, PR5 delta)

## Purpose

Fix the production defect surfaced by PR5 `ReminderDispatchTest` (see `apply-progress.md` §"PR5 risks"). `app/Models/ReminderSchedule.php::$fillable` declares `type` and `anticipation_hours`, but the live `reminder_schedules` schema (`database/migrations/2025_09_20_082355_create_reminder_schedules_table.php` plus `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php`) defines `hours_before`, `scheduled_at`, `sent_at`, `status`, `channel`, `error_message`, `appointment_id`, `reminder_template_id` — NOT `type` or `anticipation_hours`. `app/Services/ReminderService.php::scheduleReminder()` writes the two non-existent columns via `ReminderSchedule::create([...])`, so every INSERT fails against MySQL strict mode with `Unknown column 'type' in 'field list'`. The defect blocks the 24h-queue and idempotency scenarios in `specs/module-validation-tests/spec.md`. This is a PRODUCTION FIX; existing migrations, permissions, and HTTP error envelopes MUST be preserved.

## Source evidence

- `app/Models/ReminderSchedule.php` lines 20-30 — `$fillable` lists `type`, `anticipation_hours`.
- `app/Services/ReminderService.php` lines 62-69, 212-219, 234-241 — `ReminderSchedule::create([...])` writes `type` and `anticipation_hours`.
- `database/migrations/2025_09_20_082355_create_reminder_schedules_table.php` — schema has `hours_before` (NOT `anticipation_hours`).
- `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` — adds `channel`, `error_message`.
- `apply-progress.md` PR5 §"Test results summary" — `test_24h_reminder_creates_one_schedule_at_minus_one_hour` and `test_redispatch_does_not_duplicate_reminder` are RED because the model INSERT fails on the missing column.

## Requirements

### Requirement: ReminderSchedule $fillable matches the live schema

`ReminderSchedule::$fillable` MUST list only columns that exist in `reminder_schedules`. The keys MUST include `hours_before`, `scheduled_at`, `sent_at`, `status`, `channel`, `error_message`, `appointment_id`, `reminder_template_id`. The keys MUST NOT include any column the schema does not define. No new migrations are introduced; the slice is bounded to the model + service.

#### Scenario: $fillable aligns with the live schema

- GIVEN the `reminder_schedules` table per the two migrations above
- WHEN `ReminderSchedule::$fillable` is read
- THEN every key in `$fillable` MUST be a column present in the live schema
- AND `hours_before`, `scheduled_at`, `sent_at`, `status`, `channel`, `error_message` MUST all be present in `$fillable`.

#### Scenario: A parser-style contract test guards future drift

- GIVEN a regression test mirroring `AuditLogMigrationTest::migrationUpBody()` (strip comments + string literals, then reflect the live model)
- WHEN `php artisan test --filter=ReminderScheduleFillableContractTest` runs
- THEN it MUST fail if `$fillable` declares any column not present in `Schema::getColumnListing('reminder_schedules')`.

### Requirement: ReminderService writes only real columns

`ReminderService::scheduleReminder()`, `createCustomReminder()`, and `sendImmediateReminder()` MUST invoke `ReminderSchedule::create([...])` with only keys that exist in `$fillable` AND only columns that exist in the schema. The legacy `type` and `anticipation_hours` writes MUST be replaced: the anticipation value MUST be persisted to `hours_before`; the kind label MAY be derived from `ReminderTemplate::type` rather than a separate column.

#### Scenario: 24h reminder persists against MySQL strict mode

- GIVEN an `Appointment` scheduled for `now() + 24h` with an active matching `ReminderTemplate`
- WHEN `ReminderService::scheduleReminders($appointment)` runs against MySQL
- THEN exactly one `reminder_schedules` row MUST be inserted with `hours_before = 24`
- AND `scheduled_at = appointment.scheduled_at - 24h`
- AND `status = 'pending'`
- AND NO `SQLSTATE 42S22` (`Unknown column`) error MUST be raised.

#### Scenario: 48h and 72h reminders persist when threshold met

- GIVEN an `Appointment` scheduled for `now() + 96h`
- WHEN `ReminderService::scheduleReminders($appointment)` runs
- THEN three rows MUST be inserted with `hours_before IN (24, 48, 72)` and matching `scheduled_at` timestamps.

#### Scenario: `sendImmediateReminder` writes zero anticipation correctly

- GIVEN `ReminderService::sendImmediateReminder($appointment)`
- WHEN it runs
- THEN the inserted row MUST have `hours_before = 0` and `scheduled_at = now()`
- AND MUST NOT write `anticipation_hours`.

### Requirement: Scheduling is idempotent on (appointment_id, hours_before)

Re-invoking `scheduleReminders` for the same appointment MUST NOT create duplicate rows for the same `hours_before` value. The slice MUST rely on either an idempotency lookup (existing pending row for `(appointment_id, hours_before)`) OR `updateOrCreate` semantics so that re-runs are a no-op.

#### Scenario: Re-dispatch keeps the row count stable per hours_before

- GIVEN `ReminderService::scheduleReminders($appointment)` has already inserted 3 rows (`hours_before IN (24, 48, 72)`)
- WHEN it runs a second time for the same appointment
- THEN the `reminder_schedules` row count for that `appointment_id` MUST remain 3 (1 per hours_before value)
- AND no `MassAssignmentException`, no duplicate-key violation, no extra row MAY appear.

#### Scenario: Past-time branch is still a no-op

- GIVEN an appointment in the past (`scheduled_at < now()`)
- WHEN `ReminderService::scheduleReminders($appointment)` runs
- THEN zero rows MUST be inserted (early-return semantics preserved).

### Requirement: Existing error envelopes, routes, and permissions preserved

The slice MUST NOT alter routes, role gates (`role:cajero,administrador,odontologo,recepcionista`), middleware, or HTTP error envelopes in `bootstrap/app.php`. The 422 / 500 / 404 / 403 handlers MUST continue to apply unchanged.

#### Scenario: Reminder routes intact

- GIVEN `php artisan route:list --path=api/reminders`
- WHEN the slice is applied
- THEN the route list MUST be identical to pre-slice (same URI, same middleware, same controller).

#### Scenario: No new permissions introduced

- GIVEN the existing permission map for the reminders module
- WHEN the slice is applied
- THEN no new role name, no new gate, and no new policy MUST be added.

## Permissions

- No new permissions. The reminders module continues to use the existing role gates.

## Rollback invariants

- Reverting the slice commit alone MUST restore the prior `$fillable` and the prior `scheduleReminder` write path. The seeder, routes, and `bootstrap/app.php` are NOT touched.
- The slice MUST NOT add a migration to `reminder_schedules`. The defect is fixed by aligning code to the existing schema.
- `ReminderProvider` (the console-scheduled dispatcher) MUST remain unchanged in semantics — its `dispatch()` and `IDEMPOTENCY_WINDOW_SECONDS` behaviour stay identical.
