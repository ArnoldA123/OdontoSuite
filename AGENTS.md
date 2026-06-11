# AGENTS.md — OdontoSuite V2

> **Lee este archivo primero antes de tocar el proyecto.** Contiene el quickstart, stack, comandos, troubleshooting y referencias a los planes cerrados.

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

# 5. Levantar todo (API + Reverb + Vite + queue + logs en una sola terminal)
composer dev
# Equivale a: php artisan serve + php artisan reverb:start + queue:listen + pail + pnpm dev
```

**Credenciales demo**: ver `CREDENTIALS.md`. Todos los usuarios con password `password123`, dominio `@test.com` (15 usuarios seedados por `RoleBasedUsersSeeder`).

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
  Http/Controllers/Api/    # 35 controllers API
  Http/Middleware/         # CheckRole (alias 'role'), ThrottleLoginAttempts,
                           # RequireActiveCashSession (alias 'cash.session')
  Models/                  # 45 modelos Eloquent
  Services/                # 16 services + Reports/ (BI)
  Events/                  # 33 eventos (26 marcados @deprecated, ver §6)
  Listeners/               # 5 listeners cableados en AppServiceProvider

resources/js/
  app.js                   # entry + router (guards en router/auth.js)
  composables/             # 23 composables singleton
  components/
    ui/                    # 30+ primitives (Button, Modal, DataTable, Toast, ...)
    layout/                # AppLayout, MobileMenu, FloatingActionButton
  modules/                 # 18 módulos de dominio (ver §5)
  router/auth.js           # requireAuth, requireGuest (localStorage)
  design-system/tokens.js  # design tokens

database/
  migrations/              # 80+ migraciones
  seeders/                 # 11 activos
  seeders/_legacy/         # 23 legacy (ver §6)

routes/
  api.php                  # 145 rutas
  web.php                  # catch-all que retorna view('app')
```

---

## 5. Módulos del frontend

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

**Auth frontend**: el router NO tiene `meta.roles`. La API rechaza con 403 y el frontend muestra toast. El control de visibilidad es por `AppLayout` (computed `navigation` con `useAuth().hasRole(...)`).

**API client**: siempre `useApi().request/get/post/...`. NO usar axios directo.

---

## 6. Estado del proyecto

### ✅ Funcional
- Auth Sanctum completo (login/logout/me/refresh/forgot/reset)
- 45 modelos, 35 controllers, 15 eventos con listener, 11 seeders activos
- Multi-rol con middleware `role:`
- Calendario (FullCalendar)
- Caja completa (apertura/cierre, movimientos, arqueos, PDF)
- BI (6 reportes) + export
- Catálogo de procedimientos con favoritos (admin/clínico/recep)
- Multi-sede parcial (filtros en 6 controllers)
- 15 modelos con SoftDeletes
- Branding migrado de EasyDent a OdontoSuite

### ⚠️ Pendiente
- **Email real**: `MAIL_MAILER=log` por defecto (dev). Configurar SMTP en producción.
- **Tests**: 28 tests viejos fallan por `MODIFY COLUMN` (SQLite vs MySQL). Ver `plan-mejoras-futuras-2026-06.md` Sprint 4 (IM-1).
- **Eventos huérfanos**: 26 de 33 eventos no tienen listener. Marcados con `@deprecated`, Sprint 3 los implementa.
- **Algunos controllers en 501**: `ReminderController`, `ReminderTemplateController` y métodos `update/destroy` de `WaitingListController` devuelven 501 explícito (features pendientes).
- **3 FormRequests no migrables**: `StoreAppointmentRequest`, `StoreQuotationRequest`, `StoreSpecialtyRecordRequest`. Requieren refactor del controller (no solo type-hint). Ver Sprint 2 (DM-4).
- **Doble fuente de verdad especialidades**: `User::specialty` (string) vs `User::specialties[]` (JSON) vs `user_specialties` (pivote). Pendiente deprecar formalmente.
- **`procedure_catalog.legacy_specialty`**: doble fuente de verdad con `specialty_id` (FK). Pendiente drop.
- **Sin CI/CD**: no hay `.github/workflows`. Ver Sprint 2 (DM-8).

### 🐛 Bugs conocidos
- Tests preexistentes: 28 fallan por `MODIFY COLUMN` SQLite/MySQL (no relacionado con código actual).
- `AGENTS.md` se reescribió el 2026-06-11 (Sprint 1 DM-2). Si volvés a leerlo y está desactualizado, regenerar con el Sprint 1.

---

## 7. Convenciones de código

### Backend (PHP / Laravel)
- Namespaces: `App\Http\Controllers\Api`, `App\Services`, `App\Models`, `App\Http\Middleware`
- Autorización: `->middleware('role:rol1,rol2,...)` en `routes/api.php`. NO usar Spatie.
- Roles válidos: `administrador`, `recepcionista`, `odontologo`, `implantologo`, `tecnico_dental`, `asistente`, `finanzas`
- Respuestas JSON: `{data: ..., meta: {message: ...}}` (ver `AuthController@login`)
- Servicios: lógica de negocio en `app/Services/*Service.php`
- Eventos: al crear/actualizar/eliminar entidades, `event(new X(...))`. Si dispara broadcast, envolver en `try/catch` (M-2 fix).
- Validación: `Request->validate()` inline. FormRequests para casos complejos.

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
| Tests fallan con `MODIFY COLUMN` | Problema preexistente SQLite/MySQL. Ver Sprint 4 IM-1 del plan. |
| `composer dev` no levanta Vite | Verificar que el script usa `pnpm dev` (no `npm run dev`). Sprint 1 DM-1 fix. |
| Email no se envía | Verificar `MAIL_MAILER` en `.env`. Default `log` (escribe a `storage/logs/laravel.log`). |
| `php artisan migrate:fresh --seed` falla | Verificar conexión MySQL en `.env`. Seeders activos: 11. Legacy: 23 (no se ejecutan). |
| Frontend no encuentra módulo | `pnpm install` y reiniciar `pnpm dev`. |
| WebSocket no conecta | Verificar `php artisan reverb:start` corriendo y `BROADCAST_CONNECTION=reverb` en `.env`. |
| 500 en lugar de 501 | Bug conocido pre-Sprint 0. Si aparece, ver si el controller es un stub legacy. |

---

## 9. Planes cerrados (referencia)

| Plan | Sprints | Estado | Commits |
|---|---|---|---|
| `docs/mejoras/plan-flujo-catalog-procedimientos.md` | 6 (1-6) | ✅ Cerrado | `feat/procedure-catalog-master-data` (22 commits) |
| `docs/mejoras/plan-inconsistencias-2026-06-actualizado.md` | 6 (0-5) | ✅ Cerrado | `fix/inconsistencias-sprint-1-multi-tenant` mergeado a `main` |
| `docs/mejoras/plan-mejoras-futuras-2026-06.md` | 5 (0-4) | 🔵 En curso | Sprints 0 y 1 cerrados. Pendiente: 2, 3, 4. |

> **Workflow**: el plan activo es `plan-mejoras-futuras-2026-06.md`. Cada sprint se cierra con commit, push, y merge a `main` antes de arrancar el siguiente.

---

## 10. Resumen ejecutivo

OdontoSuite es una app fullstack Laravel 12 + Vue 3 con 35 controllers API, 45 modelos, 7 roles, sistema de caja completo, BI, IA, multi-sede parcial y broadcasting. Auth Sanctum con tokens bearer. Estado global via composables. Stack maduro listo para capstone; la deuda pendiente está documentada y priorizada en `plan-mejoras-futuras-2026-06.md`.

---

## 11. Changelog de AGENTS.md

- **2026-06-11** — Reescrito desde 428 → 150 líneas (Sprint 1 DM-2). Datos actualizados: 35 controllers, 45 modelos, 23 pages, 11 seeders activos, 23 legacy. Estado del proyecto refleja los 2 planes cerrados + Sprint 0 de mejoras futuras. Estructura reorganizada: quickstart, stack, comandos, estructura, módulos, estado, convenciones, troubleshooting, planes cerrados.
- **<fecha anterior>** — Versión desactualizada con worktrees inexistentes, "Sprint 1-3 pendientes" cuando ya estaban cerrados, import roto de `useAuth` ya arreglado, `console.warn` ya eliminado.
