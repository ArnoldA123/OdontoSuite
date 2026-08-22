# Spec: mis-procedimientos Category Delta (ui-rollout-all-modules-2026-08)

> Delta type: Category delta spec. Sibling of the parent `design-language-rollout`
> and `foundation-primitives` specs. Extends the global rollout with
> mis-procedimientos-specific MUST rows scoped to a single file. Naming: `MIS-*`.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `mis-procedimientos` |
| Date | 2026-08-21 |
| Phase | spec (3 of 6) |
| Author | `sdd-spec` sub-agent |
| Parent spec | `openspec/specs/design-language-rollout/spec.md` |
| Parent proposal | `openspec/changes/ui-rollout-all-modules-2026-08/categories/mis-procedimientos/proposal.md` |
| Slice | `PR-mis-procedimientos-01` (single PR) |
| Artifact store | `hybrid` (this file + Engram) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` |

This spec does NOT modify the global DLR rows. The `DLR-CORE-*` and `DLR-MOD-*`
rules apply unmodified; rows below add category-specific edges.

---

## 1. Scope

Single Vue file: `resources/js/modules/my-procedures/MyProceduresPage.vue`
(276 lines). 22 legacy-token references across 12 patterns, 2 inline
`S/ ${...toFixed(2)}` literals, 1 misnamed `<LoadingSpinner>` import, zero
`<style scoped>` blocks, zero `<script>` block edits. No backend changes.

---

## 2. ADDED Requirements

### Requirement: `MIS-001` — Hairline tokens replace every `border-theme` and `divide-theme` literal

The system MUST replace every `border-theme` literal (4 occurrences at lines 39, 49, 138, 161) with `border-[color:var(--color-hairline)]` (or `border-hairline`) and the `divide-theme` literal at line 161 with `divide-[color:var(--color-hairline)]`.

#### Scenario: `MIS-001-1` — No legacy `border-theme` / `divide-theme` literals remain

- GIVEN the file currently references `border-theme` 4 times and `divide-theme` 1 time
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_no_border_theme_literal` asserts both literals are absent (regex count == 0)
- AND `MyProceduresPageAppShellTest::test_no_divide_theme_literal` asserts the divide literal is absent
- AND `ModuleAppShellTestCase::test_no_border_theme_literal` (DLR-R-002) stays green

### Requirement: `MIS-002` — `<UiInput>` adoption for the search field

The system MUST replace the raw `<input>` at line 134 (with `class="...focus:ring-primary-500 focus:border-accent..."`) with `<UiInput v-model="search" ...>` while preserving the `<div class="relative">` wrapper and the search-icon `<svg>` inset at `left-3`.

#### Scenario: `MIS-002-1` — Search field uses `<UiInput>` and no raw input

- GIVEN the search field is the only raw `<input>` in the file
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_search_uses_ui_input` asserts `<UiInput v-model="search"` is present
- AND asserts raw `<input` (with `class="...focus:ring-primary-500..."`) is absent
- AND asserts the `<div class="relative">` wrapper is preserved

### Requirement: `MIS-003` — `formatCurrency` is the only money formatter on this page

The system MUST replace the two inline `S/ {{ Number(...).toFixed(2) }}` literals at lines 65 and 178 with `formatCurrency(...)` from `useFormatters`, per the PAGOS-DLR-MNY-002 precedent.

#### Scenario: `MIS-003-1` — Both PEN literals consume `formatCurrency`

- GIVEN two inline PEN literals currently bypass the canonical formatter
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_format_currency_used_for_pen_values` asserts `formatCurrency(` is referenced at least twice
- AND asserts `Intl.NumberFormat` is absent from the template (single-source rule)
- AND `FormatPENLabelTest` stays green at exactly one declaration location

### Requirement: `MIS-004` — Tabular numerals on every numeric cell

The system MUST carry `font-feature-settings: var(--font-features-tabular-nums)` (Tailwind `tabular-nums`) on the 6 numeric spans across the two cards: `default_duration_minutes`, `default_cost`, `position`.

#### Scenario: `MIS-004-1` — At least 6 `tabular-nums` references on numeric cells

- GIVEN 6 numerics × 2 cards currently lack tabular-nums
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_tabular_nums_on_numeric_cells` asserts `tabular-nums` OR `font-feature-settings: var(--font-features-tabular-nums)` appears at least 6 times
- AND the DNI-style `fav.code` + `proc.code` code badges (separately covered by `MIS-007`) also carry the token

### Requirement: `MIS-005` — `<LoadingSpinner>` rename to `<UiLoadingSpinner>`

The system MUST rename the import at line 211 and both tag references (lines 34, 156) from `LoadingSpinner` to `UiLoadingSpinner`, per AGENTS.md §7 `Ui*`-prefix convention.

#### Scenario: `MIS-005-1` — Only `UiLoadingSpinner` is consumed

- GIVEN the legacy `<LoadingSpinner>` name violates the `Ui*`-prefix convention
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_loading_spinner_import_is_ui_prefixed` asserts `import UiLoadingSpinner` is present
- AND asserts `import LoadingSpinner` and the bare tag `<LoadingSpinner` are both absent

### Requirement: `MIS-006` — `disabled:opacity-30` parity with `<UiButton>` (`40`)

The system MUST replace the two `disabled:opacity-30` references at lines 72 and 89 with `disabled:opacity-40` to match the iOS convention used by `<UiButton>`.

#### Scenario: `MIS-006-1` — Disabled state matches iOS parity

- GIVEN `<UiButton>` already ships `disabled:opacity-40`
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_disabled_opacity_uses_ios_parity` asserts `disabled:opacity-40` appears at least 2 times
- AND asserts `disabled:opacity-30` appears 0 times

### Requirement: `MIS-007` — `font-mono` drop + system sans + `tabular-nums`

The system MUST drop `font-mono` at lines 53 and 170 and replace each with `text-xs text-theme-secondary tabular-nums` (system sans + tabular numerals, not the legacy monospace stack).

#### Scenario: `MIS-007-1` — Code badges consume system sans, not `font-mono`

- GIVEN both code badges currently render in `font-mono`
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_code_badges_use_system_sans_with_tabular_nums` asserts `font-mono` appears 0 times
- AND asserts `tabular-nums` is present on both code-badge spans (combined with `MIS-004`)

### Requirement: `MIS-008` — `<UiEmptyState>` adoption for both hand-built empty states

The system MUST replace the two hand-built empty states (no favourites at lines 38-43, no search results at lines 192-196) with `<UiEmptyState>` slots, per cached OQ-1 resolution.

#### Scenario: `MIS-008-1` — Both empty states consume `<UiEmptyState>`

- GIVEN both empty states are simple text blocks
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_empty_states_consume_ui_empty_state` asserts `<UiEmptyState` appears at least 2 times
- AND asserts the legacy `border-2 border-dashed border-theme` literal on the no-favourites card is absent

### Requirement: `MIS-009` — Every raw icon `<button>` consumes the focus-ring token

The system MUST add `:focus-visible` plus `box-shadow: var(--focus-ring-default)` (or equivalent class) to each of the 3 raw icon `<button>`s at lines 69-118 (subir / bajar / quitar) so each is keyboard-reachable. Per cached OQ-2 resolution, raw markup stays; `<UiButton size="icon">` migration is deferred.

#### Scenario: `MIS-009-1` — All 3 raw icon buttons consume `var(--focus-ring-default)`

- GIVEN none of the 3 raw `<button>`s currently expose a focus ring
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_raw_icon_buttons_consume_focus_ring_token` asserts `var(--focus-ring-default)` appears at least 3 times
- AND asserts `focus:ring-primary-500` is absent (DLR-R-004 re-asserted)

### Requirement: `MIS-010` — Status ramp tokenization (blue / yellow / red)

The system MUST replace the raw Tailwind ramps with the proven system ramps: `bg-primary-50 text-primary-700` (line 56, rank pill) → `bg-systemBlue-50 text-systemBlue-700`; `text-yellow-500` (lines 28, 171) → `text-systemYellow-500`; `text-red-500 hover:text-red-700` (line 105) → `text-systemRed-500 hover:text-systemRed-700`. `<UiStatusBadge>` migration is deferred per OQ-4 (rank pill is a counter, not state).

#### Scenario: `MIS-010-1` — Every status ramp uses the proven system ramp

- GIVEN the file consumes 4 raw Tailwind status ramps
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_order_pill_uses_tokenized_blue_ramp` asserts `bg-systemBlue-50 text-systemBlue-700` is present
- AND `MyProceduresPageAppShellTest::test_yellow_ramp_is_systemyellow` asserts `text-systemYellow-500` is present and raw `text-yellow-500` is absent
- AND `MyProceduresPageAppShellTest::test_remove_favorite_uses_systemred_ramp` asserts `text-systemRed-500` is present and raw `text-red-500` is absent

### Requirement: `MIS-011` — `bg-theme-surface` alias migration to `bg-canvas`

The system MUST replace `hover:bg-theme-surface` at line 166 with `hover:bg-canvas`. `bg-theme-surface-elevated` (lines 49, 138) is RETAINED as a semantic alias resolving to `#ffffff`.

#### Scenario: `MIS-011-1` — Hover surface uses the canvas token

- GIVEN the file currently distinguishes `bg-theme-surface` from `bg-theme-surface-elevated`
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_hover_surface_uses_canvas_token` asserts `hover:bg-canvas` is present
- AND asserts the raw `hover:bg-theme-surface` literal is absent
- AND `bg-theme-surface-elevated` remains present (semantic alias)

### Requirement: `MIS-012` — `rounded-lg` replaced with contextual radius tokens

The system MUST replace the 4 `rounded-lg` references at lines 39, 49, 138, 161 with contextual tokens: input → `rounded-[var(--radius-control)]`; cards → `rounded-[var(--radius-ios)]` or `rounded-[var(--radius-card-lg)]`; dividers follow the card radius.

#### Scenario: `MIS-012-1` — Every radius literal consumes a token

- GIVEN 4 `rounded-lg` literals with no token binding
- WHEN PR-mis-procedimientos-01 lands
- THEN `MyProceduresPageAppShellTest::test_radius_uses_contextual_tokens` asserts `var(--radius-control)` or `var(--radius-ios)` or `var(--radius-card-lg)` appears at least 4 times
- AND asserts bare `rounded-lg` (without a token) appears 0 times

### Requirement: `MIS-013` — `<script setup>` block MUST stay byte-for-byte

The system MUST preserve the `<script setup>` block verbatim. The `useProcedureFavorites` contract (`getFavorites`, `getForMe`, `addFavorite`, `removeFavorite`, `reorderFavorites`), the `useToast` calls, and the `useRouter().push('/dashboard')` redirect MUST stay unchanged.

#### Scenario: `MIS-013-1` — Composable contract stays green

- GIVEN `ComposablesStandardizationTest` pins the `useProcedureFavorites` surface
- WHEN PR-mis-procedimientos-01 lands
- THEN `ComposablesStandardizationTest` stays green
- AND `git diff --stat` reports zero edits to the `<script setup>` block
- AND `MyProceduresPageAppShellTest::test_script_setup_unchanged` asserts the file's `<script>` block content hash matches the pre-PR baseline

### Requirement: `MIS-014` — `PR-mis-procedimientos-01` MUST stay under the 400-line review budget

The system MUST keep the PR diff under 400 authored lines. If the diff exceeds 400, the apply phase MUST split per the `chained-pr` skill BEFORE review starts.

#### Scenario: `MIS-014-1` — Single-PR diff fits the budget

- GIVEN the proposal estimates ~280-330 total lines (template ~220 + test ~80)
- WHEN PR-mis-procedimientos-01 is reviewed
- THEN `git diff --stat` reports `additions + deletions <= 400`
- AND if the diff exceeds 400 lines, the PR is split BEFORE review starts

---

## 3. Inherited MUST (re-asserted from parent `design-language-rollout/spec.md`)

The following parent rows remain green at this PR boundary; the new test
file `MyProceduresPageAppShellTest.php` extends `ModuleAppShellTestCase` and
re-asserts them via the base class's data provider:

- **DLR-R-001** — Canvas surface (file references `bg-canvas` / `var(--color-canvas)` / `rgb(242, 242, 247)`).
- **DLR-R-002** — Hairline (file MUST NOT contain `border-theme` literal) — re-asserted by `MIS-001-1`.
- **DLR-R-004** — Focus ring (file MUST consume `var(--focus-ring-default)` if `:focus`/`:focus-visible` present; MUST NOT contain `focus:ring-primary-500` / `focus:border-accent`) — re-asserted by `MIS-009-1`.
- **DLR-R-021** — No `<style scoped>` block (already satisfied; rule pins zero count, not literal) — re-asserted by `ModuleAppShellTestCase::test_no_style_scoped`.

---

## 4. Out of scope (deferred)

Mirrors `proposal.md` §3. Items are excluded from this category and
explicitly recorded so the apply phase does NOT silently resolve them.

| Item | Reason |
|---|---|
| `<UiButton size="icon">` migration for 3 raw icon-buttons | Deferred per OQ-2. Keep raw markup + `:focus-visible` ring (`MIS-009`). |
| `useProcedureFavorites` pagination (`per_page: 100`) | OUT per OQ-3 (separate slice). |
| `<UiStatusBadge>` adoption for `#N` rank pill | Deferred per OQ-4 (counter, not state). Keep inline ramp (`MIS-010`). |
| New `<UiStatusBadge>` primitive | OUT — PR0 owns it (global OQ-5). |
| Pagination primitive de-duplication | OUT — global PR3 cluster (global OQ-7). |
| Settings/branches + Settings/payment-methods | OUT per global OQ-3. |
| Dark mode / two-tone numerals / per-KPI sparklines | OUT per global proposal §3. |
| New tokens / new primitives beyond PR0's catalog | OUT — `tokens.js` frozen for the rollout. |

---

## 5. Verification strategy

- **Visual**: `pnpm build` clean; `playwright-cli screenshot --filename=.playwright-cli/screenshots-rollout/my-procedures-1440x900.png` (gitignored). Login as `odonto@test.com`.
- **Static (PHPUnit)**: New `tests/Unit/DesignSystem/MyProceduresPageAppShellTest.php` extends `ModuleAppShellTestCase` with `polishedFiles()` returning the single `MyProceduresPage.vue` path. Per-case tests cover `MIS-001` through `MIS-012`; `MIS-013` is covered by `ComposablesStandardizationTest` + a script-hash diff test.
- **Runtime**: `ComposablesStandardizationTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, and the base `ModuleAppShellTestCase` assertions stay green at this PR boundary.
- **CI gates**: `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm) green.

---

## 6. Acceptance criteria

The `mis-procedimientos` category is considered complete when ALL of the
following hold: every `border-theme` / `divide-theme` literal is replaced
with the hairline token (`MIS-001`); the search field uses `<UiInput>`
(`MIS-002`); both PEN literals consume `formatCurrency` (`MIS-003`);
≥6 numeric spans carry `tabular-nums` (`MIS-004`); `<LoadingSpinner>`
rename to `<UiLoadingSpinner>` is complete (`MIS-005`); `disabled:opacity-30`
→ `40` on both move buttons (`MIS-006`); `font-mono` dropped in favour
of system sans + `tabular-nums` (`MIS-007`); both empty states consume
`<UiEmptyState>` (`MIS-008`); all 3 raw icon `<button>`s consume
`var(--focus-ring-default)` (`MIS-009`); all 4 status ramps are tokenized
(`MIS-010`); `hover:bg-theme-surface` → `hover:bg-canvas` (`MIS-011`);
4 `rounded-lg` literals are tokenized (`MIS-012`); `<script setup>` block
is byte-for-byte unchanged (`MIS-013`); PR diff ≤ 400 authored lines
(`MIS-014`); DLR-R-001/002/004/021 inherited rules stay green; visual
sweep captured; CI gates green.

---

## 7. References

- `categories/mis-procedimientos/proposal.md` — primary input (intent, scope, risk register, rollback).
- `categories/mis-procedimientos/explore.md` — file inventory, pattern table, risk matrix.
- `openspec/specs/design-language-rollout/spec.md` — parent spec (DLR-R-001/002/004/021 base rules).
- `openspec/specs/foundation-primitives/spec.md` — PR0 primitives (`<UiInput>`, `<UiEmptyState>`, `<UiLoadingSpinner>`).
- `openspec/changes/archive/2026-08-12-ui-pacientes/specs/pacientes/spec.md` — closest precedent (single-page category).
- `openspec/changes/archive/2026-08-12-ui-pagos/specs/pagos/spec.md` — alternate precedent (`PAGOS-MNY-002` currency rule).
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` — base test class (DLR-R-001/002/004/021).
- `tests/Unit/DesignSystem/CajaPagesAppShellTest.php` — `PAGOS-MNY-002` currency assertion pattern.
- `tests/Unit/DesignSystem/PatientsListAppShellTest.php` — single-page list test pattern.
- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` — `/my-procedures` already at `canvasRoutes` line 547.
- `resources/js/components/ui/{UiInput,UiButton,UiCard,UiEmptyState,UiLoadingSpinner,UiStatusBadge}.vue` — primitives.
- `resources/js/composables/useProcedureFavorites.js` (147 lines, read-only).
- `resources/js/composables/useFormatters.js` (`formatCurrency` canonical PEN formatter).
- AGENTS.md §5 (module inventory) + §7 (`Ui*`-prefix convention).
- CREDENTIALS.md — `odonto@test.com` for visual verification.

---

*End of mis-procedimientos category spec. Next phase: `sdd-design`.*
