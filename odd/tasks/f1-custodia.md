# F1 — Custodia: llegada en recepción, anuncio y escritorio Mi día

Issue #281 (épica #294). Fase F1; depende de F0 (#280).

## Specs

S1. `arrived` marcado por recepción, con autor y hora.

S2. Evento `paciente_listo` al anunciar; el anuncio no crea estado.

S3. Escritorio "Mi día" del profesional: en espera, atendiendo, próximos.

S4. Respaldo: el profesional puede marcar llegada si recepción no está,
registrando quién lo hizo.

S5. El odontólogo inicia la atención desde su cola, sin buscar en el calendario.

S6. Recepción no puede cerrar ni documentar el acto clínico.

S7. Todo cambio de custodia deja registro de autor, hora y puerta.

## Tasks

- T1 — Dominio de custodia: estado `arrived`, autor de la llegada, bitácora de
  custodia (autor, hora, puerta), guardas del endpoint genérico de estado.
  Specs: S1, S4, S6, S7. Commit: `b5022c1`.
- T2 — Anuncio: `POST /appointments/{id}/announce` + evento `paciente_listo`
  sin mutación de estado. Specs: S2. Commit: `b5022c1` (comparte contrato con T1:
  el anuncio exige `arrived`, que T1 introduce).
- T3 — Cola del profesional: `GET /api/my-day`, página `/my-day`, navegación y
  tiempo real. Specs: S3, S5. Commit: `6b322a7`.
- T4 — Recepción: acciones de llegada y anuncio en el calendario, vocabulario
  de estado `arrived`. Specs: S1, S2, S6. Commit: `c135918`.
- T5 — Documentación y guardas (AGENTS.md, CREDENTIALS.md, doc-drift).
  Commit: `docs(citas): documentar custodia F1` (este documento vive en `odd/`, ignorado por git).

## Contrato decidido

- Estados: `scheduled → confirmed → arrived → in_consultation → completed`.
  `arrived` entra en el enum MySQL; `in_consultation` sigue significando
  "siendo atendido". `PATCH /appointments/{id}/status` deja de aceptar
  `arrived`, `in_consultation` y `completed` (422 con la puerta correcta);
  cada una tiene su endpoint con su permiso.
- Llegada: `POST /api/appointments/{id}/check-in` (permiso
  `check_in.appointment`). Escribe `status=arrived`, `checked_in_at` y
  `checked_in_by`; es idempotente. Puerta derivada del rol del actor:
  recepción/administrador → `reception`; clínico → `professional`.
- Atención: `POST /api/appointments/{id}/start-attention` (permiso
  `start.consultation`, roles clínicos). Solo desde `arrived`; escribe
  `in_consultation` y la bitácora con puerta `professional`.
- Anuncio: `POST /api/appointments/{id}/announce` (permiso
  `announce.patient`, recepción/administrador). Exige `status=arrived`; emite
  `PatientReady` (`paciente_listo`) al canal privado `user.{user_id}`; no toca
  estado ni bitácora.
- Bitácora: tabla `appointment_custody_logs`
  (`appointment_id`, `action` = `arrival|attention_start`, `door` =
  `reception|professional`, `from_status`, `to_status`, `actor_id`,
  `created_at`). Modelo `AppointmentCustodyLog`.
- Mi día: `GET /api/my-day` (permiso `view.my_day`, roles clínicos). Devuelve
  las citas de hoy del profesional autenticado en tres cubos:
  `waiting` (`arrived`), `attending` (`in_consultation`), `upcoming`
  (`scheduled|confirmed`).
- Permisos nuevos: `view.my_day`, `start.consultation`, `announce.patient`.

## Log

L1. Usuario: "haz la issue 281, en worktree nuevo".

### Frontera de slices (commit b5022c1)

El primer commit del feature reúne T1+T2 en una sola unidad de 1286 líneas: el
anuncio exige `arrived`, la guarda del endpoint genérico y la bitácora de
custodia son un único cambio de contrato con sus tests, y separarlos dejaría un
commit donde el endpoint publica un evento que todavía no puede dispararse.

Unidades posteriores: `fc2f939` fija la puerta clínica de la atención en la
matriz de rutas; `c135918` entrega la recepción (calendario, vocabulario y
composables); `6b322a7` entrega el escritorio Mi día (API consumida, página,
navegación y tiempo real); `2117fa7` documenta el estado medido; y `6b4e032`
(corregido por verificación independiente) serializa la transición de custodia
con `lockForUpdate` para que un traspaso deje exactamente un registro. El
slice acumulado supera las 400 líneas porque la feature es una sola: el
contrato de custodia y sus dos superficies. La revisión se hace por unidad
(commits), no por el acumulado de la rama.

### Verificación final

- Suite PHP completa en MySQL: OK (2114 tests, 12223 assertions).
- Vitest completo: 904 tests en 108 archivos.
- `pnpm build`, `pnpm lint:check` (0 errores) y `pnpm format:check`: verdes.
- `doc-drift`: 14/14; `sdd:check-events`: 0 huérfanos.
- Verificación independiente (gentle-ai-verify): PASS en S1/S2/S3/S4/S5/S6/S7,
  sin bypasses de permisos; detectó el TOCTOU de idempotencia, corregido en
  `6b4e032`.
- `gentle_review assess`: `nativeRisk=unassessable` (el binario no ve el
  candidato ya commiteado); no se inició review nativa. Sin push ni PR: la
  rama queda local a decisión del humano.
