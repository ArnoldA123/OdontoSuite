# Plan maestro de mejoras futuras — OdontoSuite V2

> **Fecha**: 2026-06-11
> **Estado**: borrador para validación de Arnold.
> **Alcance**: hallazgos **nuevos** detectados tras cerrar los dos planes previos. NO duplica lo resuelto en:
> - `plan-flujo-catalog-procedimientos.md` (6 sprints ✅, 22 commits en `feat/procedure-catalog-master-data`).
> - `plan-inconsistencias-2026-06-actualizado.md` (6 sprints 0-5 ✅, 22 hallazgos C-/I-/M- cerrados, branch `fix/inconsistencias-sprint-1-multi-tenant` mergeado a `main`).

**Verificación de partida**: el branch `main` incluye `feat/procedure-catalog-master-data` + `fix/inconsistencias-sprint-1-multi-tenant` mergeados. La auditoría de este plan se ejecutó leyendo código real al 2026-06-11 (no en base a lo que decía `AGENTS.md`, que está desactualizado y se trata como hallazgo aparte, ver §3 hallazgo DM-4).

---

## 1. Contexto y problema

Los dos planes previos dejaron OdontoSuite V2 **funcionalmente sano**:
- Catálogo maestro de procedimientos con favoritos y pantallas por rol.
- Multi-tenant, 15 modelos con `SoftDeletes`, eventos blindados con `try/catch`, 0 console calls en frontend, alias `@` en Vite, branding migrado de EasyDent a OdontoSuite.

Pero al releer el código con ojos frescos aparecen **20 hallazgos** que ninguno de los dos planes tocó:

1. **Funcionalidad visiblemente rota** que no debería estar en `main`: dos controllers que son `//` (500 garantizados), un endpoint que devuelve 501, 26 de 33 eventos huérfanos, notificaciones que nunca salen, un `created_by => 1` hardcodeado.
2. **Deuda mayor** documentada como "out of scope" en los planes previos: triple fuente de verdad para `User::specialty`, doble fuente de verdad para `procedure_catalog.legacy_specialty`, 3 FormRequests que requieren refactor del controller, 9+ seeders legacy.
3. **DX descuidado**: `composer dev` invoca `npm` en vez de `pnpm`, `AGENTS.md` miente sobre el estado del proyecto, 2 `TestPage.vue` de desarrollo olvidados, sin CI/CD.
4. **Mejoras del catálogo** que el plan de procedimientos dejó explícitamente fuera de scope: dashboard de uso, importador CSV, versionado, multi-idioma, notificaciones en tiempo real.

Este plan ataca todo en **5 sprints ordenados por impacto**, con el Sprint 0 dedicado exclusivamente a que `main` no tenga APIs rotas ni 501s.

---

## 2. Resumen ejecutivo

| Severidad | Total | En Sprint 0 (urgente) | En Sprint 1-2 (dx/deuda) | En Sprint 3-4 (mejoras) |
|---|---|---|---|---|
| 🔴 Crítico | 6 | 6 | 0 | 0 |
| 🟠 Importante | 10 | 0 | 7 | 3 |
| 🟡 Mejora | 6 | 0 | 1 | 5 |
| **Total** | **22** | **6** | **8** | **8** |

**Esfuerzo restante estimado**: ~9 días-hombre distribuidos en 5 sprints (Sprint 0 = 0.5 d-h, el resto 1.5-3 d-h cada uno).

**Por categoría** (los IDs se detallan en §3):

| Categoría | ID rango | # | Notas |
|---|---|---|---|
| No funcional / API rota | NF-1 a NF-6 | 6 | Todos en Sprint 0 |
| Deuda mayor | DM-1 a DM-8 | 8 | Sprints 1-2 |
| Mejora de implementación | IM-1 a IM-8 | 8 | Sprints 3-4 |

**Sprint 0 es la prioridad #1**: 30 minutos de trabajo eliminan 6 500s/501s garantizados. No requiere ninguna decisión de arquitectura.

---

## 3. Hallazgos — estado verificado al 2026-06-11

### 🔴 CRÍTICOS (Sprint 0)

#### ❌ NF-1 — `ReminderController` y `ReminderTemplateController` son stubs vacíos → 500 garantizado

- **Archivos**: `app/Http/Controllers/Api/ReminderController.php`, `app/Http/Controllers/Api/ReminderTemplateController.php`.
- **Verificación**:
  ```bash
  grep -nE "public function (index|store|show|update|destroy)" \
    app/Http/Controllers/Api/ReminderController.php \
    app/Http/Controllers/Api/ReminderTemplateController.php
  # 5+5 métodos con body `//` (vacío)
  ```
- **Rutas activas** (`routes/api.php`):
  - L226: `Route::apiResource('reminder-templates', ReminderTemplateController::class);`
  - L234: `Route::apiResource('reminders', ReminderController::class);`
  - L235: `Route::post('reminders/{id}/send', [ReminderController::class, 'send']);`
- **Modelos/migraciones**: existen `Reminder` y `ReminderTemplate` (verificado en `database/migrations/`). La BD está lista, falta el controller.
- **Impacto real**: cualquier consumer del frontend que llame `GET /api/reminders` recibe HTTP 500. Si el `ReceptionProceduresPage` o el módulo de agenda llaman a estas rutas (por ejemplo, para mostrar recordatorios próximos), la página revienta.
- **Fix recomendado**:
  - Opción A (rápida, ~30 min): devolver `abort(501, 'Not implemented yet')` o `response()->json([...], 501)` con un mensaje claro, eliminar las rutas del `api.php` mientras no estén implementadas, o registrar `Route::fallback(fn() => response()->json(['message' => 'Not implemented'], 501))`.
  - Opción B (correcta, ~2-3 d-h): implementar CRUD mínimo sobre `Reminder` y `ReminderTemplate`. Plantilla: copiar patrón de `MedicalRecordController` (FormRequest tipado + Service).
  - **Recomendación**: **Opción A primero** (Sprint 0). Opción B como feature posterior si el negocio la pide.
- **Esfuerzo**: Opción A 5 min, Opción B 2-3 d-h.

#### ❌ NF-2 — 26 de 33 eventos están huérfanos

- **Archivos**: `app/Events/` (33 clases), `app/Providers/AppServiceProvider.php` (boot).
- **Verificación**:
  ```bash
  ls app/Events | wc -l
  # 33
  grep -nE "Event::listen" app/Providers/AppServiceProvider.php | wc -l
  # 7
  grep -nE "Event::listen" app/Providers/AppServiceProvider.php
  # L25, L30, L35, L40, L45, L50, L56
  ```
- **Lo cableado** (7 listeners):
  1. `LogAppointmentActivity` (AppointmentCreated/Updated/Deleted).
  2. `LogPatientActivity` (PatientCreated/Updated/Deleted).
  3. `CreateTransactionOnAppointmentCompleted` (AppointmentCompleted).
  4. (3 más según el orden de L25-L56, probablemente broadcasts de Reverb).
- **Lo huérfano** (26 eventos con clase creada pero 0 listeners):
  - Cotizaciones: `QuotationApproved`, `QuotationCreated`, `QuotationUpdated`.
  - Planes de tratamiento: `TreatmentPlanCreated`, `TreatmentPlanUpdated`, `TreatmentPlanDeleted`.
  - Registros clínicos: `SpecialtyRecordCreated`, `SpecialtyRecordUpdated`, `MedicalRecordCreated`, `MedicalRecordUpdated`, `ClinicalEvolutionCreated`, `ClinicalAttachmentCreated`.
  - Interconsultas: `InterconsultationCreated`, `InterconsultationResponded`.
  - Lista de espera: `WaitingListCreated`, `WaitingListFilled`.
  - Recordatorios: `ReminderSent`.
  - Caja: `CashMovementCreated`, `CashSessionOpened`, `CashSessionClosed`.
  - Pagos: `PaymentRegistered`.
  - Transacciones: `TransactionCreated`, `TransactionUpdated`.
  - Citas: `AppointmentCheckedIn`.
  - Usuarios: `UserCreated`, `UserUpdated`.
- **Impacto real**: los eventos se disparan (`event(new X(...))` está en los services), pero no hay nada escuchando. No se loguean, no se transmiten por Reverb, no disparan notificaciones, no actualizan BI. Es código muerto disfrazado de código activo. El plan de inconsistencias cerró M-5 (un evento huérfano eliminado) y M-2 (try/catch en event()), pero NO auditó cuántos eventos hay sin listener.
- **Fix recomendado**: para cada evento, decidir:
  - **Lo necesito → escribir listener** (LogXActivity, SendNotification, BroadcastOnReverb).
  - **No lo necesito → eliminar la clase del evento y el `event(new ...)` que lo dispara**.
  - Patrón de referencia: `LogAppointmentActivity` (escucha 3 eventos de Appointment y los escribe a `activity_log`).
  - **Mínimo viable Sprint 0**: eliminar los eventos que nadie dispara. Sprint 3 implementa los listeners que sí se necesitan.
- **Esfuerzo**: auditoría 1 h, eliminación 30 min, implementación de listeners 1-2 d-h por sprint.

#### ❌ NF-3 — `PendingPaymentsController@pay()` devuelve 501 explícito

- **Archivo**: `app/Http/Controllers/Api/PendingPaymentsController.php`.
- **Verificación**:
  ```bash
  grep -nE "501|TODO" app/Http/Controllers/Api/PendingPaymentsController.php
  # L193: "queda como TODO fuera del scope de este sprint"
  # L196: "queda como TODO fuera del scope de este sprint"
  # L212: "TODO Sprint futuro: implementar creación de Transaction via"
  # L220: "Por ahora devolvemos 501 para no romper la API con un 500"
  # L224: ], 501);
  ```
- **Ruta activa** (`routes/api.php`): `POST pending-payments/{id}/pay`.
- **El TODO dice exactamente qué hacer** (L212): `TransactionService::createTransaction` con `payment_method_id`, `amount`, etc.
- **Impacto real**: el recepcionista de caja no puede registrar un pago desde el front. Si el front tiene un botón "Pagar" en la pantalla de pagos pendientes, devuelve 501. Bloqueador real para el flujo de caja.
- **Fix recomendado**: implementar `pay()` siguiendo el TODO de L212. Validar `payment_method_id` (debe existir y pertenecer a la misma sede), crear `Transaction` vía `TransactionService::createTransaction`, marcarla como `paid`, retornar la transacción.
- **Esfuerzo**: 1.5 d-h (controller 30 min, FormRequest 15 min, tests 1 h, regresión manual 30 min).

#### ❌ NF-4 — `AuthController::forgotPassword` no envía email (TODO L155, L158)

- **Archivo**: `app/Http/Controllers/Api/AuthController.php`.
- **Verificación**:
  ```bash
  grep -nE "TODO|MAIL" app/Http/Controllers/Api/AuthController.php
  # L155: "// TODO: Implement email sending"
  # L158: "// TODO: Implementar envío real de email en producción"
  ```
- **Lo que hace**: genera un token, lo guarda en `password_reset_tokens`, responde 200 con mensaje genérico. El token queda en BD para siempre, sin uso.
- **Causa raíz**: `MAIL_MAILER` no está en `.env` (verificado). Sin driver de mail configurado no se puede enviar.
- **Impacto real**: el flujo de "olvidé mi contraseña" no funciona. El usuario pide reset, no recibe email, no puede resetear.
- **Fix recomendado**:
  1. Agregar `MAIL_MAILER=log` al `.env.example` (al menos en dev el email se loguea).
  2. Implementar el envío con `Notification::send($user, new ResetPasswordNotification($token))`.
  3. Si en producción no hay SMTP, al menos que el endpoint devuelva el token en dev (bajo `APP_DEBUG=true`) para que el desarrollador pueda testear el flujo completo.
- **Esfuerzo**: 1 d-h (configuración 15 min, Mailable 30 min, prueba real 30 min).

#### ❌ NF-5 — `ExportPatientFileJob` no notifica al usuario (TODO L63)

- **Archivo**: `app/Jobs/ExportPatientFileJob.php`.
- **Verificación**:
  ```bash
  grep -nE "TODO|Notify" app/Jobs/ExportPatientFileJob.php
  # L63: "// TODO: Notify user via WebSocket or email that export is ready"
  ```
- **Lo que hace**: genera el archivo, lo guarda, termina el job. El usuario no recibe aviso.
- **Impacto real**: el doctor pide la historia clínica del paciente para auditoría/legal, el job se ejecuta, pero el doctor nunca se entera. Si no refresca la página, no sabe que está listo.
- **Fix recomendado**:
  1. Disparar un evento `PatientFileExported($userId, $path)` con listener que envía notification + broadcast por Reverb.
  2. Crear `Notification\PatientFileReadyNotification` (canal `mail` + `database`).
  3. En el frontend, suscribir al canal privado del usuario y mostrar toast/banner.
- **Esfuerzo**: 1 d-h (job 15 min, notification 15 min, listener + broadcast 30 min, frontend toast 30 min).

#### ❌ NF-6 — `WaitingListService` hardcodea `created_by => 1`

- **Archivo**: `app/Services/WaitingListService.php`.
- **Verificación**:
  ```bash
  grep -nE "created_by|TODO" app/Services/WaitingListService.php
  # L88: "'created_by' => 1, // TODO: Get from authenticated user"
  # L89: "'updated_by' => 1, // TODO: Get from authenticated user"
  ```
- **Impacto real**: cualquier usuario que agrega un paciente a lista de espera aparece registrado como creado por el usuario con `id=1` (probablemente el admin seed). Rompe auditoría, no se puede rastrear quién agregó a quién, y falla en `if (auth()->user()->id === $waitingList->created_by)` en cualquier UI.
- **Fix recomendado**:
  1. Cambiar la firma del service: `addToWaitingList(int $patientId, int $createdBy, ...)`.
  2. Pasar `auth()->id()` desde el controller (`WaitingListController::store`).
  3. Mismo patrón en `WaitingListController` (verificar si tiene la signature actual).
- **Esfuerzo**: 20 min.

---

### 🟠 IMPORTANTES (Sprints 1-2)

#### ❌ DM-1 — `composer.json` script `dev` usa `npm` en vez de `pnpm`

- **Archivo**: `composer.json`.
- **Verificación**:
  ```bash
  grep -nE "npm run dev|pnpm dev" composer.json
  # Línea del script "dev": "npx concurrently ... \"npm run dev\""
  ```
- **Inconsistencia**: `AGENTS.md` (aunque desactualizado, ver DM-4) dice "pnpm siempre, nunca npm". `package.json` probablemente también lo dice.
- **Impacto real**: el dev que corre `composer dev` para arrancar el stack completo (serve + reverb + queue + pail + vite) termina ejecutando `npm run dev` en lugar de `pnpm dev`. Si `node_modules/` está lockeado a pnpm, puede haber inconsistencias.
- **Fix recomendado**: cambiar `"npm run dev"` por `"pnpm dev"` en el script `dev` de `composer.json`. Verificar también `package.json` (debería tener `"dev": "vite"` y los scripts `predev`/`prebuild` con `pnpm install`).
- **Esfuerzo**: 2 min.

#### ❌ DM-2 — `AGENTS.md` (428 líneas) desactualizado y engañoso

- **Archivo**: `AGENTS.md`.
- **Verificación**:
  ```bash
  wc -l AGENTS.md
  # 428
  ```
- **Lo que dice (incorrecto)**:
  - Menciona worktrees que ya no existen (los branches ya están mergeados a `main`).
  - Dice "Sprint 1-3 pendientes" cuando los 6 sprints de ambos planes ya están cerrados.
  - Menciona import roto en `AppLayout.vue` L335 que ya se arregló en M-3 del plan de inconsistencias.
  - Lista "Email real sin implementar" como pendiente cuando solo es NF-4 (un TODO específico).
  - Dice "347 console.log" cuando ya están en 0 (Sprint 5 I-8).
  - Lista "SoftDeletes sin aplicar" cuando ya están en 15 modelos (Sprint 3 + Sprint 4 M-1).
  - Menciona seeders legacy como vigentes (ver DM-5).
- **Impacto real**: el próximo dev/IA que lea `AGENTS.md` va a perder tiempo arreglando cosas que ya están hechas, o va a asumir que otras están pendientes cuando no. Es peor que no tener doc.
- **Fix recomendado**:
  1. Reescribir `AGENTS.md` desde cero con la realidad al 2026-06-11.
  2. Estructura sugerida: §1 quickstart (pnpm + composer dev), §2 arquitectura (Laravel 12 + Vue 3 + Reverb + Sanctum), §3 comandos frecuentes, §4 troubleshooting, §5 referencias a planes cerrados.
  3. Mover el detalle histórico de cada sprint a `docs/mejoras/changelog.md` (ver IM-7).
- **Esfuerzo**: 2 d-h (reescritura 1 d-h, revisión con Arnold 1 d-h).

#### ❌ DM-3 — 2 `TestPage.vue` de desarrollo olvidados en `resources/js/modules/`

- **Archivos**:
  - `resources/js/modules/test/TestPage.vue`
  - `resources/js/modules/auth/TestPage.vue`
- **Verificación**:
  ```bash
  find resources/js/modules -name "TestPage.vue"
  # resources/js/modules/test/TestPage.vue
  # resources/js/modules/auth/TestPage.vue
  ```
- **Impacto real**: páginas de prueba que NO deberían estar en producción. Si están registradas en el router (`resources/js/router/index.js` o equivalente), el usuario puede acceder a `/test` o `/auth/test` y ver UI de debugging.
- **Fix recomendado**:
  - **Opción A (rápida)**: eliminar ambos archivos y las entradas del router.
  - **Opción B (correcta)**: mover a `resources/js/modules/_dev/` y excluir del build con `pnpm build --production` o con un flag de entorno en `vite.config.js`. Agregar `_dev/` a `.gitignore` del repo local.
- **Esfuerzo**: 15 min (Opción A).

#### ❌ DM-4 — 3 FormRequests no migrables (cierre I-2 del plan de inconsistencias)

- **Archivos**:
  - `app/Http/Requests/StoreAppointmentRequest.php` (omite `duration_minutes`, `idempotency_key`, `notes`, `status`).
  - `app/Http/Requests/StoreQuotationRequest.php` (rompe path `generateQuotation` al requerir `patient_id`).
  - `app/Http/Requests/StoreSpecialtyRecordRequest.php` (omite 14 campos: `batch_number`, `canal_count`, `implant_brand`, etc.).
- **Verificación**:
  ```bash
  diff <(grep -oE "'[a-z_]+'" app/Http/Controllers/Api/AppointmentController.php | sort -u) \
       <(grep -oE "'[a-z_]+'" app/Http/Requests/StoreAppointmentRequest.php | sort -u)
  # 4+ campos no coinciden
  ```
- **Impacto real**: el controller valida 4-14 campos inline (sin `FormRequest`). Es código duplicado, difícil de mantener, y rompe el patrón uniforme que el plan de catálogo S6 y el Sprint 5 I-2 ya establecieron.
- **Fix recomendado**:
  - `StoreAppointmentRequest`: refactorizar para que `generateAppointment()` y `store()` acepten el mismo set de campos (split del controller o agregar `duration_minutes`/etc al FR).
  - `StoreQuotationRequest`: agregar `patient_id` nullable (cuando viene de `generateQuotation`, el patient viene del plan; cuando viene de `store` manual, sí lo requiere). O crear 2 FormRequests separados.
  - `StoreSpecialtyRecordRequest`: agregar los 14 campos faltantes al FR. Probablemente es un refactor de 30 min por campo.
- **Esfuerzo**: 1.5 d-h total (30 min cada StoreAppointment + StoreQuotation + StoreSpecialtyRecord × análisis + fix + tests).

#### ❌ DM-5 — 9+ seeders legacy no usados (dominio `@easydent.com` y roles antiguos)

- **Archivos**: `database/seeders/`.
- **Verificación**:
  ```bash
  ls database/seeders
  # 35 seeders en total
  grep -lE "easydent\.com|EasyDent" database/seeders/*.php
  # AdminUserSeeder.php, ReceptionUserSeeder.php, DentistUserSeeder.php
  ```
- **Lo que hay** (35 seeders):
  - **Vigentes** (referenciados en `DatabaseSeeder.php` o `EnvironmentSeeder.php`): `EnvironmentSeeder`, `RoleBasedUsersSeeder`, `BranchSeeder`, `SpecialtySeeder`, `ProcedureCatalogSeeder`, `PaymentMethodsSeeder`, `AppointmentTypeSeeder`, `CashRegisterSeeder`, `ProfessionalSeeder`, `PatientSeeder`, `SpecialtyRecordSeeder`, `MedicalRecordSeeder`, `ClinicalAttachmentSeeder`, `ReminderSchedulesSeeder`, `AiAnalysisSeeder`, `EssentialUsersSeeder`, `DatabaseSeeder`.
  - **Legacy/duplicados/candidatos a eliminar**:
    - `AdminUserSeeder`, `ReceptionUserSeeder`, `DentistUserSeeder` (dominio `@easydent.com`, roles antiguos — antes del plan de catálogo).
    - `AppointmentSeeder`, `ClinicalDataSeeder`, `DentalPiecesSeeder`, `RealisticAppointmentsSeeder`, `SampleDataSeeder`, `SimpleSpecialtyRecordSeeder`, `TestUserSeeder`, `DentalChairSeeder`, `EssentialDataSeeder`, `TestDataSeeder`, `CashRegisterTestSeeder`, `SimpleAppointmentsSeeder`, `CompletedAppointmentsSeeder`, `DentalPieceSeeder` (vs `DentalPiecesSeeder` y `ProcedureCatalogSeeder`).
    - `ReminderSchedulesSeeder` está vigente pero `AdminUserSeeder`/`ReceptionUserSeeder`/`DentistUserSeeder` fueron reemplazados por `RoleBasedUsersSeeder`.
- **Impacto real**: clutter. Riesgo de que un dev nuevo corra `php artisan db:seed --class=AdminUserSeeder` y cree usuarios con dominio incorrecto.
- **Fix recomendado**:
  1. Mover todos los seeders legacy a `database/seeders/_legacy/` con un README explicando por qué se preservan.
  2. O eliminarlos (recomendado si no hay razón histórica) y documentar en el commit "eliminados seeders legacy EasyDent, migrados a RoleBasedUsersSeeder".
- **Esfuerzo**: 30 min.

#### ❌ DM-6 — Triple fuente de verdad para `User::specialty`

- **Archivos**: `app/Models/User.php` (`$fillable` L30: `'specialty'`), migraciones `2025_09_20_093115_add_specialty_and_phone_to_users_table.php` (string legacy), `2026_06_10_100200_create_user_specialties_table.php` (pivote many-to-many).
- **Verificación**:
  ```bash
  grep -nE "specialty" app/Models/User.php
  # L23: protected $fillable = [
  # L30: 'specialty',  <- string legacy
  ls database/migrations | grep -i user
  # ..._add_specialty_and_phone_to_users_table.php (string)
  # ..._create_user_specialties_table.php (pivote)
  ```
- **Estado actual**:
  - `users.specialty` (string legacy): "Ortodoncia", "Endodoncia", etc.
  - `user_specialties` (pivote many-to-many con `specialties`): 0+ filas por usuario.
  - Probable tercer lugar: `users.specialties` (JSON legacy) — **no verificado**, pendiente auditoría.
- **Impacto real**: cuando Arnold crea un doctor con 2 especialidades (caso real en clínicas grandes), ¿cuál campo se actualiza? Si los 3 existen, el código que lee `$user->specialty` puede no reflejar la realidad.
- **Fix recomendado**:
  1. **Auditar primero**: `grep -rn "->specialty" app/ resources/js/ database/` para ver quién lee cada campo.
  2. Si `users.specialties` (JSON) existe, eliminarlo (consolidar todo en pivote).
  3. Si `users.specialty` (string) se sigue usando en algún lado, decidir:
     - **Mantenerlo como campo display** (denormalizado, actualizado por observer al pivote) y deprecarlo formalmente con `@deprecated` PHPDoc.
     - **Eliminarlo** y migrar todo el código a `->specialties()->pluck('name')`.
  4. Documentar en `docs/decisions/0007-user-specialty-source-of-truth.md` (ADR).
- **Esfuerzo**: 2 d-h (1 d-h auditoría + 1 d-h migración).

#### ❌ DM-7 — `procedure_catalog.legacy_specialty` (string) convive con `procedure_catalog.specialty_id` (FK)

- **Archivos**: `database/migrations/2026_06_10_100500_rename_specialty_to_legacy_specialty_in_procedure_catalog.php`, `app/Models/ProcedureCatalog.php` L22 (`'legacy_specialty'` en fillable).
- **Verificación**:
  ```bash
  ls database/migrations | grep procedure_catalog
  # 2026_06_10_100500_rename_specialty_to_legacy_specialty_in_procedure_catalog.php
  grep -nE "legacy_specialty|specialty_id" app/Models/ProcedureCatalog.php
  # L22: 'legacy_specialty',  <- string
  # (presumiblemente) 'specialty_id' también en fillable
  ```
- **Contexto**: el plan de catálogo Sprint 1 renombró `specialty` → `legacy_specialty` y agregó `specialty_id` FK. El plan de catálogo §7 desvío 1 documentó que el drop final de `legacy_specialty` quedaba pendiente.
- **Impacto real**: doble escritura. Si actualizas `specialty_id` pero no `legacy_specialty` (o viceversa), el código que lee uno u otro se desincroniza.
- **Fix recomendado**:
  1. Auditar quién lee `legacy_specialty` (`grep -rn "legacy_specialty" app/ resources/js/`).
  2. Si nadie lo lee (probable, fue desfasado por el catálogo), hacer drop de la columna en una nueva migración.
  3. Si alguien lo lee, refactorizarlo a leer `specialty.name` vía JOIN.
- **Esfuerzo**: 1 d-h (auditoría 30 min, drop + test 30 min).

#### ❌ DM-8 — No hay CI/CD (`.github/` no existe)

- **Verificación**:
  ```bash
  ls -la .github 2>/dev/null || echo "no .github dir"
  ```
- **Impacto real**: cualquier deploy es manual. No hay validación automática de `pnpm build`, `php artisan test`, o `phpstan`. Un PR con tests rotos puede mergear.
- **Fix recomendado**:
  - Crear `.github/workflows/ci.yml` con jobs:
    1. `composer install --no-interaction --prefer-dist`
    2. `pnpm install --frozen-lockfile`
    3. `pnpm build` (debe pasar)
    4. `php artisan test` (debe pasar; ver IM-1 sobre el problema SQLite/MySQL)
    5. `php artisan route:list` (verifica que no haya rutas rotas)
  - Opcional: `phpstan analyse` si se quiere agregar análisis estático.
- **Esfuerzo**: 1 d-h.

---

### 🟡 MEJORAS (Sprints 3-4)

#### ❌ IM-1 — 16 tests nuevos son estructurales, no de integración real

- **Origen**: Sprint 4 M-6 del plan de inconsistencias.
- **Problema**: `RefreshDatabase` no funciona con SQLite (28 tests viejos fallan por `MODIFY COLUMN` de MySQL en migraciones).
- **Impacto real**: los 16 tests verifican que las clases existen y los métodos requeridos están, pero **no** que devuelven los datos correctos. Si alguien cambia la lógica de `QuotationService::createQuotation` y rompe C-1, los tests no lo detectan.
- **Fix recomendado**:
  - **Opción A (correcta)**: arreglar el problema SQLite/MySQL. Reemplazar las migraciones con `DB::statement('ALTER TABLE ... MODIFY ...')` por `Schema::table()` con tipos portables. Estimación: 2-3 d-h porque hay 2 migraciones afectadas.
  - **Opción B (pragmática)**: configurar la suite de tests para usar MySQL en CI (requiere servicio MySQL en GH Actions). Estimación: 4 h.
  - **Opción C (parcial)**: marcar los 16 tests como `# @group integration-skip` y dejarlos fuera de la corrida. Documentar en `tests/README.md` que la cobertura real está pendiente.
- **Esfuerzo**: 2-3 d-h (Opción A) o 4 h (Opción B).

#### ❌ IM-2 — `CREDENTIALS.md` desactualizado

- **Origen**: mencionado en `plan-flujo-catalog-procedimientos.md` §7 "Cómo probarlo" L401.
- **Problema**: dice `adm1n` como usuario, pero el seeder vigente es `RoleBasedUsersSeeder` (no `AdminUserSeeder`). El login real puede no estar.
- **Verificación**: leer `CREDENTIALS.md`, comparar con `RoleBasedUsersSeeder::run()`.
- **Fix recomendado**: regenerar `CREDENTIALS.md` corriendo los seeders en dev y exportando los usuarios creados con sus roles y especialidades. Documentar también los passwords de dev (NUNCA producción).
- **Esfuerzo**: 30 min.

#### ❌ IM-3 — No hay tests para el flujo de catálogo de procedimientos (recién implementado)

- **Origen**: plan de catálogo §8 (out of scope).
- **Impacto real**: el feature más grande del último sprint no tiene un solo test automatizado. Regresiones pasarían desapercibidas.
- **Fix recomendado**: agregar `tests/Feature/ProcedureCatalogTest.php` cubriendo:
  - Crear procedimiento (admin) → 201 + body correcto.
  - Marcar como favorito (cualquier rol) → 201, segunda vez → 200 (idempotente).
  - ProcedureQuickPicker devuelve solo los favoritos + procedimientos generales.
  - MyProceduresPage devuelve solo los procedimientos del usuario.
  - ReceptionProceduresPage devuelve los procedimientos habilitados de la sede.
  - ProcedureCatalogPage (admin) → filtros por nombre, especialidad, estado.
  - Soft-delete de un procedimiento en uso: verificar que aparece como "no disponible" en planes de tratamiento activos.
- **Esfuerzo**: 2 d-h (7-10 tests, integrar con la suite arreglando IM-1 primero).

#### ❌ IM-4 — Notificación Reverb en tiempo real al desactivar procedimiento en uso

- **Origen**: plan de catálogo §8 out-of-scope.
- **Impacto real**: si el admin desactiva un procedimiento que está en planes de tratamiento activos, los doctores que lo están usando no se enteran hasta que refrescan. En OdontoSuite, los planes pueden durar meses.
- **Fix recomendado**:
  1. Listener `NotifyProcedureDeactivation` para evento `ProcedureCatalogDeactivated` (nuevo).
  2. Disparar el evento desde `ProcedureCatalogController::update` cuando `is_active` pasa de true a false.
  3. Listener busca todos los `TreatmentPlanItem` con ese `procedure_catalog_id` y notifica a los doctores responsables (vía `User` relacionado).
  4. Frontend: canal Reverb `procedures.deactivated`, mostrar toast persistente.
- **Esfuerzo**: 1.5 d-h (backend 1 d-h, frontend 30 min).

#### ❌ IM-5 — Dashboard de uso de procedimientos

- **Origen**: plan de catálogo §8 out-of-scope.
- **Impacto real**: el admin no sabe qué procedimientos se usan más ni cuáles son rentables. Decisiones de catálogo se toman a ciegas.
- **Fix recomendado**:
  1. Endpoint `GET /api/admin/procedure-stats?from=&to=` que devuelva:
     - Top 10 procedimientos por # de citas.
     - Top 10 por revenue total.
     - Distribución por especialidad.
     - Distribución por profesional.
  2. Query optimizada con `withCount` y agregaciones.
  3. Frontend: nueva pantalla `ProcedureStatsPage.vue` con tabla + gráfico (Chart.js ya instalado probablemente).
- **Esfuerzo**: 2 d-h (backend 1 d-h, frontend 1 d-h).

#### ❌ IM-6 — Importador CSV de procedimientos

- **Origen**: plan de catálogo §8 out-of-scope.
- **Impacto real**: agregar 50 procedimientos uno por uno en la UI es tedioso. Un CSV con columnas `code, name, specialty, default_duration, default_cost, currency` lo resolvería.
- **Fix recomendado**:
  1. Nuevo endpoint `POST /api/admin/procedure-catalog/import` con `multipart/form-data`.
  2. Validar headers, procesar en chunks con `Bus::batch`, retornar resumen (insertados / errores / filas fallidas con razón).
  3. Frontend: drag-and-drop en `ProcedureCatalogPage.vue` con preview antes de confirmar.
  4. Exportar CSV también (para que el admin pueda editar en Excel y reimportar).
- **Esfuerzo**: 2.5 d-h (backend 1.5 d-h, frontend 1 d-h).

#### ❌ IM-7 — Versionado del catálogo

- **Origen**: plan de catálogo §8 out-of-scope.
- **Impacto real**: si un procedimiento cambia de precio o nombre, los planes de tratamiento viejos muestran el precio/nuevo. No hay forma de "vieron" el catálogo en el tiempo.
- **Fix recomendado**:
  1. Tabla `procedure_catalog_versions` (procedure_catalog_id, version, name, default_cost, valid_from, valid_to).
  2. Trigger o listener que crea una versión cada vez que cambia `default_cost` o `name`.
  3. `TreatmentPlanItem` referencia la versión, no el `procedure_catalog_id` directo.
  4. UI: "Ver historial de cambios" en cada procedimiento.
- **Esfuerzo**: 3 d-h (es un cambio de modelo, no un feature pequeño).

#### ❌ IM-8 — Multi-idioma del catálogo

- **Origen**: plan de catálogo §8 out-of-scope.
- **Impacto real**: el catálogo está en español. Si la clínica atiende pacientes angloparlantes, los profesionales que comparten la pantalla con el paciente no pueden mostrar nombres en otro idioma.
- **Fix recomendado**:
  1. Migración `procedure_catalog_translations` (procedure_catalog_id, locale, name, description).
  2. Actualizar `ProcedureCatalog` model con relación `translations()` y accessor `name($locale)`.
  3. UI: selector de idioma en `ProcedureQuickPicker` y en las páginas de catálogo.
  4. Seed inicial con `es` (actual) + `en` para los procedimientos de ProcedureCatalogSeeder.
- **Esfuerzo**: 2 d-h.

---

## 4. Roadmap en sprints

| Sprint | Foco | # hallazgos | Esfuerzo | Branch sugerido | Riesgo principal |
|---|---|---|---|---|---|
| **0** | Funcionalidad rota que no debería estar en `main` | 6 (NF-1 a NF-6) | 0.5 d-h | `fix/sprint-0-api-rota` | Romper consumidores de Reminder si se elimina la ruta en vez de devolver 501 |
| **1** | Configuración y DX | 4 (DM-1, DM-2, DM-3, DM-5) | 1 d-h | `chore/dx-cleanup` | Reescritura de AGENTS.md lleva más de lo estimado |
| **2** | Deuda técnica | 4 (DM-4, DM-6, DM-7, DM-8) | 4 d-h | `fix/deuda-tecnica` | Drop de `legacy_specialty` puede romper readers que no detectamos |
| **3** | Calidad de catálogo | 5 (IM-3, IM-4, IM-5, IM-6, IM-7) | 9 d-h | `feat/catalog-quality` | Tests IM-3 dependen de arreglar IM-1 antes |
| **4** | Pulido y consistencia | 3 (IM-1, IM-2, IM-8) | 4 d-h | `chore/pulido-final` | IM-1 (SQLite/MySQL) es un rabbit hole |
| **Total** | — | **22** | **18.5 d-h** | — | — |

**Core (Sprints 0-2)**: ~5.5 d-h = ~1.5 semanas calendario a ritmo de capstone. Recomendado antes de cualquier deploy a producción.

---

### Sprint 0 — Funcionalidad rota (0.5 d-h) — **Pendiente**

**Objetivo**: que `main` no tenga APIs rotas, eventos huérfanos, ni 501s.

**Implementación** (branch: `fix/sprint-0-api-rota`):

- [ ] **NF-1 (Opción A)**: en `ReminderController` y `ReminderTemplateController`, reemplazar los bodies `//` por `return response()->json(['message' => 'Reminder feature not implemented yet'], 501);`. NO eliminar las rutas (pueden ser consumidas por front en cualquier momento).
- [ ] **NF-2 (mínimo)**: grep todos los `event(new X(...))` en el código y emparejar con listeners. Eliminar los `event(new X(...))` cuyo listener no exista Y cuyo X no aporte valor. Para los que sí aportan, **dejarlos** y abrir tickets para Sprint 3.
- [ ] **NF-3**: implementar `PendingPaymentsController@pay()` siguiendo el TODO de L212. `TransactionService::createTransaction` con validación de `payment_method_id` y `amount > 0`.
- [ ] **NF-4**: agregar `MAIL_MAILER=log` a `.env.example`. Implementar `Mail::send(...)` en `forgotPassword` (L155). Si `MAIL_MAILER` no es `smtp`, devolver el token en la respuesta solo si `APP_DEBUG=true`.
- [ ] **NF-5**: agregar `event(new PatientFileExported($userId, $path))` al final de `ExportPatientFileJob::handle()`. Crear el evento y un listener que envíe `Mail::raw(...)` al user.
- [ ] **NF-6**: cambiar firma de `WaitingListService::addToWaitingList` para recibir `$createdBy`. Pasar `auth()->id()` desde `WaitingListController::store`.

**Verificación**:
```bash
# NF-1
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/reminders -w "\n%{http_code}\n"
# 501 (no 500)

# NF-2
grep -rn "event(new " app/ | wc -l
# (debería bajar de N a M, con M = # listeners útiles)
grep -cE "Event::listen" app/Providers/AppServiceProvider.php
# 7 (sin cambios en Sprint 0, solo eliminamos los event() huérfanos)

# NF-3
curl -s -X POST -H "Authorization: Bearer $TOKEN" -d "payment_method_id=1&amount=100" \
  http://localhost:8000/api/pending-payments/1/pay -w "\n%{http_code}\n"
# 200 con Transaction creada (no 501)

# NF-4
php artisan tinker
> Mail::raw('test', fn($m) => $m->to('test@x.com'));
# debe escribir a laravel.log

# NF-5
php artisan tinker
> dispatch(new App\Jobs\ExportPatientFileJob(1, 1));
> ls storage/logs/laravel.log
# debe contener "Patient file exported for user"

# NF-6
php artisan tinker
> $wl = App\Services\WaitingListService::addToWaitingList(1, 999, 'urgente');
> echo $wl->created_by;
# 999 (no 1)

pnpm build && php artisan test
# ambos pasan
```

**Riesgos**:
- NF-1 Opción A: si el front esperaba un 200 con array vacío, va a fallar. Workaround: Opción B (implementar CRUD) en lugar de A.
- NF-2: si se eliminan eventos que algún día se cableaban, hay que rebuildear la clase. Workaround: en lugar de eliminar, marcar `@deprecated` y dejar el `event(new ...)` con `Log::warning`.
- NF-3: la implementación de `pay()` requiere que `TransactionService::createTransaction` exista y funcione. Verificar antes.
- NF-6: si el controller no pasa `auth()->id()`, sigue hardcodeado. Verificar el call site.

**Commit**: `fix(api): Sprint 0 - eliminar 500s y 501s garantizados (NF-1..NF-6)`.

---

### Sprint 1 — Configuración y DX (1 d-h) — **Pendiente**

**Objetivo**: que el setup local y la documentación del proyecto sean correctos.

**Implementación** (branch: `chore/dx-cleanup`):

- [ ] **DM-1**: cambiar `"npm run dev"` por `"pnpm dev"` en el script `dev` de `composer.json`. Verificar que `package.json` tiene `"dev": "vite"`.
- [ ] **DM-2**: reescribir `AGENTS.md` desde cero (428 → ~150 líneas). Estructura: §1 quickstart, §2 stack, §3 comandos, §4 troubleshooting, §5 planes cerrados.
- [ ] **DM-3**: eliminar `resources/js/modules/test/TestPage.vue` y `resources/js/modules/auth/TestPage.vue`. Quitar las entradas del router.
- [ ] **DM-5**: mover 9 seeders legacy a `database/seeders/_legacy/` con un README. O eliminarlos y commitear como "chore: remove legacy EasyDent seeders".

**Verificación**:
```bash
# DM-1
composer dev
# debe ejecutar "pnpm dev" (no "npm run dev")
grep -E "npm run dev|pnpm dev" composer.json
# solo "pnpm dev"

# DM-2
wc -l AGENTS.md
# ~150 (era 428)

# DM-3
find resources/js/modules -name "TestPage.vue"
# (vacío)

# DM-5
php artisan db:seed
# debe correr sin errores (los seeders legacy no se cargan automáticamente)
ls database/seeders/_legacy
# 9+ archivos
```

**Riesgos**:
- DM-2: si la reescritura lleva más de 1 d-h (probable), mover a Sprint 4.
- DM-3: si alguna página de test está en uso real, eliminarla rompe el flujo. Verificar con grep primero.
- DM-5: si algún seeder legacy se referencia desde `DatabaseSeeder.php` (no debería), no se puede mover.

**Commit**: `chore(dx): Sprint 1 - pnpm, AGENTS.md, eliminar test pages, mover seeders legacy`.

---

### Sprint 2 — Deuda técnica (4 d-h) — **Pendiente**

**Objetivo**: cerrar la deuda documentada en planes previos pero no resuelta.

**Implementación** (branch: `fix/deuda-tecnica`):

- [ ] **DM-4 (3 FormRequests)**:
  - `StoreAppointmentRequest`: agregar `duration_minutes`, `idempotency_key`, `notes`, `status` (todos nullable).
  - `StoreQuotationRequest`: hacer `patient_id` nullable. Lógica en el controller: si viene `patient_id` usarlo, si no, sacarlo del `treatment_plan_id`.
  - `StoreSpecialtyRecordRequest`: agregar 14 campos faltantes (`batch_number`, `canal_count`, `implant_brand`, etc.). Probablemente requiere revisar el modelo `SpecialtyRecord` para ver qué campos acepta.
- [ ] **DM-6 (User::specialty)**:
  1. `grep -rn "->specialty" app/ resources/js/ database/` para mapear lectores.
  2. Si `users.specialties` (JSON) existe, eliminar (drop column en nueva migración).
  3. Si `users.specialty` (string) tiene lectores, decidir mantener como display denormalizado o eliminar.
  4. Documentar decisión en `docs/decisions/0007-user-specialty-source-of-truth.md`.
- [ ] **DM-7 (procedure_catalog.legacy_specialty)**:
  1. `grep -rn "legacy_specialty" app/ resources/js/` para mapear lectores.
  2. Si 0 lectores: drop column.
  3. Si hay lectores: refactor a `specialty.name` vía JOIN.
- [ ] **DM-8 (CI/CD)**:
  - Crear `.github/workflows/ci.yml` con jobs `composer install`, `pnpm install --frozen-lockfile`, `pnpm build`, `php artisan test`, `php artisan route:list`.

**Verificación**:
```bash
# DM-4
php artisan route:list --path=appointments
# POST /appointments debe usar StoreAppointmentRequest
grep "type-hint" app/Http/Controllers/Api/AppointmentController.php
# (StoreAppointmentRequest $request)

# DM-6 / DM-7
php artisan tinker
> $u = User::factory()->create();
> $u->specialties()->attach(Specialty::first()->id);
> echo $u->fresh()->specialty; # (o null si se eliminó)

# DM-8
git push origin fix/deuda-tecnica
# GH Actions debe correr y pasar
```

**Riesgos**:
- DM-4 `StoreQuotationRequest` romper el path `generateQuotation` es muy probable. Hacer backup del controller antes.
- DM-6 eliminar `users.specialty` puede romper filtros en queries de admin. Auditar primero.
- DM-7 drop de `legacy_specialty` puede romper queries de BI que dependen de la columna string.
- DM-8 si IM-1 (tests) no está arreglado, CI va a fallar siempre. Workaround: usar `php artisan test --exclude-group=integration-skip` o arreglar IM-1 primero.

**Commit**: `fix(debt): Sprint 2 - FormRequests, User::specialty, legacy_specialty, CI/CD (DM-4, DM-6, DM-7, DM-8)`.

---

### Sprint 3 — Calidad de catálogo (9 d-h) — **Pendiente**

**Objetivo**: cerrar los 5 out-of-scope del plan de catálogo + agregar tests.

**Implementación** (branch: `feat/catalog-quality`):

- [ ] **IM-3 (tests del flujo catálogo)**: `tests/Feature/ProcedureCatalogTest.php` con 7-10 tests. **Depende de IM-1 arreglado primero.**
- [ ] **IM-4 (notif Reverb)**: evento `ProcedureCatalogDeactivated` + listener `NotifyProcedureDeactivation` + frontend toast.
- [ ] **IM-5 (dashboard de uso)**: endpoint `GET /api/admin/procedure-stats` + `ProcedureStatsPage.vue`.
- [ ] **IM-6 (importador CSV)**: endpoint `POST /api/admin/procedure-catalog/import` con batch + UI drag-and-drop.
- [ ] **IM-7 (versionado)**: tabla `procedure_catalog_versions` + listener que crea versión al cambiar `default_cost`/`name`.

**Verificación**:
```bash
# IM-3
php artisan test --filter ProcedureCatalogTest
# 7-10 tests, todos verdes

# IM-4
php artisan tinker
> $pc = ProcedureCatalog::first();
> $pc->update(['is_active' => false]);
> ls storage/logs/laravel.log
# debe contener "Procedure X deactivated, notifying N doctors"

# IM-5
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
  "http://localhost:8000/api/admin/procedure-stats?from=2026-01-01&to=2026-06-01" | jq
# debe devolver top 10 procedimientos

# IM-6
curl -s -X POST -H "Authorization: Bearer $ADMIN_TOKEN" \
  -F "file=@procedures.csv" http://localhost:8000/api/admin/procedure-catalog/import
# debe devolver { inserted: 50, errors: 0, failed_rows: [] }

# IM-7
php artisan tinker
> $pc = ProcedureCatalog::first();
> $old_cost = $pc->default_cost;
> $pc->update(['default_cost' => $old_cost + 10]);
> $versions = $pc->versions;
> $versions->count()
# 2 (la versión inicial + la nueva)
```

**Riesgos**:
- IM-3 depende de IM-1 (tests estructurales vs integración). Si IM-1 no se arregla, IM-3 también queda como tests estructurales.
- IM-5 queries pueden ser lentas sin índices. Agregar índice a `appointment_items.procedure_catalog_id` y `treatment_plan_items.procedure_catalog_id` si no existen.
- IM-7 cambio de modelo requiere migración de datos existente. Backfill en la misma migración.

**Commit**: `feat(catalog): Sprint 3 - tests, notificaciones, dashboard, CSV, versionado (IM-3..IM-7)`.

---

### Sprint 4 — Pulido y consistencia (4 d-h) — **Pendiente**

**Objetivo**: cerrar lo que queda, dejar `main` lista para deploy.

**Implementación** (branch: `chore/pulido-final`):

- [ ] **IM-1 (SQLite/MySQL tests)**: arreglar las 2 migraciones con `DB::statement('ALTER TABLE ... MODIFY ...')` para que sean portables. O configurar la suite para usar MySQL en CI.
- [ ] **IM-2 (CREDENTIALS.md)**: regenerar corriendo `php artisan migrate:fresh --seed` y exportando usuarios.
- [ ] **IM-8 (multi-idioma)**: tabla `procedure_catalog_translations` + accessor `name($locale)` + UI selector.

**Verificación**:
```bash
# IM-1
php artisan test
# 0 fallidos (o solo los marcados como skip)

# IM-2
cat CREDENTIALS.md
# debe listar usuarios reales del RoleBasedUsersSeeder

# IM-8
php artisan tinker
> $pc = ProcedureCatalog::first();
> $pc->translate('en', 'Dental cleaning');
> echo $pc->name; # 'Limpieza dental' (default)
> echo $pc->name('en'); # 'Dental cleaning'
```

**Riesgos**:
- IM-1: el problema SQLite/MySQL puede ser más profundo de lo estimado. Si tarda más de 2 d-h, abortar y usar Opción B (MySQL en CI).
- IM-8: requiere agregar locale a la sesión del usuario. Si el sistema no tiene i18n base, este es un rabbit hole.

**Commit**: `chore(polish): Sprint 4 - tests integración, CREDENTIALS, multi-idioma (IM-1, IM-2, IM-8)`.

---

## 5. Estimación total

| Sprint | d-h | Acumulado |
|---|---|---|
| 0 — Funcionalidad rota | 0.5 | 0.5 |
| 1 — Configuración y DX | 1.0 | 1.5 |
| 2 — Deuda técnica | 4.0 | 5.5 |
| 3 — Calidad de catálogo | 9.0 | 14.5 |
| 4 — Pulido y consistencia | 4.0 | 18.5 |
| **Total** | **18.5 d-h** | — |

**Core (Sprints 0-2)**: ~5.5 días-hombre = ~1.5 semanas calendario a ritmo de capstone. **Recomendado antes de cualquier deploy a producción.**

**Full (Sprints 0-4)**: 18.5 días-hombre = ~5 semanas calendario a ritmo de capstone.

---

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| NF-1 Opción A devuelve 501, el front espera 200 | Alta | Medio | Auditar `grep -rn "/api/reminders" resources/js/` antes. Si hay consumidores, usar Opción B. |
| NF-2 eliminar `event(new ...)` rompe algo no documentado | Media | Alto | NO eliminar la clase del evento. Marcar listener con `@deprecated` y `Log::warning` en `event()` para no perder la señal. |
| NF-3 implementación de `pay()` rompe transacciones reales | Media | Alto | Hacer la implementación detrás de `feature flag` (`config('features.payments_v2')`). Activar solo cuando se haya testeado. |
| NF-4 envío de email en dev con MAIL_MAILER=log satura el log | Baja | Bajo | Configurar `LOG_CHANNEL=mail` separado. |
| NF-5 broadcast por Reverb sin config de broadcasting | Media | Medio | Verificar `config/broadcasting.php` y `.env` BROADCAST_CONNECTION=reverb antes de implementar. |
| DM-2 reescritura de AGENTS.md lleva más de 1 d-h | Alta | Bajo | Hacer un PR parcial primero con quickstart + stack, luego iterar. |
| DM-4 `StoreQuotationRequest` romper path `generateQuotation` | Alta | Alto | Refactor mínimo: agregar `patient_id` nullable. No tocar lógica del controller. |
| DM-6 eliminar `users.specialty` rompe queries de admin | Media | Alto | Auditar con `grep -rn "->specialty" app/ resources/js/ database/` antes de tocar. |
| DM-7 drop de `legacy_specialty` rompe BI | Media | Alto | Idem: auditoría previa. Si BI lo usa, crear vista materializada. |
| DM-8 CI falla por IM-1 no arreglado | Alta | Medio | Workaround temporal: `continue-on-error: true` en el job de tests. Arreglar IM-1 en Sprint 4. |
| IM-3 tests del catálogo no son reales por IM-1 | Alta | Bajo | Aceptar que IM-3 son tests estructurales hasta que IM-1 se arregle. |
| IM-7 versionado requiere backfill costoso | Media | Medio | Hacer el backfill en la misma migración. Si son +10K procedimientos, hacerlo en un comando Artisan batch con `--chunk=500`. |

---

## 7. Out of scope (explícitamente)

- ❌ Reescribir el sistema de eventos completo para usar un event bus (RabbitMQ, Kafka, etc.) — los eventos actuales con `try/catch` son suficientes para el alcance del capstone.
- ❌ Implementar la lógica de negocio de recordatorios (CRUD real, scheduler, envío por email/SMS) — solo se ataca el 501 de NF-1, no el feature completo.
- ❌ Refactor del sistema de autenticación (migrar de Sanctum a Passport, agregar OAuth, etc.) — fuera del alcance del proyecto.
- ❌ Migración de la BD a PostgreSQL — el proyecto usa MySQL y los tests SQLite. Migrar es un proyecto en sí mismo.
- ❌ Internacionalización completa (i18n) del frontend — IM-8 solo cubre el catálogo de procedimientos.
- ❌ Implementar el feature de "adjuntar archivos a citas" (referenciado en `ClinicalAttachment` pero sin UI) — fuera del alcance de este plan.
- ❌ Migrar de Reverb a Pusher/Ably — Reverb funciona, el alcance es local.
- ❌ Auditoría de seguridad (penetration testing, OWASP) — fuera del alcance de un plan de mejoras de capstone.

---

## 8. Referencias cruzadas

- `plan-flujo-catalog-procedimientos.md` — 6 sprints ✅, 22 commits en `feat/procedure-catalog-master-data`. Este plan **NO duplica**:
  - Maestro de especialidades, favoritos, ProcedureQuickPicker, MyProceduresPage, ReceptionProceduresPage, ProcedureCatalogPage (todo Sprint 1-6 proc-cat, ya hecho).
  - FormRequests tipados para procedure-catalog (Sprint 6 proc-cat) — patrón replicable a DM-4 de aquí.
  - Migración de `procedure_catalog.legacy_specialty` parcial (Sprint 1 proc-cat) — DM-7 de aquí cierra el drop final.
  - Multi-sede en `procedure_catalog` — no aplica (catálogo global por decisión documentada).
  - 5 features out-of-scope (§8 proc-cat) — IM-4, IM-5, IM-6, IM-7, IM-8 de este plan los cierra.
- `plan-inconsistencias-2026-06-actualizado.md` — 6 sprints ✅, 22 hallazgos cerrados. Este plan **NO duplica**:
  - Multi-tenant (C-4 ya cerrado, todos los 6 controllers filtran por `branch_id`).
  - FormRequests migrados (I-2 cerró 5/8; DM-4 de aquí cierra los 3 que requieren refactor).
  - 8 composables muertos (I-6 ya cerrado).
  - 347 console.log eliminados (I-8 ya cerrado — IM-1 "useApi.js console.warn" del contexto original está desfasado, ya se limpió).
  - 15 modelos con SoftDeletes (M-1 ya cerrado).
  - Evento huérfano eliminado (M-5 — 1 evento; NF-2 de este plan ataca los 26 restantes).
- `AGENTS.md` — desactualizado (ver DM-2). Una vez reescrito en Sprint 1, debe referenciar este plan y los dos anteriores.

---

## 9. Próximos pasos

1. **Arnold valida este plan** (especialmente §3 hallazgos y §4 sprints 0-2).
2. **Sprint 0 arranca YA** — 30 minutos eliminan 6 500s/501s garantizados en `main`. No requiere ninguna decisión de arquitectura.
3. Un PR por sprint (mantener revisión manejable en capstone).
4. Sprint 2 (deuda técnica) **antes** de Sprint 3 (calidad de catálogo) — así IM-1 está base para IM-3.
5. Después de Sprint 4, decidir si se hace un deploy real o se sigue en local.

---

## 10. Changelog

- **2026-06-11** — Plan creado. 22 hallazgos nuevos identificados (6 críticos, 10 importantes, 6 mejoras). 5 sprints propuestos, 18.5 d-h estimados. Sprint 0 (0.5 d-h) es la prioridad #1.
