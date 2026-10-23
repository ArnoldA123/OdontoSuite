# F4 — Continuidad: recall, sesiones del plan y próxima cita (##284)

Issue #284 (`[F4] Continuidad: recall, sesiones del plan y próxima cita
materializada`, `crit:alta`, `area:backend`). Rama
`feat/treatment-plan-continuity`, worktree `.worktrees/288-284`, base `main` @
`b523347` (merge de #295). Comparte rama con #288 porque el consumo por sesión
es el cimiento de las versiones del plan.

## Specs

S1. #284: «Recall: intervalo por paciente (3/6/12 meses) con motivo, cola de
vencidos en recepción, generación de cita de control, métrica de cumplimiento.»

S2. #284: «Sesiones: la atención consume ítems del plan; el plan muestra
ejecutado, en curso, pendiente.»

S3. #284: «El profesional indica la conducta; recepción materializa la cita con
disponibilidad real.»

S4. #284: «Un recall vencido aparece en el escritorio de recepción y genera una
cita válida.»

S5. #284: «Un plan no puede marcar un ítem como ejecutado sin atención que lo
consuma.»

S6. #284: «La cita generada desde el cierre valida conflictos de horario.»

S7. #284: «Un plan se ejecuta en N sesiones y hoy cada visita crea o adivina un
plan.»

S8. #284: «El wizard crea la próxima cita sin ver disponibilidad.» Se cierra
separando conducta de agenda: el cierre registra la conducta como solicitud de
continuidad y recepción la materializa por la puerta que ya valida conflictos.

## Tasks

- T1 — S1, S4 — `patient_recalls` (intervalo 3/6/12, motivo, vencimiento),
  servicio, CRUD, cola de recepción y métrica de cumplimiento. Commit: `352d96e`;
  tests: `9af74d0`.
- T2 — S3, S6, S8 — `continuity_requests`: el cierre guarda la conducta y deja
  de inventar la cita; recepción materializa con `AppointmentService` (la misma
  validación de conflictos de la agenda). Commit: `352d96e`; tests: `9af74d0`.
- T3 — S2, S5, S7 — `treatment_plan_item_consumptions`: el cierre de una sesión
  registra el consumo, el estado del ítem se deriva de la suma y el plan publica
  ejecutado/en curso/pendiente. Commit: `352d96e`; tests: `9af74d0`.
- T4 — verificación: suite MySQL enfocada y completa, Pint, Prettier,
  `doc-drift`, revisión nativa.

## Decisiones de contrato

- El recall es una entidad propia (`patient_recalls`), no un recordatorio: su
  vencimiento se mide por `due_date`, no por la hora de un envío.
- `next_appointment` del cierre deja de crear una cita y crea una
  `continuity_request` pendiente con la fecha preferida. La cita nace sólo en
  `POST /continuity-requests/{id}/schedule`, que reutiliza `AppointmentService`
  y por tanto valida solapamiento de profesional y sillón.
- El consumo referencia el acto (`clinical_encounter_id`) y el ítem del plan.
  `treatment_plan_items.status` no es escribible por el API de planes: se deriva
  de la suma de consumos (`completed` si cubre `quantity`, `in_progress` si
  cubre parte, `pending` si no hay consumo).
- La cola de recepción combina recalls vencidos y solicitudes de continuidad
  pendientes; respeta la sede del actor con `ScopesToBranch`.

## Slices de entrega

- S1 — `352d96e` — núcleo de dominio de F4 (recall, continuidad y consumos),
  junto con el cimiento compartido de #288 (versiones, prioridad y
  alternativas) porque un commit intermedio no compilaría. Supera la guía de
  ~400 líneas por esa dependencia mutua; queda registrado como límite de slice.
- S2 — `9af74d0` — tests de F4 y fixture de permisos.
- S3 — `1d9b857` — tests de #288.
- S4 — commit de documentos — AGENTS.md, ADR-0013 y estos documentos.

## Evidence

- `RecallServiceTest` (11), `ContinuityTest` (6) y
  `PlanSessionConsumptionTest` (9): verdes en MySQL.
- Suite completa `vendor/bin/phpunit -c phpunit.mysql.xml`: 2525 tests,
  15122 assertions, `OK`.
- Regresiones de citas, presupuestos, permisos y planes: 199 + 31 verdes.
- Vitest: 114 archivos, 946 tests. `pnpm build`, ESLint (0 errores),
  Prettier, Pint y `doc-drift` (14/14) en verde.

## Log

L1. Usuario: «haz la issue 288, 284, en worktree nuevo».
L2. Entorno: worktree `.worktrees/288-284`, rama
`feat/treatment-plan-continuity`, base `b523347`. Base de test propia:
`odontosuite_288_284_test` (MariaDB de XAMPP por socket).
L3. La issue #284 depende de F2 (cerrada) y la #288 depende de F4: se entrega
primero #284 y luego #288 sobre la misma rama, en commits de unidad revisables.
