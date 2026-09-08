/**
 * useShapeMorph — Vue composable that drives the polymorphic submit button
 * + the polymorphic form Card on LoginPage
 * (`ui-login-refinement-dental-split-2026-09`, design Decision 1).
 *
 * Architecture (per design.md):
 *
 *   This composable is a thin orchestrator. It owns:
 *     - the `state` ref (one of: idle / validating / authenticating /
 *       success / error),
 *     - the dwell timestamp for the validating → authenticating guard,
 *     - the timer that fires after the dwell elapses.
 *
 *   It does NOT own the rendering — the template decides which `<span>` is
 *   visible based on `state.value`. This split keeps the composable testable
 *   as pure logic and keeps the template declarative.
 *
 * The state-machine rules live in `shapeMorphMath.js` (pure functions). This
 * file is the Vue wrapper around them. Keeping the math pure means the
 * PHPUnit suite can exercise the rules without booting a browser.
 */

import { ref, onUnmounted, getCurrentInstance } from 'vue'
import { validateTransition, STATES } from './shapeMorphMath.js'

/**
 * @param {object} [options]
 * @param {string} [options.initial='idle']   Initial state
 * @param {number} [options.dwellMs=200]     Minimum dwell for `validating → authenticating`
 * @param {string[]} [options.states=STATES] Allowed state names
 * @returns {{
 *   state: import('vue').Ref<string>,
 *   transition: (to: string) => boolean,
 *   cancel: () => void,
 *   isTerminal: (s?: string) => boolean,
 * }}
 */
export function useShapeMorph(options = {}) {
  const { initial = 'idle', dwellMs = 200 } = options

  const state = ref(initial)
  const dwellStart = ref(0)
  let timer = null

  /**
   * Attempt to move to `to`. Returns true if accepted, false if the state
   * machine rejected the transition. Only the state-machine rules in
   * `shapeMorphMath.js` decide acceptance.
   */
  function transition(to) {
    if (!validateTransition(state.value, to, dwellStart.value, dwellMs)) return false
    // When entering `validating`, record the entry timestamp and start a
    // dwell timer. The timer is informational; the real guard lives in
    // `validateTransition`, which reads `dwellStart`. We keep the timer so
    // a future caller can clearTimeout explicitly.
    if (state.value === 'idle' && to === 'validating') {
      dwellStart.value = Date.now()
      if (timer) clearTimeout(timer)
      timer = setTimeout(() => {
        timer = null
      }, dwellMs)
    }
    state.value = to
    return true
  }

  /**
   * Return to `idle`. Terminal states (success, error) reject cancel so the
   * success crossfade can hold its ground; the form Card morphs through
   * `success` for at least 600ms before the parent routes away.
   */
  function cancel() {
    if (state.value === 'success' || state.value === 'error') return
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    state.value = 'idle'
  }

  /**
   * @param {string} [s=state.value]
   * @returns {boolean}
   */
  function isTerminal(s) {
    const target = typeof s === 'string' ? s : state.value
    return target === 'success' || target === 'error'
  }

  // Cleanup the dwell timer when the component unmounts. Guarded with
  // getCurrentInstance so calling useShapeMorph() outside a Vue setup()
  // (e.g. from a PHPUnit-driven `node -e` shim) does not trigger a Vue
  // runtime warning.
  if (getCurrentInstance() !== null) {
    onUnmounted(() => {
      if (timer) clearTimeout(timer)
    })
  }

  return { state, transition, cancel, isTerminal }
}

// -----------------------------------------------------------------
// Test surface
// -----------------------------------------------------------------
//
// The PHPUnit suite (tests/Unit/Composables/ShapeMorphTest.php) drives the
// composable from `node -e` without booting a Vue runtime. These exports
// give the test an observable post-condition (a plain string) for each
// scenario. They are NOT part of the production API and the LoginPage
// template does not import them.
//
// Each helper constructs a fresh useShapeMorph() instance and reads back
// the relevant state. The instance is short-lived (no Vue component owns
// it), so onUnmounted does not fire and the dwell timer leaks harmlessly
// for the duration of one helper call.

/** @returns {string} */
export function getInitialState() {
  return useShapeMorph().state.value
}

/**
 * @param {string} to
 * @returns {boolean}
 */
export function transitionFromInitial(to) {
  return useShapeMorph().transition(to)
}

/** @returns {string} */
export function driveToValidating() {
  const m = useShapeMorph()
  m.transition('validating')
  return m.state.value
}

/** @returns {string} */
export function driveToValidatingThenCancel() {
  const m = useShapeMorph()
  m.transition('validating')
  m.cancel()
  return m.state.value
}

/** @returns {string} */
export function cancelFromSuccess() {
  const m = useShapeMorph()
  // Drive directly to terminal state to test cancel's terminal guard.
  // The state machine does not permit this transition in production; here
  // it is a test seam that proves the cancel() short-circuit.
  m.state.value = 'success'
  m.cancel()
  return m.state.value
}

/** @returns {string} */
export function cancelFromError() {
  const m = useShapeMorph()
  m.state.value = 'error'
  m.cancel()
  return m.state.value
}