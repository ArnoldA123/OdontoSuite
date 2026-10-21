import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #203 — the patient sheet consumes the quotation contract that actually
// exists: the deep link opens the list page instead of a missing child route,
// the status renders the shared vocabulary, and the PDF rides the canonical
// download verb.
const harness = vi.hoisted(() => ({ apiGet: vi.fn(), apiDownload: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    download: harness.apiDownload,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: (_error, fallback) => fallback
  })
}))

vi.mock('../../../resources/js/composables/useEcho', () => {
  const chain = { listen: () => chain }

  return {
    useEcho: () => ({
      echo: { leave: () => {} },
      channel: () => chain,
      privateChannel: () => chain,
      connectionStatus: { value: 'connected' }
    })
  }
})

vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/patients/PatientDetailPage.vue'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  is_active: true
}

const quotation = {
  id: 3,
  patient_id: 7,
  quotation_number: 'PR-VIEW-003',
  quotation_date: '2026-10-03',
  valid_until: '2026-11-03',
  status: 'viewed',
  total_amount: 900,
  notes: 'Incluye control'
}

const mountPage = async () => {
  harness.apiGet.mockImplementation(async url => {
    if (url === '/api/patients/7') return { data: patient }
    if (url === '/api/quotations?patient_id=7') return { data: [quotation] }
    return { data: [] }
  })
  harness.apiDownload.mockResolvedValue({
    blob: new Blob(['pdf']),
    filename: 'presupuesto_PR-VIEW-003.pdf'
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/patients/:id', component: { template: '<div />' } },
      { path: '/quotations', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/patients/7')

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()

  return { wrapper, router }
}

const quotationButtons = wrapper =>
  Array.from(
    wrapper.get('[data-patient-tab-panel="quotations"]').element.querySelectorAll('button')
  )

const buttonByText = (wrapper, text) =>
  quotationButtons(wrapper).find(button => button.textContent.trim() === text)

describe('patient detail quotation surfaces', () => {
  beforeEach(() => {
    localStorage.setItem('auth_token', 'test-token')
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    window.URL.createObjectURL = vi.fn(() => 'blob:quotation')
    window.URL.revokeObjectURL = vi.fn()
    document.body.innerHTML = ''
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  it('renders the shared status vocabulary instead of the raw enum value', async () => {
    const { wrapper } = await mountPage()

    const panel = wrapper.get('[data-patient-tab-panel="quotations"]')
    expect(panel.text()).toContain('Visto')
    expect(panel.text()).not.toContain('viewed')

    wrapper.unmount()
  })

  it('opens the quotation detail through the list deep link, not a missing child route', async () => {
    const { wrapper, router } = await mountPage()

    buttonByText(wrapper, 'Ver').click()
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/quotations')
    expect(router.currentRoute.value.query.open).toBe('3')

    wrapper.unmount()
  })

  it('downloads the PDF through the canonical verb', async () => {
    const { wrapper } = await mountPage()

    buttonByText(wrapper, 'PDF').click()
    await flushPromises()

    expect(harness.apiDownload).toHaveBeenCalledWith('/api/quotations/3/pdf', {
      fallbackFilename: 'presupuesto_3.pdf'
    })

    wrapper.unmount()
  })
})
