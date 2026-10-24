# Private dashboard channel (#183)

Feature: stop publishing the widest event fan-out on a public Reverb channel.
Branch: `security/private-dashboard-channel` · Issue: #183 · Risk: item 2 (authorization boundary) + item 3 (channel names subscribers use).

## Specs

S1. "El canal deja de ser público y su autorización rechaza roles sin acceso."

S2. "Dashboard y caja siguen recibiendo actualizaciones en vivo."

S3. "`sdd:check-events` sigue en 0 huérfanos."

S4. Issue context (verbatim): "Un canal público no verifica identidad al suscribirse: cualquiera que conozca el nombre recibe los payloads de dominio."

## Contract

- `dashboard-updates` is a `PrivateChannel` in all 21 publishers; the wire name is `private-dashboard-updates`.
- Authorization lives in `App\Http\Controllers\Api\BroadcastingAuthController` (the effective authority; the project does not call `Broadcast::routes()`), mirrored as documentation in `routes/channels.php`.
- Allowlist: the seven roles with dashboard access (`administrador`, `recepcionista`, `odontologo`, `implantologo`, `tecnico_dental`, `asistente`, `finanzas`). An unknown role and an anonymous request are rejected.
- The dashboard consumer subscribes with `privateChannel` and leaves `private-dashboard-updates`; `patients` uses the same wire-name rule.
- Cash keeps its own channels (`cash-register`, `cash-session.*`), untouched.

## Tasks

| ID  | Spec  | Route  | Commit  |
| --- | ----- | ------ | ------- |
| T1  | S1-S4 | inline | fae9a8b |
| T2  | S1    | inline | fae9a8b |
| T3  | S2    | inline | fae9a8b |
| T4  | S1-S3 | inline | fae9a8b |

T1 — 21 events: `new Channel('dashboard-updates')` → `new PrivateChannel('dashboard-updates')`.
T2 — Controller branch + `routes/channels.php` mirror.
T3 — `DashboardPage.vue` private subscription and wire-name leave.
T4 — Guardas: unit test over the 21 publishers (RED first), auth cases (all roles allowed, unknown role 403, anonymous 401), JS unit test for the private subscription and the leave.

## Log

L1. User request (verbatim): "quiero hacer issues #183 #184 #185 #186 #206, en un worktree nuevo".
L2. Grouping decision (user): five branches, five PRs, one issue per PR.
L3. Role policy: the dashboard module is open to every authenticated role (AGENTS §5), so the allowlist is the seven known roles; the guard rejects anonymous subscribers (the actual defect) and any role not explicitly listed.
L4. Out of scope, reported: the same 21 events still publish to public domain channels (`appointments`, `transactions`, `medical-records`, `quotations`, `cash-register`, …). #183 closes only `dashboard-updates`.
L5. RED guard (unit): with `app/Events` reverted, the guard reported 22 failed / 1 passed; with the change, 23/23 green. RED guard (JS): with `DashboardPage.vue` reverted, the private-subscription test failed; with the change it passes.
L6. `php artisan sdd:check-events` → 33 event classes, 0 orphan.

L7. Los canales públicos de dominio restantes quedaron mapeados en la issue #249
(inventario medido y consumidores). Esta entrega cierra solo `dashboard-updates`.
