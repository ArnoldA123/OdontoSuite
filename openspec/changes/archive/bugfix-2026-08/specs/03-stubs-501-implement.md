# Delta for Stub-501 Implementations — Slice 03

Implements the three stub-501 controllers/services that have real downstream consumers: `ReminderController`, `ReminderTemplateController`, and `ReminderProvider` (scheduled job). Replaces the current `notImplemented()` JSON responses with full CRUD handlers backed by `ReminderService` and `ReminderTemplateService`.

## ADDED Requirements

### Requirement: Reminder CRUD Implemented

The system MUST respond to all standard CRUD verbs on `/reminders`:
- `GET /reminders` — paginated list filtered by `patient_id`, `status`, `date_from`, `date_to`
- `POST /reminders` — create new reminder; payload: `{ patient_id, channel, send_at, template_id, message }`
- `GET /reminders/{id}` — return one reminder
- `PUT/PATCH /reminders/{id}` — update reminder
- `DELETE /reminders/{id}` — soft delete reminder

Auth: `role:administrador,recepcionista,odontologo,implantologo,tecnico_dental,asistente`.

Evidence: `ReminderController` currently returns 501 on `index`, `store`, `show`, `update`, `destroy` (`app/Http/Controllers/Api/ReminderController.php:32-55`). Only `send` is implemented. The reception module consumes the listing.

#### Scenario: list by patient

- WHEN `recepcionista` requests `GET /reminders?patient_id=42`
- THEN response is 200 with paginated reminders for patient 42

#### Scenario: create reminder

- WHEN `recepcionista` POSTs `{ patient_id: 42, channel: 'sms', send_at: '2026-08-06 09:00', message: 'Cita mañana' }`
- THEN response is 201 with the created reminder id

#### Scenario: send action delegates to service

- WHEN user POSTs `/reminders/{id}/send`
- THEN `ReminderService::sendReminder` runs and returns success

Test obligation: PHPUnit Feature `tests/Feature/Api/ReminderControllerTest.php`.

---

### Requirement: ReminderTemplate CRUD Implemented

The system MUST respond to all standard CRUD verbs on `/reminder-templates`:
- `GET /reminder-templates` — list templates with `?active=true|false` filter
- `POST /reminder-templates` — create `{ name, channel, subject, body, variables[] }`
- `GET /reminder-templates/{id}` — show template
- `PUT/PATCH /reminder-templates/{id}` — update
- `DELETE /reminder-templates/{id}` — delete

Auth: `role:administrador` (template authoring is admin-only).

Evidence: `ReminderTemplateController` is registered via `apiResource('reminder-templates', ...)` at `routes/api.php:237` but every method returns 501.

#### Scenario: admin lists templates

- WHEN `administrador` requests `GET /reminder-templates?active=true`
- THEN response is 200 with paginated active templates

#### Scenario: admin creates template

- WHEN `administrador` POSTs `{ name, channel, subject, body, variables: ['patient_name','date'] }`
- THEN response is 201 with the template id

Test obligation: PHPUnit Feature `tests/Feature/Api/ReminderTemplateControllerTest.php`.

---

### Requirement: ReminderProvider Scheduled Job Active

The system MUST dispatch `ReminderProvider` as a scheduled command (e.g. hourly) so that `send_at <= now()` reminders are processed. The job MUST be idempotent (no duplicate notifications on re-run within the same minute).

Evidence: Provider class exists but is not wired in `app/Console/Kernel.php`. Cron was relying on a manual `php artisan reminders:process` invocation that nobody runs.

#### Scenario: scheduled dispatch

- WHEN the cron tick fires (`schedule:run`)
- THEN `ReminderProvider::handle()` runs
- AND reminders with `send_at <= now` are pushed to the delivery service

#### Scenario: idempotency

- WHEN the job runs twice within 60 seconds
- THEN each reminder is processed only once (status transitions to `sent`)

Test obligation: PHPUnit Integration test `tests/Integration/ReminderProviderTest.php` using `Bus::fake()` or a clock faker.

---

### Requirement: Reminder Status Transitions

The system MUST transition a reminder through states `pending` -> `queued` -> `sent` (or `failed`). Failed deliveries MUST be visible via `GET /reminders?status=failed`.

Evidence: Model `ReminderSchedule` exists; status enum not enforced.

#### Scenario: state machine

- WHEN a reminder is created
- THEN initial status is `pending`
- WHEN the provider picks it up
- THEN status becomes `queued`
- WHEN the delivery service acknowledges
- THEN status becomes `sent` (or `failed` on delivery error)

Test obligation: Unit test `tests/Unit/ReminderScheduleStateTest.php`.

---

### Requirement: Reminder Channel Validation

The system MUST restrict `channel` to `['sms','email','whatsapp','push']`. Unknown channels MUST return 422.

Evidence: Free-text channel field.

#### Scenario: invalid channel rejected

- WHEN payload has `channel = 'carrier-pigeon'`
- THEN response is 422

Test obligation: Feature.

---

## MODIFIED Requirements

None for this slice (no documented existing behavior to replace; this is a green-field implementation behind the route declarations).

---

## REMOVED Requirements

### Requirement: ReminderController 501 Not Implemented Responses

(Reason: superseded by the new implemented handlers in this slice.)
(Migration: callers receiving 501 must migrate to the new 200/201/204 responses; the `meta.todo` pointer is no longer returned.)

---

### Requirement: ReminderTemplateController 501 Not Implemented Responses

(Reason: superseded.)
(Migration: same as above.)

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| Reminder CRUD Implemented | Feature | `tests/Feature/Api/ReminderControllerTest.php` |
| ReminderTemplate CRUD Implemented | Feature | `tests/Feature/Api/ReminderTemplateControllerTest.php` |
| ReminderProvider Scheduled | Integration | `tests/Integration/ReminderProviderTest.php` |
| Reminder Status Transitions | Unit | `tests/Unit/ReminderScheduleStateTest.php` |
| Reminder Channel Validation | Feature | `tests/Feature/Api/ReminderControllerTest.php` |
| ReminderController 501 REMOVED | Feature | implicit (no 501 in response) |
| ReminderTemplateController 501 REMOVED | Feature | implicit |
