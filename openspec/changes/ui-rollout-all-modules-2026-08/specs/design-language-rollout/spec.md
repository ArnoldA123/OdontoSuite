# Spec: Design Language Rollout (Domain 1) — `ui-rollout-all-modules-2026-08`

> **Delta type**: NEW capability spec. The existing `premium-design-foundation`
> capability (persisted at `openspec/specs/premium-design-foundation/spec.md`) is
> the stable foundation this rollout CONSUMES; this spec describes the new
> ADDED requirements that extend the language to all 17 modules.
>
> **Naming convention**: follows the archive convention from
> `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/` (one
> spec per domain under `specs/<domain>/spec.md`). The sibling file
> `specs/foundation-primitives/spec.md` covers Domain 2 (PR0 prerequisite work).

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Date | 2026-08-11 |
| SDD phase | `spec` (3 of 6) |
| Author | `sdd-spec` sub-agent |
| Domain | 1 — Design Language Rollout |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/spec`) |
| Pace | `auto` |
| Delivery strategy | `auto-chain` (forward to `sdd-tasks` for chain realisation) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` (forward to apply/verify) |
| Vertical slice (baseline) | `ui-premium-microdetail-2026-08` (closed 2026-08-11, 5 stacked PRs, PASS WITH WARNINGS) |
| Known-bad alternative | `ui-redesign-apple-claude-2026-08` (stalled, cream + terracotta + Newsreader). **DO NOT extend.** |
| Sibling spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` (Domain 2, PR0) |
| Upstream artifacts | `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (596 lines) |
|  | `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (496 lines) |
|  | `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/{proposal,design,tasks,archive-report}.md` |
|  | `openspec/specs/premium-design-foundation/spec.md` (404 lines, the capability being applied) |
|  | `openspec/specs/dashboard-period-comparisons/spec.md` (162 lines, sibling capability) |
|  | `resources/js/components/layout/AppLayout.vue` line 507 (`canvasRoutes`) |
|  | `resources/js/design-system/tokens.js` (the source of truth) |
|  | `AGENTS.md` §2, §4, §5, §6, §7 |
|  | `openspec/config.yaml` (strict_tdd, artifact_store, per-phase rules) |

### Preflight snapshot (verbatim from session preflight)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain      # chained PRs auto-activate; do NOT re-ask
review_budget_lines: 400
chain_strategy: not_cached         # recommend stacked-to-main at sdd-tasks time
strict_tdd: true
```

### Naming rationale

The archive convention (`openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/`) uses `specs/<domain>/spec.md` for delta specs. The proposal identifies two clearly separable domains (Design Language Rollout and Foundation Cross-Cutting Primitives). This split matches the vertical-slice archive pattern and is reviewable in slices. The orchestrator prompt's "specs.md (plural)" default is overridden by the archive convention, which is the precedent the prompt explicitly names.

---

## 1. Capability index

This domain covers the per-module design language rollout. The shared, cross-cutting rules and PR0 foundation work are in the sibling spec `foundation-primitives/spec.md`.

| Scenario ID | Domain | Module | RFC keyword | One-line summary |
|---|---|---|---|---|
| `DLR-CORE-001` | Cross-cutting | All | MUST | Canvas/surface separation; route-aware canvas surface renders `var(--color-canvas)` on every polished module route |
| `DLR-CORE-002` | Cross-cutting | All | MUST | Card-like surfaces consume `var(--color-hairline)` border + `var(--elevation-2)` (or higher) shadow |
| `DLR-CORE-003` | Cross-cutting | All | MUST | Numeric columns / KPI / counter numbers use `font-feature-settings: var(--font-features-tabular-nums)` (Tailwind `tabular-nums`) |
| `DLR-CORE-004` | Cross-cutting | All | MUST | Focus ring on focusable elements is `box-shadow: var(--focus-ring-default)` (composed) |
| `DLR-CORE-005` | Cross-cutting | All | MUST | All transitions use `var(--motion-duration-fast) var(--motion-easing-ios)` (transforms) or `ease-out` (colour washes) |
| `DLR-CORE-006` | Cross-cutting | All | MUST | Status pills consume `<UiStatusBadge>` (the primitive shipped in Domain 2 / PR0) |
| `DLR-CORE-007` | Cross-cutting | All | MUST | Empty state uses `<UiEmptyState>`, loading uses `<UiLoadingSpinner>` or `<UiSkeleton>` — no bespoke `@keyframes pulse` / `@keyframes spin` in scoped `<style>` |
| `DLR-CORE-008` | Cross-cutting | All | MUST | No new `<style scoped>` blocks; no grandfather clause for the 6 files that already have them |
| `DLR-CORE-009` | Cross-cutting | All | MUST | Legacy alias classes (`border-theme`, `bg-success-100`, `text-accent`, `focus:ring-primary-500 focus:border-accent`, opaque `shadow-*` Tailwind, raw `text-gray-*`) are banned in polished modules |
| `DLR-CORE-010` | Cross-cutting | All | MUST | `<script>` blocks are NOT touched in any rollout PR — UI changes are template-level class-string replacement only |
| `DLR-CORE-011` | Cross-cutting | All | MUST | Six modules with existing `<style scoped>` blocks (TreatmentPlansPage, CashRegisterPage, AiAnalysisPage, AnalyzingModal, CreatePatientInline, TreatmentPlanModal) are rewritten to plain utility classes during their respective PRs |
| `DLR-CORE-012` | Cross-cutting | All | MUST | Visual verification per module: playwright-cli screenshot at 1440×900 saved to `.playwright-cli/screenshots-rollout/<module>-1440x900.png`; mobile capture at 390×844 for Calendario, Pacientes, Caja |
| `DLR-CORE-013` | Cross-cutting | All | SHOULD | AAA contrast where a token already meets it; AA (4.5:1 body, 3:1 large) as the practical bar |
| `DLR-CORE-014` | Cross-cutting | All | MUST | Reduced-motion fallback: under `prefers-reduced-motion: reduce`, transform-based press/hover collapses to opacity/colour change of ≤200ms |
| `DLR-CORE-015` | Cross-cutting | All | MUST | `prefers-reduced-transparency` solidifies glass; `prefers-contrast: more` does not regress |
| `DLR-MOD-001` | Module 1 — Dashboard | Dashboard | — | (Polish complete in vertical slice; out of scope for this rollout) |
| `DLR-MOD-002` | Module 2 — Calendario | Calendario | MUST | Surrounding chrome only tokenised; `.fc-event` / `.fc-daygrid` / `.fc-timegrid` NOT overridden |
| `DLR-MOD-003` | Module 3 — Pacientes | Pacientes | MUST | List + detail + mobile card fallback tokenised; `hover-lift` swapped for `<UiCard clickable>` |
| `DLR-MOD-004..006` | Modules 4–6 | Profesionales, Ambientes, Tipos de cita | MUST | Admin CRUD triplet tokenised; list + detail + form modal |
| `DLR-MOD-007` | Module 7 — Caja | Caja | MUST | Template-only class-string replacement; `<script>` blocks + `useCashRegister` verbatim; rewritten `<style scoped>` in CashRegisterPage |
| `DLR-MOD-008` | Module 8 — BI | BI | MUST | Chart.js `options.animation.duration` resolved from `tokens.motion.duration` (one-time init; no CSS pass-through) |
| `DLR-MOD-009` | Module 9 — Planes | TreatmentPlans | MUST | Rewrite `<style scoped>` block to plain utility classes; consolidate inline `@keyframes pulse` |
| `DLR-MOD-010` | Module 10 — Presupuestos | Quotations | MUST | `QuotationStatusBadge` migrated to `<UiStatusBadge>` (first consumer) |
| `DLR-MOD-011..012` | Modules 11–12 | MedicalRecords, SpecialtyRecords | MUST | Clinical modules tokenised; inline `@keyframes` consolidated (if present) |
| `DLR-MOD-013` | Module 13 — AI Analysis | AI Analysis | MUST | Rewrite `<style scoped>` blocks; consolidate `@keyframes spin` on `<UiLoadingSpinner>` |
| `DLR-MOD-014` | Module 14 — Catálogo | Catalog | MUST | CRUD + import modal + CSV upload tokenised |
| `DLR-MOD-015..016` | Modules 15–16 | MyProcedures, Recepción | MUST | Smallest modules tokenised; `<UiPagination>` consolidation rides PR3 |
| `DLR-MOD-017` | Module 17 — Estadísticas | ProcedureStats | MUST | Three KPI cards + table tokenised; first full consumer of the proven KPI anatomy on a non-Dashboard module |
| `DLR-MOD-018` | Module 11–12 — Treatment plans | CreatePatientInline, TreatmentPlanModal | MUST | Rewrite `<style scoped>` blocks; consolidate inline `@keyframes` |

> **Row count check**: 17 modules + 15 cross-cutting rules = 32 scenarios. Plus the module-specific edge cases (Caja Reverb, BI Chart.js, Calendario FullCalendar, Catalog CSV upload, Quotations StatusBadge extraction) all rolled into the module rows above. The 17 module scenarios share a common template captured under `DLR-MOD-TEMPLATE` (see §2.1).

---

## 2. Domain 1 — Design Language Rollout

### 2.1 Per-module template (the shared contract)

Every polished module MUST satisfy the following contract. The per-module scenarios in §2.2 enumerate this template explicitly for each module; the template below is the canonical rule.

#### Scenario: `DLR-MOD-TEMPLATE` — every polished module renders on canvas with hairline borders, tabular-nums on numeric columns, tokenised primitives, and no legacy alias classes

- GIVEN any polished module route listed in `AppLayout.canvasRoutes`
- WHEN the page renders at 1440×900 after the rollout PR lands
- THEN the page surface computes `background-color: rgb(242, 242, 247)` (the `var(--color-canvas)` value)
- AND every card-like surface consumes `border: 1px solid var(--color-hairline)` (NOT `border-theme`)
- AND every KPI card / counter / currency column uses `font-feature-settings: var(--font-features-tabular-nums)` (Tailwind `tabular-nums`)
- AND every focusable element consumes `box-shadow: var(--focus-ring-default)` on `:focus-visible` (NOT `focus:ring-primary-500 focus:border-accent`)
- AND every status pill consumes `<UiStatusBadge>` (the primitive from Domain 2 / PR0) with a `variant` prop matching the semantic role
- AND every empty / loading / error state consumes `<UiEmptyState>` / `<UiLoadingSpinner>` / `<UiSkeleton>` (no bespoke `@keyframes pulse` / `@keyframes spin` in scoped `<style>`)
- AND the module file contains zero `<style scoped>` blocks (Rule DLR-CORE-008)
- AND the module file contains zero of the legacy alias classes listed in `tests/Unit/DesignSystem/LegacyAliasForbiddenTest::LEGACY_ALIASES` (Rule DLR-CORE-009)

### 2.2 Per-module rollout scenarios

For each module below, the "page surface" requirement is identical to the template; the module-specific rows call out deviations, edge cases, or third-party boundaries.

#### Module 1 — Dashboard

`DLR-MOD-001` — Dashboard is already polished by the vertical slice (PR4 + PR5 of `ui-premium-microdetail-2026-08`). This rollout's PR chain does NOT touch the dashboard. The standing invariant `tests/Unit/DesignSystem/DashboardAppShellTest.php` MUST remain green at every PR boundary.

#### Scenario: `DLR-MOD-001` — Dashboard is excluded from the rollout scope

- GIVEN the vertical slice shipped the dashboard on 2026-08-11
- WHEN any PR of this rollout's chain is reviewed
- THEN `git diff -- openspec/changes/ui-rollout-all-modules-2026-08/` shows zero changes to `resources/js/modules/dashboard/DashboardPage.vue`
- AND `DashboardAppShellTest` is green

#### Module 2 — Calendario

##### Scenario: `DLR-MOD-002` — Calendario renders on canvas with tokenised surrounding chrome, but FullCalendar internals are NOT overridden

- GIVEN the user navigates to `/calendar` as a clinical role
- WHEN the page renders at 1440×900
- THEN the page surface computes `background-color: rgb(242, 242, 247)` (the canvas)
- AND the surrounding chrome (header, controls, status pills, ConsultationWizard modal) is tokenised per the template
- AND the FullCalendar internals (`.fc-event`, `.fc-daygrid`, `.fc-timegrid`, `.fc-toolbar`) are NOT overridden by any rule in `CalendarPage.vue`'s `<style>` section
- AND the "En vivo" status pulse uses `animate-pulse` Tailwind utility (or `animate-pulse-subtle` if defined), not a bespoke `@keyframes pulse`
- AND playwright-cli snapshot at 1440×900 plus 390×844 (mobile responsive) saved to `.playwright-cli/screenshots-rollout/calendario-{1440x900,390x844}.png`

#### Module 3 — Pacientes

##### Scenario: `DLR-MOD-003` — Pacientes list + detail + mobile card fallback tokenised; `hover-lift` consolidates on `<UiCard clickable>`

- GIVEN the user navigates to `/patients` as any role
- WHEN the page renders at 1440×900
- THEN the page surface is canvas (`var(--color-canvas)`)
- AND each mobile card uses `<UiCard clickable>` with tokenised hover (no inline `transition-all duration-200 hover:shadow-lg`)
- AND the active/inactive status pills render via `<UiStatusBadge variant="success">` / `<UiStatusBadge variant="error">`
- AND the currency table columns (insurance balance) use `tabular-nums`
- AND the numeric counters (patient count, insurance balance) use `tabular-nums`
- AND `PatientDetailPage.vue` carries the same contract
- AND playwright-cli snapshot at 1440×900 plus 390×844 saved to `.playwright-cli/screenshots-rollout/pacientes-{1440x900,390x844}.png`

#### Modules 4–6 — Profesionales, Ambientes, Tipos de cita (admin CRUD triplet)

##### Scenario: `DLR-MOD-004..006` — Admin CRUD triplet tokenised end-to-end

- GIVEN the user navigates to `/professionals`, `/environments`, or `/appointment-types` as `administrador`
- WHEN each page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND the list + detail + form-modal pattern is shared across all three modules
- AND the form modal's inputs use `border-[color:var(--color-hairline)]` + `<UiInput>` focus ring (the proven token), NOT `border-theme rounded-md focus:ring-primary-500 focus:border-accent`
- AND the toggle-pill status (active / inactive / maintenance) uses `<UiStatusBadge variant="success | error | warning">`
- AND one playwright-cli snapshot per module at 1440×900 saved to `.playwright-cli/screenshots-rollout/{profesionales,ambientes,tipos-de-cita}-1440x900.png`

#### Module 7 — Caja

##### Scenario: `DLR-MOD-007` — Caja template-only class-string replacement; `<script>` blocks and `useCashRegister` contract preserved verbatim

- GIVEN the user navigates to `/cash-register` or `/cash-register/ready-to-bill` as admin / finanzas / recep
- WHEN the page renders at 1440×900 after the rollout PR lands
- THEN the page surface is canvas and the template contract applies
- AND the legacy `<style scoped>` block in `CashRegisterPage.vue` is REMOVED and rewritten to plain utility classes (or extracted to a primitive)
- AND the `<script>` block of `CashRegisterPage.vue`, `ReadyToBillPage.vue`, and any of the 9 components is byte-for-byte unchanged (no event listener additions, no `useCashRegister` modifications, no debounce adjustments)
- AND `useCashRegister` contract (debounce / cleanup / channel `cash-register` subscription) is preserved
- AND the MercadoPago `PaymentModal.vue` template is tokenised; its `<script>` block is untouched
- AND the "Tipo de comprobante" status pills render via `<UiStatusBadge variant="info | success | warning | error">`
- AND no Reverb regression surfaces: the cashier role's real-time updates continue to fire on `payment.created` / `payment.updated`
- AND apply phase tags the Caja merge commit with `cash-register-revert-rationale` so the rollback path is documented
- AND playwright-cli snapshot at 1440×900 plus 390×844 (cashier mobile path) saved to `.playwright-cli/screenshots-rollout/caja-{1440x900,390x844}.png`

#### Module 8 — BI (Business Intelligence)

##### Scenario: `DLR-MOD-008` — BI Chart.js JS-side `motion.duration` resolution; no CSS pass-through

- GIVEN the user navigates to `/business-intelligence` as admin / finanzas
- WHEN the page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND each `new Chart(...)` instance receives `options.animation.duration` as a JS number — NOT a `var(--motion-duration-*)` string
- AND the JS numbers are resolved once at module init from `tokens.motion.duration` (parse `parseInt('120ms')` → `120`, `parseInt('200ms')` → `200`, `parseInt('320ms')` → `320`), and the resolved values are stored in a module-scope constant
- AND the existing literal numbers in `options.animation.duration: <number>` are replaced with references to the resolved constants (e.g. `chartAnimMs.fast`)
- AND the existing `<style scoped>` block in `BusinessIntelligencePage.vue` is REMOVED and rewritten to plain utility classes
- AND the "Filter" pill on the page header uses `<UiStatusBadge variant="info">`
- AND playwright-cli snapshot at 1440×900 saved to `.playwright-cli/screenshots-rollout/bi-1440x900.png`

#### Module 9 — Planes de Tratamiento

##### Scenario: `DLR-MOD-009` — TreatmentPlans `<style scoped>` block rewritten; inline `@keyframes pulse` consolidated on `LoadingSpinner`

- GIVEN the user navigates to `/treatment-plans` as a clinical role
- WHEN the page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND the `<style scoped>` block in `TreatmentPlansPage.vue` (lines 427–end per the explore audit) is REMOVED
- AND the `@apply` directives inside the removed block are rewritten to plain utility classes (Tailwind classes) OR migrated to a primitive
- AND the inline `@keyframes pulse` inside the removed block is replaced with the `animate-pulse` Tailwind utility OR `<UiLoadingSpinner>` (whichever fits the visual)
- AND the inline `@keyframes spin` in `TreatmentPlanModal.vue` (lines 805–807) is replaced with `<UiLoadingSpinner>` or `animate-spin`
- AND the `PlanStatusBadge.vue` component consumes `<UiStatusBadge>` internally
- AND playwright-cli snapshot at 1440×900 saved to `.playwright-cli/screenshots-rollout/planes-1440x900.png`

#### Module 10 — Presupuestos (Quotations)

##### Scenario: `DLR-MOD-010` — Quotations is the first consumer of `<UiStatusBadge>`; `QuotationStatusBadge` becomes a thin wrapper

- GIVEN the user navigates to `/quotations` as admin / finanzas / odonto / implant
- WHEN the page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND `QuotationStatusBadge.vue` imports `<UiStatusBadge>` and renders `<UiStatusBadge :variant="..." :label="..." />` — the existing local classes are NOT duplicated
- AND the `QuotationStatusBadge` file MAY be removed entirely if it holds no other logic; if removed, every consumer (`QuotationCard`, `QuotationDetail`, `QuotationModal`) imports `<UiStatusBadge>` directly
- AND the currency columns (price, total, balance) use `tabular-nums`
- AND playwright-cli snapshot at 1440×900 saved to `.playwright-cli/screenshots-rollout/presupuestos-1440x900.png`

#### Modules 11–12 — Historias clínicas, Registros especialidad

##### Scenario: `DLR-MOD-011..012` — Clinical modules tokenised; no new `<style scoped>` introduced

- GIVEN the user navigates to `/medical-records` or `/specialty-records` as a clinical role
- WHEN each page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND the components under `medical-records/components/` and `specialty-records/components/` consume `<UiCard>`, `<UiModal>`, `<UiStatusBadge>` (not inline tokenised status pills)
- AND `MedicalRecordStats.vue` numeric counters use `tabular-nums`
- AND no `<style scoped>` block is introduced in any new component
- AND one playwright-cli snapshot per module at 1440×900 saved to `.playwright-cli/screenshots-rollout/{historias-clinicas,registros-especialidad}-1440x900.png`

#### Module 13 — Análisis IA

##### Scenario: `DLR-MOD-013` — AI Analysis `<style scoped>` blocks rewritten; `@keyframes spin 3s linear infinite` consolidated on `<UiLoadingSpinner variant="ai">` (or `animate-spin`)

- GIVEN the user navigates to `/ai-analysis` as a clinical role
- WHEN the page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND the `<style scoped>` block in `AiAnalysisPage.vue` (line 605 area) is REMOVED
- AND the `<style scoped>` block in `AnalyzingModal.vue` (lines 54–67 area) is REMOVED
- AND the inline `@keyframes spin 3s linear infinite` in `AnalyzingModal.vue` is replaced with `<UiLoadingSpinner>` (or the `animate-spin` Tailwind utility if the 3-second cadence is intentional — captured as a separate sub-scenario)
- AND the status maps (`statusClasses`, `severityClasses`, `confidenceClasses`) consolidate on `<UiStatusBadge>` variant props
- AND `AiAnalysisModal.vue`'s `@apply` directives are rewritten to plain utility classes
- AND playwright-cli snapshot at 1440×900 saved to `.playwright-cli/screenshots-rollout/analisis-ia-1440x900.png`

#### Module 14 — Catálogo de procedimientos

##### Scenario: `DLR-MOD-014` — Catalog CRUD + import modal + CSV upload tokenised

- GIVEN the user navigates to `/procedure-catalog` or `/procedure-catalog/:id` as admin
- WHEN each page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND `ProcedureCatalogFormModal.vue` (the import modal + CSV upload) is tokenised: `<UiModal>` chrome, `<UiFileUpload>` for the CSV
- AND the favorite toggle (ProcedureFavorites) uses `<UiStatusBadge>` if it renders a status pill, otherwise the existing star icon stays
- AND the price column uses `tabular-nums`
- AND the `<style scoped>` block in `ProcedureCatalogPage.vue` (if any) is REMOVED
- AND playwright-cli snapshot at 1440×900 saved to `.playwright-cli/screenshots-rollout/catalogo-1440x900.png`

#### Modules 15–16 — Mis procedimientos, Recepción procedimientos (smallest modules)

##### Scenario: `DLR-MOD-015..016` — Smallest modules tokenised; `<UiPagination>` consolidation rides this PR

- GIVEN the user navigates to `/my-procedures` (clinical) or `/reception-procedures` (recep)
- WHEN each page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND the price / cost columns use `tabular-nums`
- AND the filters card uses the proven `border-[color:var(--color-hairline)]` + `<UiInput>` focus ring (NOT `focus:ring-primary-500 focus:border-accent`)
- AND the pagination control imports `<UiPagination>` (the `Ui*`-prefixed primitive) — the legacy `<PaginationComponent>` import is REMOVED
- AND `resources/js/components/ui/PaginationComponent.vue` is DELETED in this PR (no remaining importers)
- AND one playwright-cli snapshot per module at 1440×900 saved to `.playwright-cli/screenshots-rollout/{mis-procedimientos,recepcion-procedimientos}-1440x900.png`

#### Module 17 — Estadísticas catálogo (ProcedureStats)

##### Scenario: `DLR-MOD-017` — ProcedureStats is the second proving ground; reuses Dashboard KPI anatomy on a 3rd screen

- GIVEN the user navigates to `/procedure-catalog/stats` (or whatever the mounted route is) as admin / finanzas
- WHEN the page renders at 1440×900
- THEN the page surface is canvas and the template contract applies
- AND the three KPI cards reuse the Dashboard's fixed-slot anatomy (`grid-template-rows: 16px 48px 24px 16px`) with `tabular-nums` on the numbers
- AND the raw `text-green-600` Tailwind utility is replaced with `text-systemGreen-600` (the tokenised equivalent)
- AND the table numerics use `tabular-nums`
- AND playwright-cli snapshot at 1440×900 saved to `.playwright-cli/screenshots-rollout/estadisticas-1440x900.png`

#### Module 11–12 — CreatePatientInline + TreatmentPlanModal (Treatment Plans cluster)

##### Scenario: `DLR-MOD-018` — CreatePatientInline and TreatmentPlanModal `<style scoped>` blocks rewritten

- GIVEN the rollout PR for TreatmentPlans lands
- WHEN the rewrites for `CreatePatientInline.vue` and `TreatmentPlanModal.vue` are reviewed
- THEN both files have NO `<style scoped>` block (asserted via `tests/Unit/DesignSystem/ModuleAppShellTestCase` `test_no_style_scoped`)
- AND the inline `@keyframes pulse` (line 261–263 of `CreatePatientInline.vue`) is replaced with `animate-pulse` Tailwind utility
- AND the inline `@keyframes spin` (line 805–807 of `TreatmentPlanModal.vue`) is replaced with `<UiLoadingSpinner>` or `animate-spin`
- AND the `@apply` directives in `AiAnalysisModal.vue` (line 274 onwards) are rewritten to plain utility classes

---

## 3. Domain 2 — Foundation Cross-Cutting Primitives

This domain's scenarios live in the sibling spec file
`openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md`.
Section 3 of THIS file is intentionally short — it points to the sibling spec.

### Scenario: `DLR-FOUND-POINTER` — Domain 2 (PR0) is defined in the sibling spec

- GIVEN the rollout chain depends on PR0 landing first
- WHEN the spec is read
- THEN the `<UiStatusBadge>` primitive, `AppLayout.canvasRoutes` extension, `ModuleAppShellTestCase` base class, and `LegacyAliasForbiddenTest` are covered in `specs/foundation-primitives/spec.md` §2
- AND the PR0 traceability is in `specs/foundation-primitives/spec.md` §8

---

## 4. Cross-cutting MUST/SHOULD rules (apply to all modules)

These rules are normative across every polished module. The token / primitive layer they reference is the existing `premium-design-foundation` capability (persisted at `openspec/specs/premium-design-foundation/spec.md`); this rollout does NOT modify that foundation but requires its contracts to be honored.

| Rule ID | RFC keyword | Statement |
|---|---|---|
| `DLR-R-001` | MUST | Every polished module route renders on `var(--color-canvas)` (`#F2F2F7`) via `AppLayout.canvasRoutes`. The route list is asserted by `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php`. |
| `DLR-R-002` | MUST | Card-like surfaces consume `border: 1px solid var(--color-hairline)` (the `rgba(60, 60, 67, 0.12)` value) — NOT the legacy `border-theme` (the opaque `#C6C6C8`). |
| `DLR-R-003` | MUST | Numeric columns, KPI numbers, currency counters, and any clinical figure use `font-feature-settings: var(--font-features-tabular-nums)` (the `"tnum" 1, "lnum" 1` value) via the `tabular-nums` Tailwind utility. |
| `DLR-R-004` | MUST | Focus ring on every focusable element is `box-shadow: var(--focus-ring-default)` (the composed `0 0 0 3px rgba(0, 122, 255, 0.20)` value). The legacy `focus:ring-primary-500 focus:border-accent` pattern is banned. |
| `DLR-R-005` | MUST | Easing for transform-based transitions is `var(--motion-duration-fast) var(--motion-easing-ios)` (the `cubic-bezier(0.25, 0.46, 0.45, 0.94)` curve). Colour washes keep `ease-out`. |
| `DLR-R-006` | MUST | Status pills consume `<UiStatusBadge variant="success \| warning \| error \| info \| neutral" :label="...">` (the primitive from Domain 2 / PR0). Inline `bg-success-100 text-success-700` etc. pills are banned. |
| `DLR-R-007` | MUST | Empty list states use `<UiEmptyState>`, loading states use `<UiLoadingSpinner>` or `<UiSkeleton>`. The vertical slice's `DashboardAppShellTest` precedent forbids bespoke `@keyframes pulse` / `@keyframes spin` in `<style scoped>` blocks. |
| `DLR-R-008` | MUST | No new `<style scoped>` blocks are introduced in any polished module. The 6 files that already have them (TreatmentPlansPage, CashRegisterPage, AiAnalysisPage, AnalyzingModal, CreatePatientInline, TreatmentPlanModal) are rewritten to plain utility classes during their respective PRs. No grandfather clause. |
| `DLR-R-009` | MUST | The legacy alias classes listed in `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` are FORBIDDEN in any polished module. The full forbidden list (initialised by PR0, extended opportunistically) MUST include at minimum: `border-theme`, `border-theme-light`, `bg-success-100`, `bg-success-500`, `bg-success-600`, `bg-success-700`, `text-success-700`, `bg-warning-100`, `text-warning-700`, `bg-error-100`, `text-error-700`, `bg-error-600`, `text-accent`, `bg-accent`, `hover:text-primary-700`, `bg-primary-50`, `bg-primary-100`, `bg-primary-200`, `focus:ring-primary-500`, `focus:border-accent`, `bg-theme-surface-elevated` (when used on the page surface, since `systemBackground` is the surface token, not the canvas). |
| `DLR-R-010` | MUST | `<script>` blocks of every module page + component are NOT edited in any rollout PR. UI changes are template-level class-string replacement only. This is the load-bearing rule for the Caja, Calendario, and BI isolation contracts. |
| `DLR-R-011` | MUST | Reduced-motion fallback: under `@media (prefers-reduced-motion: reduce)`, transform-based press/hover on polished modules collapses to an opacity or colour change of at most 200ms. The contract is inherited from the foundation primitives (`D11` of the vertical slice). |
| `DLR-R-012` | SHOULD | AAA contrast where a token already meets it; AA (4.5:1 body, 3:1 large) as the practical bar. The palette is light-only; no `prefers-color-scheme: dark` block is introduced. |
| `DLR-R-013` | MUST | Pinia / Vue 3 / Tailwind 3.3 / Vue Router only. No new animation dependencies (no motion-v, no @vueuse/motion, no GSAP). |
| `DLR-R-014` | MUST | Tab order matches visual order; `Tab` enters, `Shift+Tab` reverses; focus is always visible (the proven focus ring is the mechanism). |
| `DLR-R-015` | MUST | Role-restricted modules render a `<RoleBanner>` (or equivalent explanatory block) at the top of the module surface naming the role(s) the module is restricted to. Triggered when the route has `middleware('role:...')` in `routes/api.php`. |
| `DLR-R-016` | MUST | Visual verification per module: playwright-cli snapshot at 1440×900 saved to `.playwright-cli/screenshots-rollout/<module>-1440x900.png`. Mobile 390×844 capture required for Calendario, Pacientes, Caja only. |
| `DLR-R-017` | MUST | Strict TDD: every UI replacement MUST come with a test that proves the new behaviour (PHPUnit RED-GREEN discipline). The visual sweep is a documented verification surface, not a CI gate. |
| `DLR-R-018` | MUST | All `tests/Unit/DesignSystem/*` PHPUnit invariants remain green at every PR boundary. The new basis is `ModuleAppShellTestCase` (the rule-asserting base class from Domain 2 / PR0). |
| `DLR-R-019` | MUST | CI gates (untouched): `quality` (PHP syntax, JSON validation, Pint, ESLint, Prettier), `backend-tests` (MySQL 8.0 service), `frontend-build` (pnpm build). |
| `DLR-R-020` | MUST NOT | `var(--color-*)` references appear in module files unless the custom property is emitted by `scripts/build-tokens-css.mjs` (the existing `GeneratedTokensCssTest::generated_css_only_contains_token_hex_literals` parity guard is the standing witness). |
| `DLR-R-021` | MUST NOT | `<style scoped>` blocks are reintroduced after the rollout completes. Standing guard: `tests/Unit/DesignSystem/ModuleAppShellTestCase::test_no_style_scoped` per module. |
| `DLR-R-022` | MUST NOT | FullCalendar internals (`.fc-event`, `.fc-daygrid`, `.fc-timegrid`, `.fc-toolbar`) are overridden by module CSS. The rollout's chrome-only scope is load-bearing. |

---

## 5. Out of scope (recorded decisions)

This section mirrors `proposal.md` §3 + the OQ resolutions in `proposal.md` §6. Each item is excluded from this rollout and explicitly recorded so the apply phase does NOT silently resolve it.

| Item | Decision | Reason |
|---|---|---|
| Dark mode | OUT | The proven language is light-only per `tokens.js` line 29; parallel capability with its own palette-parity and `prefers-color-scheme` switch |
| WCAG 2.1 AA formal audit per module (axe-core sweep, label-association, focus-trap audit) | OUT (AAA opportunistic) | Per-form audits are a separate change; the proven palette already meets AA for body text |
| Settings / Branches / Payment Methods | OUT by default (PR11 conditional) | AGENTS.md §5 lists 17 modules; settings are multi-tenant infrastructure, not in the user's module list |
| Two-tone numerals (`text-numeric-fade`) | OUT (D12 REVERSIBLE stays rejected) | Clinical datum; fading trailing digits degrades legibility for zero comprehension gain |
| Per-KPI sparklines | OUT | Requires new backend endpoint + cache strategy; deferred to its own change |
| New tokens / new primitives EXCEPT `<UiStatusBadge>` | OUT | `tokens.js` is frozen for this rollout; the only addition is the StatusBadge primitive |
| Sidebar / topbar / PageHeader re-design | OUT | Already shipped in PR5 of the vertical slice |
| `MobileNavigation.vue` + `ThemeSelector.vue` removal | OUT | Dead code per the abandoned change's audit; removal is a separate cleanup |
| `<UiPagination>` consolidation standalone PR | OUT | Consolidation rides the second module that touches pagination (PR3 — Recepción procedimientos) |
| Cosmetic clip on the Dashboard comparator label | OUT (vertical-slice open item #3) | Same fix would now apply to a status label, not the Citas Hoy card |
| Settings module internals (BranchesPage, PaymentMethodsPage) | OUT | AppLayout.canvasRoutes extension in PR0 INCLUDES them so they pick up the canvas surface, but their internal tokenisation is deferred (user may opt in during spec review) |
| `<style scoped>` grandfather clause for the 6 files that already have them | OUT (NO grandfather) | Painful now, clean later; per OQ#9 |
| Sphere of `<UiDataTable>` adoption | OUT | `UiDataTable` exists but is not widely used; rollout touches only what each module already has, not a wholesale table rewrite |
| Sharing of `useCashRegister` / `useTreatmentPlans` / `useQuotations` composable changes | OUT | Composables are out of scope; `<script>` blocks are NOT edited |

---

## 6. Test gate (PHPUnit + Playwright + lint/build)

### 6.1 Standing PHPUnit invariants (must remain green at every PR boundary)

These exist from the vertical slice and are the rollout's regression gate:

| Test file | Asserts |
|---|---|
| `tests/Unit/DesignSystem/TokensModuleTest.php` | Token source invariants: `radius.cardLg`, `radius.control`, `motion.duration` (3 keys), `focusRing`, `fontFeatures.tabularNums`, `elevation` (5 rungs) |
| `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` | Generated CSS contains `--color-canvas`, `--color-hairline`, `--radius-card-lg`, `--radius-control`, `--motion-duration-fast|normal|slow`, `--focus-ring-default`, `--elevation-0..4`, `--font-features-tabular-nums`; hex-parity holds |
| `tests/Unit/DesignSystem/PrimitivePressTest.php` | 10 primitives consume `var(--focus-ring-default)`; transform transitions use `var(--motion-duration-fast) var(--motion-easing-ios)` |
| `tests/Unit/DesignSystem/DashboardAppShellTest.php` | Dashboard uses `bg-canvas`; KPI cards use `--color-hairline` + `--elevation-2`; 5 `tabular-nums` stat-card numbers |
| `tests/Unit/DesignSystem/LoginPageRenderTest.php` | Login uses `--elevation-3`, `--radius-card-lg`; 404 uses same |
| `tests/Unit/DesignSystem/UseSpringMathTest.php` | `useSpringMath.js` math invariants |

### 6.2 New PHPUnit invariants (added in Domain 2 / PR0)

See `specs/foundation-primitives/spec.md` §6 for the full list. Summary:

- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` — `canvasRoutes` array contains every polished module route + `/settings/branches` + `/settings/payment-methods` + `/procedure-stats`
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` — base class asserting the rule (not the literal): page surface references `bg-canvas` or `var(--color-canvas)`; key tables/forms reference `--color-hairline`; focus ring references `--focus-ring-default`; numeric columns use `tabular-nums`; no `<style scoped>` block remains
- `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` — pins the legacy alias list (see `DLR-R-009`)
- Per-module structure tests derived from `ModuleAppShellTestCase` (one per module, one per PR cluster — added ONLY when a new pattern is introduced, per the vertical-slice archive-report lesson that "tests pin the rule, not the literal")

### 6.3 Per-module structure tests (added with each PR cluster)

For each module PR, the apply phase adds a `tests/Unit/DesignSystem/<Module>AppShellTest.php` that extends `ModuleAppShellTestCase` and asserts the rule for that module:

- `test_page_surface_uses_canvas` (rule: `bg-canvas` or `var(--color-canvas)` reference)
- `test_card_surfaces_consume_hairline_border` (rule: `--color-hairline` reference)
- `test_focusable_elements_consume_focus_ring_token` (rule: `--focus-ring-default` reference)
- `test_numeric_columns_use_tabular_nums` (rule: `tabular-nums` reference on numeric columns)
- `test_no_style_scoped` (rule: `<style scoped>` not present)
- `test_no_legacy_aliases` (rule: file does not contain any of the alias patterns from `LegacyAliasForbiddenTest::LEGACY_ALIASES`)
- `test_status_pills_use_ui_status_badge` (rule: only applies to modules that render status pills — Pacientes, Profesionales, Ambientes, Tipos de cita, Caja, Presupuestos, ProcedureCatalog)

### 6.4 CI gates (untouched)

- `quality` (PHP syntax, JSON validation, Pint, ESLint, Prettier)
- `backend-tests` (MySQL 8.0 service, full PHPUnit suite)
- `frontend-build` (pnpm build)

### 6.5 Pre-PR visual gate (per module, per PR)

- `playwright-cli snapshot` at 1440×900 per module saved to `.playwright-cli/screenshots-rollout/<module>-1440x900.png`
- `playwright-cli snapshot` at 390×844 for Calendario, Pacientes, Caja only
- Eyeball-compare against the vertical-slice exemplar (`.playwright-cli/screenshots-pr3/login-1440x900.png` for form-heavy, `dashboard-1440x900.png` for KPI-heavy)
- If a defect is found, fix in the same PR; do not roll forward

---

## 7. Acceptance criteria (verifiable)

The rollout is considered complete when ALL of the following hold:

- [ ] All 17 module routes + `/settings/branches` + `/settings/payment-methods` + `/procedure-stats` render on `var(--color-canvas)` (`AppLayoutCanvasRoutesTest` green; per-module Playwright sweep confirms `bg-canvas` on every route in `canvasRoutes`)
- [ ] All card-like surfaces consume `var(--color-hairline)` borders, `var(--radius-card-lg)` (16px) on KPI cards, `var(--radius-control)` (8px) on inputs
- [ ] All numeric columns / KPI numbers / currency counters use `tabular-nums` (`font-feature-settings: var(--font-features-tabular-nums)`)
- [ ] All primitive interactions (focus, press, hover) use the proven token set (`PrimitivePressTest` green; `box-shadow: var(--focus-ring-default)` on every focusable element)
- [ ] All status pills consume `<UiStatusBadge>` (no inline `bg-success-100 text-success-700` etc.)
- [ ] All empty / loading / error states consume `<UiEmptyState>` / `<UiLoadingSpinner>` / `<UiSkeleton>` (no bespoke `@keyframes pulse` / `@keyframes spin`)
- [ ] No `<style scoped>` blocks in any polished module (6 rewritten to plain utility classes; rule asserted by `ModuleAppShellTestCase::test_no_style_scoped`)
- [ ] No legacy alias classes in any polished module (`LegacyAliasForbiddenTest` green)
- [ ] Playwright snapshots saved to `.playwright-cli/screenshots-rollout/` show parity with the vertical-slice exemplar at 1440×900 (mobile capture for Calendario, Pacientes, Caja)
- [ ] `tests/Unit/DesignSystem/*` invariants stay green at every PR boundary
- [ ] CI green at every PR boundary (quality, backend-tests MySQL, frontend-build)
- [ ] No `var(--color-*)` dangling references (`GeneratedTokensCssTest` parity guard)
- [ ] `AppLayout.canvasRoutes` covers all polished routes (`AppLayoutCanvasRoutesTest` green)
- [ ] `<UiStatusBadge>` primitive shipped in PR0 (Domain 2) and used in PR2 onwards
- [ ] Caja's `useCashRegister` contract is unchanged at every PR (the PR9 merge commit is tagged with `cash-register-revert-rationale`)
- [ ] BI Chart.js motion durations read from `tokens.motion.duration` (no CSS pass-through)
- [ ] Calendario does NOT override FullCalendar internals (`.fc-event`, `.fc-daygrid`, `.fc-timegrid`)
- [ ] `<PaginationComponent>` (the legacy duplicate) is removed when PR3 lands; only `<UiPagination>` remains
- [ ] Chain integrity: every PR is independently buildable, testable, and revertible per `chained-pr` skill rules

---

## 8. PR-to-spec traceability matrix

The PR chain order is anchored to `proposal.md` §7.2–§7.14. The artifact this enables is the input for `sdd-tasks`: each PR's `tasks/01-*.md` references the scenario IDs it satisfies.

| PR | Name | Scenarios satisfied | Reverse mapping |
|---|---|---|---|
| **PR0** | `pr0-foundation-canvas-routes-and-status-badge` | (Domain 2) `APP-CORE-001..004`, `STATUS-PRIM-001..003`, `TEST-BASE-001..003`, `LEGACY-LIST-001..002` | All Domain 1 scenarios depend on PR0 (the canvas surface, the StatusBadge primitive, and the `ModuleAppShellTestCase` rule-asserting base class) |
| **PR1** | `pr1-procedure-stats-tokenise` | `DLR-MOD-017` + `DLR-CORE-001..015` (template) | All Domain 1 cross-cutting rules validated against a non-Dashboard module |
| **PR2** | `pr2-quotations-tokenise-and-migrate-status-badge` | `DLR-MOD-010` + `DLR-CORE-001..015` | First consumer of `<UiStatusBadge>` (sets the pattern) |
| **PR3** | `pr3-smallest-modules-and-procedure-catalog` | `DLR-MOD-014`, `DLR-MOD-015`, `DLR-MOD-016` + pagination consolidation (Domain 2) | `<UiPagination>` consolidation rides here |
| **PR4** | `pr4-admin-crud-triplet` | `DLR-MOD-004..006` | Three admin CRUD modules in one PR; OPTIONAL split per `proposal.md` §7.6 |
| **PR5** | `pr5-patients-tokenise` | `DLR-MOD-003` | Largest CRUD module; OPTIONAL split PR5a + PR5b per `proposal.md` §7.7 |
| **PR6** | `pr6-clinical-modules-and-inline-keyframes-consolidation` | `DLR-MOD-009`, `DLR-MOD-011`, `DLR-MOD-012`, `DLR-MOD-018` | Inline `@keyframes` consolidation point |
| **PR7** | `pr7-calendar-chrome-only` | `DLR-MOD-002` | Highest-traffic clinical surface; chrome-only scope |
| **PR8** | `pr8-ai-analysis-and-analyzing-modal` | `DLR-MOD-013` | `@apply` rewrite + `@keyframes spin` consolidation |
| **PR9** | `pr9-cash-register-reverb-isolation` | `DLR-MOD-007` | Reverb + MercadoPago; last per `proposal.md` §7.11 |
| **PR10** | `pr10-bi-chartjs-js-duration-mapping` | `DLR-MOD-008` | Chart.js JS-side mapping |
| **PR11** | `pr11-settings-branches-and-payment-methods-optional` | (NOT in this spec — user opt-in only) | Conditional; runs only if user opts in during spec review |
| **PR12** | `pr12-verify-and-archive` | All `DLR-CORE-*` + `DLR-MOD-*` covered by visual sweep + PHPUnit run | Archive-time reconciliation; runs after all preceding PRs |

### 8.1 Spec-enabling PRs

The `DLR-CORE-001..015` cross-cutting rules are not "satisfied" by a single PR — they are validated incrementally as each PR cluster lands. The criterion is: at the end of the chain, every rule has been validated against every polished module. The standing PHPUnit tests (`AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `ModuleAppShellTestCase`-derived tests) are the regression gate.

### 8.2 Inputs to `sdd-tasks`

The `sdd-tasks` phase will use this traceability matrix as the per-PR instruction set. Each PR's `tasks/01-*.md` references the scenario IDs it satisfies, and the validation step at PR boundary runs the relevant invariant tests.

---

## 9. References

### 9.1 Source artifacts

- `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` — 596 lines; intent, scope, risks, OQ resolutions, PR chain, success criteria
- `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` — 496 lines; module inventory, per-module visual state, complexity tiers, PR chain ordering rationale
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/proposal.md` — 42 lines; vertical slice intent
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` — 303 lines; D1–D16 architecture decisions, G1–G13 screen-level scope, build script emission plan
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/tasks.md` — 375 lines; TDD RED-GREEN pattern, per-PR test specifications
- `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` — 71 lines; process lessons (3 defects from "test pins example, not rule")
- `openspec/specs/premium-design-foundation/spec.md` — 404 lines; the capability this rollout CONSUMES
- `openspec/specs/dashboard-period-comparisons/spec.md` — 162 lines; sibling capability unrelated to the rollout
- `openspec/changes/ui-redesign-apple-claude-2026-08/exploration.md` — 135 lines; the known-bad alternative (cream + terracotta + Newsreader); what to AVOID
- `resources/js/components/layout/AppLayout.vue` line 507 — `canvasRoutes` (the load-bearing PR0 edit)
- `resources/js/design-system/tokens.js` — the source of truth
- `resources/css/tokens.generated.css` — generated CSS
- `tests/Unit/DesignSystem/{TokensModuleTest,GeneratedTokensCssTest,PrimitivePressTest,DashboardAppShellTest,LoginPageRenderTest,UseSpringMathTest}.php` — the existing PHPUnit gate
- `AGENTS.md` §2, §4, §5, §6, §7 — project context, stack, 17-module inventory, conventions, troubleshooting
- `openspec/config.yaml` — preflight cache, strict TDD, hybrid artifact store, per-phase rules

### 9.2 Process invariant (forwarded from the vertical-slice archive-report)

The archive-report at lines 47–57 names three defects that all shared one root cause: **a test that pins an example instead of the rule**. The rollout's standing posture is to assert rules, not literals:

- `ModuleAppShellTestCase` is the rule-asserting base class (the rule is "the module references the token", not "the module contains this exact string")
- `LegacyAliasForbiddenTest` pins the list of forbidden patterns, not a single example
- Per-module structure tests assert the rule (rule: `bg-canvas` or `var(--color-canvas)` reference exists), not the literal (`bg-canvas` class string)

A second process lesson (line 56): *"run spec first, then design against the finished spec."* This rollout follows that order: this spec is staged before `sdd-design`, and the design phase operates against the finished spec.

---

*End of Domain 1 spec.*
