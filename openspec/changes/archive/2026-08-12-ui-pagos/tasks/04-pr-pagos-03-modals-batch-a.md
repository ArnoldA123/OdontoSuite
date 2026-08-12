# PR-pagos-03 — TransactionModal + MovementModal + OpenCashModal + CloseCashModal

> **Change**: `ui-rollout-all-modules-2026-08` — PAGOS category
> **Date**: 2026-08-12
> **PR scope**: PR-pagos-03 only
> **Branch base**: `main` (stacked after PR-pagos-02)
> **Review budget**: 400 authored lines / PR (target ~340)
> **Strict TDD**: true

## Goal

Polish the 4 cash-flow modals to consume the proven primitives: `TransactionModal` (`bg-primary-50` patient banner → `<UiCard>`, `animate-spin` → `<UiLoadingSpinner>`, additive `type` prop), `MovementModal` + `OpenCashModal` chrome tokenisation, `CloseCashModal` desglose table → hairline + `tabular-nums` + `<UiStatusBadge>`. Per design Key Learning 4, `TransactionModal` receives an ADDITIVE `type` prop — the only `<script>` block edit allowed in any PAGOS PR (reactivity logic untouched).

## Depends on

- PR0 (landed): primitives + `canvasRoutes`.
- PR-pagos-01 (landed): `formatCurrency` consolidated.
- PR-pagos-02 (landed): list/report views polished; `CashRegisterAppShellTest` base established.

## Work items (ordered; foundation first, visual last)

- [ ] **T-03.1** — RED: extend `tests/Unit/DesignSystem/CashRegisterAppShellTest.php` with `assertNoTeleportModal()` (regex over `<Teleport to="body">` in the Caja module) and `assertTransactionModalTypeProp()` (regex over the `type` prop declaration in `<script setup>`). Run PHPUnit: RED.
- [ ] **T-03.2** — Migrate `TransactionModal.vue`: REPLACE `bg-primary-50` patient banner block with `<UiCard>`; REPLACE `animate-spin` with `<UiLoadingSpinner>`; status pills (Ingreso/Egreso) → `<UiStatusBadge variant="success|error">`; format amounts via canonical `formatCurrency` import.
- [ ] **T-03.3** — Additive `<script>` edit on `TransactionModal.vue`: add `type: { type: String, default: 'payment', validator: v => ['payment', 'refund'].includes(v) }` to the `defineProps` block. Caller (e.g. `TransactionList.vue` "Devolución" button) passes `type="refund"`. The 401 redirect code path in `useCashRegister` is untouched. `defineEmits` + `useCashRegister` reactivity preserved byte-for-byte.
- [ ] **T-03.4** — Migrate `MovementModal.vue`: chrome tokenisation (raw `<input>` → `<UiInput>`, raw `<select>` → `<UiSelect>`); `<CurrencyInput>` consumed as-is (sole money input per `PAGOS-MNY-001`); format amounts via canonical `formatCurrency` import.
- [ ] **T-03.5** — Migrate `OpenCashModal.vue`: chrome tokenisation; `<CurrencyInput>` consumed as-is; `<UiEmptyState>` for the no-active-session fallback. Format amounts via canonical `formatCurrency` import.
- [ ] **T-03.6** — Migrate `CloseCashModal.vue`: desglose table → `border-hairline` + `font-feature-settings: var(--font-features-tabular-nums)` on totals; per-method status pills → `<UiStatusBadge variant="...">`; format amounts via canonical `formatCurrency` import (no local `Intl.NumberFormat` reimpl).
- [ ] **T-03.7** — GREEN: `CashRegisterAppShellTest` now passes `assertNoTeleportModal()` (zero matches) + `assertTransactionModalTypeProp()` (prop declared with default `'payment'` and validator constraining to `['payment', 'refund']`). Add `test_close_cash_modal_uses_tabular_nums_on_totals` + `test_modals_use_ui_status_badge`. Run PHPUnit: GREEN.
- [ ] **T-03.8** — Regression: `git grep -nE "bg-primary-50|animate-spin|border-theme"` on the 4 modal files returns zero matches.
- [ ] **T-03.9** — Tests: `php artisan test --filter=CashRegisterAppShellTest` + `PaymentModal401RedirectTest` + `ComposablesStandardizationTest` + `FormatPENLabelTest` all green.
- [ ] **T-03.10** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-03.11** — Visual smoke: open `TransactionModal` (type=payment) and (type=refund) via the cashier UI; confirm the modal title + primary button label + status badge follow the `PAGOS-MOD-001-1` ramp mapping. Open `CloseCashModal` from an active session; confirm desglose table renders with hairline borders and `tabular-nums` on totals. Save screenshots: `pr-pagos-03-transaction-modal-payment-1440x900.png`, `pr-pagos-03-transaction-modal-refund-1440x900.png`, `pr-pagos-03-close-cash-1440x900.png`. Login: `finanzas@test.com`.
- [ ] **T-03.12** — Visual smoke (open cash): `pr-pagos-03-open-cash-1440x900.png` + `pr-pagos-03-movement-modal-1440x900.png`. Confirm `<UiModal>` wrapper, `<UiInput>`/`<UiSelect>` controls, `<UiLoadingSpinner>` on submit. Save under `.playwright-cli/screenshots-rollout/pr-pagos-03-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=CashRegisterAppShellTest` green (extended with the 4 new assertions).
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] No `<Teleport to="body">` in any of the 4 modals.
- [ ] `TransactionModal` declares the `type` prop (additive only) and accepts `'payment' | 'refund'`.
- [ ] No raw `<input type="number" v-model="amount">` outside `CurrencyInput.vue`.
- [ ] `formatCurrency` imported from `@/composables/useFormatters` in all 4 modals (no local reimpl).
- [ ] `<script>` blocks of `MovementModal` / `OpenCashModal` / `CloseCashModal` are byte-for-byte unchanged.
- [ ] `TransactionModal` `<script>` block diff is restricted to the additive `type` prop; `useCashRegister` reactivity, debounce, and channel subscription preserved.
- [ ] No regression in `PaymentModal401RedirectTest`, `ComposablesStandardizationTest`, `FormatPENLabelTest`, `RequireActiveCashSessionTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`.
- [ ] PR diff under 400 lines.
- [ ] 5 screenshots saved under `.playwright-cli/screenshots-rollout/pr-pagos-03-*.png`.

## Out of scope (deferred)

- `PaymentModal` + `MercadoPagoCheckout` — PR-pagos-04.
- `PaymentMethod.gateway_config` redaction — PR-pagos-04.
- Pages (CashRegister, ReadyToBill, Quotations, PaymentMethods) — PR-pagos-05.

## Test plan (commands)

```bash
php artisan test --filter=CashRegisterAppShellTest
php artisan test --filter=PaymentModal401RedirectTest
php artisan test --filter=ComposablesStandardizationTest
pnpm build
pnpm lint:check
git grep -nE "bg-primary-50|animate-spin|Teleport to" \
  resources/js/modules/cash-register/components/TransactionModal.vue \
  resources/js/modules/cash-register/components/MovementModal.vue \
  resources/js/modules/cash-register/components/OpenCashModal.vue \
  resources/js/modules/cash-register/components/CloseCashModal.vue
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-03-transaction-modal-payment-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-03-transaction-modal-refund-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-03-close-cash-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-03-open-cash-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-03-movement-modal-1440x900.png
```

## Key Learnings (forwarded to apply)

1. `formatCurrency` is the single canonical helper (PR-pagos-01). All 4 modals import from `@/composables/useFormatters`; zero local reimpls.
2. Echo channels are owned by `useCashRegister.js:9-12` and never redeclared in any modal file. The 4 modals consume the existing composable unchanged.
3. `TransactionModal.type` prop is the single allowed `<script>` block edit in any PAGOS PR (additive only; reactivity untouched per `PAGOS-CON-001`).

## References

- `categories/pagos/design.md` §2 (surface map for the 4 modals), §3.2 (TransactionModal vocabulary decision), §6.2 (PR-pagos-03 test extensions)
- `categories/pagos/spec.md` `PAGOS-MOD-001`, `PAGOS-MNY-001`, `PAGOS-MNY-002`, `PAGOS-CON-001`
- `tests/Unit/DesignSystem/CashRegisterAppShellTest.php` (extending base)
- `resources/js/modules/cash-register/components/TransactionModal.vue` (line 412 — `bg-primary-50` patient banner block to replace)
