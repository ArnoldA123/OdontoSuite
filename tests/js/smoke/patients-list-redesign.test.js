import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Patients list redesign (login/dashboard visual language). Pins the
// structural contract at the DOM level: hero band with the page's single
// primary CTA, the ordered data-patients-block hierarchy, the dashboard
// KPI anatomy (eyebrow + number + one data-kpi-caption, non-interactive),
// the shared PatientListRow feeding both responsive surfaces, and the
// unified UiSectionEmpty states (empty without CTA, error with retry).
const harness = vi.hoisted(() => ({ apiGet: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
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
    echo: { leave: () => {} },
    channel: () => {
      const channel = { listen: () => channel }
      return channel
    },
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import PageHeader from '../../../resources/js/components/layout/PageHeader.vue'
import LoadingSpinner from '../../../resources/js/components/ui/LoadingSpinner.vue'
import UiSectionEmpty from '../../../resources/js/components/ui/UiSectionEmpty.vue'
import Page from '../../../resources/js/modules/patients/PatientsPage.vue'
import pageSource from '../../../resources/js/modules/patients/PatientsPage.vue?raw'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com',
  birth_date: '1990-04-12',
  age: 36,
  is_active: true
}

const defaultMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 1,
  active_count: 1,
  inactive_count: 0
}

const installList = payload =>
  harness.apiGet.mockImplementation(async url => {
    if (url.includes('/api/branches/active')) {
      return { data: [] }
    }
    return payload
  })

const listCalls = () =>
  harness.apiGet.mock.calls.map(([url]) => url).filter(url => url.includes('/api/patients?'))

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

describe('patients hero band (login language)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installList({ data: [patient], meta: defaultMeta })
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('renders the hero panel recipe with the page title and the brand lockup', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.find('[data-patients-hero]')
    expect(hero.exists()).toBe(true)

    for (const token of [
      'var(--color-canvas)',
      'var(--color-hairline)',
      'var(--radius-panel)',
      'var(--elevation-2)'
    ]) {
      expect(hero.attributes('style')).toContain(token)
    }

    const heroTitle = hero.get('.page-title')
    expect(heroTitle.element.tagName).toBe('P')
    expect(heroTitle.text()).toBe('Pacientes')
    expect(heroTitle.classes()).toEqual(
      expect.arrayContaining(['page-title', 'text-4xl', 'font-semibold', 'text-label'])
    )
    // The hero keeps the page-title element; the AppLayout topbar owns the
    // page's single <h1> (duplicate-h1 fix: hero titles are non-heading).
    expect(wrapper.findAll('h1').filter(heading => heading.text() === 'Pacientes')).toHaveLength(0)

    expect(hero.text()).toContain('OdontoSuite')
    expect(hero.text()).toContain('Gestiona la información de tus pacientes')

    // The hero band owns the title now; PageHeader is gone from this page.
    expect(wrapper.findComponent(PageHeader).exists()).toBe(false)

    wrapper.unmount()
  })

  it('keeps the app-shell h1 contract: the page owns no h1, the page-title stays non-heading', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.get('[data-patients-hero]')
    const title = hero.get('.page-title')
    expect(title.element.tagName).toBe('P')
    expect(title.text()).toBe('Pacientes')
    expect(title.classes()).toEqual(
      expect.arrayContaining(['page-title', 'text-4xl', 'font-semibold', 'text-label'])
    )

    // The page source and the rendered page own ZERO <h1> elements.
    expect(pageSource).not.toMatch(/<h1[\s>]/)
    expect(hero.find('h1').exists()).toBe(false)

    // The app-shell topbar owns the single <h1> (page-title map in AppLayout).
    expect(wrapper.findAll('[data-app-chrome="topbar"] h1')).toHaveLength(1)

    wrapper.unmount()
  })

  it('keeps exactly one primary CTA on the page: "Nuevo paciente" in the hero', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.find('[data-patients-hero]')
    const heroPrimaries = hero.findAll('button[data-variant="primary"]')
    expect(heroPrimaries).toHaveLength(1)
    expect(heroPrimaries[0].text().trim()).toBe('Nuevo paciente')

    expect(wrapper.findAll('button[data-variant="primary"]')).toHaveLength(1)

    wrapper.unmount()
  })
})

describe('patients block hierarchy', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installList({ data: [patient], meta: defaultMeta })
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('renders the ordered h2 blocks: summary before directory', async () => {
    const wrapper = await mountPage()

    const blocks = wrapper.findAll('[data-patients-block]')
    expect(blocks.map(block => block.attributes('data-patients-block'))).toEqual([
      'summary',
      'directory'
    ])

    // Heading tiers map by IMPORTANCE, not by source order (architect
    // decision): the directory is the protagonist block and takes the
    // leading tier; the summary is a glanceable band on the second tier.
    const summaryHeading = wrapper.find('[data-patients-block="summary"] h2')
    expect(summaryHeading.text()).toBe('Resumen de pacientes')
    expect(summaryHeading.classes()).toEqual(
      expect.arrayContaining(['text-xl', 'font-semibold', 'text-label'])
    )

    const directoryHeading = wrapper.find('[data-patients-block="directory"] h2')
    expect(directoryHeading.text()).toBe('Directorio de pacientes')
    expect(directoryHeading.classes()).toEqual(
      expect.arrayContaining(['text-2xl', 'font-semibold', 'text-label'])
    )

    // Reading order: hero leads, then the summary block, then the directory.
    const hero = wrapper.find('[data-patients-hero]').element
    const summary = blocks[0].element
    const directory = blocks[1].element
    expect(hero.compareDocumentPosition(summary) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(
      summary.compareDocumentPosition(directory) & Node.DOCUMENT_POSITION_FOLLOWING
    ).toBeTruthy()

    wrapper.unmount()
  })
})

describe('patients KPI strip (dashboard anatomy)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installList({ data: [patient], meta: defaultMeta })
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('renders four non-interactive cards with eyebrow + number + one caption', async () => {
    const wrapper = await mountPage()

    const cards = wrapper.findAll('[data-patients-block="summary"] [data-stat-card]')
    expect(cards).toHaveLength(4)

    for (const card of cards) {
      expect(card.attributes('data-clickable')).toBe('false')
      expect(card.attributes('clickable')).toBeUndefined()
      expect(card.classes()).not.toContain('cursor-pointer')

      expect(card.findAll('p')).toHaveLength(3)
      expect(card.find('p.uppercase').exists()).toBe(true)

      const captions = card.findAll('[data-kpi-caption]')
      expect(captions).toHaveLength(1)
      expect(captions[0].text().trim().length).toBeGreaterThan(0)

      const number = card.find('[data-stat]')
      expect(number.exists()).toBe(true)
      expect(number.attributes('style')).toContain("font-feature-settings: 'tnum' 1, 'lnum' 1")
      expect(number.classes()).toEqual(
        expect.arrayContaining([
          'text-2xl',
          'font-bold',
          'text-label',
          'tabular-nums',
          'leading-none',
          'truncate'
        ])
      )
    }

    wrapper.unmount()
  })

  it('scopes every number with its own data-kpi-caption key', async () => {
    const wrapper = await mountPage()

    const keys = wrapper
      .findAll('[data-patients-block="summary"] [data-kpi-caption]')
      .map(caption => caption.attributes('data-kpi-caption'))
    expect(keys).toEqual([
      'total-patients',
      'active-patients',
      'inactive-patients',
      'filtered-patients'
    ])

    wrapper.unmount()
  })
})

describe('patients row surfaces (one shared PatientListRow)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installList({ data: [patient], meta: defaultMeta })
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('renders the desktop table and the mobile cards from one row component', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-patient-surface="table"] table').exists()).toBe(true)
    expect(wrapper.find('[data-patient-surface="cards"]').exists()).toBe(true)

    const rows = wrapper.findAll('[data-patient-row]')
    expect(rows).toHaveLength(2)
    expect(wrapper.findAll('[data-patient-surface="table"] [data-patient-row]')).toHaveLength(1)
    expect(wrapper.findAll('[data-patient-surface="cards"] [data-patient-row]')).toHaveLength(1)

    // Both surfaces render the same PatientListRow component.
    expect(pageSource).toContain("import PatientListRow from './PatientListRow.vue'")
    expect(pageSource.match(/<PatientListRow\b/g) || []).toHaveLength(2)

    wrapper.unmount()
  })
})

describe('patients unified states', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('shows LoadingSpinner while the list request is in flight', async () => {
    let resolveList
    harness.apiGet.mockImplementation(url =>
      url.includes('/api/branches/active')
        ? Promise.resolve({ data: [] })
        : new Promise(resolve => {
            resolveList = resolve
          })
    )

    const wrapper = await mountPage()
    expect(wrapper.findComponent(LoadingSpinner).exists()).toBe(true)

    resolveList({ data: [patient], meta: defaultMeta })
    await flushPromises()
    expect(wrapper.findComponent(LoadingSpinner).exists()).toBe(false)
    expect(wrapper.findAll('[data-patient-row]')).toHaveLength(2)

    wrapper.unmount()
  })

  it('renders the empty state through UiSectionEmpty with no CTA', async () => {
    installList({
      data: [],
      meta: { ...defaultMeta, total: 0, active_count: 0, inactive_count: 0 }
    })

    const wrapper = await mountPage()

    const empties = wrapper.findAllComponents(UiSectionEmpty)
    expect(empties).toHaveLength(1)

    const panel = empties[0].get('[data-section-empty]')
    expect(panel.attributes('data-state')).toBe('empty-patients')
    expect(panel.text()).toContain('No se encontraron pacientes')
    expect(panel.text()).toContain('Intenta ajustar los filtros de búsqueda')
    // The hero owns the only CTA for this destination.
    expect(panel.find('button').exists()).toBe(false)

    wrapper.unmount()
  })

  it('renders the inline error state through UiSectionEmpty with a retry action', async () => {
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        return { data: [] }
      }
      throw new Error('network down')
    })

    const wrapper = await mountPage()

    const empties = wrapper.findAllComponents(UiSectionEmpty)
    expect(empties).toHaveLength(1)

    const panel = empties[0].get('[data-section-empty]')
    expect(panel.attributes('data-state')).toBe('error-patients')
    expect(panel.text()).toContain('No pudimos cargar los pacientes')

    const retry = panel.get('[data-retry-patients]')
    expect(retry.text().trim()).toBe('Reintentar')

    const before = listCalls().length
    await retry.trigger('click')
    await flushPromises()
    expect(listCalls().length).toBe(before + 1)

    wrapper.unmount()
  })
})

describe('patients motion + icon budget', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installList({ data: [patient], meta: defaultMeta })
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('staggers one-shot entrance reveals with a reduced-motion fallback', async () => {
    const wrapper = await mountPage()

    for (const [hook, cssVar] of [
      ['[data-reveal="hero"]', '--spring-patients-hero-o'],
      ['[data-reveal="summary"]', '--spring-patients-summary-o'],
      ['[data-reveal="directory"]', '--spring-patients-directory-o']
    ]) {
      const node = wrapper.find(hook)
      expect(node.exists()).toBe(true)
      expect(node.attributes('style')).toContain(`var(${cssVar}, 1)`)
    }

    expect(pageSource).toContain('useSpring')
    expect(pageSource).toContain('prefersReducedMotion')
    expect(pageSource).toMatch(/heroSpring\.set\(1\), 0\)/)
    expect(pageSource).toMatch(/summarySpring\.set\(1\), 60\)/)
    expect(pageSource).toMatch(/directorySpring\.set\(1\), 120\)/)

    wrapper.unmount()
  })

  it('consumes heroicons and keeps only the brand tooth glyph inline', async () => {
    const { readFileSync } = await import('node:fs')
    const rowSource = readFileSync('resources/js/modules/patients/PatientListRow.vue', 'utf8')

    expect(pageSource).toContain("'@heroicons/vue/24/outline'")
    expect(pageSource.match(/<svg/g) || []).toHaveLength(1)

    expect(rowSource).toContain("'@heroicons/vue/24/outline'")
    expect(rowSource.match(/<svg/g) || []).toHaveLength(0)

    // The icons must actually render (imported components get registered).
    const wrapper = await mountPage()
    const hero = wrapper.find('[data-patients-hero]')
    expect(hero.find('[data-new-patient-button] svg').exists()).toBe(true)
    expect(hero.find('[data-back-button] svg').exists()).toBe(true)
    const searchInput = wrapper.find(
      'input[placeholder="Buscar por nombre, DNI, teléfono o email..."]'
    )
    expect(searchInput.exists()).toBe(true)
    expect(wrapper.find('[data-patient-surface="cards"] [data-patient-row] svg').exists()).toBe(
      true
    )
    wrapper.unmount()
  })
})
