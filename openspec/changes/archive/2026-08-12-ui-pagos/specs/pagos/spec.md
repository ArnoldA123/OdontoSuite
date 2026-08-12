# Spec: PAGOS Category Delta — `ui-rollout-all-modules-2026-08`

> **Delta type**: Category delta spec. Sibling of the global
> `design-language-rollout` and `foundation-primitives` specs. Extends
> the global rollout with PAGOS-specific deltas that the parent rows
> `DLR-MOD-007` (Caja), `DLR-MOD-010` (Quotations), and `DLR-MOD-020`
> (Payment Methods admin CRUD) do not enumerate.
>
> **Naming convention**: follows the archive convention
> `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/`
> (one spec per domain under `specs/<domain>/spec.md`). Signing key:
> `PAGOS-*` for PAGOS-only rows; `PAGOS-XCUT-*` for cross-cutting rows.

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | PAGOS (Caja + Quotations + Payment Methods admin CRUD) |
| Date | 2026-08-12 |
| SDD phase | `spec` (3 of 6) — category slice |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/pagos/spec`) |
| Delivery strategy | `auto-chain` (inherited from global) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` (forward to apply/verify) |
| Parent spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` |
| Parent proposal | `openspec/changes/ui-rollout-all-modules-2026-08/categories/pagos/proposal.md` |
| Parent explore | `openspec/changes/ui-rollout-all-modules-2026-08/categories/pagos/explore.md` |
| PAGOS PRs | `pr-pagos-01..05` (5 chained PRs — see PAGOS proposal §6) |

### Relationship to parent spec

This spec does NOT modify the global `design-language-rollout/spec.md`
rows. It is a sibling that adds PAGOS-specific delta rows. The
`DLR-CORE-*` and `DLR-MOD-*` rules apply to PAGOS unmodified; the
rows below add category-specific edges (money formatting, modal
primitive adoption, receipt redaction, Echo channel reuse, PR-budget
isolation, financial-data accessibility, existing-contract preservation).

---

## 1. Purpose

This spec covers the PAGOS interfaces of OdontoSuite only: the cash
register hub (`/cash-register`), ready-to-bill appointments
(`/cash-register/ready-to-bill`), Quotations (`/quotations`), and the
Payment Methods admin CRUD (`/settings/payment-methods`). It extends
the global `design-language-rollout` spec with PAGOS-specific deltas:
canonical money input + PEN formatter, mandatory modal primitive
adoption across every payment modal, `PaymentMethod.gateway_config`
redaction, mandatory reuse of existing Echo real-time channels,
prohibition of new payment types or currencies, per-PR review-budget
isolation, accessibility semantics for financial data, and
preservation of the existing `useCashRegister` reactivity contract
and the `PaymentModal` 401 redirect.

---

## 2. ADDED Requirements

### Requirement: `PAGOS-MNY-001` — `CurrencyInput` is the only money input on PAGOS surfaces

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

## 3. Out-of-scope explicit list

Mirrors the PAGOS proposal §3. Items are excluded from the PAGOS
rollout and explicitly recorded so the apply phase does NOT silently
resolve them.

| Item | Reason |
|---|---|
| Backend business logic (`TransactionService`, `MercadoPagoService`, `CashRegisterService`) | UI-only migration; `TransactionService.createTransaction` byte-for-byte unchanged |
| `ProcessMercadoPagoWebhook` retry / idempotency semantics | Retry policy `tries=3, backoff=[60,300,900]` and `unique(external_id, event_type)` preserved verbatim |
| New payment gateways (Niubiz, Izipay, Culqi, Stripe) | MercadoPago is the only gateway |
| Multi-currency / currency conversion | PEN only; `Intl.NumberFormat('es-PE', { currency: 'PEN' })` is the sole formatter |
| New `Transaction.type` values or new `PaymentMethod` flags | Existing seeders are the canonical set |
| Insurance claim flows (patient billing configuration) | Belongs to the Pacientes category slice |
| Quotation template editor (PDF layout, terms text) | Quotations rollout is the LIST/DETAIL/APPROVAL chrome only |
| BI revenue dashboard visuals | Consumes transactions but is its own category slice |
| `PaymentMethod.gateway_config` raw exposure | Redacted via `data-redacted="true"`; raw blob never echoes to the DOM |
| `<script>` blocks of PAGOS modules | UI changes are template-level class-string replacement only |
| New tokens / new primitives beyond PR0's `<UiStatusBadge>` | `tokens.js` is frozen for the rollout |

---

## 4. Verification strategy

- **Visual**: `pnpm build` clean; `git grep` for `hover-lift`, `disabled:opacity-30`, `border-theme`, `bg-success-100`, `text-accent`, `focus:ring-primary-500`, `bg-black bg-opacity-60` returns zero matches inside `resources/js/modules/cash-register/**`, `resources/js/modules/quotations/**`, and `resources/js/modules/settings/payment-methods/**`. `playwright-cli` snapshot at 1440×900 + 390×844 (Caja + Ready-to-Bill only) saved to `.playwright-cli/screenshots-rollout/`. Credentials: `finanzas@test.com` for Caja + Quotations + Payment Methods; `recep@test.com` for Ready-to-Bill.
- **Static (PHPUnit)**: `FormatPENLabelTest` extended to assert `formatCurrency` exists at exactly one location and emits the `S/` prefix via `Intl.NumberFormat('es-PE', { currency: 'PEN' })`. New `CashRegisterAppShellTest`, `QuotationsAppShellTest`, `PaymentMethodsAppShellTest` extend `ModuleAppShellTestCase` and assert the rule per module (PaymentMethods additionally asserts `data-redacted="true"` on the gateway_config wrapper).
- **Runtime**: `PaymentModal401RedirectTest`, `ComposablesStandardizationTest`, `RequireActiveCashSessionTest`, `PaymentReceivedChannelTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest` stay green at every PR-pagos-NN boundary. Cashier role sees `payment.registered` update the `/dashboard` cash-status pill within 1 second of a manual payment capture.

---

## 5. Acceptance criteria

The PAGOS category is considered complete when ALL of the following hold:
all 4 PAGOS routes render on `var(--color-canvas)`; `CurrencyInput` is
the only money input; `Intl.NumberFormat('es-PE', { currency: 'PEN' })`
is the only money formatter consolidated to one location (`FormatPENLabelTest`
green); every payment modal uses `<UiModal>` + `<UiTabs>` + `<UiButton>` +
`<UiStatusBadge>` exclusively (no hand-built `<Teleport>` modals, no
`bg-black bg-opacity-60`); `PaymentMethod.gateway_config` is redacted
on the admin form (`data-redacted="true"` present, raw blob absent);
no new Echo channels introduced; no new `Transaction.type` values,
gateways, or currencies; each PR-pagos-NN stays under the 400-line
authored budget; tabular numerics on financial tables expose `scope="col"`,
`aria-label`, and currency context; `PaymentModal401RedirectTest`,
`ComposablesStandardizationTest`, `RequireActiveCashSessionTest`,
`PaymentReceivedChannelTest`, `AppLayoutCanvasRoutesTest`,
`LegacyAliasForbiddenTest` stay green at every PR-pagos-NN boundary;
`<script>` blocks of PAGOS modules are byte-for-byte unchanged;
per-PR `playwright-cli` snapshots saved to
`.playwright-cli/screenshots-rollout/`; CI gates (`quality`,
`backend-tests` MySQL, `frontend-build` pnpm) green at every
PR-pagos-NN boundary.

---

## 6. References

- `categories/pagos/explore.md` — PAGOS inventory (frontend, backend,
  controllers, services, jobs, models, tests, known gotchas).
- `categories/pagos/proposal.md` — PAGOS proposal (intent, scope,
  risk register, rollback, success criteria).
- `specs/design-language-rollout/spec.md` — parent spec (`DLR-MOD-007`
  Caja, `DLR-MOD-010` Quotations, `DLR-MOD-020` Payment Methods,
  cross-cutting `DLR-R-*` rules).
- `specs/foundation-primitives/spec.md` — PR0 spec (`<UiStatusBadge>`,
  `canvasRoutes`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`).
- `design.md` — PR0 design (StatusBadge API, canvasRoutes array
  literal, PHPUnit test contracts).
- `archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` —
  process lesson: "tests pin the rule, not the literal." PAGOS
  structure tests extend `ModuleAppShellTestCase` and assert the rule.

---

*End of PAGOS category spec.*
