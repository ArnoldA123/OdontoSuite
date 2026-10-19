import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Appointment-types detail redesign contract (D1-D5): hero band with the type
// name + status pill + actions, sectioned info groups (general / operation /
// price) with the model fields, audit rows as hairline-divided rows inside ONE
// surface (never one card per log row), a skeleton mirroring the final shape
// while the record loads, and the same inline error surface the list page
// uses with a retry that reloads.
const { getMock, makeType, makeLog } = vi.hoisted(() => {
  const makeType = (overrides = {}) => ({
    id: 7,
    name: 'Limpieza dental',
    description: 'Profilaxis dental completa',
    default_duration_minutes: 45,
    price: 150,
    color: '#4F46E5',
    requires_confirmation: true,
    requires_materials: false,
    is_consultation_mode: true,
    is_active: true,
    default_procedure_catalog_id: 12,
    ...overrides
  })

  const makeLog = (id, overrides = {}) => ({
    id,
    action: 'appointment_type_created',
    user: { name: 'Ana Torres' },
    created_at: '2026-01-15T09:00:00Z',
    old_values: {},
    new_values: {},
    ...overrides
  })

  const getMock = vi.fn(async () => ({ data: {} }))

  return { getMock, makeType, makeLog }
})

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
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
import Page from '../../../resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/appointment-types/:id', component: { template: '<div />' } },
    { path: '/appointment-types', component: { template: '<div />' } },
    { path: '/procedure-catalog/:id', component: { template: '<div />' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
  ]
})

const AUDIT_LOGS = [
  makeLog(1, {
    action: 'appointment_type_updated',
    old_values: { price: 100 },
    new_values: { price: 150 }
  }),
  makeLog(2, { action: 'appointment_type_created' })
]

const mountPage = async ({ type = makeType(), logs = AUDIT_LOGS } = {}) => {
  getMock.mockImplementation(async url => {
    const u = String(url)
    if (u.startsWith('/api/audit-logs/appointment-type/')) return { data: logs }
    if (u.startsWith('/api/appointment-types/')) return { data: type }
    return { data: [] }
  })
  await router.push('/appointment-types/7')
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const openAuditTab = async wrapper => {
  const auditTab = wrapper.findAll('button').find(b => b.text().includes('Historial'))
  expect(auditTab).toBeTruthy()
  await auditTab.trigger('click')
  await flushPromises()
}

describe('appointment-types detail smoke', () => {
  beforeEach(() => {
    getMock.mockReset()
    getMock.mockImplementation(async () => ({ data: {} }))
  })

  it('renders the hero band with the type name, status pill and actions', async () => {
    const wrapper = await mountPage()
    const hero = wrapper.find('[data-detail-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('[data-detail-title]').text()).toBe('Limpieza dental')
    expect(hero.find('[data-detail-status]').text()).toContain('Activo')
    expect(hero.text()).toContain('Tipos de Cita')
    expect(hero.text()).toContain('Editar')
    expect(hero.text()).toContain('Volver')
    expect(hero.text()).not.toContain('Cargando')
    wrapper.unmount()
  })

  it('renders the sectioned info groups with the model fields', async () => {
    const wrapper = await mountPage()

    const general = wrapper.find('[data-detail-group="general"]')
    expect(general.exists()).toBe(true)
    expect(general.text()).toContain('Limpieza dental')
    expect(general.text()).toContain('Profilaxis dental completa')
    expect(general.find('[data-detail-swatch]').exists()).toBe(true)
    expect(wrapper.text().match(/#4F46E5/g)).toHaveLength(1)

    const operation = wrapper.find('[data-detail-group="operation"]')
    expect(operation.text()).toContain('45 min')
    expect(wrapper.find('[data-detail-field="duration"]').classes()).toContain('tabular-nums')
    expect(wrapper.find('[data-detail-field="requires-confirmation"]').text()).toBe('Sí')
    expect(wrapper.find('[data-detail-field="requires-materials"]').text()).toBe('No')
    expect(wrapper.find('[data-detail-field="consultation-mode"]').text()).toBe('Sí')

    const price = wrapper.find('[data-detail-group="price"]')
    expect(price.text()).toMatch(/S\/\s*150\.00/)
    expect(wrapper.find('[data-detail-field="price"]').classes()).toContain('tabular-nums')

    expect(wrapper.find('[data-detail-procedure-link]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('hides the procedure catalog link when the type has no default catalog', async () => {
    const wrapper = await mountPage({ type: makeType({ default_procedure_catalog_id: null }) })
    expect(wrapper.find('[data-detail-procedure-link]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('renders a null price as No especificado in the detail price field', async () => {
    const wrapper = await mountPage({ type: makeType({ price: null }) })
    expect(wrapper.find('[data-detail-field="price"]').text()).toBe('No especificado')
    wrapper.unmount()
  })

  it('shows the hero + info skeleton while the record is pending', async () => {
    let resolveType
    getMock.mockImplementation(url => {
      const u = String(url)
      if (u.startsWith('/api/appointment-types/')) {
        return new Promise(resolve => {
          resolveType = resolve
        })
      }
      return Promise.resolve({ data: [] })
    })
    await router.push('/appointment-types/7')
    await router.isReady()
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })

    expect(wrapper.find('[data-detail-skeleton]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Limpieza dental')
    expect(wrapper.find('[data-detail-hero]').text()).not.toContain('Cargando')

    resolveType({ data: makeType() })
    await flushPromises()
    expect(wrapper.find('[data-detail-skeleton]').exists()).toBe(false)
    expect(wrapper.find('[data-detail-title]').text()).toBe('Limpieza dental')
    wrapper.unmount()
  })

  it('shows the inline error surface with retry and reloads on retry', async () => {
    let fail = true
    getMock.mockImplementation(async url => {
      const u = String(url)
      if (u.startsWith('/api/appointment-types/')) {
        if (fail) throw new Error('network down')
        return { data: makeType() }
      }
      return { data: [] }
    })
    await router.push('/appointment-types/7')
    await router.isReady()
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    const surface = wrapper.find('[data-types-error]')
    expect(surface.exists()).toBe(true)
    expect(surface.attributes('role')).toBe('alert')
    expect(wrapper.find('[data-retry-detail]').exists()).toBe(true)
    const heroText = wrapper.find('[data-detail-hero]').text()
    expect(heroText).not.toContain('Limpieza dental')
    expect(heroText).not.toContain('Cargando')

    fail = false
    await wrapper.find('[data-retry-detail]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-types-error]').exists()).toBe(false)
    expect(wrapper.find('[data-detail-title]').text()).toBe('Limpieza dental')
    wrapper.unmount()
  })

  it('renders audit rows inside a single surface with the diff color ramps', async () => {
    const wrapper = await mountPage({ logs: AUDIT_LOGS })
    await openAuditTab(wrapper)

    const rows = wrapper.findAll('[data-audit-row]')
    expect(rows).toHaveLength(2)

    // ONE surface: exactly one card-like container holds the rows, and no row
    // nests another card (the legacy one-UiCard-per-log-row is gone).
    const surfacesWithRows = wrapper
      .findAll('[data-clickable]')
      .filter(card => card.findAll('[data-audit-row]').length > 0)
    expect(surfacesWithRows).toHaveLength(1)
    rows.forEach(row => {
      expect(row.findAll('[data-clickable]').length).toBe(0)
    })

    const diffRow = rows.find(r => r.text().includes('Tipo de Cita Actualizado'))
    expect(diffRow).toBeTruthy()
    expect(diffRow.find('.text-systemRed-600').exists()).toBe(true)
    expect(diffRow.find('.text-systemGreen-600').exists()).toBe(true)
    expect(diffRow.text()).toContain('Ana Torres')
    expect(wrapper.find('[data-audit-date]').text()).toMatch(/\d/)
    expect(wrapper.html()).not.toContain('text-red-500')
    expect(wrapper.html()).not.toContain('text-green-500')
    wrapper.unmount()
  })

  it('renders the created and deleted messages when snapshots arrive as empty arrays', async () => {
    const logs = [
      makeLog(1, {
        action: 'appointment_type_created',
        old_values: [],
        new_values: []
      }),
      makeLog(2, {
        action: 'appointment_type_updated',
        old_values: { price: 100 },
        new_values: { price: 150 }
      }),
      makeLog(3, {
        action: 'appointment_type_deleted',
        old_values: [],
        new_values: []
      })
    ]
    const wrapper = await mountPage({ logs })
    await openAuditTab(wrapper)

    const rows = wrapper.findAll('[data-audit-row]')
    expect(rows).toHaveLength(3)

    const created = rows.find(r => r.text().includes('Tipo de Cita Creado'))
    expect(created).toBeTruthy()
    expect(created.text()).toContain('Tipo de cita creado en el sistema.')

    const deleted = rows.find(r => r.text().includes('Tipo de Cita Eliminado'))
    expect(deleted).toBeTruthy()
    expect(deleted.text()).toContain('Tipo de cita eliminado del sistema.')

    const updated = rows.find(r => r.text().includes('Tipo de Cita Actualizado'))
    expect(updated.text()).toContain('Cambios realizados:')
    expect(updated.find('.text-systemRed-600').exists()).toBe(true)
    expect(updated.find('.text-systemGreen-600').exists()).toBe(true)

    rows.forEach(row => expect(row.text()).not.toContain('Sin cambios registrados'))
    wrapper.unmount()
  })

  it('deep-links Editar to the list edit modal carrying the type id', async () => {
    const wrapper = await mountPage()
    await wrapper.find('[data-detail-action="edit"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/appointment-types')
    expect(String(router.currentRoute.value.query.edit)).toBe('7')
    wrapper.unmount()
  })
})
