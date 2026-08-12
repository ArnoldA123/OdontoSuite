# Dashboard Period Comparisons — Specification

## Purpose

Adds period-aware comparison data and omission rules to the dashboard KPI response so the three cards that benefit from a delta chip ("Citas Hoy", "Pacientes", "Total Citas") can render an honest, calendar-safe delta. Two KPI cards (Profesionales, Estado de Caja) carry no comparison. Backend shape is strictly additive: the comparison lives under a separate `data.comparisons` block and never mutates the existing scalar fields.

## ADDED Requirements

### Requirement: Comparison data lives under `data.comparisons.<stat>` as an additive block

The dashboard `stats` response MUST include a top-level `data.comparisons` object whose keys are `appointments_today`, `total_patients`, and `total_appointments_this_month`. Each value MUST be an object of the shape `{ current, previous, period_label, delta_label }`. Existing scalar fields (`data.appointments_today`, `data.total_patients`, `data.total_appointments_this_month`, `data.total_professionals`, `data.cash_session`) MUST remain the same type and value they were before this change.

#### Scenario: Existing scalar fields remain unchanged

- GIVEN the dashboard `stats` response was previously delivered
- WHEN the new controller runs against the same fixtures
- THEN `data.appointments_today` is still an integer (NOT an object)
- AND `data.total_patients` is still an integer
- AND `data.total_appointments_this_month` is still an integer
- AND `data.comparisons` is present alongside them, not nested inside them

### Requirement: Citas Hoy compares against the same weekday last week

`data.comparisons.appointments_today` MUST have `current` equal to the live `data.appointments_today` integer, `previous` equal to the count of appointments scheduled on the same weekday (Mon..Sun) seven days before `Carbon::today()` in the application timezone, scoped by `branch_id` when supplied.

#### Scenario: Previous weekday has rows

- GIVEN today is Wednesday 2026-08-12, `data.appointments_today` equals `7`, and the previous Wednesday 2026-08-05 had 4 appointments in branch 1
- WHEN the client requests `GET /api/dashboard/stats?branch_id=1`
- THEN `data.comparisons.appointments_today.current` equals `7`
- AND `data.comparisons.appointments_today.previous` equals `4`
- AND `data.comparisons.appointments_today.period_label` equals `"vs mié 5 ago"`

#### Scenario: First day of week (Monday) compares to prior Monday

- GIVEN today is Monday 2026-08-11, Sunday 2026-08-10 had 0 appointments
- WHEN the client requests the stats
- THEN `data.comparisons.appointments_today.previous` counts appointments on `2026-08-04` (the prior Monday), NOT `2026-08-10`
- AND `data.comparisons.appointments_today.period_label` reads `"vs lun 4 ago"`

### Requirement: Pacientes comparison is about new registrations, not the cumulative headline

The headline `data.total_patients` integer MUST continue to mean the cumulative count of active patients; this requirement pins that contract and the headline MUST NOT change meaning or value because of the comparison. The comparison for "Pacientes" is a separate quantity: NEW registrations in the current month vs the previous month, derived from `Patient.created_at` and scoped by `branch_id` when supplied.

#### Scenario: Cumulative headline is preserved

- GIVEN the cumulative active-patient count is `105` for branch 1
- WHEN the new controller runs
- THEN `data.total_patients` equals `105`
- AND `data.comparisons.total_patients.current` equals the count of NEW registrations in the current month (NOT `105`)
- AND `data.comparisons.total_patients.previous` equals the count of new registrations in the previous month
- AND `data.comparisons.total_patients.period_label` equals `"nuevos este mes"`
- AND `data.comparisons.total_patients.delta_label` is rendered as an ABSOLUTE figure (e.g. `"+12"`), NEVER as a percentage of `105`

### Requirement: Total Citas compares month-to-date vs the same day span of the previous month

`data.comparisons.total_appointments_this_month` MUST have `current` equal to the live `data.total_appointments_this_month` integer, `previous` equal to the count of appointments scheduled from `2026-(prevMonth)-01` through the calendar day that corresponds to today's day-of-month, inclusive. The previous span MUST end on the same day-of-month as today, never on the last day of the previous month.

#### Scenario: Day 12 of a 31-day month compares against days 1-12 of the previous month

- GIVEN today is 2026-08-12, `data.total_appointments_this_month` equals `42`, and there were 9 appointments scheduled from 2026-07-01 through 2026-07-12
- WHEN the client requests the stats
- THEN `data.comparisons.total_appointments_this_month.previous` equals `9`
- AND appointments from 2026-07-13 through 2026-07-31 are NOT counted

#### Scenario: Day 31 of August does not silently include nonexistent day 30 of February

- GIVEN today is 2026-08-31 (and the test re-runs against 2026-02-28)
- WHEN the same controller resolves "same day span"
- THEN the previous span ends on the last day of February (`2026-02-28`), not on `2026-02-30`
- AND `data.comparisons.total_appointments_this_month.previous` is a finite non-negative integer

### Requirement: Profesionales and Estado de Caja carry no comparison

`data.comparisons` MUST NOT contain a `total_professionals` or `cash_session` key for any request, regardless of data availability.

#### Scenario: Both omit comparison entries

- GIVEN any request
- WHEN the stats response is rendered
- THEN `data.comparisons.total_professionals` is absent
- AND `data.comparisons.cash_session` is absent

### Requirement: Omission is expressed as `delta_label: null`, not as object absence

The comparison object MUST always be returned for the three compared stats. Omission MUST be expressed by setting `delta_label: null`. The controller MUST emit `delta_label: null` when `previous === 0` OR `previous === null`. The controller MUST NOT emit `delta_label: null` (and MUST render a real delta) when `current === 0` and `previous > 0`. Because the client never divides, `Infinity`/`NaN`/`100%` are structurally impossible rather than merely forbidden.

#### Scenario: Previous weekday had zero appointments

- GIVEN today is Wednesday 2026-08-12, current is `7`, and the previous Wednesday 2026-08-05 had 0 appointments
- WHEN the client requests the stats
- THEN `data.comparisons.appointments_today` is present
- AND `data.comparisons.appointments_today.previous` equals `0`
- AND `data.comparisons.appointments_today.delta_label` is `null`
- AND `data.comparisons.appointments_today.period_label` is still a non-null string (e.g. `"vs mié 5 ago"`)

#### Scenario: Previous count is zero and current is positive (registrations case)

- GIVEN current-month registrations is `5` and the previous-month registrations count is `0`
- WHEN the response is rendered
- THEN `data.comparisons.total_patients.delta_label` is `null`
- AND no card renders `+Infinity%` or `+100%`

#### Scenario: Current is zero and previous is positive — delta MUST render

- GIVEN current-month registrations is `0` and previous-month registrations is `5`
- WHEN the response is rendered
- THEN `data.comparisons.total_patients.delta_label` is NOT null
- AND the chip renders the absolute drop (e.g. `"-5"`)
- AND `data.comparisons.total_patients.period_label` is the same `"nuevos este mes"` string used in the positive case

### Requirement: All numeric comparison fields are finite

`data.comparisons.*.current`, `.previous`, and `.delta_label` (when not null) MUST each be a finite integer or a finite string-encoded number. No `Infinity`, `NaN`, `null` (other than `delta_label`), or non-numeric content MAY appear.

#### Scenario: Finite-only guard

- GIVEN any input
- WHEN the controller emits the comparison block
- THEN every numeric field is finite
- AND `delta_label` is either `null` or a finite numeric string

### Requirement: Inclusive range semantics use the application clinic timezone

All "previous period" ranges MUST be computed against the application's configured timezone (the same timezone used for `Carbon::today()` today). The day window is `[00:00:00, 23:59:59.999999]` inclusive at both ends in that timezone, NOT UTC.

#### Scenario: DST transition does not drop a day

- GIVEN the application timezone is `America/Lima` and today is the day after a DST-equivalent clock shift in another timezone
- WHEN the previous-weekday range is resolved
- THEN the range still contains exactly 24 hours of local clock time
- AND the count is consistent with a manual SQL `WHERE DATE(...) = ?` against `scheduled_at` in the same timezone

#### Scenario: Month boundary inclusivity

- GIVEN today is 2026-09-01 (the first day of September)
- WHEN the "Total Citas" comparison resolves the previous-month day span
- THEN the previous span is `[2026-08-01 00:00:00, 2026-08-01 23:59:59.999999]` (single day)
- AND `data.comparisons.total_appointments_this_month.period_label` reads `"vs ago 1 (1 día)"`

### Requirement: API shape remains strictly additive

Existing dashboard response consumers MUST continue to parse the response without modification. The new `data.comparisons` block is a sibling of the existing scalar fields, never nested inside them. Older clients simply ignore `data.comparisons` because they read the scalar fields they already knew.

#### Scenario: Old client ignoring new block

- GIVEN a build from before this change
- WHEN it consumes the new response
- THEN it parses every previously documented scalar field unchanged
- AND no field name was renamed, repurposed, or had its type changed (no integer turned into object)

## Out of Scope (recorded decisions)

| Item | Decision | Reason |
|---|---|---|
| Sparkline per KPI card | Deferred | No per-day time series is exposed by the backend yet; would need a new endpoint, schema, cache strategy |
| Percentage on the "Pacientes" chip | Rejected as percentage of the cumulative headline | The comparison quantity (new registrations) is independent from the headline (cumulative active patients); rendering it as a percentage against `105` would be a product regression and a category error |
| Two-tone numeral treatment on KPI figures | Deferred (REVERSIBLE, pending user override) | In these cards the number IS the clinical datum and fading trailing digits degrades legibility for zero comprehension gain; the treatment works in the reference dashboards only because their numerals are decorative marketing copy |

## Verification surface

Every requirement above MUST be checkable by one of: a PHPUnit source-assertion test or a PHPUnit Feature test (PHPUnit is the only available test runner per the project config). Playwright visual checks are scoped to the `premium-design-foundation` spec.
