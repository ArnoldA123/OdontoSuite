# U11 — Matriz de consistencia transversal (36 rutas × 12 ejes)

- **Revisión**: `5b1cc028c7e0fd66b25546ccf688910cf0679cd8`
- **Base**: http://localhost:8000
- **Fecha**: 2026-10-27T19:22:05.070Z
- **Generado por**: `tests/e2e/audit/u11/report.js` sobre `manifest.json`, `static.json`, `render/*.json` y `primitives/*.json`
- **Alcance del gate**: `AUDIT_SCOPE=all` (36 superficie(s) esperada(s))
- **Códigos**: `OK` canon · `DIV` divergencia · `N/A` no aplica · `ACE` aceptación declarada · `NO PROBADO` sin fila de render

## Matriz

| Ruta | Rol | Diálogo | Lista | Título | Botonera | Estados | Feedback | Filtros | Badges | Formato | Responsive | Densidad | Repetición |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `/login` | anonymous | OK | OK | OK | OK | ACE | OK | N/A | OK | N/A | OK | N/A | OK |
| `/reset-password` | anonymous | OK | OK | OK | OK | ACE | OK | N/A | OK | N/A | OK | N/A | OK |
| `/dashboard` | recepcionista | OK | OK | OK | OK | OK | DIV | N/A | OK | DIV | OK | N/A | OK |
| `/calendar` | recepcionista | DIV | OK | OK | DIV | OK | OK | N/A | OK | OK | OK | N/A | OK |
| `/my-day` | odontologo | DIV | OK | OK | DIV | OK | OK | N/A | OK | OK | OK | N/A | OK |
| `/patients` | recepcionista | OK | DIV | OK | OK | OK | DIV | N/A | OK | DIV | OK | N/A | OK |
| `/patients/:id` | recepcionista | OK | OK | OK | DIV | OK | OK | DIV | OK | DIV | OK | N/A | DIV |
| `/professionals` | administrador | OK | DIV | OK | DIV | DIV | OK | DIV | OK | N/A | OK | N/A | OK |
| `/professionals/:id` | administrador | N/A | OK | OK | OK | DIV | N/A | N/A | OK | OK | OK | N/A | OK |
| `/environments` | administrador | OK | OK | OK | DIV | OK | DIV | DIV | OK | N/A | OK | N/A | OK |
| `/environments/:id` | administrador | N/A | OK | OK | OK | OK | N/A | N/A | OK | OK | OK | N/A | DIV |
| `/appointment-types` | administrador | OK | DIV | OK | DIV | OK | DIV | N/A | OK | OK | OK | OK | OK |
| `/appointment-types/:id` | administrador | N/A | OK | OK | OK | DIV | DIV | N/A | OK | OK | OK | N/A | OK |
| `/specialties` | administrador | OK | OK | OK | OK | OK | OK | DIV | OK | N/A | OK | OK | OK |
| `/procedure-catalog` | administrador | OK | DIV | OK | DIV | OK | OK | N/A | OK | DIV | OK | OK | OK |
| `/procedure-catalog/:id` | administrador | OK | OK | OK | OK | DIV | OK | N/A | OK | OK | OK | N/A | OK |
| `/procedure-stats` | finanzas | N/A | OK | OK | OK | OK | OK | DIV | OK | DIV | OK | OK | OK |
| `/my-procedures` | odontologo | N/A | OK | OK | OK | OK | DIV | N/A | OK | OK | OK | OK | OK |
| `/reception-procedures` | recepcionista | N/A | OK | OK | OK | OK | N/A | DIV | OK | DIV | OK | OK | OK |
| `/business-intelligence` | finanzas | N/A | OK | OK | OK | OK | OK | DIV | OK | DIV | OK | OK | DIV |
| `/cash-register` | finanzas | OK | OK | OK | DIV | DIV | OK | OK | OK | DIV | OK | OK | OK |
| `/cash-register/ready-to-bill` | finanzas | OK | OK | OK | OK | DIV | DIV | OK | OK | OK | OK | OK | OK |
| `/treatment-plans` | odontologo | OK | OK | OK | DIV | OK | OK | DIV | OK | OK | OK | OK | OK |
| `/quotations` | finanzas | OK | OK | OK | DIV | OK | OK | DIV | OK | OK | OK | OK | OK |
| `/medical-records` | odontologo | OK | OK | OK | DIV | OK | OK | N/A | OK | DIV | OK | N/A | OK |
| `/medical-records/:id` | odontologo | OK | OK | OK | DIV | OK | OK | N/A | OK | DIV | OK | N/A | DIV |
| `/specialty-records` | odontologo | OK | OK | OK | OK | DIV | OK | N/A | OK | N/A | OK | OK | OK |
| `/ai-analysis` | odontologo | ACE | OK | OK | OK | OK | OK | DIV | DIV | OK | OK | OK | OK |
| `/continuity` | recepcionista | OK | OK | OK | OK | OK | OK | N/A | OK | OK | OK | N/A | OK |
| `/lab-cases` | recepcionista | OK | OK | OK | OK | OK | N/A | OK | OK | N/A | OK | N/A | OK |
| `/settings/branches` | administrador | OK | OK | OK | OK | OK | OK | DIV | OK | OK | OK | OK | OK |
| `/settings/branches/:id` | administrador | N/A | OK | OK | OK | OK | N/A | N/A | OK | OK | OK | OK | DIV |
| `/branch-transfers` | recepcionista | N/A | OK | OK | OK | OK | OK | N/A | DIV | OK | OK | N/A | OK |
| `/settings/payment-methods` | administrador | OK | OK | OK | OK | OK | OK | DIV | OK | N/A | OK | OK | OK |
| `/settings/accounting-locks` | administrador | OK | OK | OK | OK | OK | OK | N/A | OK | N/A | OK | OK | OK |
| `/:pathMatch(.*)*` | recepcionista | N/A | OK | DIV | OK | ACE | N/A | N/A | OK | N/A | OK | N/A | OK |

## Reglas de veredicto por eje

- `dialog`: `modal` en todas las rutas que abren diálogo → OK; `sheet` o overlay propio → DIV con la ruta y la primitiva; sin diálogo → N/A.
- `list`: `UiDataTable` o ninguna tabla → OK; `<table>` propio en la página de lista → DIV (una tabla dentro de un modal de detalle no decide el eje).
- `title`: exactamente un `h1` en el documento → OK; 0 o más de 1 → DIV.
- `buttons`: los disparadores de alta y los envíos dentro del vocabulario canónico (`Nuevo/Nueva <sustantivo>`, verbo propio por envío) → OK; `Crear`/`Agregar`/`Registrar` para un alta, o `Aceptar`/`Confirmar` sin objeto en un envío → DIV, con el label y la ruta. `Cancelar`/`Volver`/`Cerrar` no deciden el eje: dependen del contexto de la ventana.
- `states`: placeholder de carga observado (skeleton o spinner durante la carga) y vacío declarado cuando la ruta publica una lista → OK; falta cualquiera → DIV.
- `feedback`: inline (`:error`/`formErrors`) → OK; sólo toast en una ruta con escrituras → DIV; ruta de sólo lectura, sin canal → N/A.
- `filters`: sin controles ni acciones de filtro → N/A; `UiFilterBar` con reset → OK; controles o acciones sin reset, o sin `UiFilterBar` → DIV.
- `badges`: `UiStatusBadge` → OK; `UiStatusPill` (alias de dominio deprecado) o una insignia propia con `class*badge` → DIV.
- `format`: a lo sumo una forma de moneda y un helper de fecha compartido → OK; más de una forma de moneda, fecha formateada por página (`toLocale*`/`Intl.`), o el módulo de formato legacy para moneda → DIV. Sin moneda ni fechas renderizadas → N/A.
- `responsive`: sin desbordamiento de documento a 390 px → OK; con desbordamiento → DIV.
- `density`: `heroWords` ≤ 6 y `captionWords` ≤ 30 → OK; fuera → DIV; sin hero band → N/A (el título lo lleva el topbar).
- `repetition`: `count === 0` → OK; `> 0` → DIV.
- Convenciones: `N/A` declara que el eje no aplica a la ruta; `NO PROBADO` que no hay fila de render; `ACE` exige una entrada en `acceptances.json` (`{axis, route, reason}`) y el instrumento no la inventa.

## Veredicto por eje

### 1. Diálogo (`dialog`)

**Pregunta**: ¿El modal está centrado en todos los módulos? ¿Alguno abre al costado (Sheet) o con overlay propio?

**Canon propuesto**: `UiModal (Modal.vue)` — módulo de referencia `/settings/payment-methods`.

**Por qué**: Un solo lenguaje de ventana: centrado, foco atrapado, scroll bloqueado y Escape gestionados por la primitiva. El Sheet queda reservado al flujo largo que el canon declare.

**Rutas divergentes**: `/calendar` (overlay propio (role="dialog" o fixed inset-0)) · `/my-day` (overlay propio (role="dialog" o fixed inset-0))

**Aceptaciones declaradas**: `/ai-analysis` (resources/js/modules/ai-analysis/AiAnalysisPage.vue; primitivas=["modal","sheet"]; imports=["Modal","Sheet"]; medidos=[{"primitive":"modal","outcome":"measured…)

**Medido**: 26/36 ruta(s); OK 23, DIV 2, ACE 1, N/A 10, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #391 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 2. Lista (`list`)

**Pregunta**: ¿UiDataTable o <table> propio? ¿Misma paginación, mismo affordance, mismo vacío?

**Canon propuesto**: `UiDataTable (DataTable.vue) + Pagination` — módulo de referencia `/settings/payment-methods`.

**Por qué**: El scroll vive dentro de la tabla, con affordance, paginación y estados de carga/vacío compartidos.

**Rutas divergentes**: `/patients` (1 <table> propio(s) en la página) · `/professionals` (1 <table> propio(s) en la página) · `/appointment-types` (1 <table> propio(s) en la página) · `/procedure-catalog` (1 <table> propio(s) en la página)

**Medido**: 36/36 ruta(s); OK 32, DIV 4, ACE 0, N/A 0, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #453 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 3. Título (`title`)

**Pregunta**: ¿Un solo h1? ¿El topbar oculta el suyo donde la página ya titula?

**Canon propuesto**: `un <h1> del shell y <h2 data-hero-title> en la página` — módulo de referencia `/settings/accounting-locks`.

**Por qué**: El shell conserva el h1 del documento (visible o sr-only) y el hero de la página titula con h2.

**Rutas divergentes**: `/:pathMatch(.*)*` (2 h1 (topbar: topbar))

**Medido**: 36/36 ruta(s); OK 35, DIV 1, ACE 0, N/A 0, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #381 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 4. Botonera (`buttons`)

**Pregunta**: ¿Mismos verbos para la misma acción (Nuevo/Guardar/Cancelar/Eliminar)?

**Canon propuesto**: `UiButton + vocabulario “Nuevo/Nueva <sustantivo>” y un verbo propio por envío` — módulo de referencia `/patients`.

**Por qué**: Los mismos verbos para la misma acción evitan que el usuario reaprenda cada módulo.

**Rutas divergentes**: `/calendar` (verbos alternativos: Crear cita el a las, Registrar caso de laboratorio, Confirmar) · `/my-day` (verbos alternativos: Registrar caso de laboratorio) · `/patients/:id` (verbos alternativos: Registrar cargo, Crear Primer Plan, Crear Primer Presupuesto, Crear Primera Historia, Crear Primer Registro) · `/professionals` (verbos alternativos: Crear) · `/environments` (verbos alternativos: Crear ambiente) · `/appointment-types` (verbos alternativos: Crear) · `/procedure-catalog` (verbos alternativos: Agregar equivalencia, Crear procedimiento) · `/cash-register` (verbos alternativos: Registrar cobro, Registrar depósito, Registrar movimiento, Registrar Cobro) · `/treatment-plans` (verbos alternativos: Crear paciente, Agregar procedimiento, Crear Plan) · `/quotations` (verbos alternativos: Agregar procedimiento) · `/medical-records` (verbos alternativos: Registrar addendum, Registrar evolución, Agregar addendum, Agregar evolución, Crear historia) · `/medical-records/:id` (verbos alternativos: Registrar addendum, Registrar evolución, Agregar addendum, Agregar evolución, Crear historia)

**Medido**: 36/36 ruta(s); OK 24, DIV 12, ACE 0, N/A 0, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #455 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 5. Estados (`states`)

**Pregunta**: ¿Hay placeholder de carga y EmptyState declarado en todas?

**Canon propuesto**: `UiSkeleton (o LoadingSpinner) + UiEmptyState` — módulo de referencia `/settings/accounting-locks`.

**Por qué**: La carga se ve como esqueleto y el vacío como primitiva, no como un div con texto.

**Rutas divergentes**: `/professionals` (carga ausente, vacío declarado) · `/professionals/:id` (carga ausente, vacío no aplica) · `/appointment-types/:id` (carga ausente, vacío no aplica) · `/procedure-catalog/:id` (carga ausente, vacío declarado) · `/cash-register` (carga ausente, vacío declarado) · `/cash-register/ready-to-bill` (carga ausente, vacío declarado) · `/specialty-records` (carga ausente, vacío no aplica)

**Aceptaciones declaradas**: `/login` (resources/js/modules/auth/LoginPage.vue#states; render/login-anonymous.json#loadingProbe={"skeleton":false,"spinner":false,"visibleText":233}; aceptación: La p…) · `/reset-password` (resources/js/modules/auth/LoginPage.vue#states; render/reset-password-anonymous.json#loadingProbe={"skeleton":false,"spinner":false,"visibleText":233}; aceptac…) · `/:pathMatch(.*)*` (resources/js/modules/errors/NotFoundPage.vue#states; render/pathmatch-recepcionista.json#loadingProbe={"skeleton":false,"spinner":false,"visibleText":143}; ace…)

**Medido**: 36/36 ruta(s); OK 26, DIV 7, ACE 3, N/A 0, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #452 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 6. Feedback (`feedback`)

**Pregunta**: ¿El 422 cae inline en el campo o sólo se avisa con toast?

**Canon propuesto**: `:error en el campo (UiInput / useFieldValidation)` — módulo de referencia `/cash-register`.

**Por qué**: El 422 cae en el campo que lo provocó; el toast queda para la acción sin superficie.

**Rutas divergentes**: `/dashboard` (sólo toast) · `/patients` (sólo toast) · `/environments` (sólo toast) · `/appointment-types` (sólo toast) · `/appointment-types/:id` (sólo toast) · `/my-procedures` (sólo toast) · `/cash-register/ready-to-bill` (sólo toast)

**Medido**: 30/36 ruta(s); OK 23, DIV 7, ACE 0, N/A 6, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #415 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 7. Filtros (`filters`)

**Pregunta**: ¿Hay UiFilterBar con limpiar, o controles sueltos sin reset?

**Canon propuesto**: `UiFilterBar (FilterBar.vue) con limpiar` — módulo de referencia `/business-intelligence`.

**Por qué**: Una barra de filtros con reset explícito, en vez de controles sueltos por página.

**Rutas divergentes**: `/patients/:id` (filterBar=false, reset=[], controles=0, acciones=1) · `/professionals` (filterBar=false, reset=["Limpiar filtros"], controles=0, acciones=1) · `/environments` (filterBar=false, reset=["Limpiar filtros"], controles=0, acciones=1) · `/specialties` (filterBar=false, reset=["Limpiar filtros"], controles=0, acciones=1) · `/procedure-stats` (filterBar=false, reset=[], controles=2, acciones=1) · `/reception-procedures` (filterBar=false, reset=[], controles=1, acciones=0) · `/business-intelligence` (filterBar=false, reset=["Limpiar filtros"], controles=2, acciones=2) · `/treatment-plans` (filterBar=false, reset=["Limpiar","Limpiar filtros"], controles=2, acciones=2) · `/quotations` (filterBar=false, reset=["Limpiar filtros"], controles=2, acciones=2) · `/ai-analysis` (filterBar=false, reset=["Limpiar filtros"], controles=2, acciones=1) · `/settings/branches` (filterBar=false, reset=["Limpiar filtros"], controles=0, acciones=1) · `/settings/payment-methods` (filterBar=false, reset=["Limpiar filtros"], controles=0, acciones=1)

**Medido**: 15/36 ruta(s); OK 3, DIV 12, ACE 0, N/A 21, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #456 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 8. Badges (`badges`)

**Pregunta**: ¿Un solo StatusPill/StatusBadge o una insignia propia con class*badge?

**Canon propuesto**: `UiStatusBadge (StatusBadge.vue)` — módulo de referencia `/treatment-plans`.

**Por qué**: Un solo badge genérico (variant + label) y una sola etiqueta por estado de dominio; UiStatusPill queda como alias deprecado.

**Rutas divergentes**: `/ai-analysis` (UiStatusPill (alias de dominio deprecado)) · `/branch-transfers` (UiStatusPill (alias de dominio deprecado))

**Medido**: 36/36 ruta(s); OK 34, DIV 2, ACE 0, N/A 0, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #454 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 9. Formato (`format`)

**Pregunta**: ¿Una sola forma de moneda y un helper de fecha compartido?

**Canon propuesto**: `useFormatters.formatCurrency + un helper de fecha compartido` — módulo de referencia `/cash-register`.

**Por qué**: Hoy la moneda tiene helper compartido y la fecha larga no: cada página usa toLocale*/Intl, y conviven es-ES y es-PE con dos relojes.

**Rutas divergentes**: `/dashboard` (moneda ["S/ #.#"], fechas ["# de octubre de #"], toLocale/Intl=true, módulo legacy=false) · `/patients` (moneda [], fechas ["#/#/#"], toLocale/Intl=true, módulo legacy=false) · `/patients/:id` (moneda [], fechas ["#/#/#"], toLocale/Intl=true, módulo legacy=false) · `/procedure-catalog` (moneda ["S/ #.#","S/ #,#.#"], fechas [], toLocale/Intl=false, módulo legacy=false) · `/procedure-stats` (moneda ["S/ #.#"], fechas ["#/#/#"], toLocale/Intl=true, módulo legacy=false) · `/reception-procedures` (moneda ["S/ #,#.#","S/ #.#"], fechas [], toLocale/Intl=false, módulo legacy=false) · `/business-intelligence` (moneda ["S/ #,#.#","S/ #.#"], fechas ["#/#/#"], toLocale/Intl=true, módulo legacy=false) · `/cash-register` (moneda ["S/ #.#","S/ #,#.#"], fechas [], toLocale/Intl=false, módulo legacy=false) · `/medical-records` (moneda [], fechas ["#/#/#"], toLocale/Intl=true, módulo legacy=false) · `/medical-records/:id` (moneda [], fechas ["#/#/#"], toLocale/Intl=true, módulo legacy=false)

**Medido**: 26/36 ruta(s); OK 16, DIV 10, ACE 0, N/A 10, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #451 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

### 10. Responsive (`responsive`)

**Pregunta**: ¿Sin desborde horizontal de documento a 390 px?

**Canon propuesto**: `UiDataTable con scroll interno y sin desborde de documento` — módulo de referencia `/medical-records`.

**Por qué**: A 390 px el documento no se desplaza en horizontal; la tabla resuelve su propio ancho.

**Rutas divergentes**: ninguna

**Medido**: 36/36 ruta(s); OK 36, DIV 0, ACE 0, N/A 0, NO PROBADO 0.

**Veredicto del eje**: OK

**Dueño**: aceptación/no aplica (sin divergencia pendiente)

### 11. Densidad (`density`)

**Pregunta**: ¿Hero ≤ 6 palabras y subtítulo ≤ 30?

**Canon propuesto**: `hero band (data-hero-title / data-hero-caption) con título ≤ 6 palabras` — módulo de referencia `/settings/accounting-locks`.

**Por qué**: El control manda: el título y su subtítulo no compiten con la tarea.

**Rutas divergentes**: ninguna

**Medido**: 17/36 ruta(s); OK 17, DIV 0, ACE 0, N/A 19, NO PROBADO 0.

**Veredicto del eje**: OK

**Dueño**: aceptación/no aplica (sin divergencia pendiente)

### 12. Repetición (`repetition`)

**Pregunta**: ¿El mismo dato repetido dos veces en la misma vista?

**Canon propuesto**: `KPI strip + UiDataTable como fuentes únicas del dato` — módulo de referencia `/settings/branches`.

**Por qué**: El mismo dato no se publica dos veces en la misma vista sin una función distinta.

**Rutas divergentes**: `/patients/:id` (5 grupo(s) repetido(s)) · `/environments/:id` (1 grupo(s) repetido(s)) · `/business-intelligence` (1 grupo(s) repetido(s)) · `/medical-records/:id` (1 grupo(s) repetido(s)) · `/settings/branches/:id` (1 grupo(s) repetido(s))

**Medido**: 36/36 ruta(s); OK 31, DIV 5, ACE 0, N/A 0, NO PROBADO 0.

**Veredicto del eje**: DIV

**Dueño**: #405 (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)

## Evidencia por ruta

| Ruta | Módulo | Fila de render | Capturas |
| --- | --- | --- | --- |
| `/login` | resources/js/modules/auth/LoginPage.vue | render/login-anonymous.json | `shots/login-anonymous-desktop.png` · `shots/login-anonymous-mobile.png` |
| `/reset-password` | resources/js/modules/auth/LoginPage.vue | render/reset-password-anonymous.json | `shots/reset-password-anonymous-desktop.png` · `shots/reset-password-anonymous-mobile.png` |
| `/dashboard` | resources/js/modules/dashboard/DashboardPage.vue | render/dashboard-recepcionista.json | `shots/dashboard-recepcionista-desktop.png` · `shots/dashboard-recepcionista-mobile.png` |
| `/calendar` | resources/js/modules/appointments/CalendarPage.vue | render/calendar-recepcionista.json | `shots/calendar-recepcionista-desktop.png` · `shots/calendar-recepcionista-mobile.png` |
| `/my-day` | resources/js/modules/my-day/MyDayPage.vue | render/my-day-odontologo.json | `shots/my-day-odontologo-desktop.png` · `shots/my-day-odontologo-mobile.png` |
| `/patients` | resources/js/modules/patients/PatientsPage.vue | render/patients-recepcionista.json | `shots/patients-recepcionista-desktop.png` · `shots/patients-recepcionista-mobile.png` |
| `/patients/:id` | resources/js/modules/patients/PatientDetailPage.vue | render/patients-id-recepcionista.json | `shots/patients-id-recepcionista-desktop.png` · `shots/patients-id-recepcionista-mobile.png` |
| `/professionals` | resources/js/modules/professionals/ProfessionalsPage.vue | render/professionals-administrador.json | `shots/professionals-administrador-desktop.png` · `shots/professionals-administrador-mobile.png` |
| `/professionals/:id` | resources/js/modules/professionals/ProfessionalDetailPage.vue | render/professionals-id-administrador.json | `shots/professionals-id-administrador-desktop.png` · `shots/professionals-id-administrador-mobile.png` |
| `/environments` | resources/js/modules/environments/EnvironmentsPage.vue | render/environments-administrador.json | `shots/environments-administrador-desktop.png` · `shots/environments-administrador-mobile.png` |
| `/environments/:id` | resources/js/modules/environments/EnvironmentDetailPage.vue | render/environments-id-administrador.json | `shots/environments-id-administrador-desktop.png` · `shots/environments-id-administrador-mobile.png` |
| `/appointment-types` | resources/js/modules/appointment-types/AppointmentTypesPage.vue | render/appointment-types-administrador.json | `shots/appointment-types-administrador-desktop.png` · `shots/appointment-types-administrador-mobile.png` |
| `/appointment-types/:id` | resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue | render/appointment-types-id-administrador.json | `shots/appointment-types-id-administrador-desktop.png` · `shots/appointment-types-id-administrador-mobile.png` |
| `/specialties` | resources/js/modules/specialties/SpecialtiesPage.vue | render/specialties-administrador.json | `shots/specialties-administrador-desktop.png` · `shots/specialties-administrador-mobile.png` |
| `/procedure-catalog` | resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue | render/procedure-catalog-administrador.json | `shots/procedure-catalog-administrador-desktop.png` · `shots/procedure-catalog-administrador-mobile.png` |
| `/procedure-catalog/:id` | resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue | render/procedure-catalog-id-administrador.json | `shots/procedure-catalog-id-administrador-desktop.png` · `shots/procedure-catalog-id-administrador-mobile.png` |
| `/procedure-stats` | resources/js/modules/procedure-catalog/ProcedureStatsPage.vue | render/procedure-stats-finanzas.json | `shots/procedure-stats-finanzas-desktop.png` · `shots/procedure-stats-finanzas-mobile.png` |
| `/my-procedures` | resources/js/modules/my-procedures/MyProceduresPage.vue | render/my-procedures-odontologo.json | `shots/my-procedures-odontologo-desktop.png` · `shots/my-procedures-odontologo-mobile.png` |
| `/reception-procedures` | resources/js/modules/reception-procedures/ReceptionProceduresPage.vue | render/reception-procedures-recepcionista.json | `shots/reception-procedures-recepcionista-desktop.png` · `shots/reception-procedures-recepcionista-mobile.png` |
| `/business-intelligence` | resources/js/modules/business-intelligence/BusinessIntelligencePage.vue | render/business-intelligence-finanzas.json | `shots/business-intelligence-finanzas-desktop.png` · `shots/business-intelligence-finanzas-mobile.png` |
| `/cash-register` | resources/js/modules/cash-register/CashRegisterPage.vue | render/cash-register-finanzas.json | `shots/cash-register-finanzas-desktop.png` · `shots/cash-register-finanzas-mobile.png` |
| `/cash-register/ready-to-bill` | resources/js/modules/cash-register/ReadyToBillPage.vue | render/cash-register-ready-to-bill-finanzas.json | `shots/cash-register-ready-to-bill-finanzas-desktop.png` · `shots/cash-register-ready-to-bill-finanzas-mobile.png` |
| `/treatment-plans` | resources/js/modules/treatment-plans/TreatmentPlansPage.vue | render/treatment-plans-odontologo.json | `shots/treatment-plans-odontologo-desktop.png` · `shots/treatment-plans-odontologo-mobile.png` |
| `/quotations` | resources/js/modules/quotations/QuotationsPage.vue | render/quotations-finanzas.json | `shots/quotations-finanzas-desktop.png` · `shots/quotations-finanzas-mobile.png` |
| `/medical-records` | resources/js/modules/medical-records/MedicalRecordsPage.vue | render/medical-records-odontologo.json | `shots/medical-records-odontologo-desktop.png` · `shots/medical-records-odontologo-mobile.png` |
| `/medical-records/:id` | resources/js/modules/medical-records/MedicalRecordsPage.vue | render/medical-records-id-odontologo.json | `shots/medical-records-id-odontologo-desktop.png` · `shots/medical-records-id-odontologo-mobile.png` |
| `/specialty-records` | resources/js/modules/specialty-records/SpecialtyRecordsPage.vue | render/specialty-records-odontologo.json | `shots/specialty-records-odontologo-desktop.png` · `shots/specialty-records-odontologo-mobile.png` |
| `/ai-analysis` | resources/js/modules/ai-analysis/AiAnalysisPage.vue | render/ai-analysis-odontologo.json | `shots/ai-analysis-odontologo-desktop.png` · `shots/ai-analysis-odontologo-mobile.png` |
| `/continuity` | resources/js/modules/continuity/ContinuityPage.vue | render/continuity-recepcionista.json | `shots/continuity-recepcionista-desktop.png` · `shots/continuity-recepcionista-mobile.png` |
| `/lab-cases` | resources/js/modules/lab-cases/LabCasesPage.vue | render/lab-cases-recepcionista.json | `shots/lab-cases-recepcionista-desktop.png` · `shots/lab-cases-recepcionista-mobile.png` |
| `/settings/branches` | resources/js/modules/settings/branches/BranchesPage.vue | render/settings-branches-administrador.json | `shots/settings-branches-administrador-desktop.png` · `shots/settings-branches-administrador-mobile.png` |
| `/settings/branches/:id` | resources/js/modules/settings/branches/BranchDetailPage.vue | render/settings-branches-id-administrador.json | `shots/settings-branches-id-administrador-desktop.png` · `shots/settings-branches-id-administrador-mobile.png` |
| `/branch-transfers` | resources/js/modules/branch-transfers/BranchTransfersPage.vue | render/branch-transfers-recepcionista.json | `shots/branch-transfers-recepcionista-desktop.png` · `shots/branch-transfers-recepcionista-mobile.png` |
| `/settings/payment-methods` | resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue | render/settings-payment-methods-administrador.json | `shots/settings-payment-methods-administrador-desktop.png` · `shots/settings-payment-methods-administrador-mobile.png` |
| `/settings/accounting-locks` | resources/js/modules/accounting-locks/AccountingLocksPage.vue | render/settings-accounting-locks-administrador.json | `shots/settings-accounting-locks-administrador-desktop.png` · `shots/settings-accounting-locks-administrador-mobile.png` |
| `/:pathMatch(.*)*` | resources/js/modules/errors/NotFoundPage.vue | render/pathmatch-recepcionista.json | `shots/pathmatch-recepcionista-desktop.png` · `shots/pathmatch-recepcionista-mobile.png` |

## Canon propuesto

| Eje | Primitiva/helper que gana | Módulo de referencia | Por qué |
| --- | --- | --- | --- |
| Diálogo | UiModal (Modal.vue) | /settings/payment-methods | Un solo lenguaje de ventana: centrado, foco atrapado, scroll bloqueado y Escape gestionados por la primitiva. El Sheet queda reservado al flujo largo que el canon declare. |
| Lista | UiDataTable (DataTable.vue) + Pagination | /settings/payment-methods | El scroll vive dentro de la tabla, con affordance, paginación y estados de carga/vacío compartidos. |
| Título | un <h1> del shell y <h2 data-hero-title> en la página | /settings/accounting-locks | El shell conserva el h1 del documento (visible o sr-only) y el hero de la página titula con h2. |
| Botonera | UiButton + vocabulario “Nuevo/Nueva <sustantivo>” y un verbo propio por envío | /patients | Los mismos verbos para la misma acción evitan que el usuario reaprenda cada módulo. |
| Estados | UiSkeleton (o LoadingSpinner) + UiEmptyState | /settings/accounting-locks | La carga se ve como esqueleto y el vacío como primitiva, no como un div con texto. |
| Feedback | :error en el campo (UiInput / useFieldValidation) | /cash-register | El 422 cae en el campo que lo provocó; el toast queda para la acción sin superficie. |
| Filtros | UiFilterBar (FilterBar.vue) con limpiar | /business-intelligence | Una barra de filtros con reset explícito, en vez de controles sueltos por página. |
| Badges | UiStatusBadge (StatusBadge.vue) | /treatment-plans | Un solo badge genérico (variant + label) y una sola etiqueta por estado de dominio; UiStatusPill queda como alias deprecado. |
| Formato | useFormatters.formatCurrency + un helper de fecha compartido | /cash-register | Hoy la moneda tiene helper compartido y la fecha larga no: cada página usa toLocale*/Intl, y conviven es-ES y es-PE con dos relojes. |
| Responsive | UiDataTable con scroll interno y sin desborde de documento | /medical-records | A 390 px el documento no se desplaza en horizontal; la tabla resuelve su propio ancho. |
| Densidad | hero band (data-hero-title / data-hero-caption) con título ≤ 6 palabras | /settings/accounting-locks | El control manda: el título y su subtítulo no compiten con la tarea. |
| Repetición | KPI strip + UiDataTable como fuentes únicas del dato | /settings/branches | El mismo dato no se publica dos veces en la misma vista sin una función distinta. |

