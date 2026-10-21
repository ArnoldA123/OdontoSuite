import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h, ref } from 'vue'

// Treatment plan detail overlay (treatment-plans-ui-redesign T5, issue #242).
// The dialog now rides the shared UiModal primitive: the `data-plan-detail`
// hook lives on the teleported backdrop, the panel owns the dialog semantics
// and the heading label, and Escape/backdrop dismissal belongs to the
// primitive's stack. Every DOM assertion reads <body>, where UiModal teleports.
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
import UiModal from '../../../resources/js/components/ui/Modal.vue'
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

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

// UiModal teleports the dialog to <body>, outside the wrapper's tree, so the
// helpers below read the rendered document.
const root = () => document.querySelector('[data-plan-detail]')
const dialog = () => document.querySelector('[data-plan-detail] [role="dialog"]')
const query = selector => document.querySelector(selector)
const queryAll = selector => [...document.querySelectorAll(selector)]

const pressEscape = () => {
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
}

const mountDetail = (overrides = {}) =>
  mount(Detail, {
    props: { plan: { ...plan, ...overrides } },
    global: { plugins: [router, uiComponents] },
    attachTo: document.body
  })

// formatCurrency may emit non-breaking spaces; compare condensed text only.
const flat = value => value.replace(/\s+/g, ' ').trim()

let wrapper = null

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
})

describe('treatment plan detail smoke', () => {
  it('renders the plan identity as an accessible dialog on the modal primitive', () => {
    wrapper = mountDetail()

    expect(root(), 'the detail hook must be the modal root').toBeTruthy()
    expect(root().getAttribute('role')).toBeNull()

    const panel = dialog()
    expect(panel, 'UiModal must own the dialog role').toBeTruthy()
    expect(panel.getAttribute('aria-modal')).toBe('true')

    const heading = query('[data-detail-title]')
    expect(heading.textContent).toBe('Rehabilitación oral integral')
    expect(panel.getAttribute('aria-labelledby')).toBe(heading.getAttribute('id'))

    expect(flat(root().textContent)).toContain('PT-2026-0042')
    expect(flat(root().textContent)).toContain('Lucía Ramírez')
    expect(flat(root().textContent)).toContain('lucia@test.com')
    expect(flat(root().textContent)).toContain('987654321')
    expect(flat(query('[data-flag="overdue"]').textContent)).toBe('Vencido')
    expect(flat(query('[data-flag="urgent"]').textContent)).toBe('Urgente')
  })

  it('formats the plan total and every line amount through formatCurrency', () => {
    wrapper = mountDetail()

    expect(flat(query('[data-detail-total]').textContent)).toBe(
      flat(formatCurrency(plan.final_cost))
    )

    const rows = queryAll('[data-detail-item]')
    expect(rows).toHaveLength(2)
    expect(flat(rows[0].textContent)).toContain('Corona de zirconio')
    expect(flat(rows[0].textContent)).toContain('Fase 1')
    expect(flat(rows[0].textContent)).toContain(flat(formatCurrency(500)))
    expect(flat(rows[0].textContent)).toContain(flat(formatCurrency(1000)))
    expect(flat(rows[1].textContent)).toContain(flat(formatCurrency(250.5)))

    expect(flat(query('[data-detail-quotation]').textContent)).toContain('COT-0007')
    expect(flat(query('[data-detail-quotation]').textContent)).toContain(
      flat(formatCurrency(1250.5))
    )
  })

  it('emits edit, duplicate and close from the footer actions', async () => {
    wrapper = mountDetail()

    query('[data-action="edit"]').click()
    await flushPromises()
    query('[data-action="duplicate"]').click()
    await flushPromises()
    query('[data-action="close"]').click()
    await flushPromises()

    expect(wrapper.emitted('edit')).toEqual([[plan]])
    expect(wrapper.emitted('duplicate')).toEqual([[plan]])
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('dismisses on Escape and on backdrop click but not on panel clicks', async () => {
    wrapper = mountDetail()

    dialog().click()
    await flushPromises()
    expect(wrapper.emitted('close')).toBeUndefined()

    root().click()
    await flushPromises()
    expect(wrapper.emitted('close')).toHaveLength(1)

    pressEscape()
    await flushPromises()
    expect(wrapper.emitted('close')).toHaveLength(2)
  })

  it('lets an inner modal own Escape while the detail stays open', async () => {
    const closed = ref(0)
    let openInner = () => {}

    const Host = defineComponent({
      setup() {
        const inner = ref(false)
        openInner = () => {
          inner.value = true
        }

        return () =>
          h('div', [
            h(Detail, {
              plan,
              onClose: () => {
                closed.value += 1
              }
            }),
            h(
              UiModal,
              {
                modelValue: inner.value,
                'onUpdate:modelValue': value => {
                  inner.value = value
                },
                title: 'Editar plan',
                'data-inner-modal': ''
              },
              { default: () => h('p', 'inner') }
            )
          ])
      }
    })

    wrapper = mount(Host, { global: { plugins: [router, uiComponents] }, attachTo: document.body })
    await flushPromises()

    openInner()
    await flushPromises()
    expect(document.querySelector('[data-inner-modal] [role="dialog"]')).toBeTruthy()

    pressEscape()
    await flushPromises()

    expect(document.querySelector('[data-inner-modal]')).toBeNull()
    expect(root(), 'the detail must survive the inner close').toBeTruthy()
    expect(closed.value).toBe(0)
  })

  it('falls back to explicit placeholders when optional data is missing', () => {
    wrapper = mountDetail({ items: [], quotations: [], notes: null, patient_notes: null })

    expect(query('[data-detail-empty="procedures"]').textContent).toContain('No hay procedimientos')
    expect(query('[data-detail-section="quotations"]')).toBeNull()
    expect(query('[data-detail-field="notes"]')).toBeNull()
  })
})
