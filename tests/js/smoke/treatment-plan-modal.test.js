import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Create/edit treatment-plan modal contract: the dialog root hook and
// accessibility wiring, local validation that stops before the API, the live
// total rendered through the shared currency formatter, and Escape honouring
// the dirty guard instead of discarding edits silently. UiModal teleports the
// dialog to <body>, so every assertion queries the document.
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

// The dialog lives under <body> through UiModal's Teleport, outside the
// wrapper's tree, so the helpers below read the rendered document.
const root = () => document.querySelector('[data-plan-modal]')
const dialog = () => root().querySelector('[role="dialog"]')
const planTitle = () => document.querySelector('[data-plan-title]')

const setValue = async (element, value) => {
  expect(element, 'the target input must render').toBeTruthy()
  element.value = value
  element.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
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
    document.body.style.overflow = ''
  })

  it('mounts in create mode behind the data-plan-modal root hook', async () => {
    const wrapper = await mountModal()

    expect(root()).toBeTruthy()
    expect(document.body.contains(root())).toBe(true)
    expect(planTitle().textContent).toContain('Nuevo Plan')

    const target = dialog()
    expect(target.getAttribute('aria-modal')).toBe('true')
    expect(target.getAttribute('aria-labelledby')).toBe(planTitle().getAttribute('id'))

    wrapper.unmount()
  })

  it('keeps a real submit control associated with its form so Enter still saves', async () => {
    const wrapper = await mountModal()

    const form = document.querySelector('#treatment-plan-form')
    const submitButton = document.querySelector('[data-plan-submit]')

    expect(form).toBeTruthy()
    expect(submitButton.getAttribute('type')).toBe('submit')
    expect(submitButton.getAttribute('form')).toBe('treatment-plan-form')

    wrapper.unmount()
  })

  it('blocks submission without a patient and emits nothing', async () => {
    const wrapper = await mountModal()

    expect(root().querySelector('[data-plan-error="patient_id"]')).toBeNull()

    root().querySelector('[data-plan-submit]').click()
    await flushPromises()

    expect(root().querySelector('[data-plan-error="patient_id"]').textContent).toContain(
      'Selecciona un paciente'
    )
    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(wrapper.emitted('saved')).toBeFalsy()

    wrapper.unmount()
  })

  it('keeps data-plan-total in sync through the shared formatter', async () => {
    const wrapper = await mountModal()
    const total = () => normalize(document.querySelector('[data-plan-total]').textContent)

    expect(total()).toContain(normalize(formatCurrency(0)))

    const item = root().querySelector('[data-plan-item]')
    await setValue(item.querySelector('[data-plan-item-field="name"] input'), 'Limpieza dental')
    await setValue(item.querySelector('[data-plan-item-field="quantity"] input'), '2')
    await setValue(item.querySelector('[data-plan-item-field="unit_cost"] input'), '50')

    expect(total()).toContain(normalize(formatCurrency(100)))
    expect(normalize(item.querySelector('[data-plan-item-total]').textContent)).toContain(
      normalize(formatCurrency(100))
    )

    wrapper.unmount()
  })

  it('asks the dirty guard before Escape discards edits', async () => {
    const wrapper = await mountModal()

    await setValue(
      root().querySelector('[data-plan-field="title"] input'),
      'Rehabilitación superior'
    )

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
