# Delta for response-unwrap-canonical

## ADDED Requirements

### Requirement: one-level unwrap is the canonical response shape

Every consumer of `useApi().{get,post,put,patch,delete}` in `resources/js/composables/*.js` and in Vue pages under `resources/js/**/*.vue` that consumes a success-path envelope MUST treat the resolved value of `handleResponse` (`resources/js/composables/useApi.js:74`) as the flat envelope `{data: [...], meta: {...}}` returned by the backend. Concretely: collection consumers MUST drill exactly one level (`response.data` for the array, `response.meta` for pagination meta), and single-resource consumers MUST drill exactly one level (`response.data`).

The double-drill chain `response.data?.data`, `response.data.data`, and `response.data?.meta` is forbidden on the success path. Error-path access (`err.response.data.message`, `err.response.data.errors`) is unaffected and is NOT subject to this rule.

The canonical reference pattern is `resources/js/modules/environments/EnvironmentsPage.vue:359` (`response?.data || []`). Every composable and page in scope SHALL mirror that pattern.

The following composables are in scope and SHALL be patched: `useProcedureCatalog.js`, `useSpecialties.js`, `useAiAnalysis.js`, `useAuditLogs.js`, `useBranches.js`, `useMedicalRecords.js`, `useProcedureFavorites.js`, `useQuotations.js`, `useSpecialtyRecords.js`. The following pages are in scope and SHALL be patched: `resources/js/modules/business-intelligence/BusinessIntelligencePage.vue`, `resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue`, `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue`, `resources/js/modules/treatment-plans/components/CreatePatientInline.vue`.

#### Scenario: Catálogo de Procedimientos renders every BD row (Lens A empirical)

- **GIVEN** the live MySQL database contains 41 procedures in `procedure_catalog`
- **WHEN** the user loads `resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue` after a `pnpm build` + hard reload
- **THEN** the rendered list shows 41 rows
- **AND** `grep -nE "response\.data\s*\?\s*\.\s*data|response\.data\.data|response\.data\s*\?\s*\.\s*meta" resources/js/composables/useProcedureCatalog.js` returns zero matches

#### Scenario: Especialidades dropdown lists every BD row

- **GIVEN** the live MySQL database contains 9 specialties in `specialties`
- **WHEN** the user opens the New Procedure modal and inspects the Especialidades dropdown
- **THEN** the dropdown shows 9 entries (plus the default "— Sin especialidad —" option)
- **AND** `grep -nE "response\.data\s*\?\s*\.\s*data|response\.data\.data" resources/js/composables/useSpecialties.js` returns zero matches

#### Scenario: regex sweep across all nine in-scope composables

- **WHEN** `grep -nE "response\.data\s*\?\s*\.\s*data|response\.data\.data|response\.data\s*\?\s*\.\s*meta" resources/js/composables/useProcedureCatalog.js resources/js/composables/useSpecialties.js resources/js/composables/useAiAnalysis.js resources/js/composables/useAuditLogs.js resources/js/composables/useBranches.js resources/js/composables/useMedicalRecords.js resources/js/composables/useProcedureFavorites.js resources/js/composables/useQuotations.js resources/js/composables/useSpecialtyRecords.js` is executed after the patch
- **THEN** the command returns zero matches

#### Scenario: error-path access is preserved

- **WHEN** `grep -nE "err\.response\.data\.message|err\.response\.data\.errors|error\.response\.data\.message" resources/js/composables/useProcedureCatalog.js resources/js/composables/useMedicalRecords.js resources/js/composables/useQuotations.js` is executed after the patch
- **THEN** the command returns at least one match per composable (unchanged from pre-fix)
- **AND** the error-path string is NOT touched by this change

#### Scenario: working pages remain untouched

- **WHEN** `grep -nE "response\??\.data\b" resources/js/modules/environments/EnvironmentsPage.vue` is executed
- **THEN** the command still returns matches at lines ~359 and ~381 (one-level drill pattern, unchanged)

#### Scenario: action verbs that return a single object unwrap correctly

- **WHEN** `useProcedureCatalog.create(...)` is invoked and the backend returns `200 OK` with body `{"data": {"id": 42, "name": "Endodoncia"}}`
- **THEN** the composable resolves the create promise with `{id: 42, name: 'Endodoncia'}` (one level of drill)
- **AND** the call site stores `response.data` (not `response.data.data`) in `currentProcedure.value`

## ADDED Requirements

### Requirement: static guard test for the response-unwrap anti-pattern

The test suite MUST include `tests/Unit/SddCheckJsComposablesTest.php`. The test MUST scan every `*.js` file under `resources/js/composables/` and MUST fail when a file contains, after stripping JavaScript line comments (`//.*$`), block comments (`/\*.*?\*/`), single-quoted strings, and double-quoted strings (mirroring the `$stripPatterns` recipe at `tests/Unit/SddCheckMigrationsTest.php:247-252`), any of the following forbidden patterns:

- `response.data?.data`
- `response.data.data`
- `response.data?.meta`

The guard MUST also scan the four in-scope Vue pages (`BusinessIntelligencePage.vue`, `ProcedureCatalogDetailPage.vue`, `ProcedureStatsPage.vue`, `CreatePatientInline.vue`) for the same patterns. The failure message MUST identify the offending filename and the 1-indexed line number. Error-path occurrences (`err.response.data.message`, `error.response.data.errors`, `error.response.data.message`) MUST be excluded from the match set either by an anchor that requires `response.data` to be the prefix (not `err.response.data` / `error.response.data`) or by the strip pass.

#### Scenario: guard passes against the post-fix tree

- **WHEN** `vendor/bin/phpunit tests/Unit/SddCheckJsComposablesTest.php` is executed after the patch lands
- **THEN** the test exits 0
- **AND** the test reports zero violations

#### Scenario: guard fails when a forbidden pattern is reintroduced

- **WHEN** a developer reintroduces `procedures.value = response.data.data` at `resources/js/composables/useProcedureCatalog.js:37` (replacing the correct one-level drill)
- **THEN** `vendor/bin/phpunit tests/Unit/SddCheckJsComposablesTest.php` fails
- **AND** the failure message identifies `useProcedureCatalog.php` (filename) and line 37 and the matched pattern

#### Scenario: guard runs cleanly under SQLite in-memory phpunit.xml

- **WHEN** `vendor/bin/phpunit` is executed with the default `phpunit.xml` configuration (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- **THEN** `SddCheckJsComposablesTest.php` passes (pure string scan, no DB connection required)
- **AND** the existing `SddCheckMigrationsTest.php` (NEW-001 / NEW-002 / NEW-003 guards) continues to pass
- **AND** the live `odontosuite` MySQL database is NOT touched (no `migrate:fresh`, no destructive artisan command)

#### Scenario: error-path strings do not trigger the guard

- **WHEN** the guard scans `resources/js/composables/useMedicalRecords.js` after the patch
- **THEN** `err.response.data.message` and `error.response.data.errors` occurrences on the catch branches are NOT reported as violations
- **AND** only the success-path `response.data?.data` and `response.data.data` patterns are flagged when present

#### Scenario: working composables are not flagged

- **WHEN** the guard scans `resources/js/modules/environments/EnvironmentsPage.vue`
- **THEN** the file passes the guard
- **AND** `response?.data` (one-level, with optional chaining on `response` itself) is NOT a violation because the regex requires `response.data` as the prefix

#### Scenario: Vue pages scope is exact

- **WHEN** the guard scans `resources/js/modules/business-intelligence/BusinessIntelligencePage.vue`, `resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue`, `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue`, and `resources/js/modules/treatment-plans/components/CreatePatientInline.vue` after the patch
- **THEN** zero matches are reported for the forbidden patterns
- **AND** other Vue pages outside the four-file scope are not scanned (the guard is intentionally narrow, mirroring `SddCheckMigrationsTest` scope discipline)

## Evidence

- Defect repro (orchestrator, 2026-08-05): `GET /api/procedure-catalog` against the live DB returns a flat envelope `{data: [41 rows], meta: {...}}`; the Catálogo de Procedimientos page renders 0 rows because `useProcedureCatalog.js:37` reads `response.data?.data` (undefined fallback to `[]`).
- Defect repro (orchestrator, 2026-08-05): `GET /api/specialties` returns a flat envelope `{data: [9 rows]}`; the Especialidades dropdown shows only "— Sin especialidad —" because `useSpecialties.js:16` reads `response.data?.data` (undefined fallback to `[]`).
- Working canonical pattern: `resources/js/modules/environments/EnvironmentsPage.vue:359` — `environments.value = response?.data || []`. This is the target shape every in-scope composable/page must mirror.
- Anti-pattern locations (pre-fix): see the grep output above — 9 composables, 4 pages, 51 offending lines total.
- Test mirror: `tests/Unit/SddCheckMigrationsTest.php` lines 247-252 (`$stripPatterns`), lines 261-269 (line-by-line walk for file:line reporting), and lines 452-456 (anchored guard pattern that scopes to the current table/column). `SddCheckJsComposablesTest.php` reuses all three recipes and adapts them to JavaScript comment/string stripping.
