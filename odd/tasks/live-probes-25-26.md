# Task: probes live A3/A4 + fix ready-to-bill (issues #25 y #26)

Status: DONE, PR abierto. Pendiente: merge. #26 se puede cerrar (0 divergencias);
#25 queda abierta con resto acotado (fixtures de query params).

## Tareas

- [x] `probe-api-live.mjs` (332 líneas): sweep GET sin parámetros × 4 roles,
      estado + claves top-level, clases ok/gated/5xx/skipped/unclassified
- [x] `probe-rbac-live.mjs` (379 líneas): expectativa derivada de `role:`,
      observado en vivo, divergencia = permitido→401/403 o denegado→2xx
- [x] Hallazgo real: `ready-to-bill` 404 para todos (shadowing por resource).
      Fix: mover grupo Billing antes del grupo Citas (puro, mismo middleware).
      Verificado en vivo 200 + show intacto + probe 57 ok
- [x] Guard `AppointmentsRouteOrderTest` (orden + roles del grupo Billing)
- [x] Verificar: probes, guard, lint, build
- [ ] Merge; cerrar #26; comentar #25 (resto: fixtures de query)

## Evidencia

- API: 101 rutas, 57 ok, 0 server-error, 2 unclassified (422/400 correctos por
  falta de query params — comportamiento, no defecto).
- RBAC: 59 rutas, 236 celdas, 230 matches, **0 divergencias**.
- Guard: 2 passed. `pnpm lint:check` exit 0, `pnpm build` exit 0.

## Espejo Engram

Observación pendiente al mergear.
