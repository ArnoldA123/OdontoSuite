# Explore — pacientes (ui-rollout-all-modules-2026-08)

> SDD phase: `sdd-explore`. Pacientes sub-category of the rollout. Read-only; no proposal, no design, no tasks.
> Source artifacts: global `explore.md` §3.2 row 3 + `proposal.md` §2.1 row 3 + `routes/api.php` (patients group) + `app/Models/Patient.php` + `app/Http/Controllers/Api/PatientController.php` + `app/Http/Resources/PatientResource.php` + `app/Services/PatientExportService.php` + `app/Events/Patient{Created,Updated,Deleted,FileExported}.php` + `app/Jobs/ExportPatientFileJob.php` + `app/Policies/PatientPolicy.php` + `resources/js/modules/patients/*` + `resources/js/components/ui/PatientSelector.vue` + `resources/views/exports/patient-file.blade.php`.

---

## Scope

The "pacientes" category covers every interface in OdontoSuite that stores, displays, edits, or exports patient demographic / clinical / billing information: the patient list (search, filter, paginate), the patient detail page (5-tab drawer over Datos / Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría), the create / edit forms (demographics, emergency contact, medical history, allergies, notes), the soft-delete + restore flow, the per-patient audit log surface, the patient file export (PDF / ZIP, async + sync), the patient search endpoint used by the cross-module `PatientSelector` primitive, and the policy/auth surface (`viewAny / view / create / update / delete / restore / export`). IN scope: any screen where the primary object is an `App\Models\Patient` (including the related `auditLogs` MorphMany). OUT of scope: treatment plan editor (`treatment-plans` category), quotation screens (`pagos` category), appointment calendar + new-appointment modal (`citas` category), medical record content (`medical-records` category), specialty record content (`specialty-records` category), AI analysis (`ai-analysis` category), procedure catalog (`procedure-catalog` category), and the Odontogram / clinical attachment storage (those are surfaced as tabs on `PatientDetailPage` but their CRUD lives in their own modules).

## Inventory — Frontend (Vue)

PR0 already added `/patients` to `AppLayout.canvasRoutes` (the entire paciente surface inherits the canvas automatically; only one entry covers both list and detail). The two paciente Vue files still render legacy alias classes; both carry a `<style scoped>` block that must be rewritten per DLR-CORE-008.

| Route (URL) | Component file | Purpose | Apple-language status | Touch scope |
| --- | --- | --- | --- | --- |
| `/patients` | `resources/js/modules/patients/PatientsPage.vue` (1249 lines, 44.5 KB) | List view: search, status filter (all/active/inactive), 4 stat cards (Total / Activos / Inactivos / Filtrados), desktop table, mobile card fallback, pagination, New Patient modal, Edit Patient modal | `canvasRoutes` pinned; visual still legacy. `border-theme` table dividers, `bg-success-badge` / `bg-danger-badge` status pills, `text-accent hover:text-primary-700` link buttons, raw `text-green-600` / `text-red-600` mobile action buttons, `hover-lift` stat cards, `bg-theme-surface-elevated` / `bg-theme-surface` mixed surfaces, `divide-theme`, custom `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` modal backdrop, raw `<select>` gender/status dropdowns with `focus:ring-primary-500 focus:border-transparent`. Tabular columns (DNI, age) lack `tabular-nums`. `<style scoped>` block at line 1315 (single `@media (max-width: 640px)` rule) MUST be removed | large |
| `/patients/:id` | `resources/js/modules/patients/PatientDetailPage.vue` (1480 lines, 53.4 KB) | Detail view: 5-tab drawer (Datos / Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría), per-tab data loaders, per-tab create buttons that deep-link to other modules with `?patient_id=…`, export-to-PDF/ZIP action, edit modal that mirrors the create form | `canvasRoutes` pinned; visual still legacy. Same patterns as the list page (border-theme, hover-lift, gradient card icons, raw `border border-theme rounded-lg p-4` list items, custom modal markup at line 707–845). Also uses raw `<select>` with `focus:ring-primary-500 focus:border-transparent` in the edit modal (line 780, 792), `border-accent text-accent` active tab indicator (line 87), and `border-l-2 border-theme` for the change-diff callout (line 669). `<style scoped>` block at line 1556 (single `.tab-content { min-height: 400px }` rule) MUST be removed | large |

Modal / sub-component surface (all in the pacientes module — there is no `resources/js/modules/patients/components/` directory; modals are inlined):

| Component file | Purpose | Apple-language status | Touch scope |
| --- | --- | --- | --- |
| New Patient modal (lines 463–581 of `PatientsPage.vue`) | Inlined form: first/last name, DNI, email, phone, birth date, gender, address, emergency contact name+phone, medical history, allergies, notes | Untouched: custom `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop, `bg-theme-surface-elevated rounded-2xl shadow-2xl` panel, `border-b border-theme` header divider, raw form fields | large |
| Edit Patient modal in `PatientsPage.vue` (lines 583–725) | Mirrors the create form + status toggle | Same legacy patterns as the New Patient modal | large |
| Edit Patient modal in `PatientDetailPage.vue` (lines 706–845) | Detail-page variant of the edit form (raw `<select>` for gender + is_active) | Same legacy patterns; raw `<select>` violates the proven `<UiInput>` / `<UiSelect>` pattern | large |

Cross-cutting primitive consumed by the pacientes module and 8 other modules:

| Component file | Pacientes use |
| --- | --- |
| `resources/js/components/ui/PatientSelector.vue` (229 lines) | Cross-module patient picker (PaymentModal, QuotationModal, TreatmentPlanModal, MedicalRecordModal, SpecialtyRecordModal, AiAnalysisPage, etc.). Reused by 6 modules; surfaces `patient.age`, `patient.dni`, `patient.phone`. Legacy class strings: `border border-theme rounded-ios focus:ring-primary-500 focus:border-transparent`, `bg-primary-50 border-primary-200` selected state, raw `border-b border-theme hover:bg-theme-surface` list items, raw `border border-dashed border-theme rounded-ios` "create new" CTA, `bg-primary-50 border border-primary-200` selected-summary block. `<style scoped>` block at line 225 (single `.patient-selector { @apply space-y-3 }`) MUST be removed | medium |

Composables consumed by the pacientes module:

| Composable | Use |
| --- | --- |
| `resources/js/composables/useApi.js` | `get / post / put / delete` for `/api/patients` and `/api/patients/search`; `get /api/patients/${id}/export?format=…` for the file download (PatientDetailPage uses raw `fetch` + Bearer token for the download because the JSON wrapper would corrupt the binary stream — out of scope for tokenisation, do not touch) |
| `resources/js/composables/usePermissions.js` | `can.createPatient / updatePatient / deletePatient / createTreatmentPlan / createQuotation / createMedicalRecord / createSpecialtyRecord` |
| `resources/js/composables/useToast.js` | success / error toasts on create / update / delete / export |
| `resources/js/composables/useEcho.js` | Reverb subscriptions: `patients` channel `.patient.updated`, `treatment-plans` channel `.treatment-plan.{created,updated,deleted}`, `quotations` channel `.quotation.{created,updated,deleted}`, `medical-records` channel `.medical-record.{created,updated,deleted}`, `specialty-records` channel `.specialty-record.{created,updated,deleted}` |
| `resources/js/composables/useConfirm.js` | delete confirmation |
| `resources/js/composables/useAuditLogs.js` | `getPatientAuditLogs(patientId)` for the Historial de auditoría tab |

Reused primitives (already tokenised in PR2 of the vertical slice, inherited by pacientes as-is):

| Primitive | Use |
| --- | --- |
| `resources/js/components/layout/AppLayout.vue` (`<AppLayout>`) | Wrapper for both list + detail pages |
| `resources/js/components/layout/PageHeader.vue` (`<PageHeader>`) | Title + breadcrumbs + Volver / Nuevo / Exportar actions |
| `resources/js/components/ui/Card.vue` (`<UiCard variant="glass">`) | Filters card, stat cards, table card, list-item wrappers, edit-form panel |
| `resources/js/components/ui/Button.vue` (`<UiButton>`) | New / Edit / Delete / Ver / Cancelar / Crear Paciente actions |
| `resources/js/components/ui/Input.vue` (`<UiInput>`) | Search input, demographics fields, address, medical history / allergies / notes textareas |
| `resources/js/components/ui/Select.vue` (`<UiSelect>`) | Status filter, gender, status toggle |
| `resources/js/components/ui/Badge.vue` (`<UiBadge>`) | Active/Inactive status pill on detail header + per-tab plan/quotation/record status pills (uses proven variants `success | error | warning | primary | secondary`) |
| `resources/js/components/ui/EmptyState.vue` (`<EmptyState>`) | "No se encontraron pacientes" empty state on the list page |
| `resources/js/components/ui/LoadingSpinner.vue` (`<LoadingSpinner>`) | Initial load + per-tab reload spinners |
| `resources/js/components/ui/Pagination.vue` (`<Pagination>`) | Pagination control (the legacy name; the duplicate `<UiPagination>` is the canonical primitive per the global spec, but `PatientsPage.vue` still imports `<Pagination>`) |

> **Pagination primitive duplication note**: `PatientsPage.vue` imports `Pagination` from `../../components/ui/Pagination.vue` (line 742, 752). The global spec (OQ#7) commits to consolidating on `<UiPagination>` and removing the duplicate `<PaginationComponent>` (also referenced in `ReceptionProceduresPage.vue` per the global explore). The pacientes module will not introduce a fix for the duplication itself; the consolidation rides the third module that touches pagination in the chain (per the global proposal §7.5). Apply phase: do NOT silently rename the import here; that belongs to the PR3 cluster.

## Inventory — Backend

Controllers (all under `app/Http/Controllers/Api/`):

| File | Role |
| --- | --- |
| `PatientController.php` | `index` (paginated list with multi-axis search, branch_id filter, active/inactive filter, age counter meta envelope) + `store` (validate + create + emit `PatientCreated` + `ClearDashboardCache::handle()` + 201 with `PatientResource`) + `show` (eager-loads appointments / waitingLists / auditLogs / treatmentPlans / quotations relations) + `update` (validate + capture oldValues for audit + update + emit `PatientUpdated` + clear cache) + `destroy` (rejects with 422 if `appointments()->count() > 0`; otherwise soft-deletes via `Patient::delete()` and emits `PatientDeleted`) + `search` (autocomplete endpoint: name/email/phone/DNI like; returns `id, first_name, last_name, dni, document_number, email, phone, age`; 20-row cap; `is_active = true` only) + `export` (sync + async branch via `?async=1`; async dispatches `ExportPatientFileJob`; sync returns PDF or ZIP via `PatientExportService`) + `exportSync` (private; PDF via `barryvdh/laravel-dompdf` from `resources/views/exports/patient-file.blade.php`; ZIP via `ZipArchive` from `storage/app/temp/exports/` + per-attachment `Storage::disk('public')->path()` reads) |
| `ReportController.php` (cross-cutting) | `patients` report (BI module; consumes paginated patient list, status counters, branch_id filter) — out of scope for this category but read by `BusinessIntelligencePage.vue` |

Resources:

| File | Role |
| --- | --- |
| `app/Http/Resources/PatientResource.php` | Wraps every patient response; exposes the `age` integer (or null) derived from `birth_date` via `(int) $this->birth_date->diffInYears(now())`; exposes conditional counter fields (`appointments_count`, `treatment_plans_count`, `quotations_count`, `medical_records_count`) and conditional relations (`appointments`, `treatmentPlans`, `quotations`, `medicalRecords`) via `whenLoaded` / `when` |

Services:

| File | Role |
| --- | --- |
| `app/Services/PatientExportService.php` | `exportToPdf(int $patientId): string` (returns PDF binary via `barryvdh/laravel-dompdf`; reads `resources/views/exports/patient-file.blade.php`) + `exportToZip(int $patientId): string` (creates `storage/app/temp/exports/patient_export_<uniqid>/`, writes the PDF + all `ClinicalAttachment` rows where `is_active = true`, zips via `ZipArchive`, returns binary; cleans up temp dir on both success + failure) + private `loadPatientData(int $patientId): Patient` (eager-loads 10 relations: appointments / waitingLists / treatmentPlans / quotations / medicalRecords / endodonticsRecords / implantologyRecords / orthodonticsRecords / rehabilitationRecords / oralSurgeryRecords / odontograms / auditLogs) + private `deleteDirectory(string)` recursive cleanup |
| `app/Services/Reports/PatientReportService.php` | Cross-cutting; consumed by BI module — out of scope here |

Form requests / policies:

| File | Role |
| --- | --- |
| `app/Http/Requests/StorePatientRequest.php` / `UpdatePatientRequest.php` | NOT FOUND as standalone request classes — validation is inlined in `PatientController::store` and `PatientController::update` (lines 112–127, 197–221 of `PatientController.php`). If extracted later, out of scope for tokenisation. |
| `app/Policies/PatientPolicy.php` | `viewAny` (all roles) / `view` (all roles) / `create` (administrador + recepcionista only) / `update` (administrador + recepcionista + clinical roles — odontologo / implantologo / tecnico_dental / asistente) / `delete` (administrador only) / `restore` (administrador only) / `forceDelete` (administrador only) / `export` (administrador + recepcionista + odontologo + implantologo + tecnico_dental) |

Jobs / events / listeners:

| File | Role |
| --- | --- |
| `app/Jobs/ExportPatientFileJob.php` | Async export; `tries` default (3), `backoff` default ([60, 300, 900] per project convention); calls `PatientExportService::exportToPdf` or `exportToZip`; on success dispatches `PatientFileExported` (wrapped in try/catch so notification failure does not revert the job); `failed()` writes a log entry |
| `app/Events/PatientCreated.php` | Broadcasts on `patients` + `dashboard-updates` channels; event name `patient.created`; payload: full `Patient` model |
| `app/Events/PatientUpdated.php` | Broadcasts on `patients` + `dashboard-updates`; event name `patient.updated`; payload: `$patient->load('appointments', 'waitingLists')`; constructor also captures `$oldValues` for audit |
| `app/Events/PatientDeleted.php` | Broadcasts on `patients` + `dashboard-updates`; event name `patient.deleted`; payload: `patient_id` only (the model is gone) |
| `app/Events/PatientFileExported.php` | Broadcasts on `dashboard-updates` + per-user `user.{id}` private channel; event name `patient.file.exported`; payload: `patient_id`, `patient_name`, `format`, `file_path`, `user_id` |
| `app/Listeners/LogPatientActivity.php` | Audit log writer; handles `PatientCreated` / `PatientUpdated` / `PatientDeleted`; writes to `audit_logs` via `AuditLog::log($user, $action, $auditable, $oldValues, $newValues)`; `PatientDeleted` constructs a temporary `Patient` instance with just the `id` to satisfy the morph |
| `app/Listeners/NotifyPatientFileExported.php` | Notification fan-out on export: writes to `audit_logs` (`patient_file_exported` action), generates a `temporarySignedRoute('patient-export.download', +60min)` URL, sends raw email to `$user->email` (Spanish copy) via `Mail::raw`; failures logged but not rethrown |

Routes (per `routes/api.php` lines 129–131, under the pacientes role group):

- `GET /api/patients/search` → `PatientController@search` (autocomplete, returns max 20 active rows)
- `apiResource /api/patients` (index / show / store / update / destroy) → `PatientController`
- `GET /api/patients/{patient}/export` → `PatientController@export` (sync or async)
- `GET /api/reports/patients` (admin + finanzas + recep roles) → `ReportController@patients` (cross-cutting, consumed by BI)
- `GET /api/audit-logs/patient/{patientId}` (cross-cutting) → `AuditLogController@byPatient` (consumed by PatientDetailPage audit tab via `useAuditLogs` composable)
- `GET /api/quotations/patient/{patientId}` (cross-cutting) → `QuotationController@byPatient`
- `GET /api/specialty-records/patient/{patientId}/{specialty}` + `/specialty-records/patient/{patientId}/all` (cross-cutting) → `SpecialtyRecordController`
- `GET /api/ai-analysis/patient/{patientId}` (cross-cutting) → `AiImageAnalysisController@byPatient`

Frontend route (per `routes/web.php` line 23 + `resources/js/app.js` lines 60–70):

- `GET /patients` → `view('app')` (SPA fallback for Inertia-less mounting)
- `GET /patients/:id` → SPA-mounted `PatientDetailPage.vue`

## Database touchpoints

| File | Touch |
| --- | --- |
| `database/migrations/2025_09_20_082331_create_patients_table.php` | Base `patients` table: id, first_name, last_name, email, phone, birth_date, gender (enum male/female/other), address, emergency_contact_name, emergency_contact_phone, medical_history, allergies, notes, is_active, timestamps; indexes on `(first_name, last_name)`, `phone`, `email` |
| `database/migrations/2025_09_27_135908_add_unique_constraints_to_patients_table.php` | Adds `unique('email', 'patients_email_unique')` and `unique('phone', 'patients_phone_unique')` — surfaces as a `422 Unprocessable Entity` from `PatientController::store` when an email or phone is already in use |
| `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` | Adds `document_number VARCHAR(20) NULL` after `last_name`; idempotent guard for half-applied dev DBs; backfills `DOC-{8-digit zero-padded id}` via raw `DB::table()` (Eloquent would inject `deleted_at is null` against a column that does not exist yet at this point in the chain); re-applies the unique index via raw DDL (avoids pulling `doctrine/dbal`); unique key `patients_document_number_unique` |
| `database/migrations/2026_06_11_001034_add_soft_deletes_to_patients_table.php` | Adds `softDeletes` to `patients` (Sprint 3 / M-1, clinical no-delete posture). Subsequent `Patient::find($id)` calls exclude soft-deleted rows by default. The `Patient::withTrashed()` / `->restore()` paths are policy-gated (admin only). `PatientPolicy::restore` and `forceDelete` exist but no `restore` REST route is exposed. |
| `database/migrations/2026_08_11_120000_add_index_to_patients_created_at.php` | Adds `index('created_at')` for the Dashboard `data.comparisons.total_patients` month-bucketed aggregation (PR3 of the vertical slice) |

> The `patient-fillable` legacy includes `dni`, `blood_type`, `insurance_provider`, `insurance_number` (lines 31–34 of `app/Models/Patient.php`) but the corresponding columns are NOT in any migration. These are dormant `fillable` entries — any field the API actually accepts comes from the inline validation rules in `PatientController::store` / `update`, which do NOT include them. Out of scope: removing the dormant `fillable` entries is a separate cleanup, not visual polish.

Reporting surfaces that render the patient table:

- `ReportController@patients` (BI module) — out of scope here (BI is its own category)
- `DashboardController` (vertical-slice polished) — consumes `Patient::where('is_active', true)->count()` + `data.comparisons.total_patients` (month-over-month); already tokenised

## Test coverage surface

| File | Coverage |
| --- | --- |
| `tests/Feature/Api/PatientControllerAgeTest.php` | Feature: `index / show / store / update` all return `data.age` as a JSON integer (or null) — the bounded PR2 follow-up from `verify-report #337`. Pins `today = 2026-08-05 12:00:00 UTC`; seeds adult + null-birth-date patients; asserts `actingAs(admin, 'sanctum')` envelope (200/201 + pagination meta + 401 on unauthenticated). RefreshDatabase, MySQL via `odontosuite_test` |
| `tests/Unit/Resources/PatientResourceAgeTest.php` | Unit: 7 cases on `PatientResource::toArray()` (adult → 36, infant → 0, day-before-first-birthday → 0, first-birthday → 1, null birth_date → null, timezone-portable across UTC/America/Lima/Asia/Tokyo/Europe/Madrid, source-must-declare `age` key, source-must-compute via `$this->birth_date->diffInYears(now())`). Capsule Manager + `:memory:` SQLite (no migrations, no schema) |
| `tests/Unit/Controllers/PatientControllerResourceWireUpTest.php` | Source-contract (no DB, no app boot): every public CRUD method (`index / show / store / update`) MUST reference `PatientResource`; controller must reference `PatientResource` at least 4 times; mirrors the `AppointmentControllerInjectionStyleTest` recipe (parse method body via brace-walk, assert the resource class appears) |
| `tests/Unit/Polish/ApiAndSeedersPolishTest.php` | API-035 + API-057: `PatientController@export` MUST accept `pdf | zip` formats; MUST emit `application/pdf` for pdf branch and `application/zip` for zip branch (whitelist + Content-Type assertions) |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Pins `canvasRoutes` array literal; includes `/patients` at line 48 — must remain green at every pacientes PR boundary |

No dedicated unit test exists for:

- `PatientExportService::exportToPdf` / `exportToZip` (synchronous export)
- `ExportPatientFileJob` (async export)
- `PatientCreated / Updated / Deleted / FileExported` events
- `LogPatientActivity` / `NotifyPatientFileExported` listeners
- `PatientPolicy` (the policy is referenced via the API auth envelope tests but never directly asserted)
- `Patient::scopeActive` query scope
- The `PatientSearch` endpoint (`/api/patients/search`) — autocomplete contract has no test
- The `ReportController@patients` BI consumer

Coverage gap is NOTED but out of scope for the visual polish rollout; the proposed PR will not add tests beyond the `ModuleAppShellTestCase`-derived pacientes rule-asserting tests.

## Known gotchas

- **PHI exposure on the API envelope.** `PatientResource::toArray()` exposes `email`, `phone`, `birth_date`, `address`, `medical_history`, `allergies`, `notes` for every viewer. `PatientPolicy::view` returns `true` for every authenticated role. There is no per-branch scoping on the `show` endpoint — a `recepcionista` from branch B can read a branch-A patient's PHI. The rollout is visual-only; the scope guard is a separate change. **The polish PR MUST NOT widen or narrow the API envelope.**
- **Soft-delete semantics vs appointments.** `Patient::delete()` is a soft-delete (via `SoftDeletes` trait) but `PatientController::destroy` rejects with 422 if the patient has any appointments — including soft-deleted appointments. After a patient's appointments are purged (or all moved), the patient can be soft-deleted. A `restore` API route is NOT exposed. The audit tab shows `patient_deleted` events but no recovery surface — confirms the deletion is operationally sticky. Polish is unaffected.
- **`document_number` formatting.** The migration backfills `DOC-{8-digit zero-padded id}` (e.g., `DOC-00000042`). The PatientsPage renders `patient.id` as the secondary line ("ID: 42"), NOT `document_number`. The detail header shows the email + phone as secondary; the DNI is rendered as a separate `formatDate`-style field only in the create/edit form. Apply phase: keep the legacy "ID: $id" pattern; do not migrate to `DOC-XXX` rendering — that's a UX decision, not a visual token decision.
- **Unique constraints on email + phone.** `PatientController::store` validates `email: unique:patients,email` and `phone: nullable|string|max:20` (no `unique` validator on `phone` at the controller layer, but a `patients_phone_unique` index exists). On a race-condition double-submit, the API returns 422 with `errors.email[0] = "El email ya está registrado"`. The frontend's catch block (line 1152–1159 of `PatientDetailPage.vue`) renders the server error message + flattened error bag — correct UX, no polish needed. **However**: the `update` path allows the same email/phone on the current patient via `Rule::unique(...)->ignore($patient->id)`. The frontend's create form does not warn for `422` specifically; the toast surfaces the error but the form stays open. Out of scope for the polish (no UX change required, the backend contract is correct).
- **Search performance on large patient lists.** `PatientController::index` does a 5-axis `LIKE %term%` over `first_name / last_name / email / phone / document_number`; the `(first_name, last_name)` composite index and the `phone` / `email` unique indexes are NOT used by leading-wildcard LIKE. The global `created_at` index (added 2026-08-11) does not help this query. Polish: visual page renders counts from a separate `clone $baseQuery->where(...)->count()` (lines 74–75) — correct, but the underlying search will degrade past ~10k patients. **Not a visual defect, not in scope.**
- **Dormant `fillable` fields.** `Patient::$fillable` declares `dni`, `blood_type`, `insurance_provider`, `insurance_number` (lines 31–34) but no migration column matches them. `Patient::create($data)` will silently drop these. The frontend create form does NOT collect them either. Out of scope: removing the dormant `fillable` is a separate cleanup.
- **Audit log retention.** `PatientController::show` eager-loads the last 50 `auditLogs` ordered by `created_at desc`. The frontend audit tab displays a flattened list. No retention policy is enforced at the DB layer. The `LogPatientActivity` listener runs INSIDE the controller's transaction context; if the transaction rolls back, the audit row is NOT written. Out of scope.
- **Insurance data confidentiality.** `insurance_provider` + `insurance_number` are not collected, not displayed, and not audited. PHI is limited to demographics + clinical text. The polish PR must not introduce fields that surface these dormant `fillable` entries.
- **Family / guardian relationships.** NOT in the schema. `emergency_contact_name` + `emergency_contact_phone` are the only guardian surrogate fields. No `family_relationships` table exists, no `guardian` model exists. Out of scope.
- **Document storage encryption.** `ClinicalAttachment` is the attachment model; the paciente module only references it indirectly (the `exportToZip` walks `ClinicalAttachment::where('patient_id', $patient->id)->where('is_active', true)->get()` and copies files from `Storage::disk('public')` into the zip). Files on the `public` disk are NOT encrypted at rest. Out of scope for the polish, but **the apply phase must not echo the raw `file_path` to the DOM on the detail page** — currently the only mention of attachments is via the `Export` action which streams the binary, so no leakage risk exists today. The detail page's `clinicalAttachments` relationship is not loaded by `PatientController::show` (only treatmentPlans / quotations / medicalRecords are eager-loaded in the show response).
- **Consent form versioning.** NOT in the schema. There is no `consent_forms` / `patient_consents` table. The closest analog is the audit log (which records who read/updated the patient). No signature flow. Out of scope.
- **Allergy / condition alert UX.** `allergies` and `medical_history` are free-text `TEXT` columns (no structured list, no alert UX). The detail page renders them as a `<p>` block inside a `UiCard`. There is NO prominent "ALERT" / "WARNING" callout when an allergy is non-empty. The polish PR must not invent a new alert component; the global spec is strict about no new primitives except `<UiStatusBadge>`. The text-only display is the contract.
- **Cross-category references on the detail page.** The detail page's 5-tab drawer deep-links to 4 other modules via `router.push('/treatment-plans?patient_id=…')`, `router.push('/quotations?patient_id=…')`, `router.push('/medical-records?patient_id=…')`, `router.push('/specialty-records?patient_id=…')`. The cross-category modules are out of scope for the pacientes PR — but the deep-link surface in `PatientDetailPage.vue` (create / view / edit buttons per tab) MUST be preserved verbatim. Any tokenisation must keep the navigation contract identical.
- **The `$patient->full_name` accessor exists (line 174 of `Patient.php`) but the frontend never calls it — `PatientsPage.vue` and `PatientDetailPage.vue` template `{{ patient.first_name }} {{ patient.last_name }}` directly. Resource does set `full_name` (line 21 of `PatientResource.php`) but no consumer reads it. Out of scope.
- **Echo channel `patient.updated` payload loads `appointments` + `waitingLists`** (line 41 of `PatientUpdated.php`). The frontend `useEcho().channel('patients').listen('.patient.updated', …)` in `PatientDetailPage.vue` (lines 1349–1357) reads `e.patient.id` and replaces the entire `patient.value` ref. If a malformed payload arrives (e.g., a `patient.id === undefined` due to a serialization edge case), the guard `if (e.patient.id === patientId)` is correctly strict. Out of scope.
- **The Reverb channel `dashboard-updates`** also receives `patient.created / updated / deleted` events. The paciente frontend does NOT subscribe to `dashboard-updates` (no `useEcho().channel('dashboard-updates')` call in either file). The Dashboard page consumes it. Out of scope.
- **PDF export uses raw DOMPDF CSS** (`resources/views/exports/patient-file.blade.php` lines 7–129). The `#2563eb` blue + `#10b981` green / `#f59e0b` yellow / `#ef4444` red / `#f3f4f6` gray palette does NOT match the proven Apple design language. **The polish PR MUST NOT change the export PDF template** — it is a print artifact consumed outside the SPA and out of the visual-language scope. Flagged for a future print-design slice.
- **Per-patient file export via `window.URL.createObjectURL` + anchor click** (lines 1217–1225 of `PatientDetailPage.vue`) — the `<a download>` link is created in the DOM, clicked, then removed. This is the safe way to download a Bearer-token-authenticated binary stream. Polish: not in scope. Apply must not refactor this.
- **Pagination duplicate primitive.** `PatientsPage.vue` imports `Pagination` from `../../components/ui/Pagination.vue` (line 742). The global rollout's PR3 cluster consolidates this duplicate — the pacientes PR does NOT rename the import. Apply phase MUST keep `Pagination` as-is in this PR.

## Out-of-scope

- Treatment plan CRUD screens (`/treatment-plans`) even though `PatientDetailPage` has a "Planes" tab that deep-links into them
- Quotation / billing screens (`/quotations`, `/quotations/:id`) even though the detail page has a "Presupuestos" tab
- Medical record content (`/medical-records`, `/medical-records/:id`) even though the detail page has a "Historia Clínica" tab
- Specialty record content (`/specialty-records`) even though the detail page has a "Especialidades" tab
- Odontogram editing (`/medical-records/{id}/odontogram` or equivalent) — out of scope
- Clinical attachment upload UI (no frontend surface exists; the `ClinicalAttachment` model is only touched via `PatientExportService::exportToZip`)
- AI image analysis (`/ai-analysis`, `/ai-analysis/{id}`) even though `ReportController@patients` and the detail page's future "Análisis IA" tab are adjacent
- Procedure catalog / favorites — only the `my-procedures` and `reception-procedures` modules reference the catalog, not the paciente surface
- `DashboardController` card "Pacientes activos" + "Pacientes nuevos (mes)" — already polished in the vertical slice
- `Patient::scopeActive` query scope — backend, not a UI surface
- `ReportController@patients` BI consumer (the BI module renders it, not the pacientes module)
- `PatientExportService` synchronous export — backend, the frontend only triggers it
- `ExportPatientFileJob` async export — backend, triggered by `?async=1` flag on the controller
- `PatientCreated / Updated / Deleted / FileExported` events — backend, consumed by `useEcho`
- `LogPatientActivity` / `NotifyPatientFileExported` listeners — backend
- `PatientPolicy` — backend, not a UI surface
- `PatientResource` — backend, but the additive `age` key MUST be preserved (the unit + feature tests pin it)
- Removing the dormant `dni / blood_type / insurance_provider / insurance_number` from `Patient::$fillable` — separate cleanup
- Encrypting `ClinicalAttachment.file_path` on the `public` disk — separate change
- Adding a `consent_forms` / `patient_consents` table — out of scope
- Adding a `family_relationships` / `guardians` table — out of scope
- Adding an allergy / condition alert component — the global spec forbids new primitives; the free-text display is the contract
- Restyling `resources/views/exports/patient-file.blade.php` (PDF template) — print artifact, separate slice
- The per-patient audit log retention policy — out of scope
- The `Patient::restore()` flow (policy method exists, no API route) — out of scope
- The `Patient::forceDelete()` flow (admin-only) — out of scope
- Two-tone numerals (D12 REVERSIBLE from vertical slice) — stays rejected
- The cross-module `PatientSelector` primitive — touched by 6+ modules; its tokenisation rides the same PR5 (or later cluster) but is NOT exclusively pacientes
- The pagination primitive duplication consolidation — rides the PR3 (Recepción procedimientos) per the global spec, not the pacientes PR