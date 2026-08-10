# Design: fix-composables-response-unwrap-2026-08

## Technical Approach

Mechanical one-level unwrap across 14 frontend files: `response.data?.data → response.data`, `response.data.data → response.data`, `response.data?.meta → response.meta`. Mirror the working `EnvironmentsPage.vue:359` (`response?.data`) and `useApi.handleResponse` (`return response.json()`) contract — the success payload is the flat envelope `{data:[…], meta:{…}}`. New PHPUnit guard `SddCheckJsComposablesTest.php` scans `resources/js/` and fails the build if any read pattern re-asserts the double-drill.

## Architecture Decisions

### Decision 1 — Fix scope is uniform one-line unwrap, no `.meta` / `.errors` refactor

**Choice**: replace every read of `response.data.data` / `response.data?.data` with `response.data`. Replace `response.data?.meta` / `response.data.meta` with `response?.meta`.
**Alternatives considered**: (a) refactor `useApi.js` to auto-unwrap — rejected; adds opacity and forces a global change to every call site, masking intent. (b) Introduce a typed wrapper helper — rejected; vitest is not installed and there is no TS, so a runtime helper is the same opacity cost with no testability gain.
**Rationale**: the only API contract is `response.json()` = `{data, meta}`. The error path stays `err.response.data.{errors,message,meta.message}` (per `useApi.normalizeError`) — that branch is unchanged. Grep confirms 47 occurrences across 14 files; mechanical replacement is exhaustive.

### Decision 2 — One commit, one PR

**Choice**: single commit titled `fix(composables): unwrap one level on flat-envelope response` covering all 14 file patches + the new guard test.
**Alternatives considered**: (a) split patches vs. guard test across two commits — rejected; the guard enforces the patches; they share one review surface and one rollback. (b) Per-composable commits — rejected; the bug is one defect class (NEW-005), not 14 unrelated ones.
**Rationale**: project history (per AGENTS.md §12 + the 22-commit `bugfix-2026-08` archive) shows single-commit-per-bugfix for small mechanical changes. Total ≈ 47 lines added/removed; well under the 400-line review budget. Forecast: `Decision needed before apply: No`, `Chained PRs recommended: No`, `400-line budget risk: Low`.

### Decision 3 — New file `tests/Unit/SddCheckJsComposablesTest.php`, not an extension of `SddCheckMigrationsTest`

**Choice**: dedicated test file mirroring `SddCheckMigrationsTest` conventions (line-by-line strip, `$stripPatterns` array, brace-aware closure walks not needed here — the JS regex is flat).
**Alternatives considered**: extend `SddCheckMigrationsTest` — rejected; that test is named and doc-block-scoped to migration hygiene. Conflating JS unwrap discipline into a migrations file bloats the doc-block and obscures the failure domain (`migration drift` vs `flat-envelope violation`).
**Rationale**: one test = one concern. The new file scans `resources/js/composables/*.js`, `resources/js/modules/**/*.{vue,js}`, and `resources/js/components/**/*.{vue,js}` — covering the missed `ImportCsvModal.vue:110` that the proposal's "4 affected pages" count under-reported.

### Decision 4 — `pnpm build` required for production; dev-server HMR handles manual smoke

**Choice**: include `pnpm build` in the verification steps so CI's `frontend-build` job rebuilds `public/build/`. Manual smoke against `pnpm dev` needs no rebuild (Vite HMR).
**Alternatives considered**: skip the build step — rejected; CI's `frontend-build` job (per AGENTS.md §10) gates every PR, so the change must build clean.
**Rationale**: AGENTS.md §3 lists `pnpm dev` (HMR) and `pnpm build` (CI) as the two verification paths. The orchestrator's manual reload on `pnpm dev` will hot-reload automatically; the CI gate is the binding constraint.

### Decision 5 — Symmetric guard: reverting patches alone fails CI; reverting test alone leaves UI working

**Choice**: guard test fails on ANY `response.data.data`, `response.data?.data`, `response.data.meta`, or `response.data?.meta` read pattern across the scanned paths.
**Rationale**: if someone `git revert`s the patches, the patterns reappear and `SddCheckJsComposablesTest::no_composable_double_unwraps_response` fails — loud, immediate. If someone reverts the test alone, the patches remain and the UI works (silent regression risk for future devs). Both halves of the contract are independently load-bearing; the test is the tripwire.

## Data Flow

```
Controller@index → JsonResponse → fetch().json() → handleResponse() returns flat JSON
       ↓
  composable receives response (already flat)
       ↓
  composable unwraps ONCE: response.data / response?.meta
       ↓
  Vue page renders array
```

Pre-fix, the third arrow drills twice (`response.data.data`); the second `.data` is always `undefined`, so pages render empty arrays even when the DB is full.

## File Changes

| File | Action | Description |
|---|---|---|
| `resources/js/composables/useProcedureCatalog.js` | Modify | Lines 37–38, 54–55, 69, 85 → unwrap |
| `resources/js/composables/useSpecialties.js` | Modify | Line 16 → unwrap |
| `resources/js/composables/useAiAnalysis.js` | Modify | Line 201 → unwrap |
| `resources/js/composables/useAuditLogs.js` | Modify | Lines 19, 69, 86, 103 → simplify fallback (drop dead `.data?.data` branch since `response.data` already covers it) |
| `resources/js/composables/useBranches.js` | Modify | Lines 50–52 dead branch removed; first `if` retains `Array.isArray(response.data)` (matches `EnvironmentsPage` pattern) |
| `resources/js/composables/useMedicalRecords.js` | Modify | Lines 27, 29, 44, 46, 61, 81, 133, 161, 163, 189, 210, 225, 227 → unwrap |
| `resources/js/composables/useProcedureFavorites.js` | Modify | Lines 26, 49–50, 66, 107 → unwrap |
| `resources/js/composables/useQuotations.js` | Modify | Lines 38–39, 61, 63, 78, 98, 150, 178, 235, 237 → unwrap |
| `resources/js/composables/useSpecialtyRecords.js` | Modify | Lines 25, 27, 42, 44, 59, 79, 133, 135, 151 → unwrap |
| `resources/js/modules/business-intelligence/BusinessIntelligencePage.vue` | Modify | Line 495 → `response.data`; line 496 → `response.columns` |
| `resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue` | Modify | Line 208 → unwrap |
| `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | Modify | Line 157 → unwrap |
| `resources/js/modules/treatment-plans/components/CreatePatientInline.vue` | Modify | Line 143 → `response.data` |
| `resources/js/components/procedures/ImportCsvModal.vue` | Modify | Line 110 → unwrap (5th page, missed in proposal) |
| `tests/Unit/SddCheckJsComposablesTest.php` | Create | Guard test |

## Interfaces / Contracts

```php
/** @test */
public function no_composable_double_unwraps_response(): void
{
    $violations = [];
    $stripPatterns = [
        '/\/\/.*$/m',
        '/\/\*.*?\*\//s',
        "/'(?:\\\\.|[^'\\\\])*'/s",
        '/"(?:\\\\.|[^"\\\\])*"/s',
    ];
    // Anchored on literal `response.` so constructed error objects
    // (`{ response: { data: { message: ... } } }`) never match.
    $forbidden = '/\bresponse\.data\??\.(?:data|meta)\b/';
    $paths = array_merge(
        glob(__DIR__ . '/../../resources/js/composables/*.js') ?: [],
        glob(__DIR__ . '/../../resources/js/modules/**/*.{vue,js}') ?: [],
        glob(__DIR__ . '/../../resources/js/components/**/*.{vue,js}') ?: [],
    );
    foreach ($paths as $file) {
        $name = basename($file);
        $lines = explode("\n", (string) file_get_contents($file));
        foreach ($lines as $i => $line) {
            $stripped = preg_replace($stripPatterns, '', $line) ?? $line;
            if (preg_match($forbidden, $stripped)) {
                $violations[] = "{$name}:" . ($i + 1) . " double-unwraps response";
            }
        }
    }
    $this->assertSame([], $violations,
        "No composable may drill `response.data.data` / `response.data.meta` — useApi returns a flat envelope (NEW-005). Offenders:\n"
        . implode("\n", $violations)
    );
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Source inspection | Anti-pattern regression | `SddCheckJsComposablesTest::no_composable_double_unwraps_response` (PHPUnit, mirrors `SddCheckMigrationsTest` strip-recipe) |
| Manual smoke | Catálogo renders 41/41; Especialidades dropdown 9/9; remaining 5 pages render | Reload via `pnpm dev` HMR; or `pnpm build` then serve from `public/build/` |
| CI | Build + lint clean | `pnpm lint:check && pnpm build` (per AGENTS.md §3) |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary is touched. Frontend-only read-pattern correction.

## Migration / Rollout

No migration required. No DB touch. No backend change. Rollback is `git revert <sha>`; UI returns to the current broken-but-noisy state with no schema drift.

## Open Questions

None.
