# Explore: ambientes (ui-rollout-all-modules-2026-08)

> SDD phase: `sdd-explore`. Read-only. Tier-1, 2-page admin CRUD (list + detail + 3 inlined modals + audit log tab).

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | AMBIENTES (admin CRUD) |
| Date | 2026-08-21 |
| Phase | explore (1 of 6) |
| Pace / Artifact store | auto / hybrid |
| Delivery strategy | auto-chain |
| Review budget | 400 authored lines / PR |
| Strict TDD | true |
| Closest precedent | `archive/2026-08-12-ui-pacientes/` |
| Global PR mapping | Part of global PR4 (`pr4-admin-crud-triplet`: AppointmentTypes + Ambientes + Profesionales) |
| Slice plan | 2 PRs |

---

## CRITICAL FINDING (OQ-ambientes-01)

**`/environments/:id` is NOT in `AppLayout.canvasRoutes`** (line 537 lists `/environments` but no `:id` suffix). The detail page will render on `bg-systemBackground` chrome while list pages inherit the canvas surface. This gap affects 6 detail routes: `/patients/:id`, `/professionals/:id`, `/environments/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus the auxiliary ones.

**Recommendation**: Widen the test contract to accept prefix matching (`/environments/:id` matches `/environments`) AND drop the explicit detail-route entries. Alternative: add 6 explicit detail-route entries to `canvasRoutes` + `EXPECTED_ROUTES`. The cost is identical. **BLOCKING for PR-ambientes-01.**

---

## 1. File Inventory

| File | Lines | Size | `<style scoped>` | `@apply` | `@keyframes` | Legacy | Proven |
|---|---|---|---|---|---|---|---|
| `EnvironmentsPage.vue` | 571 | ~19.7 KB | **0** | 0 | 0 | 17 | 0 |
| `EnvironmentDetailPage.vue` | 383 | ~13.6 KB | **0** | 0 | 0 | 9 | 0 |

Both files use **Options API** (not `<script setup>`). They import `<AppLayout>`, `<UiCard>`, `<UiButton>`, `<UiInput>`, `<UiModal>`, `<UiSelect>`, `<UiEmptyState>`, `<UiBadge>` — chrome is tokenized; only inline template class strings carry legacy aliases.

Zero `<style scoped>` blocks. Global OQ#9 grandfather clause does NOT apply. Cleanest starting point in the rollout.

### Backend touchpoints (frozen)

- `app/Http/Controllers/Api/DentalChairController.php` — index/store/show/update/destroy/search/audit. API envelope: `{ id, name, code, description, equipment, status, is_active, created_at, updated_at, audit_logs }`.
- `app/Models/DentalChair.php` — Status enum: `active | inactive | maintenance`. Soft-deletes enabled.
- `useAuditLogs.js:82` — `getDentalChairAuditLogs(chairId)`. Frozen.

### Routes (per `app.js` lines 83–94)

```js
{ path: '/environments', name: 'environments', component: EnvironmentsPage, beforeEnter: requireAuth },
{ path: '/environments/:id', name: 'environment-detail', component: EnvironmentDetailPage, beforeEnter: requireAuth }
```

`requireAuth` only — no role middleware. Policy enforced server-side.

### Test surface

- `tests/Unit/DesignSystem/EnvironmentsAppShellTest.php` — **NOT YET EXISTS.** Precedent: `AppointmentTypesAppShellTest.php` covers both list + detail in one class.
- No Reverb channel for `dental-chairs`. No `useEcho` import. **No realtime regression risk.**

---

## 2. Current Visual State

### 2.1 List page — `EnvironmentsPage.vue` (571 lines)

| Region | Lines | Legacy class strings | Tokenised replacement |
|---|---|---|---|
| Status filter `<select>` | 67 | `border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary` | `<UiSelect :options="statusOptions" v-model="statusFilter">` |
| Table dividers | 104, 134 | `divide-y divide-theme` | `divide-y divide-[color:var(--color-hairline)]` |
| Row avatar background | 144 | `bg-primary-100` | `bg-systemBlue-50` |
| Row avatar text | 146 | `text-accent` | `text-systemBlue-700` |
| "Ver Detalle" link | 182 | `text-accent hover:text-accent-hover` | `<UiButton variant="link">` |
| "Editar" link | 190 | `text-accent hover:text-primary-800` | `<UiButton variant="link">` |
| "Eliminar" link | 198 | `text-red-600 hover:text-red-900` (raw Tailwind) | `<UiButton variant="ghost">` with `text-systemRed-700` |
| Loading spinner | 82 | `inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent` | `<UiLoadingSpinner size="md">` |
| Empty state | 86–101 | Hand-rolled `<svg>` + `<p>` | `<UiEmptyState title="No se encontraron ambientes" description="..." />` |
| Status pill | 172 | `:class="getStatusColor(...)"` returns `bg-success-100 text-success-700` etc. | `<UiStatusBadge :variant="getStatusVariant(environment.status)" :label="getStatusText(...)" />` |
| ID cell | 155 | `<div class="text-sm text-theme-secondary">ID: {{ id }}</div>` | Add `font-feature-settings: var(--font-features-tabular-nums)` |
| New modal: name input | 217–222 | Raw `<input>` with legacy focus chrome | `<UiInput v-model="newEnvironment.name" label="Nombre del Ambiente" required />` |
| New modal: description textarea | 226–230 | Raw `<textarea>` | `<UiTextarea v-model="newEnvironment.description" label="Descripción" :rows="3" />` |
| New modal: equipment textarea | 234–238 | Raw `<textarea>` | `<UiTextarea v-model="newEnvironment.equipment" label="Equipamiento" :rows="2" />` |
| New modal: status select | 242–250 | Raw `<select>` | `<UiSelect :options="statusOptions" v-model="newEnvironment.status" label="Estado" required />` |
| Edit modal (4 fields) | 271–305 | Same raw input/textarea/select pattern | Same `<UiInput>` / `<UiTextarea>` / `<UiSelect>` migration |
| View modal status pill | 340–346 | `bg-success-100 text-success-700` pattern | `<UiStatusBadge>` |

### 2.2 Detail page — `EnvironmentDetailPage.vue` (383 lines)

| Region | Lines | Legacy class strings | Tokenised replacement |
|---|---|---|---|
| Header card gradient avatar | 30 | `h-16 w-16 rounded-xl bg-gradient-accent` | `bg-systemBlue-50 rounded-[var(--radius-card-lg)]` (NO gradients per global §11) |
| Tab strip | 70 | `border-b border-theme` | `border-b border-[color:var(--color-hairline)]` |
| Active tab indicator | 77 | `border-accent text-accent` | `border-systemBlue-600 text-systemBlue-700` |
| Inactive tab hover | 78 | `border-transparent text-theme-secondary hover:text-theme-primary hover:border-theme` | `<UiTabs v-model="activeTab" :tabs="tabs">` primitive |
| Audit log loading spinner | 141 | `inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600` | `<UiLoadingSpinner size="md">` |
| Audit log empty state | 147–167 | Hand-rolled `bg-gradient-to-br` + svg + h3 + p | `<UiEmptyState title="No hay historial de auditoría" ... />` |
| Audit log card | 172 | `border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors` | `<UiCard>` wrapper |
| Audit log change-diff left border | 198 | `border-l-2 border-theme` | `border-l-2 border-[color:var(--color-hairline)]` |
| Old/new value colors | 203, 207 | `text-red-500` / `text-green-500` (raw Tailwind) | `text-systemRed-600` / `text-systemGreen-600` |
| Status pill in header | 61 | Already `<UiBadge>` | KEEP |
| Audit action badges | 177 | `<UiBadge :variant="getAuditActionVariant(log.action)">` — `'secondary'` NOT a valid UiStatusBadge variant | Map `'secondary'` → `'neutral'` |

### 2.3 `getStatusColor` helper (lines 516–532)

After tokenisation, collapses to `getStatusVariant`:

```js
const getStatusVariant = status => ({
  active: 'success', inactive: 'neutral', maintenance: 'warning'
})[status] || 'neutral'
```

---

## 3. Gap Analysis

### 3.1 Tokens — already complete

| Need | Token | Verified |
|---|---|---|
| Canvas surface on list page | `/environments` in canvasRoutes (line 537) | YES |
| Canvas surface on detail page | `/environments/:id` NOT in canvasRoutes | **NO — gap.** See §3.4. |
| Hairline borders | `border-[color:var(--color-hairline)]` | YES |
| Status pills | `<UiStatusBadge>` | YES |
| Focus ring on form fields | `var(--focus-ring-default)` via `<UiInput>` | YES |
| Tabular-nums on ID cell | `font-feature-settings: var(--font-features-tabular-nums)` | YES |
| Tab strip | `<UiTabs>` | YES |
| Loading spinner | `<UiLoadingSpinner>` | YES |
| Empty state | `<UiEmptyState>` | YES |
| Modal chrome | `<UiModal>` | YES (already used) |

### 3.2 Primitives — all exist

- `<AppLayout>`, `<PageHeader>`: already used
- `<UiModal>`: already used (3 inlined modals)
- `<UiInput>` / `<UiSelect>` / `<UiTextarea>`: imported but UNUSED in modals + status filter
- `<UiStatusBadge>`: NOT IMPORTED on list page
- `<UiTabs>`: NOT IMPORTED on detail page
- `<UiLoadingSpinner>`: NOT IMPORTED on either page
- `<UiEmptyState>`: IMPORTED on list page but UNUSED
- `<UiButton>`: already used

### 3.3 Cross-cutting

Zero `<style scoped>` blocks — DLR-R-021 GREEN by default.

### 3.4 Critical finding — detail route in `canvasRoutes`

`AppLayout.vue:537` lists `/environments` but NOT `/environments/:id`. The detail page renders on `bg-systemBackground` chrome, NOT the canvas. Detail pages read as the pre-rollout "cards on white" — visual inconsistency vs list pages.

**Recommendation**: widen `AppLayoutCanvasRoutesTest` to accept prefix matching. Alternative: add 6 explicit detail-route entries (`/patients/:id`, `/professionals/:id`, `/environments/:id`, `/appointment-types/:id`, `/procedure-catalog/:id`, plus auxiliary). Surface as propose-phase OQ. **BLOCKING for PR-ambientes-01.**

### 3.5 Pre-existing test gaps

| Gap | Fix |
|---|---|
| No `EnvironmentsAppShellTest.php` | CREATE in PR-ambientes-01 |
| `LegacyAliasForbiddenTest` does NOT include ambientes files | Extend in PR-ambientes-01 |
| `AppLayoutCanvasRoutesTest` does not require `/environments/:id` | Update test contract per §3.4 |

---

## 4. Risk Assessment

| Risk | Blast radius | Mitigation |
|---|---|---|
| `<script>` blocks contain business logic that MUST stay verbatim | Low | `<script>` blocks NEVER touched (template-level class replacements only) |
| 9 raw form fields in 3 inlined modals to migrate to primitives | Low | Keep all v-model bindings + `required` attributes byte-for-byte |
| `<UiTabs>` migration requires changing `activeTab` ref interaction | Low | Pattern matches `PatientDetailPage.vue` 5-tab drawer precedent |
| `getStatusColor` → `getStatusVariant` rename in `<script>` | Low | 1-line additive change OR inline v-if chain — DECIDE in propose |
| `getAuditActionVariant` `'secondary'` → `'neutral'` | Low | 1-line `<script>` edit OR template mapping — DECIDE in propose |
| Header avatar gradient `bg-gradient-accent` (forbidden) | Low | Replace with `bg-systemBlue-50` |
| Detail route not in canvasRoutes | **Medium** | Fix in PR-ambientes-01 per §3.4 |
| No Reverb channel for `dental-chairs` | None | No realtime regression risk |

---

## 5. Suggested Slice Plan (2 PRs)

### PR-ambientes-01 — list page + canvasRoutes fix

| Field | Value |
|---|---|
| Name | `pr-ambientes-01-list-page-and-canvas-routes-detail-fix` |
| Scope | (1) `EnvironmentsPage.vue` tokenisation: status filter → `<UiSelect>`; table dividers → hairline; row avatar → tokenised systemBlue ramps; link buttons → `<UiButton variant="link/ghost">`; loading spinner → `<UiLoadingSpinner>`; empty state → `<UiEmptyState>`; status pill → `<UiStatusBadge>`; ID cell → `tabular-nums`. (2) Fix `/environments/:id` not in canvasRoutes — widen test contract to accept prefix matching. (3) Extend `LegacyAliasForbiddenTest::defaultPolishedFiles()` to include `EnvironmentsPage.vue`. (4) Create `EnvironmentsAppShellTest.php` for list page. (5) Create `EnvironmentsStatusBadgeTest.php` asserting `<UiStatusBadge>` adoption. |
| Files | `EnvironmentsPage.vue`, `AppLayout.vue` (canvasRoutes fix), `AppLayoutCanvasRoutesTest.php`, `LegacyAliasForbiddenTest.php`, new tests × 2 |
| Risk | Low |
| Dependencies | PR0 (already merged) |
| Line estimate | ~200 authored |

### PR-ambientes-02 — detail page + 3 inlined modals + audit tab

| Field | Value |
|---|---|
| Name | `pr-ambientes-02-detail-page-and-modals` |
| Scope | (1) `EnvironmentDetailPage.vue`: tab strip → `<UiTabs>`; header avatar gradient → flat `bg-systemBlue-50`; audit log spinner → `<UiLoadingSpinner>`; empty state → `<UiEmptyState>`; card wrapper → `<UiCard>`; hairline borders; `text-red-500`/`text-green-500` → systemRed/systemGreen. (2) `getAuditActionVariant` `'secondary'` → `'neutral'` mapping. (3) `EnvironmentsPage.vue` 3 inlined modals: 9 raw form fields → `<UiInput>` / `<UiTextarea>` / `<UiSelect>` primitives. (4) Extend `EnvironmentsAppShellTest.php` to include detail page. (5) Extend `LegacyAliasForbiddenTest`. (6) Create `EnvironmentsModalChromeTest.php`. |
| Files | `EnvironmentDetailPage.vue`, `EnvironmentsPage.vue` (modals), test extensions, new modal chrome test |
| Risk | Low |
| Dependencies | PR-ambientes-01 |
| Line estimate | ~280 authored |

### Why 2 PRs

- 2 PRs fits global PR4 window (~380 lines combined with AppointmentTypes + Profesionales)
- Matches PR-pacientes-01..05 split logic (list vs detail + modals)
- PR-ambientes-01 must land first (canvasRoutes fix + list establishes canvas surface)

### Strict ordering

PR-ambientes-01 MUST land before PR-ambientes-02. If detail page lands first without canvasRoutes fix, it renders on white chrome until PR-01 catches up — visually inconsistent.

---

## 6. Open Questions for Proposal Phase

- **OQ-ambientes-01 [BLOCKING]**: Widen test contract to accept prefix matching OR add 6 explicit detail-route entries?
- **OQ-ambientes-02 [soft]**: Rename `getStatusColor` → `getStatusVariant` in `<script>` (1-line additive change) OR inline v-if chain in template? Global rule says `<script>` is NEVER touched.
- **OQ-ambientes-03 [soft]**: Map `'secondary'` → `'neutral'` in `<script>` OR change helper return value directly?
- **OQ-ambientes-04**: Detail-page header avatar: `bg-systemBlue-50` OR `bg-theme-surface-elevated`?
- **OQ-ambientes-05**: Old/new value colors: `text-systemRed-600` / `text-systemGreen-600` (precedent) OR `text-systemRed-700` / `text-systemGreen-700` (darker)?
- **OQ-ambientes-06**: Extract `useEnvironmentStatuses` shared composable? Global §11 forbids new composables — keep duplication.
- **OQ-ambientes-07**: Admin-only role banner? No `<RoleBanner>` primitive exists — defer.
- **OQ-ambientes-08**: Visual verification: desktop only (1440x900), mobile optional?

---

## 7. References

- `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` §3.2 row 5
- `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §2.1 row 5 + §7.6 PR4
- `openspec/changes/archive/2026-08-12-ui-pacientes/{explore,design,proposal}.md` (closest precedent)
- `resources/js/modules/environments/EnvironmentsPage.vue` (571 lines)
- `resources/js/modules/environments/EnvironmentDetailPage.vue` (383 lines)
- `resources/js/app.js` lines 83–94 (router)
- `resources/js/components/layout/AppLayout.vue` lines 523–557 (`canvasRoutes`) — **gap**
- `tests/Unit/DesignSystem/{ModuleAppShellTestCase,AppLayoutCanvasRoutesTest,LegacyAliasForbiddenTest,AppointmentTypesAppShellTest,PatientsListAppShellTest}.php`
- `resources/js/design-system/tokens.js`
- `app/Http/Controllers/Api/DentalChairController.php` (frozen)
- `app/Models/DentalChair.php` (status enum: `active | inactive | maintenance`)
- AGENTS.md §5

---

## Key Learnings

1. `/environments/:id` missing from `canvasRoutes` — affects 5 other detail routes; needs global fix in PR-ambientes-01.
2. Zero `<style scoped>` blocks, zero `@apply`, zero `@keyframes` — cleanest starting point; global OQ#9 grandfather clause does NOT apply.
3. EnvironmentsPage.vue imports `UiSelect`, `UiEmptyState`, `UiLoadingSpinner` but uses bespoke replacements — apply phase activates dormant imports rather than rewriting scripts.
4. Two-page CRUD pattern identical across Ambientes + AppointmentTypes + Profesionales — validates global PR4 triplet grouping; per-category 2-PR splits inside ~380-line global PR4 budget.
5. No Reverb channel for `dental-chairs`; no `useEcho` import — zero realtime regression risk.

---

*End of category explore. Next phase: `sdd-propose` (must resolve OQ-ambientes-01 before apply).*
