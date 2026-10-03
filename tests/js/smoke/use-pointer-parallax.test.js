import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { usePointerParallax } from '@/composables/usePointerParallax'

const nativeMatchMedia = window.matchMedia

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

function dispatchPointerMove(x = 900, y = 200) {
  const event = new Event('pointermove')
  event.clientX = x
  event.clientY = y
  window.dispatchEvent(event)
}

function mountParallax() {
  const target = document.createElement('div')
  const api = { current: null }
  const Host = defineComponent({
    setup() {
      api.current = usePointerParallax({ value: target }, { response: 0.45, damping: 0.85 })
      return () => h('div')
    }
  })
  const wrapper = mount(Host)
  return { api: api.current, wrapper, target }
}

afterEach(() => {
  window.matchMedia = nativeMatchMedia
  globalThis.matchMedia = nativeMatchMedia
  vi.restoreAllMocks()
  vi.useRealTimers()
})

describe('usePointerParallax activity window', () => {
  it('marks activity on pointer move and releases it after the settle window', async () => {
    mockMedia({ reducedMotion: false })
    vi.useFakeTimers()

    const { api, wrapper } = mountParallax()
    try {
      expect(api.active.value).toBe(false)

      dispatchPointerMove()
      expect(api.active.value).toBe(true)

      vi.advanceTimersByTime(1200)
      expect(api.active.value).toBe(false)
    } finally {
      wrapper.unmount()
    }
  })

  it('releases the listener and the activity window on unmount', async () => {
    mockMedia({ reducedMotion: false })
    vi.useFakeTimers()

    const { api, wrapper } = mountParallax()
    dispatchPointerMove()
    expect(api.active.value).toBe(true)

    wrapper.unmount()
    expect(api.active.value).toBe(false)

    dispatchPointerMove()
    expect(api.active.value).toBe(false)
    vi.advanceTimersByTime(5000)
    expect(api.active.value).toBe(false)
  })

  it('stays inert and attaches no listener under prefers-reduced-motion: reduce', async () => {
    mockMedia({ reducedMotion: true })

    const { api, wrapper, target } = mountParallax()
    try {
      dispatchPointerMove()
      expect(api.active.value).toBe(false)
      expect(target.style.getPropertyValue('--parallax-x')).toBe('0')
      expect(target.style.getPropertyValue('--parallax-y')).toBe('0')
    } finally {
      wrapper.unmount()
    }
  })
})
