import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const subscriptions = { public: [], private: [] }

const recordListeners = () => {
  const listeners = []
  const chain = {
    listen: vi.fn(event => {
      listeners.push(event)
      return chain
    }),
    listeners
  }
  return chain
}

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async () => ({ data: [] }),
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
    echo: { leave: vi.fn() },
    channel: name => {
      subscriptions.public.push(name)
      return recordListeners()
    },
    privateChannel: name => {
      subscriptions.private.push({ name, listeners: recordListeners() })
      return subscriptions.private[subscriptions.private.length - 1].listeners
    },
    connectionStatus: { value: 'connected' }
  })
}))

vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/quotations/QuotationsPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

describe('quotations private channel', () => {
  beforeEach(() => {
    subscriptions.public.length = 0
    subscriptions.private.length = 0
  })

  it('subscribes to quotations through privateChannel, never through channel', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(subscriptions.public).not.toContain('quotations')

    const quotations = subscriptions.private.filter(entry => entry.name === 'quotations')
    expect(quotations).toHaveLength(1)

    wrapper.unmount()
  })

  it('keeps the four quotation listeners on the private channel', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    const quotations = subscriptions.private.find(entry => entry.name === 'quotations')
    expect(quotations).toBeDefined()
    expect(quotations.listeners.listeners).toEqual([
      '.quotation.created',
      '.quotation.updated',
      '.quotation.approved',
      '.quotation.rejected'
    ])

    wrapper.unmount()
  })
})
