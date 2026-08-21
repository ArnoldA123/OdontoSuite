# Tasks: mis-procedimientos (ui-rollout-all-modules-2026-08)

> Single PR: `pr-mis-procedimientos-tokenise`. TDD-ordered: tests first, implementation second.

## Metadata
| Key | Value |
|---|---|
| Change | ui-rollout-all-modules-2026-08 |
| Category | mis-procedimientos |
| Date | 2026-08-21 |
| Phase | tasks (4 of 6) |
| PR | pr-mis-procedimientos-tokenise (single PR) |

## Review Workload Forecast
| Field | Value |
|-------|-------|
| Estimated changed lines | ~280-330 (template ~220 + test ~80) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR |
| Delivery strategy | single-pr |
| Chain strategy | size-exception (per proposal §7, line estimate fits budget) |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Low

### Suggested Work Unit

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Tokenise `MyProceduresPage.vue` per all 14 MIS-* MUST rows | PR-mis-procedimientos-01 | `vendor/bin/phpunit tests/Unit/DesignSystem/MyProceduresPageAppShellTest.php` | `php artisan test --testsuite=Unit` | revert `MyProceduresPage.vue` + delete `MyProceduresPageAppShellTest.php`; composable, API, routing, `<script>` untouched |

## Phase 1: RED — write the new test FIRST

- [x] **T1**: Create `tests/Unit/DesignSystem/MyProceduresPageAppShellTest.php` extending `ModuleAppShellTestCase`. Implement 14 assertions mapped to MIS-001..MIS-014:
  - `test_no_border_theme_literal` (MIS-001, DLR-R-002) — `border-theme` count == 0; `divide-theme` count == 0.
  - `test_search_uses_ui_input` (MIS-002) — `<UiInput v-model="search"` present; raw `<input` with `class="...focus:ring-primary-500..."` absent.
  - `test_format_currency_used_for_pen_values` (MIS-003) — `formatCurrency(` referenced ≥ 2 times; `Intl.NumberFormat` absent from template.
  - `test_tabular_nums_on_numeric_cells` (MIS-004) — `tabular-nums` OR `font-feature-settings: var(--font-features-tabular-nums)` appears ≥ 6 times.
  - `test_loading_spinner_import_is_ui_prefixed` (MIS-005) — `import UiLoadingSpinner` present; `import LoadingSpinner` and bare `<LoadingSpinner` absent.
  - `test_disabled_opacity_uses_ios_parity` (MIS-006) — `disabled:opacity-40` appears ≥ 2 times; `disabled:opacity-30` count == 0.
  - `test_code_badges_use_system_sans_with_tabular_nums` (MIS-007) — `font-mono` count == 0; `tabular-nums` present on both code-badge spans.
  - `test_empty_states_consume_ui_empty_state` (MIS-008) — `<UiEmptyState` appears ≥ 2 times; legacy `border-2 border-dashed border-theme` literal absent.
  - `test_raw_icon_buttons_consume_focus_ring_token` (MIS-009) — `var(--focus-ring-default)` appears ≥ 3 times; `focus:ring-primary-500` absent (DLR-R-004 re-asserted).
  - `test_order_pill_uses_tokenized_blue_ramp` (MIS-010) — `bg-systemBlue-50 text-systemBlue-700` present.
  - `test_yellow_ramp_is_systemyellow` (MIS-010) — `text-systemYellow-500` present; raw `text-yellow-500` absent.
  - `test_remove_favorite_uses_systemred_ramp` (MIS-010) — `text-systemRed-500` present; raw `text-red-500` absent.
  - `test_hover_surface_uses_canvas_token` (MIS-011) — `hover:bg-canvas` present; raw `hover:bg-theme-surface` absent; `bg-theme-surface-elevated` retained.
  - `test_radius_uses_contextual_tokens` (MIS-012) — `var(--radius-control)` OR `var(--radius-ios)` OR `var(--radius-card-lg)` appears ≥ 4 times; bare `rounded-lg` count == 0.
  - `test_script_setup_unchanged` (MIS-013) — assert file's `<script setup>` block content hash matches pre-PR baseline; `ComposablesStandardizationTest` stays green.
- [x] **T1a**: Run `vendor/bin/phpunit tests/Unit/DesignSystem/MyProceduresPageAppShellTest.php` — must RED (all 14 assertions fail against current `MyProceduresPage.vue`).

## Phase 2: GREEN — apply template-level class-string replacements

- [x] **T2** (MIS-001): Replace `border-theme` literals at lines 39, 49, 138, 161 with `border-[color:var(--color-hairline)]`; replace `divide-theme` at line 161 with `divide-[color:var(--color-hairline)]`. Empty-state border at line 39 becomes `border-2 border-dashed border-[color:var(--color-hairline)]`.
- [x] **T3** (MIS-002): Replace raw `<input>` at line 134 with `<UiInput v-model="search" ...>`. Preserve `<div class="relative">` wrapper and the `<svg>` icon inset at `left-3`; pass through padding class for `pl-9`.
- [x] **T4** (MIS-003): Replace both inline `S/ {{ Number(...).toFixed(2) }}` literals at lines 65 and 178 with `{{ formatCurrency(...) }}`. Add `formatCurrency` to the `useFormatters` destructure in the `<script setup>` block (script touch is one-line, no behavior change).
- [x] **T5** (MIS-004): Add `tabular-nums` to the 6 numeric spans: 3 numerics × 2 cards (`default_duration_minutes`, `default_cost`, `position`). Insert after the existing `text-theme-secondary` class on each span.
- [x] **T6** (MIS-005): Rename `LoadingSpinner` import at line 211 to `UiLoadingSpinner`; rename both tag references at lines 34 and 156 to `<UiLoadingSpinner />`.
- [x] **T7** (MIS-006): Replace `disabled:opacity-30` at lines 72 and 89 with `disabled:opacity-40` (iOS parity with `<UiButton>`).
- [x] **T8** (MIS-007): Drop `font-mono` at lines 53 and 170; replace each with `text-xs text-theme-secondary tabular-nums` (system sans + tabular numerals, kept consistent with MIS-004).
- [x] **T9** (MIS-008): Replace hand-built empty states (lines 38-43, no favourites; lines 192-196, no search results) with `<UiEmptyState>` slots carrying the same copy.
- [x] **T10** (MIS-009): Add `:focus-visible` + `box-shadow: var(--focus-ring-default)` (or `focus-visible:shadow-[var(--focus-ring-default)]` class string) to each of the 3 raw icon `<button>`s at lines 69-118 (subir / bajar / quitar). Keep raw markup — no `<UiButton size="icon">` migration (per cached OQ-2).
- [x] **T11** (MIS-010): Tokenize status ramps — replace `bg-primary-50 text-primary-700` (line 56) with `bg-systemBlue-50 text-systemBlue-700`; replace `text-yellow-500` (lines 28, 171) with `text-systemYellow-500`; replace `text-red-500 hover:text-red-700` (line 105) with `text-systemRed-500 hover:text-systemRed-700`.
- [x] **T12** (MIS-011, MIS-012): Replace `hover:bg-theme-surface` at line 166 with `hover:bg-canvas`. Replace 4 `rounded-lg` literals at lines 39, 49, 138, 161 with contextual radius tokens — input → `rounded-[var(--radius-control)]`; cards → `rounded-[var(--radius-ios)]` (or `rounded-[var(--radius-card-lg)]` per design language). MIS-013/MIS-014 covered by verification.

## Phase 3: VERIFY

- [x] **T13**: Run `vendor/bin/phpunit tests/Unit/DesignSystem/MyProceduresPageAppShellTest.php` — must be GREEN (all 14 assertions pass).
- [x] **T14**: Run full DesignSystem suite — `vendor/bin/phpunit tests/Unit/DesignSystem/` — no regressions (DLR-R-001/002/004/021 base rules stay green; `ModuleAppShellTestCase` data-provider rows stay green).
- [x] **T15**: Run `pnpm build` — no regressions; no new bundler warnings.
- [x] **T16**: Run `vendor/bin/phpunit tests/Feature/Api` — no backend regressions (`ComposablesStandardizationTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest` stay green).
- [x] **T17**: Visual verification at 1440x900 — login as `odonto@test.com`, screenshot to `.playwright-cli/screenshots-rollout/my-procedures-1440x900.png` (gitignored). Confirm canvas surface, hairline borders, focus rings, search-icon inset, and tabular numerals render correctly.
- [x] **T17a**: Confirm `git diff --stat` reports `additions + deletions <= 400` (MIS-014). If exceeded, halt and split per `chained-pr` skill BEFORE review starts.

## Phase 4: COMMIT

- [x] **T18**: Conventional commit: `feat(ui): tokenise my-procedimientos per DLR + MIS-* MUST rows`. Squash or rebase per project convention. Do NOT add Co-Authored-By lines.
- [x] **T19**: Merge to main per `auto-chain` delivery strategy. Confirm CI gates (`quality`, `backend-tests`, `frontend-build`) green.

## Out of scope (do NOT include in tasks)
- `<UiStatusBadge>` adoption for rank pill (counter, not state) — per cached OQ-4.
- `<UiButton size="icon">` migration for raw icon-buttons — per cached OQ-2.
- `useProcedureFavorites` pagination — per cached OQ-3.
- Dark mode / per-KPI sparklines / new tokens / new primitives — global out-of-scope.

## Notes on test method naming
- Use camelCase PHPUnit method names in the actual file (e.g. `testBorderThemeLiteralReplaced`, not `test_no_border_theme_literal`). The base class's `polishedFileProvider` enumerates the single `MyProceduresPage.vue` path so a failure pinpoints the row.

## Key Learnings

1. `useProcedureFavorites` is fetch-only — no realtime risk; UI-only tokenization with zero `<script>` business-logic edits.
2. `formatCurrency` from `useFormatters` is the single source of truth for PEN rendering; the `<script>` block needs one destructure addition to import it.
3. Raw icon-buttons keep raw markup per cached OQ-2 — only `:focus-visible` + `var(--focus-ring-default)` is added.
