# Spec: tipos-cita Category Delta (ui-rollout-all-modules-2026-08)

> 2 chained PRs. Residual cleanup only (already PR-citas-04-polished).
> Sibling delta spec to the global `design-language-rollout` spec.
> Naming convention: `TIPOS-01-NNN` for PR-tipos-01 MUSTs, `TIPOS-02-NNN` for PR-tipos-02 MUSTs.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `tipos-cita` (admin CRUD: list + detail) |
| Date | 2026-08-21 |
| Phase | spec (3 of 6) — category slice |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/tipos-cita/spec`) |
| Delivery strategy | `auto-chain` (inherited from global) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` (forward to apply/verify) |
| Parent spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` |
| Parent proposal | `openspec/changes/ui-rollout-all-modules-2026-08/categories/tipos-cita/proposal.md` |
| Parent explore | `openspec/changes/ui-rollout-all-modules-2026-08/categories/tipos-cita/explore.md` |
| Closest precedent | `openspec/changes/archive/2026-08-12-ui-pacientes/specs/pacientes/spec.md` (2-page CRUD) |
| Slices | `PR-tipos-01` (list + modals ~200 lines) + `PR-tipos-02` (detail + gradient removal ~180 lines) |
| Roles | admin only (`administrador`) |

### Relationship to parent spec

This spec does NOT modify the global `design-language-rollout/spec.md`
rows. It is a sibling delta that adds tipos-cita-specific MUSTs for the
2 chained PRs. The global `DLR-CORE-*` and `DLR-MOD-006` rules apply
unmodified; the rows below pin category-specific edges: raw `<input>` /
`<textarea>` migration to `<UiInput>` / `<UiTextarea>` in 2 inlined
modals, hand-rolled spinner + empty state replacement, tab navigation
via `<UiTabs>` (with `name` → `label` data-layer rename), audit-row
`<UiCard variant="glass">` wrap, audit empty state `<UiEmptyState>`
adoption, and the **forbidden gradient removal** at
`AppointmentTypeDetailPage.vue:167`.

---

## 1. Scope

### 1.1 PR-tipos-01 scope

| Field | Value |
|---|---|
| Target file | `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (641 lines) |
| New test | `tests/Unit/DesignSystem/AppointmentTypesListCleanupTest.php` (4 additive rules) |
| Line estimate | ~200 |
| Risk | Low |

Replacements:

- 9 raw `<input>` (name / duration_minutes / price / color in New + Edit modals) → `<UiInput v-model="..." />` for the 7 text/number inputs; the 2 `<input type="color">` color pickers MAY remain raw (the canonical `<UiInput>` primitive does not formally support `type="color"`).
- 2 raw `<textarea>` (description in New + Edit modals) → `<UiTextarea v-model="..." />`.
- Hand-rolled `border-b-2 border-accent` spinner on line 85 → `<LoadingSpinner />`.
- Hand-rolled list empty state on lines 89-104 → `<UiEmptyState title="No se encontraron tipos de cita" description="Crea el primer tipo de cita para empezar" />` (already imported at line 427 but never wired — consume it).
- `<script>` block (lines 413-640) is **byte-for-byte unchanged**.

### 1.2 PR-tipos-02 scope

| Field | Value |
|---|---|
| Target file | `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` (407 lines) |
| Test extension | `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` (3 additive rules) |
| Line estimate | ~180 |
| Risk | Low-Medium (gradient removal is the load-bearing defect) |

Replacements:

- Raw `<button>` tab nav on lines 84-100 → `<UiTabs v-model="activeTab" :tabs="tabs">` with `tabs` array `name` → `label` rename (per `Tabs.vue:78` validator).
- **[CRITICAL]** Hand-rolled audit empty state WITH **forbidden gradient** (`bg-gradient-to-br` on line 167) → `<UiEmptyState>`. Gradient removal is mandatory per global guard rail #10.
- Hand-rolled audit log row on lines 189-251 → `<UiCard variant="glass">` wrapper with `space-y-4` parent (pacientes precedent).
- Raw spinner on line 161 (`border-4 border-primary-200 border-t-primary-600`) → `<LoadingSpinner />`.
- `text-red-500` / `text-green-500` on lines 225, 229 → `text-systemRed-600` / `text-systemGreen-600`.
- `<script>` block (lines 259-406) is **byte-for-byte unchanged** except for the `tabs` array literal `name` → `label` rename (data-layer label-key rename, NOT a logic change).

---

## 2. Requirements (PR-tipos-01 — list + modals)

### 2.1 [TIPOS-01-001] 9 raw `<input>` → `<UiInput>` in New + Edit modals

The system MUST replace every raw `<input type="text">` and `<input type="number">` form field in the New modal (lines 226-290) and the Edit modal (lines 293-359) of `AppointmentTypesPage.vue` with `<UiInput v-model="..." />` consuming the canonical `var(--focus-ring-default)` focus ring. The 2 `<input type="color">` color pickers (New modal line 270 + Edit modal line 337) MAY remain raw because the canonical `<UiInput>` primitive does not formally support `type="color"`. The migration MUST NOT remove any `v-model` binding (`newType.name`, `newType.duration_minutes`, `newType.price`, `newType.color` text-input, `editingType.name`, `editingType.duration_minutes`, `editingType.price`, `editingType.color` text-input) and MUST NOT touch the `<script>` block.

#### Scenario: TIPOS-01-001-1 — All text/number inputs in modals adopt `<UiInput>`

- GIVEN the New + Edit modals contain 7 raw text/number inputs and 2 `<input type="color">` color pickers
- WHEN PR-tipos-01 lands
- THEN `AppointmentTypesListCleanupTest::test_list_uses_ui_input_for_modal_form_fields` asserts the POSITIVE rule (≥7 `<UiInput v-model="...">` references present in the modal sections)
- AND asserts the NEGATIVE rule (zero raw `<input type="text">` or `<input type="number">` elements in the modal sections)
- AND all 8 `v-model` bindings to `newType.*` + `editingType.*` remain bound (the `<script>` block is byte-for-byte unchanged)

### 2.2 [TIPOS-02-002 → TIPOS-01-002] 2 raw `<textarea>` → `<UiTextarea>` in New + Edit modals

The system MUST replace the 2 raw `<textarea>` description fields (New modal line 239, Edit modal line 306) of `AppointmentTypesPage.vue` with `<UiTextarea v-model="..." />`. The `v-model="newType.description"` and `v-model="editingType.description"` bindings MUST be preserved verbatim.

#### Scenario: TIPOS-01-002-1 — Description fields adopt `<UiTextarea>`

- GIVEN the New + Edit modals each render a raw `<textarea v-model="...description">`
- WHEN PR-tipos-01 lands
- THEN `AppointmentTypesListCleanupTest::test_list_uses_ui_textarea_for_description_fields` asserts ≥2 `<UiTextarea v-model="...">` references
- AND asserts zero raw `<textarea>` elements remain in the modal sections
- AND visual smoke test: open the New modal, type into the description field, verify the `POST /api/appointment-types` payload includes the typed value

### 2.3 [TIPOS-01-003] hand-rolled spinner → `<LoadingSpinner>` on list loading state

The system MUST replace the hand-rolled `border-b-2 border-accent` spinner on `AppointmentTypesPage.vue:85` with `<LoadingSpinner />` (the canonical primitive). The `border-accent` legacy alias MUST be removed.

#### Scenario: TIPOS-01-003-1 — List spinner consumes `<LoadingSpinner>` primitive

- GIVEN the list loading state renders a hand-rolled `<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />`
- WHEN PR-tipos-01 lands
- THEN `AppointmentTypesListCleanupTest::test_list_uses_loading_spinner` asserts the POSITIVE rule (`<LoadingSpinner` reference present on line 85 vicinity)
- AND asserts the NEGATIVE rule (zero `border-accent` legacy alias anywhere in the file)

### 2.4 [TIPOS-01-004] hand-rolled empty state → `<UiEmptyState>` (already imported, unused)

The system MUST replace the hand-rolled empty state with custom SVG (lines 89-104) of `AppointmentTypesPage.vue` with `<UiEmptyState title="No se encontraron tipos de cita" description="Crea el primer tipo de cita para empezar" />`. The `<UiEmptyState>` import at line 427 already exists but is unused — this requirement wires it up.

#### Scenario: TIPOS-01-004-1 — List empty state consumes `<UiEmptyState>` primitive

- GIVEN the list empty state renders a hand-rolled `<svg> + <p>` pair with `text-theme-secondary` chrome
- WHEN PR-tipos-01 lands
- THEN `AppointmentTypesListCleanupTest::test_list_uses_ui_empty_state` asserts the POSITIVE rule (`<UiEmptyState` reference present in the list section)
- AND asserts the NEGATIVE rule (the hand-rolled custom SVG empty container absent on lines 89-104)
- AND the `UiEmptyState` import at line 427 is now consumed (no dead import remains)

---

## 3. Requirements (PR-tipos-02 — detail + gradient removal)

### 3.1 [TIPOS-02-001] Raw `<button>` tab nav → `<UiTabs>` (with `name` → `label` rename)

The system MUST replace the raw `<button>` step strip with `border-systemBlue-500 text-systemBlue-600` active indicator (lines 84-100) on `AppointmentTypeDetailPage.vue` with `<UiTabs v-model="activeTab" :tabs="tabs" />`. The `tabs` array literal in `<script>` (lines 314-325) MUST rename its `name` field to `label` to match the `Tabs.vue:78` validator. The `id` and `icon` fields stay verbatim. The `activeTab` ref + `loadAuditLogs` watcher + `getAppointmentTypeAuditLogs(id)` call MUST stay verbatim.

#### Scenario: TIPOS-02-001-1 — Detail tab nav consumes `<UiTabs>` with `label` field

- GIVEN the detail page renders a 2-tab strip (Datos / Historial) with raw `<button>` + custom `border-systemBlue-500 text-systemBlue-600` active indicator
- WHEN PR-tipos-02 lands
- THEN `AppointmentTypesAppShellTest::test_detail_uses_ui_tabs_for_tab_nav` asserts the POSITIVE rule (`<UiTabs` reference present on lines 84-100)
- AND asserts the NEGATIVE rule (zero raw `<button>` step strip; zero `border-systemBlue-500 text-systemBlue-600` active indicator classes)
- AND asserts the `tabs` array literal in `<script>` uses `label` (NOT `name`)
- AND visual smoke test: open `/appointment-types/:id`, click each tab, verify `activeTab` updates and the audit tab triggers `loadAuditLogs`

### 3.2 [TIPOS-02-002] **[CRITICAL]** Forbidden gradient removal at `AppointmentTypeDetailPage.vue:167`

The system MUST remove the **forbidden gradient** `bg-gradient-to-br from-theme-surface to-theme-surface-levated` on the audit empty-state container at `AppointmentTypeDetailPage.vue:167`. This violates global guard rail #10 ("no gradients anywhere") and MUST be removed in PR-tipos-02. The migration adopts `<UiEmptyState title="No hay historial de auditoría" description="Este tipo de cita no tiene registros de auditoría." />` which removes the gradient as a side-effect. The gradient removal MUST NOT be deferred, extracted as a hotfix, or bundled into a later PR.

#### Scenario: TIPOS-02-002-1 — Zero `bg-gradient` matches anywhere in the detail file

- GIVEN `AppointmentTypeDetailPage.vue:167` carries `bg-gradient-to-br from-theme-surface to-theme-surface-elevated` on the audit empty-state container
- WHEN PR-tipos-02 lands
- THEN `AppointmentTypesAppShellTest::test_detail_no_gradient_anywhere` asserts the NEGATIVE rule (zero `bg-gradient-to-br` matches anywhere in the file)
- AND `AppointmentTypesAppShellTest::test_detail_audit_empty_uses_ui_empty_state` asserts the POSITIVE rule (`<UiEmptyState` reference present on lines 165-187)
- AND visual smoke test: open a detail page with no audit logs, verify the audit empty state renders on canvas (no gradient background)
- AND CI gate: `LegacyAliasForbiddenTest` extends the gradient check; future PRs that reintroduce gradients fail at the assertion

### 3.3 [TIPOS-02-003] hand-rolled audit empty state → `<UiEmptyState>`

The system MUST replace the hand-rolled audit empty state (lines 165-187) of `AppointmentTypeDetailPage.vue` with `<UiEmptyState title="No hay historial de auditoría" description="Este tipo de cita no tiene registros de auditoría." />`. This is the same surface as TIPOS-02-002 (the gradient removal is a side-effect of this adoption).

#### Scenario: TIPOS-02-003-1 — Audit empty state adopts `<UiEmptyState>`

- GIVEN the audit empty state renders a hand-rolled `<div>` container with custom SVG + `text-theme-primary` heading + `text-theme-secondary` paragraph
- WHEN PR-tipos-02 lands
- THEN `AppointmentTypesAppShellTest::test_detail_audit_empty_uses_ui_empty_state` asserts the POSITIVE rule (`<UiEmptyState` reference present on lines 165-187)
- AND asserts the NEGATIVE rule (hand-rolled custom SVG empty container absent)

### 3.4 [TIPOS-02-004] hand-rolled audit log row → `<UiCard variant="glass">` wrapper

The system MUST replace each hand-rolled audit log row (`border border-hairline rounded-lg p-4 hover:bg-theme-surface transition-colors` on lines 189-251) of `AppointmentTypeDetailPage.vue` with a `<UiCard variant="glass">` wrapper. The audit log rows MUST be wrapped in a `space-y-4` parent (matching the pacientes precedent at `archive/2026-08-12-ui-pacientes/design.md` §3.8).

#### Scenario: TIPOS-02-004-1 — Audit log row wraps in `<UiCard variant="glass">`

- GIVEN each audit log row renders a raw `<div class="border border-hairline rounded-lg p-4 hover:bg-theme-surface transition-colors">`
- WHEN PR-tipos-02 lands
- THEN `AppointmentTypesAppShellTest::test_detail_audit_row_uses_ui_card` asserts the POSITIVE rule (`<UiCard variant="glass">` reference present in the audit-log row section)
- AND asserts the NEGATIVE rule (raw `border border-hairline rounded-lg p-4 hover:bg-theme-surface transition-colors` absent)
- AND asserts the `space-y-4` parent is present (pacientes precedent)

### 3.5 [TIPOS-02-005] hand-rolled audit spinner → `<LoadingSpinner>`

The system MUST replace the hand-rolled `border-4 border-primary-200 border-t-primary-600` spinner on `AppointmentTypeDetailPage.vue:161` with `<LoadingSpinner />`. The `border-primary-*` legacy alias MUST be removed.

#### Scenario: TIPOS-02-005-1 — Audit spinner consumes `<LoadingSpinner>` primitive

- GIVEN the audit loading state renders a hand-rolled `<div class="animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600" />`
- WHEN PR-tipos-02 lands
- THEN `AppointmentTypesAppShellTest::test_detail_uses_loading_spinner_for_audit` asserts the POSITIVE rule (`<LoadingSpinner` reference present on line 161 vicinity)
- AND asserts the NEGATIVE rule (zero `border-primary-200` or `border-t-primary-600` legacy aliases anywhere in the file)

### 3.6 [TIPOS-02-006] raw `text-red-500` / `text-green-500` → system ramps

The system MUST replace the raw `text-red-500` (line 225) and `text-green-500` (line 229) Tailwind colour ramps on `AppointmentTypeDetailPage.vue` with `text-systemRed-600` and `text-systemGreen-600` (token-aligned Apple-language ramps). These ramps live in the change-diff block under each audit log row.

#### Scenario: TIPOS-02-006-1 — Audit diff colours consume system ramps

- GIVEN the change-diff block renders `<span class="text-red-500">{{ change.old }}</span>` and `<span class="text-green-500">{{ change.new }}</span>`
- WHEN PR-tipos-02 lands
- THEN `LegacyAliasForbiddenTest` (extended) asserts zero `text-red-500` and zero `text-green-500` matches in either tipos-cita page
- AND `AppointmentTypesAppShellTest` asserts the POSITIVE rule (`text-systemRed-600` + `text-systemGreen-600` references present in the audit diff block)

---

## 4. Inherited MUST (re-asserted from parent + precedent)

These MUSTs are NOT new — they are inherited from the parent DLR spec
and PACIENTES precedent, and they apply to tipos-cita verbatim:

- **DLR-R-001 canvas surface**: `AppointmentTypesPage.vue` + `AppointmentTypeDetailPage.vue` MUST reference `bg-canvas` / `var(--color-canvas)` / `rgb(242, 242, 247)`. Inherited from `ModuleAppShellTestCase::test_page_references_canvas_token`. GREEN before/after PR-tipos-01..02.
- **DLR-R-002 no `border-theme` literal**: both files MUST NOT contain `border-theme`. Inherited. GREEN before/after.
- **DLR-R-004 no legacy focus-ring aliases**: both files MUST NOT contain `focus:ring-primary-500` or `focus:border-accent`. Inherited via `AppointmentTypesAppShellTest::test_pages_no_legacy_focus_ring`. GREEN.
- **DLR-R-007 `tabular-nums` on price**: both files MUST apply `tabular-nums` (or the token form) on the price column / summary. Inherited via `AppointmentTypesAppShellTest::test_pages_price_column_uses_tabular_nums`. GREEN.
- **DLR-R-009 no legacy status pills**: neither file MUST contain `bg-success-100`, `bg-error-100`, `bg-warning-100`, `text-success-700`, `text-error-700`, `text-warning-700`. Inherited via `AppointmentTypesAppShellTest::test_pages_no_legacy_status_pills`. GREEN.
- **DLR-R-021 no `<style scoped>` block**: neither file MUST contain a `<style scoped>` block. Inherited via `ModuleAppShellTestCase::test_no_style_scoped` + `AppointmentTypesAppShellTest::test_pages_no_style_scoped`. GREEN (already zero per `explore.md` §2).
- **CITAS-AT-001 formatCurrency canonical**: both files MUST import `formatCurrency` from the canonical `useFormatters.js` location. Inherited. GREEN.
- **CITAS-CON-001 `useApi` ownership**: the list page MUST keep `useApi` import verbatim (owns the 401 redirect per UXF-021). Inherited via `AppointmentTypesAppShellTest::test_list_page_use_api_ownership_preserved`. GREEN.

---

## 5. Out of scope

Items deferred per `categories/tipos-cita/proposal.md` §3:

1. `<UiStatusBadge>` extraction — already consumed; no extraction needed.
2. Dark mode — global decision deferred; light-only per `tokens.js` line 29.
3. New primitives — full set inherited from PR0 + CITAS; no additions.
4. `<style scoped>` blocks — already zero (explore.md §2 confirmed); no removal work needed.
5. `AppointmentTypeResource` envelope changes — additive `age` analogue is a clinical-module concern; tipos-cita has no per-instance derived attribute.
6. Audit log retention policy — backend, not a UI surface.
7. Multi-branch pricing for appointment types — schema, not UI.
8. `<input type="color">` color picker migration — kept raw because `<UiInput>` does not formally support `type="color"`. Flagged for follow-up.
9. Backend controllers / services / jobs / listeners / events — frozen per global proposal §4.
10. `<script>` block edits in either tipos-cita page — except for the `tabs` array `name` → `label` rename in PR-tipos-02 (data-layer label-key rename, NOT a logic change).
11. Cross-tab create buttons / deep-links — N/A; tipos-cita has no per-tab create flow.
12. Echo / Reverb channels — N/A; neither tipos-cita page subscribes to realtime channels.

---

## 6. References

- `categories/tipos-cita/explore.md` — tipos-cita inventory (file table, residual defect table, open questions).
- `categories/tipos-cita/proposal.md` — tipos-cita proposal (intent, scope, risk register, acceptance, references).
- `specs/design-language-rollout/spec.md` — parent spec (`DLR-MOD-006` Tipos de cita + cross-cutting `DLR-CORE-*` rules).
- `specs/foundation-primitives/spec.md` — PR0 spec (`<UiStatusBadge>`, `<UiEmptyState>`, `<LoadingSpinner>`, `canvasRoutes`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`).
- `archive/2026-08-12-ui-pacientes/specs/pacientes/spec.md` — closest precedent (2-page admin CRUD pattern).
- `archive/2026-08-12-ui-pacientes/design.md` (§3.5 `<UiTabs>`, §3.8 audit row `<UiCard>` + `space-y-4`) — tipos-cita detail-page pattern applied verbatim.
- `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` — existing 7 PR-citas-04 rules + 5 inherited; PR-tipos-02 extends with 3 additive rules.
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` — abstract base; 5 inherited rules.
- `tests/Unit/DesignSystem/PatientsListAppShellTest.php` — closest precedent for `AppointmentTypesListCleanupTest` (4 additive rules).
- `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (641 lines) — PR-tipos-01 primary file.
- `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` (407 lines) — PR-tipos-02 primary file (line 167 gradient).
- `resources/js/components/ui/Tabs.vue` (line 78 `label` validator) — `<UiTabs>` primitive contract.
- `resources/js/components/ui/EmptyState.vue` (default folder icon, lines 19-30) — `<UiEmptyState>` primitive contract.
- `resources/js/components/ui/LoadingSpinner.vue` — `<LoadingSpinner>` primitive contract.
- `resources/js/composables/useAuditLogs.js:99` — `getAppointmentTypeAuditLogs` composable (frozen).
- `resources/js/composables/useFormatters.js` — `formatCurrency` canonical helper (mandated by CITAS-AT-001 / PAGOS-MNY-002).
- `openspec/specs/premium-design-foundation/spec.md` — archived capability tipos-cita inherits.
- `AGENTS.md` §3 + §5 + §7 — project context, 17-module inventory, conventions.

---

## Key Learnings

1. **[CRITICAL]** The `bg-gradient-to-br` audit empty-state at `AppointmentTypeDetailPage.vue:167` violates global guard rail #10 (no gradients anywhere). PR-tipos-02 MUST remove it as part of the `<UiEmptyState>` adoption; `AppointmentTypesAppShellTest::test_detail_no_gradient_anywhere` pins the rule via a zero-match grep assertion.
2. Pre-polished modules require residual-only PRs rather than first-pass migration; the slice plan shrinks to two small PRs (~200 + ~180 lines) instead of one 400-line PR. Both fit the chained PR delivery strategy comfortably.
3. An unused import (`<UiEmptyState>` at `AppointmentTypesPage.vue:427`) indicates the prior PR added the import but never wired it; PR-tipos-01 consumes it as part of the list empty-state migration.
4. The pacientes detail-page pattern (`<UiTabs>` + `<UiCard variant="glass">` audit row + `<UiEmptyState>` audit empty state + `space-y-4` parent) applies verbatim to tipos-cita with a `name` → `label` data-layer rename in the `tabs` array literal to match the `Tabs.vue:78` validator.
5. Neither tipos-cita page subscribes to Reverb channels, so the rollout carries no realtime regression risk for this module. The 6-module composable contract preservation rules from PACIENTES do not apply.

---

*End of tipos-cita category spec. Next phase: `sdd-design` (if design exists) or `sdd-tasks`.*
