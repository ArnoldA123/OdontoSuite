import { ref } from 'vue'
import { useApi } from './useApi'

/**
 * Sprint 3 (plan #11): composable para integracion con Mercado Pago.
 * Gestiona la carga del SDK JS de MP (desde CDN, no bundle) y la
 * creacion de preferencias de pago via backend.
 */
export function useMercadoPago () {
  const { post } = useApi()

  const loading = ref(false)
  const sdkLoaded = ref(false)
  const sdkError = ref(null)

  /**
   * Cargar SDK de Mercado Pago desde CDN.
   * Solo se carga una vez; cachea la promesa.
   */
  let sdkPromise = null

  const loadSdk = (publicKey) => {
    if (sdkLoaded.value) return Promise.resolve(window.MercadoPago)
    if (sdkPromise) return sdkPromise

    sdkPromise = new Promise((resolve, reject) => {
      // Si ya esta cargado (por otro componente)
      if (window.MercadoPago) {
        sdkLoaded.value = true
        resolve(window.MercadoPago)
        return
      }

      const script = document.createElement('script')
      script.src = 'https://sdk.mercadopago.com/js/v2'
      script.async = true
      script.onload = () => {
        try {
          window.MercadoPago.setPublishableKey(publicKey)
          sdkLoaded.value = true
          resolve(window.MercadoPago)
        } catch (err) {
          reject(err)
        }
      }
      script.onerror = () => {
        sdkError.value = 'No se pudo cargar el SDK de Mercado Pago'
        reject(new Error(sdkError.value))
      }
      document.head.appendChild(script)
    })

    return sdkPromise
  }

  /**
   * Crear preferencia de pago en el backend.
   * POST /api/payments/mercadopago/preference
   */
  const createPreference = async (transactionId) => {
    loading.value = true
    try {
      const response = await post('/api/payments/mercadopago/preference', {
        transaction_id: transactionId
      })
      return response.data
    } catch (err) {
      throw new Error(err.response?.data?.message || 'Error al crear preferencia de pago')
    } finally {
      loading.value = false
    }
  }

  /**
   * Inicializar CardPaymentBrick en un contenedor.
   * Devuelve el brick controller (para unmount, etc.)
   */
  const createBrick = async (preferenceId, publicKey, containerId, callbacks = {}) => {
    try {
      const mp = await loadSdk(publicKey)

      const bricksBuilder = mp.bricks()

      const settings = {
        initialization: {
          preferenceId,
        },
        customization: {
          visual: {
            style: {
              theme: 'default'
            }
          }
        },
        callbacks: {
          onReady: () => {
            callbacks.onReady?.()
          },
          onSubmit: ({ selectedPaymentMethod, formData }) => {
            callbacks.onSubmit?.(selectedPaymentMethod, formData)
          },
          onError: (error) => {
            callbacks.onError?.(error)
          }
        }
      }

      const brickController = await bricksBuilder.create(
        'payment',
        containerId,
        settings
      )

      return brickController
    } catch (err) {
      sdkError.value = err.message || 'Error al crear el brick de pago'
      throw err
    }
  }

  /**
   * Limpiar brick (eliminar del DOM)
   */
  const unmount = (containerId) => {
    const container = document.getElementById(containerId)
    if (container) {
      container.innerHTML = ''
    }
  }

  return {
    loading,
    sdkLoaded,
    sdkError,
    loadSdk,
    createPreference,
    createBrick,
    unmount
  }
}
