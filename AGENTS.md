# AGENTS.md — OdontoSuite / EasyDent

> **Lee este archivo primero antes de tocar el proyecto.**
> Contiene la estructura, reglas, comandos, módulos, roles y convenciones para que cualquier agente de IA (Mavis, Claude, Codex, Cursor, Aider, etc.) pueda trabajar productivamente sin re-analizar todo el código.

---

## 1. Identidad del proyecto

| Campo | Valor |
|---|---|
| **Nombre** | OdontoSuite V2 (la vista blade sigue llamándose "EasyDent" — refactor pendiente) |
| **Tipo** | Sistema de gestión odontológica (clínica dental) — fullstack |
| **Propósito** | Doble: portafolio personal (Capstone UPN) + uso real en clínica dental |
| **Stack** | Laravel 11 (API) + Vue 3 (SPA) + Vite + Tailwind + FullCalendar + Chart.js + Sanctum + Reverb (WebSockets) |
| **DB** | MySQL/MariaDB (migraciones revisadas) |
| **Auth** | Sanctum bearer tokens (no cookies) |
| **Paquete JS** | **pnpm** (NO usar `npm` — preferencia del dueño) |
| **SO dev** | Windows + PowerShell (rutas con espacios, usar `-LiteralPath` en `Get-ChildItem`) |
| **Workspace actual** | `E:\UNIVERSIDAD PRIVADA DEL NORTE\UPN 10 CICLO\Capstone\Proyecto\OdontoSuiteV2\OdontoSuite\.worktrees\wt-0d413c15` (worktree git activo) |

---

## 2. Comandos esenciales

```powershell
# Backend (desde la raíz del proyecto)
php artisan migrate --seed          # levantar DB con datos demo
php artisan serve                   # API en http://127.0.0.1:8000
php artisan test                    # tests
php artisan route:list              # ver rutas registradas
php artisan tinker                  # REPL de Laravel

# Frontend (Vite)
pnpm install                        # dependencias
pnpm dev                            # vite dev server (HMR)
pnpm build                          # build producción
pnpm lint:check                     # eslint
pnpm format:check                   # prettier --check

# Broadcast (Reverb) — necesario para notificaciones en tiempo real
php artisan reverb:start            # servidor WebSocket
```

> **Importante:** el proyecto no tiene un `composer.json` con script "dev" que levante todo a la vez. Hay que abrir tres terminales: `php artisan serve`, `php artisan reverb:start`, `pnpm dev` (o configurar `concurrently` — pendiente).

---

## 3. Estructura de carpetas (lo que importa)

```
app/
  Http/
    Controllers/Api/        # 29 controladores API (Auth, Patient, Appointment, ...)
    Controllers/Api/Reports/ # ReportController (BI)
    Middleware/              # RoleMiddleware, ThrottleLoginAttempts, RequireActiveCashSession, CheckRole
  Models/                   # 41 modelos Eloquent
  Services/                 # 16 services + Services/Reports/ (7 services de BI)
  Jobs/                     # 2 jobs (ExportPatientFileJob, ClearDashboardCache)
  Listeners/                # listeners de los Events
  Events/                   # 33 eventos (PatientCreated, AppointmentUpdated, QuotationApproved, ...)
  Repositories/             # capa de repositorio
  Policies/                 # policies (algunas)

resources/
  js/
    app.js                  # entry point + router (sin auth guards aquí, están en router/auth.js)
    bootstrap.js
    components/
      ui/                   # 28+ primitives (Button, Input, Modal, DataTable, Card, Toast, ...)
      layout/               # AppLayout, MobileMenu, FloatingActionButton
      auth/                 # LoginCard
      appointments/         # NewAppointmentModal
    composables/            # 30+ composables (useAuth, useApi, usePatients, useCashRegister, ...)
    modules/                # 15 módulos de dominio (ver §6)
    design-system/tokens.js # design tokens
    plugins/ui-components.js # registro global de componentes UI
    router/auth.js          # requireAuth, requireGuest (lee localStorage)
  views/
    app.blade.php           # única vista (entry de la SPA)
    welcome.blade.php       # placeholder

routes/
  api.php                   # 349 líneas — TODAS las rutas API (incluye broadcasting/auth)
  web.php                   # catch-all que retorna view('app') (Vue Router se encarga del resto)
  channels.php              # canales broadcast
  console.php

database/
  migrations/               # 60+ migraciones
  seeders/                  # 25 seeders (DatabaseSeeder.php llama 8 de ellos)
```

---

## 4. Convenciones de código (reglas duras)

### Backend (PHP / Laravel)
- **Namespaces:** `App\Http\Controllers\Api`, `App\Services`, `App\Models`, `App\Http\Middleware`.
- **Autorización:** `RoleMiddleware` aplicado como `->middleware('role:rol1,rol2,...)'` en `routes/api.php`. NO usar Spatie (no instalado).
- **Roles válidos (string en `users.role`):** `administrador`, `recepcionista`, `odontologo`, `implantologo`, `tecnico_dental`, `asistente`, `finanzas`.
- **Respuestas JSON:** envuelven datos en `data` y mensajes en `meta.message`. Ver `AuthController@login` para el shape canónico.
- **Servicios:** la lógica de negocio vive en `app/Services/*Service.php` (no en controladores). Inyectar via constructor o facades.
- **Reportes:** cada reporte tiene su service dedicado en `app/Services/Reports/*ReportService.php`.
- **Eventos:** al crear/actualizar/eliminar entidades de dominio se dispara un Event (33 eventos). El listener lo loguea y dispara Jobs cuando aplica.
- **Validación:** `Request->validate()` con reglas inline. Para reglas complejas, ver `Reminders` y `MedicalRecords`.
- **Multi-sede:** hay `branches` y `branch_id` en `users`. Las migraciones `2025_10_24_202936_add_multi_sede_fields_to_existing_tables.php` lo agregaron a varias tablas.

### Frontend (Vue 3)
- **Componentes:** Composition API + `<script setup>` (preferido) o Composition API con `setup()` en módulos legacy. **NO Options API** salvo en componentes muy viejos.
- **Naming:** PascalCase en `.vue`, camelCase en composables (`useAuth.js`).
- **Estado global:** NO hay Pinia. Estado compartido via composables singleton (`useApi.js` exporta `token` y `user` como `ref()` a nivel de módulo).
- **Auth state:** `useAuth()` lee de `localStorage.getItem('auth_token')` y `localStorage.getItem('user')`. En 401 limpia ambos y redirige a `/login`.
- **Rutas:** lazy-loading con `() => import(...)` excepto `LoginPage` que es eager.
- **Roles en frontend:** el router NO tiene `meta.roles`. El control de visibilidad es binario (`requireAuth`) + filtrado en `AppLayout.vue` (computed `navigation`).
- **API client:** SIEMPRE `useApi().request(method, url, body)` o `useApi().get(url, {params})`. NO usar axios directo salvo en scripts puntuales.
- **UI primitives:** consumir SIEMPRE los componentes de `resources/js/components/ui/`. NO crear botones/modales/toasts ad-hoc.
- **Estilos:** Tailwind 3 + design tokens en `design-system/tokens.js`. NO escribir CSS scoped salvo necesidad real.
- **Iconos:** `@heroicons/vue`.
- **TypeScript:** **NO usado**. Todo es JavaScript.

### Estilo del usuario (Arnold)
- Código limpio y profesional por defecto — **NO agregar comentarios pedagógicos** salvo que lo pida explícitamente (proyectos de aprendizaje).
- Default a explicaciones cortas y al grano.
- Idioma de trabajo: español (Perú). El código en inglés.
- Pnpm siempre. Nunca `npm install`.

---

## 5. Sistema de autenticación

### Flujo login (Frontend)
1. Usuario abre `/` → redirige a `/login` (router redirect).
2. `LoginPage` pide `username` + `password` (+ opcional `remember`).
3. POST `/api/auth/login` con `{username, password, remember}`.
4. Si OK → recibe `{data: {user, token}, meta}` → guarda en `localStorage` (`auth_token` y `user`).
5. Redirige a `/dashboard`.

### Backend (`AuthController@login`)
- Rate limit personalizado: 3/minuto, bloqueo 10 min tras 5 errores (`throttle.login` middleware).
- `Auth::attempt(['username', 'password'])` (usa el campo `username`, NO email).
- Verifica `$user->is_active` (boolean); si false → 422 con mensaje "Tu cuenta ha sido desactivada".
- Crea token Sanctum `auth-token` y retorna datos del usuario (id, name, username, email, role).

### Logout
- `POST /api/auth/logout` (revoca el `currentAccessToken` en backend).
- Limpia `localStorage.auth_token` y `localStorage.user`.
- Redirige a `/login`.

### Recuperación de contraseña
- `POST /api/auth/forgot-password` → genera token, lo guarda en `password_reset_tokens` (hash).
- **TODO en backend:** envío real de email (no implementado, retorna mensaje genérico por seguridad).
- `POST /api/auth/reset-password` con `{token, email, password, password_confirmation}` → expira en 60 min, verifica hash, actualiza password, revoca todos los tokens.

### Guards de router
- `requireAuth` (en `resources/js/router/auth.js`) → si falta `auth_token` o `user` en localStorage, redirige a `/login`.
- `requireGuest` → si ya hay sesión, redirige a `/dashboard`.
- Los guards NO validan roles — la API rechaza con 403 y el frontend muestra mensaje en toast.

### Middleware backend `RoleMiddleware`
- Alias: `role:rol1,rol2,...`
- Si `Auth::user()->role` no está en la lista → 403 con `required_roles` y `user_role` en el body.
- Aplicado en `routes/api.php` para todos los recursos protegidos.

---

## 6. Módulos del sistema (funcionalidades operativas)

| Módulo | Ruta frontend | Roles permitidos | Endpoints API clave |
|---|---|---|---|
| **Dashboard** | `/dashboard` | todos | `GET /api/dashboard/{stats\|today\|upcoming}` |
| **Calendario** | `/calendar` | admin, recep, odonto, implant, técnico, asistente | `GET /api/calendar/{events\|availability}`, `apiResource appointments`, `appointment-blocks`, `work-schedules`, `waiting-lists`, `reminders`, `reminder-templates`, `audit-logs` |
| **Pacientes** | `/patients`, `/patients/:id` | todos | `apiResource patients` + `GET /api/patients/{id}/export` |
| **Profesionales** | `/professionals`, `/professionals/:id` | admin | `apiResource users` (con filtros) |
| **Ambientes (Sillones)** | `/environments`, `/environments/:id` | admin | `apiResource dental-chairs` |
| **Tipos de Cita** | `/appointment-types`, `/appointment-types/:id` | admin | `apiResource appointment-types` |
| **Caja** | `/cash-register` | admin, finanzas, recep | `apiResource payment-methods`, `transactions`, `cash-movements`, `cash-register-sessions` + `open/close/active/closure-report`, `cash-reports/{daily\|period}`, `pending-payments` |
| **Reportes BI** | `/business-intelligence` | admin, finanzas | `GET /api/reports/{dashboard\|appointments\|patients\|professionals\|revenue\|utilization}` + `/export` |
| **Planes de Tratamiento** | `/treatment-plans` | admin, odonto, implant, técnico | `apiResource treatment-plans` + `change-status`, `duplicate`, `add-item`, `remove-item` |
| **Presupuestos** | `/quotations` | admin, finanzas, odonto, implant | `apiResource quotations` + `approve`, `reject`, `downloadPDF`, `byPatient` |
| **Historias Clínicas** | `/medical-records` | admin, odonto, implant, técnico, asistente | `apiResource medical-records` + `evolutions`, `attachments`, `stats` |
| **Especialidades** | `/specialty-records` | admin, odonto, implant, técnico | `apiResource specialty-records` (ortodoncia, endodoncia, implantología, cirugía oral, rehabilitación) |
| **Odontogramas** | embebido en Historia Clínica | clínicos + admin | `apiResource odontograms` + `records` anidadas |
| **Análisis IA** | `/ai-analysis` | admin, odonto, implant, técnico | `POST /api/ai-analysis/upload-and-analyze`, `analyze`, `review`, `stats`, `pending` |
| **Interconsultas** | embebido en HC | clínicos | `apiResource interconsultations` + `respond`, `complete`, `my-interconsultations` |
| **Lista de Espera** | embebido en Calendario | clínicos + recep | `apiResource waiting-lists` |

---

## 7. Modelos y datos (resumen)

41 modelos Eloquent. Los más usados:

- **User** (con `username`, `role`, `specialty`, `is_active`, `branch_id`, `professional_license`, `specialties[]`, `commission_rate`)
- **Patient** (con `document_number`, `branch_id`, soft-delete-ready)
- **Appointment** (status enum: `scheduled|confirmed|in_progress|completed|cancelled|no_show`)
- **DentalChair** (equipment, status)
- **AppointmentType** (duración, color)
- **WorkSchedule** (horarios del profesional)
- **AppointmentBlock**, **AppointmentRecurrence** (bloqueo + recurrencia)
- **WaitingList**, **ReminderTemplate**, **ReminderSchedule**
- **ConfirmationToken** (tokens para confirmar citas por link)
- **AuditLog** (polimórfico, loguea cambios)
- **Branch** (multi-sede)
- **DentalPiece**, **ToothSurface**, **Odontogram**, **OdontogramRecord**
- **MedicalRecord**, **ClinicalEvolution**, **ClinicalAttachment**
- **Interconsultation**, **TreatmentPlan**, **TreatmentPlanItem**
- **Quotation**, **QuotationItem**, **QuotationApproval**
- **SpecialtyRecord** (polimórfico: `ImplantologyRecord`, `OrthodonticsRecord`, `EndodonticsRecord`, `RehabilitationRecord`, `OralSurgeryRecord`)
- **PaymentMethod**, **Transaction**, **PaymentPlan**, **Installment**
- **Receipt**, **CashRegisterSession**, **CashMovement**
- **ProductCategory**, **Product**, **StockMovement**, **Supplier**, **PurchaseOrder**
- **ProcedureMaterial**, **ProcedureCatalog**, **DiagnosisCatalog**, **MedicationCatalog**
- **AiImageAnalysis** (resultados del análisis IA de imágenes clínicas)

---

## 8. Seeders vigentes (usados por `DatabaseSeeder.php`)

```php
$this->call([
    RoleBasedUsersSeeder::class,      // 15 usuarios con password 'password123', dominio @test.com
    AppointmentTypeSeeder::class,     // tipos de cita demo
    EnvironmentSeeder::class,         // sillones
    PatientSeeder::class,             // 100 pacientes
    SimpleAppointmentsSeeder::class,  // 100 citas
    ReminderSchedulesSeeder::class,
    CashRegisterSeeder::class,
    CompletedAppointmentsSeeder::class,
    SpecialtyRecordSeeder::class,
]);
```

### Credenciales demo (15 usuarios)
Todos con password `password123`. Emails con formato `<username>@test.com` (p. ej. `admin_test@test.com`).

Roles representados (3 admins, 1 recep, 3 odontólogos, 2 implantólogos, 2 técnicos, 2 asistentes, 2 finanzas).

> Hay también `EssentialUsersSeeder` (legacy, NO usado por `DatabaseSeeder`): 3 usuarios con password `password`, dominio `@odontosuite.com`. Útil como fallback mínimo: `admin@odontosuite.com`, `recepcionista@odontosuite.com`, `odontologo@odontosuite.com`.

### Seeders legacy NO usar
- `AdminUserSeeder`, `ReceptionUserSeeder`, `DentistUserSeeder` → dominio `@easydent.com` y roles `admin`/`recepcion` (antiguos). Están desfasados.

---

## 9. Servicios backend clave (para invocar lógica de negocio)

```
app/Services/
  AppointmentService           # lógica de creación/validación de citas
  CalendarService              # eventos y disponibilidad
  PatientExportService         # export a Excel
  CashRegisterService          # apertura/cierre de caja, arqueos
  TransactionService           # transacciones financieras
  QuotationService             # generación de presupuestos + PDF
  TreatmentPlanService         # cambio de estado, items
  SpecialtyRecordService       # registros por especialidad
  MedicalRecordService         # HC + evoluciones
  ClinicalAttachmentService    # subida/gestión de adjuntos
  ReminderService              # envío de recordatorios
  WaitingListService           # gestión de lista de espera
  AiImageAnalysisService       # integración con IA
  CacheService                 # wrapper de cache
  Reports/
    DashboardReportService     # métricas del dashboard
    AppointmentReportService   # reportes de citas
    PatientReportService       # reportes de pacientes
    ProfessionalReportService  # reportes por profesional
    RevenueReportService       # ingresos
    CashReportService          # caja
    UtilizationReportService   # utilización de sillones
```

---

## 10. Composables Vue clave (cómo se usa el frontend)

| Composable | Para qué |
|---|---|
| `useApi()` | base de fetch + token bearer + manejo 401 |
| `useAuth()` | login/logout/me/hasRole/hasAnyRole |
| `useApiWithLoading()` | wrapper con `isLoading` y `error` |
| `useLoading()` | estados de carga globales |
| `useToast()` | notificaciones toast |
| `useNotifications()` | centro de notificaciones |
| `useWebSocketNotifications()` | notificaciones vía Reverb/Echo |
| `usePagination()` | paginación reusable |
| `usePermissions()` | check de permisos client-side |
| `useAccessibility()` | helpers a11y |
| `useDropdownPosition()` | posicionamiento de dropdowns |
| `useZIndex()` | gestión de z-index |
| `useErrorHandler()` | manejo centralizado de errores |
| `useAuditLogs()`, `useMedicalRecords()`, `useInterconsultations()`, `useTreatmentPlans()`, `useQuotations()`, `useSpecialtyRecords()`, `useTransactions()`, `useCashRegister()`, `useAiAnalysis()`, `useExport()` | composables de dominio |

> **Patrón:** cada composable de dominio es un módulo singleton (estado compartido via `ref()` a nivel de módulo). NO crea instancias nuevas por componente.

---

## 11. Estado del proyecto (lo que funciona y lo que falta)

### ✅ Funcional y verificado
- Auth completo (login/logout/me/refresh/forgot/reset) con Sanctum + rate limit
- Multi-rol con middleware `role:` + control de UI por `AppLayout`
- CRUD completo de las 29 entidades
- Calendario con FullCalendar (eventos, disponibilidad, bloques)
- Sistema de caja (apertura/cierre, movimientos, arqueos, reportes PDF)
- Reportes BI (6 tipos) + export
- 33 eventos + listeners
- Reverb + Laravel Echo (notificaciones en tiempo real)
- Análisis IA (subida + análisis de imágenes clínicas)
- Odontogramas interactivos
- Historias clínicas con evoluciones + adjuntos
- 5 especialidades: implantología, ortodoncia, endodoncia, rehabilitación, cirugía oral
- Interconsultas
- Presupuestos con PDF
- Planes de tratamiento con estados e items
- 60+ migraciones
- Auditoría completa

### ⚠️ Incompleto / pendiente
- **Email real:** `forgotPassword` no envía email, solo guarda el token. Necesita integrar `Mail` driver (log/smtp).
- **composer.json dev script:** no hay script unificado para levantar API + Reverb + Vite juntos. Hay que abrir 3 terminales.
- **Tests:** estructura de tests existe (`tests/`) pero la cobertura no está mapeada. Revisar `phpunit.xml`.
- **TypeScript:** todo el frontend es JS, sin tipos.
- **Pinia:** estado global via composables, no escalará bien si crece.
- **Vista blade:** sigue llamándose "EasyDent" (mismatch con el nombre "OdontoSuite").
- **Seeders legacy:** `AdminUserSeeder`/`ReceptionUserSeeder`/`DentistUserSeeder` desfasados — dejarlos o eliminarlos.

### 🐛 Bugs conocidos (observados)
- En `AppLayout.vue` línea 335: `import { useAuth } from '../../composables/useApi'` — el path real es `composables/useAuth`. Probablemente roto (validar antes de usar).
- Codificación: en varios seeders/controllers se ven caracteres corruptos (`�`) que son artefactos de la lectura PowerShell — verificar encoding UTF-8 en archivos PHP.
- `useApi.js`: imprime `console.warn('No authentication token available')` en cada request sin token (puede ensuciar consola en login público).

---

## 12. Convenciones de git y trabajo

- **Worktree activo:** todas las operaciones de archivos se hacen dentro del worktree actual.
- **No hay CI/CD configurado** (no hay `.github/workflows`).
- **Branches:** usar prefijos semánticos (`feat/`, `fix/`, `chore/`, `refactor/`).
- **Commits:** el usuario prefiere mensajes cortos y descriptivos, sin emojis.

---

## 13. Comandos rápidos de descubrimiento

```powershell
# Rutas API agrupadas por middleware
php artisan route:list --path=api --columns=method,uri,middleware

# Ver qué roles accede a qué
Get-Content routes/api.php | Select-String "role:" 

# Contar pacientes
php artisan tinker --execute="echo \App\Models\Patient::count();"

# Limpiar caches
php artisan optimize:clear
```

---

## 14. Resumen ejecutivo de una línea

> OdontoSuite es una app fullstack Laravel 11 + Vue 3 con 29 controladores API, 41 modelos, 15 módulos Vue, 7 roles, sistema de caja completo, BI, IA, multi-sede y broadcasting. Auth Sanctum con tokens bearer. Estado global via composables. Stack maduro listo para capstone; falta consolidar naming (EasyDent → OdontoSuite), tests y envío real de email.

---

## 15. Skills del proyecto (declaradas con autoskills.sh)

Este proyecto tiene **7 skills curadas** instaladas con [`autoskills.sh`](https://autoskills.sh) de midudev y propagadas a Mavis como **agent-private** con prefijo `odontosuite-`.

### Skills activas (Mavis las carga automáticamente)

| Skill (en Mavis) | Fuente original | Para qué se usa en este proyecto |
|---|---|---|
| `odontosuite-vue` | antfu/skills | Referencia Vue 3, Composition API, `<script setup>`, reactivity |
| `odontosuite-vue-best-practices` | antfu/skills | Patrones Vue 3: async components, props/emit, animation |
| `odontosuite-vue-debug-guides` | hyf0/vue-skills | Debugging Vue (reactivity issues, devtools, perf) |
| `odontosuite-tailwind-css-patterns` | giuseppe-trisciuoglio/developer-kit | Patrones Tailwind 3, design system, responsive, a11y |
| `odontosuite-vite` | antfu/skills | Config de Vite 5+, plugins, HMR |
| `odontosuite-laravel-specialist` | jeffallan/claude-skills | Laravel 10+, Eloquent, Sanctum, queues, testing |
| `odontosuite-laravel-patterns` | affaan-m/everything-claude-code | Patrones Laravel: services, repositories, jobs, events |

### Doble ubicación (portabilidad + Mavis)

| Capa | Path | Propósito |
|---|---|---|
| **En el repo (portable)** | `E:\...\OdontoSuite\.worktrees\wt-0d413c15\.agents\skills\<nombre>\SKILL.md` | Para que cualquier IDE (Claude Code, Cursor, Codex, Windsurf, etc.) las detecte automáticamente |
| **En Mavis (efectiva)** | `C:\Users\chomb\.mavis\agents\mavis\skills\odontosuite-<nombre>\SKILL.md` | Lo que Mavis carga cuando trabaja en el proyecto |

> **Por qué doble ubicación:** Mavis NO escanea dinámicamente el workspace del proyecto — solo lee de sus directorios internos. Las copiamos a `~/.mavis/agents/mavis/skills/` con prefijo `odontosuite-` para que (a) Mavis las cargue y (b) sean identificables como de este proyecto (no contaminan otros futuros).

### Skills excluidas (curación, no instalación ciega)

Se detectaron 13 con `pnpm dlx autoskills --dry-run`, se instalaron las 13, y se descartaron 6:

| Excluida | Razón |
|---|---|
| `nodejs-backend-patterns` | El backend es PHP/Laravel, no Node.js |
| `nodejs-best-practices` | Idem |
| `seo` | Sistema interno, no sitio público |
| `php-pro` | ⚠️ Warning de seguridad de autoskills (credenciales hardcoded en ejemplos, broad shell commands) + duplicado con Mavis global `php-pro` y `laravel-expert` |
| `frontend-design` | Ya existe en Mavis global (`frontend-design` de anthropics) |
| `accessibility` | Ya existe en Mavis global (`accessibility-compliance-accessibility-audit` + `fixing-accessibility`) |

Las excluidas quedaron en `.agents/skills.disabled/` (NO en el path de Mavis) por si en el futuro se quieren reactivar. Ver `skills-lock.json` para el manifest completo con SHA-256 hashes.

### Cómo actualizar / regenerar

```powershell
# Ver qué recomendaría ahora
pnpm dlx autoskills --dry-run

# Actualizar a la última versión de las skills activas
pnpm dlx autoskills -y
# luego repetir el trim manual descrito arriba

# Si Mavis no las lista (cache de sesión)
mavis skill list mavis | Select-String odontosuite

# Re-cargar manualmente después de cambios en archivos
# (toma efecto en la SIGUIENTE sesión — no requiere reiniciar daemon)
```

### Por qué `.agents/skills/` y no `.harness/`

`autoskills` usa el formato **open de skills.sh** (universal, compatible con 50+ herramientas). `.harness/` es formato propietario de Mavis para definir teams de proyecto. Se podrían mover con `mavis skill install <path>` si se quisiera, pero perderíamos la portabilidad a otros IDEs.
