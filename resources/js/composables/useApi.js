import { ref } from 'vue'

const baseURL = import.meta.env.VITE_APP_URL || window.location.origin
const token = ref(localStorage.getItem('auth_token'))

export function useApi() {
  const setToken = newToken => {
    token.value = newToken
    if (newToken) {
      localStorage.setItem('auth_token', newToken)
    } else {
      localStorage.removeItem('auth_token')
    }
  }

  const getHeaders = () => {
    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json'
    }

    if (token.value) {
      headers['Authorization'] = `Bearer ${token.value}`
    }

    return headers
  }

  const handleResponse = async response => {
    if (response.status === 401) {
      // Token expirado o inválido. NO redirigir si ya estamos en /login
      // (los overlays del login disparan fetches sin token y el redirect
      // causa un loop infinito de recargas en la misma página).
      const isOnLogin = typeof window !== 'undefined' && window.location.pathname === '/login'
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      if (!isOnLogin) {
        window.location.href = '/login'
      }
      throw { response: { data: { message: 'Sesión expirada' } }, status: 401 }
    }

    // Check if response is HTML (error page) instead of JSON
    const contentType = response.headers.get('content-type') || ''
    if (!contentType.includes('application/json')) {
      // Clone the response to read it without consuming the original
      const clonedResponse = response.clone()
      await clonedResponse.text()

      // If it's an error status, provide more context
      if (!response.ok) {
        throw {
          response: {
            data: {
              message: `Error del servidor (${response.status}): El servidor devolvió HTML en lugar de JSON. Verifica que la ruta API sea correcta.`
            }
          },
          status: response.status
        }
      }

      throw {
        response: {
          data: {
            message:
              'El servidor devolvió una respuesta inesperada (HTML en lugar de JSON). Por favor, verifica la configuración del servidor.'
          }
        },
        status: response.status
      }
    }

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Error desconocido' }))
      throw { response: { data: error }, status: response.status }
    }

    return response.json()
  }

  const get = async (url, options = {}) => {
    let fullUrl = url.startsWith('http') ? url : `${baseURL}${url}`

    // Handle query parameters
    if (options.params) {
      const params = new URLSearchParams(options.params)
      fullUrl += `?${params}`
    }

    const response = await fetch(fullUrl, {
      method: 'GET',
      headers: getHeaders()
    })

    return handleResponse(response)
  }

  const post = async (url, data) => {
    const fullUrl = url.startsWith('http') ? url : `${baseURL}${url}`

    // Detectar si es FormData
    const isFormData = data instanceof FormData

    const fetchOptions = {
      method: 'POST',
      body: isFormData ? data : JSON.stringify(data)
    }

    // Si es FormData, no establecer Content-Type (el navegador lo hace automáticamente)
    // Si no es FormData, usar los headers normales
    if (isFormData) {
      fetchOptions.headers = {
        Accept: 'application/json'
      }
      if (token.value) {
        fetchOptions.headers['Authorization'] = `Bearer ${token.value}`
      }
    } else {
      fetchOptions.headers = getHeaders()
    }

    const response = await fetch(fullUrl, fetchOptions)
    return handleResponse(response)
  }

  const put = async (url, data) => {
    const fullUrl = url.startsWith('http') ? url : `${baseURL}${url}`
    const response = await fetch(fullUrl, {
      method: 'PUT',
      headers: getHeaders(),
      body: JSON.stringify(data)
    })

    return handleResponse(response)
  }

  const patch = async (url, data) => {
    const fullUrl = url.startsWith('http') ? url : `${baseURL}${url}`
    const response = await fetch(fullUrl, {
      method: 'PATCH',
      headers: getHeaders(),
      body: JSON.stringify(data)
    })

    return handleResponse(response)
  }

  const del = async (url, options = {}) => {
    const fullUrl = url.startsWith('http') ? url : `${baseURL}${url}`
    const fetchOptions = {
      method: 'DELETE',
      headers: getHeaders()
    }

    // Some DELETE callsites (e.g. useSpecialtyRecords.deleteRecord) need to
    // send a request body. Slice 01 / T-01.5 fixes the wrapper to forward
    // options.data so fetch passes it along.
    if (options.data !== undefined && options.data !== null) {
      fetchOptions.body = JSON.stringify(options.data)
    }

    const response = await fetch(fullUrl, fetchOptions)

    return handleResponse(response)
  }

  /**
   * Read the filename a download response advertises. RFC 5987's
   * `filename*=UTF-8''...` form wins over the plain `filename=` fallback
   * because it is the only one that survives non-ASCII names.
   */
  const filenameFromDisposition = contentDisposition => {
    if (!contentDisposition) {
      return ''
    }

    const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i)
    if (utf8Match) {
      try {
        return decodeURIComponent(utf8Match[1].trim())
      } catch (decodeError) {
        return utf8Match[1].trim()
      }
    }

    const asciiMatch = contentDisposition.match(/filename="?([^";]+)"?/i)
    return asciiMatch ? asciiMatch[1].trim() : ''
  }

  /**
   * T9 - export downloads ride the same auth headers as the rest of the
   * client instead of re-reading `localStorage` at the call site. Returns the
   * blob plus the server filename and rejects with the normalized shape the
   * other verbs throw, so `normalizeError` can read it unchanged.
   */
  const download = async (url, options = {}) => {
    let fullUrl = url.startsWith('http') ? url : `${baseURL}${url}`

    if (options.params) {
      const params = new URLSearchParams(options.params).toString()
      if (params) {
        fullUrl += `${fullUrl.includes('?') ? '&' : '?'}${params}`
      }
    }

    const response = await fetch(fullUrl, {
      method: options.method || 'GET',
      headers: { ...getHeaders(), ...(options.headers || {}) }
    })

    if (response.status === 401) {
      const isOnLogin = typeof window !== 'undefined' && window.location.pathname === '/login'
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      if (!isOnLogin) {
        window.location.href = '/login'
      }
      throw { response: { data: { message: 'Sesión expirada' } }, status: 401 }
    }

    if (!response.ok) {
      const contentType = response.headers.get('content-type') || ''
      let payload = {}

      if (contentType.includes('application/json')) {
        payload = await response.json().catch(() => ({}))
      }

      if (!payload || !payload.message) {
        payload = { message: `No se pudo descargar el archivo (${response.status}).` }
      }

      throw { response: { data: payload }, status: response.status }
    }

    const blob = await response.blob()
    const filename =
      filenameFromDisposition(response.headers.get('content-disposition')) ||
      options.fallbackFilename ||
      'descarga'

    return { blob, filename }
  }

  /**
   * Slice 11 / T-08.14: normalize an error thrown by any of the useApi
   * verbs into a Spanish-localized user-facing string. The order of
   * preference matches what consumers have been hand-rolling so the
   * migration is drop-in.
   *
   *   1. err.response.data.errors (Laravel 422 first error)
   *   2. err.response.data.message
   *   3. err.response.data.meta.message
   *   4. err.message
   *   5. fallback
   */
  const normalizeError = (err, fallback = 'Ha ocurrido un error inesperado') => {
    if (!err) {
      return fallback
    }
    const data = err.response && err.response.data
    if (data) {
      if (data.errors) {
        const firstKey = Object.keys(data.errors)[0]
        if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
          return String(data.errors[firstKey][0])
        }
      }
      if (data.message) {
        return String(data.message)
      }
      if (data.meta && data.meta.message) {
        return String(data.meta.message)
      }
    }
    if (err.message) {
      return String(err.message)
    }
    return fallback
  }

  return {
    get,
    post,
    put,
    patch,
    delete: del,
    download,
    setToken,
    normalizeError
  }
}

// useAuth() fue eliminado de este archivo (I-1, Sprint 2 del plan maestro de
// inconsistencias). Ahora se importa únicamente desde '@/composables/useAuth'.
// La versión duplicada acá creaba un split-brain: el `user`/`isAuthenticated`
// de acá era una INSTANCIA SEPARADA de los refs del useAuth.js canónico, así
// que la mitad de la app veía al usuario logueado y la otra mitad no.
