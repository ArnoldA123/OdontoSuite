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

function dispatchPointer(type, { x = 0, y = 0, pointerType, pointerId } = {}) {
  const event = new Event(type)
  event.clientX = x
  event.clientY = y
  if (pointerType !== undefined) event.pointerType = pointerType
  if (pointerId !== undefined) event.pointerId = pointerId
  window.dispatchEvent(event)
}

function dispatchPointerMove(x = 900, y = 200) {
  dispatchPointer('pointermove', { x, y })
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

describe('usePointerParallax pointer coverage', () => {
  it('tracks pointermove for mouse and pen without a press', () => {
    mockMedia({ reducedMotion: false })

    const { api, wrapper } = mountParallax()
    try {
      dispatchPointer('pointermove', { x: 768, y: 384, pointerType: 'mouse' })
      expect(api.x.target.value).toBeCloseTo(0.5, 5)
      expect(api.y.target.value).toBeCloseTo(0, 5)

      dispatchPointer('pointermove', { x: 256, y: 576, pointerType: 'pen' })
      expect(api.x.target.value).toBeCloseTo(-0.5, 5)
      expect(api.y.target.value).toBeCloseTo(0.5, 5)
    } finally {
      wrapper.unmount()
    }
  })

  it('tracks a touch drag once the finger leaves the tap slop', () => {
    mockMedia({ reducedMotion: false })

    const { api, wrapper } = mountParallax()
    try {
      dispatchPointer('pointerdown', { x: 100, y: 100, pointerType: 'touch', pointerId: 7 })
      dispatchPointer('pointermove', { x: 900, y: 200, pointerType: 'touch', pointerId: 7 })

      expect(api.x.target.value).toBeGreaterThan(0)
      expect(api.y.target.value).toBeLessThan(0)
      expect(api.active.value).toBe(true)

      dispatchPointer('pointerup', { x: 900, y: 200, pointerType: 'touch', pointerId: 7 })
    } finally {
      wrapper.unmount()
    }
  })

  it('leaves the target untouched by a tap, its jitter, and non-pointer events', () => {
    mockMedia({ reducedMotion: false })

    const { api, wrapper } = mountParallax()
    try {
      dispatchPointer('pointerdown', { x: 800, y: 600, pointerType: 'touch', pointerId: 3 })
      dispatchPointer('pointerup', { x: 800, y: 600, pointerType: 'touch', pointerId: 3 })
      dispatchPointer('touchstart', { x: 800, y: 600 })
      dispatchPointer('click', { x: 800, y: 600 })

      expect(api.x.target.value).toBe(0)
      expect(api.y.target.value).toBe(0)

      // A real tap jitters a couple of pixels: below the slop the scene stays
      // perfectly still.
      dispatchPointer('pointerdown', { x: 200, y: 200, pointerType: 'touch', pointerId: 4 })
      dispatchPointer('pointermove', { x: 202, y: 201, pointerType: 'touch', pointerId: 4 })
      dispatchPointer('pointerup', { x: 202, y: 201, pointerType: 'touch', pointerId: 4 })

      expect(api.x.target.value).toBe(0)
      expect(api.y.target.value).toBe(0)
      expect(api.active.value).toBe(false)
    } finally {
      wrapper.unmount()
    }
  })

  it('never retargets from a mouse press or release', () => {
    mockMedia({ reducedMotion: false })

    const { api, wrapper } = mountParallax()
    try {
      dispatchPointer('pointerdown', { x: 900, y: 500, pointerType: 'mouse', pointerId: 1 })
      dispatchPointer('pointerup', { x: 900, y: 500, pointerType: 'mouse', pointerId: 1 })

      expect(api.x.target.value).toBe(0)
      expect(api.y.target.value).toBe(0)
    } finally {
      wrapper.unmount()
    }
  })

  it('binds pointermove and never a legacy mouse or touch fallback', () => {
    mockMedia({ reducedMotion: false })
    const addSpy = vi.spyOn(window, 'addEventListener')

    const { wrapper } = mountParallax()
    try {
      const types = addSpy.mock.calls.map(([type]) => type)

      expect(types).toContain('pointermove')
      ;['mousemove', 'mousedown', 'touchstart', 'touchmove', 'click'].forEach(type => {
        expect(types, `${type} must not drive the parallax`).not.toContain(type)
      })
    } finally {
      wrapper.unmount()
    }
  })

  it('removes every listener it attached on unmount', () => {
    mockMedia({ reducedMotion: false })
    const addSpy = vi.spyOn(window, 'addEventListener')
    const removeSpy = vi.spyOn(window, 'removeEventListener')

    const { wrapper } = mountParallax()
    const attached = addSpy.mock.calls.map(([type]) => type)
    wrapper.unmount()
    const removed = removeSpy.mock.calls.map(([type]) => type)

    attached.forEach(type => {
      expect(removed, `${type} must be released on unmount`).toContain(type)
    })
  })
})
