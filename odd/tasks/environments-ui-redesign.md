# Environments UI Redesign — CERRADO ✅

Rama: `feat/environments-ui-redesign` (desde `main` @ e2034c1)
Worktree: `/home/chomb/proyectos/odontosuite-wt/environments-ui-redesign`
Creado: 2026-10-11 · Orquestador: el Gentleman

## Objetivo

Llevar la interfaz de Ambientes (`/environments`) al mismo nivel de calidad y
lenguaje visual con el que quedó el dashboard (dashboard-hierarchy-redesign,
mergeado en main como PR #130): jerarquía clara, vocabulario del design system
sin ad-hoc, estados completos (carga/vacío/error), cero repeticiones y defectos
funcionales corregidos.

## Alcance (aprobado por el usuario: "Completo + ajustes backend")

1. Rehacer `EnvironmentsPage.vue` (lista) y `EnvironmentDetailPage.vue` (detalle).
2. Arreglar defectos funcionales detectados en la auditoría 2026-10-11:
   - `confirm({...})` invocado sin importar `useConfirm` (cae a `window.confirm`).
   - Filtro de estado muerto: `statusFilter` bindeado pero `filterEnvironments`
     ignora el valor.
   - Código muerto: `viewEnvironment` + modal "Ver" inalcanzable desde el template.
   - Duplicación: helpers de estado/variante duplicados entre lista y detalle.
3. Ajuste backend: `DentalChairController@index` acepta filtro real por `status`
   (`active|inactive|maintenance`), validado; sin romper el default actual.

## No-alcance

- Sin migraciones ni nuevos endpoints (el filtro va por query param en `index`).
- Sin cambios en el modelo `DentalChair` ni en la semántica `is_active`
  (archivado/visible) vs `status` (estado operativo).
- Sin hero band ni parallax: el "lenguaje del login" que hereda este módulo es
  vocabulario (tokens, hairline, elevation, heroicons, badges), no escenografía.
- No se toca `/environments` routing ni permisos (admin).

## Auditoría base (2026-10-11, evidencia path:line)

- Tabla cruda a mano (`EnvironmentsPage.vue:104-186`) en vez de `components/ui/DataTable.vue`.
- SVGs inline (`:15-27`, `:51-62`) en vez de `@heroicons/vue`; en detalle,
  spinner ad-hoc `:134` e iconos inline `:268-289`.
- Sin `UiSkeleton`, sin `useReducedMotion`, sin `--elevation-*` en el módulo.
- Columnas omiten `code` aunque Edit/Detalle lo muestran (`:148-160` vs `:250`).
- `index()` filtra `is_active=true` (DentalChairController.php:35): los valores
  `inactive|maintenance` del filtro sí existen entre filas activas; hoy el filtro
  no hace nada.

## Decisiones de diseño (arquitecto)

| # | Decisión | Motivo |
|---|---|---|
| D1 | Lista = tabla protagonista con `UiDataTable` (Código, Nombre, Equipamiento, Estado, acciones) | Es un módulo de master data; el patrón del repo es `components/ui/` siempre, y hoy la tabla viola esa convención |
| D2 | Vocabulario compartido `resources/js/modules/environments/environmentStatus.js` (variant + label) | Mataba la duplicación lista/detalle; una sola fuente de verdad por estado |
| D3 | Filtro de estado funcional: API param (T1) + filtro en cliente sobre filas cargadas | El filtro existe en UI pero no filtra; con `status` soportado en backend la lista es correcta y reactiva |
| D4 | Modal "Ver" eliminado; "Ver detalle" navega al detalle | Era código muerto inalcanzable; un solo camino al detalle evita superficies duplicadas (lección del dashboard) |
| D5 | Borrado con `useConfirm` (primitiva existente) | El `confirm` actual está roto (símbolo no resuelto) |
| D6 | Estados: `UiSkeleton` en carga, `UiEmptyState` en vacío, estado de error propio con retry (patrón `DashboardSectionError`) | Hoy no hay estado de error: un fallo de API deja la lista vacía en silencio |
| D7 | Detalle: header + tarjeta resumen (code, status badge, equipment) + `UiTabs` (Información / Auditoría), iconos heroicons, sin duplicar helpers | La estructura actual ya tiene tabs; se conserva la anatomía y se elimina todo lo ad-hoc |
| D8 | Motion mínimo o nulo, `useReducedMotion` si se introduce | CRUD de master data: la densidad calmada es la del login, no su escenografía |
| D9 | Tests anclados al contrato (bindings, variant mapping, data-*), no a literales de clases | Lección de dashboard-visual-coherence: los literales muertos pasan en verde y el render nunca cambió |

## Restricciones de estilo (heredadas)

- `pnpm` siempre; commits convencionales en inglés, sin emojis; sin comentarios pedagógicos.
- UI siempre desde `components/ui/`; Tailwind + design tokens; `<script setup>`.
- Copy de UI en español (convención del módulo); código e identificadores en inglés.
- Guards existentes que fijan contrato: `EnvironmentsAppShellTest` (AMB-01/AMB-02),
  `EnvironmentsModalChromeTest`, `EnvironmentsStatusBadgeTest`,
  `EnvironmentsCanvasRoutesPrefixTest`, `tests/js/smoke/environments.test.js`.
  Si el rediseño los cambia, se actualizan anclados al contrato nuevo, nunca se
  relajan para pasar.

## Disposición RDD (delegada por el usuario, 2026-10-11)

1. Toda work unit con TDD observado cuando aplique (RED → GREEN → refactor) y
   plan ASSESS cumplido en cada gate.
2. Revisión nativa del slice (rango commiteado, baseRef = frontera previa real +
   `committedOnly`) **SOLO cuando ASSESS marque `reviewDue=true`**.
3. El agregado final de la feature (frontera de la feature → HEAD) se revisa
   **UNA vez al cierre**, antes de entrega.
4. Los agregados intermedios (unión vs merge-base entre fronteras de slice)
   quedan **explícitamente sin revisar**, cubiertos por sus slices.
5. El consent RDD es por candidato y user-owned: esta disposición acota la
   cadencia, no reemplaza el consentimiento.

### Enmienda 2026-10-11 (tras el incidente del switch)

El switch RDD del clone quedó en `off (decided by clone_local)` durante la sesión
(el global seguía `on`); el usuario decidió: **rehabilitar el switch (hecho:
`gentle-ai review mode enable --scope clone` → `on (decided by global)`), sin
revisiones nativas por slice, y UNA única revisión nativa del agregado final de
la feature al cierre**. Los puntos 2-4 quedan así:

- T2 ya tenía su recibo quemado antes del incidente (`review-d0e5290b5e347634`).
- T3: verificado por verificador independiente (PASS 6/6), sin revisión nativa.
- T4/T5: plan ASSESS cumplido por slice (verificador independiente cuando lo
  pida), sin revisión nativa.
- Al cierre: revisión nativa UNA vez del agregado final (frontera `e2034c1` →
  HEAD) antes de cualquier entrega.

## Tasks

| # | Task | Estado | Commit |
|---|---|---|---|
| T1 | Backend: filtro `status` real en `DentalChairController@index` (test-first PHP) | ✅ hecho | 9823904 |
| T2 | Vocabulary compartido + rehacer lista (UiDataTable, heroicons, estados, filtro funcional, `useConfirm`, borrar código muerto) | ✅ hecho | 27ce570 |
| T3 | Modales crear/editar: chrome y jerarquía consistentes | ✅ hecho | 7279bb0 |
| T4 | Detalle: lenguaje del dashboard, shared vocabulary, estados completos | ✅ hecho | 7ba3b5a |
| T5 | Verificación visual (Playwright) + suites completas + sync docs y cierre | ✅ hecho | 3598fc1 |

## Verificación final (árbol final, 2026-10-11)

- JS: **215/215** (38 archivos) · PHP MySQL: **1153/1153** (4761 assertions, 0 fallos) ·
  ESLint: **0 errores** (backlog prettier preexistente, advisory) · build ✓ ·
  **doc-drift 12/12**.
- Visual (Playwright, 3 viewports + re-check): PASS 4/4 en los arreglos F2/F3/F5;
  capturas en `/home/chomb/proyectos/odontosuite-wt/env-ui-verify-screenshots/`
  (fuera del worktree a propósito).
- **Revisión nativa del agregado final** (frontera `e2034c1` → HEAD, 10 paths /
  2925 líneas): lens `review-reliability` **approved + burned**
  (`review-d3decc0d5d01b6b9`, evidencia `gentle-ai.review-acknowledged/v1`,
  `sha256:8d12ec2d…`). Advisories no bloqueantes: R3-001 y R3-002
  (tests/js/smoke/environments.test.js:424-428 y :291).

## Follow-ups abiertos (no bloquean)

- **F1** (minor): AppLayout duplica el header de página (patrón global, también en
  `/patients`); arreglarlo es de alcance cross-módulo.
- **F4** (minor): los seed data no tienen filas `inactive`/`maintenance` con
  `is_active=1`, así que los filtros no-activos no se ven con datos demo.
- **F6/F7** (cosmetic): tipografía del header "Acciones" y affordance de scroll
  horizontal en móvil viven en el primitivo `DataTable.vue` (fuera de alcance).
- `Button.vue` sin variante `link` (T3 re-ancló a `ghost`); `UiSelect`/`UiInput`
  sin `type="button"` (submit implícito dentro de `<form>` → modales como `<div>`);
  `EmptyState` con `icon` recibiendo Function (Vue warn en T2); advisories R3-*
  del review de T2 y R3-001/002 del agregado final.

## Evidencia

- **T1** `9823904` — `DentalChairController@index` con `status` validado (`sometimes|in:active,inactive,maintenance`); 7 tests DB (`tests/Feature/Modules/DentalChairStatusFilterTest.php`) RED 5/7 → GREEN 7/7 (MySQL socket, phpunit.mysql.xml). ASSESS: medium, `reviewDue=false` (under_budget) → sin revisión nativa; plan cumplido (self-verification del writer).
  Nota contrato: `?status=` vacío → 422 (presente = debe ser válido); el frontend omite el parámetro para "Todos".
- **T2** `27ce570` — lista rehacida sobre `UiDataTable` (Código/Nombre/Equipamiento/Estado/Acciones), `environmentStatus.js` compartido, heroicons, skeleton/empty/error+retry, filtro funcional (API param + client-side en search), `useConfirm` cableado, `<script setup>`. Vitest RED 5/7 → GREEN 7/7; suites JS 201/201, PHP Environments 260. ASSESS: medium, `reviewDue=true` (slice_budget_reached, 1090 líneas) → **revisión nativa del slice** (baseRef 9823904, committedOnly): lens `review-reliability` **approved + burned** (`review-d0e5290b5e347634`, evidencia `gentle-ai.review-acknowledged/v1`).
  Advisories no bloqueantes (trabajo posterior, no reabre): R3-EMPTY-STATE-COVERAGE (EnvironmentsPage.vue:79-92), R3-STATUS-FALLBACK-COVERAGE (environmentStatus.js:18-20).
  Riesgos heredados para T3/T5: `Button.vue` sin variante `link` (AMB-01-005 la exigía); `DataTable.vue` anida su panel dentro del panel de la lista (doble chrome) — juzgar en la pasada visual.
- **T3** `7279bb0` — modales crear/editar con jerarquía (Identificación/Descripción/Equipamiento/Estado), estado de guardado visible y coherente, modal Ver + `viewEnvironment` eliminados (D4), AMB-01-005 re-anclado a `variant="link"` → `variant="ghost"` (D-modal-link, `Button.vue` sin tocar). Vitest RED 4 → GREEN 11; JS 205/205, PHP Environments 260. ASSESS: medium, `reviewDue=true` (slice_budget_reached), plan self-verification + verificador independiente → **verificador PASS 6/6**; sin revisión nativa del slice por la enmienda RDD. Deviations documentadas por el writer: cuerpos de modal como `<div>` (bug preexistente: `UiSelect` sin `type="button"` dispara submit implícito dentro de `<form>` — follow-up compartido), copy a sentence case (4 strings de T2 tocadas deliberadamente), preview de Estado con `UiStatusBadge` en ambos modales (mata la asimetría y conserva el contrato ≥2 badges).
- **T4** `7ba3b5a` — detalle rehacido: vocabulario compartido (helpers duplicados eliminados), skeleton de carga (adiós "Cargando..." del título), error+retry para ambiente y auditoría, heroicons (adiós SVGs inline), layout de definición con hairlines, fix de contraste del avatar (`text-white` → `text-systemBlue-700` sobre `bg-systemBlue-50`, pin aditivo en el guard), tabs Información/Auditoría, diff de auditoría con variantes `getAuditActionVariant` + colores AMB-02-008. Vitest RED (contraste/vocabulario/estados/tabs) → GREEN; JS 213/213, PHP Environments 261, lint 0. ASSESS: medium + perfil large → plan self-verification cumplido; sin revisión nativa (enmienda RDD). Deviation notable: fetch de auditoría vía `useApi` local en vez de `useAuditLogs.getDentalChairAuditLogs` (ese composable traga errores y no permite retry).
- **T5** — verificación completa (suites 5/5: JS 213→215, PHP MySQL 1153/1153, lint 0, build ✓, doc-drift 12/12) + pasada visual Playwright (7 hallazgos F1-F7) + arreglos `3598fc1` (F2 equipamiento editable en edición con RED→GREEN, F3 código read-only con hint, F5 un solo marco para la tabla; guardas re-ancladas 8→9 bindings, UiTextarea ≥3→≥4) + re-check visual 4/4 + revisión nativa del agregado final approved+burned. Suites finales re-observadas sobre el árbol final: PASS.

Commits: 9823904 T1 · 27ce570 T2 · 7279bb0 T3 · 7ba3b5a T4 · 3598fc1 T5b · cierre de docs en este commit.
