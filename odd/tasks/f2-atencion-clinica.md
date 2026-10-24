# F2 — Entidad de atención clínica, cierre único y urgencia sin cita

Issue #282 (épica #294). Fase F2; depende de F1 (#281, cerrada en `0c41e88`).
Entregada en la rama `feat/f2-atencion-clinica` y fusionada en `e59f248`
(PR #299).

## Specs

S1. «Tabla de atención clínica con estado propio (`open → closed | voided`),
profesional, historia clínica, plan e importe.»

S2. «Relación cita 1 → 0..n atenciones.»

S3. «Cierre único: evolucion, hallazgos, procedimientos, materiales, adjuntos,
importe y cotización salen de ahí.»

S4. «La cita conserva `final_amount`, `completed_at` y `checked_in_at` como
proyección de una sola pluma.»

S5. «Atención sin cita para urgencia.»

S6. «Máquina de estados de agenda: `completed` solo por cierre; `/status`
rechaza `completed` y `arrived`.»

S7. «Cita en `fulfilled` exige al menos una atención cerrada.»

S8. «Existe atención de urgencia sin cita, sin fila de agenda inventada.»

S9. «Backfill idempotente de citas completadas históricas; segunda corrida no
duplica.»

S10. «`ready-to-bill` idéntico para filas históricas (regresión).»

Riesgo declarado por la issue: «item 1 (datos, backfill) e item 3 (contrato).»

## Tasks

- T1 — Esquema, modelo y backfill: tabla `clinical_encounters`, columnas
  `clinical_encounter_id` en las cinco tablas clínicas, `procedure_materials.appointment_id`
  nullable, modelo `ClinicalEncounter`, relaciones, escopos y comando
  `encounters:backfill --dry-run`. Specs: S1, S2, S9. Commit: `12d6b91`.
- T2 — Puertas de apertura: `start-attention` abre la atención del acto en la
  misma transacción; `POST /api/encounters` abre urgencia sin cita;
  `POST /api/encounters/{id}/void` cierra la puerta de anulación. Specs: S1,
  S5, S8. Commit: `feffe80`.
- T3 — Cierre único: `ConsultationService` cierra la atención y desde ahí
  escribe evolución, hallazgos, procedimientos, materiales, adjuntos, importe y
  cotización; la cita recibe la proyección. Specs: S3, S4, S6, S7, S10. Commit:
  `bd887e6`.
- T4 — Superficie de lectura y permisos: `GET /api/encounters`,
  `GET /api/encounters/{id}`, catálogo de permisos y matriz de rutas. Specs: S1,
  S5. Commit: `f90e839`.
- T5 — Documentación y guardas: AGENTS.md, CREDENTIALS.md, fixture de permisos,
  `doc-drift`. Commit: `36d5303`.
- Seguimientos: `683a372` fija que el cierre reutiliza el acto que abrió la
  custodia; `d48fd70` cierra el hallazgo de la verificación independiente
  (atribución de la urgencia a un usuario no clínico).

## Contrato decidido

**Entidad.** Nueva tabla `clinical_encounters`: `patient_id` (obligatorio),
`appointment_id` (nullable, `nullOnDelete`), `user_id` (profesional),
`medical_record_id`, `treatment_plan_id`, `mode`, `status`
(`open|closed|voided`), `opened_at`, `closed_at`, `closed_by`, `voided_at`,
`voided_by`, `void_reason`, `final_amount`, `notes`. Índices
`(appointment_id, status)`, `(patient_id, status)` y `(status, opened_at)`.

**Relación con las filas clínicas (aditiva, no sustitutiva).** Las cinco tablas
que produce el acto ganan `clinical_encounter_id` nullable con FK
(`nullOnDelete`): `clinical_evolutions`, `odontogram_records`,
`procedure_materials`, `clinical_attachments`, `quotations`. `appointment_id`
se conserva en las cinco —y pasa a nullable en `procedure_materials`, la única
que lo exigía NOT NULL— porque los consumidores actuales leen por cita
(historias clínicas, reportes, `payment-preview`). El cierre escribe las dos
columnas: la atención es el acto y la cita es su proyección de agenda.

**Apertura.** Dos puertas, ninguna crea estado de agenda:

- `POST /api/appointments/{id}/start-attention` (`start.consultation`) abre la
  atención `open` del acto en la misma transacción que la custodia. Una cita
  puede tener 0..n atenciones: si la anterior quedó `voided`, la siguiente
  apertura crea otra fila.
- `POST /api/encounters` (`create.encounter`) abre la atención de urgencia:
  `appointment_id` nulo, `mode = urgency`, `user_id` = profesional autenticado
  (o el indicado por un administrador, y solo si ese usuario puede iniciar
  consulta). No se inventa fila de agenda (S8).

**Anulación.** `POST /api/encounters/{id}/void` (`void.encounter`) solo desde
`open`: no hay documentos clínicos que perder. Una atención `closed` no se
anula —la historia no se borra (regla del dominio #292)— y la respuesta es 422
nombrando la regla. Anular deja `voided_at`, `voided_by` y `void_reason`.

**Cierre único.** `ConsultationService::close(ClinicalEncounter, payload)` es la
única implementación; `ConsultationService::complete(Appointment, payload)` es
el adaptador que conserva el contrato del wizard (`POST
/api/appointments/{id}/complete`): resuelve o abre la atención de la cita y
delega. `POST /api/encounters/{id}/complete` (`close.encounter`) cierra una
atención de urgencia con el mismo motor. El payload vive en
`CloseClinicalEncounterRequest` y es el mismo para las dos puertas, con las
reglas que antes estaban inline en el controlador. `next_appointment` solo se
acepta al cerrar una cita: una atención sin cita no inventa agenda (la
continuidad es F4/#284).

**Proyección (una sola pluma).** Solo el cierre escribe `status = completed`,
`completed_at`, `final_amount` y `consultation_mode` en la cita, y lo hace
después de cerrar la atención en la misma transacción. Las otras puertas ya no
pueden llegar a `completed`: `/status` lo rechaza con la puerta correcta (F1),
`StoreAppointmentRequest` y `UpdateAppointmentRequest` no lo aceptan en su
whitelist, y `checked_in_at` lo escribe únicamente la llegada (F1). S7 se
sostiene por tres hechos verificables: el único escritor de `completed` es el
cierre, el cierre deja ≥1 atención `closed` en la misma transacción (con guarda
`MissingClosedEncounterException` que lee estado persistido), y el backfill da
una atención `closed` a cada cita histórica.

**Interpretación de `fulfilled`.** El Alcance de la issue dice «`completed` solo
por cierre»; el criterio de aceptación dice «cita en `fulfilled`». Se lee
`fulfilled` como la cita cumplida, es decir el estado `completed` de la agenda:
no se inventa un estado nuevo en el enum.

**Backfill.** Comando `encounters:backfill` (idempotente, con `--dry-run`) sobre
citas `status = completed` sin atención cerrada: crea una atención `closed` con
`opened_at = checked_in_at ?? scheduled_at`,
`closed_at = completed_at ?? ends_at ?? updated_at`,
`final_amount = appointments.final_amount`, `mode = consultation_mode ??
execution`, `medical_record_id` = historia activa del paciente si existe, y
luego enlaza las filas clínicas huérfanas de esa cita
(`clinical_encounter_id IS NULL`) a la atención con un update raw que no mueve
`updated_at`. La segunda corrida no crea filas ni enlaza nada.

**`ready-to-bill`.** Sin cambios: sigue leyendo la proyección de la cita
(`status`, `final_amount`, `completed_at`). La regresión se fija con un test que
captura la respuesta antes y después del backfill y compara el JSON completo.

**Permisos nuevos.** `create.encounter`, `view.encounter`, `close.encounter` y
`void.encounter`, todos para roles clínicos y administrador (`MEDICAL_ROLES`).
La recepción no abre, no cierra, no anula y no lee actos clínicos.

**Fuera de alcance (declarado).** Cobro de la urgencia (el asiento contable de
una atención sin cita pertenece a #287), motivo de consulta y CIE-10 (#286),
consumo de ítems del plan (#288/#284) y cualquier superficie de UI: la issue es
`area:backend`.

## Log

L1. Usuario: "haz la issue 282, en worktree nuevo".

L2. Worktree: `~/proyectos/odontosuite-f2-atencion`, rama
`feat/f2-atencion-clinica`, base `0c41e88` (merge de F1).

### Frontera de slices

`12d6b91` reúne T1 en una unidad de 834 líneas: la tabla, las seis columnas de
enlace, el modelo, el comando de backfill y sus pruebas son un solo cambio de
esquema; partirlo dejaría un commit con columnas sin dueño o un backfill sin
columnas que llenar. `feffe80` entrega T2 (570 líneas): las dos puertas de
apertura, la anulación, los permisos y sus pruebas. `bd887e6` entrega T3, el
slice más delicado (reescribe ConsultationService y BillingService), y por eso
viaja solo con sus tres archivos de pruebas. `f90e839` entrega T4: la superficie
de lectura del acto, solo lectura sobre el modelo ya cerrado. La revisión se
hace por unidad, no por el acumulado.

### Verificación final

- Suite PHP completa en MySQL: OK (2159 tests, 12599 assertions).
- Vitest completo: 904 tests en 108 archivos.
- `vite build`, `eslint --quiet`, `prettier --check .` y `pint --test`: verdes.
- `doc-drift`: 14/14; `sdd:check-events`: 0 huérfanos.
- `procedure_materials.appointment_id` conserva FK y ambos índices tras el
  `->change()` (verificado con `SHOW CREATE TABLE`).
- `gentle_review assess` sobre `origin/main..HEAD`: `nativeRisk=medium`,
  `reviewDue=false` (`under_budget`), sin review nativa iniciada.
- Verificación independiente (gentle-ai-verify): PASS en S1..S10, sin bypass de
  permisos y sin segundo escritor de `completed`. Confirmó en MySQL real que el
  `->change()` conserva la FK y los dos índices de `procedure_materials`, y que
  la regresión de `ready-to-bill` compara el JSON completo, no un subconjunto.
- Hallazgos informativos atendidos: la urgencia ya no se puede atribuir a un
  usuario que no puede iniciar consulta (422
  `encounter_professional_not_clinical`), con test para recepción y finanzas.
- Hallazgo aceptado sin cambio: `encounters:backfill` no envuelve cada cita en
  su propia transacción. Se acepta porque el comando es idempotente por diseño
  (solo crea la atención que falta y solo llena columnas nulas): una corrida
  interrumpida deja un estado parcial que la siguiente completa. Envolverlo
  añadiría código defensivo sin una prueba que lo pueda fijar.

### Entrega

PR #299 fusionado en `main` con merge commit `e59f248`; la rama
`feat/f2-atencion-clinica` quedó eliminada y el worktree retirado.
