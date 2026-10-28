import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

// Issue #224: the medical-records surfaces had shell-level coverage but no
// component suite that walks a whole flow. This file mounts the modals and the
// page against a mocked API and pins the flows the issue asks for: a 422 mapped
// per field, an evolution with a follow-up date, an addendum signed over the
// original (#292) and the 10 MB attachment limit. The category filter and the
// evolutions retry are small enough to cover here too.
//
// `useMedicalRecords` is deliberately left real: it is the data layer under
// test, and every write it makes goes through the mocked `useApi`, so the
// assertions see the payload the API would receive.
const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  apiDelete: vi.fn(),
  confirm: vi.fn(),
  listeners: {}
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: harness.apiPost,
    put: harness.apiPut,
    patch: async () => ({ data: {} }),
    delete: harness.apiDelete,
    setToken: () => {},
    normalizeError: () => ''
  })
}))

// `EvolutionTimeline` and `AttachmentGallery` route deletion through the shared
// confirm dialog; the module also exports the singleton refs `ConfirmDialog`
// reads, so the mock keeps them.
vi.mock('../../../resources/js/composables/useConfirm', async () => {
  const { ref } = await import('vue')

  return {
    useConfirm: () => ({
      confirm: harness.confirm,
      handleConfirm: () => {},
      handleCancel: () => {}
    }),
    confirmIsOpen: ref(false),
    confirmTitle: ref(''),
    confirmMessage: ref(''),
    confirmConfirmText: ref(''),
    confirmCancelText: ref(''),
    confirmVariant: ref('default')
  }
})

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: () => {} },
    channel: () => {
      const channel = {
        listen: (name, handler) => {
          harness.listeners[name] = handler
          return channel
        }
      }
      return channel
    },
    privateChannel: () => {
      const channel = { listen: () => channel }
      return channel
    },
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import { useToast } from '../../../resources/js/composables/useToast'
import uiComponents from '../../../resources/js/plugins/ui-components'
import MedicalRecordsPage from '../../../resources/js/modules/medical-records/MedicalRecordsPage.vue'
import MedicalRecordModal from '../../../resources/js/modules/medical-records/components/MedicalRecordModal.vue'
import EvolutionModal from '../../../resources/js/modules/medical-records/components/EvolutionModal.vue'
import EvolutionTimeline from '../../../resources/js/modules/medical-records/components/EvolutionTimeline.vue'
import AddendumModal from '../../../resources/js/modules/medical-records/components/AddendumModal.vue'
import UploadAttachmentModal from '../../../resources/js/modules/medical-records/components/UploadAttachmentModal.vue'
import AttachmentGallery from '../../../resources/js/modules/medical-records/components/AttachmentGallery.vue'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com'
}

const record = {
  id: 21,
  record_number: 'HC-2026-000021',
  first_visit_date: '2026-03-04',
  chief_complaint: 'Dolor molar',
  diagnosis: 'Caries',
  treatment_plan: 'Resina',
  notes: null,
  is_active: true,
  patient,
  evolutions_count: 1,
  attachments_count: 0
}

const evolution = {
  id: 5,
  evolution_date: '2026-03-05',
  specialty: 'endodoncia',
  subjective: 'Dolor al masticar',
  objective: 'Caries oclusal',
  assessment: 'Caries',
  plan: 'Resina'
}

const stats = {
  total_records: 1,
  total_evolutions: 1,
  total_attachments: 0,
  last_evolution: null,
  specialties_involved: [],
  attachment_categories: {},
  pending_follow_ups: 0
}

const signIn = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Odontóloga', role }))
}

const mountInBody = async (component, props = {}) => {
  const wrapper = mount(component, {
    props,
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()

  return wrapper
}

const find = selector => {
  const element = document.body.querySelector(selector)
  expect(element, `missing element ${selector}`).toBeTruthy()

  return element
}

const click = async selector => {
  find(selector).click()
  await flushPromises()
}

const typeInto = (selector, value) => {
  const input = find(selector)
  input.value = String(value)
  input.dispatchEvent(new Event('input'))
}

const submitForm = async selector => {
  const form = find(selector)
  form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
  await flushPromises()
}

const openSelect = async selector => {
  const trigger = find(`${selector} button[aria-haspopup="true"]`)
  trigger.click()
  await flushPromises()
}

const chooseOption = async label => {
  const option = [...document.body.querySelectorAll('button[aria-selected]')].find(
    button => button.textContent.trim() === label
  )
  expect(option, `missing option ${label}`).toBeTruthy()
  option.click()
  await flushPromises()
}

// The component only reads `name`, `size` and `type`, so the payload stays
// tiny while the size is exact.
const fileOfSize = (name, size, type = 'application/pdf') => {
  const file = new File(['adjunto'], name, { type })
  Object.defineProperty(file, 'size', { value: size })

  return file
}

const chooseFile = async file => {
  const input = find('[data-upload-file-input]')
  Object.defineProperty(input, 'files', { value: [file], configurable: true })
  input.dispatchEvent(new Event('change'))
  await flushPromises()
}

const isoDateAfter = days => {
  const date = new Date()
  date.setDate(date.getDate() + days)
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/medical-records', name: 'medical-records', component: { template: '<div />' } },
      {
        path: '/medical-records/:id(\\d+)',
        name: 'medical-record-detail',
        component: { template: '<div />' }
      },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push({ path: '/medical-records', query: { patient_id: '7' } })
  await router.isReady()

  const wrapper = mount(MedicalRecordsPage, { global: { plugins: [router, uiComponents] } })
  await flushPromises()

  return wrapper
}

describe('medical records component flows (#224)', () => {
  beforeEach(() => {
    signIn('odontologo')
    vi.clearAllMocks()
    harness.listeners = {}
    harness.apiGet.mockResolvedValue({ data: [] })
    harness.apiPost.mockResolvedValue({ data: { id: 1 } })
    harness.apiPut.mockResolvedValue({ data: { id: 1 } })
    harness.apiDelete.mockResolvedValue({ data: {} })
    harness.confirm.mockResolvedValue(true)
  })

  afterEach(() => {
    document.body.innerHTML = ''
    localStorage.clear()
  })

  describe('crear historia clínica', () => {
    it('maps a 422 back onto the field that produced it', async () => {
      harness.apiPost.mockRejectedValue({
        response: { data: { errors: { chief_complaint: ['El motivo es obligatorio'] } } }
      })

      const wrapper = await mountInBody(MedicalRecordModal, { patient })

      await click('[data-modal-submit]')

      expect(harness.apiPost).toHaveBeenCalledTimes(1)
      const [url, payload] = harness.apiPost.mock.calls[0]
      expect(url).toBe('/api/medical-records')
      expect(payload.patient_id).toBe(7)

      const field = find('[data-field="chief_complaint"]')
      const messages = [...field.querySelectorAll('p')].map(node => node.textContent.trim())
      expect(messages).toContain('El motivo es obligatorio')
      expect(find('[data-medical-record-form]')).toBeTruthy()

      wrapper.unmount()
    })
  })

  describe('crear evolución con seguimiento', () => {
    it('sends requires_follow_up with the chosen date', async () => {
      const followUpDate = isoDateAfter(14)
      harness.apiPost.mockResolvedValue({ data: { id: 6 } })

      const wrapper = await mountInBody(EvolutionModal, { record })

      await click('[data-evolution-follow-up-toggle]')
      expect(find('[data-evolution-follow-up-toggle]').checked).toBe(true)

      typeInto('[data-evolution-field="follow_up_date"] input', followUpDate)
      await flushPromises()

      // #425: the form no longer sends an act with no content, so this case
      // documents the visit with one SOAP block to reach the API at all.
      typeInto('[data-evolution-field="plan"] textarea', 'Control en dos semanas')
      await flushPromises()

      await submitForm('[data-evolution-form]')

      expect(harness.apiPost).toHaveBeenCalledTimes(1)
      const [url, payload] = harness.apiPost.mock.calls[0]
      expect(url).toBe('/api/medical-records/21/evolutions')
      expect(payload.requires_follow_up).toBe(true)
      expect(payload.follow_up_date).toBe(followUpDate)

      wrapper.unmount()
    })
  })

  describe('rechazar una evolución sin contenido', () => {
    const contentMessage =
      'La evolución debe registrar al menos un bloque SOAP, una indicación o una medición.'

    it('does not reach the API and paints the reason in the SOAP block', async () => {
      const { toasts, clearAll } = useToast()
      clearAll()

      const wrapper = await mountInBody(EvolutionModal, { record })

      await submitForm('[data-evolution-form]')

      expect(harness.apiPost).not.toHaveBeenCalled()

      const error = find('[data-evolution-error]')
      expect(error.getAttribute('role')).toBe('alert')
      expect(error.textContent.trim()).toBe(contentMessage)
      expect(find('[data-evolution-section="soap"]').contains(error)).toBe(true)

      // #425 S4: no success toast, no close and no `saved` on an empty submit.
      expect(toasts.value.some(toast => toast.type === 'success')).toBe(false)
      expect(wrapper.emitted('saved')).toBeUndefined()
      expect(wrapper.emitted('close')).toBeUndefined()

      wrapper.unmount()
    })

    it('shows the message of a 422 that carries errors.content and keeps the modal open', async () => {
      harness.apiPost.mockRejectedValue({
        response: {
          data: { message: contentMessage, errors: { content: [contentMessage] } }
        }
      })

      const wrapper = await mountInBody(EvolutionModal, { record })

      typeInto('[data-evolution-field="assessment"] textarea', 'Caries oclusal')
      await flushPromises()

      await submitForm('[data-evolution-form]')

      expect(harness.apiPost).toHaveBeenCalledTimes(1)

      expect(find('[data-evolution-error]').textContent.trim()).toBe(contentMessage)
      expect(wrapper.emitted('saved')).toBeUndefined()
      expect(wrapper.emitted('close')).toBeUndefined()

      wrapper.unmount()
    })
  })

  describe('registrar un addendum', () => {
    it('emits the evolution that needs the correction', async () => {
      const wrapper = await mountInBody(EvolutionTimeline, { evolutions: [evolution] })

      await click('[data-evolution-addendum]')

      expect(wrapper.emitted('addendum')[0][0]).toMatchObject({ id: 5 })

      wrapper.unmount()
    })

    it('refuses an empty reason or content before reaching the API', async () => {
      const wrapper = await mountInBody(AddendumModal, {
        target: { kind: 'evolution', id: 5, label: 'Evolución del 05/03/2026' }
      })

      await submitForm('[data-addendum-form]')

      expect(harness.apiPost).not.toHaveBeenCalled()
      expect(document.body.textContent).toContain('Escribe el motivo de la corrección')

      wrapper.unmount()
    })

    it('posts a record-level addendum with its reason and content', async () => {
      harness.apiPost.mockResolvedValue({ data: { id: 31 } })

      const wrapper = await mountInBody(AddendumModal, {
        target: { kind: 'record', id: 21, label: 'Historia HC-2026-000021' }
      })

      typeInto('[data-addendum-field="reason"] input', 'Dato omitido')
      typeInto('#addendum-content', 'Antecedente de penicilina')
      await submitForm('[data-addendum-form]')

      expect(harness.apiPost).toHaveBeenCalledWith('/api/medical-records/21/addenda', {
        reason: 'Dato omitido',
        content: 'Antecedente de penicilina'
      })
      expect(wrapper.emitted('saved')).toBeTruthy()

      wrapper.unmount()
    })

    it('posts an evolution-level addendum on the evolution it corrects', async () => {
      harness.apiPost.mockResolvedValue({ data: { id: 32 } })

      const wrapper = await mountInBody(AddendumModal, {
        target: { kind: 'evolution', id: 5, label: 'Evolución del 05/03/2026' }
      })

      typeInto('[data-addendum-field="reason"] input', 'Error de digitación')
      typeInto('#addendum-content', 'La pieza correcta es la 36')
      await submitForm('[data-addendum-form]')

      expect(harness.apiPost).toHaveBeenCalledWith('/api/medical-records/evolutions/5/addenda', {
        reason: 'Error de digitación',
        content: 'La pieza correcta es la 36'
      })

      wrapper.unmount()
    })
  })

  describe('subir un adjunto', () => {
    it('rejects an oversize file before the request and uploads a valid one', async () => {
      const wrapper = await mountInBody(UploadAttachmentModal, { patient })

      await chooseFile(fileOfSize('radiografia.pdf', 11 * 1024 * 1024))

      const fileError = find('[data-upload-error="file"]')
      expect(fileError.textContent).toContain('10 MB')
      expect(harness.apiPost).not.toHaveBeenCalled()

      await chooseFile(fileOfSize('radiografia.pdf', 2 * 1024 * 1024))

      await openSelect('[data-upload-category]')
      await chooseOption('Radiografía')
      await submitForm('[data-upload-attachment-form]')

      expect(harness.apiPost).toHaveBeenCalledTimes(1)
      const [url, payload] = harness.apiPost.mock.calls[0]
      expect(url).toBe('/api/medical-records/attachments')
      expect(payload).toBeInstanceOf(FormData)
      expect(payload.get('file').name).toBe('radiografia.pdf')
      expect(payload.get('patient_id')).toBe('7')
      expect(payload.get('category')).toBe('radiografia')
      expect(wrapper.emitted('uploaded')).toHaveLength(1)

      wrapper.unmount()
    })
  })

  describe('filtrar adjuntos por categoría', () => {
    it('narrows the grid and offers a way back when the category is empty', async () => {
      const attachments = [
        {
          id: 1,
          category: 'radiografia',
          original_name: 'panoramica.jpg',
          file_type: 'image',
          mime_type: 'image/jpeg',
          file_path: 'records/panoramica.jpg',
          file_size_human: '1.2 MB',
          created_at: '2026-03-06 10:00:00'
        },
        {
          id: 2,
          category: 'radiografia',
          original_name: 'periapical.jpg',
          file_type: 'image',
          mime_type: 'image/jpeg',
          file_path: 'records/periapical.jpg',
          file_size_human: '0.8 MB',
          created_at: '2026-03-06 10:05:00'
        },
        {
          id: 3,
          category: 'documento',
          original_name: 'consentimiento.pdf',
          file_type: 'pdf',
          mime_type: 'application/pdf',
          file_path: 'records/consentimiento.pdf',
          file_size_human: '0.4 MB',
          created_at: '2026-03-06 10:10:00'
        }
      ]

      const wrapper = await mountInBody(AttachmentGallery, { attachments })

      expect(wrapper.findAll('[data-attachment-id]')).toHaveLength(3)

      await openSelect('[data-category-filter]')
      await chooseOption('Documento')

      expect(wrapper.findAll('[data-attachment-id]')).toHaveLength(1)
      expect(wrapper.get('[data-attachment-count]').text()).toBe('1 de 3')

      await openSelect('[data-category-filter]')
      await chooseOption('Foto clínica')

      expect(wrapper.find('[data-attachment-grid]').exists()).toBe(false)
      expect(wrapper.get('[data-state="empty-attachments-filter"]').text()).toContain(
        'Sin adjuntos en esta categoría'
      )

      await wrapper.get('[data-clear-category-filter]').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('[data-attachment-id]')).toHaveLength(3)

      wrapper.unmount()
    })
  })

  describe('reintentar evoluciones', () => {
    it('shows the surface error and refetches when the retry is pressed', async () => {
      let evolutionsCalls = 0
      let failEvolutions = true

      harness.apiGet.mockImplementation(async url => {
        const { pathname } = new URL(url, 'http://localhost')

        if (pathname === '/api/medical-records') {
          return {
            data: [record],
            meta: { total: 1, current_page: 1, per_page: 50, last_page: 1 }
          }
        }
        if (/^\/api\/medical-records\/\d+\/evolutions$/.test(pathname)) {
          evolutionsCalls += 1

          if (failEvolutions) {
            throw { response: { data: { message: 'Servidor no disponible' } } }
          }

          return { data: [evolution], meta: { total: 1 } }
        }
        if (/^\/api\/medical-records\/patient\/\d+\/attachments$/.test(pathname)) {
          return { data: [], meta: { total: 0 } }
        }
        if (/^\/api\/medical-records\/patient\/\d+\/stats$/.test(pathname)) {
          return { data: stats }
        }
        if (pathname === '/api/specialties/active') {
          return { data: [] }
        }
        if (/^\/api\/patients\/\d+$/.test(pathname)) {
          return { data: patient }
        }

        return { data: [] }
      })

      const wrapper = await mountPage()

      expect(evolutionsCalls).toBe(1)

      await wrapper.findAll('[role="tab"]')[1].trigger('click')
      await flushPromises()

      const error = wrapper.get('[data-state="error-evolutions"]')
      expect(error.text()).toContain('Servidor no disponible')

      failEvolutions = false
      await error.get('button').trigger('click')
      await flushPromises()

      expect(evolutionsCalls).toBe(2)
      expect(wrapper.find('[data-state="error-evolutions"]').exists()).toBe(false)
      expect(wrapper.get('[data-evolution-id="5"]').text()).toContain('Dolor al masticar')

      wrapper.unmount()
    })
  })
})
