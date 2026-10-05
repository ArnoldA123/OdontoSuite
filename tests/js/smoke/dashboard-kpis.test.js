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

  const installPayload = (stats = {}, appointments = []) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        return { data: stats }
      }
      if (url === '/api/dashboard/appointments-today') {
        return { data: appointments }
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

  it('renders every quick action tile through @heroicons/vue outline icons', async () => {
    const wrapper = await mountDashboard()

    const tiles = wrapper.findAll('[data-action]')
    expect(tiles).toHaveLength(5)

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
    expect(glyphs.size).toBe(5)

    wrapper.unmount()
  })
})
