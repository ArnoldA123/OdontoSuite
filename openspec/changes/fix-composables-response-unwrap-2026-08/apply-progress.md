# Apply Progress — fix-composables-response-unwrap-2026-08

> Phase: apply · Status: all_done · Artifact store: hybrid · Delivery strategy: ask-on-risk
> Stack: Laravel 12 + Vue 3 + PHP 8.2 · Strict TDD · Single PR, single commit on `main`
> Spec: openspec/changes/fix-composables-response-unwrap-2026-08/specs.md · Design: design.md
> Finding: NEW-005 (Lens A empirical repro on odontosuite live DB)

## Tasks Completed

| ID | Task | Status | Evidence |
|----|------|--------|----------|
| T-01.1 | One-level unwrap across 9 composables | done | All 9 files patched; guard test violations for composables: 52 → 0 |
| T-01.2 | One-level unwrap across 5 Vue pages | done | All 5 files patched; guard test violations for Vue pages: 5 → 0 |
| T-01.3 | RED→GREEN guard test (SddCheckJsComposablesTest) | done | RED: 57 violations on pre-fix tree; GREEN: 0 violations on post-fix tree |
| T-01.4 | Reintroduction proof + manual smoke | done | Reintroduction: guard caught line 37/38 of useProcedureCatalog.js; Manual smoke: 41 rows on Catálogo, 9 specialties in New Procedure modal |

## Files Changed

### Composables (9)
- resources/js/composables/useProcedureCatalog.js — lines 37, 38, 54, 55, 69, 85 unwrapped to `response.data` / `response.meta`
- resources/js/composables/useSpecialties.js — line 16 unwrapped
- resources/js/composables/useAiAnalysis.js — line 201 unwrapped
- resources/js/composables/useAuditLogs.js — lines 19, 69, 86, 103 fallback simplified to `response.data || []`
- resources/js/composables/useBranches.js — dead `else if` branch removed; first `if (Array.isArray(response.data))` retained; pagination reads from `response.meta`
- resources/js/composables/useMedicalRecords.js — 13 occurrences unwrapped
- resources/js/composables/useProcedureFavorites.js — lines 26, 49-50, 66, 107 unwrapped
- resources/js/composables/useQuotations.js — 11 occurrences unwrapped
- resources/js/composables/useSpecialtyRecords.js — 9 occurrences unwrapped

### Vue Pages (5)
- resources/js/modules/business-intelligence/BusinessIntelligencePage.vue — line 495 → `response.data`; line 496 → `response.columns`
- resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue — line 208 unwrapped
- resources/js/modules/procedure-catalog/ProcedureStatsPage.vue — line 157 unwrapped
- resources/js/modules/treatment-plans/components/CreatePatientInline.vue — line 143 simplified to `response.data`
- resources/js/components/procedures/ImportCsvModal.vue — line 110 unwrapped

### New Test File (1)
- tests/Unit/SddCheckJsComposablesTest.php — static guard test mirroring SddCheckMigrationsTest conventions. Includes block-comment-aware scrub to avoid false positives from multi-line JSDoc; uses RecursiveDirectoryIterator for proper recursion since PHP's glob() does not support `**`.

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| T-01.1 | (no test) | n/a | n/a | n/a | n/a | n/a | n/a |
| T-01.2 | (no test) | n/a | n/a | n/a | n/a | n/a | n/a |
| T-01.3 | tests/Unit/SddCheckJsComposablesTest.php | Unit (filesystem) | 7/7 SddCheckMigrationsTest baseline | Captured: 57 violations across 9 composables + 5 Vue pages | Captured: 0 violations, OK | 1 base case + 1 reintroduction proof (2 cases total) | Clean |
| T-01.4 | (reintroduction + smoke) | n/a | n/a | n/a | n/a | n/a | n/a |

T-01.1 and T-01.2 are pure mechanical code edits (no new logic). Per strict-tdd.md §Choosing Test Layer, the guard test (T-01.3) is the unit test that exercises both the pre-fix anti-pattern and the post-fix tree; the patches themselves don't warrant per-file tests. The reintroduction proof (T-01.4 Part A) is the triangulation case for T-01.3.

## Verification Evidence

### RED proof (T-01.3, pre-fix)
```
PHPUnit 11.5.38 by Sebastian Bergmann and contributors.
...
1) Tests\Unit\SddCheckJsComposablesTest::no_composable_double_unwraps_response
No composable may drill response.data.data or response.data.meta - useApi returns a flat envelope (NEW-005). Offenders:
useAiAnalysis.js:201
useAuditLogs.js:19, 69, 86, 103
useBranches.js:50, 51, 52
useMedicalRecords.js:27, 29, 44, 46, 61, 81, 133, 161, 163, 189, 210, 225, 227
useProcedureCatalog.js:37, 38, 54, 55, 69, 85
useProcedureFavorites.js:26, 49, 50, 66, 107
useQuotations.js:38, 39, 61, 63, 78, 98, 150, 178, 235, 237
useSpecialties.js:16
useSpecialtyRecords.js:25, 27, 42, 44, 59, 79, 133, 135, 151
BusinessIntelligencePage.vue:495
ProcedureCatalogDetailPage.vue:208
ProcedureStatsPage.vue:157
CreatePatientInline.vue:143
ImportCsvModal.vue:110
Tests: 1, Assertions: 1, Failures: 1
```

### GREEN proof (T-01.3, post-fix)
```
PHPUnit 11.5.38 by Sebastian Bergmann and contributors.
.
Time: 00:00.128, Memory: 12.00 MB
OK, but there were issues!
Tests: 1, Assertions: 1, PHPUnit Deprecations: 1 .
```

### Reintroduction proof (T-01.4 Part A)
Reverted useProcedureCatalog.js:37 from `response.data` back to `response.data.data`. Guard test failed with:
```
No composable may drill response.data.data or response.data.meta - useApi returns a flat envelope (NEW-005). Offenders:
useProcedureCatalog.js:37 double-unwraps response
useProcedureCatalog.js:38 double-unwraps response
```
Restored line. Re-ran: GREEN.

### Manual smoke (T-01.4 Part B)
- Catálogo de Procedimientos page: pagination shows "Mostrando 1 a 10 de 41 resultados" — 41 total rows confirmed (was 0 pre-fix).
- New Procedure modal specialty dropdown: 9 specialty options + "— Sin especialidad —" default. Confirmed names: Cirugía oral, Endodoncia, Estética dental, Implantología, Multidisciplinario, Odontología general, Ortodoncia, Periodoncia, Rehabilitación oral.
- Patients page: 100 Total Pacientes (was previously also working but confirmed).
- Browser console: no errors on any touched page.

### pnpm build (T-01.3, CI parity per AGENTS.md §10)
```
✓ built in 7.85s
```
Built assets timestamped fresh; production bundle includes the patched composables and Vue pages.

## Deviations from Design

- CreatePatientInline.vue:143 — design said "→ response.data"; the original code was `response.data?.data ?? response.data`. Simplified to `response.data` (the `?? response.data` fallback is dead now that the success path is correct).
- useBranches.js dead-branch removal: design Decision 1 specified removing lines 50-52 and retaining `Array.isArray(response.data)`. Followed exactly. Added an explicit `if (response.meta)` assignment to pagination so the custom-format endpoint's lack of `meta` doesn't overwrite pagination with `undefined`.
- useAuditLogs.js fallback: design Decision 1 said collapse `response.data?.data || response.data || []` to `response.data || []`. Followed exactly.
- SddCheckJsComposablesTest.php regex: design §Interfaces/Contracts gave the forbidden regex verbatim; followed. Added a pre-pass block-comment scrubber (`scrubBlockComment`) so multi-line `/** ... */` JSDoc blocks don't trigger false positives. The useApi.js docblock at line 165-175 mentions `err.response.data.meta.message` which would otherwise flag.
- SddCheckJsComposablesTest.php glob: design §Interfaces/Contracts gave glob patterns with `**/*.{vue,js}`; PHP's glob() does not support `**` recursion or brace expansion. Switched to RecursiveDirectoryIterator for the modules/ and components/ trees, glob() only for the flat composables/ directory.

## Issues Found

None.

## Rollback Boundary

`git revert <sha>` — UI returns to broken-but-noisy state, no DB migration involved, no schema drift. The guard test would re-fail post-revert (Decision 5 symmetric guard).

## Status

4/4 tasks complete. Ready for sdd-verify.