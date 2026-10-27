# Task: U10 — BI, continuidad y laboratorio (auditoría UI, plan #13)

Status: DONE (sesión de auditoría). Rama `feat/audit-ui-u10` desde
`c6180b3` (`main`). No se toca código de producto: la sesión mide y documenta en
`tests/e2e/audit/u10/` y deja la evidencia en `.atl/qa-evidence/audit/ui/U10/`.

## Estrategia de entrega

El presupuesto de ~400 líneas aplica a correcciones, no a un instrumento nuevo
completo: un corte sólo es ejecutable cuando su capa y sus fixtures están
completos. Mismo criterio declarado en U0–U9. La entrega va en cortes revisables:
**1** instrumento de capas 0–3 y 5 (helpers, fixtures, render, matriz) y el
workflow de CI; **2** las tres caminatas de capa 4 y el generador de evidencia;
**3** informe, rúbrica, triage e issues; **4** esta nota y el PR. El merge y la
publicación quedan a decisión del dueño.

Frontera del primer corte: commit `fc4285e` (`helpers.js`, `fixtures.js`,
`render.js`, `api-matrix.js`, el registro en `surface.spec.js` y
`.github/workflows/audit-ui.yml`).

## Specs

- **S1** — Objetivo (issue #373, verbatim): «Verificar los tres escritorios de
  lectura y coordinación: que un reporte sin datos explique qué hacer, que el
  export coincida con la pantalla, que la cola de continuidad se pueda resolver y
  que la fase del laboratorio solo avance. Medido en `8b72d37`:
  `BusinessIntelligencePage` es la página más larga (1 115 líneas) y la única con
  consolidado por sede; continuidad y laboratorio usan `UiDataTable` y modales
  propios.»
- **S2** — Método (issue #373, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=business-intelligence,continuity,lab-cases --out=.atl/qa-evidence/audit/ui/U10/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U10
  ```
- **S3** — Superficies, roles y acciones (issue #373, verbatim): «Superficies:
  `/business-intelligence` (6 reportes, presets 7/30/90, filtros de sede,
  profesional y ambiente, consolidado por sede, export, gráficos), `/continuity`
  (cola mixta, métricas, agendar con fecha preferida, descartar con motivo) y
  `/lab-cases` (filtros por fase, técnico y vencido; alta, edición, detalle,
  avance de fase). Roles: administrador y finanzas (BI); recepción y clínicos
  (continuidad y laboratorio). Acciones: cambiar de reporte y de rango; exportar;
  redimensionar con gráficos; agendar con conflicto; descartar sin motivo;
  avanzar y retroceder fase; intentar editar un caso de otro técnico.»
- **S4** — Entregable (issue #373, verbatim): «`render.json`, `walkthrough.md`
  con la matriz reporte × estado (datos, vacío, error), `api-matrix.json`,
  `ux-rubric.md` y `odd/tasks/audit-ui-u10.md`.»
- **S5** — Criterio de salida (issue #373, verbatim): «Cada reporte tiene sus
  tres estados y su export coincide con la pantalla; el conflicto de agenda deja
  la solicitud pendiente y lo dice; la fase de laboratorio no retrocede y el
  mensaje cae en su campo; cero `pageerror`; rúbrica puntuada.»
- **S6** — Plan #13 §4 capa 2 (verbatim): «Por cada ruta y por cada rol que la
  ve (máximo 3 roles: el dueño del escritorio, administración y un rol excluido
  para la puerta negativa)»; «Cero `console.error`, cero `pageerror`, cero
  request ≥ 400 salvo allowlist declarada (por ejemplo, la propia prueba de
  403)»; «Longitud de contenido renderizado por encima del umbral del módulo»;
  «Captura `desktop 1440×900` y `mobile 390×844`, **siempre y explícita**»;
  «Sin scroll horizontal de documento en 390 px.»
- **S7** — Plan #13 §4 capa 3 (verbatim): «Centrado horizontal
  (`|box.x + box.width/2 − viewportWidth/2| < 8`)»; «Un solo `h1`»; «Escape
  cierra»; «Foco atrapado»; «Scroll bloqueado»; «Etiqueta accesible». «Un
  diálogo que no cumple **una** fila es hallazgo, no ruido: es la clase
  "primitiva mal usada".»
- **S8** — Plan #13 §4 capa 4 (verbatim): los veredictos `OK`, `DEFECTO`,
  `NO-OP`, `CONFUSO`, `NO PROBADO`; «Toda acción con `DEFECTO`, `NO-OP` o
  `CONFUSO` produce fila en `findings.md` con captura y, si hay request, su
  status.»
- **S9** — Plan #13 §4 capa 5 y 6, §5 (verbatim): «Para los endpoints que la
  sesión consume: matriz `rol × endpoint` con el status esperado derivado de
  `App\Authorization\Permissions` y el observado con un token real»; «Doce
  criterios, 0/1/2 cada uno, por superficie primaria y una vez por sesión para
  su conjunto».
- **S10** — Plan #13 §6.1, §6.3 y §9 (verbatim): «la sesión **solo** escribe en
  `odontosuite_audit`; los borrados se hacen sobre filas que la propia sesión
  creó; nunca `migrate:fresh` sobre la base de desarrollo; `.env` intacto»;
  «`.atl/` está en `.gitignore` (verificado), así que las capturas y JSON **no se
  commitean**»; «La sesión que audita no escribe código de producto».
- **S11** — Plan #13 §8.1 (verbatim): «Un issue por clase raíz, nunca por
  síntoma ni por módulo»; «Tope por sesión: 3–7 issues»; «La sesión no cierra
  sus propios issues».

## Tasks

- [ ] **T1** · S2, S10 · `inline` · Precondiciones: rama `feat/audit-ui-u10`;
  base `odontosuite_audit` recreada y sembrada; servidor local con
  `DB_DATABASE=odontosuite_audit`; capa 1 → `manifest.json` de las tres rutas.
- [ ] **T2** · S1, S3–S9 · `delegated` · Instrumento `@U10` en
  `tests/e2e/audit/u10/`: fixtures propios, render por rol, caminata de BI
  (reporte × estado + export), caminata de continuidad, caminata de laboratorio,
  matriz `rol × endpoint` e informe.
- [ ] **T3** · S2, S5 · `inline` · Corrida del instrumento y `gate.json` con cero
  `pageerror`.
- [ ] **T4** · S1, S5, S8–S11 · `inline` · Triage por clase raíz, issues
  publicados, `walkthrough.md`, `ux-rubric.md` y `findings.raw.json`.
- [ ] **T5** · S5, S10 · `inline` · Cierre: Prettier y ESLint en verde,
  `playwright test --list`, commits por corte, esta nota y PR.


## Línea base medida

- Revisión medida: `c6180b3` sobre `main`; árbol limpio salvo el instrumento y
  esta nota. Base `odontosuite_audit` recreada con `migrate:fresh --seed` (15
  usuarios, 3 sedes, 106 citas, 372 transacciones, 100 pacientes, 20 diagnósticos
  CIE-10) y fixtures propios de la sesión (dinero con dos cobros y una devolución,
  continuidad por cierre real de una atención, cuatro casos de laboratorio).
- **Capa 1** · 3 superficies, **25 acciones (0 sin clasificar)**, 3 diálogos, 16
  endpoints, **0 permisos sin resolver**; 2 rutas `excluded` por `redirect`.
- **Capa 2** · **9 filas de render** (3 superficies × sus roles), **0
  `pageerror`**, **0 scroll horizontal a 390 px**, una sola `h1` en 9/9 y la
  puerta negativa de cada escritorio respondiendo 403.
- **Capa 3** · 3 diálogos, **21 comprobaciones**, **1 falla**: el modal de agenda
  de continuidad no tiene nombre accesible.
- **Capa 4** · **62 acciones clasificadas: 52 `OK`, 3 `DEFECTO`, 2 `NO-OP`, 5
  `CONFUSO`, 0 `NO PROBADO`**; 0 `pageerror` y 0 request inesperado de página (los
  siete 500 del estado de error se declaran y no cuentan).
- **Capa 5** · **168 filas sobre 24 endpoints × 7 roles, 168/168 conformes**, 0
  desvíos; los 403 esperados se derivan del catálogo de permisos y se observaron
  con token real.
- **Rúbrica** · **BI 12/24 · continuidad 11/24 · laboratorio 13/24 · sesión 9/24**
  (`ux-rubric.md`, doce criterios × cuatro ámbitos, cada puntuación con su
  justificación y su `NO MEDIDO`).
- **Gate** · `gate.json` → `ok: false` con un solo motivo: el contrato del
  diálogo sin nombre accesible. Es el criterio de salida incumplido, no ruido.

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue |
| -- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| F01 | BI | «Dashboard General» con un período sin actividad | tarjeta de vacío con CTA | KPI en cero + rendimiento por profesional; sin vacío | 4 | MEDIDO | `crit:media` | `isEmpty` sólo mira el tamaño de la tabla | #447 |
| F02 | BI | «Reporte de Pacientes» con el mismo período | tarjeta de vacío | 100 filas y contadores en cero | 4 | MEDIDO | `crit:media` | población no acotada por período | #447 |
| F03 | BI | «Reporte de Profesionales» | tarjeta de vacío | 9 filas | 4 | MEDIDO | `crit:media` | la misma | #447 |
| F04 | BI | «Utilización de Ambientes» | tarjeta de vacío | 8 filas | 4 | MEDIDO | `crit:media` | la misma | #447 |
| F05 | BI | «Limpiar filtros» | los filtros publican la población aplicada | profesional y ambiente siguen en «Seleccionar...» | 4 | MEDIDO | `crit:media` | `UiSelect` con opción de valor vacío | #402 (comentario) |
| F06 | Continuidad | abrir el modal de agenda | diálogo con nombre accesible | `aria-labelledby` ausente y `aria-label` vacío | 3 | MEDIDO | `crit:media` | `#header` propio sin `title`/`labelledby` | #429 (comentario) |
| F07 | Laboratorio | buscar alta y edición (filas crudas F07 y F09) | la superficie las ofrece | sólo «Actualizar» y «Ver» | 4 | MEDIDO | `crit:media` | la superficie se entregó sin ellas y el changelog dice lo contrario | #448 |
| F08 | Laboratorio | editar el caso de otro técnico (fila cruda F08) | 403 | `PUT` 200 con el token del otro técnico | 4 | MEDIDO | `crit:media` | `update` sin regla de pertenencia | #449 |
| F10 | BI | renderizar como `recepcionista` | sin escrituras | «Excel», «CSV» y «PDF» habilitados; puerta 403 | 2 | MEDIDO | `crit:media` | la página no consulta `usePermissions()` | #439 (comentario) |
| F11 | Continuidad | agendar un recall sobre la ventana de una cita que la sesión acaba de borrar | 422 con conflicto | **500** «Error al agendar la cita de control» (cita 201, borrada 200) | 4 | MEDIDO | `crit:media` | el índice único cuenta las filas con soft delete y `findConflicts` no las ve | #392 (comentario) |

### Backlog del programa (§8.4)

- **El vacío del laboratorio no existe como tal**: la lista vacía sí tiene
  `UiEmptyState`, pero con 15 filas de residuo de corridas previas el estado no
  se alcanza; en una base limpia (CI) se mide con 4 filas y el vacío sigue sin
  ejercitarse. Se declara como no observable en esta sesión.
- **La densidad de texto y la información repetida** no tuvieron medición
  dedicada: la rúbrica las deja en `NO MEDIDO` con el motivo escrito.

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| Cada reporte tiene sus tres estados | **No cumple**: 4 de 7 no tienen vacío (#447); datos y error sí están en los siete |
| El export coincide con la pantalla | **Cumple**: seis reportes exportables con cabeceras normalizadas, filas contra el total declarado y una muestra de valor; el consolidado declara que no se exporta |
| El conflicto de agenda deja la solicitud pendiente y lo dice | **Cumple** con un matiz: `POST` 422 con «Conflicto de horario detectado…» en `scheduled_at`, la fila sigue `pending` y la cola la conserva; pero la ventana que ocupa una cita **borrada** no se puede reusar y revienta en 500 (F11) |
| La fase de laboratorio no retrocede y el mensaje cae en su campo | **Cumple**: la puerta sólo ofrece fases posteriores, el retroceso forzado responde 422 con `errors.phase[0]` y la fase no cambia |
| Cero `pageerror` | **Cumple**: 0 en las 9 filas de render, los 3 diálogos y las 61 acciones |
| Rúbrica puntuada | **Cumple**: `ux-rubric.md` con doce criterios × cuatro ámbitos y su justificación |

## Declarado como no observable

1. El vacío de `/lab-cases`: la base de auditoría acumula los casos de cada
   corrida (no existe `DELETE`) y el estado se alcanza sólo sobre una base limpia;
   la corrida de CI lo intenta con la población de la sesión y lo deja declarado.
2. La edición de un caso ajeno **desde la pantalla**: no hay control de edición
   (F07), así que la regla de pertenencia sólo se puede medir contra la API.
3. El nombre accesible de los otros dos diálogos: el de descarte y el de detalle
   de laboratorio sí lo publican; la falla es del modal de agenda.

## Residuos de datos

La sesión escribió sólo en `odontosuite_audit`. Los fixtures se retiran en su
`afterAll`: las citas de dinero se borran, la solicitud de continuidad se
descarta, los recalls se cancelan y la caja que la sesión abrió se cierra con
diferencia cero. No hay `DELETE` de casos de laboratorio, de solicitudes de
continuidad ni de una atención cerrada, así que esas filas quedan como residuo
declarado en `fixtures.json > residues`. La única fila que envejece es el
consumo de ventanas de agenda: cada corrida ocupa una y la borra (soft delete),
que es justo el hecho F10.

## Revisión nativa (RDD)

**Sin veredicto: el proveedor no produjo autoridad y no hay nada que consumir.**

| Paso | Respuesta |
| --- | --- |
| `inspect` | `ready`, `action: start`, candidato `base-diff` `c6180b3..HEAD` con 11 archivos. |
| `start` (3 lentes: riesgo, legibilidad, fiabilidad) | `blocked` en `preflight`, `lens_context_budget_exceeded`: «The candidate's complete reviewer evidence exceeds the native context budget (…) retrying this exact candidate cannot succeed (…) split it into a chained sequence of smaller reviewable commits»; `mutation_outcome: none`, sin linaje ni autoridad. |
| `assess` | `nativeReviewOutcome: unavailable` (explícito), `nativeRisk: unassessable`, plan sin verificador independiente. |
| `assess` con `escalate` item 5 | `risk: high`, `independentVerifier: true`: el candidato modifica un workflow de CI (entorno de entrega). |

Consecuencia: no se consumió autoridad, no hay corrección pendiente y el
proveedor declara que el candidato completo no cabe en su presupuesto. La
verificación independiente la ejecuta `gentle-ai-verify` sobre el rango
`c6180b3..HEAD` y su veredicto se registra en el cierre. Queda declarado como
revisión nativa no obtenida, igual que en U9.

### Verificación independiente (`gentle-ai-verify`)

| Afirmación | Resultado |
| --- | --- |
| No se toca código de producto | **PASS** — 11 archivos, ninguno en `resources/`, `app/`, `routes/`, `database/`, `config/` |
| El workflow de CI es seguro y consistente | **PASS** — base desechable `odontosuite_audit`, sin mutación del repositorio ni credenciales en claro |
| El instrumento mide y no afirma sobre el producto | **PASS** — ningún defecto medido se convirtió en `OK` |
| La evidencia es consistente | **PASS** — `gate.json` = los `counts` de cada caminata, 10 filas crudas, 168/168 de matriz, matriz BI 7 × 4 |
| Guardarraíles de datos | **PASS en datos** (0 filas de la sesión en `odontosuite`, sin `migrate:fresh`); **FAIL en estado del árbol**: la nota estaba sin commitear y así se declaró |
| La nota es fiel | **FAIL parcial** — la numeración F09/F10 no seguía al artefacto y la fila del 500 no tenía evidencia en el artefacto |

Corregido en el cierre: la nota adopta los ids del artefacto (F10 = affordance de
exportación de BI, F11 = ventana borrada) y la ventana borrada pasa a ser un paso
medido (`09-deleted-window`): crea la cita, la borra y pide la misma ventana →
**500** en vez del 422 que el producto promete.

## Delegación

Instrumento delegado a `gentle-ai-worker`: fundación (helpers, fixtures, render,
matriz) y caminata de BI, ambas verificadas por reejecución del padre; generador
de evidencia; y caminatas de continuidad y laboratorio, que el worker dejó
escritas pero **agotó 30 minutos sin verificarlas**. El padre las registró, las
ejecutó con cota de tiempo y corrigió cinco defectos del instrumento: la espera
de las opciones del modal, el `Escape` que cerraba el diálogo al leer el tipo, un
modal abierto tapando la fila siguiente, el reloj del conflicto convertido con la
zona del runner y el teardown que trataba un 422 como residuo.

## Log

- **L12** — Primera corrida de CI (`40539553371`): verde, y destapó un defecto del
  instrumento, no del producto: el hueco de conflicto se convertía con la zona del
  runner (UTC) y en CI medía un hueco libre (201 en vez de 422), lo que dejaba dos
  pasos sin medir. Corregido con el reloj literal del API (Laravel serializa con el
  offset de la aplicación, así que la hora literal es el reloj de pared).
- **L13** — Revisión nativa: `start` rechazado en `preflight` por
  `lens_context_budget_exceeded`; `assess` con la escalación del ítem 5 (workflow de
  CI = entorno de entrega) pide verificador independiente. Sin autoridad creada.
- **L14** — Verificación independiente: seis afirmaciones, cuatro PASS y dos FAIL
  corregidos (nota sin commitear y numeración/evidencia de F10). La ventana que
  ocupa una cita borrada se midió como paso propio: **500** en la agenda de
  continuidad tras crear y borrar la cita (`09-deleted-window`).
- **L15** — Corrida definitiva de CI `40544841390` sobre `6e96d8f`: **verde**
  (4m21s). 62 acciones clasificadas (52 `OK` / 3 `DEFECTO` / 2 `NO-OP` / 5
  `CONFUSO` / 0 `NO PROBADO`), **11 filas crudas** (4 `DEFECTO`, 2 `NO-OP`, 5
  `CONFUSO`), 168/168 filas de matriz, 0 `pageerror`, 0 request inesperado. La
  puerta de sesión queda en `ok: false` con **un solo motivo**: el contrato del
  diálogo de agenda sin nombre accesible; la ventana de la cita borrada y el
  reset de filtros son acciones `DEFECTO` del recorrido, no motivos de puerta. La
  rúbrica cierra en BI 12/24 · continuidad 11/24 · laboratorio 13/24 · sesión
  9/24.

- **L1** — Pedido del usuario, verbatim: «haz la issue 373-U10».
- **L2** — Cuerpo de #373 (S1–S5 verbatim), plan #13 §4 (capas 2–6), §5
  (rúbrica), §6 (anatomía y evidencia), §8 (protocolo de issues) y §9
  (guardarraíles).
- **L3** — Precondiciones en `c6180b3`: rama `feat/audit-ui-u10`; base
  `odontosuite_audit` recreada con `migrate:fresh --seed` (15 usuarios, 3 sedes,
  106 citas, 372 transacciones, 100 pacientes, 20 diagnósticos CIE-10, 0 casos de
  laboratorio, 0 recalls, 0 solicitudes de continuidad); app servida en
  `127.0.0.1:8000` con `DB_DATABASE=odontosuite_audit` y assets construidos
  (`pnpm build`; `public/hot` retirado para no depender del dev server). Capa 1 →
  **3 superficies, 25 acciones (0 sin clasificar), 3 diálogos, 16 endpoints, 0
  permisos sin resolver**; 2 rutas `excluded` por `redirect`.
- **L4** — Reconocimiento de contrato (token real por rol, base de auditoría):
  administrador 200 en toda lectura y 201/422 en las puertas de escritura;
  recepcionista 403 en los siete reportes y en `POST /api/patient-recalls`, 200 en
  continuidad y laboratorio; finanzas 200 en los reportes y 403 en los otros dos
  escritorios; los roles clínicos leen continuidad y laboratorio y no crean
  recalls. `PUT`/`POST` de laboratorio con id inexistente responden **404 antes**
  del middleware de permiso; con id real, 403 al rol sin `manage.lab_case`. Dos
  hechos de datos que la matriz debe declarar: las 372 transacciones sembradas
  son de tipos legacy `income`/`expense` (fuera de `MoneyVocabulary`), así que sin
  fixtures propios el reporte de ingresos sale vacío en todo período; y la
  semilla no crea casos de laboratorio, recalls ni solicitudes de continuidad.
- **L5** — Instrumento: `helpers.js` (1 369 líneas, set de U9 + siete auxiliares
  de BI), `fixtures.js` (1 291: dinero, continuidad por cierre real de atención y
  cuatro casos de laboratorio con residuo declarado), `render.js` (272),
  `api-matrix.js` (612, 24 endpoints × 7 roles) y `walk-bi.js` (2 024, siete
  filas). Corrida local de capas 0/2: **9 filas de render, 0 `pageerror`, 0
  scroll horizontal a 390 px**, y la puerta negativa de cada superficie (403).
  Corrección del instrumento: la página de BI pinta su armazón antes del reporte,
  así que la fila espera el gancho de estado (`[data-bi-kpis]`,
  `[data-report-table-section]`, `[data-report-empty]`, `[data-state="error"]`)
  antes de medir; sin esa espera la fila registraba el estado de carga como si
  fuera el reporte.
