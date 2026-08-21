# Proposal: recepcion-procedimientos (ui-rollout-all-modules-2026-08)

> SDD phase: sdd-propose. Smallest Tier-1 module (180 lines, 5.2 KB). Single PR.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `recepcion-procedimientos` |
| Date | 2026-08-21 |
| Phase | propose (2 of 6) — category slice |
| Author | `sdd-propose` sub-agent (recepcion-procedimientos) |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/recepcion-procedimientos/proposal`) |
| Slice | `PR-recepcion-procedimientos-01` (single PR, ~80-120 authored + ~30 test lines) |
| Risk | Low (smallest module, no `<script>`, no `<style scoped>`, no realtime, no 3rd-party widget) |
| Roles | recepcionista |
| Delivery strategy | Inherits `auto-chain` from global proposal; this slice is a sub-PR of the chain but small enough to ride inside another slice or stack standalone |
| Review budget | 400 authored lines / PR (well under budget; ~25% utilisation) |
| Strict TDD | `true` (forward to apply/verify) |
| Vertical slice baseline | `ui-premium-microdetail-2026-08` — closed 2026-08-11 |
| Source artifacts | `categories/recepcion-procedimientos/explore.md` (122 lines), `explore.md` §1, §3, §4, §5.1, `proposal.md` §2, `archive/2026-08-12-ui-pagos/proposal.md` (precedent for single-PR category with `AppLayout.canvasRoutes` addition) |

### Preflight snapshot (verbatim)

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

Recepcion-procedimientos is a recepcionista-only read-only catalog (`/reception-procedures`) — filter procedures by search + specialty, browse a 1/2/3-column responsive grid, paginate. The proven Apple language landed on Dashboard, Login, and 404; this surface still reads as legacy: opaque `border-theme` outlines on the search input and the raw `<select>` specialty filter, deprecated `bg-primary-50 text-primary-700` chip on procedure codes, `text-accent` on prices (no `tabular-nums`), and a hand-rolled empty-results `<div>` instead of `<UiEmptyState>`. The page surface is also missing from `canvasRoutes`, so cards read as outlines on white.

This proposal scopes the rollout to **only** `ReceptionProceduresPage.vue`. It inherits every rule from the global proposal (token discipline, primitive contract, focus-ring composition, `tabular-nums`, canvas/surface separation) and applies them mechanically. The result: a recepcionista landing on `/reception-procedures` reads the same product as a clinician landing on `/dashboard`. The `useProcedureCatalog` + `useSpecialties` composables stay byte-for-byte untouched — UI changes are template-level class-string replacement only. The 1-line additive `canvasRoutes` extension lives in this category PR (per orchestrator note: PR0 already merged, but the `/reception-procedimientos` route was not in the original `canvasRoutes` array; this PR wires it).

**Why now:** the smallest module in the project, with zero `<style scoped>`, zero inline `@apply`, zero `@keyframes`, zero realtime, zero third-party widget. The 5.2 KB footprint fits inside one PR at ~25% of the 400-line budget. Cheapest possible category rollout; ideal proving ground for the small-Tier-1 pattern that ProcedureStatsPage and MyProceduresPage will follow.

---

## 2. Scope

### 2.1 Files to modify

- `resources/js/components/layout/AppLayout.vue` — add `'/reception-procedimientos'` to the `canvasRoutes` array (1-line additive; ~5 characters).
- `resources/js/modules/reception-procedures/ReceptionProceduresPage.vue` — full template tokenisation:
  - Replace raw `<input>` (lines 31-36) with `<UiInput v-model="filters.search" type="search" placeholder="...">` + prefix slot for the SVG search icon.
  - Replace raw `<select>` (lines 54-62) with `<UiSelect v-model="filters.specialty">` + `<option>` list.
  - Replace inline procedure code chip (line 83: `bg-primary-50 text-primary-700`) with `<UiBadge variant="primary" size="sm" class="font-mono">`.
  - Replace price text (line 103: `text-accent`) with `text-systemBlue-600 tabular-nums`.
  - Replace `border-theme` on inputs and card divider with `border-[color:var(--color-hairline)]`.
  - Replace `bg-theme-surface-elevated` on inputs with `bg-systemBackground` (the proven surface token).
  - Replace `text-theme-primary` / `text-theme-secondary` with `text-label.label` / `text-label.secondaryLabel` (iOS label ramps).
  - Replace `rounded-lg` on inputs with `rounded-[var(--radius-control)]` (8 px).
  - Replace inline empty-results `<div>` (lines 71-73) with `<UiEmptyState title="Sin resultados" description="Ajusta los filtros para ver más procedimientos.">`.
  - Drop `hover-lift transition-shadow` from `<UiCard variant="elevated">` (primitive already handles hover).
  - Replace `focus:ring-primary-500 focus:border-accent` on inputs with the proven focus ring (delegated to `<UiInput>` / `<UiSelect>` primitive; no raw `:focus` selectors added).
- `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` — NEW. Extends `ModuleAppShellTestCase`. Asserts the per-module rule for `ReceptionProceduresPage.vue`:
  - Page surface references `bg-canvas` / `var(--color-canvas)` / `rgb(242, 242, 247)` (DLR-R-001).
  - No `border-theme` literal (DLR-R-002).
  - No `focus:ring-primary-500` / `focus:border-accent` aliases (DLR-R-004 neg).
  - No `<style scoped>` block (DLR-R-021).
  - Currency display uses `tabular-nums`.
  - Procedure code chip uses `<UiBadge>` (not inline `bg-primary-50 text-primary-700`).
  - Empty state uses `<UiEmptyState>` (not inline `<div class="py-12 text-center text-theme-secondary">`).

### 2.2 Files NOT to modify

- `resources/js/composables/useProcedureCatalog.js` — frozen (composable contract preserved).
- `resources/js/composables/useSpecialties.js` — frozen (composable contract preserved).
- `resources/js/app.js` — route `/reception-procedimientos` already registered; no router change.
- `resources/js/components/ui/{Card,Button,Input,Badge,EmptyState,Pagination,LoadingSpinner,Select}.vue` — all primitives already tokenised in PR2 of the vertical slice; this category only consumes them.

### 2.3 Backend changes

**NONE.** No controller, service, model, migration, route, listener, or job touched. The `/api/reception-procedures` endpoint contract is preserved verbatim.

---

## 3. Out of scope (deferred)

The category is the cheapest in the project; nothing is deferred from it to a later slice. The following global-level deferred items (inherited from the global proposal §3) still apply:

- Dark mode (out of the entire rollout).
- Accessibility audit beyond incidental color contrast (out of the entire rollout).
- Per-KPI sparklines, two-tone numerals, BI visuals, Calendario FullCalendar internals, CashRegister `<script>` blocks (all out of the entire rollout).
- `<UiStatusBadge>` primitive is NOT needed here — procedure code chips are informational, not status; `<UiBadge variant="primary">` is correct (per OQ-1 resolution below).

---

## 4. Acceptance criteria

- [ ] `/reception-procedimientos` renders on `var(--color-canvas)` page surface; cards lift off the canvas (verified by per-module Playwright snapshot at 1440x900).
- [ ] All 14 legacy class occurrences (`border-theme`, `bg-theme-surface-elevated`, `focus:ring-primary-500`, `focus:border-accent`, `rounded-lg`, `bg-primary-50 text-primary-700`, `text-accent`, `text-theme-primary` ×2, `text-theme-secondary` ×3) removed from the template.
- [ ] `<UiInput>`, `<UiSelect>`, `<UiBadge>`, `<UiEmptyState>` primitives adopted for filter input, specialty filter, procedure code chip, and empty-results state respectively.
- [ ] Price display uses `text-systemBlue-600 tabular-nums` (OQ-1 resolution).
- [ ] Procedure code chip uses `<UiBadge variant="primary" size="sm" class="font-mono">` (OQ-2 resolution; `font-mono` passthrough confirmed).
- [ ] No `hover-lift transition-shadow` on the procedure card (OQ-3 resolution; primitive hover takes over).
- [ ] Empty-results Spanish copy: "Sin resultados" title + descriptive subtitle (OQ-4 resolution).
- [ ] Specialty filter uses `<UiSelect>` primitive (OQ-5 resolution); no raw `<select>`.
- [ ] No `<style scoped>` block in the page (none exists today; DLR-R-021 guard extended to this file).
- [ ] `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` green; asserts the rule (token reference, alias absence, primitive adoption, `tabular-nums`), not literal strings.
- [ ] `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` extended to assert `'/reception-procedimientos'` is present in the `canvasRoutes` array literal.
- [ ] All existing PHPUnit invariants stay green: `TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `ModuleAppShellTestCase`-derived tests.
- [ ] CI green: `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).
- [ ] Playwright snapshot at 1440x900 saved to `.playwright-cli/screenshots-rollout/reception-procedures-1440x900.png` (desktop mandatory; mobile capture NOT required — this is not a mobile-first surface, per global OQ-8).
- [ ] Test count delta: +2 (new `ReceptionProceduresAppShellTest` + extended `AppLayoutCanvasRoutesTest`).

---

## 5. Risks + mitigations

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | `<UiInput type="search">` prefix slot for the SVG search icon may not match the visual weight of the legacy raw input. | Low | Apply phase: keep the SVG `w-4 h-4 absolute left-3` pattern inside the prefix slot; verify by Playwright snapshot at 1440x900 before merge. |
| 2 | `<UiSelect>` primitive may not accept the empty-value "Todas las especialidades" placeholder option pattern used in the legacy `<select>`. | Low | Apply phase: confirm `<UiSelect>` supports `value=""` + `<option value="">` placeholder (likely yes; if not, pass `:placeholder="'Todas las especialidades'"` prop). Update `ReceptionProceduresAppShellTest` to assert no raw `<select>` tag in the rendered file. |
| 3 | `font-mono` passthrough inside `<UiBadge>` may collide with the primitive's own typography class. | Low | Apply phase: render in browser; confirm monospace digits render correctly. `<UiBadge>` is known to forward class via Vue's `class` attribute merging (per PR2 press test). |
| 4 | The 1-line `canvasRoutes` addition is the only cross-cutting edit; if missed, the page surface stays on `bg-systemBackground` and cards read as outlines on white. | Low | `AppLayoutCanvasRoutesTest` extended to assert `'/reception-procedimientos'` is present in the array literal; test must pass before merge. |
| 5 | Rollback safety: a partial revert of just `ReceptionProceduresPage.vue` (without the `canvasRoutes` line) would leave the page with tokenised chrome on a non-canvas surface (subtle visual regression). | Low | Reversibility strategy: single PR; one `git revert <merge-sha>` restores both files atomically. |

---

## 6. Open questions resolved

The 5 open questions from `categories/recepcion-procedimientos/explore.md` §6 are resolved here with concrete decisions. Apply phase does NOT silently re-open them.

### OQ-1 — Price display: `<UiBadge variant="success">` vs plain `text-systemBlue-600 tabular-nums`

**Decision: plain `text-systemBlue-600 tabular-nums` (NOT `<UiBadge>`).**

Rationale: prices are NOT status indicators; `<UiBadge>` would semantically overload the primitive. `text-systemBlue-600` matches the proven iOS link/info colour; `tabular-nums` ensures column-aligned digits across the 1/2/3-column responsive grid. The legacy `text-accent` was also a blue, so the visual delta is minimal (token-only swap), keeping the rollback path obvious.

### OQ-2 — `font-mono` inside `<UiBadge>` via passthrough

**Decision: YES — passthrough is allowed.**

Rationale: `<UiBadge>` forwards `class` via Vue's attribute fallthrough (verified in PR2 press test). The procedure code chip needs monospace for `PROC-001`-style codes to align across cards. Apply phase: `<UiBadge variant="primary" size="sm" class="font-mono">` — single-line expression.

### OQ-3 — `hover-lift transition-shadow` utility vs primitive hover

**Decision: DROP the class; let `<UiCard variant="elevated">` primitive handle hover.**

Rationale: the proven `<UiCard>` primitive (tokenised in PR2) already ships `translateY(-2px)` hover with reduced-motion fallback. Stacking a `hover-lift transition-shadow` class on top would double-apply the transform and risk `transform` compositing glitches. Drop the class; the primitive owns the hover.

### OQ-4 — Empty-results Spanish copy

**Decision: title="Sin resultados" + description="Ajusta los filtros para ver más procedimientos."**

Rationale: matches the Dashboard "today-appointments" empty-state pattern (proven in PR4). Title is short and direct; description is action-oriented ("ajusta los filtros") so the recepcionista knows what to do next. No emoji, no decoration.

### OQ-5 — `<UiSelect>` primitive vs raw `<select>`

**Decision: YES — adopt `<UiSelect>` primitive.**

Rationale: the specialty filter is a static list of options loaded once via `useSpecialties`. `<UiSelect>` (tokenised in PR2) handles this exact pattern; raw `<select>` would carry the legacy `border-theme` + `focus:ring-primary-500` defects that we're trying to eliminate. Apply phase: confirm `<UiSelect>` supports `v-model` + `<option>` children; the binding contract is identical to raw `<select>`.

---

## 7. PR shape

`PR-recepcion-procedimientos-01`:

| Field | Value |
|---|---|
| Name | `pr-recepcion-procedimientos-01-tokenise-and-wire-canvas` |
| Scope | (1) `AppLayout.vue` — add `'/reception-procedimientos'` to `canvasRoutes`. (2) `ReceptionProceduresPage.vue` — full template tokenisation per §2.1. (3) NEW `tests/Unit/DesignSystem/ReceptionProceduresAppShellTest.php` extending `ModuleAppShellTestCase`. (4) Extend `AppLayoutCanvasRoutesTest` to assert the new route is present. |
| Files | 1 layout file (1-line edit) + 1 page file (~60-80 line edits) + 1 new test file (~30 lines) + 1 extended test file (~1 line) |
| Dependencies | Global PR0 (already merged: `canvasRoutes` partial extension, `<UiStatusBadge>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`) |
| Risk | Low |
| Line estimate | ~80-120 authored + ~30 test lines (~25% of the 400-line budget) |
| Reversibility | Single `git revert <merge-sha>` restores the legacy `border-theme` + `text-accent` + raw `<select>` + missing canvas surface atomically. The `canvasRoutes` extension is additive; removing it is safe even if other categories have landed. |
| Stacking | This PR can stack inside any global PR slice (recommended: ride global PR3 `pr3-smallest-modules-and-procedure-catalog` alongside `MyProceduresPage.vue` and `ProcedureCatalogPage.vue`). If the user prefers a standalone PR, it's small enough to merge independently. |
| Visual verification | Playwright snapshot at 1440x900; saved to `.playwright-cli/screenshots-rollout/reception-procedures-1440x900.png`. Mobile capture NOT required (not a mobile-first surface per global OQ-8). Credentials: `recep@test.com` per `CREDENTIALS.md`. |

---

## 8. References

| File | Why it matters |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/recepcion-procedimientos/explore.md` (122 lines) | Primary input. Module inventory, current visual state, gap analysis, OQs |
| `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` §1, §3, §4, §5.1 | Global baseline + per-module visual state + token/primitive coverage |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §2, §4, §6 | Global intent, mapping table, OQ resolutions (dark-mode, StatusBadge extraction, hover-lift consolidation) |
| `openspec/changes/archive/2026-08-12-ui-pagos/proposal.md` | Precedent for a single-PR category slice with `AppLayout.canvasRoutes` addition |
| `resources/js/modules/reception-procedures/ReceptionProceduresPage.vue` (180 lines, 5.2 KB) | The single source file for this rollout |
| `resources/js/components/layout/AppLayout.vue` (line 507 `canvasRoutes`) | The 1-line additive change target |
| `resources/js/composables/{useProcedureCatalog,useSpecialties}.js` | Frozen composables; NOT touched |
| `resources/js/components/ui/{Card,Button,Input,Badge,EmptyState,Pagination,LoadingSpinner,Select}.vue` | Primitives already tokenised in PR2; this category only consumes them |
| `resources/js/design-system/tokens.js` | Proven token source-of-truth |
| `resources/css/tokens.generated.css` (369 lines) | Generated CSS consumed by the primitives |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Base class extended by the new test (5 rules: DLR-R-001, DLR-R-002, DLR-R-004 pos, DLR-R-004 neg, DLR-R-021) |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Extended by this PR to pin the `canvasRoutes` array literal |
| `tests/Unit/DesignSystem/DashboardAppShellTest.php` | Sibling pattern for the new per-module test |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` lines 47-57 | Process lesson: "assert the rule, not the literal string" — applied to all 5 assertions in `ReceptionProceduresAppShellTest` |
| `openspec/specs/premium-design-foundation/spec.md` | The archived capability this rollout inherits |
| `AGENTS.md` §3, §5, §7 | Commands, module inventory, conventions |
| `openspec/config.yaml` | Preflight cache + strict TDD + pnpm-only + 400-line budget |
| `CREDENTIALS.md` | `recep@test.com` for visual verification |

---

*End of recepcion-procedimientos proposal. Next phase: `sdd-spec` (and `sdd-design` in parallel per `next_recommended`).*
