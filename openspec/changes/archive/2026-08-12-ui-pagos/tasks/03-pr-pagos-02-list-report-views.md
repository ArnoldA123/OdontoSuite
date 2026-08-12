# PR-pagos-02 — list/report views polish

> **Change**: `ui-rollout-all-modules-2026-08` — PAGOS category
> **Date**: 2026-08-12
> **PR scope**: PR-pagos-02 only
> **Branch base**: `main` (stacked after PR-pagos-01)
> **Review budget**: 400 authored lines / PR (target ~340)
> **Strict TDD**: true

## Goal

Polish the 5 list/report cash-register views (`TransactionList`, `MovementList`, `SessionList`, `CashReports`, `PendingPaymentsList`) to consume the proven primitives: raw `<input>`/`<select>` → `<UiInput>`/`<UiSelect>`, `border-theme` table → hairline, status pills → `<UiStatusBadge>`, custom spinners → `<UiLoadingSpinner>`, `tabular-nums` on every numeric column. Template-only changes; `<script>` blocks stay byte-for-byte unchanged (per `PAGOS-CON-001`).

## Depends on

- PR0 (landed): `canvasRoutes`, `<UiStatusBadge>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`.
- PR-pagos-01 (landed): `formatCurrency` consolidated to `useFormatters.js`.

## Work items (ordered; foundation first, visual last)

- [ ] **T-02.1** — RED: create `tests/Unit/DesignSystem/CashRegisterAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return the 5 list/report files + 2 modal files (full Caja half). Add `assertNoRawMoneyInput()` (regex over the module tree). Run PHPUnit: RED on list/report alias assertions.
- [ ] **T-02.2** — Migrate `TransactionList.vue`: replace raw `<input>` (search) with `<UiInput>`, raw `<select>` (filters) with `<UiSelect>`, `border-theme` table borders with `border-hairline` (= `rgba(60, 60, 67, 0.12)`), `tabular-nums` on amount columns, status column → `<UiStatusBadge variant="...">`, no-results → `<UiEmptyState>`. Import `formatCurrency` from `@/composables/useFormatters` (no local reimpl).
- [ ] **T-02.3** — Migrate `MovementList.vue`: same pattern as T-02.2. Status pills (income/expense/withdrawal/deposit/adjustment) map to `<UiStatusBadge variant="...">` ramps. Custom spinner → `<UiLoadingSpinner>`.
- [ ] **T-02.4** — Migrate `SessionList.vue`: raw controls → `<UiInput>`/`<UiSelect>`, status (open/closed) → `<UiStatusBadge variant="success|neutral">`, opening/closing amounts → `font-feature-settings: var(--font-features-tabular-nums)`.
- [ ] **T-02.5** — Migrate `CashReports.vue`: REPLACE gradient cards with `<UiCard>`; date range filter card → hairline + `var(--focus-ring-default)` on the date inputs; `tabular-nums` on all totals; format amounts via canonical `formatCurrency` import.
- [ ] **T-02.6** — Migrate `PendingPaymentsList.vue`: raw `<input>` borders → hairline; custom spinner → `<UiLoadingSpinner>`; status badges → `<UiStatusBadge>`.
- [ ] **T-02.7** — GREEN: `CashRegisterAppShellTest` now passes all alias assertions. Extend with `test_lists_use_ui_status_badge` + `test_lists_use_hairline_not_border_theme` + `test_lists_no_raw_money_input`. Run PHPUnit: GREEN.
- [ ] **T-02.8** — Accessibility pass: add `scope="col"` to every `<th>` + `aria-label="Monto en soles"` (or equivalent) on numeric columns in all 5 list/report files (per spec `PAGOS-A11Y-001`).
- [ ] **T-02.9** — Regression: `git grep -nE "border-theme|bg-success-100|text-accent|focus:ring-primary-500"` on the 5 list/report files returns zero matches.
- [ ] **T-02.10** — Tests: `php artisan test --filter=CashRegisterAppShellTest` + `--filter=PaymentModal401RedirectTest` + `--filter=ComposablesStandardizationTest` + `--filter=FormatPENLabelTest` all green.
- [ ] **T-02.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-02.12** — Visual: `playwright-cli` snapshots at 1440x900 — `cash-register-transactions-tab-1440x900.png`, `cash-register-movements-tab-1440x900.png`, `cash-register-history-tab-1440x900.png`, `cash-register-reports-tab-1440x900.png`, `ready-to-bill-pending-list-1440x900.png`. Login: `finanzas@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-pagos-02-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=CashRegisterAppShellTest` green.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] No legacy alias matches inside the 5 list/report files (grep-verified).
- [ ] No raw `<input type="number" v-model="amount">` outside `CurrencyInput.vue` (per `PAGOS-MNY-001`).
- [ ] `tabular-nums` applied to every numeric column (verified by `CashRegisterAppShellTest` rule assertion + visual).
- [ ] `<script>` blocks of all 5 list/report files are byte-for-byte unchanged.
- [ ] No regression in `PaymentModal401RedirectTest`, `ComposablesStandardizationTest`, `FormatPENLabelTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`.
- [ ] PR diff under 400 lines.
- [ ] 5 screenshots saved under `.playwright-cli/screenshots-rollout/pr-pagos-02-*.png`.

## Out of scope (deferred)

- `PaymentModal` + `MercadoPagoCheckout` — PR-pagos-04.
- `TransactionModal` + `MovementModal` + `OpenCashModal` + `CloseCashModal` — PR-pagos-03.
- `CashRegisterPage` template chrome — PR-pagos-05.
- `ReadyToBillPage` modal migration — PR-pagos-05.

## Test plan (commands)

```bash
php artisan test --filter=CashRegisterAppShellTest
php artisan test --filter=PaymentModal401RedirectTest
php artisan test --filter=ComposablesStandardizationTest
pnpm build
pnpm lint:check
git grep -nE "border-theme|bg-success-100|text-accent" \
  resources/js/modules/cash-register/components/TransactionList.vue \
  resources/js/modules/cash-register/components/MovementList.vue \
  resources/js/modules/cash-register/components/SessionList.vue \
  resources/js/modules/cash-register/components/CashReports.vue \
  resources/js/modules/cash-register/components/PendingPaymentsList.vue
playwright-cli screenshot http://localhost:5173/cash-register?tab=transactions 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-02-transactions-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register?tab=movements 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-02-movements-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register?tab=history 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-02-history-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register?tab=reports 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-02-reports-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register/ready-to-bill 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-02-ready-to-bill-1440x900.png
```

## Key Learnings (forwarded to apply)

1. `formatCurrency` already canonical at `useFormatters.js` from PR-pagos-01 — all 5 list/report files import it; no local reimpl.
2. Echo channels `cash-register`, `.cash-session.opened/closed`, `.payment.registered`, `.cash-movement.created` (per `useCashRegister.js:9-12`) — list/report views consume them via the composable; no new channels introduced.

## References

- `categories/pagos/design.md` §2 (surface map rows for the 5 list/report files), §3.4 (CurrencyInput sole money input), §6.2 (per-PR new tests)
- `categories/pagos/spec.md` `PAGOS-MOD-001`, `PAGOS-MNY-001`, `PAGOS-A11Y-001`, `PAGOS-RT-001`
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base class to extend)
- `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` (forbidden alias list — extend if new patterns found)
