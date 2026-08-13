# PR-pacientes-04 — `PatientDetailPage` Edit Patient modal + Export action surface

> **Change**: `ui-rollout-all-modules-2026-08` — PACIENTES category
> **Date**: 2026-08-12
> **PR scope**: PR-pacientes-04 only
> **Branch base**: `main` (stacked after PR-pacientes-03)
> **Review budget**: 400 authored lines / PR (target ~260)
> **Strict TDD**: true

## Goal

Migrate `PatientDetailPage.vue` Edit Patient modal (lines 706–845) + Export action surface to consume proven primitives. Edit Patient modal: `<UiModal>` chrome (replaces hand-built `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop) + `<UiSelect>` for gender (line 780) + `is_active` (line 792) + `<UiInput>` + hairline + `var(--focus-ring-default)`. Export action surface: PDF/ZIP dropdown → `<UiButton>` (Export trigger) + `<UiSelect>` (PDF/ZIP format) + `var(--focus-ring-default)`. **Binary download pattern preserved byte-for-byte**: the raw `fetch` + Bearer token + `window.URL.createObjectURL` + `<a download>` anchor click pattern at lines 1217–1225 stays verbatim (a JSON wrapper from `useApi()` would corrupt the binary stream). The `useApi` `PUT /api/patients/${id}` call signature + the 422 error envelope from `Rule::unique(...)->ignore($patient->id)` stay verbatim. `<script>` block is NEVER touched (`useEcho` channels + `usePermissions.can.updatePatient / export` + `useToast` preserved). `PatientResource` API envelope preserved; `ApiAndSeedersPolishTest` API-035 + API-057 stay GREEN.

## Depends on

- PR0 (landed): primitives + `canvasRoutes`.
- PR-pacientes-01..03 (landed): list polish + list modals + detail header + 5-tab drawer chrome established; `PatientModalChromeTest` + `PatientStatusBadgeTest` + `PatientTableNumsTest` green.

## Work items (ordered; foundation first, visual last)

- [ ] **T-04.1** — RED: extend `tests/Unit/DesignSystem/PatientModalChromeTest.php` (created in PR-pacientes-02). Add `assertDetailEditModalUsesUiPrimitives()` (regex: `<UiModal` + `<UiSelect` + `<UiInput` referenced in detail edit modal section; raw `<select class="border-theme focus:ring-primary-500 focus:border-transparent">` absent). Add `assertExportActionUsesUiButton()` (regex over export dropdown section: `<UiButton>` + `<UiSelect>` referenced; raw `<button class="...">` + raw `<select>` absent). Add `assertExportDownloadPatternPreserved()` (regex: `window\.URL\.createObjectURL` + `<a download>` anchor click pattern present byte-for-byte). Run PHPUnit: RED.
- [ ] **T-04.2** — Migrate Edit Patient modal backdrop + panel (lines 706–845): same `<UiModal>` + `<UiCard variant="glass">` chrome as PR-pacientes-02 (T-02.2 + T-02.4). Modal motion uses `var(--motion-duration-fast) var(--motion-easing-ios)`. Header close button → `<UiButton variant="ghost">`.
- [ ] **T-04.3** — Migrate Edit Patient form fields: raw `<input>` (first_name, last_name, dni, email, phone, birth_date, address, emergency_contact_name, emergency_contact_phone, medical_history, allergies, notes) → `<UiInput variant="bordered">` + hairline + `var(--focus-ring-default)`. Raw `<select>` for gender (line 780) + `is_active` (line 792) → `<UiSelect variant="bordered">`. Required asterisks → `<UiInput required>` indicator.
- [ ] **T-04.4** — Migrate submit affordance: REPLACE `<button :disabled="!canSubmit || loading" class="... disabled:opacity-30">` with `<UiButton :disabled="!canSubmit || loading" :loading="loading">` (inside-button `<UiLoadingSpinner v-if="loading" />`). Cancel button → `<UiButton variant="secondary">`. The `useApi` PUT `/api/patients/${id}` call signature + the 422 error envelope from `Rule::unique(...)->ignore($patient->id)` stay verbatim.
- [ ] **T-04.5** — Migrate Export trigger button: REPLACE raw `<button class="...">` with `<UiButton variant="primary">` triggering the format dropdown. Export dropdown chrome → `<UiSelect>` for PDF/ZIP format (replaces raw `<select class="border-theme focus:ring-primary-500 focus:border-transparent">`). `var(--focus-ring-default)` on both.
- [ ] **T-04.6** — Verify binary download pattern preserved: confirm `fetch(\`/api/patients/${id}/export?format=\${format}\`, { headers: { Authorization: \`Bearer \${token}\` } })` + `const blob = await response.blob()` + `const url = window.URL.createObjectURL(blob)` + `const a = document.createElement('a'); a.href = url; a.download = ...; a.click()` + `window.URL.revokeObjectURL(url)` pattern at lines 1217–1225 is byte-for-byte unchanged (the JSON wrapper from `useApi()` would corrupt the binary stream). `ApiAndSeedersPolishTest` API-035 (Content-Type `application/pdf`) + API-057 (Content-Type `application/zip`) stay GREEN.
- [ ] **T-04.7** — Verify isolation: `git diff --stat resources/js/modules/patients/PatientDetailPage.vue` shows zero edits to `<script setup>` block. `useEcho` `patients` + cross-category channels + `usePermissions.can.updatePatient` + `usePermissions.can.export` flags + `useToast` preserved byte-for-byte.
- [ ] **T-04.8** — GREEN: `PatientModalChromeTest::assertDetailEditModalUsesUiPrimitives()` + `assertExportActionUsesUiButton()` + `assertExportDownloadPatternPreserved()` pass. `git grep -nE "bg-black bg-opacity-50"` on `PatientDetailPage.vue` returns zero matches.
- [ ] **T-04.9** — Regression: `git grep -nE "focus:ring-primary-500 focus:border-transparent|disabled:opacity-30"` on `PatientDetailPage.vue` modal + export sections returns zero matches; `<script>` block byte-for-byte unchanged. `window.URL.createObjectURL` + `<a download>` anchor click pattern present byte-for-byte (regression witness).
- [ ] **T-04.10** — Tests: `php artisan test --filter=PatientModalChromeTest` + `--filter=PatientStatusBadgeTest` + `--filter=PatientTableNumsTest` + `--filter=ApiAndSeedersPolishTest` + `--filter=LegacyAliasForbiddenTest` + `--filter=PatientResourceAgeTest` all GREEN.
- [ ] **T-04.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-04.12** — Visual: `playwright-cli` snapshots at 1440x900 — `patient-detail-edit-modal-1440x900.png` (Edit Patient modal open); `patient-detail-export-dropdown-1440x900.png` (PDF/ZIP dropdown open); `patient-detail-export-pdf-download-1440x900.png` (PDF download triggered). Login: `recep@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-pacientes-04-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=PatientModalChromeTest` GREEN (3 assertions: list modals + detail edit modal + export action).
- [ ] `php artisan test --filter=ApiAndSeedersPolishTest` GREEN (API-035 + API-057 stay green).
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] Detail Edit Patient modal uses `<UiModal>` + `<UiSelect>` + `<UiInput>`; no raw `<select>` with `focus:ring-primary-500 focus:border-transparent` in modal sections; no `bg-black bg-opacity-50` backdrop.
- [ ] Export action uses `<UiButton>` + `<UiSelect>`; no raw `<button>` + raw `<select>`.
- [ ] Binary download pattern (`window.URL.createObjectURL` + Bearer token + anchor click) preserved byte-for-byte; `useApi()` NOT applied to export (JSON wrapper would corrupt stream).
- [ ] `useApi` PUT `/api/patients/${id}` call signature preserved; 422 `Rule::unique(...)->ignore($patient->id)` envelope rendering stays verbatim.
- [ ] `<script>` block of `PatientDetailPage.vue` byte-for-byte unchanged; `useEcho` `patients` + 4 cross-category channels preserved.
- [ ] `PatientResource` API envelope (additive `age` integer key) preserved.
- [ ] No regression in `PatientStatusBadgeTest`, `PatientTableNumsTest`, `PatientControllerAgeTest`, `PatientControllerResourceWireUpTest`, `ComposablesStandardizationTest`, `AppLayoutCanvasRoutesTest`.
- [ ] PR diff under 400 lines.
- [ ] 3 screenshots saved under `.playwright-cli/screenshots-rollout/pr-pacientes-04-*.png`.

## Out of scope (deferred to PR-pacientes-05)

- Cross-cutting `PatientsAppShellTest` + `PatientDetailAppShellTest` + a11y doc — PR-pacientes-05.
- Allergy / medical-history alert callout — global spec forbids new primitives; flagged in `a11y-followup.md`.
- `PatientSelector.vue` + `<Pagination>` consolidation — their own PRs.
- PDF template (`resources/views/exports/patient-file.blade.php`) restyling — print artifact, separate slice.
- Soft-delete + restore / forceDelete REST flows, dormant `$fillable` cleanup, `ClinicalAttachment.file_path` encryption at rest — separate changes.

## Test plan (commands)

```bash
php artisan test --filter=PatientModalChromeTest
php artisan test --filter=PatientStatusBadgeTest
php artisan test --filter=PatientTableNumsTest
php artisan test --filter=ApiAndSeedersPolishTest
php artisan test --filter=LegacyAliasForbiddenTest
php artisan test --filter=PatientResourceAgeTest
pnpm build
pnpm lint:check
git grep -nE "window\.URL\.createObjectURL" \
  resources/js/modules/patients/PatientDetailPage.vue
git grep -nE "bg-black bg-opacity-50|focus:ring-primary-500 focus:border-transparent" \
  resources/js/modules/patients/PatientDetailPage.vue
git diff --stat resources/js/modules/patients/PatientDetailPage.vue
playwright-cli screenshot http://localhost:5173/patients/1?openEditPatient=true 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-04-detail-edit-modal-1440x900.png
playwright-cli screenshot http://localhost:5173/patients/1?openExportDropdown=true 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-04-detail-export-dropdown-1440x900.png
```

## Key Learnings (forwarded to apply)

1. Binary download pattern at `PatientDetailPage.vue` lines 1217–1225 is byte-for-byte preserved — `window.URL.createObjectURL(blob)` + `<a download>` anchor click pattern is the ONLY safe way to download a Bearer-token-authenticated binary stream; `useApi()` JSON wrapper would corrupt the stream.
2. The `useApi` PUT `/api/patients/${id}` call signature stays verbatim; the 422 `Rule::unique(...)->ignore($patient->id)` envelope from `PatientController::update` is rendered via `useToast` (form stays open + server error surfaces verbatim).
3. `<script>` block of `PatientDetailPage.vue` is NEVER edited — `useEcho` `patients` + 4 cross-category channels (`treatment-plans`, `quotations`, `medical-records`, `specialty-records`) + `usePermissions.can.{updatePatient, export}` + `useToast` + `useApi` + `useAuditLogs.getPatientAuditLogs(patientId)` all preserved byte-for-byte.

## References

- `categories/pacientes/design.md` §3.6 (detail edit modal decision), §3.7 (export action decision), §6.2 (PR-pacientes-04 test extensions)
- `categories/pacientes/spec.md` `PAC-EDIT-001`, `PAC-EXP-001`
- `resources/js/modules/patients/PatientDetailPage.vue` lines 706–845 (edit modal) + lines 1217–1225 (binary download)
- `tests/Unit/Polish/ApiAndSeedersPolishTest.php` (API-035 + API-057 export Content-Type)
- `resources/views/exports/patient-file.blade.php` (PDF template — out of scope, print artifact)
- `CREDENTIALS.md` (`recep@test.com` for edit + export)
