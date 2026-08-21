# Explore: recepcion-procedimientos (ui-rollout-all-modules-2026-08)

> SDD phase: sdd-explore. Read-only. Tier-1 single-page module — smallest in the project.

## Metadata

| Key | Value |
|---|---|
| Change | ui-rollout-all-modules-2026-08 |
| Category | recepcion-procedimientos |
| Date | 2026-08-21 |
| Phase | explore |
| Pace / Artifact store | auto / hybrid |
| Strict TDD | true |
| Delivery strategy | auto-chain (single PR for this category) |
| Review budget | 400 authored lines / PR |

## 1. File Inventory

| File | Lines | Style scoped | @apply | @keyframes | Legacy count | Proven count |
|---|---|---|---|---|---|---|
| `ReceptionProceduresPage.vue` | 180 | NO | 0 | 0 | 14 | 0 |

The module directory is the smallest in the project — a single `.vue` file with no scoped `<style>` block, no subcomponents, no helper composables, no modal flows. Internal search/specialty `<select>` controls are inline raw HTML, not primitives.

## 2. Current Visual State

ReceptionProceduresPage is a Tier-1 CRUD-style read-only view: a PageHeader with a "Volver" UiButton, a glass-variant `<UiCard>` wrapping a 2-column filter bar (text search + specialty `<select>`), a `LoadingSpinner` while loading, a custom "no results" message, a responsive 1/2/3-column grid of `<UiCard variant="elevated">` cards each showing `{code badge, specialty, name, description, duration, price}`, and a single `<UiPagination>` at the bottom. Server interaction is plain (`useProcedureCatalog` + `useSpecialties`); no realtime, no third-party widgets, no `<style scoped>`, no inline `@apply`, no inline `@keyframes`.

Legacy patterns (14 occurrences): `border-theme`, `bg-theme-surface-elevated`, `focus:ring-primary-500 focus:border-accent`, `rounded-lg`, `bg-primary-50 text-primary-700`, `text-accent`, `text-theme-primary`, `text-theme-secondary`. The `<UiCard>` primitives themselves are already tokenised.

The page surface sits on `/reception-procedures`, which is NOT in `canvasRoutes` — meaning `bg-canvas` is NOT applied and cards read as outlines on white.

## 3. Gap Analysis

### 3.1 Tokens

| Need | Token | Action |
|---|---|---|
| Page surface | `var(--color-canvas)` + `bg-canvas` | Add `/reception-procedures` to `canvasRoutes` |
| Input border | `var(--color-hairline)` | Replace `border-theme` on both inputs |
| Input background | `bg-systemBackground` on `bg-canvas` page | Replace `bg-theme-surface-elevated` on both inputs |
| Card divider inside procedure card | `var(--color-hairline)` | Replace `border-t border-theme` on footer divider |
| Procedure code chip | `<UiBadge variant="primary" size="sm">` | Replace inline `bg-primary-50 text-primary-700` span |
| Price text | `text-systemBlue-600 tabular-nums` | Replace `text-accent` |
| Focus ring | `var(--focus-ring-default)` | Replace inline `<input>` with `<UiInput>` |
| Numeric craft | `tabular-nums` + `--font-features-tabular-nums` | Add to price display |
| Semantic colours on labels | `text-label.label` / `text-label.secondaryLabel` | Replace `text-theme-primary` / `text-theme-secondary` |

Token extensions needed: NONE.

### 3.2 Primitives

| Pattern | Current | Target |
|---|---|---|
| Search input | Line 31-36 raw `<input>` + pl-9 absolute SVG prefix | `<UiInput v-model="filters.search" type="search" placeholder="...">` + prefix slot |
| Specialty filter | Line 54-62 raw `<select>` | `<UiSelect v-model="filters.specialty">` + `<option>` list |
| Procedure code chip | Inline span `bg-primary-50 text-primary-700` | `<UiBadge variant="primary" size="sm" class="font-mono">` |
| Procedure cards | `<UiCard variant="elevated" class="hover-lift transition-shadow">` | KEEP primitive; drop `hover-lift transition-shadow` |
| "Volver" button | `<UiButton variant="secondary">` | KEEP primitive |
| Pagination | `<UiPagination>` | KEEP primitive |
| Loading state | `<LoadingSpinner>` | KEEP primitive |
| Empty results | Inline `<div class="py-12 text-center text-theme-secondary">` | `<UiEmptyState title="Sin resultados" description="...">` |

New primitives required: NONE.

### 3.3 Components

Cross-cutting dependency: `AppLayout.vue` `canvasRoutes` must be extended to include `/reception-procedures`. Single additive edit. This is slice 0 (prerequisite) per parent explore — coordinately added in this category PR.

### 3.4 Motion

No motion. No inline `@keyframes`. The `:hover` translateY(-2px) of `<UiCard variant="elevated">` is pre-wired from the primitive.

## 4. Risk Assessment

Tier-1, lowest-blast-radius module.

- Canvas surface wiring is shared with 16 other modules. Additive; no regression risk.
- Raw `<input>` / `<select>` replaced with `<UiInput>` / `<UiSelect>` — proven in PR2. v-model behaviour identical.
- No third-party CSS, no `@apply` blocks, no inline `<style>` — Tailwind purge risk negligible.
- No realtime, no Reverb, no `<script>` logic touched — rollout is template-only.

## 5. Suggested Slice Plan

**ONE PR** (~80-120 lines authored):

1. `AppLayout.vue` — Add `'/reception-procedimientos'` to `canvasRoutes`.
2. `ReceptionProceduresPage.vue` — Replace raw `<input>`/`<select>` with `<UiInput>`/`<UiSelect>`; replace empty-results div with `<UiEmptyState>`; replace procedure code chip with `<UiBadge variant="primary" size="sm" class="font-mono">`; replace `text-accent` price with `text-systemBlue-600 tabular-nums`; drop `hover-lift transition-shadow`; replace `text-theme-*` with iOS label ramps.
3. `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` (new) — sibling pattern of `DashboardAppShellTest`: page surface `bg-canvas`, filter controls consume `var(--focus-ring-default)`, divider uses `var(--color-hairline)`, empty-state is `<UiEmptyState>`, currency uses `tabular-nums`.
4. Playwright visual verification — `.playwright-cli/screenshots-recepcion-procedimientos/` with 2 PNGs (1440x900 + 390x844).

## 6. Open Questions for Proposal Phase

1. Price display: `<UiBadge variant="success">` vs plain `text-systemBlue-600 tabular-nums`. **Recommendation: the latter.**
2. Procedure code chip: keep `font-mono` inside `<UiBadge>` via passthrough class. **Recommendation: YES.**
3. `hover-lift` utility vs primitive hover prop on `<UiCard variant="elevated">`. **Recommendation: drop the class.**
4. Empty-results Spanish copy.
5. `<UiSelect>` primitive confirmation vs raw `<select>` (since this is a static list).

## 7. References

- Parent explore: `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (§1, §3, §4)
- Token source-of-truth: `resources/js/design-system/tokens.js`
- Primitives consumed: `resources/js/components/ui/{Card,Button,Input,Badge,EmptyState,DataTable,Pagination}.vue`
- Source file: `resources/js/modules/reception-procedures/ReceptionProceduresPage.vue` (180 lines, 5.2 KB)
- Composables: `useProcedureCatalog`, `useSpecialties` (read-only, no logic changes)
- Test gate: `tests/Unit/DesignSystem/{TokensModuleTest,GeneratedTokensCssTest,PrimitivePressTest,DashboardAppShellTest,LoginPageRenderTest}.php`
- Vertical-slice precedent: `DashboardPage.vue` (`tabular-nums`, `<UiEmptyState>`)

## Key Learnings

1. Recepcion-procedimientos is the smallest module in the entire project — single 180-line Vue file, no scoped styles, no inline keyframes. Safest Tier-1 proving ground.
2. The page uses raw `<input>` and `<select>` for both filter controls instead of `<UiInput>` / `<UiSelect>` — missed primitive adoption is the highest-impact find.
3. `<UiCard variant="elevated">` and `<UiCard variant="glass">` are already tokenised via PR2 — no card-level work needed, only page surface wiring and inner template class strings.
4. Zero proven-token references in the page template — rollout is pure inheritance-via-primitives plus a 1-line additive change to `AppLayout.vue`.
5. 5.2 KB footprint fits inside a single PR well under the 400-line authored budget — cheapest possible category rollout.

---

*End of explore — recepcion-procedimientos. Next phase: `sdd-propose`.*
