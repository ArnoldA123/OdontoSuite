import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DentalParallaxBackground from '@/components/login/DentalParallaxBackground.vue'
import componentSource from '@/components/login/DentalParallaxBackground.vue?raw'

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
  return matchMedia
}

function dispatchPointerMove(x, y) {
  const event = new Event('pointermove')
  event.clientX = x
  event.clientY = y
  window.dispatchEvent(event)
}

function waitFrames(ms = 80) {
  return new Promise(resolve => setTimeout(resolve, ms))
}

afterEach(() => {
  window.matchMedia = nativeMatchMedia
  vi.restoreAllMocks()
})

describe('DentalParallaxBackground', () => {
  it('renders the three decorative layers behind an aria-hidden scene', () => {
    const wrapper = mount(DentalParallaxBackground)

    expect(wrapper.element.getAttribute('aria-hidden')).toBe('true')

    const layers = wrapper.findAll('[data-layer]')
    expect(layers.map(layer => layer.attributes('data-layer'))).toEqual(['far', 'mid', 'near'])

    wrapper.unmount()
  })

  it('follows the pointer with the damped spring and releases the listener on unmount', async () => {
    mockMedia({ reducedMotion: false })
    const addSpy = vi.spyOn(window, 'addEventListener')
    const removeSpy = vi.spyOn(window, 'removeEventListener')

    const wrapper = mount(DentalParallaxBackground)
    await flushPromises()

    expect(addSpy.mock.calls.some(([type]) => type === 'pointermove')).toBe(true)

    dispatchPointerMove(900, 200)
    await waitFrames()

    const x = parseFloat(wrapper.element.style.getPropertyValue('--parallax-x'))
    const y = parseFloat(wrapper.element.style.getPropertyValue('--parallax-y'))
    expect(Number.isFinite(x)).toBe(true)
    expect(x).toBeGreaterThan(0)
    expect(y).toBeLessThan(0)

    wrapper.unmount()
    expect(removeSpy.mock.calls.some(([type]) => type === 'pointermove')).toBe(true)
  })

  it('stays static and attaches no pointer listener under prefers-reduced-motion: reduce', async () => {
    mockMedia({ reducedMotion: true })
    const addSpy = vi.spyOn(window, 'addEventListener')

    const wrapper = mount(DentalParallaxBackground)
    await flushPromises()

    expect(addSpy.mock.calls.some(([type]) => type === 'pointermove')).toBe(false)

    dispatchPointerMove(1000, 100)
    await waitFrames()

    expect(wrapper.element.style.getPropertyValue('--parallax-x')).toBe('0')
    expect(wrapper.element.style.getPropertyValue('--parallax-y')).toBe('0')

    wrapper.unmount()
  })

  it('declares the static layer fallback for prefers-reduced-motion in its CSS', () => {
    expect(componentSource).toContain('@media (prefers-reduced-motion: reduce)')
    expect(componentSource).toMatch(
      /@media \(prefers-reduced-motion: reduce\)[\s\S]*?transform: none/
    )
  })

  it('promotes the layers for the compositor only while the parallax is live', async () => {
    mockMedia({ reducedMotion: false })
    const wrapper = mount(DentalParallaxBackground)

    expect(wrapper.classes()).not.toContain('is-parallax-active')

    dispatchPointerMove(900, 200)
    await flushPromises()
    expect(wrapper.classes()).toContain('is-parallax-active')

    wrapper.unmount()
  })

  it('never promotes the layers under prefers-reduced-motion: reduce', async () => {
    mockMedia({ reducedMotion: true })
    const wrapper = mount(DentalParallaxBackground)
    await flushPromises()

    dispatchPointerMove(900, 200)
    await flushPromises()
    expect(wrapper.classes()).not.toContain('is-parallax-active')

    wrapper.unmount()
  })

  it('scopes will-change to the active parallax class', () => {
    expect(componentSource).toMatch(
      /\.dental-parallax\.is-parallax-active\s+\.dental-parallax-layer\s*\{[^}]*will-change:\s*transform/
    )
    expect(componentSource).not.toMatch(/^\s*\.dental-parallax-layer\s*\{[^}]*will-change/m)
  })
})
