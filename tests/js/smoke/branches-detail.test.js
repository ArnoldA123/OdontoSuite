import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

// Branch detail redesign contract: the same hero band recipe as the list, a
// summary card with the shared status vocabulary, the three definition groups
// (location / contact / operation) plus the description, loading and inline
// error states, and the edit action that hands the branch back to the list's
// shared edit modal through ?edit=<id>.
const { getMock } = vi.hoisted(() => ({
  getMock: vi.fn()
}))

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
import Page from '../../../resources/js/modules/settings/branches/BranchDetailPage.vue'

const BRANCH = {
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
}

const makeRouter = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/settings/branches', component: { template: '<div />' } },
      { path: '/settings/branches/:id', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/settings/branches/1')
  await router.isReady()
  return router
}

const mountPage = async (branch = BRANCH) => {
  getMock.mockImplementation(async url => {
    if (String(url) === '/api/branches/1') {
      return { data: branch, meta: { message: 'Sucursal obtenida exitosamente' } }
    }
    return { data: null }
  })
  const router = await makeRouter()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return { wrapper, router }
}

const field = (wrapper, name) => wrapper.find(`[data-detail-field="${name}"]`)

describe('branch detail smoke', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    getMock.mockReset()
  })

  it('renders the hero band with the branch identity and status', async () => {
    const { wrapper } = await mountPage()

    const hero = wrapper.find('[data-branch-detail-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('[data-hero-title]').text()).toBe('Sede Central Lima')
    expect(hero.find('[data-hero-caption]').text()).toContain('SC-LIM-01')
    expect(hero.text()).toContain('Activa')
    expect(hero.find('[data-branch-detail-edit]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('renders location, contact, operation and description groups', async () => {
    const { wrapper } = await mountPage()

    expect(field(wrapper, 'address').text()).toContain('Av. Arequipa 123')
    expect(field(wrapper, 'city').text()).toContain('Lima')
    expect(field(wrapper, 'state').text()).toContain('Lima')
    expect(field(wrapper, 'country').text()).toContain('Perú')
    expect(field(wrapper, 'postal_code').text()).toContain('15046')
    expect(field(wrapper, 'phone').text()).toContain('+51 1 426-0001')
    expect(field(wrapper, 'email').text()).toContain('central@odontosuite.pe')
    expect(field(wrapper, 'timezone').text()).toContain('America/Lima')
    expect(field(wrapper, 'coordinates').text()).toContain('-12.05000000')
    expect(field(wrapper, 'description').text()).toContain('Sede principal de la clínica.')
    expect(field(wrapper, 'created_at').text().length).toBeGreaterThan(0)

    wrapper.unmount()
  })

  it('falls back to explicit placeholders for missing optional data', async () => {
    const { wrapper } = await mountPage({
      ...BRANCH,
      phone: null,
      email: null,
      latitude: null,
      longitude: null,
      description: ''
    })

    expect(field(wrapper, 'phone').text()).toContain('No registrado')
    expect(field(wrapper, 'email').text()).toContain('No registrado')
    expect(field(wrapper, 'coordinates').text()).toContain('No registradas')
    expect(field(wrapper, 'description').text()).toContain('Sin descripción')

    wrapper.unmount()
  })

  it('shows the loading skeleton before the branch resolves', async () => {
    let resolveRequest
    getMock.mockImplementation(
      () =>
        new Promise(resolve => {
          resolveRequest = resolve
        })
    )
    const router = await makeRouter()
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await nextTick()

    expect(wrapper.find('[data-branch-detail-loading]').exists()).toBe(true)

    resolveRequest({ data: BRANCH })
    await flushPromises()

    expect(wrapper.find('[data-branch-detail-loading]').exists()).toBe(false)
    expect(wrapper.find('[data-branch-detail-hero]').text()).toContain('Sede Central Lima')

    wrapper.unmount()
  })

  it('renders the inline error surface with retry when the request fails', async () => {
    getMock.mockRejectedValueOnce(new Error('network down'))
    getMock.mockImplementation(async () => ({ data: BRANCH }))
    const router = await makeRouter()
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(wrapper.find('[data-branch-detail-error]').exists()).toBe(true)
    expect(wrapper.find('[data-retry-branch-detail]').exists()).toBe(true)

    await wrapper.find('[data-retry-branch-detail]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-branch-detail-error]').exists()).toBe(false)
    expect(wrapper.find('[data-branch-detail-hero]').text()).toContain('Sede Central Lima')

    wrapper.unmount()
  })

  it('sends the user back to the list edit modal through ?edit=<id>', async () => {
    const { wrapper, router } = await mountPage()

    await wrapper.find('[data-branch-detail-edit]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/settings/branches')
    expect(router.currentRoute.value.query.edit).toBe('1')

    wrapper.unmount()
  })
})
