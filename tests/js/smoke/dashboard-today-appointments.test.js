import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T1 regression coverage: DashboardPage must fetch today's appointments from
// the canonical `dashboard/appointments-today` route and render the
// AppointmentResource shape (patient.full_name + nested appointment_type).
const { getMock, todayAppointment, installTodayPayload } = vi.hoisted(() => {
  const todayAppointment = {
    id: 41,
    scheduled_at: '2026-10-04T10:30:00-05:00',
    status: 'confirmed',
    patient: {
      id: 9,
      first_name: 'Ana',
      last_name: 'Torres Quispe',
      full_name: 'Ana Torres Quispe'
    },
    appointment_type: { id: 3, name: 'Limpieza dental' }
  }

  const getMock = vi.fn()

  const installTodayPayload = payload => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/appointments-today') {
        return { data: payload }
      }
      if (url === '/api/dashboard/stats') {
        return { data: {} }
      }
      return { data: [] }
    })
  }

  return { getMock, todayAppointment, installTodayPayload }
})

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'

const mountDashboard = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

describe('dashboard today appointments', () => {
  beforeEach(() => {
    localStorage.setItem('auth_token', 'test-token')
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 1, name: 'Admin Prueba', role: 'administrador' })
    )
    getMock.mockReset()
    installTodayPayload([todayAppointment])
  })

  it('fetches from the canonical appointments-today endpoint', async () => {
    const wrapper = await mountDashboard()

    const urls = getMock.mock.calls.map(([url]) => url)
    expect(urls).toContain('/api/dashboard/appointments-today')
    expect(urls).not.toContain('/api/dashboard/today')

    wrapper.unmount()
  })

  it('renders the patient full_name from the AppointmentResource payload', async () => {
    const wrapper = await mountDashboard()

    const rows = wrapper.findAll('[data-appointment-row]')
    expect(rows).toHaveLength(1)
    expect(rows[0].text()).toContain('Ana Torres Quispe')
    expect(rows[0].text()).toContain('Limpieza dental')

    wrapper.unmount()
  })

  it('falls back to the composed first/last name when full_name is absent', async () => {
    installTodayPayload([
      {
        ...todayAppointment,
        patient: { id: 10, first_name: 'Luis', last_name: 'Rojas' }
      }
    ])

    const wrapper = await mountDashboard()

    const rows = wrapper.findAll('[data-appointment-row]')
    expect(rows).toHaveLength(1)
    expect(rows[0].text()).toContain('Luis Rojas')

    wrapper.unmount()
  })
})
