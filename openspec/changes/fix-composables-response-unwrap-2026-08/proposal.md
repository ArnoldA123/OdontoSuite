# Proposal: fix-composables-response-unwrap-2026-08

## Intent

Remediate NEW-005: `useApi.handleResponse` at `resources/js/composables/useApi.js:74` returns the flat JSON envelope `{data: [...], meta: {...}}` without unwrapping. Nine composables then over-drill one level: `response.data?.data` or `response.data.data`. Because the API never wraps in a second envelope, the second `.data` is always `undefined`. Verified impact: Catálogo de Procedimientos renders 0 of 41 BD rows; Especialidades dropdown shows only "— Sin especialidad —" against 9 rows. The same anti-pattern repeats, unverified, across 7 more composables.

This is a frontend-only correctness fix. One-line pattern change across 14 files. No backend, schema, or framework change.

## Scope

### In Scope

- 9 composables in `resources/js/composables/`: replace `response.data?.data` with `response.data` (and `response.data.data` with `response.data`); `response.data?.meta` with `response.meta`. Mirror the working `EnvironmentsPage.vue:359` pattern (`response?.data`).
- 4 affected pages: drop the wrong second-level accesses that match the same anti-pattern.
- 1 new PHPUnit source-inspection test `tests/Unit/SddCheckJsComposablesTest.php` that scans `resources/js/composables/*.js` for the forbidden patterns and fails on any match. Mirror the `SddCheckMigrationsTest.php` precedent (PHPUnit + `glob` + regex on file contents).
- Single PR, ≤120 LOC.

### Out of Scope

- Backend API shape changes. Flat envelope is canonical; do NOT introduce a second wrapper.
- Refactoring `useApi.js` to auto-unwrap (adds opacity; every caller stays explicit).
- Touching working composables (Environments, AppointmentTypes already correct).
- New test framework (no vitest). PHPUnit filesystem scan only.
- `migrate:fresh` or any live DB touch (108 migrations, 100 patients, 41 procedures, 9 specialties).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

None at the spec level. Mechanical JS patch; no requirements change.

## Approach

| Step | File | Action |
|---|---|---|
| 1 | `resources/js/composables/useProcedureCatalog.js` and 8 sibling composables | Replace `response.data?.data` → `response.data`; `response.data.data` → `response.data`; `response.data?.meta` → `response.meta`. |
| 2 | 4 affected `.vue` pages | Same one-level unwrap. |
| 3 | `tests/Unit/SddCheckJsComposablesTest.php` (new) | `glob` `resources/js/composables/*.js`. Strip strings + comments; regex `response\s*\.\s*data\s*\?\s*\.\s*data\b\|\s*\[\]\|response\.data\s*\.\s*data\b\|response\.data\s*\?\s*\.\s*meta\b`. Fail on match. |

Manual verify post-merge: load Catálogo de Procedimientos → expect 41 rows; Especialidades dropdown → expect 9 entries; spot-check remaining 7 pages.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `resources/js/composables/useProcedureCatalog.js` | Modified | One-level unwrap. |
| `resources/js/composables/useSpecialties.js` | Modified | One-level unwrap. |
| `resources/js/composables/useAiAnalysis.js` | Modified | One-level unwrap. |
| `resources/js/composables/useAuditLogs.js` | Modified | One-level unwrap. |
| `resources/js/composables/useBranches.js` | Modified | One-level unwrap. |
| `resources/js/composables/useMedicalRecords.js` | Modified | One-level unwrap. |
| `resources/js/composables/useProcedureFavorites.js` | Modified | One-level unwrap. |
| `resources/js/composables/useQuotations.js` | Modified | One-level unwrap. |
| `resources/js/composables/useSpecialtyRecords.js` | Modified | One-level unwrap. |
| 4 affected `.vue` pages | Modified | One-level unwrap. |
| `tests/Unit/SddCheckJsComposablesTest.php` | New | Guard test mirroring `SddCheckMigrationsTest`. |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Unwrapping breaks a backend response that DID wrap | Very Low | Orchestrator verification: every `/api/*` endpoint returns flat envelope. |
| Guard test false-positives on legitimate nested `.data.data` access | Low | Pattern requires `response\.data` prefix; strip strings/comments before match (same recipe as `SddCheckMigrationsTest`). |
| Vue dev server cache; manual verify fails until rebuild | Low | `pnpm build` (or `pnpm dev --force`) before manual smoke. |
| Public comments accidentally unwrap an error path correctly | Low | `err.response.data.message` pattern is unchanged; only the success-path `.data` chain is touched. |

## Rollback Plan

`git revert <sha>`. Composables revert to broken-but-noisy state; UI returns to current behavior. No DB migration involved; no schema drift.

## Dependencies

- None (no new packages).
- PHPUnit + filesystem read access (already present in the project).

## Success Criteria

- [ ] `vendor/bin/phpunit tests/Unit/SddCheckJsComposablesTest.php` exits 0.
- [ ] Catálogo de Procedimientos page renders all 41 procedures.
- [ ] Especialidades dropdown lists all 9 specialties.
- [ ] Remaining 7 composables visually validated on their pages.
- [ ] `vendor/bin/phpunit` overall does not regress.
- [ ] `pnpm lint:check && pnpm build` exits 0.
