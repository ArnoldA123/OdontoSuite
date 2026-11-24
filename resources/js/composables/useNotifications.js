import { ref, computed } from 'vue'

const notifications = ref([])
const notificationId = ref(0)
const STORAGE_KEY = 'odonto_suite_notifications'
const MAX_NOTIFICATIONS = 100
const DEFAULT_DURATION = 5000
const ERROR_DURATION = 8000

// Un temporizador por notificación viva. La superficie visible también arma el
// suyo (NotificationToast), que es el que rescata las filas rehidratadas
// desde localStorage: aquí se cancela al retirar y al reiniciar una clave.
const timers = new Map()

const clearTimer = id => {
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
}

const removeNotification = id => {
  clearTimer(id)
  const index = notifications.value.findIndex(n => n.id === id)
  if (index > -1) {
    notifications.value.splice(index, 1)
    saveToStorage()
  }
}

const scheduleExpiry = notification => {
  clearTimer(notification.id)

  // `persistent: true` y `duration: 0` son peticiones explícitas; el resto
  // caduca. Una fila rehidratada sin temporizador quedaba en pantalla para
  // siempre, que es el «toast atascado» que este cambio retira.
  if (notification.persistent === true || !(Number(notification.duration) > 0)) {
    return
  }

  timers.set(
    notification.id,
    setTimeout(() => {
      removeNotification(notification.id)
    }, Number(notification.duration))
  )
}

const clearTimers = () => {
  timers.forEach(timer => clearTimeout(timer))
  timers.clear()
}

const durationFor = (type, options) => {
  if (options.duration !== undefined) return Number(options.duration)
  return type === 'error' ? ERROR_DURATION : DEFAULT_DURATION
}

// Una notificación guardada ya caducada no vuelve a aparecer al recargar: el
// temporizador que la retiraba vivía en la sesión anterior.
const isExpired = notification => {
  if (notification?.persistent === true) return false
  const duration = Number(notification?.duration ?? DEFAULT_DURATION)
  if (!Number.isFinite(duration) || duration <= 0) return false
  const created = Date.parse(notification?.timestamp ?? '')
  if (Number.isNaN(created)) return false
  return created + duration <= Date.now()
}

const hydrate = list =>
  (Array.isArray(list) ? list : [])
    .filter(notification => notification && !isExpired(notification))
    .slice(0, MAX_NOTIFICATIONS)
    .map(notification => ({
      ...notification,
      key: notification.key ?? null,
      revision: notification.revision ?? 0,
      persistent: notification.persistent === true
    }))

// Cargar notificaciones desde localStorage al iniciar
const loadFromStorage = () => {
  // Slice 08 / FF-013: SSR / non-browser environments (Vite SSR, tests
  // in Node) don't have `window.localStorage` and the unguarded top-level
  // call crashed module evaluation. Guard before touching storage.
  if (typeof window === 'undefined' || typeof window.localStorage === 'undefined') {
    return
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    if (stored) {
      const parsed = JSON.parse(stored)
      notifications.value = hydrate(parsed.notifications)
      notificationId.value = parsed.lastId || 0
    }
  } catch (error) {
    // Storage corrupto: se arranca con notificaciones vacías
  }
}

// Guardar notificaciones en localStorage
const saveToStorage = () => {
  if (typeof window === 'undefined' || typeof window.localStorage === 'undefined') {
    return
  }
  try {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        notifications: notifications.value,
        lastId: notificationId.value
      })
    )
  } catch (error) {
    // Sin persistencia las notificaciones siguen vivas en memoria
  }
}

// Cargar al inicializar — guarded for SSR per FF-013
if (typeof window !== 'undefined') {
  loadFromStorage()
}

export function useNotifications() {
  const addNotification = (message, type = 'success', options = {}) => {
    const key = options.key ?? options.dedupeKey ?? null
    const duration = durationFor(type, options)
    const persistent = options.persistent === true
    const title = options.title || getDefaultTitle(type)

    // La misma clave es el mismo hecho: se actualiza la fila viva y se
    // reinicia su cuenta en vez de apilar un aviso idéntico. Dos capas que
    // publican el mismo evento comparten clave y, por tanto, una sola fila.
    if (key !== null) {
      const existing = notifications.value.find(item => item.key === key)
      if (existing) {
        Object.assign(existing, {
          title,
          message,
          type,
          category: options.category || existing.category || 'system',
          read: false,
          timestamp: new Date().toISOString(),
          action: options.action || null,
          sound: options.sound !== false,
          persistent,
          duration,
          data: options.data || null,
          key,
          revision: (existing.revision ?? 0) + 1
        })

        // La fila actualizada vuelve al frente para que se lea en la pila.
        notifications.value = [
          existing,
          ...notifications.value.filter(item => item.id !== existing.id)
        ]
        saveToStorage()
        scheduleExpiry(existing)

        if (existing.sound && options.playSound !== false) {
          playSound(type)
        }

        return existing.id
      }
    }

    const id = ++notificationId.value
    const notification = {
      id,
      key,
      revision: 0,
      title,
      message,
      type,
      category: options.category || 'system',
      read: false,
      timestamp: new Date().toISOString(),
      action: options.action || null,
      sound: options.sound !== false, // Por defecto activado
      persistent,
      duration,
      data: options.data || null
    }

    notifications.value.unshift(notification)

    // Limitar número máximo de notificaciones
    if (notifications.value.length > MAX_NOTIFICATIONS) {
      for (const dropped of notifications.value.slice(MAX_NOTIFICATIONS)) {
        clearTimer(dropped.id)
      }
      notifications.value = notifications.value.slice(0, MAX_NOTIFICATIONS)
    }

    // Guardar en localStorage
    saveToStorage()

    // Reproducir sonido si está habilitado
    if (notification.sound && options.playSound !== false) {
      playSound(type)
    }

    scheduleExpiry(notification)

    return id
  }

  const markAsRead = id => {
    const notification = notifications.value.find(n => n.id === id)
    if (notification && !notification.read) {
      notification.read = true
      saveToStorage()
    }
  }

  const markAllAsRead = () => {
    notifications.value.forEach(n => {
      n.read = true
    })
    saveToStorage()
  }

  const getUnreadCount = computed(() => {
    return notifications.value.filter(n => !n.read).length
  })

  const getByCategory = category => {
    return notifications.value.filter(n => n.category === category)
  }

  const clearByCategory = category => {
    notifications.value.filter(n => n.category === category).forEach(n => clearTimer(n.id))
    notifications.value = notifications.value.filter(n => n.category !== category)
    saveToStorage()
  }

  const clearAll = () => {
    clearTimers()
    notifications.value = []
    saveToStorage()
  }

  const clearRead = () => {
    notifications.value.filter(n => n.read).forEach(n => clearTimer(n.id))
    notifications.value = notifications.value.filter(n => !n.read)
    saveToStorage()
  }

  const getDefaultTitle = type => {
    const titles = {
      success: 'Éxito',
      error: 'Error',
      warning: 'Advertencia',
      info: 'Información'
    }
    return titles[type] || 'Notificación'
  }

  const playSound = (type = 'info') => {
    try {
      // Solo reproducir si el usuario no está en modo silencioso
      if (typeof Audio !== 'undefined') {
        // Por ahora usamos un sonido genérico
        // En el futuro se pueden agregar archivos de sonido específicos
        const audio = new Audio()
        audio.volume = 0.3
        // Usar Web Audio API para generar un sonido simple
        const audioContext = new (window.AudioContext || window.webkitAudioContext)()
        const oscillator = audioContext.createOscillator()
        const gainNode = audioContext.createGain()

        oscillator.connect(gainNode)
        gainNode.connect(audioContext.destination)

        // Diferentes frecuencias según el tipo
        const frequencies = {
          success: 800,
          error: 400,
          warning: 600,
          info: 500
        }

        oscillator.frequency.value = frequencies[type] || 500
        oscillator.type = 'sine'
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime)
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2)

        oscillator.start(audioContext.currentTime)
        oscillator.stop(audioContext.currentTime + 0.2)
      }
    } catch (error) {
      // Silenciar errores de audio (puede fallar en algunos navegadores)
    }
  }

  /**
   * Slice 11 / T-08.12: register a `visibilitychange` listener that
   * triggers a saveToStorage() refresh when the user comes back to the
   * tab. This re-hydrates notifications written by another tab/process
   * without forcing a manual reload.
   *
   * The returned function unregisters the listener. Callers should
   * invoke the cleanup in `onUnmounted` to avoid leaking handlers.
   */
  const setupVisibilityAutoRefresh = () => {
    if (typeof document === 'undefined') {
      return () => {}
    }
    const handler = () => {
      if (document.visibilityState === 'visible') {
        loadFromStorage()
      }
    }
    document.addEventListener('visibilitychange', handler)
    return () => document.removeEventListener('visibilitychange', handler)
  }

  return {
    notifications,
    addNotification,
    removeNotification,
    markAsRead,
    markAllAsRead,
    getUnreadCount,
    getByCategory,
    clearByCategory,
    clearAll,
    clearRead,
    playSound,
    setupVisibilityAutoRefresh
  }
}
