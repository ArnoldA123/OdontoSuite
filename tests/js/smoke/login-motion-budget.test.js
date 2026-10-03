import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const { loginMock } = vi.hoisted(() => ({ loginMock: vi.fn() }))

vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ login: loginMock })
}))
vi.mock('@/composables/useApi', () => ({
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
vi.mock('@/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('@/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '@/plugins/ui-components'
import Page from '@/modules/auth/LoginPage.vue'
import pageSource from '@/modules/auth/LoginPage.vue?raw'

const nativeMatchMedia = window.matchMedia
const nativeRaf = globalThis.requestAnimationFrame

const MOTION_LISTENERS = [
  'pointermove',
  'pointerleave',
  'mousemove',
  'mouseenter',
  'mouseleave',
  'resize',
  'scroll'
]

function mockMedia({ reducedMotion = false } = {}) {
  const matchMedia = vi.fn(query => ({
    matches: query.includes('prefers-reduced-motion') ? reducedMotion : false,
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

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
}

function trackMotionListeners() {
  const windowSpy = vi.spyOn(window, 'addEventListener')
  const documentSpy = vi.spyOn(document, 'addEventListener')
  return () =>
    [...windowSpy.mock.calls, ...documentSpy.mock.calls]
      .map(([type]) => type)
      .filter(type => MOTION_LISTENERS.includes(type))
}

function mountPage() {
  return mount(Page, { global: { plugins: [makeRouter(), uiComponents] } })
}

function dispatchPointerMove() {
  window.dispatchEvent(new Event('pointermove'))
}

afterEach(() => {
  window.matchMedia = nativeMatchMedia
  globalThis.matchMedia = nativeMatchMedia
  globalThis.requestAnimationFrame = nativeRaf
  window.requestAnimationFrame = nativeRaf
  loginMock.mockReset()
  localStorage.clear()
  vi.restoreAllMocks()
})

describe('login motion budget under prefers-reduced-motion: reduce', () => {
  it('attaches no motion listeners and schedules no animation frame', async () => {
    mockMedia({ reducedMotion: true })
    const rafSpy = vi.fn(callback => setTimeout(() => callback(Date.now()), 16))
    globalThis.requestAnimationFrame = rafSpy
    window.requestAnimationFrame = rafSpy
    const motionListeners = trackMotionListeners()

    const wrapper = mountPage()
    try {
      await flushPromises()
      await new Promise(resolve => setTimeout(resolve, 120))

      expect(motionListeners()).toEqual([])
      expect(rafSpy).not.toHaveBeenCalled()
      expect(
        wrapper.find('.login-submit-wrap').element.style.getPropertyValue('--spring-magnet-x')
      ).toBe('')

      dispatchPointerMove()
      await flushPromises()
      expect(wrapper.find('.dental-parallax').classes()).not.toContain('is-parallax-active')
    } finally {
      wrapper.unmount()
    }
  })

  it('binds no motion directive to the submit stages', () => {
    expect(pageSource).not.toContain('v-motion')
    expect(pageSource).not.toContain('morphMotion')
  })

  it('declares the parallax wrapper as .login-backdrop', async () => {
    const wrapper = mountPage()
    try {
      await flushPromises()
      const backdrop = wrapper.find('.login-backdrop')
      expect(backdrop.exists()).toBe(true)
      expect(backdrop.attributes('aria-hidden')).toBe('true')
      expect(wrapper.find('.login-hero-column').exists()).toBe(false)
    } finally {
      wrapper.unmount()
    }
  })
})

describe('login motion budget when motion is allowed', () => {
  it('attaches the magnet and the pointer parallax', async () => {
    mockMedia({ reducedMotion: false })
    const motionListeners = trackMotionListeners()

    const wrapper = mountPage()
    try {
      await flushPromises()
      await new Promise(resolve => setTimeout(resolve, 80))

      expect(motionListeners()).toContain('pointermove')
      expect(wrapper.find('button[type="submit"]').attributes('data-magnetic')).toBe('true')
      expect(
        wrapper.find('.login-submit-wrap').element.style.getPropertyValue('--spring-magnet-x')
      ).toBe('0')

      dispatchPointerMove()
      await flushPromises()
      expect(wrapper.find('.dental-parallax').classes()).toContain('is-parallax-active')
    } finally {
      wrapper.unmount()
    }
  })
})
