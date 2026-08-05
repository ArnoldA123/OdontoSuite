# AGENTS.md — OdontoSuite V2

> **Lee este archivo primero antes de tocar el proyecto.** Contiene quickstart, stack, comandos, estructura, convenciones, troubleshooting y planes cerrados. Actualizado al 2026-08-05 tras aplicar el slice 11 del change `bugfix-2026-08` (docs sync + polish).

---

## 1. Quickstart

```bash
# 1. Clonar
git clone <repo> && cd OdontoSuite

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

**Credenciales demo**: ver `CREDENTIALS.md`. Todos los usuarios con password `password123`, dominio `@test.com` (15 usuarios seedados por `RoleBasedUsersSeeder`). Tests automáticos validan que CREDENTIALS.md esté sincronizado con el seeder (`tests/Unit/Documentation/CredentialsDocumentationTest.php`).

---

## 2. Stack

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | Laravel | 12 |
| Frontend | Vue 3 (Composition API + `<script setup>`) | 3.3 |
| Build | Vite | 5 |
| Estilos | Tailwind 3 + design tokens | 3.3 |
| DB | MySQL / MariaDB | 8.0 / 10.3+ |
| Auth | Sanctum (bearer tokens) | 4 |
| Real-time | Reverb + Laravel Echo | 1.6 / 1.16 |
| Calendario | FullCalendar | 6 |
| Gráficos | Chart.js | 4 |
| PDF | DomPDF | 3 |
| Excel | maatwebsite/excel | 1.1 |
| Package manager | **pnpm** (NUNCA npm) | 11 |

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
  Http/Controllers/Api/    # 36 controllers API
  Http/Middleware/         # CheckRole (alias 'role'), ThrottleLoginAttempts,
                           # RequireActiveCashSession (alias 'cash.session')
  Models/                  # 47 modelos Eloquent
  Services/                # 18 services + Reports/ (8 report services)
  Events/                  # 33 eventos (10 con listener cableado + 21 con consumer WS, 2 sin dispatch)
  Listeners/               # 9 listener classes; 13 cableos en AppServiceProvider::boot
  Mail/                    # PasswordResetMail (Mailable para forgot-password)
  Jobs/                    # ExportPatientFileJob, ClearDashboardCache

resources/js/
  app.js                   # entry + router (guards en router/auth.js)
  composables/             # 23 composables singleton
  components/
    ui/                    # 30 primitives (Button, Modal, DataTable, Toast, ...)
    layout/                # AppLayout, MobileMenu, FloatingActionButton
    procedures/            # ProcedureQuickPicker, ProcedureCatalogPicker, ImportCsvModal
  modules/                 # 17 módulos de dominio (ver §5)
  router/auth.js           # requireAuth, requireGuest (localStorage)
  design-system/tokens.js  # design tokens

database/
  migrations/              # 98 migraciones
  seeders/                 # 13 activos (RoleBasedUsersSeeder, BranchSeeder, PaymentMethodSeeder,
                           #   SpecialtySeeder, AppointmentTypeSeeder, EnvironmentSeeder,
                           #   ProcedureCatalogSeeder, PatientSeeder, SimpleAppointmentsSeeder,
                           #   ReminderSchedulesSeeder, CashRegisterSeeder, CompletedAppointmentsSeeder,
                           #   SpecialtyRecordSeeder)
  seeders/_legacy/         # 24 legacy (no se ejecutan, ver README.md en esa carpeta)

routes/
  api.php                  # 148 rutas
  web.php                  # catch-all que retorna view('app')

tests/
  Unit/                    # tests estructurales (Services, Controllers, Events, Models, Middleware, Documentation)
  Feature/Api/             # AuthTest (rate limiting, login)
docs/
  mejoras/                 # 3 planes cerrados (ver §9)
  decisions/               # 2 ADRs (0007-user-specialty-source-of-truth, 0008-procedure-catalog-legacy-specialty)

.github/workflows/
  ci.yml                   # CI con 3 jobs: quality (lint), backend-tests (MySQL 8.0 service), frontend-build
```

---

## 5. Módulos del frontend (17)

| Módulo | Ruta | Roles |
|---|---|---|
| Dashboard | `/dashboard` | todos |
| Calendario | `/calendar` | todos los clínicos |
| Pacientes | `/patients` | todos |
| Profesionales | `/professionals` | admin |
| Ambientes | `/environments` | admin |
| Tipos de cita | `/appointment-types` | admin |
| Caja | `/cash-register` | admin, finanzas, recep |
| BI | `/business-intelligence` | admin, finanzas |
| Planes de tratamiento | `/treatment-plans` | clínicos |
| Presupuestos | `/quotations` | admin, finanzas, odonto, implant |
| Historias clínicas | `/medical-records` | clínicos |
| Registros especialidad | `/specialty-records` | clínicos |
| Análisis IA | `/ai-analysis` | clínicos |
| Catálogo procedimientos | `/procedure-catalog` | admin |
| Mis procedimientos | `/my-procedures` | clínicos (favoritos) |
| Recepción procedimientos | `/reception-procedures` | recep |
| Estadísticas catálogo | `/procedure-stats` | admin, finanzas (vía `/procedure-catalog`) |

**Auth frontend**: el router NO tiene `meta.roles`. La API rechaza con 403 y el frontend muestra toast. El control de visibilidad es por `AppLayout` (computed `navigation` con `useAuth().hasRole(...)`).

**API client**: siempre `useApi().request/get/post/...`. NO usar axios directo.

---

## 6. Estado del proyecto (actualizado 2026-06-11)

### ✅ Todo funcional (3 planes cerrados, 22 hallazgos resueltos)
- Auth Sanctum completo (login/logout/me/refresh/forgot/reset con email real vía MAIL_MAILER=log)
- 47 modelos, 36 controllers, 33 eventos (9 listener classes / 13 cableos en AppServiceProvider)
- 15 modelos con SoftDeletes
- Multi-rol con middleware `role:`, `cash.session`
- Multi-sede parcial (filtros en 6 controllers)
- Calendario con FullCalendar, bloques, waiting list
- Caja completa (apertura/cierre, movimientos, arqueos, PDF)
- BI (8 report services) + export
- Catálogo de procedimientos con favoritos (admin/clínico/recep)
- Estadísticas del catálogo (`/api/admin/procedure-stats`)
- Importador CSV de procedimientos (`/api/admin/procedure-catalog/import`)
- Versionado del catálogo (tabla `procedure_catalog_versions` + tracking automático)
- Multi-idioma del catálogo (tabla `procedure_catalog_translations` + accessor `translate()`)
- `PendingPaymentsController@pay()` implementado (TransactionService + balance tracking)
- Branding migrado de EasyDent a OdontoSuite
- AGENTS.md actualizado (236 líneas)
- `composer dev` usa `pnpm dev`
- CI/CD con GitHub Actions (3 jobs: quality, backend-tests MySQL, frontend-build)
- CREDENTIALS.md sincronizado (tests automáticos lo validan)

### ⚠️ Pendiente (cosas que NO se hicieron, documentadas formalmente)
- **28 tests preexistentes** fallan por `MODIFY COLUMN` en SQLite local. En CI con MySQL (ya configurado) pasan. **Workaround local**: levantar MySQL vía `docker compose up -d mysql` y correr `php artisan test --group=mysql` (los tests afectados están anotados con `@group mysql` en el docblock). Ver `phpunit.xml` para `BROADCAST_CONNECTION=null` que resuelve el TypeError de Pusher en tests.
- **26 eventos huérfanos** marcados con `@deprecated` (no tienen listener). Solo los 10 que necesitan listener activo lo tienen. Los 26 se mantienen por si se cablean en el futuro.
- **`ReminderController` y `ReminderTemplateController`**: stubs vacíos que devuelven 501. Las rutas apiResource están activas pero los métodos no implementan CRUD. `WaitingListController::update()` y `destroy()` también 501.
- **`User::specialty` (string legacy)**: conservado como display denormalizado. Sprint 2 DM-6 lo deprecó formalmente, creó accessor `specialty_code`, eliminó cast JSON inexistente. Ver ADR-0007.
- **`procedure_catalog.legacy_specialty`**: conservado en BD por compatibilidad. Sprint 2 DM-7 lo deprecó formalmente, creó accessor `specialty_code`. Plan de drop documentado en ADR-0008.
- **3 FormRequests no migrables** (documentado en Sprint 5 del plan de inconsistencias): `StoreAppointmentRequest` (omite 4 campos inline), `StoreQuotationRequest` (requiere `patient_id` que rompe path `generateQuotation`), `StoreSpecialtyRecordRequest` (omite 14 campos inline). Requieren refactor del controller.

### 🐛 Bugs conocidos
- `php artisan test` local: 28 fallidos por MODIFY COLUMN (preexistente, no es de código actual). En CI con MySQL pasan todos.
- El `.env` tiene `BROADCAST_CONNECTION=reverb`. Para tests locales sin servidor Reverb, usar `BROADCAST_CONNECTION=null` (ya configurado en `phpunit.xml`).

---

## 7. Convenciones de código

### Backend (PHP / Laravel)
- Namespaces: `App\Http\Controllers\Api`, `App\Services`, `App\Models`, `App\Http\Middleware`
- Autorización: `->middleware('role:rol1,rol2,...)` en `routes/api.php`. NO usar Spatie.
- Roles válidos: `administrador`, `recepcionista`, `odontologo`, `implantologo`, `tecnico_dental`, `asistente`, `finanzas`
- Respuestas JSON: `{data: ..., meta: {message: ...}}` (ver `AuthController@login`)
- Servicios: lógica de negocio en `app/Services/*Service.php`
- Eventos: al crear/actualizar/eliminar entidades, `event(new X(...))` envuelto en `try/catch` (M-2 fix)
- Validación: `Request->validate()` inline. FormRequests tipados para los 10 controllers que los usan.
- Multi-idioma catálogo: `$catalog->translate('en', 'name')` (Sprint 4 IM-8)

### Frontend (Vue 3)
- Composition API + `<script setup>` (preferido). NO Options API salvo componentes muy viejos.
- Naming: PascalCase `.vue`, camelCase composables (`useAuth.js`)
- Estado global: composables singleton (NO Pinia)
- Auth: `useAuth()` lee de `localStorage` (`auth_token`, `user`). En 401 limpia ambos.
- Rutas: lazy-loading con `() => import(...)` excepto `LoginPage` (eager)
- API: SIEMPRE `useApi().request/get/post/...`. NO axios directo.
- UI: consumir SIEMPRE de `components/ui/`. NO botones/modales ad-hoc.
- Estilos: Tailwind 3 + design tokens. NO CSS scoped salvo necesidad real.
- Iconos: `@heroicons/vue`
- TypeScript: NO usado. Todo es JS.
- WebSocket: `useWebSocketNotifications()` escucha canales y dispara toasts. `procedure-catalog` canal activo para eventos de catálogo.

### Estilo del usuario (Arnold)
- Código limpio y profesional. **NO comentarios pedagógicos** salvo pedido explícito.
- Default a explicaciones cortas y al grano.
- Idioma de trabajo: español (Perú). Código en inglés.
- **Pnpm siempre. NUNCA `npm install`.**
- Commits: prefijos semánticos (`feat/`, `fix/`, `chore/`, `refactor/`), sin emojis.
- Multi-branch, mensaje corto y descriptivo.

---

## 8. Troubleshooting

| Problema | Solución |
|---|---|
| `npm` o `yarn` reclamando | Usar `pnpm` exclusivamente. AGENTS.md §2. |
| `vite.config.js` no resuelve `@/` | Alias ya está configurado (M-3 fix). Si se rompe, revisar. |
| Tests fallan con `MODIFY COLUMN` | Solo en SQLite local. En CI con MySQL (ya configurado) pasan. `phpunit.xml` tiene `BROADCAST_CONNECTION=null`. Workaround local: `docker compose up -d mysql` + `php artisan test --group=mysql`. |
| Pusher TypeError en tests | `phpunit.xml` tiene `BROADCAST_CONNECTION=null` (ya configurado). Si falta, agregar `env name="BROADCAST_CONNECTION" value="null"`. |
| `composer dev` no levanta Vite | Verificar que el script usa `pnpm dev` (no `npm run dev`). Sprint 1 DM-1 fix. |
| Email no se envía | Verificar `MAIL_MAILER` en `.env`. Default `log` (escribe a `storage/logs/laravel.log`). Para producción: SMTP/SES. |
| `php artisan migrate:fresh --seed` falla | Verificar conexión MySQL en `.env`. Seeders activos: 11. Legacy: 24 (no se ejecutan). |
| Frontend no encuentra módulo | `pnpm install` y reiniciar `pnpm dev`. |
| WebSocket no conecta | Verificar `php artisan reverb:start` corriendo y `BROADCAST_CONNECTION=reverb` en `.env`. |
| CI falla por `MissingAppKeyException` | `phpunit.xml` ya tiene `APP_KEY` configurado (Sprint 4 IM-1 fix). Si falta, agregar. |
| `procedure_catalog.legacy_specialty` aparece en queries | Campo deprecado (ADR-0008). Usar `specialty_code` accessor o `$pc->specialty->code`. Drop futuro documentado. |

---

## 9. Planes cerrados (referencia)

| Plan | Sprints | Estado | Hallazgos |
|---|---|---|---|
| `docs/mejoras/plan-flujo-catalog-procedimientos.md` | 6 (1-6) | ✅ Cerrado 2026-06-10 | 22 commits en `feat/procedure-catalog-master-data` |
| `docs/mejoras/plan-inconsistencias-2026-06-actualizado.md` | 6 (0-5) | ✅ Cerrado 2026-06-11 | 22 hallazgos C-/I-/M- cerrados en `fix/inconsistencias-sprint-1-multi-tenant` |
| `docs/mejoras/plan-mejoras-futuras-2026-06.md` | 5 (0-4) | ✅ Cerrado 2026-06-11 | 22 hallazgos NF-/DM-/IM- cerrados (0→4). Total: 18.5 d-h |

> **Estado**: los 3 planes están cerrados. La deuda documentada en `docs/mejoras/` está toda resuelta o formalmente deprecada con ADRs. El proyecto está listo para deploy.

### Decisiones de diseño (ADRs)

| ADR | Contenido |
|---|---|
| `docs/decisions/0007-user-specialty-source-of-truth.md` | `User::specialty` (string) es legacy, `user_specialties` (pivote) es source-of-truth. Accessor `specialty_code` sincroniza. |
| `docs/decisions/0008-procedure-catalog-legacy-specialty.md` | `procedure_catalog.legacy_specialty` es legacy, `specialty_id` (FK) es source-of-truth. Drop futuro con script de backfill. |

---

## 10. CI/CD

`.github/workflows/ci.yml` con 3 jobs:

| Job | Runner | Qué hace |
|---|---|---|
| `quality` | ubuntu-latest | PHP syntax, JSON validation, Pint, ESLint, Prettier |
| `backend-tests` | ubuntu-latest + MySQL 8.0 service | `php artisan migrate --force`, `php artisan test` (suite completa, MySQL real) |
| `frontend-build` | ubuntu-latest | `pnpm build`, upload artifact |

**Triggers**: push a `main`/`fix/*`/`feat/*`/`chore/*`/`refactor/*` + PRs a `main`.

---

## 11. Resumen ejecutivo

OdontoSuite es una app fullstack Laravel 12 + Vue 3 con 36 controllers API, 47 modelos, 7 roles, sistema de caja completo, BI, IA, multi-sede parcial y broadcasting. Auth Sanctum con tokens bearer. Estado global via composables. Stack maduro para capstone: los 3 planes de mejoras están cerrados (66 hallazgos resueltos), CI/CD con GitHub Actions, 19 tests estructurales + 52 tests que pasan. La deuda restante (28 tests viejos SQLite, 26 eventos @deprecated, stubs 501) está documentada y no bloquea producción.

---

## 12. Changelog de AGENTS.md

- **2026-08-05 (Slice 11)** — Actualizado tras aplicar slice 11 de `bugfix-2026-08`. Datos: §4 lista ahora 13 seeders activos (antes 11 — añadidos `BranchSeeder` + `PaymentMethodSeeder`), §4 listeners corregido a "9 listener classes, 13 cableos" (antes "7 listeners"), §6 añade workaround SQLite (`docker compose up -d mysql` + `--group=mysql`), §12 changelog sincronizado. Tests: `tests/Unit/Documentation/AgentsDocsSyncTest.php` valida los 4 invariantes en CI.
- **2026-06-11 (Sprint 4)** — Actualizado tras cerrar el plan de mejoras futuras (5/5 sprints). Datos: 36 controllers, 47 modelos, 36 eventos, 7 listeners, 18 services, 98 migraciones, 148 rutas, 17 módulos, 24 pages, 19 tests. CI/CD con GitHub Actions + MySQL. Multi-idioma catálogo. 2 ADRs. Estado §6 refleja los 3 planes cerrados.
- **2026-06-11 (Sprint 1)** — Reescrito desde 428 → 236 líneas. Datos: 35 controllers, 45 modelos. Estructura reorganizada.
- **<fecha anterior>** — Versión desactualizada con worktrees inexistentes, Sprint 1-3 pendientes, imports rotos ya arreglados.
