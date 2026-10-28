# Auditoría U8: tarifario en el índice, búsqueda de recepción y filtro de estado (#431, #432, #433)

Tres defectos medidos por la auditoría UI (#371, plan #13 §4 capas 4-5), todos
`crit:alta` y `type:defecto`: #431 y #433 `area:backend`, #432 `area:frontend`.
Rama `fix/audit-catalog-u8`, worktree `<ruta-local>`, base `56103c5d` (merge de
#473).

## Specs

S1. #431 esperado: «la cascada de #293 (convenio+sede → convenio → sede → lista)
se resuelve en todas las puertas del catálogo y la fila publica `list_cost` y
`price_source`, como ya hacen `search()` y `activeList()`.»

S2. #431 ámbito: «La corrección debe resolver la página completa en una sola
consulta (no una por fila), publicar `list_cost`/`price_source` en las filas del
índice y no tocar la cascada, el snapshot del presupuesto ni el tarifario.» «`show()`
queda fuera a propósito: su bloque «Costo por defecto» declara que es la lista y
el tab Tarifario publica la fila de la sede.»

S3. #432 esperado: «La consulta de recepción filtra por el término escrito, como
ya lo hace la lista del catálogo (que envía `q` y devuelve una fila al buscar por
nombre y por código).»

S4. #432 ámbito: «La corrección pertenece a la página (enviar `q`, el parámetro
que ya usan la lista y «Mis procedimientos»); si se decide aceptar `search` como
alias en el índice, debe ser aditivo y documentado, porque #416 vive en la misma
frontera.» Decisión de esta entrega: sólo la página, sin alias en el índice.

S5. #433 esperado: «Cada valor del filtro devuelve su propia población: `false` →
inactivos, `true` → activos, ausente → todos.»

S6. #433 ámbito: «La corrección es normalizar el valor antes del `where`, más un
test que fije `false`, `true` y ausente sobre una fila inactiva propia. No se toca
el contrato del recurso ni el filtro de especialidad.»

## Tasks

- T1 — S1, S2 — `ProcedureCatalogService::paginate()` resuelve el tarifario de la
  página en una sola consulta y el recurso publica `list_cost`/`price_source`;
  `show()` conserva la lista. Test: `ProcedureIndexPriceTest`. Commit: `15349953`.
- T2 — S3, S4 — `/reception-procedures` envía `q` al índice. Test: smoke de
  recepción con la URL pedida. Commit: `a7662913`.
- T3 — S5, S6 — `is_active` se normaliza con `filter_var(..., FILTER_VALIDATE_BOOLEAN)`
  antes del `where`. Test: `ProcedureIndexActiveFilterTest`. Commit: `3c640adb`.
- T4 — Documentación (§12) y chequeos: Pint, ESLint, Prettier, doc-drift, Vitest,
  suite MySQL. Commit: `docs: changelog de la auditoría U8 del catálogo`.

## Log

L1. Usuario: «haz las issues #431 #432 #433 en un worktree nuevo».
L2. Baseline del worktree: `composer install` y `pnpm install` en
`odontosuite-catalog-u8`; base de test `odontosuite_catalog_u8_test` creada sobre
el MariaDB local por socket (`skip-networking`).
L3. #431 RED: `default_cost` 500 ≠ 375, `list_cost` ausente y 0 consultas a
`procedure_prices` con la página de 5 filas; GREEN con las 4 pruebas nuevas y
`ProcedureBranchPriceTest` (13 tests).
L4. #432 RED: la petición salía como
`/api/procedure-catalog?search=REST-COMP-1S&…`; GREEN con `q` y el smoke de
recepción en 2 pruebas.
L5. #433 RED: `is_active=false` no incluía la fila inactiva propia (las ramas
`true` y ausente ya respondían lo suyo); GREEN con las 3 pruebas nuevas.
L6. Verificación completa del worktree: suite MySQL `OK (2846 tests, 17352
assertions)`; Vitest 1354 pruebas en 158 archivos; Pint 906 archivos; ESLint 0
errores / 36 avisos; Prettier limpio; doc-drift 14/14; `pnpm build` correcto.
L7. RDD: `assess` sobre el rango comprometido respondió `unassessable` (la
facade evalúa el repositorio principal y no el worktree); el plan no escaló
ningún ítem de alto riesgo, así que la autoverificación del escritor es la que
corre. Sin revisión nativa solicitada por el usuario.
