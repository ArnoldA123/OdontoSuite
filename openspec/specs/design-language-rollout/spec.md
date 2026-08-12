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
