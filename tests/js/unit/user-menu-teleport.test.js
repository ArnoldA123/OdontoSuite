import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// The user menu used to mount inside the topbar, a glass chrome element with a
// mask and its own stacking context: a `fixed z-50` child was clipped by the
// mask and painted below page content, so the session options were unreachable.
// The menu now teleports to <body> and positions itself against the trigger.
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
const TOPBAR = '[data-app-chrome="topbar"]'

const VIEWPORT_WIDTH = 1024
const VIEWPORT_HEIGHT = 768
const GAP = 8

const mounted = []

const mountShell = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(AppLayout, {
    attachTo: document.body,
    global: { plugins: [router, uiComponents] },
    slots: { default: '<div data-shell-page />' }
  })
  mounted.push(wrapper)
  await flushPromises()
  return wrapper
}

const menuOf = () => document.body.querySelector(USER_MENU)

const triggerOf = wrapper => wrapper.get(USER_MENU_TRIGGER)

// jsdom has no layout, so the trigger rect is supplied by the test. The shell
// positions the teleported menu from exactly this rect.
const setTriggerRect = (wrapper, rect) => {
  const trigger = triggerOf(wrapper).element
  trigger.getBoundingClientRect = () => ({
    x: rect.left,
    y: rect.top,
    width: rect.right - rect.left,
    height: rect.bottom - rect.top,
    top: rect.top,
    bottom: rect.bottom,
    left: rect.left,
    right: rect.right,
    toJSON: () => rect
  })
}

const openMenu = async wrapper => {
  await triggerOf(wrapper).trigger('click')
  await flushPromises()
  return menuOf()
}

const setMenuHeight = (menu, height) => {
  Object.defineProperty(menu, 'offsetHeight', { configurable: true, value: height })
}

beforeEach(() => {
  Object.defineProperty(window, 'innerWidth', { configurable: true, value: VIEWPORT_WIDTH })
  Object.defineProperty(window, 'innerHeight', { configurable: true, value: VIEWPORT_HEIGHT })
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 1, name: 'Ana Admin', email: 'ana@test.com', role: 'administrador' })
  )
})

afterEach(() => {
  while (mounted.length > 0) mounted.pop().unmount()
  localStorage.clear()
  document.body.innerHTML = ''
})

describe('user menu placement (teleported dropdown)', () => {
  it('mounts the dropdown on body, outside the topbar chrome', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 20, bottom: 52, left: 900, right: 984 })

    const menu = await openMenu(wrapper)

    expect(menu).not.toBeNull()
    expect(document.body.contains(menu)).toBe(true)
    expect(menu.closest(TOPBAR)).toBeNull()
    expect(menu.classList.contains('fixed')).toBe(true)
    expect(triggerOf(wrapper).attributes('aria-expanded')).toBe('true')
    expect(triggerOf(wrapper).attributes('aria-controls')).toBe('user-menu-dropdown')
  })

  it('paints above the chrome and page content with the top layer token', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 20, bottom: 52, left: 900, right: 984 })

    const menu = await openMenu(wrapper)

    // `z-tooltip` is the highest layer of the project scale (1070), above the
    // topbar (`z-30`), dropdowns (1000) and overlays (1050).
    expect(menu.classList.contains('z-tooltip')).toBe(true)
    expect(menu.classList.contains('z-50')).toBe(false)
  })

  it('opens below the trigger with its right edge aligned', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 20, bottom: 52, left: 900, right: 984 })

    const menu = await openMenu(wrapper)

    expect(menu.style.top).toBe(`${52 + GAP}px`)
    expect(menu.style.right).toBe(`${VIEWPORT_WIDTH - 984}px`)
    expect(menu.hidden).toBe(false)
  })

  it('opens above the trigger when it does not fit below', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 700, bottom: 732, left: 900, right: 984 })

    const menu = await openMenu(wrapper)
    setMenuHeight(menu, 240)
    window.dispatchEvent(new Event('resize'))
    await flushPromises()

    // 700 - 8 - 240: the menu's bottom edge sits one gap above the trigger.
    expect(menu.style.top).toBe(`${700 - GAP - 240}px`)
  })

  it('repositions on resize and on scroll', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 20, bottom: 52, left: 900, right: 984 })
    const menu = await openMenu(wrapper)

    expect(menu.style.top).toBe(`${52 + GAP}px`)

    setTriggerRect(wrapper, { top: 100, bottom: 132, left: 900, right: 984 })
    window.dispatchEvent(new Event('resize'))
    await flushPromises()

    expect(menu.style.top).toBe(`${132 + GAP}px`)

    setTriggerRect(wrapper, { top: 200, bottom: 232, left: 900, right: 984 })
    // A nested scroller (the shell content scrolls inside the layout) must also
    // reposition the fixed menu: the listener runs in the capture phase.
    const scroller = document.createElement('div')
    document.body.appendChild(scroller)
    scroller.dispatchEvent(new Event('scroll'))
    await flushPromises()

    expect(menu.style.top).toBe(`${232 + GAP}px`)
  })

  it('closes on outside click and stays open on clicks inside the menu', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 20, bottom: 52, left: 900, right: 984 })
    const menu = await openMenu(wrapper)

    menu.querySelector('p').click()
    await flushPromises()
    expect(menuOf()).not.toBeNull()

    document.body.click()
    await flushPromises()
    expect(menuOf()).toBeNull()
  })

  it('closes with Escape from the document and from inside the menu', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 20, bottom: 52, left: 900, right: 984 })
    const menu = await openMenu(wrapper)

    menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()
    expect(menuOf()).toBeNull()

    await openMenu(wrapper)
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()
    expect(menuOf()).toBeNull()
  })

  it('keeps keyboard access and returns focus to the trigger on Escape', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 20, bottom: 52, left: 900, right: 984 })
    const trigger = triggerOf(wrapper).element
    trigger.focus()

    const menu = await openMenu(wrapper)
    const item = menu.querySelector('button')
    expect(document.activeElement).toBe(item)

    item.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()

    expect(menuOf()).toBeNull()
    expect(document.activeElement).toBe(trigger)
  })

  it('keeps the session identity and Cerrar Sesión as the only entry', async () => {
    const wrapper = await mountShell()
    setTriggerRect(wrapper, { top: 20, bottom: 52, left: 900, right: 984 })
    const menu = await openMenu(wrapper)

    expect(menu.getAttribute('role')).toBe('menu')
    expect(menu.getAttribute('aria-label')).toBe('Menú de usuario')
    expect(menu.textContent).toContain('Ana Admin')
    expect(menu.textContent).toContain('ana@test.com')

    const items = Array.from(menu.querySelectorAll('button'))
    expect(items).toHaveLength(1)
    expect(items[0].textContent.trim()).toBe('Cerrar Sesión')
  })
})
