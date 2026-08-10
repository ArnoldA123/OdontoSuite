# Delta for FormRequests — Slice 02

Resolves FormRequest validation gaps (API-008..060 high subset) by adding the missing optional/nullable fields that the frontend already sends but the backend silently drops. All new fields are additive and nullable — no destructive ALTER, rollback-safe via `git revert`.

## ADDED Requirements

### Requirement: FormRequests Accept Optional Related IDs

The system MUST accept and validate the following optional, nullable fields on the indicated FormRequests. Missing fields MUST NOT cause a 422; the system MUST persist `null` in the corresponding column when absent.

Evidence: Frontend `useApi().post(...)` payloads include these ids; backend FormRequests reject them with 422.

| FormRequest | New field | Type | Rule |
|---|---|---|---|
| `StoreAppointmentRequest` | `procedure_id` | integer, nullable | `nullable,exists:procedure_catalog,id` |
| `StoreAppointmentRequest` | `treatment_plan_id` | integer, nullable | `nullable,exists:treatment_plans,id` |
| `StoreAppointmentRequest` | `branch_id` | integer, nullable | `nullable,exists:branches,id` |
| `UpdateAppointmentRequest` | `ends_at` | date, nullable | `nullable,date,after:starts_at` |
| `StoreQuotationRequest` | `procedure_id` | integer, nullable | `nullable,exists:procedure_catalog,id` |
| `StoreQuotationRequest` | `payment_method_id` | integer, nullable | `nullable,exists:payment_methods,id` |
| `StoreTreatmentPlanRequest` | `branch_id` | integer, nullable | `nullable,exists:branches,id` |
| `StoreTransactionRequest` | `payment_method_id` | integer, nullable | `nullable,exists:payment_methods,id` |
| `StoreCashMovementRequest` | `branch_id` | integer, nullable | `nullable,exists:branches,id` |
| `StoreSpecialtyRecordRequest` | `procedure_id` | integer, nullable | `nullable,exists:procedure_catalog,id` |

#### Scenario: payload with new fields accepted

- WHEN the frontend POSTs a payload that includes `procedure_id`, `treatment_plan_id`, `branch_id`, `ends_at`, `payment_method_id`
- THEN the system responds 201 with the created resource
- AND the persisted record has the supplied ids

#### Scenario: payload without new fields still accepted

- WHEN the frontend POSTs the legacy payload (no new fields)
- THEN the system responds 201 with `procedure_id = null`, etc.

Test obligation: PHPUnit Feature test `tests/Feature/Api/FormRequestFieldsTest.php` parameterized per FormRequest.

---

### Requirement: StoreAppointment Validates ends_at After starts_at

The system MUST reject `ends_at` that is not strictly after `starts_at` with 422 and a message that names both fields.

Evidence: `ends_at` is required when present; `after:starts_at` rule missing.

#### Scenario: ends_at before starts_at rejected

- WHEN a payload has `starts_at = 2026-08-05 10:00`, `ends_at = 2026-08-05 09:00`
- THEN response is 422 with `errors.ends_at` referencing `starts_at`

Test obligation: PHPUnit Feature.

---

### Requirement: StoreCashMovement Validates Concept Whitelist

The system MUST reject `concept` values outside an allow-list: `["opening_balance","sale_refund","withdrawal","deposit","adjustment"]`. Auth: `role:administrador,finanzas`.

Evidence: Free-text `concept` allowed; downstream reports break on unknown strings.

#### Scenario: unknown concept rejected

- WHEN a payload has `concept = "typo"`
- THEN response is 422

Test obligation: PHPUnit Feature.

---

### Requirement: FormRequests Return Localized Error Envelopes

Every FormRequest that fails validation MUST return `{ message, errors: { field: [msg, ...] } }` shape (Laravel default) and a `meta.locale = es` marker so the frontend can decide on pluralization.

#### Scenario: localized envelope

- WHEN any FormRequest fails
- THEN response includes `meta.locale: "es"`

Test obligation: PHPUnit Feature assertions.

---

### Requirement: FormRequest Rules Documented Inline

Each FormRequest MUST declare a class-level PHPDoc summarizing the optional fields with their rules. Comment-only, no runtime change.

#### Scenario: PHPDoc present

- WHEN the file is read
- THEN the docblock lists all nullable fields and rules

Test obligation: Static review.

---

## MODIFIED Requirements

### Requirement: StoreAppointmentRequest Accepts Treatment Plan

The full updated request MUST include rules for `procedure_id`, `treatment_plan_id`, `branch_id` (all `nullable|exists`). The base rules for `patient_id`, `appointment_type_id`, `dental_chair_id`, `professional_id`, `starts_at`, `notes` MUST remain unchanged.

(Previously: only the legacy required fields were validated; any optional id caused 422.)

#### Scenario: legacy payload still validates

- WHEN a payload contains only legacy fields
- THEN validation passes

#### Scenario: extended payload validates

- WHEN a payload adds the new fields
- THEN validation passes

Test obligation: PHPUnit Feature.

---

### Requirement: StoreQuotationRequest Accepts Procedure and Payment Method

The full updated request MUST include `procedure_id` and `payment_method_id` as nullable. Existing rules for `patient_id`, `items`, `total`, `valid_until` MUST remain.

(Previously: missing fields caused 422 from the frontend quotation wizard.)

---

### Requirement: StoreTransactionRequest Accepts Payment Method

The full updated request MUST include `payment_method_id` as nullable. Existing rules for `amount`, `cash_register_session_id`, `concept` MUST remain.

---

### Requirement: StoreTreatmentPlanRequest Accepts Branch

The full updated request MUST include `branch_id` as nullable. Existing rules MUST remain.

---

### Requirement: StoreSpecialtyRecordRequest Accepts Procedure

The full updated request MUST include `procedure_id` as nullable. Existing inline validation rules (per ADR-0008) MUST remain.

---

## REMOVED Requirements

None for this slice.

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| FormRequests Accept Optional Related IDs | Feature | `tests/Feature/Api/FormRequestFieldsTest.php` |
| ends_at After starts_at | Feature | `tests/Feature/Api/AppointmentValidationTest.php` |
| CashMovement Concept Whitelist | Feature | `tests/Feature/Api/CashMovementValidationTest.php` |
| Localized Error Envelopes | Feature | per-FormRequest |
| PHPDoc Documented | Static review | n/a |
| StoreAppointmentRequest Accepts Treatment Plan | Feature | `tests/Feature/Api/StoreAppointmentRequestTest.php` |
| StoreQuotationRequest Accepts Procedure and Payment Method | Feature | `tests/Feature/Api/StoreQuotationRequestTest.php` |
| StoreTransactionRequest Accepts Payment Method | Feature | `tests/Feature/Api/StoreTransactionRequestTest.php` |
| StoreTreatmentPlanRequest Accepts Branch | Feature | `tests/Feature/Api/StoreTreatmentPlanRequestTest.php` |
| StoreSpecialtyRecordRequest Accepts Procedure | Feature | `tests/Feature/Api/StoreSpecialtyRecordRequestTest.php` |
