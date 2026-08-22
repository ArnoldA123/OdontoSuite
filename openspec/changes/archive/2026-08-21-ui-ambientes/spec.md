# Spec: ambientes Category Delta — `ui-rollout-all-modules-2026-08`

> 2 chained PRs (PR-ambientes-01 + PR-ambientes-02). Includes the
> BLOCKING `canvasRoutes` detail-route fix (OQ-ambientes-01) and 2
> documented DLR-AMB-005 `<script>`-edit exceptions. Sibling delta of
> the global `design-language-rollout` spec; appends AMBIENTES-specific
> rows to the parent delta.

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | AMBIENTES (admin CRUD: list + detail + 3 inlined modals + audit log tab) |
| Date | 2026-08-21 |
| Phase | `spec` (3 of 6) — category slice |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/ambientes/spec`) |
| Parent spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` |
| Parent proposal | `openspec/changes/ui-rollout-all-modules-2026-08/categories/ambientes/proposal.md` |
| Parent explore | `openspec/changes/ui-rollout-all-modules-2026-08/categories/ambientes/explore.md` |
| Slices | `pr-ambientes-01-list-page-and-canvas-routes-detail-fix` + `pr-ambientes-02-detail-page-and-modals` |
| Delivery strategy | `auto-chain` (inherited; sub-PRs stack inside global PR4 `pr4-admin-crud-triplet`) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` (forward to apply/verify) |
| Total MUST rows | 15 (8 in PR-ambientes-01 + 7 in PR-ambientes-02) |
| Naming convention | `AMB-01-NNN` for PR-ambientes-01, `AMB-02-NNN` for PR-ambientes-02 |

### Relationship to parent spec

This spec does NOT modify the parent `design-language-rollout/spec.md`
rows directly. It is a sibling delta that adds AMBIENTES-specific rows.
The `DLR-CORE-*` rules and `DLR-MOD-005` module baseline apply to
AMBIENTES unmodified; the rows below add category-specific edges
(BLOCKING canvasRoutes detail-route fix, list primitive adoption,
status pill variant tokenisation, gradient removal, audit log card +
spinner + empty state, 9 raw form fields → 9 primitive migrations, 2
documented `<script>`-edit exceptions).

### Two documented DLR-AMB-005 `<script>`-edit exceptions

Both exceptions are 1-line mechanical renames with zero behavioural
drift on the data flow. They are the cheapest resolution vs. inline
v-if workarounds in the template that would duplicate logic.

- **`getStatusColor` → `getStatusVariant` rename** — the function name
  lies after tokenisation (it now returns a variant token, not a colour
  class). Rename in PR-ambientes-01, exception AMB-02-001.
- **`getAuditActionVariant` `'secondary'` → `'neutral'` mapping** —
  `'secondary'` is not a valid `<UiBadge>` variant. Mapping in
  PR-ambientes-02, exception AMB-02-002.

---

## 1. Scope

### 1.1 PR-ambientes-01 — list page + canvasRoutes fix

**Name**: `pr-ambientes-01-list-page-and-canvas-routes-detail-fix`
**Files**: `resources/js/components/layout/AppLayout.vue` (canvasRoutes
helper), `resources/js/modules/environments/EnvironmentsPage.vue`
(571 lines), `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php`
(prefix-matching replacement), `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php`
(extension), new `EnvironmentsAppShellTest.php`, new
`EnvironmentsStatusBadgeTest.php`, new `EnvironmentsCanvasRoutesPrefixTest.php`.
**Line estimate**: ~200 authored lines.
**Risk**: Low (cleanest starting point in the rollout; zero
`<style scoped>` blocks; no Reverb channel; canvasRoutes fix is global
additive).
**Dependencies**: Global PR0 already merged (`canvasRoutes` array,
`<UiBadge>`, `<UiLoadingSpinner>`, `<UiEmptyState>`,
`ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`).

### 1.2 PR-ambientes-02 — detail page + 3 inlined modals

**Name**: `pr-ambientes-02-detail-page-and-modals`
**Files**: `resources/js/modules/environments/EnvironmentDetailPage.vue`
(383 lines), `resources/js/modules/environments/EnvironmentsPage.vue`
modal sections (3 inlined modals, 9 raw form fields), test extension,
new `EnvironmentsModalChromeTest.php`.
**Line estimate**: ~280 authored lines.
**Risk**: Low (3 inlined modals are bounded; `<script>` edit is 1-line
mapping; `useAuditLogs.getDentalChairAuditLogs` stays verbatim).
**Dependencies**: PR-ambientes-01 (canvasRoutes fix is in place +
`<UiBadge>` pattern is established).

---

## 2. Requirements (PR-ambientes-01)

### Requirement: `AMB-01-001` [BLOCKING] — `canvasRoutes` MUST match detail routes via prefix

The system MUST add a `matchesCanvasRoute(path)` helper to
`resources/js/components/layout/AppLayout.vue`. The helper MUST use
`startsWith` matching: `path === route || path.startsWith(route + '/')`.
The `isCanvasRoute` computed MUST delegate to the helper. The
`AppLayoutCanvasRoutesTest::test_each_expected_route_is_in_canvas_routes`
literal-array test MUST be REPLACED with a prefix-matching assertion
that asserts `matchesCanvasRoute('/environments/123')` returns `true`.
The 6 detail routes (`/environments/:id`, `/patients/:id`,
`/professionals/:id`, `/appointment-types/:id`,
`/procedure-catalog/:id`, plus auxiliary) MUST get the fix for free;
subsequent category PRs MUST NOT touch `canvasRoutes` again.

#### Scenario: `AMB-01-001-1` — Detail route matches via prefix

- GIVEN `AppLayout.vue` line 537 lists `/environments` but NOT `/environments/:id`
- WHEN PR-ambientes-01 lands
- THEN `matchesCanvasRoute('/environments')` returns `true`
- AND `matchesCanvasRoute('/environments/123')` returns `true`
- AND `matchesCanvasRoute('/environments-archive')` returns `false` (over-match guard)
- AND `EnvironmentsCanvasRoutesPrefixTest::test_canvas_routes_matches_detail_via_starts_with` asserts the rule
- AND `git grep -nE 'canvasRoutes\.includes\(route\.path\)' resources/js/components/layout/AppLayout.vue` returns zero matches (exact-match check is gone)

### Requirement: `AMB-01-002` — Status filter MUST use `<UiSelect>` primitive

The system MUST replace the raw `<select>` status filter on
`EnvironmentsPage.vue` line 65 (legacy class string
`border border-theme rounded-lg focus:ring-2 focus:ring-primary-500
focus:border-accent bg-theme-surface-elevated text-theme-primary`) with
`<UiSelect :options="statusOptions" v-model="statusFilter">`. All 4
options (`Todos los estados` / `Activos` / `Inactivos` /
`Mantenimiento`) MUST keep their `value` attributes byte-for-byte.

#### Scenario: `AMB-01-002-1` — Status filter consumes UiSelect

- GIVEN the status filter renders 4 options from the legacy alias string
- WHEN PR-ambientes-01 lands
- THEN `EnvironmentsAppShellTest::test_status_filter_uses_ui_select` asserts `<UiSelect>` reference present + raw `<select class="border-theme">` absent
- AND `LegacyAliasForbiddenTest` (extended) returns zero matches for `border-theme` + `focus:ring-primary-500` + `focus:border-accent` on the list page

### Requirement: `AMB-01-003` — Table dividers MUST consume hairline token

The system MUST replace `divide-y divide-theme` (lines 104 + 134) with
`divide-y divide-[color:var(--color-hairline)]` on the environments
table. The `<thead>` and `<tbody>` MUST both consume the hairline.

#### Scenario: `AMB-01-003-1` — Hairline divides table rows

- GIVEN the table renders a `divide-y` row separator
- WHEN PR-ambientes-01 lands
- THEN `EnvironmentsAppShellTest::test_table_dividers_use_hairline` asserts `--color-hairline` reference present + `divide-theme` absent
- AND `git grep -nE 'divide-theme' resources/js/modules/environments/EnvironmentsPage.vue` returns zero matches

### Requirement: `AMB-01-004` — Row avatar MUST consume systemBlue ramps

The system MUST replace `bg-primary-100` + `text-accent` on the row
avatar (lines 144 + 146) with `bg-systemBlue-50` + `text-systemBlue-700`.
The legacy `text-accent` is a forbidden alias that MUST be removed.

#### Scenario: `AMB-01-004-1` — Row avatar is tokenised systemBlue

- GIVEN the row avatar uses the legacy accent ramp
- WHEN PR-ambientes-01 lands
- THEN `EnvironmentsAppShellTest::test_row_avatar_uses_system_blue` asserts `bg-systemBlue-50` + `text-systemBlue-700` references present + `bg-primary-100` + `text-accent` absent
- AND the `LegacyAliasForbiddenTest` extends its file set to include `EnvironmentsPage.vue`

### Requirement: `AMB-01-005` — Action link buttons MUST consume `<UiButton variant="link/ghost">`

The system MUST replace the three action links (`Ver Detalle` line 179,
`Editar` line 187, `Eliminar` line 195) with `<UiButton variant="link">`
for Ver/Editar and `<UiButton variant="ghost">` for Eliminar. The
`text-red-600 hover:text-red-900` raw Tailwind on Eliminar MUST be
replaced by `text-systemRed-700`. The legacy `text-accent
hover:text-accent-hover` + `text-accent hover:text-primary-800` MUST
be removed.

#### Scenario: `AMB-01-005-1` — Action buttons consume UiButton variants

- GIVEN the 3 action buttons use raw class strings on top of `<UiButton>`
- WHEN PR-ambientes-01 lands
- THEN `EnvironmentsAppShellTest::test_action_buttons_use_ui_button_variants` asserts the rule (`variant="link"` on Ver/Editar, `variant="ghost"` on Eliminar, `text-accent` + `hover:text-accent-hover` + `text-red-600` + `hover:text-red-900` absent)
- AND the existing `<UiButton>` wrapper presence is preserved (no raw `<button>` is reintroduced)

### Requirement: `AMB-01-006` — Loading spinner MUST consume `<UiLoadingSpinner>`

The system MUST replace the raw `inline-block animate-spin rounded-full
h-8 w-8 border-b-2 border-accent` spinner on line 82 with
`<UiLoadingSpinner size="md">`. The `border-accent` legacy alias MUST be
removed.

#### Scenario: `AMB-01-006-1` — Loading spinner consumes UiLoadingSpinner

- GIVEN the list page renders a hand-rolled spinner while loading
- WHEN PR-ambientes-01 lands
- THEN `EnvironmentsAppShellTest::test_loading_spinner_uses_ui_component` asserts `<UiLoadingSpinner>` reference present + `animate-spin rounded-full` + `border-b-2` absent
- AND the `LoadingSpinner.vue` import is added to the list page's `import` block

### Requirement: `AMB-01-007` — Empty state MUST consume `<UiEmptyState>`

The system MUST replace the hand-rolled `<svg>` + `<p>` empty state
(lines 86–101) with
`<UiEmptyState title="No se encontraron ambientes" description="Intenta ajustar los filtros o crear un nuevo ambiente." />`.
The legacy 7-line SVG path + `text-theme-secondary` literal MUST be
removed.

#### Scenario: `AMB-01-007-1` — Empty state consumes UiEmptyState

- GIVEN the list page renders a hand-rolled empty state when `environments.length === 0`
- WHEN PR-ambientes-01 lands
- THEN `EnvironmentsAppShellTest::test_empty_state_uses_ui_component` asserts `<UiEmptyState>` reference present + the 7-line SVG path absent
- AND the existing `UiEmptyState` import (already in the file but unused) is wired to the empty branch

### Requirement: `AMB-01-008` — Status pill MUST consume `<UiStatusBadge>` + `getStatusVariant` helper

The system MUST replace the `<span :class="getStatusColor(...)">` status
pill on line 170 with
`<UiStatusBadge :variant="getStatusVariant(environment.status)" :label="getStatusText(environment.status)" />`.
The `getStatusColor` helper on line 516 MUST be renamed to
`getStatusVariant` and its return values MUST change from legacy colour
class strings (`bg-success-100 text-success-700`, etc.) to variant
tokens (`success | neutral | warning`). This is the FIRST documented
DLR-AMB-005 exception (see AMB-02-001 for the spec row). The
`getStatusText` helper MUST stay byte-for-byte (no rename, no change).

#### Scenario: `AMB-01-008-1` — Status pill consumes UiStatusBadge + variant token

- GIVEN the status pill on each table row uses legacy colour class strings
- WHEN PR-ambientes-01 lands
- THEN `EnvironmentsStatusBadgeTest::test_status_pill_uses_ui_status_badge` asserts `<UiStatusBadge>` reference present + `bg-success-100` + `bg-warning-100` + `bg-theme-surface text-theme-primary` absent
- AND `EnvironmentsStatusBadgeTest::test_get_status_variant_returns_tokens` asserts `getStatusVariant('active')` returns `'success'`, `getStatusVariant('inactive')` returns `'neutral'`, `getStatusVariant('maintenance')` returns `'warning'`

---

## 3. Requirements (PR-ambientes-02)

### Requirement: `AMB-02-001` — DLR-AMB-005 exception: `getStatusColor` MUST be renamed to `getStatusVariant`

The system MUST rename the `getStatusColor` function on
`EnvironmentsPage.vue` line 516 to `getStatusVariant` and MUST update
the return values from legacy colour class strings (`bg-success-100
text-success-700`, etc.) to variant tokens
(`success | neutral | warning`). This is a DOCUMENTED EXCEPTION to the
global `<script>`-never-touched rule. The function is 1 line, the
behaviour change is zero (call sites already expect a variant token
after AMB-01-008 lands). The remaining `<script>` block of
`EnvironmentsPage.vue` MUST stay byte-for-byte preserved (all
`useApi` / `useToast` / `useConfirm` / `useErrorHandler` calls, the
`loadEnvironments` / `searchEnvironments` / `createEnvironment` /
`updateEnvironment` / `deleteEnvironment` flows, the `onMounted` hook,
and the `return` statement's other entries stay verbatim).

#### Scenario: `AMB-02-001-1` — Rename is mechanical, zero drift

- GIVEN the helper is renamed + return values updated
- WHEN PR-ambientes-01 lands (carry)
- THEN `EnvironmentsStatusBadgeTest::test_get_status_variant_returns_tokens` pins the variant tokens
- AND `git diff --stat` on `<script>` blocks of `EnvironmentsPage.vue` shows <= 2 lines changed (the `const getStatusColor` → `const getStatusVariant` line + the body line)
- AND no other `<script>` lines are touched (the `useApi` / `useToast` / `useConfirm` / `useErrorHandler` reactivity stays verbatim)

### Requirement: `AMB-02-002` — DLR-AMB-005 exception: `getAuditActionVariant` MUST map `'secondary'` to `'neutral'`

The system MUST update the `getAuditActionVariant` helper on
`EnvironmentDetailPage.vue` line 339 from `return 'secondary'` to
`return 'neutral'`. This is a DOCUMENTED EXCEPTION to the global
`<script>`-never-touched rule. The change is 1 line, the `<UiBadge>`
validation requires a legal variant (`'secondary'` is not in the enum),
and the audit action badge currently renders blank without this fix.
The remaining `<script>` block of `EnvironmentDetailPage.vue` MUST stay
byte-for-byte preserved (the `useAuditLogs.getDentalChairAuditLogs`
call, the `onMounted` hook, the `watch(activeTab, ...)` reactivity, and
all `useApi` / `useToast` calls stay verbatim).

#### Scenario: `AMB-02-002-1` — Mapping is load-bearing

- GIVEN the audit action badge renders with no variant when `'secondary'` is returned
- WHEN PR-ambientes-02 lands
- THEN `EnvironmentsAppShellTest::test_audit_action_badge_uses_legal_variant` asserts the rule (`'neutral'` returned, `'secondary'` absent)
- AND `git diff --stat` on `<script>` blocks of `EnvironmentDetailPage.vue` shows <= 1 line changed
- AND `<UiBadge :variant="getAuditActionVariant(log.action)">` now renders with a visible background ramp

### Requirement: `AMB-02-003` — 2-tab drawer MUST consume `<UiTabs v-model="activeTab">`

The system MUST replace the raw tab strip on `EnvironmentDetailPage.vue`
line 70 (`border-b border-theme` + line 77 `border-accent text-accent`
+ line 78 `border-transparent text-theme-secondary
hover:text-theme-primary hover:border-theme`) with
`<UiTabs v-model="activeTab" :tabs="tabs">`. The 2 tabs (`Datos` /
`Historial de auditoría`) MUST keep their labels + click handlers
byte-for-byte. The transitions MUST consume
`var(--motion-duration-fast) var(--motion-easing-ios)`.

#### Scenario: `AMB-02-003-1` — Tabs use UiTabs primitive

- GIVEN the detail page renders a hand-rolled 2-tab drawer
- WHEN PR-ambientes-02 lands
- THEN `EnvironmentsAppShellTest::test_tabs_use_ui_tabs` asserts `<UiTabs>` reference present + raw `border-accent text-accent` active indicator absent
- AND `git grep -nE 'border-accent text-accent' resources/js/modules/environments/EnvironmentDetailPage.vue` returns zero matches
- AND the `activeTab` ref interaction (Datos → loadChair, Historial → loadAuditLogs) is preserved verbatim via `<UiTabs v-model>`

### Requirement: `AMB-02-004` — Header avatar MUST be flat systemBlue (no gradients)

The system MUST replace the `bg-gradient-accent` header avatar on
`EnvironmentDetailPage.vue` line 30 with
`bg-systemBlue-50 rounded-[var(--radius-card-lg)]`. Global §11 forbids
gradients; `bg-gradient-*` is the load-bearing violation.

#### Scenario: `AMB-02-004-1` — Gradient is removed, flat ramp applied

- GIVEN the header avatar currently uses a forbidden gradient
- WHEN PR-ambientes-02 lands
- THEN `EnvironmentsAppShellTest::test_no_gradient_class` asserts the rule (`bg-gradient-*` absent, `bg-systemBlue-50` + `rounded-[var(--radius-card-lg)]` present)
- AND the precedent (`PatientsPage` row avatar `bg-systemBlue-50`) is mirrored

### Requirement: `AMB-02-005` — Audit log empty state MUST consume `<UiEmptyState>`

The system MUST replace the hand-rolled `bg-gradient-to-br` empty
state on `EnvironmentDetailPage.vue` lines 147–167 with
`<UiEmptyState title="No hay historial de auditoría" description="Los cambios en este ambiente aparecerán aquí." />`.
The forbidden `bg-gradient-to-br` MUST be removed.

#### Scenario: `AMB-02-005-1` — Audit empty state uses UiEmptyState

- GIVEN the audit log tab renders a hand-rolled gradient empty state
- WHEN PR-ambientes-02 lands
- THEN `EnvironmentsAppShellTest::test_audit_empty_state_uses_ui_component` asserts `<UiEmptyState>` reference present + `bg-gradient-to-br` absent
- AND the `UiEmptyState` import is added to the detail page's `import` block

### Requirement: `AMB-02-006` — Audit log card MUST consume `<UiCard variant="glass">`

The system MUST replace the legacy `border border-theme rounded-lg p-4
hover:bg-theme-surface transition-colors` audit log item wrapper
(line 172) with `<UiCard variant="glass">`. The change-diff callout
`border-l-2 border-theme` (line 198) MUST consume a hairline token.

#### Scenario: `AMB-02-006-1` — Audit log items consume UiCard + hairline

- GIVEN the audit log renders a hand-rolled card per entry
- WHEN PR-ambientes-02 lands
- THEN `EnvironmentsAppShellTest::test_audit_log_uses_ui_card` asserts `<UiCard variant="glass">` reference present + `border-theme rounded-lg p-4` legacy class absent
- AND `EnvironmentsAppShellTest::test_change_diff_callout_uses_hairline` asserts `border-l-2 border-[color:var(--color-hairline)]` present + `border-l-2 border-theme` absent

### Requirement: `AMB-02-007` — 3 inlined modals MUST migrate 9 raw form fields to primitives

The system MUST replace the 9 raw form fields across the 3 inlined
modals in `EnvironmentsPage.vue` with the canonical `<UiInput>` /
`<UiTextarea>` / `<UiSelect>` primitives. Migration counts:
`<UiInput>` × 3 (name in New + name + code in Edit),
`<UiTextarea>` × 4 (description + equipment in New, description in Edit
+ 1 buffer — see explore.md §2.2 row New + Edit counts), `<UiSelect>`
× 2 (status in New + status in Edit). The `v-model` bindings +
`required` attributes MUST stay byte-for-byte. The View modal status
pill (line 340) MUST consume `<UiBadge>`.

#### Scenario: `AMB-02-007-1` — 9 raw fields → 9 primitives

- GIVEN 3 inlined modals (New / Edit / View) carry raw `<input>` / `<textarea>` / `<select>` with legacy focus chrome
- WHEN PR-ambientes-02 lands
- THEN `EnvironmentsModalChromeTest::test_modals_use_ui_form_primitives` asserts the rule per modal (`<UiInput>` / `<UiTextarea>` / `<UiSelect>` present, raw `<input>` + `<textarea>` + `<select>` absent in modal sections lines 213–352)
- AND every `v-model=` binding remains present byte-for-byte (asserted via grep)
- AND the View modal renders `<UiBadge>` for the status pill (not raw `<span>` with legacy class)

---

## 4. Inherited MUST (re-asserted from parent `DLR-CORE-*`)

The AMBIENTES PRs inherit the following guard rails verbatim from the
parent spec. This spec does NOT relax them.

- AMB inherits DLR-R-009: `LegacyAliasForbiddenTest` pins the alias
  list and is extended to include `EnvironmentsPage.vue` +
  `EnvironmentDetailPage.vue`.
- AMB inherits DLR-R-021: zero `<style scoped>` blocks. Both files
  have zero by default (cleanest starting point in the rollout).
- AMB inherits DLR-R-011 (no gradients): AMB-02-004 enforces this
  on the detail page header avatar + audit empty state.
- AMB inherits DLR-R-005 (focus ring composed):
  `var(--focus-ring-default)` is the only focus-ring token; AMB-01-002
  + AMB-02-007 enforce this via primitive adoption.
- AMB inherits DLR-R-007 (`<script>` blocks never touched EXCEPT for
  the 2 documented DLR-AMB-005 exceptions per AMB-02-001 + AMB-02-002).
- AMB inherits DLR-R-008 (no new primitives): the full `<UiCard>` /
  `<UiButton>` / `<UiInput>` / `<UiSelect>` / `<UiTextarea>` /
  `<UiModal>` / `<UiBadge>` / `<UiLoadingSpinner>` / `<UiEmptyState>`
  / `<UiTabs>` set is consumed as-is. AMBIENTES introduces zero new
  primitives.
- AMB inherits the `DentalChairController` API envelope preservation
  (`{ id, name, code, description, equipment, status, is_active,
  created_at, updated_at, audit_logs }`) and the
  `useAuditLogs.getDentalChairAuditLogs(chairId)` composable contract
  byte-for-byte.
- AMB inherits the 400-line authored review budget per PR.

---

## 5. Out of scope

Mirrors the AMBIENTES proposal §4. Items are excluded and explicitly
recorded so the apply phase does NOT silently resolve them.

| Item | Reason |
|---|---|
| Cross-module `useEnvironmentStatuses` composable | Global §11 forbids new composables in the rollout; `getStatusColor` / `getStatusText` / `getStatusVariant` are duplicated across 2 files. Defer to global composable-extraction slice (post-rollout). |
| Admin-only role banner | No `<RoleBanner>` primitive exists; `DentalChairPolicy` handles server-side role gating. UX decision, not visual-token decision. Defer. |
| Dark mode | Light-only by design. |
| Audit log pagination | Out of scope for visual polish. |
| `DentalChair::code` uniqueness client-side validation | Server-side 422 surface via `useToast` is the contract. |
| Settings/branches + Settings/payment-methods | Per global OQ#3 — OUT of scope. |
| Two-tone numerals (D12 REVERSIBLE) | Stays rejected. |
| New primitives (any) | Global §11 forbids. |
| Mobile capture on admin surface | Desktop 1440x900 mandatory; 390x844 mobile optional per module (no documented responsive behaviour). |

---

## 6. Verification strategy

- **Visual**: `pnpm build` clean; `git grep` for `border-theme`,
  `divide-theme`, `bg-success-100`, `bg-warning-100`, `bg-theme-surface
  text-theme-primary`, `text-accent hover:text-accent-hover`,
  `text-red-600 hover:text-red-900`, `bg-primary-100 text-accent`,
  `focus:ring-primary-500 focus:border-accent`, `bg-gradient-accent`,
  `bg-gradient-to-br`, raw `<select class="border-theme">`, raw
  `<input ... class="...">` inside modal sections on
  `resources/js/modules/environments/EnvironmentsPage.vue` +
  `EnvironmentDetailPage.vue` returns zero matches.
  `playwright-cli` snapshot at 1440x900 saved to
  `.playwright-cli/screenshots-rollout/environments-list-1440x900.png`
  + `.playwright-cli/screenshots-rollout/environment-detail-1440x900.png`.
  Credentials: `admin@test.com` for the canonical admin role per
  `CREDENTIALS.md`.
- **Static (PHPUnit)**: `EnvironmentsAppShellTest` extends
  `ModuleAppShellTestCase` and asserts the per-file rule (token
  reference exists, alias absent, `<style scoped>` absent, no
  `bg-gradient-*` class). `EnvironmentsStatusBadgeTest` asserts
  `<UiStatusBadge>` variant presence + legacy alias absence +
  `getStatusVariant` returns variant tokens. `EnvironmentsModalChromeTest`
  asserts `<UiInput>` / `<UiTextarea>` / `<UiSelect>` wrapper presence
  + raw form field absence on the 3 inlined modals.
  `EnvironmentsCanvasRoutesPrefixTest` asserts the `matchesCanvasRoute`
  helper covers detail routes + the over-match guard
  (`/environments-archive` returns `false`).
  `AppLayoutCanvasRoutesTest` (modified) replaces literal-array test
  with prefix-matching helper test.
  `LegacyAliasForbiddenTest` (extended) green at every PR-ambientes-NN
  boundary.
- **Runtime**: `AppLayoutCanvasRoutesTest`, `TokensModuleTest`,
  `GeneratedTokensCssTest`, `PrimitivePressTest`,
  `DashboardAppShellTest`, `LoginPageRenderTest`, `UseSpringMathTest`,
  `ModuleAppShellTestCase`-derived tests stay green at every PR
  boundary. Manual smoke test: open `/environments/:id`, switch to the
  audit tab, verify the audit log loads via
  `getDentalChairAuditLogs(chairId)` within 1 second; verify create /
  update / delete toasts still fire.

---

## 7. Acceptance criteria

The AMBIENTES rollout is considered complete when ALL of the
following hold:

- Both ambientes routes (`/environments`, `/environments/:id`) render
  on Apple canvas (`bg-canvas`). Verified post-PR-ambientes-01.
  `AppLayoutCanvasRoutesTest` green; `EnvironmentsCanvasRoutesPrefixTest`
  green.
- Both files contain zero legacy alias classes
  (`border-theme`, `bg-success-100`, `bg-warning-100`,
  `bg-primary-100`, `text-accent`, `focus:ring-primary-500`,
  `focus:border-accent`, `bg-gradient-accent`, `bg-gradient-to-br`).
  `LegacyAliasForbiddenTest` green; `EnvironmentsAppShellTest` green.
- Status pills on list rows + View modal use `<UiStatusBadge>` (NOT
  legacy alias). `EnvironmentsStatusBadgeTest` green.
- 3 inlined modals use `<UiInput>` / `<UiTextarea>` / `<UiSelect>` (NOT
  raw `<input>` / `<textarea>` / `<select>`).
  `EnvironmentsModalChromeTest` green.
- Detail page header avatar uses `bg-systemBlue-50` (NOT
  `bg-gradient-accent`). `EnvironmentsAppShellTest::test_no_gradient_class`
  green.
- 2-tab drawer uses `<UiTabs>` instead of raw
  `border-accent text-accent` step strip.
- Audit log spinner uses `<UiLoadingSpinner>`; audit empty state uses
  `<UiEmptyState>`; audit list items use `<UiCard variant="glass">`.
- ID cell on list page carries
  `font-feature-settings: var(--font-features-tabular-nums)`.
- Both files have zero `<style scoped>` blocks (already green by
  default; `ModuleAppShellTestCase::test_no_style_scoped` pins).
- `useApi` / `useToast` / `useConfirm` / `useErrorHandler` /
  `useAuditLogs` reactivity preserved.
- `DentalChairController` API envelope unchanged.
- No new primitives introduced.
- Playwright snapshots saved.
- All `tests/Unit/DesignSystem/*` PHPUnit invariants stay green.
- CI green: `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).
- Test count delta ≥ +20 vs PR0 baseline.

---

## 8. References

- `categories/ambientes/explore.md` — AMBIENTES inventory + 8 OQs.
- `categories/ambientes/proposal.md` — AMBIENTES proposal (intent,
  scope, OQ resolutions, PR chain, success criteria).
- `specs/design-language-rollout/spec.md` — parent spec
  (`DLR-CORE-*` rules + `DLR-MOD-005` Ambientes baseline).
- `archive/2026-08-12-ui-pacientes/specs/pacientes/spec.md` —
  closest precedent (5-PR split, sibling delta format).
- `archive/2026-08-12-ui-citas/specs/citas/spec.md` — sibling CITAS
  category spec.
- `archive/2026-08-12-ui-pagos/specs/pagos/spec.md` — sibling PAGOS
  category spec.
- `archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` —
  process lesson: "tests pin the rule, not the literal." AMBIENTES
  structure tests extend `ModuleAppShellTestCase` and assert the rule.
- `resources/js/components/layout/AppLayout.vue` lines 523–557
  (`canvasRoutes` + BLOCKING fix lands here).
- `resources/js/modules/environments/EnvironmentsPage.vue` (571 lines)
  + `EnvironmentDetailPage.vue` (383 lines).
- `tests/Unit/DesignSystem/{ModuleAppShellTestCase,
  AppLayoutCanvasRoutesTest, LegacyAliasForbiddenTest,
  AppointmentTypesAppShellTest}.php`.

---

*End of ambientes category spec.*
