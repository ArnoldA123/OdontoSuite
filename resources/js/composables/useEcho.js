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
          console.error('❌ Echo connection error:', err)
          if (err.type === 'PusherError') {
            console.error('Detalles del error:', err.data)
            if (err.data?.code) {
              console.error('Código de error:', err.data.code)
            }
            if (err.data?.message) {
              console.error('Mensaje:', err.data.message)
            }
          }
          // Solo mostrar el mensaje de Reverb si el error indica que el servidor no está disponible
          if (err.type === 'TransportError' || err.data?.code === 1006) {
            console.warn('💡 Asegúrate de que el servidor Reverb esté corriendo: php artisan reverb:start')
          }
        })

        pusher.connection.bind('connected', () => {
          console.log('✅ Echo connected successfully')
        })

        pusher.connection.bind('disconnected', () => {
          console.warn('⚠️ Echo disconnected')
        })

        pusher.connection.bind('state_change', (states) => {
          console.log('🔄 Echo connection state changed:', states.previous, '->', states.current)
        })

        pusher.connection.bind('unavailable', () => {
          console.error('❌ Echo connection unavailable - El servidor Reverb no está disponible')
          console.warn('💡 Inicia el servidor Reverb con: php artisan reverb:start')
          console.warn('💡 O usa: composer run dev (inicia todo automáticamente)')
        })

        // Intentar reconectar automáticamente cada 5 segundos si falla
        let reconnectAttempts = 0
        const maxReconnectAttempts = 10

        pusher.connection.bind('failed', () => {
          reconnectAttempts++
          if (reconnectAttempts <= maxReconnectAttempts) {
            console.log(`🔄 Intentando reconectar... (${reconnectAttempts}/${maxReconnectAttempts})`)
            setTimeout(() => {
              if (echoInstance && pusher.connection.state !== 'connected') {
                pusher.connect()
              }
            }, 5000)
          } else {
            console.error('❌ Máximo de intentos de reconexión alcanzado')
            console.warn('💡 Por favor, verifica que el servidor Reverb esté corriendo')
          }
        })
      } catch (error) {
        console.error('❌ Error al inicializar Echo:', error)
        console.warn('💡 Verifica la configuración de Reverb en tu archivo .env')
      }
    }
  }

  // Función para suscribirse a un canal público
  const channel = (channelName) => {
    if (!echoInstance) {
      console.warn('Echo not initialized')
      return null
    }
    return echoInstance.channel(channelName)
  }

  // Función para suscribirse a un canal privado
  const privateChannel = (channelName) => {
    if (!echoInstance) {
      console.warn('Echo not initialized')
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

