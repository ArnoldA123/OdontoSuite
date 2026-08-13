# Proposal: UI Rollout — PACIENTES category (`ui-rollout-all-modules-2026-08`)

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | PACIENTES (patient list, patient detail, New Patient modal, Edit Patient modals, audit log tab, export action surface) |
| Date | 2026-08-12 |
| Phase | propose (2 of 6) — category slice |
| Author | `sdd-propose` sub-agent (PACIENTES) |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/pacientes/proposal`) |
| Parent artifacts | `proposal.md` (596 lines), `explore.md` (496 lines), `categories/pacientes/explore.md` (213 lines), `specs/design-language-rollout/spec.md` |
| Sibling categories | PAGOS (`categories/pagos/proposal.md`, archived 2026-08-12), CITAS (`categories/citas/proposal.md`, archived 2026-08-12) |
| Global PR mapping | PACIENTES = global PR5 (`pr5-patients-tokenise`) per global proposal §7.7; PACIENTES sub-PRs `pr-pacientes-01..05` split PR5's largest-CRLD scope into chained work units |
| Delivery strategy | Inherits `auto-chain` from the global proposal; PACIENTES sub-PRs `pr-pacientes-01..05` stack inside PR5 |
| Review budget | 400 authored lines / PR (per global proposal §7.15) |
| Strict TDD | `true` (forward to apply/verify) |
| Vertical slice baseline | `ui-premium-microdetail-2026-08` — closed 2026-08-11; tokens, primitives, easing, focus-ring, canvas/surface separation, `tabular-nums` all inherited as-is |

### Preflight snapshot (verbatim from global proposal)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain      # chained PRs auto-activate; do NOT re-ask
review_budget_lines: 400
chain_strategy: not_cached         # recommend stacked-to-main at sdd-tasks time
strict_tdd: true
```

---

## 1. Intent

PACIENTES is the demographic core of OdontoSuite: every patient record starts in `/patients` (list) and lives on `/patients/:id` (detail). Receptionists search by name/DNI/phone to look up an arriving patient, filter by active/inactive, paginate through hundreds of records, open the New Patient modal for walk-ins, and click into the detail page to manage 5-tab deep-links (Planes → treatment plans, Presupuestos → quotations, Historia Clínica → medical records, Especialidades → specialty records, Historial de auditoría → audit log). The detail page also triggers per-patient export (PDF or ZIP). The proven Apple language landed on Dashboard, Login, and 404; PACIENTES still reads as legacy on its two largest patient surfaces: `PatientsPage.vue` (1249 lines, 44.5 KB) and `PatientDetailPage.vue` (1480 lines, 53.4 KB).

The defects are concentrated and visual: `border-theme` table dividers, `bg-success-badge / bg-danger-badge` status pills (legacy alias classes that don't tokenise cleanly), `text-accent hover:text-primary-700` link buttons, raw `text-green-600 / text-red-600` mobile action buttons, `hover-lift` stat cards, `bg-theme-surface-elevated / bg-theme-surface` mixed surfaces, `divide-theme` row dividers, a custom `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` modal backdrop inside both pages, raw `<select>` gender/status dropdowns with `focus:ring-primary-500 focus:border-transparent`, and tabular columns (DNI, age) that lack `tabular-nums` — so the ID column jitters when numbers grow. Both pages carry a `<style scoped>` block that must be rewritten per the vertical slice's no-`<style scoped>` rule. The PatientDetailPage's 5-tab drawer deep-links into 4 other modules via `?patient_id=…`; the deep-link surface must be preserved verbatim.

This proposal scopes the rollout to **only** the pacientes interfaces inventoried in `categories/pacientes/explore.md`. It inherits every rule from the global proposal (token discipline, primitive contract, focus-ring composition, `tabular-nums`, canvas/surface separation, no `<style scoped>` grandfather clause) and applies them mechanically. The result: a receptionist landing on `/patients` reads the same product as a clinician landing on `/dashboard`. Real-time Echo channels (`patients` + cross-category `treatment-plans` / `quotations` / `medical-records` / `specialty-records`), the `Patient::scopeActive` query, the `PatientResource` API envelope (incl. the additive `age` integer key), the soft-delete + appointments-conflict 422, the per-patient audit log, the async/sync export flow, and the per-branch PHI exposure surface all stay byte-for-byte untouched — UI changes are template-level class-string replacement only.

**Why now:** the foundation tokens are settled, the PHPUnit invariants are wired, and the global proposal's chain has Patients isolated as PR5 (the largest CRUD module — right at the 400-line budget per global §7.7). The PACIENTES work splits cleanly into 5 sub-PRs (see §6) that stay inside the 400-line review budget and don't disturb the chain order. The user's stated intent — extend the proven language to every module — applies with extra weight to PACIENTES because the list + detail pages are the highest-touch demographic surface (every clinical module deep-links through them).

---

## 2. In-Scope

### 2.1 Pages / routes (2)

1. `/patients` — `resources/js/modules/patients/PatientsPage.vue` (1249 lines, 44.5 KB). List view: search bar, status filter (all/active/inactive), 4 stat cards (Total / Activos / Inactivos / Filtrados), desktop table, mobile card fallback, pagination, New Patient modal (inlined lines 463–581), Edit Patient modal (inlined lines 583–725). Pinned in `AppLayout.canvasRoutes` by global PR0.
2. `/patients/:id` — `resources/js/modules/patients/PatientDetailPage.vue` (1480 lines, 53.4 KB). Detail view: header (name, ID, email, phone, status pill), 5-tab drawer (Datos / Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría), per-tab data loaders + create buttons that deep-link to other modules with `?patient_id=…`, export-to-PDF/ZIP action, Edit Patient modal (inlined lines 706–845). Inherited canvas from PR0.

### 2.2 Inlined modals (3)

| # | File (lines) | Touch scope |
|---|---|---|
| 1 | New Patient modal in `PatientsPage.vue` (lines 463–581) | Medium. Inlined form: first/last name, DNI, email, phone, birth date, gender, address, emergency contact name+phone, medical history, allergies, notes. Replace custom `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop with `<UiModal>` chrome; `bg-theme-surface-elevated rounded-2xl shadow-2xl` panel → `<UiCard>`; `border-b border-theme` header divider → hairline; raw `<input>` / `<select>` / `<textarea>` → `<UiInput>` / `<UiSelect>` / `<UiTextarea>`. |
| 2 | Edit Patient modal in `PatientsPage.vue` (lines 583–725) | Medium. Mirrors the create form + status toggle (`is_active`). Same legacy patterns as the New Patient modal. |
| 3 | Edit Patient modal in `PatientDetailPage.vue` (lines 706–845) | Medium. Detail-page variant of the edit form; raw `<select>` for gender + `is_active` (lines 780, 792). Same legacy patterns; raw `<select>` violates the proven `<UiInput>` / `<UiSelect>` pattern. |

### 2.3 Cross-cutting primitives consumed (NOT tokenised here; PR0 owns them; PACIENTES consumes as-is)

| Primitive | Use |
|---|---|
| `resources/js/components/ui/Card.vue` (`<UiCard variant="glass">`) | Filters card, stat cards (replacing `hover-lift`), table card, list-item wrappers, edit-form panel |
| `resources/js/components/ui/Button.vue` (`<UiButton>`) | New / Edit / Delete / Ver / Cancelar / Crear Paciente / Exportar actions |
| `resources/js/components/ui/Input.vue` (`<UiInput>`) | Search input, demographics fields, address, medical history / allergies / notes textareas |
| `resources/js/components/ui/Select.vue` (`<UiSelect>`) | Status filter, gender dropdown, `is_active` toggle |
| `resources/js/components/ui/Badge.vue` (`<UiBadge>`) | Active/Inactive status pill on detail header + per-tab plan/quotation/record status pills |
| `resources/js/components/ui/EmptyState.vue` (`<EmptyState>`) | "No se encontraron pacientes" empty state on the list page |
| `resources/js/components/ui/LoadingSpinner.vue` (`<LoadingSpinner>`) | Initial load + per-tab reload spinners (replacing inline `animate-spin`) |
| `resources/js/components/ui/Modal.vue` (`<UiModal>`) | Modal chrome for the 3 inlined modals (replacing hand-built `bg-black bg-opacity-50` backdrop) |
| `resources/js/components/layout/PageHeader.vue` (`<PageHeader>`) | Title + breadcrumbs + Volver / Nuevo / Exportar actions on the detail page |
| `resources/js/components/ui/StatusBadge.vue` (`<UiStatusBadge>`) | Replaces `bg-success-badge / bg-danger-badge` legacy alias classes on patient status pills (PR0 primitive, inherited) |
| `resources/js/components/ui/Tabs.vue` (`<UiTabs>`) | 5-tab drawer on `PatientDetailPage` (replacing `border-accent text-accent` raw tab strip) |

### 2.4 Cross-cutting composables (touch points only — do NOT fork)

| Composable | Touch |
|---|---|
| `resources/js/composables/useApi.js` | **Unchanged.** Used for `get / post / put / delete` on `/api/patients` + `/api/patients/search`. UI changes do NOT touch the `<script>` block. The raw `fetch` + Bearer token for `/api/patients/${id}/export?format=…` download stays verbatim (the JSON wrapper would corrupt the binary stream). |
| `resources/js/composables/usePermissions.js` | **Unchanged.** `can.createPatient / updatePatient / deletePatient / createTreatmentPlan / createQuotation / createMedicalRecord / createSpecialtyRecord` consumed verbatim. |
| `resources/js/composables/useEcho.js` | **Unchanged.** Reverb subscriptions: `patients` channel `.patient.updated`; cross-category channels `treatment-plans` / `quotations` / `medical-records` / `specialty-records` listened for the per-tab deep-link create buttons. `.listen(...)` + `echo.leave(...)` stays verbatim. |
| `resources/js/composables/useConfirm.js` | **Unchanged.** Delete confirmation flow stays verbatim. |
| `resources/js/composables/useAuditLogs.js` | **Unchanged.** `getPatientAuditLogs(patientId)` consumed for the Historial de auditoría tab. |
| `resources/js/composables/useToast.js` | **Unchanged.** Success / error toasts on create / update / delete / export. |

### 2.5 Tests

| Test file | Action |
|---|---|
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Keep green. The array literal includes `/patients` (already pinned in PR0). |
| `tests/Feature/Api/PatientControllerAgeTest.php` | Keep green. Pins `data.age` integer in `PatientResource` envelope — API contract preserved verbatim. |
| `tests/Unit/Resources/PatientResourceAgeTest.php` | Keep green. 7 cases on `PatientResource::toArray()` — additive `age` key MUST NOT be widened or narrowed. |
| `tests/Unit/Controllers/PatientControllerResourceWireUpTest.php` | Keep green. Source-contract test asserts every public CRUD method references `PatientResource`. |
| `tests/Unit/Polish/ApiAndSeedersPolishTest.php` | Keep green. API-035 + API-057: `PatientController@export` MUST accept `pdf | zip` formats; MUST emit `application/pdf` / `application/zip` Content-Type. |
| `tests/Unit/DesignSystem/PatientsAppShellTest.php` | NEW. Extends `ModuleAppShellTestCase`; asserts `PatientsPage` + the 2 inlined modals reference the proven tokens, contain no legacy aliases (`border-theme`, `bg-success-badge`, `bg-danger-badge`, `text-accent hover:text-primary-700`, `bg-theme-surface-elevated` on the page surface, `divide-theme`, `bg-black bg-opacity-50`, `focus:ring-primary-500 focus:border-transparent`, raw `<select>`), and have zero `<style scoped>` blocks. |
| `tests/Unit/DesignSystem/PatientDetailAppShellTest.php` | NEW. Extends `ModuleAppShellTestCase`; asserts `PatientDetailPage` + the inlined Edit modal reference the proven tokens, contain no legacy aliases (same forbidden list as the list page), have zero `<style scoped>` blocks, and the 4 cross-category deep-links (`?patient_id=…`) are preserved verbatim. |
| `tests/Unit/DesignSystem/PatientStatusBadgeTest.php` | NEW. Asserts the patient status pill on the detail header uses `<UiBadge variant="success | error">` (NOT the legacy `bg-success-badge` / `bg-danger-badge` alias classes). Asserts the rule (variant token present, alias absent), not the literal string. |
| `tests/Unit/DesignSystem/PatientTableNumsTest.php` | NEW. Asserts the DNI column + age column on `PatientsPage` + the `document_number` / age cells on `PatientDetailPage` carry `tabular-nums` (font-feature-settings: var(--font-features-tabular-nums)). Asserts the rule, not the literal string. |
| `tests/Unit/DesignSystem/PatientModalChromeTest.php` | NEW. Asserts the 3 inlined modals (New + Edit in `PatientsPage` + Edit in `PatientDetailPage`) use `<UiModal>` chrome (NOT hand-built `bg-black bg-opacity-50` backdrop). Asserts the rule (`<UiModal>` wrapper present, alias absent), not the literal string. |

---

## 3. Out-of-Scope

The following look pacientes-related but are explicitly excluded. They may be raised in a follow-up change once this rollout lands.

1. **Cross-module `PatientSelector` primitive.** `resources/js/components/ui/PatientSelector.vue` (229 lines) is consumed by 6+ modules (PaymentModal, QuotationModal, TreatmentPlanModal, MedicalRecordModal, SpecialtyRecordModal, AiAnalysisPage). Tokenising it is cross-cutting; the pacientes PR does NOT include it. Rides the same PR5 cluster (or later) as a separate PR per global OQ#7.
2. **Pagination primitive duplication.** `PatientsPage.vue` still imports the legacy `<Pagination>` from `../../components/ui/Pagination.vue` (line 742, 752). The global rollout's PR3 cluster consolidates this duplicate onto `<UiPagination>`; the pacientes PR does NOT silently rename the import. Apply phase MUST keep `Pagination` as-is in this PR.
3. **PDF export template (`resources/views/exports/patient-file.blade.php`).** Print artifact consumed outside the SPA. The DOMPDF CSS palette (`#2563eb` blue + `#10b981` green / `#f59e0b` yellow / `#ef4444` red / `#f3f4f6` gray) is NOT in the visual-language scope. Flagged for a future print-design slice.
4. **Dormant `fillable` cleanup.** `Patient::$fillable` declares `dni`, `blood_type`, `insurance_provider`, `insurance_number` (lines 31–34 of `app/Models/Patient.php`) but no migration column matches them. Removing the dormant `fillable` is a separate cleanup, not visual polish.
5. **Audit log retention policy.** `PatientController::show` eager-loads the last 50 `auditLogs`. No retention policy enforced at the DB layer. Out of scope.
6. **`Patient::restore()` / `forceDelete()` flows.** Policy methods exist (`PatientPolicy::restore` + `forceDelete`, admin-only) but no `restore` REST route is exposed. Out of scope.
7. **PHI exposure / per-branch scoping.** `PatientPolicy::view` returns `true` for every authenticated role. No per-branch scoping on the `show` endpoint. The rollout is visual-only; the scope guard is a separate change. **The polish PR MUST NOT widen or narrow the API envelope.**
8. **`document_number` formatting.** The migration backfills `DOC-{8-digit zero-padded id}` (e.g., `DOC-00000042`). The PatientsPage renders `patient.id` as the secondary line ("ID: 42"), NOT `document_number`. Apply phase: keep the legacy "ID: $id" pattern; do not migrate to `DOC-XXX` rendering — that's a UX decision, not a visual token decision.
9. **Allergy / condition alert UX.** `allergies` + `medical_history` are free-text `TEXT` columns. The detail page renders them as a `<p>` block inside a `<UiCard>`. There is NO prominent "ALERT" / "WARNING" callout. The polish PR MUST NOT invent a new alert component; the global spec is strict about no new primitives except `<UiStatusBadge>`. The text-only display is the contract.
10. **Cross-category modules surfaced as tabs.** Treatment plan CRUD (`/treatment-plans`), quotation screens (`/quotations`), medical record content (`/medical-records`), specialty record content (`/specialty-records`), AI analysis (`/ai-analysis`) — all out of scope for the pacientes PR. The deep-link buttons in `PatientDetailPage.vue` MUST be preserved verbatim.
11. **Clinical attachment upload UI.** No frontend surface exists; the `ClinicalAttachment` model is only touched via `PatientExportService::exportToZip`. Out of scope.
12. **Document storage encryption.** `ClinicalAttachment.file_path` is NOT encrypted at rest on the `public` disk. Out of scope for the polish.
13. **Consent form versioning.** NOT in the schema; no `consent_forms` / `patient_consents` table. Out of scope.
14. **Family / guardian relationships.** NOT in the schema; only `emergency_contact_name` + `emergency_contact_phone`. No `family_relationships` table. Out of scope.
15. **Insurance data surfacing.** `insurance_provider` + `insurance_number` are dormant `fillable` entries; not collected, not displayed, not audited. The polish PR MUST NOT introduce fields that surface these dormant `fillable` entries.
16. **`useApi` Bearer-token download pattern.** `PatientDetailPage.vue` lines 1217–1225 use `window.URL.createObjectURL` + anchor click for the binary export download. The `<a download>` link is created, clicked, then removed. Polish: not in scope. Apply phase MUST NOT refactor this.
17. **Search performance on large patient lists.** `PatientController::index` does a 5-axis `LIKE %term%` over `first_name / last_name / email / phone / document_number`; the composite + unique indexes are NOT used by leading-wildcard LIKE. Visual page renders counts from a separate `clone $baseQuery->where(...)->count()` (lines 74–75) — correct, but the underlying search will degrade past ~10k patients. **Not a visual defect, not in scope.**
18. **Soft-delete vs appointments semantics.** `Patient::delete()` is a soft-delete but `PatientController::destroy` rejects with 422 if the patient has any appointments. Polish is unaffected.
19. **`document_number` migration backfill idempotency.** `2025_10_25_030052_add_document_number_to_patients_table.php` uses raw DDL via `DB::table()` to backfill `DOC-{8-digit zero-padded id}`. Polish is unaffected.
20. **`Patient::scopeActive` query scope.** Backend, not a UI surface.
21. **`ReportController@patients` BI consumer.** Cross-cutting; consumed by `BusinessIntelligencePage.vue`. Out of scope here (BI is its own category).
22. **`PatientExportService` synchronous export + `ExportPatientFileJob` async export.** Backend, triggered by the UI's Export action only.
23. **`PatientCreated / Updated / Deleted / FileExported` events + `LogPatientActivity` + `NotifyPatientFileExported` listeners.** Backend, consumed by `useEcho` + `useToast` only.
24. **`PatientPolicy` + `PatientResource`.** Backend; additive `age` key MUST be preserved (the unit + feature tests pin it).
25. **Two-tone numerals (D12 REVERSIBLE from vertical slice)** — stays rejected.
26. **Settings/branches + Settings/payment-methods.** Per global OQ#3 — OUT of scope for this rollout (mechanically identical to Profesionales / Ambientes; rides its own PR if user opts in).
27. **`MobileNavigation.vue` and `ThemeSelector.vue`** (dead code per the abandoned `ui-redesign-apple-claude-2026-08` audit). Removal is OUT of scope.

---

## 4. Approach

Reuse the proven language as-is; no new tokens, no new primitives (the full PR0 / Domain 2 set — `<UiCard>`, `<UiButton>`, `<UiInput>`, `<UiSelect>`, `<UiModal>`, `<UiTabs>`, `<UiBadge>`, `<UiStatusBadge>`, `<UiLoadingSpinner>`, `<UiEmptyState>` — is inherited from PAGOS / CITAS / vertical slice). Replace legacy alias classes one-by-one inside each pacientes `.vue` file using the global proposal §4.1 mapping table verbatim. Touch scope ordering: **list page first** (highest traffic, the receptionist's primary surface), then **detail page** (5-tab drawer + deep-link surface), then **3 inlined modals** (New Patient + Edit Patient in `PatientsPage` + Edit Patient in `PatientDetailPage`), then **cross-cutting tests** + tabular-nums audit. The `useEcho` channel subscriptions, `PatientResource` API envelope (incl. additive `age` integer), `PatientPolicy` role gating, soft-delete + appointments-conflict 422, per-patient audit log, async/sync export flow, and cross-category deep-link navigation are preserved verbatim — UI changes are template-only class-string replacement.

The PACIENTES rollout touches 2 pages + 3 inlined modals (no `resources/js/modules/patients/components/` directory exists; modals are inlined). Both pages carry a `<style scoped>` block (line 1315 of `PatientsPage.vue`, line 1556 of `PatientDetailPage.vue`) — both MUST be rewritten to plain utility classes per OQ#9 (no grandfather clause). Tabular columns (DNI, age) MUST consume `font-feature-settings: var(--font-features-tabular-nums)` so the ID column stops jittering. The status pills on the detail header (active/inactive) and on the table rows use the legacy `bg-success-badge / bg-danger-badge` alias classes — these MUST be replaced by `<UiBadge variant="success | error">` (or `<UiStatusBadge>` for the system-wide enum token). The 2 routes already receive the canvas surface via the global `canvasRoutes` extension (PR0, already landed). The PR0 `LegacyAliasForbiddenTest` pins the alias list (`border-theme`, `bg-success-badge`, `bg-danger-badge`, `text-accent hover:text-primary-700`, `focus:ring-primary-500 focus:border-transparent`, `bg-theme-surface-elevated` on the page surface, `divide-theme`, `bg-black bg-opacity-50`, `hover-lift`, etc.); `PatientsAppShellTest` + `PatientDetailAppShellTest` extend `ModuleAppShellTestCase` and assert the rule (token reference exists, alias absent), not a literal string (per the archive-report lesson). Visual verification per module: playwright-cli snapshot at 1440x900, plus 390x844 for `/patients` (receptionist mobile path with the mobile card fallback). Credentials: `recep@test.com` for list + detail; `admin@test.com` for delete + restore flows (admin-only).

Strict TDD discipline: every UI replacement MUST come with a test that proves the new behaviour (RED-GREEN per project policy). The visual sweep is documented verification, not a CI gate.

---

## 5. Capabilities (contract with sdd-spec)

The sdd-spec phase reads this section to know exactly which spec files to create or update. Research `openspec/specs/` first to use the existing capability names.

### New Capabilities (none)

The PACIENTES rollout does NOT introduce new capability specs. It exercises the global capability `premium-design-foundation` (persisted at `openspec/specs/premium-design-foundation/spec.md`) and the global delta spec `design-language-rollout` (at `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md`). The PACIENTES requirements live as additional rows in the global spec's module table.

### Modified Capabilities (delta rows added to existing global delta spec)

For the `design-language-rollout` delta spec (sibling file `specs/design-language-rollout/spec.md`), add PACIENTES-specific rows to the Module scenarios table:

- `DLR-MOD-003` — Pacientes (existing): inherited as-is from the global spec; PACIENTES clarifies that the 2 patients pages + 3 inlined modals are tokenised as one cluster, `PatientResource` API envelope (incl. additive `age` integer) stays verbatim, the `useEcho` channel subscriptions stay verbatim, the soft-delete + appointments-conflict 422 stays verbatim, the cross-category deep-link surface (`?patient_id=…`) is preserved, and the `<style scoped>` blocks in both pages are rewritten to plain utility classes.
- `DLR-PAC-001` — `PatientsPage` list polish: `border-theme` table dividers → hairline + `divide-[color:var(--color-hairline)]`; `bg-success-badge / bg-danger-badge` status pills → `<UiBadge variant="success | error">` (or `<UiStatusBadge>`); `text-accent hover:text-primary-700` link buttons → `<UiButton variant="link">`; raw `text-green-600 / text-red-600` mobile action buttons → `<UiButton variant="ghost">` with semantic token color; `hover-lift` stat cards → `<UiCard clickable>`; `bg-theme-surface-elevated / bg-theme-surface` mixed surfaces → `bg-theme-surface-elevated` only; `divide-theme` row dividers → hairline; tabular columns (DNI, age) → `font-feature-settings: var(--font-features-tabular-nums)`; `<style scoped>` block at line 1315 MUST be removed. (NEW row added by PACIENTES.)
- `DLR-PAC-002` — `PatientDetailPage` detail polish: header status pill → `<UiBadge variant="success | error">`; 5-tab drawer raw tab strip with `border-accent text-accent` active indicator → `<UiTabs>` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions; `border border-theme rounded-lg p-4` list items → `<UiCard>` wrappers; `border-l-2 border-theme` change-diff callout (line 669) → hairline; cross-category deep-links (`/treatment-plans?patient_id=…`, `/quotations?patient_id=…`, `/medical-records?patient_id=…`, `/specialty-records?patient_id=…`) preserved verbatim; `<style scoped>` block at line 1556 (`.tab-content { min-height: 400px }`) MUST be removed. (NEW row added by PACIENTES.)
- `DLR-PAC-003` — Modal chrome for 3 inlined modals: New Patient + Edit Patient in `PatientsPage` + Edit Patient in `PatientDetailPage` MUST use `<UiModal>` chrome (NOT hand-built `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop). Raw `<select>` gender / status dropdowns with `focus:ring-primary-500 focus:border-transparent` → `<UiSelect>` + hairline. `bg-theme-surface-elevated rounded-2xl shadow-2xl` panel → `<UiCard>`. `border-b border-theme` header divider → hairline. (NEW row added by PACIENTES.)
- `DLR-PAC-004` — `useEcho` channel isolation: `patients` channel `.patient.updated` subscription preserved verbatim; cross-category `treatment-plans` / `quotations` / `medical-records` / `specialty-records` channel subscriptions preserved verbatim (the per-tab deep-link create buttons consume the same events as the source modules). UI changes MUST NOT touch `<script>` blocks of `PatientsPage.vue` or `PatientDetailPage.vue`. (NEW row added by PACIENTES.)
- `DLR-PAC-005` — `PatientResource` envelope preservation: API envelope MUST NOT be widened or narrowed. Additive `age` integer key MUST stay (pinned by `PatientResourceAgeTest` + `PatientControllerAgeTest`). `email`, `phone`, `birth_date`, `address`, `medical_history`, `allergies`, `notes` continue to be exposed for every viewer (the PHI scope guard is a separate change). (NEW row added by PACIENTES.)
- `DLR-PAC-006` — Cross-category deep-link preservation: `router.push('/treatment-plans?patient_id=…')`, `router.push('/quotations?patient_id=…')`, `router.push('/medical-records?patient_id=…')`, `router.push('/specialty-records?patient_id=…')` MUST remain verbatim. Per-tab create buttons (Planes / Presupuestos / Historia Clínica / Especialidades) MUST keep their navigation contract byte-for-byte. (NEW row added by PACIENTES.)
- `DLR-PAC-007` — `PatientExportService` download pattern preservation: the raw `fetch` + Bearer token + `window.URL.createObjectURL` + anchor click pattern at `PatientDetailPage.vue` lines 1217–1225 MUST stay verbatim. The JSON wrapper would corrupt the binary stream; refactoring is out of scope. (NEW row added by PACIENTES.)
- `DLR-PAC-008` — `PatientModalChromeTest` + `PatientStatusBadgeTest` + `PatientTableNumsTest` rule-asserting tests (extends `ModuleAppShellTestCase`): all 4 assert the rule (token reference exists, alias absent, primitive wrapper present, `tabular-nums` on DNI/age columns), not a literal string. (NEW row added by PACIENTES.)

If sdd-spec chooses to extract PACIENTES into a sibling delta spec (`specs/pacientes-rollout/spec.md`), that is allowed — the global proposal does not forbid per-category specs. Recommendation: extend the global spec to keep traceability simple. Discuss with the orchestrator at spec phase.

---

## 6. Deliverables

Five PRs. Each fits inside the 400-line budget. Each is independently buildable, testable, and revertible.

### PR-pacientes-01 — `PatientsPage` list polish (highest-traffic demographic surface)

| Field | Value |
|---|---|
| Name | `pr-pacientes-01-patients-list` |
| Scope | `resources/js/modules/patients/PatientsPage.vue` (1249 lines, 44.5 KB). Search bar, status filter (all/active/inactive), 4 stat cards (Total / Activos / Inactivos / Filtrados), desktop table, mobile card fallback, pagination. Replace `border-theme` table dividers → hairline; `bg-success-badge / bg-danger-badge` status pills → `<UiBadge variant="success | error">` (or `<UiStatusBadge>`); `text-accent hover:text-primary-700` link buttons → `<UiButton variant="link">`; raw `text-green-600 / text-red-600` mobile action buttons → `<UiButton variant="ghost">` with semantic token color; `hover-lift` stat cards → `<UiCard clickable>`; `bg-theme-surface-elevated / bg-theme-surface` mixed surfaces → `bg-theme-surface-elevated` only; `divide-theme` row dividers → hairline; tabular columns (DNI, age) → `font-feature-settings: var(--font-features-tabular-nums)`; rewrite the `<style scoped>` block at line 1315 (`@media (max-width: 640px)` rule) to plain utility classes. The legacy `<Pagination>` import (line 742, 752) stays verbatim — the consolidation rides PR3. |
| Files | 1 page + new `PatientTableNumsTest` + extend `PatientsAppShellTest` |
| Risk | Medium-High (touches the highest-traffic demographic surface; 1249 lines is the largest single page in PACIENTES) |
| Dependencies | Global PR0 (already landed: `canvasRoutes`, `<UiStatusBadge>`, `<UiBadge>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`) |
| Line estimate | ~390 (right at the budget; split into 01a + 01b if reviewer flags) |
| Reversibility | `git revert <merge-sha>`; PatientsPage UI reverts to legacy look but `<script>` untouched (useEcho subscriptions preserved) |

### PR-pacientes-02 — `PatientsPage` inlined modals (New + Edit)

| Field | Value |
|---|---|
| Name | `pr-pacientes-02-patients-modals` |
| Scope | `resources/js/modules/patients/PatientsPage.vue` inlined modals (New Patient lines 463–581 + Edit Patient lines 583–725). Replace custom `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop with `<UiModal>` chrome; `bg-theme-surface-elevated rounded-2xl shadow-2xl` panel → `<UiCard>`; `border-b border-theme` header divider → hairline; raw `<input>` / `<select>` / `<textarea>` → `<UiInput>` / `<UiSelect>` / `<UiTextarea>` + hairline; focus ring → `var(--focus-ring-default)`. The `useApi` 422 error envelope rendering on duplicate email/phone stays verbatim (the form stays open + server error surfaces via `useToast`). |
| Files | 1 page (modal sections only) + new `PatientModalChromeTest` |
| Risk | Medium |
| Dependencies | PR-pacientes-01 |
| Line estimate | ~280 |
| Reversibility | Same as 01 |

### PR-pacientes-03 — `PatientDetailPage` header + 5-tab drawer + cross-category deep-links

| Field | Value |
|---|---|
| Name | `pr-pacientes-03-patient-detail` |
| Scope | `resources/js/modules/patients/PatientDetailPage.vue` (1480 lines, 53.4 KB). Header status pill → `<UiBadge variant="success | error">`; 5-tab drawer raw tab strip with `border-accent text-accent` active indicator (line 87) → `<UiTabs>` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions; `border border-theme rounded-lg p-4` list items → `<UiCard>` wrappers; `border-l-2 border-theme` change-diff callout (line 669) → hairline; gradient card icons → tokenised; rewrite the `<style scoped>` block at line 1556 (`.tab-content { min-height: 400px }` rule) to a plain utility class. **Cross-category deep-links (`/treatment-plans?patient_id=…`, `/quotations?patient_id=…`, `/medical-records?patient_id=…`, `/specialty-records?patient_id=…`) preserved verbatim.** `useEcho` `patients` channel `.patient.updated` subscription preserved verbatim. |
| Files | 1 page + new `PatientStatusBadgeTest` + extend `PatientDetailAppShellTest` |
| Risk | Medium-High (1480 lines; the detail page is the largest single Vue file in PACIENTES) |
| Dependencies | PR-pacientes-01 + PR-pacientes-02 (so the modals on the detail page inherit the chrome established in 02) |
| Line estimate | ~390 (right at the budget; split into 03a + 03b if reviewer flags) |
| Reversibility | Same as 01 |

### PR-pacientes-04 — `PatientDetailPage` Edit modal + Export action surface

| Field | Value |
|---|---|
| Name | `pr-pacientes-04-patient-edit-modal-and-export` |
| Scope | `resources/js/modules/patients/PatientDetailPage.vue` inlined Edit Patient modal (lines 706–845) + Export action surface. Same modal chrome as PR-pacientes-02: `<UiModal>` chrome replaces hand-built backdrop; raw `<select>` gender + `is_active` (lines 780, 792) → `<UiSelect>`; `useApi` form fields → `<UiInput>` / `<UiSelect>` + hairline. Export action surface: PDF / ZIP dropdown → `<UiSelect>`; the raw `fetch` + Bearer token + `window.URL.createObjectURL` + anchor click pattern at lines 1217–1225 stays verbatim (binary stream preservation is the contract). |
| Files | 1 page (modal + Export action sections only) + extend `PatientDetailAppShellTest` |
| Risk | Medium |
| Dependencies | PR-pacientes-03 |
| Line estimate | ~260 |
| Reversibility | Same as 01 |

### PR-pacientes-05 — Cross-cutting tests + tabular-nums audit + a11y follow-up flag

| Field | Value |
|---|---|
| Name | `pr-pacientes-05-cross-cutting-tests` |
| Scope | Add `tests/Unit/DesignSystem/PatientsAppShellTest.php` (extends `ModuleAppShellTestCase`) covering `PatientsPage` + the 2 inlined modals. Add `tests/Unit/DesignSystem/PatientDetailAppShellTest.php` covering `PatientDetailPage` + the inlined Edit modal + the 4 cross-category deep-links. Add `tests/Unit/DesignSystem/PatientStatusBadgeTest.php` asserting the patient status pill uses `<UiBadge>` (not legacy alias). Add `tests/Unit/DesignSystem/PatientTableNumsTest.php` asserting `tabular-nums` on DNI + age columns. Add `tests/Unit/DesignSystem/PatientModalChromeTest.php` asserting `<UiModal>` wrapper presence on the 3 inlined modals. Add `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/a11y-followup.md` documenting the allergy / medical-history alert callout as future work (out of scope for visual polish). |
| Files | 5 new test files + 1 a11y follow-up doc |
| Risk | Low (no UI change; test-only) |
| Dependencies | PR-pacientes-01..04 |
| Line estimate | ~200 |
| Reversibility | Same as 01 |

### Deliverable-to-PR mapping (verifies the global chain)

| Global PR | PACIENTES PRs that ride it |
|---|---|
| Global PR5 (`pr5-patients-tokenise`) | PR-pacientes-01 + 02 + 03 + 04 (all 4 tokenisation PRs) |
| (any) | PR-pacientes-05 can ride any of the four; default is to land after PR-pacientes-04 |

---

## 7. Affected Areas

| Area | Impact | Description |
|---|---|---|
| `resources/js/modules/patients/PatientsPage.vue` | Modified | List polish + 2 inlined modals + `<style scoped>` block removal |
| `resources/js/modules/patients/PatientDetailPage.vue` | Modified | Header + 5-tab drawer + 4 cross-category deep-links + Edit modal + Export action + `<style scoped>` block removal |
| `resources/js/components/ui/PatientSelector.vue` | Unchanged | Cross-cutting primitive; out of scope here |
| `resources/js/composables/useEcho.js` | Unchanged | `patients` + cross-category channel subscriptions preserved verbatim |
| `resources/js/composables/useApi.js` | Unchanged | All `get / post / put / delete` paths preserved verbatim |
| `resources/js/composables/usePermissions.js` | Unchanged | Role gating preserved verbatim |
| `resources/js/composables/useConfirm.js` | Unchanged | Delete confirmation flow preserved verbatim |
| `resources/js/composables/useAuditLogs.js` | Unchanged | Audit log loader preserved verbatim |
| `resources/js/composables/useToast.js` | Unchanged | Success / error toasts preserved verbatim |
| `app/Http/Controllers/Api/PatientController.php` | Unchanged | Out of scope; CRUD + export + search verbatim |
| `app/Http/Resources/PatientResource.php` | Unchanged | Additive `age` key MUST stay (pinned by tests) |
| `app/Services/PatientExportService.php` | Unchanged | Out of scope; sync PDF/ZIP export verbatim |
| `app/Services/Reports/PatientReportService.php` | Unchanged | Cross-cutting BI consumer; out of scope here |
| `app/Http/Requests/StorePatientRequest.php` / `UpdatePatientRequest.php` | Unchanged (do not exist as standalone request classes) | Validation is inlined in the controller |
| `app/Policies/PatientPolicy.php` | Unchanged | Role gating preserved verbatim |
| `app/Jobs/ExportPatientFileJob.php` | Unchanged | Async export; tries + backoff verbatim |
| `app/Events/PatientCreated.php` / `PatientUpdated.php` / `PatientDeleted.php` / `PatientFileExported.php` | Unchanged | Reverb broadcasts verbatim |
| `app/Listeners/LogPatientActivity.php` / `NotifyPatientFileExported.php` | Unchanged | Audit log writer + notification fan-out verbatim |
| `app/Models/Patient.php` | Unchanged | SoftDeletes + `$fillable` + `$appends.full_name` + `scopeActive` verbatim |
| `database/migrations/2025_09_20_082331_create_patients_table.php` + `2025_09_27_135908_add_unique_constraints_to_patients_table.php` + `2025_10_25_030052_add_document_number_to_patients_table.php` + `2026_06_11_001034_add_soft_deletes_to_patients_table.php` + `2026_08_11_120000_add_index_to_patients_created_at.php` | Unchanged | Schema + indexes + soft-deletes verbatim |
| `resources/views/exports/patient-file.blade.php` | Unchanged | Print artifact; out of scope |
| `tests/Unit/DesignSystem/PatientsAppShellTest.php` | New | Extends `ModuleAppShellTestCase` |
| `tests/Unit/DesignSystem/PatientDetailAppShellTest.php` | New | Extends `ModuleAppShellTestCase` |
| `tests/Unit/DesignSystem/PatientStatusBadgeTest.php` | New | Asserts `<UiBadge>` on status pill |
| `tests/Unit/DesignSystem/PatientTableNumsTest.php` | New | Asserts `tabular-nums` on DNI + age columns |
| `tests/Unit/DesignSystem/PatientModalChromeTest.php` | New | Asserts `<UiModal>` wrapper on 3 inlined modals |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Unchanged | Keep green (already includes `/patients`) |
| `tests/Feature/Api/PatientControllerAgeTest.php` | Unchanged | Keep green (pins `data.age` integer) |
| `tests/Unit/Resources/PatientResourceAgeTest.php` | Unchanged | Keep green (pins additive `age` key) |
| `tests/Unit/Controllers/PatientControllerResourceWireUpTest.php` | Unchanged | Keep green (pins `PatientResource` references) |
| `tests/Unit/Polish/ApiAndSeedersPolishTest.php` | Unchanged | Keep green (pins export Content-Type) |

---

## 8. Risks

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | `PatientDetailPage.vue` (1480 lines, 53.4 KB) is the largest single Vue file in PACIENTES; the chained PR-pacientes-03 (~390 lines) is right at the 400-line review budget. | Medium | Apply phase: PR-pacientes-03 is split into PR-pacientes-03a (header + 5-tab drawer + cross-category deep-links) + PR-pacientes-03b (per-tab content polish: Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría) IF the diff exceeds 400 lines. `chained-pr` skill rule applied: split BEFORE the review starts. |
| 2 | `useEcho` channel isolation: `patients` + cross-category `treatment-plans` / `quotations` / `medical-records` / `specialty-records` channel subscriptions must keep firing. Any `<script>` edit that accidentally removes `.listen(...)` or `echo.leave(...)` silently breaks realtime across the 5-tab drawer. | Medium | Apply phase scope rule: `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` are NEVER touched in any PR. Visual smoke test: open `/patients/:id` in two browser tabs, update the patient in tab A, verify tab B receives the `patient.updated` event within 1 second. The cross-category channel subscriptions are critical for the per-tab create buttons on the detail page. |
| 3 | `PatientResource` API envelope must stay verbatim — additive `age` integer key is pinned by `PatientResourceAgeTest` + `PatientControllerAgeTest`. A UI change that accidentally narrows or widens the envelope (e.g., removes `email`, `phone`, `address`, `medical_history`, `allergies`, `notes`) breaks every clinical consumer. | Low | Apply phase: UI changes do NOT touch `PatientResource.php` or `PatientController.php`. The `PatientControllerResourceWireUpTest` source-contract test asserts every public CRUD method references `PatientResource`; the resource wire-up is verified at every PR-pacientes-NN boundary. |
| 4 | Pagination primitive duplication: `PatientsPage.vue` still imports the legacy `<Pagination>` (line 742, 752). The global PR3 cluster consolidates this duplicate; the pacientes PR MUST NOT silently rename the import (would break the dependency graph). | Low | Apply phase: keep `<Pagination>` as-is in this PR. The consolidation is owned by global PR3 (Recepción procedimientos per the global §7.5). Per-PR grep for `PaginationComponent` import returns zero matches; if found, rewrite to `<UiPagination>` only after explicit confirmation that global PR3 has landed. |
| 5 | The per-patient PDF export uses a raw `fetch` + Bearer token + `window.URL.createObjectURL` + anchor click pattern at `PatientDetailPage.vue` lines 1217–1225. The JSON wrapper would corrupt the binary stream. A UI refactor that accidentally wraps the export call in `useApi()` breaks the download. | Low | Apply phase scope rule: the export action surface stays verbatim. `PatientModalChromeTest::test_export_download_pattern` (or similar) asserts the `window.URL.createObjectURL` + anchor click pattern is preserved byte-for-byte. `ApiAndSeedersPolishTest` API-035 + API-057 stays green (pins `application/pdf` / `application/zip` Content-Type). |
| 6 | Cross-category deep-links (`/treatment-plans?patient_id=…`, `/quotations?patient_id=…`, `/medical-records?patient_id=…`, `/specialty-records?patient_id=…`) MUST be preserved verbatim. A UI refactor that drops the `?patient_id=…` query param breaks the deep-link contract with the 4 other modules. | Low | Apply phase: deep-link navigation stays verbatim. `PatientDetailAppShellTest::test_cross_category_deep_links_preserved` asserts the 4 `router.push(...)` calls remain byte-for-byte. Visual smoke test: click the "Crear plan" button on the Planes tab, verify the URL contains `?patient_id=<id>` and the treatment-plans page loads. |
| 7 | Soft-delete + appointments-conflict 422: `PatientController::destroy` rejects with 422 if the patient has any appointments (including soft-deleted). The polish PR MUST NOT touch this contract. | Low | Apply phase: UI changes do NOT touch `PatientController.php`. The 422 error envelope rendering on the detail page's delete button (the catch block at `PatientDetailPage.vue` line 1152–1159) stays verbatim — the server error message + flattened error bag are surfaced via `useToast` correctly today. |
| 8 | `<style scoped>` block removal from both pages may expose CSS specificity issues if the inline `@media (max-width: 640px)` rule (PatientsPage line 1315) or `.tab-content { min-height: 400px }` rule (PatientDetailPage line 1556) was load-bearing for the responsive / tab-content layout. | Low | Apply phase: rewrite the contents to plain utility classes (`sm:hidden`, `min-h-[400px]`, etc.). `ModuleAppShellTestCase::test_no_style_scoped` asserts no `<style scoped>` block remains per file. Visual verification at 1440x900 + 390x844 confirms the responsive behavior + tab-content minimum height are preserved. |

---

## 9. Rollback Plan

- **Per-PR revert:** each PR-pacientes-NN is independently revertible via `git revert <merge-sha>` because the global `stacked-to-main` strategy keeps every commit reachable.
- **PR-pacientes-01 (List):** revert restores legacy `border-theme` table dividers, `bg-success-badge / bg-danger-badge` status pills, `hover-lift` stat cards, raw `text-green-600 / text-red-600` mobile action buttons, and the `<style scoped>` block at line 1315. `<script>` block untouched, so `useEcho` `patients` channel subscription + `useApi` calls are preserved. The list page's verified screenshot baseline at `.playwright-cli/screenshots-rollout/patients-list-1440x900.png` is the regression witness.
- **PR-pacientes-02 (List modals):** revert restores the custom `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` modal backdrop and raw `<input>` / `<select>` / `<textarea>` form fields. `<script>` block untouched, so the `useApi` POST to `/api/patients` + the 422 duplicate-email/phone error rendering are preserved.
- **PR-pacientes-03 (Detail header + tabs + deep-links):** revert restores the `border-accent text-accent` raw tab strip and the gradient card icons. `<script>` block untouched, so `useEcho` `patients` + cross-category channel subscriptions + the 4 cross-category `router.push(...)` deep-links are preserved. The detail page's verified screenshot baseline at `.playwright-cli/screenshots-rollout/patient-detail-1440x900.png` is the regression witness.
- **PR-pacientes-04 (Detail Edit modal + Export):** revert restores the custom backdrop + raw `<select>` gender / `is_active` dropdowns + the legacy export action chrome. The `window.URL.createObjectURL` + anchor click export pattern stays verbatim (out of scope anyway).
- **PR-pacientes-05 (Cross-cutting tests):** revert restores the pre-PR0 test count; the new `*AppShellTest` + `PatientStatusBadgeTest` + `PatientTableNumsTest` + `PatientModalChromeTest` files are deleted. No UI regression.
- **No destructive schema/data migrations.** All backend controllers / services / models / migrations are byte-for-byte unchanged. No destructive operation anywhere.

---

## 10. Success Criteria

The PACIENTES rollout is considered complete when ALL of the following hold:

- [ ] **All 2 pacientes routes (`/patients`, `/patients/:id`) render on Apple canvas without legacy `border-theme`, `bg-success-badge`, `bg-danger-badge`, `text-accent hover:text-primary-700`, `bg-theme-surface-elevated` on the page surface, `bg-theme-surface`, `divide-theme`, `bg-black bg-opacity-50`, `hover-lift`, `text-green-600` / `text-red-600` raw Tailwind, or raw `<select>` with `focus:ring-primary-500 focus:border-transparent` in the visible content area.** `AppLayoutCanvasRoutesTest` green; `PatientsAppShellTest` + `PatientDetailAppShellTest` each green.
- [ ] **3 inlined modals (New + Edit in `PatientsPage` + Edit in `PatientDetailPage`) use `<UiModal>` chrome.** Grep-verified: no `bg-black bg-opacity-50` strings in either page's modal sections. `PatientModalChromeTest` green.
- [ ] **Status pills on the list rows + detail header use `<UiBadge>` (or `<UiStatusBadge>`).** `PatientStatusBadgeTest` green; grep-verified: no `bg-success-badge` / `bg-danger-badge` alias classes anywhere in either page.
- [ ] **Tabular columns (DNI, age) carry `font-feature-settings: var(--font-features-tabular-nums)`.** `PatientTableNumsTest` green; grep-verified: `tabular-nums` utility class is present on the DNI + age cells in both pages.
- [ ] **Stat cards use `<UiCard clickable>` instead of `hover-lift` raw utility.** Grep-verified: no `hover-lift` class on the 4 stat cards in `PatientsPage.vue`.
- [ ] **5-tab drawer uses `<UiTabs>` instead of raw `<button>` step strip.** Grep-verified: no raw `<button class="border-accent text-accent">` active tab indicator; no inline `@click="currentStep = step.id"`-style handler.
- [ ] **Both `PatientsPage.vue` + `PatientDetailPage.vue` have zero `<style scoped>` blocks.** `ModuleAppShellTestCase::test_no_style_scoped` green per file. The contents of the 2 blocks (mobile media query + tab-content min-height) are migrated to plain utility classes.
- [ ] **`useEcho` channel subscriptions stay subscribed.** Manual smoke test: two browser tabs on `/patients/:id`, update the patient in tab A, verify tab B receives the `patient.updated` event within 1 second. The 4 cross-category channels (`treatment-plans`, `quotations`, `medical-records`, `specialty-records`) keep firing on the per-tab create buttons.
- [ ] **Cross-category deep-links preserved verbatim.** `PatientDetailAppShellTest::test_cross_category_deep_links_preserved` green; grep-verified: `router.push('/treatment-plans?patient_id=…')` / `router.push('/quotations?patient_id=…')` / `router.push('/medical-records?patient_id=…')` / `router.push('/specialty-records?patient_id=…')` patterns are present byte-for-byte.
- [ ] **`PatientResource` API envelope unchanged.** `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` stay green. The additive `age` integer key continues to be exposed; no field is added or removed.
- [ ] **`PatientExportService` download pattern preserved.** The raw `fetch` + Bearer token + `window.URL.createObjectURL` + anchor click pattern at `PatientDetailPage.vue` lines 1217–1225 is byte-for-byte unchanged. `ApiAndSeedersPolishTest` API-035 + API-057 stay green (pins `application/pdf` / `application/zip` Content-Type).
- [ ] **Soft-delete + appointments-conflict 422 contract preserved.** The detail page's delete button catch block (line 1152–1159) surfaces the server error message verbatim. `PatientController::destroy` is NOT touched.
- [ ] **`<Pagination>` import kept verbatim in `PatientsPage.vue`.** Grep-verified: `import Pagination from '../../components/ui/Pagination.vue'` remains as-is. The consolidation rides global PR3 (Recepción procedimientos).
- [ ] **No new primitives introduced.** The full `<UiCard>` / `<UiButton>` / `<UiInput>` / `<UiSelect>` / `<UiModal>` / `<UiTabs>` / `<UiBadge>` / `<UiStatusBadge>` / `<UiLoadingSpinner>` / `<UiEmptyState>` set is inherited from PAGOS / CITAS / vertical slice. The pacientes PR does NOT introduce any new primitive.
- [ ] **Playwright snapshots saved to `.playwright-cli/screenshots-rollout/{patients-list,patient-detail}-{1440x900,390x844}.png`** (mobile required for `/patients` because of the mobile card fallback).
- [ ] **All `tests/Unit/DesignSystem/*` PHPUnit invariants stay green** (`TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `UseSpringMathTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `ModuleAppShellTestCase`-derived tests).
- [ ] **CI green:** `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).
- [ ] **Test count delta ≥ +40** vs PR0 baseline (167 / 1158). Budget: +40 from the 5 new `*AppShellTest` / `*Test` files + per-PR RED-GREEN pairs. PR-pacientes-05 carries the bulk of the test additions.
- [ ] **A11y follow-up documented.** `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/a11y-followup.md` records the allergy / medical-history alert callout work as a future change (out of scope for visual polish).
- [ ] **Chain integrity:** every PR-pacientes-NN is independently buildable, testable, and revertible per `chained-pr` skill rules.

---

## 11. References

### 11.1 Source artifacts (read for this proposal)

| File | Why it matters |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (596 lines) | Global intent, scope, OQ resolutions, PR chain, success criteria |
| `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (496 lines) | Module inventory, per-module visual state, complexity tiers, PR chain ordering rationale |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/explore.md` (213 lines) | **PRIMARY INPUT.** Pacientes inventory, controllers/services/jobs/models inventory, test coverage surface, known gotchas |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` | Global MUST/SHOULD language; PACIENTES sub-PRs map onto global PR5 |
| `openspec/changes/archive/2026-08-12-ui-pagos/categories/pagos/proposal.md` | PAGOS category proposal — PACIENTES mirrors its structure, tone, length, deliverable granularity |
| `openspec/changes/archive/2026-08-12-ui-citas/categories/citas/proposal.md` | CITAS category proposal — PACIENTES mirrors its structure, tone, length, deliverable granularity |
| `openspec/specs/premium-design-foundation/spec.md` (404 lines) | The archived capability PACIENTES inherits (tokens, primitives, easing) |
| `openspec/config.yaml` | Preflight cache + strict TDD + pnpm-only + 400-line budget + CI MySQL |
| `AGENTS.md` §2, §4, §5, §6, §7 | Project context, stack, 17-module inventory, conventions, troubleshooting |
| `resources/js/design-system/tokens.js` | The proven token source-of-truth |
| `resources/css/tokens.generated.css` | Generated CSS (369 lines) |
| `resources/js/components/layout/AppLayout.vue` line 507 | `canvasRoutes` gate (global PR0) |
| `resources/js/modules/patients/PatientsPage.vue` (1249 lines, 44.5 KB) | PACIENTES PR-pacientes-01 + 02 primary file |
| `resources/js/modules/patients/PatientDetailPage.vue` (1480 lines, 53.4 KB) | PACIENTES PR-pacientes-03 + 04 primary file |
| `resources/js/components/ui/PatientSelector.vue` (229 lines) | Cross-cutting primitive; OUT of scope here |
| `resources/js/components/ui/{Card,Button,Input,Select,Badge,EmptyState,LoadingSpinner,Modal,Tabs,StatusBadge}.vue` | 10+ tokenised primitives; inherited by PACIENTES as-is |
| `resources/js/composables/{useApi,usePermissions,useEcho,useConfirm,useAuditLogs,useToast}.js` | 6 composables; preserved verbatim |
| `app/Http/Controllers/Api/PatientController.php` | `index` / `store` / `show` / `update` / `destroy` / `search` / `export` / `exportSync` — out of scope |
| `app/Http/Resources/PatientResource.php` | Wraps every patient response; additive `age` key MUST stay |
| `app/Services/PatientExportService.php` | `exportToPdf` / `exportToZip` — out of scope |
| `app/Policies/PatientPolicy.php` | `viewAny` / `view` / `create` / `update` / `delete` / `restore` / `forceDelete` / `export` — out of scope |
| `app/Models/Patient.php` | SoftDeletes + `$fillable` + `$appends.full_name` + `scopeActive` — out of scope |
| `app/Events/Patient{Created,Updated,Deleted,FileExported}.php` | Reverb broadcasts — out of scope |
| `app/Listeners/{LogPatientActivity,NotifyPatientFileExported}.php` | Audit log writer + notification fan-out — out of scope |
| `app/Jobs/ExportPatientFileJob.php` | Async export; tries + backoff — out of scope |
| `database/migrations/{2025_09_20_082331_create_patients_table,2025_09_27_135908_add_unique_constraints_to_patients_table,2025_10_25_030052_add_document_number_to_patients_table,2026_06_11_001034_add_soft_deletes_to_patients_table,2026_08_11_120000_add_index_to_patients_created_at}.php` | Schema + indexes + soft-deletes — out of scope |
| `resources/views/exports/patient-file.blade.php` | PDF template — out of scope (print artifact) |
| `tests/Feature/Api/PatientControllerAgeTest.php` | Pins `data.age` integer in API envelope |
| `tests/Unit/Resources/PatientResourceAgeTest.php` | Pins additive `age` key in resource |
| `tests/Unit/Controllers/PatientControllerResourceWireUpTest.php` | Source-contract test for `PatientResource` references |
| `tests/Unit/Polish/ApiAndSeedersPolishTest.php` | API-035 + API-057: export Content-Type whitelist |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Pin the `canvasRoutes` array literal (includes `/patients`) |
| `CREDENTIALS.md` | `recep@test.com` for list + detail; `admin@test.com` for delete + restore flows |

### 11.2 Standing guard rails (inherited from the global proposal)

This proposal does NOT relax any of:

1. `tokens.js` is the only source of truth for tokens.
2. `systemBackground` (`#ffffff`) is pinned; canvas = `#F2F2F7`.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings: var(--font-features-tabular-nums)`, NOT literal `tabular-nums` utility name.
7. `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` are NEVER edited in any PR.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only; NEVER npm/yarn.
10. Code in English; conversation in Spanish (Peru).

### 11.3 Process invariant (forwarded from the vertical-slice archive-report)

The archive-report at lines 47–57 names three defects that all shared one root cause: **a test that pins an example instead of the rule**. PACIENTES's standing posture is to assert rules, not literals:

- `PatientsAppShellTest`, `PatientDetailAppShellTest` extend `ModuleAppShellTestCase` — they assert the rule (`--color-canvas` reference exists, `border-theme` absent, `<style scoped>` absent, `<UiModal>` wrapper present), not a literal string.
- `LegacyAliasForbiddenTest` (global PR0) pins the list of forbidden patterns, not a single example.
- `PatientStatusBadgeTest` asserts the rule (`<UiBadge>` variant token present, legacy `bg-success-badge` / `bg-danger-badge` alias absent), not the literal output of one example.
- `PatientTableNumsTest` asserts the rule (`tabular-nums` utility class present on DNI + age columns), not the literal output of one example.
- `PatientModalChromeTest` asserts the rule (`<UiModal>` wrapper present on the 3 inlined modals, `bg-black bg-opacity-50` absent), not the literal output of one example.

---

## 12. What This Proposal Does NOT Do

- Does NOT redesign any pacientes surface — it ROLLOUTS the proven language.
- Does NOT add new tokens, primitives, or components (the full PR0 / Domain 2 set is inherited).
- Does NOT add dark mode.
- Does NOT add gradients anywhere.
- Does NOT touch the backend (no controller, no resource, no service, no listener, no migration, no job, no model).
- Does NOT relax any standing guard rail from §11.2.
- Does NOT introduce `<style scoped>` blocks (or carry them as grandfathered).
- Does NOT touch `<script>` blocks in either pacientes page — UI changes are template-level only.
- Does NOT change `useEcho` channel subscriptions (`patients` + cross-category `treatment-plans` / `quotations` / `medical-records` / `specialty-records`).
- Does NOT change `useApi` call signatures or Bearer-token download pattern.
- Does NOT widen or narrow the `PatientResource` API envelope (additive `age` integer key MUST stay).
- Does NOT alter the soft-delete + appointments-conflict 422 contract.
- Does NOT change the cross-category deep-link navigation (`?patient_id=…` preserved verbatim).
- Does NOT rename the legacy `<Pagination>` import (consolidation rides global PR3).
- Does NOT touch `PatientExportService` synchronous or async export flow.
- Does NOT restyle `resources/views/exports/patient-file.blade.php` (PDF template; print artifact).
- Does NOT remove dormant `fillable` entries (`dni`, `blood_type`, `insurance_provider`, `insurance_number`) — separate cleanup.
- Does NOT introduce an allergy / medical-history alert component — global spec forbids new primitives.
- Does NOT add encryption at rest for `ClinicalAttachment.file_path` on the `public` disk — separate change.
- Does NOT add `consent_forms` / `patient_consents` / `family_relationships` / `guardians` tables.
- Does NOT add `Patient::restore()` / `forceDelete()` REST routes.
- Does NOT introduce per-branch scoping on the `show` endpoint (PHI scope guard is a separate change).
- Does NOT migrate `document_number` to `DOC-XXX` rendering — UX decision, not visual token decision.
- Does NOT introduce `WorkSchedule` / `AppointmentBlock` admin frontend.
- Does NOT add `formatCurrency` consolidation (PAGOS owns PR-pagos-05; pacientes does NOT consume it).

---

*End of PACIENTES proposal.*
