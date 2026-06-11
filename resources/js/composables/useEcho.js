import { ref, onUnmounted } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Instancia global de Echo
let echoInstance = null

// Configuración por defecto
const defaultConfig = {
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY || 'local-key',
  wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
  wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
  wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
  enabledTransports: ['ws', 'wss'],
  disableStats: true,
  authEndpoint: '/api/broadcasting/auth',
  auth: {
    headers: {
      Authorization: `Bearer ${localStorage.getItem('auth_token') || ''}`,
      Accept: 'application/json',
    },
  },
}

export function useEcho() {
  // Inicializar Echo si no existe
  if (!echoInstance) {
    if (typeof window !== 'undefined') {
      try {
        // Configurar Pusher en window
        window.Pusher = Pusher

        // Crear instancia de Echo
        echoInstance = new Echo(defaultConfig)

        // Configurar eventos de conexión
        const pusher = echoInstance.connector.pusher

        pusher.connection.bind('error', (err) => {
          if (err.type === 'PusherError') {
            if (err.data?.code) {
            }
            if (err.data?.message) {
            }
          }
          // Solo mostrar el mensaje de Reverb si el error indica que el servidor no está disponible
          if (err.type === 'TransportError' || err.data?.code === 1006) {
          }
        })

        pusher.connection.bind('connected', () => {
        })

        pusher.connection.bind('disconnected', () => {
        })

        pusher.connection.bind('state_change', (states) => {
        })

        pusher.connection.bind('unavailable', () => {
        })

        // Intentar reconectar automáticamente cada 5 segundos si falla
        let reconnectAttempts = 0
        const maxReconnectAttempts = 10

        pusher.connection.bind('failed', () => {
          reconnectAttempts++
          if (reconnectAttempts <= maxReconnectAttempts) {
            setTimeout(() => {
              if (echoInstance && pusher.connection.state !== 'connected') {
                pusher.connect()
              }
            }, 5000)
          } else {
          }
        })
      } catch (error) {
      }
    }
  }

  // Función para suscribirse a un canal público
  const channel = (channelName) => {
    if (!echoInstance) {
      return null
    }
    return echoInstance.channel(channelName)
  }

  // Función para suscribirse a un canal privado
  const privateChannel = (channelName) => {
    if (!echoInstance) {
      return null
    }
    // Actualizar token antes de suscribirse a canal privado
    updateAuthToken()
    return echoInstance.private(channelName)
  }

  // Función para obtener el token actualizado
  const getAuthToken = () => {
    return localStorage.getItem('auth_token') || ''
  }

  // Función para actualizar el token de autenticación
  const updateAuthToken = (token) => {
    const authToken = token || getAuthToken()
    if (echoInstance && echoInstance.connector.options.auth) {
      echoInstance.connector.options.auth.headers.Authorization = `Bearer ${authToken}`
    }
    // También actualizar la configuración por defecto para futuras conexiones
    defaultConfig.auth.headers.Authorization = `Bearer ${authToken}`
  }

  // Actualizar token al inicializar
  if (echoInstance) {
    updateAuthToken()
  }

  // Función para desconectar Echo
  const disconnect = () => {
    if (echoInstance) {
      echoInstance.disconnect()
      echoInstance = null
    }
  }

  // Función para reconectar Echo
  const reconnect = () => {
    if (echoInstance) {
      echoInstance.disconnect()
    }
    echoInstance = null
    return useEcho()
  }

  return {
    echo: echoInstance,
    channel,
    privateChannel,
    updateAuthToken,
    getAuthToken,
    disconnect,
    reconnect,
  }
}

