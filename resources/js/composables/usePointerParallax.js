/**
 * usePointerParallax — damped pointer offsets for decorative layers.
 *
 * The composable writes two normalized values ([-1, 1]) as CSS custom
 * properties on the bound element; descendants turn them into per-layer
 * translation amplitudes. Two independent springs (useSpring2D) integrate
 * the offsets, so an interrupt mid-flight blends instead of jumping.
 *
 * Contract:
 *   - `prefers-reduced-motion: reduce` — no pointer listener is attached
 *     (and any already attached is released), so the layers never move.
 *   - The listeners are passive (pointermove on `window`, pointerleave on
 *     `document`) and are always removed on unmount.
 *   - The springs handle their own rAF lifecycle and reduced-motion settle.
 */
import { onMounted, onUnmounted, watch } from 'vue'
import { useSpring2D } from './useSpring2D.js'
import { useReducedMotion } from './useReducedMotion.js'

const clamp = value => Math.max(-1, Math.min(1, value))

/**
 * @param {import('vue').Ref<HTMLElement|null>} elementRef
 * @param {object} [options]
 * @param {number} [options.response=0.35]
 * @param {number} [options.damping=0.8]
 * @param {string} [options.cssVarX='--parallax-x']
 * @param {string} [options.cssVarY='--parallax-y']
 * @returns {{ reduced: import('vue').Ref<boolean>, x: ReturnType<typeof useSpring2D>['x'], y: ReturnType<typeof useSpring2D>['y'] }}
 */
export function usePointerParallax(elementRef, options = {}) {
  const {
    response = 0.35,
    damping = 0.8,
    cssVarX = '--parallax-x',
    cssVarY = '--parallax-y'
  } = options

  const reduced = useReducedMotion()
  const { x, y } = useSpring2D({ response, damping, cssVarX, cssVarY })

  let listening = false

  const onPointerMove = event => {
    const width = window.innerWidth || 1
    const height = window.innerHeight || 1
    x.set(clamp((event.clientX / width) * 2 - 1))
    y.set(clamp((event.clientY / height) * 2 - 1))
  }

  const onPointerLeave = () => {
    x.set(0)
    y.set(0)
  }

  const attach = () => {
    if (listening) return
    window.addEventListener('pointermove', onPointerMove, { passive: true })
    document.addEventListener('pointerleave', onPointerLeave)
    listening = true
  }

  const release = () => {
    if (!listening) return
    window.removeEventListener('pointermove', onPointerMove)
    document.removeEventListener('pointerleave', onPointerLeave)
    listening = false
  }

  // Live reduced-motion flips also recentre the layers.
  const detach = () => {
    release()
    x.set(0)
    y.set(0)
  }

  const sync = () => {
    if (reduced.value) {
      detach()
      return
    }
    attach()
  }

  onMounted(() => {
    const el = elementRef.value
    if (!el) return
    x.attach(el)
    y.attach(el)
    sync()
  })

  watch(reduced, sync)
  onUnmounted(release)

  return { reduced, x, y }
}

export default usePointerParallax
