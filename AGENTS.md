# AGENTS.md — OdontoSuite V2

> **Lee este archivo primero antes de tocar el proyecto.** Contiene quickstart,
> stack, comandos, estructura, convenciones, troubleshooting y planes cerrados.
> Actualizado al 2026-10-21 tras el fix de #21 (docs drift: §4, §6, §11, §12 con
> valores medidos).

---

## 1. Quickstart

```bash
# 1. Clonar
git clone <repo> && cd OdontoSuite

# 1b. SOLO WINDOWS — rutas largas (obligatorio)
#     El repo tiene rutas anidadas que superan los 260 caracteres
#     (openspec/changes/archive/<nombre-largo>/specs/...). LongPathsEnabled=1
#     en el registro NO basta: git necesita su propio opt-in. Sin este paso la
#     app y los tests funcionan, pero el review tooling falla con un error
#     opaco (candidate-owner-preparation-failed) que cuesta horas diagnosticar.
git config core.longpaths true

# 2. Instalar dependencias
composer install
pnpm install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate

# 4. Levantar DB + datos demo
php artisan migrate --seed

# 5. Levantar todo (API + Reverb + Vite + queue + logs)
composer dev
# Equivale a: php artisan serve + php artisan reverb:start + queue:listen + pail + pnpm dev
```

**Credenciales demo**: ver `CREDENTIALS.md`, **rastreado en git a propósito**
(contiene credenciales DEMO generadas por un seeder público, no secretos). Todos
los usuarios con password `password123`, dominio `@test.com` (15 usuarios
seedados por `RoleBasedUsersSeeder`).

`tests/Unit/Documentation/CredentialsDocumentationTest.php` verifica el
documento **contra el seeder y en ambas direcciones**: ningún usuario sembrado
falta y ninguno documentado se inventa. Como el documento se rastrea, esa
verificación es la que impide que una credencial real se cuele ahí sin romper el
build.

---

## 2. Stack

| Capa            | Tecnología                                            | Versión      |
| --------------- | ----------------------------------------------------- | ------------ |
| Backend         | Laravel                                               | 12           |
| Frontend        | Vue 3 (Composition API + `<script setup>`)            | 3.3          |
| Build           | Vite                                                  | 5            |
| Estilos         | Tailwind 3 + design tokens                            | 3.3          |
| DB              | MySQL / MariaDB                                       | 8.0 / 10.3+  |
| Auth            | Sanctum (bearer tokens)                               | 4            |
| Real-time       | Reverb + Laravel Echo                                 | 1.6 / 1.16   |
| Calendario      | FullCalendar                                          | 6            |
| Gráficos        | Chart.js                                              | 4            |
| PDF             | DomPDF                                                | 3            |
| Export          | CSV (`fputcsv`) + SpreadsheetML `.xls` + PDF (DomPDF) | sin librería |
| Package manager | **pnpm** (NUNCA npm)                                  | 11           |

---

## 3. Comandos esenciales

```bash
# Backend
php artisan serve                 # API en :8000
php artisan reverb:start          # WebSocket
php artisan migrate --seed        # DB + datos demo
php artisan test                  # tests PHPUnit
php artisan route:list            # ver rutas API
php artisan tinker                # REPL

# Frontend
pnpm dev                          # Vite dev server (HMR)
pnpm build                        # build producción
pnpm lint:check                   # ESLint
pnpm format:check                 # Prettier

# Todo-en-uno
composer dev                      # concurrently: server + reverb + queue + pail + vite
```

---

## 4. Estructura clave

```
app/
  Http/Controllers/Api/    # 48 controllers API (47 en Api/ + 1 en Api/Reports/)
  Http/Middleware/         # CheckRole (alias 'role'), ThrottleLoginAttempts,
                           # RequireActiveCashSession (alias 'cash.session')
  Models/                  # 71 modelos Eloquent
  Services/                # 36 services + Reports/ (9 report services)
  Events/                  # 36 eventos (10 listener classes, 13 cableos en AppServiceProvider)
  Listeners/               # 10 listener classes; 13 cableos en AppServiceProvider::boot
  Mail/                    # PasswordResetMail (Mailable para forgot-password)
  Jobs/                    # ExportPatientFileJob, ClearDashboardCache

resources/js/
  app.js                   # entry + router (guards en router/auth.js)
  composables/             # 46 composables singleton
  components/
    ui/                    # 35 primitives (Button, Modal, DataTable, Toast, DentalMotifLayer, UiSectionEmpty, ...)
    layout/                # AppLayout, MobileMenu, FloatingActionButton
    procedures/            # ProcedureQuickPicker, ProcedureCatalogPicker, ProcedureSiteFields,
                           #   ImportCsvModal
  modules/                 # 25 módulos de dominio (ver §5)
  router/auth.js           # requireAuth, requireGuest (localStorage)
  design-system/tokens.js  # design tokens

database/
  migrations/              # 153 migraciones
  seeders/                 # 17 activos (RoleBasedUsersSeeder, BranchSeeder, PaymentMethodSeeder,
                           #   SpecialtySeeder, UserSpecialtySeeder, DiagnosisCatalogSeeder,
                           #   AppointmentTypeSeeder, EnvironmentSeeder, ProcedureCatalogSeeder,
                           #   PatientSeeder, SimpleAppointmentsSeeder, ReminderSchedulesSeeder,
                           #   CashRegisterSeeder, CompletedAppointmentsSeeder, DentalPieceSeeder,
                           #   ToothSurfaceSeeder, SpecialtyRecordSeeder)

routes/
  api.php                  # 261 rutas API (medido con php artisan route:list --json: uris api/…; 274 totales con web)
  web.php                  # catch-all que retorna view('app')

tests/
  Unit/                    # tests estructurales (Services, Controllers, Events, Models, Middleware, Documentation)
  Feature/Api/             # AuthTest (rate limiting, login)
docs/
  mejoras/                 # 3 planes cerrados (ver §9)
  architecture/decisions/  # índice de ADRs (README.md) + cuerpos 0007, 0008 y 0010-0015

.github/workflows/
  ci.yml                   # CI con 4 jobs: quality (lint), backend-tests (MySQL), frontend-build, security (gitleaks)
```

---

## 5. Módulos del frontend (25 módulos, 25 páginas)

| Módulo                    | Ruta                         | Roles                                 |
| ------------------------- | ---------------------------- | ------------------------------------- |
| Dashboard                 | `/dashboard`                 | todos                                 |
| Mi día                    | `/my-day`                    | admin, todos los clínicos             |
| Calendario                | `/calendar`                  | todos los clínicos                    |
| Pacientes                 | `/patients`                  | todos                                 |
| Profesionales             | `/professionals`             | admin                                 |
| Ambientes                 | `/environments`              | admin                                 |
| Tipos de cita             | `/appointment-types`         | admin                                 |
| Especialidades (catálogo) | `/specialties`               | admin                                 |
| Sucursales                | `/settings/branches`         | admin                                 |
| Traslados de sede         | `/branch-transfers`          | todos                                 |
| Métodos de pago           | `/settings/payment-methods`  | admin                                 |
| Caja                      | `/cash-register`             | admin, finanzas, recep                |
| BI                        | `/business-intelligence`     | admin, finanzas                       |
| Planes de tratamiento     | `/treatment-plans`           | clínicos                              |
| Presupuestos              | `/quotations`                | admin, finanzas, odonto, implant      |
| Historias clínicas        | `/medical-records`           | odontólogos, implantólogos, asistente |
| Registros especialidad    | `/specialty-records`         | clínicos                              |
| Análisis IA               | `/ai-analysis`               | admin, odonto, implantólogo           |
| Catálogo procedimientos   | `/procedure-catalog`         | admin                                 |
| Mis procedimientos        | `/my-procedures`             | clínicos (favoritos)                  |
| Recepción procedimientos  | `/reception-procedures`      | recep                                 |
| Estadísticas catálogo     | `/procedure-stats`           | admin, finanzas                       |
| Continuidad               | `/continuity`                | admin, recepción, clínicos            |
| Laboratorio protésico     | `/lab-cases`                 | admin, recepción, clínicos            |
| Bloqueo contable          | `/settings/accounting-locks` | admin                                 |

**Auth frontend**: el router NO tiene `meta.roles`. La API rechaza con 403 y el
frontend muestra toast. El control de visibilidad es por `AppLayout` (computed
`navigation` con `useAuth().hasRole(...)`).

**API client**: siempre `useApi().request/get/post/...`. NO usar axios directo.

---

## 6. Estado del proyecto (actualizado 2026-10-22)

### ✅ Todo funcional (3 planes cerrados, 66 hallazgos resueltos)

- Auth Sanctum completo (login/logout/me/refresh/forgot/reset con email real vía
  MAIL_MAILER=log)
- 71 modelos, 48 controllers API, 36 eventos (10 listener classes / 13 cableos
  en AppServiceProvider)
- 13 modelos con SoftDeletes (la historia clínica y sus evoluciones ya no lo
  usan: #292)
- Historia clínica inmutable (#292): ninguna ruta elimina una historia ni una
  evolución y el modelo rechaza `deleting`; corregir crea un addendum firmado
  (autor, fecha, motivo) en `clinical_addenda`, y toda lectura de contenido
  clínico queda en `audit_logs` (`medical_record_viewed` + `metadata.surface`)
- Análisis IA como apoyo a la decisión (#296): el análisis cuelga de la atención
  (`clinical_encounter_id`) además del adjunto y se anula con motivo
  (`voided_at`/`voided_by`/`void_reason`) sin perder la traza de revisión;
  `DELETE /api/ai-analysis/{id}` ya no existe y
  `POST /api/ai-analysis/{id}/void` la reemplaza. Las dos puertas de alta exigen
  la declaración del aviso al paciente
  (`notice_acknowledged_at`/`notice_acknowledged_by`) y guardan el costo
  estimado (`estimated_cost`, USD) con un tope diario por paciente configurable.
  Un análisis solo entra al registro por una cita revisada
  (`ai_analysis_citations`, evolución o plan) y `tecnico_dental` recibe 403 en
  todo el módulo.
- Multi-rol con middleware `permission:` (alias `CheckPermission`),
  `cash.session`
- Multi-sede parcial (filtros en 6 controllers)
- Alcance por sede en BI completo (#303): los seis reportes y su exportación
  aceptan `branch_id` resuelto con
  `App\Authorization\BranchAccess::effectiveFilter` (sede propia por omisión,
  403 al pedir otra) y el reporte de ingresos gana el corte «Por Sede», que
  agrupa por la sede del cajón, deja el dinero sin cajón en `Sin sede` y suma
  exactamente el resumen del período. La atribución de un reporte de período por
  sede es la sede donde ocurrió la atención (ADR-0010).
- Superficie de la sede (#304): `/settings/accounting-locks` administra el
  bloqueo contable por módulo y sede (fila global de gerencia vs. sede propia),
  el detalle del catálogo edita el tarifario por sede con vigencia y estado, y
  BI publica el selector de sede (solo alcance de grupo) y el bloque consolidado
  con su doble desglose. Gobiernan `can.manageAccountingLock`,
  `can.manageBranchPrice` y `can.viewReports`; el 403 del bloqueo contable se
  avisa en línea en los modales de dinero con reintento autorizado por motivo.
- Calendario con FullCalendar, bloques, waiting list
- Caja completa (apertura/cierre, movimientos, arqueos, PDF)
- Vocabulario del dinero y ledger del paciente (#287):
  `App\Support\MoneyVocabulary` es la única fuente de los cuatro tipos
  escribibles (`payment|refund|write_off|adjustment`), de su efecto en el saldo
  y de si mueven efectivo. `discount` deja de ser un tipo de transacción —el
  descuento vive en el cobro (`discount_amount`/`discount_type`)— y
  `income`/`expense` quedan como valores legacy de la columna, sin puerta de
  escritura. Sólo pago y devolución exigen método y caja abierta y escriben
  `cash_movements`; la condonación (`write_off`) y el ajuste (`adjustment`,
  firmado) son asientos de ledger con autorización de administrador registrada
  (`authorized_by` + `authorization_reason`) que no exigen método, no exigen
  gaveta abierta y no cuentan como ingreso ni como cobro. El cierre de la
  atención escribe exactamente un cargo en `patient_charges` (único por
  `clinical_encounter_id`, importe `final_amount`, fecha `closed_at`) y la
  puerta manual es `POST /api/patient-charges` (`manage.patient_charge`); el
  libro es inmutable —un cargo equivocado se corrige con un ajuste— y
  `charges:backfill` (`--dry-run`, idempotente) da su cargo a las atenciones
  cerradas históricas. `GET /api/patients/{patient}/statement`
  (`view.patient_statement`) publica el estado de cuenta del período con saldo
  de apertura y de cierre según
  `cargos − descuentos − pagos + devoluciones − condonaciones + ajustes`; sólo
  cuentan las transacciones `completed`. La devolución exige autorización de
  administrador y está topada por lo cobrado no devuelto del paciente y por su
  pago padre; la condonación, por el saldo pendiente. Los depósitos
  (`cash_deposits`, con su `CashMovement` `withdrawal` en la misma transacción)
  cuadran la gaveta: el reporte diario añade `reconciliation.deposits` y
  `pending_deposit = cash − deposits`, y los agregados por método netean
  devoluciones y excluyen los asientos sin efectivo. Un cobro o una devolución
  con tarjeta, transferencia o billetera queda en `transactions` y en el estado
  de cuenta pero no escribe movimiento de gaveta. Como consecuencia de D7, la
  guarda `cash.session` deja de estar en las rutas de creación y anulación de
  transacciones: la regla vive en `TransactionService`, que sigue exigiendo la
  sesión de la transacción abierta para anular. #321 añade la imputación como
  vista (`POST /api/transactions/{transaction}/allocations` y `allocations` en
  el alta del pago, `patient_charge_allocations`): un pago cubre uno o varios
  cargos de su paciente sin pasar de su importe ni del importe del cargo, y el
  estado de cuenta publica `charge_balances` y `unallocated_payments` sin mover
  el total. #322 deja `cash:repair-electronic-movements` (`--dry-run`,
  idempotente) para marcar los movimientos de gaveta históricos de métodos
  electrónicos; #323 cierra la ventana de concurrencia (pago, devolución,
  depósito y cierre comparten el lock de la sesión) y hace que el bloqueo
  contable del depósito se evalúe sobre `deposited_at` con
  `lock_override_reason`.
- BI (8 report services) + export
- Catálogo de procedimientos con favoritos (admin/clínico/recep)
- Estadísticas del catálogo (`/api/admin/procedure-stats`)
- Importador CSV de procedimientos (`/api/admin/procedure-catalog/import`)
- Versionado del catálogo (tabla `procedure_catalog_versions` + tracking
  automático)
- Multi-idioma del catálogo (tabla `procedure_catalog_translations` + accessor
  `translate()`)
- `PendingPaymentsController@pay()` implementado (TransactionService + balance
  tracking)
- Branding migrado de EasyDent a OdontoSuite
- Recordatorios de confirmación agendados en el flujo real de citas (#154):
  crear y reprogramar agendan 24h/48h/72h, cancelar cancela los pendientes y
  `reminders:reconcile` (horario) repara huecos y cubre citas previas
- Continuidad y plan versionado (#284/#288): `patient_recalls` (intervalo
  3/6/12, motivo y vencimiento) alimenta la cola de recepción
  (`GET /api/continuity/queue`) con métrica de cumplimiento; el cierre ya no
  inventa la próxima cita, deja una `continuity_request` que recepción
  materializa con la validación de conflictos de la agenda; el acto consume
  ítems del plan (`treatment_plan_item_consumptions`) y el estado del ítem se
  deriva de esos consumos. Los planes tienen prioridad y secuencia por ítem,
  versiones inmutables con el presupuesto aprobado atado a la versión aceptada,
  alternativas A/B por pieza con el rechazo fechado y motivo, y máquina de
  estados que responde 422
- AGENTS.md sincronizado con el código (guard `scripts/audit/doc-drift.mjs`)
- Atención clínica como entidad propia (#282): `clinical_encounters` con estado
  `open → closed | voided`, cierre único, proyección de la cita y urgencia sin
  cita
- `composer dev` usa `pnpm dev`
- Materiales e inventario con stock real (#295): el procedimiento declara los
  insumos que consume (`procedure_catalog_materials`, editado como
  `material_recipe` en el catálogo) y el cierre de la atención los descuenta:
  `InventoryService::consumeForEncounter` congela el costo unitario, escribe un
  `ProcedureMaterial` y un `StockMovement` `out` por lote y baja
  `products.stock_quantity`. Un material sin stock suficiente es 422 y no deja
  nada escrito. El almacén se opera por `GET /api/inventory/products`,
  `/products/{id}`, `/movements`, `/alerts` (`view.inventory`) y por
  `POST /api/inventory/receipts` y `/adjustments` (`manage.inventory`), y
  `requires_materials` se deriva del recetario del procedimiento en vez de un
  flag manual. Las cantidades de stock y consumo son `decimal(12,3)`.
- `composer dev` usa `pnpm dev`
- Cumplimiento NTS 139/NTS 188 (#286): el cierre exige motivo de consulta,
  diagnóstico codificado contra el catálogo CIE-10 y firma del profesional; el
  acto firmado publica su hash de integridad y `GET /api/encounters/{id}/record`
  emite el registro imprimible de la norma.
- CI/CD con GitHub Actions (4 jobs desacoplados: quality, backend-tests MySQL,
  frontend-build y security; ver §10 para qué bloquea y qué es aviso explícito)
- CREDENTIALS.md sincronizado (tests automáticos lo validan)
- Catálogo con aplicabilidad (#293): `procedure_catalog.site_type`
  (`none|tooth|surface|quadrant|arch`) y `procedure_external_codes` con los
  códigos del nomenclador público (RM 550-2023/MINSA) y del SIS, más búsqueda
  por `external_system`/`external_code`. Una línea de plan o de presupuesto que
  nombra un procedimiento con sitio debe declararlo (`dental_piece_id`,
  `tooth_surface_id` o `site_code`); sin sitio responde 422 nombrando el
  procedimiento, y el traspaso plan → presupuesto se niega a emitir una línea
  anterior a la regla que no lo declare.
- Tarifario con sede y convenio (#293): `procedure_prices` (sede y convenio
  opcionales, con ventana de vigencia) reemplaza `procedure_branch_prices`; la
  cascada a la fecha de referencia es convenio+sede → convenio → sede →
  `default_cost`, y el resultado nombra su origen y la fila aplicada. `payers`
  es el catálogo de convenios; el precio de un convenio se administra con
  `manage.payer_price` y alcance de grupo.
- Presupuesto congelado al emitir (#293): cada línea con procedimiento resuelve
  el precio vigente en su `quotation_date` y guarda la evidencia del tarifario
  (`price_source`, `price_row_id`, `price_valid_from`, `price_valid_to`); el
  documento guarda `payer_id` y su bruto es la suma de sus líneas. Cambiar el
  tarifario no altera presupuestos emitidos ni atenciones cerradas.
- Superficies dentales con datos: `ToothSurfaceSeeder` da las cinco superficies
  canónicas (V/L/M/D/O) a cada pieza activa y `GET /api/dental-pieces` las
  publica, así que un procedimiento de superficie tiene dónde cumplirse.
- Laboratorio protésico propio (#285): `lab_cases` es la orden de trabajo del
  taller (paciente, pieza, trabajo, material, fecha de envío, fecha prometida y
  técnico asignado) con cinco fases que sólo avanzan; entrar en `delivered`
  emite `LabCaseReady` y el caso aparece en `GET /api/my-day` bajo
  `ready_lab_cases`. El escritorio de laboratorio ya no lee la historia clínica,
  el acto clínico ni los registros de especialidad (#313), y la cita dejó de
  llevar la conducta clínica a cualquier lector (#314): sus insumos son el caso
  protésico, el plan de tratamiento y la agenda operativa (ADR-0016).

### ⚠️ Pendiente (cosas que NO se hicieron, documentadas formalmente)

- **Retención y archivo por antigüedad**: la historia se conserva y no se borra,
  y desde #286 cada acto se firma, pero todavía no existe el archivo por
  antigüedad (cinco años activos y quince pasivos) ni la firma con certificado
  digital; la firma vigente es electrónica simple (contraseña revalidada + hash
  de integridad, ADR-0014).
- **`migrate:fresh` es solo-MySQL por decisión (#79).** El esquema no se
  construye en SQLite: eliminar `transactions.type` choca con el índice
  `idx_transactions_patient_type_status` (`no such column: type`), y las
  migraciones aplicadas no se reescriben. La suite con base de datos corre con
  `vendor/bin/phpunit -c phpunit.mysql.xml` contra un motor real (local:
  MySQL/MariaDB de XAMPP por socket; CI: service MySQL 8.0). La variante
  `php artisan test --configuration=phpunit.mysql.xml` no es equivalente: con la
  ruta relativa artisan acaba ejecutando `phpunit.xml` —el archivo que fija el
  motor no se usa—, PHPUnit responde
  `WARN Option --configuration cannot be used more than once` y el comando sale
  con código 1 aunque todos los tests pasen (la invocación completa está en §8).
  `php artisan test` a secas (SQLite `:memory:`) no puede construir el esquema:
  todo test que usa `RefreshDatabase`, `DatabaseMigrations`,
  `DatabaseTruncation` o `DatabaseTransactions` se reporta como **skipped**, no
  como failed, por `Tests\TestCase::setUpTraits()` vía
  `Tests\Support\MysqlRunner` (#170), así que ese runner solo muestra
  regresiones reales. **El número de tests saltados no se cita aquí a
  propósito** (deriva con cada migración). Lo ejecutable que fija esta
  declaración: `tests/Unit/Tooling/SqliteMigrateFreshDeclarationTest` y
  `tests/Unit/Tooling/MysqlRunnerGateTest`.
- **0 eventos `@deprecated` huérfanos** en `app/Events/` (medido con
  `grep -rl '@deprecated' app/Events | wc -l`). Los únicos `@deprecated` del
  dominio están a nivel de campo: `User::$specialty` (`specialty` legacy,
  ADR-0007) y `ProcedureCatalog::$legacy_specialty` (`legacy_specialty`,
  ADR-0008). Se nombra el símbolo y no su línea: el ancla numérica envejecía con
  cada refactor.
- **Reminders con CRUD real, WaitingList eliminada.** `ReminderController` y
  `ReminderTemplateController` implementan CRUD completo desde el slice 03.
  `WaitingListController` no existe: fue eliminado en el slice 04 y sus
  endpoints devuelven 404 (guardado por `StubsRemovedEndpointsTest`).
- **`User::specialty` (string legacy)**: conservado como display denormalizado.
  Sprint 2 DM-6 lo deprecó formalmente, creó accessor `specialty_code`, eliminó
  cast JSON inexistente. La decisión y su contrato de lectura viven en ADR-0007.
- **`procedure_catalog.legacy_specialty`**: conservado en BD por compatibilidad.
  Sprint 2 DM-7 lo deprecó formalmente, creó accessor `specialty_code`. El plan
  de drop está descrito en ADR-0008.
- **2 FormRequests no migrables** (documentado en Sprint 5 del plan de
  inconsistencias): `StoreAppointmentRequest` (omite 4 campos inline) y
  `StoreSpecialtyRecordRequest` (omite 14 campos inline). Requieren refactor del
  controller. `StoreQuotationRequest` salió de la lista: declara `patient_id`
  como `sometimes|nullable` y `QuotationController@store` deriva el paciente
  desde `treatment_plan_id`, así que el bloqueo del path `generateQuotation`
  (fix C-1 del Sprint 0) ya no existe.

- **#287 declaró fuera de alcance tres cosas y las tres se cerraron después**:
  la UI de caja para devoluciones, depósitos y estado de cuenta se entrega en
  #320 (devolución con autorizador y motivo, depósito y depósitos del turno en
  el cierre, cargo manual y tab «Cuenta» en la ficha, y conciliación en
  Reportes); la imputación de un pago a un cargo existe desde #321 como vista
  (`patient_charge_allocations`, ADR-0018) y el saldo del paciente sigue siendo
  la regla de #287, así que la corrección de un cargo va por ajuste; y los
  movimientos de gaveta electrónicos escritos antes de la regla sólo-efectivo se
  reparan con `cash:repair-electronic-movements` (#322), que los marca sin
  reescribir las columnas de la sesión.

### 🔧 Notas de entorno

- El `.env` tiene `BROADCAST_CONNECTION=reverb`. Para tests locales sin servidor
  Reverb, usar `BROADCAST_CONNECTION=null` (ya configurado en `phpunit.xml`).

---

## 7. Convenciones de código

### Backend (PHP / Laravel)

- Namespaces: `App\Http\Controllers\Api`, `App\Services`, `App\Models`,
  `App\Http\Middleware`
- Autorización: permisos, no roles. `App\Authorization\Permissions` es la única
  fuente (`verbo.recurso` + matriz rol → permisos); cada ruta declara
  `->middleware('permission:verbo.recurso')`, nunca `role:...`. El front deriva
  `usePermissions()` de `user.permissions` (login y `/api/auth/me`), sin repetir
  la matriz. NO usar Spatie.
- Alcance por sede (#283/#290): `users.branch_id` es la afiliación. Con valor,
  el usuario opera sólo esa sede; sin valor tiene alcance de grupo (gerencia), y
  un `administrador` con sede es el administrador de esa sede.
  `App\Authorization\BranchAccess` es la regla única; el trait
  `App\Models\Concerns\ScopesToBranch` (guarda de escritura + route-model
  binding acotado) y el middleware `EnforceBranchScope` la aplican, y
  `RequireGlobalBranchScope` protege el catálogo de sedes. La ficha del paciente
  y la historia clínica son compartidas entre sedes (ADR-0010); agenda,
  sillones, caja, personal e inventario no lo son. La matriz escritorio ×
  permiso vive en `App\Authorization\Desks` y se publica en
  `docs/architecture/desks.md`. En una escritura `PUT/PATCH`, un `branch_id`
  nulo es 403 (la sede no se limpia desde una sesión de sede); en `POST` el
  mismo valor significa «sin sede» y el modelo estampa la del actor.
- Bloqueo contable (#283): `accounting_locks` guarda `locked_through` por módulo
  (`cash_transactions`, `cash_movements`, `quotations`, `treatment_plans`) y
  sede, con la fila de la sede por encima de la global. Mutar dinero con fecha
  contable anterior o igual responde 403 y deja `accounting_lock_blocked` en
  `audit_logs`; la autorización registrada viaja como `lock_override_reason`
  (mínimo 10 caracteres, sólo administrador) y deja
  `accounting_lock_overridden`. Crear dinero de hoy nunca se bloquea.
- Tarifario por sede (#290): `procedure_branch_prices` gana sobre
  `procedure_catalog.default_cost` dentro de su ventana de vigencia. El picker
  devuelve el precio de la sede en `default_cost` y conserva `list_cost`; las
  cotizaciones y los planes guardan el precio aplicado como snapshot y no lo
  recalculan.
- Roles válidos: `administrador`, `recepcionista`, `odontologo`, `implantologo`,
  `tecnico_dental`, `asistente`, `finanzas`
- Respuestas JSON: `{data: ..., meta: {message: ...}}` (ver
  `AuthController@login`)
- Servicios: lógica de negocio en `app/Services/*Service.php`
- Eventos: al crear/actualizar/eliminar entidades, `event(new X(...))` envuelto
  en `try/catch` (M-2 fix)
- Validación: `Request->validate()` inline. FormRequests tipados para los 10
  controllers que los usan.
- Multi-idioma catálogo: `$catalog->translate('en', 'name')` (Sprint 4 IM-8)

### Frontend (Vue 3)

- Composition API + `<script setup>` (preferido). NO Options API salvo
  componentes muy viejos.
- Naming: PascalCase `.vue`, camelCase composables (`useAuth.js`)
- Estado global: composables singleton (NO Pinia)
- Auth: `useAuth()` lee de `localStorage` (`auth_token`, `user`). En 401 limpia
  ambos.
- Rutas: lazy-loading con `() => import(...)` excepto `LoginPage` (eager)
- API: SIEMPRE `useApi().request/get/post/...`. NO axios directo.
- UI: consumir SIEMPRE de `components/ui/`. NO botones/modales ad-hoc.
- Estilos: Tailwind 3 + design tokens. NO CSS scoped salvo necesidad real.
- Formato: TODO dinero, fecha y hora sale de `composables/useFormatters.js`
  (locale `es-PE`, reloj 24 h). NO `Intl.NumberFormat` de moneda,
  `Intl.DateTimeFormat` ni `toLocale*` fuera de ese módulo; el guard es
  `tests/Unit/Composables/FormatPENLabelTest`.
- Iconos: `@heroicons/vue`
- TypeScript: NO usado. Todo es JS.
- WebSocket: `useWebSocketNotifications()` escucha canales y dispara toasts.
  `procedure-catalog` canal activo para eventos de catálogo.

### Estilo del usuario (Arnold)

- Código limpio y profesional. **NO comentarios pedagógicos** salvo pedido
  explícito.
- Default a explicaciones cortas y al grano.
- Idioma de trabajo: español (Perú). Código en inglés.
- **Pnpm siempre. NUNCA `npm install`.**
- Commits: prefijos semánticos (`feat/`, `fix/`, `chore/`, `refactor/`), sin
  emojis.
- Multi-branch, mensaje corto y descriptivo.

---

## 8. Troubleshooting

| Problema                                                | Solución                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `npm` o `yarn` reclamando                               | Usar `pnpm` exclusivamente. AGENTS.md §2.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `vite.config.js` no resuelve `@/`                       | Alias ya está configurado (M-3 fix). Si se rompe, revisar.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| Tests con BD fallan por DDL de SQLite                   | Por diseño (#79): migrate:fresh es solo-MySQL; con SQLite el esquema no se construye (ver §6) y, desde #170, esos tests se reportan como **skipped**, no como failed. Camino soportado: `vendor/bin/phpunit -c phpunit.mysql.xml` contra motor real; `php artisan test --configuration=phpunit.mysql.xml` no sirve para esto: con la ruta relativa artisan ejecuta `phpunit.xml` en vez del archivo indicado, PHPUnit avisa `Option --configuration cannot be used more than once` y sale con código 1 aunque todos los tests pasen. El MariaDB de XAMPP tiene `skip-networking`, así que el destino se fija por entorno: `DB_HOST=localhost DB_PORT=3306 DB_SOCKET=/opt/lampp/var/mysql/mysql.sock DB_USERNAME=root DB_PASSWORD=<from .env> DB_DATABASE=odontosuite_<worktree>_test vendor/bin/phpunit -c phpunit.mysql.xml`. Alternativo: `docker compose up -d mysql` + `php artisan test --group=mysql`. |
| Varias sesiones o worktrees corren tests a la vez       | Cada worktree usa su propia base de test (`DB_DATABASE=odontosuite_<worktree>_test`) y nadie recrea una base compartida mientras otra suite corre: un `migrate:fresh` sobre la base de otro proceso la deja sin tablas y su suite falla en el bootstrap. CI conserva `odontosuite_test` porque su runner es aislado.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `git checkout-index failed`                             | **Windows + rutas largas.** El repo tiene rutas que superan los 260 caracteres (`openspec/changes/archive/<nombre-largo>/specs/...`). `LongPathsEnabled=1` en el registro **no basta**: git necesita su propio opt-in. Ejecutar `git config core.longpaths true` (local al repo).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| Pusher TypeError en tests                               | `phpunit.xml` tiene `BROADCAST_CONNECTION=null` (ya configurado). Si falta, agregar `env name="BROADCAST_CONNECTION" value="null"`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `composer dev` no levanta Vite                          | Verificar que el script usa `pnpm dev` (no `npm run dev`). Sprint 1 DM-1 fix.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| Email no se envía                                       | Verificar `MAIL_MAILER` en `.env`. Default `log` (escribe a `storage/logs/laravel.log`). Para producción: SMTP/SES.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `php artisan migrate:fresh --seed` falla                | Verificar conexión MySQL en `.env`. Seeders activos: 17.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| Frontend no encuentra módulo                            | `pnpm install` y reiniciar `pnpm dev`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| WebSocket no conecta                                    | Verificar `php artisan reverb:start` corriendo y `BROADCAST_CONNECTION=reverb` en `.env`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| CI falla por `MissingAppKeyException`                   | `phpunit.xml` ya tiene `APP_KEY` configurado (Sprint 4 IM-1 fix). Si falta, agregar.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `procedure_catalog.legacy_specialty` aparece en queries | Campo deprecado (ADR-0008). Usar `specialty_code` accessor o `$pc->specialty->code`. Drop futuro documentado.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |

---

## 9. Planes cerrados (referencia)

| Plan                                                       | Sprints | Estado                | Hallazgos                                                                     |
| ---------------------------------------------------------- | ------- | --------------------- | ----------------------------------------------------------------------------- |
| `docs/mejoras/plan-flujo-catalog-procedimientos.md`        | 6 (1-6) | ✅ Cerrado 2026-06-10 | 22 commits en `feat/procedure-catalog-master-data`                            |
| `docs/mejoras/plan-inconsistencias-2026-06-actualizado.md` | 6 (0-5) | ✅ Cerrado 2026-06-11 | 22 hallazgos C-/I-/M- cerrados en `fix/inconsistencias-sprint-1-multi-tenant` |
| `docs/mejoras/plan-mejoras-futuras-2026-06.md`             | 5 (0-4) | ✅ Cerrado 2026-06-11 | 22 hallazgos NF-/DM-/IM- cerrados (0→4). Total: 18.5 d-h                      |

> **Estado**: los 3 planes están cerrados. La deuda documentada en
> `docs/mejoras/` está toda resuelta o formalmente deprecada con ADRs. El
> proyecto está listo para deploy.

### Decisiones de diseño (ADRs)

| ADR                                                                       | Contenido                                                                                                                                        |
| ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `docs/architecture/decisions/0007-user-specialty-source-of-truth.md`      | `User::specialty` (string) es legacy, `user_specialties` (pivote) es source-of-truth. Accessor `specialty_code` sincroniza.                      |
| `docs/architecture/decisions/0008-procedure-catalog-legacy-specialty.md`  | `procedure_catalog.legacy_specialty` es legacy, `specialty_id` (FK) es source-of-truth. Drop futuro con script de backfill.                      |
| `docs/architecture/decisions/0010-branch-scope-authorization.md`          | La sede es una dimensión de autorización, no un filtro de consulta.                                                                              |
| `docs/architecture/decisions/0011-quotation-igv-policy.md`                | El IGV de un presupuesto se descompone del precio, no se suma.                                                                                   |
| `docs/architecture/decisions/0012-catalog-applicability-and-tariff.md`    | El catálogo declara sobre qué se hace (pieza/superficie/cuadrante/arcada) y a qué precio, con vigencia y convenio.                               |
| `docs/architecture/decisions/0013-plan-versioning-and-continuity.md`      | El plan aceptado es una versión inmutable; la continuidad es una solicitud que recepción materializa con validación de agenda.                   |
| `docs/architecture/decisions/0014-clinical-record-signature-and-cie10.md` | El registro de atención es un documento firmado: motivo, CIE-10 y firma electrónica simple con hash de integridad.                               |
| `docs/architecture/decisions/0015-money-vocabulary-and-patient-ledger.md` | Pago, descuento, condonación, devolución y ajuste son conceptos distintos; el cargo es un libro del paciente y sólo el efectivo mueve la gaveta. |
| `docs/architecture/decisions/0016-clinical-narrative-desk-boundary.md`    | La narrativa clínica no cruza al escritorio no clínico; su decisión y su alcance viven en el ADR.                                                |
| `docs/architecture/decisions/0017-branch-transfer-request.md`             | El traslado entre sedes es una solicitud aprobada, no una edición del alcance.                                                                   |
| `docs/architecture/decisions/0018-payment-charge-allocation.md`           | La imputación de un pago a un cargo es una vista sobre el mismo dinero, no una segunda contabilidad.                                             |

> **Estado**: los once cuerpos de la tabla están escritos y el índice resuelve
> sus enlaces. El índice vive en `docs/architecture/decisions/README.md` y su
> integridad la fija
> `tests/Unit/Documentation/ArchitectureDecisionsIndexTest.php`.

---

## 10. CI/CD

`.github/workflows/ci.yml` con 4 jobs desacoplados (ninguno bloquea a otro: un
lint en rojo nunca esconde si los tests pasan). El workflow es la fuente de
verdad; esta tabla lo resume sin repetir su contenido.

| Job              | Qué verifica                                                                                                                                                                                                           |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `quality`        | Bloqueantes: PHP syntax, validación JSON, Pint (`vendor/bin/pint --test`), ESLint, Vitest smoke (`pnpm test:js`) y Prettier (`pnpm format:check`).                                                                     |
| `backend-tests`  | MySQL 8.0 real como service: `php artisan migrate --force` + suite completa.                                                                                                                                           |
| `frontend-build` | `pnpm build` + upload del artifact.                                                                                                                                                                                    |
| `security`       | Bloqueante: `gitleaks` fijado y verificado por checksum sobre los commits que publica el run (rango del PR o del push), con las reglas de `.gitleaks.toml`; la credencial histórica de `SECURITY.md` no se re-escanea. |

**Triggers**: push a `main`/`fix/*`/`feat/*`/`chore/*`/`refactor/*` + PRs a
`main`.

---

## 11. Resumen ejecutivo

OdontoSuite es una app fullstack Laravel 12 + Vue 3 con 48 controllers API, 71
modelos, 7 roles, sistema de caja completo, BI, IA, multi-sede parcial y
broadcasting. Auth Sanctum con tokens bearer. Estado global via composables.
Stack maduro para capstone: los 3 planes de mejoras están cerrados (66 hallazgos
resueltos), CI/CD con GitHub Actions, y la suite PHP corre contra un motor MySQL
real (local y en CI): su tamaño no se cita aquí porque cambia con cada test. La
deuda restante (tests que tocan la BD y se saltan en SQLite local) está
documentada y no bloquea producción.

---

## 12. Changelog de AGENTS.md

- **2026-10-27 (auditoría de dinero: saldo único, caja del actor, bloqueo
  contable en el alta y búsqueda por nombre completo, #393/#407/#410/#412)** —
  Cuatro hallazgos de la auditoría UI U5, un commit por issue. **#393**: nace
  `App\Support\AppointmentBalance`, la única definición del saldo de una
  atención (`final_amount` cuando existe, si no `total_cost`, menos los pagos
  `completed`), y la leen `GET /api/appointments/ready-to-bill`, el desglose y
  `POST /api/pending-payments/{id}/pay`; esa puerta bloquea la fila de la cita
  mientras lee y escribe, admite cobros parciales, responde 409 con su propio
  motivo cuando ya hay un pago registrado y 422 cuando el importe excede el
  saldo. «Listos para facturar» gana la acción **Cobrar** y `PaymentModal`
  incorpora el aviso de bloqueo contable. **#407**:
  `CashRegisterService::getCurrentSession()` filtra siempre por el actor (la
  exención del administrador desaparece) y `getLatestSession()` devuelve su
  último turno; `GET /api/cash-register/current` publica la gaveta propia
  abierta con `summary`, o la propia cerrada con un bloque `closing` (contado,
  esperado, diferencia, fecha) y sin `summary`, así que el hero de
  `/cash-register` distingue «Sin sesión» (KPIs `N/D`, «Abrir caja»), «Sesión
  abierta» y «Sesión cerrada» con su arqueo, y ninguna acción de caja opera
  sobre una gaveta cerrada o ajena. **#410**: `AccountingLockService::guard()`
  corre también en el alta — `TransactionService::createTransaction` antes de
  abrir su transacción y `CashMovementController@store` antes del insert—, sobre
  la fecha contable de hoy y la sede de la sesión, así que un cobro o un
  movimiento bloqueado responde `403 accounting_lock_blocked` y el aviso del
  modal ofrece el motivo de administrador (≥10 caracteres) con
  `accounting_lock_overridden`; finanzas no ve ese campo. **#412**:
  `/api/patients/search` separa el término en palabras (AND entre palabras sobre
  nombre y apellido, en cualquier orden) y acepta la cadena completa contra
  `CONCAT(first_name, ' ', last_name)` sin perder documento, email ni teléfono,
  y publica `name`; `TransactionModal` arma sus filas desde
  `first_name`/`last_name` y deja de pintar `undefined`. Sin rutas, modelos,
  migraciones ni permisos nuevos: los conteos de §4/§5 no cambian. Tests:
  `AccountingLockCreationGuardTest` (8), `AppointmentPaymentBalanceTest` (5,
  cierre real por `/appointments/{id}/complete`),
  `CashRegisterCurrentSessionScopeTest` reescrito (tres estados y propiedad de
  la gaveta), `PatientSearchFullNameTest` (6) y los smokes `caja-caja-actual`
  (tres estados), `caja-ready-to-bill`, `caja-cobros`, `caja-depositos`,
  `caja-transacciones` y `patient-selector`. Suite MySQL completa
  `OK (2804 tests, 17116 assertions)`; Vitest 1292 pruebas en 151 archivos.
- **2026-10-27 (primitivas de diálogo: apilado, nombre accesible y foco del
  sheet, #423/#379/#385/#429)** — Los overlays dejan de compartir un plano.
  `resources/js/composables/useOverlayStack.js` mantiene una sola pila ordenada
  que consumen `Modal.vue` y `Sheet.vue`: el fondo se pinta en
  `50 + profundidad de la pila` con un `z-index` inline, sólo la superficie
  superior responde a Escape y al clic del fondo, y el bloqueo del scroll del
  documento se cuenta por superficie abierta. Antes cada overlay usaba `z-50`,
  así que el `UiConfirmDialog` global de `AppLayout` —montado antes que
  cualquier modal de página— quedaba debajo del detalle de Análisis IA y su
  botón «Anular» nunca recibía el clic; ahora una confirmación pedida desde
  dentro de un modal abierto se pinta encima y el flujo termina donde promete
  (análisis no citado → `POST …/void` `200` y la fila sale de la lista; análisis
  citado → `422` **con su motivo en el aviso**: el composable prefiere el primer
  `errors.*` al `message` genérico de validación). Ambos fondos publican
  `data-overlay-backdrop` (`modal` / `sheet`). **Sheet (#379/#385)**:
  `Sheet.vue` gana la prop `labelledby`, cae a `aria-label` desde `title` cuando
  el consumidor reemplaza el slot `#header` —así el sheet de navegación móvil
  anuncia «Navegación»—, deja de apuntar `aria-labelledby` a un id que nunca se
  renderiza cuando no hay título y arma la trampa de foco con `nextTick` en las
  aperturas por `v-model`; el sheet de `/ai-analysis` es la misma primitiva y
  queda cubierto por el mismo arreglo. **Detalle de IA (#429)**:
  `AiAnalysisModal.vue` da id a su bloque de encabezado —la etiqueta «Análisis
  de IA» más el nombre del paciente— y lo pasa como `labelledby` a `UiModal`,
  con lo que el nombre accesible es «Análisis de IA Ana Torres». Sin cambios de
  fuente PHP, rutas, controllers, modelos, migraciones ni páginas; §4 sube a 45
  composables (el nuevo `useOverlayStack.js`) y §5 no cambia. Tests:
  `overlayStack.test.js` (5), `sheetPrimitive.test.js` (5),
  `useAiAnalysis-void.test.js` (3) y un caso nuevo en
  `ai-analysis-detail.test.js` (16 en el archivo); `pnpm test:js` en 144
  archivos / 1239 pruebas y
  `vendor/bin/phpunit tests/Unit/DesignSystem/PrimitivePressTest.php` en 12
  tests / 144 aserciones. Medido en navegador real sobre una base dedicada
  (`odontosuite_dialog_contract_audit`, servidor en `:8001` con Vite en
  `:5173`): el criterio de #385
  (`AUDIT_SCOPE=route:/ai-analysis pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep "dialog contract /ai-analysis"`)
  queda `focus-trapped → inside` y `accessible-name → Nuevo análisis de IA`; el
  hit test de la confirmación devuelve `ok: true` («Anular») y el `POST …/void`
  responde 200; el análisis citado responde 422 con «No se puede anular un
  análisis citado en una evolución o en un plan.» visible en el aviso. La sonda
  de navegador no se versiona: su evidencia queda en
  `.atl/qa-evidence/audit/dialog-contract`. Se entrega en un solo pull request
  con cuatro commits de unidad (`a11bc2c`, `e33fbde`, `2f06d95`, `9131a4f`).

- **2026-10-27 (un solo módulo de formato: dinero, fecha y hora, #451)** — El
  frontend deja de tener dos módulos de formato.
  `resources/js/composables/useFormatters.js` es la única fuente (locale
  `es-PE`, reloj 24 h): `formatCurrency` y su alias `formatPENLabel`,
  `formatUsd`, `formatNumber`, `formatDate`, `formatDateTime`, `formatTime`, los
  dos helpers compactos de la agenda y `dateOnly` para campos fecha.
  `resources/js/utils/formatters.js` se elimina: su `formatCurrency` respondía
  `S/ NaN` a un importe no numérico y su `formatDateShort` retrocedía un día
  fuera de UTC (`new Date('YYYY-MM-DD')` es UTC). El guard
  `tests/Unit/Composables/FormatPENLabelTest` barre ahora `resources/js`
  completo y falla con archivo y línea ante un `Intl.NumberFormat` de moneda, un
  `Intl.DateTimeFormat`/`toLocale*` o un prefijo `S/` delante del helper o de un
  importe crudo; quedan exentos el propio módulo y `CurrencyInput.vue` (display
  numérico del campo). Un campo fecha se lee con `dateOnly` y se pinta con
  `formatDate`, así una medianoche UTC no retrocede el día; un instante se pinta
  con `formatDateTime` en el reloj de la sesión. Texto visible canónico: fecha
  `02/06/1999`, mes corto `20 oct. 2026`, hora `15:07`, USD `USD 0.0042`. Sin
  rutas, modelos, migraciones ni permisos nuevos: los conteos de §4/§5 no
  cambian. Tests: `formatters-single-source` (25), guard (27),
  `calendarDateMath`/`labCaseMeta`/`continuityMeta` y sus smokes ajustados al
  formato `es-PE`; Vitest **1264 pruebas en 145 archivos**; suite MySQL **OK
  (2783 tests, 16970 assertions)**; Pint, ESLint (0 errores), Prettier y
  `pnpm build` en verde; `node scripts/audit/doc-drift.mjs` en verde; U11 capa 1
  con `intl=0`, `locale=0` y `legacyModule=0` en las 36 superficies, y capa 2
  con el eje `format` en **OK (0 DIV)** y 36/36 superficies medidas; el único
  motivo restante del gate son los 403 clínicos de la ficha, en #399.
- **2026-10-26 (lote de auditoría: selects vacíos, filtros fantasma, scripts sin
  lint, notas del instrumento y helper de 422)** — Cinco entregas del programa
  de auditoría (#402, #408, #386, #458) más el canon del eje «Feedback».
  **#402**: `UiSelect` declaraba `Boolean` antes que `String` en la unión de
  `modelValue`, así que Vue convertía `''` en `true` (`shouldCastTrue` de
  `resolvePropValue`); el orden pasa a `[String, Number, Boolean, Array]` y un
  select cuyo valor legítimo es `''` —la opción «Todos» de `/patients`— muestra
  esa etiqueta en el disparador y `aria-selected="true"` en su opción, sin
  perder el soporte booleano de F11. **#408**:
  `new URLSearchParams({ search: undefined })` serializaba las cadenas
  `undefined`/`null`, el servidor filtraba por ellas y la pestaña Cobros quedaba
  vacía; `resources/js/utils/query.js` (`buildQueryString`) las omite y conserva
  `''`, `0` y `false`, y `useApi` lo usa en `get` y en `download`. **422**:
  `resources/js/composables/validationErrors.js` normaliza el cuerpo del rechazo
  (arrays → primer mensaje, claves anidadas colapsadas al campo declarado) y
  prefiere el motivo que la API publica en `errors` sobre el `message` genérico;
  `useFieldValidation` gana `applyServerErrors` y las tres superficies medidas
  de #415 pintan la razón: editar presupuesto (`QuotationModal`), aprobarlo
  (`QuotationApprovalModal`) y cambiar el estado de un plan
  (`TreatmentPlansPage`). **#386**: `lint`/`lint:check` recorren `.mjs` y
  `ecmaVersion` pasa a 2022 (el `await` de nivel superior de los entry points no
  se podía parsear); se corrigen los 9 hallazgos publicados y los 6 que la
  extensión dejaba a la vista detrás del error de parseo, sin cambiar la lógica
  de los scripts. **#458**: mantenimiento del instrumento de U11 —evidencia de
  repetición con `truncated`, informe que exige `manifest.json` y busca la fila
  por ruta y rol, `tests/e2e/audit/slug.js` como única implementación del slug,
  filas estáticas validadas contra sus ocho ejes, `renderRows` sin colisión de
  roles y workflow con el alcance resuelto una vez, `paths` en el push y
  artefacto vacío como fallo—. §4 sincronizado: 46 composables. Sin rutas,
  modelos, migraciones ni primitivas UI nuevas. Tests: `select-empty-value` (3),
  `useApi-params` (4), `validationErrors` (6),
  `useFieldValidation-server-errors` (2), `quotations-form` (5),
  `quotations-list` (6) y `treatment-plans` (10), más
  `./node_modules/.bin/eslint --ext .mjs scripts` y `pnpm lint:check` en verde
  (`eslint . --ext .mjs,.vue,.js,.jsx,.ts,.tsx` sale 0; el wrapper de pnpm no
  corre en el worktree porque `node_modules` es un enlace al repo principal). La
  re-medición de U11 se ejecutó sobre una base local sembrada: **62 passed** con
  `AUDIT_SCOPE=all`, 36/36 filas medidas, y el `report.js` de esta rama produce
  la misma matriz, `gate.json` y JSON que el de `origin/main` sobre el mismo
  árbol de evidencia; contra la matriz publicada, 35 de 36 filas coinciden (la
  restante es dato del sembrado).

- **2026-10-23 (las notas ODD se versionan)** — Las 47 notas de tarea que
  quedaban sin seguimiento en `odd/tasks/` entran al repositorio junto a las 43
  ya versionadas: son la especificación que leen los subagentes (`S#`, tareas y
  log) y el rastro de cada entrega, así que en un repositorio público su sitio
  es el historial. `odd/` nunca estuvo en `.gitignore`; la única exclusión es la
  de `.prettierignore`, que evita reformatear notas. El contenido pasó por el
  gate de secretos con la misma versión fijada de `gitleaks` y la misma
  configuración de CI, y se sustituyeron por marcadores el valor literal de
  `DB_PASSWORD`, una clave local y una ruta local; la nota
  `odd/tasks/security-secret-gate.md` los nombra en su tarea T1 (las rutas), su
  log L2 (la clave local) y su hallazgo F3 (`DB_PASSWORD=<valor literal>`), sin
  anclar por número de línea: el ancla numérica envejece en cada edición, y la
  de esta entrada ya no apuntaba a lo que decía (#359, R3-001). Sin cambios de
  código: los conteos de §4/§5 no cambian. Se entrega en una cadena de seis PRs
  por tema, cada uno dentro del presupuesto del revisor nativo.

- **2026-10-23 (caja sólo con permiso, #345)** — `DashboardPage` deja de pedir
  la sesión de caja a los roles que no pueden leerla: la carga y la suscripción
  al canal privado `cash-register` quedan detrás de `can.viewCashRegister`, así
  que odontología y laboratorio ya no reciben el 403 de
  `/api/cash-register/current` en la consola de su pantalla de aterrizaje y los
  tres roles de caja conservan sesión, totales y refresco en vivo. Sin rutas,
  permisos, payloads ni migraciones nuevas: los conteos de §4/§5 no cambian.
  Test: `dashboard-cash-permission.test.js` (5), que falla si el escritorio
  vuelve a pedir la sesión o el canal a un rol sin el permiso.
- **2026-10-23 (nits de continuidad y laboratorio, #346)** — El placeholder del
  profesional en el modal de agenda pierde el sustantivo («Seleccionar») y
  `UiSelect` trunca cualquier placeholder en una línea, como ya hacía con el
  valor elegido. En laboratorio la columna Trabajo se trunca en una línea con el
  nombre completo en el `title`, así las filas mantienen el mismo ritmo y el
  scroll horizontal sigue siendo del primitivo. Sin cambios de contrato,
  permisos ni payloads. Tests: `select-placeholder-single-line.test.js` (2) y
  los smokes de continuidad y laboratorio extendidos.

- **2026-10-23 (UI de caja: devoluciones, depósitos, cargos manuales y estado de
  cuenta, #320)** — La superficie web del dinero que #287 dejó fuera de alcance.
  **Devolución**: el modal de Caja deja de ofrecer «Egreso» como si fuera un
  gasto; una devolución pide el administrador que la autoriza (`authorized_by`,
  cargado de `GET /api/users/active?role=administrador`) y el motivo
  (`authorization_reason`), bloquea el envío sin ambos y muestra el 422 en su
  campo. El vocabulario (`moneyMeta.js`) lo comparten el modal y el filtro de
  transacciones. **Cargo manual**: `POST /api/patient-charges` tiene formulario
  en la ficha; un rechazo no crea fila y el error cae en su campo. **Depósito**:
  `POST /api/cash-register/deposits` se registra desde el hero de Caja con el
  efectivo disponible como tope, referencia bancaria obligatoria y fecha no
  futura; el cierre muestra los depósitos del turno, que el listado acota con el
  filtro aditivo `?cash_register_session_id=`. **Estado de cuenta**: la ficha
  gana la tab «Cuenta» (`view.patient_statement`) con período, saldos de
  apertura y cierre, los totales del `summary` y las entradas del libro;
  «Registrar cargo» exige `manage.patient_charge`. **Reportes**: el reporte de
  período publica `reconciliation` con el mismo helper del diario (`cash`,
  `electronic`, `deposits`, `pending_deposit`) y la tab pinta los cuatro
  valores, con `N/D` si el payload no los trae. El lookup de autorizadores es
  aditivo: sólo el valor exacto `role=administrador` acota la lista; cualquier
  otro conserva la lista clínica. Roles clínicos no ven la tab ni las acciones
  de caja. La rama se fusionó con `main` (#321/#322/#323) antes de cerrar. Sin
  rutas, controladores, modelos ni migraciones nuevas: §4 sigue en 48
  controllers (47 en `Api/` + 1 en `Api/Reports/`), 71 modelos, 153 migraciones
  y 261 rutas API (274 con web). Tests: `UserAuthorizersLookupTest` (4),
  `CashPeriodReconciliationTest` (3), `CashDepositTest` con el filtro por turno,
  `usePermissions` (6), `moneyMeta` (4), `caja-devoluciones` (6),
  `caja-depositos` (14), `caja-reportes` (7), `patientStatementMeta` (6),
  `patients-cuenta` (12) y `patients-detail-redesign` (23). Suite MySQL:
  `OK (2773 tests, 16948 assertions)`; Vitest: 1178 pruebas en 137 archivos.

- **2026-10-25 (traslado entre sedes: solicitud, aprobación y efecto en el
  alcance, #305)** — El traslado deja de ser un cambio administrativo sin
  proceso. Nace `branch_transfer_requests` (`user_id`, `from_branch_id` nullable
  —copia de `users.branch_id` al pedir, así que un usuario con alcance de grupo
  también puede pedir su afiliación—, `to_branch_id`, `status`
  `pending|approved|rejected|cancelled`, `reason` obligatorio, `requested_at`,
  `decided_at`, `decided_by`, `decision_notes`); ambas sedes son
  `restrictOnDelete` porque la historia sigue nombrando la sede que menciona.
  Puertas: `POST /api/branch-transfers` (autoservicio de cualquier rol, destino
  activo y distinto del actual), `POST .../{id}/cancel` (sólo el solicitante y
  sólo en `pending`), `POST .../{id}/approve` y `.../reject` (gerencia:
  `decide.branch_transfer` + `branch.global`, así que el administrador de sede
  ve entrar y salir pero no decide), más `GET /api/branch-transfers` y
  `GET /api/branch-transfers/{id}`, más
  `GET /api/branch-transfers/destinations`, que publica las sedes activas del
  catálogo porque `/api/branches/active` filtra por sede y dejaba sin destinos
  al profesional que pide el traslado. Reglas: una sola solicitud abierta (409;
  transacción con bloqueo de la fila del usuario antes de contar), nadie decide
  su propia solicitud (403: el solicitante cancela, no rechaza), origen que ya
  no coincide con la afiliación (409) y destino desactivado (409). **Una sola
  pluma**: la aprobación es la única escritura de `users.branch_id` fuera de
  `PUT /api/users/{id}`, en la misma transacción que el estado y la auditoría
  `user_branch_assigned` con el valor anterior y el nuevo; un fallo posterior no
  deja la sede a medio cambiar, y las puertas de escritura releen la fila con
  bloqueo para que una decisión concurrente no se pise. **Lectura**: el
  solicitante ve las suyas, el administrador de sede las que entran o salen de
  su sede, gerencia todas; un cruce es 404 en lectura y 403 en operación.
  Permisos nuevos: `request.branch_transfer` (todos los roles) y
  `decide.branch_transfer` (administración). `POST /api/auth/login` y
  `GET /api/auth/me` publican `branch_id`, que el SPA necesita para distinguir
  gerencia de administrador de sede. Superficie: `/branch-transfers` con el
  formulario de solicitud, la historia visible y la decisión de gerencia. §4/§5
  sincronizados: 48 controllers API, 71 modelos, 36 services, 153 migraciones,
  260 rutas API y 273 totales con web, 41 composables, 20 módulos de dominio y
  22 páginas. Tests: `BranchTransferRequestTest` (19),
  `BranchTransferDecisionTest` (10), `BranchAffiliationWriterTest` (2, guard
  estático de «una sola pluma»), `AuthPermissionsPayloadTest` (+2) y el smoke
  `branch-transfers` (21); `PermissionCatalogTest`, `DeskMatrixTest`,
  `RoutePermissionMatrixTest` y `FrontendPermissionContractTest` verdes con el
  fixture regenerado. Decisión en ADR-0017, que cierra la postergación que
  declaraba ADR-0010. Fuera de alcance, documentado: el 403 conserva el sobre
  genérico de la API (los mensajes de la regla viven en la excepción) y la
  etiqueta accesible de `UiStatusPill` no conoce `rejected` (mismo patrón que el
  resto del SPA).
- **2026-10-23 (superficies web de continuidad y laboratorio, #310/#316)** — Dos
  pantallas que el backend ya publicaba pero nadie veía. **Continuidad**
  (`/continuity`, entrada de navegación para administración, recepción y los
  escritorios clínicos) consume `GET /api/continuity/queue`, que devuelve los
  recalls vencidos y las solicitudes de continuidad pendientes con su
  procedencia (plan, atención o cita), y `GET /api/patient-recalls/metrics`: la
  tira de indicadores muestra recalls vencidos, solicitudes pendientes, total en
  cola y `compliance_rate` tal como lo publica la API (`—` cuando no hay
  denominador), sin recalcularlo en el cliente. **Agendar** abre el formulario
  con la fecha preferida precargada (o el vencimiento del recall a las 09:00 si
  ese instante ya pasó) y postea por la puerta de su población
  (`POST /api/continuity-requests/{id}/schedule` o
  `POST /api/patient-recalls/{id}/schedule`), así que la cita sigue validando
  profesional y sillón y el 422 de conflicto se pinta en su campo. **Descartar**
  es solo de solicitudes de continuidad, porque es la única fila que registra el
  motivo (`POST /api/continuity-requests/{id}/dismiss`, motivo obligatorio en la
  interfaz); un recall sale de la cola agendándolo. Las tres acciones se ocultan
  sin `manage.continuity` (`administrador` y `recepcionista`): un clínico lee la
  cola y no la gestiona. **Laboratorio protésico** (`/lab-cases`, visible para
  los mismos escritorios) lista las órdenes de trabajo con filtros de fase,
  `technician_id=me` y `overdue`, sobre `GET /api/lab-cases`; el detalle abre la
  puerta de fase (`POST /api/lab-cases/{id}/phase`) ofreciendo solo fases
  posteriores, porque la máquina no retrocede, y el mensaje del servicio cae en
  el campo. El formulario de alta/edición (`POST|PUT /api/lab-cases`) se abre
  desde la propia página, desde una pestaña **Laboratorio** nueva en la ficha
  del paciente —que lista `GET /api/lab-cases?patient_id=…`— y desde el cierre
  de la atención como bloque aditivo que no toca el payload del cierre.
  Recepción ve los casos y no escribe (`manage.lab_case` es de administración,
  dentistas y técnico). `GET /api/my-day` ya publicaba `ready_lab_cases`: el
  escritorio del profesional ahora los agrupa en un bloque `Laboratorio listo`
  con su conteo, y la suscripción al canal privado `lab-cases` refresca la lista
  al recibir `caso_laboratorio_listo`. Sin rutas API, migraciones ni controllers
  nuevos: la entrega es de superficie. §4 y §5 re-medidos: 42 composables y 23
  módulos de dominio en 23 páginas (la cabecera de §5 decía 19/21 y ya derivaba:
  los directorios de `resources/js/modules/` eran 21 antes de esta entrega).
  Tests nuevos en `pnpm test:js`: 36 de continuidad (`continuityMeta`,
  `useContinuity` y el smoke de la cola), 46 del laboratorio (`labCaseMeta`,
  `useLabCases`, listado, formulario, pestaña del paciente y cierre) y 7 del
  escritorio de Mi día. `scripts/audit/doc-drift.mjs` en verde.

- **2026-10-23 (frontera de la narrativa clínica: especialidades, cita y
  difusión, #313/#314/#315)** — La frontera que #285 fijó en el módulo de
  historia clínica se cierra en las otras tres superficies que publicaban la
  misma narrativa; la decisión vive en ADR-0016. **Registros de especialidad
  (#313)**: el registro es un acto clínico con narrativa quirúrgica
  (`surgical_notes`, `post_surgical_notes`, `complications`, `follow_up_notes` y
  sus equivalentes) y no es insumo del trabajo protésico —el caso `lab_cases` ya
  publica pieza, trabajo, material, fechas y técnico—, así que
  `manage.specialty_record` queda en administración y escritorio clínico: el
  técnico recibe 403 en todo el módulo, la navegación no ofrece
  `/specialty-records`, el tab de especialidades del detalle de paciente se
  oculta sin el permiso, y el canal `specialty-records` deja de autorizarlo.
  `SPECIALTY_RECORD_ROLES` sustituye al uso compartido de `TREATMENT_ROLES`; los
  planes de tratamiento y `view.dental_piece` no cambian. **Cita (#314)**:
  `appointments.treatment_notes` es la conducta clínica copiada de la evolución,
  no un resumen operativo. El cierre deja de escribirla
  (`ConsultationService::projectOnAppointment`), la cita conserva `notes` y la
  solicitud de continuidad, y `AppointmentResource` publica el campo sólo a
  quien lee la historia clínica (`manage.medical_record`): recepción y
  laboratorio reciben la cita sin la clave. Las filas históricas no se
  reescriben, pero tampoco se publican fuera del escritorio clínico, y ningún
  evento de cita difunde el campo (`AppointmentBroadcastPayload` recorta el
  payload de `AppointmentCreated`, `AppointmentUpdated`, `AppointmentCompleted`
  y `AppointmentCheckedIn`; `ReminderSent` recorta la cita que embebe, porque
  publica por `reminders` y `appointments`, canales de recepción y laboratorio).
  **Difusión (#315)**: un canal no puede variar su payload por rol, así que un
  evento de documento clínico viaja sólo por el canal de los escritorios que lo
  leen. `MedicalRecordCreated` publica sólo en `medical-records`, y
  `SpecialtyRecordCreated`/`SpecialtyRecordUpdated` sólo en `specialty-records`;
  ninguno en `dashboard-updates`, que autoriza a los siete roles. Sin rutas,
  controllers, modelos ni migraciones nuevas: §4 no cambia. Tests:
  `TechnicianClinicalRecordScopeTest` extendido (403 en las cinco puertas del
  módulo y 200 de los tres roles que lo conservan),
  `AppointmentTreatmentNotesDeskTest` (ambos lados: quién ve el campo y quién
  no), `ConsultationClosureTest` (la cita cerrada ya no copia el plan),
  `AppointmentBroadcastPayloadTest`, `ClinicalEventsChannelBoundaryTest`,
  `DashboardUpdatesPrivateChannelTest` (14 publicadores), más
  `PermissionCatalogTest`, `DeskMatrixTest`,
  `SpecialtiesNavigationDisambiguationTest`, `DomainChannelsAuthorizationTest`,
  `FrontendPermissionContractTest` y el fixture de permisos regenerado.
  `CREDENTIALS.md` suma las dos filas de la frontera y
  `docs/architecture/desks.md` retira el acto del escritorio de laboratorio.
- **2026-10-23 (seguridad: credencial local redactada y gate de secretos)** — La
  nota ODD de la interfaz de métodos de pago publicaba en su log la contraseña
  del MariaDB local de XAMPP; la misma clave aparecía en una nota de auditoría
  sin versionar. Se redactaron las dos, junto con las rutas absolutas de otras
  cinco notas. El valor sigue recuperable en la historia (`b914f45`, `2fd17a2`)
  y en cuatro cabeceras de PR cerradas: la decisión de aceptar el riesgo, sus
  condiciones (base local, `skip-networking`, phpMyAdmin bajo `Require local`,
  sin datos reales) y sus disparadores de rotación viven en `SECURITY.md`. Nuevo
  job `security` en CI: `gitleaks` 8.30.1 fijado y verificado contra el checksum
  publicado, corriendo sobre los commits que publica cada run (rango del PR o
  del push) con las reglas de `.gitleaks.toml` (contraseña de base escrita como
  valor, forma `root/<clave>` y allowlist de marcadores documentados). No
  re-escanea la historia: el hallazgo conocido ya está publicado. Guard
  `SecretScanGateTest` (3). Sin rutas, modelos, migraciones ni primitivas UI
  nuevas: los conteos de §4 no cambian.

n

- **2026-10-23 (asistente de cierre: el formulario se rearma entre atenciones,
  #311)** — `ConsultationWizard` vive montado en `/my-day` y `/calendar` y sólo
  alterna su visibilidad, así que su `payload` local sobrevivía entre una cita y
  la siguiente: el motivo de consulta, la evolución SOAP, los diagnósticos
  CIE-10, los materiales y los ítems del plan de la atención anterior seguían
  editables y viajaban al firmar el acto de otro paciente. El payload se
  construye ahora desde `createInitialPayload()` y `resetForm()` lo rearma
  completo —paso inicial, firma, ítems y búsquedas en vuelo— cuando cambia la
  cita y en cada apertura del asistente (`openForAppointment()`), no sólo la
  contraseña de firma. Sin rutas, modelos, migraciones ni primitivas UI nuevas:
  los conteos de §4 no cambian. Tests: `consultation-wizard-reset.test.js` (2) y
  el caso de firma de `consultation-closure-nts139.test.js` ajustado al paso
  inicial.
- **2026-10-23 (BI por sede y superficie de la sede, #303/#304)** — Los seis
  reportes de Business Intelligence y su exportación aceptan `branch_id`
  resuelto con `App\Authorization\BranchAccess::effectiveFilter`: un usuario con
  sede recibe la suya por omisión y pedir otra responde 403, y la exportación
  respeta el mismo filtro. El reporte de ingresos gana el corte «Por Sede», que
  agrupa por la sede del cajón, deja el dinero sin cajón en la fila `Sin sede` y
  suma exactamente el resumen del período. La atribución de un reporte de
  período por sede es la sede donde ocurrió la atención (#303 L2, ADR-0010):
  pacientes con cita en la sede con su dinero de la sede, sillones por su sede,
  profesionales por actividad, citas por `appointments.branch_id` e ingresos por
  `cash_register_sessions.branch_id`; `patients.branch_id` sigue siendo la sede
  de registro de la ficha, no la clave de un reporte. Superficie: nueva página
  `/settings/accounting-locks` (`manage.accounting_lock`) que lista los cuatro
  módulos con su fecha efectiva y su origen (sede, global o sin bloqueo) y
  permite fijar y retirar el bloqueo, distinguiendo gerencia (fila global) de
  administrador de sede; el detalle del catálogo suma el tab «Tarifario»
  (`manage.branch_price`) para alta, edición y retiro del precio por sede con
  ventana de vigencia, precio de lista y estado (vigente, futuro, vencido), y su
  API acepta `procedure_id` como filtro; BI gana selector de sede visible solo
  con alcance de grupo, alimentado por `GET /api/branches/active`, y el bloque
  consolidado con su doble desglose (que no se exporta). Los modales de dinero
  reconocen el bloqueo contable por código de máquina: `AccountingLockService`
  lanza `App\Exceptions\AccountingLockBlockedException`, que publica
  `code: accounting_lock_blocked` con el mismo 403 y el mismo mensaje, y el
  aviso se muestra en línea con el campo de motivo de al menos 10 caracteres y
  el reintento autorizado. §4 y §5 sincronizados: 20 módulos de dominio y 22
  páginas. Tests: `BranchScopeReportTest` (6), `AccountingLockHttpGuardTest`
  extendido, `ProcedureBranchPriceTest` (9), `businessIntelligenceBranch` (5),
  `business-intelligence-branch` (8), `accountingLockMeta` (12),
  `accounting-locks` (13), `branchTariff` (6), `procedure-tariff` (4),
  `accountingLockOverride` (7), `money-lock-notice` (3) y `usePermissions`
  extendido; `BusinessIntelligenceRenderTest` y `RevenueReportContractTest`
  ajustados al corte nuevo.
- **2026-10-23 (caja: imputación de pagos, gaveta histórica y concurrencia,
  #321/#322/#323)** — Tres consecuencias de #287 que quedaron declaradas.
  **Imputación (#321)**: `patient_charge_allocations` (`patient_charge_id`,
  `transaction_id`, `amount` decimal(12,2), `created_by`, único por par) dice
  qué cargo pagó un pago, sin mover dinero. `PatientChargeAllocationService`
  verifica las dos sumas —un pago no imputa más que su importe y un cargo no
  recibe más que el suyo— sobre filas bloqueadas en orden de `id` (cargos) y
  después el pago; sólo un pago `completed` cubre un cargo y una fila anulada
  deja de contar. Las puertas son `allocations` en `POST /api/transactions`
  (dentro de la misma transacción del pago) y
  `POST /api/transactions/{transaction}/allocations` (`manage.patient_charge`,
  sin `cash.session`: el ledger es del paciente). El estado de cuenta añade
  `charge_balances` (importe, imputado y saldo por cargo a la fecha de corte) y
  `unallocated_payments`; `summary`, `opening_balance` y `closing_balance` no
  cambian, y una devolución libera cobertura en proporción al pago que la
  originó. Los datos previos se leen como no imputados, sin backfill. Decisión
  en ADR-0018. **Gaveta histórica (#322)**: `cash:repair-electronic-movements`
  (`--dry-run`, idempotente) marca con soft delete y `audit_logs`
  (`cash_movement_repaired`) los movimientos ligados a métodos
  `card|transfer|wallet`, conserva los de método no comprobable y no reescribe
  ninguna columna de la sesión: el arqueo guardado queda como registro histórico
  y el esperado vivo se recalcula sin esas filas. **Concurrencia y bloqueo
  (#323)**: pago, devolución, depósito y cierre toman el lock de la fila de
  `cash_register_sessions` antes de leer o escribir la gaveta, y el depósito
  evalúa el bloqueo contable del módulo `cash_movements` sobre `deposited_at`
  (no sobre la fecha en que se escribe), con `lock_override_reason` de
  administrador y `accounting_lock_blocked`/`accounting_lock_overridden` en
  `audit_logs`. §4 sincronizado con valores medidos: 48 controllers API, 71
  modelos, 153 migraciones, 254 rutas API y 267 totales con web; §9 suma
  ADR-0018 y su índice. Sin cambios de UI ni de §5 (las tres issues son
  `area:backend`). Tests: `PatientChargeAllocationTest` (16),
  `RepairElectronicCashMovementsCommandTest` (6), `CashDrawerLockTest` (5),
  `CashDepositAccountingLockTest` (5) y el guard de índice de ADR. La
  verificación independiente dejó tres seguimientos, cerrados aquí: el tope de
  un cargo sólo descuenta imputaciones de pagos completados (anular un pago
  libera lo reservado), el reparto proporcional de una devolución se hace en
  centavos con el mayor residuo (ninguna cobertura negativa) y la anulación toma
  el lock de la sesión, como los otros escritores de gaveta.

- **2026-10-23 (caja: vocabulario del dinero y estado de cuenta del paciente,
  #287)** — La caja y la deuda del paciente dejan de compartir un solo
  vocabulario. `App\Support\MoneyVocabulary` es la única fuente de los cuatro
  tipos escribibles (`payment|refund|write_off|adjustment`), de su efecto en el
  saldo y de si mueven efectivo: **pago y devolución** exigen método y caja
  abierta y escriben `cash_movements` (`income`/`expense`); **condonación y
  ajuste** son asientos de ledger con autorización registrada de administrador
  (`authorized_by` + `authorization_reason`, con motivo obligatorio) que no
  exigen método, no exigen gaveta abierta y no cuentan como ingreso ni como
  cobro; **descuento** deja de ser un tipo de transacción y vive en el cobro
  (`discount_amount`/`discount_type`), donde reduce lo que el paciente debe sin
  mover plata. `income`/`expense` quedan como valores legacy de la columna, sin
  puerta de escritura (`discount` responde 422). La devolución está topada por
  lo cobrado no devuelto del paciente y, si declara `parent_transaction_id`, por
  lo devolvible de ese pago; la condonación, por el saldo pendiente. La guarda
  `cash.session` sale de las rutas de creación y anulación de transacciones
  (D7): la regla vive en `TransactionService`, que sigue exigiendo la sesión de
  la transacción abierta para anular. **Ledger de cargos**: nace
  `patient_charges` (`patient_id`, `clinical_encounter_id` único y nullable,
  `source = encounter|manual`, `description`, `amount` decimal(12,2),
  `charged_at`, `created_by`). El cierre de la atención escribe exactamente un
  cargo por acto dentro de su transacción (importe `final_amount`, fecha
  `closed_at`, descripción tomada del tipo de cita o del `mode`), la puerta
  manual es `POST /api/patient-charges` (`manage.patient_charge`, `CASH_ROLES`)
  y `charges:backfill` (`--dry-run`, idempotente) cubre las atenciones cerradas
  históricas con el mismo escritor. El libro es inmutable: el modelo rechaza
  `updating`/`deleting` y un cargo equivocado se corrige con un ajuste. **Estado
  de cuenta**: `GET /api/patients/{patient}/statement`
  (`view.patient_statement`) devuelve paciente, período (por omisión el mes
  actual hasta hoy), saldos de apertura y cierre, resumen y entradas
  cronológicas, con la regla única
  `cargos − descuentos − pagos + devoluciones − condonaciones + ajustes` y sólo
  transacciones `completed`. El saldo de apertura aplica la misma regla a todo
  lo anterior al período, así que el cierre de un período es la apertura del
  siguiente. **Depósitos y conciliación**: `cash_deposits` (`branch_id`,
  `cash_register_session_id`, `cash_movement_id`, `amount`, `deposited_at`,
  `reference`, `notes`) registra el depósito bancario por
  `POST /api/cash-register/deposits` (`manage.cash_deposit`) con sesión abierta,
  monto > 0 y ≤ efectivo disponible, referencia obligatoria y su `CashMovement`
  `withdrawal` en la misma transacción; `GET /api/cash-register/deposits` pagina
  y filtra por rango y sede efectiva con `view.cash_register`. El reporte diario
  añade `reconciliation.deposits.{count,total}` y
  `pending_deposit = cash.total − deposits.total`, y los agregados por método
  (`summary_by_payment_method`, `payment_methods_analysis`,
  `transactions_total`, `summary_by_hour`, `top_transactions`) netean
  devoluciones y excluyen los asientos sin efectivo. **Gaveta sólo-efectivo**:
  un cobro o una devolución con tarjeta, transferencia o billetera queda en
  `transactions` (y en el mix y el estado de cuenta) pero no crea
  `CashMovement`; anularlo tampoco mueve la gaveta. El ledger es del paciente
  (la ficha es compartida, ADR-0010); la gaveta, sus movimientos, los depósitos
  y la conciliación siguen acotados por sede. Permisos nuevos (los tres con
  `CASH_ROLES`): `manage.patient_charge`, `view.patient_statement` y
  `manage.cash_deposit`. Decisión en ADR-0015. §4 sincronizado con valores
  medidos: 48 controllers API, 71 modelos, 36 services + 9 report services, 152
  migraciones, 254 rutas API y 267 totales con web; sin cambios de UI ni de §5
  (la issue es `area:backend`). Tests: `MoneyVocabularyTest` unitario (8),
  `MoneyVocabularyTest` de API (18), `PatientChargeLedgerTest` (12),
  `BackfillPatientChargesCommandTest` (4), `PatientStatementTest` (9),
  `CashDepositTest` (13), `CashReportNettingTest` (4), más
  `CashLedgerIntegrityTest`, `CashReportPeriodTest` y `CashReportPopulationTest`
  ajustados. La UI de caja quedó entregada en #320; la imputación de un pago a
  un cargo y la reparación de la gaveta histórica se cerraron en #321/#322
  (ADR-0018 y `cash:repair-electronic-movements`).

- **2026-10-23 (laboratorio protésico: casos, fases y entregas, #285)** — El
  trabajo de laboratorio deja de vivir en la historia clínica. `lab_cases`
  guarda la orden del taller —paciente, pieza, trabajo, material, `sent_at`,
  `promised_at`, `professional_id` que prescribe y `technician_id` asignado— y
  `status` recorre las cinco fases del issue
  (`in_preparation → in_laboratory → try_in → delivered → placed`). La máquina
  sólo avanza: una fase posterior se acepta (un trabajo simple salta la prueba),
  la misma o una anterior responde 422 nombrando la fase actual y la pedida, y
  `status` no se escribe por `POST`/`PUT`: la única puerta es
  `POST /api/lab-cases/{id}/phase`. Entrar en `delivered` marca `delivered_at` y
  emite `LabCaseReady` (`caso_laboratorio_listo`) por el canal privado
  `lab-cases` y por `user.{professional_id}`; `placed` marca `placed_at` y no
  notifica. `GET /api/my-day` publica `ready_lab_cases` del profesional
  autenticado y su conteo, y el escritorio del profesional escucha el evento,
  avisa y refresca. **Alcance del escritorio**: `tecnico_dental` deja de leer la
  historia clínica y el acto clínico (`manage.medical_record`,
  `manage.evolution`, `manage.attachment`, `view.medical_record_stats` y
  `view.encounter`), no exporta la ficha del paciente (`export.patient`, cuyo
  PDF/ZIP lleva evoluciones, odontogramas, registros de especialidad y los
  binarios de los adjuntos) y deja de suscribirse al canal privado
  `medical-records`, que publica la evolución, los addenda y los adjuntos, con
  `TechnicianClinicalRecordScopeTest` cubriendo el criterio de aceptación;
  conserva el flujo clínico que fijaron F1/F2 (iniciar y cerrar la atención,
  abrir o anular un acto) y sus registros de especialidad y planes de
  tratamiento. Catálogo: `view.lab_case` (administrador, recepción, odontólogo,
  implantólogo, técnico, asistente) y `manage.lab_case` (administrador,
  odontólogo, implantólogo, técnico). Puertas: `GET /api/lab-cases`,
  `GET /api/lab-cases/{id}`, `POST /api/lab-cases`, `PUT /api/lab-cases/{id}` y
  `POST /api/lab-cases/{id}/phase`. La sede sigue siendo dimensión de
  autorización (`ScopesToBranch`: estampado en el alta, 403 en sede ajena, 404
  en binding). §4 sincronizado con valores medidos: 42 controllers API, 65
  modelos, 146 migraciones, 36 eventos, 246 rutas API y 259 totales con web.
  Tests: `LabCaseDomainTest` (2), `LabCasePhaseMachineTest` (12),
  `LabCaseApiTest` (16), `TechnicianClinicalRecordScopeTest` (2),
  `MyDayQueueTest` extendido (3 casos nuevos), `DomainChannelsPrivateTest`,
  `DomainChannelsAuthorizationTest`, `BroadcastingAuthChannelsTest`,
  `PermissionCatalogTest` y el smoke unitario `myDay`. Fuera de alcance,
  declarado: la pantalla del módulo (la issue es `area:backend`);
  `appointments.treatment_notes`, que el cierre copia del `plan` de la evolución
  y que la agenda publica desde F2 a todo el que lee citas —recepción incluida—,
  porque es la conducta de la cita y no la lectura del registro; y
  `MedicalRecordCreated`, que desde #249 publica el modelo completo en
  `dashboard-updates`, canal abierto a todos los roles.

- **2026-10-22 (cumplimiento NTS 139 y NTS 188: registro firmado, CIE-10 y
  motivo de consulta, #286)** — El acto clínico pasa a ser un documento firmado.
  `clinical_encounters` gana `reason_for_consultation` (aceptado al abrir la
  urgencia y exigido al cerrar) y el bloque de firma: `signed_by`, `signed_at`,
  `signer_license` y `signature_hash`. `diagnosis_catalog` deja de ser una tabla
  muerta: modelo `DiagnosisCatalog`, seeder con el capítulo odontológico de
  CIE-10 (K00-K14 más estomatología, 20 entradas) y superficie de lectura
  `GET /api/diagnoses` (permiso `view.diagnosis_catalog`); el acto codifica uno
  o más diagnósticos en `encounter_diagnoses` con copia congelada de `cie_code`
  y `name`, y una entrada inactiva no se puede codificar. El cierre exige
  motivo, al menos un diagnóstico y la firma: el profesional del acto reingresa
  su contraseña y `App\Support\ClinicalRecordSignature` sella un SHA-256 del
  contenido (acto, evoluciones, diagnósticos, odontograma y materiales; el plan
  queda fuera a propósito porque sigue vivo después del acto). Una contraseña
  inválida responde 422 (`clinical_signature_invalid_credentials`), firmar el
  acto de otro profesional 403 (`clinical_signature_not_professional`), y ningún
  rechazo escribe algo. El registro imprimible es
  `GET /api/encounters/{id}/record`: PDF con los bloques de NTS 139 y el
  odontograma de NTS 188, sólo para atención cerrada, con auditoría de lectura
  (`medical_record_viewed`, `surface = attention_record`) y el hash de
  verificación impreso. El asistente de cierre del SPA captura los tres campos.
  §4 sincronizado con valores medidos: 42 controllers API, 66 modelos, 147
  migraciones, 17 seeders activos, 244 rutas API y 257 totales con web.
  ADR-0014. Tests: `ClinicalRecordComplianceTest` (9), `DiagnosisCatalogTest`
  (8), `EncounterRecordPdfTest` (5), el trait `ClinicalCompliancePayload` en los
  siete tests de cierre previos y el fixture de permisos regenerado. Fuera de
  alcance, documentado: retención y archivo por antigüedad y la firma con
  certificado digital.

- **2026-10-22 (continuidad y planes versionados, #284/#288)** — Dos decisiones
  de dominio que comparten cimiento. **Continuidad (#284)**: nace
  `patient_recalls` (paciente, intervalo 3/6/12, motivo, `due_date`, estado y
  cita de control) con cola de vencidos y métrica de cumplimiento
  (`completed / (completed + overdue)`), y `continuity_requests` sustituye la
  cita que el cierre inventaba: el profesional deja la conducta con fecha
  preferida y recepción la materializa por
  `POST /continuity-requests/{id}/schedule` o
  `POST /patient-recalls/{id}/schedule`, puertas que delegan en
  `AppointmentService` y validan profesional y sillón; un conflicto deja la
  solicitud pendiente sin cita. El cierre acepta `recall` (intervalo y motivo) y
  conserva `next_appointment` como conducta. `GET /api/continuity/queue` combina
  recalls vencidos y solicitudes pendientes acotados por sede. La ejecución del
  plan deja de escribirse a mano: `treatment_plan_item_consumptions` liga el
  acto al ítem y el estado se deriva (`completed` si cubre `quantity`,
  `in_progress` si cubre parte, `pending` sin consumo); el plan publica
  `executed_quantity`, `pending_quantity`, `session_count` y `consumptions`.
  **Planes (#288)**: `treatment_plan_items` gana `priority`
  (`urgent|elective|optional`) y `sequence`; `treatment_plan_versions` guarda la
  foto inmutable (cabecera, ítems, alternativas y rechazos) con
  `current_version_id`/`active_version_id`, y
  `quotations.treatment_plan_version_id` se fija al aprobar el presupuesto, así
  que editar el plan después de la firma abre una versión nueva sin tocar lo
  aceptado. `treatment_plan_item_alternatives` registra opciones A/B por pieza;
  aceptar una copia sus datos al ítem y rechaza las demás con fecha y motivo, y
  un ítem opcional rechazado conserva `rejected_at`/`rejection_reason`. La
  máquina de estados responde 422 (antes 500), `approved` exige al menos una
  línea vigente y `completed` exige todas las líneas no canceladas consumidas.
  `StoreTreatmentPlanRequest` y el `PUT` reconcilian líneas por `id` en lugar de
  borrar y recrear, con lo que los consumos sobreviven a la edición. §4
  sincronizado con valores medidos: 41 controllers API, 64 modelos, 30 services,
  145 migraciones, 241 rutas API y 254 totales con web. ADR-0013. Tests:
  `RecallServiceTest` (11), `ContinuityTest` (9), `PlanSessionConsumptionTest`
  (9), `TreatmentPlanPriorityTest` (4), `TreatmentPlanVersioningTest` (9),
  `TreatmentPlanAlternativesTest` (7), más el fixture de permisos regenerado y
  los smokes JS del modal, detalle y lista de planes. Follow-up declarado: la
  superficie web de la cola de continuidad de recepción (la issue es
  `area:backend`).

- **2026-10-23 (materiales e inventario: consumo desde la atención y stock
  real,** **#295)** — El almacén deja de ser un catálogo congelado. El
  procedimiento declara lo que consume (`procedure_catalog_materials`: insumo +
  cantidad estimada, editado como `material_recipe` en el alta y la edición del
  catálogo, con reemplazo completo al enviarlo y publicado en el detalle), y el
  cierre de la atención lo descuenta. `InventoryService::consumeForEncounter`
  bloquea los productos en orden de `id`, evalúa la guarda sobre la demanda
  **agregada** por insumo —dos lotes del mismo material son una sola demanda—,
  congela el costo unitario de cada línea y escribe un `ProcedureMaterial` y un
  `StockMovement` `out` por lote, con lote, vencimiento y
  `clinical_encounter_id`. `products.stock_quantity` sigue siendo la única
  verdad del saldo y baja una sola vez por producto, dentro de la transacción
  del cierre. Un material que el almacén no cubre rechaza el cierre con 422
  (`Stock insuficiente para «Resina A2»: disponible 2, solicitado 5.`) y no
  escribe nada: ni evolución, ni material, ni movimiento, ni saldo. Un
  `product_id` inexistente pasa de omisión silenciosa con warning a 422
  (`materials.*.product_id` gana `exists:products,id`). Las cantidades dejan de
  ser enteras —`products.stock_quantity`/`minimum_stock`/`maximum_stock`,
  `stock_movements.quantity`/`previous_stock`/`new_stock` y
  `procedure_materials.quantity_used` pasan a `decimal(12,3)`— porque 0.5
  jeringa entraba como 1 y el libro no podía representar lo que hay en el
  estante. Superficie de almacén, seis rutas y dos permisos:
  `GET /api/inventory/products` (stock, mínimo, vencimiento y KPIs sobre la
  población sin filtros), `GET /api/inventory/products/{id}` (con sus últimos
  movimientos), `GET /api/inventory/movements` y `GET /api/inventory/alerts`
  bajo `view.inventory`; `POST /api/inventory/receipts` y
  `POST /api/inventory/adjustments` bajo `manage.inventory`. La recepción
  escribe un movimiento `in` con lote, vencimiento y referencia y no reprecifica
  el catálogo; el ajuste por conteo fija el saldo en lo contado, con la magnitud
  positiva en el libro y la dirección en `previous_stock`/`new_stock`, y
  responde 422 si el conteo coincide con el saldo. El movimiento se estampa con
  la sede del actor cuando la tiene y queda sin sede en alcance de grupo
  (ADR-0010); `stock_movements.branch_id` pasa a nullable. `requires_materials`
  deja de ser un flag manual: un acto exige materiales cuando alguno de los
  procedimientos que documenta declara receta —el procedimiento por defecto del
  tipo de cita, los ítems que el cierre declara o los del plan del acto—, con
  una sola consulta; la columna legacy se sigue aceptando al escribir pero ya no
  decide y las respuestas publican el valor derivado. El cierre ahora persiste
  `procedure_catalog_id` en los ítems del plan que crea y el request valida ese
  campo: sin ambos, la regla no era alcanzable por HTTP. `consultation-context`
  publica además `material_recipe`, la receta agregada de los procedimientos
  implicados con el stock de cada insumo, que es la precarga del asistente. §4
  sincronizado con valores medidos: 39 controllers API, 59 modelos, 25 services,
  136 migraciones, 224 rutas API y 237 totales con web. Tests:
  `InventorySchemaTest` (5), `ProcedureCatalogMaterialRecipeTest` (8),
  `EncounterMaterialConsumptionTest` (10), `InventoryReadSurfaceTest` (10),
  `InventoryWriteSurfaceTest` (9), `RequiresMaterialsDerivationTest` (6), más
  `ConsultationClosureTest`, `EncounterReadSurfaceTest` y
  `AppointmentTypeCrudContractTest` ajustados a la regla nueva. Fuera de
  alcance, documentado: saldo por lote (el lote es trazabilidad, no FIFO), orden
  de compra formal y UI del módulo (la issue es `area:backend`).

- **2026-10-22 (catálogo: aplicabilidad, tarifario y vigencia, #293)** — El
  catálogo deja de ser un nomenclador a medias. `procedure_catalog.site_type`
  declara sobre qué se hace un procedimiento
  (`none|tooth|surface|quadrant|arch`, por omisión `none`) y
  `procedure_external_codes` guarda sus códigos en el catálogo público (RM
  550-2023/MINSA) y en el tarifario del SIS, con búsqueda por código externo.
  Una línea de plan o de presupuesto que nombra un procedimiento con sitio debe
  declararlo —pieza, superficie (que ya nombra su pieza), cuadrante o arcada—;
  `ProcedureSiteRule` es la única regla y la aplican los dos escritores, y el
  traspaso plan → presupuesto se niega a emitir una línea previa a la regla. El
  tarifario pasa a una sola tabla: `procedure_prices` (sede y convenio
  opcionales, ventana de vigencia) sustituye a `procedure_branch_prices`, con
  las filas copiadas y sus `id` preservados, y `payers` es el catálogo de
  convenios. La cascada a la fecha de referencia es convenio+sede → convenio →
  sede → `default_cost`, y el resultado nombra su origen y la fila aplicada. El
  presupuesto resuelve el precio el día de su emisión, no el día del plan, y
  guarda la evidencia (`price_source`, `price_row_id`, `price_valid_from`,
  `price_valid_to`) y su convenio (`payer_id`); el bruto del documento es la
  suma de sus líneas. Cambiar el tarifario no mueve un presupuesto emitido ni
  una atención cerrada. `tooth_surfaces` deja de estar vacía:
  `ToothSurfaceSeeder` da cinco superficies canónicas (V/L/M/D/O) a cada pieza
  activa y `GET /api/dental-pieces` las publica. Permisos nuevos: `view.payer`,
  `manage.payer` y `manage.payer_price` (este último con `branch.global`).
  Puertas: `GET|PUT|DELETE /api/branches/{branch}/procedure-prices` (sin
  convenio) y `GET|PUT|DELETE /api/payers/{payer}/procedure-prices` (convenio,
  sede opcional), más `GET /api/payers`, `GET /api/payers/active`,
  `POST /api/payers` y `PUT /api/payers/{payer}`. §4 sincronizado con valores
  medidos: 38 controllers API, 58 modelos, 133 migraciones, 218 rutas API y 231
  totales con web, 16 seeders activos. Superficie: el detalle del catálogo
  declara la aplicabilidad y las equivalencias, el alta del plan elige
  procedimiento y sitio, y el presupuesto elige convenio y sitio por línea.
  Queda declarada como follow-up, en la órbita de #304, la pantalla de
  administración de convenios y del tarifario de convenio. Tests:
  `ProcedureCatalogApplicabilityTest`, `ProcedureExternalCodeTest`,
  `ProcedurePriceCascadeTest`, `PayerCatalogTest`,
  `ProcedureSiteRequirementTest`, `QuotationPriceFreezeTest`,
  `DentalPiecesEndpointTest` extendido y los smokes
  `procedure-catalog-form-site`, `treatment-plan-site` y `quotation-site-payer`
  con el unitario `procedureSite`. Suite MySQL:
  `OK (2428 tests, 14285 assertions)`; Vitest: 946 pruebas en 114 archivos;
  ADR 0012.

- **2026-10-22 (presupuestos con IGV explícito e inmutables, y métodos de pago**
  **con familia y referencia, #289/#291)** — Dos decisiones de dominio sobre
  dinero. **IGV**: el precio del catálogo y del plan es el precio de venta, así
  que el impuesto se descompone del importe en vez de sumarse encima. Antes,
  declarar 18% convertía un procedimiento de S/ 118 en S/ 139.24; ahora
  `subtotal` (base imponible) + `tax_amount` (IGV) == `total_amount` (precio del
  paciente), y la tasa por defecto sale de `config/billing.php`
  (`BILLING_TAX_PERCENTAGE`, 18.00) — una tasa declarada en 0 es el modo exento,
  no un accidente del default. Una sola implementación
  (`App\Support\QuotationTax`) alimenta la puerta manual y la del cierre de la
  atención, y un `PATCH` parcial recalcula desde el bruto y la tasa ya guardados
  en vez de poner los importes en cero (`updateQuotation` tomaba
  `subtotal ?? 0`). La decisión vive en ADR-0011. **Inmutabilidad**: `PUT` y
  `PATCH /api/quotations/{id}` responden 422 para `approved`
  (`Solo se puede modificar un presupuesto en borrador, enviado o visto. Estado actual: approved.`),
  `rejected` y `expired`, antes del bloqueo contable y antes de escribir; el
  snapshot de precios sobrevive al cambio posterior del catálogo o del plan.
  `draft`, `sent` y `viewed` siguen editables. **Métodos de pago**:
  `payment_methods` gana `type` (`cash`, `card`, `transfer`, `wallet`) y
  `requires_reference`; los métodos del sistema quedan Efectivo→`cash`,
  Transferencia→`transfer`, tarjeta de crédito y débito→`card`, Yape→`wallet`, y
  los tres medios que dejan voucher o número de operación exigen referencia.
  `TransactionService::createTransaction` rechaza el cobro con 422
  (`El método de pago {nombre} exige un número de referencia.`), regla que cubre
  las dos puertas —`POST /api/transactions` y
  `POST /api/pending-payments/{id}/pay`, que ahora acepta y guarda
  `reference_number`— sin escribir transacción, movimiento ni saldo. La
  conciliación del día separa las familias: `GET /api/cash-reports/daily` añade
  `reconciliation.cash` y `reconciliation.electronic`, y cada fila de
  `summary_by_payment_method` y de `payment_methods_analysis` declara su `type`.
  Las superficies acompañan el cambio de contrato: el modal de presupuestos
  descompone el IGV en el preview y deja de inventar una tasa 0, el detalle y el
  PDF parten del precio de lista (`total + descuento`) porque `subtotal` es la
  base imponible, la UI admin del método suma el selector de familia y el check
  de referencia, y en caja el método elegido marca la referencia como
  obligatoria y el 422 cae en el campo. La verificación independiente encontró
  dos defectos, ambos cerrados aquí: un `PATCH` parcial descontaba dos veces, y
  el desglose impreso no llegaba al total en presupuestos con descuento. §4
  sincronizado con el valor medido: 125 migraciones. Sin rutas, controllers,
  modelos ni primitivas UI nuevas; `scripts/audit/doc-drift.mjs` sigue en verde.
  Tests: `QuotationTaxPolicyTest` (12), `QuotationImmutabilityTest` (8),
  `QuotationDocumentBreakdownTest` (4), `PaymentMethodFamilyTest` (8),
  `PaymentReferenceRequirementTest` (9), `CashReconciliationByFamilyTest` (6), y
  los smokes de `paymentMethodMeta`, del formulario de métodos, del modal de
  presupuesto, del detalle y de `caja-cobros` extendidos. Fuera de alcance,
  documentado en la issue: comprobante de pago/SUNAT (#289) y pago mixto más el
  gobierno de `requires_authorization`/`allows_change` (#291).

- **2026-10-22 (la sede autoriza: escritorios, bloqueo contable, tarifario y**
  **consolidado, #283/#290)** — La sede deja de ser un filtro de consulta y pasa
  a ser una dimensión de autorización (ADR-0010). `users.branch_id` es la
  afiliación: con valor el usuario opera sólo esa sede, sin valor tiene alcance
  de grupo; un `administrador` con sede es el administrador de esa sede.
  `App\Authorization\BranchAccess` es la regla única y se aplica en tres capas:
  el trait `ScopesToBranch` (guarda de escritura con estampado de sede en altas
  y binding acotado a 404), el middleware `EnforceBranchScope` (403 de un
  `branch_id` ajeno, incluida una `{branch}` de ruta) y
  `RequireGlobalBranchScope` para el catálogo de sedes. La ficha del paciente y
  la historia clínica siguen compartidas entre sedes; agenda, sillones, caja,
  personal e inventario no. Los seis escritorios de F0 quedan explícitos en
  `App\Authorization\Desks` y publicados en `docs/architecture/desks.md`, con
  guarda de que cada rol vive en un escritorio y cada permiso pertenece a
  alguno. Bloqueo contable: `accounting_locks` (`locked_through` por módulo y
  sede, la sede sobre la global) responde 403 y deja `accounting_lock_blocked`
  al mutar dinero anterior al cierre; la autorización registrada viaja como
  `lock_override_reason` (≥10 caracteres, sólo administrador) y deja
  `accounting_lock_overridden`. Tarifario: `procedure_branch_prices` gana sobre
  `default_cost` dentro de su ventana de vigencia y el picker responde el precio
  de la sede conservando `list_cost`; cotización y plan guardan el snapshot.
  Reportes: `GET /api/reports/consolidated` publica `summary`, `by_branch` y
  `by_professional`, y cada desglose cuadra con el resumen porque el dinero que
  no resuelve cae en «Sin sede» o «Sin cita asociada». Permisos nuevos:
  `manage.accounting_lock`, `override.accounting_lock` y `manage.branch_price`
  (administrador). §4 sincronizado con valores medidos: 37 controllers API, 56
  modelos, 124 migraciones, 211 rutas API y 224 totales con web. Tests:
  `BranchAccessTest`, `DeskMatrixTest`, `BranchScopeReadGuardTest`,
  `BranchScopeWriteGuardTest`, `BranchScopeAdministrationTest`,
  `AccountingLockServiceTest`, `AccountingLockManagementTest`,
  `AccountingLockTransactionGuardTest`, `AccountingLockCashMovementGuardTest`,
  `AccountingLockMoneyGuardTest`, `AccountingLockHttpGuardTest`,
  `ProcedureBranchPriceTest`, `ConsolidatedReportTest` y
  `ArchitectureDecisionsIndexTest` con el ADR 0010. Follow-up declarado: filtro
  por sede en los reportes de citas, pacientes, profesionales, utilización y
  dashboard, y la UI de tarifario, bloqueo y BI (las issues son `area:backend`).

- **2026-10-22 (análisis IA como apoyo a la decisión: revisión obligatoria,**
  **anulación con traza y costo, #296)** — El análisis deja de ser un documento
  suelto colgado del adjunto. `ai_image_analyses` gana `clinical_encounter_id`
  (FK nullable `nullOnDelete`) que se hereda del adjunto o se recibe explícito
  —422 si la atención es de otro paciente—, y `GET /api/encounters/{id}` publica
  `ai_analyses` con solo los análisis revisados y vigentes del acto: un análisis
  sin revisar no aparece en el registro. `DELETE /api/ai-analysis/{id}` deja de
  existir (405) y nace `POST /api/ai-analysis/{id}/void` con `reason`
  obligatorio (≤255) que escribe `voided_at`/`voided_by`/`void_reason` y
  **conserva** `reviewed`, `reviewed_by`, `reviewed_at`, `review_decision` y
  `review_notes`: la traza de revisión sobrevive a la anulación. Las lecturas
  (`index`, `pending`, `byPatient`, `byAttachment`, `stats`) excluyen lo
  anulado, `index?voided=1` lo hace consultable y `stats` lo cuenta aparte. Las
  dos puertas de alta (`upload-and-analyze` y `analyze/{attachment}`) exigen
  `notice_acknowledged` y guardan `notice_acknowledged_at`/`_by`; el recurso
  publica `assistive`, `assistive_notice`, la declaración y `estimated_cost`
  (USD, `tokens_used / 1000 × services.openai.price_per_1k_tokens`), y
  `services.openai.daily_analysis_limit_per_patient` (0 = sin tope) rechaza con
  422 el análisis que exceda el tope diario del paciente. Un alta rechazada ya
  no deja un adjunto huérfano. Catálogo de permisos: `delete.ai_analysis` pasa a
  `void.ai_analysis` y las cuatro capacidades de IA dejan de viajar con
  `TREATMENT_ROLES` —que incluye `tecnico_dental`— para usar `AI_ROLES`;
  `tecnico_dental` recibe 403 en todo el módulo y la matriz de `CREDENTIALS.md`
  lo refleja. Nuevo `ai_analysis_citations` (polimórfico: `ClinicalEvolution` o
  `TreatmentPlan`, `cited_by`, único por par, sin `updated_at`):
  `cited_analysis_ids` es opcional en el alta de la evolución y en el
  alta/edición del plan, y la cita se niega si el análisis no existe, es de otro
  paciente, no se completó, no fue revisado, fue anulado o el autor no puede
  leer el módulo; un análisis citado tampoco puede anularse. §4 re-medido: 54
  modelos, 22 services, 122 migraciones; §5: la entrada `/ai-analysis` deja de
  listar «clínicos». Tests: `AiAnalysisDecisionSupportSchemaTest`,
  `AiAnalysisRoleScopeTest`, `AiAnalysisDecisionSupportTest`,
  `AiAnalysisCitationTest`, `EncounterReadSurfaceTest` extendido,
  `AiAnalysisContractTest` adaptado y los cuatro smokes JS del módulo.

- **2026-10-22 (historia clínica inmutable: addenda, sin borrado y auditoría de
  lectura, #292)** — La historia clínica deja de poder editarse o eliminarse.
  Nueva tabla `clinical_addenda` (`medical_record_id` obligatorio,
  `clinical_evolution_id` nullable, `user_id` autor, `reason` ≤255, `content`)
  sin `updated_at`; `ClinicalAddendum` rechaza `updating` y `deleting`.
  `MedicalRecord` y `ClinicalEvolution` pierden `SoftDeletes` y rechazan
  `deleting` (`ClinicalRecordImmutableException`), así que las columnas
  `deleted_at` se conservan pero una fila marcada por la regla anterior vuelve a
  contar en la historia. Rutas: `apiResource('medical-records')` queda en
  `index/store/show`, desaparecen `PUT/PATCH /medical-records/{id}`,
  `PUT /medical-records/evolutions/{id}` y los dos `DELETE`; corregir es
  `POST /medical-records/{id}/addenda` (`manage.medical_record`) y
  `POST /medical-records/evolutions/{id}/addenda` (`manage.evolution`), con
  autor de sesión y auditoría `clinical_addendum_created` en la misma
  transacción. Toda lectura clínica escribe `medical_record_viewed` contra la
  historia vía `ClinicalRecordAuditService` (superficies `index`, `record`,
  `evolutions`, `attachments`, `stats`) y no se silencia: si la auditoría falla,
  la lectura falla. `getEvolutions` responde 404 para una historia inexistente
  en vez de una lista vacía. Los 500 de las dos puertas de addenda registran la
  excepción en el log y responden un mensaje genérico. Eventos: se retiran
  `MedicalRecordUpdated`, `ClinicalEvolutionUpdated` y
  `ClinicalEvolutionDeleted` y nace `ClinicalAddendumCreated` en el canal
  privado `medical-records`. La interfaz reemplaza editar/eliminar por «Agregar
  addendum» y muestra los addenda junto al original en timeline y detalle.
  Conteos medidos: 54 modelos, 35 eventos, 122 migraciones, 204 rutas API y 217
  totales, 22 services, 13 modelos con SoftDeletes. Tests:
  `ClinicalAddendumDomainTest`, `ClinicalRecordImmutabilityTest`,
  `ClinicalAddendumApiTest`, `MedicalRecordReadAuditTest`,
  `MedicalRecordEvolutionTest`, `MedicalRecordEvolutionEventsTest` reescritos,
  `SoftDeletesTest`, `MedicalRecordIndexTest`, `RoutePermissionMatrixTest`,
  `DomainChannelsPrivateTest`, los guards de `tests/Unit/DesignSystem` de
  Historias y los smokes JS del módulo (incluido
  `medical-records-addendum.test.js`).

- **2026-10-22 (atención clínica: entidad del acto, cierre único y urgencia
  sin** **cita, #282)** — La cita deja de cargar el acto clínico. Nueva tabla
  `clinical_encounters` con estado propio (`open → closed | voided`),
  profesional, historia clínica, plan e importe, y relación cita 1 → 0..n
  atenciones. Las cinco filas que produce el cierre (`clinical_evolutions`,
  `odontogram_records`, `procedure_materials`, `clinical_attachments`,
  `quotations`) ganan `clinical_encounter_id` y conservan `appointment_id`,
  porque los lectores actuales consultan por cita;
  `procedure_materials.appointment_id` pasa a nullable para que una urgencia no
  necesite agenda. Puertas: `POST /appointments/{id}/start-attention` abre el
  acto de la cita en la misma transacción de la custodia, `POST /api/encounters`
  abre la urgencia sin cita (sin fila de agenda),
  `POST /api/encounters/{id}/void` solo anula una atención abierta y
  `POST /api/encounters/{id}/complete` cierra con el mismo motor que
  `POST /api/appointments/{id}/complete`: `ConsultationService::close()` es la
  única implementación y `complete()` es el adaptador de la cita. La cita queda
  como proyección de una sola pluma: `status`, `completed_at`, `final_amount` y
  `consultation_mode` los escribe solo el cierre, que se niega a proyectar si la
  atención no quedó cerrada. Los estados de custodia siguen sin ser alcanzables
  por `PATCH /appointments/{id}/status`, y `Store`/`UpdateAppointmentRequest`
  tampoco aceptan `completed`. `encounters:backfill` da una atención cerrada a
  cada cita completada histórica y enlaza sus filas clínicas huérfanas; es
  idempotente (segunda corrida: 0 creadas, 0 enlazadas) y tiene `--dry-run`.
  `ready-to-bill` no cambia: sigue leyendo la proyección de la cita, con
  regresión comparada antes y después del backfill. Permisos nuevos:
  `create.encounter`, `view.encounter`, `close.encounter` y `void.encounter`,
  todos clínicos. Lectura: `GET /api/encounters` (paciente, cita, estado y
  ventana de fechas) y `GET /api/encounters/{id}`. §4 sincronizado con valores
  medidos: 35 controllers API, 52 modelos, 119 migraciones, 206 rutas API y 219
  totales con web. Sin cambios de UI ni de §5: la issue es `area:backend`.
  Tests: `ClinicalEncounterDomainTest`, `BackfillClinicalEncountersCommandTest`,
  `EncounterOpeningTest`, `UrgentEncounterTest`, `ConsultationClosureTest`,
  `UrgentEncounterClosureTest`, `ReadyToBillRegressionTest`,
  `EncounterReadSurfaceTest`, y `PermissionCatalogTest` y
  `RoutePermissionMatrixTest` extendidos. Cuatro decisiones de contrato que el
  alcance no fijaba: `mode` del acto describe el modo de documentación y la
  urgencia se reconoce por `appointment_id` nulo; la próxima cita exige cita de
  origen (continuidad es F4/#284); solo un administrador atribuye la urgencia a
  otro profesional; y una cotización de urgencia nace con `appointment_id` nulo
  y `clinical_encounter_id` propio.

- **2026-10-22 (custodia: llegada, anuncio y escritorio Mi día, #281)** — La
  cita deja de usar `in_consultation` para dos actos distintos. `arrived`
  registra la llegada que marca recepción, con `checked_in_at` y
  `checked_in_by`; `in_consultation` queda para la atención que inicia el
  profesional. Cada transición escribe `appointment_custody_logs` (acción,
  puerta `reception|professional`, estado de origen y destino, autor y hora) y
  `PATCH /appointments/{id}/status` rechaza con 422 los tres estados de custodia
  nombrando su puerta real. Las puertas son `POST /appointments/{id}/check-in`
  (`check_in.appointment`; recepción y respaldo clínico, que queda registrado
  con puerta `professional`), `POST /appointments/{id}/start-attention`
  (`start.consultation`, roles clínicos) y `POST /appointments/{id}/complete`
  (cierre clínico, sin cambios). El anuncio `POST /appointments/{id}/announce`
  (`announce.patient`) emite `App\Events\PatientReady` como `paciente_listo` en
  el canal privado `user.{id}` y no crea estado: la cita sigue `arrived`. Nuevo
  `GET /api/my-day` (`view.my_day`) devuelve `waiting`/`attending`/`upcoming`
  del profesional autenticado, y la página `/my-day` es su escritorio. Catálogo
  de permisos: 71. §4 sincronizado: 34 controllers API, 51 modelos, 37 eventos,
  118 migraciones, 201 rutas API y 214 totales con web; §5: 19 módulos y 21
  páginas; 40 composables. Tests: `AppointmentCustodyArrivalTest`,
  `AppointmentCustodyAttentionTest`, `PatientReadyAnnouncementTest`,
  `MyDayQueueTest`, `AppointmentStatusDoorGuardTest`,
  `appointmentStatus.test.js`, `myDay.test.js` y el smoke de Mi día;
  `RoutePermissionMatrixTest` suma `startAttention` a las escrituras clínicas
  que recepción no alcanza.

- **2026-10-21 (permisos: catálogo `verbo.recurso` y cierre de la grieta**
  **clínica, #280)** — La autorización deja de escribirse en tres fuentes
  divergentes. `app/Authorization/Permissions.php` es la única fuente: catálogo
  de 68 permisos `verbo.recurso` y matriz rol → permisos. El middleware
  `permission` (alias `CheckPermission`) reemplaza a `role:` en las 198 rutas
  API: ninguna conserva listas de roles. `POST /api/auth/login` y
  `GET /api/auth/me` devuelven `permissions` y
  `resources/js/composables/usePermissions.js` deriva sus ~60 claves `can.*` de
  esa lista, sin repetir la matriz; el cierre de consulta del calendario se
  oculta sin `complete.appointment`. Recepción pierde la escritura clínica por
  cualquier endpoint, incluido `POST /appointments/{id}/complete`, que escribe
  `ClinicalEvolution`, plan, materiales y adjuntos; conserva check-in, estado de
  cita y cobro. `PATCH /appointments/{id}/status` sigue disponible porque no
  escribe historia clínica. Tests: `PermissionCatalogTest`,
  `CheckPermissionMiddlewareTest`, `RoutePermissionMatrixTest` (falla con
  archivo y línea si una ruta vuelve a declarar `role:`),
  `FrontendPermissionContractTest` (cruza las claves del SPA con el catálogo y
  el fixture de smoke), `AuthPermissionsPayloadTest` y
  `tests/js/unit/usePermissions.test.js`. Sin rutas, migraciones, modelos ni
  primitivas UI nuevas: los conteos de §4 no cambian.
  `scripts/audit/probe-rbac.mjs` y `probe-rbac-live.mjs` se retiran para
  revisiones actuales (salen con código 2 y remiten al guard nuevo) porque
  derivaban su expectativa de `role:`.

- **2026-10-21 (ADR 0007/0008 y los seguimientos de adjuntos y dependencias,**
  **#264/#265/#266/#267)** — Los dos ADR de §9 dejan de ser una fila sin cuerpo:
  `0007-user-specialty-source-of-truth.md` y
  `0008-procedure-catalog-legacy-specialty.md` describen contexto, decisión,
  alternativas y consecuencias, con las rutas que citan verificadas contra el
  repositorio. El índice pierde la fila `0009 (pending — slice 05)` —esa
  decisión la fija `tests/Unit/SddCheckMigrationsTest.php`, no un documento
  pendiente— y cambia la sección del change archivado por una nota histórica.
  Nuevo guard `tests/Unit/Documentation/ArchitectureDecisionsIndexTest.php`:
  todo enlace del índice resuelve en disco, todo cuerpo de la carpeta está
  indexado y ningún `docs/decisions/` sobrevive fuera del changelog. En la misma
  sesión, dos unidades de comportamiento: `MedicalRecord::attachments()` y
  `ClinicalEvolution::attachments()` dejan de ofrecer filas archivadas (el
  binario ya no existe desde #244 y la URL `/storage/...` respondía 404), y
  `ClinicalAttachmentService` pierde `update()`, `delete()` y
  `getAttachmentUrl()` —sin llamadas, y con un `delete()` que duplicaba el de
  `MedicalRecordService`— conservando `store()`, que sí usa
  `POST /api/ai-analysis/upload-and-analyze`; un guard estático impide la
  segunda implementación. Del mismo lote, `package.json` deja de declarar
  `xlsx`, `html2canvas`, `jspdf` y `vue-chartjs`, sin consumidor en
  `resources/js` ni en `vite.config.js`. Sin rutas, migraciones, modelos ni
  primitivas UI nuevas: los conteos de §4 no cambian y el guard
  `scripts/audit/doc-drift.mjs` sigue en 14 chequeos verdes.

- **2026-10-21 (canales de dominio privados, #249)** — Los 27 eventos que aún
  publicaban en canales públicos de Reverb (`appointments`, `reminders`,
  `medical-records`, `specialty-records`, `treatment-plans`, `cash-register`,
  `transactions`, `procedure-catalog`, `users` y `user.{id}`) pasan a
  `PrivateChannel`: un canal público no verifica identidad y cualquier cliente
  con la app key recibía citas, historia clínica, caja, planes, montos y
  usuarios sin autenticarse. `BroadcastingAuthController` (la autoridad
  efectiva) autoriza cada canal con los mismos roles que ya exige su lectura en
  `routes/api.php` —citas y recordatorios sin finanzas; historia clínica y
  planes/registros de especialidad solo clínicos; caja y transacciones con
  administrador/finanzas/recepcionista; catálogo con los siete roles; usuarios
  solo administrador— y `routes/channels.php` lo refleja como documentación. En
  la misma pasada, el canal de sesión `cash-session.{id}` —que ya era privado—
  deja de aceptar a cualquier usuario autenticado y exige los mismos tres roles
  de caja, porque publica cobros y movimientos del ledger. `PatientFileExported`
  deja de usar `Channel('user.{id}')` y queda en el canal privado del propio
  usuario exportador que el handler ya conocía. Los ocho consumidores
  (`useWebSocketNotifications`, `DashboardPage`, `useCashRegister`,
  `CalendarPage`, `MedicalRecordsPage`, `PatientDetailPage`,
  `TreatmentPlansPage`, `SpecialtyRecordsPage`) se suscriben con
  `privateChannel` y liberan el nombre real (`private-*`): `leave()` de Laravel
  Echo 1.19 no agrega el prefijo, así que el `leave` del nombre base era inerte
  —se corrige también en `patients` y `quotations`—. Sin rutas, migraciones,
  modelos ni primitivas UI nuevas: los conteos de §4 no cambian. Tests:
  `DomainChannelsPrivateTest` (publicadores y nombre en el cable),
  `DomainChannelsAuthorizationTest` (matriz de roles por canal, 403 al rol fuera
  de la lista y 401 al anónimo) y
  `tests/js/unit/domain-channels-private.test.js` (suscripción privada, `leave`
  del nombre real y guarda estática de consumidores). `sdd:check-events` sigue
  en 0 huérfanos.
- **2026-10-21 (registros de especialidad — #246/#247)** — El contrato de
  registros de especialidad deja de mentir sobre lo que guarda. Las cinco tablas
  (`implantology_records`, `orthodontics_records`, `endodontics_records`,
  `rehabilitation_records`, `oral_surgery_records`) ganan `procedure_id`
  nullable con FK a `procedure_catalog` (`nullOnDelete`), el patrón que ya usan
  `appointments` y `quotations`: `StoreSpecialtyRecordRequest` validaba el campo
  desde el slice 02 y `validated()` lo descartaba en silencio, así que un POST
  con `procedure_id` respondía 201 sin vínculo; la API ahora lo persiste y lo
  devuelve con la relación `procedure` precargada en los cinco listados.
  `oral_surgery_records` gana `surgery_date` (nullable,
  `date|before_or_equal:today`): el listado de cirugía oral ordena por esa fecha
  y no por `created_at`, y las filas existentes se fechan desde
  `appointments.scheduled_at` con `created_at` como respaldo.
  `rehabilitation_records` gana `try_in_date` (nullable, `date`), coherente con
  el estado `try_in` que existía sin fecha; sin backfill, porque las otras
  fechas del flujo son otros actos clínicos. La interfaz del módulo suma el
  selector `ProcedureCatalogPicker` para el procedimiento y muestra las dos
  fechas nuevas en formulario y detalle. §4 sincronizado con el valor medido:
  117 migraciones. Sin rutas, modelos, controllers ni primitivas UI nuevas:
  `scripts/audit/doc-drift.mjs` sigue en verde. Tests:
  `SpecialtyRecordProcedurePersistenceTest` (5),
  `SpecialtyRecordSurgeryDateTest` (5), `OralSurgeryDateBackfillTest` (1),
  `SpecialtyRecordTryInDateTest` (4), `SpecialtyRecordShowContractTest` (2) y
  los unitarios/smoke JS del módulo. En la misma pasada se cierran dos defectos
  vecinos: `GET /specialty-records/{id}` respondía 500 en ortodoncia porque
  `show()` precargaba `dentalPiece` en los cinco modelos y esa tabla no tiene la
  columna (ahora sólo carga la relación donde el modelo la define), y el detalle
  leía `dentalPiece` mientras Eloquent serializa `dental_piece`, así que la
  pieza no aparecía nunca.

- **2026-10-21 (deuda de §6 y troubleshooting de §8, segunda pasada)** — La
  auditoría anterior corrigió lo que el guard podía medir y dejó sin
  re-verificar la lista `⚠️ Pendiente` ni las filas de §8. Medido ahora:
  `StoreQuotationRequest` ya no bloquea el path `generateQuotation` —declara
  `patient_id` como `sometimes|nullable` y `QuotationController@store` deriva el
  paciente desde `treatment_plan_id`, el fix C-1 del Sprint 0—, así que la lista
  baja de 3 a 2 FormRequests no migrables. Las referencias a ADR-0007/0008 en §6
  y §8 dejan de sugerir cuerpos escritos: ahora apuntan a §9 y a #264. Los
  anclajes por número de línea (`User.php:125`, `ProcedureCatalog.php:24`) pasan
  a nombrar el símbolo, porque el número envejecía con cada refactor. Verificado
  sin cambios: los tres guards `SqliteMigrateFreshDeclarationTest`,
  `MysqlRunnerGateTest` y `StubsRemovedEndpointsTest` existen, los dos accessors
  `specialty_code` están, `User` no castea `specialty` a JSON, el alias `@` de
  `vite.config.js` apunta a `/resources/js`, `.env.example` trae
  `MAIL_MAILER=log` y `BROADCAST_CONNECTION=reverb`, `phpunit.xml` trae
  `APP_KEY` y la ruta `appointments/{appointment}/generate-quotation` sigue
  publicada.

- **2026-10-21 (auditoría de deriva de AGENTS.md)** — El documento describía un
  stack y unos conteos que el código ya no tenía: `34 eventos` (son 36) y
  `23 composables` (son 39); una fila `maatwebsite/excel 1.1` en §2 cuando el
  paquete salió de `composer.json` (los exports reales son CSV con `fputcsv`,
  SpreadsheetML `.xls` y PDF con DomPDF); los ADR en `docs/decisions/` cuando el
  índice vive en `docs/architecture/decisions/` y los cuerpos 0007/0008 todavía
  no existen; y en §6/§8 el camino MySQL
  `php artisan test --configuration=phpunit.mysql.xml`, que no funciona: con la
  ruta relativa artisan termina ejecutando `phpunit.xml` en vez del archivo
  indicado, PHPUnit avisa `Option --configuration cannot be used more than once`
  y el comando sale con código 1 aunque todo pase, así que la invocación
  documentada pasa a `vendor/bin/phpunit -c phpunit.mysql.xml` con el destino
  MySQL fijado por entorno. §11 deja de citar un tamaño de suite volátil y §6
  deja de decir que los tests con BD fallan en SQLite (desde #170 se saltan, con
  el runner MySQL como veredicto), mientras §8 cuenta 15 seeders y no 14. El
  guard `scripts/audit/doc-drift.mjs` pasa de 12 a 14 chequeos (eventos y
  composables) y el de seeders ya lee también la forma posfija `activos: N` de
  §8, no solo la de §4. Los cuerpos e índice de los ADR quedan en #264, la
  duplicación muerta de `ClinicalAttachmentService` en #265, el
  `MedicalRecord::attachments()` sin filtro `is_active` en #266 y las cuatro
  dependencias de frontend sin consumidores en #267. Sin rutas, migraciones,
  modelos ni primitivas UI nuevas: los conteos de §4/§5/§6 que siguen siendo
  ciertos no se tocan.

- **2026-10-21 (adjuntos clínicos — #244)** —
  `DELETE /api/medical-records/attachments/{attachment}` delega en
  `MedicalRecordService::deleteAttachment()` en vez de duplicar media lógica:
  borra el binario del disco `public` y conserva la fila como marcador de
  archivo (`is_active = false`), que es donde vive el rastro auditable — nombre
  original, tipo, tamaño, categoría, descripción, quién y cuándo. Antes el
  endpoint solo marcaba la fila, así que cada adjunto eliminado desde
  `/medical-records` dejaba su archivo huérfano en
  `storage/app/public/clinical-attachments/`: disco que crece sin límite y un
  binario clínico todavía servido por `/storage/...` sin forma de alcanzarlo
  desde la aplicación. Repetir el borrado es un no-op (204) y un id inexistente
  sigue respondiendo 404 con el mismo envelope. Consecuencia asumida del
  contrato: un análisis de IA ya guardado sobre ese adjunto pierde su miniatura,
  porque `AiImageAnalysisResource` construye la URL desde
  `clinical_attachment.file_path`; el hallazgo del análisis se conserva.
  `ClinicalAttachmentService::delete()` repite esa lógica y sigue sin llamadas
  (hallazgo fuera de alcance de #244). Sin rutas, migraciones, modelos ni
  primitivas UI nuevas: los conteos de §4 no cambian. Tests:
  `MedicalRecordAttachmentTest` (7, MySQL) cubre borrado con
  `Storage::fake('public')` y `assertMissing` del `file_path`, segundo borrado
  y 404.

- **2026-10-20 (metadata de PHPUnit en atributos, #237)** — `tests/` deja de
  declarar metadata en doc-comments: `@test` → `#[Test]` (354 métodos),
  `@dataProvider foo` → `#[DataProvider('foo')]` (76 métodos) y `@group mysql` →
  `#[Group('mysql')]` (5 clases). Sin cambios de lógica, providers ni cobertura:
  `main` reportaba `Tests: 2053, Assertions: 10155, PHPUnit Deprecations: 570` y
  la rama termina `OK (2054 tests, 10156 assertions)` sin deprecaciones (el test
  extra es el guard nuevo). `PhpunitAttributesOnlyTest` falla con archivo y
  línea cuando una anotación vuelve, porque PHPUnit 12 ignora la metadata de
  doc-comments y PHPUnit 11 ya descarta el provider de un método que lleva
  cualquier atributo, sin error. `vendor/bin/pint --test` y `prettier --check .`
  en verde. Sin rutas, modelos, migraciones ni primitivas UI nuevas: los conteos
  de §4 no cambian.
- **2026-10-20 (caja: anulación, movimientos manuales, cobro ad-hoc y reportes —
  #192/#195/#199/#197)** — Cuatro decisiones de contrato del módulo de caja.
  **Anulación**: `POST /api/transactions/{transaction}/void` vuelve a
  `role:administrador` y `TransactionService::voidTransaction()` rechaza con 403
  a cualquier otro rol; finanzas y recepción conservan apertura, cobros y
  movimientos (#192). **Movimientos manuales**: `adjustment` sale de las
  whitelists de tipo y concepto en `StoreCashMovementRequest` y del formulario,
  porque el ledger no puede firmarlo; la fila de cierre la sigue escribiendo
  `CashRegisterService::closeSession()` directamente al modelo (#195). **Cobro
  ad-hoc**: `PaymentModal` usa `PatientSelector` cuando no viene de una fila de
  cobros pendientes, así que una venta de producto o un anticipo eligen
  cualquier paciente (#199). **Reportes**: el reporte de período declara su
  población (`population.basis = transaction_date`), el bloque de sesiones son
  las sesiones con actividad en el rango, `daily_summary` suma
  `total_income`/`total_expenses` por día y las cuatro agregaciones
  (`summary_by_payment_method`, `summary_by_hour`, `top_transactions`,
  `daily_summary`) pasan a una consulta por rango en vez de una por sesión
  (#197). Sin rutas, migraciones, modelos ni primitivas UI nuevas: los conteos
  de §4 no cambian. Tests: `TransactionVoidRoleTest`,
  `CashMovementManualWhitelistTest`, `CashReportPopulationTest`,
  `CashRegisterValidationTest` actualizado, los 12 casos de
  `caja-cobros.test.js` y el guard de opciones de `caja-movimientos.test.js`.
- **2026-10-20 (follow-ups de UI #239/#242/#243)** — `AiAnalysisButton` se
  retira con su smoke y su contrato de design system: el flujo de análisis se
  inicia solo desde `/ai-analysis` y el componente llevaba sin consumidor desde
  el rediseño de historias clínicas (#239). `TreatmentPlanDetail` pasa a
  `UiModal`: deja de declarar overlay, backdrop, `role="dialog"` y listener de
  Escape propios, conserva el hook `data-plan-detail` (ahora en el backdrop
  teleportado), la cabecera, las acciones y el `aria-labelledby`, y entra en la
  pila compartida — un formulario abierto encima cierra solo y el bloqueo de
  scroll se coordina (#242). En `/appointment-types/:id`,
  `/procedure-catalog/:id` y `/professionals/:id` el topbar deja de renderizar
  su `<h1>` oculto porque la página de detalle nombra la entidad en su propio
  `<h1>`: cada documento queda con un solo encabezado, y visible (#243). Sin
  rutas, modelos, migraciones ni primitivas UI nuevas: los conteos de §4 no
  cambian. Tests: smoke del detalle reescrito contra la primitiva (6), guard
  `test_plan_detail_rides_the_modal_primitive`, `page-header-hierarchy.test.js`
  con las tres rutas de detalle (6) y `topbar-title-ownership.test.js` (28).
  Guard `scripts/audit/doc-drift.mjs` en verde.

- **2026-10-21 (especialidades — #210/#211/#212/#213)** — Cuatro issues del
  dominio cerrados. Navegación: la entrada clínica de `/specialty-records` pasa
  a «Registros de Especialidad» y el catálogo conserva «Catálogo de
  Especialidades»; sin cambios de rutas ni permisos, con guard
  `SpecialtiesNavigationDisambiguationTest` (#210). Datos: nuevo
  `UserSpecialtySeeder` que puebla `user_specialties` desde el `users.specialty`
  legacy con mapa explícito (`general`→`general`, `orthodontics`→`ortodoncia`,
  `implantology`→`implantologia`) solo para roles clínicos; tras
  `migrate --seed` el catálogo muestra profesionales > 0 y
  `UserSpecialtySeederTest` fija el invariante (#211). Auditoría:
  `SpecialtyController@store/update` escriben
  `specialty_created`/`specialty_updated` con actor y snapshot auditable
  (`code`, `name`, `description`, `is_active`), cubierto por
  `SpecialtyAuditLogTest` (#213). Rediseño: `/specialty-records` pasa al
  lenguaje del dashboard (hero band, KPI strip de la especialidad activa,
  selector de paciente, `UiTabs` con badges de conteo, estados completos y
  `UiModal` para alta/edición y detalle) y por fin renderiza el payload real de
  `GET /specialty-records/patient/{id}/all`: antes la página cargaba
  `allRecords` y pintaba `records`, siempre vacío; además esa ruta estaba
  sombreada por `/patient/{patientId}/{specialty}` y respondía una lista vacía,
  y el listado de cirugía oral ordenaba por `surgery_date`, una columna
  inexistente que producía 500. Se eliminan las claves inglesas y los campos
  inventados (`title`, `description`, `procedure_date`, `professional_name`,
  `observations`, `next_appointment`). El contrato de escritura se alinea con el
  esquema real: `rehabilitacion` exige `prosthesis_type` (no
  `restoration_type`), `cirugia_oral` exige `surgery_site` (no `surgery_date`,
  columna inexistente), los enums de estado son los de la BD, `dental_piece_id`
  es obligatorio donde la tabla lo exige y el nuevo `GET /api/dental-pieces`
  alimenta el selector de pieza. `UpdateSpecialtyRecordRequest` reutiliza las
  reglas del alta para que la edición persista todo lo que muestra. En la misma
  pasada, `/stats` cuenta cada especialidad por el campo de estado que realmente
  tiene, los 500 del controlador dejan de filtrar el mensaje de la excepción y
  `notes` sale del contrato porque no existe en ninguna de las cinco tablas. §4
  sincronizado con valores medidos: 33 controllers API, 198 rutas API, 211
  totales con web, 15 seeders activos; sin migraciones nuevas (114). Tests:
  `SpecialtyAuditLogTest`, `UserSpecialtySeederTest`,
  `SpecialtyRecordStoreContractTest`, `SpecialtyRecordUpdateContractTest`,
  `SpecialtyRecordReadContractTest`, `DentalPiecesEndpointTest`,
  `SpecialtyRecordsAppShellTest`, `SpecialtyRecordsRedesignContractTest`,
  `SpecialtiesNavigationDisambiguationTest`, unit JS de `specialtyRecordMeta` y
  smoke del módulo.

- **2026-10-20 (seguimientos de historias clínicas #222/#223/#225/#228/#224)** —
  La superficie de evoluciones cierra cuatro huecos del rediseño y gana
  cobertura de flujos. `clinical_measurements` pasa a tener un contrato plano
  (`pocket_depth_mm`, `gingival_recession_mm`, `plaque_index_percent`,
  `bleeding_on_probing`, `tooth_mobility`), validado en `StoreEvolutionRequest`
  y capturado/mostrado en el modal y el timeline. Editar y borrar una evolución
  emiten `ClinicalEvolutionUpdated` / `ClinicalEvolutionDeleted` (canal
  `medical-records`; el payload de borrado lleva `medical_record_id` y
  `patient_id`) y el cliente refresca sólo al paciente seleccionado. La
  especialidad de una evolución se valida contra el catálogo `specialties` y se
  corrige desde un selector; el timeline muestra el nombre con fallback al
  `code`. El workspace carga todas las historias del paciente paginando el
  índice y ofrece selector de historia cuando hay más de una: Evoluciones la
  sigue, Adjuntos y Estadísticas siguen al paciente y lo declaran. Nuevo
  `tests/js/smoke/medical-records-flows.test.js` (alta de historia con 422 por
  campo, evolución con seguimiento, borrado confirmado y adjunto con límite de
  10 MB) sobre `pnpm test:js`, que ya corre en el job `quality`. Sin rutas,
  migraciones, modelos ni primitivas UI nuevas: los conteos de §4 no cambian y
  `scripts/audit/doc-drift.mjs` sigue en verde.
- **2026-10-20 (cierre de hallazgos de #177/#208)** — El atajo `n` de la página
  de planes queda inerte con un overlay abierto (reinicializaba el formulario en
  edición y perdía los cambios), `UiModal` arma la trampa de foco también cuando
  se abre por `v-model` (antes el `activate` corría con la ref todavía vacía) y
  `/professionals/:id` entra en las rutas cuyo título visible pertenece a la
  página. Los hallazgos fuera de alcance quedaron registrados como issues #242
  (`TreatmentPlanDetail` a `UiModal`) y #243 (dos `<h1>` en rutas de detalle).
  Tests: `modalPrimitiveGuards` (14), `treatment-plans` (9) y
  `topbar-title-ownership` (27). Sin rutas, modelos ni migraciones nuevas.

- **2026-10-20 (corrección de #177 tras verificación independiente)** — El botón
  de envío de los dos diálogos migrados vuelve a ser `type="submit"` con
  `form="…"`, así Enter sigue enviando desde cualquier campo; `DataTable` remide
  el overflow cuando cambia el conjunto de columnas, no solo los datos. Tests:
  asociación del submit (5 en `treatment-plan-modal`, 4 en
  `create-patient-inline`), remedición por columnas (6) y guardias de la
  primitiva (13).

- **2026-10-20 (UiModal: hooks de raíz y cierre cancelable)** — `Modal.vue`
  reenvía `$attrs` al nodo raíz teleportado (`inheritAttrs: false` +
  `v-bind="$attrs"`), acepta `beforeClose` (al devolver `false` veta el cierre y
  solo corre una vez mientras está pendiente) y `labelledby` para que un
  consumidor con header propio conserve `aria-labelledby`. Los modales anidados
  comparten una pila: solo el superior responde a Escape y al backdrop, el
  bloqueo de scroll usa un contador y `useFocusTrap` deja que solo la trampa
  superior mueva el foco. `TreatmentPlanModal` y `CreatePatientInline` dejan de
  duplicar overlay, backdrop y foco, y `TreatmentPlansPage` deja de cerrar el
  modal con su atajo de Escape para no saltarse la guardia de cambios. Sin
  rutas, modelos, migraciones ni primitivas UI nuevas: los conteos de §4 no
  cambian. Tests: `modalPrimitiveGuards.test.js` (10),
  `treatment-plan-modal.test.js` (4), `create-patient-inline.test.js` (3) y el
  guard de Escape de página en `treatment-plans.test.js`.

- **2026-10-20 (topbar sin título duplicado)** — En las rutas cuya página ya
  renderiza su propio título (hero band de las superficies rediseñadas o
  `PageHeader`), el topbar de `AppLayout` deja de pintar el título visible:
  conserva su `<h1>` como `sr-only` y publica la descripción corta como contexto
  (`data-topbar-title-owner` y `data-topbar-context`). Las rutas sin título
  propio (calendario, pacientes, dashboard, historias) siguen mostrando el
  título del topbar sin cambios. Sin rutas, navegación funcional, modelos,
  migraciones ni primitivas UI nuevas: los conteos de §4 no cambian. Tests:
  `topbar-title-ownership.test.js` (26).

- **2026-10-20 (UiDataTable: affordance de scroll horizontal)** —
  `DataTable.vue` mide el overflow de su viewport interno y, cuando la tabla
  excede el ancho, pinta un degradado en el borde oculto y el aviso «Desliza
  para ver más columnas» (`data-table-scroll`, `data-table-scroll-edge`,
  `data-table-scroll-affordance`); al llegar al final el aviso desaparece y el
  degradado cambia de lado. El scroll sigue siendo interno al primitivo (un
  único `overflow-x-auto`), así que ninguna página gana overflow de documento.
  Sin rutas, modelos, migraciones ni primitivas UI nuevas: los conteos de §4 no
  cambian. Tests: `data-table-scroll-affordance.test.js` (5).
- **2026-10-20 (deuda del design system: #218/#219/#221/#205)** — El plugin de
  Tailwind registra las utilidades token que los módulos ya hablaban
  (`text-label`, `border-hairline`, `bg-canvas`, `bg-systemBackground`,
  `text-systemBackground`, `border-separator`, `divide-hairline`, `ring-accent`,
  `text-accent-hover`); un guard estático en
  `tests/js/unit/design-token-utilities.test.js` falla si aparece una clase
  token que Tailwind no puede generar. `UiEmptyState.icon` acepta componentes
  funcionales de heroicons. `AiAnalysisButton` pasa a `UiButton`/`UiStatusPill`
  sin `<style scoped>` ni alias legacy, y el guard de app-shell suma el grupo
  `embeddedFiles` para componentes embebidos. `useAiAnalysis` deja de tostar las
  lecturas y la subida (el Sheet pinta el error en línea) y conserva el toast
  solo en las acciones sin superficie de error propia. Sin rutas, modelos,
  migraciones ni primitivas UI nuevas: los conteos de §4 no cambian.

- **2026-10-20 (caja: rutas honestas, sesión actual y fugas de los 500)** —
  Cierra los cuatro defectos vecinos detectados al entregar
  #191/#193/#194/#196/#198. Se retira `POST /cash-register-sessions/{id}/close`
  (ignoraba su `{id}`, gemelo del `{id}/open` que quitó #194) y
  `apiResource('cash-register-sessions')` publica sólo `index` y `show`:
  `store`, `update` y `destroy` no existen en el controlador y respondían 500
  por método inexistente (ahora 405). `CashRegisterService::getCurrentSession()`
  acota por usuario salvo `administrador` —regla de `CashRegisterPolicy::view`—
  porque el guard anterior usaba `userCanManageCashRegister()`, que admite los
  tres roles de caja y nunca acotaba nada; `GET /cash-register/current` ya no
  devuelve la caja de otro usuario. El mensaje de `OpenCashRegisterRequest`
  nombra la regla real (usuario y sucursal). Los cinco `catch` de
  `CashRegisterController` (`current`, `index`, `show`, `movements`, `summary`)
  dejan de devolver el mensaje de la excepción: mensaje genérico y `Log::error`,
  con `ModelNotFoundException` reenviada para conservar el 404. §4 sincronizado
  con valores medidos: 197 rutas API, 210 totales con web. Tests nuevos:
  `CashRegisterPhantomRoutesTest` (4), `CashRegisterCurrentSessionScopeTest` (4)
  y `CashRegisterErrorEnvelopeTest` (5). Guard `scripts/audit/doc-drift.mjs` en
  verde.

- **2026-10-20 (caja: dureza del backend y cobro en recepción)** — Cierra las
  issues #191, #193, #194, #196 y #198 en dos slices: `fix/caja-hardening`
  (contrato y dureza) y `fix/caja-hardening-rbac` (#194, #191).
  `CashRegisterController@show`, `@movements` y `@closureReport` aplican ahora
  `CashRegisterPolicy::view` (dueño o administrador), regla que era código
  muerto porque el auto-descubrimiento busca `CashRegisterSessionPolicy`;
  `AppServiceProvider` la registra explícitamente y un id inexistente responde
  404 en vez de 500. `GET /api/pending-payments` pagina con `per_page` (1..100,
  default 15) y devuelve `current_page`, `last_page`, `per_page` y `total`; sus
  500 dejan de exponer el mensaje de la excepción y el trace y pasan al log.
  `GET /api/cash-register/summary` se acota al usuario autenticado salvo para
  administrador/finanzas (que pueden filtrar por `user_id`) y hereda el filtro
  de sucursal de `CashRegisterService::getCurrentSession()`; se retira
  `POST /cash-register-sessions/{id}/open` (ignoraba `{id}`) y el método muerto
  `currentView()`. Los pagos pendientes pasan al grupo de caja: `recepcionista`
  cobra desde la tab Cobros (permiso `collectPendingPayments`), documentado en
  la matriz de `CREDENTIALS.md`. §4 sincronizado con valores medidos: 201 rutas
  API, 214 totales con web (la ruta `{id}/open` se retiró). Tests nuevos:
  `CashSessionOwnershipTest` (8), `PendingPaymentsErrorEnvelopeTest` (2),
  `PendingPaymentsPaginationTest` (5), `CashRegisterContractWartsTest` (5) y
  `PendingPaymentsRoleAccessTest` (4). Guard `scripts/audit/doc-drift.mjs` en
  verde.
- **2026-10-20 (endurecimiento del contrato de presupuestos, #200-#204)** — Los
  tres eventos de presupuesto y el nuevo `QuotationRejected` viajan solo por
  `PrivateChannel('quotations')`: `BroadcastingAuthController` autoriza
  exactamente a administrador, finanzas, odontologo, implantologo y
  recepcionista, y esos eventos dejan de publicarse por el canal público
  `dashboard-updates` porque su payload lleva datos del paciente y montos (el
  resto de eventos de ese canal queda para #183). `QuotationService` respeta la
  máquina de estados: aprobar y rechazar solo desde `sent`/`viewed` (422 con
  mensaje explícito), una sola fila de aprobación vigente, la descarga del PDF
  escribe `viewed` y el nuevo comando diario `quotations:expire` escribe
  `expired`; `index?expired=` y el resumen leen el estado en vez de derivarlo de
  `valid_until`, mientras el badge de vigencia del front sigue avisando por
  fecha. El rechazo emite `quotation.rejected` (no `QuotationUpdated`, para no
  duplicar la notificación) y `NotificationService` lo mapea. El detalle de
  paciente abre el presupuesto con `/quotations?open=<id>`, usa el vocabulario
  de estado compartido y descarga con `useApi().download`. `store` y `update`
  comparten el saneo de texto libre (`App\Support\QuotationText`: sin HTML,
  topes 2000/1000) y el motivo de rechazo ya no desborda `notes`. Sin rutas,
  migraciones, modelos ni primitivas UI nuevas: solo el conteo de eventos sube
  a 34. Tests: `QuotationTextTest`, `QuotationTextHygieneTest`,
  `QuotationStatusMachineTest`, `QuotationViewedOnPdfTest`,
  `ExpireQuotationsCommandTest`, `QuotationBroadcastChannelsTest`,
  `QuotationRejectedEventTest`, `BroadcastingAuthChannelsTest` extendido y los
  smokes JS del canal privado, del deep link y de las acciones del paciente.
  Follow-up de #196/#236: los nueve handlers de `QuotationController` registran
  la excepción y responden un mensaje genérico (ya no exponen `error`), y un id
  inexistente responde 404 en vez de 500 (`QuotationErrorEnvelopeTest`). Guard
  `scripts/audit/doc-drift.mjs` en verde.
- **2026-10-20 (canal privado del dashboard #183)** — `dashboard-updates` deja
  de ser un canal público de Reverb: los 18 eventos que aún publicaban en él
  usan `PrivateChannel`, `POST /api/broadcasting/auth` autoriza el canal solo
  para los 7 roles con acceso al dashboard (y rechaza roles nuevos o anónimos),
  y el consumidor del panel se suscribe con `privateChannel` y libera
  `private-dashboard-updates`. Caja conserva sus canales (`cash-register`,
  `cash-session.*`). Los presupuestos ya viajaban por
  `PrivateChannel('quotations')` (#200-#204). `sdd:check-events` sigue en 0
  huérfanos. Sin rutas, migraciones ni conteos nuevos.

- **2026-10-20 (historias clínicas)** — Interfaz de `/medical-records`
  rediseñada al lenguaje del dashboard y reparada sobre el contrato real: hero
  band con «Historias recientes» y deep links (`?patient_id=`,
  `/medical-records/{id}`), workspace del paciente con KPI strip, `UiTabs` y las
  cuatro superficies (historia general, evoluciones SOAP, adjuntos,
  estadísticas) reconstruidas sobre los campos que el backend valida. §4
  sincronizado: 202 rutas API (añadidas `PUT` y
  `DELETE /medical-records/evolutions/{evolution}`), 215 totales con web. Sin
  migraciones, modelos ni primitivas UI nuevas; `MedicalRecord::attachments()`
  declara el `HasManyThrough` que ya devolvía (el desajuste producía un 500 en
  `/stats`). Guard `scripts/audit/doc-drift.mjs` en verde.

- **2026-10-20 (rediseño de la interfaz de Análisis IA)** — `/ai-analysis` pasa
  al lenguaje de superficie del dashboard: hero band con el título en `<h2>`,
  KPI strip no interactiva, filtros y paginación servidos por la API, tabla
  sobre `UiDataTable` + `Pagination`, estados de carga/error con retry y vacío,
  y motion con `prefers-reduced-motion`. El alta de análisis pasa a un `UiSheet`
  con validación local (20 MB, solo imágenes) y el detalle/revisión se
  reescribió con gate de permisos y confirmación de borrado. Backend:
  `GET /api/ai-analysis` acepta `per_page` (1..100) y `search` por nombre de
  paciente, ignora filtros vacíos, y `DELETE` de un id inexistente responde 404
  (antes 500); las 500 del `AiImageAnalysisController` ya no exponen el mensaje
  de la excepción. Corrige además el gate de `AiAnalysisButton.vue`, que llamaba
  `can(...)` sobre el mapa de permisos. Sin rutas, migraciones ni primitivas UI
  nuevas: los conteos de §4 no cambian y `scripts/audit/doc-drift.mjs` sigue en
  verde.

- **2026-10-20 (especialidades — catálogo maestro)** — Nueva interfaz admin en
  `/specialties` para el maestro de especialidades, con el lenguaje del
  dashboard (hero band, KPI strip, búsqueda y filtro de estado, `UiDataTable`,
  estados de carga/error/vacío y modal de alta/edición). El backend suma de
  forma aditiva `q`, `is_active`, `procedures_count`, `professionals_count` y
  `meta.kpis` (población sin filtros) a `GET /api/specialties`, y conteos a
  `GET /api/specialties/{id}`; sin rutas, migraciones, modelos ni controllers
  nuevos. El `code` es inmutable en `PUT` (se descarta del payload, igual que en
  métodos de pago). Las especialidades no se eliminan: se desactivan, porque
  `procedure_catalog.specialty_id` y `user_specialties` las referencian. La
  entrada clínica "Especialidades" (`/specialty-records`) no cambia; la nueva
  entrada del sidebar se llama "Catálogo de Especialidades". §4 y §5
  sincronizados: 18 módulos de dominio y 20 páginas; la feature no agrega rutas
  API.
- **2026-10-20 (presupuestos interface redesign)** — `/quotations` pasa a la
  superficie del dashboard: hero band, KPI strip de cuatro tarjetas alimentado
  por `GET /api/quotations/summary`, búsqueda `q` server-side (número, paciente,
  documento) más estado (los 6 del enum) y rango de fechas, lista sobre
  `UiDataTable` con badges de estado y vencimiento, y detalle / creación /
  edición / aprobación / rechazo sobre `UiModal`, con estados skeleton, error y
  vacío. Corrige el contrato del backend: `treatment_plan_id` pasa a nullable
  (presupuesto manual), `approve` escribe las columnas reales de
  `quotation_approvals` (`patient_id`, `approval_status`, `approval_date`,
  `patient_notes`, `signature_data`) en vez de columnas inexistentes, y la
  descarga del PDF deja de pedir un blob a `useApi.get`. Se retira
  `QuotationCard.vue` (la tabla lo reemplaza) y se conserva
  `QuotationStatusBadge.vue` para el detalle de planes de tratamiento. §4
  sincronizado con valores medidos: 200 rutas API (nueva
  `GET quotations/summary`) y 213 totales con web; 114 migraciones. Tests:
  `QuotationIndexSummaryTest` (7, MySQL), `QuotationsRedesignContractTest` (10),
  `quotationMeta.test.js` (8), `quotations-list.test.js` (4) y
  `quotations-form.test.js` (2). Guard `scripts/audit/doc-drift.mjs` en verde.
- **2026-10-20 (caja interface redesign)** — `/cash-register` pasa al lenguaje
  del dashboard: hero band con estado de sesión, KPI strip permanente (Apertura
  / Cobros / Egresos / Saldo esperado) y las cinco tabs sobre `UiDataTable`,
  `UiFilterBar` y `UiPagination`, con estados de carga, error con retry y vacío.
  `/cash-register/ready-to-bill` entra en `AppLayout` y usa las mismas
  primitivas. Se arreglan los controles que no funcionaban: los cuatro modales
  de sesión/transacción/movimiento pasan a `v-model`, "Nuevo movimiento" envía
  el `concept` obligatorio, el Historial pagina y filtra contra el API, Cobros
  paga con `pending-payments/{id}/pay` (actualiza el saldo de la cita) y
  Reportes consume su propio payload de período. La aritmética de caja deja de
  mezclar poblaciones: el ledger de movimientos es la fuente única de flujos
  (`cash_in`, `cash_out`, `expected_amount`), las filas espejo de apertura y
  cierre quedan fuera de la ecuación y el reporte de período suma
  `total_income`/`total_expenses` y calcula el porcentaje de cada método sobre
  el total del mix. Sin rutas, controladores, modelos ni migraciones nuevas: los
  conteos de §4 no cambian y `scripts/audit/doc-drift.mjs` sigue en verde.
- **2026-10-20 (Business Intelligence: superficie + contrato de reportes v2)** —
  `/business-intelligence` se rediseña sobre la receta del dashboard: hero band
  con período aplicado, KPI strip, filtros compartidos y presets, selector de
  corte de ingresos, `UiDataTable`, estados de carga/error/vacío y gráficos con
  tokens y destrucción controlada. La página deja de consumir el canal público
  `dashboard-updates` y refresca bajo demanda. Los servicios de reporte se
  corrigen: una sola definición de ingresos (transacciones cobradas, no precio
  de catálogo), atribución única por `appointment_id`, período inclusivo en
  ambos extremos, disponibilidad por sillas activas y días hábiles reales,
  contadores reales de canceladas/no-show, `meta.summary` en el reporte de
  ingresos, export Excel/CSV/PDF honesto y errores 500 sin filtrar la excepción.
  Sin rutas, modelos, migraciones ni primitivas UI nuevas: los conteos de §4 no
  cambian. Follow-ups abiertos: canal privado por rol, devoluciones no neteadas
  y retiro de la dependencia `maatwebsite/excel` (en desuso).
- **2026-10-20 (treatment-plans-ui-redesign)** — Planes de tratamiento pasa al
  lenguaje del dashboard en sus cuatro superficies (lista y kanban, modal de
  alta/edición, detalle del plan y tarjetas/columnas/badge): hero band, tira de
  cuatro KPIs, tarjeta de filtros con presets y estados de carga, error con
  reintento y vacío compartido. Añade `GET /api/treatment-plans/summary` para
  que los KPIs resuman la tabla completa y no la página visible, más los filtros
  de índice `statuses` (lista) y `overdue`, que hacen funcionar los presets
  Activos/Vencidos (antes enviaban `status=active|overdue` y nunca filtraban).
  §4 sincronizado: 199 rutas API, 212 totales con web. Sin migraciones ni
  primitivas UI nuevas. Tests: `TreatmentPlansSummaryTest`,
  `TreatmentPlansIndexFiltersTest`, `TreatmentPlansAppShellTest` y smokes JS del
  módulo. Guard `scripts/audit/doc-drift.mjs` en verde.
- **2026-10-20 (follow-ups #169/#170/#171 de citas)** — La ventana de lista de
  espera se compara por fecha completa (`whereDate` en ambos bordes): un
  `preferred_end_date` a medianoche cubre su día y el día de inicio cuenta
  entero, sin migraciones (#169). Los tests que poseen o unen el esquema
  (`RefreshDatabase`, `DatabaseMigrations`, `DatabaseTruncation`,
  `DatabaseTransactions`) se saltan explícitamente con driver no-MySQL, vía
  `Tests\TestCase::setUpTraits()` y `Tests\Support\MysqlRunner` (#170).
  `AppointmentTimezonePersistenceTest` completa su matriz con el caso
  naive-local de reprogramación (#171). Sin rutas, modelos, migraciones ni
  primitivas UI nuevas: los conteos de §4 no cambian.

- **2026-10-19 (recordatorios #154)** — `ReminderService::scheduleReminders()`
  deja de ser código muerto: `AppointmentService` agenda los recordatorios de
  confirmación (24h/48h/72h) dentro de la transacción de crear, actualizar y
  reprogramar, y `PATCH /api/appointments/{appointment}/status` cancela los
  pendientes al cancelar la cita. Nuevo comando horario `reminders:reconcile`
  (`app/Console/Commands/ReconcileReminders.php`; sin rutas HTTP nuevas) que
  repara huecos y cubre el backfill de citas previas sin duplicar ni resucitar
  recordatorios. Corrige de paso la ventana 48h/72h, inerte con Carbon 3 porque
  `diffInHours(now())` es negativo para citas futuras. Sin migraciones, sin
  cambios de UI ni de entrega de correo; los conteos de §4 no cambian.

- **2026-10-20 (cierre de la deuda de formato)** — Pint y Prettier pasan a
  bloqueantes en el job `quality`: se formateó el código propio (369 archivos
  PHP con Pint y 92 de frontend, scripts y documentación viva con Prettier).
  `.prettierignore` excluye con motivo escrito lo que no es código propio
  (`.agents/`, `openspec/`, `odd/`, `docs/mejoras/`) y lo que otra herramienta
  reescribe (`pnpm-lock.yaml`, `composer.json`,
  `resources/css/tokens.generated.css`). Cierra el follow-up de #15; el issue no
  se reabre. Los conteos de §4 no cambian: no hay rutas, modelos, migraciones ni
  primitivas UI nuevas.

- **2026-10-19 (procedure-stats-ui-redesign T1-T4)** — `/procedure-stats` pasa a
  un rediseño completo que reemplaza el pase anterior de esa misma página
  (procedure-catalog-ui-redesign): hero band con período aplicado, KPI strip de
  cuatro tarjetas, filtros con presets 7/30/90 días y rango validado, Top N
  acotado, ranking sobre `UiDataTable`, distribución por especialidad con barras
  proporcionales, estados completos (carga/error con retry/vacío de período) y
  export CSV del período aplicado. Suma gráficos Chart.js (barras del top por
  usos y doughnut por especialidad). Corrige las métricas del reporte: el
  período incluye el día `to` completo, los ítems opcionales y los planes
  cancelados dejan de contar, ranking y distribución comparten una sola
  población, y el 500 deja de devolver el mensaje de la excepción. Sin ruta,
  primitiva UI ni migración nuevas: los conteos de §4 no cambian por esta
  feature y `scripts/audit/doc-drift.mjs` sigue en verde.

- **2026-10-19 (appointment-types audit)** — §4 sincronizado con el valor
  medido: 113 migraciones (nueva migración que fija `requires_confirmation` por
  defecto en `appointment_types`). Guard `scripts/audit/doc-drift.mjs` en verde.

- **2026-10-19 (procedure-catalog-ui-redesign)** — Interfaz del catálogo de
  procedimientos rediseñada al lenguaje del dashboard en sus cuatro superficies
  (`/procedure-catalog` lista y detalle, `/procedure-stats`, `/my-procedures`,
  `/reception-procedures`). §4 sincronizado: 198 rutas API (añadidas
  `GET procedure-catalog/summary` y `GET procedure-catalog/{id}/versions`), 211
  totales con web. Endpoints aditivos; sin migraciones. Tests:
  `ProcedureCatalogSummaryVersionsTest` (6 tests, MySQL) y smoke JS del módulo.
  Guard `scripts/audit/doc-drift.mjs` en verde.

- **2026-10-11 (calendar-scheduling-redesign T8)** — §4 sincronizado con valores
  medidos: 196 rutas API (añadida
  `PATCH /api/appointments/{appointment}/reschedule`), 209 totales con web;
  `components/ui/` de 30 a 35 primitives (incluye `DentalMotifLayer.vue` movido
  desde `modules/dashboard/` y `UiSectionEmpty.vue` de patients; la base ya
  tenía 33, deriva preexistente corregida). Guard `scripts/audit/doc-drift.mjs`
  en verde.

- **2026-10-05 (dashboard-ops-redesign T7a)** — §4 sincronizado con valores
  medidos: 195 rutas API (añadida `GET dashboard/pending`), 208 totales con web.
  Guard `scripts/audit/doc-drift.mjs` en verde.

- **2026-09-18 (Fix #21)** — Deriva de docs corregida con valores medidos: §4
  (32 controllers API, 50 modelos, 111 migraciones, 194 rutas API, 10 listener
  classes), §6 (0 eventos `@deprecated` huérfanos; reminders con CRUD real;
  `WaitingListController` eliminado en slice 04), §11 sincronizado. Nuevo guard
  `scripts/audit/doc-drift.mjs` (falla con el diff ante cualquier deriva).
  Corrige el apunte de slice 11: eran 14 seeders activos, no 13. Tests:
  `tests/Unit/Documentation/AgentsDocsSyncTest.php` valida 5 invariantes en CI.
- **2026-08-05 (Slice 11)** — Actualizado tras aplicar slice 11 de
  `bugfix-2026-08`. Datos: §4 lista ahora 14 seeders activos (antes 11 —
  añadidos `BranchSeeder` + `PaymentMethodSeeder` más `DentalPieceSeeder`), §4
  listeners corregido a "10 listener classes, 13 cableos" (antes "7 listeners"),
  §6 añade workaround SQLite (`docker compose up -d mysql` + `--group=mysql`),
  §12 changelog sincronizado. Tests:
  `tests/Unit/Documentation/AgentsDocsSyncTest.php` valida los 5 invariantes en
  CI.
- **2026-06-11 (Sprint 4)** — Actualizado tras cerrar el plan de mejoras futuras
  (5/5 sprints). Datos: 36 controllers, 47 modelos, 36 eventos, 7 listeners, 18
  services, 98 migraciones, 148 rutas, 17 módulos, 24 pages, 19 tests. CI/CD con
  GitHub Actions + MySQL. Multi-idioma catálogo. 2 ADRs. Estado §6 refleja los 3
  planes cerrados.
- **2026-06-11 (Sprint 1)** — Reescrito desde 428 → 236 líneas. Datos: 35
  controllers, 45 modelos. Estructura reorganizada.
- **<fecha anterior>** — Versión desactualizada con worktrees inexistentes,
  Sprint 1-3 pendientes, imports rotos ya arreglados.
