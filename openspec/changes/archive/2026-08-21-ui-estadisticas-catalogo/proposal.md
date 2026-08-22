# Proposal: estadisticas-catalogo (ui-rollout-all-modules-2026-08)

> SDD phase: `sdd-propose`. Recommended second proving ground (global explore §6.2 slice 1). Single PR.
> All technical artifacts in English.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `estadisticas-catalogo` (AGENTS.md §5 #17) |
| Slice | PR1 (single PR) |
| Date | 2026-08-21 |
| Phase | propose (2 of 6) — category slice |
| Author | `sdd-propose` sub-agent (estadisticas-catalogo) |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/estadisticas-catalogo/proposal`) |
| Delivery strategy | Inherits `auto-chain` from global proposal; this PR stacks after PR0 |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` — forward to apply/verify |
| Roles | administrador, finanzas |
| Primary route | `/procedure-stats` (NOT currently registered — see OQ-EC-1) |
| Primary file | `ProcedureStatsPage.vue` (198 LOC) |
| Parent artifacts | global `proposal.md` §7.3 (PR1), global `explore.md` §6.2 slice 1, category `explore.md` |

### CRITICAL FINDING (OQ-EC-1) — RESOLVED IN FAVOR OF IN-PR1

**`/procedure-stats` is NOT registered in `resources/js/app.js` (lines 32–203, 17 module routes + 2 settings + 404 catch-all, no procedure-stats entry).** `AppLayout.canvasRoutes` already lists `/procedure-stats` (line 534, PR0 landed), so the canvas surface wiring is in place, but `vue-router` cannot reach the page. Without the route, the polished output is unreachable. **PR1 includes additive `app.js` registration (~5 lines)** between `/procedure-catalog/:id` (lines 113–118) and `/my-procedures` (lines 119–124).

---

## 1. Intent

Estadísticas catálogo is the read-only BI companion of the procedure-catalog module: it surfaces per-specialty usage, top-N procedures by frequency and revenue, and aggregate catalog counters for the `administrador` reviewing what's being practiced and the `finanzas` role reconciling what gets billed. Today it reads as legacy: opaque `border-theme` separators, raw `text-green-600` Tailwind on the "Activos" KPI, jittering numeric columns without `tabular-nums`, ad-hoc empty-state `<div>`, and a page-level `<h1>` that competes with the topbar h1. There is no router registration, so the only way to reach the page today is direct file inspection — the polished page is invisible.

This proposal scopes the rollout to **only** the `ProcedureStatsPage.vue` surface plus the missing router wire-up. It inherits every proven rule from the vertical slice and global proposal (tokens, primitives, ease-ios, tinted elevation, hairlines, focus-ring, `tabular-nums`, canvas/surface separation) and applies them mechanically. Result: a clinician landing on `/procedure-stats` reads the same product as one landing on `/dashboard`. Backend is byte-for-byte untouched — only the frontend template, the router registration, and a new per-module test change.

**Why now:** the proven language has been validated on Login (form-heavy) and Dashboard (KPI-heavy); the rollout needs a third screen that exercises a DIFFERENT pattern to prove the language scales. `ProcedureStatsPage.vue` is the cleanest Tier-1 candidate — small, single-page, table-heavy with 3 KPI counters, no third-party widgets, no real-time channels, no `<script>`-block fragility. The router registration fix is mandatory for the PR to ship (the polished output is otherwise invisible); deferring it to a separate prerequisite PR inflates the chain for a trivial edit. Including the route in PR1 is the cheapest path to a verified second proving ground.

---

## 2. Scope

### 2.1 Files to modify

| File | Touch scope |
|---|---|
| `resources/js/app.js` | **Additive** route registration (`~5 lines`, between `/procedure-catalog/:id` block and `/my-procedures` block). No existing route mutated. |
| `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | Tokenise (hairline borders, elevation-2 shadows, `tabular-nums`, systemGreen for "Activos", systemRed error banner), adopt Dashboard KPI anatomy (fixed-slot h-4/h-12/h-6/h-4 reserved rows), adopt `<PageHeader>` (removes page-level `<h1>`), adopt `<UiEmptyState>` (replaces ad-hoc `<div>`), adopt `<UiSkeleton>` (loading state), adopt `formatPENLabel` from `useFormatters` (replaces inline `.toFixed(2)`), adopt `var(--color-canvas)` via PR0 surface wiring, add inline role disclosure (administrador / finanzas). |
| `tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` | **NEW.** Extends `ModuleAppShellTestCase` (5 inherited assertions) + ~5 category-specific assertions for KPI anatomy and `formatPENLabel` adoption. |

### 2.2 Files NOT to modify

| File | Reason |
|---|---|
| `routes/api.php:189` (`GET /api/admin/procedure-stats`) | Backend frozen. The endpoint already returns `stats.catalog.{total,active,inactive}`, `stats.top_procedures[]`, `stats.by_specialty[]` (verified by `loadStats` payload shape). |
| `resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue` (271 LOC) | PR3 scope (global proposal §7.5). Has import modal + CSV upload — bounded but bigger. |
| `resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue` (161 LOC) | PR3 scope. |
| `resources/js/modules/procedure-catalog/ProcedureCatalogFormModal.vue` (151 LOC) | PR3 scope. |
| `resources/js/components/layout/AppLayout.vue` | PR0 already extended `canvasRoutes` to include `/procedure-stats` (line 534). No further AppLayout work for this PR. |

### 2.3 Backend changes

**NONE.** Backend contracts are frozen; the rollout is UI-only.

### 2.4 Cross-cutting primitives consumed (PR0 owns the primitives themselves)

- `<AppLayout>` — already used.
- `<PageHeader>` — REPLACE custom header (lines 3–17). Removes page-level `<h1>` (defect 7 family per `DashboardAppShellTest::test_dashboard_greeting_not_h2_or_h1_uses_text_lg_font_medium`).
- `<UiCard variant="glass">` — already used; gains inline `:style` for `boxShadow: var(--elevation-2)` and `borderColor: var(--color-hairline)`.
- `<UiSkeleton>` — ADOPT for loading state (3 card + 6 list skeletons, mirroring Dashboard).
- `<UiEmptyState>` — ADOPT to replace ad-hoc `<div>` empty messages (lines 81, 129).
- `<UiInput>` + `<UiButton>` — already used in filter card; keep.
- `formatPENLabel` from `useFormatters` — ADOPT to replace `.toFixed(2)` at lines 117 and 142.
- `<UiStatusBadge>` — NOT consumed in this PR (page has no status pills). First consumer is global PR2 (Quotations).

---

## 3. Out of scope (deferred)

1. **ProcedureCatalogPage + ProcedureCatalogDetailPage + ProcedureCatalogFormModal** — PR3 (global proposal §7.5). Separate slice. Sibling of this PR in the same `procedure-catalog/` directory.
2. **`<UiDataTable>` migration** — keep raw `<table>`, tokenise borders + add `tabular-nums`. Lifecycle-defer until a second table-heavy module needs it (per global proposal OQ#5 disposition — keep raw, no primitive extraction).
3. **`<UiStatusBadge>` adoption** — page has no status pills; no consumer here.
4. **`<RoleBanner>` primitive extraction** — inline `<div>` disclosure for PR1 (OQ-EC-3). The duplication threshold (≥2 modules) is not yet met; second consumer arrives with the next role-restricted module slice. Spec phase may flag it.
5. **Per-KPI sparklines** — vertical slice open item #1, deferred to its own change.
6. **Two-tone numerals** — vertical slice D12 REVERSIBLE, stays rejected (no override arrived).
7. **Dark mode, accessibility overhaul, new tokens, new primitives** — all inherited from global proposal §3.

---

## 4. Acceptance criteria

Verifiable; PR1 considered complete when ALL hold:

- [ ] **`/procedure-stats` is reachable.** `resources/js/app.js` carries `{ path: '/procedure-stats', name: 'procedure-stats', component: () => import('./modules/procedure-catalog/ProcedureStatsPage.vue'), beforeEnter: requireAuth }` between the `/procedure-catalog/:id` and `/my-procedures` route blocks. `git grep -n "procedure-stats" resources/js/app.js` returns exactly one match (the route entry, plus the implicit name string).
- [ ] **Each KPI number uses `tabular-nums` AND `font-feature-settings: var(--font-features-tabular-nums)`** (BOTH required per standing contract). All 3 KPI counters (Total procedimientos, Activos, Inactivos) and the 3 numeric table columns (Usos, Cantidad total, Ingresos S/) and the 2 specialty numeric columns (usos, S/) carry the class+style pair.
- [ ] **Each KPI `<UiCard>` consumes `var(--color-hairline)` + `var(--elevation-2)`** via inline `:style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"`. 3 instances.
- [ ] **Page-level `<h1>` is removed.** `<PageHeader title="..." :subtitle="...">` replaces the custom header block (lines 3–17). Page source declares zero `<h1>` tags.
- [ ] **Canvas surface is wired.** The page renders inside `<AppLayout>` and the route `/procedure-stats` is in `canvasRoutes` (already true per PR0). Visual: page background reads `#F2F2F7`, cards read `#FFFFFF` lifted off the canvas.
- [ ] **Specialty tiles** consume `var(--color-hairline)` border + `var(--radius-control)` (8 px) radius + `tabular-nums` on numerics.
- [ ] **Error banner** uses `bg-systemRed-50 border-systemRed-200 text-systemRed-700` (NOT raw `bg-red-50 border-red-200 text-red-700`).
- [ ] **`text-green-600` on line 60** becomes `text-systemGreen-600`.
- [ ] **Currency cells** (table line 117, specialty line 142) consume `formatPENLabel` from `useFormatters`.
- [ ] **Empty states** (lines 81, 129) consume `<UiEmptyState title="Sin datos" description="..." />`.
- [ ] **Loading state** mirrors Dashboard's pattern: 3 `<UiSkeleton variant="card">` for KPI counters + 6 `<UiSkeleton variant="list">` for table + specialty rows. `aria-busy` and `aria-live="polite"` on the loading wrapper.
- [ ] **Role disclosure** rendered inline at the page top: "Visible para: Administrador, Finanzas" (matches `routes/api.php:189` role middleware).
- [ ] **`ProcedureStatsAppShellTest` green** with 10 assertions (5 inherited from `ModuleAppShellTestCase` + 5 category-specific — see §6 test plan).
- [ ] **All 6 standing gate tests stay green**: `TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `AppLayoutCanvasRoutesTest`.
- [ ] **No `<style scoped>` block introduced** (file already has 0).
- [ ] **No new `<script>` complexity** — `formatPENLabel` is the only import added (from existing `useFormatters`).
- [ ] **Playwright sweep at 1440x900** captures `.playwright-cli/screenshots-pr1/procedure-stats-1440x900.png` for visual verification (eyeball-compare against Dashboard's KPI anatomy).

---

## 5. Risks + Mitigations

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | **Router registration missing** (OQ-EC-1). Without the route in `app.js`, the polished page is invisible — users hit the 404 catch-all. | **BLOCKING (already true today)** | PR1 includes additive `app.js` edit (~5 lines). Acceptance criterion #1 enforces presence via `git grep`. Reversible independently: reverting the `app.js` lines restores the 404 path; the page file changes stay useful for future route work. |
| 2 | **Heading hierarchy defect** — page-level `<h1>` at line 6 competes with topbar `<h1>` (defect 7 family). | Low | Adopt `<PageHeader>` — no page-level h1. Test asserts zero `<h1>` in the file source. |
| 3 | **Missing `<UiSkeleton>` for loading** — current page renders blank during fetch, no progress cue. | Low | Mirror Dashboard's loading block exactly: 3 card skeletons + 6 list skeletons, `aria-busy`, `aria-live="polite"`. |
| 4 | **KPI numbers jitter on update** — no `tabular-nums` today; column figures shift. | Low | BOTH `tabular-nums` Tailwind utility AND `style="font-feature-settings: var(--font-features-tabular-nums)"` on every numeric element (per standing contract from `DashboardAppShellTest`). |
| 5 | **`text-green-600` raw Tailwind** at line 60. | Low | `text-systemGreen-600` (tokenised; preserves the "Activos" positive affordance). |
| 6 | **Raw red ramps on error banner** at line 147. | Low | Tokenised `systemRed-50/200/700` ramp. |
| 7 | **No role disclosure** — admin/finanzas-only module with no visible role cue. | Low | Inline `<div>` at page top: "Visible para: Administrador, Finanzas" (OQ-EC-3). |
| 8 | **Currency formatter inline** at lines 117, 142 (`.toFixed(2)`). | Low | Adopt `formatPENLabel` from `useFormatters`. Test asserts no `.toFixed(2)` remains on currency cells. |
| 9 | **No per-module test** — global PR0 doesn't cover this surface. | Low | New `ProcedureStatsAppShellTest.php` extending `ModuleAppShellTestCase` with 10 assertions (5 inherited + 5 category-specific). |

---

## 6. Test plan (strict TDD)

### 6.1 Inherited assertions (5 — from `ModuleAppShellTestCase`)

| # | Assertion | DLR rule |
|---|---|---|
| 1 | File references `bg-canvas` / `var(--color-canvas)` / `rgb(242, 242, 247)`. | DLR-R-001 |
| 2 | File does NOT contain `border-theme` literal. | DLR-R-002 |
| 3 | If file has `:focus` / `:focus-visible`, it consumes `var(--focus-ring-default)`. | DLR-R-004 |
| 4 | File does NOT contain `focus:ring-primary-500` / `focus:border-accent`. | DLR-R-004 |
| 5 | File does NOT contain `<style scoped>` block. | DLR-R-021 |

### 6.2 Category-specific assertions (5 — added in `ProcedureStatsAppShellTest`)

| # | Assertion | Rationale |
|---|---|---|
| 6 | The 3 KPI `<UiCard>` elements carry `data-stat-card="..."` attribute AND `var(--color-hairline)` AND `var(--elevation-2)`. | KPI anatomy adoption (Dashboard contract). |
| 7 | Every numeric element (KPI counters + 3 table numeric columns + 2 specialty numerics) uses BOTH `tabular-nums` AND `style="font-feature-settings: var(--font-features-tabular-nums)"`. | Standing contract — numbers are data, not display type. |
| 8 | The page source declares zero `<h1>` tags (heading lives in `<PageHeader>` and AppLayout topbar). | Defect 7 family fix. |
| 9 | No `.toFixed(2)` literal remains on currency cells (table line 117, specialty line 142 now call `formatPENLabel`). | Currency formatter consolidation contract. |
| 10 | Loading branch renders `<UiSkeleton variant="card">` (≥3 instances) AND `<UiSkeleton variant="list">` (≥6 instances), wrapped in `aria-busy="true"`. | Loading-state contract (mirror Dashboard). |

### 6.3 TDD discipline

1. Write `ProcedureStatsAppShellTest.php` with all 10 assertions (RED).
2. Apply `app.js` route addition first (so the page is reachable for visual verification at GREEN).
3. Apply template-level class-string + slot replacements on `ProcedureStatsPage.vue` (GREEN).
4. Verify via Playwright at 1440x900 (`admin@test.com` or `finanzas@test.com` per `CREDENTIALS.md`).
5. Eyeball-compare against DashboardPage KPI anatomy (`screenshots-pr3/dashboard-1440x900.png`).

---

## 7. Open Questions resolved (with rationale)

All 5 OQs from category `explore.md` §6 are resolved with concrete defaults.

| # | Question | Resolution | Rationale |
|---|---|---|---|
| **OQ-EC-1** | Router registration scope? | **PR1 includes additive `app.js` edit (~5 lines)**. | The polished page is unreachable without the route. A separate prerequisite PR inflates the chain for a trivial edit. Including the route in PR1 is the cheapest path to a verified proving ground; reversibility is preserved via `git revert`. |
| **OQ-EC-2** | `<UiCard>` default border? | Keep per-card inline `:style` (Dashboard pattern). | The Dashboard exemplar uses inline `:style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"` per card — proven, minimal. The "extract to UiCard default" disposition is deferred to a future Card primitive revision (out of scope). |
| **OQ-EC-3** | Role banner pattern? | Inline `<div>` for PR1; flag `<RoleBanner>` primitive extraction for spec phase. | The duplication threshold (≥2 modules) is not yet met; the next role-restricted module slice will land alongside this one and trigger the primitive extraction. PR1 stays small. |
| **OQ-EC-4** | `<UiDataTable>` adoption? | Keep raw `<table>`, tokenise borders + add `tabular-nums`. | The `<UiDataTable>` primitive (13.7 KB) is rarely used today; many modules roll their own tables. Lifecycle-defer until a second table-heavy module needs it. This PR keeps raw. |
| **OQ-EC-5** | `<PageHeader>` adoption? | **Yes** — removes page-level `<h1>` defect. | The defect 7 family (page-level h1 vs topbar h1) is a proven fix on Dashboard. `<PageHeader>` primitive already exists and is in use on other screens. |

---

## 8. PR shape

| Field | Value |
|---|---|
| Name | `pr1-estadisticas-catalogo-router-and-tokenise` |
| Branch | `feature/pr1-estadisticas-catalogo-router-and-tokenise` (stacked-to-main per global proposal §7.1) |
| Scope | (1) `app.js`: additive `/procedure-stats` route (~5 lines). (2) `ProcedureStatsPage.vue`: full tokenisation + KPI anatomy adoption + skeleton loading + `<UiEmptyState>` + `formatPENLabel` + `<PageHeader>` + role disclosure. (3) `tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php`: new test, 10 assertions. |
| Target module(s) | 17 (Estadísticas catálogo) |
| Risk | Low |
| Dependencies | PR0 (already merged — `canvasRoutes` extended, `<UiStatusBadge>` extracted, `ModuleAppShellTestCase` available) |
| Authored lines | ~140 (app.js ~5, page ~120, test ~50, minus deletions) |
| Reversibility | `git revert <merge-sha>` removes route registration + tokenisation + test. The route delete restores the 404 path; page reverts to legacy visual but remains functionally reachable. |
| Visual verification | Playwright snapshot at 1440x900 → `.playwright-cli/screenshots-pr1/procedure-stats-1440x900.png`. Eyeball-compare against `.playwright-cli/screenshots-pr3/dashboard-1440x900.png` for KPI anatomy parity. |
| CI gate | `quality` + `backend-tests` + `frontend-build` all green. |

### 8.1 PR1 checklist

- [ ] `app.js`: insert `/procedure-stats` route block between `/procedure-catalog/:id` and `/my-procedures` (≈5 lines, copy the pattern from sibling routes).
- [ ] `ProcedureStatsPage.vue`: replace custom header (lines 3–17) with `<PageHeader title="..." :subtitle="...">`. Remove page-level `<h1>`.
- [ ] `ProcedureStatsPage.vue`: each of the 3 KPI `<UiCard>`s gains `:style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"`.
- [ ] `ProcedureStatsPage.vue`: each KPI number uses `text-5xl font-bold tabular-nums text-label` + `style="font-feature-settings: var(--font-features-tabular-nums)"`.
- [ ] `ProcedureStatsPage.vue`: line 60 `text-green-600` → `text-systemGreen-600`.
- [ ] `ProcedureStatsPage.vue`: lines 85, 99 `border-theme` → `border-[color:var(--color-hairline)]`.
- [ ] `ProcedureStatsPage.vue`: line 136 specialty tile wrapper — `rounded-lg border border-theme bg-theme-surface-elevated` → `rounded-[var(--radius-control)] border-[color:var(--color-hairline)] bg-theme-surface-elevated`.
- [ ] `ProcedureStatsPage.vue`: table numerics (lines 110, 113, 116) + specialty numerics (lines 141, 142) get `tabular-nums` + `style="font-feature-settings: var(--font-features-tabular-nums)"`.
- [ ] `ProcedureStatsPage.vue`: import `formatPENLabel` from `@/composables/useFormatters`. Replace `.toFixed(2)` at lines 117 and 142 with `{{ formatPENLabel(proc.total_revenue) }}` and `{{ formatPENLabel(spec.total_revenue) }}`.
- [ ] `ProcedureStatsPage.vue`: line 81 + line 129 ad-hoc empty `<div>` → `<UiEmptyState title="Sin datos" description="No hay datos para el período seleccionado." />`.
- [ ] `ProcedureStatsPage.vue`: loading state — mirror Dashboard pattern: 3 `<UiSkeleton variant="card">` + 6 `<UiSkeleton variant="list">` inside an `aria-busy="true" aria-live="polite"` wrapper.
- [ ] `ProcedureStatsPage.vue`: line 147 raw red ramps → `bg-systemRed-50 border border-systemRed-200 text-systemRed-700`.
- [ ] `ProcedureStatsPage.vue`: inline role disclosure at page top — "Visible para: Administrador, Finanzas" inside a small `<div class="text-xs text-theme-secondary">`.
- [ ] `ProcedureStatsAppShellTest.php`: 10 assertions (5 inherited + 5 category-specific per §6).
- [ ] Playwright sweep at 1440x900 saves `.playwright-cli/screenshots-pr1/procedure-stats-1440x900.png`.

---

## 9. Affected Areas

| Area | Impact | Description |
|---|---|---|
| `resources/js/app.js` | Modified (additive) | One route entry inserted (~5 lines). No existing route touched. |
| `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` | Modified | Template: header replace, KPI anatomy, table tokenisation, specialty tile tokenisation, error banner, empty states, loading skeleton, role disclosure. Script: 1 new import (`formatPENLabel`). No new state, no new computed. |
| `tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php` | New | Extends `ModuleAppShellTestCase`; 10 assertions. |
| `routes/api.php:189` | Unchanged | Backend frozen. |
| `resources/js/components/layout/AppLayout.vue` | Unchanged | PR0 already extended `canvasRoutes` to include `/procedure-stats`. |
| Backend (controllers, services, migrations) | Unchanged | Out of scope. |
| `resources/js/modules/procedure-catalog/{ProcedureCatalogPage,ProcedureCatalogDetailPage,ProcedureCatalogFormModal}.vue` | Unchanged | PR3 scope. |

---

## 10. Rollback Plan

- **Per-PR revert:** PR1 is independently revertible via `git revert <merge-sha>`. The stacked-to-main strategy keeps every commit reachable and named.
- **Route registration alone:** reverting just the `app.js` lines restores the 404 catch-all path. Visual verification: visiting `/procedure-stats` falls through to `NotFoundPage`. The page file changes (tokenisation) stay in the tree; a future PR can re-add the route without re-doing the visual work.
- **Page file alone:** reverting just the `ProcedureStatsPage.vue` lines restores the legacy visual but the route still resolves (404 → NotFoundPage). Renders OK because the legacy template is still self-consistent.
- **Test file alone:** removing `ProcedureStatsAppShellTest.php` does not affect runtime; CI simply loses one test row.
- **No destructive schema/data migrations.** No backend changes. The visual layer is fully revertible.

---

## 11. References

### 11.1 Source artifacts read for this proposal

| File | Why it matters |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/estadisticas-catalogo/explore.md` | **PRIMARY INPUT.** KPI inventory, header defect, table defects, error banner defect, OQ-EC-1..5. |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (596 lines) | Global intent, scope, PR chain (§7.3 PR1), success criteria, OQ resolutions, standing guard rails. |
| `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (496 lines) | Module inventory, complexity tiers, KPI anatomy reference, PR chain ordering rationale (§6.2 slice 1 = this PR). |
| `openspec/changes/archive/2026-08-12-ui-pagos/proposal.md` | Category proposal format precedent (sibling to this one). |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` | Source of truth for token values, primitive API, motion durations, focus ring composition, fixed-slot KPI anatomy (D6 + G2–G13). |
| `openspec/specs/premium-design-foundation/spec.md` | The archived capability inherited as-is. |
| `AGENTS.md` §5 row 17 | Estadísticas catálogo inventory entry (administrador, finanzas). |
| `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` (198 lines) | Target file. |
| `resources/js/modules/dashboard/DashboardPage.vue` | KPI card anatomy exemplar. |
| `resources/js/app.js` (lines 32–203) | Router definitions; CRITICAL — `/procedure-stats` NOT registered (OQ-EC-1). |
| `resources/js/components/layout/AppLayout.vue` (line 534) | `canvasRoutes` already includes `/procedure-stats` (PR0 landed). |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Base class for per-module tests (5 inherited assertions). |
| `tests/Unit/DesignSystem/DashboardAppShellTest.php` | KPI anatomy assertion pattern (fixed-slot h-4/h-12/h-6/h-4, `data-stat-card`, hairline + elevation-2). |
| `resources/js/design-system/tokens.js` | Token source-of-truth. |
| `resources/css/tokens.generated.css` | Generated CSS output. |
| `resources/js/composables/useFormatters.js` | Source of `formatPENLabel`. |
| `routes/api.php:189` | `GET /api/admin/procedure-stats` (backend, frozen). |

### 11.2 Standing guard rails (inherited from the baseline)

This PR does NOT relax any of:

1. `tokens.js` is the only source of truth for tokens.
2. `systemBackground` (`#ffffff`) is pinned; canvas = `#F2F2F7`.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT pure black.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT opaque `#c6c6c8`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings: var(--font-features-tabular-nums)`, NOT literal utility name.
7. Numeric tables require BOTH `tabular-nums` Tailwind utility AND `style="font-feature-settings: var(--font-features-tabular-nums)"`.
8. `<script setup>` Composition API only; NO Options API for new code.
9. `useApi()` wrapper only; NO axios direct.
10. pnpm only; NEVER npm/yarn.
11. Code in English; conversation in Spanish (Peru).

---

## 12. What This Proposal Does NOT Do

- Does NOT add `/procedure-stats` to `canvasRoutes` (PR0 already did).
- Does NOT redesign the page — it ROLLOUTS the proven language.
- Does NOT touch the backend (no controller, no service, no migration, no listener).
- Does NOT touch the sibling `procedure-catalog/{ProcedureCatalogPage,ProcedureCatalogDetailPage,ProcedureCatalogFormModal}.vue` files (PR3).
- Does NOT extract a `<RoleBanner>` primitive (inline disclosure for PR1; defer to a future slice).
- Does NOT adopt `<UiDataTable>` (keep raw `<table>`, tokenise only).
- Does NOT adopt `<UiStatusBadge>` (page has no status pills).
- Does NOT introduce `<style scoped>` blocks.
- Does NOT add gradients, dark mode, two-tone numerals, or per-KPI sparklines.
- Does NOT change the `GET /api/admin/procedure-stats` API contract.

---

## Key Learnings

1. **`/procedure-stats` is NOT registered in `resources/js/app.js` today** — the polished page is unreachable via normal navigation. PR1 includes additive `app.js` registration (~5 lines) between `/procedure-catalog/:id` and `/my-procedures` blocks; without this fix, the PR is functionally invisible.
2. The page has zero `<style scoped>` blocks and zero proven-token references — cleanest Tier-1 candidate for the second proving ground after Dashboard, with minimal blast radius.
3. The 3 KPI counters do NOT reuse Dashboard's fixed-slot anatomy (no h-4/h-12/h-6/h-4 reserved slots, no `--color-hairline`/`--elevation-2` inline styles, no `tabular-nums`) — adopting the anatomy proves the pattern scales to non-Dashboard surfaces.
4. The page has no status pills — `<UiStatusBadge>` extraction (PR0) doesn't bind here; Quotations (PR2) is the first consumer.
5. Numeric tables require BOTH `tabular-nums` Tailwind utility AND `style="font-feature-settings: var(--font-features-tabular-nums)"` per standing contract; either alone fails the assertion.
6. `<PageHeader>` adoption is the cheapest fix for the page-level `<h1>` defect (defect 7 family) — no need for a new primitive, no extra slots, no re-render of surrounding chrome.

---

*End of category proposal. Next: `sdd-spec` (resolves OQ-EC-3 primitive extraction) and `sdd-design` (tokens / primitives / motion review) can run in parallel.*
