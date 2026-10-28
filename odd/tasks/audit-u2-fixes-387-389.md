# Task: cierre de hallazgos U2 — primer frame, aislamiento y copy de error (#387/#388/#389)

Status: OPEN. Rama `fix/audit-u2-387-389` en el worktree `<ruta-local>` desde
`56103c5d`. Un commit por issue con su prueba al lado; la revisión nativa, el
push y el PR quedan como decisión del señor Arnold.

## Specs

- **S1** — Pedido del usuario, verbatim: «haz las issues #387 #388 #389 en un
  worktree nuevo».
- **S2** — #387 «[UI] Dashboard y Mi día pintan su estado vacío antes de
  cargar», criterios de aceptación verbatim:
  ```
  - `/dashboard` pinta el skeleton desde el primer frame y no muestra estados
    vacíos antes de que resuelvan las cuatro fuentes; la carga de la caja no
    bloquea la carga de KPIs, agenda, pendientes y próximas.
  - `/my-day` pinta el skeleton desde el primer frame y no muestra contadores en 0
    como si la cola estuviera vacía.
  - Con 9 citas reales, ningún primer frame muestra 0 ni «Sin citas para hoy».
  - Test de navegador que falle si el primer frame publica `data-state="empty-*"`
    con una fuente aún en vuelo.
  ```
- **S3** — #388 «[UI] Si falla el resumen del dashboard desaparecen agenda,
  pendientes y próximas», criterios de aceptación verbatim:
  ```
  - Un fallo de `stats` deja visibles la agenda, los pendientes y las próximas si
    sus propias respuestas llegaron, con el error del resumen en su espacio.
  - El reintento del resumen recupera sólo su bloque sin desmontar el resto.
  - Test de navegador: cortar `stats` y comprobar que `data-dashboard-block`
    conserva `hoy`, `attention` y `upcoming`.
  ```
- **S4** — #389 «[UI] Mi día muestra «Failed to fetch» como mensaje de error»,
  criterios de aceptación verbatim:
  ```
  - Un fallo de red de `/api/my-day` muestra un mensaje en español que nombra la
    condición («No pudimos actualizar tu día» / «No se pudo cargar tu día»), no el
    texto del navegador.
  - Un error de dominio conserva su mensaje del servidor (p. ej. 409 de custodia).
  - El reintento sigue recuperando la cola.
  ```
- **S5** — AGENTS.md §7 (frontend), verbatim: «Composition API + `<script setup>`
  (preferido). […] Estilos: Tailwind 3 + design tokens. […] API: SIEMPRE
  `useApi().request/get/post/...`. NO axios directo.» La corrección se queda en
  los composables y las páginas existentes.
- **S6** — AGENTS.md §7 (formato), verbatim: «Formato: TODO dinero, fecha y hora
  sale de `composables/useFormatters.js` (locale `es-PE`, reloj 24 h).» Los
  contadores de Mi día no formatean fechas ni importes nuevos.
- **S7** — AGENTS.md §7 (commits), verbatim: «Commits: prefijos semánticos
  (`feat/`, `fix/`, `chore/`, `refactor/`), sin emojis.» y «Multi-branch,
  mensaje corto y descriptivo.»

## Estrategia

- Un commit por issue (T1–T3), cada uno con sus pruebas de humo de Vitest y su
  fila de navegador; un cuarto commit al cierre con la documentación
  (AGENTS.md §12 y esta nota). Nada de backend: los tres hallazgos son de
  superficie.
- Las pruebas de navegador viven en `tests/e2e/u2-resilience.spec.js`, con el
  mismo patrón manual de `tests/e2e/cash-current.spec.js`: cabecera con el
  setup (base dedicada, `php artisan serve`, build con `VITE_APP_URL`) y guarda
  de entorno (`U2_RESILIENCE_BROWSER=1`) para no romper CI, que no levanta app
  ni base.
- `pnpm test:js` (Vitest), `pnpm lint:check`, `pnpm format:check` y
  `pnpm build` son las verificaciones deterministas; la corrida de navegador se
  intenta sobre una base dedicada y su evidencia queda en `.atl/` (ignorado).
  El wrapper de pnpm no corre en el worktree porque `node_modules` es un enlace
  al repo principal: se invoca `./node_modules/.bin/<bin>`.

## Tasks

- [ ] **T1** · S2, S5, S6 · `inline` · #387: el dashboard arranca con el
  skeleton y la caja entra en el lote paralelo; Mi día arranca con el skeleton y
  sus contadores no pintan 0 antes de la primera respuesta. Pruebas
  `dashboard-first-paint` y `my-day-first-paint`; fila de navegador del primer
  frame.
- [ ] **T2** · S3, S5 · `inline` · #388: un fallo de `stats` deja su bloque de
  error en su espacio y el resto de la página montada; el reintento re-consulta
  sólo el resumen. Prueba `dashboard-section-isolation`; fila de navegador con
  `stats` cortado.
- [ ] **T3** · S4, S5 · `inline` · #389: el mensaje de error de Mi día prefiere
  el sobre de la API y cae al texto español ante un fallo de transporte; un 409
  de custodia conserva su mensaje. Pruebas `my-day-error-message`; filas de
  navegador de error y reintento.
- [ ] **T4** · S1–S7 · `inline` · Verificación: Vitest, ESLint, Prettier, build,
  corrida de navegador con evidencia y guard de deriva de AGENTS.md.
- [ ] **T5** · S1, S7 · `inline` · Cierre: entrada de AGENTS.md §12 con los
  números medidos, esta nota en DONE y veredicto por `S#`.

## Log

- **L1** — Pedido del usuario, verbatim: «haz las issues #387 #388 #389 en un
  worktree nuevo».
- **L2** — Base y rama: `56103c5d` (`main`, tras #473), rama
  `fix/audit-u2-387-389`, worktree `<ruta-local>`. Las tres issues son
  `area:frontend` y del programa `auditoria-2026-10`; #387 y #388 son
  `crit:media`, #389 `crit:baja`.
- **L3** — Lectura de las causas publicadas: `DashboardPage.vue` arranca
  `loading = false` y su `onMounted` espera `loadCurrentSession()` antes de
  `loadDashboardData()`; los bloques `hoy`, `attention` y `upcoming` viven bajo
  `v-if="!statsError"`; `useMyDay.js` arranca `loading = false` y
  `messageFor()` prefiere `thrown.message`, que en un fallo de transporte es el
  `TypeError` del navegador.
