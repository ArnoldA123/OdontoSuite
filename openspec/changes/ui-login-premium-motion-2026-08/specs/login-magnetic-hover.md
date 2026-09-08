# Spec: login-magnetic-hover

## Summary
The login submit button tilts toward the cursor when the cursor enters its bounds, and springs back to center on cursor-leave. Implemented via the new `useMagneticHover` composable wrapping `useSpring2D`. Bypassed under `prefers-reduced-motion: reduce` and on `(pointer: coarse)` devices.

## Source-of-truth references
- 21st.dev React magnetic cursor effects (reference)
- motion.dev "Magnetic Cursors in Motion Cursor" (reference)
- `resources/js/composables/useSpring.js` — single-axis spring primitive
- `resources/js/composables/useSpring2D.js` — 2-axis wrapper (currently zero consumers; this spec is the first honest consumer)
- `resources/js/composables/useSpringMath.js` — pure math kernels

## Scenarios

### M1 — Cursor enters, button tilts
**Given** the submit button is rendered with `data-magnetic` bound to `useMagneticHover`,
**When** the cursor enters the button's bounding box at position `(mx, my)`,
**Then** the button MUST apply `transform: translate3d(dx, dy, 0)` where `(dx, dy)` is the clamped offset toward `(mx, my)`,
**And** the transform MUST reach the target within 350ms (`response 0.35`, the existing token default).

### M2 — Offset is clamped to 40% of the button's smaller dimension
**Given** the button's width is `W` and height is `H`,
**And** `M = min(W, H)`,
**When** the cursor is at the button's center (offset vector = `(0, 0)`) and moves to a position that would yield an offset of `(M*0.6, M*0.4)`,
**Then** the applied offset MUST be clamped to `(M*0.4, M*0.4)` (40% cap on each axis independently).

### M3 — On cursor-leave, button springs back with one gentle bounce
**Given** the button has been tilted by the magnetic effect,
**When** the cursor leaves the button's bounding box (via `mouseleave`),
**Then** the target offset MUST reset to `(0, 0)` with `damping: 0.7` (one gentle bounce on release),
**And** the spring MUST settle to within 1px of `(0, 0)` within 600ms.

### M4 — Reduced-motion bypass
**Given** the user has `prefers-reduced-motion: reduce` enabled in the OS,
**When** the cursor enters the submit button bounds,
**Then** the magnetic effect MUST be inert (target stays at `(0, 0)`),
**And** the existing `translateY(-1px)` hover lift from `Button.vue` MUST continue to work as the only feedback.

### M5 — Touch / coarse-pointer bypass
**Given** the user's device matches `(pointer: coarse)`,
**When** the submit button is rendered,
**Then** the composable MUST return `{ x: { value: 0 }, y: { value: 0 } }` and the mouse-tracking rAF loop MUST NOT start.

### M6 — Settle window
**Given** the cursor has left the button,
**Then** the spring MUST settle (within epsilon) within 600ms under standard motion preferences,
**And** within 50ms under `prefers-reduced-motion: reduce`.

### M7 — Composability with the existing primary-button lift
**Given** the primary button's `:hover` rule in `Button.vue` applies `transform: translateY(-1px)`,
**When** the magnetic composable writes its `var(--spring-magnet-x)` and `var(--spring-magnet-y)` CSS vars,
**Then** the scoped CSS MUST compose the two transforms (`translateY(-1px) translate3d(var(--spring-magnet-x), var(--spring-magnet-y), 0)`),
**And** the magnetic effect MUST NOT cancel the hover lift.

### M8 — Keyboard-only fallback path is unchanged
**Given** a keyboard user tabs to the submit button (no mouse ever enters),
**Then** the magnetic composable MUST NOT have activated the rAF loop,
**And** pressing `Enter` or `Space` MUST submit the form exactly as it does today.

## API contract (composable)

```js
// resources/js/composables/useMagneticHover.js
/**
 * @param {import('vue').Ref<HTMLElement|null>} elementRef
 * @param {object} [options]
 * @param {number} [options.response=0.35]
 * @param {number} [options.damping=0.7]   underdamped for the gentle bounce
 * @param {number} [options.maxDistanceFactor=0.4]   40% of min(width, height)
 * @param {string} [options.cssVarX='--spring-magnet-x']
 * @param {string} [options.cssVarY='--spring-magnet-y']
 * @returns {{
 *   x: ReturnType<typeof useSpring>,
 *   y: ReturnType<typeof useSpring>,
 *   active: import('vue').Ref<boolean>
 * }}
 */
export function useMagneticHover(elementRef, options = {})
```

## Acceptance criteria
- AC1: `useMagneticHover` returns `{ x, y, active }` where `x` and `y` are `useSpring` instances bound to `--spring-magnet-x` / `--spring-magnet-y` on the provided element.
- AC2: A `tests/Unit/Composables/MagneticHoverTest.php` (pure-math) covers M2 (clamp) and M3 (damping 0.7 yields a single overshoot within 600ms).
- AC3: `tests/Unit/DesignSystem/LoginPageRenderTest.php` extends with `login_page_uses_magnetic_hover_composable` (source-grep the import + the `data-magnetic` attribute on the submit ref).
