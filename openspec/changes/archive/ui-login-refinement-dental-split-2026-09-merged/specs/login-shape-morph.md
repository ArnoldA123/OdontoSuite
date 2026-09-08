# Spec: Login Shape Morph

Change: `ui-login-refinement-dental-split-2026-09`

## M1 — State machine

The `useShapeMorph` composable MUST expose a 5-state machine: `idle | validating | authenticating | success | error`.
- `idle` is the initial state.
- `validating → authenticating` MUST only be allowed after the `validating` state has held for at least 200ms.
- `authenticating → success` MUST only be allowed on an API 2xx response.
- `authenticating → error` MUST only be allowed on API 4xx/5xx or network failure.
- Any state MUST be cancellable back to `idle` while not in `success` or `error` (terminal states).

## M2 — Submit button polymorphism

- The submit MUST render one `<UiButton>` element with 5 inner `<span>` branches, one per state, controlled by `v-show` (not `v-if`).
- State crossfade MUST be implemented via `v-motion` initial/leave (opacity 0 + y -4 → opacity 1 + y 0; duration 200ms; easing ease-ios).
- Stage labels:
  - `idle`: "Iniciar sesión"
  - `validating`: "Validando"
  - `authenticating`: "Autenticando"
  - `success`: "Listo" + check SVG (24×24, stroke-dashoffset animation 220ms)
  - `error`: "Reintentar"
- The button MUST maintain a `min-width: 220px` to prevent width oscillation across states.
- The existing magnetic-hover effect MUST remain active in `idle` and MUST be disabled in `validating`, `authenticating`, `success`, and `error`.

## M3 — Form Card polymorphism

- On `success`, the existing `<form>` MUST crossfade (opacity 0 + scale 0.98 → opacity 1 + scale 1; duration 250ms) to a `<MiniSummary>` block.
- `<MiniSummary>` MUST render: avatar circle (32×32, systemBlue-500 bg, initials from `user.name` in white), display name (`text-base`, `text-label`), role label (`text-sm`, `text-secondaryLabel`), and a secondary "Ir al dashboard" button (`<UiButton variant="secondary" size="md">`).
- `router.push('/dashboard')` MUST fire 600ms after the `success` state is entered (dwell time).

## M4 — Terminal guard

- `success` and `error` are terminal states. Once entered, no transition out is allowed.
- The MiniSummary "Ir al dashboard" button is the only path out of `success`.
- The existing failure-shake animation MUST fire on `error` (220ms, transform only; reduced-motion collapses to opacity flash).

## M5 — Dwell

- `validating` MUST hold for exactly 200ms (timer-based; not just on click).
- The 200ms timer MUST start on `idle → validating` and MUST be cleared on cancel.

## M6 — Reduced-motion collapse

- Under `prefers-reduced-motion: reduce`, every `v-motion` enter/leave MUST collapse to opacity-only (no `y`, no `scale`, no transform).
- The check SVG stroke-dashoffset animation MUST collapse to an instant `opacity: 0 → 1` crossfade.

## Acceptance criteria

- AC1: Submit cycles idle → validating (200ms dwell) → authenticating → success (with check) → router.push to /dashboard.
- AC2: Submit failure path: idle → validating (200ms) → authenticating → error (shake) → idle (after 220ms).
- AC3: MiniSummary crossfades in on success and persists for 600ms before route push.
- AC4: Magnetic hover works only in `idle` and `error` states.
- AC5: `prefers-reduced-motion: reduce` collapses every state transition to opacity-only.
