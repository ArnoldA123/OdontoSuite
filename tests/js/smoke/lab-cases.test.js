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
    put: vi.fn(),
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

const catalogGet = async url => {
  const target = String(url)
  if (target === '/api/lab-cases/11') return { data: IN_LABORATORY }
  if (target === '/api/lab-cases/12') return { data: DELIVERED }
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

let wrapper = null

beforeEach(() => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 9, name: 'Tec. Marco Díaz', role: 'tecnico_dental' })
  )
  harness.get.mockReset()
  harness.post.mockReset()
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
    expect(lateRow.querySelector('[data-lab-case-work]').textContent).toContain(
      'Corona metal-cerámica'
    )
    expect(lateRow.textContent).toContain('16')
    expect(lateRow.textContent).toContain('01 ene 2020')
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
    expect(detail.textContent).toContain('20 oct 2026')
  })

  it('offers only the phases after the current one', async () => {
    wrapper = await mountPage()

    await wrapper.findAll('[data-lab-case-open]')[1].trigger('click')
    await flushPromises()

    expect(phaseButtons()).toEqual(['placed'])
    expect(document.body.querySelector('[data-lab-case-detail]').textContent).toContain(
      '21 oct 2026, 09:00'
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
})
