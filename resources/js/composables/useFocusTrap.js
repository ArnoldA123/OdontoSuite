import { onBeforeUnmount, nextTick } from 'vue'

/**
 * Traps focus inside a container element while it is open.
 * - Cycles Tab / Shift+Tab through the focusable descendants.
 * - Restores focus to the previously focused element on close.
 *
 * Returns a controller object so the calling component can pause/resume
 * the trap (e.g. when a child menu opens on top of the modal).
 *
 * WCAG 2.1.1 (Keyboard) + 2.4.3 (Focus Order).
 */
const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'textarea:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
  'audio[controls]',
  'video[controls]',
  '[contenteditable]:not([contenteditable="false"])'
].join(',')

export function useFocusTrap() {
  let containerEl = null
  let previouslyFocused = null
  let active = false

  const getFocusable = () => {
    if (!containerEl) return []
    return Array.from(containerEl.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
      (el) => !el.hasAttribute('disabled') && el.offsetParent !== null
    )
  }

  const focusFirst = () => {
    const focusable = getFocusable()
    if (focusable.length > 0) {
      focusable[0].focus()
    } else if (containerEl) {
      containerEl.focus()
    }
  }

  const handleKeydown = (event) => {
    if (event.key !== 'Tab' || !active) return

    const focusable = getFocusable()
    if (focusable.length === 0) {
      event.preventDefault()
      if (containerEl) containerEl.focus()
      return
    }

    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    const current = document.activeElement

    if (event.shiftKey) {
      if (current === first || !containerEl.contains(current)) {
        event.preventDefault()
        last.focus()
      }
    } else {
      if (current === last || !containerEl.contains(current)) {
        event.preventDefault()
        first.focus()
      }
    }
  }

  /**
   * Activate the focus trap on the given container.
   * Stores the currently focused element so it can be restored on release().
   */
  const activate = (container) => {
    if (!container) return
    containerEl = container
    previouslyFocused = document.activeElement
    active = true
    document.addEventListener('keydown', handleKeydown)
    nextTick(() => focusFirst())
  }

  /**
   * Release the trap and restore the previous focus target.
   */
  const release = () => {
    active = false
    document.removeEventListener('keydown', handleKeydown)
    containerEl = null
    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
      previouslyFocused.focus()
    }
    previouslyFocused = null
  }

  onBeforeUnmount(() => {
    if (active) release()
  })

  return {
    activate,
    release,
    focusFirst
  }
}
