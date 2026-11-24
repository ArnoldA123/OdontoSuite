import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const { getMock, postMock, putMock, deleteMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  putMock: vi.fn(),
  deleteMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: putMock,
    patch: vi.fn(),
    delete: deleteMock,
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
import { useToast } from '../../../resources/js/composables/useToast'
import { useConfirm } from '../../../resources/js/composables/useConfirm'
import rolePermissions from '../support/role-permissions.json'
import Page from '../../../resources/js/modules/environments/EnvironmentsPage.vue'

/**
 * citas-privacidad (T4) — la sede del ambiente en la superficie de Ambientes.
 *
 * El contrato tiene dos mitades: el formulario (alta/edición) declara la sede y
 * el actor restringido la ve fijada a la suya, y la lista la publica por fila.
 * El alcance de grupo, además, gana el filtro por sede.
 *
 * La API es la autoridad: estos casos fijan lo que la página manda y lo que
 * pinta, nunca lo que el servidor decide.
 */

const trujillo = { id: 1, name: 'Trujillo', code: 'SC-TRU-01', is_active: true }
const lima = { id: 2, name: 'Lima central', code: 'SC-LIM-01', is_active: true }

const chairInTrujillo = {
  id: 11,
  code: 'AMB-001',
  name: 'Consultorio Norte',
  description: 'Box 1',
  equipment: 'Unidad dental',
  status: 'active',
  is_active: true,
  branch_id: 1,
  branch: { id: 1, name: 'Trujillo', code: 'SC-TRU-01' }
}

const chairInLima = {
  id: 12,
  code: 'AMB-004',
  name: 'Consultorio Central',
  description: 'Box 4',
  equipment: 'Unidad dental',
  status: 'active',
  is_active: true,
  branch_id: 2,
  branch: { id: 2, name: 'Lima central', code: 'SC-LIM-01' }
}

const seedSession = (role, extra = {}) => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 1, role, permissions: rolePermissions[role], ...extra })
  )
}

// Two endpoints answer on mount: the list and the active sede catalog. The
// restricted session only ever receives the sede the API scopes for it.
const mockGet = (rows, branches) => {
  getMock.mockImplementation(url => {
    if (String(url).startsWith('/api/branches')) return Promise.resolve({ data: branches })
    return Promise.resolve({ data: rows })
  })
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const rowAction = async (wrapper, label) => {
  const button = wrapper.findAll('button').find(candidate => candidate.text().trim() === label)
  expect(button, `row action "${label}" must exist`).toBeTruthy()
  await button.trigger('click')
  await flushPromises()
}

const modalForm = kind => document.body.querySelector(`[data-environment-form="${kind}"]`)

const modalAction = (kind, action) =>
  document.body.querySelector(`[data-modal-${action}="${kind}"]`)

const setFieldValue = (element, value) => {
  expect(element, 'modal field element must exist').toBeTruthy()
  element.value = value
  element.dispatchEvent(new Event('input', { bubbles: true }))
}

const branchSelect = kind => {
  const select = document.body.querySelector(`[data-branch-select="${kind}"]`)
  expect(select, `sede select "${kind}" must render`).toBeTruthy()
  return select
}

const chooseBranch = async (kind, label) => {
  branchSelect(kind).querySelector('button[aria-haspopup="true"]').click()
  await flushPromises()
  const option = [...branchSelect(kind).querySelectorAll('[role="listbox"] button')].find(
    candidate => candidate.textContent.trim() === label
  )
  expect(option, `sede option "${label}" must exist`).toBeTruthy()
  option.click()
  await flushPromises()
}

beforeEach(async () => {
  useConfirm().handleCancel()
  await flushPromises()
  vi.clearAllMocks()
  document.body.innerHTML = ''
  useToast().clearAll()
  seedSession('administrador')
  mockGet([], [trujillo, lima])
})

describe('environments sede in the list', () => {
  it('publishes the sede of every environment beside its name', async () => {
    mockGet([chairInTrujillo, chairInLima], [trujillo, lima])
    const wrapper = await mountPage()

    const cells = wrapper.findAll('[data-environment-branch]').map(cell => cell.text())

    expect(cells).toEqual(['Trujillo', 'Lima central'])
    // The column belongs to the shared table: same rows, new axis.
    expect(wrapper.find('[data-environments-table]').text()).toContain('Sede')
    wrapper.unmount()
  })

  it('falls back to the declared absence instead of rendering an empty slot', async () => {
    mockGet([{ ...chairInTrujillo, branch_id: null, branch: null }], [trujillo])
    const wrapper = await mountPage()

    expect(wrapper.find('[data-environment-branch]').text()).toBe('Sin sede')
    wrapper.unmount()
  })
})

describe('environments sede filter (group scope)', () => {
  it('offers every active sede and re-reads the list with the chosen branch', async () => {
    mockGet([chairInTrujillo, chairInLima], [trujillo, lima])
    const wrapper = await mountPage()

    expect(wrapper.find('[data-branch-filter]').text()).toContain('Todas las sedes')

    await wrapper.find('[data-branch-filter] button[aria-haspopup="true"]').trigger('click')
    const option = wrapper
      .findAll('[role="listbox"] button')
      .find(candidate => candidate.text().trim() === 'Lima central')
    expect(option, 'active sedes must be offered').toBeTruthy()
    await option.trigger('click')
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/dental-chairs?branch_id=2')
    expect(wrapper.find('[data-branch-filter]').text()).toContain('Lima central')
    wrapper.unmount()
  })

  it('carries the sede into the search request instead of filtering rows locally', async () => {
    mockGet([chairInTrujillo], [trujillo])
    const wrapper = await mountPage()

    await wrapper.find('[data-branch-filter] button[aria-haspopup="true"]').trigger('click')
    await wrapper
      .findAll('[role="listbox"] button')
      .find(candidate => candidate.text().trim() === 'Trujillo')
      .trigger('click')
    await flushPromises()

    vi.useFakeTimers()
    try {
      await wrapper.find('input').setValue('consult')
      await vi.advanceTimersByTimeAsync(300)
    } finally {
      vi.useRealTimers()
    }
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/dental-chairs/search?q=consult&branch_id=1')
    wrapper.unmount()
  })
})

describe('environments sede form (group scope)', () => {
  it('sends the sede chosen in the create form', async () => {
    mockGet([chairInTrujillo], [trujillo, lima])
    postMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await rowAction(wrapper, 'Nuevo ambiente')
    const form = modalForm('create')
    setFieldValue(form.querySelector('[data-field="name"] input'), 'Box 7')

    await chooseBranch('create', 'Lima central')
    modalAction('create', 'submit').click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledWith('/api/dental-chairs', {
      name: 'Box 7',
      description: '',
      equipment: '',
      status: 'active',
      branch_id: 2
    })
    wrapper.unmount()
  })

  it('renders the 422 of a missing sede inside the sede field', async () => {
    mockGet([chairInTrujillo], [trujillo, lima])
    postMock.mockRejectedValue({
      status: 422,
      response: {
        data: {
          message: 'Error de validación',
          errors: { branch_id: ['La sede es obligatoria.'] }
        }
      }
    })
    const wrapper = await mountPage()

    await rowAction(wrapper, 'Nuevo ambiente')
    const form = modalForm('create')
    setFieldValue(form.querySelector('[data-field="name"] input'), 'Box 7')
    modalAction('create', 'submit').click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledWith('/api/dental-chairs', {
      name: 'Box 7',
      description: '',
      equipment: '',
      status: 'active',
      branch_id: null
    })
    const stillOpen = modalForm('create')
    expect(stillOpen, 'the modal stays open after a 422').toBeTruthy()
    expect(stillOpen.querySelector('[data-field="branch"]').textContent).toContain(
      'La sede es obligatoria.'
    )
    wrapper.unmount()
  })
})

describe('environments sede form (restricted scope)', () => {
  it('hides the sede filter and locks the create field to the session sede', async () => {
    seedSession('administrador', { branch_id: 1 })
    mockGet([chairInTrujillo], [trujillo])
    postMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    // The API already scoped the read: no second, redundant filter for a
    // session that cannot name another sede.
    expect(wrapper.find('[data-branch-filter]').exists()).toBe(false)

    await rowAction(wrapper, 'Nuevo ambiente')
    const form = modalForm('create')
    const field = form.querySelector('[data-field="branch"]')

    expect(field.textContent).toContain('Trujillo')
    expect(field.querySelector('button[aria-haspopup="true"]').disabled).toBe(true)

    setFieldValue(form.querySelector('[data-field="name"] input'), 'Box 8')
    modalAction('create', 'submit').click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledWith('/api/dental-chairs', {
      name: 'Box 8',
      description: '',
      equipment: '',
      status: 'active',
      branch_id: 1
    })
    wrapper.unmount()
  })

  it('prefills the edit field with the row sede and keeps it locked', async () => {
    seedSession('administrador', { branch_id: 1 })
    mockGet([chairInTrujillo], [trujillo])
    putMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await rowAction(wrapper, 'Editar')
    const form = modalForm('edit')
    const field = form.querySelector('[data-field="branch"]')

    expect(field.textContent).toContain('Trujillo')
    expect(field.querySelector('button[aria-haspopup="true"]').disabled).toBe(true)

    modalAction('edit', 'submit').click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledWith('/api/dental-chairs/11', {
      name: 'Consultorio Norte',
      description: 'Box 1',
      equipment: 'Unidad dental',
      status: 'active',
      is_active: true,
      branch_id: 1
    })
    wrapper.unmount()
  })

  it('renders a 403 of another sede in the sede field instead of only as a toast', async () => {
    seedSession('administrador', { branch_id: 1 })
    mockGet([chairInTrujillo], [trujillo])
    const message = 'No puede operar sobre otra sede. Su acceso está limitado a la sede asignada.'
    putMock.mockRejectedValue({
      status: 403,
      response: { data: { message } }
    })
    const wrapper = await mountPage()

    await rowAction(wrapper, 'Editar')
    modalAction('edit', 'submit').click()
    await flushPromises()

    const stillOpen = modalForm('edit')
    expect(stillOpen, 'the modal stays open after a 403').toBeTruthy()
    expect(stillOpen.querySelector('[data-field="branch"]').textContent).toContain(message)
    expect(useToast().toasts.value.some(entry => entry.message === message)).toBe(false)
    wrapper.unmount()
  })
})
