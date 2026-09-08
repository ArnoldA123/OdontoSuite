/**
 * useReducedMotion — reactive `prefers-reduced-motion: reduce` detector.
 *
 * Returns a Vue ref that tracks the OS setting. Vue components import this
 * to gate motion paths (e.g. collapse every `v-motion` enter/leave to
 * opacity-only, skip the spring integrator).
 *
 * Implementation note — matchMedia fires a `change` event when the user
 * toggles the OS setting. The reactive ref is re-evaluated on each change
 * so a live user preference flip is honored (mirrors the
 * `prefersReducedMotion` helper in `useSpringMath.js` which is re-checked
 * on every set()).
 *
 * SSR safety — `matchMedia` is undefined on the server. The ref starts
 * at `false` so server-rendered markup assumes motion is on; the client
 * effect flips it on the first browser tick if the user has opted out.
 *
 * Used by:
 *   - LoginPage.vue (gate every polymorphic motion path under reduced
 *     motion + reduced transparency)
 *   - any future page that needs the same primitive
 */

import { ref, onMounted, onUnmounted } from 'vue'

/**
 * @param {MediaQueryList['matches']} [initial=false]
 * @returns {import('vue').Ref<boolean>}
 */
export function useReducedMotion(initial = false) {
  const reduced = ref(initial)
  /** @type {MediaQueryList | null} */
  let mql = null
  /** @type {((e: MediaQueryListEvent) => void) | null} */
  let handler = null

  onMounted(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return
    }
    mql = window.matchMedia('(prefers-reduced-motion: reduce)')
    reduced.value = mql.matches
    handler = (e) => {
      reduced.value = e.matches
    }
    if (typeof mql.addEventListener === 'function') {
      mql.addEventListener('change', handler)
    } else if (typeof mql.addListener === 'function') {
      // Safari < 14 fallback.
      mql.addListener(handler)
    }
  })

  onUnmounted(() => {
    if (!mql || !handler) return
    if (typeof mql.removeEventListener === 'function') {
      mql.removeEventListener('change', handler)
    } else if (typeof mql.removeListener === 'function') {
      mql.removeListener(handler)
    }
    mql = null
    handler = null
  })

  return reduced
}