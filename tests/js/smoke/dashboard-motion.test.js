import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T4 regression coverage. The dashboard motion layer is deliberately
// moderate (soft section reveals, a first-load KPI count-up, hover lift)
// and every path collapses under prefers-reduced-motion: reduce. These
// tests pin the contract at the DOM level: the four entrance springs must
// be consumed by real template rules, the KPI counter must start at 0 and
// converge to the fixture value when motion is allowed, the reduced-motion
// path must render final values with no spring attached, and the
// quick-action surface must share the KPI elevation rung.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = (stats = {}, appointments = []) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        return { data: stats }
      }
      if (url === '/api/dashboard/appointments-today') {
        return { data: appointments }
      }
      return { data: [] }
    })
  }

  return { getMock, installPayload }
})

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

const revealBindings = [
  ['[data-reveal="greeting"]', '--spring-dash-greeting-o'],
  ['[data-reveal="kpi"]', '--spring-dash-kpi-o'],
  ['[data-reveal="empty-state"]', '--spring-dash-empty-o']
]

const nativeMatchMedia = window.matchMedia
const nativeRaf = globalThis.requestAnimationFrame
const nativeCancelRaf = globalThis.cancelAnimationFrame

let frames = []
let frameHandle = 0

function mockMedia({ reducedMotion = false, noPreference = false } = {}) {
  const matchMedia = vi.fn(query => ({
    matches: query.includes('prefers-reduced-motion: reduce')
      ? reducedMotion
      : query.includes('prefers-reduced-motion: no-preference')
        ? noPreference
        : false,
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: () => false
  }))
  window.matchMedia = matchMedia
  globalThis.matchMedia = matchMedia
  return matchMedia
}

// Deterministic frame scheduler: the spring runtime reads
// requestAnimationFrame, so the test owns both its steps and its clock.
function installManualFrames() {
  frames = []
  frameHandle = 0
  const raf = callback => {
    frameHandle += 1
    frames.push(callback)
    return frameHandle
  }
  globalThis.requestAnimationFrame = raf
  window.requestAnimationFrame = raf
  globalThis.cancelAnimationFrame = () => {}
  window.cancelAnimationFrame = () => {}
}

async function pumpFrames(count = 360) {
  let now = 0
  for (let i = 0; i < count; i++) {
    const queued = frames.splice(0)
    if (queued.length === 0) break
    now += 16
    queued.forEach(callback => callback(now))
    await Promise.resolve()
  }
  await flushPromises()
}

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const makeAppointment = id => ({
  id,
  scheduled_at: `2026-10-04T${String(8 + id).padStart(2, '0')}:30:00-05:00`,
  status: 'confirmed',
  patient: {
    id: 100 + id,
    full_name: `Paciente ${id}`,
    first_name: 'Paciente',
    last_name: `${id}`
  },
  appointment_type: { id: 3, name: 'Limpieza dental' },
  user: { id: 2, name: 'Dra. Carla Núñez' }
})

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
  return wrapper
}

const kpiNumber = (wrapper, statKey) =>
  wrapper.find(`[data-stat-card="${statKey}"] p.tabular-nums`).text()

describe('dashboard motion (T4)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  afterEach(() => {
    window.matchMedia = nativeMatchMedia
    globalThis.matchMedia = nativeMatchMedia
    globalThis.requestAnimationFrame = nativeRaf
    window.requestAnimationFrame = nativeRaf
    globalThis.cancelAnimationFrame = nativeCancelRaf
    window.cancelAnimationFrame = nativeCancelRaf
    localStorage.clear()
    vi.restoreAllMocks()
  })

  it('consumes each entrance spring var in a real template style rule', async () => {
    mockMedia()
    const wrapper = await mountDashboard()

    for (const [selector, cssVar] of revealBindings) {
      const element = wrapper.find(selector)
      expect(element.exists()).toBe(true)
      expect(element.attributes('style')).toContain(`var(${cssVar}, 1)`)
    }

    wrapper.unmount()
  })

  it('counts KPI numbers up from 0 on first load only', async () => {
    mockMedia({ noPreference: true })
    installManualFrames()
    installPayload({}, [makeAppointment(1), makeAppointment(2), makeAppointment(3)])

    const wrapper = await mountDashboard()

    expect(kpiNumber(wrapper, 'appointments-today')).toBe('0')
    expect(kpiNumber(wrapper, 'pending-today')).toBe('0')

    await pumpFrames()

    expect(kpiNumber(wrapper, 'appointments-today')).toBe('3')
    expect(kpiNumber(wrapper, 'pending-today')).toBe('3')

    // A refresh updates the number instantly; the count-up never replays.
    installPayload({}, [makeAppointment(9)])
    await wrapper.find('[data-refresh-button]').trigger('click')
    await flushPromises()
    await flushPromises()
    expect(kpiNumber(wrapper, 'appointments-today')).toBe('1')

    wrapper.unmount()
  })

  it('renders final values with no spring attached under reduced motion', async () => {
    mockMedia({ reducedMotion: true })
    installManualFrames()
    installPayload({}, [makeAppointment(1), makeAppointment(2)])

    const wrapper = await mountDashboard()

    expect(kpiNumber(wrapper, 'appointments-today')).toBe('2')

    const header = wrapper.find('[data-reveal="greeting"]')
    expect(header.attributes('style')).toContain('var(--spring-dash-greeting-o, 1)')
    expect(header.element.style.getPropertyValue('--spring-dash-greeting-o')).toBe('')

    wrapper.unmount()
  })

  it('keeps the refresh spinner gated by motion-reduce', async () => {
    mockMedia()
    const wrapper = await mountDashboard()

    expect(pageSource).toContain('motion-reduce:animate-none')
    expect(wrapper.find('[data-refresh-button]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('shares the KPI elevation rung while the KPI strip stays static (T3)', async () => {
    mockMedia()
    installPayload({}, [makeAppointment(1)])

    const wrapper = await mountDashboard()

    // T3 — the KPI cards keep their elevation but are a static reference
    // surface: no hover lift, no click affordance.
    const kpi = wrapper.find('[data-stat-card="appointments-today"]')
    expect(kpi.attributes('data-hover')).toBeUndefined()
    expect(kpi.attributes('style')).toContain('var(--elevation-2)')

    const row = wrapper.find('[data-appointment-row]')
    expect(row.exists()).toBe(true)
    expect(row.attributes('data-hover')).toBe('true')

    wrapper.unmount()
  })
})
