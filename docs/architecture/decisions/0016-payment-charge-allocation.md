# ADR-0016 — La imputación de un pago a un cargo es una vista, no una segunda contabilidad

- **Status:** Accepted
- **Decided:** 2026-10-23 (issue #321, specs S1-S4 de
  `odd/tasks/caja-followups-321-323.md`)
- **Body recorded:** 2026-10-23 (misma entrega)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

ADR-0015 dejó el saldo del paciente agregado:
`cargos − descuentos − pagos + devoluciones − condonaciones + ajustes`. El libro
responde cuánto debe el paciente, pero no qué cargo pagó cada pago. Con dos
cargos —una consulta y una restauración— y un cobro, el estado de cuenta solo
mostraba el neto, y la consecuencia quedó declarada como fuera de alcance: «No
hay imputación de un pago a un cargo concreto».

#321 reabre esa pregunta. La respuesta no puede ser una segunda contabilidad: si
la imputación moviera dinero, el mismo cobro contaría dos veces y el saldo del
paciente dejaría de cuadrar con las transacciones. Tampoco puede ser un
`charge_id` en el pago: un cobro cubre varios cargos en la práctica del
mostrador (una consulta y una restauración se pagan en un solo movimiento).

## Decision

**La imputación es una tabla de asignaciones que dice qué cargo pagó un pago, y
el saldo del paciente sigue siendo la única autoridad.**

1. **Tabla `patient_charge_allocations`.** `patient_charge_id`,
   `transaction_id`, `amount` decimal(12,2), `created_by`, `timestamps`, y una
   fila única por par (cargo, pago). Un pago cubre varios cargos con varias
   filas; un cargo recibe varios pagos con varias filas. No lleva `branch_id`:
   el ledger es del paciente y su ficha es compartida (ADR-0010).

2. **Dos reglas de suma, verificadas antes de escribir.** La suma de las
   asignaciones de un pago no supera su importe y la suma de las asignaciones de
   un cargo no supera su importe. `PatientChargeAllocationService` bloquea los
   cargos en orden de `id` y luego el pago, así que dos llamadas concurrentes no
   pueden pasar el tope juntas ni bloquearse entre sí. Un pago repetido en el
   payload se suma en una sola demanda.

3. **Solo un pago completado cubre un cargo.** Un `write_off` o un `adjustment`
   no imputa —son asientos de ledger, no cobros— y un pago anulado deja de
   contar sin borrar la fila, porque la vista y el tope del cargo leen solo
   pagos `completed`: anular un pago libera lo que reservaba.

4. **Dos puertas de escritura.** El alta del pago acepta `allocations` y las
   escribe dentro de su misma transacción (un rechazo no deja ni el pago ni la
   vista), y un pago ya existente recibe las suyas por
   `POST /api/transactions/{transaction}/allocations`. La segunda puerta existe
   porque el cobro suele nacer antes o después de los cargos que paga.

5. **El estado de cuenta publica la vista.**
   `GET /api/patients/{patient}/statement` añade `charge_balances` (por cargo:
   importe, imputado y saldo) y `unallocated_payments` (dinero cobrado que
   ningún cargo reclamó), calculados a la fecha de corte. `summary`,
   `opening_balance` y `closing_balance` no cambian: la imputación no mueve
   dinero.

6. **Una devolución libera cobertura en proporción.** Una devolución no dice a
   qué cargo vuelve, así que reduce la cobertura de su propio pago en la misma
   proporción que el pago perdió (`imputado × (importe − devuelto) / importe`),
   y el residuo de redondeo queda en la última asignación de ese pago para que
   las filas cuadren con el total.

7. **Los datos previos se leen como no imputados.** Sin backfill: un cargo o un
   pago anteriores a esta decisión simplemente no tienen fila, y el saldo del
   paciente no cambia por eso.

## Alternatives considered

- **`charge_id` en el pago (una sola imputación por cobro).** Rechazada: un
  cobro cubre varios cargos; forzar uno obliga a partir el pago en filas que no
  ocurrieron en el mostrador.
- **Deducir la imputación por orden de fechas (FIFO) al leer.** Rechazada: es
  una política inventada que cambia el saldo por cargo sin que nadie la haya
  decidido, y no distingue un pago dirigido de uno genérico.
- **Hacer de la imputación la contabilidad autoritativa.** Rechazada: el mismo
  cobro contaría en `transactions` y en las asignaciones, y el saldo del
  paciente dejaría de ser reconstruible desde el libro.
- **Imputar devoluciones por cargo.** Rechazada por ahora: una devolución no
  nombra el cargo al que vuelve; la reducción proporcional es la regla que no
  inventa una atribución.

## Consequences

- Se puede responder «qué cargo está pagado» y envejecer la deuda por cargo, y
  el total del estado de cuenta sigue siendo el de #287: la imputación es una
  vista.
- Un pago puede quedar parcialmente imputado; el remanente aparece en
  `unallocated_payments` y puede asignarse a otro cargo mientras el pago siga
  `completed`.
- Las filas históricas se leen como no imputadas sin backfill destructivo.
- La corrección de una imputación equivocada no tiene puerta en esta entrega: no
  hay `DELETE` de asignaciones. El saldo del paciente no depende de ello —la
  corrección del cargo sigue siendo un ajuste— y una puerta de reasignación es
  una decisión posterior.
- Una devolución sobre un pago imputado reduce la cobertura de sus cargos en
  proporción; el cargo no vuelve a deuda por el monto devuelto a otro cargo,
  porque la devolución no lo nombra.
- Queda fuera de alcance la UI de imputación: la issue #321 es `area:backend`.

## References

- `app/Services/PatientChargeAllocationService.php`,
  `app/Services/PatientStatementService.php`
- `app/Models/PatientChargeAllocation.php`, `app/Models/PatientCharge.php`,
  `app/Models/Transaction.php`
- `app/Http/Controllers/Api/PatientChargeAllocationController.php`,
  `app/Http/Requests/StorePatientChargeAllocationRequest.php`
- `routes/api.php` (`transactions/{transaction}/allocations`)
- `database/migrations/2026_10_24_110000_create_patient_charge_allocations_table.php`
- `tests/Feature/Api/PatientChargeAllocationTest.php`
- `odd/tasks/caja-followups-321-323.md` (S1-S4)
- ADR-0015 (el saldo del paciente y el vocabulario del dinero)
