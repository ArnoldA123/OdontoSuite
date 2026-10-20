import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

const { getMock, putMock, deleteMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  putMock: vi.fn(),
  deleteMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(),
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
import Page from '../../../resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue'
import { confirmIsOpen } from '../../../resources/js/composables/useConfirm'

const METHODS = [
  {
    id: 1,
    code: 'CASH',
    name: 'Efectivo',
    description: 'Cobro directo en caja',
    gateway_type: 'manual',
    commission_percentage: 0,
    requires_authorization: false,
    allows_change: true,
    is_active: true,
    is_system: true,
    has_gateway_config: false,
    transactions_count: 0
  },
  {
    id: 2,
    code: 'CARD',
    name: 'Tarjeta',
    description: 'Cobro con pasarela',
    gateway_type: 'mercadopago',
    commission_percentage: 3.5,
    requires_authorization: true,
    allows_change: true,
    is_active: false,
    is_system: false,
    has_gateway_config: true,
    transactions_count: 0
  },
  {
    id: 3,
    code: 'YAPE',
    name: 'Yape',
    description: 'Billetera digital',
    gateway_type: 'manual',
    commission_percentage: 2,
    requires_authorization: false,
    allows_change: true,
    is_active: true,
    is_system: false,
    has_gateway_config: false,
    transactions_count: 4
  },
  {
    id: 4,
    code: 'TRANSFER',
    name: 'Transferencia',
    description: null,
    gateway_type: 'manual',
    commission_percentage: 0,
    requires_authorization: false,
    allows_change: true,
    is_active: true,
    is_system: false,
    has_gateway_config: false,
    transactions_count: 0
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

const dataRows = wrapper => wrapper.findAll('[data-pm-table] tbody tr')

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
  getMock.mockResolvedValue({ data: [] })
})

afterEach(async () => {
  confirmIsOpen.value = false
  await nextTick()
  document.body.innerHTML = ''
})

describe('payment methods list smoke', () => {
  it('renders the hero band with the page title and its actions', async () => {
    const wrapper = await mountPage()

    const hero = wrapper.find('[data-pm-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('h2').text()).toBe('Métodos de Pago')
    expect(hero.text()).toContain('Configuración')
    expect(hero.text()).toContain('Gestiona los métodos con los que la clínica registra cobros')
    expect(hero.text()).toContain('Volver')
    expect(hero.text()).toContain('Nuevo método')
    expect(wrapper.findComponent({ name: 'PageHeader' }).exists()).toBe(false)

    wrapper.unmount()
  })

  it('derives the four KPI values from the loaded rows', async () => {
    getMock.mockResolvedValue({ data: METHODS })
    const wrapper = await mountPage()

    expect(wrapper.find('[data-kpi-card="total"]').exists()).toBe(true)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('4')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('3')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('1')
    expect(wrapper.find('[data-kpi-value="system"]').text()).toBe('1')

    wrapper.unmount()
  })

  it('shows N/D instead of a fabricated zero while loading', async () => {
    getMock.mockReturnValue(new Promise(() => {}))
    const wrapper = mount(Page, { global: { plugins: [makeRouter(), uiComponents] } })
    await nextTick()

    expect(wrapper.find('[data-pm-loading]').exists()).toBe(true)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('N/D')
    expect(wrapper.find('[data-kpi-value="active"]').text()).toBe('N/D')
    expect(wrapper.find('[data-kpi-value="inactive"]').text()).toBe('N/D')
    expect(wrapper.find('[data-kpi-value="system"]').text()).toBe('N/D')

    wrapper.unmount()
  })

  it('renders the friendly gateway label, formatted commission and scope/status badges', async () => {
    getMock.mockResolvedValue({ data: METHODS })
    const wrapper = await mountPage()

    const systemRow = rowByText(wrapper, 'Efectivo')
    expect(systemRow.text()).toContain('CASH')
    expect(systemRow.text()).toContain('Manual')
    expect(systemRow.text()).toContain('0%')
    expect(systemRow.text()).toContain('Sistema')
    expect(systemRow.text()).toContain('Activo')
    expect(systemRow.find('[data-gateway-configured]').exists()).toBe(false)

    const gatewayRow = rowByText(wrapper, 'Tarjeta')
    expect(gatewayRow.text()).toContain('Mercado Pago')
    expect(gatewayRow.text()).not.toContain('mercadopago')
    expect(gatewayRow.text()).toContain('3.5%')
    expect(gatewayRow.text()).toContain('Personalizado')
    expect(gatewayRow.text()).toContain('Inactivo')
    expect(gatewayRow.find('[data-gateway-configured]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('hides delete for system methods and methods with transactions, and shows it for deletable ones', async () => {
    getMock.mockResolvedValue({ data: METHODS })
    const wrapper = await mountPage()

    const systemRow = rowByText(wrapper, 'Efectivo')
    expect(systemRow.find('[data-row-action="edit"]').exists()).toBe(true)
    expect(systemRow.find('[data-row-action="delete"]').exists()).toBe(false)
    expect(systemRow.find('[data-row-action="toggle"]').text()).toBe('Desactivar')

    const busyRow = rowByText(wrapper, 'Yape')
    expect(busyRow.find('[data-row-action="delete"]').exists()).toBe(false)

    const deletableRow = rowByText(wrapper, 'Transferencia')
    expect(deletableRow.find('[data-row-action="delete"]').exists()).toBe(true)

    const inactiveRow = rowByText(wrapper, 'Tarjeta')
    expect(inactiveRow.find('[data-row-action="toggle"]').text()).toBe('Activar')

    wrapper.unmount()
  })

  it('confirms and updates the active state of a system method', async () => {
    getMock.mockResolvedValue({ data: [METHODS[0]] })
    putMock.mockResolvedValue({ data: {} })
    const wrapper = await mountPage()

    await rowByText(wrapper, 'Efectivo').find('[data-row-action="toggle"]').trigger('click')
    await flushPromises()

    const dialog = document.body.querySelector('[role="alertdialog"]')
    expect(dialog, 'useConfirm dialog must open').toBeTruthy()
    const confirmButton = [...dialog.querySelectorAll('button')].find(
      button => button.textContent.trim() === 'Desactivar'
    )
    expect(confirmButton, 'confirm button must exist').toBeTruthy()
    confirmButton.click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledWith('/api/payment-methods/1', {
      name: 'Efectivo',
      is_active: false
    })
    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/payment-methods')

    wrapper.unmount()
  })

  it('shows the error callout with retry and recovers after Reintentar', async () => {
    getMock
      .mockRejectedValueOnce(new Error('network down'))
      .mockResolvedValue({ data: [METHODS[3]] })
    const wrapper = await mountPage()

    const alert = wrapper.find('[data-pm-error]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toContain('No pudimos cargar los métodos de pago')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('N/D')
    expect(wrapper.find('[data-pm-table]').exists()).toBe(false)

    await wrapper.find('[data-pm-retry]').trigger('click')
    await flushPromises()

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/payment-methods')
    expect(wrapper.find('[data-pm-error]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('1')
    expect(rowByText(wrapper, 'Transferencia').exists()).toBe(true)

    wrapper.unmount()
  })

  it('renders the unfiltered empty state with a create action', async () => {
    getMock.mockResolvedValue({ data: [] })
    const wrapper = await mountPage()

    const empty = wrapper.find('[data-pm-empty]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('Aún no hay métodos de pago')
    expect(empty.text()).toContain('Nuevo método')
    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('0')

    wrapper.unmount()
  })

  it('requests the status filter server-side and offers to clear it when nothing matches', async () => {
    getMock.mockResolvedValueOnce({ data: METHODS }).mockResolvedValueOnce({ data: [] })
    const wrapper = await mountPage()

    await selectStatus(wrapper, 'Inactivos')

    expect(getMock.mock.calls.at(-1)[0]).toBe('/api/payment-methods?is_active=false')
    const empty = wrapper.find('[data-pm-empty]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('No se encontraron métodos de pago')
    expect(empty.text()).toContain('Limpiar filtros')

    wrapper.unmount()
  })
})
