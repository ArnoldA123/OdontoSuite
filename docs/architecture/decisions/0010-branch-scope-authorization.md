# ADR-0010 — La sede es una dimensión de autorización

- **Status:** Accepted
- **Decided:** 2026-10-22 (issue #283 F3, decisión L2/L3/L4 de
  `odd/tasks/f3-sucursales-283-290.md`)
- **Body recorded:** 2026-10-22 (misma entrega)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

`branch_id` existed in `users`, `patients`, `dental_chairs`, `appointments`,
`cash_register_sessions`, `cash_movements`, `treatment_plans`, `stock_movements`
y `purchase_orders`, y seis controladores lo aceptaban como parámetro de
consulta. Es decir: la sede **filtraba** listados, pero no autorizaba nada. Un
usuario con sede asignada podía leer y escribir la sede de al lado conociendo el
id, y la única barrera era que la interfaz no le mostrara el enlace.

Tres preguntas quedaban abiertas y decidían el modelo:

1. ¿Un usuario pertenece a una sede o rota entre varias?
2. ¿La ficha del paciente es compartida o también se restringe?
3. ¿Quién administra cada sede?

## Decision

**Afiliación única.** `users.branch_id` es la afiliación del usuario a su sede:
un profesional pertenece a la sede donde postuló. `null` significa alcance de
grupo (gerencia). El traslado no habilita nada por sí mismo —no existe estado
pendiente que otorgue acceso— y lo ejecuta un administrador sobre la misma
columna, con auditoría `user_branch_assigned` que guarda el valor anterior y el
nuevo. Un flujo de solicitud y aprobación de traslado es otra entrega: inventar
esa entidad aquí duplicaría un proceso que ninguna de las issues que originan
esta decisión pide.

**Administrador de sede por asignación.** No se crea un octavo rol ni una tabla
de grupos: `administrador` con `branch_id` administra esa sede y `administrador`
sin `branch_id` es gerencia global (consolidado, catálogo de sedes,
transferencias). La autorización sigue siendo una sola matriz
(`App\Authorization\Permissions`), y la sede agrega el "dónde" sin duplicar el
"qué".

**Dos conjuntos, explícitos.** La restricción aplica a lo que tiene sede
operativa: agenda, sillones, caja, personal, inventario. La ficha del paciente
—identidad, historia clínica, evoluciones, adjuntos, registros de especialidad,
atenciones y planes de tratamiento— se lee desde cualquier sede: el paciente que
se traslada a otra ciudad continúa un tratamiento de dos años sin abrir una
segunda historia. `patients.branch_id` queda como sede de origen de la ficha, no
como candado de lectura.

**Tres capas, una regla.** `App\Authorization\BranchAccess` es la única regla
(pura, sin contenedor ni base de datos):

1. Escritura: el trait `App\Models\Concerns\ScopesToBranch` rechaza con 403 toda
   escritura sobre una fila de otra sede y estampa la sede del actor en una fila
   nueva sin sede.
2. Lectura: el mismo trait acota el route-model binding, así que la fila de otra
   sede responde 404 en `show`, `update` y `destroy`, y los listados aplican
   `visibleToBranch()`.
3. HTTP: `EnforceBranchScope` corre en el grupo `api` después de
   `SubstituteBindings` y responde el 403 de un `branch_id` ajeno o de una sede
   ajena en la ruta. Corre como middleware porque los controladores de esta API
   atrapan `\Exception` y responderían 500 a una excepción de autorización.

## Alternatives considered

- **Pivote `user_branches` (varias sedes por usuario).** Es más expresivo y
  habilita la rotación sin traslado, pero duplica la administración de usuarios
  y necesita UI propia; la rotación real que describió el usuario es un traslado
  aprobado, no una pertenencia simultánea. Se puede migrar después sin romper
  esta regla: el pivote pasaría a alimentar `allowedBranchIds()`.
- **Rol `administrador_sede`.** Duplica la matriz de permisos y obliga a revisar
  cada ruta que hoy concede administración; la asignación ya existía y no agrega
  una fuente de verdad.
- **Global scope de Eloquent sobre `Auth::user()`.** Invisible en consola, colas
  y reportes, y difícil de auditar: la restricción se vería como "no hay datos".
  Se eligió el binding + el scope explícito.

## Consequences

- Asignar `branch_id` restringe; dejarlo nulo da alcance de grupo. Es una regla
  que la operación debe conocer al crear usuarios, y la UI de administración de
  usuarios ya expone el campo.
- Las filas históricas con `branch_id` nulo siguen siendo de alcance de grupo;
  un usuario restringido no crea filas nuevas sin sede, porque la creación las
  estampa.
- Leer la ficha de un paciente de otra sede es posible abriéndola por id, no
  enumerando esa sede: el selector `branch_id` de un listado ajeno responde 403.
- Los reportes que aún no aceptan `branch_id` (citas, pacientes, profesionales,
  utilización, dashboard) quedan como follow-up declarado en la entrega de #290.

## References

- Issues #283 (F3) y #290; contrato completo en
  `odd/tasks/f3-sucursales-283-290.md`.
- Matriz de escritorios: `docs/architecture/desks.md`.
- Pruebas: `tests/Unit/Authorization/BranchAccessTest.php`,
  `tests/Feature/Api/BranchScopeReadGuardTest.php`,
  `tests/Feature/Api/BranchScopeWriteGuardTest.php`,
  `tests/Feature/Api/BranchScopeAdministrationTest.php`.
