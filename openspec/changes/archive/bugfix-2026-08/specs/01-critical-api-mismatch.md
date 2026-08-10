# Delta for Critical API Mismatch — Slice 01

Resolves 10 critical findings (BF-001..010) and the highest-priority API contract mismatches (API-001..007) that block production. Covers audit-log read-only, RBAC bypass on cash movements, MercadoPago SDK v2 dead call, broken DELETE attachments, and the 7 missing cash-register endpoints.

## ADDED Requirements

### Requirement: Audit Log Is Read-Only

The system MUST expose audit logs through `GET /audit-logs` (index) and `GET /audit-logs/{id}` (show) only. The system MUST NOT route POST, PUT, PATCH, or DELETE requests to the `AuditLogController` and MUST NOT register those verbs. The `AuditLogController` MUST return `501 Not Implemented` with a `meta.todo` pointing to `docs/mejoras/plan-mejoras-futuras-2026-06.md` only if a future write endpoint is registered.

Evidence: `routes/api.php:238` registers `apiResource('audit-logs', AuditLogController::class)` which currently 500s on POST/PUT/DELETE because the controller only declares `index`, `show`, `byPatient`, `byUser`, `byDentalChair`, `byAppointmentType`.

#### Scenario: GET index returns paginated logs

- WHEN an authenticated user with role `role:administrador` requests `GET /audit-logs`
- THEN the system responds 200 with shape `{ data: [...], meta: { current_page, last_page, per_page, total } }`
- AND no POST/PUT/PATCH/DELETE route exists for `audit-logs`

#### Scenario: POST is not routable

- WHEN any authenticated user requests `POST /audit-logs`
- THEN the system responds 405 Method Not Allowed (NOT 500)

Test obligation: PHPUnit Feature test `tests/Feature/Api/AuditLogControllerTest.php` — assert 200 on GET, 405 on POST/PUT/PATCH/DELETE.

---

### Requirement: Cash Movements RBAC Enforced

The system MUST enforce role-based access on `POST /cash-movements` (create) so that only users with role `administrador` or `finanzas` may create movements. The system MUST return 403 Forbidden otherwise. The frontend `usePermissions` composable MUST expose a `createMovement` capability bound to the same backend roles.

Evidence: `routes/api.php:376` registers `Route::middleware('cash.session')->apiResource('cash-movements', CashMovementController::class);` inside the `role:administrador,finanzas` group (line 366) — backend is correct; the gap is that `usePermissions` did not expose the `createMovement` boolean, allowing UI buttons to render for users who lacked the role.

#### Scenario: createMovement exposed for admin

- WHEN an authenticated `administrador` user invokes `usePermissions().createMovement`
- THEN the composable returns `true`
- AND the `CashMovementCreate.vue` modal mounts the form

#### Scenario: createMovement false for receptionist

- WHEN an authenticated `recepcionista` user invokes `usePermissions().createMovement`
- THEN the composable returns `false`
- AND the create button is hidden or disabled

Test obligation: PHPUnit Feature test `tests/Feature/Api/CashMovementControllerTest.php` covering 403 path + Vitest (or hand-rolled assertion) on the composable mapping.

---

### Requirement: MercadoPago Key Is Server-Side Only

The system MUST NOT call `MercadoPago.setPublishableKey` from the browser. The public key MUST be read from `VITE_MERCADOPAGO_PUBLIC_KEY` env and passed to the SDK `preferences.create` flow on the server. The `useMercadoPago.js` composable MUST drop any `setPublishableKey` invocation.

Evidence: SDK `mercadopago/dx-php` 3.10 has no `setPublishableKey` method (server-side SDK v2 uses `MercadoPago\SDK::setAccessToken`); `useMercadoPago.js` was calling it and throwing `BadMethodCallException` at runtime.

#### Scenario: composable boots without SDK call

- WHEN `useMercadoPago().init()` runs in the browser
- THEN no error is thrown
- AND the public key comes from `import.meta.env.VITE_MERCADOPAGO_PUBLIC_KEY`

#### Scenario: payment preference created server-side

- WHEN the client posts to `/payments/mercadopago/preference`
- THEN the server creates the preference using the server access token
- AND the client receives the preference id only

Test obligation: PHPUnit Feature test asserting `MercadoPagoController@createPreference` returns preference id; manual smoke-test confirming browser console has no `setPublishableKey` call.

---

### Requirement: Attachment DELETE Endpoint Returns 204

The system MUST respond 204 No Content on `DELETE /medical-records/attachments/{id}` when the authenticated user has policy authorization. The system MUST respond 403 if the user does not own the attachment or lacks the role.

Evidence: `MedicalRecordController::deleteAttachment` is missing — only `uploadAttachment` and `getAttachmentsByCategory` exist; the route declaration is absent.

#### Scenario: authorized delete succeeds

- WHEN a `odontologo` requests `DELETE /medical-records/attachments/{id}` for an attachment they uploaded
- THEN the system responds 204
- AND the file is removed from storage

#### Scenario: unauthorized delete rejected

- WHEN a non-owning user requests the same DELETE
- THEN the system responds 403

Test obligation: PHPUnit Feature test `tests/Feature/Api/MedicalRecordAttachmentTest.php`.

---

### Requirement: Cash Register Summary Endpoint

The system MUST respond 200 on `GET /cash-register/summary` returning aggregate totals (open sessions, expected cash, movements count) for the current shift. Auth: `role:administrador,finanzas`.

Evidence: `CashRegisterController` has `current`, `movements`, `open`, `close`, but no `summary` action; the frontend `CashBoxPage` calls it.

#### Scenario: summary returns aggregates

- WHEN `administrador` requests `GET /cash-register/summary`
- THEN response is 200 with `{ data: { open_sessions, expected_cash, movements_count } }`

Test obligation: PHPUnit Feature test.

---

### Requirement: Cash Register Session Detail

The system MUST expose `GET /cash-register/sessions/{id}` returning the full session record (open/close timestamps, user, expected vs counted amounts). Auth: `role:administrador,finanzas`.

Evidence: `CashRegisterController@show` exists but the alias `/cash-register/sessions/{id}` was missing on `routes/api.php:402`.

#### Scenario: session detail by id

- WHEN user requests `GET /cash-register/sessions/42`
- THEN response is 200 with the session payload

Test obligation: PHPUnit Feature test.

---

### Requirement: Closure Report Endpoint

The system MUST expose `GET /cash-register/sessions/{id}/closure-report` returning the printable closure payload (movements breakdown, totals per payment method). Auth: `role:administrador,finanzas`.

Evidence: `routes/api.php:383` declares the route, but `CashRegisterController::closureReport` returns 501.

#### Scenario: closure report renders

- WHEN user requests the closure report for a closed session
- THEN response is 200 with movements grouped by payment_method_id

Test obligation: PHPUnit Feature test.

---

### Requirement: Reports Export Endpoint Accepts Format

The system MUST respond 200 on `GET /reports/{reportType}/export?format=pdf|xlsx|csv` and stream the file. Auth: any authenticated user.

Evidence: `ReportController::export` exists at `routes/api.php:199` but the format query parameter is not validated/passed; some reportTypes return 500 for unsupported formats.

#### Scenario: PDF export streams

- WHEN user requests `GET /reports/revenue/export?format=pdf`
- THEN the response streams a `application/pdf` body

#### Scenario: unsupported format 400

- WHEN user requests `format=xml`
- THEN the system responds 400

Test obligation: PHPUnit Feature test.

---

### Requirement: Reports Period Endpoint

The system MUST expose `GET /reports/period?from=YYYY-MM-DD&to=YYYY-MM-DD` returning aggregate counts and revenue for the window. Auth: `role:administrador,finanzas`.

Evidence: Endpoint missing; frontend `ReportsPage` calls it.

#### Scenario: period range returns aggregate

- WHEN `administrador` requests `GET /reports/period?from=2026-07-01&to=2026-07-31`
- THEN response is 200 with `{ data: { revenue, appointments, new_patients } }`

Test obligation: PHPUnit Feature test.

---

### Requirement: Transactions List Endpoint

The system MUST expose `GET /transactions/list` returning the current session's transaction list with pagination. Auth: `role:administrador,finanzas` + `cash.session` middleware.

Evidence: Endpoint missing; frontend expects a flat list separate from the apiResource index.

#### Scenario: paginated list

- WHEN user requests `GET /transactions/list?per_page=25`
- THEN response is 200 with paginated items

Test obligation: PHPUnit Feature test.

---

### Requirement: Response Shape Uniformity

Every API endpoint listed in this slice MUST return responses with the shape `{ data: ..., meta: { message: ... } }`. The system MUST include `meta.message` on success and on validation errors.

#### Scenario: meta present on success

- WHEN any new endpoint succeeds
- THEN the response includes `meta.message`

Test obligation: PHPUnit Feature assertions on every new endpoint.

---

## MODIFIED Requirements

### Requirement: Audit Log Routes Reduced to Index + Show

The route `Route::apiResource('audit-logs', AuditLogController::class);` MUST be replaced by an explicit `Route::get('audit-logs', ...)` and `Route::get('audit-logs/{id}', ...)` pair. The byX action methods (`byPatient`, `byUser`, `byDentalChair`, `byAppointmentType`) MUST remain and stay mounted on `audit-logs/{resource}/{id}`.

(Previously: apiResource registered all 7 verbs; only 6 actions existed, causing 500 on writes.)

#### Scenario: explicit GET routes only

- WHEN route list is inspected via `php artisan route:list --path=audit-logs`
- THEN only GET verbs appear

Test obligation: PHPUnit Feature test + `route:list` snapshot.

---

## REMOVED Requirements

None for this slice.

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| Audit Log Is Read-Only | Feature | `tests/Feature/Api/AuditLogControllerTest.php` |
| Cash Movements RBAC Enforced | Feature | `tests/Feature/Api/CashMovementControllerTest.php` |
| MercadoPago Key Server-Side Only | Feature + manual | `tests/Feature/Api/MercadoPagoControllerTest.php` |
| Attachment DELETE | Feature | `tests/Feature/Api/MedicalRecordAttachmentTest.php` |
| Cash Register Summary | Feature | `tests/Feature/Api/CashRegisterSummaryTest.php` |
| Cash Register Session Detail | Feature | `tests/Feature/Api/CashRegisterSessionDetailTest.php` |
| Closure Report | Feature | `tests/Feature/Api/CashRegisterClosureReportTest.php` |
| Reports Export Format | Feature | `tests/Feature/Api/ReportsExportTest.php` |
| Reports Period | Feature | `tests/Feature/Api/ReportsPeriodTest.php` |
| Transactions List | Feature | `tests/Feature/Api/TransactionsListTest.php` |
| Response Shape Uniformity | Feature | per-endpoint assertions |
| Audit Log Routes Reduced | Feature + snapshot | `tests/Feature/Api/AuditLogControllerTest.php` + `route:list` golden |
