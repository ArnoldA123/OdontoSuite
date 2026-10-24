# Task: U0 — arnés de audit-test y línea base de las 36 rutas

Status: IN PROGRESS. Rama `feat/audit-ui-u0` desde `30f7ef1`.

## Specs

- **S1** — Criterio de salida (issue #363, verbatim):
  ```
  node scripts/audit/ui-pass.mjs --all
  ```
  «Sale 0 con 36 filas clasificadas (renderiza / no renderiza / excluida con
  motivo), cero `pageerror` y el manifiesto declara 0 botones sin clasificar.»
- **S2** — Método, capa 1 (issue #363, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --all --out=.atl/qa-evidence/audit/ui/U0/manifest.json
  ```
  La salida es `[{ route, name, page, roles, tabs[], dialogs[], actions[{label,
  kind, target}], endpoints[], permission }]` (plan #13 §4, capa 1) y la capa
  declara «0 superficies sin clasificar».
- **S3** — Criterio de salida (issue #363, verbatim):
  «`pnpm exec playwright test --list` incluye la spec nueva.»
- **S4** — Método del arnés (issue #363, verbatim): «El login va por API una vez
  por rol y reusa el token en `localStorage` (throttle 3/min + bloqueo de 10 min
  tras 5 fallos).»
- **S5** — Capa 2 (plan #13 §4, verbatim): respuesta 200 y render del marcador;
  «Cero `console.error`, cero `pageerror`, cero request ≥ 400 salvo allowlist
  declarada»; longitud de contenido sobre el umbral; capturas `desktop 1440×900`
  y `mobile 390×844` siempre explícitas; «Sin scroll horizontal de documento en
  390 px».
- **S6** — Capa 3 (plan #13 §4, verbatim): «Centrado horizontal», «Docking», «Un
  solo `h1`», «Escape cierra», «Foco atrapado», «Scroll bloqueado», «Etiqueta
  accesible».
- **S7** — Entregable (issue #363, verbatim): «`scripts/audit/ui-manifest.mjs`,
  `tests/e2e/audit/harness.js`, `tests/e2e/audit/surface.spec.js`,
  `scripts/audit/ui-pass.mjs`, manifiesto de las 36 rutas y
  `.atl/qa-evidence/audit/ui/U0/` con `index.html` de galería +
  `odd/tasks/audit-ui-u0.md`.»
- **S8** — Plan #13 §6.3 (verbatim): «`.atl/` está en `.gitignore`
  (verificado), así que las capturas y JSON **no se commitean**»; la nota ODD es
  «lo que sobrevive».
- **S9** — Plan #13 §2 (verbatim): «**La sesión no corrige**»: U0 crea el
  instrumento; no toca código de producto.

## Estrategia de entrega

El instrumento es un parser, así que las rebanadas son capas, no archivos
sueltos: cada commit añade una capa legible por sí misma y el último la cablea.
`text.mjs` (415) → `sources.mjs` (468) → `api-routes.mjs` (387) → `routes.mjs`
(214) → `build.mjs` + entrada + contrato (≈520). Los commits intermedios no
montan todavía la entrada; el árbol queda verde en el último, que es donde el
test corre. La revisión humana gana cinco unidades de ~400 líneas en vez de un
monolito de 1 770.

## Tasks

- [x] **T1** · S2, S9 · `inline` · Manifiesto estático `scripts/audit/ui-manifest.mjs` (rutas de `app.js`, roles de `AppLayout.navigation`, pestañas, diálogos, acciones clasificadas, endpoints y permiso de ruta) con test unitario determinista. Commits `ea1295f`, `ff49753`, `644743a`, `3761339` (+ capa de montaje).
- [ ] **T2** · S3, S4, S5, S6 · `inline` · Arnés `tests/e2e/audit/harness.js` (login por API por rol, token en `localStorage`, diagnóstico, capturas explícitas, contrato de diálogo) y `tests/e2e/audit/surface.spec.js` (capas 2 y 3 parametrizadas por manifiesto). Commit `feat(audit): arnés de navegador por rol y contrato de primitivas`.
- [ ] **T3** · S1, S7 · `inline` · Envoltorio `scripts/audit/ui-pass.mjs`: resumen de 36 filas clasificadas, galería `index.html` ordenada por ruta y rol, y código de salida. Commit `feat(audit): ui-pass con resumen por ruta y galería`.
- [ ] **T4** · S1, S5, S8 · `inline` · Corrida en vivo sobre `odontosuite_audit` (migrate --seed, app, Vite y Reverb arriba), correcciones del arnés que la corrida destape y evidencia en `.atl/qa-evidence/audit/ui/U0/`. Commit `fix(audit): correcciones del arnés medidas en la línea base`.
- [ ] **T5** · S1, S2, S3, S8, S9 · `inline` · Cierre: nota ODD con los números medidos, changelog del plan #13, `pnpm lint:check`, `pnpm format:check`, `pnpm test:js` y `pnpm exec playwright test --list`. Commit `docs(audit): línea base de las 36 rutas (U0)`.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 363-U0».
- **L2** — Cuerpo de #363 (verbatim, extractos que fijan el alcance): objetivo
  «Un solo comando produce el manifiesto de superficies y la evidencia de render
  y contrato de primitivas de las 36 rutas»; método en tres comandos (S1/S2) y
  «La captura es siempre explícita (`page.screenshot()`), porque
  `playwright.config.js` usa `only-on-failure` y no deja registro visual por sí
  sola»; «El manifiesto deriva ruta → componente → tabs → diálogos → botones →
  endpoints de `app.js`, `AppLayout.navigation` y las primitivas».
- **L3** — Revisión de referencia del plan #13: `8b72d37`; árbol de trabajo en
  `30f7ef1` (sin cambios de producto desde esa revisión: `git diff 8b72d37..HEAD`
  es documentación). El plan mide 38 rutas declaradas, 36 con pantalla y 2
  redirects (`/` y `/appointments/new`).
- **L4** — Decisión de alcance del manifiesto: `roles` sale de la navegación
  (quién ve la entrada) y hereda del hermano de módulo para las rutas sin entrada
  (`/cash-register/ready-to-bill`) y del listado para los detalles; `permission`
  sale de `routes/api.php` (pila de grupos + constantes de
  `App\Authorization\Permissions`), no de una lista inventada.
