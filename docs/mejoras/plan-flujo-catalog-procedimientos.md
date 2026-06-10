# Plan de desarrollo — Flujo de catálogo de procedimientos / tratamientos

> **Fecha**: 2026-06-10
> **Alcance**: módulo transversal de **catálogo de procedimientos** (no confundir con `AppointmentType`).
> **Estado del proyecto al momento del plan**: ver `docs/mejoras/analisis-inconsistencias-2026-06.md` y `docs/mejoras/AUDITORIA_2026-06-09.md`. Este plan parte de lo que ya existe y **no duplica hallazgos previos**; solo los referencia cuando hay dependencia.

---

## 1. Contexto y problema

Hoy en OdontoSuite conviven **dos catálogos paralelos** para representar "qué hace la clínica":

| Catálogo | Tabla | Modelo | Backend | Frontend | Quién lo edita |
|---|---|---|---|---|---|
| **Tipos de cita** | `appointment_types` | `AppointmentType` | `AppointmentTypeController` (apiResource) | `resources/js/modules/appointment-types/` | Admin (`role:administrador` en `routes/api.php:224`) |
| **Catálogo de procedimientos** | `procedure_catalog` | `ProcedureCatalog` | `ProcedureCatalogController` (rutas planas en `routes/api.php:260-267`) | ❌ **no existe módulo Vue** | Admin (rutas protegidas con `role:administrador`) |

**Y además**, los procedimientos se reutilizan (sin un flujo claro de UI) en:

- `treatment_plan_items` (FK `procedure_catalog_id`, migración `2026_06_08_100100`) — al armar un plan de tratamiento.
- `appointments` (campo `procedure_id` que la auditoría C-3 señala como faltante en `$fillable`) — al registrar una cita, *en teoría*.
- `specialty_records` — al documentar el procedimiento realizado.
- `quotations` / `quotation_items` — vía `BillingService` y `QuotationService` (la auditoría C-1 ya marcó que la lectura de campos estaba mal mapeada).

### El problema concreto

Arnold lo resume así en su consulta:

> *El catálogo de procedimientos lo ve el administrador únicamente para los cambios. Ahora un odontólogo o especialista no le importa si ve bueno aquí esto...*

Hay tres roles que tocar el catálogo y no está claro **qué puede hacer cada uno**:

1. **Administrador** — gestiona el catálogo (crear/editar/desactivar). Ya implementado a nivel API, sin UI.
2. **Odontólogo / implantólogo / técnico** — al atender un paciente, **selecciona** procedimientos del catálogo (no los crea ni edita). Quiere ver pocos, los suyos. Hoy no tiene ni módulo de UI ni endpoint que devuelva "los míos".
3. **Recepción / finanzas** — ven los procedimientos (precios, duraciones) al agendar y al cobrar. Lectura.

El resultado actual es:

- ❌ **No hay UI para el catálogo de procedimientos** (ni admin, ni clínico). Solo API.
- ❌ No existe la noción de "**mis procedimientos favoritos**" ni "**procedimientos visibles para mi especialidad**".
- ❌ La API ya soporta `?specialty=` en `GET /api/procedure-catalog` (vía `scopeBySpecialty` en el modelo y filtro en `ProcedureCatalogService`) pero nadie lo está consumiendo desde la UI.
- ❌ El campo `specialty` en `procedure_catalog` es un `string(50)` libre (no FK), igual que en `User` (`specialty` y `specialties[]` como JSON). Esto invita a inconsistencias: "Ortodoncia" vs "ortodoncia" vs "ORTO".
- ❌ `User` ya tiene `specialties` (array JSON) y `specialty` (string simple). Se duplica la noción.
- ❌ El procedimiento no está conectado de forma explícita con la cita (FK `appointments.procedure_id` existe en la migración multi-sede pero NO en `$fillable` de `Appointment`, ver auditoría **C-3**).

---

## 2. Objetivo del plan

Definir y entregar un **flujo de procedimientos de extremo a extremo** que:

1. El **administrador** pueda crear/editar/desactivar procedimientos del catálogo desde la UI (hoy solo existe la API).
2. Cada procedimiento pertenezca a una **especialidad** del catálogo maestro (no string libre) y la lista de especialidades viva en un único lugar.
3. El **odontólogo/especialista** pueda:
   - Marcar procedimientos como **favoritos** (panel de acceso rápido).
   - Ver, en su pantalla de consulta, **solo los procedimientos compatibles con su perfil/especialidad** + sus favoritos (sin tener que buscar entre 40+ items).
   - **Seleccionar** un procedimiento al crear una cita o al armar un plan de tratamiento, sin poder crearlo ni editarlo.
4. Al registrar una **cita**, el procedimiento quede persistido (FK) y al odontograma/evolución se autocomplete con los defaults del catálogo (duración, materiales, requiere anestesia).
5. La lectura para **recepción/finanzas** siga funcionando como hasta ahora, pero con datos consistentes.

---

## 3. Estado actual (mapeo de lo que ya tenemos)

Para no duplicar trabajo ni contradecir la auditoría:

### 3.1 Backend ya hecho y reutilizable

- `App\Models\ProcedureCatalog` (campos completos, scope `active`, `bySpecialty`, helper `applyDefaultsToItem`).
- `App\Services\ProcedureCatalogService` (CRUD, paginación, búsqueda, lista activa).
- `App\Http\Controllers\Api\ProcedureCatalogController` (7 endpoints, validación, audit log).
- `App\Http\Resources\ProcedureCatalogResource`.
- Rutas API: `routes/api.php:260-267` (lectura abierta a autenticados, escritura `role:administrador`).
- Seeder: `database/seeders/ProcedureCatalogSeeder.php` con ~40 procedimientos demo (8 especialidades: `general`, `rehabilitacion`, `endodoncia`, `cirugia_oral`, `implantologia`, `ortodoncia`, `estetica`, `periodoncia`).
- Relación `treatment_plan_items.procedure_catalog_id` (migración `2026_06_08_100100`).

### 3.2 Pendientes heredados que este plan **asume arreglados** (ver auditoría)

- **C-3 (auditoría)**: agregar a `Appointment::$fillable`: `procedure_id`, `branch_id`, `total_cost`, `paid_amount`, `balance`, `requires_payment`, `specialty`, `requires_anesthesia`, `treatment_plan_item_id`, `origin_appointment_id`, `last_activity_at`. Sin esto, registrar el procedimiento en la cita no es posible de forma fiable.
- **C-4 (auditoría)**: filtrar `procedure_catalog` y `appointments` por `branch_id` en los controllers. (Multi-sede).
- **I-2 / I-7 (auditoría)**: tipar los `FormRequest` huérfanos y añadir `sometimes` a `nullable` para que el frontend pueda omitir campos opcionales sin 422.

> Estos fixes no son parte de este plan; deben estar mergeados **antes** de la fase 3 de aquí. Si no lo están, la fase 3 los incluye como sub-tarea.

### 3.3 Lo que NO existe y este plan introduce

- ❌ UI admin de catálogo de procedimientos.
- ❌ UI de selección rápida de procedimiento para clínico.
- ❌ Modelo `Specialty` (maestro de especialidades). Hoy la especialidad es string libre en `procedure_catalog.specialty` y `users.specialty`/`users.specialties[]`.
- ❌ Tabla pivote `user_favorite_procedures` (favoritos por usuario).
- ❌ Endpoint `GET /api/procedure-catalog/for-me` (devuelve procedimientos del usuario según su perfil/especialidad + favoritos).
- ❌ Endpoint `POST/DELETE /api/procedure-catalog/{id}/favorite` (toggle favorito).
- ❌ Sincronización de `Appointment` con el procedimiento seleccionado al crearse la cita.

---

## 4. Decisiones de diseño

### 4.1 ¿`AppointmentType` vs `ProcedureCatalog`? ¿Los fusionamos?

**No.** Sirven para cosas distintas y la auditoría no lo recomienda:

- `AppointmentType` = **plantilla de agenda** (color, duración por defecto, si requiere confirmación, si bloquea sillón, modo consulta). Es lo que la recepcionista ve al *agendar*. Es 1 fila por "tipo de evento" (Consulta, Control, Urgencia, Instalación de brackets).
- `ProcedureCatalog` = **catálogo clínico** (código, descripción clínica, materiales, contraindicaciones, cuidados post-procedimiento, default_cost). Es 1 fila por *procedimiento clínico facturable* (Endodoncia unirradicular, Corona zirconio, Implante unitario).

Se mantienen ambos, pero se **acopla explícitamente**: al crear un `AppointmentType` se le ofrece opcionalmente un `default_procedure_catalog_id` para que, al agendar, el campo `appointments.procedure_id` se autocomplete.

### 4.2 Especialidades como catálogo (maestro)

Hoy `procedure_catalog.specialty` es `string(50)`. El seeder usa 8 valores. Vamos a:

1. Crear tabla `specialties` (`id`, `code`, `name`, `is_active`, `timestamps`).
2. Migrar `procedure_catalog.specialty` → FK `specialty_id` (nullable para retro-compat). Mantener columna vieja como `legacy_specialty` durante 1 sprint, luego drop.
3. Migrar `users.specialties[]` (JSON) → tabla pivote `user_specialties` (`user_id`, `specialty_id`, `is_primary`).
4. Dejar `users.specialty` (string simple legacy) deprecado con TODO, fuera del scope de este plan.

> **Justificación**: hoy "Ortodoncia" puede aparecer como `ortodoncia`, `ORTO`, `orthodontics`. Con maestro, una sola fuente de verdad.

### 4.3 Favoritos

- Nueva tabla `user_favorite_procedures` (`user_id`, `procedure_catalog_id`, `position` (1-12), `timestamps`).
- `position` define el orden del panel rápido. 12 slots es generoso (los odontólogos raramente tienen >8 favoritos).
- Toggle con `POST /api/procedure-catalog/{id}/favorite` y `DELETE /api/procedure-catalog/{id}/favorite`.
- Reordenamiento: `PUT /api/procedure-catalog/favorites/reorder` con array de IDs en el orden deseado.

### 4.4 ¿Qué ve cada rol? (matriz)

| Acción | Admin | Odontólogo/Implant/Técnico | Recepción | Finanzas | Asistente |
|---|---|---|---|---|---|
| Listar catálogo completo | ✅ | ✅ (solo lectura) | ✅ | ✅ | ✅ |
| Buscar/filtrar catálogo | ✅ | ✅ (filtrado por su especialidad + favoritos primero) | ✅ | ✅ | ✅ |
| Crear/editar/desactivar procedimiento | ✅ | ❌ (403) | ❌ | ❌ | ❌ |
| Marcar favorito | ❌ (no aplica) | ✅ | ❌ | ❌ | ❌ |
| Seleccionar procedimiento al agendar cita | ✅ | ✅ | ✅ | ❌ | ❌ |
| Ver/editar el catálogo maestro de especialidades | ✅ | ❌ | ❌ | ❌ | ❌ |

### 4.5 Endpoint "para mí" (`for-me`)

```
GET /api/procedure-catalog/for-me?specialty=ortodoncia
```

Lógica en el service:

1. Cargar `Auth::user()` y resolver sus `specialty_ids` desde `user_specialties`.
2. Si el query trae `specialty` y el usuario tiene esa especialidad → filtrar `procedure_catalog` por `specialty_id` (e ignorar favoritos).
3. Si NO trae `specialty`:
   - Traer procedimientos activos cuya `specialty_id` ∈ `user.specialty_ids` **+** los marcados como favoritos por el usuario, deduplicados, **favoritos primero** respetando `position`.
4. Aplicar `scopeActive` y `paginate(15)`.

Esto resuelve el comentario de Arnold: *"...puede seleccionar cuáles tratamientos quiere ver en su pantalla de acceso rápido para no buscar entre cientos de opciones"*.

---

## 5. Roadmap de implementación (sprints)

Estimaciones en **días-hombre** de Arnold (1 d-h ≈ 4-5 h reales considerando su contexto + capstone).

### Sprint 1 — Fundaciones de datos (1 d-h)

**Objetivo**: que los datos estén bien antes de tocar la UI.

- [ ] Migración: `create_specialties_table`.
- [ ] Migración: `add_specialty_id_to_procedure_catalog_table` (nullable, FK).
- [ ] Migración: `create_user_specialties_table` (pivote, con `is_primary`).
- [ ] Migración: `create_user_favorite_procedures_table` (pivote, con `position`).
- [ ] Migración: `add_default_procedure_catalog_id_to_appointment_types_table` (nullable FK).
- [ ] Seeder: `SpecialtySeeder` (las 8 del ProcedureCatalogSeeder + 1 general "Multidisciplinario").
- [ ] Actualizar `ProcedureCatalogSeeder` para usar los IDs de las specialties (lookup por code).
- [ ] Backfill script (en el seeder o comando): `UPDATE procedure_catalog SET specialty_id = (SELECT id FROM specialties WHERE code = procedure_catalog.legacy_specialty)` — ejecutable una vez y marcado como idempotente.
- [ ] Modelos: `Specialty`, actualizar `User` (relación `specialties()` belongsToMany, `favoriteProcedures()` belongsToMany con `withPivot('position')`).
- [ ] Actualizar `ProcedureCatalog` model: agregar `specialty()` BelongsTo, `scopeBySpecialty` reescrito para usar `specialty_id`.

**Verificación**:
```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\ProcedureCatalog::count() . ' / ' . App\Models\Specialty::count();"
# Esperado: 40 / 9
```

---

### Sprint 2 — Endpoints nuevos (1.5 d-h)

**Objetivo**: extender la API sin romper compatibilidad.

- [ ] `GET /api/procedure-catalog/for-me` → método nuevo en `ProcedureCatalogController`, lógica en `ProcedureCatalogService::forUser(User $user, array $filters)`.
- [ ] `POST /api/procedure-catalog/{id}/favorite` → `ProcedureCatalogFavoriteController@store`.
- [ ] `DELETE /api/procedure-catalog/{id}/favorite` → mismo controller `@destroy`.
- [ ] `PUT /api/procedure-catalog/favorites/reorder` → `@reorder` (recibe `{ids: [4, 12, 7, ...]}` y reescribe `position` en una sola transacción).
- [ ] `GET /api/specialties` (lectura abierta autenticados) + `GET /api/specialties/active` (helper).
- [ ] Actualizar `routes/api.php` (lectura abierta autenticados, **escritura admin**, favoritos solo roles clínicos).
- [ ] Fix auditoría C-3: añadir `procedure_id`, `branch_id`, `specialty`, `requires_anesthesia` a `Appointment::$fillable` (sin esto el flujo de "seleccionar al agendar" no se puede persistir).
- [ ] Fix auditoría C-4: filtro por `branch_id` en `ProcedureCatalogController@index` (multi-sede).
- [ ] Tipar `StoreProcedureCatalogRequest` y `UpdateProcedureCatalogRequest` (nuevos, hoy validación inline en el controller — auditoría I-2).

**Verificación**:
- `curl` con token admin → crear/editar/desactivar → 200/201/200.
- `curl` con token odontólogo → `GET /api/procedure-catalog/for-me` → 200 con sus favoritos primero.
- `curl` con token odontólogo → `POST /api/procedure-catalog` → **403**.
- `curl` con token recepcionista → `POST /api/procedure-catalog/{id}/favorite` → **403** (recepción no tiene favoritos).
- Tests: añadir a `tests/Feature/ProcedureCatalogTest.php` (no existe, lo creamos) los casos de roles, favoritos, for-me.

---

### Sprint 3 — UI Admin: gestión del catálogo (2 d-h)

**Objetivo**: pantalla `/procedure-catalog` para el administrador (no clínico, no recepción).

- [ ] Módulo Vue: `resources/js/modules/procedure-catalog/` con:
  - `ProcedureCatalogPage.vue` — DataTable (componente UI ya existe) con filtros por specialty y estado activo, búsqueda, paginación.
  - `ProcedureCatalogFormModal.vue` — crear / editar. Usa `UiModal` + `UiInput`/`UiSelect`/`UiTextarea`. Validación client-side con `useValidation`.
  - `ProcedureCatalogDetailPage.vue` — ver detalle + audit log.
- [ ] Rutas en `resources/js/router/`:
  - `/procedure-catalog` → `ProcedureCatalogPage.vue`
  - `/procedure-catalog/:id` → `ProcedureCatalogDetailPage.vue`
  - Lazy-loaded como el resto.
- [ ] Item en `AppLayout.vue` `navigation` (computed) → solo visible si `useAuth().hasRole('administrador')` (patrón ya existente).
- [ ] Composable: `useProcedureCatalog.js` (singleton) con `list`, `find`, `create`, `update`, `deactivate`, `activeList`, `search`, `forMe`, `addFavorite`, `removeFavorite`, `reorderFavorites`. Patrón: como `useQuotations.js`.
- [ ] Empty state, loading state, error state (con `useErrorHandler.js` y `useToast.js`).

**Verificación manual**:
- Login admin → ve módulo en sidebar → crear "Terapia con láser de baja potencia" → ver en lista → desactivar → no aparece en `GET /api/procedure-catalog/active` → reactivar → vuelve.
- Login odontólogo → `/procedure-catalog` → **404 o 403** (no debe existir en su nav, ni ser accesible por URL).

---

### Sprint 4 — UI Clínico: favoritos + selector en consulta (2.5 d-h)

**Objetivo**: que el odontólogo pueda gestionar favoritos y seleccionar procedimientos al atender.

- [ ] Módulo Vue: `resources/js/modules/my-procedures/` con:
  - `MyProceduresPage.vue` — perfil de procedimientos del usuario:
    - Sección "Mis favoritos" (grid 3-4 columnas de cards, drag para reordenar o botones ↑↓).
    - Sección "Explorar catálogo" (DataTable con filtro por specialty, búsqueda).
    - Click en estrella ⭐ para togglear favorito.
  - Ruta: `/my-procedures`.
  - Item en nav: solo roles `odontologo`, `implantologo`, `tecnico_dental`, `asistente` (clinical).
- [ ] Componente reutilizable: `resources/js/components/procedures/ProcedureQuickPicker.vue`:
  - Props: `modelValue` (procedure_catalog_id), `specialty` (opcional, para filtrar).
  - Muestra favoritos primero (chips con icono estrella), luego "otros de tu especialidad", luego buscador.
  - Emite `update:modelValue` y `select` con el objeto completo.
  - Usado en: `NewAppointmentModal`, `TreatmentPlanItemForm`, `SpecialtyRecordForm`.
- [ ] Integración en `NewAppointmentModal.vue`:
  - Reemplazar el select actual de tipo de procedimiento (si existe) por `ProcedureQuickPicker`.
  - Al seleccionar, autollenar: `duration_minutes`, `specialty`, `requires_anesthesia`, `total_cost` desde el catálogo.
- [ ] Integración en `TreatmentPlanItem` form (en el módulo `treatment-plans`): igual.

**Verificación manual**:
- Login odontólogo → `/my-procedures` → marcar 4 favoritos → reordenar (subir/bajar) → cerrar sesión.
- Re-login → los 4 favoritos persisten en el mismo orden.
- Login odontólogo → `/calendar` → "Nueva cita" → abrir modal → ve 4 favoritos como chips, luego buscador → al elegir "Endodoncia unirradicular" se rellenan duración (90 min) y costo (S/ 300) automáticamente.
- Login admin → `/calendar` → "Nueva cita" → ve ProcedureQuickPicker pero **sin** la sección "Mis favoritos" (recepción no tiene perfil de procedimientos).

---

### Sprint 5 — UI Recepción: catálogo en agenda + auditoría (1 d-h)

**Objetivo**: que recepcionista siga agendando con un selector amigable, y dejar la auditoría visible.

- [ ] En `NewAppointmentModal` (visto por recepcionista), el `ProcedureQuickPicker` debe funcionar en modo "solo lectura" (sin gestión de favoritos), pero con todos los procedimientos visibles (sin filtro de especialidad).
- [ ] Página `/procedure-catalog` (admin) debe mostrar pestaña "Historial" que cargue `GET /api/audit-logs/auditable/procedure_catalog/{id}` (verificar si existe el endpoint polimórfico en `AuditLogController`; si no, lo añadimos — debería ser 1 método genérico).
- [ ] Confirmar en `routes/api.php` que `/procedure-catalog` (lectura) sigue abierta a todos los autenticados (recepción/finanzas pueden consultarlo al cobrar).

**Verificación manual**:
- Login recepcionista → agendar cita para paciente X → seleccionar procedimiento → confirmar → la cita tiene `procedure_id` no nulo (verificar en BD).
- Login admin → ver detalle del procedimiento en `/procedure-catalog/:id` → ver audit log con el alta original.

---

### Sprint 6 — Hardening (1 d-h)

**Objetivo**: que no se rompa en producción y que la auditoría general siga limpia.

- [ ] Tests: `tests/Feature/ProcedureCatalogTest.php`, `tests/Feature/ProcedureFavoritesTest.php`, `tests/Feature/SpecialtyTest.php`. Cobertura mínima:
  - Auth/roles.
  - CRUD básico.
  - Favoritos: agregar, quitar, reordenar, idempotencia.
  - `for-me` con/sin favoritos, con/sin filtro de especialidad.
  - Multi-sede: `branch_id` filtra correctamente.
- [ ] Auditoría:
  - Eliminar los `console.log` que aparezcan en el nuevo código (auditoría I-8).
  - Verificar que `ProcedureCatalogService` envuelve los `event()` con `try/catch` si dispara eventos (auditoría M-2). Si no dispara eventos, no aplica.
  - Imports con `@/` alias, no relativos (auditoría M-4).
- [ ] Limpiar código muerto: si el viejo `User::$specialty` (string) ya no se usa en ningún controller/seed nuevo, marcar deprecation en el modelo con `@deprecated` y `@see App\Models\User::specialties()`.
- [ ] Verificar `bootstrap/app.php` registra `ProcedureCatalogFavoriteController` si la convención no es auto-resolve.
- [ ] Si se crean eventos (`ProcedureCatalogCreated`, `ProcedureCatalogUpdated`, `FavoriteAdded`, `FavoriteRemoved`), añadirlos a `app/Events/` con su listener que solo loguea (consistente con el patrón actual de 33 eventos).

**Verificación**:
```bash
php artisan test --filter=Procedure
pnpm lint:check
pnpm format:check
php artisan route:list --path=api/procedure-catalog
```

---

## 6. Estimación total

| Sprint | d-h | Acumulado |
|---|---|---|
| 1 — Datos | 1.0 | 1.0 |
| 2 — API | 1.5 | 2.5 |
| 3 — UI Admin | 2.0 | 4.5 |
| 4 — UI Clínico | 2.5 | 7.0 |
| 5 — UI Recepción + auditoría | 1.0 | 8.0 |
| 6 — Hardening | 1.0 | 9.0 |

**Total: ~9 días-hombre** = ~2 semanas calendario a ritmo de capstone (Arnold trabaja en esto a tiempo parcial).

---

## 7. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| El campo `appointments.procedure_id` choca con el fix de auditoría C-3 y genera conflictos de migración | Media | Alto | Hacer el fix de `$fillable` en una migración aparte previa a Sprint 2. Si C-3 ya está hecho, no hay problema. |
| `users.specialties[]` (JSON) está en uso por algún módulo/seeder que asuma string | Baja | Medio | Mantener cast `array` y accessor `getSpecialtiesListAttribute()` que devuelva array, no romper consumidores. No migrar datos a la pivote en este plan; la migración se hace por trigger al loguear. |
| Reordenamiento de favoritos con drag&drop en móvil es difícil | Media | Bajo | Sprint 4 empieza con botones ↑↓; drag&drop es nice-to-have en un PR aparte. |
| El catálogo puede crecer mucho (>100 procedimientos) y saturar la respuesta `for-me` | Baja | Medio | `paginate(15)` ya está. Añadir `?per_page=` configurable. |
| Recepcionista sin `specialty` ve "0 procedimientos" si se filtra por su (inexistente) specialty | Media | Bajo | El endpoint `for-me` solo filtra por specialty si el usuario tiene specialties asignadas. Si no tiene, devuelve todos los activos. |
| Auditoría C-1 (QuotationService con unit_price null) no está arreglada al llegar a Sprint 4 | Media | Alto | Bloqueador: sin el fix, los presupuestos de planes de tratamiento seguirán en S/ 0.00. Documentar la dependencia al inicio del Sprint 4. |

---

## 8. Out of scope (explícitamente)

- ❌ Versionado del catálogo (no es un requisito del capstone).
- ❌ Importador CSV de procedimientos (sería nice-to-have, no bloquea el flujo).
- ❌ Multi-idioma del catálogo.
- ❌ Refactor de `User::$specialty` (string legacy) → mantener deprecado.
- ❌ Notificaciones en tiempo real (Reverb) cuando se desactiva un procedimiento que está en uso en un plan de tratamiento — se documenta como idea futura en `docs/mejoras/`.
- ❌ Dashboard de uso de procedimientos (qué procedimientos se usan más, por profesional) — pertenece al módulo BI, plan aparte.

---

## 9. Referencias cruzadas

- `AGENTS.md` §6 (módulos y roles) — confirma que `procedure-catalog` no aparece aún como módulo frontend.
- `AGENTS.md` §7 — modelo `ProcedureCatalog` documentado.
- `docs/mejoras/analisis-inconsistencias-2026-06.md` — inconsistencias generales (no específicas de este flujo).
- `docs/mejoras/AUDITORIA_2026-06-09.md`:
  - **C-1** QuotationService unit_price null → **bloqueador** para Sprint 4.
  - **C-3** Appointment `$fillable` → **bloqueador** para Sprint 2.
  - **C-4** Multi-sede branch_id → **bloqueador** para Sprint 2.
  - **I-2** FormRequests huérfanos → relevante en Sprint 2 (creamos los nuevos).
  - **I-7** `sometimes|nullable` → aplicar en los nuevos FormRequests.
  - **M-2** eventos sin try/catch → aplicar si creamos eventos.
  - **M-4** alias `@/` → aplicar en todo el código nuevo.

---

## 10. Próximos pasos inmediatos

1. **Arnold valida el plan** (especialmente §4.4 matriz de roles y §5 sprints).
2. Abrir rama `feat/procedure-catalog-master-data` desde el worktree actual.
3. Ejecutar Sprint 1 (1 d-h, datos puros, sin tocar UI).
4. PR corto de Sprint 1 → revisión → merge.
5. Continuar con Sprint 2 en rama nueva `feat/procedure-catalog-api`.
6. Iterar Sprints 3-6 con un PR por sprint para mantener la revisión manejable (capstone, no producción).
