# Spec: PACIENTES Category Delta — `ui-rollout-all-modules-2026-08`

> **Delta type**: Category delta spec. Sibling of the global
> `design-language-rollout` and `foundation-primitives` specs and the
> PAGOS + CITAS category deltas. Extends the global rollout with
> PACIENTES-specific rows that the parent `DLR-MOD-003` (Pacientes) and
> cross-cutting `DLR-CORE-*` rules do not enumerate.
>
> **Naming convention**: archive convention `specs/<domain>/spec.md`.
> Signing key: `PAC-*` for PACIENTES-only rows.

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | PACIENTES (patient list, patient detail, 3 inlined modals, audit log tab, export action surface) |
| Date | 2026-08-12 |
| SDD phase | `spec` (3 of 6) — category slice |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/pacientes/spec`) |
| Delivery strategy | `auto-chain` (inherited from global; PACIENTES sub-PRs `pr-pacientes-01..05`) |
| Review budget | 400 authored lines / PR (re-scoped per user) |
| Strict TDD | `true` (forward to apply/verify) |
| Parent spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` |
| Parent proposal | `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/proposal.md` |
| Parent explore | `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/explore.md` |
| PACIENTES PRs | `pr-pacientes-01..05` (5 chained PRs — see PACIENTES proposal §6) |

### Relationship to parent spec

This spec does NOT modify the global `design-language-rollout/spec.md`
rows. It is a sibling that adds PACIENTES-specific delta rows. The
`DLR-CORE-*` and `DLR-MOD-003` rules apply to PACIENTES unmodified;
the rows below add category-specific edges (list primitive adoption +
tabular-nums on DNI/age, 3 inlined modals → `<UiModal>` chrome, 5-tab
drawer → `<UiTabs>`, export action surface, Echo channel preservation
on `patients` + 4 cross-category channels, `PatientResource` additive
`age` key preservation, cross-category deep-link byte-for-byte
preservation, per-PR 400-line review budget isolation, existing
contracts preservation).

---

## 1. Purpose

This spec covers the PACIENTES interfaces of OdontoSuite only: the
patient list (`/patients`), the patient detail page (`/patients/:id`)
with its 5-tab drawer (Datos / Planes / Presupuestos / Historia Clínica
/ Especialidades / Historial de auditoría), the three inlined patient
modals (New Patient + Edit Patient in `PatientsPage` + Edit Patient in
`PatientDetailPage`), the per-patient audit log surface, and the
PDF/ZIP export action surface. It extends the global
`design-language-rollout` spec with PACIENTES-specific deltas: mandate
`UiCard` / `UiInput` / `UiSelect` / `UiButton` adoption across the list
+ 3 modals, `<UiModal>` chrome (no hand-built `bg-black bg-opacity-50`
backdrop), `<UiTabs>` on the 5-tab drawer, `tabular-nums` on DNI + age
columns, removal of `<style scoped>` blocks from both pages, preservation
of the `useEcho` subscriptions on the `patients` channel and the 4
cross-category channels (`treatment-plans`, `quotations`,
`medical-records`, `specialty-records`), preservation of the
`PatientResource` API envelope (additive `age` integer key), preservation
of the 4 cross-category deep-link `?patient_id=…` surfaces, preservation
of the Bearer-token + `createObjectURL` binary download pattern, per-PR
400-line review budget isolation, and byte-for-byte preservation of the
`useEcho` / `usePermissions` / `useToast` / `useConfirm` / `useApi` /
`useAuditLogs` contracts.

---

## 2. ADDED Requirements

### Requirement: `PAC-LIST-001` — `PatientsPage` list MUST consume Ui primitives + tabular-nums on DNI/age

The system MUST replace every `border-theme` table divider, `divide-theme`
row divider, `bg-success-badge` / `bg-danger-badge` status pill, raw
`text-green-600` / `text-red-600` mobile action button, `text-accent
hover:text-primary-700` link button, `hover-lift` stat card, and raw
`<input>` / `<select>` field on `PatientsPage.vue` with the
corresponding `Ui*`-prefixed primitive. The 4 stat cards MUST consume
`<UiCard clickable>` (NOT `hover-lift`). The DNI + age columns MUST
carry `font-feature-settings: var(--font-features-tabular-nums)` so the
ID column stops jittering. The list page MUST consume
`bg-theme-surface-elevated` only (NOT mixed `bg-theme-surface` /
`bg-theme-surface-elevated`).

#### Scenario: `PAC-LIST-001-1` — List page uses Ui primitives and tabular-nums

- GIVEN `PatientsPage.vue` (1249 lines) renders 4 stat cards, a status filter, a desktop table, a mobile card fallback, and pagination
- WHEN PR-pacientes-01 lands
- THEN `PatientsAppShellTest::test_list_uses_ui_primitives` asserts the rule (token reference exists, `border-theme` / `bg-success-badge` / `bg-danger-badge` / `divide-theme` / `hover-lift` absent)
- AND `PatientTableNumsTest` asserts `tabular-nums` is present on the DNI column + the age column
- AND `LegacyAliasForbiddenTest` (extended) returns zero matches for any of the forbidden aliases on the list page

### Requirement: `PAC-MOD-001` — Three inlined patient modals MUST use `<UiModal>` + `<UiInput>` + `<UiSelect>`

The system MUST replace every hand-built `<div class="fixed inset-0
bg-black bg-opacity-50 … z-50">` modal backdrop, `bg-theme-surface-
elevated rounded-2xl shadow-2xl` panel, `border-b border-theme` header
divider, raw `<input>` / `<select>` / `<textarea>` field, and `focus:ring-
primary-500 focus:border-transparent` ring in the New Patient modal and
the Edit Patient modal of `PatientsPage.vue` with the canonical
`<UiModal>` chrome + `<UiInput>` / `<UiSelect>` / `<UiTextarea>`
primitives + hairline dividers + `var(--focus-ring-default)` focus
ring. The capture-form rule applies to all 3 modals (New Patient modal
`PatientsPage.vue` lines 463–581; Edit Patient modal `PatientsPage.vue`
lines 583–725; Edit Patient modal `PatientDetailPage.vue` lines
706–845).

#### Scenario: `PAC-MOD-001-1` — Three inlined modals all use UiModal chrome

- GIVEN the New Patient + Edit Patient modals in `PatientsPage.vue` and the Edit Patient modal in `PatientDetailPage.vue` each render a hand-built backdrop + raw form fields
- WHEN PR-pacientes-02 lands (list modals) + PR-pacientes-04 lands (detail edit modal)
- THEN `PatientModalChromeTest::test_modal_uses_ui_modal` asserts the rule on each of the 3 modals (`<UiModal>` wrapper present, `bg-black bg-opacity-50` absent)
- AND `git grep -nE 'bg-black bg-opacity-50' resources/js/modules/patients/PatientsPage.vue resources/js/modules/patients/PatientDetailPage.vue` returns zero matches
- AND the `useApi` 422 duplicate-email/phone error envelope rendering stays verbatim (form stays open + server message surfaces via `useToast`)

### Requirement: `PAC-DET-001` — `PatientDetailPage` 5-tab drawer MUST consume `<UiTabs>` + cross-category deep-links preserved

The system MUST replace the raw `<button>` step strip with
`border-accent text-accent` active indicator (line 87) on
`PatientDetailPage.vue` with `<UiTabs>` (the canonical primitive) wired
to `var(--motion-duration-fast) var(--motion-easing-ios)` transitions.
The 5-tab drawer MUST continue to deep-link to `/treatment-plans?
patient_id=…`, `/quotations?patient_id=…`, `/medical-records?patient_id=…`,
and `/specialty-records?patient_id=…` byte-for-byte. The change-diff
callout at line 669 (legacy `border-l-2 border-theme`) MUST consume a
hairline token. The `<style scoped>` block at line 1556
(`.tab-content { min-height: 400px }`) MUST be removed and the contents
rewritten to plain utility classes (`min-h-[400px]`).

#### Scenario: `PAC-DET-001-1` — Tabs use UiTabs and deep-links stay byte-for-byte

- GIVEN `PatientDetailPage.vue` (1480 lines) renders 5 tabs across Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría
- WHEN PR-pacientes-03 lands
- THEN `PatientDetailAppShellTest::test_tabs_use_ui_tabs` asserts the rule (`<UiTabs>` reference present, raw `border-accent text-accent` active indicator absent, inline `@click="currentStep = step.id"`-style handler absent)
- AND `PatientDetailAppShellTest::test_cross_category_deep_links_preserved` asserts the 4 `router.push(...)` calls remain byte-for-byte
- AND `ModuleAppShellTestCase::test_no_style_scoped` green for `PatientDetailPage.vue`

### Requirement: `PAC-EDIT-001` — `PatientDetailPage` Edit Patient modal MUST consume `<UiModal>` + `<UiSelect>` for gender + is_active

The system MUST replace the hand-built backdrop, raw `<select>` for
gender + `is_active` (lines 780 + 792), `bg-theme-surface-elevated`
panel, and `focus:ring-primary-500 focus:border-transparent` ring in the
inlined Edit Patient modal of `PatientDetailPage.vue` with `<UiModal>`
chrome + `<UiSelect>` + `<UiInput>` + hairline divider +
`var(--focus-ring-default)` focus ring. The `useApi` `PUT /api/patients/
{id}` call signature MUST stay verbatim; the 422 error envelope from
`Rule::unique(...)->ignore($patient->id)` MUST stay verbatim.

#### Scenario: `PAC-EDIT-001-1` — Detail Edit modal uses Ui primitives

- GIVEN the inlined Edit Patient modal in `PatientDetailPage.vue` lines 706–845 carries raw `<select>` for gender + `is_active`
- WHEN PR-pacientes-04 lands
- THEN `PatientModalChromeTest::test_detail_edit_modal_uses_ui_primitives` asserts the rule on the detail edit modal specifically (`<UiModal>` + `<UiSelect>` + `<UiInput>` present, raw `<select>` + hand-built backdrop absent)
- AND the `useApi` update call stays verbatim (no axios, no fork)
- AND the 422 error envelope from the email/phone unique constraint surfaces verbatim via `useToast`

### Requirement: `PAC-EXP-001` — Export action surface MUST use `<UiButton>` + preserve Bearer-token binary download pattern

The system MUST replace the legacy export action chrome (PDF / ZIP) on
`PatientDetailPage.vue` with `<UiButton>` + `<UiSelect>` (NOT raw
`<button>` + raw `<select>`). The raw `fetch` + Bearer token +
`window.URL.createObjectURL` + `<a download>` anchor click pattern at
lines 1217–1225 MUST stay byte-for-byte (a JSON wrapper would corrupt
the binary stream; `useApi()` cannot replace it).

#### Scenario: `PAC-EXP-001-1` — Export action uses Ui primitives and the binary download stays verbatim

- GIVEN the export action surface triggers `GET /api/patients/${id}/export?format=pdf|zip` with a Bearer token and streams the binary
- WHEN PR-pacientes-04 lands
- THEN `PatientDetailAppShellTest::test_export_action_uses_ui_button` asserts `<UiButton>` + `<UiSelect>` adoption on the export dropdown
- AND `git grep -nE 'window\.URL\.createObjectURL' resources/js/modules/patients/PatientDetailPage.vue` confirms the pattern is present byte-for-byte
- AND `ApiAndSeedersPolishTest` API-035 + API-057 stay green (`application/pdf` / `application/zip` Content-Type whitelisted)

### Requirement: `PAC-RT-001` — `useEcho` channel subscriptions MUST stay subscribed byte-for-byte

The system MUST keep every `useEcho` channel subscription on
`PatientsPage.vue` and `PatientDetailPage.vue` firing verbatim. The
channels are: `patients` (`.patient.updated`), `treatment-plans`
(`.treatment-plan.{created,updated,deleted}`), `quotations`
(`.quotation.{created,updated,deleted}`), `medical-records`
(`.medical-record.{created,updated,deleted}`), `specialty-records`
(`.specialty-record.{created,updated,deleted}`). The `dashboard-updates`
channel is NOT consumed by the paciente module (the Dashboard page
consumes it). Visual changes MUST NOT touch `<script>` blocks.

#### Scenario: `PAC-RT-001-1` — All 5 channels stay subscribed

- GIVEN the per-tab deep-link create buttons on `PatientDetailPage.vue` rely on cross-category Echo events firing
- WHEN any PR-pacientes-NN lands
- THEN `git diff --stat` shows zero edits to `<script>` blocks of `PatientsPage.vue` and `PatientDetailPage.vue`
- AND manual smoke test: two browser tabs on `/patients/:id`, update the patient in tab A, verify tab B receives the `patient.updated` event within 1 second
- AND the cross-category channels continue firing on the Planes / Presupuestos / Historia Clínica / Especialidades tab create buttons

### Requirement: `PAC-PHI-001` — `PatientResource` API envelope MUST NOT widen or narrow

The system MUST preserve the `PatientResource` API envelope byte-for-
byte. The additive `age` integer key (computed via `$this->birth_date-
>diffInYears(now())`) MUST stay. The `email`, `phone`, `birth_date`,
`address`, `medical_history`, `allergies`, `notes` fields MUST continue
exposing for every viewer (the `PatientPolicy::view` return-true
posture is OUT of scope; the cross-branch PHI scope guard is a separate
change). The conditional counter fields (`appointments_count`,
`treatment_plans_count`, `quotations_count`, `medical_records_count`)
and conditional relations (`appointments`, `treatmentPlans`,
`quotations`, `medicalRecords`) via `whenLoaded` / `when` MUST stay
verbatim.

#### Scenario: `PAC-PHI-001-1` — Additive age key and PHI surface preserved

- GIVEN `PatientResourceAgeTest` (7 cases on `PatientResource::toArray()`) + `PatientControllerAgeTest` pin the additive `age` key
- WHEN any PR-pacientes-NN lands
- THEN both tests stay green at every PR boundary
- AND `PatientControllerResourceWireUpTest` stays green (every public CRUD method references `PatientResource`)
- AND the API envelope is NOT widened or narrowed — no field is added or removed

### Requirement: `PAC-DEEP-001` — Cross-category deep-links MUST stay byte-for-byte

The system MUST preserve the 4 cross-category deep-link `router.push`
calls on `PatientDetailPage.vue` byte-for-byte:
`router.push('/treatment-plans?patient_id=…')`,
`router.push('/quotations?patient_id=…')`,
`router.push('/medical-records?patient_id=…')`,
`router.push('/specialty-records?patient_id=…')`. The per-tab create
buttons (Planes / Presupuestos / Historia Clínica / Especialidades)
MUST keep their navigation contract identical across all 4 deep-link
surfaces.

#### Scenario: `PAC-DEEP-001-1` — All 4 deep-links preserved verbatim

- GIVEN the per-tab create buttons navigate to other modules with the `?patient_id=…` query param
- WHEN PR-pacientes-03 lands
- THEN `PatientDetailAppShellTest::test_cross_category_deep_links_preserved` asserts the 4 `router.push(...)` patterns remain byte-for-byte
- AND visual smoke test: click "Crear plan" on the Planes tab, verify the URL contains `?patient_id=<id>` and the treatment-plans page loads

### Requirement: `PAC-REV-001` — Each `pr-pacientes-NN` MUST stay under the 400-line review budget

The system MUST keep each `pr-pacientes-NN` PR under the 400-line
authored review budget. When a PR's diff exceeds 400 lines (PR-
pacientes-01 ~390 lines + PR-pacientes-03 ~390 lines are right at the
budget), the apply phase MUST split per the `chained-pr` skill (e.g.
PR-pacientes-01a + 01b for the desktop table vs the mobile card
fallback; PR-pacientes-03a + 03b for the 5-tab drawer chrome vs the
per-tab deep-link create buttons).

#### Scenario: `PAC-REV-001-1` — PR-pacientes-01 and PR-pacientes-03 split when needed

- GIVEN `PatientsPage.vue` (1249 lines) + `PatientDetailPage.vue` (1480 lines) are the largest single Vue files in PACIENTES
- WHEN the PR-pacientes-01 + PR-pacientes-03 diffs are reviewed
- THEN `git diff --stat` reports `additions + deletions <= 400` per PR
- AND if a diff exceeds 400 lines, the PR is split BEFORE the review starts

### Requirement: `PAC-CON-001` — Existing paciente contracts MUST be preserved

The system MUST preserve the public contracts of `useEcho`,
`usePermissions`, `useToast`, `useConfirm`, `useApi`, and `useAuditLogs`
consumed by the pacientes module byte-for-byte. The
`usePermissions.can.{createPatient, updatePatient, deletePatient,
createTreatmentPlan, createQuotation, createMedicalRecord,
createSpecialtyRecord}` flags MUST stay verbatim. The
`useAuditLogs.getPatientAuditLogs(patientId)` call MUST stay verbatim.
The `useConfirm` delete-confirmation flow MUST stay verbatim. Visual
changes MUST NOT touch `<script>` blocks.

#### Scenario: `PAC-CON-001-1` — All 6 composable contracts stay green

- GIVEN `ComposablesStandardizationTest` pins the 6 composable surfaces
- WHEN any PR-pacientes-NN lands
- THEN `ComposablesStandardizationTest` stays green at every PR boundary
- AND `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` are byte-for-byte unchanged
- AND the `useEcho` channel list + the `usePermissions.can.*` flags + the `useAuditLogs.getPatientAuditLogs(...)` call all stay verbatim

---

## 3. Out-of-scope explicit list

Mirrors the PACIENTES proposal §3. Items are excluded from the
PACIENTES rollout and explicitly recorded so the apply phase does NOT
silently resolve them.

| Item | Reason |
|---|---|
| Cross-module `PatientSelector.vue` primitive | Consumed by 6+ modules; rides the same PR5 cluster or a later cluster as a separate PR per global OQ#7 |
| Pagination primitive duplication (`<Pagination>` import at lines 742, 752) | Consolidated by global PR3 (Recepción procedimientos) per global proposal §7.5; rename rides that PR |
| `resources/views/exports/patient-file.blade.php` (PDF template) | Print artifact consumed outside the SPA; DOMPDF palette out of visual-language scope |
| Dormant `Patient::$fillable` entries (`dni`, `blood_type`, `insurance_provider`, `insurance_number`) | No migration column matches them; cleanup is separate |
| `Patient::scopeActive` query scope | Backend, not a UI surface |
| `PatientExportService::exportToPdf` / `exportToZip` synchronous export | Backend, triggered by the UI's Export action only |
| `ExportPatientFileJob` async export | Backend, triggered by the `?async=1` flag |
| `PatientCreated / Updated / Deleted / FileExported` events | Backend, consumed by `useEcho` only |
| `LogPatientActivity` / `NotifyPatientFileExported` listeners | Backend |
| `PatientPolicy` role gating | Backend; role gating preserved verbatim |
| `PatientResource` envelope changes | Backend; additive `age` key MUST stay (pinned by tests) |
| Cross-branch PHI scoping | `PatientPolicy::view` returns `true` for every authenticated role; scope guard is a separate change |
| Audit log retention policy | DB layer; no UI change |
| `Patient::restore()` / `forceDelete()` flows | Policy methods exist; no `restore` REST route |
| Soft-delete + appointments-conflict 422 contract | `PatientController::destroy` unchanged; the 422 envelope rendering at `PatientDetailPage.vue` lines 1152–1159 stays verbatim |
| `document_number` → `DOC-XXX` rendering | UX decision, not a visual-token decision |
| Allergy / medical-history alert component | Global spec forbids new primitives; the free-text display is the contract |
| `ClinicalAttachment.file_path` encryption at rest on the `public` disk | Separate change |
| `consent_forms` / `patient_consents` / `family_relationships` / `guardians` tables | OUT of scope |
| Search performance on large patient lists (5-axis `LIKE %term%`) | Not a visual defect |
| `settings/branches` + `settings/payment-methods` | Per global OQ#3 — OUT of scope for this rollout |
| `MobileNavigation.vue` + `ThemeSelector.vue` (dead code) | Removal is OUT of scope |
| Two-tone numerals (D12 REVERSIBLE from vertical slice) | Stays rejected |
| New tokens / new primitives beyond PR0's `<UiStatusBadge>` | `tokens.js` is frozen for the rollout |
| `<script>` blocks of pacientes module files | UI changes are template-level class-string replacement only |

---

## 4. Verification strategy

- **Visual**: `pnpm build` clean; `git grep` for `border-theme`,
  `bg-success-badge`, `bg-danger-badge`, `text-accent hover:text-primary-700`,
  `bg-theme-surface-elevated` on the page surface, `bg-theme-surface`,
  `divide-theme`, `bg-black bg-opacity-50`, `hover-lift`, raw
  `text-green-600` / `text-red-600`, raw `<select>` with
  `focus:ring-primary-500 focus:border-transparent` returns zero matches
  inside `resources/js/modules/patients/PatientsPage.vue` and
  `resources/js/modules/patients/PatientDetailPage.vue`. `playwright-cli`
  snapshot at 1440×900 + 390×844 (list page only — mobile card fallback)
  saved to `.playwright-cli/screenshots-rollout/patients-list-{1440x900,
  390x844}.png` + `.playwright-cli/screenshots-rollout/patient-detail-
  1440x900.png`. Credentials: `recep@test.com` for list + detail;
  `admin@test.com` for delete flows (admin-only).
- **Static (PHPUnit)**: `PatientsAppShellTest` + `PatientDetailAppShellTest`
  extend `ModuleAppShellTestCase` and assert the per-page rule (token
  reference exists, alias absent, `<style scoped>` absent, `<UiModal>`
  wrapper present on 3 modals, 4 cross-category deep-links preserved).
  `PatientStatusBadgeTest` asserts `<UiBadge>` (or `<UiStatusBadge>`)
  variant presence + `bg-success-badge` / `bg-danger-badge` absence on
  the detail header + list row status pills.
  `PatientTableNumsTest` asserts `tabular-nums` on the DNI + age
  columns in `PatientsPage` + the `document_number` / age cells in
  `PatientDetailPage`. `PatientModalChromeTest` asserts `<UiModal>`
  wrapper presence + `bg-black bg-opacity-50` absence on all 3
  inlined modals. `LegacyAliasForbiddenTest` (extended) green at
  every PR-pacientes-NN boundary.
- **Runtime**: `AppLayoutCanvasRoutesTest`, `PatientControllerAgeTest`,
  `PatientResourceAgeTest`, `PatientControllerResourceWireUpTest`,
  `ApiAndSeedersPolishTest` (API-035 + API-057), `ComposablesStand-
  ardizationTest`, `LegacyAliasForbiddenTest` stay green at every
  PR-pacientes-NN boundary. Manual smoke test: two browser tabs on
  `/patients/:id`, update the patient in tab A, verify tab B receives
  the `patient.updated` event within 1 second; click each of the 4
  per-tab create buttons, verify the URL contains the `?patient_id=…`
  deep-link and the destination module loads.

---

## 5. Acceptance criteria

The PACIENTES category is considered complete when ALL of the
following hold: both pacientes routes (`/patients`, `/patients/:id`)
render on `var(--color-canvas)` without legacy alias classes; the 3
inlined modals (New + Edit in `PatientsPage` + Edit in
`PatientDetailPage`) use `<UiModal>` chrome; status pills on the list
rows + detail header use `<UiBadge>` (or `<UiStatusBadge>`); tabular
columns (DNI, age) carry `font-feature-settings: var(--font-features-
tabular-nums)`; stat cards use `<UiCard clickable>` (NOT `hover-lift`);
5-tab drawer uses `<UiTabs>` (NOT raw `border-accent text-accent` tab
strip); both `PatientsPage.vue` + `PatientDetailPage.vue` have zero
`<style scoped>` blocks; `useEcho` subscriptions on `patients` + the 4
cross-category channels stay subscribed; `PatientResource` API envelope
preserved verbatim (additive `age` integer key stays); cross-category
deep-links preserved verbatim (4 `router.push(...)` calls stay byte-
for-byte); `PatientExportService` download pattern preserved (raw
`fetch` + Bearer token + `window.URL.createObjectURL` + anchor click
stays); soft-delete + appointments-conflict 422 contract preserved;
`<Pagination>` import kept verbatim; no new primitives introduced; each
PR-pacientes-NN stays under the 400-line authored budget; per-PR
`playwright-cli` snapshots saved; CI gates (`quality`, `backend-tests`
MySQL, `frontend-build` pnpm) green at every PR-pacientes-NN boundary.

---

## 6. References

- `categories/pacientes/explore.md` — PACIENTES inventory (frontend,
  backend, controllers, services, jobs, models, tests, known gotchas).
- `categories/pacientes/proposal.md` — PACIENTES proposal (intent,
  scope, risk register, rollback, success criteria).
- `specs/design-language-rollout/spec.md` — parent spec (`DLR-MOD-003`
  Pacientes, cross-cutting `DLR-CORE-*` rules).
- `specs/foundation-primitives/spec.md` — PR0 spec (`<UiStatusBadge>`,
  `canvasRoutes`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`).
- `specs/citas/spec.md` — sibling CITAS category spec (format + tone
  reference).
- `specs/pagos/spec.md` — sibling PAGOS category spec (format + tone
  reference).
- `archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` —
  process lesson: "tests pin the rule, not the literal." PACIENTES
  structure tests extend `ModuleAppShellTestCase` and assert the rule.

---

*End of PACIENTES category spec.*
