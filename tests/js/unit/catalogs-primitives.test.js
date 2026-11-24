import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(),
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

import uiComponents from '../../../resources/js/plugins/ui-components'
import CatalogPageShell from '../../../resources/js/components/catalogs/CatalogPageShell.vue'
import CatalogFormModal from '../../../resources/js/components/catalogs/CatalogFormModal.vue'

const makeRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })

const mountShell = async (props = {}, slots = {}) => {
  const router = makeRouter()
  await router.push('/catalogs')
  await router.isReady()

  return mount(CatalogPageShell, {
    props: {
      kicker: 'Catálogo',
      title: 'Procedimientos',
      subtitle: 'Actos clínicos y facturables',
      listLabel: 'Procedimientos',
      kpiLabel: 'Resumen de procedimientos',
      ...props
    },
    slots,
    global: { plugins: [router, uiComponents] }
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador', permissions: [] }))
  getMock.mockResolvedValue({ data: [], meta: {} })
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('CatalogPageShell', () => {
  it('renders the shared header, actions, KPIs, filters and table slots', async () => {
    const wrapper = await mountShell(
      {},
      {
        actions: '<button type="button" data-slot-action>Actualizar</button>',
        kpis: '<div data-slot-kpi>3</div>',
        filters: '<div data-slot-filter>Filtros</div>',
        heading: '<h2 data-slot-heading>Todos los procedimientos</h2>',
        table: '<div data-slot-table>Filas</div>'
      }
    )

    const header = wrapper.find('[data-page-header]')
    expect(header.find('h1').text()).toBe('Procedimientos')
    expect(header.text()).toContain('Catálogo')
    expect(header.text()).toContain('Actos clínicos y facturables')
    expect(header.find('[data-slot-action]').exists()).toBe(true)

    expect(wrapper.find('[data-catalog-kpis]').attributes('aria-label')).toBe(
      'Resumen de procedimientos'
    )
    expect(wrapper.find('[data-slot-kpi]').exists()).toBe(true)
    expect(wrapper.find('[data-catalog-filters] [data-slot-filter]').exists()).toBe(true)
    expect(wrapper.find('[data-catalog-list]').attributes('aria-label')).toBe('Procedimientos')
    expect(wrapper.find('[data-slot-heading]').exists()).toBe(true)

    const content = wrapper.find('[data-catalog-content]')
    expect(content.attributes('data-catalog-state')).toBe('ready')
    expect(content.find('[data-slot-table]').exists()).toBe(true)
    expect(content.find('[data-slot-error]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('selects the loading, error, empty and table slot from one state prop', async () => {
    const wrapper = await mountShell(
      { state: 'error' },
      {
        loading: '<div data-slot-loading>Cargando</div>',
        error: '<div data-slot-error role="alert">Falló</div>',
        empty: '<div data-slot-empty>Vacío</div>',
        table: '<div data-slot-table>Filas</div>'
      }
    )

    const content = () => wrapper.find('[data-catalog-content]')

    expect(content().attributes('data-catalog-state')).toBe('error')
    expect(content().find('[data-slot-error]').exists()).toBe(true)

    await wrapper.setProps({ state: 'loading' })
    expect(content().find('[data-slot-loading]').exists()).toBe(true)

    await wrapper.setProps({ state: 'empty' })
    expect(content().find('[data-slot-empty]').exists()).toBe(true)

    await wrapper.setProps({ state: 'ready' })
    expect(content().find('[data-slot-table]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('paints the default skeleton while loading when the consumer supplies no slot', async () => {
    const wrapper = await mountShell({ state: 'loading', loadingLabel: 'Cargando catálogo...' })

    expect(wrapper.find('[data-catalog-content]').attributes('data-catalog-state')).toBe('loading')
    expect(wrapper.find('[aria-label="Cargando catálogo..."]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('omits the KPI strip and filter bar when the consumer supplies no slot', async () => {
    const wrapper = await mountShell({}, { table: '<div data-slot-table>Filas</div>' })

    expect(wrapper.find('[data-catalog-kpis]').exists()).toBe(false)
    expect(wrapper.find('[data-catalog-filters]').exists()).toBe(false)

    wrapper.unmount()
  })
})

describe('CatalogFormModal', () => {
  const mountForm = async (props = {}, slots = {}) => {
    const wrapper = mount(CatalogFormModal, {
      props: {
        title: 'Nuevo tipo de cita',
        submitLabel: 'Crear tipo de cita',
        ...props
      },
      slots: {
        default: '<div data-slot-field>Campos</div>',
        ...slots
      },
      global: { plugins: [uiComponents] },
      attachTo: document.body
    })
    await flushPromises()
    return wrapper
  }

  it('renders the shared modal, the fields slot and the stable footer hooks', async () => {
    const wrapper = await mountForm()
    const dialog = document.querySelector('[role="dialog"]')

    expect(dialog).toBeTruthy()
    expect(dialog.textContent).toContain('Nuevo tipo de cita')
    expect(dialog.querySelector('[data-catalog-form]')).toBeTruthy()
    expect(dialog.querySelector('[data-slot-field]')).toBeTruthy()
    expect(dialog.querySelector('[data-catalog-form-error]')).toBeNull()

    const cancel = dialog.querySelector('[data-catalog-form-cancel]')
    const submit = dialog.querySelector('[data-catalog-form-submit]')
    expect(cancel.textContent.trim()).toBe('Cancelar')
    expect(submit.textContent.trim()).toBe('Crear tipo de cita')
    expect(cancel.hasAttribute('data-modal-cancel')).toBe(true)
    expect(submit.hasAttribute('data-modal-submit')).toBe(true)

    wrapper.unmount()
  })

  it('publishes the general error in its own alert region', async () => {
    const wrapper = await mountForm({ error: 'No se pudo guardar el catálogo.' })
    const alert = document.querySelector('[data-catalog-form-error]')

    expect(alert).toBeTruthy()
    expect(alert.getAttribute('role')).toBe('alert')
    expect(alert.textContent).toContain('No se pudo guardar el catálogo.')

    await wrapper.setProps({ error: '' })
    expect(document.querySelector('[data-catalog-form-error]')).toBeNull()

    wrapper.unmount()
  })

  it('reports submit and close through its own events and disables the footer while submitting', async () => {
    const wrapper = await mountForm()

    document.querySelector('[data-catalog-form-submit]').click()
    await flushPromises()
    expect(wrapper.emitted('submit')).toHaveLength(1)

    document.querySelector('[data-catalog-form-cancel]').click()
    await flushPromises()
    expect(wrapper.emitted('close')).toHaveLength(1)

    await wrapper.setProps({ submitting: true })
    expect(document.querySelector('[data-catalog-form-submit]').disabled).toBe(true)
    expect(document.querySelector('[data-catalog-form-cancel]').disabled).toBe(true)

    wrapper.unmount()
  })
})
