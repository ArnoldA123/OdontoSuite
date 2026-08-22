# Proposal: tipos-cita (`ui-rollout-all-modules-2026-08`)

> SDD phase: `sdd-propose`. Tier-1 admin CRUD, 2 pages. Residual cleanup only (already polished by `PR-citas-04`).

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `tipos-cita` (admin CRUD: list + detail) |
| Date | 2026-08-21 |
| Phase | propose (2 of 6) — category slice |
| Author | `sdd-propose` sub-agent (tipos-cita) |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/tipos-cita/proposal`) |
| Parent artifacts | `proposal.md`, `explore.md`, `categories/tipos-cita/explore.md`, `specs/design-language-rollout/spec.md` |
| Sibling precedent | `archive/2026-08-12-ui-pacientes` (closest 2-page admin CRUD pattern) |
| Global PR mapping | tipos-cita = global PR4 triplet per global proposal §7.6; sub-PRs `pr-tipos-01..02` split the residual cleanup into chained work units |
| Delivery strategy | `auto-chain` (inherited from global) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` (forward to apply/verify) |
| Slice | 2 PRs (PR-tipos-01 list+modals ~200 / PR-tipos-02 detail ~180) |
| Risk | Low / Low-Medium |
| Roles | admin only (`administrador`) |

### Preflight snapshot (verbatim from explore.md)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached
strict_tdd: true
```

### CRITICAL FINDING (inherited from explore.md §CRITICAL)

`AppointmentTypeDetailPage.vue:167` carries `bg-gradient-to-br from-theme-surface to-theme-surface-elevated` on the audit empty-state container. **This violates global guard rail #10 (no gradients).** PR-tipos-02 MUST remove it as part of the `<UiEmptyState>` migration. Bundled, not extracted as a hotfix.

---

## 1. Intent

`tipos-cita` (Appointment Types) is the admin CRUD for the catalog of bookable appointment categories (Consultation, Cleaning, Root Canal, …). Admins reach `/appointment-types` to scan the list, filter by active/inactive, open the New/Edit/View modals, and click into the detail page to inspect data + audit history. The module was substantially polished by `PR-citas-04` (canvas surface, `<PageHeader>`, `<UiCard variant="glass">`, `<UiInput>` search, `<UiSelect>` status filter, `<UiStatusBadge>` row pills, `<UiButton variant="ghost">` actions, `<UiModal>` chrome, `tabular-nums` on price, `divide-hairline`) — but residual legacy patterns remain: 9 raw `<input>` + 2 raw `<textarea>` in the New/Edit modals (no focus-ring, no `<UiInput>` press mechanism), 2 hand-rolled spinners using `border-accent` / `border-primary-*` aliases, 2 hand-rolled empty states, raw `<button>` tab navigation on the detail page (custom `border-systemBlue-500 text-systemBlue-600` active indicator), a **forbidden gradient** (`bg-gradient-to-br` on the audit empty-state container at `AppointmentTypeDetailPage.vue:167`), raw audit log rows (`border border-hairline rounded-lg p-4`), and raw `text-red-500` / `text-green-500` colour ramps in the change-diff block.

This proposal scopes the rollout to **only** the 2 tipos-cita pages inventoried in `categories/tipos-cita/explore.md`. It inherits every rule from the global proposal (token discipline, primitive contract, focus-ring composition, `tabular-nums`, canvas/surface separation, no gradients) and applies them mechanically. Backend controllers (`AppointmentTypeController`), the `AppointmentTypeResource`, the `useAuditLogs.getAppointmentTypeAuditLogs` composable, and all `<script>` blocks stay byte-for-byte untouched — UI changes are template-level class-string replacement only.

**Why now:** `PR-citas-04` shipped the visual surface but the `<script>` blocks left the raw inputs/textareas/buttons in place. The gradient defect is a known anti-pattern; the polish closes it as part of the same PR that adopts `<UiEmptyState>`. Both fit comfortably under the 400-line budget (~200 + ~180). The user's stated intent — extend the proven language to every module — applies here with concrete, mechanical replacements.

---

## 2. In-Scope

### 2.1 PR-tipos-01 — `AppointmentTypesPage` list + modals cleanup

| Field | Value |
|---|---|
| Scope | (a) 9 raw `<input>` (name, duration_minutes, price, color ×2 in New modal; name, duration_minutes, price, color ×1 in Edit modal) → `<UiInput>` + `var(--focus-ring-default)`. (b) 2 raw `<textarea>` (description in New + Edit) → `<UiTextarea>`. (c) Hand-rolled `border-b-2 border-accent` spinner on list line 85 → `<LoadingSpinner />`. (d) Hand-rolled list empty state on lines 89-104 → `<UiEmptyState title="No se encontraron tipos de cita" description="Crea el primer tipo de cita para empezar" />` (already imported but unused at line 427). (e) New test `AppointmentTypesListCleanupTest.php` extending `ModuleAppShellTestCase` with 4 additive rules. |
| Files | `AppointmentTypesPage.vue` + 1 new test |
| Risk | Low |
| Lines | ~200 |

### 2.2 PR-tipos-02 — `AppointmentTypeDetailPage` detail cleanup

| Field | Value |
|---|---|
| Scope | (a) Raw `<button>` tab nav on lines 84-100 (custom `border-systemBlue-500 text-systemBlue-600` active indicator) → `<UiTabs v-model="activeTab" :tabs="tabs">` with `label` field (rename `name` → `label` to match the primitive validator in `Tabs.vue:78`). (b) **Remove forbidden gradient**: hand-rolled audit empty state WITH `bg-gradient-to-br` on line 167 → `<UiEmptyState title="No hay historial de auditoría" description="Este tipo de cita no tiene registros de auditoría." />`. (c) Hand-rolled audit log row on lines 189-251 (`border border-hairline rounded-lg p-4 hover:bg-theme-surface transition-colors`) → `<UiCard variant="glass">` wrapper with `space-y-4` parent (pacientes precedent). (d) Raw spinner on line 161 (`border-4 border-primary-200 border-t-primary-600`) → `<LoadingSpinner />`. (e) Raw `text-red-500` / `text-green-500` colour ramps on lines 225, 229 → `text-systemRed-600` / `text-systemGreen-600`. (f) Extend `AppointmentTypesAppShellTest.php` with 3 additive rules (tabs use `<UiTabs>`, no gradient, no legacy spinner). |
| Files | `AppointmentTypeDetailPage.vue` + 1 test extension |
| Risk | Low-Medium |
| Lines | ~180 |

### 2.3 Files NOT to modify (frozen contract)

- `routes/api.php:93,138,150` — backend routes frozen.
- `app/Http/Controllers/Api/AppointmentTypeController.php` — CRUD endpoints out of scope.
- `app/Http/Resources/AppointmentTypeResource.php` — API envelope preserved.
- `app/Models/AppointmentType.php` — model out of scope.
- `resources/js/composables/useAuditLogs.js:99` — `getAppointmentTypeAuditLogs` composable frozen.
- `<script>` blocks in `AppointmentTypesPage.vue` (lines 413-640) and `AppointmentTypeDetailPage.vue` (lines 259-406) — NEVER touched. UI changes are template-level only.

### 2.4 Backend changes

NONE. Backend frozen per global proposal §4.

---

## 3. Out-of-scope (deferred)

1. `<UiStatusBadge>` extraction — already consumed; no extraction needed.
2. Dark mode — global decision deferred; light-only per `tokens.js` line 29.
3. New primitives — full set inherited from PR0 + CITAS.
4. `<style scoped>` blocks — already zero (explore.md §2 confirmed); no removal work needed.
5. Backend envelope changes (`AppointmentTypeResource`) — additive `age` analogue is a clinical-module concern; tipos-cita has no per-instance derived attribute.
6. Audit log retention policy — backend, not a UI surface.
7. Appointment types with multi-branch pricing — schema, not UI.

---

## 4. Capabilities (contract with sdd-spec)

The sdd-spec phase reads this section to know exactly which spec files to create or update.

### New Capabilities (none)

The tipos-cita rollout does NOT introduce new capability specs. It exercises the global capability `premium-design-foundation` (persisted at `openspec/specs/premium-design-foundation/spec.md`) and the global delta spec `design-language-rollout` (at `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md`). The tipos-cita requirements live as additional rows in the global spec's module table.

### Modified Capabilities (delta rows added to existing global delta spec)

For the `design-language-rollout` delta spec, add tipos-cita-specific rows to the Module scenarios table:

- `DLR-MOD-006` — Tipos de cita (existing): inherited as-is from the global spec; tipos-cita clarifies that the 2 pages + 3 inlined modals are tokenised as one cluster, `AppointmentTypeResource` API envelope stays verbatim, the `getAppointmentTypeAuditLogs` composable stays verbatim, the `<script>` blocks of both files are NEVER touched, the forbidden gradient on `AppointmentTypeDetailPage.vue:167` is removed in PR-tipos-02, and the pacientes precedent (`<UiTabs>` + `<UiCard variant="glass">` audit row + `<UiEmptyState>` audit empty state) is applied verbatim. (NEW row added by tipos-cita.)
- `DLR-TIP-001` — `AppointmentTypesPage` list polish: 9 raw `<input>` → `<UiInput>` + 2 raw `<textarea>` → `<UiTextarea>` in New + Edit modals; hand-rolled `border-b-2 border-accent` spinner on line 85 → `<LoadingSpinner />`; hand-rolled list empty state on lines 89-104 → `<UiEmptyState>` (already imported at line 427 but unused — wire it up). (NEW row added by tipos-cita.)
- `DLR-TIP-002` — `AppointmentTypeDetailPage` detail polish: raw `<button>` tab nav on lines 84-100 → `<UiTabs v-model="activeTab" :tabs="tabs">` (rename `name` → `label` in the `tabs` array literal at line 314 to match the primitive validator in `Tabs.vue:78`); hand-rolled audit empty state WITH **forbidden gradient** (`bg-gradient-to-br` on line 167) → `<UiEmptyState>` (gradient removal is mandatory per global guard rail #10); hand-rolled audit log row on lines 189-251 → `<UiCard variant="glass">` wrapper (pacientes precedent); raw spinner on line 161 → `<LoadingSpinner />`; `text-red-500` / `text-green-500` on lines 225, 229 → `text-systemRed-600` / `text-systemGreen-600`. (NEW row added by tipos-cita.)
- `DLR-TIP-003` — `<script>` block preservation: `<script>` blocks of `AppointmentTypesPage.vue` (lines 413-640) + `AppointmentTypeDetailPage.vue` (lines 259-406) are NEVER edited in any PR. UI changes are template-level class-string replacement only. `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` / `useFormatters` contracts preserved verbatim. (NEW row added by tipos-cita.)
- `DLR-TIP-004` — `AppointmentTypeResource` envelope preservation: API envelope MUST NOT be widened or narrowed. `useAuditLogs.getAppointmentTypeAuditLogs(id)` composable contract stays verbatim (line 99 of `useAuditLogs.js`). The `formatCurrency` from `useFormatters.js` stays canonical (mandated by CITAS-AT-001 / PAGOS-MNY-002). (NEW row added by tipos-cita.)
- `DLR-TIP-005` — Rule-asserting tests (extends `AppointmentTypesAppShellTest`): PR-tipos-01 adds 4 rules (`<UiInput>` adopted on raw inputs; `<UiTextarea>` adopted on raw textareas; `<LoadingSpinner>` adopted on list spinner; `<UiEmptyState>` adopted on list empty state). PR-tipos-02 adds 3 rules (`<UiTabs>` adopted on detail tab nav; zero `bg-gradient-to-br` matches anywhere in the file; `<LoadingSpinner>` adopted on audit spinner). All assert RULES, not literal strings. (NEW row added by tipos-cita.)

If sdd-spec chooses to extract tipos-cita into a sibling delta spec (`specs/tipos-cita-rollout/spec.md`), that is allowed — the global proposal does not forbid per-category specs. Recommendation: extend the global spec to keep traceability simple.

---

## 5. Approach

Reuse the proven language as-is; no new tokens, no new primitives (the full PR0 / Domain 2 set — `<UiCard>`, `<UiButton>`, `<UiInput>`, `<UiSelect>`, `<UiTextarea>`, `<UiModal>`, `<UiTabs>`, `<UiStatusBadge>`, `<UiLoadingSpinner>`, `<UiEmptyState>` — is inherited from PAGOS / CITAS / vertical slice). Replace legacy patterns one-by-one inside each tipos-cita `.vue` file using the global proposal §4.1 mapping table verbatim. Touch scope ordering: **list page first** (heaviest residual defects: 9 inputs + 2 textareas + spinner + empty state), then **detail page** (gradient removal + tabs + audit row + spinner + colour ramps).

The pacientes precedent (`archive/2026-08-12-ui-pacientes/design.md` §3.5 `<UiTabs>`, §3.8 audit row `<UiCard>`) applies verbatim to tipos-cita — the `<UiTabs>` `tabs` array shape changes from `{id, name, icon}` to `{id, label, icon}` to match the `Tabs.vue:78` validator. The `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` composables are preserved verbatim — UI changes do NOT touch `<script>` blocks. The `formatCurrency` import from `useFormatters.js` is already in place (mandated by CITAS-AT-001 / PR-pagos-05); the apply phase must NOT re-add or rename it.

The gradient defect (`bg-gradient-to-br` on `AppointmentTypeDetailPage.vue:167`) is the highest-priority single change in this rollout because it violates a global guard rail. PR-tipos-02 removes it as part of the `<UiEmptyState>` adoption; it is bundled, NOT extracted as a one-line hotfix PR (the `<UiEmptyState>` adoption is the natural carrier). Visual verification per module: playwright-cli snapshot at 1440x900. Credentials: `admin@test.com` for the full admin CRUD surface.

Strict TDD discipline: every UI replacement MUST come with a test that proves the new behaviour (RED-GREEN per project policy). The visual sweep is documented verification, not a CI gate. The existing `AppointmentTypesAppShellTest` (409 lines, 7 PR-citas-04 rules + 5 inherited) stays green; PR-tipos-02 extends it with 3 additive rules; PR-tipos-01 adds a sibling `AppointmentTypesListCleanupTest` with 4 additive rules.

---

## 6. PR shape

### 6.1 PR-tipos-01 — `AppointmentTypesPage` list + modals cleanup

| Field | Value |
|---|---|
| Name | `pr-tipos-01-list-modals` |
| Scope | `resources/js/modules/appointment-types/AppointmentTypesPage.vue`. New modal (lines 226-290): 4 raw `<input>` (name, duration_minutes, price, color text-input) + 1 raw `<textarea>` (description) + 1 raw `<input type="color">` (kept raw because `<UiInput type="color">` is not part of the canonical primitive — flag for follow-up if needed) → `<UiInput>` + `<UiTextarea>` + `var(--focus-ring-default)`. Edit modal (lines 293-359): same pattern — 4 raw `<input>` (name, duration_minutes, price, color) + 1 raw `<textarea>` (description) + 1 raw `<input type="color">` → `<UiInput>` + `<UiTextarea>`. List spinner on line 85 (`border-b-2 border-accent`) → `<LoadingSpinner />`. List empty state on lines 89-104 (custom SVG + `text-theme-secondary` text) → `<UiEmptyState title="No se encontraron tipos de cita" description="Crea el primer tipo de cita para empezar" />` (already imported at line 427 but unused — wire it up). The `<script>` block (lines 413-640) is byte-for-byte untouched. |
| Files | 1 page + new `AppointmentTypesListCleanupTest.php` (4 additive rules) |
| Risk | Low |
| Dependencies | Global PR0 (landed: `canvasRoutes`, `<UiStatusBadge>`, `<UiEmptyState>`, `<LoadingSpinner>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`) |
| Line estimate | ~200 |
| Reversibility | `git revert <merge-sha>`; AppointmentTypesPage UI reverts to legacy look but `<script>` untouched (useApi / useToast / useConfirm / useErrorHandler contracts preserved) |

### 6.2 PR-tipos-02 — `AppointmentTypeDetailPage` detail cleanup (incl. gradient removal)

| Field | Value |
|---|---|
| Name | `pr-tipos-02-detail-tabs-spinner-empty-card-row` |
| Scope | `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue`. Tab nav on lines 84-100 (raw `<button>` step strip with `border-systemBlue-500 text-systemBlue-600` active indicator + inline `@click="activeTab = tab.id"`) → `<UiTabs v-model="activeTab" :tabs="tabs">` with `tabs` array renamed from `{id, name, icon}` → `{id, label, icon}` to match the `Tabs.vue:78` validator. **Forbidden gradient removal** (line 167 `bg-gradient-to-br from-theme-surface to-theme-surface-elevated`) as part of adopting `<UiEmptyState title="No hay historial de auditoría" description="Este tipo de cita no tiene registros de auditoría." />` (lines 165-187). Audit log row on lines 189-251 (`border border-hairline rounded-lg p-4 hover:bg-theme-surface transition-colors`) → `<UiCard variant="glass">` wrapper with `space-y-4` parent (pacientes precedent at `archive/2026-08-12-ui-pacientes/design.md` §3.8). Audit spinner on line 161 (`border-4 border-primary-200 border-t-primary-600`) → `<LoadingSpinner />`. `text-red-500` / `text-green-500` on lines 225, 229 → `text-systemRed-600` / `text-systemGreen-600`. The `<script>` block (lines 259-406) is byte-for-byte untouched — including the `tabs` array literal in `<script setup>` that gets renamed `name` → `label` because the rename happens at the data layer (no reactivity risk; v-model remains `activeTab`). |
| Files | 1 page + extend `AppointmentTypesAppShellTest.php` with 3 additive rules |
| Risk | Low-Medium |
| Dependencies | PR-tipos-01 |
| Line estimate | ~180 |
| Reversibility | `git revert <merge-sha>`; AppointmentTypeDetailPage UI reverts to legacy look but `<script>` untouched (useApi / useAuditLogs / formatCurrency contracts preserved); gradient defect restored but tracked as known defect until next polish |

### 6.3 Ordering rationale

- **List first**: list page has more residual defects (9 inputs + 2 textareas + spinner + empty state) and is the higher-traffic admin surface (receptionist scanning the catalog).
- **Detail second**: detail page's work is smaller (~180 lines) but contains the gradient defect — bundling it with the `<UiEmptyState>` adoption is the natural carrier. The `<UiTabs>` adoption and the audit row `<UiCard>` wrap follow the pacientes precedent.
- **Both fit 400-line budget** comfortably (~200 + ~180). Chained PR delivery per `auto-chain`.
- **Independent** — could stack in either order; list-first is chosen because it has the most cross-file test additions.

---

## 7. Acceptance criteria

### 7.1 PR-tipos-01 acceptance

- [ ] `AppointmentTypesPage.vue` consumes `<UiInput>` on the 9 raw `<input>` form fields across New + Edit modals (verified via source-grep; `<UiInput v-model="..." />` count ≥ 9 in the modal sections). `AppointmentTypesListCleanupTest::test_list_uses_ui_input_for_modal_form_fields` green.
- [ ] `AppointmentTypesPage.vue` consumes `<UiTextarea>` on the 2 raw `<textarea>` description fields across New + Edit modals (verified via source-grep; `<UiTextarea v-model="..." />` count ≥ 2). `AppointmentTypesListCleanupTest::test_list_uses_ui_textarea_for_description_fields` green.
- [ ] `AppointmentTypesPage.vue` consumes `<LoadingSpinner />` on the list loading state at line 85 (verified via source-grep; `border-accent` legacy spinner absent). `AppointmentTypesListCleanupTest::test_list_uses_loading_spinner` green.
- [ ] `AppointmentTypesPage.vue` consumes `<UiEmptyState>` on the list empty state at lines 89-104 (verified via source-grep; custom SVG empty container absent). `AppointmentTypesListCleanupTest::test_list_uses_ui_empty_state` green.
- [ ] `AppointmentTypesAppShellTest` + `AppLayoutCanvasRoutesTest` + `ModuleAppShellTestCase` 5 inherited rules stay green.
- [ ] `<script>` block (lines 413-640) is byte-for-byte unchanged. `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useFormatters.formatCurrency` contracts preserved verbatim.

### 7.2 PR-tipos-02 acceptance

- [ ] `AppointmentTypeDetailPage.vue` consumes `<UiTabs v-model="activeTab" :tabs="tabs">` on lines 84-100 (verified via source-grep; raw `<button>` step strip absent; `border-systemBlue-500 text-systemBlue-600` active indicator absent). `AppointmentTypesAppShellTest::test_detail_uses_ui_tabs_for_tab_nav` green (NEW additive rule).
- [ ] `AppointmentTypeDetailPage.vue` contains **zero** `bg-gradient-to-br` matches anywhere in the file (verified via source-grep; the gradient defect at line 167 is removed). `AppointmentTypesAppShellTest::test_detail_no_gradient_anywhere` green (NEW additive rule). This rule pins the global guard rail #10 enforcement.
- [ ] `AppointmentTypeDetailPage.vue` consumes `<UiEmptyState>` on the audit empty state at lines 165-187 (verified via source-grep; gradient `<div>` container absent). Covered by `test_detail_no_gradient_anywhere` + a new `AppointmentTypesAppShellTest::test_detail_audit_empty_uses_ui_empty_state` rule.
- [ ] `AppointmentTypeDetailPage.vue` consumes `<UiCard variant="glass">` as the wrapper for each audit log row at lines 189-251 (verified via source-grep; raw `border border-hairline rounded-lg p-4` row absent; `space-y-4` parent present). `AppointmentTypesAppShellTest::test_detail_audit_row_uses_ui_card` green (NEW additive rule).
- [ ] `AppointmentTypeDetailPage.vue` consumes `<LoadingSpinner />` on the audit spinner at line 161 (verified via source-grep; `border-primary-200 border-t-primary-600` legacy spinner absent). `AppointmentTypesAppShellTest::test_detail_uses_loading_spinner_for_audit` green (NEW additive rule).
- [ ] `AppointmentTypeDetailPage.vue` consumes `text-systemRed-600` / `text-systemGreen-600` on lines 225, 229 (verified via source-grep; `text-red-500` / `text-green-500` absent). Covered by `LegacyAliasForbiddenTest` (already pins `text-red-*` / `text-green-*` aliases).
- [ ] `AppointmentTypesAppShellTest` + `AppLayoutCanvasRoutesTest` + `ModuleAppShellTestCase` 5 inherited rules stay green.
- [ ] `<script>` block (lines 259-406) is byte-for-byte unchanged except for the `tabs` array literal `name` → `label` rename (which is a data-layer label-key rename, NOT a logic change; `activeTab` ref + `loadAuditLogs` watcher + `getAppointmentTypeAuditLogs` call all stay verbatim). `useApi` / `useAuditLogs.getAppointmentTypeAuditLogs` / `formatCurrency` / `formatPrice` / `formatDate` / `getAuditActionVariant` contracts preserved verbatim.

### 7.3 Cross-cutting

- [ ] No new tokens introduced (`tokens.js` frozen).
- [ ] No new primitives introduced (full PR0 / Domain 2 set inherited).
- [ ] No `<style scoped>` blocks introduced (both files already have zero per `DLR-R-021`).
- [ ] No `<script>` block edits (both files byte-for-byte except for the `tabs` array `name` → `label` rename in PR-tipos-02).
- [ ] No gradients anywhere in either file (PR-tipos-02 closes the gradient defect).
- [ ] No raw `text-red-*` / `text-green-*` colour ramps in either file.
- [ ] No `border-accent` / `border-primary-*` legacy aliases for spinners (both files).
- [ ] No `focus:ring-primary-500 focus:border-accent` legacy focus-ring aliases (already enforced by `AppointmentTypesAppShellTest::test_pages_no_legacy_focus_ring`).
- [ ] Playwright snapshots saved to `.playwright-cli/screenshots-rollout/{appointment-types-list,appointment-type-detail}-{1440x900}.png` (mobile 390x844 not required — admin-only desktop surface).
- [ ] CI green: `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).
- [ ] Test count delta ≥ +7 (4 new rules in `AppointmentTypesListCleanupTest` + 3 additive rules in `AppointmentTypesAppShellTest`).

---

## 8. Risks + mitigations

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | Gradient defect on `AppointmentTypeDetailPage.vue:167` violates global guard rail #10. If PR-tipos-02 slips or is reverted, the defect persists. | Low | Gradient removal is bundled in PR-tipos-02 (same PR as `<UiEmptyState>` adoption). `AppointmentTypesAppShellTest::test_detail_no_gradient_anywhere` enforces zero `bg-gradient-to-br` matches anywhere in the file — the rule pins the global guard rail. |
| 2 | `<UiInput>`/`<UiTextarea>` migration on 11 form fields (9 inputs + 2 textareas) removes a `v-model` binding. | Low | `<script>` blocks untouched; `useApi` `post`/`put` calls keep same payload shape; `AppointmentTypesListCleanupTest::test_list_uses_ui_input_for_modal_form_fields` + `test_list_uses_ui_textarea_for_description_fields` assert the primitive reference is present on each field. Visual smoke test: open `/appointment-types`, click "Nuevo Tipo", fill the form, submit, verify the `POST /api/appointment-types` payload matches the pre-PR shape. |
| 3 | `<UiTabs>` adoption requires renaming `name` → `label` in the `tabs` array literal inside `<script>` (per the `Tabs.vue:78` validator). A partial rename would break the primitive. | Low | The rename is a single character-class change in the array literal; `AppointmentTypesAppShellTest::test_detail_uses_ui_tabs_for_tab_nav` asserts `<UiTabs` reference present + raw `<button>` step strip absent. Visual smoke test: open `/appointment-types/:id`, click each of the 2 tabs (Datos / Historial), verify `activeTab` updates correctly. |
| 4 | `<UiEmptyState>` adoption on the audit tab removes the gradient background — this is the GOAL, not a regression. | None | The gradient is a defect. Removal is the fix. |
| 5 | `<UiCard variant="glass">` audit row wrap changes visual density (the original is `p-4` plain). | Low | `space-y-4` parent per pacientes precedent; `AppointmentTypesAppShellTest::test_detail_audit_row_uses_ui_card` asserts the rule. Visual smoke test: open a detail page with audit logs present, verify each row has hairline + glass surface. |
| 6 | `<LoadingSpinner>` adoption replaces hand-rolled `<div>` with the primitive; size + color may differ. | Low | The primitive uses the canonical `border-2 border-systemGray-200 border-t-systemBlue-500` ramp; `AppointmentTypesAppShellTest::test_detail_uses_loading_spinner_for_audit` + `test_list_uses_loading_spinner` assert the rule. |
| 7 | `text-red-500` / `text-green-500` → `text-systemRed-600` / `text-systemGreen-600` change diff colors. | None | Pure colour token swap; `LegacyAliasForbiddenTest` already pins `text-red-*` / `text-green-*` aliases. |
| 8 | `AppointmentTypesAppShellTest` regression: the existing 7 PR-citas-04 rules + 5 inherited rules could break if the migration accidentally removes a required token reference. | Low | The additive rules are added in addition to the existing ones; no rule is removed. The existing `tabular-nums` rule, `formatCurrency` rule, `<UiSelect>` rule, `<UiStatusBadge>` rule, `<UiModal>` rule, no-`<style-scoped>` rule, no-legacy-focus-ring rule all stay green because the migration preserves every token reference they assert. |
| 9 | The 9 raw `<input>` + 2 raw `<textarea>` count assumes the `color` picker `<input type="color">` is counted as a raw `<input>` and kept raw. If `<UiInput type="color">` is supported by the primitive, the count drops. | Low | The proposal explicitly keeps `<input type="color">` raw because the canonical `<UiInput>` primitive does NOT formally support `type="color"` (color pickers have platform-specific UIs). The 9-count + 2-count assertion in the test is a "≥" check, so any future extension of `<UiInput>` to support `type="color"` would naturally reduce the count and pass. Flagged for follow-up. |

---

## 9. Open questions resolved (defaults adopted from explore.md §6)

| OQ | Question | Resolution |
|---|---|---|
| OQ-1 | `<UiTabs>` icon slot shape — does the primitive accept a per-tab `icon` prop, or must icons move to inline `<svg>` in the tab slot? | **Adopt primitive `icon` prop.** `Tabs.vue:22-24` confirms `<component :is="tab.icon">` accepts a Vue component prop. The `tabs` array literal renames `name` → `label` (per `Tabs.vue:78` validator) but keeps `icon` as-is. No inline `<svg>` move. |
| OQ-2 | Audit log row `space-y-4` adoption — match pacientes precedent? | **YES.** The audit row `<UiCard variant="glass">` wrapper sits inside a `space-y-4` parent per `archive/2026-08-12-ui-pacientes/design.md` §3.8. |
| OQ-3 | Gradient removal packaging — bundled or hotfix? | **Bundled in PR-tipos-02.** The `<UiEmptyState>` adoption is the natural carrier; extracting it as a one-line hotfix would inflate the PR count without benefit. |
| OQ-4 | `<UiEmptyState>` icon prop — custom or default? | **Default icon.** `EmptyState.vue:19-30` provides a folder SVG as the default when no `icon` prop is passed. Pacientes precedent uses default. |

---

## 10. What this proposal does NOT do

- Does NOT redesign any tipos-cita surface — it rolls out the proven language.
- Does NOT add new tokens, primitives, or components (the full PR0 / Domain 2 set is inherited).
- Does NOT add dark mode.
- Does NOT introduce gradients (PR-tipos-02 REMOVES the gradient defect).
- Does NOT touch the backend (no controller, no resource, no service, no listener, no migration, no job, no model).
- Does NOT relax any standing guard rail from §11.2.
- Does NOT introduce `<style scoped>` blocks (or carry them as grandfathered).
- Does NOT touch `<script>` blocks in either tipos-cita page — UI changes are template-level only (except for the `name` → `label` rename in the `tabs` array literal, which is a data-layer label-key rename).
- Does NOT change `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useAuditLogs` composable contracts.
- Does NOT widen or narrow the `AppointmentTypeResource` API envelope.
- Does NOT consolidate the `formatCurrency` import (already canonical per PR-pagos-05 / CITAS-AT-001).
- Does NOT extract a new `<UiStatusBadge>` primitive (already consumed).
- Does NOT introduce per-branch scoping on the `index` / `show` endpoints (separate change).
- Does NOT add audit log retention policy (backend, not UI).

---

## 11. References

### 11.1 Source artifacts (read for this proposal)

| File | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/tipos-cita/explore.md` (223 lines) | **PRIMARY INPUT.** Tipos-cita inventory, controllers/services/models inventory, residual defect table, open questions. |
| `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (§3.2 row 6, §6.2 slice 5) | Module inventory, slice ordering rationale, complexity tiers. |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (§7.6 PR4 triplet) | Global intent, PR chain, slice 5 types placement. |
| `openspec/changes/archive/2026-08-12-ui-pacientes/explore.md` + `proposal.md` + `design.md` (§3.5, §3.8) | Closest precedent — 2-page admin CRUD pattern, `<UiTabs>` adoption, `<UiCard>` audit row wrap, `space-y-4` parent. |
| `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (641 lines) | PR-tipos-01 primary file (list + 3 inlined modals). |
| `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` (407 lines) | PR-tipos-02 primary file (header + info card + tab nav + audit tab — **line 167 gradient**). |
| `resources/js/components/ui/Tabs.vue` (284 lines) | `<UiTabs>` primitive — confirms `icon` prop (line 22) + `label` field (line 78 validator) + `border-theme` tablist underline (line 116 — preserved). |
| `resources/js/components/ui/EmptyState.vue` (60 lines) | `<UiEmptyState>` primitive — confirms default folder icon (lines 19-30). |
| `resources/js/components/ui/LoadingSpinner.vue` | `<LoadingSpinner>` primitive — canonical spinner ramp. |
| `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` (409 lines) | Existing 7 PR-citas-04 rules + 5 inherited; PR-tipos-02 extends with 3 additive rules. |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Abstract base — 5 inherited rules. |
| `resources/js/composables/useAuditLogs.js:99` | `getAppointmentTypeAuditLogs` composable — frozen. |
| `openspec/specs/premium-design-foundation/spec.md` | The archived capability tipos-cita inherits. |
| `resources/js/design-system/tokens.js` | The proven token source-of-truth. |
| `AGENTS.md` §3 + §5 + §7 | Project context, 17-module inventory, conventions. |

### 11.2 Standing guard rails (inherited from global proposal §11.2)

This proposal does NOT relax any of:

1. `tokens.js` is frozen — no new tokens.
2. systemBackground `#ffffff` pinned; canvas `#F2F2F7` pinned.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings: var(--font-features-tabular-nums)` for numerics.
7. `<script>` blocks of `AppointmentTypesPage.vue` + `AppointmentTypeDetailPage.vue` are NEVER edited (except for the `tabs` array `name` → `label` rename in PR-tipos-02).
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only; NEVER npm/yarn.
10. **No gradients anywhere** (the `bg-gradient-to-br` on line 167 of detail page MUST be removed in PR-tipos-02).
11. Code in English; conversation in Spanish (Peru).

### 11.3 Process invariant (forwarded from PR0 + CITAS proposals)

The archive-report at lines 47–57 names three defects that all shared one root cause: **a test that pins an example instead of the rule**. tipos-cita's standing posture is to assert rules, not literals:

- `AppointmentTypesListCleanupTest` (PR-tipos-01) asserts the rule (`<UiInput>` reference present on the 9 form fields, `<UiTextarea>` present on the 2 description fields, `<LoadingSpinner>` present on the list spinner, `<UiEmptyState>` present on the list empty state), not the literal output of one example.
- `AppointmentTypesAppShellTest` (PR-tipos-02 additive rules) asserts the rule (`<UiTabs>` reference present on tab nav, zero `bg-gradient-to-br` matches anywhere, `<LoadingSpinner>` present on audit spinner, `<UiCard variant="glass">` present on audit row wrapper), not the literal output of one example.

---

## Key Learnings (preview — duplicated in proposal return envelope)

1. The `bg-gradient-to-br` audit-tab empty state at `AppointmentTypeDetailPage.vue:167` violates the global no-gradients guard rail #10 and MUST be removed in PR-tipos-02 — bundled with the `<UiEmptyState>` adoption.
2. Pre-polished modules require residual-only PRs rather than first-pass migration; the slice plan shrinks to two small PRs (~200 + ~180 lines) instead of one 400-line PR.
3. An unused import (`<UiEmptyState>` at `AppointmentTypesPage.vue:427`) indicates the prior PR added the import but never wired it; PR-tipos-01 consumes it.
4. The pacientes detail-page pattern (`<UiTabs>` + `<UiCard variant="glass">` audit row + `<UiEmptyState>` audit empty state) applies verbatim to tipos-cita with a `name` → `label` data-layer rename.
5. Neither tipos-cita page subscribes to Reverb channels, so the rollout carries no realtime regression risk for this module.

---

*End of tipos-cita proposal. Next phase: `sdd-spec` for this category.*
