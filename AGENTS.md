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
  Http/Controllers/Api/    # 33 controllers API (32 en Api/ + 1 en Api/Reports/)
  Http/Middleware/         # CheckRole (alias 'role'), ThrottleLoginAttempts,
                           # RequireActiveCashSession (alias 'cash.session')
  Models/                  # 50 modelos Eloquent
  Services/                # 18 services + Reports/ (8 report services)
  Events/                  # 36 eventos (10 listener classes, 13 cableos en AppServiceProvider)
  Listeners/               # 10 listener classes; 13 cableos en AppServiceProvider::boot
  Mail/                    # PasswordResetMail (Mailable para forgot-password)
  Jobs/                    # ExportPatientFileJob, ClearDashboardCache

resources/js/
  app.js                   # entry + router (guards en router/auth.js)
  composables/             # 39 composables singleton
  components/
    ui/                    # 35 primitives (Button, Modal, DataTable, Toast, DentalMotifLayer, UiSectionEmpty, ...)
    layout/                # AppLayout, MobileMenu, FloatingActionButton
    procedures/            # ProcedureQuickPicker, ProcedureCatalogPicker, ImportCsvModal
  modules/                 # 18 módulos de dominio (ver §5)
  router/auth.js           # requireAuth, requireGuest (localStorage)
  design-system/tokens.js  # design tokens

database/
  migrations/              # 114 migraciones
  seeders/                 # 15 activos (RoleBasedUsersSeeder, BranchSeeder, PaymentMethodSeeder,
                           #   SpecialtySeeder, UserSpecialtySeeder, AppointmentTypeSeeder,
                           #   EnvironmentSeeder, ProcedureCatalogSeeder, PatientSeeder,
                           #   SimpleAppointmentsSeeder, ReminderSchedulesSeeder, CashRegisterSeeder,
                           #   CompletedAppointmentsSeeder, DentalPieceSeeder, SpecialtyRecordSeeder)

routes/
  api.php                  # 198 rutas API (medido con php artisan route:list --json: uris api/…; 211 totales con web)
  web.php                  # catch-all que retorna view('app')

tests/
  Unit/                    # tests estructurales (Services, Controllers, Events, Models, Middleware, Documentation)
  Feature/Api/             # AuthTest (rate limiting, login)
docs/
  mejoras/                 # 3 planes cerrados (ver §9)
  architecture/decisions/  # índice de ADRs (README.md); cuerpos 0007/0008 pendientes (#264)

.github/workflows/
  ci.yml                   # CI con 3 jobs: quality (lint), backend-tests (MySQL 8.0 service), frontend-build
```

---

## 5. Módulos del frontend (18 módulos, 20 páginas)

| Módulo                    | Ruta                        | Roles                            |
| ------------------------- | --------------------------- | -------------------------------- |
| Dashboard                 | `/dashboard`                | todos                            |
| Calendario                | `/calendar`                 | todos los clínicos               |
| Pacientes                 | `/patients`                 | todos                            |
| Profesionales             | `/professionals`            | admin                            |
| Ambientes                 | `/environments`             | admin                            |
| Tipos de cita             | `/appointment-types`        | admin                            |
| Especialidades (catálogo) | `/specialties`              | admin                            |
| Sucursales                | `/settings/branches`        | admin                            |
| Métodos de pago           | `/settings/payment-methods` | admin                            |
| Caja                      | `/cash-register`            | admin, finanzas, recep           |
| BI                        | `/business-intelligence`    | admin, finanzas                  |
| Planes de tratamiento     | `/treatment-plans`          | clínicos                         |
| Presupuestos              | `/quotations`               | admin, finanzas, odonto, implant |
| Historias clínicas        | `/medical-records`          | clínicos                         |
| Registros especialidad    | `/specialty-records`        | clínicos                         |
| Análisis IA               | `/ai-analysis`              | clínicos                         |
| Catálogo procedimientos   | `/procedure-catalog`        | admin                            |
| Mis procedimientos        | `/my-procedures`            | clínicos (favoritos)             |
| Recepción procedimientos  | `/reception-procedures`     | recep                            |
| Estadísticas catálogo     | `/procedure-stats`          | admin, finanzas                  |

**Auth frontend**: el router NO tiene `meta.roles`. La API rechaza con 403 y el
frontend muestra toast. El control de visibilidad es por `AppLayout` (computed
`navigation` con `useAuth().hasRole(...)`).

**API client**: siempre `useApi().request/get/post/...`. NO usar axios directo.

---

## 6. Estado del proyecto (actualizado 2026-10-21)

### ✅ Todo funcional (3 planes cerrados, 66 hallazgos resueltos)

- Auth Sanctum completo (login/logout/me/refresh/forgot/reset con email real vía
  MAIL_MAILER=log)
- 50 modelos, 33 controllers API, 36 eventos (10 listener classes / 13 cableos
  en AppServiceProvider)
- 15 modelos con SoftDeletes
- Multi-rol con middleware `role:`, `cash.session`
- Multi-sede parcial (filtros en 6 controllers)
- Calendario con FullCalendar, bloques, waiting list
- Caja completa (apertura/cierre, movimientos, arqueos, PDF)
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
- AGENTS.md sincronizado con el código (guard `scripts/audit/doc-drift.mjs`)
- `composer dev` usa `pnpm dev`
- CI/CD con GitHub Actions (3 jobs desacoplados: quality, backend-tests MySQL,
  frontend-build; ver §10 para qué bloquea y qué es aviso explícito)
- CREDENTIALS.md sincronizado (tests automáticos lo validan)

### ⚠️ Pendiente (cosas que NO se hicieron, documentadas formalmente)

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
  dominio están a nivel de campo: `User.php:125` (`specialty` legacy, ADR-0007)
  y `ProcedureCatalog.php:24` (`legacy_specialty`, ADR-0008).
- **Reminders con CRUD real, WaitingList eliminada.** `ReminderController` y
  `ReminderTemplateController` implementan CRUD completo desde el slice 03.
  `WaitingListController` no existe: fue eliminado en el slice 04 y sus
  endpoints devuelven 404 (guardado por `StubsRemovedEndpointsTest`).
- **`User::specialty` (string legacy)**: conservado como display denormalizado.
  Sprint 2 DM-6 lo deprecó formalmente, creó accessor `specialty_code`, eliminó
  cast JSON inexistente. Ver ADR-0007.
- **`procedure_catalog.legacy_specialty`**: conservado en BD por compatibilidad.
  Sprint 2 DM-7 lo deprecó formalmente, creó accessor `specialty_code`. Plan de
  drop documentado en ADR-0008.
- **3 FormRequests no migrables** (documentado en Sprint 5 del plan de
  inconsistencias): `StoreAppointmentRequest` (omite 4 campos inline),
  `StoreQuotationRequest` (requiere `patient_id` que rompe path
  `generateQuotation`), `StoreSpecialtyRecordRequest` (omite 14 campos inline).
  Requieren refactor del controller.

### 🔧 Notas de entorno

- El `.env` tiene `BROADCAST_CONNECTION=reverb`. Para tests locales sin servidor
  Reverb, usar `BROADCAST_CONNECTION=null` (ya configurado en `phpunit.xml`).

---

## 7. Convenciones de código

### Backend (PHP / Laravel)

- Namespaces: `App\Http\Controllers\Api`, `App\Services`, `App\Models`,
  `App\Http\Middleware`
- Autorización: `->middleware('role:rol1,rol2,...)` en `routes/api.php`. NO usar
  Spatie.
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
| `php artisan migrate:fresh --seed` falla                | Verificar conexión MySQL en `.env`. Seeders activos: 15.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
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

| ADR                                                                      | Contenido                                                                                                                   |
| ------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------- |
| `docs/architecture/decisions/0007-user-specialty-source-of-truth.md`     | `User::specialty` (string) es legacy, `user_specialties` (pivote) es source-of-truth. Accessor `specialty_code` sincroniza. |
| `docs/architecture/decisions/0008-procedure-catalog-legacy-specialty.md` | `procedure_catalog.legacy_specialty` es legacy, `specialty_id` (FK) es source-of-truth. Drop futuro con script de backfill. |

> **Estado**: los dos ADR de la tabla todavía no tienen cuerpo escrito en el
> repo: por ahora solo existe el índice `docs/architecture/decisions/README.md`,
> con esos enlaces pendientes. El trabajo está registrado en #264.

---

## 10. CI/CD

`.github/workflows/ci.yml` con 3 jobs desacoplados (ninguno bloquea a otro: un
lint en rojo nunca esconde si los tests pasan). El workflow es la fuente de
verdad; esta tabla lo resume sin repetir su contenido.

| Job              | Qué verifica                                                                                                                                       |
| ---------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `quality`        | Bloqueantes: PHP syntax, validación JSON, Pint (`vendor/bin/pint --test`), ESLint, Vitest smoke (`pnpm test:js`) y Prettier (`pnpm format:check`). |
| `backend-tests`  | MySQL 8.0 real como service: `php artisan migrate --force` + suite completa.                                                                       |
| `frontend-build` | `pnpm build` + upload del artifact.                                                                                                                |

**Triggers**: push a `main`/`fix/*`/`feat/*`/`chore/*`/`refactor/*` + PRs a
`main`.

---

## 11. Resumen ejecutivo

OdontoSuite es una app fullstack Laravel 12 + Vue 3 con 33 controllers API, 50
modelos, 7 roles, sistema de caja completo, BI, IA, multi-sede parcial y
broadcasting. Auth Sanctum con tokens bearer. Estado global via composables.
Stack maduro para capstone: los 3 planes de mejoras están cerrados (66 hallazgos
resueltos), CI/CD con GitHub Actions, y la suite PHP corre contra un motor MySQL
real (local y en CI): su tamaño no se cita aquí porque cambia con cada test. La
deuda restante (tests que tocan la BD y se saltan en SQLite local) está
documentada y no bloquea producción.

---

## 12. Changelog de AGENTS.md

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
