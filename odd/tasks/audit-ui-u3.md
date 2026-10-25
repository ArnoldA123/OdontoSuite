# Task: U3 — Agenda y consulta (auditoría UI, plan #13)

Status: DONE. Rama `feat/audit-ui-u3` desde `94eb1f9`; instrumento `@U3` en
`tests/e2e/audit/u3/`, evidencia en `.atl/qa-evidence/audit/ui/U3/`
(gitignored), esta nota en el commit de documentación y hallazgos publicados en
#391–#397.

## Specs

- **S1** — Objetivo (issue #366, verbatim): «Verificar el ciclo de vida completo
  de una cita en la interfaz —crear, reprogramar, cancelar, recibir, atender,
  cerrar y dejar continuidad— y que el asistente de cierre se comporte como una
  primitiva del sistema. Medido en `8b72d37`: `CalendarPage` (1 376 líneas) no
  usa `Skeleton` ni `UiDataTable` y concentra 7 `<button>`; `ConsultationWizard`
  es el único overlay propio (`fixed inset-0`) fuera de `UiModal`/`UiSheet`.»
- **S2** — Método, capa 1 (issue #366, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=appointments --out=.atl/qa-evidence/audit/ui/U3/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U3
  ```
- **S3** — Superficies, roles y acciones (issue #366, verbatim): «Superficies:
  `/calendar` (día/semana/mes, navegación de fechas, filtros por profesional,
  sillón y sede, alta, reprogramación por drag, custodia, anuncio) y
  `ConsultationWizard` (evolución, diagnósticos CIE-10, materiales, ítems de
  plan, firma, continuidad). Roles: administrador, recepcionista, odontólogo y
  técnico; finanzas como puerta negativa. Acciones: crear con envío vacío;
  reprogramar; cancelar con motivo; check-in; anunciar; iniciar atención;
  recorrer los 5 pasos; firmar con contraseña inválida y con la válida; cerrar;
  dejar continuidad.»
- **S4** — Entregable (issue #366, verbatim): «`render.json`, `primitives.json`
  del asistente (centrado/overlay, Escape, foco, pila con un modal encima),
  `walkthrough.md` del ciclo completo, `api-matrix.json` de los endpoints de la
  sesión, `ux-rubric.md` y `odd/tasks/audit-ui-u3.md`.»
- **S5** — Criterio de salida (issue #366, verbatim): «Una cita creada en la
  sesión recorre crear → check-in → iniciar → cerrar → cobrar sin callejón y
  deja su solicitud de continuidad; el asistente cumple el contrato de
  primitivas o queda como hallazgo con captura; cero `pageerror`; rúbrica
  puntuada.»
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
- **S9** — Plan #13 §4 capa 5 (verbatim): «Para los endpoints que la sesión
  consume: matriz `rol × endpoint` con el status esperado derivado de
  `App\Authorization\Permissions` y el observado con un token real.»
- **S10** — Plan #13 §4 capa 6 y §5 (verbatim): «Doce criterios, 0/1/2 cada uno,
  por superficie primaria y una vez por sesión para su conjunto. La puntuación va
  a `ux-rubric.md` con la cita de lo observado.»
- **S11** — Plan #13 §6.1, §6.3 y §9 (verbatim): «la sesión **solo** escribe en
  `odontosuite_audit`; los borrados se hacen sobre filas que la propia sesión
  creó; nunca `migrate:fresh` sobre la base de desarrollo»; «`.atl/` está en
  `.gitignore` (verificado), así que las capturas y JSON **no se commitean**»;
  «La sesión que audita no escribe código de producto».
- **S12** — Plan #13 §8.1 (verbatim): «Un issue por clase raíz, nunca por
  síntoma ni por módulo»; «Tope por sesión: 3–7 issues»; «La sesión no cierra
  sus propios issues».

## Estrategia de entrega

Un solo instrumento de medición —`tests/e2e/audit/u3/` (`fixtures`, `calendar`,
`wizard`, `api-matrix`)— registrado en `surface.spec.js`, igual que U2. El
escritor delegado lo redactó y lo corrió; la sesión lo corrigió tras la primera
corrida (ver «Correcciones del instrumento»), midió la capa 1, redactó los
entregables, agrupó los hallazgos y publicó **siete** issues. No se toca código
de producto (S11). El asistente tiene **siete** pasos, no cinco: la sesión mide
lo que existe y la diferencia se declara aquí.

El commit `d4a2283` (4 021 líneas autoradas, 5 archivos) es el **límite
declarado de esta unidad**: los cuatro módulos son un solo instrumento de
medición (comparten fixtures y registro en `surface.spec.js`, y partirlos
dejaría el árbol roto o dos mitades de la misma medición). El presupuesto de
~400 líneas aplica a correcciones, no a este instrumento nuevo; mismo criterio
que U0/U1/U2.

Datos: los fixtures crean por API una cita por estado (vista, drag, custodia,
asistente); la cita del ciclo de vida se crea **por la interfaz**, se cierra por
el asistente y se intenta cobrar en `/cash-register/ready-to-bill`. Las filas
propias se retiran al final; la cita cerrada queda como evidencia del cierre.

## Tasks

- [x] **T1** · S2, S6–S11 · `inline` · Precondiciones: `odontosuite_audit`
  recreada y sembrada (17 seeders), app `:8000` con
  `DB_DATABASE=odontosuite_audit`, Vite `:5173`, Reverb `127.0.0.1:8081` y
  `queue:work` sobre la misma base; revisión anotada `94eb1f9`.
- [x] **T2** · S1–S9 · `delegated` · Instrumento `@U3`:
  `tests/e2e/audit/u3/{fixtures,calendar,wizard,api-matrix}.js` (4 021 líneas con
  `surface.spec.js`) registrados en `surface.spec.js`. Commit `d4a2283`.
- [x] **T3** · S2, S3, S5, S11 · `inline` · Capa 1 (manifiesto de `appointments`)
  y corrida completa: **17 tests, 3.8 min, exit 0**; `ui-pass --no-run` → gate
  OK. Correcciones del instrumento aplicadas y re-medidas.
- [x] **T4** · S4, S10, S11, S12 · `inline` · `walkthrough.md`, `ux-rubric.md`,
  `primitives.json` (+ `primitives/wizard.json`), `api-matrix.json`,
  `render.json` + `index.html` y esta nota; hallazgos publicados #391–#397.
- [x] **T5** · S5, S11, S12 · `inline` · Cierre: `pnpm format:check`,
  `pnpm lint:check`, `pnpm test:js`, `pnpm exec playwright test --list`,
  commits (instrumento y nota), revisión nativa y PR.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 366-U3».
- **L2** — Cuerpo de #366 (lo citado en S1–S5 es verbatim de ahí) y plan #13 §4
  (capas 1–6), §5 (rúbrica), §6 (anatomía y evidencia), §7 (U3, L, divisible en
  U3a calendario · U3b asistente), §8 (protocolo de issues) y §9 (guardarraíles).
- **L3** — Estado al abrir la sesión (`94eb1f9`): `CalendarPage.vue` tiene
  `data-calendar-*` y `LoadingSpinner` (no `Skeleton`); **no** ofrece filtros por
  profesional, sillón ni sede. `ConsultationWizard.vue` es un `Teleport` con
  `fixed inset-0` propio, sin `role="dialog"`, sin trampa de foco y sin Escape;
  tiene siete pasos y usa `UiTabs`/`UiButton`. `changeStatus('cancelled')` no
  pide motivo ni confirma. El cierre acepta `recall` por API pero el asistente no
  lo expone.
- **L4** — Entorno: base `odontosuite_audit` recreada y sembrada; servicios
  arriba; login por API verificado. La primera corrida del escritor delegado
  dejó el instrumento completo y una corrida entera, pero la sesión la tomó como
  intermedia: el usuario pidió cancelar el subagente por demora y el trabajo se
  continuó inline.
- **L5** — Correcciones del instrumento antes de la corrida definitiva: (a) la
  sonda de filtros leía el `Sillón` de los chips como si fuera un filtro
  (`present: true` falso) y ahora excluye `[data-calendar-appointment]`; (b) el
  probe de «horario liberado» pasó de un segundo arrastre frágil a la misma
  puerta que usa el drag (`PATCH /reschedule`), con fila propia creada y
  cancelada; (c) se añadió la medición móvil de la rejilla (ancho de columna y
  de chip) en la capa 2; (d) el alta ya no convierte los `pageerror` medidos en
  fallo del instrumento: los registra con su causa y el criterio de sesión se
  puntúa; (e) la medición móvil del asistente captura la primitiva abierta y
  cierra con su botón.
- **L6** — Corrida definitiva sobre base recreada: **17/17 verdes, 3.8 min**;
  `node scripts/audit/ui-pass.mjs --module=appointments --no-run` → 1/1
  renderizan, 0 `pageerror`, gate OK, 1 diálogo con 6 filas fallidas, 22
  solicitudes esperadas.
- **L7** — Hallazgos publicados tras medirlos y agruparlos por clase raíz: #391
  (asistente fuera de la primitiva), #392 (500 al reprogramar sobre un horario
  liberado), #393 (el cobro de la atención cerrada responde 409), #394
  (cancelar sin confirmación ni motivo), #395 (sin filtros de agenda), #396
  (errores de la agenda: vacío falso y pageerrors del alta), #397 (vista semanal
  cortada en móvil).
- **L8** — Observaciones sin issue: el badge «En vivo» afirma actualizaciones en
  tiempo real con las 9 suscripciones abortadas (se deja en el backlog de la
  sesión por impacto bajo, criterio 12 de la rúbrica en 0); finanzas ve una
  agenda «vacía» en vez de una denegación (clase «estados falsos», se suma a
  #396); el asistente no expone el recall 3/6/12 que la API acepta (superficie
  ausente declarada); la reprogramación por teclado no existe (no se midió).

## Línea base medida

- Revisión del árbol medido: `94eb1f9` + instrumento; árbol limpio salvo la nota.
- **Capa 1** · `node scripts/audit/ui-manifest.mjs --module=appointments`:
  exit 0, **1 superficie** (`/calendar`, 6 roles de agenda), **45 acciones
  (0 sin clasificar)**, **3 diálogos**, **21 endpoints**, **0 permisos sin
  resolver**; 2 redirecciones excluidas. El asistente **no** figura como
  diálogo: no es primitiva.
- **Capas 2–4** · `pnpm exec playwright test … --grep @U3` → **17 pasan**, exit
  0, **3.8 min**.
- **Envoltorio** · `ui-pass --module=appointments --no-run` → **gate OK**: 1/1
  renderizan, 0 excluidas, **0 `pageerror`**, **22 solicitudes esperadas**
  (allowlist U0 F1), 0 inesperadas, 0 acciones sin clasificar, contrato de
  primitivas con **1 diálogo y 6 filas fallidas**.
- **Render por rol**: administrador, odontólogo y recepción 987 caracteres, 9
  chips de semana, 9 de día, 3 de mes, 42 celdas de mes, 1 `h1` «Calendario»,
  0 overflow; finanzas 283 caracteres, 0 chips, 1 `h1` «OdontoSuite», 14 × 403
  esperados y el vacío visible. Carga: `LoadingSpinner` («Cargando agenda...»),
  **0 skeletons**.
- **Móvil 390**: documento sin scroll (`scrollWidth 390`) pero rejilla semanal
  comprimida (columna 38 px, chip 29 px, cabecera cortada). El asistente cabe
  (374×466) y no desborda.
- **Asistente** (`primitives.json`): centrado 0 px, `h1` único y cierre por
  fondo OK; **fallan** `dialog-role` (0), `aria-modal`, `accessible-name`
  (vacío), `focus-trapped`, `scroll-locked` y `escape-closes`. Con un `UiModal`
  encima, Escape cierra solo el superior y el foco vuelve, pero el scroll se
  libera con el asistente abierto (`wizard-stack.json`).
- **Ciclo de vida**: alta por interfaz 201 → check-in 200 → anuncio 200 →
  inicio 200 → 7 pasos → firma inválida 422 (cita sigue `in_consultation`) →
  firma válida 200 (cita `completed`, `final_amount 50`, acto 19) → solicitud de
  continuidad `pending` (2026-11-01 14:00–14:30). **Cobro detenido**: el
  «Desglose» no ofrece cobrar y `POST /api/pending-payments/{id}/pay` responde
  409 «La cita no tiene saldo pendiente.» con saldo publicado 50.
- **Drag**: 10:00 → 13:00 con 200, toast y chip movido; soltar en la propia
  celda no emite request; el horario liberado por una cancelación responde
  **500**.
- **Cancelación**: sin confirmación, sin motivo, PATCH 200, «Estado
  actualizado», recordatorios cancelados. **Error de carga**: solo toast y el
  vacío; 0 bloques de error/reintento; 9 chips al cambiar de vista.
- **Capa 5** · `api-matrix.json`: **133 filas (7 roles × 19 endpoints), 133 OK,
  0 desajustes, 0 saltadas**; expectativa derivada de
  `App\Authorization\Permissions` (matriz `GRANTS` por reflexión) y observada
  con token real; los `DELETE` usan una fila propia por rol (7 creadas, 7
  retiradas).
- **Rúbrica**: `/calendar` **13/24** (ceros en estados, responsive y
  honestidad), asistente **14/24** (ceros en primitivas y accesibilidad).

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- |
| F1 | asistente | Abrir/cerrar el cierre | Diálogo con rol, foco, Escape y scroll | 6 checks fallidos; fondo descarta sin confirmar; la pila pierde el scroll | 3/4 | MEDIDO | `crit:media` | `ConsultationWizard.vue`: overlay propio sin `UiModal`/`useFocusTrap` | #391 |
| F2 | `/calendar` | Reprogramar a un horario liberado | 200 (o 422 con conflicto) | **500** «Error al reprogramar la cita» | 4 | MEDIDO | `crit:alta` | `findConflicts` excluye canceladas; el índice único no | #392 |
| F3 | cierre → cobro | Cobrar la atención cerrada | La deuda publicada se cobra | Desglose sin acción; `pay` responde 409 «La cita no tiene saldo pendiente» | 4 | MEDIDO | `crit:alta` | `pay()` lee `total_cost/paid_amount`; la lista y el cierre usan `final_amount` | #393 |
| F4 | `/calendar` | Cancelar una cita | Confirmación + motivo | PATCH directo, «Estado actualizado», recordatorios cancelados | 4/6 | MEDIDO | `crit:media` | `changeStatus` sin `useConfirm` ni campo de motivo | #394 |
| F5 | `/calendar` | Filtrar por profesional/sillón/sede | Controles operables | 0 controles; API filtra por `user_id`/`branch_id`, no por sillón | 1/4 | MEDIDO | `crit:media` | Superficie nunca escrita (`type:no-terminado`) | #395 |
| F6 | `/calendar` | Fallo de lectura y alta | Error con reintento; cero pageerror | Vacío «Sin citas esta semana» sin reintento; 2 `pageerror` + 403 de favoritos al abrir el alta | 4 | MEDIDO | `crit:media` | `loadAppointments` solo toastea; `ProcedureQuickPicker` no maneja el 403 | #396 |
| F7 | `/calendar` 390 px | Vista semanal | Legible o con affordance | Columna 38 px, chip 29 px, cabecera cortada, chips «1.» | 2 | MEDIDO | `crit:media` | `grid-cols-8` sin corte móvil y vista inicial «semana» | #397 |

## Correcciones del instrumento

No son hallazgos de producto: (a) la sonda de filtros leía el sillón de los
chips; (b) el probe de horario liberado dependía de un segundo arrastre y pasó a
la puerta API que usa el drag, con fila propia; (c) la capa 2 no medía la
compresión móvil y ahora mide columna y chip; (d) el alta registra sus
`pageerror` como defecto medido en vez de fallar el instrumento; (e) la medición
móvil del asistente captura la primitiva abierta y la cierra con su botón.

## Revisión nativa (RDD)

- Linaje `review-24c5643b0262bf78`, nivel `high`, lentes `readability` y
  `reliability`; candidato de 6 archivos y 4 246 líneas sobre `94eb1f9`
  (`d4a2283` + `4f5c59c`). Previsión de 2 ejecuciones de modelo por el relé del
  host.
- El grupo de dos lentes se sometió con una sola captura; el relé del revisor
  de `reliability` falló porque intentó una llamada a herramienta
  (`pi-host-relay-transport-failure`, 0 revisores preparados, 0 enviados). No se
  repitió la captura desde la transcripción: STATUS volvió a ofrecer los dos
  slots y se capturó uno por uno con su binding exacto.
- Veredicto: **aprobado**; autoridad consumida (`authority: burned`).
- Trece hallazgos asesores, todos no bloqueantes e informativos: `R2-01`–`R2-08`
  (legibilidad del instrumento) y `R3-001`–`R3-005` (sugerencias de la suite).
  Ninguno abrió corrección ni reabre el candidato.
- La entrega sigue la política ordinaria: la revisión no autoriza push ni merge
  por sí sola.
