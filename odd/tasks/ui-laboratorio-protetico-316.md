# UI — Pantalla del laboratorio protésico (issue #316)

Issue #316 (`[F5] Pantalla del módulo de laboratorio protésico`,
`area:frontend`, `crit:media`). Rama `feat/continuity-lab-ui`, worktree
`.worktrees/310-316`, base `main` @ `ad29517`. Comparte rama con #310 porque el
usuario pidió ambas entregas en un solo worktree.

## Specs

S1. #316: «Página `/lab-cases` para el escritorio de laboratorio: listado con
filtros (`status`, `technician_id=me`, `overdue`), detalle del caso y puerta de
fase.»

S2. #316: «Alta y edición del caso desde la ficha del paciente y desde el cierre
de la atención.»

S3. #316: «Bloque de casos listos en `/my-day`; el aviso `.caso_laboratorio_listo`
ya se emite por el canal privado `lab-cases`.»

S4. #316: «El técnico gestiona las fases sin ver la historia clínica (la API ya
responde 403 fuera del módulo de laboratorio).»

S5. #316: «Recepción ve los casos listos y no puede escribir.»

S6. #316: «Los contratos de UI siguen el design system (`UiDataTable`,
`UiModal`, `UiButton`) y los smokes JS cubren el flujo.»

## Tasks

- T1 — S1, S4, S6 — shell (`/lab-cases`, navegación, título propio, canvas,
  banderas `viewLabCase`/`manageLabCase`), `labCaseMeta`, `useLabCases`, listado
  con filtros y paginación, detalle con puerta de fase. Commit: `47fd364`
  (reglas y composable), `b6dbae2` (listado, detalle y smoke), `1c22c81`
  (shell).
- T2 — S2 — `LabCaseFormModal`, pestaña «Laboratorio» en la ficha del paciente y
  entrada desde el cierre de la atención. Commit: `96cafc2`.
- T3 — S3, S5 — bloque de casos listos en `/my-day` (`ready_lab_cases`) y su
  conteo. Commit: `91fd7c5`.
- T4 — S6 — tests unitarios y smokes, incluido `myDay`. Commit: viajan con su
  unidad (`47fd364`, `b6dbae2`, `96cafc2`, `91fd7c5`); 46 pruebas del módulo y 7
  del escritorio de Mi día.
- T5 — verificación: `pnpm test:js` (1045 pruebas, 122 archivos) y
  `pnpm build` verdes; ESLint 0 errores (36 avisos previos, ninguno en los
  archivos de esta entrega), `format:check` verde; Pint no aplica (no hay PHP
  modificado); `doc-drift` 14/14. `gentle_review assess` sobre `main..HEAD`:
  riesgo medio, `reviewDue: false` (`under_budget`), sin escalamiento → la
  revisión nativa no procede y no se lanza.

## Slices de entrega

Medido con `git show --stat` (líneas autoradas, sin lockfiles):

- `47fd364` — 609 líneas — `labCaseMeta` + `useLabCases` y sus dos suites.
- `b6dbae2` — 813 líneas — `LabCasesPage` + `LabCaseDetailModal` + smoke.
- `96cafc2` — 1244 líneas — `LabCaseFormModal`, la pestaña del paciente, el
  bloque del cierre y sus tres archivos de prueba.
- `91fd7c5` — 228 líneas — el bloque de casos listos de `/my-day` y sus pruebas.
- `1c22c81` — 94 líneas — shell compartido con #310.

Todos superan la guía de ~400 líneas y quedan registrados como límite de slice:
la página importa el detalle y el smoke cubre las dos superficies en un solo
flujo (abrir el caso y mover la fase), así que separarlas produciría un commit
que no compila o un test que no ejercita la puerta. El mismo criterio aplica al
formulario: el modal, la pestaña que lo abre, el bloque del cierre y las pruebas
que verifican también el payload intacto del cierre son una sola unidad de
comportamiento; cortarlo dejaría un modal sin puerta o una prueba sin modal.
El corte sigue la costura real: reglas y contrato primero, superficie después.

## Decisiones de contrato

- El detalle del caso es un `UiModal`, no una ruta: la API no publica un
  identificador estable de URL para el caso y la issue pide «detalle del caso y
  puerta de fase» dentro de la página.
- La puerta de fase ofrece solo fases posteriores (`LabCase.PHASES` desde la
  actual): la máquina no retrocede y el mismo botón repetido responde 422 con el
  mensaje del servicio, que se muestra en el campo `phase`.
- Recepción no escribe: `manage.lab_case` no incluye `recepcionista`, así que los
  botones de fase y de alta/edición se ocultan con `can.manageLabCase`; la API
  responde 403 igual. El criterio «recepción ve los casos listos» se cumple en
  `/lab-cases` con el filtro de fase `delivered`: `/my-day` es del profesional
  (`view.my_day` no incluye recepción) y no se le abre esa cola.
- El alta desde el cierre de la atención abre el mismo `LabCaseFormModal` con el
  paciente precargado y **no** toca el payload del cierre: el caso es una
  entidad propia y su puerta es `POST /api/lab-cases`.
- `/api/users/active` ya devuelve odontólogos, implantólogos, técnicos y
  asistentes con su `role`, así que el formulario filtra en cliente los dos
  conjuntos que valida el request.
- La pestaña «Laboratorio» de la ficha del paciente solo se ofrece con
  `view.lab_case`; el técnico la ve y no ve la historia clínica, que es lo que
  pide S4.

## Log

- L1 (usuario, verbatim): «haz la issue 310,316 en un worktree nuevo»
- L2: requisitos tomados del cuerpo de la issue #316 (verbatim en Specs).
- L3 (decisión de alcance sobre S5): «Recepción ve los casos listos y no puede
  escribir» se cumple en `/lab-cases` con el filtro de fase `delivered`.
  `GET /api/my-day` no se le abre a recepción porque su permiso es `view.my_day`
  (`MEDICAL_ROLES`), así que el bloque de casos listos es del profesional.
- L4 (verificación): `pnpm exec vitest run` completo en verde (122 archivos,
  1045 pruebas) y `pnpm build` correcto. ESLint: 0 errores; los 36 avisos son
  preexistentes y ninguno cae en los archivos de este módulo. `format:check`
  verde y `node scripts/audit/doc-drift.mjs` en 14/14 (42 composables, 253 rutas
  API).
- L5 (decisión de entrega): `gentle_review assess` con
  `{"baseRef":"main","committedOnly":true}` devuelve `nativeRisk: medium`,
  `reviewDue: false` (`under_budget`) y sin escalamiento; la revisión nativa no
  procede y el plan deja la autoverificación del escritor sin verificación
  independiente.
