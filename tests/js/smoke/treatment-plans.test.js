import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const requests = []

const SUMMARY = {
  total: 9,
  by_status: {
    draft: 1,
    proposed: 2,
    approved: 1,
    in_progress: 1,
    completed: 3,
    cancelled: 1
  },
  active: 5,
  overdue: 1,
  pending_approval: 2,
  pending_cost: 1500
}

const PLAN = {
  id: 7,
  plan_number: 'TP-2026-ABC123',
  title: 'Rehabilitación oral',
  status: 'proposed',
  final_cost: 1500,
  estimated_duration_weeks: 6,
  created_at: '2026-10-01T10:00:00Z',
  is_overdue: true,
  patient: { first_name: 'Ana', last_name: 'Quispe' },
  progress: {
    total_items: 2,
    completed_items: 1,
    progress_percentage: 25,
    completed_cost: 0,
    remaining_cost: 1500
  },
  // #417: the index now publishes the lines of every plan, so the row the card
  // hands to the detail and the edit form is the same document `GET /{id}`
  // returns.
  items: [
    {
      id: 1,
      procedure_name: 'Limpieza dental',
      procedure_description: 'Detartraje supragingival',
      quantity: 1,
      unit_cost: 500,
      total_cost: 500,
      phase_number: 1
    },
    {
      id: 2,
      procedure_name: 'Corona de porcelana',
      procedure_description: 'Pieza 16',
      quantity: 1,
      unit_cost: 1000,
      total_cost: 1000,
      phase_number: 2,
      dental_piece_id: 16
    }
  ]
}

const apiState = vi.hoisted(() => ({ postReject: null, plans: [] }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      requests.push({ method: 'get', url })
      if (url.includes('/summary')) return { data: SUMMARY }
      return {
        data: apiState.plans,
        meta: { current_page: 1, last_page: 3, per_page: 15, total: 31 }
      }
    },
    post: async (url, body) => {
      requests.push({ method: 'post', url, body })
      if (apiState.postReject) throw apiState.postReject
      return { data: { ...PLAN, id: 8 } }
    },
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
    channel: () => ({ listen: () => ({ listen: () => ({ listen: () => ({}) }) }) }),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

// The plan modal asks the shared confirm prompt before discarding edits; the
// page-level regression below only needs to know that the prompt ran.
const confirmMock = vi.hoisted(() => vi.fn())
vi.mock('../../../resources/js/composables/useConfirm', async () => {
  const { ref } = await import('vue')
  return {
    confirmIsOpen: ref(false),
    confirmTitle: ref(''),
    confirmMessage: ref(''),
    confirmConfirmText: ref(''),
    confirmCancelText: ref(''),
    confirmVariant: ref('default'),
    useConfirm: () => ({
      confirm: confirmMock,
      handleConfirm: () => {},
      handleCancel: () => {}
    })
  }
})

import rolePermissions from '../support/role-permissions.json'
import { globalToasts } from '../../../resources/js/composables/useToast'
import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/treatment-plans/TreatmentPlansPage.vue'
import TreatmentPlanCard from '../../../resources/js/modules/treatment-plans/components/TreatmentPlanCard.vue'

// #420: the page gates the create affordances on `usePermissions().can.*`,
// and those flags are a membership check on the permission list the API
// publishes, so a session is seeded with that list and never with a role
// lookup.
const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = async () => {
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const lastListRequest = () =>
  [...requests].reverse().find(r => r.url.includes('/api/treatment-plans?'))
const lastSummaryRequest = () => [...requests].reverse().find(r => r.url.includes('/summary'))

describe('treatment-plans page', () => {
  beforeEach(() => {
    requests.length = 0
    apiState.postReject = null
    apiState.plans = [{ ...PLAN }]
    globalToasts.value = []
    confirmMock.mockReset()
    confirmMock.mockResolvedValue(true)
    seedSession('administrador')
  })

  it('mounts TreatmentPlansPage.vue and renders content', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('Planes de Tratamiento')
    wrapper.unmount()
  })

  it('renders the KPI strip from the summary endpoint, not from the visible page', async () => {
    const wrapper = await mountPage()

    expect(lastSummaryRequest()).toBeTruthy()
    expect(wrapper.get('[data-kpi-value="active"]').text()).toBe('5')
    expect(wrapper.get('[data-kpi-value="pending-approval"]').text()).toBe('2')
    expect(wrapper.get('[data-kpi-value="overdue"]').text()).toBe('1')
    expect(wrapper.get('[data-kpi-value="pending-cost"]').text()).toContain('1,500')

    wrapper.unmount()
  })

  it('renders the summary total on the pagination strip', async () => {
    const wrapper = await mountPage()

    expect(wrapper.text()).toContain('31')

    wrapper.unmount()
  })

  it('sends the real API filter when the Activos preset is pressed', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-quick-filter="active"]').trigger('click')
    await flushPromises()

    const { url } = lastListRequest()
    expect(url).toContain('statuses=draft%2Cproposed%2Capproved%2Cin_progress')
    expect(url).not.toContain('status=active')

    wrapper.unmount()
  })

  it('sends overdue=1 for the Vencidos preset and releases the manual status', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-quick-filter="overdue"]').trigger('click')
    await flushPromises()

    const { url } = lastListRequest()
    expect(url).toContain('overdue=1')
    expect(url).not.toContain('status=overdue')

    wrapper.unmount()
  })

  it('opens the detail and the edit form with the lines the list publishes', async () => {
    const wrapper = await mountPage()

    // The card count comes from the same payload: before #417 the index had no
    // lines and the card announced "0 procedimientos".
    expect(wrapper.get('[data-plan-items]').text()).toBe('2')

    wrapper.findComponent(TreatmentPlanCard).vm.$emit('view', PLAN)
    await flushPromises()

    const detail = document.querySelector('[data-plan-detail]')
    expect(detail).toBeTruthy()
    expect(detail.querySelectorAll('[data-detail-item]')).toHaveLength(2)
    expect(detail.textContent).toContain('Corona de porcelana')

    detail.querySelector('[data-action="edit"]').click()
    await flushPromises()

    const itemRows = document.querySelectorAll('[data-plan-item]')
    expect(itemRows).toHaveLength(2)
    expect(itemRows[0].querySelector('[data-plan-item-field="name"] input').value).toBe(
      'Limpieza dental'
    )
    expect(itemRows[1].querySelector('[data-plan-item-field="name"] input').value).toBe(
      'Corona de porcelana'
    )

    wrapper.unmount()
    document.body.innerHTML = ''
  })

  it('duplicates a plan through the plan id, never through the whole object', async () => {
    const wrapper = await mountPage()

    wrapper.findComponent(TreatmentPlanCard).vm.$emit('duplicate', { id: 7 })
    await flushPromises()

    const duplicate = requests.find(r => r.method === 'post' && r.url.includes('/duplicate'))
    expect(duplicate).toBeTruthy()
    expect(duplicate.url).toBe('/api/treatment-plans/7/duplicate')
    expect(duplicate.url).not.toContain('[object Object]')

    wrapper.unmount()
  })

  it('keeps the list and kanban surfaces behind the view toggle', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-plan-list]').exists()).toBe(true)

    await wrapper.get('[data-view="kanban"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-plan-list]').exists()).toBe(false)
    expect(wrapper.find('[data-kanban-column]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('keeps the open plan form untouched when the page shortcut fires', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-action="edit"]').trigger('click')
    await flushPromises()

    const titleInput = document.querySelector('[data-plan-field="title"] input')
    expect(titleInput.value).toBe('Rehabilitación oral')

    titleInput.value = 'Rehabilitación superior'
    titleInput.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    // Focus outside an input, so the page shortcut is eligible: re-opening the
    // overlay here would drop the selected plan and re-initialize the form.
    const shortcutTarget = document.querySelector('[data-plan-submit]')
    shortcutTarget.focus()
    shortcutTarget.dispatchEvent(new KeyboardEvent('keydown', { key: 'n', bubbles: true }))
    await flushPromises()

    expect(document.querySelector('[data-plan-title]').textContent).toContain('Editar Plan')
    expect(document.querySelector('[data-plan-field="title"] input').value).toBe(
      'Rehabilitación superior'
    )

    wrapper.unmount()
  })

  it('does not let the page Escape shortcut bypass the plan dirty guard', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-create-plan]').trigger('click')
    await flushPromises()

    const titleInput = document.querySelector('[data-plan-field="title"] input')
    expect(titleInput, 'the create modal must render its title field').toBeTruthy()

    // Clean form: Escape belongs to the modal and closes it without a prompt.
    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()
    expect(confirmMock).not.toHaveBeenCalled()
    expect(document.querySelector('[data-plan-modal]')).toBeNull()

    await wrapper.get('[data-create-plan]').trigger('click')
    await flushPromises()

    const editedTitle = document.querySelector('[data-plan-field="title"] input')
    editedTitle.value = 'Rehabilitación superior'
    editedTitle.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    confirmMock.mockResolvedValueOnce(false)
    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()

    expect(confirmMock).toHaveBeenCalledTimes(1)
    expect(document.querySelector('[data-plan-modal]')).toBeTruthy()

    wrapper.unmount()
  })

  it('hides the create affordances from a session without manage.treatment_plan', async () => {
    seedSession('finanzas')
    const finance = await mountPage()

    expect(finance.find('[data-create-plan]').exists()).toBe(false)

    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'n', bubbles: true }))
    await flushPromises()
    expect(document.querySelector('[data-plan-modal]')).toBeNull()

    finance.unmount()

    seedSession('recepcionista')
    const reception = await mountPage()

    expect(reception.find('[data-create-plan]').exists()).toBe(false)

    reception.unmount()

    seedSession('administrador')
    const admin = await mountPage()

    expect(admin.find('[data-create-plan]').exists()).toBe(true)

    // Positive control: the shortcut is gated by the permission, not broken.
    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'n', bubbles: true }))
    await flushPromises()
    expect(document.querySelector('[data-plan-modal]')).not.toBeNull()

    admin.unmount()
    document.body.innerHTML = ''
  })

  it('hides the empty-state create action without manage.treatment_plan', async () => {
    apiState.plans = []

    seedSession('finanzas')
    const finance = await mountPage()

    expect(finance.find('[data-state="empty"]').exists()).toBe(true)
    expect(finance.find('[data-empty-create]').exists()).toBe(false)

    finance.unmount()

    seedSession('administrador')
    const admin = await mountPage()

    expect(admin.find('[data-empty-create]').exists()).toBe(true)

    admin.unmount()
  })

  it('reports the API reason when a status change is rejected with a generic 422', async () => {
    apiState.postReject = {
      response: {
        status: 422,
        data: {
          message: 'Error de validación',
          errors: {
            status: ['No se puede completar el plan: 2 procedimiento(s) siguen sin ejecutar.']
          }
        }
      }
    }

    const wrapper = await mountPage()

    const card = wrapper.findComponent(TreatmentPlanCard)
    expect(card.exists()).toBe(true)
    card.vm.$emit('changeStatus', PLAN.id, 'approved')
    await flushPromises()

    expect(globalToasts.value.map(toast => toast.message)).toContain(
      'No se puede completar el plan: 2 procedimiento(s) siguen sin ejecutar.'
    )

    wrapper.unmount()
  })
})
