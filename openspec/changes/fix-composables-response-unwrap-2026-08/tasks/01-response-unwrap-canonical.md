# Slice 01 — Response Unwrap Canonical (NEW-005)

> Finding: NEW-005 — `useApi.handleResponse` at `resources/js/composables/useApi.js:74`
> returns the flat JSON envelope `{data: [...], meta: {...}}` without unwrapping.
> Nine composables then over-drill one level (`response.data?.data` /
> `response.data.data` / `response.data?.meta`). Because the backend never wraps
> in a second envelope, the second `.data` is always `undefined`. Verified
> empirical impact: `GET /api/procedure-catalog` returns 41 rows but the
> Catálogo de Procedimientos page renders 0; `GET /api/specialties` returns 9
> rows but the New Procedure modal's Especialidades dropdown shows only the
> default "— Sin especialidad —" option.
>
> Cluster: response-unwrap-canonical
> LOC est: ~50 · Budget risk: Low · Depends on: —
> Spec: [../specs/01-response-unwrap-canonical.md](../specs/01-response-unwrap-canonical.md)
> Design: [../design.md](../design.md) §Decision 1–5

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size:exception
400-line budget risk: Low

## Acceptance Criteria (slice-level)

- [x] `grep -nE "response\.data\s*\?\s*\.\s*data|response\.data\.data|response\.data\s*\?\s*\.\s*meta" resources/js/composables/*.js resources/js/modules/business-intelligence/BusinessIntelligencePage.vue resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue resources/js/modules/procedure-catalog/ProcedureStatsPage.vue resources/js/modules/treatment-plans/components/CreatePatientInline.vue resources/js/components/procedures/ImportCsvModal.vue` returns zero matches.
- [x] `grep -nE "err\.response\.data\.message|err\.response\.data\.errors|error\.response\.data\.message" resources/js/composables/useProcedureCatalog.js resources/js/composables/useMedicalRecords.js resources/js/composables/useQuotations.js` returns ≥1 match per file (error path preserved).
- [x] `grep -nE "response\??\.data\b" resources/js/modules/environments/EnvironmentsPage.vue` still returns matches at lines ~359 and ~381 (canonical pattern preserved).
- [x] `vendor/bin/phpunit tests/Unit/SddCheckJsComposablesTest.php` passes (GREEN).
- [x] `vendor/bin/phpunit` overall does not regress (mirrors the SQLite in-memory `phpunit.xml` pinning).
- [x] Manual smoke: agent-browser reload of `procedure-catalog` index page renders 41 rows; Especialidades dropdown in the New Procedure modal shows 9 entries.
- [x] `pnpm lint:check && pnpm build` exits 0 (CI `frontend-build` job per AGENTS.md §10).

---

## Phase 1 — Composables Patch (Commit A, GREEN)

### T-01.1 — One-level unwrap across 9 composables

**Files**: 9 files under `resources/js/composables/`
**Action**: Modify (9 files, 1 logical unit)
**LOC**: ~30 added/removed

Replace every `response.data.data` / `response.data?.data` read with `response.data`; every `response.data.meta` / `response.data?.meta` read with `response.meta`. Mirror the canonical pattern at `resources/js/modules/environments/EnvironmentsPage.vue:359` (`response?.data || []`).

Concrete edits per file (verbatim line ranges from design §File Changes):

1. `resources/js/composables/useProcedureCatalog.js` — lines 37–38, 54–55, 69, 85 → unwrap data + meta.
2. `resources/js/composables/useSpecialties.js` — line 16 → unwrap.
3. `resources/js/composables/useAiAnalysis.js` — line 201 → unwrap.
4. `resources/js/composables/useAuditLogs.js` — lines 19, 69, 86, 103 → simplify fallback (drop dead `response.data?.data \|\| response.data` → `response.data`).
5. `resources/js/composables/useBranches.js` — lines 50–52 dead `else if` branch removed; retain first `if (Array.isArray(response.data))` (matches `EnvironmentsPage` pattern).
6. `resources/js/composables/useMedicalRecords.js` — 13 occurrences (lines 27, 29, 44, 46, 61, 81, 133, 161, 163, 189, 210, 225, 227) → unwrap.
7. `resources/js/composables/useProcedureFavorites.js` — lines 26, 49–50, 66, 107 → unwrap.
8. `resources/js/composables/useQuotations.js` — lines 38–39, 61, 63, 78, 98, 150, 178, 235, 237 → unwrap.
9. `resources/js/composables/useSpecialtyRecords.js` — lines 25, 27, 42, 44, 59, 79, 133, 135, 151 → unwrap.

Acceptance criteria:
- [x] `grep -nE "response\.data\s*\?\s*\.\s*data|response\.data\.data|response\.data\s*\?\s*\.\s*meta" resources/js/composables/useProcedureCatalog.js resources/js/composables/useSpecialties.js resources/js/composables/useAiAnalysis.js resources/js/composables/useAuditLogs.js resources/js/composables/useBranches.js resources/js/composables/useMedicalRecords.js resources/js/composables/useProcedureFavorites.js resources/js/composables/useQuotations.js resources/js/composables/useSpecialtyRecords.js` returns zero matches.
- [x] Every `catch` clause that reads `err.response.data.message` or `err.response.data.errors` is untouched (run the error-path grep below to confirm).
- [x] `git diff --stat resources/js/composables/` shows exactly 9 files changed.
- [x] No `import`, no top-level statement, no comment, no doc-block is modified outside the line ranges above.

---

## Phase 2 — Vue Pages Patch (Commit A, GREEN, same commit)

### T-01.2 — One-level unwrap across 5 Vue pages

**Files**: 5 Vue pages
**Action**: Modify (5 files, 1 logical unit)
**LOC**: ~6 added/removed

1. `resources/js/modules/business-intelligence/BusinessIntelligencePage.vue` — line 495 → `response.data`; line 496 → `response.columns`.
2. `resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue` — line 208 → unwrap.
3. `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` — line 157 → unwrap.
4. `resources/js/modules/treatment-plans/components/CreatePatientInline.vue` — line 143 → `response.data`.
5. `resources/js/components/procedures/ImportCsvModal.vue` — line 110 → unwrap (5th page, missed in original proposal but identified by the design's grep sweep).

Acceptance criteria:
- [x] `grep -nE "response\.data\s*\?\s*\.\s*data|response\.data\.data|response\.data\s*\?\s*\.\s*meta" resources/js/modules/business-intelligence/BusinessIntelligencePage.vue resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue resources/js/modules/procedure-catalog/ProcedureStatsPage.vue resources/js/modules/treatment-plans/components/CreatePatientInline.vue resources/js/components/procedures/ImportCsvModal.vue` returns zero matches.
- [x] `git diff --stat resources/` shows exactly 5 `.vue` files in `modules/` + `components/` changed.
- [x] No `<template>`, `<script>`, `<style>`, or `<script setup>` block outside the line ranges is touched.

---

## Phase 3 — Static Guard Test (Commit A, RED→GREEN proof is T-01.4)

### T-01.3 — Add `SddCheckJsComposablesTest::no_composable_double_unwraps_response`

**File**: `tests/Unit/SddCheckJsComposablesTest.php` (NEW)
**Action**: Create
**LOC**: ~40 added

Concrete behavior (verbatim from design §Interfaces/Contracts):

1. Class extends `PHPUnit\Framework\TestCase` (NOT Laravel's `TestCase`) — pure string scan, no DB connection, runs cleanly on SQLite in-memory with `phpunit.xml`'s `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` pinning.
2. `$stripPatterns` array (line-by-line strip before regex match, mirroring `SddCheckMigrationsTest` lines 247–252):
   ```php
   $stripPatterns = [
       '/\/\/.*$/m',
       '/\/\*.*?\*\//s',
       "/'(?:\\\\.|[^'\\\\])*'/s",
       '/"(?:\\\\.|[^"\\\\])*\"/s',
   ];
   ```
3. Forbidden regex anchored on literal `response.` prefix so constructed error objects (`{ response: { data: { message: ... } } }`) never match:
   ```php
   $forbidden = '/\bresponse\.data\??\.(?:data|meta)\b/';
   ```
4. Scan paths (mirrors design §Decision 3 + §Interfaces/Contracts):
   ```php
   $paths = array_merge(
       glob(__DIR__ . '/../../resources/js/composables/*.js') ?: [],
       glob(__DIR__ . '/../../resources/js/modules/**/*.{vue,js}') ?: [],
       glob(__DIR__ . '/../../resources/js/components/**/*.{vue,js}') ?: [],
   );
   ```
5. Per-file, per-line walk: append `<<filename>:<line> double-unwraps response` to `$violations` on a match.
6. Final assertion: `assertSame([], $violations, "No composable may drill \`response.data.data\` / \`response.data.meta\` — useApi returns a flat envelope (NEW-005). Offenders:\n" . implode("\n", $violations));`
7. Class docblock (top of file) explains scope + the canonical reference (`EnvironmentsPage.vue:359`) so future devs understand why the regex exists.

Acceptance criteria:
- [x] `vendor/bin/phpunit tests/Unit/SddCheckJsComposablesTest.php` passes against the post-T-01.1 + T-01.2 tree (GREEN proof).
- [x] Test runs cleanly on SQLite in-memory (no `MySQL connection refused` errors); the only file I/O is `file_get_contents` + `glob` against `resources/js/`.
- [x] `vendor/bin/phpunit` overall does not regress.
- [x] No composable outside the 9 in-scope files is touched by the patch (guard scope is filesystem-wide, design decision; the guard catches regressions even outside the original 9 if the pattern resurfaces).

---

## Phase 4 — Reintroduction Proof + Manual Smoke (Verification)

### T-01.4 — Demonstrate the guard catches the pre-fix state AND verify UI

**File**: N/A (verification only — no persistent code change)
**Action**: Verify

Part A — Reintroduction proof (mirror design Decision 5 + design §Testing Strategy row 3):

1. Temporarily revert `resources/js/composables/useProcedureCatalog.js:37` from `procedures.value = response.data` (the post-fix one-level drill) back to `procedures.value = response.data.data` (the pre-fix anti-pattern).
2. Run `vendor/bin/phpunit tests/Unit/SddCheckJsComposablesTest.php --filter=no_composable_double_unwraps_response` — expect a failure.
3. Confirm the failure message contains `useProcedureCatalog.js`, a 1-indexed line number near 37, and the substring `response.data.data` (or the forbidden pattern). Record the exact text in the apply report.
4. Restore `procedures.value = response.data`.
5. Re-run the same filter — expect GREEN.
6. Confirm `git diff resources/js/composables/useProcedureCatalog.js` is empty post-restore (no leftover diff).

Part B — Manual smoke against the live UI:

1. Check whether `pnpm dev` is running. If yes, HMR handles the change automatically — proceed to step 2. If no, run `pnpm dev` or `pnpm build && pnpm dev --force` before proceeding.
2. Via agent-browser, load `http://localhost:5173/procedure-catalog` (or the project's URL per AGENTS.md §3) — expect 41 rows in the procedure list. Record a screenshot.
3. Open the New Procedure modal — expect the Especialidades dropdown to show 9 specialty entries plus the default "— Sin especialidad —" option. Record a screenshot.
4. Spot-check the remaining pages touched by T-01.1 + T-01.2 (Medical Records, Quotations, Audit Logs, Branches, Specialty Records, Procedure Favorites, AI Analysis, Procedure Catalog Detail, Procedure Stats, Business Intelligence, Create Patient Inline, Import CSV Modal) — each must render without console errors.

Acceptance criteria:
- [x] Part A step 2 produces a failure message that names `useProcedureCatalog.js` AND `response.data.data` (or the forbidden pattern) AND a 1-indexed line number.
- [x] Part A step 5 produces GREEN.
- [x] Part A step 6 confirms no leftover diff on `useProcedureCatalog.js`.
- [x] Part B step 2 confirms 41 rows in the Catálogo de Procedimientos page (Lens A empirical repro inverted).
- [x] Part B step 3 confirms 9 specialty entries in the New Procedure modal dropdown (Lens A empirical repro inverted).
- [x] Part B step 4 confirms no console errors on any of the touched pages.
- [x] `vendor/bin/phpunit tests/Unit/SddCheckJsComposablesTest.php` final run is GREEN.
- [x] `pnpm lint:check && pnpm build` exits 0 (CI parity).

---

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| Static-guard string-stripping regresses on edge cases (e.g. pattern inside a backtick template literal or template-string interpolation) | The `$stripPatterns` set covers single + double quotes only; template literals (`` ` ``) are NOT stripped. Mitigate by extending `$stripPatterns` with `/\`(?:\\\\.\|[^\\\\\`])*\`/s` if T-01.4 Part A surfaces a leak. The pre-fix tree uses only single + double quoted strings, so the conservative default is sound |
| Guard false-positives a legitimate `err.response.data.message` chain | The regex is anchored on literal `response.` prefix; `err.response.data.message` starts with `err.response.`, not `response.`, so it does not match |
| Guard false-positives a legitimate `response?.data` chain (one-level) | The regex requires `response.data?.` or `response.data.` PLUS a second member (`.data` or `.meta`); `response?.data` alone matches the anchor but fails the trailing `.data|.meta` requirement |
| Guard scans go outside `resources/js/` (e.g. into `vendor/`) | `vendor/` is sibling to `resources/js/`, not a descendant; `glob` against `resources/js/composables/*.js`, `resources/js/modules/**/*.{vue,js}`, `resources/js/components/**/*.{vue,js}` does not recurse outside |
| `pnpm dev` not running when manual smoke starts | T-01.4 Part B step 1 explicitly checks dev-server status and rebuilds if needed |
| `pnpm build` cache produces stale `public/build/` between dev-server reloads | `pnpm dev --force` (or `rm -rf node_modules/.vite && pnpm build`) forces a clean rebuild |
| `useBranches.js:50–52` simplification removes a defensive branch that some endpoint relies on | Verify by reading the controller — `BranchesController@index` returns `JsonResponse` with the standard `{data: ...}` envelope; no endpoint double-wraps (orchestrator verified pre-fix) |
| `useAuditLogs.js` simplification drops a fallback that handles a non-standard endpoint | Same as above — the `||` fallback is dead under `useApi.handleResponse` returning the flat envelope |
| Live odontosuite DB is touched accidentally during verification | T-01.4 runs only read-only `file_get_contents` via PHPUnit + agent-browser GET navigation; no `migrate:fresh`, no `db:wipe`, no artisan destructive command |
| Dev-server hot-reload drops the change because the file watcher is stale | `pnpm dev --force` or restart Vite explicitly; documented as a fallback in T-01.4 Part B step 1 |

## Notes

- **TDD ordering recap**:
  - **T-01.1** GREEN: composable files end in the one-level drill shape after the patch.
  - **T-01.2** GREEN: Vue pages end in the one-level drill shape after the patch.
  - **T-01.3** RED→GREEN: the static guard test passes against the post-T-01.1 + T-01.2 tree (GREEN proof). The RED proof requires reverting T-01.1's shape — this is T-01.4 Part A.
  - **T-01.4** REINTRODUCTION + SMOKE: revert T-01.1, observe guard failure, restore; then agent-browser smoke against Catálogo (41) and Especialidades (9).
- The guard test is the tripwire for future regressions — even if a future dev adds a new composable that drills one level too deep, this test fails CI immediately. This is the symmetric half of the design Decision 5 contract.
- DO NOT refactor `useApi.js` to auto-unwrap — design Decision 1 explicitly rejects this. The flat envelope is the API contract; every caller remains explicit.
- DO NOT touch `resources/js/modules/environments/EnvironmentsPage.vue` — it is the canonical reference (the only working pattern). It must remain unchanged so future devs have a clean template.
- No backend, no migration, no schema change. `git revert <sha>` returns the UI to the current broken-but-noisy state with no other side effects.
