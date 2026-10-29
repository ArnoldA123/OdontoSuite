import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { nextTick } from 'vue'

// Patients list redesign (login/dashboard visual language). Pins the
// structural contract at the DOM level: hero band with the page's single
// primary CTA, the ordered data-patients-block hierarchy, the dashboard
// KPI anatomy (eyebrow + number + one data-kpi-caption, non-interactive),
// the page-owned UiDataTable feeding the desktop table, the PatientListRow
// mobile card, and the unified UiSectionEmpty states (empty without CTA, error
// with retry). #499: the card component renders no table row — the desktop row
// belongs to the page and its primitive.
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
import UiDataTable from '../../../resources/js/components/ui/DataTable.vue'
import UiSectionEmpty from '../../../resources/js/components/ui/UiSectionEmpty.vue'
import Page from '../../../resources/js/modules/patients/PatientsPage.vue'
import pageSource from '../../../resources/js/modules/patients/PatientsPage.vue?raw'
import rowSource from '../../../resources/js/modules/patients/PatientListRow.vue?raw'

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
    routes: [
      { path: '/patients', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/patients')
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

/**
 * #453 — jsdom performs no layout, so the overflow the primitive advertises has
 * to be declared by the test: geometry on the scroll viewport plus the resize
 * event the primitive listens to.
 */
const declareTableOverflow = async (wrapper, { scrollWidth = 900, clientWidth = 640 } = {}) => {
  const viewport = wrapper.get('[data-patient-table] [data-table-scroll-viewport]').element
  Object.defineProperty(viewport, 'scrollWidth', { configurable: true, value: scrollWidth })
  Object.defineProperty(viewport, 'clientWidth', { configurable: true, value: clientWidth })
  viewport.scrollLeft = 0
  window.dispatchEvent(new Event('resize'))
  await nextTick()
  await nextTick()
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
    expect(heroTitle.element.tagName).toBe('H1')
    expect(heroTitle.text()).toBe('Pacientes')
    expect(heroTitle.classes()).toEqual(
      expect.arrayContaining(['page-title', 'text-4xl', 'font-semibold', 'text-label'])
    )
    // #405: the hero title is the document's heading and the topbar yields it,
    // so the page paints one visible <h1> and no second one above it.
    expect(wrapper.findAll('h1')).toHaveLength(1)

    expect(hero.text()).toContain('OdontoSuite')
    expect(hero.text()).toContain('Gestiona la información de tus pacientes')

    // The hero band owns the title now; PageHeader is gone from this page.
    expect(wrapper.findComponent(PageHeader).exists()).toBe(false)

    wrapper.unmount()
  })

  it('owns the document heading: one visible h1 and no heading in the topbar', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.get('[data-patients-hero]')
    const title = hero.get('.page-title')
    expect(title.element.tagName).toBe('H1')
    expect(title.text()).toBe('Pacientes')
    expect(title.classes()).toEqual(
      expect.arrayContaining(['page-title', 'text-4xl', 'font-semibold', 'text-label'])
    )

    // #405: the shell cedes the heading on /patients, so the document keeps
    // exactly one <h1> and it is the visible hero title.
    const topbar = wrapper.get('[data-app-chrome="topbar"]')
    expect(topbar.attributes('data-topbar-title-owner')).toBe('page')
    expect(topbar.find('h1').exists()).toBe(false)

    const headings = wrapper.findAll('h1')
    expect(headings).toHaveLength(1)
    expect(headings[0].element).toBe(title.element)
    expect(headings[0].classes()).not.toContain('sr-only')

    wrapper.unmount()
  })

  it('keeps exactly one primary CTA on the page: "Nuevo paciente" in the hero', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.find('[data-patients-hero]')
    const heroPrimaries = hero.findAll('button[data-variant="primary"]')
    expect(heroPrimaries).toHaveLength(1)
    expect(heroPrimaries[0].text().trim()).toBe('Nuevo paciente')

    // Hero action buttons keep their natural width with unwrappable labels
    // at xs; the row wraps instead of squeezing the CTA into two lines.
    const actions = hero.get('[data-hero-actions]')
    expect(actions.classes()).toContain('flex-wrap')
    for (const button of actions.findAll('button')) {
      expect(button.classes()).toEqual(expect.arrayContaining(['flex-none', 'whitespace-nowrap']))
      expect(button.classes()).not.toContain('flex-1')
    }

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
      // Prettier formats the inline style attribute across lines; the rule is
      // the declaration, so compare with whitespace collapsed.
      const numberStyle = (number.attributes('style') || '').replace(/\s+/g, ' ')
      expect(numberStyle).toContain("font-feature-settings: 'tnum' 1, 'lnum' 1")
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

describe('patients row surfaces (DataTable on desktop, card on mobile)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installList({ data: [patient], meta: defaultMeta })
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('renders the desktop table through UiDataTable and the mobile cards from the row card', async () => {
    const wrapper = await mountPage()

    // #453 (U11, list): the desktop list inherits the primitive, so the
    // horizontal scroll and its affordance are no longer hand-built here.
    expect(wrapper.findComponent(UiDataTable).exists()).toBe(true)
    expect(wrapper.find('[data-patient-surface="table"] table').exists()).toBe(true)
    expect(wrapper.find('[data-patient-surface="table"] [data-table-scroll]').exists()).toBe(true)
    expect(wrapper.find('[data-patient-surface="cards"]').exists()).toBe(true)

    const rows = wrapper.findAll('[data-patient-row]')
    expect(rows).toHaveLength(2)
    expect(wrapper.findAll('[data-patient-surface="table"] [data-patient-row]')).toHaveLength(1)
    expect(wrapper.findAll('[data-patient-surface="cards"] [data-patient-row]')).toHaveLength(1)

    // The mobile surface renders the PatientListRow card; the desktop table is
    // the page's and the primitive renders it.
    expect(pageSource).toContain("import PatientListRow from './PatientListRow.vue'")
    expect(pageSource.match(/<PatientListRow\b/g) || []).toHaveLength(1)
    expect(pageSource).toContain('<UiDataTable')
    expect(pageSource).not.toMatch(/<table\b/)

    // #499: the component is the mobile card only. Its dead `variant === 'table'`
    // branch (and the prop that selected it) retired with the #453 migration.
    expect(rowSource).not.toContain('<tr')
    expect(rowSource).not.toContain("variant === 'table'")
    expect(rowSource).not.toContain('variant: {')
    expect(pageSource).not.toContain('variant="card"')

    // The primitive renders the same six columns the page owned before.
    const header = wrapper.get('[data-patient-table] thead').text()
    for (const label of ['Paciente', 'Contacto', 'Fecha de Nacimiento', 'Edad', 'Estado']) {
      expect(header).toContain(label)
    }
    expect(header).toContain('Acciones')

    wrapper.unmount()
  })

  it('renders the mobile card alone: row hook, values and its three actions', async () => {
    const wrapper = await mountPage()

    const card = wrapper.get('[data-patient-surface="cards"] [data-patient-row]')
    expect(card.element.tagName).toBe('DIV')
    expect(card.text()).toContain('Ana Torres')
    expect(card.text()).toContain('999888777')
    expect(card.text()).toContain('ana@test.com')
    expect(card.text()).toContain('12/04/1990')
    expect(card.text()).toContain('36 años')
    expect(card.text()).toContain('Activo')

    const labels = card.findAll('button').map(button => button.text().trim())
    expect(labels).toEqual(['Ver', 'Editar', 'Eliminar'])

    wrapper.unmount()
  })

  it('keeps the row hook, its cells and its three actions inside the table', async () => {
    const wrapper = await mountPage()

    const row = wrapper.get('[data-patient-table] [data-patient-row]')
    expect(row.element.tagName).toBe('TR')
    expect(row.text()).toContain('Ana Torres')
    expect(row.text()).toContain('999888777')
    expect(row.text()).toContain('ana@test.com')
    expect(row.text()).toContain('12/04/1990')
    expect(row.text()).toContain('36 años')
    expect(row.text()).toContain('Activo')

    const labels = row.findAll('button').map(button => button.text().trim())
    expect(labels).toEqual(['Ver', 'Editar', 'Eliminar'])

    wrapper.unmount()
  })

  it('publishes the primitive scroll affordance when the columns overflow', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-table-scroll-affordance]').exists()).toBe(false)

    await declareTableOverflow(wrapper)

    expect(wrapper.get('[data-table-scroll]').attributes('data-table-scroll')).toBe('right')
    const hint = wrapper.get('[data-table-scroll-affordance]')
    expect(hint.text()).toMatch(/desliza/i)
    expect(hint.text()).toMatch(/columnas/i)

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
