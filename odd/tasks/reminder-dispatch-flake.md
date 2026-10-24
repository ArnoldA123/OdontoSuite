# Reminder dispatch flake (#206)

Feature: stabilize `tests/Feature/Modules/ReminderDispatchTest.php` so the queue-window cases stop failing under CPU load.
Branch: `fix/reminder-dispatch-flake` · Issue: #206 · Risk: none (test-only).

## Specs

S1. "Anclar el caso a una referencia estable: cita en `now()->addHours(24)->addMinutes(5)` o congelar el reloj (`Carbon::setTestNow`) antes de crear la cita."

S2. "Revisar el resto del archivo por la misma ventana (los casos 48h/72h comparten la resta)."

S3. "Es un falso rojo dependiente de la latencia: no hay regresión de producto, pero rompe CI de forma intermitente y erosiona la confianza en el gate."

## Tasks

| ID  | Spec | Route    | Commit   |
| --- | ---- | -------- | -------- |
| T1  | S1-S2 | inline  | a7d4af0 |
| T2  | S3   | inline  | a7d4af0 |

T1 — Freeze the clock for the whole file (`Carbon::setTestNow` in `setUp`, release in `tearDown`) so every case derives from a fixed instant, including the 48h case that shares the same subtraction.
T2 — Prove the latency dependency is gone: old test fails with a >1s delay (RED), fixed test stays green with the same delay, then remove the delay and run the file plus the full MySQL suite.

## Log

L1. User request (verbatim): "quiero hacer issues #183 #184 #185 #186 #206, en un worktree nuevo".
L2. Grouping decision (user): five branches, five PRs, one per issue. #206 goes first.
L3. Baseline: `app/Services/ReminderService.php:119` already grants a 1-second grace period, so only a >1s stall drops the row; the test anchored at exactly `now()+24h` has zero margin.
L4. RED (deterministic): with a temporary `usleep(1_100_000)` between fixture creation and the service call, `test_24h_reminder_creates_one_schedule_at_minus_one_hour` failed with the exact reported message (`Failed asserting that 0 is identical to 1`); the other three cases passed.
L5. GREEN under the same stall once the clock was frozen in `setUp` (`Carbon::setTestNow('2026-03-10 09:00:00')`, released in `tearDown`): 4/4. The `usleep` probe was then removed.
L6. Scope boundary: no production code changed. `ReminderService` keeps its 1s grace period; the defect was the test's zero-margin anchor, not the service contract.
