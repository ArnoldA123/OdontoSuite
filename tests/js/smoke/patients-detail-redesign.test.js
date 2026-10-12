import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'

// Patient detail redesign (dashboard/login visual language). Pins the
// structural contract at the DOM level: hero band recipe with the single
// page title (non-heading .page-title; the app-shell topbar owns the h1)
// + identity line (document, age, status badge) + data-hero-actions
// whose only page-wide primary is Editar, the ordered
// data-patient-detail-block hierarchy with importance-mapped h2 weights,
// the six tab panels rendering through ONE shared PatientTabPanel
// (loading via LoadingSpinner, empty/error via UiSectionEmpty), the edit
// form consolidated into the shared PatientFormModal, and a
// heroicons-only icon budget.
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
import Tabs from '../../../resources/js/components/ui/Tabs.vue'
import PatientFormModal from '../../../resources/js/modules/patients/PatientFormModal.vue'
import Page from '../../../resources/js/modules/patients/PatientDetailPage.vue'
import pageSource from '../../../resources/js/modules/patients/PatientDetailPage.vue?raw'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com',
  birth_date: '1990-04-12',
  gender: 'female',
  address: 'Av. Central 123',
  medical_history: 'Asma leve',
  allergies: 'Penicilina',
  notes: 'Control semestral',
  is_active: true
}

const URLS = {
  patient: '/api/patients/7',
  treatment: '/api/treatment-plans?patient_id=7',
  quotations: '/api/quotations?patient_id=7',
  medical: '/api/medical-records?patient_id=7',
  specialty: '/api/specialty-records/patient/7/all',
  audit: '/api/audit-logs/patient/7'
}

const happyGet = async url => {
  if (url === URLS.patient) return { data: patient }
  return { data: [] }
}

const listCalls = () =>
  harness.apiGet.mock.calls.map(([url]) => url).filter(url => url === URLS.treatment).length

const installGet = handler => harness.apiGet.mockImplementation(url => handler(url))

const installDeferred = () => {
  const pending = new Map()
  harness.apiGet.mockImplementation(
    url => new Promise((resolve, reject) => pending.set(url, { resolve, reject }))
  )
  return pending
}

const settle = async (pending, url, payload) => {
  const entry = pending.get(url)
  if (!entry) throw new Error(`no pending request for ${url}`)
  entry.resolve(payload)
  await flushPromises()
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/patients/:id', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/patients/7')
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const TAB_IDS = ['data', 'treatment-plans', 'quotations', 'medical-records', 'specialties', 'audit']

describe('patient detail hero band (login language)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installGet(happyGet)
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('renders the hero panel recipe with the page title and the identity line', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.find('[data-patient-detail-hero]')
    expect(hero.exists()).toBe(true)

    for (const token of [
      'var(--color-canvas)',
      'var(--color-hairline)',
      'var(--radius-panel)',
      'var(--elevation-2)'
    ]) {
      expect(hero.attributes('style')).toContain(token)
    }

    const title = hero.get('.page-title')
    expect(title.element.tagName).toBe('P')
    expect(title.text()).toBe('Ana Torres')
    expect(title.classes()).toEqual(
      expect.arrayContaining(['page-title', 'text-4xl', 'font-semibold', 'text-label'])
    )
    // The hero keeps the page-title element; the AppLayout topbar owns the
    // page's single <h1> (duplicate-h1 fix: hero titles are non-heading).
    expect(wrapper.findAll('h1').filter(h => h.text() === 'Ana Torres')).toHaveLength(0)

    // Identity line: document, age, status badge (no second identity surface).
    const document = hero.get('[data-patient-identity="document"]')
    expect(document.text()).toContain('45678912')
    expect(document.classes()).toContain('tabular-nums')

    const age = hero.get('[data-patient-identity="age"]')
    expect(age.text()).toMatch(/años/)
    expect(age.classes()).toContain('tabular-nums')

    const badge = hero.get('[data-patient-identity="status"]')
    expect(badge.text().trim()).toBe('Activo')

    // Breadcrumbs preserved inside the hero.
    const breadcrumbLink = hero.find('nav a[href="/patients"]')
    expect(breadcrumbLink.exists()).toBe(true)
    expect(breadcrumbLink.text()).toBe('Pacientes')

    // The hero owns the title now; PageHeader is gone from this page.
    expect(wrapper.findComponent(PageHeader).exists()).toBe(false)

    wrapper.unmount()
  })

  it('keeps the app-shell h1 contract: the page owns no h1, the page-title stays non-heading', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.get('[data-patient-detail-hero]')
    const title = hero.get('.page-title')
    expect(title.element.tagName).toBe('P')
    expect(title.text()).toBe('Ana Torres')
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

  it('keeps data-hero-actions with exactly one page-wide primary: Editar', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.find('[data-patient-detail-hero]')
    const actions = hero.get('[data-hero-actions]')
    const buttons = actions.findAll('button')
    expect(buttons).toHaveLength(2)

    const primaries = actions.findAll('button[data-variant="primary"]')
    expect(primaries).toHaveLength(1)
    expect(primaries[0].text().trim()).toBe('Editar')
    expect(primaries[0].attributes('data-edit-patient-button')).toBeDefined()

    const exportButton = actions.get('[data-export-button]')
    expect(exportButton.text().trim()).toBe('Exportar')
    expect(exportButton.attributes('data-variant')).toBe('secondary')

    // The hero owns the page's only primary CTA (the modal is closed).
    expect(wrapper.findAll('button[data-variant="primary"]')).toHaveLength(1)

    wrapper.unmount()
  })
})

describe('patient detail block hierarchy', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installGet(happyGet)
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('renders the ordered h2 blocks: summary before the tabbed work area', async () => {
    const wrapper = await mountPage()

    const blocks = wrapper.findAll('[data-patient-detail-block]')
    expect(blocks.map(block => block.attributes('data-patient-detail-block'))).toEqual([
      'summary',
      'work'
    ])

    // Heading tiers map by IMPORTANCE: the tabbed work area is the
    // protagonist (leading tier), the identity summary is the glanceable
    // band on the second tier.
    const summaryHeading = wrapper.find('[data-patient-detail-block="summary"] h2')
    expect(summaryHeading.text()).toBe('Resumen del paciente')
    expect(summaryHeading.classes()).toEqual(
      expect.arrayContaining(['text-xl', 'font-semibold', 'text-label'])
    )

    const workHeading = wrapper.find('[data-patient-detail-block="work"] h2')
    expect(workHeading.text()).toBe('Expediente clínico')
    expect(workHeading.classes()).toEqual(
      expect.arrayContaining(['text-2xl', 'font-semibold', 'text-label'])
    )

    // Reading order: hero leads, then the summary block, then the work block.
    const hero = wrapper.find('[data-patient-detail-hero]').element
    const summary = blocks[0].element
    const work = blocks[1].element
    expect(hero.compareDocumentPosition(summary) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(summary.compareDocumentPosition(work) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()

    // The summary carries the contact band that used to live in the
    // duplicate patient info card.
    expect(wrapper.find('[data-patient-detail-block="summary"]').text()).toContain('ana@test.com')

    wrapper.unmount()
  })
})

describe('patient detail tab anatomy (one shared PatientTabPanel)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installGet(happyGet)
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('renders the six UiTabs panels through ONE shared component', async () => {
    const wrapper = await mountPage()

    // UiTabs is resolved as a real component (not an unknown element).
    expect(wrapper.findAllComponents(Tabs)).toHaveLength(1)
    const tabButtons = wrapper.findAll('[role="tab"]')
    expect(tabButtons.map(tab => tab.text())).toEqual([
      'Datos',
      'Planes',
      'Presupuestos',
      'Historia Clínica',
      'Especialidades',
      'Historial'
    ])

    const panels = wrapper.findAll('[data-patient-tab-panel]')
    expect(panels.map(panel => panel.attributes('data-tab'))).toEqual(TAB_IDS)

    // The page renders the shared component exactly once per tab and
    // never writes the panel hook itself (the component owns it).
    expect(pageSource).toContain("import PatientTabPanel from './PatientTabPanel.vue'")
    expect(pageSource.match(/<PatientTabPanel/g) || []).toHaveLength(6)
    expect(pageSource.match(/data-patient-tab-panel/g) || []).toHaveLength(0)

    wrapper.unmount()
  })

  it('delegates loading and empty states to the shared component', async () => {
    const wrapper = await mountPage()

    // The page owns no bespoke loading/empty markup: no ad-hoc spinners,
    // no gradient icon boxes, no direct UiSectionEmpty/LoadingSpinner use.
    expect(pageSource).not.toContain('animate-spin')
    expect(pageSource).not.toContain('bg-gradient-to-br from-theme-surface')
    expect(pageSource).not.toContain('UiSectionEmpty')
    expect(pageSource).not.toContain('LoadingSpinner')

    let panelSource = ''
    try {
      panelSource = readFileSync('resources/js/modules/patients/PatientTabPanel.vue', 'utf8')
    } catch {
      panelSource = ''
    }
    expect(panelSource).toContain("from '../../components/ui/UiSectionEmpty.vue'")
    expect(panelSource).toContain("from '../../components/ui/LoadingSpinner.vue'")

    // Every non-data tab renders its empty state through UiSectionEmpty
    // with the per-tab state name.
    for (const [state, title] of [
      ['treatment-plans', 'No hay planes de tratamiento'],
      ['quotations', 'No hay presupuestos'],
      ['medical-records', 'No hay historias clínicas'],
      ['specialties', 'No hay registros de especialidades'],
      ['audit', 'No hay historial de auditoría']
    ]) {
      const empties = wrapper.findAll(`[data-patient-tab-panel="${state}"] [data-section-empty]`)
      expect(empties).toHaveLength(1)
      expect(empties[0].attributes('data-state')).toBe(`empty-${state}`)
      expect(empties[0].text()).toContain(title)
    }

    // The empty CTA only exists where a real action exists (the cross-
    // category create link); the audit tab has none.
    const treatmentCta = wrapper.get(
      '[data-patient-tab-panel="treatment-plans"] [data-section-empty] button'
    )
    expect(treatmentCta.text()).toContain('Crear Primer Plan')

    expect(
      wrapper.findAll('[data-patient-tab-panel="audit"] [data-section-empty] button')
    ).toHaveLength(0)

    expect(wrapper.findAllComponents(UiSectionEmpty)).toHaveLength(5)

    wrapper.unmount()
  })

  it('shows LoadingSpinner inside the panels while requests are in flight', async () => {
    const pending = installDeferred()
    const wrapper = await mountPage()

    expect(wrapper.find('[data-patient-tab-panel="data"]').find('.loading-spinner').exists()).toBe(
      true
    )

    await settle(pending, URLS.patient, { data: patient })

    // Patient resolved; the four list requests are now in flight.
    for (const state of ['treatment-plans', 'quotations', 'medical-records', 'specialties']) {
      expect(
        wrapper.find(`[data-patient-tab-panel="${state}"]`).find('.loading-spinner').exists()
      ).toBe(true)
    }

    await settle(pending, URLS.treatment, { data: [] })
    await settle(pending, URLS.quotations, { data: [] })
    await settle(pending, URLS.medical, { data: [] })
    await settle(pending, URLS.specialty, { data: [] })

    expect(wrapper.findComponent(LoadingSpinner).exists()).toBe(false)
    expect(wrapper.find('.animate-spin').exists()).toBe(false)

    // Activating the audit tab streams its own load through the panel.
    const auditTab = wrapper.findAll('[role="tab"]')[5]
    await auditTab.trigger('click')
    expect(wrapper.find('[data-patient-tab-panel="audit"]').find('.loading-spinner').exists()).toBe(
      true
    )
    await settle(pending, URLS.audit, { data: [] })
    expect(wrapper.find('[data-patient-tab-panel="audit"]').find('.loading-spinner').exists()).toBe(
      false
    )

    wrapper.unmount()
  })

  it('renders the error state with a working retry through the shared panel', async () => {
    installGet(async url => {
      if (url === URLS.patient) return { data: patient }
      if (url === URLS.treatment) throw new Error('network down')
      return { data: [] }
    })

    const wrapper = await mountPage()

    const errorEmpty = wrapper.get(
      '[data-patient-tab-panel="treatment-plans"] [data-section-empty]'
    )
    expect(errorEmpty.attributes('data-state')).toBe('error-treatment-plans')
    expect(errorEmpty.text()).toContain('No pudimos cargar los planes')

    const retry = wrapper.get('[data-patient-tab-panel="treatment-plans"] [data-tab-retry]')
    expect(retry.text().trim()).toBe('Reintentar')

    const before = listCalls()
    await wrapper.find('[data-tab-retry]').trigger('click')
    await flushPromises()
    expect(listCalls()).toBe(before + 1)

    wrapper.unmount()
  })

  it('renders the patient load failure through the data panel with retry', async () => {
    let patientCalls = 0
    installGet(async url => {
      if (url === URLS.patient) {
        patientCalls += 1
        if (patientCalls === 1) throw new Error('boom')
        return { data: patient }
      }
      return { data: [] }
    })

    const wrapper = await mountPage()

    const errorEmpty = wrapper.get('[data-patient-tab-panel="data"] [data-section-empty]')
    expect(errorEmpty.attributes('data-state')).toBe('error-data')
    expect(errorEmpty.text()).toContain('No pudimos cargar el paciente')

    await wrapper.find('[data-tab-retry]').trigger('click')
    await flushPromises()

    expect(patientCalls).toBe(2)
    expect(wrapper.get('[data-patient-tab-panel="data"]').text()).toContain('Información Personal')
    expect(
      wrapper.find('[data-patient-tab-panel="data"]').find('[data-section-empty]').exists()
    ).toBe(false)

    wrapper.unmount()
  })
})

describe('patient detail edit modal (shared PatientFormModal)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installGet(happyGet)
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('renders the edit form from the shared component, not a second page form', async () => {
    const wrapper = await mountPage()

    // The page owns no patient form of its own.
    expect(pageSource).toContain("import PatientFormModal from './PatientFormModal.vue'")
    expect(pageSource).toContain('mode="edit"')
    expect(pageSource).not.toMatch(/<UiModal\b/)
    expect(pageSource).not.toMatch(/<form\b/)
    expect(pageSource).not.toMatch(/<UiInput\b|<UiSelect\b|<UiTextarea\b/)
    expect(pageSource).not.toContain('editPatientData')
    expect(pageSource).not.toContain('updatePatient')
    expect(pageSource).not.toContain('type="textarea"')

    expect(wrapper.findComponent(PatientFormModal).exists()).toBe(false)

    await wrapper.find('[data-edit-patient-button]').trigger('click')
    await flushPromises()

    const modal = wrapper.findComponent(PatientFormModal)
    expect(modal.exists()).toBe(true)
    expect(modal.props('mode')).toBe('edit')
    expect(modal.props('patient')).toMatchObject({ id: 7, first_name: 'Ana' })

    // The shared component owns the single form root (teleported modal).
    expect(document.querySelectorAll('[data-patient-form]')).toHaveLength(1)

    wrapper.unmount()
  })
})

describe('patient detail motion + icon budget', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    installGet(happyGet)
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('staggers one-shot entrance reveals with a reduced-motion fallback', async () => {
    const wrapper = await mountPage()

    for (const [hook, cssVar] of [
      ['[data-reveal="hero"]', '--spring-patient-detail-hero-o'],
      ['[data-reveal="summary"]', '--spring-patient-detail-summary-o'],
      ['[data-reveal="work"]', '--spring-patient-detail-work-o']
    ]) {
      const node = wrapper.find(hook)
      expect(node.exists()).toBe(true)
      expect(node.attributes('style')).toContain(`var(${cssVar}, 1)`)
    }

    expect(pageSource).toContain('useSpring')
    expect(pageSource).toContain('prefersReducedMotion')
    expect(pageSource).toMatch(/heroSpring\.set\(1\), 0\)/)
    expect(pageSource).toMatch(/summarySpring\.set\(1\), 60\)/)
    expect(pageSource).toMatch(/workSpring\.set\(1\), 120\)/)

    wrapper.unmount()
  })

  it('consumes heroicons and keeps zero hand-written SVGs', async () => {
    const wrapper = await mountPage()

    expect(pageSource).toContain("'@heroicons/vue/24/outline'")
    expect(pageSource.match(/<svg/g) || []).toHaveLength(0)
    // No inline template-string icon components either.
    expect(pageSource.match(/template:\s*`/g) || []).toHaveLength(0)
    expect(wrapper.find('.animate-spin').exists()).toBe(false)

    // The icons actually render: hero actions + the six tab buttons.
    const hero = wrapper.find('[data-patient-detail-hero]')
    expect(hero.find('[data-edit-patient-button] svg').exists()).toBe(true)
    expect(hero.find('[data-export-button] svg').exists()).toBe(true)
    expect(wrapper.findAll('[role="tab"] svg')).toHaveLength(6)

    wrapper.unmount()
  })
})
