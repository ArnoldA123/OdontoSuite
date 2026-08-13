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
