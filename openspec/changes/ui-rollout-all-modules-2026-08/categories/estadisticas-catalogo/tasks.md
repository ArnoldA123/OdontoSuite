# Tasks: estadisticas-catalogo (ui-rollout-all-modules-2026-08)

> Single PR: `pr1-procedure-stats-tokenise-and-wire-up`. Includes BLOCKING EC-001 router fix.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `estadisticas-catalogo` (AGENTS.md §5 #17) |
| Slice | PR1 (`pr1-procedure-stats-tokenise-and-wire-up`) |
| Date | 2026-08-21 |
| Phase | tasks (4 of 6) — category slice |
| Artifact store | hybrid (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/estadisticas-catalogo/tasks`) |
| Pace / Delivery strategy | auto / auto-chain |
| Strict TDD | true |
| Roles | administrador, finanzas |
| Primary route | `/procedure-stats` |
| Primary file | `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` (198 LOC) |
| Estimated changed lines | ~140 authored (app.js ~5 + page ~120 + test ~50, minus deletions) |

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~140 |
| 400-line budget risk | Low |
| Chained PRs recommended | No (single PR — router fix + tokenisation ride together per OQ-EC-1 resolution) |
| Suggested split | Single PR: `pr1-procedure-stats-tokenise-and-wire-up` |
| Delivery strategy | auto-chain (caller-cached) |
| Chain strategy | stacked-to-main |

```
Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Low
```

### Per-slice work units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Router wire-up (EC-001) + KPI anatomy (EC-002) + PageHeader (EC-003) + UiEmptyState + UiSkeleton (EC-004) + formatPENLabel (EC-005) + systemGreen/Red tokenisation (EC-006) + hairlines (EC-007) + role disclosure (EC-008) + tabular-nums (EC-009) + `ProcedureStatsAppShellTest.php` (10 assertions) | PR 1 | `vendor/bin/phpunit tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` + `pnpm build` + Playwright snapshot at 1440x900 | `php artisan serve` + `pnpm dev`; login as `admin@test.com`; navigate to `/procedure-stats` | `git revert <merge-sha>` removes `app.js` route + page tokenisation + test file |

## Branch topology

- PR1 branch: `feature/pr1-procedure-stats-tokenise-and-wire-up` <- `main`
- Base is `main` because PR0 (`feat/ui-rollout-pr0-foundation`) is already merged (per `gitStatus` — `780b079 merge: feat/ui-rollout-pr0-foundation`).

---

## Phase 1: RED — write the tests FIRST

- [x] **T1**: Create `tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` extending `ModuleAppShellTestCase` (5 inherited assertions per DLR-R-001/002/004/021 + PAGOS-MNY-002) plus 5 category-specific assertions:
  - [x] `test_kpi_anatomy_matches_dashboard`: each of the 3 KPI `<UiCard>` carries `data-stat-card="..."` + `var(--color-hairline)` + `var(--elevation-2)` + the four reserved slots in the exact order `h-4 → h-12 → h-6 → h-4` (EC-002).
  - [x] `test_tabular_nums_on_all_numerics`: count of elements carrying BOTH `tabular-nums` AND `style="font-feature-settings: var(--font-features-tabular-nums)"` is ≥8 — 3 KPI + 3 table numerics + 2 specialty numerics; paired-only rule (EC-009).
  - [x] `test_page_header_replaces_h1`: `<PageHeader` is present in the template AND `grep -n "<h1" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns 0 matches (EC-003).
  - [x] `test_format_pen_label_consumed`: `<script setup>` declares `import { formatPENLabel } from '@/composables/useFormatters'` AND `grep -n "toFixed(2)" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns 0 matches (EC-005).
  - [x] `test_loading_branch_renders_skeletons`: ≥3 `<UiSkeleton variant="card">` + ≥6 `<UiSkeleton variant="list">` wrapped in a container carrying `aria-busy="true"` AND `aria-live="polite"` (EC-004).
  - [x] Additional 3 grep-driven category assertions (EC-006 + EC-007 + EC-008):
    - [x] `test_no_raw_green_or_red_ramps`: `grep -nE "text-green-600|bg-red-50|border-red-200|text-red-700"` returns 0 matches; `text-systemGreen-600` ≥1 occurrence; `bg-systemRed-50 border-systemRed-200 text-systemRed-700` present on error block.
    - [x] `test_specialty_tile_uses_hairline_and_radius_control`: specialty tile wrapper carries `rounded-[var(--radius-control)]` AND `border-[color:var(--color-hairline)]`.
    - [x] `test_role_disclosure_present`: literal Spanish text "Visible para: Administrador, Finanzas" present (case-insensitive) inside an element carrying `text-xs text-theme-secondary` class binding.
  - [x] Verify (RED): `vendor/bin/phpunit tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` exits non-zero.

## Phase 2: GREEN — apply router fix FIRST, then tokenisation

- [x] **T2 (CRITICAL — EC-001 BLOCKING)**: Insert the `/procedure-stats` route block in `resources/js/app.js` between the `/procedure-catalog/:id` route block (current lines 113–118) and the `/my-procedures` route block (current lines 119–124). Exact entry shape (copy verbatim from sibling routes):
  ```js
  {
    path: '/procedure-stats',
    name: 'procedure-stats',
    component: () => import('./modules/procedure-catalog/ProcedureStatsPage.vue'),
    beforeEnter: requireAuth
  },
  ```
  Verify: `git grep -n "procedure-stats" resources/js/app.js` returns ≥1 match; the entry sits BEFORE the `/my-procedures` block and AFTER the `/procedure-catalog/:id` block; `beforeEnter: requireAuth` is present. Run `pnpm build` — exits 0 (no Vite warnings about unresolved routes).

- [x] **T3 (EC-002)**: Apply Dashboard KPI anatomy to each of the 3 KPI `<UiCard>` elements (lines 47–72, the Total procedimientos / Activos / Inactivos cards):
  - [ ] Add `:style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"` inline binding on each of the 3 `<UiCard>`.
  - [ ] Add `data-stat-card="total-procedures"`, `data-stat-card="active"`, `data-stat-card="inactive"` on the 3 cards respectively.
  - [ ] Wrap each card body in a fixed-slot grid reserving `h-4` (eyebrow) / `h-12` (number) / `h-6 min-h-[24px]` (chip slot, empty when no delta) / `h-4` (caption) — exactly 4 reserved rows in that order.
  - [ ] Replace the numeric `<p>` with `text-5xl font-bold text-label tabular-nums` + `style="font-feature-settings: var(--font-features-tabular-nums)"`. Replace `text-theme-primary` (line 52) with `text-label`.

- [x] **T4 (EC-003)**: Replace the custom header block (current lines 3–17) with `<PageHeader title="Estadísticas de Procedimientos" subtitle="Uso del catálogo por especialidad y procedimientos más frecuentes">`. Remove the `<h1 class="text-3xl font-bold text-theme-primary mb-2">` element entirely. Verify: `grep -n "<h1" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns 0 matches.

- [x] **T5 (EC-004)**: Replace the ad-hoc empty `<div class="text-center py-8 text-theme-secondary">` blocks at lines 81 and 129 with `<UiEmptyState title="Sin datos" description="No hay datos para el período seleccionado." />`. Add a loading skeleton block above the main content (mirror Dashboard pattern from `DashboardPage.vue:5-43`): 3 `<UiSkeleton variant="card">` for the KPI counters + 6 `<UiSkeleton variant="list">` for the table + specialty rows, all wrapped in `<div aria-busy="true" aria-live="polite">`.

- [x] **T6 (EC-005)**: In the `<script setup>` block (after line 156), add `import { formatPENLabel } from '@/composables/useFormatters'`. Replace `{{ proc.total_revenue.toFixed(2) }}` at line 117 with `{{ formatPENLabel(proc.total_revenue) }}`. Replace `S/ {{ spec.total_revenue.toFixed(2) }}` at line 142 with `{{ formatPENLabel(spec.total_revenue) }}`. Verify: `grep -n "toFixed(2)" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns 0 matches.

- [x] **T7 (EC-006)**: At line 60, replace `text-green-600` with `text-systemGreen-600`. At line 147, replace the raw red ramp `bg-red-50 border border-red-200 rounded-lg text-red-700` with `bg-systemRed-50 border border-systemRed-200 rounded-lg text-systemRed-700`. Verify: `grep -nE "text-green-600|bg-red-50|border-red-200|text-red-700" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns 0 matches.

- [x] **T8 (EC-007)**: At lines 85, 99, and 136, replace `border-theme` with `border-[color:var(--color-hairline)]`. At line 136, also replace `rounded-lg` with `rounded-[var(--radius-control)]` on the specialty tile wrapper. Verify: `grep -n "border-theme" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` returns 0 matches.

- [x] **T9 (EC-008)**: Add an inline role disclosure `<div class="text-xs text-theme-secondary">Visible para: Administrador, Finanzas</div>` at the top of the page (between the `<PageHeader>` and the filter card). Use a small, inline disclosure — do NOT make it a full-width banner.

- [x] **T10 (EC-009)**: Add `tabular-nums` + `style="font-feature-settings: var(--font-features-tabular-nums)"` to all 8 numeric elements: 3 KPI numbers (Total procedimientos, Activos, Inactivos) + 3 table numerics (Usos line 110, Cantidad total line 113, Ingresos S/ line 116) + 2 specialty numerics (usos line 141, S/ line 142). Each numeric MUST carry BOTH — paired-only rule.

## Phase 3: VERIFY

- [x] **T11**: Run `vendor/bin/phpunit tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` — exit 0 (GREEN). Confirm 5 inherited + 5 category-specific assertions pass (10 total). All RED assertions from T1 now GREEN.

- [x] **T12**: Run the full DesignSystem suite + build + API regression:
  - [x] `vendor/bin/phpunit tests/Unit/DesignSystem/` — all 6 standing gate tests stay green: `TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `AppLayoutCanvasRoutesTest`.
  - [x] `pnpm build` — exit 0, no Vite warnings, no Vue template syntax errors.
  - [x] `phpunit tests/Feature/Api` — no regressions on the backend.
  - [x] `grep -nE "border-theme|text-green-600|bg-red-50|border-red-200|text-red-700|toFixed\(2\)" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` — returns 0 matches (EC-005/006/007 anti-regression).

- [x] **T13 (EC-001 visual verification)**: Boot `php artisan serve` + `pnpm dev`. Login as `admin@test.com` (or `finanzas@test.com`). Navigate to `/procedure-stats` — this MUST resolve to `ProcedureStatsPage.vue` (verifies EC-001 router registration); if it falls through to the 404 catch-all, the route entry is wrong. Screenshot at 1440x900 saved to `.playwright-cli/screenshots-pr1/procedure-stats-1440x900.png`. Eyeball-compare against `.playwright-cli/screenshots-pr3/dashboard-1440x900.png` for KPI anatomy parity (4 reserved slots in the same order, hairline + elevation-2 inline styles, tabular-nums, systemGreen "Activos").

## Phase 4: COMMIT

- [x] **T14**: Conventional commit message: `feat(ui): tokenise procedure-stats + register router per EC-* MUST rows`. Body lists EC-001..EC-009 coverage + the new test file. No `Co-Authored-By` line.

- [x] **T15**: Merge to `main` via PR (single PR per `stacked-to-main` strategy). Confirm CI gates (`quality` + `backend-tests` + `frontend-build`) green before merge. Run `git diff --stat` and confirm `additions + deletions ≤ 400` (target: ~140 authored).

## Out of scope (do NOT include)

- `ProcedureCatalogPage.vue` + `ProcedureCatalogDetailPage.vue` + `ProcedureCatalogFormModal.vue` — PR3 scope (global proposal §7.5).
- `<UiDataTable>` migration — keep raw `<table>`, tokenise borders + add `tabular-nums` only.
- `<UiStatusBadge>` adoption — page has no status pills; first consumer is global PR2 (Quotations).
- `<RoleBanner>` primitive extraction — inline `<div>` for PR1 (OQ-EC-3); defer until ≥2 modules.
- Per-KPI sparklines — vertical slice open item #1, deferred to its own change.
- Two-tone numerals — D12 REVERSIBLE, stays rejected.
- Dark mode, accessibility overhaul, new tokens, new primitives — global proposal §3.
- Backend changes (controller, service, migration, listener) — out of scope; backend frozen.

## Notes / handoffs to `sdd-apply`

- **T2 (router registration) is BLOCKING — do it FIRST in Phase 2.** Apply phase MUST verify `git grep -n "procedure-stats" resources/js/app.js` returns ≥1 match before moving to T3+; otherwise the entire PR is functionally invisible (users hit the 404 catch-all).
- **T10 paired-only rule is the most common assertion failure.** Numeric elements carrying `tabular-nums` but NOT `style="font-feature-settings: var(--font-features-tabular-nums)"` (or vice versa) MUST fail `test_tabular_nums_on_all_numerics`. Apply both together.
- **T11 → T13 order matters.** Run the unit test before Playwright; if T11 fails, T13 visual verification is meaningless.
- **T13 explicitly verifies the router fix** by navigating to `/procedure-stats`. If the page renders as `NotFoundPage`, the `app.js` entry is malformed — fix T2 before re-running T13.
- **No `<style scoped>` block** may be added (DLR-R-021; current state: 0). All tokenisation lives in template class strings + inline `:style` bindings.
- **No new `<script>` complexity** beyond the single `formatPENLabel` import. No new computed, no new state, no new ref.
- The `canvasRoutes` extension in `AppLayout.vue:534` (PR0) is already in place — no AppLayout work this slice.
- `routes/api.php:189` (`GET /api/admin/procedure-stats`) is frozen — do NOT touch.

## Cross-slice anti-requirement index

| Anti-requirement | Static check | Refs |
|---|---|---|
| No `border-theme` literal | `grep -n "border-theme" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | EC-007 |
| No `text-green-600` | `grep -n "text-green-600" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | EC-006 |
| No raw red ramps | `grep -nE "bg-red-50\|border-red-200\|text-red-700" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | EC-006 |
| No `.toFixed(2)` | `grep -n "toFixed(2)" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | EC-005 |
| No page-level `<h1>` | `grep -n "<h1" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | EC-003 |
| No `<style scoped>` block | `grep -n "<style scoped" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | DLR-R-021 |
| Router registered | `git grep -n "procedure-stats" resources/js/app.js` returns ≥1 match | EC-001 |
| `formatPENLabel` imported | `grep -n "formatPENLabel" resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | EC-005 |

## Key Learnings

1. **`/procedure-stats` is NOT registered in `resources/js/app.js` today** — without the additive route entry (EC-001), the polished page is unreachable via normal navigation. T2 (router fix) MUST be the first GREEN task in Phase 2; T13 (visual verification) explicitly navigates to `/procedure-stats` to confirm the fix.
2. The 3 KPI counters do NOT reuse Dashboard's fixed-slot anatomy today (no `h-4/h-12/h-6/h-4` reserved slots, no `--color-hairline`/`--elevation-2` inline styles, no `tabular-nums`) — T3 (EC-002) adopts the anatomy and proves the pattern scales beyond Dashboard.
3. Numeric tables require BOTH `tabular-nums` Tailwind utility AND `style="font-feature-settings: var(--font-features-tabular-nums)"` per the standing contract (T10, EC-009 paired-only rule) — either alone fails `test_tabular_nums_on_all_numerics`.
4. `<PageHeader>` adoption (T4, EC-003) is the cheapest fix for the page-level `<h1>` defect (defect 7 family) — no new primitive, no extra slots, no re-render of surrounding chrome.
5. The page has zero `<style scoped>` blocks and zero proven-token references today — cleanest Tier-1 candidate for the second proving ground after Dashboard, with minimal blast radius (~140 authored lines, well under the 400-line PR budget).