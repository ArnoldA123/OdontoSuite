import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import Button from '../../../resources/js/components/ui/Button.vue'

// #501 — the primitive rendered `<button>` without `type`, so the platform
// default (`submit`) applied: any decorative button inside a `<form>` sent it.
// The plan form's «Nuevo procedimiento» submitted the plan with one line and
// closed the modal mid-edit; the same trap was open in every form of the SPA.
describe('UiButton type defaults to button (#501)', () => {
  it('renders type="button" when the caller does not ask for a submit', () => {
    const wrapper = mount(Button, { slots: { default: 'Cancelar' } })

    expect(wrapper.get('button').attributes('type')).toBe('button')
  })

  it('keeps the submit a caller declares', () => {
    const wrapper = mount(Button, { attrs: { type: 'submit' }, slots: { default: 'Guardar' } })

    expect(wrapper.get('button').attributes('type')).toBe('submit')
  })

  it('does not submit the form it lives in when it is pressed', async () => {
    const form = document.createElement('form')
    let submits = 0
    form.addEventListener('submit', event => {
      event.preventDefault()
      submits += 1
    })
    document.body.appendChild(form)

    const wrapper = mount(Button, { attachTo: form, slots: { default: 'Nuevo' } })
    await wrapper.get('button').trigger('click')

    expect(submits).toBe(0)
    wrapper.unmount()
    form.remove()
  })
})
