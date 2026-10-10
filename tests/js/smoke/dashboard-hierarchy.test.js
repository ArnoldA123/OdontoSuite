import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// WU4 hierarchy coverage (D7 / D6 / D10): the dashboard reads as three
// blocks in order (Hoy -> Requiere tu atención -> Próximos días), the day's
// KPI strip leads block 1 before the agenda, the retired "Resumen del día"
// footer section is gone, and the header is a hero band carrying the
// login's display typography over its calm mint surface vocabulary. Block 3
// stays visually secondary (quieter heading, recessed surface).
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = ({ today = [], upcoming = [], pending = {} } = {}) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: today }
      if (url === '/api/dashboard/upcoming') return { data: upcoming }
      if (url === '/api/dashboard/pending') return { data: pending }
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

const appointmentRow = (id, scheduledAt, fullName) => {
  const parts = fullName.split(' ')
  return {
    id,
    scheduled_at: scheduledAt,
    status: 'confirmed',
    patient: {
      id: 100 + id,
      first_name: parts[0],
      last_name: parts.slice(1).join(' '),
      full_name: fullName
    },
    appointment_type: { id: 3, name: 'Limpieza dental' },
    user: { id: 2, name: 'Dra. Carla Núñez' }
  }
}

const localIso = (year, monthIndex, day) => new Date(year, monthIndex, day, 10, 0).toISOString()

const fullPayload = () => ({
  today: [
    appointmentRow(1, '2026-10-05T09:30:00-05:00', 'Ana Torres Quispe'),
    appointmentRow(2, '2026-10-05T11:30:00-05:00', 'Luis Rojas')
  ],
  upcoming: [
    appointmentRow(3, '2026-10-06T10:00:00-05:00', 'Marta Díaz'),
    appointmentRow(4, '2026-10-08T09:00:00-05:00', 'Pedro Ruiz')
  ],
  pending: {
    quotations: {
      count: 1,
      items: [
        {
          id: 11,
          patient_name: 'Ana Torres Quispe',
          total_amount: 149.9,
          status: 'sent',
          created_at: localIso(2026, 9, 3)
        }
      ]
    },
    treatment_plans: {
      count: 1,
      items: [
        {
          id: 21,
          patient_name: 'Marta Díaz',
          final_cost: 249.5,
          status: 'proposed',
          created_at: localIso(2026, 9, 2)
        }
      ]
    }
  }
})

const precedes = (first, second) =>
  Boolean(first && second) &&
  Boolean(first.element.compareDocumentPosition(second.element) & Node.DOCUMENT_POSITION_FOLLOWING)

describe('dashboard three-block hierarchy (D7)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload(fullPayload())
  })

  it('renders exactly three blocks in reading order with matching accessible names', async () => {
    const wrapper = await mountDashboard()

    const blocks = wrapper.findAll('[data-dashboard-block]')
    expect(blocks.map(block => block.attributes('data-dashboard-block'))).toEqual([
      'hoy',
      'attention',
      'upcoming'
    ])
    expect(blocks.map(block => block.attributes('aria-label'))).toEqual([
      'Hoy',
      'Requiere tu atención',
      'Próximos días'
    ])

    wrapper.unmount()
  })

  it('groups the KPI strip and the agenda under "Hoy", strip first', async () => {
    const wrapper = await mountDashboard()

    const block = wrapper.find('[data-dashboard-block="hoy"]')
    expect(block.exists()).toBe(true)

    const heading = block.find('h2')
    expect(heading.text()).toBe('Hoy')

    const strip = block.find('[data-reveal="kpi"]')
    const agenda = block.find('section[aria-label="Agenda de hoy"]')
    expect(strip.exists()).toBe(true)
    expect(agenda.exists()).toBe(true)

    // Block heading -> KPI strip -> agenda: the day's numbers lead.
    expect(precedes(heading, strip)).toBe(true)
    expect(precedes(strip, agenda)).toBe(true)
    expect(block.findAll('[data-stat-card]')).toHaveLength(4)
    expect(agenda.findAll('[data-appointment-row]')).toHaveLength(2)

    wrapper.unmount()
  })

  it('retires the "Resumen del día" footer section (D7 / IA1)', async () => {
    const wrapper = await mountDashboard()

    expect(wrapper.text()).not.toContain('Resumen del día')
    expect(wrapper.html()).not.toContain('aria-label="Resumen del día"')
    // Source-level: no element may carry the retired name. Design-record
    // comments may still name it (WU3 guards match rendered text for the
    // same reason).
    expect(pageSource).not.toContain('aria-label="Resumen del día"')
    expect(pageSource).not.toMatch(/>\s*Resumen del día\s*</)

    wrapper.unmount()
  })

  it('keeps heading semantics sane (topbar owns the single h1)', async () => {
    const wrapper = await mountDashboard()

    const content = wrapper.find('[data-dashboard-content]')
    expect(content.find('h1').exists()).toBe(false)

    const hoy = wrapper.find('[data-dashboard-block="hoy"]')
    const attention = wrapper.find('[data-dashboard-block="attention"]')
    const upcoming = wrapper.find('[data-dashboard-block="upcoming"]')
    expect(hoy.find('h2').text()).toBe('Hoy')
    expect(attention.find('h2').text()).toBe('Requiere tu atención')
    expect(upcoming.find('h2').text()).toBe('Próximos días')

    // The agenda is a subsection of block 1: h3, never an h2 and never
    // skipping levels under the block heading.
    const agenda = wrapper.find('section[aria-label="Agenda de hoy"]')
    expect(agenda.find('h3').text()).toBe('Agenda de hoy')
    expect(agenda.find('h2').exists()).toBe(false)

    wrapper.unmount()
  })

  it('tiers the block headings typographically, strongest first', async () => {
    const wrapper = await mountDashboard()

    const hoy = wrapper.find('[data-dashboard-block="hoy"] h2')
    const attention = wrapper.find('[data-dashboard-block="attention"] h2')
    const upcoming = wrapper.find('[data-dashboard-block="upcoming"] h2')

    expect(hoy.classes()).toEqual(
      expect.arrayContaining(['text-2xl', 'font-semibold', 'text-label'])
    )
    expect(attention.classes()).toEqual(
      expect.arrayContaining(['text-xl', 'font-semibold', 'text-label'])
    )
    expect(upcoming.classes()).toEqual(
      expect.arrayContaining(['text-base', 'font-medium', 'text-theme-secondary'])
    )

    wrapper.unmount()
  })

  it('keeps the unified pending list inside its block with per-row routing hooks', async () => {
    const wrapper = await mountDashboard()

    const block = wrapper.find('[data-dashboard-block="attention"]')
    expect(block.findAll('[data-pending-row]')).toHaveLength(2)
    expect(block.find('[data-pending-row="quotations"]').exists()).toBe(true)
    expect(block.find('[data-pending-row="treatment-plans"]').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('dashboard hero band (D6 login language)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload(fullPayload())
  })

  it('renders the hero band with the login display typography, date and refresh', async () => {
    const wrapper = await mountDashboard()

    const hero = wrapper.find('[data-dashboard-hero]')
    expect(hero.exists()).toBe(true)
    // The compact header hooks survive (WU2 refresh, entrance reveal).
    expect(hero.attributes('data-dashboard-header')).toBeDefined()
    expect(hero.attributes('data-reveal')).toBe('greeting')
    expect(hero.find('[data-refresh-button]').exists()).toBe(true)

    // Greeting in the login's display step: text-4xl font-semibold.
    const greeting = hero.findAll('p').find(p => p.text().includes('Ana'))
    expect(greeting).toBeTruthy()
    expect(greeting.classes()).toEqual(expect.arrayContaining(['text-4xl', 'font-semibold']))

    // The date line keeps its tabular-nums declaration (HOTFIX-DASH-008).
    const date = hero.find('p[style*="font-feature-settings"]')
    expect(date.exists()).toBe(true)
    expect(date.text()).toMatch(/\d{1,2} de /)

    wrapper.unmount()
  })

  it('carries the login brand lockup as the sparingly used green accent', async () => {
    const wrapper = await mountDashboard()

    const hero = wrapper.find('[data-dashboard-hero]')
    expect(hero.text()).toContain('OdontoSuite')
    // Same tooth glyph and accent-500 hand-off as the login lockup.
    expect(hero.html()).toContain('var(--color-accent-500)')

    wrapper.unmount()
  })

  it('paints the hero with the login surface vocabulary (mint tint, hairline, panel radius)', async () => {
    const wrapper = await mountDashboard()

    const style = wrapper.find('[data-dashboard-hero]').attributes('style') || ''
    expect(style).toContain('var(--color-accent-50)')
    expect(style).toContain('var(--color-hairline)')
    expect(style).toContain('var(--radius-panel)')

    wrapper.unmount()
  })
})

describe('dashboard secondary upcoming block (D7)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload(fullPayload())
  })

  it('renders Próximos días as the quietest block on a reduced-prominence surface', async () => {
    const wrapper = await mountDashboard()

    const block = wrapper.find('[data-dashboard-block="upcoming"]')
    const heading = block.find('h2')
    expect(heading.text()).toBe('Próximos días')
    expect(heading.classes()).toEqual(
      expect.arrayContaining(['text-base', 'font-medium', 'text-theme-secondary'])
    )

    // Quieter surface: recessed tint + hairline + panel radius, no elevation.
    const surface = block.find('[data-upcoming-surface]')
    expect(surface.exists()).toBe(true)
    const style = surface.attributes('style') || ''
    expect(style).toContain('var(--color-system-gray-100)')
    expect(style).toContain('var(--color-hairline)')
    expect(style).toContain('var(--radius-panel)')
    expect(style).not.toContain('elevation')

    expect(block.findAll('[data-upcoming-row]')).toHaveLength(2)

    wrapper.unmount()
  })
})
