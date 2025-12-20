export function requireAuth(to, from, next) {
  const token = localStorage.getItem('auth_token')
  const user = localStorage.getItem('user')

  if (token && user) {
    next()
  } else {
    next('/login')
  }
}

export function requireGuest(to, from, next) {
  const token = localStorage.getItem('auth_token')
  const user = localStorage.getItem('user')

  if (token && user) {
    next('/dashboard')
  } else {
    next()
  }
}
