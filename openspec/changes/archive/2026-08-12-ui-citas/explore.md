# Explore — citas (ui-rollout-all-modules-2026-08)

> SDD phase: `sdd-explore`. Citas sub-category of the rollout. Read-only; no proposal, no design, no tasks.
> Source artifacts: global `explore.md` §3.2 row 2 + `proposal.md` §2.1 row 2 + `routes/api.php` (citas group) + `app/Models/Appointment.php` + `app/Services/AppointmentService.php` + `app/Services/CalendarService.php` + `app/Services/ConsultationService.php` + `app/Services/ReminderService.php` + `resources/js/modules/appointments/*` + `resources/js/modules/appointment-types/*` + `resources/js/components/appointments/NewAppointmentModal.vue` + `resources/js/composables/useConsultation.js`.

---

## Scope

The "citas" category covers every interface in OdontoSuite that schedules, displays, modifies, or closes an appointment: the calendar agenda (day / week / month views), the new-appointment modal, the appointment-detail / status-transition flow, the post-check-in consultation wizard (SOAP + plan session + procedure execution), the reminder dispatch surface (templates + scheduled instances + provider runs), the waiting-list, the appointment-type catalog, the recurrence + blocks models, the confirmation-token flow, and the work-schedule / dental-chair supporting cast. IN scope: any patient-facing or staff-facing screen where the primary object is an `Appointment`, `AppointmentType`, `AppointmentBlock`, `AppointmentRecurrence`, `ReminderSchedule`, `ReminderTemplate`, `WaitingList`, `WorkSchedule`, or `ConfirmationToken`. OUT of scope: treatment-plan and quotation pricing rules (those are their own categories even though `ConsultationWizard` advances a plan), the cash side of the appointment → quotation → transaction chain (`BillingController.readyToBill` already lives in the pagos category), patient demographic forms without scheduling, and medical-record content.

## Inventory — Frontend (Vue)

PR0 already added `/calendar` and `/appointment-types` to `AppLayout.canvasRoutes` per the global proposal. Other citas routes listed below were not pinned in PR0 — their view surface still uses the old `bg-systemBackground` and untouched panels.

| Route (URL) | Component file | Purpose | Apple-language status | Touch scope |
| --- | --- | --- | --- | --- |
| `/calendar` | `resources/js/modules/appointments/CalendarPage.vue` (29.7 KB) | Day/Week/Month agenda; FullCalendar hosting slot; "En vivo" WS pill; status legend | `canvasRoutes` pinned but visual still legacy: `bg-success-100 / text-success-700 / bg-error-100` status pills, `border-theme` dividers, raw `bg-green-500 / bg-yellow-500 / bg-red-500` legend dots, `hover-lift` on appointment blocks, `bg-primary-50` today highlight, hardcoded `textColor: '#ffffff'` in `CalendarService::getCalendarData` | large |
| `/appointments/new` | `resources/js/components/appointments/NewAppointmentModal.vue` | New appointment modal (patient / profesional / fecha / duracion / tipo / silla / notas) | Untouched: `bg-black bg-opacity-50` backdrop, `border-theme`, raw `<select>` borders, `focus:ring-primary-500 focus:border-accent` | medium |
| `/dashboard` (modal launch) | `resources/js/modules/dashboard/DashboardPage.vue` | Opens `NewAppointmentModal` via `?openAppointmentModal=true` redirect per `app.js` line 56-58 | Already polished (vertical slice). Only the modal slot it opens is citas surface | inherited |
| `/medical-records` (consultation entry) | `resources/js/modules/medical-records/MedicalRecordsPage.vue` | Doctors may also start a consultation from the medical record view | Module-level scope deferred to clinical cluster PR | inherited |
| `/appointment-types` | `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (21.5 KB) | Admin CRUD of appointment types (name, duration, price, color, requires_confirmation, requires_materials, is_consultation_mode) | `canvasRoutes` pinned but visual still legacy: `bg-success-100 text-success-700` / `bg-error-100 text-error-700` status pills, `border-theme`, raw `<select>` borders, inline `<select>` filter bar | medium |
| `/appointment-types/:id` | `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` | Detail / edit view of one appointment type | Same legacy patterns as the list page | medium |

Modal/modal-adjacent components inside the citas modules:

| Component file | Purpose | Apple-language status | Touch scope |
| --- | --- | --- | --- |
| `resources/js/modules/appointments/ConsultationWizard.vue` | Post check-in wizard: 5 steps (mode / SOAP evolution / procedures / materials / odontogram + attachments + summary) | Untouched: raw `<input>`/`<textarea>`/`<select>` with `border border-theme bg-theme-surface-elevated`, raw checkboxes, hardcoded `text-red-500` required asterisks, raw `<button>` step strip, inline `@click="currentStep = step.id"` step navigation (no transitions), `bg-accent bg-opacity-5` selected state | large |

Composables consumed by citas:

| Composable | Use |
| --- | --- |
| `resources/js/composables/useConsultation.js` | Sole canonical composable for the consultation wizard: loads `consultation-context`, calls `check-in` / `complete`, posts wizard payload to `complete` endpoint |
| `resources/js/composables/useEcho.js` | Real-time WS for `appointments` channel (created / updated / deleted events from `App\Events\Appointment*`) |
| `resources/js/composables/useFormatters.js` | `formatTime`, `formatDateTime`, `formatHour` consumed by `CalendarPage` and `NewAppointmentModal` |

Reused primitives (already tokenised in PR2 of vertical slice, inherited by citas as-is):

| Primitive | Use |
| --- | --- |
| `resources/js/components/ui/Card.vue` (`<UiCard variant="glass">`) | Wrapper on CalendarPage header + controls |
| `resources/js/components/ui/Button.vue` (`<UiButton>`) | New / Volver / view-toggle buttons |
| `resources/js/components/ui/Input.vue`, `Select.vue`, `Modal.vue`, `LoadingSpinner.vue`, `EmptyState.vue` | Form inputs + modal chrome in `NewAppointmentModal` and `AppointmentTypesPage` |

## Inventory — Backend

Controllers (all under `app/Http/Controllers/Api/`):

| File | Role |
| --- | --- |
| `AppointmentController.php` | apiResource `appointments` + `updateStatus`; index supports `start_date` / `end_date` / `user_id` / `status` / `patient_id` / `branch_id` filters; emits `AppointmentCreated` / `AppointmentUpdated` / `AppointmentDeleted` events |
| `AppointmentTypeController.php` | apiResource `appointment-types` + public `appointment-types/active` (used in dropdowns on `NewAppointmentModal` and `AppointmentTypesPage`) |
| `ConsultationController.php` | `context(appointment)`, `checkIn(appointment)`, `complete(appointment)` — backs the `ConsultationWizard`; `complete` returns 201 with billing context that downstream cash surfaces consume |
| `DentalChairController.php` | apiResource `dental-chairs` + public `dental-chairs/active`; used by `NewAppointmentModal` for chair selection |
| `ReminderController.php` | apiResource `reminders` + `POST /reminders/{id}/send`; CRUD of `ReminderSchedule` rows |
| `ReminderTemplateController.php` | apiResource `reminder-templates` (admin only); controls the template catalog the `ReminderService` reads from when scheduling 24h / 48h / 72h reminders |

Services:

| File | Role |
| --- | --- |
| `app/Services/AppointmentService.php` | `createAppointment` + `updateAppointment`; delegates conflict detection to `AppointmentRepository::findConflicts`; emits `AppointmentCreated`; logs `audit_logs`; works-schedule + blocks validation is currently commented out (24/7 staff model) |
| `app/Services/CalendarService.php` | `getDayAppointments` / `getWeekAppointments` / `getMonthAppointments` / `getAppointmentsInRange` / `getCalendarData`; the last one returns the FullCalendar-shaped array consumed by `CalendarPage.vue` (incl. hardcoded `textColor: '#ffffff'`) |
| `app/Services/ConsultationService.php` | `checkIn` / `complete`; enforces modes `consultation` / `execution` / `plan_session`; throws `MissingEvolutionException`, `MissingMaterialsException`, `UnexpectedMaterialsException`, `InvalidConsultationModeException`, `InvalidTreatmentPlanException` |
| `app/Services/ReminderService.php` | `scheduleReminders` (creates 24h / 48h / 72h rows via `updateOrCreate` keyed on `(appointment_id, hours_before)` — idempotent) + `scheduleReminder`; reads `ReminderTemplate` by type |
| `app/Services/CacheService.php` | Cache eviction triggered by `AppointmentController::store` via `ClearDashboardCache::handle()` |

Repositories:

| File | Role |
| --- | --- |
| `app/Repositories/AppointmentRepository.php` | `findConflicts` (3-axis overlap check: between / between / contains — covers user AND chair conflicts in one query); `findByUserAndDateRange`; `findByPatient` |

Form requests: `app/Http/Requests/StoreAppointmentRequest.php`, `UpdateAppointmentRequest.php`.

Resources: `app/Http/Resources/AppointmentResource.php`, `AppointmentCollection.php`.

Jobs / events / listeners / providers:

| File | Role |
| --- | --- |
| `app/Events/AppointmentCreated.php` / `AppointmentUpdated.php` / `AppointmentDeleted.php` / `AppointmentCheckedIn.php` / `AppointmentCompleted.php` | Broadcast over `App\Providers\BroadcastServiceProvider`; consumed by `useEcho` on the `appointments` channel |
| `app/Listeners/LogAppointmentActivity.php` / `LogAppointmentCheckedIn.php` | Audit log listeners |
| `app/Listeners/CreateTransactionOnAppointmentCompleted.php` | Bridge into pagos — fires on `AppointmentCompleted`, creates the pending transaction consumed by `BillingController::readyToBill` |
| `app/Providers/ReminderProvider.php` | Hourly dispatcher (registered in `routes/console.php`); idempotent dispatch of `reminder_schedules` rows; writes to `reminder_provider_runs` |
| `app/Console/Commands/DiagnoseAppointments.php` | Diagnostic Artisan command — out of UI scope |

Models: `app/Models/Appointment.php` (SoftDeletes, financial fields, treatment_plan_id, branch_id, procedure_id, requires_payment flag, idempotency_key), `AppointmentBlock.php`, `AppointmentRecurrence.php`, `AppointmentType.php`, `WorkSchedule.php`, `WaitingList.php`, `ConfirmationToken.php`, `ReminderSchedule.php`, `ReminderTemplate.php`, `ReminderProviderRun.php`.

Routes (per `routes/api.php` lines 134-165, citas group under `role:administrador,recepcionista,odontologo,implantologo,tecnico_dental,asistente`):

- `PATCH /api/appointments/{appointment}/status`
- `apiResource /api/appointments` (index / show / store / update / destroy)
- `apiResource /api/dental-chairs`
- `apiResource /api/appointment-types`
- `apiResource /api/reminder-templates` (admin only)
- `apiResource /api/reminders`, `POST /api/reminders/{id}/send`
- `GET /api/appointments/{appointment}/consultation-context`
- `POST /api/appointments/{appointment}/check-in`
- `POST /api/appointments/{appointment}/complete`

Public read endpoints (no role middleware): `GET /api/appointment-types/active`, `GET /api/dental-chairs/active`.

## Database touchpoints

| File | Touch |
| --- | --- |
| `database/migrations/2025_09_20_082336_create_appointment_types_table.php` | Appointment type catalog |
| `database/migrations/2025_09_20_082338_create_work_schedules_table.php` | Per-user per-day-of-week working hours |
| `database/migrations/2025_09_20_082341_create_appointments_table.php` | Base appointments table — status enum `scheduled\|confirmed\|in_progress\|completed\|cancelled\|no_show\|rescheduled`, indexes on `(user_id,scheduled_at)`, `(dental_chair_id,scheduled_at)`, `(patient_id,scheduled_at)` + unique constraints `unique_user_time_slot` and `unique_chair_time_slot` |
| `database/migrations/2025_09_20_082344_create_appointment_blocks_table.php` | Calendar blocks (vacation / maintenance / training / personal / other) with optional recurrence pattern |
| `database/migrations/2025_09_20_082346_create_waiting_lists_table.php` | Waitlist for overflow demand |
| `database/migrations/2025_09_20_082349_create_appointment_recurrences_table.php` | daily / weekly / monthly recurrence rules with `days_of_week` JSON, `end_date`, `max_occurrences` |
| `database/migrations/2025_09_20_082352_create_reminder_templates_table.php` | Reminder template catalog (24h / 48h / 72h) |
| `database/migrations/2025_09_20_082355_create_reminder_schedules_table.php` | Per-appointment scheduled instances, `(status,scheduled_at)` index |
| `database/migrations/2025_09_20_082357_create_confirmation_tokens_table.php` | Public confirmation token flow (status transitions) |
| `database/migrations/2025_10_14_123001_fix_appointments_status_enum.php` | Status enum migration (alignment with no_show / rescheduled) |
| `database/migrations/2025_10_24_201039_make_reminder_template_id_nullable_in_reminder_schedules_table.php` | Allows ad-hoc reminder rows |
| `database/migrations/2025_10_24_202953_add_financial_fields_to_appointments.php` | total_cost / paid_amount / balance / requires_payment |
| `database/migrations/2026_06_02_173228_fix_appointments_timezone_offset.php` | Timezone correctness fix |
| `database/migrations/2026_06_08_110000_add_appointment_id_to_quotations_and_quotation_id_to_transactions.php` | Cross-category link into pagos (billing) |
| `database/migrations/2026_06_10_100400_add_default_procedure_catalog_id_to_appointment_types_table.php` | Default procedure linkage |
| `database/migrations/2026_06_11_001034_add_soft_deletes_to_appointments_table.php` | SoftDeletes (Appointment model uses `SoftDeletes` trait) |
| `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` | Reminder provider observability |
| `database/migrations/2026_08_05_020001_create_reminder_provider_runs_table.php` | Per-run audit of `ReminderProvider::dispatch` |

Reporting surfaces that render these tables: `ReportController::appointments` (BI module), `DashboardController::today` + `DashboardController::upcoming`, `ReadyToBillPage` (pagos category, but consumes completed appointments), `AppointmentReportService` aggregation.

## Test coverage surface

| File | Coverage |
| --- | --- |
| `tests/Unit/Services/AppointmentServiceTest.php` | `AppointmentService::createAppointment` unit (validation + audit + relationship loading) |
| `tests/Feature/Api/AppointmentValidationTest.php` | StoreAppointmentRequest + UpdateAppointmentRequest validation rules |
| `tests/Feature/Api/AppointmentFillableTest.php` | Mass-assignment / fillable contract |
| `tests/Feature/Api/ReminderCrudTest.php` | Reminder CRUD + send endpoint |
| `tests/Feature/Api/ReminderTemplateCrudTest.php` | Reminder template CRUD (admin) |
| `tests/Feature/Modules/ReminderDispatchTest.php` | ReminderProvider hourly dispatch end-to-end |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | `useConsultation` standard contract (used by `ConsultationWizard`) |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Pins `canvasRoutes` array literal — must include `/calendar` + `/appointment-types`; must remain green |

No dedicated unit test was found for `CalendarService` / `AppointmentRepository::findConflicts` / `ConsultationService::complete`; coverage gap noted but out of scope for visual polish.

## Known gotchas

- **Timezone handling.** `AppointmentService::createAppointment` does `Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'))`; the migration `2026_06_02_173228_fix_appointments_timezone_offset` exists precisely because this was once wrong. UI input is `datetime-local` (no timezone); the server interprets it as `app.timezone`. Any new field that takes a date+time MUST stay consistent with this contract. Visual change MUST NOT introduce a JS-side `toISOString()` on a `datetime-local` value (drops the local TZ offset).
- **Conflict detection.** `AppointmentRepository::findConflicts` is a 3-axis overlap (start-within / end-within / contains) over BOTH `user_id` AND `dental_chair_id` in one query. The unique constraints `unique_user_time_slot` and `unique_chair_time_slot` are a backstop for race conditions; the validator is the primary defence. UI should NEVER claim "no conflict" without round-tripping through this endpoint — the in-page block-count heuristic is stale by definition.
- **Race conditions on concurrent bookings.** Two reception desks can submit identical `(user_id, scheduled_at, ends_at)` and the DB unique constraint will fire on the second commit. `AppointmentService::createAppointment` wraps `Appointment::create` in a `DB::transaction`; the error bubbles as a `QueryException`. Frontend must render a friendly "another desk booked this slot" message, not a 500 toast. Currently the code relies on Laravel default exception rendering — verify during apply that the modal handles the duplicate-key gracefully.
- **Status enum + soft-deletes.** `Appointment` uses `SoftDeletes` AND a 7-value status enum. A "deleted" appointment is conceptually distinct from a "cancelled" one. `findConflicts` filters `status != 'cancelled'` but does NOT filter `deleted_at IS NULL` — confirmed in `AppointmentRepository.php` line 24. Soft-deleted rows are not soft-cancelled; a UX error exists if a soft-deleted appointment is restored — verify the polling path. Visual change MUST keep the status-pill colors aligned with the 7 enum values (5 currently rendered in `CalendarPage.vue` legend: programada / confirmada / en consulta / completada / cancelada; missing: `no_show`, `rescheduled`).
- **Recurrence vs single-edit semantics.** `AppointmentRecurrence` exists but `AppointmentService::updateAppointment` updates the single appointment, not the recurrence series. A "edit recurring" feature does NOT exist. Do not introduce UI that implies it.
- **Reminder dispatch idempotency.** `ReminderService::scheduleReminder` uses `updateOrCreate` keyed on `(appointment_id, hours_before)`; `ReminderProvider` writes per-run audit in `reminder_provider_runs`. The hourly schedule (`routes/console.php` line 14-19) has `withoutOverlapping(5)` — 5-minute lock window. UI must never re-create reminders on appointment update; currently `AppointmentService::updateAppointment` does NOT call `scheduleReminders` again, so this is correct by absence.
- **Confirmation tokens.** `ConfirmationToken` model backs a public-link confirmation flow. The API does not appear in `routes/api.php`; it's used by reminder emails. Any visual change to the appointment card MUST NOT expose the token or its hash to non-admin viewers.
- **WorkSchedule + blocks are dormant.** `AppointmentService` lines 75-89 show that work-schedule and blocks validation are commented out — "profesionales trabajan 24/7". The `work_schedules` and `appointment_blocks` tables still exist and are populated by admin UI; do not break their admin-only CRUD but DO NOT introduce UX that suggests the system enforces them.
- **Accessibility of calendar grid.** The week/month views in `CalendarPage.vue` use plain `<div>` grids with no ARIA role; the day view uses a flat list. Screen readers cannot navigate "Tuesday 9 AM, Tuesday 10 AM" efficiently. The proposed language change is the right moment to add `role="grid"` / `role="gridcell"` and per-cell `aria-label`, but this is OUT of scope for visual polish unless the proposal explicitly includes a11y per OQ#2.
- **CalendarPage `textColor: '#ffffff'` in `CalendarService::getCalendarData` line 101** — hardcoded white text against `appointmentType->color` (which can be any hex). If the type color is yellow/light, text becomes unreadable. Color contrast is an existing defect; the rollout must NOT regress the contract (currently: hardcoded white, accept it as-is) and SHOULD flag for a future a11y slice.
- **`ConsultationWizard.vue` is form-heavy.** ~50 raw `<input>` / `<textarea>` / `<select>` controls in the steps; all use `border border-theme bg-theme-surface-elevated`. Tokenisation will touch every one — line estimate is the highest among citas surfaces.
- **Reverb channel collision risk.** `useEcho` channels `appointments`, `patients`, `treatment-plans`, `quotations`, `medical-records`, `specialty-records`, `procedure-catalog`. The Citas surface listens on `appointments`. UI change must NOT touch `<script>` blocks; any accidental removal of `.listen(...)` or `echo.leave(...)` silently breaks realtime across the calendar.
- **`AppointmentTypesPage.vue` / `AppointmentTypeDetailPage.vue`.** Currently in the admin CRUD tier per global explore. They consume the same `border-theme` / `bg-success-100 text-success-700` patterns and the legacy `<select>` filter bar. Reuses the proposal's "admin CRUD triplet" pattern (PR4 in the global chain).

## Out-of-scope

- Treatment-plan CRUD screens (`/treatment-plans`) even though `ConsultationWizard` advances a plan — that is the clinical-modules PR6 slice
- Quotation / billing screens (`/quotations`, `/cash-register/ready-to-bill`) even though they consume completed appointments — that is the pagos category
- Patient demographic forms (`/patients`, `/patients/:id`) — separate module even though appointment history is rendered on the detail page
- Medical-record content (`/medical-records`, `/specialty-records`) — separate clinical cluster
- `WorkSchedule` admin UI (currently absent from `resources/js/modules/` — admin edits via API only)
- `AppointmentBlock` admin UI (same — API-only CRUD; no frontend surface yet)
- `WaitingList` admin UI (same — model exists, no frontend surface yet)
- The `DiagnoseAppointments` Artisan command (CLI surface, no UI)
- `ReminderProvider` hourly cron wiring (backend, no UI change)
- Dashboard's "today appointments" tile — already polished in vertical slice (PR4); consumed by the new modal only via `?openAppointmentModal=true` redirect
- Two-tone numerals (D12 REVERSIBLE from vertical slice) — stays rejected
- New `WaitingList` / `AppointmentBlock` / `WorkSchedule` admin frontend — flagged for a follow-up change if user signals during proposal review