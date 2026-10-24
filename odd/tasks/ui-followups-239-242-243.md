# UI follow-ups — #239 / #242 / #243

Rama: `fix/ui-followups-239-242-243` (desde `origin/main` @ a2c7101)
Worktree: `~/proyectos/odontosuite-ui-followups`
Creado: 2026-10-20 · Orquestador: el Gentleman

## Specs

- **S1.** "quiero hacer issues 242, #243 #239 desarrolles, en un worktree nuevo" — los tres issues se desarrollan juntos, en un worktree aislado y una rama nueva desde `origin/main`.
- **S2.** #239 — "refactor(ui): decidir el destino de AiAnalysisButton, que quedó sin consumidor". Decisión del usuario en esta sesión: "A. Retirar el componente (recomendado)" — "Borra el componente, su smoke y su contrato de design system; el flujo queda donde ya funciona." Criterio de aceptación del issue: "Si se remueve: no queda ninguna referencia a `AiAnalysisButton`, su smoke y la entrada `embeddedFiles()` de `AiAnalysisAppShellTest` se retiran, y `pnpm test:js` + `tests/Unit/DesignSystem` siguen verdes."
- **S3.** #242 — "refactor(ui): TreatmentPlanDetail sigue duplicando overlay, backdrop y foco". Criterios: "`TreatmentPlanDetail.vue` no declara overlay, backdrop ni listener de Escape propios."; "El smoke del módulo sigue en verde y suma cobertura del hook raíz."; "Sin cambios de datos ni de ruteo." Propuesta: "Migrar a `UiModal` conservando el hook `data-plan-detail`, la cabecera propia y las acciones existentes."; "Mantener el orden de foco y el cierre por Escape/backdrop que hoy ofrece el overlay."; "Cubrir con smoke del módulo el hook raíz, el `aria-labelledby` y el cierre."
- **S4.** #243 — "fix(ui): las rutas de detalle renderizan dos <h1>". Decisión del usuario en esta sesión: "A. La página de detalle es dueña (recomendado)" — "El topbar no renderiza ningún h1 en esas rutas; el nombre de la entidad es el encabezado del documento." Criterios: "Cada ruta de detalle renderiza exactamente un `<h1>`."; "`page-header-hierarchy.test.js` y los contratos de módulo siguen verdes."; "Sin cambios de ruteo, datos ni copy."
- **S5.** Entrega: un PR con tres work-unit commits (uno por issue) sobre la rama del worktree, referenciando #239 #242 #243. Las decisiones de producto ya fueron tomadas por el usuario en esta sesión (S2, S4); no se re-preguntan.
- **S6.** Convenciones heredadas de `AGENTS.md` §7: código, tests, commits y docs de repositorio en inglés; copy de UI en español; `pnpm` siempre; commits semánticos sin emojis; "UI: consumir SIEMPRE de `components/ui/`. NO botones/modales ad-hoc."; sin comentarios pedagógicos.
- **S7.** Sin cambios de rutas, datos, migraciones ni primitivas UI nuevas: los conteos de `AGENTS.md` §4 no cambian y `scripts/audit/doc-drift.mjs` sigue en verde.

## Tasks

| ID  | Módulo                    | Specs | Ruta   | Commit |
| --- | ------------------------- | ----- | ------ | ------ |
| T1  | #239 retirar `AiAnalysisButton` | S2 | inline | `356d98c` `refactor(ui): retire the orphaned AiAnalysisButton` |
| T2  | #242 `TreatmentPlanDetail` a `UiModal` | S3 | inline | `ade2903` `refactor(ui): move the treatment plan detail onto UiModal` |
| T3  | #243 un `<h1>` por ruta de detalle | S4 | inline | `fdd40cd` `fix(ui): keep a single h1 per detail route` |
| T4  | Docs: changelog de `AGENTS.md` §12 | S5, S7 | inline | dentro de `fdd40cd` (cierra la feature); este documento queda local por `.git/info/exclude` |

Presupuesto de revisión: T1 es una remoción (~-300 líneas netas), T2 una migración de plantilla
(~150 líneas), T3 unas ~40 líneas más tests; ningún slice se acerca al umbral de 400 líneas
autoradas. Cada commit es revisable solo.

## Log

- **L1.** "quiero hacer issues 242, #243 #239 desarrolles, en un worktree nuevo"
- **L2.** #239: decisión A — retirar el componente; el flujo de análisis queda en `/ai-analysis`.
- **L3.** #243: decisión A — la página de detalle es dueña del `<h1>`; el topbar omite su encabezado en esas rutas.
- **L4.** Worktree `odontosuite-ui-followups` creado desde `origin/main` @ `a2c7101`; `vendor/` y `node_modules/` copiados desde el checkout principal (sin reinstalación).
- **L5.** Alcance verificado en el código antes de escribir: `grep -rn AiAnalysisButton resources/js` no devuelve consumidores; `TreatmentPlanDetail.vue` declara overlay, backdrop, `document.addEventListener('keydown')` propio y `role="dialog"` en su raíz; `AppLayout.pageOwnsTitle` marca como `sr-only` el h1 del topbar en `/appointment-types/:id`, `/procedure-catalog/:id` y `/professionals/:id`, que ya renderizan su propio `<h1>`.
- **L6.** T1 (#239) sin RED posible: el cambio es una remoción, no hay comportamiento nuevo que fijar. Verificación: `grep` sin referencias en `resources/js` y `tests/js`, `php artisan test tests/Unit/DesignSystem/AiAnalysisAppShellTest.php` 6/6 (la clase queda solo con el contrato de página) y `vitest run tests/js/smoke/ai-analysis` 4 archivos / 37 tests en verde. El smoke del botón se retiró junto con el componente y `embeddedFiles()` desapareció del shell test.
- **L7.** Slice T1 registró 517 líneas autoradas según el hook de entrega: el 90% son borrados (componente 124, smoke 169 y 103 líneas de contrato de design system que se reemplazan por 28). No hay re-trabajo ni slice artificial: la estrategia `single-pr` con tres commits ya estaba decidida (S5); se deja constancia del límite y se continúa.
- **L8.** T2 (#242) RED: el smoke reescrito del detalle falla 3/6 contra el componente viejo — no existe un `[role="dialog"]` interno, el hook raíz es el propio diálogo y el Escape cierra el detalle aunque haya un modal de la primitiva encima (el defecto anidado que describe el issue). GREEN tras montar `UiModal`: 6/6; smoke del módulo 4 archivos / 34 tests; `TreatmentPlansAppShellTest` 20/20 con un guard estático nuevo (`test_plan_detail_rides_the_modal_primitive`) que prohíbe overlay, backdrop, `role="dialog"` y listener de Escape propios.
- **L9.** Slice T2: 748 líneas en el slice (el smoke del detalle se reescribe completo, 261 → 250 líneas, y cuenta como borrado + alta). Acumulado 1265 en dos commits. Sin re-trabajo: el contrato anterior no sobrevivía a la primitiva y la reescritura del smoke es el RED. Se mantiene `single-pr`.
- **L10.** T3 (#243) RED: `page-header-hierarchy.test.js` falla en las tres rutas de detalle (el topbar pinta su h1 además del de la página); `/environments` queda en verde con un solo h1. GREEN tras añadir `pageOwnsHeading` a `AppLayout` (el topbar no renderiza `<h1>` en `/appointment-types/:id`, `/procedure-catalog/:id`, `/professionals/:id`): 6/6. `topbar-title-ownership.test.js` movió esas rutas a un grupo propio: RED 2/27 → GREEN 28/28. Verificación completa: `pnpm test:js` 104 archivos / 859 tests, `tests/Unit/DesignSystem` 949, Pint 632 archivos, `pnpm lint:check` 0 errores, `pnpm format:check` y `scripts/audit/doc-drift.mjs` 12/12.
- **L11.** Cierre: `pnpm test:js` 104 archivos / 859 tests, `tests/Unit/DesignSystem` 949 tests / 5287 aserciones, Pint 632 archivos, `pnpm lint:check` 0 errores (34 warnings preexistentes fuera de los archivos tocados), `pnpm format:check` y `scripts/audit/doc-drift.mjs` 12/12. `gentle_review assess` sobre `origin/main...HEAD`: riesgo `medium`, `reviewDue: false` (`under_budget`), plan `writerSelfVerification` sin verificador independiente; se ejecuta la autoverificación ya registrada.
- **L12.** Este documento no se commitea: `.git/info/exclude` excluye `odd/` con el motivo escrito "ODD task documents are local agent workflow memory, not repo code". La evidencia vive en la rama y en el PR #254. Sin riesgo de los seis ítems del Task Size: no toca datos, seguridad, contratos consumidos externamente, concurrencia ni entrega, y los tres guards cubren las reglas modificadas.
- **L13.** CI del PR #254 en verde sobre `5a082dd`: `quality` (2m28s), `backend-tests` MySQL (2m14s) y `frontend-build` (28s). Entrega cerrada; el merge queda a decisión del usuario.
- **L14.** Merge ejecutado: squash-free merge commit `99e962a` en `main` (`gh pr merge 254 --merge`), PR #254 cerrado. Worktree y rama limpiados tras preservar este documento en `odd/tasks/` del checkout principal.
