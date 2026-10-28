import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// Issue #249: every domain channel is private on the server, so every consumer
// must subscribe with `privateChannel` and release the wire name it actually
// joined (`private-<name>`). Laravel Echo 1.19's `leave()` does not add the
// private prefix for you: leaving the base name is a no-op and the subscription
// survives unmount.
const echoMock = vi.hoisted(() => {
  const makeChannel = () => {
    const channel = { listeners: [] }
    channel.listen = event => {
      channel.listeners.push(event)
      return channel
    }
    return channel
  }

  return {
    channel: vi.fn(() => makeChannel()),
    privateChannel: vi.fn(() => makeChannel()),
    echo: { leave: vi.fn() }
  }
})

const api = { current: null }

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      if (url === '/api/cash-register/current') return { data: api.current }
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

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: echoMock.echo,
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
import { useCashRegister } from '../../../resources/js/composables/useCashRegister'
import rolePermissions from '../support/role-permissions.json'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

// Shared fan-out: the seven channels every screen listens to through
// AppLayout. Rendered inside a probe so onMounted/onUnmounted run for real.
const FanOutProbe = defineComponent({
  setup() {
    useWebSocketNotifications()
    return () => null
  }
})

const mountFanOut = async () => {
  const wrapper = mount(FanOutProbe, { global: { plugins: [router] } })
  await flushPromises()

  return wrapper
}

const CashProbe = defineComponent({
  setup() {
    return { cash: useCashRegister() }
  },
  template: '<div />'
})

describe('domain channels are private (#249)', () => {
  beforeEach(() => {
    echoMock.channel.mockClear()
    echoMock.privateChannel.mockClear()
    echoMock.echo.leave.mockClear()
    api.current = null
    // #378: the fan-out gates each subscription by permission, so the probe
    // needs a session that holds them all to exercise the whole handshake.
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 1, role: 'administrador', permissions: rolePermissions.administrador })
    )
  })

  it('subscribes the shared fan-out through privateChannel for every domain channel', async () => {
    const wrapper = await mountFanOut()

    const subscribed = echoMock.privateChannel.mock.calls.map(([name]) => name)

    expect(subscribed).toEqual(
      expect.arrayContaining([
        'appointments',
        'treatment-plans',
        'medical-records',
        'specialty-records',
        'cash-register',
        'reminders',
        'procedure-catalog'
      ])
    )
    expect(echoMock.channel).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('leaves the private wire name of every shared subscription on unmount', async () => {
    const wrapper = await mountFanOut()

    wrapper.unmount()

    const left = echoMock.echo.leave.mock.calls.map(([name]) => name)

    expect(left).toEqual(
      expect.arrayContaining([
        'private-appointments',
        'private-treatment-plans',
        'private-medical-records',
        'private-specialty-records',
        'private-cash-register',
        'private-reminders',
        'private-procedure-catalog'
      ])
    )
    expect(left).not.toContain('appointments')
    expect(left).not.toContain('cash-register')
  })

  it('subscribes the cash composable to the private cash-register channel and leaves it', async () => {
    api.current = {
      session: { id: 9, status: 'open' },
      summary: {
        opening_amount: '0.00',
        total_income: '0.00',
        total_expenses: '0.00',
        cash_in: '0.00',
        cash_out: '0.00',
        net_flow: '0.00',
        expected_amount: '0.00'
      }
    }

    const wrapper = mount(CashProbe)
    await wrapper.vm.cash.loadCurrentSession()
    await flushPromises()

    const subscribed = echoMock.privateChannel.mock.calls.map(([name]) => name)
    expect(subscribed).toContain('cash-register')
    expect(subscribed).toContain('cash-session.9')
    expect(echoMock.channel).not.toHaveBeenCalled()

    wrapper.unmount()

    const left = echoMock.echo.leave.mock.calls.map(([name]) => name)
    expect(left).toContain('private-cash-register')
    expect(left).toContain('private-cash-session.9')
  })
})

// Static guard over the page consumers. Mounting eight pages for a one-line
// subscription assertion would pin the harness, not the contract; this asserts
// the two properties that make the handshake work: the page asks for the private
// channel, and the teardown names the wire channel.
const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..')
const JS_ROOT = join(ROOT, 'resources/js')

const CONSUMED_CHANNELS = [
  'appointments',
  'reminders',
  'medical-records',
  'specialty-records',
  'treatment-plans',
  'cash-register',
  'procedure-catalog'
]

const BACKEND_ONLY_CHANNELS = ['transactions', 'users']

const sourceFiles = () => {
  const files = []
  const walk = dir => {
    for (const entry of readdirSync(dir)) {
      const full = join(dir, entry)
      if (statSync(full).isDirectory()) walk(full)
      else if (/\.(js|vue|mjs)$/.test(entry)) files.push(full)
    }
  }
  walk(JS_ROOT)

  return files
}

const fileContents = () => sourceFiles().map(file => ({ file, source: readFileSync(file, 'utf8') }))

describe('domain channel consumers (#249)', () => {
  it('never subscribes to a domain channel through the public API', () => {
    const offenders = []

    for (const name of [...CONSUMED_CHANNELS, ...BACKEND_ONLY_CHANNELS]) {
      for (const { file, source } of fileContents()) {
        if (source.includes(`channel('${name}')`)) {
          offenders.push(`${file.replace(ROOT, '')} uses channel('${name}')`)
        }
      }
    }

    expect(offenders).toEqual([])
  })

  it('subscribes every consumed domain channel through the permission-gated helper', () => {
    const contents = fileContents()

    // #378: the shell fan-out calls `privateChannel` only inside `subscribe()`,
    // which first checks the catalog permission of the channel. Pages keep
    // calling it directly; only the shared fan-out owns a gated list.
    for (const name of CONSUMED_CHANNELS) {
      const gated = contents.some(
        ({ file, source }) =>
          file.endsWith('useWebSocketNotifications.js') && source.includes(`subscribe('${name}'`)
      )
      const direct = contents.some(({ source }) => source.includes(`privateChannel('${name}')`))

      expect(
        gated || direct,
        `No consumer subscribes to \`${name}\` through privateChannel or the gated helper.`
      ).toBe(true)
    }

    const fanOut = contents.find(({ file }) => file.endsWith('useWebSocketNotifications.js'))
    expect(fanOut, 'the shell fan-out must exist').toBeTruthy()
    expect(fanOut.source.match(/privateChannel\(/g) ?? []).toHaveLength(1)
  })

  it('destructures privateChannel from useEcho wherever it subscribes', () => {
    // A consumer that calls `privateChannel` without taking it from useEcho
    // throws a ReferenceError inside its own try/catch: the page renders and
    // silently stops receiving live updates.
    const offenders = []

    for (const { file, source } of fileContents()) {
      if (!source.includes('privateChannel(')) continue
      if (!/const\s*\{[^}]*privateChannel[^}]*\}\s*=\s*useEcho\(\)/.test(source)) {
        offenders.push(file.replace(ROOT, ''))
      }
    }

    expect(offenders).toEqual([])
  })

  it('releases only wire channel names, never the base name', () => {
    const offenders = []

    for (const { file, source } of fileContents()) {
      for (const match of source.matchAll(/leave\??\.?\s*\(\s*(['"])([^'"]+)\1/g)) {
        if (!match[2].startsWith('private-')) {
          offenders.push(`${file.replace(ROOT, '')} leaves '${match[2]}'`)
        }
      }
    }

    expect(offenders).toEqual([])
  })
})
