# AI Analysis Interface Redesign

**Branch**: `feat/ai-analysis-interface-redesign` (worktree `~/proyectos/odontosuite-ai-analysis`, base `main` @ b3c168a)
**Estado**: ✅ MERGEADO en `main` (PR #178, 2026-10-20)
**Inicio**: 2026-10-20

Rediseño de la interfaz `/ai-analysis` al lenguaje de superficie del dashboard
(hero band, KPI strip, filtros, estados, motion), con los arreglos de contrato
que la interfaz necesita para no mentir. Solo frontend + endurecimiento aditivo
del backend de IA.

## Specs

**S1 — Alcance (L1, verbatim).** Rediseñar la interfaz de Análisis IA del
proyecto, en la dirección visual/estructural que dejó el dashboard, dentro de un
worktree nuevo.

**S2 — Contrato con la app intacto.** Se conservan `path: '/ai-analysis'`,
`name: 'ai-analysis'` (`resources/js/app.js:188-192`), el item de navegación
`{ name: 'Análisis IA', to: '/ai-analysis', roles: ['administrador','odontologo','implantologo','tecnico_dental'] }`
(`resources/js/components/layout/AppLayout.vue:921-929`) y la pertenencia de
`/ai-analysis` a `canvasRoutes` (`AppLayout.vue:556`, guard
`tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php:56`). No se añaden rutas
HTTP ni módulos nuevos.

**S3 — Copia de UI en español, literal.** Sobreviven estas cadenas exactas:
`"Análisis con IA"` (aserción de `tests/js/smoke/ai-analysis.test.js:36`),
`"Nuevo análisis"`, `"Actualizar"`, `"Total de análisis"`, `"Completados"`,
`"Pendientes de revisión"`, `"Tasa de completado"`, `"Buscar por paciente"`,
`"Todas"`, `"Pendientes"`, `"Completados"`, `"Fallidos"`, `"Revisión"`,
`"Revisados"`, `"Sin revisar"`, `"Desde"`, `"Hasta"`, `"Limpiar filtros"`,
`"Sin análisis registrados"`, `"Reintentar"`, `"Imagen"`, `"Paciente"`,
`"Tipo"`, `"Estado"`, `"Confianza"`, `"Fecha"`, `"Acciones"`,
`"Nuevo análisis de IA"`, `"Analizar con IA"`, `"Cancelar"`,
`"Selecciona un paciente."`, `"Selecciona una imagen."`,
`"La imagen supera los 20 MB."`, `"Solo se aceptan imágenes."`,
`"Hallazgos"`, `"Recomendaciones"`, `"Metadatos"`, `"Decisión"`, `"Aceptado"`,
`"Rechazado"`, `"Parcial"`, `"Notas de revisión"`, `"Guardar revisión"`,
`"Eliminar análisis"`, `"¿Estás seguro de que quieres eliminar este análisis?"`,
`"Eliminar"`.

**S4 — Restricciones de diseño vigentes en el repo.** El código nuevo de este
módulo no usa `<style scoped>` (deuda registrada en
`tests/Unit/DesignSystem/ModuleAppShellTestCase.php:42`), ni `bg-gradient-*`
(`tests/Unit/DesignSystem/DashboardAppShellTest.php:69-86`), ni `text-[11px]`
(`:1382`), ni alias legacy de color (`tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php:41-70`),
ni `border-theme`, ni `text-ink-*`, ni `systemBlue`. Iconos desde
`@heroicons/vue`; primitivas desde `resources/js/components/ui/`; colores,
radios y elevación por token (`--color-canvas`, `--color-hairline`,
`--radius-panel`, `--radius-control`, `--elevation-2`) como en
`resources/js/modules/procedure-catalog/ProcedureStatsPage.vue:3-64`. El título
de página es `<h2 data-hero-title>`: el `<h1>` del documento lo posee el topbar
(`tests/js/smoke/page-header-hierarchy.test.js:5-8`).

**S5 — La lista se lee del servidor, no se trocea en cliente.** Paginación,
búsqueda y filtros viajan a `GET /api/ai-analysis` y la UI consume su `meta`
(`current_page`, `last_page`, `per_page`, `total`,
`app/Http/Controllers/Api/AiImageAnalysisController.php:233-247`). Prohibido
repetir el patrón actual de pedir una página de 15 y paginar en memoria
(`resources/js/modules/ai-analysis/AiAnalysisPage.vue:577-582`). Parámetros
aditivos nuevos del endpoint: `per_page` (entero 1..100, default 15) y `search`
(texto ≤100, coincide con nombre y apellido del paciente). Los filtros
existentes (`status`, `reviewed`, `patient_id`, `requested_by`, `date_from`,
`date_to`) siguen funcionando y sus valores vacíos se ignoran.

**S6 — Errores y confirmaciones visibles, sin `alert()`.** Un fallo de carga se
muestra como bloque `data-state="error"` con `role="alert"` y acción
`data-retry` que reintenta la carga; no se traga el error ni se vacía la
pantalla. Las acciones destructivas pasan por `UiConfirmDialog`/`useConfirm`.
No se introduce `alert()` nuevo en el módulo. Las primitivas compartidas
(`FileUpload.vue`, `PatientSelector.vue`) no se modifican: la validación de
tipo/tamaño del formulario de análisis vive en el page-local.

**S7 — Backend: 404 honesto y sin filtrar internals.** `DELETE /api/ai-analysis/{id}`
sobre un id inexistente responde 404 `{"message":"Análisis no encontrado"}`
(hoy 500 por el `catch` genérico); sobre un análisis revisado sigue respondiendo
422 con `"No se puede eliminar un análisis que ya ha sido revisado"`. Ninguna
respuesta 500 del `AiImageAnalysisController` incluye la clave `error` con el
mensaje de la excepción: se registra en `Log` y el cuerpo queda
`{"message":"<mensaje de dominio>"}`. Cubierto por tests de feature nuevos en
`tests/Feature/Api/`.

**S8 — Verificación.** `pnpm test:js`, `pnpm lint:check`, `pnpm build` y
`php artisan test --configuration=phpunit.mysql.xml` en verde, más verificación
visual en navegador de `/ai-analysis` en estados vacío, con datos, filtrado y
error. Los guards existentes que enumeran este módulo se actualizan en el mismo
slice que los rompe.

## Tasks

| ID | Spec | Tarea | Ruta | Commit |
|---|---|---|---|---|
| T1 | S5, S7 | Backend: `per_page`+`search`, filtros vacíos ignorados, 404 en `destroy`, 500 sin `error` | inline (test-first) | 14fd510 |
| T2 | S3, S4, S5, S6 | Rebuild de página: hero, KPI strip, filtros servidor, tabla+paginación, estados, motion, sin `<style scoped>` | worker (backstop de contexto, test-first) | 6cac25d |
| T3 | S3, S6 | Flujo "Nuevo análisis" en Sheet con pasos y validación local (reemplaza el formulario inline) | worker | 29e1982 |
| T4 | S3, S6 | Detalle y revisión: modal, hallazgos, revisión con gate, borrado con confirm | worker | 3235aef |
| T5 | S8 | Suites, lint, build, verificación visual, guards restantes y docs | inline + verify | 88792ed (fixes del pase visual) + 1ac6038 (docs) |

Superficies disjuntas por slice: T1 (`AiImageAnalysisController`, `AiImageAnalysisService`, `tests/Feature/Api/AiAnalysisContractTest.php`) corre en paralelo con T2 (`AiAnalysisPage.vue`, `AiAnalysisCard.vue`, `useAiAnalysis.js`, `tests/js/smoke/ai-analysis.test.js`); T3 (`AiAnalysisSheet.vue` nuevo, `AnalyzingModal.vue`) y T4 (`AiAnalysisModal.vue`) van en serie tras T2.

## Log

- **L1 (verbatim, 2026-10-20):** "continuando con este proyecto conforme dejamos
  el dashboar quiero hacer ahora la interfaz de Analisis IA, para hacer ello en
  un worktree nuevo"
- **L2 (decisión del orquestador, 2026-10-20):** el rediseño incluye el
  endurecimiento aditivo del contrato de lista y de errores (S5, S7) porque la
  interfaz nueva no puede paginar en cliente ni exponer el mensaje de la
  excepción. No se agregan endpoints, migraciones ni cambios de permisos.
- **L3 (hallazgo de exploración, 2026-10-20):** la página actual pide una sola
  página de 15 análisis y pagina/filtra en memoria; `destroy` responde 500 ante
  id inexistente; las 500 devuelven `error: $e->getMessage()` en todo el
  controller; el módulo no tiene tests de feature ni guard de app-shell activo
  (`ModuleAppShellTestCase.php:42` solo lo lista como deuda de `<style scoped>`).
- **L4 (entorno, 2026-10-20):** worktree creado desde `b3c168a`; `pnpm install`
  + `vendor` listos; `.env` copiado del worktree principal (gitignored).
- **L5 (routing, 2026-10-20):** T2 absorbe la lista completa y la eliminación del
  bloque `<style scoped>` en un solo slice: repartir el desmontaje del CSS entre
  dos slices produciría cableado desechable y diff duplicado. El slice queda
  ~600 líneas (dentro del rango que este repo ya aceptó en `dashboard-ops-redesign`).
  T1 y T2 se lanzan en paralelo por superficies disjuntas; T3 y T4 en serie.

- **L6 (T1, 2026-10-20):** RED 6 failed/4 passed (39 aserciones) → GREEN 10/10 (71
  aserciones) con `php artisan test --configuration=phpunit.mysql.xml` contra
  MySQL de XAMPP en `odontosuite_ai_analysis_test` (socket). Commit 14fd510,
  380 líneas autoriales: `AiImageAnalysisController` (per_page 1..100, `search`
  por nombre de paciente con LIKE + CONCAT, filtros vacíos ignorados vía
  `filled()`/comparación a `''`, 404 explícito en `destroy` y `show`, `Log::error`
  en lugar de `error` en el cuerpo) + `tests/Feature/Api/AiAnalysisContractTest.php`.
  Incidente: el engine MySQL de 127.0.0.1:3307 murió a mitad de la segunda corrida
  (`MySQL server has gone away`); el puerto es compartido con otros worktrees. Se
  cambió a XAMPP + base dedicada, igual que la sesión de Caja.
- **L7 (assess T1, 2026-10-20):** `gentle-ai review assess --base-ref
  b3c168ab1c964adc7a54b59eb771ac4a987921f0 --committed-only` → risk medium,
  2 paths / 380 líneas, `review due: no (under_budget)`, razón
  `executable_change`. El facade con `input.baseRef` devolvió `unassessable`
  ("no pending changes"): el camino fiable aquí es la CLI con `--committed-only`.
- **L8 (política de escritores, 2026-10-20):** el gate del harness rechazó los
  `gentle-ai-worker` hasta que el contexto del padre superó el backstop medido;
  T1 se hizo inline y T2 se delegó al dispararse ese backstop (150k tokens).

## Estrategia de entrega (decidida por el orquestador, 2026-10-20)

- Una sola rama de feature (`feat/ai-analysis-interface-redesign`) y **un PR al
  cierre** contra `main`; push/PR/merge son decisión del usuario.
- Candidatos de revisión = commits de work unit, no el agregado de la rama:
  `14fd510` T1 (380 líneas, assess medium/under_budget), `6cac25d` T2
  (4 paths, 1810 líneas de diff, assess medium/under_budget: el churn incluye la
  reescritura in-place de la página y el borrado de la tarjeta muerta),
  `3235aef` T4 (2 paths, 1123 líneas de diff, modal reescrito in-place + test
  nuevo 309: 10/10 verde),
  `29e1982` T3 (4 paths, 1081 líneas de diff: Sheet nuevo 242 + página
  246/212 + AnalyzingModal 145/54 + test 448), `3d27c6a` pin de acciones y gate
  de permiso (3 paths, 259 líneas: 2 tests nuevos + fix de una línea).
- Límite de slice registrado después de cada commit; un slice que exceda ~400
  líneas de diff se documenta aquí con su razón (T2: reescritura in-place de un
  único archivo + eliminación del componente muerto) y se revisa por separado.
- La verificación funcional (suites, lint, build, navegador) es la de T5 y cubre
  el agregado; los recibos por commit cubren la introducción de cada unidad.

- **L9 (fixtures para verificación visual, 2026-10-20):** la base de desarrollo
  (`odontosuite` en XAMPP) tiene 100 pacientes pero **0** `clinical_attachments`
  y **0** `ai_image_analyses`. Para el estado con datos de T5 hay que sembrar
  filas offline (INSERT de 3-4 adjuntos + análisis sobre pacientes existentes y
  un usuario clínico, más una imagen PNG en `storage/app/public/clinical-attachments/`)
  y borrarlas al terminar; el flujo real de subida llama al servicio de IA
  externo y no se usa para verificar.

- **L10 (T4, 2026-10-20):** modal de detalle reescrito in-place, commit `3235aef`.
  RED 10/10 → GREEN 10/10; higiene limpia (sin `<style>`, gradientes ni alias
  legacy). El worker interpretó "el formulario se oculta hasta elegir decisión"
  como "el formulario se muestra y el submit queda deshabilitado" (manda la
  restricción 4); el test fija submit deshabilitado → habilitado → emit exacto.
- **L11 (T3, 2026-10-20):** commit `29e1982`. RED 12/12 → GREEN 12/12; con el
  smoke de T2, 22/22. La página quedó sin el formulario inline y la tarjeta
  `AiAnalysisCard` ya no existe. Carrera entre workers: T3 leyó el modal antes
  de que T4 añadiera el evento `delete`, así que el padre cableó
  `@delete="deleteAnalysis"` y normalizó el handler a `id` (la fila pasa
  `row.id`); `tests/js/smoke/ai-analysis-actions.test.js` fija ambos caminos de
  borrado y el de revisión.
- **L12 (hallazgo latente corregido, 2026-10-20):** `AiAnalysisButton.vue`
  llamaba `can('ai-analysis.analyze')` sobre el **objeto** de permisos (la API
  real es `can['ai-analysis.analyze'].value`), un `TypeError` que sólo no se
  manifiesta porque la base demo no tiene adjuntos. Fix de una línea + 3 tests
  (`3d27c6a`), incluido el comportamiento previo: una foto clínica no habilita
  el botón.
- **L13 (integración, 2026-10-20):** la corrida completa de JS dentro del slice
  T3 reportó `3 failed files / 4 failed tests` sobre 69 archivos, con los cinco
  archivos de ai-analysis pasando juntos `39/39` después. Se repite la suite
  completa en T5 y se compara contra la baseline de `main` antes de afirmar
  verde; la causa probable es contención de CPU (varias sesiones corriendo
  suites a la vez).

- **L14 (verificación visual en navegador, 2026-10-20):** con la app servida en
  `:8000` y datos sembrados offline (3 adjuntos + 3 análisis sobre pacientes
  demo, imágenes PNG reales), se verificó con Chromium/Playwright: hero e
  indicadores (3 / 2 / 1 / 66.67%), tabla con nombres, presets Pendientes y
  Fallidos (1 fila cada uno, `aria-pressed` correcto), "Limpiar filtros" (3 de
  vuelta), modal de detalle (hallazgos, recomendaciones, metadatos, imagen
  512 px, submit deshabilitado, acción de borrado), análisis revisado (decisión,
  notas, sin formulario y sin borrado), Sheet de alta (4 pasos, paciente
  seleccionado, imagen 15.29 KB, submit habilitado), móvil 390 px sin overflow
  horizontal (KPIs apiladas, hero 278 px), `prefers-reduced-motion` con hero en
  opacidad 1 y sin transform, estado de error con `role=alert` + "Reintentar"
  (recupera 3 filas) y vacío filtrado con la copia exacta.
- **L15 (defectos encontrados y corregidos en la verificación visual, 2026-10-20):**
  (1) la tabla y el modal mostraban "Paciente" / "Paciente no disponible" porque
  la API serializa `patient` sin el accessor `full_name`; ahora la página y el
  modal componen `first_name + last_name` y dos tests lo fijan. (2) el hook
  `data-ai-detail` se perdía al cruzar `UiModal` (fallthrough de atributos), por
  lo que existe en tests y no en el DOM real; se movió a un `div` explícito.
  Ambos fixes quedan en un commit propio de cierre de verificación.
- **L16 (suites finales, 2026-10-20):** JS `71 archivos / 583 tests` en verde y
  build de producción OK antes del pase visual; PHP `1454 passed (6381
  assertions)` con la salvedad conocida de exit 1 por el `--configuration`
  duplicado del wrapper de artisan (0 `FAILED` en el log). Tras L15 se repiten
  la suite JS completa y el formato.

## Cierre (2026-10-20)

- **Commits**: `14fd510` T1 · `6cac25d` T2 · `3235aef` T4 · `29e1982` T3 ·
  `3d27c6a` pin de acciones/gate · `88792ed` fixes del pase visual ·
  `1ac6038` changelog de AGENTS.md.
- **Assess nativo (RDD encendido)**: T1 380 líneas `under_budget`; T2 1810
  `under_budget`; T4 1123 `under_budget`; T3+pin 1340 `under_budget`; fix 63
  `under_budget`; agregado de la rama (15 paths, 4444 líneas) `under_budget`.
  Ningún candidato quedó `reviewDue`, así que no se creó linaje ni se pidió
  consentimiento: la verificación funcional es la evidencia de cierre.
- **Suites**: JS `71 archivos / 584 tests` verde; PHP `1454 passed (6381
  assertions)` con la salvedad conocida del exit 1 por `--configuration`
  duplicado (0 `FAILED`); `pnpm build` OK; Prettier y ESLint limpios en lo
  tocado; `scripts/audit/doc-drift.mjs` 12/12.
- **Sin cambios de contrato externo**: no hay rutas HTTP nuevas, ni migraciones,
  ni primitivas UI nuevas; los conteos de §4 de AGENTS.md siguen válidos.
- **Pendiente**: PR a `main` (decisión de entrega del usuario) y sacar `odd/`
  del worktree no hace falta (está excluido por `.git/info/exclude`).

- **Entrega (2026-10-20)**: rama `feat/ai-analysis-interface-redesign` publicada
  y **PR #178** abierto contra `main`
  (https://github.com/ArnoldA123/OdontoSuite/pull/178). Merge pendiente de
  decisión del usuario; CI observada con `gh pr checks 178 --watch`.

- **L17 (CI del PR #178, 2026-10-20):** primera corrida: `Frontend build` pass;
  `Quality gates` fail por Prettier sobre `AGENTS.md` (mi entrada del changelog
  no la había pasado por `prettier --write`); `Backend tests (MySQL)` fail por
  una flakiness **preexistente** de payment-methods: `PaymentMethodFactory`
  generaba `code` como `PM-##` (90 combinaciones) y el índice único reventaba al
  crear dos filas en el mismo test (`Duplicate entry 'PM-91'`,
  `PaymentMethodAdminContractTest.php:59`, archivo ajeno a esta feature). Fixes
  en commits `6e0685c` (factory con cola `???###`) y `b567e7c` (Prettier del
  changelog); `pnpm format:check` repo-wide en verde y la clase de payment
  methods 6/6. Segunda corrida de CI en curso.

- **L18 (CI verde, 2026-10-20):** segunda corrida del run
  `39465755703`: `Backend tests (MySQL)` pass 2m12s, `Quality gates` pass 2m5s,
  `Frontend build` pass 28s. Comentario publicado en el PR con las dos causas y
  sus commits. El merge queda en manos del usuario; la rama y el worktree se
  conservan.

- **L19 (segunda fase, 2026-10-20):** `main` avanzó 61 commits mientras el PR
  estaba abierto (especialidades, presupuestos, historias clínicas). Se integró
  con merge `5f298b8`; único conflicto `AGENTS.md` §12 (changelog): se conservó
  mi entrada seguida de las de `main`. Post-merge: JS `89 archivos / 722 tests`,
  PHP `1624 passed (7578 aserciones, 0 FAILED)`, `pnpm format:check` repo-wide y
  doc-drift 12/12. Se quitó un `Log::info` de depuración que registraba la
  descripción libre del paciente en cada subida (`2f10e9e`).
- **L20 (follow-ups convertidos en issues, 2026-10-20):** #214 autorización y
  alcance por paciente (crit:alta), #215 no exponer `prompt_sent`/`raw_response`
  (crit:media), #216 `getUsageStats` devuelve ceros al fallar (crit:media), #217
  unificar `per_page`, sobre de respuesta y validación de fechas (crit:baja),
  #218 `AiAnalysisButton` con `<style scoped>` y alias legacy (crit:baja), #219
  doble aviso toast + error en línea (crit:baja).

- **L21 (CI verde y merge, 2026-10-20):** tercera corrida tras integrar `main`:
  run `39483495263` (push) y `39483485559` (pull_request), `Backend tests
  (MySQL)`, `Quality gates` y `Frontend build` en verde en ambos. PR #178
  mergeado a `main`; worktree y ramas eliminados después de copiar este
  documento al `odd/` del checkout principal (excluido de git).
