# Task: U4 — Pacientes: lista y ficha (auditoría UI, plan #13)

Status: IN PROGRESS (cierre pendiente). Ramas y commits: `feat/audit-ui-u4`
desde `4f072f0`; instrumento en `tests/e2e/audit/u4/`, evidencia en
`.atl/qa-evidence/audit/ui/U4/` (gitignored), esta nota en el commit de
documentación y hallazgos publicados en #399–#405. No se toca código de
producto.

## Specs

- **S1** — Objetivo (issue #367, verbatim): «Verificar que la ficha del paciente
  es el lugar donde se consulta y se actúa sin buscar en otro módulo, y que su
  lista se comporta como las demás listas del sistema. Medido en `8b72d37`:
  `PatientsPage` usa `<table>` propio y 1 `LoadingSpinner` sin `Skeleton`;
  `PatientDetailPage` (1 602 líneas) no tiene ninguno de los dos; la ficha aloja
  4 tabs, cargo manual, estado de cuenta y alta de laboratorio.»
- **S2** — Método, capa 1 (issue #367, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=patients --out=.atl/qa-evidence/audit/ui/U4/manifest.json
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U4
  ```
- **S3** — Superficies, roles y acciones (issue #367, verbatim): «Superficies:
  `/patients` (búsqueda, filtros, orden, paginación, alta, edición, borrado con
  confirmación, exportar ficha) y `/patients/:id` (tabs Datos, Atenciones,
  Cuenta y Laboratorio; cargo manual; estado de cuenta del período; presupuestos
  del paciente). Roles: administrador, recepcionista, odontólogo y finanzas.
  Acciones: alta con envío vacío, documento duplicado y fecha inválida; edición;
  borrado confirmado sobre una fila propia; exportación; recorrido de cada tab;
  alta de cargo manual; lectura del estado de cuenta; vuelta a la lista
  conservando filtros.»
- **S4** — Entregable (issue #367, verbatim): «`render.json` (4 roles),
  `primitives.json`, `walkthrough.md`, `api-matrix.json`, `ux-rubric.md` y
  `odd/tasks/audit-ui-u4.md`.»
- **S5** — Criterio de salida (issue #367, verbatim): «El CRUD completo no deja
  residuos en la base de auditoría; las 4 tabs abren con sus tres estados; el
  estado de cuenta cuadra con el seed; los filtros sobreviven el viaje lista →
  ficha → lista; cero `pageerror`; rúbrica puntuada.»
- **S6** — Plan #13 §4 capa 2 (verbatim): «Por cada ruta y por cada rol que la
  ve (máximo 3 roles: el dueño del escritorio, administración y un rol excluido
  para la puerta negativa)»; «Cero `console.error`, cero `pageerror`, cero
  request ≥ 400 salvo allowlist declarada»; «Captura `desktop 1440×900` y
  `mobile 390×844`, **siempre y explícita**»; «Sin scroll horizontal de
  documento en 390 px.»
- **S7** — Plan #13 §4 capa 3 (verbatim): «Por cada diálogo abierto desde una
  superficie»: centrado (`|box.x + box.width/2 − vw/2| < 8`), docking, un solo
  `h1`, Escape cierra, foco atrapado, scroll bloqueado y etiqueta accesible.
  «Un diálogo que no cumple **una** fila es hallazgo, no ruido: es la clase
  "primitiva mal usada" y se agrupa con sus hermanos.»
- **S8** — Plan #13 §4 capa 4 (verbatim): los veredictos `OK`, `DEFECTO`,
  `NO-OP`, `CONFUSO`, `NO PROBADO`; «Toda acción con `DEFECTO`, `NO-OP` o
  `CONFUSO` produce fila en `findings.md` con captura y, si hay request, su
  status.»
- **S9** — Plan #13 §4 capa 5 (verbatim): «Para los endpoints que la sesión
  consume: matriz `rol × endpoint` con el status esperado derivado de
  `App\Authorization\Permissions` y el observado con un token real.»
- **S10** — Plan #13 §4 capa 6 y §5 (verbatim): «Doce criterios, 0/1/2 cada uno,
  por superficie primaria y una vez por sesión para su conjunto. La puntuación va
  a `ux-rubric.md` con la cita de lo observado.»
- **S11** — Plan #13 §6.1, §6.3 y §9 (verbatim): «la sesión **solo** escribe en
  `odontosuite_audit`; los borrados se hacen sobre filas que la propia sesión
  creó; nunca `migrate:fresh` sobre la base de desarrollo»; «`.atl/` está en
  `.gitignore` (verificado), así que las capturas y JSON **no se commitean**»;
  «La sesión que audita no escribe código de producto».
- **S12** — Plan #13 §8.1 (verbatim): «Un issue por clase raíz, nunca por
  síntoma ni por módulo»; «Tope por sesión: 3–7 issues»; «La sesión no cierra
  sus propios issues».

## Estrategia de entrega

Un solo instrumento de medición —`tests/e2e/audit/u4/` (`helpers`, `fixtures`,
`list`, `detail`, `primitives`, `api-matrix`)— registrado en `surface.spec.js`,
igual que U2/U3. No se toca código de producto (S11).

El commit `43b71e9` (3 822 líneas autoradas, 7 archivos) es el **límite declarado
de esta unidad**: los seis módulos son una sola medición (comparten helpers y
fixtures, y el registro en `surface.spec.js` los importa juntos; partirlos
dejaría el árbol roto o dos mitades de la misma corrida). El presupuesto de ~400
líneas aplica a correcciones, no a este instrumento nuevo; mismo criterio que
U0/U1/U2/U3.

Datos: los fixtures crean por API cinco pacientes con marcador `AUDIT-U4`, un
presupuesto de borrador y, por la interfaz, un alta y un cargo; se retiran al
final salvo el cargo (libro inmutable, ADR-0015) y la fila del presupuesto (soft
delete), declarados como residuo.

## Tasks

- [x] **T1** · S2, S6, S11 · `inline` · Precondiciones: `odontosuite_audit`
  recreada y sembrada (17 seeders; 105 pacientes, todos activos, 3 sedes, 0
  casos de laboratorio), app `:8000` con `DB_DATABASE=odontosuite_audit`, Vite
  `:5173`, Reverb `127.0.0.1:8081` y `queue:work` sobre la misma base; revisión
  anotada `4f072f0`. Capa 1: 2 superficies, 39 acciones (0 sin clasificar),
  4 diálogos, 20 endpoints, 0 permisos sin resolver.
- [x] **T2** · S1–S9 · `delegated` · Instrumento `@U4`:
  `tests/e2e/audit/u4/{helpers,fixtures,list,detail,primitives,api-matrix}.js`
  (3 813 líneas) registrados en `surface.spec.js` (sólo el bloque de imports,
  +9 líneas). Commit `43b71e9`.
- [x] **T3** · S2, S3, S5, S11 · `inline` · Corrida definitiva y correcciones:
  **23 tests, 3.8 min, exit 0**; `ui-pass --module=patients --no-run` → gate OK,
  0 `pageerror`, 4 diálogos con 0 filas fallidas, 36 solicitudes esperadas, 0
  acciones sin clasificar. Verificación independiente de los defectos contra la
  API con tokens reales (403 por rol, payload agrupado, mensajes en inglés,
  política de exportación) antes de publicar.
- [x] **T4** · S4, S10, S11, S12 · `inline` · `render.json`, `primitives.json`,
  `walkthrough.md`, `ux-rubric.md`, `api-matrix.json`, galería `index.html` y
  esta nota; hallazgos agrupados por clase raíz y publicados #399–#405, más un
  comentario medido en #387.
- [ ] **T5** · S5, S11, S12 · `inline` · Cierre: `pnpm format:check`,
  `pnpm lint:check`, `pnpm test:js`, `pnpm exec playwright test --list`,
  commits, revisión nativa y PR.

## Log

- **L1** — Pedido del usuario, verbatim: «affrontare il problema (issue) 367-u4».
- **L2** — Cuerpo de #367 (lo citado en S1–S5 es verbatim de ahí) y plan #13 §4
  (capas 1–6), §5 (rúbrica), §6 (anatomía y evidencia), §7 (U4), §8 (protocolo
  de issues), §9 (guardarraíles) y §12 (P1/P2).
- **L3** — Estado al abrir la sesión (`4f072f0`): `PatientsPage.vue` (969 líneas)
  usa `<table>` propio + `PatientListRow`, `LoadingSpinner`, `UiSectionEmpty`
  para error y vacío, `Pagination`, cuatro `UiSelect` de filtro y `useConfirm`
  para el borrado; no tiene `Skeleton`. `PatientDetailPage.vue` (1 602 líneas)
  tiene **siete** tabs por omisión (Datos, Planes, Presupuestos, Historia
  Clínica, Especialidades, Historial) más Laboratorio y Cuenta condicionadas por
  permiso — no 4; la sesión mide lo que existe y declara la diferencia.
- **L4** — Entorno: base `odontosuite_audit` recreada y sembrada; servicios
  arriba; login por API verificado. Contexto del arnés: `harness.js` (capas
  2–3), `surface.spec.js` (registro por sesión) y los instrumentos `u2/` y `u3/`
  como patrón.
- **L5** — El instrumento se delegó a un escritor acotado (mecanismo de
  *context backstop* de la sesión): 6 módulos, 3 813 líneas, registro sólo en el
  bloque de imports. Correcciones que la propia corrida exigió, todas del
  instrumento y no del producto: el lector de toasts mezclaba los KPI (ahora
  sólo `aria-live="assertive"`); el cierre del modal de cargo se leía en el
  primer fotograma de la transición; el `teardown` contaba como residuo la fila
  que la interfaz ya había borrado (ahora `skipped-already-gone`); la matriz
  habría etiquetado como desajuste la concesión inerte de exportación, y ahora
  deriva también `PatientPolicy::export()`; la sonda de tabs paraba los
  retrasos de ruta antes de recorrer los perezosos; el alta feliz reutilizaba el
  nombre del intento duplicado.
- **L6** — Corrida definitiva sobre base recién recreada: **23/23 verdes, 3.8
  min**. `ui-pass`: 1 de 2 superficies clasificada `renders`; `/patients/:id`
  queda `not-renders` por «solicitud 403» — es el defecto F1, no un fallo de
  carga (el contenido renderiza, 527–561 caracteres)—; gate OK, 0 `pageerror`,
  36 solicitudes esperadas, 4 diálogos y 0 filas de contrato fallidas.
- **L7** — Verificación propia antes de publicar, contra la API con tokens
  reales: `GET /api/specialty-records/patient/{id}/all` responde el objeto
  agrupado (F2); `treatment-plans?patient_id=` y `medical-records?patient_id=` 403
  para recepción y finanzas y 200 para odontología (F1); `audit-logs/patient/{id}`
  403 para recepción, odontología y finanzas (F1); export 200 para
  recepción/odontología y 403 para finanzas (F7); los 422 de documento
  duplicado y fecha futura llegan con la frase de campo en inglés (F6). Además
  se leyó la instancia de `UiSelect`: `modelValue: true` con opciones
  `['', 'active', 'inactive']`, que fija la causa de F4 en el casteo booleano
  de la primitiva.
- **L8** — Decisiones de triage: F1 absorbe el «403 pintado como vacío» del
  Historial y remite a #396 (misma clase en agenda); F5 se agrupa con F3 porque
  los dos viven en la barra de filtros de la misma página; F9 (los tabs
  perezosos nacen en vacío) no abre issue propio: se midió como comentario en
  #387, que ya es dueño de la clase; F8 se publica aparte porque la causa es el
  *ownership* de títulos del shell, no la ficha.
- **L9** — El cargo de la sesión queda en la base de auditoría por diseño
  (ADR-0015: el libro no tiene puerta de borrado) y el presupuesto en soft
  delete; toda fila visible se retiró. Se declaran como residuo en
  `fixtures.json`, no se ocultan.

## Línea base medida

- Revisión del árbol medido: `4f072f0` + instrumento; árbol limpio salvo el
  instrumento y esta nota. Corrida: `2026-10-25T17:50Z`.
- **Capa 1** · `ui-manifest --module=patients`: 2 superficies, **39 acciones (0
  sin clasificar)**, **4 diálogos**, **20 endpoints**, **0 permisos sin
  resolver**.
- **Capas 2–4** · `pnpm exec playwright test … --grep @U4` → **23 pasan**, exit
  0, **3.8 min**.
- **Envoltorio** · `ui-pass --module=patients --no-run` → **gate OK**; 1 de 2
  superficies `renders`, 1 `not-renders` («solicitud 403»), 0 excluidas; **0
  `pageerror`**; 36 solicitudes esperadas y 4 inesperadas (las de F1); 4
  diálogos con **28 comprobaciones y 0 fallos**.
- **Render por rol**: lista 2 269 / 2 134 / 2 119 / 2 014 caracteres, 15 filas,
  1 `h1` («Pacientes»), 0 overflow en 390 px; ficha 561 / 546 / 538 / 527
  caracteres, **8/7/7/6 tabs**, 0 overflow. Primer pintado: `LoadingSpinner`
  «Cargando pacientes…», **0 skeletons**.
- **CRUD**: alta 201 (105 → 106), edición `PUT` 200, borrado con `alertdialog`
  (cancelar 0 `DELETE`, confirmar 200), búsqueda 5 filas propias, paginación
  «16 a 30 de 105», `per_page=30` → 30 filas.
- **Cargo y cuenta**: vacío → errores en línea; 201 → cierre 0 → **240.50**
  (delta exacto); fórmula de la cuenta = recorrido de asientos = cierre; DOM =
  payload; período 2000 → vacío; 500 forzado → error y el reintento recupera.
- **Capa 5** · `api-matrix.json`: **119 filas (17 endpoints × 7 roles), 119
  `ok`, 0 desajustes, 0 saltadas**; expectativa desde `GRANTS` + `PatientPolicy`
  y observación con token real.
- **Rúbrica**: `/patients` **14/24**, `/patients/:id` **17/24**; ningún criterio
  en 0, cuatro criterios en 1 repetidos en ambas superficies (títulos, acciones,
  estados, feedback).

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- |
| F1 | ficha | Abrir como recepción/finanzas/odontología | Tabs con puerta de permiso; el 403 no se pinta como contenido | Planes e Historia Clínica 403 con panel de error; Historial 403 → «No hay historial» | 2/4 | MEDIDO | `crit:alta` | `PatientDetailPage.vue:901-949,1337`; `useAuditLogs.js:19-24` | #399 |
| F2 | ficha → Especialidades | Ver la tab de un paciente sin registros | Vacío con CTA | Cinco tarjetas fantasma y rutas `/undefined` | 4 | MEDIDO | `crit:media` | `PatientDetailPage.vue:1050` vs payload agrupado, `:517` | #400 |
| F3 | `/patients` | Filtrar, abrir ficha, volver | Filtros conservados | Petición sin `search` ni `active`; 15 de 105 | 4 | MEDIDO | `crit:media` | Estado de componente + `:key="$route.fullPath"` (§8) | #401 |
| F4 | `/patients` filtros | Leer el select de estado | «Todos» visible y marcada | «Seleccionar…»; `aria-selected="false"` ×3 | 4/5 | MEDIDO | `crit:media` | `Select.vue:226-229,281-287` (casteo `''`→`true`) | #402 |
| F5 | `/patients` filtros | Limpiar filtros | Control con nombre accesible | Icono sin `aria-label` ni `title` (§7) | 4 | MEDIDO | `crit:media` | `PatientsPage.vue:283-285` | #401 |
| F6 | `/patients` alta | 422 duplicado/futuro y alta válida | Error en su campo, en español; éxito sin claves | Sólo toast, frase de campo en inglés; `Email: null` | 4 | MEDIDO | `crit:media` | `PatientFormModal.vue:256-280`; sin `lang/es` | #403 |
| F7 | ficha → Exportar | Exportar como finanzas | No ofrecer lo que la política rechaza | Botón visible y 403 `application/json` | 4 | MEDIDO | `crit:media` | `PatientDetailPage.vue:39`, `Permissions.php:314-326`, `PatientPolicy.php:85-96` | #404 |
| F8 | lista y ficha | Leer los títulos | Un título visible; la entidad como encabezado | «Pacientes» dos veces; el `h1` no nombra al paciente | 2 | MEDIDO | `crit:media` | `AppLayout.vue:1152-1192`; `:71` / `:67` | #405 |
| F9 | ficha tabs perezosos | Abrir Laboratorio/Historial | Cargar antes que vaciar | Vacío antes de pedir, luego spinner | 4 | MEDIDO | `crit:baja` | Paneles montados con el vacío resuelto | #387 (comentario) |

Observaciones sin issue: el filtro de sede funciona y devuelve 0 filas porque los
105 pacientes del seed no tienen sede (población, no defecto); los toasts se
acumulan visibles mientras se encadenan intentos; la atribución de peticiones de
los tabs que cargan al montar es aproximada en el instrumento (el status se toma
de la fila de render del rol).

## Criterio de salida (S5)

| Criterio | Resultado |
| -------- | --------- |
| CRUD sin residuos | **Cumple con dos residuos declarados**: cargo 5 (libro inmutable, ADR-0015) y presupuesto 13 (soft delete); toda fila visible se retiró |
| Las tabs abren con sus tres estados | **No cumple** (F2 y F1) |
| El estado de cuenta cuadra con el seed | **Cumple** |
| Los filtros sobreviven el viaje | **No cumple** (F3) |
| Cero `pageerror` | **Cumple** (0 en 8 filas de render y 10 recorridos) |
| Rúbrica puntuada | **Cumple** (14/24 y 17/24) |
