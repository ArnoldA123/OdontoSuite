# PR-pacientes-02 — `PatientsPage` inlined New + Edit modals → `<UiModal>` chrome

> **Change**: `ui-rollout-all-modules-2026-08` — PACIENTES category
> **Date**: 2026-08-12
> **PR scope**: PR-pacientes-02 only
> **Branch base**: `main` (stacked after PR-pacientes-01)
> **Review budget**: 400 authored lines / PR (target ~280)
> **Strict TDD**: true

## Goal

Migrate the 2 inlined modals on `PatientsPage.vue` — New Patient modal (lines 463–581) + Edit Patient modal (lines 583–725) — to consume `<UiModal>` chrome (replaces hand-built `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop + `bg-theme-surface-elevated rounded-2xl shadow-2xl` panel + `border-b border-theme` header divider). Raw `<input>` / `<select>` / `<textarea>` form fields → `<UiInput>` / `<UiSelect>` / `<UiTextarea>` with hairline + `var(--focus-ring-default)`. The `useApi` POST/PUT signatures + the 422 duplicate-email/phone error envelope rendering stay verbatim (form stays open + server message surfaces via `useToast`). `<script>` block of `PatientsPage.vue` is NEVER touched (`useEcho` `patients` channel subscription + `usePermissions.can.createPatient / updatePatient` + `useToast` preserved byte-for-byte). `PatientResource` API envelope (additive `age` integer) preserved.

## Depends on

- PR0 (landed): primitives + `canvasRoutes`.
- PR-pacientes-01 (landed): list primitives (`<UiStatusBadge>`, `<UiCard clickable>`, `<UiButton variant="link|ghost">`) established.

## Work items (ordered; foundation first, visual last)

- [ ] **T-02.1** — RED: NEW `tests/Unit/DesignSystem/PatientModalChromeTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `PatientsPage.vue`. Add `assertListModalsUseUiModal()` (regex: `<UiModal` referenced in modal sections AND `bg-black bg-opacity-50` absent AND `<Teleport to="body">` absent). Add `assertListModalsRawFormFieldsAbsent()` (regex over modal sections: zero `<input class="border-theme">`, zero `<select class="border-theme">`, zero `<textarea class="border-theme">`). Run PHPUnit: RED.
- [ ] **T-02.2** — Migrate New Patient modal backdrop + panel: REPLACE `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` with `<UiModal :open="showNewPatientModal" @close="closeNewPatientModal" title="Nuevo paciente" />`. REPLACE `bg-theme-surface-elevated rounded-2xl shadow-2xl` panel with `<UiCard variant="glass">`. REPLACE `border-b border-theme` header divider with hairline (`border-b border-hairline`). Modal motion uses `var(--motion-duration-fast) var(--motion-easing-ios)`.
- [ ] **T-02.3** — Migrate New Patient form fields (lines 463–581): raw `<input>` (first_name, last_name, dni, email, phone, birth_date, emergency_contact_name, emergency_contact_phone, address) → `<UiInput variant="bordered">` with hairline + `var(--focus-ring-default)`; raw `<select>` (gender) → `<UiSelect variant="bordered">`; raw `<textarea>` (medical_history, allergies, notes) → `<UiInput type="textarea">`; required asterisks → `<UiInput required>` indicator. Zero `border border-theme bg-theme-surface-elevated` literals remain.
- [ ] **T-02.4** — Migrate Edit Patient modal backdrop + panel (lines 583–725): same `<UiModal>` + `<UiCard>` chrome as T-02.2. `is_active` toggle → `<UiSelect>` (replaces raw `<select>`). Status badge inside the modal header (when editing active patient) → `<UiStatusBadge variant="success">`.
- [ ] **T-02.5** — Migrate Edit Patient form fields: same field migration as T-02.3 + the `is_active` `<UiSelect>`. `useApi` PUT call signature + the 422 `Rule::unique(...)->ignore($patient->id)` envelope rendering stays verbatim (the catch block surfaces the server error message via `useToast`, form stays open).
- [ ] **T-02.6** — Migrate submit affordance: REPLACE `<button :disabled="!canSubmit || loading" class="... disabled:opacity-30">` with `<UiButton :disabled="!canSubmit || loading" :loading="loading">` (inside-button `<UiLoadingSpinner v-if="loading" />`). Cancel button → `<UiButton variant="secondary">`. Header close button → `<UiButton variant="ghost">`.
- [ ] **T-02.7** — Verify isolation: `git diff --stat resources/js/modules/patients/PatientsPage.vue` shows zero edits to `<script setup>` block; `useEcho` `patients` channel `.patient.updated` subscription + `useApi().post('/api/patients', …)` / `useApi().put('/api/patients/${id}', …)` signatures + `usePermissions.can.{createPatient, updatePatient}` flags stay verbatim. `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` stay GREEN.
- [ ] **T-02.8** — GREEN: `PatientModalChromeTest::assertListModalsUseUiModal()` + `assertListModalsRawFormFieldsAbsent()` pass. `git grep -nE "bg-black bg-opacity-50"` on `PatientsPage.vue` returns zero matches.
- [ ] **T-02.9** — Regression: `git grep -nE "focus:ring-primary-500 focus:border-transparent|<Teleport to=|disabled:opacity-30|border border-theme bg-theme-surface-elevated"` on `PatientsPage.vue` returns zero matches; `<script>` block byte-for-byte unchanged.
- [ ] **T-02.10** — Tests: `php artisan test --filter=PatientModalChromeTest` + `--filter=PatientTableNumsTest` + `--filter=LegacyAliasForbiddenTest` + `--filter=ComposablesStandardizationTest` + `--filter=PatientResourceAgeTest` all GREEN.
- [ ] **T-02.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-02.12** — Visual: `playwright-cli` snapshots at 1440x900 — `patients-new-patient-modal-1440x900.png` (New Patient modal open); `patients-edit-patient-modal-1440x900.png` (Edit Patient modal open with sample data); `patients-modal-422-error-1440x900.png` (duplicate email error rendered via `useToast`). Login: `recep@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-pacientes-02-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=PatientModalChromeTest` GREEN (2 list-modal assertions).
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] No `bg-black bg-opacity-50`, no `<Teleport to="body">` in `PatientsPage.vue`.
- [ ] No `focus:ring-primary-500 focus:border-transparent`, no `disabled:opacity-30` affordance in `PatientsPage.vue`.
- [ ] Submit uses `<UiLoadingSpinner>` + `<UiButton :loading>`; cancel uses `<UiButton variant="secondary">`.
- [ ] 422 duplicate-email/phone error envelope rendering stays verbatim (form stays open + `useToast` surfaces server message).
- [ ] `<script>` block of `PatientsPage.vue` byte-for-byte unchanged; `useEcho` `patients` + `useApi` + `usePermissions.can.createPatient/updatePatient` contracts preserved.
- [ ] `PatientResource` API envelope (additive `age` integer key) preserved.
- [ ] No regression in `PatientTableNumsTest`, `LegacyAliasForbiddenTest`, `AppLayoutCanvasRoutesTest`, `ComposablesStandardizationTest`, `PatientResourceAgeTest`, `PatientControllerAgeTest`.
- [ ] PR diff under 400 lines.
- [ ] 3 screenshots saved under `.playwright-cli/screenshots-rollout/pr-pacientes-02-*.png`.

## Out of scope (deferred to PR-pacientes-03..05)

- `PatientDetailPage.vue` header + 5-tab drawer + cross-category deep-links + audit tab — PR-pacientes-03.
- `PatientDetailPage.vue` Edit Patient modal (lines 706–845) — PR-pacientes-04.
- `PatientDetailPage.vue` Export action surface — PR-pacientes-04.
- Cross-cutting `PatientsAppShellTest` + `PatientDetailAppShellTest` + a11y doc — PR-pacientes-05.
- Allergy / medical-history alert component (global spec forbids new primitives; flagged in `a11y-followup.md` by PR-pacientes-05).
- `PatientSelector.vue` + `<Pagination>` consolidation — out of scope (their own PRs).

## Test plan (commands)

```bash
php artisan test --filter=PatientModalChromeTest
php artisan test --filter=PatientTableNumsTest
php artisan test --filter=LegacyAliasForbiddenTest
php artisan test --filter=ComposablesStandardizationTest
php artisan test --filter=PatientResourceAgeTest
pnpm build
pnpm lint:check
git grep -nE "bg-black bg-opacity-50|Teleport to=|disabled:opacity-30" \
  resources/js/modules/patients/PatientsPage.vue
git diff --stat resources/js/modules/patients/PatientsPage.vue
playwright-cli screenshot http://localhost:5173/patients?openNewPatient=true 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-02-new-patient-modal-1440x900.png
playwright-cli screenshot http://localhost:5173/patients/1?openEditPatient=true 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-pacientes-02-edit-patient-modal-1440x900.png
```

## Key Learnings (forwarded to apply)

1. 422 duplicate-email/phone envelope stays verbatim — the catch block on `useApi().post(...)` surfaces `errors.email[0]` via `useToast`, the form stays open. The edit path uses `Rule::unique(...)->ignore($patient->id)`, which preserves the current patient's email/phone.
2. Modal motion duration uses `var(--motion-duration-fast) var(--motion-easing-ios)` — inherited from `<UiModal>` primitive; no per-instance override.
3. `<UiModal>` emit contract (`open` / `close` / `confirm`) preserves existing `showNewPatientModal` / `showEditPatientModal` reactive refs in the page's `<script>` block — zero caller-side changes needed; the page's `<script>` stays byte-for-byte unchanged.

## References

- `categories/pacientes/design.md` §3.4 (modal chrome decision), §6.2 (PR-pacientes-02 test extensions)
- `categories/pacientes/spec.md` `PAC-MOD-001`
- `resources/js/modules/patients/PatientsPage.vue` lines 463–725 (modal sections)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base class to extend)
- `resources/js/composables/useEcho.js` (`patients` channel subscription — preserved)
- `database/migrations/2025_09_27_135908_add_unique_constraints_to_patients_table.php` (email + phone unique indexes)
- `CREDENTIALS.md` (`recep@test.com` for modal)
