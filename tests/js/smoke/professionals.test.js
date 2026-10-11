import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T3 (D2/D3): the page loads ONE collection for the whole clinical team and
// derives KPIs, rendered rows, search and specialty filter from it. The useApi
// mock below is the only data boundary (same contract as the original smoke
// mount).
const { getMock, makeUser, installTeam } = vi.hoisted(() => {
  const makeUser = (id, overrides = {}) => ({
    id,
    name: `Profesional ${id}`,
    username: `prof${id}`,
    email: `prof${id}@test.com`,
    phone: '999999999',
    specialty: 'general',
    is_active: true,
    ...overrides
  })

  const getMock = vi.fn(async () => ({ data: [] }))

  const installTeam = users => {
    getMock.mockImplementation(async url => {
      if (typeof url === 'string' && url.startsWith('/api/users?role=')) {
        return { data: users }
      }
      return { data: [] }
    })
  }

  return { getMock, makeUser, installTeam }
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
import Page from '../../../resources/js/modules/professionals/ProfessionalsPage.vue'
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = async () => {
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

// 2 active / 1 inactive; specialties: orthodontics x2, endodontics x1.
const TEAM = [
  makeUser(1, {
    name: 'Ana Torres',
    username: 'atorres',
    email: 'ana@test.com',
    specialty: 'orthodontics',
    is_active: true
  }),
  makeUser(2, {
    name: 'Beto Salas',
    username: 'bsalas',
    email: 'beto@test.com',
    specialty: 'orthodontics',
    is_active: true
  }),
  makeUser(3, {
    name: 'Carla Díaz',
    username: 'cdiaz',
    email: 'carla@test.com',
    specialty: 'endodontics',
    is_active: false
  })
]

describe('professionals smoke', () => {
  beforeEach(() => {
    getMock.mockClear()
    installTeam([])
  })

  it('mounts ProfessionalsPage.vue and renders content', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('Profesionales')
    wrapper.unmount()
  })

  it('derives the four team KPIs from the single loaded collection', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()

    const strip = wrapper.find('[data-team-kpis]')
    expect(strip.findAll('[data-stat-card]')).toHaveLength(4)

    expect(strip.find('[data-stat="team-total"]').text()).toBe('3')
    expect(strip.find('[data-stat="team-active"]').text()).toBe('2')
    expect(strip.find('[data-stat="team-inactive"]').text()).toBe('1')
    expect(strip.find('[data-stat="team-specialties"]').text()).toBe('2')

    wrapper.unmount()
  })

  it('filters rendered rows client-side from the search input', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()
    const search = wrapper.find('[data-professionals-search] input')

    expect(wrapper.findAll('tbody tr')).toHaveLength(3)

    await search.setValue('ana')
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.text()).toContain('Ana Torres')

    await search.setValue('cdiaz')
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.text()).toContain('Carla Díaz')

    await search.setValue('sin coincidencias')
    expect(wrapper.findAll('tbody tr')).toHaveLength(0)

    wrapper.unmount()
  })

  it('filters rendered rows by specialty', async () => {
    installTeam(TEAM)
    const wrapper = await mountPage()

    const select = wrapper.find('[data-professionals-specialty-filter]')
    await select.find('button').trigger('click')
    const option = select.findAll('button').find(button => button.text().includes('Ortodoncia'))
    await option.trigger('click')
    await flushPromises()

    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
    expect(wrapper.text()).toContain('Ana Torres')
    expect(wrapper.text()).toContain('Beto Salas')
    expect(wrapper.text()).not.toContain('Carla')

    wrapper.unmount()
  })
})
