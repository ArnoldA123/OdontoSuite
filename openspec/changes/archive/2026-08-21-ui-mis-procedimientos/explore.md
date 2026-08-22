# Explore: mis-procedimientos (ui-rollout-all-modules-2026-08)

> SDD phase: `sdd-explore`. Single-page Tier-1 module. Read-only; no proposal, no design, no tasks.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `mis-procedimientos` |
| Date | 2026-08-21 |
| Phase | explore (1 of 6, category-level) |
| Author | `sdd-explore` sub-agent |
| Pace / Artifact store | auto / hybrid |
| Delivery strategy | auto-chain |
| Review budget | 400 authored lines / PR |
| Strict TDD | true |
| PR0 status | `AppLayout.canvasRoutes` ALREADY includes `/my-procedures` at line 547 (PR0). `<UiStatusBadge>` primitive already exists. |
| Closest precedent | `archive/2026-08-12-ui-pacientes/explore.md` |
| Roles | All clinical roles per AGENTS.md §5 |

---

## 1. File Inventory

| Route | Component file | Lines | `<style scoped>` | `@apply` | Inline `@keyframes` | Legacy refs | Proven refs |
|---|---|---|---|---|---|---|---|
| `/my-procedures` | `MyProceduresPage.vue` | 276 | **0** | **0** | **0** | **~22** (12 patterns) | **0** |

Single file, no sibling components, no components/ subdir. Glob confirmed.

**Composables consumed:** `useProcedureFavorites`, `useToast`, `useRouter`.
**Primitives consumed:** `<AppLayout>`, `<PageHeader>`, `<UiButton>`, `<UiCard>`, `<LoadingSpinner>` (legacy name — should become `<UiLoadingSpinner>` per AGENTS.md §7).

---

## 2. Current Visual State

Zero proven-token references. ~22 legacy-token references across 12 distinct classes.

### Legacy patterns with line numbers

| # | Pattern | Lines | Replacement |
|---|---|---|---|
| 1 | `border-theme` | 39, 49, 138, 161 | `border-hairline` or `border-[color:var(--color-hairline)]` |
| 2 | `divide-theme` | 161 | `divide-[color:var(--color-hairline)]` |
| 3 | `bg-primary-50 text-primary-700` | 56 (order-index `#N` pill) | `bg-systemBlue-50 text-systemBlue-700` or `<UiStatusBadge variant="info">` |
| 4 | `focus:ring-2 focus:ring-primary-500 focus:border-accent` | 138 (raw `<input>`) | Replace raw `<input>` with `<UiInput>` |
| 5 | `bg-theme-surface-elevated` | 49, 138 | Keep semantic alias (resolves to `#ffffff`); canvas/surface separation from `<AppLayout>` |
| 6 | `hover:bg-theme-surface` | 166 | `hover:bg-canvas` |
| 7 | `text-yellow-500` (raw Tailwind) | 28, 171 (star icon + label) | `text-systemYellow-500` |
| 8 | `text-red-500 hover:text-red-700` (raw Tailwind) | 105 (Quitar icon) | `text-systemRed-500 hover:text-systemRed-700` |
| 9 | `disabled:opacity-30` | 72, 89 | `disabled:opacity-40` (iOS convention) — soft tolerance |
| 10 | `border-2 border-dashed border-theme` | 39 (empty state) | `border-2 border-dashed border-hairline` |
| 11 | `rounded-lg` (without var) | 39, 49, 138, 161 | `rounded-[var(--radius-control)]` for inputs; `rounded-[var(--radius-ios)]` for cards |
| 12 | `font-mono` | 53, 170 | Drop — proven language uses system sans + tabular-nums |

**Currency formatting (mandatory swap per PAGOS-DLR-MNY-002):**
- Line 65: `S/ {{ Number(fav.default_cost).toFixed(2) }}`
- Line 178: `S/ {{ Number(fav.default_cost).toFixed(2) }}`

Replace with `formatCurrency(fav.default_cost)` from `useFormatters`.

**Numeric cells (no `tabular-nums`):** 6 spans (3 numerics × 2 cards) — `default_duration_minutes`, `default_cost`, `position`. Add `font-feature-settings: var(--font-features-tabular-nums)`.

**No status pills / no modals / no tables / no pagination chrome.**

---

## 3. Gap Analysis

### 3.1 Tokens — no extensions needed

| Need | Token | Action |
|---|---|---|
| Page surface | `var(--color-canvas)` | Inherited via `<AppLayout>` (PR0 wired) |
| Card / input border | `var(--color-hairline)` | Replace `border-theme` (4 lines) |
| Card elevation | `var(--elevation-1)` | `<UiCard variant="glass">` consumes internally |
| Input focus ring | `var(--focus-ring-default)` | Replace raw `<input>` with `<UiInput>` |
| Numeric craft | `--font-features-tabular-nums` | Add to 6 numeric spans |
| Radius | `var(--radius-control/ios)` | Contextual replace (4 sites) |
| Status colour ramps | `systemBlue-50/700`, `systemRed-500/700`, `systemYellow-500` | Replace raw Tailwind ramps |
| Currency formatting | `formatCurrency` from `useFormatters` | Replace 2 inline literals |

### 3.2 Primitives — all exist

| Pattern | Action |
|---|---|
| `<AppLayout>`, `<PageHeader>`, `<UiCard>`, `<UiButton>` | Keep |
| `<UiInput>` | Replace raw `<input>` at line 134 |
| `<UiLoadingSpinner>` | Rename import (line 211) + tag references |
| `<UiStatusBadge>` | Optional: `#N` pill at line 56 |
| `<UiEmptyState>` | Optional: empty states at lines 38–43, 192–196 |
| `<UiButton size="icon">` | Optional: 3 raw icon-buttons at lines 69–118 |

### 3.3 Components

No cross-cutting changes needed (canvas surface inherited from PR0). No tab bar, modal, sheet, table, pagination.

### 3.4 Motion

No inline `@keyframes`. `LoadingSpinner` already inherits proven motion. No `<TransitionGroup>` (well within scope but OUT per global proposal §11 — no new animation deps).

**Reverb realtime risk:** NONE. `useProcedureFavorites` is fetch-only.

---

## 4. Risk Assessment

| Risk | Severity | Mitigation |
|---|---|---|
| `<UiInput>` swap drops `pl-9` search-icon inset | Low | `prefix-icon` slot OR wrapper `<div class="relative">` (ReceptionProceduresPage.vue precedent) |
| 3 raw icon `<button>`s lack focus rings | Low | Migrate to `<UiButton size="icon" variant="ghost">` OR add `:focus-visible` → `var(--focus-ring-default)` |
| `disabled:opacity-30` vs `disabled:opacity-40` | Low | Soft tolerance — migrate to `40` for `<UiButton>` parity |
| `S/ ${...toFixed(2)}` bypasses `formatCurrency` | Low | Mandatory swap per PAGOS-DLR-MNY-002 precedent |
| Composable `<script>` block unchanged | None | Read-only — UI changes are template-level |

**Overall risk: Low.** Diff ~22 class-string replacements + 2 currency-format swaps + 3 focus-ring additions + 1 import rename. Estimated ~180–250 authored lines + ~80 lines of test.

---

## 5. Suggested Slice Plan

**Single PR** (~280–330 total lines, well under 400-line budget).

**Proposed PR name:** `pr-mis-procedimientos-tokenise`

**Scope:**
1. `border-theme` (4 lines) → `border-hairline` (or `border-[color:var(--color-hairline)]`)
2. `divide-theme` (1 line) → `divide-[color:var(--color-hairline)]`
3. `bg-primary-50 text-primary-700` (1 line) → `bg-systemBlue-50 text-systemBlue-700` OR `<UiStatusBadge variant="info">`
4. Raw `<input>` (line 134) → `<UiInput>` with search-icon slot or wrapper
5. 3 raw `<button>` icon-buttons (lines 69–118) → `<UiButton size="icon" variant="ghost">` OR `:focus-visible` + `var(--focus-ring-default)`
6. `text-yellow-500` (28, 171) → `text-systemYellow-500`
7. `text-red-500 hover:text-red-700` (105) → `text-systemRed-500 hover:text-systemRed-700`
8. `hover:bg-theme-surface` (166) → `hover:bg-canvas`
9. 2 inline `S/ ${...toFixed(2)}` (65, 178) → `formatCurrency(fav.default_cost)` from `useFormatters`
10. `tabular-nums` (or `font-feature-settings: var(--font-features-tabular-nums)`) on 6 numeric spans
11. `LoadingSpinner` import (line 211) → `UiLoadingSpinner` per AGENTS.md §7
12. `rounded-lg` (4 sites) → contextual `var(--radius-control|ios)`
13. Optional: hand-built empty states → `<UiEmptyState>`

**Test gate (new file):** `tests/Unit/DesignSystem/MyProceduresPageAppShellTest.php` extending `ModuleAppShellTestCase`.

Inherits DLR-R-001/002/004/021. Adds per-category rules:
- `test_search_uses_ui_input` — `<UiInput v-model="search" ...>` present; raw `<input>` absent.
- `test_order_pill_uses_tokenized_blue_ramp` — `bg-systemBlue-50 text-systemBlue-700` or `<UiStatusBadge variant="info">` present.
- `test_loading_spinner_import_is_ui_prefixed` — `import UiLoadingSpinner` (NOT `import LoadingSpinner`).
- `test_format_currency_used_for_pen_values` — `formatCurrency(` present; `Intl.NumberFormat` absent.
- `test_tabular_nums_on_numeric_cells` — `tabular-nums` or `font-feature-settings: var(--font-features-tabular-nums)` ≥ 6 times.
- `test_remove_favorite_uses_systemred_ramp` — `text-systemRed-500` present; raw `text-red-500` absent.
- `test_yellow_ramp_is_systemyellow` — `text-systemYellow-500` present; raw `text-yellow-500` absent.
- `test_raw_icon_buttons_consume_focus_ring_token` — for each of 3 raw `<button>`s, either `<UiButton>` used OR `:focus-visible` + `var(--focus-ring-default)` present.
- `test_no_border_theme_literal` (DLR-R-002 re-assertion).
- `test_no_style_scoped` (DLR-R-021 re-assertion — already passes).

**Dependencies:** PR0 (merged). `useFormatters`, `<UiInput>`, `<UiButton>`, `<UiCard>`, `<UiEmptyState>`, `<UiLoadingSpinner>`, `<UiStatusBadge>` all exist.

**Visual verification:** `playwright-cli screenshot` at 1440x900. Login as `odonto@test.com`. Save to `.playwright-cli/screenshots-rollout/my-procedures-1440x900.png` (gitignored).

**Reversibility:** independently revertible via `git revert <merge-sha>`. Composable, API, routing untouched.

---

## 6. Open Questions (for `sdd-propose`)

1. **`<UiEmptyState>` migration** of hand-built empty states (lines 38–43, 192–196)? **Recommendation: YES.**
2. **`<UiButton size="icon">` for 3 raw icon-buttons** vs keep raw + add `:focus-visible` ring. **Recommendation: keep raw + `:focus-visible`.**
3. **`useProcedureFavorites` pagination.** Server-side `per_page: 100`. **Recommendation: out of scope** (separate slice).
4. **Rank pill (`#N`) → `<UiStatusBadge>`?** Counter, not state. **Recommendation: keep inline ramp.**
5. **`disabled:opacity-30` → `disabled:opacity-40`.** **Recommendation: migrate.**
6. **`font-mono` (53, 170) → system sans + `tabular-nums`.** **Recommendation: migrate.**
7. **Single PR vs PR3 cluster (with recepción + procedure-catalog).** **Recommendation: standalone PR (low blast radius).**

---

## 7. References

- `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` §3.2 row 15 (Tier-1, 8.0 KB)
- `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §2.1 row 15, §7.5 PR3 cluster plan
- `openspec/changes/archive/2026-08-12-ui-pacientes/explore.md` (closest precedent)
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` D1–D16 + G1–G13
- `openspec/changes/archive/2026-08-12-ui-pagos/explore.md` (alternate precedent)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (DLR-R-001/002/004/021 base rules)
- `tests/Unit/DesignSystem/CajaPagesAppShellTest.php` (PAGOS-DLR-MNY-002 currency precedent)
- `tests/Unit/DesignSystem/PatientsListAppShellTest.php` (single-page list test precedent)
- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` (`/my-procedures` already at line 547)
- `resources/js/design-system/tokens.js` (token source-of-truth)
- `resources/css/tokens.generated.css` (generated CSS)
- `resources/js/components/layout/AppLayout.vue` line 507–556 (`canvasRoutes`)
- `resources/js/components/ui/{UiInput,UiButton,UiCard,UiEmptyState,UiLoadingSpinner,UiStatusBadge}.vue`
- `resources/js/modules/my-procedures/MyProceduresPage.vue` (276 lines)
- `resources/js/composables/useProcedureFavorites.js` (147 lines, read-only)
- `resources/js/composables/useFormatters.js` (`formatCurrency` canonical PEN formatter)
- AGENTS.md §5 (module inventory) + §7 (`Ui*`-prefix convention)

---

## Key Learnings

1. Tier-1 single-page module (276 lines, 1 file, 0 sibling components) consumes zero proven-token references despite PR0 already wiring `canvasRoutes`.
2. 12 distinct legacy class patterns, zero `<style scoped>` blocks, zero inline `@keyframes`. No `<script>` block touched.
3. PR0 has already wired `/my-procedures` into `AppLayout.canvasRoutes` at line 547 — canvas surface inherited trivially.
4. Two inline `S/ ${...toFixed(2)}` literals bypass canonical `formatCurrency`; PAGOS-DLR-MNY-002 makes the swap mandatory.
5. `<LoadingSpinner>` import at line 211 violates AGENTS.md §7 (`Ui*`-prefix convention); rename to `UiLoadingSpinner` is non-cosmetic with no rendering impact.

---

*End of category explore. Next phase: `sdd-propose` for `mis-procedimientos`.*
