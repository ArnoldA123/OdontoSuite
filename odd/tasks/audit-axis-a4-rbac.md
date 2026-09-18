# Task: A4 — Real authorization matrix (issue #26)

Status: implemented, pending owner review. Branch: main (sin commits por regla de seguridad: explícito del dueño requerido; ver D3).

Axis A4 of plan #12 (`docs/mejoras/12-programa-auditoria-integral-2026-08.md` §A4).
Issue: #26 (type:eje, crit:media, area:backend). Related: plan #12 §2 (A4), #21
(AGENTS §5 auditada contra código, no creída). Policy comment from owner on #26
(2026-09-18): A2 assigns 1 failure of the fresh `main` run (`b0e8a0b`, 37
failures, swept with `node scripts/audit/cluster-failures.mjs`, issue #44):
`AuthTest > login blocked after five failed attempts` (`AuthTest.php:73-74`).
Measured cause: `ThrottleLoginAttempts.php:52-70` returns the 3/min 429 before
the 5-failure block (:73-90) can set `blockedKey`; the message and
`meta.remaining_minutes` the test demands are unreachable in a 1-minute loop.
Policy decision (middleware or test): this axis decides.

## Work units

| # | Deliverable | State | Evidence |
| --- | --- | --- | --- |
| WU-1 | `scripts/audit/probe-rbac.mjs`: static matrix code-expectation vs three sources + `--fail-on-divergence` | done | gate red exit 1 with 26 open rows; all-accepted exit 0; restored exit 1 |
| WU-2 | Evidence in `.atl/qa-evidence/audit/rbac/` (matrix + divergences, tied to revision) | done | `matrix.md`, `divergences.md`, `result.json` (revision resolved via `git rev-parse` at runtime) |
| WU-3 | Policy decision throttle-vs-lockout + minimal fix (middleware or test) with green run | done | test-only fix in `AuthTest.php`; 4/4 green on MySQL (`odontosuite_test` @3306); red-proofed without fix (1 failed); `AUTH-*` row cleared from probe |
| WU-4 | Divergence triage: one follow-up issue per root class or written acceptance | done (filed #55–#59) | F1 caja/recepcionista → #55; F2 recursos sin `role:` → #56; F3 páginas admin sobre API abierta → #57; F4 `cash.session` DELETE bypass → #58; F5 filas §5 → #59 |

## Method (issue #26, commands not opinions)

```
node scripts/audit/probe-rbac.mjs --roles=administrador,recepcionista,odontologo,finanzas
node scripts/audit/probe-rbac.mjs --fail-on-divergence
```

Expectation derived from code (`role:` middleware per route in `routes/api.php`),
authenticated demo users per role from `CREDENTIALS.md`, real state observed per
endpoint. Includes the `cash.session` cycle and multi-sede filters. Live
per-endpoint probing needs a seeded server + 7 tokens; this axis ships the
static cross of the three sources nobody crosses (middleware, AGENTS §5,
frontend `AppLayout.navigation` without `meta.roles`) as the always-runnable
gate, with the live cycle recorded as explicit follow-ups where static evidence
is insufficient.

## Measured facts (2026-09-18, `bb86333`, clean tree)

- `ls scripts/audit/probe-rbac.mjs` → missing. The exit criterion names a
  command that does not exist: the gate cannot pass or fail, it is absent.
- `php artisan route:list --json` → 203 routes total, 190 under `api/` (matches
  #21 evidence at `3f5cc58`, unchanged).
- Router has zero `meta.roles`: `grep -rn "meta.*roles" resources/js/app.js
  resources/js/router/` → no hits. Every SPA route uses `beforeEnter:
  requireAuth` only; visibility control is `AppLayout.navigation` roles.
- `cash.session` (`RequireActiveCashSession`) enforces POST/PUT/PATCH only and
  passes everything else through (`handle`, early return for other methods), so
  DELETE on `transactions`/`cash-movements` bypasses the active-session check.
- `reminder-templates` apiResource sits inside the 6-role appointments group
  with an extra `->middleware('role:administrador')`: effective admin-only, by
  middleware stacking, not by a single readable rule.
- CREDENTIALS.md permission matrix promises recepcionista cash access
  (Abrir/cerrar caja ✅, Registrar transacciones ✅) and quotations visibility,
  but `routes/api.php` scopes all cash routes to `administrador,finanzas` and
  quotations to `administrador,finanzas,odontologo,implantologo`: recepcionista
  gets 403 on what the doc promises.
- `patients` resource has no `role:` middleware (all authenticated roles),
  but CREDENTIALS.md marks several roles ❌ for create/edit: hidden-by-doc
  endpoints are reachable.
- EnvironmentsPage consumes `/api/dental-chairs` while nav scopes
  `/environments` to admin-only and the API allows 6 clinical roles on
  `dental-chairs`: doc over-restricts what the API permits.

## Decisions

- **D1 — static gate first, live probe as follow-up.** A live
  role × endpoint sweep needs a seeded server, 7 tokens, a cash-session cycle
  and multi-sede fixtures; it does not fit one axis session reproducibly. The
  shipped gate crosses the three checked-in sources statically and fails with
  the diff. Endpoints needing a live observation become follow-up issues, not
  prose.
- **D2 — throttle-vs-lockout policy (owner-delegated).** The 3/min burst limit
  shadows the 5-failure/10-min lockout by construction: the 4th rapid request
  returns before `$next` runs, so `failedCount` stalls at 3 and `blockedKey` is
  unreachable in a 1-minute loop. The test's 6-rapid-request loop asserts a
  path the middleware makes unreachable. Decision: keep the middleware (burst
  protection is the intended outer layer), fix the test to isolate the lockout
  layer by emulating minute-boundary passage between bursts. No production
  behavior change.
- **D3 — no commits from this session.** Safety rule (explicit user request
  required) beats the harness work-unit-commit default. Changes stay in the
  working tree for the owner to review, commit and push.

## Verification

- `node scripts/audit/probe-rbac.mjs --fail-on-divergence` → exit 1 with the
  divergence table while divergences exist; exit 0 only when every divergence
  is a follow-up issue or a written acceptance. Falsifiability: temporarily
  widen one API group to `all`, show exit 0 changes / diff shrinks, restore.
- `php artisan test --filter=AuthTest` → green after WU-3 (both the 3/min and
  the isolated lockout tests).
- Each divergence row ends as a follow-up issue number or a written acceptance
  in the evidence dir, never as prose in this file.
