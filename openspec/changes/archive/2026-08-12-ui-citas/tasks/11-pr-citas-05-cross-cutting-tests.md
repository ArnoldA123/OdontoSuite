# PR-citas-05 — cross-cutting tests + calendar grid a11y follow-up

> **Change**: `ui-rollout-all-modules-2026-08` — CITAS category
> **Date**: 2026-08-12
> **PR scope**: PR-citas-05 only
> **Branch base**: `main` (stacked after PR-citas-04)
> **Review budget**: 400 authored lines / PR (target ~180)
> **Strict TDD**: true

## Goal

Land the consolidated cross-cutting tests + the calendar grid a11y follow-up document. Consolidate the per-module rule assertions from PR-citas-01..04 into 2 durable `*AppShellTest` files + 1 negative-space rules test (5 rules: no JS-side `.toISOString()` on `datetime-local`, no client-side conflict heuristic, no `ConfirmationToken` exposure, no `WorkSchedule` / `AppointmentBlock` enforcement UX, no `<script>` block edits on CITAS modules). Optionally introduce `role="grid"` + per-cell `aria-label` on the calendar grid (per `CITAS-A11Y-001` OPTIONAL row). Re-snapshot PR-citas-01..04 visual regression to confirm no drift from the consolidated tests.

## Depends on

- PR0 (landed).
- PR-citas-01..04 (landed): wizard + calendar + modal + admin CRUD triplet tokenised; per-PR test files (`CitasWizardAppShellTest`, `CitasCalendarAppShellTest`, `NewAppointmentModalAppShellTest`, `ConsultationWizardStatusEnumTest`, `AppointmentTypesAppShellTest`, `AppointmentPriceFormatterTest`) all GREEN.

## Work items (ordered; foundation first, visual last)

- [ ] **T-05.1** — RED: NEW `tests/Unit/DesignSystem/CitasNegativeSpaceRulesTest.php` extending `TestCase` (NOT `ModuleAppShellTestCase` — this is a cross-cutting negative-space guard). Add 5 test methods: (a) `test_no_js_side_to_iso_string_on_datetime_local` — regex `git grep -nE '\.toISOString\(\)' resources/js/modules/appointments resources/js/components/appointments resources/js/modules/appointment-types` returns zero matches; (b) `test_no_client_side_conflict_heuristic` — regex over `NewAppointmentModal.vue` template: zero `findConflicts` / `conflicts` / `available` references; (c) `test_no_confirmation_token_render` — regex `git grep -nE 'ConfirmationToken|confirmation_token' resources/js/modules/appointments resources/js/modules/appointment-types` returns zero matches; (d) `test_no_work_schedule_or_block_enforcement_ux` — regex over `CalendarPage.vue` + `NewAppointmentModal.vue`: zero `work[_ ]?schedule` / `appointment[_ ]?block` matches that imply enforcement (allow data-table column names but disallow UX text like "Fuera de horario" / "Bloqueado"); (e) `test_no_script_block_edits_on_citas_modules` — regex over `git diff --stat` of the 5 CITAS `.vue` files vs the PR-citas-05 base: zero `<script setup>` diffs. Run PHPUnit: RED on at least 1 method (the negative-space guards must fail before enforcement begins).
- [ ] **T-05.2** — GREEN: `CitasNegativeSpaceRulesTest` passes all 5 methods after PR-citas-01..04 work landed. Extend `LegacyAliasForbiddenTest` `LEGACY_ALIASES` with `bg-black bg-opacity-50` (the modal backdrop literal from PR-citas-03) + `focus:ring-primary-500 focus:border-accent` (modal focus ring from PR-citas-03) + `border-theme` (admin CRUD from PR-citas-04) if not already pinned. Run PHPUnit: GREEN.
- [ ] **T-05.3** — NEW: create `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/a11y-followup.md`. Document the calendar grid `role="grid"` + per-cell `aria-label` follow-up (so screen readers can navigate "Tuesday 9 AM, Tuesday 10 AM" efficiently) as a future a11y slice. Document the `CalendarService::getCalendarData` line 101 hardcoded `textColor: '#ffffff'` color-contrast defect (against `appointmentType->color` which can be any hex) and propose a luminance-based text-color resolver as a future fix. Mark both rows OPTIONAL per `CITAS-A11Y-001`; no test fails if not introduced in this rollout.
- [ ] **T-05.4** — Optional a11y slice (per design §1 + `CITAS-A11Y-001` OPTIONAL): add `role="grid"` to the week-view container in `CalendarPage.vue` + `role="gridcell"` + `aria-label="${dayLabel} ${hourLabel}"` to each hour cell. This is OPTIONAL; if reviewer flags it as scope creep, skip and let the follow-up doc carry the work.
- [ ] **T-05.5** — Consolidate per-PR test files: rename `CitasWizardAppShellTest` + `CitasCalendarAppShellTest` + `NewAppointmentModalAppShellTest` + `AppointmentTypesAppShellTest` from per-PR ephemeral tests into the consolidated `tests/Unit/DesignSystem/CitasAppShellTest.php` (or keep them as 4 separate files — designer's call). Each `*AppShellTest` extends `ModuleAppShellTestCase` and asserts the per-module rule set (token reference exists, alias absent, `<style scoped>` absent, no `<script>` block edits).
- [ ] **T-05.6** — Re-run all PR-citas-01..04 PHPUnit test suites: `CitasWizardAppShellTest` + `CitasCalendarAppShellTest` + `NewAppointmentModalAppShellTest` + `AppointmentTypesAppShellTest` + `ConsultationWizardStatusEnumTest` + `AppointmentPriceFormatterTest` + `CitasNegativeSpaceRulesTest` + `ComposablesStandardizationTest` + `AppLayoutCanvasRoutesTest` + `LegacyAliasForbiddenTest` + `FormatPENLabelTest` all GREEN.
- [ ] **T-05.7** — Final regression sweep: `git grep -nE "border-theme|bg-success-100|text-accent|bg-error-100|bg-primary-50|hover-lift|disabled:opacity-30|bg-black bg-opacity-50|focus:ring-primary-500 focus:border-accent|bg-accent bg-opacity-5|S/ \$\{n\.toFixed|Intl\.NumberFormat|ConfirmationToken|confirmation_token|work[_ ]?schedule|appointment[_ ]?block|\.fc-event|\.fc-daygrid|\.fc-timegrid|\.fc-toolbar"` across `resources/js/modules/appointments/**` + `resources/js/components/appointments/**` + `resources/js/modules/appointment-types/**` returns zero matches (the full CITAS surface).
- [ ] **T-05.8** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-05.9** — Realtime smoke: open `/calendar` in 2 browser tabs (`recep@test.com`); create appointment in tab A; verify tab B receives `AppointmentCreated` within 1 second (per `CITAS-RT-001`). Document the result in the PR description.
- [ ] **T-05.10** — Visual sweep: `playwright-cli` re-run of PR-citas-01..04 screenshots to confirm no visual drift from the consolidated tests. Files: `pr-citas-05-wizard-1440x900.png` + `pr-citas-05-calendar-1440x900.png` + `pr-citas-05-calendar-390x844.png` + `pr-citas-05-modal-1440x900.png` + `pr-citas-05-appointment-types-1440x900.png` (5 regression snapshots total). Save under `.playwright-cli/screenshots-rollout/pr-citas-05-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=CitasNegativeSpaceRulesTest` GREEN (5 negative-space rules asserted).
- [ ] All PR-citas-01..04 test suites GREEN (no regression from consolidation).
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] `git grep` on the full CITAS surface returns zero legacy alias matches.
- [ ] `a11y-followup.md` exists at `openspec/changes/ui-rollout-all-modules-2026-08/categories/citas/a11y-followup.md` with both follow-up rows documented (calendar grid ARIA + textColor luminance resolver).
- [ ] Realtime smoke test documented in PR description (2 tabs on `/calendar`, `AppointmentCreated` within 1 second).
- [ ] PR diff under 400 lines.
- [ ] 5 regression screenshots saved under `.playwright-cli/screenshots-rollout/pr-citas-05-*.png`.

## Out of scope (deferred to follow-up change)

- Calendar grid `role="grid"` + per-cell `aria-label` (OPTIONAL in this PR; tracked in `a11y-followup.md`).
- `CalendarService::getCalendarData` `textColor: '#ffffff'` luminance-based text-color resolver (tracked in `a11y-followup.md`).
- `WorkSchedule` / `AppointmentBlock` / `WaitingList` admin frontend — separate change.
- Treatment-plan CRUD screens — clinical cluster PR6.
- Quotation / billing screens — PAGOS category.
- Patient demographic forms / medical-record content — separate module.
- Dark mode; gradients; new tokens.

## Test plan (commands)

```bash
php artisan test --filter=CitasNegativeSpaceRulesTest
php artisan test --filter=CitasWizardAppShellTest
php artisan test --filter=CitasCalendarAppShellTest
php artisan test --filter=NewAppointmentModalAppShellTest
php artisan test --filter=AppointmentTypesAppShellTest
php artisan test --filter=ConsultationWizardStatusEnumTest
php artisan test --filter=AppointmentPriceFormatterTest
php artisan test --filter=ComposablesStandardizationTest
php artisan test --filter=AppLayoutCanvasRoutesTest
php artisan test --filter=LegacyAliasForbiddenTest
php artisan test --filter=FormatPENLabelTest
pnpm build
pnpm lint:check
git grep -nE "border-theme|bg-success-100|text-accent|bg-error-100|bg-primary-50|hover-lift|disabled:opacity-30|bg-black bg-opacity-50|focus:ring-primary-500 focus:border-accent|bg-accent bg-opacity-5|S/ \$\{n\.toFixed|Intl\.NumberFormat|ConfirmationToken|confirmation_token" \
  resources/js/modules/appointments \
  resources/js/components/appointments \
  resources/js/modules/appointment-types
git grep -nE '\.fc-event|\.fc-daygrid|\.fc-timegrid|\.fc-toolbar' \
  resources/js/modules/appointments/CalendarPage.vue
```

## Key Learnings (forwarded to apply)

1. `CitasNegativeSpaceRulesTest` is the durable regression guard for 5 negative-space decisions (`CITAS-TZ-001`, `CITAS-CONF-001`, `CITAS-RT-001` partial via token redaction, `CITAS-WS-001`, `CITAS-CON-001` partial via no `<script>` edits). Extends `TestCase` (not `ModuleAppShellTestCase`) — it is cross-cutting, not per-module.
2. `a11y-followup.md` records 2 known a11y defects (calendar grid ARIA + `textColor: '#ffffff'` luminance resolver) for the future slice; both are OPTIONAL per `CITAS-A11Y-001`.
3. Per-module `*AppShellTest` files (PR-citas-01..04) can be consolidated into a single `CitasAppShellTest` OR kept separate — designer's call. Either way, the per-module rule set (token reference exists, alias absent, `<style scoped>` absent) is the regression witness.

## References

- `categories/citas/design.md` §3.5 (timezone), §3.6 (conflict round-trip), §3.7 (ConfirmationToken redaction), §3.8 (WorkSchedule/AppointmentBlock prohibition), §4.3 (budget breakdown PR-citas-05 ~180 lines), §6.2 (PR-citas-05 test extensions)
- `categories/citas/spec.md` `CITAS-TZ-001`, `CITAS-CONF-001`, `CITAS-RT-001`, `CITAS-WS-001`, `CITAS-CON-001`, `CITAS-A11Y-001`
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base class)
- `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` (forbidden alias list — extend with `bg-black bg-opacity-50` + `focus:ring-primary-500 focus:border-accent` from PR-citas-03)
- `database/migrations/2025_09_20_082341_create_appointments_table.php` + `2025_10_14_123001_fix_appointments_status_enum.php` (7-value enum source)
- `app/Services/CalendarService.php:101` (hardcoded `textColor: '#ffffff'` — a11y follow-up)
- `CREDENTIALS.md` (`recep@test.com` for realtime smoke; `admin@test.com` for admin visual sweep)
