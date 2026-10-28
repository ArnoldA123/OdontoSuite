import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

// #378/#384 — the shell used to ask for nine private channels on every page and
// collected a 403 per channel the role could not authorize. The fan-out now
// gates each subscription with the permission the server uses for the same
// channel, so a role only opens the doors it can enter.
const echoMock = vi.hoisted(() => {
  const makeChannel = () => {
    const channel = { listen: vi.fn(() => channel) }
    return channel
  }

  return { privateChannel: vi.fn(() => makeChannel()), channel: vi.fn(() => makeChannel()) }
})

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: vi.fn() },
    channel: echoMock.channel,
    privateChannel: echoMock.privateChannel,
    connectionStatus: { value: 'connected' }
  })
}))

vi.mock('../../../resources/js/composables/useNotifications', () => ({
  useNotifications: () => ({ addNotification: vi.fn() })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => ({ error: vi.fn(), success: vi.fn(), info: vi.fn() })
}))

import { useWebSocketNotifications } from '../../../resources/js/composables/useWebSocketNotifications'
import rolePermissions from '../support/role-permissions.json'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

// A probe mounts the composable so `onMounted` runs for real; the returned list
// is what the shell would ask the server to authorize.
const Probe = defineComponent({
  setup() {
    useWebSocketNotifications()
    return () => null
  }
})

async function subscribeAs(permissions) {
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Audit', role: 'audit', permissions }))

  const wrapper = mount(Probe, { global: { plugins: [router] } })
  await flushPromises()

  const subscribed = echoMock.privateChannel.mock.calls.map(([name]) => name)
  wrapper.unmount()

  return subscribed
}

beforeEach(() => {
  localStorage.clear()
  echoMock.privateChannel.mockClear()
  echoMock.channel.mockClear()
})

describe('the shell subscribes only to the channels its session can authorize (#378/#384)', () => {
  // The measured denials of the audit (U0 F1 / U1 F1) are the expectation:
  // the union of role grants yields exactly these channels per role.
  const expected = {
    administrador: [
      'appointments',
      'patients',
      'treatment-plans',
      'quotations',
      'medical-records',
      'specialty-records',
      'cash-register',
      'reminders',
      'procedure-catalog'
    ],
    recepcionista: [
      'appointments',
      'patients',
      'quotations',
      'cash-register',
      'reminders',
      'procedure-catalog'
    ],
    odontologo: [
      'appointments',
      'patients',
      'treatment-plans',
      'quotations',
      'medical-records',
      'specialty-records',
      'reminders',
      'procedure-catalog'
    ],
    implantologo: [
      'appointments',
      'patients',
      'treatment-plans',
      'quotations',
      'medical-records',
      'specialty-records',
      'reminders',
      'procedure-catalog'
    ],
    tecnico_dental: [
      'appointments',
      'patients',
      'treatment-plans',
      'reminders',
      'procedure-catalog'
    ],
    asistente: ['appointments', 'patients', 'medical-records', 'reminders', 'procedure-catalog'],
    finanzas: ['patients', 'quotations', 'cash-register', 'procedure-catalog']
  }

  it.each(Object.entries(expected))(
    'suscita los canales de %s y ninguno más',
    async (role, channels) => {
      const subscribed = await subscribeAs(rolePermissions[role])

      expect(subscribed).toEqual(channels)
    }
  )

  it('does not ask for any handshake when the session holds no permission', async () => {
    const subscribed = await subscribeAs([])

    expect(subscribed).toEqual([])
  })

  it('never falls back to a public channel', async () => {
    await subscribeAs(rolePermissions.administrador)

    expect(echoMock.channel).not.toHaveBeenCalled()
  })
})
