import { ref, computed } from 'vue'

const notifications = ref([])
const notificationId = ref(0)
const STORAGE_KEY = 'odonto_suite_notifications'
const MAX_NOTIFICATIONS = 100

// Cargar notificaciones desde localStorage al iniciar
const loadFromStorage = () => {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored) {
      const parsed = JSON.parse(stored)
      notifications.value = parsed.notifications || []
      notificationId.value = parsed.lastId || 0
    }
  } catch (error) {
  }
}

// Guardar notificaciones en localStorage
const saveToStorage = () => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      notifications: notifications.value,
      lastId: notificationId.value
    }))
  } catch (error) {
  }
}

// Cargar al inicializar
loadFromStorage()

export function useNotifications() {
  const addNotification = (message, type = 'success', options = {}) => {
    const id = ++notificationId.value
    const notification = {
      id,
      title: options.title || getDefaultTitle(type),
      message,
      type,
      category: options.category || 'system',
      read: false,
      timestamp: new Date().toISOString(),
      action: options.action || null,
      sound: options.sound !== false, // Por defecto activado
      persistent: options.persistent || false,
      duration: options.duration || (type === 'error' ? 8000 : 5000),
      data: options.data || null
    }

    notifications.value.unshift(notification)

    // Limitar número máximo de notificaciones
    if (notifications.value.length > MAX_NOTIFICATIONS) {
      notifications.value = notifications.value.slice(0, MAX_NOTIFICATIONS)
    }

    // Guardar en localStorage
    saveToStorage()

    // Reproducir sonido si está habilitado
    if (notification.sound && options.playSound !== false) {
      playSound(type)
    }

    // Auto remove after duration (solo si no es persistente)
    if (!notification.persistent) {
      setTimeout(() => {
        removeNotification(id)
      }, notification.duration)
    }

    return id
  }

  const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index > -1) {
      notifications.value.splice(index, 1)
      saveToStorage()
    }
  }

  const markAsRead = (id) => {
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

  const getByCategory = (category) => {
    return notifications.value.filter(n => n.category === category)
  }

  const clearByCategory = (category) => {
    notifications.value = notifications.value.filter(n => n.category !== category)
    saveToStorage()
  }

  const clearAll = () => {
    notifications.value = []
    saveToStorage()
  }

  const clearRead = () => {
    notifications.value = notifications.value.filter(n => !n.read)
    saveToStorage()
  }

  const getDefaultTitle = (type) => {
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
    playSound
  }
}
