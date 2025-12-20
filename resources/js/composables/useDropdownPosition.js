import { ref, computed, onMounted, onUnmounted } from 'vue'

export function useDropdownPosition(triggerRef, dropdownRef) {
  const position = ref({ top: 0, left: 0, right: 0, bottom: 0 })
  const isMobile = ref(false)
  const isOpen = ref(false)

  const checkMobile = () => {
    isMobile.value = window.innerWidth < 768
  }

  const calculatePosition = () => {
    if (!triggerRef.value || !isOpen.value) return

    // Obtener el elemento DOM real (puede ser un componente Vue)
    const element = triggerRef.value.$el || triggerRef.value
    if (!element || typeof element.getBoundingClientRect !== 'function') {
      console.error('Invalid trigger element:', element)
      return
    }

    const rect = element.getBoundingClientRect()
    const dropdownHeight = dropdownRef.value?.offsetHeight || 300
    const dropdownWidth = dropdownRef.value?.offsetWidth || 200
    const viewportHeight = window.innerHeight
    const viewportWidth = window.innerWidth

    // Calcular si hay espacio abajo o arriba
    const spaceBelow = viewportHeight - rect.bottom
    const spaceAbove = rect.top

    // Calcular si hay espacio a la derecha o izquierda
    const spaceRight = viewportWidth - rect.left
    const spaceLeft = rect.left

    let top, left, right, bottom, maxHeight, maxWidth

    // Posicionamiento vertical - SIEMPRE abrir hacia abajo
    top = rect.bottom + 8
    maxHeight = Math.min(dropdownHeight, spaceBelow - 16)

    // Si no hay suficiente espacio abajo, ajustar la altura máxima
    if (spaceBelow < 200) {
      maxHeight = Math.max(200, spaceBelow - 16)
    }

    // Posicionamiento horizontal - Alinear a la derecha del trigger
    const rightEdge = rect.right
    if (rightEdge - dropdownWidth >= 8) {
      // Hay espacio para alinear a la derecha
      left = rightEdge - dropdownWidth
    } else {
      // No hay espacio, alinear al borde izquierdo con padding
      left = 8
    }

    // Asegurar que no se salga de la pantalla
    if (left < 8) left = 8
    if (right < 8) right = 8
    if (top < 8) top = 8
    if (bottom < 8) bottom = 8

    position.value = {
      top: top ? `${top}px` : undefined,
      left: left ? `${left}px` : undefined,
      right: right ? `${right}px` : undefined,
      bottom: bottom ? `${bottom}px` : undefined,
      maxHeight: maxHeight ? `${maxHeight}px` : undefined,
      maxWidth: maxWidth ? `${maxWidth}px` : undefined,
      position: 'fixed',
      zIndex: 1000
    }
  }

  const openDropdown = () => {
    isOpen.value = true
    checkMobile()
    if (!isMobile.value) {
      calculatePosition()
    }
  }

  const closeDropdown = () => {
    isOpen.value = false
  }

  const toggleDropdown = () => {
    if (isOpen.value) {
      closeDropdown()
    } else {
      openDropdown()
    }
  }

  onMounted(() => {
    checkMobile()
    window.addEventListener('resize', checkMobile)
    window.addEventListener('scroll', calculatePosition)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    window.removeEventListener('scroll', calculatePosition)
  })

  return {
    position,
    isMobile,
    isOpen,
    calculatePosition,
    openDropdown,
    closeDropdown,
    toggleDropdown
  }
}

