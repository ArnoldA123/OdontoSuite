# Design: Login Premium Motion (`ui-login-premium-motion-2026-08`)

Change: `ui-login-premium-motion-2026-08`
Phase: design
Artifact store: hybrid (this file + Engram `sdd/ui-login-premium-motion-2026-08/design`)

This document CONCRETIZES the proposal + spec: exact composable APIs, exact timing values, exact path data, exact LoginPage wiring, and a complete file-changes scope. Two stacked PRs (PR1 foundation ~250 lines, PR2 composition ~320 lines), each independently reviewable and revertable.

---

## Architecture decisions

| ID | Decision | Choice | Rationale | Rejected |
|---|---|---|---|---|
| D17 | New dependency | Add `@vueuse/motion` v3.0.3 only. | User-confirmed stack motion. ~10kb gzipped. Native Vue 3 directive (`v-motion`) covers declarative enter/exit/hover variants. Does NOT replace `useSpring`/`useSpring2D` — those stay for imperative physics (magnetic cursor, deceleration). | GSAP + MorphSVG (~70kb); vanilla-only (the morph math by hand is fragile and the SMIL path attribute is fragile). |
| D18 | Magnetic composable wrapping | New `useMagneticHover(el, options)` wrapping `useSpring2D`. | The existing `useSpring2D` is declared but has ZERO real consumers — this slice is the first honest consumer. 40% offset + damping 0.7 (one gentle bounce). Magnetic composable owns mouse tracking, max-distance clamp, and reduced-motion/coarse-pointer bypass. | Re-implementing 2D spring physics inside the magnetic composable (duplicates `useSpring2D`). Wiring raw mouse tracking to `useSpring2D` from the LoginPage (leaks imperative detail into the screen). |
| D19 | Live-validation composable | New `useFieldValidation(form, rules, options)` composable. | Debouncing, success/error state, and rule evaluation are cross-cutting concerns that will be reused when the other ~10 forms roll out. User-confirmed timing (on blur + 250ms idle). | Inline `watch()` + `setTimeout` in LoginPage (duplicates work later; harder to test). |
| D20 | Glassmorphism implementation | Attach the existing `.decorative-glass` class from `resources/css/tokens.generated.css`. | The class already exists, has the right alpha + blur, and ships with `prefers-reduced-transparency: reduce` collapse built in. Zero new CSS. | New Card variant (`variant="glass-translucent"`) — proliferates variants for a single use site. Hand-rolled `backdrop-filter` — duplicates work and bypasses the reduced-transparency collapse. |
| D21 | SVG morph driver | SMIL `<animate attributeName="d" values="tooth;check" dur="0.6s" fill="freeze" />`. | Native browser support. No JS dependency. The existing brand-glyph SVG is inline in the LoginPage template — switching to a `<g>` with two `<path>` elements + one `<animate>` is the smallest change. Under reduced-motion, the `<animate>` is not rendered and an opacity cross-fade runs instead. | Web Animations API on the `d` attribute (poor browser coverage, requires runtime polyfill). GSAP MorphSVG (rejected in D17). |
| D22 | Multi-stage loading driver | Three static stage labels + `useSpring` for the active-state crossfade. No new dep. | The crossfade is a single-axis transform on the active-pill background. `useSpring({ response: 0.15, damping: 1.0 })` is sub-second. Layout shift is avoided by reserving the form's headline height via a skeleton placeholder. | `@vueuse/motion` variants for stage transitions (overkill for three pill labels; the composable adds no value here). |
| D23 | Reduced-motion contract | Every new motion path declares its OWN `@media (prefers-reduced-motion: reduce)` block, co-located with the motion declaration. No global block. | Spec R1 mandates per-path testability (source-grep ≥4 blocks). Local blocks also future-proof against the LoginPage being extracted as a template for other screens. | One global block at the bottom of LoginPage.vue's `<style scoped>` (hard to test; breaks if LoginPage is forked). |
| D24 | Token additions | NONE. The existing motion + elevation + duration tokens cover everything. Magnetic constants (`response 0.35`, `damping 0.7`, `maxDistanceFactor 0.4`) live INSIDE the `useMagneticHover` composable as defaults — they are not project-wide tokens. | Token proliferation is a smell. The existing `motion.response`, `motion.damping`, and `motion.duration` tokens cover all six new motion paths. | Adding `motion.magnet.response`, `motion.magnet.damping`, `motion.magnet.maxDistance` to tokens.js (overgeneralizes for a single consumer). |
| D25 | Test runner | PHPUnit only. No new JS test runner. The new composables' pure-math + state-machine logic is testable from PHPUnit via `shell_exec('node -e ...')` (the precedent: `tests/Unit/DesignSystem/UseSpringMathTest.php`). | AGENTS.md §2 declares PHPUnit as the only test runner. The existing `UseSpringMathTest.php` pattern works for `useMagneticHover` (clamp function) and `useFieldValidation` (debounce timing + state machine). | Vitest / Jest (new runner, new config, contradicts AGENTS.md). |
| D26 | Failure animation | Shake keyframe (`translateX: 0 → -6 → 6 → -4 → 4 → 0`) over 220ms. Reduced-motion: opacity flash (`1 → 0.6 → 1`) over 200ms. | Three oscillations read as "no" without being nauseating. Reduced-motion preserves feedback (the opacity flash) while removing movement. | 4+ oscillations (nauseating). Continuous shake (infinite loop = regression). No animation (no feedback). |

---

## Composable APIs (final)

### `useMagneticHover`

```js
// resources/js/composables/useMagneticHover.js
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useSpring2D } from './useSpring2D.js'

/**
 * Magnetic cursor effect: the bound element tilts toward the cursor when the
 * cursor enters its bounds, and springs back to center on cursor-leave. Falls
 * back to inert under prefers-reduced-motion and on coarse-pointer devices.
 *
 * The composable wraps `useSpring2D` (which wraps two `useSpring` instances).
 * The CSS vars `--spring-magnet-x` and `--spring-magnet-y` are written to the
 * element's style; the LoginPage's scoped CSS composes them with the existing
 * hover lift.
 *
 * @param {import('vue').Ref<HTMLElement|null>} elementRef
 * @param {object} [options]
 * @param {number} [options.response=0.35]
 * @param {number} [options.damping=0.7]
 * @param {number} [options.maxDistanceFactor=0.4]   // 40% of min(width, height)
 * @param {string} [options.cssVarX='--spring-magnet-x']
 * @param {string} [options.cssVarY='--spring-magnet-y']
 * @returns {{ x: ReturnType<typeof useSpring>, y: ReturnType<typeof useSpring>, active: import('vue').Ref<boolean> }}
 */
export function useMagneticHover(elementRef, options = {}) {
  const {
    response = 0.35,
    damping = 0.7,
    maxDistanceFactor = 0.4,
    cssVarX = '--spring-magnet-x',
    cssVarY = '--spring-magnet-y'
  } = options

  const active = ref(false)
  const { x, y } = useSpring2D({ response, damping, cssVarX, cssVarY })

  let rafId = null
  let pendingEvent = null
  let bounds = null

  // Pure clamp helper — exported for unit testing.
  const computeOffset = (mx, my) => {
    if (!bounds || bounds.width === 0 || bounds.height === 0) return { dx: 0, dy: 0 }
    const cx = bounds.left + bounds.width / 2
    const cy = bounds.top + bounds.height / 2
    const rawDx = mx - cx
    const rawDy = my - cy
    const M = Math.min(bounds.width, bounds.height) * maxDistanceFactor
    // Clamp magnitude to M, preserving direction.
    const mag = Math.hypot(rawDx, rawDy)
    if (mag === 0 || mag <= M) return { dx: rawDx, dy: rawDy }
    return { dx: (rawDx / mag) * M, dy: (rawDy / mag) * M }
  }

  const reducedMotion =
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const coarsePointer =
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(pointer: coarse)').matches

  const onEnter = e => {
    if (reducedMotion || coarsePointer) return
    active.value = true
    bounds = elementRef.value?.getBoundingClientRect()
    pendingEvent = e
    if (rafId == null) rafId = requestAnimationFrame(loop)
  }
  const onMove = e => {
    if (!active.value) return
    pendingEvent = e
    if (rafId == null) rafId = requestAnimationFrame(loop)
  }
  const onLeave = () => {
    active.value = false
    bounds = null
    pendingEvent = null
    x.set(0)
    y.set(0)
  }
  const loop = () => {
    rafId = null
    if (!pendingEvent || !bounds) return
    const { dx, dy } = computeOffset(pendingEvent.clientX, pendingEvent.clientY)
    x.set(dx)
    y.set(dy)
  }

  onMounted(() => {
    const el = elementRef.value
    if (!el) return
    el.addEventListener('mouseenter', onEnter)
    el.addEventListener('mousemove', onMove)
    el.addEventListener('mouseleave', onLeave)
    // Refresh bounds on resize/scroll so the magnetic remains accurate.
    window.addEventListener('resize', refreshBounds)
    window.addEventListener('scroll', refreshBounds, { passive: true })
  })

  const refreshBounds = () => {
    if (active.value) bounds = elementRef.value?.getBoundingClientRect()
  }

  onUnmounted(() => {
    const el = elementRef.value
    if (el) {
      el.removeEventListener('mouseenter', onEnter)
      el.removeEventListener('mousemove', onMove)
      el.removeEventListener('mouseleave', onLeave)
    }
    window.removeEventListener('resize', refreshBounds)
    window.removeEventListener('scroll', refreshBounds)
    if (rafId != null) cancelAnimationFrame(rafId)
  })

  return { x, y, active }
}

export default useMagneticHover
```

### `useFieldValidation`

```js
// resources/js/composables/useFieldValidation.js
import { reactive, watch } from 'vue'

/**
 * Live form validation. Triggers validation on a field when:
 *   - the field blurs, OR
 *   - 250ms of typing idle elapses (debounced).
 * Success state is set immediately when a field passes all rules (no debounce).
 *
 * The composable is pure state machine + timers; it does NOT render anything.
 * The LoginPage consumes `errors` and `successes` and renders them in the
 * existing <p class="field-error"> slot and a new <span class="field-success">
 * checkmark slot.
 *
 * @template T extends Record<string, any>
 * @param {T} form          reactive form state (the same ref/reactive used by v-model)
 * @param {Record<keyof T, Array<(value: any, form: T) => string|null>>} rules
 * @param {object} [options]
 * @param {number} [options.idleMs=250]
 * @returns {{
 *   errors: import('vue').Reactive<Record<string, string>>,
 *   successes: import('vue').Reactive<Record<string, boolean>>,
 *   validateField: (field: keyof T) => void,
 *   validateAll: () => boolean,
 *   clearField: (field: keyof T) => void
 * }}
 */
export function useFieldValidation(form, rules, options = {}) {
  const { idleMs = 250 } = options
  const errors = reactive({})
  const successes = reactive({})
  const timers = {}

  const runRules = field => {
    const value = form[field]
    const fieldRules = rules[field] || []
    for (const rule of fieldRules) {
      const err = rule(value, form)
      if (err) {
        errors[field] = err
        successes[field] = false
        return
      }
    }
    errors[field] = ''
    successes[field] = true
  }

  const validateField = field => {
    if (timers[field]) {
      clearTimeout(timers[field])
      delete timers[field]
    }
    runRules(field)
  }

  const clearField = field => {
    errors[field] = ''
    successes[field] = false
  }

  const validateAll = () => {
    let pass = true
    for (const field of Object.keys(rules)) {
      runRules(field)
      if (errors[field]) pass = false
    }
    return pass
  }

  // Debounced re-validation on input change.
  watch(
    () => Object.fromEntries(Object.keys(rules).map(f => [f, form[f]])),
    () => {
      for (const field of Object.keys(rules)) {
        if (timers[field]) clearTimeout(timers[field])
        timers[field] = setTimeout(() => {
          delete timers[field]
          runRules(field)
        }, idleMs)
      }
    },
    { deep: true }
  )

  return { errors, successes, validateField, validateAll, clearField }
}

export default useFieldValidation
```

---

## Button.vue additive change

`resources/js/components/ui/Button.vue` gains a single scoped CSS rule that consumes the magnet CSS vars when present. NO change to the template, the variants, the ripple logic, or the existing transitions.

```css
/* Additive: applies the magnetic effect ONLY when --spring-magnet-x/y are
   defined on the button root. Other buttons are untouched. The hover lift
   composes with the magnet via transform composition. */
button[data-magnetic='true'] {
  transform: translate3d(
    var(--spring-magnet-x, 0),
    calc(var(--spring-magnet-y, 0) - 1px),
    0
  );
}
button[data-magnetic='true']:not(:disabled):active {
  /* On press the magnet is inert (cursor is on the button, not hovering
     around it). The existing translateY(1px) takes over. */
  transform: translateY(1px);
}
@media (prefers-reduced-motion: reduce) {
  button[data-magnetic='true'] {
    transform: none;
  }
}
```

The LoginPage wires `<UiButton type="submit" data-magnetic="true" :ref="...">` and binds the magnetic composable to that ref.

---

## LoginPage.vue composition

The LoginPage template gets:

1. **A `data-magnetic="true"` attribute on the submit button** + a `ref="submitRef"` + `useMagneticHover(submitRef)` wired in the script.
2. **Live validation** consuming the existing form via `useFieldValidation(form, rules)`; errors and successes replace the current `errors.username` / `errors.password` reactive refs.
3. **`.decorative-glass` class on the `.login-card-surface` Card wrapper**.
4. **A `<g>` with two paths + an `<animate>` for the brand glyph**; conditional rendering of the animate vs the opacity cross-fade under reduced-motion.
5. **A `<Transition name="login-loading">` wrapper around the form-vs-loading branch** with the three stage labels and the failure shake.
6. **Per-motion reduced-motion blocks** in the scoped `<style>` for each new motion path.

### Path data (pre-validated parity)

Both paths must have **identical command-letter counts**: M5 C5 L8 Z1 (or whatever they actually parse to — must match). The design's `<animate>` element provides two `values` attributes; the parser compares them at template-render time.

```
<!-- Tooth (current) — 1 M, 7 C, 0 L, 1 Z -->
<path id="brand-tooth" d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z" />

<!-- Check (new) — 1 M, 2 L (or equivalent) — MUST be hand-rebuilt to match command count -->
<!-- The apply phase rebuilds this path with a matching command-letter count,
     OR falls back to opacity cross-fade if parity cannot be achieved. -->
```

The fallback path (when parity fails) is `<animate attributeName="opacity">` instead of `<animate attributeName="d">`.

### Multi-stage loading template

```html
<div v-if="loading" class="login-loading">
  <div class="login-skeleton-headline h-7 w-3/4" />
  <ol class="login-stages" role="status" aria-live="polite">
    <li :class="['login-stage', { 'is-active': stage === 1, 'is-done': stage > 1 }]">
      <CheckIcon v-if="stage > 1" />
      <span>Validando</span>
    </li>
    <li :class="['login-stage', { 'is-active': stage === 2, 'is-done': stage > 2 }]">
      <CheckIcon v-if="stage > 2" />
      <span>Autenticando</span>
    </li>
    <li :class="['login-stage', { 'is-active': stage === 3, 'is-done': stage > 3 }]">
      <CheckIcon v-if="stage > 3" />
      <span>Listo</span>
    </li>
  </ol>
</div>
```

Stage advance is `setTimeout`-driven (`setTimeout(() => (stage.value = 2), 150)` etc.) on the assumption that a real login takes ≥ 450ms (network + server). If the API resolves faster, the loading block is short-circuited to the morph branch directly.

---

## File changes summary

### PR1 foundation

| File | Action | Description | Approx lines |
|---|---|---|---|
| `package.json` | Modify | Add `"@vueuse/motion": "3.0.3"` to dependencies. Run `pnpm install`. | 2 |
| `resources/js/app.js` | Modify | Add `import { MotionPlugin } from '@vueuse/motion'` and `app.use(MotionPlugin)`. | 2 |
| `resources/js/composables/useMagneticHover.js` | Create | New composable (see API above). | 110 |
| `resources/js/composables/useFieldValidation.js` | Create | New composable (see API above). | 80 |
| `resources/js/components/ui/Button.vue` | Modify | Additive `data-magnetic` scoped CSS + reduced-motion block. NO template change. | 25 |
| `tests/Unit/Composables/MagneticHoverTest.php` | Create | Pure-math test for the `computeOffset` clamp (using `shell_exec('node -e ...')` per `UseSpringMathTest` precedent). | 50 |
| `tests/Unit/Composables/FieldValidationTest.php` | Create | State-machine + debounce timing test (same pattern). | 60 |

PR1 subtotal: ~329 lines (the math test files are smaller than estimated; the composable files are larger). Under 400.

### PR2 composition

| File | Action | Description | Approx lines |
|---|---|---|---|
| `resources/js/modules/auth/LoginPage.vue` | Modify | Wire all six microinteractions. Net +200 lines (template + script + style). | 200 |
| `resources/css/tokens.generated.css` | Regenerate | Run `node scripts/build-tokens-css.mjs`. No source change — but verify the output. | 0 (regen) |
| `tests/Unit/DesignSystem/LoginPageRenderTest.php` | Modify | Add 6 new source-inspection tests (M hook, V hook, P paths, G class, L stages, R reduced-motion blocks). | 80 |

PR2 subtotal: ~280 lines. Under 400.

---

## Sequence: login success path (PR2 wiring)

```text
user clicks submit
  ↓
UiButton.handleClick → emits 'click'
  ↓
LoginPage.handleLogin()
  ├─ useFieldValidation.validateAll() → true
  ├─ loading = true
  ├─ morph stage machine: stage=1 (Validando) → setTimeout → stage=2 (Autenticando) → API resolves
  └─ login(form) → 200 OK
       ↓
       morph stage machine: stage=3 (Listo) → setTimeout 150ms
       ↓
       loginState = 'success'  (triggers the brand-glyph morph via <Transition>)
       ↓
       brand-glyph SVG: <animate> runs (tooth → check, ~600ms)
       ↓
       on animate 'end' → router.push('/dashboard')
       ↓
       Dashboard mounts

Failure path:
  login(form) → 4xx
       ↓
       morph stage machine cleared, loading = false
       ↓
       card.classList.add('shake') → 220ms keyframe
       ↓
       on shake 'animationend' → card.classList.remove('shake')
       ↓
       error = response.message  (existing aria-live region announces)
```

---

## Test strategy

| Layer | What | How |
|---|---|---|
| Source (PHPUnit) | Magnetic clamp math | `tests/Unit/Composables/MagneticHoverTest.php` — `node -e` shell-out, asserts clamp at `maxDistanceFactor` boundaries. |
| Source (PHPUnit) | Field validation state machine | `tests/Unit/Composables/FieldValidationTest.php` — debounce timing ±20ms, success immediate, error-clears-success, validateAll returns boolean. |
| Source (PHPUnit) | Existing token-source invariants | `tests/Unit/DesignSystem/TokensModuleTest.php` — re-run; no changes. |
| Source (PHPUnit) | Login source-inspection | `tests/Unit/DesignSystem/LoginPageRenderTest.php` — extends with 6 new assertions (see AC list in spec.md). |
| Source (PHPUnit) | Bundle budget | `tests/Unit/Composables/AppShellTest.php` (new) — source-grep `resources/js/app.js` for `MotionPlugin` registration. |
| Backend (Feature) | N/A — no backend changes. | |
| Frontend (Playwright / manual) | Magnetic hover, live validation, glassmorphism, morph, loading | Manual sweep at 1440x900 + iPhone 12 viewport (390x844). Documented in `design.md` §"Manual verification". |

---

## Manual verification checklist (apply phase)

- [ ] `pnpm install` succeeds with no peer-dep warnings.
- [ ] `php artisan test --filter=MagneticHoverTest|FieldValidationTest|LoginPageRenderTest` all green.
- [ ] `php artisan test --testsuite=Unit` all green (regression).
- [ ] `pnpm build` succeeds; bundle delta ≤ +12kb gzipped.
- [ ] `pnpm lint:check` succeeds.
- [ ] At 1440x900 viewport:
  - Mouse over submit button → button tilts toward cursor up to 40% of size, bounces gently on mouse-leave.
  - Tab to username field, type 1 char, Tab away → error "El usuario es requerido" slides down.
  - Tab to password field, type 8+ chars, Tab away → green checkmark scales in.
  - Submit form (mock success) → 3-stage indicator advances over ~450ms → brand glyph morphs to check ~600ms → route transitions to /dashboard.
  - Submit form (mock failure) → shake on the card → form reappears with error message.
- [ ] Toggle DevTools `prefers-reduced-motion: reduce`:
  - Submit button: magnetic inert, only `translateY(-1px)` hover lift remains.
  - Validation: error/checkmark fade-in only, no transform.
  - Morph: opacity cross-fade between two static SVGs.
  - Loading: stages fade, no shake transform.
- [ ] Toggle DevTools `prefers-reduced-transparency: reduce`:
  - Form Card surface flattens to opaque.
- [ ] At iPhone 12 viewport (390x844): all of the above pass without breaking readability.

---

## Threat matrix

**N/A — no routing, shell, subprocess, VCS/PR automation, or executable-file classification.** This change touches only:
- `package.json` / `pnpm-lock.yaml` (additive dep)
- `resources/js/app.js` (additive plugin registration)
- Two new composables (under `resources/js/composables/`)
- One additive scoped CSS block in `Button.vue`
- Composition edits in `LoginPage.vue`
- Regenerated CSS (no source change)
- Two new PHPUnit files + extensions to one existing PHPUnit file

No new files outside `resources/`, `tests/`.

---

## Migration / Rollout

No data migration. The new dependency is additive. The LoginPage composition is internal; the rendered HTML keeps the same `<form>`, the same two fields, the same submit button. The new motion paths are CSS-class additions + JS hooks, not structural changes.

**Rollback:**
- PR1 → revert the package.json change + `pnpm install` + the two new composables + the Button.vue additive CSS + the two PHPUnit files + the app.js plugin registration. No user-visible behaviour changes.
- PR2 → revert `LoginPage.vue` only. The two new composables remain dormant until a future screen adopts them.

---

## What this design does NOT do

- Does NOT redesign the rest of the login or any other screen.
- Does NOT add dark mode.
- Does NOT add gradients.
- Does NOT add hand-written hex literals to any Vue module (existing source-inspection test must not regress).
- Does NOT consume `motion.dampingBounce` (the magnetic composable uses 0.7 literal because it is domain-specific to this effect, not a general token).
- Does NOT install `prefers-color-scheme: dark`.
- Does NOT install GSAP, Flubber, anime.js, or any other animation library.
- Does NOT touch the ForgotPasswordModal or ResetPasswordModal in this slice.

---

## Result

```yaml
status: success
executive_summary: |
  Two stacked PRs, each under 400 lines. PR1 lays the foundation (new
  composables, Button hook, plugin registration, two PHPUnit test files);
  PR2 composes the six microinteractions in LoginPage.vue plus six new
  source-inspection assertions. No token changes. No backend changes. No
  other modules touched. 13 acceptance criteria across 7 specs are gated by
  PHPUnit source-inspection or by manual Playwright sweep at apply time.
artifacts:
  - openspec/changes/ui-login-premium-motion-2026-08/design.md    (this file)
  - engram://sdd/ui-login-premium-motion-2026-08/design          (persisted)
next_recommended: sdd-tasks
risks:
  - SVG path command-count parity is a hard gate (spec P2). If the check path cannot be built with matching parity, the morph falls back to opacity cross-fade.
  - The magnetic composable is the first real consumer of useSpring2D. The existing unit test (UseSpringMathTest) covers the math; the new MagneticHoverTest covers the clamp + bypass. If either fails under @vueuse/motion's plugin registration, the LoginPage wiring exposes the failure.
  - Bundle budget is +12kb gzipped. If exceeded, the change blocks until tree-shaking is verified or the dependency is downgraded.
skill_resolution: paths-injected
```
