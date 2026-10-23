# UI — Cola de continuidad de recepción (issue #310)

Issue #310 (`[UI] Cola de continuidad de recepción: recalls vencidos y solicitudes
pendientes`, `area:frontend`, `crit:media`). Rama `feat/continuity-lab-ui`,
worktree `.worktrees/310-316`, base `main` @ `ad29517`. Comparte rama con #316
porque el usuario pidió ambas entregas en un solo worktree.

## Specs

S1. #310: «Entrada de navegación para recepción (página propia o sección de su
escritorio) que consuma `GET /api/continuity/queue`.»

S2. #310: «Lista de recalls vencidos y solicitudes pendientes con paciente,
motivo, vencimiento o fecha preferida, duración y origen (plan/atención/cita).»

S3. #310: «Acción **Agendar** que abra el formulario de cita con la fecha
preferida precargada y llame al endpoint de schedule correspondiente.»

S4. #310: «Acción **Descartar** con motivo obligatorio.»

S5. #310: «Tarjeta o indicador de la métrica de cumplimiento de recalls.»

S6. #310: «Refresco de la cola después de agendar o descartar.»

S7. #310: «Estados de carga, error con reintento y vacío; diseño responsive y
componentes del design system (`UiDataTable`, `UiModal`, `UiButton`, etc.).»

S8. #310: «Un usuario con `view.continuity` ve la cola con datos reales; un
usuario sin ese permiso no accede.»

S9. #310: «Las acciones de agendar y descartar solo aparecen con
`manage.continuity`; un clínico puede leer la cola pero no gestionarla.»

S10. #310: «Un recall vencido y una solicitud pendiente se pueden agendar, y la
cita creada valida profesional y sillón.»

S11. #310: «Un conflicto de horario deja el elemento en la cola y muestra el
error 422 en el campo correspondiente.»

S12. #310: «Descartar exige motivo y lo persiste; el elemento sale de la cola.»

S13. #310: «La métrica de cumplimiento se muestra con el mismo valor que publica
la API.»

S14. #310: «La pantalla no rompe el flujo existente de recepción ni duplica la
agenda.»

## Tasks

- T1 — S1, S8 — shell: ruta `/continuity`, entrada de navegación para
  `APPOINTMENT_ROLES`, título de página propio, superficie canvas y banderas
  `viewContinuity`/`manageContinuity`. Commit: `1c22c81`.
- T2 — S2, S5, S7, S13 — `continuityMeta`, `useContinuity`, hero + KPIs + las
  dos listas (`UiDataTable`) + carga/error/vacío. Commit: `96ffed9` (contrato y
  composable), `ec8ac79` (página y smoke).
- T3 — S3, S4, S6, S9, S10, S11, S12 — modal de agenda por las dos puertas,
  descarte con motivo obligatorio, refresco y mapeo del 422 al campo. Commit:
  `8bfdf9e`.
- T4 — S7, S14 — tests unitarios y smokes del módulo. Commit: viajan con su
  unidad (`96ffed9`, `ec8ac79`, `8bfdf9e`); 36 pruebas verdes.
- T5 — verificación: `pnpm test:js` (1045 pruebas, 122 archivos) y
  `pnpm build` verdes; ESLint 0 errores (36 avisos previos, ninguno en los
  archivos de esta entrega), `format:check` verde; Pint no aplica (no hay PHP
  modificado); `doc-drift` 14/14. `gentle_review assess` sobre `main..HEAD`:
  riesgo medio, `reviewDue: false` (`under_budget`), sin escalamiento → la
  revisión nativa no procede y no se lanza.

## Slices de entrega

Medido con `git show --stat` (líneas autoradas, sin lockfiles):

- `96ffed9` — 569 líneas — `continuityMeta` + `useContinuity` y sus dos suites.
- `ec8ac79` — 543 líneas — `ContinuityPage` + smoke.
- `1c22c81` — 94 líneas — shell compartido con #316.
- `8bfdf9e` — 889 líneas — modales de agenda y descarte, helpers de fecha, el
  cableado de acciones de la página y sus smokes.

Los tres primeros superan la guía de ~400 líneas y quedan registrados como
límite de slice: la página no compila sin el composable y el smoke de la
superficie no se puede escribir antes del marcado, así que cortar más fino
produciría commits que no se sostienen solos. El corte sigue la costura real
(contrato/test primero, superficie después) para que la revisión lea el contrato
del composable antes de la vista.

El slice de acciones supera la guía porque los dos modales, el cableado de las
tablas y el smoke que los ejercita son una sola unidad de comportamiento: el
modal de agenda no tiene superficie sin la tabla que lo abre, y el 422 al campo
sólo se puede probar con el modal montado sobre la página. Partirlo dejaría un
commit con modales inalcanzables.

## Decisiones de contrato

- Página propia (`/continuity`) y no una sección del escritorio: recepción no
  tiene `/my-day` (esa cola es del profesional, `view.my_day`), y la cola de
  continuidad es de recepción y administración.
- **Descartar es solo de solicitudes de continuidad**: es la única fila que
  persiste el motivo (`resolution_notes` por
  `POST /api/continuity-requests/{id}/dismiss`). El recall no tiene motivo de
  descarte; su salida de la cola es agendarlo, que es lo que pide S10.
- La fecha preferida del recall no existe como campo: se precarga la fecha de
  vencimiento (`due_date`) y la duración queda en el valor del formulario. La
  solicitud sí trae `preferred_from` y `duration_minutes`.
- La métrica se pide a `GET /api/patient-recalls/metrics` sin filtros de fecha:
  el indicador muestra `compliance_rate` tal como lo publica la API (`null`
  cuando no hay denominador, y se pinta «—»).

## Log

- L1 (usuario, verbatim): «haz la issue 310,316 en un worktree nuevo»
- L2: requisitos tomados del cuerpo de la issue #310 (verbatim en Specs).
- L3 (verificación): `pnpm exec vitest run` completo en verde (122 archivos,
  1045 pruebas) y `pnpm build` correcto. ESLint: 0 errores; los 36 avisos son
  preexistentes y ninguno cae en los archivos de este módulo. `format:check`
  verde y `node scripts/audit/doc-drift.mjs` en 14/14 (42 composables, 253 rutas
  API). El `artisan` del worktree necesitó `BROADCAST_CONNECTION=null` porque el
  worktree no trae `.env`: la app arranca y el guard mide.
- L4 (decisión de entrega): `gentle_review assess` con
  `{"baseRef":"main","committedOnly":true}` devuelve `nativeRisk: medium`,
  `reviewDue: false` (`under_budget`) y sin escalamiento, así que la revisión
  nativa no procede: el plan deja la autoverificación del escritor y ninguna
  verificación independiente. Los cambios de este trabajo son de superficie
  (rutas, navegación, páginas, composables y pruebas) y no tocan datos,
  permisos ni contratos consumidos por terceros.
