import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Treatment plan detail overlay (treatment-plans-ui-redesign T5). Pins the
// behaviour the page depends on: the plan object renders as the only data
// source, every amount goes through the shared formatCurrency helper, and the
// three footer actions keep emitting `edit`/`duplicate`/`close` with the plan.
vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async () => ({ data: [] }),
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
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
import { formatCurrency } from '../../../resources/js/composables/useFormatters'
import Detail from '../../../resources/js/modules/treatment-plans/components/TreatmentPlanDetail.vue'

const plan = {
  id: 42,
  plan_number: 'PT-2026-0042',
  title: 'Rehabilitación oral integral',
  description: 'Plan integral en tres fases.',
  status: 'approved',
  is_overdue: true,
  is_urgent: true,
  requires_anesthesia: false,
  estimated_duration_weeks: 12,
  phases: [{ id: 1 }, { id: 2 }],
  start_date: '2026-11-02',
  end_date: '2027-01-25',
  final_cost: 1250.5,
  notes: 'Coordinar con laboratorio.',
  patient_notes: 'Evitar alimentos duros.',
  created_at: '2026-10-20T14:30:00.000Z',
  created_by: { first_name: 'Ana', last_name: 'Torres' },
  patient: {
    first_name: 'Lucía',
    last_name: 'Ramírez',
    email: 'lucia@test.com',
    phone: '987654321'
  },
  items: [
    {
      id: 1,
      procedure_name: 'Corona de zirconio',
      phase_number: 1,
      quantity: 2,
      unit_cost: 500,
      total_cost: 1000
    },
    {
      id: 2,
      procedure_name: 'Blanqueamiento',
      phase_number: 2,
      quantity: 1,
      unit_cost: 250.5,
      total_cost: 250.5
    }
  ],
  quotations: [
    {
      id: 7,
      quotation_number: 'COT-0007',
      quotation_date: '2026-10-21',
      total_amount: 1250.5,
      status: 'sent'
    }
  ]
}

const mountDetail = (overrides = {}) => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })

  return mount(Detail, {
    props: { plan: { ...plan, ...overrides } },
    global: { plugins: [router, uiComponents] }
  })
}

// formatCurrency may emit non-breaking spaces; compare condensed text only.
const flat = value => value.replace(/\s+/g, ' ').trim()

describe('treatment plan detail smoke', () => {
  it('renders the plan identity as an accessible dialog', () => {
    const wrapper = mountDetail()

    const root = wrapper.get('[data-plan-detail]')
    expect(root.attributes('role')).toBe('dialog')
    expect(root.attributes('aria-modal')).toBe('true')

    const heading = wrapper.get('[data-detail-title]')
    expect(heading.text()).toBe('Rehabilitación oral integral')
    expect(root.attributes('aria-labelledby')).toBe(heading.attributes('id'))

    expect(flat(wrapper.text())).toContain('PT-2026-0042')
    expect(flat(wrapper.text())).toContain('Lucía Ramírez')
    expect(flat(wrapper.text())).toContain('lucia@test.com')
    expect(flat(wrapper.text())).toContain('987654321')
    expect(wrapper.get('[data-flag="overdue"]').text()).toBe('Vencido')
    expect(wrapper.get('[data-flag="urgent"]').text()).toBe('Urgente')

    wrapper.unmount()
  })

  it('formats the plan total and every line amount through formatCurrency', () => {
    const wrapper = mountDetail()

    expect(flat(wrapper.get('[data-detail-total]').text())).toBe(
      flat(formatCurrency(plan.final_cost))
    )

    const rows = wrapper.findAll('[data-detail-item]')
    expect(rows).toHaveLength(2)
    expect(flat(rows[0].text())).toContain('Corona de zirconio')
    expect(flat(rows[0].text())).toContain('Fase 1')
    expect(flat(rows[0].text())).toContain(flat(formatCurrency(500)))
    expect(flat(rows[0].text())).toContain(flat(formatCurrency(1000)))
    expect(flat(rows[1].text())).toContain(flat(formatCurrency(250.5)))

    expect(flat(wrapper.get('[data-detail-quotation]').text())).toContain('COT-0007')
    expect(flat(wrapper.get('[data-detail-quotation]').text())).toContain(
      flat(formatCurrency(1250.5))
    )

    wrapper.unmount()
  })

  it('emits edit, duplicate and close from the footer actions', async () => {
    const wrapper = mountDetail()

    await wrapper.get('[data-action="edit"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-action="duplicate"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-action="close"]').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('edit')).toEqual([[plan]])
    expect(wrapper.emitted('duplicate')).toEqual([[plan]])
    expect(wrapper.emitted('close')).toHaveLength(1)

    wrapper.unmount()
  })

  it('dismisses on Escape and on backdrop click but not on panel clicks', async () => {
    const wrapper = mountDetail()

    await wrapper.get('[data-detail-body]').trigger('click')
    await flushPromises()
    expect(wrapper.emitted('close')).toBeUndefined()

    await wrapper.trigger('click')
    await flushPromises()
    expect(wrapper.emitted('close')).toHaveLength(1)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(wrapper.emitted('close')).toHaveLength(2)

    wrapper.unmount()
  })

  it('falls back to explicit placeholders when optional data is missing', () => {
    const wrapper = mountDetail({ items: [], quotations: [], notes: null, patient_notes: null })

    expect(wrapper.get('[data-detail-empty="procedures"]').text()).toContain(
      'No hay procedimientos'
    )
    expect(wrapper.find('[data-detail-section="quotations"]').exists()).toBe(false)
    expect(wrapper.find('[data-detail-field="notes"]').exists()).toBe(false)

    wrapper.unmount()
  })
})
