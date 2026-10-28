import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Branches list redesign contract: the dashboard hero band, a KPI strip
// derived from the SAME directory the table renders, client-side search +
// status filtering over one load, the loading/error/empty/table state ladder,
// row actions (detail navigation, prefilled edit modal, confirm-gated
// activate/deactivate) and the ?edit=<id> deep link. The API stays unchanged:
// the page loads /api/branches once and filters in memory, so the dead
// server-side pagination path is gone.
const { getMock, postMock, putMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  putMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: putMock,
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
import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/settings/branches/BranchesPage.vue'
import Select from '../../../resources/js/components/ui/Select.vue'
import rolePermissions from '../support/role-permissions.json'

// #439: the page gates its write affordances on `usePermissions().can.*`.
const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const BRANCHES = [
  {
    id: 1,
    code: 'SC-LIM-01',
    name: 'Sede Central Lima',
    address: 'Av. Arequipa 123',
    city: 'Lima',
    state: 'Lima',
    country: 'Perú',
    postal_code: '15046',
    phone: '+51 1 426-0001',
    email: 'central@odontosuite.pe',
    timezone: 'America/Lima',
    latitude: '-12.05000000',
    longitude: '-77.04000000',
    description: 'Sede principal de la clínica.',
    is_active: true,
    created_at: '2026-01-10T10:00:00.000000Z',
    updated_at: '2026-02-10T10:00:00.000000Z'
  },
  {
    id: 2,
    code: 'SC-AQP-01',
    name: 'Sucursal Arequipa',
    address: 'Calle Mercaderes 456',
    city: 'Arequipa',
    state: 'Arequipa',
    country: 'Perú',
    postal_code: '04001',
    phone: '+51 54 123-456',
    email: 'aqp@odontosuite.pe',
    timezone: 'America/Lima',
    latitude: null,
    longitude: null,
    description: '',
    is_active: true,
    created_at: '2026-01-11T10:00:00.000000Z',
    updated_at: '2026-01-11T10:00:00.000000Z'
  },
  {
    id: 3,
    code: 'SC-LIM-02',
    name: 'Sucursal San Isidro',
    address: 'Calle Las Begonias 789',
    city: 'Lima',
    state: 'Lima',
    country: 'Perú',
    postal_code: '15047',
    phone: null,
    email: null,
    timezone: 'America/Lima',
    latitude: null,
    longitude: null,
    description: 'Sede administrativa.',
    is_active: false,
    created_at: '2026-01-12T10:00:00.000000Z',
    updated_at: '2026-01-12T10:00:00.000000Z'
  }
]

const makeRouter = () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/settings/branches', component: { template: '<div />' } },
      { path: '/settings/branches/:id', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  return router
}

const mountPage = async (catalog = BRANCHES, { query } = {}) => {
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/branches')) {
      return { data: catalog, meta: { message: 'Sucursales cargadas exitosamente' } }
    }
    return { data: [] }
  })
  const router = makeRouter()
  await router.push({ path: '/settings/branches', query })
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return { wrapper, router }
}

const rows = wrapper => wrapper.findAll('[data-branches-table] tbody tr')

const rowFor = (wrapper, text) => rows(wrapper).find(row => row.text().includes(text))

const statusSelect = wrapper =>
  wrapper.findAllComponents(Select).find(component => component.attributes('data-status-filter'))

const chooseStatusOption = async (wrapper, label) => {
  const select = statusSelect(wrapper)
  expect(select).toBeTruthy()
  await select.find('button[aria-haspopup="true"]').trigger('click')
  const option = select.findAll('button').find(button => button.text().trim() === label)
  expect(option, `status option "${label}" must exist`).toBeTruthy()
  await option.trigger('click')
  await flushPromises()
}

const buttonByText = (scope, text) =>
  [...scope.querySelectorAll('button')].find(button => button.textContent.trim() === text)

describe('branches list smoke', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    getMock.mockReset()
    postMock.mockReset()
    putMock.mockReset()
    seedSession('administrador')
  })

  it('renders the hero band with eyebrow, headline, caption and the primary action', async () => {
    const { wrapper } = await mountPage()

    const hero = wrapper.find('[data-branches-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('[data-hero-title]').text()).toBe('Sucursales')
    expect(hero.find('[data-hero-eyebrow]').text().length).toBeGreaterThan(0)
    expect(hero.find('[data-hero-caption]').text().length).toBeGreaterThan(0)
    expect(hero.find('[data-hero-action]').text()).toContain('Nueva sucursal')

    wrapper.unmount()
  })

  it('derives the four KPI slots from the same loaded directory', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('3')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('1')
    expect(wrapper.find('[data-kpi-value="cities"]').text()).toBe('2')

    wrapper.unmount()
  })

  it('renders code, location, contact and status for every branch', async () => {
    const { wrapper } = await mountPage()
    expect(rows(wrapper)).toHaveLength(3)

    const central = rowFor(wrapper, 'Sede Central Lima')
    expect(central.text()).toContain('SC-LIM-01')
    expect(central.text()).toContain('Av. Arequipa 123')
    expect(central.text()).toContain('Lima')
    expect(central.text()).toContain('+51 1 426-0001')
    expect(central.text()).toContain('Activa')

    expect(rowFor(wrapper, 'Sucursal San Isidro').text()).toContain('Inactiva')

    wrapper.unmount()
  })

  it('filters the loaded directory client-side through the status filter', async () => {
    const { wrapper } = await mountPage()
    expect(rows(wrapper)).toHaveLength(3)

    await chooseStatusOption(wrapper, 'Inactivas')
    expect(rows(wrapper)).toHaveLength(1)
    expect(rows(wrapper)[0].text()).toContain('Sucursal San Isidro')
    expect(wrapper.find('[data-filter-count]').text()).toContain('Mostrando 1 de 3')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('3')

    await chooseStatusOption(wrapper, 'Activas')
    expect(rows(wrapper)).toHaveLength(2)

    await chooseStatusOption(wrapper, 'Todas')
    expect(rows(wrapper)).toHaveLength(3)

    wrapper.unmount()
  })

  it('filters the loaded directory client-side through the search text', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('[data-branch-search] input').setValue('arequipa')
    await flushPromises()

    expect(rows(wrapper)).toHaveLength(1)
    expect(rows(wrapper)[0].text()).toContain('Sucursal Arequipa')

    wrapper.unmount()
  })

  it('shows a contextual empty state and clears the filters from it', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('[data-branch-search] input').setValue('zzz')
    await flushPromises()

    expect(rows(wrapper)).toHaveLength(0)
    expect(wrapper.find('[data-branches-empty]').text()).toContain('No se encontraron sucursales')

    const clear = wrapper
      .find('[data-branches-empty]')
      .findAll('button')
      .find(button => button.text().includes('Limpiar filtros'))
    expect(clear).toBeTruthy()
    await clear.trigger('click')
    await flushPromises()

    expect(rows(wrapper)).toHaveLength(3)
    expect(wrapper.find('[data-branch-search] input').element.value).toBe('')
    // The directory is read once and filtered in memory: the reset issues no
    // second request.
    expect(
      getMock.mock.calls.filter(([url]) => String(url).startsWith('/api/branches'))
    ).toHaveLength(1)

    wrapper.unmount()
  })

  it('mounts the filter bar primitive around the search, the status select and the count', async () => {
    const { wrapper } = await mountPage()

    const bar = wrapper.find('[data-branches-filters]')

    expect(bar.exists()).toBe(true)
    expect(bar.attributes('role')).toBe('search')
    expect(bar.find('[data-branch-search]').exists()).toBe(true)
    expect(bar.find('[data-status-filter]').exists()).toBe(true)
    expect(bar.find('[data-filter-count]').exists()).toBe(true)
    expect(bar.find('[data-filters-clear]').exists()).toBe(false)

    await wrapper.find('[data-branch-search] input').setValue('lima')
    await flushPromises()

    expect(bar.find('[data-filters-clear]').text()).toBe('Limpiar filtros')

    wrapper.unmount()
  })

  it('renders the inline error surface with retry when loading fails', async () => {
    getMock.mockRejectedValueOnce(new Error('network down'))
    getMock.mockImplementation(async url => {
      if (String(url).startsWith('/api/branches')) {
        return { data: BRANCHES, meta: { message: 'ok' } }
      }
      return { data: [] }
    })
    const router = makeRouter()
    await router.push('/settings/branches')
    await router.isReady()
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(wrapper.find('[data-branches-error]').exists()).toBe(true)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('N/D')

    await wrapper.find('[data-retry-branches]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-branches-error]').exists()).toBe(false)
    expect(rows(wrapper)).toHaveLength(3)

    wrapper.unmount()
  })

  it('navigates to the branch detail from the row action', async () => {
    const { wrapper, router } = await mountPage()

    await rowFor(wrapper, 'Sede Central Lima').find('[data-row-action="detail"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/settings/branches/1')

    wrapper.unmount()
  })

  it('opens the create modal from the hero action with an editable code', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('[data-hero-action]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Nueva sucursal')
    const code = document.querySelector('[data-field="code"] input')
    expect(code).toBeTruthy()
    expect(code.disabled).toBe(false)

    wrapper.unmount()
  })

  it('opens the edit modal prefilled from the row action', async () => {
    const { wrapper } = await mountPage()

    await rowFor(wrapper, 'Sucursal Arequipa').find('[data-row-action="edit"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Editar sucursal')
    expect(document.querySelector('[data-field="code"] input').disabled).toBe(true)
    expect(document.querySelector('[data-field="name"] input').value).toBe('Sucursal Arequipa')
    expect(document.querySelector('[data-field="city"] input').value).toBe('Arequipa')

    wrapper.unmount()
  })

  it('opens the shared edit modal from the ?edit=<id> deep link and clears the query', async () => {
    const { wrapper, router } = await mountPage(BRANCHES, { query: { edit: '2' } })

    expect(document.body.textContent).toContain('Editar sucursal')
    expect(document.querySelector('[data-field="name"] input').value).toBe('Sucursal Arequipa')
    expect(router.currentRoute.value.query.edit).toBeUndefined()

    wrapper.unmount()
  })

  it('ignores an unknown ?edit id without opening the modal', async () => {
    const { wrapper, router } = await mountPage(BRANCHES, { query: { edit: '999' } })

    expect(document.body.textContent).not.toContain('Editar sucursal')
    expect(router.currentRoute.value.query.edit).toBeUndefined()

    wrapper.unmount()
  })

  it('gates deactivation on the confirm dialog and aborts when cancelled', async () => {
    const { wrapper } = await mountPage()

    await rowFor(wrapper, 'Sede Central Lima').find('[data-row-action="toggle"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Desactivar sucursal')
    const cancel = buttonByText(document.body, 'Cancelar')
    expect(cancel).toBeTruthy()
    cancel.click()
    await flushPromises()

    expect(putMock).not.toHaveBeenCalled()
    expect(rowFor(wrapper, 'Sede Central Lima').text()).toContain('Activa')

    wrapper.unmount()
  })

  it('deactivates after confirming in the dialog', async () => {
    putMock.mockImplementation(async (url, payload) => ({ data: { ...BRANCHES[0], ...payload } }))
    const { wrapper } = await mountPage()

    await rowFor(wrapper, 'Sede Central Lima').find('[data-row-action="toggle"]').trigger('click')
    await flushPromises()

    const confirm = buttonByText(document.body, 'Desactivar')
    expect(confirm).toBeTruthy()
    confirm.click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledTimes(1)
    const [url, payload] = putMock.mock.calls[0]
    expect(url).toBe('/api/branches/1')
    expect(payload.is_active).toBe(false)
    expect(rowFor(wrapper, 'Sede Central Lima').text()).toContain('Inactiva')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('1')

    wrapper.unmount()
  })

  it('offers no write affordance to a session without manage.branch', async () => {
    seedSession('recepcionista')
    const { wrapper } = await mountPage()

    expect(wrapper.find('[data-hero-action]').exists()).toBe(false)
    expect(rowFor(wrapper, 'Sede Central Lima').find('[data-row-action="detail"]').exists()).toBe(
      true
    )
    expect(wrapper.find('[data-row-action="edit"]').exists()).toBe(false)
    expect(wrapper.find('[data-row-action="toggle"]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('3')

    wrapper.unmount()
  })
})
