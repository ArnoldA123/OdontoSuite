# Task: U1 — Sesión, cuenta y shell (auditoría UI, plan #13)

Status: DONE. Rama `feat/audit-ui-u1` desde `a4ea683`; instrumento en
`e26eb36` (manifiesto multi-módulo), `82a019a` (shell) y `b9e89a8` (login y
recuperación); esta nota en `docs(audit): sesión y shell medidos (U1)`.

## Specs

- **S1** — Objetivo (issue #364, verbatim): «Verificar que una persona entra,
  recupera su contraseña, sale y navega el shell con el rol que tiene, sin
  callejones ni mensajes que filtren detalle. El shell decide qué escritorio ve
  cada rol: si falla, ningún recorrido posterior es comparable.»
- **S2** — Método, capa 1 (issue #364, verbatim):
  ```
  node scripts/audit/ui-manifest.mjs --module=auth,layout --out=.atl/qa-evidence/audit/ui/U1/manifest.json
  ```
  y capas 2–3 (issue #364, verbatim):
  ```
  pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U1
  ```
- **S3** — Superficies y roles (issue #364, verbatim): «Superficies: `/login`,
  `/reset-password`, catch-all 404 y `AppLayout` (sidebar, topbar, menú de
  usuario, indicador WS, `ConfirmDialog` global, sheet de navegación móvil).
  Roles: anónimo + los 7 roles para la matriz de entradas visibles. Acciones:
  login válido e inválido; throttle a los 3 intentos/min; bloqueo de 10 min tras
  5 fallos con usuario descartable; olvido de contraseña con correo a
  `laravel.log`; reset con token; logout; `requireGuest`; `requireAuth`; 404
  dentro del shell; cada entrada del sidebar; menú de usuario; modo móvil.»
- **S4** — Entregable (issue #364, verbatim): «`render.json` de 7 roles ×
  login/404/shell, `primitives.json` del sheet y del confirm, `walkthrough.md`,
  `ux-rubric.md` y `odd/tasks/audit-ui-u1.md`.»
- **S5** — Criterio de salida (issue #364, verbatim): «Cada entrada del sidebar
  es visible solo para los roles que declara `AppLayout.navigation`; cero
  `pageerror` y cero request ≥ 400 fuera de la allowlist (401/403 de las pruebas
  negativas); el sheet cumple su docking derecho y el confirm su centrado,
  Escape y foco; rúbrica de shell puntuada.»
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

## Estrategia de entrega

El instrumento de U0 no cubría esta sesión en dos puntos medidos: `--module`
aceptaba un solo módulo y el shell no es una ruta, así que ninguna entrada del
manifiesto lo representaba. La entrega va en tres rebanadas temáticas: manifiesto
(`e26eb36`), suite del shell (`82a019a`) y suite de cuenta (`b9e89a8`), más esta
nota.

Las dos rebanadas de suite superan el presupuesto de ~400 líneas (782 y 999
autoradas). La razón queda escrita: son archivos de prueba **nuevos** de un
instrumento de medición, y el límite natural es el módulo que mide cada mitad
(shell contra sesión); partir un mismo archivo de declaraciones por la mitad
dejaría dos mitades de la misma medición en commits distintos sin reducir lo que
el revisor tiene que leer, y dejaría el árbol roto entre ellos porque la spec
importa los tres módulos.

## Tasks

- [x] **T1** · S2, S3 · `inline` · Manifiesto: selección multi-módulo
  (`--module=auth,layout`), `navigation` publicada en todo manifiesto con las
  claves del SPA resueltas a permisos de la API, y superficie pseudo-ruta
  `(shell)` más el catch-all bajo el módulo `layout`. Commits `e26eb36`
  (+ verificación: 17 tests del manifiesto, `--all` intacto en 36).
- [x] **T2** · S2, S3, S5 · `delegated` · Suite `@U1` en tres módulos
  (`u1/shell.js`, `u1/login.js`, `u1/recovery.js`) importados por
  `surface.spec.js`: matriz de 7 roles contra `AppLayout.navigation`, 404 dentro
  del shell, recorrido de cada entrada, menú de usuario, indicador WS, contrato
  del sheet móvil y del confirm global; `requireAuth`/`requireGuest`, login
  válido e inválido, throttle, bloqueo con usuario descartable, olvido con
  correo a `laravel.log`, reset con token y logout. Commits `82a019a`,
  `b9e89a8`.
- [x] **T3** · S1, S2, S5, S9 · `inline` · Corrida en vivo sobre
  `odontosuite_audit` de los dos comandos de S2, con las correcciones del
  instrumento que la corrida destapó (ver «Correcciones del instrumento»).
  Evidencia en `.atl/qa-evidence/audit/ui/U1/`.
- [x] **T4** · S4, S7, S8 · `inline` · `walkthrough.md` y `ux-rubric.md` en la
  evidencia y esta nota con los números medidos, la tabla de hallazgos y el
  veredicto.
- [x] **T5** · S2, S4, S5 · `inline` · Cierre: `pnpm lint:check`,
  `pnpm format:check`, `pnpm test:js`, `pnpm exec playwright test --list` y
  reverberificación de los dos comandos de S2 sobre el árbol final.

## Log

- **L1** — Pedido del usuario, verbatim: «haz la issue 364-U1».
- **L2** — Cuerpo de #364 (lo citado en S1–S5 es verbatim de ahí). El plan #13
  §7 describe la sesión: «`U1` — Sesión, cuenta y shell» con «Hipótesis
  (INFERIDO): `LoginPage.vue` (1 456 líneas) no usa `Skeleton` ni
  `LoadingSpinner`»; las capas aplicables son 2, 3, 4 y 6.
- **L3** — Instrumento heredado de U0 (revisión `2a0c74a`): 36 superficies, 509
  acciones clasificadas, 0 sin clasificar; `--module` filtraba un único módulo y
  `applyFilter`/`applyScope` debían seguir coincidiendo. Hallazgos heredados que
  esta sesión vuelve a medir: F1 (403 de `broadcasting/auth` en el shell de los
  roles no clínicos) y F2 (sheet de `/ai-analysis` sin trampa de foco).
- **L4** — Decisión de alcance del manifiesto: el shell no es una ruta, así que
  se declara como superficie pseudo-ruta `(shell)` con `href /dashboard`,
  seleccionable solo por el módulo `layout`, y el módulo `layout` incluye además
  el catch-all (la página 404 se pinta dentro del shell). `--all` sigue en 36
  filas para no mover la línea base de U0.
- **L5** — Entorno de la corrida: `odontosuite_audit` recreada con
  `migrate --seed --force`; `php artisan serve` en `:8000`, Vite en `:5173` y
  Reverb en `:8081`. Reverb se levanta aunque el plan §6.1 use
  `BROADCAST_CONNECTION=null` porque el cliente toma su configuración de
  `import.meta.env.VITE_REVERB_*` (build-time): sin servidor WS el indicador
  nunca llega a `connected` y la sesión no puede auditar su estado real. La base
  de auditoría es la única que recibe escrituras (S9).
- **L6** — Usuarios descartables: el bloqueo de 10 min y el reset se prueban
  sobre usuarios que la sesión crea con `POST /api/users` (administrador) y
  elimina al final; nunca sobre los 7 usuarios de `CREDENTIALS.md`. El throttle
  de `/auth/login` es por `ip+username`, así que los 7 logins por API del arnés
  no se ven afectados. `forgot-password` responde `throttle:3,10`: una sola
  llamada por corrida.
- **L7** — T1 se entregó incompleto por un fallo del proveedor del subagente
  (su mensaje final falló, no su trabajo): el árbol tenía los cinco archivos
  escritos y el padre los verificó y los commiteó en `e26eb36`. El `navigation`
  real de `AppLayout.vue` tiene **25** entradas, no 19 como estimó el contrato
  inicial; manda la fuente y el test lo fija en 25.
- **L8** — Corrección de instrumento que la sesión destapó antes de medir: el
  contrato de diálogos buscaba `[role="dialog"]`, y el confirm global se pinta
  como `role="alertdialog"`, así que la fila del confirm no podía observarse.
  `DIALOG_SELECTOR` acepta ambos roles.
- **L9** — El flujo de logout revoca el token que usa, y el arnés memoiza un
  token por rol: en la primera corrida completa las filas posteriores al logout
  quedaron sin autenticar y el catch-all de recepción registró 10 × `401` en
  `broadcasting/auth`. Se reprodujo con una sonda propia (token fresco: `403`,
  como en la matriz) y se corrigió con `freshLogin()`, que no toca la memoización.
- **L10** — La superficie `(shell)` quedó fuera de los bucles genéricos de
  `surface.spec.js`: no es una ruta, el bucle habría navegado a una ruta
  inexistente y los nombres de archivo (`render/shell-<rol>.json`) habrían
  sobrescrito la evidencia del shell. `hrefFor` honra `surface.href` para
  cualquier consumidor futuro.
- **L12** — Revisión nativa del candidato: aprobada tras tres rechazos del
  admitidor nativo (dos `stream`/payload truncados y un payload de 151 KB),
  todos resueltos releyendo STATUS y corriendo solo el slot reofrecido, nunca
  reenviando los bytes rechazados. Los ocho hallazgos asesores quedan como
  trabajo posterior; solo `R1` (token vivo en la evidencia de logout) se
  corrigió en el acto por higiene de credenciales.
- **L14** — Hallazgos publicados tras la autorización del dueño («si hacelo
  publica»): cinco issues agrupados por clase raíz, enlazados desde #364.
- **L13** — Decisión del dueño, verbatim: «si ya verificaste por afuera y validaste
  con test pues omite las lentes, si todo esta okey has push pr merge y limpia
  todo lo que ya no uses». Se abandonó el segundo linaje
  (`operator_disposition`) en vez de reintentar una lente inestable por cuarta
  vez, y la entrega sigue el camino ordinario: push, PR y merge una vez verde
  el CI, más la limpieza del entorno de auditoría.
- **L11** — El `a[href="/dashboard"]` del logo colapsado competía con la primera
  entrada del sidebar y Playwright rechazaba el clic por ambigüedad; la sonda y
  la matriz leen `#primary-sidebar nav a`.

## Línea base medida

- Revisión del árbol medido: `b9e89a8` + esta nota; árbol limpio.
- **Capa 1** · `node scripts/audit/ui-manifest.mjs --module=auth,layout` →
  exit 0, 4 superficies (`/login`, `/reset-password`, `/:pathMatch(.*)*`,
  `(shell)`), 43 acciones **0 sin clasificar**, 5 diálogos, 5 endpoints,
  4 permisos sin resolver (las dos rutas anónimas, el catch-all y el shell) y
  **25 entradas** de `navigation` publicadas con
  `manageAccountingLock → manage.accounting_lock`.
- **Capa 2y3** · `pnpm exec playwright test tests/e2e/audit/surface.spec.js --grep @U1`
  → **39 pasan** (24 del shell, 12 del login, 3 de recuperación), exit 0,
  4.5 min.
- **Envoltorio** · `node scripts/audit/ui-pass.mjs --module=auth,layout` →
  **44 pasan, 2 saltan, 0 fallan**; **gate OK**, exit 0. Filas: 4 superficies,
  **4 renderizan**, 0 excluidas, 0 sin evidencia, **0 `pageerror`**,
  **0 solicitudes fuera de la allowlist**, 45 explicadas por F1, 0 acciones sin
  clasificar, 2 diálogos probados con **2 filas de contrato fallidas** (el
  sheet).
- **Matriz del sidebar** (comparación exacta y en orden contra
  `AppLayout.navigation`, filtrada por rol y permiso efectivo):

  | Rol | Entradas | `match` | Etiquetas | WS | `pageerror` |
  | --- | --- | --- | --- | --- | --- |
  | administrador | 23 | ✅ | ✅ | connected | 0 |
  | odontologo | 13 | ✅ | ✅ | connected | 0 |
  | implantologo | 13 | ✅ | ✅ | connected | 0 |
  | recepcionista | 9 | ✅ | ✅ | connected | 0 |
  | tecnico_dental | 9 | ✅ | ✅ | connected | 0 |
  | asistente | 9 | ✅ | ✅ | connected | 0 |
  | finanzas | 8 | ✅ | ✅ | connected | 0 |

  **84 entradas navegadas** en total, todas con navegación de cliente y
  0 `pageerror`.
- **404**: 7 filas con el shell visible y «Página no encontrada»; 2 `h1`
  visibles.
- **Cuenta**: login válido 200 → `/dashboard` con token; inválido 422 con
  mensaje genérico y 0 filtraciones; throttle `422,422,422,429`; bloqueo
  `422 × 5` (tras 62 s) y después 429; recuperación `201/200/200/200/200` con
  token de 64 caracteres leído de `laravel.log`; logout → `/login` con
  `auth_token` y `user` en `null`.
- **Sheet móvil** (390×844): acoplado al borde derecho (`x = 20.2`, ancho
  `369.8`), Escape cierra, scroll bloqueado, entradas iguales a la lista del rol;
  **foco no atrapado** y **sin nombre accesible**.
- **Confirm global**: centrado con 0 px de desvío, foco dentro, Escape cierra,
  scroll bloqueado, nombre «Eliminar ambiente», `role="alertdialog"`, y ninguno
  de los 6 checks emitió un `DELETE`.
- **Hipótesis del plan confirmada**: `LoginPage.vue` no usa `UiSkeleton` ni
  `LoadingSpinner`; la única señal de carga es la morfología del botón de envío
  (5 etapas).
- Evidencia (`.atl/`, ignorado): `manifest.json`, `render/` (25 filas),
  `primitives/` (5), `shots/` (43 PNG), `session/` (16 JSON + 2 capturas),
  `render.json`, `primitives.json`, `run.json` e `index.html` con la galería.

## Hallazgos

| F# | Superficie | Acción | Esperado | Obtenido | Capa | Origen | Severidad | Causa | Issue | Reproducción |
| -- | ---------- | ------ | -------- | -------- | ---- | ------ | --------- | ----- | ----- | ------------ |
| F1 | Shell, toda superficie autenticada (heredado de U0) | Cargar la página | El shell sólo se suscribe a canales que el rol puede autorizar | 403 por rol: 0/2/2/3/8/8/5 (`cash-register`, `medical-records`, `specialty-records`, `treatment-plans`, `quotations`, `appointments`, `reminders`) | 2 | MEDIDO | `crit:media` | `useWebSocketNotifications.js` se suscribe a los nueve canales sin mirar el rol; `BroadcastingAuthController` autoriza por rol | #378 | login por API como `recepcionista_test`, abrir `/dashboard`, filtrar `broadcasting/auth` |
| F2 | Sheet de navegación móvil | Abrir el menú y tabular | El foco queda dentro del diálogo | `focus-trapped` observado `outside`; Tab escapa | 3 | MEDIDO | `crit:media` | `Sheet.vue:242-249` activa la trampa en el `watch` sin `nextTick`; `Modal.vue:282` sí lo hace | #379 | `/dashboard` a 390 px, abrir «Abrir menú» y tabular |
| F3 | Sheet de navegación móvil | Abrir el menú | Nombre accesible con el título «Navegación» | `accessible-name` vacío; el id de `aria-labelledby` no existe en el DOM | 3 | MEDIDO | `crit:media` | `Sheet.vue:28/39` enlaza el `h2` que vive en el slot por defecto y `AppLayout` lo reemplaza con `#header`; `Modal.vue:28/142` ya resuelve ese caso | #379 | abrir el sheet y leer `aria-labelledby` con lector de pantalla o inspector |
| F4 | Menú de usuario del shell | Pulsar «Perfil», «Configuración», «Apariencia» | Abrir la superficie prometida | Sin navegación, diálogo, request ni toast; «Apariencia» es una etiqueta sin control | 4 | MEDIDO | `crit:media` | `AppLayout.vue:425-449`: dos `button` sin `@click` y un `<p>` de sección vacío | #380 | `/dashboard` como administrador, abrir el menú del avatar |
| F5 | Catch-all 404 | Abrir una ruta inexistente | Un solo `h1` visible | Dos `h1`: «OdontoSuite» del topbar y «Página no encontrada» de la página | 2 | MEDIDO | `crit:baja` | `AppLayout.vue:1155-1192`: `/404` no está en `pageTitledRoutes` ni en `pageOwnedHeadingPrefixes` | #381 | `/audit-catch-all` con sesión, contar `h1` |
| F6 | `/reset-password` sin token | Abrir la ruta a secas | Explicar que falta el enlace | Formulario de login, sin diálogo ni explicación: indistinguible de `/login` | 4 | MEDIDO | `crit:baja` | La ruta es pública y `LoginPage` sólo abre el diálogo si la URL trae `token` o `email` | #382 | abrir `/reset-password` sin query |
| F7 | Diálogo de reset | Restablecer con el enlace del correo | No pedir lo que el enlace ya trae | Campo «Código de recuperación» vacío y habilitado aunque `props.token` ya se usa al enviar | 4 | MEDIDO | `crit:media` | `ResetPasswordModal.vue:44-53` pinta el campo siempre; `:421` prefiere `props.token` | #382 | abrir el enlace del correo y mirar el campo |

**Correcciones del instrumento** (cerradas en `82a019a`, no son hallazgos de
producto): contrato de diálogos que ignoraba `role="alertdialog"`; token
compartido revocado por el flujo de logout (`freshLogin()`); superficie
`(shell)` metida en el bucle genérico de rutas; selector ambiguo del logo
colapsado; y el token vivo que la fila de logout escribía en su evidencia
(R1 de la revisión nativa, corregido después).

## Revisión nativa (RDD)

- Linaje `review-334f3de36776a394`, nivel `medium`, lentes `risk`,
  `readability` y `reliability`; candidato de 10 archivos y 2 297 líneas
  autoradas sobre `a4ea683`.
- Iteraciones del relé: el grupo de tres lentes no llegó a someter nada en
  dos intentos (un `stream` cortado y un payload truncado de `risk`); el
  slot de `risk` se capturó solo, `readability` entró en el grupo de dos y
  `reliability` solo, tras un rechazo por payload truncado de 151 KB. Cada
  rechazo lo decidió el admitidor nativo, no el criterio del agente.
- Veredicto: **aprobado**, autoridad consumida (`authority: burned`). Ocho
  hallazgos asesores, todos informativos y no bloqueantes: `R1` (token vivo
  en la evidencia de logout, corregido aquí), `R2-001`–`R2-004` (legibilidad
  del arnés y de la suite) y `R3-reliability-1`–`3` (sugerencias de la
  suite). Ninguno abrió corrección ni reabre el candidato.
- Segundo linaje `review-2928e782f234a092`, abierto sobre el candidato que ya
  incluye el arreglo de R1 con las lentes `risk` y `reliability`: `risk` quedó
  admitida (4 808 bytes) y `reliability` no fue capturable —dos payloads
  truncados (76 KB y 151 KB) y un flujo cortado a los 383 s, siempre rechazados
  por el admitidor sin consumir el slot—. El dueño decidió no insistir y
  abandonar ese linaje (`operator_disposition`, actor `pi`); la autoridad quedó
  reclamada y en cuarentena, el candidato sin aprobación de seguimiento y el
  arreglo de R1 respaldado por la suite de recuperación (3/3) y por la evidencia
  (`authToken: null` tras el logout).
- Entrega: política ordinaria del repositorio; la revisión no autoriza push.

## Veredicto

El instrumento cumple el criterio de salida de #364: **cada entrada del sidebar
es visible sólo para los roles que declara `AppLayout.navigation`** (7 de 7
coincidencias exactas), **cero `pageerror`**, **cero solicitudes ≥ 400 fuera de
la allowlist** declarada (la de F1, que sigue abierta y contada), **el sheet
cumple su docking derecho** y **el confirm cumple centrado, Escape y foco**, y
la rúbrica de shell está puntuada.

La sesión **no corrige producto** (S6): deja siete hallazgos medidos y con causa
anclada, dos de ellos en una primitiva compartida (`Sheet.vue`) y uno heredado
del arnés de U0 (F1).

## Seguimientos

- Hallazgos publicados y enlazados en #364: #378 (canales por rol, F1), #379
  (sheet sin foco ni nombre, F2+F3), #380 (menú de usuario, F4), #381 (404 con
  dos `h1`, F5) y #382 (`/reset-password`, F6+F7).
- Las correcciones no son de este programa: cada issue trae su alcance y sus
  criterios de aceptación.
- Los criterios de la rúbrica en `1` en dos o más superficies (aquí sólo el `5`:
  login y reset comparten la ausencia de skeleton) son la entrada de U11.
- `.atl/qa-evidence/audit/ui/U1/` no se commitea (S7); esta nota es lo que
  sobrevive.
