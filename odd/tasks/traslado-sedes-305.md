# Traslado entre sedes: solicitud, aprobación y efecto en el alcance (#305)

Issue #305 (`[Dominio] Traslado entre sedes: solicitud, aprobación y efecto en el
alcance`, `crit:media`, `area:backend`, `area:frontend`). Rama
`feat/branch-transfer-305`, worktree `.worktrees/305-traslado-sedes`, base `main`
@ `ad29517`.

## Specs

S1. #305: «Entidad `branch_transfer_requests`: `user_id`, `from_branch_id` (sede
al momento de pedir), `to_branch_id`, `status` (`pending`/`approved`/`rejected`/
`cancelled`), `reason`, `requested_at`, `decided_at`, `decided_by`,
`decision_notes`.»

S2. #305: «Puertas: `POST /api/branch-transfers` (el propio usuario pide su
traslado a una sede activa), `POST /api/branch-transfers/{id}/cancel` (solo el
solicitante y solo en `pending`), `POST /api/branch-transfers/{id}/approve` y
`.../reject` (alcance de grupo, `manage.user`).»

S3. #305: «**Una sola pluma**: la aprobación es la única escritura de
`users.branch_id` fuera de la edición administrativa, y ocurre en la misma
transacción que el cambio de estado; reutiliza la auditoría
`user_branch_assigned`.»

S4. #305: «Reglas: una sola solicitud abierta por usuario (409 en la segunda),
sin autoaprobación, destino activo y distinto del actual, y rechazo de una
solicitud cuya sede de origen ya no coincida con la afiliación vigente (409).»

S5. #305: «Lectura: el solicitante ve las suyas; el administrador de sede ve las
que entran o salen de su sede; gerencia ve todas.»

S6. #305: «Permisos nuevos en el catálogo: `request.branch_transfer`,
`decide.branch_transfer`; matriz en `App\Authorization\Permissions` y fixture de
smoke.»

S7. #305: «Una solicitud en `pending` no cambia `users.branch_id` ni altera
ninguna respuesta de alcance, cubierto por test.»

S8. #305: «Aprobar cambia la sede y deja `user_branch_assigned` con el valor
anterior y el nuevo, en la misma transacción; un fallo posterior no deja la sede
a medio cambiar.»

S9. #305: «Rechazar deja la afiliación intacta y registra actor, fecha y notas.»

S10. #305: «Segunda solicitud abierta → 409; autoaprobación → 403; cruzar sedes
en la lectura → 403/404 según el patrón ya fijado (lecturas 404, operaciones
403).»

S11. #305: «La matriz `RoutePermissionMatrixTest`, el catálogo y el fixture
quedan verdes.»

S12. L2: «Backend + superficie Vue mínima» — página de autoservicio para pedir y
ver el propio traslado, y para que gerencia apruebe o rechace.

## Decisiones de contrato

D1. **`from_branch_id` es nullable y copia `users.branch_id` al pedir.** Un
usuario con alcance de grupo (`branch_id` nulo) puede pedir su afiliación a una
sede; la comprobación de S4 compara la copia con la afiliación vigente
comparando nulos como iguales. Aprobarla restringe al usuario, nunca al revés.

D2. **Una sola solicitud abierta se garantiza con transacción + bloqueo de la
fila del usuario.** MySQL no tiene índice único parcial sobre `status`, así que
el servicio bloquea `users` (`lockForUpdate`) y cuenta las `pending` dentro de la
misma transacción: dos POST simultáneos del mismo usuario se serializan y el
segundo responde 409.

D3. **Nadie decide su propia solicitud.** Aprobar la propia es 403 (S4); rechazar
la propia también, porque el vocabulario del estado distingue cierre del
solicitante (`cancelled`) de decisión de gerencia (`rejected`). El 403 de
rechazo nombra la cancelación.

D4. **`cancel` escribe `decided_at`/`decided_by`.** Las cuatro salidas
(`approved`, `rejected`, `cancelled`) cierran con la misma pareja de columnas
«quién y cuándo», así que no hace falta derivar el cierre de `updated_at`.

D5. **La lectura se resuelve con un scope, no con route-model binding.** S10 pide
404 en lecturas y 403 en operaciones para el mismo cruce de sedes, y el binding
acotado responde 404 siempre. `BranchTransferRequest::scopeVisibleTo()` decide en
`index`/`show` (404) y en las tres operaciones (403).

D6. **`decide.branch_transfer` se concede a `administrador` y las dos puertas de
decisión llevan además `branch.global`.** Un administrador de sede ve entrar y
salir solicitudes pero no decide; gerencia (administrador sin sede) decide. El
SPA no puede gatear la decisión sólo con el permiso: exige `branch_id` nulo.

D7. **Las puertas de escritura releen la fila con bloqueo dentro de la
transacción.** `open()` bloquea la fila del usuario antes de contar las
`pending`; `cancel()`, `approve()` y `reject()` bloquean primero la solicitud y
después el usuario del solicitante. Así el estado que se comprueba es el que se
confirma: una decisión que commitea mientras otra espera no se pisa (un rechazo
concurrente no puede terminar aprobado, ni una cancelación marcar `cancelled`
una solicitud cuya sede ya se movió).

D8. **El 403 conserva el sobre genérico de la API.** `bootstrap/app.php` traduce
toda `AccessDeniedHttpException` a «No tienes permisos para acceder a este
recurso.», así que los mensajes específicos de la regla («No puede aprobar su
propia solicitud…») viven en la excepción y en los tests, no en el cuerpo de la
respuesta. Cambiar el renderer afectaría a toda la API y queda fuera de esta
entrega.

D9. **La sesión publica `branch_id`.** `POST /api/auth/login` y
`GET /api/auth/me` suman `branch_id` (aditivo) porque el SPA distingue gerencia
de administrador de sede por ese campo y excluye la sede actual de los destinos
del traslado; sin él la tarjeta «Mi sede actual» mentía y el formulario ofrecía
la sede que el usuario ya tenía.

## Tasks

- [ ] T1 — Migración, modelo y factory de `branch_transfer_requests` (S1).
      Ruta: inline. Commit: `feat(305): add the branch transfer request entity`.
- [ ] T2 — Catálogo de permisos, `usePermissions` y fixture de smoke (S6, S11).
      Ruta: inline. Commit: `feat(305): add the branch transfer permissions`.
- [ ] T3 — Servicio con las cuatro reglas y la transacción de una sola pluma
      (S3, S4, S8, S9). Ruta: inline. Commit: `feat(305): decide branch
      transfers with one pen`.
- [ ] T4 — FormRequests, controlador, rutas y scope de lectura (S2, S5, S7, S10).
      Ruta: inline. Commit: `feat(305): open the branch transfer doors`.
- [ ] T5 — Tests de solicitud, cancelación y alcance de lectura (S7, S10).
      Ruta: inline. Commit: `test(305): pin the transfer request rules`.
- [ ] T6 — Tests de aprobación, rechazo, auditoría y transacción (S8, S9, S10).
      Ruta: inline. Commit: `test(305): pin the transfer decision rules`.
- [ ] T7 — Superficie Vue: módulo, navegación, composable y smoke (S12).
      Ruta: inline. Commit: `feat(305): add the branch transfer surface`.
- [ ] T8 — Documentación (`AGENTS.md` §4/§12, `CREDENTIALS.md`) y checks
      completos (S11). Ruta: inline. Commit: `docs(305): record the branch
      transfer delivery`.

## Log

L1. El usuario: «haz la issue 305 en un worktree nuevo».

L2. El usuario, ante la pregunta de alcance: «Backend + superficie Vue mínima»
(«página de autoservicio para pedir y ver el propio traslado, y para que gerencia
apruebe o rechace, con navegación y smoke JS»).

L3. Evidencia: `docs/architecture/decisions/0010-branch-scope-authorization.md`
(decisión L2) dejó el flujo fuera de #283/#290 explícitamente: «Un flujo de
solicitud y aprobación de traslado es otra entrega». `users.branch_id` hoy solo
lo escribe `PUT /api/users/{id}` (`UserController`) con auditoría
`user_branch_assigned`; no existe estado ni motivo.

L4. Medido antes de escribir: 46 controllers API, 69 modelos, 34 services, 151
migraciones, 253 rutas API y 266 totales con web, 40 composables, 71 permisos.

L5. Worker backend (T3-T6): 26 tests escritos antes de las rutas (RED 26/26 con
404) y verdes después; el padre añadió la relectura con bloqueo (D7) y el guard
estático `BranchAffiliationWriterTest` que fija S3 sobre `app/`. El 403 de
autoaprobación no publica su mensaje (D8), sí su código y la ausencia de
escritura.

L6. Worker frontend (T7): la superficie compiló con `user.branch_id` inexistente
en la sesión, de modo que la tarjeta de sede y la exclusión del destino no
funcionaban y el gate de decisión dejaba actuar a un administrador de sede. El
padre añadió `branch_id` a los dos payloads de auth (D9) y pidió un seguimiento:
gate por alcance de grupo y tabla sin filtro propio en memoria.

L7. Fronteras de entrega (el cambio completo excede una rebanada de ~400 líneas;
se entrega en commits revisables, con el riesgo de carga de revisión declarado):
c1 entidad (212 líneas); c2 comportamiento backend —
permisos, servicio, puertas, rutas, `branch_id` en la sesión y las dos páginas
de documentación de la matriz (644); c3 tests de solicitud, cancelación y
alcance de lectura + guard estático de «una sola pluma» (~500); c4 tests de
decisión y del payload de sesión (~350); c5 superficie Vue (~650) y c6 su
smoke (~390) si conviene separarlos; c7 `AGENTS.md` y esta ficha. El backend
comportamiento no se puede partir más pequeño: el guard
`RoutePermissionMatrixTest` falla si un permiso del catálogo no tiene puerta.

L8. Decisión registrada: `docs/architecture/decisions/0016-branch-transfer-request.md`
(la solicitud aprobada como segunda puerta de la afiliación), con la nota de
actualización en ADR-0010 que cerraba la postergación.
