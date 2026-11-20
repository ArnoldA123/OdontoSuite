import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T5 regression coverage adapted to U2: the dashboard no longer owns
// per-section states. The shared section-error/section-empty patterns left
// with the three retired blocks; the page-level summary error is the only
// error surface left, and the hero + KPI anatomy survive unchanged.
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

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
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'
import pageSource from '../../../resources/js/modules/dashboard/DashboardPage.vue?raw'

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const mountDashboard = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return { wrapper, router }
}

describe('dashboard unified states (U2)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 2 } }
      if (url === '/api/cash-register/current') {
        return {
          data: {
            session: { id: 7, status: 'open', opened_at: '2026-10-04T08:30:00' },
            summary: { opening_amount: 100, expected_amount: 280 }
          }
        }
      }
      return { data: [] }
    })
  })

  it('renders the summary error as the only error surface of the page', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') throw { status: 500 }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const errors = wrapper.findAll('[role="alert"]')
    expect(errors).toHaveLength(1)
    expect(errors[0].attributes('data-state')).toBe('error-stats')

    // The retired per-section surfaces are gone with their blocks.
    expect(wrapper.findAll('[data-section-error]')).toHaveLength(0)
    expect(wrapper.findAll('[data-section-empty]')).toHaveLength(0)
    expect(wrapper.findAll('[data-state^="error-"]')).toHaveLength(1)

    wrapper.unmount()
  })

  it('renders no shared section-empty state anywhere', async () => {
    const { wrapper } = await mountDashboard()

    expect(wrapper.findAll('[data-section-empty]')).toHaveLength(0)
    expect(pageSource).not.toContain('<DashboardSectionEmpty')
    expect(pageSource).not.toContain('<DashboardSectionError')

    wrapper.unmount()
  })

  it('keeps hero actions in one top cluster and the KPI cards equal-height', async () => {
    const { wrapper } = await mountDashboard()

    // The refresh lives in the shared header actions cluster (no orphan wrap
    // at 390px) and every KPI card shares the equal-height anatomy.
    const header = wrapper.find('[data-page-header]')
    const actions = header.find('[data-page-header-actions]')
    expect(actions.exists()).toBe(true)
    expect(actions.find('[data-refresh-button]').exists()).toBe(true)

    // The header carries no illustration: the empty states keep the motif.
    expect(header.find('[data-hero-motif]').exists()).toBe(false)
    expect(header.findAll('img')).toHaveLength(0)

    const cards = wrapper.findAll('[data-stat-card]')
    expect(cards).toHaveLength(2)
    for (const card of cards) {
      expect(card.classes()).toContain('h-full')
      expect(card.classes()).toContain('flex-col')
    }

    wrapper.unmount()
  })

  it('keeps one caption per card and no em dash fallback in the source', async () => {
    const { wrapper } = await mountDashboard()

    for (const card of wrapper.findAll('[data-stat-card]')) {
      expect(card.findAll('[data-kpi-caption]')).toHaveLength(1)
    }
    expect(pageSource).not.toContain('\u2014')

    wrapper.unmount()
  })
})
