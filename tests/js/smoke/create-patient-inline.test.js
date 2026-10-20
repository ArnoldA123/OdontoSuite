import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Inline patient dialog opened from the treatment-plan modal: it must mount
// through the shared UiModal primitive (issue #177), name its dialog, stop
// before the API on local validation, and hand the created patient back to the
// plan modal through `created`.
const harness = vi.hoisted(() => ({
  apiPost: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(async () => ({ data: [] })),
    post: harness.apiPost,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import CreatePatientInline from '../../../resources/js/modules/treatment-plans/components/CreatePatientInline.vue'

// UiModal teleports the dialog to <body>, so the helpers read the document.
const root = () => document.querySelector('[data-create-patient]')
const dialog = () => root().querySelector('[role="dialog"]')

const setValue = async (fieldName, value) => {
  const element = root().querySelector(`[data-inline-field="${fieldName}"] input`)
  expect(element, `inline field "${fieldName}" must render an input`).toBeTruthy()
  element.value = value
  element.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const submit = async () => {
  const button = root().querySelector('[data-create-patient-submit]')
  expect(button).toBeTruthy()
  button.click()
  await flushPromises()
}

const mountInline = async () => {
  const wrapper = mount(CreatePatientInline, {
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

describe('create patient inline smoke', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    harness.apiPost.mockReset()
    harness.apiPost.mockImplementation(async (url, payload) => ({ data: { id: 21, ...payload } }))
  })

  afterEach(() => {
    document.body.innerHTML = ''
    document.body.style.overflow = ''
  })

  it('mounts through UiModal behind the data-create-patient root hook', async () => {
    const wrapper = await mountInline()

    expect(root()).toBeTruthy()
    expect(document.body.contains(root())).toBe(true)

    const target = dialog()
    expect(target.getAttribute('aria-modal')).toBe('true')
    expect(document.getElementById(target.getAttribute('aria-labelledby'))?.textContent).toContain(
      'Nuevo paciente'
    )
    expect(target.getAttribute('aria-label')).toBeNull()

    // The field focus must win over the modal's initial focus (the close button).
    expect(document.activeElement).toBe(
      root().querySelector('[data-inline-field="first_name"] input')
    )

    wrapper.unmount()
  })

  it('blocks the POST while the names and the last names are missing', async () => {
    const wrapper = await mountInline()

    await submit()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(root().querySelector('[data-inline-field="first_name"]').textContent).toContain(
      'Los nombres son obligatorios'
    )
    expect(root().querySelector('[data-inline-field="last_name"]').textContent).toContain(
      'Los apellidos son obligatorios'
    )
    expect(wrapper.emitted('created')).toBeFalsy()

    wrapper.unmount()
  })

  it('emits created with the API payload after a successful POST', async () => {
    const wrapper = await mountInline()

    await setValue('first_name', 'Ana')
    await setValue('last_name', 'Torres')
    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    const [url, payload] = harness.apiPost.mock.calls[0]
    expect(url).toBe('/api/patients')
    expect(payload.first_name).toBe('Ana')
    expect(payload.last_name).toBe('Torres')
    expect(wrapper.emitted('created')).toEqual([[{ id: 21, ...payload }]])

    wrapper.unmount()
  })
})
