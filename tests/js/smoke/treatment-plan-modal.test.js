import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Create/edit treatment-plan modal contract: the dialog root hook and
// accessibility wiring, local validation that stops before the API, the live
// total rendered through the shared currency formatter, and Escape honouring
// the dirty guard instead of discarding edits silently.
const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  confirm: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: harness.apiPost,
    put: harness.apiPut,
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
vi.mock('../../../resources/js/composables/useConfirm', async () => {
  const { ref } = await import('vue')
  return {
    confirmIsOpen: ref(false),
    confirmTitle: ref(''),
    confirmMessage: ref(''),
    confirmConfirmText: ref(''),
    confirmCancelText: ref(''),
    confirmVariant: ref('default'),
    useConfirm: () => ({
      confirm: harness.confirm,
      handleConfirm: () => {},
      handleCancel: () => {}
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import TreatmentPlanModal from '../../../resources/js/modules/treatment-plans/components/TreatmentPlanModal.vue'
import { formatCurrency } from '../../../resources/js/composables/useFormatters'

// Intl renders U+00A0 between the currency glyph and the amount.
const normalize = value => value.replace(/\u00a0/g, ' ')

const pressEscape = () => {
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
}

const mountModal = async (props = {}) => {
  const wrapper = mount(TreatmentPlanModal, {
    props: { plan: null, isEdit: false, ...props },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

describe('treatment plan modal smoke', () => {
  beforeEach(() => {
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async () => ({ data: [] }))
    harness.apiPost.mockReset()
    harness.apiPost.mockImplementation(async (url, payload) => ({ data: { id: 11, ...payload } }))
    harness.apiPut.mockReset()
    harness.confirm.mockReset()
    harness.confirm.mockResolvedValue(false)
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('mounts in create mode behind the data-plan-modal root hook', async () => {
    const wrapper = await mountModal()

    expect(wrapper.find('[data-plan-modal]').exists()).toBe(true)
    expect(wrapper.find('[data-plan-title]').text()).toContain('Nuevo Plan')

    const dialog = wrapper.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(dialog.attributes('aria-labelledby')).toBe(
      wrapper.find('[data-plan-title]').attributes('id')
    )

    wrapper.unmount()
  })

  it('blocks submission without a patient and emits nothing', async () => {
    const wrapper = await mountModal()

    expect(wrapper.find('[data-plan-error="patient_id"]').exists()).toBe(false)

    await wrapper.find('[data-plan-submit]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-plan-error="patient_id"]').text()).toContain(
      'Selecciona un paciente'
    )
    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(wrapper.emitted('saved')).toBeFalsy()

    wrapper.unmount()
  })

  it('keeps data-plan-total in sync through the shared formatter', async () => {
    const wrapper = await mountModal()
    const total = () => normalize(wrapper.find('[data-plan-total]').text())

    expect(total()).toContain(normalize(formatCurrency(0)))

    const item = wrapper.find('[data-plan-item]')
    await item.find('[data-plan-item-field="name"] input').setValue('Limpieza dental')
    await item.find('[data-plan-item-field="quantity"] input').setValue('2')
    await item.find('[data-plan-item-field="unit_cost"] input').setValue('50')
    await flushPromises()

    expect(total()).toContain(normalize(formatCurrency(100)))
    expect(normalize(item.find('[data-plan-item-total]').text())).toContain(
      normalize(formatCurrency(100))
    )

    wrapper.unmount()
  })

  it('asks the dirty guard before Escape discards edits', async () => {
    const wrapper = await mountModal()

    await wrapper.find('[data-plan-field="title"] input').setValue('Rehabilitación superior')

    pressEscape()
    await flushPromises()

    expect(harness.confirm).toHaveBeenCalledTimes(1)
    expect(harness.confirm.mock.calls[0][0].message).toMatch(/cambios sin guardar/i)
    expect(wrapper.emitted('close')).toBeFalsy()

    harness.confirm.mockResolvedValueOnce(true)
    pressEscape()
    await flushPromises()

    expect(wrapper.emitted('close')).toHaveLength(1)

    wrapper.unmount()
  })
})
