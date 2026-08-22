# Spec: `estadisticas-catalogo` Category Delta (ui-rollout-all-modules-2026-08)

> Recommended second proving ground (slice 1 per global §6.2).
> Includes BLOCKING router fix (OQ-EC-1).

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `estadisticas-catalogo` (AGENTS.md §5 #17) |
| Date | 2026-08-21 |
| Phase | spec (3 of 6) — category slice |
| Parent spec | `openspec/specs/design-language-rollout/spec.md` |
| Parent proposal | `openspec/changes/ui-rollout-all-modules-2026-08/categories/estadisticas-catalogo/proposal.md` |
| Slice | PR1 (`pr1-estadisticas-catalogo-router-and-tokenise`) |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/estadisticas-catalogo/spec`) |
| Strict TDD | `true` |

---

## 1. Scope

Files touched by PR1 (additive or tokenisation only):

| File | Touch scope |
|---|---|
| `resources/js/app.js` | **Additive** `/procedure-stats` route (~5 lines, between `/procedure-catalog/:id` and `/my-procedures`). No existing route mutated. |
| `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | Tokenise (hairline, elevation-2, tabular-nums, systemGreen for "Activos", systemRed error banner), adopt `<PageHeader>`, adopt `<UiEmptyState>`, adopt `<UiSkeleton>`, adopt `formatPENLabel` from `useFormatters`, add inline role disclosure, add loading skeleton block. |
| `tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` | **NEW.** Extends `ModuleAppShellTestCase` (5 inherited assertions) + 5 category-specific assertions. |

Files NOT modified (out of scope for this slice): `routes/api.php`, `AppLayout.vue`, the sibling `procedure-catalog/{ProcedureCatalogPage,ProcedureCatalogDetailPage,ProcedureCatalogFormModal}.vue`, all backend code. PR3 rides the sibling files.

---

## 2. Requirements

### 2.1 `[EC-001]` — **CRITICAL** Router registration for `/procedure-stats`

The system MUST register `/procedure-stats` in `resources/js/app.js` between the `/procedure-catalog/:id` route block (current lines 113–118) and the `/my-procedures` route block (current lines 119–124). The route entry MUST follow the exact sibling pattern: `{ path: '/procedure-stats', name: 'procedure-stats', component: () => import('./modules/procedure-catalog/ProcedureStatsPage.vue'), beforeEnter: requireAuth }`. The route MUST carry `beforeEnter: requireAuth` (the same auth gate the sibling routes use) and MUST NOT be placed inside the 404 catch-all block.

#### Scenario: `EC-001-1` — `/procedure-stats` is reachable via the auth gate

- GIVEN `resources/js/app.js` defines the `routes[]` array with the existing 17 module routes + 2 settings + 404 catch-all
- AND `AppLayout.vue:canvasRoutes` already includes `/procedure-stats` (PR0 landed)
- WHEN the apply phase inserts the `/procedure-stats` route block between the `/procedure-catalog/:id` and `/my-procedures` blocks
- THEN `git grep -n "procedure-stats" resources/js/app.js` returns at least one match (the route entry path + name)
- AND a new `appRoutes_contain_procedure_stats_test.php` (or extension of an existing router test) asserts the route object literal is present with `beforeEnter: requireAuth`
- AND visual verification: logging in as `admin@test.com` (or `finanzas@test.com`) and visiting `/procedure-stats` renders `ProcedureStatsPage.vue` (not the 404 catch-all)

### 2.2 `[EC-002]` — KPI anatomy adoption (3 counter cards)

The system MUST apply the Dashboard KPI anatomy to each of the 3 KPI `<UiCard>` elements on `ProcedureStatsPage.vue` (Total procedimientos, Activos, Inactivos, currently at lines 47–72). Each card MUST carry: (a) `:style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"` inline binding; (b) `data-stat-card="<key>"` attribute (`total-procedures`, `active`, `inactive`); (c) a fixed-slot grid reserving `h-4` (eyebrow) / `h-12` (number) / `h-6 min-h-[24px]` (chip slot, empty when no delta) / `h-4` (caption); (d) a numeric `<p>` element using `tabular-nums` AND `style="font-feature-settings: var(--font-features-tabular-nums)"` AND `text-5xl font-bold text-label`.

#### Scenario: `EC-002-1` — Each KPI card matches the Dashboard contract

- GIVEN the Dashboard contract from `DashboardAppShellTest::test_dashboard_stat_cards_use_fixed_slot_grid`, `test_dashboard_kpi_cards_consume_hairline_and_elevation`, and `test_dashboard_five_stat_cards_carry_data_stat_card_attribute`
- WHEN PR1 lands
- THEN `ProcedureStatsAppShellTest::test_kpi_anatomy_matches_dashboard` asserts each of the 3 KPI `<UiCard>` elements carries `data-stat-card="..."` AND `var(--color-hairline)` AND `var(--elevation-2)` AND the four reserved slots in the exact order `h-4 → h-12 → h-6 → h-4`

### 2.3 `[EC-003]` — `<PageHeader>` adoption (removes page-level `<h1>` defect)

The system MUST replace the custom header block (current lines 3–17 of `ProcedureStatsPage.vue`) with `<PageHeader title="Estadísticas de Procedimientos" :subtitle="...">`. The `<h1 class="text-3xl font-bold text-theme-primary mb-2">` element MUST be removed entirely (it competes with the `AppLayout` topbar `<h1>`, defect 7 family). The page source MUST declare zero `<h1>` tags after the change.

#### Scenario: `EC-003-1` — Page-level `<h1>` is gone, `<PageHeader>` is present

- GIVEN the current page header at lines 3–17 carries a `<h1 class="text-3xl font-bold text-theme-primary mb-2">`
- WHEN PR1 lands
- THEN the rendered template contains `<PageHeader` (and the bound title + subtitle)
- AND `grep -n "<h1" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns zero matches
- AND `ProcedureStatsAppShellTest::test_page_header_replaces_h1` asserts both: `<PageHeader` is present, `<h1` is absent

### 2.4 `[EC-004]` — `<UiEmptyState>` + `<UiSkeleton>` adoption

The system MUST replace the ad-hoc empty `<div>` at lines 81 and 129 with `<UiEmptyState title="Sin datos" description="No hay datos para el período seleccionado." />`. The system MUST introduce a loading skeleton block (currently absent — the page renders blank during fetch) that mirrors the Dashboard pattern: 3 `<UiSkeleton variant="card">` for KPI counters + 6 `<UiSkeleton variant="list">` for table + specialty rows, all inside a wrapper carrying `aria-busy="true"` AND `aria-live="polite"`.

#### Scenario: `EC-004-1` — Empty states and loading skeleton are primitive-driven

- GIVEN `ProcedureStatsPage.vue` renders two ad-hoc `<div class="text-center py-8 text-theme-secondary">` empty blocks (lines 81, 129) and no loading state
- WHEN PR1 lands
- THEN both empty blocks consume `<UiEmptyState title="Sin datos" ...>` (≥2 instances)
- AND the loading branch renders ≥3 `<UiSkeleton variant="card">` AND ≥6 `<UiSkeleton variant="list">`, wrapped in a container carrying `aria-busy="true"` AND `aria-live="polite"`
- AND `ProcedureStatsAppShellTest::test_empty_states_and_loading_skeleton` asserts all four counts + the aria attributes

### 2.5 `[EC-005]` — `formatPENLabel` swap on currency cells

The system MUST replace the inline `.toFixed(2)` calls at line 117 (`{{ proc.total_revenue.toFixed(2) }}`) and line 142 (`S/ {{ spec.total_revenue.toFixed(2) }}`) with `formatPENLabel` from `@/composables/useFormatters`. The `<script setup>` block MUST import `formatPENLabel` (no other imports added, no new computed, no new state). The currency cells MUST emit the `S/` prefix via the formatter (not via inline `S/` concatenation in the template).

#### Scenario: `EC-005-1` — No inline `.toFixed(2)` remains on currency cells

- GIVEN `formatPENLabel` exists at `resources/js/composables/useFormatters.js` (per PAGOS-MNY-002)
- WHEN PR1 lands
- THEN `grep -n "toFixed(2)" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns zero matches
- AND `<script setup>` declares `import { formatPENLabel } from '@/composables/useFormatters'`
- AND `ProcedureStatsAppShellTest::test_format_pen_label_consumed` asserts both: the import exists AND no `.toFixed(2)` literal remains in the source

### 2.6 `[EC-006]` — Tokenisation of `text-green-600` and raw red ramps

The system MUST replace the raw Tailwind classes: (a) `text-green-600` at line 60 → `text-systemGreen-600`; (b) the raw `bg-red-50 border border-red-200 text-red-700` block at line 147 → `bg-systemRed-50 border border-systemRed-200 text-systemRed-700`. The replacement MUST use the tokenised systemGreen / systemRed ramps from `resources/js/design-system/tokens.js` (NOT raw Tailwind palette classes).

#### Scenario: `EC-006-1` — Raw green/red ramps are tokenised

- GIVEN the "Activos" KPI carries `text-green-600` (line 60) and the error banner carries `bg-red-50 border-red-200 text-red-700` (line 147)
- WHEN PR1 lands
- THEN `grep -nE "text-green-600|bg-red-50|border-red-200|text-red-700" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns zero matches
- AND `text-systemGreen-600` is present (≥1 occurrence) and `bg-systemRed-50 border-systemRed-200 text-systemRed-700` is present on the error block
- AND `ProcedureStatsAppShellTest::test_no_raw_green_or_red_ramps` asserts the rule

### 2.7 `[EC-007]` — Hairline borders on table + specialty tile

The system MUST replace `border-theme` literals at lines 85, 99, and 136 with the hairline token. The table `<thead>` and `<tr>` row dividers MUST use `border-[color:var(--color-hairline)]` (or the equivalent tokenised form). The specialty tile wrapper at line 136 MUST use `rounded-[var(--radius-control)]` (8 px) for the corner radius (replacing `rounded-lg`) AND `border-[color:var(--color-hairline)]` for the border.

#### Scenario: `EC-007-1` — Legacy `border-theme` literals are gone, hairlines tokenised

- GIVEN `border-theme` appears at lines 85, 99, and 136 (3 occurrences per the category `explore.md` §1 file inventory)
- WHEN PR1 lands
- THEN `grep -n "border-theme" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns zero matches (inherited from `ModuleAppShellTestCase::test_no_legacy_border_theme_literal`)
- AND the specialty tile wrapper carries `rounded-[var(--radius-control)]` AND `border-[color:var(--color-hairline)]`
- AND `ProcedureStatsAppShellTest::test_specialty_tile_uses_hairline_and_radius_control` asserts both classes are present on the tile wrapper

### 2.8 `[EC-008]` — Inline role disclosure banner

The system MUST render an inline role disclosure at the page top (above the filter card): the visible text MUST read "Visible para: Administrador, Finanzas" (matching the `role:administrador,role:finanzas` middleware on `routes/api.php:189`). The disclosure MUST live inside a small `<div class="text-xs text-theme-secondary">` (NOT a `<UiStatusBadge>` — role disclosure is not a status pill, and `<RoleBanner>` primitive extraction is deferred until a second role-restricted module slice arrives, per OQ-EC-3). The disclosure MUST NOT block content layout (no full-width banner).

#### Scenario: `EC-008-1` — Role disclosure is visible at page top

- GIVEN the page is gated by the `administrador` and `finanzas` role middleware on `GET /api/admin/procedure-stats` (`routes/api.php:189`)
- WHEN PR1 lands
- THEN the rendered template contains the literal Spanish text "Visible para: Administrador, Finanzas" (case-insensitive substring match)
- AND the disclosure text lives inside an element carrying the `text-xs text-theme-secondary` class binding
- AND `ProcedureStatsAppShellTest::test_role_disclosure_present` asserts both the literal text and the class binding exist

### 2.9 `[EC-009]` — `tabular-nums` + `font-feature-settings` on 8 numeric elements

The system MUST apply the standing numeric contract (per `DashboardAppShellTest::test_dashboard_stat_card_numbers_are_tabular_nums` and the design contract) to every numeric element in the page: 3 KPI counters (Total procedimientos, Activos, Inactivos) + 3 table numerics (Usos, Cantidad total, Ingresos S/, at lines 110–117) + 2 specialty numerics (usos + S/, at lines 141–142). Each numeric element MUST carry BOTH the `tabular-nums` Tailwind utility class AND `style="font-feature-settings: var(--font-features-tabular-nums)"` — either alone is a contract violation.

#### Scenario: `EC-009-1` — All 8 numeric elements carry the paired tabular contract

- GIVEN the standing contract requires `tabular-nums` AND `font-feature-settings: var(--font-features-tabular-nums)` together (Dashboard pattern)
- WHEN PR1 lands
- THEN `ProcedureStatsAppShellTest::test_tabular_nums_on_all_numerics` asserts the count of `<p>` (or `<td>`) elements carrying BOTH `tabular-nums` AND `font-feature-settings: var(--font-features-tabular-nums)` is ≥8
- AND any numeric element carrying one but not the other fails the test (paired-only rule)

---

## 3. Inherited MUST (re-asserted)

The following MUST rows are inherited verbatim from the parent `design-language-rollout/spec.md` and apply to PR1 unmodified (each is the inherited base contract, not a delta):

- `DLR-R-001` — Canvas surface: `ProcedureStatsPage.vue` references `bg-canvas`, `var(--color-canvas)`, or `rgb(242, 242, 247)`.
- `DLR-R-002` — Hairline: page source does NOT contain the `border-theme` literal (enforced by EC-007 + the inherited `ModuleAppShellTestCase` assertion).
- `DLR-R-004` — Focus ring: if any `:focus` / `:focus-visible` selector appears, it MUST consume `var(--focus-ring-default)`; no `focus:ring-primary-500` or `focus:border-accent` literals.
- `DLR-R-021` — No `<style scoped>` block in the page source (current state: 0; PR1 MUST NOT add one).
- `PAGOS-MNY-002` (sibling cross-cutting) — `formatPENLabel` is the only money formatter consumed on this slice (enforced by EC-005).

---

## 4. Out of scope (deferred, mirrored from proposal §3)

| Item | Reason |
|---|---|
| `ProcedureCatalogPage.vue` + `ProcedureCatalogDetailPage.vue` + `ProcedureCatalogFormModal.vue` | PR3 (global proposal §7.5). Sibling of this PR in `procedure-catalog/`. |
| `<UiDataTable>` migration | Keep raw `<table>`, tokenise borders + add `tabular-nums`. Lifecycle-defer until a second table-heavy module needs it. |
| `<UiStatusBadge>` adoption | Page has no status pills; first consumer is global PR2 (Quotations). |
| `<RoleBanner>` primitive extraction | Inline `<div>` for PR1 (OQ-EC-3). Duplication threshold (≥2 modules) not yet met. |
| Per-KPI sparklines | Deferred to a separate change (vertical slice open item #1). |
| Two-tone numerals | Reversible D12, stays rejected. |
| Dark mode, accessibility overhaul, new tokens, new primitives | Inherited from global proposal §3. |
| Backend changes (controller, service, migration, listener) | Out of scope; backend frozen. |

---

## 5. Acceptance criteria

PR1 is considered complete when ALL hold:

- `/procedure-stats` renders via the auth gate (EC-001).
- All 9 EC-* MUST rows are satisfied (EC-001..EC-009).
- The 5 inherited `ModuleAppShellTestCase` assertions stay green on `ProcedureStatsPage.vue`.
- `ProcedureStatsAppShellTest` is green with 5 inherited + 5 category-specific assertions (10 total).
- All 6 standing gate tests stay green: `TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `AppLayoutCanvasRoutesTest`.
- No `<style scoped>` block introduced (file already has 0).
- No new `<script>` complexity beyond the single `formatPENLabel` import.
- Playwright sweep at 1440×900 saves `.playwright-cli/screenshots-pr1/procedure-stats-1440x900.png` for visual verification.
- `git diff --stat` reports `additions + deletions ≤ 400` (PR-budget isolation per global proposal §7.1; current estimate ~140 authored lines).
- CI gates (`quality`, `backend-tests`, `frontend-build`) green.

---

## 6. Verification strategy

- **Static (PHPUnit)**: new `ProcedureStatsAppShellTest.php` extending `ModuleAppShellTestCase` (5 inherited + 5 category-specific per the MUSTs above). The router registration is enforced by a new `appRoutes_contain_procedure_stats_test.php` (or extension of `AppLayoutCanvasRoutesTest`).
- **Visual**: Playwright snapshot at 1440×900 saved to `.playwright-cli/screenshots-pr1/procedure-stats-1440x900.png`. Eyeball-compare against `DashboardPage.vue` KPI anatomy.
- **Static grep**: `git grep -nE "border-theme|text-green-600|bg-red-50|border-red-200|text-red-700|toFixed\(2\)" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns zero matches after the change.
- **Build**: `pnpm build` clean (no Vite warnings, no Vue template syntax errors).

---

## 7. References

- Parent proposal: `openspec/changes/ui-rollout-all-modules-2026-08/categories/estadisticas-catalogo/proposal.md`
- Parent explore: `openspec/changes/ui-rollout-all-modules-2026-08/categories/estadisticas-catalogo/explore.md`
- Parent spec: `openspec/specs/design-language-rollout/spec.md`
- Sibling spec format: `openspec/changes/archive/2026-08-12-ui-pagos/specs/pagos/spec.md`
- KPI anatomy exemplar: `resources/js/modules/dashboard/DashboardPage.vue` (fixed-slot grid, `data-stat-card`, hairline + elevation-2)
- Router: `resources/js/app.js` (lines 32–203) — `/procedure-stats` NOT registered (OQ-EC-1, CRITICAL)
- `AppLayout.vue` lines 518–557 — `/procedure-stats` IS in `canvasRoutes` (PR0 landed)
- Test base: `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (5 inherited assertions)
- Token source: `resources/js/design-system/tokens.js` (systemGreen L47–56, systemRed L58–67)
- Currency formatter: `resources/js/composables/useFormatters.js` (PAGOS-MNY-002)
- Backend: `routes/api.php:189` — `GET /api/admin/procedure-stats` (frozen)

---

## Key Learnings

1. **CRITICAL**: `/procedure-stats` is NOT registered in `resources/js/app.js` today (OQ-EC-1). Without the additive route entry (EC-001), the polished page is unreachable via normal navigation; users hit the 404 catch-all. PR1 includes the `app.js` fix (~5 lines) between `/procedure-catalog/:id` and `/my-procedures` blocks — this MUST be the first MUST (EC-001) and is the cheapest path to a verified second proving ground.
2. The page carries zero `<style scoped>` blocks and zero proven-token references — the cleanest Tier-1 candidate for the second proving ground, with minimal blast radius.
3. The 3 KPI counters do NOT reuse Dashboard's fixed-slot anatomy today (no `h-4/h-12/h-6/h-4` reserved slots, no `--color-hairline`/`--elevation-2` inline styles, no `tabular-nums`) — adopting the anatomy proves the pattern scales beyond Dashboard.
4. Numeric tables require BOTH `tabular-nums` Tailwind utility AND `style="font-feature-settings: var(--font-features-tabular-nums)"` per the standing contract; either alone fails the assertion (EC-009 paired-only rule).
5. `<PageHeader>` adoption is the cheapest fix for the page-level `<h1>` defect (defect 7 family) — no new primitive, no extra slots, no re-render of surrounding chrome (EC-003).

---

*End of category spec. Next: `sdd-design` (in parallel; design is a sibling review of tokens + primitives + motion) and `sdd-tasks` (TDD task list).*
