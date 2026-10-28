import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

const { getMock, putMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  putMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(),
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
import Page from '../../../resources/js/modules/specialties/SpecialtiesPage.vue'
import { confirmIsOpen } from '../../../resources/js/composables/useConfirm'
import rolePermissions from '../support/role-permissions.json'

// #439: the page gates its write affordances on `usePermissions().can.*`, and
// those flags are a membership check on the permission list the API publishes.
const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const KPI_POPULATION = { total: 9, active: 7, inactive: 2, procedures: 48 }

const SPECIALTIES = [
  {
    id: 1,
    code: 'general',
    name: 'Odontología general',
    description: 'Atención integral, preventiva y diagnóstico general.',
    is_active: true,
    procedures_count: 12,
    professionals_count: 4
  },
  {
    id: 2,
    code: 'ortodoncia',
    name: 'Ortodoncia',
    description: 'Brackets, alineadores y retención.',
    is_active: false,
    procedures_count: 0,
    professionals_count: 1
  },
  {
    id: 3,
    code: 'periodoncia',
    name: 'Periodoncia',
    description: null,
    is_active: true,
    procedures_count: 3,
    professionals_count: 0
  }
]

const makeRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })

const mountPage = async () => {
  const router = makeRouter()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const dataRows = wrapper => wrapper.findAll('[data-specialties-table] tbody tr')

const rowByText = (wrapper, text) => {
  const row = dataRows(wrapper).find(candidate => candidate.text().includes(text))
  expect(row, `row containing "${text}" must exist`).toBeTruthy()
  return row
}

const selectStatus = async (wrapper, label) => {
  const select = wrapper.find('[data-status-filter]')
  expect(select.exists()).toBe(true)
  await select.find('button[aria-haspopup="true"]').trigger('click')
  const option = select
    .findAll('[role="listbox"] button')
    .find(candidate => candidate.text().trim() === label)
  expect(option, `status option "${label}" must exist`).toBeTruthy()
  await option.trigger('click')
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  seedSession('administrador')
  getMock.mockResolvedValue({ data: [], meta: { kpis: KPI_POPULATION } })
})

afterEach(async () => {
  confirmIsOpen.value = false
  await nextTick()
  document.body.innerHTML = ''
})

describe('specialties list smoke', () => {
  it('renders the hero band with the page title and its actions', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.find('[data-specialties-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('h2').text()).toBe('Especialidades')
    expect(hero.text()).toContain('Catálogo clínico')
    expect(hero.text()).toContain(
      'Administra la taxonomía que ordena procedimientos y profesionales'
    )
    expect(hero.text()).toContain('Volver')
    expect(hero.text()).toContain('Nueva especialidad')
    expect(wrapper.findComponent({ name: 'PageHeader' }).exists()).toBe(false)

    wrapper.unmount()
  })

  it('renders the four KPIs from the server population, not from the loaded rows', async () => {
    getMock.mockResolvedValue({
      data: [SPECIALTIES[0]],
      meta: { kpis: KPI_POPULATION }
    })
    const wrapper = await mountPage()

    expect(wrapper.find('[data-kpi-card="total"]').exists()).toBe(true)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('9')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('7')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-value="procedures"]').text()).toBe('48')

    wrapper.unmount()
  })

  it('shows N/D instead of a fabricated zero while loading', async () => {
    getMock.mockReturnValue(new Promise(() => {}))
    const wrapper = mount(Page, { global: { plugins: [makeRouter(), uiComponents] } })
    await nextTick()

    expect(wrapper.find('[data-specialties-loading]').exists()).toBe(true)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('N/D')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('N/D')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('N/D')
    expect(wrapper.find('[data-kpi-value="procedures"]').text()).toBe('N/D')

    wrapper.unmount()
  })

  it('renders each row with its code, usage counts and status badge', async () => {
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    const general = rowByText(wrapper, 'Odontología general')
    expect(general.text()).toContain('general')
    expect(general.find('[data-row-procedures]').text()).toContain('12')
    expect(general.find('[data-row-professionals]').text()).toContain('4')
    expect(general.text()).toContain('Activa')
    expect(general.text()).not.toContain('Sin procedimientos')

    const orthodontics = rowByText(wrapper, 'Ortodoncia')
    expect(orthodontics.text()).toContain('Inactiva')
    expect(orthodontics.find('[data-row-procedures]').text()).toContain('Sin procedimientos')

    const periodontics = rowByText(wrapper, 'Periodoncia')
    expect(periodontics.find('[data-row-professionals]').text()).toContain('Sin profesionales')

    wrapper.unmount()
  })

  it('offers edit and activate/deactivate but never delete', async () => {
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    const activeRow = rowByText(wrapper, 'Odontología general')
    expect(activeRow.find('[data-row-action="edit"]').exists()).toBe(true)
    expect(activeRow.find('[data-row-action="toggle"]').text()).toBe('Desactivar')
    expect(activeRow.find('[data-row-action="delete"]').exists()).toBe(false)

    const inactiveRow = rowByText(wrapper, 'Ortodoncia')
    expect(inactiveRow.find('[data-row-action="toggle"]').text()).toBe('Activar')

    wrapper.unmount()
  })

  it('offers no write affordance to a session without manage.specialty', async () => {
    seedSession('recepcionista')
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    expect(wrapper.find('[data-specialties-hero]').text()).not.toContain('Nueva especialidad')
    expect(rowByText(wrapper, 'Odontología general').text()).toContain('Odontología general')
    expect(wrapper.find('[data-row-action="edit"]').exists()).toBe(false)
    expect(wrapper.find('[data-row-action="toggle"]').exists()).toBe(false)
    // The read surface stays whole: KPIs and filters are not gated.
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('9')
    expect(wrapper.find('[data-status-filter]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('confirms and deactivates a specialty against the catalog contract', async () => {
    getMock.mockResolvedValue({ data: [SPECIALTIES[0]], meta: { kpis: KPI_POPULATION } })
    putMock.mockResolvedValue({ data: { ...SPECIALTIES[0], is_active: false } })
    const wrapper = await mountPage()

    await rowByText(wrapper, 'Odontología general')
      .find('[data-row-action="toggle"]')
      .trigger('click')
    await flushPromises()

    const dialog = document.body.querySelector('[role="alertdialog"]')
    expect(dialog, 'useConfirm dialog must open').toBeTruthy()
    const confirmButton = [...dialog.querySelectorAll('button')].find(
      button => button.textContent.trim() === 'Desactivar'
    )
    expect(confirmButton, 'confirm button must exist').toBeTruthy()
    confirmButton.click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledWith('/api/specialties/1', { is_active: false })
    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/specialties')

    wrapper.unmount()
  })

  it('opens the edit modal from a row', async () => {
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    await rowByText(wrapper, 'Ortodoncia').find('[data-row-action="edit"]').trigger('click')
    await flushPromises()

    const dialog = document.body.querySelector('[role="dialog"]')
    expect(dialog).toBeTruthy()
    expect(dialog.textContent).toContain('Editar especialidad')
    expect(dialog.querySelector('[data-specialty-form="edit"]')).toBeTruthy()

    wrapper.unmount()
  })

  it('shows the error callout with retry and recovers after Reintentar', async () => {
    getMock
      .mockRejectedValueOnce(new Error('network down'))
      .mockResolvedValue({ data: [SPECIALTIES[0]], meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    const alert = wrapper.find('[data-specialties-error]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toContain('No pudimos cargar las especialidades')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('N/D')
    expect(wrapper.find('[data-specialties-table]').exists()).toBe(false)

    await wrapper.find('[data-specialties-retry]').trigger('click')
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/specialties')
    expect(wrapper.find('[data-specialties-error]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('9')
    expect(rowByText(wrapper, 'Odontología general').exists()).toBe(true)

    wrapper.unmount()
  })

  it('renders the unfiltered empty state with a create action', async () => {
    const wrapper = await mountPage()

    const empty = wrapper.find('[data-specialties-empty]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('Aún no hay especialidades')
    expect(empty.text()).toContain('Nueva especialidad')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('9')

    wrapper.unmount()
  })

  it('filters server-side and keeps the KPI population intact when nothing matches', async () => {
    getMock
      .mockResolvedValueOnce({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
      .mockResolvedValueOnce({ data: [], meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    await selectStatus(wrapper, 'Inactivas')

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/specialties?is_active=false')
    const empty = wrapper.find('[data-specialties-empty]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('No se encontraron especialidades')
    expect(empty.text()).toContain('Limpiar filtros')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('9')

    wrapper.unmount()
  })

  it('mounts the filter bar primitive and «Limpiar filtros» re-reads the defaults once', async () => {
    getMock
      .mockResolvedValueOnce({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
      .mockResolvedValueOnce({ data: [], meta: { kpis: KPI_POPULATION } })
      .mockResolvedValueOnce({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    const bar = wrapper.find('[data-specialties-filters]')

    expect(bar.exists()).toBe(true)
    expect(bar.attributes('role')).toBe('search')
    expect(bar.find('[data-specialties-search]').exists()).toBe(true)
    expect(bar.find('[data-status-filter]').exists()).toBe(true)

    expect(bar.find('[data-filters-clear]').exists()).toBe(false)

    await selectStatus(wrapper, 'Inactivas')

    // The reset lives inside the bar as well as in the empty state, so the
    // browser measurement reads it in the search region.
    const inBarClear = bar.find('[data-filters-clear]')

    expect(inBarClear.exists()).toBe(true)
    expect(inBarClear.text()).toBe('Limpiar filtros')

    const clear = wrapper
      .find('[data-specialties-empty]')
      .findAll('button')
      .find(button => button.text().trim() === 'Limpiar filtros')

    expect(clear, 'Limpiar filtros must be offered when filters hide every row').toBeTruthy()

    await inBarClear.trigger('click')
    await flushPromises()

    expect(getMock).toHaveBeenCalledTimes(3)
    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/specialties')
    expect(wrapper.find('[data-specialties-search] input').element.value).toBe('')
    expect(wrapper.find('[data-status-filter]').text()).toContain('Todos los estados')
    expect(dataRows(wrapper)).toHaveLength(3)

    wrapper.unmount()
  })
})
