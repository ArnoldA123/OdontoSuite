# Caja: vocabulario del dinero y estado de cuenta del paciente (#287)

Issue #287 (`[Dominio] Caja: vocabulario del dinero y estado de cuenta del
paciente`, `crit:alta`, `area:backend`). Rama `feat/cash-money-ledger`, worktree
`.worktrees/287-caja-dinero`, base `main` @ `2a9f053`.

## Specs

S1. #287: «Vocabulario del dinero explícito y separado: pago, descuento,
condonación, devolución, ajuste.»

S2. #287: «No existe condonación (write-off) como concepto propio: el descuento
es del presupuesto, no del cobro.»

S3. #287: «Estado de cuenta por paciente (cargos, pagos, ajustes, saldo),
consultable desde recepción.»

S4. #287: «No hay estado de cuenta del paciente: el saldo se deriva de
`transactions` contra `appointments.final_amount`.»

S5. #287: «Devolución con autorización y registro.»

S6. #287: «No hay devolución con flujo (saldo a favor, autorización, registro).»

S7. #287: «Conciliación diaria por método y depósito.»

S8. #287: «La condonación no cuenta como ingreso ni como cobro.»

S9. #287: «El estado de cuenta cuadra contra las transacciones del período,
cubierto por test.»

S10. #287: «No hay bloqueo contable ni conciliación por método con depósito.»
El bloqueo contable ya lo cerró F3 (#283/#290); esta issue cierra la
conciliación.

## Decisiones de contrato

D1. **El cargo es una fila propia** (`patient_charges`, decisión del usuario):
`source = encounter|manual`. El cierre de la atención escribe exactamente un
cargo por acto (índice único sobre `clinical_encounter_id`, importe =
`final_amount`, fecha = `closed_at`). El cargo manual tiene su propia puerta.
El libro es inmutable: un cargo equivocado se corrige con un ajuste, nunca se
edita ni se borra.

D2. **La devolución la ejecuta caja con autorización registrada** (decisión del
usuario): `authorized_by` (debe ser `administrador`) + `authorization_reason`.
Mismo patrón que el descuento >10%. Si el actor es administrador y no declara
autorizador, se autoriza a sí mismo.

D3. **Saldo del paciente**:
`cargos − descuentos − pagos + devoluciones − condonaciones + ajustes`.
El ajuste es firmado (positivo sube la deuda, negativo la baja); el resto de
importes es positivo. El descuento es un campo del cobro
(`discount_amount`/`discount_type`), no una transacción: reduce lo que el
paciente debe pagar y no es plata que se mueva.

D4. **Sólo pago y devolución mueven la gaveta.** `write_off` (condonación) y
`adjustment` (ajuste) son asientos de ledger: no exigen método de pago, no
exigen caja abierta, no escriben `cash_movements` y no cuentan como ingreso ni
como cobro. El pago es `income`; la devolución es `expense`.

D5. **El depósito bancario es una salida de gaveta** (`cash_deposits`) con su
`CashMovement` `withdrawal` en la misma transacción. La fila del depósito
guarda la referencia bancaria; el movimiento mantiene verdadero el esperado de
la sesión y el arqueo.

D5b. **Sólo el efectivo escribe movimiento de gaveta.** Un cobro o una
devolución con tarjeta, transferencia o billetera se registra en
`transactions` (y entra al mix y al estado de cuenta) pero no crea
`CashMovement`: si lo creara, `expected_amount` contaría dinero que nunca
estuvo en el cajón y el depósito podría retirar efectivo inexistente. Es la
regla que hace que la gaveta y el depósito puedan cuadrar por método.

D6. **El estado de cuenta es del paciente** (la ficha es compartida, ADR-0010);
la gaveta, sus movimientos y la conciliación siguen acotados por sede.

D7. **La guarda `cash.session` deja de estar en las rutas de creación y
anulación de transacciones:** sólo pago y devolución exigen caja abierta, y la
regla vive en el servicio. Un `write_off`/`adjustment` no puede exigir una
gaveta abierta sin mentir; una anulación sigue exigiendo que la sesión de la
transacción esté abierta.

D8. **Vocabulario cerrado en `App\Support\MoneyVocabulary`**: es la única
fuente de los tipos, de su efecto en el saldo y de si mueven efectivo. La API
acepta `payment|refund|write_off|adjustment`; `discount` deja de ser un tipo de
transacción (el descuento vive en el cobro) y `income|expense` quedan como
valores legacy de la columna, sin puerta de escritura.

## Tasks

- T1 — S1, S2, S8 — `MoneyVocabulary`; migración (`write_off` en el enum,
  `payment_method_id` nullable, `authorized_by`, `authorization_reason`,
  `parent_transaction_id`); reglas por tipo en `TransactionService` (incluye el
  tope de devolución; el tope de condonación necesita el ledger de cargos y
  entra en T3); `StoreTransactionRequest` condicional; ruta `POST
  /transactions` sin `cash.session`; tests de vocabulario. Commit: `feat(287):
  separate the money vocabulary from the drawer`.
- T2 — S3, S4 — `patient_charges` (modelo, migración, servicio), escritura
  única en `ConsultationService::close()`, puerta manual
  `POST /api/patient-charges`, comando idempotente `charges:backfill`; tests.
  Commit: `feat(287): record the patient charge ledger`.
- T3 — S3, S6, S9 — `PatientStatementService` + `GET
  /api/patients/{patient}/statement` (`view.patient_statement`); saldo de
  apertura/cierre, entradas cronológicas, resumen y reconciliación por tipo;
  tope de condonación contra el saldo pendiente; tests. Commit: `feat(287):
  expose the patient statement`.
- T4 — S5, S7 — `cash_deposits` + servicio + `POST|GET
  /api/cash-register/deposits`; reporte diario con depósitos y pendiente; neteo
  de devoluciones en los agregados por método; gaveta sólo-efectivo (D5b);
  tests. La devolución con autorización y tope ya quedó en T1. Commit:
  `feat(287): reconcile the day by method and deposit`.
- T5 — S1-S10 — verificación: suite MySQL enfocada y completa, Pint, Prettier,
  `doc-drift`, fixture de permisos, AGENTS.md §4/§6/§12, ADR-0015 e índice;
  revisión independiente. Commit: `docs(287): record the money contract`.

## Contrato técnico

### Tipo de transacción (T1)

| tipo | método | caja abierta | movimiento | autorización | saldo |
| --- | --- | --- | --- | --- | --- |
| `payment` | sí | sí | `income` | no | `−amount` (el `discount_amount` resta aparte) |
| `refund` | sí | sí | `expense` | admin + motivo | `+amount` |
| `write_off` | no | no | no | admin + motivo | `−amount` |
| `adjustment` | no | no | no | admin + motivo | `+amount` firmado |
| `discount` | — | — | — | — | campo del cobro: `−discount_amount` |

- `write_off` ≤ saldo pendiente del paciente (sin él, la condonación inventa
  un perdón mayor que la deuda). El tope entra en T3, cuando existe el estado
  de cuenta que lo calcula; T1 sólo valida vocabulario, autorización y motivo.
- `refund` ≤ dinero cobrado no devuelto del paciente; con
  `parent_transaction_id` (pago completado del mismo paciente) además ≤ lo
  devolvible de ese pago.
- `adjustment` no admite `0` ni `discount_*`; `amount` puede ser negativo.
- La devolución crea un `CashMovement` `expense`; el pago uno `income`; el
  resto no crea movimiento.
- `void` sigue siendo sólo administrador y sólo revierte el movimiento que
  exista.

### `patient_charges` (T2)

`patient_id`, `clinical_encounter_id` (nullable, único), `source`
(`encounter|manual`), `description`, `amount` decimal(12,2), `charged_at`,
`created_by`, `notes`, timestamps; índice `(patient_id, charged_at)`. Un
`final_amount <= 0` no escribe cargo. El comando `charges:backfill`
(`--dry-run`) cubre las atenciones cerradas históricas y es idempotente.

La descripción de un cargo de acto sale del acto: nombre del tipo de cita o,
si no hay cita, una etiqueta del `mode` (`Consulta`, `Ejecución`, `Sesión de
plan`, `Urgencia`); nunca queda vacía. La puerta manual es `POST
/api/patient-charges` (`manage.patient_charge`, `CASH_ROLES`) con
`patient_id`, `amount > 0`, `description`, `charged_at` (hoy o antes) y
`notes`; el cargo nace `source = manual`. El libro no se edita ni se borra: la
corrección es un ajuste.

### Slices de entrega

- S1 — `e35c869` — vocabulario del dinero, esquema de `transactions` y reglas
  por tipo (T1). Supera la guía de ~400 líneas porque el esquema, el servicio,
  el request y sus tests son una sola unidad: un commit intermedio no
  compilaría. Límite registrado.
- S2 — `6d0899f` — libro de cargos `patient_charges`, escritor del cierre,
  puerta manual y `charges:backfill` (T2), con la guarda de inmutabilidad del
  modelo. También supera la guía: la tabla, el modelo, el servicio, el cierre y
  sus tests no se pueden separar sin dejar el acto sin cargo. Límite
  registrado.
- S3 — `fa50b98` — estado de cuenta del paciente y tope de condonación (T3).
  Supera la guía por los mismos tests de punta a punta; el servicio, la ruta y
  el tope no se pueden partir sin dejar el libro sin lectura. Límite registrado.
- S4 — `ea78a4d` — depósitos, conciliación diaria, neteo de devoluciones y
  gaveta sólo-efectivo (T4). Supera la guía: tabla, servicio, reporte y tests
  forman una unidad; sin la regla de efectivo el depósito no puede cuadrar.
  Límite registrado.
- S5 — `a90ffd3` — merge de `origin/main` (#285/#286): ADR del dinero
  renumerada a 0015 (0014 es la firma clínica), conteos de AGENTS.md medidos
  sobre el árbol fusionado, fixture de permisos regenerado y fixture del cierre
  con diagnóstico y firma de #286.

### Estado de cuenta (T3)

`GET /api/patients/{patient}/statement?date_from=&date_to=` (default: primer
día del mes actual → hoy). Devuelve `patient`, `period`, `opening_balance`,
`closing_balance`, `summary` (charges, discounts, payments, refunds,
write_offs, adjustments, collected = payments − refunds, forgiven, balance) y
`entries` cronológicas con `kind`, `date`, `reference`, `description`,
`amount`, `effect`, `balance`, `source_id`. Sólo transacciones `completed`;
las anuladas no cuentan. El saldo de apertura resume todo lo anterior a
`date_from`; el de cierre, apertura + efectos del período.

### Depósitos y conciliación (T4)

`cash_deposits`: `branch_id`, `cash_register_session_id`, `cash_movement_id`,
`created_by`, `amount` decimal(12,2), `deposited_at`, `reference`, `notes`,
timestamps. Reglas: sesión abierta (el depósito se registra antes de cerrar la
gaveta), monto > 0 y ≤ efectivo disponible de la gaveta (`getSessionSummary`),
referencia bancaria obligatoria. `GET /api/cash-register/deposits` pagina con
`per_page` (1..100, default 15) y filtra por rango y sede efectiva.

`GET /api/cash-reports/daily` añade `reconciliation.deposits.{count,total}` y
`reconciliation.pending_deposit = cash.total − deposits.total`. Las
condonaciones y los ajustes no entran a la población de la gaveta (no tienen
sesión ni método): su prueba es el estado de cuenta y su ausencia de
`total_income`/`total_expenses`. Los agregados por método
(`summary_by_payment_method`, `payment_methods_analysis`,
`transactions_total`, `summary_by_hour`, `top_transactions`) netean
devoluciones y excluyen los asientos sin efectivo.

### Permisos

`view.patient_statement`, `manage.patient_charge`, `manage.cash_deposit`, los
tres con `CASH_ROLES` (administrador, finanzas, recepcionista). El listado de
depósitos usa `view.cash_register`. Regenerar
`tests/js/support/role-permissions.json` con
`php scripts/export-permissions-fixture.php`.

## Evidence

- T1 (`e35c869`): vocabulario del dinero y reglas por tipo — 130 tests, 298
  assertions, verdes en MySQL.
- T2 (`6d0899f`): ledger de cargos, escritor del cierre, puerta manual y
  `charges:backfill` — 105 tests, verdes en MySQL.
- T3 (`fa50b98`): estado de cuenta y tope de condonación — 377 tests, verdes en
  MySQL.
- T4 (`ea78a4d`): depósitos, conciliación diaria y gaveta sólo-efectivo — 435
  tests, verdes en MySQL.

Verificación independiente (`gentle-ai-verify`, read-only): veredicto «listo
para fusionar con seguimientos», sin bloqueantes. Sus seguimientos quedaron
cerrados así: F-1 (las filas históricas de tipo `discount` se leen como línea
de descuento del estado de cuenta) en `15546fe`; F-2 a F-5 (padre de devolución
inválido, devolución no-efectiva sin movimiento, autorización de condonación y
ajuste contra un no-administrador, descuento prohibido fuera del pago y los
tres permisos anclados a la caja) en `7edc365`; F-6 (ventana depósito ↔
devolución y depósito retrocedido frente al bloqueo contable) declarada en
ADR-0015.

Suite completa MySQL sobre el árbol fusionado con `origin/main`: `OK (2665
tests, 16087 assertions)`. Pint (831 archivos), Prettier y `doc-drift` (14/14) verdes.

Declarado fuera de alcance:

- La UI de caja para devoluciones, depósitos y estado de cuenta (la issue es
  `area:backend`).
- La imputación de un pago a un cargo concreto: el saldo es del paciente y la
  corrección de un cargo va por ajuste.
- Los movimientos de gaveta electrónicos escritos antes de la regla
  sólo-efectivo siguen contando en las sesiones antiguas; el ledger no se
  reescribe.

## Log

L1. Usuario: «haz la issue 287, en worktree nuevo».

L2. Decisiones del usuario: los cargos son un libro propio
(`patient_charges`, origen acto o manual) y la devolución la ejecuta caja con
la autorización registrada de un administrador (`authorized_by` + motivo),
mismo patrón que el descuento >10%.

L3. Entorno: worktree `.worktrees/287-caja-dinero`, rama
`feat/cash-money-ledger`, base `2a9f053`. Base de test propia
`odontosuite_287_test` (MariaDB de XAMPP por socket). Harness MySQL verificado
con `CashLedgerIntegrityTest` (5 verdes).

L4. Verificación independiente: veredicto «listo para fusionar con
seguimientos», sin bloqueantes; sus seguimientos F-1 a F-6 se cerraron en los
commits de corrección, tests y documentos antes del PR.

L5. La suite completa MySQL, Pint, Prettier y `doc-drift` se corren sobre el
árbol comprometido antes de publicar el PR.

L6. PR [#319](https://github.com/ArnoldA123/OdontoSuite/pull/319) abierto desde
`feat/cash-money-ledger` hacia `main`; la fusión queda en manos del usuario.

L7. `origin/main` avanzó con #285 y #286; el merge `a90ffd3` reconcilia ambos
sin conflictos de comportamiento: ADR-0015, conteos medidos, fixture de
permisos y payload de cierre actualizados. Suite fusionada: 2665 tests,
16087 assertions.
