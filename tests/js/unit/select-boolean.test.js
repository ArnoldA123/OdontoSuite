import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Select from '../../../resources/js/components/ui/Select.vue'

// F11: UiSelect.modelValue declared [String, Number, Array], so the edit
// modal's Boolean `is_active` binding logged a prop-type warning. The fix is
// additive: Boolean joins the accepted types and string/number/array keep
// working unchanged.
const BOOLEAN_OPTIONS = [
  { value: true, label: 'Sí' },
  { value: false, label: 'No' }
]

const mountSelect = props => mount(Select, { props: { options: BOOLEAN_OPTIONS, ...props } })

const invalidModelValueWarnings = warn =>
  warn.mock.calls.filter(call => {
    const message = String(call[0])
    return (
      message.includes('Invalid prop') &&
      message.includes('prop "modelValue"') &&
      message.includes('Expected String | Number | Array')
    )
  })

describe('UiSelect Boolean modelValue', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('accepts a Boolean true without a prop-type warning and selects Sí', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})

    const wrapper = mountSelect({ modelValue: true })

    expect(wrapper.find('button').text()).toContain('Sí')
    expect(invalidModelValueWarnings(warn)).toHaveLength(0)
    wrapper.unmount()
  })

  it('accepts a Boolean false without a prop-type warning and selects No', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})

    const wrapper = mountSelect({ modelValue: false })

    expect(wrapper.find('button').text()).toContain('No')
    expect(invalidModelValueWarnings(warn)).toHaveLength(0)
    wrapper.unmount()
  })

  it('keeps String, Number and Array model values working', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})

    const stringSelect = mountSelect({
      options: [{ value: 'a', label: 'Alfa' }],
      modelValue: 'a'
    })
    expect(stringSelect.find('button').text()).toContain('Alfa')

    const numberSelect = mountSelect({
      options: [{ value: 1, label: 'Uno' }],
      modelValue: 1
    })
    expect(numberSelect.find('button').text()).toContain('Uno')

    const arraySelect = mountSelect({
      options: [
        { value: 'a', label: 'Alfa' },
        { value: 'b', label: 'Beta' }
      ],
      modelValue: ['a'],
      multiple: true
    })
    await arraySelect.find('button').trigger('click')
    const selected = arraySelect
      .findAll('button')
      .find(button => button.attributes('aria-selected') === 'true')
    expect(selected.text()).toContain('Alfa')

    expect(invalidModelValueWarnings(warn)).toHaveLength(0)
    stringSelect.unmount()
    numberSelect.unmount()
    arraySelect.unmount()
  })
})
