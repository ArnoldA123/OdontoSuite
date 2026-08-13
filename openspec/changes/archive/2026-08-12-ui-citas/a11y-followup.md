# CITAS a11y Follow-up — `ui-rollout-all-modules-2026-08`

> **Tracking**: This file tracks 2 known accessibility defects in the
> CITAS rollout that are flagged for a future a11y slice. The PR-citas-05
> apply phase did NOT introduce fixes in this rollout; both rows are
> marked OPTIONAL per `CITAS-A11Y-001` in
> `categories/citas/spec.md` §2.
>
> **Date opened**: 2026-08-12
> **Change**: `ui-rollout-all-modules-2026-08`
> **Category**: CITAS (Calendario, ConsultationWizard, NewAppointmentModal, Tipos de cita)
> **Follow-up owner**: future a11y slice (separate change)

## 1. Calendar grid ARIA roles (`role="grid"` + per-cell `aria-label`)

### Defect

The week/day/month views in `CalendarPage.vue` use plain `<div>` grids
with no ARIA role. Screen reader users cannot navigate the calendar
efficiently ("Tuesday 9 AM, Tuesday 10 AM"); the day-view hour rows
have no row headers, and the appointment blocks have no cell-level
labels.

### Spec row

`CITAS-A11Y-001` in `categories/citas/spec.md` §2:
> The system SHOULD add `role="grid"` plus per-cell `aria-label` on the
> day/week/month views in `CalendarPage.vue` so screen readers can
> navigate "Tuesday 9 AM, Tuesday 10 AM" efficiently.

### Proposed fix

1. Add `role="grid"` to the day-view container (line 123 of
   `CalendarPage.vue` — the `<div v-else-if="currentView === 'day'" class="p-6">`).
2. Add `role="row"` to the hour row (line 125 — the `<div v-for="hour in dayHours" ...>`).
3. Add `role="gridcell"` to each appointment block (line 130 — the
   `<div v-for="appointment in getAppointmentsForHour(hour)" ...>`).
4. Add `aria-label="<dayLabel> <hourLabel> <type> <patient> <duration>"`
   per appointment cell, computed via a `getAppointmentAriaLabel(appointment)`
   helper in the `<script>` block.
5. Add `aria-label` for empty hour cells ("Tuesday 9 AM, no appointments")
   so screen readers still announce the slot.

### Why deferred

- The PR-citas-05 budget was restricted to cross-cutting tests + this
  follow-up doc; the a11y implementation requires touching
  `CalendarPage.vue` `<template>` + `<script>` blocks (CITAS-CON-001
  preservation requires a careful change).
- The fix is mechanical but spread across 3 view modes (day / week /
  month); the day view is the load-bearing one, the week/month views
  need a different `role="grid"` strategy (column headers + row
  headers).
- The a11y follow-up is OPTIONAL per `CITAS-A11Y-001`; no test fails
  if not introduced in this rollout.

### Acceptance criteria (future slice)

- `CalendarPage.vue` day view: `role="grid"` on the container, `role="row"`
  on hour rows, `role="gridcell"` on appointment blocks, `aria-label`
  computed per cell.
- `CalendarPage.vue` week view: `role="grid"` on the outer grid, `role="row"`
  on day rows, `role="columnheader"` on day + hour headers,
  `role="gridcell"` on each day/hour cell.
- `vue-axe` / `axe-core` automated audit: zero critical a11y violations
  on `/calendar` route.
- Manual NVDA + VoiceOver smoke test: user can navigate "Tuesday 9 AM"
  → "Tuesday 10 AM" with arrow-key semantic navigation.

## 2. `CalendarService::getCalendarData` hardcoded `textColor: '#ffffff'` color-contrast defect

### Defect

`app/Services/CalendarService.php` line 101 hardcodes
`textColor: '#ffffff'` for every appointment block regardless of the
`appointmentType->color` background. When the appointment type's color
is light (e.g. `#FFFFE0` or `#FFE4B5`), the white text is unreadable
(WCAG AA contrast ratio failure: 1.05:1 vs the required 4.5:1).

### Spec row

`CITAS-A11Y-001` in `categories/citas/spec.md` §2 (out-of-scope explicit list):
> `CalendarService::getCalendarData` `textColor: '#ffffff'` color-contrast
> defect — existing a11y defect; flagged for future slice

### Proposed fix

1. Replace the hardcoded `'#ffffff'` with a luminance-based resolver:
   ```php
   $textColor = self::resolveTextColor($appointmentType->color);
   ```
2. The resolver should compute the relative luminance of the background
   hex (per WCAG 2.x), and pick `#ffffff` if luminance < 0.5, else
   `#1f2937` (the dark-text colour that pairs with the `surface`
   ramp).
3. Unit-test the resolver at the four corner cases (pure white,
   pure black, light pastel, mid-saturation) to lock the threshold.

### Why deferred

- The fix is a backend change to `CalendarService::getCalendarData`
  (1 line + a helper), but the helper is a NEW public method that
  needs unit-test coverage.
- The PR-citas-05 scope is UI-only cross-cutting tests; the backend
  CalendarService change is a separate concern tracked in the
  `out-of-scope explicit list` of the CITAS spec.

### Acceptance criteria (future slice)

- `CalendarService::resolveTextColor(string $hex): string` returns
  `#ffffff` for dark backgrounds and `#1f2937` for light backgrounds.
- Unit test covers 4 corner cases (pure white, pure black, light
  pastel `#FFFFE0`, mid-saturation `#FFA500`).
- axe-core contrast audit on `/calendar` week view: zero violations
  on the appointment block text.

## 3. References

- `categories/citas/spec.md` §2 (`CITAS-A11Y-001`)
- `categories/citas/spec.md` §3 (out-of-scope explicit list)
- `categories/citas/design.md` §6.2 (PR-citas-05 follow-up rows)
- `app/Services/CalendarService.php` line 101 (the color-contrast defect)
- `resources/js/modules/appointments/CalendarPage.vue` (the ARIA roles defect)
- `CitasNegativeSpaceRulesTest` (cross-cutting negative-space guard
  for the 5 CITAS rollout rules)

---

*End of a11y follow-up.*
