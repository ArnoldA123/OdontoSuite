# PR-pagos-04 — PaymentModal + MercadoPagoCheckout + 401 redirect + PaymentMethodFormModal redaction

> **Change**: `ui-rollout-all-modules-2026-08` — PAGOS category
> **Date**: 2026-08-12
> **PR scope**: PR-pagos-04 only
> **Branch base**: `main` (stacked after PR-pagos-03)
> **Review budget**: 400 authored lines / PR (target ~390 — right at budget; split into 04a + 04b if reviewer flags)
> **Strict TDD**: true

## Goal

Polish the real-money UX: `PaymentModal.vue` (tab strip → `<UiTabs>`, error styling → `var(--focus-ring-default)`, submit → `<UiLoadingSpinner>`, MercadoPago tab disabled when amount ≤ 0) and `MercadoPagoCheckout.vue` (Apple motion on Bricks-loading pill). Verify `PaymentModal401RedirectTest` (UXF-021) stays green. Add `PaymentMethodFormModal.vue` redaction wrapper with `data-redacted="true"` on the `gateway_config` field (per design Key Learning 5).

## Depends on

- PR0 (landed): primitives + `canvasRoutes`.
- PR-pagos-01..03 (landed): `formatCurrency` consolidated, lists polished, modals polished.

## Work items (ordered; foundation first, visual last)

- [ ] **T-04.1** — RED: extend `tests/Unit/DesignSystem/CashRegisterAppShellTest.php` with `assertModalUsesUiPrimitives()` (regex asserts `<UiModal>` + `<UiTabs>` + `<UiButton>` + `<UiStatusBadge>` references in `PaymentModal.vue`; no raw `<button class="...">` tab strip; no `bg-black bg-opacity-60`) and `assertMercadoPagoTabDisabledWhenAmountZero()` (regex asserts the MercadoPago tab is `:disabled` when `amount <= 0`). Run PHPUnit: RED.
- [ ] **T-04.2** — Migrate `PaymentModal.vue`: REPLACE raw `<button class="...">` tab strip with `<UiTabs>` (Manual = active default, MercadoPago = secondary); REPLACE `border-red-500` error styling with `var(--focus-ring-default)`; REPLACE submit button visual with `<UiLoadingSpinner>` inside `<UiButton :loading="loading" :disabled="!canSubmit || loading">`; MercadoPago tab `:disabled="amount <= 0 || paymentMethod.gateway_type !== 'mercadopago'"` with a small `<UiStatusBadge variant="warning" size="sm" label="Ingrese monto" />` hint below the strip when disabled.
- [ ] **T-04.3** — Migrate `MercadoPagoCheckout.vue`: Bricks-loading state gets `var(--motion-duration-normal) var(--motion-easing-ios)` wash (single `<UiLoadingSpinner>` + label — no over-animation per design §8 risk #1). Processing state uses `<UiStatusBadge variant="info">`. All buttons → `<UiButton>`.
- [ ] **T-04.4** — RED: NEW `tests/Unit/DesignSystem/PaymentMethodsAppShellTest.php` extending `ModuleAppShellTestCase`. Add `assertGatewayConfigRedacted()` (regex asserts `data-redacted="true"` attribute on the `gateway_config` wrapper) and `assertNoRawGatewayConfigInDom()` (regex asserts the decrypted value never appears in any rendered text node). Run PHPUnit: RED.
- [ ] **T-04.5** — Migrate `PaymentMethodFormModal.vue` (create/edit form): wrap `gateway_config` field in `<UiInput type="password" :model-value="REDACTED_PLACEHOLDER" :data-redacted="true" />`. The decrypted blob is NEVER echoed into any rendered text node. Submit posts the new value only when the admin types one; leaving the field blank preserves the existing encrypted blob (no DOM round-trip).
- [ ] **T-04.6** — GREEN: `PaymentMethodsAppShellTest::test_gateway_config_redacted` passes (attribute present, raw blob absent from rendered text). `CashRegisterAppShellTest::assertModalUsesUiPrimitives` + `assertMercadoPagoTabDisabledWhenAmountZero` pass. Run PHPUnit: GREEN.
- [ ] **T-04.7** — Regression sweep: `git grep -nE "focus:ring-primary-500|bg-black bg-opacity-60|focus:border-accent"` on `PaymentModal.vue` + `MercadoPagoCheckout.vue` + `PaymentMethodFormModal.vue` returns zero matches. `git grep -nE "gateway_config"` on the form modal returns the `data-redacted` reference only.
- [ ] **T-04.8** — UXF-021 boundary check: confirm `PaymentModal.vue` `<script>` block is byte-for-byte unchanged (the 401 redirect code path in `handleSubmit` + `switchToMercadoPago` is the regression guard for `PaymentModal401RedirectTest`). `git diff` on the `<script>` block returns zero lines.
- [ ] **T-04.9** — Tests: `php artisan test --filter=PaymentModal401RedirectTest` GREEN (UXF-021 unchanged). `ComposablesStandardizationTest` + `FormatPENLabelTest` + `AppLayoutCanvasRoutesTest` + `LegacyAliasForbiddenTest` + `PaymentReceivedChannelTest` all green.
- [ ] **T-04.10** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-04.11** — Visual: `playwright-cli` snapshots of `PaymentModal` Manual + MercadoPago tabs + MercadoPago-disabled (amount=0) state + `MercadoPagoCheckout` Bricks-loading + `PaymentMethodFormModal` (MercadoPago config redacted). Save under `.playwright-cli/screenshots-rollout/pr-pagos-04-payment-modal-manual-1440x900.png` + `pr-pagos-04-payment-modal-mercadopago-1440x900.png` + `pr-pagos-04-payment-modal-mercadopago-disabled-1440x900.png` + `pr-pagos-04-mercadopago-checkout-1440x900.png` + `pr-pagos-04-payment-methods-form-1440x900.png`. Login: `finanzas@test.com`.
- [ ] **T-04.12** — Echo real-time smoke: cashier role captures a manual payment in `PaymentModal`; confirm `/dashboard` cash-status pill updates within 1 second (the `payment.registered` Echo broadcast fires unchanged). Log the latency in the apply-progress journal.

## Acceptance criteria

- [ ] `php artisan test --filter=PaymentModal401RedirectTest` GREEN (UXF-021 unchanged).
- [ ] `php artisan test --filter=CashRegisterAppShellTest` GREEN (extended with the 2 new assertions).
- [ ] `php artisan test --filter=PaymentMethodsAppShellTest` GREEN (`test_gateway_config_redacted` passes).
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] No `bg-black bg-opacity-60` overlay anywhere in `PaymentModal.vue` or `MercadoPagoCheckout.vue`.
- [ ] No raw `<button class="...">` for the tab strip in `PaymentModal.vue`.
- [ ] `PaymentMethodFormModal.vue` renders `data-redacted="true"` on the `gateway_config` wrapper; raw decrypted blob absent from rendered text.
- [ ] `<script>` block of `PaymentModal.vue` is byte-for-byte unchanged (UXF-021 contract).
- [ ] No regression in `ComposablesStandardizationTest`, `FormatPENLabelTest`, `RequireActiveCashSessionTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `PaymentReceivedChannelTest`.
- [ ] Echo `payment.registered` smoke test updates `/dashboard` cash-status pill within 1 second.
- [ ] PR diff under 400 lines.
- [ ] 5 screenshots saved under `.playwright-cli/screenshots-rollout/pr-pagos-04-*.png`.

## Out of scope (deferred to PR-pagos-05)

- Pages (CashRegister, ReadyToBill, Quotations, PaymentMethods) — PR-pagos-05.
- Quotations 6 files + `QuotationStatusBadge` → `<UiStatusBadge>` migration.
- `ReadyToBillPage` `<Teleport>` → `<UiModal>` migration + `disabled:opacity-30` → `<UiLoadingSpinner`.
- Final accessibility pass across all PAGOS surfaces.

## Test plan (commands)

```bash
php artisan test --filter=PaymentModal401RedirectTest
php artisan test --filter=CashRegisterAppShellTest
php artisan test --filter=PaymentMethodsAppShellTest
php artisan test --filter=ComposablesStandardizationTest
php artisan test --filter=PaymentReceivedChannelTest
pnpm build
pnpm lint:check
git grep -nE "bg-black bg-opacity-60|focus:ring-primary-500|focus:border-accent" \
  resources/js/modules/cash-register/components/PaymentModal.vue \
  resources/js/modules/cash-register/components/MercadoPagoCheckout.vue \
  resources/js/modules/settings/payment-methods/PaymentMethodFormModal.vue
git diff resources/js/modules/cash-register/components/PaymentModal.vue | grep -A 1 "script setup"
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-04-payment-modal-manual-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-04-payment-modal-mercadopago-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-04-payment-modal-mercadopago-disabled-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-04-mercadopago-checkout-1440x900.png
playwright-cli screenshot http://localhost:5173/settings/payment-methods 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-04-payment-methods-form-1440x900.png
```

## Key Learnings (forwarded to apply)

1. `PaymentModal.vue` `<script>` block MUST stay byte-for-byte unchanged — `PaymentModal401RedirectTest` (UXF-021) is the regression guard. Only template + class strings are touched.
2. `MercadoPagoCheckout.vue` Bricks-loading pill gets Apple motion (`var(--motion-duration-normal) var(--motion-easing-ios)`); keep it minimal — single `<UiLoadingSpinner>` + label, no over-animation.
3. `PaymentMethodFormModal.vue` wraps `gateway_config` in a `<UiInput>` with `data-redacted="true"` attribute; raw decrypted blob MUST NOT appear in any rendered text node. `PaymentMethodsAppShellTest::test_gateway_config_redacted` asserts both halves.

## References

- `categories/pagos/design.md` §2 (PaymentModal + MercadoPagoCheckout + PaymentMethodFormModal surface map), §3.1 (PaymentModal tab order), §3.6 (gateway_config redaction), §6.2 (PR-pagos-04 test additions), §8 risk #1 (Apple motion latency)
- `categories/pagos/spec.md` `PAGOS-MOD-001`, `PAGOS-RED-001`, `PAGOS-RT-001`, `PAGOS-CON-001`
- `tests/Unit/Composables/PaymentModal401RedirectTest.php` (UXF-021 — must stay green)
- `tests/Unit/Events/PaymentReceivedChannelTest.php` (Echo channel auth — must stay green)
- `resources/js/composables/useCashRegister.js:9-12` (Echo channel set — no new channels)
- `CREDENTIALS.md` (`finanzas@test.com` for PaymentModal + MercadoPagoCheckout; admin role for PaymentMethodFormModal)
