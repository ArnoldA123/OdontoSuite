# Recordatorios: agendar confirmaciones al crear o reprogramar (issue #154)

Status: DONE — PR #167 abierto (Closes #154), pendiente CI/merge. Worktree
`../odontosuite-reminders-154` desde `origin/main` @ 0335c26. Decisiones (L2):
agendado sincrónico en `AppointmentService` dentro de la transacción existente +
comando de reconciliación; el comando cubre el backfill de citas previas.

## Specs

S1. Crear una cita cuyo tipo tiene `requires_confirmation = true` genera el
recordatorio de 24h en `reminder_schedules`: `hours_before = 24`,
`scheduled_at = appointments.scheduled_at - 24h`, `status = pending`.

S2. La misma cita genera además 48h y 72h cuando la cita está suficientemente
lejos. "Suficientemente lejos" = `appointments.scheduled_at - hours_before` no
está en el pasado; en el borde exacto (48h u 72h de anticipación) el
recordatorio sí se agenda.

S3. Con `requires_confirmation = false` no genera ninguno.

S4. "Re-disparar es idempotente sobre `(appointment_id, hours_before)`: sin
duplicados."

S5. "Reprogramar cancela los pendientes y los recrea."

S6. "Cancelar una cita cancela sus recordatorios pendientes." Cubre el camino
vivo `PATCH /api/appointments/{appointment}/status` con `status = cancelled` y
el update del servicio con `status = cancelled`. `AppointmentService::
cancelAppointment` queda fuera (ver L4).

S7. "Sin `ReminderTemplate` activo para un tipo: advierte y no agenda
(comportamiento actual preservado)."

S8. Comando programado que reconcilie citas sin recordatorios: agenda los
faltantes de citas futuras sin duplicar ni resucitar recordatorios cancelados o
enviados, y cancela los pendientes de citas canceladas. Es también el mecanismo
de backfill para filas anteriores al fix.

S9. "Tests: feature tests con MySQL por cada regla, verificando filas en base y
no solo la respuesta."

S10. Alcance: "Solo backend. Sin cambios de UI ni de entrega de correo."

## Tasks

- [ ] T1 (S1-S5, S7) `ReminderService`: ventanas 24/48/72 sin el `diffInHours`
      actual (Carbon 3 lo devuelve negativo para citas futuras, así que 48h/72h
      nunca se agendaban) y agendado desde `AppointmentService` en crear,
      actualizar y reprogramar, dentro de la transacción. Commit: `66c598e` (389
      líneas, `ReminderSchedulingTest` verde).
- [ ] T2 (S6) Cancelación de pendientes en `updateAppointment` (status
      `cancelled`) y en `AppointmentController::updateStatus`. Commit: `523c84d`
      (152 líneas, cancelación y reactivación verdes).
- [ ] T3 (S8) Comando `reminders:reconcile` + entrada horaria en
      `routes/console.php`. Commit: `0c9fd06` (221 líneas,
      `RemindersReconcileCommandTest` 5 tests verdes).
- [ ] T4 (S9, S10) Suite MySQL completa, `doc-drift` y changelog de AGENTS.md.
      Commit: `989d152` (14 líneas). Suite MySQL 1434 passed (6242 assertions),
      doc-drift 12/12, Pint y Prettier limpios, `AGENTS.md` §6 y §12
      actualizados.

## Log

L1. Pedido original: "haz la issue 154 en un worktree nuevo".

L2. Decisión (2026-10-19, consulta al usuario): agendado sincrónico en
`AppointmentService` (crear/actualizar/reprogramar/cancelar) dentro de la
transacción existente, más comando horario `reminders:reconcile`. El backfill de
citas futuras previas al fix lo cubre el comando; sin migración de datos.

L3. Evidencia de la causa del 48h/72h inerte: `nesbot/carbon 3.10.3` devuelve
diferencia con signo (`now()->addHours(60)->diffInHours(now())` = -59.99...), así
que `diffInHours(now()) > 48` es siempre falso para citas futuras. Medido con
`php artisan tinker` en el worktree.

L4. Hallazgo fuera de alcance al probar la cancelación por servicio:
`AppointmentService::cancelAppointment` (sin callers en `app/` ni `routes/`)
lanza `QueryException: Unknown column 'waiting_lists.preferred_date'` desde
`notifyWaitingList`. El modelo `WaitingList` declara `preferred_date`,
`preferred_time_start`, `preferred_time_end` y `expires_at`, y la migración
`2025_09_20_082346_create_waiting_lists_table` crea `preferred_start_date`,
`preferred_end_date` y `preferences`. El camino está roto desde antes de esta
issue; no se toca y se reporta como follow-up.

L5. Comportamiento añadido al alcance mínimo, con test propio: reactivar una
cita cancelada (PATCH status a `confirmed`) repone sus ventanas abiertas, igual
que ya hacía el update del servicio. Evita que el camino vivo de la UI deje la
cita reactivada sin recordatorios.

L6. El guard estructural `AppointmentControllerInjectionStyleTest` fijaba el
constructor a un único servicio promovido; se generalizó a N servicios
promovidos (mismo contrato: nada de `protected` mutable).

L7. Verificación observada: `php artisan test --configuration=phpunit.mysql.xml`
= 1434 passed (6242 assertions), `node scripts/audit/doc-drift.mjs` = 12/12,
`vendor/bin/pint --test` y Prettier sin cambios. Los 8 fallos del primer intento
limpio eran ambientales (faltaba `node_modules` en el worktree para los tests de
composables que hacen `shell_exec` de node), no regresiones.

L8. Worktree: `vendor` y `node_modules` no se pueden symlinkear al checkout
principal (`vendor` rompe la base del autoloader de Composer y carga el `app/`
del repo principal; `node_modules` sí tolera symlink). `vendor` se copió.

## Slices de entrega

5 commits, 801 líneas autoradas sobre la base 0335c26: 389 (`66c598e`) + 152
(`523c84d`) + 221 (`0c9fd06`) + 14 (`989d152`) + 25 (`7ae060a`, test de no
resucitar recordatorios en updates ajenos). Los commits ya son las unidades de
revisión; si se abre PR, el agregado supera las 400 líneas, así que la entrega
es encadenada (skill `chained-pr`) y la decisión de push/PR es del usuario.

Integración: `origin/main` avanzó a b88a558 (PR #163, procedure-stats) durante
la feature; merge `6f15d45` sin conflictos (solo JS ajeno al backend).

Entrega: push de `fix/reminders-154`, PR #167 con `Closes #154`, etiquetas
`area:backend` + `type:defecto`. Body con los slices de revisión y la nota de
despliegue (correr `php artisan reminders:reconcile` una vez tras el deploy para
el backfill). Follow-ups fuera de alcance en #166.
