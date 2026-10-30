# Diseño de sistema — OdontoSuite V2

> **Estado medido**: `main` @ `3cddce9d` (2026-10-29). Los conteos de este
> documento se midieron sobre ese árbol y se declaran con su símbolo, no con un
> número de línea: un conteo sin fuente envejece en silencio.

## 0. Cómo leer este documento

El repositorio tiene tres capas de documentación y cada una responde una
pregunta distinta. Este documento no las reemplaza: las conecta.

| Capa       | Documento                                      | Responde                                                           |
| ---------- | ---------------------------------------------- | ------------------------------------------------------------------ |
| Operativa  | `AGENTS.md`                                    | ¿Qué hay? ¿Cómo lo levanto? ¿Qué comandos existen?                 |
| Diseño     | **este documento**                             | ¿Por qué está partido así? ¿Qué garantiza cada pieza? ¿Dónde toco? |
| Decisiones | `docs/architecture/decisions/` (ADR 0007-0018) | ¿Por qué se eligió esto y qué se descartó?                         |

Regla de lectura: cuando este documento y el código discrepen, **gana el
código**, y la discrepancia es un defecto de este documento. Por eso cada
afirmación estructural nombra la clase, la tabla o la ruta que la sostiene.

Fuera de este documento, a propósito: el detalle de cada issue
(`docs/mejoras/`), el inventario de pantallas (`docs/ux-guidelines.md`) y la
matriz de escritorios (`docs/architecture/desks.md`).

---

## 1. Contexto

### 1.1 Qué es el sistema

OdontoSuite es el sistema de operación de una clínica odontológica peruana:
recibe al paciente, agenda su cita, documenta y **firma** el acto clínico,
cobra, mueve la gaveta, deja la continuidad del tratamiento y mide el negocio.
Un solo despliegue (Laravel + MySQL) sirve una SPA Vue, y un proceso hermano
(Reverb) empuja los cambios en vivo.

El dominio no es genérico: la forma del sistema la imponen cuatro reglas
externas.

| Regla externa                             | Efecto en el diseño                                                                                                |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| NTS 139 / NTS 188 (registro odontológico) | El acto clínico es un documento firmado con hash de integridad y CIE-10 (ADR-0014)                                 |
| IGV peruano                               | El precio de lista es el precio del paciente; el impuesto se **descompone**, no se suma (ADR-0011)                 |
| Multi-sede real                           | La sede es dimensión de autorización, no filtro de consulta (ADR-0010)                                             |
| Dinero auditable                          | Cargo, pago, condonación, devolución y ajuste son conceptos distintos; sólo el efectivo mueve la gaveta (ADR-0015) |

### 1.2 Actores

Siete roles (`Permissions::ROLES`), agrupados en seis escritorios (`Desks`). Un
escritorio es dónde ocurre el trabajo, no una unidad de autorización nueva: las
rutas siguen declarando permisos.

| Rol              | Escritorio     | Qué hace en una frase                                                    |
| ---------------- | -------------- | ------------------------------------------------------------------------ |
| `administrador`  | administración | Configura, autoriza y ve todo; con sede, es el administrador de esa sede |
| `recepcionista`  | recepción      | Recibe, agenda, cobra y materializa la continuidad                       |
| `odontologo`     | clínico        | Documenta y firma el acto, prescribe el plan                             |
| `implantologo`   | clínico        | Igual, con el registro de implantología                                  |
| `asistente`      | asistente      | Apoya el acto clínico sin firmarlo                                       |
| `tecnico_dental` | laboratorio    | Ejecuta el caso protésico; no lee narrativa clínica (ADR-0016)           |
| `finanzas`       | finanzas       | Lee y opera el dinero; no escribe clínica                                |

### 1.3 Límites declarados

Lo que el sistema **no** hace hoy, y está escrito para que nadie lo suponga:

- Archivo por antigüedad (5 años activos / 15 pasivos) y firma con certificado
  digital. La firma vigente es electrónica simple (contraseña revalidada +
  SHA-256).
- Comprobante fiscal / SUNAT y pago mixto.
- Reescritura del esquema en SQLite: `migrate:fresh` es sólo-MySQL por decisión
  (issue #79) y los tests que tocan la base se **saltan**, no fallan, sin motor
  real (`Tests\Support\MysqlRunner`).

---

## 2. Vista de contenedores

```
                        ┌──────────────────────────────────────────────┐
   Navegador            │  SPA Vue 3 (25 módulos, 46 composables)      │
   (Chrome/Edge)  ─────►│  build estático servido por Laravel / Vite   │
                        └───────┬───────────────────────┬──────────────┘
              HTTPS + Bearer    │                       │  WSS (Echo → Pusher protocol)
              JSON (Sanctum)    │                       │
                                ▼                       ▼
                    ┌───────────────────────┐   ┌─────────────────────┐
                    │ Laravel 12 (PHP 8.2)  │   │ Reverb 1.6          │
                    │ · /api/*  (263 rutas) │◄──┤ · canales privados  │
                    │ · shell web (catch-all)│   │ · auth por permiso  │
                    │ · colas, scheduler    │   └─────────────────────┘
                    └───┬────────┬──────────┘
                        │        │
                  ┌─────▼──┐  ┌──▼──────────────────────────────┐
                  │ MySQL 8│  │ Servicios externos              │
                  │ 156 mi-│  │ · OpenAI (análisis asistivo)    │
                  │ gracio-│  │ · Mercado Pago (webhook + SDK)  │
                  │ nes    │  │ · SMTP/log (correo)             │
                  └────────┘  └─────────────────────────────────┘
```

**Por qué un monolito y no servicios.** Tres razones concretas, no una
preferencia: el cierre de la atención escribe en nueve tablas dentro de una
transacción (acto, evolución, diagnósticos, odontograma, materiales, movimientos
de stock, adjuntos, cargo y proyección de la cita); el dinero exige lecturas
bloqueadas del mismo motor que el ledger (`cash_register_sessions` es el lock
compartido de pago, devolución, depósito y cierre); y el presupuesto de revisión
es de una persona. Partir eso en servicios cambiaría corrección por despliegue.

Elementos del contenedor:

| Elemento     | Implementación                                  | Papel                                         |
| ------------ | ----------------------------------------------- | --------------------------------------------- |
| Shell web    | `routes/web.php` catch-all → `view('app')`      | Sirve la SPA en cualquier ruta no-`api`       |
| API          | `routes/api.php` (263 URIs)                     | Todo el dominio, autenticado con Sanctum      |
| Tiempo real  | `laravel/reverb` + `BroadcastingAuthController` | Eventos de dominio en canales privados        |
| Persistencia | MySQL 8 (driver único de producción y de tests) | Verdad única del dinero y la clínica          |
| Colas        | driver `database` (`QUEUE_CONNECTION`)          | Export de expediente, webhook de Mercado Pago |
| Storage      | disco `public` + `storage:link`                 | Adjuntos clínicos y PDFs                      |
| PDF          | `barryvdh/laravel-dompdf`                       | Registro de atención, arqueos, recibos        |

**Una sola autoridad por concepto.** El sistema está lleno de "una sola pluma" a
propósito, y es el patrón que más se repite en el código:

| Concepto                           | Autoridad única                                       |
| ---------------------------------- | ----------------------------------------------------- |
| Permisos y roles                   | `App\Authorization\Permissions`                       |
| Escritorios                        | `App\Authorization\Desks` (derivado de `Permissions`) |
| Alcance de sede                    | `App\Authorization\BranchAccess`                      |
| Canales de difusión                | `App\Authorization\BroadcastChannels`                 |
| Vocabulario del dinero             | `App\Support\MoneyVocabulary`                         |
| Saldo de una atención              | `App\Support\AppointmentBalance`                      |
| Tarifa aplicable                   | `App\Services\ProcedurePriceResolver`                 |
| IGV                                | `App\Support\QuotationTax` + `config/billing.php`     |
| Sitio de un procedimiento          | `App\Support\ProcedureSiteRule`                       |
| Firma clínica                      | `App\Support\ClinicalRecordSignature`                 |
| Formato de dinero/fecha/hora (SPA) | `resources/js/composables/useFormatters.js`           |

---

## 3. Ciclo de una petición

### 3.1 Pipeline

```
POST /api/...  (Authorization: Bearer <sanctum>)
   │
   ├─ auth:sanctum ................... ¿hay usuario? si no → 401
   ├─ permission:<verbo.recurso> ..... ¿el rol lo tiene? si no → 403  (CheckPermission)
   ├─ EnforceBranchScope ............. ¿la sede del payload/ruta es suya? si no → 403
   ├─ cash.session (por ruta) ........ ¿gaveta abierta? si no → 422 NO_ACTIVE_SESSION
   │
   ├─ Controller ..................... valida forma, delega, nunca decide reglas de dinero
   ├─ Service ........................ regla de negocio + transacción + locks
   │     ├─ Model .................... invariantes de fila (guards, casts, scopes)
   │     ├─ Support .................. reglas puras reutilizables (Money, Tax, Balance)
   │     └─ Event .................... try/catch → listener → broadcast/auditoría
   └─ Resource ....................... forma de salida (13 Resources)
```

Orden importa. `EnforceBranchScope` se agrega al grupo `api` **después** de
`SubstituteBindings` y antes del controller, porque los controllers atrapan
`\Throwable` y responderían 500 donde corresponde 403 o 404
(`bootstrap/app.php`).

### 3.2 Envoltorios

| Caso                | Cuerpo                                                                            |
| ------------------- | --------------------------------------------------------------------------------- |
| Éxito               | `{ "data": ..., "meta": { "message": "..." } }`                                   |
| Validación          | `422 {"message": "Los datos proporcionados no son válidos.", "errors": {...}}`    |
| Regla de negocio    | `422 {"message": "<motivo en español>"}` (+ `errors.<campo>` cuando es por campo) |
| No autenticado      | `401 {"message": "No autenticado."}`                                              |
| Sin permiso         | `403 {"message": "No tienes permisos para acceder a este recurso."}`              |
| No existe           | `404 {"message": "El recurso solicitado no fue encontrado."}`                     |
| Estado incompatible | `409` (conflicto de unicidad, solicitud abierta, libro bloqueado)                 |
| Bloqueo contable    | `403 {"code": "accounting_lock_blocked", "message": ...}`                         |
| Falla inesperada    | `500 {"message": "Error interno del servidor."}` + `Log::error` con traza         |

`error` sólo viaja con `APP_DEBUG=true`: la traza jamás sale en producción.

### 3.3 Dónde vive cada cosa

| Responsabilidad                               | Vive en                                | No vive en       |
| --------------------------------------------- | -------------------------------------- | ---------------- |
| Forma y presencia de campos                   | Controller / FormRequest               | Modelo           |
| Regla de negocio y atomicidad                 | Service                                | Controller       |
| Invariante de fila (inmutable, único, estado) | Modelo (guards de `saving`/`deleting`) | Service          |
| Regla pura y reutilizable                     | `App\Support\*`                        | Service concreto |
| Forma de la respuesta                         | Resource / array del controller        | Modelo           |

Excepciones de dominio ya nombradas, por si se necesita capturarlas:
`AccountingLockBlockedException` (lleva el código de máquina) y las familias
`Clinical`, `Consultation`, `Custody`, `Encounter`, `Inventory`, `Money`,
`Transfer` bajo `app/Exceptions/`.

---

## 4. Mapa de dominios

Diez contextos. Cada uno tiene un dueño claro y una invariante que no comparte.

| #   | Contexto             | Modelos troncales                                                                                             | Servicio de entrada                                                        | Invariante que protege                                                          |
| --- | -------------------- | ------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| 1   | Identidad y acceso   | `User`, `BranchTransferRequest`, `AuditLog`                                                                   | `BranchTransferService`                                                    | Nadie cambia su propia sede; el traslado es solicitud aprobada (ADR-0017)       |
| 2   | Catálogo maestro     | `ProcedureCatalog`, `ProcedurePrice`, `Payer`, `Specialty`, `DentalPiece`, `ToothSurface`                     | `ProcedureCatalogService`, `ProcedurePriceResolver`                        | El precio emitido no se recalcula; la tarifa nombra su origen                   |
| 3   | Agenda y custodia    | `Appointment`, `AppointmentCustodyLog`, `Reminder`                                                            | `AppointmentService`, `AppointmentCustodyService`                          | Un slot ocupado no se duplica; la custodia sólo avanza por sus puertas          |
| 4   | Acto clínico         | `ClinicalEncounter`, `MedicalRecord`, `ClinicalEvolution`, `ClinicalAddendum`                                 | `ConsultationService`, `ClinicalEncounterService`                          | El acto se firma una vez y la historia no se borra ni se edita                  |
| 5   | Dinero               | `Transaction`, `PatientCharge`, `PatientChargeAllocation`, `CashRegisterSession`, `CashDeposit`               | `TransactionService`, `PatientStatementService`, `CashRegisterService`     | Cargos y transacciones son libros inmutables; sólo el efectivo mueve la gaveta  |
| 6   | Planes y continuidad | `TreatmentPlan`, `TreatmentPlanVersion`, `TreatmentPlanItemConsumption`, `PatientRecall`, `ContinuityRequest` | `TreatmentPlanService`, `TreatmentPlanVersionService`, `ContinuityService` | El plan aceptado es una versión inmutable; el consumo deriva el estado del ítem |
| 7   | Inventario           | `Product`, `StockMovement`, `ProcedureMaterial`                                                               | `InventoryService`                                                         | El stock baja una vez por producto y sin stock no se cierra el acto             |
| 8   | Laboratorio          | `LabCase`                                                                                                     | `LabCaseService`                                                           | La fase sólo avanza; el técnico sólo edita su caso                              |
| 9   | Reportes / BI        | — (proyecciones)                                                                                              | 9 servicios en `app/Services/Reports/`                                     | Un desglose cuadra con su resumen, y el dinero sin atribución cae en «Sin sede» |
| 10  | Plataforma           | `AuditLog`, eventos, jobs                                                                                     | `CacheService`, listeners                                                  | Toda lectura clínica se audita; ningún canal público transporta dominio         |

### 4.1 Identidad y acceso

`users.branch_id` es la afiliación: con valor, el usuario opera esa sede; sin
valor tiene alcance de grupo. El rol se lee en `Permissions::ROLES` y cada
capacidad en `Permissions::roleHas()`. El traslado entre sedes no edita la fila:
nace `branch_transfer_requests` con motivo y estado, y **la aprobación** es la
única escritura de `users.branch_id` fuera de `PUT /api/users/{id}`, en la misma
transacción que la auditoría (`user_branch_assigned`). Un guard estático
(`BranchAffiliationWriterTest`) falla si aparece una segunda pluma.

Toda escritura sensible deja fila en `audit_logs` con actor y metadatos. La
lectura clínica también: `medical_record_viewed` con `metadata.surface`; si la
auditoría falla, la lectura falla (`ClinicalRecordAuditService`).

### 4.2 Catálogo maestro

El catálogo no es un nomenclador: declara **sobre qué** se hace un procedimiento
(`site_type`: `none|tooth|surface|quadrant|arch`), sus códigos externos (RM
550-2023/MINSA y SIS), su receta de insumos y su tarifa. La tarifa vive en una
sola tabla, `procedure_prices`, con sede y convenio opcionales y ventana de
vigencia; la cascada es convenio+sede → convenio → sede → `default_cost`, y el
resultado nombra su origen y la fila aplicada (`ProcedurePriceResolver`).

El catálogo se versiona (`procedure_catalog_versions`, por listener) y se
traduce (`procedure_catalog_translations`). Una línea de plan o presupuesto que
nombra un procedimiento con sitio **debe** declararlo (`ProcedureSiteRule`); sin
sitio responde 422 nombrando el procedimiento.

### 4.3 Agenda y custodia

La cita no carga el acto clínico: es una **proyección** de la atención. Los
estados de custodia (`arrived`, `in_consultation`) sólo se alcanzan por sus
puertas — `check-in`, `start-attention`, `complete`— y cada transición escribe
`appointment_custody_logs`. `PATCH /status` rechaza con 422 los estados de
custodia nombrando la puerta real.

La unicidad del slot está en el esquema, no en el servicio:
`appointments.slot_reservation` es una columna generada que vale NULL para
`cancelled` y 1 para el resto, e integra `unique_user_time_slot` y
`unique_chair_time_slot`. Cancelar libera el horario por construcción, y las
cuatro puertas que escriben horario traducen la violación de unicidad a
`422 conflict.type=slot_taken`, nunca 500.

### 4.4 Acto clínico y registro

`clinical_encounters` es la entidad del acto, con estado propio
(`open → closed | voided`), y la cita es su proyección. El cierre es único y
ejecuta `ConsultationService::close()`: la misma implementación para la cita
(`/appointments/{id}/complete`) y para la atención
(`/encounters/{id}/complete`).

```
open ──close (firma + motivo + CIE-10)──► closed        (proyecta status/final_amount en la cita)
  └──void (sólo si abierta, con motivo)─► voided
```

La historia clínica es inmutable (ADR-0014): no hay ruta que elimine una
historia o una evolución, el modelo rechaza `deleting`, y corregir **agrega** un
addendum firmado con autor, fecha y motivo. La anulación de un análisis de IA
conserva la traza de revisión (`voided_at` sin borrar `reviewed_by`).

### 4.5 Dinero

Es el contexto más denso y el que más invariantes concentra. Se lee mejor como
tres libros que no se mezclan:

| Libro              | Tabla                                                         | Qué registra                          | Mutabilidad                                    |
| ------------------ | ------------------------------------------------------------- | ------------------------------------- | ---------------------------------------------- |
| Deuda del paciente | `patient_charges` + `patient_charge_allocations`              | Qué se debe y qué pago lo cubre       | Inmutable: se corrige con ajuste               |
| Transacciones      | `transactions`                                                | Pago, devolución, condonación, ajuste | Se anulan (nunca se editan montos)             |
| Gaveta             | `cash_register_sessions` + `cash_movements` + `cash_deposits` | Papel físico del turno                | Sólo efectivo; el arqueo guardado es histórico |

El vocabulario es de `MoneyVocabulary`: cuatro tipos escribibles
(`payment|refund|write_off|adjustment`), su efecto en el saldo y si mueven
efectivo. `discount` ya no es un tipo de transacción —vive en el cobro— y
`income`/`expense` quedan como valores legacy sin puerta de escritura.

Reglas que el servicio hace cumplir y que ningún controller puede saltarse:

- Un pago o una devolución exigen método y sesión de caja **abierta**; una
  condonación o un ajuste, autorización de administrador registrada
  (`authorized_by` + `authorization_reason`) y no mueven gaveta.
- La devolución está topada por lo cobrado no devuelto del paciente y por su
  pago padre; la condonación, por el saldo pendiente.
- Un método con `requires_reference` rechaza el cobro sin número de referencia.
- El bloqueo contable (`accounting_locks`) se evalúa **antes** de abrir la
  transacción, sobre la fecha contable del hecho (no la de escritura), y un
  override deja `accounting_lock_overridden` con su motivo.

El saldo de una atención tiene una sola definición (`AppointmentBalance`):
`final_amount` si existe, si no `total_cost`, menos los pagos `completed`. La
leen «Listos para facturar», el desglose y la puerta de cobro de pendientes.

### 4.6 Planes y continuidad

El plan aceptado es una **versión inmutable** (`treatment_plan_versions`) y el
presupuesto aprobado queda atado a la versión aceptada
(`quotations.treatment_plan_version_id`): editar el plan después de la firma
abre una versión nueva sin tocar lo aceptado. Los ítems tienen prioridad y
secuencia, y admiten alternativas A/B por pieza con rechazo fechado y motivo.

La ejecución no se escribe a mano: `treatment_plan_item_consumptions` liga el
acto al ítem y el estado se **deriva** (`completed` si cubre `quantity`,
`in_progress` si cubre parte, `pending` sin consumo). El cierre de la atención
ya no inventa la próxima cita: deja una `continuity_request` que recepción
materializa con la validación de conflictos de la agenda.

### 4.7 Inventario

El procedimiento declara lo que consume (`procedure_catalog_materials`) y el
cierre lo descuenta: `InventoryService::consumeForEncounter` bloquea productos
en orden de `id`, evalúa la demanda **agregada** por insumo, congela el costo
unitario y escribe un `ProcedureMaterial` y un `StockMovement` `out` por lote.
Sin stock suficiente responde 422 y **no escribe nada** (ni evolución, ni
material, ni movimiento).

`products.stock_quantity` es la única verdad del saldo y las cantidades son
`decimal(12,3)`: media jeringa entra como media jeringa.

### 4.8 Laboratorio

`lab_cases` es la orden de trabajo del taller —paciente, pieza, trabajo,
material, fechas, técnico— con cinco fases que sólo avanzan
(`in_preparation → in_laboratory → try_in → delivered → placed`). La fase no se
escribe por `POST`/`PUT`: la única puerta es `POST /api/lab-cases/{id}/phase`.
Entrar en `delivered` emite `LabCaseReady` y el caso aparece en
`GET /api/my-day`.

El escritorio de laboratorio no lee la historia clínica, el acto ni los
registros de especialidad (ADR-0016): su insumo es el caso, el plan y la agenda
operativa.

### 4.9 Reportes / BI

Nueve servicios de lectura en `app/Services/Reports/` más `ReportController`.
Dos reglas los gobiernan: **una sola definición de ingresos** (transacciones
cobradas, atribuidas por `appointment_id`) y **todo desglose cuadra con su
resumen** —el dinero que no resuelve cae en «Sin sede» o «Sin cita asociada»—.
El filtro de sede usa `BranchAccess::effectiveFilter` y pedir otra sede es 403.

Los tres reportes tabulares que publican su censo completo suman las filas ya
materializadas (`SummarizesPeriodActivity`) y publican `meta.summary`; con eso
la pantalla distingue «período sin actividad» de «tabla vacía».

### 4.10 Plataforma

Eventos de dominio (37) → listeners (10 clases, 13 cableos en
`AppServiceProvider`) → auditoría y difusión. Jobs: `ExportPatientFileJob` y
`ProcessMercadoPagoWebhook`. Caché de dashboard por listener
(`ClearDashboardCache`), no por TTL adivinado.

---

## 5. Modelo de datos: invariantes

El esquema no es un detalle de implementación: es donde viven las garantías que
el servicio no puede perder entre dos peticiones.

| Invariante                                                          | Dónde se sostiene                                                                          | Qué evita                                  |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ | ------------------------------------------ |
| Un cargo por atención                                               | `patient_charges.clinical_encounter_id` único                                              | Cobrar dos veces el mismo acto             |
| Un pago no cubre más de su importe, un cargo no recibe más del suyo | `patient_charge_allocations` único por par + sumas sobre filas bloqueadas en orden de `id` | Doble imputación concurrente               |
| Un slot no se duplica                                               | `appointments.slot_reservation` en los dos índices únicos                                  | Dos citas en la misma silla/hora           |
| El acto se cierra una vez                                           | `clinical_encounters.status` + locks de fila                                               | Cierre doble y doble cargo                 |
| El plan aceptado no cambia                                          | `treatment_plan_versions` inmutable + `current_version_id`/`active_version_id`             | Reescritura de lo firmado                  |
| El precio emitido no se recalcula                                   | evidencia congelada en la línea (`price_source`, `price_row_id`, `price_from/to`)          | Que un cambio de tarifa mueva presupuestos |
| Los libros no se editan                                             | guards de modelo en `PatientCharge` / `ClinicalAddendum` / historia                        | Corrección silenciosa                      |
| El stock baja una vez                                               | `products.stock_quantity` + movimiento por lote dentro de la transacción del cierre        | Descuadre de inventario                    |
| Toda lectura clínica se audita                                      | `ClinicalRecordAuditService` en cada superficie                                            | Lectura sin rastro                         |
| La sede del actor se estampa                                        | `ScopesToBranch` + `EnforceBranchScope`                                                    | Filas huérfanas de sede                    |

Convenciones que sostienen lo anterior:

- **Migraciones aditivas** desde 2026-08-05 (`SddCheckMigrationsTest`):
  `Schema::create` o `Schema::table` con columnas nullable/índices;
  `DROP COLUMN` sólo en `down()` o bajo el prefijo `drop_legacy_*`. Los
  constructos MySQL-only van con guarda de driver.
- **Soft deletes** en 13 modelos; la historia clínica y sus evoluciones ya no,
  porque su corrección es un addendum.
- **Montos** `decimal(12,2)`; cantidades `decimal(12,3)`; fechas de calendario
  con `date`, instantes con `datetime` en la zona de la sesión.

---

## 6. Flujos críticos

### 6.1 Cierre de la atención — el flujo que explica el sistema

Es el punto donde nueve escrituras deben ocurrir o ninguna.

```
Profesional (Mi día / Calendario)
 1. POST /encounters/{id}/complete     (o /appointments/{id}/complete)
 2. ConsultationService::close()
      ├─ lock del acto y de la cita
      ├─ valida motivo de consulta + ≥1 diagnóstico CIE-10 + firma
      ├─ verifica credenciales del profesional y sella SHA-256 del contenido
      ├─ InventoryService::consumeForEncounter()  ← 422 sin stock, rollback total
      ├─ escribe evolución SOAP + diagnósticos + odontograma + adjuntos
      ├─ consume ítems del plan (treatment_plan_item_consumptions)
      ├─ emite PatientChargeService (1 cargo, importe final_amount, fecha closed_at)
      ├─ deja continuity_request y/o patient_recall
      └─ proyecta en la cita: status, completed_at, final_amount, mode
 3. Evento AppointmentCompleted → listeners (auditoría, transacción pendiente, caché)
```

Tres detalles que suelen sorprender al trabajar el código:

1. El **modo** describe la documentación, no la urgencia: una urgencia se
   reconoce por `appointment_id` nulo, no por un enum distinto.
2. El **plan queda fuera del hash**: sigue vivo después del acto, así que
   firmarlo obligaría a refirmar cada vez que se toca.
3. El cierre **no** escribe `appointments.treatment_notes` (conducta clínica):
   desde #314 ese campo sólo se publica a quien lee la historia.

### 6.2 Dinero — cobrar, imputar, cuadrar

```
POST /transactions (payment)
  guarda de bloqueo contable → sesión abierta → método/referencia
  → transacción ACID: transactions + cash_movement (sólo efectivo) + allocations
  → evento TransactionCreated / PaymentReceived

GET /patients/{id}/statement
  cargos − descuentos − pagos + devoluciones − condonaciones + ajustes
  opening_balance + entradas del período = closing_balance
  charge_balances + unallocated_payments (la imputación es una vista, no otro dinero)

Cierre del turno
  POST /cash-register/{id}/close → arqueo; deposists se concilian
  daily report: reconciliation.cash / .electronic / .deposits,
                pending_deposit = cash − deposits
```

Una devolución libera cobertura en proporción al pago que la originó (reparto en
centavos con mayor residuo). Anular un pago libera lo reservado. Un cobro con
tarjeta, transferencia o billetera queda en `transactions` y en el estado de
cuenta pero **no** escribe movimiento de gaveta; anularlo tampoco la mueve.

### 6.3 Del plan al presupuesto aprobado

```
plan (líneas con prioridad, secuencia, sitio, alternativas A/B)
  → versión inmutable al enviarse a aprobación
  → presupuesto: cada línea de catálogo congela precio vigente en su quotation_date
  → aprobación del paciente: fija treatment_plan_version_id en el presupuesto
  → editar el plan ahora abre versión nueva; lo aceptado no se toca
```

El IGV se descompone: `subtotal + tax_amount = total_amount`, con la tasa de
`config/billing.php`. Un `PATCH` parcial recalcula desde el bruto y la tasa
guardados, nunca desde cero.

### 6.4 Custodia de la cita y Mi día

```
scheduled ──check-in (recepción)──► arrived ──announce──► (sigue arrived; emite PatientReady)
                                   └─start-attention (clínico)─► in_consultation ──complete──► completed
cancelled / no_show  ← PATCH /status (con motivo, cancela recordatorios pendientes)
```

Cada transición escribe `appointment_custody_logs`. `GET /api/my-day` devuelve
`waiting`/`attending`/`upcoming` del profesional autenticado, más
`ready_lab_cases`: es el escritorio, no un reporte.

### 6.5 Continuidad

El cierre registra la conducta (`continuity_request`) o el recall (intervalo
3/6/12). Recepción materializa por `POST /continuity-requests/{id}/schedule` o
`POST /patient-recalls/{id}/schedule`, que delegan en `AppointmentService` y
validan profesional y sillón: un conflicto deja la solicitud pendiente sin cita.
La métrica de cumplimiento se publica tal cual (`compliance_rate`, `—` sin
denominador); el cliente no la recalcula.

### 6.6 Laboratorio, traslado de sede y análisis IA

| Flujo            | Puerta                                 | Efecto                                                                                 |
| ---------------- | -------------------------------------- | -------------------------------------------------------------------------------------- |
| Avanzar fase     | `POST /lab-cases/{id}/phase`           | Sólo fases posteriores; `delivered` emite `LabCaseReady`                               |
| Pedir traslado   | `POST /branch-transfers`               | Una sola solicitud abierta (409); origen copiado al pedir                              |
| Aprobar traslado | `POST /branch-transfers/{id}/approve`  | `decide.branch_transfer` + `branch.global`; escribe `users.branch_id` + auditoría      |
| Analizar imagen  | `POST /ai-analysis/upload-and-analyze` | Exige aviso al paciente, guarda costo y tope diario; el análisis es apoyo, no decisión |
| Anular análisis  | `POST /ai-analysis/{id}/void`          | Motivo obligatorio; 422 si está citado en una evolución o plan                         |

---

## 7. Autorización y multi-sede

### 7.1 Tres capas, un solo catálogo

1. **Catálogo** (`Permissions`): 93 permisos `verbo.recurso` y la matriz rol →
   permisos. `Permissions::roleHas()` es la única lectura.
2. **Ruta**: cada ruta declara `->middleware('permission:verbo.recurso')`, nunca
   `role:...`. `RoutePermissionMatrixTest` falla si una ruta vuelve a listar
   roles.
3. **SPA**: `usePermissions()` deriva sus claves `can.*` de `user.permissions`
   (login y `/api/auth/me`). No hay una segunda matriz en el cliente; una sesión
   de recepción no ve controles que la API le respondería 403.

El handshake de broadcasting usa el **mismo** catálogo: `BroadcastChannels`
resuelve cada canal al permiso que ya guarda su lectura, y `routes/channels.php`
es espejo documental —la autoridad efectiva es `BroadcastingAuthController`,
porque el proyecto no llama a `Broadcast::routes()`—.

### 7.2 Alcance por sede

`BranchAccess` responde cinco preguntas y todo lo demás la consulta:

| Pregunta                      | Método                                                      |
| ----------------------------- | ----------------------------------------------------------- |
| ¿Está restringido a una sede? | `isRestricted()`                                            |
| ¿Puede operar esta sede?      | `allows()` / `assertBranch()`                               |
| ¿Qué sedes ve?                | `allowedBranchIds()`                                        |
| ¿Qué valor aplico al filtro?  | `effectiveFilter()` (propia por omisión, 403 al pedir otra) |
| ¿Es alcance de grupo?         | `isGlobal()` / `assertGlobal()`                             |

Tres mecanismos lo aplican: el trait `ScopesToBranch` (estampado en altas y
binding acotado → 404), el middleware `EnforceBranchScope` (403 de un
`branch_id` ajeno, incluida una `{branch}` de ruta) y `RequireGlobalBranchScope`
para el catálogo de sedes.

Qué es compartido y qué no (ADR-0010): la **ficha del paciente y la historia
clínica** son compartidas entre sedes; agenda, sillones, caja, personal,
inventario y tarifario no. En una escritura `PUT/PATCH`, `branch_id` nulo es 403
(la sede no se limpia desde una sesión de sede); en `POST` significa «sin sede»
y el modelo estampa la del actor.

### 7.3 Bloqueo contable

`accounting_locks` guarda `locked_through` por módulo (`cash_transactions`,
`cash_movements`, `quotations`, `treatment_plans`) y sede, con la fila de la
sede por encima de la global. Mutar dinero con fecha contable anterior o igual
responde 403, deja `accounting_lock_blocked` y admite un `lock_override_reason`
(≥10 caracteres, sólo administrador) que deja `accounting_lock_overridden`.
Crear dinero de hoy nunca se bloquea.

---

## 8. Contratos de API

| Tema               | Convención                                                                                              |
| ------------------ | ------------------------------------------------------------------------------------------------------- |
| Autenticación      | Bearer Sanctum (`auth_token` en `localStorage`); 401 limpia token y usuario                             |
| Envoltorio         | `{data, meta:{message}}`; errores con `message` (+ `errors` por campo)                                  |
| Validación         | `422` con `errors.<campo>` en español (`lang/es/validation.php`)                                        |
| Paginación         | `per_page` acotado (1..100) + `current_page`/`last_page`/`per_page`/`total`                             |
| Filtros de texto   | `q` (el índice de catálogo lee `q`; `search` **no** existe)                                             |
| Booleanos en query | Se normalizan con `FILTER_VALIDATE_BOOLEAN` antes del `where`                                           |
| Fechas             | `YYYY-MM-DD` para calendario; ISO-8601 para instantes                                                   |
| Dinero             | Decimal serializado como número; el formato es del cliente                                              |
| Export             | CSV/SpreadsheetML/PDF según el reporte; el export respeta el mismo filtro                               |
| Códigos de máquina | Sólo cuando el cliente debe ramificar (`accounting_lock_blocked`, `NO_ACTIVE_SESSION`, `conflict.type`) |

Trampa recurrente: `new URLSearchParams({ search: undefined })` serializa la
cadena `"undefined"`. El cliente usa `buildQueryString`
(`resources/js/utils/query.js`), que omite `undefined`/`null` y conserva `''`,
`0` y `false`.

---

## 9. Tiempo real

Reverb transporta eventos de dominio por **canales privados**; el handshake es
`POST /api/broadcasting/auth` con los permisos del catálogo. Un canal público no
verifica identidad, así que no existe ninguno que transporte dominio.

| Canal                                                | Publica                             | Quién entra                                  |
| ---------------------------------------------------- | ----------------------------------- | -------------------------------------------- |
| `dashboard-updates`                                  | Citas, pacientes, transacciones     | Los siete roles (lectura de panel)           |
| `patients`                                           | Ficha y datos clínicos del paciente | Los siete roles                              |
| `appointments` / `reminders`                         | Agenda y recordatorios              | Clínicos y recepción                         |
| `medical-records`                                    | Evolución, addenda, adjuntos        | Clínica + administración (laboratorio fuera) |
| `specialty-records`                                  | Registro de especialidad            | Clínica + administración                     |
| `treatment-plans`                                    | Planes y versiones                  | Clínica + laboratorio + administración       |
| `quotations`                                         | Presupuestos (datos y montos)       | Admin, finanzas, clínica, recepción          |
| `cash-register`, `cash-session.{id}`, `transactions` | Caja y ledger                       | Admin, finanzas, recepción                   |
| `procedure-catalog`                                  | Catálogo                            | Los siete roles                              |
| `users`                                              | Altas y cambios de usuario          | Sólo administración                          |
| `lab-cases`                                          | Caso entregado                      | Quien lee el caso (finanzas fuera)           |
| `user.{id}`                                          | Export de expediente terminado      | Sólo el propio usuario                       |

Dos reglas que se rompen fácil y cuestan un bug:

1. **`leave()` necesita el nombre real** (`private-*`): Laravel Echo no agrega
   el prefijo. Liberar el nombre base es inerte y deja la suscripción viva.
2. **Un canal no varía su payload por rol.** Si un evento lleva narrativa
   clínica, viaja sólo por el canal de los escritorios que la leen, o se recorta
   antes de publicar (`AppointmentBroadcastPayload`).

---

## 10. Frontend

### 10.1 Shell y navegación

`resources/js/app.js` monta el router y el plugin de primitivas (`UiButton`,
`UiModal`, …). Todas las rutas exigen `requireAuth` salvo login; la visibilidad
de acciones se decide con `usePermissions()`, no con `meta.roles`. Dos detalles
del shell son contrato:

- `meta.preserveOnQuery`: la vista se re-monta por `$route.path` y no por
  `$route.fullPath`, para no perder el foco del buscador ni cerrar el modal que
  se acaba de abrir (`/patients`, `/procedure-catalog`, `/appointment-types`).
- Error de chunk tras un despliegue: se recarga una vez y se avisa; un bucle
  significa bug real.

### 10.2 Estado

46 composables **singleton**, sin Pinia. El patrón: el composable guarda el
estado del dominio en refs de módulo, expone acciones y `reset()`; la página
pinta estados (loading / error con reintento / vacío con acción) y no inventa
datos en vuelo.

### 10.3 Design system

`resources/js/design-system/tokens.js` es la fuente: Tailwind y
`resources/css/tokens.generated.css` salen del mismo módulo
(`scripts/build-tokens-css.mjs`). Encima viven 34 primitivas en
`resources/js/components/ui/`, registradas globalmente como `Ui*`.

Receta de página (la que siguen las superficies rediseñadas): hero band con
título y acción primaria → KPI strip → `UiFilterBar` con «Limpiar filtros» →
`UiDataTable` con su affordance de scroll → estados completos →
`UiModal`/`UiSheet` para editar. El vocabulario de acciones
(`Nuevo/Nueva <sustantivo>`, verbo + objeto, «Cancelar / Volver / Cerrar») está
en `docs/ux-guidelines.md` §9.

### 10.4 Formato, formularios y accesibilidad

| Necesidad           | Usa                                                                            | Prohibido                                  |
| ------------------- | ------------------------------------------------------------------------------ | ------------------------------------------ |
| Dinero, fecha, hora | `useFormatters` (`formatCurrency`, `formatDate`, `formatDateTime`, `dateOnly`) | `Intl.*`, `toLocale*`, prefijo `S/` a mano |
| Errores 422 del API | `validationErrors` + `useFieldValidation.applyServerErrors`                    | Toast genérico                             |
| Overlays            | `UiModal`/`UiSheet` con la pila compartida (`useOverlayStack`)                 | `z-50` propio, listener de Escape propio   |
| Encabezado          | Un solo `h1` visible por pantalla (página o topbar)                            | Dos `h1`                                   |

`Modal`/`Sheet` comparten una pila: sólo la superficie superior responde a
Escape y al clic de fondo, y el bloqueo de scroll se cuenta por superficie
abierta.

---

## 11. Configuración y entornos

| Concern      | Variables                                                                                                 | Nota                                                                |
| ------------ | --------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| App          | `APP_NAME`, `APP_ENV`, `APP_URL`, `APP_LOCALE=es`, `APP_DEBUG`                                            | `es` es el locale por omisión                                       |
| Base         | `DB_*`                                                                                                    | Producción y tests: MySQL 8. SQLite no construye el esquema         |
| Auth         | `SANCTUM_*`                                                                                               | Tokens bearer, sin cookies de SPA                                   |
| Tiempo real  | `REVERB_*`, `VITE_REVERB_*`, `BROADCAST_CONNECTION`                                                       | `null` en tests                                                     |
| Colas/caché  | `QUEUE_CONNECTION`, `CACHE_STORE`                                                                         | `database` en local; worker obligatorio                             |
| Correo       | `MAIL_MAILER`                                                                                             | `log` en local; el reset de contraseña escribe al log               |
| Facturación  | `BILLING_TAX_PERCENTAGE`                                                                                  | 18.00 por omisión; `0` es modo exento                               |
| Mercado Pago | `MERCADOPAGO_*`                                                                                           | Sandbox por omisión; el admin puede sobreescribir desde el catálogo |
| IA           | `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_PRICE_PER_1K_TOKENS`, `OPENAI_DAILY_ANALYSIS_LIMIT_PER_PATIENT` | `0` = sin tope; el costo se guarda por análisis                     |

Levantar todo: `composer dev` (server + reverb + queue + pail + vite). Verificar
base real: `vendor/bin/phpunit -c phpunit.mysql.xml` con `DB_*` del entorno; el
destino se fija por variables, porque `php artisan test --configuration=...`
reinyecta su propio `phpunit.xml` y devuelve código 1 aunque todo pase.

---

## 12. Pruebas y calidad

### 12.1 Pirámide real

| Nivel                          | Volumen medido                           | Qué prueba                                              |
| ------------------------------ | ---------------------------------------- | ------------------------------------------------------- |
| PHPUnit Unit (139 archivos)    | Estructura, contrato y guardas estáticas | Que la regla exista y nadie la duplique                 |
| PHPUnit Feature (228 archivos) | MySQL real                               | El comportamiento observable de punta a punta           |
| Vitest (176 archivos)          | SPA                                      | Composables, contratos de primitivas y smokes de página |
| Playwright (`tests/e2e`)       | Instrumento de auditoría                 | Superficie real: rutas, roles, diálogos, formatos       |

Última medición publicada de la suite completa (cierre de la auditoría 2026-10):
MySQL **2915 pruebas / 17853 aserciones**, Vitest **1564 pruebas**. Los conteos
son volátiles; lo estable es la regla: una suite PHP corre contra un motor real
y una suite JS no toca la red.

### 12.2 Guardas que sostienen el diseño

Estas pruebas no prueban features: prueban que el diseño no se degrade.

| Guarda                                                  | Qué impide                                        |
| ------------------------------------------------------- | ------------------------------------------------- |
| `RoutePermissionMatrixTest`                             | Que una ruta vuelva a declarar `role:`            |
| `PermissionCatalogTest` / `DeskMatrixTest`              | Un permiso sin escritorio o un rol sin escritorio |
| `FrontendPermissionContractTest`                        | Que el SPA invente una matriz de permisos         |
| `BroadcastChannelAuthorizationTest`                     | Un canal que acepte a quien su lectura rechaza    |
| `docs` + `doc-drift.mjs` (14 chequeos)                  | Que `AGENTS.md` derive del código                 |
| `ArchitectureDecisionsIndexTest`                        | Un ADR sin cuerpo o un enlace roto                |
| `SingleStatusBadgeTest`, `DataTableListInheritanceTest` | Una superficie que no herede la primitiva         |
| `CredentialsDocumentationTest`                          | Una credencial real colada en la documentación    |
| `SecretScanGateTest`                                    | Un secreto publicado en CI                        |
| `SddCheckMigrationsTest`                                | Una migración destructiva                         |
| `PhpunitAttributesOnlyTest`                             | Metadata de PHPUnit en doc-comments               |

### 12.3 CI

Cuatro jobs desacoplados: `quality` (sintaxis, Pint, ESLint, Vitest, Prettier),
`backend-tests` (MySQL 8 como servicio), `frontend-build` (`vite build` +
artifact), `security` (`gitleaks` fijado por checksum sobre los commits del
run).

### 12.4 Definición de terminado

Una unidad está terminada cuando: la regla tiene prueba que falla sin ella, la
prueba observa **todos** los efectos (código, mensaje, y que un rechazo no
escriba nada), los comandos/opciones tocados actualizan su ayuda y su
documentación, y la suite enfocada más la del módulo están en verde. Si el
cambio toca código compartido que otra función ya usaba, se añade una prueba de
que la conducta anterior sigue vigente.

---

## 13. Operación

| Necesidad                              | Comando                            | Seguridad                                    |
| -------------------------------------- | ---------------------------------- | -------------------------------------------- |
| Dar atención a citas históricas        | `encounters:backfill`              | `--dry-run`, idempotente                     |
| Dar cargo a atenciones cerradas viejas | `charges:backfill`                 | `--dry-run`, idempotente                     |
| Reparar gaveta electrónica histórica   | `cash:repair-electronic-movements` | `--dry-run`, no reescribe columnas de sesión |
| Reparar huecos de recordatorios        | `reminders:reconcile`              | Horario, sin duplicar                        |
| Vencer presupuestos                    | `quotations:expire`                | Diario                                       |
| Recalcular totales de plan             | `recalculate-plan-totals`          | Idempotente                                  |
| Diagnóstico de citas                   | `diagnose-appointments`            | Sólo lectura                                 |
| Verificar eventos huérfanos            | `sdd:check-events`                 | Reporta, no corrige                          |

Salud del proceso: `GET /up`. Fallas de API se registran con traza en
`storage/logs/laravel.log` (`Log::error('API Exception', …)`); el cliente recibe
siempre el mensaje genérico. Un worker de cola caído degrada export y webhook,
no el cobro; un Reverb caído degrada la actualización en vivo, no la lectura.

---

## 14. Cómo trabajar este proyecto

### 14.1 Agregar una capacidad de punta a punta

El orden importa: cada paso da por existente el anterior.

1. **Decide si es permiso nuevo.** Si la capacidad es una escritura nueva sobre
   un recurso existente, probablemente no lo es. Si lo es, `Permissions`
   (constante + matriz) y después `Desks` lo heredan.
2. **Modela el hecho, no la pantalla.** ¿Qué fila nueva representa la cosa? Si
   es inmutable (ledger, versión, addendum), el modelo rechaza
   `updating`/`deleting`.
3. **Migración aditiva** con índices para las invariantes (unicidad, slot, par).
4. **Regla en un Service** (o en `App\Support` si es pura) dentro de una
   transacción; locks en orden de `id`.
5. **Ruta con `permission:`**, dentro del grupo que ya agrupa su lectura si
   existe.
6. **Resource** sólo si la forma difiere del modelo o hay campos condicionales.
7. **Evento** si otro módulo debe reaccionar; `try/catch` y payload recortado si
   lleva clínica.
8. **Composable** (`useX`) con estado singleton y `reset()`; nunca axios
   directo.
9. **Página** con las primitivas y los cinco estados (inicial, loading, error
   con reintento, vacío con acción, datos).
10. **Pruebas**: feature MySQL para la regla, unit para la guarda, Vitest para
    el composable o el contrato de primitiva.
11. **Docs**: AGENTS.md §4/§5 si cambian conteos, ADR si es una decisión, este
    documento si cambia una frontera.
12. **Unidad de commit** ≤ ~400 líneas con su prueba y su doc; si toca un riesgo
    alto (datos, seguridad, contrato, concurrencia, entrega), su propio commit.

### 14.2 «Quiero tocar X» → empiezo en

| Quiero…                                      | Empiezo en                                                                 |
| -------------------------------------------- | -------------------------------------------------------------------------- |
| Añadir un permiso o cambiar quién puede algo | `app/Authorization/Permissions.php` (+ `Desks`, + `usePermissions`)        |
| Cambiar cómo se arma un saldo                | `app/Support/AppointmentBalance.php`                                       |
| Tocar el cobro, la devolución o la gaveta    | `app/Services/TransactionService.php` + `MoneyVocabulary`                  |
| Tocar el cierre del acto                     | `app/Services/ConsultationService.php`                                     |
| Cambiar el estado de una cita                | `AppointmentCustodyService` (y sus puertas)                                |
| Añadir un reporte                            | `app/Services/Reports/` + `ReportController`                               |
| Cambiar el precio aplicado                   | `app/Services/ProcedurePriceResolver.php`                                  |
| Añadir una pantalla                          | `resources/js/router` + `resources/js/modules/<área>/`                     |
| Cambiar un formato de dinero/fecha           | `resources/js/composables/useFormatters.js` (y su guard)                   |
| Añadir una primitiva UI                      | `resources/js/components/ui/` + `plugins/ui-components.js` + su guard      |
| Añadir un canal de tiempo real               | `app/Authorization/BroadcastChannels.php` + `routes/channels.php` (espejo) |
| Cambiar el contrato de una respuesta         | El `Resource` y el composable que lo lee                                   |

### 14.3 Trampas conocidas

1. **SQLite no construye el esquema.** Los tests con `RefreshDatabase` se
   saltan, no fallan: un verde local sin MySQL no prueba nada de base de datos.
2. **`php artisan test --configuration=phpunit.mysql.xml` miente** (WARN +
   código 1). Usa `vendor/bin/phpunit -c phpunit.mysql.xml` con `DB_*` del
   entorno.
3. **Windows + rutas largas**: `git config core.longpaths true` dentro del repo,
   o el tooling de revisión falla con un error opaco.
4. **`q`, no `search`.** El índice de catálogo lee `q`; `search` no existe.
5. **Query con `undefined`** se serializa como `"undefined"`; usa
   `buildQueryString`.
6. **`leave()` con el nombre real** del canal (`private-…`).
7. **Un canal no cambia su payload por rol.** Recorta o cambia de canal.
8. **`meta.preserveOnQuery`** en rutas que abren modales por query.
9. **Un solo `h1` visible** por pantalla; el topbar lo cede cuando la página lo
   pinta.
10. **`StoreAppointmentRequest` y `StoreSpecialtyRecordRequest`** no son
    migrables a FormRequest sin refactor: validan inline y omiten campos.
11. **Un `POST` con `branch_id: null`** significa «sin sede»; en `PUT/PATCH`
    es 403.
12. **El bloqueo contable se evalúa antes de abrir la transacción**, sobre la
    fecha del hecho, no sobre la de escritura.

### 14.4 Qué hacer cuando algo falla

Reproduce primero. El repo tiene tres instrumentos listos: la suite MySQL para
el backend, Vitest para el SPA y `tests/e2e/audit` para la superficie (con
`AUDIT_SCOPE` por ruta). Un fallo de producto se corrige con prueba que falla
antes y pasa después; un fallo del instrumento se corrige en el instrumento y se
declara.

---

## 15. Riesgos y deuda declarada

| Riesgo                                             | Impacto                                | Mitigación actual                                     | Dónde está declarado      |
| -------------------------------------------------- | -------------------------------------- | ----------------------------------------------------- | ------------------------- |
| Esquema sólo-MySQL                                 | Tests locales sin motor real se saltan | `MysqlRunner` + CI con MySQL 8                        | AGENTS.md §6, issue #79   |
| Retención y archivo por antigüedad                 | Cumplimiento a largo plazo             | Firma por acto; archivo no implementado               | §1.3, ADR-0014            |
| Firma electrónica simple                           | No sustituye certificado digital       | Contraseña revalidada + hash                          | ADR-0014                  |
| Sin comprobante SUNAT ni pago mixto                | Facturación fiscal fuera del sistema   | Fuera de alcance declarado                            | Issue #289                |
| Tarifario: UI de convenios incompleta en su origen | Administración de convenios por API    | Follow-up declarado en #304                           | ADR-0012                  |
| Reverb como proceso único                          | Sin actualización en vivo si cae       | La lectura no depende de él                           | §13                       |
| Migraciones destructivas                           | Pérdida de datos históricos            | Guarda `SddCheckMigrationsTest` + política aditiva    | `docs/database/schema.md` |
| Duplicación de reglas entre SPA y API              | Divergencia silenciosa                 | Guards de contrato (`FrontendPermissionContractTest`) | §12.2                     |

Deuda aceptada a propósito: `users.specialty` y
`procedure_catalog.legacy_specialty` siguen en la base como display
denormalizado (ADR-0007/0008); su plan de drop está escrito y no ejecutado.

---

## 16. Glosario

| Término                             | Significado en este sistema                                                                          |
| ----------------------------------- | ---------------------------------------------------------------------------------------------------- |
| **Atención** (`clinical_encounter`) | El acto clínico con estado propio; la cita lo proyecta                                               |
| **Acto firmado**                    | Atención cerrada con motivo, CIE-10, firma e hash de integridad                                      |
| **Escritorio**                      | Agrupación operativa de roles (recepción, clínico, asistente, laboratorio, finanzas, administración) |
| **Sede / alcance de grupo**         | `users.branch_id` con valor restringe; sin valor, alcance global                                     |
| **Convenio** (`payer`)              | Tercero con tarifa propia (aseguradora, empresa)                                                     |
| **Cargo**                           | Deuda del paciente por un acto; libro inmutable                                                      |
| **Imputación**                      | Vista que dice qué pago cubre qué cargo; no mueve dinero                                             |
| **Condonación** (`write_off`)       | Perdón de deuda autorizado; no mueve gaveta                                                          |
| **Ajuste** (`adjustment`)           | Corrección firmada del ledger; no mueve gaveta                                                       |
| **Tarifario**                       | `procedure_prices`: precio por sede y convenio con vigencia                                          |
| **Versión de plan**                 | Foto inmutable del plan al aceptarse                                                                 |
| **Recall**                          | Control programado por intervalo (3/6/12 meses)                                                      |
| **Solicitud de continuidad**        | Conducta que recepción materializa en cita                                                           |
| **Custodia**                        | Estados de la cita (`arrived`, `in_consultation`) y su bitácora                                      |
| **Bloqueo contable**                | Fecha de corte por módulo y sede que impide mutar dinero anterior                                    |

---

## Anexo A — Mapa de archivos

| Ruta                                   | Qué contiene                                                                                                                          |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Authorization/`                   | `Permissions`, `Desks`, `BranchAccess`, `BroadcastChannels`                                                                           |
| `app/Support/`                         | Reglas puras: dinero, saldo, IGV, firma, sitio, fechas                                                                                |
| `app/Services/`                        | 36 servicios de dominio + `Reports/` (9)                                                                                              |
| `app/Http/Controllers/Api/`            | 47 controllers + `Reports/ReportController`                                                                                           |
| `app/Http/Middleware/`                 | `CheckPermission`, `EnforceBranchScope`, `RequireGlobalBranchScope`, `RequireActiveCashSession`, `ThrottleLoginAttempts`, `CheckRole` |
| `app/Http/Resources/`                  | 13 recursos de salida                                                                                                                 |
| `app/Models/`                          | 71 modelos (+ `Concerns/ScopesToBranch`)                                                                                              |
| `app/Events/` + `app/Listeners/`       | 37 eventos, 10 listeners                                                                                                              |
| `app/Exceptions/`                      | Familias por dominio + `AccountingLockBlockedException`                                                                               |
| `routes/api.php`                       | 263 rutas, agrupadas por permiso                                                                                                      |
| `routes/channels.php`                  | Espejo documental de canales                                                                                                          |
| `resources/js/modules/`                | 25 módulos de dominio                                                                                                                 |
| `resources/js/composables/`            | 46 composables singleton                                                                                                              |
| `resources/js/components/ui/`          | 34 primitivas                                                                                                                         |
| `resources/js/design-system/tokens.js` | Fuente de los design tokens                                                                                                           |
| `database/migrations/`                 | 156 migraciones (aditivas desde 2026-08-05)                                                                                           |
| `database/seeders/`                    | 18 seeders activos                                                                                                                    |
| `tests/Unit` + `tests/Feature`         | 367 archivos de prueba PHP                                                                                                            |
| `tests/js` + `tests/e2e`               | 176 archivos Vitest + instrumento Playwright                                                                                          |
| `scripts/audit/`                       | `doc-drift`, `ui-pass`, sondas de API/RBAC/tiempo real                                                                                |
| `docs/architecture/decisions/`         | ADR 0007-0018 con índice verificado                                                                                                   |
