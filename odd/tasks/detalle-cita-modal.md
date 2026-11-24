# Detalle de cita — modal con siguiente paso y un color por estado

Diseño aprobado en `design/detalle-cita/`; tablero elegido
`01-modal-siguiente-paso.html` (los otros dos son alternativas: la página
`02-pagina-linea-de-vida.html` y el tablero de estados `03-estados-y-movil.html`).

Pedido del usuario: al abrir una cita ya creada hay hasta seis botones
amontonados, no se distingue en qué estado está y cuatro estados comparten el
mismo verde del acento (`scheduled`, `confirmed`, `arrived`, `rescheduled`). El
detalle debe decir el estado, el siguiente paso y ofrecer **una** acción
primaria.

## Specs

S1. El detalle de la cita muestra **una** acción primaria, derivada del estado;
las secundarias quedan como enlaces o en un menú, y cancelar/eliminar viven en
el menú con confirmación, nunca al lado del primario.

S2. Un rail de cinco etapas (Programada → Confirmada → En espera → En consulta →
Completada) comunica la posición. Un desenlace (`cancelled`, `no_show`,
`rescheduled`) sustituye el rail por una franja con su píldora. Las horas de cada
etapa salen solo de campos publicados por la API (`created_at`,
`checked_in_at`, `completed_at`); si faltan, se omite la hora, nunca se inventa.

S3. Cada estado tiene identidad propia con tokens de `design/system.md`:
Programada gris, Confirmada acero (`info`), En espera índigo, En consulta ámbar,
Completada verde, Cancelada rojo, No se presentó naranja, Reprogramada violeta.
El relleno es tenue en todos (paso `50`) y **sube a `100` solo en los dos estados
que piden acción** (`arrived`, `in_consultation`), que además pulsan su punto; el
resto no pulsa. El verde de acento queda reservado para la acción primaria.
`in_progress` (legacy) se presenta igual que `in_consultation`.

S4. Se conservan los ganchos que la auditoría mide (`data-calendar-action`:
`status-confirmed`, `check-in`, `announce`, `my-day`, `status-cancelled`, `edit`,
`delete`) y todas las puertas de permiso actuales. No se agregan rutas, permisos
ni endpoints: «Iniciar atención» usa el `POST /appointments/{id}/start-attention`
que ya existe, solo con `start.consultation` y sin montar el asistente de cierre.
La guarda de U3 se ajusta a esa frontera: la agenda puede iniciar la atención,
nunca abrir la consulta clínica.

S5. **Una transición por estado**, sin dos fases en la misma pantalla:
`scheduled` → Confirmar cita (única acción primaria, sin check-in anticipado);
`confirmed` → Registrar llegada; `arrived` → Iniciar atención con
`start.consultation` (si no lo tiene, Anunciar paciente), con Ir a mi jornada
como enlace; `in_consultation` → sin primario, Ir a mi jornada como enlace (el
cierre clínico vive allí); `completed` y desenlaces → sin primario, el pie
conserva Editar/Reagendar según permiso.

S6. El payload de la cita publica `branch` y `procedure` (solo cuando vienen
precargados) para que el detalle muestre sede y procedimiento sin consultas
perezosas por fila; si no llegan, las filas se omiten. Sin migración: las
columnas ya existen.

## Tasks

- T1 — S3 — `appointmentStatus.js`: paleta única por estado, `stage` del rail,
  desenlaces, alias de `in_progress`; `StatusBadge.vue` suma las variantes que
  faltan (`indigo`, `purple`, `orange`) sin tocar las cinco existentes.
- T2 — S1, S2, S4, S5 — `AppointmentStageRail.vue` presentacional y
  `CalendarPage.vue`: cabecera con hora + sillón + tipo, rail, píldora, tarjeta
  «Ahora» con el primario, datos, menú de secundarias y destructivas. El rail
  recorta los conectores 14 px en cada extremo, no usa halo ni pulso en el nodo
  y fija el alto de la etiqueta para que las horas queden alineadas.
- T3 — pruebas — `tests/js/unit/appointmentStatus.test.js` y
  `tests/js/smoke/appointments.test.js`: una sola acción primaria por estado,
  desenlaces sin rail, ganchos y permisos intactos, sin horas inventadas.
- T4 — verificación — Vitest de foco, guardas de design system de la superficie,
  ESLint y Prettier en los archivos tocados.
- T5 — S6 — `Appointment::branch()`, `branch` y `procedure` en
  `AppointmentResource` con `whenLoaded`, ambas relaciones en el `with` del
  índice y del `show`, y las dos filas en el modal. Test de contrato del payload:
  presentes cuando están cargadas, ausentes cuando no.

## Log

L1 — Arnold: «no me gusta cómo está… veo botones bastantes al abrir la cita y no
sé en qué estado está… verde hay 3 estados o más».

L2 — Tres tableros en `design/detalle-cita/`; se elige
`01-modal-siguiente-paso.html` y se retira la franja verde de su tarjeta «Ahora»
por comentario de canvas `p9wrh9hl`.

L3 — Decisión de implementación: la guarda `CitasCalendarAppShellTest` (la
agenda no abre la consulta) se conserva; el primario de `arrived` es Anunciar y
el de `scheduled` vuelve a Registrar llegada, para no perder el acceso directo
que recepción ya tenía. `Badge.vue` recibe las tres variantes nuevas de forma
aditiva porque la leyenda de la agenda lo consume.

L4 — Implementación en el árbol de trabajo (2026-11-23, sin commit): T1-T3
entregados por el worker en `appointmentStatus.js`, `AppointmentStageRail.vue`,
`CalendarPage.vue`, `StatusBadge.vue`, `Badge.vue` y las dos suites de foco.
Verificación medida: Vitest de foco **114 pruebas en 5 archivos**; guardas
`CitasCalendar|CitasNegativeSpaceRules|SurfaceHeaderHomogeneity` **167 pruebas,
913 aserciones** (la guarda de U3 intacta y verde); ESLint y Prettier limpios en
los nueve archivos y en la documentación. Queda cerrado en T5 (L7): «Sede» y
«Procedimiento» ya se publican cuando vienen precargados.

L5 — Arnold (2026-11-23): «hay 2 botones… 2 fases en 1… las fases deben ser
secuenciales»; «algo más camuflado la fase que lo necesita y más llamativo
otros»; «recursos que se solapan… en la línea de la fase». Decisión: secuencia
estricta, relleno tenue en todos con subida a `100` solo en los estados que
piden acción, y conectores recortados sin halo. El tablero 01 se actualiza al
mismo contrato antes de tocar el código.

L6 — Ronda 2 medida (2026-11-23, sin commit): secuencia estricta, `arrived` con
una sola puerta (`start.consultation` → iniciar; si no, anunciar), píldora tenue
`50` con subida a `100` y pulso solo en los estados activos, y rail con
conectores recortados 14 px, nodo relleno sin halo y horas alineadas. Vitest de
foco **119 pruebas en 5 archivos**; guardas
`CitasCalendar|CitasNegativeSpaceRules|SurfaceHeaderHomogeneity` **167 pruebas,
913 aserciones**; ESLint, Prettier y Pint limpios. La guarda de U3 se renombró a
«inicia la atención sin abrir la consulta»: el asistente sigue prohibido en la
agenda.

L7 — Cierre de S6/T5 (2026-11-23, sin commit): `Appointment::branch()`;
`AppointmentResource` publica `branch` {id,name} y `procedure` {id,name,code}
con `whenLoaded`; el índice selecciona `branch_id`/`procedure_id` y precarga
ambas, y `show()` también. `my-day` precarga las dos en
`AppointmentCustodyService::queueFor()` y en `AppointmentCustodyController::RELATIONS`,
así su payload no lleva objetos vacíos. El modal pinta «Sede» y «Procedimiento»
con `v-if` sobre la relación. Verificación: `AppointmentResourceBranchProcedureTest`
**6 pruebas, 30 aserciones**; Vitest de foco **120 pruebas**; guardas
`CitasCalendar|CitasNegativeSpaceRules|SurfaceHeaderHomogeneity` verdes; Pint,
ESLint y Prettier limpios. Los tests de `MyDay|Custody` quedan 18 de 19
saltados por SQLite (necesitan `phpunit.mysql.xml`), no fallidos.

L8 — Cierre del rail (2026-11-23, sin commit): el `select` del índice no traía
`checked_in_at`, `completed_at`, `final_amount` ni `consultation_mode`, así que
el rail nunca podía pintar la hora de llegada ni la de cierre; se agregaron. Se
retira la fila «Plan de tratamiento» del modal: el índice no precarga esa
relación y la fila jamás se pintaba. Sin cambios de contrato para el cliente más
allá de las claves nuevas de L7.
