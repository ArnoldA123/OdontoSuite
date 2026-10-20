import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import Modal from '../../../resources/js/components/ui/Modal.vue'

// Issue #177 — the primitive could not carry a consumer root hook through its
// Teleport nor let a consumer cancel a close before the dirty guard ran. Both
// capabilities live here, together with the nested-modal behaviour the two
// migrated consumers need: only the top-most modal answers Escape, and closing
// the inner one keeps the page scroll locked for the outer one.
const pressEscape = () => {
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
}

const pressTab = () => {
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))
}

const mountModal = (options = {}) =>
  mount(Modal, {
    props: { modelValue: true, title: 'Modal', ...options.props },
    attrs: options.attrs,
    slots: options.slots,
    attachTo: document.body
  })

beforeEach(() => {
  document.body.style.overflow = ''
})

afterEach(() => {
  document.body.innerHTML = ''
  document.body.style.overflow = ''
  delete HTMLElement.prototype.offsetParent
})

describe('Modal root hooks (issue #177)', () => {
  it('forwards the consumer attributes to the teleported root', () => {
    const wrapper = mountModal({
      attrs: { 'data-plan-modal': '', class: 'z-[60]' }
    })

    const root = document.querySelector('[data-plan-modal]')
    expect(root).toBeTruthy()
    expect(root.classList.contains('z-[60]')).toBe(true)
    expect(root.querySelector('[role="dialog"]')).toBeTruthy()

    wrapper.unmount()
  })

  it('lets the consumer name the dialog from its own header', () => {
    const wrapper = mountModal({
      props: { title: 'Nuevo Plan', labelledby: 'plan-title' },
      slots: {
        header: '<h2 id="plan-title" data-plan-title>Nuevo Plan</h2>',
        default: '<p>body</p>'
      }
    })

    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog.getAttribute('aria-labelledby')).toBe('plan-title')
    expect(dialog.getAttribute('aria-label')).toBeNull()

    wrapper.unmount()
  })
})

describe('Modal cancellable close (issue #177)', () => {
  it('keeps the modal open when the guard refuses the close', async () => {
    const beforeClose = vi.fn(async () => false)
    const wrapper = mountModal({ props: { beforeClose } })

    document.querySelector('[aria-label="Cerrar modal"]').click()
    await flushPromises()

    expect(beforeClose).toHaveBeenCalledTimes(1)
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    expect(wrapper.emitted('close')).toBeFalsy()

    wrapper.unmount()
  })

  it('guards Escape and the backdrop click with the same hook', async () => {
    const beforeClose = vi.fn(async () => false)
    const wrapper = mountModal({ props: { beforeClose }, attrs: { 'data-modal-overlay': '' } })

    pressEscape()
    await flushPromises()
    document.querySelector('[data-modal-overlay]').click()
    await flushPromises()

    expect(beforeClose).toHaveBeenCalledTimes(2)
    expect(wrapper.emitted('close')).toBeFalsy()

    wrapper.unmount()
  })

  it('closes once when the guard allows it', async () => {
    const beforeClose = vi.fn(async () => true)
    const wrapper = mountModal({ props: { beforeClose } })

    document.querySelector('[aria-label="Cerrar modal"]').click()
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
    expect(wrapper.emitted('close')).toHaveLength(1)

    wrapper.unmount()
  })

  it('runs the guard once while it is still resolving', async () => {
    let releaseGuard
    const beforeClose = vi.fn(
      () =>
        new Promise(resolve => {
          releaseGuard = resolve
        })
    )
    const wrapper = mountModal({ props: { beforeClose } })

    document.querySelector('[aria-label="Cerrar modal"]').click()
    document.querySelector('[aria-label="Cerrar modal"]').click()
    await flushPromises()

    expect(beforeClose).toHaveBeenCalledTimes(1)

    releaseGuard(true)
    await flushPromises()

    expect(wrapper.emitted('close')).toHaveLength(1)

    wrapper.unmount()
  })

  it('closes without a guard when none is provided', async () => {
    const wrapper = mountModal()

    document.querySelector('[aria-label="Cerrar modal"]').click()
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])

    wrapper.unmount()
  })
})

describe('Modal nested instances (issue #177)', () => {
  it('answers Escape only on the top-most modal', async () => {
    const parent = mountModal({ props: { title: 'Plan' } })
    const child = mountModal({ props: { title: 'Paciente' } })

    pressEscape()
    await flushPromises()

    expect(child.emitted('close')).toHaveLength(1)
    expect(parent.emitted('close')).toBeFalsy()

    parent.unmount()
    child.unmount()
  })

  it('keeps the page locked while the outer modal is still open', async () => {
    const parent = mountModal({ props: { title: 'Plan' } })
    const child = mountModal({ props: { title: 'Paciente' } })

    expect(document.body.style.overflow).toBe('hidden')

    child.unmount()
    expect(document.body.style.overflow).toBe('hidden')

    parent.unmount()
    expect(document.body.style.overflow).toBe('')
  })

  it('lets the top-most modal own the Tab trap', async () => {
    Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
      configurable: true,
      get() {
        return this.parentElement
      }
    })

    const parent = mountModal({
      props: { title: 'Plan' },
      slots: { default: '<button id="parent-action">Parent</button>' }
    })
    const child = mountModal({
      // `closable: false` keeps the default header's close button out of the
      // focusable list, so the forward wrap target is the first slot control.
      props: { title: 'Paciente', closable: false },
      slots: { default: '<button id="child-a">A</button><button id="child-b">B</button>' }
    })
    await flushPromises()

    document.getElementById('child-b').focus()
    pressTab()

    expect(document.activeElement.id).toBe('child-a')

    parent.unmount()
    child.unmount()
  })
})
