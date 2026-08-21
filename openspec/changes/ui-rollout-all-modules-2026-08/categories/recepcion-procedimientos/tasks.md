# Tasks: recepcion-procedimientos (ui-rollout-all-modules-2026-08)

> Single PR: `pr-recepcion-procedimientos-tokenise`. Smallest category (~25% of 400-line budget).

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `recepcion-procedimientos` |
| PR | `pr-recepcion-procedimientos-tokenise` (single) |
| Slice | `PR-recepcion-procedimientos-01` |
| Source file | `resources/js/modules/reception-procedures/ReceptionProceduresPage.vue` (180 lines) |
| Regression target | `resources/js/components/layout/AppLayout.vue` line 548 (`canvasRoutes`) + `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` (`EXPECTED_ROUTES`) |
| Base test class | `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (inherits DLR-R-001/002/004/021) |
| Strict TDD | true |
| Delivery | `auto-chain` (single PR; may stack inside global PR3) |
| Line estimate | ~60-80 authored + ~30 test lines |

---

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~90-110 (60-80 page + ~30 test) |
| 400-line budget risk | Low (~25% utilisation) |
| Chained PRs recommended | No (single PR) |
| Suggested split | single PR `pr-recepcion-procedimientos-tokenise` |
| Delivery strategy | auto-chain |
| Chain strategy | not-needed |
| Decision needed before apply | No |
| Generated CSS inclusion | N/A (no token/CSS changes) |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: not-needed
400-line budget risk: Low

### Suggested Work Unit

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Tokenise `ReceptionProceduresPage.vue` + regression-guard canvasRoutes | PR-recepcion-procedimientos-01 | `php artisan test --filter=ReceptionProceduresAppShellTest` | `pnpm build` + `phpunit tests/Feature/Api` | revert `ReceptionProceduresPage.vue` + new test file (atomic `git revert <merge-sha>`) |

---

## Phase 1: RED — write the new test FIRST

- [x] **T1**: Create `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `ReceptionProceduresPage.vue`. Add 7 REC-* test methods per `spec.md` §2 (each named after the rule, NOT the literal string — per vertical-slice archive lesson):
  - `test_canvas_routes_regression_guard_reception_procedures` (REC-001) — assert `AppLayout.vue` contains `'/reception-procedures'` literal AND `AppLayoutCanvasRoutesTest.php` `EXPECTED_ROUTES` contains it.
  - `test_search_input_uses_ui_input` (REC-002) — regex `<UiInput\b` present, raw `<input ... v-model="filters.search"` absent.
  - `test_specialty_filter_uses_ui_select` (REC-003) — regex `<UiSelect\b` present, raw `<select ... v-model="filters.specialty"` absent.
  - `test_procedure_code_chip_uses_ui_badge` (REC-004) — regex `<UiBadge\b` present, literals `bg-primary-50` and `text-primary-700` absent.
  - `test_empty_state_uses_ui_empty_state` (REC-005) — regex `<UiEmptyState\b` present, literal `py-12 text-center text-theme-secondary` absent.
  - `test_price_uses_system_blue_tabular_nums` (REC-006) — `text-systemBlue-600` + `tabular-nums` both present, literal `text-accent` absent.
  - `test_hairline_borders_and_no_hover_lift` (REC-007) — no `border-theme` literal, no `hover-lift transition-shadow` literal, `border-hairline` OR `var(--color-hairline)` present.
  - Inherited from `ModuleAppShellTestCase` (5 rules, no redefinition): DLR-R-001, DLR-R-002, DLR-R-004 pos+neg, DLR-R-021.
  - Expected: RED (failing — page is still on legacy classes).

## Phase 2: GREEN — apply template-level class-string replacements

- [x] **T2**: Apply REC-001 (canvasRoutes regression guard). Verify `'/reception-procedures'` IS at `AppLayout.vue` line 548 (PR0 already merged; this is a no-op verify). If absent, ADD it to `canvasRoutes` array literal. Same verify for `AppLayoutCanvasRoutesTest::EXPECTED_ROUTES`. NO edit expected.

- [x] **T3**: Apply REC-002 (`<UiInput>` adoption). Replace raw search `<input>` (lines 31-36) with `<UiInput v-model="filters.search" type="search" placeholder="Buscar por nombre o código...">`. Move the SVG search icon into a prefix slot (preserve `w-4 h-4 absolute left-3` positioning inside the slot per proposal §5 risk #1). Import `UiInput` at top of `<script setup>`. Expected: removes `border-theme`, `bg-theme-surface-elevated`, `focus:ring-primary-500`, `focus:border-accent`, `rounded-lg`, `text-theme-primary` in one edit (6 of 14 legacy occurrences).

- [x] **T4**: Apply REC-003 (`<UiSelect>` adoption). Replace raw specialty `<select>` (lines 54-62) with `<UiSelect v-model="filters.specialty">` keeping the `<option value="">Todas las especialidades</option>` placeholder and the `v-for="spec in specialties"` children. Import `UiSelect`. Confirms primitive supports `v-model` + `<option>` children (proposal §6 OQ-5).

- [x] **T5**: Apply REC-004 (`<UiBadge>` adoption). Replace inline procedure code chip (line 83: `<span class="font-mono text-xs px-2 py-0.5 rounded bg-primary-50 text-primary-700">`) with `<UiBadge variant="primary" size="sm" class="font-mono">`. Import `UiBadge`. Verify `font-mono` passthrough renders monospace digits in browser (proposal §6 OQ-2).

- [x] **T6**: Apply REC-005 (`<UiEmptyState>` adoption). Replace hand-rolled empty-results `<div class="py-12 text-center text-theme-secondary">No se encontraron procedimientos con los filtros aplicados</div>` (lines 71-73) with `<UiEmptyState title="Sin resultados" description="Ajusta los filtros para ver más procedimientos.">`. Import `UiEmptyState`. Title + description copy per proposal §6 OQ-4.

- [x] **T7**: Apply REC-006 (price display). Replace `text-lg font-bold text-accent` (line 103) with `text-lg font-bold text-systemBlue-600 tabular-nums`. Removes `text-accent` literal; adds `tabular-nums` for column-aligned currency digits (proposal §6 OQ-1).

- [x] **T8**: Apply REC-007 (hairline + drop hover-lift). (a) Replace `border-theme` literals (lines 35, 56 already gone via T3/T4; remaining line 94 `border-t border-theme pt-3 mt-3`) with `border-t border-hairline pt-3 mt-3`. (b) Drop `hover-lift transition-shadow` from `<UiCard variant="elevated">` (line 80) — `<UiCard>` primitive already ships `translateY(-2px)` hover with reduced-motion fallback (proposal §6 OQ-3; double-apply avoided).

## Phase 3: VERIFY

- [x] **T9**: Run `php artisan test --filter=ReceptionProceduresAppShellTest` — GREEN. All 7 REC-* assertions pass; 5 inherited DLR rules pass.

- [ ] **T10**: Run full regression sweep — `php artisan test --filter=AppLayoutCanvasRoutesTest` (REC-001 guard), `php artisan test --filter=ModuleAppShellTestCase` (sibling modules unaffected), `php artisan test` (all existing test methods stay green), `pnpm build` (no Tailwind purge regression), `phpunit tests/Feature/Api` (`/api/reception-procedures` contract preserved verbatim).

- [x] **T11**: Visual verification at 1440x900. Login as `recep@test.com` per `CREDENTIALS.md`. Navigate to `/reception-procedures`. Confirm: page surface is `rgb(242, 242, 247)`; cards lift off canvas; search input renders `<UiInput>` chrome; specialty filter renders `<UiSelect>`; procedure code chip is `<UiBadge>` with monospace; price renders blue tabular-nums; empty-state is `<UiEmptyState>` with Spanish copy. Save Playwright snapshot to `.playwright-cli/screenshots-rollout/reception-procedures-1440x900.png` (mobile capture NOT required per global OQ-8).

## Phase 4: COMMIT

- [x] **T12**: Conventional commit: `feat(ui): tokenise recepcion-procedimientos per DLR + REC-* MUST rows`.

- [ ] **T13**: Merge to main (single PR). Test count delta: +1 (new `ReceptionProceduresAppShellTest`); `AppLayoutCanvasRoutesTest` unchanged (REC-001 regression guard only, no new route).

## Out of scope (do NOT include in tasks)

- Status pill (page has no status pills — `<UiBadge variant="primary">` is correct per proposal OQ-1).
- New primitives / new tokens (`tokens.js` is frozen).
- `<script>` block edits (composables `useProcedureCatalog` + `useSpecialties` contracts preserved byte-for-byte).
- Dark mode / accessibility audit (out of the entire rollout).
- Per-KPI sparklines, BI visuals, CashRegister internals (out of the entire rollout).
- Mobile-first Playwright capture (not a mobile-first surface per global OQ-8).

---

## Key Learnings

1. REC-001 is a regression guard, NOT an additive change — `/reception-procedures` is already at `AppLayout.vue` line 548 and in `AppLayoutCanvasRoutesTest::EXPECTED_ROUTES` (PR0 merge); T2 is a no-op verify.
2. The 14 legacy class occurrences across 8 distinct strings collapse to 6 edits; the raw `<input>` and `<select>` swap (T3+T4) alone eliminates 6 occurrences (`border-theme` ×2, `bg-theme-surface-elevated` ×2, `focus:ring-primary-500` ×2, `focus:border-accent` ×2, `rounded-lg` ×2, `text-theme-primary` ×2) — highest-impact single edit.
3. REC-007 explicitly drops `hover-lift transition-shadow` because `<UiCard variant="elevated">` (tokenised in PR2) already ships `translateY(-2px)` hover with reduced-motion fallback; stacking the class would double-apply the transform.
4. REC-006 uses `text-systemBlue-600 tabular-nums` NOT `<UiBadge variant="success">` — prices are NOT status indicators; semantic overload avoided (proposal OQ-1 resolution).
5. Test count delta is +1, not +2 — `AppLayoutCanvasRoutesTest` stays unchanged (REC-001 is regression guard only, route already wired by PR0).
