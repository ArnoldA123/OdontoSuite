import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// WU1 coherence coverage. The KPI strip is a titled section like its
// siblings, it renders NO comparison chip and NO reserved blank slot (the
// backend delta_label is broken, D3), the four cards carry no decorative
// status-like dot, and every caption states the scope of its number instead
// of repeating the eyebrow or the page date.
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

const kpiSection = wrapper => wrapper.find('section[aria-label="Resumen del día"]')

const comparisonPayload = {
  comparisons: {
    appointments_today: { delta_label: '+4', period_label: 'vs lun 28 sep' },
    total_patients: { delta_label: '-100', period_label: 'nuevos este mes' },
    total_appointments_this_month: { delta_label: '+12', period_label: 'vs sep 5 (5 días)' }
  }
}

const chipSelector = '[data-variant="success"], [data-variant="error"], [data-variant="neutral"]'

describe('dashboard KPI section headings (T4)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders the KPI strip h2 with the sibling title anatomy and a coherent accessible name', async () => {
    const wrapper = await mountDashboard()
    const section = kpiSection(wrapper)

    expect(section.exists()).toBe(true)

    const heading = section.find('h2')
    expect(heading.exists()).toBe(true)
    expect(heading.text()).toBe('Resumen del día')
    expect(heading.classes()).toEqual(
      expect.arrayContaining(['text-base', 'font-semibold', 'text-label'])
    )

    // The visible title is the section's accessible name.
    expect(section.attributes('aria-label')).toBe(heading.text())

    // The heading sits at the top of the section, above the card grid.
    const grid = section.element.querySelector('[data-reveal="kpi"]')
    expect(grid).toBeTruthy()
    expect(
      heading.element.compareDocumentPosition(grid) & Node.DOCUMENT_POSITION_FOLLOWING
    ).toBeTruthy()

    wrapper.unmount()
  })

  it('keeps every sibling h2 and drops the title-case Acciones Rápidas', async () => {
    const wrapper = await mountDashboard()

    const quickHeading = wrapper.find('section[aria-label="Acciones rápidas"]').find('h2')
    expect(quickHeading.text()).toBe('Acciones rápidas')
    expect(quickHeading.classes()).toEqual(
      expect.arrayContaining(['text-base', 'font-semibold', 'text-label'])
    )
    expect(wrapper.text()).not.toContain('Acciones Rápidas')

    // The siblings that render for this payload keep the same anatomy and
    // their existing copy (the source-level presence of all four section
    // headings is pinned by DashboardAppShellTest).
    for (const label of ['Agenda de hoy', 'Próximas citas']) {
      const section = wrapper.find(`section[aria-label="${label}"]`)
      expect(section.exists()).toBe(true)
      expect(section.find('h2').text()).toBe(label)
    }

    wrapper.unmount()
  })
})

describe('dashboard KPI comparison chips are gone (D3)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders no chip and no reserved slot even when comparisons are present', async () => {
    installPayload(comparisonPayload)

    const wrapper = await mountDashboard()
    const section = kpiSection(wrapper)

    // UiCard renders data-variant="glass", so any success/error/neutral
    // variant inside the strip would be a comparison pill.
    expect(section.findAll(chipSelector)).toHaveLength(0)
    expect(section.findAll('.min-h-6')).toHaveLength(0)
    expect(section.html()).not.toContain('delta_label')
    expect(section.html()).not.toContain('text-xs font-semibold')

    wrapper.unmount()
  })

  it('renders no chip and no reserved slot when comparisons are absent', async () => {
    const wrapper = await mountDashboard()
    const section = kpiSection(wrapper)

    expect(section.findAll(chipSelector)).toHaveLength(0)
    expect(section.findAll('.min-h-6')).toHaveLength(0)

    wrapper.unmount()
  })
})

describe('dashboard KPI card decoration (T4)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders zero decorative dots inside the four KPI cards', async () => {
    const wrapper = await mountDashboard()
    const cards = kpiSection(wrapper).findAll('[data-stat-card]')

    expect(cards).toHaveLength(4)
    for (const card of cards) {
      expect(card.findAll('span[aria-hidden="true"]')).toHaveLength(0)
      expect(card.html()).not.toContain('--color-accent-500')
    }

    // The header cash-status dot is the only dot left with meaning.
    expect(wrapper.findAll('span.w-1\\.5')).toHaveLength(1)
    expect(wrapper.find('[data-dashboard-header] span.w-1\\.5').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('dashboard KPI captions (WU1)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('states the scope of each number in exactly one caption line', async () => {
    const wrapper = await mountDashboard()
    const section = kpiSection(wrapper)

    const expected = {
      'appointments-today': 'Agenda del día',
      'completed-today': 'Completadas',
      'pending-today': 'Sin completar ni cancelar'
    }
    for (const [cardKey, text] of Object.entries(expected)) {
      const card = section.find(`[data-stat-card="${cardKey}"]`)
      const captions = card.findAll('[data-kpi-caption]')
      expect(captions).toHaveLength(1)
      expect(captions[0].text()).toBe(text)
    }

    // No caption restates the page header's date anchor.
    expect(section.text()).not.toMatch(/\d{1,2} de [a-z]{3}/)

    wrapper.unmount()
  })

  it('keeps the retired historical captions out of the strip', async () => {
    const wrapper = await mountDashboard()
    const section = kpiSection(wrapper)

    for (const gone of [
      'data-kpi-caption="total-patients"',
      'data-kpi-caption="total-appointments-month"',
      'data-kpi-caption="total-income"'
    ]) {
      expect(section.html()).not.toContain(gone)
    }
    expect(section.find('[data-kpi-caption="cash-balance"]').exists()).toBe(true)

    wrapper.unmount()
  })
})
