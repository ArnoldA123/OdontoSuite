# Slice 02 — FormRequests

> Findings: API-008..060 high subset (FormRequest validation gaps)
> Cluster: form-requests
> LOC est: ~360 · Budget risk: Low · Depends on: —
> Spec: [../specs/02-form-requests.md](../specs/02-form-requests.md)

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low

## Acceptance Criteria

- All 10 FormRequests accept new nullable fields without 422.
- `php artisan test --filter=FormRequestFieldsTest` exits 0.
- `meta.locale: "es"` asserted in every validation-failure response.
- No destructive ALTER; only ADD nullable columns.
- `ends_at > starts_at` enforced server-side.
- Concept whitelist enforced on `StoreCashMovementRequest`.
- Rollback = `git revert`; no migration to undo.

## Tasks

- [x] **T-02.1** Extend `StoreAppointmentRequest` with `procedure_id`, `treatment_plan_id`, `branch_id` (all nullable, exists rules). Description: 3 new nullable rules. Files: `app/Http/Requests/StoreAppointmentRequest.php`. AC: Feature test posts without these fields → 201; posts with invalid IDs → 422. Estimated LOC: ~15. Depends on: —. Parallelizable: yes (per-request).
- [x] **T-02.2** Clean `StoreAppointmentRequest` enum — `in_consultation` vs `in_progress` (canonicalize to `in_progress`). Description: Pick one canonical value; update frontend enum if needed. Files: `app/Http/Requests/StoreAppointmentRequest.php`, `resources/js/composables/useAppointments.js`. AC: Unit test asserts only `in_progress` accepted; FE smoke saves appointment. Estimated LOC: ~12. Depends on: T-02.1. Parallelizable: no.
- [x] **T-02.3** `PaymentModal.createTransaction` sends `payment_method_id` in payload. Description: Currently sends only `amount`; backend rejects or persists null. Files: `resources/js/components/PaymentModal.vue`. AC: Feature `StoreTransactionRequestTest` green; FE smoke completes payment. Estimated LOC: ~6. Depends on: T-02.4. Parallelizable: yes (FE-only).
- [x] **T-02.4** `StoreSpecialtyRecordRequest::authorize()` doesn't break when specialty is null. Description: Guard against null in role check. Files: `app/Http/Requests/StoreSpecialtyRecordRequest.php`. AC: Feature test green. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-02.5** `StoreQuotationRequest` patient_id error message coherent with `sometimes|nullable` rule. Description: Replace "required" message with "patient_id is optional but must exist when provided" localization. Files: `app/Http/Requests/StoreQuotationRequest.php`. AC: Feature test asserts 422 message text; `meta.locale: "es"`. Estimated LOC: ~6. Depends on: —. Parallelizable: yes.
- [x] **T-02.6** `CloseCashRequest` validates `closing_amount > 0` server-side. Description: Add `gt:0` rule. Files: `app/Http/Requests/CloseCashRequest.php`. AC: Feature `CloseCashValidationTest` green. Estimated LOC: ~6. Depends on: —. Parallelizable: yes.
- [x] **T-02.7** Add `procedure_id`, `payment_method_id` to `StoreQuotationRequest` (both nullable, exists rules). Description: 2 new rules. Files: `app/Http/Requests/StoreQuotationRequest.php`. AC: Feature test. Estimated LOC: ~8. Depends on: —. Parallelizable: yes.
- [x] **T-02.8** Add `payment_method_id` to `StoreTransactionRequest` (nullable, exists rule). Description: 1 new rule. Files: `app/Http/Requests/StoreTransactionRequest.php`. AC: Feature test. Estimated LOC: ~6. Depends on: —. Parallelizable: yes.
- [x] **T-02.9** Add `branch_id` to `StoreTreatmentPlanRequest` and `StoreCashMovementRequest` (nullable, exists rule). Description: 2 new rules across 2 files. Files: `app/Http/Requests/StoreTreatmentPlanRequest.php`, `StoreCashMovementRequest.php`. AC: Feature test. Estimated LOC: ~12. Depends on: —. Parallelizable: yes.
- [x] **T-02.10** Add `procedure_id` to `StoreSpecialtyRecordRequest` (nullable, exists rule). Description: 1 new rule. Files: `app/Http/Requests/StoreSpecialtyRecordRequest.php`. AC: Feature test. Estimated LOC: ~6. Depends on: T-02.4. Parallelizable: yes.
- [x] **T-02.11** Add `concept` whitelist on `StoreCashMovementRequest`: `in:opening_balance,sale_refund,withdrawal,deposit,adjustment`. Description: Reject unknown concept with 422. Files: `app/Http/Requests/StoreCashMovementRequest.php`. AC: Feature `CashMovementValidationTest` green. Estimated LOC: ~6. Depends on: T-02.9. Parallelizable: no.
- [x] **T-02.12** Add `ends_at` validation to `UpdateAppointmentRequest`: `nullable|date|after:starts_at`. Description: Reject ends_at <= starts_at with 422 referencing both fields. Files: `app/Http/Requests/UpdateAppointmentRequest.php`. AC: Feature `AppointmentValidationTest` green. Estimated LOC: ~6. Depends on: —. Parallelizable: yes.
- [x] **T-02.13** Override `messages()` in all 10 FormRequests to return `meta.locale: "es"`. Description: Single trait or per-request override. Files: each FormRequest + `app/Http/Requests/Concerns/LocalizedErrors.php` (new). AC: Per-FormRequest test asserts envelope. Estimated LOC: ~30. Depends on: T-02.1..T-02.12. Parallelizable: no (centralizes).
- [x] **T-02.14** Write RED test `tests/Feature/Api/FormRequestFieldsTest.php` (parameterized over each new field). Description: Table-driven test posts payloads with/without each field. AC: Test fails on `main`; passes after this slice. Estimated LOC: ~80. Depends on: T-02.1..T-02.13. Parallelizable: no (covers all).
- [x] **T-02.15** Add class-level PHPDoc to each FormRequest listing optional fields and rules. Description: Static doc only. Files: each FormRequest. AC: `php artisan test` green; doc-block check. Estimated LOC: ~10. Depends on: T-02.13. Parallelizable: yes.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| Additive fields break existing payload contracts | All nullable; old payloads persist identical |
| `in_progress` enum rename breaks frontend consumers | Frontend enum synced in same commit |
| `gt:0` rule rejects edge case (zero balance close) | Confirm business intent before enforcing |
