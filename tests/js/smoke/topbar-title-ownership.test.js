import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Issue #208 — redesigned pages render their own hero band, and the topbar
// painted the same page name again above it. The hero owns the visible title;
// the topbar keeps the document's single <h1> for the outline (visually
// hidden) plus the short description as context, and routes whose page has no
// title of its own keep the topbar title exactly as before.
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
import SpecialtiesPage from '../../../resources/js/modules/specialties/SpecialtiesPage.vue'

const topbar = wrapper => wrapper.find('[data-app-chrome="topbar"]')
const topbarHeading = wrapper => topbar(wrapper).find('h1')

const mountShell = async path => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
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

const mountSpecialties = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/specialties')
  await router.isReady()

  const wrapper = mount(SpecialtiesPage, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({
    data: [],
    meta: { kpis: { total: 0, active: 0, inactive: 0, procedures: 0 } }
  })
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role: 'administrador' }))
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('topbar title ownership (issue #208)', () => {
  const pageOwnedRoutes = [
    '/specialties',
    '/appointment-types',
    '/settings/payment-methods',
    '/settings/branches',
    '/settings/branches/3',
    '/environments',
    '/environments/3',
    '/specialty-records',
    '/treatment-plans',
    '/quotations',
    '/procedure-catalog',
    '/procedure-stats',
    '/my-procedures',
    '/reception-procedures',
    '/business-intelligence',
    '/ai-analysis',
    '/cash-register',
    '/cash-register/ready-to-bill'
  ]

  it.each(pageOwnedRoutes)('leaves the visible title to the page on %s', async path => {
    const wrapper = await mountShell(path)

    expect(topbar(wrapper).attributes('data-topbar-title-owner')).toBe('page')

    const heading = topbarHeading(wrapper)
    expect(heading.exists()).toBe(true)
    expect(heading.classes()).toContain('sr-only')

    wrapper.unmount()
  })

  const shellOwnedRoutes = [
    '/calendar',
    '/dashboard',
    '/patients',
    '/professionals',
    '/medical-records'
  ]

  it.each(shellOwnedRoutes)('keeps the topbar title visible on %s', async path => {
    const wrapper = await mountShell(path)

    expect(topbar(wrapper).attributes('data-topbar-title-owner')).toBe('topbar')

    const heading = topbarHeading(wrapper)
    expect(heading.exists()).toBe(true)
    expect(heading.classes()).not.toContain('sr-only')

    wrapper.unmount()
  })

  // Issue #243 — the detail page names the entity in its own <h1>, so the
  // topbar renders no heading at all instead of a second, hidden one.
  const detailRoutes = ['/appointment-types/7', '/procedure-catalog/12', '/professionals/7']

  it.each(detailRoutes)('renders no heading in the topbar on %s', async path => {
    const wrapper = await mountShell(path)

    expect(topbar(wrapper).attributes('data-topbar-title-owner')).toBe('page')
    expect(topbarHeading(wrapper).exists()).toBe(false)

    wrapper.unmount()
  })

  it('keeps the short description as the only topbar context on a titled page', async () => {
    const wrapper = await mountShell('/appointment-types')

    const context = topbar(wrapper).find('[data-topbar-context]')
    expect(context.exists()).toBe(true)
    expect(context.text()).toBe('Tipos de citas disponibles')
    expect(context.classes()).not.toContain('sr-only')

    wrapper.unmount()
  })

  it('renders no competing title on a real hero page and keeps one h1', async () => {
    const wrapper = await mountSpecialties()

    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(topbarHeading(wrapper).classes()).toContain('sr-only')

    const heroTitle = wrapper.find('[data-hero-title]')
    expect(heroTitle.exists()).toBe(true)
    expect(heroTitle.element.tagName).toBe('H2')
    expect(heroTitle.text()).toBe('Especialidades')
    expect(heroTitle.classes()).not.toContain('sr-only')

    wrapper.unmount()
  })
})
