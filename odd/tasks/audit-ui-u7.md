# Task: U7 — Clínica: historia, especialidades y Análisis IA (auditoría UI, plan #13)

Status: DONE (sesión de auditoría). Rama `feat/audit-ui-u7` desde `10dc93b`
(`main`); instrumento en `tests/e2e/audit/u7/` entregado en cuatro cortes
(`b7a84a4`, `c564bfa`, `38b54fc`, `da147f8`), esta nota en su commit de
documentación y hallazgos publicados en #423–#429. No se toca código de
producto. La **revisión nativa quedó bloqueada por un defecto del relé del host**
(§Revisión nativa): no hay veredicto y la entrega queda a decisión del dueño.

## Specs

- **S1** — Objetivo (issue #370, verbatim): «Verificar que el escritorio clínico
  documenta sin ofrecer acciones que la regla prohíbe, que cada pestaña explica
  qué hacer cuando está vacía y que el técnico dental no alcanza la narrativa
  clínica. Medido en `8b72d37`: `MedicalRecordsPage` (1 272 líneas) acumula 15
  marcas de estado vacío y 18 de reintento; el módulo usa 18 `UiModal` y 3 tabs;
  el alta de IA es el único flujo de alta en `UiSheet`.»
- **S2** — Método, capa 1 (issue #370, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=medical-records,specialty-records,ai-analysis --out=.atl/qa-evidence/audit/ui/U7/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U7
  ```
- **S3** — Superficies, roles y acciones (issue #370, verbatim): «Superficies:
  `/medical-records` y `/:id` (historia general, evoluciones, adjuntos,
  estadísticas; alta, evolución SOAP con mediciones, adjunto con tipo y tamaño,
  addendum, estadísticas), `/specialty-records` (selector de paciente y las 5
  especialidades con su CRUD y detalle) y `/ai-analysis` (lista con KPIs, alta
  con aviso al paciente, revisión, anulación con motivo, cita). Roles:
  administrador, odontólogo, implantólogo y asistente; técnico como puerta
  negativa (403 en API y sin entrada de navegación).»
- **S4** — Entregable (issue #370, verbatim): «`render.json` (4 roles + técnico
  negativo), `primitives.json` (sheet de IA), `walkthrough.md`,
  `api-matrix.json`, `ux-rubric.md` y `odd/tasks/audit-ui-u7.md`.»
- **S5** — Criterio de salida (issue #370, verbatim): «Ninguna ruta de borrado
  clínico es visible; cada tab vacía ofrece una acción; el técnico recibe 403 en
  toda la API del módulo y no ve sus entradas; anular un análisis citado se
  rechaza con mensaje; cero `pageerror`; rúbrica puntuada.»
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
- **S12** — Capa 5 aplicada (derivado del §4 y de S4): la unidad nombra
  `api-matrix.json` en su entregable, así que la matriz `rol × endpoint` cubre
  los 38 endpoints que la sesión consume (29 del manifiesto más las lecturas
  prestadas y las rutas de escritura que **no** deben existir) con la
  expectativa derivada de `App\Authorization\Permissions`.

## Estrategia de entrega

Un instrumento de medición en `tests/e2e/audit/u7/` (10 módulos, 7 957 líneas)
registrado en `surface.spec.js` sólo por su bloque de imports, igual que
U2–U6. Se entregó en cuatro cortes revisables: **1/4** `b7a84a4` (helpers,
fixtures y el puente tinker guardado), **2/4** `c564bfa` (render y contrato de
diálogos), **3/4** `38b54fc` (caminatas de historia y especialidades) y **4/4**
`da147f8` (caminata de IA, matriz de roles e informe). El presupuesto de ~400
líneas aplica a correcciones, no a un instrumento nuevo completo; mismo criterio
declarado en U0–U6.

Datos: la base `odontosuite_audit` se recreó por sesión y no trae historias,
evoluciones, adjuntos ni análisis de IA. Los fixtures los crean por las puertas
de producto y el único estado que no tiene puerta —un análisis `completed`, que
exige la clave de OpenAI— se coloca con el tinker guardado
(`fixtures-guarded.js`: asserta `odontosuite_audit` y el marcador `AUDIT-U7`) y
se declara como fixture, nunca como comportamiento observado.

## Tasks

- [x] **T1** · S2, S10 · `inline` · Precondiciones: base `odontosuite_audit`
  recreada, migrada y sembrada; app `:8000`, Vite `:5173`; capa 1 →
  `manifest.json`: **4 superficies, 102 acciones (0 sin clasificar), 18
  diálogos, 29 endpoints, 0 permisos sin resolver**; 2 rutas `excluded` por
  `redirect`.
- [x] **T2** · S1–S9, S12 · `delegated` · Instrumento `@U7` en
  `tests/e2e/audit/u7/` (10 módulos, 7 957 líneas) registrado en
  `surface.spec.js` sólo con imports. Commits `b7a84a4`, `c564bfa`, `38b54fc`,
  `da147f8`. Delegado a un escritor acotado y verificado por lectura propia
  (L7–L10).
- [x] **T3** · S2, S3, S5 · `inline` · Corrida definitiva sobre base limpia:
  **32/32, 5.0 min, exit 0**; `ui-pass --no-run` → gate OK, 4/4 superficies
  renderizan, 0 `pageerror`, 12 solicitudes en allowlist, 12 diálogos y 2 filas
  fallidas del contrato (las dos se publican).
- [x] **T4** · S4, S8–S11 · `inline` · `walkthrough.md`, `ux-rubric.md`
  (17/24, 21/24 y 13/24), `render.json`/`render/`, `primitives.json`/
  `primitives/`, `api-matrix.json`, galería `index.html`/`gallery.html` y esta
  nota; hallazgos agrupados por clase raíz y publicados **#423–#429**, más el
  registro de sesión en #370.
- [x] **T5** · S5, S10, S11 · `inline` · Cierre: `pnpm exec prettier --check`
  en verde, `eslint` 0 errores, `playwright test --list` → 286 tests en 31
  archivos, commits y nota. **Revisión nativa bloqueada por el relé** (§Revisión
  nativa); PR #430 abierto, merge a decisión del dueño.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 370-U7».
- **L2** — Cuerpo de #370 (S1–S5 verbatim) y plan #13 §4 (capas 1–6), §5
  (rúbrica), §6 (anatomía y evidencia), §8 (protocolo de issues) y §9
  (guardarraíles).
- **L3** — Estado al abrir la sesión (`10dc93b`, `main` limpio, U6 fusionada):
  `MedicalRecordsPage.vue` (1 272 líneas), `SpecialtyRecordsPage.vue` (516) y
  `AiAnalysisPage.vue` (765); el manifiesto publica 4 superficies, 102 acciones,
  18 diálogos y 29 endpoints.
- **L4** — Entorno: app, Vite y base `odontosuite_audit` sembrada. Medido:
  15 usuarios, 100 pacientes, 9 especialidades, 11 registros de especialidad
  (pacientes 1–3), 106 citas, **0 historias, 0 evoluciones, 0 adjuntos y 0
  análisis de IA**. Sin `OPENAI_API_KEY` en `.env`.
- **L5** — Causas medidas antes de instrumentar: (a) el alta real de IA responde
  `201` y deja `status = failed` porque `processAnalysis` captura el error del
  proveedor; (b) la cita exige `status = completed` + `reviewed`, así que ese
  estado sólo existe por el tinker guardado; (c) la matriz de escritura de
  especialidades es `implantologia` → implantólogo/administrador y las otras
  cuatro → odontólogo/administrador (`StoreSpecialtyRecordRequest::authorize`),
  y la interfaz filtra las tabs por rol (`definitionsForRole`).
- **L6** — Puertas medidas: `tecnico_dental` responde `403` en las tres raíces
  del módulo y su sidebar no publica entradas clínicas; `medical-records` sólo
  publica `GET/POST` y `evolutions` sólo `GET/POST` (`PUT/PATCH/DELETE → 405`),
  y el único borrado clínico vivo es el del adjunto, que deja marcador de
  archivo (#244).
- **L7** — El instrumento se delegó a un escritor acotado (*context backstop* de
  la sesión): 10 módulos, 7 957 líneas, registro sólo en el bloque de imports,
  evidencia en `.atl/qa-evidence/audit/ui/U7` (gitignored).
- **L8** — Verificación propia: se releyeron el manifiesto, las 19 filas de
  render, los 13 diálogos, las cinco caminatas y la matriz de 161 filas; se
  reejecutó la sesión completa sobre una base recreada. Dos defectos del propio
  informe, corregidos antes de commitear: citaba una captura de sonda que la
  suite no produce y un `step` con prefijo numérico inexistente
  (`tests/e2e/audit/u7/report.js`). La rúbrica se corrigió en tres filas
  (jerarquía de títulos de la historia a 1 por el título duplicado; accesibilidad
  de la historia a 2 porque el detalle de IA pertenece a su superficie; población
  de KPIs de IA a 1), con la cita de lo medido.
- **L9** — Higiene de datos: la evidencia inicial del escritor arrastraba 107
  análisis de sus iteraciones. La línea base se midió sobre base recreada y una
  sola corrida: 3 análisis en la lista, 4 tras el alta del sheet; 1 historia en
  el índice, 2 tras el alta de la caminata.
- **L10** — Comprobaciones independientes de los hallazgos de mayor severidad:
  (a) reproducido a mano el apilado de la confirmación: existe
  `role="alertdialog"`, `elementFromPoint` del botón «Anular» devuelve el footer
  del detalle («Guardar revisión») y no se emite `POST …/void`; (b) `curl` sobre
  `PUT/DELETE /api/specialty-records/999999999` → `500`; (c) el detalle de IA no
  tiene `aria-labelledby` ni `aria-label`; (d) `/medical-records` pinta el `h1`
  del topbar y el `.page-title` del hero con el mismo título (clase de #405).
- **L11** — Triage por clase raíz: F03+F04 → #423; F01 → #424; F02 → #425;
  F05+F06 → #426; F07 → #427; F13 → #428; F11 → #429. Dedupe: F12 es #385
  (foco del sheet); la mitad del selector de F08 es #402 y la población global
  de KPIs se acepta (mismo criterio que U6); F18 es la clase de #405. Quedan
  como aceptación escrita F09, F10, F14, F15, F16 y F17 (§Hallazgos).
- **L12** — Corrida definitiva: `32 passed (5.0m)`, `EXIT=0`; gate de `ui-pass`
  OK. Revisión nativa: el corte 1 admitió `risk` y `readability`; `reliability`
  fue rechazada dos veces en admisión y expiró a los 982 200 ms de un límite de
  982 196 ms, y el proveedor declaró el slot inalcanzable. El candidato c2
  (reducido a 1 049 líneas) volvió a ser rechazado en admisión, esta vez en
  `risk` con un payload de 2 469 bytes y llaves balanceadas. La revisión se
  detiene como defecto del relé del host.

## Línea base medida

- Revisión del árbol medido: `10dc93b` + instrumento (4 cortes); árbol limpio
  salvo el instrumento y esta nota. Corrida: `2026-10-26`, base
  `odontosuite_audit` recreada + `migrate --seed`.
- **Capa 1** · 4 superficies, **102 acciones (0 sin clasificar)**, **18
  diálogos**, **29 endpoints**, **0 permisos sin resolver**.
- **Capas 2–5** · `pnpm exec playwright test … --grep @U7` → **32 pasan**, exit
  0, **5.0 min**.
- **Envoltorio** · `ui-pass --no-run` → **gate OK**; 4/4 superficies renderizan;
  **0 `pageerror`**; 12 solicitudes esperadas en allowlist; 0 acciones sin
  clasificar; 12 diálogos y 2 filas fallidas del contrato.
- **Render por rol** · 18 filas (4 superficies × sus roles) + 1 sonda de
  navegación: 5 filas para `/medical-records` y 5 para `/:id` (administrador,
  odontólogo, implantólogo, asistente, técnico negativo), 4 para
  `/specialty-records` y 4 para `/ai-analysis`; **0 `pageerror`**, **0 scroll
  horizontal a 390 px**, 1 `h1` por superficie; el técnico no recibe entradas
  clínicas (`clinicalEntries: []`) y su lectura responde 403.
- **Diálogos** · 13 medidos: 12 filas del contrato (una de ellas la pila anidada de la anulación) + la sonda de oclusión;
  **2 filas fallidas** (`ai-sheet` foco, `ai-detail` nombre accesible) y la
  confirmación anidada pasa sus filas pero queda **ocluida** por el detalle.
- **Caminatas** · historia **17 pasos: 14 OK / 3 DEFECTO**; asistente 2/2 OK;
  especialidades **16/16 OK**; matrices rol × especialidad 3/3 en cada rol; IA
  **17 pasos: 10 OK / 6 DEFECTO / 1 CONFUSO**.
- **Matriz de contrato** · **161 filas sobre 38 endpoints**, 0 discrepancias,
  0 filas no conformes y **4 desviaciones declaradas** (id inexistente en
  `PUT`/`DELETE` de especialidades → 500). Denegaciones: administrador 0,
  odontólogo 1, asistente 22, técnico 32, implantólogo 0.
- **Rúbrica** · `/medical-records` **17/24** (criterios 5 y 4 en 1/0),
  `/specialty-records` **21/24**, `/ai-analysis` **13/24** (acciones, feedback,
  accesibilidad y honestidad en 0).

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- |
| F01 | historia › tabs vacías | abrir «Evoluciones» y «Estadísticas» sin historia | cada tab vacía ofrece la acción que la resuelve | 0 acciones en las dos (sus hermanas sí) | 4 | MEDIDO | `crit:media` | `MedicalRecordsPage.vue:505`; `MedicalRecordStats.vue:7` | #424 |
| F02 | historia › alta de evolución | enviar el formulario vacío | se rechaza con motivo | `POST → 201`, tarjeta sin bloques SOAP | 4 | MEDIDO | `crit:media` | `StoreEvolutionRequest.php:52-73`; `EvolutionModal.vue` | #425 |
| F03 | IA › detalle → anular | anular con motivo | la confirmación recibe el clic y `void → 200` | confirmación **debajo** del detalle; `elementFromPoint` = footer; sin POST | 3 | MEDIDO | `crit:alta` | `Modal.vue:151`; `UiConfirmDialog` en `AppLayout` | #423 |
| F04 | IA › detalle → anular citado | anular el análisis citado | la confirmación abre y el 422 se explica | mismo apilado: el clic no llega; el 422 sólo se mide en la API | 4 | MEDIDO | `crit:alta` | mismo origen que F03 | #423 |
| F05 | IA › sheet › imagen | elegir un archivo que no es imagen | error en línea del paso | `alert()` nativo; error en línea vacío | 4 | MEDIDO | `crit:media` | `FileUpload.vue:97` | #426 |
| F06 | IA › sheet › tamaño | elegir una imagen > 20 MB | error en línea y archivo no retenido | mensaje en línea, **vista previa retenida** y selector oculto | 4 | MEDIDO | `crit:media` | `AiAnalysisSheet.vue:218-227` | #426 |
| F07 | IA › alta | enviar un análisis válido | si falla, la pantalla dice por qué | `201` con «completado»; detalle «Fallido» sin causa | 4 | MEDIDO | `crit:media` | `AiImageAnalysisController.php:472-487`; `AiAnalysisPage.vue:586-589` | #427 |
| F08 | IA › filtros y KPIs | aplicar preset o filtro | KPIs y tabla, misma población; selector fiel | KPIs globales sin declararlo; selector muestra «Revisados» con modelo `''` | 4 | MEDIDO | `crit:media` | `AiAnalysisPage.vue:609`; `Select.vue:225-229` | #402 (selector) · aceptado el KPI global |
| F09 | IA › anulados | buscar análisis anulados | la superficie ofrece los que la API conserva | API `?voided=1` → 200; 0 controles en la interfaz | 4 | MEDIDO | `crit:media` | `AiAnalysisPage.vue` (filtros) | aceptado: bajo impacto, contrato de lectura |
| F10 | IA › acciones de fila | pulsar «Revisar» o «Anular» | cada acción hace lo que promete | las tres llaman a `viewAnalysis(row)` | 4 | MEDIDO | `crit:baja` | `AiAnalysisPage.vue:299-325` | aceptado: el detalle aloja las tres acciones |
| F11 | IA › detalle | abrir el detalle | nombre accesible | `accessible-name: ""` | 3 | MEDIDO | `crit:media` | `AiAnalysisModal.vue:2-18`; `Modal.vue:28-29` | #429 |
| F12 | IA › sheet | abrir y tabular | foco atrapado | `focus-trapped: outside` | 3 | MEDIDO | `crit:media` | `Sheet.vue` (trampa antes de la ref) | #385 |
| F13 | especialidades › id inexistente | editar/eliminar un id que no existe | `404` | `500` en `PUT` y `DELETE` (4 filas) | 5 | MEDIDO | `crit:media` | `SpecialtyRecordController.php:181-218` | #428 |
| F14 | tres superficies | mirar botones primarios sin requisitos | el deshabilitado explica qué falta | sin pista (`pista=null`) en historia, especialidades e IA | 4 | MEDIDO | `crit:baja` | `MedicalRecordsPage.vue`; `SpecialtyRecordsPage.vue`; `AiAnalysisSheet.vue` | aceptado: se habilita al completar |
| F15 | IA › alcance | abrir como implantólogo sin acceso al paciente | el vacío distingue «sin datos» de «fuera de tu alcance» | 0 filas y «Sin análisis registrados»; el administrador ve 3 | 2 | MEDIDO | `crit:baja` | `AiImageAnalysis.php::scopeVisibleTo` (`ClinicalAccess`) | aceptado: decisión de alcance clínico |
| F16 | especialidades › técnico por URL | abrir la ruta sin permiso | mensaje de permiso, sin reintento inútil | «Tu rol no tiene especialidades» + reintento que siempre 403 | 2 | MEDIDO | `crit:baja` | `SpecialtyRecordsPage.vue` (vacío por rol) | aceptado: el sidebar no la ofrece |
| F17 | historia e IA › formato | leer confianza y contador de adjuntos | formato único y legible | «88.00% · Media»; «1 DE 1» en mayúsculas | 4 | MEDIDO | `crit:baja` | `AiAnalysisModal.vue`; `AttachmentGallery.vue` | aceptado: cosmético |
| F18 | historia › título | abrir el índice | un solo título visible | `h1` del topbar + `.page-title` repiten «Historias clínicas» | 2 | MEDIDO | `crit:baja` | `AppLayout.vue` (`pageTitledRoutes` sin `/medical-records`) | #405 (misma clase) |

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| Ninguna ruta de borrado clínico es visible | **Cumple**: 0 controles de edición/borrado en historia, detalle y evoluciones; `PUT/PATCH/DELETE` → 405. El único borrado vivo es el del adjunto, que deja marcador (#244) |
| Cada tab vacía ofrece una acción | **No cumple**: Evoluciones y Estadísticas sin CTA (F01, #424); las otras dos tabs y las cinco de especialidades cumplen (6/8) |
| El técnico recibe 403 en toda la API y no ve sus entradas | **Cumple la puerta**: 403 en las tres raíces y en los 32 endpoints del módulo; sidebar sin entradas clínicas. La copia de `/specialty-records` por URL directa es engañosa (F16, aceptado) |
| Anular un análisis citado se rechaza con mensaje | **No cumple en la interfaz**: la confirmación queda debajo del detalle (F03/F04, #423); la API sí responde `422` con «No se puede anular un análisis citado en una evolución o en un plan.» |
| Cero `pageerror` | **Cumple**: 0 en las 19 filas de render, los 13 diálogos y las cuatro caminatas |
| Rúbrica puntuada | **Cumple**: 17/24, 21/24 y 13/24 (`ux-rubric.md`) |

## Revisión nativa (RDD)

**Sin veredicto: el relé del host no pudo completar las lentes.** Se intentó con
el corte 1 (`10dc93b..b7a84a4`, 2 325 líneas, nivel `high` por
`process_boundary` en `fixtures-guarded.js`) y, tras el fallo, con un candidato
reducido (`fixtures-guarded.js` + `fixtures.js`, 1 049 líneas). Estado por
linaje:

| Linaje | Candidato | Lentes | Resultado |
| ------ | --------- | ------ | --------- |
| `review-4d9de24c631629bb` | corte 1 (2 325 líneas) | risk, readability, reliability | `risk` y `readability` admitidos (2 resultados enviados); `reliability` rechazada en admisión dos veces (`review-reliability-1-89775071e47b.json`, `…-b0591473f16c.json`) y luego **expirada a los 982 200 ms** de un límite de 982 196 ms. Slot declarado `unachievable`; linaje detenido |
| `review-4d89e49a8d88c36e` | c2 reducido (1 049 líneas) | risk, readability, reliability | `risk` rechazada en admisión (`review-risk-1-f5398a4c4346.json`, 2 469 bytes, llaves balanceadas). 0 revisores enviados; linaje detenido |

Los payloads rechazados se conservan en
`.git/gentle-ai/rejected-results/<linaje>/` (fuera del árbol y de la evidencia
versionada). No hay aprobación, ni corrección, ni consumo de autoridad; la
entrega (push/PR) queda a decisión del dueño. El instrumento no se modificó para
satisfacer el rechazo: se respetó la regla de no reenviar los bytes rechazados.

## Delegación

Instrumento delegado a `gentle-ai-worker` (task `mvoqj495-1-sc1u`, background):
10 módulos y 7 957 líneas. El padre verificó la evidencia, corrigió dos
referencias del informe y tres filas de la rúbrica, y ejecutó la corrida
definitiva por su cuenta.

## Declarado como no observable

1. El texto que la interfaz habría mostrado al anular un análisis citado: la
   confirmación queda debajo del detalle (F03); el `422` y su motivo se miden en
   `api-matrix.json` y el toast genérico queda como `INFERIDO`.
2. Un análisis `completed` creado por la puerta real: sin `OPENAI_API_KEY` toda
   alta termina en `failed`; las tres filas completadas se colocaron con el
   puente tinker declarado (`fixtures.json.statusWrites`) y se revisaron por la
   puerta real.
3. Un análisis anulado visible en la lista: la anulación por interfaz está
   bloqueada (F03); `?voided=1` se midió por API y la ausencia de control en el
   DOM.

## Residuos de datos

La sesión creó 2 pacientes, 1 historia, 3 evoluciones, 2 addenda, 6 adjuntos y 4
análisis. El teardown retiró los 9 registros de especialidad por la única puerta
de borrado clínico disponible (200); los pacientes no se pueden borrar (422:
«historial clínico o financiero») y las historias, evoluciones y análisis no
tienen puerta (#292, #296), así que quedan declarados. Ninguna fila del seed se
tocó.
