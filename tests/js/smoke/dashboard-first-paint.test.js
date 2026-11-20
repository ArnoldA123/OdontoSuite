import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #387 (S2) - the dashboard's first frame is the skeleton, never a partially
// painted page; the cash session never gates the summary. U2: the page no
// longer owns appointment lists, so it must not request the retired endpoints.
const harness = vi.hoisted(() => ({
  get: vi.fn(),
  loadCurrentSession: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.get,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => {
  const chainable = () => {
    const channel = { listen: () => channel }
    return channel
  }

  return {
    useEcho: () => ({
      echo: {},
      channel: chainable,
      privateChannel: chainable,
      connectionStatus: { value: 'connected' }
    })
  }
})
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

// The cash composable is the gate the fix removes: its session load is held
// open (row 2) so the summary must still be requested.
vi.mock('../../../resources/js/composables/useCashRegister', async () => {
  const { computed, ref } = await import('vue')
  const currentSession = ref(null)
  const realTimeTotals = ref(null)

  return {
    useCashRegister: () => ({
      currentSession,
      hasActiveSession: computed(() => currentSession.value !== null),
      isOpen: computed(() => currentSession.value?.status === 'open'),
      realTimeTotals,
      loadCurrentSession: harness.loadCurrentSession
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const makeRouter = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/dashboard')
  await router.isReady()
  return router
}

// Synchronous mount on purpose: the first-frame rows must observe the very
// first render, before any microtask resolves a request. The router is built
// separately so no `await` sits between mount() and the first assertion.
const mountDashboard = router => mount(Page, { global: { plugins: [router, uiComponents] } })

const requestedUrls = () => harness.get.mock.calls.map(([url]) => url)

const REMOVED_ENDPOINTS = [
  '/api/dashboard/appointments-today',
  '/api/dashboard/upcoming',
  '/api/dashboard/pending'
]

const installDashboardPayload = () => {
  harness.get.mockImplementation(async url => {
    if (url === '/api/dashboard/stats') return { data: {} }
    return { data: [] }
  })
}

describe('dashboard first paint (#387)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    signInAs('administrador')
    harness.loadCurrentSession.mockResolvedValue(undefined)
    installDashboardPayload()
  })

  it('renders the first-load skeleton on the first frame, never a partial page', async () => {
    const router = await makeRouter()
    const wrapper = mountDashboard(router)

    // No flush yet: this is the first frame.
    expect(wrapper.find('[aria-label="Cargando resumen"]').exists()).toBe(true)
    expect(wrapper.find('[data-dashboard-content]').exists()).toBe(false)
    expect(wrapper.html()).not.toContain('data-state="empty-')

    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[data-dashboard-content]').exists()).toBe(true)
    expect(wrapper.find('[aria-label="Cargando resumen"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('loads the summary while the cash session is still in flight', async () => {
    // The cash session never settles: the summary must not wait on it.
    harness.loadCurrentSession.mockImplementation(() => new Promise(() => {}))

    const router = await makeRouter()
    const wrapper = mountDashboard(router)
    await flushPromises()

    expect(harness.loadCurrentSession).toHaveBeenCalledTimes(1)
    expect(requestedUrls()).toContain('/api/dashboard/stats')

    wrapper.unmount()
  })

  it('never requests the retired appointment endpoints', async () => {
    const router = await makeRouter()
    const wrapper = mountDashboard(router)
    await flushPromises()
    await flushPromises()

    for (const endpoint of REMOVED_ENDPOINTS) {
      expect(requestedUrls()).not.toContain(endpoint)
    }

    wrapper.unmount()
  })

  it('never asks for the cash session from a role without view.cash_register', async () => {
    signInAs('odontologo')

    const router = await makeRouter()
    const wrapper = mountDashboard(router)

    expect(wrapper.find('[aria-label="Cargando resumen"]').exists()).toBe(true)
    expect(harness.loadCurrentSession).not.toHaveBeenCalled()

    await flushPromises()
    await flushPromises()

    expect(harness.loadCurrentSession).not.toHaveBeenCalled()
    expect(requestedUrls()).not.toContain('/api/cash-register/current')
    expect(wrapper.find('[data-stat-card="cash-balance"]').exists()).toBe(false)

    wrapper.unmount()
  })
})
