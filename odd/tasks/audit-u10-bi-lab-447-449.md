# Auditoría U10 — BI sin estado vacío y laboratorio sin alta/edición ni regla de propiedad (#447, #448, #449)

Worktree `../odontosuite-u10`, rama `fix/audit-u10-bi-lab-447-449`, base `main`
@ `46e05b8b`. Tres hallazgos de la auditoría U10 (plan #13, issue #373) que se
entregan juntos por compartir superficie de escritorio y presupuesto de review.

## Specs

S1. #447: «`BusinessIntelligencePage.vue` decide el vacío con `isEmpty`, que sólo
puede ser cierto cuando el conjunto de filas queda vacío. Tres de los servicios
no acotan su **población** por período (`PatientReportService`,
`ProfessionalReportService`, `UtilizationReportService`: devuelven el censo
completo y sólo acotan los contadores), y el dashboard nunca queda sin
`professionalPerformance`. El vacío del período no se puede derivar de
`rows.length`.»

S2. #447: «Derivar el vacío de la actividad del período (el resumen o un
contador que los servicios ya calculan) en lugar del tamaño de la tabla, y
fijarlo con un test por reporte: `2019-01-01 … 2019-01-31` → tarjeta de vacío;
rango con datos → tabla.»

S3. #447: «un reporte cuyo período no tiene actividad explica qué hacer, como ya
hacen Citas, Ingresos y Consolidado: «Sin datos en el período», «No hay
actividad registrada para los filtros aplicados.» y la acción «Ampliar a 90
días».» Aplica a los cuatro reportes sin tarjeta: Dashboard General, Reporte de
Pacientes, Reporte de Profesionales y Utilización de Ambientes.

S4. #448: «Decidir la superficie y dejarla escrita: o `/lab-cases` gana el
alta/edición con `LabCaseFormModal` (permiso `manage.lab_case`, ya existe), o
`AGENTS.md` deja de afirmar que se abre desde la página y la issue de la unidad
declara el follow-up. En ambos casos, un test que fije la decisión.» Decisión:
`/lab-cases` gana el alta y la edición.

S5. #448: «Esperado (issue #373, S3): la superficie de laboratorio cubre «filtros
por fase, técnico y vencido; **alta**, **edición**, detalle, avance de fase».»

S6. #449: «si el caso es del técnico asignado, `update` responde 403 cuando
`technician_id` no es el actor (salvo administración), y un caso `placed` deja de
ser editable. Cubrirlo con un test de ambos lados (el técnico asignado 200, el
otro 403).»

S7. #449: «Como mínimo, la superficie no ofrece editar casos ajenos y la API lo
explica.»

S8. Los contratos de UI siguen el design system (`UiModal`, `UiButton`,
`UiDataTable`, `UiEmptyState`) y los tests JS cubren el flujo; los instrumentos
de auditoría que midan un contrato cambiado se alinean con el contrato nuevo.

## Contrato fijado para #447

- `/api/reports/{patients,professionals,utilization}` publican además
  `meta.summary = { appointments: <int>, revenue: <float> }`, sumando los
  contadores y el dinero que cada fila **ya** trae (`total_appointments` y
  `total_spent` en pacientes; `total_appointments` y `total_revenue` en
  profesionales y utilización). Sin consultas nuevas.
- `/api/reports/revenue` conserva su `meta.summary` actual
  (`appointments_count`, `total_revenue`, `refunds`, …).
- `/api/reports/dashboard` conserva su `data`; la página deriva la actividad de
  `totalAppointments` y `totalRevenue`.
- La página declara vacío el período cuando: dashboard →
  `totalAppointments === 0 && totalRevenue === 0`; tabular con `meta.summary` →
  `summary.appointments === 0 && summary.revenue === 0`; tabular sin
  `meta.summary` (payload viejo) → `reportRows.length === 0`; consolidado → su
  regla actual. La tarjeta de vacío es la misma para los siete reportes.

## Contrato fijado para #448/#449

- `App\Policies\LabCasePolicy::update(User, LabCase)` es la única regla de
  propiedad: `administrador`, `odontologo` e `implantologo` escriben cualquier
  caso de su alcance; `tecnico_dental` sólo el caso cuyo `technician_id` es el
  suyo. El controlador la aplica con `$this->authorize('update', $labCase)`
  (403 con el sobre estándar de la API).
- Un caso en fase `placed` deja de ser editable: `LabCaseService::update()`
  responde 422 con una frase que lo explica, antes de escribir nada, y
  `can_edit` sale `false`.
- `GET /api/lab-cases` y `GET /api/lab-cases/{id}` publican `can_edit` por caso,
  derivado de la política y de la fase (`App\Http\Resources\LabCaseResource`).
  La superficie lee esa capacidad: nunca inventa la regla.
- `/lab-cases` ofrece «Nuevo caso» en el héroe y «Editar» en la fila y en el
  detalle, con `LabCaseFormModal`, sólo con `manageLabCase` y sólo cuando la
  fila publica `can_edit`; el alta abre el formulario con el selector de
  paciente (sin `patientId` precargado).

## Tasks

- T1 — S1, S2, S3 — backend de #447: `meta.summary` en los tres servicios
  tabulares, reenvío en `ReportController@tabularReport` y un test por reporte
  (período sin actividad → resumen en cero; rango con datos → resumen con las
  cifras de las filas). Commit: `9f19a092`.
- T2 — S1, S2, S3 — frontend de #447: `isEmpty` derivado de la actividad,
  tarjeta de vacío con su acción para los cuatro reportes, y smoke JS del vacío
  por reporte. Commit: `5518ed83`.
- T3 — S6, S7 — backend de #449: `LabCasePolicy`, `LabCaseResource` con
  `can_edit`, `authorize` en `update`, rechazo 422 del caso `placed` y tests de
  ambos lados. Commit: `65d1ace5`.
- T4 — S4, S5, S7, S8 — superficie de #448: alta y edición desde `/lab-cases`
  con `LabCaseFormModal`, `can_edit` respetado, smokes JS y alineación del
  instrumento `tests/e2e/audit/u10/walk-lab.js`. Commit: `9c689355`.
- T5 — verificación: `pnpm test:js`, suite MySQL de los archivos tocados, Pint,
  ESLint, Prettier, `doc-drift` y la entrada de changelog en `AGENTS.md`.
  Commit: el de cierre.

## Slices de entrega

Medido con `git diff --numstat` sobre cada commit (líneas autoradas, sin
lockfiles). Los cuatro slices superan la guía de ~400 líneas: se registran como
límite declarado, no como deuda silenciosa.

- `9f19a092` — 395 líneas — el trait `SummarizesPeriodActivity`, los tres
  servicios, el reenvío del controlador y `ReportPeriodActivitySummaryTest`.
- `5518ed83` — 673 autoradas / 206 borradas — `reportScope.js`,
  `BusinessIntelligencePage.vue`, `biReportActivity` y los dos smokes de BI
  (el rediseño de la plantilla explica el volumen: mover los bloques dentro del
  `v-else` cuenta como adición y borrado de las mismas líneas).
- `65d1ace5` — 323 líneas — `LabCasePolicy`, `LabCaseResource`, el controlador,
  el servicio y `LabCaseOwnershipTest`.
- `9c689355` — 629 autoradas / 79 borradas — la superficie de laboratorio, sus
  smokes y el instrumento de auditoría alineado.

## Log

- L1: «haz las issues #447 #448 #449 een un worktree nuevo»
- L2: `#448` deja la decisión en su propuesta: la página gana el alta y la
  edición; el changelog de `AGENTS.md` ya lo afirmaba, así que la entrega alinea
  el código con el documento en vez de retirar la afirmación.
- L3: `#449` nombra sólo `PUT /api/lab-cases/{id}`. La puerta de fase no entra en
  esta unidad: el issue no la incluye y su regla es la de avance, no la de
  propiedad.
