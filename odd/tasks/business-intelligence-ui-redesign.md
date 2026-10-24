# Business Intelligence — surface redesign and report-service corrections

Feature branch `feat/business-intelligence-ui-redesign`, worktree `~/proyectos/odontosuite-bi-ui`, base `main@b3c168a`. Scope: `resources/js/modules/business-intelligence/**` (surface) and `app/Services/Reports/**`, `app/Http/Controllers/Api/Reports/**`, `app/Exports/**` (report contract). Deliverable: one redesigned `/business-intelligence` page over a report contract that no longer reports structurally wrong numbers.

## Specs

S1. The user asked: "continuando con este proyecto conforme dejamos el dashboar quiero hacer ahora la interfaz de bussiness intelligence, para hacer ello en un worktree nuevo". The Business Intelligence module gets a full interface redesign, built in a new worktree, reusing the visual language the dashboard and `/procedure-stats` already share.

S2. The user chose the scope: "Interfaz + corregir los servicios". The redesign ships together with real corrections to the report services it reads.

S3. On the public broadcast channel the user delegated the call: "LA MEJOR SOLUCION Y RESPUESTA QUE PUEDAS DAR DE ACUERDO AL CASO Y COMO INGENIERO COMO EXPERIENCIA QUE TIENES Y LAS MEJORES DESICIONES A FUTURO". Engineering decision: BI stops consuming the public `dashboard-updates` channel in this delivery; the private-channel migration of the whole channel is a separate slice because it is shared with the dashboard and 21 events.

## Decisions

D1. ONE definition of money across the page. Revenue is `transactions` with `type = 'payment'` and `status = 'completed'`, within `created_at` inside the applied period. Catalog price of a completed appointment is production, not income, and stops being labeled "Ingresos". Refunds are not netted (documented limit, follow-up).

D2. Revenue is attributed once. A transaction with `appointment_id` belongs to that appointment's professional and chair. A transaction without an appointment stays in the clinic total only and is never redistributed across professionals. `sum(professionalPerformance.revenue) <= totalRevenue` holds by construction.

D3. Period end is inclusive. Every `scheduled_at` / `created_at` range uses `[startOfDay(start), endOfDay(end)]`. The current bare `YYYY-MM-DD` comparison drops the last day.

D4. Availability is physical. Available hours = active chairs (respecting the environment filter) × working days in the period, Monday–Friday × 8 h. No `min(days, 30)` cap and no clinic-wide single resource. Rates are not silently capped; overbooking may legitimately exceed 100% and is shown as such.

D5. Occupancy numerator counts appointments that occupied the chair: every status except `cancelled` and `no_show`.

D6. Patients KPI means "patients seen in the period" (distinct patients with at least one appointment in range). A new `newPatients` metric covers patients registered in the period. The `is_active` patient filter is dropped from every period calculation, so deactivating a patient does not erase the history of a past period.

D7. The professionals report uses the same population as the dashboard (`odontologo`, `implantologo`, `dentista`, `tecnico_dental`, `asistente`) and counts `cancelled` / `no_show` with their own query, not off a collection pre-filtered to `completed`.

D8. Revenue report rows are mutually exclusive groups plus `meta.summary`; the duplicated `Resumen` row inside `data` disappears. The four cuts (type, professional, chair, day) plus the residual describe the SAME money and are alternatives, never rows of one summable column, so the page shows one cut at a time. The already-computed daily cut is exposed as `Por Día`.

D9. Export keeps three honest formats: `excel` = SpreadsheetML-compatible HTML table served as `.xls` (same decision documented in `App\Exports\CashReportExport`), `csv` = real CSV, `pdf` = DomPDF. The `maatwebsite/excel` 1.1 install is Laravel 4-era and unusable; no new dependency is added.

D10. A server error returns a generic message; the exception text goes to the log, never to the browser. An unknown `{reportType}` is a 422, not a 500.

D11. BI does not consume `dashboard-updates`. The page refreshes on demand for the applied period and reports `aria-busy` while loading.

## Tasks

| ID | Specs | Route | Surface | Commit |
| --- | --- | --- | --- | --- |
| T1 | S2 D1 D2 D8 | worker | `app/Services/Reports/RevenueReportService.php` + feature tests | `fix(reports): single money definition and exclusive revenue groups` |
| T2 | S2 D2 D7 | worker | `app/Services/Reports/ProfessionalReportService.php` + tests | `fix(reports): real cancelled and no-show counts per professional` |
| T3 | S2 D1 D3 | worker | `app/Services/Reports/PatientReportService.php` + tests | `fix(reports): period scoped spend from transactions` |
| T4 | S2 D2 D3 D4 D6 | worker | `app/Services/Reports/DashboardService.php` + tests | `fix(reports): period patients, physical availability, single revenue attribution` |
| T5 | S2 D1 D3 D4 D5 | worker | `app/Services/Reports/UtilizationReportService.php` + tests | `fix(reports): utilization over real chair hours` |
| T6 | S2 D9 D10 | worker | `app/Http/Controllers/Api/Reports/ReportController.php`, `app/Exports/`, `resources/views/reports/` + tests | `fix(reports): honest exports and generic error envelopes` |
| T7 | S1 S3 D11 | parent | `resources/js/modules/business-intelligence/BusinessIntelligencePage.vue` | `feat(business-intelligence): dashboard surface recipe for the BI page` |
| T8 | S1 | parent | same file + `tests/js/smoke/business-intelligence*.test.js` | `feat(business-intelligence): charts on the shared chart recipe` |
| T9 | S1 | parent | same file | `feat(business-intelligence): export the applied period through the api client` |
| T10 | S1 | parent | `tests/Unit/DesignSystem/BusinessIntelligenceAppShellTest.php`, `AGENTS.md` if counts change | `test(business-intelligence): design system guard and docs sync` |

Backend worker owns `app/Services/Reports/**`, `app/Http/Controllers/Api/Reports/**`, `app/Exports/**`, `resources/views/reports/**`, `tests/Feature/Reports/**`. The parent owns `resources/js/modules/business-intelligence/**`, `tests/js/smoke/business-intelligence*.test.js`, `tests/Unit/DesignSystem/BusinessIntelligenceAppShellTest.php`, `AGENTS.md`.

## Contract v2 (frontend consumes this)

`GET /api/reports/dashboard` → `data`:

```
totalAppointments:int  totalPatients:int  newPatients:int
totalRevenue:float     occupancyRate:float  usedHours:float  availableHours:float
appointmentsByDay:[{date,count}]  revenueByMonth:[{month,revenue}]
professionalPerformance:[{id,name,appointments,revenue,averagePerAppointment}]
```

`GET /api/reports/{appointments|patients|professionals|utilization}` → `{data:[...], columns:[{key,label}], meta:{message}}` (unchanged shape).

`GET /api/reports/revenue` → `{data:[...], columns:[...], meta:{summary:{appointments_count,total_revenue,average_per_appointment,period}}}`; `data[].type ∈ {Por Tipo de Cita, Por Profesional, Por Ambiente, Por Día, Sin cita asociada}`.

`GET /api/reports/{reportType}/export?format=excel|csv|pdf` → file download; unknown `reportType` → 422.

## Log

L1 (verbatim): "continuando con este proyecto conforme dejamos el dashboar quiero hacer ahora la interfaz de bussiness intelligence, para hacer ello en un worktree nuevo"

L2 (verbatim): "Interfaz + corregir los servicios"

L3 (verbatim): "LA MEJOR SOLUCION Y RESPUESTA QUE PUEDAS DAR DE ACUERDO AL CASO Y COMO INGENIERO COMO EXPERIENCIA QUE TIENES Y LAS MEJORES DECISIONES A FUTURO"

E1 (measured, 2026-10-20): `ProfessionalReportService.php:26-31` eager-loads appointments filtered to `status='completed'`, then `:50-51` counts `cancelled` and `no_show` on that collection: both are always 0.

E2 (measured): `DashboardService.php:255-266` computes available hours as `min(days,30)*8` for the whole clinic, so occupancy can exceed 100%; `:422-445` attributes appointment-less transactions to every professional who saw that patient, and falls back to catalog price, so professional revenue can exceed the clinic total.

E3 (measured): `RevenueReportService.php:44` computes `getDailyRevenue()` and never returns it; `:47-100` mixes a `Resumen` row with the groups that already add up to it.

E4 (measured): `RevenueReportService`, `UtilizationReportService:83-85` and `PatientReportService:45-47` price revenue from `appointment_types.price`, contradicting the transaction-based `totalRevenue` on the same screen.

E5 (measured): `AppointmentReportService.php:70` maps `in_consultation`, a status that does not exist in `appointments` (`scheduled, confirmed, in_progress, completed, cancelled, no_show, rescheduled`).

E6 (measured): `ReportController.php:339-347` serves CSV bytes as `application/vnd.ms-excel` with an `.xls` filename; `:77` and siblings return `$e->getMessage()` to the client.

E7 (measured): `BusinessIntelligencePage.vue` renders `Debug: N profesionales cargados` (`:78`, `:117`), keeps the previous table on a failed tabular load (`:643-647`), bypasses `useApi` for export (`:788-800`), subscribes to eleven events of the public `dashboard-updates` channel, and carries `<style scoped>` plus `border-theme` classes that the design-system guards forbid.

E8 (independent audit, 2026-10-20): all thirteen checked requirements satisfied; no monetary defect. Three minor findings: (a) `DashboardService` availability for an explicitly filtered inactive chair is non-zero, `UtilizationReportService` handles it correctly; (b) the dashboard performance table filters `User::is_active` while the professionals report does not, so the populations differ; (c) the design-system guard pins a few exact strings, acceptable for a visual guard. Decision: (a) and (b) are fixed in this feature, (c) stays.

E9 (JS baseline, 2026-10-20): `main@b3c168a` fails 9 smoke files that never collect (`appointments`, `dashboard-kpis`, `dashboard-pending`, `dashboard-section-states`, `environments`, `environments-detail`, `patients-detail-redesign`, `patients-list-redesign`, `professionals`). The feature worktree fails the same set plus a transient timeout in `business-intelligence-reports.test.js`, which passes 8/8 when run alone. No BI regression; the pre-existing collection failures are out of scope and tracked below.

## Follow-ups (out of scope, not done here)

F1. Migrate `dashboard-updates` to a private channel authorized by role (21 events, dashboard and BI). BI no longer subscribes; the dashboard still does.

F2. Net refunds against collected revenue. Today only `type = payment` is summed, so a refund never reduces income.

F3. `maatwebsite/excel` 1.1 is Laravel 4-era and unusable; Excel exports use the SpreadsheetML HTML path already established by `App\Exports\CashReportExport`. Removing the dead dependency is its own chore.

F4. Nine JS smoke files fail to collect on `main`; they need their own slice.

## Delivery

Branch `feat/business-intelligence-ui-redesign`, six commits on top of `main@b3c168a`:

| Commit | Contents | Lines |
| --- | --- | --- |
| 40521ce | Revenue money definition, exclusive cuts, period trait, test base + revenue contract | ~700 |
| 16a5894 | Dashboard availability/attribution/patients, utilization hours + contract | ~450 |
| b2674f1 | Professional counters, patient spend, appointment status vocabulary + contract | ~450 |
| fc8954d | Export formats, 422 report type, generic 500 + export contract | ~450 |
| 0b0fd3f | BI page redesign, `useApi.download`, `transaction.updated` consumer, three JS suites | ~2900 |
| 144b18b | Design-system guard, AGENTS.md changelog | ~730 |

Slice boundary: the whole candidate is 6142 authored lines, past one review slice, so delivery is stacked rather than one PR. Proposed split, each reviewable on its own:
1. Report contract corrections: `40521ce`, `16a5894`, `b2674f1`, `fc8954d`.
2. Redesigned surface: `0b0fd3f`.
3. Guard and docs: `144b18b`.

Verification at this boundary: PHP MySQL suite 1472 tests / 6667 assertions green; BI JS suites 19/19; `cash-register` smoke green; ESLint, Prettier and Pint clean; `vite build` green; `sdd:check-events` 33 events / 0 orphans.

## Delivered

Merged on 2026-10-20 into `main`:
- PR #187 `dc8d3a8` — report contract corrections (4 commits).
- PR #188 `13730a3` — redesigned BI surface, `useApi.download`, `transaction.updated` consumer.
- PR #190 `8880089` — design-system guard and AGENTS.md changelog.

Superseded and closed: PRs #180, #181, #182 (pre-fix stack) and #189 (auto-closed when its base branch was deleted). Worktree removed, all feature branches deleted locally and on the remote, MySQL test container on port 3310 removed.

Post-merge verification on `main`: BI vitest 19/19, `sdd:check-events` 33 events / 0 orphans, guard test present, AGENTS.md changelog entry present. CI green on all three PRs (Quality gates, Backend tests MySQL, Frontend build).

Open follow-ups: #183 private broadcast channel, #184 refunds, #185 dead excel dependency, #186 nine JS smoke files that never collect.
