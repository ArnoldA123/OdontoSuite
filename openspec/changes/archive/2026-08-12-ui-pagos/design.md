# Design: PAGOS Category Delta — `ui-rollout-all-modules-2026-08`

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | PAGOS (Caja + Ready-to-Bill + Quotations + Payment Methods admin) |
| Date | 2026-08-12 |
| SDD phase | `design` (4 of 6) — category slice |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/pagos/design`) |
| Delivery strategy | `auto-chain` (inherited from global) |
| Review budget | 400 authored lines / PR (per global proposal §7.15) |
| Strict TDD | `true` (forward to apply/verify) |
| Parent design | `openspec/changes/ui-rollout-all-modules-2026-08/design.md` (PR0 + global primitives) |
| Parent spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` |
| Sibling spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/pagos/spec.md` |
| PAGOS PRs | `pr-pagos-01..05` (5 chained sub-PRs — see §3) |

### What this document IS and IS NOT

**IS**: a PAGOS-only delta on top of the global design. It maps the 4 pagos routes and 12 cash-register + 6 quotations + 2 payment-methods `.vue` files onto the primitives, tokens, motion durations, focus-ring composition, hairline, canvas/surface separation, and `tabular-nums` decisions already locked in `design.md` §2 (StatusBadge API), §3 (canvasRoutes), §4 (PHPUnit invariants), §5 (cross-cutting rules). It enumerates the 5 PAGOS sub-PRs, their dependency graph, the per-PR changed-line budget, and the per-module test strategy.

**IS NOT**: a re-derivation of the global design. Token names, primitive prop contracts, motion durations (`var(--motion-duration-normal)` + `var(--motion-easing-ios)`), focus-ring composition (`var(--focus-ring-default)`), hairline (`rgba(60, 60, 67, 0.12)`), canvas/surface (`#F2F2F7` canvas, `#ffffff` systemBackground), and the `ModuleAppShellTestCase` rule set are ALL inherited from the global design — referenced here by path + section, never restated.

### Preflight snapshot

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached
strict_tdd: true
```

### Standing guard rails (inherited verbatim — see global design §5 + proposal §11.2)

1. `tokens.js` is frozen — no new tokens.
2. systemBackground `#ffffff` pinned; canvas `#F2F2F7` pinned.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings: var(--font-features-tabular-nums)` for numerics.
7. `<script>` blocks of every PAGOS module are NEVER edited in any PR.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only.
10. Code in English; conversation in Spanish (Peru).

---

## 1. Architectural intent

PAGOS is the operational heart of OdontoSuite: every sol enters or leaves through these screens. The cashier opens a session, takes payments (manual or Mercado Pago Bricks), records movements, issues refunds ("Egreso"), closes the session with an arqueo, and at month-end exports reports. The ready-to-bill screen identifies appointments with a balance due. Quotations are the pre-payment approval flow that feeds into transactions. The payment-method catalog governs which gateways the cashier may use.

The global design (PR0) shipped the canvas surface for all 21 routes (4 of them PAGOS) and the generic `<UiStatusBadge>` primitive. PAGOS consumes these primitives and applies the global design rules mechanically to every `.vue` file under `cash-register/**`, `quotations/**`, and `settings/payment-methods/**`. **No new tokens, no new primitives, no backend changes, no `<script>` edits.** All work is template-level class-string replacement against the global design §4.1 mapping table.

The 4 PAGOS-only deltas the global design does NOT enumerate (and that PAGOS adds):

1. **Money formatter consolidation**: `formatCurrency` is reimplemented in 4+ files (verified at `ReadyToBillPage.vue:63`, `CashRegisterPage.vue:610`, `CloseCashModal.vue:412`, `CashReports.vue`, plus the canonical `formatPENLabel` at `useFormatters.js:49`). PAGOS adds a single canonical `formatCurrency` export at `useFormatters.js` and migrates all call sites.
2. **Modal primitive migration**: hand-built `<Teleport>` modal in `ReadyToBillPage` + raw `<button>` tab strips in `PaymentModal` + raw `<div class="modal">` patterns across `TransactionModal`, `MovementModal`, `OpenCashModal`, `CloseCashModal` MUST adopt `<UiModal>` + `<UiTabs>` + `<UiButton>` + `<UiStatusBadge>`.
3. **`PaymentMethod.gateway_config` redaction**: the encrypted `Crypt::encryptString` blob is rendered today (or at risk of being rendered) on the admin form. PAGOS adds `data-redacted="true"` on the visible field and asserts the raw blob is absent from the DOM.
4. **Echo channel reuse**: existing channels `cash-register`, `.cash-session.opened`, `.cash-session.closed`, `.payment.registered`, `.cash-movement.created` (per `useCashRegister.js:9-12` + `useCashRegister.js` Echo consumer). PAGOS MUST NOT introduce new channels.

---

## 2. PAGOS surface map

The global design §6 enumerates what every category MUST consume. The table below maps each PAGOS route and component to the specific primitive set, tokens, and motion duration that apply.

| Surface (file path) | Primary primitive(s) | Token set | Motion duration | Touch scope (from proposal) |
|---|---|---|---|---|
| `resources/js/modules/cash-register/CashRegisterPage.vue` | `<UiModal>` (delete-confirm), `<UiTabs>` (Pagos/Transacciones/Movimientos/Historial/Reportes), `<UiStatusBadge>` (status pills), `<UiCard clickable>` (hover-lift replacement) | canvas, hairline, `tabular-nums`, systemBlue-50 (active tab) | `var(--motion-duration-normal) var(--motion-easing-ios)` (tab transitions only) | large |
| `resources/js/modules/cash-register/ReadyToBillPage.vue` | `<UiModal>` (REPLACES hand-built `<Teleport>` with `bg-black bg-opacity-60`), `<UiLoadingSpinner>` (REPLACES `disabled:opacity-30`), `<UiStatusBadge>` (status pills) | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` (modal open) | medium |
| `resources/js/modules/cash-register/components/PaymentModal.vue` | `<UiModal>`, `<UiTabs>` (Manual / MercadoPago), `<UiButton>` (all actions), `<UiStatusBadge>` (gateway pills), `<CurrencyInput>` (amount), `<UiInput>` (reference, notes), `<UiSelect>` (patient, method) | canvas, systemYellow-50 (MercadoPago tab pending), focus-ring on errors | `var(--motion-duration-normal) var(--motion-easing-ios)` (tab transition + Bricks-loading pill) | large |
| `resources/js/modules/cash-register/components/MercadoPagoCheckout.vue` | `<UiButton>`, `<UiLoadingSpinner>`, `<UiStatusBadge variant="info">` (processing state) | systemBlue-50 (processing state) | `var(--motion-duration-normal) var(--motion-easing-ios)` (loading pill wash only) | small |
| `resources/js/modules/cash-register/components/TransactionModal.vue` | `<UiModal>`, `<UiCard>` (REPLACES `bg-primary-50` patient banner), `<UiStatusBadge>` (Ingreso/Egreso), `<UiLoadingSpinner>` (REPLACES `animate-spin`) | canvas, hairline | `var(--motion-duration-fast)` (modal open) | medium |
| `resources/js/modules/cash-register/components/MovementModal.vue` | `<UiModal>`, `<CurrencyInput>`, `<UiSelect>` | canvas, hairline | `var(--motion-duration-fast)` | small |
| `resources/js/modules/cash-register/components/OpenCashModal.vue` | `<UiModal>`, `<CurrencyInput>`, `<UiEmptyState>` | canvas, hairline | `var(--motion-duration-fast)` | small |
| `resources/js/modules/cash-register/components/CloseCashModal.vue` | `<UiModal>`, `<UiStatusBadge>` (per-method status), `<CurrencyInput>` (desglose), `tabular-nums` on totals | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` | medium |
| `resources/js/modules/cash-register/components/TransactionList.vue` | `<UiInput>` (search, REPLACES raw `<input>`), `<UiSelect>` (filters, REPLACES raw `<select>`), `<UiStatusBadge>` (status column), `<UiEmptyState>` (no results), `tabular-nums` on amounts | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` (filter chip press) | medium |
| `resources/js/modules/cash-register/components/MovementList.vue` | Same primitive set as `TransactionList` | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` | medium |
| `resources/js/modules/cash-register/components/SessionList.vue` | `<UiInput>`, `<UiSelect>`, `<UiStatusBadge>` (session state: open/closed), `tabular-nums` on opening/closing amounts | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` | medium |
| `resources/js/modules/cash-register/components/CashReports.vue` | `<UiCard>` (REPLACES gradient cards), `<UiInput>`, `<UiSelect>` (date range), `tabular-nums` on all totals | canvas, hairline, `tabular-nums` | `var(--motion-duration-normal)` (card hover wash only) | medium |
| `resources/js/modules/cash-register/components/PendingPaymentsList.vue` | `<UiInput>`, `<UiLoadingSpinner>` (REPLACES custom spinner) | canvas, hairline | `var(--motion-duration-fast)` | small |
| `resources/js/modules/quotations/QuotationsPage.vue` | `<UiInput>`, `<UiSelect>`, `<UiStatusBadge>` (status column), `tabular-nums` on amounts | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` | medium |
| `resources/js/modules/quotations/components/QuotationCard.vue` | `<UiCard>` (REPLACES `bg-theme-surface`), `<UiStatusBadge>` (status) | canvas, hairline | `var(--motion-duration-normal)` (card press) | medium |
| `resources/js/modules/quotations/components/QuotationModal.vue` | `<UiModal>`, `<UiInput>`, `<UiSelect>`, `<CurrencyInput>` | canvas, hairline | `var(--motion-duration-fast)` | medium |
| `resources/js/modules/quotations/components/QuotationDetail.vue` | `<UiStatusBadge>` (status), `tabular-nums` on line items | canvas, hairline, `tabular-nums` | none (read-only surface) | medium |
| `resources/js/modules/quotations/components/QuotationStatusBadge.vue` | **MIGRATED**: thin wrapper around `<UiStatusBadge variant="...">` (first consumer per global spec `DLR-MOD-010`) | inherited from `StatusBadge.vue` ramp | inherited | small |
| `resources/js/modules/quotations/components/QuotationApprovalModal.vue` | `<UiModal>`, `<UiButton>`, `<UiStatusBadge>` | canvas, hairline | `var(--motion-duration-fast)` | small |
| `resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue` | `<UiCard>`, `<UiInput>`, `<UiSelect>`, `<UiStatusBadge>` (active/inactive), `tabular-nums` on counters | canvas, hairline, `tabular-nums` | `var(--motion-duration-fast)` | medium |
| `resources/js/modules/settings/payment-methods/PaymentMethodFormModal.vue` | `<UiModal>`, `<UiInput>`, `<UiSelect>`, `data-redacted="true"` on `gateway_config` wrapper | canvas, hairline | `var(--motion-duration-fast)` | medium |
| `resources/js/components/ui/CurrencyInput.vue` | UNCHANGED — sole canonical money input | inherited | inherited | none (consumed as-is) |
| `resources/js/components/ui/ReceiptPreview.vue` | `<UiButton>` (print/download), hairline borders | canvas, hairline | `var(--motion-duration-normal)` (action press) | small |

**Negative space (PAGOS MUST NOT introduce)**:

- No new `<Teleport to="body">` block in any PAGOS file (use `<UiModal>`).
- No raw `<button class="...">` for tab strips (use `<UiTabs>`).
- No raw `<div class="modal">` (use `<UiModal>`).
- No `bg-black bg-opacity-60` overlays (use `<UiModal>` overlay token).
- No raw `<input>` borders outside `CurrencyInput` (use `<UiInput>`).
- No raw `<select>` borders (use `<UiSelect>`).
- No `bg-primary-50` blocks (use `<UiCard>` or tokenised surface).
- No `animate-spin` (use `<UiLoadingSpinner>`).
- No `disabled:opacity-30` affordance on submit (use `<UiLoadingSpinner>` + `disabled`).
- No `Intl.NumberFormat` calls outside `useFormatters.js` (use `formatCurrency`).
- No `S/ ${n.toFixed(2)}` patterns (use `formatCurrency`).

---

## 3. PAGOS-specific component decisions

### 3.1 Decision: PaymentModal tab order and disabled-when-amount-zero rule

**Choice**:

- Tab order: `Manual` (default active) → `MercadoPago`. Rationale: the cashier scans the patient + amount first; MercadoPago is the secondary path selected only when the patient asks for card/wallet payment.
- The `MercadoPago` tab is `disabled` (per the global `<UiTabs>` `disabled` prop pattern — see global design §6.1) when `amount <= 0` OR when `paymentMethod.gateway_type !== 'mercadopago'`. The disabled state shows a small `<UiStatusBadge variant="warning" size="sm" label="Ingrese monto" />` hint below the tab strip.
- The `Manual` tab is NEVER disabled (it accepts cash, Yape, Plin, transfer, etc., all of which are non-gateway methods).

**Alternatives considered**:

- Single-tab PaymentModal with a gateway dropdown — REJECTED. The cashier workflow today is tab-based; switching to a dropdown breaks muscle memory and adds a click per payment. The spec `PAGOS-MOD-001-1` explicitly requires `<UiTabs>`.
- Disable the entire PaymentModal when `amount <= 0` — REJECTED. The cashier may want to record a `refund` ("Egreso") with a negative amount; that path requires the modal to be open with the `Manual` tab active. The disable rule applies to the `MercadoPago` tab only.

**Rationale**: tab-level disable (vs. modal-level) is the minimal change that satisfies both UXF-021 (payment capture flow) and the refund/Egreso flow. The `FormatPENLabelTest` extension does NOT assert this rule (it tests formatter output); the rule is asserted by `CashRegisterAppShellTest::test_payment_modal_mercadopago_tab_disabled_when_amount_zero` (new test method, ships with PR-pagos-02).

### 3.2 Decision: TransactionModal vocabulary (Ingreso vs Egreso)

**Choice**: align modal title + button label with `Transaction.type` enum.

| `Transaction.type` value | Modal title | Primary button label | Status badge variant |
|---|---|---|---|
| `payment` | "Registrar Ingreso" | "Registrar pago" | `<UiStatusBadge variant="success" label="Ingreso" />` |
| `refund` | "Registrar Egreso" | "Registrar devolución" | `<UiStatusBadge variant="error" label="Egreso" />` |

The modal accepts a `type` prop (default `'payment'`); the caller passes `type` based on which action triggered the modal (e.g. a "Devolución" button on `TransactionList.vue` passes `type="refund"`). The same modal file serves both flows — no separate `RefundModal.vue` is created (PAGOS keeps the surface area minimal).

**Alternatives considered**:

- Separate `RefundModal.vue` — REJECTED. The `TransactionModal.vue` body is identical between payment and refund flows (patient, concept, amount, method, notes); only the title + button + status badge differ. Adding a second file duplicates ~80% of the template.
- Rename `Egreso` to `Refund` in UI — REJECTED. The cashier workflow today uses "Egreso" (per `MovementList.vue` line pattern); the spec `PAGOS-CON-001` requires vocabulary preservation (`Transaction.type === 'refund'` → UI label "Egreso").

**Rationale**: a single `TransactionModal.vue` with a `type` prop is the minimal change that satisfies both the global spec (`PAGOS-MOD-001-1`) and the contract preservation rule (`PAGOS-CON-001`). The `type` prop change is the ONLY `<script>` block edit PAGOS-MOD-001 needs — and it is restricted to adding the prop, not changing existing reactivity logic. The 401 redirect code path is untouched.

### 3.3 Decision: ReadyToBillPage modal migration

**Choice**: replace the hand-built `<Teleport to="body">` modal (uses `bg-black bg-opacity-60`, raw `<input>` borders, `disabled:opacity-30` affordance) with `<UiModal>`. The wrapper markup swap is mechanical:

- `<Teleport to="body">` + `<div class="fixed inset-0 bg-black bg-opacity-60">` → `<UiModal :open="showBreakdown" @close="showBreakdown = false" @confirm="confirmBreakdown" />`.
- `:disabled="!canSubmit"` + `class="disabled:opacity-30"` → `:disabled="!canSubmit || loading"` + inside-button `<UiLoadingSpinner v-if="loading" />`.
- The `open` / `close` / `confirm` emits are preserved byte-for-byte (per spec `PAGOS-CON-001-1`).
- The 401 redirect from inside the modal: when `BillingController.paymentPreview` returns 401, the `useApi()` wrapper's interceptor (per `useApi.js` consumer pattern) tears down the session and bounces to `/login`. The redirect path is owned by `useApi`, not by the modal — so the modal swap does not touch the redirect logic. `PaymentModal401RedirectTest` stays green (same code path).

**Alternatives considered**:

- Wrap the existing `<Teleport>` block in a `<UiCard>` (keep the teleport) — REJECTED. `<UiModal>` is the canonical primitive; mixing teleport + UiCard breaks the canvas/surface separation rule (`DLR-R-001`).
- Defer the migration to PR-pagos-05 — REJECTED. The 4 PAGOS sub-PRs (01..04) each must complete under 400 lines; migrating `ReadyToBillPage` in PR-pagos-03 keeps the budget.

**Rationale**: this is the lowest-risk migration because the `useApi` redirect path is composable-owned, not component-owned. The test `CashRegisterAppShellTest::test_ready_to_bill_modal_uses_ui_modal` asserts the `<UiModal>` wrapper is present and the `bg-black bg-opacity-60` literal is absent.

### 3.4 Decision: CurrencyInput canonicalization

**Choice**: `<CurrencyInput>` (the primitive at `resources/js/components/ui/CurrencyInput.vue`) is the SOLE money input on every PAGOS surface. The apply phase MUST NOT introduce a raw `<input type="text" v-model="amount">` or `<input type="number" v-model="amount">` pattern under `cash-register/**`, `quotations/**`, or `settings/payment-methods/**`. The existing `CurrencyInput.vue` is UNCHANGED (consumed as-is); the rule is enforced by:

- Extended `CashRegisterAppShellTest`, `QuotationsAppShellTest`, `PaymentMethodsAppShellTest` (new files) — each calls `assertNoRawMoneyInput()` which greps the module tree for the raw `<input type="..." v-model="amount...">` pattern (per spec `PAGOS-MNY-001-1`).
- The component class names (`form-input`, `border-theme`, `border-red-500`, raw `<input>` borders) MUST change on every caller. The migration is mechanical: replace `<input type="number" v-model="amount">` with `<CurrencyInput v-model="amount" />`. The `CurrencyInput` props (`currency="PEN"`, `decimals="2"`, `prefix="S/"`) match the existing `formatPENLabel` output.

**Alternatives considered**:

- Add a new `MoneyInput` component — REJECTED. `CurrencyInput` IS the money input. Naming a new component duplicates the surface area.
- Allow `<input type="number">` for read-only displays (e.g. balance preview) — REJECTED. Read-only money displays MUST use `tabular-nums` + `formatCurrency` (per `PAGOS-A11Y-001`); the `<input>` element is never appropriate for a read-only money display.

**Rationale**: the `CurrencyInput` primitive already exists and ships with PR0 (consumed as-is per `DLR-R-013` no-new-dependencies rule). The migration is mechanical class-string replacement. The rule is asserted per-module via the three new `*AppShellTest` files.

### 3.5 Decision: formatCurrency consolidation

**Choice**: consolidate `formatCurrency` to exactly one location at `resources/js/composables/useFormatters.js` (alongside the existing `formatPENLabel`). The decision:

1. Rename the canonical helper from `formatPENLabel` to `formatCurrency` (the spec name per `PAGOS-MNY-002`).
2. Re-export `formatPENLabel` as `formatCurrency` for backwards compatibility (the existing DashboardPage + SessionList callers continue to work without import-line edits).
3. Migrate the 4+ duplicate `formatCurrency` reimplementations (verified at `ReadyToBillPage.vue:63` manual `S/ ${...}`, `CashRegisterPage.vue:610` `Intl.NumberFormat`, `CloseCashModal.vue:412` `Intl.NumberFormat`, plus `CashReports.vue`, `TransactionList.vue`, `MovementList.vue`, `MovementModal.vue`, `OpenCashModal.vue`, `PendingPaymentsList.vue`) to import from `useFormatters.js`.
4. Wrapper signature: `(amount, options) => string` — the `options` param is reserved for future use (e.g. currency override in a multi-currency world — NOT supported today per `PAGOS-SCP-001`). Today `options` is ignored; the formatter always emits `S/` + 2-decimal PEN.

**Alternatives considered**:

- Dedicated `resources/js/utils/formatCurrency.js` (per proposal §2.7 fallback) — REJECTED. `useFormatters.js` already exists and hosts the canonical `formatPENLabel`; creating a new sibling file duplicates the surface area. The spec `PAGOS-MNY-002` lists `useFormatters.js` as the PREFERRED location.
- Keep both names (`formatPENLabel` AND `formatCurrency`) as parallel exports — REJECTED. The spec `PAGOS-MNY-002-1` requires exactly ONE declaration location; a parallel export is two declarations.
- Move `formatCurrency` into `CurrencyInput.vue` as an exported helper — REJECTED. `CurrencyInput` is a component, not a helper module; exporting a function from a `.vue` file is non-conventional and complicates testing.

**Rationale**: extending the existing canonical helper (rather than creating a new module) is the minimal change that satisfies `PAGOS-MNY-002-1` ("exists at exactly one location"). The `options` param preserves future flexibility without committing to multi-currency today (`PAGOS-SCP-001`). The migration is a pure import-line update at every call site — no template changes, no signature changes for existing callers.

### 3.6 Decision: PaymentMethod.gateway_config redaction

**Choice**: on `PaymentMethodFormModal.vue` (create/edit), the `gateway_config` field renders a `<UiInput type="password" :model-value="REDACTED_PLACEHOLDER" :data-redacted="true" />` wrapper. The decrypted blob is NEVER echoed into any rendered text node — only the wrapper attribute `data-redacted="true"` confirms the field is gated. Submit posts `gateway_config: <decrypted value>` only when the admin types a NEW value; on edit, leaving the field blank preserves the existing encrypted blob (no round-trip through the DOM).

**Alternatives considered**:

- Display a `••••••••` masked value — REJECTED. Any visible value (even masked) is information leak (length reveals secret length). The `data-redacted="true"` attribute + empty field is the iOS-standard treatment for sensitive credentials.
- Use a separate `<GatewayConfigEditor>` sub-component — DEFERRED. PAGOS keeps the surface area minimal; the redaction is inline in `PaymentMethodFormModal.vue`. A dedicated editor is a follow-up change if MercadoPago config grows beyond 2-3 fields.

**Rationale**: the `data-redacted="true"` attribute is greppable + assertable by `PaymentMethodsAppShellTest::test_gateway_config_redacted` (per spec `PAGOS-RED-001-1`). The attribute is the durable surface; the visual treatment (empty field + lock icon) is secondary.

### 3.7 Decision: Real-time Echo channel reuse

**Choice**: every PAGOS surface that consumes real-time updates MUST subscribe to the existing channel list (per `useCashRegister.js:9-12`):

- `cash-register` (module-level channel)
- `cash-session.opened`, `cash-session.closed` (private session channels)
- `payment.registered` (broadcast)
- `cash-movement.created` (broadcast)

These channels are confirmed by reading `useCashRegister.js` lines 9-12 (module-scope refs) + the Echo consumer block in `useCashRegister.js` body (the `privateChannel` + `channel` calls). PAGOS MUST NOT introduce `Echo.private(...)` or `Reverb` channel declarations in any PAGOS `.vue` file — the channels live in the composable, not the view.

**Alternatives considered**:

- Introduce a per-modal channel (e.g. `payment-modal.${transactionId}`) — REJECTED. The existing `payment.registered` broadcast is sufficient; per-modal channels multiply subscription overhead with no UX benefit.
- Polling fallback (`setInterval`) — REJECTED. The composable already manages subscriptions + cleanup; polling duplicates the data path.

**Rationale**: the existing channel set covers every PAGOS real-time need (cash session state, payment capture, cash movements). The rule is enforced by `PaymentReceivedChannelTest` (existing) + manual visual smoke (cashier sees `payment.registered` update `/dashboard` cash-status pill within 1 second).

---

## 4. PAGOS PR slicing

The PAGOS rollout splits into 5 chained sub-PRs. Each fits inside the 400-line review budget (global proposal §7.15). Each is independently buildable, testable, and revertible per `chained-pr` skill rules.

| PR | Name | Scope | Files touched | Estimated lines | Depends on |
|---|---|---|---|---|---|
| PR-pagos-01 | `pr-pagos-01-cash-register-hub` | `CashRegisterPage.vue` (tabs + real-time cards); `formatCurrency` import update (line 610 → `useFormatters.formatCurrency`); new `CashRegisterAppShellTest` | 1 page + 1 helper update + 1 new test | ~320 | PR0 (landed) |
| PR-pagos-02 | `pr-pagos-02-payment-modal-and-mercadopago` | `PaymentModal.vue` (tab strip → `<UiTabs>`, error styling → focus-ring, submit button → `<UiLoadingSpinner>`); `MercadoPagoCheckout.vue` (Bricks-loading pill motion); extend `CashRegisterAppShellTest` with modal assertions; verify `PaymentModal401RedirectTest` green | 2 components + 1 test extension | ~390 (at budget; split into 02a + 02b if reviewer flags) | PR-pagos-01 |
| PR-pagos-03 | `pr-pagos-03-ready-to-bill-and-modals` | `ReadyToBillPage.vue` (`<Teleport>` → `<UiModal>`, `disabled:opacity-30` → `<UiLoadingSpinner>`, `formatCurrency` migration); `TransactionModal.vue` (`bg-primary-50` → `<UiCard>`, `animate-spin` → `<UiLoadingSpinner>`, `type` prop added); `MovementModal.vue`, `OpenCashModal.vue`, `CloseCashModal.vue` (chrome tokenisation, `formatCurrency` migration) | 5 files + extend `CashRegisterAppShellTest` | ~340 | PR-pagos-01 |
| PR-pagos-04 | `pr-pagos-04-quotations-and-payment-methods` | Quotations: 6 files (`QuotationsPage.vue` + 5 components; `QuotationStatusBadge.vue` migrated to `<UiStatusBadge>` wrapper); Payment Methods: 2 files (`PaymentMethodsPage.vue` + `PaymentMethodFormModal.vue` with `data-redacted="true"`); new `QuotationsAppShellTest` + new `PaymentMethodsAppShellTest` | 8 files + 2 new tests | ~380 (at budget; split into 04a + 04b if reviewer flags) | PR-pagos-01..03 |
| PR-pagos-05 | `pr-pagos-05-format-currency-consolidation` | `useFormatters.js`: rename `formatPENLabel` to `formatCurrency` (keep `formatPENLabel` as backwards-compatible alias); migrate the remaining `formatCurrency` reimplementations to import from `useFormatters`; extend `FormatPENLabelTest` to assert exactly-one-location rule | 1 helper file + 4+ import-line updates + 1 test extension | ~120 | PR-pagos-01..04 |

### 4.1 Ordering rationale

- **Low-risk first (PR-pagos-01)**: `CashRegisterPage.vue` is the hub surface (highest-traffic). Doing it first establishes the `<UiStatusBadge>` + canvas/surface rhythm for every subsequent modal.
- **High-risk in the middle (PR-pagos-02)**: `PaymentModal.vue` is the largest single component (~22.3 KB) and the real-money UX. Splitting from PR-pagos-01 keeps the 400-line budget; the 401 redirect test stays green because `<script>` is untouched.
- **Modals follow hub (PR-pagos-03)**: the smaller modals (`TransactionModal`, `MovementModal`, `OpenCashModal`, `CloseCashModal`) cluster after `ReadyToBillPage` because they share the same `<UiModal>` adoption pattern. The `TransactionModal.type` prop change is the only `<script>` edit allowed in any PAGOS PR (additive only; existing reactivity untouched).
- **Sibling category modules last (PR-pagos-04)**: Quotations + Payment Methods are independent of the Caja half. Doing them last means `CashRegisterAppShellTest` is fully green before the new `QuotationsAppShellTest` and `PaymentMethodsAppShellTest` ship.
- **Cross-cutting consolidation last (PR-pagos-05)**: `formatCurrency` consolidation depends on every other PR's import lines being migrated first (so the test can assert exactly-one-location). Doing it last avoids partial-migration ambiguity in the test.

### 4.2 Alternatives considered

- **Reverse order (PR-pagos-05 first)**: rejected. The consolidation requires every call site to be migrated; doing it first means the test asserts exactly-one-location while 4+ duplicates still exist → test fails on RED.
- **Bundle PaymentModal + TransactionModal (PR-pagos-02 merged with PR-pagos-03)**: rejected. Combined diff would exceed 700 lines (PaymentModal ~390 + ReadyToBillPage + modals ~340) → exceeds the 400-line budget and triggers the `chained-pr` split rule.
- **Skip PR-pagos-05 (consolidation rides with PR-pagos-01)**: rejected. PR-pagos-01's diff is already ~320 lines; adding the consolidation bumps it to ~440 → over budget. Splitting is cheaper.

### 4.3 Budget breakdown per PR (additions + deletions counted for authored risk)

PR-pagos-01: ~320 lines (CashRegisterPage rewrite ~250 + import update ~5 + new test ~65).
PR-pagos-02: ~390 lines (PaymentModal rewrite ~280 + MercadoPagoCheckout motion ~30 + test extension ~80).
PR-pagos-03: ~340 lines (ReadyToBillPage ~120 + TransactionModal ~80 + MovementModal ~30 + OpenCashModal ~30 + CloseCashModal ~50 + test extension ~30).
PR-pagos-04: ~380 lines (Quotations 6 files ~220 + Payment Methods 2 files ~90 + 2 new tests ~70).
PR-pagos-05: ~120 lines (helper rename ~20 + 4+ import updates ~40 + test extension ~60).

Total authored lines across PR-pagos-01..05: ~1,550 lines. No single PR exceeds 400 lines. Generated goldens (test snapshots, generated CSS) are excluded from the risk count per `sdd-phase-common.md` §E.

---

## 5. Apple-language faithfulness checklist

The global spec rows (`DLR-*`) apply to PAGOS unmodified. The PAGOS spec rows (`PAGOS-*`) add category-specific edges. The table below confirms one-line compliance per applicable row.

| Spec row | Compliance (one-line confirmation) |
|---|---|
| `DLR-CORE-001` (canvas surface) | All 4 PAGOS routes are in `AppLayout.canvasRoutes` (PR0 landed); no further work needed. |
| `DLR-CORE-008` (no `<style scoped>`) | All 5 PAGOS PRs remove existing `<style scoped>` blocks (where present) and add none; `ModuleAppShellTestCase::test_no_style_scoped` green per module. |
| `DLR-R-001` (canvas background) | `CashRegisterPage.vue`, `ReadyToBillPage.vue`, `QuotationsPage.vue`, `PaymentMethodsPage.vue` already reference `bg-canvas` (PR0 effect); no template change needed. |
| `DLR-R-002` (hairline borders) | `border-theme` literals replaced by `border-hairline` (= `rgba(60, 60, 67, 0.12)` token) on `TransactionList`, `MovementList`, `SessionList`, `PendingPaymentsList`, `CashReports` filters. |
| `DLR-R-004` (composed focus ring) | `focus:ring-primary-500 focus:border-accent` literals replaced by `var(--focus-ring-default)`; `LegacyAliasForbiddenTest` extended per PR. |
| `DLR-R-007` (`tabular-nums`) | Applied on every numeric column in `TransactionList`, `MovementList`, `SessionList`, `CashReports`, `QuotationsPage` table, `PaymentMethodsPage` counters; uses `font-feature-settings: var(--font-features-tabular-nums)`. |
| `DLR-R-009` (legacy alias ban) | `LegacyAliasForbiddenTest::LEGACY_ALIASES` extended per PR (each PAGOS PR adds the aliases it migrates away from). |
| `DLR-R-013` (no new dependencies) | PAGOS consumes PR0 primitives only; no new npm or composer deps. |
| `DLR-R-017` (strict TDD) | Every UI replacement comes with a test that proves the new behaviour; RED-GREEN discipline per PR. |
| `DLR-R-019` (CI green) | `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm) green at every PR-pagos-NN boundary. |
| `DLR-R-021` (no `<style scoped>`) | See `DLR-CORE-008` above. |
| `DLR-MOD-007` (Caja) | All 4 Caja rules satisfied: template-only class-string replacement; `<script>` blocks preserved verbatim (except `TransactionModal.type` prop addition); `PaymentModal` 401 redirect stays green; `formatCurrency` consolidated. |
| `DLR-MOD-010` (Quotations) | `QuotationStatusBadge` migrated to thin `<UiStatusBadge>` wrapper; currency columns use `tabular-nums`. |
| `DLR-MOD-020` (Payment Methods admin CRUD) | `PaymentMethodsPage` + `PaymentMethodFormModal` tokenised; `PaymentMethod.gateway_config` redacted (`data-redacted="true"` present, raw blob absent); counters use `tabular-nums`. |
| `DLR-MOD-021` (Ready-to-bill) | `ReadyToBillPage` + desglose modal tokenised; hand-built `<Teleport>` replaced by `<UiModal>`; disabled affordance uses `<UiLoadingSpinner>` (NOT legacy `disabled:opacity-30`). |
| `DLR-XCUT-007` (formatCurrency consolidation) | Exactly one location (`useFormatters.js`); legacy 4+ reimplementations forbidden; `FormatPENLabelTest` extended to assert the rule. |
| `PAGOS-MNY-001` (CurrencyInput sole money input) | Grep-verified per module: no raw `<input type="number" v-model="amount">` patterns outside `CurrencyInput.vue`. |
| `PAGOS-MNY-002` (formatCurrency single location) | `useFormatters.js` is the sole declaration; every call site imports from it. |
| `PAGOS-MOD-001` (every payment modal uses Ui primitives) | 7 modals (Payment, MercadoPagoCheckout, Transaction, Movement, OpenCash, CloseCash, ReadyToBillPage desglose) all use `<UiModal>` + `<UiTabs>` + `<UiButton>` + `<UiStatusBadge>` exclusively. |
| `PAGOS-RED-001` (gateway_config redacted) | `data-redacted="true"` attribute on the form wrapper; raw blob absent from DOM. |
| `PAGOS-RT-001` (Echo channel reuse) | `useCashRegister` channels inherited as-is; no new `Echo.private(...)` declarations in any PAGOS file. |
| `PAGOS-SCP-001` (no new payment kinds or currencies) | `Transaction.type` literal set remains `{payment, refund}`; only gateway is MercadoPago; only currency is PEN. |
| `PAGOS-REV-001` (per-PR budget) | Each PR-pagos-NN ≤ 400 lines; split rule applied (02a/02b, 04a/04b) if reviewer flags. |
| `PAGOS-A11Y-001` (tabular numerics expose currency context) | `<th scope="col">` + `aria-label="Monto en soles"` on every numeric column in Quotations and Payment Methods tables. |
| `PAGOS-CON-001` (existing contracts preserved) | `useCashRegister` / `useTransactions` / `usePaymentMethods` contracts unchanged; `PaymentModal` 401 redirect stays green; `<script>` blocks of PAGOS modules byte-for-byte unchanged except `TransactionModal.type` prop addition. |

---

## 6. Test strategy

The PAGOS rollout extends the PR0 test infrastructure (global design §4) with per-module structure tests + cross-cutting formatter assertions. Strict TDD: every UI replacement comes with a test that proves the new behaviour.

### 6.1 Existing tests (MUST stay green at every PR-pagos-NN boundary)

| Test file | What it asserts | Witness role |
|---|---|---|
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | `canvasRoutes` array literal contains all 21 routes (including 4 PAGOS) | regression guard for canvas surface |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | No legacy alias (`bg-success-100`, `bg-primary-50`, etc.) in polished files | regression guard for alias ban |
| `tests/Unit/Composables/PaymentModal401RedirectTest.php` (UXF-021) | 401 from `createTransaction` tears down session + bounces to `/login` | regression guard for payment UXF |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | `useCashRegister` / `useTransactions` / `usePaymentMethods` contracts preserved | regression guard for composable surface |
| `tests/Unit/Composables/FormatPENLabelTest.php` | `Intl.NumberFormat('es-PE', { currency: 'PEN' })` emits `S/` prefix | regression guard for currency formatter |
| `tests/Unit/Middleware/RequireActiveCashSessionTest.php` | Middleware enforces open session for transactions | regression guard for active-session rule |
| `tests/Unit/Events/PaymentReceivedChannelTest.php` | Broadcast channel authorization for `.payment.received` | regression guard for Echo auth |
| `tests/Unit/Listeners/LogPaymentReceivedTest.php` | Audit listener fires on `PaymentReceived` | regression guard for audit trail |
| `tests/Feature/Api/TransactionEndpointsTest.php` | apiResource transactions + list/void/receipt | regression guard for API |
| `tests/Feature/Api/CashRegisterEndpointsTest.php` | open/close/summary/movements/closureReport | regression guard for caja API |
| `tests/Feature/Modules/CashCloseAndClosureReportTest.php` | E2E close + closure report | regression guard for caja flow |

### 6.2 New tests (per PR)

| PR | Test file | What it asserts | Extends |
|---|---|---|---|
| PR-pagos-01 | `tests/Unit/DesignSystem/CashRegisterAppShellTest.php` | `CashRegisterPage.vue` references canvas token; no `border-theme`, `bg-primary-50`, `bg-success-100`, `disabled:opacity-30`, `hover-lift`, `*:transition`; no `<style scoped>` block; no raw `<input type="number" v-model="amount">` | `ModuleAppShellTestCase` + adds `assertNoRawMoneyInput()` |
| PR-pagos-02 | (extension of `CashRegisterAppShellTest.php`) | `PaymentModal.vue` uses `<UiModal>` + `<UiTabs>` + `<UiLoadingSpinner>`; MercadoPago tab `disabled` when amount zero; 401 redirect code path byte-for-byte unchanged | extends base + new `assertModalUsesUiPrimitives()` + `assertMercadoPagoTabDisabledWhenAmountZero()` |
| PR-pagos-03 | (extension of `CashRegisterAppShellTest.php`) | `ReadyToBillPage.vue` desglose modal uses `<UiModal>` (NOT `<Teleport>`); `disabled:opacity-30` absent; `formatCurrency` import from `useFormatters`; `TransactionModal.vue` uses `<UiCard>` (NOT `bg-primary-50`); `type` prop added | extends base + new `assertNoTeleportModal()` + `assertTransactionModalTypeProp()` |
| PR-pagos-04 | `tests/Unit/DesignSystem/QuotationsAppShellTest.php` | 6 Quotations files: no legacy aliases; no `<style scoped>`; `QuotationStatusBadge` is thin wrapper around `<UiStatusBadge>`; numeric columns use `tabular-nums` + `aria-label` | `ModuleAppShellTestCase` + adds `assertQuotationStatusBadgeWrapsUiStatusBadge()` + `assertNumericColumnA11y()` |
| PR-pagos-04 | `tests/Unit/DesignSystem/PaymentMethodsAppShellTest.php` | `PaymentMethodsPage.vue` + `PaymentMethodFormModal.vue`: no legacy aliases; `data-redacted="true"` attribute on `gateway_config` wrapper; raw `gateway_config` value absent from any rendered text node; counters use `tabular-nums` | `ModuleAppShellTestCase` + adds `assertGatewayConfigRedacted()` + `assertNoRawGatewayConfigInDom()` |
| PR-pagos-05 | (extension of `FormatPENLabelTest.php`) | `formatCurrency` exists at exactly one location (`useFormatters.js`); every call site imports from canonical location; `CurrencyInput.vue` formatter unchanged (no formatting fork) | extends base + new `assertFormatCurrencyExistsAtExactlyOneLocation()` |

### 6.3 Per-PR RED-GREEN discipline

Per the archive-report lesson (global design §9.3 line 1: "test pins rule, not example"), every test method asserts a RULE, not a literal string:

- `test_no_legacy_border_theme_literal` — regex-based, not literal-string pin.
- `test_page_references_canvas_token` — accepts `bg-canvas` OR `var(--color-canvas)` OR `rgb(242, 242, 247)`.
- `assertNoRawMoneyInput()` — regex for the raw `<input type="..." v-model="amount...">` pattern; tolerates the `CurrencyInput.vue` source itself.
- `assertGatewayConfigRedacted()` — checks the `data-redacted="true"` attribute is present on the wrapper; does NOT pin the exact wrapper class.

---

## 7. Visual verification (per PR)

Every PR-pagos-NN ships with a `playwright-cli` screenshot of the touched pages for visual regression. The screenshots are saved to `.playwright-cli/screenshots-rollout/` and reviewed against the global design §6 acceptance criteria.

| PR | Screenshots required | Credentials (per `CREDENTIALS.md`) |
|---|---|---|
| PR-pagos-01 | `cash-register-1440x900.png` (tab Pagos + Transacciones); `cash-register-390x844.png` (mobile cashier) | `finanzas@test.com` |
| PR-pagos-02 | `cash-register-payment-modal-manual-1440x900.png`; `cash-register-payment-modal-mercadopago-1440x900.png`; `cash-register-payment-modal-mercadopago-disabled-1440x900.png` (amount=0) | `finanzas@test.com` |
| PR-pagos-03 | `ready-to-bill-1440x900.png`; `ready-to-bill-desglose-1440x900.png` (modal open); `cash-register-transaction-modal-1440x900.png`; `cash-register-close-cash-1440x900.png` | `recep@test.com` (ready-to-bill), `finanzas@test.com` (transaction + close cash) |
| PR-pagos-04 | `quotations-1440x900.png`; `payment-methods-1440x900.png`; `payment-methods-form-modal-1440x900.png` (with `data-redacted="true"` visible) | `finanzas@test.com` |
| PR-pagos-05 | (regression snapshots — re-run PR-pagos-01..04 screenshots to confirm no visual drift from the formatter consolidation) | same as the source PR |

### 7.1 Verification discipline

- Snapshots are saved as PNG (not JPEG) to preserve text sharpness for `S/` prefix legibility.
- Snapshots are reviewed for: legacy alias absence (`border-theme`, `bg-success-100`, `text-accent`, etc.), canvas surface presence (`bg-canvas` visible), focus-ring composition (when tab-cycled), `tabular-nums` on numeric columns, `<UiStatusBadge>` ramps (no `bg-system*-100` heavy borders).
- The visual sweep is documented verification, not a CI gate (per global proposal §4.3).

---

## 8. Risks & mitigations

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | **Apple motion on MercadoPagoCheckout Bricks-loading state may feel slower than the legacy instant-snap.** The cashier has a 300ms debounce; the Bricks loading pill wash (`var(--motion-duration-normal) var(--motion-easing-ios)`) adds visual latency without functional latency. | Medium | Keep the Bricks-loading state minimal (single `<UiLoadingSpinner>` + label); no over-animation. Per-PR visual snapshot at 1440x900 confirms the cashier workflow feels snappy. If a cashier reports latency, reduce the wash duration to `var(--motion-duration-fast)` (still under the 200ms reduced-motion cap). |
| 2 | **`formatCurrency` consolidation could break callers with subtly different signatures.** Some callers today return `S/ X.XX` (with space), some return `S/X.XX` (no space), some return localised strings. The canonical `Intl.NumberFormat('es-PE', { currency: 'PEN' })` emits `S/ X.XX` (with non-breaking space — different from `ReadyToBillPage`'s manual `S/ ${...}` ASCII space). | Medium | Apply phase audits all 4+ call sites BEFORE consolidating. `FormatPENLabelTest` extended to assert canonical output for representative inputs (PEN with 2 decimals, zero, negative, null, undefined, non-numeric). Visual snapshot review confirms no display drift. |
| 3 | **Echo real-time channels must keep firing after primitive swap.** The composable is untouched (`<script>` blocks preserved), but a template change that accidentally re-mounts a component could re-subscribe and break the module-scope subscription count. | Low | Apply phase: `<script>` blocks NEVER touched; UI changes are template-level only. Manual smoke: cashier role sees `payment.registered` update `/dashboard` cash-status pill within 1 second. `PaymentReceivedChannelTest` stays green. |
| 4 | **`PaymentMethod.gateway_config` raw exposure.** The encrypted blob (`Crypt::encryptString`, APP_KEY) must never echo to the DOM. A template change that accidentally renders the decrypted value would leak credentials. | Medium | Explicit `data-redacted="true"` attribute on the form field; `PaymentMethodsAppShellTest::test_gateway_config_redacted` asserts the attribute is present and the raw value is absent from any rendered text node (regex over the rendered HTML). |
| 5 | **Chained PRs may exceed the 400-line budget.** `PaymentModal.vue` (~22.3 KB) is the largest single component; `Quotations` + `Payment Methods` together are 8 files. | Medium | PR-pagos-02 and PR-pagos-04 are both near the budget (~390 and ~380 lines). If a PR's diff exceeds 400 lines, split per `chained-pr` skill: PR-pagos-02a (PaymentModal) + 02b (MercadoPagoCheckout); PR-pagos-04a (Quotations) + 04b (Payment Methods). |
| 6 | **The Caja `useCashRegister` contract has a 300ms debounce; a UI change that accidentally introduces a heavier re-render storm could lag the cashier.** | Low | Apply phase: `<script>` blocks NEVER touched; UI changes are template-level only. If a regression surfaces, isolate via `git revert <pr-sha>` and re-do. The Caja merge commit is tagged with `cash-register-revert-rationale` per global proposal §8. |
| 7 | **Hand-built `<Teleport>` modal in `ReadyToBillPage` (uses `bg-black bg-opacity-60`) must be replaced by `<UiModal>` without changing the modal-open / close behaviour.** | Low | Apply phase: preserve the `open` / `close` / `confirm` emits byte-for-byte; only swap the wrapper markup. Extend `CashRegisterAppShellTest::test_ready_to_bill_modal_uses_ui_modal` to assert `<UiModal>` wrapper is present. |

---

## 9. File changes

### 9.1 Modified files (across PR-pagos-01..05)

| File | Action | PR | Description |
|---|---|---|---|
| `resources/js/modules/cash-register/CashRegisterPage.vue` | Modify | PR-pagos-01 | Template class-string replacement; remove `*:transition` global selector; replace hover-lift with `<UiCard clickable>`; replace status pills with `<UiStatusBadge>`; `formatCurrency` import update. |
| `resources/js/modules/cash-register/components/PaymentModal.vue` | Modify | PR-pagos-02 | Tab strip → `<UiTabs>`; error styling → `var(--focus-ring-default)`; submit button → `<UiLoadingSpinner>`; MercadoPago tab disabled-when-amount-zero. |
| `resources/js/modules/cash-register/components/MercadoPagoCheckout.vue` | Modify | PR-pagos-02 | Apple motion on Bricks-loading pill; `<UiStatusBadge variant="info">` for processing state. |
| `resources/js/modules/cash-register/ReadyToBillPage.vue` | Modify | PR-pagos-03 | `<Teleport>` → `<UiModal>`; `disabled:opacity-30` → `<UiLoadingSpinner>`; `formatCurrency` import update. |
| `resources/js/modules/cash-register/components/TransactionModal.vue` | Modify | PR-pagos-03 | `bg-primary-50` → `<UiCard>`; `animate-spin` → `<UiLoadingSpinner>`; `type` prop added (additive only). |
| `resources/js/modules/cash-register/components/MovementModal.vue` | Modify | PR-pagos-03 | Chrome tokenisation; `formatCurrency` import update. |
| `resources/js/modules/cash-register/components/OpenCashModal.vue` | Modify | PR-pagos-03 | Chrome tokenisation; `formatCurrency` import update. |
| `resources/js/modules/cash-register/components/CloseCashModal.vue` | Modify | PR-pagos-03 | Desglose table → hairline + `tabular-nums`; status pills → `<UiStatusBadge>`; `formatCurrency` import update. |
| `resources/js/modules/cash-register/components/TransactionList.vue` | Modify | PR-pagos-03 | Raw `<input>` + `<select>` → `<UiInput>` / `<UiSelect>`; `border-theme` table → hairline; `tabular-nums` on amounts. |
| `resources/js/modules/cash-register/components/MovementList.vue` | Modify | PR-pagos-03 | Same pattern as `TransactionList`. |
| `resources/js/modules/cash-register/components/SessionList.vue` | Modify | PR-pagos-03 | Same pattern; status pills → `<UiStatusBadge>`; `tabular-nums` on opening/closing amounts. |
| `resources/js/modules/cash-register/components/CashReports.vue` | Modify | PR-pagos-03 | Gradient cards → `<UiCard>`; filter card → hairline + focus ring; `tabular-nums` on totals. |
| `resources/js/modules/cash-register/components/PendingPaymentsList.vue` | Modify | PR-pagos-03 | Raw `<input>` borders → hairline; custom spinner → `<UiLoadingSpinner>`. |
| `resources/js/modules/quotations/QuotationsPage.vue` | Modify | PR-pagos-04 | Template class-string replacement; `tabular-nums` on amounts; status column → `<UiStatusBadge>`. |
| `resources/js/modules/quotations/components/QuotationCard.vue` | Modify | PR-pagos-04 | `<UiCard>` (REPLACES `bg-theme-surface`); status badge. |
| `resources/js/modules/quotations/components/QuotationModal.vue` | Modify | PR-pagos-04 | `<UiModal>` + `<UiInput>` + `<UiSelect>` + `<CurrencyInput>`. |
| `resources/js/modules/quotations/components/QuotationDetail.vue` | Modify | PR-pagos-04 | `<UiStatusBadge>`; `tabular-nums` on line items. |
| `resources/js/modules/quotations/components/QuotationStatusBadge.vue` | Modify | PR-pagos-04 | Migrated to thin wrapper around `<UiStatusBadge variant="...">`. |
| `resources/js/modules/quotations/components/QuotationApprovalModal.vue` | Modify | PR-pagos-04 | `<UiModal>` + `<UiButton>` + `<UiStatusBadge>`. |
| `resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue` | Modify | PR-pagos-04 | `<UiCard>` + counters → `tabular-nums`; status badges. |
| `resources/js/modules/settings/payment-methods/PaymentMethodFormModal.vue` | Modify | PR-pagos-04 | `<UiModal>` + `data-redacted="true"` on `gateway_config` wrapper. |
| `resources/js/composables/useFormatters.js` | Modify | PR-pagos-05 | Rename `formatPENLabel` → `formatCurrency`; keep `formatPENLabel` as backwards-compatible alias. |
| `resources/js/modules/cash-register/ReadyToBillPage.vue` (line 63 `formatCurrency` reimpl) | Modify | PR-pagos-05 | Import `formatCurrency` from `useFormatters`. |
| `resources/js/modules/cash-register/CashRegisterPage.vue` (line 610 `formatCurrency` reimpl) | Modify | PR-pagos-05 | Import `formatCurrency` from `useFormatters`. |
| `resources/js/modules/cash-register/components/CloseCashModal.vue` (line 412 `formatCurrency` reimpl) | Modify | PR-pagos-05 | Import `formatCurrency` from `useFormatters`. |
| `resources/js/modules/cash-register/components/CashReports.vue` (`formatCurrency` reimpl) | Modify | PR-pagos-05 | Import `formatCurrency` from `useFormatters`. |
| (plus 3+ more `formatCurrency` reimpl sites) | Modify | PR-pagos-05 | Import `formatCurrency` from `useFormatters`. |

### 9.2 New files

| File | PR | Description |
|---|---|---|
| `tests/Unit/DesignSystem/CashRegisterAppShellTest.php` | PR-pagos-01 | Extends `ModuleAppShellTestCase`; Caja half coverage. |
| `tests/Unit/DesignSystem/QuotationsAppShellTest.php` | PR-pagos-04 | Extends `ModuleAppShellTestCase`; Quotations half coverage. |
| `tests/Unit/DesignSystem/PaymentMethodsAppShellTest.php` | PR-pagos-04 | Extends `ModuleAppShellTestCase`; Payment Methods half coverage. |

### 9.3 Unchanged files (PAGOS MUST NOT touch)

| File | Why frozen |
|---|---|
| `resources/js/components/ui/CurrencyInput.vue` | Sole canonical money input; consumed as-is per `DLR-R-013` no-new-deps. |
| `resources/js/components/ui/StatusBadge.vue` | PR0 primitive; immutable thereafter per global design §6.1. |
| `resources/js/components/ui/Modal.vue` / `Tabs.vue` / `Button.vue` / `Card.vue` / etc. | Existing primitives; consumed as-is. |
| `AppLayout.canvasRoutes` array literal | PR0 one-shot extension; frozen per global design §3.4. |
| `tokens.js` / `tokens.generated.css` / `scripts/build-tokens-css.mjs` / `tailwind.config.js` | Frozen for entire rollout per `DLR-R-013`. |
| Backend (controllers, services, jobs, listeners, models, migrations) | Out of scope per proposal §3.1. |
| `<script>` blocks of every PAGOS module | Per `PAGOS-CON-001`; UI changes are template-level only. Exception: `TransactionModal.vue` `type` prop addition (additive only). |
| `useCashRegister.js` / `useTransactions.js` / `usePaymentMethods.js` | Composable surface preserved per `ComposablesStandardizationTest`. |
| `PaymentMethod.gateway_config` raw exposure | Redacted via `data-redacted="true"`. |

---

## 10. References

### 10.1 Spec files (PAGOS contract)

| File | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/pagos/spec.md` | The 9 PAGOS scenarios (`PAGOS-MNY-001..002`, `PAGOS-MOD-001`, `PAGOS-RED-001`, `PAGOS-RT-001`, `PAGOS-SCP-001`, `PAGOS-REV-001`, `PAGOS-A11Y-001`, `PAGOS-CON-001`) — the contract this design satisfies. |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` | Cross-cutting `DLR-R-*` rules + per-module `DLR-MOD-007/010/020/021` rows — inherited unmodified. |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` | PR0 contract (`StatusBadge.vue`, `canvasRoutes`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`). |
| `openspec/changes/ui-rollout-all-modules-2026-08/design.md` | Global design — tokens, primitive API, motion durations, focus-ring composition, PHPUnit invariants. |

### 10.2 Source artifacts

| File | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/pagos/explore.md` | PAGOS inventory (frontend, backend, controllers, services, jobs, models, tests, known gotchas). |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/pagos/proposal.md` | PAGOS proposal (intent, scope, risk register, PR chain, success criteria). |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §7.15 | Global PR chain (`PR2` Quotations, `PR9` Caja, `PR11` Payment Methods). |
| `resources/js/composables/useFormatters.js` | Canonical `formatPENLabel` (target for rename to `formatCurrency`). |
| `resources/js/composables/useCashRegister.js` | Echo channel refs (lines 9-12); 300ms debounce; module-scope subscription. |
| `resources/js/composables/useTransactions.js` | Public contract (lines 4-60). |
| `resources/js/components/ui/CurrencyInput.vue` | Sole canonical money input; consumed as-is. |
| `resources/js/components/layout/AppLayout.vue` line 507 | `canvasRoutes` literal (frozen at PR0). |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Abstract base class for `*AppShellTest` subclasses. |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | Forbidden alias pin (extended per PR). |
| `tests/Unit/Composables/PaymentModal401RedirectTest.php` | UXF-021 regression guard. |
| `tests/Unit/Composables/FormatPENLabelTest.php` | Currency formatter guard (extended in PR-pagos-05). |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | Composable surface guard. |
| `CREDENTIALS.md` | `finanzas@test.com`, `recep@test.com` for visual verification. |

### 10.3 Process invariants

1. **Test pins rule, not example** (global design §9.3): `ModuleAppShellTestCase` + per-module subclasses assert RULES, not literal strings. The PAGOS-specific test methods follow the same discipline.
2. **`<script>` blocks NEVER edited** (proposal §11.2 line 7): UI changes are template-level class-string replacement only. Exception: `TransactionModal.vue` `type` prop addition is the single allowed `<script>` edit (additive only; reactivity logic untouched).
3. **Strict TDD forward** (proposal §4.5): every UI replacement comes with a test that proves the new behaviour; RED-GREEN per PR.
4. **Per-PR budget** (`PAGOS-REV-001`): each `pr-pagos-NN` ≤ 400 lines; split rule applied (02a/02b, 04a/04b) if reviewer flags.

---

## 11. What this design does NOT do

- Does NOT add new tokens. `tokens.js` is frozen.
- Does NOT add new primitives. PAGOS consumes `<UiStatusBadge>` (PR0), `<UiModal>`, `<UiTabs>`, `<UiButton>`, `<UiInput>`, `<UiSelect>`, `<UiCard>`, `<UiLoadingSpinner>`, `<UiEmptyState>`, `<CurrencyInput>`, `<UiPagination>` from the proven set.
- Does NOT add dark mode.
- Does NOT touch the backend (no controller, no service, no listener, no migration, no job).
- Does NOT relax any standing guard rail from §0.
- Does NOT introduce `<style scoped>` blocks (or carry them as grandfathered).
- Does NOT touch `<script>` blocks of any PAGOS module — UI changes are template-level only. (Exception: `TransactionModal.vue` `type` prop addition, additive only.)
- Does NOT change `useCashRegister` reactivity contract, debounce, or channel subscription.
- Does NOT change `PaymentModal` 401-redirect-on-createTransaction-failure behaviour (UXF-021 stays green).
- Does NOT touch `ProcessMercadoPagoWebhook` retry / idempotency.
- Does NOT expose `PaymentMethod.gateway_config` raw.
- Does NOT add a new payment gateway (Niubiz, Izipay, Culqi, Stripe, etc.).
- Does NOT add multi-currency or currency conversion. PEN only.
- Does NOT touch insurance claims, quotation template editor, or BI revenue dashboard visuals.
- Does NOT bundle the `formatCurrency` consolidation with PR-pagos-01 (over-budget); consolidation rides PR-pagos-05 after all call sites are migrated.

---

*End of PAGOS category design.*