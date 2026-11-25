# Citas: privacidad por profesional, sede y anuncio

## Objetivo

Cerrar tres fallas medidas en la agenda:

1. Un rol clínico ve citas de otros profesionales. Decisión del usuario:
   **privacidad estricta**. `odontologo`, `implantologo`, `tecnico_dental` y
   `asistente` solo leen y operan las citas con `user_id` propio;
   `administrador` y `recepcionista` conservan el alcance de su sede.
2. Una cita creada por recepción para un profesional de otra sede/rama no la ve
   ese profesional. Causa medida: la cita nace con `branch_id = null` cuando
   quien la crea no tiene sede (recepción/administración de grupo), y el índice
   del profesional con sede filtra `branch_id = su sede`. Debe heredar la sede
   del profesional asignado (y, en su defecto, la del ambiente) y los datos
   existentes deben rellenarse.
3. Pulsar «Anunciar» confirma con un toast pero no deja rastro visible: el
   anuncio no cambia `status` (correcto) pero tampoco guarda cuándo ocurrió.
   Debe quedar `announced_at`/`announced_by`, visible en la agenda y en Mi día.

Además, el cambio de estado por `PATCH /api/appointments/{id}/status` no emite
evento, así que la confirmación/cancelación no llega en tiempo real. Debe emitir
`AppointmentUpdated` después del commit.

## Contrato de lectura

- `AppointmentPolicy::seesWholeAgenda(User)` es la única matriz: verdadero solo
  para `administrador` y `recepcionista`.
- `view`/`viewAny`: permiso `manage.appointment` + alcance propio para clínicos.
- `AppointmentController@index` acota con `where('user_id', Auth::id())` cuando
  el actor no es lector amplio, después del filtro de sede.
- `show`, `update`, `updateStatus`, `reschedule`, `destroy` y las puertas de
  custodia (`check-in`, `announce`, `start-attention`) autorizan la política
  antes de escribir. Recepción/administración sobre cualquiera de su sede;
  clínico solo sobre las suyas.
- No se agrega permiso nuevo ni se cambia el mapa de canales.

## Contrato de sede

- Al crear: `branch_id` explícito no nulo manda; si falta, se hereda de
  `users.branch_id` del profesional; si también falta, de
  `dental_chairs.branch_id`; si no, queda nulo.
- Al editar: solo se recalcula si cambia `user_id`/`dental_chair_id` o si llega
  `branch_id` explícito. Un `branch_id` nulo en el payload no borra la sede.
- Migración de datos: rellenar `appointments.branch_id` nulo desde el
  profesional y luego desde el ambiente. Idempotente; `down()` no revierte datos.
- El guard existente `ScopesToBranch` sigue siendo la autoridad de escritura: un
  recepcionista de sede 4 que asigna un profesional de sede 5 recibe 403.

## Anuncio

- Migración: `announced_at` timestamp nullable, `announced_by` FK nullable
  `nullOnDelete`.
- `AppointmentCustodyService::announce`: guarda ambos, mantiene `status=arrived`,
  registra la acción `announcement` en `appointment_custody_logs` y emite
  `PatientReady` como hoy.
- `Appointment` fillable/cast/relación `announcedBy`; índice y recurso publican
  `announced_at`, `announced_by` y `announced_by_user` cuando está cargada.

## Difusión

- Los eventos de cita (`AppointmentCreated`, `AppointmentUpdated`,
  `AppointmentCompleted`, `AppointmentDeleted`, `AppointmentCheckedIn`) siguen
  viajando por `appointments` y `dashboard-updates`, pero su payload se reduce a
  lo operativo: `id`, `user_id`, `status`, `scheduled_at`, `updated_at`. Sin
  nombre, documento, teléfono ni contenido clínico del paciente. `AppointmentDeleted`
  conserva `appointment_id` y `user_id` en el sobre.
- `PATCH /status` emite `AppointmentUpdated` después del commit, con el mismo
  payload reducido.
- `PatientReady` no cambia: va al canal privado del profesional con el detalle
  que ya publica.

## T3 — seeders y datos existentes (unidad aparte)

- `BranchSeeder` incluye Trujillo (`SC-TRU-01`) y `DatabaseSeeder` lo corre antes
  de los usuarios.
- Todos los usuarios sembrados y existentes pasan a Trujillo, salvo
  `admin+administrador@test.com` (`admin_test`), que queda sin sede como
  administrador de grupo para conservar la administración de sedes.
- `EnvironmentSeeder` usa `updateOrCreate` y reparte los ambientes por sede:
  AMB-001, AMB-002, AMB-003 y AMB-007/008 a Trujillo; AMB-004 a Lima central;
  AMB-005 a Norte; AMB-006 a Sur. Al menos tres activos en Trujillo.
- Las citas sembradas llevan la sede del profesional/ambiente; las completadas
  usan Trujillo. Las sesiones y movimientos de caja de usuarios de Trujillo
  llevan Trujillo.
- Migración `2026_11_24_100000_assign_trujillo_branch_to_seeded_data.php`:
  rellena usuarios nulos (salvo el admin de grupo), ambientes nulos por el mapa
  de códigos, sesiones de caja nulas y citas nulas desde profesional/ambiente.
  Idempotente; en una BD fresca no falla si Trujillo aún no existe.

## T4 — ambientes con sede y participantes acotados (unidad aparte)

- El formulario de `/environments` gana el campo Sede; la lista y el detalle lo
  publican. Un actor restringido lo ve fijado a su sede; el administrador de
  grupo elige entre las sedes activas y el campo es obligatorio.
- `DentalChairController@index` filtra por la sede efectiva y publica `branch`;
  `store/update` validan `branch_id` y devuelven 403 (no 500) ante otra sede.
- `UserController@active` filtra profesionales por la sede efectiva del actor.
- `StoreAppointmentRequest`/`UpdateAppointmentRequest` responden 403 cuando un
  actor restringido nombra un profesional o ambiente de otra sede (ADR-0010);
  el 422 queda para que el alcance de grupo no una dos sedes. El detalle de un
  ambiente ajeno responde 404 (`visibleToBranch`).

## T6 — tiempo real sin recarga completa (unidad aparte)

- El listener de `.appointment.created` de `CalendarPage.vue` hoy llama a
  `loadAppointments()`, que prende `loading` y vuelve a pedir todo el rango
  visible. Se reemplaza por una lectura puntual
  `GET /api/appointments/{id}` y un `upsert` en el arreglo, sin tocar `loading`
  ni el rango. Lo mismo para `updated`, `checked_in`, `completed` y `deleted`:
  actualizar o quitar la fila, no recargar la agenda.
- Un clínico ignora los eventos de otra cita; si la cita estaba en su lista y
  deja de ser suya, la fila se retira. Si la cita queda fuera del rango
  visible, no se inserta (o se retira si estaba). El creador local usa el mismo
  upsert y conserva la confirmación única.
- Pruebas: el evento provoca exactamente una lectura puntual, ningún
  `loading`/fetch del rango, y un arreglo con muchas citas no cambia el costo.

## Fuera de alcance de esta unidad

- Frontend de agenda (`resources/js/modules/appointments/**`): lo implementa la
  unidad T2.
- Pruebas de navegador y suite completa.
- Commit.

## Criterios de aceptación backend

- Un odontólogo no ve ni abre la cita de otro; sí la suya. Recepción y
  administración ven toda su sede.
- Recepción sin sede crea para un profesional con sede 4 y la cita queda en
  sede 4; el profesional la ve. La migración rellena las filas existentes.
- `announce` deja `announced_at`/`announced_by`, `status=arrived`, log y evento.
- `PATCH /status` emite `AppointmentUpdated`.
- El payload de cita no contiene datos del paciente.
- Pruebas focalizadas en verde, sin regresiones en `Appointment*`.
