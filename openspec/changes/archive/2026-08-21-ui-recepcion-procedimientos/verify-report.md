# Verify Report: recepcion-procedimientos (ui-rollout-all-modules-2026-08)

> Category-level verify. Single PR.

## Metadata
| Key | Value |
|---|---|
| Date | 2026-08-21 |
| PR | pr-recepcion-procedimientos-tokenise (commit 654130a) |
| Verdict | **PASS WITH WARNINGS** |

## Per-MUST verification

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| REC-001 | `canvasRoutes` regression guard — `/reception-procedures` in `AppLayout.vue` AND in `EXPECTED_ROUTES` | PASS | `AppLayout.vue:548` carries `'/reception-procedures'`. `AppLayoutCanvasRoutesTest.php:54` carries it in `EXPECTED_ROUTES`. `AppLayoutCanvasRoutesTest` ran 25/25 (72 assertions). |
| REC-002 | Raw `<input v-model="filters.search">` replaced by `<UiInput>` (with prefix slot for SVG icon) | PASS | `ReceptionProceduresPage.vue:30-51` uses `<UiInput v-model="filters.search" type="search" placeholder="...">` plus `<template #prefix>` for SVG. Grep for `<input`: 0 matches. |
| REC-003 | Raw `<select v-model="filters.specialty">` replaced by `<UiSelect>` | PASS | `ReceptionProceduresPage.vue:55-60` uses `<UiSelect v-model="filters.specialty" :options="...">`. Grep for `<select`: 0 matches. |
| REC-004 | Inline `bg-primary-50 text-primary-700` chip replaced by `<UiBadge>` | PASS | `ReceptionProceduresPage.vue:82-84` renders `<UiBadge variant="primary" size="sm" class="font-mono">`. Grep for `bg-primary-50` / `text-primary-700`: 0 matches each. |
| REC-005 | Hand-rolled `py-12 text-center text-theme-secondary` empty-results replaced by `<UiEmptyState>` | PASS | `ReceptionProceduresPage.vue:69-73` renders `<UiEmptyState v-else-if="!procedures.length" title="Sin resultados" description="...">`. |
| REC-006 | Price uses `text-systemBlue-600 tabular-nums` (no `text-accent`) | PASS | `ReceptionProceduresPage.vue:102-105` renders `text-systemBlue-600 tabular-nums` + `font-feature-settings: var(--font-features-tabular-nums)`. Grep for `text-accent`: 0 matches. |
| REC-007 | Hairline token consumed; `hover-lift transition-shadow` dropped | PASS | `ReceptionProceduresPage.vue:93` uses `border-t border-hairline`. Grep for `border-theme` / `hover-lift transition-shadow`: 0 matches each. |

Inherited module rules (DLR-R-001/002/004/021) pass via `ModuleAppShellTestCase`. Page surface reference `<AppLayout class="bg-canvas">` at `ReceptionProceduresPage.vue:2` confirms DLR-R-001.

## Test gate results

| Command | Result |
|---|---|
| `vendor/bin/phpunit tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` | PASS — 12 tests, 36 assertions |
| `vendor/bin/phpunit tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` (REC-001 guard) | PASS — 25 tests, 72 assertions |
| `pnpm build` | PASS — built in 9.77s, no errors |
| `vendor/bin/phpunit tests/Unit/DesignSystem/` (full sweep) | PARTIAL — 448 tests, 2 pre-existing failures (LoginPageRenderTest + PrimitivePressTest). Isolation run confirms not introduced by this PR. |
| `vendor/bin/phpunit tests/Feature/Api` | PARTIAL — 95 SQLite migration errors (documented `transactions.type` SQLite limitation). |

## Known environmental limitations (NOT category defects)

1. **LoginPageRenderTest + PrimitivePressTest failures** — pre-existing in PR0 baseline. The PR only edits `ReceptionProceduresPage.vue` + adds `ReceptionProceduresAppShellTest.php`. Both are orthogonal to `recepcion-procedimientos`.
2. **95 SQLite migration errors** — documented SQLite limitation around `transactions.type`. Backend unaffected.
3. **Playwright snapshot skipped** — `playwright-cli` Windows assertion error blocked 1440x900 capture. Static-contract evidence is conclusive; visual follow-up needed on a host with functional playwright-cli.
4. **T10 PARTIAL** in apply-progress because of (1) and (2). Per the brief, T10 failures MUST NOT mark any REC-* failing — they do not.

## Commit evidence

```
654130a feat(ui): tokenise recepcion-procedimientos per DLR + REC-* MUST rows
780b079 merge: feat/ui-rollout-pr0-foundation — Apple-only UI rollout (PAGOS + CITAS + PACIENTES)
```

Single commit. Diff stat (production + test):

```
resources/js/modules/reception-procedures/ReceptionProceduresPage.vue | 78 ++++++-------
tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php           | 118 +++++++++++++++++++++
2 files changed, 158 insertions(+), 38 deletions(-)
```

~40% of the 400-line review budget.

## Warnings (non-blocking)

1. Visual capture missing (playwright-cli Windows error). Static-contract evidence is conclusive.
2. Apply reported `partial` because of T10 env noise, not category. Per verify brief, this does not affect per-MUST verdict.
3. 5 PHPUnit deprecations in focused run (PHPUnit 11.5 API-level warnings). Out of scope.
4. `<UiSelect :options=...>` prop pattern used instead of `<option>` children suggested by task T4. REC-003 acceptance test passes either way (asserts absence of raw `<select ... v-model="filters.specialty">` + presence of `<UiSelect\b`). Documented in apply-progress as known deviation.

## Verdict rationale

**PASS WITH WARNINGS** — all 7 REC-* MUSTs satisfied at static-contract level. Focused test 12/12 (36 assertions). CanvasRoutes regression guard 25/25. 158-line diff well under 400-line budget. No REC-* MUST is marked failing because of environmental noise, per the verify brief.

## Next

`sdd-archive` — change is ready for settlement.

---

## Key Learnings

1. The single-commit, single-PR strategy absorbed the entire category without chained sub-PRs, well within the 400-line budget — proves the smallest Tier-1 module pattern for the rollout.
2. REC-001 is a regression guard, not an additive change — `/reception-procedures` was already wired by PR0; T2 was a no-op verify.
3. The highest-impact single edit was the raw `<input>` swap to `<UiInput>` (REC-002), which simultaneously eliminated `border-theme`, `bg-theme-surface-elevated`, `focus:ring-primary-500`, `focus:border-accent`, `rounded-lg`, and `text-theme-primary` in one replacement.
4. REC-006 uses `text-systemBlue-600 tabular-nums` (NOT `<UiBadge variant="success">`) because prices are NOT status indicators — semantic overload deliberately avoided per proposal OQ-1.
5. REC-007 explicitly drops `hover-lift transition-shadow` because `<UiCard variant="elevated">` already ships a tokenized `translateY(-2px)` hover with reduced-motion fallback.
