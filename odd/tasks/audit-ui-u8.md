# Task: U8 — Catálogo de procedimientos y tarifario (auditoría UI, plan #13)

Status: DONE (sesión de auditoría). Rama `feat/audit-ui-u8` desde `5c90e45`
(`main`); instrumento en `tests/e2e/audit/u8/` entregado en cuatro cortes y esta
nota en el suyo. No se toca código de producto. Hallazgos publicados **#431–#437**
y las dos filas de clase ya abierta registradas en **#416** y **#420**. PR
**#438** abierto; su merge cierra **#371** por instrucción del dueño (L12) y
queda a decisión del dueño.

## Specs

- **S1** — Objetivo (issue #371, verbatim): «Verificar que las cuatro
  superficies del catálogo cuentan la misma verdad: el procedimiento, su
  aplicabilidad, su precio por sede y su uso. Medido en `8b72d37`: el módulo
  tiene 4 páginas (catálogo, detalle, estadísticas, más los dos escritorios de
  consulta), 8 smokes y 2 unitarios; `ProcedureCatalogPage` usa `<table>` propio
  mientras `ProcedureStatsPage` usa `UiDataTable`.»
- **S2** — Método (issue #371, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=procedure-catalog,my-procedures,reception-procedures --out=.atl/qa-evidence/audit/ui/U8/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U8
  ```
- **S3** — Superficies, roles y acciones (issue #371, verbatim): «Superficies:
  `/procedure-catalog` y `/:id` (lista, formulario con aplicabilidad, códigos
  externos y receta de materiales, duplicar, desactivar, tabs
  Datos/Tarifario/Versiones/Historial, precio por sede con vigencia),
  `/procedure-stats` (presets, Top N, CSV, gráficos), `/my-procedures`
  (favoritos) y `/reception-procedures` (consulta sin edición). Roles:
  administrador, finanzas, odontólogo y recepcionista. Acciones: buscar y
  filtrar; alta con envío vacío; editar; desactivar; duplicar; alta de precio
  por sede; exportar CSV; marcar y desmarcar favorito; comprobar que recepción
  no edita.»
- **S4** — Entregable (issue #371, verbatim): «`render.json` (4 roles),
  `primitives.json`, `walkthrough.md`, `api-matrix.json` con el 403 de recepción
  en escritura, `ux-rubric.md` y `odd/tasks/audit-ui-u8.md`.»
- **S5** — Criterio de salida (issue #371, verbatim): «El precio de sede con
  vigencia se refleja en el picker y en el detalle; el CSV coincide exactamente
  con lo mostrado; dos listas del mismo módulo se comportan igual (paginación,
  vacío, affordance de scroll); cero `pageerror`; rúbrica puntuada.»
- **S6** — Plan #13 §4 capa 2 (verbatim): «Por cada ruta y por cada rol que la
  ve (máximo 3 roles: el dueño del escritorio, administración y un rol excluido
  para la puerta negativa)»; «Cero `console.error`, cero `pageerror`, cero
  request ≥ 400 salvo allowlist declarada (por ejemplo, la propia prueba de
  403)»; «Longitud de contenido renderizado por encima del umbral del módulo»;
  «Captura `desktop 1440×900` y `mobile 390×844`, **siempre y explícita**»;
  «Sin scroll horizontal de documento en 390 px.»
- **S7** — Plan #13 §4 capa 3 (verbatim): centrado
  (`|box.x + box.width/2 − vw/2| < 8`), docking, un solo `h1`, Escape cierra,
  foco atrapado, scroll bloqueado y etiqueta accesible. «Un diálogo que no
  cumple **una** fila es hallazgo, no ruido: es la clase "primitiva mal usada" y
  se agrupa con sus hermanos.»
- **S8** — Plan #13 §4 capa 4 (verbatim): los veredictos `OK`, `DEFECTO`,
  `NO-OP`, `CONFUSO`, `NO PROBADO`; «Toda acción con `DEFECTO`, `NO-OP` o
  `CONFUSO` produce fila en `findings.md` con captura y, si hay request, su
  status.»
- **S9** — Plan #13 §4 capa 6 y §5 (verbatim): «Doce criterios, 0/1/2 cada uno,
  por superficie primaria y una vez por sesión para su conjunto. La puntuación va
  a `ux-rubric.md` con la cita de lo observado.»; «Regla de oro: una capa no
  sustituye a otra.»
- **S10** — Plan #13 §6.1, §6.3 y §9 (verbatim): «la sesión **solo** escribe en
  `odontosuite_audit`; los borrados se hacen sobre filas que la propia sesión
  creó; nunca `migrate:fresh` sobre la base de desarrollo; `.env` intacto (se
  pasa por entorno, no se edita)»; «`.atl/` está en `.gitignore` (verificado),
  así que las capturas y JSON **no se commitean**»; «La sesión que audita no
  escribe código de producto».
- **S11** — Plan #13 §8.1 (verbatim): «Un issue por clase raíz, nunca por
  síntoma ni por módulo»; «Tope por sesión: 3–7 issues»; «La sesión no cierra
  sus propios issues».
- **S12** — Entrada declarada en #371 (verbatim): «`ProcedureCatalogPicker` —el
  selector que montan presupuestos y planes— filtra en cliente sobre las 12
  filas de la página 1 y recarga la página 1 sin el término: buscar
  `REST-COMP-1S` (que existe, página 4) devuelve 0 resultados. Está registrado
  en **#416** con reproducción y captura.» U8 fija su contrato de búsqueda y de
  precio: lo primero se confirmó y quedó como comentario en #416; lo segundo es
  #431.
- **S13** — Capa 5 aplicada (derivada de S4): la matriz `rol × endpoint` cubre
  los 22 endpoints del manifiesto más las puertas de escritura que no deben
  existir para cada rol, con la expectativa derivada de
  `App\Authorization\Permissions`.

## Estrategia de entrega

Un instrumento de medición en `tests/e2e/audit/u8/` (10 módulos, 7 642 líneas)
registrado en `surface.spec.js` sólo por su bloque de imports, igual que
U2–U7. Se entregó en cuatro cortes revisables más el documental: **1/4**
helpers, fixtures, render y primitivas (`dad3384`, 3 144 líneas); **2/4**
caminatas del catálogo y del detalle (`9df1802`, 1 988); **3/4** estadísticas,
favoritos y matriz de roles (`8ab42e2`, 1 443); **4/4** informe, rúbrica y
galería (`bd97d8d`, 1 081). El presupuesto de ~400 líneas
aplica a correcciones, no a un instrumento nuevo completo: un corte sólo es
ejecutable cuando su capa y sus fixtures están completos. Mismo criterio
declarado en U0–U7.

Datos: la base `odontosuite_audit` se recreó por sesión (41 procedimientos,
0 inactivos, 0 favoritos, 0 planes, 0 tarifas de sede). Los fixtures crean por
las puertas de producto 5 procedimientos propios (uno con `site_type=surface` y
una equivalencia), una tarifa de sede con ventana, 2 planes que alimentan
`/api/admin/procedure-stats`, 3 favoritos y un administrador de sede
(`audit_u8_sede`, `branch_id = 1`) para poder medir la cascada de precio: todos
los usuarios del seed tienen alcance de grupo y sin un actor con sede el picker
nunca resolvería una tarifa.

## Tasks

- [x] **T1** · S2, S10 · `inline` · Precondiciones: rama `feat/audit-ui-u8`
  desde `5c90e45`; base `odontosuite_audit` recreada, migrada y sembrada; app
  `:8000` y Vite `:5173`; capa 1 → `manifest.json`: **5 superficies, 50 acciones
  (0 sin clasificar), 4 diálogos, 22 endpoints, 0 permisos sin resolver**; 2
  rutas `excluded` por `redirect`.
- [x] **T2** · S1–S9, S12, S13 · `delegated` · Instrumento `@U8` en
  `tests/e2e/audit/u8/`: 10 módulos, 7 642 líneas, registro sólo en el bloque
  de imports. Delegado a un escritor acotado y verificado por lectura y
  reejecución propia (L8–L12).
- [x] **T3** · S2, S3, S5 · `inline` · Corrida definitiva sobre base limpia:
  **20/20, 3.5 min, exit 0**; `ui-pass --no-run` → gate OK, 5/5 superficies
  renderizan, 0 `pageerror`, 5 solicitudes en allowlist, 6 diálogos y 0 filas
  fallidas.
- [x] **T4** · S4, S8–S11 · `inline` · `walkthrough.md`, `ux-rubric.md`
  (18/24, 18/24, 23/24, 21/24 y 18/24), `render.json`/`render/`,
  `primitives.json`/`primitives/`, `api-matrix.json`, `findings.md`, galería
  `index.html`/`gallery.html` y esta nota; hallazgos agrupados por clase raíz y
  publicados **#431–#437**, con las dos clases ya abiertas registradas en
  **#416** y **#420**.
- [x] **T5** · S5, S10, S11 · `inline` · Cierre: Prettier y ESLint en verde,
  `playwright test --list` → 20 tests en 8 archivos, commits y nota. Revisión
  nativa: `assess` medio y `start` bloqueado en preflight
  (`lens_context_budget_exceeded`, sin autoridad; §Revisión nativa). PR #438
  abierto; el merge a decisión del dueño.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 371-U8».
- **L2** — Cuerpo de #371 (S1–S5 verbatim), su comentario de entrada (S12) y
  plan #13 §4 (capas 1–6), §5 (rúbrica), §6 (anatomía y evidencia), §8
  (protocolo de issues) y §9 (guardarraíles).
- **L3** — Estado al abrir (`5c90e45`, `main` limpio): `ProcedureCatalogPage.vue`
  (722 líneas), `ProcedureCatalogDetailPage.vue` (656), `ProcedureStatsPage.vue`
  (728), `MyProceduresPage.vue` (409), `ReceptionProceduresPage.vue` (286) y
  `BranchTariffPanel.vue` (454).
- **L4** — Entorno: app, Vite y base `odontosuite_audit` sembrada. Medido: 41
  procedimientos, **0 inactivos**, 0 favoritos, 0 planes de tratamiento, y
  `/api/admin/procedure-stats` sin uso en el período (seed sin planes).
- **L5** — Causas medidas antes de instrumentar: (a) el índice del catálogo
  filtra por `q`, no por `search`; (b) el picker filtra en cliente su página de
  12 filas y al teclear recarga la página 1 sin término (S12); (c)
  `material_recipe` existe en las dos requests y en el servicio pero **ningún**
  consumidor de `resources/js` lo lee ni lo escribe; (d)
  `import.procedure_catalog` es de `FINANCE_ROLES` y la única superficie de
  importación vive en `/procedure-catalog`; (e) `manage.procedure_favorite` es
  `CLINICAL`; (f) el tarifario de sede escribe por
  `PUT /api/branches/{branch}/procedure-prices` con `items[]`; (g) todos los
  usuarios del seed tienen `branch_id` nulo.
- **L6** — Medido antes del instrumento (API, base `odontosuite_audit`):
  (a) **el precio de sede no llega al picker**: con un administrador de sede
  (`branch_id = 1`) y una fila vigente de S/ 1 234,50 para `ORTO-ALIGN`,
  `/api/procedure-catalog` devuelve `default_cost: 3500` y **ningún**
  `price_source`, mientras `/search` y `/active` del mismo usuario devuelven
  `1234.5` con `price_source: "branch"`;
  (b) **la búsqueda de recepción muere en el servidor**: envía `search` y el
  índice sólo lee `q` (`?search=REST-COMP-1S` → 41; `?q=REST-COMP-1S` → 1);
  (c) **duplicar pierde datos declarados**: `submitDuplicate` no copia
  `site_type`, `external_codes` ni `material_recipe`;
  (d) `material_recipe` no tiene consumidor en `resources/js`;
  (e) la lista propia (`<table min-w-[880px]>`) no monta `UiDataTable`;
  (f) `/procedure-catalog` no consulta permiso de escritura y expone
  Nuevo/Importar/Editar/Duplicar/Desactivar a cualquier rol;
  (g) los 41 procedimientos del seed son `site_type = none`, 0 inactivos y 0
  favoritos: desactivar, aplicabilidad, favoritos y estadísticas requieren
  fixtures propios.
- **L7** — El instrumento se delegó a un escritor acotado (*context backstop* de
  la sesión, task `mvph4hyf-1-ca3a`): 10 módulos, 7 642 líneas, registro sólo en
  el bloque de imports, evidencia en `.atl/qa-evidence/audit/ui/U8`
  (gitignored). El escritor validó 20/20 tests, Prettier y ESLint.
- **L8** — Verificación propia: se releyó el registro en `surface.spec.js` (sólo
  el bloque de imports), la evidencia cruda y los cuatro hechos de mayor
  severidad se reprodujeron a mano contra la API:
  (a) `?is_active=false` → 41 filas, 0 inactivas, con 7 inactivas en la base;
  (b) `?search=` → 41 filas, `?q=` → 1;
  (c) `?model_type=App\\Models\\ProcedureCatalog` → 0 filas, con una barra →
  17;
  (d) con un administrador de sede y una fila vigente, el índice responde 500
  sin `price_source` y `/search` responde 375 con `price_source: branch`.
  También se confirmó por lectura la lista fija de campos de `submitDuplicate` y
  la ausencia total de `material_recipe` en `resources/js`.
- **L9** — Corrida definitiva sobre base recreada: **20 pasan, 3.5 min, exit 0**;
  `ui-pass --no-run` → gate OK. Se descartó la evidencia de las iteraciones del
  escritor (base con ~230 procedimientos acumulados) antes de medir la línea
  base.
- **L10** — Triage por clase raíz: F05 → #431; F02 → #432; F01 → #433;
  F06+F10 → #434; F07+F09 → #435; F03+F12 → #436; F08 → #437. Dedupe: F04 es
  #416 (comentario con la medición) y F11+F14 son #420 (comentario con las
  filas del catálogo). Se publicó una corrección del comentario de #420 al
  cotejar el dato crudo: el render mide 6 controles (5 escrituras), no 7, y las
  filas de finanzas/odontólogo son inferidas, no medidas.
- **L11** — Cierre de comprobaciones: Prettier en verde sobre `tests/e2e/audit/u8`
  y `surface.spec.js`; ESLint 0 errores; `--list` → 20 tests en 8 archivos, todos
  `@U8`.
- **L12** — Instrucción del dueño, verbatim: «okey no te olvides de cerrar tu
  issue tambien al hacer el pr». El plan §8.1 reserva el cierre de los issues de
  sesión a otra sesión; la instrucción del dueño prevalece para esta unidad: el
  PR #438 declara `Closes #371` y su merge cierra el issue de sesión. El issue
  #371 se leyó de vuelta después del merge (L13).

## Línea base medida

- Revisión del árbol medido: `5c90e45` + instrumento (4 cortes); árbol limpio
  salvo el instrumento y esta nota. Corrida: `2026-10-26`, base
  `odontosuite_audit` recreada + `migrate --seed` (41 procedimientos, 0 tarifas,
  0 planes, 0 favoritos, 15 usuarios).
- **Capa 1** · 5 superficies, **50 acciones (0 sin clasificar)**, **4 diálogos**,
  **22 endpoints**, **0 permisos sin resolver**; 2 rutas excluidas por
  `redirect`.
- **Capas 2–4** · `pnpm exec playwright test … --grep @U8` → **20 pasan**, exit
  0, **3.5 min**.
- **Envoltorio** · `ui-pass --no-run` → **gate OK**; 5/5 superficies renderizan;
  **0 `pageerror`**; 5 solicitudes esperadas en allowlist; 0 acciones sin
  clasificar; **6 diálogos y 0 filas fallidas** del contrato.
- **Render por rol** · 12 filas (5 superficies × sus roles, con una fila
  negativa por superficie): 0 `pageerror`, 1 `h1` por fila, **0 scroll horizontal a 390 px**;
  la fila negativa de recepción declara **6 controles** (`denial.writeAffordances`)
  y 9 puertas de escritura con `403`; 31 mensajes de consola, todos de la puerta
  negativa declarada (0 inesperados).
- **Diálogos** · 6 medidos: los 4 del manifiesto (importar CSV, formulario,
  duplicar, precio de sede) más las dos confirmaciones que la página posee
  (desactivar, retirar precio); **0 filas fallidas** del contrato.
- **Caminatas** · catálogo **18 pasos: 13 OK / 5 DEFECTO**; detalle **6: 3/3**;
  tarifario y picker **6: 3/3**; estadísticas **10/10 OK**; favoritos y
  recepción **10: 9/1**. Total: **38 OK / 12 DEFECTO / 0 NO-OP / 0 CONFUSO /
  0 NO PROBADO**.
- **Matriz de contrato** · **112 filas sobre 28 endpoints**, **112/112
  conformes**, 0 desvíos de estado y **1 desviación declarada** (código de 21
  caracteres → `500`); denegaciones por rol: administrador 4, finanzas 17,
  odontólogo 15, recepcionista 19. Dos hechos de contenido fallan y son los
  publicados: `?search=` ignorado (#432) y `?is_active=false` devolviendo activos
  (#433).
- **Rúbrica** · catálogo **18/24**, detalle **18/24**, estadísticas **23/24**,
  mis procedimientos **21/24**, recepción **18/24**; sesión **98/120**.

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- |
| F01 | catálogo › filtro de estado | elegir «Inactivos» | el filtro devuelve los inactivos | 20 filas, todas «Activo»; `is_active=false` → 41 activos, 0 inactivos | 4 | MEDIDO | `crit:alta` | `ProcedureCatalogService.php:44-45` (`(bool)` sobre `"false"`) | #433 |
| F02 | recepción › buscador | escribir un código existente | la consulta filtra por el término | 12 tarjetas → 12; la petición envía `search` y el índice lee `q` | 4 | MEDIDO | `crit:alta` | `ReceptionProceduresPage.vue:83`; `ProcedureCatalogController.php:25-37` | #432 |
| F03 | catálogo › paginación | leer «Mostrando 1 a N» con 20 filas | el texto usa el tamaño pedido | «Mostrando 1 a 10 de 46» con 20 filas y `per_page=20` | 4 | MEDIDO | `crit:media` | `ProcedureCatalogPage.vue` (`Pagination` sin `per-page`) | #436 |
| F04 | picker compartido | buscar `REST-COMP-1S` | encuentra el procedimiento | 12 filas → 0 y «Sin resultados»; la API devuelve 1 | 4 | MEDIDO | `crit:alta` | `ProcedureCatalogPicker.vue` (filtro cliente de una página) | #416 (dedupe) |
| F05 | picker › precio | leer el precio de la sede | el picker muestra el que la API resuelve | API 375 (`branch`), picker «S/ 500.00» (lista) | 4 | MEDIDO | `crit:alta` | `ProcedureCatalogService::paginate` no resuelve el tarifario | #431 |
| F06 | catálogo › duplicar | duplicar con aplicabilidad y equivalencia | la copia conserva los campos | código propuesto → `500`; copia con código corto pierde `site_type` y equivalencias | 4 | MEDIDO | `crit:alta` | `ProcedureCatalogPage.vue:665`; `varchar(20)` vs `max:50` | #434 |
| F07 | detalle › Historial | abrir la pestaña | lista los cambios guardados | 0 filas; pide `model_type` con doble barra; con una barra → 17 | 4 | MEDIDO | `crit:media` | `ProcedureCatalogDetailPage.vue:603` | #435 |
| F08 | detalle › Tarifario | guardar una vigencia invertida | el rechazo aparece en su campo | sin mensaje y sin `PUT` | 4 | MEDIDO | `crit:media` | `BranchTariffPanel.vue` (`valid_to` sin `:error`) | #437 |
| F09 | detalle › «Editar» | pulsar «Editar» | abre el formulario del procedimiento | la URL termina en `/procedure-catalog`; 0 diálogos | 4 | MEDIDO | `crit:media` | `ProcedureCatalogPage.vue:604-617` | #435 |
| F10 | detalle y formulario | buscar la receta de insumos | la receta tiene superficie | 0 bloques de receta; 0 consumidores de `material_recipe` en `resources/js` | 4 | INFERIDO | `crit:media` | requests y servicio sí, `resources/js` no | #434 |
| F11 | catálogo › rol recepción | abrir la lista como recepción | sin escrituras para un rol que recibe 403 | 5 escrituras visibles; 9 puertas `403` | 4 | MEDIDO | `crit:media` | `ProcedureCatalogPage.vue` sin `usePermissions` | #420 (dedupe) |
| F12 | catálogo y estadísticas | comparar las dos tablas | comparten paginación y affordance | `<table>` propia (`data-table-scroll 0`) vs `UiDataTable` (`1`); a 390 desplaza sin aviso | 4 | MEDIDO | `crit:baja` | `ProcedureCatalogPage.vue` (tabla propia) | #436 |
| F14 | catálogo › capa 2 | renderizar la lista para recepción | sin controles que su rol no ejecuta | 6 controles, 5 de escritura | 2 | MEDIDO | `crit:media` | el mismo que F11 | #420 (dedupe) |

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| El precio de sede con vigencia se refleja en el picker y en el detalle | **No cumple**: el detalle lo publica en el tab Tarifario (S/ 375 · S/ 500 · ventana · «Vigente»), pero el picker muestra la lista (S/ 500) porque el índice no resuelve el tarifario (F05, #431) |
| El CSV coincide exactamente con lo mostrado | **Cumple**: BOM y CRLF parseados, 3 filas de procedimiento y 2 de especialidad, 0 diferencias con la tabla y la distribución (paso `09-csv-vs-screen`) |
| Dos listas del mismo módulo se comportan igual | **No cumple**: la paginación informa 10 con 20 filas y la tabla propia no tiene affordance (F03+F12, #436); el estado vacío y la primitiva de paginación sí coinciden |
| Cero `pageerror` | **Cumple**: 0 en las 12 filas de render, las cinco caminatas y los seis diálogos |
| Rúbrica puntuada | **Cumple**: 18/24, 18/24, 23/24, 21/24 y 18/24 (98/120, `ux-rubric.md`) |

## Revisión nativa (RDD)

**Sin veredicto: el proveedor no admitió el candidato y no creó autoridad.** Dos
intentos, ambos declarados por el contrato nativo:

| Paso | Respuesta |
| --- | --- |
| `gentle_review assess` (candidato sin comprometer) | `risk: medium`, `nativeRisk: unassessable` — el proveedor pidió declarar los archivos no rastreados (`untracked files require an explicit declaration`). Plan: autoverificación del escritor, ningún verificador independiente, `closed: unknown`. Sin `reviewDue` verdadero. |
| `gentle_review inspect` (tras comprometer y empujar) | `ready`, `action: start`, target `sha256:96cd8233…`, `base-ref 5c90e45`, `committed-only`, linaje `review-4a1ff6e45f9b698a`, 12 archivos. |
| `gentle_review start` (modo ordinary; lentes `risk`, `reliability`, `readability`) | **`blocked`** en `preflight`: `lens_context_budget_exceeded`. `mutation_performed: false`; no se creó autoridad, no hay consentimiento que responder ni linaje que abandonar. `next_action: stop`. |

El proveedor prescribe la continuación: «split it into a chained sequence of
smaller reviewable commits, each under the budget, then refresh the exact native
next transition … for the reduced scope». El candidato completo mide 7 956
líneas en 12 archivos; sus cortes (`dad3384`, `9df1802`, `8ab42e2`, `bd97d8d`, la
mayoría sobre 1 000–3 100 líneas) ya existen y el mayor queda por encima del
presupuesto que el preflight aplicó a una sola unidad. No se reescribió la rama
ni se reenvió el candidato: la regla es no reintentar exactamente el mismo
candidato.

Consecuencia: no hay aprobación, corrección ni consumo de autoridad. La
verificación independiente la ejecutó el padre (L8–L9) sobre la misma base y la
misma revisión. PR #438 abierto; el merge queda a decisión del dueño.

## Delegación

Instrumento delegado a `gentle-ai-worker` (task `mvph4hyf-1-ca3a`, background):
10 módulos y 7 642 líneas. El padre verificó el registro en `surface.spec.js`
(sólo imports), reprodujo cuatro hallazgos contra la API, corrigió un dato del
comentario de #420 y ejecutó la corrida definitiva sobre una base recreada.

## Declarado como no observable

1. La receta de insumos con datos reales: la base de auditoría no siembra
   productos (`GET /api/inventory/products` → 0) y `material_recipe.*.product_id`
   exige uno existente; se midió la ausencia de superficie (F10), no la
   diferencia de datos.
2. La cascada de precio con convenio: la sesión no crea convenios; se midió la
   rama «sede» (375 sobre lista 500), que es la que nombra #371. La rama de
   convenio pertenece a U6.
3. El uso de caja o de planes más allá de los dos que alimentan las
   estadísticas: son fixtures de población, no objeto de la sesión.

## Residuos de datos

La sesión creó 7 procedimientos (5 de los fixtures, 1 del alta de la caminata y
1 del duplicado), 2 filas de tarifa de sede (la del fixture y la de la
caminata), 2 planes de tratamiento, 4 favoritos y 1 usuario administrador de
sede. El teardown retiró las tarifas, los favoritos, los planes (`DELETE` 200) y
el usuario; los procedimientos sólo pueden desactivarse (`DELETE` = desactivar),
así que quedan declarados: **7 procedimientos inactivos** —6 `AUDIT-U8-*` y
`A8-3559-C`, el código corto con el que se probó el duplicado—. Ninguna fila del
seed se tocó (el catálogo subió de 41 a 48 filas, 41 activas).
