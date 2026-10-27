import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'

// #402 — UiSelect declared `Boolean` in the modelValue union before `String`,
// so Vue cast the legitimate `''` value to `true`: the "Todos" option (value
// `''`) never matched, the trigger fell back to the placeholder and every
// option reported aria-selected="false". The primitive keeps Boolean support
// (F11, `is_active`) and the empty string intact.
import Select from '../../../resources/js/components/ui/Select.vue'

const STATUS_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Activos' },
  { value: 'inactive', label: 'Inactivos' }
]

const mountSelect = props =>
  mount(Select, { props: { options: STATUS_OPTIONS, ...props } })

const selectedOptions = wrapper =>
  wrapper.findAll('[role="listbox"] button[aria-selected="true"]')

describe('UiSelect empty string modelValue (#402)', () => {
  it('shows the empty-value option label and marks it selected', async () => {
    const wrapper = mountSelect({ modelValue: '' })

    const trigger = wrapper.find('button[aria-haspopup="true"]')
    expect(trigger.text()).toBe('Todos')

    await trigger.trigger('click')

    const selected = selectedOptions(wrapper)
    expect(selected).toHaveLength(1)
    expect(selected[0].text()).toBe('Todos')

    wrapper.unmount()
  })

  it('keeps the placeholder when the model value is null', () => {
    const wrapper = mountSelect({ modelValue: null })

    expect(wrapper.find('button[aria-haspopup="true"]').text()).toBe('Seleccionar...')

    wrapper.unmount()
  })

  it('keeps a non-empty string and a number selecting their own option', () => {
    const stringSelect = mountSelect({ modelValue: 'active' })
    expect(stringSelect.find('button[aria-haspopup="true"]').text()).toBe('Activos')

    const numberSelect = mountSelect({
      options: [{ value: 1, label: 'Uno' }],
      modelValue: 1
    })
    expect(numberSelect.find('button[aria-haspopup="true"]').text()).toBe('Uno')

    stringSelect.unmount()
    numberSelect.unmount()
  })
})
