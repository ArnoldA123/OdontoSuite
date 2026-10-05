import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T4 coherence coverage. The KPI strip is a titled section like its siblings,
// its comparison chips render through the single pill primitive (UiBadge),
// the five cards carry no decorative status-like dot, and every caption
// states the period or scope of its number instead of repeating the eyebrow
// or the page date.
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

const chipCases = [
  {
    card: 'appointments-today',
    variant: 'success',
    tone: 'bg-systemGreen-100',
    delta: '+4',
    label: 'vs lun 28 sep'
  },
  {
    card: 'total-patients',
    variant: 'error',
    tone: 'bg-systemRed-100',
    delta: '-100',
    label: 'nuevos este mes'
  },
  {
    card: 'total-appointments-month',
    variant: 'success',
    tone: 'bg-systemGreen-100',
    delta: '+12',
    label: 'vs sep 5 (5 días)'
  }
]

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

describe('dashboard KPI comparison chips (T4)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders each delta through UiBadge with the sign-matching filled pill', async () => {
    installPayload(comparisonPayload)

    const wrapper = await mountDashboard()
    const section = kpiSection(wrapper)

    for (const { card: cardKey, variant, tone, delta, label } of chipCases) {
      const card = section.find(`[data-stat-card="${cardKey}"]`)
      expect(card.exists()).toBe(true)

      const badge = card.find(`[data-variant="${variant}"]`)
      expect(badge.exists()).toBe(true)
      expect(badge.classes()).toContain('rounded-full')
      expect(badge.classes()).toContain(tone)
      expect(badge.text().trim()).toBe(delta)

      // The muted period_label stays a plain sibling span outside the badge.
      const labelNode = Array.from(card.element.querySelectorAll('span')).find(
        node => node.textContent.trim() === label
      )
      expect(labelNode).toBeTruthy()
      expect(labelNode.closest(chipSelector)).toBeNull()
    }

    wrapper.unmount()
  })

  it('leaves exactly three UiBadge chips and no ad-hoc pill span in the strip', async () => {
    installPayload(comparisonPayload)

    const wrapper = await mountDashboard()
    const section = kpiSection(wrapper)

    // UiCard renders data-variant="glass", so the success/error variants are
    // the comparison chips only.
    expect(section.findAll(chipSelector)).toHaveLength(3)

    // One pill per chip, all of them the primitive's pill shape.
    const pillSpans = section
      .findAll('span')
      .filter(node => node.classes().includes('rounded-full'))
    expect(pillSpans).toHaveLength(3)

    // The hand-rolled chip signature is gone.
    expect(section.html()).not.toContain('text-xs font-semibold')

    wrapper.unmount()
  })

  it('renders no chip and keeps the reserved slot when comparisons are absent', async () => {
    const wrapper = await mountDashboard()
    const section = kpiSection(wrapper)

    expect(section.findAll(chipSelector)).toHaveLength(0)
    expect(section.findAll('.min-h-6')).toHaveLength(5)

    wrapper.unmount()
  })
})

describe('dashboard KPI card decoration (T4)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders zero decorative dots inside the five KPI cards', async () => {
    const wrapper = await mountDashboard()
    const cards = kpiSection(wrapper).findAll('[data-stat-card]')

    expect(cards).toHaveLength(5)
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

describe('dashboard KPI captions (T4)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('reserves the Citas Hoy caption slot and leaves it empty', async () => {
    const wrapper = await mountDashboard()
    const card = kpiSection(wrapper).find('[data-stat-card="appointments-today"]')
    const caption = card.find('[data-kpi-caption="appointments-today"]')

    expect(caption.exists()).toBe(true)
    expect(caption.text()).toBe('')
    expect(caption.classes()).toContain('h-4')

    // No short "5 de oct" date restates the page header's date anchor.
    expect(card.text()).not.toMatch(/\d{1,2} de [a-z]{3}/)

    wrapper.unmount()
  })

  it('shows the current month name as the Citas del Mes caption', async () => {
    const wrapper = await mountDashboard()
    const caption = kpiSection(wrapper).find('[data-kpi-caption="total-appointments-month"]')

    expect(caption.exists()).toBe(true)

    const monthName = new Date().toLocaleDateString('es-ES', { month: 'long' })
    expect(caption.text()).toBe(monthName.charAt(0).toUpperCase() + monthName.slice(1))
    expect(caption.text()).toMatch(/^[A-ZÁÉÍÓÚ][a-záéíóúñ]+$/)
    expect(caption.text()).not.toBe('Este mes')

    wrapper.unmount()
  })

  it('keeps the scope captions on Pacientes and Ingresos', async () => {
    const wrapper = await mountDashboard()

    const expected = { 'total-patients': 'Total registrados', 'total-income': 'Total histórico' }
    for (const [cardKey, text] of Object.entries(expected)) {
      const caption = kpiSection(wrapper).find(`[data-kpi-caption="${cardKey}"]`)
      expect(caption.exists()).toBe(true)
      expect(caption.text()).toBe(text)
    }

    wrapper.unmount()
  })
})
