import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// F5 (#316) — the prosthetic laboratory desk renders the work orders with the
// phase badge and the overdue marker, refetches on a filter change, opens the
// detail from the row and moves the case through its forward-only door.
// Transports and authorization are mocked: the page only consumes their
// contracts.
const harness = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  canManage: true,
  channelStub: { listen: vi.fn() },
  privateChannel: vi.fn(),
  leave: vi.fn(),
  toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.get,
    post: harness.post,
    put: harness.put,
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: harness.leave },
    channel: () => harness.channelStub,
    privateChannel: harness.privateChannel,
    connectionStatus: { value: 'connected' }
  })
}))

vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

// B1 only consumes the flag for the phase door; the read flag belongs to the
// shell. The mutable boolean lets one test mount the same page as reception.
vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      viewLabCase: { value: true },
      manageLabCase: { value: harness.canManage }
    }
  })
}))

vi.mock('../../../resources/js/composables/useAuth', () => ({
  useAuth: () => ({
    user: { value: { id: 9, name: 'Tec. Marco Díaz', role: 'tecnico_dental' } },
    isAuthenticated: { value: true }
  })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => harness.toast,
  globalToasts: { value: [] }
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import { formatDateTime } from '../../../resources/js/modules/lab-cases/labCaseMeta'
import Page from '../../../resources/js/modules/lab-cases/LabCasesPage.vue'

const labCase = (id, status, overrides = {}) => ({
  id,
  patient_id: 3,
  branch_id: 1,
  professional_id: 7,
  technician_id: 9,
  dental_piece_id: 4,
  work: 'Corona metal-cerámica',
  material: 'Zirconio',
  sent_at: '2026-10-20',
  promised_at: '2026-10-24',
  status,
  notes: 'Pieza 16, ajuste oclusal',
  delivered_at: null,
  placed_at: null,
  patient: { id: 3, first_name: 'Ana', last_name: 'Torres' },
  professional: { id: 7, name: 'Dr. Luis Rojas' },
  technician: { id: 9, name: 'Tec. Marco Díaz' },
  dental_piece: { id: 4, fdi_number: '16' },
  ...overrides
})

const IN_LABORATORY = labCase(11, 'in_laboratory', {
  work: 'Corona metal-cerámica',
  promised_at: '2020-01-01'
})

const DELIVERED = labCase(12, 'delivered', {
  work: 'Puente de zirconio',
  promised_at: '2020-01-02',
  delivered_at: '2026-10-21T09:00:00.000000Z'
})

const listPayload = () => ({
  data: [IN_LABORATORY, DELIVERED],
  meta: { current_page: 1, last_page: 2, per_page: 15, total: 16 }
})

// An overdue promise (2020) next to a 2026 sending date is what the desk shows,
// but the form refuses that pair on save. The edit flows measure the surface, so
// they mount a case the form can actually send.
const editableCase = (row, overrides = {}) => ({ ...row, promised_at: '2026-10-26', ...overrides })

const editablePayload = rows => ({
  data: rows,
  meta: { current_page: 1, last_page: 1, per_page: 15, total: rows.length }
})

const mockEditableList = () => {
  const rows = [editableCase(IN_LABORATORY), editableCase(DELIVERED)]

  harness.get.mockImplementation(async url => {
    const target = String(url)
    if (target === '/api/lab-cases/11') return { data: rows[0] }
    if (target === '/api/lab-cases/12') return { data: rows[1] }
    if (target === '/api/patients') return { data: [PATIENT] }
    if (target === '/api/users/active') return { data: [] }
    if (target === '/api/dental-pieces') return { data: [] }
    return editablePayload(rows)
  })
}

const PATIENT = {
  id: 3,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '12345678'
}

const catalogGet = async url => {
  const target = String(url)
  if (target === '/api/lab-cases/11') return { data: IN_LABORATORY }
  if (target === '/api/lab-cases/12') return { data: DELIVERED }
  if (target === '/api/patients') return { data: [PATIENT] }
  if (target === '/api/users/active') return { data: [] }
  if (target === '/api/dental-pieces') return { data: [] }
  return listPayload()
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/lab-cases')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const listCalls = () =>
  harness.get.mock.calls.filter(([url]) => String(url).startsWith('/api/lab-cases'))

const phaseButtons = () =>
  [...document.body.querySelectorAll('[data-lab-case-phase]')].map(node =>
    node.getAttribute('data-lab-case-phase')
  )

const rowNode = id => wrapper.find(`[data-lab-case-row="${id}"]`).element.closest('tr')

const pickPatient = async name => {
  const row = [...document.querySelectorAll('[data-lab-case-form-patient] div')].find(
    node => node.className.includes('cursor-pointer') && node.textContent.includes(name)
  )
  expect(row, `patient ${name} must be offered`).toBeTruthy()
  row.click()
  await flushPromises()
}

const setFormValue = async (hook, value) => {
  const control = document.querySelector(`${hook} input, ${hook} textarea`)
  control.value = value
  control.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const submitForm = async () => {
  document.querySelector('[data-lab-case-form-submit]').click()
  await flushPromises()
}

let wrapper = null

beforeEach(() => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 9, name: 'Tec. Marco Díaz', role: 'tecnico_dental' })
  )
  harness.get.mockReset()
  harness.post.mockReset()
  harness.put.mockReset()
  harness.canManage = true
  harness.toast.success.mockReset()
  harness.toast.error.mockReset()
  harness.toast.info.mockReset()
  harness.channelStub.listen.mockReset()
  harness.channelStub.listen.mockImplementation(() => harness.channelStub)
  harness.privateChannel.mockReset()
  harness.privateChannel.mockImplementation(() => harness.channelStub)
  harness.leave.mockReset()

  harness.get.mockImplementation(catalogGet)
  harness.post.mockImplementation(async () => ({
    data: DELIVERED,
    meta: { message: 'Fase del caso de laboratorio actualizada exitosamente' }
  }))
  harness.put.mockImplementation(async () => ({
    data: DELIVERED,
    meta: { message: 'Caso de laboratorio actualizado exitosamente' }
  }))
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
})

describe('lab-cases desk', () => {
  it('renders the work orders with their phase badge and the overdue marker', async () => {
    wrapper = await mountPage()

    expect(wrapper.find('[data-lab-cases-title]').text()).toBe('Laboratorio protésico')

    const lateRow = wrapper.find('[data-lab-case-row="11"]').element.closest('tr')
    expect(lateRow.querySelector('[data-lab-case-patient]').textContent).toContain('Ana Torres')
    // #346 — the work name keeps the row on one line and the full text stays
    // reachable through the native tooltip.
    const work = lateRow.querySelector('[data-lab-case-work]')
    expect(work.textContent.trim()).toBe('Corona metal-cerámica')
    expect(work.classList.contains('truncate')).toBe(true)
    expect(work.getAttribute('title')).toBe('Corona metal-cerámica')
    expect(lateRow.textContent).toContain('16')
    expect(lateRow.textContent).toContain('01 ene. 2020')
    expect(lateRow.querySelector('[data-lab-case-status]').textContent).toContain('En laboratorio')
    expect(lateRow.querySelector('[data-lab-case-overdue]')).toBeTruthy()

    const deliveredRow = wrapper.find('[data-lab-case-row="12"]').element.closest('tr')
    expect(deliveredRow.querySelector('[data-lab-case-status]').textContent).toContain(
      'Recibido del taller'
    )
    expect(deliveredRow.querySelector('[data-lab-case-overdue]')).toBeNull()
  })

  it('refetches when the phase filter changes', async () => {
    wrapper = await mountPage()
    harness.get.mockClear()

    wrapper.findComponent('[data-lab-cases-status]').vm.$emit('update:modelValue', 'delivered')
    await flushPromises()

    expect(harness.get).toHaveBeenCalledWith('/api/lab-cases?status=delivered&page=1')
    expect(listCalls()).toHaveLength(1)
  })

  it('opens the case detail from the row action', async () => {
    wrapper = await mountPage()

    await wrapper.find('[data-lab-case-open]').trigger('click')
    await flushPromises()

    expect(harness.get).toHaveBeenCalledWith('/api/lab-cases/11')
    const detail = document.body.querySelector('[data-lab-case-detail]')
    expect(detail).toBeTruthy()
    expect(detail.textContent).toContain('Corona metal-cerámica')
    expect(detail.textContent).toContain('Ana Torres')
    expect(detail.textContent).toContain('Dr. Luis Rojas')
    expect(detail.textContent).toContain('Zirconio')
    expect(detail.textContent).toContain('20 oct. 2026')
  })

  it('offers only the phases after the current one', async () => {
    wrapper = await mountPage()

    await wrapper.findAll('[data-lab-case-open]')[1].trigger('click')
    await flushPromises()

    expect(phaseButtons()).toEqual(['placed'])
    // #451: `delivered_at` is an instant (`...Z`), so the canonical helper
    // renders it in the session clock instead of slicing the UTC wall clock.
    // The expectation delegates to the desk's own label so it stays
    // timezone-independent (the exact date+time rendering is pinned by
    // `labCaseMeta.test.js`).
    expect(document.body.querySelector('[data-lab-case-detail]').textContent).toContain(
      formatDateTime(DELIVERED.delivered_at)
    )
  })

  it('shows the service sentence when the phase door rejects the move', async () => {
    harness.post.mockRejectedValue({
      response: {
        data: {
          message: 'Fase no permitida',
          errors: { phase: ['El caso ya está en la fase «placed»; no puede volver a avanzar.'] }
        }
      },
      status: 422
    })

    wrapper = await mountPage()

    await wrapper.find('[data-lab-case-open]').trigger('click')
    await flushPromises()

    document.body.querySelector('[data-lab-case-phase="placed"]').click()
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/lab-cases/11/phase', { phase: 'placed' })
    expect(document.body.querySelector('[data-lab-case-phase-error]').textContent).toContain(
      'El caso ya está en la fase «placed»; no puede volver a avanzar.'
    )
    expect(document.body.querySelector('[data-lab-case-detail]')).toBeTruthy()
  })

  it('retries the failed list instead of blanking the desk', async () => {
    harness.get.mockRejectedValueOnce({ response: { data: { message: 'Sin conexión' } } })

    wrapper = await mountPage()

    expect(wrapper.find('[data-lab-cases-error]').exists()).toBe(true)
    expect(wrapper.find('[data-lab-cases-error-message]').text()).toBe('Sin conexión')

    await wrapper.find('[data-lab-cases-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-lab-cases-error]').exists()).toBe(false)
    expect(wrapper.find('[data-lab-case-row="11"]').exists()).toBe(true)
  })

  it('hides every phase button when the profile cannot manage the module', async () => {
    harness.canManage = false

    wrapper = await mountPage()

    await wrapper.find('[data-lab-case-open]').trigger('click')
    await flushPromises()

    const detail = document.body.querySelector('[data-lab-case-detail]')
    expect(detail).toBeTruthy()
    expect(phaseButtons()).toEqual([])
    expect(document.body.querySelector('[data-lab-case-read-only]')).toBeTruthy()
  })

  it('offers «Nuevo caso» and the row edit only with manage.lab_case', async () => {
    wrapper = await mountPage()

    expect(wrapper.find('[data-lab-cases-create]').exists()).toBe(true)
    expect(rowNode(11).querySelector('[data-lab-case-edit]')).toBeTruthy()

    wrapper.unmount()
    wrapper = null
    harness.canManage = false
    wrapper = await mountPage()

    expect(wrapper.find('[data-lab-cases-create]').exists()).toBe(false)
    expect(rowNode(11).querySelector('[data-lab-case-edit]')).toBeNull()
  })

  it('offers «Editar» per row only when the case publishes can_edit', async () => {
    harness.get.mockImplementation(async url => {
      const target = String(url)
      if (target === '/api/lab-cases/11') return { data: { ...IN_LABORATORY, can_edit: true } }
      if (target === '/api/lab-cases/12') return { data: { ...DELIVERED, can_edit: false } }
      if (target === '/api/patients') return { data: [PATIENT] }
      if (target === '/api/users/active') return { data: [] }
      if (target === '/api/dental-pieces') return { data: [] }
      return {
        data: [
          { ...IN_LABORATORY, can_edit: true },
          { ...DELIVERED, can_edit: false }
        ],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 }
      }
    })

    wrapper = await mountPage()

    expect(rowNode(11).querySelector('[data-lab-case-edit]')).toBeTruthy()
    expect(rowNode(12).querySelector('[data-lab-case-edit]')).toBeNull()
  })

  it('offers «Editar» in the detail with the capability and the case permit', async () => {
    wrapper = await mountPage()

    await wrapper.find('[data-lab-case-open]').trigger('click')
    await flushPromises()

    const button = document.body.querySelector('[data-lab-case-detail-edit]')
    expect(button).toBeTruthy()
    expect(button.textContent.trim()).toBe('Editar')
  })

  it('hides the detail «Editar» without the capability or the case permit', async () => {
    harness.canManage = false
    wrapper = await mountPage()

    await wrapper.find('[data-lab-case-open]').trigger('click')
    await flushPromises()

    expect(document.body.querySelector('[data-lab-case-detail-edit]')).toBeNull()

    wrapper.unmount()
    wrapper = null
    harness.canManage = true
    harness.get.mockImplementation(async url => {
      const target = String(url)
      if (target === '/api/lab-cases/11') return { data: { ...IN_LABORATORY, can_edit: false } }
      if (target === '/api/patients') return { data: [PATIENT] }
      if (target === '/api/users/active') return { data: [] }
      if (target === '/api/dental-pieces') return { data: [] }
      return listPayload()
    })

    wrapper = await mountPage()

    await wrapper.find('[data-lab-case-open]').trigger('click')
    await flushPromises()

    expect(document.body.querySelector('[data-lab-case-detail-edit]')).toBeNull()
  })

  it('opens the create form from the hero, posts the case and reloads the list', async () => {
    wrapper = await mountPage()

    await wrapper.find('[data-lab-cases-create]').trigger('click')
    await flushPromises()

    expect(document.body.querySelector('[data-lab-case-form]')).toBeTruthy()
    expect(document.body.querySelector('[data-lab-case-form-patient]')).toBeTruthy()

    await pickPatient('Ana Torres')
    await setFormValue('[data-lab-case-form-work]', 'Prótesis parcial removible')

    harness.get.mockClear()
    await submitForm()

    expect(harness.post).toHaveBeenCalledTimes(1)
    const [url, body] = harness.post.mock.calls[0]
    expect(url).toBe('/api/lab-cases')
    expect(body).toMatchObject({ patient_id: 3, work: 'Prótesis parcial removible' })
    expect(listCalls()).toHaveLength(1)
  })

  it('opens the edit form from the row prefilled, puts the case and reloads the list', async () => {
    mockEditableList()
    wrapper = await mountPage()

    rowNode(12).querySelector('[data-lab-case-edit]').click()
    await flushPromises()

    expect(document.body.querySelector('[data-lab-case-form-work] input').value).toBe(
      'Puente de zirconio'
    )
    expect(document.body.querySelector('[data-lab-case-form-patient-fixed]').textContent).toContain(
      'Ana Torres'
    )

    harness.get.mockClear()
    await submitForm()

    expect(harness.put).toHaveBeenCalledTimes(1)
    const [url, body] = harness.put.mock.calls[0]
    expect(url).toBe('/api/lab-cases/12')
    expect(body).toMatchObject({ patient_id: 3, work: 'Puente de zirconio' })
    expect(listCalls()).toHaveLength(1)
  })

  it('refreshes the open detail after saving its case', async () => {
    let work = 'Corona metal-cerámica'
    const row = () => editableCase(IN_LABORATORY, { work })

    harness.get.mockImplementation(async url => {
      const target = String(url)
      if (target === '/api/lab-cases/11') return { data: row() }
      if (target === '/api/patients') return { data: [PATIENT] }
      if (target === '/api/users/active') return { data: [] }
      if (target === '/api/dental-pieces') return { data: [] }
      return editablePayload([row(), editableCase(DELIVERED)])
    })
    harness.put.mockImplementation(async () => ({
      data: editableCase(IN_LABORATORY, { work }),
      meta: { message: 'Caso de laboratorio actualizado exitosamente' }
    }))

    wrapper = await mountPage()

    await wrapper.find('[data-lab-case-open]').trigger('click')
    await flushPromises()

    document.body.querySelector('[data-lab-case-detail-edit]').click()
    await flushPromises()

    work = 'Corona de circonio'
    harness.get.mockClear()
    await submitForm()

    expect(harness.put).toHaveBeenCalledWith('/api/lab-cases/11', expect.any(Object))
    expect(harness.get).toHaveBeenCalledWith('/api/lab-cases/11')
    expect(document.body.querySelector('[data-lab-case-detail]').textContent).toContain(
      'Corona de circonio'
    )
  })

  it('keeps the server sentence of a refused edit in the form notice', async () => {
    mockEditableList()
    harness.put.mockRejectedValue({
      response: { data: { message: 'No tienes permisos para acceder a este recurso.' } },
      status: 403
    })

    wrapper = await mountPage()

    rowNode(11).querySelector('[data-lab-case-edit]').click()
    await flushPromises()

    await submitForm()

    expect(
      document.body.querySelector('[data-lab-case-form-error="message"]').textContent
    ).toContain('No tienes permisos para acceder a este recurso.')
    expect(document.body.querySelector('[data-lab-case-form]')).toBeTruthy()
  })
})
