import { useAuth } from '@/composables/useAuth'

/**
 * Slice 08 / FF-016: useAuth already exposes isAuthenticated as the single
 * source of truth for the SPA. Reads from localStorage used to create a
 * split-brain because useAuth.user / useAuth.token are SEPARATE refs that
 * can drift (e.g. setToken() updates one but not the other across a tab
 * sync). Centralizing the check on useAuth eliminates that drift.
 *
 * NOTE: useAuth is a factory that creates *new* refs per call. We invoke
 * it inside each guard so every navigation re-reads the canonical
 * isAuthenticated from the most recent logout / login cycle.
 */

export function requireAuth(to, from, next) {
  const { isAuthenticated } = useAuth()
  if (isAuthenticated.value) {
    next()
  } else {
    next('/login')
  }
}

export function requireGuest(to, from, next) {
  const { isAuthenticated } = useAuth()
  if (isAuthenticated.value) {
    next('/dashboard')
  } else {
    next()
  }
}
