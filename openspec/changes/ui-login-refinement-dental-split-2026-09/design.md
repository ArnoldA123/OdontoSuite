# Design: Login Refinement — Dental Editorial Split

Change: `ui-login-refinement-dental-split-2026-09`
Date: 2026-09-08
RDD: enabled (global)

---

## Architecture decisions

### D1 — `useShapeMorph` is a thin orchestrator, not a renderer

The composable manages a `ref('idle')` state, an `idleTimer` for the 200ms dwell, and a `transition(from, to)` guard. The actual rendering (which `<span>` is visible, which `v-motion` enter/leave fires) is owned by the template. This keeps the composable testable as pure logic and keeps the template declarative.

### D2 — `shapeMorphMath.js` is pure

Three exported functions:

- `validateTransition(from, to)` — returns `true` only if the transition is allowed by the state machine (M1 + M4). Pure: no Vue, no DOM.
- `clampIntensity(value, min = 0, max = 1)` — clamps an intensity value (used for the dwell-timer remaining). Pure.
- `dwellRemaining(startedAt, min = 200)` — returns the milliseconds remaining before the state machine allows the next transition. Pure.

### D3 — Submit button stays a `<UiButton>`

The submit is a single `<UiButton>` with 5 `<span>` children. The `v-show` on each `<span>` is bound to `state === 'idle'` etc. The polymorphic content is a slot of the button, not a replacement. Magnetic hover reads `state === 'idle' || state === 'error'` and attaches the listeners accordingly.

### D4 — Form Card polymorphism uses `v-motion` group

`<TransitionGroup name="form-card-morph">` wraps the `<form>` and the `<MiniSummary>`. The form has `:key="'form'"`; the summary has `:key="'summary'"`. `v-motion` initial/enter/leave on the summary provides the scale-in. Reduced-motion collapses the `scale` to a flat `opacity` crossfade.

### D5 — Floating overlays use absolute positioning + grid

The right column is a `position: relative` container. The 3 overlay cards are `position: absolute` with explicit `top` / `left` / `right` / `bottom` percentages (e.g. Card 1: `top: 24px; right: 24px;`, Card 2: `top: 50%; left: 24px; transform: translateY(-50%);`, Card 3: `bottom: 24px; right: 24px;`). On mobile the positioning collapses to a horizontal row below the hero.

### D6 — Image fallback is a static SVG

If the `<img>` fails to load (`@error` event), the wrapper swaps to a static SVG of a tooth glyph in systemBlue-500 over a soft gradient. Same aspect ratio, same padding. The image is the only thing that changes.

### D7 — Fetches are fire-and-forget, not awaited

The 3 overlay fetches run on mount with `void fetchOverlay(...)`. The `async` functions return early on error and never reject. The login form's submit flow is independent of the overlay fetch state.

### D8 — RDD 400-line budget is exceeded by user-approved exception

The user explicitly chose "un solo PR grande (~480 líneas)" despite the 400-line guideline. This is recorded as deviation D1 in `apply-progress.md`. Forecast:
- LoginPage.vue: +350 lines
- useShapeMorph.js: +80
- shapeMorphMath.js: +50
Total composition: ~480 lines.

Test files do not count against the budget (per project convention; PHPUnit docblocks are heavy and per-test inspection is the actual review surface).

### D9 — Reduced-motion collapse is per-element, not a global gate

Each `v-motion` directive in the login checks the `useReducedMotion` composable individually. A single global gate would be cheaper to write but harder to verify per-spec. Per-element collapses also let us keep the page-level entrance spring even when individual motion paths are gated.

## Composable API (final)

```js
// useShapeMorph.js
export function useShapeMorph({ initial = 'idle', dwellMs = 200, states = ['idle','validating','authenticating','success','error'] } = {}) {
  const state = ref(initial)
  const dwellStart = ref(0)
  let timer = null

  function transition(to) {
    if (!validateTransition(state.value, to, dwellStart.value, dwellMs)) return false
    if (state.value === 'idle' && to === 'validating') {
      dwellStart.value = Date.now()
      timer = setTimeout(() => { /* dwell elapsed; allow next transition */ }, dwellMs)
    }
    state.value = to
    return true
  }

  function cancel() {
    if (state.value === 'success' || state.value === 'error') return
    if (timer) { clearTimeout(timer); timer = null }
    state.value = 'idle'
  }

  function isTerminal(s = state.value) {
    return s === 'success' || s === 'error'
  }

  onUnmounted(() => { if (timer) clearTimeout(timer) })

  return { state, transition, cancel, isTerminal }
}
```

```js
// shapeMorphMath.js
export const STATES = ['idle', 'validating', 'authenticating', 'success', 'error']

export function validateTransition(from, to, dwellStart, dwellMs = 200) {
  if (!STATES.includes(from) || !STATES.includes(to)) return false
  if (from === to) return false
  if (from === 'success' || from === 'error') return false
  if (from === 'validating' && to === 'authenticating') {
    return Date.now() - dwellStart >= dwellMs
  }
  return true
}

export function clampIntensity(value, min = 0, max = 1) {
  return Math.max(min, Math.min(max, value))
}

export function dwellRemaining(startedAt, dwellMs = 200) {
  return Math.max(0, dwellMs - (Date.now() - startedAt))
}
```

## Button template (final)

```vue
<UiButton
  ref="submitRef"
  type="submit"
  variant="primary"
  size="lg"
  :loading="state === 'authenticating'"
  :disabled="state === 'success'"
  :full-width="true"
  data-magnetic="true"
  data-state="shape-morph"
  class="login-submit-shape"
>
  <span v-show="state === 'idle'" v-motion="{ initial: { opacity: 0, y: -4 }, enter: { opacity: 1, y: 0, transition: { duration: 200 } } }">
    Iniciar sesión
  </span>
  <span v-show="state === 'validating'" v-motion="...">
    <span class="login-submit-dot" />
    Validando
  </span>
  <span v-show="state === 'authenticating'" v-motion="...">
    <span class="login-submit-dot" />
    Autenticando
  </span>
  <span v-show="state === 'success'" v-motion="...">
    <svg class="login-submit-check" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M5 12 L10 17 L19 7" />
    </svg>
    Listo
  </span>
  <span v-show="state === 'error'" v-motion="...">
    Reintentar
  </span>
</UiButton>
```

The `v-motion` directives on each `<span>` are wrapped in a helper:

```vue
<script setup>
const motion = (variant) => ({
  initial: reducedMotion.value ? { opacity: 0 } : { opacity: 0, y: -4 },
  enter: reducedMotion.value ? { opacity: 1, transition: { duration: 150 } } : { opacity: 1, y: 0, transition: { duration: 200 } },
})
</script>
```

## File-by-file change table

| File | Action | Lines |
|---|---|---|
| `resources/js/composables/useShapeMorph.js` | new | ~80 |
| `resources/js/composables/shapeMorphMath.js` | new | ~50 |
| `resources/js/modules/auth/LoginPage.vue` | modify | +350 net |
| `tests/Unit/Composables/ShapeMorphTest.php` | new | ~200 |
| `tests/Unit/DesignSystem/LoginPageRenderTest.php` | extend | +60 (6 cases) |
| `openspec/changes/.../{explore,proposal,design,tasks,spec}.md` | new | n/a (artifacts) |

## Rollback boundary

`git revert <sha>` of the apply commit restores:
- LoginPage.vue to its pre-PR state (single column, no overlays, single-state button).
- Removes the 2 new composable files.
- Removes the new ShapeMorphTest file.
- Reverts the LoginPageRenderTest extension.

The 3 overlay API endpoints already exist; reverting leaves them untouched.

## Verification

- Per-task RED → GREEN → TRIANGULATE → REFACTOR.
- `php artisan test` (focused): `ShapeMorphTest`, `LoginPageRenderTest` (extended).
- `pnpm build` succeeds.
- `git diff main -- package.json pnpm-lock.yaml` returns empty (B1 gate).
- `git ls-files public/images/pexels/auth/login/6812463_modern-dental_p2.jpg` returns 1 line.
- Playwright manual sweep at 1440x900 + 390x844 confirms the visual composition.

## Out of scope (intentional)

- Brand-glyph morph on success (kept as the existing opacity crossfade from PR2 of `ui-login-premium-motion-2026-08`).
- Sound on submit.
- Touch / haptic feedback.
- New tokens.
- New ui/ primitives.
- OAuth.
- Dark mode.
