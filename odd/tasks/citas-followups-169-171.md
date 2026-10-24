# Follow-ups de citas #169, #170, #171

Tres hallazgos advisory de la revisión nativa de #166 (PR #168): la ventana de
lista de espera pierde el día final, el runner SQLite no salta los tests con BD,
y la matriz de zona horaria no incluye la reprogramación naive-local.

## Specs

### S1 — ventana de lista de espera inclusiva por día (#169)

Los criterios de aceptación de la issue #169, verbatim:

> - Una sola regla escrita y aplicada en `notifyWaitingList()`, con su test: o el
>   fin es exclusivo y se documenta (y entonces se corrige lo que se guarde), o un
>   fin a medianoche significa «todo ese día».
> - Test MySQL que cubra el borde superior exacto y el borde superior a medianoche.
> - Sin migraciones ni cambios de esquema salvo que la decisión lo exija.

Regla elegida: **la ventana se expresa en días**. `preferred_start_date` cubre su
día completo desde el primer minuto y `preferred_end_date` cubre su día completo
hasta el último minuto; ambas cotas son inclusivas por fecha, no por instante. Un
`preferred_end_date = 2026-11-03 00:00:00` significa «hasta el 3 de noviembre
incluido».

### S2 — gate MySQL en los tests que dependen de base de datos (#170)

Los criterios de aceptación de la issue #170, verbatim:

> - Los tests que requieren MySQL se saltan explícitamente cuando el driver no es
>   MySQL (`DB::getDriverName()`), reportando `skipped` en vez de `failed`; vale un
>   helper en `TestCase` o un trait reutilizable.
> - `php artisan test` (SQLite) deja de mezclar fallos de runner con fallos reales.
> - `php artisan test --configuration=phpunit.mysql.xml` sigue ejecutando esos
>   tests sin saltos.
> - Un test que fije el guard (el skip no puede volverse invisible).

### S3 — matriz de zona horaria completa en su archivo (#171)

Los criterios de aceptación de la issue #171, verbatim:

> - `AppointmentTimezonePersistenceTest` incluye el caso naive-local de
>   `PATCH /api/appointments/{appointment}/reschedule`, con la misma aserción de
>   fila en base que el caso con offset.
> - El test existente de `AppointmentRescheduleTest` no se duplica ni se toca: la
>   regla queda completa en la suite de zona horaria y el contrato del endpoint
>   sigue donde está.

## Tasks

| ID | Spec | Ruta | Commit |
| --- | --- | --- | --- |
| T1 | S1 | padre inline, test-first | 5633448 |
| T2 | S2 | padre inline, test-first | ae4e456 + 11c3ec7 |
| T3 | S3 | padre inline, test-first | 5d2cb70 |

## Log

L1 — pedido del usuario, verbatim:

> haz las issues 169, 170, 171 en un worktree nuevo

L2 — decisión de S1: la ventana se compara por fecha (`whereDate`) en ambos
bordes; no se toca el esquema. Evidencia: RED 2 fallos (`midnight end`,
`later-same-day start`), GREEN 7/7 con `phpunit.mysql.xml`.

L3 — decisión de S2: el gate se deriva del trait de esquema
(`RefreshDatabase`, `DatabaseMigrations`, `DatabaseTruncation`,
`DatabaseTransactions`) en `Tests\TestCase::setUpTraits()`, no de un atributo
por clase: 56 clases usan esos traits y solo 3 lo declaraban en el docblock.
Evidencia: SQLite antes 318 errores en Feature, después 1452 tests / 0 fallos /
359 skipped; MySQL completo sin saltos.

L4 — S3 no tiene RED propio: el comportamiento ya lo entregó #166 y
`AppointmentRescheduleTest` lo fija; la issue pide simetría de la matriz, no un
cambio de comportamiento. Evidencia: 17/17 en los dos archivos de citas con
`phpunit.mysql.xml`.

L5 — revisión nativa (linaje review-d781958ed8a47ccd, tier high, lentes risk/
readability/reliability): tres reviewers admitidos, un refuter. El refuter
confirmó `R3-WindowsShell` (CRITICAL, deterministic, introduced): el guard de
#170 armaba una línea POSIX con prefijos `VAR=valor` y `exec`, que en Windows
—el entorno local documentado en AGENTS.md §8— falla siempre. Corrección
acotada (25 líneas de diff, dentro del presupuesto 185): Symfony Process con
argv y entorno, sin shell; `PHP_BINARY` ejecuta el proxy de vendor. Aprobada y
acknowledgement quemado (`gentle-ai.review-acknowledged/v1`).

L6 — los cuatro advisories eran de este trabajo y se cerraron en `78cae27`:
R3-DateIndexWrap (rangos de día en vez de `whereDate`; misma semántica, sin
envolver la columna), R2-001 (§8 ya no dice que fallan y que se saltan a la
vez), R2-002 (exit code nulo → -1 en el helper del gate), R2-003 (los cuatro
tests de borde comparten un helper de cancelación). Assess del candidato
corregido: reviewDue false (under_budget), sin segundo ciclo nativo.

Incidente de verificación: otro worktree corría PHPUnit contra el mismo MySQL
(`odontosuite_test` en 3307) y mi corrida quedó bloqueada; la verificación
MySQL se aisló en `odontosuite_test_169` (11/11).
