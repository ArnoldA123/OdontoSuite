import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T2a regression coverage: the operational dashboard renders today's agenda
// as the page protagonist (every appointment returned, each row carrying the
// professional and appointment type). WU2 / D4: the header carries no cash
// surface; the state and the "Ir a Caja" action live only on the cash card.
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

const kpiStrip = wrapper => wrapper.find('[data-reveal="kpi"]')

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

  it('renders no cash state or cash CTA in the header (D4: the pill is gone)', async () => {
    const wrapper = await mountDashboard()

    const header = wrapper.find('[data-dashboard-header]')
    expect(header.exists()).toBe(true)
    expect(header.find('[data-cash-pill]').exists()).toBe(false)
    expect(header.find('[data-cash-state]').exists()).toBe(false)
    expect(header.text()).not.toContain('Ir a Caja')
    expect(header.text()).not.toContain('Abierta')
    expect(header.text()).not.toContain('Sin sesión')

    // The single cash surface is the Saldo de Caja card.
    const card = wrapper.find('[data-stat-card="cash-balance"]')
    expect(card.exists()).toBe(true)
    expect(card.find('[data-cash-state]').exists()).toBe(true)
    expect(card.text()).toContain('Ir a Caja')

    wrapper.unmount()
  })

  it('hides the whole cash surface for a role without cash permission', async () => {
    signInAs('odontologo')

    const wrapper = await mountDashboard()

    const header = wrapper.find('[data-dashboard-header]')
    expect(header.exists()).toBe(true)
    expect(header.find('[data-cash-pill]').exists()).toBe(false)
    expect(wrapper.find('[data-stat-card="cash-balance"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Ir a Caja')

    wrapper.unmount()
  })

  it('renders the agenda heading without the appointment count (D8) and the Nueva cita action', async () => {
    installTodayPayload([makeAppointment(1, 'Ana Torres Quispe'), makeAppointment(2, 'Luis Rojas')])

    const wrapper = await mountDashboard()

    const agenda = wrapper.find('section[aria-label="Agenda de hoy"]')
    expect(agenda.find('h3').text()).toBe('Agenda de hoy')
    expect(wrapper.text()).toContain('Nueva cita')

    // D8 / R1: the daily count lives ONLY in the KPI.
    expect(agenda.text()).not.toMatch(/\d+\s+citas?\b/)
    expect(kpiStrip(wrapper).find('[data-stat="appointments-today"]').text()).toBe('2')

    wrapper.unmount()
  })

  it('renders the agenda empty state without a CTA and with informational copy (D5/R4)', async () => {
    const wrapper = await mountDashboard()

    const empty = wrapper.find('[data-state="empty-appointments"]')
    expect(empty.exists()).toBe(true)
    // The header owns the only "Nueva cita" CTA: the empty state must not
    // re-issue it, and its copy must not send users to "la sección de
    // calendario" (the CTA elsewhere opens the modal).
    expect(empty.find('button').exists()).toBe(false)
    expect(empty.find('[data-cta]').exists()).toBe(false)
    expect(empty.text()).not.toContain('Crear nueva cita')
    expect(empty.text()).not.toContain('sección de calendario')
    expect(empty.text()).toContain('Cuando registres citas, aparecerán aquí.')

    wrapper.unmount()
  })
})
