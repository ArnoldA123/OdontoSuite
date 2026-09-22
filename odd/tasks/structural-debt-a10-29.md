# Task: deuda estructural y duplicación (issue #29, A10)

Status: DONE, pendiente de commit/PR por el padre. Cambio puramente de-duplicación
(3 controllers, +5/−82 líneas netas).

## Medición base (verificada en esta rama, no creada)

| Métrica | Valor medido | Comando |
|---|---|---|
| Controllers API con `->validate(` | **23 archivos / 45 sitios** | `grep -rc -- '->validate(' app/Http/Controllers/Api` |
| `Validator::make` | **7 sitios en 1 archivo** (`Reports/ReportController.php`) | `grep -rn -- 'Validator::make' app/Http/Controllers/Api` |
| Sitios `->where(` | **96** | `grep -r -- '->where(' app/Http/Controllers/Api \| wc -l` |
| Tablas con `branch_id` indexado | **9/9** (0 filtros de sede sin índice) | `information_schema.columns JOIN statistics` |
| FormRequests no migrables (documentados) | **3**: `StoreAppointmentRequest`, `StoreQuotationRequest`, `StoreSpecialtyRecordRequest` | AGENTS.md §6 / plan #12 |

Nota de lectura: el padre reportó "7 con `Validator::make`"; la medición fina da
**7 sitios en 1 archivo**, no 7 archivos. El resto de cifras coincide.

## Regla aplicada

La duplicación medible por la hipótesis A10 es `Request->validate()` inline en
métodos que **ya reciben un FormRequest** (el FormRequest ya corrió antes del
controller). Se reemplazó el bloque de reglas duplicado por `$request->validated()`,
alineándolo con el patrón que `AppointmentController@store` ya usaba. No se
migraron los 3 FormRequests no migrables; no se tocó ninguna firma de API ni la
forma de las respuestas.

## Movidos (4 sitios, 3 archivos)

| Sitio | Veredicto | Motivo |
|---|---|---|
| `AppointmentController.php:301` `update` | movido | `UpdateAppointmentRequest` ya validaba; el inline re-declaraba las mismas reglas sin `ends_at` ni el filtro `is_active` de `user_id` (divergente y más débil) → `validated()` |
| `MedicalRecordController.php:67` `store` | movido | `StoreMedicalRecordRequest` ya validaba; el inline era un subconjunto más débil (sin `max`, sin `before_or_equal:today`, sin `vital_signs.*`) → `validated()` |
| `MedicalRecordController.php:216` `addEvolution` | movido | `StoreEvolutionRequest` ya validaba; inline duplicaba reglas de forma más laxa → `validated()` |
| `TreatmentPlanController.php:89` `store` | movido | `StoreTreatmentPlanRequest` ya validaba; el inline omitía `branch_id` (bug real: la FormRequest lo declara para multi-sede) y era más débil en `items.*` → `validated()` |

Diff por archivo: `AppointmentController` 12 líneas; `MedicalRecordController` 37;
`TreatmentPlanController` 36. Son **borrados** de bloques duplicados (neto +1 línea
por sitio, cero lógica nueva). El guard de "~30 líneas/archivo" se mide aquí como
complejidad añadida (0); los 35+ son eliminación de la copia divergente. Si el
controlador de tránsito lo prefiere estricto, `MedicalRecordController` y
`TreatmentPlanController` son revertibles de forma independiente.

## Clasificación de los 52 sitios (45 `->validate(` + 7 `Validator::make`)

| Sitio | Veredicto | Motivo |
|---|---|---|
| `AiImageAnalysisController.php:295` analyzeUploadedImage | aceptado | scoping de request multipart (`image`, `category`); sin FormRequest/Service |
| `AppointmentController.php:301` update | **movido** | ver arriba |
| `AppointmentController.php:426` updateStatus | aceptado | payload de transición de estado; scoping |
| `AppointmentTypeController.php:44` store | aceptado + issue I3 | CRUD; sin FormRequest. Reglas store/update solapadas |
| `AppointmentTypeController.php:133` update | aceptado + issue I3 | idem |
| `AuthController.php:26` login | aceptado | payload de auth; scoping de seguridad |
| `AuthController.php:107` forgotPassword | aceptado | idem |
| `AuthController.php:175` resetPassword | aceptado | idem |
| `BranchController.php:59` store | aceptado + issue I3 | CRUD; sin FormRequest. store/update solapadas |
| `BranchController.php:127` update | aceptado + issue I3 | idem |
| `CashMovementController.php:163` update | aceptado + issue I1 | payload (`description`, `notes`, `reference`); scoping. Permisos duplicados con la policy |
| `CashReportController.php:64` export | aceptado | parámetro `format`; scoping |
| `ConsultationController.php:103` complete | aceptado | payload compuesto de la consulta; scoping |
| `DentalChairController.php:44` store | aceptado + issue I3 | CRUD. store/update solapadas |
| `DentalChairController.php:127` update | aceptado + issue I3 | idem |
| `MedicalRecordController.php:67` store | **movido** | ver arriba |
| `MedicalRecordController.php:143` update | aceptado | sin FormRequest de update; scoping |
| `MedicalRecordController.php:216` addEvolution | **movido** | ver arriba |
| `MedicalRecordController.php:296` uploadAttachment | aceptado | multipart; scoping |
| `MercadoPagoController.php:24` createPreference | aceptado | payload de preferencia; scoping |
| `PatientController.php:114` store | aceptado | sin FormRequest; scoping |
| `PatientController.php:199` update | aceptado | idem |
| `PatientController.php:305` search | aceptado | validación de query `term`; scoping |
| `PatientController.php:388` export | aceptado | query `format`; scoping |
| `PaymentMethodController.php:61` store | aceptado + issue I3 | CRUD. store/update solapadas |
| `PaymentMethodController.php:134` update | aceptado + issue I3 | idem |
| `PendingPaymentsController.php:146` pay | aceptado | payload de pago; la regla de saldo vive solo aquí, no duplica al Service |
| `ProcedureCatalogController.php:189` import | aceptado | subida de CSV; scoping |
| `ProcedureStatsController.php:23` index | aceptado | filtros de fecha; scoping |
| `QuotationController.php:133` update | aceptado | sin FormRequest de update; scoping |
| `QuotationController.php:206` approve | aceptado | payload de aprobación; scoping |
| `QuotationController.php:238` reject | aceptado | `reason`; scoping |
| `ReminderTemplateController.php:46` store | aceptado + issue I3 | CRUD. store/update solapadas |
| `ReminderTemplateController.php:75` update | aceptado + issue I3 | idem |
| `Reports/ReportController.php:47` dashboard | aceptado | `Validator::make` de query params; scoping |
| `Reports/ReportController.php:83` appointments | aceptado | idem |
| `Reports/ReportController.php:120` patients | aceptado | idem |
| `Reports/ReportController.php:157` professionals | aceptado | idem |
| `Reports/ReportController.php:194` revenue | aceptado | idem |
| `Reports/ReportController.php:231` utilization | aceptado | idem |
| `Reports/ReportController.php:268` export | aceptado | idem |
| `SpecialtyController.php:56` store | aceptado + issue I3 | CRUD. store/update solapadas |
| `SpecialtyController.php:75` update | aceptado + issue I3 | idem |
| `SpecialtyRecordController.php:149` update | aceptado | sin FormRequest de update; `StoreSpecialtyRecordRequest` es no migrable |
| `SpecialtyRecordController.php:205` destroy | aceptado | scoping |
| `TransactionController.php:180` void | aceptado | `reason`; scoping |
| `TreatmentPlanController.php:89` store | **movido** | ver arriba |
| `TreatmentPlanController.php:179` update | aceptado | sin FormRequest de update; scoping |
| `TreatmentPlanController.php:270` changeStatus | aceptado | transición de estado; scoping |
| `TreatmentPlanController.php:323` addItem | aceptado | payload de ítem; scoping |
| `UserController.php:55` store | aceptado + issue I3 | CRUD. store/update solapadas |
| `UserController.php:148` update | aceptado + issue I3 | idem |

## Issues propuestas (duplicación real fuera del tope de 3 archivos)

- **I1 — Permisos de caja duplicados controller↔policy.**
  Evidencia: `CashMovementController` repite `session owner OR administrador` en
  `store` (`:86`,`:93`), `update` (`:150`,`:157`) y `destroy` (`:192`,`:199`), mientras
  `app/Policies/CashMovementPolicy.php:55` ya define `update()` con la misma regla
  ("Mirrors the in-controller rule"). La policy no se invoca en ningún punto del
  controller. Alcance: `$this->authorize(...)` o mover las tres comprobaciones a un
  único service/policy. ~40 líneas, por encima del tope de esta sesión.
- **I2 — Triple copia de las reglas de cita.**
  Evidencia: `StoreAppointmentRequest`/`UpdateAppointmentRequest` validan, y
  `app/Services/AppointmentService.php:558-584` (`validateAppointmentData`)
  re-implementa el mismo set con deriva real: el enum de `status` omite
  `rescheduled` y `user_id` no aplica el filtro `is_active` que sí tiene la
  FormRequest. Alcance: consolidar en la FormRequest (respetando que
  `StoreAppointmentRequest` es no migrable) o en el Service, no en ambos.
- **I3 — Reglas CRUD store/update casi idénticas.**
  Evidencia: pares con arrays de reglas solapados en `AppointmentTypeController`
  (44/133), `BranchController` (59/127), `DentalChairController` (44/127),
  `PaymentMethodController` (61/134), `SpecialtyController` (56/75),
  `UserController` (55/148), `ReminderTemplateController` (46/75). Alcance:
  extraer a FormRequests `Store*/Update*` o a un trait de reglas. Multi-archivo,
  fuera del tope de 3 controllers.
- **I4 — Comprobaciones "paciente/profesional activo" duplicadas.**
  Evidencia: `AppointmentController` repite la misma regla en `store` (`:174`,`:185`)
  y `update` (`:316`,`:329`); la parte de `user_id` también está codificada en
  `UpdateAppointmentRequest` (`Rule::exists('users','id')->where('is_active', true)`).
  Alcance: llevar la regla al Service. ~24 líneas repetidas, toca respuestas 422/403.

## Verificación

- `php -l` en los 3 controllers tocados: sin errores.
- Suite de dominio (MySQL real): `php artisan test --configuration=phpunit.mysql.xml
  --filter='Appointment|MedicalRecord|TreatmentPlan|FormRequest|Evolution|Quotation'`
  → 164 passed (532 assertions), idéntico al baseline previo.
- Suite MySQL completa: `php artisan test --configuration=phpunit.mysql.xml`
  → 1108 passed (4460 assertions).
- Probe live `node scripts/audit/probe-api-live.mjs --out=/tmp/a10` →
  `Routes: 101 | ok: 57 | server-error: 0 | skipped-needs-fixture: 42 | unclassified: 2`
  (los 2 unclassified son los preexistentes de query params:
  `GET api/patients/search` 422, `GET api/specialty-records` 400). Sin nuevos
  server-error ni rutas caídas vs baseline.

## Espejo Engram

Observación pendiente (el padre no validó un nombre de proyecto para persistir).
