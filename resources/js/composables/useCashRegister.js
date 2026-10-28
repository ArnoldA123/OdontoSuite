import { ref, computed, watch, onUnmounted } from 'vue'
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

const normalizeSummary = payload => ({
  ...payload,
  opening_amount: Number(payload.opening_amount ?? 0),
  total_income: Number(payload.total_income ?? 0),
  total_expenses: Number(payload.total_expenses ?? 0),
  cash_in: Number(payload.cash_in ?? 0),
  cash_out: Number(payload.cash_out ?? 0),
  net_flow: Number(payload.net_flow ?? 0),
  expected_amount: Number(payload.expected_amount ?? 0),
  transactions_count: Number(payload.transactions_count ?? 0),
  movements_count: Number(payload.movements_count ?? 0),
  by_payment_method: Array.isArray(payload.by_payment_method) ? payload.by_payment_method : [],
  by_hour: Array.isArray(payload.by_hour) ? payload.by_hour : []
})

export function useCashRegister() {
  const { get, post } = useApi()
  const { echo, privateChannel } = useEcho()

  // Estado reactivo
  const currentSession = ref(null)
  const loading = ref(false)
  const error = ref(null)
  // #411 — the drawer reading owns its own flags: the lists that load after it
  // used to clear the same `error`, so the hero panel never painted.
  const sessionLoading = ref(false)
  const sessionError = ref(null)
  const summary = ref(null)
  // #407: the closure counts of a closed session; "mi caja" answers the
  // session payload plus its closing block, never a summary.
  const closing = ref(null)

  // Computed properties
  const isOpen = computed(() => currentSession.value?.status === 'open')
  const isClosed = computed(() => currentSession.value?.status === 'closed')
  const hasActiveSession = computed(() => !!currentSession.value)

  // Cargar sesión actual
  const loadCurrentSession = async () => {
    sessionLoading.value = true
    sessionError.value = null

    try {
      const response = await get('/api/cash-register/current')

      currentSession.value = response.data?.session || null
      summary.value = response.data?.summary ? normalizeSummary(response.data.summary) : null
      closing.value = response.data?.closing || null
    } catch (err) {
      sessionError.value = err.message || 'Error al cargar la sesión de caja'
      currentSession.value = null
      summary.value = null
      closing.value = null
    } finally {
      sessionLoading.value = false
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
      return { data: response.data || [], meta: response.meta || null }
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
    // #411 — a failed reading leaves the drawer state unknown, so the hero does
    // not offer a door built on a state it could not confirm.
    return !sessionError.value && !isOpen.value && !sessionLoading.value && !loading.value
  })

  // Validar si se puede cerrar caja
  const canClose = computed(() => {
    return isOpen.value && !sessionLoading.value && !loading.value
  })

  // Calcular totales en tiempo real
  const realTimeTotals = computed(() => {
    if (!summary.value) {
      return null
    }

    return {
      openingAmount: summary.value.opening_amount,
      totalIncome: summary.value.total_income,
      totalExpenses: summary.value.total_expenses,
      cashIn: summary.value.cash_in,
      cashOut: summary.value.cash_out,
      netFlow: summary.value.net_flow,
      expectedAmount: summary.value.expected_amount,
      currentBalance: summary.value.expected_amount
    }
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
      // Canal privado de caja (#249): el handshake de
      // /api/broadcasting/auth exige un rol de caja antes de entregar
      // sesiones, cobros y movimientos. Bound exactly once.
      cashRegisterChannel = privateChannel('cash-register')
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
          .listen('.transaction.updated', async e => {
            // Anular o editar una transacción cambia el saldo esperado de la
            // sesión, así que este evento también recarga el resumen. La
            // suscripción vive aquí desde que Business Intelligence dejó de
            // consumir el canal público (D11).
            const updatedSession = e.transaction?.cash_register_session_id
            if (!updatedSession || updatedSession === currentSession.value?.id) {
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
    } catch (error) {
      // Sin WebSocket la caja sigue operativa vía polling manual
    }
  }

  // Suscribirse al canal privado de la sesión
  const subscribeToSessionChannel = sessionId => {
    if (!sessionId) return

    try {
      cashSessionChannel = privateChannel(`cash-session.${sessionId}`)
      if (cashSessionChannel) {
        cashSessionChannel
          .listen('.payment.registered', async () => {
            await loadCurrentSession()
          })
          .listen('.cash-movement.created', async () => {
            await loadCurrentSession()
          })
          .listen('.transaction.created', async () => {
            await loadCurrentSession()
          })
          .listen('.transaction.updated', async () => {
            await loadCurrentSession()
          })
      }
    } catch (error) {
      // Sin canal privado la sesión sigue operativa vía canal público
    }
  }

  // Limpiar suscripciones WebSocket
  const cleanupWebSocketSubscriptions = () => {
    try {
      if (echo) {
        // Echo's leave() does not add the private prefix: name the wire
        // channel that was actually joined (#249). `subscribedSessionId`
        // still holds the session we are tearing down, because the watch
        // that calls this already moved `currentSession` to the new one.
        echo.leave('private-cash-register')
        if (subscribedSessionId) {
          echo.leave(`private-cash-session.${subscribedSessionId}`)
        }
      }
      cashSessionChannel = null
      cashRegisterChannel = null
      subscribedSessionId = null
      subscriberCount = 0
    } catch (error) {
      // La limpieza de canales es best-effort, el estado local ya se reseteó
    }
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
      const response = await get('/api/payment-methods/active')
      return response.data || []
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
      const response = await get('/api/branches/active')
      return response.data || []
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
    closing.value = null
    error.value = null
    sessionError.value = null
    cleanupWebSocketSubscriptions()
  }

  return {
    // Estado — Slice 08 / T-08.10 + T-08.11: standardised shape.
    currentSession,
    data: currentSession, // alias for { data } consumers (T-08.10)
    loading,
    error,
    sessionLoading,
    sessionError,
    summary,
    closing,

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
