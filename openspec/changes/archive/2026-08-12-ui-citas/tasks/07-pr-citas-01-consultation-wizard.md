# PR-citas-01 — `ConsultationWizard` tokenisation (densest form surface)

> **Change**: `ui-rollout-all-modules-2026-08` — CITAS category
> **Date**: 2026-08-12
> **PR scope**: PR-citas-01 only
> **Branch base**: `main` (stacked after PR0 `feat/ui-rollout-pr0-foundation`)
> **Review budget**: 400 authored lines / PR (target ~390; split into 01a + 01b if reviewer flags)
> **Strict TDD**: true

## Goal

Migrate `ConsultationWizard.vue` (5 steps: mode / SOAP evolution / procedures / materials / odontogram + attachments + summary; ~50 raw `<input>` / `<textarea>` / `<select>` controls) to consume the proven primitives: raw form controls → `<UiInput>` / `<UiSelect>` + hairline; raw `<button>` step strip → `<UiTabs v-model="currentStep">` with `var(--motion-duration-fast) var(--motion-easing-ios)` transitions; raw checkboxes → `<UiCheckbox>` or `<UiStatusBadge>` indicator; hardcoded `text-red-500` asterisks → `<UiInput required>` indicator; `bg-accent bg-opacity-5` selected state → `<UiTabs>` active state. `<script>` block of `ConsultationWizard.vue` is NEVER touched (per `CITAS-CON-001`); `useConsultation` reactivity preserved verbatim.

## Depends on

- PR0 (landed): `canvasRoutes`, `<UiStatusBadge>`, `<UiTabs>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`.

## Work items (ordered; foundation first, visual last)

- [ ] **T-01.1** — RED: NEW `tests/Unit/DesignSystem/CitasWizardAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `ConsultationWizard.vue`. Add `assertWizardUsesUiTabs()` (regex over `<script setup>` + template: asserts `<UiTabs` is referenced AND inline `@click="currentStep = step.id"` is absent) + `assertWizardUsesUiInputs()` (regex: zero raw `<input class="border-theme">`, zero raw `<select class="border-theme">`). Run PHPUnit: RED.
- [ ] **T-01.2** — Migrate step 1 (`mode` selection) controls: raw `<select>` → `<UiSelect variant="bordered">` with `v-model="form.mode"`; mode chips → `<UiStatusBadge variant="info|warning|success">` for `consultation | execution | plan_session`; hardcoded `text-red-500` asterisks → `<UiSelect required>` indicator.
- [ ] **T-01.3** — Migrate step 2 (`SOAP evolution`) controls: 4 raw `<textarea>` (Subjective / Objective / Assessment / Plan) → `<UiInput type="textarea">` + hairline + `var(--focus-ring-default)` on focus; raw checkbox (consentimiento informado) → `<UiCheckbox>`; required asterisks → `<UiInput required>`.
- [ ] **T-01.4** — Migrate step 3 (`procedures`) controls: raw `<input>` (duración, número de piezas) → `<UiInput type="number">` with `font-feature-settings: var(--font-features-tabular-nums)`; raw `<select>` (procedimiento catálogo) → `<UiSelect>`; add-procedure button → `<UiButton variant="secondary">`.
- [ ] **T-01.5** — Migrate steps 4 (`materials`) + 5 (`odontogram + attachments + summary`): materials list rows → hairline; material quantity `<input>` → `<UiInput type="number">` + `tabular-nums`; odontogram checkboxes → `<UiCheckbox>` or `<UiStatusBadge>` indicator on required; attachments `<input type="file">` stays native but wrapped in `<UiCard>` chrome; summary readonly fields consume `formatCurrency` from `@/composables/useFormatters` (zero `S/ ${...}` literals).
- [ ] **T-01.6** — Migrate the step strip: REPLACE raw `<button v-for="step in steps" :class="..." @click="currentStep = step.id">` with `<UiTabs v-model="currentStep" :items="steps">`; step transition uses `var(--motion-duration-fast) var(--motion-easing-ios)` (opacity + translateY ≤8px, per archive-report lesson); `bg-accent bg-opacity-5` selected state replaced by `<UiTabs>` active state.
- [ ] **T-01.7** — Verify `useConsultation` contract: re-read `resources/js/composables/useConsultation.js`; confirm `loadConsultationContext()` / `checkIn()` / `complete()` / `currentStep` ref signature preserved byte-for-byte. `git diff --stat resources/js/composables/useConsultation.js` returns zero edits. `ComposablesStandardizationTest` stays green at PR boundary.
- [ ] **T-01.8** — GREEN: `CitasWizardAppShellTest` passes `assertWizardUsesUiTabs()` + `assertWizardUsesUiInputs()`. Add `test_no_js_side_to_iso_string_on_datetime_local()` (regex: `git grep -nE '\.toISOString\(\)' resources/js/modules/appointments/ConsultationWizard.vue` returns zero matches). Run PHPUnit: GREEN.
- [ ] **T-01.9** — Regression: `git grep -nE "border-theme|bg-success-100|text-red-500|focus:ring-primary-500 focus:border-accent"` on `ConsultationWizard.vue` returns zero matches; `<script>` block byte-for-byte unchanged (`git diff -- resources/js/modules/appointments/ConsultationWizard.vue | grep -A 2 'script setup'` shows no `<script>` mutations).
- [ ] **T-01.10** — Tests: `php artisan test --filter=CitasWizardAppShellTest` + `--filter=ComposablesStandardizationTest` + `--filter=AppLayoutCanvasRoutesTest` + `--filter=LegacyAliasForbiddenTest` all green.
- [ ] **T-01.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-01.12** — Visual: `playwright-cli` snapshots at 1440x900 — `citas-wizard-step-1-mode-1440x900.png`, `citas-wizard-step-3-procedures-1440x900.png`, `citas-wizard-step-5-summary-1440x900.png`, `citas-wizard-back-forward-1440x900.png` (after navigating forward 3 + back 2 to verify step strip motion). Login: `odontologo@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-citas-01-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=CitasWizardAppShellTest` GREEN.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] No raw `<input class="border-theme">` or raw `<select class="border-theme">` inside `ConsultationWizard.vue`.
- [ ] No inline `@click="currentStep = step.id"`; `<UiTabs v-model="currentStep">` is the step nav.
- [ ] Zero `.toISOString()` calls on `datetime-local` inputs (per `CITAS-TZ-001`).
- [ ] `<script>` block of `ConsultationWizard.vue` is byte-for-byte unchanged.
- [ ] `useConsultation` reactivity preserved verbatim; `ComposablesStandardizationTest` green.
- [ ] No regression in `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `ConsultationWizardStatusEnumTest` (PR-citas-02 will land — pre-existing).
- [ ] PR diff under 400 lines; if exceeded, split per design §4.3 (PR-citas-01a steps 1–3 + PR-citas-01b steps 4–5).
- [ ] 4 screenshots saved under `.playwright-cli/screenshots-rollout/pr-citas-01-*.png`.

## Out of scope (deferred)

- `CalendarPage.vue` chrome (status pills, 7-value legend) — PR-citas-02.
- `NewAppointmentModal.vue` chrome + duplicate-key 422 mapping — PR-citas-03.
- `AppointmentTypesPage` + `AppointmentTypeDetailPage` admin CRUD triplet — PR-citas-04.
- Cross-cutting `CalendarAppShellTest` + `AppointmentTypesAppShellTest` + a11y follow-up doc — PR-citas-05.
- Calendar grid `role="grid"` + per-cell `aria-label` — a11y follow-up, out of scope.

## Test plan (commands)

```bash
php artisan test --filter=CitasWizardAppShellTest
php artisan test --filter=ComposablesStandardizationTest
php artisan test --filter=AppLayoutCanvasRoutesTest
php artisan test --filter=LegacyAliasForbiddenTest
pnpm build
pnpm lint:check
git grep -nE '\.toISOString\(\)' resources/js/modules/appointments/ConsultationWizard.vue
git grep -nE 'border-theme|text-red-500|bg-accent bg-opacity-5' resources/js/modules/appointments/ConsultationWizard.vue
git diff --stat resources/js/modules/appointments/ConsultationWizard.vue
playwright-cli screenshot http://localhost:5173/medical-records/1/consultation 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-01-wizard-step-1-mode-1440x900.png
playwright-cli screenshot http://localhost:5173/medical-records/1/consultation?step=3 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-01-wizard-step-3-procedures-1440x900.png
playwright-cli screenshot http://localhost:5173/medical-records/1/consultation?step=5 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-01-wizard-step-5-summary-1440x900.png
```

## Key Learnings (forwarded to apply)

1. `useConsultation` composable at `resources/js/composables/useConsultation.js` is the sole contract — wizard `<script>` block is NEVER edited; `currentStep` ref binding preserved byte-for-byte.
2. Step strip transition must stay minimal (opacity + translateY ≤8px) per archive-report lesson; over-animation feels sluggish for clinical back/forward navigation.
3. `AppointmentTypesPage` price field `formatCurrency` dependency lands in PR-citas-04 (after PAGOS PR-pagos-05); wizard summary (PR-citas-01) may temporarily import via TEMPORARY local helper matching the canonical signature.

## References

- `categories/citas/design.md` §3.1 (ConsultationWizard tab strip decision), §3.5 (timezone contract), §6.2 (PR-citas-01 test extensions)
- `categories/citas/spec.md` `CITAS-WIZ-001`, `CITAS-TZ-001`, `CITAS-CON-001`
- `categories/citas/explore.md` line 28 (`ConsultationWizard` inventory + ~50 raw controls)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base class to extend)
- `resources/js/composables/useConsultation.js` (canonical contract — preserved)
- `CREDENTIALS.md` (`odontologo@test.com` for wizard)
