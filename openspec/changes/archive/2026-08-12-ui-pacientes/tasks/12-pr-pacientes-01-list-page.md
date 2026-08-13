# PR-pacientes-01 — `PatientsPage` list polish (highest-traffic demographic surface)

> **Change**: `ui-rollout-all-modules-2026-08` — PACIENTES category
> **Date**: 2026-08-12
> **PR scope**: PR-pacientes-01 only
> **Branch base**: `main` (stacked after PR0 `feat/ui-rollout-pr0-foundation`)
> **Review budget**: 400 authored lines / PR (target ~390; split into 01a desktop table + 01b mobile cards if reviewer flags)
> **Strict TDD**: true

## Goal

Migrate `PatientsPage.vue` (1249 lines, 44.5 KB) LIST SECTION ONLY (not the 2 inlined modals) to consume proven primitives: search bar + status filter (`<UiSelect>`) + 4 stat cards (`<UiCard clickable>`, NOT `hover-lift`) + desktop table (hairline + `<UiStatusBadge>` + `<UiButton variant="link">`) + mobile card fallback (`<UiButton variant="ghost">`) + `<style scoped>` block removal (line 1315). DNI + age columns carry `font-feature-settings: var(--font-features-tabular-nums)`. Legacy `<Pagination>` import (lines 742, 752) stays verbatim — the consolidation rides global PR3. Cross-module `PatientSelector.vue` primitive is OUT of scope here. `<script>` block is NEVER touched (`useEcho` `patients` channel `.patient.updated` + cross-category channel subscriptions + `useApi` / `usePermissions` / `useToast` / `useConfirm` / `useAuditLogs` contracts preserved byte-for-byte). `PatientResource` API envelope (incl. additive `age` integer) preserved.

## Depends on

- PR0 (landed): `canvasRoutes`, `<UiStatusBadge>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`.

## Work items (ordered; foundation first, visual last)

- [ ] **T-01.1** — RED: NEW `tests/Unit/DesignSystem/PatientTableNumsTest.php` extending `TestCase`. Add `test_dni_column_uses_tabular_nums_on_patients_page` (regex: `font-feature-settings:\s*var\(--font-features-tabular-nums\)` present in DNI column context) + `test_age_column_uses_tabular_nums_on_patients_page` + `test_document_number_and_age_cells_use_tabular_nums_on_patient_detail_page`. Run PHPUnit: RED.
- [ ] **T-01.2** — RED: extend `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` `LEGACY_ALIASES` constant with `bg-success-badge`, `bg-danger-badge`, `hover-lift`, `divide-theme`, `text-green-600`, `text-red-600`. Override `polishedFiles()` to append `resources/js/modules/patients/PatientsPage.vue`. Run PHPUnit: RED.
- [ ] **T-01.3** — Migrate 4 stat cards: REPLACE `class="hover-lift"` on Total / Activos / Inactivos / Filtrados cards with `<UiCard clickable>` (the primitive owns focus + hover + press via `var(--focus-ring-default)` + `var(--motion-duration-normal) var(--motion-easing-ios)`). Value uses `font-feature-settings: var(--font-features-tabular-nums)`. Click handler stays verbatim in `<script>` (untouched).
- [ ] **T-01.4** — Migrate status filter: REPLACE raw `<select class="border-theme focus:ring-primary-500 focus:border-transparent">` with `<UiSelect v-model="statusFilter" :options="statusOptions" />`. Debounce watcher on search query stays verbatim.
- [ ] **T-01.5** — Migrate desktop table: REPLACE `border-theme` table dividers with `border-hairline`; `divide-theme` row dividers with `divide-[color:var(--color-hairline)]`; row status pill (`bg-success-badge` / `bg-danger-badge`) → `<UiStatusBadge variant="success | error">`; "Ver" detail link (`text-accent hover:text-primary-700`) → `<UiButton variant="link">`. DNI + age columns get `font-feature-settings: var(--font-features-tabular-nums)`.
- [ ] **T-01.6** — Migrate mobile card fallback: REPLACE raw `text-green-600` / `text-red-600` action buttons with `<UiButton variant="ghost">` using semantic token color (`text-systemGreen-700` / `text-systemRed-700`). No-results branch → `<UiEmptyState>`.
- [ ] **T-01.7** — Remove `<style scoped>` block at line 1315 (single `@media (max-width: 640px)` rule). Rewrite to plain utility classes (`sm:hidden`). `ModuleAppShellTestCase::test_no_style_scoped` GREEN for this file.
- [ ] **T-01.8** — GREEN: `PatientTableNumsTest` passes all 3 methods; `LegacyAliasForbiddenTest::test_no_legacy_alias_in_polished_file` GREEN for `PatientsPage.vue`. `git grep -nE "border-theme|hover-lift|divide-theme|bg-success-badge|bg-danger-badge|text-green-600|text-red-600"` on `PatientsPage.vue` returns zero matches.
- [ ] **T-01.9** — Verify isolation: `git diff --stat resources/js/modules/patients/PatientsPage.vue` shows zero edits to `<script setup>` block; `useEcho` `patients` + cross-category channel subscriptions (the detail-page channels are present on `PatientDetailPage.vue`, not here) preserved verbatim. `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` stay GREEN.
- [ ] **T-01.10** — Tests: `php artisan test --filter=PatientTableNumsTest` + `--filter=LegacyAliasForbiddenTest` + `--filter=AppLayoutCanvasRoutesTest` + `--filter=PatientResourceAgeTest` + `--filter=PatientControllerAgeTest` + `--filter=PatientControllerResourceWireUpTest` all GREEN.
- [ ] **T-01.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-01.12** — Visual: `playwright-cli` snapshots at 1440x900 — `patients-list-1440x900.png` (full list with stats + filters + table + pagination); at 390x844 — `patients-list-390x844.png` (mobile card fallback); `patients-list-filters-open-1440x900.png` (status filter dropdown open). Login: `recep@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-pacientes-01-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=PatientTableNumsTest` GREEN (3 methods).
- [ ] `php artisan test --filter=LegacyAliasForbiddenTest` GREEN with the extended alias set.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] No `border-theme`, `hover-lift`, `divide-theme`, `bg-success-badge`, `bg-danger-badge`, `text-green-600`, `text-red-600`, `text-accent hover:text-primary-700`, `bg-theme-surface` on the page surface inside `PatientsPage.vue`.
- [ ] DNI + age columns carry `font-feature-settings: var(--font-features-tabular-nums)`.
- [ ] Zero `<style scoped>` blocks in `PatientsPage.vue`; `ModuleAppShellTestCase::test_no_style_scoped` GREEN.
- [ ] `<script>` block of `PatientsPage.vue` byte-for-byte unchanged; `useEcho` `patients` channel subscription preserved verbatim.
- [ ] Legacy `<Pagination>` import kept verbatim; `PatientResource` API envelope (additive `age` key) preserved; `PatientSelector.vue` NOT touched.
- [ ] No regression in `AppLayoutCanvasRoutesTest`, `PatientResourceAgeTest`, `PatientControllerAgeTest`, `PatientControllerResourceWireUpTest`, `ApiAndSeedersPolishTest`, `ComposablesStandardizationTest`.
- [ ] PR diff under 400 lines; if exceeded, split per design §4.4 (PR-pacientes-01a desktop + stat cards + filters / PR-pacientes-01b mobile cards + pagination).
- [ ] 3 screenshots saved under `.playwright-cli/screenshots-rollout/pr-pacientes-01-*.png`.

## Out of scope (deferred to PR-pacientes-02..05)

- `PatientsPage.vue` 2 inlined modals (New Patient lines 463–581 + Edit Patient lines 583–725) — PR-pacientes-02.
- `PatientDetailPage.vue` header + 5-tab drawer + cross-category deep-links + audit tab — PR-pacientes-03.
- `PatientDetailPage.vue` Edit modal + Export action surface — PR-pacientes-04.
- Cross-cutting `PatientsAppShellTest` + `PatientDetailAppShellTest` + a11y doc — PR-pacientes-05.
- `PatientSelector.vue` cross-module primitive — its own PR per global OQ#7.
- `<Pagination>` → `<UiPagination>` consolidation — global PR3 (Recepción procedimientos).
- `Patient::$fillable` dormant entries cleanup, `ClinicalAttachment.file_path` encryption at rest, `document_number` → `DOC-XXX` rendering — separate changes.

## Test plan (commands)

```bash
php artisan test --filter=PatientTableNumsTest
php artisan test --filter=LegacyAliasForbiddenTest
php artisan test --filter=AppLayoutCanvasRoutesTest
php artisan test --filter=PatientResourceAgeTest
php artisan test --filter=PatientControllerAgeTest
php artisan test --filter=PatientControllerResourceWireUpTest
pnpm build
pnpm lint:check
git grep -nE "border-theme|hover-lift|divide-theme|bg-success-badge|bg-danger-badge|text-green-600|text-red-600" \
  resources/js/modules/patients/PatientsPage.vue
git diff --stat resources/js/modules/patients/PatientsPage.vue
playwright-cli screenshot http://localhost:5173/patients 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-01-list-1440x900.png
playwright-cli screenshot http://localhost:5173/patients 390x844 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-01-list-390x844.png
playwright-cli screenshot http://localhost:5173/patients 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-01-list-filters-open-1440x900.png
```

## Key Learnings (forwarded to apply)

1. Stat cards use `<UiCard clickable>` (NOT `hover-lift` raw utility) — the primitive owns focus + hover + press; `var(--motion-duration-normal) var(--motion-easing-ios)` scale + brightness shift is inherited from `<UiCard>`, not written inline.
2. DNI + age `tabular-nums` uses `font-feature-settings: var(--font-features-tabular-nums)` (token-aligned, NOT literal `tabular-nums` utility name) per `DLR-R-007`.
3. Legacy `<Pagination>` import (lines 742, 752 of `PatientsPage.vue`) stays verbatim — the consolidation to `<UiPagination>` rides global PR3 (Recepción procedimientos); silent rename would break the dependency graph.
4. `<script>` block of `PatientsPage.vue` is NEVER edited in any PR; `useEcho` `patients` channel subscription + `useApi` / `usePermissions` / `useToast` / `useConfirm` / `useAuditLogs` contracts preserved verbatim.

## References

- `categories/pacientes/design.md` §3.1 (stat cards decision), §3.2 (filters decision), §3.3 (table + mobile cards decision), §3.9 (`<style scoped>` removal), §6.2 (PR-pacientes-01 test extensions)
- `categories/pacientes/spec.md` `PAC-LIST-001`
- `resources/js/modules/patients/PatientsPage.vue` (1249 lines, 44.5 KB — primary file)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base class to extend via per-module subclass in PR-pacientes-05)
- `resources/js/composables/useEcho.js` (`patients` channel subscription — preserved)
- `CREDENTIALS.md` (`recep@test.com` for list)
