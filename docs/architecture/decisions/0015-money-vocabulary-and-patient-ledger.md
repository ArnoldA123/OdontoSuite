# ADR-0015 — El dinero tiene vocabulario cerrado y el paciente tiene ledger propio

- **Status:** Accepted
- **Decided:** 2026-10-23 (issue #287, specs S1-S10 de
  `odd/tasks/caja-dinero-287.md`)
- **Body recorded:** 2026-10-23 (misma entrega)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

`transactions.type` mezclaba cinco conceptos del negocio en una sola palabra.
`payment` era un cobro, pero `discount` era un tipo de transacción aunque el
descuento es una condición del cobro y no plata que se mueva; `income` y
`expense` eran valores del enum que nadie escribía y que el reporte trataba como
si fueran tipos. Un pago con tarjeta y uno en efectivo producían el mismo
`CashMovement`, de modo que `expected_amount` contaba dinero que nunca estuvo en
la gaveta y un depósito podía retirar efectivo inexistente.

La deuda del paciente tampoco existía como libro. El saldo se derivaba de restar
`transactions` contra `appointments.final_amount`, lo que ataba la deuda a la
proyección de una cita: una atención de urgencia sin cita no debía nada, y una
corrección no tenía dónde escribirse. No había estado de cuenta, no había
devolución con flujo (quién la autoriza, cuánto se puede devolver) y la
conciliación del día no distinguía lo cobrado de lo depositado. La condonación,
que la issue pide como concepto propio, no existía: el descuento era del
presupuesto, no del cobro.

## Decision

**El dinero se escribe con un vocabulario cerrado y la deuda del paciente vive
en su propio ledger.**

1. **Vocabulario único.** `App\Support\MoneyVocabulary` es la única fuente de
   los tipos, de su efecto en el saldo y de si mueven efectivo. La API acepta
   `payment|refund|write_off|adjustment`; `discount` deja de ser un tipo de
   transacción (el descuento vive en el cobro, en
   `discount_amount`/`discount_type`) y `income`/`expense` quedan como valores
   legacy de la columna, sin puerta de escritura. La tabla de reglas de la
   feature documenta el contrato:

   | tipo         | método | caja abierta | movimiento | autorización   | saldo                               |
   | ------------ | ------ | ------------ | ---------- | -------------- | ----------------------------------- |
   | `payment`    | sí     | sí           | `income`   | no             | `−amount`                           |
   | `refund`     | sí     | sí           | `expense`  | admin + motivo | `+amount`                           |
   | `write_off`  | no     | no           | no         | admin + motivo | `−amount`                           |
   | `adjustment` | no     | no           | no         | admin + motivo | `+amount` firmado                   |
   | `discount`   | —      | —            | —          | —              | campo del cobro: `−discount_amount` |

   Sólo pago y devolución exigen método y gaveta y escriben `cash_movements`;
   condonación y ajuste son asientos de ledger y no cuentan como ingreso ni como
   cobro. El ajuste es firmado: positivo sube la deuda, negativo la baja.

2. **El cargo es una fila (`patient_charges`).** `patient_id`,
   `clinical_encounter_id` (nullable, único), `source = encounter|manual`,
   `description`, `amount` decimal(12,2), `charged_at`, `created_by`, `notes`.
   El cierre de la atención escribe exactamente un cargo por acto dentro de su
   propia transacción, con `final_amount` como importe y `closed_at` como fecha;
   un acto sin importe no escribe cargo. La puerta manual
   (`POST /api/patient-charges`) registra un cargo que ningún acto produjo y
   nace `source = manual`. El libro es **inmutable**: el modelo rechaza
   `updating` y `deleting`, y la corrección de un cargo equivocado es un ajuste,
   nunca una edición ni un borrado. `charges:backfill` (`--dry-run`,
   idempotente) usa el mismo escritor que el cierre para dar su cargo a las
   atenciones cerradas históricas.

3. **El saldo es una regla, no una resta.**
   `cargos − descuentos − pagos + devoluciones − condonaciones + ajustes`. El
   `PatientStatementService` la aplica al período y a todo lo anterior a él, así
   que el saldo de cierre de un período es el de apertura del siguiente, y sólo
   cuenta transacciones `completed` (una fila anulada nunca ocurrió). El
   descuento resta aparte porque el importe del pago ya es neto. El estado de
   cuenta publica resumen, entradas cronológicas y saldo corriente por
   `GET /api/patients/{patient}/statement`.

4. **La devolución tiene autorización y tope.** Quien ejecuta es caja, pero la
   fila guarda el administrador que autorizó (`authorized_by`, obligatoriamente
   rol `administrador`) y el motivo (`authorization_reason`); un administrador
   que no declara autorizador se autoriza a sí mismo. La devolución está topada
   por el dinero cobrado no devuelto del paciente y, cuando declara
   `parent_transaction_id` (pago completado del mismo paciente), también por lo
   devolvible de ese pago. La condonación está topada por el saldo pendiente:
   sin el tope, el perdón puede inventar una deuda mayor que la real.

5. **El depósito es una salida de gaveta con fila propia.** `cash_deposits`
   (`branch_id`, `cash_register_session_id`, `cash_movement_id`, `created_by`,
   `amount`, `deposited_at`, `reference`, `notes`) se registra contra una sesión
   abierta, por un monto mayor que cero y no mayor que el efectivo disponible de
   la gaveta, con referencia bancaria obligatoria; escribe su `CashMovement`
   `withdrawal` en la misma transacción, para que el esperado de la sesión y el
   arqueo sigan siendo verdaderos. El reporte diario añade
   `reconciliation.deposits` y `pending_deposit = cash.total − deposits.total`.
   Los agregados por método netean devoluciones y excluyen los asientos sin
   efectivo.

6. **Sólo el efectivo mueve la gaveta.** Un cobro o una devolución con tarjeta,
   transferencia o billetera se registra en `transactions` (entra al mix y al
   estado de cuenta) pero no crea `CashMovement`, y anularlo tampoco mueve la
   gaveta. Es la regla que permite que gaveta y depósito cuadren por método.

7. **Dos alcances distintos.** El ledger es del paciente: la ficha y la historia
   son compartidas entre sedes (ADR-0010). La gaveta, sus movimientos, los
   depósitos y la conciliación siguen acotados por sede.

8. **La guarda de caja vive en el servicio, no en la ruta.** `cash.session` deja
   de estar en las rutas de creación y anulación de transacciones: sólo pago y
   devolución exigen caja abierta, y un `write_off`/`adjustment` no puede exigir
   una gaveta que nunca escribe. La anulación sigue exigiendo que la sesión de
   la transacción esté abierta.

## Alternatives considered

- **Mantener `discount` y `write_off` como tipos de `transactions`.** Rechazada:
  el descuento no es plata que se mueva, así que como transacción aparecería en
  el mix de cobros; y la condonación, como tipo de transacción, tendría que
  decidir si exige método y caja, y cualquiera de las dos respuestas miente.
- **Derivar el saldo de `transactions` contra `appointments.final_amount`.**
  Rechazada: la deuda queda atada a la proyección de una cita —una urgencia sin
  cita no debe nada— y no hay dónde escribir una corrección. Un libro con fila
  propia puede existir sin cita, se audita y se reconstruye.
- **Devolución como pago negativo.** Rechazada: se pierde el pago padre, el tope
  de lo devolvible y la autorización; anular uno de los dos dejaría la historia
  ambigua.
- **Depósito como movimiento manual de caja sin fila propia.** Rechazada: el
  movimiento no guarda la referencia bancaria ni permite conciliar el día contra
  lo que llegó al banco; la fila es la evidencia y el movimiento la
  consecuencia.
- **Seguir contando todo cobro en `expected_amount`.** Rechazada: el esperado
  incluiría tarjeta y transferencia, y el depósito retiraría efectivo que nunca
  existió. La regla sólo-efectivo es la que hace cuadrado el arqueo.
- **Acotar el ledger por sede.** Rechazada: contradice ADR-0010. El paciente que
  se traslada sigue debiendo lo mismo en otra sede; gaveta y depósito sí son de
  la sede que los opera.

## Consequences

- La deuda del paciente es un libro reconstruible: cada cargo tiene fila, fecha
  y autor, y el estado de cuenta cuadra contra las transacciones del período.
- Un cargo histórico se recupera con `charges:backfill` sin reescribir nada más;
  una segunda corrida no duplica.
- El esperado de la sesión y el depósito sólo cuentan efectivo, así que
  `pending_deposit` puede ser negativo cuando el depósito incluye efectivo de
  días anteriores: es información, no un error.
- Los movimientos de gaveta electrónicos escritos **antes** de esta decisión se
  reparan con `cash:repair-electronic-movements` (#322, ADR posterior): el
  comando marca (soft delete + `audit_logs`) los movimientos ligados a un método
  que no es efectivo, con `--dry-run` e idempotencia, y **no** reescribe las
  columnas de la sesión (`expected_amount`, `closing_amount`,
  `difference_amount`, `arqueo_data`), que quedan como el registro histórico de
  lo que se declaró al cerrar. Un movimiento cuyo método no se puede comprobar
  se conserva y se cuenta aparte.
- Las filas históricas de tipo `discount` se leen como línea de descuento del
  estado de cuenta (no se migran ni se reescriben), así que un paciente con
  descuentos del modelo anterior no ve su deuda inflada.
- La ventana entre un depósito y una devolución de la misma sesión queda cerrada
  (#323): el depósito, el pago, la devolución y el cierre toman el mismo lock de
  la fila de `cash_register_sessions` antes de leer o escribir la gaveta, así
  que no pueden calcular el efectivo disponible a la vez.
- Un depósito con `deposited_at` anterior es un hecho de ese día bancario: el
  bloqueo contable del módulo `cash_movements` se evalúa sobre `deposited_at`
  (#323), no sobre la fecha en que se escribe el movimiento, y la operación
  exige `lock_override_reason` de administrador cuando cae en un período
  cerrado. El movimiento sigue naciendo con la fecha de hoy; lo que cambia es
  qué fecha decide el bloqueo.
- La imputación de un pago a un cargo concreto existe desde #321, pero como
  vista: `patient_charge_allocations` dice qué cargo pagó un pago y el saldo
  sigue siendo la regla de esta decisión. Ver ADR-0016.
- Permisos nuevos, los tres con `CASH_ROLES` (administrador, finanzas,
  recepcionista): `manage.patient_charge`, `view.patient_statement` y
  `manage.cash_deposit`; el listado de depósitos conserva `view.cash_register`.
- Queda declarada fuera de alcance la UI de caja de devoluciones, depósitos y
  estado de cuenta: la issue #287 es `area:backend`.

## References

- `app/Support/MoneyVocabulary.php`
- `app/Services/TransactionService.php`,
  `app/Services/PatientChargeService.php`,
  `app/Services/PatientStatementService.php`,
  `app/Services/CashDepositService.php`
- `app/Models/PatientCharge.php`, `app/Models/CashDeposit.php`
- `app/Services/Reports/CashReportService.php`,
  `app/Services/Reports/Concerns/ResolvesMoneyTotals.php`
- `app/Console/Commands/BackfillPatientCharges.php`
- `routes/api.php` (`patients/{patient}/statement`, `patient-charges`,
  `cash-register/deposits`, `transactions`)
- `tests/Unit/Support/MoneyVocabularyTest.php`,
  `tests/Feature/Api/MoneyVocabularyTest.php`,
  `tests/Feature/Api/PatientChargeLedgerTest.php`,
  `tests/Feature/Api/BackfillPatientChargesCommandTest.php`,
  `tests/Feature/Api/PatientStatementTest.php`,
  `tests/Feature/Api/CashDepositTest.php`,
  `tests/Feature/Api/CashReportNettingTest.php`
- `odd/tasks/caja-dinero-287.md` (decisiones D1-D8 y tabla de tipos)
