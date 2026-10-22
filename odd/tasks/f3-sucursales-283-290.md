# F3 + Sucursales — alcance por sede, bloqueo contable, tarifario y reportes (##283, #290)

Issues #283 (`[F3]`, `crit:media`) y #290 (`[Dominio] Sucursales`, `crit:media`).
Rama `feat/f3-sucursales-283-290`, worktree
`.worktrees/feat-f3-sucursales-283-290`, base `59a0a09` (merge de #296). Las dos
comparten el cimiento: #290 punto 1 es exactamente #283 punto 2, así que se
entregan en una sola rama y un solo PR que cierra ambas.

## Specs

S1. #283: «Completar la matriz escritorio x permiso (ver F0) para los 6
escritorios.»

S2. #283: «Alcance por sede en autorización, no solo en filtros de consulta.»

S3. #283: «Un usuario restringido a sede no lee ni escribe datos de otra sede,
cubierto por test.»

S4. #283: «Fecha de bloqueo contable por módulo: nada anterior al cierre se edita
sin autorización registrada.»

S5. #283: «Editar dinero anterior al bloqueo devuelve 403 con registro de
auditoría.»

S6. #290: «Un usuario restringido a sede recibe 403 al operar sobre otra sede,
cubierto por test.»

S7. #290: «Reportes consolidados con drill-down por sede y profesional» y «El
reporte consolidado cuadra con la suma de las sedes.»

S8. #290: «Tarifario por sede o convenio (D7).»

S9. #290: «No hay regla de historia clínica compartida entre sedes.» Se cierra
con una regla explícita, escrita y cubierta por test.

## Tasks

- T1 — S1, S2, S3, S6 — núcleo de autorización por sede y catálogo de
  escritorios (`Desks`, `BranchAccess`, `ScopesToBranch`, ámbitos y guarda de
  escritura) con tests unitarios. Commit: `20965e1`.
- T2 — S2, S3, S6, S9 — aplicación en la superficie real: sedes sólo lectura
  dentro de la sede asignada, administrador de sede, ficha compartida del
  paciente, cambio de sede auditado, matriz 403/404 por endpoint. Commit:
  `a375b20`.
- T3 — S4, S5 — bloqueo contable por módulo y sede con anulación registrada y
  auditada. Commits: `2c5499b` (el bloqueo y su administración) y `1d56bb9`
  (las puertas del dinero y el 403).
- T4 — S8 — tarifario por sede con vigencia y resolución en cascada. Commit:
  `780303a`.
- T5 — S7 — reportes consolidados con desglose por sede y por profesional, con
  cuadre contra el resumen. Commit: `7567301`.
- T6 — S1, S9 — documentación (AGENTS, CREDENTIALS, matriz de escritorios) y
  guardas estáticas. Commit: este mismo commit.
- T7 — verificación: suite MySQL completa, Vitest, Pint, ESLint, Prettier,
  `doc-drift`, revisión nativa y PR.
- T8 — S3, S6 — cierre de los hallazgos de la verificación independiente:
  `Transaction` sin alcance por sede (la sede es la de su cajón), cobros
  pendientes acotados con 403 antes de escribir dinero, lectura del tarifario de
  otra sede y `branch_id` nulo en una escritura respondiendo 403 en lugar de 500.
  Commit: `C7`.

## Contrato decidido

Las cuatro preguntas abiertas se resolvieron con las respuestas del usuario
(L2–L5). Decisiones que el código debe reflejar:

**Afiliación única a sede (L2).** `users.branch_id` es la afiliación: un
profesional pertenece a la sede donde postuló. `branch_id` nulo significa
alcance de grupo (gerencia); con valor, el usuario queda restringido a esa sede.
El traslado no da acceso por sí mismo: no existe estado pendiente que habilite
nada, y el cambio lo ejecuta un administrador sobre la misma columna. Todo
cambio de `users.branch_id` escribe `audit_logs` con `user_branch_assigned`,
valor anterior y valor nuevo, para que «pedir permiso a gerencia» tenga registro.
Un flujo de solicitud/aprobación de traslado es otra entrega (declarado fuera de
alcance): inventarlo aquí duplicaría una entidad que ninguna de las dos issues
pide.

**Administrador de sede (L3).** No se crea un octavo rol ni una tabla de grupos.
`administrador` con `branch_id` es administrador de esa sede; `administrador`
con `branch_id` nulo es gerencia global. Se sostiene con la asignación que ya
existe, sin duplicar la matriz de permisos, y deja la puerta abierta a que
gerencia vea el consolidado (S7) mientras la sede ve sólo la suya.

**Dos conjuntos, explícitos (S9, L4).**

- *Operativo, con sede*: `appointments`, `cash_register_sessions`,
  `cash_movements`, `dental_chairs`, `stock_movements`, `purchase_orders`,
  `users`. Se leen y se escriben sólo dentro de las sedes permitidas. Es donde
  la sede autoriza, no filtra.
- *Ficha del paciente, compartida*: identidad del paciente, historia clínica,
  evoluciones, adjuntos, registros de especialidad, atenciones y planes de
  tratamiento. Se leen desde cualquier sede, porque el paciente que se traslada
  a otra ciudad sigue su tratamiento de dos años sin abrir una segunda historia.
  `patients.branch_id` se conserva como sede de origen (dato de la ficha), no
  como candado de lectura.

La regla se sostiene por el esquema: se restringe lo que tiene `branch_id`
operativo; lo que cuelga del paciente y no tiene sede propia no se restringe.
Las escrituras clínicas siguen limitadas por permiso y por la sede de la cita o
de la atención, que sí está restringida.

**Bloqueo contable (L5; S4, S5).** Tabla `accounting_locks` con
`(module, branch_id)` único, `locked_through` (fecha), `set_by` y auditoría.
Resolución por módulo: fila de la sede → fila global (`branch_id` nulo) → sin
bloqueo. Módulos: `cash_transactions`, `cash_movements`, `quotations`,
`treatment_plans`. El bloqueo protege la **mutación de una fila histórica**
(anular, editar, borrar); crear dinero de hoy nunca queda bloqueado. Sin
autorización registrada, la mutación de una fila con fecha contable ≤
`locked_through` responde **403** y el intento queda en `audit_logs` como
`accounting_lock_blocked`. Con autorización —permiso
`override.accounting_lock`, sólo administrador— la mutación exige un motivo de
al menos 10 caracteres y escribe `accounting_lock_overridden` con módulo,
fecha, entidad, motivo y actor. Administración del bloqueo:
`GET /api/accounting-locks`, `PUT /api/accounting-locks/{module}`,
`DELETE /api/accounting-locks/{module}` con `manage.accounting_lock`
(administrador; el administrador de sede sólo sobre su sede).

**Tarifario por sede (L6; S8).** Tabla `procedure_branch_prices`
(`procedure_catalog_id`, `branch_id`, `price`, `valid_from`, `valid_to`
nullable). Es el tarifario acordado más las campañas por sede: el precio de la
sede gana sobre `procedure_catalog.default_cost`, y dentro de la sede gana la
ventana vigente a la fecha de referencia. Sin fila vigente, el precio es
`default_cost`. Cotizaciones y planes guardan el precio aplicado (snapshot);
nunca lo recalculan. Administración: `GET|PUT /api/branches/{branch}/procedure-prices`
con `manage.branch_price` (administrador, y el de sede sobre la suya).
«Convenio» por pagador queda declarado como follow-up: la issue dice «por sede
o convenio», y la sede cubre el caso descrito (promociones por plaza).

**Reportes consolidados (S7).** Nuevo
`GET /api/reports/consolidated?start_date&end_date&branch_id` (`view.report`)
devuelve `period`, `summary`, `by_branch` y `by_professional`. La suma de cada
desglose cuadra con `summary` porque la población es una sola —pagos completados
menos devoluciones completadas del período— y ambas proyecciones la reparten en
filas excluyentes: los `LEFT JOIN` dejan en «Sin sede» y «Sin cita asociada» el
dinero que no resuelve, en lugar de perderlo. El filtro por sede es el efectivo:
un usuario restringido recibe su sede y pedir otra responde 403. Añadir el mismo
filtro a los otros cinco reportes de BI, y el selector de sede en la página de
BI, queda como follow-up declarado (abajo): la issue pide el consolidado, y
media docena de filtros con semánticas distintas para la ficha compartida del
paciente no se inventan en esta entrega.

**Matriz escritorio × permiso (S1).** Los seis escritorios son los grupos que fijó
F0 (#280, S3): `recepcion`, `clinico`, `asistente`, `laboratorio`, `finanzas`,
`administrador`. `App\Authorization\Desks` los declara como conjuntos de roles y
deriva sus permisos del catálogo; `docs/architecture/desks.md` publica la matriz
y dos guardas la sostienen: todo permiso del catálogo pertenece a al menos un
escritorio, y el conjunto de cada escritorio es exactamente la unión de los
permisos de sus roles.

## Frontera de slices

Un commit por unidad revisable. Los slices C1, C3a y C3b exceden el objetivo de
~400 líneas autoradas a propósito y se declaran aquí: el núcleo de autorización
no se puede partir sin dejar una regla sin dueño a la mitad, y el bloqueo
contable viaja en dos mitades —primero la tabla y su administración, luego las
puertas del dinero— porque es la superficie más delicada de la entrega. La
revisión se hace por unidad, no por el acumulado.

| Slice | Commit | Contenido | Líneas |
| --- | --- | --- | --- |
| C1 | `20965e1` `feat(auth): scope operational data to the assigned branch` | `BranchAccess`, `Desks`, `ScopesToBranch`, `EnforceBranchScope`, binding, matriz y pruebas | 1060 |
| C2 | `a375b20` `feat(branches): keep sede administration and reports inside the assigned branch` | filtros efectivos, `RequireGlobalBranchScope`, auditoría de traslado | 266 |
| C3a | `2c5499b` `feat(cash): freeze a money module through a date, per sede` | `accounting_locks`, modelo, servicio, controlador, rutas, permisos y pruebas de administración | 996 |
| C3b | `1d56bb9` `feat(cash): refuse money mutations inside the freeze and audit the attempt` | guardas en caja, presupuestos y planes, 403 reenviado en cuatro controladores, pruebas HTTP | 979 |
| C4 | `780303a` `feat(catalog): give each sede its own tariff inside a validity window` | `procedure_branch_prices`, resolver, controlador, picker y snapshot | 691 |
| C5 | `7567301` `feat(reports): consolidate revenue per sede and per professional` | servicio consolidado, endpoint y cuadre | 380 |
| C7 | (este commit) | los hallazgos de la verificación independiente: alcance de `Transaction`, cobros pendientes, lectura del tarifario y `branch_id` nulo | ~450 |
| C6 | `0107796` | AGENTS §4/§7/§12, CREDENTIALS, ADR-0010, fixture de permisos y frontera de slices | — |

## Fuera de alcance (declarado)

- Superficie de UI de tarifario, bloqueo contable y filtro por sede en BI: las
  dos issues son `area:backend`. La API queda completa y la UI se documenta como
  follow-up.
- Filtro `branch_id` en los reportes de citas, pacientes, profesionales,
  utilización y dashboard, y corte «Por Sede» dentro del reporte de ingresos: el
  consolidado nuevo cubre el criterio de S7; extender el filtro a los otros cinco
  reportes exige decidir antes cómo se atribuye la ficha compartida del paciente
  (sede de origen vs. sede de atención), decisión que ninguna de las dos issues
  fija.
- Convenio/aseguradora con precios propios (S8 lo nombra como alternativa; se
  entrega la sede).
- Flujo de solicitud y aprobación de traslado entre sedes (L2).
- Recálculo de dinero histórico ya escrito (el bloqueo impide mutarlo; no
  reescribe).

## Log

L1. Usuario: "haz la issue 283 y 290, en worktree nuevo".

L2 (asignación de sede). Usuario: "la 1 y 2 opcines son buenas en si, pero toma
la mejor decision a corde al caso y a futuro tambien, como tal lo que pienso es
que un usuario (profesional entienco osea los roles y todo) esta afiliado a una
sucursal o sede de la clinica y si esta ahi es porque ahi postulo y no para ptra
sede los dela otra sede postulan los profesioanles par dicha sede como tal, pero
a su ves si quiere cambiarse de sede el profesional debe de perdir permiso y un
proceso debe de hacer con gerencia si se aprueba pues el admin debe de poder
cambiarlo, pero es es lo que pieso puedes refutarme en caso como lo veas tu como
ingeniero y artitecto genio" → resuelto como afiliación única con cambio
administrativo auditado (Contrato, L2).

L3 (excepciones). Usuario: "las 3 opciones tiene o veo buenas pero ccomo tal
entiendo esto cuando un paciente va a una clinica es porque ya sabe a donde ir
supongamos sede de trujillo ejemplo y todo su historial esta ahi y ahi va
siempre entonces pues ahi ira simepre com tal, pero en un caso excepcional viaja
a lima y se queda a vivir por 1 años pero esta en un tratamiento de 2 años como
ortodoncia entonces desde ahi continua, ahora eso del aministrador considero que
cada sede debe de tener un administrador-admin no??, refutame y como genio
ingeniero arquitecto senior has lo mejor para la situacion y a futuro tambien"
→ resuelto como administrador de sede por asignación y ficha clínica compartida
(Contrato).

L4 (bloqueo contable). Usuario: "Por módulo y sede".

L5 (tarifario). Usuario: "las opciones 1 y 2 se ven buenas porque si bien puede
haber un precio fijo planilla que se acorda como tal la clinica pero cambpñas o
fechas donde hay descuents y todo y que es por sede por ejemplo si una sede esta
bajo de pacientes y ello para acaparar mas hay promociones, ofertas y descuentos
que un otra sede o otra ciudad no hay porque esta bien de ganancias, pero com otal
refutame y saca a resucir tu como ingeniero y arquitecto senior y eleje lo mejor
para esta situacion y a futuro tambien" → resuelto como precio por sede con
ventana de vigencia y snapshot (Contrato).

L6. Worktree y verificación: `composer install` y `pnpm install` en el worktree
(`vendor` symlink apuntaría `baseDir` al árbol principal), base MySQL
`odontosuite_f3_sedes_test`. Línea base medida antes de tocar código:
`CashRegisterCurrentSessionScopeTest` en verde (4 tests, 9 assertions).

L7 (hallazgo de arquitectura). Los controladores de esta API envuelven su cuerpo
en `try { … } catch (\Exception) { return 500 }`, así que una excepción de
autorización lanzada dentro del controlador se responde 500, no 403. Por eso el
alcance por sede se aplica en middleware (`EnforceBranchScope`) y el catálogo de
sedes en `RequireGlobalBranchScope`; y por eso las cuatro puertas de dinero con
`catch` genérico reenvían `AuthorizationException`. El worker de T3 detectó el
caso en presupuestos y planes, que estaban fuera de su superficie; la decisión
fue aplicar el reenvío en este carril (su opción 3) y cubrirlo con
`AccountingLockHttpGuardTest` (403, fila intacta, auditoría).

L9 (verificación independiente). `gentle-ai-verify` sobre `59a0a09..HEAD`
devolvió S1/S9/S4/S5/S7 PASS, S2/S3/S6 y S8 con hallazgos. Bloqueante: las
transacciones no tienen `branch_id` y el modelo no usaba el alcance, así que un
usuario de sede podía leer y actualizar por id una transacción de otra sede. No
bloqueantes: los cobros pendientes listaban todas las sedes y cobrar una cita
ajena respondía 500 después de escribir la transacción; la lectura del tarifario
de otra sede no se rechazaba (el middleware sólo miraba parámetros de ruta en
escrituras); `branch_id: null` en una escritura caía en el guard del modelo y
respondía 500; y el controlador del consolidado atrapaba `\Throwable` sin
reenviar la autorización. Cerrado todo en C7: la sede de una transacción es la de
su cajón (scope + 404 en lectura, 403 en escritura), los cobros pendientes se
acotan por sede y se rechazan antes de crear dinero, el middleware afirma la
`{branch}` de ruta en cualquier método y los `PUT/PATCH` con `branch_id` nulo
responden 403 (en `POST` ese valor significa «sin sede» y se estampa la del
actor). `BranchScopeCashSurfaceTest` lo fija (8 tests).

L8 (verificación medida). Suite MySQL completa: `OK (2320 tests, 13676
assertions)` —2257 de línea base más 63 de la entrega—. `vendor/bin/pint --test`
en verde sobre 731 archivos. `node scripts/audit/doc-drift.mjs`: 14 de 14
comprobaciones. `ArchitectureDecisionsIndexTest` en verde con el ADR 0010.
