import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Issue #380 — the user menu has no no-op entries. The shell has no profile
// surface, no settings surface reachable from the menu and no appearance
// control, so the menu offers the session identity and "Cerrar Sesión" and
// nothing else. A visible button that opens nothing is worse than a missing
// one, so the guard fails if one comes back.
vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
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
    echo: { leave: () => {} },
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

const USER_MENU_TRIGGER = '[aria-haspopup="menu"]'
const USER_MENU = '#user-menu-dropdown'
const REMOVED_LABELS = ['Perfil', 'Configuración', 'Apariencia']

const mountShell = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(AppLayout, {
    global: { plugins: [router, uiComponents] },
    slots: { default: '<div data-shell-page />' }
  })
  await flushPromises()
  return wrapper
}

const openMenu = async wrapper => {
  await wrapper.get(USER_MENU_TRIGGER).trigger('click')
  await flushPromises()
  return wrapper.get(USER_MENU)
}

beforeEach(() => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 1, name: 'Ana Admin', email: 'ana@test.com', role: 'administrador' })
  )
})

afterEach(() => {
  localStorage.clear()
  document.body.innerHTML = ''
})

describe('user menu contract (issue #380)', () => {
  it('opens from the trigger and lists exactly one menu item: Cerrar Sesión', async () => {
    const wrapper = await mountShell()

    expect(wrapper.find(USER_MENU).exists()).toBe(false)

    const menu = await openMenu(wrapper)

    expect(menu.attributes('role')).toBe('menu')
    expect(menu.attributes('aria-label')).toBe('Menú de usuario')

    const items = menu.findAll('button')
    expect(items).toHaveLength(1)
    expect(items[0].text()).toBe('Cerrar Sesión')

    wrapper.unmount()
  })

  it('keeps the identity block as the menu header', async () => {
    const wrapper = await mountShell()
    const menu = await openMenu(wrapper)

    expect(menu.text()).toContain('Ana Admin')
    expect(menu.text()).toContain('ana@test.com')

    wrapper.unmount()
  })

  it('paints no profile, settings or appearance entry', async () => {
    const wrapper = await mountShell()
    const menu = await openMenu(wrapper)

    for (const label of REMOVED_LABELS) {
      expect(menu.text()).not.toContain(label)
      expect(wrapper.text()).not.toContain(label)
    }

    wrapper.unmount()
  })

  it('closes with Escape', async () => {
    const wrapper = await mountShell()
    await openMenu(wrapper)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()

    expect(wrapper.find(USER_MENU).exists()).toBe(false)

    wrapper.unmount()
  })
})
