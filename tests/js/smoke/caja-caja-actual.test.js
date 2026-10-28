import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Caja > "mi caja" hero contract (#407, S4-S7). `GET /api/cash-register/current`
// answers the actor's own drawer: no session, the open one, or the last one when
// it is closed. The three hero states are pinned here because the screen must
// never call a foreign drawer its own:
//  - no session      -> "Sin sesión", four KPIs N/D, only "Abrir caja";
//  - open session    -> "Sesión abierta", formatted KPIs, close and deposit;
//  - closed session  -> "Sesión cerrada", the closing counts, "Abrir caja" back
//                       and no close/deposit action.
const harness = vi.hoisted(() => ({ apiGet: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    download: async () => new Blob([]),
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
      privateChannel: chainable,
      connectionStatus: { value: 'connected' }
    })
  }
})
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/cash-register/CashRegisterPage.vue'

const OPEN_SESSION = {
  id: 7,
  user_id: 3,
  status: 'open',
  opening_amount: 100,
  opened_at: '2026-10-21T13:00:00.000000Z'
}

const SUMMARY = {
  opening_amount: 100,
  total_income: 320,
  total_expenses: 70,
  expected_amount: 250,
  transactions_count: 7,
  movements_count: 3
}

const CLOSED_SESSION = {
  id: 9,
  user_id: 3,
  status: 'closed',
  opening_amount: 100,
  closing_amount: 180.5,
  expected_amount: 170,
  difference_amount: 10.5,
  closed_at: '2026-10-21T23:30:00.000000Z',
  closing_notes: 'Cierre del turno'
}

const CLOSING = {
  closing_amount: 180.5,
  expected_amount: 170,
  difference_amount: 10.5,
  closed_at: '2026-10-21T23:30:00.000000Z',
  closing_notes: 'Cierre del turno'
}

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }

const installCurrent = payload => {
  harness.apiGet.mockImplementation(async url => {
    if (url === '/api/cash-register/current') return { data: payload }
    if (url === '/api/cash-register/sessions') return emptyPage
    if (url === '/api/transactions') return emptyPage
    return { data: [] }
  })
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/cash-register')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const normalize = value => (value || '').replace(/\u00a0/g, ' ')
const heroState = wrapper => normalize(wrapper.find('[data-hero-state]').text())
const kpiValues = wrapper => wrapper.findAll('[data-kpi-value]').map(node => normalize(node.text()))
const requestedUrls = () => harness.apiGet.mock.calls.map(([url]) => String(url))

beforeEach(() => {
  harness.apiGet.mockReset()
  // Deposit is the only hero action behind a permission (#320/#407).
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({
      id: 3,
      name: 'Ana Admin',
      role: 'administrador',
      permissions: ['manage.cash_deposit']
    })
  )
  installCurrent(null)
})

afterEach(() => {
  localStorage.clear()
})

describe('Caja > la caja actual es la del actor (#407)', () => {
  it('says Sin sesión and offers Abrir caja when the endpoint answers no session', async () => {
    const wrapper = await mountPage()

    expect(heroState(wrapper)).toBe('Sin sesión')
    expect(kpiValues(wrapper)).toEqual(['N/D', 'N/D', 'N/D', 'N/D'])
    expect(wrapper.find('[data-hero-action="open"]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-action="close"]').exists()).toBe(false)
    expect(wrapper.find('[data-hero-action="deposit"]').exists()).toBe(false)
    expect(wrapper.find('[data-caja-closing]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('says Sesión abierta with the four formatted KPIs and the close/deposit actions', async () => {
    installCurrent({ session: OPEN_SESSION, summary: SUMMARY })

    const wrapper = await mountPage()

    expect(heroState(wrapper)).toBe('Sesión abierta')
    expect(kpiValues(wrapper)).toEqual(['S/ 100.00', 'S/ 320.00', 'S/ 70.00', 'S/ 250.00'])
    expect(wrapper.find('[data-hero-action="open"]').exists()).toBe(false)
    expect(wrapper.find('[data-hero-action="close"]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-action="deposit"]').exists()).toBe(true)
    expect(wrapper.find('[data-caja-closing]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('says Sesión cerrada with the closing counts, offers Abrir caja and no close/deposit', async () => {
    installCurrent({ session: CLOSED_SESSION, closing: CLOSING })

    const wrapper = await mountPage()

    expect(heroState(wrapper)).toBe('Sesión cerrada')
    expect(kpiValues(wrapper)).toEqual(['N/D', 'N/D', 'N/D', 'N/D'])

    const closing = wrapper.find('[data-caja-closing]')
    expect(closing.exists()).toBe(true)
    expect(normalize(closing.find('[data-caja-closing-amount]').text())).toContain('S/ 180.50')
    expect(normalize(closing.find('[data-caja-closing-expected]').text())).toContain('S/ 170.00')
    expect(normalize(closing.find('[data-caja-closing-difference]').text())).toContain('S/ 10.50')
    expect(normalize(closing.find('[data-caja-closing-closed-at]').text())).not.toBe('N/D')

    expect(wrapper.find('[data-hero-action="open"]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-action="close"]').exists()).toBe(false)
    expect(wrapper.find('[data-hero-action="deposit"]').exists()).toBe(false)

    // A closed session is history: its transactions, movements and deposits
    // are not this screen's to load.
    expect(requestedUrls()).not.toContain('/api/transactions')
    expect(requestedUrls()).not.toContain('/api/cash-register/sessions/9/movements')
    expect(requestedUrls()).not.toContain('/api/cash-register/deposits')

    wrapper.unmount()
  })
})
