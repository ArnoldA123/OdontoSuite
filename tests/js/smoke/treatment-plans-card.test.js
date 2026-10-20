import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

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
import TreatmentPlanCard from '../../../resources/js/modules/treatment-plans/components/TreatmentPlanCard.vue'
import KanbanColumn from '../../../resources/js/modules/treatment-plans/components/KanbanColumn.vue'
import PlanStatusBadge from '../../../resources/js/modules/treatment-plans/components/PlanStatusBadge.vue'
import StatusBadge from '../../../resources/js/components/ui/StatusBadge.vue'

const PLAN = {
  id: 7,
  plan_number: 'TP-2026-ABC123',
  title: 'Rehabilitación oral',
  status: 'proposed',
  final_cost: 1500,
  estimated_duration_weeks: 6,
  created_at: '2026-10-01T10:00:00Z',
  is_overdue: true,
  notes: 'Paciente con alergia a la penicilina',
  patient: { first_name: 'Ana', last_name: 'Quispe' },
  progress: {
    total_items: 4,
    completed_items: 1,
    progress_percentage: 25
  }
}

const APPROVED_COLUMN = { value: 'approved', label: 'Aprobado', tone: 'green' }

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Test User', role }))
}

const mountCard = (plan = PLAN) =>
  mount(TreatmentPlanCard, {
    props: { plan },
    global: { plugins: [uiComponents] }
  })

const mountColumn = (column = APPROVED_COLUMN, plans = [PLAN]) =>
  mount(KanbanColumn, {
    props: { column, plans },
    global: { plugins: [uiComponents] }
  })

// jsdom has no DragEvent, and Vue Test Utils cannot inject `dataTransfer`
// through `trigger`. Dispatching a plain Event with the transfer attached is
// the only deterministic way to exercise the drag-and-drop contract.
const createDataTransfer = () => {
  const store = new Map()

  return {
    setData: (type, value) => store.set(type, String(value)),
    getData: type => store.get(type) ?? '',
    clearData: type => store.delete(type),
    effectAllowed: 'move',
    dropEffect: 'move'
  }
}

const dispatchWithTransfer = (element, type, dataTransfer) => {
  const event = new Event(type, { bubbles: true, cancelable: true })
  Object.defineProperty(event, 'dataTransfer', { value: dataTransfer })
  element.dispatchEvent(event)
}

describe('treatment-plan card', () => {
  beforeEach(() => {
    signInAs('administrador')
  })

  it('renders the plan identity, status, patient, cost and progress', () => {
    const wrapper = mountCard()

    const card = wrapper.get('[data-plan-card]')
    expect(card.text()).toContain('Rehabilitación oral')
    expect(card.text()).toContain('TP-2026-ABC123')
    expect(wrapper.get('[data-plan-patient]').text()).toBe('Ana Quispe')
    expect(wrapper.get('[data-plan-cost]').text()).toContain('1,500')
    expect(wrapper.get('[data-plan-items]').text()).toBe('4')
    expect(wrapper.get('[data-plan-overdue]').text()).toContain('Vencido')
    expect(wrapper.get('[data-plan-progress]').text()).toContain('1/4 completados')
    expect(wrapper.get('[data-plan-progress]').text()).toContain('25%')

    const badge = wrapper.findComponent(PlanStatusBadge)
    expect(badge.text()).toBe('Propuesto')
    expect(badge.findComponent(StatusBadge).props('variant')).toBe('info')

    wrapper.unmount()
  })

  it('emits the plan when the card is clicked for viewing', async () => {
    const wrapper = mountCard()

    await wrapper.get('[data-plan-card]').trigger('click')

    expect(wrapper.emitted('view')).toEqual([[PLAN]])

    wrapper.unmount()
  })

  it('emits changeStatus with the plan id and the target status', async () => {
    const wrapper = mountCard()

    await wrapper.get('[data-status-toggle]').trigger('click')
    await flushPromises()

    const options = wrapper.findAll('[data-status-option]')
    expect(options.map(option => option.text())).toEqual(['Aprobado', 'Cancelado'])

    await options[0].trigger('click')
    await flushPromises()

    expect(wrapper.emitted('changeStatus')).toEqual([[7, 'approved']])
    expect(wrapper.find('[data-status-menu]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('emits the whole plan object on duplicate', async () => {
    const wrapper = mountCard()

    await wrapper.get('[data-action="duplicate"]').trigger('click')
    await flushPromises()

    const emitted = wrapper.emitted('duplicate')
    expect(emitted).toHaveLength(1)
    expect(emitted[0][0]).toEqual(PLAN)
    expect(emitted[0][0].id).toBe(7)

    wrapper.unmount()
  })

  it('keeps the delete action disabled unless the role and status allow it', () => {
    const wrapper = mountCard()

    // `proposed` plans are not deletable, even for an administrator.
    expect(wrapper.get('[data-action="delete"]').attributes('disabled')).toBeDefined()

    wrapper.unmount()
  })
})

describe('treatment-plan status badge', () => {
  const cases = [
    ['draft', 'neutral', 'Borrador', false],
    ['proposed', 'info', 'Propuesto', false],
    ['approved', 'success', 'Aprobado', false],
    ['in_progress', 'warning', 'En Progreso', false],
    ['completed', 'success', 'Completado', true],
    ['cancelled', 'error', 'Cancelado', false]
  ]

  it.each(cases)('maps %s onto the shared primitive as %s', (status, variant, label, showDot) => {
    const wrapper = mount(PlanStatusBadge, { props: { status } })

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('variant')).toBe(variant)
    expect(badge.props('showDot')).toBe(showDot)
    expect(wrapper.text()).toBe(label)

    wrapper.unmount()
  })
})

describe('treatment-plan kanban column', () => {
  it('emits dropPlan with the dragged plan and the target status', async () => {
    const wrapper = mountColumn()
    const dataTransfer = createDataTransfer()

    dispatchWithTransfer(wrapper.get('[data-kanban-card]').element, 'dragstart', dataTransfer)
    dispatchWithTransfer(wrapper.get('[data-kanban-drop-zone]').element, 'drop', dataTransfer)
    await flushPromises()

    expect(wrapper.emitted('dropPlan')).toEqual([[{ plan: PLAN, newStatus: 'approved' }]])

    wrapper.unmount()
  })

  it('ignores a drop onto the column the plan already belongs to', async () => {
    const wrapper = mountColumn({ value: 'proposed', label: 'Propuesto', tone: 'steel' })
    const dataTransfer = createDataTransfer()

    dispatchWithTransfer(wrapper.get('[data-kanban-card]').element, 'dragstart', dataTransfer)
    dispatchWithTransfer(wrapper.get('[data-kanban-drop-zone]').element, 'drop', dataTransfer)
    await flushPromises()

    expect(wrapper.emitted('dropPlan')).toBeUndefined()

    wrapper.unmount()
  })

  it('emits view when a kanban card is clicked', async () => {
    const wrapper = mountColumn()

    await wrapper.get('[data-kanban-card]').trigger('click')

    expect(wrapper.emitted('view')).toEqual([[PLAN]])

    wrapper.unmount()
  })
})
