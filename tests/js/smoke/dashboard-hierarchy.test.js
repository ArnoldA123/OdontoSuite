import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Hierarchy coverage (WU4 / U2): the dashboard reads as two blocks in order
// (Hoy -> Dónde seguir), the day's KPI strip leads block 1, the retired
// "Resumen del día" footer and the three appointment blocks are gone, and the
// header is a hero band carrying the login's display typography over its calm
// surface vocabulary.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = (stats = {}) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: stats }
      if (url === '/api/cash-register/current') {
        return {
          data: {
            session: { id: 7, status: 'open', opened_at: '2026-10-05T08:30:00' },
            summary: { opening_amount: 100, expected_amount: 280 }
          }
        }
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
  await flushPromises()
  return wrapper
}

const precedes = (first, second) =>
  Boolean(first && second) &&
  Boolean(first.element.compareDocumentPosition(second.element) & Node.DOCUMENT_POSITION_FOLLOWING)

describe('dashboard two-block hierarchy (U2)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders the two blocks in reading order with matching accessible names', async () => {
    const wrapper = await mountDashboard()

    const blocks = wrapper.findAll('[data-dashboard-block]')
    expect(blocks.map(block => block.attributes('data-dashboard-block'))).toEqual([
      'hoy',
      'destinations'
    ])
    expect(blocks.map(block => block.attributes('aria-label'))).toEqual(['Hoy', 'Dónde seguir'])

    wrapper.unmount()
  })

  it('groups the KPI strip under "Hoy" with the block heading first', async () => {
    const wrapper = await mountDashboard()

    const block = wrapper.find('[data-dashboard-block="hoy"]')
    expect(block.exists()).toBe(true)

    const heading = block.find('h2')
    expect(heading.text()).toBe('Hoy')

    const strip = block.find('[data-reveal="kpi"]')
    expect(strip.exists()).toBe(true)

    // Block heading -> KPI strip.
    expect(precedes(heading, strip)).toBe(true)
    expect(block.findAll('[data-stat-card]')).toHaveLength(2)

    wrapper.unmount()
  })

  it('retires the appointment blocks and the "Resumen del día" footer section (U2)', async () => {
    const wrapper = await mountDashboard()

    expect(wrapper.text()).not.toContain('Resumen del día')
    expect(wrapper.html()).not.toContain('aria-label="Resumen del día"')

    for (const retired of ['Agenda de hoy', 'Próximos días', 'Requiere tu atención']) {
      expect(wrapper.find(`section[aria-label="${retired}"]`).exists()).toBe(false)
    }
    expect(wrapper.find('[data-dashboard-block="attention"]').exists()).toBe(false)
    expect(wrapper.find('[data-dashboard-block="upcoming"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-appointment-row]')).toHaveLength(0)
    expect(wrapper.findAll('[data-upcoming-row]')).toHaveLength(0)
    expect(wrapper.findAll('[data-pending-row]')).toHaveLength(0)

    // Source-level: no element may carry the retired names.
    expect(pageSource).not.toMatch(/>\s*Resumen del día\s*</)
    expect(pageSource).not.toContain('aria-label="Agenda de hoy"')
    expect(pageSource).not.toContain('aria-label="Próximos días"')
    expect(pageSource).not.toContain('aria-label="Requiere tu atención"')

    wrapper.unmount()
  })

  it('keeps heading semantics sane (one visible h1, owned by the page header)', async () => {
    const wrapper = await mountDashboard()

    const content = wrapper.find('[data-dashboard-content]')
    const pageHeadings = content.findAll('h1')
    expect(pageHeadings).toHaveLength(1)
    expect(pageHeadings[0].element).toBe(content.find('[data-page-header-title]').element)

    const blockHeadings = content.findAll('h2').map(heading => heading.text())
    expect(blockHeadings).toEqual(['Hoy', 'Dónde seguir'])

    // The destination card titles are copy, not headings: the block owns the
    // only heading of that section.
    const destinations = wrapper.find('[data-dashboard-block="destinations"]')
    expect(destinations.findAll('h3')).toHaveLength(0)
    expect(destinations.text()).toContain('Agenda')
    expect(destinations.text()).toContain('Mi jornada')
    expect(destinations.text()).toContain('Pendientes')

    wrapper.unmount()
  })

  it('tiers both block headings on the same strongest rung', async () => {
    const wrapper = await mountDashboard()

    for (const hook of ['hoy', 'destinations']) {
      const heading = wrapper.find(`[data-dashboard-block="${hook}"] h2`)
      expect(heading.classes()).toEqual(
        expect.arrayContaining(['text-2xl', 'font-semibold', 'text-label'])
      )
    }

    wrapper.unmount()
  })
})

describe('dashboard hero band (D6 login language)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders the canonical page header with the greeting, the date and refresh', async () => {
    const wrapper = await mountDashboard()

    // The motion wrapper keeps the entrance hooks; the header itself is the
    // shared component, so the anatomy cannot drift again.
    const region = wrapper.find('[data-dashboard-header]')
    expect(region.exists()).toBe(true)
    expect(region.attributes('data-reveal')).toBe('greeting')
    expect(region.find('[data-refresh-button]').exists()).toBe(true)

    const header = wrapper.find('[data-page-header]')
    expect(header.exists()).toBe(true)
    expect(header.find('[data-page-header-kicker]').text()).toBe('HOY')

    const greeting = header.find('[data-page-header-title]')
    expect(greeting.element.tagName).toBe('H1')
    expect(greeting.text()).toContain('Ana')
    expect(greeting.classes()).toEqual(expect.arrayContaining(['text-4xl', 'font-semibold']))

    const date = header.find('[data-page-header-subtitle]')
    expect(date.exists()).toBe(true)
    expect(date.text()).toMatch(/\d{1,2} de /)

    wrapper.unmount()
  })

  it('keeps the brand lockup out of the page header', async () => {
    const wrapper = await mountDashboard()

    const header = wrapper.find('[data-page-header]')
    expect(header.text()).not.toContain('OdontoSuite')
    expect(header.html()).not.toContain('var(--color-accent-500)')

    wrapper.unmount()
  })

  it('paints the header with the canonical panel surface', async () => {
    const wrapper = await mountDashboard()

    const style = wrapper.find('[data-page-header]').attributes('style') || ''
    expect(style).toContain('var(--color-canvas)')
    expect(style).toContain('var(--color-hairline)')
    expect(style).toContain('var(--radius-panel)')
    expect(style).toContain('var(--elevation-2)')

    wrapper.unmount()
  })
})
