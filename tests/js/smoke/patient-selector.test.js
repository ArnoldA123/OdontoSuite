import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// #412 / T4 (S11, S12) — the patient selector must forward the full name to
// the search endpoint and render the row from the fields it publishes
// (`first_name` / `last_name`), never from a `name` key that a patient row may
// not carry.
const harness = vi.hoisted(() => ({ apiGet: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({ get: harness.apiGet })
}))

import PatientSelector from '../../../resources/js/components/ui/PatientSelector.vue'

const PATIENT = {
  id: 12,
  first_name: 'Gonzalo',
  last_name: 'Velasco',
  document_number: '45678912',
  email: 'gonzalo.velasco@example.com',
  phone: '987654321',
  age: 36
}

const mountSelector = async () => {
  const wrapper = mount(PatientSelector)
  await flushPromises()
  return wrapper
}

describe('patient selector full-name search', () => {
  beforeEach(() => {
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/patients/search') return { data: [PATIENT] }
      return { data: [PATIENT] }
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('sends the typed full name to the search endpoint as a single q', async () => {
    const wrapper = await mountSelector()

    vi.useFakeTimers()
    await wrapper.find('input').setValue('Gonzalo Velasco')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(harness.apiGet).toHaveBeenCalledWith('/api/patients/search', {
      params: { q: 'Gonzalo Velasco' }
    })

    wrapper.unmount()
  })

  it('renders the result row as "Gonzalo Velasco" without an undefined', async () => {
    const wrapper = await mountSelector()

    vi.useFakeTimers()
    await wrapper.find('input').setValue('Gonzalo Velasco')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    const row = wrapper.find('.max-h-48 > div')
    expect(row.exists()).toBe(true)
    expect(row.text()).toContain('Gonzalo Velasco')
    expect(row.text()).not.toContain('undefined')

    wrapper.unmount()
  })
})

// #452 (U11, states) — the selector's own read is the only loading read that
// `/specialty-records` performs on a cold entry, so it has to publish the shared
// placeholder (`.loading-spinner-container`), not a hand-rolled `animate-spin`
// ring that neither the instrument nor a consumer can see.
describe('patient selector loading state', () => {
  it('publishes the shared spinner while the directory read is pending', async () => {
    let release = () => {}

    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(
      () =>
        new Promise(resolve => {
          release = resolve
        })
    )

    const wrapper = mount(PatientSelector)
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-patient-selector-loading]').exists()).toBe(true)
    expect(wrapper.find('.loading-spinner-container').exists()).toBe(true)
    expect(wrapper.find('.animate-spin').exists()).toBe(false)

    release({ data: [PATIENT] })
    await flushPromises()

    expect(wrapper.find('[data-patient-selector-loading]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Gonzalo Velasco')
    expect(wrapper.text()).not.toContain('undefined')

    wrapper.unmount()
  })
})
