# Archive Report: recepcion-procedimientos (ui-rollout-all-modules-2026-08)

**Status**: ARCHIVED
**Change**: ui-rollout-all-modules-2026-08 (recepcion-procedimientos category slice)
**Archived**: 2026-08-21
**Verify**: PASS WITH WARNINGS (see `verify-report.md`)

## Metadata

| Key | Value |
|---|---|
| Date | 2026-08-21 |
| Category | recepcion-procedimientos |
| Source change | ui-rollout-all-modules-2026-08 |
| PR | pr-recepcion-procedimientos-tokenise (commit `654130a`) |
| Verdict | PASS WITH WARNINGS |
| Line diff | 158 lines (40% of the 400-line budget) |
| Artifact store | hybrid (OpenSpec files + Engram observations) |

## Final state

- 7 REC-* MUSTs all satisfied at static-contract level (REC-001 … REC-007).
- 1 new test file: `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` — 12 tests, 36 assertions, green.
- `AppLayoutCanvasRoutesTest` 25 tests / 72 assertions green (REC-001 regression guard).
- `pnpm build` clean (9.77s, no Tailwind purge regression).
- 0 new tokens; `tokens.js` untouched.
- 0 new primitives; the category only consumes primitives tokenised in PR2.
- 0 `<style scoped>` blocks introduced (DLR-R-021 guard extended).
- `<script>` block of `ReceptionProceduresPage.vue` byte-for-byte preserved; `useProcedureCatalog` and `useSpecialties` contracts frozen.
- Backend untouched; `/api/reception-procedures` contract preserved verbatim.
- Single commit, single PR, fast-forward merged to `main`.

## Deliverables

| Artifact | Detail |
|---|---|
| Production change | `resources/js/modules/reception-procedures/ReceptionProceduresPage.vue` (78 lines touched, template-only) |
| Test change | `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` (NEW, 118 lines) |
| Diff stat | 2 files changed, 158 insertions(+), 38 deletions(-) |

## Archive-time reconciliation (recorded per skill policy)

`tasks.md` reached archive with **T10** still unchecked (`- [ ]`). The
Task Completion Gate permits mechanical reconciliation only with proof from
`apply-progress.md` and `verify-report.md` plus explicit orchestrator
instruction to settle the category; both conditions held.

Proof: every T10 command was actually executed. `AppLayoutCanvasRoutesTest`
25/25, `pnpm build` clean, DesignSystem sweep 448 tests, `tests/Feature/Api`
137 tests. T10 was left unchecked because two of those sweeps reported
**pre-existing, environmental** failures, not because work remained. Per
`verify-report.md` §"Known environmental limitations", neither failure set is
attributable to this category and no REC-* MUST is affected. The checkbox was
reconciled to `- [x]` with an inline note recording the same reason.

## Final-state authority notes

- The launch prompt (highest available final-state source for this close) and
  `verify-report.md` agree on all facts recorded here; no unrankable
  contradiction was found.
- `apply-progress.md` records overall status **PARTIAL** and recommends
  rerunning `sdd-apply`. That is an intermediate snapshot claim, valid at the
  time it was written and driven solely by the T10 environmental noise above.
  It is NOT the final state: verification subsequently returned PASS WITH
  WARNINGS on the same commit `654130a`, and the category closed without
  another apply pass.
- `apply-progress.md` also records "365 lines changed"; the authoritative
  final figure is the merged commit's `git diff --stat`: **158 lines**.
- `reviewGate` is structurally absent for this candidate — no receipt-driven
  review was ever started. Archive proceeds under ordinary repository policy.

## Spec promotion

7 REC-* MUST rows promoted to
`openspec/specs/design-language-rollout/spec.md` under the new section
`## Recepcion-procedimientos Rollout — 2026-08-21`, mirroring the PAGOS /
CITAS / PACIENTES section structure (requirement + provenance line +
scenario, plus an explicit close verdict per row).

| Promoted ID | Rule | Verdict at close |
|---|---|---|
| `REC-001` | `/reception-procedures` stays in `canvasRoutes` + `EXPECTED_ROUTES` | PASS |
| `REC-002` | Raw search `<input>` replaced by `<UiInput>` + `#prefix` slot | PASS |
| `REC-003` | Raw `<select>` specialty filter replaced by `<UiSelect>` | PASS |
| `REC-004` | Inline `bg-primary-50 text-primary-700` chip replaced by `<UiBadge>` | PASS |
| `REC-005` | Hand-rolled empty-results block replaced by `<UiEmptyState>` | PASS |
| `REC-006` | Price uses `text-systemBlue-600 tabular-nums` (no `text-accent`) | PASS |
| `REC-007` | Hairline token on dividers; `hover-lift transition-shadow` dropped | PASS |

## Archived artifact layout

| Artifact | Source path (in change) | Archived to |
|---|---|---|
| explore.md | `categories/recepcion-procedimientos/` | `./explore.md` |
| proposal.md | `categories/recepcion-procedimientos/` | `./proposal.md` |
| spec.md (delta) | `categories/recepcion-procedimientos/` | `./spec.md` |
| tasks.md | `categories/recepcion-procedimientos/` | `./tasks.md` |
| apply-progress.md | `categories/recepcion-procedimientos/` | `./apply-progress.md` |
| verify-report.md | `categories/recepcion-procedimientos/` | `./verify-report.md` |

`git mv` moved the whole tracked folder in one operation. A recursive
pre-move snapshot was compared against the archived tree with `diff -r`;
the output was empty (exit 0), which is the only passing evidence under the
Mechanical Copy Contract. No artifact content passed through Read/Write.

No `design.md` exists for this slice — category slices in this rollout run
explore → propose → spec → tasks → apply → verify without a separate design
artifact, matching the PAGOS/CITAS/PACIENTES precedent for category-level
deltas. This is recorded, not treated as a defect.

## Engram observation IDs (traceability)

| Artifact | Observation |
|---|---|
| explore | `#544` — "Explore: recepcion-procedimientos (ui-rollout-all-modules-2026-08)" |
| proposal | `#549` — `sdd/.../categories/recepcion-procedimientos/proposal` |
| spec | `#555` — category delta spec, 7 MUST rows |
| tasks | `#559` — `sdd/.../categories/recepcion-procedimientos/tasks` |
| apply-progress | `#504` — `sdd/ui-rollout-all-modules-2026-08/apply-progress` |
| apply summary | `#563` — `ui-rollout-all-modules-2026-08/recepcion-procedimientos/apply` |
| session context | `#565` — session summary (Lote 1 sequencing) |
| verify-report | Not present in Engram; the authoritative copy is `./verify-report.md` |

## Process lessons

1. The smallest Tier-1 module absorbs an entire category in a single PR at
   40% of the review budget. Chained sub-PRs were unnecessary, confirming that
   the chained-PR machinery should be triggered by measured diff size, not by
   category count.
2. Adopting one primitive can retire many legacy aliases at once. The single
   `<input>` → `<UiInput>` swap removed `border-theme`,
   `bg-theme-surface-elevated`, `focus:ring-primary-500`, `focus:border-accent`,
   `rounded-lg`, and `text-theme-primary` in one edit — token debt is
   concentrated in hand-rolled controls, not spread evenly across a template.
3. Environmental test noise must not be encoded as an unchecked task. T10 stayed
   `- [ ]` purely because two unrelated sweeps were red, which forced an
   archive-time reconciliation. Future apply phases should check the task and
   record the environmental failures as warnings instead.

## Warnings inherited (not blocking)

- Playwright snapshot skipped — `playwright-cli` Windows assertion error blocked
  the 1440x900 capture. Static-contract evidence is conclusive; a visual
  follow-up is owed on a host with a functional `playwright-cli`.
- 2 pre-existing DesignSystem failures (`LoginPageRenderTest`,
  `PrimitivePressTest`) — present in the PR0 baseline, orthogonal to this
  category, confirmed by isolation run.
- 95 SQLite migration errors in `tests/Feature/Api` — documented SQLite
  limitation around `transactions.type`. Backend behavior unaffected.
- 5 PHPUnit 11.5 API-level deprecations in the focused run. Out of scope.
- `<UiSelect :options="...">` used instead of `<option>` children suggested by
  task T4. REC-003 passes either way; documented as a known deviation.

## Change folder status

`openspec/changes/ui-rollout-all-modules-2026-08/` remains **active**. The
`categories/recepcion-procedimientos/` subfolder no longer exists there.

## Next

Lote 1 has 4 categories remaining, all still at the planning stage
(`explore.md`, `proposal.md`, `spec.md`, `tasks.md` present; no
`apply-progress.md` or `verify-report.md`):

- `ambientes`
- `estadisticas-catalogo`
- `mis-procedimientos`
- `tipos-cita`

Each should follow this slice as the precedent: single PR, category-level
verify, promote MUST rows to the parent DLR spec, then archive.
