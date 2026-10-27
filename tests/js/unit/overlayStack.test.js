import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Modal from '../../../resources/js/components/ui/Modal.vue'
import Sheet from '../../../resources/js/components/ui/Sheet.vue'
import ConfirmDialog from '../../../resources/js/components/ui/ConfirmDialog.vue'

// Issue #423 — every overlay painted at `z-50`, so a confirmation asked from an
// open dialog stayed underneath it whenever the confirmation was mounted first
// (the global `UiConfirmDialog` of AppLayout mounts before every page modal).
// The open set owns the layer and the page scroll, across both primitives.
const mounted = []

const track = wrapper => {
  mounted.push(wrapper)
  return wrapper
}

const backdropOf = (index = 0) => document.querySelectorAll('[data-overlay-backdrop]')[index]
const layerOf = element => Number(element?.style.zIndex)
// Paints and hit-testing follow the stacking context, not the DOM slot: the
// confirmation teleports before the detail (its anchor exists since AppLayout
// mounted) and must still land on top.
const layerOfRole = role =>
  layerOf(document.querySelector(`[role="${role}"]`)?.closest('[data-overlay-backdrop]'))

const mountModal = (props = {}) =>
  track(
    mount(Modal, {
      props: { modelValue: true, title: 'Detalle', ...props },
      attachTo: document.body
    })
  )

const mountSheet = (props = {}) =>
  track(
    mount(Sheet, {
      props: { modelValue: false, title: 'Navegación', ...props },
      attachTo: document.body
    })
  )

const pressEscape = () => {
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
}

beforeEach(() => {
  document.body.style.overflow = ''
})

afterEach(() => {
  while (mounted.length > 0) mounted.pop().unmount()
  document.body.innerHTML = ''
  document.body.style.overflow = ''
})

describe('overlay stacking (issue #423)', () => {
  it('paints the dialog opened last above the one already open', async () => {
    mountModal({ title: 'Primero' })
    mountModal({ title: 'Segundo' })
    await flushPromises()

    expect(layerOf(backdropOf(1))).toBeGreaterThan(layerOf(backdropOf(0)))
  })

  it('lands a confirmation asked from inside a dialog above that dialog', async () => {
    // Mount order mirrors the app: the global confirmation lives in AppLayout
    // and the detail modal mounts later, when the page opens it.
    const confirmDialog = track(
      mount(ConfirmDialog, {
        props: {
          modelValue: false,
          title: 'Anular análisis',
          message: '¿Anular este análisis?'
        },
        attachTo: document.body
      })
    )
    mountModal({ title: 'Análisis de IA' })

    await confirmDialog.setProps({ modelValue: true })
    await flushPromises()

    expect(layerOfRole('alertdialog')).toBeGreaterThan(layerOfRole('dialog'))
  })

  it('gives the slot back to the outer dialog when the top one closes', async () => {
    mountModal({ title: 'Detalle' })
    const inner = mountModal({ title: 'Confirmar' })

    await inner.setProps({ modelValue: false })
    await flushPromises()

    mountModal({ title: 'Otra confirmación' })
    await flushPromises()

    expect(layerOf(backdropOf(1))).toBeGreaterThan(layerOf(backdropOf(0)))
  })
})

describe('overlay page scroll (issue #423)', () => {
  it('keeps the page locked while a sheet underneath is still open', async () => {
    const sheet = mountSheet()
    await sheet.setProps({ modelValue: true })
    await flushPromises()
    expect(document.body.style.overflow).toBe('hidden')

    const modal = mountModal()
    expect(document.body.style.overflow).toBe('hidden')

    await modal.setProps({ modelValue: false })
    await flushPromises()
    expect(document.body.style.overflow).toBe('hidden')

    await sheet.setProps({ modelValue: false })
    await flushPromises()
    expect(document.body.style.overflow).toBe('')
  })
})

describe('overlay escape ownership (issue #423)', () => {
  it('answers Escape only on the top-most overlay across primitives', async () => {
    const sheet = mountSheet()
    await sheet.setProps({ modelValue: true })
    await flushPromises()

    const modal = mountModal()

    pressEscape()

    expect(modal.emitted('update:modelValue')).toEqual([[false]])
    expect(sheet.emitted('update:modelValue')).toBeFalsy()
  })
})
