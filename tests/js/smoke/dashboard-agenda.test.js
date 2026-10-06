import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T2a regression coverage: the operational dashboard renders today's agenda
// as the page protagonist (every appointment returned, each row carrying the
// professional and appointment type) and surfaces the cash-session state plus
// the "Ir a Caja" action in the compact header.
const { getMock, makeAppointment, installTodayPayload } = vi.hoisted(() => {
  const makeAppointment = (id, fullName, extra = {}) => {
    const parts = fullName.split(' ')
    return {
      id,
      scheduled_at: `2026-10-04T${String(8 + id).padStart(2, '0')}:30:00-05:00`,
      status: 'confirmed',
      patient: {
        id: 100 + id,
        first_name: parts[0],
        last_name: parts.slice(1).join(' '),
        full_name: fullName
      },
      appointment_type: { id: 3, name: 'Limpieza dental' },
      user: { id: 2, name: 'Dra. Carla Núñez' },
      ...extra
    }
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

  return { getMock, makeAppointment, installTodayPayload }
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

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

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

describe('dashboard agenda (ops IA)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installTodayPayload([])
  })

  it('renders every appointment returned by the today fetch (no slice cap)', async () => {
    installTodayPayload([
      makeAppointment(1, 'Ana Torres Quispe'),
      makeAppointment(2, 'Luis Rojas'),
      makeAppointment(3, 'Marta Díaz'),
      makeAppointment(4, 'Pedro Salas'),
      makeAppointment(5, 'Rocío Campos')
    ])

    const wrapper = await mountDashboard()

    const rows = wrapper.findAll('[data-appointment-row]')
    expect(rows).toHaveLength(5)
    expect(rows[4].text()).toContain('Rocío Campos')

    wrapper.unmount()
  })

  it('renders the appointment type and the professional in each row', async () => {
    installTodayPayload([makeAppointment(1, 'Ana Torres Quispe')])

    const wrapper = await mountDashboard()

    const row = wrapper.find('[data-appointment-row]')
    expect(row.text()).toContain('Limpieza dental')
    expect(row.text()).toContain('Dra. Carla Núñez')

    wrapper.unmount()
  })

  it('shows the cash status pill and the Ir a Caja action in the header when the role can view cash', async () => {
    const wrapper = await mountDashboard()

    const header = wrapper.find('[data-dashboard-header]')
    expect(header.exists()).toBe(true)
    expect(header.find('[data-cash-pill]').exists()).toBe(true)
    expect(header.text()).toContain('Sin sesión')
    expect(header.text()).toContain('Ir a Caja')

    wrapper.unmount()
  })

  it('hides the cash status pill and Ir a Caja for a role without cash permission', async () => {
    signInAs('odontologo')

    const wrapper = await mountDashboard()

    const header = wrapper.find('[data-dashboard-header]')
    expect(header.exists()).toBe(true)
    expect(header.find('[data-cash-pill]').exists()).toBe(false)
    expect(header.text()).not.toContain('Ir a Caja')

    wrapper.unmount()
  })

  it('renders the agenda heading with its count and the Nueva cita action', async () => {
    installTodayPayload([makeAppointment(1, 'Ana Torres Quispe'), makeAppointment(2, 'Luis Rojas')])

    const wrapper = await mountDashboard()

    expect(wrapper.text()).toContain('Agenda de hoy')
    expect(wrapper.text()).toContain('2 citas')
    expect(wrapper.text()).toContain('Nueva cita')

    wrapper.unmount()
  })

  it('keeps the agenda empty state with the Crear nueva cita CTA', async () => {
    const wrapper = await mountDashboard()

    const empty = wrapper.find('[data-state="empty-appointments"]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('Crear nueva cita')

    wrapper.unmount()
  })
})
