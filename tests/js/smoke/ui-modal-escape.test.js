import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import UiModal from '../../../resources/js/components/ui/Modal.vue'

// UiModal keyboard contract: while the modal is open, Escape emits the same
// close signals as the X button (update:modelValue=false + close) — including
// when the consumer mounts the modal with a STATIC modelValue (the
// PatientFormModal pattern: `<UiModal model-value ...>`). The document-level
// keydown listener lives for the component's mounted lifetime and is removed
// on unmount. persistent / closeOnEscape=false keep vetoing Escape.
const pressEscape = () => {
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
}

let wrapper = null

const mountModal = props =>
  mount(UiModal, {
    props: { title: 'Nuevo Paciente', ...props },
    slots: { default: '<form data-patient-form />' },
    attachTo: document.body
  })

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
})

describe('UiModal Escape contract', () => {
  it('emits update:modelValue(false) and close on Escape while open (static model-value)', () => {
    wrapper = mountModal({ modelValue: true })

    pressEscape()

    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('emits close on Escape when the modal is opened via v-model after mount', async () => {
    wrapper = mountModal({ modelValue: false })
    await wrapper.setProps({ modelValue: true })

    pressEscape()

    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('ignores Escape while the modal is closed', () => {
    wrapper = mountModal({ modelValue: false })

    pressEscape()

    expect(wrapper.emitted('close')).toBeUndefined()
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('honours persistent and closeOnEscape=false on Escape', () => {
    wrapper = mountModal({ modelValue: true, persistent: true })
    pressEscape()
    expect(wrapper.emitted('close')).toBeUndefined()
    wrapper.unmount()

    wrapper = mountModal({ modelValue: true, closeOnEscape: false })
    pressEscape()
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('removes the document keydown listener on unmount', () => {
    wrapper = mountModal({ modelValue: true })
    wrapper.unmount()

    pressEscape()

    expect(wrapper.emitted('close')).toBeUndefined()
  })
})
