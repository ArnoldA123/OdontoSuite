# Spec: Login Overlays — Live Data

Change: `ui-login-refinement-dental-split-2026-09`

## L1 — Stats fetch (Card 1)

- On mount, Card 1 MUST call `GET /api/dashboard/stats`.
- On 200 with `data.total_patients > 0`, the card MUST render the number with thousands separator and the label "Pacientes activos".
- On 200 with `data.total_patients === 0` or `data === null`, the card MUST render the empty state: "Sin pacientes aún" + subdued copy.
- On non-2xx or network failure, the card MUST catch the error silently and render the empty state.

## L2 — Appointments today fetch (Card 2)

- On mount, Card 2 MUST call `GET /api/dashboard/appointments-today?per_page=3`.
- On 200 with a non-empty array, the card MUST render up to 3 rows: `patient.name` (truncated at 20 chars) + `scheduled_at` formatted as `HH:mm`.
- On 200 with an empty array, the card MUST render: "Sin citas para hoy" + subdued copy.
- On non-2xx or network failure, the card MUST catch the error silently and render the empty state.

## L3 — Users active fetch (Card 3)

- On mount, Card 3 MUST call `GET /api/users/active?per_page=3`.
- On 200 with a non-empty array, the card MUST render up to 3 circular avatars (24×24, systemBlue-500 bg, white initials from `user.name`).
- On 200 with an empty array, the card MUST render: "Sin equipo activo" + subdued copy.
- On non-2xx or network failure, the card MUST catch the error silently and render the empty state.

## L4 — Empty state

- Every empty state MUST be visually consistent: 12px icon (systemGray-500), 13px label (`text-secondaryLabel`), centered horizontally.
- Empty states MUST NOT throw, MUST NOT block the rest of the login, and MUST NOT log to console.

## L5 — Error swallowing

- All three fetches MUST wrap their `useApi` call in `try/catch`.
- Catch blocks MUST do nothing (no `console.error`, no toast) — overlays are decorative; failure is silent.
- Catches MUST NOT propagate the error to the parent.

## Acceptance criteria

- AC1: With seeded data (`elizabet` login), Card 1 shows a non-zero number, Card 2 shows 0-3 appointment rows, Card 3 shows 0-3 avatar circles.
- AC2: With no data, all 3 cards show their empty state without throwing.
- AC3: With `/api/dashboard/stats` returning 500, Card 1 still renders the empty state.
- AC4: Network failure (offline) does not break the login layout.
