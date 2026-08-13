# PR-pacientes-03 — `PatientDetailPage` header + 5-tab drawer + audit tab + cross-category deep-links

> **Change**: `ui-rollout-all-modules-2026-08` — PACIENTES category
> **Date**: 2026-08-12
> **PR scope**: PR-pacientes-03 only
> **Branch base**: `main` (stacked after PR-pacientes-02)
> **Review budget**: 400 authored lines / PR (target ~390; split into 03a header + 5-tab drawer / 03b audit tab if reviewer flags)
> **Strict TDD**: true

## Goal

Migrate `PatientDetailPage.vue` (1480 lines, 53.4 KB) header + 5-tab drawer + audit tab to consume proven primitives: header status pill → `<UiStatusBadge variant="success | error">`; raw `<button>` step strip with `border-accent text-accent` active indicator (line 87) → `<UiTabs v-model="currentStep">` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions (6 entries: Datos / Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría); audit tab content (`border border-theme rounded-lg p-4` raw list items) → `<UiCard>` wrappers + `<UiStatusBadge variant="info">` for action-type indicator; `border-l-2 border-theme` change-diff callout (line 669) → hairline; `<style scoped>` block at line 1556 (`.tab-content { min-height: 400px }`) removed + rewritten as `min-h-[400px]` utility. **Cross-category deep-links preserved byte-for-byte**: `router.push('/treatment-plans?patient_id=…')`, `router.push('/quotations?patient_id=…')`, `router.push('/medical-records?patient_id=…')`, `router.push('/specialty-records?patient_id=…')`. `<script>` block is NEVER touched (`useEcho` `patients` channel + cross-category channels `treatment-plans` / `quotations` / `medical-records` / `specialty-records` + `useAuditLogs.getPatientAuditLogs(patientId)` preserved). `PatientResource` API envelope (additive `age` integer) preserved.

## Depends on

- PR0 (landed): primitives + `canvasRoutes`.
- PR-pacientes-01 (landed): list primitives established.
- PR-pacientes-02 (landed): `<UiModal>` + `<UiInput>` + `<UiSelect>` + `<UiStatusBadge>` rhythm proven on the list page; `PatientModalChromeTest` green.

## Work items (ordered; foundation first, visual last)

- [ ] **T-03.1** — RED: NEW `tests/Unit/DesignSystem/PatientStatusBadgeTest.php` extending `TestCase`. Add `test_detail_header_status_pill_uses_ui_status_badge` (regex: `<UiStatusBadge variant="success|error">` OR `<UiBadge variant="success|error">` referenced in header context; legacy `bg-success-badge` / `bg-danger-badge` absent). Add `test_audit_tab_action_indicator_uses_ui_status_badge_info` (regex: `<UiStatusBadge variant="info">` in audit tab content; legacy alias absent). Run PHPUnit: RED.
- [ ] **T-03.2** — RED: extend `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` `LEGACY_ALIASES` constant with `border-l-2 border-theme` (the change-diff callout legacy alias) + `border border-theme` (audit tab raw list item legacy). Run PHPUnit: RED.
- [ ] **T-03.3** — Migrate header status pill: REPLACE legacy active/inactive pill on `PatientDetailPage.vue` (raw div with `bg-success-badge` / `bg-danger-badge` or raw text-color utility) with `<UiStatusBadge variant="success | error" :label="patient.is_active ? 'Activo' : 'Inactivo'" />`. Header wrapper stays `<PageHeader>` (already tokenised).
- [ ] **T-03.4** — Migrate 5-tab drawer: REPLACE raw `<button v-for="step in steps" :class="... border-accent text-accent ..." @click="currentStep = step.id">` with `<UiTabs v-model="currentStep" :items="steps">` (6 entries: Datos, Planes, Presupuestos, Historia Clínica, Especialidades, Historial de auditoría). Tab transition uses `var(--motion-duration-fast) var(--motion-easing-ios)` (opacity + translateY ≤8px per archive-report lesson; over-animation feels sluggish). Inline `@click="currentStep = step.id"` handler is REMOVED (replaced by v-model binding).
- [ ] **T-03.5** — Migrate audit tab content (Historial de auditoría): REPLACE `border border-theme rounded-lg p-4` raw list items with `<UiCard variant="glass">` wrappers; action-type indicator (created / updated / deleted / file_exported) → `<UiStatusBadge variant="info">`. The list content (timestamp + user + action + diff metadata) stays byte-for-byte.
- [ ] **T-03.6** — Migrate change-diff callout: REPLACE `border-l-2 border-theme` callout (line 669) with hairline left border (`border-l-2 border-hairline`). Same visual weight, no legacy alias.
- [ ] **T-03.7** — Remove `<style scoped>` block at line 1556 (single `.tab-content { min-height: 400px }` rule). Rewrite to plain utility class (`min-h-[400px]`) applied directly to the tab-content wrapper. `ModuleAppShellTestCase::test_no_style_scoped` GREEN for this file.
- [ ] **T-03.8** — Verify deep-links preserved: regex `git grep -nE "router\.push\(['\"]/treatment-plans\?patient_id|router\.push\(['\"]/quotations\?patient_id|router\.push\(['\"]/medical-records\?patient_id|router\.push\(['\"]/specialty-records\?patient_id"` on `PatientDetailPage.vue` returns 4 matches (one per deep-link). `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` stay GREEN.
- [ ] **T-03.9** — Verify isolation: `git diff --stat resources/js/modules/patients/PatientDetailPage.vue` shows zero edits to `<script setup>` block. `useEcho` `patients` channel `.patient.updated` + 4 cross-category channel subscriptions (`treatment-plans`, `quotations`, `medical-records`, `specialty-records` for `.created/.updated/.deleted`) + `useAuditLogs.getPatientAuditLogs(patientId)` preserved byte-for-byte.
- [ ] **T-03.10** — GREEN: `PatientStatusBadgeTest` passes both methods; `LegacyAliasForbiddenTest::test_no_legacy_alias_in_polished_file` GREEN with extended alias set + extended `polishedFiles()` covering `PatientDetailPage.vue`.
- [ ] **T-03.11** — Regression: `git grep -nE "border-accent text-accent|border-l-2 border-theme|border border-theme rounded-lg p-4|<style scoped>"` on `PatientDetailPage.vue` returns zero matches; `<script>` block byte-for-byte unchanged.
- [ ] **T-03.12** — Tests + build + visual: `php artisan test --filter=PatientStatusBadgeTest` + `--filter=LegacyAliasForbiddenTest` + `--filter=AppLayoutCanvasRoutesTest` + `--filter=PatientModalChromeTest` + `--filter=PatientResourceAgeTest` all GREEN. `pnpm build` clean; `pnpm lint:check` clean. Visual: `playwright-cli` snapshots at 1440x900 — `patient-detail-1440x900.png` (header + 5-tab drawer + Datos tab); `patient-detail-tabs-active-1440x900.png` (each of the 6 tabs active); `patient-detail-deep-link-1440x900.png` (clicking "Crear plan" on Planes tab navigates to `/treatment-plans?patient_id=…`). Login: `recep@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-pacientes-03-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=PatientStatusBadgeTest` GREEN (2 methods).
- [ ] `php artisan test --filter=LegacyAliasForbiddenTest` GREEN with extended alias set + extended `polishedFiles()`.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] Header status pill uses `<UiStatusBadge variant="success | error">`; no legacy `bg-success-badge` / `bg-danger-badge` in `PatientDetailPage.vue`.
- [ ] 5-tab drawer uses `<UiTabs v-model="currentStep">`; no raw `border-accent text-accent` active indicator; no inline `@click="currentStep = step.id"` handler.
- [ ] Audit tab content uses `<UiCard>` + `<UiStatusBadge variant="info">`; no `border border-theme rounded-lg p-4` raw list items.
- [ ] Change-diff callout uses hairline left border.
- [ ] Zero `<style scoped>` blocks in `PatientDetailPage.vue`.
- [ ] 4 cross-category `router.push(...)` deep-links preserved byte-for-byte.
- [ ] `<script>` block byte-for-byte unchanged; `useEcho` `patients` + 4 cross-category channels preserved verbatim.
- [ ] `PatientResource` API envelope preserved; `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` stay GREEN.
- [ ] No regression in `PatientTableNumsTest`, `PatientModalChromeTest`, `AppLayoutCanvasRoutesTest`, `ComposablesStandardizationTest`.
- [ ] PR diff under 400 lines; if exceeded, split per design §4.4 (PR-pacientes-03a header + 5-tab drawer / PR-pacientes-03b audit tab + per-tab polish).
- [ ] 3 screenshots saved under `.playwright-cli/screenshots-rollout/pr-pacientes-03-*.png`.

## Out of scope (deferred to PR-pacientes-04..05)

- `PatientDetailPage.vue` Edit Patient modal (lines 706–845) — PR-pacientes-04.
- `PatientDetailPage.vue` Export action surface + binary download pattern — PR-pacientes-04.
- Cross-cutting `PatientsAppShellTest` + `PatientDetailAppShellTest` + a11y doc — PR-pacientes-05.
- Allergy / medical-history alert callout — global spec forbids new primitives; flagged in `a11y-followup.md`.
- `PatientSelector.vue` + `<Pagination>` consolidation — their own PRs.
- Treatment plan / quotation / medical record / specialty record content — separate modules; only the deep-link navigation contract lives here.

## Test plan (commands)

```bash
php artisan test --filter=PatientStatusBadgeTest
php artisan test --filter=LegacyAliasForbiddenTest
php artisan test --filter=PatientModalChromeTest
php artisan test --filter=PatientTableNumsTest
php artisan test --filter=PatientResourceAgeTest
pnpm build
pnpm lint:check
git grep -nE "border-accent text-accent|border-l-2 border-theme" \
  resources/js/modules/patients/PatientDetailPage.vue
git grep -nE "router\.push\('/(treatment-plans|quotations|medical-records|specialty-records)\?patient_id" \
  resources/js/modules/patients/PatientDetailPage.vue
git diff --stat resources/js/modules/patients/PatientDetailPage.vue
playwright-cli screenshot http://localhost:5173/patients/1 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-03-detail-1440x900.png
playwright-cli screenshot "http://localhost:5173/patients/1?tab=audit" 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-03-detail-tabs-active-1440x900.png
```

## Key Learnings (forwarded to apply)

1. 5-tab drawer (`<UiTabs>`) is wired via `v-model="currentStep"` — the inline `@click="currentStep = step.id"` handler MUST be removed; the v-model binding is the sole contract. Tab transition motion stays minimal (opacity + translateY ≤8px) per archive-report lesson.
2. 4 cross-category `router.push('/<target>?patient_id=…')` deep-links are byte-for-byte preserved — these are the navigation contract between `PatientDetailPage` and the 4 sibling modules (treatment-plans, quotations, medical-records, specialty-records); a UI refactor that drops the `?patient_id=…` query param breaks the contract.
3. `<script>` block is NEVER edited — `useEcho` `patients` channel + 4 cross-category channels (`treatment-plans`, `quotations`, `medical-records`, `specialty-records`) + `useAuditLogs.getPatientAuditLogs(patientId)` + `useApi` + `usePermissions.can.{createPatient, updatePatient, deletePatient, createTreatmentPlan, createQuotation, createMedicalRecord, createSpecialtyRecord}` all preserved verbatim.

## References

- `categories/pacientes/design.md` §3.5 (5-tab drawer decision), §3.8 (audit tab tokenisation), §3.9 (`<style scoped>` removal), §3.10 (deep-link preservation), §6.2 (PR-pacientes-03 test extensions)
- `categories/pacientes/spec.md` `PAC-DET-001`, `PAC-DEEP-001`, `PAC-RT-001`
- `resources/js/modules/patients/PatientDetailPage.vue` (1480 lines, 53.4 KB — primary file)
- `resources/js/composables/useEcho.js` (`patients` + 4 cross-category channel subscriptions — preserved)
- `resources/js/composables/useAuditLogs.js` (`getPatientAuditLogs(patientId)` — preserved)
- `CREDENTIALS.md` (`recep@test.com` for detail)
