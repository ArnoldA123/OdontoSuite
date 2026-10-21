import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const { getMock, postMock, allowed } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  allowed: { value: true }
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
vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      'ai-analysis.analyze': allowed
    }
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Button from '../../../resources/js/modules/ai-analysis/components/AiAnalysisButton.vue'

const attachment = (overrides = {}) => ({
  category: 'radiografia',
  file_type: 'image',
  ...overrides
})

const existingAnalysis = (overrides = {}) => ({
  id: 9,
  status: 'pending',
  confidence_score: null,
  reviewed: false,
  review_decision: null,
  ...overrides
})

const mountButton = props =>
  mount(Button, {
    props: { attachmentId: 3, ...props },
    global: { plugins: [uiComponents] }
  })

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: null })
  postMock.mockReset()
  postMock.mockResolvedValue({ data: existingAnalysis({ status: 'processing' }) })
  allowed.value = true
})

describe('ai-analysis button permission gate', () => {
  it('renders an enabled action for a radiograph when the permission is present', async () => {
    const wrapper = mountButton({ attachment: attachment() })
    await flushPromises()

    const button = wrapper.find('button')
    expect(button.exists()).toBe(true)
    expect(button.text()).toContain('Analizar con IA')
    expect(button.attributes('disabled')).toBeUndefined()

    wrapper.unmount()
  })

  it('disables the action when the permission is absent', async () => {
    allowed.value = false

    const wrapper = mountButton({ attachment: attachment() })
    await flushPromises()

    expect(wrapper.find('button').attributes('disabled')).toBeDefined()

    wrapper.unmount()
  })

  it('disables the action for a non-radiograph attachment', async () => {
    const wrapper = mountButton({ attachment: attachment({ category: 'foto_clinica' }) })
    await flushPromises()

    expect(wrapper.find('button').attributes('disabled')).toBeDefined()

    wrapper.unmount()
  })
})

describe('ai-analysis button states', () => {
  it('shows the analyzing label while the request is in flight', async () => {
    let resolvePost
    postMock.mockImplementation(
      () =>
        new Promise(resolve => {
          resolvePost = resolve
        })
    )

    const wrapper = mountButton({ attachment: attachment() })
    await flushPromises()

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Analizando...')

    resolvePost({ data: existingAnalysis({ status: 'processing' }) })
    await flushPromises()

    expect(wrapper.text()).toContain('Procesando')

    wrapper.unmount()
  })

  it.each([
    ['pending', 'Pendiente'],
    ['processing', 'Procesando'],
    ['completed', 'Completado'],
    ['failed', 'Fallido']
  ])('renders the module label for a %s analysis', async (status, label) => {
    getMock.mockResolvedValue({ data: existingAnalysis({ status }) })

    const wrapper = mountButton({ attachment: attachment() })
    await flushPromises()

    expect(wrapper.find('button').exists()).toBe(true)
    expect(wrapper.text()).toContain(label)

    wrapper.unmount()
  })

  it.each([
    ['accepted', 'Aceptado'],
    ['rejected', 'Rechazado'],
    ['partial', 'Parcial']
  ])('renders the review decision label for a reviewed %s analysis', async (decision, label) => {
    getMock.mockResolvedValue({
      data: existingAnalysis({ status: 'completed', reviewed: true, review_decision: decision })
    })

    const wrapper = mountButton({ attachment: attachment() })
    await flushPromises()

    expect(wrapper.text()).toContain('Completado')
    expect(wrapper.text()).toContain(label)

    wrapper.unmount()
  })

  it('emits viewAnalysis when the existing analysis is opened', async () => {
    getMock.mockResolvedValue({ data: existingAnalysis({ status: 'completed' }) })

    const wrapper = mountButton({ attachment: attachment() })
    await flushPromises()

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('viewAnalysis')).toHaveLength(1)

    wrapper.unmount()
  })
})
