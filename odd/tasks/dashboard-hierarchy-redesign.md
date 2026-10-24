# Dashboard Hierarchy Redesign — ABIERTO

Rama: `feat/dashboard-hierarchy-redesign` (desde `main` @ 7d51f62)
Creado: 2026-10-10 · Orquestador: el Gentleman

## Objetivo

Eliminar las repeticiones restantes del dashboard y darle jerarquía de panel
operativo de alto impacto, tras la auditoría 2026-10-10 (repeticiones + deuda de
jerarquía confirmadas en código y en capturas reales del build).

## Alcance (6 direcciones aprobadas por el usuario)

1. Una sola verdad por estado y por métrica (sin datos repetidos entre secciones).
2. Estructura en 3 bloques con jerarquía: **Hoy** (hero) → **Requiere tu atención** → **Próximos días**.
3. Bloque "Acciones rápidas" eliminado (re-listaba el sidebar).
4. Estados vacíos como caso de uso principal: un solo patrón, no 4 cajas gemelas.
5. KPIs: máximo 4, marco temporal coherente ("del día / sesión"), número + contexto real, sin slots vacíos ni chips inventados.
6. Lenguaje visual del login traído al dashboard (tipografía display, marca, densidad calmada).

## No-alcance (follow-ups documentados, NO se tocan aquí)

- **Bug backend comparación de pacientes** (`comparisons.total_patients` devuelve
  `current: 0, previous: 100, delta_label: "-100"` sobre un total de 100): se
  elimina el chip del KPI por diseño; el bug de comparación queda abierto.
- **Scoping de sesión de caja en `stats()`** (`->where('user_id', Auth::id())`,
  DashboardController.php:66) vs `/cash-register/current`: el panel unificado usará
  `useCashRegister` como fuente única, por lo que la contradicción desaparece del
  UI; corregir el scoping del endpoint queda como follow-up backend.
- **Ingresos del día**: `total_income` es histórico y no hay métrica diaria en el
  backend; los KPIs del día no incluyen dinero de ingresos (saldo de caja cubre el
  dinero operativo).
- **Caché de stats** (TTL 5 min + `ClearDashboardCache` no limpia `dashboard_pending_*`).
- Sin cambios de backend, sin nuevos endpoints, sin migraciones.

## Decisiones de diseño (arquitecto)

| # | Decisión | Motivo |
|---|---|---|
| D1 | KPIs = Citas hoy · Atendidas · Por confirmar · Saldo de caja | Los 3 primeros se derivan de la lista `today()` (fuente única, misma que la agenda); saldo desde `useCashRegister`. Marco temporal: hoy/sesión. |
| D2 | Métricas históricas (Pacientes, Citas del mes, Ingresos totales) fuera del panel diario | Viven en BI; mezclaban marcos temporales en una misma fila |
| D3 | Sin chips de comparación en KPIs | Los `delta_label` del backend son inválidos ("-100"); número + caption de alcance es más honesto |
| D4 | Caja en UNA superficie: tarjeta "Saldo de caja" (estado + saldo + CTA "Ir a Caja"); pill del header eliminada | Mataba la contradicción visible pill "Abierta" vs KPI "Sin sesión abierta" (R2) |
| D5 | "Acciones rápidas" eliminada; header conserva solo "Nueva cita" (acción real, no navegación) | Los tiles re-listaban el sidebar (/patients, /professionals, /environments, /business-intelligence) — fuente #1 de la percepción de repetición (R3) |
| D6 | Pendientes: una sola lista unificada (presupuestos + planes) con badge de tipo, ordenada por fecha; un solo empty state | Dos columnas gemelas con la misma intención = duplicación semántica (R6/R5) |
| D7 | Orden final: Header compacto → **Hoy** (KPIs del día + Agenda) → **Requiere tu atención** (Pendientes) → **Próximos días** (compacto) | El resumen va arriba; hoy "Resumen del día" era un pie de página (IA1) |
| D8 | Contador de citas del día solo en el KPI (no también en el header de Agenda) | R1: mismo dato en dos lugares |
| D9 | Empty states: variante compacta de `DashboardSectionEmpty`, copy contextual exacto (sin apuntar a "la sección de calendario" si el CTA abre el modal) | El peor caso (panel vacío) es el más frecuente y era 4 cajas gemelas gigantes |
| D10 | Topbar subtitle "Resumen general del sistema" → texto sin la palabra "Resumen" | Aparecía "Resumen" dos veces como marco (IA1/R-extra) |

## Restricciones de estilo (heredadas)

- Sin em-dash U+2014 en `DashboardPage.vue` (HOTFIX-DASH-011); usar "N/D" o texto.
- `pnpm` siempre; commits convencionales en inglés, sin emojis; sin comentarios pedagógicos.
- UI siempre desde `components/ui/`; Tailwind + design tokens; `<script setup>`.
- Tests anclados al contrato real (variant binding, data-*), nunca a literales de clases muertas.

## Tasks

| # | Task | Estado | Commit |
|---|---|---|---|
| WU1 | KPIs "del día": 4 tarjetas (Citas hoy · Atendidas · Por Atender · Saldo), derivadas de fuente única, sin chips/slots vacíos | ✅ hecho | 960b382 |
| WU2 | Caja en una superficie: quitar pill del header, tarjeta saldo con estado+saldo+CTA, fuente `useCashRegister` | ✅ hecho | 5b11d8a |
| WU3 | Pendientes unificados + Acciones rápidas fuera + CTA único + copy de empty states | ✅ hecho | 58b46fb |
| WU4 | Estructura 3 bloques (D7) + lenguaje visual del login + topbar subtitle | ✅ hecho | 5f6dd99 |
| WU5 | Verificación visual Playwright (3 viewports) + ajustes finos + cierre de docs | ✅ hecho | bf7b60c (cierre) |
| WU5b | Empty states con motivo del login a escala menor + pulido (alturas KPI, refresh hero) | ✅ hecho | bf7b60c (amend de 228325a) |

### Notas WU1
- Enum real de estados de cita: scheduled, confirmed, in_consultation, in_progress, completed, cancelled, no_show, rescheduled (NO existe "pending_confirmation"). Bucket 3 = "Por Atender" (ni completadas ni canceladas), hook `data-stat="pending-today"`.
- TDD: RED 26 failed/158 passed (JS) + 14 failed PHP → GREEN 184/184 JS + 567 PHP. ASSESS: medium, reviewDue=true (slice 1416 líneas), plan writer self-verification.
- `stats/applyStats` recortado a `cash_session`; los contadores de citas viven SOLO en la lista `today()`.

## Disposición RDD (delegada por el usuario 2026-10-10: "decide tú, sin bajar calidad")

1. Toda work unit: TDD observado + plan ASSESS cumplido (auto-verificación o verificador independiente según riesgo).
2. Revisión nativa del slice (rango commiteado, baseRef = frontera previa + committedOnly) solo cuando ASSESS marque `reviewDue=true`. WU1 ya la marcó (slice_budget_reached, 1416 líneas) → pendiente su revisión sobre el rango 7d51f62..960b382.
3. Agregado final de la feature: UNA revisión al cierre.
4. Agregados intermedios sin revisar (cubiertos por sus slices).
5. Ajuste de cadencia (2026-10-10): las revisiones se ejecutan como INCREMENTOS desde la última frontera revisada (ej. 7d51f62..5b11d8a cubre WU1+WU2 sin huecos), manteniendo cada lote dentro del presupuesto del reviewer; el agregado final de entrega se revisa UNA vez al cierre si cabe.

### Notas WU4
- Estructura: hero band (saludo display `text-4xl font-semibold` + fecha + refresh, tinte accent-50 + hairline + radius-panel) → **Hoy** (h2 2xl → KPIs → h3 "Agenda de hoy") → **Requiere tu atención** (h2 xl) → **Próximos días** (h2 base + tray gris-100 sin elevación). `space-y-12`.
- ADN del login trazado a fuentes reales: `.login-card` LoginPage.vue:758-771, `.welcome-headline` :838-843 (tokens.js:377), brand lockup tooth SVG con `--color-accent-500`, tinte plano `--color-accent-50` (el motivo dental es PNG+parallax, no reutilizable → solo tinte).
- D10: solo la entrada `/dashboard` del mapa de descripciones (AppLayout.vue:1018): "Resumen general del sistema" → "Operación del día".
- TDD: RED 21 failed → GREEN 194/194 JS (37 archivos, nuevos dashboard-hierarchy.test.js + DashboardHierarchyContractTest.php) + 613 PHP. ASSESS: primera llamada `unassessable` por archivos untracked (lección re-aplicada: git add antes de ASSESS); tras add → medium, reviewDue=true (1400 líneas), plan solo auto-verificación.
- "Acciones rápidas" eliminada (tiles re-listaban sidebar); handlers/iconos muertos retirados (goToPatients/Professionals/Environments/BusinessIntelligence/Calendar/Quotations/TreatmentPlans, 4 iconos, quickActionsSpring).
- Pendientes = UNA lista (`pendingRows`): nombre, monto (total_amount/final_cost, N/D fallback), badge de tipo (Presupuesto / Plan de tratamiento), badge de estado, fecha, UNA acción por fila que rutea a /quotations o /treatment-plans (el router no tiene rutas de detalle); ambos "Ver todos" de sección retirados; orden por fecha ascendente.
- Empty states compactos (DashboardSectionEmpty: p-6, icon h-8, un título + una oración); agenda sin CTA (copy "Cuando registres citas, aparecerán aquí."); upcoming de una sola oración. Quedan 3 usos: agenda, upcoming, pendientes.
- TDD: RED 16 failed → GREEN 184/184 JS + 607 PHP (2851 asserts). ASSESS: medium, reviewDue=true (1836 líneas), plan solo auto-verificación (writer profile large).
- Pill del header y botón "Ir a Caja" del header eliminados; la tarjeta "Saldo de Caja" es la única superficie (estado + saldo + caption Apertura + CTA único "Ir a Caja").
- Fuente única `useCashRegister` (currentSession/isOpen/hasActiveSession/realTimeTotals); `stats.cash_session` ya no se renderiza (stats solo como error-gate).
- TDD: RED 9 failed JS + 5 failed PHP → GREEN 185/185 JS + 607 PHP. Verificador independiente: PASS 4/4. ASSESS: medium, reviewDue=true (416 líneas), plan writer + verifier independiente.

## Entrega (2026-10-10)

- Push: `feat/dashboard-hierarchy-redesign` → origin (8 commits sobre `7d51f62`).
- Issue: **#129** (`feat(dashboard): hierarchy redesign and login parallax scene`) — alcance, no-alcance y verificación.
- PR: **#130** → `main`, `Closes #129`, label `type:feature`. MERGEADO (e2034c1) con CI verde; rama eliminada; issue #129 cerrado automáticamente.
- Iteración de diseño posterior al cierre (aprobada por el usuario, "está espectacular ahora sí"): WU5b ampliado con el escenario parallax del login (DentalMotifLayer.vue: 3 capas con la receta del login, deriva automática 58/46/34s, fondo `--color-canvas` sin verde, SIN seguimiento de puntero, reduced-motion). Commits `bf7b60c`, `ef05884`, `f0eb3ea`, `e073b1f`.
- Gate final del slice consolidado (`5f6dd99..e073b1f`, 365 líneas/6 paths): ASSESS `reviewDue=false` (under_budget) + verificador independiente PASS 5/5 → sin revisión nativa requerida por disposición.

## Evidencia de verificación (cierre 2026-10-10)

**Commits (rama `feat/dashboard-hierarchy-redesign`, base `7d51f62`)**
`960b382` KPIs del día · `5b11d8a` caja en una superficie · `58b46fb` pendientes unificados + acciones rápidas fuera · `5f6dd99` 3 bloques + lenguaje login · `bf7b60c` motivo parallax en vacíos + pulido (amend de 228325a con fix lint prefer-destructuring).

**Suites y guards (verificador independiente)**
- JS: 195/195 (37 archivos) · PHP MySQL (`phpunit.mysql.xml`): 1145/1145 (4708 asserts) · ESLint: 0 errores (1461 warnings preexistentes, no bloqueantes) · build ✓ · doc-drift 12/12.
- Nota de entorno: `.env.testing` pisa la conexión y `phpunit.mysql.xml` requiere exportar `DB_SOCKET=/opt/lampp/var/mysql/mysql.sock` + credenciales XAMPP locales (ya documentado en el runner).

**Verificación visual (v4/v5, medición + capturas)**
- KPIs: 4 tarjetas 260×170 con bottoms iguales (y=578) en desktop.
- Motivo de dientes escalado visible en los 3 empty states en los 3 viewports; con datos reales: superficie limpia (contrato `[data-empty-motif]` solo dentro de `[data-section-empty]`).
- Refresh del hero en el clúster superior junto al lockup en ambos viewports (móvil x=301,y=197; desktop x=1327,y=149; mismo chunk `DashboardPage-BQMKGxlh.js` con `data-hero-actions`).
- 0 errores de consola, 0 requests fallidas.
- Anomalía de tooling: el pipeline de adjuntos de imágenes del agente sirvió renders cacheados/rotulados mal en esta sesión; la verdad visual se verificó por medición de bounding boxes + contratos estructurales jsdom + capturas guardadas en /tmp/dashboard-verify/.

**Cobertura RDD**
- Incremento 1 (`7d51f62..5b11d8a`, WU1+WU2): review-37bbaa0c96f0075c **approved + burned** (advisory R3-STATUS-FALLBACK informativo).
- Incremento 2 (`5b11d8a..58b46fb`, WU3): review-9fc29c5b4bfdf206 **approved + burned** (advisory R3-pending-sort-null informativo).
- Incremento 3 (`58b46fb..5f6dd99`, WU4): review-89b8411db08448eb **approved + burned** (advisory R3-loading-shape-coverage informativo).
- WU5b (`bf7b60c`): ASSESS `reviewDue=false` (under_budget, 160 líneas) → auto-verificación TDD por disposición.
- Agregado final: NO ejecutado por la cláusula "si cabe" — 100% del contenido ya cubierto por incrementos aprobados (5190 líneas agregadas excederían el presupuesto del reviewer, como ya pasó en features anteriores con `lens_context_budget_exceeded`).

**Follow-ups abiertos (no-alcance, ver sección No-alcance)**
Bug backend `comparisons.total_patients` ("-100"); scoping `user_id` de `stats().cash_session` vs `/cash-register/current`; `completed_today/pending_confirmation/this_week` inexistentes en `stats()`; ingresos diarios sin backend; caché TTL 5 min + `ClearDashboardCache` sin `dashboard_pending_*`; advisories R3-* (informativos) como trabajo posterior.
