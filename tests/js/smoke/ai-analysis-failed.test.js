import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { h } from 'vue'

// #427 (specs S6 and S7) — the result contract of a failed analysis.
//
// The provider call is caught by the API, so the creation still answers 201.
// The screen may not present that row as a result: the detail paints the
// readable reason, and the upload flow keeps the failure on its own surface
// instead of closing the sheet and opening the detail as if it had findings.
const { getMock, postMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
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
vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      'ai-analysis.review': { value: false },
      'ai-analysis.void': { value: false }
    }
  })
}))
import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/ai-analysis/AiAnalysisPage.vue'
import AiAnalysisModal from '../../../resources/js/modules/ai-analysis/components/AiAnalysisModal.vue'
import AnalyzingModal from '../../../resources/js/modules/ai-analysis/components/AnalyzingModal.vue'
import { useToast, globalToasts } from '../../../resources/js/composables/useToast'

const FAILURE_REASON = 'El proveedor de IA no devolvió un resultado válido.'

const STATS = {
  total_analyses: 12,
  completed_analyses: 9,
  reviewed_analyses: 4,
  pending_review: 5,
  completion_rate: 75,
  review_rate: 44.44
}

const row = (overrides = {}) => ({
  id: 1,
  patient: { id: 7, full_name: 'Ana Torres' },
  clinical_attachment: {
    id: 3,
    file_path: 'clinical-attachments/rx.jpg',
    original_name: 'rx.jpg',
    category: 'radiografia'
  },
  status: 'completed',
  reviewed: false,
  confidence_score: 82.5,
  created_at: '2026-10-01T10:00:00.000000Z',
  findings: [],
  recommendations: [],
  failure_reason: null,
  ...overrides
})

const listResponse = (items = [row()]) => ({
  data: items,
  meta: { current_page: 1, last_page: 1, per_page: 15, total: items.length }
})

const analysisFixture = (overrides = {}) => ({
  id: 42,
  status: 'failed',
  reviewed: false,
  confidence_score: null,
  created_at: '2026-10-01T10:15:00.000000Z',
  model_used: 'gpt-4o-mini',
  tokens_used: null,
  findings: [],
  recommendations: [],
  patient: { id: 7, full_name: 'Ana Torres' },
  clinical_attachment: {
    id: 3,
    file_path: 'clinical-attachments/rx.jpg',
    original_name: 'rx.jpg',
    category: 'radiografia'
  },
  requested_by: { id: 2, name: 'Dr. Pérez' },
  assistive: true,
  assistive_notice: 'Resultado de apoyo a la decisión asistido por inteligencia artificial.',
  notice_acknowledged_at: null,
  notice_acknowledged_by: null,
  estimated_cost: null,
  failure_reason: FAILURE_REASON,
  ...overrides
})

// The sheet is the page's submission surface; this file drives the page's
// contract, so the stub emits the same payload the real sheet emits.
const SheetStub = {
  name: 'AiAnalysisSheet',
  props: {
    modelValue: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' }
  },
  emits: ['update:modelValue', 'close', 'submit'],
  setup(_, { emit }) {
    return () =>
      h('div', { 'data-stub-sheet': '' }, [
        h(
          'button',
          {
            type: 'button',
            'data-stub-sheet-submit': '',
            onClick: () =>
              emit('submit', {
                patientId: 7,
                file: new File(['x'], 'rx.jpg', { type: 'image/jpeg' }),
                category: 'radiografia',
                description: '',
                noticeAcknowledged: true
              })
          },
          'Enviar'
        )
      ])
  }
}

const mountPage = async ({ items = [row()] } = {}) => {
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/ai-analysis/stats')) {
      return { data: STATS }
    }
    return listResponse(items)
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/ai-analysis')
  await router.isReady()

  const wrapper = mount(Page, {
    attachTo: document.body,
    global: {
      plugins: [router, uiComponents],
      stubs: { AiAnalysisSheet: SheetStub }
    }
  })
  await flushPromises()
  return wrapper
}

const submitNewAnalysis = async wrapper => {
  await wrapper.find('[data-hero-primary]').trigger('click')
  await flushPromises()
  await wrapper.find('[data-stub-sheet-submit]').trigger('click')
  await flushPromises()
}

const successToasts = () => globalToasts.value.filter(toast => toast.type === 'success')

let wrapper = null

beforeEach(() => {
  useToast().clearAll()
  getMock.mockReset()
  postMock.mockReset()
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
})

describe('ai-analysis failed detail', () => {
  it('paints the readable reason before the findings section', async () => {
    wrapper = mount(AiAnalysisModal, {
      props: { analysis: analysisFixture() },
      global: { plugins: [uiComponents], stubs: { teleport: true } }
    })
    await flushPromises()

    const block = wrapper.find('[data-ai-failure]')
    expect(block.exists()).toBe(true)
    expect(block.text()).toContain(FAILURE_REASON)

    const html = wrapper.html()
    expect(html.indexOf('data-ai-failure')).toBeLessThan(html.indexOf('data-ai-section="findings"'))
  })

  it('adds no cause of its own when the row carries no reason', async () => {
    wrapper = mount(AiAnalysisModal, {
      props: { analysis: analysisFixture({ failure_reason: null }) },
      global: { plugins: [uiComponents], stubs: { teleport: true } }
    })
    await flushPromises()

    const block = wrapper.find('[data-ai-failure]')
    expect(block.exists()).toBe(true)
    expect(block.text()).not.toContain('inválid')
    expect(block.text()).not.toContain('conectar')
    expect(block.text()).toBe('No se pudo completar el análisis.')
  })

  it('keeps the failure block off a completed analysis', async () => {
    wrapper = mount(AiAnalysisModal, {
      props: { analysis: analysisFixture({ status: 'completed', failure_reason: null }) },
      global: { plugins: [uiComponents], stubs: { teleport: true } }
    })
    await flushPromises()

    expect(wrapper.find('[data-ai-failure]').exists()).toBe(false)
  })
})

describe('ai-analysis failed upload', () => {
  it('keeps the sheet open with the reason instead of opening the detail', async () => {
    postMock.mockResolvedValue({
      data: { id: 99, status: 'failed', failure_reason: FAILURE_REASON }
    })

    wrapper = await mountPage()
    getMock.mockClear()

    await submitNewAnalysis(wrapper)

    expect(successToasts()).toHaveLength(0)
    expect(wrapper.findComponent(AiAnalysisModal).exists()).toBe(false)

    // #427: the sheet stays on screen so the professional can retry, and the
    // reason lands in its own inline error channel.
    const sheet = wrapper.findComponent(SheetStub)
    expect(sheet.props('modelValue')).toBe(true)
    expect(sheet.props('errorMessage')).toBe(FAILURE_REASON)

    // The progress surface is not dismissible by Escape: leaving it open would
    // trap the page behind it.
    expect(wrapper.findComponent(AnalyzingModal).props('show')).toBe(false)

    const urls = getMock.mock.calls.map(([requested]) => String(requested))
    expect(urls.some(requested => requested.includes('/api/ai-analysis?page=1'))).toBe(true)
  })

  it('falls back to the neutral sentence when the failure carries no reason', async () => {
    postMock.mockResolvedValue({ data: { id: 99, status: 'failed' } })

    wrapper = await mountPage()
    await submitNewAnalysis(wrapper)

    expect(wrapper.findComponent(AiAnalysisModal).exists()).toBe(false)
    expect(wrapper.findComponent(SheetStub).props('errorMessage')).toBe(
      'No se pudo completar el análisis.'
    )
    expect(wrapper.findComponent(AnalyzingModal).props('show')).toBe(false)
    expect(successToasts()).toHaveLength(0)
  })

  it('still opens the detail and announces the result when the analysis completes', async () => {
    const analysis = { id: 99, status: 'completed' }
    postMock.mockResolvedValue({ data: analysis })

    wrapper = await mountPage()
    await submitNewAnalysis(wrapper)

    const detail = wrapper.findComponent(AiAnalysisModal)
    expect(detail.exists()).toBe(true)
    expect(detail.props('analysis')).toEqual(analysis)
    expect(wrapper.findComponent(SheetStub).props('modelValue')).toBe(false)
    expect(wrapper.findComponent(AnalyzingModal).props('show')).toBe(false)
    expect(successToasts()).toHaveLength(1)
  })
})
