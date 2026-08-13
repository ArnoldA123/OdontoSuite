import { useToast } from './useToast.js'

export function useErrorHandler() {
  const { error: showError, warning: showWarning, info: showInfo } = useToast()

  const handleError = (err, customMessage = null) => {
    let message = customMessage

    if (!message) {
      if (err.response?.data?.message) {
        message = err.response.data.message
      } else if (err.response?.data?.errors) {
        // Handle validation errors
        const { errors } = err.response.data
        const firstError = Object.values(errors)[0]
        message = Array.isArray(firstError) ? firstError[0] : firstError
      } else if (err.message) {
        message = err.message
      } else {
        message = 'Ha ocurrido un error inesperado'
      }
    }

    // Show appropriate error based on status code
    const status = err.response?.status || err.status

    switch (status) {
      case 400:
        showWarning(message)
        break
      case 401:
        showError('Sesión expirada. Por favor, inicia sesión nuevamente.')
        // Redirect to login after a delay
        setTimeout(() => {
          window.location.href = '/login'
        }, 2000)
        break
      case 403:
        showError('No tienes permisos para realizar esta acción.')
        break
      case 404:
        showError('El recurso solicitado no fue encontrado.')
        break
      case 422:
        showWarning(message)
        break
      case 429:
        showError('Demasiadas solicitudes. Por favor, intenta más tarde.')
        break
      case 500:
        showError('Error del servidor. Por favor, contacta al administrador.')
        break
      default:
        showError(message)
    }
  }

  const handleValidationErrors = errors => {
    if (typeof errors === 'object' && errors !== null) {
      const firstError = Object.values(errors)[0]
      const message = Array.isArray(firstError) ? firstError[0] : firstError
      showWarning(message)
    } else {
      showWarning('Por favor, revisa los datos ingresados.')
    }
  }

  const handleNetworkError = () => {
    showError('Error de conexión. Verifica tu conexión a internet.')
  }

  const handleTimeoutError = () => {
    showError('La solicitud tardó demasiado. Por favor, intenta nuevamente.')
  }

  const withErrorHandling = async (asyncFn, customMessage = null) => {
    try {
      return await asyncFn()
    } catch (err) {
      handleError(err, customMessage)
      throw err
    }
  }

  return {
    handleError,
    handleValidationErrors,
    handleNetworkError,
    handleTimeoutError,
    withErrorHandling
  }
}
