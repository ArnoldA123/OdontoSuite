/**
 * useSpring2D — independent X and Y springs for 2D motion (drag, sheet swipe).
 *
 * Design contract (Decision 2 + the 2.3.10 spec):
 *   - Never use a single spring on a 2D distance; X and Y must be independent
 *     so a vertical drag does not produce horizontal wobble.
 *   - Expose `projectAndSnap(current, velocity, snapPoints)` so a drag-end
 *     gesture can compute the final landing point via the iOS deceleration
 *     curve and snap to a nearby snap point. The deceleration rate is the
 *     iOS standard `d = 0.998` (configurable).
 *
 * Each axis is its own `useSpring` instance. The 2D wrapper just composes
 * them and re-exports the math.
 */
import { useSpring } from './useSpring.js'
import { projectAndSnap as projectAndSnapMath } from './useSpringMath.js'

/**
 * @param {object} options  passed straight through to each axis useSpring
 * @returns {{
 *   x: ReturnType<typeof useSpring>,
 *   y: ReturnType<typeof useSpring>
 * }}
 */
export function useSpring2D(options = {}) {
  const x = useSpring({ ...options, cssVar: options.cssVarX || '--spring-x' })
  const y = useSpring({ ...options, cssVar: options.cssVarY || '--spring-y' })
  return { x, y }
}

/**
 * Re-export the pure projection function for callers that want to compute
 * the landing point without going through a full spring instance.
 *
 * @param {number} current
 * @param {number} velocity
 * @param {number[]} snapPoints
 * @param {number} [d=0.998]
 * @returns {number}
 */
export function projectAndSnap(current, velocity, snapPoints, d = 0.998) {
  return projectAndSnapMath(current, velocity, snapPoints, d)
}

export default useSpring2D
