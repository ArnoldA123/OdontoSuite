/**
 * useSpringMath — pure math kernels for the motion runtime.
 *
 * The Vue composable (`useSpring.js`) wraps these kernels and adds rAF
 * scheduling + CSS var writes + reduced-motion detection. Keeping the math
 * pure makes it testable from a node `shell_exec` without booting a browser
 * or a Vue runtime; the PHPUnit suite (`tests/Unit/DesignSystem/UseSpringMathTest.php`)
 * shells out to `node -e` to exercise every branch.
 *
 * Contract — every function returns a NEW state object; nothing mutates inputs.
 * This is what lets `useSpring` interrupt mid-flight: the integrator advances
 * state, and the caller chooses the next target without touching the integrator.
 *
 * Apple motion semantics (design Decision 2):
 *   - omega0 = 2π / response  (angular frequency for response time)
 *   - zeta   = damping        (1.0 = critically damped, <1.0 = underdamped)
 *   - integrator: damped harmonic oscillator (a = -k·x - c·v)
 *   - bounciness is purely a function of zeta; the integrator does not change.
 */

/**
 * Single spring step — one fixed-dt advance of the damped harmonic oscillator.
 *
 * @param {object} state     Current { value, velocity }
 * @param {number} target    Target value to settle toward
 * @param {object} options
 * @param {number} options.response  seconds to reach target (Apple "response")
 * @param {number} options.damping   1.0 critically damped; <1.0 underdamped
 * @param {number} options.dt        timestep in seconds (default 1/60)
 * @returns {object} NEW state { value, velocity }
 */
export function stepSpring(state, target, { response = 0.35, damping = 1.0, dt = 1 / 60 } = {}) {
  const omega0 = (2 * Math.PI) / response
  const k = omega0 * omega0
  const c = 2 * damping * omega0
  const dx = state.value - target
  const a = -k * dx - c * state.velocity
  return {
    value: state.value + state.velocity * dt,
    velocity: state.velocity + a * dt
  }
}

/**
 * Critically-damped settle test helper — runs N steps of the integrator and
 * reports the final state. The caller decides pass/fail on the returned
 * numbers (test-side, so the helper stays a pure function).
 *
 * @param {object} init      Initial { value, velocity }
 * @param {number} target
 * @param {object} options
 * @param {number} options.steps      number of fixed-dt steps to run
 * @param {number} options.response
 * @param {number} options.damping
 * @param {number} options.dt
 * @returns {object} final { value, velocity }
 */
export function settle(init, target, { steps = 600, response = 0.35, damping = 1.0, dt = 1 / 60 } = {}) {
  let s = { value: init.value, velocity: init.velocity }
  for (let i = 0; i < steps; i++) {
    s = stepSpring(s, target, { response, damping, dt });
  }
  return s
}

/**
 * Project the current value forward at the given velocity, then snap to the
 * nearest snap point. `decelerationRate d` is the iOS-style friction:
 *   projected = current + (velocity / 1000) * d / (1 - d)
 * Returns the snap point closest to the projection.
 *
 * Independent of axis — `useSpring2D` calls this for both X and Y separately.
 *
 * @param {number} current
 * @param {number} velocity
 * @param {number[]} snapPoints
 * @param {number} d   deceleration rate, default 0.998 (iOS standard)
 * @returns {number} nearest snap point
 */
export function projectAndSnap(current, velocity, snapPoints, d = 0.998) {
  if (!Array.isArray(snapPoints) || snapPoints.length === 0) {
    return current;
  }
  const projected = current + (velocity / 1000) * d / (1 - d)
  return snapPoints.reduce((a, b) =>
    Math.abs(b - projected) < Math.abs(a - projected) ? b : a, snapPoints[0])
}

/**
 * Reduced-motion instant apply — value jumps directly to the target with no
 * animation. Velocity is zeroed so subsequent gestures start from rest.
 *
 * @param {number} target
 * @returns {object} final { value, velocity }
 */
export function instantSettle(target) {
  return { value: target, velocity: 0 }
}

/**
 * Live reduced-motion probe. Mirrors the constructor check in useSpring,
 * but re-checked on every `set` call so a user who toggles the OS setting
 * mid-flight is honored on the next gesture.
 *
 * The browser global is the only non-pure part of the runtime. This helper
 * is shaped so tests can pass a mock `matchMedia` object.
 *
 * @param {object|undefined} matchMedia  defaults to globalThis.matchMedia
 * @returns {boolean}
 */
export function prefersReducedMotion(matchMedia) {
  const mm = matchMedia || (typeof globalThis !== 'undefined' ? globalThis.matchMedia : undefined)
  if (!mm) return false
  try {
    return mm('(prefers-reduced-motion: reduce)').matches === true
  } catch (_e) {
    return false
  }
}
