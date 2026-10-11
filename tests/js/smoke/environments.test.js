import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

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

let pageRouter = null

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  pageRouter = router
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

const pageSource = () =>
  readFileSync(
    resolve(process.cwd(), 'resources/js/modules/environments/EnvironmentsPage.vue'),
    'utf8'
  )

const findModalForm = kind => document.body.querySelector(`[data-environment-form="${kind}"]`)

const findModalAction = (kind, action) =>
  document.body.querySelector(`[data-modal-${action}="${kind}"]`)

const setFieldValue = (element, value) => {
  expect(element, 'modal field element must exist').toBeTruthy()
  element.value = value
  element.dispatchEvent(new Event('input', { bubbles: true }))
}

const openModalStatusOptions = async form => {
  form.querySelector('[data-field="status"] button[aria-haspopup="true"]').click()
  await flushPromises()
  return [...form.querySelectorAll('[role="listbox"] button')].map(option =>
    option.textContent.trim()
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
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

describe('environments modal surfaces (T3)', () => {
  it('drops the dead View modal and keeps "Ver detalle" as the single path to the detail route', async () => {
    const source = pageSource()
    expect(source).not.toMatch(/\bviewEnvironment\b/)
    expect(source).not.toContain('viewingEnvironment')
    expect(source).not.toContain('showViewEnvironmentModal')
    expect(source).not.toContain('Ver Ambiente')

    getMock.mockResolvedValue({ data: [rowActive] })
    const wrapper = await mountPage()

    await clickRowAction(wrapper, 'Ver detalle')
    await flushPromises()

    expect(pageRouter.currentRoute.value.path).toBe('/environments/1')
    expect(findModalForm('create')).toBeNull()
    expect(findModalForm('edit')).toBeNull()
    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
    wrapper.unmount()
  })

  it('creates an environment through the grouped form with the same payload shape as before', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    postMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await clickRowAction(wrapper, 'Nuevo ambiente')

    const form = findModalForm('create')
    expect(form, 'create modal form must render').toBeTruthy()
    expect(form.textContent).toContain('Identificación')
    expect(form.textContent).toContain('Descripción')
    expect(form.textContent).toContain('Equipamiento')
    expect(form.textContent).toContain('Estado')
    expect(form.querySelector('[data-field="name"]').textContent).toContain('Nombre del ambiente')

    setFieldValue(form.querySelector('[data-field="name"] input'), 'Box 4')
    setFieldValue(form.querySelector('[data-field="description"] textarea'), 'Cuarto nuevo')
    setFieldValue(form.querySelector('[data-field="equipment"] textarea'), 'Unidad dental')

    const statusLabels = await openModalStatusOptions(form)
    expect(statusLabels).not.toContain('Todos los estados')
    const option = [...form.querySelectorAll('[role="listbox"] button')].find(
      candidate => candidate.textContent.trim() === 'Mantenimiento'
    )
    expect(option, 'modal status option "Mantenimiento" must exist').toBeTruthy()
    option.click()
    await flushPromises()

    findModalAction('create', 'submit').click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledWith('/api/dental-chairs', {
      name: 'Box 4',
      description: 'Cuarto nuevo',
      equipment: 'Unidad dental',
      status: 'maintenance'
    })
    expect(findModalForm('create')).toBeNull()
    wrapper.unmount()
  })

  it('prefills the edit form with code, name, description and status and saves the edited row', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    putMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await clickRowAction(wrapper, 'Editar')

    const form = findModalForm('edit')
    expect(form, 'edit modal form must render').toBeTruthy()
    expect(form.querySelector('[data-field="code"] input').value).toBe('AMB-01')
    expect(form.querySelector('[data-field="name"] input').value).toBe('Consultorio Norte')
    expect(form.querySelector('[data-field="description"] textarea').value).toBe('Box 1')
    expect(form.querySelector('[data-field="status"]').textContent).toContain('Activo')
    expect(form.textContent).toContain('Código')

    setFieldValue(form.querySelector('[data-field="name"] input'), 'Consultorio Sur')
    findModalAction('edit', 'submit').click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledWith('/api/dental-chairs/1', {
      id: 1,
      code: 'AMB-01',
      name: 'Consultorio Sur',
      description: 'Box 1',
      equipment: 'Unidad dental',
      status: 'active'
    })
    expect(findModalForm('edit')).toBeNull()
    wrapper.unmount()
  })

  it('locks the form and shows progress while the create request is running', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    let resolveCreate
    postMock.mockReturnValue(
      new Promise(resolve => {
        resolveCreate = resolve
      })
    )
    const wrapper = await mountPage()

    await clickRowAction(wrapper, 'Nuevo ambiente')
    const form = findModalForm('create')
    setFieldValue(form.querySelector('[data-field="name"] input'), 'Box 4')

    const submit = findModalAction('create', 'submit')
    const cancel = findModalAction('create', 'cancel')
    submit.click()
    await flushPromises()

    expect(submit.disabled).toBe(true)
    expect(submit.querySelector('.spinner')).toBeTruthy()
    expect(cancel.disabled).toBe(true)
    expect(form.querySelector('[data-field="name"] input').disabled).toBe(true)
    expect(form.querySelector('[data-field="description"] textarea').disabled).toBe(true)
    expect(form.querySelector('[data-field="status"] button[aria-haspopup="true"]').disabled).toBe(
      true
    )

    resolveCreate({ data: {} })
    await flushPromises()
    wrapper.unmount()
  })
})
