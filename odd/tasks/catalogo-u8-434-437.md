# Auditoría U8: escritura del catálogo, detalle y tarifario (#434, #435, #436, #437)

Cuatro defectos medidos por la auditoría UI (#371, plan #13), todos `area:frontend`
y `type:defecto`. Rama `fix/catalogo-u8-434-437`, worktree
`.worktrees/catalogo-u8-434-437`, base `ff382b5d` (`main`).

## Specs

S1. #434 esperado: «Una copia conserva lo que el original declara —aplicabilidad,
equivalencias y receta— y el código propuesto cabe en la columna (o la API lo
rechaza con un `422` que nombra el límite, no con un `500`).»

S2. #434 esperado: «La receta de insumos es legible y editable en la superficie
del procedimiento, o el campo se retira con una decisión escrita: hoy está
soportado por la API y por los tests, y es invisible para quien administra el
catálogo.» Decisión de esta entrega: se implanta la superficie.

S3. #434 qué pasa: «El original del paso `14-duplicate` declara
`site_type = surface`, una equivalencia (`997301`) y requisitos. La copia creada
responde `201` pero queda con `site_type = none` y **0 equivalencias**.»
`submitDuplicate` copia una lista fija de campos y omite `site_type`,
`external_codes` y `material_recipe`; propone `${code}-COPIA`.

S4. #434 ámbito: «`database/migrations/2025_10_24_202849_create_procedure_catalog_table.php:16`
(`varchar(20)`) contra `StoreProcedureCatalogRequest` (`max:50`): un solo
contrato o el `422` que lo declare.»

S5. #434 ámbito: «No se toca el catálogo del tarifario ni la regla de sitio de
#293; la copia debe respetar ambas.»

S6. #435 esperado: «El historial lista los cambios que la API guardó para ese
procedimiento (alta, edición, desactivación).» y ««Editar» abre el formulario del
procedimiento que se está mirando, con sus campos cargados.»

S7. #435 ámbito: «La corrección no debe cambiar el contrato de la URL: o se
espera la carga antes de retirar el parámetro, o el id viaja por estado.»

S8. #436 esperado: «Las dos listas del mismo módulo comparten el comportamiento
de tabla: paginación que informa el tamaño de página aplicado, estado vacío y
aviso de desplazamiento cuando el contenido excede su marco.»

S9. #437 esperado: «El rechazo aparece en su campo, como ya ocurre con el importe
(«Ingresa un importe válido.») y como la misma regla hace en el formulario de
estadísticas («La fecha final no puede ser anterior a la inicial.»). Sin
petición, sin perder lo escrito.»

S10. #437 ámbito: «La corrección enlaza el error al campo y conserva la regla del
servicio (`valid_to >= valid_from`, `422` «La vigencia final no puede ser
anterior al inicio.»). No se toca la validación del servidor ni el contrato de la
fila.»

## Tasks

- T1 — S1, S3, S5 — duplicar copia `site_type`, `external_codes` y
  `material_recipe` desde el detalle del original; el código propuesto cabe en
  20 caracteres. Tests: smoke del catálogo. Commit: `30f21d3b` (helper en
  `1f361eb7`).
- T2 — S4 — contrato único del código: `max:20` con mensaje que nombra el límite
  en `Store`/`UpdateProcedureCatalogRequest`. Test: `ProcedureCatalogCodeLengthTest`.
  Commit: `1f361eb7`.
- T3 — S2, S5 — receta legible en el detalle y editable en el formulario del
  procedimiento. Tests: smoke del formulario y del detalle. Commits: `30f21d3b`
  y `4bb838ce`.
- T4 — S6, S7 — historial con el `model_type` real y «Editar» abre el formulario
  cargado sin remontar la página. Tests: smoke del detalle y del catálogo.
  Commits: `c2d5f5cc` y `4bb838ce`.
- T5 — S8 — la lista del catálogo monta el contrato de tabla del módulo
  (`UiDataTable` + `Pagination` con el tamaño de página aplicado). Test:
  primitiva `data-table` y smoke del catálogo. Commit: `737a84da`.
- T6 — S9, S10 — la vigencia invertida pinta su error en el campo. Test:
  `procedure-tariff`. Commit: `845fc851`.
- T7 — Documentación (AGENTS.md §12) y chequeos completos. Commit de
  documentación (`docs: changelog de la auditoría U8 del catálogo`).

## Log

L1. Usuario: «haz la issues #434 #435 #436 #437 en un worktree nuevo».

L2. Baseline del worktree: rama `fix/catalogo-u8-434-437` desde `ff382b5d`;
`composer install` y `pnpm install --frozen-lockfile` en
`.worktrees/catalogo-u8-434-437`; base de test `odontosuite_catalogo_434_test`
creada sobre el MariaDB local por socket.

L3. Levantado del código: `submitDuplicate` omite `site_type`, `external_codes`
y `material_recipe` (la receta ni siquiera viene en el índice, sólo en el
detalle); `openDuplicate` propone `${code}-COPIA` sin tope; `Store`/`Update`
declaran `max:50` contra `varchar(20)`; el detalle pide
`model_type: 'App\\\\Models\\\\ProcedureCatalog'` (dos barras reales);
`openEditFromQuery` abre el formulario y hace `router.replace` en la misma
pasada, y el `router-view` del shell re-monta por `$route.fullPath`; la lista
monta una `<table>` propia con `min-w-[880px]` y `<Pagination>` sin `per-page`;
`BranchTariffPanel` escribe `formErrors.valid_to` sin enlazarlo al `UiInput`.

L4. Decisión S4: el único contrato del código es la columna (`20`); se alinean
`Store` y `Update` con un `422` que nombra el límite. El importador CSV queda
fuera del ámbito nombrado por #434: ya degrada la fila a error (no produce `500`)
y su columna no es una puerta del formulario.

L5. Decisión S2: la receta se implanta en la superficie (detalle y formulario),
no se retira. La lectura usa `material_recipe` que publica `show()`; la edición
consume `GET /api/inventory/products` (administrador ya tiene `view.inventory`).

L6. Decisión S7: `/procedure-catalog` declara `meta.preserveOnQuery`, como
`/patients` (#401), para que retirar `?edit=` no re-monte la página que acaba de
abrir el formulario. El contrato de la URL no cambia.

L7. T2 RED observable en MySQL: código de 21 caracteres en `POST` y `PUT`
respondía `500` (`SQLSTATE[22001] Data too long for column 'code'`); con
`max:20` en las dos requests el mismo test queda `OK (3 tests, 14 assertions)`.

L8. T1 (regla pura) RED: `tests/js/unit/procedureCode.test.js` no resolvía el
módulo; GREEN con `procedureCode.js` (5 pruebas). T5 (primitiva) RED: el
`tableClass` y los atributos de fila no llegaban al markup; GREEN con las dos
props aditivas de `UiDataTable` y la suite de affordance intacta (6 pruebas).

L9. T6 RED: la vigencia invertida no pintaba mensaje ni `aria-invalid` en el
campo «Vigente hasta»; GREEN al enlazar `:error="formErrors.valid_to"` (5
pruebas del panel).

L10. T3 (receta) delegado a `gentle-ai-worker` sobre
`ProcedureCatalogFormModal.vue` y su smoke propio: RED de las 8 pruebas antes
del cambio y GREEN después, sin tocar el resto del formulario.

L11. RED de T1/T3/T4/T5 sobre la página y el detalle: con las tres fuentes
revertidas (`git stash` de página, detalle y `app.js`), 7 de las 11 pruebas de
`procedure-catalog` y `procedure-catalog-detail` fallan (tabla, paginación,
código propuesto, copia de aplicabilidad/equivalencias/receta, receta del
detalle, `model_type` del historial y la guarda de `preserveOnQuery`). Con los
arreglos, las 11 pasan.

L12. Suite focalizada del módulo: 20 archivos, 158 pruebas en verde (catálogo,
detalle, receta, tarifario, estadísticas, favoritos, recepción, tabla, títulos y
guards de UI).

## Delivery

Un solo pull request con seis commits de código y uno de documentación: el
contrato del código y la copia (`1f361eb7`), la receta y el duplicado
(`30f21d3b`), el historial y la puerta de edición (`c2d5f5cc`), la tabla de la
lista (`737a84da`), la vigencia del precio de sede (`845fc851`), el formulario
alimentado por el detalle (`4bb838ce`) y esta documentación. El corte de
revisión pasa de 400 líneas publicadas (~1500): los cuatro arreglos comparten
las superficies del módulo, cada issue conserva su commit y el PR es la unidad
de revisión, como en las entregas anteriores del mismo módulo.

L13. Verificación completa del worktree: Vitest `1417 pruebas en 166 archivos`;
suite MySQL `OK (2860 tests, 17400 assertions)`; Pint 911 archivos; ESLint 0
errores / 36 avisos; Prettier limpio; `vite build` en verde; doc-drift 14/14. El
`assess` nativo respondió `unassessable` (la facade evalúa el árbol del
repositorio principal y el trabajo está comprometido en el worktree, como en la
sesión #431-#433), sin `escalate`.

L14. Alcance del riesgo: el diff toca `UiDataTable` (primitiva compartida) sólo
de forma aditiva —`table-class` y `row-attributes` opcionales—, con su suite
previa intacta y una prueba nueva del contrato: `Risk: item 3` por tocar código
compartido, sin cambio de comportamiento para los consumidores existentes. Por
eso corre la verificación independiente (`gentle-ai-verify`): confirmó la
primitiva, la forma del payload de la copia, la receta y el alcance de
`preserveOnQuery`; dejó dos hallazgos. F1 era esta documentación aún sin
commitear (la cierra este commit). F2 era real: la fila del índice publica el
precio resuelto de la sede y no lleva la receta, así que editar desde ella
reescribía el precio de lista y, con el editor nuevo, vaciaba la receta; se
corrigió en `4bb838ce` con RED observado (`expected '180' to be '150'`) y GREEN
(8 pruebas del catálogo).

