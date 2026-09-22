# Task: realtime A8 — mapa, prueba viva y 2 fixes (issue #28)

Status: DONE, PR abierto. Pendiente: merge (cierra #28).

## Tareas

- [x] `realtime-map.mjs`: 33 eventos, 33 con dispatch, 29 consumidos, 4 huérfanos
- [x] `probe-realtime.mjs`: 3/3 live-verified (appointments, dashboard-updates,
      canal privado con auth real), limpieza verificada
- [x] Fix 1: `ready-to-bill` 404 (shadowing por resource) → grupo Billing movido
- [x] Fix 2: doble prefijo `private-private-*` → nombres base + auth de ambos
      canales en `BroadcastingAuthController` (única autoridad real) + espejo
      en `channels.php` + test de matriz por roles (7 passed)
- [x] Guard `AppointmentsRouteOrderTest` (orden + roles)
- [x] Verificar: matriz live (admin 200/200, unknown 404, odonto 403/200),
      suites, lint, build
- [ ] Merge (cierra #28)

## Clasificación final de eventos

- verificados-en-vivo: appointments, dashboard-updates (+ privado con auth).
- arreglados: AppointmentCheckedIn, PaymentReceived, ready-to-bill.
- huérfanos declarados: UserCreated/UserUpdated (canal `users` sin consumidor;
  inofensivos).

## Espejo Engram

Observación pendiente al mergear.
