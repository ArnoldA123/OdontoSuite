import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

// #391: the wizard used to be a private Teleport overlay — no dialog role, no
// accessible name, no focus trap, no Escape and no scroll lock — and a backdrop
// click threw the documented act away without a word. It now rides the repo's
// dialog primitive (`UiModal`), so the contract is asserted through the DOM the
// primitive renders: role, aria-modal, accessible name, focus trap, scroll lock
// and Escape. The guard is asserted on top: an untouched act closes at once and
// a documented one asks before discarding it.
const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  fetch: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: vi.fn(async () => ({ data: {} })),
    put: vi.fn(async () => ({ data: {} })),
    patch: vi.fn(async () => ({ data: {} })),
    del: vi.fn(async () => ({ data: {} })),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

import { useConsultation } from '../../../resources/js/composables/useConsultation'
import { useConfirm } from '../../../resources/js/composables/useConfirm'
import ConfirmDialog from '../../../resources/js/components/ui/ConfirmDialog.vue'
import ConsultationWizard from '../../../resources/js/modules/appointments/ConsultationWizard.vue'

const APPOINTMENT = {
  id: 501,
  status: 'in_consultation',
  scheduled_at: '2026-10-24T10:00:00',
  patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
  user: { id: 2, name: 'Dra. Ruiz' }
}

const CATALOG = [{ id: 11, cie_code: 'K02.1', name: 'Caries de la dentina' }]
const REASON = 'Me duele la muela desde hace tres días'
const PASSWORD = 'password123'

// `<UiConfirmDialog>` is mounted once by AppLayout in the application; the
// harness mounts the same component against the same `useConfirm()` singleton,
// wired the way AppLayout wires it (`v-model` + confirm/cancel handlers).
const ConfirmHost = defineComponent({
  setup() {
    const {
      isOpen,
      title,
      message,
      confirmText,
      cancelText,
      variant,
      handleConfirm,
      handleCancel
    } = useConfirm()

    return () =>
      h(ConfirmDialog, {
        modelValue: isOpen.value,
        title: title.value,
        message: message.value,
        confirmText: confirmText.value,
        cancelText: cancelText.value,
        variant: variant.value,
        'onUpdate:modelValue': value => {
          isOpen.value = value
        },
        onConfirm: handleConfirm,
        onCancel: handleCancel
      })
  }
})

const element = selector => {
  const found = document.querySelector(selector)
  expect(found, `${selector} must render`).toBeTruthy()
  return found
}

const panel = () => document.querySelector('[role="dialog"]')
const discardPrompt = () => document.querySelector('[role="alertdialog"]')

const clickIn = async (root, text) => {
  const button = Array.from(root.querySelectorAll('button')).find(node =>
    node.textContent.includes(text)
  )
  expect(button, `a button labelled "${text}" must render`).toBeTruthy()
  button.click()
  await flushPromises()
}

const goToStep = async id => {
  element(`#tab-${id}`).click()
  await flushPromises()
}

const setValue = async (selector, value) => {
  const target = element(selector)
  target.value = value
  target.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

// The dialog listens on `document`, the way Escape arrives from a focused
// control anywhere inside it.
const pressEscape = async () => {
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  await flushPromises()
}

// The backdrop is the node the primitive renders into <body>; the panel stops
// the click from reaching it, so this is the outside click a user makes.
const clickBackdrop = async () => {
  element('[data-overlay-backdrop="modal"]').click()
  await flushPromises()
}

const closeButton = () => element('[data-wizard-close]')

let wizardWrapper = null
let confirmWrapper = null

const mountWizard = async () => {
  wizardWrapper = mount(ConsultationWizard, {
    props: { appointment: APPOINTMENT },
    attachTo: document.body
  })
  confirmWrapper = mount(ConfirmHost, { attachTo: document.body })
  // `isOpen` lives in the composable's singleton, so opening it is what makes
  // the modal render its dialog.
  await useConsultation().openForAppointment(APPOINTMENT)
  await flushPromises()
  return wizardWrapper
}

const documentAct = async () => {
  await goToStep('evolution')
  await setValue('#cw-reason', REASON)
}

describe('consultation wizard close guard (#391)', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/diagnoses') return { data: CATALOG, meta: { total: CATALOG.length } }
      if (url.endsWith('/consultation-context')) {
        return { data: { requires_materials: false, appointment_type: { name: 'Consulta' } } }
      }
      return { data: [] }
    })
  })

  afterEach(() => {
    useConfirm().handleCancel()
    useConsultation().close()
    wizardWrapper?.unmount()
    confirmWrapper?.unmount()
    wizardWrapper = null
    confirmWrapper = null
    document.body.innerHTML = ''
  })

  it('presents the act as a labelled modal dialog with focus trapped and the page scroll locked', async () => {
    await mountWizard()

    const dialog = panel()
    expect(dialog, 'the act must be a dialog of the design system').toBeTruthy()
    expect(dialog.getAttribute('role')).toBe('dialog')
    expect(dialog.getAttribute('aria-modal')).toBe('true')

    // The accessible name is the wizard's own <h2>, through aria-labelledby.
    const labelledby = dialog.getAttribute('aria-labelledby')
    expect(labelledby, 'the dialog must be named through aria-labelledby').toBeTruthy()
    const heading = document.getElementById(labelledby)
    expect(heading, `#${labelledby} must exist inside the dialog`).toBeTruthy()
    expect(dialog.contains(heading)).toBe(true)
    expect(heading.tagName).toBe('H2')
    expect(heading.textContent.trim()).toBe('Expediente de Cita')

    // Focus starts inside the surface and the page behind it does not scroll.
    expect(dialog.contains(document.activeElement)).toBe(true)
    expect(document.body.style.overflow).toBe('hidden')
  })

  it('closes an untouched act from its own close button without asking', async () => {
    const wrapper = await mountWizard()

    closeButton().click()
    await flushPromises()

    expect(discardPrompt(), 'nothing was documented, so nothing is asked').toBeFalsy()
    expect(useConsultation().isOpen.value).toBe(false)
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('closes an untouched act from the backdrop without asking', async () => {
    await mountWizard()

    await clickBackdrop()

    expect(discardPrompt(), 'nothing was documented, so nothing is asked').toBeFalsy()
    expect(useConsultation().isOpen.value).toBe(false)
  })

  it('closes an untouched act with Escape without asking', async () => {
    await mountWizard()

    await pressEscape()

    expect(discardPrompt(), 'nothing was documented, so nothing is asked').toBeFalsy()
    expect(useConsultation().isOpen.value).toBe(false)
  })

  it('asks before discarding a documented act and closes only on confirm', async () => {
    const wrapper = await mountWizard()
    await documentAct()

    closeButton().click()
    await flushPromises()

    const prompt = discardPrompt()
    expect(prompt, 'a documented act must be confirmed before it is discarded').toBeTruthy()
    expect(prompt.textContent).toContain('Descartar el cierre')
    expect(prompt.textContent).toContain('¿Descartar la consulta?')
    // The confirmation owns the keyboard while it is on top of the act.
    expect(prompt.contains(document.activeElement)).toBe(true)
    expect(useConsultation().isOpen.value).toBe(true)

    // Cancelling keeps the act and what it documents.
    await clickIn(prompt, 'Seguir documentando')
    expect(discardPrompt()).toBeFalsy()
    expect(useConsultation().isOpen.value).toBe(true)
    expect(element('#cw-reason').value).toBe(REASON)
    expect(wrapper.emitted('close')).toBeFalsy()
    // The confirmation was a dialog on top of the act: closing it hands the
    // focus back and leaves the page scroll owned by the act (#391).
    expect(panel().contains(document.activeElement)).toBe(true)
    expect(document.body.style.overflow).toBe('hidden')

    // Confirming discards it.
    closeButton().click()
    await flushPromises()
    await clickIn(discardPrompt(), 'Descartar')

    expect(useConsultation().isOpen.value).toBe(false)
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('asks on Escape for a documented act and keeps the act closable afterwards', async () => {
    await mountWizard()
    await documentAct()

    await pressEscape()

    expect(useConsultation().isOpen.value, 'Escape must not discard the act').toBe(true)
    expect(discardPrompt(), 'Escape must ask first').toBeTruthy()

    // Escape on the confirmation dismisses the question, not the act.
    await pressEscape()
    expect(discardPrompt()).toBeFalsy()
    expect(useConsultation().isOpen.value).toBe(true)

    // The vetoed question must not leave the act impossible to close.
    closeButton().click()
    await flushPromises()
    expect(discardPrompt(), 'a new close request asks again').toBeTruthy()
    await clickIn(discardPrompt(), 'Descartar')
    expect(useConsultation().isOpen.value).toBe(false)
  })

  it('asks on a backdrop click for a documented act', async () => {
    await mountWizard()
    await documentAct()

    await clickBackdrop()

    expect(useConsultation().isOpen.value, 'the backdrop must not discard the act').toBe(true)
    expect(discardPrompt(), 'the backdrop must ask first').toBeTruthy()

    await clickIn(discardPrompt(), 'Descartar')
    expect(useConsultation().isOpen.value).toBe(false)
  })

  it('ignores the signature password when deciding whether the act documents anything', async () => {
    await mountWizard()

    await goToStep('signature')
    await setValue('#cw-signature', PASSWORD)

    closeButton().click()
    await flushPromises()

    // The password exists only for the request that signs the record, so it is
    // neither content to lose nor a reason to ask.
    expect(discardPrompt()).toBeFalsy()
    expect(useConsultation().isOpen.value).toBe(false)
  })
})
