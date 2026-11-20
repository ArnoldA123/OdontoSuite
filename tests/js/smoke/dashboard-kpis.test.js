import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// The dashboard stopped listing appointments, so the daily-operations strip
// keeps the two numbers it can source honestly: today's appointment count
// (published by GET /api/dashboard/stats) and the drawer balance (from
// useCashRegister). No comparison chip, no reserved blank slot and no second
// counter survive.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = (stats = {}, cashCurrent = null) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        return { data: stats }
      }
      if (url === '/api/cash-register/current') {
        return { data: cashCurrent || {} }
      }
      return { data: [] }
    })
  }

  return { getMock, installPayload }
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

const kpiStrip = wrapper => wrapper.find('[data-reveal="kpi"]')

const ALL_STAT_KEYS = ['appointments-today', 'cash-balance']

describe('dashboard daily-operations KPI strip (WU1)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders only the two sourceable KPI cards with their stable hooks', async () => {
    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    expect(strip.findAll('[data-stat-card]')).toHaveLength(2)

    for (const key of ALL_STAT_KEYS) {
      expect(strip.find(`[data-stat="${key}"]`).exists()).toBe(true)
      expect(strip.find(`[data-stat-card="${key}"]`).exists()).toBe(true)
    }

    // The day buckets that read the retired appointment list are gone with it;
    // the historical metrics still live in BI (D2).
    for (const gone of [
      'completed-today',
      'pending-today',
      'total-patients',
      'total-appointments-month',
      'total-income'
    ]) {
      expect(strip.find(`[data-stat-card="${gone}"]`).exists()).toBe(false)
    }

    wrapper.unmount()
  })

  it('reads Citas hoy from the summary count, never from a second list', async () => {
    installPayload({ appointments_today: 99 })

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    expect(strip.find('[data-stat="appointments-today"]').text()).toBe('99')

    const requested = getMock.mock.calls.map(([url]) => url)
    expect(requested).toContain('/api/dashboard/stats')
    expect(requested).not.toContain('/api/dashboard/appointments-today')

    wrapper.unmount()
  })

  it('renders one scope caption per card and reserves no blank slots', async () => {
    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    for (const key of ALL_STAT_KEYS) {
      const card = strip.find(`[data-stat-card="${key}"]`)
      expect(card.findAll('[data-kpi-caption]')).toHaveLength(1)
    }

    expect(strip.find('[data-kpi-caption="appointments-today"]').text()).toBe('Agenda del día')

    // D3: no reserved chip/caption slots anywhere in the strip.
    expect(strip.html()).not.toContain('min-h-6')
    expect(strip.findAll('.min-h-6')).toHaveLength(0)

    wrapper.unmount()
  })

  it('renders the uppercase eyebrow plus the big tabular number per card', async () => {
    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    const eyebrows = {
      'appointments-today': 'Citas Hoy',
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

  it('shows N/D on the day count when the summary fails', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') throw { status: 500 }
      return { data: [] }
    })

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    // Single source: when the source is down the card says N/D, never a
    // fabricated zero.
    expect(strip.find('[data-stat="appointments-today"]').text()).toBe('N/D')

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
  // expected_amount (280) deliberately differs from opening + income - expenses
  // (300): the dashboard balance must read the position the drawer will be
  // counted against, not a formula recomputed in the browser.
  const openCurrent = {
    session: { id: 7, status: 'open', opened_at: '2026-10-05T08:30:00' },
    summary: {
      opening_amount: 100,
      total_income: 250,
      total_expenses: 50,
      cash_in: 260,
      cash_out: 80,
      expected_amount: 280
    }
  }
  const closedCurrent = {
    session: { id: 6, status: 'closed', opened_at: '2026-10-04T09:15:00' },
    summary: {
      opening_amount: 100,
      total_income: 250,
      total_expenses: 50,
      cash_in: 260,
      cash_out: 80,
      expected_amount: 280
    }
  }

  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('removes the header pill entirely: no data-cash-pill-state anywhere', async () => {
    installPayload({}, openCurrent)

    const wrapper = await mountDashboard()

    expect(wrapper.html()).not.toContain('data-cash-pill-state')
    expect(wrapper.findAll('[data-cash-pill]')).toHaveLength(0)
    expect(wrapper.find('[data-dashboard-header] [data-cash-state]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('renders the state line on the cash card through the data-cash-state hook', async () => {
    installPayload({}, openCurrent)

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
      closedCurrent
    )

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    const state = card.find('[data-cash-state]')
    expect(state.attributes('data-cash-state')).toBe('closed')
    expect(state.text()).toBe('Cerrada')
    expect(card.text()).not.toContain('Abierta')
    expect(card.text()).not.toContain('Apertura 09:15')
    expect(normalize(card.find('[data-stat="cash-balance"]').text())).toBe('S/ 280.00')

    wrapper.unmount()
  })

  it('renders Abierta, the opening caption and the live balance while a session is open', async () => {
    installPayload({}, openCurrent)

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    expect(card.text()).toContain('Saldo de Caja')
    expect(card.find('[data-cash-state]').attributes('data-cash-state')).toBe('open')
    expect(card.find('[data-kpi-caption="cash-balance"]').text()).toBe('Apertura 08:30')
    expect(normalize(card.find('[data-stat="cash-balance"]').text())).toBe('S/ 280.00')

    wrapper.unmount()
  })

  it('renders Sin sesión and N/D values when the composable has no session', async () => {
    installPayload({}, {})

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    expect(card.find('[data-cash-state]').attributes('data-cash-state')).toBe('no_session')
    expect(card.find('[data-cash-state]').text()).toBe('Sin sesión')
    expect(card.find('[data-stat="cash-balance"]').text()).toBe('N/D')
    expect(card.find('[data-kpi-caption="cash-balance"]').text()).toBe('Sin sesión abierta')

    wrapper.unmount()
  })

  it('renders Ir a Caja exactly once and only inside the cash card', async () => {
    installPayload({}, openCurrent)

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-balance"]')

    const cashCtas = wrapper.findAll('button').filter(button => button.text().includes('Ir a Caja'))
    expect(cashCtas).toHaveLength(1)
    expect(card.find('button').text()).toContain('Ir a Caja')
    expect(wrapper.find('[data-dashboard-header]').text()).not.toContain('Ir a Caja')

    wrapper.unmount()
  })
})
