# Explore: estadisticas-catalogo (ui-rollout-all-modules-2026-08)

> SDD phase: `sdd-explore`. Recommended second proving ground after vertical slice (global explore §6.2 slice 1).

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `estadisticas-catalogo` (AGENTS.md §5 #17) |
| Slice | PR1 |
| Date | 2026-08-21 |
| Phase | explore (1 of 6) |
| Pace / Artifact store | auto / hybrid |
| Delivery strategy | auto-chain |
| Review budget | 400 authored lines / PR |
| Strict TDD | true |
| Roles | administrador, finanzas |
| Primary route | `/procedure-stats` |
| Primary file | `ProcedureStatsPage.vue` (198 LOC, 6.4 KB) |

---

## CRITICAL FINDING (OQ-EC-1)

**`/procedure-stats` is NOT registered in `resources/js/app.js`** (lines 32–203 — 17 module routes + 2 settings + 404 catch-all, but no `procedure-stats` entry). `AppLayout.vue` already lists `/procedure-stats` in `canvasRoutes` (PR0 landed, line 534), so the canvas surface wiring is in place, but `vue-router` cannot reach the page. The polished output is invisible without the route. **PR1 MUST include the additive router registration (~5 lines).**

---

## 1. File Inventory

### Target file (PR1 scope)

| File | LOC | `<style scoped>` | `@apply` | `@keyframes` | Legacy refs | Proven refs |
|---|---|---|---|---|---|---|
| `ProcedureStatsPage.vue` | **198** | **0** | **0** | **0** | **8** | **0** |

Sibling files in `procedure-catalog/` (NOT in PR1 — they ride PR3):

| File | LOC | In PR1? |
|---|---|---|
| `ProcedureCatalogPage.vue` | 271 | NO (PR3) |
| `ProcedureCatalogDetailPage.vue` | 161 | NO (PR3) |
| `ProcedureCatalogFormModal.vue` | 151 | NO (PR3) |

---

## 2. Current Visual State

### 2.1 KPI counters (lines 47–72) — NOT reusing Dashboard anatomy

The page has 3 KPI counters in `<UiCard variant="glass">`. Comparing to DashboardPage.vue (the DLR contract per `DashboardAppShellTest::test_dashboard_stat_cards_use_fixed_slot_grid`):

| Dimension | DashboardPage (POLISHED) | ProcedureStatsPage (LEGACY) |
|---|---|---|
| Border token | `--color-hairline` via inline `:style` | absent |
| Shadow token | `--elevation-2` via inline `:style` | absent |
| Eyebrow row slot | `h-4` (16 px) | NONE |
| Number row slot | `h-12` (48 px) | NONE — `text-3xl font-bold` (30 px) without reserved slot |
| Chip row slot | `h-6 min-h-[24px]` | absent |
| Caption row slot | `h-4` | absent |
| Number | `text-5xl font-bold tabular-nums` + `var(--font-features-tabular-nums)` | `text-3xl font-bold` WITHOUT tabular-nums |
| Number color | `text-label` (tokenised neutral) | inconsistent: `text-theme-primary`, `text-green-600`, `text-theme-secondary` |
| Icon plate | `w-12 h-12 bg-systemGray-100 rounded-ios` | absent (visual asymmetry) |
| `data-stat-card` attribute | REQUIRED | absent |

### 2.2 Top procedures table (lines 84–121)

Raw `<table>` with thead/tbody:
- `border-b border-theme` (lines 85, 99) — opaque separator
- Numeric columns WITHOUT `tabular-nums` — column figures jitter on update
- Revenue cell uses `.toFixed(2)` (line 117) — not `formatPENLabel`
- Empty state at line 81 is ad-hoc `<div>` — not `<UiEmptyState>`

### 2.3 Specialty grid (lines 132–144)

3-col responsive grid (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`) of tile boxes:
- Wrapper: `p-4 rounded-lg border border-theme bg-theme-surface-elevated` (3 defects)
- Numbers not `tabular-nums`

### 2.4 Filter card (lines 20–44)

Already tokenized (`<UiCard variant="glass">` + `<UiInput>` + `<UiButton>`). No changes needed.

### 2.5 Error banner (line 147)

Raw Tailwind red ramps: `bg-red-50 border-red-200 rounded-lg text-red-700`.

### 2.6 Heading hierarchy defect (line 6)

`<h1 class="text-3xl font-bold text-theme-primary mb-2">Estadísticas de Procedimientos</h1>` competes with topbar `<h1>` (defect 7 family per `DashboardAppShellTest::test_dashboard_greeting_not_h2_or_h1_uses_text_lg_font_medium`). Page-level `<h1>` should be removed.

---

## 3. Gap Analysis

### 3.1 Tokens — no extensions needed

| Need | Token | Status |
|---|---|---|
| Page surface | `var(--color-canvas)` via canvasRoutes | ALREADY wired (PR0) |
| Card border | `var(--color-hairline)` | MISSING — needs inline `:style` on each KPI `<UiCard>` |
| Card shadow | `var(--elevation-2)` | MISSING |
| Numeric tabular | `tabular-nums` + `var(--font-features-tabular-nums)` | MISSING on 3 KPIs + 3 table numerics + 2 specialty numerics |
| Empty state | `<UiEmptyState>` | MISSING — line 81 ad-hoc `<div>` |
| Loading skeleton | `<UiSkeleton>` | MISSING |
| Error banner | systemRed-50/200/700 ramp + `var(--radius-control)` | MISSING |
| Filter input focus | `var(--focus-ring-default)` via `<UiInput>` | ALREADY wired |

### 3.2 Primitives — all exist

| Pattern | Action |
|---|---|
| `<AppLayout>` | already used |
| `<PageHeader>` | REPLACE custom header (lines 3–17) |
| Filter card + `<UiInput>` + `<UiButton>` | keep — already proven |
| KPI / counter card | ADOPT Dashboard anatomy |
| Table | keep raw — class-string swap |
| Empty state | ADOPT `<UiEmptyState>` |
| Loading skeleton | ADOPT `<UiSkeleton>` |
| Error banner | ADOPT tokenised div |
| Currency | ADOPT `formatPENLabel` from `useFormatters` |

### 3.3 Cross-cutting

`canvasRoutes` extension completed in PR0. No additional AppLayout work this slice.

---

## 4. Risk Assessment

| # | Risk | Mitigation |
|---|---|---|
| 1 | **Router registration missing.** | PR1 includes additive `app.js` edit (~5 lines). |
| 2 | **Heading hierarchy defect** (page-level `<h1>`). | Adopt `<PageHeader>` — no page-level h1. |
| 3 | **No `<UiSkeleton>` for loading.** | Mirror Dashboard's loading block (3 card skeletons + 6 list skeletons). |
| 4 | **KPI numbers jitter on update.** | Add `tabular-nums` + `var(--font-features-tabular-nums)` to every numeric element. |
| 5 | **`text-green-600` raw Tailwind** (line 60). | `text-systemGreen-600`. |
| 6 | **`bg-red-50 border-red-200 text-red-700` raw Tailwind** (line 147). | Tokenised systemRed ramp. |
| 7 | **Role banner missing.** | Inline disclosure at top of page per OQ-EC-3. |
| 8 | **Currency formatter inline** (lines 117, 142). | Adopt `formatPENLabel` from `useFormatters`. |
| 9 | **No per-module test exists.** | New `ProcedureStatsAppShellTest.php` extending `ModuleAppShellTestCase`. |

Total: 9 risks. None are show-stoppers.

---

## 5. Suggested Slice Plan (1 PR)

**PR1 — ProcedureStats tokenise + router wire-up + role disclosure**

| Field | Value |
|---|---|
| Name | `pr1-procedure-stats-tokenise-and-wire-up` |
| Scope | (1) `app.js`: additive `/procedure-stats` route (~5 lines). (2) `ProcedureStatsPage.vue`: full tokenisation + KPI anatomy adoption + skeleton loading + `<UiEmptyState>` + `formatPENLabel` + `<PageHeader>`. (3) `tests/Unit/DesignSystem/ProcedureStatsAppShellTest.php`: new test. |
| Risk | Low |
| Dependencies | PR0 (already merged) |
| Reversibility | `git revert <pr-sha>` removes router registration + tokenisation + test |
| Line estimate | ~140 authored |

### PR1 checklist

- [ ] Add `{ path: '/procedure-stats', name: 'procedure-stats', component: () => import('./modules/procedure-catalog/ProcedureStatsPage.vue'), beforeEnter: requireAuth }` to `routes[]` in `app.js`
- [ ] Replace page header with `<PageHeader title="..." :subtitle="...">`
- [ ] Remove `<h1 class="text-3xl font-bold text-theme-primary mb-2">` (line 6)
- [ ] Each KPI `<UiCard>` gains `:style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"`
- [ ] Each KPI number: `tabular-nums` + `style="font-feature-settings: var(--font-features-tabular-nums)"`
- [ ] Line 60: `text-green-600` → `text-systemGreen-600`
- [ ] Lines 85, 99: `border-theme` → `border-[color:var(--color-hairline)]`
- [ ] Line 136: rewrite specialty tile (hairline border, `var(--radius-control)`)
- [ ] Specialty + table numerics get `tabular-nums` + `font-feature-settings`
- [ ] Lines 117, 142: adopt `formatPENLabel` from `useFormatters`
- [ ] Line 81: ad-hoc `<div>` → `<UiEmptyState title="Sin datos" description="..." />`
- [ ] Loading state: 3 `<UiSkeleton variant="card">` + 6 `<UiSkeleton variant="list">`
- [ ] Line 147: tokenised systemRed error banner
- [ ] Role banner per OQ-EC-3
- [ ] New test: `ProcedureStatsAppShellTest.php` with 10 assertions (5 inherited + 5 category-specific)
- [ ] Playwright sweep at 1440x900 (mandatory) + 390x844 (optional per OQ#8)

### Visual verification

1. Login as `admin@test.com` (or `finanzas@test.com`).
2. Navigate to `/procedure-stats` (verifies router registration).
3. Eyeball-compare against DashboardPage KPI anatomy.

### Strict TDD

1. Write `ProcedureStatsAppShellTest.php` with all 10 assertions. RED.
2. Apply template-level class-string replacements. GREEN.
3. Verify via Playwright at 1440x900.

---

## 6. Open Questions for Proposal Phase

- **OQ-EC-1 — Router registration scope [MUST RESOLVE]**: PR1 includes additive router edit. Alternative (separate prerequisite PR) adds a PR to chain for trivial edit. **Recommendation: PR1 includes it.**
- **OQ-EC-2 — `<UiCard>` default border**: keep per-card inline `:style` (Dashboard pattern) for PR1.
- **OQ-EC-3 — Role banner**: inline `<div>` disclosure for PR1; flag primitive extraction for spec phase.
- **OQ-EC-4 — `<UiDataTable>` adoption**: keep raw `<table>`, tokenise borders + add `tabular-nums`.
- **OQ-EC-5 — `<PageHeader>` adoption**: yes — removes page-level `<h1>` defect.

---

## 7. References

- Global: `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (§6.2 slice 1)
- Global proposal: `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (§2.1 row 17, §7.3, §7.4)
- Vertical slice design: `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md`
- Target: `resources/js/modules/procedure-catalog/ProcedureStatsPage.vue` (198 lines)
- KPI exemplar: `resources/js/modules/dashboard/DashboardPage.vue`
- Router: `resources/js/app.js` (lines 32–203) — `/procedure-stats` NOT registered
- `AppLayout.vue` lines 518–557 — `/procedure-stats` IS in canvasRoutes
- Tests: `tests/Unit/DesignSystem/{DashboardAppShellTest,ModuleAppShellTestCase,AppLayoutCanvasRoutesTest,AppointmentTypesAppShellTest}.php`
- Tokens: `resources/js/design-system/tokens.js` (systemGreen L47–56, systemRed L58–67)
- Backend: `routes/api.php:189` — `GET /api/admin/procedure-stats` exists
- AGENTS.md §5 row 17 — Estadísticas catálogo (administrador, finanzas)

---

## Key Learnings

1. ProcedureStatsPage is reachable today only via the 404 catch-all — `app.js` lacks the `/procedure-stats` registration; without the route, the polished output is invisible.
2. The page has zero proven-token references and zero `<style scoped>` blocks — cleanest Tier-1 candidate for the second proving ground.
3. The 3 KPI counters do NOT reuse Dashboard's fixed-slot anatomy (no h-4/h-12/h-6/h-4 slots, no `--color-hairline`/`--elevation-2` inline styles, no `tabular-nums`).
4. No status pills — PR0 `<UiStatusBadge>` extraction doesn't bind here; PR2 (Quotations) is the first consumer.
5. Numeric tables require BOTH `tabular-nums` Tailwind utility AND `style="font-feature-settings: var(--font-features-tabular-nums)"` per standing contract.

---

*End of category explore. Next: `sdd-propose` (must resolve OQ-EC-1 before apply).*
