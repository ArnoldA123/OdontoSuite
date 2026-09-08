/**
 * useMagneticHover — magnetic cursor effect on the bound element.
 *
 * When the cursor enters the element's bounds, two springs (useSpring2D)
 * are set toward a clamped offset (dx, dy) such that the element tilts
 * toward the cursor. On cursor-leave, both springs target (0, 0) with
 * underdamped `damping: 0.7` for one gentle bounce.
 *
 * Bypassed under `prefers-reduced-motion: reduce` AND on `(pointer: coarse)`
 * devices — both checks are evaluated at composable construction time so
 * a user toggling the OS setting on a sticky page is honoured on next
 * interaction (R4 — spec login-premium-motion-reduced-motion).
 *
 * The bound element receives two CSS custom properties (default
 * `--spring-magnet-x` and `--spring-magnet-y`) which the additive scoped
 * CSS in Button.vue composes with the existing `translateY(-1px)` hover
 * lift.
 *
 * Design contract (design.md §"useMagneticHover" + spec M1–M8):
 *   - maxDistanceFactor 0.4 (40% of min(width, height))
 *   - response 0.35 (Apple "response" — reaches target in ~350ms)
 *   - damping 0.7 (one gentle bounce on release; M3)
 *   - settle within 600ms (M6)
 *   - reduced-motion + coarse-pointer short-circuit (M4, M5)
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { useSpring2D } from './useSpring2D.js'
import { computeOffset as computeOffsetMath } from './magneticHoverMath.js'

/**
 * Re-export the pure clamp helper so the unit tests
 * (`tests/Unit/Composables/MagneticHoverTest.php`) can call it with
 * synthetic bounds and cursor positions without booting a Vue runtime.
 *
 * The math lives in `./magneticHoverMath.js` so it is Node-loadable in a
 * plain ESM context (no Vue dependency). The composable file re-exports
 * it for the public API surface (`import { computeOffset } from
 * '@/composables/useMagneticHover'`).
 *
 * @param {{left:number, top:number, width:number, height:number}} bounds
 * @param {number} mx                          cursor client X
 * @param {number} my                          cursor client Y
 * @param {number} [maxDistanceFactor=0.4]    40% cap of min(width, height)
 * @returns {{dx:number, dy:number}}
 */
export function computeOffset(bounds, mx, my, maxDistanceFactor = 0.4) {
  return computeOffsetMath(bounds, mx, my, maxDistanceFactor)
}

/**
 * @param {import('vue').Ref<HTMLElement|null>} elementRef
 * @param {object} [options]
 * @param {number} [options.response=0.35]
 * @param {number} [options.damping=0.7]
 * @param {number} [options.maxDistanceFactor=0.4]
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
    const el = elementRef.value
    if (el && typeof el.getBoundingClientRect === 'function') {
      const rect = el.getBoundingClientRect()
      bounds = { left: rect.left, top: rect.top, width: rect.width, height: rect.height }
    }
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
    const { dx, dy } = computeOffset(
      bounds,
      pendingEvent.clientX,
      pendingEvent.clientY,
      maxDistanceFactor
    )
    x.set(dx)
    y.set(dy)
  }

  const refreshBounds = () => {
    if (!active.value) return
    const el = elementRef.value
    if (el && typeof el.getBoundingClientRect === 'function') {
      const rect = el.getBoundingClientRect()
      bounds = { left: rect.left, top: rect.top, width: rect.width, height: rect.height }
    }
  }

  onMounted(() => {
    const el = elementRef.value
    if (!el) return
    // PR2 FIX: attach the two springs to the element so useSpring.writeVar()
    // has a target. Without this, the CSS vars --spring-magnet-x/y are never
    // written and the magnetic effect is silent. (Discovered during PR2
    // functional sweep; verify-report PR2 entry documents the fix.)
    x.attach(el)
    y.attach(el)
    el.addEventListener('mouseenter', onEnter)
    el.addEventListener('mousemove', onMove)
    el.addEventListener('mouseleave', onLeave)
    // Refresh bounds on resize/scroll so the magnetic remains accurate.
    window.addEventListener('resize', refreshBounds)
    window.addEventListener('scroll', refreshBounds, { passive: true })
  })

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
