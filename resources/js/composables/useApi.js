import { ref } from 'vue'

const baseURL = import.meta.env.VITE_APP_URL || window.location.origin
const token = ref(localStorage.getItem('auth_token'))
const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))

export function useApi() {
  const setToken = (newToken) => {
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
      'Accept': 'application/json'
    }

    if (token.value) {
      headers['Authorization'] = `Bearer ${token.value}`
    } else {
    }

    return headers
  }

  const handleResponse = async (response) => {
    if (response.status === 401) {
      // Token expirado o inválido
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      window.location.href = '/login'
      throw { response: { data: { message: 'Sesión expirada' } }, status: 401 }
    }

    // Check if response is HTML (error page) instead of JSON
    const contentType = response.headers.get('content-type') || ''
    if (!contentType.includes('application/json')) {
      // Clone the response to read it without consuming the original
      const clonedResponse = response.clone()
      const text = await clonedResponse.text()

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
            message: 'El servidor devolvió una respuesta inesperada (HTML en lugar de JSON). Por favor, verifica la configuración del servidor.'
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

  const post = async (url, data, options = {}) => {
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
        'Accept': 'application/json'
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

  return {
    get,
    post,
    put,
    patch,
    delete: del,
    setToken
  }
}

// useAuth() fue eliminado de este archivo (I-1, Sprint 2 del plan maestro de
// inconsistencias). Ahora se importa únicamente desde '@/composables/useAuth'.
// La versión duplicada acá creaba un split-brain: el `user`/`isAuthenticated`
// de acá era una INSTANCIA SEPARADA de los refs del useAuth.js canónico, así
// que la mitad de la app veía al usuario logueado y la otra mitad no.


