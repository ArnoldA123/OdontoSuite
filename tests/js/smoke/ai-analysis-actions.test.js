import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const { getMock, postMock, deleteMock, confirmMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  deleteMock: vi.fn(),
  confirmMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: vi.fn(),
    patch: vi.fn(),
    delete: deleteMock,
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
vi.mock('../../../resources/js/composables/useConfirm', async () => {
  const { ref } = await import('vue')

  return {
    useConfirm: () => ({ confirm: confirmMock }),
    confirmIsOpen: ref(false),
    confirmTitle: ref(''),
    confirmMessage: ref(''),
    confirmConfirmText: ref(''),
    confirmCancelText: ref(''),
    confirmVariant: ref('default')
  }
})
vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      'ai-analysis.review': { value: true },
      'ai-analysis.void': { value: true }
    }
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/ai-analysis/AiAnalysisPage.vue'
import AiAnalysisModal from '../../../resources/js/modules/ai-analysis/components/AiAnalysisModal.vue'

const STATS = {
  total_analyses: 1,
  completed_analyses: 1,
  reviewed_analyses: 0,
  pending_review: 1,
  completion_rate: 100,
  review_rate: 0
}

const row = () => ({
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
  recommendations: []
})

const listResponse = () => ({
  data: [row()],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 }
})

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/ai-analysis')
  await router.isReady()
  const wrapper = mount(Page, {
    global: { plugins: [router, uiComponents], stubs: { teleport: true } }
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  getMock.mockReset()
  postMock.mockReset()
  deleteMock.mockReset()
  confirmMock.mockReset()

  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/ai-analysis/stats')) {
      return { data: STATS }
    }
    return listResponse()
  })
  postMock.mockResolvedValue({ data: row() })
  deleteMock.mockResolvedValue({ data: {} })
  confirmMock.mockResolvedValue(true)
})

describe('ai-analysis page actions', () => {
  it('opens the detail from the annulment row action instead of annulling directly', async () => {
    const wrapper = await mountPage()

    const voidAction = wrapper.find('[data-row-action="void"]')
    expect(voidAction.attributes('aria-label')).toBe('Anular análisis')

    await voidAction.trigger('click')
    await flushPromises()

    expect(wrapper.findComponent(AiAnalysisModal).exists()).toBe(true)
    expect(confirmMock).not.toHaveBeenCalled()
    expect(postMock).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('annuls from the detail surface only after the reason is confirmed', async () => {
    const wrapper = await mountPage()

    await wrapper.find('[data-row-action="void"]').trigger('click')
    await flushPromises()
    getMock.mockClear()

    const reason = wrapper.find('[data-ai-void-reason]')
    await reason.setValue('Duplicado del estudio previo')
    await wrapper.find('[data-ai-void]').trigger('click')
    await flushPromises()

    expect(confirmMock).toHaveBeenCalledTimes(1)
    expect(confirmMock.mock.calls[0][0]).toMatchObject({
      title: 'Anular análisis',
      message:
        '¿Anular este análisis? Se conserva la traza de revisión y el motivo queda registrado.',
      confirmText: 'Anular',
      variant: 'danger'
    })
    expect(postMock).toHaveBeenCalledWith('/api/ai-analysis/1/void', {
      reason: 'Duplicado del estudio previo'
    })
    expect(wrapper.findComponent(AiAnalysisModal).exists()).toBe(false)
    expect(getMock).toHaveBeenCalled()

    wrapper.unmount()
  })

  it('keeps the analysis when the confirmation is cancelled', async () => {
    confirmMock.mockResolvedValue(false)
    const wrapper = await mountPage()

    await wrapper.find('[data-row-action="void"]').trigger('click')
    await flushPromises()

    await wrapper.find('[data-ai-void-reason]').setValue('Duplicado del estudio previo')
    await wrapper.find('[data-ai-void]').trigger('click')
    await flushPromises()

    expect(postMock).not.toHaveBeenCalled()
    expect(wrapper.findComponent(AiAnalysisModal).exists()).toBe(true)

    wrapper.unmount()
  })

  it('wires the detail surface void event to the annulment API', async () => {
    const wrapper = await mountPage()

    await wrapper.find('[data-row-action="view"]').trigger('click')
    await flushPromises()

    wrapper.findComponent(AiAnalysisModal).vm.$emit('void', 1, 'Imagen equivocada')
    await flushPromises()

    expect(postMock).toHaveBeenCalledWith('/api/ai-analysis/1/void', {
      reason: 'Imagen equivocada'
    })

    wrapper.unmount()
  })

  it('saves a review, closes the detail surface and reloads the list', async () => {
    const wrapper = await mountPage()

    await wrapper.find('[data-row-action="view"]').trigger('click')
    await flushPromises()
    getMock.mockClear()

    wrapper.findComponent(AiAnalysisModal).vm.$emit('review', 1, 'accepted', 'Sin hallazgos')
    await flushPromises()

    expect(postMock).toHaveBeenCalledWith('/api/ai-analysis/1/review', {
      decision: 'accepted',
      notes: 'Sin hallazgos'
    })
    expect(wrapper.findComponent(AiAnalysisModal).exists()).toBe(false)
    expect(getMock).toHaveBeenCalled()

    wrapper.unmount()
  })
})
