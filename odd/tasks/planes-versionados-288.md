# Planes de tratamiento — fases, prioridad, versiones y sesiones (##288)

Issue #288 (`[Dominio] Planes de tratamiento: fases, prioridad, versiones y
sesiones`, `crit:alta`, `area:backend`). Continúa en la misma rama y worktree que
#284 (`odd/tasks/f4-continuidad-284.md`), porque el consumo por sesión es el
cimiento del versionado.

## Specs

S1. #288: «Prioridad y secuencia explícitas por ítem.»

S2. #288: «Versionado del plan; el presupuesto aceptado apunta a una versión
inmutable.»

S3. #288: «Alternativas por pieza, con lo rechazado registrado.»

S4. #288: «Consumo de ítems por sesión (ver F4).»

S5. #288: «Cambiar un plan aceptado crea versión nueva y no altera el
presupuesto aceptado.»

S6. #288: «Un ítem opcional rechazado queda registrado con fecha y motivo.»

S7. #288: «No hay versiones del plan: se edita el mismo registro.» Se cierra con
`treatment_plan_versions` inmutable y punteros `current_version_id` /
`active_version_id` en el plan.

S8. #288: «La máquina de estados no se valida.» Se cierra con transiciones
inválidas en 422 (antes 500) y guardas de contenido para `approved` y
`completed`.

S9. #288: «No hay prioridad por ítem (urgente vs electivo vs opcional).»

S10. #288: «No hay alternativas por diente (opción A/B) ni registro de lo
rechazado.»

## Tasks

- T1 — S1, S9 — `priority` (`urgent|elective|optional`) y `sequence` por ítem,
  validación, orden estable y resumen de fases. Commit: `352d96e`; tests:
  `1d9b857`.
- T2 — S2, S5, S7 — versiones inmutables con snapshot, punteros del plan,
  aceptación al aprobar y vínculo del presupuesto aprobado. Commit: `352d96e`;
  tests: `1d9b857`.
- T3 — S3, S6, S10 — `treatment_plan_item_alternatives` (opción A/B por pieza)
  y rechazo de ítem opcional con fecha y motivo. Commit: `352d96e`; tests:
  `1d9b857`.
- T4 — S4 — el plan publica consumos y ejecutado/en curso/pendiente (cierre de
  F4). Commit: `352d96e`; tests: `9af74d0` y `1d9b857`.
- T5 — S8 — máquina de estados con 422 y guardas de contenido. Commit:
  `352d96e`; tests: `1d9b857`.
- T6 — verificación: suite MySQL, Vitest, Pint, Prettier, `doc-drift`, revisión
  nativa y cierre documental.

## Decisiones de contrato

- La versión es una foto inmutable de la definición del plan (cabecera + ítems),
  no de su ejecución. Los consumos viven en el acto y no se congelan.
- Un plan en `draft`/`proposed` sin versión aceptada refresca su versión
  corriente; uno con versión aceptada crea una versión nueva y conserva la
  aceptada como `active_version_id`. La nueva versión no altera el presupuesto
  ya aprobado: la cotización se vincula a la versión vigente al aprobarse.
- `approved` acepta la versión corriente; `completed` exige todos los ítems no
  cancelados consumidos; `cancelled` sigue siendo la salida de cualquier estado
  no terminal.
- `is_optional` se conserva por compatibilidad y se sincroniza con
  `priority = optional`.
- `phases` JSON queda como dato legacy: las fases explícitas son
  `phase_number` + `sequence`.

## Slices de entrega

- S1 — `352d96e` — cimiento compartido con F4: esquema, modelos, servicios,
  controladores, rutas y permisos de versiones, prioridad, alternativas y
  máquina de estados. El commit supera la guía de ~400 líneas porque comparte
  archivos con F4 y un corte intermedio dejaría clases sin definir; queda
  registrado como límite de slice.
- S2 — `9af74d0` — tests de F4 (cimiento de consumo).
- S3 — `1d9b857` — tests de #288.
- S4 — commit de documentos — ADR-0013, AGENTS.md y estos documentos.

## Evidence

- `TreatmentPlanPriorityTest` (4), `TreatmentPlanVersioningTest` (9) y
  `TreatmentPlanAlternativesTest` (6): verdes en MySQL.
- Suite completa `vendor/bin/phpunit -c phpunit.mysql.xml`: 2529 tests,
  15140 assertions, `OK`.
- `doc-drift` 14/14, Pint, Prettier, ESLint (0 errores), Vitest 946 tests y
  `pnpm build` en verde.

## Log

L1. Usuario: «haz la issue 288, 284, en worktree nuevo».
L2. Orden de entrega: #284 primero (F4) y #288 después, porque el consumo por
sesión nace en F4 y el versionado lo necesita.
L3. La issue #288 declara dependencia F4; la issue #284 declara dependencia F2
(cerrada en `main`).
