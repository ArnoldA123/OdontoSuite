import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
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
import { useToast } from '../../../resources/js/composables/useToast'
import { useConfirm } from '../../../resources/js/composables/useConfirm'
import Page from '../../../resources/js/modules/environments/EnvironmentsPage.vue'

const rowActive = {
  id: 1,
  code: 'AMB-01',
  name: 'Consultorio Norte',
  description: 'Box 1',
  equipment: 'Unidad dental',
  status: 'active',
  is_active: true
}
const rowMaintenance = {
  id: 2,
  code: 'AMB-02',
  name: 'Sala de Esterilizacion',
  description: 'Area de autoclave',
  equipment: 'Autoclave',
  status: 'maintenance',
  is_active: true
}
const rowInactive = {
  id: 3,
  code: 'AMB-03',
  name: 'Recepcion',
  description: null,
  equipment: null,
  status: 'inactive',
  is_active: true
}

const rowArchived = {
  id: 4,
  code: 'AMB-04',
  name: 'Consultorio Historico',
  description: 'Archivado',
  equipment: 'Unidad dental',
  status: 'maintenance',
  is_active: false
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

const selectFilterOption = async (wrapper, label, index = 0) => {
  await wrapper.findAll('button[aria-haspopup="true"]')[index].trigger('click')
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

beforeEach(async () => {
  // Close any teleported dialog left by the previous test before its host
  // container is discarded; otherwise the teleport anchors are stale.
  useConfirm().handleCancel()
  await flushPromises()
  vi.clearAllMocks()
  document.body.innerHTML = ''
  getMock.mockResolvedValue({ data: [] })
})

afterEach(() => {
  vi.useRealTimers()
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

    vi.useFakeTimers()
    try {
      await wrapper.find('input').setValue('consult')
      await vi.advanceTimersByTimeAsync(300)
    } finally {
      vi.useRealTimers()
    }
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

  it('surfaces the 409 explanation when the environment still has scheduling history', async () => {
    const toast = useToast()
    toast.clearAll()
    getMock.mockResolvedValue({ data: [rowActive] })
    deleteMock.mockRejectedValue({
      status: 409,
      response: {
        data: {
          message: 'No se puede eliminar el ambiente porque tiene citas o bloqueos asociados.'
        }
      }
    })
    const wrapper = await mountPage()

    await clickRowAction(wrapper, 'Eliminar')
    await flushPromises()

    const dialog = findConfirmDialog()
    expect(dialog, 'useConfirm dialog must open').toBeTruthy()
    const confirmButton = [...dialog.querySelectorAll('button')].find(
      button => button.textContent.trim() === 'Eliminar'
    )
    confirmButton.click()
    await flushPromises()

    expect(deleteMock).toHaveBeenCalledWith('/api/dental-chairs/1')
    expect(wrapper.text()).toContain(
      'No se puede eliminar el ambiente porque tiene citas o bloqueos asociados.'
    )
    wrapper.unmount()
    toast.clearAll()
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
      status: 'active',
      is_active: true
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

describe('environments T5 polish (edit modal + list chrome)', () => {
  it('edits equipment end-to-end through the edit modal (F2)', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    putMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await clickRowAction(wrapper, 'Editar')

    const form = findModalForm('edit')
    expect(form, 'edit modal form must render').toBeTruthy()
    const equipmentField = form.querySelector('[data-field="equipment"] textarea')
    expect(equipmentField, 'edit modal must expose the equipment field').toBeTruthy()

    setFieldValue(equipmentField, 'Unidad dental + aspiracion')
    findModalAction('edit', 'submit').click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledWith('/api/dental-chairs/1', {
      id: 1,
      code: 'AMB-01',
      name: 'Consultorio Norte',
      description: 'Box 1',
      equipment: 'Unidad dental + aspiracion',
      status: 'active',
      is_active: true
    })
    expect(findModalForm('edit')).toBeNull()
    wrapper.unmount()
  })

  it('pins the read-only auto-generated code field and the single framed table surface (F3 + F5)', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    const wrapper = await mountPage()

    // F5 (render): the table sits in exactly one framed surface — the
    // DataTable primitive container — not nested inside a page-level card.
    const tableRoot = wrapper.find('[data-environments-table]')
    expect(tableRoot.exists()).toBe(true)
    expect(tableRoot.element.closest('[data-variant]')).toBeNull()

    // F3 (render): the Código input is read-only and carries the hint.
    await clickRowAction(wrapper, 'Editar')
    const form = findModalForm('edit')
    expect(form, 'edit modal form must render').toBeTruthy()
    const codeInput = form.querySelector('[data-field="code"] input')
    expect(codeInput.readOnly).toBe(true)
    expect(form.querySelector('[data-field="code"]').textContent).toContain(
      'Generado automáticamente'
    )
    wrapper.unmount()

    const source = pageSource()

    // F5 (source): the only remaining UiCard is the filter bar; the list
    // section itself carries no card chrome around the DataTable.
    expect((source.match(/<UiCard\b/g) || []).length).toBe(1)
    const listSection = source.slice(
      source.indexOf('<!-- Environments List -->'),
      source.indexOf('data-environments-table')
    )
    expect(listSection).not.toContain('<UiCard')

    // F3 (source): the code field keeps the pinned v-model binding but is
    // read-only with the auto-generated hint.
    const codeFieldStart = source.indexOf('data-field="code"')
    const codeField = source.slice(codeFieldStart, source.indexOf('</div>', codeFieldStart))
    expect(codeField).toContain('readonly')
    expect(codeField).toContain('hint="Generado automáticamente"')
  })
})

describe('environments search debounce (S6)', () => {
  const deferredSearch = () => {
    const pendingSearches = []
    getMock.mockImplementation(url => {
      if (url === '/api/dental-chairs') return Promise.resolve({ data: [rowActive] })
      return new Promise(resolve => pendingSearches.push({ url, resolve }))
    })
    return pendingSearches
  }

  it('issues a single search request 300 ms after the last keystroke', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    const wrapper = await mountPage()
    getMock.mockClear()

    vi.useFakeTimers()
    try {
      const input = wrapper.find('input')
      await input.setValue('con')
      await input.setValue('cons')
      await input.setValue('consult')

      expect(getMock).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(299)
      expect(getMock).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(1)
      expect(getMock).toHaveBeenCalledTimes(1)
      expect(getMock).toHaveBeenCalledWith('/api/dental-chairs/search?q=consult')
    } finally {
      vi.useRealTimers()
    }

    await flushPromises()
    expect(wrapper.text()).toContain('Consultorio Norte')
    wrapper.unmount()
  })

  it('loads the unfiltered list immediately for a short query and cancels the pending search', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    const wrapper = await mountPage()
    getMock.mockClear()

    vi.useFakeTimers()
    try {
      await wrapper.find('input').setValue('consult')
      await wrapper.find('input').setValue('c')

      expect(getMock).toHaveBeenCalledWith('/api/dental-chairs')
      const callsAfterShortQuery = getMock.mock.calls.length

      await vi.advanceTimersByTimeAsync(500)
      expect(getMock.mock.calls.length).toBe(callsAfterShortQuery)
      expect(getMock).not.toHaveBeenCalledWith(expect.stringContaining('/search'))
    } finally {
      vi.useRealTimers()
    }

    await flushPromises()
    wrapper.unmount()
  })

  it('never lets a superseded search response overwrite the newest one', async () => {
    const pendingSearches = deferredSearch()
    const wrapper = await mountPage()

    vi.useFakeTimers()
    try {
      await wrapper.find('input').setValue('consult')
      await vi.advanceTimersByTimeAsync(300)
      await wrapper.find('input').setValue('recepcion')
      await vi.advanceTimersByTimeAsync(300)
    } finally {
      vi.useRealTimers()
    }

    expect(pendingSearches.map(search => search.url)).toEqual([
      '/api/dental-chairs/search?q=consult',
      '/api/dental-chairs/search?q=recepcion'
    ])

    pendingSearches[1].resolve({ data: [rowInactive] })
    await flushPromises()
    expect(wrapper.text()).toContain('Recepcion')

    pendingSearches[0].resolve({ data: [rowActive] })
    await flushPromises()
    expect(wrapper.text()).toContain('Recepcion')
    expect(wrapper.text()).not.toContain('Consultorio Norte')
    wrapper.unmount()
  })
})

describe('environments error messages (S6)', () => {
  const errorBlock = wrapper => wrapper.find('[data-environments-error]')

  it('shows the permission message for 403 responses read from error.status', async () => {
    getMock.mockRejectedValueOnce({ status: 403, response: { data: { message: 'Forbidden' } } })
    const wrapper = await mountPage()

    expect(errorBlock(wrapper).exists()).toBe(true)
    expect(errorBlock(wrapper).text()).toContain('No tienes permisos para ver los ambientes.')
    expect(errorBlock(wrapper).text()).not.toContain('Revisa tu conexión y vuelve a intentarlo.')
    wrapper.unmount()
  })

  it('shows the server message for 500 responses read from error.response.status', async () => {
    getMock.mockRejectedValueOnce({ response: { status: 500, data: { message: 'Boom' } } })
    const wrapper = await mountPage()

    expect(errorBlock(wrapper).exists()).toBe(true)
    expect(errorBlock(wrapper).text()).toContain(
      'Ocurrió un error en el servidor. Intenta nuevamente.'
    )
    expect(errorBlock(wrapper).text()).not.toContain('Revisa tu conexión y vuelve a intentarlo.')
    wrapper.unmount()
  })

  it('keeps the connection message when the failure carries no status', async () => {
    getMock.mockRejectedValueOnce(new Error('network down'))
    const wrapper = await mountPage()

    expect(errorBlock(wrapper).exists()).toBe(true)
    expect(errorBlock(wrapper).text()).toContain('Revisa tu conexión y vuelve a intentarlo.')
    expect(errorBlock(wrapper).text()).not.toContain(
      'Ocurrió un error en el servidor. Intenta nuevamente.'
    )
    expect(errorBlock(wrapper).text()).not.toContain('No tienes permisos para ver los ambientes.')
    wrapper.unmount()
  })

  it('keeps the connection message for a status 0 failure', async () => {
    getMock.mockRejectedValueOnce({ status: 0, response: { data: {} } })
    const wrapper = await mountPage()

    expect(errorBlock(wrapper).exists()).toBe(true)
    expect(errorBlock(wrapper).text()).toContain('Revisa tu conexión y vuelve a intentarlo.')
    expect(errorBlock(wrapper).text()).not.toContain(
      'Ocurrió un error en el servidor. Intenta nuevamente.'
    )
    wrapper.unmount()
  })
})

describe('environments archive management (S9)', () => {
  it('requests only archived environments and offers reactivation', async () => {
    getMock.mockResolvedValue({ data: [rowArchived] })
    const wrapper = await mountPage()

    await selectFilterOption(wrapper, 'Solo archivados', 1)
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/dental-chairs?archived=only')
    expect(wrapper.text()).toContain('Consultorio Historico')
    expect(wrapper.text()).toContain('Archivado')
    expect(wrapper.text()).toContain('Reactivar')
    expect(wrapper.text()).not.toContain('Archivar')
    wrapper.unmount()
  })

  it('carries the archive visibility into search requests', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    const wrapper = await mountPage()

    vi.useFakeTimers()
    try {
      await wrapper.find('input').setValue('consult')
      await vi.advanceTimersByTimeAsync(300)
    } finally {
      vi.useRealTimers()
    }
    await flushPromises()
    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/dental-chairs/search?q=consult')

    await selectFilterOption(wrapper, 'Incluir archivados', 1)
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe(
      '/api/dental-chairs/search?q=consult&archived=include'
    )
    wrapper.unmount()
  })

  it('archives an environment after confirmation', async () => {
    getMock.mockResolvedValue({ data: [rowActive] })
    putMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()
    await clickRowAction(wrapper, 'Archivar')
    await flushPromises()

    const dialog = findConfirmDialog()
    expect(dialog, 'archive confirmation must open').toBeTruthy()
    const confirmButton = [...dialog.querySelectorAll('button')].find(
      button => button.textContent.trim() === 'Archivar'
    )
    confirmButton.click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledWith('/api/dental-chairs/1', { is_active: false })
    expect(wrapper.text()).toContain('Ambiente archivado exitosamente')
    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/dental-chairs')
    wrapper.unmount()
  })

  it('reactivates an archived environment without a confirmation dialog', async () => {
    getMock.mockResolvedValue({ data: [rowArchived] })
    putMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await selectFilterOption(wrapper, 'Solo archivados', 1)
    await flushPromises()
    await clickRowAction(wrapper, 'Reactivar')
    await flushPromises()

    expect(findConfirmDialog()).toBeFalsy()
    expect(putMock).toHaveBeenCalledWith('/api/dental-chairs/4', { is_active: true })
    expect(wrapper.text()).toContain('Ambiente reactivado exitosamente')
    wrapper.unmount()
  })
})
