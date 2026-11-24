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
import AppLayout from '../../../resources/js/components/layout/AppLayout.vue'
import rolePermissions from '../support/role-permissions.json'

const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

const mountShell = async path => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/catalogs', component: { template: '<div />' } },
      { path: '/specialties', component: { template: '<div />' } },
      { path: '/appointment-types', component: { template: '<div />' } },
      { path: '/procedure-catalog', component: { template: '<div />' } },
      { path: '/reception-procedures', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push(path)
  await router.isReady()

  const wrapper = mount(AppLayout, {
    global: { plugins: [router, uiComponents] },
    slots: { default: '<div data-shell-page />' }
  })
  await flushPromises()
  return wrapper
}

const sidebar = wrapper => wrapper.find('[data-app-chrome="sidebar"]')

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  seedSession('administrador')
  getMock.mockResolvedValue({ data: [], meta: {} })
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('catalog hub navigation', () => {
  it('offers one «Catálogos» entry instead of the three catalog siblings', async () => {
    const wrapper = await mountShell('/dashboard')
    const nav = sidebar(wrapper)

    const hub = nav.find('a[href="/catalogs"]')
    expect(hub.exists()).toBe(true)
    expect(hub.text()).toContain('Catálogos')

    expect(nav.find('a[href="/specialties"]').exists()).toBe(false)
    expect(nav.find('a[href="/appointment-types"]').exists()).toBe(false)
    expect(nav.find('a[href="/procedure-catalog"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('keeps the reception catalog door untouched', async () => {
    seedSession('recepcionista')
    const wrapper = await mountShell('/dashboard')
    const nav = sidebar(wrapper)

    expect(nav.find('a[href="/catalogs"]').exists()).toBe(false)
    expect(nav.find('a[href="/reception-procedures"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('marks the hub active and lets the page own its topbar title', async () => {
    const wrapper = await mountShell('/catalogs')

    const hub = sidebar(wrapper).find('a[href="/catalogs"]')
    expect(hub.classes()).toContain('bg-systemBlue-50')

    const topbar = wrapper.find('[data-app-chrome="topbar"]')
    expect(topbar.attributes('data-topbar-title-owner')).toBe('page')
    expect(topbar.find('h1').classes()).toContain('sr-only')
    expect(topbar.find('h1').text()).toBe('Catálogos')
    expect(topbar.find('[data-topbar-context]').text()).toBe('Catálogos maestros de la clínica')

    wrapper.unmount()
  })
})
