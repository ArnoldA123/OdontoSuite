# Dashboard Ops Redesign — Replanteamiento del dashboard

**Branch**: `feat/dashboard-ops-redesign` (desde `main` @ 0db46d9)
**Estado**: abierto
**Inicio**: 2026-10-04

## Objetivo

Replantear el dashboard de OdontoSuite como panel **operativo diario**: la agenda del día manda, los KPIs se compactan, las acciones son contextuales, y el lenguaje visual hereda el del login (clínico claro, acento verde `accent`, motion moderado). Incluye fixes funcionales de datos y la actualización de los guards estructurales que fijan la forma actual.

## Decisiones del usuario (2026-10-04)

- **Profundidad**: reestructuración total (IA + flujo + visual), no solo maquillaje.
- **Prioridad de uso**: operativo diario (agenda y pendientes antes que métricas).
- **Motion**: moderado tipo login (reveals suaves, hover con elevación, springs); `prefers-reduced-motion` respetado.

## Hallazgos de exploración (base del plan)

- Bug funcional: `DashboardPage.vue:1145` pide `/api/dashboard/today`; la ruta real es `dashboard/appointments-today` (`routes/api.php:119`) → la sección "Citas de Hoy" está permanentemente vacía. Además `AppointmentResource` emite `patient.full_name`, no `patient.name`.
- Sin estado de error: fallos de red no 401 se tragan (`loadDashboardData`).
- Datos backend sin usar: `dashboard/upcoming` (semana), `total_income`, `comparisons.*` con deltas.
- Deuda visual: `text-ink-*` (token muerto), alias deprecado `systemBlue`, SVGs inline en vez de `@heroicons/vue`, `text-[11px]` arbitrario.
- ~1250 líneas de guards estructurales source-scan (`tests/Unit/DesignSystem/DashboardAppShellTest.php` y `tests/Feature/Ui/HotfixDashboard*`) fijan marcadores literales (`data-stat-card`, `data-action`, `data-state`, `slice(0, 3)`, textos, grids). Cada work unit actualiza sus propios guards.

## Diseño propuesto (IA nueva)

1. **Header compacto**: saludo + fecha + estado de caja con acción directa (abrir/cerrar sesión de caja según permisos).
2. **Agenda de hoy (protagonista)**: lista completa con hora, paciente, tipo, profesional, estado; acciones `crear cita` / `ver calendario`; estados vacío/carga/error propios.
3. **Próximas citas (semana)**: tira compacta desde `dashboard/upcoming`.
4. **KPIs compactos**: fila densa (citas hoy, pacientes nuevos mes, citas mes, ingresos, caja) con chips delta existentes.
5. **Acciones rápidas contextuales**: por permisos, con `@heroicons/vue`, misma superficie que las cards.

## Tareas

| # | Tarea | Estado | Evidencia |
|---|---|---|---|
| T1 | fix(dashboard): cablear `appointments-today` + mapeo `patient.full_name`/tipo (TDD) | ✅ hecha | commit 588b59b (RED 3/3 → GREEN 3/3; spot check padre 3/3) |
| T2 | feat(dashboard): nueva IA operativa — split T2a/T2b | ✅ hecha | T2a a30cde6 + T2b dc8426f |
| T2a | header compacto (saludo+fecha+caja) + Agenda de hoy protagonista | ✅ hecha | commit a30cde6 (RED 5/6 → GREEN 6/6; suites JS 124/124, PHP 584/584, lint 0) |
| T2b | KPIs compactos + acciones rápidas contextuales (heroicons) | ✅ hecha | commit dc8426f (RED 3/3 → GREEN 5/5; JS 129/129, PHP 584/584, lint 0) |
| T3 | feat(dashboard): estados carga/vacío/error + retry + refresh manual | ✅ hecha | commit e37e0ae (RED 4/7 → GREEN 7/7; JS 136/136, PHP 584/584, lint 0) |
| T4 | feat(dashboard): motion moderado (reveals, springs, hover) + reduced-motion | ✅ hecha | commit e70c41b (RED 4/5 → GREEN 5/5; JS 141/141, PHP 587/587, lint 0) |
| T5 | feat(dashboard): tira de próximas citas de la semana (`dashboard/upcoming`) | ✅ hecha | commit 8fc2b89 (RED 5/5 → GREEN 5/5; JS 146/146, PHP 587/587, lint 0) |
| T6 | verify: suites PHP/JS + lint + build + verificación visual en navegador | ✅ hecha | JS 146/146, PHP 1113/1113 (MySQL vía socket; ver nota), lint 0, build ✓; 7/7 items visuales PASS, capturas revisadas por el padre |
| T7 | (pendientes) — ampliado por decisión del usuario 2026-10-05: implementar ahora | 🔄 planificado | split T7a backend / T7b frontend |
| T7a | feat(api): endpoint `dashboard/pending` (presupuestos + planes) | ✅ hecha | commit 7f35beb (RED 6/6 → GREEN 6/6, 49 assertions; + AGENTS.md 195 rutas, doc-drift 12/12) |
| T7b | feat(dashboard): bloque "Pendientes" (con gating por permisos) | ✅ hecha | commit fc65dac (RED 8/8 → GREEN 8/8; JS 155/155, PHP 587/587, lint 0) |
| T8 | fix(dashboard): chip de comparación truncado en KPIs | ✅ hecha | commit fe6c82e (RED 1/6 → GREEN 6/6; JS 147/147, PHP 587/587, lint 0; 138 líneas; ASSESS medium under_budget → diferido a target final) |
| T9 | cierre: revisión RDD target final + push + cadena de PRs | ✅ hecha | issue #116 + PRs #117–#125 (9 slices); verificación final 155/155 JS, 1120/1120 PHP, 17 capturas PASS; agregado final irreviable por presupuesto de lentes (documentado) |

## Criterios de aceptación

- La agenda de hoy muestra datos reales (hoy está vacía por bug de ruta) y distingue carga/vacío/error.
- KPIs compactos con comparativas; sin clases muertas ni `systemBlue` en código nuevo.
- Todo el texto de UI en español; primitivas de `components/ui/`; tokens de diseño, sin hex ni gradientes.
- Guards estructurales actualizados y verdes; `pnpm test:js`, `php artisan test`, `pnpm lint:check`, `pnpm build` en verde.
- Motion respeta `prefers-reduced-motion`.
- Visibilidad verificada en navegador (capturas revisadas) como criterio de aceptación.

## Ejecución: rutas y pronóstico

| Tarea | Ruta | Trigger |
|---|---|---|
| T1 | delegada (gentle-ai-worker) | multi-file write: página + tests |
| T2 | delegada (gentle-ai-worker) | multi-file write: IA nueva + guards |
| T3 | delegada (gentle-ai-worker) | multi-file write |
| T4 | delegada (gentle-ai-worker) | multi-file write |
| T5 | delegada (gentle-ai-worker) | multi-file write |
| T6 | delegada (gentle-ai-verify) | verification rule (suites/build/visual) |
| T7 | pendiente de decisión | — |

**Pronóstico de líneas autoriales (add+del, sin generados):** ~1800-2800 sin T7. Supera el umbral de ~400 → estrategia de entrega por decidir con el usuario antes del primer commit.

## Estrategia de entrega (decidida por el usuario, 2026-10-04)

`chain_strategy=feature-branch-chain` — cadena sobre rama feature. Tracker: `feat/dashboard-ops-redesign` (rama de integración, PR final draft/no-merge hacia `main` al cierre de la cadena). Cada slice es una rama hija encadenada con diff limpio; push/PR/merge son decisión del usuario.

| PR | Rama hija | Base | Commits (slice) | Tareas |
|---|---|---|---|---|
| 📍 #1 | `feat/dashboard-ops-redesign-01-today-fix` | `feat/dashboard-ops-redesign` | 588b59b | T1 ✅ |
| #2 | `feat/dashboard-ops-redesign-02-ia-agenda` | `...-01-today-fix` | a30cde6 | T2a ✅ |
| #3 | `feat/dashboard-ops-redesign-03-ia-kpis` | `...-02-ia-agenda` | dc8426f | T2b ✅ |
| #4 | `feat/dashboard-ops-redesign-04-states` | `...-03-ia-kpis` | e37e0ae | T3 ✅ |
| #5 | `feat/dashboard-ops-redesign-05-motion` | `...-04-states` | e70c41b | T4 ✅ |
| #6 | `feat/dashboard-ops-redesign-06-upcoming` | `...-05-motion` | 8fc2b89 | T5 ✅ |
| #7 | `feat/dashboard-ops-redesign-07-chip-fix` | `...-06-upcoming` | por definir | T8 🔄 |
| #8 | `feat/dashboard-ops-redesign-08-pending-endpoint` | `...-07-chip-fix` | por definir | T7a |
| #9 | `feat/dashboard-ops-redesign-09-pending-block` | `...-08-pending-endpoint` | por definir | T7b |
| Tracker | `feat/dashboard-ops-redesign` | `main` | cadena completa | tras T9: revisión RDD target final + push + PRs |

```text
main (0db46d9)
 └── feat/dashboard-ops-redesign              ← tracker (PR final draft)
      └── 📍 ...-01-today-fix                 ← T1 (588b59b)
           └── ...-02-ia-agenda               ← T2a
                └── ...-03-ia-kpis            ← T2b
                     └── ...-04-states        ← T3
                          └── ...-05-motion   ← T4
                               └── ...-06-upcoming ← T5
                                    └── ...-07-verify ← T6
```

### Decisiones de diseño (T2a/T2b)
- Estado vacío: wash migrado de radial-gradient + systemBlue a `bg-accent-50` + hairline (constraint sin gradientes); confirmar en la aceptación visual de T6.
- Tira KPI: 5 cards fijas — Citas Hoy, Pacientes, Citas del Mes, **Ingresos (nuevo, `total_income` + `formatPENLabel`)**, Estado de Caja. La card Profesionales se elimina (no es daily-ops; sigue accesible por acción rápida/módulo).
- Eyebrow `text-[11px]` (no-token) → `text-xs` token; guard actualizado en el mismo unit.
- Iconos de acciones rápidas: `@heroicons/vue` 24-outline en vez de SVG inline.
- KPI Ingresos: caption "Total histórico" + click → `/business-intelligence` (elección del worker; `total_income` es suma histórica de pagos completados; confirmar en aceptación visual T6).
- Gating de KPIs conservado: roles sin `viewAppointment`/`viewCashRegister` ven menos de 5 cards (igual que antes).
- Follow-up pendiente (asignado a T4): alinear elevación de quick actions (`--elevation-1`) con KPIs (`--elevation-2`), según HOTFIX-DASH-005.
- Nota de diseño T3: si un refresh falla tras una carga exitosa, el error reemplaza las secciones (interpretación del spec); alternativa = banner no bloqueante con datos stale — ajustar en T6 si el usuario lo prefiere.

Conteo de líneas autoriales (running): T1 = 155, T2a = 561, T2b = 498, T3 = 566, T4 = 527, T5 = 517, T8 = 138, T7a = 434, T7b = 686. Total: 4082. Rama vs main (git): ~3500+/512-.

### Decisiones de diseño (T7b)
- Visibilidad de grupos por presencia de clave en el payload (el backend omite subsets no autorizados), no por mapa local de permisos.
- Labels de estado en español definidos en el dashboard: Enviado (sent), Visto (viewed, sin label previo en el módulo), Propuesto (proposed).
- Filas de planes: paciente/estado/fecha (title y final_cost del payload sin usar; total acotado a presupuestos por el alcance aceptado).
- El cap de 5 items lo impone el backend; el cliente renderiza lo que llega.
- Fetch fallido de pendientes muestra error inline incluso para roles sin subsets (decisión de consistencia; alternativa = silencio).

### Decisiones de diseño (T7a)
- "Pendiente de acción" definido de los enums reales: presupuestos `sent`+`viewed` (emitidos, decisión pendiente; `viewed` es enum válido aunque hoy nadie lo asigne — se incluye); planes `proposed` (esperando aceptación). `draft` excluido (trabajo interno), estados terminales/activos excluidos.
- Subsets no autorizados se omiten de `data` (asistente recibe `data: {}`).
- Cache 2 min por user+role (+branch_id opcional). ClearDashboardCache NO limpia `dashboard_pending_*` (TTL acota el staleness; follow-up menor).
- AGENTS.md §4: 195 rutas API / 208 con web (el guard doc-drift exige sincronizar al añadir rutas).

Nota operativa T8: en ASSESS, el baseRef debe ser el hash completo 40-chars REAL del commit de frontera (usar `git rev-parse <sha-corto>`, nunca completar a mano: un hash inventado produce `unassessable` y fuerza el plan de riesgo alto).

Nota de diseño T5: el fetch de upcoming añade una tercera request paralela por carga; futuros suites que cuenten requests totales deben añadir la rama upcoming. Agrupación por día en hora local (misma convención que formatTime).

## Resultado T6 (verificación de cierre)
- JS 32 files / 146 tests ✓ · lint 0 errores (1361 warnings preexistentes) · build ✓ (DashboardPage 32.08 kB).
- PHP: 1113/1113 ✓ contra MySQL de XAMPP vía socket (los overrides de env documentados en phpunit.mysql.xml). Salvedad: el comando con Collision emite warning de `--configuration` duplicado y retorna exit 1 aunque todo pasa; contra 127.0.0.1:3307 sin docker la suite falla por connection refused (ambiental).
- Visual (Playwright, 3 viewports, capturas en /tmp/dashboard-verify/): header+pill+Ir a Caja (gating correcto en odontologo), agenda sobre KPIs con empty state y filas stub correctas (endpoint correcto confirmado: appointments-today, nunca /today), próximas por día, 5 KPIs con Ingresos formatPENLabel (4 en odontologo), heroicons, motion + reduced-motion verificados, refresh sin vaciado + error block con retry.

### Hallazgos menores T6 (no bloqueantes)
1. Truncado del chip del KPI Pacientes ("-100 nuevos este m…") a 1440/1280; en móvil se ve completo.
2. "Citas del Mes" muestra 104 con caption "Este mes" porque applyStats hace fallback a total_appointments cuando el conteo mensual es 0 (semántica preexistente expuesta por el nuevo caption); DB demo con datos stale.
3. Doble fuente de estado de caja: pill del header (branch-level, /api/cash-register/current) vs stats.cash_session (per-user) pueden discordar; consistente con la DB actual.
4. Filas reales de agenda/próximas sin datos vivos en DB demo (seed 2026-09-21): rama real verificada con respuesta vacía + rama de filas verificada con stubs; una re-siembra cierra el gap.

## Entrega (2026-10-05)
- Issue #116 (Closes en el tracker). Cadena de PRs: #117 (01-today-fix → tracker), #118 (02 → 01), #119 (03 → 02), #120 (04 → 03), #121 (05 → 04), #122 (06 → 05), #123 (07-chip-fix → 06), #124 (08-pending-endpoint → 07), #125 (09-pending-block → 08). Cada PR con Chain Context + recibo RDD del slice. Merge en orden #117→#125 (cada PR se retargetea limpiamente conforme los padres se fusionan).
- PR tracker (feat/dashboard-ops-redesign → main): se abre al completar la cadena (GitHub rechaza PRs con diff vacío y el tracker recibe la cadena con estas fusiones).
- Revisión del agregado final: `lens_context_budget_exceeded` en preflight (16 paths / ~4000 líneas exceden el presupuesto nativo; sin autoridad creada, retry_safe pero este candidato es irreviable). Cobertura preservada: 9/9 commits con recibo RDD propio (8 slices + chip fix), verificación funcional completa. `gentle-ai review mode` NO se tocó (consulta reservada al usuario según disposición).

### Revisiones finales (cierre)
| Candidato | Resultado |
|---|---|
| T8 chip fix (fe6c82e vs 8fc2b89, 138 líneas) | lineage `review-60c509b2bb307290` **approved + burned**, 0 hallazgos |
| Agregado final (rama vs main) | **no iniciado**: `lens_context_budget_exceeded` (preflight), sin autoridad que abandonar |

## Estado: ✅ FEATURE MERGEADA EN MAIN (2026-10-05) — ramas limpias

- Merge final: PR #128 (tracker) → main @ 7d51f62. Cadena fusionada en orden: #117, #127 (sustituto de #118, auto-cerrado al borrarse su base), #119–#125, #126 (pulido de la sesión paralela, 8 commits). Issue #116 cerrado automáticamente (Closes #116).
- Limpieza: 0 PRs abiertos; solo quedan main (local+remoto); doc-drift 12/12 en verde; 184/184 tests JS en el tip final.
- Lección de entrega en cadena: NUNCA usar `--delete-branch` al mergear un PR hija cuya rama head es la BASE del siguiente (GitHub cierra el PR siguiente); borrar ramas al final y retargetear los PRs al tracker antes de mergear el padre.

Frontera revisada inicial: `main` = 0db46d9a3363a3c06069e502040ac500e35cca6d. Candidato por work-unit commit contra la frontera previa.

| Candidato | ASSESS | Revisión nativa | Outcome |
|---|---|---|---|
| T1 (588b59b vs 0db46d9a) | medium (`executable_change`), writerProfile large (runtime) | lineage `review-37e101a5943ccd62`, 1 lente `review-reliability` | **approved + burned** (gentle-ai.review-acknowledged/v1); 2 advisory no bloqueantes |
| T2a (a30cde6 vs 588b59b) | medium, **reviewDue=true** (`slice_budget_reached`, 561 líneas) | lineage `review-9a708c75e1e36156`, 1 lente `review-reliability` | **approved + burned**, 0 hallazgos |
| Acumulado T1+T2a (a30cde6 vs 0db46d9a, 4 paths/710 líneas) | — (impulsado por recordatorio RDD) | lineage `review-d387591c0bf03ca9`, 1 lente `review-reliability` | **approved + burned**, 0 hallazgos (target identity distinto de los slices) |
| T2b (dc8426f vs a30cde6) | medium, **reviewDue=true** (`slice_budget_reached`, 498 líneas) | lineage `review-ccbdd3b629acf410`, 1 lente `review-reliability` | **approved + burned**, 0 hallazgos |
| Agregado T1+T2a+T2b (`7f7d84ed…`, 5 paths) | — | — | **sin revisar por disposición** (cubierto por receipts de slices; reportado ante recordatorio RDD) |
| T3 (e37e0ae vs dc8426f) | medium, **reviewDue=true** (`slice_budget_reached`, 566 líneas) | lineage `review-c4e40e0965af78ed`, 1 lente `review-reliability` | **approved + burned**, 0 hallazgos |
| T4 (e70c41b vs e37e0ae) | medium, **reviewDue=true** (`slice_budget_reached`, 527 líneas) | lineage `review-0a453401e03b70f5`, 1 lente `review-reliability` | **approved + burned**, 0 hallazgos |
| T5 (8fc2b89 vs e70c41b) | medium, **reviewDue=true** (`slice_budget_reached`, 517 líneas) | lineage `review-24ffe302c6e9f59e`, 1 lente `review-reliability` | **approved + burned**, 0 hallazgos |
| T8 (fe6c82e vs 8fc2b89) | medium, under_budget (138 líneas) | — (diferido al target final) | writer self-verification |
| T7a (7f35beb vs fe6c82e) | medium, **reviewDue=true** (`slice_budget_reached`, 434 líneas) | lineage `review-1fe91cb0efb69acd`, 1 lente `review-reliability` | **approved + burned**, 0 hallazgos |
| T7b (fc65dac vs 7f35beb) | medium, **reviewDue=true** (`slice_budget_reached`, 686 líneas) | lineage `review-8075d3e8b0ac6286`, 1 lente `review-reliability` | **approved + burned**, 0 hallazgos |

### Disposición RDD (delegada por el usuario, 2026-10-05)

"Tú hagas lo que mejor te convenga y no por eso bajar la calidad" → decisión del orquestador, registrada:

- **Targets agregados intermedios** (unión vs merge-base entre fronteras de slice): **EXPLÍCITAMENTE sin revisar**. Su contenido queda cubierto por los recibos RDD por slice (cada work unit se revisa al introducirse). Ante recordatorio RDD de estos targets: reportar esta disposición y continuar sin invocar review.
- **Target acumulado final** de la feature (PR tracker: rama completa vs `main`): **se revisa UNA vez al cierre**, antes de entrega (patrón R4 de login-ui-refresh), sujeto a presupuesto de lentes; si lo excede, se reporta y se consulta antes de tocar `gentle-ai review mode`.
- La calidad no baja: revisión por slice + revisión de integración final + checks funcionales completos (T6).

### Advisory T1 (trabajo posterior, no bloquean)
- R3-001 (WARNING, informational): `tests/js/smoke/dashboard-today-appointments.test.js:80-126`.
- R3-002 (SUGGESTION, informational): `resources/js/modules/dashboard/DashboardPage.vue:1020-1032` (precedencia del nombre de paciente).
