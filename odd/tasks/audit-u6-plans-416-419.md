# Auditoría U6: buscador del catálogo y plan de tratamiento (#416, #417, #418, #419)

Cuatro defectos medidos por la auditoría UI (#369, sesión U6). #416 es del
selector de procedimientos; #417, #418 y #419 del plan de tratamiento. Rama
`fix/audit-u6-plans-416-419`, worktree `<ruta-local>`, base `46e05b8b`.

## Specs

S1. #416 esperado: «El buscador del catálogo consulta al servidor con el término
(el índice acepta `search`) y encuentra cualquier procedimiento, no sólo los de
la página cargada.»

S2. #416 ámbito: «`resources/js/components/procedures/ProcedureCatalogPicker.vue`.
Afecta a las dos superficies que lo montan (presupuestos y planes).»

S3. #417 esperado: «La lista, el detalle y la edición publican las líneas y el
total que la API tiene: o el índice las incluye, o la página pide el detalle
antes de abrir el modal.»

S4. #417 ámbito: «`TreatmentPlanController@index`, `TreatmentPlanService` (carga
de relaciones) y `TreatmentPlansPage` (apertura del detalle). El `GET /{id}` sí
trae las líneas: el defecto es del payload de lista y de la página que confía en
él.»

S5. #418 esperado: «O el campo es obligatorio y la interfaz lo dice (y lo valida
antes de enviar), o el servidor acepta una línea sin descripción derivando el
nombre del procedimiento.»

S6. #418 ámbito: «`resources/js/modules/treatment-plans/components/TreatmentPlanModal.vue`
y `app/Http/Requests/StoreTreatmentPlanRequest.php`.» Decisión de esta entrega:
el servidor acepta la línea sin descripción (la regla ya es `nullable` en el
`PUT` y en `POST /items`), la interfaz conserva «Descripción breve (opcional)» y
la descripción escrita se persiste en `procedure_description`.

S7. #419 esperado: «Cerrar el formulario devuelve al detalle intacto: el cierre
del formulario no limpia el plan seleccionado mientras el detalle esté abierto,
y no hay error de render.»

S8. #419 ámbito: «`resources/js/modules/treatment-plans/TreatmentPlansPage.vue`
(cierre del modal de edición) y el contrato de `TreatmentPlanDetail` (que exige
un plan completo).»

## Tasks

- T1 — S1, S2 — `ProcedureCatalogPicker` envía `q` al índice (el parámetro que
  el controlador lee; `search` no existe en `ProcedureCatalogController@index`)
  y pagina con el término vivo. Test: `procedure-catalog-picker.test.js`.
  Commit: `8f4fadac`.
- T2 — S3, S4 — El índice de planes publica el recurso con `items` y `progress`
  cargados. Tests: `TreatmentPlansIndexPayloadTest` y el caso nuevo de
  `treatment-plans.test.js`. Commit: `fdd69ff1`.
- T3 — S5, S6 — `items.*.description` es opcional en el alta y se guarda en
  `procedure_description`, también por las puertas de edición y de alta de
  línea. Test: `TreatmentPlanItemDescriptionTest` (5). Commit: `36b0e477`.
- T4 — S7, S8 — Cerrar el formulario sobre el detalle no limpia el plan, y
  guardar desde ese formulario refresca la ficha. Tests: caso nuevo en
  `treatment-plans.test.js`. Commit: `792bc949`.
- T5 — Documentación (§12 de AGENTS.md) y chequeos: Pint, ESLint, Prettier,
  doc-drift, Vitest, suite MySQL y `vite build`. Commit:
  `docs: changelog de la auditoria U6 de planes`.

## Delivery

Un solo pull request con un commit de unidad por issue y el commit de
documentación, la convención de los lotes de auditoría previos. Cada commit es
una rebanada revisable por sí sola: T1 (selector), T2 (payload del índice), T3
(contrato de la descripción) y T4 (cierre anidado). La primera rebanada del
lote cierra en T2 (`fdd69ff1`, 6 archivos, +343/−29 sobre `main`); la segunda llega hasta
T4 y la tercera es documentación y chequeos. Sin rutas, modelos, migraciones ni
permisos nuevos.

## Log

L1. Usuario: «haz las issues #416 #417 #418 #419 en un worktree nuevo».
L2. Baseline del worktree: `node_modules` enlazado al repo principal y `vendor`
propio (`composer install`; el enlace prestaba el autoload del repo principal y
los tests PHP corrían contra su `app/`), `.env` copiado, base de test
`odontosuite_u6_plans_test` sobre el MariaDB local por socket;
`TreatmentPlanPriorityTest` en verde (4 pruebas).
L3. #416 premisa corregida: el índice lee `q` (`ProcedureCatalogController@index`
y `ProcedureCatalogService::paginate`), no `search`; el buscador de recepción ya
se corrigió a `q` en #432. El arreglo usa `q`.
L4. #416 RED: la petición tras escribir `REST-COMP-1S` seguía siendo
`/api/procedure-catalog?page=1&per_page=12&is_active=true`, sin el término (2 de
3 pruebas en rojo); GREEN con el término en la petición, la paginación que lo
conserva y el término que se retira al vaciar la caja. Los smokes del catálogo,
del tarifario, del sitio del plan, de presupuestos y de especialidades siguen en
verde (30 pruebas).
L5. #417 RED: el índice no publicaba `items` ni `progress` (`Undefined array key
"items"` y `null` frente al nombre real); GREEN con
`TreatmentPlanResource::collection` sobre `items.consumptions` precargados, y el
caso del smoke que abre detalle y edición con las dos líneas del payload.
L6. #418 RED: el alta sin descripción respondía 422 y la descripción escrita
llegaba `null` a `procedure_description`; GREEN con la regla opcional y el mapeo
`description` → `procedure_description`, conservando el nombre obligatorio (422
y cero filas escritas). El mapeo vive en `prepareItemPayload`, compartido por la
edición y por `POST /treatment-plans/{id}/add-item`: las dos puertas quedan
cubiertas por la misma prueba, porque antes descartaban la descripción en
silencio.
L7. #419 RED: cerrar el formulario dejaba `selectedPlan` en `null` con el
detalle montado (`Cannot read properties of null (reading 'title')`) y guardar
sobre el detalle no refrescaba la ficha; GREEN con el cierre que conserva la
selección mientras el detalle viva y el `GET /{id}` posterior al guardado.
L8. Estrategia de entrega: un PR con un commit de unidad por issue y el de
documentación; la frontera de la primera rebanada cierra en T2 (`fdd69ff1`).
L9. Chequeos medidos sobre el árbol final: Pint 919 archivos, ESLint 0 errores /
36 avisos, Prettier limpio, `vite build` en verde, `doc-drift` 14/14, Vitest
**172 archivos / 1468 pruebas** y suite MySQL **OK (2890 pruebas, 17662
aserciones)**. Los avisos y el conteo de pruebas de la suite son los del árbol
medido, no los del último changelog de UI. La primera corrida MySQL falló dos
pruebas de `PatientValidationMessageLocaleTest` porque el shell exportó el
`.env` completo (`APP_ENV=local`, `APP_LOCALE=en`) y anuló `.env.testing`; con
sólo las claves `DB_*` exportadas la suite queda en verde. No es una regresión
del cambio.
