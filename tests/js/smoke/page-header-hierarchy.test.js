import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Issue #164 — PageHeader pages share the app-shell heading contract: the
// topbar owns the document's single <h1>, so the page header contributes a
// section heading (h2) and no second banner landmark inside <main>.
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
import Page from '../../../resources/js/modules/environments/EnvironmentsPage.vue'

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/environments')
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: [] })
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role: 'administrador' }))
})

describe('PageHeader heading hierarchy (issue #164)', () => {
  it('keeps the topbar h1 as the only document heading and renders the page title as h2', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-app-chrome="topbar"] h1')).toHaveLength(1)
    expect(wrapper.findAll('h1')).toHaveLength(1)

    const pageTitle = wrapper.find('.page-header .page-title')
    expect(pageTitle.exists(), 'PageHeader must keep its .page-title element').toBe(true)
    expect(pageTitle.element.tagName).toBe('H2')
    expect(pageTitle.text()).toBe('Ambientes')

    wrapper.unmount()
  })

  it('declares no banner landmark from the page header', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('.page-header').attributes('role')).toBeUndefined()
    expect(wrapper.findAll('[role="banner"]')).toHaveLength(0)

    wrapper.unmount()
  })
})
