# Spec: CITAS Category Delta — `ui-rollout-all-modules-2026-08`

> **Delta type**: Category delta spec. Sibling of the global
> `design-language-rollout` and `foundation-primitives` specs and the
> PAGOS category delta. Extends the global rollout with CITAS-specific
> rows that the parent `DLR-MOD-002` (Calendario) and `DLR-MOD-006`
> (Tipos de cita) do not enumerate.
>
> **Naming convention**: archive convention `specs/<domain>/spec.md`.
> Signing key: `CITAS-*` for CITAS-only rows.

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | CITAS (Calendario, ConsultationWizard, NewAppointmentModal, Tipos de cita) |
| Date | 2026-08-12 |
| SDD phase | `spec` (3 of 6) — category slice |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/citas/spec`) |
| Delivery strategy | `auto-chain` (inherited from global; CITAS sub-PRs `pr-citas-01..05`) |
| Review budget | 400 authored lines / PR (re-scoped per user; per-PR isolation MANDATORY) |
| Strict TDD | `true` (forward to apply/verify) |
| Parent spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` |
| Parent proposal | `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/proposal.md` |
| Parent explore | `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/explore.md` |
| CITAS PRs | `pr-citas-01..05` (5 chained PRs — see CITAS proposal §6) |
| Source of 7-value status enum | `database/migrations/2025_09_20_082341_create_appointments_table.php` + `2025_10_14_123001_fix_appointments_status_enum.php` |

### Relationship to parent spec

This spec does NOT modify the global `design-language-rollout/spec.md`
rows. It is a sibling that adds CITAS-specific delta rows. The
`DLR-CORE-*` and `DLR-MOD-*` rules apply to CITAS unmodified; the
rows below add category-specific edges (7-value status legend, wizard
primitive adoption, modal chrome, admin CRUD triplet prices, timezone
contract preservation, conflict-detection round-trip, Echo channel
reuse, dormant WorkSchedule/AppointmentBlock validations, per-PR
budget isolation, existing contract preservation, and calendar grid
a11y follow-up).

---

## 1. Purpose

This spec covers the CITAS interfaces of OdontoSuite only: the calendar
agenda (`/calendar`), the consultation wizard (`ConsultationWizard.vue`),
the new-appointment modal (`NewAppointmentModal.vue`), and the
appointment-type catalog (`/appointment-types`, `/appointment-types/:id`).
It extends the global `design-language-rollout` spec with CITAS-specific
deltas: complete 7-value status legend (adds `no_show` + `rescheduled`
to the current 5), ConsultationWizard primitive adoption (`<UiTabs>` +
`<UiInput>` + `<UiSelect>` + `<UiStatusBadge>`), `<UiModal>` chrome on
`NewAppointmentModal`, admin CRUD triplet price field via canonical
`formatCurrency`, timezone contract preservation (no JS-side
`toISOString()` on `datetime-local`), 3-axis conflict-detection
round-trip requirement, mandatory reuse of the existing `appointments`
Echo channel, prohibition of UX that implies WorkSchedule/AppointmentBlock
enforcement, per-PR 400-line review budget isolation, and byte-for-byte
preservation of the `useConsultation` composable contract.

---

## 2. ADDED Requirements

### Requirement: `CITAS-CAL-001` — Calendar status legend MUST render all 7 enum values

The system MUST render all 7 status enum values in the `CalendarPage.vue`
status legend: `scheduled`, `confirmed`, `in_progress`, `completed`,
`cancelled`, `no_show`, `rescheduled`. Each value MUST consume
`<UiStatusBadge variant="...">` with a token-aligned variant
(`success | info | warning | neutral | error | neutral | warning`).
The current 5-value legend is the load-bearing bug.

#### Scenario: `CITAS-CAL-001-1` — Legend enumerates all 7 enum values

- GIVEN the migration `2025_09_20_082341_create_appointments_table.php` + `2025_10_14_123001_fix_appointments_status_enum.php` define the 7-value status enum
- WHEN PR-citas-02 lands
- THEN `CalendarPage.vue`'s legend template references each of the 7 enum values
- AND `ConsultationWizardStatusEnumTest` asserts the rule (7 enum values referenced, not the literal output of one example)
- AND visual verification confirms each legend dot/badge is perceptually distinct

### Requirement: `CITAS-WIZ-001` — `ConsultationWizard` MUST use Ui primitives exclusively

The system MUST replace every raw `<input>`, `<textarea>`, `<select>`,
and `<button>` in `ConsultationWizard.vue` with `<UiInput>`,
`<UiSelect>`, `<UiButton>`, and `<UiStatusBadge>`. The 5-step
navigation strip MUST consume `<UiTabs>` with step transitions of
`var(--motion-duration-fast) var(--motion-easing-ios)`. The inline
`@click="currentStep = step.id"` navigation MUST be replaced by
`<UiTabs v-model="currentStep">`. Hardcoded `text-red-500` required
asterisks MUST be replaced by `<UiInput required>` indicator.

#### Scenario: `CITAS-WIZ-001-1` — Wizard uses Ui primitives and UiTabs

- GIVEN `ConsultationWizard.vue` contains ~50 raw form controls
- WHEN PR-citas-01 lands
- THEN zero raw `<input class="border-theme">` or raw `<button class="step">` remain in the wizard
- AND `<UiTabs v-model="currentStep">` replaces the inline step click handler
- AND `CalendarAppShellTest::test_consultation_wizard_uses_ui_primitives` asserts the rule

### Requirement: `CITAS-MOD-001` — `NewAppointmentModal` MUST use `<UiModal>` + Ui inputs

The system MUST use `<UiModal>` chrome (NOT the hand-built
`bg-black bg-opacity-50` backdrop) and `<UiInput>` / `<UiSelect>` /
`<UiButton>` / `<UiStatusBadge>` primitives in `NewAppointmentModal.vue`.
Duplicate-key 422 errors from `AppointmentService::createAppointment`
(the DB unique constraint fires on the second commit) MUST be rendered
as a friendly "another desk booked this slot" message via template-level
error mapping.

#### Scenario: `CITAS-MOD-001-1` — Modal chrome is canonical and handles duplicate-key race

- GIVEN two reception desks can submit identical `(user_id, scheduled_at, ends_at)` and the DB unique constraint fires on the second commit
- WHEN PR-citas-03 lands
- THEN `NewAppointmentModal.vue` contains zero `bg-black bg-opacity-50` strings
- AND `CalendarAppShellTest::test_new_appointment_modal_uses_ui_modal` asserts `<UiModal>` wrapper presence
- AND the duplicate-key 422 is mapped to a localizable conflict message (not a default 500 toast)

### Requirement: `CITAS-AT-001` — `AppointmentTypesPage` + `AppointmentTypeDetailPage` (admin CRUD triplet half)

The system MUST use `<UiCard>` + `<UiButton>` + `<UiStatusBadge>` +
`<UiSelect>` + `<UiInput>` primitives in `AppointmentTypesPage.vue`
and `AppointmentTypeDetailPage.vue`. The filter bar MUST consume
`<UiSelect>` (NOT raw `<select>`). The `price` field MUST call
`formatCurrency` from the canonical `useFormatters.js` location
(depends on PAGOS PR-pagos-05 landing first).

#### Scenario: `CITAS-AT-001-1` — Admin CRUD triplet uses Ui primitives and canonical formatter

- GIVEN `AppointmentTypesPage.vue` exposes a `price` field
- WHEN PR-citas-04 lands
- THEN the filter bar contains zero raw `<select class="border-theme">`
- AND `useFormatters.formatCurrency` is the sole money formatter consumed
- AND `AppointmentTypesAppShellTest` extends `ModuleAppShellTestCase` and asserts the rule
- AND `AppointmentPriceFormatterTest` asserts `formatCurrency` exists at exactly one location

### Requirement: `CITAS-TZ-001` — Timezone contract preservation on `datetime-local` inputs

The system MUST NOT introduce a JS-side `.toISOString()` call on any
`datetime-local` input value in `NewAppointmentModal.vue` or
`ConsultationWizard.vue`. The server interprets naive local time as
`app.timezone` per `AppointmentService::createAppointment`. The
migration `2026_06_02_173228_fix_appointments_timezone_offset` exists
precisely because this was once wrong; visual change MUST NOT regress.

#### Scenario: `CITAS-TZ-001-1` — No JS-side `toISOString()` on `datetime-local`

- GIVEN `AppointmentService::createAppointment` does `Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'))`
- WHEN PR-citas-01 or PR-citas-03 lands
- THEN `git grep -nE '\.toISOString\(\)' resources/js/components/appointments/NewAppointmentModal.vue resources/js/modules/appointments/ConsultationWizard.vue` returns zero matches
- AND the timezone contract holds verbatim

### Requirement: `CITAS-CONF-001` — Conflict detection MUST round-trip through `AppointmentRepository::findConflicts`

The system MUST NOT claim "no conflict" without round-tripping through
`POST /api/appointments` (the `AppointmentRepository::findConflicts`
3-axis overlap check). The in-page block-count heuristic is stale by
definition. UI feedback MAY render a count after the round-trip, but
the source of truth is the server.

#### Scenario: `CITAS-CONF-001-1` — No client-side "no conflict" claim

- GIVEN `AppointmentRepository::findConflicts` is the only conflict oracle
- WHEN PR-citas-03 lands
- THEN `NewAppointmentModal.vue` does NOT introduce a local block-count heuristic
- AND the modal's conflict message (if any) reflects the server response, not a client count

### Requirement: `CITAS-RT-001` — Echo channel isolation on `appointments`

The system MUST subscribe to the `appointments` channel via the existing
`useEcho` patterns (`.listen(...)` + `echo.leave(...)`). The apply phase
MUST NOT introduce parallel channels or polling fallbacks. UI changes
MUST NOT touch `<script>` blocks of `CalendarPage.vue`,
`NewAppointmentModal.vue`, `ConsultationWizard.vue`,
`AppointmentTypesPage.vue`, or `AppointmentTypeDetailPage.vue`.

#### Scenario: `CITAS-RT-001-1` — `appointments` channel subscription preserved verbatim

- GIVEN `useEcho` listens on `appointments` for `AppointmentCreated` / `AppointmentUpdated` / `AppointmentDeleted` events
- WHEN any PR-citas-NN lands
- THEN no `Echo.private(...)` or polling fallback is introduced
- AND `git diff --stat` shows zero edits to `<script>` blocks of the 5 CITAS modules
- AND manual smoke test: two browser tabs on `/calendar`, create an appointment in tab A, tab B receives the event within 1 second

### Requirement: `CITAS-WS-001` — Dormant WorkSchedule / AppointmentBlock validations MUST NOT be implied

The system MUST NOT introduce UX that implies the system enforces
`WorkSchedule` or `AppointmentBlock` validation. Those validations are
commented out in `AppointmentService::createAppointment` lines 75–89
("profesionales trabajan 24/7"). The `work_schedules` and
`appointment_blocks` tables exist but are NOT enforced.

#### Scenario: `CITAS-WS-001-1` — No UX implies WorkSchedule / AppointmentBlock enforcement

- GIVEN `AppointmentService` lines 75–89 show work-schedule + blocks validation is commented out
- WHEN any PR-citas-NN lands
- THEN `CalendarPage.vue` and `NewAppointmentModal.vue` do NOT render error text or disabled states that imply outside-hours blocking
- AND `git grep -nE 'work[_ ]?schedule|appointment[_ ]?block' resources/js/modules/appointments/CalendarPage.vue resources/js/components/appointments/NewAppointmentModal.vue` returns zero matches that imply enforcement

### Requirement: `CITAS-REV-001` — Each PR-citas-NN MUST stay under the 400-line review budget

The system MUST keep each `pr-citas-NN` PR under the 400-line authored
review budget. When a PR's diff exceeds 400 lines (PR-citas-01 ~390
lines, PR-citas-04 ~360 lines are both near the budget), the apply
phase MUST split per the `chained-pr` skill (e.g. PR-citas-01a + 01b for
wizard steps 1–3 vs 4–5; PR-citas-04a + 04b for the list vs detail page).

#### Scenario: `CITAS-REV-001-1` — PR-citas-01 splits under the 400-line budget when needed

- GIVEN `ConsultationWizard.vue` is the densest form in CITAS (~50 raw controls)
- WHEN the PR-citas-01 diff is reviewed
- THEN `git diff --stat` reports `additions + deletions <= 400`
- AND if the diff exceeds 400 lines, the PR is split into `pr-citas-01a-wizard-steps-1-3` + `pr-citas-01b-wizard-steps-4-5` BEFORE the review starts

### Requirement: `CITAS-CON-001` — Existing consultation-wizard contract MUST be preserved

The system MUST preserve the `useConsultation` composable public contract
byte-for-byte. The `.listen(...)` + `echo.leave(...)` calls on the
`appointments` channel MUST stay verbatim. The apply phase MUST NOT
edit any `<script>` block of `ConsultationWizard.vue`.

#### Scenario: `CITAS-CON-001-1` — `useConsultation` contract and wizard reactivity preserved

- GIVEN `ComposablesStandardizationTest` pins the `useConsultation` standard contract
- WHEN any PR-citas-NN lands
- THEN `ComposablesStandardizationTest` stays green at every boundary
- AND `<script>` block of `ConsultationWizard.vue` is byte-for-byte unchanged
- AND wizard reactivity (currentStep, v-model bindings, computed class strings) is preserved

### Requirement: `CITAS-A11Y-001` — Calendar grid a11y follow-up (OPTIONAL, flagged)

The system SHOULD add `role="grid"` plus per-cell `aria-label` on the
day/week/month views in `CalendarPage.vue` so screen readers can
navigate "Tuesday 9 AM, Tuesday 10 AM" efficiently. This row is
OPTIONAL for the visual polish rollout; it is flagged in
`openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/a11y-followup.md`
for a future a11y slice.

#### Scenario: `CITAS-A11Y-001-1` — Calendar grid ARIA roles recorded as future work

- GIVEN the week/month views in `CalendarPage.vue` use plain `<div>` grids with no ARIA role
- WHEN PR-citas-02 lands
- THEN the a11y follow-up document is created
- AND the row is marked OPTIONAL; no test fails if `role="grid"` is not introduced in PR-citas-02
- AND the hardcoded `textColor: '#ffffff'` in `CalendarService::getCalendarData` line 101 is also documented as a future a11y slice (color-contrast defect)

---

## 3. Out-of-scope explicit list

Mirrors the CITAS proposal §3. Items are excluded from the CITAS
rollout and explicitly recorded so the apply phase does NOT silently
resolve them.

| Item | Reason |
|---|---|
| Backend business logic (`AppointmentService`, `CalendarService`, `ConsultationService`, `ReminderService`, `AppointmentRepository`) | UI-only migration; `createAppointment` + `findConflicts` + `checkIn` + `complete` byte-for-byte unchanged |
| Timezone contract (`setTimezone(config('app.timezone'))`) | Visual change MUST NOT introduce JS-side `toISOString()` on `datetime-local` |
| Conflict detection (`AppointmentRepository::findConflicts` 3-axis overlap) | UI SHALL NOT claim "no conflict" without round-tripping through the endpoint |
| Recall on concurrent bookings (DB unique constraint) | The modal renders a friendly error; verification during apply, not a service change |
| Status enum + SoftDeletes semantics | Seven values is the rendering rule; the enum + SoftDeletes trait are unchanged |
| Recurrence vs single-edit semantics | `AppointmentRecurrence` is single-edit only; no UI implies series editing |
| Reminder dispatch idempotency | `ReminderService::scheduleReminders` uses `updateOrCreate`; UI never re-creates on update |
| `ConfirmationToken` redaction | Token + hash MUST NOT render to non-admin viewers; apply phase greps to verify |
| `WorkSchedule` + `AppointmentBlock` admin UIs | Validation is commented out; UI MAY NOT imply enforcement |
| `WaitingList` admin UI | Separate change |
| Treatment-plan CRUD screens | Belongs to the clinical cluster PR6 slice |
| Quotation / billing screens | PAGOS category |
| Patient demographic forms | Separate module |
| Medical-record content | Separate clinical cluster |
| `DiagnoseAppointments` Artisan command | CLI; no UI |
| `ReminderProvider` hourly cron wiring | Backend; no UI change |
| Dashboard's "today appointments" tile | Polished in vertical slice (PR4) |
| Two-tone numerals (D12 REVERSIBLE) | Stays rejected |
| `CalendarService::getCalendarData` `textColor: '#ffffff'` color-contrast defect | Existing a11y defect; flagged for future slice |
| Calendar grid `role="grid"` + per-cell `aria-label` | A11y follow-up; out of scope for visual polish |
| New `WaitingList` / `AppointmentBlock` / `WorkSchedule` admin frontend | Flagged for follow-up change |
| `<script>` blocks of CITAS modules | UI changes are template-level class-string replacement only |

---

## 4. Verification strategy

- **Visual**: `pnpm build` clean; no console errors; `git grep` for
  `border-theme`, `bg-success-100`, `text-accent`, `bg-error-100`,
  `focus:ring-primary-500 focus:border-accent`, `hover-lift`,
  `bg-primary-50`, `bg-black bg-opacity-50`, raw `<input>`,
  raw `<select>` returns zero matches inside the four CITAS module
  paths. `playwright-cli` snapshot at 1440×900 per CITA route
  + 390×844 for `/calendar` (receptionist mobile path), saved to
  `.playwright-cli/screenshots-rollout/citas-{calendar,wizard,modal,types}-{1440x900,390x844}.png`.
  Credentials: `recep@test.com` for calendar + modal; `admin@test.com`
  for appointment-types CRUD.
- **Static (PHPUnit)**: `AppointmentPriceFormatterTest` asserts
  `formatCurrency` is imported from the canonical `useFormatters.js`
  location; `ConsultationWizardStatusEnumTest` asserts the legend
  references all 7 enum values; `CalendarAppShellTest` +
  `AppointmentTypesAppShellTest` extend `ModuleAppShellTestCase` and
  assert the per-module rule (token reference exists, alias absent,
  `<style scoped>` absent). `LegacyAliasForbiddenTest` extended with
  `bg-black bg-opacity-50` for the modal.
- **Runtime**: `ComposablesStandardizationTest` (uses `useConsultation`),
  `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, and the
  remaining `tests/Unit/DesignSystem/*` invariants stay green at every
  PR-citas-NN boundary. Manual smoke test: two browser tabs on
  `/calendar`; create an appointment in tab A; verify tab B receives
  the `AppointmentCreated` event within 1 second.

---

## 5. Acceptance criteria

The CITAS category is considered complete when ALL of the following
hold: all 3 CITAS routes (`/calendar`, `/appointment-types`,
`/appointment-types/:id`) render on `var(--color-canvas)`;
`ConsultationWizard` uses `<UiInput>` / `<UiSelect>` / `<UiButton>` /
`<UiStatusBadge>` / `<UiTabs>` exclusively; `CalendarPage` legend
renders all 7 enum values (`scheduled / confirmed / in_progress /
completed / cancelled / no_show / rescheduled`) via `<UiStatusBadge>`;
`NewAppointmentModal` uses `<UiModal>` (NOT
`bg-black bg-opacity-50`); `AppointmentTypesPage` filter bar uses
`<UiSelect>`; `AppointmentTypesPage` price field uses `formatCurrency`
from canonical `useFormatters.js`; `useConsultation` contract preserved
verbatim; `useEcho` `appointments` channel subscription preserved
verbatim; zero JS-side `.toISOString()` on `datetime-local`; no UX
implies WorkSchedule or AppointmentBlock enforcement; no ConfirmationToken
echoed to non-admin views; no FullCalendar internals overridden
(`.fc-event`, `.fc-daygrid`, `.fc-timegrid`, `.fc-toolbar`); each
PR-citas-NN stays under the 400-line authored budget; per-PR
playwright-cli snapshots saved; CI gates (`quality`, `backend-tests`
MySQL, `frontend-build` pnpm) green at every PR-citas-NN boundary.

---

## 6. References

- `categories/citas/explore.md` — CITAS inventory (frontend, backend,
  controllers, services, jobs, models, tests, known gotchas).
- `categories/citas/proposal.md` — CITAS proposal (intent, scope,
  risk register, rollback, success criteria).
- `specs/design-language-rollout/spec.md` — parent spec (`DLR-MOD-002`
  Calendario, `DLR-MOD-006` Tipos de cita, cross-cutting `DLR-R-*`
  rules).
- `specs/foundation-primitives/spec.md` — PR0 spec (`<UiStatusBadge>`,
  `canvasRoutes`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`).
- `design.md` — PR0 design (StatusBadge API, canvasRoutes array
  literal, PHPUnit test contracts).
- `archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` —
  process lesson: "tests pin the rule, not the literal." CITAS
  structure tests extend `ModuleAppShellTestCase` and assert the rule.

---

*End of CITAS category spec.*
