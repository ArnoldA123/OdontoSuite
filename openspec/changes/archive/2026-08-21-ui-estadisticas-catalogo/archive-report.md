# Archive Report: estadisticas-catalogo (ui-rollout-all-modules-2026-08)

**Status**: ARCHIVED
**Change**: ui-rollout-all-modules-2026-08 (estadisticas-catalogo category slice)
**Archived**: 2026-08-21
**Verify**: PASS WITH WARNINGS (see `verify-report.md`)

## Metadata

| Key | Value |
|---|---|
| Date | 2026-08-21 |
| Category | estadisticas-catalogo |
| Source change | ui-rollout-all-modules-2026-08 |
| PR | pr1-procedure-stats-tokenise-and-wire-up (feature commit `36fd93e` + housekeeping `61c4211`) |
| Verdict | PASS WITH WARNINGS |
| Line diff | 619 lines (55% over the 400-line review budget; size-exception pre-authorized for 3rd consecutive Lote 1 slice) |
| Artifact store | hybrid (OpenSpec files + Engram observations) |

## Final state

- 9 EC-* MUSTs all satisfied at static-contract + runtime level (EC-001 ... EC-009). EC-001 was BLOCKING (router fix) and is now verified at `app.js:123-128`.
- 1 new test file: `tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` — 14 tests, 86 assertions, green.
- 1 additive route registration: `resources/js/app.js` now carries the `/procedure-stats` route block between `/procedure-catalog/:id` and `/my-procedures`, with `beforeEnter: requireAuth`. Sits before the catch-all (line 207). Without this entry the polished page fell through to the 404 catch-all per OQ-EC-1.
- `pnpm build` clean (7.74s, no Vite warnings, `ProcedureStatsPage-*.js` chunk emitted).
- 0 new tokens; `tokens.js` untouched.
- 0 new primitives; the category only consumes primitives tokenised in PR0.
- 0 `<style scoped>` blocks introduced (DLR-R-021 guard extended).
- `<script setup>` preserved with one additive line: `import { formatPENLabel } from '@/composables/useFormatters'`.
- Backend untouched; `/api/admin/procedure-stats` contract preserved verbatim.
- Single PR, single feature commit (`36fd93e`) + housekeeping commit (`61c4211`), fast-forward merged to `main`.
- Source-grep anti-regression clean: zero raw `border-theme`, `text-green-600`, `bg-red-50`, `border-red-200`, `text-red-700`, `toFixed(2)`, or production `<h1` matches in `ProcedureStatsPage.vue`.

## Deliverables

| Artifact | Detail |
|---|---|
| Production change #1 | `resources/js/app.js` (10 insertions, 0 deletions) — additive `/procedure-stats` route block at lines 123-128 |
| Production change #2 | `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` (162 insertions, 59 deletions) — tokenisation, KPI anatomy, PageHeader, EmptyState, Skeleton, formatPENLabel, systemGreen/Red, hairlines, role disclosure |
| Test change | `tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` (NEW, 398 lines) — 14 tests, 86 assertions |
| Diff stat | 3 files changed, 570 insertions(+), 59 deletions(-) |

## Archive-time reconciliation (recorded per skill policy)

`tasks.md` reached archive with all 15 implementation tasks checked
(`- [x]`): T1 RED → T2 EC-001 router fix → T3-T10 GREEN → T11 focused
suite GREEN → T12 full sweep + build → T13 visual skipped (playwright
Windows error) → T14-T15 commit + fast-forward merge. No
stale-checkbox reconciliation was required for this category. No
unrankable contradiction was found between the launch prompt (highest
final-state source), the cached `delivery_strategy: auto-chain`, and
the persisted `apply-progress.md` / `verify-report.md` snapshots.

## Final-state authority notes

- The launch prompt (highest available final-state source for this close) records: "All 9 EC-* MUSTs satisfied at static-contract + runtime level; BLOCKING EC-001 router fix verified at `app.js:123-128`; focused test `ProcedureStatsAppShellTest` 14/14 (86 assertions); `pnpm build` clean (7.74s); 619-line diff size-exception pre-authorized."
- `apply-progress.md` reports "Status: DONE (Phase 4 commits pending orchestrator)" and `Total PR diff: 619 lines` (over 400-line cap; documented under size-exception). The housekeeping commit `61c4211` matches the source of truth the launch prompt referenced.
- `verify-report.md` reports "PASS WITH WARNINGS" with all 9 EC-* MUSTs satisfied. The 619-line figure (221 production + 398 test) is canonical.
- `tasks.md` §"Review Workload Forecast" estimated ~140 lines; actual was 619 because the test file carried 9 EC-* + 5 inherited assertions across the per-category rule set and required 398 lines (25-30 lines per MUST row × 14 assertions + setup, mirroring the mis-procedimientos precedent). The size-exception was pre-authorized by the orchestrator prompt and recorded in `apply-progress.md` §"Risks encountered" and `tasks.md` §"Review Workload Forecast" (`Chain strategy: size-exception`). It is NOT a defect; the test file is the load-bearing evidence layer.
- `reviewGate` is structurally absent for this candidate — no receipt-driven review was ever started. Archive proceeds under ordinary repository policy.

## Spec promotion

9 EC-* MUST rows promoted to `openspec/specs/design-language-rollout/spec.md`
under the new section `## Estadísticas catálogo Rollout — 2026-08-21`,
mirroring the PAGOS / CITAS / PACIENTES / recepcion-procedimientos /
mis-procedimientos section structure (requirement + provenance line +
scenario, plus an explicit close verdict per row).

| Promoted ID | Rule | Verdict at close |
|---|---|---|
| `EC-001` (BLOCKING) | `/procedure-stats` registered in `app.js` between `/procedure-catalog/:id` and `/my-procedures` blocks; `beforeEnter: requireAuth`; before catch-all | PASS |
| `EC-002` | 3 KPI `<UiCard>` carry `data-stat-card` + hairline + elevation-2 + 4 reserved slots `h-4 → h-12 → h-6 → h-4` in exact order | PASS |
| `EC-003` | `<PageHeader>` replaces custom header; zero `<h1>` tags (defect 7 family) | PASS |
| `EC-004` | `<UiEmptyState>` x2 + `<UiSkeleton variant="card">` x3 + `<UiSkeleton variant="list">` x6 with `aria-busy` + `aria-live="polite"` | PASS |
| `EC-005` | `formatPENLabel` consumed; no inline `.toFixed(2)` | PASS |
| `EC-006` | `text-green-600` → `text-systemGreen-600`; raw red ramps → `bg-systemRed-50 border-systemRed-200 text-systemRed-700` | PASS |
| `EC-007` | `border-theme` eliminated; specialty tile uses `rounded-[var(--radius-control)]` + `border-[color:var(--color-hairline)]` | PASS |
| `EC-008` | Inline role disclosure `Visible para: Administrador, Finanzas` inside `<div class="text-xs text-theme-secondary mb-4">` | PASS |
| `EC-009` | All 8 numerics carry BOTH `tabular-nums` AND `style="font-feature-settings: var(--font-features-tabular-nums)"` | PASS |

## Archived artifact layout

| Artifact | Source path (in change) | Archived to |
|---|---|---|
| explore.md | `categories/estadisticas-catalogo/` | `./explore.md` |
| proposal.md | `categories/estadisticas-catalogo/` | `./proposal.md` |
| spec.md (delta) | `categories/estadisticas-catalogo/` | `./spec.md` |
| tasks.md | `categories/estadisticas-catalogo/` | `./tasks.md` |
| apply-progress.md | `categories/estadisticas-catalogo/` | `./apply-progress.md` |
| verify-report.md | `categories/estadisticas-catalogo/` | `./verify-report.md` |

`git mv` moved the 5 tracked artifacts (`explore.md`, `proposal.md`,
`spec.md`, `tasks.md`, `apply-progress.md`) in git-aware operations;
the 1 untracked `verify-report.md` was moved with `mv`. The empty
source directory was removed afterwards to match the
`recepcion-procedimientos` and `mis-procedimientos` precedent (the
`categories/estadisticas-catalogo/` subfolder no longer exists in the
live change tree). MANDATORY `diff -r` against the pre-move snapshot
returned exit 0 (empty diff — no truncation, no alteration). Verbatim
output included in the phase result.

No `design.md` exists for this slice — category slices in this
rollout run explore → propose → spec → tasks → apply → verify
without a separate design artifact, matching the
PAGOS/CITAS/PACIENTES/recepcion-procedimientos/mis-procedimientos
precedent for category-level deltas. This is recorded, not treated
as a defect.

## Engram observation IDs (traceability)

| Artifact | Observation |
|---|---|
| explore | Not present in Engram; the authoritative copy is `./explore.md` |
| proposal | Not present in Engram; the authoritative copy is `./proposal.md` |
| spec (delta) | Not present in Engram; the authoritative copy is `./spec.md` |
| tasks | Not present in Engram; the authoritative copy is `./tasks.md` |
| apply-progress | Not present in Engram; the authoritative copy is `./apply-progress.md` |
| verify-report | Not present in Engram; the authoritative copy is `./verify-report.md` |
| session summary | Saved by this archive agent as `sdd/ui-rollout-all-modules-2026-08/categories/estadisticas-catalogo/archive-report` |

## Process lessons

1. **The router fix is the load-bearing blocker for this PR.** `/procedure-stats` was NOT registered in `resources/js/app.js` before commit `36fd93e`. Without the additive route block at lines 123-128, the polished page is unreachable via normal navigation — users hit the 404 catch-all. The fix is ~10 lines including a context comment; deferring it to a separate prerequisite PR would have inflated the chain for a trivial edit. Including the route in PR1 is the cheapest path to a verified third proving ground (after Login + Dashboard + this page).
2. **Test-file size is the load-bearing variable for per-category slices.** The 140-line estimate assumed ~50 lines of test coverage; 9 EC-* assertions + 5 inherited assertions across the per-category rule set required 398 lines. This is the 3rd Lote 1 slice where the test file drives the size-exception (PAGOS/CITAS/PACIENTES closed in 2026-08-12 under the 400-line cap; recepcion-procedimientos 7 REC-* at ~200 lines; mis-procedimientos 14 MIS-* at 367 lines; this slice 9 EC-* at 398 lines). Future apply phases for category slices with 9+ MUST rows MUST pre-budget the test file at 25-30 lines per MUST row + inherited setup, then surface the total up front. Size-exception is a real outcome, not a defect.
3. **Source-grep contracts need HTML-comment stripping.** The page carries an inline comment documenting the EC-003 fix (`<!-- Page header (EC-003) ... <h1> defect -->`); a naive `<h1` regex would have tripped on the documentation comment itself. The test must strip HTML comments inside the template before applying the regex — same discipline as the base class's `stripStringsAndComments` helper, but extended to template HTML comments. The skeleton source-level count also requires explicit enumeration: `<UiSkeleton v-for="i in 3" ...>` counts as 1 source occurrence, not 3 runtime elements; rewriting as 3 explicit elements (lines 46-48) satisfies both the source-grep contract and the Dashboard precedent.
4. **Environmental test noise from `tests/Feature/Api` (95 SQLite `transactions.type` errors) and 2 pre-existing `LoginPageRenderTest` + `PrimitivePressTest` failures carried over from prior archives are now confirmed as cross-category baseline noise.** This is the 3rd archive confirmation (after recepcion-procedimientos + mis-procedimientos). Future categories can short-circuit the investigation and treat these as inherited warnings.

## Warnings inherited (not blocking)

- Playwright snapshot skipped — `playwright-cli` Windows assertion error blocked the 1440x900 capture. Static-contract evidence is conclusive; a visual follow-up is owed on a host with a functional `playwright-cli`. 3rd consecutive archive confirmation (environmental, not category-specific).
- 2 pre-existing DesignSystem failures (`LoginPageRenderTest`, `PrimitivePressTest`) — present in the PR0 baseline, orthogonal to this category, confirmed by isolation run. 3rd confirmation.
- 95 SQLite migration errors in `tests/Feature/Api` — documented SQLite limitation around `transactions.type`. Backend behavior unaffected. 3rd confirmation.
- **619-line PR diff (55% over 400-line review budget)** — pre-authorized as `size-exception` per orchestrator prompt; test file is the load-bearing evidence layer for 9 EC-* rows. Documented in `tasks.md` §"Review Workload Forecast" and `apply-progress.md` §"Risks encountered". 3rd consecutive Lote 1 slice that exceeds the cap under the auto-chain delivery strategy; the precedent is now solidly established.
- 3 MIS-010 status-ramp tests were consolidated into 1 method during the mis-procedimientos apply to bound file size — defensible refactor; carried over as a documented deviation.
- Initial loading skeleton v-for form (1 source occurrence) was rewritten to explicit enumeration (3 source occurrences) to satisfy the source-grep contract.

## Change folder status

`openspec/changes/ui-rollout-all-modules-2026-08/` remains **active**.
The `categories/estadisticas-catalogo/` subfolder no longer exists
there. The remaining categories are `ambientes` and `tipos-cita`.

## Next

Lote 1 has 2 categories remaining, both still at the planning stage
(`explore.md`, `proposal.md`, `spec.md`, `tasks.md` present; no
`apply-progress.md` or `verify-report.md`):

- `ambientes`
- `tipos-cita`

Each should follow this slice as the precedent: single PR, category-level
verify, promote MUST rows to the parent DLR spec, then archive. Future
slices with 9+ MUST rows MUST pre-budget test file size at 25-30 lines
per MUST row + inherited setup to avoid the size-exception path being
unexpected.

The estadisticas-catalogo slice is the **3rd of 4 Lote 1 categories
archived** (after recepcion-procedimientos and mis-procedimientos). The
size-exception precedent is now established three times; the final
Lote 1 slice can re-cite this archive as documented precedent. The
router-fix pattern (additive `app.js` entry between sibling routes,
`beforeEnter: requireAuth`, before the catch-all) is now a documented
sub-pattern for any future slice that lands a polished page whose
route was missed by PR0.
