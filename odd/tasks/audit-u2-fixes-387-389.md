# Task: cierre de hallazgos U2 — primer frame, aislamiento y copy de error (#387/#388/#389)

Status: DONE. Rama `fix/audit-u2-387-389` en el worktree `<ruta-local>` desde
`56103c5d`. Un commit por issue con su prueba al lado; el push y el PR quedan
como decisión del señor Arnold.

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
- [x] **T1** · S2, S5, S6 · `delegated` · #387: el dashboard arranca con el
  skeleton (`loading = true`) y la caja entra como quinta fuente del mismo
  `Promise.all`; Mi día arranca con el skeleton y sus contadores leen `—` hasta
  la primera respuesta (`hasLoaded` en el composable). Pruebas
  `dashboard-first-paint` (3) y `my-day-first-paint` (1); filas `@387` de
  navegador. Commit `731e98c1`.
- [x] **T2** · S3, S5 · `delegated` · #388: los tres bloques dejan de colgar de
  `!statsError`; el error del resumen se queda en su espacio y `retryStats()`
  re-consulta sólo `/api/dashboard/stats`. Los count-ups y la entrada de
  secciones dejan de depender del resumen. Prueba `dashboard-section-isolation`
  (2), reescritura de las dos filas de `dashboard-states` que fijaban el
  contrato anterior y fila `@388` de navegador. Commit `e0ed2822`.
- [x] **T3** · S4, S5 · `delegated` · #389: `messageFor` sólo devuelve el sobre
  de la API y cae al texto español en un fallo de transporte; un 409 de
  custodia conserva su mensaje. Prueba `my-day-error-message` (3) y fila `@389`
  de navegador. Commit `1430e9d2`.
- [x] **T4** · S1–S7 · `inline` · Verificación en el worktree: Vitest completo,
  ESLint, Prettier, build, suite Unit de PHPUnit, `doc-drift` y la corrida de
  navegador sobre una base dedicada (ver «Evidencia medida»).
- [x] **T5** · S1, S7 · `inline` · Cierre: entrada de AGENTS.md §12 con los
  números medidos, esta nota en DONE y el veredicto por `S#`.

## Evidencia medida

Worktree `<ruta-local>`, rama `fix/audit-u2-387-389`, HEAD de cierre el commit
de documentación; base `56103c5d`.

| Chequeo | Comando | Resultado |
| --- | --- | --- |
| Vitest completo | `./node_modules/.bin/vitest run` | **162 archivos, 1363 pruebas, 0 fallos** |
| Suite MySQL completa | `vendor/bin/phpunit -c phpunit.mysql.xml` (base `odontosuite_u2_resilience_test`, motor local por socket) | **OK (2846 pruebas, 17352 aserciones)** |
| PHPUnit Unit | `php artisan test --testsuite=Unit` | **OK (1427 pruebas, 9709 aserciones; 51 saltadas por SQLite)** |
| ESLint | `eslint . --ext .mjs,.vue,.js,.jsx,.ts,.tsx` | **0 errores, 36 avisos** (línea base) |
| Prettier | `prettier --check .` | limpio |
| Build | `VITE_APP_URL=http://127.0.0.1:8014 vite build` | OK, 14.16 s |
| Deriva de AGENTS.md | `node scripts/audit/doc-drift.mjs` | **14/14** |
| Navegador | `U2_RESILIENCE_BROWSER=1 BROWSER_PASS_BASE=http://127.0.0.1:8014 playwright test tests/e2e/u2-resilience.spec.js` | **4 filas en verde**, 9.4 s |

La corrida de navegador usó una base dedicada (`odontosuite_u2_resilience_e2e`,
`migrate --seed --force`: 15 usuarios, 100 pacientes, 106 citas), `php artisan
serve --port=8014` en el worktree y el build con `VITE_APP_URL` apuntando a ese
origen. Evidencia en `.atl/qa-evidence/audit/u2-resilience/` (ignorada por
git): los dos primeros frames, el resumen caído y el error de transporte.
Filas: `@387 dashboard first frame never publishes an empty state`, `@387
my-day first frame is the skeleton and its counters are not zero`, `@388 a
failed summary leaves the other blocks mounted`, `@389 my-day network failure
explains itself in Spanish and the retry recovers it`.

## Veredicto por S#

- **S2 (#387) — CUMPLE.** El primer frame de `/dashboard` es el skeleton
  (`dashboard-first-paint`, fila `@387` medida) y no publica `data-state="empty-*"`
  con una fuente en vuelo; la caja ya no lo bloquea (la prueba la deja en vuelo y
  las cuatro fuentes se piden igual). `/my-day` arranca en skeleton y sus
  contadores no pintan 0 (`my-day-first-paint`, fila `@387` medida). Con 9 citas
  reales el primer frame medido no muestra ningún vacío ni un 0: la corrida de
  navegador se hizo sobre las 106 citas sembradas.
- **S3 (#388) — CUMPLE.** `dashboard-section-isolation` fija que `hoy`,
  `attention` y `upcoming` siguen montados con el resumen caído y que el
  reintento deja los otros tres contadores de llamadas en 1; la fila `@388`
  compara la lista de `data-dashboard-block` del resumen caído contra la de una
  carga sana y las deja iguales.
- **S4 (#389) — CUMPLE.** `my-day-error-message` fija el texto español ante un
  `TypeError('Failed to fetch')`, el mensaje de servidor de un 409 y la
  recuperación por reintento; la fila `@389` lo mide en Chromium. El mensaje del
  sobre de dominio no se pierde (sólo se deja de preferir el `message` del
  navegador).
- **S5, S6, S7 — CUMPLE.** Composition API y `useApi` en los tres arreglos, sin
  formato nuevo de dinero/fecha, y tres commits `fix(...)` con prefijo semántico
  y sin emojis.
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
- **L4** — Límite de slice declarado (T1, commit `731e98c1`): la unidad reporta
  577 líneas autoradas, de las que 328 son los dos archivos de prueba nuevos,
  95 la primera fila de navegador y 102 esta nota; el comportamiento son 52
  líneas entre `DashboardPage.vue`, `useMyDay.js` y `MyDayPage.vue`. Se entrega
  entera porque el primer frame y las pruebas que lo fijan son una sola regla, y
  partirla dejaría la medición sin su comportamiento.
- **L5** — T2 (#388): el comentario de `DashboardAppShellTest` decía que el
  bloque de error del resumen «replaces the whole page»; se corrigió la prosa
  (la aserción de unicidad no cambia) para que el guard no describa el contrato
  anterior.
- **L6** — T3 (#389): `messageFor` deja de leer `thrown.message`; sólo el sobre
  de la API puede ganar y un fallo de transporte cae al texto español. La misma
  forma (`thrown?.message`) sobrevive en `useContinuity.js`,
  `useBranchTransfers.js` y `BranchTransfersPage.vue`; quedan fuera de esta
  issue (superficies de otra sesión de auditoría) y se reportan como
  seguimiento, no se tocan.
- **L7** — Verificación de navegador (T4): base dedicada
  `odontosuite_u2_resilience_e2e` sembrada, `php artisan serve --port=8014` en el
  worktree y build con `VITE_APP_URL` a ese origen. Las cuatro filas de
  `tests/e2e/u2-resilience.spec.js` en verde (11.0 s); las capturas quedan en
  `.atl/qa-evidence/audit/u2-resilience/`. El servidor se detuvo al terminar; la
  base queda para una re-corrida.
- **L8** — Tamaños de slice: T1 `577` líneas autoradas (328 pruebas nuevas, 95
  navegador, 102 nota, 52 comportamiento), T2 `271` (171 prueba nueva) y T3 `230`
  (187 prueba nueva). Cada commit queda por debajo de 400 salvo la unidad de
  #387, ya declarada en L4; la estrategia de entrega es un commit por issue con
  sus pruebas y su fila de navegador.
- **L9** — Seguimiento observado, fuera de alcance: `useContinuity.js`,
  `useBranchTransfers.js` y `BranchTransfersPage.vue` repiten la forma
  `thrown?.message` en su `messageFor`; la misma clase de defecto de #389 puede
  aparecer en esas superficies y su corrección pertenece a la sesión de
  auditoría que las cubra.
- **L10** — Integración: `main` avanzó durante la sesión (#474, auditoría U8
  del catálogo). Se fusionó `main` (`cb775770`) en la rama con un conflicto en
  AGENTS.md §12, resuelto dejando la entrada de #387/#388/#389 arriba y la de
  U8 debajo; los números de esta nota y de la entrada se re-midieron sobre el
  árbol fusionado (Vitest 1363 en 162 archivos, PHPUnit Unit 1427), y la corrida
  de navegador se repitió sobre el árbol final.
- **L11** — Suite MySQL completa: `vendor/bin/phpunit -c phpunit.mysql.xml`
  contra una base dedicada (`odontosuite_u2_resilience_test`) y el motor local
  por socket, con las credenciales que el señor Arnold indicó en la sesión (no
  se escriben en ningún archivo). Resultado **OK (2846 pruebas, 17352
  aserciones)**, 3 min 20 s; cierra la única prueba que quedaba pendiente.
