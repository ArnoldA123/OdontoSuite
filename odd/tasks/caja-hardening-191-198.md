# Caja hardening — issues #191, #193, #194, #196, #198

Rama `fix/caja-hardening`, worktree `~/proyectos/odontosuite-caja`.
Endpoint family: `pending-payments` + `cash-register-sessions` (lectura y RBAC).

## Specs

S1 — #193, propiedad de la sesión: «`CashRegisterController@show` (`:153`) y `@movements`
(`:176`) reciben el id de la sesión y devuelven su detalle con cualquier usuario del grupo de
caja, sin comprobar que la sesión sea del solicitante. [...] Extraer la regla de propiedad a la
policy de sesión y aplicarla en `show`, `movements`, `closure-report` y
`sessions/{id}/movements`, igual que `CashMovementPolicy`.»

S2 — #196, la fuga del 500: «El catch de `PendingPaymentsController@index` responde con
`message`, `error` (mensaje de la excepción) y, en el mismo envelope, `trace` (`:118-125`).
[...] Devolver sólo un mensaje genérico y registrar la excepción en el log, sin `error` ni
`trace` en la respuesta.»

S3 — #198, paginación: «`GET /api/pending-payments` devuelve todas las citas completadas con
saldo pendiente y un `meta` con `total` y `filtros`, sin `current_page`, `last_page` ni
`per_page` [...]. La lista de Cobros pagina igual: usa el modo servidor cuando `meta.last_page`
existe y, si no, ventanea las filas en cliente de 10 en 10. [...] Paginar en el controlador con
`per_page` y devolver el `meta` completo. La UI ya soporta ese contrato sin cambios.»

S4 — #194, contract warts de sesiones: «1. `GET /api/cash-register/summary` toma la última
sesión del día sin filtrar por usuario ni sucursal (`CashRegisterController.php:212-241`), así
que el resumen puede ser el de otra caja. 2. `POST /api/cash-register-sessions/{id}/open`
ignora `{id}` (`routes/api.php:304`) y delega en el open genérico: la ruta promete un recurso
que no usa. 3. `currentView()` (`CashRegisterController.php:104`) no tiene ruta: código muerto.»
Arreglo: «`summary()`: exigir `session_id` o filtrar por usuario autenticado. Quitar la ruta con
`{id}` o hacerla coherente. Eliminar `currentView()`. Sin cambios de UI: la página no consume
`summary` y usa `cash-register/current`.»

S5 — #191, decisión aprobada: «A) Ampliar el grupo de cobros pendientes a `recepcionista`
(coherente con issue #55, que ya le permite abrir caja y crear transacciones).» Cierra S5:
`GET /api/pending-payments`, `POST /api/pending-payments/{id}/pay` y el alias
`GET /api/cash-register/pending-payments` responden a `administrador`, `finanzas` y
`recepcionista`; la tab Cobros se gatea con el permiso nuevo `collectPendingPayments`; la
matriz de `CREDENTIALS.md` documenta la fila de cobro.

S6 — entrega aprobada: «commit push, pr merge y limpieza del worktree».

## Tasks

| ID  | Spec | Ruta                                                       | Commit                    |
| --- | ---- | ---------------------------------------------------------- | ------------------------- |
| T1  | S1   | #193 policy de sesión en show/movements/closure-report     | `72c81f0`                 |
| T2  | S2   | #196 envelope del 500 sin error/trace                      | `8659b84`                 |
| T3  | S3   | #198 paginación server-side de pending-payments            | `9218521`                 |
| T4  | S4   | #194 summary acotado, ruta `{id}/open` fuera, currentView  | `69e2561`                 |
| T5  | S5   | #191 RBAC de cobros a recepcionista + matriz y UI          | `aa875eb`                 |
| T6  | S6   | checks, docs sincronizados, push, PR, merge, limpieza      | docs + entrega            |

## Slices de entrega

- **Slice 1 — contrato y dureza del backend de caja** (`72c81f0`, `8659b84`, `9218521`):
  T1-T3, 419 líneas propias. Frontera registrada al cruzar el presupuesto de 400 líneas de
  revisión; se entrega como PR #230 sobre `main`.
- **Slice 2 — sesiones y RBAC de cobros** (base `fix/caja-hardening`, rama
  `fix/caja-hardening-rbac`): T4 `69e2561`, T5 `aa875eb` y el commit de docs. PR con base en el
  slice 1 hasta que este se integre, y luego reencuadrado a `main`.

## Verificación

- Suite PHP completa con `phpunit.mysql.xml` contra la base dedicada
  `odontosuite_caja_hardening_test` (`DB_SOCKET=` fuerza el MySQL de Docker en 3307). Las otras
  sesiones de agente comparten `odontosuite_test` y una llegó a recrearla en vivo, así que la
  base de tests queda aislada por worktree.
- `pnpm test:js`, `pnpm lint:check`, `pnpm format:check`, `vendor/bin/pint --test` y
  `node scripts/audit/doc-drift.mjs` como guardas del repositorio.

## Log

L1 — «quiero hacer issues #191 #193 #196 #198 #194 desarrolles, en un worktree nuevo»

L2 — Decisión #191: «A — Sí, recepcionista cobra en Caja». Detalle aprobado: middleware +
gate de UI + `CREDENTIALS.md` + tests de RBAC.

L3 — Entrega: «commit push, pr merge y limpieza del worktree».
