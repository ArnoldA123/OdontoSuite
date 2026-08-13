# Tasks: PR0 — Foundation (`pr0-foundation-canvas-routes-and-status-badge`)

> **Change**: `ui-rollout-all-modules-2026-08`
> **Date**: 2026-08-11
> **Phase**: `tasks` (5 of 6) — produces the per-PR instruction set consumed by `sdd-apply`
> **PR scope**: PR0 only (`pr0-foundation-canvas-routes-and-status-badge`)
> **Branch base**: `main` → `feat/ui-rollout-pr0-foundation` (stacked 5 commits, merged via PR)

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Author | `sdd-tasks` sub-agent (this file) |
| Spec contract | `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` (18 PR0 scenarios) |
| Design contract | `openspec/changes/ui-rollout-all-modules-2026-08/design.md` (StatusBadge API + canvasRoutes 21-entry list + 3 PHPUnit tests) |
| Cross-cutting rules | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` §4 (`DLR-R-001..022`) |
| Pace / Strategy | `auto` / `auto-chain` |
| Review budget | 400 authored lines / PR (PR0 lands at ~385 authored LOC) |
| Strict TDD | `true` (3 RED tests precede or accompany their implementations) |
| Artifact store | `hybrid` (this file + Engram topic `sdd/ui-rollout-all-modules-2026-08/tasks`) |
| Commit shape | `work-unit-commits` skill (each commit = one deliverable behaviour + its tests) |

### Preflight snapshot (verbatim)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached
strict_tdd: true
```

### Spec scenarios PR0 satisfies

`APP-CORE-001..004`, `STATUS-PRIM-001..004`, `TEST-BASE-001..004`, `LEGACY-LIST-001..002`, `PAGIN-CONS-001..003` (audit only — no implementation work in PR0), `HOVER-LIFT-001` (audit only), `CANVAS-ROUTE-001`, `PER-MOD-001` (base class only — per-module subclasses ship with PR1+).

### Cross-cutting rules PR0 enables as testable invariants

`DLR-R-001` (canvas surface — guarded by `AppLayoutCanvasRoutesTest`), `DLR-R-009` (legacy alias ban — guarded by `LegacyAliasForbiddenTest`), `DLR-R-013` (no new deps — PR0 ships zero new packages), `DLR-R-017` (strict TDD — tests precede code), `DLR-R-018` (PHPUnit invariants stay green), `DLR-R-019` (CI gates), `DLR-R-020` (no dangling `var(--color-*)`), `DLR-R-021` (no `<style scoped>` — enforced for per-module files via `ModuleAppShellTestCase`; StatusBadge is a primitive, the rule does not apply to it).

### References

- `openspec/changes/ui-rollout-all-modules-2026-08/design.md` — PR0 contract, StatusBadge prop table, canvasRoutes final list, test method signatures, whole-token regex.
- `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` — 18 PR0 scenarios (Given/When/Then).
- `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` §4 — cross-cutting rules `DLR-R-001..022`.
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` — D6 (focus-ring token), D10 (press mechanism), D11 (reduced-motion fallback) precedents reused in StatusBadge.
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` lines 47–57 — "test pins rule, not literal" lesson.
- `openspec/config.yaml` `rules.tasks` — hierarchical numbering, 400-line budget, completable in one session.
- `tests/Unit/DesignSystem/PrimitivePressTest.php` lines 1–80 — style template for the new source-grep tests.
- `tests/Unit/DesignSystem/DashboardAppShellTest.php` — proven PHPUnit pattern for `<Module>AppShellTest`.
- `resources/js/components/ui/Badge.vue` — existing primitive; StatusBadge is **lighter** (filled-50, no border, pill-only).
- `resources/js/components/ui/StatusPill.vue` — existing appointment-status i18n mapping; StatusBadge is **generic** (no `STATUS_MAP`).
- `resources/js/components/layout/AppLayout.vue` line 507 — the `canvasRoutes` literal to extend.
- `AGENTS.md` §3 (commands), §5 (17-module inventory), §7 (`Ui*`-prefix convention, pnpm only, strict TDD).
- Work-unit-commits skill (`C:\Users\chomb\.config\opencode\skills\work-unit-commits\SKILL.md`) — commit shape.

---

## Goal

PR0 ships the foundation the rollout chain rides on. It produces five source-of-truth changes (one new `<UiStatusBadge>` primitive, one additive `AppLayout.canvasRoutes` extension from 3 to 21 routes, three new PHPUnit tests — `AppLayoutCanvasRoutesTest`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest` — plus the optional `PrimitivePressTest` extension to include `StatusBadge.vue` in the focus-ring loop), plus one verification gate. Total authored footprint lands near 385 lines (well under the 400-line review budget), fits in a single PR, and is independently revertible per `chained-pr` rules. Every PR1..PR12 in the chain depends on PR0 having landed; without PR0 the per-module tokenisation work has no canvas surface, no `StatusBadge` primitive, and no rule-asserting PHPUnit infrastructure to lean on.

---

## Dependency graph

Strict ordering — each task lists its prerequisite TASK IDs. Tasks 1.1 and 1.7 are non-committal gates (documentation and merge gate). Tasks 1.2 and 1.3 are the only production-code commits; Tasks 1.4, 1.5, and 1.6 are the test infrastructure commits. Strict TDD is enforced on Tasks 1.4 (RED before Task 1.3 GREEN) and 1.6 (RED on PR0-touched files).

```
   ┌─────────────────────────────────────────┐
   │ Task 1.1 — Verify baseline is green     │  (documentation step; no commit)
   └────────────────────┬────────────────────┘
                        ▼
   ┌─────────────────────────────────────────┐
   │ Task 1.2 — Add <UiStatusBadge> primitive│  ◀── independent production commit
   └────────────────────┬────────────────────┘
                        │
                        ▼
   ┌─────────────────────────────────────────┐
   │ Task 1.3 — Extend AppLayout.canvasRoutes│  ◀── independent production commit
   └────────────────────┬────────────────────┘
                        ▼
   ┌─────────────────────────────────────────┐
   │ Task 1.4 — AppLayoutCanvasRoutesTest    │  (RED→GREEN: 3→21 routes)
   └────────────────────┬────────────────────┘
                        ▼
   ┌─────────────────────────────────────────┐
   │ Task 1.5 — ModuleAppShellTestCase       │  (abstract base class)
   └────────────────────┬────────────────────┘
                        ▼
   ┌─────────────────────────────────────────┐
   │ Task 1.6 — LegacyAliasForbiddenTest     │  (forbidden-alias pin)
   └────────────────────┬────────────────────┘
                        ▼
   ┌─────────────────────────────────────────┐
   │ Task 1.7 — Full verification gate       │  (merge gate; no commit)
   └─────────────────────────────────────────┘
```

**Sequencing rationale** (work-unit-commits skill applied):

1. **Task 1.2 first (primitive)** — StatusBadge is the most isolated change (new file, no callers yet). Land it standalone so a reviewer can validate the prop API + reduced-motion + focus-ring scoping without scrolling through 200 lines of array literals.
2. **Task 1.3 second (AppLayout additive)** — the array extension is one-line additive + comment. Decoupled from StatusBadge. Landing it second lets `AppLayoutCanvasRoutesTest` (Task 1.4) read the FINAL 21-route state.
3. **Tasks 1.4 → 1.5 → 1.6 (test infrastructure)** — TDD discipline: `AppLayoutCanvasRoutesTest` reads the routes after Task 1.3 lands; `ModuleAppShellTestCase` is the base class that future per-module tests extend; `LegacyAliasForbiddenTest` closes the loop with the default `polishedFiles()` returning `[StatusBadge.vue, AppLayout.vue]` so the PR0 surface is asserted against the forbidden list.
4. **Task 1.7 (gate)** — final PHPUnit + lint + build sweep before merge.

---

## Tasks (sequenced; hierarchical numbering)

### Task 1.1 — Verify baseline is green

- **Prerequisite**: none
- **Spec reference**: implicit (no spec scenario; preflight sanity)
- **Command**: `php artisan test --filter=DesignSystem`
- **Expected**: 132 passed (1040 assertions) — the current vertical-slice state per `AGENTS.md` §6
- **Output**: a confirmation note in the apply-progress journal: `BASELINE GREEN, 1040 assertions`
- **Commit boundary**: NOT a commit; documentation step only
- **Notes**:
  - If the local SQLite caveat bites (28 pre-existing failures on `MODIFY COLUMN`), use the documented workaround: `docker compose up -d mysql && php artisan test --group=mysql`. Do NOT chase a green baseline if the local environment is broken; flag and defer to CI.
  - Record the assertion count verbatim — apply phase echoes it at the PR description footer.

---

### Task 1.2 — Add `<UiStatusBadge>` primitive at `resources/js/components/ui/StatusBadge.vue`

- **Prerequisite**: Task 1.1
- **Spec reference**: `STATUS-PRIM-001`, `STATUS-PRIM-002`, `STATUS-PRIM-003` (+ `STATUS-PRIM-004` first consumer arrives in PR2)
- **Files**: NEW file `resources/js/components/ui/StatusBadge.vue` (~80 lines)
- **Commit boundary**: yes — single commit titled `feat(ui): add StatusBadge primitive (PR0 of ui-rollout-all-modules-2026-08)`
- **Review-budget**: ~80 lines (well under 400)
- **Implementation summary** (the apply phase writes the code; this task only describes the contract):

  - **Props** (per `design.md` §2.2):
    - `variant: String` — default `'neutral'`, validator constrains to `['success', 'warning', 'error', 'info', 'neutral']`.
    - `label: String|Number` — default `null`. Display text; overridden by the `default` slot when provided.
    - `size: String` — default `'md'`, validator constrains to `['sm', 'md']`. No `lg`.
    - `showDot: Boolean` — default `false`. When true, prepends a 6px coloured dot (`w-1.5 h-1.5 rounded-full`).
    - `as: String` — default `'span'`, validator constrains to `['span', 'div']`. Render-as escape hatch.
  - **Slots** (per `design.md` §2.3):
    - `default` (optional) — overrides `label` when provided (use when the label needs an icon or formatter).
    - `icon-left` (optional) — inserted between the dot and the label/slot.
  - **Variant ramp classes** (per `design.md` §2.4, lighter than `Badge.vue`'s filled-100, no border):
    - `success` → `bg-systemGreen-50 text-systemGreen-700` + dot `bg-systemGreen-500`
    - `warning` → `bg-systemYellow-50 text-systemYellow-700` + dot `bg-systemYellow-500`
    - `error` → `bg-systemRed-50 text-systemRed-700` + dot `bg-systemRed-500`
    - `info` → `bg-systemBlue-50 text-systemBlue-700` + dot `bg-systemBlue-500`
    - `neutral` → `bg-systemGray-100 text-systemGray-700` + dot `bg-systemGray-500`
  - **Shape**: `rounded-full` always (pill-only; no `shape` prop).
  - **Size table** (per `design.md` §2.5):
    - `sm` → `px-2 py-0.5 text-xs min-h-[20px]`
    - `md` → `px-2.5 py-1 text-xs min-h-[24px]`
  - **Interactions** (per `design.md` §2.7):
    - Decorative colour-wash transition: `transition: background-color, color, box-shadow var(--motion-duration-normal) ease-out` — NO transform (StatusBadge is not a button).
    - Focus ring on `:focus-visible` via `box-shadow: var(--focus-ring-default)` inside `<style scoped>`.
    - Reduced-motion override under `@media (prefers-reduced-motion: reduce)` caps `transition-duration` at `200ms`.
  - **Root class hook**: a class such as `.status-badge` applied to the root `<component>` element so the scoped `<style>` block can target it without leaking. Apply-phase chooses between a root-class hook and a `[data-variant="..."]:focus-visible` selector — whichever keeps the file closest to 80 lines.
  - **Accessibility**: `role="status"`, `:aria-label="ariaLabel"` (`Estado: ${label}` when `label` provided, `undefined` otherwise), `aria-hidden="true"` on the dot.
  - **Imports**: only `computed` from `vue`. No composables, no event emissions, no watchers — the primitive is purely presentational (per `design.md` §5.1).
  - **Token references**: only `var(--focus-ring-default)`, `var(--motion-duration-normal)`, `var(--motion-easing-ios)` (eased via `ease-out` per `STATUS-PRIM-002`). All three are emitted by `scripts/build-tokens-css.mjs`; `GeneratedTokensCssTest` is the standing witness.

- **Out of scope (negative space, per `design.md` §2.9)**:
  - NOT a wrapper around `Badge.vue` (different ramp, no border, no `dismissible`, no `shape`).
  - NOT a wrapper around `StatusPill.vue` (different domain — no `STATUS_MAP`).
  - NOT a button (no `tabindex`, no press transform).
  - NOT a `<style scoped>`-heavy component (only focus ring + reduced-motion override).

- **Acceptance**:
  - File exists at `resources/js/components/ui/StatusBadge.vue`.
  - All 5 props + 2 slots wired.
  - Scoped style block ≤ 10 lines (focus ring + reduced-motion).
  - `LegacyAliasForbiddenTest` (Task 1.6) returns GREEN for this file (the default `polishedFiles()` includes `StatusBadge.vue`).
  - `pnpm build` emits the new utility classes (Tailwind content path covers `resources/js/components/ui/*.vue`).

---

### Task 1.3 — Extend `AppLayout.canvasRoutes` to 21 routes

- **Prerequisite**: Task 1.2 (the primitive must exist first so the post-merge PHPUnit sweep does not surface a missing-file surprise). Strictly speaking this task is independent of Task 1.2 (they touch different files), but the dependency keeps the apply order additive without surprises.
- **Spec reference**: `APP-CORE-001`, `APP-CORE-002`, `APP-CORE-004`
- **Files**: EDIT `resources/js/components/layout/AppLayout.vue` line 507 region — replace `const canvasRoutes = ['/dashboard', '/login', '/404']` with the 21-route array (cite verbatim from `design.md` §3.1).
- **Commit boundary**: yes — single commit titled `feat(ui): extend AppLayout.canvasRoutes to 21 polished routes (PR0)`
- **Review-budget**: ~25 lines (1-line additive + comment block + blank line above)
- **The 21-route array** (per `design.md` §3.1, copied verbatim into the task plan so apply phase does not have to flip back):

  ```js
  // PR0 (ui-rollout-all-modules-2026-08) — canvas surface for every polished
  // module route. Sources: foundation-primitives spec APP-CORE-001 + AGENTS.md
  // §5 (17 modules). Order: already-polished first, then domain-grouped.
  // This array is FROZEN at PR0 merge — category PRs MUST NOT extend or
  // narrow it. The regression guard is tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php.
  const canvasRoutes = [
    // Already polished (vertical slice)
    '/dashboard',
    '/login',
    '/404',
    // Pagos
    '/cash-register',
    '/cash-register/ready-to-bill',
    '/quotations',
    // Catálogo
    '/procedure-catalog',
    '/procedure-stats',
    // Admin
    '/professionals',
    '/environments',
    '/appointment-types',
    // Operación
    '/calendar',
    // Clínico
    '/patients',
    '/medical-records',
    '/specialty-records',
    '/treatment-plans',
    // Catálogo tail (PR3 cluster)
    '/my-procedures',
    '/reception-procedures',
    // Análisis
    '/ai-analysis',
    // BI
    '/business-intelligence',
    // Settings (canvas surface only — internals deferred per OQ#3)
    '/settings/branches',
    '/settings/payment-methods',
  ]
  ```

- **Add inline comment** above the array (mandatory per `design.md` §3.2): documents PR0 source, spec scenario ID (`APP-CORE-001`), the FROZEN nature of the array, and the AGENTS.md §5 reference.
- **DO NOT** touch any other part of `AppLayout.vue` (per spec `APP-CORE-004` and `design.md` §3.3):
  - `isCanvasRoute` computed (line 508) — UNCHANGED.
  - The `<element :class="isCanvasRoute ? 'bg-canvas' : ''">` binding — UNCHANGED.
  - No sidebar / topbar / event listener / `<script setup>` edits.
- **Acceptance**:
  - `git diff -- resources/js/components/layout/AppLayout.vue` shows ONLY the `canvasRoutes` array literal + the inline comment block. Any other change fails review.
  - Total diff ≤ 25 lines.
  - `AppLayoutCanvasRoutesTest` (Task 1.4) returns GREEN.
  - `pnpm build` and `pnpm lint:check` remain GREEN.

---

### Task 1.4 — Add `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php`

- **Prerequisite**: Task 1.3 (the 21-route array must exist in AppLayout.vue for the test to read)
- **Spec reference**: `CANVAS-ROUTE-001`
- **Files**: NEW file `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` (~45 lines per `design.md` §7 estimate; budgeted ~80 here to absorb the data-provider + sentinel + docblock)
- **Commit boundary**: yes — single commit titled `test(ui): pin AppLayout.canvasRoutes list in AppLayoutCanvasRoutesTest (PR0)`
- **Review-budget**: ~80 lines
- **Implementation summary**:
  - Class `AppLayoutCanvasRoutesTest extends TestCase` under namespace `Tests\Unit\DesignSystem` (per AGENTS.md §7 — PHPUnit `Test\` suffix via the project's autoload convention; mirror `PrimitivePressTest`'s namespace).
  - `private const string APP_LAYOUT_PATH` resolves to the project root (`tests/` is 2 levels deep → `dirname(__DIR__, 3)`) plus `resources/js/components/layout/AppLayout.vue`.
  - `private const array EXPECTED_ROUTES` lists the 21 expected routes (cite the array literal from `design.md` §3.1 verbatim; this is a literal-list pin because the rule IS the list of routes — `canvasRoutes` is an array of strings, not a token reference).
  - **Test methods**:
    - `test_canvas_routes_file_exists` — `assertFileExists` against the AppLayout path.
    - `test_canvas_routes_array_present` — regex `/const\s+canvasRoutes\s*=\s*\[/` against the file source.
    - `test_each_expected_route_is_in_canvas_routes` — `@dataProvider expectedRouteProvider()` (one data row per EXPECTED_ROUTES entry). For each route, the test extracts the array body (between `[` and `]`, tolerating comments + whitespace + trailing commas), then `preg_match_all("/'([^']+)'/", $body, $matches)` enumerates the quoted strings and asserts the route appears.
    - `test_no_legacy_narrowing_to_vertical_slice_routes` — **SENTINEL**. Counts routes in the array literal; fails if the count is exactly 3 (the vertical-slice set) AND none of the module routes beyond `/dashboard | /login | /404` are present. Message: `"canvasRoutes was narrowed back to the vertical-slice set; PR0 extension is required."`
  - **Regex tolerance**: array body extractor must tolerate single-line `//` comments, block `/* ... */` comments, whitespace, line-breaks, and trailing commas. The tolerant parser is the contract: the test must remain a "the array contains every expected route" assertion, not a literal-string pin (per `design.md` §8 risk #2 mitigation).
  - **Data provider**: `@dataProvider expectedRouteProvider()` exposes each route as a separate test case. PHPUnit's `@dataProvider` runs the test method once per data row; one failure points at one missing route.
  - **Failure messages**: each failing test includes the route name in its message so a regression pinpoints which route dropped.
- **Acceptance**:
  - File exists at `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php`.
  - `php artisan test --filter=AppLayoutCanvasRoutesTest` is GREEN with 24 test runs (1 file exists + 1 array present + 21 data-provider rows + 1 sentinel).
  - **Negative verification** (apply phase must do this manually before merging): temporarily narrow the array to the 3 vertical-slice routes, confirm the sentinel fires, revert. Document the negative-verification outcome in the apply-progress journal.

---

### Task 1.5 — Add `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base class)

- **Prerequisite**: Task 1.4 (cosmetic — independent). The base class has no compile-time dependency on `AppLayoutCanvasRoutesTest`, but the dependency keeps apply order linear.
- **Spec reference**: `TEST-BASE-001`, `TEST-BASE-002`, `TEST-BASE-003`, `TEST-BASE-004`
- **Files**: NEW file `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (~120 lines; absorbs abstract boilerplate + 5 rule assertions + helper + docblock)
- **Commit boundary**: yes — single commit titled `test(ui): add ModuleAppShellTestCase rule-asserting base class (PR0)`
- **Review-budget**: ~120 lines
- **Implementation summary**:
  - Class `abstract class ModuleAppShellTestCase extends TestCase` under namespace `Tests\Unit\DesignSystem`.
  - **Abstract method**: `abstract protected function polishedFiles(): array;` — returns absolute paths to the module's polished `.vue` files.
  - **Helper**: `protected function projectRoot(): string { return dirname(__DIR__, 3); }` (mirrors the convention in `PrimitivePressTest` if present, else mirrors `DashboardAppShellTest`).
  - **Test methods** (all data-provider-driven on `polishedFileProvider()` so each file gets its own test row):
    - `test_page_references_canvas_token` — asserts the file matches `/bg-canvas\|var\(--color-canvas\)\|rgb\(242,\s*242,\s*247\)/` (the rule: canvas token is referenced; the file references the token, not a specific literal class string).
    - `test_no_legacy_border_theme_literal` — asserts the file does NOT match `/(?<![\w-])border-theme(?![\w-])/`. Negative lookbehind + lookahead excludes `border-theme-light` and `border-theme-dark` variants (the modifier variants contain `-light`/`-dark`, which match `[\w-]` after `border-theme`).
    - `test_focus_ring_consumes_token` — **conditional**. If the file contains `:focus` or `:focus-visible`, it MUST also contain `var(--focus-ring-default)`. Implementation: `if (preg_match('/:focus(-visible)?/', $src)) { assertMatchesRegularExpression('/var\(--focus-ring-default\)/', $src); } else { $this->assertTrue(true); }` (always-true branch when `:focus` is absent so PHPUnit does not flag a risky test).
    - `test_no_legacy_focus_ring_alias` — asserts the file does NOT contain `focus:ring-primary-500` OR `focus:border-accent` (whole-token regex with negative lookbehind + lookahead).
    - `test_no_style_scoped` — asserts the file does NOT contain `<style scoped>` (after stripping strings + comments per `STATUS-PRIM-003` precedent).
  - **Strip helper**: `private function stripStringsAndComments(string $src): string` — removes JS/TS single + double quotes, block comments, and Vue template strings so the regex patterns match against actual class strings, not pattern examples embedded in comments. Reused by `LegacyAliasForbiddenTest` (Task 1.6) via a small `TestSourceHelpers` trait OR by copy-pasting the helper (Task 1.6 prefers copy-paste to avoid coupling).
  - **Data provider**: `@dataProvider polishedFileProvider()` enumerates each file from `polishedFiles()` as a separate test row.
  - **Docblock**: top-of-class docblock references `archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` lines 47–57 ("test pins example vs rule" lesson) so future maintainers understand why the base class asserts the rule, not the literal.
- **Why these five rules** (per `design.md` §4.2):
  - `test_page_references_canvas_token` → `DLR-R-001` (canvas surface).
  - `test_no_legacy_border_theme_literal` → `DLR-R-002` (hairline, not border-theme). PR0 list excludes `-light`/`-dark` modifier variants (added per-category per `design.md` §4.2).
  - `test_focus_ring_consumes_token` → `DLR-R-004` (composed focus ring).
  - `test_no_legacy_focus_ring_alias` → `DLR-R-004` (negative form).
  - `test_no_style_scoped` → `DLR-CORE-008` / `DLR-R-021` (no new `<style scoped>` blocks). Standing guard for the 6 files with existing `<style scoped>` blocks (TreatmentPlansPage, CashRegisterPage, AiAnalysisPage, AnalyzingModal, CreatePatientInline, TreatmentPlanModal) during their respective PRs.
- **Acceptance**:
  - File exists at `tests/Unit/DesignSystem/ModuleAppShellTestCase.php`.
  - The class is `abstract` (cannot be instantiated directly).
  - **Negative verification** (apply phase): confirm the file fails the rule assertions when given a synthetic file with `border-theme` or `focus:ring-primary-500`. Document the negative-verification outcome.
  - The base class extends `PHPUnit\Framework\TestCase` (or the project's TestCase alias if one exists per `tests/Unit` autoload).

---

### Task 1.6 — Add `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php`

- **Prerequisite**: Task 1.5
- **Spec reference**: `LEGACY-LIST-001`, `LEGACY-LIST-002`
- **Files**: NEW file `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` (~80 lines; absorbs the `LEGACY_ALIASES` constant + the default `polishedFiles()` + 3 test methods + docblock)
- **Commit boundary**: yes — single commit titled `test(ui): pin legacy alias forbidden set in LegacyAliasForbiddenTest (PR0)`
- **Review-budget**: ~80 lines
- **Implementation summary**:
  - Class `LegacyAliasForbiddenTest extends ModuleAppShellTestCase` under namespace `Tests\Unit\DesignSystem`.
  - **Constant** (per `design.md` §4.3):

    ```php
    private const LEGACY_ALIASES = [
        // Success ramp legacy (replaced by <UiStatusBadge variant="success">)
        'bg-success-100',
        'bg-success-500',
        'bg-success-600',
        'bg-success-700',
        'text-success-700',
        // Warning ramp legacy (replaced by <UiStatusBadge variant="warning">)
        'bg-warning-100',
        'text-warning-700',
        // Error ramp legacy (replaced by <UiStatusBadge variant="error">)
        'bg-error-100',
        'text-error-700',
        'bg-error-600',
        // Accent / primary legacy (replaced by tokenised systemBlue-* ramps)
        'text-accent',
        'bg-accent',
        'hover:text-primary-700',
        'bg-primary-50',
        'bg-primary-100',
        'bg-primary-200',
        // Focus ring legacy (replaced by var(--focus-ring-default))
        'focus:ring-primary-500',
        'focus:border-accent',
    ];
    ```

  - **`border-theme` is EXCLUDED from the PR0 list** (per `design.md` §4.3 explicit exclusion + `LEGACY-LIST-002` — `ModuleAppShellTestCase::test_no_legacy_border_theme_literal` is the immediate per-module pin; the `LEGACY_ALIASES` array grows opportunistically per-category as AppLayout/Card/Sidebar/Topbar migrate).
  - **Default `polishedFiles()`** (per `design.md` §4.3):

    ```php
    protected function polishedFiles(): array
    {
        return [
            $this->projectRoot() . '/resources/js/components/ui/StatusBadge.vue',
            $this->projectRoot() . '/resources/js/components/layout/AppLayout.vue',
        ];
    }
    ```

    Categories override `polishedFiles()` and append their module files. The default validates the 2 PR0-touched files.
  - **Test methods**:
    - `test_legacy_aliases_constant_is_non_empty` — `assertNotEmpty(self::LEGACY_ALIASES)`.
    - `test_no_legacy_alias_in_polished_file` — `@dataProvider polishedFileProvider()` (inherited from base). For each alias, for each file: extract the source, strip strings + comments, run the whole-token regex `/(?<![\w-])ALIAS(?![\w-])/`. Assertion: zero matches per file per alias. Failure message includes the alias + the file path so a regression pinpoints the offender.
    - `test_alias_patterns_are_whole_token` (sanity) — unit-tests the regex against synthetic inputs:
      - `bg-success-100` → MATCH against `bg-success-100 text-success-700`.
      - `bg-success-100` → NO MATCH against `bg-success-1000` (the spec's "must NOT falsely match" requirement).
      - `border-theme` → NO MATCH against `border-theme-light` (modifier variant, excluded).
  - **Whole-token regex** (per `design.md` §4.3 final corrected form):

    ```php
    $aliasEscaped = preg_quote($alias, '/');
    $pattern = '/(?<![\w-])' . $aliasEscaped . '(?![\w-])/';
    ```

    For `bg-success-100`:
    - preceded by `-` (in `[\w-]`) → NO match (correct for hypothetical nested classes).
    - followed by `0` (in `[\w-]`) → NO match (correct — the bug case).
    - followed by ` ` (NOT in `[\w-]`) → MATCH (correct).
- **Acceptance**:
  - File exists at `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php`.
  - `php artisan test --filter=LegacyAliasForbiddenTest` is GREEN.
  - **Negative verification** (apply phase): inject a `bg-success-100` class into a synthetic temp file, confirm the test fires. Document the negative-verification outcome.
  - The test acts as a tripwire: any future module that re-introduces a `LEGACY_ALIASES` pattern fails the test (per spec `LEGACY-LIST-002`).

---

### Task 1.7 — Run full PHPUnit DesignSystem suite + `pnpm build` + `pnpm lint:check`

- **Prerequisite**: Tasks 1.2 through 1.6 all complete
- **Spec reference**: implicit (PR0 merge gate; cross-cutting rules `DLR-R-018` + `DLR-R-019`)
- **Steps**:

  1. **PHPUnit** — `php artisan test --filter=DesignSystem`
     - **Expected**: all 132 pre-existing tests + ~6-7 new tests GREEN.
     - New test count breakdown:
       - `AppLayoutCanvasRoutesTest` → 1 file-exists + 1 array-present + 21 data-provider rows + 1 sentinel = 24 runs.
       - `ModuleAppShellTestCase` → 5 rule assertions × N data-provider rows from default `polishedFiles()` (default returns `[]` in the base class itself; subclasses append) = 0 concrete runs from the base class alone, but the base class compiles.
       - `LegacyAliasForbiddenTest` → 1 constant-non-empty + ~17 aliases × 2 default files = ~34 data-provider rows + 1 sanity = ~36 runs.
     - **Gate**: every pre-existing test stays GREEN (no regression on `TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `UseSpringMathTest`).
  2. **Frontend build** — `pnpm build`
     - **Expected**: Vite/Tailwind emits `tokens.generated.css` (unchanged) + the new utility classes (`bg-systemGreen-50`, etc., already present in the existing palette) for `StatusBadge.vue`.
     - **Gate**: build succeeds without warnings about missing utility classes (Tailwind content path covers `resources/js/components/ui/*.vue`).
  3. **Frontend lint** — `pnpm lint:check`
     - **Expected**: Vue + JS lint pass. ESLint catches unused imports in `StatusBadge.vue` (`computed` is used; no dead imports).
  4. **Frontend format** (optional, NOT a gate) — `pnpm format:check`
     - If prettier flags formatting drift, run `pnpm format` and re-run lint + build.

- **Expected outcome**: all green; PR0 ready to merge.
- **Commit boundary**: NO commit — this is the PR merge gate.
- **Output**: a confirmation table per gate:

  | Gate | Command | Expected | Actual | PASS/FAIL |
  |---|---|---|---|---|
  | Backend tests | `php artisan test --filter=DesignSystem` | 132 pre-existing + ~60 new GREEN | _(apply phase fills)_ | _(apply phase fills)_ |
  | Frontend build | `pnpm build` | exit 0; no warnings | _(apply phase fills)_ | _(apply phase fills)_ |
  | Frontend lint | `pnpm lint:check` | exit 0 | _(apply phase fills)_ | _(apply phase fills)_ |
  | Frontend format | `pnpm format:check` | exit 0 (optional) | _(apply phase fills)_ | _(apply phase fills)_ |

- **PR description footer** (echoes from Task 1.1 baseline): `BASELINE GREEN + ~60 NEW TESTS, all assertions green`.

---

## Risk register (for `sdd-apply` to absorb)

| # | Risk | Blast radius | Mitigation |
|---|---|---|---|
| 1 | `StatusBadge.vue` ballooning past 80 lines (someone adds a `STATUS_MAP`, a `dismissible` mode, or a `shape` prop) | The primitive becomes a kitchen-sink and duplicates `Badge.vue` or `StatusPill.vue`; the "primitive immutability" guarantee weakens | Stick to the 5 props + 2 slots list above. No `STATUS_MAP` (StatusPill's domain). No `dismissible` (Badge has it). No `shape` (pill-only). Apply phase enforces line budget via PR review (reviewer is the gate). |
| 2 | `AppLayoutCanvasRoutesTest` regex parser breaks if `AppLayout.vue` is refactored (e.g. the array becomes a computed, an external constant, or a getter) | The test fails for the wrong reason; category PRs ship with broken tests | Tolerant regex tolerates comments + whitespace + trailing commas. Sentinel test catches narrow regressions. If the refactor is unavoidable, the test is updated to read the array contents from the new shape — the test must remain a "the array contains every expected route" assertion, not a literal-string pin. |
| 3 | `LegacyAliasForbiddenTest` alias list misses the user's actual legacy aliases (incomplete list) | A category PR introduces a forgotten alias and the test passes; the rollout misses a defect | The `LEGACY_ALIASES` array is **extended opportunistically** per spec `LEGACY-LIST-002`: when a defect is observed during a module PR, the pattern is added in the same PR. `border-theme` is the explicit exclusion in PR0 (re-included as AppLayout/Card/Sidebar/Topbar migrate). |
| 4 | The AppLayout edit accidentally touches other AppLayout code | Per spec `APP-CORE-004`, the PR0 edit is the `canvasRoutes` array literal ONLY. Any other AppLayout change is a scope violation | Apply phase uses a tight PR diff: `git diff -- resources/js/components/layout/AppLayout.vue` must show ONLY the array literal + the inline comment block. Any other change fails review. |
| 5 | The 21-route list misses a route that PR1+ needs | A category PR's module page does NOT render on canvas (no `bg-canvas`) → the module's canvas/surface contrast breaks | The list is exhaustive per spec `APP-CORE-001` + AGENTS.md §5. If a missing route is discovered during a category PR, ESCALATE to spec review (do NOT silently add to the array). `AppLayoutCanvasRoutesTest` will fail and surface the gap. |
| 6 | StatusBadge ramp classes (`bg-systemGreen-50`, etc.) conflict with Tailwind purge config | The classes are stripped from production CSS and the pill renders as a transparent span | The Tailwind config (`tailwind.config.js`) already includes `resources/js/components/ui/*.vue` in its content paths (verified per AGENTS.md §3 conventions); the new file is automatically picked up. The `frontend-build` CI job is the standing witness. |
| 7 | `ModuleAppShellTestCase` rule assertions miss real defects (e.g. someone writes `bg-canvas` in a comment, satisfying the rule without actually using the token) | A module renders the wrong canvas/surface contrast | The rule is a regression guard for the COMMITTED file shape, not a behavioural test. The standing `DashboardAppShellTest` precedent (per spec `DLR-CORE-008`) uses the same source-grep style; the rule-vs-literal distinction is what `archive-report.md` lines 47–57 explicitly endorsed. The visual sweep (per `proposal.md` §4.3) is the behavioural witness. |
| 8 | The whole-token regex in `LegacyAliasForbiddenTest` falsely matches a modifier variant (e.g. `border-bg-success-100`) | A module reintroduces a forbidden alias via a concatenated class string | The `test_alias_patterns_are_whole_token` sanity test catches this regression at PR0 time. The negative-lookbehind `(?<![\w-])` excludes matches preceded by `-` (so `border-bg-success-100` does NOT trigger). |
| 9 | Local SQLite `MODIFY COLUMN` failures bite the apply phase's test runs | PR0 appears broken locally even though it's green in CI | Use the documented workaround: `docker compose up -d mysql && php artisan test --group=mysql`. Do NOT chase a green baseline if the local environment is broken; flag and defer to CI. |
| 10 | The 3 test files together exceed the 400-line review budget | The PR splits across the 400-line gate; apply phase must commit one test at a time | The per-file budget is `~80 + ~80 + ~120` (three test files). Combined they are ~280 lines but spread across 3 commits. The 400-line budget is per-PR (cumulative diff), so the PR still lands under budget when summing across commits. |

---

## PR0 boundary summary

- **Total commits**: 5 (Tasks 1.2, 1.3, 1.4, 1.5, 1.6 — Task 1.1 is a documentation step, Task 1.7 is the merge gate).
- **Total lines authored**: ~80 (`StatusBadge.vue`) + ~25 (`AppLayout.vue` edit) + ~80 (`AppLayoutCanvasRoutesTest`) + ~120 (`ModuleAppShellTestCase`) + ~80 (`LegacyAliasForbiddenTest`) = **~385 LOC** (under the 400-line review budget).
- **Final gate**: Task 1.7 (no commit; PHPUnit + pnpm build + pnpm lint:check all GREEN).
- **Pre-merge**: branch `feat/ui-rollout-pr0-foundation` from `main`; 5 commits stacked; merge via PR with the standard SDD body referencing the 18 spec scenarios.
- **Per-commit summary** (work-unit-commits skill applied — each commit has clear start/finished state + verification + rollback):

  | Commit | Title | Files | Lines | Start state | Finished state | Verification | Rollback boundary |
  |---|---|---|---|---|---|---|---|
  | 1 | `feat(ui): add StatusBadge primitive (PR0 of ui-rollout-all-modules-2026-08)` | NEW `resources/js/components/ui/StatusBadge.vue` | ~80 | Primitive does not exist | Primitive ships with 5 props + 2 slots + scoped focus-ring + reduced-motion override | `pnpm build` succeeds; `pnpm lint:check` clean; no test references StatusBadge yet | Remove `StatusBadge.vue`; no callers to revert |
  | 2 | `feat(ui): extend AppLayout.canvasRoutes to 21 polished routes (PR0)` | EDIT `resources/js/components/layout/AppLayout.vue` | ~25 | `canvasRoutes` = 3 entries | `canvasRoutes` = 21 entries + inline comment | `git diff -- AppLayout.vue` shows ONLY the array literal + comment; `pnpm build` clean | Restore the 3-entry literal + remove the comment |
  | 3 | `test(ui): pin AppLayout.canvasRoutes list in AppLayoutCanvasRoutesTest (PR0)` | NEW `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | ~80 | No canvas-routes test exists | Test reads AppLayout.vue and asserts every expected route + sentinel | `php artisan test --filter=AppLayoutCanvasRoutesTest` GREEN (24 runs) | Remove the test file; no production code reverted |
  | 4 | `test(ui): add ModuleAppShellTestCase rule-asserting base class (PR0)` | NEW `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | ~120 | No base class exists | Abstract base class with 5 rule assertions + helper + data-provider | `php artisan test --filter=ModuleAppShellTestCase` GREEN (compiles; 0 concrete runs since `polishedFiles()` is `[]`) | Remove the base class; no subclasses exist yet (subclasses arrive with PR1+) |
  | 5 | `test(ui): pin legacy alias forbidden set in LegacyAliasForbiddenTest (PR0)` | NEW `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | ~80 | No legacy-alias test exists | Test asserts zero whole-token matches against `LEGACY_ALIASES` for the 2 PR0-touched files | `php artisan test --filter=LegacyAliasForbiddenTest` GREEN (~36 runs) | Remove the test file; no production code reverted |

- **Optional extension (not in the 5-commit plan, surfaced for reviewer visibility)**: extending `tests/Unit/DesignSystem/PrimitivePressTest.php` to append `'StatusBadge.vue'` to the `HEADLINE` primitive set (per `design.md` §7 + spec `STATUS-PRIM-003`). This is `+5 lines` and additive — does not block PR0 merge. Apply phase MAY include it in commit 5 (combine with `LegacyAliasForbiddenTest` since both are test additions) or split it into a 6th commit (`test(ui): register StatusBadge.vue in PrimitivePressTest primitive set (PR0)`). Recommendation: fold into commit 5 to keep the commit count at 5 and stay well under the 400-line budget.

---

## References

- `openspec/changes/ui-rollout-all-modules-2026-08/design.md` — PR0 contract (StatusBadge API, canvasRoutes final list, test method signatures, whole-token regex, scope rules).
- `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` — 18 PR0 scenarios (Given/When/Then) the design satisfies.
- `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` — cross-cutting rules `DLR-R-001..022` + per-module scenarios `DLR-MOD-001..018`.
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` — D6 (focus-ring token), D10 (press mechanism), D11 (reduced-motion fallback) precedents reused in StatusBadge.
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` lines 47–57 — "test pins rule, not literal" lesson applied to `ModuleAppShellTestCase` + `LegacyAliasForbiddenTest`.
- `tests/Unit/DesignSystem/PrimitivePressTest.php` — PHPUnit style template for source-grep rule-asserting tests.
- `tests/Unit/DesignSystem/DashboardAppShellTest.php` — proven PHPUnit pattern for `<Module>AppShellTest`.
- `resources/js/components/ui/Badge.vue` — existing primitive (filled-100, with border); StatusBadge is lighter (filled-50, no border).
- `resources/js/components/ui/StatusPill.vue` — existing appointment-status i18n mapping (`STATUS_MAP`); StatusBadge is generic.
- `resources/js/components/layout/AppLayout.vue` line 507 — the `canvasRoutes` literal extended in Task 1.3.
- `resources/js/design-system/tokens.js` — the frozen source of truth (PR0 ships NO new tokens).
- `resources/css/tokens.generated.css` — generated CSS the rollout consumes. StatusBadge references `--focus-ring-default`, `--motion-duration-normal`, `--motion-easing-ios` — all emitted by `scripts/build-tokens-css.mjs`.
- `openspec/config.yaml` `rules.tasks` — hierarchical numbering, 400-line budget, completable in one session.
- `AGENTS.md` §3 (commands: `php artisan test`, `pnpm build`, `pnpm lint:check`), §5 (17-module inventory), §7 (`Ui*`-prefix convention, pnpm only, strict TDD).
- `C:\Users\chomb\.config\opencode\skills\work-unit-commits\SKILL.md` — commit shape; each PR0 commit follows the work-unit pattern.

---

*End of PR0 tasks. Forward to `sdd-apply` for implementation.*
