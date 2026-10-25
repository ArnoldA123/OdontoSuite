# Task: U2 — Dashboard y Mi día (auditoría UI, plan #13)

Status: DONE. Rama `feat/audit-ui-u2` desde `b9103f7`; instrumento en `f7fb3d5`
(suite `@U2`), esta nota en el commit de documentación y hallazgos publicados en
#387, #388 y #389.

## Specs

- **S1** — Objetivo (issue #365, verbatim): «Verificar que la pantalla de
  aterrizaje responde la pregunta "¿qué hago ahora?" y que cada tarjeta o
  sección falla sola, sin arrastrar a las demás. Medido en `8b72d37`:
  `DashboardPage` concentra 21 marcas de reintento y 0 `EmptyState`;
  `MyDayPage` tiene 4 `Skeleton` y 2 estados vacíos.»
- **S2** — Método, capa 1 (issue #365, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=dashboard,my-day --out=.atl/qa-evidence/audit/ui/U2/manifest.json
  ```
  y capas 2–4 (issue #365, verbatim):
  ```
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U2
  ```
- **S3** — Superficies, roles y acciones (issue #365, verbatim): «Superficies:
  `/dashboard` (KPIs, agenda de hoy, pendientes, próximas, accesos) y `/my-day`
  (en espera, atendiendo, próximos, laboratorio listo). Roles: administrador,
  odontólogo, recepcionista y finanzas para el dashboard; odontólogo, técnico y
  asistente para Mi día. Acciones: cargar KPIs; abrir cada sección y su CTA;
  forzar el error de una sola sección cortando su endpoint y comprobar el
  reintento; verificar que un rol clínico no pide sesión de caja; en Mi día:
  marcar llegada, iniciar atención, continuar consulta, refrescar laboratorio.»
- **S4** — Entregable (issue #365, verbatim): «`render.json` (4 roles),
  `walkthrough.md` con la matriz sección × estado (carga, vacío, error,
  contenido), `ux-rubric.md` y `odd/tasks/audit-ui-u2.md`.»
- **S5** — Criterio de salida (issue #365, verbatim): «Una sección caída deja el
  resto de la página operativa y su reintento la recupera; las 4 acciones de Mi
  día producen su efecto observable; cero `pageerror`; rúbrica puntuada.»
- **S6** — Plan #13 §7 (verbatim): «**Presupuesto**: una sesión por jornada;
  entre 3 y 7 issues por sesión; ninguna corrección de más de 400 líneas (las
  correcciones no son de este programa).» → esta sesión **no corrige producto**.
- **S7** — Plan #13 §6.3 (verbatim): «`.atl/` está en `.gitignore` (verificado),
  así que las capturas y JSON **no se commitean**»; lo versionado es
  «`odd/tasks/audit-ui-<sesión>.md` — resumen versionado: revisión, base, rutas,
  tabla de hallazgos, enlaces a issues y veredicto. Es lo que sobrevive.»
- **S8** — Plan #13 §4 capa 6 (verbatim): «Doce criterios, 0/1/2 cada uno, por
  superficie primaria y una vez por sesión para su conjunto»; la puntuación va a
  `ux-rubric.md` «con la cita de lo observado».
- **S9** — Plan #13 §6.1 (verbatim): «la sesión **solo** escribe en
  `odontosuite_audit`; los borrados se hacen sobre filas que la propia sesión
  creó; nunca `migrate:fresh` sobre la base de desarrollo».
- **S10** — Plan #13 §4 capa 4 (verbatim): «Toda acción con `DEFECTO`, `NO-OP` o
  `CONFUSO` produce fila en `findings.md` con captura y, si hay request, su
  status.»

## Estrategia de entrega

La sesión hereda de U0/U1 manifiesto, arnés y envoltorio; añade la suite `@U2`
en tres módulos (`fixtures`, `dashboard`, `my-day`) y no toca código de producto
(S6). Los tres módulos son **un solo instrumento de medición**: `dashboard.js` y
`my-day.js` importan `fixtures.js` y ambos se registran en `surface.spec.js`, así
que partirlos en commits dejaría el árbol roto o dos mitades de la misma
medición. El commit `f7fb3d5` (1 975 líneas autoradas, 4 archivos) es el límite
declarado de esa unidad; el presupuesto de ~400 líneas aplica a correcciones, no
a este instrumento nuevo (mismo criterio que U0/U1).

Datos de la sesión: las citas y el caso de laboratorio se crean **por API** y se
retiran al final; la cotización pendiente no se inventa (ver «Limitaciones»).

## Tasks

- [x] **T1** · S2, S3, S5 · `delegated` · Suite `@U2`:
  `tests/e2e/audit/u2/{fixtures,dashboard,my-day}.js` importados por
  `surface.spec.js`; 17 filas (4 renders del dashboard, 4 aislamientos por
  sección, 2 CTA, 1 sin Reverb, 3 renders de Mi día, custodia, laboratorio y
  error). Commit `f7fb3d5`.
- [x] **T2** · S1, S2, S3, S5, S9 · `inline` · Corrida en vivo sobre
  `odontosuite_audit` (migrate --seed, app, Vite, Reverb y worker de cola) de
  los dos comandos de S2, con dos correcciones del instrumento que la corrida
  destapó (ver «Correcciones del instrumento»). Evidencia en
  `.atl/qa-evidence/audit/ui/U2/`.
- [x] **T3** · S4, S7, S8, S10 · `inline` · `walkthrough.md`, `ux-rubric.md` y
  esta nota con los números medidos y el veredicto; hallazgos publicados en #387,
  #388 y #389.
- [x] **T4** · S5 · `inline` · Cierre: `pnpm lint:check`, `pnpm format:check`,
  `pnpm test:js`, `pnpm exec playwright test --list` y reverberificación de los
  dos comandos de S2 sobre el árbol final.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 365-U2».
- **L2** — Cuerpo de #365 (lo citado en S1–S5 es verbatim de ahí) y plan #13
  §7: «U2 — Dashboard y Mi día», con la hipótesis INFERIDO: «`DashboardPage`
  usa 21 marcas de reintento/`retry` y cero `EmptyState`; verificar si un fallo
  de una sección degrada toda la página o solo su tarjeta.»
- **L3** — Estado al abrir la sesión (`b9103f7`): la página ya declara
  `data-dashboard-block` (`hoy`, `attention`, `upcoming`), errores por sección
  y estados vacíos compartidos (`DashboardSectionEmpty`); la hipótesis del plan
  se re-mide. `/my-day` ya publica `data-my-day-*`, `UiSkeleton` y
  `UiSectionEmpty`. La sección «accesos» que nombra la issue **no existe** en la
  página: sus entradas son los CTA de las tarjetas.
- **L4** — Precondiciones: `odontosuite_audit` recreada y sembrada
  (`migrate --seed --force`, 17 seeders); app en `:8000` con
  `DB_DATABASE=odontosuite_audit`, Vite en `:5173`, Reverb en `:8081` y
  `queue:work` sobre la misma base. Sin worker, `ShouldBroadcast` deja los
  eventos en `jobs` (58 en cola) y la acción de laboratorio no llega: el worker
  es parte del entorno de la sesión.
- **L5** — Datos: se sembraron 6 citas de hoy (`completed`, administrador),
  0 pendientes, 0 próximas y 0 casos de laboratorio; la suite creó 3 citas de
  hoy para `ever_odontologo` (una `scheduled`, una `arrived`, una
  `in_consultation`), 1 de mañana, 1 caso en `in_preparation` y 1 plan
  `proposed`, y los retiró al final.
- **L6** — Correcciones del instrumento tras la primera corrida: (a) las filas
  medían el **primer frame** (la rama de contenido con arreglos vacíos) porque
  `settleSurface` se satisface con el hero; se añadió la espera de los cuatro
  payloads (`/api/dashboard/*`, `/api/my-day`) y el skeleton oculto, y se
  conserva ese primer frame como `firstPaint` para F1; (b) se añadió la cita de
  mañana para medir el estado **con contenido** de «Próximos días»; (c) el
  `abort` propio del probe se clasifica como solicitud esperada, no como fallo
  inesperado de la superficie; (d) el reintento registra el status de su
  respuesta.
- **L7** — Limitaciones declaradas: la fila de presupuesto pendiente quedó **NO
  PROBADA** porque ninguna puerta HTTP escribe `quotation.status = "sent"`
  (el alta crea borrador, aprobar/rechazar exigen `sent|viewed`, `PUT/PATCH`
  ignora `status`); el plan `proposed` sí ejercita la sección y su CTA. El
  asistente que abre «Continuar consulta» no declara `role="dialog"` ni trampa
  de foco: se difiere a **U3**, que audita el asistente.
- **L8** — Hallazgos publicados tras medirlos y agruparlos por causa raíz:
  #387 (estado vacío antes de cargar y caja que bloquea los datos del
  dashboard), #388 (un fallo de `stats` desmonta los otros tres bloques) y #389
  (`Failed to fetch` como mensaje visible en Mi día).

## Línea base medida

- Revisión del árbol medido: `f7fb3d5` + esta nota; árbol limpio salvo esta nota.
- **Capa 1** · `node scripts/audit/ui-manifest.mjs --module=dashboard,my-day`:
  exit 0, **2 superficies**, **32 acciones (0 sin clasificar)**, 2 diálogos,
  32 endpoints, **0 permisos sin resolver**. Excluidas 2 redirecciones
  (`/` y `/appointments/new`).
- **Capas 2–4** · `pnpm exec playwright test … --grep @U2` → **17 pasan**, exit
  0, 1m 46s.
- **Envoltorio** · `node scripts/audit/ui-pass.mjs --module=dashboard,my-day
  --no-run` → **gate OK**: 2/2 superficies renderizan, 0 excluidas, 0 sin
  evidencia, **0 `pageerror`**, **36 solicitudes esperadas** (allowlist de
  F1/U0), **0 inesperadas**, 0 acciones sin clasificar, 0 diálogos agregados
  (la suite mide el modal de «Nueva cita» en su propia fila: contrato 7/7).
- **Dashboard por rol** (`render/u2-dashboard-*.json`): administrador y
  recepción 9/6/3 citas con 9 filas de agenda y 1 próxima; administrador ve 1
  pendiente y `S/ 204.00`, recepción ve el vacío de pendientes y `Sin sesión`;
  odontólogo **3 tarjetas y 0 requests de caja** (#345 confirmado); finanzas
  sólo la tarjeta de caja (`S/ 61.00`) porque no tiene `view.appointment`.
  0 `pageerror`, 0 overflow a 390 px en los cuatro roles.
- **Mi día por rol** (`render/u2-my-day-*.json`): odontólogo 1/1/1/0 con las
  tres acciones; técnico y asistente 0/0/0/0 con tres vacíos y sin acciones
  (permisos). 0 `pageerror`, 0 overflow.
- **Aislamiento por sección** (`sections-*.json`): cortar `appointments-today`,
  `upcoming` o `pending` deja el resto operativo y el reintento (200) recupera;
  cortar `stats` deja **0 bloques de datos** y el reintento recupera los tres.
- **Acciones de Mi día**: llegada (200, próximos → espera, contadores
  1/1/1 → 2/1/0, toast), inicio (200, espera → atendiendo, 2/1/0 → 1/2/0,
  toast), continuar (abre «Expediente de Cita»), laboratorio (el caso pasa a
  `delivered` y el bloque aparece **sin recargar**, con toast). Error de cola:
  bloque con `Failed to fetch`, reintento 200 y cuatro secciones.
- **Sin Reverb**: la página conserva 9 filas y 3 bloques con 0 `pageerror`; el
  indicador llega a `unavailable` a los 15 s (sonda propia).

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue | Reproducción |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- | ------------ |
| F1 | `/dashboard` y `/my-day` | Cargar la página | Skeleton desde el primer frame; nunca datos vacíos falsos | Primer frame con 0/0/0, «Sin citas para hoy» y sin pendientes con 9 citas reales; en Mi día, contadores 0 y sin secciones | 2/4/6 | MEDIDO | `crit:media` | `DashboardPage.vue`: `loading` nace en `false` y `onMounted` espera `loadCurrentSession()` antes de `loadDashboardData()`; `MyDayPage`/`useMyDay` esperan `load()` tras el primer frame | #387 | `/dashboard` como administrador con `/api/cash-register/current` lento; comparar `firstPaint` con `snapshot` |
| F2 | `/dashboard` | Cortar `GET /api/dashboard/stats` | Una sección caída deja el resto operativo | `blocks: []`: desaparecen KPIs, agenda, pendientes y próximas, con las otras tres fuentes en 200 | 2/4 | MEDIDO | `crit:media` | `DashboardPage.vue`: los tres bloques viven bajo `v-if="!statsError"` | #388 | bloquear sólo `api/dashboard/stats`, recargar y leer `data-dashboard-block` |
| F3 | `/my-day` | Cortar `GET /api/my-day` | Mensaje en español y reintento | Bloque de error con `Failed to fetch` (texto del navegador); el reintento sí recupera | 2/4 | MEDIDO | `crit:baja` | `useMyDay.js` prefiere `thrown.message` antes del texto de reserva | #389 | bloquear `api/my-day`, recargar y leer `data-my-day-error-message` |

**Observaciones sin issue de esta sesión**

- **Asistente de consulta sin primitiva de diálogo**: `ConsultationWizard`
  abre con encabezado «Expediente de Cita» pero sin `role="dialog"` ni trampa
  de foco (`dialogRoleCount: 0`). Pertenece a **U3**, que audita el asistente.
- **Cotización `sent` inalcanzable por HTTP**: no hay puerta que escriba el
  estado; la fila de presupuesto pendiente del dashboard queda NO PROBADA. Es
  material de **U6/U8** (presupuestos y catálogo), no de esta sesión.
- **F1 de U0 heredado** (canales privados que el rol no puede autorizar):
  contado en la allowlist (36 solicitudes esperadas) y ya publicado en #378.

## Correcciones del instrumento

Cerradas en `f7fb3d5`, no son hallazgos de producto: espera de payloads y del
skeleton antes de leer el estado (la primera corrida medía el primer frame),
cita de mañana para el estado con contenido de «Próximos días», clasificación
del `abort` propio como solicitud esperada y status del reintento en la
evidencia.

## Revisión nativa (RDD)

- Linaje `review-a5ac30d23f80e77d`, nivel `medium`, lentes `readability` y
  `reliability`; candidato de 5 archivos y 2 190 líneas sobre `b9103f7` (árbol
  de `09babf3`). Previsión: 2 ejecuciones de modelo por el relé del host.
- El grupo de dos lentes se sometió en una sola captura (prompts de 98 457 y
  98 436 bytes; resultados de 4 994 y 5 872). Veredicto: **aprobado**;
  autoridad consumida (`authority: burned`).
- Once hallazgos asesores, todos informativos y no bloqueantes: `R2-1`–`R2-6`
  (legibilidad del arnés y de la suite) y `R3-001`–`R3-005` (sugerencias de la
  suite). Ninguno abrió corrección ni reabre el candidato; quedan como trabajo
  posterior.
- La entrega sigue la política ordinaria del repositorio: la revisión no
  autoriza push ni merge por sí sola.

## Veredicto

El criterio de salida de #365 se cumple en tres de sus cuatro partes:
**cero `pageerror`** en las dos superficies y los siete roles medidos; **las 4
acciones de Mi día producen su efecto observable** (llegada, inicio, asistente y
laboratorio en vivo); **la rúbrica está puntuada** (40/48, sin ceros). La parte
«una sección caída deja el resto operativa» se cumple para `appointments-today`,
`upcoming` y `pending`, y **falla para `stats`** (F2), publicado en #388.

La hipótesis INFERIDO del plan se corrige con la medición: la página **sí** usa
estados vacíos (`DashboardSectionEmpty` en las tres secciones) y sus reintentos
por sección existen; el defecto real es el orden de carga (F1) y el acoplamiento
del resumen (F2).

La sesión no corrige producto (S6): deja tres hallazgos medidos, con causa
anclada, evidencia y receta de reproducción.

## Seguimientos

- #387 — estado vacío antes de cargar y caja que bloquea los datos (F1).
- #388 — un fallo de `stats` desmonta agenda, pendientes y próximas (F2).
- #389 — `Failed to fetch` visible en Mi día (F3).
- Asistente sin `role="dialog"`: U3. Cotización `sent` inalcanzable: U6/U8.
- `.atl/qa-evidence/audit/ui/U2/` no se commitea (S7); esta nota es lo que
  sobrevive.
