import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #305 T7 — self-service branch transfers. The page asks for a transfer, lists
// exactly the rows the API scoped for the actor and, only with group scope,
// decides the pending ones. The API contract is mocked here: the backend is the
// other half of the issue.
//
// `useAuth().user.branch_id` carries the affiliation (null = group scope), and
// it is what separates gerencia from a sede administrator: both hold
// `decide.branch_transfer`, but only group scope may decide (D6).
const harness = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() },
  can: { request: true, decide: false },
  user: { id: 7, name: 'Dra. Elsa Pérez', role: 'odontologo', branch_id: 1 },
  refreshSession: vi.fn()
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
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))
vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => harness.toast,
  globalToasts: { value: [] }
}))
vi.mock('../../../resources/js/composables/useAuth', () => ({
  useAuth: () => ({
    user: { value: harness.user },
    isAuthenticated: { value: true },
    getCurrentUser: harness.refreshSession,
    logout: vi.fn()
  })
}))
vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      requestBranchTransfer: { value: harness.can.request },
      decideBranchTransfer: { value: harness.can.decide }
    }
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/branch-transfers/BranchTransfersPage.vue'

const BRANCHES = [
  { id: 1, code: 'SC-LIM-01', name: 'Sede Central Lima', is_active: true },
  { id: 2, code: 'SC-AQP-01', name: 'Sucursal Arequipa', is_active: true },
  { id: 3, code: 'SC-LIM-02', name: 'Sucursal San Isidro', is_active: true }
]

const transfer = overrides => ({
  id: 11,
  user_id: 7,
  from_branch_id: 1,
  to_branch_id: 2,
  status: 'pending',
  reason: 'Vivo más cerca de Arequipa.',
  requested_at: '2026-10-20T09:00:00.000000Z',
  decided_at: null,
  decided_by: null,
  decision_notes: null,
  user: { id: 7, name: 'Dra. Elsa Pérez' },
  from_branch: { id: 1, name: 'Sede Central Lima' },
  to_branch: { id: 2, name: 'Sucursal Arequipa' },
  decider: null,
  ...overrides
})

// Two rows belong to the actor (one pending, one decided) and two are requests
// from other professionals touching the same sedes: that is the visible scope a
// sede administrator reads and gerencia decides.
const TRANSFERS = () => [
  transfer({ id: 11, status: 'pending' }),
  transfer({
    id: 12,
    status: 'approved',
    to_branch_id: 3,
    decided_at: '2026-10-21T15:30:00.000000Z',
    decided_by: 1,
    decision_notes: 'Bienvenida a San Isidro.',
    to_branch: { id: 3, name: 'Sucursal San Isidro' },
    decider: { id: 1, name: 'Ana Admin' }
  }),
  transfer({
    id: 21,
    user_id: 8,
    user: { id: 8, name: 'Dr. Hugo Salas' },
    reason: 'Cobertura en la sede sur.'
  }),
  transfer({
    id: 22,
    user_id: 9,
    user: { id: 9, name: 'Dra. Rosa Vega' },
    reason: 'Cambio de residencia.'
  })
]

// A professional: asks, reads only their own rows, decides nothing.
const asProfessional = () => {
  harness.user = { id: 7, name: 'Dra. Elsa Pérez', role: 'odontologo', branch_id: 1 }
  harness.can = { request: true, decide: false }
}

// A sede administrator: holds `decide.branch_transfer` but not `branch.global`,
// so the API answers 403 for the decision doors (D6).
const asSedeAdministrator = () => {
  harness.user = { id: 5, name: 'Ana Admin Sede', role: 'administrador', branch_id: 1 }
  harness.can = { request: true, decide: true }
}

// Gerencia: group scope (no sede of its own) and the decision grant.
const asGerencia = () => {
  harness.user = { id: 1, name: 'Ana Admin', role: 'administrador', branch_id: null }
  harness.can = { request: true, decide: true }
}

const mockApi = ({ transfers = TRANSFERS(), branches = BRANCHES } = {}) => {
  harness.get.mockImplementation(async url => {
    const target = String(url)
    if (target.startsWith('/api/branches/active')) {
      return { data: branches, meta: { message: 'Sucursales cargadas exitosamente' } }
    }
    if (target.startsWith('/api/branch-transfers')) {
      return {
        data: transfers,
        meta: { current_page: 1, last_page: 1, per_page: 15, total: transfers.length }
      }
    }
    return { data: [] }
  })
}

const makeRouter = () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/branch-transfers', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  return router
}

const mountPage = async options => {
  mockApi(options)
  const router = makeRouter()
  await router.push('/branch-transfers')
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const transferFetches = () =>
  harness.get.mock.calls.filter(([url]) => String(url).startsWith('/api/branch-transfers')).length

const tableRows = wrapper => wrapper.findAll('[data-transfers-table] tbody tr')

const buttonByText = (scope, text) =>
  [...scope.querySelectorAll('button')].find(button => button.textContent.trim() === text)

const branchOptionLabels = async wrapper => {
  const select = wrapper.find('[data-transfer-branch]')
  expect(select.exists()).toBe(true)
  await select.find('button[aria-haspopup="true"]').trigger('click')
  const labels = select.findAll('[role="listbox"] button').map(button => button.text().trim())
  await select.find('button[aria-haspopup="true"]').trigger('click')
  return labels
}

const chooseBranch = async (wrapper, label) => {
  const select = wrapper.find('[data-transfer-branch]')
  expect(select.exists()).toBe(true)
  await select.find('button[aria-haspopup="true"]').trigger('click')
  const option = select.findAll('button').find(button => button.text().trim() === label)
  expect(option, `branch option "${label}" must exist`).toBeTruthy()
  await option.trigger('click')
  await flushPromises()
}

beforeEach(() => {
  document.body.innerHTML = ''
  harness.get.mockReset()
  harness.post.mockReset()
  harness.post.mockImplementation(async (url, payload) => ({ data: { id: 1, url, payload } }))
  harness.toast.success.mockReset()
  harness.toast.error.mockReset()
  harness.toast.info.mockReset()
  harness.refreshSession.mockReset()
  asProfessional()

  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 7, name: 'Dra. Elsa Pérez', role: 'odontologo' })
  )
})

describe('branch transfers surface', () => {
  it('renders the hero band, the current sede and the request form', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-branch-transfers]').exists()).toBe(true)

    const hero = wrapper.find('[data-branch-transfers-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('[data-branch-transfers-title]').text()).toBe('Traslados de Sede')

    // The session carries the affiliation, so the card names the sede instead
    // of falling back to group scope.
    expect(wrapper.find('[data-branch-current]').text()).toContain('Sede Central Lima')

    expect(wrapper.find('[data-transfer-form]').exists()).toBe(true)
    expect(wrapper.find('[data-transfer-branch]').exists()).toBe(true)
    expect(wrapper.find('[data-transfer-reason]').element.tagName).toBe('TEXTAREA')
    // The guard: no branch and no reason yet, so the submit stays disabled.
    expect(wrapper.find('[data-transfer-submit]').attributes('disabled')).toBeDefined()
    // A professional reads the queue but decides nothing.
    expect(wrapper.find('[data-transfers-decisions]').exists()).toBe(false)

    // The page owns its visible title, so the shell keeps its heading for the
    // outline only, and the sidebar entry opens this surface.
    const topbar = wrapper.find('[data-app-chrome="topbar"]')
    expect(topbar.attributes('data-topbar-title-owner')).toBe('page')
    const navEntry = wrapper
      .findAll('[data-app-chrome="sidebar"] a')
      .find(link => link.text().includes('Traslados de Sede'))
    expect(navEntry, 'the sidebar must link to the new surface').toBeTruthy()
    expect(navEntry.attributes('href')).toBe('/branch-transfers')

    wrapper.unmount()
  })

  it('reports group scope when the actor has no sede of its own', async () => {
    asGerencia()
    const wrapper = await mountPage()

    expect(wrapper.find('[data-branch-current]').text()).toContain('Alcance de grupo')
    expect(wrapper.find('[data-branch-current]').text()).not.toContain('Sede Central Lima')

    wrapper.unmount()
  })

  it('refreshes a session stored before the affiliation was published', async () => {
    // A session from before #305 carries permissions but no branch_id. The page
    // asks for /api/auth/me once instead of reading the unknown as group scope.
    harness.user = { id: 7, name: 'Dra. Elsa Pérez', role: 'odontologo' }

    const wrapper = await mountPage()

    expect(harness.refreshSession).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })

  it('does not refresh a session that already carries the affiliation', async () => {
    const wrapper = await mountPage()

    expect(harness.refreshSession).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('renders every row the API scoped, with the applicant of each one', async () => {
    asSedeAdministrator()
    const wrapper = await mountPage()

    // The table is not filtered in memory: the four visible rows are the same
    // four the API returned, including the ones opened by other professionals.
    expect(tableRows(wrapper)).toHaveLength(4)

    const incoming = tableRows(wrapper).find(row => row.text().includes('Dr. Hugo Salas'))
    expect(incoming).toBeTruthy()
    expect(incoming.find('[data-transfer-applicant]').text()).toContain('Dr. Hugo Salas')
    expect(incoming.text()).toContain('Cobertura en la sede sur.')
    expect(tableRows(wrapper).find(row => row.text().includes('Dra. Rosa Vega'))).toBeTruthy()
    expect(wrapper.find('[data-transfers-section-title]').text().toLowerCase()).toContain(
      'solicitudes'
    )

    // Somebody else's row is not the actor's to withdraw.
    expect(incoming.find('[data-transfer-cancel]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('labels a group-scope origin as no sede', async () => {
    // D1: a user with group scope asks without an origin sede, so the row has a
    // null `from_branch` and the route must say so instead of inventing one.
    asSedeAdministrator()
    const wrapper = await mountPage({
      transfers: [
        transfer({
          id: 31,
          user_id: 8,
          user: { id: 8, name: 'Dr. Hugo Salas' },
          from_branch_id: null,
          from_branch: null
        })
      ]
    })

    const route = wrapper.find('[data-transfer-route]')
    expect(route.text()).toContain('Sin sede')
    expect(route.text()).toContain('Sucursal Arequipa')

    wrapper.unmount()
  })

  it('does not offer decisions to a sede administrator', async () => {
    asSedeAdministrator()
    const wrapper = await mountPage()

    expect(wrapper.find('[data-transfers-decisions]').exists()).toBe(false)
    expect(wrapper.find('[data-decision-approve]').exists()).toBe(false)
    expect(wrapper.find('[data-decision-reject]').exists()).toBe(false)
    // The rows are still readable.
    expect(tableRows(wrapper)).toHaveLength(4)

    wrapper.unmount()
  })

  it('offers pending decisions to gerencia', async () => {
    asGerencia()
    const wrapper = await mountPage()

    expect(wrapper.find('[data-transfers-decisions]').exists()).toBe(true)
    expect(wrapper.find('[data-decision-approve="21"]').exists()).toBe(true)
    expect(wrapper.find('[data-decision-reject="22"]').exists()).toBe(true)
    // Group scope reads every row.
    expect(tableRows(wrapper)).toHaveLength(4)

    wrapper.unmount()
  })

  it('posts the request and reloads the list', async () => {
    const wrapper = await mountPage()
    expect(transferFetches()).toBe(1)

    await chooseBranch(wrapper, 'Sucursal Arequipa')
    await wrapper.find('[data-transfer-reason]').setValue('Vivo más cerca de Arequipa.')
    expect(wrapper.find('[data-transfer-submit]').attributes('disabled')).toBeUndefined()

    await wrapper.find('[data-transfer-form]').trigger('submit')
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/branch-transfers', {
      to_branch_id: 2,
      reason: 'Vivo más cerca de Arequipa.'
    })
    expect(harness.toast.success).toHaveBeenCalled()
    expect(transferFetches()).toBe(2)
    expect(wrapper.find('[data-transfer-reason]').element.value).toBe('')
    expect(wrapper.find('[data-transfer-submit]').attributes('disabled')).toBeDefined()

    wrapper.unmount()
  })

  it('keeps the current sede out of the destinations', async () => {
    const wrapper = await mountPage()

    const labels = await branchOptionLabels(wrapper)
    expect(labels).toEqual(['Sucursal Arequipa', 'Sucursal San Isidro'])
    expect(labels).not.toContain('Sede Central Lima')

    wrapper.unmount()
  })

  it('lets group scope ask for any active sede', async () => {
    asGerencia()
    const wrapper = await mountPage()

    expect(await branchOptionLabels(wrapper)).toEqual([
      'Sede Central Lima',
      'Sucursal Arequipa',
      'Sucursal San Isidro'
    ])

    wrapper.unmount()
  })

  it('surfaces a 409 from the API on the form instead of crashing', async () => {
    const wrapper = await mountPage()

    harness.post.mockRejectedValueOnce({
      response: { data: { message: 'Ya tienes una solicitud de traslado pendiente.' } },
      status: 409
    })

    await chooseBranch(wrapper, 'Sucursal Arequipa')
    await wrapper.find('[data-transfer-reason]').setValue('Segundo intento.')
    await wrapper.find('[data-transfer-form]').trigger('submit')
    await flushPromises()

    const formError = wrapper.find('[data-transfer-form-error]')
    expect(formError.exists()).toBe(true)
    expect(formError.text()).toContain('Ya tienes una solicitud de traslado pendiente.')
    // Nothing was reloaded and the table is still mounted.
    expect(transferFetches()).toBe(1)
    expect(wrapper.find('[data-transfers-table]').exists()).toBe(true)
    expect(wrapper.find('[data-branch-transfers-hero]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('offers cancel on an own pending row and posts the cancel after confirming', async () => {
    const wrapper = await mountPage()

    const cancelButtons = wrapper.findAll('[data-transfer-cancel]')
    expect(cancelButtons).toHaveLength(1)
    expect(cancelButtons[0].attributes('data-transfer-cancel')).toBe('11')

    await cancelButtons[0].trigger('click')
    await flushPromises()

    const confirm = buttonByText(document.body, 'Cancelar solicitud')
    expect(confirm).toBeTruthy()
    confirm.click()
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/branch-transfers/11/cancel', {})
    expect(harness.toast.success).toHaveBeenCalled()
    expect(transferFetches()).toBe(2)

    wrapper.unmount()
  })

  it('does not offer cancel on a decided row', async () => {
    const wrapper = await mountPage()

    expect(tableRows(wrapper)).toHaveLength(4)
    const approved = tableRows(wrapper).find(row => row.text().includes('Aprobada'))
    expect(approved, 'the decided row must stay readable').toBeTruthy()
    expect(approved.find('[data-transfer-cancel]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('approves and rejects a pending request with the notes', async () => {
    asGerencia()
    const wrapper = await mountPage()

    expect(wrapper.find('[data-transfers-decisions]').exists()).toBe(true)

    await wrapper.find('[data-decision-notes="21"] input').setValue('Bienvenido a la sede sur.')
    await wrapper.find('[data-decision-approve="21"]').trigger('click')
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/branch-transfers/21/approve', {
      decision_notes: 'Bienvenido a la sede sur.'
    })
    expect(harness.toast.success).toHaveBeenCalled()

    await wrapper.find('[data-decision-notes="22"] input').setValue('Sin cobertura aún.')
    await wrapper.find('[data-decision-reject="22"]').trigger('click')
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/branch-transfers/22/reject', {
      decision_notes: 'Sin cobertura aún.'
    })

    wrapper.unmount()
  })

  it('surfaces a refused decision inline on the row', async () => {
    asGerencia()
    const wrapper = await mountPage()

    harness.post.mockRejectedValueOnce({
      response: {
        data: { message: 'La sede de origen de la solicitud ya no coincide con su afiliación.' }
      },
      status: 409
    })

    await wrapper.find('[data-decision-approve="21"]').trigger('click')
    await flushPromises()

    const inlineError = wrapper.find('[data-decision-error="21"]')
    expect(inlineError.exists()).toBe(true)
    expect(inlineError.text()).toContain('La sede de origen de la solicitud ya no coincide')
    expect(wrapper.find('[data-transfers-decisions]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('shows the retry surface when the load fails and recovers on retry', async () => {
    harness.get.mockRejectedValueOnce(new Error('network down'))
    const wrapper = await mountPage()

    expect(wrapper.find('[data-transfers-error]').exists()).toBe(true)
    expect(wrapper.find('[data-transfers-table]').exists()).toBe(false)

    await wrapper.find('[data-transfers-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-transfers-error]').exists()).toBe(false)
    expect(tableRows(wrapper)).toHaveLength(4)

    wrapper.unmount()
  })

  it('renders the empty states when there is nothing to show', async () => {
    asGerencia()
    const wrapper = await mountPage({ transfers: [] })

    const empty = wrapper.find('[data-transfers-empty]')
    expect(empty.exists()).toBe(true)
    // The copy describes the scope of the list, not ownership.
    expect(empty.text()).toContain('No hay solicitudes de traslado')
    expect(wrapper.find('[data-transfers-decisions-empty]').exists()).toBe(true)
    expect(wrapper.find('[data-transfers-table]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('hides the request form when the permission is missing', async () => {
    harness.can.request = false

    const wrapper = await mountPage()

    expect(wrapper.find('[data-transfer-form]').exists()).toBe(false)
    // The visible scope of the actor is still readable.
    expect(wrapper.find('[data-transfers-table]').exists()).toBe(true)

    wrapper.unmount()
  })
})
