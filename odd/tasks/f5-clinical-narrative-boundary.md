# F5 — Frontera de la narrativa clínica (#313, #314, #315)

Feature: que el contenido clínico no cruce al escritorio no clínico por ninguna
superficie: la lectura HTTP, el payload de la cita ni el canal de difusión.
Branch: `fix/f5-clinical-narrative-boundary` (worktree `.worktrees/f5-clinical-boundary`).
Issues: #313, #314, #315.

## Specs

S1 (#313). "La decisión queda registrada (ADR o nota en `docs/architecture/`)."

S2 (#313). "Si se cierra la lectura: la API responde 403 al técnico en las rutas
de lectura y la SPA no ofrece el módulo, con test que lo fije."

S3 (#314). "La decisión queda registrada." / "Si se filtra: la API no publica
`treatment_notes` al escritorio de laboratorio (y a recepción, si corresponde),
con test de ambos lados (quien lo ve y quien no)."

S4 (#315). "`broadcastWith()` de `MedicalRecordCreated` no expone contenido
clínico por el canal abierto a todos los roles." / "Test que fije el payload y la
autorización del canal."

## Tasks

| ID  | Spec     | Route    | Commit                                                              | State |
| --- | -------- | -------- | ------------------------------------------------------------------- | ----- |
| T1  | S2       | worker   | `fix(313): close the specialty record to the laboratory desk`      | done  |
| T2  | S3       | worker   | `fix(314): stop publishing the clinical conduct on the appointment` | done  |
| T3  | S4       | worker   | `fix(315): keep the clinical document off dashboard-updates`        | done  |
| T4  | S1       | inline   | `docs(313,314,315): record ADR-0016 and sync the desk docs`         | done  |
| T5  | S1-S4    | inline   | `docs(f5): record the close evidence`                               | done  |

T1 — #313: `manage.specialty_record` deja de incluir `tecnico_dental`; el canal
`specialty-records` pierde al técnico; la navegación no ofrece el módulo y el
tab de especialidades del detalle de paciente se oculta sin el permiso. Los
eventos `SpecialtyRecordCreated`/`SpecialtyRecordUpdated` dejan de publicar en
`dashboard-updates` (un canal no puede variar el payload por rol y
`dashboard-updates` lo reciben los siete roles).

T2 — #314: el cierre deja de copiar `evolution.plan` en
`appointments.treatment_notes`; `AppointmentResource` publica el campo solo a
quien lee la historia clínica (`manage.medical_record`), y los eventos de cita
quitan el campo de su payload (el mismo payload llega a recepción y laboratorio).

T3 — #315: `MedicalRecordCreated` publica solo en `medical-records`.

T4 — ADR-0016, índice de ADR, `AGENTS.md` §6/§12, `CREDENTIALS.md` y
`docs/architecture/desks.md`.

T5 — suite MySQL completa, guards de formato y docs, verificación del diff.

## Log

L1 (usuario, 2026-10-23): "haz la issue 313,314,315 en un worktree nuevo"

L2 (#313): "El argumento de #285 fue que el registro de especialidad es insumo
del trabajo protésico, no la historia clínica. Hay que decidir si esa
justificación se sostiene para el escritorio de laboratorio o si el técnico debe
ver solo lo que necesita del caso (pieza, procedimiento, fechas) y no la
narrativa quirúrgica."

L3 (#314): "¿La conducta de la cita es contenido clínico o resumen operativo? Si
es clínico, el cierre no debe copiarlo o el recurso debe filtrar el campo por
escritorio; si es operativo, hay que declararlo y no presentarlo como historia."

L4 (#315): "Decidir si el evento debe seguir viajando por `dashboard-updates`
(los contadores del panel pueden refrescarse con un payload mínimo) o solo por
`medical-records`."

D1 (decisión #313, cierra la lectura): el registro de especialidad es un acto
clínico con narrativa quirúrgica (`surgical_notes`, `post_surgical_notes`,
`complications`, `follow_up_notes` y sus equivalentes). El trabajo protésico no
la necesita: el caso (`lab_cases`) ya publica pieza, trabajo, material, fechas y
técnico. El escritorio de laboratorio deja `manage.specialty_record` por
completo —lectura y escritura son el mismo permiso— y el canal
`specialty-records` deja de autorizarlo, porque publica el modelo completo.

D2 (decisión #314, contenido clínico): `appointments.treatment_notes` es la
conducta clínica copiada de la evolución, no un resumen operativo. El cierre deja
de copiarla (la conducta vive en la evolución y en la solicitud de continuidad;
la cita conserva `notes`, que es su anotación de agenda) y la publicación se
acota al escritorio que lee la historia clínica: administración, clínico y
asistente. Recepción y laboratorio no reciben el campo ni por HTTP ni por
socket. Las filas históricas no se reescriben (el ledger no se altera), pero
tampoco se publican fuera del escritorio clínico.

D3 (decisión #315, canal): un canal no puede variar su payload por rol. Un
evento cuyo payload es un documento clínico viaja solo por el canal de los
escritorios que lo leen: `MedicalRecordCreated` por `medical-records` (y
`SpecialtyRecordCreated`/`Updated` por `specialty-records`), nunca por
`dashboard-updates`, que autoriza a los siete roles. El panel refresca sus
contadores con los eventos de agenda, pacientes y dinero, que no llevan
narrativa clínica.

L5 (consecuencia de alcance): D3 alcanza también a
`SpecialtyRecordCreated`/`Updated` —publicaban el modelo completo en
`dashboard-updates`— porque el registro es la misma narrativa que #313 cierra.
Ninguno de los tres eventos tenía consumidor en `dashboard-updates`:
`useWebSocketNotifications` ya escucha `medical-record.created` y
`specialty-record.*` en sus canales clínicos, y `DashboardPage` no los escucha.

L6 (revisado tras la verificación independiente): `ReminderSent` embebía la cita
cruda de un recordatorio futuro (24/48/72 h antes) y publicaba por `reminders` y
`appointments`, canales de recepción y laboratorio. La premisa «la cita futura no
tiene conducta» dependía de que nadie escribiera `treatment_notes` a mano, así
que el payload del recordatorio también recorta el campo
(`Arr::except(..., ['appointment.treatment_notes'])`), con test. La escritura
manual de `treatment_notes` por `PUT/PATCH /api/appointments/{id}` sigue
abierta: la issue pide cerrar la publicación, no el contrato de escritura. El
intento de suscripción del rol sin permiso a `specialty-records` en
`useWebSocketNotifications` es el mismo patrón preexistente de #285 para
`medical-records` y también queda como follow-up.

## Delivery — Feature Branch Chain

Decidido por el usuario (2026-10-23) al pasar el presupuesto de revisión de
~400 líneas autoradas. Una sola pasada de slicing; el presupuesto no recorta
código ni tests.

| Slice | Branch | Commits (asunto) | Contenido |
| --- | --- | --- | --- |
| 1 | `fix/f5-313-315-clinical-channel` | `fix(313,315): keep clinical documents off dashboard-updates` · `fix(313): close the specialty record to the laboratory desk` | Eventos clínicos fuera de `dashboard-updates` (#313, #315) y registro de especialidad cerrado al laboratorio (#313) |
| 2 | `fix/f5-314-appointment-conduct` | `fix(314): stop publishing the clinical conduct on the appointment` · `fix(314): keep the reminder payload free of the conduct` · `docs(313,314,315): record ADR-0016…` · `docs(f5): record the close evidence` | Cita sin la conducta clínica (#314), recordatorio sin la cita cruda, ADR-0016 y documentos de la frontera |

- **Tracker:** `fix/f5-clinical-narrative-boundary`, en `main`, PR draft/no-merge
  que recibe los slices en orden.
- **Dependencia:** el slice 2 arranca en el tip del slice 1; su PR apunta a la
  branch del slice 1.
- **Fuera de alcance declarado:** escritura manual de `treatment_notes` por
  `PUT/PATCH /api/appointments/{id}` (el campo ya no se publica fuera del
  escritorio clínico ni por socket); intento de suscripción del rol sin permiso
  en `useWebSocketNotifications` (patrón preexistente de #285).

## Verification record

| Check | Command | Result |
| --- | --- | --- |
| Permisos, canal, SPA y dos lados | `--filter 'PermissionCatalogTest\|DeskMatrixTest\|SpecialtiesNavigationDisambiguationTest\|TechnicianClinicalRecordScopeTest\|DomainChannelsAuthorizationTest\|FrontendPermissionContractTest'` | OK (76 tests, 1242 assertions) |
| Eventos clínicos | `--filter 'DashboardUpdatesPrivateChannelTest\|ClinicalEventsChannelBoundaryTest\|DomainChannelsPrivateTest'` | OK (54 tests, 142 assertions) |
| Recordatorio sin la cita cruda | `--filter 'AppointmentBroadcastPayloadTest'` | OK (5 tests, 48 assertions) |
| Cita y difusión | `--filter 'AppointmentTreatmentNotesDeskTest\|ConsultationClosureTest\|AppointmentBroadcastPayloadTest'` | OK (17 tests, 157 assertions) |
| Detalle de paciente (JS) | `pnpm vitest run tests/js/smoke/patients-detail-redesign.test.js` | 14 passed |
| Guardas de docs | `ArchitectureDecisionsIndexTest`, `AgentsDocsSyncTest`, `doc-drift.mjs` | OK (9 tests) y 14/14 |
| Formato | `vendor/bin/pint --test` sobre 59 archivos PHP tocados | PASS |
| Suite PHP completa | `vendor/bin/phpunit -c phpunit.mysql.xml` (MySQL, base propia) | OK (2686 tests, 16242 assertions) |
| Suite JS completa | `pnpm test:js` | 115 archivos, 956 tests, todos en verde |
| Smoke del detalle de paciente | `pnpm vitest run tests/js/smoke/patients-detail-redesign.test.js` | 14 passed |
| Revisión nativa (RDD) | `assess` → `inspect` → `start` → captura de reviewers | **Bloqueada por el transporte**: `pi-host-relay-transport-failure`, «Stream ended without finish_reason» en el grupo de 3 y en el slot `review-risk` (9m 28s). Lineage `review-2cd04e630ed70cfc` abierta, sin mutación. |

