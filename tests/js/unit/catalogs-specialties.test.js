import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

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
import Page from '../../../resources/js/modules/specialties/SpecialtiesPage.vue'
import { confirmIsOpen } from '../../../resources/js/composables/useConfirm'
import rolePermissions from '../support/role-permissions.json'

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

const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/specialties', component: Page },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/specialties')
  await router.isReady()

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

const setField = async (name, value) => {
  const input = document.querySelector(
    `[data-field="${name}"] input, [data-field="${name}"] textarea`
  )
  expect(input, `field "${name}" must exist`).toBeTruthy()
  input.value = value
  input.dispatchEvent(new Event('input'))
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  seedSession('administrador')
  getMock.mockResolvedValue({ data: [], meta: { kpis: KPI_POPULATION } })
  postMock.mockResolvedValue({ data: { id: 10, code: 'cirugia_oral', name: 'Cirugía oral' } })
  putMock.mockResolvedValue({ data: { ...SPECIALTIES[0], is_active: false } })
})

afterEach(async () => {
  confirmIsOpen.value = false
  await flushPromises()
  document.body.innerHTML = ''
})

describe('specialties · regression under the catalog shell', () => {
  it('renders through the shared shell without losing its header, KPIs and filters', async () => {
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    expect(wrapper.findComponent({ name: 'CatalogPageShell' }).exists()).toBe(true)
    expect(wrapper.find('[data-catalog-content]').attributes('data-catalog-state')).toBe('ready')

    const header = wrapper.find('[data-page-header]')
    expect(header.find('h1').text()).toBe('Especialidades')
    expect(header.find('[data-page-header-kicker]').text()).toBe('Catálogo')
    expect(header.text()).toContain('Taxonomía de procedimientos y profesionales')
    expect(header.text()).toContain('Actualizar')
    expect(header.text()).toContain('Nueva especialidad')

    expect(wrapper.find('[data-catalog-kpis]').attributes('aria-label')).toBe(
      'Resumen de especialidades'
    )
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('9')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('7')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-value="procedures"]').text()).toBe('48')

    const bar = wrapper.find('[data-specialties-filters]')
    expect(bar.attributes('role')).toBe('search')
    expect(bar.find('[data-specialties-search]').exists()).toBe(true)
    expect(bar.find('[data-status-filter]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('keeps the rows, usage counts and status badges of the list', async () => {
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    const general = rowByText(wrapper, 'Odontología general')
    expect(general.text()).toContain('general')
    expect(general.find('[data-row-procedures]').text()).toContain('12')
    expect(general.find('[data-row-professionals]').text()).toContain('4')
    expect(general.text()).toContain('Activa')

    const orthodontics = rowByText(wrapper, 'Ortodoncia')
    expect(orthodontics.text()).toContain('Inactiva')
    expect(orthodontics.find('[data-row-procedures]').text()).toContain('Sin procedimientos')

    const periodontics = rowByText(wrapper, 'Periodoncia')
    expect(periodontics.find('[data-row-professionals]').text()).toContain('Sin profesionales')

    wrapper.unmount()
  })

  it('keeps the error state with retry and the unfiltered empty state', async () => {
    getMock
      .mockRejectedValueOnce(new Error('network down'))
      .mockResolvedValue({ data: [SPECIALTIES[0]], meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    const alert = wrapper.find('[data-specialties-error]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toContain('No pudimos cargar las especialidades')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('N/D')
    expect(wrapper.find('[data-catalog-content]').attributes('data-catalog-state')).toBe('error')

    await wrapper.find('[data-specialties-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-specialties-error]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('9')
    expect(rowByText(wrapper, 'Odontología general').exists()).toBe(true)

    wrapper.unmount()
  })

  it('keeps create and edit wired to the existing form modal', async () => {
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    await wrapper.find('[data-new-specialty]').trigger('click')
    await flushPromises()

    let dialog = document.querySelector('[role="dialog"]')
    expect(dialog).toBeTruthy()
    expect(dialog.textContent).toContain('Nueva especialidad')
    expect(dialog.querySelector('[data-specialty-form="create"]')).toBeTruthy()

    dialog.querySelector('[data-modal-cancel]').click()
    await flushPromises()

    await rowByText(wrapper, 'Ortodoncia').find('[data-row-action="edit"]').trigger('click')
    await flushPromises()

    dialog = document.querySelector('[role="dialog"]')
    expect(dialog.textContent).toContain('Editar especialidad')
    expect(dialog.querySelector('[data-specialty-form="edit"]')).toBeTruthy()
    expect(dialog.querySelector('[data-field="code"] input').value).toBe('ortodoncia')
    expect(dialog.querySelector('[data-field="code"] input').disabled).toBe(true)

    wrapper.unmount()
  })

  it('keeps server validation errors attached to their field', async () => {
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    postMock.mockRejectedValueOnce({
      response: { data: { errors: { name: ['El nombre ya existe.'] } } }
    })
    const wrapper = await mountPage()

    await wrapper.find('[data-new-specialty]').trigger('click')
    await flushPromises()

    await setField('code', 'cirugia_oral')
    await setField('name', 'Cirugía oral')

    document.querySelector('[data-modal-submit]').click()
    await flushPromises()
    await flushPromises()

    expect(postMock).toHaveBeenCalledWith('/api/specialties', {
      code: 'cirugia_oral',
      name: 'Cirugía oral',
      description: null,
      is_active: true
    })

    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog.textContent).toContain('El nombre ya existe.')
    expect(dialog.querySelector('[data-field="name"]').textContent).toContain(
      'El nombre ya existe.'
    )

    wrapper.unmount()
  })

  it('keeps the read-only surface for a session without manage.specialty', async () => {
    seedSession('recepcionista')
    getMock.mockResolvedValue({ data: SPECIALTIES, meta: { kpis: KPI_POPULATION } })
    const wrapper = await mountPage()

    expect(wrapper.find('[data-page-header]').text()).not.toContain('Nueva especialidad')
    expect(rowByText(wrapper, 'Odontología general').text()).toContain('Odontología general')
    expect(wrapper.find('[data-row-action="edit"]').exists()).toBe(false)
    expect(wrapper.find('[data-row-action="toggle"]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('9')
    expect(wrapper.find('[data-status-filter]').exists()).toBe(true)

    wrapper.unmount()
  })
})
