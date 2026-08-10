/**
 * useFontsLoaded — flips a ref on `document.fonts.ready` and sets
 * `<html data-fonts-loaded="true">`.
 *
 * The design's FOUT mitigation (Decision 6) is:
 *   1. font-display: swap on the @font-face (immediate fallback paint).
 *   2. Metric-matched fallback stack (minimal layout shift).
 *   3. This composable + a CSS rule keyed on `[data-fonts-loaded="true"]
 *      .font-serif` that micro-adjusts the fallback size/leading so any
 *      remaining width difference after the swap doesn't jump.
 *
 * Behavior contract:
 *   - On mount, if `document.fonts` is unavailable (older browsers, tests),
 *     immediately set `loaded.value = true` so consumers don't wait forever.
 *   - Otherwise, resolve on `document.fonts.ready` and flip the ref.
 *   - The data attribute is the public signal; consumers should prefer it
 *     over the ref so DOM-driven CSS is the single source of truth.
 */
import { ref, onMounted } from 'vue'

export function useFontsLoaded() {
  const loaded = ref(false)

  onMounted(() => {
    if (typeof document === 'undefined' || !document.fonts) {
      loaded.value = true
      applyDataAttribute()
      return
    }

    // `document.fonts.ready` resolves once all declared font faces are
    // either loaded or failed. We treat failure as "ready" — the FOUT
    // mitigation only requires the swap to have a chance to happen.
    Promise.resolve(document.fonts.ready).then(
      () => {
        loaded.value = true
        applyDataAttribute()
      },
      () => {
        loaded.value = true
        applyDataAttribute()
      }
    )
  })

  return loaded
}

function applyDataAttribute() {
  if (typeof document === 'undefined' || !document.documentElement) return
  document.documentElement.dataset.fontsLoaded = 'true'
}

export default useFontsLoaded
