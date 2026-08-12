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
