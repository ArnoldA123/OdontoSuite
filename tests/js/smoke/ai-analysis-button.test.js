import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const { getMock, allowed } = vi.hoisted(() => ({
  getMock: vi.fn(),
  allowed: { value: true }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(async () => ({ data: {} })),
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

const mountButton = props =>
  mount(Button, {
    props: { attachmentId: 3, ...props },
    global: { plugins: [uiComponents] }
  })

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: null })
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
