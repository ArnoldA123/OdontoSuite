# Caja: imputación de pagos, gaveta histórica y concurrencia (#321-#323)

Feature branch `feat/caja-followups-321-323`, worktree
`.worktrees/321-323-caja`, base `ad29517`. Las tres issues son
`area:backend`/`area:data` y comparten la consecuencia declarada de #287.

## Specs

S1 (#321). Un pago puede cubrir uno o varios cargos, y la suma de sus
asignaciones es menor o igual a su importe.

S2 (#321). El total del estado de cuenta no cambia respecto de #287: la
imputación es una vista, no una segunda contabilidad.

S3 (#321). Los datos previos se leen como no imputados, sin backfill
destructivo.

S4 (#321). Test que fije la suma y el saldo por cargo.

S5 (#322). La decisión queda registrada con su alcance: sesiones abiertas,
cerradas o ambas.

S6 (#322). Si se repara: comando idempotente con `--dry-run`, conteos y tests;
las sesiones cerradas no se reescriben sin decisión explícita. Decisión del
usuario (L2): se reparan ambas, y «no se reescriben» se cumple no tocando las
columnas de la sesión (`expected_amount`, `closing_amount`,
`difference_amount`, `status`, `arqueo_data`), sólo marcando sus movimientos.

S7 (#323). Dos depósitos o devoluciones concurrentes de la misma sesión no
dejan el esperado negativo ni retiran más efectivo del que hay.

S8 (#323). Un depósito en un período bloqueado responde 403 y deja la traza;
con override de administrador se registra.

S9 (#323). Los tests cubren ambos lados, bloqueado y permitido.

## Tasks

- [x] T1 — #321: tabla `patient_charge_allocations`, modelo, servicio con las
      dos reglas de suma, puertas de escritura (alta del pago y pago ya
      existente), vista por cargo en el estado de cuenta. S1-S4.
      Commit `9e08b6f`. Evidencia: `PatientChargeAllocationTest` (14 tests, 75
      aserciones) verde en MySQL.
- [x] T2 — #322: comando `cash:repair-electronic-movements` (`--dry-run`,
      idempotente) que marca los movimientos de gaveta ligados a métodos
      electrónicos, sin reescribir las sesiones. S5-S6.
      Commit `c8bf972`. Evidencia: `RepairElectronicCashMovementsCommandTest`
      (6 tests, 74 aserciones) verde en MySQL.
- [x] T3 — #323: lock de la sesión en pago y devolución (y en el cierre),
      bloqueo contable del depósito con override registrado. S7-S9.
      Commit `49d33fd`. Evidencia: `CashDrawerLockTest` (4) +
      `CashDepositAccountingLockTest` (5) verdes en el run combinado
      `PatientChargeAllocationTest|CashDrawerLockTest|CashDepositAccountingLockTest`
      (22 tests, 116 aserciones).
- [x] T4 — Documentación (ADR-0018, ADR-0015, AGENTS.md §4/§6/§9/§11/§12) y
      verificación final. Evidencia: suite MySQL completa `OK (2694 tests, 16297
      assertions)`, Pint `--test` (859 archivos), Prettier `--check` y
      `node scripts/audit/doc-drift.mjs` (14/14). Commit propio.

- [x] T5 — Seguimientos de la verificación independiente (S1, S2, S7): el tope
      de un cargo descuenta sólo imputaciones de pagos `completed`; el reparto
      proporcional de una devolución se hace en centavos (mayor residuo); la
      anulación toma el lock de la sesión. Commit `4d79689`. Evidencia: RED
      con 3 fallos (21 tests) y después `PatientChargeAllocationTest` (16) +
      `CashDrawerLockTest` (5) verdes, más la suite completa
      `OK (2697 tests, 16319 assertions)`.

### Registro de la rebanada

T1 quedó en un commit de 1044 líneas (445 son su archivo de test). Es una sola
conducta —el esquema, las dos puertas, los dos topes y la vista— y partirla
entre «escritura» y «lectura» obligaría a duplicar el fixture del ledger y a
editar dos veces el mismo archivo de test. T2 (≈330) y T3 (≈300) quedan por
debajo de la rebanada de 400. Riesgo del diff: T1 toca datos y contratos
(tabla nueva + columnas nuevas en el payload del pago) y por eso tiene su
propio commit, igual que T3 (concurrencia) y T2 (datos históricos).

## Log

L1. Usuario: «haz la issue 321,322,323 en un worktree nuevo».

L2. Decisión del usuario sobre #322: reparar todas, abiertas y cerradas. Los
arqueos guardados no se reescriben; los movimientos electrónicos se marcan
(soft delete) y la traza queda en `audit_logs`.

L3. Entorno: worktree `.worktrees/321-323-caja`, rama
`feat/caja-followups-321-323`, base `ad29517`. Base de test propia
`odontosuite_321_323_test` (MariaDB de XAMPP por socket). Harness MySQL
verificado con `CashDepositTest` (13 verdes).

L4. T2 se delegó a `gentle-ai-worker` (unidad independiente y pesada, regla de
writer). Devolvió comando y test con `php -l` y Pint verdes y la suite MySQL
bloqueada por el defecto de L5; el padre corrió su archivo: 6 tests, 74
aserciones, verdes.

L5. Incidentes de entorno. (a) La primera versión de la migración de T1 usaba
el nombre autogenerado del índice único
(`patient_charge_allocations_patient_charge_id_transaction_id_unique`, 66
caracteres), que MySQL rechaza por el tope de 64; `migrate:fresh` fallaba y
`RefreshDatabase` reintentaba la migración en cada test, lo que hacía parecer
que la suite «se colgaba». Se corrigió con un nombre explícito
(`patient_charge_allocation_unique`). (b) El motor MariaDB lo comparten otras
tres sesiones de agente con `migrate:fresh` concurrente, así que las corridas
se hicieron de a una. (c) El padre ejecutó un `pkill -f "phpunit -c
phpunit.mysql.xml"` para despejar sus propios procesos colgados y ese patrón
alcanzó las corridas de dos sesiones vecinas; el incidente queda declarado: no
se repite, y los runs propios se detienen por `job_stop`/PID exacto.

L6. Verificación independiente (`gentle-ai-verify`, read-only), tras escalar el
item 1 (datos): veredicto «listo con seguimientos», sin bloqueantes, sobre 56 +
76 tests verdes y los guards de documentación. Sus tres seguimientos Low se
reprodujeron con RED antes de corregirlos (21 tests, 3 fallos: el tope del cargo
seguía reservado por un pago anulado, la cobertura podía quedar en `-0.01` al
centavo, y la anulación no tomaba el lock de la sesión) y se cerraron en T5. Los
dos avisos informativos: el conteo de tests de `AGENTS.md` §12 (corregido) y que
`main` avanzó a `53da232` (#324); el merge sólo solapa la entrada de changelog
de §12 y se resolverá conservando ambas.
