# Procedure Catalog UI Redesign — EN CURSO

Rama: `feat/procedure-catalog-ui-redesign` (desde `main` @ 862f416)
Worktree: `.worktrees/procedure-catalog-ui-redesign`
Creado: 2026-10-19 · Orquestador: el Gentleman

## Specs

- **S1 — Alcance (respuesta del usuario: "Flujo completo de procedimientos").**
  Entran las cuatro superficies del módulo, no solo el catálogo admin:
  `/procedure-catalog` (lista, detalle, modales de alta/edición, import CSV),
  `/procedure-stats`, `/my-procedures` y `/reception-procedures`. Las cuatro
  deben compartir el lenguaje visual del dashboard.

- **S2 — Detalle (respuesta del usuario: "Lo visible + versionado").**
  El detalle conserva información general, requisitos y materiales,
  contraindicaciones, cuidados post-procedimiento e historial de auditoría, y
  añade el historial de versiones del procedimiento (`procedure_catalog_versions`)
  con fecha, autor y campos modificados. Las versiones ya se persisten en
  backend (`TrackProcedureVersion`); solo falta exponerlas.

- **S3 — Lista (respuesta del usuario: "la mejor practica si es esas 2 opciones
  o una intermedia, pero la mejor si malograr lo demas de este proyecto").**
  Decisión técnica del orquestador: la lista mantiene **paginación server-side**
  (contrato actual de `GET /api/procedure-catalog`, sin cargar el catálogo
  completo ni duplicar filtros en cliente) y los KPIs se calculan con un
  endpoint de resumen aditivo `GET /api/procedure-catalog/summary`, de modo que
  los números no dependan de la página visible y el catálogo pueda crecer sin
  romper la pantalla. No se altera el envelope `{data, meta}` existente.

- **S4 — Acciones de escritura (respuesta del usuario: "Admin + duplicar y
  reactivar").**
  Solo `administrador` ve y ejecuta crear, editar, desactivar, importar CSV,
  duplicar y reactivar. `Duplicar` precarga los datos del procedimiento y exige
  código y nombre nuevos; reutiliza `POST /api/procedure-catalog` (sin endpoint
  nuevo). `Reactivar` usa `PUT /api/procedure-catalog/{id}` con
  `{ is_active: true }` (sin endpoint nuevo). El resto de roles solo lee.

- **S5 — Lenguaje visual (petición original: "conforme dejamos el dashboar").**
  Cada superficie usa el vocabulario ya establecido por
  `dashboard-hierarchy-redesign`, `appointment-types-interface-redesign` y
  `environments-ui-redesign`: hero band con hairline + `--radius-panel` +
  `--elevation-2`, eyebrow + display headline + caption, KPI cards, filtros en
  `UiCard variant="glass"`, estados completos (carga con `LoadingSpinner` o
  `UiSkeleton`, vacío con `UiEmptyState`, error con retry), `@heroicons/vue`,
  `UiStatusBadge`, tokens del design system y cero SVGs inline. Copy de UI en
  español; identificadores y código en inglés.

- **S6 — Defectos funcionales del módulo que la entrega corrige.**
  (a) `StoreProcedureCatalogRequest` y `UpdateProcedureCatalogRequest` no
  aceptan `materials_needed`, `requires_radiographs`, `post_procedure_care`,
  `requirements` ni `steps`, aunque el formulario y el importador ya los editan:
  `validated()` los descarta en silencio. Se amplían las reglas sin quitar las
  existentes. (b) El explorador de `Mis Procedimientos` promete "cualquier
  procedimiento del catálogo" pero solo recibe `for-me` (especialidades del
  usuario + favoritos); el buscador pasa a consultar
  `GET /api/procedure-catalog/search` para encontrar cualquiera. (c) El detalle
  y la lista deben mostrar los campos que hoy se editan y no se ven.

- **S7 — Contratos existentes.**
  No se rompen las rutas ni los envelopes actuales, los roles de `routes/api.php`,
  los smoke tests de JS, `CatalogFilterTest`, `ProcedureCatalogFlowTest`,
  `CatalogStatsReachabilityTest`, `ProcedureCatalogTranslationTest` ni el
  doc-drift de `AGENTS.md`. Los tests nuevos se anclan al contrato observable
  (bindings, `data-*`, variantes), no a literales de clases.

- **S8 — Entrega.**
  Trabajo en worktree nuevo, commits convencionales por unidad revisable, issue
  con `Closes`, PR a `main`, CI verde y merge. El push/PR/merge se ejecutan como
  parte de esta entrega autorizada.

## Tasks

| # | Task | S# | Ruta | Commit |
|---|---|---|---|---|
| T1 | Backend: `GET /procedure-catalog/summary`, `GET /procedure-catalog/{id}/versions`, reglas faltantes en FormRequests, tests MySQL | S2, S3, S6 | inline | — |
| T2 | Catálogo admin: lista con hero, KPIs, filtros server-side, tabla, estados y deep-link `?edit=` | S1, S3, S5 | inline | — |
| T3 | Catálogo admin: modales crear/editar/duplicar, import CSV, desactivar/reactivar | S4, S5, S6 | inline | — |
| T4 | Detalle: hero, tabs Datos / Historial / Versiones | S2, S5 | inline | — |
| T5 | Estadísticas del catálogo rediseñadas | S1, S5 | writer | — |
| T6 | Mis procedimientos rediseñados (favoritos + explorador global) | S1, S5, S6 | writer | — |
| T7 | Recepción de procedimientos rediseñada | S1, S5 | writer | — |
| T8 | Verificación completa (Vitest, PHP MySQL, lint, build), docs, issue, PR, merge | S7, S8 | inline | — |

## Log

- **L1** — Pedido original del usuario (verbatim):
  "continuando con este proyecto conforme dejamos el dashboar quiero hacer ahora
  la interfaz de catalogo de procedimientos, para hacer ello en un worktree nuevo"

- **L2** — Respuesta de alcance (verbatim): "Flujo completo de procedimientos".

- **L3** — Respuesta de detalle (verbatim): "Lo visible + versionado".

- **L4** — Respuesta sobre la lista (verbatim): "la mejor practica si es esas 2
  opciones o una intermedia, pero la mejor si malograr lo demas de este proyecto".
  Interpretación registrada en S3: paginación server-side + endpoint `summary`;
  es la opción intermedia que no toca el contrato de la lista y da KPIs exactos.

- **L5** — Respuesta de acciones (verbatim): "Admin + duplicar y reactivar".

- **L6** — Decisión de arquitectura (orquestador): las versiones se exponen en
  un endpoint de lectura dedicado en vez de incrustarse en `show`; así el
  detalle pinta el historial bajo demanda y `show` conserva su contrato.

## Slices de entrega

Frontera de la feature: `862f416` → `3c5e01b` (4066 líneas autoradas, 4 superficies).
Estrategia: PR único con seis commits-unidad revisables (cada uno pasa lint/build/tests
del módulo por separado); no se encadenan PRs porque el usuario pidió el flujo completo
como una sola entrega y las unidades comparten el mismo contrato visual.

| Commit | Unidad |
|---|---|
| `29dab9c` | Backend: summary, versiones, FormRequests, filtro `auditable_id`, test MySQL, AGENTS.md |
| `94507c1` | Catálogo admin: lista, KPIs, filtros, modales, import |
| `8f317d2` | Detalle: hero + tabs Datos/Historial/Versiones |
| `f2a6e97` | Estadísticas rediseñadas |
| `88d9ef1` | Mis procedimientos: favoritos + explorador global |
| `6ed3265` | Recepción rediseñada |
| `3c5e01b` | Fix CI: contratos de design-system (MyProcedures/Stats/Reception) + summary robusto |

## Verificación

- JS: `vitest run --no-file-parallelism` → **54/54 archivos, 425/425 tests** (serial;
  la corrida paralela falló por timeouts del worker de Vite, no por asserts).
- PHP: `ProcedureCatalogSummaryVersionsTest` → **6/6, 41 assertions** contra MySQL
  (`odontosuite_ci_test`, `DatabaseTransactions`); `AgentsDocsSyncTest` **5/5**.
- `node scripts/audit/doc-drift.mjs` → **12/12** invariantes.
- `pnpm build` → ✓; ESLint de los archivos tocados → 0 errores/0 warnings.
- Los tests que usan `RefreshDatabase` no se pudieron ejecutar en este entorno local
  porque `migrate:fresh` choca con tablas residuales de la DB de test; no es una
  regresión de este cambio. El job `backend-tests` de CI corre sobre una DB limpia.

## Entrega

- Issue: https://github.com/ArnoldA123/OdontoSuite/issues/146
- PR: https://github.com/ArnoldA123/OdontoSuite/pull/148
- Rama remota: `feat/procedure-catalog-ui-redesign`
- CI: en curso al momento de escribir esta nota; merge pendiente de checks verdes.

### Lección CI (PR #148, run 39331085395)

Los smoke tests JS no cubren los guards PHP `tests/Unit/DesignSystem/*AppShellTest`, que leen el
fuente y fijan contratos de tokens por página. El rediseño cambió la estructura (hero band en vez de
PageHeader, UiButton en vez de `<button>` crudo, KPI sin slots fijos) y 17 asserts fallaron. Se
resolvió anclando los guards al contrato nuevo (hero + KPI anatomy + radios contextuales) y
aplicando los tokens que seguían vigentes (tabular-nums pareado, UiBadge, focus ring, ramps).
`ProcedureCatalogSummaryVersionsTest` también falló en CI porque el `setUp` borraba el catálogo y la
suite completa deja hijos con FK; ahora mide contra los agregados de la DB y usa códigos únicos.
