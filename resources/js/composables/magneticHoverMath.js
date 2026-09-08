/**
 * magneticHoverMath — pure math + DOM-free helpers for the
 * `useMagneticHover` composable.
 *
 * Mirrors the `useSpringMath.js` separation pattern: the pure functions
 * live in their own module so the PHPUnit suite
 * (`tests/Unit/Composables/MagneticHoverTest.php`) can shell out to
 * `node -e` and exercise them without booting a Vue runtime.
 *
 * No imports — keeps the module Node-loadable in a plain ESM context.
 */

/**
 * Pure clamp helper — given element bounds + cursor position, return the
 * clamped offset `(dx, dy)` the magnetic effect should spring toward.
 *
 * @param {{left:number, top:number, width:number, height:number}} bounds
 * @param {number} mx                          cursor client X
 * @param {number} my                          cursor client Y
 * @param {number} [maxDistanceFactor=0.4]    40% cap of min(width, height)
 * @returns {{dx:number, dy:number}}
 */
export function computeOffset(bounds, mx, my, maxDistanceFactor = 0.4) {
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

export default computeOffset
