import { computed, ref } from 'vue'

const DEFAULT_DURATION = 5000
const ERROR_DURATION = 7000

const toasts = ref([])
let toastId = 0

// Un temporizador por toast vivo. Se cancela al retirarlo, al limpiar la pila
// y cuando la misma clave reinicia su cuenta: un toast retirado no puede
// tocar el que ocupa su lugar.
const timers = new Map()

// Riel de avisos (arriba-derecha). El toast efímero publica el alto que ocupa
// y el aviso persistente se apila debajo, así ambos comparten la misma área y
// ninguna capa tapa a la otra. 80 px = top-20 (64 px de topbar + 16 px de
// aire); 12 px = gap-3 de separación entre las dos pilas.
const RAIL_TOP_PX = 80
const RAIL_GAP_PX = 12

const toastRailHeight = ref(0)

export const notificationRailTop = computed(() =>
  toasts.value.length > 0 ? RAIL_TOP_PX + toastRailHeight.value + RAIL_GAP_PX : RAIL_TOP_PX
)

export function setToastRailHeight(height) {
  const next = Number(height)
  toastRailHeight.value = Number.isFinite(next) && next > 0 ? next : 0
}

const clearTimer = id => {
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
}

const removeToast = id => {
  clearTimer(id)
  const index = toasts.value.findIndex(toast => toast.id === id)
  if (index > -1) {
    toasts.value.splice(index, 1)
  }
}

const scheduleRemoval = toast => {
  clearTimer(toast.id)

  // `duration: 0` y `persistent` son peticiones explícitas de un toast que no
  // caduca; el resto se retira solo. Sin esta guarda un toast normal se queda
  // en pantalla indefinidamente.
  if (toast.persistent === true || !(Number(toast.duration) > 0)) {
    return
  }

  timers.set(
    toast.id,
    setTimeout(() => {
      removeToast(toast.id)
    }, Number(toast.duration))
  )
}

const clearAll = () => {
  timers.forEach(timer => clearTimeout(timer))
  timers.clear()
  toasts.value = []
}

const normalizeToast = toast => {
  const normalized = {
    type: 'info', // success, error, warning, info
    title: '',
    message: '',
    duration: DEFAULT_DURATION,
    persistent: false,
    dismissible: true,
    ...toast
  }

  normalized.duration = Number.isFinite(Number(normalized.duration))
    ? Number(normalized.duration)
    : DEFAULT_DURATION
  normalized.persistent = normalized.persistent === true
  // `dedupeKey` es el alias histórico: se guarda normalizado en `key`.
  normalized.key = toast.key ?? toast.dedupeKey ?? null

  return normalized
}

export function useToast() {
  const addToast = toast => {
    const incoming = normalizeToast(toast)

    // Una clave estable identifica el hecho, no el aviso: dos capas que
    // publican el mismo evento reutilizan el toast vivo en vez de apilar uno
    // idéntico. El temporizador se reinicia con la actualización.
    if (incoming.key !== null) {
      const existing = toasts.value.find(item => item.key === incoming.key)
      if (existing) {
        Object.assign(existing, {
          ...incoming,
          id: existing.id,
          revision: (existing.revision ?? 0) + 1,
          startedAt: Date.now()
        })
        scheduleRemoval(existing)
        return existing.id
      }
    }

    const id = ++toastId
    const newToast = {
      ...incoming,
      id,
      revision: 0,
      startedAt: Date.now()
    }

    toasts.value.push(newToast)
    scheduleRemoval(newToast)

    return id
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
      duration: ERROR_DURATION, // Errors stay longer
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
    info,
    // Riel compartido con el aviso persistente. Se publica por aquí, y no
    // sólo como export del módulo, porque un mock de `useToast` puede
    // devolver un objeto parcial: el consumidor lo comprueba antes de usar.
    railTop: notificationRailTop,
    setRailHeight: setToastRailHeight
  }
}

// Global toast state for the toast container
export const globalToasts = toasts
