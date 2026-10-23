# UI: tarifario por sede, bloqueo contable y selector de sede en BI (#304)

Issue #304 (`area:frontend`, `crit:media`, `type:feature`). Rama
`feat/bi-sede-tarifario-bloqueo-ui`, worktree `.worktrees/303-304-bi-sede-ui`,
base `ad29517` (merge de #319). Cierra además S3 de
`bi-filtro-sede-303.md` (selector de sede en BI).

## Specs

S1. #304 alcance 1: «**Bloqueo contable**: pantalla de administración que lista
los cuatro módulos (`cash_transactions`, `cash_movements`, `quotations`,
`treatment_plans`) con la fecha efectiva y su origen (sede, global o sin
bloqueo), permite fijar y retirar la fecha por módulo, y distingue gerencia
(fila global) de administrador de sede (solo su sede).»

S2. #304 alcance 2: «**Tarifario por sede**: en el detalle del catálogo, edición
del precio por sede con ventana de vigencia, mostrando el precio de lista al lado
y el estado (vigente, futuro, vencido).»

S3. #304 alcance 3: «**BI**: selector de sede (solo con alcance de grupo,
alimentado por `GET /api/branches/active`) y bloque del consolidado con su doble
desglose.»

S4. #304 alcance 4: «**Autorización en la interfaz**: claves `can.*` derivadas de
`user.permissions` (`manage.accounting_lock`, `manage.branch_price`,
`view.report`), sin repetir la matriz de roles. En los modales de dinero, el 403
del bloqueo y el 422 del motivo se muestran en línea con la acción de reintento
con motivo.»

S5. #304 criterio 1: «Ninguna superficie es alcanzable sin su permiso: la entrada
de menú y el botón desaparecen cuando falta, y la API sigue respondiendo 403
(defensa doble).»

S6. #304 criterio 2: «Fijar o retirar el bloqueo deja la tabla y el origen
correctos y responde al reload sin recargar la página.»

S7. #304 criterio 3: «El tarifario permite alta, edición y retiro de un precio con
vigencia, y el selector de procedimientos refleja el cambio en la siguiente
lectura.»

S8. #304 criterio 4: «El bloqueo de una edición anterior al cierre se muestra con
el mensaje de la API y el campo de motivo; el motivo enviado se valida a ≥10
caracteres en el cliente y el servidor sigue siendo la autoridad.»

S9. #304 criterio 5: «Todo sobre primitivas del design system (`UiModal`,
`UiDataTable`, `UiSheet`, `UiFilterBar`), sin botones ni modales ad hoc, con
estados de carga, error con reintento y vacío.»

S10. #304 criterio 6: «Cobertura Vitest de las superficies nuevas y de las guardas
de permiso.»

S11. #303 S3: «Selector de sede en la página de BI alimentado por
`GET /api/branches/active` (ya acotado por sede), visible solo cuando el usuario
tiene alcance de grupo.»

## Tasks

- T1 — S1, S5, S6, S9 — módulo `/settings/accounting-locks`: tabla de los cuatro
  módulos con fecha efectiva y origen, fijar y retirar, alcance gerencia/sede.
  Commit: `a18742a`.
- T2 — S2, S7, S9 — tarifario por sede en el detalle del catálogo: alta, edición
  y retiro de una ventana de vigencia con precio de lista y estado. Commit:
  `fbb1d07`.
- T3 — S3, S11, S9 — BI: selector de sede (solo alcance de grupo) y bloque del
  consolidado con doble desglose. Commit: `43afe3a`.
- T4 — S4, S8 — claves `can.*` nuevas (`6f0b693`), guardas de permiso y aviso de
  bloqueo contable en línea con motivo de reintento en los modales de dinero
  (`3a94c43`).
- T5 — S10 — cobertura Vitest de las tres superficies y de las guardas:
  incluida en los commits `a18742a`, `fbb1d07`, `43afe3a` y `3a94c43`.
- T6 — documentación: AGENTS.md §4/§5/§6 y changelog §12. Commit: este mismo
  commit (docs).

## Slices de revisión

Cada superficie entra con su cobertura: `a18742a` (bloqueo contable, 1276
líneas), `6f0b693` (claves compartidas, 21), `fbb1d07` (tarifario, 887),
`43afe3a` (BI, 797) y `3a94c43` (aviso del bloqueo, 699). Los slices de página
superan las ~400 líneas porque incluyen el test que fija su contrato; las dos
issues pueden separarse en PR encadenados por slice si la revisión lo pide.

## Log

L1. Pedido del usuario (verbatim): «haz la issue 303,304 en un worktree nuevo».

L2. Alcance de S4/S8. Los modales que reciben el aviso del bloqueo son las tres
puertas de dinero que la interfaz ya tiene (cobro en caja, movimiento de caja y
presupuesto). El aviso es un componente compartido que muestra el mensaje de la
API y, solo para quien tiene `override.accounting_lock`, el campo de motivo con
el mínimo de 10 caracteres en cliente; el reintento reenvía la misma operación
con `lock_override_reason`. La detección deja de depender del texto: la API
publica el código `accounting_lock_blocked` en el cuerpo del 403.
