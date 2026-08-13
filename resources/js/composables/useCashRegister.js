import { ref, computed, watch, onUnmounted, nextTick, triggerRef } from 'vue'
import { useApi } from './useApi'
import { useEcho } from './useEcho'

// Slice 08 / FF-007: promote the channel refs to MODULE scope so multiple
// `useCashRegister()` callers (CashRegisterPage, DashboardPage, the app
// shell indicator) share one subscription instead of stacking duplicate
// listeners on every mount.
let cashRegisterChannel = null
let cashSessionChannel = null
let subscribedSessionId = null
let subscriberCount = 0

export function useCashRegister() {
  const { get, post } = useApi()
  const { channel, privateChannel } = useEcho()

  // Estado reactivo
  const currentSession = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const summary = ref(null)
  const summaryUpdateKey = ref(0) // Key para forzar actualización

  // Computed properties
  const isOpen = computed(() => currentSession.value?.status === 'open')
  const isClosed = computed(() => currentSession.value?.status === 'closed')
  const hasActiveSession = computed(() => !!currentSession.value)

  // Cargar sesión actual
  const loadCurrentSession = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await get('/api/cash-register/current')

      // Verificar estructura de respuesta

      currentSession.value = response.data?.session || null

      // Asegurar que el resumen se asigne correctamente
      if (response.data?.summary) {
        // Crear un objeto completamente nuevo para forzar reactividad
        const newSummary = {
          session: response.data.summary.session,
          opening_amount: parseFloat(response.data.summary.opening_amount || 0),
          total_income: parseFloat(response.data.summary.total_income || 0),
          total_expenses: parseFloat(response.data.summary.total_expenses || 0),
          total_movements: parseFloat(response.data.summary.total_movements || 0),
          expected_amount: parseFloat(response.data.summary.expected_amount || 0),
          transactions_count: parseInt(response.data.summary.transactions_count || 0),
          movements_count: parseInt(response.data.summary.movements_count || 0),
          by_payment_method: response.data.summary.by_payment_method || {},
          by_hour: response.data.summary.by_hour || {}
        }

        // Asignar el nuevo resumen - forzar nueva referencia
        summary.value = null // Limpiar primero
        await nextTick()
        summary.value = newSummary // Asignar nuevo objeto
        summaryUpdateKey.value++ // Incrementar key para forzar reactividad

        // Forzar actualización de Vue con nextTick y triggerRef
        await nextTick()
        triggerRef(summary)
      } else {
        summary.value = null
      }
    } catch (err) {
      error.value = err.message || 'Error al cargar la sesión de caja'
      currentSession.value = null
      summary.value = null
    } finally {
      loading.value = false
    }
  }

  // Abrir sesión de caja
  const openSession = async data => {
    loading.value = true
    error.value = null

    try {
      const response = await post('/api/cash-register/open', data)
      currentSession.value = response.data
      await loadCurrentSession() // Recargar con resumen
      return response.data
    } catch (err) {
      error.value = err.message || 'Error al abrir la sesión de caja'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Cerrar sesión de caja
  const closeSession = async data => {
    loading.value = true
    error.value = null

    try {
      const response = await post('/api/cash-register/close', data)
      currentSession.value = response.data
      return response.data
    } catch (err) {
      error.value = err.message || 'Error al cerrar la sesión de caja'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Obtener resumen de caja
  const getSummary = async (filters = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await get('/api/cash-register/summary', { params: filters })
      summary.value = response.data
      return response.data
    } catch (err) {
      error.value = err.message || 'Error al obtener el resumen de caja'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Obtener sesiones de caja
  const getSessions = async (filters = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await get('/api/cash-register/sessions', { params: filters })
      // Slice 01 / T-01.7 (API-011): backend returns {data, meta}. Return the
      // unwrapped data array so callers can iterate `.sessions`-like lists.
      return response.data || response
    } catch (err) {
      error.value = err.message || 'Error al obtener las sesiones de caja'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Obtener detalles de sesión
  const getSessionDetails = async sessionId => {
    loading.value = true
    error.value = null

    try {
      const response = await get(`/api/cash-register/sessions/${sessionId}`)
      return response.data
    } catch (err) {
      error.value = err.message || 'Error al obtener los detalles de la sesión'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Validar si se puede abrir caja
  const canOpen = computed(() => {
    return !hasActiveSession.value && !loading.value
  })

  // Validar si se puede cerrar caja
  const canClose = computed(() => {
    return isOpen.value && !loading.value
  })

  // Calcular totales en tiempo real
  const realTimeTotals = computed(() => {
    if (!summary.value) {
      return null
    }

    const totals = {
      openingAmount: parseFloat(summary.value.opening_amount || 0),
      totalIncome: parseFloat(summary.value.total_income || 0),
      totalExpenses: parseFloat(summary.value.total_expenses || 0),
      totalMovements: parseFloat(summary.value.total_movements || 0),
      expectedAmount: parseFloat(summary.value.expected_amount || 0),
      currentBalance:
        parseFloat(summary.value.opening_amount || 0) +
        parseFloat(summary.value.total_income || 0) -
        parseFloat(summary.value.total_expenses || 0)
    }

    return totals
  })

  // Forzar actualización de datos
  const forceRefresh = async () => {
    if (hasActiveSession.value) {
      await loadCurrentSession()
    }
  }

  // Configurar WebSocket subscriptions (singleton)
  const setupWebSocketSubscriptions = () => {
    // Slice 08 / FF-007: previously this function called cleanup+rebind
    // on every mount, stacking duplicate listeners when both DashboardPage
    // and CashRegisterPage consumed the composable. Now it guards on the
    // active session id so the second caller reuses the same channel and
    // the listeners are bound exactly once.
    const sessionId = currentSession.value?.id || null
    if (subscribedSessionId === sessionId && cashRegisterChannel) {
      // Nothing to do — already subscribed for this session.
      subscriberCount += 1
      return
    }

    // Session changed (or first time): tear down the previous session's
    // private channel if any and bind fresh listeners.
    cleanupWebSocketSubscriptions()

    try {
      // Canal público para caja registradora — bound exactly once.
      cashRegisterChannel = channel('cash-register')
      if (cashRegisterChannel) {
        cashRegisterChannel
          .listen('.cash-session.opened', async e => {
            if (e.session?.id === currentSession.value?.id) {
              await loadCurrentSession()
            }
          })
          .listen('.cash-session.closed', async e => {
            if (e.session?.id === currentSession.value?.id) {
              await loadCurrentSession()
            }
          })
          .listen('.payment.registered', async e => {
            // Recargar resumen si la transacción pertenece a la sesión actual
            if (
              e.session_id === currentSession.value?.id ||
              e.transaction?.cash_register_session_id === currentSession.value?.id
            ) {
              await loadCurrentSession()
            }
          })
          .listen('.cash-movement.created', async e => {
            // Recargar resumen si el movimiento pertenece a la sesión actual
            if (
              e.session_id === currentSession.value?.id ||
              e.movement?.cash_register_session_id === currentSession.value?.id
            ) {
              await loadCurrentSession()
            }
          })
          .listen('.transaction.created', async e => {
            // Recargar resumen si la transacción pertenece a la sesión actual
            if (e.transaction?.cash_register_session_id === currentSession.value?.id) {
              await loadCurrentSession()
            }
          })
      }

      // Canal privado para la sesión específica si hay sesión activa
      if (sessionId) {
        subscribeToSessionChannel(sessionId)
      }

      subscribedSessionId = sessionId
      subscriberCount = Math.max(subscriberCount, 1)
    } catch (error) {}
  }

  // Suscribirse al canal privado de la sesión
  const subscribeToSessionChannel = sessionId => {
    if (!sessionId) return

    try {
      cashSessionChannel = privateChannel(`cash-session.${sessionId}`)
      if (cashSessionChannel) {
        cashSessionChannel
          .listen('.payment.registered', async e => {
            await loadCurrentSession()
          })
          .listen('.cash-movement.created', async e => {
            await loadCurrentSession()
          })
          .listen('.transaction.created', async e => {
            await loadCurrentSession()
          })
      }
    } catch (error) {}
  }

  // Limpiar suscripciones WebSocket
  const cleanupWebSocketSubscriptions = () => {
    try {
      if (cashSessionChannel && currentSession.value?.id) {
        // Laravel Echo maneja automáticamente el leave cuando se desuscribe
        cashSessionChannel = null
      }
      // Public cash-register channel is intentionally kept because it
      // outlives the active session: the SPA wants to know about
      // session open/close events even when no session is active.
      cashRegisterChannel = null
      subscribedSessionId = null
      subscriberCount = 0
    } catch (error) {}
  }

  // Watch para suscribirse cuando hay sesión activa
  watch(
    () => currentSession.value?.id,
    sessionId => {
      if (sessionId) {
        subscribeToSessionChannel(sessionId)
        setupWebSocketSubscriptions()
      } else {
        cleanupWebSocketSubscriptions()
      }
    }
  )

  // Slice 08 / FF-007: only tear down when the LAST subscriber unmounts.
  // CashRegisterPage on /cash-register AND DashboardPage on /dashboard
  // both call useCashRegister(); both must decrement before we leave the
  // channel.
  onUnmounted(() => {
    subscriberCount = Math.max(0, subscriberCount - 1)
    if (subscriberCount === 0) {
      cleanupWebSocketSubscriptions()
    }
  })

  // Obtener pacientes
  // getPatients removido - ahora se usan solo pacientes con pagos pendientes

  // Obtener métodos de pago
  const getPaymentMethods = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await get('/api/payment-methods')
      return response
    } catch (err) {
      error.value = err.message || 'Error al obtener los métodos de pago'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Obtener sucursales
  const getBranches = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await get('/api/branches')
      return response
    } catch (err) {
      error.value = err.message || 'Error al obtener las sucursales'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Limpiar estado
  const clearState = () => {
    currentSession.value = null
    summary.value = null
    error.value = null
    cleanupWebSocketSubscriptions()
  }

  return {
    // Estado — Slice 08 / T-08.10 + T-08.11: standardised shape.
    currentSession,
    data: currentSession, // alias for { data } consumers (T-08.10)
    loading,
    error,
    summary,

    // Computed
    isOpen,
    isClosed,
    hasActiveSession,
    canOpen,
    canClose,
    realTimeTotals,

    // Métodos
    loadCurrentSession,
    openSession,
    closeSession,
    getSummary,
    getSessions,
    getSessionDetails,
    getPaymentMethods,
    getBranches,
    forceRefresh,
    clearState,
    setupWebSocketSubscriptions,

    // Slice 08 / T-08.10 + T-08.11: refresh + retry aliases. Components
    // and the AppLayout retry button can wire a single handler
    // regardless of which composable owns the data.
    refresh: loadCurrentSession,
    retry: loadCurrentSession
  }
}
