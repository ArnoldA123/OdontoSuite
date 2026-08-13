# Design: PACIENTES Category Delta — `ui-rollout-all-modules-2026-08`

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | PACIENTES (patient list + patient detail + 3 inlined modals + audit tab + export action surface) |
| Date | 2026-08-12 |
| SDD phase | `design` (4 of 6) — category slice |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/pacientes/design`) |
| Delivery strategy | `auto-chain` (inherited from global) |
| Review budget | 400 authored lines / PR (per global proposal §7.15) |
| Strict TDD | `true` (forward to apply/verify) |
| Parent design | `openspec/changes/ui-rollout-all-modules-2026-08/design.md` (PR0 foundation) |
| Parent spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` |
| Parent proposal | `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` |
| Parent explore | `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/explore.md` |
| Parent category spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/pacientes/spec.md` |
| Sibling design (pattern) | `openspec/changes/archive/2026-08-12-ui-citas/design.md` |
| Sibling design (precedent) | `openspec/changes/archive/2026-08-12-ui-pagos/design.md` |
| PACIENTES PRs | `pr-pacientes-01..05` (5 chained sub-PRs — see §3) |

### What this document IS and IS NOT

**IS**: a PACIENTES-only delta on top of the PR0 design. It maps the 2 pacientes routes + 3 inlined modals onto the primitives (`<UiStatusBadge>`, `<UiModal>`, `<UiTabs>`, `<UiCard>`, `<UiButton>`, `<UiInput>`, `<UiSelect>`, `<UiTextarea>`), tokens (canvas, hairline, tabular-nums, focus-ring), motion durations (`var(--motion-duration-fast) var(--motion-easing-ios)`), hairline (`rgba(60, 60, 67, 0.12)`), and PHPUnit invariant infrastructure (`ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`) that PR0 already locked. It enumerates the 5 PACIENTES sub-PRs, their dependency graph, the per-PR changed-line budget, and the per-module test strategy.

**IS NOT**: a re-derivation of the PR0 design. Token names, primitive prop contracts, motion durations, focus-ring composition, hairline, canvas/surface separation, and the `ModuleAppShellTestCase` rule set are ALL inherited from `design.md` §2 (StatusBadge API), §3 (canvasRoutes), §4 (PHPUnit invariants), §5 (cross-cutting rules). Referenced here by path + section; never restated.

### Preflight snapshot (verbatim from PACIENTES proposal §0)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached      # stacked-to-main at sdd-tasks
strict_tdd: true
```

### Standing guard rails (inherited verbatim from global proposal §11.2)

1. `tokens.js` is frozen — no new tokens.
2. systemBackground `#ffffff` pinned; canvas `#F2F2F7` pinned.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings: var(--font-features-tabular-nums)` for numerics.
7. `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` are NEVER edited in any PR.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only.
10. Code in English; conversation in Spanish (Peru).

---

## 1. Architectural intent

PACIENTES is the demographic core of OdontoSuite: every patient record starts in `/patients` (list) and lives on `/patients/:id` (detail). Receptionists search by name/DNI/phone to look up an arriving patient, filter by active/inactive, paginate through hundreds of records, open the New Patient modal for walk-ins, and click into the detail page to manage 5-tab deep-links (Planes → treatment plans, Presupuestos → quotations, Historia Clínica → medical records, Especialidades → specialty records, Historial de auditoría → audit log) and trigger per-patient export (PDF/ZIP). The PR0 design shipped the canvas surface for all 21 routes (2 of them PACIENTES: `/patients`, `/patients/:id`) and the generic `<UiStatusBadge>` primitive. PACIENTES consumes these primitives and applies the PR0 rules mechanically to the 2 largest Vue files in the patient surface. **No new tokens, no new primitives, no backend changes, no `<script>` edits.** All work is template-level class-string replacement against the PR0 mapping table (global design §4) and the category-specific decisions enumerated below.

The 8 PACIENTES-only deltas the global design does NOT enumerate (and that PACIENTES adds):

1. **PatientsPage stat cards**: 4 cards (Total / Activos / Inactivos / Filtrados) use `<UiCard clickable>` (NOT `hover-lift` raw utility). Numeric value uses `font-feature-settings: var(--font-features-tabular-nums)`.
2. **PatientsPage filters**: status filter (all/active/inactive) → `<UiSelect>`; search input → `<UiInput>` with debounce on the existing watcher.
3. **PatientsPage table**: `border-theme` table dividers → hairline; `bg-success-badge / bg-danger-badge` status pills → `<UiStatusBadge>`; `text-accent hover:text-primary-700` link buttons → `<UiButton variant="link">`; `tabular-nums` on DNI + age columns.
4. **PatientsPage mobile cards**: raw `text-green-600 / text-red-600` action buttons → `<UiButton variant="ghost">` with semantic token color.
5. **Inlined New Patient modal**: custom `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop + `bg-theme-surface-elevated rounded-2xl shadow-2xl` panel → `<UiModal>` chrome + `<UiCard>` panel; raw `<input>` / `<select>` / `<textarea>` → `<UiInput>` / `<UiSelect>` / `<UiTextarea>`.
6. **PatientDetailPage 5-tab drawer**: raw `<button>` step strip with `border-accent text-accent` active indicator (line 87) → `<UiTabs>` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions. Cross-category deep-links (`?patient_id=…` to treatment-plans / quotations / medical-records / specialty-records) preserved verbatim.
7. **PatientDetailPage Edit Patient modal**: same `<UiModal>` chrome as list; raw `<select>` for gender + `is_active` → `<UiSelect>`; focus ring → `var(--focus-ring-default)`.
8. **PatientDetailPage Export action**: PDF/ZIP dropdown → `<UiButton>` + `<UiSelect>`; the raw `fetch` + Bearer token + `window.URL.createObjectURL` + `<a download>` anchor click pattern at lines 1217–1225 preserved byte-for-byte (binary stream must not be wrapped in `useApi()` JSON envelope).

The `<style scoped>` blocks at `PatientsPage.vue` line 1315 (`@media (max-width: 640px)` rule) and `PatientDetailPage.vue` line 1556 (`.tab-content { min-height: 400px }` rule) are removed and rewritten to plain utility classes (`sm:hidden`, `min-h-[400px]`). The legacy `<Pagination>` import (lines 742, 752 of `PatientsPage.vue`) stays verbatim — the consolidation rides the global PR3 cluster per global OQ#7 + global proposal §7.5.

---

## 2. PACIENTES surface map

The global design §5 enumerates what every category MUST consume. The table below maps each PACIENTES route and inlined modal to the specific primitive set, tokens, and motion duration that apply.

| Surface (file path) | Primary primitive(s) | Token set | Motion duration | Touch scope |
|---|---|---|---|---|
| `resources/js/modules/patients/PatientsPage.vue` (1249 lines) — list | `<UiCard clickable>` (4 stat cards, REPLACES `hover-lift`), `<UiInput>` (search input + debounce), `<UiSelect>` (status filter), `<UiStatusBadge>` (row status pills), `<UiButton variant="link">` (Ver detail link), `<UiButton variant="ghost">` (mobile action buttons), `<UiEmptyState>` (no-results), `<UiPagination>` (skip — legacy `<Pagination>` import kept verbatim per OQ#7) | canvas, hairline, `tabular-nums` on DNI + age | `var(--motion-duration-fast) var(--motion-easing-ios)` (filter chip press + card press) | large |
| `resources/js/modules/patients/PatientDetailPage.vue` (1480 lines) — detail | `<UiCard>` (header wrapper + per-tab content cards), `<UiStatusBadge>` (header status pill), `<UiTabs v-model="currentStep">` (5-tab drawer), `<UiButton>` (Volver / Edit / Export / per-tab create buttons) | canvas, hairline, focus-ring on errors | `var(--motion-duration-fast) var(--motion-easing-ios)` (tab transition: opacity + translateY ≤8px) | large |
| `PatientsPage.vue` inlined New Patient modal (lines 463–581) | `<UiModal>` (REPLACES `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop), `<UiCard>` (panel, REPLACES `bg-theme-surface-elevated rounded-2xl shadow-2xl`), `<UiInput>` + `<UiSelect>` + `<UiTextarea>` (demographics + medical history + allergies + notes), hairline header divider | canvas, hairline, focus-ring | `var(--motion-duration-fast)` (modal open) | medium |
| `PatientsPage.vue` inlined Edit Patient modal (lines 583–725) | Same primitives as New Patient + `<UiSelect>` for `is_active` toggle | canvas, hairline, focus-ring | `var(--motion-duration-fast)` | medium |
| `PatientDetailPage.vue` inlined Edit Patient modal (lines 706–845) | `<UiModal>` + `<UiCard>` + `<UiInput>` + `<UiSelect>` (gender + `is_active`, REPLACES raw `<select>` at lines 780, 792) | canvas, hairline, focus-ring | `var(--motion-duration-fast)` | medium |
| `PatientDetailPage.vue` Export action surface (lines 1217–1225 + dropdown chrome) | `<UiButton>` (Export trigger), `<UiSelect>` (PDF/ZIP format), `var(--focus-ring-default)` on both | canvas, hairline, focus-ring | `var(--motion-duration-fast)` (dropdown open) | small |
| `PatientDetailPage.vue` audit tab content (Historial de auditoría) | `<UiCard>` (audit row wrapper, REPLACES `border border-theme rounded-lg p-4` raw list items) + `<UiStatusBadge>` (action type indicator) | canvas, hairline | none (static list) | small |

**Negative space (PACIENTES MUST NOT introduce)**:

- No `border-theme` table dividers (use hairline `border-hairline` or `divide-[color:var(--color-hairline)]`).
- No `bg-success-badge` / `bg-danger-badge` legacy alias classes (use `<UiStatusBadge variant="success | error">`).
- No `text-accent hover:text-primary-700` link buttons (use `<UiButton variant="link">`).
- No raw `text-green-600` / `text-red-600` mobile action buttons (use `<UiButton variant="ghost">` with semantic token color).
- No `hover-lift` raw utility on stat cards (use `<UiCard clickable>`).
- No mixed `bg-theme-surface-elevated` + `bg-theme-surface` on the page surface (use `bg-theme-surface-elevated` only on the page chrome; `<UiCard>` for content blocks).
- No `divide-theme` row dividers (use hairline).
- No `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` modal backdrop (use `<UiModal>`).
- No `border-accent text-accent` raw tab indicator (use `<UiTabs>`).
- No `border-l-2 border-theme` callout (use hairline left border).
- No raw `<select>` with `focus:ring-primary-500 focus:border-transparent` (use `<UiSelect>` + `var(--focus-ring-default)`).
- No `<style scoped>` blocks (both pages carry one; both MUST be removed and rewritten as plain utility classes per `DLR-CORE-008` + proposal OQ#9).
- No `.toISOString()` calls in template date rendering (server interprets naive local time).
- No renaming of the legacy `<Pagination>` import (consolidation rides global PR3).
- No refactor of the `window.URL.createObjectURL` + Bearer token + anchor click download pattern (binary stream preservation).
- No PHI scope guard changes (cross-branch `PatientPolicy::view` is a separate change).
- No new tokens, no new primitives.

---

## 3. PACIENTES-specific component decisions

### 3.1 Decision: `PatientsPage` 4 stat cards → `<UiCard clickable>` (no `hover-lift`)

**Choice**: the 4 stat cards (Total / Activos / Inactivos / Filtrados) currently use `hover-lift` raw utility. Replace with `<UiCard clickable>` — the proven primitive handles focus + hover + press via the composed focus-ring and the iOS press mechanism (`var(--motion-duration-normal) var(--motion-easing-ios)` scale + brightness shift). Label uses primary token color; value uses `font-feature-settings: var(--font-features-tabular-nums)` so the counter doesn't jitter when numbers grow.

**Alternatives considered**:

- Keep `hover-lift` raw utility on stat cards — REJECTED. The global design §2 declares `hover-lift` legacy alias; per PR0 `LegacyAliasForbiddenTest::LEGACY_ALIASES` extension (scheduled for patients modules) it will be forbidden.
- Wrap stat cards in `<UiCard>` without `clickable` — REJECTED. The cards filter the patient list when clicked (Total = no filter, Activos = `is_active=true`, etc.); they ARE clickable. The click handler is preserved verbatim.

**Rationale**: `<UiCard clickable>` is the minimal change that satisfies `PAC-LIST-001`. The click handler binding stays in `<script>` (which is NOT touched). The rule is asserted by `PatientsAppShellTest::test_stat_cards_use_ui_card_clickable` (new test method, ships with PR-pacientes-01).

### 3.2 Decision: `PatientsPage` filters → `<UiSelect>` + `<UiInput>` with debounce

**Choice**: status filter (all / active / inactive) is currently a raw `<select class="border-theme focus:ring-primary-500 focus:border-transparent">`. Replace with `<UiSelect v-model="statusFilter" :options="statusOptions" />`. Search input is already a `<UiInput>` (per the vertical-slice adoption pattern); the debounce watcher on the search query stays verbatim — `<script>` is untouched. Filter chip press motion uses `var(--motion-duration-fast) var(--motion-easing-ios)`.

**Alternatives considered**:

- Keep raw `<select>` filter with tokenised chrome — REJECTED. `<UiSelect>` is the canonical primitive; raw `<select>` breaks `DLR-R-009`.
- Replace debounce with a `useDebounce` composable — REJECTED. The debounce is in `<script>` (which is NEVER touched); the apply phase does NOT introduce new composables.

**Rationale**: filter migration is the minimal change that satisfies `PAC-LIST-001`. The debounce contract (existing `setTimeout` in the watcher) is preserved byte-for-byte. The rule is asserted by `PatientsAppShellTest::test_filters_use_ui_primitives` (new test method, ships with PR-pacientes-01).

### 3.3 Decision: `PatientsPage` desktop table + mobile card fallback tokenisation

**Choice**: desktop table tokens migrated to hairline dividers + `<UiStatusBadge>` for the row status pill + `<UiButton variant="link">` for the "Ver" detail link + `font-feature-settings: var(--font-features-tabular-nums)` on the DNI + age columns. Mobile card fallback replaces raw `text-green-600` / `text-red-600` action buttons with `<UiButton variant="ghost">` using the same semantic tokens as the desktop status pill (the action IS the edit/delete affordance; the color follows the semantics — green for edit, red for delete, mapped via `<UiStatusBadge>` variant or `<UiButton variant="ghost">` with explicit `text-systemGreen-700` / `text-systemRed-700` text color). `<EmptyState>` wraps the no-results branch.

**Alternatives considered**:

- Keep `bg-success-badge / bg-danger-badge` legacy alias on row pills — REJECTED. The rollout explicitly bans these legacy aliases via `LegacyAliasForbiddenTest::LEGACY_ALIASES` (extended per PR).
- Wrap row in `<UiCard>` — REJECTED. The table is a `<table>` for column alignment; `<UiCard>` would break the desktop layout. The hairline divider + status pill migration is the minimal change.
- Use `<UiButton variant="primary">` for mobile actions — REJECTED. Mobile action buttons are secondary (edit/delete); `variant="ghost"` is the iOS affordance for secondary inline actions.

**Rationale**: table + card tokenisation is the minimal change that satisfies `PAC-LIST-001`. The action semantics (edit / delete) and the click handlers stay verbatim in `<script>`. The rule is asserted by `PatientsAppShellTest::test_table_uses_ui_primitives` + `test_mobile_cards_use_ui_button_ghost` (new test methods, ship with PR-pacientes-01).

### 3.4 Decision: `PatientsPage` inlined New Patient + Edit Patient modals → `<UiModal>`

**Choice**: both modals currently use a custom `<div class="fixed inset-0 bg-black bg-opacity-50 … z-50">` backdrop, `bg-theme-surface-elevated rounded-2xl shadow-2xl` panel, `border-b border-theme` header divider, raw `<input>` / `<select>` / `<textarea>` form fields with `focus:ring-primary-500 focus:border-transparent`. Replace with `<UiModal>` chrome + `<UiCard>` panel + hairline header divider + `<UiInput>` / `<UiSelect>` / `<UiTextarea>` form fields + `var(--focus-ring-default)` focus ring. The `useApi` POST/PUT signatures and the 422 duplicate-email/phone error envelope rendering stay verbatim (the form stays open + server error surfaces via `useToast`).

**Alternatives considered**:

- Wrap existing `<div>` modal in `<UiCard>` (keep custom backdrop) — REJECTED. `<UiModal>` is the canonical primitive; mixing backdrop + UiCard breaks the canvas/surface separation rule (`DLR-R-001`).
- Map duplicate-email/phone at the service level — REJECTED. Service is out of scope (proposal §3.1); the mapping is template-level only.

**Rationale**: modal chrome migration is the minimal change that satisfies `PAC-MOD-001`. The 422 catch block (lines 1152–1159 of `PatientDetailPage.vue` analog; same pattern on `PatientsPage.vue` modal catch block) stays verbatim. The rule is asserted by `PatientModalChromeTest::test_list_modals_use_ui_modal` (new test method, ships with PR-pacientes-02).

### 3.5 Decision: `PatientDetailPage` 5-tab drawer → `<UiTabs>` + cross-category deep-links preserved

**Choice**: the raw `<button>` step strip with `border-accent text-accent` active indicator (line 87 of `PatientDetailPage.vue`) is replaced by `<UiTabs v-model="currentStep">`. Tab transition uses `var(--motion-duration-fast) var(--motion-easing-ios)` with minimal motion (single opacity fade + translateY ≤8px — the archive-report lesson at lines 47–57: over-animated transitions on tab navigation feel sluggish). The 5 tabs map onto `<UiTabs>` items: Datos / Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría (6 entries; "Datos" is the default active tab). The cross-category deep-link `router.push(...)` calls (`/treatment-plans?patient_id=…`, `/quotations?patient_id=…`, `/medical-records?patient_id=…`, `/specialty-records?patient_id=…`) stay byte-for-byte.

**Alternatives considered**:

- Keep raw `<button>` step strip with transitions only — REJECTED. PR0 design §6 declares `<UiTabs>` the canonical primitive; carrying raw tab strips breaks the canvas/surface separation rule.
- Full slide-in transition (translateX per tab) — REJECTED. Per the archive-report lesson, over-animated transitions on tab navigation feel sluggish for clinicians; minimal motion preserves the snappy iOS feel.
- Add `tab-button-refactor` that consolidates to a single nav-pill primitive — DEFERRED. The 5-tab drawer is per-page; a nav-pill consolidation rides a later slice (global OQ#7 analogue).

**Rationale**: `<UiTabs>` adoption is the minimal change that satisfies `PAC-DET-001`. The `currentStep` ref + the back/forward navigation contract stay unchanged in `<script>`. The rule is asserted by `PatientDetailAppShellTest::test_tabs_use_ui_tabs` + `test_cross_category_deep_links_preserved` (new test methods, ship with PR-pacientes-03).

### 3.6 Decision: `PatientDetailPage` Edit Patient modal → `<UiModal>` + `<UiSelect>` for gender + `is_active`

**Choice**: the inlined Edit Patient modal at `PatientDetailPage.vue` lines 706–845 uses raw `<select>` for gender (line 780) and `is_active` (line 792). Replace with `<UiModal>` chrome (same as `PatientsPage.vue` modals per §3.4) + `<UiSelect>` + `<UiInput>` + hairline + `var(--focus-ring-default)`. The `useApi` `PUT /api/patients/{id}` call signature stays verbatim; the 422 error envelope from `Rule::unique(...)->ignore($patient->id)` stays verbatim (the email/phone unique constraint ignores the current patient).

**Alternatives considered**:

- Keep raw `<select>` with tokenised chrome — REJECTED. `<UiSelect>` is the canonical primitive; raw `<select>` breaks `DLR-R-009`.
- Refactor the `useApi` PUT to use `axios` directly — REJECTED. `useApi()` wrapper only (guard rail #8); no axios direct.

**Rationale**: detail edit modal chrome migration is the minimal change that satisfies `PAC-EDIT-001`. The PUT call signature stays verbatim. The rule is asserted by `PatientModalChromeTest::test_detail_edit_modal_uses_ui_primitives` (new test method, ships with PR-pacientes-04).

### 3.7 Decision: `PatientDetailPage` Export action surface → `<UiButton>` + `<UiSelect>` (binary download preserved)

**Choice**: the Export action (PDF / ZIP dropdown) currently uses raw `<button>` + raw `<select>` with `focus:ring-primary-500 focus:border-transparent`. Replace with `<UiButton>` (Export trigger) + `<UiSelect>` (PDF/ZIP format). The raw `fetch` + Bearer token + `window.URL.createObjectURL` + `<a download>` anchor click pattern at lines 1217–1225 stays byte-for-byte. The JSON wrapper from `useApi()` would corrupt the binary stream — `useApi()` cannot replace this pattern.

**Alternatives considered**:

- Wrap the export call in `useApi()` — REJECTED. The JSON envelope corrupts the binary stream; the raw `fetch` + Bearer token pattern is the only safe path.
- Stream the binary via a service worker — DEFERRED. Service worker streaming is a future architectural slice; the current pattern is the proven contract.
- Add a server-side signed URL for the download — DEFERRED. Out of scope; the Bearer token + `temporarySignedRoute` (used by `NotifyPatientFileExported` listener for the email link) is a separate path, not the staff-triggered download.

**Rationale**: export action chrome migration is the minimal change that satisfies `PAC-EXP-001`. The download pattern stays verbatim. The rule is asserted by `PatientDetailAppShellTest::test_export_action_uses_ui_button` + `test_export_download_pattern_preserved` (new test methods, ship with PR-pacientes-04). `ApiAndSeedersPolishTest` API-035 + API-057 stay green (pins `application/pdf` / `application-`zip` Content-Type).

### 3.8 Decision: `PatientDetailPage` audit tab content tokenisation (flattened list preserved)

**Choice**: the audit tab (Historial de auditoría) renders a flattened list of `auditLogs` (loaded via `useAuditLogs.getPatientAuditLogs(patientId)`). Currently the row markup uses `border border-theme rounded-lg p-4` raw list items. Replace with `<UiCard>` wrappers + `<UiStatusBadge variant="info">` for the action-type indicator (created / updated / deleted / file_exported). The list content (timestamp + user + action + diff metadata) stays byte-for-byte.

**Alternatives considered**:

- Invent a new `<UiAuditRow>` primitive — REJECTED. The global spec forbids new primitives except `<UiStatusBadge>`. `<UiCard>` wrappers + `<UiStatusBadge>` indicator is the minimal adoption.
- Drop the audit tab content and replace with a "coming soon" placeholder — REJECTED. The audit log is a load-bearing operational surface (compliance + clinician debugging); the content stays.

**Rationale**: audit tab tokenisation is the minimal change that satisfies `PAC-DET-001` (cross-cutting token discipline on the detail page). The list content stays verbatim. The rule is asserted by `PatientDetailAppShellTest::test_audit_tab_uses_ui_card` (new test method, ships with PR-pacientes-03 alongside the tab strip migration).

### 3.9 Decision: `<style scoped>` block removal from both pages

**Choice**: `PatientsPage.vue` line 1315 carries a `<style scoped>` block with a single `@media (max-width: 640px)` rule. `PatientDetailPage.vue` line 1556 carries a `<style scoped>` block with a single `.tab-content { min-height: 400px }` rule. Both are removed and their contents rewritten to plain utility classes (`sm:hidden`, `min-h-[400px]`). The `ModuleAppShellTestCase::test_no_style_scoped` rule asserts zero `<style scoped>` blocks remain per file.

**Alternatives considered**:

- Keep the `<style scoped>` blocks (grandfather them) — REJECTED. PR0 proposal OQ#9 establishes "no grandfather clause for `<style scoped>`" — every patients PR removes the blocks.
- Migrate to `:deep()` scoped selectors — REJECTED. The contents are simple responsive / layout rules; plain utility classes suffice.

**Rationale**: scoped-style removal is the minimal change that satisfies `DLR-CORE-008` + `DLR-R-021`. The visual behavior is preserved via utility classes. The rule is asserted by `ModuleAppShellTestCase::test_no_style_scoped` (the base class rule, green per module page).

### 3.10 Decision: Cross-category deep-link preservation (negative space)

**Choice**: zero edits to the 4 `router.push(...)` calls on `PatientDetailPage.vue` that deep-link to other modules with `?patient_id=…`. The `useEcho` channel subscriptions on `patients` + the 4 cross-category channels (`treatment-plans` / `quotations` / `medical-records` / `specialty-records`) stay verbatim. The `PatientResource` API envelope (incl. additive `age` integer key) stays verbatim. The `usePermissions.can.*` flags stay verbatim. The `useAuditLogs.getPatientAuditLogs(...)` call stays verbatim. The `useConfirm` delete-confirmation flow stays verbatim. The `useToast` success/error toasts stay verbatim.

**Alternatives considered**:

- Refactor the deep-link to use `<UiLink>` primitive — DEFERRED. There is no `<UiLink>` primitive (PR0 didn't ship one); the deep-link is a `<UiButton>` on the per-tab create buttons, which stays as-is. The `router.push(...)` is the contract.
- Drop a deep-link because the destination module isn't polished — REJECTED. The deep-link contract is between modules; visual polish of one module must not break navigation to another module's surface.

**Rationale**: this is a NEGATIVE-space decision (don't touch contracts). The rule is enforced by `PatientDetailAppShellTest::test_cross_category_deep_links_preserved` (new test method, ships with PR-pacientes-03) + `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` + `ComposablesStandardizationTest` stay green at every PR-pacientes-NN boundary.

---

## 4. PACIENTES PR slicing

The PACIENTES rollout splits into 5 chained sub-PRs. Each fits inside the 400-line review budget (global proposal §7.15). Each is independently buildable, testable, and revertible per `chained-pr` skill rules.

| PR | Name | Scope | Files touched | Estimated lines | Depends on |
|---|---|---|---|---|---|
| PR-pacientes-01 | `pr-pacientes-01-patients-list` | `PatientsPage.vue` list section only (NOT the 2 inlined modals): search bar + status filter + 4 stat cards + desktop table + mobile card fallback + pagination + `<style scoped>` removal (line 1315). Legacy `<Pagination>` import kept verbatim. New `PatientTableNumsTest`; extend `LegacyAliasForbiddenTest` with `bg-success-badge` / `bg-danger-badge` / `hover-lift` / `divide-theme` aliases. | 1 page (list section) + 1 new test + 1 test extension | ~390 (at budget; split into 01a desktop table + 01b mobile cards + stat cards if reviewer flags) | PR0 (landed) |
| PR-pacientes-02 | `pr-pacientes-02-patients-modals` | `PatientsPage.vue` inlined New Patient modal (lines 463–581) + Edit Patient modal (lines 583–725). Replace custom backdrop + raw `<input>` / `<select>` / `<textarea>` with `<UiModal>` + `<UiInput>` / `<UiSelect>` / `<UiTextarea>`. 422 catch block stays verbatim. New `PatientModalChromeTest`. | 1 page (modal sections) + 1 new test | ~280 | PR-pacientes-01 |
| PR-pacientes-03 | `pr-pacientes-03-patient-detail` | `PatientDetailPage.vue` header + 5-tab drawer (`<UiTabs>`) + 4 cross-category deep-links (preserved verbatim) + audit tab content (`<UiCard>` + `<UiStatusBadge>`) + `<style scoped>` removal (line 1556). `useEcho` `patients` + cross-category channel subscriptions preserved verbatim. New `PatientStatusBadgeTest`; extend `PatientDetailAppShellTest` with tab + deep-link + audit assertions. | 1 page + 1 new test + 1 test extension | ~390 (at budget; split into 03a header + 5-tab drawer + 03b audit tab + per-tab content if reviewer flags) | PR-pacientes-01 + PR-pacientes-02 |
| PR-pacientes-04 | `pr-pacientes-04-patient-edit-modal-and-export` | `PatientDetailPage.vue` inlined Edit Patient modal (lines 706–845) + Export action surface. `<UiModal>` chrome replaces hand-built backdrop; raw `<select>` gender + `is_active` → `<UiSelect>`; PDF/ZIP dropdown → `<UiButton>` + `<UiSelect>`. `window.URL.createObjectURL` + Bearer token download pattern preserved byte-for-byte. Extend `PatientDetailAppShellTest` + `PatientModalChromeTest` + `LegacyAliasForbiddenTest`. | 1 page (modal + Export sections) + 2 test extensions | ~260 | PR-pacientes-03 |
| PR-pacientes-05 | `pr-pacientes-05-cross-cutting-tests` | New `tests/Unit/DesignSystem/PatientsAppShellTest.php` (extends `ModuleAppShellTestCase`; covers `PatientsPage` + 2 inlined modals). New `tests/Unit/DesignSystem/PatientDetailAppShellTest.php` (covers `PatientDetailPage` + inlined Edit modal + 4 cross-category deep-links). Extend `LegacyAliasForbiddenTest::polishedFiles()` to append `PatientsPage.vue` + `PatientDetailPage.vue`. New `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/a11y-followup.md` (allergy / medical-history alert callout as future work, out of scope for visual polish). | 2 new test files + 1 test extension + 1 a11y doc | ~200 | PR-pacientes-01..04 |

### 4.1 Ordering rationale

- **Highest-traffic demographic surface first (PR-pacientes-01)**: `PatientsPage.vue` is the receptionist's primary surface (1249 lines, 44.5 KB). Doing it first establishes the `<UiCard clickable>` / `<UiStatusBadge>` / `<UiButton variant="ghost">` rhythm for every subsequent PR. The DNI + age `tabular-nums` adoption is the visible win — ID column stops jittering.
- **List modals second (PR-pacientes-02)**: the 2 inlined modals on `PatientsPage.vue` (New Patient + Edit Patient) share the modal chrome pattern. Doing them second means the `<UiModal>` + `<UiInput>` rhythm is already proven on the list surface before the detail page adopts it.
- **Detail page third (PR-pacientes-03)**: `PatientDetailPage.vue` (1480 lines, 53.4 KB) is the largest single Vue file in PACIENTES. Doing it third means the 5-tab drawer chrome pattern is established by the time the detail edit modal lands. The 4 cross-category deep-links are preserved verbatim; the `useEcho` subscriptions are byte-for-byte.
- **Detail edit + export fourth (PR-pacientes-04)**: the Edit Patient modal on `PatientDetailPage.vue` and the Export action surface share the `<UiModal>` + `<UiButton>` pattern. The binary download pattern is preserved verbatim.
- **Cross-cutting tests + a11y flag last (PR-pacientes-05)**: tests + a11y doc ship after all UI is in place; the per-module `<Module>AppShellTest` files cover all prior PRs in one consolidated structure.

### 4.2 Alternatives considered

- **Reverse order (PR-pacientes-05 first)**: rejected. The cross-cutting tests assert per-module rule (token reference exists, alias absent); landing them first means the test fails RED before any UI is in place → test is meaningless.
- **Bundle list + detail into one PR**: rejected. Combined diff would exceed 1100 lines (list ~390 + detail ~390 + modals ~280 + export ~260) → exceeds the 400-line budget and triggers the `chained-pr` split rule.
- **Land detail page first (PR-pacientes-03 first)**: rejected. The detail page is the largest Vue file; landing it first means the highest-traffic list surface lands later, which raises the regression blast radius if a primitive swap breaks.
- **Skip PR-pacientes-05**: rejected. The cross-cutting tests are the durable regression guard for the rollout; without them, the list + detail + modal rules are asserted only by per-PR ephemeral tests.
- **Land the export action in PR-pacientes-02 (alongside list modals)**: rejected. The Export action lives on `PatientDetailPage.vue` (not `PatientsPage.vue`); bundling it with the list modals creates a cross-file dependency that violates `chained-pr` skill rules.

### 4.3 Budget breakdown per PR (additions + deletions counted for authored risk)

PR-pacientes-01: ~390 lines (list rewrite ~310 + new test ~30 + test extension ~50).
PR-pacientes-02: ~280 lines (modal rewrite ~240 + new test ~40).
PR-pacientes-03: ~390 lines (detail header + tabs + deep-links + audit ~340 + new test ~20 + test extension ~30).
PR-pacientes-04: ~260 lines (detail edit modal + Export ~210 + 2 test extensions ~50).
PR-pacientes-05: ~200 lines (2 new test files ~150 + test extension ~20 + a11y doc ~30).

Total authored lines across PR-pacientes-01..05: ~1,520 lines. No single PR exceeds 400 lines. Generated goldens (test snapshots, generated CSS) are excluded from the risk count per `sdd-phase-common.md` §E.

### 4.4 PR split rule (forwarded from proposal §8 risk #1)

PR-pacientes-01 and PR-pacientes-03 are both right at the 400-line budget. If either exceeds 400 lines on review:

- **PR-pacientes-01a**: desktop table + stat cards + filters + `<style scoped>` removal (~210 lines).
- **PR-pacientes-01b**: mobile card fallback + pagination wrapper + `PatientTableNumsTest` (~180 lines).
- **PR-pacientes-03a**: header + 5-tab drawer + cross-category deep-links + `<style scoped>` removal (~220 lines).
- **PR-pacientes-03b**: audit tab + per-tab content cards + `PatientStatusBadgeTest` (~170 lines).

The `chained-pr` skill rule applies: split BEFORE the review starts (not after).

---

## 5. Apple-language faithfulness checklist

The global spec rows (`DLR-*`) apply to PACIENTES unmodified. The PACIENTES spec rows (`PAC-*`) add category-specific edges. The table below confirms one-line compliance per applicable row.

| Spec row | Compliance (one-line confirmation) |
|---|---|
| `DLR-CORE-001` (canvas surface) | Both PACIENTES routes (`/patients`, `/patients/:id`) are in `AppLayout.canvasRoutes` (PR0 landed); no further work needed. |
| `DLR-CORE-008` (no `<style scoped>`) | Both PACIENTES PRs remove existing `<style scoped>` blocks (PatientsPage line 1315, PatientDetailPage line 1556) and add none; `ModuleAppShellTestCase::test_no_style_scoped` green per module. |
| `DLR-R-001` (canvas background) | `PatientsPage.vue` + `PatientDetailPage.vue` already reference `bg-canvas` via `<AppLayout>` (PR0 effect); no template change needed. |
| `DLR-R-002` (hairline borders) | `border-theme` / `divide-theme` literals replaced by `border-hairline` / `divide-[color:var(--color-hairline)]` on `PatientsPage` table + `PatientDetailPage` change-diff callout (line 669). |
| `DLR-R-004` (composed focus ring) | `focus:ring-primary-500 focus:border-transparent` literals replaced by `var(--focus-ring-default)` via `<UiInput>` / `<UiSelect>` primitives; `LegacyAliasForbiddenTest` extended per PR. |
| `DLR-R-007` (`tabular-nums`) | Applied on `PatientsPage` DNI + age columns + `PatientDetailPage` document_number / age cells; uses `font-feature-settings: var(--font-features-tabular-nums)`. |
| `DLR-R-009` (legacy alias ban) | `LegacyAliasForbiddenTest::LEGACY_ALIASES` extended per PR (each PACIENTES PR adds the aliases it migrates away from: `border-theme`, `bg-success-badge`, `bg-danger-badge`, `text-accent hover:text-primary-700`, `hover-lift`, `divide-theme`, `bg-black bg-opacity-50`, `text-green-600`, `text-red-600`). |
| `DLR-R-013` (no new dependencies) | PACIENTES consumes PR0 primitives only; no new npm or composer deps. |
| `DLR-R-017` (strict TDD) | Every UI replacement comes with a test that proves the new behaviour; RED-GREEN discipline per PR. |
| `DLR-R-019` (CI green) | `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm) green at every PR-pacientes-NN boundary. |
| `DLR-R-021` (no `<style scoped>`) | See `DLR-CORE-008` above. |
| `DLR-MOD-003` (Pacientes) | Both patients pages + 3 inlined modals tokenised as one cluster; `PatientResource` API envelope (incl. additive `age` integer) stays verbatim; `useEcho` channel subscriptions stay verbatim; soft-delete + appointments-conflict 422 stays verbatim; cross-category deep-link surface (`?patient_id=…`) is preserved; `<style scoped>` blocks in both pages are rewritten to plain utility classes. |
| `PAC-LIST-001` (list polish) | All 9 alias migrations on `PatientsPage` list section complete; `PatientTableNumsTest` + `PatientsAppShellTest` green. |
| `PAC-MOD-001` (modal chrome) | 2 inlined modals on `PatientsPage` use `<UiModal>` chrome; `PatientModalChromeTest::test_list_modals_use_ui_modal` green. |
| `PAC-DET-001` (5-tab drawer + deep-links) | `<UiTabs>` adopted on `PatientDetailPage`; 4 cross-category `router.push(...)` calls preserved byte-for-byte; `PatientDetailAppShellTest::test_tabs_use_ui_tabs` + `test_cross_category_deep_links_preserved` green. |
| `PAC-EDIT-001` (detail edit modal) | Detail Edit Patient modal uses `<UiModal>` + `<UiSelect>`; `useApi` PUT signature preserved; `PatientModalChromeTest::test_detail_edit_modal_uses_ui_primitives` green. |
| `PAC-EXP-001` (export action) | PDF/ZIP dropdown uses `<UiButton>` + `<UiSelect>`; `window.URL.createObjectURL` + Bearer token download pattern preserved byte-for-byte; `PatientDetailAppShellTest::test_export_action_uses_ui_button` + `test_export_download_pattern_preserved` green; `ApiAndSeedersPolishTest` API-035 + API-057 stay green. |
| `PAC-RT-001` (Echo channel isolation) | `useEcho` `patients` + 4 cross-category channel subscriptions preserved verbatim; `<script>` blocks of both pages NEVER touched. |
| `PAC-PHI-001` (API envelope preservation) | `PatientResource` envelope unchanged (additive `age` integer key stays); `PatientResourceAgeTest` + `PatientControllerAgeTest` + `PatientControllerResourceWireUpTest` green. |
| `PAC-DEEP-001` (deep-link preservation) | 4 `router.push(...)` calls byte-for-byte; `PatientDetailAppShellTest::test_cross_category_deep_links_preserved` green. |
| `PAC-REV-001` (per-PR budget) | Each `pr-pacientes-NN` ≤ 400 lines; split rule applied (01a/01b, 03a/03b) if reviewer flags. |
| `PAC-CON-001` (composable contracts) | `useEcho` / `usePermissions` / `useToast` / `useConfirm` / `useApi` / `useAuditLogs` contracts unchanged; `<script>` blocks of both pages byte-for-byte unchanged. |

---

## 6. Test strategy

The PACIENTES rollout extends the PR0 test infrastructure (global design §4) with per-module structure tests + cross-cutting alias assertions + status badge / tabular-nums / modal chrome rule-asserting tests. Strict TDD: every UI replacement comes with a test that proves the new behaviour.

### 6.1 Existing tests (MUST stay green at every PR-pacientes-NN boundary)

| Test file | What it asserts | Witness role |
|---|---|---|
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | `canvasRoutes` array literal contains all 21 routes (including 2 PACIENTES: `/patients`, `/patients/:id`) | regression guard for canvas surface |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | abstract base class with 5 rules (canvas token, hairline, focus-ring, no `<style scoped>`, no legacy focus-ring alias) | per-module rule source |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | forbidden legacy alias pin (extended per PR to add pacientes-specific aliases) | regression guard for alias ban |
| `tests/Feature/Api/PatientControllerAgeTest.php` | `index / show / store / update` return `data.age` as integer (or null) | regression guard for additive `age` key |
| `tests/Unit/Resources/PatientResourceAgeTest.php` | 7 cases on `PatientResource::toArray()` (additive `age` key MUST NOT be widened/narrowed) | regression guard for resource envelope |
| `tests/Unit/Controllers/PatientControllerResourceWireUpTest.php` | every public CRUD method references `PatientResource` | regression guard for resource wire-up |
| `tests/Unit/Polish/ApiAndSeedersPolishTest.php` | API-035 + API-057: export Content-Type `application/pdf` / `application/zip` | regression guard for binary export |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | 6 composables (`useEcho` / `usePermissions` / `useToast` / `useConfirm` / `useApi` / `useAuditLogs`) contract preserved | regression guard for composable surface |

### 6.2 New tests (per PR)

| PR | Test file | What it asserts | Extends |
|---|---|---|---|
| PR-pacientes-01 | new `tests/Unit/DesignSystem/PatientTableNumsTest.php` | `PatientsPage` DNI column + age column carry `tabular-nums` (`font-feature-settings: var(--font-features-tabular-nums)`); `PatientDetailPage` document_number / age cells carry `tabular-nums` | source-grep, rule-vs-literal |
| PR-pacientes-01 | (extension of `LegacyAliasForbiddenTest.php`) | `LEGACY_ALIASES` extended with `bg-success-badge`, `bg-danger-badge`, `hover-lift`, `divide-theme`, `text-green-600`, `text-red-600`; `polishedFiles()` extended with `PatientsPage.vue` | base + extension |
| PR-pacientes-02 | new `tests/Unit/DesignSystem/PatientModalChromeTest.php` | `PatientsPage.vue` inlined New Patient + Edit Patient modals use `<UiModal>` (NOT `bg-black bg-opacity-50`); raw `<input>` / `<select>` / `<textarea>` absent | `ModuleAppShellTestCase` + new `assertListModalsUseUiModal()` |
| PR-pacientes-03 | new `tests/Unit/DesignSystem/PatientStatusBadgeTest.php` | `PatientDetailPage` header status pill uses `<UiBadge variant="success | error">` (NOT legacy `bg-success-badge` / `bg-danger-badge`); audit tab action-type indicator uses `<UiStatusBadge variant="info">` | `ModuleAppShellTestCase` + new `assertStatusBadgeVariantPresent()` |
| PR-pacientes-03 | (extension of `PatientDetailAppShellTest.php`) | `<UiTabs>` reference present on 5-tab drawer; raw `border-accent text-accent` active indicator absent; inline `@click="currentStep = step.id"`-style handler absent; 4 cross-category `router.push(...)` calls remain byte-for-byte; audit tab uses `<UiCard>` wrapper | base + new `assertTabsUseUiTabs()` + `assertCrossCategoryDeepLinksPreserved()` + `assertAuditTabUsesUiCard()` |
| PR-pacientes-04 | (extension of `PatientDetailAppShellTest.php`) | PDF/ZIP dropdown uses `<UiButton>` + `<UiSelect>`; `window.URL.createObjectURL` + Bearer token + anchor click pattern preserved byte-for-byte; raw `<select>` with `focus:ring-primary-500 focus:border-transparent` absent | base + new `assertExportActionUsesUiButton()` + `assertExportDownloadPatternPreserved()` |
| PR-pacientes-04 | (extension of `PatientModalChromeTest.php`) | Detail Edit Patient modal uses `<UiModal>` + `<UiSelect>` + `<UiInput>`; raw `<select>` absent; `useApi` PUT signature preserved | new `assertDetailEditModalUsesUiPrimitives()` |
| PR-pacientes-04 | (extension of `LegacyAliasForbiddenTest.php`) | `LEGACY_ALIASES` extended with `bg-black bg-opacity-50` on detail modal sections; `polishedFiles()` extended with `PatientDetailPage.vue` | base + extension |
| PR-pacientes-05 | new `tests/Unit/DesignSystem/PatientsAppShellTest.php` | Extends `ModuleAppShellTestCase`; covers `PatientsPage` + 2 inlined modals; asserts all module rules (canvas token, hairline, focus-ring, no `<style scoped>`, no legacy aliases) | `ModuleAppShellTestCase` |
| PR-pacientes-05 | new `tests/Unit/DesignSystem/PatientDetailAppShellTest.php` | Extends `ModuleAppShellTestCase`; covers `PatientDetailPage` + inlined Edit modal + 4 cross-category deep-links + Export action; asserts all module rules | `ModuleAppShellTestCase` |

### 6.3 Per-PR RED-GREEN discipline

Per the archive-report lesson (global design §9.3 line 1: "test pins rule, not example"), every test method asserts a RULE, not a literal string:

- `PatientTableNumsTest::test_dni_column_uses_tabular_nums` — regex `font-feature-settings:\s*var\(--font-features-tabular-nums\)` on the DNI column template; does NOT pin the exact class list.
- `PatientStatusBadgeTest::test_status_pill_uses_ui_badge_variant` — checks `<UiBadge>` OR `<UiStatusBadge>` reference is present and `bg-success-badge` / `bg-danger-badge` is absent; does NOT pin the exact variant mapping.
- `PatientModalChromeTest::assertListModalsUseUiModal()` — checks `<UiModal>` wrapper present and `bg-black bg-opacity-50` absent in the modal sections; does NOT pin the exact wrapper markup.
- `PatientDetailAppShellTest::assertTabsUseUiTabs()` — checks `<UiTabs>` reference present and `border-accent text-accent` active indicator absent; does NOT pin the exact tab markup.
- `PatientDetailAppShellTest::assertCrossCategoryDeepLinksPreserved()` — regex matches the 4 `router.push('/<target>?patient_id=…')` patterns; does NOT pin the exact variable name for the patient ID interpolation.

---

## 7. Visual verification (per PR)

Every PR-pacientes-NN ships with a `playwright-cli` screenshot of the touched pages for visual regression. The screenshots are saved to `.playwright-cli/screenshots-rollout/` and reviewed against the global design acceptance criteria.

| PR | Screenshots required | Credentials (per `CREDENTIALS.md`) |
|---|---|---|
| PR-pacientes-01 | `patients-list-1440x900.png` (full list with stats + filters + table + pagination); `patients-list-390x844.png` (mobile card fallback; receptionist mobile path); `patients-list-filters-open-1440x900.png` (status filter dropdown open) | `recep@test.com` |
| PR-pacientes-02 | `patients-new-patient-modal-1440x900.png` (open); `patients-edit-patient-modal-1440x900.png` (open with sample data); `patients-modal-422-error-1440x900.png` (duplicate email error via `useToast`) | `recep@test.com` |
| PR-pacientes-03 | `patient-detail-1440x900.png` (header + 5-tab drawer + Datos tab); `patient-detail-tabs-active-1440x900.png` (each of the 5 tabs active: Datos / Planes / Presupuestos / Historia Clínica / Especialidades / Historial de auditoría); `patient-detail-deep-link-1440x900.png` (clicking "Crear plan" on Planes tab navigates to `/treatment-plans?patient_id=…`) | `recep@test.com` |
| PR-pacientes-04 | `patient-detail-edit-modal-1440x900.png` (Edit Patient modal open); `patient-detail-export-dropdown-1440x900.png` (PDF/ZIP dropdown open); `patient-detail-export-pdf-download-1440x900.png` (PDF download triggered via `window.URL.createObjectURL`) | `recep@test.com`; `admin@test.com` for delete flows |
| PR-pacientes-05 | (regression snapshots — re-run PR-pacientes-01..04 screenshots to confirm no visual drift from the consolidated tests) | same as the source PR |

### 7.1 Verification discipline

- Snapshots are saved as PNG (not JPEG) to preserve text sharpness for status badge ramps + tabular-nums alignment.
- Snapshots are reviewed for: legacy alias absence (`border-theme`, `bg-success-badge`, `bg-danger-badge`, `text-accent hover:text-primary-700`, `bg-black bg-opacity-50`, `hover-lift`, `divide-theme`, `text-green-600`, `text-red-600`, `border-accent text-accent`, raw `<select>` with `focus:ring-primary-500 focus:border-transparent`), canvas surface presence (`bg-canvas` visible), focus-ring composition (when tab-cycled), `tabular-nums` on DNI + age columns, `<UiStatusBadge>` ramps (no `bg-system*-100` heavy borders), 5-tab drawer `<UiTabs>` motion (single opacity + translateY ≤8px).
- The visual sweep is documented verification, not a CI gate (per global proposal §4.3).
- Manual smoke test for realtime: two browser tabs on `/patients/:id`; update the patient in tab A; verify tab B receives the `patient.updated` event within 1 second (per `PAC-RT-001-1`).
- Manual smoke test for deep-links: click each of the 4 per-tab create buttons; verify the URL contains `?patient_id=<id>` and the destination module loads (per `PAC-DEEP-001-1`).

---

## 8. Risks & mitigations

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | `PatientDetailPage.vue` (1480 lines, 53.4 KB) is the largest single Vue file in PACIENTES; the chained PR-pacientes-03 (~390 lines) is right at the 400-line review budget. | Medium | Apply phase: PR-pacientes-03 is split into PR-pacientes-03a (header + 5-tab drawer + cross-category deep-links) + PR-pacientes-03b (audit tab + per-tab content polish) IF the diff exceeds 400 lines. `chained-pr` skill rule applied: split BEFORE the review starts. |
| 2 | `useEcho` channel isolation: `patients` + cross-category `treatment-plans` / `quotations` / `medical-records` / `specialty-records` channel subscriptions must keep firing. Any `<script>` edit that accidentally removes `.listen(...)` or `echo.leave(...)` silently breaks realtime across the 5-tab drawer. | Medium | Apply phase scope rule: `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` are NEVER touched in any PR (per `PAC-RT-001` + `PAC-CON-001`). Visual smoke test: open `/patients/:id` in two browser tabs, update the patient in tab A, verify tab B receives the `patient.updated` event within 1 second. |
| 3 | `PatientResource` API envelope must stay verbatim — additive `age` integer key is pinned by `PatientResourceAgeTest` + `PatientControllerAgeTest`. A UI change that accidentally narrows or widens the envelope (e.g., removes `email`, `phone`, `address`, `medical_history`, `allergies`, `notes`) breaks every clinical consumer. | Low | Apply phase: UI changes do NOT touch `PatientResource.php` or `PatientController.php`. The `PatientControllerResourceWireUpTest` source-contract test asserts every public CRUD method references `PatientResource`; the resource wire-up is verified at every PR-pacientes-NN boundary. |
| 4 | Pagination primitive duplication: `PatientsPage.vue` still imports the legacy `<Pagination>` (lines 742, 752). The global PR3 cluster consolidates this duplicate; the pacientes PR MUST NOT silently rename the import (would break the dependency graph). | Low | Apply phase: keep `<Pagination>` as-is in this PR. The consolidation is owned by global PR3 (Recepción procedimientos per the global §7.5). Per-PR grep for `<UiPagination>` import returns zero matches; if found, rewrite only after explicit confirmation that global PR3 has landed. |
| 5 | The per-patient PDF export uses a raw `fetch` + Bearer token + `window.URL.createObjectURL` + anchor click pattern at `PatientDetailPage.vue` lines 1217–1225. The JSON wrapper would corrupt the binary stream. A UI refactor that accidentally wraps the export call in `useApi()` breaks the download. | Low | Apply phase scope rule: the export action surface stays verbatim (per `PAC-EXP-001`). `PatientDetailAppShellTest::assertExportDownloadPatternPreserved()` asserts the `window.URL.createObjectURL` + anchor click pattern is preserved byte-for-byte. `ApiAndSeedersPolishTest` API-035 + API-057 stays green (pins `application/pdf` / `application/zip` Content-Type). |
| 6 | Cross-category deep-links (`/treatment-plans?patient_id=…`, `/quotations?patient_id=…`, `/medical-records?patient_id=…`, `/specialty-records?patient_id=…`) MUST be preserved verbatim. A UI refactor that drops the `?patient_id=…` query param breaks the deep-link contract with the 4 other modules. | Low | Apply phase: deep-link navigation stays verbatim (per `PAC-DEEP-001`). `PatientDetailAppShellTest::assertCrossCategoryDeepLinksPreserved()` asserts the 4 `router.push(...)` patterns remain byte-for-byte. Visual smoke test: click the "Crear plan" button on the Planes tab, verify the URL contains `?patient_id=<id>` and the treatment-plans page loads. |
| 7 | Soft-delete + appointments-conflict 422: `PatientController::destroy` rejects with 422 if the patient has any appointments (including soft-deleted). The polish PR MUST NOT touch this contract. | Low | Apply phase: UI changes do NOT touch `PatientController.php`. The 422 error envelope rendering on the detail page's delete button (the catch block at `PatientDetailPage.vue` line 1152–1159) stays verbatim — the server error message + flattened error bag are surfaced via `useToast` correctly today. |
| 8 | `<style scoped>` block removal from both pages may expose CSS specificity issues if the inline `@media (max-width: 640px)` rule (PatientsPage line 1315) or `.tab-content { min-height: 400px }` rule (PatientDetailPage line 1556) was load-bearing for the responsive / tab-content layout. | Low | Apply phase: rewrite the contents to plain utility classes (`sm:hidden`, `min-h-[400px]`). `ModuleAppShellTestCase::test_no_style_scoped` asserts no `<style scoped>` block remains per file. Visual verification at 1440x900 + 390x844 confirms the responsive behavior + tab-content minimum height are preserved. |
| 9 | Allergy / medical-history alert UX gap (free-text columns, no structured alert). Out of scope for visual polish (global spec forbids new primitives except `<UiStatusBadge>`); flagged in `a11y-followup.md` for a future slice. | Low | PR-pacientes-05 ships the `a11y-followup.md` doc to record the gap. The text-only display on `PatientDetailPage.vue` (free-text inside a `<UiCard>`) is the contract for this rollout. |

---

## 9. File changes

### 9.1 Modified files (across PR-pacientes-01..04)

| File | Action | PR | Description |
|---|---|---|---|
| `resources/js/modules/patients/PatientsPage.vue` | Modify | PR-pacientes-01 + PR-pacientes-02 | List section: search bar + status filter (`<UiSelect>`) + 4 stat cards (`<UiCard clickable>`) + desktop table (hairline + `<UiStatusBadge>` + `<UiButton variant="link">`) + mobile card fallback (`<UiButton variant="ghost">`) + `<style scoped>` removal (line 1315). Modal sections: New Patient (lines 463–581) + Edit Patient (lines 583–725) → `<UiModal>` chrome + `<UiCard>` panel + `<UiInput>` / `<UiSelect>` / `<UiTextarea>` + hairline + `var(--focus-ring-default)`. `<script>` block byte-for-byte unchanged. Legacy `<Pagination>` import kept verbatim. |
| `resources/js/modules/patients/PatientDetailPage.vue` | Modify | PR-pacientes-03 + PR-pacientes-04 | Header (`<UiStatusBadge>` for status pill) + 5-tab drawer (`<UiTabs v-model="currentStep">`) + 4 cross-category deep-links (preserved verbatim) + audit tab (`<UiCard>` + `<UiStatusBadge variant="info">`) + `<style scoped>` removal (line 1556). Edit modal (lines 706–845) → `<UiModal>` + `<UiSelect>` + `<UiInput>` + hairline + `var(--focus-ring-default)`. Export action (lines 1217–1225) → `<UiButton>` + `<UiSelect>`; raw `fetch` + Bearer token + `window.URL.createObjectURL` + `<a download>` anchor click pattern preserved byte-for-byte. `<script>` block byte-for-byte unchanged. |

### 9.2 New files

| File | PR | Description |
|---|---|---|
| `tests/Unit/DesignSystem/PatientsAppShellTest.php` | PR-pacientes-05 | Extends `ModuleAppShellTestCase`; covers `PatientsPage` + 2 inlined modals. |
| `tests/Unit/DesignSystem/PatientDetailAppShellTest.php` | PR-pacientes-05 | Extends `ModuleAppShellTestCase`; covers `PatientDetailPage` + inlined Edit modal + 4 cross-category deep-links + Export action. |
| `tests/Unit/DesignSystem/PatientStatusBadgeTest.php` | PR-pacientes-03 | Asserts `<UiBadge>` / `<UiStatusBadge>` variant presence on status pill + audit tab. |
| `tests/Unit/DesignSystem/PatientTableNumsTest.php` | PR-pacientes-01 | Asserts `tabular-nums` on DNI + age columns in `PatientsPage` + `PatientDetailPage`. |
| `tests/Unit/DesignSystem/PatientModalChromeTest.php` | PR-pacientes-02 | Asserts `<UiModal>` wrapper presence on 2 inlined modals on `PatientsPage` (PR-02) + detail Edit modal (PR-04 extension). |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/a11y-followup.md` | PR-pacientes-05 | Records the allergy / medical-history alert callout work as a future change (out of scope for visual polish). |

### 9.3 Unchanged files (PACIENTES MUST NOT touch)

| File | Why frozen |
|---|---|
| `resources/js/components/ui/StatusBadge.vue` | PR0 primitive; immutable thereafter per global design §6.1. |
| `resources/js/components/ui/{Card,Button,Input,Select,Textarea,Badge,Modal,Tabs,EmptyState,LoadingSpinner}.vue` | Existing primitives; consumed as-is. |
| `AppLayout.canvasRoutes` array literal | PR0 one-shot extension; frozen per global design §3.4. |
| `tokens.js` / `tokens.generated.css` / `scripts/build-tokens-css.mjs` / `tailwind.config.js` | Frozen for entire rollout per `DLR-R-013`. |
| Backend (controllers, services, jobs, listeners, models, migrations) | Out of scope per proposal §3.1. |
| `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` | Per `PAC-RT-001` + `PAC-CON-001`; UI changes are template-level only. |
| `useEcho.js` / `usePermissions.js` / `useApi.js` / `useConfirm.js` / `useAuditLogs.js` / `useToast.js` | Composable surface preserved per `ComposablesStandardizationTest`. |
| `app/Http/Controllers/Api/PatientController.php` | Out of scope; CRUD + export + search verbatim. |
| `app/Http/Resources/PatientResource.php` | Out of scope; additive `age` key MUST stay (pinned by tests). |
| `app/Services/PatientExportService.php` | Out of scope; sync PDF/ZIP export verbatim. |
| `app/Policies/PatientPolicy.php` | Out of scope; role gating preserved verbatim. |
| `app/Models/Patient.php` | Out of scope; SoftDeletes + `$fillable` + `$appends.full_name` + `scopeActive` verbatim. |
| `app/Events/Patient{Created,Updated,Deleted,FileExported}.php` | Out of scope; Reverb broadcasts verbatim. |
| `app/Listeners/LogPatientActivity.php` / `NotifyPatientFileExported.php` | Out of scope; audit log writer + notification fan-out verbatim. |
| `app/Jobs/ExportPatientFileJob.php` | Out of scope; async export; tries + backoff verbatim. |
| `database/migrations/{2025_09_20_082331_create_patients_table,2025_09_27_135908_add_unique_constraints_to_patients_table,2025_10_25_030052_add_document_number_to_patients_table,2026_06_11_001034_add_soft_deletes_to_patients_table,2026_08_11_120000_add_index_to_patients_created_at}.php` | Out of scope; schema + indexes + soft-deletes verbatim. |
| `resources/views/exports/patient-file.blade.php` | PDF template; out of scope (print artifact, separate slice). |
| `resources/js/components/ui/PatientSelector.vue` | Cross-cutting primitive (consumed by 6+ modules); OUT of scope here. |

---

## 10. References

### 10.1 Spec files (PACIENTES contract)

| File | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/pacientes/spec.md` | The 10 PACIENTES scenarios (`PAC-LIST-001`, `PAC-MOD-001`, `PAC-DET-001`, `PAC-EDIT-001`, `PAC-EXP-001`, `PAC-RT-001`, `PAC-PHI-001`, `PAC-DEEP-001`, `PAC-REV-001`, `PAC-CON-001`) — the contract this design satisfies. |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` | Cross-cutting `DLR-R-*` rules + per-module `DLR-MOD-003` row — inherited unmodified. |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` | PR0 contract (`StatusBadge.vue`, `canvasRoutes`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`). |
| `openspec/changes/ui-rollout-all-modules-2026-08/design.md` | Global design (PR0 foundation) — tokens, primitive API, motion durations, focus-ring composition, PHPUnit invariants. |

### 10.2 Source artifacts

| File | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/explore.md` | PACIENTES inventory (frontend, backend, controllers, services, jobs, models, tests, known gotchas). |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/pacientes/proposal.md` | PACIENTES proposal (intent, scope, risk register, PR chain, success criteria). |
| `openspec/changes/archive/2026-08-12-ui-citas/design.md` | CITAS category design — pattern reference for category delta structure + tone + section granularity. |
| `openspec/changes/archive/2026-08-12-ui-pagos/design.md` | PAGOS category design — sibling precedent for category delta. |
| `resources/js/composables/useEcho.js` | `patients` + cross-category channel subscriptions — preserved verbatim per `PAC-RT-001`. |
| `resources/js/composables/useApi.js` | `get / post / put / delete` for `/api/patients` — preserved verbatim per `PAC-CON-001`. |
| `resources/js/composables/usePermissions.js` | `can.createPatient / updatePatient / deletePatient / createTreatmentPlan / createQuotation / createMedicalRecord / createSpecialtyRecord` — preserved verbatim per `PAC-CON-001`. |
| `resources/js/composables/useAuditLogs.js` | `getPatientAuditLogs(patientId)` — preserved verbatim per `PAC-CON-001`. |
| `app/Http/Controllers/Api/PatientController.php` | `index / store / show / update / destroy / search / export` — out of scope. |
| `app/Http/Resources/PatientResource.php` | Wraps every patient response; additive `age` key MUST stay (pinned by tests). |
| `app/Services/PatientExportService.php` | `exportToPdf / exportToZip` — out of scope. |
| `app/Policies/PatientPolicy.php` | `viewAny / view / create / update / delete / restore / forceDelete / export` — out of scope. |
| `app/Models/Patient.php` | SoftDeletes + `$fillable` + `$appends.full_name` + `scopeActive` — out of scope. |
| `database/migrations/2025_09_20_082331_create_patients_table.php` + `2025_09_27_135908_add_unique_constraints_to_patients_table.php` + `2025_10_25_030052_add_document_number_to_patients_table.php` + `2026_06_11_001034_add_soft_deletes_to_patients_table.php` + `2026_08_11_120000_add_index_to_patients_created_at.php` | Schema + indexes + soft-deletes — out of scope. |
| `resources/views/exports/patient-file.blade.php` | PDF template — out of scope (print artifact, separate slice). |
| `tests/Feature/Api/PatientControllerAgeTest.php` | Pins `data.age` integer in API envelope. |
| `tests/Unit/Resources/PatientResourceAgeTest.php` | Pins additive `age` key in resource. |
| `tests/Unit/Controllers/PatientControllerResourceWireUpTest.php` | Source-contract test for `PatientResource` references. |
| `tests/Unit/Polish/ApiAndSeedersPolishTest.php` | API-035 + API-057: export Content-Type whitelist. |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Pin the `canvasRoutes` array literal (includes `/patients`). |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Abstract base class for `*AppShellTest` subclasses. |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | Forbidden alias pin (extended per PR with pacientes aliases). |
| `CREDENTIALS.md` | `recep@test.com` for list + detail; `admin@test.com` for delete + restore flows. |

### 10.3 Process invariants (forwarded from PR0 + CITAS designs)

1. **Test pins rule, not example** (PR0 design §9.3 line 1): `ModuleAppShellTestCase` + per-module subclasses assert RULES, not literal strings. The PACIENTES-specific test methods follow the same discipline.
2. **`<script>` blocks NEVER edited** (PR0 design §5.1 + PACIENTES guard rail #7): UI changes are template-level class-string replacement only. No PACIENTES PR edits `<script>` blocks of any module.
3. **Strict TDD forward** (PR0 design §5.3): every UI replacement comes with a test that proves the new behaviour; RED-GREEN per PR.
4. **Per-PR budget** (`PAC-REV-001`): each `pr-pacientes-NN` ≤ 400 lines; split rule applied (01a/01b, 03a/03b) if reviewer flags.
5. **Existing contracts preserved** (`PAC-CON-001`): `useEcho` / `usePermissions` / `useToast` / `useConfirm` / `useApi` / `useAuditLogs` contracts unchanged; `<script>` blocks of both pacientes pages byte-for-byte unchanged.

---

## 11. What this design does NOT do

- Does NOT add new tokens. `tokens.js` is frozen.
- Does NOT add new primitives. PACIENTES consumes `<UiStatusBadge>` (PR0), `<UiModal>`, `<UiTabs>`, `<UiButton>`, `<UiInput>`, `<UiSelect>`, `<UiTextarea>`, `<UiCard>`, `<UiLoadingSpinner>`, `<UiEmptyState>` from the proven set.
- Does NOT add dark mode.
- Does NOT touch the backend (no controller, no service, no listener, no migration, no job).
- Does NOT relax any standing guard rail from §0.
- Does NOT introduce `<style scoped>` blocks (or carry them as grandfathered).
- Does NOT touch `<script>` blocks of `PatientsPage.vue` + `PatientDetailPage.vue` — UI changes are template-level only.
- Does NOT change `useEcho` channel subscriptions (`patients` + cross-category `treatment-plans` / `quotations` / `medical-records` / `specialty-records`).
- Does NOT change `useApi` call signatures or Bearer-token download pattern.
- Does NOT widen or narrow the `PatientResource` API envelope (additive `age` integer key MUST stay).
- Does NOT alter the soft-delete + appointments-conflict 422 contract.
- Does NOT change the cross-category deep-link navigation (`?patient_id=…` preserved verbatim).
- Does NOT rename the legacy `<Pagination>` import (consolidation rides global PR3).
- Does NOT touch `PatientExportService` synchronous or async export flow.
- Does NOT restyle `resources/views/exports/patient-file.blade.php` (PDF template; print artifact).
- Does NOT remove dormant `fillable` entries (`dni`, `blood_type`, `insurance_provider`, `insurance_number`) — separate cleanup.
- Does NOT introduce an allergy / medical-history alert component — global spec forbids new primitives; flagged in `a11y-followup.md`.
- Does NOT add encryption at rest for `ClinicalAttachment.file_path` on the `public` disk — separate change.
- Does NOT add `consent_forms` / `patient_consents` / `family_relationships` / `guardians` tables.
- Does NOT add `Patient::restore()` / `forceDelete()` REST routes.
- Does NOT introduce per-branch scoping on the `show` endpoint (PHI scope guard is a separate change).
- Does NOT migrate `document_number` to `DOC-XXX` rendering — UX decision, not visual token decision.
- Does NOT tokenise the cross-module `PatientSelector.vue` primitive (consumed by 6+ modules; rides its own PR per global OQ#7).
- Does NOT consolidate the pagination primitive duplication (rides global PR3 Recepción procedimientos).

---

*End of PACIENTES category design.*
