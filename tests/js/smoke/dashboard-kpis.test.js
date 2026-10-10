import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// WU1 (D1/D3/D8) regression coverage: the daily-operations KPI strip renders
// exactly four cards (Citas hoy / Atendidas hoy / Por atender / Saldo de caja),
// the three day counts derive from the SAME todayAppointments array the agenda
// renders through the SAME status map the rows label, no comparison chip or
// reserved blank slot survives (the backend delta_label is broken), and the
// cash card keeps its existing session-balance behaviour.
const { getMock, makeAppointment, installPayload } = vi.hoisted(() => {
  const makeAppointment = (id, status = 'confirmed') => ({
    id,
    scheduled_at: `2026-10-04T${String(8 + id).padStart(2, '0')}:30:00-05:00`,
    status,
    patient: {
      id: 100 + id,
      full_name: `Paciente ${id}`,
      first_name: 'Paciente',
      last_name: `${id}`
    },
    appointment_type: { id: 3, name: 'Limpieza dental' },
    user: { id: 2, name: 'Dra. Carla Núñez' }
  })

  const getMock = vi.fn()

  const installPayload = (stats = {}, appointments = [], cashCurrent = null) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        return { data: stats }
      }
      if (url === '/api/dashboard/appointments-today') {
        return { data: appointments }
      }
      if (url === '/api/cash-register/current') {
        return { data: cashCurrent || {} }
      }
      return { data: [] }
    })
  }

  return { getMock, makeAppointment, installPayload }
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
import pageSource from '../../../resources/js/modules/dashboard/DashboardPage.vue?raw'

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

// Intl rendering uses U+00A0 between the currency glyph and the amount.
const normalize = value => value.replace(/\u00a0/g, ' ')

const kpiStrip = wrapper => wrapper.find('[aria-label="Resumen del día"]')

const DAY_STAT_KEYS = ['appointments-today', 'completed-today', 'pending-today']
const ALL_STAT_KEYS = [...DAY_STAT_KEYS, 'cash-balance']

describe('dashboard daily-operations KPI strip (WU1)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders exactly four KPI cards with the stable data-stat value hooks', async () => {
    installPayload({}, [makeAppointment(1)])

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    expect(strip.findAll('[data-stat-card]')).toHaveLength(4)

    for (const key of ALL_STAT_KEYS) {
      expect(strip.find(`[data-stat="${key}"]`).exists()).toBe(true)
      expect(strip.find(`[data-stat-card="${key}"]`).exists()).toBe(true)
    }

    // D2: historical metrics live in BI, not in the daily-operations strip.
    for (const gone of ['total-patients', 'total-appointments-month', 'total-income']) {
      expect(strip.find(`[data-stat-card="${gone}"]`).exists()).toBe(false)
    }

    wrapper.unmount()
  })

  it('derives Citas hoy from the agenda array, never from a second counter', async () => {
    installPayload({ appointments_today: 99, completed_today: 77, pending_confirmation: 55 }, [
      makeAppointment(1),
      makeAppointment(2),
      makeAppointment(3)
    ])

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    expect(strip.find('[data-stat="appointments-today"]').text()).toBe('3')

    wrapper.unmount()
  })

  it('derives Atendidas hoy and Por atender from the same status map the rows render', async () => {
    installPayload({}, [
      makeAppointment(1, 'completed'),
      makeAppointment(2, 'completed'),
      makeAppointment(3, 'cancelled'),
      makeAppointment(4, 'scheduled'),
      makeAppointment(5, 'in_consultation')
    ])

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    expect(strip.find('[data-stat="appointments-today"]').text()).toBe('5')
    expect(strip.find('[data-stat="completed-today"]').text()).toBe('2')
    // The honest remaining bucket: not completed and not cancelled.
    expect(strip.find('[data-stat="pending-today"]').text()).toBe('2')

    // The rows label the same statuses through the same map.
    const rowTexts = wrapper.findAll('[data-appointment-row]').map(row => row.text())
    expect(rowTexts.filter(text => text.includes('Completada'))).toHaveLength(2)
    expect(rowTexts.filter(text => text.includes('Cancelada'))).toHaveLength(1)
    expect(rowTexts.filter(text => text.includes('Programada'))).toHaveLength(1)
    expect(rowTexts.filter(text => text.includes('En Consulta'))).toHaveLength(1)

    wrapper.unmount()
  })

  it('renders one scope caption per card and reserves no blank slots', async () => {
    installPayload({}, [makeAppointment(1)])

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    for (const key of ALL_STAT_KEYS) {
      const card = strip.find(`[data-stat-card="${key}"]`)
      expect(card.findAll('[data-kpi-caption]')).toHaveLength(1)
    }

    const captions = {
      'appointments-today': 'Agenda del día',
      'completed-today': 'Completadas',
      'pending-today': 'Sin completar ni cancelar'
    }
    for (const [key, text] of Object.entries(captions)) {
      expect(strip.find(`[data-kpi-caption="${key}"]`).text()).toBe(text)
    }

    // D3: no reserved chip/caption slots anywhere in the strip.
    expect(strip.html()).not.toContain('min-h-6')
    expect(strip.findAll('.min-h-6')).toHaveLength(0)

    wrapper.unmount()
  })

  it('renders the uppercase eyebrow plus the big tabular number per card', async () => {
    installPayload({}, [makeAppointment(1)])

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    const eyebrows = {
      'appointments-today': 'Citas Hoy',
      'completed-today': 'Atendidas Hoy',
      'pending-today': 'Por Atender',
      'cash-balance': 'Saldo de Caja'
    }
    for (const [key, label] of Object.entries(eyebrows)) {
      const card = strip.find(`[data-stat-card="${key}"]`)
      const eyebrow = card.find('p.uppercase')
      expect(eyebrow.exists()).toBe(true)
      expect(eyebrow.text()).toBe(label)

      const number = card.find('p.tabular-nums')
      expect(number.exists()).toBe(true)
    }

    wrapper.unmount()
  })

  it('renders no comparison chips even when the backend sends comparisons', async () => {
    installPayload({
      comparisons: {
        appointments_today: { delta_label: '+4', period_label: 'vs lun 28 sep' },
        total_patients: { delta_label: '-100', period_label: 'nuevos este mes' },
        total_appointments_this_month: { delta_label: '+12', period_label: 'vs sep 5 (5 días)' }
      }
    })

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    // UiCard renders data-variant="glass", so no success/error/neutral pill
    // may appear inside the strip.
    expect(
      strip.findAll('[data-variant="success"], [data-variant="error"], [data-variant="neutral"]')
    ).toHaveLength(0)
    expect(strip.html()).not.toContain('delta_label')
    expect(strip.text()).not.toContain('vs lun 28 sep')
    expect(strip.text()).not.toContain('nuevos este mes')

    wrapper.unmount()
  })

  it('shows N/D in the day KPIs when the today resource fails', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') throw { status: 500 }
      return { data: [] }
    })

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    // Single source: when the source is down the KPIs say N/D, never a
    // fabricated zero.
    for (const key of DAY_STAT_KEYS) {
      expect(strip.find(`[data-stat="${key}"]`).text()).toBe('N/D')
    }

    wrapper.unmount()
  })

  it('renders no quick-action tiles and keeps their dead handlers and icons out of the source (D5)', async () => {
    const wrapper = await mountDashboard()

    // The block that re-listed sidebar navigation is gone at the DOM level.
    expect(wrapper.findAll('[data-action]')).toHaveLength(0)
    expect(wrapper.find('section[aria-label="Acciones rápidas"]').exists()).toBe(false)

    // Dead code removed with it: navigation handlers and icon imports.
    for (const deadCode of [
      'goToPatients',
      'goToProfessionals',
      'goToEnvironments',
      'goToBusinessIntelligence',
      'goToCalendar',
      'UsersIcon',
      'UserGroupIcon',
      'BuildingOfficeIcon',
      'ChartBarIcon'
    ]) {
      expect(pageSource).not.toContain(deadCode)
    }

    wrapper.unmount()
  })
})

// WU2 acceptance surface (D4 / R2): cash state lives on exactly ONE
// surface. The header pill is gone; the "Saldo de Caja" card renders the
// state line (data-cash-state), the session balance (data-stat="cash-balance")
// and the single "Ir a Caja" CTA. State AND balance derive from the
// useCashRegister composable; stats.cash_session never paints visible state.
describe('dashboard single cash surface (WU2)', () => {
  const openCurrent = {
    session: { id: 7, status: 'open', opened_at: '2026-10-05T08:30:00' },
    summary: { opening_amount: 100, total_income: 250, total_expenses: 50 }
  }
  const closedCurrent = {
    session: { id: 6, status: 'closed', opened_at: '2026-10-04T09:15:00' },
    summary: { opening_amount: 100, total_income: 250, total_expenses: 50 }
  }

  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('removes the header pill entirely: no data-cash-pill-state anywhere', async () => {
    installPayload({}, [], openCurrent)

    const wrapper = await mountDashboard()

    expect(wrapper.html()).not.toContain('data-cash-pill-state')
    expect(wrapper.findAll('[data-cash-pill]')).toHaveLength(0)
    expect(wrapper.find('[data-dashboard-header] [data-cash-state]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('renders the state line on the cash card through the data-cash-state hook', async () => {
    installPayload({}, [], openCurrent)

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    const state = card.find('[data-cash-state]')
    expect(state.exists()).toBe(true)
    expect(state.attributes('data-cash-state')).toBe('open')
    expect(state.text()).toBe('Abierta')

    wrapper.unmount()
  })

  it('sources state and balance from useCashRegister and ignores stats.cash_session (R2)', async () => {
    installPayload(
      {
        cash_session: { status: 'open', opened_at: '2026-10-05T09:15:00' },
        total_income: 999
      },
      [],
      closedCurrent
    )

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    const state = card.find('[data-cash-state]')
    expect(state.attributes('data-cash-state')).toBe('closed')
    expect(state.text()).toBe('Cerrada')
    expect(card.text()).not.toContain('Abierta')
    expect(card.text()).not.toContain('Apertura 09:15')
    expect(normalize(card.find('[data-stat="cash-balance"]').text())).toBe('S/ 300.00')

    wrapper.unmount()
  })

  it('renders Abierta, the opening caption and the live balance while a session is open', async () => {
    installPayload({}, [], openCurrent)

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    expect(card.text()).toContain('Saldo de Caja')
    expect(card.find('[data-cash-state]').attributes('data-cash-state')).toBe('open')
    expect(card.find('[data-kpi-caption="cash-balance"]').text()).toBe('Apertura 08:30')
    expect(normalize(card.find('[data-stat="cash-balance"]').text())).toBe('S/ 300.00')

    wrapper.unmount()
  })

  it('renders Sin sesión and N/D values when the composable has no session', async () => {
    installPayload({}, [], {})

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    expect(card.find('[data-cash-state]').attributes('data-cash-state')).toBe('no_session')
    expect(card.find('[data-cash-state]').text()).toBe('Sin sesión')
    expect(card.find('[data-stat="cash-balance"]').text()).toBe('N/D')
    expect(card.find('[data-kpi-caption="cash-balance"]').text()).toBe('N/D')

    wrapper.unmount()
  })

  it('renders Ir a Caja exactly once and only inside the cash card', async () => {
    installPayload({}, [], openCurrent)

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    const cashCtas = wrapper.findAll('button').filter(button => button.text().includes('Ir a Caja'))
    expect(cashCtas).toHaveLength(1)
    expect(card.find('button').text()).toContain('Ir a Caja')
    expect(wrapper.find('[data-dashboard-header]').text()).not.toContain('Ir a Caja')

    wrapper.unmount()
  })
})
