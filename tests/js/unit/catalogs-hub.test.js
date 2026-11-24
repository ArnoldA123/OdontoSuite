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
import CatalogsPage from '../../../resources/js/modules/catalogs/CatalogsPage.vue'
import rolePermissions from '../support/role-permissions.json'

const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/catalogs', component: CatalogsPage },
      { path: '/specialties', component: { template: '<div />' } },
      { path: '/appointment-types', component: { template: '<div />' } },
      { path: '/procedure-catalog', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/catalogs')
  await router.isReady()

  const wrapper = mount(CatalogsPage, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const mockCounts = () => {
  getMock.mockImplementation(async url => {
    if (url === '/api/specialties') return { data: [], meta: { kpis: { active: 7 } } }
    if (url === '/api/appointment-types') return { data: [], meta: { total: 4 } }
    if (url === '/api/procedure-catalog/summary') return { data: { active: 46 } }

    return { data: [], meta: {} }
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  seedSession('administrador')
  mockCounts()
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('catalog hub', () => {
  it('renders one card per catalog with its explanation, active count and destination', async () => {
    const wrapper = await mountPage()

    const header = wrapper.find('[data-page-header]')
    expect(header.find('h1').text()).toBe('Catálogos')
    expect(header.find('[data-page-header-kicker]').text()).toBe('Administración')

    const cards = wrapper.findAll('[data-catalog-card]')
    expect(cards).toHaveLength(3)
    expect(cards.map(card => card.attributes('data-catalog-card'))).toEqual([
      'specialties',
      'appointment-types',
      'procedures'
    ])
    expect(cards.map(card => card.attributes('href'))).toEqual([
      '/specialties',
      '/appointment-types',
      '/procedure-catalog'
    ])

    const specialties = wrapper.find('[data-catalog-card="specialties"]')
    expect(specialties.text()).toContain('Especialidades')
    expect(specialties.text()).toContain('Clasificador que ordena procedimientos y profesionales.')
    expect(specialties.find('[data-catalog-card-count]').text()).toBe('Activos: 7')

    const types = wrapper.find('[data-catalog-card="appointment-types"]')
    expect(types.text()).toContain('Tipos de cita')
    expect(types.text()).toContain('Plantillas de agenda')
    expect(types.find('[data-catalog-card-count]').text()).toBe('Activos: 4')

    const procedures = wrapper.find('[data-catalog-card="procedures"]')
    expect(procedures.text()).toContain('Procedimientos')
    expect(procedures.text()).toContain('Actos clínicos y facturables')
    expect(procedures.find('[data-catalog-card-count]').text()).toBe('Activos: 46')

    expect(wrapper.find('[data-catalogs-empty]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('keeps the cards visible and prints an unknown count when an endpoint fails', async () => {
    getMock.mockRejectedValue(new Error('network down'))
    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-catalog-card]')).toHaveLength(3)
    expect(wrapper.find('[data-catalog-card="specialties"]').text()).toContain('Activos: —')

    wrapper.unmount()
  })

  it('offers no catalog door to a session without any catalog permission', async () => {
    seedSession('recepcionista')
    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-catalog-card]')).toHaveLength(0)
    const empty = wrapper.find('[data-catalogs-empty]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('No tienes catálogos a tu cargo')

    const countCalls = getMock.mock.calls
      .map(call => call[0])
      .filter(url =>
        ['/api/specialties', '/api/appointment-types', '/api/procedure-catalog/summary'].includes(
          url
        )
      )
    expect(countCalls).toEqual([])

    wrapper.unmount()
  })
})
