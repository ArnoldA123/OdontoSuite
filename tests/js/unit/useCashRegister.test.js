import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'

// Responses are swapped per test: the composable is the contract layer between
// the cash API payload and every Caja screen, so the payloads it receives here
// mirror the ones CashRegisterController returns.
const api = {
  current: null,
  sessions: null,
  failure: null,
  lastParams: null
}

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async (url, options = {}) => {
      if (api.failure) throw new Error(api.failure)
      api.lastParams = options.params ?? null
      if (url === '/api/cash-register/current') return { data: api.current }
      if (url === '/api/cash-register/sessions') {
        return { data: api.sessions.data, meta: api.sessions.meta }
      }
      return { data: [] }
    },
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
      privateChannel: chainable,
      connectionStatus: { value: 'connected' }
    })
  }
})

import { useCashRegister } from '../../../resources/js/composables/useCashRegister'

const mountCashRegister = () => {
  const Probe = defineComponent({
    setup() {
      return { cash: useCashRegister() }
    },
    template: '<div />'
  })

  return mount(Probe)
}

describe('useCashRegister contract', () => {
  beforeEach(() => {
    api.current = null
    api.sessions = { data: [], meta: null }
    api.failure = null
    api.lastParams = null
  })

  it('normalizes the summary and derives the balance from the arqueo', async () => {
    api.current = {
      session: { id: 1, status: 'open' },
      summary: {
        opening_amount: '100.00',
        total_income: '50.00',
        total_expenses: '0.00',
        cash_in: '75.00',
        cash_out: '15.00',
        net_flow: '60.00',
        expected_amount: '160.00',
        transactions_count: '1',
        movements_count: '4',
        by_payment_method: [{ payment_method: 'Efectivo', count: 1, total: 50 }],
        by_hour: []
      }
    }

    const wrapper = mountCashRegister()
    await wrapper.vm.cash.loadCurrentSession()

    expect(wrapper.vm.cash.summary.value.cash_in).toBe(75)
    expect(wrapper.vm.cash.summary.value.by_payment_method).toHaveLength(1)
    expect(wrapper.vm.cash.summary.value.by_hour).toEqual([])
    expect(wrapper.vm.cash.realTimeTotals.value.cashOut).toBe(15)
    expect(
      wrapper.vm.cash.realTimeTotals.value.currentBalance,
      'the balance shown must be the position the drawer will be counted against'
    ).toBe(160)
  })

  it('returns the sessions envelope with its pagination meta and passes filters', async () => {
    api.sessions = {
      data: [{ id: 7 }],
      meta: { current_page: 2, last_page: 3, per_page: 15, total: 31 }
    }

    const wrapper = mountCashRegister()
    const result = await wrapper.vm.cash.getSessions({ status: 'closed', page: 2 })

    expect(result.data).toEqual([{ id: 7 }])
    expect(result.meta.total).toBe(31)
    expect(api.lastParams).toEqual({ status: 'closed', page: 2 })
  })

  it('surfaces a failed session load instead of painting an empty session', async () => {
    api.failure = 'Caja no disponible'

    const wrapper = mountCashRegister()
    await wrapper.vm.cash.loadCurrentSession()

    expect(wrapper.vm.cash.error.value).toBe('Caja no disponible')
    expect(wrapper.vm.cash.summary.value).toBeNull()
    expect(wrapper.vm.cash.hasActiveSession.value).toBe(false)
  })
})
