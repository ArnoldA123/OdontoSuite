import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Modal from '../../../resources/js/components/ui/Modal.vue'

/**
 * The module modals are mounted by their parent with a static
 * `model-value="true"` (the page renders `<XModal v-if="open" />`), which is
 * the repo pattern for `PatientFormModal` too. The modelValue watch never
 * observes a change in that mount, so the scroll lock and the focus trap used
 * to stay idle until the next toggle.
 */
describe('Modal static mount', () => {
  afterEach(() => {
    document.body.style.overflow = ''
    document.body.innerHTML = ''
  })

  it('locks the page scroll when it mounts already open', () => {
    const wrapper = mount(Modal, {
      props: { modelValue: true, title: 'Historia clínica' },
      attachTo: document.body
    })

    expect(document.body.style.overflow).toBe('hidden')

    wrapper.unmount()
  })

  it('releases the lock when it unmounts', () => {
    const wrapper = mount(Modal, {
      props: { modelValue: true },
      attachTo: document.body
    })
    expect(document.body.style.overflow).toBe('hidden')

    wrapper.unmount()

    expect(document.body.style.overflow).toBe('')
  })

  it('does not touch the lock when it mounts closed', () => {
    document.body.style.overflow = 'hidden'

    const wrapper = mount(Modal, {
      props: { modelValue: false },
      attachTo: document.body
    })

    expect(document.body.style.overflow).toBe('hidden')

    document.body.style.overflow = ''
    wrapper.unmount()
  })

  it('names the dialog from the title when it renders its own header', () => {
    const wrapper = mount(Modal, {
      props: { modelValue: true, title: 'Nueva historia' },
      attachTo: document.body
    })

    const dialog = document.querySelector('[role="dialog"]')
    const labelledBy = dialog.getAttribute('aria-labelledby')

    expect(labelledBy).toBeTruthy()
    expect(document.getElementById(labelledBy)?.textContent).toContain('Nueva historia')

    wrapper.unmount()
  })

  it('falls back to aria-label when a consumer replaces the header', () => {
    const wrapper = mount(Modal, {
      props: { modelValue: true, title: 'Historia clínica' },
      slots: { header: '<div data-custom-header>custom</div>' },
      attachTo: document.body
    })

    const dialog = document.querySelector('[role="dialog"]')

    expect(dialog.getAttribute('aria-labelledby')).toBeNull()
    expect(dialog.getAttribute('aria-label')).toBe('Historia clínica')

    wrapper.unmount()
  })
})
