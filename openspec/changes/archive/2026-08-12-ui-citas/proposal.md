# Proposal: UI Rollout — CITAS category (`ui-rollout-all-modules-2026-08`)

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | CITAS (calendar agenda, consultation wizard, new-appointment modal, appointment-type catalog) |
| Date | 2026-08-12 |
| Phase | propose (2 of 6) — category slice |
| Author | `sdd-propose` sub-agent (CITAS) |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/citas/proposal`) |
| Parent artifacts | `proposal.md` (596 lines), `explore.md` (496 lines), `categories/citas/explore.md` (175 lines), `specs/design-language-rollout/spec.md` |
| Sibling categories | PAGOS (`categories/pagos/proposal.md`) |
| Global PR mapping | CITAS = global PR7 (`pr7-calendar-chrome-only`) per global proposal §7.8; CITAS sub-PRs `pr-citas-01..05` split PR7's chrome-only scope into chained work units |
| Delivery strategy | Inherits `auto-chain` from the global proposal; CITAS sub-PRs `pr-citas-01..05` stack inside PR7 |
| Review budget | 400 authored lines / PR (per global proposal §7.15) |
| Strict TDD | `true` (forward to apply/verify) |
| Vertical slice baseline | `ui-premium-microdetail-2026-08` — closed 2026-08-11; tokens, primitives, easing, focus-ring, canvas/surface separation, `tabular-nums` all inherited as-is |

### Preflight snapshot (verbatim from global proposal)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain      # chained PRs auto-activate; do NOT re-ask
review_budget_lines: 400
chain_strategy: not_cached         # recommend stacked-to-main at sdd-tasks time
strict_tdd: true
```

---

## 1. Intent

CITAS is the operational pivot of every dental appointment: receptionists schedule, doctors check in, billing waits for completed appointments, and patients see their next visit on `/calendar`. The proven Apple language landed on Dashboard, Login, and 404; CITAS still reads as legacy. `CalendarPage.vue` (29.7 KB) shows `bg-success-100 / text-success-700 / bg-error-100` status pills, raw `bg-green-500 / bg-yellow-500 / bg-red-500` legend dots, `hover-lift` on appointment blocks, `bg-primary-50` today highlight, and a hardcoded `textColor: '#ffffff'` baked into `CalendarService::getCalendarData` line 101. `ConsultationWizard.vue` carries ~50 raw `<input>` / `<textarea>` / `<select>` controls with `border border-theme bg-theme-surface-elevated`, raw checkboxes, hardcoded `text-red-500` required asterisks, a raw `<button>` step strip with inline `@click="currentStep = step.id"` navigation (no transitions), and `bg-accent bg-opacity-5` selected state. `NewAppointmentModal.vue` uses a `bg-black bg-opacity-50` backdrop, raw `<select>` borders, and `focus:ring-primary-500 focus:border-accent`. `AppointmentTypesPage.vue` (21.5 KB) and `AppointmentTypeDetailPage.vue` mirror the admin CRUD triplet pattern.

The calendar status legend is the worst surface defect: it currently renders 5 of the 7 enum values (`programada / confirmada / en consulta / completada / cancelada`) — `no_show` and `rescheduled` are missing. A doctor who marks a patient as `no_show` sees no legend entry for it, and a patient who gets rescheduled has no visible token for that status. The form density in `ConsultationWizard` is the highest in the product; one wizard visit touches more legacy class strings than an entire Caja tab. `AppointmentTypesPage` exposes a `price` field that needs `formatCurrency` from `useFormatters.js`; that helper is the canonical money formatter that PAGOS consolidates to a single location.

This proposal scopes the rollout to **only** the citas interfaces inventoried in `categories/citas/explore.md`. It inherits every rule from the global proposal (token discipline, primitive contract, focus-ring composition, `tabular-nums`, canvas/surface separation, no `<style scoped>` grandfather clause) and applies them mechanically. The result: a receptionist landing on `/calendar` reads the same product as a clinician landing on `/dashboard`. Real-time Echo channels on `appointments`, the `useConsultation` contract, the `findConflicts` 3-axis overlap check, the timezone contract on `datetime-local`, and the `ConfirmationToken` redaction stay byte-for-byte untouched — UI changes are template-level class-string replacement only.

**Why now:** the foundation tokens are settled, the PHPUnit invariants are wired, and the global proposal's chain has Calendario isolated as PR7 (chrome-only per `DLR-MOD-002`). The CITAS work splits cleanly into 5 sub-PRs (see §6) that stay inside the 400-line review budget and don't disturb the chain order. The user's stated intent — extend the proven language to every module — applies with extra weight to CITAS because the consultation wizard and calendar agenda are the highest-touch clinical surfaces.

---

## 2. In-Scope

### 2.1 Pages / routes (3)

1. `/calendar` — `resources/js/modules/appointments/CalendarPage.vue` (29.7 KB; the hub: Day/Week/Month agenda, FullCalendar hosting slot, "En vivo" WS pill, status legend). Pinned in `AppLayout.canvasRoutes` by global PR0.
2. `/appointment-types` — `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (21.5 KB; admin CRUD: name, duration, price, color, requires_confirmation, requires_materials, is_consultation_mode). Pinned in `AppLayout.canvasRoutes` by global PR0.
3. `/appointment-types/:id` — `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` (detail / edit view of one appointment type). Inherited canvas from PR0.

### 2.2 Modal component (1)

| File | Touch scope |
|---|---|
| `resources/js/components/appointments/NewAppointmentModal.vue` | Medium. Mounted via `?openAppointmentModal=true` redirect from `DashboardPage.vue` (line 56–58 of `app.js`), `CalendarPage.vue`, and `MedicalRecordsPage.vue`. Replace `bg-black bg-opacity-50` backdrop with `<UiModal>` chrome; replace raw `<select>` / `<input>` borders with `<UiSelect>` / `<UiInput>` + hairline; error styling → focus-ring; disabled affordance → `LoadingSpinner`. |

### 2.3 Wizard component (1)

| File | Touch scope |
|---|---|
| `resources/js/modules/appointments/ConsultationWizard.vue` | Large. 5 steps (mode / SOAP evolution / procedures / materials / odontogram + attachments + summary). ~50 raw `<input>` / `<textarea>` / `<select>` controls all using `border border-theme bg-theme-surface-elevated`. Raw `<button>` step strip → `<UiTabs>` with transitions; raw checkboxes → `<UiCheckbox>`; hardcoded `text-red-500` required asterisks → `<UiInput required>` indicator; `bg-accent bg-opacity-5` selected state → tokenised `<UiTabs>` active state. **Largest single tokenisation target in CITAS** (line estimate higher than `CalendarPage.vue`). |

### 2.4 Cross-cutting composables (touch points only — do NOT fork)

| Composable | Touch |
|---|---|
| `resources/js/composables/useConsultation.js` | **Unchanged.** Sole canonical composable for the wizard. UI changes are template-only; the `consultation-context` / `check-in` / `complete` contract stays verbatim. |
| `resources/js/composables/useEcho.js` | **Unchanged.** Listens on the `appointments` channel. UI changes do NOT touch `<script>` blocks; `.listen(...)` + `echo.leave(...)` stays verbatim (see risk #3). |
| `resources/js/composables/useFormatters.js` | **Consume `formatCurrency`** for the `AppointmentTypes.price` field. PAGOS consolidates `formatCurrency` to exactly one location in PR-pagos-05; CITAS depends on that helper but does NOT add its own reimplementation. |

### 2.5 Reused primitives (consumed from PR0 / Domain 2; CITAS does NOT redefine)

| Primitive | Use |
|---|---|
| `resources/js/components/ui/Card.vue` (`<UiCard variant="glass">`) | Wrapper on `CalendarPage` header + controls; `AppointmentTypesPage` filter bar; `NewAppointmentModal` body chrome. |
| `resources/js/components/ui/Button.vue` (`<UiButton>`) | All action affordances: Nuevo / Volver / view-toggle / wizard step submit. |
| `resources/js/components/ui/Input.vue` / `Select.vue` / `Modal.vue` / `LoadingSpinner.vue` / `EmptyState.vue` / `StatusBadge.vue` / `Tabs.vue` | Form inputs, modal chrome, status pills, wizard step strip. |
| `resources/js/components/ui/Checkbox.vue` (if present, otherwise raw `<input type="checkbox">` with tokenised chrome) | Wizard step toggles. |

### 2.6 Tests

| Test file | Action |
|---|---|
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Keep green. The array literal includes `/calendar` + `/appointment-types`. |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | Keep green. `useConsultation` standard contract preserved (used by `ConsultationWizard`). |
| `tests/Unit/DesignSystem/CalendarAppShellTest.php` | NEW. Extends `ModuleAppShellTestCase`; asserts `CalendarPage` + `ConsultationWizard` + `NewAppointmentModal` reference the proven tokens and contain no legacy aliases / no `<style scoped>` block. |
| `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` | NEW. Extends `ModuleAppShellTestCase`; same contract for `AppointmentTypesPage` + `AppointmentTypeDetailPage`. |
| `tests/Unit/DesignSystem/ConsultationWizardStatusEnumTest.php` | NEW. Asserts the calendar status legend renders all 7 enum values (`programada / confirmada / en consulta / completada / cancelada / no_show / rescheduled`) — the rule, not the literal string array. |
| `tests/Unit/DesignSystem/AppointmentPriceFormatterTest.php` | NEW. Asserts `AppointmentTypesPage` price field calls `formatCurrency` from `useFormatters.js` (canonical location) — NOT a local reimplementation. |

---

## 3. Out-of-Scope

The following look citas-related but are explicitly excluded. They may be raised in a follow-up change once this rollout lands.

1. **Backend business logic.** No changes to `AppointmentService::createAppointment`, `AppointmentRepository::findConflicts` (3-axis overlap), `ConsultationService::checkIn` / `complete` (with its `MissingEvolutionException`, `MissingMaterialsException`, `UnexpectedMaterialsException`, `InvalidConsultationModeException`, `InvalidTreatmentPlanException`), `ReminderService::scheduleReminders` (idempotent via `updateOrCreate`), or `CalendarService::getCalendarData` (incl. the hardcoded `textColor: '#ffffff'` at line 101). UI-only.
2. **Timezone contract.** `AppointmentService::createAppointment` does `Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'))`. UI input is `datetime-local`; server interprets as `app.timezone`. Visual change MUST NOT introduce a JS-side `toISOString()` on a `datetime-local` value. The migration `2026_06_02_173228_fix_appointments_timezone_offset` exists precisely because this was once wrong — do not regress.
3. **Conflict detection.** `AppointmentRepository::findConflicts` is a 3-axis overlap (start-within / end-within / contains) over both `user_id` AND `dental_chair_id` in one query. UI SHALL NOT claim "no conflict" without round-tripping through this endpoint — the in-page block-count heuristic is stale by definition.
4. **Race conditions on concurrent bookings.** Two reception desks can submit identical `(user_id, scheduled_at, ends_at)` and the DB unique constraint fires on the second commit. The modal MUST render a friendly "another desk booked this slot" message, not a 500 toast. Currently the code relies on Laravel default exception rendering — verify during apply that the modal handles the duplicate-key gracefully (template-level error mapping, not service-level).
5. **Status enum + soft-deletes.** `Appointment` uses `SoftDeletes` AND a 7-value status enum. Visual change MUST keep the status-pill colors aligned with the 7 enum values. The legend currently renders 5 of 7 — this proposal ADDS `no_show` and `rescheduled` (the missing two) per the global design language, but does NOT alter the status semantics.
6. **Recurrence vs single-edit semantics.** `AppointmentRecurrence` exists but `AppointmentService::updateAppointment` updates the single appointment, not the recurrence series. A "edit recurring" feature does NOT exist. Do not introduce UI that implies it.
7. **Reminder dispatch idempotency.** `ReminderService::scheduleReminder` uses `updateOrCreate` keyed on `(appointment_id, hours_before)`. UI must never re-create reminders on appointment update; `AppointmentService::updateAppointment` does NOT call `scheduleReminders` again, so this is correct by absence.
8. **Confirmation tokens.** `ConfirmationToken` model backs a public-link confirmation flow. Any visual change to the appointment card MUST NOT expose the token or its hash to non-admin viewers.
9. **WorkSchedule + AppointmentBlock admin UI.** Currently absent from `resources/js/modules/` — admin edits via API only. The `AppointmentService` lines 75–89 show that work-schedule and blocks validation are commented out ("profesionales trabajan 24/7"). The `work_schedules` and `appointment_blocks` tables still exist; DO NOT introduce UX that suggests the system enforces them.
10. **WaitingList admin UI.** Same — `WaitingList` model exists, no frontend surface yet. Out of scope.
11. **Treatment-plan CRUD screens** even though `ConsultationWizard` advances a plan. That is the clinical-modules PR6 slice.
12. **Quotation / billing screens** even though they consume completed appointments. That is the PAGOS category.
13. **Patient demographic forms** (`/patients`, `/patients/:id`) — separate module even though appointment history is rendered on the detail page.
14. **Medical-record content** (`/medical-records`, `/specialty-records`) — separate clinical cluster.
15. **`DiagnoseAppointments` Artisan command** (CLI surface, no UI).
16. **`ReminderProvider` hourly cron wiring** (`routes/console.php` line 14–19, `withoutOverlapping(5)`) — backend, no UI change.
17. **Dashboard's "today appointments" tile** — already polished in vertical slice (PR4); consumed by the new modal only via the `?openAppointmentModal=true` redirect.
18. **Two-tone numerals (D12 REVERSIBLE from vertical slice)** — stays rejected.
19. **`CalendarPage textColor: '#ffffff'` color-contrast defect.** The hardcoded white text against `appointmentType->color` (which can be any hex) is an existing a11y defect. The rollout MUST NOT regress the contract (currently: hardcoded white, accept it as-is) and SHOULD flag for a future a11y slice.
20. **Calendar grid `role="grid"` ARIA per-cell labels.** The week/month views use plain `<div>` grids with no ARIA role; screen readers cannot navigate "Tuesday 9 AM, Tuesday 10 AM" efficiently. The proposed language change is the right moment to add this, but a11y is OUT of scope for visual polish unless the proposal explicitly includes it per the global spec `DLR-MOD-002`. Flag in this proposal (see §8 success criteria) but do NOT implement.
21. **New `WaitingList` / `AppointmentBlock` / `WorkSchedule` admin frontend** — flagged for a follow-up change if user signals during proposal review.

---

## 4. Approach

Reuse the proven language as-is; no new tokens, no new primitives (the full PR0 / Domain 2 set — `<UiCard>`, `<UiButton>`, `<UiInput>`, `<UiSelect>`, `<UiModal>`, `<UiTabs>`, `<UiStatusBadge>`, `<UiLoadingSpinner>`, `<UiEmptyState>`, `<UiCheckbox>` if present — is inherited from PAGOS / vertical slice). Replace legacy alias classes one-by-one inside each citas `.vue` file using the global proposal §4.1 mapping table verbatim. Reuse `formatCurrency` from `useFormatters.js` for the `AppointmentTypes.price` field (PAGOS PR-pagos-05 consolidates the helper to one location; CITAS depends on that PR landing first). The calendar status legend ADDS the 2 missing enum values (`no_show`, `rescheduled`) — bringing it from 5 to 7 — so a doctor marking `no_show` and a patient being `rescheduled` both render the correct legend token.

The CITAS rollout touches 5 files: `CalendarPage.vue` (29.7 KB; calendar agenda + legend), `NewAppointmentModal.vue` (modal chrome + form polish), `ConsultationWizard.vue` (~50 raw form controls), `AppointmentTypesPage.vue` (21.5 KB; admin CRUD triplet), `AppointmentTypeDetailPage.vue` (admin CRUD triplet). Touch scope ordering: **wizard first** (densest form surface, biggest UX win — sets the pattern for `<UiInput>` / `<UiSelect>` / `<UiTabs>` adoption), then **calendar** (highest-traffic clinical surface + 7-value legend fix), then **modal**, then **admin CRUD triplet**, then **cross-cutting tests**. The `useConsultation` contract, `useEcho` channel subscription on `appointments`, and `useFormatters.formatCurrency` consumer are preserved verbatim — UI changes are template-only class-string replacement.

The hardcoded `textColor: '#ffffff'` in `CalendarService::getCalendarData` line 101 stays as-is (out of scope for visual polish; flagged for future a11y slice). The calendar grid ARIA pass is also out of scope for this proposal (see §3 #20); it is the natural follow-up a11y change. The 5 citas routes already receive the canvas surface via the global `canvasRoutes` extension (PR0, already landed). The PR0 `LegacyAliasForbiddenTest` pins the alias list (`border-theme`, `bg-success-100`, `text-accent`, `focus:ring-primary-500 focus:border-accent`, `bg-theme-surface-elevated` on the page surface, `bg-primary-50`, `hover-lift`, etc.); `CalendarAppShellTest` / `AppointmentTypesAppShellTest` extend `ModuleAppShellTestCase` and assert the rule (token reference exists, alias absent), not a literal string (per the archive-report lesson). Visual verification per module: playwright-cli snapshot at 1440x900, plus 390x844 for `/calendar` (receptionist mobile path). Credentials: `recep@test.com` for calendar + modal; `admin@test.com` for appointment-types CRUD.

Strict TDD discipline: every UI replacement MUST come with a test that proves the new behaviour (RED-GREEN per project policy). The visual sweep is documented verification, not a CI gate.

---

## 5. Capabilities (contract with sdd-spec)

The sdd-spec phase reads this section to know exactly which spec files to create or update. Research `openspec/specs/` first to use the existing capability names.

### New Capabilities (none)

The CITAS rollout does NOT introduce new capability specs. It exercises the global capability `premium-design-foundation` (persisted at `openspec/specs/premium-design-foundation/spec.md`) and the global delta spec `design-language-rollout` (at `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md`). The CITAS requirements live as additional rows in the global spec's module table.

### Modified Capabilities (delta rows added to existing global delta spec)

For the `design-language-rollout` delta spec (sibling file `specs/design-language-rollout/spec.md`), add CITAS-specific rows to the Module scenarios table:

- `DLR-MOD-002` — Calendario (existing): inherited as-is from the global spec; CITAS clarifies that FullCalendar internals are NOT overridden and the 7-value status legend is the rule (not the 5-value literal that currently exists).
- `DLR-MOD-006` — Tipos de cita: `AppointmentTypesPage` + `AppointmentTypeDetailPage` tokenised as the admin CRUD triplet half; `price` field uses `formatCurrency` from `useFormatters.js` (single canonical location). (Inherits from global spec.)
- `DLR-CITAS-001` — NewAppointmentModal: `<UiModal>` chrome replaces hand-built `bg-black bg-opacity-50` backdrop; raw `<select>` / `<input>` borders → `<UiSelect>` / `<UiInput>` + hairline; duplicate-key 422 from `AppointmentService::createAppointment` rendered as a friendly "another desk booked this slot" message (template-level error mapping). (NEW row added by CITAS.)
- `DLR-CITAS-002` — ConsultationWizard: 5-step strip migrates from inline `@click="currentStep = step.id"` to `<UiTabs>` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions; ~50 raw `<input>` / `<textarea>` / `<select>` controls migrate to `<UiInput>` / `<UiSelect>` + hairline; raw checkboxes migrate to `<UiCheckbox>` (or tokenised raw with `<UiStatusBadge>` indicator on required); hardcoded `text-red-500` asterisks replaced by `<UiInput required>` indicator. `useConsultation` contract preserved verbatim. (NEW row added by CITAS.)
- `DLR-CITAS-003` — Calendar status legend completeness: legend MUST render all 7 enum values from `database/migrations/2025_09_20_082341_create_appointments_table.php` + `2025_10_14_123001_fix_appointments_status_enum.php`: `scheduled / confirmed / in_progress / completed / cancelled / no_show / rescheduled`. Missing `no_show` and `rescheduled` from current 5-value legend is the load-bearing bug. (NEW row added by CITAS.)
- `DLR-CITAS-004` — Echo channel isolation: `useEcho` `appointments` channel subscription preserved verbatim; `.listen(...)` + `echo.leave(...)` calls stay in their existing positions; UI changes MUST NOT touch `<script>` blocks of `CalendarPage.vue`, `NewAppointmentModal.vue`, `ConsultationWizard.vue`, `AppointmentTypesPage.vue`, or `AppointmentTypeDetailPage.vue`. (NEW row added by CITAS.)
- `DLR-CITAS-005` — FormatPENLabel backwards-compatible alias (inherited from PAGOS): `AppointmentTypesPage` price field calls `formatCurrency(amount, { currency: 'PEN', locale: 'es-PE' })` from the canonical `useFormatters.js` location. Backwards-compatible alias signature preserved; no formatting fork. (NEW row added by CITAS, rides PAGOS PR-pagos-05.)

If sdd-spec chooses to extract CITAS into a sibling delta spec (`specs/citas-rollout/spec.md`), that is allowed — the global proposal does not forbid per-category specs. Recommendation: extend the global spec to keep traceability simple. Discuss with the orchestrator at spec phase.

---

## 6. Deliverables

Five PRs. Each fits inside the 400-line budget. Each is independently buildable, testable, and revertible.

### PR-citas-01 — `ConsultationWizard` tokenisation (densest form surface)

| Field | Value |
|---|---|
| Name | `pr-citas-01-consultation-wizard` |
| Scope | `resources/js/modules/appointments/ConsultationWizard.vue`. ~50 raw `<input>` / `<textarea>` / `<select>` controls → `<UiInput>` / `<UiSelect>` + hairline. Raw `<button>` step strip → `<UiTabs>` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions on step navigation (replaces inline `@click="currentStep = step.id"`). Raw checkboxes → `<UiCheckbox>` (or tokenised raw with `<UiStatusBadge>` indicator on required). Hardcoded `text-red-500` asterisks → `<UiInput required>` indicator. `bg-accent bg-opacity-5` selected state → tokenised `<UiTabs>` active state. `useConsultation` contract preserved verbatim — only template + computed class strings change. |
| Files | 1 wizard + extend `CalendarAppShellTest` |
| Risk | High (touches the densest form in CITAS; 5-step navigation must keep back/forward working) |
| Dependencies | Global PR0 (already landed: `canvasRoutes`, `<UiStatusBadge>`, `<UiTabs>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`) |
| Line estimate | ~390 (right at the budget; split into 01a + 01b if reviewer flags) |
| Reversibility | `git revert <merge-sha>`; ConsultationWizard UI reverts to legacy look but `<script>` untouched |

### PR-citas-02 — `CalendarPage` tokenisation + 7-value status legend

| Field | Value |
|---|---|
| Name | `pr-citas-02-calendar-page-and-legend` |
| Scope | `resources/js/modules/appointments/CalendarPage.vue` (29.7 KB). Status pills → `<UiStatusBadge>` (5 currently rendered). Raw `bg-green-500 / bg-yellow-500 / bg-red-500` legend dots → tokenised legend with all 7 enum values (`scheduled / confirmed / in_progress / completed / cancelled / no_show / rescheduled`). `hover-lift` on appointment blocks → `<UiCard clickable>`. `bg-primary-50` today highlight → tokenised `<UiCard variant="elevated">`. FullCalendar internals (`.fc-event`, `.fc-daygrid`, `.fc-timegrid`, `.fc-toolbar`) NOT overridden. `textColor: '#ffffff'` hardcoded white stays as-is (existing a11y defect; flagged for future slice). |
| Files | 1 page + extend `CalendarAppShellTest` + new `ConsultationWizardStatusEnumTest` |
| Risk | Medium (highest-traffic clinical surface; 7-value legend is the load-bearing bug fix) |
| Dependencies | PR-citas-01 (so ConsultationWizard lives on the tokenised `<UiTabs>` + `<UiInput>` primitives that PR-citas-02 consumes) |
| Line estimate | ~340 |
| Reversibility | `git revert <merge-sha>`; CalendarPage UI reverts to legacy look but `<script>` untouched |

### PR-citas-03 — `NewAppointmentModal` tokenisation

| Field | Value |
|---|---|
| Name | `pr-citas-03-new-appointment-modal` |
| Scope | `resources/js/components/appointments/NewAppointmentModal.vue`. `bg-black bg-opacity-50` backdrop → `<UiModal>` chrome. Raw `<select>` / `<input>` borders → `<UiSelect>` / `<UiInput>` + hairline. Error styling → `var(--focus-ring-default)` (composed focus ring). Disabled affordance → `<UiLoadingSpinner>` (NOT legacy `disabled:opacity-30`). Duplicate-key 422 from `AppointmentService::createAppointment` (the DB unique constraint fires on the second commit) rendered as a friendly "another desk booked this slot" message via template-level error mapping. UI changes are template-only; the `useApi()` call + timezone contract + conflict-detection round-trip stay verbatim. |
| Files | 1 modal + extend `CalendarAppShellTest` |
| Risk | Medium (real-money UX path; race-condition handling must stay intact) |
| Dependencies | PR-citas-01 + PR-citas-02 |
| Line estimate | ~320 |
| Reversibility | Same as 01 |

### PR-citas-04 — `AppointmentTypesPage` + `AppointmentTypeDetailPage` (admin CRUD triplet half)

| Field | Value |
|---|---|
| Name | `pr-citas-04-appointment-types-admin` |
| Scope | `AppointmentTypesPage.vue` (21.5 KB) + `AppointmentTypeDetailPage.vue`. Status pills → `<UiStatusBadge>` (`active / inactive` etc.). `border-theme` table → hairline + `tabular-nums` on price column. Inline `<select>` filter bar → `<UiSelect>`. `price` field uses `formatCurrency` from the canonical `useFormatters.js` location (depends on PAGOS PR-pagos-05 landing first). |
| Files | 2 pages + new `AppointmentTypesAppShellTest` + new `AppointmentPriceFormatterTest` |
| Risk | Medium (depends on PAGOS PR-pagos-05 helper consolidation) |
| Dependencies | PR-citas-01..03 + PAGOS PR-pagos-05 |
| Line estimate | ~360 |
| Reversibility | Same as 01 |

### PR-citas-05 — Cross-cutting tests + calendar legend a11y flag (no UI change)

| Field | Value |
|---|---|
| Name | `pr-citas-05-cross-cutting-tests-and-a11y-flag` |
| Scope | Add `tests/Unit/DesignSystem/CalendarAppShellTest.php` (extends `ModuleAppShellTestCase`) covering `CalendarPage` + `NewAppointmentModal` + `ConsultationWizard`. Add `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` covering the admin CRUD triplet. Add `tests/Unit/DesignSystem/ConsultationWizardStatusEnumTest.php` asserting the 7-value status legend rule. Add `tests/Unit/DesignSystem/AppointmentPriceFormatterTest.php` asserting `formatCurrency` from `useFormatters.js` is the sole money formatter. Add `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/a11y-followup.md` documenting the calendar grid `role="grid"` + per-cell `aria-label` follow-up (out of scope for visual polish; tracked for the future a11y slice). |
| Files | 4 new test files + 1 a11y follow-up doc |
| Risk | Low (no UI change; test-only) |
| Dependencies | PR-citas-01..04 |
| Line estimate | ~180 |
| Reversibility | Same as 01 |

### Deliverable-to-PR mapping (verifies the global chain)

| Global PR | CITAS PRs that ride it |
|---|---|
| Global PR7 (`pr7-calendar-chrome-only`) | PR-citas-01 + 02 + 03 (Calendar + Modal + Wizard) |
| Global PR4 (`pr4-admin-crud-triplet`) | PR-citas-04 (Appointment-Types half) |
| (any) | PR-citas-05 can ride any of the four; default is to land after PR-citas-04 |

---

## 7. Affected Areas

| Area | Impact | Description |
|---|---|---|
| `resources/js/modules/appointments/CalendarPage.vue` | Modified | Calendar agenda + 7-value status legend; FullCalendar chrome only |
| `resources/js/modules/appointments/ConsultationWizard.vue` | Modified | 5-step wizard tokenised; `<UiTabs>` + `<UiInput>` + `<UiSelect>` |
| `resources/js/components/appointments/NewAppointmentModal.vue` | Modified | `<UiModal>` chrome; raw inputs → `<UiInput>` / `<UiSelect>` |
| `resources/js/modules/appointment-types/AppointmentTypesPage.vue` | Modified | Admin CRUD list; `price` via `formatCurrency` from canonical `useFormatters.js` |
| `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` | Modified | Admin CRUD detail; same primitives |
| `resources/js/composables/useConsultation.js` | Unchanged | Wizard contract preserved verbatim |
| `resources/js/composables/useEcho.js` | Unchanged | `appointments` channel subscription preserved verbatim |
| `resources/js/composables/useFormatters.js` | Modified (by PAGOS PR-pagos-05) | `formatCurrency` consolidated to canonical location; CITAS consumes |
| `app/Services/AppointmentService.php` | Unchanged | Out of scope; `createAppointment` + conflict detection verbatim |
| `app/Services/CalendarService.php` | Unchanged | Out of scope; `textColor: '#ffffff'` hardcoded white stays as-is |
| `app/Services/ConsultationService.php` | Unchanged | Out of scope; `checkIn` / `complete` verbatim |
| `app/Services/ReminderService.php` | Unchanged | Out of scope; `scheduleReminders` idempotent verbatim |
| `app/Repositories/AppointmentRepository.php` | Unchanged | Out of scope; 3-axis `findConflicts` verbatim |
| `app/Events/Appointment*` | Unchanged | Out of scope; Reverb broadcasts verbatim |
| `app/Models/Appointment.php` (incl. SoftDeletes, 7-value status enum) | Unchanged | Out of scope; status semantics preserved |
| `database/migrations/2025_09_20_082341_create_appointments_table.php` + `2025_10_14_123001_fix_appointments_status_enum.php` | Unchanged | 7-value status enum is the SOURCE for the 7-value legend; no migration change |
| `tests/Unit/DesignSystem/CalendarAppShellTest.php` | New | Extends `ModuleAppShellTestCase` |
| `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` | New | Extends `ModuleAppShellTestCase` |
| `tests/Unit/DesignSystem/ConsultationWizardStatusEnumTest.php` | New | Asserts 7-value status legend rule |
| `tests/Unit/DesignSystem/AppointmentPriceFormatterTest.php` | New | Asserts `formatCurrency` from canonical `useFormatters.js` |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Unchanged | Keep green (already includes `/calendar` + `/appointment-types`) |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | Unchanged | Keep green (`useConsultation` standard contract) |

---

## 8. Risks

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | `ConsultationWizard` 5-step navigation currently uses inline `@click="currentStep = step.id"` (no transitions); Apple motion wash with `var(--motion-duration-fast) var(--motion-easing-ios)` on `<UiTabs>` could break the wizard back/forward if transitions interfere with the step change. | Medium | Apply phase: keep step transitions minimal (single opacity + translateY ≤8px); don't over-animate. Visual verification per PR-citas-01 with clinical role at 1440x900. Manual smoke test: enter wizard, navigate forward 3 steps, navigate back 2 steps, complete — verify no stuck-state on the step strip. |
| 2 | `CalendarService::getCalendarData` line 101 hardcodes `textColor: '#ffffff'` against `appointmentType->color` (which can be any hex). If the type color is yellow/light, text becomes unreadable. Color contrast is an existing a11y defect. | Medium | The rollout MUST NOT regress the contract (currently: hardcoded white, accept it as-is) — UI changes do NOT touch `CalendarService.php`. Flag in `a11y-followup.md` for a future a11y slice that introduces a luminance-based text-color resolver. Document the defect in the PR-citas-02 description. |
| 3 | `useEcho` `appointments` channel subscription must keep firing after primitive swap. Any `<script>` edit that accidentally removes `.listen(...)` or `echo.leave(...)` silently breaks realtime across the calendar. | Low | Apply phase scope rule: `<script>` blocks of `CalendarPage.vue`, `NewAppointmentModal.vue`, `ConsultationWizard.vue`, `AppointmentTypesPage.vue`, `AppointmentTypeDetailPage.vue` are NEVER touched. Visual smoke test: open `/calendar` in two browser tabs, create an appointment in tab A, verify tab B receives the `AppointmentCreated` event within 1 second. |
| 4 | `ConfirmationToken` model backs a public-link confirmation flow; visual change to the appointment card MUST NOT expose the token or its hash to non-admin viewers. | Low | `ConfirmationToken` is not rendered in `CalendarPage.vue`'s appointment card markup; the rollout does NOT add token rendering. Apply phase: verify `grep -r "ConfirmationToken\|confirmation_token" resources/js/modules/appointments` returns zero matches. If a token is ever echoed to a non-admin view, treat as a blocker. |
| 5 | `WorkSchedule` + `AppointmentBlock` validations are commented out in `AppointmentService` (lines 75–89) — "profesionales trabajan 24/7". UI must NOT imply the system enforces them. | Low | `WorkSchedule` + `AppointmentBlock` admin UIs are out of scope (no frontend surface yet). The rollout does NOT introduce UX that suggests enforcement. The existing `work_schedules` + `appointment_blocks` tables are not consumed by `CalendarPage.vue` or `NewAppointmentModal.vue`; verify during apply. |
| 6 | `formatCurrency` consolidation in PAGOS PR-pagos-05 lands first; if that PR slips, `AppointmentTypesPage.price` cannot consume the canonical helper. | Medium | PR-citas-04 is gated on PAGOS PR-pagos-05. If PAGOS slips, CITAS PR-citas-04 is held back. Fallback: PR-citas-04 imports `formatCurrency` from a TEMPORARY local helper that matches the canonical signature `(amount, options) => string`; PAGOS PR-pagos-05 deletes the temporary helper on landing. Apply phase MUST NOT ship a formatting fork. |
| 7 | Chained PRs may exceed the 400-line review budget. PR-citas-01 (wizard, ~390 lines) and PR-citas-04 (admin triplet, ~360 lines) are both near the budget. | Medium | If a PR's diff exceeds 400 lines, split per `chained-pr` skill: PR-citas-01a (steps 1–3: mode + SOAP + procedures) + 01b (steps 4–5: materials + odontogram/summary); PR-citas-04a (`AppointmentTypesPage`) + 04b (`AppointmentTypeDetailPage`). |
| 8 | Status legend completeness: adding `no_show` and `rescheduled` could introduce a token-color clash if the chosen variant overlaps with an existing one. | Low | `<UiStatusBadge variant="neutral">` for `no_show` and `<UiStatusBadge variant="warning">` for `rescheduled` — distinct from existing `success / error / info` variants. Visual verification per PR-citas-02: confirm legend swatches are perceptually distinct. `ConsultationWizardStatusEnumTest` asserts the 7 enum values map to distinct variants. |

---

## 9. Rollback Plan

- **Per-PR revert:** each PR-citas-NN is independently revertible via `git revert <merge-sha>` because the global `stacked-to-main` strategy keeps every commit reachable.
- **PR-citas-01 (Wizard):** revert restores legacy raw `<input>` / `<textarea>` / `<select>` controls and the inline `@click="currentStep = step.id"` step strip. `<script>` block untouched, so `useConsultation` reactivity contract is preserved. The wizard's verified screenshot baseline at `.playwright-cli/screenshots-rollout/citas-wizard-1440x900.png` is the regression witness.
- **PR-citas-02 (Calendar):** revert restores the legacy 5-value status legend (re-introducing the `no_show` / `rescheduled` gap). `<script>` block untouched, so `useEcho` `appointments` channel subscription is preserved. The status pill classes revert to `bg-success-100 text-success-700` / `bg-error-100 text-error-700` patterns.
- **PR-citas-03 (Modal):** revert restores the legacy `bg-black bg-opacity-50` backdrop and raw `<select>` / `<input>` borders. `<script>` block untouched, so the `useApi()` call + timezone contract + conflict-detection round-trip are preserved. The duplicate-key 422 error mapping reverts to Laravel default exception rendering.
- **PR-citas-04 (Appointment-Types):** revert restores legacy status badges and raw `<select>` filter bar. `formatCurrency` consumption reverts to the legacy 4+ reimplementations pattern (or whatever PAGOS PR-pagos-05's revert restored).
- **PR-citas-05 (Cross-cutting tests):** revert restores the pre-PR0 test count; the new `*AppShellTest` + `ConsultationWizardStatusEnumTest` + `AppointmentPriceFormatterTest` files are deleted. No UI regression.
- **No destructive schema/data migrations.** All backend controllers / services / models / migrations are byte-for-byte unchanged. No destructive operation anywhere.

---

## 10. Success Criteria

The CITAS rollout is considered complete when ALL of the following hold:

- [ ] **All 3 citas routes (`/calendar`, `/appointment-types`, `/appointment-types/:id`) render on Apple canvas without legacy `border-theme`, `bg-success-100`, `bg-error-100`, `text-accent`, `focus:ring-primary-500 focus:border-accent`, `hover-lift`, `bg-primary-50`, or raw `<select>`/`<input>` borders in the visible content area.** `AppLayoutCanvasRoutesTest` green; `CalendarAppShellTest` + `AppointmentTypesAppShellTest` each green.
- [ ] **`ConsultationWizard` uses `<UiInput>` / `<UiSelect>` / `<UiButton>` / `<UiStatusBadge>` / `<UiTabs>` primitives exclusively.** Grep-verified: no raw `<input class="border-theme">`, no raw `<button>` step strip, no inline `@click="currentStep = step.id"` (replaced by `<UiTabs>` v-model). `useConsultation` contract preserved verbatim.
- [ ] **`CalendarPage` legend renders all 7 status enum values** (`scheduled / confirmed / in_progress / completed / cancelled / no_show / rescheduled`) with correct token colors via `<UiStatusBadge>`. `ConsultationWizardStatusEnumTest` green.
- [ ] **`NewAppointmentModal` uses `<UiModal>`** (NOT hand-built `bg-black bg-opacity-50` backdrop). Grep-verified: no `bg-black bg-opacity-50` in `NewAppointmentModal.vue`. `CalendarAppShellTest::test_new_appointment_modal_uses_ui_modal` green.
- [ ] **`AppointmentTypesPage` filter bar uses `<UiSelect>` not raw `<select>`.** Grep-verified: no raw `<select class="border-theme">` in the filter bar.
- [ ] **`AppointmentTypesPage` price field uses `formatCurrency` from canonical `useFormatters.js` location.** `AppointmentPriceFormatterTest` green; no `S/ ${n.toFixed(2)}` or `Intl.NumberFormat` outside the canonical helper.
- [ ] **`useConsultation` contract is unchanged.** `ComposablesStandardizationTest` green at every PR-citas-NN boundary.
- [ ] **`useEcho` `appointments` channel subscription stays subscribed.** Manual smoke test: two browser tabs on `/calendar`, create appointment in tab A, verify tab B receives the `AppointmentCreated` event within 1 second.
- [ ] **Timezone contract preserved: no JS-side `toISOString()` on `datetime-local`** anywhere in `NewAppointmentModal.vue` or `ConsultationWizard.vue`. Grep-verified: zero `\.toISOString\(\)` matches in the form input handlers.
- [ ] **3-axis conflict detection preserved.** `NewAppointmentModal` does NOT claim "no conflict" without round-tripping through `POST /api/appointments` — the in-page block-count heuristic is NOT introduced. `AppointmentRepository::findConflicts` stays the only conflict oracle.
- [ ] **Status enum alignment: 7 values rendered** in the calendar legend; the legacy 5-value legend is forbidden.
- [ ] **All CITAS Vue files have zero `<style scoped>` blocks.** `ModuleAppShellTestCase::test_no_style_scoped` green per module.
- [ ] **FullCalendar internals (`.fc-event`, `.fc-daygrid`, `.fc-timegrid`, `.fc-toolbar`) NOT overridden.** Grep-verified: no `.fc-event` / `.fc-daygrid` / `.fc-timegrid` / `.fc-toolbar` selectors in `CalendarPage.vue`'s `<style>` section.
- [ ] **`ConfirmationToken` is not exposed.** Grep-verified: `grep -r "ConfirmationToken\|confirmation_token" resources/js/modules/appointments resources/js/modules/appointment-types` returns zero matches.
- [ ] **Playwright snapshots saved to `.playwright-cli/screenshots-rollout/{calendar,appointment-types}-{1440x900,390x844}.png`** (mobile required for Calendar only).
- [ ] **All `tests/Unit/DesignSystem/*` PHPUnit invariants stay green** (`TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `UseSpringMathTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `ModuleAppShellTestCase`-derived tests).
- [ ] **CI green:** `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).
- [ ] **Test count delta ≥ +50** vs PR0 baseline (167 / 1158). Budget: +50 from the four new `*AppShellTest` / `*Test` files + per-PR RED-GREEN pairs. PR-citas-05 carries the bulk of the test additions.
- [ ] **A11y follow-up documented.** `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/a11y-followup.md` records the calendar grid `role="grid"` + per-cell `aria-label` work + the `textColor: '#ffffff'` luminance-resolver work as future changes (out of scope for this rollout).
- [ ] **Chain integrity:** every PR-citas-NN is independently buildable, testable, and revertible per `chained-pr` skill rules.

---

## 11. References

### 11.1 Source artifacts (read for this proposal)

| File | Why it matters |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (596 lines) | Global intent, scope, OQ resolutions, PR chain, success criteria |
| `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (496 lines) | Module inventory, per-module visual state, complexity tiers, PR chain ordering rationale |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/explore.md` (175 lines) | **PRIMARY INPUT.** Citas inventory, controllers/services/jobs/models inventory, test coverage surface, known gotchas |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` | Global MUST/SHOULD language; CITAS sub-PRs map onto global PR7 + PR4 (admin CRUD triplet) |
| `openspec/changes/ui-rollout-all-modules-2026-08/design.md` (47 KB) | Token names, primitive API, motion durations, focus rings |
| `openspec/changes/archive/2026-08-12-ui-pagos/proposal.md` | PAGOS category proposal — CITAS mirrors its structure, tone, length, deliverable granularity |
| `openspec/changes/archive/2026-08-12-ui-pagos/categories/pagos/explore.md` | PAGOS inventory — reference for cross-category `formatCurrency` consolidation |
| `openspec/specs/premium-design-foundation/spec.md` (404 lines) | The archived capability CITAS inherits (tokens, primitives, easing) |
| `openspec/config.yaml` | Preflight cache + strict TDD + pnpm-only + 400-line budget + CI MySQL |
| `AGENTS.md` §2, §4, §5, §6, §7 | Project context, stack, 17-module inventory, conventions, troubleshooting |
| `resources/js/design-system/tokens.js` | The proven token source-of-truth |
| `resources/css/tokens.generated.css` | Generated CSS (369 lines) |
| `resources/js/components/layout/AppLayout.vue` line 507 | `canvasRoutes` gate (global PR0) |
| `resources/js/modules/appointments/CalendarPage.vue` (29.7 KB) | CITAS PR-citas-02 primary file |
| `resources/js/modules/appointments/ConsultationWizard.vue` | CITAS PR-citas-01 primary file (densest form surface) |
| `resources/js/components/appointments/NewAppointmentModal.vue` | CITAS PR-citas-03 primary file |
| `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (21.5 KB) | CITAS PR-citas-04 list page |
| `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` | CITAS PR-citas-04 detail page |
| `resources/js/composables/useConsultation.js` | Wizard composable — preserved verbatim |
| `resources/js/composables/useEcho.js` | `appointments` channel subscription — preserved verbatim |
| `resources/js/composables/useFormatters.js` | Target location for canonical `formatCurrency` (PAGOS PR-pagos-05; CITAS PR-citas-04 consumes) |
| `app/Services/AppointmentService.php` | `createAppointment` + conflict detection + timezone contract — out of scope |
| `app/Services/CalendarService.php` line 101 | `textColor: '#ffffff'` hardcoded white — out of scope (a11y follow-up) |
| `app/Services/ConsultationService.php` | `checkIn` / `complete` — out of scope |
| `app/Services/ReminderService.php` | `scheduleReminders` idempotent — out of scope |
| `app/Repositories/AppointmentRepository.php` | 3-axis `findConflicts` — out of scope |
| `app/Models/Appointment.php` | SoftDeletes + 7-value status enum — source for 7-value legend |
| `database/migrations/2025_09_20_082341_create_appointments_table.php` | 7-value status enum source |
| `database/migrations/2025_10_14_123001_fix_appointments_status_enum.php` | Status enum alignment (no_show / rescheduled) |
| `database/migrations/2026_06_02_173228_fix_appointments_timezone_offset.php` | Timezone correctness — contract preserved verbatim |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | `useConsultation` contract guard |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Pin the `canvasRoutes` array literal (includes `/calendar` + `/appointment-types`) |
| `CREDENTIALS.md` | `recep@test.com` for calendar + modal; `admin@test.com` for appointment-types CRUD |

### 11.2 Standing guard rails (inherited from the global proposal)

This proposal does NOT relax any of:

1. `tokens.js` is the only source of truth for tokens.
2. `systemBackground` (`#ffffff`) is pinned; canvas = `#F2F2F7`.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings: var(--font-features-tabular-nums)`, NOT literal `tabular-nums` utility name.
7. `<script>` blocks of every CITAS module are NEVER edited in any PR.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only; NEVER npm/yarn.
10. Code in English; conversation in Spanish (Peru).

### 11.3 Process invariant (forwarded from the vertical-slice archive-report)

The archive-report at lines 47–57 names three defects that all shared one root cause: **a test that pins an example instead of the rule**. CITAS's standing posture is to assert rules, not literals:

- `CalendarAppShellTest`, `AppointmentTypesAppShellTest` extend `ModuleAppShellTestCase` — they assert the rule (`--color-canvas` reference exists, `border-theme` absent, `<style scoped>` absent), not a literal string.
- `LegacyAliasForbiddenTest` (global PR0) pins the list of forbidden patterns, not a single example.
- `ConsultationWizardStatusEnumTest` asserts the rule (all 7 enum values are referenced in the legend template), not the literal output of one example.
- `AppointmentPriceFormatterTest` asserts the rule (`formatCurrency` exists at exactly one location, imported from `useFormatters.js`), not the literal output of one example.

---

## 12. What This Proposal Does NOT Do

- Does NOT redesign any CITAS surface — it ROLLOUTS the proven language.
- Does NOT add new tokens, primitives, or components (the full PR0 / Domain 2 set is inherited).
- Does NOT add dark mode.
- Does NOT add gradients anywhere.
- Does NOT touch the backend (no controller, no service, no listener, no migration, no job).
- Does NOT relax any standing guard rail from §11.2.
- Does NOT introduce `<style scoped>` blocks (or carry them as grandfathered).
- Does NOT touch `<script>` blocks in any CITAS module — UI changes are template-level only.
- Does NOT change `useConsultation` reactivity contract, debounce, or step navigation logic.
- Does NOT change `useEcho` `appointments` channel subscription (`.listen(...)` + `echo.leave(...)` stay verbatim).
- Does NOT alter `AppointmentService::createAppointment` conflict detection (the 3-axis `findConflicts` stays the only oracle).
- Does NOT alter the timezone contract (no JS-side `toISOString()` on `datetime-local`).
- Does NOT touch `CalendarService::getCalendarData` `textColor: '#ffffff'` (existing a11y defect; flagged for future slice).
- Does NOT add `role="grid"` + per-cell `aria-label` to the calendar grid (a11y follow-up; out of scope).
- Does NOT add `WorkSchedule` / `AppointmentBlock` / `WaitingList` admin frontend.
- Does NOT add a "edit recurring" feature (`AppointmentRecurrence` is single-edit semantics only).
- Does NOT expose `ConfirmationToken` to non-admin viewers.
- Does NOT introduce UX that suggests `WorkSchedule` / `AppointmentBlock` are enforced (they are commented out in `AppointmentService`).
- Does NOT alter the 7-value status enum semantics (only the legend rendering of all 7 values is the load-bearing fix).

---

*End of CITAS proposal.*
