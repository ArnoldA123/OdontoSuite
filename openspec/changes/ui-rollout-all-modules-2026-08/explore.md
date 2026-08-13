# Explore: ui-rollout-all-modules-2026-08

> SDD phase: `sdd-explore`. Read-only; no proposal, no tasks, no source edits.
> All technical artifacts in English. Conversation language with the user remains Spanish (Peru).

---

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Date | 2026-08-11 |
| Phase | explore (1 of 6) |
| Author | `sdd-explore` sub-agent |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/explore`) |
| Delivery strategy (cached) | `auto-chain` — chained PRs auto-activate if `sdd-tasks` flags high risk; do NOT re-ask |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` — forward to apply/verify prompts |
| Vertical slice | `ui-premium-microdetail-2026-08` (closed 2026-08-11, 5 stacked PRs, PASS WITH WARNINGS) |
| Known-bad alternative | `ui-redesign-apple-claude-2026-08` (stalled at `apply-progress`, mix of Apple + Claude/terracotta/Newsreader). DO NOT extend. |

### Preflight snapshot (verbatim from session preflight)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached            # ask only when chained PRs actually trigger
strict_tdd: true
```

### Proven language status

The Apple-only language landed in 5 stacked PRs (#7 #5 #6 #8 #9) and is now the baseline:

- PR1 foundation (`#7`): tokens + build script + 2 PHPUnit invariants.
- PR2 primitives (`#5`): press / hover / focus / `ease-ios` adoption across 10 primitives.
- PR3 backend (`#6`): additive `data.comparisons` block on `/api/dashboard/stats`.
- PR4 dashboard (`#8`): fixed-slot KPI anatomy, single optical weight on topbar, keyhint chips on quick-actions.
- PR5 polish (`#9`): Login placeholders, helper-text removal, primary-button shadow + inner top highlight, hero scrim, 404 hero radius + hairline, sidebar group headers.

Closed: 137 passed / 1055 assertions (Unit `--filter=DesignSystem|UiRefresh`); 15 passed / 108 assertions (DashboardComparisonTest, MySQL).

---

## 1. Baseline — Proven Language Recap

### 1.1 Tokens added in PR1 (token foundation)

Read `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` D1–D16 + `resources/js/design-system/tokens.js`.

| Token group | Key | Value | Emitted as |
|---|---|---|---|
| Canvas | `colors.background.canvas` | `#F2F2F7` (alias of `secondaryBackground`) | `--color-background-canvas` + semantic alias `--color-canvas` |
| Hairline | `colors.border.hairline` | `rgba(60, 60, 67, 0.12)` (iOS separator opacity) | `--color-hairline` |
| Elevation | `elevation` (5 rungs) | `none`; rungs 1..4 use `rgba(60, 60, 67, α)`, two-layer from rung 2 up | `--elevation-0..4` |
| Radius (nested) | `radius.cardLg = 16px`; `radius.control = 8px` | KPI card / input | `--radius-card-lg`, `--radius-control` (camelCase → kebab via `toKebab()`) |
| Duration | `motion.duration = { fast: 120ms, normal: 200ms, slow: 320ms }` | Exactly 3 keys; `instant` and `spring` are DEAD, dropped | `--motion-duration-fast\|normal\|slow` |
| Focus ring | `focusRing = { width: 3px, color: '#007AFF', alpha: 0.20, offset: 2px }` | Composed `0 0 0 3px rgba(0, 122, 255, 0.20)` | `--focus-ring-default` (+ parts `--focus-ring-width\|color\|alpha\|offset`) |
| Font features | `fontFeatures.tabularNums = '"tnum" 1, "lnum" 1'` | Valid CSS `font-feature-settings` value | `--font-features-tabular-nums` |
| Topbar (PR4) | `topbar.iconSize = 20px`; `topbar.iconWeight = 1.5`; `topbar.control = 20px`; `topbar.controlLg = 32px` | Single optical weight across WS dot / bell / avatar | `--topbar-icon-size\|weight\|control\|control-lg` |

Pinned and NOT to be touched by this rollout: `radius.ios`, `radius.modal`, `motion.response`, `motion.damping`, `motion.dampingBounce` (kept honest-unconsumed), `motion.stiffness`, the typography `fontSize` ramp, and the letterSpacing table.

### 1.2 Primitives tokenized in PR2 (interaction states)

Per `tests/Unit/DesignSystem/PrimitivePressTest.php`, the press / focus / `ease-ios` rollout covered these 10 primitives:

`Card`, `Button`, `Input`, `Badge`, `Avatar`, `Modal`, `Sheet`, `ConfirmDialog`, `Toast`, `Select`.

The 11th UI primitive left out of the press test but still relevant: `EmptyState`, `LoadingSpinner`, `Skeleton` (no-press decorations, no transform needed).

Mechanism per primitive (R10 ruling):

- **Press** = pure CSS `:active` transform. Card keeps `scale(0.98)`. Button keeps `translateY(0)` (the existing `translateY(-1px)` hover→`translateY(0)` press is already a "press in" cue without scale). Avatar keeps `active:scale-95`.
- **Focus ring** = `box-shadow: var(--focus-ring-default);` on focusable elements (Card with `data-clickable="true"`, Input success path, Button still uses 2px `outline` for visual hierarchy).
- **Easing** = `transform var(--motion-duration-fast) var(--motion-easing-ios)`; colour washes stay on `ease-out`.
- **Reduced-motion fallback** = under `prefers-reduced-motion: reduce`, transform-based press/hover collapses to opacity or colour change of at most 200ms. Feedback survives; movement goes.

### 1.3 Screen-level scope delivered (PR4 + PR5)

- **Login** (`resources/js/modules/auth/LoginPage.vue`): placeholder text on inputs (G6), helper-text removal (G7), password reveal repositioned `right: 12px` (G12), primary-button `box-shadow: var(--elevation-3)` + inner top highlight `inset 0 1px 0 rgba(255,255,255,0.30)` (G8), hero scrim `linear-gradient(180deg, rgba(60,60,67,0.05) 0%, rgba(60,60,67,0.55) 100%)` + eyebrow opacity 1.0 (G9). LoginPage CSS block uses `background: var(--color-canvas)` on the page surface, `border-radius: var(--radius-card-lg)` on the form card, `var(--color-hairline)` on the input border. Tokenised in the file: 7 `var(--*)` references (grep verified).
- **Dashboard** (`resources/js/modules/dashboard/DashboardPage.vue`): 5 KPI cards rebuilt with fixed-slot layout (16/48/24/16 px), optional delta chip driven by `data.comparisons[key].delta_label`, greeting reduced from `text-2xl font-semibold` to `text-lg font-medium text-theme-secondary` (G11), quick-action `data-keyhint` chips (G4), today-appointments empty state uses `<EmptyState>` primitive (G5), topbar WS dot / bell / avatar share one optical weight via `topbar.iconSize/iconWeight` (G2). KPI numbers use `tabular-nums` Tailwind utility. Each of the 5 KPI cards references `--elevation-2` and `--color-hairline` via inline `:style` (5 occurrences grep verified).
- **404** (`resources/js/modules/errors/NotFoundPage.vue`): hero `border-radius: var(--radius-card-lg)`, `border: 1px solid var(--color-hairline)`, `background: var(--color-canvas)` on the page surface (3 `var(--*)` refs).
- **AppLayout** (`resources/js/components/layout/AppLayout.vue`): route-aware canvas surface via `isCanvasRoute = computed(...)` against `canvasRoutes = ['/dashboard', '/login', '/404']`. Sidebar group headers `<div class="px-6 py-2 text-[11px] uppercase tracking-[0.12em] text-systemGray-500">` before "Operaciones" (Pacientes) and "Configuración" (Sucursales). Additive only — labels and order frozen.

### 1.4 Test gate (PHPUnit)

The roll-out inherits these invariants. Any new screen work MUST keep them green and the design token / primitive change pattern they enforce:

- `tests/Unit/DesignSystem/TokensModuleTest.php` — canvas, hairline, duration, focus-ring, font-features, elevation, radius.cardLg, radius.control present in `tokens.js`.
- `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` — generated CSS contains `--color-canvas`, `--color-hairline` (no `--color-hairline-hairline` double-prefix), `--radius-card-lg`, `--radius-control`, `--motion-duration-fast|normal|slow`, `--focus-ring-default`, `--elevation-0..4`, `--font-features-tabular-nums`. No new `#RRGGBB` literal outside `tokens.colors`.
- `tests/Unit/DesignSystem/PrimitivePressTest.php` — 10 primitives consume `var(--focus-ring-default)`, use `transform var(--motion-duration-fast) var(--motion-easing-ios)`.
- `tests/Unit/DesignSystem/DashboardAppShellTest.php` — dashboard uses `bg-canvas`, KPI cards use `--color-hairline` and `--elevation-2`, 5 `tabular-nums` stat-card numbers.
- `tests/Unit/DesignSystem/LoginPageRenderTest.php` — Login uses `--elevation-3`, `--radius-card-lg`; 404 uses `--radius-card-lg` + `--color-hairline`.

These tests are the gate. The vertical-slice change already proved the pattern is enforceable.

### 1.5 Visual evidence in repo

| Evidence | Location | Notes |
|---|---|---|
| Screenshots | `.playwright-cli/screenshots-pr3/` | 4 PNGs: `login-1440x900.png` (608 KB), `login-390x844.png` (124 KB), `notfound-1440x900.png` (202 KB), `notfound-390x844.png` (69 KB). Desktop + mobile per screen, both exemplar screens. NO `ui-audit-*.png` files in repo root — the visual evidence is these PR3 screenshots. |
| Playwright snapshots | `.playwright-cli/page-*.yml` | ~85 YAML snapshots from PR3 visual verification. |
| Git history | `git log --oneline -10` on `tokens.js` and the four exemplar screens | 5 commits dated 2026-08-10/11: PR1 tokens, PR2 primitives, PR4 dashboard rebuild, PR5 polish, plus the iOS clinical token refresh + Newsreader removal (541faae). |

No `ui-audit-*.png` at repo root — flagged for the proposal phase so they don't search in vain.

---

## 2. Module Inventory

The frontend has **17 domain modules** per AGENTS.md §5 + 2 auxiliary (auth, errors) that were the vertical-slice target. The `resources/js/modules/` directory actually lists **19 directories** (`auth`, `errors`, `settings` are auxiliary; `procedure-catalog` houses `ProcedureStatsPage` at the same route family — see §2.17).

The 17 modules from AGENTS.md §5 (the inventory the user requested):

| # | Module | Path | Roles | Primary route(s) |
|---|---|---|---|---|
| 1 | Dashboard | `modules/dashboard/` | all | `/dashboard` (polished in vertical slice) |
| 2 | Calendario | `modules/appointments/` | all clínicos | `/calendar` |
| 3 | Pacientes | `modules/patients/` | all | `/patients` |
| 4 | Profesionales | `modules/professionals/` | admin | `/professionals` |
| 5 | Ambientes | `modules/environments/` | admin | `/environments` |
| 6 | Tipos de cita | `modules/appointment-types/` | admin | `/appointment-types` |
| 7 | Caja | `modules/cash-register/` | admin, finanzas, recep | `/cash-register`, `/cash-register/ready-to-bill` |
| 8 | BI | `modules/business-intelligence/` | admin, finanzas | `/business-intelligence` |
| 9 | Planes de tratamiento | `modules/treatment-plans/` | clínicos | `/treatment-plans` |
| 10 | Presupuestos | `modules/quotations/` | admin, finanzas, odonto, implant | `/quotations` |
| 11 | Historias clínicas | `modules/medical-records/` | clínicos | `/medical-records` |
| 12 | Registros especialidad | `modules/specialty-records/` | clínicos | `/specialty-records` |
| 13 | Análisis IA | `modules/ai-analysis/` | clínicos | `/ai-analysis` |
| 14 | Catálogo procedimientos | `modules/procedure-catalog/` | admin | `/procedure-catalog`, `/procedure-catalog/:id` |
| 15 | Mis procedimientos | `modules/my-procedures/` | clínicos | `/my-procedures` |
| 16 | Recepción procedimientos | `modules/reception-procedures/` | recep | `/reception-procedures` |
| 17 | Estadísticas catálogo | `modules/procedure-catalog/ProcedureStatsPage.vue` | admin, finanzas | `/procedure-stats` (mounted under procedure-catalog dir) |

Auxiliary directories (not in the 17, but on disk):

- `modules/auth/` — `LoginPage.vue`, `ForgotPasswordModal.vue`, `ResetPasswordModal.vue`. Polished in vertical slice.
- `modules/errors/` — `NotFoundPage.vue`. Polished in vertical slice.
- `modules/settings/` — `branches/` (`BranchesPage.vue`, `BranchFormModal.vue`) and `payment-methods/`. Multi-tenant infrastructure, not listed in AGENTS.md §5. **Out of scope for this rollout** unless the proposal phase explicitly extends it.

### Per-module API surface (rough, for blast-radius thinking)

| Module | Key controllers | Key endpoints | Composable |
|---|---|---|---|
| Dashboard | `DashboardController` | `GET /api/dashboard/stats` (with `comparisons`) | `useAuth`, `usePermissions`, `useCashRegister`, `useEcho` |
| Calendario | `AppointmentController` | `GET /api/appointments`, `POST /api/appointments`, `PATCH /api/appointments/{id}/status` | `useConsultation` |
| Pacientes | `PatientController` | `GET /api/patients`, `POST /api/patients`, `GET /api/patients/{id}` | (per-feature) |
| Profesionales | `ProfessionalController` | `GET /api/professionals` | — |
| Ambientes | `EnvironmentController` | `GET /api/environments` | — |
| Tipos de cita | `AppointmentTypeController` | `GET /api/appointment-types` | — |
| Caja | `CashRegisterController`, `CashSessionController`, `TransactionController` | many (`/api/cash-sessions`, `/api/cash-movements`, `/api/transactions`, `/api/payments/*`) | `useCashRegister`, `useTransactions`, `useMercadoPago` |
| BI | `BusinessIntelligenceController`, `ProcedureStatsController` | `GET /api/business-intelligence/*`, `GET /api/admin/procedure-stats` | (per-report) |
| Planes | `TreatmentPlanController` | `GET /api/treatment-plans`, `POST /api/treatment-plans` | `useTreatmentPlans` |
| Presupuestos | `QuotationController` | `GET /api/quotations`, `POST /api/quotations/{id}/approve`, `/reject` | `useQuotations` |
| Historias clínicas | `MedicalRecordController` | `GET /api/medical-records`, `POST /api/medical-records/{id}/odontogram` | `useMedicalRecords` |
| Registros esp. | `SpecialtyRecordController` | `GET /api/specialty-records` | `useSpecialtyRecords` |
| Análisis IA | `AiAnalysisController` | `POST /api/ai-analysis`, `GET /api/ai-analysis/{id}` | `useAiAnalysis` |
| Catálogo | `ProcedureCatalogController` | CRUD + `POST /api/admin/procedure-catalog/import`, `/translations`, `/versions` | `useProcedureCatalog`, `useProcedureFavorites` |
| Mis procedimientos | (uses Catálogo + favorites) | `GET /api/my-procedures` | `useProcedureFavorites` |
| Recepción | (uses Catálogo) | `GET /api/reception-procedures` | — |
| Estadísticas cat. | `ProcedureStatsController` | `GET /api/admin/procedure-stats` | — |

---

## 3. Current Visual State Audit (with evidence)

Method: sampled 2-3 representative `.vue` files per non-vertical-slice module; grep'd for legacy token references (`border-theme`, `bg-success-*`, `text-accent`, `bg-cream-*`, `bg-ink-*`, `bg-neutral-*`, `bg-primary-*`, `focus:ring-primary-500 focus:border-accent`); grep'd for proven-token references (`var(--color-canvas)`, `var(--color-hairline)`, `var(--focus-ring-default)`, `var(--elevation-*)`, `var(--radius-card-lg)`, `var(--motion-duration-*)`).

### 3.1 Grep summary (full count in `.playwright-cli/` and modules)

**Proven-token references found in the entire codebase (excluding the previous change's archived tests):**

- 11 UI primitives — `Avatar`, `Badge`, `Card`, `Button`, `Input`, `Modal`, `Select`, `Sheet`, `Toast` — all consume `var(--focus-ring-default)`, `var(--motion-duration-fast|normal)`, `var(--motion-easing-ios)`. `Avatar` + `Badge` + `Card` + `Button` also consume `var(--elevation-1)`.
- 3 exemplar pages — `LoginPage.vue` (7 `var(--*)` refs), `DashboardPage.vue` (5 `--elevation-2` + 5 `--color-hairline` for KPI cards), `NotFoundPage.vue` (3 refs).
- 0 other modules — `patients`, `appointments/CalendarPage`, `cash-register/*`, `quotations/*`, `treatment-plans`, `medical-records`, `specialty-records`, `professionals`, `environments`, `appointment-types`, `procedure-catalog`, `procedure-stats`, `ai-analysis`, `my-procedures`, `reception-procedures` — NONE consume the proven tokens.

**Legacy-token references found across all modules (non-vertical-slice):**

- `border-theme` / `border-theme-light` — pervasive (search found 100+ matches per module in cash-register, quotations, treatment-plans, ai-analysis, appointments). Semantic alias for `--color-separator-separator` that the previous `ui-refresh-apple-clinical` change kept so old classes keep resolving. The opacity of `border-theme` is the opaque `#c6c6c8` — not the iOS hairline.
- `bg-success-100` / `bg-success-500` / `bg-success-600` / `bg-success-700` / `text-success-700` — deprecated alias for systemGreen ramps. Used heavily in CashRegisterPage, CashReports, TransactionList, PendingPaymentsList, CalendarPage status pills, Quotations status badges.
- `bg-warning-100` / `text-warning-700` — deprecated alias for systemYellow ramps.
- `bg-error-100` / `text-error-700` / `bg-error-600` — deprecated alias for systemRed ramps.
- `text-accent` / `hover:text-primary-700` / `bg-primary-50` / `bg-primary-100` / `bg-primary-200` — deprecated alias for systemBlue ramps. Every module uses these for primary actions and link text.
- `bg-theme-surface` / `bg-theme-surface-elevated` — semantic aliases for `#f2f2f7` and `#ffffff`. These are the correct values but the modules use them WITHOUT the canvas separator on the page surface — so cards and page are both white (`#ffffff`) and cards read as outlines, not as objects lifted off a canvas.
- `focus:ring-primary-500 focus:border-accent` — input focus pattern using deprecated alias ramps, NOT the tokenised `var(--focus-ring-default)`. Every form in every module uses this.
- `rounded-lg` — generic Tailwind 8px radius; the proven language uses `--radius-control` (8px), `--radius-ios` (10px), `--radius-modal` (14px), `--radius-card-lg` (16px).

### 3.2 Per-module visual state

| # | Module | Sample file(s) | Visual state |
|---|---|---|---|
| 1 | Dashboard | `DashboardPage.vue` | **POLISHED.** Tokenised. KPI rebuild + topbar single weight + keyhint chips + EmptyState. Status: done. Excluded from rollout scope. |
| 2 | Calendario | `CalendarPage.vue` (29.7 KB), `ConsultationWizard.vue` | Legacy. Uses `bg-success-100`, `text-success-700`, `border-theme`, `text-accent`, `bg-primary-50`, `focus:ring-primary-500`. Status pills computed via inline string `bg-success-badge` / `bg-warning-badge` / `bg-danger-badge`. FullCalendar component (third-party) is its own scope. ConsultationWizard is form-heavy (50+ inputs) with `border-theme` everywhere. |
| 3 | Pacientes | `PatientsPage.vue` (44.5 KB), `PatientDetailPage.vue` (53.4 KB) | Legacy. `bg-success-badge` / `bg-danger-badge` status pills, `text-accent hover:text-primary-700`, `border-theme` table dividers, `bg-theme-surface-elevated rounded-2xl border border-theme p-4 hover:shadow-lg transition-all duration-200` mobile cards. Heavy table + detail page. |
| 4 | Profesionales | `ProfessionalsPage.vue` (23.0 KB), `ProfessionalDetailPage.vue` | Legacy. Same patterns as Pacientes. |
| 5 | Ambientes | `EnvironmentsPage.vue` (19.7 KB), `EnvironmentDetailPage.vue` | Legacy. |
| 6 | Tipos de cita | `AppointmentTypesPage.vue` (21.5 KB), `AppointmentTypeDetailPage.vue` | Legacy. `bg-success-100 text-success-700` / `bg-error-100 text-error-700` status pills. |
| 7 | Caja | `CashRegisterPage.vue` (19.0 KB), `ReadyToBillPage.vue` (10.2 KB), `components/CashReports.vue` (14.6 KB), `components/TransactionList.vue`, `components/TransactionModal.vue`, `components/CloseCashModal.vue`, `components/OpenCashModal.vue`, `components/MovementList.vue`, `components/MovementModal.vue`, `components/PaymentModal.vue` (22.3 KB), `components/PendingPaymentsList.vue`, `components/SessionList.vue`, `components/MercadoPagoCheckout.vue` | **Most legacy-heavy module.** 11 components. Every component uses `border-theme`, `focus:ring-primary-500 focus:border-accent`, `bg-theme-surface-elevated`, `text-accent`. Status badges inline. Heavy use of inline `@apply` blocks in `<style scoped>`. Real-time via `useCashRegister` channel `cash-register`. |
| 8 | BI | `BusinessIntelligencePage.vue` (32.5 KB) | Legacy. Chart.js loaded dynamically (`const { Chart, registerables } = await import('chart.js')`). Filters card uses `border-theme` + `focus:ring-primary-500 focus:border-accent`. Single-page module. |
| 9 | Planes de tratamiento | `TreatmentPlansPage.vue` (15.5 KB), `components/CreatePatientInline.vue`, `components/TreatmentPlanModal.vue` | Legacy. Inline `@keyframes pulse` + `animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;` in scoped styles. Inline `@keyframes spin` in modal. Heavy `border-theme` + `focus:ring-primary-500` pattern. |
| 10 | Presupuestos | `QuotationsPage.vue` (9.8 KB), `components/QuotationCard.vue`, `components/QuotationModal.vue`, `components/QuotationDetail.vue`, `components/QuotationStatusBadge.vue`, `components/QuotationApprovalModal.vue` | Legacy. 6 components. Status pills via inline `bg-primary-100 text-primary-700` / `bg-success-100 text-success-700` / `bg-error-100 text-error-700`. QuotationStatusBadge already extracts the pattern — could be the template for a tokenised `StatusBadge`. |
| 11 | Historias clínicas | `MedicalRecordsPage.vue` (13.8 KB) + components/ | Legacy. |
| 12 | Registros esp. | `SpecialtyRecordsPage.vue` (11.7 KB) + components/ | Legacy. |
| 13 | Análisis IA | `AiAnalysisPage.vue` (21.0 KB), `components/AnalyzingModal.vue` | Legacy. Heavy `@apply` blocks. `bg-success-100`, `bg-warning-100`, `bg-accent`. Inline `@keyframes spin 3s linear infinite;` in AnalyzingModal. |
| 14 | Catálogo | `ProcedureCatalogPage.vue` (13.2 KB), `ProcedureCatalogDetailPage.vue`, `ProcedureCatalogFormModal.vue` | Legacy. |
| 15 | Mis procedimientos | `MyProceduresPage.vue` (8.0 KB) | Legacy. Smallest module after Recepción. |
| 16 | Recepción | `ReceptionProceduresPage.vue` (5.2 KB) | Legacy. Smallest module. |
| 17 | Estadísticas cat. | `ProcedureStatsPage.vue` (6.4 KB) | Legacy. Small. |
| aux | Settings (branches) | `BranchesPage.vue` (35.4 KB) | Legacy. Already has `hover-lift` utility. Out of AGENTS.md §5 inventory — flag for proposal phase. |

### 3.3 Dangling-token check

`grep -r "var(--color-canvas)\|var(--color-hairline)\|var(--elevation-1)\|var(--radius-card-lg)\|var(--radius-control)\|var(--motion-duration-" resources/js resources/css` — 70 matches, all in 11 primitives + 3 exemplar pages. **No dangling references** in the codebase; the previous change did not introduce CSS variables that fail to resolve.

The `GeneratedTokensCssTest` parity test (already passing per the previous change) is the standing guard. No extension needed for the rollout — the gate is already wired.

---

## 4. Gap Analysis — Tokens, Primitives, Components

### 4.1 Token coverage

| Need | Existing token? | Notes |
|---|---|---|
| Page surface (canvas vs surface separation) | `bg-canvas` / `var(--color-canvas)` ✅ | Currently routed to 3 screens (`/dashboard`, `/login`, `/404`). Rollout must extend `canvasRoutes` in `AppLayout.vue` to cover all module routes. |
| Card / input border | `var(--color-hairline)` ✅ | Need to replace `border-theme` with `border-[color:var(--color-hairline)]` (or a new `border-hairline` Tailwind utility). The hex-parity test still passes because the value is `rgba(...)`, not a `#RRGGBB`. |
| Card / button shadow | `var(--elevation-1..4)` ✅ | Need to replace `shadow-*` Tailwind utilities with the elevation tokens. Subtle / soft / medium / large / elevated are pure-black rgba (the previous `shadow.*` ramps) — they coexist with the new tinted elevation rungs. |
| Input / button focus ring | `var(--focus-ring-default)` ✅ | Need to replace `focus:ring-primary-500 focus:border-accent` everywhere. |
| Numeric craft (KPI numbers, table numerics) | `tabular-nums` Tailwind utility (via `--font-features-tabular-nums`) ✅ | Already used in Dashboard. Should be applied to currency tables in CashRegister, QuotationDetail, ProcedureStats, BranchesPage counters. |
| Press feedback | `:active` transform + `transform var(--motion-duration-fast) var(--motion-easing-ios)` ✅ | Built into the 10 primitives. Module pages don't need extra work — they already use `<UiButton>`, `<UiCard>`, `<UiInput>`. |
| Loading spinner motion | `animate-pulse-subtle` Tailwind utility ✅ | Used in CalendarPage and BIPage for the "En vivo" status pill. |
| Reduced motion | `@media (prefers-reduced-motion: reduce)` per primitive ✅ | Built into the 10 primitives. Module pages don't need extra work. |

**Token extensions needed: NONE.** The proven language already covers every need. The rollout is mechanical replacement of legacy alias classes with the proven tokens.

### 4.2 Primitive coverage

30 primitives exist (`resources/js/components/ui/`). Mapping to the rollout modules' UI patterns:

| Module pattern | Primitive | Status |
|---|---|---|
| Page shell | `<AppLayout>`, `<PageHeader>` | Already there. |
| KPI / metric card | `<UiCard>` + `tabular-nums` | Sufficient. |
| Table | `<table>` raw or via `<UiDataTable>` (13.7 KB) | `UiDataTable` exists but is not widely used — many modules roll their own `<table>`. **Out of scope for rollout** unless a specific module clearly needs it. |
| Form | `<UiInput>`, `<UiSelect>`, `<UiTextarea>` | Sufficient. |
| Modal | `<UiModal>` (8.0 KB), `<UiSheet>` (10.6 KB), `<UiConfirmDialog>` (2.6 KB) | Sufficient. |
| Toast | `<UiToast>`, `useToast` | Sufficient. |
| Empty / loading | `<UiEmptyState>` (5.0 KB), `<UiLoadingSpinner>` (3.0 KB), `<UiSkeleton>` (3.4 KB) | Sufficient. |
| Filter bar | `<UiFilterBar>` (1.7 KB) | Exists, rarely used. |
| Pagination | `<UiPagination>` (6.6 KB), `<PaginationComponent>` (6.2 KB — duplicate) | Two primitives for the same need. **Pre-existing duplication**, not introduced by this rollout. |
| Status pill | Inline classes like `bg-success-100 text-success-700` in many modules | The vertical slice added `cashStatusBadgeClass` inline on Dashboard. **Could extract** a `<UiStatusBadge>` primitive (mirroring the structure of `QuotationStatusBadge` and `TransactionList.statusClasses`), but **not required** by the rollout. Defer to proposal phase. |
| Tab bar | `<UiTabs>` (7.2 KB) | Exists. |
| Currency input | `<UiCurrencyInput>` (6.5 KB) | Exists. |
| Rich text / odontogram | `<UiRichTextEditor>` (7.9 KB), `<UiToothSelector>` (6.7 KB) | Specialized. |
| File upload | `<UiFileUpload>` (4.3 KB), `<UiFileUploader>` (8.7 KB) | Sufficient. |
| Patient / procedure selectors | `<UiPatientSelector>` (6.8 KB), `<UiProcedureSelector>` (5.7 KB), `<UiTreatmentPlanSelector>` (6.7 KB) | Specialized. |
| Breadcrumbs | `<UiBreadcrumbs>` (8.9 KB) | Exists, used in PageHeader. |
| Avatar | `<UiAvatar>` (7.3 KB) | Tokenised in PR2. |
| Receipt preview | `<UiReceiptPreview>` (11.1 KB) | Specialized. |
| Progress bar | `<UiProgressBar>` (2.1 KB) | Exists. |
| Radio / pagination | `<UiRadioGroup>` (1.7 KB), `<UiPagination>` (6.6 KB) | Sufficient. |
| Notification / toast | `<UiNotificationToast>`, `useToast`, `useNotifications`, `<NotificationCenter>` | Existing. |

**New primitives required: NONE.** The rollout is replacement of legacy class strings with tokenised equivalents at the module-page level.

**Possible minor extraction (proposal-phase decision):** a `<UiStatusBadge>` primitive that consolidates the status-pill pattern across modules. The vertical slice didn't extract it because DashboardPage had only 3 cash states; the rollout touches ~6 modules with status badges. The duplication threshold (>=2 places) is met. But this is a Phase-2 concern, not Phase-1.

### 4.3 Cross-cutting components

| Need | Current state | Rollout scope |
|---|---|---|
| Sidebar | Tokenised in AppLayout (group headers, canvas on /dashboard, /login, /404). Sidebar still uses legacy classes for nav items. | Out of scope — sidebar is shared chrome, not per-module. Extend `canvasRoutes` only. |
| Topbar | Tokenised (single optical weight via `topbar.*`). | Same as sidebar. |
| Breadcrumbs | `<UiBreadcrumbs>` used in PageHeader. | Out of scope. |
| Empty state | `<UiEmptyState>` primitive. | Replace ad-hoc empty-state markup if found in module pages. |
| Error state | `useErrorHandler` composable + `<UiToast>`. | Ensure modules consume the composable; no UI change. |
| Mobile menu | `<MobileMenu>` + `<Sheet>` wrapper. | Out of scope. |
| Page header | `<PageHeader>` primitive. | Sufficient. |

**Cross-cutting scope of the rollout: extend `canvasRoutes` in `AppLayout.vue` to include every polished module route.** That single edit is what wires the proven canvas surface onto every screen.

### 4.4 Animation / motion

The proven language covers most needs. Two specific module-level motion uses need explicit consideration:

- **Chart.js (BI module)** — Chart.js animations are configured per-instance via `options.animation.duration`. The proven duration tokens (`var(--motion-duration-fast/normal/slow)`) are CSS variables; Chart.js wants JS numbers. The rollout must map `fast → 120, normal → 200, slow → 320` once at module init time (or read from a JS-side `tokens.motion.duration`). NOT auto-applied.
- **FullCalendar (CalendarPage)** — FullCalendar has its own animation config. The "En vivo" pulse uses `animate-pulse-subtle` (Tailwind utility), which is fine. FullCalendar event rendering uses CSS transforms internally; the proven `ease-ios` is not auto-applied. Out of scope unless a defect is observed.
- **WebSocket-driven re-renders (cash-register, appointments, patients, etc.)** — channels `cash-register`, `appointments`, `patients`, `treatment-plans`, `quotations`, `medical-records`, `specialty-records`, `procedure-catalog`. These re-render lists when a server event fires. The proven language has no opinion on list-reveal motion; Vue's default patch behaviour is fine. NO additional animation needed.
- **Inline `@keyframes pulse` / `@keyframes spin` in scoped styles** — `TreatmentPlansPage.vue` (line 519-522), `TreatmentPlanModal.vue` (line 805-807), `CashRegisterPage.vue` (line 638-641), `CreatePatientInline.vue` (line 261-263), `AnalyzingModal.vue` (line 64-67). These are generic Tailwind-equivalent keyframes that the rollout should consolidate on the existing `animate-pulse` / `animate-spin` Tailwind utilities (or extend `LoadingSpinner`). The Spanish/Peru `LoadingSpinner` is already there. **Action item: replace inline keyframes with the existing primitives.**

---

## 5. Risk + Dependency Mapping

### 5.1 Complexity tiers

| Tier | Modules | Rationale |
|---|---|---|
| **Tier 1 — low risk (small CRUD, mostly token replacement)** | Mis procedimientos, Recepción procedimientos, Estadísticas catálogo, Tipos de cita, Ambientes | Single page each; few legacy classes; status pills; small tables. |
| **Tier 2 — medium risk (CRUD with detail page + form modals)** | Profesionales, Planes de tratamiento, Historias clínicas, Registros especialidad, Catálogo procedimientos, Pacientes | 2 pages each (list + detail); modal forms; tables. |
| **Tier 3 — high risk (data-heavy, real-time, or 3rd-party widgets)** | Caja, BI, Análisis IA, Calendario, Presupuestos | Caja: 11 components + Reverb channel + MercadoPago. BI: Chart.js. Análisis IA: form-heavy modal flow. Calendario: FullCalendar + ConsultationWizard. Presupuestos: 6 components including QuotationModal/Detail/Approval. |

### 5.2 Module-specific risks

| Module | Risk | Mitigation |
|---|---|---|
| Caja | Real-time via `useCashRegister` channel `cash-register`. Refactor blast radius is large (11 components). Any UI change that triggers re-render storms must keep the existing 300ms debounce / throttle pattern. | Rollout in a chained slice; preserve `useCashRegister` reactivity contract; do not touch event listeners. |
| BI | Chart.js is loaded dynamically (`await import('chart.js')`). Chart options include `options.animation.duration` (a number, not a CSS variable). | Replace JS-side `animation: { duration: ... }` numbers with the JS-side `motion.duration` values at module init; verify with Playwright. |
| Calendario | FullCalendar is a third-party widget with its own CSS. FullCalendar's `.fc-event` class is not part of the design system. | Scope the rollout to surrounding chrome (header, controls, modals, status pills). Do NOT attempt to override FullCalendar internals. |
| Análisis IA | Heavy `@apply` blocks in scoped styles + `@keyframes spin` in AnalyzingModal. The `@apply` blocks will need to be converted to plain utility classes once the legacy tokens are removed (Tailwind purges unreferenced classes). | Verify each `@apply` against the surviving tokens; some may be obsolete. |
| Presupuestos | 6 components including `QuotationCard.vue` with a custom `@apply bg-theme-surface-elevated rounded-lg border border-theme shadow-sm hover-lift transition-shadow;`. The `hover-lift` utility is defined elsewhere. | Coordinate with `hover-lift` definition site; ensure it survives the swap. |
| ProcedureStats | Single page. References `text-green-600` (raw Tailwind, not even a deprecated alias). | Replace with `text-systemGreen-600` or `text-success-600` (alias). |
| All modules | The `border-theme` semantic alias uses `--color-separator-separator: #c6c6c8` — opaque, NOT the hairline. The audit identified this as the defect. | Replace `border-theme` → `border-[color:var(--color-hairline)]` (or add a `border-hairline` Tailwind utility that maps to the CSS var). |
| All modules | `bg-theme-surface-elevated` (= `#ffffff`) sits on a `bg-systemBackground` (= `#ffffff`) page → cards read as outlines. The canvas/surface separation only kicks in for routes in `canvasRoutes`. | Extend `canvasRoutes` to all module routes in `AppLayout.vue`. Coordinate with PR1 — this is a one-line additive change. |
| All modules with realtime | Reverb broadcasting regression risk. Any refactor that accidentally removes a `.listen(...)` call or `echo.leave(...)` in cleanup will silently break realtime. | The rollout is class-string replacement at template level; `<script>` blocks are not touched. No realtime regression risk if scope holds. |

### 5.3 Role-restricted visibility

Modules and their `role:` middleware (per AGENTS.md §5):

- `administrador` only: Profesionales, Ambientes, Tipos de cita, Catálogo procedimientos, Estadísticas catálogo.
- `administrador` + `finanzas`: Caja, BI, Presupuestos.
- `administrador` + `finanzas` + `recepcionista`: Caja.
- `administrador` + `finanzas` + `odontólogo` + `implantólogo`: Presupuestos.
- All clinical roles: Calendario, Historias clínicas, Registros especialidad, Planes de tratamiento, Análisis IA, Mis procedimientos.
- `recepcionista`: Recepción procedimientos.

Implication: the rollout will be visually verified by role. Each slice's Playwright sweep should log in as the canonical role per module (e.g. `admin@test.com` for Profesionales; `finanzas@test.com` for Caja) — credentials in `CREDENTIALS.md`.

### 5.4 TDD posture

`tests/Unit/Documentation/AgentsDocsSyncTest.php` (from the previous bugfix slice) plus the 5 DesignSystem PHPUnit tests are the standing gate. The rollout MUST extend:

- `tests/Unit/DesignSystem/DashboardAppShellTest.php` is module-specific. Add sibling tests like `PatientsAppShellTest`, `CashRegisterAppShellTest` for each polished module — or extract a generic `ModuleAppShellTestCase` that asserts per-route: `bg-canvas` is on the surface, key tables/forms reference `--color-hairline` + `--focus-ring-default`, numeric columns reference `tabular-nums`. The "test that pins an example instead of the rule" lesson from `archive-report.md` (process lesson #1) applies here: assert the rule, not the literal string.
- `tests/Unit/DesignSystem/PrimitivePressTest.php` should remain unchanged (primitives are already covered).
- `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` should remain unchanged (no new tokens).

### 5.5 `strict_tdd: true` implication for apply/verify

Forward to apply: every UI replacement MUST come with a test that proves the new behaviour. Forward to verify: every slice must demonstrate that the module's Playwright snapshot at 1440x900 + 390x844 matches the proven language (canvas surface, hairline borders, focus rings, KPI/counter numbers with `tabular-nums`).

---

## 6. Suggested PR Chain Ordering (for `sdd-tasks` — DO NOT write tasks here)

The user-facing rollout is too large for one PR (~16 modules × average 1-2 pages × ~15-30 KB each = easily > 4000 lines changed). Chained PRs are mandatory. Auto-chain is the cached strategy.

### 6.1 Rationale (sequencing principles)

1. **Pick a "second proving ground" first.** The vertical slice proved the language on Login (form-heavy) and Dashboard (KPI-heavy). The rollout should pick a module that exercises a DIFFERENT pattern, ideally one that's Tier 1 (low risk, fast feedback) but introduces at least one new pattern. **Recommendation: Estadísticas catálogo (`ProcedureStatsPage.vue`, ~6.4 KB).** Rationale:
   - Single page, table-heavy + 3 KPI cards.
   - Reuses the Dashboard KPI card anatomy.
   - Table numerics benefit from `tabular-nums`.
   - Small blast radius — proves the language on a 3rd screen without risk.
2. **Group modules that share a primitive/token extension.** Modules 2/3/4/5/6 (Pacientes, Profesionales, Ambientes, Tipos de cita, Catálogo) all share the "list + detail + status pills" pattern. They can ride a single PR if each module's diff stays small. **Recommendation: group by pattern, not by directory.**
3. **Charts and realtime last.** BI (Chart.js) and Caja (Reverb) need careful, isolated PRs because the runtime is harder to verify.
4. **Calendario split.** FullCalendar chrome + ConsultationWizard + CalendarPage — three sub-tasks. FullCalendar chrome is the lowest-risk (just surrounding header + status pills); ConsultationWizard is form-heavy (50+ inputs, `@apply` blocks); CalendarPage has the most legacy patterns.

### 6.2 Indicative slice ordering (10 slices, target ≤400 lines each)

| Slice | Modules | Theme | Why this order |
|---|---|---|---|
| 0 | `AppLayout.vue` only | Extend `canvasRoutes` to every polished module route. One-line additive change. | Wire the canvas surface onto all modules FIRST so every subsequent PR sees the proven surface when it lands. Required pre-requisite for all visual verification. |
| 1 | `ProcedureStatsPage.vue` (Estadísticas catálogo) | Tokenise table + 3 counters; reuse Dashboard KPI anatomy. | Second proving ground. Small. Fast feedback. Proves the pattern on a 3rd screen. |
| 2 | `QuotationsPage.vue` + 5 components | Tokenise status pills + tables + modal + detail. The `QuotationStatusBadge` already extracts the pattern — can become the template for `<UiStatusBadge>`. | Sets the status-badge precedent. |
| 3 | `ReceptionProceduresPage.vue` + `MyProceduresPage.vue` | Smallest two modules. Both are filter + grid. | Rinse the pattern on the smallest modules. |
| 4 | `ProcedureCatalogPage.vue` + `ProcedureCatalogDetailPage.vue` + `ProcedureCatalogFormModal.vue` | Admin module. CRUD + import modal + CSV upload. | Multi-component but bounded. |
| 5 | `AppointmentTypesPage.vue` + `AppointmentTypeDetailPage.vue` | Admin CRUD. | Bounded. |
| 6 | `EnvironmentsPage.vue` + `EnvironmentDetailPage.vue` | Admin CRUD. | Bounded. |
| 7 | `ProfessionalsPage.vue` + `ProfessionalDetailPage.vue` | Admin CRUD. | Bounded. |
| 8 | `PatientsPage.vue` + `PatientDetailPage.vue` | Largest CRUD module (44.5 + 53.4 KB). May need 2 PRs if >400 lines. | Largest single module; do late. |
| 9 | `MedicalRecordsPage.vue` + `SpecialtyRecordsPage.vue` + `TreatmentPlansPage.vue` + 2 components | Clinical modules. Forms + inline `@keyframes` to consolidate. | Consolidate the inline `@keyframes` here. |
| 10 | `CalendarPage.vue` (FullCalendar chrome only) + `ConsultationWizard.vue` | Highest-traffic clinical surface. | Mid-risk due to FullCalendar scope discipline. |
| 11 | `BusinessIntelligencePage.vue` | Chart.js config mapping (CSS-var → JS-number). Single page. | Isolated because of Chart.js. |
| 12 | `AiAnalysisPage.vue` + `AnalyzingModal.vue` | Heavy `@apply` blocks + `@keyframes spin`. | Consolidate the `@apply` rewriting here. |
| 13 | `CashRegisterPage.vue` + `ReadyToBillPage.vue` + 9 components | Largest module (11 components, Reverb channel). | Highest-risk; do last; chain slice mandatory if >400 lines. |

This is a 13-slice chain (excluding the prerequisite slice 0). Forecast: each slice ~150-350 lines of authored code (excluding generated tests). All under the 400-line budget. Chained PRs required per `auto-chain` strategy.

### 6.3 Parallel-merge candidates

Slices 5, 6, 7 (Appointment Types / Ambientes / Profesionales) are independent — they touch different files, different routes, no shared composable. Could merge as a stacked pair. The first lands, the second stacks. Per the chained-pr skill, each child PR targets the immediate parent branch in a Feature Branch Chain; keep the tracker PR draft until all three are merged.

### 6.4 Strict-ordering slices

- Slice 0 (canvasRoutes extension) MUST land before any visual verification.
- Slices 1, 2, 3 can land in any order after 0.
- Slice 13 (Caja) MUST be last; its blast radius is the largest.

---

## 7. Open Questions (for `sdd-propose` — NOT to be answered here)

These are decisions only the user can make. They are flagged for the proposal phase.

1. **Dark mode.** The previous `ui-redesign-apple-claude` change stalled because of an inconsistent palette + serif mix. The proven language is **light-only** by design (per `tokens.js` line 29: "The design system is light-only (no dark-mode media query)."). Is dark mode a v1 requirement of this rollout, or is it a deliberate "later" — to be deferred behind its own change once the light language lands everywhere?
2. **Accessibility level.** Apple guidance §14 enforces `prefers-reduced-motion`, `prefers-reduced-transparency`, `prefers-contrast` on primitives. The vertical slice honoured all three on Login/404. Should the rollout target WCAG 2.2 AA across every module, or only the existing primitives' contracts? (No code change required for the latter; the former requires per-form audits.)
3. **Settings / Branches / Payment Methods.** AGENTS.md §5 lists 17 modules, but `resources/js/modules/settings/` exists with `branches/` (35.4 KB `BranchesPage.vue`) and `payment-methods/` directories. Is the rollout intended to cover those, or are they deliberately out of scope as "infrastructure"?
4. **Two-tone numerals (D12 from vertical slice).** Marked REVERSIBLE, pending user override. Does the rollout add the `text-numeric-fade` Tailwind variant now, or leave the rejection in place?
5. **`<UiStatusBadge>` extraction.** The vertical slice didn't extract it because Dashboard had only 3 cash states. The rollout touches ~6 modules with status pills (Pacientes, Profesionales, Tipos de cita, Caja, Presupuestos, ProcedureCatalog). Should the rollout extract a generic `<UiStatusBadge>` primitive (mirroring `QuotationStatusBadge`), or keep the inline pattern?
6. **`hover-lift` utility.** Several modules use `hover-lift` (defined in `resources/css/utilities.css`). Is this a utility the rollout preserves, or does it get replaced by the proven `Card.vue` `:hover { transform: translateY(-2px) }` mechanism? Note: the proven Card primitive already ships `hover-lift` behaviour. Consolidate on the primitive.
7. **Pagination primitive duplication.** `<UiPagination>` (6.6 KB) and `<PaginationComponent>` (6.2 KB) both exist. The rollout uses both — should it consolidate on one (likely `<UiPagination>` as the "Ui*" prefix is the project convention per AGENTS.md §7)?
8. **Module-by-module screenshot verification.** The vertical slice verified 4 PNG screenshots at 1440x900 and 390x844. With 16 modules to roll out, the screenshot count would explode (~32 PNGs at ~200 KB each = ~6 MB). Is the rollout expected to ship per-module screenshots in `.playwright-cli/screenshots-{sliceN}/`, or only the highest-risk modules (Caja, BI, Calendario)?
9. **Inline `@apply` blocks.** `TreatmentPlansPage.vue`, `CashRegisterPage.vue`, `TreatmentPlanModal.vue`, `CreatePatientInline.vue`, `AnalyzingModal.vue` all use `<style scoped>` with `@apply`. The proven language forbids `<style scoped>` per `DashboardAppShellTest`. Should the rollout rewrite these to plain utility classes, or grandfather `<style scoped>` for the rollout (with a follow-up change to eliminate)?

---

## 8. References + File Inventory

### 8.1 Source artifacts read for this explore

| File | Why it matters |
|---|---|
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/proposal.md` | Defines intent and scope of the proven language — load-bearing reference for "what shipped". |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` | 303 lines of architecture decisions D1–D16 + Build Script Emission Plan + Screen-Level Scope G1–G13 + Slice Boundaries. The source of truth for every concrete value. |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/tasks.md` | Original task breakdown for the 5-PR vertical slice. Useful as the precedent for the chained-PR delivery model. |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/specs/premium-design-foundation/spec.md` | The delta spec promoted to `openspec/specs/premium-design-foundation/`. The capability the rollout inherits. |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/specs/dashboard-period-comparisons/` | Dashboard-specific delta spec. Independent of the rollout. |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` | Process lessons (3 defects from pinning examples instead of rules; sdd-spec / sdd-design parallel-execution risk). |
| `openspec/changes/ui-redesign-apple-claude-2026-08/exploration.md` | The stalled alternative. Confirms what to AVOID: cream + terracotta + Newsreader + Claude. DO NOT extend. |
| `openspec/specs/premium-design-foundation/spec.md` | The archived capability, now in `openspec/specs/`. Stable reference. |
| `openspec/config.yaml` | Preflight cache + strict TDD + pnpm-only + 400-line budget + CI MySQL. |
| `AGENTS.md` | Project context, stack, 17-module inventory (§5), commands (§3), conventions (§7), troubleshooting (§8). |
| `resources/js/design-system/tokens.js` | The proven token source-of-truth. Every concrete value the rollout consumes. |
| `resources/css/tokens.generated.css` | Generated CSS (369 lines). The output the rollout consumes. |
| `resources/js/components/layout/AppLayout.vue` | The `canvasRoutes` gate. Slice 0 of the rollout. |
| `resources/js/components/ui/{Card,Button,Input,Badge,Avatar,Modal,Sheet,ConfirmDialog,Toast,Select}.vue` | 10 tokenised primitives. Inherited by the rollout as-is. |
| `resources/js/components/ui/EmptyState.vue` | Exemplar of `<UiEmptyState>` consumption pattern. |
| `resources/js/modules/dashboard/DashboardPage.vue` | Reference screen. KPI card anatomy, comparison chip, today-appointments empty state, topbar single weight, quick-action keyhints. |
| `resources/js/modules/auth/LoginPage.vue` | Reference screen. Form polish, primary-button shadow + inner highlight, hero scrim. |
| `resources/js/modules/errors/NotFoundPage.vue` | Reference screen. Hero radius + hairline. |
| `resources/js/modules/{patients,appointments,cash-register,business-intelligence,professionals,quotations,treatment-plans,medical-records,specialty-records,ai-analysis,procedure-catalog,environments,appointment-types,my-procedures,reception-procedures}/**/*.vue` | 16 module directories. Sampled for visual state. |
| `tests/Unit/DesignSystem/{TokensModuleTest,GeneratedTokensCssTest,PrimitivePressTest,DashboardAppShellTest,LoginPageRenderTest,UseSpringMathTest}.php` | The PHPUnit test gate. The rollout extends `DashboardAppShellTest` (or extracts `ModuleAppShellTestCase`) for per-module coverage. |
| `tailwind.config.js` | Reads from `tokens.js`. Exposes `transitionDuration` from `motion.duration`, `borderRadius` from `radius`. No change needed for the rollout. |
| `scripts/build-tokens-css.mjs` | Generator for `resources/css/tokens.generated.css`. No change needed for the rollout. |
| `resources/js/composables/{useApi,useAuth,useCashRegister,useWebSocketNotifications,useEcho,useSpring,useSpring2D,useFormatters,useMercadoPago,useProcedureCatalog,useProcedureFavorites,useQuotations,useMedicalRecords,useSpecialtyRecords,useTransactions,useTreatmentPlans}.js` | 31 composables. Out of scope for the rollout (UI-only change), but consulted for blast-radius thinking. |
| `.playwright-cli/screenshots-pr3/{login,notfound}-{1440x900,390x844}.png` | Visual evidence the vertical slice shipped. |
| `.playwright-cli/page-*.yml` | Playwright snapshots from PR3 verification. ~85 YAML files. |

### 8.2 Files NOT read but referenced

| File | Reason for not reading |
|---|---|
| `openspec/changes/ui-redesign-apple-claude-2026-08/{proposal,spec,design,tasks,apply-progress}.md` | Sampled `exploration.md` only. The full file set is large and the user explicitly directed: "treat it as a known-bad alternative" — no need to absorb its details, only its intent. |
| `database/seeders/*.php`, `app/Http/Controllers/Api/*.php`, `routes/api.php` | Backend is out of scope. The rollout is frontend-only. |
| `database/migrations/*.php` | Out of scope. |
| `docs/mejoras/plan-mejoras-futuras-2026-06.md` | Already-closed plan. AGENTS.md §9 summarises. |

### 8.3 Test gates the rollout must keep green

Per `archive-report.md` and `tokens.js` line 33-39, every concrete value has at least one PHPUnit assertion. The rollout's reference tests are:

- `tests/Unit/DesignSystem/TokensModuleTest.php` — token source invariants.
- `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` — generated CSS invariants (including parity: no new `#xxxxxx` outside `tokens.colors`).
- `tests/Unit/DesignSystem/PrimitivePressTest.php` — 10 primitives consume `var(--focus-ring-default)` and `transform var(--motion-duration-fast) var(--motion-easing-ios)`.
- `tests/Unit/DesignSystem/DashboardAppShellTest.php` — Dashboard exemplars: `bg-canvas`, KPI hairline + elevation-2, `tabular-nums` on KPI numbers.
- `tests/Unit/DesignSystem/LoginPageRenderTest.php` — Login + 404 exemplars: `--elevation-3`, `--radius-card-lg`, `--color-hairline`.
- `tests/Feature/Modules/DashboardComparisonTest.php` — Dashboard API contract (NOT in rollout scope).
- `tests/Unit/Documentation/AgentsDocsSyncTest.php` — AGENTS.md invariants (NOT in rollout scope unless §5 inventory changes).

### 8.4 Standing guard rails

The proposal phase MUST inherit these from the proven language — the rollout is not authorised to relax any of them:

1. `tokens.js` is the only source of truth for tokens. Tailwind config reads from it. Build script reads from it.
2. `systemBackground` (`#ffffff`) is pinned. The canvas lives at `canvas = secondaryBackground = #F2F2F7`. Mutating systemBackground would repaint all 20 modules.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, not `rgba(0, 0, 0, α)`. Pure black on near-white is the cheap-looking defect being fixed.
4. Hairline is `rgba(60, 60, 67, 0.12)`, not `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, not a single value.
6. `font-feature-settings` value is `"tnum" 1, "lnum" 1`, not the literal `tabular-nums` utility name.
7. `motion.duration` is exactly `{ fast: 120ms, normal: 200ms, slow: 320ms }`. No `instant`. No `spring`.
8. `motion.dampingBounce` stays unconsumed (no momentum-driven gesture in this rollout).
9. `radius.cardLg` (16px) is for KPI cards and hero photos. `radius.control` (8px) is for inputs. Pinned `radius.ios` (10px) for cards/buttons/status chips. Pinned `radius.modal` (14px) for overlays. `lg`/`2xl`/`3xl` removed.
10. Light-only. No `prefers-color-scheme: dark` blocks. No gradients. No hand-written hex literals outside `tokens.colors`.
11. Information architecture frozen: nav labels + order, KPI labels + order, route slugs, form field names + order.
12. The "test pins an example instead of the rule" lesson from `archive-report.md` — assert the rule, not the literal string.

---

## End of Explore

Next phase: `sdd-propose`. Inputs from this artifact:

- The proven language is the baseline; do not invent new tokens.
- The rollout is mechanical replacement of legacy alias classes with proven tokens at the module-page level.
- The canvas surface (`bg-canvas` via `canvasRoutes` in `AppLayout.vue`) must be extended to every polished module route FIRST.
- The rollout needs a chained-PR delivery (12+ slices, target ≤400 lines each).
- The `auto-chain` strategy is the cached choice — `sdd-tasks` MUST NOT re-ask.
- The `strict_tdd: true` flag is forward — every UI replacement has a test.
- 9 open questions flagged for the proposal phase (dark mode, accessibility level, Settings scope, two-tone numerals, StatusBadge extraction, hover-lift consolidation, pagination duplicate, screenshot scope, `<style scoped>` grandfather clause).
- The known-bad alternative (`ui-redesign-apple-claude-2026-08`) is treated as evidence of what NOT to do — cream + terracotta + Newsreader + Claude is explicitly out.