# Task: patients module — behavior verification and defect fixes

Base: `main` @ `862f416`. Worktree `.worktrees/patients-audit`, branch
`fix/patients-audit`. Every change below lands on that branch; `main` is untouched.

## Specs

S1. "ahora continuando este proyecto quiero verificar el funcionameinto como tal y los bugs que
    encuentres y problemas" — verify the real behavior of the patients area first, then fix what is
    measurably broken. No fix without observed evidence.

S2. "en cuanto a los pacientes com tal todo ese apartado sea front inconsistencias o backend como
    tal" — the patients area: backend (controller, model, resource) and frontend (list, detail,
    form, patient picker).

S3. "has un wortre y ahi trabajalo" — all work happens in the dedicated worktree and branch.

S4. Existing contracts stay green: the 102 patient-related PHP tests that pass today on the MySQL
    runner must still pass, and the PACIENTES negative-space rules stay satisfied — PAC-PHI-001
    (no `dni`/`blood_type`/`insurance_provider`/`insurance_number` tokens in `PatientsPage.vue` or
    `PatientDetailPage.vue`), PAC-WS-001 (`PatientSelector.vue` keeps `border-theme` +
    `focus:ring-primary-500`), PAC-RT-001 (the 4 cross-category Echo channels), PAC-EXP-001 (the
    PDF export template is not touched).

## Measured findings

Probe: a temporary Feature test (run once against the real MySQL engine with `RefreshDatabase`,
then deleted; its evidence is reproduced below and every rule it measured is now pinned by
`tests/Feature/Api/PatientsModuleContractTest.php`).

| # | Finding | Evidence |
| --- | --- | --- |
| F1 | The API fabricates the patient id as the DNI. `Patient::getDocumentNumberAttribute()` returns `$this->id` when the column is null, so every consumer of `document_number` reads an id. | `store` without DNI → `data.document_number = 1` (the new id) while the column stores NULL. `show` → `document_number: 6`, type `integer`. `index` row → `6`. Audit `old_values`/`new_values` inherit the same fabricated value. |
| F2 | Searching the DNI the UI shows finds nothing: the query hits the column, not the accessor. | `search?q=<id>` → 0 rows while `index` renders that same id as the DNI. |
| F3 | `GET /api/patients?per_page=-5` is a 500. `per_page` is never validated; `min($perPage, 100)` keeps the negative value and the paginator throws. | `per_page=-5` → `{"status":500,"message":"Error interno del servidor."}`. `abc` → 100, `2.5` → 2, `0` → 15. |
| F4 | `patients.branch_id` is writable in the model but no endpoint validates or persists it, so the branch filter can never match. | `store` with `branch_id` → 201, response `branch_id: null`, stored NULL. `index?branch_id=1` → `meta.total: 0`. Every one of the 100 dev patients has `branch_id IS NULL`. |
| F5 | `delete` only guards appointments (and the message says "citas programadas" while the check is any appointment). A patient with medical records, quotations or cash transactions is soft-deleted, then every `belongsTo(Patient)` relation returns null and the cash register renders "Paciente no encontrado" for that history. | DELETE with a medical record → 200 + `onlyTrashed() === 1`; the record keeps its `patient_id` and `record->patient` is null. |
| F6 | `GET /api/patients/search` and `index` disagree: `search` ignores `branch_id`, and LIKE wildcards are not escaped in either. | `search?q=%25` → `meta.total: 2` (matches everything). |
| F7 | Birth dates render one day early in UTC-negative timezones: `new Date('1990-04-15')` parses as UTC midnight, then `toLocaleDateString('es-ES')` renders it in the local zone. | `PatientListRow.vue` + `PatientDetailPage.vue` both call `new Date(date).toLocaleDateString('es-ES')` on a `YYYY-MM-DD` string. |
| F8 | `PatientSelector.vue` reads `patient.dni`, but `/api/patients` returns `document_number`; the DNI badge never renders and DNI search only works after a server search. | Selector template `v-if="patient.dni"`, filter `patient.dni?.toLowerCase()`. `index` payload has no `dni` key. |
| F9 | The selector re-filters server results locally by name/DNI/phone only, so a server match on email disappears from the list. | `filteredPatients` computed runs after `searchPatients()` wrote the server result. |
| F10 | `PatientsPage.addPatientToList` calls `.toLowerCase()` on `document_number`; with F1 that value is an integer, so creating a patient while a search filter is active throws. | `(patientData.document_number \|\| '').toLowerCase()`. |
| F11 | The KPI cards never refresh after a create: `activeCount`/`inactiveCount` come from `meta` and are not incremented. | `addPatientToList` only bumps `paginationMeta.total`. |
| F12 | The detail page prints the raw enum for gender (`male`, `female`, `other`). | `{{ patient?.gender }}`. |
| F13 | `PatientFormModal` posts empty strings for every optional field and has no branch control, so `branch_id` can never be set from the UI. | `emptyForm()` values `''`; no branch field. |
| F14 | A duplicate `phone` answers 500, not 422: the DB has `patients_phone_unique` but the controller never validates phone uniqueness. | `POST /api/patients` with a taken phone → `SQLSTATE[23000] 1062 Duplicate entry ... for key 'patients_phone_unique'` → 500. |

## Decisions

- D1 (F1): remove the accessor fallback instead of rewriting every consumer. A null DNI is honest;
  the fabricated id was the only reason the value looked present. `dni` stays in the search payload
  as a backward-compatible alias because 7 modules read `dni` off the picker.
- D2 (F3): clamp `per_page` into 1..100 instead of returning 422, so a crafted or stale value
  degrades to the default instead of failing the page.
- D3 (F4): `branch_id` is optional and only written when the client sends it. No default branch is
  invented for the actor; an unassigned patient keeps `NULL` and appears under "all branches".
- D4 (F5): block deletion when the patient has clinical or financial history, and keep soft delete
  for patients without it. The message names the blocking records and points to deactivation.
- D5 (F7): add `formatDateOnly()` to `resources/js/utils/formatters.js` instead of changing the
  shared `formatDateShort()`; other modules keep their current behavior.

## Tasks

| ID | Task | Specs | Route | Commit |
| --- | --- | --- | --- | --- |
| T1 | Backend: honest `document_number` (F1/F2), `per_page` clamp (F3), LIKE escaping (F6), phone uniqueness (F14) | S1,S2 | inline | C1 |
| T2 | Backend: persist `branch_id` and expose it (F4) + destroy guard (F5) | S1,S2 | inline | C1 |
| T3 | Frontend: patient UI aligned with the API contract (F7-F13) | S1,S2,S4 | worker | C2 |
| T4 | Verify: patient PHP suite (MySQL) + patient Vitest smoke suite, plus the design-system guards | S4 | inline | — |
| T5 | Report the measured findings, the fixes and the remaining out-of-scope issues | S1,S2 | inline | — |

Commit slices:

| Commit | Contents | Authored lines |
| --- | --- | --- |
| C1 `ff6df1e` | backend behaviour + `PatientsModuleContractTest` (19 cases). One work unit: the contract and the test that pins it, so they cannot drift apart. Past the ~400-line guide because the test file alone is 412 lines and separating it would leave the behaviour unpinned. | 560 |
| C2 `9d96b88` | frontend behaviour + 2 Vitest files + this document | 412 |

## Checks

| Check | Command | Result |
| --- | --- | --- |
| Patient PHP suite (MySQL) | `DB_CONNECTION=mysql DB_DATABASE=odontosuite_test php artisan test --configuration=phpunit.mysql.xml --filter='Patient'` | 347 passed, 1250 assertions, 0 failures |
| Contract suite (MySQL) | same runner, `--filter=PatientsModuleContractTest` | 19 passed |
| Design-system + negative-space guards | `php artisan test --filter='DesignSystem'` | 662 passed, 3392 assertions |
| Patient Vitest smoke | `pnpm exec vitest run tests/js/smoke/patients` | 7 files / 43 tests passed |
| ESLint on the changed frontend | `pnpm exec eslint --ext .js,.vue resources/js/modules/patients resources/js/components/ui/PatientSelector.vue resources/js/utils/formatters.js` | 0 errors |
| Consumer modules (accessor blast radius: Billing, Appointment, Transaction, Quotation, TreatmentPlan, PendingPayment, MedicalRecord) | same MySQL runner, `--filter='Billing|Appointment|Transaction|Quotation|TreatmentPlan|PendingPayment|MedicalRecord'` | 262 passed, 842 assertions |
| Documentation + tooling guards | `php artisan test --filter='Documentation|AgentsDocs|Tooling'` | 88 passed, 239 assertions |
| Doc-drift guard | `node scripts/audit/doc-drift.mjs` | 12/12 checks hold (no route, model or migration count changed) |
| Native assessment | `gentle_review` `assess` over `862f416..9d96b88` | `nativeRisk: medium`, `reviewDue: false` (`under_budget`), plan: writer self-verification, no independent verifier |
| Timezone probe | `TZ=America/Lima node ...` over `formatDateOnly` | `1990-04-15` → `15/4/1990` in Lima, UTC and Tokyo; the legacy `formatDateShort` still returns `14/04/1990` in Lima (D5 keeps that helper untouched) |

## Log

L1 (user, verbatim): "ahora continuando este proyecto quiero verificar el funcionameinto como tal y
los bugs que encuentres y problemas, en cuanto a los pacientes com tal todo ese apartado sea front
inconsistencias o backend como tal, has un wortre y ahi trabajalo"

L2 (evidence): baseline on `main` with the patient filter (`--filter=Patient`, MySQL runner) →
102 passed / 483 assertions across 21 classes. After the fix the same filter on this branch →
347 passed / 1250 assertions across 41 classes, and `comm` over the two class lists is empty on the
`main`-only side: every class that passed before still passes. The extra 20 classes are design
system suites PHPUnit also matched on this branch (Citas, Caja, Environments, ...); all green.

L3 (decision): the MySQL runner needs a pit-stop between runs. `RefreshDatabase` calls
`migrate:fresh`, which does not recover a half-applied schema on this MariaDB instance; the reliable
protocol is drop + create `odontosuite_test` + `php artisan migrate`, and never two DB suites at the
same time.

L4 (decision): the worktree needs its own `vendor/`. A symlinked `vendor` keeps
`vendor/composer/autoload_psr4.php` resolving `App\` to the parent checkout, so tests would exercise
the parent's classes; the worktree therefore carries a real `vendor` plus `composer dump-autoload`.

L5 (out of scope, found while measuring): `PendingPaymentsList.vue` calls
`payment.patient.document_number.toLowerCase()`; the value is now `null` for a patient without a DNI
(it was the patient id before, which crashed the same way), so the cash-register search still needs
a `String(...)` guard in its own module. `MedicalRecordDetail.vue`, `SpecialtyRecordDetail.vue`,
`QuotationDetail.vue`, `TransactionModal.vue` and `TransactionList.vue` read `patient.dni`, which
those endpoints never send, so those DNIs stay invisible; `PatientSelector.vue` now emits the alias
for the payloads it owns. Both belong to their own modules and are reported, not changed here.
