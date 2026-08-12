# PR-citas-02 — `CalendarPage` tokenisation + 7-value status legend

> **Change**: `ui-rollout-all-modules-2026-08` — CITAS category
> **Date**: 2026-08-12
> **PR scope**: PR-citas-02 only
> **Branch base**: `main` (stacked after PR-citas-01)
> **Review budget**: 400 authored lines / PR (target ~340)
> **Strict TDD**: true

## Goal

Migrate `CalendarPage.vue` (29.7 KB; the hub — Day/Week/Month agenda, FullCalendar hosting slot, "En vivo" WS pill, status legend) to consume the proven primitives: status pills → `<UiStatusBadge variant="...">` for all 7 enum values; raw `bg-green-500 / bg-yellow-500 / bg-red-500` legend dots → `<UiStatusBadge>` ramps; `hover-lift` on appointment blocks → `<UiCard clickable>`; `bg-primary-50` today highlight → `<UiCard variant="elevated">`. Add the 2 missing enum values (`no_show`, `rescheduled`) to the legend, bringing it from 5 to 7 (load-bearing bug fix per `CITAS-CAL-001`). FullCalendar internals (`.fc-event`, `.fc-daygrid`, `.fc-timegrid`, `.fc-toolbar`) NOT overridden. The hardcoded `textColor: '#ffffff'` in `CalendarService::getCalendarData` line 101 stays as-is (existing a11y defect, flagged for future slice). `<script>` block of `CalendarPage.vue` is NEVER touched; `useEcho` `appointments` channel subscription preserved verbatim.

## Depends on

- PR0 (landed): primitives + `canvasRoutes`.
- PR-citas-01 (landed): ConsultationWizard lives on `<UiTabs>` + `<UiInput>` primitives; `CitasWizardAppShellTest` base established.

## Work items (ordered; foundation first, visual last)

- [ ] **T-02.1** — RED: NEW `tests/Unit/DesignSystem/CitasCalendarAppShellTest.php` extending `ModuleAppShellTestCase`. Override `polishedFiles()` to return `CalendarPage.vue`. Add `assertCalendarLegendReferencesAll7EnumValues()` (regex over the legend template: each of `scheduled|confirmed|in_progress|completed|cancelled|no_show|rescheduled` must appear at least once). Run PHPUnit: RED.
- [ ] **T-02.2** — Migrate status pills (current 5 enum values): `bg-success-100 text-success-700` / `bg-error-100 text-error-700` / `bg-warning-100 text-warning-700` patterns → `<UiStatusBadge variant="success|error|warning">` with token-aligned ramps. Variant mapping per design §3.2: `scheduled` → `info`, `confirmed` → `success`, `in_progress` → `warning`, `completed` → `neutral`, `cancelled` → `error`.
- [ ] **T-02.3** — Add the 2 missing legend entries: `no_show` → `<UiStatusBadge variant="neutral">` (with dot, distinct from `completed` via label "No se presentó"); `rescheduled` → `<UiStatusBadge variant="warning">` (no dot, distinct from `in_progress` via label "Reprogramada"). Localised labels added to the legend template.
- [ ] **T-02.4** — Migrate legend dots: REPLACE raw `<span class="bg-green-500 rounded-full">` / `bg-yellow-500` / `bg-red-500` with `<UiStatusBadge variant="..." dot>` tokenised ramps (zero `bg-green-500` / `bg-yellow-500` / `bg-red-500` literals remain).
- [ ] **T-02.5** — Migrate appointment blocks + today highlight: REPLACE `class="hover-lift"` on appointment cards with `<UiCard clickable>` (motion duration `var(--motion-duration-fast) var(--motion-easing-ios)`); REPLACE `bg-primary-50` today highlight with `<UiCard variant="elevated">` wrapping the day-cell; FullCalendar event rendering wrappers consume `<UiStatusBadge>` for inline status pill on each event.
- [ ] **T-02.6** — Migrate view-toggle buttons (Día / Semana / Mes): REPLACE raw `<button class="...">` with `<UiButton :variant="isActive ? 'primary' : 'secondary'" :size="sm">`; "En vivo" WS pill preserved verbatim (no template change to the `<script>` block — the pill renders from existing reactive state).
- [ ] **T-02.7** — Preserve WS pill + filters: confirm `useEcho` `appointments` channel subscription (`.listen(...)` + `echo.leave(...)`) stays byte-for-byte unchanged in `<script>` block; filter chips (`status`, `user_id`, `dental_chair_id`) → `<UiSelect>` (replaces raw `<select class="border-theme">`); date range filter `<input type="date">` → `<UiInput type="date">` + hairline + composed `var(--focus-ring-default)`.
- [ ] **T-02.8** — NEW: create `tests/Unit/DesignSystem/ConsultationWizardStatusEnumTest.php`. Add `test_legend_references_all_7_enum_values()` (regex over `CalendarPage.vue` template — each enum value must appear; order/spacing tolerant). Add `test_status_pills_use_ui_status_badge()` (regex: zero `bg-success-100 text-success-700`, zero `bg-error-100 text-error-700`, `<UiStatusBadge variant="...">` referenced at least 7 times). Run PHPUnit: GREEN.
- [ ] **T-02.9** — Regression: `git grep -nE "hover-lift|bg-primary-50|bg-green-500|bg-yellow-500|bg-red-500|bg-success-100 text-success-700|bg-error-100 text-error-700"` on `CalendarPage.vue` returns zero matches; `git grep -nE '\.fc-event|\.fc-daygrid|\.fc-timegrid|\.fc-toolbar'` on `CalendarPage.vue` `<style>` block returns zero matches (FullCalendar internals NOT overridden).
- [ ] **T-02.10** — Tests: `php artisan test --filter=CitasCalendarAppShellTest` + `--filter=ConsultationWizardStatusEnumTest` + `--filter=CitasWizardAppShellTest` + `--filter=ComposablesStandardizationTest` + `--filter=AppLayoutCanvasRoutesTest` + `--filter=LegacyAliasForbiddenTest` all GREEN.
- [ ] **T-02.11** — Build: `pnpm build` clean; `pnpm lint:check` clean.
- [ ] **T-02.12** — Visual: `playwright-cli` snapshots at 1440x900 — `citas-calendar-week-1440x900.png` (week view), `citas-calendar-legend-1440x900.png` (7-value legend close-up), `citas-calendar-no-show-rescheduled-1440x900.png` (a `no_show` and a `rescheduled` appointment rendered). Mobile: `citas-calendar-390x844.png` (receptionist mobile path). Login: `recep@test.com`. Save under `.playwright-cli/screenshots-rollout/pr-citas-02-*.png`.

## Acceptance criteria

- [ ] `php artisan test --filter=CitasCalendarAppShellTest` GREEN.
- [ ] `php artisan test --filter=ConsultationWizardStatusEnumTest` GREEN.
- [ ] `pnpm build` clean; `pnpm lint:check` clean.
- [ ] Legend template references all 7 enum values via `<UiStatusBadge>` (regex-verified).
- [ ] No `hover-lift`, `bg-primary-50`, raw `bg-green-500` / `bg-yellow-500` / `bg-red-500`, `bg-success-100 text-success-700`, or `bg-error-100 text-error-700` inside `CalendarPage.vue`.
- [ ] No `.fc-event` / `.fc-daygrid` / `.fc-timegrid` / `.fc-toolbar` overrides in `<style>` block.
- [ ] `<script>` block of `CalendarPage.vue` is byte-for-byte unchanged; `useEcho` `appointments` subscription preserved.
- [ ] No regression in `CitasWizardAppShellTest`, `ComposablesStandardizationTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`.
- [ ] PR diff under 400 lines.
- [ ] 4 screenshots saved under `.playwright-cli/screenshots-rollout/pr-citas-02-*.png`.

## Out of scope (deferred)

- `NewAppointmentModal.vue` chrome + duplicate-key 422 mapping — PR-citas-03.
- `AppointmentTypesPage` + `AppointmentTypeDetailPage` admin CRUD triplet — PR-citas-04.
- Cross-cutting tests + a11y follow-up doc — PR-citas-05.
- Calendar grid `role="grid"` + per-cell `aria-label` — a11y follow-up, out of scope (flag in `a11y-followup.md`).
- `CalendarService::getCalendarData` `textColor: '#ffffff'` luminance-resolver — a11y follow-up, out of scope.

## Test plan (commands)

```bash
php artisan test --filter=CitasCalendarAppShellTest
php artisan test --filter=ConsultationWizardStatusEnumTest
php artisan test --filter=CitasWizardAppShellTest
php artisan test --filter=ComposablesStandardizationTest
php artisan test --filter=LegacyAliasForbiddenTest
pnpm build
pnpm lint:check
git grep -nE "hover-lift|bg-primary-50|bg-green-500|bg-yellow-500|bg-red-500" \
  resources/js/modules/appointments/CalendarPage.vue
git grep -nE '\.fc-event|\.fc-daygrid|\.fc-timegrid|\.fc-toolbar' \
  resources/js/modules/appointments/CalendarPage.vue
git diff --stat resources/js/modules/appointments/CalendarPage.vue
playwright-cli screenshot http://localhost:5173/calendar 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-02-calendar-week-1440x900.png
playwright-cli screenshot http://localhost:5173/calendar 1440x900 \
  --out .playwright-cli/screenshots-rollout/pr-citas-02-calendar-legend-1440x900.png
playwright-cli screenshot http://localhost:5173/calendar 390x844 \
  --out .playwright-cli/screenshots-rollout/pr-citas-02-calendar-390x844.png
```

## Key Learnings (forwarded to apply)

1. 7-value status legend variant mapping (`no_show` → `neutral` w/ dot; `rescheduled` → `warning` w/o dot) avoids perceptual collision with existing `completed` (`neutral` w/ dot, different label) and `in_progress` (`warning` w/ dot, different label).
2. FullCalendar internals MUST NOT be overridden — `.fc-*` selectors stay untouched; only the event-content wrapper consumes `<UiStatusBadge>` + `<UiCard>`.
3. `useEcho` `appointments` channel subscription (`.listen(...)` + `echo.leave(...)`) is preserved verbatim; visual smoke test = 2 browser tabs on `/calendar`, create appointment in tab A, verify tab B receives `AppointmentCreated` within 1 second.

## References

- `categories/citas/design.md` §3.2 (7-value status legend decision), §3.7 (Echo channel reuse), §6.2 (PR-citas-02 test extensions)
- `categories/citas/spec.md` `CITAS-CAL-001`, `CITAS-RT-001`, `CITAS-A11Y-001`
- `database/migrations/2025_09_20_082341_create_appointments_table.php` + `2025_10_14_123001_fix_appointments_status_enum.php` (7-value enum source)
- `resources/js/composables/useEcho.js` (`appointments` channel subscription — preserved)
- `CREDENTIALS.md` (`recep@test.com` for calendar)
