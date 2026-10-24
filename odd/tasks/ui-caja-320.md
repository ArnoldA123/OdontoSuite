# UI — Caja: devoluciones, depósitos, cargos manuales y estado de cuenta (#320)

Issue #320 (`[UI] Caja: devoluciones, depósitos, cargos manuales y estado de
cuenta`, `area:frontend`, `crit:media`, `type:feature`). Rama
`feat/ui-caja-320`, worktree `<ruta-local>/odontosuite-320`, base
`main` @ `5c44daa`. #287 entregó el contrato del dinero en el backend; esta
entrega es su superficie web.

## Specs

S1. #320: «Modal de devolución con autorizador (administrador), motivo y manejo
del 422.» Criterio: «Una devolución desde la UI no responde 422 por falta de
autorización ni de motivo.»

S2. #320: «Formulario de cargo manual desde la ficha del paciente.» Criterio:
«El cargo manual y el depósito muestran el error de campo del backend y no crean
filas cuando el backend rechaza.»

S3. #320: «Registro de depósito desde la sesión de caja, con tope de efectivo
disponible y referencia bancaria.» Criterio: el mismo de S2.

S4. #320: «Vista de estado de cuenta para caja: período, entradas, saldos y
resumen.» Criterio: «El estado de cuenta muestra los mismos totales que la API
y respeta `view.patient_statement`.»

S5. #320: «Bloque de depósitos y pendiente en Reportes.»

S6. #320: «Los roles clínicos no ven las acciones de caja.»

S7. #320: «Tests de smoke del módulo y unitarios de los helpers nuevos.»

S8. Soporte mínimo que S1/S3/S5 exigen (no es requisito nuevo, es la forma de
cumplirlos sin inventar permisos ni contratos):

- `GET /api/users/active?role=administrador` devuelve los administradores
  activos visibles por sede (`{data: [{id,name,role,specialty}], meta:{total}}`).
  Cualquier otro valor de `role` conserva exactamente la lista clínica anterior.
  Es la fuente del selector de autorizador de S1; no se crea ruta nueva.
- `GET /api/cash-register/deposits?cash_register_session_id=<id>` acota el
  listado al turno. La respuesta no cambia. Es lo que el cierre usa para mostrar
  los depósitos del turno (S3/S5).
- `GET /api/cash-register/reports/period` añade
  `reconciliation: {cash:{count,total}, electronic:{count,total},
  deposits:{count,total}, pending_deposit}` con la misma regla del reporte
  diario (#287): efectivo neto del período menos depósitos del período. Es
  aditivo y es lo que S5 pinta.

Decisiones de superficie:

- D1: el estado de cuenta vive en la ficha del paciente como tab «Cuenta»,
  visible sólo con `view.patient_statement`; recepción trabaja desde la ficha y
  el backend ya acota el libro por paciente. El cargo manual se abre desde ese
  tab y desde el hero de la ficha, con `manage.patient_charge`.
- D2: el depósito se registra desde el hero de Caja con
  `manage.cash_deposit`; el cierre muestra los depósitos del turno. El
  «pendiente» del período se pinta en Reportes, donde la API lo publica.
- D3: `usePermissions` gana `managePatientCharge`, `viewPatientStatement` y
  `manageCashDeposit`, mapeados a los permisos del catálogo #287.

## Contratos

- Devolución (`POST /api/transactions`): `type=refund` exige
  `payment_method_id`, `authorization_reason` (≤255) y un `authorized_by` cuyo
  rol sea `administrador`; los 422 llegan en `errors.authorized_by` /
  `errors.authorization_reason`.
- Cargo manual (`POST /api/patient-charges`): `patient_id`, `amount`,
  `description`, `charged_at` opcional (≤ hoy), `notes` opcional.
- Depósito (`POST /api/cash-register/deposits`): `cash_register_session_id`,
  `amount` > 0 y ≤ efectivo disponible, `reference` obligatoria, `deposited_at`
  opcional (≤ hoy), `notes` opcional.
- Estado de cuenta (`GET /api/patients/{patient}/statement?date_from&date_to`):
  `patient`, `period`, `opening_balance`, `closing_balance`, `summary`
  (`charges`, `discounts`, `payments`, `refunds`, `write_offs`, `adjustments`,
  `collected`, `forgiven`, `balance`) y `entries` (`kind` = `charge|discount|
  payment|refund|write_off|adjustment`, `date`, `reference`, `description`,
  `amount`, `effect`, `balance`).

## Tasks

- T1 — S8, S7 — soporte backend y permisos: lookup de autorizadores, filtro de
  depósitos por turno, `reconciliation` del período, claves de `usePermissions`
  y sus pruebas. Commit: `fa9df79`.
- T2 — S1, S6, S7 — modal de devolución: autorizador, motivo, 422 por campo,
  helpers y smokes. Commit: `9264c56`.
- T3 — S3, S6, S7 — depósito: modal con tope y referencia, acción en Caja,
  depósitos del turno en el cierre y smokes. Commit: `9763f44`.
- T4 — S2, S4, S6, S7 — cuenta del paciente: tab «Cuenta», estado de cuenta,
  modal de cargo manual, helper y smokes. Commit: `f72d040`.
- T5 — S5, S7 — Reportes: bloque de conciliación (efectivo, electrónico,
  depósitos, pendiente) y smoke. Commit: `754cc30`.
- T6 — verificación integral, documentación y cierre. Commit: cierre
  documental (este commit).
- Integración: merge de `origin/main` (`19df281`) para traer #321/#322/#323,
  que tocaban depósitos, estado de cuenta y concurrencia de la gaveta. Sin
  conflictos; la suite se re-ejecutó sobre la rama fusionada.

## Log

L1. «haz la issue 320 en un worktree nuevo»

## Slices de entrega

Medido con `git show --stat` (líneas autoradas, sin lockfiles):

- `754cc30` — 141 líneas — conciliación del período en la tab Reportes.
- `9264c56` — 539 líneas — devolución con autorizador y motivo; el modal y su
  vocabulario, con la suite de 11 pruebas del flujo (incluye
  `caja-devoluciones`, `moneyMeta` y la actualización de `caja-transacciones`).
  Supera la guía de ~400 líneas por el peso de la suite; queda registrado como
  límite de slice.
- `9763f44` — 746 líneas — depósito y cierre; modal, acción de Caja, fila de
  depósitos al cierre y 14 pruebas. Mismo límite declarado: la suite del flujo
  es la mayor parte del slice.
- `f72d040` — 1026 líneas — tab «Cuenta», panel del estado de cuenta, modal del
  cargo manual y 41 pruebas del módulo (incluye la actualización del smoke de la
  ficha). Supera la guía por el peso de las dos suites nuevas.
- `fa9df79` — 453 líneas — soporte backend (autorizadores, filtro por turno,
  conciliación del período) y sus 21 pruebas PHP + 6 JS.
- `19df281` — merge de `origin/main`; integra #321/#322/#323 sin conflictos.

## Verificación (T6)

- `pnpm test:js`: 137 archivos, 1178 pruebas.
- `vendor/bin/phpunit -c phpunit.mysql.xml` sobre MySQL 10.4 con la base
  dedicada `odontosuite_320_test`, en la rama fusionada con `origin/main`:
  `OK (2773 tests, 16948 assertions)`.
- `pnpm build`: verde. `pnpm lint:check`: 0 errores (los avisos son previos del
  repo). `pnpm format:check`: verde tras formatear `usePermissions.test.js`.
- `vendor/bin/pint --test`: 890 archivos. `node scripts/audit/doc-drift.mjs`:
  14/14.
- `git merge origin/main` (`19df281`) sin conflictos: integra #321/#322/#323,
  que tocan depósitos, estado de cuenta y concurrencia. La suite se re-ejecutó
  completa después del merge.
- Nota de entorno: `vendor/` y `node_modules/` del worktree son reales, no
  enlaces al checkout base; un `vendor` enlazado hace que PHPUnit cargue el
  `app/` equivocado y la verificación deje de ser válida.
