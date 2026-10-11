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
import Page from '../../../resources/js/modules/environments/EnvironmentsPage.vue'

const rowActive = {
  id: 1,
  code: 'AMB-01',
  name: 'Consultorio Norte',
  description: 'Box 1',
  equipment: 'Unidad dental',
  status: 'active'
}
const rowMaintenance = {
  id: 2,
  code: 'AMB-02',
  name: 'Sala de Esterilizacion',
  description: 'Area de autoclave',
  equipment: 'Autoclave',
  status: 'maintenance'
}
const rowInactive = {
  id: 3,
  code: 'AMB-03',
  name: 'Recepcion',
  description: null,
  equipment: null,
  status: 'inactive'
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

const selectFilterOption = async (wrapper, label) => {
  await wrapper.find('button[aria-haspopup="true"]').trigger('click')
  const option = wrapper
    .findAll('[role="listbox"] button')
    .find(candidate => candidate.text().trim() === label)
  expect(option, `filter option "${label}" must exist`).toBeTruthy()
  await option.trigger('click')
}

const clickRowAction = async (wrapper, label) => {
  const button = wrapper.findAll('button').find(candidate => candidate.text().trim() === label)
  expect(button, `row action "${label}" must exist`).toBeTruthy()
  await button.trigger('click')
}

const findConfirmDialog = () => document.body.querySelector('[role="alertdialog"]')

beforeEach(() => {
  vi.clearAllMocks()
  getMock.mockResolvedValue({ data: [] })
})

describe('environments smoke', () => {
  it('mounts EnvironmentsPage.vue and renders content', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('Ambientes')
    wrapper.unmount()
  })
})

describe('environments status filter (T2)', () => {
  it('requests the filtered list and hides non-matching rows when a status is selected', async () => {
    getMock.mockResolvedValue({ data: [rowActive, rowMaintenance, rowInactive] })
    const wrapper = await mountPage()

    expect(getMock).toHaveBeenCalledWith('/api/dental-chairs')
    expect(wrapper.text()).toContain('Consultorio Norte')

    await selectFilterOption(wrapper, 'Mantenimiento')
    await flushPromises()

    expect(getMock).toHaveBeenCalledWith('/api/dental-chairs?status=maintenance')
    expect(wrapper.text()).toContain('Sala de Esterilizacion')
    expect(wrapper.text()).not.toContain('Consultorio Norte')
    expect(wrapper.text()).not.toContain('Recepcion')
    wrapper.unmount()
  })

  it('omits the status param entirely for "Todos los estados"', async () => {
    getMock.mockResolvedValue({ data: [rowActive, rowMaintenance, rowInactive] })
    const wrapper = await mountPage()

    await selectFilterOption(wrapper, 'Mantenimiento')
    await flushPromises()
    await selectFilterOption(wrapper, 'Todos los estados')
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/dental-chairs')
    expect(wrapper.text()).toContain('Consultorio Norte')
    expect(wrapper.text()).toContain('Recepcion')
    wrapper.unmount()
  })

  it('filters search results client-side without a server-side status refetch', async () => {
    getMock.mockResolvedValue({ data: [rowActive, rowMaintenance, rowInactive] })
    const wrapper = await mountPage()

    await wrapper.find('input').setValue('consult')
    await flushPromises()

    expect(getMock).toHaveBeenCalledWith('/api/dental-chairs/search?q=consult')
    const callsAfterSearch = getMock.mock.calls.length

    await selectFilterOption(wrapper, 'Mantenimiento')
    await flushPromises()

    expect(getMock.mock.calls.length).toBe(callsAfterSearch)
    expect(wrapper.text()).toContain('Sala de Esterilizacion')
    expect(wrapper.text()).not.toContain('Consultorio Norte')
    wrapper.unmount()
  })
})

describe('environments error state (T2)', () => {
  it('shows the error state with retry and recovers on Reintentar', async () => {
    getMock
      .mockRejectedValueOnce(new Error('network down'))
      .mockResolvedValue({ data: [rowActive] })
    const wrapper = await mountPage()

    expect(wrapper.find('[data-environments-error]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No pudimos cargar los ambientes')
    expect(wrapper.text()).not.toContain('Consultorio Norte')

    await clickRowAction(wrapper, 'Reintentar')
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/dental-chairs')
    expect(wrapper.find('[data-environments-error]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Consultorio Norte')
    wrapper.unmount()
  })
})

describe('environments delete flow (T2)', () => {
  it('deletes through useConfirm only after the dialog is confirmed', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    deleteMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await clickRowAction(wrapper, 'Eliminar')
    await flushPromises()

    const dialog = findConfirmDialog()
    expect(dialog, 'useConfirm dialog must open').toBeTruthy()
    expect(dialog.textContent).toContain('Eliminar ambiente')

    const confirmButton = [...dialog.querySelectorAll('button')].find(
      button => button.textContent.trim() === 'Eliminar'
    )
    confirmButton.click()
    await flushPromises()

    expect(deleteMock).toHaveBeenCalledWith('/api/dental-chairs/1')
    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/dental-chairs')
    wrapper.unmount()
  })

  it('does not delete when the dialog is cancelled', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    deleteMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await clickRowAction(wrapper, 'Eliminar')
    await flushPromises()

    const dialog = findConfirmDialog()
    expect(dialog, 'useConfirm dialog must open').toBeTruthy()

    const cancelButton = [...dialog.querySelectorAll('button')].find(
      button => button.textContent.trim() === 'Cancelar'
    )
    cancelButton.click()
    await flushPromises()

    expect(deleteMock).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
