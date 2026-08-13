# Spec: Foundation Cross-Cutting Primitives (Domain 2) — `ui-rollout-all-modules-2026-08`

> **Delta type**: NEW capability spec, sibling of `design-language-rollout`.
> Domain 2 covers the PR0 prerequisite work that the entire rollout chain
> depends on: the `AppLayout.canvasRoutes` extension, the `<UiStatusBadge>`
> primitive, the `ModuleAppShellTestCase` rule-asserting base class, the
> `<UiPagination>` consolidation, and the new PHPUnit invariants.
>
> **Naming convention**: follows the archive convention from
> `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/`
> (one spec per domain under `specs/<domain>/spec.md`). The sibling file
> `specs/design-language-rollout/spec.md` covers the per-module rollout
> (Domain 1).

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Date | 2026-08-11 |
| SDD phase | `spec` (3 of 6) |
| Author | `sdd-spec` sub-agent |
| Domain | 2 — Foundation Cross-Cutting Primitives |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/spec`) |
| Pace | `auto` |
| Delivery strategy | `auto-chain` (forward to `sdd-tasks` for chain realisation) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` (forward to apply/verify) |
| PR scope | PR0 ONLY (the prerequisite for the entire chain) |
| Companion spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` (Domain 1) |
| Upstream artifacts | `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §7.2 (PR0 forecast) |
|  | `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` §3.2 (per-module visual state) |
|  | `openspec/specs/premium-design-foundation/spec.md` (the foundation this domain extends) |
|  | `resources/js/components/layout/AppLayout.vue` line 507 (`canvasRoutes`) |
|  | `resources/js/components/ui/Card.vue` (the proven card primitive pattern) |
|  | `resources/js/components/ui/Badge.vue` (the proven badge primitive pattern) |
|  | `tests/Unit/DesignSystem/DashboardAppShellTest.php` (the proven PHPUnit pattern) |

### Preflight snapshot (verbatim from session preflight)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached
strict_tdd: true
```

### PR0 reverse dependency

Every PR in the chain (PR1..PR12) depends on PR0. The PR0 work is required
FIRST. The scenario set below describes the PR0 contract exactly: each
scenario pins what makes the rest of the rollout possible.

---

## 1. Capability index

| Scenario ID | Sub-domain | RFC keyword | One-line summary |
|---|---|---|---|
| `APP-CORE-001` | AppLayout | MUST | `canvasRoutes` covers all 17 module routes + settings + `/procedure-stats` |
| `APP-CORE-002` | AppLayout | MUST | `isCanvasRoute` computed is the only gating mechanism (no module-level override) |
| `APP-CORE-003` | AppLayout | MUST | Canvas surface renders on the listed routes via `bg-canvas` (the existing AppLayout binding) |
| `APP-CORE-004` | AppLayout | MUST | The `canvasRoutes` extension is the ONLY functional change to AppLayout — no other concurrency-related refactor |
| `STATUS-PRIM-001` | `<UiStatusBadge>` | MUST | New primitive at `resources/js/components/ui/StatusBadge.vue`; variants `success / warning / error / info / neutral` |
| `STATUS-PRIM-002` | `<UiStatusBadge>` | MUST | Internal tokenised ramps — no raw Tailwind variant classes for the variant ramps |
| `STATUS-PRIM-003` | `<UiStatusBadge>` | MUST | Reduced-motion fallback + focus ring via `var(--focus-ring-default)` |
| `STATUS-PRIM-004` | `<UiStatusBadge>` | SHOULD | `QuotationStatusBadge` migrated to use it internally (first consumer in PR2) |
| `TEST-BASE-001` | `ModuleAppShellTestCase` | MUST | Base class at `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` asserts the rule, not the literal |
| `TEST-BASE-002` | `ModuleAppShellTestCase` | MUST | Rule: page surface references `bg-canvas` or `var(--color-canvas)`; key tables/forms reference `--color-hairline`; focus ring references `--focus-ring-default`; numeric columns use `tabular-nums` |
| `TEST-BASE-003` | `ModuleAppShellTestCase` | MUST | Rule: no `<style scoped>` block in the module file |
| `TEST-BASE-004` | `ModuleAppShellTestCase` | MAY | Subclasses for modules that introduce new patterns (e.g. Caja enforces no `<script>` change) |
| `LEGACY-LIST-001` | `LegacyAliasForbiddenTest` | MUST | Pins the forbidden legacy alias list (initial set enumerated) |
| `LEGACY-LIST-002` | `LegacyAliasForbiddenTest` | MUST | Extends opportunistically per module PR — when a defect is observed, the pattern is added to the list |
| `PAGIN-CONS-001` | `<UiPagination>` consolidation | MUST | When the second module that touches pagination lands (PR3 — Recepción procedimientos), `<PaginationComponent>` is removed and `<UiPagination>` is the only remaining primitive |
| `PAGIN-CONS-002` | `<UiPagination>` consolidation | MUST NOT | No standalone consolidation PR; the consolidation rides the per-module PR |
| `PAGIN-CONS-003` | `<UiPagination>` | MUST | The `Ui*`-prefixed primitive is the project convention per AGENTS.md §7; the duplicate is a pre-existing inconsistency |
| `HOVER-LIFT-001` | `hover-lift` utility | SHOULD | `hover-lift` utility in `resources/css/utilities.css` is confirmed reachable; per-module PRs migrate where it substitutes a `<UiCard clickable>` |
| `CANVAS-ROUTE-001` | `AppLayoutCanvasRoutesTest` | MUST | New invariant test asserting `canvasRoutes` array contains every polished module route |
| `PER-MOD-001` | Per-module structure tests | MUST | At least one per module, derived from `ModuleAppShellTestCase` (assert the rule, not the literal) |

---

## 2. Domain 2 — Foundation Cross-Cutting Primitives

### 2.1 `AppLayout.canvasRoutes` extension

#### Scenario: `APP-CORE-001` — `canvasRoutes` covers every polished module route + settings + `/procedure-stats`

- GIVEN `resources/js/components/layout/AppLayout.vue` line 507 currently declares `const canvasRoutes = ['/dashboard', '/login', '/404']`
- WHEN PR0 lands
- THEN `canvasRoutes` is extended to include the FULL list of polished module routes:
  - `/dashboard` (already in; vertical slice)
  - `/login` (already in; vertical slice)
  - `/404` (already in; vertical slice)
  - `/calendar` (Calendario)
  - `/patients` (Pacientes)
  - `/professionals` (Profesionales)
  - `/environments` (Ambientes)
  - `/appointment-types` (Tipos de cita)
  - `/cash-register` (Caja)
  - `/cash-register/ready-to-bill` (Caja)
  - `/business-intelligence` (BI)
  - `/treatment-plans` (Planes de tratamiento)
  - `/quotations` (Presupuestos)
  - `/medical-records` (Historias clínicas)
  - `/specialty-records` (Registros especialidad)
  - `/ai-analysis` (Análisis IA)
  - `/procedure-catalog` (Catálogo procedimientos)
  - `/procedure-catalog/:id` (Catálogo procedimientos detail)
  - `/my-procedures` (Mis procedimientos)
  - `/reception-procedures` (Recepción procedimientos)
  - `/procedure-stats` (Estadísticas catálogo — mounted under the catalog route family)
  - `/settings/branches` (Settings — canvas surface only, internals tokenisation deferred per OQ#3)
  - `/settings/payment-methods` (Settings — canvas surface only)

- AND the change is a single additive array literal (NOT a refactor of the gating mechanism)
- AND `AppLayoutCanvasRoutesTest` (the new PHPUnit invariant) pins the full list

#### Scenario: `APP-CORE-002` — `isCanvasRoute` computed is the only gating mechanism

- GIVEN the canvas surface is bound to `<element :class="isCanvasRoute ? 'bg-canvas' : ''">` (or equivalent)
- WHEN PR0 lands
- THEN the binding mechanism is unchanged — only the `canvasRoutes` constant changes
- AND NO module-level override is introduced (the gating is AppLayout-local; per-module code does not duplicate the check)

#### Scenario: `APP-CORE-003` — Canvas surface renders on listed routes

- GIVEN the user navigates to any of the routes listed in `APP-CORE-001`
- WHEN the page renders at 1440×900
- THEN the page surface computes `background-color: rgb(242, 242, 247)` (the `var(--color-canvas)` value)
- AND cards on the same surface compute `background-color: rgb(255, 255, 255)` (the `systemBackground` value), so the canvas/surface contrast is visible

#### Scenario: `APP-CORE-004` — `canvasRoutes` extension is the ONLY functional change to AppLayout

- GIVEN PR0 is scoped to AppLayout + new StatusBadge primitive + new tests
- WHEN the PR0 diff is reviewed
- THEN the change to `AppLayout.vue` is limited to the `canvasRoutes` array literal (one-line additive) plus a comment that documents the list
- AND no other AppLayout refactor is bundled (no sidebar re-design, no topbar changes, no new event listeners)

### 2.2 `<UiStatusBadge>` primitive extraction

#### Scenario: `STATUS-PRIM-001` — New primitive at `resources/js/components/ui/StatusBadge.vue` with five variants

- GIVEN the duplication threshold (≥2 modules with status pills) is met: Pacientes, Profesionales, Ambientes, Tipos de cita, Caja, Presupuestos, ProcedureCatalog all render status pills with inline classes
- WHEN PR0 lands
- THEN a new primitive file is created at `resources/js/components/ui/StatusBadge.vue`
- AND the primitive accepts a `variant` prop with values: `success`, `warning`, `error`, `info`, `neutral`
- AND the primitive accepts a `label` prop (string or slot)
- AND the primitive accepts an optional `size` prop with values `sm`, `md` (default `md`)
- AND the primitive renders a `<span class="...">` (or `<UiBadge variant="...">` wrapping) with the tokenised ramps

#### Scenario: `STATUS-PRIM-002` — Internal tokenised ramps; no raw Tailwind variant classes for the variant ramps

- GIVEN the variant ramps are mapped to semantic system colors
- WHEN the primitive source is inspected
- THEN the rendered classes for each variant reference the tokenised ramps:
  - `success` → `bg-systemGreen-50 text-systemGreen-700`
  - `warning` → `bg-systemYellow-50 text-systemYellow-700`
  - `error` → `bg-systemRed-50 text-systemRed-700`
  - `info` → `bg-systemBlue-50 text-systemBlue-700`
  - `neutral` → `bg-systemGray-100 text-systemGray-700`
- AND the primitive uses `rounded-full` for the pill shape (the proven Apple treatment)
- AND no raw `bg-success-100 text-success-700` (or similar deprecated alias) classes appear in the primitive's source
- AND the `LegacyAliasForbiddenTest` regex set includes the `bg-success-100` style classes so the primitive's contract is enforced for the rest of the codebase

#### Scenario: `STATUS-PRIM-003` — Reduced-motion fallback + focus ring via `var(--focus-ring-default)`

- GIVEN the primitive is interactive (some modules render status badges as clickable filters)
- WHEN the primitive source is inspected
- THEN a focus ring is present on the focusable status pill via `box-shadow: var(--focus-ring-default)` on `:focus-visible`
- AND under `@media (prefers-reduced-motion: reduce)`, any transition collapses to opacity/colour change of ≤200ms
- AND the `PrimitivePressTest` is extended (or an equivalent sibling test) to verify the focus-ring composition

#### Scenario: `STATUS-PRIM-004` — `QuotationStatusBadge` becomes the first consumer in PR2

- GIVEN PR0 ships the primitive but no module consumes it yet
- WHEN PR2 (Quotations) lands
- THEN `resources/js/modules/quotations/components/QuotationStatusBadge.vue` imports `<UiStatusBadge>` and renders `<UiStatusBadge :variant="..." :label="..." />` — the existing local classes are NOT duplicated
- AND if `QuotationStatusBadge` holds no other logic, the file is REMOVED and every consumer (`QuotationCard`, `QuotationDetail`, `QuotationModal`) imports `<UiStatusBadge>` directly
- AND the `LegacyAliasForbiddenTest` catches this: the `bg-success-100 text-success-700` pattern that `QuotationStatusBadge` previously emitted is now banned — and so it must be migrated

### 2.3 `ModuleAppShellTestCase` base class

#### Scenario: `TEST-BASE-001` — Base class at `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` asserts the rule, not the literal

- GIVEN the vertical-slice archive-report lesson: "a test that pins an example instead of the rule" caused 3 defects
- WHEN PR0 lands
- THEN a new PHPUnit base class is created at `tests/Unit/DesignSystem/ModuleAppShellTestCase.php`
- AND the base class declares abstract methods or template rule methods that subclasses expand with the module's file path
- AND the base class assertions are rule-based (regex against the token REFERENCE, not the literal class string)

#### Scenario: `TEST-BASE-002` — The rule assertions the base class enforces

- GIVEN the base class is the rule-asserting foundation for per-module tests
- WHEN a subclass extends it with a module file path
- THEN the inherited rules assert:
  - **Canvas surface rule**: the module file (or its AppLayout binding) references `bg-canvas` OR `var(--color-canvas)` (either is acceptable; the rule is that the canvas token is referenced)
  - **Hairline rule**: key tables, forms, or card surfaces reference `--color-hairline` (the token reference, not the literal class string)
  - **Focus ring rule**: focusable elements reference `--focus-ring-default` (the composed token)
  - **Tabular-nums rule**: numeric columns / KPI numbers reference `tabular-nums` OR `font-feature-settings: var(--font-features-tabular-nums)`
  - **Status badge rule**: any status pill consumes `<UiStatusBadge>` (not bare `bg-success-100 text-success-700` etc.)
- AND the assertions are NOT literal-string pinning (per the archive-report lesson)

#### Scenario: `TEST-BASE-003` — No `<style scoped>` block remains in any polished module

- GIVEN the vertical-slice `DashboardAppShellTest` precedent forbids `<style scoped>` in the dashboard
- WHEN the base class is reviewed
- THEN a `test_no_style_scoped` rule is included asserting the module file does NOT contain `<style scoped>` (or, equivalently, the file's `<style>` tag is absent)
- AND this rule is the standing guard for the 6 files with existing `<style scoped>` blocks (TreatmentPlansPage, CashRegisterPage, AiAnalysisPage, AnalyzingModal, CreatePatientInline, TreatmentPlanModal) during their respective PRs

#### Scenario: `TEST-BASE-004` — Subclasses MAY add module-specific rules

- GIVEN the Caja module requires a `useCashRegister` contract preservation check
- WHEN PR0 lands
- THEN the base class is designed to be extensible (subclasses can override or augment the rule set)
- AND no Caja-specific rule ships in PR0 (the Caja-specific rule ships in PR9, when the Caja test is added)
- AND similarly for BI (Chart.js JS-side mapping rule ships in PR10, when the BI test is added)

### 2.4 `LegacyAliasForbiddenTest` (legacy alias pin)

#### Scenario: `LEGACY-LIST-001` — Pins the forbidden legacy alias list (initial set)

- GIVEN the explore audit enumerated the legacy alias classes used across the 17 modules
- WHEN PR0 lands
- THEN a new PHPUnit test is created at `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php`
- AND the test declares a `LEGACY_ALIASES` constant array containing the initial forbidden set (the canonical list from `proposal.md` §4.1 + `DLR-R-009`):
  - `border-theme`, `border-theme-light`
  - `bg-success-100`, `bg-success-500`, `bg-success-600`, `bg-success-700`, `text-success-700`
  - `bg-warning-100`, `text-warning-700`
  - `bg-error-100`, `text-error-700`, `bg-error-600`
  - `text-accent`, `bg-accent`, `hover:text-primary-700`, `bg-primary-50`, `bg-primary-100`, `bg-primary-200`
  - `focus:ring-primary-500`, `focus:border-accent`
  - `shadow-md` (when used on a card-like surface that should consume `var(--elevation-2)`), `shadow-lg`, `shadow-xl` (opaque)
  - raw `text-gray-*` (when used outside the canvas-hairline accent context)
- AND the test greps each polished module's directory for any of these patterns and asserts a count of zero
- AND the test runs against the polished module set as it grows (the test reads the module list from `AppLayout.canvasRoutes` or a hardcoded list, kept in sync)

#### Scenario: `LEGACY-LIST-002` — Extends opportunistically per module PR

- GIVEN a defect is observed during a module PR (a forgotten legacy alias class)
- WHEN the apply phase runs
- THEN the new pattern is added to `LEGACY_ALIASES` in the same PR (NOT a separate cleanup PR)
- AND the test is re-run to confirm the new pattern is now banned
- AND the test acts as a tripwire: any future module that re-introduces the pattern fails the test

### 2.5 `<UiPagination>` consolidation

#### Scenario: `PAGIN-CONS-001` — `<PaginationComponent>` is removed when the second module that touches pagination lands

- GIVEN `<UiPagination>` (6.6 KB, the `Ui*`-prefixed primitive) and `<PaginationComponent>` (6.2 KB, the duplicate) coexist
- WHEN PR3 (Recepción + Mis procedimientos + Catálogo) lands
- THEN every `<PaginationComponent>` import in the affected modules is replaced with `<UiPagination>` (the `Ui*`-prefixed primitive)
- AND `resources/js/components/ui/PaginationComponent.vue` is DELETED in this PR (no remaining importers)
- AND the `LegacyAliasForbiddenTest` is extended to catch any future re-import of the legacy primitive (assertion: zero matches for `PaginationComponent` outside the test file itself)

#### Scenario: `PAGIN-CONS-002` — No standalone consolidation PR

- GIVEN the consolidation is a side-effect of the second module that touches pagination
- WHEN the PR chain is reviewed
- THEN NO standalone `<UiPagination>` consolidation PR exists in the chain
- AND the work rides PR3 (Recepción procedimientos has the list-with-pagination pattern)

#### Scenario: `PAGIN-CONS-003` — `<UiPagination>` is the project convention

- GIVEN the `Ui*` prefix is the project's primitive convention per AGENTS.md §7
- WHEN the consolidation is reviewed
- THEN the surviving primitive is `<UiPagination>` (NOT `<PaginationComponent>`)
- AND the consolidation is not a name change — it is the removal of the duplicate and the standardisation on the `Ui*`-prefixed primitive

#### Scenario: `HOVER-LIFT-001` — `hover-lift` utility consolidation on `<UiCard clickable>`

- GIVEN `hover-lift` is defined in `resources/css/utilities.css` and is used in several modules (e.g. Profesionales, CashRegister)
- WHEN PR0 lands
- THEN the `hover-lift` utility is confirmed reachable (`rg "hover-lift" resources/css/utilities.css` returns matches)
- AND PR0 audits the existing `hover-lift` usage sites but does NOT migrate them
- AND per-module PRs migrate where `hover-lift` is a `<UiCard>` substitute: each migration replaces the inline `hover-lift` with `<UiCard clickable>` (the proven primitive handles hover + press + focus)
- AND `hover-lift` is kept for one-off decorative purposes only (rare; documented in the migration note)

### 2.6 `AppLayoutCanvasRoutesTest` invariant

#### Scenario: `CANVAS-ROUTE-001` — New invariant test asserting the route list

- GIVEN the load-bearing nature of the `canvasRoutes` extension
- WHEN PR0 lands
- THEN a new PHPUnit test is created at `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php`
- AND the test reads `AppLayout.vue` source and asserts the `canvasRoutes` array literal contains each of the routes listed in `APP-CORE-001`
- AND the test fails (RED) if any route is removed or if `canvasRoutes` is reduced to a hardcoded shorter list
- AND the test is the standing regression guard for the entire rollout: removing a route from `canvasRoutes` surfaces as a test failure

### 2.7 Per-module structure tests derived from `ModuleAppShellTestCase`

#### Scenario: `PER-MOD-001` — At least one per module, derived from `ModuleAppShellTestCase`

- GIVEN the rollout touches 17 modules + auxiliary screens
- WHEN PR0 lands
- THEN the `ModuleAppShellTestCase` base class is in place (the rule-asserting foundation)
- AND per-module structure tests (`<Module>AppShellTest`) are added WITH their respective module PRs — NOT prophylactically in PR0 (per the `proposal.md` §4.4 lesson: "Per-module structure test only when a NEW primitive or pattern is introduced")
- AND each subclass extends `ModuleAppShellTestCase` and overrides the module file path
- AND the per-module tests are the regression guards for the `DLR-CORE-*` rules in the sibling spec

---

## 3. Cross-cutting MUST/SHOULD rules (apply to Domain 2 and Domain 1)

The cross-cutting rules are documented in full in the sibling spec
`design-language-rollout/spec.md` §4. The rules relevant to DOMAIN 2 (PR0) are:

| Rule ID | Statement |
|---|---|
| `DLR-R-001` | `AppLayout.canvasRoutes` covers all polished module routes (asserted by `AppLayoutCanvasRoutesTest`) |
| `DLR-R-009` | Legacy alias classes are banned in any polished module (asserted by `LegacyAliasForbiddenTest`) |
| `DLR-R-013` | No new dependencies (Vue 3 + Tailwind 3.3 + Vue Router only) |
| `DLR-R-017` | Strict TDD: every replacement MUST come with a test |
| `DLR-R-018` | All `tests/Unit/DesignSystem/*` PHPUnit invariants remain green at every PR boundary |
| `DLR-R-019` | CI gates (quality, backend-tests MySQL, frontend-build) green at every PR boundary |
| `DLR-R-020` | No `var(--color-*)` dangling references (`GeneratedTokensCssTest` parity guard) |
| `DLR-R-021` | No `<style scoped>` blocks introduced (`ModuleAppShellTestCase::test_no_style_scoped` per module) |

PR0 introduces the standing PHPUnit tests that enforce these rules. The rules
themselves are not MODIFIED — they are introduced by this rollout and stand
alongside the existing `premium-design-foundation` rules.

---

## 4. Cross-cutting MUST/SHOULD rules (full list)

For the full cross-cutting rule set, see the sibling spec `design-language-rollout/spec.md` §4. Domain 2 introduces the rule-asserting PHPUnit infrastructure; the rules it enforces are documented in one place to avoid duplication.

---

## 5. Out of scope (mirrors the sibling spec)

For the full out-of-scope list, see the sibling spec `design-language-rollout/spec.md` §5. Specifically for Domain 2:

- **PR0 does NOT add new tokens** (`tokens.js` is frozen for this rollout)
- **PR0 does NOT introduce dark mode** (the foundation is light-only)
- **PR0 does NOT redesign the sidebar / topbar / PageHeader** (already shipped in PR5 of the vertical slice)
- **PR0 does NOT introduce a `<UiDataTable>` adoption rule** (the rollout touches each module's existing table, not a wholesale table rewrite)
- **PR0 does NOT bundle the BI Chart.js JS-side mapping** (that ships in PR10)
- **PR0 does NOT touch any module's `<script>` blocks** (UI changes are template-level only)

---

## 6. Test gate (PHPUnit + Playwright + lint/build)

### 6.1 Standing PHPUnit invariants (from the vertical slice)

These MUST remain green at PR0 merge:

- `tests/Unit/DesignSystem/TokensModuleTest.php`
- `tests/Unit/DesignSystem/GeneratedTokensCssTest.php`
- `tests/Unit/DesignSystem/PrimitivePressTest.php`
- `tests/Unit/DesignSystem/DashboardAppShellTest.php`
- `tests/Unit/DesignSystem/LoginPageRenderTest.php`
- `tests/Unit/DesignSystem/UseSpringMathTest.php`

### 6.2 NEW PHPUnit invariants introduced by PR0

- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` — pins the `canvasRoutes` array list (scenario `CANVAS-ROUTE-001`)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` — base class (scenarios `TEST-BASE-001..004`)
- `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` — pins the legacy alias list (scenarios `LEGACY-LIST-001..002`)
- Extended `tests/Unit/DesignSystem/PrimitivePressTest.php` — adds `<UiStatusBadge>` to the primitive set (scenario `STATUS-PRIM-003`)

### 6.3 Per-module structure tests (added with each PR cluster, NOT in PR0)

Per the proposal's lesson: per-module tests are added WITH their respective module PRs, not prophylactically. The first module test arrives with PR1 (ProcedureStats).

### 6.4 CI gates (untouched)

- `quality` (PHP syntax, JSON validation, Pint, ESLint, Prettier)
- `backend-tests` (MySQL 8.0 service, full PHPUnit suite)
- `frontend-build` (pnpm build)

### 6.5 Pre-PR visual gate for PR0

- No new module renders anything new on PR0 (the change is a route-list extension + a new primitive + tests)
- Existing visual verification: the dashboard / login / 404 visual screenshots from the vertical slice remain unchanged
- A new illustrative screenshot of `<UiStatusBadge>` variants rendered side-by-side at 1440×900 saved to `.playwright-cli/screenshots-rollout/ui-status-badge-variants-1440x900.png` (manual capture, not a CI gate)

---

## 7. Acceptance criteria (verifiable for PR0)

PR0 is considered complete when ALL of the following hold:

- [ ] `AppLayout.canvasRoutes` covers all 17 module routes + `/settings/branches` + `/settings/payment-methods` + `/procedure-stats` (≈20 routes total)
- [ ] `AppLayoutCanvasRoutesTest` is green
- [ ] `<UiStatusBadge>` primitive exists at `resources/js/components/ui/StatusBadge.vue` with `success / warning / error / info / neutral` variants
- [ ] `<UiStatusBadge>` consumes `var(--focus-ring-default)` (extends `PrimitivePressTest`)
- [ ] `ModuleAppShellTestCase` base class exists and is the rule-asserting foundation
- [ ] `LegacyAliasForbiddenTest` exists with the initial forbidden list and is green against the existing vertical-slice modules (Dashboard, Login, 404)
- [ ] No module's `<script>` block was touched by PR0
- [ ] No new tokens were added (only the `<UiStatusBadge>` primitive file + tests changed)
- [ ] All 6 existing `tests/Unit/DesignSystem/*` PHPUnit invariants remain green
- [ ] All CI jobs green at PR0 merge
- [ ] Tag the PR0 merge commit with `pr0-foundation-merge` for traceability

---

## 8. PR-to-spec traceability matrix (Domain 2 portion)

| PR | Scenarios satisfied | Notes |
|---|---|---|
| **PR0** | `APP-CORE-001..004`, `STATUS-PRIM-001..004`, `TEST-BASE-001..004`, `LEGACY-LIST-001..002`, `PAGIN-CONS-001..003` (audit only), `HOVER-LIFT-001` (audit only), `CANVAS-ROUTE-001`, `PER-MOD-001` (base class only) | The single PR for Domain 2. Required FIRST; all subsequent PRs depend on it. |
| **PR3** | `PAGIN-CONS-001` (second module touched — Recepción procedimientos has list pagination) | `<UiPagination>` consolidation rides PR3 |
| **PR9 (Caja)** | `TEST-BASE-004` (Caja-specific rule: `useCashRegister` contract preservation) | Caja subclass adds the no-`<script>`-change rule |
| **PR10 (BI)** | `TEST-BASE-004` (BI-specific rule: Chart.js JS-side mapping) | BI subclass adds the resolved-duration rule |

### 8.1 Inputs to `sdd-tasks`

The `sdd-tasks` phase will use this traceability matrix as the per-PR instruction set. PR0's `tasks/01-*.md` references the scenario IDs it satisfies, and the validation step at PR0 merge runs the relevant invariant tests.

---

## 9. References

### 9.1 Source artifacts

- `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §7.2 (PR0 forecast), §4.5 (strict TDD forward to apply/verify), §6 (OQ#5 StatusBadge extraction decision, OQ#6 hover-lift audit, OQ#7 pagination consolidation)
- `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` §3.2 (per-module visual state), §4.2 (primitive coverage), §4.3 (cross-cutting components)
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` D11 (reduced-motion fallback contract), D6 (focus-ring token composition)
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` process lesson (test pins example vs rule)
- `openspec/specs/premium-design-foundation/spec.md` — the foundation this domain extends
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/specs/premium-design-foundation/spec.md` — the persisted delta spec from the vertical slice
- `resources/js/components/layout/AppLayout.vue` line 507 — the load-bearing edit
- `resources/js/components/ui/{Card,Badge,Button,Input,Avatar,Modal,Sheet,ConfirmDialog,Toast,Select}.vue` — the 10 proven primitives this domain inherits
- `resources/js/components/ui/EmptyState.vue`, `LoadingSpinner.vue`, `Skeleton.vue` — the no-press decoration primitives
- `tests/Unit/DesignSystem/DashboardAppShellTest.php` — the proven PHPUnit pattern (per-Card hairline + elevation assertions)
- `tests/Unit/DesignSystem/PrimitivePressTest.php` — the proven focus-ring + transition assertions
- `resources/css/utilities.css` — `hover-lift` utility source
- `AGENTS.md` §7 — `Ui*` prefix convention for primitives
- `openspec/config.yaml` — preflight cache, strict TDD, hybrid artifact store

### 9.2 Process invariant (forwarded from the vertical-slice archive-report)

The archive-report at lines 47–57 names three defects that all shared one root cause: **a test that pins an example instead of the rule**. Domain 2's design is the opposite: `ModuleAppShellTestCase` is the rule-asserting base class. The base class's assertions are:
- Page surface references `bg-canvas` OR `var(--color-canvas)` (the rule: canvas token is referenced)
- Card surfaces reference `--color-hairline` (the rule: hairline token is referenced)
- Focus ring references `--focus-ring-default` (the rule: focus-ring token is referenced)
- Numeric columns use `tabular-nums` OR `font-feature-settings: var(--font-features-tabular-nums)` (the rule: tabular-nums is referenced)
- No `<style scoped>` block (the rule: `<style scoped>` tag is absent)

None of these pin a literal string. The rule is the contract; the literal is the implementation.

---

*End of Domain 2 spec.*
