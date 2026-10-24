import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #345 - the cash register is a permission-gated surface. A role without
// view.cash_register must not ask for the session (the API answers 403 by
// design) and must not subscribe to the channel that publishes its money.
// The three cash desks keep the same session and totals they had before.
const harness = vi.hoisted(() => {
  const get = vi.fn()
  const privateChannel = vi.fn()
  const urls = []
  const channels = []

  return { get, privateChannel, urls, channels }
})

const cashCurrent = {
  session: { id: 7, status: 'open', opened_at: '2026-10-04T08:30:00' },
  summary: {
    opening_amount: '100.00',
    total_income: '200.00',
    total_expenses: '20.00',
    cash_in: '80.00',
    cash_out: '20.00',
    net_flow: '60.00',
    expected_amount: '280.00',
    transactions_count: '2',
    movements_count: '3',
    by_payment_method: [],
    by_hour: []
  }
}

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.get,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

vi.mock('../../../resources/js/composables/useEcho', () => {
  const chainable = () => {
    const channel = { listen: () => channel }
    return channel
  }

  return {
    useEcho: () => ({
      echo: {},
      channel: chainable,
      privateChannel: name => {
        harness.channels.push(name)
        return chainable()
      },
      connectionStatus: { value: 'connected' }
    })
  }
})

vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'

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
  return wrapper
}

const normalize = value => value.replace(/\u00a0/g, ' ')

describe('dashboard cash surface authorization (#345)', () => {
  beforeEach(() => {
    harness.get.mockReset()
    harness.privateChannel.mockClear()
    harness.urls.length = 0
    harness.channels.length = 0

    harness.get.mockImplementation(async url => {
      harness.urls.push(url)
      if (url === '/api/cash-register/current') {
        return { data: cashCurrent }
      }
      if (url === '/api/dashboard/stats') {
        return { data: {} }
      }
      return { data: [] }
    })
  })

  it.each(['odontologo', 'tecnico_dental'])(
    'an %s desk never asks for the cash session or its channel',
    async role => {
      signInAs(role)

      const wrapper = await mountDashboard()

      expect(harness.urls).not.toContain('/api/cash-register/current')
      expect(harness.channels).not.toContain('cash-register')
      expect(wrapper.find('[data-stat-card="cash-balance"]').exists()).toBe(false)

      wrapper.unmount()
    }
  )

  it.each(['recepcionista', 'finanzas', 'administrador'])(
    'a %s desk still loads the session and renders its totals',
    async role => {
      signInAs(role)

      const wrapper = await mountDashboard()

      expect(harness.urls.filter(url => url === '/api/cash-register/current')).toHaveLength(1)
      expect(harness.channels).toContain('cash-register')

      const card = wrapper.find('[data-stat-card="cash-balance"]')
      expect(card.exists()).toBe(true)
      expect(card.find('[data-cash-state]').text()).toBe('Abierta')
      expect(normalize(card.find('[data-stat="cash-balance"]').text())).toBe('S/ 280.00')
      expect(card.find('[data-kpi-caption="cash-balance"]').text()).toBe('Apertura 08:30')

      wrapper.unmount()
    }
  )
})
