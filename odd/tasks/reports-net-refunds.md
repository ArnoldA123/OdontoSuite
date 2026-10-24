# Net refunds in Business Intelligence (#184)

Feature: one money rule for every BI report — completed payments minus completed refunds inside the period, with the refunded amount always reported next to the net figure.
Branch: `fix/reports-net-refunds` · Issue: #184 · Risk: item 3 (report contract others consume) and item 1 (money figures).

## Specs

S1. "Los reportes muestran el neto o el bruto con devoluciones explícitas, coherente en KPI, cortes y export."

S2. "Tests de contrato cubriendo venta + devolución en el mismo período."

S3. "Decidir si la devolución se resta del total o se muestra como línea separada; ambas son defendibles, pero tiene que ser una sola regla en toda la pantalla."

S4. Product decision (user, 2026-10-20): net rule — `total_revenue` / `total_spent` is `payments − refunds`; the same payload carries `refunds` as a positive amount; refunds are attributed with the same key as payments (appointment → professional/chair/day/type, otherwise the residual row); `net + refunds` is the gross collected and can never be negative.

## Contract

- Money types: `payment` adds, `refund` subtracts. Both require `status = completed` and `created_at` inside `[startOfDay(start_date), endOfDay(end_date)]`.
- Keys exposed per money row: `total_revenue` (net), `refunds` (positive). Patient report keeps its key: `total_spent` (net) + `refunds`.
- Dashboard payload: `totalRevenue` (net), `totalRefunds` (positive), `revenueByMonth[].revenue` (net) + `.refunds`, `professionalPerformance[].revenue` (net) + `.refunds`.
- Revenue summary: `{appointments_count, total_revenue, refunds, average_per_appointment, period}`; average is net over completed appointments (the denominator does not move).
- Revenue cut rows: `{type, category, appointments_count, total_revenue, refunds, average_per_appointment}`; `appointments_count` counts appointments with payments (a refund-only appointment never inflates the denominator); rows order by net desc; the residual group is emitted when net or refunds differ from the attrib
uted total.
- Column labels: `total_revenue` → "Ingresos netos (S/)", `refunds` → "Devoluciones (S/)", `total_spent` → "Total Neto (S/)".
- Export: the summary row is key-driven (never positional) and carries the refunds figure.

## Tasks

| ID  | Spec    | Route  | Commit  |
| --- | ------- | ------ | ------- |
| T1  | S1-S3   | writer | pending |
| T2  | S2      | writer | pending |
| T3  | S1      | inline | pending |

T1 — Shared money expressions in `app/Services/Reports/Concerns/ResolvesMoneyTotals.php`; apply to `DashboardService`, `RevenueReportService`, `ProfessionalReportService`, `PatientReportService`, `UtilizationReportService`.
T2 — Contract tests for sale + refund in the same period in every report, plus the unlinked-refund residual and the export column.
T3 — BI surface: KPI strip, revenue summary and chart labels show net with explicit refunds.

## Log

L1. User request (verbatim): "quiero hacer issues #183 #184 #185 #186 #206, en un worktree nuevo".
L2. Grouping decision (user): five branches, five PRs, one issue per PR.
L3. Rule decision (user): "Neto: cobros − devoluciones, con devolución explícita".
L4. Test database for this worktree: `odontosuite_backlog_test` (never `odontosuite_test`: other agents use it).
L5. T1 + T2 implemented in this worktree by the writer, no commits (the parent owns git).
RED before the production change, `php artisan test --configuration=phpunit.mysql.xml tests/Feature/Reports`: 8 failed / 5 passed (75 assertions) — dashboard key set without `totalRefunds`, revenue summary key set without `refunds`, refunds still excluded from revenue/professionals/patients/utilization, missing `Devoluciones (S/)` column and positional export summary row.
GREEN after: same command, 13 passed (293 assertions). `vendor/bin/pint --test` on the touched paths: PASS.
L6. Out-of-surface follow-up: `tests/Feature/Modules/BusinessIntelligenceRenderTest.php` pins the old revenue summary key set (`appointments_count, total_revenue, average_per_appointment, period`) and now fails against S4, which adds `refunds`. It needs a one-line update; the writer's edit surfaces stop at `tests/Feature/Reports/**`.
L7. T3 stays pending: the BI surface lives in `resources/js` and the frontend is out of the writer's edit surfaces.

L5. RED (worker, contract tests first): `php artisan test --configuration=phpunit.mysql.xml tests/Feature/Reports` → 8 failed, 5 passed.
L6. GREEN after the trait + five services + key-driven export: 13 passed (293 assertions).
L7. Slice boundary: this PR is one contract change (745 authored lines, ~400 of them contract tests). Splitting services from their tests would ship the rule without its proof, so the slice is the whole issue; the surface change is the second commit.
L8. Parent follow-ups: chronological order restored for the "Por Día" cut (it feeds a time series), the legacy `BusinessIntelligenceRenderTest` summary key set updated, and the obsolete D2 `sum(performance) <= total` assertion replaced by the exact `total = performance + unlinked` reconciliation.
