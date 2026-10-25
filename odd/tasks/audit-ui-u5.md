# Task: U5 — Dinero: caja, cobros y reportes (auditoría UI, plan #13)

Status: DONE. Rama `feat/audit-ui-u5` desde `61eb568`; instrumento en
`tests/e2e/audit/u5/` entregado en tres cortes revisables (`dd890f1`, `978e921`,
`6f13a8e`), esta nota en el commit de documentación y hallazgos publicados en
#407–#412 (más un comentario medido en #393). No se toca código de producto.

## Specs

- **S1** — Objetivo (issue #368, verbatim): «Verificar que cada movimiento de
  dinero deja el saldo que promete y que el usuario entiende por qué un cobro se
  rechaza. Medido en `8b72d37`: `CashRegisterPage` no usa `Skeleton` ni
  `LoadingSpinner` pese a que su primera carga depende de la sesión y los saldos;
  el módulo concentra 23 `UiModal`, 2 tabs y 1 185 líneas entre página y
  `ReadyToBillPage`.»
- **S2** — Método, capa 1 (issue #368, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=cash-register --out=.atl/qa-evidence/audit/ui/U5/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U5
  ```
- **S3** — Superficies, roles y acciones (issue #368, verbatim): «Superficies:
  `/cash-register` (Sesión, Cobros, Transacciones, Movimientos, Historial,
  Reportes; apertura, cierre, cobro, devolución, depósito, movimiento manual,
  recibo) y `/cash-register/ready-to-bill`. Roles: administrador, finanzas y
  recepcionista. Acciones: abrir sesión y abrirla dos veces; cerrar con
  diferencia; cobrar con cada familia de método y sin referencia cuando es
  obligatoria; devolver con autorizador y motivo; depositar con tope y
  referencia; ver recibo; leer conciliación y depósitos pendientes; probar el
  bloqueo contable con y sin `lock_override_reason`; intentar cobrar sin sesión
  abierta.»
- **S4** — Entregable (issue #368, verbatim): «`render.json`, `primitives.json`,
  `walkthrough.md` con la aritmética por movimiento, `api-matrix.json` con los
  403 esperados, `ux-rubric.md` y `odd/tasks/audit-ui-u5.md`.»
- **S5** — Criterio de salida (issue #368, verbatim): «Cada tipo de movimiento
  deja el saldo esperado y el reporte de período cuadra con la gaveta; el 422 de
  referencia y el 403 del bloqueo se muestran en su campo y en línea; una
  devolución no puede exceder lo cobrado; cero `pageerror`; rúbrica puntuada.»
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

Un solo instrumento de medición —`tests/e2e/audit/u5/` (`helpers`, `fixtures`,
`render`, `walk`, `primitives`, `ready-to-bill`, `api-matrix`)— registrado en
`surface.spec.js` con sólo el bloque de imports, igual que U2/U3/U4. No se toca
código de producto (S11).

La unidad son 6 496 líneas autoradas en 8 archivos, entregadas en **tres cortes
revisables** (2 084 + 3 223 + 1 189 líneas): `dd890f1` (helpers, fixtures y
render), `978e921` (caminatas y diálogos) y `6f13a8e` (listos para facturar y
matriz). El primer intento de revisión nativa rechazó el candidato completo por
`lens_context_budget_exceeded`; partir la unidad en cortes que cada uno quepa en
el presupuesto es la continuación que indica el proveedor. Cada corte deja el
árbol ejecutable (el bloque de imports de `surface.spec.js` crece con él). El
presupuesto de ~400 líneas aplica a correcciones, no a este instrumento nuevo;
mismo criterio que U0/U1/U2/U3/U4.

Datos: los fixtures crean por API y por un `tinker` guardado (`getDatabaseName()
=== 'odontosuite_audit'`) tres citas completadas `AUDIT-U5` con saldo (60/80/90)
y, por la interfaz, dos gavetas propias (administrador y finanzas) y una tercera
de precondición para los diálogos. Se retiran las citas sin transacción; el
libro, los depósitos y la cotización quedan declarados como residuo.

## Tasks

- [x] **T1** · S2, S6, S11 · `inline` · Precondiciones: `odontosuite_audit`
  recreada y sembrada (15 usuarios, 100 pacientes, 3 sedes, 30 sesiones —5
  abiertas—, 393 transacciones, 38 citas completadas sin transacción, 0
  depósitos); app `:8000`, Vite `:5173`, Reverb `:8081` y `queue:work` sobre la
  misma base; revisión anotada `61eb568`. Capa 1: 2 superficies, **50 acciones
  (0 sin clasificar)**, **7 diálogos**, **30 endpoints**, **0 permisos sin
  resolver**; las 5 tabs se declaran `resolved: false` (son un `computed`) y la
  sesión mide los `[role="tab"]` reales.
- [x] **T2** · S1–S9 · `delegated` · Instrumento `@U5` en `tests/e2e/audit/u5/`
  (7 módulos, 6 496 líneas) registrado en `surface.spec.js` (sólo el bloque de
  imports, +10 líneas). Commits `dd890f1`, `978e921` y `6f13a8e`. Delegado a un
  escritor acotado y corregido en tres pasadas propias (ver Log L5–L8).
- [x] **T3** · S2, S3, S5, S11 · `inline` · Corrida definitiva sobre base
  resemebrada: **15/15, 5.2 min, exit 0**; `ui-pass --module=cash-register
  --no-run` → gate OK, 2/2 renderizan, 0 `pageerror`, 0 solicitudes inesperadas.
  La base se sembró hasta que administrador y finanzas no tuvieran gaveta propia
  (precondición declarada de la aritmética); ninguna fila del seed se tocó.
- [x] **T4** · S4, S10, S11, S12 · `inline` · `walkthrough.md`, `ux-rubric.md`
  (14/24 y 19/24), `api-matrix.json`, `render.json`/`render/`, `primitives.json`/
  `primitives/`, `loading.json`, `error-recovery.json`, galería `index.html` y
  esta nota; hallazgos agrupados por clase raíz y publicados #407–#412, más un
  comentario medido en #393.
- [x] **T5** · S5, S11, S12 · `inline` · Cierre: `pnpm format:check` y
  `pnpm lint:check` en verde (0 errores; 36 avisos preexistentes de
  `vue/one-component-per-file`), `pnpm test:js` → **141 archivos y 1 225 tests**,
  `pnpm exec playwright test --list` → **239 tests en 20 archivos**, commits,
  revisión nativa y PR. Resultado de la revisión en su sección.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 368-U5».
- **L2** — Cuerpo de #368 (lo citado en S1–S5 es verbatim de ahí) y plan #13 §4
  (capas 1–6), §5 (rúbrica), §6 (anatomía y evidencia), §7 (U5), §8 (protocolo
  de issues) y §9 (guardarraíles).
- **L3** — Estado al abrir la sesión (`61eb568`, `main` limpio):
  `CashRegisterPage.vue` (676 líneas) monta 5 tabs por permiso (`Cobros`,
  `Transacciones`, `Movimientos`, `Historial`, `Reportes`; `Sesión` no es una
  tab) y 6 modales; `ReadyToBillPage.vue` (509) tiene dos modales.
  `CashRegisterPage` no usa `Skeleton` ni `LoadingSpinner`;
  `ReadyToBillPage` usa `LoadingSpinner` en el detalle pero no para la carga de
  la lista. La sesión mide lo que existe y declara la diferencia.
- **L4** — Entorno: base `odontosuite_audit` recreada y sembrada; app, Vite,
  Reverb y worker arriba. Las cinco gavetas abiertas del seed se reparten al
  azar: la primera corrida definitiva falló la capa 3 porque recepción no tenía
  gaveta; se corrigió el instrumento y se resembró hasta que administrador y
  finanzas no tuvieran gaveta propia.
- **L5** — El instrumento se delegó a un escritor acotado (mecanismo de
  *context backstop* de la sesión): 7 módulos, 6 496 líneas, registro sólo en el
  bloque de imports. Corrió 15/15 a la primera y dejó 8 defectos medidos.
- **L6** — Correcciones que la sesión exigió, todas del instrumento: la capa 3
  abre y cierra la gaveta que cada diálogo necesita (precondición declarada) en
  vez de depender del sorteo del seed; el preflight de la caminata no cierra una
  gaveta que no lleva la marca `AUDIT-U5`; se añadió el paso 17 (reporte de
  período contra la gaveta) y se reubicó el hallazgo posterior al cierre para
  que mi paso nuevo no lo desplazara.
- **L7** — La corrida definitiva se repitió sobre base resemebrada cada vez:
  15/15 en 5.2 min, gate OK, 0 `pageerror`, 0 overflow y 0 solicitudes
  inesperadas. La capa 3 mide **8/8 diálogos** con **0 fallos de contrato**.
- **L8** — Trampa de entorno anotada: `AUDIT_OUT=U5` también redirige los
  agregados de U2/U3/U4 si sus tests se seleccionan por accidente (`--grep
  primitives` seleccionó el de U4 y escribió su `primitives.json` en U5); y
  `ui-pass --no-run` reescribe `render.json`/`primitives.json` con su forma de
  envoltorio (las filas por rol y por diálogo conservan los hechos).
- **L9** — Verificación propia de las causas antes de publicar, por lectura del
  código: `getCurrentSession` sin filtro de usuario para administrador;
  `URLSearchParams` serializando `undefined`; `PaymentModal` sin canal para
  `errors.session` y sin `reference_number` en la puerta de pendientes;
  `createTransaction`/`CashMovementController@store` sin `AccountingLockService::guard`;
  `useCashRegister` con un solo `error`; `PatientController@search` con LIKE por
  columna y el modal leyendo `patient.name`.
- **L10** — Triage: F3+F4 se publican juntos porque son el contrato del mismo
  modal (#409); F6+F7 se publican juntos porque son el estado de la misma
  pantalla y del mismo composable (#411). El 409 de saldo y la falta de acción de
  cobro en listos para facturar no se republican: son #393 (U3). El `h1` oculto y
  el «Subtotal 0.00 / Total 90.00» quedan como observaciones de rúbrica, no como
  issues nuevos.
- **L11** — Residuos: sesiones 31–34 cerradas (31: 100 → 170, +10; 32: 60 → 80,
  0; 33/34: 50 → 50), depósito 1 por 40.00, 6 transacciones y 9 movimientos en
  la 31, cotización 1 `sent` (`PR-2026-JQNEIY`, el `DELETE` solo borra `draft`),
  citas 107/108 conservadas por su pago y 109 en soft delete. La gaveta sembrada
  de recepción no se tocó (en este seed no tenía).
- **L12** — Cierre: `pnpm format:check` y `pnpm lint:check` en verde (0 errores;
  36 avisos preexistentes), `pnpm test:js` → **1 225 tests en 141 archivos**,
  `pnpm exec playwright test --list` → 239 tests en 20 archivos. Revisión nativa
  en su sección.

## Línea base medida

- Revisión del árbol medido: `61eb568` + instrumento; árbol limpio salvo el
  instrumento y esta nota. Corrida: `2026-10-25T22:49Z`.
- **Capa 1** · `ui-manifest --module=cash-register`: 2 superficies, **50 acciones
  (0 sin clasificar)**, **7 diálogos**, **30 endpoints**, **0 permisos sin
  resolver**.
- **Capas 2–4** · `pnpm exec playwright test … --grep @U5` → **15 pasan**, exit
  0, **5.2 min**.
- **Envoltorio** · `ui-pass --module=cash-register --no-run` → **gate OK**; 2/2
  superficies `renders`; **0 `pageerror`**; 31 solicitudes esperadas; 0
  inesperadas. El conteo de diálogos del envoltorio es 0 porque lee la forma
  `primitives/<slug>.json` del arnés y la sesión mide `primitives/*.json` con su
  propio contrato: **8/8 abiertos y 0 fallos**.
- **Render por rol** · caja 568 / 516 / 507 caracteres (administrador, finanzas,
  recepción), 5 / 5 / 4 tabs, 1 `h1` («Caja», oculto) por fila, 0 overflow en
  390 px; listos para facturar 620 caracteres por rol, 0 overflow. **0
  `pageerror`** en las 6 filas y 0 solicitudes inesperadas.
- **Primer pintado** · 900 ms de retardo en `current`: hero «Sin sesión», KPIs
  `N/D`, 0 skeletons y 0 spinners en el hero (la tabla sí trae su spinner);
  asentado «Sesión abierta». Veredicto **DEFECTO** (F7).
- **Error** · `500` forzado en `current`: el panel y el reintento de la caja no
  se pintan (DEFECTO, F6); en listos para facturar se pintan, el reintento
  recupera 200 (OK).
- **Diálogos** · 8/8 medidos, 0 fallos de contrato (centrado/docking, un `h1`,
  foco, scroll, nombre, Escape); precondiciones 33 (recepción, sede 1, 50.00) y
  34 (finanzas, sede 1, 50.00), ambas cerradas con conteo = esperado.
- **Caminata del administrador** · **17 pasos, 11 OK / 6 DEFECTO**, **13 pasos
  con aritmética** y 0 desajustes; apertura 100.00, cierre contado 170.00,
  esperado 160.00, diferencia +10.00; fórmula `100 + 125 − 65 = 160` (`ok`);
  ingresos 195.00, egresos 20.00; 6 transacciones y 9 movimientos en la sesión.
- **Reporte del período** · 20/20 comprobaciones internas (pendiente = efectivo −
  depósitos; familias y conteos; daily/branch/user suman el encabezado) y 12/12
  deltas contra la lectura previa (sesiones +1, apertura +100, cierre +170,
  esperado +160, diferencia +10, ingresos +195, egresos +20, efectivo +95,
  electrónico +80, depósitos +1/+40, pendiente +55). DOM = payload.
- **Caminata de finanzas** · **5 pasos, 4 OK / 1 DEFECTO**; apertura 60.00,
  movimiento +15.00, bloqueo que no bloquea (+5.00, F5), cierre contado 80.00,
  diferencia 0, fórmula `60 + 20 = 80`.
- **Listos para facturar** · OK: filtros de servidor, limpiar, selector de
  paciente, desglose, vacío y error con reintento. Cotización generada `201`
  (`PR-2026-JQNEIY`) y badge «Generada»; el `DELETE` de limpieza responde 422
  (nace `sent`) → residuo.
- **Capa 5** · `api-matrix.json`: **168 filas (24 endpoints × 7 roles), 168
  `ok`, 0 desajustes, 0 saltadas**; **95/95 403 esperados**; expectativa desde
  `GRANTS` + estado de gaveta por rol, observación con token real.
- **Rúbrica** · `/cash-register` **14/24** (tres criterios en 0: acciones,
  estados, honestidad); `/cash-register/ready-to-bill` **19/24**.

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- |
| F1 | caja | Abrir sin gaveta propia; después del cierre | «Sin sesión» / «Sesión cerrada» | La gaveta abierta de otro usuario con sus saldos y las acciones de cierre y depósito | 2/4 | MEDIDO | `crit:alta` | `CashRegisterService.php:160-181` | #407 |
| F2 | caja › Cobros | Cobrar una cita pendiente | Fila con saldo y su cobro | «No hay cobros pendientes» con la API publicando filas; la petición envía `search=undefined&…` | 4 | MEDIDO | `crit:alta` | `useApi.js:86`; `PendingPaymentsList.vue:194-204` | #408 |
| F3 | caja › Registrar cobro | Cobrar sin caja abierta | 422 con mensaje | 422 `{errors:{session}}` sin error general, de campo ni toast | 4 | MEDIDO | `crit:alta` | `PaymentModal.vue:481-525` | #409 |
| F4 | caja › Cobros pendientes | Cobrar con Yape y referencia | La referencia viaja | El formulario no envía `reference_number`; la API acepta el mismo cobro con ella | 4 | MEDIDO | `crit:alta` | `PaymentModal.vue:458-479` | #409 |
| F5 | caja / finanzas | Cobrar y mover dinero bajo bloqueo | 403 con aviso, motivo y reintento | **201** y ningún aviso; la anulación y el depósito sí bloquean | 4 | MEDIDO | `crit:alta` | `TransactionService.php:40-100,213`; `CashMovementController.php:82-124` | #410 |
| F6 | caja | Forzar 500 en la carga | Panel de error + reintento | Inalcanzable: `getSessions()` limpia el mismo `error` | 2/4 | MEDIDO | `crit:media` | `useCashRegister.js:45-130`; `CashRegisterPage.vue:669-676` | #411 |
| F7 | caja | Primer pintado con carga retrasada | Skeleton o spinner | «Sin sesión» falso, KPIs N/D, 0 skeletons/spinners en el hero | 2 | MEDIDO | `crit:media` | `CashRegisterPage.vue` sin primitiva de carga en el hero | #411 |
| F8 | modales de dinero / RTB | Buscar por nombre completo | Encuentra al paciente | 0 resultados; se buscó por documento. `Nueva transacción` pinta `undefined <apellido>` | 4 | MEDIDO | `crit:media` | `PatientController.php:380-411`; `TransactionModal.vue:70,475` | #412 |

Observaciones sin issue: el `h1` de las dos rutas es «Caja» y está oculto (el
título visible es un `h2`); el desglose pinta «Subtotal S/ 0.00» junto a «Total
a cobrar S/ 90.00»; el envío del depósito sigue habilitado por encima del
efectivo (el error es local y bloquea en el submit); el modal de movimientos
permite combinar tipo y concepto contradictorios (12 de 16 pares incoherentes);
la cotización generada nace `sent` y no se puede borrar.

## Revisión nativa (RDD)

- **Candidato completo** (`origin/main` → `bed824f`, 9 archivos y 6 758 líneas):
  el START fue rechazado en preflight con `lens_context_budget_exceeded`; **no se
  creó autoridad**. La continuación que indicó el proveedor fue partir la unidad
  en cortes revisables, que es lo que se hizo.
- **Cortes**: `dd890f1` (2 084 líneas), `978e921` (3 223), `6f13a8e` (1 189) y
  `bed824f` (262, la nota).
- **Corte 1/4** (`dd890f1`, base `origin/main`): START creado en nivel `high`,
  lentes `risk`, `readability` y `reliability`, linaje
  `review-e2661e52d02e4a7b`. El grupo previó 3 ejecuciones de modelo; `risk` y
  `readability` fueron admitidas. `reliability` fue rechazada dos veces por el
  relé del host: primero un payload sin JSON completo (9 objetos abiertos, 8
  cerrados), después un `id` de hallazgo con espacio y un carácter no ASCII.
  Tras el segundo rechazo, el slot que STATUS reofreció llevaba
  `expected-revision b0edb3f…` mientras la autoridad estaba en `880f81d…`, así
  que el reenvío se rechazó como *stale*. **No hubo veredicto de esa lente.**
- **Decisión del usuario**: abandonar el linaje bloqueado
  (`review abandon`, razón `operator_disposition`, commit
  `2026-10-25T23:35:17Z`) y entregar con la revisión parcial declarada. La
  autoridad quedó en cuarentena (`…/quarantine/review-e2661e52d02e4a7b-2688000388`);
  el trabajo descartado incluía los resultados de `risk` y `readability` con
  hallazgos. Los cortes 2/4 a 4/4 no se revisaron.
- **Entrega**: la revisión no aprobó ni autorizó la entrega (no se consumió
  ninguna aprobación); el push y el PR siguen la política ordinaria del
  repositorio y el PR declara la revisión parcial y su causa.

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| Cada tipo de movimiento deja el saldo esperado | **Cumple** en la aritmética (13 pasos, 0 desajustes; fórmula final 160); **no cumple** el bloqueo contable como control (F5/#410) |
| El reporte de período cuadra con la gaveta | **Cumple**: 20/20 comprobaciones internas y 12/12 deltas contra la lectura previa |
| El 422 de referencia se muestra en su campo | **No cumple en la puerta de cobros pendientes** (F4: el campo no viaja) y sí en el cobro ad-hoc |
| El 403 del bloqueo se muestra en línea | **No cumple** para el alta de dinero (F5): nunca hay 403 |
| Una devolución no puede exceder lo cobrado | **Cumple**: 422 en el campo del monto, sin escribir |
| Cero `pageerror` | **Cumple**: 0 en 6 filas de render, 2 caminatas, 8 diálogos y 2 filas de RTB |
| Rúbrica puntuada | **Cumple**: 14/24 y 19/24 (`ux-rubric.md`) |
