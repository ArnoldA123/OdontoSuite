# Slice 03 — Stubs-501 Implement

> Findings: ReminderController / ReminderTemplateController / ReminderProvider stubs
> Cluster: stubs-501
> LOC est: ~390 · Budget risk: Low · Depends on: S02
> Spec: [../specs/03-stubs-501-implement.md](../specs/03-stubs-501-implement.md)

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low

## Acceptance Criteria

- `ReminderController::index/store/show/update/destroy` return real responses (not 501).
- `ReminderTemplateController` full CRUD implemented.
- `ReminderProvider` registered in `routes/console.php` on hourly schedule.
- Channel whitelist enforced: `['sms','email','whatsapp','push']` → unknown returns 422.
- Status machine `pending → queued → sent|failed` enforced.
- Feature + integration tests green.
- No listener must propagate failures into the request lifecycle.

## Tasks

- [x] **T-03.1** Implement `ReminderController@index` (delegates to `ReminderService::paginate`). Description: Replace 501 stub. Files: `app/Http/Controllers/Api/ReminderController.php`. AC: Feature `ReminderControllerTest::test_index` green. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-03.2** Implement `ReminderController@store` (validate, persist, dispatch event). Description: Replace 501 stub. Files: `app/Http/Controllers/Api/ReminderController.php`. AC: Feature `ReminderControllerTest::test_store` green. Estimated LOC: ~25. Depends on: T-03.1. Parallelizable: no.
- [x] **T-03.3** Implement `ReminderController@show/update/destroy`. Description: Replace 501 stubs. Files: `app/Http/Controllers/Api/ReminderController.php`. AC: Feature tests green. Estimated LOC: ~30. Depends on: T-03.2. Parallelizable: no.
- [x] **T-03.4** Implement `ReminderTemplateController` full CRUD (role:administrador). Description: Replace 501 stubs. Files: `app/Http/Controllers/Api/ReminderTemplateController.php`. AC: Feature `ReminderTemplateControllerTest` green. Estimated LOC: ~50. Depends on: —. Parallelizable: yes.
- [x] **T-03.5** Wire `ReminderProvider` in `routes/console.php` as hourly `Schedule::call(...)`. Description: Laravel 12 has no Kernel.php; use routes/console.php Schedule facade. Files: `routes/console.php`. AC: `php artisan schedule:list` shows ReminderProvider. Estimated LOC: ~10. Depends on: T-03.1..T-03.4. Parallelizable: no.
- [x] **T-03.6** Enforce channel whitelist on `StoreReminderRequest`: `in:sms,email,whatsapp,push`. Description: Reject unknown with 422. Files: `app/Http/Requests/StoreReminderRequest.php`. AC: Feature test green. Estimated LOC: ~6. Depends on: T-03.2. Parallelizable: yes.
- [x] **T-03.7** Status machine on `ReminderSchedule` model — `pending → queued → sent|failed` transitions only via service. Description: Add `transitionTo()` method on model with allowed-state guard. Files: `app/Models/ReminderSchedule.php`, `app/Services/ReminderService.php`. AC: Unit `ReminderScheduleStateTest` green. Estimated LOC: ~40. Depends on: —. Parallelizable: yes.
- [x] **T-03.8** Add migration `add_reminder_provider_runs` (idempotency tracking; nullable `last_run_at`, `runs_count`). Description: Additive. Files: `database/migrations/2026_08_05_020001_create_reminder_provider_runs_table.php`. AC: `php artisan migrate` succeeds. Estimated LOC: ~20. Depends on: T-03.5. Parallelizable: yes.
- [x] **T-03.9** Wrap each listener in try/catch + `report()` (AGENTS.md §7). Description: Listeners MUST NOT propagate into request lifecycle. Files: `app/Listeners/TrackReminderDelivery.php` (new). AC: Unit test asserts catch + report; integration feature green. Estimated LOC: ~30. Depends on: T-03.5. Parallelizable: yes.
- [x] **T-03.10** Write RED tests for Reminder CRUD + Provider + State + Channel validation. Description: One file per test scenario per Strict TDD. Files: `tests/Feature/Api/ReminderCrudTest.php`, `ReminderTemplateCrudTest.php`, `AuditLogFiltersTest.php`. AC: Tests fail on `main`; pass after this slice. Estimated LOC: ~120. Depends on: T-03.1..T-03.9. Parallelizable: no (covers all).

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| ReminderProvider scheduled run floods test environment | Bus::fake + clock-faker in tests; provider skips when `app()->runningUnitTests()` |
| Listener crashes propagate into 500 | try/catch + report in T-03.9 |
| Migration `add_reminder_provider_runs` blocks on SQLite | Additive only; nullable columns SQLite-compatible |
