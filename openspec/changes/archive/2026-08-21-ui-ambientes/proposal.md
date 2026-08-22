# Proposal: ambientes (`ui-rollout-all-modules-2026-08`)

> SDD phase: `sdd-propose` — category slice. Tier-1 admin CRUD, 2 pages + 3 inlined modals + audit log tab.

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | AMBIENTES (admin CRUD: list, detail, New/Edit/View modals, audit tab) |
| Date | 2026-08-21 |
| Phase | propose (2 of 6) — category slice |
| Author | `sdd-propose` sub-agent (AMBIENTES) |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/ambientes/proposal`) |
| Parent artifacts | `proposal.md` (596 lines), `explore.md` (496 lines), `categories/ambientes/explore.md` (256 lines) |
| Global PR mapping | AMBIENTES = part of global PR4 (`pr4-admin-crud-triplet`) per global proposal §7.6; AMBIENTES sub-PRs `pr-ambientes-01..02` split PR4's admin-CRLD scope into chained work units |
| Delivery strategy | Inherits `auto-chain` from the global proposal; AMBIENTES sub-PRs `pr-ambientes-01..02` stack inside PR4 |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` — forward to apply/verify |
| Vertical slice baseline | `ui-premium-microdetail-2026-08` — closed 2026-08-11; tokens, primitives, easing, focus-ring, canvas/surface separation, `tabular-nums` all inherited as-is |
| CRITICAL | **OQ-ambientes-01 [BLOCKING]** — `/environments/:id` is NOT in `AppLayout.canvasRoutes` (line 537 lists only `/environments`). Detail page renders on `bg-systemBackground` chrome. PR-ambientes-01 includes the global fix. |

### Preflight snapshot (verbatim from session preflight)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain      # chained PRs auto-activate; do NOT re-ask
review_budget_lines: 400
chain_strategy: not_cached         # recommend stacked-to-main at sdd-tasks time
strict_tdd: true
```

### Two soft conflicts with the global `<script>`-never-touched rule

Both resolved in §6 as documented exceptions. Each is a single-line, mechanical change with zero risk and no behavioural drift.

- **`getStatusColor` → `getStatusVariant` rename** — the function name is wrong after tokenisation (it now returns a variant token, not a colour). 1-line additive change in PR-ambientes-01.
- **`getAuditActionVariant` `'secondary'` → `'neutral'` mapping** — `'secondary'` is not a valid `<UiBadge>` variant. 1-line `<script>` edit in PR-ambientes-02.

---

## 1. Intent

AMBIENTES is the rooms-and-chairs admin surface: the clinic manager (rol `administrador`) lands on `/environments` to see all dental chairs with their status (active / inactive / maintenance), searches by name or description, opens the New / Edit / View modals for CRUD, and clicks into `/environments/:id` to review the chair's 2-tab drawer (Datos / Historial de auditoría) with the audit log filtered by chair. The proven Apple language landed on Dashboard, Login, and 404; AMBIENTES still reads as legacy on its two smallest admin surfaces: `EnvironmentsPage.vue` (571 lines, 19.7 KB) and `EnvironmentDetailPage.vue` (383 lines, 13.6 KB).

The defects are visual and concentrated: 17 legacy class strings on the list page + 9 on the detail page (per `categories/ambientes/explore.md` §1), `border-theme` table dividers, `bg-primary-100 text-accent` row avatars (legacy alias ramps), `text-accent hover:text-accent-hover` link buttons, `text-red-600 hover:text-red-900` raw Tailwind on delete, `bg-gradient-accent` header avatar (forbidden gradient per global §11), `focus:ring-primary-500 focus:border-accent` on 9 raw form fields in 3 inlined modals, `border-accent text-accent` raw tab strip, `bg-gradient-to-br` audit empty state, and `text-red-500 / text-green-500` raw Tailwind on the audit diff. **Critical: `/environments/:id` is not in `AppLayout.canvasRoutes` so the detail page renders on `bg-systemBackground` chrome, not the canvas — visually inconsistent with the list page even after tokenisation.**

This proposal scopes the rollout to **only** the ambientes interfaces inventoried in `categories/ambientes/explore.md`. It inherits every rule from the global proposal (token discipline, primitive contract, focus-ring composition, `tabular-nums`, canvas/surface separation, no `<style scoped>` grandfather clause) and applies them mechanically. The result: an admin landing on `/environments` reads the same product as a clinician landing on `/dashboard`. The `DentalChairController` API envelope (`{ id, name, code, description, equipment, status, is_active, created_at, updated_at, audit_logs }`), the `DentalChair` model (status enum + soft-deletes), the `getDentalChairAuditLogs(chairId)` composable, and the `requireAuth` middleware all stay byte-for-byte untouched — UI changes are template-level class-string replacement only.

**Why now:** the foundation tokens are settled, the PHPUnit invariants are wired, and the global proposal's chain has Ambientes isolated as part of PR4 (admin CRUD triplet, per global §7.6). The AMBIENTES work splits cleanly into 2 sub-PRs that stay inside the 400-line review budget and don't disturb the chain order. The user's stated intent — extend the proven language to every module — applies with extra weight to AMBIENTES because it carries the global `canvasRoutes` detail-route fix (a 6-route fix that benefits every future detail-page PR in the rollout: `/patients/:id`, `/professionals/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary).

---

## 2. In-Scope

### 2.1 Pages / routes (2)

1. `/environments` — `resources/js/modules/environments/EnvironmentsPage.vue` (571 lines, 19.7 KB). List view: search bar (name / description), status filter (all / active / inactive / maintenance), table with row avatar + ID + status pill + 3 action links (Ver Detalle / Editar / Eliminar). Already receives canvas via global PR0.
2. `/environments/:id` — `resources/js/modules/environments/EnvironmentDetailPage.vue` (383 lines, 13.6 KB). Detail view: header card (name + code + status + is_active badge), 2-tab drawer (Datos / Historial de auditoría), per-tab data loaders. **Currently NOT on canvas surface — gap fixed in PR-ambientes-01.**

### 2.2 Inlined modals (3)

All 3 live in `EnvironmentsPage.vue` (the detail page has no inlined modal — edit happens on the list page).

| # | Modal | Lines | Fields migrated |
|---|---|---|---|
| 1 | New Environment (lines 213–259) | 47 | name (raw input) → `<UiInput>`; description (raw textarea) → `<UiTextarea>`; equipment (raw textarea) → `<UiTextarea>`; status (raw select) → `<UiSelect>`. 4 raw form fields → 4 primitives. |
| 2 | Edit Environment (lines 262–314) | 53 | name (raw input) → `<UiInput>`; code (raw input) → `<UiInput>`; description (raw textarea) → `<UiTextarea>`; status (raw select) → `<UiSelect>`. 4 raw form fields → 4 primitives. |
| 3 | View Environment (lines 317–352) | 36 | No raw form fields — only `<label>` + `<p>` rendering. Status pill (line 340–346) → `<UiBadge>` variant token. |

Total: 9 raw form fields → 9 primitive migrations (UiInput × 3, UiTextarea × 4, UiSelect × 2).

### 2.3 Cross-cutting primitives consumed (NOT tokenised here; PR0 owns them; AMBIENTES consumes as-is)

| Primitive | Use |
|---|---|
| `resources/js/components/layout/AppLayout.vue` (`<AppLayout>`) | Page shell; canvas surface wired via `canvasRoutes` (PR-ambientes-01 fix lands here) |
| `resources/js/components/layout/PageHeader.vue` (`<PageHeader>`) | Title + breadcrumbs + Volver / Nuevo Ambiente actions |
| `resources/js/components/ui/Card.vue` (`<UiCard variant="glass">`) | Filters card, table card, info card, audit log items (replacing `border border-theme rounded-lg p-4`) |
| `resources/js/components/ui/Button.vue` (`<UiButton>`) | Volver / Nuevo / Cancelar / Crear / Actualizar / Cerrar / Ver Detalle / Editar / Eliminar actions |
| `resources/js/components/ui/Input.vue` (`<UiInput>`) | Search bar; name + code fields in New + Edit modals (3 fields) |
| `resources/js/components/ui/Select.vue` (`<UiSelect>`) | Status filter on list; status selector in New + Edit modals (3 selects) |
| `resources/js/components/ui/Textarea.vue` (`<UiTextarea>`) | description + equipment in New; description in Edit (4 textareas) |
| `resources/js/components/ui/Modal.vue` (`<UiModal>`) | All 3 inlined modals (already used; only the inner raw form fields migrate) |
| `resources/js/components/ui/Badge.vue` (`<UiBadge>`) | Status pill on detail header (already used); audit action badges (after `secondary`→`neutral` mapping) |
| `resources/js/components/ui/Tabs.vue` (`<UiTabs>`) | 2-tab drawer on detail page (replacing raw `<button class="border-accent text-accent">` tab strip) |
| `resources/js/components/ui/EmptyState.vue` (`<UiEmptyState>`) | "No se encontraron ambientes" empty state on list; "No hay historial de auditoría" on detail (already imported, currently unused) |
| `resources/js/components/ui/LoadingSpinner.vue` (`<UiLoadingSpinner>`) | Initial load on list; audit log loading on detail (NOT YET imported) |

### 2.4 Cross-cutting composables (touch points only — do NOT fork)

| Composable | Touch |
|---|---|
| `resources/js/composables/useApi.js` | **Unchanged.** Used for `get / post / put / delete` on `/api/dental-chairs` + `/api/dental-chairs/search`. UI changes do NOT touch the `<script>` block except for the 2 documented exceptions (see §6). |
| `resources/js/composables/useToast.js` | **Unchanged.** Success / error toasts on create / update / delete. |
| `resources/js/composables/useConfirm.js` | **Unchanged.** Delete confirmation flow stays verbatim. |
| `resources/js/composables/useErrorHandler.js` | **Unchanged.** Error envelope rendering on the catch block. |
| `resources/js/composables/useAuditLogs.js:82` | **Unchanged.** `getDentalChairAuditLogs(chairId)` consumed verbatim. |

### 2.5 Tests

| Test file | Action |
|---|---|
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | **MODIFIED** — extend the `EXPECTED_ROUTES` list to include the 6 detail routes OR replace the `test_each_expected_route_is_in_canvas_routes` test to accept prefix matching (per OQ-ambientes-01 in §6). Recommendation: replace with prefix-matching contract; see §3.1 below. |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | **MODIFIED** — extend `defaultPolishedFiles()` to include `EnvironmentsPage.vue` + `EnvironmentDetailPage.vue`. |
| `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` | NEW. Extends `ModuleAppShellTestCase`; asserts both files reference the proven tokens, contain no legacy aliases, have zero `<style scoped>` blocks (already green by default), and the `getStatusColor` → `getStatusVariant` rename is in place. |
| `tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php` | NEW. Asserts the status pill on the list rows + View modal uses `<UiBadge>` (NOT `bg-success-100 text-success-700` etc.). Asserts the rule (variant token present, alias absent), not the literal string. |
| `tests/Unit/DesignSystem/EnvironmentsModalChromeTest.php` | NEW. Asserts the 3 inlined modals use `<UiInput>` / `<UiTextarea>` / `<UiSelect>` (NOT raw `<input>` / `<textarea>` / `<select>` with legacy focus chrome). Asserts the rule (primitive wrapper present, raw form absent, alias absent), not the literal string. |
| `tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php` | NEW. Asserts the `matchesCanvasRoute(path)` helper returns `true` for `/environments`, `/environments/123`, `/environments/abc-def`. Asserts the rule (helper exists + matches by prefix), not the literal array. |

---

## 3. Cross-cutting: `canvasRoutes` detail-route fix (OQ-ambientes-01 [BLOCKING])

### 3.1 Problem

`AppLayout.vue:537` lists `/environments` but NOT `/environments/:id`. Line 557's `isCanvasRoute = computed(() => canvasRoutes.includes(route.path))` is **exact-match**, so `/environments/123` returns `false` and the detail page renders on `bg-systemBackground` chrome instead of the canvas. The same gap exists for 5 other detail routes: `/patients/:id`, `/professionals/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary. Every detail-page PR in the rollout would have to re-discover this.

### 3.2 Resolution — prefix-matching helper

Add a `matchesCanvasRoute(path)` helper to `AppLayout.vue` and replace `isCanvasRoute`'s computed body. The helper uses `startsWith` so any path that starts with a canvas route string + `/` (or equals the canvas route exactly) returns `true`.

```js
// AppLayout.vue (insert near canvasRoutes literal, ~line 556)
function matchesCanvasRoute(path) {
  return canvasRoutes.some(
    route => path === route || path.startsWith(route + '/')
  )
}

// Replace line 557:
const isCanvasRoute = computed(() => matchesCanvasRoute(route.path))
```

This single helper covers all 6 detail routes globally — no per-route entries needed.

### 3.3 Test contract

**Replace** `AppLayoutCanvasRoutesTest::test_each_expected_route_is_in_canvas_routes` with **prefix-aware** assertions. Two options:

| Option | Pro | Con |
|---|---|---|
| **A. Prefix-matching test** (recommended) | Single test asserts `matchesCanvasRoute('/environments')` + `matchesCanvasRoute('/environments/123')` both return `true`. Honest: tests the rule. | Requires the helper to exist in AppLayout.vue. |
| **B. Add 6 explicit entries** | Backwards-compatible with the literal-array test. | 6 new array entries; future detail-page routes need new entries. |

**Adopt Option A.** Rationale: 1 helper vs 6 entries, future-proof, matches the vertical-slice archive-report's "test pins rule, not literal" lesson. `EnvironmentsCanvasRoutesPrefixTest` (NEW) pins the rule.

### 3.4 Where it lives

- **Source edit:** `resources/js/components/layout/AppLayout.vue` — add helper, update `isCanvasRoute` computed (2-line additive change).
- **Test edit:** `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` — keep the array-presence tests (`test_canvas_routes_file_exists`, `test_canvas_routes_array_present`, `test_no_legacy_narrowing_to_vertical_slice_routes`); REPLACE `test_each_expected_route_is_in_canvas_routes` with a prefix-matching helper test.
- **PR-ambientes-01 carries the fix.** The other 5 detail routes get the fix for free. Subsequent category PRs (pacientes, profesionales, appointment-types, procedure-catalog) do NOT touch `canvasRoutes` again.

### 3.5 Migration risk

The change is additive and rule-based. If a route that was previously NOT canvas accidentally becomes canvas (e.g., `/environments-archive` matching `/environments`), the helper would over-match. Mitigation: the canvas routes end in either a top-level path (`/environments`) or a known sub-path (`/cash-register/ready-to-bill`); a near-match like `/environments-archive` would not match either `/environments` or any other route. **Edge case handled by the `/` separator in `startsWith(route + '/')`.**

---

## 4. Out-of-Scope

1. **Cross-module `useEnvironmentStatuses` composable.** `getStatusColor` / `getStatusText` / `getStatusVariant` are duplicated across list + detail pages. Global §11 forbids new composables in the rollout. Defer until the global composable-extraction slice (post-rollout).
2. **Admin-only role banner.** No `<RoleBanner>` primitive exists. `requireAuth` is the only middleware; role gating is server-side via `DentalChairPolicy`. Adding a banner is a UX decision, not a visual token decision. Defer.
3. **Dark mode.** Light-only by design.
4. **Audit log pagination.** Currently no pagination on the audit log. Not in scope for visual polish.
5. **`DentalChair::code` uniqueness validation.** Currently unique by DB constraint; surfaced as 422 server error. UI shows the error via `useToast`. No new client-side validation.
6. **Settings/branches + Settings/payment-methods.** Per global OQ#3 — OUT of scope.
7. **Two-tone numerals (D12 REVERSIBLE).** Stays rejected.

---

## 5. Approach

Reuse the proven language as-is; no new tokens, no new primitives. Replace legacy alias classes one-by-one inside each `.vue` file using the global proposal §4.1 mapping table verbatim. Touch scope ordering: **list page first** (establishes canvas surface + status pill pattern), then **canvasRoutes detail-route fix** (unblocks the detail page), then **detail page** (header + tabs + audit log), then **3 inlined modals** (9 raw form fields → 9 primitives). The `useApi` call signatures, `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` reactivity, `DentalChairController` API envelope, and `requireAuth` middleware are preserved verbatim.

The AMBIENTES rollout touches 2 pages + 3 inlined modals. Both files carry zero `<style scoped>` blocks (per explore.md §1) — global OQ#9 does NOT apply; the cleanest starting point in the rollout. The status pill on the list rows + View modal use the legacy `getStatusColor` helper that returns `bg-success-100 text-success-700` etc. — these MUST be replaced by `<UiBadge :variant="getStatusVariant(...)" :label="getStatusText(...)" />` (1-line `<script>` edit to rename `getStatusColor` → `getStatusVariant` and return variant tokens). The audit action badges on the detail page use `<UiBadge :variant="getAuditActionVariant(log.action)">` where `'secondary'` is not a valid variant — this MUST be mapped to `'neutral'` (1-line `<script>` edit). Both `<script>` edits are documented exceptions to the global `<script>`-never-touched rule (see §6). The 2 routes already receive / will receive the canvas surface via the `matchesCanvasRoute` helper (PR-ambientes-01, this proposal). `LegacyAliasForbiddenTest` pins the alias list; `EnvironmentsAppShellTest` + `EnvironmentsStatusBadgeTest` + `EnvironmentsModalChromeTest` extend `ModuleAppShellTestCase` and assert the rule (token reference exists, alias absent), not a literal string. Visual verification per module: playwright-cli snapshot at 1440x900; mobile optional (no documented responsive behaviour on this admin surface). Credentials: `admin@test.com` (the canonical admin role per `CREDENTIALS.md`).

Strict TDD discipline: every UI replacement MUST come with a test that proves the new behaviour (RED-GREEN per project policy). The visual sweep is documented verification, not a CI gate.

---

## 6. Capabilities (contract with sdd-spec)

The sdd-spec phase reads this section to know exactly which spec files to create or update. Research `openspec/specs/` first to use the existing capability names.

### New Capabilities (none)

The AMBIENTES rollout does NOT introduce new capability specs. It exercises the global capability `premium-design-foundation` (persisted at `openspec/specs/premium-design-foundation/spec.md`) and the global delta spec `design-language-rollout` (at `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md`). The AMBIENTES requirements live as additional rows in the global spec's module table.

### Modified Capabilities (delta rows added to existing global delta spec)

For the `design-language-rollout` delta spec, add AMBIENTES-specific rows to the Module scenarios table:

- `DLR-MOD-005` — Ambientes (existing): inherited as-is from the global spec; AMBIENTES clarifies that the 2 pages + 3 inlined modals are tokenised as one cluster (2 PRs), `DentalChairController` API envelope stays verbatim, `useAuditLogs.getDentalChairAuditLogs(chairId)` stays verbatim, `requireAuth` middleware stays verbatim, and zero `<style scoped>` blocks exist (DLR-R-021 green by default). (NEW row added by AMBIENTES.)
- `DLR-AMB-001` — `EnvironmentsPage` list polish: `border-theme` table dividers → hairline + `divide-[color:var(--color-hairline)]`; `bg-primary-100 text-accent` row avatar → `bg-systemBlue-50 text-systemBlue-700`; `text-accent hover:text-accent-hover` link buttons → `<UiButton variant="link">`; `text-red-600 hover:text-red-900` raw Tailwind → `<UiButton variant="ghost">` with `text-systemRed-700`; `bg-success-100 text-success-700` status pill → `<UiBadge :variant="getStatusVariant(...)" :label="getStatusText(...)" />`; raw `<input>` / `<textarea>` / `<select>` status filter → `<UiSelect>`; `inline-block animate-spin` → `<UiLoadingSpinner>`; hand-rolled empty state SVG → `<UiEmptyState>`; ID cell → `font-feature-settings: var(--font-features-tabular-nums)`. (NEW row added by AMBIENTES.)
- `DLR-AMB-002` — `EnvironmentDetailPage` detail polish: header avatar `bg-gradient-accent` (forbidden) → flat `bg-systemBlue-50 rounded-[var(--radius-card-lg)]`; raw `border-accent text-accent` tab strip → `<UiTabs>` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions; audit log `border border-theme rounded-lg p-4` list items → `<UiCard>` wrappers; `border-l-2 border-theme` change-diff callout → hairline; `text-red-500 / text-green-500` raw Tailwind on diff → `text-systemRed-600 / text-systemGreen-600`; `bg-gradient-to-br` audit empty state → `<UiEmptyState>`; audit log spinner → `<UiLoadingSpinner>`. (NEW row added by AMBIENTES.)
- `DLR-AMB-003` — Modal chrome for 3 inlined modals (New + Edit + View on `EnvironmentsPage.vue`): 9 raw form fields → `<UiInput>` (3) / `<UiTextarea>` (4) / `<UiSelect>` (2). Raw focus chrome `focus:outline-none focus:ring-primary-500 focus:border-accent` → `<UiInput>` / `<UiSelect>` / `<UiTextarea>` built-in `var(--focus-ring-default)`. View modal status pill → `<UiBadge>`. (NEW row added by AMBIENTES.)
- `DLR-AMB-004` — `canvasRoutes` detail-route pattern (global fix): `AppLayout.vue` MUST introduce a `matchesCanvasRoute(path)` helper using `startsWith` matching (`path === route || path.startsWith(route + '/')`); `isCanvasRoute` MUST delegate to the helper. The fix covers 6 detail routes globally: `/environments/:id`, `/patients/:id`, `/professionals/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary. Test contract: `AppLayoutCanvasRoutesTest::test_each_expected_route_is_in_canvas_routes` REPLACED with prefix-matching assertion (asserts helper exists + `matchesCanvasRoute('/environments/123')` returns `true`); new `EnvironmentsCanvasRoutesPrefixTest` pins the rule for the ambientes-specific paths. (NEW row added by AMBIENTES — load-bearing for the entire rollout.)
- `DLR-AMB-005` — `<script>` edit exceptions (documented): `getStatusColor` (line 516) renamed to `getStatusVariant` (1-line additive change) returning variant tokens `success | neutral | warning` instead of legacy colour class strings; `getAuditActionVariant` (line 339) mapping updated from `return 'secondary'` to `return 'neutral'` (1-line additive change, `'secondary'` is not a valid `<UiBadge>` variant). Both edits are mechanical 1-line renames with zero behavioural drift on the data flow. The remaining `<script>` blocks of both pages are byte-for-byte preserved (all `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` calls + the 401 redirect + the `loadEnvironments` / `searchEnvironments` / `createEnvironment` / `updateEnvironment` / `deleteEnvironment` flows stay verbatim). (NEW row added by AMBIENTES.)
- `DLR-AMB-006` — `useAuditLogs.getDentalChairAuditLogs` composable isolation: `getDentalChairAuditLogs(chairId)` consumed verbatim on the detail page's audit tab. UI changes MUST NOT touch the composable. (NEW row added by AMBIENTES.)
- `DLR-AMB-007` — `DentalChairController` API envelope preservation: API envelope `{ id, name, code, description, equipment, status, is_active, created_at, updated_at, audit_logs }` MUST NOT be widened or narrowed. Status enum `active | inactive | maintenance` continues to be exposed. (NEW row added by AMBIENTES.)
- `DLR-AMB-008` — Rule-asserting tests (extend `ModuleAppShellTestCase`): `EnvironmentsAppShellTest` asserts the canvas / hairline / focus-ring / no-`<style scoped>` rules per file; `EnvironmentsStatusBadgeTest` asserts the status pill uses `<UiBadge>` (not legacy alias); `EnvironmentsModalChromeTest` asserts the 3 inlined modals use `<UiInput>` / `<UiTextarea>` / `<UiSelect>` (not raw `<input>` / `<textarea>` / `<select>`); `EnvironmentsCanvasRoutesPrefixTest` asserts the `matchesCanvasRoute` helper covers detail routes. All assert the rule (token reference exists, alias absent, primitive wrapper present), not the literal string. (NEW row added by AMBIENTES.)

If sdd-spec chooses to extract AMBIENTES into a sibling delta spec (`specs/ambientes-rollout/spec.md`), that is allowed — the global proposal does not forbid per-category specs. Recommendation: extend the global spec to keep traceability simple. Discuss with the orchestrator at spec phase.

---

## 7. Deliverables

Two PRs. Each fits inside the 400-line budget. Each is independently buildable, testable, and revertible.

### PR-ambientes-01 — `EnvironmentsPage` list polish + `canvasRoutes` detail-route fix (global)

| Field | Value |
|---|---|
| Name | `pr-ambientes-01-list-page-and-canvas-routes-detail-fix` |
| Scope | (1) `AppLayout.vue` — add `matchesCanvasRoute(path)` helper using `startsWith` matching; update `isCanvasRoute` to delegate to the helper (2-line additive change). (2) `AppLayoutCanvasRoutesTest.php` — replace `test_each_expected_route_is_in_canvas_routes` with prefix-matching assertion (asserts helper exists + matches `/environments/123`); keep the other 4 sentinel tests. (3) `EnvironmentsPage.vue` (571 lines, 19.7 KB) — replace `border-theme` table dividers → hairline; `bg-primary-100 text-accent` row avatar → `bg-systemBlue-50 text-systemBlue-700`; `text-accent hover:text-accent-hover` link buttons → `<UiButton variant="link">`; `text-red-600 hover:text-red-900` raw Tailwind → `<UiButton variant="ghost">` with `text-systemRed-700`; `bg-success-100 text-success-700` status pill → `<UiBadge :variant="getStatusVariant(...)" :label="getStatusText(...)" />`; raw `<select>` status filter → `<UiSelect>`; `inline-block animate-spin` → `<UiLoadingSpinner>`; hand-rolled empty state SVG → `<UiEmptyState>`; ID cell → `font-feature-settings: var(--font-features-tabular-nums)`. (4) `getStatusColor` → `getStatusVariant` 1-line rename (DLR-AMB-005 exception). (5) Extend `LegacyAliasForbiddenTest::defaultPolishedFiles()` to include both files. (6) Create `EnvironmentsAppShellTest.php` extending `ModuleAppShellTestCase`. (7) Create `EnvironmentsStatusBadgeTest.php` asserting `<UiBadge>` adoption. (8) Create `EnvironmentsCanvasRoutesPrefixTest.php` asserting prefix matching. |
| Files | 2 source files (`AppLayout.vue`, `EnvironmentsPage.vue`), 1 test edit (`AppLayoutCanvasRoutesTest.php`), 3 new test files, 1 `LegacyAliasForbiddenTest` extension |
| Risk | Low (cleanest starting point in the rollout; zero `<style scoped>` blocks; no Reverb channel; the canvasRoutes fix is global additive) |
| Dependencies | Global PR0 (already landed: `canvasRoutes` array, `<UiBadge>`, `<UiLoadingSpinner>`, `<UiEmptyState>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`) |
| Line estimate | ~200 (right at the budget) |
| Reversibility | `git revert <merge-sha>`; `AppLayout.vue` reverts to exact-match `canvasRoutes.includes(route.path)` (original behaviour) + `EnvironmentsPage.vue` reverts to legacy class strings; `<script>` block's `getStatusVariant` rename reverts to `getStatusColor`; no data-flow change (call sites adapt) |

### PR-ambientes-02 — `EnvironmentDetailPage` detail polish + 3 inlined modals

| Field | Value |
|---|---|
| Name | `pr-ambientes-02-detail-page-and-modals` |
| Scope | (1) `EnvironmentDetailPage.vue` (383 lines, 13.6 KB) — header avatar `bg-gradient-accent` (forbidden) → flat `bg-systemBlue-50 rounded-[var(--radius-card-lg)]`; raw `border-accent text-accent` tab strip → `<UiTabs>` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions; audit log `border border-theme rounded-lg p-4` list items → `<UiCard>` wrappers; `border-l-2 border-theme` change-diff callout → hairline; `text-red-500 / text-green-500` raw Tailwind → `text-systemRed-600 / text-systemGreen-600`; `bg-gradient-to-br` audit empty state → `<UiEmptyState>`; audit log spinner → `<UiLoadingSpinner>`. (2) `getAuditActionVariant` `'secondary'` → `'neutral'` 1-line mapping (DLR-AMB-005 exception). (3) `EnvironmentsPage.vue` 3 inlined modals — 9 raw form fields → `<UiInput>` (3) / `<UiTextarea>` (4) / `<UiSelect>` (2); raw focus chrome → built-in `var(--focus-ring-default)`; View modal status pill → `<UiBadge>`. (4) Extend `EnvironmentsAppShellTest` to include `EnvironmentDetailPage.vue`. (5) Create `EnvironmentsModalChromeTest.php` asserting `<UiInput>` / `<UiTextarea>` / `<UiSelect>` adoption on the 3 inlined modals. |
| Files | 2 source files (`EnvironmentDetailPage.vue`, `EnvironmentsPage.vue` modal sections), 1 test extension, 1 new test file |
| Risk | Low (3 inlined modals are bounded; `<script>` edit is 1-line rename; `useAuditLogs.getDentalChairAuditLogs` stays verbatim) |
| Dependencies | PR-ambientes-01 (so the canvasRoutes fix is in place and the `<UiBadge>` pattern is established) |
| Line estimate | ~280 |
| Reversibility | `git revert <merge-sha>`; detail page reverts to legacy look + 3 inlined modals revert to raw form fields; `<script>` block's `getAuditActionVariant` mapping reverts to `'secondary'` (which would fail `<UiBadge>` variant validation, so revert requires a follow-up hotfix — accept this risk since it's a 1-line rename) |

### Deliverable-to-PR mapping (verifies the global chain)

| Global PR | AMBIENTES PRs that ride it |
|---|---|
| Global PR4 (`pr4-admin-crud-triplet`) | PR-ambientes-01 + 02 (both 2 tokenisation PRs) |

---

## 8. Affected Areas

| Area | Impact | Description |
|---|---|---|
| `resources/js/modules/environments/EnvironmentsPage.vue` | Modified | List polish (PR-ambientes-01) + 3 inlined modals (PR-ambientes-02) + 1-line `getStatusColor` rename (PR-ambientes-01) |
| `resources/js/modules/environments/EnvironmentDetailPage.vue` | Modified | Header + 2-tab drawer + audit log (PR-ambientes-02) + 1-line `getAuditActionVariant` mapping (PR-ambientes-02) |
| `resources/js/components/layout/AppLayout.vue` | Modified | `matchesCanvasRoute(path)` helper + `isCanvasRoute` delegate (PR-ambientes-01) — global fix |
| `resources/js/components/ui/Card.vue` | Unchanged | `<UiCard variant="glass">` consumed as-is |
| `resources/js/components/ui/{Button,Input,Select,Textarea,Modal,Badge,EmptyState,LoadingSpinner,Tabs}.vue` | Unchanged | 9 tokenised primitives consumed as-is |
| `resources/js/composables/{useApi,useToast,useConfirm,useErrorHandler,useAuditLogs}.js` | Unchanged | All composables preserved verbatim; `getDentalChairAuditLogs` at `useAuditLogs.js:82` stays byte-for-byte |
| `app/Http/Controllers/Api/DentalChairController.php` | Unchanged | Out of scope; CRUD + search + audit verbatim |
| `app/Models/DentalChair.php` | Unchanged | Status enum + soft-deletes + `$fillable` verbatim |
| `app/Policies/DentalChairPolicy.php` | Unchanged | Role gating preserved verbatim (admin-only) |
| `database/migrations/*.php` | Unchanged | Schema verbatim |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Modified | Replace literal-array test with prefix-matching helper test |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | Modified | Extend `defaultPolishedFiles()` to include both ambientes files |
| `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` | New | Extends `ModuleAppShellTestCase`; covers both files |
| `tests/Unit/DesignSystem/EnvironmentsStatusBadgeTest.php` | New | Asserts `<UiBadge>` adoption on list rows + View modal |
| `tests/Unit/DesignSystem/EnvironmentsModalChromeTest.php` | New | Asserts `<UiInput>` / `<UiTextarea>` / `<UiSelect>` adoption on 3 inlined modals |
| `tests/Unit/DesignSystem/EnvironmentsCanvasRoutesPrefixTest.php` | New | Asserts `matchesCanvasRoute` helper covers detail routes |

---

## 9. Risks

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | **`canvasRoutes` detail-route fix could over-match** (e.g., `/environments-archive` accidentally matching `/environments`). | Low | The helper uses `path === route || path.startsWith(route + '/')` — the trailing `/` separator excludes `/environments-archive`. No current `canvasRoutes` entries share a `/<word>` prefix with any other route. |
| 2 | **3 inlined modals' raw form fields are 9 total** — risk of dropping a `v-model` binding during the migration to `<UiInput>` / `<UiTextarea>` / `<UiSelect>`. | Low | Apply phase: keep all `v-model` bindings + `required` attributes byte-for-byte. The 4 New modal fields (`newEnvironment.name`, `.description`, `.equipment`, `.status`) and the 4 Edit modal fields (`editingEnvironment.name`, `.code`, `.description`, `.status`) preserve their reactivity. `EnvironmentsModalChromeTest` grep-verifies `v-model=` is still present on every migrated field. |
| 3 | **`getStatusColor` → `getStatusVariant` rename in `<script>`** violates the global `<script>`-never-touched rule. | Low | Documented as exception (DLR-AMB-005). The rename is mechanical (function name only; return value type changes from string-class to variant-token); call sites (`<UiBadge :variant="getStatusVariant(...)" :label="getStatusText(...)" />`) already expect a variant token. `EnvironmentsStatusBadgeTest` pins the variant. |
| 4 | **`getAuditActionVariant` `'secondary'` → `'neutral'` mapping in `<script>`** violates the global `<script>`-never-touched rule. | Low | Documented as exception (DLR-AMB-005). 1-line additive change; `<UiBadge :variant="...">` validates `'neutral'` as a legal variant; previous `'secondary'` would have silently rendered with no variant. Reverting this exception requires a follow-up hotfix (acceptable risk per §7 PR-ambientes-02 reversibility note). |
| 5 | **Header avatar gradient `bg-gradient-accent`** is a forbidden pattern (global §11 forbids gradients). | Low | Replace with flat `bg-systemBlue-50 rounded-[var(--radius-card-lg)]`. The detail-page precedent (`PatientDetailPage.vue`) already uses `bg-systemBlue-50` on its header avatar. `ModuleAppShellTestCase` does NOT pin gradient absence — `EnvironmentsAppShellTest` adds a `test_no_gradient_class` assertion for `bg-gradient-*` literal. |
| 6 | **Audit log rendering consumes `useAuditLogs.getDentalChairAuditLogs(chairId)`** which loads from `/api/dental-chairs/{id}/audit-logs`. Any UI change that accidentally removes a reactive watch silently breaks the audit tab. | Low | Apply phase scope rule: `<script>` block of `EnvironmentDetailPage.vue` is NOT touched except for the documented `getAuditActionVariant` mapping. `watch(activeTab, ...)` and `onMounted(...)` stay verbatim. Visual smoke test: open `/environments/:id` in two browser tabs, create an update event on tab A, verify tab B receives the audit log update within 1 second (no Reverb channel for dental-chairs; the test runs against a synchronous refetch). |

---

## 10. Rollback Plan

- **PR-ambientes-01 revert:** restores the exact-match `canvasRoutes.includes(route.path)` behaviour (the 6 detail routes fall back to non-canvas chrome; visually inconsistent with list pages until PR-ambientes-02 also reverts, but no data loss); restores legacy `border-theme` table dividers, `bg-primary-100 text-accent` row avatar, raw `<select>` status filter, `bg-success-100 text-success-700` status pill; restores `getStatusColor` (1-line revert). `<script>` block's `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` calls are preserved. The new tests `EnvironmentsAppShellTest` + `EnvironmentsStatusBadgeTest` + `EnvironmentsCanvasRoutesPrefixTest` are deleted. No data regression.
- **PR-ambientes-02 revert:** restores legacy `border-accent text-accent` raw tab strip, gradient header avatar, `bg-gradient-to-br` audit empty state, raw form fields in 3 inlined modals, `text-red-500 / text-green-500` raw Tailwind on diff. The `getAuditActionVariant` mapping revert (`'neutral'` → `'secondary'`) would break `<UiBadge>` variant validation; a follow-up hotfix PR is preferable to a hard revert (or revert the variant source token in PR-ambientes-02 along with this change). `<script>` block's `useAuditLogs.getDentalChairAuditLogs` stays verbatim. The new test `EnvironmentsModalChromeTest` is deleted. No data regression.
- **No destructive schema/data migrations.** All backend controllers / services / models / migrations are byte-for-byte unchanged. No destructive operation anywhere.

---

## 11. Success Criteria

The AMBIENTES rollout is considered complete when ALL of the following hold:

- [ ] **Both ambientes routes (`/environments`, `/environments/:id`) render on Apple canvas** (verified post-PR-ambientes-01). `AppLayoutCanvasRoutesTest` green (prefix-matching helper test); `EnvironmentsCanvasRoutesPrefixTest` green. Visual smoke test: `bg-canvas` on `/environments` and `/environments/123`.
- [ ] **Both files contain zero legacy alias classes** (`border-theme`, `bg-success-100 text-success-700`, `bg-primary-100 text-accent`, `text-accent hover:text-accent-hover`, `focus:ring-primary-500 focus:border-accent`, `bg-gradient-accent`, `bg-gradient-to-br`, `text-red-500`, `text-green-500`, etc.). `LegacyAliasForbiddenTest` green; `EnvironmentsAppShellTest` green.
- [ ] **Status pills on list rows + View modal use `<UiBadge>`** (NOT legacy alias). `EnvironmentsStatusBadgeTest` green; grep-verified: no `bg-success-100 / bg-warning-100 / bg-theme-surface text-theme-primary` strings.
- [ ] **3 inlined modals use `<UiInput>` / `<UiTextarea>` / `<UiSelect>`** (NOT raw `<input>` / `<textarea>` / `<select>`). `EnvironmentsModalChromeTest` green; grep-verified: zero `<input ... class="...">` and zero `<textarea ... class="...">` and zero `<select ... class="...">` in the modal sections (lines 213–352).
- [ ] **Detail page header avatar uses `bg-systemBlue-50`** (NOT `bg-gradient-accent`). `EnvironmentsAppShellTest::test_no_gradient_class` green.
- [ ] **2-tab drawer uses `<UiTabs>`** instead of raw `<button class="border-accent text-accent">` step strip. Grep-verified: no `border-accent text-accent` literal in `EnvironmentDetailPage.vue`.
- [ ] **Audit log spinner uses `<UiLoadingSpinner>`**; audit empty state uses `<UiEmptyState>`; audit list items use `<UiCard>` wrappers. `EnvironmentsAppShellTest` green.
- [ ] **ID cell on list page carries `font-feature-settings: var(--font-features-tabular-nums)`** (or the `tabular-nums` Tailwind utility class). Grep-verified.
- [ ] **Both files have zero `<style scoped>` blocks** (already green by default; `ModuleAppShellTestCase::test_no_style_scoped` pins the rule).
- [ ] **`useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` reactivity preserved.** Manual smoke test: open `/environments/:id`, switch to the audit tab, verify the audit log loads via `getDentalChairAuditLogs(chairId)` within 1 second; verify create / update / delete toasts still fire.
- [ ] **`DentalChairController` API envelope unchanged.** `get / post / put / delete` on `/api/dental-chairs` + `/api/dental-chairs/search` stay verbatim.
- [ ] **No new primitives introduced.** The full `<UiCard>` / `<UiButton>` / `<UiInput>` / `<UiSelect>` / `<UiTextarea>` / `<UiModal>` / `<UiBadge>` / `<UiLoadingSpinner>` / `<UiEmptyState>` / `<UiTabs>` set is inherited from PAGOS / CITAS / vertical slice. The ambientes PRs do NOT introduce any new primitive.
- [ ] **Playwright snapshot saved to `.playwright-cli/screenshots-rollout/environments-{list,detail}-1440x900.png`** (mobile optional — admin surface, no documented responsive behaviour).
- [ ] **All `tests/Unit/DesignSystem/*` PHPUnit invariants stay green** (`TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `UseSpringMathTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `ModuleAppShellTestCase`-derived tests).
- [ ] **CI green:** `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).
- [ ] **Test count delta ≥ +20** vs PR0 baseline. Budget: +20 from the 4 new test files (`EnvironmentsAppShellTest`, `EnvironmentsStatusBadgeTest`, `EnvironmentsModalChromeTest`, `EnvironmentsCanvasRoutesPrefixTest`) + `AppLayoutCanvasRoutesTest` + `LegacyAliasForbiddenTest` extensions.
- [ ] **Chain integrity:** every PR-ambientes-NN is independently buildable, testable, and revertible per `chained-pr` skill rules.

---

## 12. References

### 12.1 Source artifacts (read for this proposal)

| File | Why it matters |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (596 lines) | Global intent, scope, OQ resolutions, PR chain, success criteria |
| `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (496 lines) | Module inventory, per-module visual state, complexity tiers, PR chain ordering rationale |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/ambientes/explore.md` (256 lines) | **PRIMARY INPUT.** Ambientes inventory, controllers/models/composables inventory, test coverage surface, known gotchas, 8 OQs |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` | Global MUST/SHOULD language; AMBIENTES sub-PRs map onto global PR4 |
| `openspec/changes/archive/2026-08-12-ui-pacientes/proposal.md` (439 lines) | Sibling category proposal — AMBIENTES mirrors its structure, tone, length, deliverable granularity |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/{proposal.md,design.md,tasks.md,archive-report.md}` | Vertical slice baseline + process lessons |
| `openspec/specs/premium-design-foundation/spec.md` (404 lines) | The archived capability AMBIENTES inherits (tokens, primitives, easing) |
| `openspec/config.yaml` | Preflight cache + strict TDD + pnpm-only + 400-line budget + CI MySQL |
| `AGENTS.md` §2, §4, §5, §6, §7 | Project context, stack, 17-module inventory, conventions, troubleshooting |
| `resources/js/design-system/tokens.js` | The proven token source-of-truth |
| `resources/css/tokens.generated.css` | Generated CSS (369 lines) |
| `resources/js/components/layout/AppLayout.vue` lines 523–557 | `canvasRoutes` gate (PR0 + PR-ambientes-01 fix) |
| `resources/js/modules/environments/EnvironmentsPage.vue` (571 lines, 19.7 KB) | PR-ambientes-01 + 02 primary file (list + 3 inlined modals) |
| `resources/js/modules/environments/EnvironmentDetailPage.vue` (383 lines, 13.6 KB) | PR-ambientes-02 primary file (detail + audit log) |
| `resources/js/components/ui/{Card,Button,Input,Select,Textarea,Modal,Badge,EmptyState,LoadingSpinner,Tabs}.vue` | 10 tokenised primitives; inherited by AMBIENTES as-is |
| `resources/js/composables/{useApi,useToast,useConfirm,useErrorHandler,useAuditLogs}.js` | 5 composables; preserved verbatim |
| `app/Http/Controllers/Api/DentalChairController.php` | `index` / `store` / `show` / `update` / `destroy` / `search` / `audit` — out of scope |
| `app/Models/DentalChair.php` | Status enum + soft-deletes + `$fillable` — out of scope |
| `app/Policies/DentalChairPolicy.php` | Role gating (admin-only) — out of scope |
| `database/migrations/*dental_chairs*.php` | Schema — out of scope |
| `resources/js/app.js` lines 83–94 | Router definitions for `/environments` + `/environments/:id` |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Pins the `canvasRoutes` array literal — modified in PR-ambientes-01 |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Base class for `EnvironmentsAppShellTest` |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | Forbidden alias list — extended in PR-ambientes-01 |
| `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` (409 lines) | Sibling category test pattern — AMBIENTES mirrors its structure |
| `CREDENTIALS.md` | `admin@test.com` for the canonical admin role |

### 12.2 Standing guard rails (inherited from the global proposal)

This proposal does NOT relax any of:

1. `tokens.js` is the only source of truth for tokens.
2. `systemBackground` (`#ffffff`) is pinned; canvas = `#F2F2F7`.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings` value is `"tnum" 1, "lnum" 1`, NOT literal `tabular-nums` utility name.
7. **`<script>` blocks of `EnvironmentsPage.vue` + `EnvironmentDetailPage.vue` are NEVER edited in any PR EXCEPT for the 2 documented exceptions in DLR-AMB-005** (`getStatusColor` → `getStatusVariant` rename + `getAuditActionVariant` `'secondary'` → `'neutral'` mapping). Both are 1-line mechanical renames.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only; NEVER npm/yarn.
10. Code in English; conversation in Spanish (Peru).
11. No gradients anywhere (global §11); `bg-gradient-accent` + `bg-gradient-to-br` on the detail page are FORBIDDEN.
12. No `<style scoped>` blocks (global §11); both files have zero by default, and the rollout does NOT introduce any.

### 12.3 Process invariant (forwarded from the vertical-slice archive-report)

The archive-report at lines 47–57 names three defects that all shared one root cause: **a test that pins an example instead of the rule**. AMBIENTES's standing posture is to assert rules, not literals:

- `EnvironmentsAppShellTest` extends `ModuleAppShellTestCase` — it asserts the rule (`--color-canvas` reference exists, `border-theme` absent, `<style scoped>` absent), not a literal string.
- `EnvironmentsStatusBadgeTest` asserts the rule (`<UiBadge>` variant token present, legacy alias absent), not the literal output of one example.
- `EnvironmentsModalChromeTest` asserts the rule (`<UiInput>` / `<UiTextarea>` / `<UiSelect>` wrappers present, raw `<input>` / `<textarea>` / `<select>` absent, focus-ring alias absent), not the literal output of one example.
- `EnvironmentsCanvasRoutesPrefixTest` asserts the rule (`matchesCanvasRoute` helper exists, returns `true` for `/environments/123`, `false` for `/environments-archive`), not the literal array entry.

---

## 13. Open Questions Resolved

All 8 OQs from `categories/ambientes/explore.md` §6 are resolved here. Each gets a concrete decision; the user can flag during review of the spec. Apply phase does NOT silently resolve any of them.

### OQ-ambientes-01 [BLOCKING] — `canvasRoutes` detail-route pattern

**Decision: prefix matching via `matchesCanvasRoute(path)` helper** (see §3.2).

Rationale: 1 helper covers 6 detail routes globally (vs. 6 explicit array entries that would still need a helper for any future detail route). Matches the vertical-slice archive-report's "test pins rule, not literal" lesson. The `AppLayoutCanvasRoutesTest` test contract is REPLACED with a prefix-matching helper test that asserts `matchesCanvasRoute('/environments/123')` returns `true`. The 6 affected detail routes (`/environments/:id`, `/patients/:id`, `/professionals/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary) get the fix for free; subsequent category PRs do NOT touch `canvasRoutes` again. PR-ambientes-01 carries the fix.

### OQ-ambientes-02 [soft] — `getStatusColor` → `getStatusVariant` rename in `<script>`

**Decision: 1-line additive rename in `<script>`** (DLR-AMB-005 documented exception).

Rationale: the function name is wrong after tokenisation (it now returns a variant token, not a colour class). The rename is mechanical and zero-risk: the function body is updated in the same line to return variant tokens (`active: 'success'`, `inactive: 'neutral'`, `maintenance: 'warning'`); the call sites (`<UiBadge :variant="getStatusVariant(...)" :label="getStatusText(...)" />`) already expect a variant token. Without the rename, the function name lies to the reader (the legacy `getStatusColor` returning `bg-success-100` would mismatch the `<UiBadge>` variant contract). The global `<script>`-never-touched rule is preserved for the remaining `<script>` blocks (all `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` calls + the data-flow methods stay byte-for-byte). `EnvironmentsStatusBadgeTest` pins the variant. PR-ambientes-01 carries the rename.

### OQ-ambientes-03 [soft] — `getAuditActionVariant` `'secondary'` → `'neutral'` mapping

**Decision: 1-line additive mapping in `<script>`** (DLR-AMB-005 documented exception).

Rationale: `<UiBadge :variant="getAuditActionVariant(log.action)">` validates against a fixed enum; `'secondary'` is not a legal variant and would silently render with no colour ramp. The mapping `return 'secondary'` → `return 'neutral'` is mechanical, zero-risk, and load-bearing (without it the audit action badge renders blank). The same exception rationale as OQ-ambientes-02 applies: global `<script>`-never-touched rule is preserved for the rest of the `<script>` block. PR-ambientes-02 carries the mapping.

### OQ-ambientes-04 — Detail-page header avatar colour

**Decision: `bg-systemBlue-50` flat ramp** (DLR-AMB-002).

Rationale: matches the precedent (`PatientsPage` row avatars use the same `bg-systemBlue-50`). No gradient (global §11 forbids). `rounded-[var(--radius-card-lg)]` (16 px) per the proven card radius token. The legacy `bg-gradient-accent` is forbidden and would fail `EnvironmentsAppShellTest::test_no_gradient_class`.

### OQ-ambientes-05 — Old/new value colours in audit diff

**Decision: `text-systemRed-600` / `text-systemGreen-600`** (DLR-AMB-002).

Rationale: precedent wins — the proven language uses the `-600` shade for text on canvas (not `-700`, which is reserved for status-pill backgrounds per `QuotationStatusBadge` and the vertical slice's `text-success-700` → `text-systemGreen-700` mapping). Raw `text-red-500` / `text-green-500` are deprecated Tailwind palettes that don't tokenise cleanly.

### OQ-ambientes-06 — `useEnvironmentStatuses` shared composable

**Decision: defer — keep duplication** (per global §11).

Rationale: global §11 forbids new composables in the rollout. `getStatusColor` / `getStatusText` / `getStatusVariant` are duplicated across list + detail pages; the duplication is bounded (2 files × 3 helpers) and the composable would add cross-file coupling for no immediate gain. Defer until the global composable-extraction slice (post-rollout).

### OQ-ambientes-07 — Admin-only role banner

**Decision: defer — no primitive exists**.

Rationale: no `<RoleBanner>` primitive in `resources/js/components/ui/`. Building one would expand the rollout scope into a new primitive (forbidden by global §11 + the global proposal's `no new primitives except <UiStatusBadge>` rule). The role gating is server-side via `DentalChairPolicy::viewAny` returning `true` only for `administrador`. The detail page's header card already surfaces the role implicitly (the page is only accessible to admins). UX decision, not a visual token decision — defer.

### OQ-ambientes-08 — Visual verification scope

**Decision: 1440x900 desktop capture mandatory; 390x844 mobile capture optional**.

Rationale: per global OQ#8 resolution (desktop proves the rollout; mobile capture only when documented responsive behaviour exists). Ambientes is an admin surface used on desktops by clinic managers; no documented responsive behaviour in the existing `EnvironmentsPage.vue` / `EnvironmentDetailPage.vue` (no `<style scoped>` blocks, no mobile media queries, no mobile card fallback). Mobile capture is optional per module PR.

---

## 14. What This Proposal Does NOT Do

- Does NOT redesign any ambientes surface — it ROLLOUTS the proven language.
- Does NOT add new tokens, primitives, or components.
- Does NOT add dark mode.
- Does NOT add gradients anywhere (`bg-gradient-accent` + `bg-gradient-to-br` are REMOVED, not added).
- Does NOT touch the backend (no controller, no model, no policy, no migration).
- Does NOT relax any standing guard rail from §12.2.
- Does NOT introduce `<style scoped>` blocks (both files have zero by default).
- Does NOT touch `<script>` blocks in either page EXCEPT for the 2 documented exceptions in DLR-AMB-005 (`getStatusColor` → `getStatusVariant` rename in PR-ambientes-01 + `getAuditActionVariant` `'secondary'` → `'neutral'` mapping in PR-ambientes-02). Both are 1-line mechanical renames.
- Does NOT change `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` reactivity.
- Does NOT widen or narrow the `DentalChairController` API envelope (`{ id, name, code, description, equipment, status, is_active, created_at, updated_at, audit_logs }` stays verbatim).
- Does NOT alter the `requireAuth` middleware contract.
- Does NOT extract `useEnvironmentStatuses` shared composable (global §11 forbids new composables).
- Does NOT add `<RoleBanner>` primitive (no primitive exists; defer).
- Does NOT add audit log pagination (out of scope).
- Does NOT add `DentalChair::code` uniqueness client-side validation (server-side 422 surface via `useToast` is the contract).

---

## Key Learnings

1. The `canvasRoutes` detail-route pattern is the load-bearing cross-cutting fix of the AMBIENTES category: 1 `matchesCanvasRoute(path)` helper covers 6 detail routes globally (`/environments/:id`, `/patients/:id`, `/professionals/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary); subsequent category PRs in the global chain get the fix for free without touching `AppLayout.vue` again.
2. Two-page CRUD pattern (list + detail + form modals) is identical across Ambientes + AppointmentTypes + Profesionales — validates global PR4 triplet grouping; per-category 2-PR splits inside ~380-line global PR4 budget.
3. EnvironmentsPage.vue imports `UiSelect`, `UiEmptyState`, `UiLoadingSpinner` but uses bespoke replacements — apply phase activates dormant imports rather than rewriting scripts.
4. Zero `<style scoped>` blocks, zero `@apply`, zero `@keyframes` in the ambientes files — cleanest starting point in the rollout; global OQ#9 grandfather clause does NOT apply.
5. The 2 soft `<script>` conflicts (`getStatusColor` rename + `getAuditActionVariant` `'secondary'` mapping) are mechanical 1-line renames with zero behavioural drift; documenting them as DLR-AMB-005 exceptions is cheaper than inline-v-if workarounds in the template that would duplicate logic across 2 files.

---

*End of ambientes proposal.*
