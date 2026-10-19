import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// PatientSelector contract after the patients API fix: the list/selector
// receives `document_number` (string or null), not `dni`, and server search
// results must render as-is (email/document matches included).
const harness = vi.hoisted(() => ({ apiGet: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({ get: harness.apiGet })
}))

import PatientSelector from '../../../resources/js/components/ui/PatientSelector.vue'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com',
  age: 36
}

const mountSelector = async () => {
  const wrapper = mount(PatientSelector)
  await flushPromises()
  return wrapper
}

const rows = wrapper => wrapper.findAll('.max-h-48 > div')

describe('patient selector document number', () => {
  beforeEach(() => {
    harness.apiGet.mockReset()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders document_number as the DNI and emits the normalized patient', async () => {
    harness.apiGet.mockResolvedValue({ data: [patient] })

    const wrapper = await mountSelector()
    const row = rows(wrapper)[0]
    expect(row.text()).toContain('45678912')

    await row.trigger('click')

    const selected = wrapper.emitted('update:modelValue')[0][0]
    expect(selected).toMatchObject({ id: 7, dni: '45678912' })
    expect(wrapper.text()).toContain('DNI: 45678912')

    wrapper.unmount()
  })

  it('renders server search matches even when they match outside the local filter', async () => {
    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/patients/search') {
        return {
          data: [
            {
              id: 9,
              first_name: 'Bea',
              last_name: 'Ruiz',
              document_number: null,
              phone: '',
              email: 'match@example.com'
            }
          ]
        }
      }
      return { data: [patient] }
    })

    const wrapper = await mountSelector()

    vi.useFakeTimers()
    await wrapper.find('input').setValue('match@example.com')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(harness.apiGet).toHaveBeenCalledWith('/api/patients/search', {
      params: { q: 'match@example.com' }
    })
    expect(wrapper.text()).toContain('Bea Ruiz')

    wrapper.unmount()
  })

  it('reloads the full list when the query drops below two characters', async () => {
    harness.apiGet.mockResolvedValue({ data: [patient] })

    const wrapper = await mountSelector()
    expect(harness.apiGet).toHaveBeenCalledTimes(1)

    vi.useFakeTimers()
    await wrapper.find('input').setValue('a')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    const listCalls = harness.apiGet.mock.calls.filter(([url]) => url === '/api/patients')
    expect(listCalls).toHaveLength(2)

    wrapper.unmount()
  })
})
