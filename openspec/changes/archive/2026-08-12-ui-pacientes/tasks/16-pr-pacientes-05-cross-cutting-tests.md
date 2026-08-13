# PR-pacientes-05 — cross-cutting tests + a11y follow-up doc

> **Change**: `ui-rollout-all-modules-2026-08` — PACIENTES category
> **Date**: 2026-08-12
> **PR scope**: PR-pacientes-05 only
> **Branch base**: `main` (stacked after PR-pacientes-04)
> **Review budget**: 400 authored lines / PR (target ~200)
> **Strict TDD**: true

## Goal

Land the consolidated cross-cutting tests for the PACIENTES rollout + the a11y follow-up document. Consolidate per-PR rule assertions from PR-pacientes-01..04 into 2 durable per-module `*AppShellTest` files (extends `ModuleAppShellTestCase`) + finalise the `LegacyAliasForbiddenTest` `polishedFiles()` set + document the allergy / medical-history alert callout as a future a11y slice. Re-snapshot PR-pacientes-01..04 visual regression to confirm no drift from the consolidated tests. `PatientSelector.vue` + `<Pagination>` consolidation are OUT of scope here (they ride their own PRs).

## Depends on

- PR0 (landed): `canvasRoutes`, primitives, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`.
- PR-pacientes-01..04 (landed): list + list modals + detail header + 5-tab drawer + detail edit modal + export action surface tokenised; per-PR test files (`PatientTableNumsTest`, `PatientModalChromeTest`, `PatientStatusBadgeTest`) all GREEN.

## Work items (ordered; foundation first, visual last)

- [ ] **T-05.1** — RED: NEW `tests/Unit/DesignSystem/PatientsAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `resources/js/modules/patients/PatientsPage.vue`. Add `assertListUsesUiPrimitives()` (regex: `<UiStatusBadge` + `<UiCard clickable>` + `<UiButton variant="link|ghost">` + `<UiSelect` + `<UiInput` + `<UiEmptyState>` referenced; legacy aliases absent). Add `assertListTabularNumsOnDniAndAge()` (regex: `font-feature-settings: var(--font-features-tabular-nums)` present in DNI + age column context). Add `assertListNoStyleScoped()` (regex: `<style scoped>` absent). Run PHPUnit: RED on at least 1 method.
- [ ] **T-05.2** — GREEN: `PatientsAppShellTest` passes all 3 methods after PR-pacientes-01 + PR-pacientes-02 work landed. Confirm `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` stay GREEN at this PR boundary.
- [ ] **T-05.3** — RED: NEW `tests/Unit/DesignSystem/PatientDetailAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `resources/js/modules/patients/PatientDetailPage.vue`. Add `assertTabsUseUiTabs()` (regex: `<UiTabs v-model="currentStep">` referenced; raw `border-accent text-accent` active indicator absent; inline `@click="currentStep = step.id"` absent). Add `assertCrossCategoryDeepLinksPreserved()` (regex: 4 `router.push('/<target>?patient_id=…')` patterns present byte-for-byte for `treatment-plans`, `quotations`, `medical-records`, `specialty-records`). Add `assertExportDownloadPatternPreserved()` (regex: `window\.URL\.createObjectURL` + `<a download>` anchor click pattern present byte-for-byte). Add `assertAuditTabUsesUiCard()` (regex: `<UiCard>` referenced in audit tab content). Add `assertDetailNoStyleScoped()` (regex: `<style scoped>` absent). Run PHPUnit: RED on at least 1 method.
- [ ] **T-05.4** — GREEN: `PatientDetailAppShellTest` passes all 5 methods after PR-pacientes-03 + PR-pacientes-04 work landed. Confirm `ApiAndSeedersPolishTest` API-035 + API-057 stay GREEN (Content-Type whitelist for PDF/ZIP export).
- [ ] **T-05.5** — Finalise `LegacyAliasForbiddenTest` `polishedFiles()`: confirm both `PatientsPage.vue` + `PatientDetailPage.vue` are in the polished set (set during PR-pacientes-01 + PR-pacientes-03). Verify all aliases from PR-pacientes-01..04 are pinned: `border-theme`, `bg-success-badge`, `bg-danger-badge`, `hover-lift`, `divide-theme`, `text-green-600`, `text-red-600`, `bg-black bg-opacity-50`, `border-accent text-accent`, `border-l-2 border-theme`, `border border-theme`, `focus:ring-primary-500 focus:border-transparent`, `disabled:opacity-30`, `<Teleport to="body">`. Run PHPUnit: GREEN.
- [ ] **T-05.6** — NEW: create `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/a11y-followup.md`. Document the allergy / medical-history alert callout follow-up (no structured alert today; free-text inside a `<UiCard>` is the contract for this rollout; global spec forbids new primitives except `<UiStatusBadge>`). Mark as OPTIONAL per `PAC-A11Y-001`; no test fails if not introduced in this rollout.
- [ ] **T-05.7** — Realtime smoke test: open `/patients/:id` in 2 browser tabs (`recep@test.com`); update patient in tab A; verify tab B receives `patient.updated` within 1 second (per `PAC-RT-001`). Click each of the 4 per-tab create buttons (Planes / Presupuestos / Historia Clínica / Especialidades); verify the URL contains `?patient_id=<id>` and the destination module loads (per `PAC-DEEP-001`). Document the result in the PR description.
- [ ] **T-05.8** — Re-run all PR-pacientes-01..04 PHPUnit test suites: `PatientTableNumsTest` + `PatientModalChromeTest` + `PatientStatusBadgeTest` + `PatientsAppShellTest` + `PatientDetailAppShellTest` + `LegacyAliasForbiddenTest` + `AppLayoutCanvasRoutesTest` + `ComposablesStandardizationTest` + `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` + `ApiAndSeedersPolishTest` all GREEN.
- [ ] **T-05.9** — Final regression sweep: `git grep -nE "border-theme|hover-lift|divide-theme|bg-success-badge|bg-danger-badge|bg-black bg-opacity-50|border-accent text-accent|border-l-2 border-theme|border border-theme rounded-lg p-4|text-green-600|text-red-600|text-accent hover:text-primary-700|focus:ring-primary-500 focus:border-transparent|disabled:opacity-30|<Teleport to=\"body\">|<style scoped>"` across `resources/js/modules/patients/**` returns zero matches (the full PACIENTES surface).
- [ ] **T-05.10** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-05.11** — Visual sweep: `playwright-cli` re-run of PR-pacientes-01..04 screenshots to confirm no visual drift from the consolidated tests. Files: `pr-pacientes-05-list-1440x900.png` + `pr-pacientes-05-list-390x844.png` + `pr-pacientes-05-modal-1440x900.png` + `pr-pacientes-05-detail-1440x900.png` + `pr-pacientes-05-export-1440x900.png` (5 regression snapshots total). Save under `.playwright-cli/screenshots-rollout/pr-pacientes-05-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=PatientsAppShellTest` GREEN (3 module rules).
- [ ] `php artisan test --filter=PatientDetailAppShellTest` GREEN (5 module rules including 4 cross-category deep-links + export download pattern).
- [ ] All PR-pacientes-01..04 test suites GREEN (no regression from consolidation): `PatientTableNumsTest`, `PatientModalChromeTest`, `PatientStatusBadgeTest`, `LegacyAliasForbiddenTest`, `AppLayoutCanvasRoutesTest`, `ComposablesStandardizationTest`, `PatientResourceAgeTest`, `PatientControllerAgeTest`, `PatientControllerResourceWireUpTest`, `ApiAndSeedersPolishTest`.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] `git grep` on the full PACIENTES surface returns zero legacy alias matches.
- [ ] `a11y-followup.md` exists at `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/a11y-followup.md` with the allergy / medical-history alert callout follow-up documented.
- [ ] Realtime smoke test documented in PR description (2 tabs on `/patients/:id`, `patient.updated` within 1 second; 4 deep-links preserve `?patient_id=<id>`).
- [ ] `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` byte-for-byte unchanged across PR-pacientes-01..05 (verified at every PR boundary via `git diff --stat`).
- [ ] `PatientResource` API envelope preserved; `useEcho` `patients` + 4 cross-category channels + `useAuditLogs.getPatientAuditLogs(...)` preserved verbatim.
- [ ] `PatientSelector.vue` NOT touched (cross-module primitive); `<Pagination>` import kept verbatim in `PatientsPage.vue`.
- [ ] PR diff under 400 lines.
- [ ] 5 regression screenshots saved under `.playwright-cli/screenshots-rollout/pr-pacientes-05-*.png`.

## Out of scope (deferred to follow-up change)

- Allergy / medical-history alert component (OPTIONAL; tracked in `a11y-followup.md` per `PAC-A11Y-001`).
- `PatientSelector.vue` cross-module primitive tokenisation — its own PR per global OQ#7.
- `<Pagination>` → `<UiPagination>` consolidation — global PR3 (Recepción procedimientos).
- `Patient::$fillable` dormant entries cleanup, `ClinicalAttachment.file_path` encryption at rest, `document_number` → `DOC-XXX` rendering — separate changes.
- Per-branch PHI scoping on `PatientPolicy::view` — separate change.
- `Patient::restore()` / `forceDelete()` REST routes, soft-delete + appointments-conflict semantics — out of scope.
- `document_number` migration backfill idempotency, `Patient::scopeActive` query scope — backend, not a UI surface.
- PDF template (`resources/views/exports/patient-file.blade.php`) restyling — print artifact, separate slice.
- Cross-category module content (treatment plans / quotations / medical records / specialty records / AI analysis) — separate modules; only the deep-link navigation contract lives here.
- `Settings/branches` + `Settings/payment-methods` — per global OQ#3, OUT of scope.

## Test plan (commands)

```bash
php artisan test --filter=PatientsAppShellTest
php artisan test --filter=PatientDetailAppShellTest
php artisan test --filter=PatientModalChromeTest
php artisan test --filter=PatientStatusBadgeTest
php artisan test --filter=PatientTableNumsTest
php artisan test --filter=LegacyAliasForbiddenTest
php artisan test --filter=AppLayoutCanvasRoutesTest
php artisan test --filter=ComposablesStandardizationTest
php artisan test --filter=PatientResourceAgeTest
php artisan test --filter=PatientControllerAgeTest
php artisan test --filter=PatientControllerResourceWireUpTest
php artisan test --filter=ApiAndSeedersPolishTest
pnpm build
pnpm lint:check
git grep -nE "border-theme|hover-lift|divide-theme|bg-success-badge|bg-danger-badge|bg-black bg-opacity-50|border-accent text-accent|border-l-2 border-theme|text-green-600|text-red-600" \
  resources/js/modules/patients
git diff --stat resources/js/modules/patients
playwright-cli screenshot http://localhost:5173/patients 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-05-list-1440x900.png
playwright-cli screenshot http://localhost:5173/patients/1 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-05-detail-1440x900.png
```

## Key Learnings (forwarded to apply)

1. Per-module `*AppShellTest` files (`PatientsAppShellTest` + `PatientDetailAppShellTest`) are the durable regression witnesses for the PACIENTES rollout — they consolidate per-PR ephemeral assertions into 8 module rules (3 list + 5 detail) that any future refactor must preserve.
2. `a11y-followup.md` records the allergy / medical-history alert callout as a future a11y slice (global spec forbids new primitives except `<UiStatusBadge>`; the text-only display inside a `<UiCard>` is the contract for this rollout).
3. Realtime smoke test (2 tabs on `/patients/:id`) + deep-link verification (4 `?patient_id=…` contracts) are the manual behavioral witnesses for `PAC-RT-001` + `PAC-DEEP-001` — PHPUnit asserts the source-code rule; the manual smoke confirms the runtime contract still fires.

## References

- `categories/pacientes/design.md` §3.10 (deep-link preservation), §3.9 (`<style scoped>` removal), §6.1 (existing tests that must stay green), §6.2 (PR-pacientes-05 test extensions), §6.3 (RED-GREEN discipline)
- `categories/pacientes/spec.md` `PAC-LIST-001`, `PAC-MOD-001`, `PAC-DET-001`, `PAC-EDIT-001`, `PAC-EXP-001`, `PAC-RT-001`, `PAC-PHI-001`, `PAC-DEEP-001`, `PAC-REV-001`, `PAC-CON-001`, `PAC-A11Y-001` (future)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base class)
- `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` (extended `LEGACY_ALIASES` + `polishedFiles()` from PR-pacientes-01 + PR-pacientes-03)
- `tests/Feature/Api/PatientControllerAgeTest.php` + `tests/Unit/Resources/PatientResourceAgeTest.php` + `tests/Unit/Controllers/PatientControllerResourceWireUpTest.php` (API envelope regression guards)
- `tests/Unit/Polish/ApiAndSeedersPolishTest.php` (API-035 + API-057 export Content-Type)
- `resources/js/composables/useEcho.js` (`patients` + 4 cross-category channels — preserved)
- `resources/js/composables/useAuditLogs.js` (`getPatientAuditLogs(patientId)` — preserved)
- `CREDENTIALS.md` (`recep@test.com` for realtime smoke)
