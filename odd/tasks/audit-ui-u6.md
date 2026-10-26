# Task: U6 — Presupuestos y planes de tratamiento (auditoría UI, plan #13)

Status: DONE. Rama `feat/audit-ui-u6` desde `7da7ceb`; instrumento en
`tests/e2e/audit/u6/` entregado en tres cortes (`2d350fe`, `37b15bd`,
`0ee422f`), esta nota en su commit de documentación y hallazgos publicados en
#414–#420 (más el registro de sesión en el comentario de #369). Los tres cortes
pasaron la revisión nativa (tres linajes aprobados y consumidos). No se toca
código de producto.

## Specs

- **S1** — Objetivo (issue #369, verbatim): «Verificar que el dinero del
  presupuesto se explica en pantalla (base + IGV = total), que un documento
  aprobado no se puede editar y que el plan versionado no pierde el trabajo
  aceptado. Medido en `8b72d37`: `QuotationsPage` y `TreatmentPlansPage` usan
  `UiDataTable` y `UiModal`; el módulo de presupuestos acumula 12 `UiModal` y el
  de planes 12.»
- **S2** — Método, capa 1 (issue #369, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=quotations,treatment-plans --out=.atl/qa-evidence/audit/ui/U6/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U6
  ```
- **S3** — Superficies, roles y acciones (issue #369, verbatim): «Superficies:
  `/quotations` (lista y filtros, alta manual y desde plan, línea con
  procedimiento + sitio + convenio, desglose de IGV, aprobar, rechazar con
  motivo, PDF) y `/treatment-plans` (lista/kanban, modal de alta y edición,
  detalle, prioridad, secuencia, alternativa A/B, versión nueva, consumo de
  ítem). Roles: administrador, finanzas, recepcionista y odontólogo. Acciones:
  crear y enviar vacío; editar borrador; aprobar; intentar editar aprobado;
  descargar PDF; provocar una transición de estado inválida; abrir un
  formulario encima del detalle y comprobar la pila de modales.»
- **S4** — Entregable (issue #369, verbatim): «`render.json`,
  `primitives.json` (modal anidado), `walkthrough.md`, `ux-rubric.md` y
  `odd/tasks/audit-ui-u6.md`.»
- **S5** — Criterio de salida (issue #369, verbatim): «Base + IGV = total en
  pantalla y en el PDF; la edición de un aprobado se bloquea con mensaje
  explícito; un 422 de transición se pinta en su campo; el modal anidado cierra
  solo el superior; cero `pageerror`; rúbrica puntuada.»
- **S6** — Plan #13 §4 capa 2 (verbatim): «Por cada ruta y por cada rol que la
  ve (máximo 3 roles: el dueño del escritorio, administración y un rol excluido
  para la puerta negativa)»; «Cero `console.error`, cero `pageerror`, cero
  request ≥ 400 salvo allowlist declarada»; «Captura `desktop 1440×900` y
  `mobile 390×844`, **siempre y explícita**»; «Sin scroll horizontal de
  documento en 390 px.»
- **S7** — Plan #13 §4 capa 3 (verbatim): «Por cada diálogo abierto desde una
  superficie»: centrado (`|box.x + box.width/2 − vw/2| < 8`), docking, un solo
  `h1`, Escape cierra, foco atrapado, scroll bloqueado y etiqueta accesible.
  «Un diálogo que no cumple **una** fila es hallazgo, no ruido: es la clase
  "primitiva mal usada" y se agrupa con sus hermanos.»
- **S8** — Plan #13 §4 capa 4 (verbatim): los veredictos `OK`, `DEFECTO`,
  `NO-OP`, `CONFUSO`, `NO PROBADO`; «Toda acción con `DEFECTO`, `NO-OP` o
  `CONFUSO` produce fila en `findings.md` con captura y, si hay request, su
  status.»
- **S9** — Plan #13 §4 capa 6 y §5 (verbatim): «Doce criterios, 0/1/2 cada uno,
  por superficie primaria y una vez por sesión para su conjunto. La puntuación va
  a `ux-rubric.md` con la cita de lo observado.»
- **S10** — Plan #13 §6.1, §6.3 y §9 (verbatim): «la sesión **solo** escribe en
  `odontosuite_audit`; los borrados se hacen sobre filas que la propia sesión
  creó; nunca `migrate:fresh` sobre la base de desarrollo»; «`.atl/` está en
  `.gitignore` (verificado), así que las capturas y JSON **no se commitean**»;
  «La sesión que audita no escribe código de producto».
- **S11** — Plan #13 §8.1 (verbatim): «Un issue por clase raíz, nunca por
  síntoma ni por módulo»; «Tope por sesión: 3–7 issues»; «La sesión no cierra
  sus propios issues».
- **S12** — Alcance de capa 5 (derivado de S4): la unidad no pide
  `api-matrix.json`; los `403` que la sesión necesite para sus puertas negativas
  se miden dentro de las filas de render, no como matriz `rol × endpoint`.

## Estrategia de entrega

Un solo instrumento de medición —`tests/e2e/audit/u6/` (`helpers`, `pdf`,
`sources`, `fixtures`, `fixtures-guarded`, `render`, `walk-drivers`, `walk`,
`walk-plans`, `primitives`)— registrado en `surface.spec.js` con sólo el bloque
de imports, igual que U2/U3/U4/U5.

Cortes revisables, cada uno con el árbol ejecutable (el bloque de imports de
`surface.spec.js` crece con el corte): **1/3** `2d350fe` (2 098 líneas: helpers,
PDF, fuentes, fixtures y el tinker guardado), **2/3** `37b15bd` (673: render y
probes de carga/error) y **3/3** `0ee422f` (2 779: caminatas y pila de modales).
El presupuesto de ~400 líneas aplica a correcciones, no a un instrumento nuevo
completo; mismo criterio declarado en U0–U5.

Datos: la base `odontosuite_audit` tiene 41 procedimientos **todos con
`site_type = none`**, 0 convenios (`payers`) y 0 filas de tarifario
(`procedure_prices`), así que la línea «procedimiento + sitio + convenio» no se
puede ejercitar con el seed: los fixtures crean el procedimiento con sitio, el
convenio y su tarifa por las puertas de producto y lo declaran. El estado `sent`
de un presupuesto **no tiene puerta en el SPA** (medido en el código: sólo
`BillingService` lo escribe al generar desde una cita cerrada), así que la
caminata usa la puerta real, coloca `sent`/`approved` con el tinker guardado y
lo declara como fixture, nunca como comportamiento observado.

## Tasks

- [x] **T1** · S2, S10 · `inline` · Precondiciones: app `:8000` con
  `DB_DATABASE=odontosuite_audit`, Vite `:5173`, Reverb `:8081` y `queue:work`
  sobre la misma base; capa 1 → `manifest.json`: 2 superficies, **56 acciones
  (0 sin clasificar)**, **7 diálogos**, **19 endpoints únicos**, **0 permisos
  sin resolver**.
- [x] **T2** · S1–S9, S12 · `delegated` · Instrumento `@U6` en
  `tests/e2e/audit/u6/` (10 módulos, 5 534 líneas) registrado en
  `surface.spec.js` (sólo imports). Commits `2d350fe`, `37b15bd`, `0ee422f`.
  Delegado a un escritor acotado y verificado por lectura propia (ver L7–L9).
- [x] **T3** · S2, S3, S5 · `inline` · Corrida definitiva: **15/15, 2.9 min,
  exit 0**; `ui-pass --no-run` → gate OK, 2/2 renderizan, 0 `pageerror`, 44
  solicitudes en allowlist, 9 diálogos y 0 filas fallidas. La corrida se repitió
  íntegra por la sesión sobre la evidencia del escritor.
- [x] **T4** · S4, S8–S11 · `inline` · `walkthrough.md`, `ux-rubric.md`
  (17/24 y 16/24), `render.json`/`render/`, `primitives.json`/`primitives/`,
  `loading/`, `error/`, galería `index.html`, PDF en `pdf/` y esta nota;
  hallazgos agrupados por clase raíz y publicados #414–#420, más el registro de
  sesión en #369.
- [x] **T5** · S5, S10, S11 · `inline` · Cierre: `pnpm format:check` y
  `pnpm lint:check` en verde (0 errores; 36 avisos preexistentes de
  `vue/one-component-per-file`), `pnpm test:js` → **141 archivos y 1 225
  tests**, `pnpm exec playwright test --list` → **254 tests en 24 archivos**,
  commits, revisión nativa y PR. Resultado de la revisión en su sección.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 369-U6».
- **L2** — Cuerpo de #369 (S1–S5 verbatim) y plan #13 §4 (capas 2–6), §5
  (rúbrica), §6 (anatomía y evidencia), §8 (protocolo de issues) y §9
  (guardarraíles).
- **L3** — Estado al abrir la sesión (`7da7ceb`, `main` limpio, U5 fusionada):
  `QuotationsPage.vue` (766 líneas) y `TreatmentPlansPage.vue` (744) montan
  `UiDataTable`/`UiModal` y sus modales (`QuotationModal` 744,
  `QuotationDetail` 364, `TreatmentPlanModal` 826, `TreatmentPlanDetail` 418).
- **L4** — Entorno: app, Vite, Reverb y worker arriba sobre `odontosuite_audit`.
  Medido en la base: 15 usuarios, 100 pacientes, 3 sedes, 1 presupuesto (`sent`),
  0 planes, 0 ítems de plan, 0 convenios, 0 tarifario, 41 procedimientos con
  `site_type = none` y 0 atenciones cerradas.
- **L5** — Causa raíz medida antes de instrumentar: el SPA no escribe `sent` en
  un presupuesto. `QuotationModal` crea `draft`; aprobar y rechazar exigen
  `sent|viewed` (`QuotationService::DECISION_PENDING`) y el `PDF` sólo pasa a
  `viewed` desde `sent`. La única puerta que produce `sent` es `BillingService`
  al generar desde una cita cerrada. El plan versionado (prioridad, secuencia,
  alternativas A/B, versiones, consumo) tampoco tiene superficie:
  `TreatmentPlanResource` los publica, `resources/js` no los lee.
- **L6** — Sin `pdftotext`, `mutool`, `qpdf` ni `pypdf` en la máquina: el PDF se
  mide inflando sus streams Flate con `zlib` en Node sobre el binario que
  devuelve la API (`tests/e2e/audit/u6/pdf.js`), y se declara el método.
- **L7** — El instrumento se delegó a un escritor acotado (*context backstop* de
  la sesión): 10 módulos, 5 534 líneas, registro sólo en el bloque de imports.
  Corrió 15/15 a la primera y dejó 11 defectos medidos.
- **L8** — Verificación propia: se releyeron el manifiesto, las 8 filas de
  render, los 10 diálogos, las dos caminatas y los JSON de carga/error; se
  volvió a correr la sesión completa (`15 passed, exit 0, 2.9 min`) y se generó
  la galería con `ui-pass --no-run` (gate OK). Trampa de entorno repetida de U5:
  `ui-pass` reescribe `render.json`/`primitives.json` con su forma de envoltorio;
  las filas por rol y por diálogo conservan los hechos en sus archivos.
- **L9** — Triage por clase raíz: F1+F2 → #414; F3+F4+F10 → #415; F5 → #416;
  F6+F8 → #417; F7 → #418; F9 → #419; F11 → #420. La falta de puerta a `sent`
  se registra en el comentario de #369 y en la rúbrica, no como issue: el tope
  de §8.1 es 3–7 y las siete clases publicadas son las que tienen defecto
  reproducible con captura.
- **L10** — Residuos declarados en `fixtures.json`: 6 presupuestos (el `DELETE`
  sólo acepta `draft`), 1 plan, el paciente de la sesión y el convenio (sin
  puerta de borrado); el procedimiento y el tarifario quedan con su razón.
  Ninguna fila del seed se tocó.
- **L11** — Cierre: `pnpm format:check` en verde, `pnpm lint:check` 0 errores
  (36 avisos preexistentes), `pnpm test:js` → 1 225 tests en 141 archivos,
  `playwright test --list` → 254 tests en 24 archivos. Revisión nativa en su
  sección.

## Línea base medida

- Revisión del árbol medido: `7da7ceb` + instrumento; árbol limpio salvo el
  instrumento y esta nota. Corrida: `2026-10-26T02:02Z`.
- **Capa 1** · `ui-manifest --module=quotations,treatment-plans`: 2
  superficies, **56 acciones (0 sin clasificar)**, **7 diálogos**, **19
  endpoints únicos**, **0 permisos sin resolver**; 2 rutas `excluded` por
  `redirect`.
- **Capas 2–4** · `pnpm exec playwright test … --grep @U6` → **15 pasan**, exit
  0, **2.9 min**.
- **Envoltorio** · `ui-pass --module=quotations,treatment-plans --no-run` →
  **gate OK**; 2/2 superficies `renders`; **0 `pageerror`**; 44 solicitudes
  esperadas; 0 inesperadas; 0 acciones sin clasificar; 9 diálogos y 0 filas
  fallidas (la décima fila es la pila anidada, que vive sólo en
  `primitives.json`).
- **Render por rol** · presupuestos 1435 / 1435 / 1335 / 1435 caracteres
  (administrador, finanzas, recepción, odontólogo), 7 filas, 1 `h1`, 0 overflow
  en 390 px; planes 3301 / 539 / 539 / 3301, 15 / 0 / 0 / 15 tarjetas, 1 `h1`,
  0 overflow. **0 `pageerror`**, 0 solicitudes inesperadas; los `console.error`
  son el 403 de `/api/broadcasting/auth` (allowlist U0 F1).
- **Primer pintado** · presupuestos: 1 skeleton y KPIs «N/D» (OK); planes: 7
  skeletons y KPIs vacíos (OK). **Error + reintento**: panel y «Reintentar» en
  las dos, recuperación 200 (OK).
- **Diálogos** · 10/10 abiertos, **0 fallos de contrato**; la pila anidada
  cierra sólo el superior con el detalle montado, pero deja **1 `pageerror`**
  (F9).
- **Caminata de presupuestos** · **14 pasos, 10 OK / 4 hallazgos**; detalle
  `lista 140,00 · base 118,64 · IGV 21,36 · total 140,00`; PDF 5 087 bytes con
  las mismas filas (11 864 + 2 136 = 14 000 céntimos); resumen del alta 120,00
  con IGV 0 → persistido 140,00 con IGV 21,36 y `price_source: payer`; lista vs
  payload 8/8.
- **Caminata de planes** · **13 pasos, 8 OK / 5 hallazgos**; alta con 422 por
  descripción «opcional», detalle con 0 líneas (API 2, S/ 180,00), pila anidada
  con `pageerror`, tres transiciones válidas OK, transición inválida con motivo
  ausente del DOM, edición con 1 línea vacía; superficies ausentes medidas en
  ambos lados (API publica, DOM y módulo no).
- **Rúbrica** · `/quotations` **17/24** (dos criterios en 0: acciones y
  honestidad); `/treatment-plans` **16/24** (los mismos dos en 0).

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- |
| F1 | presupuestos › alta | leer el resumen vivo | base + IGV = total con la tasa efectiva | total S/ 120,00 con **IGV S/ 0,00** | 4 | MEDIDO | `crit:alta` | `QuotationModal.vue:407,521-525` | #414 |
| F2 | presupuestos › guardar | guardar la línea con convenio | el documento guardado es el del resumen | resumen S/ 120,00 → persistido **S/ 140,00** (IGV 21,36) | 4 | MEDIDO | `crit:alta` | `QuotationModal.vue:455` vs `QuotationService.php:548-560` | #414 |
| F3 | presupuestos › editar aprobado | enviar el formulario de un aprobado | 422 con el motivo explícito | `[data-submit-error]` = «Error de validación» | 4 | MEDIDO | `crit:media` | `QuotationController.php:257` + `QuotationModal.vue:652` | #415 |
| F4 | presupuestos › decisión obsoleta | aprobar una fila ya aprobada | 422 con el motivo | `[data-modal-error]` = «Error de validación» | 4 | MEDIDO | `crit:media` | `QuotationController.php:186` + `QuotationApprovalModal.vue:133` | #415 |
| F5 | presupuestos › picker | buscar por código | el buscador consulta al catálogo | `REST-COMP-1S` (página 4) → 0 resultados | 4 | MEDIDO | `crit:media` | `ProcedureCatalogPicker.vue:91-97,109-113` | #416 |
| F6 | planes › tarjeta y detalle | abrir un plan de 2 líneas | lista las líneas y su total | «0 procedimientos» / «No hay procedimientos» (API 2) | 2+4 | MEDIDO | `crit:alta` | `TreatmentPlanController.php:93`; `TreatmentPlanService.php:585`; `TreatmentPlansPage.vue:570-573` | #417 |
| F7 | planes › alta | crear sin la descripción «opcional» | el alta se guarda | **422** `items.*.description: …obligatoria` | 4 | MEDIDO | `crit:alta` | `StoreTreatmentPlanRequest.php:80,121` vs `TreatmentPlanModal.vue:235` | #418 |
| F8 | planes › editar | editar el plan creado | el formulario llega con sus 2 líneas | **1 línea vacía**; envío detenido sin petición | 4 | MEDIDO | `crit:alta` | mismo origen que F6 | #417 |
| F9 | planes › pila anidada | Escape sobre Editar encima del detalle | cierra sólo el superior y el detalle sigue legible | pila 2→1, detalle montado, **`plan = null`**: `Cannot read properties of null (reading 'title')` | 3+4 | MEDIDO | `crit:alta` | `TreatmentPlansPage.vue:575-579` | #419 |
| F10 | planes › transición inválida | «Completado» con líneas pendientes | el 422 se explica en pantalla | 422 con motivo; la página sólo toasta «No se pudo cambiar el estado» | 4 | MEDIDO | `crit:media` | `TreatmentPlansPage.vue:629-638` | #415 |
| F11 | ambas × rol | pulsar el alta sin permiso | lo que la UI ofrece, la API lo acepta | 3 filas: botón visible y `POST` **403** | 2 | MEDIDO | `crit:media` | `QuotationsPage.vue:32`; `TreatmentPlansPage.vue:82` | #420 |

Observaciones sin issue: un presupuesto creado en `/quotations` no puede llegar a
`sent` (sin puerta en el SPA; la decisión sólo es alcanzable desde una cita
cerrada), el paso 11 de planes apila dos toasts «Estado actualizado» junto al de
error, el panel de error de planes imprime el `message` de la API tal cual, y el
descuento > 0 % no se caminó por la interfaz (los fixtures cubren 0 %).

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| Base + IGV = total en pantalla y en el PDF | **Cumple en el detalle y en el PDF** (11 864 + 2 136 = 14 000 céntimos, ambos); **no cumple en el resumen del alta** (F1/F2) |
| La edición de un aprobado se bloquea con mensaje explícito | **Bloqueo sí** (la fila no ofrece «Editar» y el `PUT` responde 422); **mensaje explícito no** (F3) |
| Un 422 de transición se pinta en su campo | **No cumple**: presupuestos pinta «Error de validación» (F4); planes sólo un toast sin motivo (F10) |
| El modal anidado cierra sólo el superior | **Cumple la pila** (2→1, detalle montado con su contenido); **no cumple la salud** (F9) |
| Cero `pageerror` | **No cumple**: 0 en las 8 filas de render, los 2 probes de carga, los 2 de error y la caminata de presupuestos; **1** en la pila anidada de planes (F9) |
| Rúbrica puntuada | **Cumple**: 17/24 y 16/24 (`ux-rubric.md`) |

## Revisión nativa (RDD)

El candidato completo (`origin/main` → `a29528e`, 12 archivos y 5 779 líneas)
**no pudo revisarse como una sola unidad**: `review.start` respondió en preflight
`lens_context_budget_exceeded` y **no creó autoridad** (`mutation_performed:
false`). La continuación que indicó el proveedor es partir la unidad en
candidatos más pequeños; como los cortes ya existían como commits, cada uno se
revisó como candidato propio desde un *worktree* de este mismo clon
(`.atl/review-cut1`, `.atl/review-cut2`, `.atl/review-cut3`, ignorados por git),
con el `baseRef` del corte anterior.

| Corte | Linaje | Nivel | Lentes | Resultado |
| ----- | ------ | ----- | ------ | --------- |
| `2d350fe` (fixtures y helpers) | `review-6b156558d0c16738` | high | risk, readability, reliability | **approved** y consumido; 19 hallazgos informativos |
| `37b15bd` (render) | `review-752175e9393f2ce0` | medium | readability, reliability | **approved** y consumido; 7 hallazgos informativos |
| `0ee422f` (caminatas y diálogos) | `review-87a5123a420dcb5f` | medium | readability, reliability | **approved** y consumido; 13 hallazgos informativos |

- El único motivo de nivel `high` fue `process_boundary` en
  `tests/e2e/audit/u6/fixtures-guarded.js` (el tinker guardado); quedó aislado en
  el corte 1, que es el que lo revisó.
- Ningún hallazgo bloqueó: los 39 son `informational` (sugerencias y avisos de
  legibilidad y de fiabilidad del instrumento). Los más citables como trabajo
  posterior: `R3-005` (cobertura del parser PDF), `R2-PDF-MISSING-ASYMMETRY`
  (simetría de la comparación de filas faltantes), `R3-firstpaint-timing-race`
  (ventana del primer pintado), `R3-classifyfailures-expectation-swap`
  (expectativa de la allowlist) y `R2-PLAN-PRICE-MISMATCH` (precio del fixture de
  plan contra su propio cálculo). No se abren issues por el tope de §8.1.
- Dos intentos del relé del host fueron rechazados en admisión antes de lograrlo
  (un payload de `reliability` truncado en el corte 1 y uno de `readability` con
  arreglos desbalanceados en el corte 3). En ambos casos se consultó STATUS y se
  reejecutó **sólo** el slot reofrecido, sin reenviar los bytes rechazados.
- La revisión no autoriza la entrega: push y PR siguen la política ordinaria del
  repositorio.
