import { ref, computed } from 'vue'
import { useApi } from './useApi'

export function useAuth() {
  const { setToken, getHeaders, request } = useApi()

  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('auth_token'))
  const isLoading = ref(false)

  const isAuthenticated = computed(() => {
    return !!token.value && !!user.value
  })

  const login = async (credentials) => {
    isLoading.value = true
    try {
      const response = await request('POST', '/api/auth/login', credentials)

      if (response.data && response.data.token) {
        setToken(response.data.token)
        user.value = response.data.user
        localStorage.setItem('user', JSON.stringify(response.data.user))
        return response
      }
      throw new Error('Invalid response format')
    } catch (error) {
      console.error('Login error:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const logout = async () => {
    try {
      if (token.value) {
        await request('POST', '/api/auth/logout')
      }
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      setToken(null)
      user.value = null
      localStorage.removeItem('user')
    }
  }

  const getCurrentUser = async () => {
    try {
      const response = await request('GET', '/api/auth/me')
      user.value = response.data
      localStorage.setItem('user', JSON.stringify(response.data))
      return response
    } catch (error) {
      console.error('Get current user error:', error)
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
    getCurrentUser,
    hasRole,
    hasAnyRole
  }
}
