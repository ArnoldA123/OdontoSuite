# Task: U0 — arnés de audit-test y línea base de las 36 rutas

Status: DONE. Rama `feat/audit-ui-u0` desde `30f7ef1`; línea base en `2a0c74a`.

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
- [x] **T2** · S3, S4, S5, S6 · `inline` · Arnés `tests/e2e/audit/harness.js` (login por API por rol, token en `localStorage`, diagnóstico, capturas explícitas, contrato de diálogo) y `tests/e2e/audit/surface.spec.js` (capas 2 y 3 parametrizadas por manifiesto). Commit `45676f8`.
- [x] **T3** · S1, S7 · `inline` · Envoltorio `scripts/audit/ui-pass.mjs`: resumen de 36 filas clasificadas, galería `index.html` ordenada por ruta y rol, y código de salida. Commit `a5236a3`.
- [x] **T4** · S1, S5, S8 · `inline` · Corrida en vivo sobre `odontosuite_audit` (migrate --seed, app, Vite y Reverb arriba), correcciones del arnés que la corrida destape y evidencia en `.atl/qa-evidence/audit/ui/U0/`. Commit `2a0c74a`.
- [x] **T5** · S1, S2, S3, S8, S9 · `inline` · Cierre: nota ODD con los números medidos, changelog del plan #13, `pnpm lint:check`, `pnpm format:check`, `pnpm test:js` y `pnpm exec playwright test --list`. Commit `docs(audit): línea base de las 36 rutas (U0)`.

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

- **L5** — Entorno de la corrida: `odontosuite_audit` recreada con
  `migrate --seed --force` (18 s), `php artisan serve` en `:8000`, Vite en
  `:5173` y Reverb en `:8081`, `BROADCAST_CONNECTION=reverb`. Los siete roles se
  autentican una vez por API con los usuarios de `CREDENTIALS.md`; el throttle
  de login (3/min) se respeta por construcción.
- **L6** — Datos del seed que usan las muestras de detalle: 100 pacientes, 15
  usuarios, 8 sillones, 8 tipos de cita, 41 procedimientos, 3 sedes y **0
  historias clínicas**. Los siete detalles se resuelven con el token de
  administración; `/medical-records/:id` queda excluida con motivo.
- **L7** — Instrumento corregido por la corrida, no por intuición:
  1. medir en el primer paint clasificaba seis superficies como vacías porque el
     skeleton no tiene texto; el arnés espera el asentamiento (≤12 s) antes de
     medir y capturar;
  2. redimensionar a 390 después de pintar en 1440 conservaba anchos de
     escritorio e inventó overflow en 31 superficies; el móvil se mide ahora con
     una recarga del documento a 390 (contraprueba: `/quotations`,
     `/treatment-plans` y `/patients` dan `scrollWidth 390`);
  3. un plan negativo sin `h1` consumía los 30 s del test en un `innerText` con
     autoespera; el encabezado se lee del DOM sin esperar;
  4. el `addInitScript` del token tocaba `localStorage` en un frame opaco y
     producía un `pageerror` en el probe de `/calendar`; ahora está guardado;
  5. `ui-pass` cuenta también los `pageerror` del probe de primitivas y clasifica
     «no renderiza» como hallazgo (el criterio de #363), con guarda de defecto de
     plataforma si más de la mitad del alcance no renderiza.

## Línea base medida

- Revisión: `2a0c74a`, árbol limpio.
- Comando: `node scripts/audit/ui-pass.mjs --all` → **exit 0**.
- Manifiesto: 36 superficies de 38 rutas (2 redirects), 509 acciones
  clasificadas y 0 sin clasificar, 59 diálogos, 140 endpoints, 3 permisos sin
  resolver (las dos rutas anónimas y el catch-all).
- Playwright: 120 tests (84 de render × rol + 36 de contrato), 114 pasan, 6
  saltan con motivo y 0 fallan.
- Filas: 36 · **35 renderizan** · **1 excluida con motivo** · 0 sin evidencia ·
  **0 `pageerror`** · **0 acciones sin clasificar**.
- Capa 3: 6 diálogos abiertos y medidos sobre 43 disparadores candidatos; 1 fila
  de contrato fallida (F2).
- Diagnóstico: 33 mensajes de `console.error` (los 403 de canales) y 171
  solicitudes ≤ 400 declaradas en la allowlist (F1).
- Evidencia (`.atl/`, ignorado): `manifest.json`, `render/` (84 filas),
  `primitives/` (34), `shots/` (162 PNG), `render.json`, `primitives.json`,
  `run.json` e `index.html` con 36 secciones.

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue | Reproducción |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- | ------------ |
| F1 | Shell, toda superficie autenticada con rol no clínico | Cargar la página | El shell sólo se suscribe a canales que el rol puede autorizar | `POST /api/broadcasting/auth` → 403 para `medical-records`/`specialty-records`; 171 solicitudes esperadas en la corrida | 2 | MEDIDO | `crit:media` | `resources/js/composables/useWebSocketNotifications.js` se suscribe a los nueve canales sin mirar el rol; `BroadcastingAuthController` autoriza por rol | no publicado (decisión del dueño) | login por API como `recepcionista_test`, abrir `/dashboard`, filtrar `broadcasting/auth` en la pestaña Red |
| F2 | `/ai-analysis` | Pulsar «Nuevo análisis» | El sheet atrapa el foco | `focus-trapped` observado `outside` | 3 | MEDIDO | `crit:media` | `Sheet.vue` arma la trampa con la ref vacía cuando se abre por `v-model` | no publicado | abrir `/ai-analysis` como `elizabet`, pulsar el botón del hero y tabular |
| F3 | `/medical-records/:id` | Abrir el detalle | Render con una historia del seed | Excluida: 0 historias sembradas | 2 | MEDIDO | `crit:baja` | El seed no crea historias; U7 las creará al recorrer el alta | no publicado | `SELECT COUNT(*) FROM medical_records` en `odontosuite_audit` → 0 |
| F4 | Instrumento (cerrado en T4) | Medir 390 tras redimensionar | Cargar el documento a 390 | Falso overflow en 31 superficies | 2 | MEDIDO | — | La ventana redimensionada conserva anchos calculados a 1440 | corregido en `2a0c74a` | recargar a 390 y comparar `scrollWidth` con `innerWidth` |

**Veredicto**: el instrumento cumple el criterio de salida de #363. Los
hallazgos quedan registrados con evidencia; U0 no corrige producto (S9).

## Seguimientos

- Los scripts `.mjs` no entran en `pnpm lint:check` (la extensión no está en el
  script). Incluirlos falla hoy con 9 errores preexistentes en 7 scripts
  (`doc-drift`, `probe-*`, `realtime-map`, `build-tokens-css`); la limpieza y la
  inclusión son un cambio de CI, fuera de U0.
- F1 y F2 necesitan issue propio antes de U11 (consistencia transversal).
