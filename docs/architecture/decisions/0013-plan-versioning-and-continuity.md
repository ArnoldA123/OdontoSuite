# ADR-0013 — El plan aceptado es una versión inmutable y la continuidad es una solicitud, no una cita

- **Status:** Accepted
- **Decided:** 2026-10-22 (issues #284 y #288)
- **Body recorded:** 2026-10-22 (misma entrega)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

El plan de tratamiento era una fila mutable. El presupuesto aprobado apuntaba al
mismo `treatment_plan_id`, así que editar el plan después de la firma cambiaba
retroactivamente lo que el paciente había aceptado. `phases` era un JSON que
nadie leía, la prioridad no existía y las alternativas por pieza vivían en la
cabeza del profesional.

La atención tampoco tenía continuidad formal. El cierre del acto creaba la
próxima cita directamente en `appointments`, sin pasar por la validación de
conflictos de la agenda, y el recall vivía en la operación pero no en el
sistema. Una sesión de plan marcaba ítems como `completed` editando su estado,
sin dejar el acto que los había ejecutado.

## Decision

1. **Versiones inmutables.** `treatment_plan_versions` guarda una foto de la
   definición (cabecera e ítems, con alternativas y rechazos). El plan tiene
   `current_version_id` (cabeza editable) y `active_version_id` (versión
   aceptada). Mientras la cabeza es un borrador se refresca; una vez aceptada,
   cualquier cambio abre una versión nueva y la aceptada no se reescribe.
   `quotations.treatment_plan_version_id` se fija al aprobar el presupuesto, de
   modo que el documento firmado queda atado a la foto que se cotizó.

2. **La ejecución es un consumo, no un estado editable.** Cada acto registra
   `treatment_plan_item_consumptions` (acto, ítem, cantidad, autor, fecha). El
   estado del ítem se deriva de la suma: `completed` si cubre `quantity`,
   `in_progress` si cubre parte y `pending` si no hay consumo. La API del plan
   rechaza (422) un estado ejecutado escrito a mano y no permite eliminar una
   línea que ya tiene historia.

3. **La continuidad es una solicitud.** El cierre del acto ya no inventa una
   cita: registra una `continuity_request` con la conducta y la fecha preferida
   (y, si corresponde, un `patient_recall` con intervalo 3/6/12, motivo y
   vencimiento). Recepción materializa la solicitud por
   `POST /continuity-requests/{id}/schedule` o
   `POST /patient-recalls/{id}/schedule`; ambas puertas delegan en
   `AppointmentService`, la única puerta de la agenda, y por tanto validan
   solapamiento de profesional y sillón. Un conflicto deja la solicitud o el
   recall pendiente sin cita.

4. **La máquina de estados se valida en la puerta.** Una transición inválida
   responde 422 en lugar de 500, `approved` exige al menos una línea vigente y
   `completed` exige que todas las líneas no canceladas estén consumidas.

## Consequences

- El presupuesto aprobado y su versión aceptada son inmutables; el plan puede
  seguir evolucionando en un borrador nuevo sin tocar lo firmado.
- El plan publica `executed_quantity`, `pending_quantity`, `session_count` y
  `phase_summary`; la interfaz puede mostrar ejecutado, en curso y pendiente sin
  recalcular nada.
- Un ítem opcional rechazado conserva `rejected_at` y `rejection_reason`, y una
  alternativa rechazada conserva su fecha y su motivo.
- `phases` JSON queda como dato legacy: la fase explícita es `phase_number` +
  `sequence`. No se migra ni se elimina en esta entrega.
- La cola de recepción (`GET /api/continuity/queue`) combina recalls vencidos y
  solicitudes pendientes, acotada por la sede del actor. La superficie web de
  esa cola es un follow-up de interfaz; la issue es `area:backend`.
- No hay migraciones destructivas: todo lo nuevo es aditivo y reversible.

## References

- `odd/tasks/f4-continuidad-284.md` y `odd/tasks/planes-versionados-288.md`.
- `tests/Feature/Api/ContinuityTest.php`,
  `tests/Feature/Api/PlanSessionConsumptionTest.php`,
  `tests/Feature/Api/TreatmentPlanVersioningTest.php`,
  `tests/Feature/Api/TreatmentPlanAlternativesTest.php`,
  `tests/Feature/Api/TreatmentPlanPriorityTest.php` y
  `tests/Feature/Services/RecallServiceTest.php`.
