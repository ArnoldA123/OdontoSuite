# Task: audit 2026-10 — shell, recovery and patient titles (#378/#384/#380/#381/#382/#405)

Branch `fix/audit-u1-shell-405`, worktree `.worktrees/audit-u1-shell`, based on
`ff382b5d` (main). Six defects measured by the UI audit (plan #13). Work units in
Conventional Commits, one per issue; native review, push and merge are the
owner's decision.

## Specs

### S1 — #378 + #384: the shell subscribes only to channels the session can authorize

#378, verbatim:

> - Gatear la suscripción por el permiso que la API ya exige para leer cada escritorio (una sola fuente: `App\Authorization\Permissions`), o por un mapa explícito canal → permiso servido al cliente en `POST /api/auth/login` y `GET /api/auth/me`.
> - Dejar la prueba que fija la conducta: un rol no se suscribe a un canal que no puede autorizar, y los canales que sí le corresponden siguen activos.

Criterios de aceptación, verbatim:

> - Recorrer las superficies autenticadas con cada uno de los siete roles no produce ningún `403` en `POST /api/broadcasting/auth`.
> - Cada rol sigue recibiendo en vivo lo que hoy recibe en los canales que sí autoriza (citas, pagos, presupuestos, catálogo, laboratorio).
> - Un test falla si el shell vuelve a pedir autorización de un canal fuera del permiso del rol.

#384 is the same finding (U0 F1). Its correction scope is verbatim:

> Gatear las suscripciones por los permisos efectivos de la sesión (o publicar la lista de canales autorizados desde la API). No se toca la matriz de canales del controlador ni los eventos publicados.

Decision: the channel matrix stops being a second role table. `BroadcastingAuthController`
resolves every channel to the permission that already guards its desk
(`App\Authorization\Permissions`) and the SPA gates each subscription with the
same permission name. One PHP guard crosses both sides, so drift fails the build.

### S2 — #380: no no-op entries in the user menu

Verbatim:

> - Ninguna entrada del menú de usuario es un no-op: o navega a su superficie, o abre su diálogo, o no existe.
> - «Apariencia» no aparece sin control debajo.
> - Un test falla si vuelve a haber un botón visible sin efecto.

Decision: the shell has no profile surface, no settings surface reachable from
the menu and no appearance control (there is no theme composable), so the two
buttons and the empty section are removed. The menu keeps the identity block and
"Cerrar Sesión".

### S3 — #381: the 404 inside the shell paints one h1

Verbatim:

> - `/cualquier-ruta-inexistente` con cualquier rol muestra un solo `h1` visible y sigue ofreciendo «Volver» e «Ir al inicio».
> - Una prueba falla si el 404 vuelve a producir dos `h1`.

Decision: `NotFoundPage` renders the document's heading, so the topbar drops its
own heading on the `not-found` route (the same rule the detail pages follow).

### S4 — #382: the reset surface explains both of its entries

Verbatim:

> - `/reset-password` sin `token` ni `email` dice qué falta y ofrece el camino para solicitarlo.
> - Con el enlace del correo, el formulario no pide un código que ya tiene y el restablecimiento sigue funcionando igual.
> - Una prueba falla si la superficie vuelve a ofrecer el campo vacío con el token en la URL, o si la entrada sin token vuelve a ser el login sin explicación.

Decision: without a token the login card carries a notice that names what is
missing and a button that opens the "forgot password" dialog. With a token in the
URL the recovery-code field is not rendered; the token travels in the payload as
it already did.

### S5 — #405: the patient surfaces own their heading

Verbatim:

> - `/patients` tiene un solo título visible: o el topbar lo cede como en las 21 superficies rediseñadas, o el hero deja de repetirlo.
> - `/patients/:id` expone el nombre del paciente como `<h1>` de la página y el topbar no anuncia «Pacientes» como título visible.
> - `render/patients*-administrador.json` deja `snapshot.h1.count === 1` con el texto de la vista, y los guards `topbar-title-ownership` y `page-header-hierarchy` siguen verdes con las rutas nuevas en su lista.

Decision: both patient surfaces render their title as `<h1>` and the topbar
renders no heading at all there, so the document keeps exactly one `h1` and it is
the visible one.

### S6 — cross-cutting

> Every existing behavior these changes touch keeps working: the WebSocket fan-out
> still delivers every event to the roles that authorize it, the user menu still
> closes with Escape, the login and forgot-password flow still work, and the audit
> rows keep measuring the contract that now holds.

## Tasks

| ID | Spec | Route | Commit |
| --- | --- | --- | --- |
| T1 | S1 | `BroadcastChannels`, `BroadcastingAuthController`, `broadcastChannels.js`, `useWebSocketNotifications.js`, `usePermissions.js`, guards | pending |
| T2 | S2 | `AppLayout.vue` user menu + contract test + audit row | pending |
| T3 | S3 | `AppLayout.vue` heading ownership + 404 test + audit row | pending |
| T4 | S4 | `LoginPage.vue`, `ResetPasswordModal.vue`, tests + audit row | pending |
| T5 | S5 | `AppLayout.vue`, `PatientsPage.vue`, `PatientDetailPage.vue`, guards + audit row | pending |
| T6 | S1-S6 | AGENTS.md changelog, full verification | pending |

## Log

**L1** — User request, verbatim:

> haz las issues #378 #384 #380 #381 #382 #405 en un worktree nuuevo

**L2** — Measured base: `git worktree add -b fix/audit-u1-shell-405 .worktrees/audit-u1-shell main`
at `ff382b5d`. The worktree uses a real `vendor/` (`composer install`; a symlink
would resolve the Composer autoloader against the main checkout) and a
`node_modules` symlink (Vite/Vitest resolve sources from the config root).

**L3** — T1 measured. `BroadcastingAuthController` carries no role lists any more:
`App\Authorization\BroadcastChannels` resolves every channel (static, dynamic and
the two per-user names) to a catalog permission or `self`, and the handshake
authorizes through `Permissions::roleHas()`. The SPA gates each fan-out
subscription with the same permission name; `DashboardPage` additionally stops
asking for the appointments channel when the role cannot read the agenda
(finance was collecting that 403 on its landing page). Focused evidence:
`tests/js/unit/broadcast-channels-permission.test.js` (9) +
`domain-channels-private.test.js` (7) + `dashboard-private-channel.test.js` (3),
unit guard `BroadcastChannelAuthorizationTest` (7 assertions over the whole
role matrix), and the MySQL suite for the channel family: **113 tests, 617
assertions, OK** — the handshake authorizes exactly the roles it authorized
before.

**L4** — T4 measured. `/reset-password` without `token`/`email` renders a notice
that names the missing code and opens the forgot-password dialog; with a link
token the recovery-code field is not rendered and the payload still sends the
token (`data-reset-token-applied` marks the state). `login-reset-flow` 7/7.

**L5** — Delivery slices (local commits, not pushed): cut 1 = the ODD note, T1
(the channel map plus both guards) and T4 (the reset surface); cut 2 = T2/T3/T5
(the shell menu, the 404 heading and the patient headings) and T6 (documentation
and full verification). Each issue keeps its own commit so a chained PR can take
them one at a time.

**L6** — T2/T3/T5 delivered by the delegated writer and reviewed hunk by hunk,
then split into two commits (menu vs heading ownership; the heading computed is
one unit that serves both #381 and #405). Two source-inspection guards pinned
the retired contract and were re-anchored with the issue as justification:
`PatientsListAppShellTest` (the hero title is now the page `h1`) and
`ClinicalEventsChannelBoundaryTest` (`dashboard-updates` is pinned through the
catalog instead of the controller's old role list).

**L7** — Browser evidence on a dedicated base (`odontosuite_u1shell_audit`,
`migrate:fresh --seed`): app on `127.0.0.1:8000`, Reverb on `:8081`, assets built
with `.env` so the client app key matches the server's. `@U1 shell · (sidebar
matrix|catch-all|user menu)` **15/15**: `render/shell-*.json` leaves **0 expected
/ 0 unexpected** failed requests for the seven roles, the catch-all row records
`h1: {count: 1, texts: ["Página no encontrada"]}` and the menu records exactly
one item. A standalone probe counted the handshakes on `/dashboard`:
administrador 12, recepcionista 9, odontólogo 9, implantólogo 9, técnico 6,
asistente 6, finanzas 7 — **all 200, zero 403**. The #384 command
(`AUDIT_SCOPE=route:/dashboard`) **24/24** and `render/dashboard-*.json` declares
no denied channel.

**L8** — `HotfixLoginEmDashAuditTest` treats any U+2014 after `<template>` as a
visible character, style comments included: one em dash in the new notice's CSS
comment turned the full MySQL suite red. Replaced with a colon;
`tests/Feature/Ui/HotfixLoginEmDashAuditTest` 2/2.
