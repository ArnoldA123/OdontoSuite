import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Issue #183: the server now rejects unauthenticated subscribers on
// `dashboard-updates`, so the dashboard must ask for the private channel and
// leave the wire name it actually subscribed to.
const echoMock = vi.hoisted(() => {
  const channelStub = { listen: () => channelStub }
  channelStub.listen = () => channelStub
  return {
    channelStub,
    channel: vi.fn(() => channelStub),
    privateChannel: vi.fn(() => channelStub),
    echo: { leave: vi.fn() }
  }
})

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
    echo: echoMock.echo,
    channel: echoMock.channel,
    privateChannel: echoMock.privateChannel,
    connectionStatus: { value: 'connected' }
  })
}))

vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'
import rolePermissions from '../support/role-permissions.json'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

describe('dashboard private channel (#183)', () => {
  beforeEach(() => {
    localStorage.clear()
    echoMock.channel.mockClear()
    echoMock.privateChannel.mockClear()
    echoMock.echo.leave.mockClear()
  })

  it('subscribes to dashboard-updates through the private channel and leaves its wire name', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(echoMock.privateChannel).toHaveBeenCalledWith('dashboard-updates')
    expect(echoMock.channel).not.toHaveBeenCalledWith('dashboard-updates')

    wrapper.unmount()

    expect(echoMock.echo.leave).toHaveBeenCalledWith('private-dashboard-updates')
  })

  // #378: finance lands on the dashboard but cannot read the agenda, so asking
  // for the appointments channel only bought a 403 on every load.
  it('does not ask for the appointments channel when the role cannot read it', async () => {
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 1, role: 'finanzas', permissions: rolePermissions.finanzas })
    )

    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    const requested = echoMock.privateChannel.mock.calls.map(([name]) => name)

    expect(requested).toContain('dashboard-updates')
    expect(requested).toContain('patients')
    expect(requested).not.toContain('appointments')

    wrapper.unmount()
  })

  it('keeps the appointment and cash channels for the roles that hold them', async () => {
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 1, role: 'administrador', permissions: rolePermissions.administrador })
    )

    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    const requested = echoMock.privateChannel.mock.calls.map(([name]) => name)

    expect(requested).toEqual(
      expect.arrayContaining(['dashboard-updates', 'patients', 'appointments', 'cash-register'])
    )

    wrapper.unmount()
  })
})
