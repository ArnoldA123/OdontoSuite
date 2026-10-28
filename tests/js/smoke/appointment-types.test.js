import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Appointment-types list redesign contract: hero band, KPI strip derived from
// the SAME full catalog the table renders, client-side status + search
// filtering over a single `status=all` load, the `default_duration_minutes`
// duration fix, the removed View modal (Ver Detalle navigates instead), the
// inline error surface with retry, the delete confirmation gate (composable
// dialog: cancel aborts without calling the API, confirm deletes), and a
// coherent color pair in both modals.
//
// #439: the write affordances follow `usePermissions()`; a receptionist session
// reads the whole surface with no write door.
// #443: `?edit=<id>` opens the shared form and the query replace that clears
// the parameter does not remount the page (the route opts out of the
// full-path key).
const { getMock, deleteMock, makeType } = vi.hoisted(() => {
  const makeType = (id, overrides = {}) => ({
    id,
    name: `Tipo ${id}`,
    description: `Descripción del tipo ${id}`,
    default_duration_minutes: 30,
    price: 100,
    color: 'steelblue',
    is_active: true,
    ...overrides
  })

  const getMock = vi.fn(async () => ({ data: [] }))
  const deleteMock = vi.fn(async () => ({ data: {} }))

  return { getMock, deleteMock, makeType }
})

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
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
import { readFileSync } from 'node:fs'
import { nextTick } from 'vue'
import Page from '../../../resources/js/modules/appointment-types/AppointmentTypesPage.vue'
import Modal from '../../../resources/js/components/ui/Modal.vue'
import Select from '../../../resources/js/components/ui/Select.vue'
import DataTable from '../../../resources/js/components/ui/DataTable.vue'
import rolePermissions from '../support/role-permissions.json'

// #439: `usePermissions()` is a membership check on the permission list the API
// publishes, so the suite seeds the session it works as.
const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const CATALOG = [
  makeType(1, {
    name: 'Limpieza dental',
    description: 'Profilaxis dental completa',
    default_duration_minutes: 30,
    is_active: true
  }),
  makeType(2, {
    name: 'Ortodoncia',
    description: 'Control mensual de brackets',
    default_duration_minutes: 60,
    is_active: true
  }),
  makeType(3, {
    name: 'Blanqueamiento',
    description: 'Blanqueamiento ambulatorio',
    default_duration_minutes: 45,
    is_active: false
  })
]

const mountPage = async (catalog = []) => {
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/appointment-types')) {
      return { data: catalog }
    }
    return { data: [] }
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const rows = wrapper => wrapper.findAll('[data-type-row]')

// The duration input of the OPEN modal, resolved through its floating label
// so the assertion targets the field itself, not any numeric-looking input.
const editDurationInput = () => {
  const label = [...document.body.querySelectorAll('label')].find(
    l => l.textContent.trim() === 'Duración (minutos)'
  )
  expect(label).toBeTruthy()
  const input = document.getElementById(label.getAttribute('for'))
  expect(input).toBeTruthy()
  return input
}

const pickStatusFilter = wrapper =>
  wrapper.findAllComponents(Select).find(c => c.attributes('data-status-filter') !== undefined)

const chooseStatusOption = async (wrapper, label) => {
  const select = pickStatusFilter(wrapper)
  expect(select).toBeTruthy()
  await select.find('button').trigger('click')
  const option = select.findAll('button').find(b => b.text().includes(label))
  expect(option).toBeTruthy()
  await option.trigger('click')
  await flushPromises()
}

// #443: the real route declares `meta.preserveOnQuery` (app.js) and the shell
// keys its router-view by `$route.path`, exactly like `App` does.
const mountShellAt = async (path, catalog = CATALOG) => {
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/appointment-types')) return { data: catalog }
    return { data: [] }
  })

  const shellRouter = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/appointment-types',
        name: 'appointment-types',
        component: Page,
        meta: { preserveOnQuery: true }
      },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await shellRouter.push(path)
  await shellRouter.isReady()

  const Shell = {
    template: '<router-view :key="$route.meta.preserveOnQuery ? $route.path : $route.fullPath" />'
  }
  const wrapper = mount(Shell, { global: { plugins: [shellRouter, uiComponents] } })
  await flushPromises()

  return { wrapper, shellRouter }
}

const openModalNameInput = name =>
  [...document.body.querySelectorAll('input')].find(input => input.value === name)

const closeOpenEditModal = async () => {
  const cancel = [...document.body.querySelectorAll('button')].find(
    b => b.textContent.trim() === 'Cancelar'
  )
  expect(cancel).toBeTruthy()
  cancel.click()
  await flushPromises()
}

describe('appointment-types smoke', () => {
  beforeEach(() => {
    seedSession('administrador')
    getMock.mockReset()
    getMock.mockImplementation(async () => ({ data: [] }))
    deleteMock.mockReset()
    deleteMock.mockImplementation(async () => ({ data: {} }))
  })

  it('mounts AppointmentTypesPage.vue and renders content', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()
    expect(wrapper.text()).toContain('Tipos de Cita')
    wrapper.unmount()
  })

  it('renders the hero band with eyebrow, headline, caption and primary action', async () => {
    const wrapper = await mountPage(CATALOG)
    const hero = wrapper.find('[data-appointment-types-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('[data-hero-title]').text()).toBe('Tipos de Cita')
    expect(hero.find('[data-hero-eyebrow]').text()).toBe('Catálogo')
    expect(hero.find('[data-hero-caption]').text().length).toBeGreaterThan(0)
    expect(hero.find('[data-hero-action]').text()).toContain('Nuevo tipo')
    wrapper.unmount()
  })

  it('derives the four KPI slots from the same full catalog', async () => {
    const wrapper = await mountPage(CATALOG)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('3')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('1')
    expect(wrapper.find('[data-kpi-value="avg-duration"]').text()).toBe('45 min')
    expect(wrapper.find('[data-kpi-caption="total"]').text()).toMatch(/en el catálogo/i)
    wrapper.unmount()
  })

  it('renders default_duration_minutes as the duration cell', async () => {
    const wrapper = await mountPage(CATALOG)
    const row = rows(wrapper).find(r => r.text().includes('Limpieza dental'))
    expect(row.text()).toContain('30 min')
    expect(row.text()).not.toContain('undefined')
    wrapper.unmount()
  })

  it('filters the loaded catalog client-side through the status filter', async () => {
    const wrapper = await mountPage(CATALOG)
    expect(rows(wrapper)).toHaveLength(3)

    await chooseStatusOption(wrapper, 'Inactivos')
    expect(rows(wrapper)).toHaveLength(1)
    expect(rows(wrapper)[0].text()).toContain('Blanqueamiento')
    expect(wrapper.find('[data-filter-count]').text()).toContain('Mostrando 1 de 3')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('3')

    await chooseStatusOption(wrapper, 'Activos')
    expect(rows(wrapper)).toHaveLength(2)
    await chooseStatusOption(wrapper, 'Todos')
    expect(rows(wrapper)).toHaveLength(3)
    wrapper.unmount()
  })

  it('filters the loaded catalog client-side through the search text', async () => {
    const wrapper = await mountPage(CATALOG)
    await wrapper.find('[data-type-search] input').setValue('ortodoncia')
    await flushPromises()
    expect(rows(wrapper)).toHaveLength(1)
    expect(rows(wrapper)[0].text()).toContain('Ortodoncia')
    wrapper.unmount()
  })

  it('shows a contextual empty state for the active filter', async () => {
    const wrapper = await mountPage([CATALOG[0], CATALOG[1]])
    await chooseStatusOption(wrapper, 'Inactivos')
    expect(rows(wrapper)).toHaveLength(0)
    expect(wrapper.text()).toContain('No hay tipos de cita inactivos')
    wrapper.unmount()
  })

  it('renders the inline error surface with retry when loading fails', async () => {
    getMock.mockImplementationOnce(async () => {
      throw new Error('network down')
    })
    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/appointment-types')) {
        return { data: CATALOG }
      }
      return { data: [] }
    })
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()
    expect(wrapper.find('[data-types-error]').exists()).toBe(true)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('N/D')

    await wrapper.find('[data-retry-types]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-types-error]').exists()).toBe(false)
    expect(rows(wrapper)).toHaveLength(3)
    wrapper.unmount()
  })

  it('keeps only the New and Edit modals (the View modal is gone)', async () => {
    const wrapper = await mountPage(CATALOG)
    const titles = wrapper.findAllComponents(Modal).map(m => m.props('title'))
    expect(titles).toContain('Nuevo Tipo de Cita')
    expect(titles).toContain('Editar Tipo de Cita')
    expect(titles).not.toContain('Ver Tipo de Cita')
    expect(titles.filter(t => typeof t === 'string' && t.includes('Tipo de Cita'))).toEqual([
      'Nuevo Tipo de Cita',
      'Editar Tipo de Cita'
    ])
    wrapper.unmount()
  })

  it('presents a coherent color pair (swatch + hex input) in both modals', async () => {
    const wrapper = await mountPage(CATALOG)

    const bodyColorPair = () => {
      expect(document.body.querySelectorAll('input[type="color"]')).toHaveLength(1)
      expect(document.body.textContent).toContain('Código hex')
    }

    await wrapper.find('[data-hero-action]').trigger('click')
    await flushPromises()
    bodyColorPair()

    const cancel = [...document.body.querySelectorAll('button')].find(
      b => b.textContent.trim() === 'Cancelar'
    )
    expect(cancel).toBeTruthy()
    cancel.click()
    await flushPromises()

    const editRow = rows(wrapper).find(r => r.text().includes('Limpieza dental'))
    await editRow.find('[data-row-action="edit"]').trigger('click')
    await flushPromises()
    bodyColorPair()
    wrapper.unmount()
  })

  it('opens the shared edit modal from the ?edit=<id> deep link and clears the query', async () => {
    await router.push({ path: '/appointment-types', query: { edit: '2' } })
    await router.isReady()
    const wrapper = await mountPage(CATALOG)

    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    const nameInput = [...document.body.querySelectorAll('input')].find(
      i => i.value === 'Ortodoncia'
    )
    expect(nameInput).toBeTruthy()
    expect(router.currentRoute.value.query.edit).toBeUndefined()
    wrapper.unmount()
    await router.push('/appointment-types')
  })

  it('pre-fills the edit duration input from default_duration_minutes (row action)', async () => {
    const wrapper = await mountPage(CATALOG)
    const editRow = rows(wrapper).find(r => r.text().includes('Blanqueamiento'))
    await editRow.find('[data-row-action="edit"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    expect(editDurationInput().value).toBe('45')
    wrapper.unmount()
  })

  it('pre-fills the edit duration input from default_duration_minutes (?edit deep link)', async () => {
    await router.push({ path: '/appointment-types', query: { edit: '2' } })
    await router.isReady()
    const wrapper = await mountPage(CATALOG)

    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    expect(editDurationInput().value).toBe('60')
    wrapper.unmount()
    await router.push('/appointment-types')
  })

  it('ignores an unknown ?edit id without opening the modal', async () => {
    await router.push({ path: '/appointment-types', query: { edit: '999' } })
    await router.isReady()
    const wrapper = await mountPage(CATALOG)

    expect(document.body.textContent).not.toContain('Editar Tipo de Cita')
    expect(router.currentRoute.value.query.edit).toBeUndefined()
    wrapper.unmount()
    await router.push('/appointment-types')
  })

  it('gates deletion on the confirm dialog and aborts when cancelled', async () => {
    const wrapper = await mountPage(CATALOG)
    const row = rows(wrapper).find(r => r.text().includes('Limpieza dental'))
    await row.find('[data-row-action="delete"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Eliminar tipo de cita')
    const cancel = [...document.body.querySelectorAll('button')].find(
      b => b.textContent.trim() === 'Cancelar'
    )
    expect(cancel).toBeTruthy()
    cancel.click()
    await flushPromises()

    expect(deleteMock).not.toHaveBeenCalled()
    expect(rows(wrapper).some(r => r.text().includes('Limpieza dental'))).toBe(true)
    wrapper.unmount()
  })

  it('offers the write affordances to a session holding manage.appointment_type', async () => {
    const wrapper = await mountPage(CATALOG)

    expect(wrapper.find('[data-hero-action]').exists()).toBe(true)
    const row = rows(wrapper).find(r => r.text().includes('Limpieza dental'))
    expect(row.find('[data-row-action="edit"]').exists()).toBe(true)
    expect(row.find('[data-row-action="delete"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('offers no write affordance to a session without manage.appointment_type', async () => {
    seedSession('recepcionista')
    const wrapper = await mountPage(CATALOG)

    expect(wrapper.find('[data-hero-action]').exists()).toBe(false)
    expect(wrapper.findAll('[data-row-action="edit"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-row-action="delete"]')).toHaveLength(0)

    // The read surface stays whole: rows, detail action, KPIs and filters.
    expect(rows(wrapper)).toHaveLength(3)
    expect(wrapper.findAll('[data-row-action="detail"]')).toHaveLength(3)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('3')
    expect(wrapper.find('[data-status-filter]').exists()).toBe(true)
    expect(wrapper.find('[data-type-search]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('opens the prefilled form from ?edit=<id> and keeps it open after the query replace', async () => {
    const { wrapper, shellRouter } = await mountShellAt('/appointment-types?edit=2')

    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    expect(openModalNameInput('Ortodoncia')).toBeTruthy()
    expect(editDurationInput().value).toBe('60')

    // `openEditFromQuery` clears the parameter with `router.replace`: with the
    // route opt-out the key (`$route.path`) is unchanged, so the page that
    // just opened the form is not remounted and the form stays filled.
    expect(shellRouter.currentRoute.value.query.edit).toBeUndefined()
    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    expect(openModalNameInput('Ortodoncia')).toBeTruthy()

    wrapper.unmount()
  })

  it('opens the form again on a later visit with ?edit=<id>', async () => {
    const { wrapper, shellRouter } = await mountShellAt('/appointment-types?edit=1')
    expect(openModalNameInput('Limpieza dental')).toBeTruthy()

    await closeOpenEditModal()
    expect(document.body.textContent).not.toContain('Editar Tipo de Cita')

    // List -> detail -> «Editar» mounts the page again and reads the query.
    await shellRouter.push('/appointment-types/3')
    await flushPromises()
    await shellRouter.push({ path: '/appointment-types', query: { edit: '3' } })
    await flushPromises()

    expect(document.body.textContent).toContain('Editar Tipo de Cita')
    expect(openModalNameInput('Blanqueamiento')).toBeTruthy()
    expect(shellRouter.currentRoute.value.query.edit).toBeUndefined()

    wrapper.unmount()
  })

  it('deletes the type when the confirm dialog is accepted', async () => {
    const wrapper = await mountPage(CATALOG)
    const row = rows(wrapper).find(r => r.text().includes('Limpieza dental'))
    await row.find('[data-row-action="delete"]').trigger('click')
    await flushPromises()

    const confirmBtn = [...document.body.querySelectorAll('button')].find(
      b => b.textContent.trim() === 'Eliminar'
    )
    expect(confirmBtn).toBeTruthy()
    confirmBtn.click()
    await flushPromises()

    expect(deleteMock).toHaveBeenCalledTimes(1)
    expect(String(deleteMock.mock.calls[0][0])).toContain('/api/appointment-types/1')
    wrapper.unmount()
  })
})

// #453 (U11, list): the catalog list inherits the module's table contract. The
// endpoint answers the whole catalog in one `status=all` read, so the primitive
// never paginates: the page keeps its client-side filters and its `N de M`
// counter, and the primitive owns the internal scroll, its affordance and the
// row hooks.
describe('appointment-types list inherits UiDataTable (#453)', () => {
  const pageSource = () =>
    readFileSync('resources/js/modules/appointment-types/AppointmentTypesPage.vue', 'utf8')

  beforeEach(() => {
    seedSession('administrador')
    getMock.mockReset()
    deleteMock.mockReset()
    deleteMock.mockImplementation(async () => ({ data: {} }))
  })

  // jsdom performs no layout: the overflow the primitive advertises is declared
  // by the test through the viewport geometry the primitive reads.
  const declareTableOverflow = async (wrapper, { scrollWidth = 960, clientWidth = 640 } = {}) => {
    const viewport = wrapper.get('[data-types-table] [data-table-scroll-viewport]').element
    Object.defineProperty(viewport, 'scrollWidth', { configurable: true, value: scrollWidth })
    Object.defineProperty(viewport, 'clientWidth', { configurable: true, value: clientWidth })
    viewport.scrollLeft = 0
    window.dispatchEvent(new Event('resize'))
    await nextTick()
    await nextTick()
  }

  it('renders the catalog through UiDataTable instead of a hand-rolled table', async () => {
    const wrapper = await mountPage(CATALOG)

    expect(wrapper.findComponent(DataTable).exists()).toBe(true)
    expect(wrapper.find('[data-types-table] table').exists()).toBe(true)
    expect(wrapper.find('[data-types-table] [data-table-scroll]').exists()).toBe(true)

    // The page no longer renders its own table markup, and the primitive is
    // fed the whole catalog with pagination disabled (the endpoint does not
    // paginate, so inventing a client page would hide rows).
    expect(pageSource()).not.toMatch(/<table\b/)
    expect(wrapper.findComponent(DataTable).props('pagination')).toBe(false)

    // The primitive renders the same columns the page owned before.
    const header = wrapper.get('[data-types-table] thead').text()
    for (const label of ['Nombre', 'Descripción', 'Duración', 'Precio', 'Estado']) {
      expect(header).toContain(label)
    }
    expect(header).toContain('Acciones')

    wrapper.unmount()
  })

  it('keeps the row hook, its cells and its actions inside the table', async () => {
    const wrapper = await mountPage(CATALOG)

    const row = wrapper.get('[data-types-table] [data-type-row]')
    expect(row.element.tagName).toBe('TR')
    expect(row.text()).toContain('Limpieza dental')
    expect(row.text()).toContain('Profilaxis dental completa')
    expect(row.text()).toContain('30 min')
    expect(row.text()).toContain('Activo')
    expect(row.find('[data-type-swatch]').exists()).toBe(true)
    expect(row.get('[aria-label^="Precio"]').text().length).toBeGreaterThan(0)

    const actions = row
      .findAll('[data-row-action]')
      .map(action => action.attributes('data-row-action'))
    expect(actions).toEqual(['detail', 'edit', 'delete'])

    wrapper.unmount()
  })

  it('publishes the primitive scroll affordance when the columns overflow', async () => {
    const wrapper = await mountPage(CATALOG)

    expect(wrapper.find('[data-table-scroll-affordance]').exists()).toBe(false)

    await declareTableOverflow(wrapper)

    expect(wrapper.get('[data-table-scroll]').attributes('data-table-scroll')).toBe('right')
    expect(wrapper.get('[data-table-scroll-affordance]').text()).toMatch(/columnas/i)

    wrapper.unmount()
  })
})
