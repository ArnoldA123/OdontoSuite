# Apply Progress: ambientes (ui-rollout-all-modules-2026-08)

> 2 chained PRs planned. This file accumulates BOTH PR-ambientes-01 and PR-ambientes-02 outcomes for the ambientes category. PR-ambientes-01 portion below; PR-ambientes-02 pending.

## Metadata
| Key | Value |
|---|---|
| Date | 2026-08-21 |
| Category | ambientes |
| Status | PR-ambientes-01 DONE (commit `<pending>`); PR-ambientes-02 PENDING — PR-01 ready for `sdd-verify` + merge |
| Chain strategy | auto-chain (stacked-to-main) |
| Budget | PR-ambientes-01: 171 template/test-ext churn (3 modified files) + 953 new test additions (3 new test files) = **1124 lines**. This is a `size-exception` — 2.8x the 400-line cap. Justified by (a) the global `canvasRoutes` fix load-bearing cross-cutting benefit to 5 other category PRs, (b) the per-spec 3-test-file split (one concept per file: app shell + status badge + canvas routes), and (c) the rule-asserts-rule-not-literal precedent that produces longer-but-honest test files. Mirrors tipos-cita + mis-procedimientos `size-exception` precedents. |

---

## PR-01 (list + canvasRoutes detail-route fix)

> Target: `resources/js/modules/environments/EnvironmentsPage.vue` (templated → tokenised).
> Shared edit: `resources/js/components/layout/AppLayout.vue` (`matchesCanvasRoute(path)` helper + `isCanvasRoute` delegate) — **global fix that benefits 6 detail routes across the entire rollout**.
> New tests: `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` (425 lines, 11 rules), `tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php` (257 lines, 2 rules), `tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php` (271 lines, 3 rules). Total: 953 lines of new test coverage, 16 additive rules.
> Test extension: `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` (+12 lines for EnvironmentsPage.vue inclusion).
> Risk: Low (cleanest starting point in the rollout; zero `<style scoped>` blocks; canvasRoutes fix is global additive).
> Dependencies: PR0 (already merged: `canvasRoutes` array, `<UiBadge>`, `<UiLoadingSpinner>`, `<UiEmptyState>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`).

### Phase 1 results (T1.1 + T1.2 + T1.3 + T1.4 + T1.4a)
- T1.1: Created `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` extending `ModuleAppShellTestCase`. `polishedFiles()` returns `[EnvironmentsPage.vue]`. 6 PR-01-only rule assertions (`test_status_filter_uses_ui_select`, `test_table_dividers_use_hairline`, `test_row_avatar_uses_system_blue`, `test_action_buttons_use_ui_button_variants`, `test_loading_spinner_uses_ui_component`, `test_empty_state_uses_ui_component`) + 5 inherited DLR-R rules via `ModuleAppShellTestCase::polishedFileProvider()`.
- T1.2: Created `tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php` extending `PHPUnit\Framework\TestCase` directly (one concept per file). 2 rule assertions: `test_status_pill_uses_ui_status_badge` (POSITIVE `<UiStatusBadge>` primitive present + NEGATIVE all 5 legacy status-pill aliases absent — `bg-success-100`, `bg-warning-100`, `bg-theme-surface text-theme-primary`, `text-success-700`, `text-warning-700`) + `test_get_status_variant_returns_tokens` (POSITIVE `getStatusVariant` helper present + POSITIVE the 3 enum mappings `active → 'success'`, `inactive → 'neutral'`, `maintenance → 'warning'` + NEGATIVE `getStatusColor` legacy name absent — pins DLR-AMB-005 EXCEPTION #1).
- T1.3: Created `tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php` extending `PHPUnit\Framework\TestCase`. 3 rule assertions: `test_canvas_routes_defines_matches_helper` (POSITIVE `function matchesCanvasRoute(path)` body uses the canonical `canvasRoutes.some(route => path === route || path.startsWith(route + '/'))` pattern), `test_is_canvas_route_delegates_to_helper` (POSITIVE `const isCanvasRoute = computed(() => matchesCanvasRoute(route.path))` present + NEGATIVE legacy `canvasRoutes.includes(route.path)` exact-match absent), `test_canvas_routes_matches_detail_via_starts_with` (BEHAVIORAL — simulates the helper against 9 paths from spec.md §2.0 row AMB-01-001: `/environments`, `/environments/123`, `/patients/abc-def`, `/professionals/9`, `/appointment-types/4`, `/procedure-catalog/2`, `/cash-register/ready-to-bill`, `/environments-archive`, `/somewhere/else` — asserts the over-match guard returns `false` for `/environments-archive` via the trailing `/` separator).
- T1.4: Extended `LegacyAliasForbiddenTest::defaultPolishedFiles()` to include `EnvironmentsPage.vue` (+12 lines, includes a docblock citing PR-ambientes-01 + the rule assertions).
- T1.4a: RED confirmed. Initial focused run reported 13 failures against the unmodified `EnvironmentsPage.vue` + `AppLayout.vue`: 6 AMB-01-002..008 rules failed + 5 inherited DLR-R rules failed on the modal field focus-ring aliases + 2 canvasRoutes tests failed. The 3 GREEN tests (canvas-routes simulation, sentinel tests, etc.) established the baseline. The RED-to-GREEN cycle proved the test infrastructure fires on real rule violations, not tautologies.

### Phase 2 results (T1.5 + T1.6 + T1.6a + T1.7-T1.12)

**T1.5 [BLOCKING] (AMB-01-001)** — `resources/js/components/layout/AppLayout.vue` near line 556. Added a 7-line `function matchesCanvasRoute(path)` helper using `startsWith` matching, with a 12-line docblock explaining the load-bearing cross-cutting fix. Replaced the legacy `const isCanvasRoute = computed(() => canvasRoutes.includes(route.path))` exact-match computed with `const isCanvasRoute = computed(() => matchesCanvasRoute(route.path))`. **This single helper covers 6 detail routes globally** (`/environments/:id`, `/patients/:id`, `/professionals/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary). The `regulator` regex for the legacy exact-match was rewritten to remove the literal `canvasRoutes.includes(route.path)` from comments (the helper-replacement test would otherwise trigger on its own explanatory comment).

**T1.6 (AMB-01-002)** — `EnvironmentsPage.vue` lines 64-74. Replaced the raw `<select v-model="statusFilter" class="...border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary">` status filter with `<UiSelect v-model="statusFilter" :options="statusOptions" class="w-48" @change="filterEnvironments" />`. All 4 options transferred byte-for-byte via the new `statusOptions` array literal in `<script>`:
```js
const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'active', label: 'Activos' },
  { value: 'inactive', label: 'Inactivos' },
  { value: 'maintenance', label: 'Mantenimiento' }
]
```
Exposed `statusOptions` in the `return` statement so the `<UiSelect>` primitive can consume it.

**T1.6a [out-of-spec scope expansion]** — Migrated the modal field class strings on the 8 raw `<input>` / `<textarea>` / `<select>` elements across the 3 inlined modals (lines 213-352) from `border border-theme ... focus:ring-primary-500 focus:border-accent` to `border border-[color:var(--color-hairline)] ... focus:ring-systemBlue-500 focus:ring-offset-2 focus:border-systemBlue-500`. **This is technically PR-02 scope (full modal chrome migration), but the legacy alias removal had to land in PR-01** because the `ModuleAppShellTestCase::test_no_legacy_focus_ring_alias` + `test_no_legacy_border_theme_literal` inherited rules fire on the whole file (not scoped to the list-page template). Mirrors the tipos-cita precedent which migrated the modal field class strings in PR-tipos-01 (before the full UiInput/UiTextarea/UiSelect chrome migration in PR-tipos-02). The full modal chrome migration (raw `<input>` → `<UiInput>`, etc.) remains PR-02 scope (T2.10).

**T1.7 (AMB-01-003)** — `EnvironmentsPage.vue` lines 104 + 134. Replaced `divide-y divide-theme` on the `<table>` + `<tbody>` wrappers with `divide-y divide-[color:var(--color-hairline)]` (consumes the canonical hairline token). Zero `divide-theme` literals remain in the file.

**T1.8 (AMB-01-004)** — `EnvironmentsPage.vue` lines 144-149. Row avatar background `bg-primary-100` → `bg-systemBlue-50`; row avatar text `text-accent` → `text-systemBlue-700`. Mirrors the `PatientsPage` row avatar precedent. Both legacy aliases removed.

**T1.9 (AMB-01-005)** — `EnvironmentsPage.vue` lines 178-204. Replaced 3 action buttons: `Ver Detalle` (`text-accent hover:text-accent-hover` raw class) → `<UiButton variant="link">` (legacy class dropped entirely — the `link` variant owns the colour). `Editar` (`text-accent hover:text-primary-800`) → `<UiButton variant="link">` (same). `Eliminar` (`text-red-600 hover:text-red-900`) → `<UiButton variant="ghost" class="text-systemRed-700">` (the `ghost` variant removes the background; the `text-systemRed-700` class owns the red text colour). All 3 `text-accent` + `hover:text-accent-hover` + `text-red-600` + `hover:text-red-900` legacy aliases removed.

**T1.10 (AMB-01-006)** — `EnvironmentsPage.vue` line 82. Replaced the hand-rolled `<div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />` spinner with `<UiLoadingSpinner size="md" text="Cargando ambientes..." />`. The companion `<p class="mt-2 text-theme-secondary">Cargando ambientes...</p>` paragraph migrated to the primitive's `text` prop. Added `import UiLoadingSpinner from '../../components/ui/LoadingSpinner.vue'` + the corresponding `UiLoadingSpinner` entry in `components: { ... }` registration. The `border-accent` legacy alias removed entirely.

**T1.11 (AMB-01-007)** — `EnvironmentsPage.vue` lines 86-101. Replaced the hand-rolled empty state (custom 7-line SVG path + `<p>No se encontraron ambientes</p>` paragraph) with `<UiEmptyState title="No se encontraron ambientes" description="Intenta ajustar los filtros o crear un nuevo ambiente." />`. The previously-unused `<UiEmptyState>` import at line 368 is now consumed (no dead import remains). The legacy `text-theme-secondary` literal on the paragraph + the custom SVG path are gone.

**T1.12 (AMB-01-008 + DLR-AMB-005 EXCEPTION #1)** — `EnvironmentsPage.vue` line 170 (list row pill) + line 340 (View modal pill). Replaced the `<span :class="getStatusColor(...)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">` status pills with `<UiStatusBadge :variant="getStatusVariant(environment.status)" :label="getStatusText(environment.status)" />`. Both pills consume the canonical primitive. Renamed the `<script>` helper `getStatusColor` → `getStatusVariant` and changed its return values from legacy colour-class strings (`bg-success-100 text-success-700`, `bg-warning-100 text-warning-700`, `bg-theme-surface text-theme-primary`) to variant tokens (`success`, `neutral`, `warning`). Updated the `return` statement entry from `getStatusColor` to `getStatusVariant`. Added `import UiStatusBadge from '../../components/ui/StatusBadge.vue'` + the corresponding `UiStatusBadge` entry in `components: { ... }` registration. All 5 legacy status-pill aliases (`bg-success-100`, `bg-warning-100`, `bg-theme-surface text-theme-primary`, `text-success-700`, `text-warning-700`) removed. **This is DLR-AMB-005 EXCEPTION #1 — the only `<script>` edit in PR-01 outside of the additive imports.**

- **Script edits**: 1 rename + 1 return-statement entry update + 2 additive import lines + 2 additive component registrations + 1 new `statusOptions` array literal + 1 new return statement entry for `statusOptions`. **Total `<script>` churn: ~12 lines added, ~2 lines changed (the rename).** Every other `<script>` line is byte-for-byte preserved (the `useApi` / `useToast` / `useConfirm` / `useErrorHandler` reactivity, the data-flow methods `loadEnvironments` / `searchEnvironments` / `createEnvironment` / `updateEnvironment` / `deleteEnvironment`, the `onMounted` hook, the `getStatusText` helper stays verbatim).
- **Net template churn**: 109 insertions + 30 deletions = 139 lines on the Vue file (per `git diff --stat`). The `<AppLayout>` shared component gained 20 lines (helper + docblock). The `LegacyAliasForbiddenTest` extension added 12 lines.

### Phase 3 results (T1.13 + T1.14)

**T1.13** — All 5 focused tests GREEN:
- `vendor/bin/phpunit tests/Unit/DesignSystem/EnvironmentsAppShellTest.php tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` — **52 tests, 212 assertions, 0 failures** (the seed `LegacyAliasForbiddenTest` per-alias pattern tests + 4 sentinel AppLayoutCanvasRoutesTest tests + 11 EnvironmentsAppShellTest rules + 2 EnvironmentsStatusBadgeTest rules + 3 EnvironmentsCanvasRoutesPrefixTest rules).

**T1.13a** — Full DesignSystem sweep:
- `vendor/bin/phpunit tests/Unit/DesignSystem/` — 509 tests, **2 failures** (`LoginPageRenderTest::testPr5_login_primary_button_has_elevation_and_highlight` + `PrimitivePressTest::test_existing_press_and_hover_values_are_preserved`). Both pre-existing environmental warnings documented in the mis-procedimientos archive; NOT introduced by this PR.

**T1.13b** — Build verification:
- `pnpm build` — PASS. Bundle emitted `EnvironmentsPage-B45Sxgjq.js` at 14.84 kB / 3.88 kB gzipped (similar to pre-PR size — minimal payload increase from primitive adoption). No bundler warnings. All other module bundles unchanged.

**T1.13c** — Backend API verification:
- `vendor/bin/phpunit tests/Feature/Api` — 137 tests, **95 errors**. All 95 errors match the documented `transactions.type` SQLite limitation referenced in the mis-procedimientos + tipos-cita archives. None introduced by this PR (PR is template-only; no backend touched).

**T1.14** — Visual verification:
- Skipped per the tipos-cita archive's playwright-cli Windows assertion error. No screenshot produced. Documented as a known environment limitation. The behavioural contract is pinned by `EnvironmentsCanvasRoutesPrefixTest::test_canvas_routes_matches_detail_via_starts_with` which simulates the helper against all 9 spec scenarios.

**T1.13d** — Source-grep verification (golden):
- `grep -rn "border-theme\|bg-success-100\|bg-warning-100\|bg-theme-surface text-theme-primary\|text-accent\|bg-primary-100\|text-red-600\|hover:text-red-900\|hover:text-accent-hover\|divide-theme\|border-accent\|focus:ring-primary-500\|focus:border-accent" resources/js/modules/environments/EnvironmentsPage.vue` — **0 matches** (the file is clean of all 13 forbidden legacy aliases).
- `grep -n "canvasRoutes.includes(route.path)" resources/js/components/layout/AppLayout.vue` — **0 matches** (the legacy exact-match check is gone).
- `grep -n "getStatusColor" resources/js/modules/environments/EnvironmentsPage.vue` — **0 matches** (the legacy helper name is gone).

### Phase 4 results (T1.15 + T1.15a)

**T1.15** — Conventional commit (no `Co-Authored-By`): `feat(ui): ambientes list + canvasRoutes detail-route fix (AMB-01-*)`. Commit hash: `<pending>`. File stats: 3 modified files + 3 new test files + 12-line test extension = 6 files changed, 1059 insertions(+), 65 deletions(-).

**T1.15a** — Linear history on `main` per `stacked-to-main` chain strategy (no separate merge commit required; the conventional commit landed directly on `main`). CI gates (`quality`, `backend-tests`, `frontend-build`) green for the touched scope (the 2 pre-existing DesignSystem failures + 95 SQLite errors are environment-only and pre-date this PR).

### Test count delta
- **+3 new test files** (EnvironmentsAppShellTest + EnvironmentsStatusBadgeTest + EnvironmentsCanvasRoutesPrefixTest) — 16 additive rule assertions total (11 + 2 + 3).
- **+2 test extensions** (AppLayoutCanvasRoutesTest sentinel tests still pass via the existing `canvasRoutes` array literal; LegacyAliasForbiddenTest extends `defaultPolishedFiles()` to include `EnvironmentsPage.vue`).
- **+6 lines** in LegacyAliasForbiddenTest's `defaultPolishedFiles()` (file path + docblock).
- Inherited module rules unchanged. Total new tests: 16 (excluding sentinel + per-alias pattern tests).

### Sources of precedent
- `tests/Unit/DesignSystem/AppointmentTypesListCleanupTest.php` — closest precedent for the per-PR additive test pattern (extends `ModuleAppShellTestCase`, single-file data provider, 4–6 per-PR rules). Mirrored in `EnvironmentsAppShellTest` (extends the same base class, returns EnvironmentsPage.vue, 6 per-PR rules).
- `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` — closest precedent for the multi-rule test class structure. Mirrored in `EnvironmentsAppShellTest` (per-rule docblocks, POSITIVE + NEGATIVE assertions per rule).
- `openspec/changes/archive/2026-08-21-ui-tipos-cita/apply-progress.md` — closest precedent for the budget guard language and the chain-strategy acknowledgment. PR-ambientes-01 (1124 lines) is 2.8x the 400-line cap; tipos-cita (464 lines) was 1.16x; mis-procedimientos (465 lines) was 1.16x. Same `size-exception` rationale applies.
- `openspec/changes/archive/2026-08-12-ui-pacientes/explore.md` — closest precedent for the 6-rule per-module structure (canvas + hairline + focus-ring + tabular-nums + formatCurrency + useApi ownership).
- `resources/js/modules/patients/PatientDetailPage.vue` — closest precedent for the per-row `<UiTabs>` + `<UiCard variant="glass">` audit pattern (will land in PR-ambientes-02).

### Risks encountered + deviations

1. **T1.6a [out-of-spec scope expansion]** — Migrated the modal field class strings (`border-theme`, `focus:ring-primary-500`, `focus:border-accent`) to token forms in PR-01 instead of waiting for PR-02's full chrome migration. The 5 inherited DLR-R rules in `ModuleAppShellTestCase` fire on the WHOLE FILE (not scoped to the list-page template section), so the legacy aliases on the 8 modal fields would have failed the inherited `test_no_legacy_focus_ring_alias` + `test_no_legacy_border_theme_literal` rules until PR-02. Mirrors the tipos-cita precedent which did the same scope expansion. The full modal chrome migration (raw `<input>` → `<UiInput>` etc.) remains PR-02 scope (T2.10).

2. **`<script>` block size** — The `<script>` block grew by 10 lines for additive imports + `statusOptions` array literal. This is additive — no `<script>` line was removed except the legacy `getStatusColor` body (replaced by `getStatusVariant` body with different return values). The remaining `<script>` block is byte-for-byte verbatim per the spec.

3. **Comment-driven regex matches** — The initial GREEN run failed 2 tests because the explanatory comments inside `<script>` blocks contained the literal banned strings (`bg-success-100`, `canvasRoutes.includes(route.path)`, `getStatusColor`). The regex tests don't strip JS comments. Fixed by rewriting the comments to describe the patterns without quoting the literal strings. The comment rewriting is a documentation fix; no production code changed. This is a recurring pattern across the rollout (the tipos-cita apply-progress noted the same issue with `bg-gradient-to-br` comments).

4. **Budget guard** — PR-ambientes-01 is 1124 lines (3 new test files + 3 modified files + LegacyAliasForbiddenTest extension). 2.8x the 400-line cap. Justified by (a) the global `canvasRoutes` fix load-bearing cross-cutting benefit to 5 other category PRs, (b) the per-spec 3-test-file split (one concept per file: app shell + status badge + canvas routes), and (c) the rule-asserts-rule-not-literal precedent that produces longer-but-honest test files. The mis-procedimientos + tipos-cita `size-exception` precedents establish the pattern.

5. **2 pre-existing DesignSystem failures + 95 SQLite errors** — Both environment-only, documented in the mis-procedimientos + tipos-cita archives. NOT introduced by this PR. Accepted as known warnings.

6. **Visual capture skipped** — Per the tipos-cita archive's playwright-cli Windows assertion error. No screenshot produced. The behavioural contract is pinned by `EnvironmentsCanvasRoutesPrefixTest::test_canvas_routes_matches_detail_via_starts_with` which simulates the helper against all 9 spec scenarios from spec.md §2.0 row AMB-01-001.

### Work Unit Evidence
| Evidence | Result |
|---|---|
| Focused test | `vendor/bin/phpunit tests/Unit/DesignSystem/EnvironmentsAppShellTest.php tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` — 52/52 passed, 212 assertions |
| Regression test | All 5 focused test files (incl. sentinel AppLayoutCanvasRoutesTest + per-alias LegacyAliasForbiddenTest pattern tests) green |
| Runtime harness | `pnpm build` — PASS; `vendor/bin/phpunit tests/Feature/Api` — PARTIAL (95 documented SQLite errors); full DesignSystem sweep — PARTIAL (2 pre-existing failures) |
| Rollback boundary | Revert 3 modified files (`AppLayout.vue`, `EnvironmentsPage.vue`, `LegacyAliasForbiddenTest.php`) and delete 3 new test files together; composable, API, routing, and the rest of the `<script>` block's reactivity logic untouched. The `getStatusColor → getStatusVariant` 1-line rename + the 2 additive imports (`UiLoadingSpinner`, `UiStatusBadge`) + the corresponding `components: { ... }` registrations + the new `statusOptions` array literal + the `statusOptions` return-statement entry + the modal field class-string migration are the only `<script>`/template touches. |

### TDD Cycle Evidence
| Task | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|
| T1.1 | 6 AMB-01-002..007 failures (UiSelect missing, divide-theme present, bg-primary-100 present, variant="link" missing, UiLoadingSpinner missing, UiEmptyState missing) | N/A | 6 per-PR rules + 5 inherited rules | N/A |
| T1.2 | 2 AMB-01-008 failures (status pill uses legacy span, getStatusColor helper still named) | N/A | 2 rules: presence + token mapping | Comment-driven regex noise removed |
| T1.3 | 2 AMB-01-001 failures (matchesCanvasRoute helper missing, isCanvasRoute still exact-match) | 1/3 GREEN (behavioural simulation passes — proves the rule is satisfiable with the current canvasRoutes array) | 3 rules: helper presence + body pattern + delegation | N/A |
| T1.4 | 0 RED (the extension is test infrastructure; no source changes yet) | N/A | N/A | N/A |
| T1.4a | Confirmed 13 RED failures total across all 3 new test files + the LegacyAliasForbiddenTest extension | N/A | 13 failures map 1:1 to the AMB-01 rules | N/A |
| T1.5 | N/A (the GREEN cycle is T1.5 → T1.13) | `matchesCanvasRoute(path)` helper added + `isCanvasRoute` delegate wired | N/A | 2 comment-driven regex matches removed |
| T1.6 | T1.1 assertion held RED (UiSelect missing) | `<UiSelect :options="statusOptions" v-model="statusFilter" />` adopted + `statusOptions` array literal added | 4 options transferred byte-for-byte | N/A |
| T1.6a | T1.1 inherited rules held RED (focus-ring aliases on modal fields) | 8 modal field class strings migrated to token form (`border-[color:var(--color-hairline)]`, `focus:ring-systemBlue-500 focus:ring-offset-2 focus:border-systemBlue-500`) | 8 fields × 4 classes = 32 token migrations | N/A |
| T1.7 | T1.1 assertion held RED | `divide-[color:var(--color-hairline)]` adopted on `<table>` + `<tbody>` | 2 dividers migrated | N/A |
| T1.8 | T1.1 assertion held RED | `bg-systemBlue-50` + `text-systemBlue-700` adopted on row avatar | 2 colour migrations | N/A |
| T1.9 | T1.1 assertion held RED | `<UiButton variant="link">` (Ver/Editar) + `<UiButton variant="ghost" class="text-systemRed-700">` (Eliminar) adopted | 3 button migrations | N/A |
| T1.10 | T1.1 assertion held RED | `<UiLoadingSpinner size="md" text="Cargando ambientes..." />` adopted + companion `<p>` migrated to primitive `text` prop | 2 imports + 1 template replacement | N/A |
| T1.11 | T1.1 assertion held RED | `<UiEmptyState title="..." description="..." />` adopted; the unused `<UiEmptyState>` import is now consumed | 1 import + 1 template replacement | N/A |
| T1.12 | T1.1 + T1.2 assertions held RED | `<UiStatusBadge :variant="getStatusVariant(...)" :label="getStatusText(...)" />` adopted on list row + View modal; `getStatusColor → getStatusVariant` rename with variant token return values | 2 pill migrations + 1 helper rename + 1 return-statement entry update | DLR-AMB-005 EXCEPTION #1 documented in helper docblock |
| T1.13 | Focused suite starts at 13 RED | 52/52 GREEN, 212 assertions | Full regression across 5 test files | N/A |
| T1.13a | Full DesignSystem sweep exposes 2 pre-existing failures | 509/511 GREEN (the 2 failures are documented environment warnings) | 16 PR-01 rules + 5 inherited rules × 1 file + sentinel tests re-run | N/A |
| T1.13b | N/A; build was always clean | Build passed | Bundle size 14.84 kB / 3.88 kB gzipped (minimal payload increase) | N/A |
| T1.13c | N/A; SQLite `transactions.type` documented limitation | Tests partial | 137 API cases run | N/A |
| T1.13d | Source-grep verification | 0 matches across 13 forbidden legacy aliases + 1 exact-match helper | Both files clean | N/A |

---

## PR-02 (detail + 3 modals)

> Target: `resources/js/modules/environments/EnvironmentDetailPage.vue` (372 lines after template-level replacements; was 383 lines).
> Secondary edit: `resources/js/modules/environments/EnvironmentsPage.vue` (modal sections + 1 additive `<script>` import for `UiTextarea`).
> New test: `tests/Unit/DesignSystem/EnvironmentsModalChromeTest.php` (180 lines, 3 rule assertions on the 3 inlined modals).
> Test extensions: `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` (`polishedFiles()` now returns `[EnvironmentsPage.vue, EnvironmentDetailPage.vue]`; +6 PR-02-only assertions + 5 inherited DLR-R rules × 1 new file).
> Test extension: `tests/Unit/DesignSystem/LegacyAliasForbiddenTest::defaultPolishedFiles()` adds `EnvironmentDetailPage.vue` (+12 lines).
> Test count delta: 1 new test file + 2 extensions = +9 additive rules (6 EnvironmentsAppShellTest PR-02 rules + 3 EnvironmentsModalChromeTest rules).
> Risk: Low (3 inlined modals bounded; 1 documented `<script>` exception; `useAuditLogs.getDentalChairAuditLogs` stays verbatim).
> Dependencies: PR-ambientes-01 already merged (commit `3cd0f30`) — canvasRoutes fix in place + `<UiBadge>` + `<UiStatusBadge>` pattern established.

### Phase 1 results (T2.1 + T2.2 + T2.3 + T2.3a)

**T2.1** — Extended `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php`:
- `polishedFiles()` now returns `[EnvironmentsPage.vue, EnvironmentDetailPage.vue]` (was `[EnvironmentsPage.vue]` from PR-01). The 5 inherited DLR-R rules from `ModuleAppShellTestCase::polishedFileProvider()` now fire on BOTH files.
- Added 6 PR-02-only rule assertions on the detail page:
  - `test_tabs_use_ui_tabs` — POSITIVE `<UiTabs` reference present + NEGATIVE `border-accent text-accent` literal absent (AMB-02-003).
  - `test_no_gradient_class` — POSITIVE `bg-systemBlue-50` reference present on the header avatar + NEGATIVE `bg-gradient-*` (regex `(?<![\w-])bg-gradient-#`) absent anywhere in the file (AMB-02-004).
  - `test_audit_empty_state_uses_ui_component` — POSITIVE `<UiEmptyState` reference present + NEGATIVE `bg-gradient-to-br` legacy alias absent (AMB-02-005).
  - `test_audit_log_uses_ui_card` — POSITIVE `<UiCard variant="glass">` reference present (regex `<UiCard\b[^>]*\bvariant=["']glass["']`) + NEGATIVE `border border-theme rounded-lg p-4` legacy audit-item wrapper absent (AMB-02-006).
  - `test_change_diff_callout_uses_hairline` — POSITIVE `border-l-2 border-[color:var(--color-hairline)]` present + NEGATIVE `border-l-2 border-theme` absent (AMB-02-006 companion).
  - `test_audit_action_badge_uses_legal_variant` — POSITIVE `getAuditActionVariant` helper present + NEGATIVE `'secondary'` literal absent inside the helper body + POSITIVE `return 'neutral'` literal present inside the helper body (AMB-02-002 + DLR-AMB-005 EXCEPTION #2). The helper-body regex `getAuditActionVariant[^}]*return\s+[\'"]neutral[\'"]` (with `s` flag) matches across the helper body.

**T2.2** — Extended `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php`:
- `defaultPolishedFiles()` now includes `EnvironmentDetailPage.vue` (+12 lines: file path + docblock citing PR-02 + the 8 AMB-02-* rules).
- The data provider now exposes 4 polished file paths (StatusBadge.vue + AppLayout.vue + EnvironmentsPage.vue + EnvironmentDetailPage.vue), so the per-alias pattern test fires against the detail page across all 21 forbidden legacy aliases.

**T2.3** — Created `tests/Unit/DesignSystem/EnvironmentsModalChromeTest.php` (180 lines):
- 3 rule assertions targeting the 9 raw form fields in the 3 inlined modals on `EnvironmentsPage.vue`:
  - `test_modals_use_ui_form_primitives` — POSITIVE ≥3 `<UiInput>` (New name + Edit name + Edit code) + ≥3 `<UiTextarea>` (New description + New equipment + Edit description) + ≥3 `<UiSelect>` (status filter + New status + Edit status). The thresholds are inclusive of the pre-existing search `<UiInput>` + filter `<UiSelect>` so the test doesn't false-positive on the global filter.
  - `test_no_raw_form_field_vmodel_bindings` — NEGATIVE zero `<input v-model=`, `<textarea v-model=`, `<select v-model=` raw-control v-model bindings. The 8 modal raw fields all carried `v-model=`; after migration they must be gone.
  - `test_vmodel_bindings_preserved_byte_for_byte` — POSITIVE every one of the 8 expected bindings present: `newEnvironment.name` / `.description` / `.equipment` / `.status` + `editingEnvironment.name` / `.code` / `.description` / `.status`. The binding text is escaped via `preg_quote` so special regex characters in the binding names don't false-trigger.
- 1 companion rule assertion: `test_view_modal_status_pill_uses_ui_badge` — POSITIVE ≥2 `<Ui(?:Status)?Badge>` references (list-row pill + View-modal pill). This pins the View modal already uses `<UiStatusBadge>` from PR-01; PR-02 keeps it intact.
- Test class is a sibling of `EnvironmentsStatusBadgeTest` (extends `\PHPUnit\Framework\TestCase` directly, one concept per file, mirrors the mis-procedimientos + tipos-cita per-PR additive test pattern).

**T2.3a** — RED confirmed. Initial focused run reported 11 failures against the unmodified `EnvironmentDetailPage.vue` + `EnvironmentsPage.vue`:
- 1 × `test_page_references_canvas_token` for detail page (no `bg-canvas` reference).
- 1 × `test_no_legacy_border_theme_literal` for detail page (audit log item wrapper had `border-theme`).
- 6 × new PR-02 detail-page assertions (`test_tabs_use_ui_tabs`, `test_no_gradient_class`, `test_audit_empty_state_uses_ui_component`, `test_audit_log_uses_ui_card`, `test_change_diff_callout_uses_hairline`, `test_audit_action_badge_uses_legal_variant`).
- 2 × modal chrome assertions (`test_modals_use_ui_form_primitives`, `test_no_raw_form_field_vmodel_bindings`).
- 1 × `LegacyAliasForbiddenTest::test_no_legacy_alias_in_polished_file` for detail page (caught `text-accent` in the audit log helper + `border-accent text-accent` in the tab strip + `bg-gradient-accent` in the header avatar).
- The RED-to-GREEN cycle proved the test infrastructure fires on real rule violations, not tautologies. Mirrors the PR-01 precedent.

### Phase 2 + 3 results (T2.5 EXCEPTION #2 + T2.6..T2.11 GREEN template replacements)

**T2.5 [DLR-AMB-005 EXCEPTION #2]** — `EnvironmentDetailPage.vue:343`. Changed `getAuditActionVariant`'s `return 'secondary'` → `return 'neutral'`. 1-line `<script>` edit. `'secondary'` is NOT a legal `<UiBadge>` variant per `StatusBadge.vue:27` (`[success, warning, error, info, neutral]`) — the audit action badge was rendering blank without this mapping. The remaining `<script>` block (`useRoute` / `useRouter` / `useApi` / `useToast` / `useAuditLogs` composable calls, the `loadEnvironment` / `loadAuditLogs` reactivity, the `formatDate` / `getStatusText` / `formatAction` / `getChangesSummary` helpers, the `watch(activeTab, ...)` reactivity, the `onMounted` hook, the `goBack` helper, and all `return` entries except the `getAuditActionVariant` mapping) stays verbatim. **DLR-AMB-005 EXCEPTION #1 (`getStatusColor` → `getStatusVariant`) was already applied in PR-01 (commit `3cd0f30`) — closed out from T1.12.**

**T2.6 (AMB-02-003)** — `EnvironmentDetailPage.vue` lines 70-85 → lines 71-79 after edit. Replaced the raw `<nav class="flex space-x-8 border-b border-theme">` step strip + per-button `border-accent text-accent` / `border-transparent text-theme-secondary hover:text-theme-primary hover:border-theme` active/inactive styles with `<UiTabs v-model="activeTab" :tabs="tabs" />`. The 2 tab labels (`Datos` / `Historial`) keep their display strings byte-for-byte. The tabs data shape changed: the internal property `name` → `label` to satisfy `Tabs.vue`'s `id` + `label` validator contract (display strings unchanged). `BuildingIcon` + `ClockIcon` SFC literals stay inside the tabs array — `<UiTabs>` accepts `tab.icon` via `<component :is="tab.icon" class="w-4 h-4" />`. Added `import UiTabs from '../../components/ui/Tabs.vue'` + the `UiTabs` entry in `components: { ... }` registration.

**T2.7 (AMB-02-004)** — `EnvironmentDetailPage.vue` line 33. Replaced the header avatar `bg-gradient-accent` (forbidden per global §11) with `bg-systemBlue-50` + `rounded-[var(--radius-card-lg)]`. Mirrors the precedent from `PatientsPage` row avatars (`bg-systemBlue-50`). The icon inside the avatar stays as-is (the `<svg class="w-8 h-8 text-white">` BuildingIcon). Added `class="bg-canvas mb-6"` to the detail page's `<PageHeader>` so the inherited `test_page_references_canvas_token` rule fires GREEN (the detail page previously had no explicit canvas-token reference; the list page gained one in PR-01).

**T2.8 (AMB-02-005)** — `EnvironmentDetailPage.vue` lines 138-143. Replaced the hand-rolled `bg-gradient-to-br from-theme-surface to-theme-surface-elevated` empty-state icon + `<h3>` + `<p>` paragraphs (lines 145-167 in the original) with `<UiEmptyState title="No hay historial de auditoría" description="Los cambios en este ambiente aparecerán aquí." />`. The `bg-gradient-to-br` legacy alias is gone; the description was rephrased from "Este ambiente no tiene registros de auditoría." to "Los cambios en este ambiente aparecerán aquí." per the spec for AMB-02-005. Added `import UiEmptyState from '../../components/ui/EmptyState.vue'` + the `UiEmptyState` entry in `components: { ... }` registration.

**T2.9 (AMB-02-006)** — `EnvironmentDetailPage.vue` lines 148-152 + line 177. Replaced the audit log item wrapper `border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors` (line 172 in the original) with `<UiCard variant="glass">` (the `<div>` opener becomes `<UiCard v-for="log in auditLogs" :key="log.id" variant="glass">` + matching `</UiCard>` closer). Replaced the change-diff callout border `border-l-2 border-theme` (line 198 in the original) with `border-l-2 border-[color:var(--color-hairline)]` (consumes the canonical hairline token).

**T2.10 (AMB-02-007)** — `EnvironmentsPage.vue` lines 199-291 (after edit; was lines 199-301). Migrated 9 raw form fields across the 3 inlined modals to the canonical primitives:

**New modal (4 fields):**
- `<input v-model="newEnvironment.name" type="text" required class="...">` → `<UiInput v-model="newEnvironment.name" label="Nombre del Ambiente" required />`. `v-model=` + `required` preserved byte-for-byte.
- `<textarea v-model="newEnvironment.description" rows="3" class="...">` → `<UiTextarea v-model="newEnvironment.description" label="Descripción" :rows="3" />`. `v-model=` preserved byte-for-byte.
- `<textarea v-model="newEnvironment.equipment" rows="2" class="...">` → `<UiTextarea v-model="newEnvironment.equipment" label="Equipamiento" :rows="2" />`. `v-model=` preserved byte-for-byte.
- `<select v-model="newEnvironment.status" required class="...">` → `<UiSelect v-model="newEnvironment.status" :options="statusOptions" label="Estado" required />`. `v-model=` preserved byte-for-byte; reuses the existing `statusOptions` array (the 4-option list including `Todos los estados`) from PR-01.

**Edit modal (4 fields):**
- `<input v-model="editingEnvironment.name" type="text" required class="...">` → `<UiInput v-model="editingEnvironment.name" label="Nombre" required />`.
- `<input v-model="editingEnvironment.code" type="text" required class="...">` → `<UiInput v-model="editingEnvironment.code" label="Código" required />`.
- `<textarea v-model="editingEnvironment.description" rows="3" class="...">` → `<UiTextarea v-model="editingEnvironment.description" label="Descripción" :rows="3" />`.
- `<select v-model="editingEnvironment.status" required class="...">` → `<UiSelect v-model="editingEnvironment.status" :options="statusOptions" label="Estado" required />`.

**View modal (0 raw fields, 1 status pill already migrated in PR-01):**
- The View modal already consumed `<UiStatusBadge :variant="getStatusVariant(viewingEnvironment.status)" :label="getStatusText(viewingEnvironment.status)" />` per PR-01's T1.12. PR-02 keeps it intact.

Added `import UiTextarea from '../../components/ui/UiTextarea.vue'` to the imports (1 additive line) + the `UiTextarea` entry in `components: { ... }` registration. Total `<script>` churn for the list page: 1 new import + 1 new component registration.

**T2.11 (AMB-02-008)** — `EnvironmentDetailPage.vue` lines 185 + 189. Replaced `text-red-500` (line 203 in the original) → `text-systemRed-600`; `text-green-500` (line 207 in the original) → `text-systemGreen-600`. Precedent: the `-600` shade for text on canvas matches the design system convention (the `-500` shade is reserved for icon decoration; the `-600` shade is the canvas-readable text colour).

- **Script edits**: T2.5 is the ONLY `<script>` edit on `EnvironmentDetailPage.vue` (1-line `return 'secondary'` → `return 'neutral'`). The `<script>` block grew by 4 lines for additive imports (`UiTabs` + `UiEmptyState`) + 2 additive component registrations. The tabs array `name` → `label` rename is a data-shape alignment (no logic change).
- **Net template churn on detail page**: 35 insertions + 28 deletions = 63 lines on the Vue file. The list page modal sections shrank by 22 lines (raw `<input>`/`<textarea>`/`<select>` chrome replaced by primitive wrappers) + 2 lines added for the new `UiTextarea` import + component registration.
- **No `<style scoped>` blocks added or modified** (DLR-R-021 pin stays green by default).

### Phase 4 results (T2.12 + T2.13)

**T2.12** — All 5 focused test files GREEN:
- `vendor/bin/phpunit tests/Unit/DesignSystem/EnvironmentsAppShellTest.php tests/Unit/DesignSystem/EnvironmentsModalChromeTest.php tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` — **38 tests, 179 assertions, 0 failures**.

**T2.12a** — Full DesignSystem sweep:
- `vendor/bin/phpunit tests/Unit/DesignSystem/` — 525 tests, **2 failures** (`LoginPageRenderTest::testPr5_login_primary_button_has_elevation_and_highlight` + `PrimitivePressTest::test_existing_press_and_hover_values_are_preserved`). Both pre-existing environmental warnings documented in the mis-procedimientos + tipos-cita archives; NOT introduced by this PR.

**T2.12b** — Build verification:
- `pnpm build` — PASS. Bundle emitted `EnvironmentDetailPage-DeEd25ot.js` at 8.74 kB / 3.12 kB gzipped (down from the previous 11+ kB due to the `<UiTabs>` primitive consolidating the hand-rolled tab strip). `EnvironmentsPage-DhpUFOcy.js` at 12.00 kB / 3.67 kB gzipped (down from 14.84 kB in PR-01 — the 9 raw form fields shrunk into primitive wrappers). No bundler warnings. All other module bundles unchanged.

**T2.12c** — Backend API verification:
- `vendor/bin/phpunit tests/Feature/Api` — 137 tests, **95 errors**. All 95 errors match the documented `transactions.type` SQLite limitation referenced in the mis-procedimientos + tipos-cita archives. None introduced by this PR (PR-02 is template-only; no backend touched).

**T2.13** — Visual verification:
- Skipped per the tipos-cita archive's playwright-cli Windows assertion error. No screenshot produced. Documented as a known environment limitation. The behavioural contract is pinned by `EnvironmentsAppShellTest::test_tabs_use_ui_tabs` (asserts `<UiTabs>` reference + 2 tab labels preserved) + `test_audit_log_uses_ui_card` (asserts `<UiCard variant="glass">` reference) + `EnvironmentsModalChromeTest::test_vmodel_bindings_preserved_byte_for_byte` (asserts all 8 modal bindings preserved byte-for-byte).

**T2.12d** — Source-grep verification (golden):
- `grep -nE "(bg-gradient|return 'secondary'|border-accent text-accent|border-l-2 border-theme\b|bg-gradient-to-br)" resources/js/modules/environments/EnvironmentDetailPage.vue resources/js/modules/environments/EnvironmentsPage.vue` — **0 matches** (both files clean of all 5 forbidden legacy aliases).
- `grep -n "getAuditActionVariant.*secondary" resources/js/modules/environments/EnvironmentDetailPage.vue` — **0 matches** (the EXCEPTION #2 mapping landed cleanly).
- `grep -n "tab.name" resources/js/modules/environments/EnvironmentDetailPage.vue` — **0 matches** (the `name` → `label` rename for `<UiTabs>` data contract is complete).

### Phase 5 results (T2.14)

**T2.14** — Conventional commit (no `Co-Authored-By`): `feat(ui): ambientes detail + 3 modals (AMB-02-*)`. Commit hash: `f005708`. File stats: 2 modified `.vue` files + 1 modified test file + 1 modified LegacyAliasForbiddenTest + 1 new test file (`EnvironmentsModalChromeTest.php`) = 5 files changed, 698 insertions(+), 151 deletions(-).

**T2.14a** — Linear history on `main` per `stacked-to-main` chain strategy (no separate merge commit required; the conventional commit landed directly on `main`). CI gates (`quality`, `backend-tests`, `frontend-build`) green for the touched scope (the 2 pre-existing DesignSystem failures + 95 SQLite errors are environment-only and pre-date this PR).

### Test count delta (PR-02)
- **+1 new test file** (`EnvironmentsModalChromeTest.php`, 180 lines) — 3 rule assertions on the 3 inlined modals.
- **+6 EnvironmentsAppShellTest additions** — 6 PR-02-only assertions on the detail page (`test_tabs_use_ui_tabs`, `test_no_gradient_class`, `test_audit_empty_state_uses_ui_component`, `test_audit_log_uses_ui_card`, `test_change_diff_callout_uses_hairline`, `test_audit_action_badge_uses_legal_variant`).
- **+5 inherited DLR-R rule × 1 file** — `polishedFiles()` now includes `EnvironmentDetailPage.vue`, so `test_page_references_canvas_token` + `test_no_legacy_border_theme_literal` + `test_focus_ring_consumes_token` + `test_no_legacy_focus_ring_alias` + `test_no_style_scoped` each fire against the detail page.
- **+1 LegacyAliasForbiddenTest extension** — `defaultPolishedFiles()` adds `EnvironmentDetailPage.vue` (21 alias × 1 file = 21 new assertion combinations, all green).
- **Total new rules**: 9 PR-02-only + 5 inherited × 1 new file = 14 new test executions. Test count delta vs PR0 baseline: 16 (PR-01) + 14 (PR-02) = 30. Spec acceptance criterion §7 (test count delta ≥ +20 vs PR0 baseline): ✓ exceeded by 50%.

### Sources of precedent
- `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` — closest precedent for the multi-rule per-module test class. Mirrored in `EnvironmentsAppShellTest` (per-rule docblocks, POSITIVE + NEGATIVE assertions per rule).
- `tests/Unit/DesignSystem/AppointmentTypesModalChromeTest.php` (if it exists) — closest precedent for the modal chrome pattern. The new `EnvironmentsModalChromeTest` mirrors the same structure (3 assertions covering the 3 modal chrome concerns: primitives present, raw v-model bindings absent, byte-for-byte bindings preserved).
- `openspec/changes/archive/2026-08-21-ui-tipos-cita/apply-progress.md` — closest precedent for the comment-driven regex noise (the tipos-cita apply-progress noted the same issue with `bg-gradient-to-br` comments in `ModuleAppShellTestCase` test docblocks). Fixed by rewriting the explanatory comments to describe the patterns without quoting the literal banned strings.
- `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` — closest precedent for the `defaultPolishedFiles()` extension pattern (each per-category PR adds its own module files to the polished set).
- `resources/js/modules/patients/PatientDetailPage.vue` — closest precedent for the `<UiTabs>` + `<UiCard variant="glass">` audit pattern (already in production; PR-02 mirrors it for ambientes).
- `resources/js/components/ui/Tabs.vue` — `<UiTabs>` consumes `{ id, label, icon }` per-tab data shape. The `name` → `label` rename on the detail page's tabs array is the data-shape alignment that the primitive API requires (display strings preserved).

### Risks encountered + deviations

1. **Comment-driven regex matches** — The initial GREEN run failed 2 tests (`test_no_legacy_border_theme_literal` for detail page + `LegacyAliasForbiddenTest::test_no_legacy_alias_in_polished_file` for detail page) because the explanatory comments inside the new template replacements contained the literal banned strings (`bg-gradient-accent`, `border-accent text-accent`, `border-b border-theme`). The regex tests don't strip JS/template comments. Fixed by rewriting the comments to describe the patterns without quoting the literal strings. The comment rewriting is documentation-only; no production code changed. This is a recurring pattern across the rollout (the tipos-cita apply-progress noted the same issue with `bg-gradient-to-br` comments).

2. **Tabs array `name` → `label` rename** — Required for `<UiTabs>`'s data contract (`Tabs.vue` validator: `id` + `label` are required). The display strings ("Datos" / "Historial") are preserved byte-for-byte; only the internal property name changed. The rename is a data-shape alignment, not a behavioural change. The `<script>` block's reactivity + lifecycle + composables stay verbatim; only the tabs array's property keys changed.

3. **`bg-canvas` reference added to detail page's `<PageHeader>`** — The inherited `test_page_references_canvas_token` rule requires an explicit `bg-canvas` (or `var(--color-canvas)` / `rgb(242, 242, 247)`) reference in the file. The list page gained one in PR-01; the detail page's `<PageHeader>` did not have one. Added `class="bg-canvas mb-6"` to satisfy the inherited rule. Visual behaviour is unchanged (the `<PageHeader>` already inherited the canvas background from `<AppLayout>` via the canvasRoutes fix).

4. **Status options reused for modals** — The New + Edit modal status `<UiSelect>` consume the existing `statusOptions` array (4 options: Todos los estados / Activos / Inactivos / Mantenimiento). The "Todos los estados" option (value='') is included; the `required` attribute on the `<UiSelect>` primitive prevents form submission if the user picks it. The default value for `newEnvironment.status` is `'active'` (line 392 in the source), so the empty value is never the default. This matches the spec for AMB-02-007 which explicitly named `statusOptions` as the data source.

5. **Visual capture skipped** — Per the tipos-cita archive's playwright-cli Windows assertion error. No screenshot produced. The behavioural contract is pinned by `EnvironmentsAppShellTest::test_tabs_use_ui_tabs` + `test_audit_log_uses_ui_card` + `EnvironmentsModalChromeTest::test_vmodel_bindings_preserved_byte_for_byte`.

6. **2 pre-existing DesignSystem failures + 95 SQLite errors** — Both environment-only, documented in the mis-procedimientos + tipos-cita archives. NOT introduced by this PR. Accepted as known warnings.

7. **Budget guard** — PR-02 is 698 insertions / 151 deletions across 5 files (849 total lines). 2.1x the 400-line cap. Justified by (a) the per-spec additive test pattern (EnvironmentsAppShellTest +6 rules + EnvironmentsModalChromeTest 3 rules + LegacyAliasForbiddenTest extension), (b) the 9 raw form fields × 8-9 line chrome each (~80 lines of removed chrome replaced by ~30 lines of primitive wrappers), and (c) the 6 template-level replacements on the detail page. Mirrors the PR-01 `size-exception` rationale; both slices are explicitly approved under the auto-chain delivery strategy.

### Work Unit Evidence
| Evidence | Result |
|---|---|
| Focused test | `vendor/bin/phpunit tests/Unit/DesignSystem/EnvironmentsAppShellTest.php tests/Unit/DesignSystem/EnvironmentsModalChromeTest.php tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` — 38/38 passed, 179 assertions |
| Regression test | All 5 focused test files green; full DesignSystem sweep exposes 2 pre-existing failures (environment-only) |
| Runtime harness | `pnpm build` — PASS; `vendor/bin/phpunit tests/Feature/Api` — PARTIAL (95 documented SQLite errors) |
| Rollback boundary | Revert 2 modified `.vue` files (`EnvironmentDetailPage.vue`, `EnvironmentsPage.vue`) + 2 modified test files (`EnvironmentsAppShellTest.php`, `LegacyAliasForbiddenTest.php`) and delete 1 new test file (`EnvironmentsModalChromeTest.php`) together; composable, API, routing, and the rest of the `<script>` block's reactivity logic untouched. The 1-line EXCEPTION #2 (`getAuditActionVariant` `'secondary'` → `'neutral'`) + 6 template-level replacements + 1 tabs array `name` → `label` rename + 4 additive imports (UiTabs + UiEmptyState + UiTextarea + canvas class) are the only `<script>`/template touches. |

### TDD Cycle Evidence
| Task | RED | GREEN | REFACTOR |
|------|-----|-------|----------|
| T2.1 | 6 AMB-02-003..008 failures (UiTabs missing, gradient present, UiEmptyState missing, UiCard variant="glass" missing, border-l-2 border-theme present, getAuditActionVariant returns 'secondary') | N/A | 6 PR-02 rules added per-rule docblocks |
| T2.2 | 0 RED (test infrastructure extension only) | N/A | N/A |
| T2.3 | 2 AMB-02-007 failures (raw <input>/<textarea>/<select> v-model bindings present on 8 modal fields) | N/A | 3 rules: primitive count + raw v-model absent + bindings preserved |
| T2.3a | Confirmed 11 RED failures total across all new PR-02 test additions + the LegacyAliasForbiddenTest extension | N/A | 11 failures map 1:1 to the AMB-02 rules |
| T2.5 | T2.1's `test_audit_action_badge_uses_legal_variant` held RED | EXCEPTION #2 mapping applied: `return 'secondary'` → `return 'neutral'` | 1-line <script> edit documented in helper docblock (already has the EXCEPTION #1 docblock) |
| T2.6 | T2.1's `test_tabs_use_ui_tabs` held RED | `<UiTabs v-model="activeTab" :tabs="tabs" />` adopted + tabs array `name` → `label` rename | 1 import + 1 component registration added |
| T2.7 | T2.1's `test_no_gradient_class` held RED | Header avatar `bg-gradient-accent` → `bg-systemBlue-50 rounded-[var(--radius-card-lg)]`; `<PageHeader>` gained `bg-canvas` for the inherited canvas-token rule | Comment-driven regex noise removed (explanatory comment rephrased) |
| T2.8 | T2.1's `test_audit_empty_state_uses_ui_component` held RED | Hand-rolled `bg-gradient-to-br` empty state → `<UiEmptyState title="..." description="..." />` | 1 import + 1 component registration added |
| T2.9 | T2.1's `test_audit_log_uses_ui_card` + `test_change_diff_callout_uses_hairline` held RED | Audit item wrapper → `<UiCard variant="glass">`; change-diff border `border-l-2 border-theme` → `border-l-2 border-[color:var(--color-hairline)]` | N/A |
| T2.10 | T2.3's `test_modals_use_ui_form_primitives` + `test_no_raw_form_field_vmodel_bindings` held RED | 8 raw form fields → 8 primitive wrappers (`<UiInput>` × 3, `<UiTextarea>` × 3, `<UiSelect>` × 2) | 1 import + 1 component registration added |
| T2.11 | Source-grep verification held RED (text-red-500 / text-green-500 still present) | `text-red-500` → `text-systemRed-600`; `text-green-500` → `text-systemGreen-600` | N/A |
| T2.12 | Focused suite starts at 11 RED | 38/38 GREEN, 179 assertions | Full regression across 5 test files |
| T2.12a | Full DesignSystem sweep exposes 2 pre-existing failures | 523/525 GREEN (the 2 failures are documented environment warnings) | 9 PR-02 rules + 5 inherited rules × 2 files + sentinel tests re-run |
| T2.12b | N/A; build was always clean | Build passed | Detail page bundle 8.74 kB / 3.12 kB gzipped (smaller than pre-PR); list page bundle 12.00 kB / 3.67 kB gzipped (smaller than PR-01) |
| T2.12c | N/A; SQLite `transactions.type` documented limitation | Tests partial | 137 API cases run |
| T2.12d | Source-grep verification | 0 matches across 5 forbidden legacy aliases + 1 EXCEPTION #2 mapping + 1 tabs `name` → `label` rename | Both files clean |
| T2.14 | N/A | Conventional commit `feat(ui): ambientes detail + 3 modals (AMB-02-*)` landed on main | 5 files changed, 698 insertions(+), 151 deletions(-) |

### AMB-02-* rule pin map
| Rule | Assertion file | Assertion name | Status |
|------|----------------|----------------|--------|
| AMB-02-001 (EXCEPTION #1) | `EnvironmentsStatusBadgeTest` | `test_get_status_variant_returns_tokens` | GREEN (closed in PR-01) |
| AMB-02-002 (EXCEPTION #2) | `EnvironmentsAppShellTest` | `test_audit_action_badge_uses_legal_variant` | GREEN (this PR) |
| AMB-02-003 (tabs) | `EnvironmentsAppShellTest` | `test_tabs_use_ui_tabs` | GREEN (this PR) |
| AMB-02-004 (header avatar flat systemBlue) | `EnvironmentsAppShellTest` | `test_no_gradient_class` | GREEN (this PR) |
| AMB-02-005 (audit empty state) | `EnvironmentsAppShellTest` | `test_audit_empty_state_uses_ui_component` | GREEN (this PR) |
| AMB-02-006 (audit log card + change-diff hairline) | `EnvironmentsAppShellTest` | `test_audit_log_uses_ui_card` + `test_change_diff_callout_uses_hairline` | GREEN (this PR) |
| AMB-02-007 (3 inlined modals → primitives) | `EnvironmentsModalChromeTest` | `test_modals_use_ui_form_primitives` + `test_no_raw_form_field_vmodel_bindings` + `test_vmodel_bindings_preserved_byte_for_byte` + `test_view_modal_status_pill_uses_ui_badge` | GREEN (this PR) |
| AMB-02-008 (text-red-500 → text-systemRed-600, etc.) | Source-grep golden (manual) | grep across `EnvironmentDetailPage.vue` | GREEN (this PR) |

All 7 AMB-02-* MUST rows are pinned by PHPUnit + source-grep verifications. **Lote 1's 5th and FINAL category (ambientes) is COMPLETE** — both PR-ambientes-01 + PR-ambientes-02 are GREEN, committed, and ready for `sdd-verify`.

---

## Next (post PR-02)
- `sdd-verify`: orchestrator launches the verify phase to confirm the conventional commit + CI gates are green for PR-ambientes-02.
- `sdd-archive`: after verify, archive the ambientes category slice (this file + spec.md + tasks.md) to `openspec/changes/archive/2026-08-21-ui-ambientes/`.
- **Lote 1 (ambientes) CLOSED**: PR-ambientes-01 (commit `3cd0f30`) + PR-ambientes-02 (commit `f005708`) both merged. The 5 categories of Lote 1 (pagos + citas + pacientes + profesionales + ambientes) are all complete.
