import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T2b regression coverage: the compact KPI strip renders the Ingresos card
// from stats.total_income through the shared PEN formatter, drops the
// admin-only Profesionales card, and every quick action tile renders its
// icon through an @heroicons/vue 24-outline component instead of an inline
// SVG. Those three behaviours are the T2b acceptance surface.
const { getMock, installPayload } = vi.hoisted(() => {
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

describe('dashboard compact KPI strip (ops IA, T2b)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders the Ingresos KPI from stats.total_income with the PEN label format', async () => {
    installPayload({ total_income: 1234.5 })

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="total-income"]')

    expect(card.exists()).toBe(true)
    expect(normalize(card.text())).toContain('S/ 1,234.50')
    expect(normalize(card.text())).not.toContain('1234.5')

    wrapper.unmount()
  })

  it('renders the Ingresos KPI as S/ 0.00 when the stats payload has no income', async () => {
    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="total-income"]')

    expect(card.exists()).toBe(true)
    expect(normalize(card.text())).toContain('S/ 0.00')

    wrapper.unmount()
  })

  it('drops the Profesionales KPI card from the strip', async () => {
    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    expect(strip.findAll('[data-stat-card]')).toHaveLength(5)
    expect(strip.find('[data-stat-card="total-professionals"]').exists()).toBe(false)
    expect(strip.text()).not.toContain('Profesionales')

    wrapper.unmount()
  })

  it('keeps the Profesionales KPI card out of the strip for a non-admin role too', async () => {
    signInAs('odontologo')

    const wrapper = await mountDashboard()
    const strip = kpiStrip(wrapper)

    expect(strip.find('[data-stat-card="total-professionals"]').exists()).toBe(false)
    expect(strip.text()).not.toContain('Profesionales')

    wrapper.unmount()
  })

  it('renders every comparison period_label in full with a wrapping chip row instead of truncation', async () => {
    installPayload({
      comparisons: {
        appointments_today: { delta_label: '+4', period_label: 'vs lun 28 sep' },
        total_patients: { delta_label: '-100', period_label: 'nuevos este mes' },
        total_appointments_this_month: { delta_label: '+12', period_label: 'vs sep 5 (5 días)' }
      }
    })

    const wrapper = await mountDashboard()

    const chips = [
      { card: 'appointments-today', label: 'vs lun 28 sep' },
      { card: 'total-patients', label: 'nuevos este mes' },
      { card: 'total-appointments-month', label: 'vs sep 5 (5 días)' }
    ]

    for (const { card: cardKey, label } of chips) {
      const card = kpiStrip(wrapper).find(`[data-stat-card="${cardKey}"]`)
      expect(card.exists()).toBe(true)

      // The full backend label must render verbatim (no ellipsis cut).
      const labelNode = Array.from(card.element.querySelectorAll('span')).find(
        node => node.textContent.trim() === label
      )
      expect(labelNode).toBeTruthy()

      // The label node must not carry any single-line truncation class.
      for (const truncation of ['truncate', 'text-ellipsis', 'overflow-hidden']) {
        expect(labelNode.classList.contains(truncation)).toBe(false)
      }

      // Structural contract: the chip row wraps the label onto a second
      // line and reserves only a minimum height, so the wrapped line can
      // never be clipped by a fixed-height slot.
      const chipRow = labelNode.parentElement
      expect(chipRow.classList.contains('flex-wrap')).toBe(true)
      expect(chipRow.classList.contains('min-h-6')).toBe(true)
      expect(chipRow.classList.contains('h-6')).toBe(false)
    }

    wrapper.unmount()
  })

  it('renders every quick action tile through @heroicons/vue outline icons', async () => {
    const wrapper = await mountDashboard()

    const tiles = wrapper.findAll('[data-action]')
    expect(tiles).toHaveLength(4)

    const glyphs = new Set()
    for (const tile of tiles) {
      const svg = tile.find('svg')
      expect(svg.exists()).toBe(true)
      expect(svg.attributes('data-slot')).toBe('icon')
      expect(svg.attributes('xmlns')).toBe('http://www.w3.org/2000/svg')
      const path = svg.find('path')
      expect(path.exists()).toBe(true)
      glyphs.add(path.attributes('d'))
    }
    expect(glyphs.size).toBe(4)

    wrapper.unmount()
  })
})

// T2 acceptance surface: the cash KPI card owns the session balance as its
// headline number and the opening time as its caption; the header pill is the
// single session-state surface (no duplicated data-cash-pill hook).
describe('dashboard cash KPI (T2)', () => {
  const openStats = {
    cash_session: { status: 'open', id: 7, opened_at: '2026-10-05T08:30:00' }
  }
  const openCurrent = {
    session: { id: 7, status: 'open', opened_at: '2026-10-05T08:30:00' },
    summary: { opening_amount: 100, total_income: 250, total_expenses: 50 }
  }

  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders the live session balance as the cash card number', async () => {
    installPayload(openStats, [], openCurrent)

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-status"]')

    expect(card.exists()).toBe(true)
    const number = card.find('p.tabular-nums')
    expect(number.exists()).toBe(true)
    expect(normalize(number.text())).toBe('S/ 300.00')

    wrapper.unmount()
  })

  it('drops the pill from the KPI card so the header keeps the only data-cash-pill', async () => {
    installPayload(openStats, [], openCurrent)

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat="cash-status"]')

    expect(card.find('[data-cash-pill]').exists()).toBe(false)
    expect(wrapper.findAll('[data-cash-pill]')).toHaveLength(1)
    expect(wrapper.find('[data-dashboard-header] [data-cash-pill]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('shows the Saldo de Caja eyebrow and the opening-time caption while the session is open', async () => {
    installPayload(openStats, [], openCurrent)

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-status"]')

    expect(card.text()).toContain('Saldo de Caja')
    expect(card.text()).not.toContain('Estado de Caja')
    expect(card.text()).toContain('Apertura 08:30')

    wrapper.unmount()
  })

  it('shows an em dash and the Sin sesión abierta caption when no session is open', async () => {
    installPayload({ cash_session: { status: 'closed' } })

    const wrapper = await mountDashboard()
    const card = kpiStrip(wrapper).find('[data-stat-card="cash-status"]')

    const number = card.find('p.tabular-nums')
    expect(number.exists()).toBe(true)
    expect(number.text()).toBe('N/D')
    expect(card.text()).toContain('Sin sesión abierta')
    expect(card.find('[data-cash-pill]').exists()).toBe(false)
    expect(wrapper.findAll('[data-cash-pill]')).toHaveLength(1)

    wrapper.unmount()
  })

  it('lets the UiBadge variant own the header pill text color', async () => {
    installPayload(openStats, [], openCurrent)

    const wrapper = await mountDashboard()
    const pill = wrapper.find('[data-dashboard-header] [data-cash-pill]')

    expect(pill.classes()).toContain('text-systemGreen-700')
    expect(pill.classes()).not.toContain('text-systemGreen-600')

    wrapper.unmount()
  })
})
