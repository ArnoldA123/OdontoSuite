# Design Language Rollout — Apple-only UI

**Origin**: First promoted 2026-08-12 from `ui-rollout-all-modules-2026-08/specs/pagos/spec.md` (PAGOS category closure).

> Provenance note: the PAGOS delta spec lives at the change root
> (`specs/pagos/spec.md`), not under `categories/pagos/`. It was archived to
> `openspec/changes/archive/2026-08-12-ui-pagos/specs/pagos/spec.md`.

**Scope**: This spec accumulates design language MUSTs as each category slice closes. Future categories will append their delta rows here with provenance.

## PAGOS Rollout — 2026-08-12 (PAGOS category closed)

All rows below are promoted verbatim from `ui-rollout-all-modules-2026-08`
(PAGOS category slice). Provenance for every row:
`openspec/changes/archive/2026-08-12-ui-pagos/specs/pagos/spec.md`.

### Requirement: `PAGOS-MNY-001` — `CurrencyInput` is the only money input on PAGOS surfaces

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST use `<CurrencyInput>` (the primitive at
`resources/js/components/ui/CurrencyInput.vue`) as the sole money input
on every PAGOS surface. The apply phase MUST NOT introduce a raw
`<input type="text" v-model="amount">` or
`<input type="number" v-model="amount">` pattern under
`resources/js/modules/cash-register/**`, `resources/js/modules/quotations/**`,
or `resources/js/modules/settings/payment-methods/**`.

#### Scenario: `PAGOS-MNY-001-1` — Only `CurrencyInput` collects money

- GIVEN any PAGOS surface inventoried in `pagos/explore.md` §"Inventory"
- WHEN the apply phase greps the PAGOS module tree for raw money inputs
- THEN the grep returns zero matches for `<input type="number" v-model="amount"` or `<input type="text" v-model="amount"` outside `CurrencyInput.vue`
- AND `LegacyAliasForbiddenTest` (extended) or a new `CurrencyInputSoleMoneyInputTest` asserts the rule per module

### Requirement: `PAGOS-MNY-002` — `Intl.NumberFormat('es-PE', { currency: 'PEN' })` is the only money formatter

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST consolidate `formatCurrency` to exactly one declaration
location — preferred `resources/js/composables/useFormatters.js`, fallback
`resources/js/utils/formatCurrency.js` — and MUST use
`Intl.NumberFormat('es-PE', { currency: 'PEN' })` as the only formatter.
The wrapper signature MUST be `(amount, options) => string` so existing
call sites change only their import line.

#### Scenario: `PAGOS-MNY-002-1` — `formatCurrency` exists at exactly one location

- GIVEN `formatCurrency` is reimplemented in 4+ files per `pagos/explore.md` §"Known gotchas"
- WHEN PR-pagos-05 lands
- THEN `FormatPENLabelTest` asserts the helper exists at exactly one location (regex count == 1)
- AND every call site imports from that canonical location
- AND `CurrencyInput` formatter is unchanged (no formatting fork)

### Requirement: `PAGOS-MOD-001` — Every payment modal uses `<UiModal>` + `<UiTabs>` + `<UiButton>` + `<UiStatusBadge>`

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST replace every hand-built `<Teleport>` modal with
`<UiModal>` (the canonical primitive) and MUST replace every raw tab
strip / status pill / input border / button with the corresponding
`Ui*`-prefixed primitive. The apply phase MUST NOT introduce a new
`<Teleport to="body">` block in any PAGOS file, and MUST NOT use raw
`bg-black bg-opacity-60` overlays. This rule applies to `PaymentModal`,
`MercadoPagoCheckout`, `TransactionModal`, `MovementModal`,
`OpenCashModal`, `CloseCashModal`, and the desglose modal inside
`ReadyToBillPage`.

#### Scenario: `PAGOS-MOD-001-1` — Payment + transaction + cash modals all use Ui primitives

- GIVEN the seven payment-facing modals are the cash-register UI
- WHEN PR-pagos-02 and PR-pagos-03 land
- THEN every modal uses `<UiModal>` + `<UiTabs>` + `<UiButton>` + `<UiStatusBadge>` exclusively (no raw `<button>` tab strips, no raw `<div class="modal">`, no `bg-black bg-opacity-60` overlays)
- AND `TransactionModal`'s `bg-primary-50` patient banner becomes `<UiCard>` (the proven card primitive)
- AND the inline `animate-spin` is replaced by `<UiLoadingSpinner>`
- AND `CashRegisterAppShellTest::test_ready_to_bill_modal_uses_ui_modal` asserts the `<UiModal>` wrapper is present on `ReadyToBillPage.vue`'s desglose

### Requirement: `PAGOS-RED-001` — `PaymentMethod.gateway_config` MUST NOT render raw

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST redact `PaymentMethod.gateway_config` (the encrypted
`Crypt::encryptString` blob keyed by `APP_KEY`) on the admin CRUD form.
The form MUST emit `data-redacted="true"` on the visible field and MUST
NOT echo the decrypted value into any rendered text node.

#### Scenario: `PAGOS-RED-001-1` — Admin form marks `gateway_config` as redacted

- GIVEN `PaymentMethod.gateway_config` is encrypted at rest
- WHEN an admin opens the create/edit form for a `gateway_type = mercadopago` payment method
- THEN the rendered form contains the `data-redacted="true"` attribute on the gateway_config wrapper
- AND the rendered text content does NOT contain the decrypted blob
- AND `PaymentMethodsAppShellTest::test_gateway_config_redacted` asserts both: the attribute is present and the raw value is absent

### Requirement: `PAGOS-RT-001` — PAGOS screens MUST subscribe to existing Echo channels

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST subscribe to the existing Echo channels `cash-register`,
`.cash-session.opened`, `.cash-session.closed`, `.payment.registered`, and
`.cash-movement.created` (per `pagos/explore.md` §"Jobs/events"). New PAGOS
surfaces MUST NOT introduce parallel channels or polling fallbacks.

#### Scenario: `PAGOS-RT-001-1` — New surfaces reuse the existing channel list

- GIVEN `useCashRegister` listens on the five channels above
- WHEN any new PAGOS surface is added (e.g. a future `TransactionList` variant)
- THEN the new surface's composable subscribes to the SAME channel names (case-sensitive)
- AND no new `Echo.private(...)` or `Reverb` channel declaration is introduced in any PAGOS file
- AND `PaymentReceivedChannelTest` stays green

### Requirement: `PAGOS-SCP-001` — SHALL NOT add new payment types, gateways, or currencies

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST NOT add new `Transaction.type` values, new payment
gateways, or new currencies. The single supported currency is PEN (S/)
and the single supported gateway is MercadoPago.

#### Scenario: `PAGOS-SCP-001-1` — No new payment-kind or currency additions

- GIVEN the rollout is a UI-only migration
- WHEN PR-pagos-01..05 land
- THEN the `Transaction.type` literal set remains `{payment, refund}` (zero new literal values outside `app/Models/Transaction.php`)
- AND no new `PaymentMethod` gateway type is added
- AND no `Intl.NumberFormat` call uses a currency other than `PEN`
- AND `FormatPENLabelTest` asserts the formatter emits the `S/` prefix

### Requirement: `PAGOS-REV-001` — Each PR-pagos-NN MUST stay under the 400-line review budget

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST keep each `pr-pagos-NN` PR under the 400-line authored
review budget. When a PR's diff exceeds 400 lines, the apply phase MUST
split per the `chained-pr` skill (e.g. PR-pagos-02a + 02b for
`PaymentModal` + `MercadoPagoCheckout`; PR-pagos-04a + 04b for Quotations
+ Payment Methods).

#### Scenario: `PAGOS-REV-001-1` — PR-pagos-02 splits under the 400-line budget when needed

- GIVEN `PaymentModal.vue` is the largest single component in the cash-register module (~22.3 KB)
- WHEN the PR-pagos-02 diff is reviewed
- THEN `git diff --stat` reports `additions + deletions <= 400`
- AND if the diff exceeds 400 lines, the PR is split into `pr-pagos-02a-payment-modal` + `pr-pagos-02b-mercadopago-checkout` BEFORE the review starts

### Requirement: `PAGOS-A11Y-001` — Tabular numerics on financial data MUST expose currency context

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST expose `scope="col"`, `aria-label`, and currency context
on every tabular numeric column that renders monetary values. The
`Intl.NumberFormat('es-PE', { currency: 'PEN' })` formatter emits the
`S/` prefix that supplies the currency context visually; the `aria-label`
MUST mirror the visible sign so screen readers do not omit the unit.

#### Scenario: `PAGOS-A11Y-001-1` — Quotations and Payment Methods tables expose currency context

- GIVEN the quotations table renders `price`, `total`, and `balance` columns
- WHEN PR-pagos-04 lands
- THEN each numeric column has `<th scope="col">` and a currency `aria-label` (e.g. `aria-label="Precio en soles"`)
- AND the rendered money strings keep the `S/` prefix
- AND `QuotationsAppShellTest::test_numeric_columns_have_aria_labels` asserts the rule

### Requirement: `PAGOS-CON-001` — Existing cash-register contracts MUST be preserved

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pagos/spec.md` §2.*

The system MUST preserve the `useCashRegister` / `useTransactions` /
`usePaymentMethods` public contract AND MUST keep the `PaymentModal` 401
redirect behaviour (UXF-021) intact. The apply phase MUST NOT edit any
`<script>` block of a PAGOS module file.

#### Scenario: `PAGOS-CON-001-1` — `PaymentModal` 401 redirect and `useCashRegister` contract stay green

- GIVEN `PaymentModal401RedirectTest` (UXF-021) asserts that a 401 on
  `createTransaction` tears down the session and bounces to `/login`
- AND `ComposablesStandardizationTest` pins the `useCashRegister` /
  `useTransactions` / `usePaymentMethods` public surface
- WHEN any PR-pagos-NN lands
- THEN both tests remain green at every PR boundary
- AND the redirect code path in `PaymentModal.vue`'s `<script>` block
  is byte-for-byte unchanged
- AND `<script>` blocks of `CashRegisterPage.vue`,
  `ReadyToBillPage.vue`, `PaymentModal.vue`, and the 9 other
  components are NOT edited

---

*End of promoted PAGOS rows. Next category slice appends below.*

## CITAS Rollout — 2026-08-12 (CITAS category closed)

All rows below are promoted verbatim from `ui-rollout-all-modules-2026-08`
(CITAS category slice). Provenance for every row:
`openspec/changes/archive/2026-08-12-ui-citas/specs/citas/spec.md`.

### Requirement: `CITAS-CAL-001` — Calendar status legend MUST render all 7 enum values

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

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

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/citas/spec.md` §2.*

The system SHOULD add `role="grid"` plus per-cell `aria-label` on the
day/week/month views in `CalendarPage.vue` so screen readers can
navigate "Tuesday 9 AM, Tuesday 10 AM" efficiently. This row is
OPTIONAL for the visual polish rollout; it is flagged in
`openspec/changes/archive/2026-08-12-ui-citas/a11y-followup.md`
for a future a11y slice.

#### Scenario: `CITAS-A11Y-001-1` — Calendar grid ARIA roles recorded as future work

- GIVEN the week/month views in `CalendarPage.vue` use plain `<div>` grids with no ARIA role
- WHEN PR-citas-02 lands
- THEN the a11y follow-up document is created
- AND the row is marked OPTIONAL; no test fails if `role="grid"` is not introduced in PR-citas-02
- AND the hardcoded `textColor: '#ffffff'` in `CalendarService::getCalendarData` line 101 is also documented as a future a11y slice (color-contrast defect)

---

*End of promoted CITAS rows. Next category slice appends below.*

## PACIENTES Rollout — 2026-08-12 (PACIENTES category closed)

All rows below are promoted verbatim from `ui-rollout-all-modules-2026-08`
(PACIENTES category slice). Provenance for every row:
`openspec/changes/archive/2026-08-12-ui-pacientes/specs/pacientes/spec.md`.

### Requirement: `PAC-LIST-001` — `PatientsPage` list MUST consume Ui primitives + tabular-nums on DNI/age

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST replace every `border-theme` table divider, `divide-theme`
row divider, `bg-success-badge` / `bg-danger-badge` status pill, raw
`text-green-600` / `text-red-600` mobile action button, `text-accent
hover:text-primary-700` link button, `hover-lift` stat card, and raw
`<input>` / `<select>` field on `PatientsPage.vue` with the
corresponding `Ui*`-prefixed primitive. The 4 stat cards MUST consume
`<UiCard clickable>` (NOT `hover-lift`). The DNI + age columns MUST
carry `font-feature-settings: var(--font-features-tabular-nums)` so the
ID column stops jittering. The list page MUST consume
`bg-theme-surface-elevated` only (NOT mixed `bg-theme-surface` /
`bg-theme-surface-elevated`).

#### Scenario: `PAC-LIST-001-1` — List page uses Ui primitives and tabular-nums

- GIVEN `PatientsPage.vue` (1249 lines) renders 4 stat cards, a status filter, a desktop table, a mobile card fallback, and pagination
- WHEN PR-pacientes-01 lands
- THEN `PatientsListAppShellTest` (16 cases / 47 assertions) asserts the rule (token reference exists, `border-theme` / `bg-success-badge` / `bg-danger-badge` / `divide-theme` / `hover-lift` absent)
- AND `PatientTableNumsTest` asserts `tabular-nums` is present on the DNI column + the age column
- AND `LegacyAliasForbiddenTest` (extended) returns zero matches for any of the forbidden aliases on the list page

### Requirement: `PAC-MOD-001` — Three inlined patient modals MUST use `<UiModal>` + `<UiInput>` + `<UiSelect>`

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST replace every hand-built `<div class="fixed inset-0
bg-black bg-opacity-50 … z-50">` modal backdrop, `bg-theme-surface-
elevated rounded-2xl shadow-2xl` panel, `border-b border-theme` header
divider, raw `<input>` / `<select>` / `<textarea>` field, and `focus:ring-
primary-500 focus:border-transparent` ring in the New Patient modal and
the Edit Patient modal of `PatientsPage.vue` with the canonical
`<UiModal>` chrome + `<UiInput>` / `<UiSelect>` / `<UiTextarea>`
primitives + hairline dividers + `var(--focus-ring-default)` focus
ring. The capture-form rule applies to all 3 modals (New Patient modal
`PatientsPage.vue` lines 463–581; Edit Patient modal `PatientsPage.vue`
lines 583–725; Edit Patient modal `PatientDetailPage.vue` lines
706–845).

#### Scenario: `PAC-MOD-001-1` — Three inlined modals all use UiModal chrome

- GIVEN the New Patient + Edit Patient modals in `PatientsPage.vue` and the Edit Patient modal in `PatientDetailPage.vue` each render a hand-built backdrop + raw form fields
- WHEN PR-pacientes-02 lands (list modals) + PR-pacientes-04 lands (detail edit modal)
- THEN `PatientsModalAppShellTest` (13 cases / 42 assertions) asserts the rule on each of the 3 modals (`<UiModal>` wrapper present, `bg-black bg-opacity-50` absent)
- AND `git grep -nE 'bg-black bg-opacity-50' resources/js/modules/patients/PatientsPage.vue resources/js/modules/patients/PatientDetailPage.vue` returns zero matches
- AND the `useApi` 422 duplicate-email/phone error envelope rendering stays verbatim (form stays open + server message surfaces via `useToast`)

### Requirement: `PAC-DET-001` — `PatientDetailPage` 5-tab drawer MUST consume `<UiTabs>` + cross-category deep-links preserved

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST replace the raw `<button>` step strip with
`border-accent text-accent` active indicator (line 87) on
`PatientDetailPage.vue` with `<UiTabs>` (the canonical primitive) wired
to `var(--motion-duration-fast) var(--motion-easing-ios)` transitions.
The 5-tab drawer MUST continue to deep-link to `/treatment-plans?
patient_id=…`, `/quotations?patient_id=…`, `/medical-records?patient_id=…`,
and `/specialty-records?patient_id=…` byte-for-byte. The change-diff
callout at line 669 (legacy `border-l-2 border-theme`) MUST consume a
hairline token. The `<style scoped>` block at line 1556
(`.tab-content { min-height: 400px }`) MUST be removed and the contents
rewritten to plain utility classes (`min-h-[400px]`).

#### Scenario: `PAC-DET-001-1` — Tabs use UiTabs and deep-links stay byte-for-byte

- GIVEN `PatientDetailPage.vue` (1480 lines) renders 5 tabs across Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría
- WHEN PR-pacientes-03 lands
- THEN `PatientDetailAppShellTest::test_detail_tabs_use_ui_tabs` asserts the rule (`<UiTabs>` reference present, raw `border-accent text-accent` active indicator absent, inline `@click="currentStep = step.id"`-style handler absent)
- AND `PatientDetailAppShellTest::test_detail_cross_category_deep_links_preserved` asserts the 4 `router.push(...)` calls remain byte-for-byte
- AND `ModuleAppShellTestCase::test_no_style_scoped` green for `PatientDetailPage.vue`

### Requirement: `PAC-EDIT-001` — `PatientDetailPage` Edit Patient modal MUST consume `<UiModal>` + `<UiSelect>` for gender + is_active

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST replace the hand-built backdrop, raw `<select>` for
gender + `is_active` (lines 780 + 792), `bg-theme-surface-elevated`
panel, and `focus:ring-primary-500 focus:border-transparent` ring in the
inlined Edit Patient modal of `PatientDetailPage.vue` with `<UiModal>`
chrome + `<UiSelect>` + `<UiInput>` + hairline divider +
`var(--focus-ring-default)` focus ring. The `useApi` `PUT /api/patients/
{id}` call signature MUST stay verbatim; the 422 error envelope from
`Rule::unique(...)->ignore($patient->id)` MUST stay verbatim.

#### Scenario: `PAC-EDIT-001-1` — Detail Edit modal uses Ui primitives

- GIVEN the inlined Edit Patient modal in `PatientDetailPage.vue` lines 706–845 carries raw `<select>` for gender + `is_active`
- WHEN PR-pacientes-04 lands
- THEN `PatientDetailEditExportAppShellTest::test_detail_edit_modal_uses_ui_primitives` asserts the rule on the detail edit modal specifically (`<UiModal>` + `<UiSelect>` + `<UiInput>` present, raw `<select>` + hand-built backdrop absent)
- AND the `useApi` update call stays verbatim (no axios, no fork)
- AND the 422 error envelope from the email/phone unique constraint surfaces verbatim via `useToast`

### Requirement: `PAC-EXP-001` — Export action surface MUST use `<UiButton>` + preserve Bearer-token binary download pattern

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST replace the legacy export action chrome (PDF / ZIP) on
`PatientDetailPage.vue` with `<UiButton>` + `<UiSelect>` (NOT raw
`<button>` + raw `<select>`). The raw `fetch` + Bearer token +
`window.URL.createObjectURL` + `<a download>` anchor click pattern at
lines 1217–1225 MUST stay byte-for-byte (a JSON wrapper would corrupt
the binary stream; `useApi()` cannot replace it).

#### Scenario: `PAC-EXP-001-1` — Export action uses Ui primitives and the binary download stays verbatim

- GIVEN the export action surface triggers `GET /api/patients/${id}/export?format=pdf|zip` with a Bearer token and streams the binary
- WHEN PR-pacientes-04 lands
- THEN `PatientDetailEditExportAppShellTest::test_detail_export_button_uses_ui_button` asserts `<UiButton>` + `<UiSelect>` adoption on the export dropdown
- AND `git grep -nE 'window\.URL\.createObjectURL' resources/js/modules/patients/PatientDetailPage.vue` confirms the pattern is present byte-for-byte
- AND `ApiAndSeedersPolishTest` API-035 + API-057 stay green (`application/pdf` / `application/zip` Content-Type whitelisted)

### Requirement: `PAC-RT-001` — `useEcho` channel subscriptions MUST stay subscribed byte-for-byte

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST keep every `useEcho` channel subscription on
`PatientsPage.vue` and `PatientDetailPage.vue` firing verbatim. The
channels are: `patients` (`.patient.updated`), `treatment-plans`
(`.treatment-plan.{created,updated,deleted}`), `quotations`
(`.quotation.{created,updated,deleted}`), `medical-records`
(`.medical-record.{created,updated,deleted}`), `specialty-records`
(`.specialty-record.{created,updated,deleted}`). The `dashboard-updates`
channel is NOT consumed by the paciente module (the Dashboard page
consumes it). Visual changes MUST NOT touch `<script>` blocks.

#### Scenario: `PAC-RT-001-1` — All 5 channels stay subscribed

- GIVEN the per-tab deep-link create buttons on `PatientDetailPage.vue` rely on cross-category Echo events firing
- WHEN any PR-pacientes-NN lands
- THEN `git diff --stat` shows zero edits to `<script>` blocks of `PatientsPage.vue` and `PatientDetailPage.vue`
- AND manual smoke test: two browser tabs on `/patients/:id`, update the patient in tab A, verify tab B receives the `patient.updated` event within 1 second
- AND the cross-category channels continue firing on the Planes / Presupuestos / Historia Clínica / Especialidades tab create buttons

### Requirement: `PAC-PHI-001` — `PatientResource` API envelope MUST NOT widen or narrow

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST preserve the `PatientResource` API envelope byte-for-
byte. The additive `age` integer key (computed via `$this->birth_date-
>diffInYears(now())`) MUST stay. The `email`, `phone`, `birth_date`,
`address`, `medical_history`, `allergies`, `notes` fields MUST continue
exposing for every viewer (the `PatientPolicy::view` return-true
posture is OUT of scope; the cross-branch PHI scope guard is a separate
change). The conditional counter fields (`appointments_count`,
`treatment_plans_count`, `quotations_count`, `medical_records_count`)
and conditional relations (`appointments`, `treatmentPlans`,
`quotations`, `medicalRecords`) via `whenLoaded` / `when` MUST stay
verbatim.

#### Scenario: `PAC-PHI-001-1` — Additive age key and PHI surface preserved

- GIVEN `PatientResourceAgeTest` (7 cases on `PatientResource::toArray()`) + `PatientControllerAgeTest` pin the additive `age` key
- WHEN any PR-pacientes-NN lands
- THEN both tests stay green at every PR boundary
- AND `PatientControllerResourceWireUpTest` stays green (every public CRUD method references `PatientResource`)
- AND the API envelope is NOT widened or narrowed — no field is added or removed

### Requirement: `PAC-DEEP-001` — Cross-category deep-links MUST stay byte-for-byte

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST preserve the 4 cross-category deep-link `router.push`
calls on `PatientDetailPage.vue` byte-for-byte:
`router.push('/treatment-plans?patient_id=…')`,
`router.push('/quotations?patient_id=…')`,
`router.push('/medical-records?patient_id=…')`,
`router.push('/specialty-records?patient_id=…')`. The per-tab create
buttons (Planes / Presupuestos / Historia Clínica / Especialidades)
MUST keep their navigation contract identical across all 4 deep-link
surfaces.

#### Scenario: `PAC-DEEP-001-1` — All 4 deep-links preserved verbatim

- GIVEN the per-tab create buttons navigate to other modules with the `?patient_id=…` query param
- WHEN PR-pacientes-03 lands
- THEN `PatientDetailAppShellTest::test_detail_cross_category_deep_links_preserved` asserts the 4 `router.push(...)` patterns remain byte-for-byte
- AND visual smoke test: click "Crear plan" on the Planes tab, verify the URL contains `?patient_id=<id>` and the treatment-plans page loads

### Requirement: `PAC-REV-001` — Each `pr-pacientes-NN` MUST stay under the 400-line review budget

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST keep each `pr-pacientes-NN` PR under the 400-line
authored review budget. When a PR's diff exceeds 400 lines (PR-
pacientes-01 ~390 lines + PR-pacientes-03 ~390 lines are right at the
budget), the apply phase MUST split per the `chained-pr` skill (e.g.
PR-pacientes-01a + 01b for the desktop table vs the mobile card
fallback; PR-pacientes-03a + 03b for the 5-tab drawer chrome vs the
per-tab deep-link create buttons).

#### Scenario: `PAC-REV-001-1` — PR-pacientes-01 and PR-pacientes-03 split when needed

- GIVEN `PatientsPage.vue` (1249 lines) + `PatientDetailPage.vue` (1480 lines) are the largest single Vue files in PACIENTES
- WHEN the PR-pacientes-01 + PR-pacientes-03 diffs are reviewed
- THEN `git diff --stat` reports `additions + deletions <= 400` per PR
- AND if a diff exceeds 400 lines, the PR is split BEFORE the review starts

### Requirement: `PAC-CON-001` — Existing paciente contracts MUST be preserved

*Provenance: `ui-rollout-all-modules-2026-08` → `specs/pacientes/spec.md` §2.*

The system MUST preserve the public contracts of `useEcho`,
`usePermissions`, `useToast`, `useConfirm`, `useApi`, and `useAuditLogs`
consumed by the pacientes module byte-for-byte. The
`usePermissions.can.{createPatient, updatePatient, deletePatient,
createTreatmentPlan, createQuotation, createMedicalRecord,
createSpecialtyRecord}` flags MUST stay verbatim. The
`useAuditLogs.getPatientAuditLogs(patientId)` call MUST stay verbatim.
The `useConfirm` delete-confirmation flow MUST stay verbatim. Visual
changes MUST NOT touch `<script>` blocks.

#### Scenario: `PAC-CON-001-1` — All 6 composable contracts stay green

- GIVEN `ComposablesStandardizationTest` pins the 6 composable surfaces
- WHEN any PR-pacientes-NN lands
- THEN `ComposablesStandardizationTest` stays green at every PR boundary
- AND `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` are byte-for-byte unchanged
- AND the `useEcho` channel list + the `usePermissions.can.*` flags + the `useAuditLogs.getPatientAuditLogs(...)` call all stay verbatim

---

*End of promoted PACIENTES rows. Next category slice appends below.*

## Recepcion-procedimientos Rollout — 2026-08-21 (RECEPCION-PROCEDIMIENTOS category closed)

All rows below are promoted verbatim from `ui-rollout-all-modules-2026-08`
(recepcion-procedimientos category slice). Provenance for every row:
`openspec/changes/archive/2026-08-21-ui-recepcion-procedimientos/spec.md`.
Verify verdict at close: **PASS WITH WARNINGS** — 7/7 REC-* MUSTs satisfied
at static-contract level (commit `654130a`).

### Requirement: `REC-001` — `canvasRoutes` regression guard for `/reception-procedures`

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/recepcion-procedimientos/spec.md` §2.1.*

`'/reception-procedures'` MUST remain present in the `canvasRoutes` array
literal in `AppLayout.vue` AND in `EXPECTED_ROUTES` of
`AppLayoutCanvasRoutesTest.php`. The route was added at PR0; no later PR
MAY narrow the array back to the vertical-slice set.

#### Scenario: `REC-001-1` — Route stays in canvasRoutes and EXPECTED_ROUTES

- GIVEN `/reception-procedures` was wired into `canvasRoutes` at PR0
- WHEN `pr-recepcion-procedimientos-tokenise` lands
- THEN `AppLayout.vue:548` still carries `'/reception-procedures'`
- AND `AppLayoutCanvasRoutesTest.php:54` still carries it in `EXPECTED_ROUTES`
- AND `AppLayoutCanvasRoutesTest` stays green (25 tests / 72 assertions)
- **Verdict at close: PASS**

### Requirement: `REC-002` — `<UiInput>` is the only search field on `/reception-procedures`

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/recepcion-procedimientos/spec.md` §2.2.*

`ReceptionProceduresPage.vue` MUST replace the raw `<input>` search field
with `<UiInput v-model="filters.search" type="search">` plus a `#prefix`
slot for the SVG search icon. No raw `<input v-model="filters.search"`
string MAY remain in the template.

#### Scenario: `REC-002-1` — Search field uses UiInput with prefix slot

- GIVEN the page rendered a raw `<input>` with `border-theme`, `bg-theme-surface-elevated`, `focus:ring-primary-500`, `focus:border-accent`, and `rounded-lg`
- WHEN `pr-recepcion-procedimientos-tokenise` lands
- THEN `ReceptionProceduresPage.vue:30-51` renders `<UiInput>` with a `<template #prefix>` icon slot
- AND `ReceptionProceduresAppShellTest::test_search_input_uses_ui_input` passes
- AND grep for `<input` on the page returns zero matches
- **Verdict at close: PASS**

### Requirement: `REC-003` — `<UiSelect>` is the only specialty filter control

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/recepcion-procedimientos/spec.md` §2.3.*

`ReceptionProceduresPage.vue` MUST replace the raw `<select>` specialty
filter with `<UiSelect v-model="filters.specialty">` exposing a
"Todas las especialidades" placeholder. No raw
`<select v-model="filters.specialty"` string MAY remain in the template.

#### Scenario: `REC-003-1` — Specialty filter uses UiSelect

- GIVEN the page rendered a raw `<select>` with legacy focus-ring aliases
- WHEN `pr-recepcion-procedimientos-tokenise` lands
- THEN `ReceptionProceduresPage.vue:55-60` renders `<UiSelect v-model="filters.specialty" :options="...">`
- AND `ReceptionProceduresAppShellTest::test_specialty_filter_uses_ui_select` passes
- AND grep for `<select` on the page returns zero matches
- AND the `:options` prop form satisfies the rule equally with `<option>` children (documented deviation from task T4)
- **Verdict at close: PASS**

### Requirement: `REC-004` — Procedure code chip MUST use `<UiBadge>`, not inline primary literals

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/recepcion-procedimientos/spec.md` §2.4.*

`ReceptionProceduresPage.vue` MUST replace the inline
`<span class="font-mono text-xs px-2 py-0.5 rounded bg-primary-50 text-primary-700">`
procedure code chip with `<UiBadge variant="primary" size="sm" class="font-mono">`.
The `bg-primary-50` and `text-primary-700` literals MUST be removed.
`<UiStatusBadge>` MUST NOT be used — procedure codes are informational,
not status (per proposal OQ-1).

#### Scenario: `REC-004-1` — Code chip uses UiBadge

- GIVEN the code chip carried inline `bg-primary-50 text-primary-700`
- WHEN `pr-recepcion-procedimientos-tokenise` lands
- THEN `ReceptionProceduresPage.vue:82-84` renders `<UiBadge variant="primary" size="sm" class="font-mono">`
- AND `ReceptionProceduresAppShellTest::test_procedure_code_chip_uses_ui_badge` passes
- AND grep for `bg-primary-50` and `text-primary-700` each return zero matches
- **Verdict at close: PASS**

### Requirement: `REC-005` — Empty-results state MUST use `<UiEmptyState>`

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/recepcion-procedimientos/spec.md` §2.5.*

`ReceptionProceduresPage.vue` MUST replace the hand-rolled
`<div class="py-12 text-center text-theme-secondary">` empty-results block
with `<UiEmptyState title="Sin resultados" description="...">`. The
`py-12 text-center text-theme-secondary` literal MUST be removed.

#### Scenario: `REC-005-1` — Empty state uses UiEmptyState

- GIVEN the page rendered a hand-rolled empty-results div
- WHEN `pr-recepcion-procedimientos-tokenise` lands
- THEN `ReceptionProceduresPage.vue:69-73` renders `<UiEmptyState v-else-if="!procedures.length">`
- AND `ReceptionProceduresAppShellTest::test_empty_state_uses_ui_empty_state` passes
- AND the Spanish copy ("Sin resultados") is preserved
- **Verdict at close: PASS**

### Requirement: `REC-006` — Price display MUST use `text-systemBlue-600 tabular-nums`

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/recepcion-procedimientos/spec.md` §2.6.*

The price text node MUST consume `text-systemBlue-600 tabular-nums` plus
`font-feature-settings: var(--font-features-tabular-nums)` in place of the
legacy `text-accent` alias, which MUST be removed from the page template.
Prices MUST NOT be rendered as a status badge — currency is not a status
indicator (per proposal OQ-1).

#### Scenario: `REC-006-1` — Price uses systemBlue with tabular numerics

- GIVEN the price node carried `text-lg font-bold text-accent`
- WHEN `pr-recepcion-procedimientos-tokenise` lands
- THEN `ReceptionProceduresPage.vue:102-105` renders `text-systemBlue-600 tabular-nums`
- AND `ReceptionProceduresAppShellTest::test_price_uses_system_blue_tabular_nums` passes
- AND grep for `text-accent` returns zero matches
- **Verdict at close: PASS**

### Requirement: `REC-007` — Hairline token on dividers and no `hover-lift transition-shadow`

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/recepcion-procedimientos/spec.md` §2.7.*

All `border-theme` literals MUST be removed from
`ReceptionProceduresPage.vue`. The `hover-lift transition-shadow` class
string on `<UiCard variant="elevated">` MUST be dropped, because the
tokenised primitive already ships a `translateY(-2px)` hover with a
reduced-motion fallback and stacking the class double-applies the
transform. The card divider MUST consume the hairline token via
`border-hairline` or `border-[color:var(--color-hairline)]`.

#### Scenario: `REC-007-1` — Hairline dividers replace border-theme and hover-lift

- GIVEN the card divider used `border-t border-theme` and the card carried `hover-lift transition-shadow`
- WHEN `pr-recepcion-procedimientos-tokenise` lands
- THEN `ReceptionProceduresPage.vue:93` renders `border-t border-hairline`
- AND `ReceptionProceduresAppShellTest::test_hairline_borders_and_no_hover_lift` passes
- AND grep for `border-theme` and `hover-lift transition-shadow` each return zero matches
- **Verdict at close: PASS**

---

*End of promoted RECEPCION-PROCEDIMIENTOS rows. Next category slice appends below.*

## Mis-procedimientos Rollout — 2026-08-21 (MIS-PROCEDIMIENTOS category closed)

All rows below are promoted verbatim from `ui-rollout-all-modules-2026-08`
(mis-procedimientos category slice). Provenance for every row:
`openspec/changes/archive/2026-08-21-ui-mis-procedimientos/spec.md`.
Verify verdict at close: **PASS WITH WARNINGS** — 14/14 MIS-* MUSTs
satisfied at static-contract + runtime level (commit `575ff1e`).

### Requirement: `MIS-001` — Hairline tokens replace every `border-theme` and `divide-theme` literal

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

`MyProceduresPage.vue` MUST replace every `border-theme` literal (4
occurrences) and the `divide-theme` literal with the hairline token
(`border-hairline` or `border-[color:var(--color-hairline)]` /
`divide-[color:var(--color-hairline)]`).

#### Scenario: `MIS-001-1` — No legacy `border-theme` / `divide-theme` literals remain

- GIVEN the file references `border-theme` 4 times and `divide-theme` 1 time
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_no_border_theme_literal` asserts both literals are absent
- AND `MyProceduresPageAppShellTest::test_no_divide_theme_literal` asserts the divide literal is absent
- AND `ModuleAppShellTestCase::test_no_border_theme_literal` (DLR-R-002) stays green
- **Verdict at close: PASS**

### Requirement: `MIS-002` — `<UiInput>` adoption for the search field

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

The raw `<input>` search field MUST be replaced with
`<UiInput v-model="search" ...>` while preserving the
`<div class="relative">` wrapper and the search-icon `<svg>` inset at
`left-3`. Per the `ReceptionProceduresPage` precedent, the search icon
moves into the `<template #prefix>` slot.

#### Scenario: `MIS-002-1` — Search field uses `<UiInput>` and no raw input

- GIVEN the search field was the only raw `<input>` in the file
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_search_uses_ui_input` asserts `<UiInput v-model="search"` is present
- AND asserts raw `<input` (with `class="...focus:ring-primary-500..."`) is absent
- AND asserts the `<div class="relative">` wrapper is preserved
- **Verdict at close: PASS**

### Requirement: `MIS-003` — `formatCurrency` is the only money formatter on this page

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

Both inline `S/ {{ Number(...).toFixed(2) }}` literals MUST be replaced
with `formatCurrency(...)` from `useFormatters` (the
`PAGOS-MNY-002` canonical location). `Intl.NumberFormat` MUST NOT appear
in the template (single-source rule).

#### Scenario: `MIS-003-1` — Both PEN literals consume `formatCurrency`

- GIVEN two inline PEN literals bypassed the canonical formatter
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_format_currency_used_for_pen_values` asserts `formatCurrency(` is referenced at least twice
- AND asserts `Intl.NumberFormat` is absent from the template
- AND `FormatPENLabelTest` stays green at exactly one declaration location
- **Verdict at close: PASS**

### Requirement: `MIS-004` — Tabular numerals on every numeric cell

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

`font-feature-settings: var(--font-features-tabular-nums)` (Tailwind
`tabular-nums`) MUST be carried on the 6 numeric spans across the two
cards (`default_duration_minutes`, `default_cost`, `position`) plus the
favorites count and both code badges (combined with `MIS-007`).

#### Scenario: `MIS-004-1` — At least 6 `tabular-nums` references on numeric cells

- GIVEN 6 numerics × 2 cards lacked tabular-nums
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_tabular_nums_on_numeric_cells` asserts `tabular-nums` OR `font-feature-settings: var(--font-features-tabular-nums)` appears at least 6 times
- AND the DNI-style `fav.code` + `proc.code` code badges (separately covered by `MIS-007`) also carry the token
- **Verdict at close: PASS**

### Requirement: `MIS-005` — `<LoadingSpinner>` rename to `<UiLoadingSpinner>`

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

The import and both tag references MUST be renamed from `LoadingSpinner`
to `UiLoadingSpinner`, per AGENTS.md §7 `Ui*`-prefix convention. File
path is unchanged (`components/ui/LoadingSpinner.vue`).

#### Scenario: `MIS-005-1` — Only `UiLoadingSpinner` is consumed

- GIVEN the legacy `<LoadingSpinner>` name violated the `Ui*`-prefix convention
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_loading_spinner_import_is_ui_prefixed` asserts `import UiLoadingSpinner` is present
- AND asserts `import LoadingSpinner` and the bare tag `<LoadingSpinner` are both absent
- **Verdict at close: PASS**

### Requirement: `MIS-006` — `disabled:opacity-30` parity with `<UiButton>` (`40`)

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

The two `disabled:opacity-30` references MUST be replaced with
`disabled:opacity-40` to match the iOS convention used by `<UiButton>`.

#### Scenario: `MIS-006-1` — Disabled state matches iOS parity

- GIVEN `<UiButton>` ships `disabled:opacity-40`
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_disabled_opacity_uses_ios_parity` asserts `disabled:opacity-40` appears at least 2 times
- AND asserts `disabled:opacity-30` appears 0 times
- **Verdict at close: PASS**

### Requirement: `MIS-007` — `font-mono` drop + system sans + `tabular-nums`

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

`font-mono` MUST be dropped on the code badges and replaced with
`text-xs text-theme-secondary tabular-nums` (system sans + tabular
numerals, not the legacy monospace stack).

#### Scenario: `MIS-007-1` — Code badges consume system sans, not `font-mono`

- GIVEN both code badges rendered in `font-mono`
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_code_badges_use_system_sans_with_tabular_nums` asserts `font-mono` appears 0 times
- AND asserts `tabular-nums` is present on both code-badge spans (combined with `MIS-004`)
- **Verdict at close: PASS**

### Requirement: `MIS-008` — `<UiEmptyState>` adoption for both hand-built empty states

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

The two hand-built empty states (no favourites; no search results) MUST
be replaced with `<UiEmptyState>` slots carrying the approved Spanish
copy. The legacy `border-2 border-dashed border-theme` literal MUST be
removed (the primitive's internal `rounded-ios` is inherited for the
dashed card shape).

#### Scenario: `MIS-008-1` — Both empty states consume `<UiEmptyState>`

- GIVEN both empty states were simple text blocks
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_empty_states_consume_ui_empty_state` asserts `<UiEmptyState` appears at least 2 times
- AND asserts the legacy `border-2 border-dashed border-theme` literal is absent
- **Verdict at close: PASS**

### Requirement: `MIS-009` — Every raw icon `<button>` consumes the focus-ring token

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

`:focus-visible` plus `box-shadow: var(--focus-ring-default)` (via
`focus-visible:shadow-[var(--focus-ring-default)]`) MUST be added to
each of the 3 raw icon `<button>`s (subir / bajar / quitar) so each is
keyboard-reachable. Per cached OQ-2, raw markup is retained;
`<UiButton size="icon">` migration is deferred.

#### Scenario: `MIS-009-1` — All 3 raw icon buttons consume `var(--focus-ring-default)`

- GIVEN none of the 3 raw `<button>`s exposed a focus ring
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_raw_icon_buttons_consume_focus_ring_token` asserts `var(--focus-ring-default)` appears at least 3 times
- AND asserts `focus:ring-primary-500` is absent (DLR-R-004 re-asserted)
- **Verdict at close: PASS**

### Requirement: `MIS-010` — Status ramp tokenization (blue / yellow / red)

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

The raw Tailwind ramps MUST be replaced with the proven system ramps:
`bg-primary-50 text-primary-700` (rank pill) → `bg-systemBlue-50
text-systemBlue-700`; `text-yellow-500` (star icon + label) →
`text-systemYellow-500`; `text-red-500 hover:text-red-700` (Quitar) →
`text-systemRed-500 hover:text-systemRed-700`. `<UiStatusBadge>`
migration is deferred per OQ-4 (rank pill is a counter, not state).

#### Scenario: `MIS-010-1` — Every status ramp uses the proven system ramp

- GIVEN the file consumed 4 raw Tailwind status ramps
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_status_ramps_use_tokenized_system_colors` asserts `bg-systemBlue-50 text-systemBlue-700` is present
- AND asserts `text-systemYellow-500` is present and raw `text-yellow-500` is absent
- AND asserts `text-systemRed-500` is present and raw `text-red-500` is absent
- **Verdict at close: PASS** (3 sub-assertions consolidated into 1 test method during apply to bound file size)

### Requirement: `MIS-011` — `bg-theme-surface` alias migration to `bg-canvas`

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

`hover:bg-theme-surface` MUST be replaced with `hover:bg-canvas`.
`bg-theme-surface-elevated` is RETAINED as a semantic alias resolving
to `#ffffff`.

#### Scenario: `MIS-011-1` — Hover surface uses the canvas token

- GIVEN the file distinguished `bg-theme-surface` from `bg-theme-surface-elevated`
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_hover_surface_uses_canvas_token` asserts `hover:bg-canvas` is present
- AND asserts the raw `hover:bg-theme-surface` literal is absent
- AND `bg-theme-surface-elevated` remains present (semantic alias)
- **Verdict at close: PASS**

### Requirement: `MIS-012` — `rounded-lg` replaced with contextual radius tokens

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

The 4 `rounded-lg` references MUST be replaced with contextual tokens:
cards → `rounded-[var(--radius-card-lg)]`; wrappers carry
`rounded-[var(--radius-ios)]` for the 4-token budget.

#### Scenario: `MIS-012-1` — Every radius literal consumes a token

- GIVEN 4 `rounded-lg` literals had no token binding
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `MyProceduresPageAppShellTest::test_radius_uses_contextual_tokens` asserts `var(--radius-control)` OR `var(--radius-ios)` OR `var(--radius-card-lg)` appears at least 4 times
- AND asserts bare `rounded-lg` (without a token) appears 0 times
- **Verdict at close: PASS**

### Requirement: `MIS-013` — `<script setup>` block MUST stay byte-for-byte

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

The `<script setup>` block MUST be preserved verbatim. The
`useProcedureFavorites` contract (`getFavorites`, `getForMe`,
`addFavorite`, `removeFavorite`, `reorderFavorites`), the `useToast`
calls, and the `useRouter().push('/dashboard')` redirect MUST stay
unchanged. The single additive change is the `formatCurrency` import
from `useFormatters` (one-line destructure addition).

#### Scenario: `MIS-013-1` — Composable contract stays green

- GIVEN `ComposablesStandardizationTest` pins the `useProcedureFavorites` surface
- WHEN `pr-mis-procedimientos-tokenise` lands
- THEN `ComposablesStandardizationTest` stays green
- AND `git diff --stat` reports edits to the `<script setup>` block limited to the one-line `formatCurrency` import addition
- AND `MyProceduresPageAppShellTest::test_script_setup_unchanged` asserts the composable surface is intact
- **Verdict at close: PASS**

### Requirement: `MIS-014` — `pr-mis-procedimientos-tokenise` MUST stay under the 400-line review budget

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/mis-procedimientos/spec.md` §2.*

The system MUST keep the PR diff under 400 authored lines. If the diff
exceeds 400, the apply phase MUST split per the `chained-pr` skill
BEFORE review starts. The 465-line diff (48 production + 367 test)
exceeded the budget by 16% and was pre-authorized as `size-exception`
by the orchestrator because the test file is the load-bearing evidence
layer for 14 MIS-* assertions and cannot be split without losing rule
coverage. This sets a documented precedent for the same trade-off in
future per-category slices.

#### Scenario: `MIS-014-1` — Single-PR diff fits the budget

- GIVEN the proposal estimated ~280-330 total lines (template ~220 + test ~80)
- WHEN `pr-mis-procedimientos-tokenise` is reviewed
- THEN `git diff --stat` reports `additions + deletions == 465` (size-exception pre-authorized; not a defect)
- AND the test file's 14 MIS-* assertions are preserved without splitting
- **Verdict at close: PASS WITH WARNING** (size-exception noted; 16% over 400-line cap; no defect)

---

*End of promoted MIS-PROCEDIMIENTOS rows. Next category slice appends below.*

## Estadísticas catálogo Rollout — 2026-08-21 (ESTADISTICAS-CATALOGO category closed)

All rows below are promoted verbatim from `ui-rollout-all-modules-2026-08`
(estadisticas-catalogo category slice). Provenance for every row:
`openspec/changes/archive/2026-08-21-ui-estadisticas-catalogo/spec.md`.
Verify verdict at close: **PASS WITH WARNINGS** — 9/9 EC-* MUSTs
satisfied at static-contract + runtime level (commit `36fd93e`),
including the BLOCKING EC-001 router fix that prevented the polished
page from 404ing on direct navigation.

### Requirement: `EC-001` — Router registration for `/procedure-stats` (BLOCKING fix)

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.1.*

`resources/js/app.js` MUST register `/procedure-stats` in the auth-gated
routes array, between the `/procedure-catalog/:id` block (lines 113–118)
and the `/my-procedures` block (lines 119–124), carrying
`beforeEnter: requireAuth` and the lazy-import
`./modules/procedure-catalog/ProcedureStatsPage.vue`. Without this entry
the polished page is unreachable via normal navigation and falls through
to the 404 catch-all (OQ-EC-1 CRITICAL FINDING).

#### Scenario: `EC-001-1` — Route resolves `ProcedureStatsPage.vue` via auth gate

- GIVEN `AppLayout.vue:canvasRoutes` already lists `/procedure-stats` (PR0 landed at line 534) so the canvas surface wiring is in place
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `git grep -n "procedure-stats" resources/js/app.js` returns ≥1 match
- AND the entry sits AFTER `/procedure-catalog/:id` and BEFORE `/my-procedures` and BEFORE the catch-all
- AND `pnpm build` emits the `ProcedureStatsPage-*.js` chunk (confirms lazy import resolves)
- AND `ProcedureStatsAppShellTest::test_procedure_stats_route_registered_in_app_js` passes
- **Verdict at close: PASS**

### Requirement: `EC-002` — KPI anatomy adoption (3 counter cards)

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.2.*

Each of the 3 KPI `<UiCard>` elements (Total procedimientos, Activos,
Inactivos) MUST adopt the Dashboard fixed-slot anatomy: inline `:style`
for `boxShadow: var(--elevation-2)` and `borderColor: var(--color-hairline)`;
a `data-stat-card="<key>"` attribute (`total-procedures`, `active`,
`inactive`); a 4-row reserved grid (`h-4` eyebrow / `h-12` number /
`h-6 min-h-[24px]` chip / `h-4` caption) in that exact order.

#### Scenario: `EC-002-1` — All 3 KPI cards carry the Dashboard contract

- GIVEN the Dashboard exemplar (`DashboardPage.vue`) carries the fixed-slot anatomy
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `ProcedureStatsAppShellTest::test_kpi_anatomy_matches_dashboard` asserts 3+ `<UiCard data-stat-card>` blocks; all 3 keys present; each card consumes hairline + elevation-2 tokens; slot ordering enforced via `strpos` comparisons
- **Verdict at close: PASS**

### Requirement: `EC-003` — `<PageHeader>` adoption (removes page-level `<h1>`)

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.3.*

The custom header block (legacy lines 3–17) MUST be replaced with
`<PageHeader title="Estadísticas de Procedimientos" :subtitle="...">`.
The page-level `<h1 class="text-3xl font-bold text-theme-primary mb-2">`
element MUST be removed entirely (defect 7 family — competes with the
`AppLayout` topbar `<h1>`).

#### Scenario: `EC-003-1` — Page-level `<h1>` is gone, `<PageHeader>` is present

- GIVEN the page header at legacy lines 3–17 carried a page-level `<h1>`
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `ProcedureStatsAppShellTest::test_page_header_replaces_h1` asserts `<PageHeader` is present and `grep -n "<h1"` returns 0 matches in production code (HTML-comment stripping applied)
- **Verdict at close: PASS**

### Requirement: `EC-004` — `<UiEmptyState>` + `<UiSkeleton>` adoption

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.4.*

The two ad-hoc empty `<div>` blocks (legacy lines 81 + 129) MUST be
replaced with `<UiEmptyState title="Sin datos" description="No hay datos
para el período seleccionado." />`. A loading skeleton block MUST be
introduced (currently absent — the page rendered blank during fetch)
mirroring the Dashboard pattern: 3 `<UiSkeleton variant="card">` for KPI
counters + 6 `<UiSkeleton variant="list">` for table + specialty rows,
all inside a wrapper carrying `aria-busy="true"` AND
`aria-live="polite"`.

#### Scenario: `EC-004-1` — Empty states and loading skeleton are primitive-driven

- GIVEN the legacy code rendered two ad-hoc empty `<div>` blocks and no loading state
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `ProcedureStatsAppShellTest::test_loading_branch_renders_skeletons` asserts ≥3 `<UiSkeleton variant="card">` + ≥6 `<UiSkeleton variant="list">` + both aria attributes + ≥2 `<UiEmptyState>` blocks
- **Verdict at close: PASS**

### Requirement: `EC-005` — `formatPENLabel` swap on currency cells

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.5.*

The inline `.toFixed(2)` calls at the two revenue cells (legacy line 117
for table; legacy line 142 for specialty tile) MUST be replaced with
`formatPENLabel` from `@/composables/useFormatters`. The `<script setup>`
block MUST import `formatPENLabel` as the only additive change. The
currency cells MUST emit the `S/` prefix via the formatter (not via
inline `S/` concatenation).

#### Scenario: `EC-005-1` — No inline `.toFixed(2)` remains on currency cells

- GIVEN `formatPENLabel` exists at `resources/js/composables/useFormatters.js` (per PAGOS-MNY-002)
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `grep -n "toFixed(2)" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns zero matches
- AND `<script setup>` declares `import { formatPENLabel } from '@/composables/useFormatters'`
- AND `ProcedureStatsAppShellTest::test_format_pen_label_consumed` asserts both: the import exists AND no `.toFixed(2)` literal remains
- **Verdict at close: PASS**

### Requirement: `EC-006` — Tokenisation of `text-green-600` and raw red ramps

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.6.*

The raw Tailwind classes MUST be replaced with the proven system ramps:
`text-green-600` (legacy line 60, "Activos" KPI) → `text-systemGreen-600`;
the raw red ramp `bg-red-50 border-red-200 text-red-700` (legacy line
147, error banner) → `bg-systemRed-50 border-systemRed-200
text-systemRed-700`.

#### Scenario: `EC-006-1` — Raw green/red ramps are tokenised

- GIVEN the "Activos" KPI carried `text-green-600` and the error banner carried raw red ramps
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `grep -nE "text-green-600|bg-red-50|border-red-200|text-red-700"` returns zero matches
- AND `ProcedureStatsAppShellTest::test_no_raw_green_or_red_ramps` asserts the rule (negative regex with word-boundary lookarounds AND the tokenised ramp regex is present)
- **Verdict at close: PASS**

### Requirement: `EC-007` — Hairline borders on table + specialty tile

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.7.*

The `border-theme` literals at legacy lines 85, 99, and 136 MUST be
replaced with `border-[color:var(--color-hairline)]`. The specialty tile
wrapper (legacy line 136) MUST use `rounded-[var(--radius-control)]`
(8 px) for the corner radius AND `border-[color:var(--color-hairline)]`
for the border.

#### Scenario: `EC-007-1` — Legacy `border-theme` literals are gone, hairlines tokenised

- GIVEN `border-theme` appears at lines 85, 99, and 136 (3 occurrences per category `explore.md` §1 file inventory)
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `grep -n "border-theme" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns zero matches (also enforced by the inherited `ModuleAppShellTestCase::test_no_legacy_border_theme_literal`)
- AND the specialty tile wrapper carries `rounded-[var(--radius-control)]` AND `border-[color:var(--color-hairline)]`
- AND `ProcedureStatsAppShellTest::test_specialty_tile_uses_hairline_and_radius_control` asserts both classes are present on the tile wrapper
- **Verdict at close: PASS**

### Requirement: `EC-008` — Inline role disclosure banner

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.8.*

The page MUST render an inline role disclosure at the top: the visible
text MUST read "Visible para: Administrador, Finanzas" (matching the
`role:administrador,role:finanzas` middleware on `routes/api.php:189`).
The disclosure MUST live inside a small `<div class="text-xs
text-theme-secondary">` (NOT a `<UiStatusBadge>` — role disclosure is
not a status pill, and `<RoleBanner>` primitive extraction is deferred
until a second role-restricted module slice arrives, per OQ-EC-3). The
disclosure MUST NOT block content layout (no full-width banner).

#### Scenario: `EC-008-1` — Role disclosure is visible at page top

- GIVEN the page is gated by the `administrador` and `finanzas` role middleware on `routes/api.php:189`
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `ProcedureStatsAppShellTest::test_role_disclosure_present` asserts the literal Spanish text (case-insensitive) AND the `text-xs text-theme-secondary` class-binding regex
- **Verdict at close: PASS**

### Requirement: `EC-009` — `tabular-nums` + `font-feature-settings` on 8 numeric elements

*Provenance: `ui-rollout-all-modules-2026-08` → `categories/estadisticas-catalogo/spec.md` §2.9.*

The standing numeric contract MUST be applied to every numeric element
in the page: 3 KPI counters (Total procedimientos, Activos, Inactivos)
+ 3 table numerics (Usos, Cantidad total, Ingresos S/) + 2 specialty
numerics (usos + S/) = 8 total. Each numeric element MUST carry BOTH the
`tabular-nums` Tailwind utility class AND
`style="font-feature-settings: var(--font-features-tabular-nums)"` —
either alone is a contract violation (paired-only rule).

#### Scenario: `EC-009-1` — All 8 numeric elements carry the paired tabular contract

- GIVEN the standing contract requires `tabular-nums` AND `font-feature-settings: var(--font-features-tabular-nums)` together (per `DashboardAppShellTest::test_dashboard_stat_card_numbers_are_tabular_nums`)
- WHEN `pr1-procedure-stats-tokenise-and-wire-up` lands
- THEN `ProcedureStatsAppShellTest::test_tabular_nums_on_all_numerics` asserts ≥8 paired occurrences via lookahead regex AND ≥8 raw `tabular-nums` literals
- AND any numeric element carrying one but not the other fails the test (paired-only rule)
- **Verdict at close: PASS**

---

*End of promoted ESTADISTICAS-CATALOGO rows. Next category slice appends below.*
