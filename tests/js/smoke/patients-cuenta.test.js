import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// #320 (S2/S4/S7) — the patient statement tab and the manual charge modal.
// The panel is read-only and prints exactly the totals the API reconciles; the
// modal is the counter's only write door and never creates a row when the
// backend rejects it. Transport and design-system primitives are real; the two
// API calls are mocked, since the tests consume their contracts.
const harness = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))

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

import uiComponents from '../../../resources/js/plugins/ui-components'
import StatementPanel from '../../../resources/js/modules/patients/components/PatientStatementPanel.vue'
import ChargeModal from '../../../resources/js/modules/patients/components/PatientChargeModal.vue'
import { formatCurrency } from '../../../resources/js/utils/formatters.js'

const PATIENT = { id: 7, first_name: 'Ana', last_name: 'Torres', document_number: '45678912' }

const STATEMENT = {
  patient: { id: 7, first_name: 'Ana', last_name: 'Torres', document_number: '45678912' },
  period: { date_from: '2026-10-01', date_to: '2026-10-25' },
  opening_balance: 80,
  closing_balance: 240,
  summary: {
    charges: 400,
    discounts: 20,
    payments: 210,
    refunds: 10,
    write_offs: 15,
    adjustments: -5,
    collected: 200,
    forgiven: 15,
    balance: 240
  },
  entries: [
    {
      kind: 'charge',
      date: '2026-10-03T10:00:00-05:00',
      reference: 'CHG-000042',
      description: 'Corona metal-cerámica',
      amount: 400,
      effect: 400,
      balance: 480
    },
    {
      kind: 'payment',
      date: '2026-10-10T11:30:00-05:00',
      reference: 'TRX-000123',
      description: 'Pago en efectivo',
      amount: 250,
      effect: -250,
      balance: 230
    },
    {
      kind: 'refund',
      date: '2026-10-12T09:00:00-05:00',
      reference: 'TRX-000130',
      description: 'Devolución aprobada',
      amount: 10,
      effect: 10,
      balance: 240
    }
  ]
}

const pad = value => String(value).padStart(2, '0')

const dateOnly = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

const statementUrl = (from, to) => `/api/patients/7/statement?date_from=${from}&date_to=${to}`

const defaultRange = () => {
  const today = new Date()
  return {
    from: dateOnly(new Date(today.getFullYear(), today.getMonth(), 1)),
    to: dateOnly(today)
  }
}

const node = selector => document.body.querySelector(selector)

const mountPanel = async (props = {}) => {
  const wrapper = mount(StatementPanel, {
    props: { patient: PATIENT, canManageCharge: false, ...props },
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const mountModal = async (props = {}) => {
  const wrapper = mount(ChargeModal, {
    props: { modelValue: true, patient: PATIENT, ...props },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

const setField = async (hook, value) => {
  const target = node(`${hook} input`) || node(`${hook} textarea`) || node(hook)
  expect(target, `${hook} must render a control`).toBeTruthy()
  target.value = value
  target.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const setPanelField = async (hook, value) => {
  const control = wrapper.find(`${hook} input`)
  expect(control.exists(), `${hook} must render a control`).toBe(true)
  control.element.value = value
  await control.trigger('input')
}

let wrapper = null

beforeEach(() => {
  document.body.innerHTML = ''
  harness.get.mockReset()
  harness.post.mockReset()
  harness.get.mockResolvedValue({ data: STATEMENT })
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
})

describe('patient statement panel (#320 S4)', () => {
  it('requests the patient statement for the default period: month start through today', async () => {
    wrapper = await mountPanel()

    const { from, to } = defaultRange()
    expect(harness.get).toHaveBeenCalledTimes(1)
    expect(harness.get).toHaveBeenCalledWith(statementUrl(from, to))

    expect(wrapper.find('[data-statement-date-from] input').element.value).toBe(from)
    expect(wrapper.find('[data-statement-date-to] input').element.value).toBe(to)

    wrapper.unmount()
    wrapper = null
  })

  it('paints the KPI cards with the totals the API reconciled, not local arithmetic', async () => {
    wrapper = await mountPanel()

    const kpi = key => wrapper.get(`[data-statement-kpi-value="${key}"]`).text()

    // The fixture is deliberately not self-consistent: the surface must print
    // the API totals verbatim instead of recomputing a balance of its own.
    expect(kpi('opening')).toBe(formatCurrency(80))
    expect(kpi('charges')).toBe(formatCurrency(400))
    expect(kpi('payments')).toBe(formatCurrency(210))
    expect(kpi('closing')).toBe(formatCurrency(240))

    expect(wrapper.get('[data-statement-kpi="opening"]').text()).toContain('Saldo de apertura')
    expect(wrapper.get('[data-statement-kpi="closing"]').text()).toContain('Saldo de cierre')

    wrapper.unmount()
    wrapper = null
  })

  it('renders the nine summary rows with the API amounts', async () => {
    wrapper = await mountPanel()

    const rows = wrapper.findAll('[data-statement-summary-row]')
    expect(rows.map(row => row.attributes('data-statement-summary-row'))).toEqual([
      'charges',
      'discounts',
      'payments',
      'refunds',
      'write_offs',
      'adjustments',
      'collected',
      'forgiven',
      'balance'
    ])

    expect(wrapper.get('[data-statement-summary-row="write_offs"]').text()).toContain(
      'Condonaciones'
    )
    expect(wrapper.get('[data-statement-summary-value="charges"]').text()).toBe(formatCurrency(400))
    expect(wrapper.get('[data-statement-summary-value="adjustments"]').text()).toBe(
      formatCurrency(-5)
    )

    wrapper.unmount()
    wrapper = null
  })

  it('renders the period entries with their kind label, amounts and running balance', async () => {
    wrapper = await mountPanel()

    const values = key =>
      wrapper.findAll(`[data-statement-entry="${key}"]`).map(cell => cell.text())

    expect(values('reference')).toEqual(['CHG-000042', 'TRX-000123', 'TRX-000130'])
    expect(values('description')).toEqual([
      'Corona metal-cerámica',
      'Pago en efectivo',
      'Devolución aprobada'
    ])
    expect(values('kind')).toEqual(['Cargo', 'Pago', 'Devolución'])
    expect(values('amount')).toEqual([formatCurrency(400), formatCurrency(250), formatCurrency(10)])
    expect(values('effect')).toEqual([
      formatCurrency(400),
      formatCurrency(-250),
      formatCurrency(10)
    ])
    expect(values('balance')).toEqual([
      formatCurrency(480),
      formatCurrency(230),
      formatCurrency(240)
    ])
    expect(values('date')).toEqual(['3/10/2026', '10/10/2026', '12/10/2026'])

    wrapper.unmount()
    wrapper = null
  })

  it('hides the manual charge door without the flag and emits it with one', async () => {
    wrapper = await mountPanel()
    expect(wrapper.find('[data-statement-charge]').exists()).toBe(false)
    wrapper.unmount()
    wrapper = null

    wrapper = await mountPanel({ canManageCharge: true })
    const button = wrapper.get('[data-statement-charge]')
    expect(button.text()).toContain('Registrar cargo')

    await button.trigger('click')
    expect(wrapper.emitted('charge')).toHaveLength(1)

    wrapper.unmount()
    wrapper = null
  })

  it('applies an explicit period and exposes reload for the page', async () => {
    wrapper = await mountPanel()

    await setPanelField('[data-statement-date-from]', '2026-09-01')
    await setPanelField('[data-statement-date-to]', '2026-09-30')
    await wrapper.get('[data-statement-apply]').trigger('click')
    await flushPromises()

    expect(harness.get).toHaveBeenLastCalledWith(statementUrl('2026-09-01', '2026-09-30'))

    await wrapper.vm.reload()
    await flushPromises()
    expect(harness.get).toHaveBeenCalledTimes(3)

    wrapper.unmount()
    wrapper = null
  })

  it('renders the empty state when the period has no movements', async () => {
    harness.get.mockResolvedValue({ data: { ...STATEMENT, entries: [] } })

    wrapper = await mountPanel()

    expect(wrapper.find('[data-statement-empty]').exists()).toBe(true)
    expect(wrapper.find('[data-statement-table]').exists()).toBe(false)
    // The period still reconciles: the totals stay on screen.
    expect(wrapper.get('[data-statement-summary-value="charges"]').text()).toBe(formatCurrency(400))

    wrapper.unmount()
    wrapper = null
  })

  it('shows a retryable error state that re-reads the statement', async () => {
    harness.get.mockRejectedValueOnce(new Error('network down'))

    wrapper = await mountPanel()

    const error = wrapper.get('[data-statement-error]')
    expect(error.attributes('data-state')).toBe('error-cuenta')
    expect(error.text()).toContain('No pudimos cargar el estado de cuenta')
    expect(wrapper.find('[data-statement-table]').exists()).toBe(false)

    await wrapper.get('[data-statement-retry]').trigger('click')
    await flushPromises()

    expect(harness.get).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-statement-error]').exists()).toBe(false)
    expect(wrapper.find('[data-statement-table]').exists()).toBe(true)

    wrapper.unmount()
    wrapper = null
  })
})

describe('patient charge modal (#320 S2)', () => {
  it('posts the manual charge for the patient with the counter form', async () => {
    harness.post.mockResolvedValue({
      data: { id: 91 },
      meta: { message: 'Cargo registrado exitosamente' }
    })

    wrapper = await mountModal()

    expect(node('[data-patient-charge-patient]').textContent).toContain('Ana Torres')
    expect(node('[data-patient-charge-amount]').textContent).toContain('Importe')
    expect(node('[data-patient-charge-description]').textContent).toContain('Descripción')

    const today = dateOnly(new Date())
    expect(node('[data-patient-charge-charged-at] input').value).toBe(today)
    expect(node('[data-patient-charge-charged-at] input').getAttribute('max')).toBe(today)

    await setField('[data-patient-charge-amount]', '150.5')
    await setField('[data-patient-charge-description]', '  Corona metal-cerámica  ')

    node('[data-patient-charge-submit]').click()
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/patient-charges', {
      patient_id: 7,
      amount: 150.5,
      description: 'Corona metal-cerámica',
      charged_at: today,
      notes: null
    })
    expect(wrapper.emitted('success')).toHaveLength(1)

    wrapper.unmount()
    wrapper = null
  })

  it('maps the 422 to its fields plus a general error and creates nothing', async () => {
    harness.post.mockRejectedValue({
      response: {
        data: {
          message: 'Datos inválidos',
          errors: {
            amount: ['El importe del cargo debe ser mayor que cero.'],
            description: ['La descripción del cargo es requerida.']
          }
        }
      }
    })

    wrapper = await mountModal()

    await setField('[data-patient-charge-amount]', '25')
    await setField('[data-patient-charge-description]', 'Corona')

    node('[data-patient-charge-submit]').click()
    await flushPromises()

    expect(node('[data-patient-charge-error="amount"]').textContent).toContain(
      'El importe del cargo debe ser mayor que cero.'
    )
    expect(node('[data-patient-charge-error="description"]').textContent).toContain(
      'La descripción del cargo es requerida.'
    )
    expect(node('[data-patient-charge-error="message"]').textContent).toContain('Datos inválidos')

    // The rejected charge is not a row: nothing was created and the modal stays.
    expect(wrapper.emitted('success')).toBeUndefined()
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(node('[data-patient-charge-submit]')).toBeTruthy()

    wrapper.unmount()
    wrapper = null
  })

  it('maps a rejected charged_at to its own field', async () => {
    harness.post.mockRejectedValue({
      response: {
        data: {
          message: 'Datos inválidos',
          errors: { charged_at: ['La fecha del cargo no puede ser futura.'] }
        }
      }
    })

    wrapper = await mountModal()
    await setField('[data-patient-charge-amount]', '40')
    await setField('[data-patient-charge-description]', 'Anticipo')

    node('[data-patient-charge-submit]').click()
    await flushPromises()

    expect(node('[data-patient-charge-error="charged_at"]').textContent).toContain(
      'La fecha del cargo no puede ser futura.'
    )
    expect(wrapper.emitted('success')).toBeUndefined()

    wrapper.unmount()
    wrapper = null
  })

  it('blocks the request when the form itself is incomplete', async () => {
    wrapper = await mountModal()

    node('[data-patient-charge-submit]').click()
    await flushPromises()

    expect(harness.post).not.toHaveBeenCalled()
    expect(node('[data-patient-charge-error="amount"]')).toBeTruthy()
    expect(node('[data-patient-charge-error="description"]')).toBeTruthy()
    expect(wrapper.emitted('success')).toBeUndefined()

    wrapper.unmount()
    wrapper = null
  })
})
