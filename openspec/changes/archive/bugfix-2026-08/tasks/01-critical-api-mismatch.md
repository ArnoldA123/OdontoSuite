# Slice 01 — Critical API Mismatch

> Findings: 10 criticals (BF-001..010) + API-001..007 high
> Cluster: api-mismatch / audit-log
> LOC est: ~380 · Budget risk: Medium · Depends on: —
> Spec: [../specs/01-critical-api-mismatch.md](../specs/01-critical-api-mismatch.md)

## Per-slice forecast

Decision needed before apply: Yes (only for the SDK-call in slice 07)
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Medium

## Acceptance Criteria

- All 10 critical findings have a RED test that fails before this slice lands.
- `php artisan test --filter=AuditLogControllerTest` exits 0.
- `php artisan route:list | grep audit-logs` shows only GET index/show (no POST/PUT/PATCH/DELETE).
- `grep -rn "setPublishableKey" resources/js/composables/` returns empty (also for slice 07).
- `grep -rn "bg-green-600\|bg-red-600" resources/js/components/` returns empty after slice 06.
- All new endpoints respond 200/201/204 in smoke harness.
- `{data, meta.message}` envelope asserted in every new test.
- Migration `add_audit_log_immutable` is additive (nullable boolean).

## Tasks

- [x] **T-01.1** Create 7 missing cash-register/transactions routes (summary, sessions/{id}, sessions/{id}/closure-report, reports/period, reports/export/{format}, list) — declare BEFORE any apiResource on `routes/api.php`. Description: New `CashRegisterSummaryController@summary`, `CashRegisterReportController@closureReport|period|export`, `CashReportController::export` accepts format whitelist pdf|xlsx|csv. Add `CashRegisterController@movements($id)` for session detail. Register `GET transactions/list` before `Route::apiResource('transactions', ...)`. Files: `routes/api.php`, `app/Http/Controllers/Api/CashRegisterSummaryController.php` (new), `app/Http/Controllers/Api/CashRegisterReportController.php` (new), `app/Http/Controllers/Api/CashRegisterController.php`, `app/Http/Controllers/Api/CashReportController.php`, `app/Http/Controllers/Api/TransactionController.php`. AC: 5 new Feature tests green; `php artisan route:list` shows new endpoints; smoke curl returns 200. Estimated LOC: ~180. Depends on: —. Parallelizable: yes (T-01.1, T-01.2, T-01.3 independent).
- [x] **T-01.2** Replace `Route::apiResource('audit-logs', AuditLogController::class)` with explicit `Route::middleware('role:administrador')->group(function () { Route::get('audit-logs', [AuditLogController::class, 'index']); Route::get('audit-logs/{id}', [AuditLogController::class, 'show']); });` (byX actions retained for v2). Description: Eliminates 500 on writes; restricts to admin role. Files: `routes/api.php:238`. AC: `php artisan route:list | grep audit-logs` shows only GET verbs; Feature test 405 for POST. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-01.3** Add `Route::delete('medical-records/attachments/{attachment}', [MedicalRecordController::class, 'deleteAttachment'])` declared BEFORE `Route::apiResource('medical-records', ...)` to avoid `{attachment}` swallowing as `{medicalRecord}`. Description: New controller method returns 204/403/404. Files: `routes/api.php`, `app/Http/Controllers/Api/MedicalRecordController.php`. AC: Feature `MedicalRecordAttachmentTest` green; smoke curl DELETE returns 204 with valid Sanctum token. Estimated LOC: ~30. Depends on: —. Parallelizable: yes.
- [x] **T-01.4** Change PDF quotation fetch in `PatientDetailPage` from `window.open(url)` to `fetch + blob + <a download>` with Authorization header. Description: window.open strips Authorization in Sanctum cookie/session hybrid; explicit fetch with `Authorization: Bearer ${token}` then `URL.createObjectURL(blob)` + temporary anchor click. Files: `resources/js/pages/PatientDetailPage.vue`. AC: `pnpm build` green; manual smoke downloads PDF without 401; grep `window.open` in patient detail returns empty. Estimated LOC: ~25. Depends on: —. Parallelizable: yes (FE-only).
- [x] **T-01.5** Fix `useApi.del` to accept optional body — change signature to `del = async (url, options = {}) => axios.delete(url, options)` and pass `options.data` to axios. Description: 6 callsites (incl. `useSpecialtyRecords.deleteRecord`) need body but wrapper threw. Files: `resources/js/composables/useApi.js`. AC: Unit test `useApi.spec.js` asserts del with data passes; existing callers unaffected. Estimated LOC: ~8. Depends on: —. Parallelizable: yes (FE-only).
- [x] **T-01.6** Standardize PaymentMethodController, BranchController, PatientController, SpecialtyController, DashboardController response envelope to `{data, meta.message}`. Description: Add `MessageEnveloper` trait or inline wrap. Files: `app/Http/Controllers/Api/PaymentMethodController.php`, `BranchController.php`, `PatientController.php`, `SpecialtyController.php`, `DashboardController.php`. AC: Feature tests assert `response.json()['meta']['message']` present; pnpm build green; existing snapshot parity. Estimated LOC: ~40. Depends on: T-01.1 (response shape precedent). Parallelizable: yes.
- [x] **T-01.7** Fix `useCashRegister.getSessions` shape mismatch — backend now returns `{data, meta}` per T-01.6; FE must read `response.data` not `response.sessions`. Description: Patch composable to extract `response.data`. Files: `resources/js/composables/useCashRegister.js`. AC: `pnpm build` green; smoke loads CashRegisterPage with mock 200; grep `.sessions` removed. Estimated LOC: ~6. Depends on: T-01.6. Parallelizable: no (coupled).
- [x] **T-01.8** Standardize remaining composables to return `response.data` (`useTransactions`, `useTreatmentPlans`, `usePatients`, `useBranches`, `useSpecialties`, `useAppointments`). Description: Same envelope conformance. Files: `resources/js/composables/useTransactions.js`, `useTreatmentPlans.js`, `usePatients.js`, `useBranches.js`, `useSpecialties.js`, `useAppointments.js`. AC: `pnpm build` green; smoke render of each module page passes. Estimated LOC: ~30. Depends on: T-01.6. Parallelizable: yes (per-composable commits).
- [x] **T-01.9** RED tests for the 10 critical findings (write failing tests first per Strict TDD). Description: One Feature test per critical bug. Files: `tests/Feature/Api/AuditLogControllerTest.php`, `CashMovementControllerTest.php`, `MercadoPagoControllerTest.php`, `MedicalRecordAttachmentTest.php`, `CashRegisterSummaryTest.php`, `CashRegisterSessionDetailTest.php`, `CashRegisterClosureReportTest.php`, `ReportsExportTest.php`, `ReportsPeriodTest.php`, `TransactionsListTest.php`. AC: `php artisan test` shows these tests failing on `main` branch; they pass after this slice lands. Estimated LOC: ~120. Depends on: —. Parallelizable: yes.
- [x] **T-01.10** Add migration `add_audit_log_immutable` (nullable boolean column on `audit_logs`, default false). Description: Future-proof hook for write-protection; out of band for slice 02 but ships with slice 01 to keep audit log change atomic. Files: `database/migrations/2026_08_xx_add_audit_log_immutable.php`. AC: `php artisan migrate` succeeds; rollback removes column. Estimated LOC: ~20. Depends on: —. Parallelizable: yes.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| New cash endpoints diverge from existing service contracts | Reuse `CashRegisterService` + `CashRegisterReportService`; add feature tests against current behavior |
| `setPublishableKey` removed in slice 07 may break PaymentModal | Coordinate: slice 01 doesn't touch PaymentModal; slice 07 removes the call after manual smoke |
| Migration `add_audit_log_immutable` may be skipped on CI MySQL | Migration is additive; CI MySQL gate is unaffected |
