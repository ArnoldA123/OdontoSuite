# Spec: recepcion-procedimientos Category Delta (ui-rollout-all-modules-2026-08)

> Smallest Tier-1 category. Single PR.

## Metadata
| Key | Value |
|---|---|
| Change | ui-rollout-all-modules-2026-08 |
| Category | recepcion-procedimientos |
| Date | 2026-08-21 |
| Phase | spec (3 of 6) |
| Parent spec | openspec/specs/design-language-rollout/spec.md |
| Slice | PR-recepcion-procedimientos-01 |
| Delivery | auto-chain (single PR; may stack inside global PR3) |
| Review budget | 400 authored lines / PR (~25% utilisation) |
| Strict TDD | true |

## 1. Scope

### 1.1 Files to modify
- `resources/js/components/layout/AppLayout.vue` — regression guard only; `/reception-procedures` is already present in the `canvasRoutes` array (line 548); this PR MUST NOT remove it.
- `resources/js/modules/reception-procedures/ReceptionProceduresPage.vue` — full template tokenisation per proposal §2.1 (~60-80 line edits).
- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` — regression guard only; `/reception-procedures` is already present in `EXPECTED_ROUTES`; this PR MUST NOT remove it.
- `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` — NEW. Extends `ModuleAppShellTestCase`. Asserts the per-module rule on `ReceptionProceduresPage.vue`.

### 1.2 Files NOT to modify
- `resources/js/composables/useProcedureCatalog.js` — frozen.
- `resources/js/composables/useSpecialties.js` — frozen.
- `resources/js/app.js` — router already registered.
- `resources/js/components/ui/{Card,Button,Input,Badge,EmptyState,Pagination,LoadingSpinner,Select}.vue` — primitives already tokenised in PR2; this category only consumes them.

### 1.3 Backend changes
NONE. `/api/reception-procedures` endpoint contract preserved verbatim.

## 2. Requirements

### 2.1 [REC-001] canvasRoutes regression guard
`'/reception-procedures'` MUST remain present in the `canvasRoutes` array literal in `AppLayout.vue` AND in `EXPECTED_ROUTES` of `AppLayoutCanvasRoutesTest.php`. The route was added at PR0 (line 548 of `AppLayout.vue`); this PR MUST NOT narrow the array back to the vertical-slice set.
Test: `AppLayoutCanvasRoutesTest::test_each_expected_route_is_in_canvas_routes` (data provider row `'/reception-procedures'`) AND `AppLayoutCanvasRoutesTest::test_no_legacy_narrowing_to_vertical_slice_routes`.

### 2.2 [REC-002] `<UiInput>` adoption for search
`ReceptionProceduresPage.vue` MUST replace the raw `<input>` (current line 31-36) with `<UiInput v-model="filters.search" type="search" placeholder="Buscar por nombre o código...">` plus a prefix slot for the SVG search icon. No raw `<input v-model="filters.search"` string MAY remain in the template.
Test: `ReceptionProceduresAppShellTest::test_search_input_uses_ui_input` (regex asserts `<UiInput\b` present and the raw `<input ... v-model="filters.search"` pattern absent).

### 2.3 [REC-003] `<UiSelect>` adoption for specialty filter
`ReceptionProceduresPage.vue` MUST replace the raw `<select>` (current line 54-62) with `<UiSelect v-model="filters.specialty">` plus an `<option value="">` placeholder for "Todas las especialidades". No raw `<select v-model="filters.specialty"` string MAY remain in the template.
Test: `ReceptionProceduresAppShellTest::test_specialty_filter_uses_ui_select` (regex asserts `<UiSelect\b` present and the raw `<select ... v-model="filters.specialty"` pattern absent).

### 2.4 [REC-004] `<UiBadge>` adoption for procedure code chip
`ReceptionProceduresPage.vue` MUST replace the inline `<span class="font-mono text-xs px-2 py-0.5 rounded bg-primary-50 text-primary-700">` (current line 83) with `<UiBadge variant="primary" size="sm" class="font-mono">`. The inline `bg-primary-50 text-primary-700` literals MUST be removed.
Test: `ReceptionProceduresAppShellTest::test_procedure_code_chip_uses_ui_badge` (regex asserts `<UiBadge\b` present and the literal `bg-primary-50` / `text-primary-700` absent).

### 2.5 [REC-005] `<UiEmptyState>` adoption
`ReceptionProceduresPage.vue` MUST replace the inline `<div class="py-12 text-center text-theme-secondary">No se encontraron procedimientos con los filtros aplicados</div>` (current line 71-73) with `<UiEmptyState title="Sin resultados" description="Ajusta los filtros para ver más procedimientos.">`. The `py-12 text-center text-theme-secondary` empty-results literal MUST be removed.
Test: `ReceptionProceduresAppShellTest::test_empty_state_uses_ui_empty_state` (regex asserts `<UiEmptyState\b` present and the `py-12 text-center text-theme-secondary` literal absent).

### 2.6 [REC-006] Price display: `text-systemBlue-600 tabular-nums`
The price text node (current line 103: `<div class="text-lg font-bold text-accent">S/ {{ Number(proc.default_cost).toFixed(2) }}</div>`) MUST consume `text-systemBlue-600 tabular-nums` in place of `text-accent`. The legacy `text-accent` literal MUST be removed from the page template.
Test: `ReceptionProceduresAppShellTest::test_price_uses_system_blue_tabular_nums` (regex asserts `text-systemBlue-600` + `tabular-nums` both present, and `text-accent` absent).

### 2.7 [REC-007] Hairline borders + drop `hover-lift`
All `border-theme` literals (current lines 35, 56, 94) MUST be removed. The `hover-lift transition-shadow` class string on `<UiCard variant="elevated">` (current line 80) MUST be dropped — the proven primitive hover takes over. The `border-t border-theme` divider inside each procedure card MUST consume the hairline token via `border-hairline` or `border-[color:var(--color-hairline)]`.
Test: `ReceptionProceduresAppShellTest::test_hairline_borders_and_no_hover_lift` (regex asserts no `border-theme` literal AND no `hover-lift transition-shadow` literal; `border-hairline` OR `var(--color-hairline)` present).

## 3. Inherited MUST (re-asserted)

Every row below is inherited unmodified from the parent DLR spec (`openspec/specs/design-language-rollout/spec.md`) and applies to `ReceptionProceduresPage.vue`. The base class `ModuleAppShellTestCase` already pins them; the new per-module test extends the base class and inherits them without redefinition.

| ID | Rule | Inherited test |
|---|---|---|
| DLR-R-001 | Page surface references `bg-canvas` / `var(--color-canvas)` / `rgb(242, 242, 247)`. | `ModuleAppShellTestCase::test_page_references_canvas_token` |
| DLR-R-002 | No `border-theme` literal. (REC-007 re-asserts this rule.) | `ModuleAppShellTestCase::test_no_legacy_border_theme_literal` |
| DLR-R-004 (neg) | No `focus:ring-primary-500` / `focus:border-accent` literals. (REC-002/REC-003 subsume via primitive adoption.) | `ModuleAppShellTestCase::test_no_legacy_focus_ring_alias` |
| DLR-R-004 (pos) | If `:focus` / `:focus-visible` is present, must consume `var(--focus-ring-default)`. | `ModuleAppShellTestCase::test_focus_ring_consumes_token` |
| DLR-R-021 | No `<style scoped>` block. (N/A today; no block exists; guard extended.) | `ModuleAppShellTestCase::test_no_style_scoped` |

## 4. Out of scope

Mirrors the proposal §3. Items are excluded from this rollout and explicitly recorded so the apply phase does NOT silently resolve them.

- Dark mode (out of the entire rollout).
- Accessibility audit beyond incidental color contrast (out of the entire rollout).
- `<script>` blocks of `ReceptionProceduresPage.vue` — UI changes are template-only; `useProcedureCatalog` + `useSpecialties` contracts preserved byte-for-byte.
- New tokens / new primitives — `tokens.js` is frozen for the rollout; all needs covered by existing primitives + iOS label ramps.
- Mobile-first Playwright capture — not a mobile-first surface per global OQ-8; only the desktop 1440x900 snapshot is mandatory.
- Calendar FullCalendar internals, CashRegister `<script>` blocks, BI visuals, Per-KPI sparklines, two-tone numerals, Calendario internals — out of the entire rollout.
- `<UiStatusBadge>` — NOT needed here; procedure code chips are informational, not status (`<UiBadge variant="primary">` is correct per proposal OQ-1).

## 5. Acceptance criteria

The category is considered complete when ALL of the following hold:

- `/reception-procedures` renders on `var(--color-canvas)` page surface; cards lift off the canvas (DLR-R-001 + REC-001).
- All 14 legacy class occurrences (`border-theme` ×3, `bg-theme-surface-elevated` ×2, `focus:ring-2` ×2, `focus:ring-primary-500` ×2, `focus:border-accent` ×2, `rounded-lg` ×2, `bg-primary-50` ×1, `text-primary-700` ×1, `text-accent` ×1, `text-theme-primary` ×3, `text-theme-secondary` ×3) removed from the template.
- `<UiInput>`, `<UiSelect>`, `<UiBadge>`, `<UiEmptyState>` primitives adopted for filter input, specialty filter, procedure code chip, and empty-results state respectively (REC-002..REC-005).
- Price display uses `text-systemBlue-600 tabular-nums` (REC-006).
- No `hover-lift transition-shadow` on procedure cards; hairline token consumed on dividers (REC-007).
- `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` green; asserts the rule (token reference, alias absence, primitive adoption, `tabular-nums`), not literal strings.
- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` stays green (REC-001 regression guard).
- All existing PHPUnit invariants stay green: `TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `ModuleAppShellTestCase`-derived tests.
- CI green: `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).
- Playwright snapshot at 1440x900 saved to `.playwright-cli/screenshots-rollout/reception-procedures-1440x900.png`. Credentials: `recep@test.com` per `CREDENTIALS.md`.
- Test count delta: +1 (new `ReceptionProceduresAppShellTest`); `AppLayoutCanvasRoutesTest` unchanged.

## 6. References

| File | Why it matters |
|---|---|
| `categories/recepcion-procedimientos/explore.md` (122 lines) | Primary input: inventory, current state, gap analysis, OQs |
| `categories/recepcion-procedimientos/proposal.md` (210 lines) | Source of intent, scope, risk register, OQ resolutions |
| `openspec/specs/design-language-rollout/spec.md` | Parent spec; DLR-R-001/002/004/021 re-asserted in §3 |
| `resources/js/modules/reception-procedures/ReceptionProceduresPage.vue` (180 lines, 5.2 KB) | Single source file for the rollout |
| `resources/js/components/layout/AppLayout.vue` (line 548 canvasRoutes) | Regression-guard target (already wired) |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Base class for the new per-module test (5 inherited rules) |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Regression-guard test for REC-001 (line 54 `EXPECTED_ROUTES`) |
| `tests/Unit/DesignSystem/CajaPagesAppShellTest.php` | Sibling pattern for per-module assertions + class-string scan helper |
| `resources/js/components/ui/{Card,Button,Input,Badge,EmptyState,Pagination,LoadingSpinner,Select}.vue` | Primitives already tokenised in PR2 |
| `resources/js/composables/{useProcedureCatalog,useSpecialties}.js` | Frozen composables; NOT touched |
| `openspec/config.yaml` | Preflight cache: pace=auto, artifact_store=hybrid, delivery=auto-chain, review_budget=400, strict_tdd=true |

## Key Learnings

1. The category is the smallest in the project — single 180-line Vue file, no scoped styles, no realtime, no third-party widget. ~25% of the 400-line review budget; cheapest possible rollout.
2. `/reception-procedures` is already in `canvasRoutes` (PR0 merge) and already in `AppLayoutCanvasRoutesTest::EXPECTED_ROUTES`. REC-001 is a regression guard, not an additive change — same pattern as Pagos/Citas/Pacientes closed categories.
3. The page reads 14 legacy class occurrences across 8 distinct strings; the highest-impact fix is the raw `<input>` and `<select>` swap to `<UiInput>`/`<UiSelect>`, which simultaneously eliminates `border-theme`, `bg-theme-surface-elevated`, `focus:ring-primary-500`, `focus:border-accent`, and `rounded-lg` in two edits.
4. REC-006 (price display) re-asserts the iOS pattern of `text-systemBlue-600 tabular-nums` for currency; NOT `<UiBadge>` (per proposal OQ-1 resolution — prices are NOT status indicators).
5. REC-007 explicitly drops `hover-lift transition-shadow` because `<UiCard variant="elevated">` (tokenised in PR2) already ships `translateY(-2px)` hover with reduced-motion fallback; stacking a class on top would double-apply the transform.
