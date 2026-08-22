# Tasks: ambientes (ui-rollout-all-modules-2026-08)

> 2 chained PRs: PR-ambientes-01 (list + canvasRoutes fix) + PR-ambientes-02 (detail + 3 modals).
> Parent artifacts: `categories/ambientes/{explore,proposal,spec}.md`. Sibling delta of `design-language-rollout` global spec.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | AMBIENTES (admin CRUD: list + detail + 3 inlined modals + audit log tab) |
| Date | 2026-08-21 |
| Phase | tasks (4 of 6) — category slice |
| Artifact store | hybrid (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/ambientes/tasks`) |
| Pace / Delivery strategy | auto / auto-chain (inherited; sub-PRs stack inside global PR4) |
| Strict TDD | true (forward to apply/verify) |
| Review budget | 400 authored lines / PR |
| Total tasks | 30 (15 in PR-ambientes-01 + 15 in PR-ambientes-02) |
| Total MUST rows | 15 (AMB-01-001..008 + AMB-02-001..008 — note: AMB-02-001/002 are the 2 DLR-AMB-005 `<script>` exceptions) |

---

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines (PR-ambientes-01) | ~200 |
| Estimated changed lines (PR-ambientes-02) | ~280 |
| 400-line budget risk | Low (each slice strictly under 400) |
| Chained PRs recommended | Yes |
| Suggested split | PR-ambientes-01 (list + canvasRoutes fix) → PR-ambientes-02 (detail + 3 modals) |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main (sub-PRs land on `main` in order inside global PR4 `pr4-admin-crud-triplet`) |
| Decision needed before apply | No (auto-chain proceeds; OQ-ambientes-01..08 all resolved in proposal §13) |
| Generated CSS inclusion | N/A (no CSS generation; AMBIENTES consumes tokens as-is from PR0) |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | List page tokenisation + global canvasRoutes detail-route fix | PR-ambientes-01 | `php artisan test --filter='EnvironmentsAppShellTest\|EnvironmentsStatusBadgeTest\|EnvironmentsCanvasRoutesPrefixTest\|AppLayoutCanvasRoutesTest\|LegacyAliasForbiddenTest'` | `php artisan test --testsuite=Unit` + `pnpm build` | `git revert <merge-sha>`; `AppLayout.vue` reverts to `canvasRoutes.includes(route.path)` exact-match; `EnvironmentsPage.vue` reverts to legacy class strings + `getStatusColor` 1-line revert |
| 2 | Detail page polish + 3 inlined modals tokenisation | PR-ambientes-02 | `php artisan test --filter='EnvironmentsAppShellTest\|EnvironmentsModalChromeTest\|LegacyAliasForbiddenTest'` | `php artisan test --testsuite=Unit` + `pnpm build` | `git revert <merge-sha>`; detail page reverts to legacy `border-accent text-accent` raw tab strip + 3 inlined modals revert to raw form fields; `getAuditActionVariant` mapping reverts to `'secondary'` (follow-up hotfix required) |

### Strict ordering rationale

PR-ambientes-01 MUST land before PR-ambientes-02. If the detail page lands first without the canvasRoutes fix, it renders on `bg-systemBackground` chrome until PR-01 catches up — visually inconsistent with the list page. The canvasRoutes fix is BLOCKING for the entire rollout (6 detail routes affected globally).

---

# PR-ambientes-01 — list + canvasRoutes detail-route fix

**Files**: `resources/js/components/layout/AppLayout.vue` (helper), `resources/js/modules/environments/EnvironmentsPage.vue` (571 lines), `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` (replace literal-array test with prefix-matching assertion), `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` (extend), NEW `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php`, NEW `tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php`, NEW `tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php`.
**Risk**: Low (cleanest starting point in the rollout; zero `<style scoped>` blocks; no Reverb channel; canvasRoutes fix is global additive).
**Dependencies**: PR0 merged (`canvasRoutes` array, `<UiBadge>`, `<UiLoadingSpinner>`, `<UiEmptyState>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`).

## Phase 1: RED

- [x] **T1.1** (AMB-01-002..007): Create `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `[EnvironmentsPage.vue]`. Add assertions: `test_status_filter_uses_ui_select`, `test_table_dividers_use_hairline`, `test_row_avatar_uses_system_blue`, `test_action_buttons_use_ui_button_variants`, `test_loading_spinner_uses_ui_component`, `test_empty_state_uses_ui_component`.
- [x] **T1.2** (AMB-01-008): Create `tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php`. Assert `<UiBadge>` (or `<UiStatusBadge>`) variant primitive present + `bg-success-100` / `bg-warning-100` / `bg-theme-surface text-theme-primary` legacy aliases absent on the list page.
- [x] **T1.3** (AMB-01-001): Extend `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` with new test `test_canvas_routes_matches_detail_via_starts_with` — exercises the `matchesCanvasRoute(path)` helper with `/environments`, `/environments/123`, `/environments-archive` (over-match guard returns `false`). Replace `test_each_expected_route_is_in_canvas_routes` with a prefix-matching helper test (helper exists + `/environments/123` returns `true`).
- [x] **T1.4** (AMB-01-002..008): Extend `LegacyAliasForbiddenTest::defaultPolishedFiles()` to include `resources/js/modules/environments/EnvironmentsPage.vue`.

## Phase 2: GREEN

- [x] **T1.5 [BLOCKING]** (AMB-01-001): Apply the canvasRoutes detail-route fix. In `resources/js/components/layout/AppLayout.vue` near the `canvasRoutes` literal (~line 556), add the helper:

  ```js
  function matchesCanvasRoute(path) {
    return canvasRoutes.some(
      route => path === route || path.startsWith(route + '/')
    )
  }
  ```

  Then replace line 557's `const isCanvasRoute = computed(() => canvasRoutes.includes(route.path))` with `const isCanvasRoute = computed(() => matchesCanvasRoute(route.path))`. This single helper covers all 6 detail routes globally (`/environments/:id`, `/patients/:id`, `/professionals/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary). Update `AppLayoutCanvasRoutesTest::EXPECTED_ROUTES` to keep the existing 21 module routes + replace the literal-array data-provider test with the prefix-matching helper test. **This is the load-bearing fix for the entire rollout — subsequent category PRs MUST NOT touch `canvasRoutes` again.**
- [x] **T1.6** (AMB-01-002): Replace raw `<select>` status filter (line 65) with `<UiSelect :options="statusOptions" v-model="statusFilter">`. Keep all 4 options (`Todos los estados` / `Activos` / `Inactivos` / `Mantenimiento`) byte-for-byte.
- [x] **T1.7** (AMB-01-003): Replace `divide-y divide-theme` (lines 104 + 134) with `divide-y divide-[color:var(--color-hairline)]` on `<thead>` + `<tbody>`.
- [x] **T1.8** (AMB-01-004): Replace row avatar `bg-primary-100` (line 144) → `bg-systemBlue-50`; `text-accent` (line 146) → `text-systemBlue-700`.
- [x] **T1.9** (AMB-01-005): Replace 3 action links. `Ver Detalle` (line 179) + `Editar` (line 187): `class="text-accent hover:text-accent-hover"` / `class="text-accent hover:text-primary-800"` → `<UiButton variant="link">` (drop legacy class, add `variant="link"`). `Eliminar` (line 195): `class="text-red-600 hover:text-red-900"` → `<UiButton variant="ghost">` with `text-systemRed-700` body class.
- [x] **T1.10** (AMB-01-006): Replace hand-rolled spinner (line 82) `inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent` with `<UiLoadingSpinner size="md" />`. Add `UiLoadingSpinner` to the import block.
- [x] **T1.11** (AMB-01-007): Replace hand-rolled empty state SVG + `<p>` (lines 86–101) with `<UiEmptyState title="No se encontraron ambientes" description="Intenta ajustar los filtros o crear un nuevo ambiente." />`. The `UiEmptyState` import is already in the file but unused — wire it.
- [x] **T1.12** (AMB-01-008): Replace status pill (line 170) `<span :class="getStatusColor(...)" class="...">` with `<UiBadge :variant="getStatusVariant(environment.status)" :label="getStatusText(environment.status)" />`. Helper AMB-02-001 rename carries in PR-01 (see T2.4).

## Phase 3: VERIFY

- [x] **T1.13** (AMB-01-001..008): Run `php artisan test --filter='EnvironmentsAppShellTest|EnvironmentsStatusBadgeTest|EnvironmentsCanvasRoutesPrefixTest|AppLayoutCanvasRoutesTest|LegacyAliasForbiddenTest'` — all green. Run full `php artisan test --testsuite=Unit` — no regressions. Run `pnpm build` — clean. Run `php artisan test --testsuite=Feature --filter=Api` — no regressions on backend endpoints.
- [x] **T1.14** (AMB-01-001): Visual verification at 1440x900 (login as `admin@test.com` per `CREDENTIALS.md`). Navigate to `/environments` AND `/environments/:id` (pick any real chair id). Both MUST render on `bg-canvas` chrome (not `bg-systemBackground`). Save screenshots to `.playwright-cli/screenshots-rollout/environments-list-1440x900.png` + `.playwright-cli/screenshots-rollout/environment-detail-1440x900.png`. **This step proves the canvasRoutes fix actually works in the browser.**

## Phase 4: COMMIT

- [x] **T1.15**: Commit (no `Co-Authored-By`): `feat(ui): ambientes list + canvasRoutes detail-route fix (AMB-01-*)`.

---

# PR-ambientes-02 — detail page + 3 modals

**Files**: `resources/js/modules/environments/EnvironmentDetailPage.vue` (383 lines), `resources/js/modules/environments/EnvironmentsPage.vue` (modal sections, lines 213–352), `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` (extend `polishedFiles()` + add 5 new assertions), `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` (extend), NEW `tests/Unit/DesignSystem/EnvironmentsModalChromeTest.php`.
**Risk**: Low (3 inlined modals are bounded; 2 `<script>` edits are 1-line mechanical renames; `useAuditLogs.getDentalChairAuditLogs` stays verbatim).
**Dependencies**: PR-ambientes-01 (canvasRoutes fix in place + `<UiBadge>` pattern established).

## Phase 1: RED

- [ ] **T2.1** (AMB-02-003..008): Extend `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` `polishedFiles()` to also return `EnvironmentDetailPage.vue`. Add assertions: `test_tabs_use_ui_tabs`, `test_no_gradient_class` (asserts `bg-gradient-*` absent + `bg-systemBlue-50` present), `test_audit_empty_state_uses_ui_component`, `test_audit_log_uses_ui_card`, `test_change_diff_callout_uses_hairline`, `test_audit_action_badge_uses_legal_variant` (`'secondary'` absent, `'neutral'` returned by `getAuditActionVariant`).
- [ ] **T2.2** (AMB-02-003..008): Extend `LegacyAliasForbiddenTest::defaultPolishedFiles()` to include `EnvironmentDetailPage.vue`.
- [ ] **T2.3** (AMB-02-007): Create `tests/Unit/DesignSystem/EnvironmentsModalChromeTest.php`. Assert 3 inlined modals (New lines 213–259, Edit lines 262–314, View lines 317–352) use `<UiInput>` / `<UiTextarea>` / `<UiSelect>` (NOT raw `<input>` / `<textarea>` / `<select>`). Assert every `v-model=` binding preserved byte-for-byte (grep-verified). Assert View modal status pill uses `<UiBadge>` (not raw `<span>` with legacy class).

## Phase 2: GREEN — DLR-AMB-005 documented `<script>` exceptions

- [ ] **T2.4 [DLR-AMB-005 EXCEPTION #1]** (AMB-02-001): Rename `getStatusColor` → `getStatusVariant` in `EnvironmentsPage.vue:516`. Change return values from legacy colour class strings (`bg-success-100 text-success-700` / `bg-theme-surface text-theme-primary` / `bg-warning-100 text-warning-700`) to variant tokens (`success` / `neutral` / `warning`). 1-line additive change in `<script>`. **Documented exception to global `<script>`-never-touched rule.** Update the `return` statement to expose `getStatusVariant` instead of `getStatusColor`. The remaining `<script>` block (`useApi` / `useToast` / `useConfirm` / `useErrorHandler` calls, `loadEnvironments` / `searchEnvironments` / `createEnvironment` / `updateEnvironment` / `deleteEnvironment` flows, `onMounted` hook, all `return` statement entries except the renamed helper) MUST stay verbatim.
- [ ] **T2.5 [DLR-AMB-005 EXCEPTION #2]** (AMB-02-002): In `EnvironmentDetailPage.vue:339`, change `getAuditActionVariant`'s `return 'secondary'` → `return 'neutral'`. 1-line `<script>` edit. **Documented exception to global `<script>`-never-touched rule.** `'secondary'` is not a legal `<UiBadge>` variant and would render blank without this mapping. The remaining `<script>` block (`useAuditLogs.getDentalChairAuditLogs`, `useApi` / `useToast` calls, `onMounted`, `watch(activeTab, ...)`, `loadEnvironment` / `loadAuditLogs` reactivity, all `return` entries except the helper mapping) MUST stay verbatim.

## Phase 3: GREEN — template-level replacements

- [ ] **T2.6** (AMB-02-003): Replace raw tab strip (lines 69–86) `<nav class="flex space-x-8 border-b border-theme">` + per-button `border-accent text-accent` / `border-transparent text-theme-secondary hover:text-theme-primary hover:border-theme` with `<UiTabs v-model="activeTab" :tabs="tabs" />`. Keep 2 tab labels (`Datos` / `Historial de auditoría`) + click handlers byte-for-byte. Add `UiTabs` to import block.
- [ ] **T2.7** (AMB-02-004): Replace header avatar (line 30) `bg-gradient-accent` (forbidden per global §11) with `bg-systemBlue-50 rounded-[var(--radius-card-lg)]`. No gradients anywhere.
- [ ] **T2.8** (AMB-02-005): Replace hand-rolled `bg-gradient-to-br` empty state (lines 145–167) with `<UiEmptyState title="No hay historial de auditoría" description="Los cambios en este ambiente aparecerán aquí." />`. Add `UiEmptyState` to import block.
- [ ] **T2.9** (AMB-02-006): Replace legacy audit log item wrapper (line 172) `border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors` with `<UiCard variant="glass">`. Replace change-diff callout (line 198) `border-l-2 border-theme` with `border-l-2 border-[color:var(--color-hairline)]`.
- [ ] **T2.10** (AMB-02-007): Migrate 9 raw form fields in 3 inlined modals to primitives. **New modal (lines 213–259)**: name input → `<UiInput v-model="newEnvironment.name" label="Nombre del Ambiente" required />`; description textarea → `<UiTextarea v-model="newEnvironment.description" label="Descripción" :rows="3" />`; equipment textarea → `<UiTextarea v-model="newEnvironment.equipment" label="Equipamiento" :rows="2" />`; status select → `<UiSelect :options="statusOptions" v-model="newEnvironment.status" label="Estado" required />`. **Edit modal (lines 262–314)**: name + code → `<UiInput>`; description → `<UiTextarea>`; status → `<UiSelect>`. **View modal (lines 317–352)**: status pill → `<UiBadge :variant="getStatusVariant(viewingEnvironment.status)" :label="getStatusText(viewingEnvironment.status)" />` (no raw form fields). Keep all `v-model=` bindings + `required` attributes byte-for-byte. Add `UiInput` / `UiTextarea` to import block (UiSelect already imported).
- [ ] **T2.11** (AMB-02-008): Replace `text-red-500` (line 203) → `text-systemRed-600`; `text-green-500` (line 207) → `text-systemGreen-600` on the audit log change-diff. Precedent wins: the proven language uses `-600` shade for text on canvas.

## Phase 4: VERIFY

- [ ] **T2.12** (AMB-02-001..008): Run `php artisan test --filter='EnvironmentsAppShellTest|EnvironmentsModalChromeTest|EnvironmentsStatusBadgeTest|LegacyAliasForbiddenTest'` — all green. Run full `php artisan test --testsuite=Unit` — no regressions. Run `pnpm build` — clean.
- [ ] **T2.13** (AMB-02-001..008): Visual verification at 1440x900 (login as `admin@test.com`). Cover: list page (`/environments`), detail page (`/environments/:id`), open each of 3 modals (New, Edit, View), switch between Datos / Historial de auditoría tabs. Save screenshots to `.playwright-cli/screenshots-rollout/environment-detail-{datos,audit}-1440x900.png` + `environments-modal-{new,edit,view}-1440x900.png`. Manual smoke test: open `/environments/:id`, switch to audit tab, verify `getDentalChairAuditLogs(chairId)` loads within 1 second; verify create / update / delete toasts still fire on list page.

## Phase 5: COMMIT

- [ ] **T2.14**: Commit (no `Co-Authored-By`): `feat(ui): ambientes detail + 3 modals (AMB-02-*)`.

---

## Out of scope (do NOT include)

- `<RoleBanner>` primitive extraction (no primitive yet; defer to global composable-extraction slice)
- `useEnvironmentStatuses` shared composable (global §11 forbids new composables; defer)
- Dark mode (light-only by design)
- Audit log pagination (out of scope for visual polish)
- `DentalChair::code` client-side uniqueness validation (server-side 422 + `useToast` is the contract)
- Settings/branches + Settings/payment-methods (per global OQ#3 — OUT of scope)
- Two-tone numerals (D12 REVERSIBLE — rejected per global proposal)
- New primitives (global §11 forbids; the 10-primitive set is consumed as-is)
- Mobile capture (admin surface, no documented responsive behaviour; 1440x900 desktop only)

---

## Notes for apply phase

1. **Strict ordering**: T1.5 (canvasRoutes fix) MUST be the FIRST GREEN task in PR-ambientes-01. Subsequent category PRs in the global chain get the fix for free without touching `AppLayout.vue` again.
2. **DLR-AMB-005 exceptions**: T2.4 + T2.5 are the ONLY `<script>` edits in the entire AMBIENTES rollout. Every other `<script>` line of both pages stays byte-for-byte.
3. **`getStatusColor` → `getStatusVariant` rename**: T1.12 (status pill migration in PR-01) depends on T2.4 (helper rename in PR-02). The cleanest ordering is to apply T2.4 in PR-01 alongside T1.12 — but the task numbers keep the rename aligned with the spec row (AMB-02-001). Apply phase: do the rename BEFORE the status pill template edit.
4. **`v-model` byte-for-byte**: T2.10 MUST preserve every `v-model=` binding + `required` attribute. The 4 New modal fields (`newEnvironment.name` / `.description` / `.equipment` / `.status`) and the 4 Edit modal fields (`editingEnvironment.name` / `.code` / `.description` / `.status`) keep their reactivity. `EnvironmentsModalChromeTest` grep-verifies `v-model=` is still present on every migrated field.
5. **Test count delta**: PR-ambientes-01 adds 3 new test files (EnvironmentsAppShellTest + EnvironmentsStatusBadgeTest + EnvironmentsCanvasRoutesPrefixTest) + extends 2 existing tests (AppLayoutCanvasRoutesTest + LegacyAliasForbiddenTest). PR-ambientes-02 adds 1 new test file (EnvironmentsModalChromeTest) + extends 2 existing tests (EnvironmentsAppShellTest + LegacyAliasForbiddenTest). Total: +4 new test files, +4 test extensions. Per spec acceptance criteria §7: test count delta ≥ +20 vs PR0 baseline.
6. **Precedent — vertical slice archive-report** at lines 47–57 names 3 defects that all shared one root cause: a test that pins an example instead of the rule. AMBIENTES asserts rules (token reference exists, alias absent, primitive wrapper present), not literal strings. `EnvironmentsAppShellTest` extends `ModuleAppShellTestCase` and inherits the 5 DLR-R rule assertions; the per-PR-only assertions add category-specific edges.

---

## Key Learnings

1. The `canvasRoutes` detail-route pattern is the load-bearing cross-cutting fix of the AMBIENTES category: 1 `matchesCanvasRoute(path)` helper covers 6 detail routes globally (`/environments/:id`, `/patients/:id`, `/professionals/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary) — subsequent category PRs in the global chain get the fix for free without touching `AppLayout.vue` again.
2. The DLR-AMB-005 exceptions (`getStatusColor` → `getStatusVariant` rename + `getAuditActionVariant` `'secondary'` → `'neutral'` mapping) are 1-line mechanical edits with zero behavioural drift; documenting them as exceptions is cheaper than inline v-if workarounds in the template that would duplicate logic across 2 files.
3. Zero `<style scoped>` blocks, zero `@apply`, zero `@keyframes` in the ambientes files — cleanest starting point in the rollout; global OQ#9 grandfather clause does NOT apply, and the `ModuleAppShellTestCase::test_no_style_scoped` rule passes by default.
4. Two-page CRUD pattern (list + detail + form modals + audit log tab) is identical across Ambientes + AppointmentTypes + Profesionales — validates global PR4 triplet grouping; per-category 2-PR splits inside ~380-line global PR4 budget.
5. EnvironmentsPage.vue imports `UiSelect`, `UiEmptyState`, `UiLoadingSpinner` but uses bespoke replacements — apply phase activates dormant imports rather than rewriting scripts; one-line import additions for `UiLoadingSpinner` (PR-01) + `UiInput` + `UiTextarea` (PR-02).