import { ref, reactive } from 'vue'

const toasts = ref([])
let toastId = 0

export function useToast() {
  const addToast = (toast) => {
    const id = ++toastId
    const newToast = {
      id,
      type: 'info', // success, error, warning, info
      title: '',
      message: '',
      duration: 5000,
      dismissible: true,
      ...toast
    }

    toasts.value.push(newToast)

    // Auto remove after duration
    if (newToast.duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, newToast.duration)
    }

    return id
  }

  const removeToast = (id) => {
    const index = toasts.value.findIndex(toast => toast.id === id)
    if (index > -1) {
      toasts.value.splice(index, 1)
    }
  }

  const clearAll = () => {
    toasts.value = []
  }

  // Convenience methods
  const success = (message, options = {}) => {
    return addToast({
      type: 'success',
      message,
      ...options
    })
  }

  const error = (message, options = {}) => {
    return addToast({
      type: 'error',
      message,
      duration: 7000, // Errors stay longer
      ...options
    })
  }

  const warning = (message, options = {}) => {
    return addToast({
      type: 'warning',
      message,
      ...options
    })
  }

  const info = (message, options = {}) => {
    return addToast({
      type: 'info',
      message,
      ...options
    })
  }

  return {
    // Slice 08 / FF-003: return the Ref itself (was `toasts.value` — a
    // plain array snapshot that broke reactivity in consumers). Callers
    // must now read `.value` when destructuring: `const { toasts } =
    // useToast(); toasts.value.push(...)`. The dedicated
    // `globalToasts` export below remains the canonical handle used by
    // ToastContainer.vue.
    toasts,
    addToast,
    removeToast,
    clearAll,
    success,
    error,
    warning,
    info
  }
}

// Global toast state for the toast container
export const globalToasts = toasts
