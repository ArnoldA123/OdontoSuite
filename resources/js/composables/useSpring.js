/**
 * useSpring — single-axis Vue 3 composable backed by rAF + CSS variables.
 *
 * Design contract (Decision 2 — non-negotiable):
 *   1. Animate from the live presentation value, never the logical/target
 *      value. On interrupt, the integrator's current state IS the new `from`.
 *   2. Be interruptible and re-targetable mid-flight. `set(newTarget)`
 *      while animating must blend, not restart.
 *   3. Carry velocity through a re-target. A reversal must not produce a
 *      velocity discontinuity ("brick wall").
 *   4. Accept a release velocity — `set(target, { velocity })` — so a
 *      gesture hands off to the spring with no visible seam.
 *   5. Critically damped by default (`damping 1.0`, `response 0.35`).
 *      Bounce (`damping ~0.8`) only for momentum-carrying gestures.
 *   6. Respect `prefers-reduced-motion: reduce` — set the value instantly
 *      (or a short opacity cross-fade), never a broken half-animation.
 *      The media query is re-checked on every set so a user toggling
 *      the OS setting mid-flight is honored.
 *   7. Clean up on unmount — cancel the rAF loop in `onUnmounted`.
 *   8. Animate only compositor-friendly properties (transform / opacity)
 *      via CSS custom properties.
 *
 * The math kernel (integrator + momentum projection + reduced-motion
 * detection) lives in `useSpringMath.js` so it can be unit-tested
 * without booting a Vue runtime.
 */
import { ref, onUnmounted } from 'vue'
import {
  stepSpring,
  instantSettle,
  prefersReducedMotion
} from './useSpringMath.js'

/**
 * @param {object} options
 * @param {number} [options.response=0.35]  seconds to reach target (Apple "response")
 * @param {number} [options.damping=1.0]    1.0 = critically damped, <1.0 = underdamped
 * @param {number} [options.from=0]         starting value
 * @param {number} [options.to=null]        optional initial target (defaults to `from`)
 * @param {Function} [options.onSettle=null] called once when |v| < eps AND |dx| < eps
 * @param {string} [options.cssVar='--spring-x'] CSS custom property to write on the bound element
 * @param {object} [options.element=null]    optional element ref to write the var to
 * @returns {{
 *   value: import('vue').Ref<number>,
 *   velocity: import('vue').Ref<number>,
 *   target: import('vue').Ref<number>,
 *   set: (next: number, opts?: { velocity?: number }) => void,
 *   stop: () => void,
 *   attach: (el: HTMLElement) => void
 * }}
 */
export function useSpring(options = {}) {
  const {
    response = 0.35,
    damping = 1.0,
    from = 0,
    to = null,
    onSettle = null,
    cssVar = '--spring-x'
  } = options

  const value = ref(to ?? from)
  const velocity = ref(0)
  const target = ref(to ?? from)
  const epsilon = 0.5
  let rafId = null
  let lastTs = 0
  let boundElement = null

  function writeVar() {
    if (boundElement && typeof boundElement.style?.setProperty === 'function') {
      boundElement.style.setProperty(cssVar, String(value.value))
    }
  }

  function step(now) {
    const dt = lastTs === 0
      ? 1 / 60
      : Math.min(0.064, (now - lastTs) / 1000)
    lastTs = now

    const next = stepSpring(
      { value: value.value, velocity: velocity.value },
      target.value,
      { response, damping, dt }
    )
    value.value = next.value
    velocity.value = next.velocity
    writeVar()

    const dx = value.value - target.value
    if (Math.abs(velocity.value) < epsilon && Math.abs(dx) < epsilon) {
      // Land exactly on target, zero velocity, stop the loop.
      value.value = target.value
      velocity.value = 0
      rafId = null
      lastTs = 0
      writeVar()
      onSettle?.()
      return
    }

    rafId = requestAnimationFrame(step)
  }

  function ensureRunning() {
    if (rafId == null && !prefersReducedMotion()) {
      lastTs = 0
      rafId = requestAnimationFrame(step)
    }
  }

  /**
   * Set a new target. Optional `velocity` injects a release velocity from
   * a gesture handoff (e.g. drag-end flick) so the spring continues with
   * the user's momentum, not from rest.
   */
  function set(next, opts = {}) {
    // Reduced-motion: instant apply, no rAF.
    if (prefersReducedMotion()) {
      const settled = instantSettle(next)
      value.value = settled.value
      velocity.value = settled.velocity
      target.value = settled.value
      if (rafId != null) { cancelAnimationFrame(rafId); rafId = null }
      writeVar()
      onSettle?.()
      return
    }

    if (opts.velocity != null) {
      velocity.value = opts.velocity
    }
    target.value = next
    ensureRunning()
  }

  function stop() {
    if (rafId != null) {
      cancelAnimationFrame(rafId)
      rafId = null
    }
    target.value = value.value
    velocity.value = 0
    lastTs = 0
  }

  function attach(el) {
    boundElement = el
    writeVar()
  }

  // Cleanup on component unmount. Per the contract, a leaked rAF loop
  // per component is a real performance bug.
  onUnmounted(stop)

  return { value, velocity, target, set, stop, attach }
}

export default useSpring
