# Caja follow-ups — rutas fantasma, alcance de la sesión actual y fugas de los 500

Rama `fix/caja-followups`, worktree `/home/chomb/proyectos/odontosuite-caja-followups`,
base `main` @ `dc11206`. Origen: cuatro defectos vecinos detectados al cerrar #191/#193/#194/
#196/#198, ninguno cubierto por las issues abiertas (#192, #195, #197, #199).

## Specs

S1 — `POST /api/cash-register-sessions/{id}/close` ignora su `{id}` y delega en el cierre
genérico: la ruta promete un recurso que no usa. Es el gemelo exacto de
`{id}/open`, retirada en #194. Se retira.

S2 — `Route::apiResource('cash-register-sessions', ...)` publica `store`, `update` y `destroy`,
pero `CashRegisterController` no implementa esos métodos: pedirlos es un 500 por método
inexistente. Se publican sólo `index` y `show`, los que existen.

S3 — `CashRegisterService::getCurrentSession()` intenta acotar por usuario con
`if (! $this->userCanManageCashRegister())`, pero ese helper devuelve `true` para los tres roles
de caja, así que el guard nunca se aplica y `GET /cash-register/current` devuelve la última
sesión abierta de la sucursal, incluso la de otro usuario. Desde #193 leer esa sesión responde
403. La regla correcta ya está escrita en `CashRegisterPolicy::view`: dueño o administrador.
Además, `OpenCashRegisterRequest` valida por usuario y sucursal pero responde «Ya existe una
sesión de caja abierta para esta sucursal.», que describe otra regla.

S4 — Los `catch` de `CashRegisterController` (`current`, `index`, `show`, `movements`,
`summary`) responden `error` con `$e->getMessage()`, la misma clase de fuga que #196 corrigió en
pending-payments. Mensaje genérico y excepción al log.

## Tasks

| ID  | Spec | Ruta                                                        | Commit    |
| --- | ---- | ----------------------------------------------------------- | --------- |
| T1  | S1   | retirar `{id}/close` y acotar el apiResource                | `d0f1cca` |
| T2  | S3   | alcance de `getCurrentSession` y mensaje del FormRequest    | `9227a3e` |
| T3  | S4   | 500 sin mensaje de la excepción en CashRegisterController   | `4955db3` |
| T4  | —    | checks, docs, push, PR, merge, limpieza                     | docs      |

## Slices de entrega

S1 y S2 viven en `d0f1cca` porque son la misma superficie (rutas que prometen métodos
inexistentes); S3 y S4 son independientes entre sí y del slice.

Frontera registrada: 429 líneas propias en 3 commits, 29 sobre el presupuesto de 400. Se entrega
como **un solo PR**, no encadenado: los cuatro defectos son un único módulo, los commits ya
separan las tres superficies y partir 429 líneas en dos PRs añade ceremonia de merge sin
ganancia de foco. Carga de revisión: tres commits con sus tests al lado, ~430 líneas.

## Verificación

- Suite PHP completa con `phpunit.mysql.xml` contra la base dedicada
  `odontosuite_caja_followups_test` (`DB_SOCKET=` fuerza el MySQL de Docker en 3307): **1745
  tests, 8309 aserciones, 0 fallos**. Las otras sesiones de agente comparten `odontosuite_test` y
  una llegó a recrearla en vivo, así que la base de tests queda aislada por worktree.
- Vitest completo 746 tests; ESLint 0 errores (34 avisos preexistentes); Prettier limpio;
  `vendor/bin/pint --test` 589 archivos; `node scripts/audit/doc-drift.mjs` 12/12 (197 rutas API
  / 210 totales).
- PR #236, un solo slice (429 líneas propias, 29 sobre presupuesto; frontera y motivo en
  «Slices de entrega»).

## Log

L1 — «esos pasos disponibles son fuiera de todas las issues que tebngo abieras?, si son fiera
pues trata de desarrollarlas si son muy grandes levanta issues, con eso ya has el push, pr merge
y limpia el worktree»
