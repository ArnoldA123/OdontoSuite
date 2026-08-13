# PR-pagos-05 — pages + final accessibility pass

> **Change**: `ui-rollout-all-modules-2026-08` — PAGOS category
> **Date**: 2026-08-12
> **PR scope**: PR-pagos-05 only
> **Branch base**: `main` (stacked after PR-pagos-04)
> **Review budget**: 400 authored lines / PR (target ~380)
> **Strict TDD**: true

## Goal

Polish the 4 PAGOS pages + Quotations + final accessibility pass: `CashRegisterPage` (hub — last to land so it can assume every modal + list is tokenised), `ReadyToBillPage` (hand-built `<Teleport>` → `<UiModal>`, `disabled:opacity-30` → `<UiLoadingSpinner>`), `QuotationsPage` + 5 components (`QuotationStatusBadge` migrated to thin `<UiStatusBadge>` wrapper), `PaymentMethodsPage` (counters → `tabular-nums`). Per spec `PAGOS-A11Y-001`, add `scope="col"` + `aria-label` currency context to every tabular numeric column. Plus `QuotationsAppShellTest` and the final regression sweep.

## Depends on

- PR0 (landed).
- PR-pagos-01..04 (landed): `formatCurrency` consolidated, lists + modals + PaymentModal + PaymentMethodFormModal tokenised.

## Work items (ordered; foundation first, visual last)

- [ ] **T-05.1** — RED: NEW `tests/Unit/DesignSystem/QuotationsAppShellTest.php` extending `ModuleAppShellTestCase`. Add `assertQuotationStatusBadgeWrapsUiStatusBadge()` (regex asserts `QuotationStatusBadge.vue` references `<UiStatusBadge variant="...">`) and `assertNumericColumnA11y()` (regex asserts `scope="col"` + `aria-label="...soles"` on every numeric `<th>`). Run PHPUnit: RED.
- [ ] **T-05.2** — Migrate `CashRegisterPage.vue` (the hub): REPLACE `*:transition` global selector (legacy pattern) with per-element transitions; REPLACE `hover-lift` with `<UiCard clickable>`; REPLACE status pills with `<UiStatusBadge>`; tab strip → `<UiTabs>`; real-time cards → hairline + `<UiCard>`; format amounts via canonical `formatCurrency` import (the line 610 site migrated in PR-pagos-01; confirm the import is wired).
- [ ] **T-05.3** — Migrate `ReadyToBillPage.vue`: REPLACE hand-built `<Teleport to="body">` + `<div class="fixed inset-0 bg-black bg-opacity-60">` with `<UiModal :open="showBreakdown" @close="showBreakdown = false" @confirm="confirmBreakdown" />`; REPLACE `:disabled="!canSubmit"` + `class="disabled:opacity-30"` with `<UiButton :disabled="!canSubmit || loading" :loading="loading">` (inside-button `<UiLoadingSpinner v-if="loading" />`); the `open` / `close` / `confirm` emits preserved byte-for-byte; format amounts via canonical `formatCurrency` import (line 63 site migrated in PR-pagos-01; confirm import is wired).
- [ ] **T-05.4** — Migrate `QuotationsPage.vue` + 5 components (`QuotationCard`, `QuotationModal`, `QuotationDetail`, `QuotationStatusBadge`, `QuotationApprovalModal`): REPLACE `bg-theme-surface` with `<UiCard>`; REPLACE raw borders with hairline; REPLACE status pills with `<UiStatusBadge>`; `QuotationStatusBadge.vue` becomes a thin wrapper around `<UiStatusBadge variant="..." :label="...">` (first consumer per `DLR-MOD-010`); numeric columns use `font-feature-settings: var(--font-features-tabular-nums)`.
- [ ] **T-05.5** — Migrate `PaymentMethodsPage.vue`: counters → `tabular-nums`; status badges (active/inactive) → `<UiStatusBadge variant="success|neutral">`; raw borders → hairline; raw controls → `<UiInput>`/`<UiSelect>`; format amounts via canonical `formatCurrency` import.
- [ ] **T-05.6** — GREEN: `QuotationsAppShellTest` passes all alias assertions. Extend `CashRegisterAppShellTest` with `assertReadyToBillModalUsesUiModal()` (asserts `<UiModal>` wrapper is present on `ReadyToBillPage.vue`; the `bg-black bg-opacity-60` literal is absent). Run PHPUnit: GREEN.
- [ ] **T-05.7** — Accessibility pass: add `scope="col"` + `aria-label="Monto en soles"` (or equivalent) on every numeric `<th>` in `QuotationsPage` + `QuotationDetail` + `PaymentMethodsPage` (per `PAGOS-A11Y-001`). Add `<th scope="row">` to row headers in tabular lists. Verify `QuotationsAppShellTest::assertNumericColumnA11y` passes.
- [ ] **T-05.8** — Final regression sweep: `git grep -nE "hover-lift|disabled:opacity-30|border-theme|\*:transition|bg-black bg-opacity-60|bg-primary-50|bg-success-100|text-accent|focus:ring-primary-500"` across `resources/js/modules/cash-register/**` + `resources/js/modules/quotations/**` + `resources/js/modules/settings/payment-methods/**` returns ZERO matches (the full PAGOS surface).
- [ ] **T-05.9** — Tests: `php artisan test --filter=CashRegisterAppShellTest` + `--filter=QuotationsAppShellTest` + `--filter=PaymentMethodsAppShellTest` + `--filter=PaymentModal401RedirectTest` + `--filter=ComposablesStandardizationTest` + `--filter=FormatPENLabelTest` + `--filter=AppLayoutCanvasRoutesTest` + `--filter=LegacyAliasForbiddenTest` + `--filter=PaymentReceivedChannelTest` + `--filter=RequireActiveCashSessionTest` all GREEN.
- [ ] **T-05.10** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-05.11** — Mobile snapshots: `playwright-cli` at 390x844 (cashier mobile path) for `cash-register-390x844.png` + `ready-to-bill-390x844.png` (Caja + Ready-to-Bill only per design §7). Login: `finanzas@test.com` (Caja), `recep@test.com` (Ready-to-Bill).
- [ ] **T-05.12** — Final 1440x900 visual sweep: `cash-register-1440x900.png` (Pagos tab + Transacciones tab) + `ready-to-bill-1440x900.png` + `ready-to-bill-desglose-1440x900.png` (modal open) + `quotations-1440x900.png` + `payment-methods-1440x900.png`. Save under `.playwright-cli/screenshots-rollout/pr-pagos-05-*.png`. CI gate: `frontend-build` + `backend-tests` (MySQL) + `quality` all green.

## Acceptance criteria

- [ ] `php artisan test --filter=QuotationsAppShellTest` GREEN.
- [ ] `php artisan test --filter=CashRegisterAppShellTest` GREEN (extended with `assertReadyToBillModalUsesUiModal`).
- [ ] `php artisan test --filter=PaymentMethodsAppShellTest` GREEN.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] `git grep` on the full PAGOS surface (cash-register + quotations + payment-methods) returns ZERO legacy alias matches.
- [ ] `QuotationStatusBadge.vue` is a thin wrapper around `<UiStatusBadge variant="...">` (first consumer per `DLR-MOD-010`).
- [ ] `ReadyToBillPage.vue` desglose modal uses `<UiModal>` (NOT `<Teleport to="body">` + `bg-black bg-opacity-60`).
- [ ] `disabled:opacity-30` absent from `ReadyToBillPage.vue`; affordance uses `<UiLoadingSpinner>` + `<UiButton :loading>`.
- [ ] Every numeric `<th>` in `QuotationsPage` + `QuotationDetail` + `PaymentMethodsPage` has `scope="col"` + `aria-label` currency context (per `PAGOS-A11Y-001`).
- [ ] No regression in `PaymentModal401RedirectTest`, `ComposablesStandardizationTest`, `FormatPENLabelTest`, `RequireActiveCashSessionTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `PaymentReceivedChannelTest`, `LogPaymentReceivedTest`, `TransactionEndpointsTest`, `CashRegisterEndpointsTest`, `CashCloseAndClosureReportTest`.
- [ ] Echo real-time smoke: cashier role sees `/dashboard` cash-status pill update within 1 second of a manual payment capture.
- [ ] PR diff under 400 lines.
- [ ] 7 screenshots saved under `.playwright-cli/screenshots-rollout/pr-pagos-05-*.png` (5 desktop + 2 mobile).

## Out of scope (deferred to follow-up change)

- Backend business logic (controllers / services / jobs / listeners / models / migrations).
- `ProcessMercadoPagoWebhook` retry / idempotency semantics.
- New payment gateways (Niubiz, Izipay, Culqi, Stripe) — MercadoPago is the only gateway.
- Multi-currency or currency conversion — PEN only.
- Quotation template editor (PDF layout, terms text).
- BI revenue dashboard visuals (`/business-intelligence`).
- Insurance claim flows.

## Test plan (commands)

```bash
php artisan test --filter=CashRegisterAppShellTest
php artisan test --filter=QuotationsAppShellTest
php artisan test --filter=PaymentMethodsAppShellTest
php artisan test --filter=PaymentModal401RedirectTest
php artisan test --filter=ComposablesStandardizationTest
php artisan test --filter=FormatPENLabelTest
php artisan test --filter=AppLayoutCanvasRoutesTest
php artisan test --filter=LegacyAliasForbiddenTest
php artisan test --filter=PaymentReceivedChannelTest
pnpm build
pnpm lint:check
git grep -nE "hover-lift|disabled:opacity-30|border-theme|\\*:transition|bg-black bg-opacity-60|bg-primary-50|bg-success-100|text-accent|focus:ring-primary-500" \
  resources/js/modules/cash-register resources/js/modules/quotations resources/js/modules/settings/payment-methods
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-05-cash-register-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register/ready-to-bill 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-05-ready-to-bill-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register/ready-to-bill 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-05-ready-to-bill-desglose-1440x900.png
playwright-cli screenshot http://localhost:5173/quotations 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-05-quotations-1440x900.png
playwright-cli screenshot http://localhost:5173/settings/payment-methods 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-05-payment-methods-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register 390x844 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-05-cash-register-390x844.png
playwright-cli screenshot http://localhost:5173/cash-register/ready-to-bill 390x844 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-05-ready-to-bill-390x844.png
```

## Key Learnings (forwarded to apply)

1. `formatCurrency` is the single canonical helper (from PR-pagos-01). Every page + component imports from `@/composables/useFormatters`; no local reimpl anywhere in the PAGOS surface.
2. Echo channels `cash-register`, `.cash-session.opened/closed`, `.payment.registered`, `.cash-movement.created` (per `useCashRegister.js:9-12`) are consumed unchanged by every page. No new channels in any PAGOS file.
3. `QuotationStatusBadge.vue` becomes the first consumer of `<UiStatusBadge variant="...">` — a thin wrapper that maps the quotation status domain (`pending`, `approved`, `rejected`, `expired`, `converted`) to the variant ramp.
4. `ReadyToBillPage.vue` is the LAST file to land because it consumes modals + lists that PR-pagos-02..03 polished. Its `useApi` 401 redirect is composable-owned (not component-owned), so the `<UiModal>` swap does not touch the redirect path.
5. Final accessibility pass enforces `scope="col"` + `aria-label="Monto en soles"` on every numeric `<th>` per `PAGOS-A11Y-001`; `QuotationsAppShellTest::assertNumericColumnA11y` is the regression guard.

## References

- `categories/pagos/design.md` §2 (page surface map for CashRegister + ReadyToBill + Quotations + PaymentMethods), §3.3 (ReadyToBill modal migration), §3.7 (Echo channel reuse), §6.1 (existing tests must stay green), §6.2 (PR-pagos-05 test extensions), §7 (visual verification per PR)
- `categories/pagos/spec.md` `PAGOS-MOD-001`, `PAGOS-MNY-002`, `PAGOS-A11Y-001`, `PAGOS-CON-001`, `PAGOS-REV-001`
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base — `QuotationsAppShellTest` + `PaymentMethodsAppShellTest` extend it)
- `resources/js/composables/useCashRegister.js:9-12` (Echo channel set)
- `resources/js/composables/useFormatters.js` (canonical `formatCurrency` + `formatPENLabel` alias)
- `CREDENTIALS.md` (`finanzas@test.com` for Caja + Quotations + Payment Methods; `recep@test.com` for Ready-to-Bill)
