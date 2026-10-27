import { computed, ref } from 'vue'

/**
 * One ordered stack for every overlay primitive (Modal and Sheet).
 *
 * The open set, not the component, owns the layer: a confirmation asked from an
 * open dialog must paint above it even when the confirmation was mounted first
 * (the global UiConfirmDialog of AppLayout mounts before every page modal).
 * Only the top-most surface answers Escape, the backdrop click and Tab.
 *
 * WCAG 2.4.3 (Focus Order).
 */
const BASE_Z_INDEX = 50

const openOverlays = ref([])

export function useOverlayStack() {
  const token = Symbol('overlay')

  const depth = computed(() => Math.max(0, openOverlays.value.indexOf(token)))
  const zIndex = computed(() => BASE_Z_INDEX + depth.value)

  const register = () => {
    if (!openOverlays.value.includes(token)) {
      openOverlays.value = [...openOverlays.value, token]
    }
  }

  const unregister = () => {
    openOverlays.value = openOverlays.value.filter(entry => entry !== token)
  }

  const isTop = () => openOverlays.value[openOverlays.value.length - 1] === token

  return { register, unregister, isTop, zIndex }
}

// The page scroll belongs to the open set too: a dialog layered on top of a
// sheet must not unlock the page the sheet still owns when it closes.
let scrollLocks = 0

export function lockPageScroll() {
  if (scrollLocks === 0) document.body.style.overflow = 'hidden'
  scrollLocks += 1
}

export function unlockPageScroll() {
  scrollLocks = Math.max(0, scrollLocks - 1)
  if (scrollLocks === 0) document.body.style.overflow = ''
}
