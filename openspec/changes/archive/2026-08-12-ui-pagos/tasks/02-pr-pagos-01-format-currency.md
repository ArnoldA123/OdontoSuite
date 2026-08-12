# PR-pagos-01 — `formatCurrency` helper + cross-cutting tests

> **Change**: `ui-rollout-all-modules-2026-08` — PAGOS category
> **Date**: 2026-08-12
> **PR scope**: PR-pagos-01 only
> **Branch base**: `main` (stacked after PR0 `feat/ui-rollout-pr0-foundation`)
> **Review budget**: 400 authored lines / PR (target ~120)
> **Strict TDD**: true

## Goal

Consolidate `formatCurrency` to exactly one declaration location at `resources/js/composables/useFormatters.js` (alongside the existing `formatPENLabel`) and migrate all duplicate reimplementations in a single go. Establishes the rule-asserting test infrastructure that PR-pagos-02..05 lean on.

## Depends on

- PR0 (landed): `canvasRoutes` extension, `<UiStatusBadge>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`.

## Work items (ordered; foundation first, visual last)

- [ ] **T-01.1** — RED: extend `tests/Unit/Composables/FormatPENLabelTest.php` with `test_format_currency_exists_at_exactly_one_location()` and `test_format_currency_emits_soles_prefix_for_pen()`. Use `git grep` count of the literal `Intl.NumberFormat('es-PE', { currency: 'PEN' })` (excluding `useFormatters.js`). Assert count == 1. Run PHPUnit: RED.
- [ ] **T-01.2** — GREEN: edit `resources/js/composables/useFormatters.js` line 49 region. Rename `formatPENLabel` → `formatCurrency`. Add a backwards-compatible `export const formatPENLabel = formatCurrency` alias so existing call sites in `DashboardPage` / `SessionList` keep working without import-line edits. The signature MUST be `(amount, options) => string`; `options` is reserved for future use (ignored today per `PAGOS-SCP-001`). Run PHPUnit: GREEN.
- [ ] **T-01.3** — Migrate reimplementation site 1: `resources/js/modules/cash-register/ReadyToBillPage.vue` line 63 (manual `S/ ${...}` literal). Replace local helper with `import { formatCurrency } from '@/composables/useFormatters'`. Template usage stays identical.
- [ ] **T-01.4** — Migrate reimplementation site 2: `resources/js/modules/cash-register/CashRegisterPage.vue` line 610 (inline `Intl.NumberFormat`). Replace with `formatCurrency` import. Template usage stays identical.
- [ ] **T-01.5** — Migrate reimplementation site 3: `resources/js/modules/cash-register/components/CloseCashModal.vue` line 412 (inline `Intl.NumberFormat`). Replace with `formatCurrency` import. Template usage stays identical.
- [ ] **T-01.6** — Migrate reimplementation site 4: `resources/js/modules/cash-register/components/CashReports.vue` (inline formatter in summary cards). Replace with `formatCurrency` import. Grep-verified zero remaining `Intl.NumberFormat` literals outside `useFormatters.js`.
- [ ] **T-01.7** — Audit sweep: `git grep -nE "Intl\.NumberFormat|S/ \$\{|S/ \$\{|toFixed\(2\)" resources/js/modules/cash-register resources/js/modules/quotations resources/js/modules/settings/payment-methods` returns zero matches outside `useFormatters.js` + `CurrencyInput.vue`. Document the grep result in the apply-progress journal.
- [ ] **T-01.8** — RED: extend `tests/Unit/Composables/FormatPENLabelTest.php` with `test_format_currency_input_sites_import_from_canon()` — assert every caller in the 4 migrated files imports `formatCurrency` from `@/composables/useFormatters`. Run PHPUnit: RED then GREEN after T-01.3..T-01.6.
- [ ] **T-01.9** — RED: extend `tests/Unit/Composables/FormatPENLabelTest.php` with `test_currency_input_formatter_unchanged()` — assert `CurrencyInput.vue` still uses its local `Intl.NumberFormat` call (no formatting fork). Run PHPUnit: GREEN (pre-existing behavior).
- [ ] **T-01.10** — Tests: run `php artisan test --filter=FormatPENLabelTest`. All assertions green; no regression in `PaymentModal401RedirectTest`, `ComposablesStandardizationTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`.
- [ ] **T-01.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-01.12** — Visual: `playwright-cli` regression snapshot at `/cash-register` and `/cash-register/ready-to-bill` (1440x900) — confirm no display drift from the formatter swap (caches cleared). Save under `.playwright-cli/screenshots-rollout/pr-pagos-01-cash-register-1440x900.png` and `pr-pagos-01-ready-to-bill-1440x900.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=FormatPENLabelTest` green with the 4 new test methods + all pre-existing assertions.
- [ ] `pnpm build` clean.
- [ ] `pnpm lint:check` clean.
- [ ] `git grep -nE "Intl\.NumberFormat" resources/js/modules/` returns 0 matches outside `useFormatters.js` + `CurrencyInput.vue`.
- [ ] `git grep -nE "S/ \$\{" resources/js/modules/` returns 0 matches.
- [ ] No regression: `PaymentModal401RedirectTest`, `ComposablesStandardizationTest`, `RequireActiveCashSessionTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest` all stay green.
- [ ] PR diff under 400 lines (target ~120).
- [ ] Screenshot saved: `.playwright-cli/screenshots-rollout/pr-pagos-01-cash-register-1440x900.png` + `pr-pagos-01-ready-to-bill-1440x900.png`.
- [ ] `<script>` blocks of every PAGOS module are byte-for-byte unchanged (apply phase MUST run `git diff -- 'resources/js/modules/cash-register/**/*.vue' | grep -A 1 'script setup'` and confirm zero matches).

## Out of scope (deferred to PR-pagos-02..05)

- Template chrome replacement (lists, modals, pages) — PR-pagos-02..05.
- `<UiStatusBadge>` adoption on transaction lists — PR-pagos-02.
- `PaymentModal` 401 redirect verification — PR-pagos-04.
- Quotations + Payment Methods admin CRUD — PR-pagos-04 + PR-pagos-05.
- Any new tokens, primitives, or backend changes.

## Test plan (commands)

```bash
php artisan test --filter=FormatPENLabelTest
php artisan test --filter=PaymentModal401RedirectTest
php artisan test --filter=ComposablesStandardizationTest
pnpm build
pnpm lint:check
git grep -nE "Intl\.NumberFormat" resources/js/modules/
git grep -nE "S/ \$\{" resources/js/modules/
playwright-cli screenshot http://localhost:5173/cash-register 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-01-cash-register-1440x900.png
playwright-cli screenshot http://localhost:5173/cash-register/ready-to-bill 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pagos-01-ready-to-bill-1440x900.png
```

## Key Learnings (forwarded to apply)

1. `formatPENLabel` lives at `resources/js/composables/useFormatters.js:49` (canonical `Intl.NumberFormat('es-PE', { currency: 'PEN' })`).
2. Four duplicate `formatCurrency` reimplementations confirmed at `ReadyToBillPage.vue:63`, `CashRegisterPage.vue:610`, `CloseCashModal.vue:412`, `CashReports.vue` — all migrated in this PR.
3. Echo channel set confirmed at `useCashRegister.js:9-12` (consumed as-is, no new channels in this PR).

## References

- `categories/pagos/proposal.md` §2.7 (consolidation), §6 (PR-pagos-01 row)
- `categories/pagos/design.md` §3.5 (canonical `useFormatters.js` decision), §9.1 (file change list)
- `categories/pagos/spec.md` `PAGOS-MNY-002`, `PAGOS-XCUT-007` (single-location rule)
- `tests/Unit/Composables/FormatPENLabelTest.php` (test file to extend)
- `resources/js/composables/useFormatters.js` (target helper)
- `tests/Unit/Composables/PaymentModal401RedirectTest.php` (UXF-021 regression guard)
