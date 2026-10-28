import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Local calendar key: `toISOString` shifts to UTC and, from 19:00 in UTC-5,
// yields tomorrow's date, so the "Vence en N días" badge drifted by one.
const addDays = days => {
  const date = new Date()
  date.setDate(date.getDate() + days)

  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

const rows = [
  {
    id: 1,
    quotation_number: 'PR-DRAFT-001',
    quotation_date: '2026-10-01',
    valid_until: addDays(40),
    status: 'draft',
    subtotal: 250,
    total_amount: 250,
    patient: { id: 11, first_name: 'Ana', last_name: 'Torres', document_number: '12345678' },
    items: [{ id: 1, item_name: 'Limpieza', quantity: 1, unit_price: 250 }]
  },
  {
    id: 2,
    quotation_number: 'PR-SENT-002',
    quotation_date: '2026-10-02',
    valid_until: addDays(-2),
    status: 'sent',
    subtotal: 480.5,
    total_amount: 480.5,
    patient: { id: 12, first_name: 'Luis', last_name: 'Rojas', document_number: '87654321' },
    items: [{ id: 2, item_name: 'Endodoncia', quantity: 1, unit_price: 480.5 }]
  },
  {
    id: 3,
    quotation_number: 'PR-VIEW-003',
    quotation_date: '2026-10-03',
    valid_until: addDays(3),
    status: 'viewed',
    subtotal: 900,
    total_amount: 900,
    patient: { id: 13, first_name: 'Rosa', last_name: 'Quispe', document_number: null },
    items: [{ id: 3, item_name: 'Implante', quantity: 1, unit_price: 900 }]
  }
]

const summary = {
  total: 12,
  pending: 5,
  approved_total: 1500.5,
  expiring_soon: 2,
  overdue: 1,
  by_status: {
    draft: { count: 3, amount: 700 },
    sent: { count: 3, amount: 1400 },
    viewed: { count: 2, amount: 1100 },
    approved: { count: 4, amount: 1500.5 },
    rejected: { count: 0, amount: 0 },
    expired: { count: 0, amount: 0 }
  }
}

const apiState = vi.hoisted(() => ({ postReject: null, quotations: [] }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      if (String(url).includes('/api/quotations/summary')) return { data: summary }
      if (/\/api\/quotations\/\d+$/.test(String(url))) return { data: rows[0] }
      return {
        data: apiState.quotations,
        meta: { current_page: 1, last_page: 1, per_page: 15, total: apiState.quotations.length }
      }
    },
    post: async () => {
      if (apiState.postReject) throw apiState.postReject
      return { data: {} }
    },
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

vi.mock('../../../resources/js/composables/useEcho', () => {
  const chain = { listen: () => chain }
  return {
    useEcho: () => ({
      echo: { leave: () => {} },
      channel: () => chain,
      privateChannel: () => chain,
      connectionStatus: { value: 'connected' }
    })
  }
})

vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import rolePermissions from '../support/role-permissions.json'
import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/quotations/QuotationsPage.vue'

// #420: the page gates every action on `usePermissions().can.*`, and those
// flags are a membership check on the permission list the API publishes, so a
// session is seeded with that list and never with a role lookup.
const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const mountPageAt = async path => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push(path)
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()

  return { wrapper, router }
}

const mountPage = async () => (await mountPageAt('/quotations')).wrapper

describe('quotations list redesign', () => {
  beforeEach(() => {
    apiState.postReject = null
    apiState.quotations = rows
    seedSession('administrador')
  })

  it('renders the hero band and the KPI strip from the server summary', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-quotations-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toBe('Presupuestos')

    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('12')
    expect(wrapper.find('[data-kpi-value="pending"]').text()).toBe('5')
    expect(wrapper.find('[data-kpi-value="approved"]').text()).toBe('4')
    expect(wrapper.find('[data-kpi-value="approved-total"]').text()).toBe('S/\u00A01,500.50')
    expect(wrapper.find('[data-kpi-caption="pending"]').text()).toBe('2 por vencer esta semana')

    wrapper.unmount()
  })

  it('renders every row on the shared table with the enum status labels', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-quotations-table] tbody tr')).toHaveLength(3)
    expect(wrapper.text()).toContain('PR-DRAFT-001')
    expect(wrapper.text()).toContain('PR-SENT-002')
    expect(wrapper.text()).toContain('PR-VIEW-003')

    expect(wrapper.text()).toContain('Borrador')
    expect(wrapper.text()).toContain('Enviado')
    expect(wrapper.text()).toContain('Visto')
    expect(wrapper.text()).not.toContain('viewed')

    wrapper.unmount()
  })

  it('shows the derived expiry badge only on pending quotations', async () => {
    const wrapper = await mountPage()

    const expiries = wrapper.findAll('[data-row-expiry]').map(node => node.text())

    expect(expiries).toContain('Vencido')
    expect(expiries).toContain('Vence en 3 días')
    expect(expiries).toHaveLength(2)

    wrapper.unmount()
  })

  it('offers only the actions the contract allows for each status', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-row-action="view"]')).toHaveLength(3)
    expect(wrapper.findAll('[data-row-action="edit"]')).toHaveLength(1)
    expect(wrapper.findAll('[data-row-action="approve"]')).toHaveLength(2)
    expect(wrapper.findAll('[data-row-action="reject"]')).toHaveLength(2)
    expect(wrapper.findAll('[data-row-action="delete"]')).toHaveLength(1)
    expect(wrapper.findAll('[data-row-action="pdf"]')).toHaveLength(3)

    wrapper.unmount()
  })

  it('hides the hero create action from a session without manage.quotation', async () => {
    seedSession('recepcionista')
    const reception = await mountPage()

    expect(reception.find('[data-hero-action="create"]').exists()).toBe(false)

    reception.unmount()

    seedSession('administrador')
    const admin = await mountPage()

    expect(admin.find('[data-hero-action="create"]').exists()).toBe(true)

    admin.unmount()
  })

  it('hides the empty-state create action from a session without manage.quotation', async () => {
    apiState.quotations = []

    seedSession('recepcionista')
    const reception = await mountPage()

    expect(reception.find('[data-quotations-empty]').exists()).toBe(true)
    expect(reception.text()).not.toContain('Nuevo presupuesto')

    reception.unmount()

    seedSession('administrador')
    const admin = await mountPage()

    expect(admin.find('[data-quotations-empty]').exists()).toBe(true)
    expect(admin.text()).toContain('Nuevo presupuesto')

    admin.unmount()
  })

  it('offers the row actions only to the session that can execute them', async () => {
    seedSession('recepcionista')
    const wrapper = await mountPage()

    // Reading is a separate grant: the API publishes view.quotation.
    expect(wrapper.findAll('[data-row-action="view"]')).toHaveLength(3)
    expect(wrapper.findAll('[data-row-action="pdf"]')).toHaveLength(3)

    expect(wrapper.findAll('[data-row-action="edit"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-row-action="approve"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-row-action="reject"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-row-action="delete"]')).toHaveLength(0)

    wrapper.unmount()
  })

  it('paints the API reason when the approval is rejected with a generic 422', async () => {
    apiState.postReject = {
      response: {
        status: 422,
        data: {
          message: 'Error de validación',
          errors: {
            status: [
              'Solo se puede aprobar un presupuesto enviado o visto. Estado actual: approved.'
            ]
          }
        }
      }
    }

    const wrapper = await mountPage()
    await wrapper.find('[data-row-action="approve"]').trigger('click')
    await flushPromises()

    document.querySelector('[data-modal-action="approve"]').click()
    await flushPromises()

    expect(document.querySelector('[data-modal-error]').textContent).toContain(
      'Solo se puede aprobar un presupuesto enviado o visto.'
    )

    wrapper.unmount()
  })

  it('opens the detail named by the open query and drops it on close', async () => {
    const { wrapper, router } = await mountPageAt('/quotations?open=1')

    const detail = document.body.querySelector('[data-quotation-detail]')
    expect(detail).not.toBeNull()
    expect(detail.textContent).toContain('Ana Torres')

    document.body.querySelector('[aria-label="Cerrar modal"]').click()
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/quotations')
    expect(router.currentRoute.value.query.open).toBeUndefined()

    wrapper.unmount()
    document.body.innerHTML = ''
  })
})
