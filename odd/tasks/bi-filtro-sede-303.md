# BI: filtro por sede en los reportes restantes y corte «Por Sede» (#303)

Issue #303 (`area:backend`, `crit:media`, `type:feature`). Rama
`feat/bi-sede-tarifario-bloqueo-ui`, worktree `.worktrees/303-304-bi-sede-ui`,
base `ad29517` (merge de #319). Se entrega junto con #304 (`ui-tarifario-bloqueo-bi-304.md`),
que aporta el selector de sede de la página de BI.

## Specs

S1. #303 alcance 1: «Aceptar `branch_id` en los seis endpoints de reporte y en la
exportación, resuelto con `App\Authorization\BranchAccess::effectiveFilter` (misma
regla que el consolidado: sede propia por omisión, 403 al pedir otra).»

S2. #303 alcance 2: «Añadir el corte «Por Sede» al reporte de ingresos, con la
misma invariante de cuadre: cada movimiento cae en exactamente una fila y la suma
coincide con el resumen.»

S3. #303 alcance 3: «Selector de sede en la página de BI alimentado por
`GET /api/branches/active` (ya acotado por sede), visible solo cuando el usuario
tiene alcance de grupo.»

S4. #303 alcance 4: «**Decisión previa obligatoria**: cómo se atribuye la ficha
compartida del paciente en los reportes de pacientes y de utilización (sede de
origen del paciente vs. sede donde ocurrió la atención). El reporte de pacientes
hoy no tiene una dimensión de sede natural; decidirla y escribirla antes de
filtrar.»

S5. #303 criterio 1: «`GET /api/reports/appointments`, `patients`,
`professionals`, `revenue`, `utilization` y `dashboard` aceptan `branch_id`; un
usuario restringido recibe su sede y pedir otra responde 403, cubierto por
test.»

S6. #303 criterio 2: «La exportación (`GET /api/reports/{reportType}/export`)
respeta el mismo filtro.»

S7. #303 criterio 3: «En el reporte de ingresos, la suma del corte «Por Sede»
coincide con el total del período, cubierto por test, con dinero sin sede
resuelto en una fila propia.»

S8. #303 criterio 4: «La decisión de atribución de la ficha compartida queda
escrita en el documento de la entrega antes del primer commit de filtros.»

## Tasks

- T1 — S4, S8 — decisión de atribución escrita en este documento (Log L2) y
  anotada en ADR-0010. Commit: este mismo commit (docs).
- T2 — S1, S5, S6 — `branch_id` en los seis endpoints y en la exportación,
  resuelto con `BranchAccess::effectiveFilter` en el controlador y aplicado por
  cada servicio. Commit: `1ff9ec8`.
- T3 — S2, S7 — corte «Por Sede» del reporte de ingresos con fila «Sin sede».
  Commit: `1ff9ec8`.
- T4 — S1, S2, S5, S6, S7 — tests de contrato del filtro, del 403 y del cuadre.
  Commit: `1ff9ec8`.
- T5 — S3 — selector de sede en la página de BI; se entrega como T3 de
  `ui-tarifario-bloqueo-bi-304.md` para no duplicar la superficie. Commit:
  `43afe3a`.

## Slices de revisión

El backend de #303 entra en un solo commit (`1ff9ec8`, 797 líneas con tests):
el filtro y el corte «Por Sede» comparten `RevenueReportService` y su test de
contrato, así que separarlos exigía partir hunks del mismo archivo y dejaba el
corte sin la cobertura que lo fija. El resto de la entrega va en slices por
superficie: `a18742a` (bloqueo contable), `6f0b693` (claves de autorización),
`fbb1d07` (tarifario), `43afe3a` (BI) y `3a94c43` (aviso del bloqueo).

## Log

L1. Pedido del usuario (verbatim): «haz la issue 303,304 en un worktree nuevo».

L2. Decisión de atribución (S4/S8), tomada antes del primer commit de filtros.
La ficha del paciente es compartida entre sedes (ADR-0010), así que un reporte de
periodo por sede no se atribuye por la sede de origen del paciente sino por la
**sede donde ocurrió la atención**:

- **Pacientes**: la población filtrada son los pacientes con al menos una cita
  en la sede dentro del periodo (la misma población que ya cuenta
  `total_appointments`), y el dinero de la fila es el cobrado en la sede (cajón
  de la sede, la atribución única de ADR-0010/#287). Un paciente atendido en dos
  sedes aparece en las dos, con las cifras de cada una: es un reporte de
  actividad por sede, no un censo. Sin filtro (alcance de grupo) la población y
  las cifras no cambian.
- **Utilización**: el sillón pertenece a la sede (`dental_chairs.branch_id`), así
  que la población filtrada son los sillones de la sede y sus agregados quedan
  acotados por construcción.
- **Profesionales y dashboard**: la población filtrada son los profesionales con
  actividad en la sede en el periodo, y sus contadores y su dinero salen de las
  citas de esa sede.
- **Ingresos**: el dinero se atribuye por la sede del cajón
  (`cash_register_sessions.branch_id`), que es la única dimensión de sede que el
  dinero tiene; el corte «Por Sede» agrupa por esa misma clave y el dinero sin
  cajón cae en la fila `Sin sede`.
- **Citas**: por `appointments.branch_id`; una cita tiene una sola sede.

Alternativa rechazada: atribuir la población de pacientes por
`patients.branch_id` (sede de origen). Produciría filas que mezclan visitas y
cobros de otras sedes bajo la sede de registro, y dejaría fuera el paciente
atendido en la sede sin haberse registrado allí. La sede de origen sigue siendo
el valor con el que se estampa la ficha al crearla, no la clave de un reporte de
periodo.
