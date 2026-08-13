# PR-citas-04 — `AppointmentTypesPage` + `AppointmentTypeDetailPage` (admin CRUD triplet)

> **Change**: `ui-rollout-all-modules-2026-08` — CITAS category
> **Date**: 2026-08-12
> **PR scope**: PR-citas-04 only
> **Branch base**: `main` (stacked after PR-citas-03)
> **Review budget**: 400 authored lines / PR (target ~360; split into 04a + 04b if reviewer flags)
> **Strict TDD**: true

## Goal

Migrate `AppointmentTypesPage.vue` (21.5 KB; admin CRUD list — name, duration, price, color, requires_confirmation, requires_materials, is_consultation_mode) and `AppointmentTypeDetailPage.vue` (detail / edit view of one appointment type) to consume the proven primitives: filter bar `<select>` → `<UiSelect>`; `border-theme` table → `border-hairline` + `font-feature-settings: var(--font-features-tabular-nums)` on the `price` column; status pills (active/inactive) → `<UiStatusBadge variant="success|neutral">`; raw `<input>` borders → `<UiInput>` + hairline; raw `<select>` borders → `<UiSelect>` + hairline. The `price` field uses `formatCurrency` from the canonical `resources/js/composables/useFormatters.js` location (depends on PAGOS PR-pagos-05 landing first; backwards-compatible `formatPENLabel` alias preserved per `PAGOS-SCP-001`). `<script>` blocks of both pages NEVER touched; `useApi()` ownership of the 401 redirect path preserved.

## Depends on

- PR0 (landed): primitives + `canvasRoutes`.
- PR-citas-01..03 (landed): `<UiInput>` / `<UiSelect>` / `<UiStatusBadge>` / `<UiTabs>` rhythm established; `CitasWizardAppShellTest` + `CitasCalendarAppShellTest` + `NewAppointmentModalAppShellTest` green.
- **PAGOS PR-pagos-05** (landed): `formatCurrency` consolidated to canonical `useFormatters.js` location. **Gating dependency** — if PAGOS slips, this PR is held back (fallback: TEMPORARY local helper matching canonical signature, deleted by PAGOS PR-pagos-05).

## Work items (ordered; foundation first, visual last)

- [ ] **T-04.1** — RED: NEW `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `AppointmentTypesPage.vue` + `AppointmentTypeDetailPage.vue`. Add `assertAppointmentTypesFilterBarUsesUiSelect()` (regex: `<UiSelect` present in filter bar context; zero raw `<select class="border-theme">`). Run PHPUnit: RED.
- [ ] **T-04.2** — Migrate `AppointmentTypesPage.vue` filter bar: REPLACE inline raw `<select class="border-theme">` (filter by `is_active`, `is_consultation_mode`, `requires_materials`) with `<UiSelect>` (3 controls). Filter chip press uses `var(--motion-duration-fast)` (per design §2 surface map).
- [ ] **T-04.3** — Migrate `AppointmentTypesPage.vue` table: REPLACE `border-theme` table borders with `border-hairline` (= `rgba(60, 60, 67, 0.12)`); REPLACE `bg-success-100 text-success-700` / `bg-error-100 text-error-700` status pills with `<UiStatusBadge variant="success|neutral">` (active/inactive); `tabular-nums` on `price` column via `font-feature-settings: var(--font-features-tabular-nums)`; no-results → `<UiEmptyState>`.
- [ ] **T-04.4** — Migrate `AppointmentTypesPage.vue` price column: REPLACE local money formatter (if any) with `import { formatCurrency } from '@/composables/useFormatters'`; template usage `formatCurrency(appointmentType.price, { currency: 'PEN', locale: 'es-PE' })`. Zero `S/ ${n.toFixed(2)}` literals; zero local `Intl.NumberFormat` calls. If PAGOS PR-pagos-05 has not landed yet, import from a TEMPORARY local helper at `resources/js/composables/useFormatters.js` that exports the same signature (canonical location is preserved by PAGOS PR-pagos-05).
- [ ] **T-04.5** — Migrate `AppointmentTypeDetailPage.vue` form fields: raw `<input>` (name, duration_minutes, default_procedure_catalog_id, color picker wrapper) → `<UiInput variant="bordered">` + hairline + `var(--focus-ring-default)`; raw `<select>` (is_consultation_mode toggle, requires_confirmation toggle, requires_materials toggle, is_active toggle) → `<UiSelect>`; price field → `<UiInput type="number">` with `formatCurrency` rendering on display (read-only summary) and raw numeric on edit; status badges (active/inactive) → `<UiStatusBadge>`.
- [ ] **T-04.6** — Migrate `AppointmentTypeDetailPage.vue` chrome: header + form wrapper → `<UiCard variant="elevated">`; save / cancel buttons → `<UiButton variant="primary">` / `<UiButton variant="secondary">`; `disabled:opacity-30` on save → `<UiButton :loading="saving">` with inside-button `<UiLoadingSpinner v-if="saving" />`.
- [ ] **T-04.7** — NEW: create `tests/Unit/DesignSystem/AppointmentPriceFormatterTest.php`. Add `test_format_currency_imported_from_canonical_location()` (regex: `AppointmentTypesPage.vue` imports `formatCurrency` from `@/composables/useFormatters`; the import is the only money formatter referenced in the file). Add `test_no_local_intl_number_format()` (regex: zero `Intl.NumberFormat` literals inside `AppointmentTypesPage.vue` + `AppointmentTypeDetailPage.vue`). Run PHPUnit: GREEN.
- [ ] **T-04.8** — GREEN: `AppointmentTypesAppShellTest` passes `assertAppointmentTypesFilterBarUsesUiSelect()` + module-level alias assertions. Run PHPUnit: GREEN.
- [ ] **T-04.9** — Regression: `git grep -nE "border-theme|bg-success-100 text-success-700|bg-error-100 text-error-700|S/ \$\{n\.toFixed|Intl\.NumberFormat"` on `AppointmentTypesPage.vue` + `AppointmentTypeDetailPage.vue` returns zero matches; `<script>` blocks of both files byte-for-byte unchanged.
- [ ] **T-04.10** — Tests: `php artisan test --filter=AppointmentTypesAppShellTest` + `--filter=AppointmentPriceFormatterTest` + `--filter=NewAppointmentModalAppShellTest` + `--filter=CitasCalendarAppShellTest` + `--filter=CitasWizardAppShellTest` + `--filter=ComposablesStandardizationTest` + `--filter=FormatPENLabelTest` + `--filter=AppLayoutCanvasRoutesTest` + `--filter=LegacyAliasForbiddenTest` all GREEN.
- [ ] **T-04.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-04.12** — Visual: `playwright-cli` snapshots at 1440x900 — `citas-appointment-types-list-1440x900.png` (list with `formatCurrency` price column), `citas-appointment-types-detail-1440x900.png` (detail page), `citas-appointment-types-filter-open-1440x900.png` (filter bar open). Login: `admin@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-citas-04-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=AppointmentTypesAppShellTest` GREEN.
- [ ] `php artisan test --filter=AppointmentPriceFormatterTest` GREEN.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] Filter bar uses `<UiSelect>`; zero raw `<select class="border-theme">`.
- [ ] `price` column uses `formatCurrency` from `@/composables/useFormatters`; zero local `Intl.NumberFormat` or `S/ ${...}` literals.
- [ ] `tabular-nums` applied to price column.
- [ ] `<script>` blocks of `AppointmentTypesPage.vue` + `AppointmentTypeDetailPage.vue` are byte-for-byte unchanged.
- [ ] No regression in `CitasWizardAppShellTest`, `CitasCalendarAppShellTest`, `NewAppointmentModalAppShellTest`, `ConsultationWizardStatusEnumTest`, `ComposablesStandardizationTest`, `FormatPENLabelTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`.
- [ ] PR diff under 400 lines; if exceeded, split per design §4.3 (PR-citas-04a list + 04b detail).
- [ ] 3 screenshots saved under `.playwright-cli/screenshots-rollout/pr-citas-04-*.png`.

## Out of scope (deferred)

- Cross-cutting tests + a11y follow-up doc — PR-citas-05.
- `WorkSchedule` / `AppointmentBlock` admin frontend — out of scope (proposal §3 #9; validations commented out in `AppointmentService`).
- Quotation / billing screens (`/quotations`, `/cash-register/ready-to-bill`) — PAGOS category.
- Treatment-plan CRUD screens — clinical cluster PR6.
- Patient demographic forms — separate module.
- Dark mode; gradients; new tokens.

## Test plan (commands)

```bash
php artisan test --filter=AppointmentTypesAppShellTest
php artisan test --filter=AppointmentPriceFormatterTest
php artisan test --filter=FormatPENLabelTest
php artisan test --filter=LegacyAliasForbiddenTest
pnpm build
pnpm lint:check
git grep -nE "border-theme|bg-success-100 text-success-700|S/ \$\{n\.toFixed|Intl\.NumberFormat" \
  resources/js/modules/appointment-types/AppointmentTypesPage.vue \
  resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue
git diff --stat \
  resources/js/modules/appointment-types/AppointmentTypesPage.vue \
  resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue
playwright-cli screenshot http://localhost:5173/appointment-types 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-04-appointment-types-list-1440x900.png
playwright-cli screenshot http://localhost:5173/appointment-types/1 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-04-appointment-types-detail-1440x900.png
playwright-cli screenshot http://localhost:5173/appointment-types 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-04-appointment-types-filter-open-1440x900.png
```

## Key Learnings (forwarded to apply)

1. `formatCurrency` consolidation is gated on PAGOS PR-pagos-05; if it slips, this PR imports from a TEMPORARY local helper matching the canonical signature `(amount, options) => string` — PAGOS PR-pagos-05 deletes the temporary helper on landing.
2. `tabular-nums` on the price column uses `font-feature-settings: var(--font-features-tabular-nums)` (token-aligned, NOT a literal utility name) per design §5 `DLR-R-007`.
3. Admin CRUD triplet is the lower-traffic CITAS surface; landing it fourth means the `<UiInput>` / `<UiSelect>` / `<UiStatusBadge>` rhythm is already proven on wizard + calendar + modal.

## References

- `categories/citas/design.md` §3.4 (AppointmentTypesPage filter bar + price formatter decision), §6.2 (PR-citas-04 test extensions)
- `categories/citas/spec.md` `CITAS-AT-001`
- `openspec/changes/archive/2026-08-12-ui-pagos/tasks/02-pr-pagos-01-format-currency.md` (canonical `formatCurrency` consolidation)
- `resources/js/composables/useFormatters.js` (canonical location for `formatCurrency` + `formatPENLabel` alias)
- `tests/Unit/Composables/FormatPENLabelTest.php` (cross-category regression guard)
- `CREDENTIALS.md` (`admin@test.com` for appointment-types CRUD)
