# Patients Interface Redesign — CERRADA · ENTREGADA Y MERGEADA (PR #132)

## Objetivo

Rediseñar la interfaz de pacientes (lista + detalle) como módulo operativo
profesional con el lenguaje visual del dashboard/login (jerarquía de bloques,
anatomía KPI, estados vacíos con motivo dental, motion moderado con
reduced-motion) y cerrar los gaps funcionales de la lista. Una feature por
módulo, siguiendo el precedente de `dashboard-hierarchy-redesign`.

## Alcance

- Incluye: `resources/js/modules/patients/**`, primitivas compartidas en
  `resources/js/components/ui/**`, tests JS/PHP de pacientes, y
  `PatientController@index` solo para los gaps de orden/paginación/filtro que la
  UI necesite.
- Excluye: el módulo dashboard (salvo la actualización de imports si se promueve
  `DentalMotifLayer` a compartido, protegido por su suite), otros módulos, y el
  delivery (push/PR/merge = decisión del usuario).

## Contexto de partida (auditoría scout 2026-10-10)

- `PatientsPage.vue` (~1183 líneas, Options API + `setup()`): 4 stat cards ad-hoc
  (gradientes, `font-feature-settings` inline), dos superficies de fila
  duplicadas (tabla desktop + cards mobile), sin `h2`, errores solo por toast,
  ~15 SVG inline, y `confirm()` sin importar en `deletePatient` (cae a
  `window.confirm`, flujo `useConfirm`/ConfirmDialog roto).
- `PatientDetailPage.vue` (~1546 líneas): 6 pestañas que repiten la misma
  anatomía de loading/empty/lista, iconos como objetos `template:` string, edit
  modal con `UiInput type="textarea"` en vez de `UiTextarea`, loading con
  `animate-spin` ad-hoc.
- Backend: `PatientController@index` ya soporta `search`, `branch_id`, `active`,
  `per_page` (máx 100, default 15) y meta `active_count`/`inactive_count`; la UI
  no envía `branch_id` ni `per_page`, no hay control de orden (orderBy hard-coded
  `last_name, first_name`) y `GET patients/search` no se usa.
- Tests que fijan el estado actual: `tests/js/smoke/patients.test.js` (mínimo),
  `tests/Unit/DesignSystem/PatientsListAppShellTest.php` (pins estructurales:
  stat cards `clickable`, `font-feature-settings` inline ×N, import legacy de
  `Pagination`), `PatientsModalAppShellTest.php` (fija `<script>` byte-for-byte),
  `PatientDetailAppShellTest.php`, `PatientDetailEditExportAppShellTest.php`.

## Decisiones

- Cadencia RDD delegada al arquitecto («decide tú, sin bajar calidad»): TDD
  observado por work unit (RED → GREEN → refactor) + revisión nativa por rango
  commiteado según ASSESS, como en `dashboard-hierarchy-redesign`.
- Worktree dedicado: `~/proyectos/odontosuite-patients`, rama
  `feat/patients-interface-redesign` desde `main` (e2034c1).
- Los pins de tests se re-anclan al contrato, no a literales de clases (lección
  de dashboard-visual-coherence T4): se conservan pins de contrato (echo
  channels, envoltorio 422, `useApi`, deep links) y se actualizan los
  estructurales que la reestructuración invalida.

## Tareas (work units)

- [x] T1 — Fix del flujo de borrado: `confirm(...)` sin importar en
  `deletePatient` cae a `window.confirm`; cablear `useConfirm` +
  `ConfirmDialog` como el resto de módulos. Test-first.
  — Cerrado con commit `fb4c307` (`fix(patients): wire useConfirm flow for
  delete confirmation`). TDD observado: RED 2 failed (spy de `confirm` 0
  llamadas) → GREEN 2 passed; suite JS completa 197/197; ESLint 0 errores en
  archivos tocados. `UiConfirmDialog` vive montado en `AppLayout.vue`, así que
  el fix fue solo el import/bindings faltantes (2 líneas).
- [x] T2 — Gaps funcionales de la lista: búsqueda server-side con debounce,
  control de `per_page`, filtro de sucursal (`branch_id`), y orden (`sort` en
  `PatientController@index` + select en la UI).
  — Cerrado con commit `722ce7d` (`feat(patients): debounced search, per-page,
  branch filter and sort`). TDD observado: RED (4 JS + 5 PHP failed) → GREEN
  (5+5). Suite JS 39 files/202 tests ✓; PHP no-DB 26 ✓; ESLint 0 errores
  (warnings = baseline 103). Sin re-anclaje necesario: los 16 pins de
  `PatientsListAppShellTest` pasan intactos. Extra in-surface reportado:
  restaurado `statusFilterOptions` (el select de estado no tenía opciones).
- [x] T3 — Primitivas compartidas del lenguaje visual: `DentalMotifLayer`
  promovido a `components/ui` + `UiSectionEmpty` reutilizable; imports del
  dashboard actualizados; suite del dashboard como guard.
  — Cerrado con commit `5692cd5` (`refactor(ui): shared DentalMotifLayer and
  UiSectionEmpty primitives`). TDD observado: RED (import inexistente) → GREEN
  (8/8). `DashboardSectionEmpty` es ahora wrapper fino de `UiSectionEmpty` con
  DOM idéntico. JS 210 ✓; PHP dashboard 57 ✓; pacientes diseño 51 ✓; ESLint 0
  errores. Re-anclados 4 pins estructurales de `DashboardAppShellTest` (el
  markup auditado vive ahora en `UiSectionEmpty`) + 1 pin nuevo de delegación.
- [x] T4 — Restructura de `PatientsPage` (estructura de página): hero con
  lenguaje del login, jerarquía `h2` (bloques), anatomía KPI strip (cards no
  interactivas, eyebrow + número + caption), superficie de filas unificada
  (componente de fila compartido entre tabla y cards mobile), empty/error
  unificados.
  — Cerrado con commit `2235294` (`feat(patients): list redesign with login
  visual language`). TDD observado: RED (11 smoke + 4 PHP failed) → GREEN
  (11+11 smoke; PHP 40/40). JS 221 ✓. Re-anclados 2 pins estructurales
  (stat cards `clickable` → no interactivas: no tenían handler; tabular-nums
  reubicado a `PatientListRow`); contrato (echo, useApi, 422, Pagination)
  intacto. Cambios de comportamiento reportados: sin CTA "Limpiar filtros" en
  el empty state (lo tiene la barra de filtros), bloque de error inline con
  reintento (antes solo toast), pill de estado → `UiBadge`. Decisión de
  arquitecto aplicada: los pesos de `h2` mapean por IMPORTANCIA (directory
  `text-2xl` protagonista, summary `text-xl`), no por orden de fuente.
- [x] T4b — Consolidación de los modales crear/editar en un formulario
  compartido (hoy duplican el mismo formulario de 14 campos) + fix de
  `genderOptions`/`statusOptions` sin definir en `setup()` (selects sin
  opciones, hallazgo preexistente reportado en T2) + `UiTextarea` en vez de
  `UiInput type="textarea"`.
  — Cerrado con commit `72b0ca7` (`refactor(patients): shared create/edit
  patient form modal`). TDD observado: RED (7 failed / 1 passed) → GREEN (8/8).
  JS 42 files/229 ✓; PHP modales+lista 33 ✓; ESLint 0 errores (26 warnings <
  baseline 54). Re-anclados 8 pins de `PatientsModalAppShellTest` de literales
  a contrato (el byte-for-byte del `<script>` murió; 422 ahora además fija que
  el formulario NO se cierra en error). Nota: el árbol contenía un intento
  parcial previo de la delegación caída por internet; el worker lo completó en
  vez de descartarlo.
- [x] T5 — Restructura de `PatientDetailPage`: cabecera consistente con la lista,
  anatomía unificada por pestaña, `UiTextarea` en el modal de edición,
  heroicons en vez de SVG/template-string inline, loading unificado, `h2`.
  — Cerrado con commit `0f6f637` (`feat(patients): detail redesign with shared
  tab anatomy`). TDD observado: RED (11 smoke + 1 pin PHP failed) → GREEN
  (11/11 + suite 56/56). `PatientTabPanel` compartido para las 6 pestañas;
  edición re-usa `PatientFormModal` (14 campos, delta 0). Re-anclados 7 pins de
  `PatientDetailEditExportAppShellTest` + pin nuevo "sin segunda forma";
  `PatientDetailAppShellTest` intacto.
- [x] T6 — Re-anclaje final de tests al contrato + verificación completa (JS,
  PHP unit, ESLint, build, doc-drift) + pasada visual Playwright 3 viewports +
  sync de AGENTS.md si cambia la superficie medible.
  — Cerrado: verificación completa en verde (ver sección inferior); pasada
  visual 10/10; `doc-drift` 12/12 → AGENTS.md no requirió cambios.

## Evidencia

- T1: `fb4c307` — fix(patients): wire useConfirm flow for delete confirmation.
  RED→GREEN observado en `tests/js/smoke/patients-delete-confirm.test.js` (2
  tests), `pnpm test:js` 38 files/197 tests ✓, ESLint 0 errores (103 warnings
  preexistentes, baseline idéntico).
- T2: `722ce7d` — feat(patients): debounced search, per-page, branch filter and
  sort. RED→GREEN observado (JS 4 failed→5 passed; PHP 5 failed→5 passed),
  `pnpm test:js` 39 files/202 tests ✓, PHP no-DB 26 ✓, ESLint 0 errores. Filtro
  de sucursal vía `GET branches/active` (abierto a todo autenticado; se oculta
  si la carga falla). `sort`/`direction` inválidos devuelven 422 (validación
  estricta) con fallback defensivo `sortColumns`/`sortDirection`.
- T3: `5692cd5` — refactor(ui): shared DentalMotifLayer and UiSectionEmpty
  primitives. RED→GREEN observado (8 tests), `pnpm test:js` 40 files/210 ✓,
  PHP dashboard 57 ✓ + pacientes diseño 51 ✓ (byte-for-byte de modales intacto
  tras T2), ESLint 0 errores. Commit cerrado por el orquestador (el worker de
  esta WU tenía git cerrado por su frontera de seguridad).
- T4: `2235294` — feat(patients): list redesign with login visual language.
  RED→GREEN observado (11 smoke + 4 PHP), `pnpm test:js` 41 files/221 ✓, PHP
  diseño pacientes 40 ✓ (modales byte-idénticas verificadas con `diff`),
  ESLint 0 errores (54 warnings < baseline 103). Hero + bloques
  `data-patients-block` + KPI strip + `PatientListRow` compartido +
  `UiSectionEmpty` + heroicons (1 glyph de marca inline intencional) + motion
  one-shot 60ms con fallback reduced-motion.
- T4b: `72b0ca7` — refactor(patients): shared create/edit patient form modal.
  RED→GREEN observado (8 tests), `pnpm test:js` 42 files/229 ✓, PHP diseño 33 ✓,
  ESLint 0 errores. `PatientFormModal.vue` (`<script setup>`, mode
  create|edit) reemplaza los dos `UiModal` duplicados; contrato de requests
  (POST/PUT), envoltorio 422 y `@close` preservados; `genderOptions`/
  `statusOptions` definidos.
- T5: `0f6f637` — feat(patients): detail redesign with shared tab anatomy.
  RED→GREEN observado (11 smoke + 1 pin PHP), `pnpm test:js` 43 files/240 ✓,
  PHP diseño 56 ✓ + `PacientesNegativeSpaceRulesTest` 7 ✓, ESLint 0/0.
  Nota de revisión: `UiTabs` nunca estaba importado en la página; ahora sí.

## Verificación final (T6)

- Verificador independiente (gentle-ai-verify): `pnpm test:js` 240/240 ✓ ·
  `pnpm lint:check` 0 errores ✓ · `pnpm build` ✓ · `doc-drift` 12/12 ✓ · PHP
  no-DB 681 ✓ · diff del branch: 21 archivos, +4.689/−2.224.
- Suite MySQL: bloqueada en el primer intento por credenciales docker-compose
  de `phpunit.mysql.xml` sin contenedor; resuelta contra XAMPP real
  (DB_SOCKET + root): **1155 passed (4809 assertions)**.
- Pasada visual Playwright: **10/10 PASS** en 3 viewports (1440×900, 768×1024,
  390×844), 16 capturas en `/tmp/patients-visual/`. Selects de género/estado
  verificados poblados (bug cerrado), una sola superficie de filas por
  breakpoint, empty state con motivo, consistencia con el dashboard 10/10.
  Notas NO bloqueantes: (1) botón "Nuevo paciente" pegado al borde derecho del
  hero en 390px; (2) `h1` duplicado (banner AppLayout + hero; preexistente
  también en dashboard); (3) modales no cierran con Escape (UiModal
  compartido); (4) capas decorativas del motivo exceden el ancho pero con
  `overflow-hidden`.
- Cadencia RDD (switch global ON): candidato agregado `e2034c1..HEAD` (7.321
  líneas) → `lens_context_budget_exceeded`, SIN autoridad creada (terminal
  para ese candidato: reducir alcance o desactivar). ASSESS con hashes
  reales: riesgo **medium** (`executable_change`), plan = auto-verificación
  del writer (sin verificador separador exigido); `reviewDue: true`
  (`slice_budget_reached`) con ruta nativa POR SLICE vía `--base-ref` (slice
  T5 verificado: 4 rutas, 2.561 líneas, bajo presupuesto). Cobertura aplicada
  según la disposición delegada: TDD observado por WU (auto-verificación del
  writer) + verificador independiente extra (pase completo + visual). Las
  revisiones nativas por slice quedan como decisión del usuario (cada
  candidato exige su consentimiento).

## Entrega

- Merge: **`6e4c4ce`** (`Merge pull request #132`) en `main`, 2026-10-12;
  issue **#131** cerrado por `Closes`; ramas local (`b079251`) y remota
  eliminadas; worktree `~/proyectos/odontosuite-patients` retirado
  (este registro se preserva en `odd/tasks/` del repo principal).
- Antes del merge, la política de `main` bloqueó por el check flaky del run de
  push (`ReminderDispatchTest`, ajeno a la feature); re-ejecución del job →
  `mergeStateStatus: CLEAN` → merge sin saltar ninguna protección.

- Rama `feat/patients-interface-redesign` publicada; issue **#131**
  (`type:feature`, `area:frontend`); PR **#132** → `main` (`Closes #131`,
  `type:feature`).
- CI del PR: Quality gates ✓ · Backend tests (MySQL) ✓ · Frontend build ✓.
- CI del push a la rama: 1 fallo flaky en `ReminderDispatchTest` (recordatorios,
  ajeno a esta feature; 3/3 verde local; test sensible al borde de 24h) —
  documentado como follow-up.
- Pulido post-visual añadido (2 commits): `806095f` (gap del hero en móvil,
  `h1` único del app-shell, Escape cierra modales — causa raíz: listener
  registrado solo vía `watch` con `model-value` estático) y `b079251`
  (labels de botones de una línea en xs). Re-check Playwright: 3/4 →
  corregido el restante → contratos fijados en pins.

## Follow-ups abiertos

- `GET /patients/search` sigue sin uso (decidir: conectar o eliminar).
- Restore de pacientes soft-deleted: no hay endpoint `trashed`/`restore`; fuera
  de alcance de esta feature.
- Export CSV desde la lista (el detalle ya exporta); fuera de alcance.
