# Proposal: mis-procedimientos (ui-rollout-all-modules-2026-08)

> SDD phase: sdd-propose. Single-page Tier-1 category. English artifacts.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `mis-procedimientos` |
| Date | 2026-08-21 |
| Phase | propose (2 of 6) |
| Author | `sdd-propose` sub-agent |
| Slice | `PR-mis-procedimientos-01` (single PR, ~280-330 lines) |
| Risk | Low |
| Roles | All clinical (per AGENTS.md §5) |

### Preflight snapshot (cached, do NOT re-ask)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached
strict_tdd: true
```

---

## 1. Intent

Bring `MyProceduresPage.vue` (276 lines, single file, zero sibling components, zero proven-token references) to the Apple-only design language. Mechanical class-string replacement + `<UiInput>` migration + `formatCurrency` adoption. NO new tokens, NO new primitives, NO `<style scoped>` blocks, NO `<script>` block edits.

The clinical user (odontólogo / implantólogo / higienista / recepcionista on the clinical set) marks frequently-used procedures as favourites and reorders them by drag, plus searches the procedure catalog by name/code. PR0 already wired `/my-procedures` into `AppLayout.canvasRoutes` at line 547 — canvas surface inherited trivially. The defect is concentrated: 22 legacy-token references across 12 distinct class patterns; two inline `S/ ${...toFixed(2)}` literals that bypass canonical `formatCurrency`; one `<LoadingSpinner>` import that violates the AGENTS.md §7 `Ui*`-prefix convention. No `<style scoped>` block, no inline `@keyframes`, no `<script>` edit.

**Why now:** Tier-1 module, smallest blast radius after `reception-procedures` (5.2 KB). Fits a single PR with ~280-330 authored lines including a fresh `MyProceduresPageAppShellTest`. Mechanical replacement; zero design risk; no realtime contract (composable is fetch-only); no third-party widget; no `<style scoped>` rewrite. The user's stated intent — extend the proven language to every module — applies cleanly here. PAGOS-DLR-MNY-002 makes the `formatCurrency` swap mandatory.

---

## 2. Scope

### 2.1 Files to modify

| File | Lines | Change summary |
|---|---|---|
| `resources/js/modules/my-procedures/MyProceduresPage.vue` | 276 | 22 legacy class replacements (12 patterns), 2 `formatCurrency` swaps, 6 `tabular-nums` additions, 3 `:focus-visible` ring additions, 1 import rename (`LoadingSpinner` → `UiLoadingSpinner`), empty-state markup migrated to `<UiEmptyState>`, no `<script>` edit |

### 2.2 Files NOT to modify

- `resources/js/composables/useProcedureFavorites.js` (147 lines, composable, frozen)
- `resources/js/composables/useFormatters.js` (frozen; `formatCurrency` already exported)
- `resources/js/app.js` (route `/my-procedures` already registered)
- `resources/js/components/layout/AppLayout.vue` (`canvasRoutes` already includes `/my-procedures` at line 547 per PR0)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (already imported; subclass extends it)

### 2.3 Backend changes

**NONE.** No controller, no route, no migration, no resource, no listener, no policy, no model. The rollout is UI-only.

---

## 3. Out of scope (deferred)

1. `<UiEmptyState>` migration: **IN scope** per OQ-1 resolution (replaces hand-built empty states at lines 38-43 and 192-196).
2. `<UiButton size="icon">` migration for raw icon-buttons: **DEFER** per OQ-2. Keep raw `<button>` + add `:focus-visible` → `var(--focus-ring-default)`.
3. `useProcedureFavorites` pagination (`per_page: 100`): **OUT** per OQ-3 (separate slice).
4. `<UiStatusBadge>` adoption for `#N` rank pill (line 56): **KEEP inline ramp** per OQ-4 (counter, not state).
5. New `<UiStatusBadge>` primitive: **OUT** of this category; PR0 owns it (per global OQ-5).
6. Pagination primitive de-duplication: **OUT** of this category; rides global PR3 cluster per global OQ-7.
7. Settings/branches + Settings/payment-methods: **OUT** per global OQ-3.
8. Dark mode / two-tone numerals / per-KPI sparklines: **OUT** per global proposal §3.

---

## 4. Acceptance criteria

The PR is complete when ALL of the following hold:

- [ ] **Zero `border-theme` literals.** All 4 occurrences (lines 39, 49, 138, 161) replaced with `border-[color:var(--color-hairline)]` (or `border-hairline` if the Tailwind utility is wired). DLR-R-002 re-asserted by `MyProceduresPageAppShellTest::test_no_legacy_border_theme_literal`.
- [ ] **Zero `divide-theme` literals.** Line 161 replaced with `divide-[color:var(--color-hairline)]`.
- [ ] **`bg-primary-50 text-primary-700` rank pill (line 56)** migrated to `bg-systemBlue-50 text-systemBlue-700` (inline ramp; `<UiStatusBadge>` deferred per OQ-4).
- [ ] **Raw `<input>` (line 134) replaced with `<UiInput v-model="search" ...>`.** The search-icon wrapper `<div class="relative">` is preserved; `<svg>` icon inset stays at `left-3`. New test: `MyProceduresPageAppShellTest::test_search_uses_ui_input` asserts `<UiInput` is present and raw `<input` (with `class="...focus:ring-primary-500..."`) is absent.
- [ ] **Three raw icon `<button>`s (lines 69-118)** keep raw markup but gain `:focus-visible` + `box-shadow: var(--focus-ring-default)` (or equivalent class) so each is keyboard-reachable. New test: `MyProceduresPageAppShellTest::test_raw_icon_buttons_consume_focus_ring_token`.
- [ ] **`bg-theme-surface-elevated`** retained as semantic alias (resolves to `#ffffff`); no semantic change.
- [ ] **`hover:bg-theme-surface` (line 166) → `hover:bg-canvas`.**
- [ ] **`text-yellow-500` (lines 28, 171)** replaced with `text-systemYellow-500`.
- [ ] **`text-red-500 hover:text-red-700` (line 105)** replaced with `text-systemRed-500 hover:text-systemRed-700`.
- [ ] **`disabled:opacity-30` (lines 72, 89)** replaced with `disabled:opacity-40` (iOS convention parity with `<UiButton>`).
- [ ] **`border-2 border-dashed border-theme` (line 39, empty state)** replaced with `border-2 border-dashed border-[color:var(--color-hairline)]`.
- [ ] **Two `S/ {{ Number(...).toFixed(2) }}` literals (lines 65, 178)** replaced with `{{ formatCurrency(...) }}` from `useFormatters`. New test: `MyProceduresPageAppShellTest::test_format_currency_used_for_pen_values` asserts `formatCurrency(` is referenced and `Intl.NumberFormat` is absent in the template.
- [ ] **Six numeric spans (3 numerics × 2 cards: `default_duration_minutes`, `default_cost`, `position`)** carry `font-feature-settings: var(--font-features-tabular-nums)` (Tailwind `tabular-nums` utility). New test: `MyProceduresPageAppShellTest::test_tabular_nums_on_numeric_cells` asserts ≥6 occurrences of `tabular-nums` OR `font-feature-settings: var(--font-features-tabular-nums)`.
- [ ] **`rounded-lg` (4 sites: lines 39, 49, 138, 161)** replaced contextually: input → `rounded-[var(--radius-control)]`; cards → `rounded-[var(--radius-ios)]` or `rounded-[var(--radius-card-lg)]` per design language; dividers follow card radius.
- [ ] **`font-mono` (lines 53, 170) → system sans + `tabular-nums`.** Drop `font-mono text-xs`; keep `text-xs text-theme-secondary tabular-nums`.
- [ ] **`<LoadingSpinner>` import (line 211) + 2 usages (lines 34, 156) → `<UiLoadingSpinner>`.** Import path and tag rename. New test: `MyProceduresPageAppShellTest::test_loading_spinner_import_is_ui_prefixed`.
- [ ] **Hand-built empty states (lines 38-43, 192-196) → `<UiEmptyState>`.** Per OQ-1 YES.
- [ ] **`<style scoped>` block count: zero (DLR-R-021 re-asserted).** Already zero; test asserts the rule, not the literal.
- [ ] **`<script setup>` block untouched.** `useProcedureFavorites` contract (`getFavorites`, `getForMe`, `addFavorite`, `removeFavorite`, `reorderFavorites`) preserved verbatim. `useToast` calls preserved verbatim. `useRouter().push('/dashboard')` preserved verbatim.
- [ ] **Visual verification:** `playwright-cli screenshot --filename=.playwright-cli/screenshots-rollout/my-procedures-1440x900.png` (gitignored). Login as `odonto@test.com`.
- [ ] **CI green:** `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).

---

## 5. Risks + mitigations

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | `<UiInput>` swap drops `pl-9` search-icon inset | Low | Preserve `<div class="relative">` wrapper; `<UiInput>` accepts class passthrough for the `pl-9` left padding. ReceptionProceduresPage precedent applies (per global explore §3.2). |
| 2 | 3 raw icon `<button>`s lack focus rings | Low | Add `:focus-visible` ring token to each; assert presence in `MyProceduresPageAppShellTest::test_raw_icon_buttons_consume_focus_ring_token`. Per OQ-2 keep raw + ring (no migration to `<UiButton size="icon">`). |
| 3 | `disabled:opacity-30` → `40` parity with `<UiButton>` | Low | Migrate both occurrences to `disabled:opacity-40`; visual diff is minor but matches iOS convention. |
| 4 | `S/ ${...toFixed(2)}` bypasses `formatCurrency` | Low | Mandatory swap per PAGOS-DLR-MNY-002 precedent; assert presence via test. Import `formatCurrency` from `useFormatters`. |
| 5 | `<script>` block accidentally edited during template cleanup | Low | Apply phase scope rule: only `<template>` + `class=""` attributes change. `useProcedureFavorites` contract preserved verbatim. |
| 6 | `<UiEmptyState>` primitive not yet consumed by the favourites card or the search list | Low | Both empty states (no favourites, no search results) are simple text blocks — `<UiEmptyState>` accepts slot-based content. Wire once, both usages identical. |

**Overall risk: Low.** Single file, zero `<script>` edits, zero sibling components, zero realtime contract.

---

## 6. Open questions resolved

Per cached defaults from the explore phase:

| OQ | Question | Decision |
|---|---|---|
| OQ-1 | `<UiEmptyState>` migration of hand-built empty states? | **YES — in scope.** Replace lines 38-43 (no favourites) and 192-196 (no search results) with `<UiEmptyState>`. |
| OQ-2 | `<UiButton size="icon">` for 3 raw icon-buttons vs keep raw + `:focus-visible`? | **Keep raw + add `:focus-visible` → `var(--focus-ring-default)`** to each of the 3 buttons (subir / bajar / quitar). |
| OQ-3 | `useProcedureFavorites` pagination? | **OUT of scope** (separate slice). `per_page: 100` stays verbatim. |
| OQ-4 | `<UiStatusBadge>` for rank pill (`#N`)? | **Keep inline ramp** (`bg-systemBlue-50 text-systemBlue-700`). Counter, not state — `<UiStatusBadge>` is reserved for state-bearing pills (success / warning / error / info / neutral). |
| OQ-5 | `disabled:opacity-30` → `40`? | **Migrate.** Both occurrences (lines 72, 89). |
| OQ-6 | `font-mono` → system sans + `tabular-nums`? | **Migrate.** Both occurrences (lines 53, 170). Drop `font-mono`; keep `text-xs text-theme-secondary tabular-nums`. |
| OQ-7 | Single PR vs PR3 cluster? | **Standalone PR (single page, low blast radius).** Rides as `PR-mis-procedimientos-01` inside the global PR3 cluster window (per global proposal §7.5: Recepción + Mis procedimientos + Catálogo). |

---

## 7. PR shape

| Field | Value |
|---|---|
| Name | `pr-mis-procedimientos-tokenise` |
| PR id | `PR-mis-procedimientos-01` |
| Target files | 1 page (`MyProceduresPage.vue`) + 1 new test (`MyProceduresPageAppShellTest.php`) |
| Line estimate | ~280-330 total (template ~220 lines changed + test ~80 lines) |
| Risk | Low |
| Dependencies | PR0 (`canvasRoutes` extended; `useFormatters.formatCurrency` available; `ModuleAppShellTestCase` imported; `<UiEmptyState>` / `<UiInput>` / `<UiLoadingSpinner>` primitives exist) |
| Reversibility | `git revert <merge-sha>`. Composable, API, routing, `<script>` block untouched. Canvas surface inherited from PR0 — revert of this PR does NOT remove the canvas (only the within-page class-string swaps). |
| Stacking | Inside global PR3 cluster window (per global proposal §7.5). Lands after Recepción procedimientos if stacked; standalone if cluster is unpacked. |

---

## 8. References

- `openspec/changes/ui-rollout-all-modules-2026-08/categories/mis-procedimientos/explore.md` — primary input (209 lines)
- `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` — global inventory, tiers, PR chain
- `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` — global intent, OQs, PR chain
- `openspec/changes/archive/2026-08-12-ui-pacientes/proposal.md` — closest precedent (1-PR category scope)
- `openspec/changes/archive/2026-08-12-ui-pagos/proposal.md` — alternate precedent (same scale)
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` — D1-D16 + G1-G13 token source-of-truth
- `openspec/specs/premium-design-foundation/spec.md` — the proven capability
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` — DLR-R-001/002/004/021 base rules (extends from here)
- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` — `/my-procedures` already at `canvasRoutes` line 547
- `resources/js/modules/my-procedures/MyProceduresPage.vue` (276 lines) — the target file
- `resources/js/composables/useProcedureFavorites.js` (147 lines, read-only)
- `resources/js/composables/useFormatters.js` (`formatCurrency` canonical PEN formatter)
- `resources/js/components/ui/{UiInput,UiButton,UiCard,UiEmptyState,UiLoadingSpinner}.vue` — primitives consumed
- `resources/js/components/layout/AppLayout.vue` line 547 — `canvasRoutes` already wired
- `AGENTS.md` §5 (module inventory) + §7 (`Ui*`-prefix convention)
- `CREDENTIALS.md` — `odonto@test.com` for visual verification

---

## Key Learnings

1. Tier-1 single-page module (276 lines, 1 file, 0 sibling components) consumes zero proven-token references despite PR0 already wiring `canvasRoutes`.
2. Twelve distinct legacy class patterns, zero `<style scoped>` blocks, zero inline `@keyframes`, zero `<script>` block edits — pure template-level mechanical replacement.
3. Two inline `S/ ${...toFixed(2)}` literals bypass canonical `formatCurrency`; PAGOS-DLR-MNY-002 makes the swap mandatory in this PR.
4. `<LoadingSpinner>` import at line 211 violates AGENTS.md §7 (`Ui*`-prefix convention); rename to `UiLoadingSpinner` is non-cosmetic with zero rendering impact.
5. Three raw icon `<button>`s (subir / bajar / quitar) keep raw markup but gain `:focus-visible` → `var(--focus-ring-default)` per cached OQ-2 resolution.