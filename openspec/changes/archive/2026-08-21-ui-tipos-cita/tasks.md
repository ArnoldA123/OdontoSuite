# Tasks: tipos-cita (ui-rollout-all-modules-2026-08)

> 2 chained PRs: PR-tipos-01 (list + modals) + PR-tipos-02 (detail + gradient).
> Residual cleanup only — module already polished by PR-citas-04.

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `tipos-cita` |
| Date | 2026-08-21 |
| Phase | tasks (4 of 6) |
| Artifact store | hybrid (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/tipos-cita/tasks`) |
| Delivery strategy | `auto-chain` |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` |
| Pace | auto |
| Roles | admin only (`administrador`) |

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines (total) | ~380 (200 + 180) |
| Estimated changed lines (PR-tipos-01) | ~200 |
| Estimated changed lines (PR-tipos-02) | ~180 |
| 400-line budget risk | Low (each PR comfortably under 400) |
| Chained PRs recommended | Yes |
| Suggested split | PR-tipos-01 (list + modals) → PR-tipos-02 (detail + gradient) |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |
| Decision needed before apply | No |
| Chained PRs recommended | Yes |
| Chain strategy | stacked-to-main |
| 400-line budget risk | Low |

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| PR-tipos-01 | Residual cleanup on `AppointmentTypesPage.vue` (9 raw inputs + 2 raw textareas + spinner + empty state) | PR-tipos-01 | `php artisan test --filter=AppointmentTypesListCleanupTest` | `pnpm run build` + Playwright 1440x900 list snapshot | `git revert <merge-sha>` — UI reverts to legacy inputs but `<script>` block untouched |
| PR-tipos-02 | Residual cleanup on `AppointmentTypeDetailPage.vue` (tab nav + **CRITICAL gradient removal** + audit row + spinner + colour ramps) | PR-tipos-02 | `php artisan test --filter=AppointmentTypesAppShellTest` | `pnpm run build` + Playwright 1440x900 detail snapshot | `git revert <merge-sha>` — UI reverts to legacy, gradient defect restored but tracked |

---

# PR-tipos-01 — list + modals

> Target: `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (641 lines).
> New test: `tests/Unit/DesignSystem/AppointmentTypesListCleanupTest.php` (4 additive rules).
> Risk: Low. Lines: ~200. Independent of PR-tipos-02.

## Phase 1: RED

- [x] **T1.1**: Create `tests/Unit/DesignSystem/AppointmentTypesListCleanupTest.php` extending `ModuleAppShellTestCase`. Add 4 additive test methods pinning the rules from spec.md:
  - `test_list_uses_ui_input_for_modal_form_fields` (TIPOS-01-001) — asserts POSITIVE `≥7 <UiInput v-model="...">` references in modal sections + NEGATIVE zero raw `<input type="text">` / `<input type="number">`.
  - `test_list_uses_ui_textarea_for_description_fields` (TIPOS-01-002) — asserts POSITIVE `≥2 <UiTextarea v-model="...">` references + NEGATIVE zero raw `<textarea>` in modal sections.
  - `test_list_uses_loading_spinner` (TIPOS-01-003) — asserts POSITIVE `<LoadingSpinner` reference present on line 85 vicinity + NEGATIVE zero `border-accent` legacy alias anywhere in the file.
  - `test_list_uses_ui_empty_state` (TIPOS-01-004) — asserts POSITIVE `<UiEmptyState` reference in list section + NEGATIVE hand-rolled custom SVG empty container absent on lines 89-104 + the `UiEmptyState` import at line 427 is now consumed (no dead import remains).

## Phase 2: GREEN

- [x] **T1.2**: Apply **TIPOS-01-001** — Replace 9 raw `<input>` (name / duration_minutes / price / color text-input × 2 in New modal lines 226-290; name / duration_minutes / price / color text-input × 1 in Edit modal lines 293-359) with `<UiInput v-model="..." />`. Preserve all 8 `v-model` bindings (`newType.name`, `newType.duration_minutes`, `newType.price`, `newType.color` text-input, `editingType.name`, `editingType.duration_minutes`, `editingType.price`, `editingType.color` text-input) byte-for-byte. The 2 `<input type="color">` color pickers (New modal line 270 + Edit modal line 337) MAY remain raw because `<UiInput>` does not formally support `type="color"`.
- [x] **T1.3**: Apply **TIPOS-01-002** — Replace 2 raw `<textarea>` description fields (New modal line 239, Edit modal line 306) with `<UiTextarea v-model="..." />`. Preserve `v-model="newType.description"` and `v-model="editingType.description"` bindings verbatim.
- [x] **T1.4**: Apply **TIPOS-01-003** — Replace hand-rolled `<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />` spinner on `AppointmentTypesPage.vue:85` with `<LoadingSpinner />`. Remove the `border-accent` legacy alias entirely.
- [x] **T1.5**: Apply **TIPOS-01-004** — Replace hand-rolled empty state (custom SVG + `text-theme-secondary` text) on lines 89-104 with `<UiEmptyState title="No se encontraron tipos de cita" description="Crea el primer tipo de cita para empezar" />`. The `<UiEmptyState>` import at line 427 already exists but is unused — this task wires it up (consumes the dead import).
- [x] **T1.5.1**: Constraint — `<script>` block (lines 413-640) MUST remain byte-for-byte unchanged. No edits to `useApi` / `useToast` / `useConfirm` / `useErrorHandler` / `useFormatters` imports or any reactivity logic.

## Phase 3: VERIFY

- [x] **T1.6**: Run `AppointmentTypesListCleanupTest` (4 new rules) — all green. Run `AppointmentTypesAppShellTest` (existing 7 + 5 inherited rules) — still green (no regression). Run `ModuleAppShellTestCase` 5 inherited rules — still green.
- [x] **T1.7**: Visual verification at 1440x900 — open `/appointment-types`, click "Nuevo Tipo", fill all 4 form fields + description, verify focus-ring composition + POST payload. Repeat for Edit modal. Verify list empty state when no records. Save snapshot to `.playwright-cli/screenshots-rollout/appointment-types-list-1440x900.png`.

## Phase 4: COMMIT

- [x] **T1.8**: Commit with conventional message: `feat(ui): tipos-cita list + modals tokenise (TIPOS-01-001..004)`. Open PR `pr-tipos-01-list-modals` to `main`. Confirm CI green (`quality`, `backend-tests` MySQL, `frontend-build` pnpm).

---

# PR-tipos-02 — detail + gradient removal

> Target: `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` (407 lines).
> Test extension: `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` (+3 additive rules).
> Risk: Low-Medium (gradient removal is load-bearing). Lines: ~180. Depends on PR-tipos-01 merged.

## Phase 1: RED

- [ ] **T2.1**: Extend `AppointmentTypesAppShellTest.php` with 3 new additive test methods:
  - `test_detail_uses_ui_tabs_for_tab_nav` (TIPOS-02-001) — asserts POSITIVE `<UiTabs` reference present on lines 84-100 + NEGATIVE zero raw `<button>` step strip + zero `border-systemBlue-500 text-systemBlue-600` active indicator classes + asserts `tabs` array literal in `<script>` uses `label` (NOT `name`).
  - `test_detail_no_gradient_anywhere` **(TIPOS-02-002 CRITICAL)** — source-greps for `bg-gradient` and asserts ZERO matches anywhere in `AppointmentTypeDetailPage.vue`. This pins global guard rail #10 (no gradients anywhere).
  - `test_detail_uses_loading_spinner_for_audit` (TIPOS-02-005) — asserts POSITIVE `<LoadingSpinner` reference present on line 161 vicinity + NEGATIVE zero `border-primary-200` or `border-t-primary-600` legacy aliases anywhere in the file.
  - `test_detail_audit_row_uses_ui_card` (TIPOS-02-004) — asserts POSITIVE `<UiCard variant="glass">` reference present in audit-log row section + NEGATIVE raw `border border-hairline rounded-lg p-4 hover:bg-theme-surface transition-colors` absent + `space-y-4` parent present.
  - `test_detail_audit_empty_uses_ui_empty_state` (TIPOS-02-003) — asserts POSITIVE `<UiEmptyState` reference present on lines 165-187 + NEGATIVE hand-rolled custom SVG empty container absent.

## Phase 2: GREEN

- [ ] **T2.2 (CRITICAL)**: Apply **TIPOS-02-002** — Remove `bg-gradient-to-br from-theme-surface to-theme-surface-elevated` on `AppointmentTypeDetailPage.vue:167`. This is a global guard rail #10 violation (no gradients anywhere). The removal is achieved by adopting `<UiEmptyState title="No hay historial de auditoría" description="Este tipo de cita no tiene registros de auditoría." />` (this same task also covers TIPOS-02-003). MUST NOT be deferred, extracted as a hotfix, or bundled into a later PR.
- [ ] **T2.3**: Apply **TIPOS-02-001** — Replace raw `<button>` tab nav (lines 84-100, custom `border-systemBlue-500 text-systemBlue-600` active indicator) with `<UiTabs v-model="activeTab" :tabs="tabs" />`. Rename `tabs` array literal in `<script>` (lines 314-325): field `name` → `label` per `Tabs.vue:78` validator (OQ-1). Keep `id` + `icon` fields verbatim. `activeTab` ref + `loadAuditLogs` watcher + `getAppointmentTypeAuditLogs(id)` call MUST stay verbatim.
- [ ] **T2.4**: Apply **TIPOS-02-003** — Hand-rolled audit empty state on lines 165-187 → `<UiEmptyState title="No hay historial de auditoría" description="Este tipo de cita no tiene registros de auditoría." />` (default folder icon from `EmptyState.vue:19-30`). Bundled with T2.2 above.
- [ ] **T2.5**: Apply **TIPOS-02-004** — Replace hand-rolled audit log row (`border border-hairline rounded-lg p-4 hover:bg-theme-surface transition-colors` on lines 189-251) with `<UiCard variant="glass">` wrapper inside a `space-y-4` parent (pacientes precedent at `archive/2026-08-12-ui-pacientes/design.md` §3.8).
- [ ] **T2.6**: Apply **TIPOS-02-005** — Replace hand-rolled `<div class="animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600" />` spinner on line 161 with `<LoadingSpinner />`. Remove the `border-primary-*` legacy alias entirely.
- [ ] **T2.7**: Apply **TIPOS-02-006** — Replace `text-red-500` (line 225) and `text-green-500` (line 229) in the audit change-diff block with `text-systemRed-600` and `text-systemGreen-600` (token-aligned Apple-language ramps). Verify `LegacyAliasForbiddenTest` extended assertions still green.
- [ ] **T2.7.1**: Constraint — `<script>` block (lines 259-406) MUST remain byte-for-byte unchanged EXCEPT for the `tabs` array literal `name` → `label` rename in T2.3 (data-layer label-key rename, NOT a logic change). No edits to `useApi` / `useAuditLogs.getAppointmentTypeAuditLogs` / `formatCurrency` / `formatPrice` / `formatDate` / `getAuditActionVariant` contracts.

## Phase 3: VERIFY

- [ ] **T2.8**: Run `AppointmentTypesAppShellTest` (now 10 PR-citas-04/PR-tipos-02 rules + 5 inherited) — all green. Run `ModuleAppShellTestCase` 5 inherited rules — still green. Run `LegacyAliasForbiddenTest` (extended for `text-red-*` / `text-green-*` gradients) — green.
- [ ] **T2.9**: Visual verification at 1440x900 — open `/appointment-types/:id`, click "Datos" + "Historial" tabs, verify `activeTab` updates correctly. Open detail page with no audit logs, verify the audit empty state renders on canvas WITHOUT any gradient background (CRITICAL visual check). Open detail page with audit logs present, verify each row has hairline + glass surface + `space-y-4` parent. Save snapshot to `.playwright-cli/screenshots-rollout/appointment-type-detail-1440x900.png`.
- [ ] **T2.9.1**: Source-grep verification: `rg "bg-gradient" resources/js/modules/appointment-types/` returns ZERO matches across both files. `rg "text-red-500|text-green-500|border-accent|border-primary-200" resources/js/modules/appointment-types/` returns ZERO matches.

## Phase 4: COMMIT

- [ ] **T2.10**: Commit with conventional message: `feat(ui): tipos-cita detail + remove forbidden gradient (TIPOS-02-001..006)`. Open PR `pr-tipos-02-detail-tabs-spinner-empty-card-row` to `main`. Confirm CI green (`quality`, `backend-tests` MySQL, `frontend-build` pnpm). After merge, the gradient defect from global guard rail #10 is closed.

---

## Out of scope (do NOT include)

- `<input type="color">` migration — `<UiInput>` doesn't formally support `type="color"`; flagged for follow-up.
- Dark mode — global decision deferred; light-only per `tokens.js` line 29.
- New primitives — full PR0 + Domain 2 set inherited; no additions.
- `<style scoped>` blocks — already zero in both files per `DLR-R-021`.
- Backend envelope (`AppointmentTypeResource`) — frozen per global proposal §4.
- `useAuditLogs.getAppointmentTypeAuditLogs` composable contract — frozen.
- `<script>` block edits — NEVER touch, except for the `tabs` array `name` → `label` rename in T2.3.
- Multi-branch pricing — schema, not UI.
- Audit log retention policy — backend, not UI.

## Inherited MUSTs (already green — must stay green)

- **DLR-R-001 canvas surface** — `bg-canvas` referenced; `ModuleAppShellTestCase::test_page_references_canvas_token` green.
- **DLR-R-002 no `border-theme` literal** — both files MUST NOT contain `border-theme`.
- **DLR-R-004 no legacy focus-ring aliases** — `AppointmentTypesAppShellTest::test_pages_no_legacy_focus_ring` green.
- **DLR-R-007 `tabular-nums` on price** — `AppointmentTypesAppShellTest::test_pages_price_column_uses_tabular_nums` green.
- **DLR-R-009 no legacy status pills** — neither file uses `bg-success-100` etc.
- **DLR-R-021 no `<style scoped>` block** — `ModuleAppShellTestCase::test_no_style_scoped` green.
- **CITAS-AT-001 formatCurrency canonical** — both files import `formatCurrency` from `useFormatters.js`.
- **CITAS-CON-001 `useApi` ownership** — list page keeps `useApi` import verbatim.

---

## References

- `openspec/changes/ui-rollout-all-modules-2026-08/categories/tipos-cita/spec.md` — MUST rows (4 TIPOS-01 + 6 TIPOS-02, 10 total).
- `openspec/changes/ui-rollout-all-modules-2026-08/categories/tipos-cita/proposal.md` — intent, scope, risk register.
- `openspec/changes/ui-rollout-all-modules-2026-08/categories/tipos-cita/explore.md` — inventory + open questions.
- `openspec/changes/archive/2026-08-12-ui-pacientes/design.md` (§3.5 `<UiTabs>`, §3.8 audit row `<UiCard>` + `space-y-4`).
- `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (641 lines).
- `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` (407 lines, line 167 gradient).
- `resources/js/components/ui/Tabs.vue` (line 78 `label` validator).
- `resources/js/components/ui/EmptyState.vue` (default folder icon, lines 19-30).
- `resources/js/components/ui/LoadingSpinner.vue` — canonical primitive.
- `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` — 7 PR-citas-04 rules + 5 inherited; PR-tipos-02 extends with 3 additive rules.
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` — abstract base; 5 inherited rules.

---

## Key Learnings

1. **[CRITICAL]** `bg-gradient-to-br` on `AppointmentTypeDetailPage.vue:167` violates global guard rail #10 (no gradients anywhere). Task T2.2 MUST remove it as part of adopting `<UiEmptyState>`; pinned by `test_detail_no_gradient_anywhere` asserting zero matches.
2. The pacientes detail-page pattern (`<UiTabs>` + `<UiCard variant="glass">` audit row + `<UiEmptyState>` audit empty state + `space-y-4` parent) applies verbatim to tipos-cita with a single data-layer rename: `tabs` array `name` → `label` to match `Tabs.vue:78` validator.
3. The unused `<UiEmptyState>` import at `AppointmentTypesPage.vue:427` is a dead import from PR-citas-04 — task T1.5 wires it up as part of the list empty-state migration rather than adding a new import.
4. Pre-polished modules require residual-only PRs rather than first-pass migration; the slice plan shrinks to two small PRs (~200 + ~180 lines) instead of one 400-line PR.
5. Neither tipos-cita page subscribes to Reverb channels, so the rollout carries no realtime regression risk and the 6-composable preservation rules from PACIENTES do not apply.

---

*End of tipos-cita tasks. Next phase: `sdd-apply` (PR-tipos-01 first per auto-chain strategy).*
