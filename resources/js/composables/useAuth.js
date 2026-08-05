import { ref, computed } from 'vue'
import { useApi } from './useApi'

export function useAuth() {
  const { setToken, getHeaders, get, post } = useApi()

  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('auth_token'))
  const isLoading = ref(false)

  const isAuthenticated = computed(() => {
    return !!token.value && !!user.value
  })

  const login = async (credentials) => {
    isLoading.value = true
    try {
      const response = await post('/api/auth/login', credentials)

      if (response.data && response.data.token) {
        setToken(response.data.token)
        user.value = response.data.user
        localStorage.setItem('user', JSON.stringify(response.data.user))
        return response
      }
      throw new Error('Invalid response format')
    } catch (error) {
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const logout = async () => {
    try {
      if (token.value) {
        await post('/api/auth/logout')
      }
    } catch (error) {
    } finally {
      setToken(null)
      user.value = null
      localStorage.removeItem('user')
    }
  }

  // Slice 09 / UXF-021 — alias for `logout`. Centralized auth-flow callers
  // (e.g. PaymentModal on 401) use `authLogout()` to make the intent
  // explicit: this is the session-expiry-driven sign-out, not a user-
  // initiated logout click. Behaviourally identical to `logout`.
  const authLogout = logout

  const getCurrentUser = async () => {
    try {
      const response = await get('/api/auth/me')
      user.value = response.data
      localStorage.setItem('user', JSON.stringify(response.data))
      return response
    } catch (error) {
      throw error
    }
  }

  const hasRole = (role) => {
    return user.value?.role === role
  }

  const hasAnyRole = (roles) => {
    return roles.includes(user.value?.role)
  }

  return {
    user: computed(() => user.value),
    token: computed(() => token.value),
    isAuthenticated,
    isLoading: computed(() => isLoading.value),
    login,
    logout,
    authLogout,
    getCurrentUser,
    hasRole,
    hasAnyRole
  }
}
