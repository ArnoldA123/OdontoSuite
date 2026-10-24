# Caja Interface Redesign — CERRADO ✅

Rama: `feat/cash-register-interface-redesign` (desde `main` @ b3c168a)
Worktree: `.worktrees/cash-register-ui`
Creado: 2026-10-20 · Orquestador: el Gentleman

## Specs

- **S1.** "continuando con este proyecto conforme dejamos el dashboar quiero
  hacer ahora la interfaz de Caja" — rediseñar la superficie del módulo Caja
  (`/cash-register`) con el mismo lenguaje visual con el que quedó el dashboard
  y los módulos ya rediseñados: hero band (eyebrow + título + caption +
  acciones), KPI strip, superficie canvas con hairline y elevación, heroicons y
  primitivas de `components/ui/`.
- **S2.** "para hacer ello en un worktree nuevo" — el trabajo se aísla en un
  worktree y en la rama `feat/cash-register-interface-redesign` partiendo de
  `main` @ b3c168a.
- **S3.** Alcance elegido por el usuario: **"Caja completa + ready-to-bill"** —
  las cinco tabs de `/cash-register` (Cobros, Transacciones, Movimientos,
  Historial, Reportes) con sus seis modales, más
  `/cash-register/ready-to-bill` dentro de `AppLayout`.
- **S4.** Alcance elegido por el usuario: **"UI + arreglo del arqueo"** — la
  aritmética del arqueo y de los totales de caja se corrige en esta feature, con
  tests que fijen cada regla.
- **S5.** Convenciones heredadas de `AGENTS.md` §7: "UI: consumir SIEMPRE de
  `components/ui/`. NO botones/modales ad-hoc."; "Código limpio y profesional.
  **NO comentarios pedagógicos** salvo pedido explícito."; pnpm siempre; commits
  semánticos en inglés, sin emojis; código e identificadores en inglés, copy de
  UI en español.
- **S6.** La superficie no inventa datos: cada KPI, botón y estado debe poder
  derivarse del payload real de la API. Un control visible sin endpoint que lo
  sostenga es un defecto, no una feature (lección de métodos de pago D5/D11).

## Auditoría base (2026-10-20)

### Contrato backend (evidencia path:line)

Rutas en `routes/api.php:283-343`. Dos grupos de rol: **A**
`role:administrador,finanzas,recepcionista` (sesiones + transacciones) y **B**
`role:administrador,finanzas` (movimientos, reportes, cobros pendientes).
`/payment-methods/active` está fuera de todo `role` (público, catálogo sin
credenciales).

| Endpoint | Gate | Pantalla |
|---|---|---|
| `GET cash-register/current` | A | shell, KPIs, todas las tabs |
| `GET cash-register/sessions` (+filtros, `paginate(15)`) | A | Historial |
| `POST cash-register/open` · `close` | A | modales de sesión |
| `GET cash-register/sessions/{id}` · `/{id}/movements` | A | detalle, Movimientos |
| `GET cash-register/summary` | A | sin consumidor |
| `GET cash-register-sessions/{id}/closure-report` | A | PDF de cierre |
| `GET/POST transactions`, `POST {id}/void`, `POST {id}/receipt` | A + `cash.session` | Transacciones, Cobro |
| CRUD `cash-movements` | B + `cash.session` + policy | Movimientos |
| `GET cash-register/pending-payments`, `POST {id}/pay` | B | Cobros |
| `GET cash-register/reports/period`, `POST reports/export/{fmt}` | B | Reportes |
| `GET cash-reports/daily` | B | sin consumidor |

Gaps verificados:

- **G1** `CashReports.vue:61,78,97,116` lee `sessions_count,total_income,total_expenses,total_difference`;
  el reporte de período no devuelve `total_income/total_expenses`
  (`CashReportService.php:73-85`). `v-if="summary.sessions"` (`:186`) nunca es
  cierto y `summary.by_payment_method`/`by_hour` no existen en ese payload
  (`:132,160`) → tabla y gráficos permanentemente vacíos. Además
  `CashRegisterPage.vue:547` asigna el reporte a `summary`, y "Generar Reporte"
  pone los KPIs de la sesión en cero.
- **G2** `by_payment_method`/`by_hour` son mapas `{id: {count,total}}`
  (`CashRegisterService.php:238-245`) sin etiqueta; el reporte usa arrays con
  nombre (`CashReportService.php:150-186`). Dos formas incompatibles para el
  mismo concepto.
- **G3** `total_income/total_expenses` suman solo transacciones
  (`CashRegisterService.php:206-233`) mientras `expected_amount` suma solo
  movimientos (`:313-335`), pese al comentario "transactions already create
  movements". Los egresos manuales no aparecen en Egresos pero sí bajan el saldo
  esperado; los depósitos manuales no suben ni uno ni otro; el dashboard calcula
  su propio saldo como `apertura + ingresos − egresos`
  (`useCashRegister.js:176-196`), una tercera cifra.
- **G4** `total_movements` es `sum('amount')` de **todos** los movimientos
  (`CashRegisterService.php:240`), incluido el depósito de apertura y el ajuste
  de cierre: no es un conteo ni un saldo.
- **G5** `MovementModal.vue:207-219` no envía `concept`, obligatorio en
  `StoreCashMovementRequest.php:76-80` → "Nuevo Movimiento" siempre 422.
- **G6** `canEdit/canDelete` de `MovementList.vue:459-467` leen `can.value` de un
  objeto plano → siempre `false`, y la página no escucha `@edit/@delete`
  (`CashRegisterPage.vue:174`): acciones muertas pese a que la API soporta
  PUT/DELETE.
- **G7** `voidTransaction` es admin-only en UI (`usePermissions.js:98`) pero la
  ruta admite los tres roles y el servicio no valida rol
  (`TransactionService.php:100-140`). Se conserva la UI restrictiva: no se
  amplía autoridad por decisión propia.
- **G8** El Historial emite `refresh(filters)`/`refresh({page})`
  (`SessionList.vue:255-263`) pero `loadSessions()` no recibe argumentos
  (`CashRegisterPage.vue:402-406`) y `getSessions` descarta `meta`
  (`useCashRegister.js:157-168`): paginación y filtros inertes, dropdown de
  usuarios nunca poblado (`SessionList.vue:239`).
- **G9** "Reabrir sesión" (`SessionList.vue:290-318`) no tiene endpoint: el
  cierre es de una sola vía.
- **G10** El rol que cobra (`recepcionista`) recibe 403 en `pending-payments` y
  `…/pay` (grupo B), y la tab "Cobros" es la default
  (`CashRegisterPage.vue:330`): la lista vacía se traga el 403
  (`PendingPaymentsList.vue:307-313`). El ítem de la fila trae solo
  `appointment_type.price` (`PendingPaymentsController.php:77-78`), sin
  `balance`, y `treatment_plan` se renderiza sin venir en el payload
  (`PendingPaymentsList.vue:165`).
- **G11** `PaymentModal` postea `/api/transactions` directo
  (`PaymentModal.vue:319-334`), no `pending-payments/{id}/pay`, así que
  `paid_amount/balance` de la cita nunca se actualizan
  (`PendingPaymentsController.php:206-214`).
- **G12** `OpenCashModal/CloseCashModal/TransactionModal/MovementModal` declaran
  solo `show` y bindean `<Modal :model-value="show">`
  (`OpenCashModal.vue:3,145-152`, `CloseCashModal.vue:293-300`) pero la página
  usa `v-model` (`CashRegisterPage.vue:198-215`): no abren.
- **G13** `show()` y `movements()` no validan propiedad de la sesión
  (`CashRegisterController.php:153,176`) mientras `CashMovementController@show`
  sí (`:120-127`).
- **G14** `percentage` divide por `sum(closing_amount)`, que es `NULL` en
  sesiones abiertas (`CashReportService.php:165`); el reporte mezcla
  poblaciones (sesiones por `opened_at`, métodos por `transactions.created_at`,
  `:66-85,300-330`).
- **G15** `transactions` sin filtro se limita silenciosamente a la sesión abierta
  (`TransactionController.php:36-57`), `paginate(15)` fijo
  (`TransactionService.php:190`); `TransactionList`/`TransactionModal` piden
  `GET /payment-methods`, admin-only (`api.php:324`) → 403 para
  recepcionista/finanzas (`TransactionList.vue:308-316`), aunque
  `/payment-methods/active` es público.
- **G16** `POST cash-register-sessions/{id}/open` ignora `{id}`
  (`routes/api.php:304`), `currentView()` (`CashRegisterController.php:104`) es
  código muerto sin ruta, `summary()` toma la última sesión del día sin filtrar
  por usuario (`:212-241`) y el 500 de `pending-payments` filtra `error` y
  `trace` (`PendingPaymentsController.php:118-125`).

### Frontend (evidencia path:line)

- **F1** Shell: `PageHeader` duplica el título del topbar
  (`CashRegisterPage.vue:5-9` vs `AppLayout.vue:255-256`); KPI solo con sesión
  abierta (`:40`); cinco cabeceras de sección repetidas a mano (`:114-223`);
  iconos de tabs duplicados (`:277-283`).
- **F2** Permisos mal destructurados: `createTransaction`/`createMovement` del
  composable no existen (`CashRegisterPage.vue:309-310`) → "Nueva Transacción" y
  "Nuevo Movimiento" nunca renderizan; `canVoid` (`TransactionList.vue:302`) y
  `manageCashRegister` (`SessionList.vue:333`) tampoco existen y el segundo hace
  `.value` sobre `undefined` al renderizar filas.
- **F3** Estado raíz silencioso: `error` del composable nunca se pinta
  (`CashRegisterPage.vue:292-293`); un fallo de `/cash-register/current` deja la
  página en "Sin Sesión" con "Abrir Caja" habilitado (`:358`) y el intento
  devuelve 422.
- **F4** Tablas a mano en las cuatro listas (`PendingPaymentsList.vue:60-160`,
  `TransactionList.vue:73-120`, `MovementList.vue:121-175`,
  `SessionList.vue:73-120`) más una tercera tabla de sesiones en
  `CashReports.vue:186-260`, con paginación propia (`PendingPaymentsList.vue:218-231`)
  y filtrado client-side duplicado (`:176-215`).
- **F5** Filtros decorativos: `TransactionList`/`MovementList`/`SessionList`
  emiten `refresh(filters)` y la página los descarta
  (`CashRegisterPage.vue:387-410`); `getSessions` devuelve un array y la página
  lee `response.data` (`:402-406` vs `useCashRegister.js:138-141`) → Historial
  siempre vacío.
- **F6** Modales rotos: `v-model` en la página (`:198-215`) contra `show` en
  `OpenCashModal/CloseCashModal/TransactionModal/MovementModal`
  (`OpenCashModal.vue:145-152`, `CloseCashModal.vue:293-300`).
- **F7** Sin primitivas de formulario: `<select>` nativos en
  `PaymentModal.vue:40-50,65-75` y `TransactionModal.vue:91-140,170-178`;
  tres `<input type="date">` nativos en `CashReports.vue:14-52` y
  `ReadyToBillPage.vue:90-116`.
- **F8** Confirmaciones sin primitiva: `window.confirm` por falta de import de
  `useConfirm` (`TransactionList.vue:343-346`, `SessionList.vue:364-367`,
  `MovementList.vue:415-418`) mientras la página usa `useConfirm` (`:313`).
- **F9** Acciones muertas: editar transacción no-op (`CashRegisterPage.vue:533-535`),
  imprimir/descargar solo toast (`:583-589`), `exportReport` vacío (`:556-558`),
  exports stub en las listas (`TransactionList.vue:359-378`,
  `MovementList.vue:425-444`, `SessionList.vue:403-422`) y "Reabrir sesión" sin
  endpoint (`SessionList.vue:290-318`).
- **F10** Reportes: `CashReports.vue` recibe el `summary` de sesión y no el de
  período; exporta con `fetch` + `localStorage` (`:355-434`) violando `useApi`;
  `window.print()` imprime el shell completo (`:438`).
- **F11** `ReadyToBillPage.vue` no usa `AppLayout` (`:75-77`), header propio
  (`:79-88`), tabla manual de 9 columnas (`:124-190`), paginación a mano
  (`:192-210`), `watch` sin debounce (`:74`), error solo toast (`:119-123`),
  modal sin rama de error (`:213-246`) y "Generar cotización" sin estado
  pendiente (`:176-182`).
- **F12** Composable: `getSessions` devuelve array o envelope según el caso
  (`useCashRegister.js:138-141`), `loadCurrentSession` usa
  `triggerRef`/`nextTick` ritual (`:71-79`), y la página vuelve a suscribir
  WebSockets (`CashRegisterPage.vue:596-600`) sobre el `watch` del composable
  (`useCashRegister.js:315-325`).

## Decisiones de diseño (arquitecto)

| # | Decisión | Motivo |
|---|---|---|
| D1 | Hero band con eyebrow/título/caption/estado de sesión + acciones Abrir/Cerrar Caja; se retira `PageHeader` | Último lenguaje aprobado (`AppointmentTypesPage.vue:7-40`); elimina el título duplicado |
| D2 | KPI strip permanente de 4 tarjetas (label + número + caption), "N/D" mientras no hay dato; todas leen el mismo `summary` | Anatomía del dashboard sin inventar ceros |
| D3 | El **ledger de movimientos** es la única fuente de flujos de efectivo: `cash_in` (income + deposit), `cash_out` (expense + withdrawal), `expected_amount = apertura + cash_in − cash_out`. Las filas espejo de apertura (`OPEN-…`) y cierre (`CLOSE-…`) se excluyen por referencia | G3/G4; cada movimiento físico entra una sola vez y el arqueo deja de ignorar depósitos manuales |
| D4 | `total_income`/`total_expenses` conservan el significado de **ventas** (transacciones completadas); el saldo del dashboard pasa a leer `expected_amount` | Una cifra de ventas y una de caja; el saldo mostrado es el que se arqueará |
| D5 | `total_movements` se retira del payload y se reemplaza por `cash_in`, `cash_out`, `net_flow`; `by_payment_method`/`by_hour` pasan a arrays con etiqueta (`payment_method`, `hour`) | G2/G4; una sola forma para sesión y reporte |
| D6 | `type: adjustment` queda como fila memo fuera de la ecuación de efectivo (hoy solo la escribe el cierre) | Sin signo definido; se documenta en el docblock |
| D7 | Reporte de período suma `total_income`/`total_expenses` y calcula `percentage` sobre el total del mix, no sobre `closing_amount` | G1/G14 |
| D8 | Listas sobre `UiDataTable` con `meta` real (página y total del API) y `UiPagination` solo cuando el API no pagina la vista | G8/F4/F5; `components/ui/` es la primitiva del repo |
| D9 | Se eliminan los controles sin contrato: "Reabrir sesión", editar/eliminar movimiento, editar transacción, exports stub, imprimir/descargar comprobante que solo hacían toast | S6 |
| D10 | Los cuatro modales pasan a `v-model` (`modelValue` + `update:modelValue`) y renderizan `errors.general` | G12/F6; hoy no abren y los 422 son invisibles |
| D11 | `MovementModal` envía `concept` del whitelist y `branch_id` de la sesión | G5 |
| D12 | Cobros: tab gateada por permiso con estado explicativo; el 403 nunca se disfraza de lista vacía. `PendingPaymentsList` usa `POST pending-payments/{id}/pay` cuando hay cita pendiente, y `/transactions` para el cobro ad-hoc | G10/G11; no se amplía RBAC (issue #55) sin autorización explícita |
| D13 | Transacciones y `TransactionModal` leen `/payment-methods/active` (público) en vez de `/payment-methods` (admin) | G15; recepcionista no puede cobrar con el catálogo vacío |
| D14 | Reportes: `CashReports` recibe su propio payload de período, se retira la escritura sobre `summary`, y los exports pasan por `useApi` | G1/F10 |
| D15 | ReadyToBill dentro de `AppLayout`, hero propio, `UiDataTable` + `FilterBar`, error con retry y modal con rama de error | F11 |
| D16 | Guards anclados a contrato (`data-*`, bindings, reglas), no a literales de clase | Lección dashboard-visual-coherence / ambientes |

## Alcance / No-alcance

**Alcance**: `app/Services/CashRegisterService.php` (ledger y summary),
`app/Services/Reports/CashReportService.php` (totales y porcentajes),
`resources/js/modules/cash-register/*` (página, 5 listas, 6 modales,
`ReadyToBillPage`), `resources/js/composables/useCashRegister.js`, guards de
diseño y tests del módulo.

**No-alcance**: ampliar RBAC de cobros pendientes (G10) o de anulación (G7),
`/quotations`, migraciones, y la semántica de `show()`/`movements()` sin
validación de propiedad (G13), que se reporta como follow-up.

## Tasks

| # | Task | Spec | Commit |
|---|---|---|---|
| T1 | Ledger de caja como fuente única: `cash_in`/`cash_out`/`expected_amount`/`net_flow`, mirrors excluidos, shapes con etiqueta; tests MySQL RED→GREEN | S4, D3-D6 | ✅ acad2c5 |
| T2 | Reporte de período: `total_income`/`total_expenses` y `percentage` sobre el mix; tests | S4, D7 | ✅ df42a9e |
| T3 | Contrato frontend: `useCashRegister` (meta, filtros, error, sin doble subscribe, saldo desde `expected_amount`) | S1, D8, F12 | ✅ eeb9b2a |
| T4 | Shell: hero + KPI strip + tabs + estados de página | S1, D1, D2 | ✅ 61ca78d |
| T5 | Cobros: `PendingPaymentsList` en `UiDataTable`, gate por permiso, pay endpoint | S1, D12 | ✅ 3d81e20 |
| T6 | Transacciones: lista, filtros reales, anular vía `useConfirm`, métodos activos | S1, D8, D9, D13 | ✅ 3a10e87 |
| T7 | Movimientos: lista, `concept`, totales desde `summary`, sin acciones muertas | S1, D9, D11 | ✅ ff920fc |
| T8 | Historial: lista, filtros y paginación del API, sin reabrir | S1, D8, D9 | ✅ 9aca68d |
| T9 | Reportes: payload de período propio, gráficos, export por `useApi` | S1, D14 | ✅ ebe1507 |
| T10 | `ReadyToBillPage` dentro de `AppLayout` y sobre primitivas | S3, D15 | ✅ d79fddc |
| T11 | Guards de contrato + `AGENTS.md` §12 + verificación completa | S1-S6 | ✅ 4b3022d |
| T12 | Modales de sesión (`OpenCashModal`, `CloseCashModal`): v-model, arqueo único contra `expected_amount`, PDF por `useApi` | S1, D10, F6, F7 | ✅ 43977fe |

## Estrategia de entrega (work units)

Un commit por task, tests junto al comportamiento. Si una unidad toca dinero
(T1/T2), commit propio y revisión nativa independiente.

Fronteras de slice (baseRef para la revisión nativa de cada unidad):
- T1 `acad2c5` ledger (dinero) — base `main`.
- T3 `eeb9b2a` contrato del composable — base `acad2c5`.
- T4 `61ca78d` shell — base `eeb9b2a`.
- T2 `df42a9e` reporte y arqueo (dinero) — base `61ca78d`.
- T13 `5c096b0` integridad del ledger (dinero, hallazgos del verificador) — base `df42a9e`.
- T5 `3d81e20` Cobros — base `5c096b0`.
- T7 `ff920fc` Movimientos — base `3d81e20`.
- T6 `3a10e87` Transacciones — base `ff920fc`.
- T8 `9aca68d` Historial — base `3a10e87`.
- T10 `d79fddc` ReadyToBill — base `9aca68d`.
- T9 `ebe1507` Reportes + wiring de la página — base `d79fddc`.
- T12 `43977fe` modales de sesión — base `ebe1507`.
- T11 `4b3022d` guards + AGENTS.md (sin código de producto) — base `43977fe`.

Los tres commits de dinero (T1, T2, T13) se revisan solos: cada uno es una
regla contable distinta.

Incidente de entorno resuelto: el worktree tenía `vendor` como symlink al repo
principal, así que Composer resolvía `App\` al árbol de `main`. Se reemplazó
por una copia real; la evidencia RED/GREEN de T1 y T2 se rehízo con el
autoloader del worktree.

Push/PR/merge: PR #179 mergeado a `main` en `5351e44` (2026-10-20); worktree local eliminado y rama borrada.

## Verificación

Línea base 2026-10-20 en el worktree: JS 545/545, `tests/Unit/DesignSystem`
695/695, tests de Caja con MySQL (`CashRegisterEndpointsTest`,
`RecepcionistaCajaAccessTest`, `CashMovementPermissionTest`,
`CashDeleteSessionGateTest`, `CashRegisterValidationTest`) 27/27 contra
`odontosuite_caja_test`.

- T1/T2: test MySQL nuevo en RED, luego GREEN, más regresión `CashRegister|Cash`.
- T3-T10: `pnpm test:js` + smoke por tab + `pnpm lint:check` + `pnpm build`.
- T11: suites completas JS y PHP (MySQL), `scripts/audit/doc-drift.mjs`,
  Playwright a 3 viewports.
- Cierre: verificación independiente (riesgo de dinero) y ASSESS nativo.

## Log

- **L1** (2026-10-20, texto original): "continuando con este proyecto conforme
  dejamos el dashboar quiero hacer ahora la interfaz de Caja, para hacer ello en
  un worktree nuevo"
- **L2** (2026-10-20, decisión de alcance): superficie = "Caja completa +
  ready-to-bill".
- **L3** (2026-10-20, decisión de aritmética): "UI + arreglo del arqueo".


## Cierre (2026-10-20)

- PR #179 mergeado a `main` en `5351e44`; CI en verde (Quality gates, Backend MySQL, Frontend build) en los dos runs del PR.
- Verificación local post-merge: PHP 1541/1541, JS 653/653, DesignSystem 748/748, lint 0 errores, format limpio, build ✓, doc-drift 12/12.
- Pasada visual Playwright a 1440×900, 834×1112 y 390×844 sobre la base demo, sin overflow y con cobros/arqueo coherentes (128 + 374 − 97 = 405).
- Follow-ups abiertos como issues #191–#199: RBAC de cobros pendientes, anulación UI/API, propiedad de sesión, contract warts de sesión, fuga del 500, `adjustment` inerte, poblaciones y N+1 del reporte, paginación de cobros pendientes y alcance del cobro ad-hoc.
