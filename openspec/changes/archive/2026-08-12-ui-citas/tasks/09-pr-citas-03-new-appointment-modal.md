# PR-citas-03 — `NewAppointmentModal` tokenisation + duplicate-key mapping

> **Change**: `ui-rollout-all-modules-2026-08` — CITAS category
> **Date**: 2026-08-12
> **PR scope**: PR-citas-03 only
> **Branch base**: `main` (stacked after PR-citas-02)
> **Review budget**: 400 authored lines / PR (target ~320)
> **Strict TDD**: true

## Goal

Migrate `NewAppointmentModal.vue` (modal chrome — patient / profesional / fecha / duración / tipo / silla / notas) to consume the proven primitives: hand-built `<Teleport to="body">` + `<div class="fixed inset-0 bg-black bg-opacity-50">` → `<UiModal :open="showModal" @close="showModal = false" @confirm="submitAppointment" />`; raw `<select>` / `<input>` borders → `<UiSelect>` / `<UiInput>` + hairline; `focus:ring-primary-500 focus:border-accent` → composed `var(--focus-ring-default)`; `disabled:opacity-30` affordance → `<UiButton :disabled="!canSubmit || loading" :loading="loading">` with inside-button `<UiLoadingSpinner v-if="loading" />`. Add duplicate-key 422 from `AppointmentService::createAppointment` template-level mapping (the DB unique constraint `unique_user_time_slot` / `unique_chair_time_slot` fires on the second commit; map on `error.code === 'duplicate_key'` OR `error.sqlstate === '23000'` AND message contains constraint name → render `<UiStatusBadge variant="error" label="Otra mesa ya reservó este horario" />`). UI changes are template-only; `<script>` block of `NewAppointmentModal.vue` is NEVER touched. The `useApi()` call + timezone contract (`Carbon::parse(...)->setTimezone(config('app.timezone'))`) + 3-axis `AppointmentRepository::findConflicts` round-trip stay verbatim.

## Depends on

- PR0 (landed): primitives + `canvasRoutes`.
- PR-citas-01 (landed): wizard primitives (`<UiTabs>`, `<UiInput>`, `<UiSelect>`) established.
- PR-citas-02 (landed): calendar legend + `<UiStatusBadge>` variant ramps proven.

## Work items (ordered; foundation first, visual last)

- [ ] **T-03.1** — RED: NEW `tests/Unit/DesignSystem/NewAppointmentModalAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `NewAppointmentModal.vue`. Add `assertNewAppointmentModalUsesUiModal()` (regex: `<UiModal` referenced AND `bg-black bg-opacity-50` absent AND `<Teleport to="body">` absent). Add `assertNoClientSideConflictHeuristic()` (regex: zero `findConflicts`/`conflicts`/`available` references in template; the only oracle is the server). Run PHPUnit: RED.
- [ ] **T-03.2** — Migrate modal chrome: REPLACE `<Teleport to="body">` + `<div class="fixed inset-0 bg-black bg-opacity-50" @click.self="close">` + `<div class="bg-white rounded-lg ...">` with `<UiModal :open="showModal" @close="close" @confirm="submitAppointment" title="Nueva cita" />`. Modal motion uses `var(--motion-duration-fast) var(--motion-easing-ios)` (open + close).
- [ ] **T-03.3** — Migrate form fields (patient / profesional / silla / tipo / fecha / duración / notas): raw `<select>` (patient / profesional / silla / tipo) → `<UiSelect variant="bordered">`; raw `<input type="datetime-local">` → `<UiInput type="datetime-local">` + hairline + `var(--focus-ring-default)`; raw `<input type="number">` (duración minutos) → `<UiInput type="number">` + `font-feature-settings: var(--font-features-tabular-nums)`; raw `<textarea>` (notas) → `<UiInput type="textarea">`. Zero `border border-theme bg-theme-surface-elevated` literals remain.
- [ ] **T-03.4** — Migrate submit affordance: REPLACE `<button :disabled="!canSubmit" class="... disabled:opacity-30">` with `<UiButton :disabled="!canSubmit || loading" :loading="loading">` (inside-button `<UiLoadingSpinner v-if="loading" />`). Cancel button → `<UiButton variant="secondary">`.
- [ ] **T-03.5** — Add duplicate-key error mapping (template-level only): in the template, after the submit button block, add `<UiStatusBadge v-if="error?.code === 'duplicate_key' || (error?.sqlstate === '23000' && /unique_(user|chair)_time_slot/.test(error?.message || ''))" variant="error" :label="t('appointments.errors.duplicateKey')" />` (localizable string "Otra mesa ya reservó este horario"). No `<script>` edit; the existing error reactive state is read-only from the template.
- [ ] **T-03.6** — Migrate focus ring: REPLACE `focus:ring-primary-500 focus:border-accent` literals with composed `var(--focus-ring-default)` on every `<UiInput>` / `<UiSelect>`. Error styling → `var(--focus-ring-default)` red-shift via the `<UiInput error>` prop. `LegacyAliasForbiddenTest` extended with `focus:ring-primary-500 focus:border-accent` if not already pinned.
- [ ] **T-03.7** — Emit contract preservation: confirm `open` / `close` / `confirm` emit names on the new `<UiModal>` wrapper match the existing event signatures used by parent callers (`DashboardPage.vue`, `CalendarPage.vue`, `MedicalRecordsPage.vue` — all via `?openAppointmentModal=true` redirect); zero caller-side changes needed. Verify `useApi()` ownership of the 401 redirect path is preserved.
- [ ] **T-03.8** — GREEN: `NewAppointmentModalAppShellTest` passes `assertNewAppointmentModalUsesUiModal()` + `assertNoClientSideConflictHeuristic()`. Add `test_no_js_side_to_iso_string_on_datetime_local()` (regex: `git grep -nE '\.toISOString\(\)' resources/js/components/appointments/NewAppointmentModal.vue` returns zero matches). Run PHPUnit: GREEN.
- [ ] **T-03.9** — Regression: `git grep -nE "bg-black bg-opacity-50|Teleport to=|focus:ring-primary-500 focus:border-accent|disabled:opacity-30|border border-theme bg-theme-surface-elevated"` on `NewAppointmentModal.vue` returns zero matches; `<script>` block byte-for-byte unchanged.
- [ ] **T-03.10** — Tests: `php artisan test --filter=NewAppointmentModalAppShellTest` + `--filter=CitasCalendarAppShellTest` + `--filter=CitasWizardAppShellTest` + `--filter=ComposablesStandardizationTest` + `--filter=AppLayoutCanvasRoutesTest` + `--filter=LegacyAliasForbiddenTest` all GREEN.
- [ ] **T-03.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-03.12** — Visual: `playwright-cli` snapshots at 1440x900 — `citas-new-appointment-modal-1440x900.png` (open), `citas-new-appointment-modal-loading-1440x900.png` (loading spinner during submit), `citas-new-appointment-modal-duplicate-key-1440x900.png` (open the modal in 2 browser tabs, submit the same `(user, scheduled_at, ends_at)` from tab B → tab B shows the friendly error). Login: `recep@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-citas-03-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=NewAppointmentModalAppShellTest` GREEN.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] No `bg-black bg-opacity-50`, no `<Teleport to="body">` in `NewAppointmentModal.vue`.
- [ ] No `focus:ring-primary-500 focus:border-accent` literal in `NewAppointmentModal.vue`.
- [ ] No `disabled:opacity-30` affordance; submit uses `<UiLoadingSpinner>` + `<UiButton :loading>`.
- [ ] No `.toISOString()` call on `datetime-local` input (per `CITAS-TZ-001`).
- [ ] No client-side conflict heuristic; the only oracle is `AppointmentRepository::findConflicts` server-side.
- [ ] Duplicate-key 422 → friendly "Otra mesa ya reservó este horario" mapping (template-level; no `<script>` edit).
- [ ] `<script>` block of `NewAppointmentModal.vue` is byte-for-byte unchanged.
- [ ] `useApi()` 401 redirect path + emit contract (`open` / `close` / `confirm`) preserved.
- [ ] No regression in `CitasCalendarAppShellTest`, `CitasWizardAppShellTest`, `ConsultationWizardStatusEnumTest`, `ComposablesStandardizationTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`.
- [ ] PR diff under 400 lines.
- [ ] 3 screenshots saved under `.playwright-cli/screenshots-rollout/pr-citas-03-*.png`.

## Out of scope (deferred)

- `AppointmentTypesPage` + `AppointmentTypeDetailPage` admin CRUD triplet — PR-citas-04.
- Cross-cutting tests + a11y follow-up doc — PR-citas-05.
- Backend conflict detection round-trip improvements (`AppointmentService::createAppointment`) — out of scope, service unchanged.
- Optimistic UI for duplicate-key pre-check — DEFERRED; visual change is template-only.

## Test plan (commands)

```bash
php artisan test --filter=NewAppointmentModalAppShellTest
php artisan test --filter=CitasCalendarAppShellTest
php artisan test --filter=CitasWizardAppShellTest
php artisan test --filter=LegacyAliasForbiddenTest
pnpm build
pnpm lint:check
git grep -nE '\.toISOString\(\)' resources/js/components/appointments/NewAppointmentModal.vue
git grep -nE "bg-black bg-opacity-50|Teleport to=|disabled:opacity-30" \
  resources/js/components/appointments/NewAppointmentModal.vue
git diff --stat resources/js/components/appointments/NewAppointmentModal.vue
playwright-cli screenshot http://localhost:5173/dashboard?openAppointmentModal=true 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-03-new-appointment-modal-1440x900.png
playwright-cli screenshot http://localhost:5173/dashboard?openAppointmentModal=true 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-03-new-appointment-modal-loading-1440x900.png
```

## Key Learnings (forwarded to apply)

1. Duplicate-key 422 mapping is template-level only (per design §3.3); the rule maps on `error.code === 'duplicate_key'` OR `error.sqlstate === '23000'` AND constraint-name regex — covers both `unique_user_time_slot` and `unique_chair_time_slot` from `2025_09_20_082341_create_appointments_table.php`.
2. Modal motion duration uses `var(--motion-duration-fast) var(--motion-easing-ios)` — inherited from `<UiModal>` primitive; no per-instance override.
3. `<UiModal>` emit contract (`open` / `close` / `confirm`) preserves the existing parent-caller signatures — zero caller-side changes in `DashboardPage.vue` / `CalendarPage.vue` / `MedicalRecordsPage.vue`.

## References

- `categories/citas/design.md` §3.3 (NewAppointmentModal chrome decision), §3.6 (conflict round-trip), §6.2 (PR-citas-03 test extensions)
- `categories/citas/spec.md` `CITAS-MOD-001`, `CITAS-TZ-001`, `CITAS-CONF-001`, `CITAS-RT-001`
- `database/migrations/2025_09_20_082341_create_appointments_table.php` (unique constraints `unique_user_time_slot`, `unique_chair_time_slot`)
- `app/Services/AppointmentService.php` (duplicate-key source — out of scope)
- `CREDENTIALS.md` (`recep@test.com` for modal)
