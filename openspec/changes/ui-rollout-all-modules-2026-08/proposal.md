# Proposal: UI Rollout to All Modules (`ui-rollout-all-modules-2026-08`)

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Date | 2026-08-11 |
| Phase | propose (2 of 6) |
| Author | `sdd-propose` sub-agent |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/proposal`) |
| Delivery strategy (cached) | `auto-chain` — chained PRs auto-activate (forecast: 13 PRs > 400-line budget) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` — forward to apply/verify in their prompts |
| Vertical slice (baseline) | `ui-premium-microdetail-2026-08` (closed 2026-08-11, 5 stacked PRs, PASS WITH WARNINGS) |
| Known-bad alternative | `ui-redesign-apple-claude-2026-08` (stalled at `apply-progress`, mix of Apple + Claude/terracotta/Newsreader). **DO NOT extend.** |
| Source artifacts | `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (53 KB) |
|  | `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/{proposal.md,design.md,tasks.md,archive-report.md}` |
|  | `openspec/specs/premium-design-foundation/spec.md` |
|  | `AGENTS.md` §2, §4, §5, §6, §7 |
|  | `openspec/config.yaml` |
|  | `resources/js/design-system/tokens.js` |
|  | `resources/css/tokens.generated.css` |
|  | `resources/js/components/layout/AppLayout.vue` (line 507: `canvasRoutes`) |
|  | `resources/js/app.js` (17 module routes + 2 settings routes) |
|  | `tests/Unit/DesignSystem/{TokensModuleTest,GeneratedTokensCssTest,PrimitivePressTest,DashboardAppShellTest,LoginPageRenderTest,UseSpringMathTest}.php` |

### Preflight snapshot (verbatim from session preflight)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain      # chained PRs auto-activate; do NOT re-ask
review_budget_lines: 400
chain_strategy: not_cached         # ask only when chained PRs actually trigger
strict_tdd: true
```

### Proven language status (carried in from the baseline)

The Apple-only language landed in 5 stacked PRs (#7 #5 #6 #8 #9) and is now the **baseline**:

- PR1 foundation (`#7`): tokens + build script + 2 PHPUnit invariants.
- PR2 primitives (`#5`): press / hover / focus / `ease-ios` adoption across 10 primitives.
- PR3 backend (`#6`): additive `data.comparisons` block on `/api/dashboard/stats`.
- PR4 dashboard (`#8`): fixed-slot KPI anatomy, single optical weight on topbar, keyhint chips on quick-actions.
- PR5 polish (`#9`): Login placeholders, helper-text removal, primary-button shadow + inner top highlight, hero scrim, 404 hero radius + hairline, sidebar group headers.

Closed: 137 passed / 1055 assertions (`Unit --filter=DesignSystem|UiRefresh`); 15 passed / 108 assertions (`DashboardComparisonTest`, MySQL against `odontosuite_test`).

This proposal inherits the entire proven language as-is. **No token is removed, no primitive is mutated, no easing curve is changed.** The rollout is replacement of legacy alias classes (`border-theme`, `bg-success-100`, `text-accent`, `focus:ring-primary-500`, etc.) with the proven `var(--color-*)` + Tailwind-tokenised equivalents in the 17 modules that still carry legacy class strings.

---

## 1. Intent

OdontoSuite reads as premium on the three screens the vertical slice polished (`/dashboard`, `/login`, `/404`), but the other 14 module routes + the auxiliary `/procedure-stats` sub-page + the 2 settings routes still render with the pre-vertical-slice look: opaque `border-theme` outlines, deprecated `bg-success-100` / `text-success-700` / `bg-accent` / `focus:ring-primary-500` ramps, generic Tailwind `shadow-*` utilities, and inline `@keyframes pulse` / `@keyframes spin` definitions in scoped `<style>` blocks. Cards sit on a `#ffffff` page surface that is also `#ffffff`, so cards read as outlines rather than as objects lifted off a canvas. KPI-style numbers don't use `tabular-nums`, so column figures jitter.

This change rolls the proven language — tokens, primitives, ease-ios, tinted elevation, hairlines, focus-ring, `tabular-nums`, and the canvas/surface separation — out to every module AGENTS.md §5 names, so a clinician landing on any page from `/dashboard` to `/cash-register` reads the same product. The vertical slice proved the language on a form-heavy page (Login) and a KPI-heavy page (Dashboard); this rollout proves the language scales to every CRUD, every detail page, every modal form, and every Chart.js / FullCalendar / Reverb-driven surface in the app. The user explicitly stated the intent to extend the language after the vertical slice landed. Deltas come from the explore phase evidence: 137 passed / 1055 assertions on the slice's PHPUnit invariants, 15 passed / 108 on the backend comparison block, plus the audit inventory of legacy classes in §3 of `explore.md`.

**Why now:** the foundation is settled and the PHPUnit invariants are wired. Every subsequent PR is mechanical class-string replacement at module-page level — the design risks of the vertical slice (token shape, easing choice, focus-ring composition, additive API shape) are all behind us. The rollback cost per module is one PR.

---

## 2. In-Scope

### 2.1 Module inventory (17 modules per AGENTS.md §5)

| # | Module | Directory | Primary route(s) | Risk tier |
|---|---|---|---|---|
| 1 | Dashboard | `modules/dashboard/` | `/dashboard` | **DONE** (vertical slice) |
| 2 | Calendario | `modules/appointments/` | `/calendar` | Tier 3 (FullCalendar + ConsultationWizard) |
| 3 | Pacientes | `modules/patients/` | `/patients`, `/patients/:id` | Tier 2 (list + detail, 44.5 + 53.4 KB) |
| 4 | Profesionales | `modules/professionals/` | `/professionals`, `/professionals/:id` | Tier 2 |
| 5 | Ambientes | `modules/environments/` | `/environments`, `/environments/:id` | Tier 2 |
| 6 | Tipos de cita | `modules/appointment-types/` | `/appointment-types`, `/appointment-types/:id` | Tier 2 |
| 7 | Caja | `modules/cash-register/` | `/cash-register`, `/cash-register/ready-to-bill` | Tier 3 (Reverb + MercadoPago, 11 components) |
| 8 | BI | `modules/business-intelligence/` | `/business-intelligence` | Tier 3 (Chart.js, single page) |
| 9 | Planes de tratamiento | `modules/treatment-plans/` | `/treatment-plans` | Tier 2 |
| 10 | Presupuestos | `modules/quotations/` | `/quotations` | Tier 2 (6 components) |
| 11 | Historias clínicas | `modules/medical-records/` | `/medical-records` | Tier 2 |
| 12 | Registros especialidad | `modules/specialty-records/` | `/specialty-records` | Tier 2 |
| 13 | Análisis IA | `modules/ai-analysis/` | `/ai-analysis` | Tier 3 (`AnalyzingModal`, heavy `@apply`) |
| 14 | Catálogo procedimientos | `modules/procedure-catalog/` | `/procedure-catalog`, `/procedure-catalog/:id` | Tier 2 |
| 15 | Mis procedimientos | `modules/my-procedures/` | `/my-procedures` | Tier 1 (8.0 KB, single page) |
| 16 | Recepción procedimientos | `modules/reception-procedures/` | `/reception-procedures` | Tier 1 (5.2 KB, single page) |
| 17 | Estadísticas catálogo | `modules/procedure-catalog/ProcedureStatsPage.vue` | mounted under `/procedure-catalog` route family | Tier 1 (6.4 KB) |

For each module, the rollout SHALL deliver:

- **Tokenised chrome:** the page surface uses `var(--color-canvas)` (canvas) where appropriate; card-like surfaces use `bg-theme-surface-elevated` (systemBackground) with a `var(--color-hairline)` border; `var(--radius-card-lg)` (16 px) on KPI / card-y surfaces and `var(--radius-control)` (8 px) on inputs.
- **Tinted elevation:** `box-shadow: var(--elevation-N)` replaces generic Tailwind `shadow-*` on cards and modals.
- **Tabular numerals:** currency tables, counters, KPI tiles use `font-feature-settings: var(--font-features-tabular-nums)` (Tailwind `tabular-nums`).
- **Focus ring:** all `focus:ring-primary-500 focus:border-accent` patterns replaced with `var(--focus-ring-default)` (composed).
- **Status pills:** tokenised `bg-system*-50 text-system*-700 rounded-full` (or extracted `<UiStatusBadge>` primitive per OQ#5).
- **Empty / loading states:** adopt `<UiEmptyState>`, `<UiLoadingSpinner>`, `<UiSkeleton>`; no ad-hoc spinners.
- **Tab / keyboard / spacing polish** to Apple conventions (`gap-2`, label-above-input, helper text removed where redundant, focus-visible reads).
- **Role-banner** at module top if the route is role-restricted (`middleware('role:...')` in `routes/api.php`).

### 2.2 Cross-cutting `AppLayout` work

- Extend `canvasRoutes` in `AppLayout.vue` from `['/dashboard', '/login', '/404']` (current line 507) to cover every polished module route. After this change, all 17 module routes + the 2 settings routes (`/settings/branches`, `/settings/payment-methods`) + `/procedure-stats` (where applicable) receive the canvas surface via the existing `isCanvasRoute` computed. One-line additive change, mandatory in PR0.
- Sidebar group headers and topbar single optical weight already shipped in PR5 (vertical slice). Do NOT re-touch.

### 2.3 Auxiliary modules — already done

- `modules/auth/` (Login + ForgotPasswordModal + ResetPasswordModal) and `modules/errors/` (NotFoundPage) were polished in the vertical slice. They are **OUT of scope** for this rollout's mechanical pass; only re-audit if a regression surfaces in shared primitives or the AppLayout canvas change.

### 2.4 Settings module — explicit decision

`resources/js/modules/settings/branches/BranchesPage.vue` (35.4 KB) and `resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue` exist on disk and are mounted at `/settings/branches` and `/settings/payment-methods`. **Recommendation: OUT of scope** for this rollout. Rationale:

- AGENTS.md §5 inventory lists 17 modules and does not include `settings/branches` or `settings/payment-methods`. They are multi-tenant infrastructure, surfaced in AppLayout under the "Configuración" group header but not in the user's module list.
- Including them inflates the chain from 13 PRs to 15 without strengthening the user's stated rollout intent.
- If the user wants them in, they signal during review of this proposal; the work fits into a Tier 2 PR pattern (CRUD list + form modal) and is mechanically identical to Profesionales / Ambientes.

The PR0 `canvasRoutes` extension SHALL include the 2 settings routes by default (the AppLayout edit is additive and trivial), so they pick up the canvas surface even if their internals are not tokenised this rollout.

---

## 3. Out of Scope

The following are explicitly excluded. They may be raised in a follow-up change once this rollout lands.

1. **Dark mode.** The proven language is light-only by design (`tokens.js` line 29: "The design system is light-only (no dark-mode media query)."). Dark mode requires a parallel capability, palette parity tests, and a `prefers-color-scheme` switch — its own change.
2. **Accessibility overhaul beyond incidental color contrast.** The proven language already honours `prefers-reduced-motion`, `prefers-reduced-transparency`, `prefers-contrast: more` at the primitive level. A WCAG 2.2 AA audit per form (axe-core runs, label-association checks, focus-trap audits, screen-reader narratives) is its own change.
3. **New feature work.** No new endpoints, no new pages, no new fields, no new permissions. The rollout is a UI migration; backend contracts are frozen.
4. **Backend refactors unrelated to UI.** No controller changes, no migrations, no event-listener rewiring. (The one Chart.js numeric exception is JS-side `tokens.motion.duration` lookup, not a backend change.)
5. **The abandoned `ui-redesign-apple-claude-2026-08` change.** Its cream `#FAF9F7` + terracotta `#C96442` + Newsreader + warm-near-black palette + Claude tone is a known-bad alternative. **DO NOT extend, rebadge, or re-import any of its artifacts.** If a previous exploratory change tried to combine "Apple chassis + Claude soul," the user's stated intent (which this proposal serves) is the opposite: pure Apple clinical, no Claude / terracotta / serif flourishes.
6. **New tokens, new primitives, new components.** `tokens.js` is frozen for this rollout. The only addition MAY be a `<UiStatusBadge>` primitive (decision deferred to OQ#5 / section 7.5 below).
7. **Sidebar / topbar / PageHeader re-design.** Already shipped in PR5 of the vertical slice.
8. **Per-KPI sparklines** (vertical slice open item #1). Requires new backend endpoint + cache strategy. Not this change.
9. **Two-tone numerals** (vertical slice open item #2, decision D12 REVERSIBLE). The decision stays rejected; this rollout does not add `text-numeric-fade`.
10. **Pagination primitive de-duplication** is IN scope (OQ#7), but only as a side-effect of the second module that touches pagination. The standalone consolidation PR is OUT.
11. **Cosmetic clip on the Dashboard comparator label** (vertical slice open item #3). Same fix would now apply to a status label, not the Citas Hoy card; defer to a future polish slice.
12. **`MobileNavigation.vue` and `ThemeSelector.vue`** (dead code per the abandoned change's audit). Removal is OUT of scope.

---

## 4. Approach

### 4.1 Style swap strategy

Replace legacy alias Tailwind classes one-by-one inside each module's `.vue` file. The mapping table is mechanical:

| Legacy class | Proven replacement | Notes |
|---|---|---|
| `border-theme` / `border-theme-light` | `border-[color:var(--color-hairline)]` | rgba(60,60,67,0.12), NOT the opaque `#c6c6c8` |
| `bg-success-100` / `text-success-700` | `bg-systemGreen-50 text-systemGreen-700` (status pill) | Or `<UiStatusBadge>` once extracted |
| `bg-warning-100` / `text-warning-700` | `bg-systemYellow-50 text-systemYellow-700` | |
| `bg-error-100` / `text-error-700` | `bg-systemRed-50 text-systemRed-700` | |
| `bg-accent` / `hover:text-primary-700` / `bg-primary-50` / `bg-primary-100` / `text-accent` | `bg-systemBlue-50` / `text-systemBlue-700` (info) / `bg-primary-600 text-white` (primary CTA) | Match the semantic role, not the legacy name |
| `focus:ring-primary-500 focus:border-accent` | `focus:outline-none focus:shadow-[var(--focus-ring-default)]` (or rely on Input primitive's built-in) | Most forms already consume `<UiInput>` |
| `rounded-lg` | context-dependent: `rounded-[var(--radius-card-lg)]` (16 px, KPI cards) / `rounded-[var(--radius-control)]` (8 px, inputs) / `rounded-[var(--radius-modal)]` (14 px, overlays) | Never a single uniform value |
| `shadow-md` / `shadow-lg` / `shadow-xl` (Tailwind) | `shadow-[var(--elevation-2)]` / `var(--elevation-3)` / `var(--elevation-4)` | |
| Generic `transition-all duration-200` | `transition: transform var(--motion-duration-fast) var(--motion-easing-ios)` (transforms); colour washes stay on `ease-out` | |
| `text-green-600` (raw Tailwind, ProcedureStats) | `text-systemGreen-600` | |
| Inline `<style scoped>` with `@apply` blocks (TreatmentPlans, CashRegister, AiAnalysis, AnalyzingModal, CreatePatientInline) | Rewrite to plain utility classes (use the existing `animate-pulse` / `animate-spin` Tailwind utilities) OR migrate the contents into a primitive. The vertical slice forbids `<style scoped>` (per `DashboardAppShellTest`); the rollout SHALL NOT introduce new `<style scoped>` blocks. | |

**One-by-one or per-cluster?** The explore forecast is 13 chained PRs, grouped per-cluster (one PR per module or per logical cluster — see section 8). Each PR is per-cluster, not per-class, because the per-PR overhead of CI + visual verification + PR body is too high for one-class-per-PR granularity. Within a PR, the order is: (1) `canvasRoutes` is already extended (PR0 prerequisite); (2) replace border-theme → hairline in module pages; (3) replace focus rings → tokenised; (4) replace status pills → tokenised ramps; (5) replace shadows → elevation tokens; (6) apply `tabular-nums` to numeric columns; (7) extract / re-place inline `@keyframes`; (8) adopt `<UiEmptyState>` / `<UiLoadingSpinner>` / `<UiSkeleton>` for empty/loading; (9) visual verification via playwright-cli at 1440x900 + 390x844.

### 4.2 Per-module checklist

Every PR SHALL touch every module in its scope and SHALL verify each of the following on every module page:

- [ ] Page surface reads `bg-canvas` (PR0 prerequisite).
- [ ] Card-like surfaces read `bg-theme-surface-elevated` with `border-[color:var(--color-hairline)]` and `shadow-[var(--elevation-2)]`.
- [ ] KPI cards / counters use `tabular-nums` and `rounded-[var(--radius-card-lg)]`.
- [ ] Inputs use `var(--radius-control)` (8 px) and the proven focus ring.
- [ ] Status pills use system*-50/system*-700 ramps (or `<UiStatusBadge>` once extracted).
- [ ] Empty state uses `<UiEmptyState>`, loading uses `<UiLoadingSpinner>` / `<UiSkeleton>`.
- [ ] Modal / Sheet uses the proven modal token layer.
- [ ] Numeric columns (currency tables, counters) use `tabular-nums`.
- [ ] No `<style scoped>` blocks introduced (or removed if found). The 4 modules with existing inline `@apply` blocks SHALL have them rewritten to plain utility classes during their respective PRs.
- [ ] Tab order matches visual order; `Tab` enters, `Shift+Tab` reverses; focus is always visible (the proven focus ring is the mechanism).
- [ ] If role-restricted, a `<RoleBanner>` (or equivalent explanatory block) at the top of the module surface names the role(s) the module is restricted to (avoids the "user landed here by mistake" defect).
- [ ] Playwright snapshot at 1440x900 + (if the module has responsive behaviour) 390x844 saved to `.playwright-cli/screenshots-rollout/<module>-<viewport>.png`.

### 4.3 Visual verification gate

For each module after migration:

1. Login as the canonical role (`admin@test.com` for admin modules, `finanzas@test.com` for Caja / BI / Presupuestos, `odonto@test.com` for clinical modules, `recep@test.com` for Recepción procedimientos — credentials in `CREDENTIALS.md`).
2. Capture `playwright-cli snapshot` at 1440x900 and at 390x844 (mobile optional per OQ#8).
3. Capture `playwright-cli screenshot --filename=.playwright-cli/screenshots-rollout/<module>-<viewport>.png`.
4. Eyeball-compare against the vertical-slice exemplar (`.playwright-cli/screenshots-pr3/login-1440x900.png` for form-heavy, `dashboard-1440x900.png` for KPI-heavy). Look for: canvas/surface contrast, hairline borders, focus ring on inputs, tabular-nums on numeric columns, no inline `@apply` residue.
5. If a defect is found, fix in the same PR; do not roll forward.

### 4.4 Test gate (PHPUnit, strict TDD)

- `tests/Unit/DesignSystem/TokensModuleTest.php` — keep green; no new tokens.
- `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` — keep green; hex-parity guard already excludes the rollout.
- `tests/Unit/DesignSystem/PrimitivePressTest.php` — keep green; primitives are frozen.
- `tests/Unit/DesignSystem/DashboardAppShellTest.php` — keep green.
- `tests/Unit/DesignSystem/LoginPageRenderTest.php` — keep green.
- New test (added in PR0): `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` — asserts the `canvasRoutes` array contains every polished module route. This is the standing guard for the AppLayout change.
- New test (added in PR0, optional): `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` — a base class that asserts per-route: page surface uses `bg-canvas`, key tables/forms reference `--color-hairline` + `--focus-ring-default`, numeric columns reference `tabular-nums`. The lesson from the vertical-slice archive-report ("a test that pins an example instead of the rule") applies: assert the rule, not the literal string.
- **Per-module structure test only when a NEW primitive or pattern is introduced** (per OQ#5, OQ#6, OQ#7). Avoid test bloat by reusing `ModuleAppShellTestCase` for any module that doesn't introduce a new pattern.

### 4.5 Strict TDD contract (forward to apply / verify)

- **Apply:** every UI replacement MUST come with a test that proves the new behaviour (either extending an existing module test or adding a sibling). Tests-first, RED-GREEN discipline, per the project's `strict_tdd: true` policy (AGENTS.md §3 + CLAUDE.md).
- **Verify:** each PR's verify phase MUST demonstrate that the module's Playwright snapshot at 1440x900 + 390x844 matches the proven language (canvas surface, hairline borders, focus rings, KPI/counter numbers with `tabular-nums`). The visual sweep is a documented verification surface, not a CI gate.

---

## 5. Risks + Mitigations

Top 5 risks, ordered by blast radius.

| # | Risk | Blast radius | Mitigation |
|---|---|---|---|
| 1 | **Caja is the highest-risk module.** 11 components + Reverb channel `cash-register` + MercadoPago `PaymentModal.vue`. Any UI change that accidentally touches the `<script>` block or removes a `.listen(...)` / `echo.leave(...)` call silently breaks real-time. | Real-time regression in cash-register touches the most operationally sensitive workflow (billing). | PR13 (Caja) is **last in the chain**. PR0 / PR1-12 NEVER touch Caja. Apply phase scope rule: `<script>` blocks are NOT touched in any PR. UI changes are template-level class-string replacement only. `useCashRegister` contract (debounce, cleanup) is observed verbatim. Visual verification at 1440x900 + 390x844 with the cashier role; if a defect appears, isolate via `git revert <pr-sha>` and re-do. |
| 2 | **BI uses Chart.js.** Chart.js `options.animation.duration` is a JS number, NOT a CSS variable. Mapping `var(--motion-duration-fast\|normal\|slow)` to JS numbers requires a one-time init-time resolution. | If the mapping is wrong, BI charts either snap (0ms) or feel sluggish (1000ms). | PR12 (BI) is isolated. Apply phase SHALL: (a) read `tokens.motion.duration` once at module init via `tokens.motion.duration.fast \| .normal \| .slow` (the values are `'120ms'`, `'200ms'`, `'320ms'` strings — strip `'ms'` and `parseInt`); (b) replace any `options.animation.duration: <number>` literal in `BusinessIntelligencePage.vue` with the resolved number; (c) assert via `ModuleAppShellTestCase` that the resolved number matches the token (assert the rule, not the literal). |
| 3 | **Calendario uses FullCalendar** — a third-party widget with its own CSS and event-rendering pipeline. FullCalendar's `.fc-event` class is not part of the design system; overriding it risks cascade breakage when FullCalendar updates. | Scope creep into FullCalendar internals could break the highest-traffic clinical surface (the calendar). | Apply phase scope rule: the Calendario PR touches ONLY surrounding chrome — the header, the controls, the modals (ConsultationWizard), the status pills. Do NOT override `.fc-event`, `.fc-daygrid`, `.fc-timegrid`, or any FullCalendar class. Visual verification per viewport confirms only the chrome is tokenised. |
| 4 | **Inline `<style scoped>` with `@apply`** in 4 modules: `TreatmentPlansPage.vue`, `CashRegisterPage.vue`, `AiAnalysisPage.vue`, `AnalyzingModal.vue`, `CreatePatientInline.vue`, plus `TreatmentPlanModal.vue` (per explore §4.4 + §3.2). The vertical slice forbade `<style scoped>` (per `DashboardAppShellTest`); the rollout SHALL rewrite to plain utility classes or extract to a primitive. | Tailwind purges unreferenced classes; leaving a now-obsolete `@apply` block in `tailwind.config.js` can silently break the rollout's visual parity at compile time. | Apply phase: in each PR that touches a module with `<style scoped>`, rewrite the contents to plain utility classes (use the existing `animate-pulse` / `animate-spin` Tailwind utilities for keyframes — `LoadingSpinner` already exists for the bespoke spin case). NO grandfather clause (per OQ#9). Painful now, clean later. |
| 5 | **9 open questions from explore** need resolution before / within spec phase. If punted to apply, each becomes a micro-spec decision during implementation that may reverse a vertical-slice ruling. | Decisions made late carry rework cost. | Section 7 of this proposal resolves all 9 with a clear recommendation + rationale. The user can flag during review of the spec; apply phase does NOT silently resolve any of them. The decisions are NOT blocking the proposal phase. |

Additional risks worth naming (lower blast radius):

- **`Pagination` primitive duplication** (OQ#7) — mechanical risk: a per-module PR accidentally imports `<PaginationComponent>` (the legacy duplicate) instead of `<UiPagination>`. Mitigation: per-PR grep for `PaginationComponent` import; if found, rewrite to `<UiPagination>`.
- **Reverb broadcasting regression in non-Caja modules** (channels `appointments`, `patients`, `treatment-plans`, `quotations`, `medical-records`, `specialty-records`, `procedure-catalog`). Mitigation: same as Caja — `<script>` blocks are NEVER touched; template-level class-string replacement only.
- **Hex literals leaking back in** during replacement. Mitigation: `GeneratedTokensCssTest::generated_css_only_contains_token_hex_literals` keeps parity, but the new `ModuleAppShellTestCase` should also grep module pages for `#RRGGBB` literals OUTSIDE the tokens file. (Per-module test added only when a defect is observed, not prophylactically.)
- **Reduced-motion regression on the new status pills / empty states.** Mitigation: `<UiEmptyState>`, `<UiLoadingSpinner>`, `<UiSkeleton>` already honour reduced-motion (PR2 of the vertical slice); the rollout inherits that contract. Spot-check `prefers-reduced-motion: reduce` in at least one of the high-traffic modules (Calendario, Dashboard, Caja) during verify.

---

## 6. Open Questions (with recommendations)

The 9 open questions from `explore.md` §7 are answered here. Each gets a clear recommendation; the user can flag during review of the spec. Apply phase does NOT silently resolve any of them.

### OQ#1 — Dark mode

**Recommendation: OUT of scope for this rollout.**

Rationale: the proven language is light-only by design (`tokens.js` line 29). Dark mode is a parallel capability — it requires its own palette parity tests, a `prefers-color-scheme` switch, and at least one pass over the 30 primitives + 17 modules for dark-mode colours. The vertical slice deliberately did NOT include dark mode; adding it now would double every PR's visual verification surface and double the regression risk. The user's stated intent is to extend the proven light language to all modules; dark mode is a separate user intent that warrants its own change once the light language is everywhere.

### OQ#2 — Accessibility target

**Recommendation: WCAG 2.1 AA as default, AAA opportunistic.**

Rationale: the proven language already honours `prefers-reduced-motion`, `prefers-reduced-transparency`, `prefers-contrast: more` on primitives (per Apple guidance §14 + `design.md` D11). The rollout inherits these contracts for free. AA is the practical bar (4.5:1 contrast for body text, 3:1 for large text ≥18 px) and aligns with `impeccable` §4.5 BUTTON CONTRA contrast check. AAA (7:1 for body, 4.5:1 for large) is opportunistic — wherever a token already meets AAA (e.g. ink on canvas), use it; do not promote the bar to AAA across the board, because the Apple palette wasn't designed for AAA contrast.

A formal WCAG audit (axe-core sweep per module, label-association checks, focus-trap audit on modals) is its own change. NOT this rollout.

### OQ#3 — Settings / Branches / Payment Methods

**Recommendation: OUT of scope for this rollout.**

Rationale: AGENTS.md §5 lists 17 modules; settings/branches + settings/payment-methods are NOT in that inventory. They are multi-tenant infrastructure. The AppLayout `canvasRoutes` extension in PR0 SHALL include them (they pick up the canvas surface trivially), but their internal tokenisation is deferred. If the user wants them in, signal during review of this proposal; the work is mechanically identical to Profesionales / Ambientes (Tier 2, 1 PR).

### OQ#4 — Two-tone numerals (D12 from vertical slice)

**Recommendation: NO. Single tabular-nums everywhere — keep it honest.**

Rationale: the vertical slice's D12 rejection was REVERSIBLE, pending user override. No user override arrived between slice close (2026-08-11) and this proposal's date (2026-08-11). The clinical-legibility argument still holds: numbers in this app are clinical data, not marketing copy. Adding the leading-ink / trailing-faded treatment would degrade legibility for zero comprehension gain. If the user explicitly overrides during review of the spec, apply phase can add a `text-numeric-fade` Tailwind variant in a follow-up slice; it is NOT this rollout's scope.

### OQ#5 — `<UiStatusBadge>` primitive

**Recommendation: YES — extract in PR0 alongside `canvasRoutes`.**

Rationale: the duplication threshold (≥2 places) is met (Pacientes, Profesionales, Tipos de cita, Caja, Presupuestos, ProcedureCatalog all use status pills with inline classes). `QuotationStatusBadge.vue` already exists as a partial extraction; making it generic is mechanical. A `<UiStatusBadge variant="success | warning | error | info | neutral" label="...">` primitive with internal tokenised ramps (`bg-systemGreen-50 text-systemGreen-700` etc.) keeps every module's status pill consistent. Bonus: the primitive removes the per-module PR's temptation to invent a new status colour combination.

Place the new primitive at `resources/js/components/ui/StatusBadge.vue`. Existing components migrate from `QuotationStatusBadge` to `<UiStatusBadge>` in PR2 (Quotations) as the first consumer.

### OQ#6 — `hover-lift` utility

**Recommendation: YES — consolidate on the proven `<UiCard>` primitive.**

Rationale: `hover-lift` is defined in `resources/css/utilities.css`. The proven `<UiCard>` already ships `hover-lift` behaviour (translateY(-2px), with focus ring + reduced-motion fallback). The rollout SHALL: (a) confirm `hover-lift` utility class is still in `utilities.css`; (b) where it appears in module pages, replace with `<UiCard clickable>` (the proven primitive handles hover + press + focus); (c) keep `hover-lift` utility for one-off decorative purposes only (rare). PR0 verifies `hover-lift` is reachable and documented; per-module PRs migrate where it's a `Card` substitute.

### OQ#7 — Pagination primitive duplication

**Recommendation: EXTRACT — consolidate on `<UiPagination>`.**

Rationale: `<UiPagination>` (6.6 KB, the `Ui*`-prefixed primitive) and `<PaginationComponent>` (6.2 KB, the duplicate) coexist. Per AGENTS.md §7, the `Ui*` prefix is the project convention. The duplicate is a pre-existing inconsistency, not introduced by this rollout. Consolidation: (a) PR0 audits both primitives; (b) when the second module touched needs pagination (likely PR3 — Recepción procedimientos — which has list pagination), the PR replaces `<PaginationComponent>` imports with `<UiPagination>` and removes the duplicate file. NOT a standalone PR; the work rides the per-module PR.

### OQ#8 — Per-module screenshot verification

**Recommendation: 1440x900 desktop capture is mandatory per module. 390x844 mobile capture is optional per module — capture ONLY when the module has responsive behaviour worth proving.**

Rationale: the vertical slice verified 4 PNGs at 1440x900 + 390x844 for 2 screens. With 16 modules + auxiliary screens to roll out, full mobile capture explodes the screenshot count (~32 PNGs at ~200 KB each = ~6 MB). Most modules (Recepción procedimientos, Mis procedimientos, Estadísticas catálogo) are not mobile-first — desktop proves the rollout. Modules with documented responsive behaviour (Calendario for sure, Pacientes for the mobile card fallback per explore §3.2, Caja for the cashier mobile path) get the mobile capture too.

Save location: `.playwright-cli/screenshots-rollout/<module>-<viewport>.png`. The directory is `.gitignore`d (already in `.playwright-cli/`); the screenshots are a local verification artifact, not committed.

### OQ#9 — Inline `<style scoped>` grandfather clause

**Recommendation: NO grandfather — rewrite to tokens during rollout.**

Rationale: the vertical slice forbids `<style scoped>` per `DashboardAppShellTest` (the test asserts no `<style scoped>` blocks in the dashboard). Carrying a grandfather clause for 5 files would propagate a known defect and require a follow-up change. Painful now, clean later. The 5 affected files (TreatmentPlansPage.vue, CashRegisterPage.vue, AiAnalysisPage.vue, AnalyzingModal.vue, CreatePatientInline.vue, plus TreatmentPlanModal.vue per explore §4.4) are rewritten in their respective PRs. The PR for each file includes a test asserting no `<style scoped>` block remains.

---

## 7. PR Chain Ordering + Delivery

### 7.1 Strategy realisation

- `delivery_strategy` cached: `auto-chain` — chained PRs auto-activate, do NOT re-ask user.
- Forecast: **13 chained PRs** (per explore §6.2) **+ 1 prerequisite PR0** = **14 PRs total**.
- This exceeds the 400-line review budget on a single-PR basis. Chained PRs MUST auto-activate. They do (per `auto-chain` cached).
- `chain_strategy` is **not_cached**. This proposal recommends **`stacked-to-main`** — same as the successful vertical slice — unless the user signals otherwise during review. Rationale:
  - **Vertical slice precedent.** `ui-premium-microdetail-2026-08` used `stacked-to-main` and shipped 5 PRs cleanly. The same reviewer workflow applies.
  - **No integration barrier.** Each PR is independently buildable, testable, and revertable. PR0 (`canvasRoutes` + `<UiStatusBadge>`) is the only prerequisite; the rest can stack in any order per the explore's tier ordering.
  - **Avoid Feature Branch Chain** unless the user wants a tracker PR. The vertical slice didn't, and the work is mechanically additive.
  - **Re-evaluation trigger.** If the user wants a tracker PR during review, the chain_strategy can flip without re-doing the proposal; the proposal only fixes the strategy choice.

### 7.2 PR0 — Foundation (REQUIRED FIRST)

| Field | Value |
|---|---|
| Name | `pr0-foundation-canvas-routes-and-status-badge` |
| Scope | (1) Extend `canvasRoutes` in `AppLayout.vue` line 507 to include every polished module route + `/settings/branches` + `/settings/payment-methods` + `/procedure-stats`. (2) Extract `<UiStatusBadge>` primitive to `resources/js/components/ui/StatusBadge.vue` with variants `success / warning / error / info / neutral`, internal tokenised ramps. (3) Add `AppLayoutCanvasRoutesTest` asserting the route list. (4) Consolidate `hover-lift` references in this PR where they touch the foundation (audit only; per-module migration is later). (5) Add `ModuleAppShellTestCase` base class (optional but recommended). (6) Audit `Pagination` primitive duplication; plan consolidation ride-along in PR3. |
| Target module(s) | AppLayout only + new StatusBadge primitive |
| Risk | Low |
| Dependencies | None |
| Line estimate | ~200 authored (AppLayout line 507 edit is one line; StatusBadge primitive ~120 lines; tests ~60 lines; audit comments ~20 lines) |
| Reversibility | Revert restores `canvasRoutes` to the original 3-route behaviour; `<UiStatusBadge>` is added and unused until a module adopts it |

### 7.3 PR1 — ProcedureStats (second proving ground)

| Field | Value |
|---|---|
| Name | `pr1-procedure-stats-tokenise` |
| Scope | `ProcedureStatsPage.vue` only — tokenise the 3 KPI cards and the table; replace `text-green-600` raw Tailwind with `text-systemGreen-600`. Reuse Dashboard KPI anatomy. First consumer of `<UiStatusBadge>` (if it has any status pills). |
| Target module(s) | 17 (Estadísticas catálogo) |
| Risk | Low (smallest module after Recepción) |
| Dependencies | PR0 |
| Line estimate | ~120 |

### 7.4 PR2 — Quotations (sets the status-badge precedent)

| Field | Value |
|---|---|
| Name | `pr2-quotations-tokenise-and-migrate-status-badge` |
| Scope | `QuotationsPage.vue` + 5 components (`QuotationCard`, `QuotationModal`, `QuotationDetail`, `QuotationStatusBadge`, `QuotationApprovalModal`). Migrate `QuotationStatusBadge.vue` to use `<UiStatusBadge>` internally; keep the file as a thin wrapper for backward compat (or remove it — defer to apply phase). Tokenise status pills, table dividers, modal focus, currency columns (tabular-nums). |
| Target module(s) | 10 (Presupuestos) |
| Risk | Medium |
| Dependencies | PR0, PR1 |
| Line estimate | ~280 |

### 7.5 PR3 — Recepción + Mis procedimientos + ProcedureCatalog (catalog cluster)

| Field | Value |
|---|---|
| Name | `pr3-smallest-modules-and-procedure-catalog` |
| Scope | `ReceptionProceduresPage.vue` + `MyProceduresPage.vue` + `ProcedureCatalogPage.vue` + `ProcedureCatalogDetailPage.vue` + `ProcedureCatalogFormModal.vue`. PR3 is the consolidation point for `<UiPagination>` — replace `<PaginationComponent>` imports here. |
| Target module(s) | 14, 15, 16 |
| Risk | Medium (catalog has the import modal + CSV upload, but is bounded) |
| Dependencies | PR0, PR1, PR2 |
| Line estimate | ~360 |

### 7.6 PR4 — AppointmentTypes + Ambientes + Profesionales (admin CRUD triplet)

| Field | Value |
|---|---|
| Name | `pr4-admin-crud-triplet` |
| Scope | `AppointmentTypesPage.vue` + `AppointmentTypeDetailPage.vue` + `EnvironmentsPage.vue` + `EnvironmentDetailPage.vue` + `ProfessionalsPage.vue` + `ProfessionalDetailPage.vue`. Three admin CRUD modules share the list+detail+form-modal pattern. |
| Target module(s) | 4, 5, 6 |
| Risk | Medium |
| Dependencies | PR0–PR3 |
| Line estimate | ~380 |
| Note | The three are independent (no shared composable) — they could merge as a stacked pair via Feature Branch Chain if the reviewer load exceeds the budget on a single PR. The proposal leaves the choice to apply phase; the default is one PR. |

### 7.7 PR5 — Patients (largest CRUD)

| Field | Value |
|---|---|
| Name | `pr5-patients-tokenise` |
| Scope | `PatientsPage.vue` (44.5 KB) + `PatientDetailPage.vue` (53.4 KB). Largest CRUD module. Tokenise table, mobile card fallback (the `hover-lift` shadow transition), status pills (active/inactive), currency fields (insurance balance), numeric counters. |
| Target module(s) | 3 |
| Risk | Medium-High |
| Dependencies | PR0–PR4 |
| Line estimate | ~390 (right at the budget) |
| Note | If the diff exceeds 400 lines, split into PR5a (PatientsPage) + PR5b (PatientDetailPage). |

### 7.8 PR6 — Clinical modules (MedicalRecords + SpecialtyRecords + TreatmentPlans)

| Field | Value |
|---|---|
| Name | `pr6-clinical-modules-and-inline-keyframes-consolidation` |
| Scope | `MedicalRecordsPage.vue` + `SpecialtyRecordsPage.vue` + `TreatmentPlansPage.vue` + 2 components (`CreatePatientInline.vue`, `TreatmentPlanModal.vue`). Rewrite `<style scoped>` blocks in TreatmentPlans + TreatmentPlanModal + CreatePatientInline to plain utility classes (OQ#9 — no grandfather). Adopt `animate-pulse` / `animate-spin` Tailwind utilities instead of inline `@keyframes`. |
| Target module(s) | 9, 11, 12 |
| Risk | Medium |
| Dependencies | PR0–PR5 |
| Line estimate | ~370 |

### 7.9 PR7 — Calendario (FullCalendar chrome only)

| Field | Value |
|---|---|
| Name | `pr7-calendar-chrome-only` |
| Scope | `CalendarPage.vue` (FullCalendar surrounding chrome: header, controls, modals, status pills) + `ConsultationWizard.vue` (form-heavy, 50+ inputs, all migrate to `<UiInput>` focus ring). DO NOT override `.fc-event`, `.fc-daygrid`, `.fc-timegrid`. |
| Target module(s) | 2 |
| Risk | High (highest-traffic clinical surface) |
| Dependencies | PR0–PR6 |
| Line estimate | ~360 |

### 7.10 PR8 — AIAnalysis (special motion + modal cluster)

| Field | Value |
|---|---|
| Name | `pr8-ai-analysis-and-analyzing-modal` |
| Scope | `AiAnalysisPage.vue` (21.0 KB) + `AnalyzingModal.vue`. Heavy `<style scoped>` blocks with `@apply` + inline `@keyframes spin 3s linear infinite;`. Rewrite to plain utility classes; consolidate spin on `LoadingSpinner`. Status pills → `<UiStatusBadge>`. |
| Target module(s) | 13 |
| Risk | Medium-High (consolidation point for the `@apply` rewrite) |
| Dependencies | PR0–PR7 |
| Line estimate | ~260 |

### 7.11 PR9 — CashRegister (Reverb isolation, late by risk)

| Field | Value |
|---|---|
| Name | `pr9-cash-register-reverb-isolation` |
| Scope | `CashRegisterPage.vue` + `ReadyToBillPage.vue` + 9 components (`CashReports`, `TransactionList`, `TransactionModal`, `CloseCashModal`, `OpenCashModal`, `MovementList`, `MovementModal`, `PaymentModal`, `PendingPaymentsList`, `SessionList`, `MercadoPagoCheckout`). 11 components, Reverb channel `cash-register`, MercadoPago `PaymentModal.vue` (22.3 KB). Rewrite remaining `<style scoped>` blocks (CashRegisterPage). Status pills → `<UiStatusBadge>`. |
| Target module(s) | 7 |
| Risk | High (largest module, real-time) |
| Dependencies | PR0–PR8 |
| Line estimate | ~400 (right at the budget) |
| Note | UI changes are TEMPLATE-LEVEL class-string replacement only. `<script>` blocks are NOT touched. `useCashRegister` contract preserved verbatim. |

### 7.12 PR10 — BusinessIntelligence (Chart.js JS-side mapping)

| Field | Value |
|---|---|
| Name | `pr10-bi-chartjs-js-duration-mapping` |
| Scope | `BusinessIntelligencePage.vue` only. Replace `options.animation.duration: <number>` literals with values resolved from `tokens.motion.duration` at module init time. Tokenise the filters card, the chart cards, the report selectors. |
| Target module(s) | 8 |
| Risk | High (isolated because of Chart.js) |
| Dependencies | PR0–PR9 |
| Line estimate | ~280 |

### 7.13 PR11 — Settings (optional, only if user approves during review)

| Field | Value |
|---|---|
| Name | `pr11-settings-branches-and-payment-methods-optional` |
| Scope | `BranchesPage.vue` (35.4 KB) + `PaymentMethodsPage.vue` + `BranchFormModal.vue` + `PaymentMethodFormModal.vue`. Tokenise the page surface, the table, the form modals, the counters (use `tabular-nums`). Replace `text-3xl font-bold text-theme-primary` with tokenised equivalent. |
| Target module(s) | Auxiliary (settings/branches, settings/payment-methods) |
| Risk | Medium |
| Dependencies | PR0–PR10 |
| Line estimate | ~280 |
| Note | This PR exists ONLY if the user explicitly opts in during review of this proposal. Default is OUT (OQ#3). |

### 7.14 PR12 — Verify all + archive change

| Field | Value |
|---|---|
| Name | `pr12-verify-and-archive` |
| Scope | Run the full verification suite (visual sweep across all polished modules, full PHPUnit, full frontend-build). Update `openspec/changes/ui-rollout-all-modules-2026-08/archive-report.md` per the project's archive discipline. Update AGENTS.md §6 status if needed. The archive phase is a meta-PR; it may also be authored as a GitHub Actions workflow run rather than a literal PR. |
| Target | All 17 modules |
| Risk | Low (verification only) |
| Dependencies | PR0–PR10 (or PR11) |
| Line estimate | ~50 (doc-only) |

### 7.15 PR count summary

| PR | Risk | Modules | Dependencies | Lines | Reversible |
|---|---|---|---|---|---|
| PR0 | Low | AppLayout + StatusBadge | none | ~200 | yes |
| PR1 | Low | ProcedureStats | PR0 | ~120 | yes |
| PR2 | Medium | Quotations | PR0, PR1 | ~280 | yes |
| PR3 | Medium | Reception, MyProc, Catalog | PR0–PR2 | ~360 | yes |
| PR4 | Medium | AppTypes, Env, Prof | PR0–PR3 | ~380 | yes |
| PR5 | Med-High | Patients | PR0–PR4 | ~390 | yes |
| PR6 | Medium | Medical, Specialty, TreatmentPlans | PR0–PR5 | ~370 | yes |
| PR7 | High | Calendar | PR0–PR6 | ~360 | yes |
| PR8 | Med-High | AIAnalysis | PR0–PR7 | ~260 | yes |
| PR9 | High | CashRegister | PR0–PR8 | ~400 | yes |
| PR10 | High | BI | PR0–PR9 | ~280 | yes |
| PR11 (opt) | Medium | Settings | PR0–PR10 | ~280 | yes |
| PR12 | Low | Archive | all | ~50 | yes |
| **Total** | | | | **~3,730** | |

Per-PR: target ~400 authored lines max. Avoid scope creep across PRs — the dependency graph is strict. The vertical-slice archive-report's lesson ("a test that pins an example instead of the rule") applies to every per-PR test: assert the rule, not the literal string.

---

## 8. Rollback Plan

- **Per-PR revert:** each PR is independently revertible via `git revert <merge-sha>` because `stacked-to-main` keeps every commit reachable and named.
- **PR0 specifically:** the `canvasRoutes` extension in `AppLayout.vue` is the load-bearing change. A single `git revert <pr0-sha>` restores the original 3-route behaviour (`['/dashboard', '/login', '/404']`). The `<UiStatusBadge>` primitive is added but unused by any module until PR2 adopts it, so its presence does not affect rendering. PR0 revert = safe and additive-clean.
- **Caja PR9:** in addition to the standard `git revert`, apply phase SHALL tag the merge commit with `cash-register-revert-rationale` and notify the cashier role's verified screenshot baseline so the regression can be reproduced.
- **BI PR10:** Chart.js `options.animation.duration` is a JS-side lookup at module init. If the mapping is wrong, a follow-up hotfix PR is preferable to a revert (since reverting drops the tokenisation entirely). Apply phase documents the fallback path: keep the BI tokenisation, but hardcode the Chart.js duration temporarily.
- **No destructive schema/data migrations in this change.** No backend changes. The visual layer is fully revertible.

---

## 9. Success Criteria

Verifiable; the proposal is considered complete when all of the following hold:

- [ ] **All 17 modules + auxiliary screens render with `var(--color-canvas)` surface.** `AppLayoutCanvasRoutesTest` green; per-module Playwright sweep confirms `bg-canvas` on every route in `canvasRoutes`.
- [ ] **All card-like surfaces use `var(--color-hairline)` borders where applicable, `var(--radius-card-lg)` (16 px) on KPI cards, `var(--radius-control)` (8 px) on inputs.** Per-module structure test green.
- [ ] **All primitive interactions (focus, press, hover) use the proven token set.** `PrimitivePressTest` green; per-module visual sweep confirms `box-shadow: var(--focus-ring-default)` on every focusable element.
- [ ] **Per-module screenshots prove parity with vertical-slice exemplar at 1440x900** for desktop; mobile capture for the 4 high-responsive modules (Calendario, Pacientes, Caja, plus any module with documented responsive behaviour). Saved to `.playwright-cli/screenshots-rollout/<module>-<viewport>.png` (gitignored).
- [ ] **`tests/Unit/DesignSystem/*` invariants stay green** — `TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `UseSpringMathTest`, plus the new `AppLayoutCanvasRoutesTest` and optional `ModuleAppShellTestCase`.
- [ ] **CI green:** `quality` (PHP syntax, JSON validation, Pint, ESLint, Prettier), `backend-tests` (MySQL service), `frontend-build` (pnpm build).
- [ ] **No `var(--color-*)` references anywhere in `resources/js/` that aren't defined in generated CSS.** The `GeneratedTokensCssTest` parity guard is the standing witness; the hex-parity assertion in that test must remain green.
- [ ] **No `<style scoped>` blocks introduced** in the rolled-out modules. The 5 files with existing `<style scoped>` (TreatmentPlansPage, CashRegisterPage, AiAnalysisPage, AnalyzingModal, CreatePatientInline, TreatmentPlanModal) are rewritten to plain utility classes (OQ#9). Each rewrite has a test asserting no `<style scoped>` block remains in the affected file.
- [ ] **Chain integrity:** every PR is independently buildable, testable, and revertible per `chained-pr` skill rules. No `size:exception` accepted unless the user flags during review.

---

## 10. References

### 10.1 Source artifacts (read for this proposal)

| File | Why it matters |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` | The 53 KB upstream artifact. Per-module inventory, audit of legacy classes, complexity tiers, PR slice ordering rationale. Authoritative for "what's in each module." |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/proposal.md` | Defines intent and scope of the proven language — load-bearing reference for "what shipped." |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` | 303 lines of architecture decisions D1–D16 + Build Script Emission Plan + Screen-Level Scope G1–G13 + Slice Boundaries. Source of truth for every concrete value (token hex, easing curve, focus-ring composition, elevation alpha). |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/tasks.md` | Original task breakdown for the 5-PR vertical slice. Precedent for the chained-PR delivery model. |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` | Process lessons: (a) "a test that pins an example instead of the rule" — apply to per-module tests; (b) "run spec first, then design against the finished spec" — applies to this rollout (spec phase runs before design phase). |
| `openspec/changes/ui-redesign-apple-claude-2026-08/exploration.md` | The stalled alternative. Confirms what to AVOID: cream + terracotta + Newsreader + Claude. DO NOT extend. |
| `openspec/specs/premium-design-foundation/spec.md` | The archived capability, now in `openspec/specs/`. Stable reference. |
| `openspec/config.yaml` | Preflight cache + strict TDD + pnpm-only + 400-line budget + CI MySQL. |
| `AGENTS.md` | Project context, stack, 17-module inventory (§5), commands (§3), conventions (§7), troubleshooting (§8). |
| `resources/js/design-system/tokens.js` | The proven token source-of-truth. Every concrete value the rollout consumes. |
| `resources/css/tokens.generated.css` | Generated CSS. The output the rollout consumes. |
| `resources/js/components/layout/AppLayout.vue` | The `canvasRoutes` gate (line 507). PR0 of the rollout. |
| `resources/js/components/ui/{Card,Button,Input,Badge,Avatar,Modal,Sheet,ConfirmDialog,Toast,Select}.vue` | 10 tokenised primitives. Inherited by the rollout as-is. |
| `resources/js/components/ui/{EmptyState,LoadingSpinner,Skeleton,UiDataTable,UiFilterBar,UiPagination,PaginationComponent,UiTabs,UiCurrencyInput,UiRichTextEditor,UiToothSelector,UiFileUpload,UiFileUploader,UiPatientSelector,UiProcedureSelector,UiTreatmentPlanSelector,UiBreadcrumbs,UiAvatar,UiReceiptPreview,UiProgressBar,UiRadioGroup,UiNotificationToast}.vue` | 22 additional primitives available for the rollout's mechanical replacement. |
| `resources/js/modules/dashboard/DashboardPage.vue` | Reference screen. KPI card anatomy, comparison chip, today-appointments empty state, topbar single weight, quick-action keyhints. |
| `resources/js/modules/auth/LoginPage.vue` | Reference screen. Form polish, primary-button shadow + inner highlight, hero scrim. |
| `resources/js/modules/errors/NotFoundPage.vue` | Reference screen. Hero radius + hairline. |
| `resources/js/modules/{patients,appointments,cash-register,business-intelligence,professionals,quotations,treatment-plans,medical-records,specialty-records,ai-analysis,procedure-catalog,environments,appointment-types,my-procedures,reception-procedures}/**/*.vue` | 16 module directories. Inventory of files the rollout touches. |
| `resources/js/modules/settings/branches/BranchesPage.vue` + `settings/payment-methods/PaymentMethodsPage.vue` | Auxiliary settings modules. OUT of scope per OQ#3; PR11 only if user opts in. |
| `resources/js/app.js` (lines 44-192) | Router definitions for the 17 module routes + 2 settings routes. PR0 `canvasRoutes` source. |
| `tests/Unit/DesignSystem/{TokensModuleTest,GeneratedTokensCssTest,PrimitivePressTest,DashboardAppShellTest,LoginPageRenderTest,UseSpringMathTest}.php` | The PHPUnit test gate. The rollout extends with `AppLayoutCanvasRoutesTest` (PR0) and optionally `ModuleAppShellTestCase` (PR0). |
| `tailwind.config.js` | Reads from `tokens.js`. Exposes `transitionDuration` from `motion.duration`, `borderRadius` from `radius`. No change needed for the rollout. |
| `scripts/build-tokens-css.mjs` | Generator for `resources/css/tokens.generated.css`. No change needed for the rollout. |
| `resources/js/composables/{useApi,useAuth,useCashRegister,useWebSocketNotifications,useEcho,useSpring,useSpring2D,useFormatters,useMercadoPago,useProcedureCatalog,useProcedureFavorites,useQuotations,useMedicalRecords,useSpecialtyRecords,useTransactions,useTreatmentPlans,useBranches,usePaymentMethods}.js` | 18+ composables. Out of scope for the rollout (UI-only change), but consulted for blast-radius thinking. |
| `.playwright-cli/screenshots-pr3/{login,notfound}-{1440x900,390x844}.png` | Visual evidence the vertical slice shipped. Eyeball-compare baseline. |
| `.playwright-cli/page-*.yml` | Playwright snapshots from PR3 verification. ~85 YAML files. |
| `resources/css/utilities.css` (`.hover-lift`) | The legacy utility the rollout consolidates onto `<UiCard>` per OQ#6. |

### 10.2 Skills loaded before writing

| Skill | Path | Used for |
|---|---|---|
| `design-taste-frontend` | `~/.agents/skills/design-taste-frontend/SKILL.md` | Anti-slop design discipline; the Apple-only language is honest (clinical), not decorative |
| `apple-design` | `~/.agents/skills/apple-design/SKILL.md` | Source of the proven language: damping, response, focus ring, canvas/surface contrast |
| `impeccable` | `~/.agents/skills/impeccable/SKILL.md` | Operate-mode UX review; token discipline; per-screen role-restricted visibility |
| `frontend-design` | `~/.agents/skills/frontend-design/SKILL.md` | Honest visual authority; no defaults; the rollout's "no gradient" rule |
| `design-guide` | `~/.agents/skills/design-guide/SKILL.md` | Paperclip system comparison; the project uses its OWN tokens, not Paperclip's; this skill is referenced for the tokens-as-source-of-truth discipline |
| `work-unit-commits` | `~/.config/opencode/skills/work-unit-commits/SKILL.md` | Commit-by-work-unit rule; per-PR scope discipline |
| `chained-pr` | `~/.config/opencode/skills/chained-pr/SKILL.md` | 400-line review budget; stacked-to-main chain strategy |
| `playwright-cli` | `~/.agents/skills/playwright-cli/SKILL.md` | Visual verification gate (1440x900 + 390x844) per module |
| `vue-best-practices` | `<repo>/.agents/skills/vue-best-practices/SKILL.md` | Composition API + `<script setup>` discipline; component split triggers |

### 10.3 Standing guard rails (inherited from the baseline)

The proposal inherits these from the proven language — the rollout is NOT authorised to relax any of them:

1. `tokens.js` is the only source of truth for tokens. Tailwind config reads from it. Build script reads from it.
2. `systemBackground` (`#ffffff`) is pinned. The canvas lives at `canvas = secondaryBackground = #F2F2F7`. Mutating `systemBackground` would repaint all 17 modules.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`. Pure black on near-white is the cheap-looking defect being fixed.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings` value is `"tnum" 1, "lnum" 1`, NOT the literal `tabular-nums` utility name.
7. `<script setup>` Composition API only; NO Options API for new code.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only; NEVER npm/yarn.
10. Code in English; conversation in Spanish (Peru).

---

## 11. What This Proposal Does NOT Do

- Does NOT redesign any module — it ROLLOUT the proven language.
- Does NOT add new tokens, primitives, or components (except `<UiStatusBadge>` per OQ#5).
- Does NOT add dark mode.
- Does NOT add gradients anywhere.
- Does NOT touch the backend (no controller, no migration, no listener).
- Does NOT relax any standing guard rail from §10.3.
- Does NOT introduce `<style scoped>` blocks (or carry them as grandfathered).
- Does NOT touch `<script>` blocks in any module — UI changes are template-level class-string replacement only.
- Does NOT override FullCalendar internals (`.fc-event`, `.fc-daygrid`, `.fc-timegrid`).
- Does NOT install new animation dependencies (no motion-v, no @vueuse/motion, no GSAP).
- Does NOT add a `text-numeric-fade` Tailwind variant.
- Does NOT add per-KPI sparklines.
- Does NOT deprecate the `ui-redesign-apple-claude-2026-08` change formally — it stays abandoned; this rollout doesn't acknowledge it.

---

## 12. Pending items NOT blocking implementation

- **OQ#3 — Settings/branches + Settings/payment-methods:** OUT of scope by default. PR11 exists conditionally. User can opt in during spec review.
- **OQ#4 — Two-tone numerals (D12 REVERSIBLE):** stays rejected. No override arrived between slice close and this proposal.
- **Vertical-slice open items 1 (per-KPI sparkline), 2 (two-tone numerals), 3 (comparator label clip), 4 (spec wording nit), 5 (stale spec details):** deferred to their own follow-up change. NOT this rollout's scope.

---

*End of proposal.*
