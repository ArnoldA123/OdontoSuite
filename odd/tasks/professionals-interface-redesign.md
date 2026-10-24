# Professionals Interface Redesign — CERRADA

Rama: `feat/professionals-interface-redesign` (desde `main` @ e2034c1)
Worktree: `~/proyectos/odontosuite-professionals`
Creado: 2026-10-10 · Orquestador: el Gentleman

## Objetivo

Rediseñar la interfaz de profesionales (lista + detalle) con el mismo lenguaje
visual y estándar de calidad dejado en el dashboard (feature
`dashboard-hierarchy-redesign`, PR #130): jerarquía de bloques, hero con ADN del
login, un solo sistema de pills, empty states con motivo dental, datos de fuente
única y cero repeticiones.

## Alcance (4 decisiones aprobadas por el usuario, 2026-10-10)

1. **Lista + detalle**: `ProfessionalsPage.vue` y `ProfessionalDetailPage.vue`.
2. **Datos nuevos visibles**: matrícula profesional (`professional_license`) y
   sucursal (`branch_id`). **Comisión (`commission_rate`) FUERA** por ser dato
   sensible de finanzas.
3. **Backend acotado permitido**: arreglo real de la búsqueda rota y del filtro
   de especialidad que nunca filtra.
4. **Equipo clínico completo**: la lista deja de estar endurecida a
   `role=odontologo` y muestra odontólogo, implantólogo, técnico dental y
   asistente, con selector de rol en alta/edición.

## Hallazgos de la auditoría que el rediseño corrige

- Búsqueda rota: `GET /api/users/search` no está registrado → cae en
  `show('search')` → 404 (`routes/api.php:92,125`; `UserController@search:277`
  sin ruta).
- Filtro de especialidad muerto: `filterProfessionals()` ignora
  `specialtyFilter` (`ProfessionalsPage.vue:521`).
- Mapa de especialidades duplicado en 2 páginas y `<option>`s triplicados
  (filtro + modal nuevo + modal editar).
- Modal "Ver" y `viewProfessional()` son superficie muerta: nada los invoca.
- Dos sistemas de pills: clases sueltas `bg-primary-100`/`bg-success-100` en la
  lista vs `UiBadge` en el detalle.
- Tres estilos de empty state distintos (SVG suelto, caja con gradiente,
  `DashboardSectionEmpty` del dashboard).
- Jerarquía plana: todo `UiCard variant="glass"` con el mismo peso; sin hero,
  sin bloques, sin KPIs.
- Subtítulo de página duplica el caption de `AppLayout` ("Gestiona el equipo
  médico" vs "Gestión de profesionales").
- Clases legacy (`border-theme`, `focus:ring-primary-500`, `focus:border-accent`)
  y `<select>`/`<input>` crudos en vez de `components/ui`.
- Campos del modelo nunca renderizados: `professional_license`, `branch_id`.
- `confirm()` nativo en vez de `useConfirm`.

## No-alcance (fuera de esta feature)

- `commission_rate` no se muestra (decisión del usuario).
- Sin cambios de esquema, migraciones ni seeders.
- No se tocan otros módulos (patients, calendar, dashboard) salvo importar
  componentes compartidos ya existentes.
- Cache/stats del dashboard y follow-ups backend de la feature previa.
- Push, PR y merge: decisiones del usuario al cierre.

## Decisiones de diseño (arquestito)

| # | Decisión | Motivo |
|---|---|---|
| D1 | Vocabulario compartido en `resources/js/modules/professionals/professionals.constants.js` (`SPECIALTY_LABELS`, `SPECIALTY_OPTIONS`, `CLINICAL_ROLES`, `ROLE_LABELS`) | Mataba el mapa duplicado en 2 páginas y los `<option>`s triplicados |
| D2 | Fuente única de datos: la página carga UNA vez la colección del equipo clínico (`GET /api/users?role=...` lista de roles) y de ahí derivan KPIs, lista filtrada y buscador | Mismo contrato que los KPIs del día del dashboard (WU1): un dato, un solo render |
| D3 | KPIs del equipo: Total equipo · Activos · Inactivos · Especialidades representadas. Número + caption de alcance, SIN chips de comparación | Los `delta_label` del backend son inválidos; honestidad del dashboard |
| D4 | Hero band con ADN del login (brand lockup, `DentalMotifLayer variant="hero"`, superficie `var(--color-canvas)` + hairline + `--elevation-2`) y CTA único "Nuevo Profesional" | Patrón reutilizado tal cual del dashboard; un solo CTA primario por destino |
| D5 | Lista en 2 bloques con jerarquía tipográfica: **Equipo** (tabla protagonista) → **Filtros activos / resultados** integrados en el encabezado del bloque; sin "acciones rápidas" que re-listen navegación | Jerarquía del dashboard; la navegación vive en el sidebar |
| D6 | Un solo sistema de pills: `UiBadge` para especialidad, estado (activo/inactivo) y rol | Dos sistemas de pills para el mismo concepto era incoherencia confirmada |
| D7 | Empty states: `DashboardSectionEmpty` (motivo dental escalado, solo en vacío) para "sin equipo", "sin resultados" y "sin historial" | Un patrón, no tres cajas distintas |
| D8 | Filtros: `UiSelect` de especialidad + buscador con filtrado cliente sobre la colección cargada; el filtro SÍ filtra | UX instantánea y coherente con D2; el bug actual queda muerto |
| D9 | Backend acotado: `UserController@index` acepta `role` como lista separada por comas; se elimina `search()` sin ruta y la llamada rota `/api/users/search` (si ningún test lo referencia; si lo referencia, se registra la ruta antes del apiResource) | Arregla la causa real con el cambio mínimo |
| D10 | Detalle: misma escala de hero/tarjeta, matrícula + sucursal + rol + especialidad visibles, tabs en bloques, historial con `DashboardSectionEmpty` | Paridad visual entre ambas páginas del módulo |
| D11 | Ambas páginas a Composition API + `<script setup>` | Convención del proyecto (§7 AGENTS.md); el rediseño reescribe el template igualmente |
| D12 | Acciones de fila con `useConfirm` para eliminar; modal "Ver" eliminado (superficie muerta) | Confirm nativo y modal inalcanzable no son UI profesional |

## Restricciones de estilo (heredadas del dashboard)

- UI siempre desde `components/ui/`; Tailwind + design tokens; sin CSS scoped.
- Sin clases legacy (`border-theme`, `focus:ring-primary-500`, `focus:border-accent`).
- Sin em-dash U+2014 en fuentes nuevas; usar "N/D" o texto.
- `pnpm` siempre; commits convencionales en inglés, sin emojis; sin comentarios
  pedagógicos.
- Copy de UI en español (convención de la app); código e identificadores en inglés.
- Tests anclados al contrato real (variant binding, `data-*`), nunca a literales
  de clases muertas.

## Tests y guards

- NUEVO `tests/Unit/DesignSystem/ProfessionalsAppShellTest.php` (RED primero
  en T1): prohíbe clases legacy, pills hechas a mano, `<select>` crudo,
  `confirm()` nativo, U+2014, y exige el import de `professionals.constants`.
  Los `data-*` hooks del hero/KPIs/bloques se anclan en T3/T4 sobre el mismo
  archivo de test.
- `tests/js/smoke/professionals.test.js` se mantiene verde ("Profesionales") y
  se amplía en T6 (filtro que filtra, búsqueda sin 404, empty states).
- PHP: tests de `UserController@index` multi-rol + eliminación/registro de
  `search()`.
- Guards existentes que deben seguir verdes: `AppLayoutCanvasRoutesTest`
  (`/professionals` ya es canvas: NO tocar `canvasRoutes`),
  `EnvironmentsCanvasRoutesPrefixTest`, `dashboard-*.test.js`,
  `DashboardComparisonTest` (`total_professionals` sin comparación).

## Disposición RDD

Misma cadencia delegada que `dashboard-hierarchy-redesign` ("decide tú, sin
bajar calidad"): toda work unit con TDD observado y plan ASSESS cumplido sobre
el rango commiteado (baseRef = frontera previa + committedOnly, hash real);
revisión nativa del slice solo cuando ASSESS marque `reviewDue`; agregado final
de la feature revisado una vez al cierre si cabe en presupuesto; agregados
intermedios sin revisar (cubiertos por sus slices). El consent RDD por candidato
es user-owned y no se delega.

## Tasks

| # | Task | Estado | Commit |
|---|---|---|---|
| T1 | Constantes compartidas + guards de diseño (RED → GREEN) | ✅ hecha | d260e00 |
| T2 | Backend acotado: `index` multi-rol + muerte de `search()` roto + tests PHP | ✅ hecha | df184cf |
| T3 | ProfessionalsPage: hero band + KPIs del equipo (fuente única) | ✅ hecha | (ver abajo) |
| T4 | ProfessionalsPage: lista jerárquica, filtros que filtan, pills únicas, empty states, modales limpios | ✅ hecha | e41c420 |
| T5 | ProfessionalDetailPage: paridad visual + matrícula/sucursal/rol | ✅ hecha | ea79a6d |
| T6 | Cierre: `useConfirm`, tests JS ampliados, verificación completa (JS/PHP/lint/build/doc-drift/visual) | ✅ hecha | 75403c6, 8c7631d, 72f3787 |

## Evidencia de commits

- **T1 → `d260e00`** `refactor(professionals): shared vocabulary constants and UI primitive guards`
  (4 files, +335/-201). Verificación: ProfessionalsAppShellTest 7 tests / 73
  assertions GREEN vía `php artisan test`; vitest 195/195; ESLint 0 errores.
  ASSESS: riesgo medium, plan = writer self-verification (cumplido por el worker).
- **T2 → `df184cf`** `fix(professionals): multi-role user index and dead search action removal`
  (2 files, +169/-40). Verificación: UserIndexFilterTest RED 3 failed → GREEN
  7 tests / 35 assertions; suite `--filter=User` 46 tests / 123 assertions;
  `UserController@search` eliminado (sin referencias); rutas sin cambio
  195 API / 208 total → AGENTS.md intacto; doc-drift 12/12. ASSESS: medium,
  `reviewDue: false` (under_budget), plan writer self-verification cumplido.
  Nota de entorno: `phpunit.mysql.xml` finge credenciales placeholder; la
  invocación válida exporta `DB_USERNAME`/`DB_PASSWORD` desde `.env` +
  `DB_SOCKET=/opt/lampp/var/mysql/mysql.sock` (BD `odontosuite_test`).
- **T3** → `feat(professionals): hero band and single-source team KPI strip`
  (3 files). `<script setup>` + una sola carga `GET /api/users?role=<csv de
  CLINICAL_ROLES>` → KPIs + filas + búsqueda cliente + filtro de especialidad
  funcional derivados de la misma colección; `/api/users/search` eliminado;
  hero con ADN del login (lockup + DentalMotifLayer hero + `var(--color-canvas)`,
  sin mint/gradientes) + CTA único; 4 KPIs con `data-stat-card`. Verificación:
  RED (PHP 3 failed / JS 3 failed) → GREEN PHP 11 tests / 101 assertions,
  JS 198/198, lint 0 errores. Workers degradados: 1 timeout (4 min sin primer
  evento) → reintento exitoso. ASSESS: medium, reviewDue (slice budget 756
  líneas), plan writer self-verification.
- **T4 → `e41c420`** `feat(professionals): hierarchical team list with shared pill and empty-state system`
  (3 files, +721/-279). Bloque de lista con jerarquía (`data-team-list/-filters/-row`),
  pills solo `UiBadge` (RELIABILITY-004: fills hechos a mano vetados incluida la
  forma `bg-[color:var(--color-*-100)]`), `DashboardSectionEmpty` en los 2
  vacíos, diccionario de sucursales vía `GET /api/branches/active` (lookup, no
  segunda fuente), selector de rol CLINICAL_ROLES/ROLE_LABELS en alta/edición,
  validación en submit (RELIABILITY-005), modal "Ver" muerto eliminado y toast
  duplicado de vacío eliminado. Verificación: guards 17 tests / 146 assertions
  (RED 7 failed sobre base d61334e), smoke 12 tests → suite JS 206/206, lint
  0 errores. Workers degradados: 1 error → reintento exitoso (recuperó la
  implementación pre-existente con swap verificado por hash, RED genuino sobre
  base). ASSESS: medium, reviewDue (1000 líneas), plan writer self-verification.
  Copy fijado: vacío "Sin profesionales registrados" / "Aún no hay nadie en el
  equipo clínico."; sin resultados "Sin resultados" / "Ningún profesional
  coincide con los filtros activos." + "Limpiar filtros"; fallbacks "N/D" /
  "Sin sucursal".
- **T5 → `ea79a6d`** `feat(professionals): professional detail page visual parity`
  (3 files, +646/-361). Hero con los mismos tokens, Breadcrumbs como navegación
  (botón "Volver" redundante eliminado), bloque `data-professional-facts` con
  matrícula/sucursal/rol (ROLE_LABELS/SPECIALTY_LABELS + lookup
  `/api/branches/active`), pills solo `UiBadge`, `DashboardSectionEmpty` en el
  historial, `<script setup>`, `commission_rate` jamás renderizado (guard +
  behavior test). Verificación: RED 5 PHP + 4 JS → GREEN PHP 25 tests / 187
  assertions, JS 212/212 (6 nuevos), lint 0 errores. ASSESS resolvió
  *unassessable* (archivo sin trackear antes del `git add`) → plan riesgo alto:
  **verificador independiente ejecutado** (`gentle-ai-verify`): **6/6 PASS,
  cero discrepancias**. Copy: fallbacks "N/D"/"Sin sucursal", vacío de
  historial "No hay historial de auditoría" / "Este profesional no tiene
  registros de auditoría."

## Revisión T1 y estado RDD (2026-10-10)

- La revisión nativa del slice (lineage `review-1805a468b9b8006b`) marcó un
  hallazgo severo R3 + refutador → `correction_required`, pero la ruta de
  corrección quedó rota: `stop` terminal `corrupted_or_unverifiable_authority`
  y reintento fallido `candidate_context_unavailable` ("negotiated START
  repository context does not match the active reviewing authority",
  `retry_safe: false`). Detalle del hallazgo ilegible.
- **Decisión del usuario (2026-10-10)**: acción `D` ejecutada
  (`gentle-ai review mode disable --scope clone`, clone-local: off). La
  verificación de calidad queda en: tests funcionales + revisores independientes
  (subagentes R1-R4) + verificación visual medible.
- **Revisor independiente R3 sobre `d260e00`**: refuta el hallazgo severo (el
  único `confirm` espera la promesa: `ProfessionalsPage.vue:481` `await
  confirm(...)`, `:487` `if (ok)`, patrón idéntico a `QuotationCard.vue:206`).
  5 hallazgos menores ruteados:
 
| Hallazgo | Resumen | Ruteo |
|---|---|---|
| RELIABILITY-001 | El guard de `confirm` no exige `await` | T6 (endurecer guard) |
| RELIABILITY-002 | Guards solo inspeccionan fuente; sin tests de comportamiento de los swaps | T6 (behavior tests) |
| RELIABILITY-003 | Aserciones de strings exactos frágiles (imports, quotes) | T6 (robustecer) |
| RELIABILITY-004 | Pills hechas a mano vía `bg-[color:var(--color-primary-100)]` (`ProfessionalsPage.vue:141`) esquivan el guard | T4 (UiBadge real) + T6 (guard) |
| RELIABILITY-005 | `required` reenviado a `UiSelect`/`UiInput` cae en el wrapper div: validación nativa perdida | T4 (modales) |

## Cierre (2026-10-11) — 8 commits en `feat/professionals-interface-redesign`

`d260e00` · `df184cf` · `d61334e` · `e41c420` · `ea79a6d` · `75403c6` ·
`8c7631d` · `72f3787` (base `e2034c1` de main).

### Verificación final (batería independiente `gentle-ai-verify`)

| Check | Resultado |
|---|---|
| JS (vitest) | 218/218 en 39 archivos |
| PHP MySQL (`phpunit.mysql.xml` + socket XAMPP) | **1177 tests / 4933 assertions** (baseline previa 1145) |
| ESLint | 0 errores (1444 warnings = deuda Prettier #15) |
| `pnpm build` | ✓ |
| `doc-drift.mjs` | 12/12 (rutas 195/208 sin cambio) |
| Promesas estructurales | sin `/api/users/search`, sin `commission_rate`, sin gradientes, sin U+2014, constants importadas en ambas páginas |

### Verificación visual medida (Playwright, 3 viewports)

- Render y comportamiento PASS en 1440/1024/390: hero + CTA único, 4 KPIs
  (9/9/0/5), 9 filas, pills, matrícula/sucursal con fallbacks "N/D"/"Sin
  sucursal", búsqueda y filtro vivos, "Limpiar filtros" condicional, ficha con
  `data-professional-facts` (9 filas), breadcrumb, sin comisión, 0 errores de
  app (solo Reverb caído = ambiental).
- 2 defectos medidos → corregidos → re-medidos:
  1. Tabla desbordaba 141px a 1440 (Sucursal/Acciones recortadas) →
     `px-6→px-3`, columna profesional acotada a 220px con `truncate` →
     **PASS medido: scrollWidth 1038 = clientWidth 1038**, ACCIONES íntegra.
  2. Captions de KPI con elipsis a 1024 → `line-clamp-2` → **PASS medido:
     4/4 captions completos en 2 líneas**.

### Notas de cierre

- RDD nativo NO ejecutado por decisión explícita del usuario (acción `D`,
  switch deshabilitado para este clon tras la corrupción de autoridad nativa
  documentada arriba). Sustituto de calidad ejecutado: verificador independiente
  en T5 (6/6), batería completa, revisión independiente R3 en T1 (refutó el
  hallazgo severo) y verificación visual medida.
- 5 hallazgos RELIABILITY cerrados: R-004/R-005 en T4, R-001/002/003 en T6
  (guards endurecidos con no-vacuidad probada por mutación scratch).
- Pendiente para el usuario: push + PR + merge (decisiones suyas), y los
  follow-ups backend previos del dashboard siguen abiertos (fuera de alcance).

## Entrega (2026-10-12) — MERGEADA EN MAIN

- Issue **#138** (cerrado por `Closes`) · PR **#139** · merge commit **`9c5b478`**.
- Integración con `main` avanzado (patients #132 + calendar #135-#137, +10.6k
  líneas): fusión sin conflictos (`27652b5`) + adaptación `31cb560` —
  `DentalMotifLayer` y `UiSectionEmpty` se consumen desde `components/ui/`
  (promovidos por esas features); cero imports cruzados al módulo dashboard.
  Causa del fallo de CI original: el commit de merge heredaba el archivo
  movido y nuestros imports apuntaban al path viejo (Quality gates + Frontend
  build, el run del push en verde delató la diferencia de árboles).
- CI del PR: 6/6 checks verdes (Quality gates · Backend tests MySQL ·
  Frontend build, en los dos runs). Verificación local post-fusión: PHP guards
  25/190, JS 379/379, MySQL 1245/5406, build ✓, doc-drift 12/12, lint 0 errores.
- Limpieza: rama `feat/professionals-interface-redesign` borrada local y
  remota; worktree en detached (conserva `odd/tasks` + entorno).
