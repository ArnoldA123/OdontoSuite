# La matriz escritorio × permiso

Los seis escritorios son la vocabulario que fijó F0 (#280, S3): **recepción,
clínico, asistente, laboratorio, finanzas y administración**. Un escritorio es
el lugar donde ocurre el trabajo, no una unidad de autorización nueva: las rutas
siguen declarando `permission:verbo.recurso` y la matriz se **deriva** del
catálogo `App\Authorization\Permissions`, de modo que no puede separarse de él.

La fuente es `App\Authorization\Desks`; esta página publica la tabla que el
código ya obedece. Dos guardas la sostienen
(`tests/Unit/Authorization/DeskMatrixTest.php`):

1. Cada rol de `users.role` pertenece a exactamente un escritorio.
2. El conjunto de permisos de cada escritorio es exactamente la unión de los
   permisos de sus roles, y todo permiso del catálogo pertenece a al menos uno.

## Quién se sienta dónde

| Escritorio      | Roles                        |
| --------------- | ---------------------------- |
| `recepcion`     | `recepcionista`              |
| `clinico`       | `odontologo`, `implantologo` |
| `asistente`     | `asistente`                  |
| `laboratorio`   | `tecnico_dental`             |
| `finanzas`      | `finanzas`                   |
| `administrador` | `administrador`              |

## Qué hace cada escritorio

La tabla se lee por escritorio y por acto. «Sí» significa que el escritorio
recibe el permiso; el detalle exacto, permiso por permiso, sale de
`Desks::permissions($desk)` y no se copia aquí para que no envejezca.

| Acto                                  | recepción | clínico | asistente | laboratorio | finanzas | administración |
| ------------------------------------- | --------- | ------- | --------- | ----------- | -------- | -------------- |
| Solicitud y reserva de agenda         | sí        | sí      | sí        | sí          | —        | sí             |
| Llegada del paciente                  | sí        | sí      | sí        | sí          | —        | sí             |
| Anuncio al profesional                | sí        | —       | —         | —           | —        | sí             |
| Inicio y cierre de la atención        | —         | sí      | sí        | sí          | —        | sí             |
| Historia clínica y evoluciones        | —         | sí      | sí        | sí          | —        | sí             |
| Registros de especialidad             | —         | sí      | sí        | sí          | —        | sí             |
| Análisis de IA                        | —         | sí      | —         | —           | —        | sí             |
| Planes de tratamiento                 | —         | sí      | —         | sí          | —        | sí             |
| Presupuestos (lectura)                | sí        | sí      | —         | —           | sí       | sí             |
| Presupuestos (escritura)              | —         | sí      | —         | —           | sí       | sí             |
| Caja: apertura, cobro y cierre        | sí        | —       | —         | —           | sí       | sí             |
| Caja: movimientos manuales y reportes | —         | —       | —         | —           | sí       | sí             |
| Anulación de transacción              | —         | —       | —         | —           | —        | sí             |
| Cargo manual al paciente              | sí        | —       | —         | —           | sí       | sí             |
| Estado de cuenta del paciente         | sí        | —       | —         | —           | sí       | sí             |
| Caja: depósito bancario               | sí        | —       | —         | —           | sí       | sí             |
| Catálogo de procedimientos            | —         | lectura | lectura   | lectura     | importa  | sí             |
| Productos (venta)                     | sí        | sí      | sí        | sí          | —        | sí             |
| Inventario (consulta y alertas)       | —         | sí      | sí        | sí          | sí       | sí             |
| Inventario (recepción y ajuste)       | —         | —       | sí        | —           | sí       | sí             |
| Reportes y exportación                | —         | —       | —         | —           | sí       | sí             |
| Usuarios, sedes, configuración        | —         | —       | —         | —           | —        | sí             |

Las rayas no son una decisión de estilo: son la consecuencia del catálogo (#280
S9 y #296 S2). Recepción no escribe historia clínica por ninguna puerta,
incluido el cierre; el escritorio de laboratorio no entra al módulo de análisis
de IA porque un análisis es apoyo a un diagnóstico; y la anulación de una
transacción queda en administración porque es el único acto del ledger que
revierte dinero ya registrado. El cargo manual, su estado de cuenta y el
depósito son trabajo de mostrador y de finanzas (#287): recepción los opera, el
cargo no reemplaza al acto que lo produjo y el depósito sólo mueve efectivo que
la gaveta realmente tiene. El almacén sigue la misma lógica: consultar stock y
alertas es parte del trabajo clínico y financiero, pero recibir por compra y
ajustar por conteo mueve el saldo del que depende el costo del acto, y queda en
asistente, finanzas y administración.

## Sede y escritorio

El escritorio dice **qué** puede hacer un usuario; la sede dice **dónde** (#283
S2, #290 S6). Son dimensiones ortogonales:

- Un usuario con `users.branch_id` asignado opera sólo su sede; sin sede
  asignada tiene alcance de grupo (gerencia). El administrador con sede es el
  administrador de esa sede; el administrador sin sede es gerencia.
- El alcance aplica a la superficie operativa (agenda, sillones, caja, personal,
  inventario). La ficha del paciente y su historia clínica son compartidas entre
  sedes por decisión del dominio (S9): el paciente que se traslada continúa su
  tratamiento sin abrir una segunda historia.

La tabla de permisos de este documento es de escritorio. El alcance por sede se
describe en `odd/tasks/f3-sucursales-283-290.md` (contrato decidido) y lo cubren
`tests/Unit/Authorization/BranchAccessTest.php`,
`tests/Feature/Api/BranchScopeReadGuardTest.php` y
`tests/Feature/Api/BranchScopeWriteGuardTest.php`.
