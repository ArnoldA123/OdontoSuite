import { ref, onMounted, onUnmounted } from 'vue'

export function useAccessibility() {
  const isKeyboardUser = ref(false)
  const focusableElements = ref([])
  const currentFocusIndex = ref(-1)

  // Detect if user is navigating with keyboard
  const handleKeyDown = (event) => {
    if (event.key === 'Tab') {
      isKeyboardUser.value = true
    }
  }

  const handleMouseDown = () => {
    isKeyboardUser.value = false
  }

  // Get all focusable elements
  const getFocusableElements = (container = document) => {
    const focusableSelectors = [
      'button:not([disabled])',
      'input:not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      'a[href]',
      '[tabindex]:not([tabindex="-1"])',
      '[contenteditable="true"]'
    ].join(', ')

    return Array.from(container.querySelectorAll(focusableSelectors))
      .filter(element => {
        const style = window.getComputedStyle(element)
        return style.display !== 'none' && style.visibility !== 'hidden'
      })
  }

  // Trap focus within a container
  const trapFocus = (container) => {
    const focusableElements = getFocusableElements(container)

    if (focusableElements.length === 0) return

    const firstElement = focusableElements[0]
    const lastElement = focusableElements[focusableElements.length - 1]

    const handleTabKey = (event) => {
      if (event.key !== 'Tab') return

      if (event.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstElement) {
          event.preventDefault()
          lastElement.focus()
        }
      } else {
        // Tab
        if (document.activeElement === lastElement) {
          event.preventDefault()
          firstElement.focus()
        }
      }
    }

    container.addEventListener('keydown', handleTabKey)

    // Focus first element
    firstElement.focus()

    // Return cleanup function
    return () => {
      container.removeEventListener('keydown', handleTabKey)
    }
  }

  // Announce message to screen readers
  const announce = (message, priority = 'polite') => {
    const announcement = document.createElement('div')
    announcement.setAttribute('aria-live', priority)
    announcement.setAttribute('aria-atomic', 'true')
    announcement.className = 'sr-only'
    announcement.textContent = message

    document.body.appendChild(announcement)

    // Remove after announcement
    setTimeout(() => {
      document.body.removeChild(announcement)
    }, 1000)
  }

  // Skip to main content
  const skipToMain = () => {
    const mainContent = document.querySelector('main') || document.querySelector('#main')
    if (mainContent) {
      mainContent.focus()
      mainContent.scrollIntoView()
    }
  }

  // Handle escape key
  const handleEscape = (callback) => {
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        callback()
      }
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
    }
  }

  // Handle arrow key navigation
  const handleArrowNavigation = (container, direction = 'vertical') => {
    const focusableElements = getFocusableElements(container)
    let currentIndex = focusableElements.indexOf(document.activeElement)

    const handleKeyDown = (event) => {
      if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
        return
      }

      event.preventDefault()

      if (direction === 'vertical') {
        if (event.key === 'ArrowDown') {
          currentIndex = (currentIndex + 1) % focusableElements.length
        } else if (event.key === 'ArrowUp') {
          currentIndex = currentIndex <= 0 ? focusableElements.length - 1 : currentIndex - 1
        }
      } else if (direction === 'horizontal') {
        if (event.key === 'ArrowRight') {
          currentIndex = (currentIndex + 1) % focusableElements.length
        } else if (event.key === 'ArrowLeft') {
          currentIndex = currentIndex <= 0 ? focusableElements.length - 1 : currentIndex - 1
        }
      }

      focusableElements[currentIndex]?.focus()
    }

    container.addEventListener('keydown', handleKeyDown)

    return () => {
      container.removeEventListener('keydown', handleKeyDown)
    }
  }

  // Set up keyboard detection
  onMounted(() => {
    document.addEventListener('keydown', handleKeyDown)
    document.addEventListener('mousedown', handleMouseDown)
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown)
    document.removeEventListener('mousedown', handleMouseDown)
  })

  return {
    isKeyboardUser,
    getFocusableElements,
    trapFocus,
    announce,
    skipToMain,
    handleEscape,
    handleArrowNavigation
  }
}
