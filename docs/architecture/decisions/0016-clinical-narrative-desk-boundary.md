# ADR-0016 — La narrativa clínica no cruza al escritorio no clínico

- **Status:** Accepted
- **Decided:** 2026-10-23 (issues #313, #314 y #315, decisiones D1-D3 de
  `odd/tasks/f5-clinical-narrative-boundary.md`)
- **Body recorded:** 2026-10-23 (misma entrega)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

#285 cerró la historia clínica al escritorio de laboratorio: el técnico dejó de
leer la historia, sus evoluciones, sus adjuntos, el acto que los publica y de
exportar la ficha del paciente. Dejó abiertas, por decisión declarada, tres
superficies que seguían entregando la misma narrativa por otra puerta:

1. **Registros de especialidad** (#313). Las cinco tablas conservan texto
   clínico —`surgical_notes`, `post_surgical_notes`, `complications`,
   `follow_up_notes` y sus equivalentes— y el técnico tiene
   `manage.specialty_record`, que es el mismo permiso para leer y escribir.
   `GET /api/specialty-records` y sus hijas se lo entregaban completo, y el
   canal `specialty-records` publicaba el modelo serializado en un canal que el
   técnico podía suscribir.
2. **`appointments.treatment_notes`** (#314). El cierre de la atención escribía
   en la cita el `plan` de la evolución, copia literal de la conducta clínica.
   `AppointmentResource` publicaba el campo a todo el que lee citas —recepción y
   laboratorio incluidos— y los eventos de cita lo difundían por
   `dashboard-updates`, que autoriza a los siete roles.
3. **`MedicalRecordCreated`** (#315). El evento publicaba el modelo
   `MedicalRecord` completo con paciente y autor por `dashboard-updates`, además
   de `medical-records`.

El resultado era una frontera que existía en el módulo de historia y en ningún
otro lado: quien no podía abrir la historia recibía su contenido por la agenda,
el dashboard o el socket.

## Decision

**El contenido clínico viaja solo por las superficies de los escritorios que lo
leen.** La regla se aplica por superficie, no por módulo.

1. **El registro de especialidad es un acto clínico.** Su narrativa quirúrgica
   no es insumo del trabajo protésico: el caso (`lab_cases`) publica pieza,
   trabajo, material, fechas y técnico asignado, que es lo que el taller
   necesita. `manage.specialty_record` queda en administración y escritorio
   clínico (`administrador`, `odontologo`, `implantologo`); lectura y escritura
   son el mismo permiso, así que el técnico sale de ambas. La API responde 403
   en todo el módulo, la SPA no ofrece la entrada ni el tab, y el canal
   `specialty-records` deja de autorizarlo.
2. **La cita es la proyección operativa del acto.** Lleva agenda, custodia,
   importe, modo y referencia al plan; no lleva la conducta clínica. El cierre
   deja de copiar `evolution.plan` en `appointments.treatment_notes`: la
   conducta vive en la evolución y en la solicitud de continuidad, y la cita
   conserva `notes`, que es su anotación de agenda. La columna histórica no se
   reescribe (el ledger no se altera), pero `AppointmentResource` la publica
   solo a quien lee la historia clínica (`manage.medical_record`), y ningún
   evento de cita la difunde.
3. **Un canal de difusión no varía su payload por rol.** Un evento cuyo payload
   es un documento clínico publica solo en el canal de los escritorios que lo
   leen: `MedicalRecordCreated` en `medical-records`,
   `SpecialtyRecordCreated`/`SpecialtyRecordUpdated` en `specialty-records`.
   Ninguno publica en `dashboard-updates`, que autoriza a los siete roles. El
   panel refresca sus contadores con los eventos de agenda, pacientes y dinero,
   que no llevan narrativa clínica.

## Consequences

- Recepción y laboratorio conservan las superficies operativas que necesitan:
  agenda, custodia, cobro, casos de laboratorio y contadores del panel. Pierden
  las dos que publicaban texto clínico de otra persona.
- El técnico conserva el flujo clínico que fijaron F1/F2 (iniciar y cerrar la
  atención, abrir o anular un acto), sus planes de tratamiento y el catálogo de
  piezas; pierde el módulo de registros de especialidad completo.
- Los registros históricos de `appointments.treatment_notes` no se borran ni se
  reescriben: la política aditiva no reescribe datos, y la migración no es el
  lugar donde se decide la visibilidad. La lectura se acota en la proyección, y
  sólo el escritorio clínico la recibe.
- Los dos endpoints que reservan una cita desde una solicitud de continuidad o
  un recall devuelven el modelo crudo de la cita recién creada. Una cita nueva
  no tiene narrativa (el cierre ya no la escribe), así que no hay valor que
  filtrar; si en el futuro esa proyección creciera, debe pasar por
  `AppointmentResource`.
- El canal `dashboard-updates` sigue autorizando a los siete roles y sigue
  transportando citas, pacientes, dinero y catálogo. Deja de transportar
  documentos clínicos: `MedicalRecordCreated`, `SpecialtyRecordCreated`,
  `SpecialtyRecordUpdated` y, entre los eventos de cita, el campo
  `treatment_notes`.
- `ReminderSent` embebe la cita de un recordatorio futuro (24/48/72 horas
  antes). Esa cita no tiene conducta: el cierre la deja nula, y un recordatorio
  nunca se programa sobre una atención ya cerrada. Queda como follow-up
  declarado si en el futuro el payload se reutilizara para otra cosa.

## References

- `app/Authorization/Permissions.php` (`MANAGE_SPECIALTY_RECORD`)
- `app/Http/Controllers/Api/BroadcastingAuthController.php`,
  `routes/channels.php` (canal `specialty-records`)
- `app/Http/Resources/AppointmentResource.php`
- `app/Services/ConsultationService.php` (`projectOnAppointment`)
- `app/Support/AppointmentBroadcastPayload.php`
- `app/Events/MedicalRecordCreated.php`, `app/Events/Appointment*.php`,
  `app/Events/SpecialtyRecordCreated.php`,
  `app/Events/SpecialtyRecordUpdated.php`
- `resources/js/components/layout/AppLayout.vue`,
  `resources/js/modules/patients/PatientDetailPage.vue`
- `tests/Feature/Api/TechnicianClinicalRecordScopeTest.php`,
  `tests/Feature/Api/AppointmentTreatmentNotesDeskTest.php`,
  `tests/Unit/Events/ClinicalEventsChannelBoundaryTest.php`,
  `tests/Unit/Events/AppointmentBroadcastPayloadTest.php`,
  `tests/Unit/Events/DashboardUpdatesPrivateChannelTest.php`
- `odd/tasks/f5-clinical-narrative-boundary.md` (decisiones D1-D3)
