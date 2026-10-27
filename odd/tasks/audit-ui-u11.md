# Task: U11 — Consistencia transversal entre módulos (auditoría UI, plan #13)

Status: DONE (sesión de auditoría). Rama `feat/audit-ui-u11` desde `5b1cc02`
(`main`). No se toca código de producto: la sesión mide y documenta en
`tests/e2e/audit/u11/` y deja la evidencia en `.atl/qa-evidence/audit/ui/U11/`.

## Estrategia de entrega

El presupuesto de ~400 líneas aplica a correcciones, no a un instrumento nuevo
completo: un corte sólo es ejecutable cuando su capa y sus fixtures están
completos. Mismo criterio declarado en U0–U10. La entrega va en cortes revisables:
**1** ejes estáticos y matriz de consistencia; **2** las filas de navegador
(render y diálogos) y el workflow de CI; **3** la corrida, el triage y los issues;
**4** esta nota, la matriz publicada y el PR. El merge y la publicación quedan a
decisión del dueño.

Frontera del primer corte: se anota en el Log cuando exista el commit.

## Specs

- **S1** — Objetivo (issue #374, verbatim): «Encontrar la divergencia que ninguna
  sesión individual puede ver: el mismo problema resuelto de dos maneras en dos
  módulos hermanos. En particular, si un diálogo aparece centrado en un módulo y
  al costado en otro. Medido en `8b72d37`: 18 módulos usan `UiModal` centrado,
  uno tiene overlay propio, uno da de alta en `UiSheet`, 4 listas usan `<table>`
  propio frente a 14 con `UiDataTable`, y 4 páginas no tienen placeholder de
  carga.»
- **S2** — Método (issue #374, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --all --out=.atl/qa-evidence/audit/ui/U11/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U11
  # matriz: 36 rutas × 12 ejes, sobre los artefactos de U1-U10 + esta pasada
  ```
- **S3** — Ejes de la matriz (issue #374, verbatim): «diálogo (modal centrado vs
  sheet anclado), lista, título, botonera, estados, feedback, filtros, badges de
  estado, formato de fecha/hora/moneda, responsive, densidad de texto y
  repetición de información. Cada fila con veredicto, captura y módulo de
  referencia.»
- **S4** — Entregable (issue #374, verbatim): «`consistency-matrix.md` con los 12
  ejes × 36 rutas, un issue por clase de divergencia (no por módulo), el canon
  propuesto (qué primitiva gana y por qué) y `odd/tasks/audit-ui-u11.md`.»
- **S5** — Criterio de salida (issue #374, verbatim): «Los 12 ejes tienen
  veredicto y dueño; ninguna fila queda en "depende"; toda divergencia tiene
  issue o aceptación escrita; el canon propuesto cita la primitiva y el módulo
  de referencia.»
- **S6** — Plan #13 §4 capa 2 (verbatim): «Por cada ruta y por cada rol que la
  ve (máximo 3 roles: el dueño del escritorio, administración y un rol excluido
  para la puerta negativa)»; «Cero `console.error`, cero `pageerror`, cero
  request ≥ 400 salvo allowlist declarada (por ejemplo, la propia prueba de
  403)»; «Longitud de contenido renderizado por encima del umbral del módulo»;
  «Captura `desktop 1440×900` y `mobile 390×844`, **siempre y explícita**»;
  «Sin scroll horizontal de documento en 390 px.»
- **S7** — Plan #13 §4 capa 3 (verbatim): «Centrado horizontal
  (`|box.x + box.width/2 − viewportWidth/2| < 8`)»; «Docking: Un `UiSheet` debe
  tocar el borde declarado»; «Un solo `h1`»; «Escape cierra»; «Foco atrapado»;
  «Scroll bloqueado»; «Etiqueta accesible». «Un diálogo que no cumple **una**
  fila es hallazgo, no ruido: es la clase "primitiva mal usada" y se agrupa con
  sus hermanos.»
- **S8** — Plan #13 §5 (verbatim, criterios que esta sesión mide): «Densidad de
  texto — Palabras del hero/subtítulo y párrafos explicativos antes del primer
  control — 0: ≥ 40 palabras o párrafo > 2 líneas por bloque — 2: ≤ 20 palabras,
  el control manda»; «Información repetida — Mismo dato dos veces en la misma
  vista sin función distinta»; «Estados completos — Carga (skeleton/spinner),
  vacío con CTA, error con reintento»; «Primitivas — `UiButton`, `UiModal`,
  `UiDataTable`, `UiEmptyState`, `UiTabs`; sin overlay propio»; «Feedback — Toast
  para acción sin superficie; error inline en el campo; confirmación en
  destructivo»; «Responsive — 390 px: sin solapamiento, sin scroll horizontal de
  documento, tabla con affordance»; «Formato de datos — Fecha, hora, moneda y
  estado con un solo formato y un solo badge». «Un criterio en `1` en dos o más
  módulos es clase raíz del issue transversal (U11).»
- **S9** — Plan #13 §6.1 y §9 (verbatim): «la sesión **solo** escribe en
  `odontosuite_audit`; los borrados se hacen sobre filas que la propia sesión
  creó; nunca `migrate:fresh` sobre la base de desarrollo; `.env` intacto»;
  «`.atl/` está en `.gitignore` (verificado), así que las capturas y JSON **no se
  commitean**»; «La sesión que audita no escribe código de producto».
- **S10** — Plan #13 §8.1 (verbatim): «Un issue por clase raíz, nunca por
  síntoma ni por módulo»; «Tope por sesión: 3–7 issues»; «La sesión no cierra
  sus propios issues».
- **S11** — Plan #13 §12 P2, P3 y P5 (verbatim): «P2 — Listas divergentes: 4
  páginas con `<table>` propio frente a 14 con `UiDataTable`. Si la paginación,
  el vacío o el affordance de scroll difieren, es una clase raíz.»; «P3 —
  Diálogos: 1 overlay propio (`ConsultationWizard`) y 1 flujo de alta en
  `UiSheet` (Análisis IA) frente a 18 módulos con `UiModal` centrado. Verificar
  si el usuario percibe dos lenguajes de "ventana".»; «P5 — Vocabulario: 1
  archivo con overlay propio y 18 módulos con la primitiva; 0
  `window.confirm`/`alert` en todo el frontend».

## Tasks

- [x] **T1** · S2, S9 · `inline` · Precondiciones: rama `feat/audit-ui-u11`;
  app servida con `DB_DATABASE=odontosuite_audit` y assets construidos; capa 1 →
  `manifest.json` de las 36 rutas.
- [x] **T2** · S1, S3–S8, S11 · `delegated` · Instrumento `@U11` en
  `tests/e2e/audit/u11/`: ejes estáticos, medición de navegador, contrato de
  diálogos, matriz, findings y gate.
- [x] **T3** · S2, S6 · `inline` · Corrida completa del instrumento y `gate.json`
  con cero `pageerror` y 36 filas de render.
- [x] **T4** · S4, S5, S8, S10 · `inline` · Triage por clase raíz, issues
  publicados, `consistency-matrix.md` y el canon propuesto.
- [x] **T5** · S5, S9 · `inline` · Cierre: Prettier y ESLint en verde,
  `playwright test --list`, commits por corte, esta nota y PR.


## Cortes de entrega

El instrumento es una unidad de comportamiento nueva, no una corrección: un
corte sólo es ejecutable cuando su capa está completa, y el presupuesto de ~400
líneas aplica a correcciones. Los cinco commits se revisan en este orden:

1. `9a94099` — `test(audit): mide los doce ejes…`: la capa estática
   (`static.js`), la medición de navegador (`measure.js`) y el generador de la
   matriz y del gate (`report.js`).
2. `41eba98` — `test(audit): camina las 36 rutas…`: las filas de capa 2 y 3
   (`render.js`, `primitives.js`) y su registro en `surface.spec.js`.
3. `928cf6f` — `ci(audit): ejecuta la matriz de U11…`: el job traduce `all` a
   `--all` y corre la capa estática y la matriz en CI.
4. `eb148ad` — `docs(audit): publica la matriz…`: la matriz 36×12 con el canon.
5. `57e8dd6` — `docs(audit): registra la sesión U11…`: esta nota.
6. `8118f4e` + `e912574` + `962e57a` — la corrección pedida por el refuter
   nativo (R3-001): el arreglo, el revert que devuelve el árbol al candidato
   revisado para intentar el plan de corrección, y su reaplicación. El diff neto
   es el arreglo (20 líneas); los tres commits cuentan el intento completo.

## Línea base medida

- Revisión medida: `5b1cc02` sobre `main`; árbol limpio salvo el instrumento y
  esta nota. Misma base `odontosuite_audit` que U10 (15 usuarios, 3 sedes, 106
  citas, 372 transacciones, 100 pacientes); U11 no escribe ni borra una fila.
  App servida en `127.0.0.1:8000` con `DB_DATABASE=odontosuite_audit` y assets
  construidos (`public/hot` retirado).
- **Capa 1** · **36 superficies, 509 acciones (0 sin clasificar), 59 diálogos,
  140 endpoints, 3 permisos sin resolver**; 2 rutas `excluded` por `redirect`.
- **Ejes estáticos** · **36 filas, 169 archivos escaneados, 1 341 imports (1 179
  no expandidos)**: 26 páginas importan `Modal` y 1 `Sheet`, 2 declaran overlay
  propio; 16 rutas usan `DataTable` y 4 publican `<table>` en su página; 19
  declaran `Skeleton` y 18 `LoadingSpinner`; 26 usan `useToast` y 23 declaran
  error inline; 3 usan `FilterBar` y 12 tienen reset; 2 usan `StatusPill` y 24
  `StatusBadge`; 19 usan el helper de moneda, 20 formatean fecha con
  `toLocale*` y 6 con `Intl`.
- **Capa 2** · **36 filas de render** (una por ruta, rol dueño), **0
  `pageerror`**, **0 scroll horizontal a 390 px (36/36)**, una sola `h1` en
  35/36 (el 404 pinta dos, #381), todas las filas asentadas. La sonda de carga
  retiene todas las respuestas `/api/**` 1.5 s y muestrea 2.8 s.
- **Capa 3** · **26 filas de diálogo, 39 mediciones (9 abrieron, 30 sin
  trigger), 63 comprobaciones, 2 fallas**: el foco no queda atrapado en el alta
  de Análisis IA (sheet y modal), ya cubierto por #385.
- **Veredicto por eje** · diálogo 23 OK · 2 DIV · 1 ACE · 10 N/A; lista 32/4;
  título 35/1; botonera 24/12; estados 26/7/3 ACE; feedback 23/7/6 N/A; filtros
  3/12/21 N/A; badges 34/2; formato 16/10/10 N/A; responsive 36 OK; densidad 17
  OK · 19 N/A; repetición 31/5. **62 hallazgos ruta × eje** (`findings.raw.json`).
- **Gate** · `gate.json` → `ok: true`: 0 `pageerror`, 0 request inesperado, 36/36
  filas, 12 ejes con veredicto. Los cuatro 403 de `/patients/:id` se declaran en
  `denials.json` con su dueño (#399); las aceptaciones (`Sheet` de Análisis IA y
  la carga de las dos pantallas de sesión y del 404) en `acceptances.json`.

## Hallazgos

Un issue por clase raíz, no por módulo. Las clases nuevas son seis; el resto de
los ejes divergentes ya tiene issue abierto y se cita como dueño.

| F# | Eje | Clase raíz | Rutas medidas | Issue |
| -- | --- | --- | --- | --- |
| F01 | `format` | Dos módulos de formato con comportamiento distinto (`S/ NaN` vs `S/ 0.00`; `formatDateShort` retrocede un día fuera de UTC), dos locales y dos relojes; 20 `formatDate` locales | `/dashboard` `/patients` `/patients/:id` `/procedure-catalog` `/procedure-stats` `/reception-procedures` `/business-intelligence` `/cash-register` `/medical-records` `/medical-records/:id` | **#451** |
| F02 | `states` | La espera se representa con tres vocabularios (esqueleto, spinner, texto suelto) y siete superficies no la representan | `/professionals` `/professionals/:id` `/appointment-types/:id` `/procedure-catalog/:id` `/cash-register` `/cash-register/ready-to-bill` `/specialty-records` | **#452** |
| F03 | `list` | Cuatro listas con `<table>` propio: dos paginan y dos no, dos vacíos distintos y ningún affordance de scroll | `/patients` `/professionals` `/appointment-types` `/procedure-catalog` | **#453** |
| F04 | `badges` | Dos primitivas de insignia; `StatusPill` quedó como alias de dominio con mapa muerto y otra escala de color | `/ai-analysis` `/branch-transfers` | **#454** |
| F05 | `buttons` | La misma alta se llama «Nuevo/Nueva X», «Crear X», «Agregar X» o «Registrar X» (23 etiquetas alternativas) | `/calendar` `/my-day` `/patients/:id` `/professionals` `/environments` `/appointment-types` `/procedure-catalog` `/cash-register` `/treatment-plans` `/quotations` `/medical-records` `/medical-records/:id` | **#455** |
| F06 | `filters` | Doce superficies filtran y sólo tres usan `FilterBar`; el reset se llama «Limpiar filtros», «Limpiar» o no existe | `/patients/:id` `/professionals` `/environments` `/specialties` `/procedure-stats` `/reception-procedures` `/business-intelligence` `/treatment-plans` `/quotations` `/ai-analysis` `/settings/branches` `/settings/payment-methods` | **#456** |
| F07 | `dialog` | El asistente de cierre es un overlay propio, sin rol de diálogo, foco atrapado ni Escape | `/calendar` `/my-day` | #391 |
| F08 | `title` | El 404 dentro del shell pinta dos `h1` | `/:pathMatch(.*)*` | #381 |
| F09 | `feedback` | El 422 se pierde: la pantalla dice «Error de validación» o un toast genérico | `/dashboard` `/patients` `/environments` `/appointment-types` `/appointment-types/:id` `/my-procedures` `/cash-register/ready-to-bill` | #415 |
| F10 | `repetition` | El mismo dato dos veces en la vista (KPI + tabla, número de historia 3×, título y breadcrumb) | `/patients/:id` `/environments/:id` `/business-intelligence` `/medical-records/:id` `/settings/branches/:id` | #405 |
| F11 | `dialog` | El sheet de «Nuevo análisis» no atrapa el foco (2/63 comprobaciones de capa 3) | `/ai-analysis` | #385 |
| F12 | — | La ficha pide al rol de recepción los tabs clínicos que la API le niega (4 request 403 declarados) | `/patients/:id` | #399 |

### Aceptaciones escritas (S5)

- **`dialog`: `/ai-analysis`** — el alta de un análisis es un flujo largo con
  pasos y vista previa; el `Sheet` anclado es el idioma declarado para ese caso y
  el canon lo reserva. Su accesibilidad se sigue en #385. Registrada en
  `acceptances.json`.
- **`states`: `/login`, `/reset-password`, `/:pathMatch(.*)*`** — no cargan
  datos; no hay espera que representar. Registradas en `acceptances.json`.

La densidad de las 19 rutas sin hero no es una aceptación: es la regla del eje
(`N/A` cuando no hay hero band, porque el título lo lleva el topbar).

## Canon propuesto

Cada eje cita la primitiva que gana y el módulo que hoy la usa bien.

| Eje | Primitiva/helper que gana | Módulo de referencia | Por qué |
| --- | --- | --- | --- |
| Diálogo | `Modal.vue` centrado; `Sheet.vue` sólo por aceptación escrita | `/settings/payment-methods` | Centrado, foco atrapado, scroll bloqueado y Escape resueltos por la primitiva |
| Lista | `DataTable.vue` + `Pagination.vue` | `/settings/payment-methods` | Scroll interno con affordance, paginación y hueco de vacío en una sola pieza |
| Título | `h1` del shell (`data-topbar-title-owner`) + `<h2 data-hero-title>` de la página | `/settings/accounting-locks` | Un solo `h1` por documento y el topbar cede el título cuando la página lo pinta |
| Botonera | `UiButton` + «Nuevo/Nueva <sustantivo>» y un verbo con objeto por envío | `/patients` | El mismo verbo para el mismo acto; `Cancelar`/`Volver`/`Cerrar` según el contexto de la ventana |
| Estados | `Skeleton`/`LoadingSpinner` + `EmptyState` | `/settings/accounting-locks` | La espera no salta el layout y el vacío ofrece la acción que lo resuelve |
| Feedback | `:error` en el campo; toast sólo para la acción sin superficie | `/cash-register` | El 422 cae donde se provocó |
| Filtros | `FilterBar.vue` con «Limpiar filtros» | `/business-intelligence` | Una barra, un verbo de limpieza |
| Badges | `StatusBadge.vue` | `/treatment-plans` | Una insignia genérica (`variant` + `label`); `StatusPill` se depreca |
| Formato | `useFormatters.formatCurrency` + un helper de fecha compartido, una locale y un reloj | `/cash-register` | Un solo formato para el mismo dato, sin `S/ NaN` ni fechas desplazadas |
| Responsive | `DataTable` con scroll interno; documento sin desborde a 390 px | `/medical-records` | La tabla resuelve su ancho, el documento no se desplaza |
| Densidad | hero band con título ≤ 6 palabras y subtítulo ≤ 30 | `/settings/accounting-locks` | El control manda; sin hero, el eje no aplica |
| Repetición | KPI + tabla como fuentes únicas del dato | `/settings/branches` | El mismo dato no se publica dos veces sin función distinta |

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| Los 12 ejes tienen veredicto y dueño | **Cumple**: 12/12 con `axisSummary` y dueño (seis issues nuevos, cinco dueños preexistentes y las aceptaciones de `acceptances.json`) |
| Ninguna fila queda en «depende» | **Cumple**: 432 celdas en `OK`, `DIV`, `ACE` o `N/A`; 0 `NO PROBADO` |
| Toda divergencia tiene issue o aceptación escrita | **Cumple**: 62 hallazgos ruta × eje agrupados en 12 clases; seis issues (#451–#456), cinco clases con issue abierto y las aceptaciones escritas en `acceptances.json` |
| El canon cita la primitiva y el módulo de referencia | **Cumple**: tabla de arriba y sección `Canon propuesto` de `consistency-matrix.md` |
| Cero `pageerror` y cero request inesperado | **Cumple**: 0 y 0; los cuatro 403 de la ficha se declaran con dueño (#399) |

## Rúbrica §5 (proyección transversal)

La puntuación por superficie vive en `ux-rubric.md` de U1–U10; U11 mide los
criterios que sólo se ven cruzando módulos. Cada uno se lee de la matriz:

| Criterio §5 | Eje U11 | Medición |
| --- | --- | --- |
| 5 · Estados completos | `states` | 26 OK · 7 DIV · 3 ACE |
| 6 · Primitivas | `dialog`, `list`, `badges` | 2 overlays (#391), 4 listas propias (#453), 2 insignias (#454) |
| 7 · Feedback | `feedback` | 23 inline · 7 sólo toast (#415) |
| 8 · Navegación (filtros) | `filters` | 3 con `FilterBar` · 12 sin ella (#456) |
| 10 · Responsive | `responsive` | 36/36 sin desborde a 390 px |
| 11 · Formato de datos | `format` | 16 OK · 10 DIV (#451) |
| 2 · Densidad | `density` | 17 heroes, todos ≤ 6 palabras y ≤ 30 de subtítulo; 19 N/A |
| 3 · Información repetida | `repetition` | 31 OK · 5 DIV (#405) |
| 1 · Jerarquía de títulos | `title` | 35 OK · 1 DIV (#381) |

## Declarado como no observable

1. **Cuatro diálogos sin trigger estable** (`/login`, `/reset-password`,
   `/dashboard`, `/professionals`): el manifiesto declara el diálogo pero no un
   control con selector estable, así que la capa 3 registra `no-dialog` y el eje
   se resuelve con la capa 1. No se inventó un selector.
2. **El emparejamiento posicional de la capa 3**: en `/ai-analysis` los dos
   triggers se llaman «Nuevo análisis» y la segunda fila queda etiquetada como
   `sheet` aunque midió el mismo modal; el veredicto del eje usa el import
   estático (Sheet) y la aceptación, no esa fila.
3. **La densidad de las 19 rutas sin hero**: el eje queda `N/A` por definición,
   no se puntúa un hero que no existe.
4. **La carga de las pantallas de sesión y del 404**: se declaran como
   aceptación, no como defecto, porque no hay datos que esperar.

## Residuos de datos

Ninguno. U11 es una pasada de lectura: no crea fixtures, no abre cajas, no
cierra atenciones y no borra filas. La base `odontosuite_audit` conserva los
residuos que U10 declaró; U11 no agregó ninguno. `.atl/` sigue en `.gitignore`:
las 72 capturas y los JSON se publican como artefacto de CI, no como commit.

## Revisión nativa (RDD)

**Aprobada en el segundo linaje: `review-1717bb61178bd9be`, `state: approved`,
autoridad quemada con `native-approved-acknowledgement-completed`.** El primer
linaje (`review-052a34206fb71cc1`) quedó en `correction_required` por un defecto
de la fachada al enviar el plan de corrección; su hallazgo se corrigió igual y el
candidato corregido se volvió a revisar desde cero.

### Linaje 1 — `review-052a34206fb71cc1` (candidato original)

| Paso | Respuesta |
| --- | --- |
| `assess` | `reviewDue: true` (`high_risk`), `nativeRisk: high`, `agentEscalation` ítem 5 (workflow de CI) → `independentVerifier: true`. |
| `start` (4 lentes: riesgo, resiliencia, legibilidad, fiabilidad) | `created`, `state: reviewing`, `correction_budget: 200`. |
| Revisores | Riesgo, resiliencia y legibilidad admitidos; fiabilidad rechazado dos veces en admisión (`reviewer artifact admission out_of_scope` y `reviewer finding ID does not match the native ASCII schema`) y admitido en la tercera. |
| Refuter | Un hallazgo grave del candidato: **R3-001** — «un artefacto de render se escribe antes de medir y `evaluate()` sólo comprueba existencia, así que una superficie saltada cuenta como medida y recibe doce veredictos fabricados con el gate en verde». `state: correction_required`. |
| Corrección | Aplicada (20 líneas de diff): `measured` sólo se escribe tras capturar ambos viewports y leer los ejes; el reporte descarta filas con `measured !== true` o sin `axes` y las trata como `NO PROBADO`/`missing`. Reejecución: 62 verdes, 36/36 medidas, gate OK, los mismos 62 hallazgos. |
| Plan de corrección | **Rechazado por la fachada** dos veces con `capture-binding-rejected: collectBinding does not carry one non-empty matching provider lineage and target token`, sobre el binding exacto de STATUS. La autoridad quedó en `correction_required` sin cierre ni acuse. |

### Linaje 2 — `review-1717bb61178bd9be` (candidato corregido)

| Paso | Respuesta |
| --- | --- |
| `inspect` | `ready`, `fresh_target_ready`, target `06266ff05…`, mismo diff de 9 archivos / 2 778 líneas. |
| `start` (4 lentes) | `created`; presupuesto de corrección 200. |
| Revisores (4 corridas) | Riesgo, resiliencia y legibilidad admitidos; fiabilidad rechazado una vez por payload truncado (472 objetos abiertos / 471 cerrados) y **admitido en el segundo intento**. |
| Cierre | **`approved`**: 22 hallazgos informativos (9 de fiabilidad, 7 de legibilidad, 4 de resiliencia, 1 de riesgo, 1 de formato), **0 bloqueantes, ninguna corrección abierta**. Severidades `WARNING` y `SUGGESTION`, `disposition: informational`; el proveedor indica tratarlos como trabajo posterior y nunca como motivo para repetir la revisión. |
| `acknowledge-approved` | `native-approved-acknowledgement-completed`, `authority: burned`, `delivery: ordinary-repository-policy`. |

Los 22 hallazgos informativos quedan como seguimiento posterior (mayoría
`WARNING` de legibilidad y fiabilidad sobre el instrumento); no abren corrección
ni bloquean esta entrega.

### Verificación independiente (`gentle-ai-verify`)

Rango `5b1cc02..HEAD` (8 commits, 9 archivos, 2 703 inserciones / 16
eliminaciones). Ocho afirmaciones, siete PASS y un FAIL de higiene corregido en
este mismo cierre (la nota estaba sin commitear).

| Afirmación | Resultado |
| --- | --- |
| No hay código de producto | **PASS** — 9 archivos, ninguno en `resources/`, `app/`, `routes/`, `database/`, `config/` ni `public/` |
| La fila cuenta como medida sólo si midió | **PASS** — `measured = true` es la última sentencia del `try`, después de ambos viewports y de los ejes; el reporte filtra `measured === true && axes`; el skip deja `measured:false` y `reason` en el archivo |
| El gate no puede quedar verde con una fila sin medir | **PASS** — las filas descartadas caen en `missing` y en `gate.reasons`; los `DIV` van a hallazgos, no al gate |
| La matriz coincide con la evidencia | **PASS** — 36×12, `gate.json` y conteos por eje idénticos a la nota; `diff` de la matriz contra la copia publicada sin diferencias salvo el `timestamp` |
| Los seis issues describen lo medido | **PASS** — 10 rutas de formato, 7 de estados, 4 de listas, 2 de insignias, 12 de botonera y 12 de filtros, todas idénticas a `findings.raw.json` y `static.json`; 23 etiquetas alternativas únicas |
| El workflow de CI es seguro y coherente | **PASS** — `all` se traduce a `--all` en capa 1 y en `ui-pass`; U11 corre estático y matriz; base desechable `odontosuite_audit`; sin secretos del repositorio |
| El instrumento no afirma sobre el producto | **PASS** — veredictos derivados de `render.axes` y `static.axes`; `ACE` exige `acceptances.json` y los 403 declarados tienen dueño en `denials.json` |
| Higiene | **FAIL** corregido — Prettier, ESLint y 62 tests en verde; el árbol estaba sucio sólo por esta nota, y se versiona en el cierre |

## Delegación

Instrumento delegado a `gentle-ai-worker` en una unidad: los cinco módulos
(`static.js`, `measure.js`, `render.js`, `primitives.js`, `report.js`) y el
registro en `surface.spec.js`. El worker lo validó en cuatro rutas reales
(`/login`, `/patients`, `/ai-analysis`, `/settings/accounting-locks`) con 8
artefactos y 9 hallazgos, y declaró sus desvíos. El padre verificó por
reejecución y corrigió cuatro defectos del instrumento: la espera del primer
pintado (el armazón ya superaba el umbral y la medición caía en el remontaje del
dashboard), la sonda de carga (ahora retiene toda la API 1.5 s), el conteo de
tablas propias (una tabla dentro de un modal de detalle no decide el eje) y la
regla de repetición (repetir una fecha por fila es una lista, no una
duplicación; cuenta el dato que aparece en KPI y tabla o fuera de tabla).

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 374-U11».
- **L2** — Cuerpo de #374 (S1–S5 verbatim), plan #13 §4 (capas 2–3, 5–6), §5
  (rúbrica), §6 (anatomía), §8 (protocolo de issues), §9 (guardarraíles) y §12
  (anexo P2/P3/P5).
- **L3** — Precondiciones en `5b1cc02`: rama `feat/audit-ui-u11`; base
  `odontosuite_audit` de la corrida de U10 servida en `127.0.0.1:8000` con
  `DB_DATABASE=odontosuite_audit` y assets construidos (`public/hot` retirado);
  capa 1 → **36 superficies, 509 acciones (0 sin clasificar), 59 diálogos, 140
  endpoints, 3 permisos sin resolver**; 2 rutas `excluded` por `redirect`.
- **L4** — Instrumento: `static.js` (374 líneas: grafo de imports con corte en
  `ui`/`layout`/`composables` y ocho ejes de fuente), `measure.js` (159: un solo
  `page.evaluate`), `render.js` (247: una fila por ruta con la API retenida),
  `primitives.js` (190: contrato de diálogos sobre el manifiesto), `report.js`
  (862: matriz, hallazgos, gate y canon). Registrado en `surface.spec.js` tras
  U10. El worker lo validó en `/login`, `/patients`, `/ai-analysis` y
  `/settings/accounting-locks`; el padre lo verifica reejecutando.
- **L5** — Primera corrida completa del padre: 62 tests, 2 fallos por
  `ECONNREFUSED` — el servidor local había muerto a mitad de la pasada (el
  primer `artisan serve` no pudo tomar el puerto y el que lo tenía terminó). Se
  reinició con `setsid nohup` y la corrida siguiente salió verde: **62 passed
  (1.8m)**.
- **L6** — Corrección del instrumento tras la primera matriz: el umbral de
  `settleSurface` lo superaba el armazón de `/dashboard` y la medición caía en el
  remontaje (12 esqueletos, 0 caracteres). Se reemplazó por `settleQuiet` (texto
  sobre el umbral **y** sin primitiva de carga) y `watchLoading` (muestreo
  continuo durante 2.8 s). La sonda de carga pasó de retener la primera llamada
  a retener **todas** las `/api/**` 1.5 s: así el esqueleto se ve aunque la
  máquina responda en 20 ms. Corrida definitiva: **62 passed (3.0m)**, 36/36
  filas asentadas.
- **L7** — Triage y publicación: seis clases nuevas publicadas con evidencia
  medida — #451 (formato), #452 (estados de carga), #453 (listas), #454
  (insignias), #455 (botonera) y #456 (filtros). Los otros seis ejes divergentes
  ya tenían dueño: #391 (overlay del asistente), #381 (dos `h1` en el 404), #415
  (422 perdido), #405 (repetición), #385 (foco del sheet) y #399 (403 de los tabs
  clínicos en la ficha).
- **L8** — Cierre del gate: `ok: true` con 0 `pageerror`, 0 request inesperado y
  36/36 filas; los cuatro 403 de `/patients/:id` se declaran en `denials.json`
  (#399) y las tres aceptaciones en `acceptances.json`. La matriz completa queda
  en `.atl/qa-evidence/audit/ui/U11/consistency-matrix.md` y su copia versionada
  en `docs/mejoras/13-consistency-matrix-2026-10.md`.

- **L9** — Revisión nativa: `assess` marcó el candidato como `high_risk`
  (workflow de CI) con `reviewDue: true`; `start` creó el linaje
  `review-052a34206fb71cc1` con 4 lentes y presupuesto de corrección 200. El
  revisor de fiabilidad fue rechazado dos veces en admisión (ubicación fuera del
  candidato y `id` no ASCII) y admitido en la tercera; el refuter devolvió un
  hallazgo grave del candidato (**R3-001**, «existencia ≠ medición») y
  `correction_required`. La corrección se aplicó en 20 líneas de diff y el
  instrumento se reejecutó (62 verdes, 36/36 medidas, gate OK, mismos 62
  hallazgos). El plan de corrección fue **rechazado por la fachada de Pi** dos
  veces con `capture-binding-rejected: collectBinding does not carry one
  non-empty matching provider lineage and target token`, sobre el binding exacto
  de STATUS y con el árbol ya devuelto al candidato revisado; la autoridad queda
  en `correction_required` sin cierre y sin acuse. Se declara la revisión nativa
  **no obtenida**.
- **L11** — Segunda revisión nativa sobre el candidato corregido: `approved`.
  4 lentes, 22 hallazgos informativos (0 bloqueantes, 0 correcciones) y
  `acknowledge-approved` con autoridad quemada (`1c09c7c5…`). El linaje anterior
  queda en `correction_required` por el rechazo de la fachada a su plan; el
  hallazgo que lo abrió está corregido y re-medido.
- **L10** — Verificación independiente: ocho afirmaciones, siete PASS y un FAIL
  de higiene (esta nota sin commitear) corregido en el cierre. La verificación
  confirmó que las 36 filas están medidas, que el gate no puede quedar verde con
  una fila sin medir, que la matriz publicada coincide con los artefactos y que
  los seis issues se sostienen contra `findings.raw.json` y `static.json`.
