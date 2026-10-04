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
 *   - `pointermove` is the only event that retargets the springs, for every
 *     pointer type (mouse, pen, touch). A mouse retargets from the first
 *     move; a touch or pen press has to travel past the tap slop before it
 *     steers, so a tap (and its jitter) leaves the scene perfectly still.
 *   - `pointerdown` only records where a touch or pen gesture started and
 *     `pointerup` / `pointercancel` only close it: neither ever sets a
 *     target, and no `touchstart` / `click` handler exists.
 *   - The listeners are passive and are always removed on unmount.
 *   - `active` reports whether the offsets are moving (a retarget is in
 *     flight, or the settle window has not elapsed), so consumers can scope
 *     `will-change` to the animation instead of reserving a compositor
 *     layer at rest.
 *   - The springs handle their own rAF lifecycle and reduced-motion settle.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useSpring2D } from './useSpring2D.js'
import { useReducedMotion } from './useReducedMotion.js'

/**
 * How long `active` stays true after the last pointer event: long enough to
 * cover the damped return to rest, short enough to release the layer.
 */
const ACTIVITY_IDLE_MS = 1200

/**
 * How far a touch or pen press must travel before it steers the parallax.
 * Below it the gesture stays a tap: the scene never shudders at the tap point.
 */
const TAP_SLOP_PX = 4

const clamp = value => Math.max(-1, Math.min(1, value))

const isDragPointer = event => event.pointerType === 'touch' || event.pointerType === 'pen'

const pointerKey = event => event.pointerId ?? event.pointerType ?? 'primary'

/**
 * @param {import('vue').Ref<HTMLElement|null>} elementRef
 * @param {object} [options]
 * @param {number} [options.response=0.35]
 * @param {number} [options.damping=0.8]
 * @param {string} [options.cssVarX='--parallax-x']
 * @param {string} [options.cssVarY='--parallax-y']
 * @returns {{
 *   reduced: import('vue').Ref<boolean>,
 *   active: import('vue').Ref<boolean>,
 *   x: ReturnType<typeof useSpring2D>['x'],
 *   y: ReturnType<typeof useSpring2D>['y']
 * }}
 */
export function usePointerParallax(elementRef, options = {}) {
  const {
    response = 0.35,
    damping = 0.8,
    cssVarX = '--parallax-x',
    cssVarY = '--parallax-y'
  } = options

  const reduced = useReducedMotion()
  const active = ref(false)
  const { x, y } = useSpring2D({ response, damping, cssVarX, cssVarY })

  let listening = false
  let idleTimer = null
  let gesture = null

  const settleActivity = () => {
    if (idleTimer) clearTimeout(idleTimer)
    idleTimer = null
    active.value = false
  }

  const markActivity = () => {
    active.value = true
    if (idleTimer) clearTimeout(idleTimer)
    idleTimer = setTimeout(settleActivity, ACTIVITY_IDLE_MS)
  }

  const onPointerDown = event => {
    if (!isDragPointer(event)) return
    gesture = {
      key: pointerKey(event),
      startX: event.clientX,
      startY: event.clientY,
      engaged: false
    }
  }

  const endGesture = event => {
    if (gesture && gesture.key === pointerKey(event)) gesture = null
  }

  const onPointerMove = event => {
    if (gesture && gesture.key === pointerKey(event) && !gesture.engaged) {
      const travelled = Math.hypot(event.clientX - gesture.startX, event.clientY - gesture.startY)
      if (travelled < TAP_SLOP_PX) return
      gesture.engaged = true
    }

    const width = window.innerWidth || 1
    const height = window.innerHeight || 1
    x.set(clamp((event.clientX / width) * 2 - 1))
    y.set(clamp((event.clientY / height) * 2 - 1))
    markActivity()
  }

  const onPointerLeave = event => {
    if (event.pointerType && isDragPointer(event)) return
    x.set(0)
    y.set(0)
    markActivity()
  }

  const attach = () => {
    if (listening) return
    window.addEventListener('pointermove', onPointerMove, { passive: true })
    window.addEventListener('pointerdown', onPointerDown, { passive: true })
    window.addEventListener('pointerup', endGesture, { passive: true })
    window.addEventListener('pointercancel', endGesture, { passive: true })
    document.addEventListener('pointerleave', onPointerLeave)
    listening = true
  }

  const release = () => {
    if (!listening) return
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerdown', onPointerDown)
    window.removeEventListener('pointerup', endGesture)
    window.removeEventListener('pointercancel', endGesture)
    document.removeEventListener('pointerleave', onPointerLeave)
    listening = false
  }

  const teardown = () => {
    release()
    gesture = null
    settleActivity()
  }

  const sync = () => {
    if (reduced.value) {
      teardown()
      x.set(0)
      y.set(0)
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
  onUnmounted(teardown)

  return { reduced, active, x, y }
}

export default usePointerParallax
