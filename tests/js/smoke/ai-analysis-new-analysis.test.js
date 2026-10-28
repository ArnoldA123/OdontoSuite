import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'
import { fileURLToPath, URL as NodeURL } from 'node:url'
import { defineComponent, h, ref } from 'vue'

// T3 sheet contract: the "Nuevo análisis" flow moved off the inline card into
// a UiSheet with numbered steps, page-local validation (20 MB / image type),
// an explicit submit payload and inline API errors. The hero and the empty
// state own the entry points; the page keeps its hero/KPI/filter/table shell.
const { getMock, postMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/ai-analysis/AiAnalysisPage.vue'
import AiAnalysisModal from '../../../resources/js/modules/ai-analysis/components/AiAnalysisModal.vue'
import AnalyzingModal from '../../../resources/js/modules/ai-analysis/components/AnalyzingModal.vue'
import { useToast, globalToasts } from '../../../resources/js/composables/useToast'

const PATIENT = { id: 7, first_name: 'Ana', last_name: 'Torres' }

const STATS = {
  total_analyses: 12,
  completed_analyses: 9,
  reviewed_analyses: 4,
  pending_review: 5,
  completion_rate: 75,
  review_rate: 44.44
}

const row = (overrides = {}) => ({
  id: 1,
  patient: { id: 7, full_name: 'Ana Torres' },
  clinical_attachment: {
    id: 3,
    file_path: 'clinical-attachments/rx.jpg',
    original_name: 'rx.jpg',
    category: 'radiografia'
  },
  status: 'completed',
  reviewed: false,
  confidence_score: 82.5,
  created_at: '2026-10-01T10:00:00.000000Z',
  findings: [],
  recommendations: [],
  ...overrides
})

const listResponse = (items = [row()], meta = {}) => ({
  data: items,
  meta: { current_page: 1, last_page: 1, per_page: 15, total: items.length, ...meta }
})

const imageFile = () => new File(['x'], 'rx.jpg', { type: 'image/jpeg' })

const oversizedFile = () => {
  const file = new File(['x'], 'huge.jpg', { type: 'image/jpeg' })
  Object.defineProperty(file, 'size', { value: 21 * 1024 * 1024 })
  return file
}

const textFile = () => new File(['x'], 'notes.txt', { type: 'text/plain' })

// The patient selector and the upload primitive reach the network and the
// native file picker. The sheet only consumes their emits, so the stubs pin
// the contract: update:modelValue, fileSelected, fileRejected and the silent
// clear() the sheet calls when its own 20 MB rule refuses an adopted file.
const PatientSelectorStub = {
  name: 'PatientSelector',
  props: { modelValue: { type: Object, default: null } },
  emits: ['update:modelValue'],
  setup(_, { emit }) {
    return () =>
      h(
        'button',
        {
          type: 'button',
          'data-stub-patient': '',
          onClick: () => emit('update:modelValue', PATIENT)
        },
        'Elegir paciente'
      )
  }
}

const FileUploadStub = defineComponent({
  name: 'FileUpload',
  // Mirrors the primitive's declared defaults so the stub cannot drift from the
  // contract the sheet relies on.
  props: {
    accept: { type: String, default: 'image/*' },
    preview: { type: Boolean, default: true },
    maxSize: { type: Number, default: 20 * 1024 * 1024 }
  },
  emits: ['fileSelected', 'fileCleared', 'fileRejected'],
  setup(_, { emit, expose }) {
    const adopted = ref(null)

    // Same contract as the primitive: clear() drops the internal file without
    // emitting fileCleared, so the sheet keeps ownership of its own state.
    expose({
      clear: () => {
        adopted.value = null
      }
    })

    const send = file => () => {
      adopted.value = file
      emit('fileSelected', file)
    }
    const reject = payload => () => emit('fileRejected', payload)

    return () =>
      h('div', { 'data-stub-upload': '' }, [
        adopted.value ? h('div', { 'data-stub-preview': '' }, adopted.value.name) : null,
        h(
          'button',
          { type: 'button', 'data-stub-file-image': '', onClick: send(imageFile()) },
          'rx'
        ),
        h(
          'button',
          { type: 'button', 'data-stub-file-big': '', onClick: send(oversizedFile()) },
          'huge'
        ),
        h(
          'button',
          { type: 'button', 'data-stub-file-text': '', onClick: send(textFile()) },
          'text'
        ),
        h(
          'button',
          {
            type: 'button',
            'data-stub-rejected-type': '',
            onClick: reject({ reason: 'type', message: 'Solo se permiten archivos de imagen' })
          },
          'rejected type'
        ),
        h(
          'button',
          {
            type: 'button',
            'data-stub-rejected-size': '',
            onClick: reject({
              reason: 'size',
              message: 'El archivo es demasiado grande. Máximo 20 MB'
            })
          },
          'rejected size'
        )
      ])
  }
})

const mountPage = async ({ items = [row()] } = {}) => {
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/ai-analysis/stats')) {
      return { data: STATS }
    }
    return listResponse(items)
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/ai-analysis')
  await router.isReady()

  const wrapper = mount(Page, {
    attachTo: document.body,
    global: {
      plugins: [router, uiComponents],
      stubs: { PatientSelector: PatientSelectorStub, FileUpload: FileUploadStub }
    }
  })
  await flushPromises()
  return wrapper
}

const sheet = () => document.querySelector('[data-ai-analysis-sheet]')

const openSheet = async wrapper => {
  await wrapper.find('[data-hero-primary]').trigger('click')
  await flushPromises()
}

const click = async selector => {
  const element = document.querySelector(selector)
  expect(element).not.toBeNull()
  element.click()
  await flushPromises()
}

const submitForm = async () => {
  const form = document.querySelector('[data-sheet-form]')
  expect(form).not.toBeNull()
  form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await flushPromises()
}

const submitButton = () => document.querySelector('[data-sheet-submit]')
const stepError = step => document.querySelector(`[data-step-error="${step}"]`)

const noticeCheckbox = () => document.querySelector('[data-sheet-notice]')

const acceptNotice = async () => {
  const checkbox = noticeCheckbox()
  expect(checkbox).not.toBeNull()
  checkbox.click()
  await flushPromises()
}

const openCategoryOptions = async () => {
  document.querySelector('[data-sheet-category] button').click()
  await flushPromises()
}

const selectCategory = async label => {
  await openCategoryOptions()
  const option = [...document.querySelectorAll('[data-sheet-category] button[aria-selected]')].find(
    element => element.textContent.includes(label)
  )
  expect(option).not.toBeUndefined()
  option.click()
  await flushPromises()
}

let wrapper = null

beforeEach(() => {
  useToast().clearAll()
  getMock.mockReset()
  postMock.mockReset()
  postMock.mockResolvedValue({ data: { id: 99, status: 'completed' } })
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
})

describe('ai-analysis new-analysis entry points', () => {
  it('opens the sheet from the hero primary action', async () => {
    wrapper = await mountPage()

    const heroActions = wrapper.find('[data-hero-actions]')
    expect(heroActions.exists()).toBe(true)
    const heroPrimary = wrapper.find('[data-hero-primary]')
    expect(heroPrimary.exists()).toBe(true)
    expect(heroPrimary.text()).toBe('Nuevo análisis')

    await openSheet(wrapper)

    expect(sheet()).not.toBeNull()
    expect(document.body.textContent).toContain('Nuevo análisis de IA')
  })

  it('opens the sheet from the empty-state action', async () => {
    wrapper = await mountPage({ items: [] })

    const empty = wrapper.find('[data-ai-empty]')
    expect(empty.exists()).toBe(true)
    const emptyPrimary = empty.find('[data-empty-primary]')
    expect(emptyPrimary.exists()).toBe(true)
    expect(emptyPrimary.text()).toBe('Nuevo análisis')

    await emptyPrimary.trigger('click')
    await flushPromises()

    expect(sheet()).not.toBeNull()
  })

  it('keeps the inline new-analysis card off the page', async () => {
    wrapper = await mountPage()

    expect(wrapper.find('[data-ai-new-analysis]').exists()).toBe(false)
  })
})

describe('ai-analysis sheet steps', () => {
  it('renders the four numbered steps, the category options and the 500-char counter', async () => {
    wrapper = await mountPage()
    await openSheet(wrapper)

    expect(
      [...document.querySelectorAll('[data-step-number]')].map(el => el.textContent.trim())
    ).toEqual(['1', '2', '3', '4'])
    const text = sheet().textContent
    expect(text).toContain('Paciente')
    expect(text).toContain('Imagen')
    expect(text).toContain('Tipo de imagen')
    expect(text).toContain('Descripción (Opcional)')
    expect(text).toContain('Radiografía')

    await openCategoryOptions()
    expect(document.body.textContent).toContain('Foto clínica')

    const textarea = document.querySelector('[data-sheet-description]')
    expect(textarea.getAttribute('maxlength')).toBe('500')
    expect(document.querySelector('[data-description-counter]').textContent.trim()).toBe('0/500')

    textarea.value = 'Dolor en molar inferior'
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    expect(document.querySelector('[data-description-counter]').textContent.trim()).toBe('23/500')
  })

  it('closes the sheet from Cancelar', async () => {
    wrapper = await mountPage()
    await openSheet(wrapper)

    await click('[data-sheet-cancel]')

    expect(sheet()).toBeNull()
  })
})

describe('ai-analysis sheet validation', () => {
  it('disables the primary action until a patient, an image and the notice exist', async () => {
    wrapper = await mountPage()
    await openSheet(wrapper)

    expect(submitButton().disabled).toBe(true)

    await click('[data-stub-patient]')
    expect(submitButton().disabled).toBe(true)

    await click('[data-stub-file-image]')
    expect(submitButton().disabled).toBe(true)

    await acceptNotice()
    expect(submitButton().disabled).toBe(false)
  })

  it('rejects oversized and non-image files with the exact copy', async () => {
    wrapper = await mountPage()
    await openSheet(wrapper)

    await click('[data-stub-file-big]')
    expect(stepError('image')).not.toBeNull()
    expect(stepError('image').textContent.trim()).toBe('La imagen supera los 20 MB.')

    await click('[data-stub-file-text]')
    expect(stepError('image').textContent.trim()).toBe('Solo se aceptan imágenes.')
    expect(submitButton().disabled).toBe(true)
  })

  it('shows a primitive type rejection inline instead of a native alert', async () => {
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {})
    wrapper = await mountPage()
    await openSheet(wrapper)

    await click('[data-stub-rejected-type]')

    expect(stepError('image')).not.toBeNull()
    expect(stepError('image').getAttribute('role')).toBe('alert')
    expect(stepError('image').textContent.trim()).toBe('Solo se permiten archivos de imagen')
    expect(alertSpy).not.toHaveBeenCalled()
    expect(document.querySelector('[data-stub-upload]')).not.toBeNull()
    expect(document.querySelector('[data-stub-preview]')).toBeNull()
    expect(submitButton().disabled).toBe(true)

    alertSpy.mockRestore()
  })

  it('shows a primitive size rejection inline instead of a native alert', async () => {
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {})
    wrapper = await mountPage()
    await openSheet(wrapper)

    await click('[data-stub-rejected-size]')

    expect(stepError('image').textContent.trim()).toBe(
      'El archivo es demasiado grande. Máximo 20 MB'
    )
    expect(alertSpy).not.toHaveBeenCalled()
    expect(document.querySelector('[data-stub-upload]')).not.toBeNull()
    expect(document.querySelector('[data-stub-preview]')).toBeNull()

    alertSpy.mockRestore()
  })

  it('drops the adopted file and restores the picker when its own 20 MB rule rejects', async () => {
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {})
    wrapper = await mountPage()
    await openSheet(wrapper)

    await click('[data-stub-file-big]')

    expect(stepError('image').textContent.trim()).toBe('La imagen supera los 20 MB.')
    // The primitive adopted the file before the sheet could measure it, so the
    // sheet must call clear(): no retained preview, picker available again.
    expect(document.querySelector('[data-stub-preview]')).toBeNull()
    expect(document.querySelector('[data-stub-upload]')).not.toBeNull()
    expect(alertSpy).not.toHaveBeenCalled()
    expect(submitButton().disabled).toBe(true)

    alertSpy.mockRestore()
  })

  it('asks for the patient, the image and the patient notice on an invalid submit', async () => {
    wrapper = await mountPage()
    await openSheet(wrapper)

    expect(noticeCheckbox()).not.toBeNull()
    expect(noticeCheckbox().type).toBe('checkbox')
    expect(document.body.textContent).toContain(
      'Informé al paciente que el resultado es apoyo asistido por IA y no sustituye el diagnóstico profesional.'
    )

    await submitForm()
    expect(stepError('patient').textContent.trim()).toBe('Selecciona un paciente.')
    expect(stepError('image').textContent.trim()).toBe('Selecciona una imagen.')

    await click('[data-stub-patient]')
    expect(stepError('patient')).toBeNull()
    expect(submitButton().disabled).toBe(true)

    await submitForm()
    expect(stepError('image').textContent.trim()).toBe('Selecciona una imagen.')
    expect(postMock).not.toHaveBeenCalled()
  })
})

describe('ai-analysis sheet submit', () => {
  it('posts the multipart payload and closes the sheet on success', async () => {
    const analysis = { id: 99, status: 'completed', patient: { id: 7, full_name: 'Ana Torres' } }
    postMock.mockResolvedValue({ data: analysis })

    wrapper = await mountPage()
    await openSheet(wrapper)
    await click('[data-stub-patient]')
    await click('[data-stub-file-image]')
    await acceptNotice()
    await selectCategory('Foto clínica')

    const textarea = document.querySelector('[data-sheet-description]')
    textarea.value = 'Dolor en molar inferior'
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    getMock.mockClear()
    await click('[data-sheet-submit]')

    expect(postMock).toHaveBeenCalledTimes(1)
    const [url, payload] = postMock.mock.calls[0]
    expect(url).toBe('/api/ai-analysis/upload-and-analyze')
    expect(payload).toBeInstanceOf(FormData)
    expect(payload.get('patient_id')).toBe('7')
    expect(payload.get('category')).toBe('foto_clinica')
    expect(payload.get('description')).toBe('Dolor en molar inferior')
    expect(payload.get('notice_acknowledged')).toBe('1')
    expect(payload.get('clinical_encounter_id')).toBeNull()
    expect(payload.get('image')).toBeInstanceOf(File)

    expect(sheet()).toBeNull()

    const detail = wrapper.findComponent(AiAnalysisModal)
    expect(detail.exists()).toBe(true)
    expect(detail.props('analysis')).toEqual(analysis)

    const urls = getMock.mock.calls.map(([requested]) => String(requested))
    expect(urls.some(requested => requested.includes('/api/ai-analysis?page=1'))).toBe(true)
    expect(urls.some(requested => requested.startsWith('/api/ai-analysis/stats'))).toBe(true)
  })

  it('keeps the sheet open and shows the API message when the upload fails', async () => {
    postMock.mockRejectedValue({
      response: { data: { message: 'La IA no pudo procesar la imagen.' } }
    })
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {})

    wrapper = await mountPage()
    await openSheet(wrapper)
    await click('[data-stub-patient]')
    await click('[data-stub-file-image]')
    await acceptNotice()
    await click('[data-sheet-submit]')

    expect(sheet()).not.toBeNull()
    const error = document.querySelector('[data-sheet-error]')
    expect(error).not.toBeNull()
    expect(error.getAttribute('role')).toBe('alert')
    expect(error.textContent.trim()).toBe('La IA no pudo procesar la imagen.')

    // One notice per context: the sheet owns the message, so the composable
    // must not paint a second copy of it as a toast.
    const occurrences =
      document.body.textContent.split('La IA no pudo procesar la imagen.').length - 1
    expect(occurrences).toBe(1)
    expect(globalToasts.value.filter(toast => toast.type === 'error')).toHaveLength(0)

    expect(alertSpy).not.toHaveBeenCalled()
    expect(submitButton().disabled).toBe(false)
    expect(wrapper.findComponent(AnalyzingModal).props('show')).toBe(false)

    alertSpy.mockRestore()
  })
})

describe('ai-analysis analyzing surface', () => {
  it('renders the failed state with the message and a Cerrar action', async () => {
    wrapper = mount(AnalyzingModal, {
      props: {
        show: true,
        status: 'failed',
        message: 'La IA no pudo procesar la imagen.'
      },
      attachTo: document.body
    })
    await flushPromises()

    const steps = [...document.querySelectorAll('[data-progress-step]')]
    expect(steps.map(step => step.getAttribute('data-progress-step'))).toEqual([
      'uploading',
      'analyzing',
      'completed'
    ])
    expect(document.querySelector('[data-progress-error]').textContent.trim()).toBe(
      'La IA no pudo procesar la imagen.'
    )

    await click('[data-progress-close]')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})

describe('ai-analysis new-analysis surface hygiene', () => {
  it('keeps the sheet and the analyzing surface free of legacy styling', () => {
    const files = [
      '../../../resources/js/modules/ai-analysis/components/AiAnalysisSheet.vue',
      '../../../resources/js/modules/ai-analysis/components/AnalyzingModal.vue'
    ]

    for (const relative of files) {
      const source = readFileSync(fileURLToPath(new NodeURL(relative, import.meta.url)), 'utf8')

      expect(source, relative).not.toContain('<style')
      expect(source, relative).not.toContain('bg-gradient-')
      expect(source, relative).not.toContain('text-ink-')
      expect(source, relative).not.toContain('systemBlue')
      expect(source, relative).not.toContain('text-[11px]')
      expect(source, relative).not.toContain('border-theme')
    }
  })
})
