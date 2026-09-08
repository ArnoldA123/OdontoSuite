/**
 * shapeMorphMath — pure functions for the shape-morph state machine.
 *
 * Drives the polymorphic submit button + polymorphic form Card on LoginPage
 * (`ui-login-refinement-dental-split-2026-09`, design Decision 2). Kept
 * pure so the PHPUnit test (`tests/Unit/Composables/ShapeMorphTest.php`)
 * can exercise every branch via `shell_exec('node -e ...')` without
 * booting a Vue runtime.
 *
 * State machine:
 *
 *   idle ──► validating ──► authenticating ──► success (terminal)
 *     ▲           │                                 │
 *     │           ▼                                 ▼
 *     └───── error (terminal) ◄──────────────────────┘
 *
 *   - Terminal states (success, error) reject every outbound transition.
 *     Only `useShapeMorph.cancel()` may force them back to idle (it
 *     short-circuits validateTransition entirely).
 *   - `validating → authenticating` requires the dwell timer to have
 *     elapsed (>= dwellMs since the validating state was entered). This
 *     prevents the validating text from flashing past the API call.
 *
 * Contract — every function returns a NEW value; nothing mutates inputs.
 */

export const STATES = ['idle', 'validating', 'authenticating', 'success', 'error']

/**
 * Returns true iff the transition `from → to` is allowed by the state
 * machine.
 *
 * @param {string} from       Current state (must be in STATES)
 * @param {string} to         Target state (must be in STATES)
 * @param {number} dwellStart  Unix-ms timestamp when the current state was entered
 * @param {number} [dwellMs=200]  Minimum dwell required for `validating → authenticating`
 * @returns {boolean}
 */
export function validateTransition(from, to, dwellStart, dwellMs = 200) {
  if (!STATES.includes(from) || !STATES.includes(to)) return false
  if (from === to) return false
  if (from === 'success' || from === 'error') return false
  if (from === 'validating' && to === 'authenticating') {
    return Date.now() - dwellStart >= dwellMs
  }
  return true
}

/**
 * Clamp a numeric value into the [min, max] range. Default range is [0, 1]
 * (intensity / progress convention).
 *
 * @param {number} value
 * @param {number} [min=0]
 * @param {number} [max=1]
 * @returns {number}
 */
export function clampIntensity(value, min = 0, max = 1) {
  return Math.max(min, Math.min(max, value))
}

/**
 * Milliseconds remaining on the dwell timer at `now`. Returns 0 once the
 * dwell has elapsed.
 *
 * @param {number} startedAt   Unix-ms timestamp when the dwell started
 * @param {number} [dwellMs=200]
 * @returns {number}
 */
export function dwellRemaining(startedAt, dwellMs = 200) {
  return Math.max(0, dwellMs - (Date.now() - startedAt))
}