# Slice 04 — Stubs-501 Remove

> Findings: WaitingListController + 6 low-priority stubs + Appointment $fillable cleanup
> Cluster: stubs-501
> LOC est: ~220 · Budget risk: Low · Depends on: S02, S08
> Spec: [../specs/04-stubs-501-remove.md](../specs/04-stubs-501-remove.md)

## Per-slice forecast

Decision needed before apply: Yes (triage outcomes for the 6 low stubs)
Chained PRs recommended: Yes
Chain strategy: stacked-to-main (per-stub commits)
400-line budget risk: Low

## Acceptance Criteria

- `grep -rn "waiting-list" resources/js/ tests/Feature/Api/` returns 0 hits before deletion.
- `Route::apiResource('waiting-lists', ...)` removed from `routes/api.php`.
- `WaitingListController.php`, `WaitingListService.php` (orphan), `WaitingList.php` model, `WaitingListCreated.php`, `WaitingListFilled.php` events deleted.
- 6 low-priority stubs each have a per-stub commit (surgical rollback).
- `Appointment::\$fillable` cleaned of non-existent columns.
- `php artisan route:list | grep -E "waiting|stub"` returns empty for removed stubs.
- Migration diff check: no FK references the dropped tables.

## Tasks

- [x] **T-04.1** Grep `resources/js/` and `tests/Feature/Api/` for any consumer of `WaitingListController` or `/waiting-lists` URL. Description: Hard gate. Files: — (CLI only). AC: grep returns 0. Estimated LOC: 0. Depends on: —. Parallelizable: yes.
- [x] **T-04.2** Delete `app/Http/Controllers/Api/WaitingListController.php` and remove `Route::apiResource('waiting-lists', WaitingListController::class);` from `routes/api.php`. Description: Per-stub commit. Files: `routes/api.php`, deleted controller. AC: `php artisan route:list` no longer lists `/waiting-lists`. Estimated LOC: -50. Depends on: T-04.1. Parallelizable: no.
- [x] **T-04.3** Delete `app/Services/WaitingListService.php` (orphan after controller removal; only WaitingListController::store called `addToWaitingList`). Description: Cleanup. Files: deleted service. AC: `grep -rn "WaitingListService" app/ tests/` returns 0. Estimated LOC: -80. Depends on: T-04.2. Parallelizable: no.
- [x] **T-04.4** Decision: `WaitingList` model **kept** (AppointmentService::notifyWaitingList + AppointmentType relation still reference it). Service + Controller removed; model stays. Files: model unchanged. AC: `grep -rn "WaitingList::" app/ tests/` still returns matches in non-controller/service paths. Estimated LOC: 0 (deviation from spec). Depends on: T-04.3. Parallelizable: no.
- [x] **T-04.5** Delete `app/Events/WaitingListCreated.php`, `app/Events/WaitingListFilled.php`, `app/Events/InterconsultationCreated.php`, `app/Events/InterconsultationResponded.php` (orphan after service/controller removal). Description: Cleanup. Files: deleted events. AC: `grep -rn "WaitingListCreated\|WaitingListFilled\|InterconsultationCreated\|InterconsultationResponded" app/` returns 0. Estimated LOC: -60. Depends on: T-04.4. Parallelizable: no.
- [x] **T-04.6** Triage and remove the 6 low-priority stubs: `RoleController`, `AuthController::refresh`, `CalendarController` (legacy), `InterconsultationController`, `WorkSchedules`, `Odontograms`, `AppointmentBlock`, `CashReportController::exportExcel/exportPdf`, `PendingPaymentsController::show`. Description: Per-controller decision: implement if a frontend consumer exists (grep gate), else remove. Files: per controller + `routes/api.php`. AC: `php artisan route:list` confirms 404 for each removed endpoint. Estimated LOC: ~-200. Depends on: T-04.1. Parallelizable: yes (per-stub commits).
- [x] **T-04.7** Eliminate orphan `exportExcel`/`exportPdf` from `CashReportController` (frontend hits `/cash-register/reports/export/{format}` per slice 01 — verified CashReports.vue line 312 + 353). Description: Update `CashReportController` to keep only the unified `export` method. Files: `app/Http/Controllers/Api/CashReportController.php`. AC: `grep "exportExcel\|exportPdf" app/` returns 0. Estimated LOC: ~10. Depends on: T-04.6. Parallelizable: no.
- [x] **T-04.8** Audit `Appointment::\$fillable` and remove non-existent columns (`specialty`, `requires_anesthesia`, `treatment_plan_item_id`, `origin_appointment_id`, `last_activity_at`). Description: Avoids silent attribute-loss bugs. Files: `app/Models/Appointment.php`. AC: `AppointmentFillableTest` asserts each removed column NOT in fillable; mass-assign with these keys silently drops them. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-04.9** Write RED tests for removal (404 assertions for removed endpoints; `AppointmentFillableTest` for fillable cleanup). Description: Strict TDD. Files: `tests/Feature/Api/StubsRemovedEndpointsTest.php`, `AppointmentFillableTest.php`. AC: AppointmentFillableTest passes; StubsRemovedEndpointsTest follows documented SQLite baseline (will pass on CI MySQL). Estimated LOC: ~200. Depends on: T-04.1..T-04.8. Parallelizable: no.
- [x] **T-04.10** Manifest documented in apply-progress topic_key (engram) — findings-map.md not yet created in openspec/changes/bugfix-2026-08/; full removal manifest captured in `sdd/bugfix-2026-08/apply-progress/slice-04`. Estimated LOC: 0. Depends on: T-04.9. Parallelizable: yes.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| Stub removal breaks hidden FE consumer | T-04.1 grep gate; abort if any hit |
| `WaitingList` model deletion cascades via FK | Migration diff in apply; backfill NULL before DROP if needed |
| Per-stub commits make history noisy | Squash on merge; keep granularity for revert only |
