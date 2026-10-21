import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { readFileSync } from 'node:fs'

// Caja / Movimientos contract (T7). The list renders through the shared
// UiDataTable primitive, the summary cards read the API summary only (N/D
// without it), the dead edit/delete/export controls are gone, and the modal
// speaks v-model and sends the required concept + branch_id envelope.
const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(async () => ({ data: [] })),
    post: postMock,
    put: vi.fn(async () => ({ data: {} })),
    patch: vi.fn(async () => ({ data: {} })),
    delete: vi.fn(async () => ({ data: {} })),
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
import MovementList from '../../../resources/js/modules/cash-register/components/MovementList.vue'
import MovementModal from '../../../resources/js/modules/cash-register/components/MovementModal.vue'

const LIST_SOURCE = 'resources/js/modules/cash-register/components/MovementList.vue'
const MODAL_SOURCE = 'resources/js/modules/cash-register/components/MovementModal.vue'

const MOVEMENTS = [
  {
    id: 1,
    type: 'income',
    amount: 999,
    description: 'Cobro de ortodoncia',
    notes: null,
    reference: 'TX-001',
    created_at: '2026-10-20T09:15:00.000000Z',
    created_by: { name: 'Ana Torres' }
  },
  {
    id: 2,
    type: 'withdrawal',
    amount: 999,
    description: 'Retiro para caja chica',
    notes: 'Autorizado por gerencia',
    reference: null,
    created_at: '2026-10-20T10:30:00.000000Z',
    created_by: null
  }
]

const PAGINATION = { current_page: 1, last_page: 3, per_page: 15, total: 31 }

const SUMMARY = {
  opening_amount: 100,
  total_income: 80,
  total_expenses: 0,
  cash_in: 180,
  cash_out: 40,
  net_flow: 140,
  expected_amount: 240,
  movements_count: 2
}

const SESSION = { id: 9, branch_id: 4, status: 'open' }

const mountList = (props = {}) =>
  mount(MovementList, {
    props: { movements: MOVEMENTS, loading: false, pagination: null, summary: null, ...props },
    global: { plugins: [uiComponents] }
  })

const field = name => document.querySelector(`[data-field="${name}"]`)

const chooseOption = async (name, label) => {
  const trigger = field(name).querySelector('button[aria-haspopup="true"]')
  expect(trigger, `select "${name}" must render a trigger`).toBeTruthy()
  trigger.click()
  await flushPromises()
  const option = [...field(name).querySelectorAll('[role="listbox"] button')].find(
    button => button.textContent.trim() === label
  )
  expect(option, `option "${label}" must exist in "${name}"`).toBeTruthy()
  option.click()
  await flushPromises()
}

const fillInput = async (name, value) => {
  const input = field(name).querySelector('input')
  expect(input, `field "${name}" must render an input`).toBeTruthy()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const mountModal = async () => {
  const wrapper = mount(MovementModal, {
    props: { modelValue: true, session: SESSION },
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const submitModal = async () => {
  const button = document.querySelector('[data-modal-submit]')
  expect(button, 'the modal must render a submit button').toBeTruthy()
  button.click()
  await flushPromises()
}

describe('Caja movement list contract', () => {
  it('renders the ledger through the UiDataTable primitive with no hand-written table', async () => {
    const wrapper = mountList()
    await flushPromises()

    expect(wrapper.find('.data-table-container').exists()).toBe(true)
    expect(wrapper.text()).toContain('Cobro de ortodoncia')
    expect(wrapper.text()).toContain('Retiro para caja chica')
    expect(wrapper.text()).toContain('Ana Torres')

    const source = readFileSync(LIST_SOURCE, 'utf8')
    expect(source).not.toContain('<table')
    expect(source).not.toContain('<select')

    wrapper.unmount()
  })

  it('reads the three cash totals from the summary prop, not from the rows', async () => {
    const wrapper = mountList({ summary: SUMMARY })
    await flushPromises()

    const values = wrapper
      .findAll('[data-movement-total-value]')
      .map(node => node.text().replace(/\s+/g, ' '))
    expect(values).toEqual(['S/ 180.00', 'S/ 40.00', 'S/ 140.00'])

    wrapper.unmount()
  })

  it('renders N/D for every total when there is no summary', async () => {
    const wrapper = mountList({ summary: null })
    await flushPromises()

    const values = wrapper.findAll('[data-movement-total-value]').map(node => node.text())
    expect(values).toEqual(['N/D', 'N/D', 'N/D'])

    wrapper.unmount()
  })

  it('drops the dead edit/delete actions and the stub exports', async () => {
    const wrapper = mountList({ summary: SUMMARY })
    await flushPromises()

    expect(wrapper.find('[title="Editar"]').exists()).toBe(false)
    expect(wrapper.find('[title="Eliminar"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Excel')
    expect(wrapper.text()).not.toContain('PDF')

    wrapper.unmount()
  })

  it('requests a payload-free reload', async () => {
    const wrapper = mountList({ summary: SUMMARY })
    await flushPromises()

    await wrapper.find('[data-movement-refresh]').trigger('click')

    expect(wrapper.emitted('refresh')).toEqual([[]])

    wrapper.unmount()
  })

  it('feeds UiPagination from the API meta when the endpoint paginates', async () => {
    const wrapper = mountList({ pagination: PAGINATION })
    await flushPromises()

    expect(wrapper.find('[data-movement-pagination]').exists()).toBe(true)
    expect(wrapper.find('[data-movement-pagination]').text()).toContain('31')
    expect(wrapper.find('[aria-label="Ir a la página 3"]').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('Caja movement modal contract', () => {
  beforeEach(() => {
    postMock.mockReset()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('sends the required concept and the session branch_id', async () => {
    postMock.mockResolvedValueOnce({ data: { id: 44 } })
    const wrapper = await mountModal()

    await chooseOption('type', 'Egreso')
    await chooseOption('concept', 'Retiro')
    await fillInput('description', 'Retiro de caja')
    await fillInput('amount', '150')
    await submitModal()

    expect(postMock).toHaveBeenCalledTimes(1)
    const [url, payload] = postMock.mock.calls[0]
    expect(url).toBe('/api/cash-movements')
    expect(payload).toMatchObject({
      cash_register_session_id: 9,
      branch_id: 4,
      concept: 'withdrawal',
      type: 'expense',
      amount: 150,
      description: 'Retiro de caja'
    })
    expect(wrapper.emitted('success')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false])

    wrapper.unmount()
  })

  it('renders the general submit error and the mapped field errors', async () => {
    postMock.mockRejectedValueOnce({
      response: {
        data: {
          message: 'No se pueden registrar movimientos en una sesión de caja cerrada'
        }
      }
    })
    const wrapper = await mountModal()

    await fillInput('description', 'Retiro de caja')
    await fillInput('amount', '150')
    await submitModal()

    expect(document.querySelector('[data-movement-error]')?.textContent).toContain(
      'No se pueden registrar movimientos en una sesión de caja cerrada'
    )

    postMock.mockRejectedValueOnce({
      response: {
        data: {
          errors: { amount: ['El monto debe ser mayor a 0 (mínimo 0.01).'] }
        }
      }
    })
    await submitModal()

    expect(document.body.textContent).toContain('El monto debe ser mayor a 0 (mínimo 0.01).')

    wrapper.unmount()
  })

  it('offers no adjustment in the manual type or concept selects', async () => {
    const wrapper = await mountModal()

    for (const name of ['type', 'concept']) {
      const trigger = field(name).querySelector('button[aria-haspopup="true"]')
      expect(trigger, `select "${name}" must render a trigger`).toBeTruthy()
      trigger.click()
      await flushPromises()

      const labels = [...field(name).querySelectorAll('[role="listbox"] button')].map(button =>
        button.textContent.trim()
      )
      expect(labels.length).toBeGreaterThan(0)
      expect(labels).not.toContain('Ajuste')
      expect(trigger.getAttribute('aria-expanded')).toBe('true')
      trigger.click()
      await flushPromises()
    }

    wrapper.unmount()
  })

  it('speaks the v-model contract with the shared UiModal and UiSelect primitives', async () => {
    const wrapper = await mountModal()

    expect(document.querySelector('[role="dialog"]')).toBeTruthy()
    expect(field('type')).toBeTruthy()
    expect(field('concept')).toBeTruthy()

    const source = readFileSync(MODAL_SOURCE, 'utf8')
    expect(source).not.toContain('<select')
    expect(source).toContain(':model-value="modelValue"')

    wrapper.unmount()
  })
})
