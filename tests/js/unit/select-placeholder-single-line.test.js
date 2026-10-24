import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'

// #346 — a placeholder is a single-line label. The reported case was the
// professional field of the continuity schedule modal, where the copy wrapped
// inside a two-column field. The primitive now owns the one-line contract so
// no consumer can reintroduce the wrap with a longer placeholder.
import Select from '../../../resources/js/components/ui/Select.vue'

const mountSelect = placeholder =>
  mount(Select, {
    props: {
      modelValue: null,
      options: [{ value: 1, label: 'Una opción' }],
      placeholder
    }
  })

describe('select primitive placeholder (#346)', () => {
  it('keeps the placeholder on one line', () => {
    const wrapper = mountSelect('Seleccionar profesional')

    const placeholder = wrapper.find('button[aria-haspopup="true"] span.text-theme-secondary')
    expect(placeholder.text()).toBe('Seleccionar profesional')
    // Tailwind `truncate` is the single-line contract: nowrap + ellipsis.
    expect(placeholder.classes()).toContain('truncate')

    wrapper.unmount()
  })

  it('keeps the selected value on one line as well', () => {
    const wrapper = mount(Select, {
      props: {
        modelValue: 1,
        options: [{ value: 1, label: 'Dra. Elena Paredes del Valle' }]
      }
    })

    const value = wrapper.find('button[aria-haspopup="true"] span.text-theme-primary')
    expect(value.classes()).toContain('truncate')

    wrapper.unmount()
  })
})
