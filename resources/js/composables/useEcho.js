import { ref, onUnmounted } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Instancia global de Echo
let echoInstance = null

// Estado reactivo de la conexion WebSocket (singleton - compartido por todos los consumidores)
// 'connecting' | 'connected' | 'disconnected' | 'unavailable'
const connectionStatus = ref('connecting')
const reconnectAttempts = ref(0)

// Configuracion por defecto
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

// Backoff exponencial: 5s, 10s, 20s, 40s, 60s (cap)
const getReconnectDelay = (attempt) => {
  return Math.min(5000 * Math.pow(2, attempt - 1), 60000)
}

const setStatus = (newStatus) => {
  connectionStatus.value = newStatus
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

        // Configurar eventos de conexion
        const pusher = echoInstance.connector.pusher

        pusher.connection.bind('error', (err) => {
          if (err.type === 'PusherError') {
            if (err.data?.code) {
              console.warn('[Echo] PusherError code:', err.data.code, err.data?.message)
            }
            if (err.data?.message) {
              console.warn('[Echo] PusherError message:', err.data.message)
            }
          }
          // Error de transporte (Reverb no disponible)
          if (err.type === 'TransportError' || err.data?.code === 1006) {
            console.warn('[Echo] TransportError - Reverb no disponible en', defaultConfig.wsHost + ':' + defaultConfig.wsPort)
          }
        })

        pusher.connection.bind('connected', () => {
          console.info('[Echo] Conectado a Reverb')
          reconnectAttempts.value = 0
          setStatus('connected')
        })

        pusher.connection.bind('disconnected', () => {
          console.warn('[Echo] Desconectado de Reverb')
          setStatus('disconnected')
        })

        pusher.connection.bind('state_change', (states) => {
          // Solo log en transiciones relevantes
          if (states.current === 'unavailable' || states.current === 'failed') {
            console.warn('[Echo] Estado:', states.previous, '->', states.current)
          }
          if (states.current === 'unavailable') {
            setStatus('unavailable')
          }
        })

        pusher.connection.bind('unavailable', () => {
          console.warn('[Echo] Conexion no disponible (Reverb caido o inalcanzable)')
          setStatus('unavailable')
        })

        // Reconexion con backoff exponencial
        pusher.connection.bind('failed', () => {
          reconnectAttempts.value++
          const delay = getReconnectDelay(reconnectAttempts.value)
          console.warn(`[Echo] Reconexion intento ${reconnectAttempts.value} en ${delay / 1000}s`)
          if (reconnectAttempts.value <= 10) {
            setTimeout(() => {
              if (echoInstance && pusher.connection.state !== 'connected') {
                pusher.connect()
              }
            }, delay)
          } else {
            console.error('[Echo] Max reintentos alcanzados (10). Reverb no se ha podido conectar.')
          }
        })
      } catch (error) {
        console.error('[Echo] Error al inicializar:', error.message)
        setStatus('unavailable')
      }
    }
  }

  // Funcion para suscribirse a un canal publico
  const channel = (channelName) => {
    if (!echoInstance) {
      return null
    }
    return echoInstance.channel(channelName)
  }

  // Funcion para suscribirse a un canal privado
  const privateChannel = (channelName) => {
    if (!echoInstance) {
      return null
    }
    // Actualizar token antes de suscribirse a canal privado
    updateAuthToken()
    return echoInstance.private(channelName)
  }

  // Funcion para obtener el token actualizado
  const getAuthToken = () => {
    return localStorage.getItem('auth_token') || ''
  }

  // Funcion para actualizar el token de autenticacion
  const updateAuthToken = (token) => {
    const authToken = token || getAuthToken()
    if (echoInstance && echoInstance.connector.options.auth) {
      echoInstance.connector.options.auth.headers.Authorization = `Bearer ${authToken}`
    }
    // Tambien actualizar la configuracion por defecto para futuras conexiones
    defaultConfig.auth.headers.Authorization = `Bearer ${authToken}`
  }

  // Actualizar token al inicializar
  if (echoInstance) {
    updateAuthToken()
  }

  // Funcion para desconectar Echo
  const disconnect = () => {
    if (echoInstance) {
      echoInstance.disconnect()
      echoInstance = null
    }
    setStatus('disconnected')
  }

  // Funcion para reconectar Echo (forzar reset)
  const reconnect = () => {
    if (echoInstance) {
      echoInstance.disconnect()
    }
    echoInstance = null
    reconnectAttempts.value = 0
    setStatus('connecting')
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
    // Estado reactivo compartido (singleton)
    connectionStatus,
    reconnectAttempts,
  }
}
