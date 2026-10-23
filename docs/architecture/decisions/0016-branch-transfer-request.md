# ADR-0016 — El traslado entre sedes es una solicitud aprobada, no una edición

- **Status:** Accepted
- **Decided:** 2026-10-25 (issue #305, specs S1-S11 de
  `odd/tasks/traslado-sedes-305.md`)
- **Body recorded:** 2026-10-25 (misma entrega)
- **Supersedes:** nothing. **Superseded by:** nothing. Completa la postergación
  que declara ADR-0010.

## Context

ADR-0010 fijó la afiliación única: `users.branch_id` es la sede del usuario y
`null` es alcance de grupo. Dejó fuera, a propósito, el proceso que mueve esa
columna: «Un flujo de solicitud y aprobación de traslado es otra entrega».

Mientras tanto el traslado era un `PUT /api/users/{id}` con alcance de grupo. La
única traza era la fila `user_branch_assigned` de `audit_logs`, con el valor
anterior y el nuevo. No existía solicitud, no existía estado, no existía motivo
y no existía aprobación: el profesional no podía pedir nada y gerencia cambiaba
la sede cuando se acordaba. El caso de operación que originó la decisión es
explícito —el profesional pide, gerencia aprueba y recién entonces cambia su
sede—, y mientras la solicitud está pendiente no debe habilitar nada, ni
siquiera parcialmente.

## Decision

**Una fila propia para el proceso.** `branch_transfer_requests` guarda
`user_id`, `from_branch_id` (la afiliación copiada al pedir; `null` cuando el
usuario tenía alcance de grupo), `to_branch_id`, `status`
(`pending|approved|rejected|cancelled`), `reason` obligatorio, `requested_at` y
la decisión (`decided_at`, `decided_by`, `decision_notes`). Ambas sedes son
`restrictOnDelete`: la historia sigue nombrando la sede que menciona, así que
una sede con traslados se retira (se desactiva), no se borra.

**Una sola pluma.** La aprobación es la única escritura de `users.branch_id`
fuera de la edición administrativa, y ocurre en la misma transacción que el
cambio de estado, la afiliación y la auditoría `user_branch_assigned` con el
valor anterior y el nuevo. Un fallo posterior revierte todo: la sede nunca queda
a medio cambiar. Un guard estático (`BranchAffiliationWriterTest`) falla si
aparece otro escritor de la columna o de la acción de auditoría.

**Nadie decide su propia solicitud.** Aprobar la propia es 403; rechazarla
también, porque el vocabulario distingue el cierre del solicitante (`cancelled`,
por su puerta `cancel`) de la decisión de gerencia (`rejected`). Decidir exige
alcance de grupo: `decide.branch_transfer` se concede a administración y las dos
puertas llevan además `branch.global`, de modo que un administrador de sede ve
entrar y salir las solicitudes de la suya y no las decide.

**Una solicitud abierta por usuario.** MySQL no tiene índice único parcial sobre
`status`, así que la puerta bloquea la fila del usuario dentro de una
transacción antes de contar las `pending`: dos solicitudes simultáneas se
serializan y la segunda responde 409. Las demás reglas de estado también son 409
—origen que ya no coincide con la afiliación vigente, destino desactivado
después de pedir, solicitud ya resuelta—, mientras que la forma del payload
(destino activo y distinto del actual) es 422.

**Lectura y operación no comparten código de respuesta.** El solicitante ve las
suyas, el administrador de sede las que entran o salen de su sede y gerencia
todas. Un cruce de alcance es 404 en lectura (no existe para quien lee) y 403 en
operación (existe, pero no es suya), que es el patrón que ya fija ADR-0010.

**El estado que se comprueba es el que se confirma.** Las puertas de escritura
releen la solicitud con bloqueo dentro de la transacción (y después la fila del
solicitante) para que una decisión concurrente no se pise: un rechazo que
commitea mientras otra puerta espera no puede terminar aprobado.

## Alternatives considered

- **Mantener la edición administrativa con auditoría.** Es lo que había: no hay
  motivo, no hay aprobación y el profesional no participa del proceso que lo
  mueve. La issue pide exactamente lo contrario.
- **Índice único parcial para «una abierta».** MySQL no lo soporta; un índice
  único sobre `(user_id, status)` prohibiría dos aprobadas o dos rechazadas del
  mismo usuario, que son historia legítima. Se eligió transacción + bloqueo de
  la fila del usuario.
- **404 también en las operaciones.** Sería más simple y no revela la existencia
  de una fila ajena, pero borra la diferencia entre «no existe» y «no es suya»
  que el resto de la API ya expresa con 403, y el administrador de sede necesita
  saber que la solicitud existe para responder por ella.
- **Permitir que el solicitante rechace su propia solicitud.** Duplicaría el
  cierre con otro nombre y dejaría `rejected` como un estado que el interesado
  puede escribir; el retiro pertenece a `cancel`.

## Consequences

- Mover una sede tiene dos puertas: la edición administrativa (gerencia) y la
  aprobación de una solicitud. Ambas dejan `user_branch_assigned` y ambas exigen
  alcance de grupo.
- Una solicitud pendiente no habilita nada: el usuario sigue operando en la sede
  que ya tiene y ninguna respuesta de alcance cambia por pedir.
- El administrador de sede gana lectura sobre traslados que entran o salen, sin
  ganar la decisión.
- `POST /api/auth/login` y `GET /api/auth/me` publican `branch_id`, porque el
  SPA necesita distinguir gerencia de administrador de sede sin repetir la
  matriz.
- El 403 conserva el sobre genérico de la API (`bootstrap/app.php`): los
  mensajes de cada regla viven en la excepción y en los tests, no en el cuerpo.

## References

- Issue #305; contrato completo en `odd/tasks/traslado-sedes-305.md`.
- ADR-0010 — la sede como dimensión de autorización.
- Código: `App\Models\BranchTransferRequest`,
  `App\Services\BranchTransferService`, `App\Authorization\Permissions`.
- Pruebas: `tests/Feature/Api/BranchTransferRequestTest.php`,
  `tests/Feature/Api/BranchTransferDecisionTest.php`,
  `tests/Unit/Authorization/BranchAffiliationWriterTest.php`.
