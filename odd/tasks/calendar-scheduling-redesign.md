# Calendar Scheduling Redesign — ENTREGADA ✅ (mergeada en main 2026-10-12)

## Entrega (chain_strategy=stacked-to-main, 3 PRs → issue #133 CERRADO)

| PR | Slice | Merge commit |
|---|---|---|
| #135 | T1-T4 core | `23a8645` |
| #136 | T5-T6 drag & drop + mes | `0297fda` |
| #137 | T7-T8 detalles + polish (`Closes #133`) | `b51abbf` |

Notas de entrega: `main` avanzó con PR #132 (patients) durante la cadena → stack rebaseado sobre `main` (colisión del `DentalMotifLayer` compartido resuelta adoptando la versión de `main`, que además trajo el primitivo `UiSectionEmpty`); PR #134 cerrado como superseded (force-push bloqueado por política del harness → plan autorizado close-and-recreate). Conteo de primitives resincronizado a 35 post-rebase. CI verde en los 3 PRs (Quality + Backend MySQL + Frontend build).

Rama: `feat/calendar-scheduling-redesign` (desde `main` @ e2034c1)
Worktree: `~/proyectos/odontosuite-calendar`
Creado: 2026-10-10 · Orquestador: el Gentleman

## Objetivo

Rediseñar la interfaz de calendario (`/calendar`, `CalendarPage.vue`) con el
lenguaje visual del dashboard/login y llevarla a calendario operativo completo:
clic-en-hueco para crear citas y drag & drop para mover citas, con soporte
backend de reprogramación y control de conflictos.

## Alcance (decisión del usuario 2026-10-10: "Visual + interacción completa")

1. Rediseño visual con el lenguaje del dashboard: hero band con motivo dental,
   jerarquía de bloques, pills/badges únicos, empty states con `DentalMotifLayer`,
   tokens `bg-canvas` / `text-theme-*` / `--radius-panel`, heroicons (sin SVG inline).
2. Interacción: clic en hueco horario → `NewAppointmentModal` prellenado con
   fecha/hora; navegación de períodos pulida; modal de detalle con `UiModal`.
3. Drag & drop en vistas Día y Semana para mover citas entre huecos, con
   actualización optimista, rollback y aviso de conflictos.
4. Backend: endpoint de reprogramación con validación de solapes y respuesta
   clara ante conflicto.

## No-alcance (follow-ups documentados, NO se tocan aquí)

- **Deps `@fullcalendar/*` sin uso** (`package.json:37-41`, cero imports en
  `resources/js`): limpieza de dependencias queda como follow-up.
- **Wizard de consulta** (`ConsultationWizard.vue`): solo se integra/dispara desde
  el detalle de cita; su flujo interno no se rediseña.
- **`NewAppointmentModal.vue`**: se reutiliza tal cual (salvo prefill de fecha/hora);
  su propio rediseño es follow-up si hiciera falta.
- Sin migraciones de esquema. La reprogramación usa campos de fecha/hora existentes.

## Decisiones de diseño (arquitecto)

| # | Decisión | Motivo |
|---|---|---|
| D1 | Lenguaje visual heredado 1:1 del dashboard (`DashboardPage.vue`): hero band + `DentalMotifLayer`, `UiBadge` pill único, `UiCard` con tokens | Coherencia con el rediseño ya entregado; `PageHeader` + `UiCard glass` + SVGs inline son el lenguaje viejo |
| D2 | Contrato de estado único `appointmentStatus.js` (label + variant + tone) consumido por chips, leyenda, modal y acciones | `CalendarPage.vue` duplica los mapas de color 3× (`getAppointmentClasses`, `getStatusClasses`, `getStatusBadgeProps`) con literales `systemBlue-*` |
| D3 | Date-math pura extraída a `calendarDateMath.js` (hours, weekDays, monthDays, period title) con tests Vitest | Date-math duplicada entre `currentPeriodTitle`/`weekDays`/`monthDays`/`getDateRangeForCurrentView`; `dayHours` y `weekHours` son idénticos |
| D4 | Modal de detalle con `UiModal` + `useConfirm` para borrado | Modal hecho a mano (`bg-black bg-opacity-50`) y bug latente: `confirm(...)` usado sin importar `useConfirm` (`CalendarPage.vue:944`) |
| D5 | Click-to-create en huecos de Día/Semana: prefill fecha + hora (+ columna) en `NewAppointmentModal` | Reducir fricción de creación; era el flujo "Nueva Cita" a secas |
| D6 | Drag & drop propio (pointer events, sin librería) en Día/Semana: optimista con rollback, toast de conflicto, feedback mínimo con `useReducedMotion` | Las vistas ya son propias; FullCalendar chocaría con el lenguaje visual y su dependencia está sin usar |
| D7 | Backend: `PATCH /api/appointments/{appointment}/reschedule` con validación de solape por profesional y ambiente (reutilizar reglas existentes de `AppointmentService`), 422 con mensaje claro | Drag & drop sin backend es mentira: el movimiento debe validarse y persistirse con la misma semántica que la creación |
| D8 | Mes: chips compactos + "N más" que hace drill-down a la vista Día | Popover ad-hoc = otra superficie más; drill-down reutiliza la vista Día ya rediseñada |
| D9 | Pruebas: TDD observado en lógica pura (date-math, contrato de estado, validación de reprogramación en backend); verificación funcional proporcional en lo visual (lint + build + smoke + build local) | Lo visual no tiene RED determinista significativo; la lógica sí |
| D10 | Reprogramación solo a futuro (≥1 min), espejo de `StoreAppointmentRequest` | Un movimiento retroactivo vía drag & drop corrompe el registro; los ajustes históricos usan las acciones de estado del detalle |
| D11 | Los quirks de date-math se CORRIGEN en T4/T6 (Sunday que rueda a la semana siguiente; mes donde el día 1º domingo falta de la rejilla), actualizando los tests que hoy los anclan con justificación | Son bugs reales preservados en T2 solo por ser refactor behavior-preserving; un calendario profesional no puede mostrarlos |
| D12 | (T6) `addPeriod` con clamp a fin de mes para pasos mensuales (31 ene +1 → 28 feb, no 3 mar) | Advisory `R3-MONTH-STEP-OVERFLOW` de la revisión nativa; semántica de calendario profesional |
| D13 | (T5, aceptado) Drops sobre huecos ocupados permitidos; el servidor arbitra con toast estructurado | Un gate cliente duplicaría `findConflicts` incompleto y stale; huecos compartidos entre profesionales son legítimos (análisis completo en reporte T5) |

## Restricciones de estilo (heredadas)

- `pnpm` siempre; commits convencionales en inglés, sin emojis; sin comentarios pedagógicos.
- UI siempre desde `components/ui/`; Tailwind + design tokens; `<script setup>` preferido.
- Tests anclados al contrato real (variant binding, data-*), nunca a literales de clases muertas.
- Idioma de trabajo: español; artefactos técnicos (código, commits) en inglés.

## RDD (disposición delegada por el usuario: "Decide tú, sin bajar calidad")

1. Toda work unit con TDD observado + plan ASSESS cumplido.
2. Revisión nativa del slice (rango commiteado, `baseRef` = frontera previa +
   `committedOnly`) SOLO cuando ASSESS marque `reviewDue=true`.
3. Agregado final de la feature revisado UNA vez al cierre.
4. Agregados intermedios sin revisar (cubiertos por sus slices).
5. El consent RDD por candidato es user-owned: la delegación de cadencia NO lo reemplaza.
6. NO correr el writer en paralelo a un START de revisión (contamina el candidato:
   lección obs #205 de `dashboard-hierarchy-redesign`).

## Notas de evidencia

- **T1** (`e26b366`): TDD con RED replay post-hoc (runner roto al escribir los tests: worktree sin `vendor/` ni MySQL). RED replay 10/10 en 404 de ruta ausente → GREEN 10/10 (57 aserciones) → vecindario `--filter=Appointment` 152/152 OK. Verificador independiente (plan ASSESS, riesgo `unassessable` = high): APPROVE 5/5. Contrato: `PATCH /api/appointments/{appointment}/reschedule` con `scheduled_at` + `duration_minutes` opcional (15-480), 422 estructurado `conflict.{type, conflicting_appointment}`, mismos roles del grupo de citas. 10 tests en `tests/Feature/Api/AppointmentRescheduleTest.php`.

- **T2** (`d771b21`): TDD Vitest real (RED por import irresoluble → GREEN 44/44) + suite completa 239/239. Extractos puros `appointmentStatus.js` (registro 8 claves, fallbacks preservados) y `calendarDateMath.js` (buildHours, weekDays, monthDays, periodTitle, dateRangeForView, addPeriod) con quirk de semana (Monday-first, Sunday rueda a la semana siguiente) y rejilla de mes (42 celdas, día 1º ausente si cae domingo) ANCLADOS como comportamiento actual — a corregir en T4/T6 según D11. Fix del bug `confirm` → singleton `useConfirm` await-eado. Aceptado: `in_progress` (enum legacy BD) ahora renderiza "En Consulta" (coherente con la leyenda preexistente del template). ASSESS `unassessable` → verifier independiente APPROVE 5/5.

- **T3** (`f9c28bd`): shell rediseñado (hero band gramática dashboard, switcher segmentado con heroicons, leyenda registry-driven como header strip de la card). `DentalMotifLayer` extraído a `components/ui/` (move verbatim verificado; dashboard sin cambio visual). Tests estructurales repunteados sin debilitar (7 estados garantizados vía registry + derivación) + 2 tests nuevos de hooks. TDD no aplicable (shell visual sin RED determinista) — verificación funcional: Citas 52/250, DesignSystem 570/2776, Vitest 239/239, build OK. Verifier APPROVE 5/5. Menor aceptado: pill "En vivo" conserva clases systemGreen (preexistente byte-idéntica, fuera de alcance T3).

## Notas de evidencia (cont.)

- **T4** (`a0dd709`): TDD real del fix D11 (RED 9 tests quirk → GREEN 37/37; guards 2024-2028). Rejillas Día/Semana reconstruidas (chips registry-driven, único `appointmentsForSlot`, chips concurrentes comprimidos — sin motor de layout por diseño), click-to-create (`data-calendar-slot`, `<button>` real, gate de permiso). `NewAppointmentModal` sin cambios (`initialDate` ya soporta datetime-local); call site DashboardPage intacto. Bonus: "Nueva cita" en vista Día ahora prefiella (era dead code). Citas 56/271, DesignSystem 574/2797, Vitest 243/243, build OK. ASSESS: risk=medium, plan = writer self-verification sin verifier; **reviewDue=true (slice_budget_reached)** → revisión nativa. **Bug preexistente para T7**: `editAppointment` setea `editingAppointment` pero nunca se pasa al modal ("Editar" abre formulario vacío).

## Notas de evidencia (cont.)

- **Revisión nativa RDD (post-T4, lineage `review-c9d5b640026643e6`)**: START con la ruta ofrecida por inspect (baseRef `e2034c1` = base de rama, committedOnly) — nota: cubrió el acumulado T1–T4 (16 archivos, 2877 líneas), no solo el slice T4; más cobertura, no menos. Lente `review-reliability` (R3), tier medium, budget de corrección 200. Forecast: 1 model run vía pi_host_relay (autorizado). Resultado: **APPROVED**, 3 hallazgos advisory no bloqueantes (sin transición de corrección), acknowledgement ejecutado → autoridad quemada (`gentle-ai.review-acknowledged/v1`).
- **Hallazgos advisory (follow-ups, NO bloquean)**: (1) `R3-CONFLICT-PAYLOAD-COVERAGE` (SUGGESTION, tests/Feature/Api/AppointmentRescheduleTest.php:109-116) — ampliar cobertura del payload de conflicto. (2) `R3-MONTH-STEP-OVERFLOW` (SUGGESTION, calendarDateMath.js:126-128) — overflow de `setMonth` en `addPeriod` (31 ene +1 mes → 3 mar): considerar clamp a fin de mes en T6. (3) `R3-RESCHEDULE-CHECK-ATOMICITY` (WARNING, AppointmentController.php:334-356) — check-then-act de conflictos con ventana de carrera (la re-verificación del servicio la acota pero no la cierra): hardening con guard a nivel BD es follow-up.

- **T5** (`570a640`): drag & drop pointer-based en Día/Semana (umbral 6px discrimina click vs drag), `dragDropMath.js` puro (fail-closed: propio-slot y <1 min futuro rechazados; payload naive-local sin toISOString), optimista + rollback (422 conflicto → toast con datos del bloqueador del payload del servidor; validación → mensaje del servidor; red → error), gate `can.editAppointment`, reduced-motion estático, teardown en unmount. TDD: RED replay post-hoc etiquetado (WIP de corridas interrumpidas por internet, sin RED first-observed) → GREEN 16/16; tests estructurales 13 con RED→GREEN real. Citas 69/350, DesignSystem 587/2876, Vitest 268/268, build OK. Verifier APPROVE. **Follow-ups menores**: (1) snapshot de rects al inicio del drag se stalea con wheel-scroll mid-drag; (2) borde cosmético del suppressNextClick en navegadores con orden timer/click no garantizado; (3) Escape no aborta request ya en vuelo. Bug preexistente adicional para T7: "Editar" del modal gatea en `can.updateAppointment` (clave inexistente) → botón nunca se renderiza.

- **T6** (`36cad2b`): Mes reconstruido (rejilla 42 celdas quieta, header semanal compartido, chips compactos registry-driven cap 3 + "N más" con count = restantes → drill-down a Día, link de número de día), helper puro `splitMonthCellAppointments` + `MONTH_CELL_CHIP_LIMIT`, D12 clamp de `addPeriod` mensual. TDD real: RED 3 (clamp) + 4 (helper) → GREEN 44/44. Citas 79/415, DesignSystem 597/2941, Vitest 279/279, eslint 0, build OK. Verifier APPROVE (1 nota menor: comentario de rationale en fixture smoke atribuye mal el hazard — corrección de comentario, no conducta). Desvío aceptado: regla estructural de hooks de chips 2→3 (mes añade el tercer site; aserciones más estrictas, no más débiles).

- **T7** (`eb3c7b9`): detalle en `UiModal` (backdrop a mano y SVG inline eliminados, pill registry, hooks de acciones con gates reales), empty states por vista con `DentalMotifLayer variant="box"` + CTA gateada (precedencia loading → empty → grids), motion con reduced-motion. Fixes de bugs preexistentes: binding `:appointment="editingAppointment"` ("Editar" abría formulario vacío) y gate `can.updateAppointment` inexistente → `can.editAppointment`. TDD real: RED 6 smoke → GREEN 22/22. Citas 98/535, DesignSystem 616/3061, Vitest 286/286, build OK. Verifier APPROVE. Menores: (1) gap del mock `delete` vs `del` era del mock, no del app (useApi expone ambos); (2) follow-up: re-editar la MISMA cita dos veces seguidas puede abrir formulario vacío (watcher no re-firea — preexistente, mejorado respecto al bug total).

## Notas de evidencia (cont.)

- **T8a** (`c5e15e4` docs + `ef7fe75` tokens): AGENTS.md sincronizado con valores medidos (196 rutas API / 209 totales; primitives 30→34 con nota de deriva preexistente en la base), §12 changelog, guards en verde (doc-drift 12/12, Documentation 27/80). Sweep de tokens: alias deprecado `systemBlue-*` → `accent-*` canónico en superficies de la feature (hex idéntico verificado en tokens.js), tests a igual fuerza.
- **Verificación visual (2 pases)**: PASO 1 (veredicto FAIL) encontró: prefill vacío (ALTO), chips de semana sin tinte de estado (ALTO), tiempo truncado (MEDIO), drag sin feedback, M1 = falso positivo (el 11 SÍ está destacado — verificado visualmente). Ronda **T8b** (`46de53d`): tinte de chips arreglado (base = solo forma, registro = tinte; RED→GREEN real), tiempo compacto `HH:mm - HH:mm` + `title` completo (RED→GREEN), drag: hipótesis de cancelación silenciosa por horario pasado PROVADA (fixtures en semana pasada) → ahora toast info "No se puede mover a un horario pasado"; sin bug de geometría. PASO 2 (veredicto PASS): prefill funcionaba con build fresco (**artefacto de HMR stale**, no bug), tinte verificado en 3 vistas y todos los estados, drag de punta a punta verificado (pasado → toast + rollback sin PATCH; futuro → PATCH 200 + movimiento real en BD + toast éxito). Pendiente menor: submit del modal de nueva cita con campos requeridos vacíos no muestra errores (silencio) — follow-up. Nota: `deleteAppointment` solo admin/recepcionista (oculto para clínicos por diseño).

## RDD al cierre — agregado final encadenado (presupuesto excedido → 3 tramos, todos APPROVED + quemados)

El agregado completo (25 archivos, 6901 líneas) excedió el presupuesto de contexto del reviewer nativo (`lens_context_budget_exceeded`; consentimiento concedido, sin autoridad creada). Continuación dictada por el proveedor: candidatos más pequeños. Cobertura final (lente `review-reliability` en los tres):

| Tramo | Rango | Líneas | Lineage | Resultado |
|---|---|---|---|---|
| T1-T4 | `e2034c1..a0dd709` | 2877 | `review-c9d5b640…` | APPROVED + quemado |
| T5-T6 | `a0dd709..36cad2b` | 2344 | `review-e553e84d…` | APPROVED + quemado |
| T7-T8 | `36cad2b..46de53d` | 1784 | `review-45e77e5f…` | APPROVED + quemado |

Técnica: `baseRef` explícito (frontera previa) + `committedOnly`; tramo intermedio revisado desde un worktree temporal detached en el tip de T6 (retirado tras el cierre). Consentimiento por candidato user-owned (1 concedido; los otros 2 START sin nueva pregunta).

## Follow-ups acumulados (NO bloqueantes; trabajo posterior)

1. `R3-RESCHEDULE-CHECK-ATOMICITY` (WARNING) — carrera check-then-act en `AppointmentController::reschedule`; hardening con guard a nivel BD.
2. `R3-EMPTY-LOAD-FAILURE` (WARNING) — si `loadAppointments` falla, el array queda vacío y el empty state dice "Sin citas" (deshonesto): falta estado de error de carga.
3. `R3-overlapping-reschedule-rollback` / `R3-stale-drop-geometry` (WARNINGs, drag) — rollback ante solapes y rects stale si se hace scroll mid-drag.
4. SUGGESTIONs de cobertura: `R3-CONFLICT-PAYLOAD-COVERAGE`, `R3-pointercancel-unproved`, `R3-CREATE-PATH-STATE-RESET`, `R3-FORMATTER-DATE-BRANCH`.
5. UI: re-editar la MISMA cita 2 veces seguidas puede abrir formulario vacío (watcher); submit del modal de nueva cita con campos requeridos vacíos no muestra errores; deps `@fullcalendar/*` sin uso.

## Tasks

| # | Task | Estado | Commit |
|---|---|---|---|
| T1 | Backend: endpoint `reschedule` con validación de solapes (TDD PHPUnit) | ✅ hecha | `e26b366` |
| T2 | Lógica frontend: `appointmentStatus.js` + `calendarDateMath.js` + fix `confirm` (TDD Vitest) | ✅ hecha | `d771b21` |
| T3 | Shell del calendario: hero band, toolbar (vista + navegación), leyenda pills | ✅ hecha | `f9c28bd` |
| T4 | Rejillas Día/Semana: chips de cita rediseñados + click-to-create | ✅ hecha | `a0dd709` |
| T5 | Drag & drop Día/Semana: optimista + rollback + conflictos | ✅ hecha | `570a640` |
| T6 | Vista Mes rediseñada + drill-down "N más" → Día | ✅ hecha | `36cad2b` |
| T7 | Detalle de cita con `UiModal` + acciones rápidas + empty states + motion | ✅ hecha | `eb3c7b9` |
| T8 | Cierre: coherencia de tokens, docs sync, agregado final RDD, verificación visual | ✅ hecha | `c5e15e4` `ef7fe75` `46de53d` |
