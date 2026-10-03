import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DentalParallaxBackground from '@/components/login/DentalParallaxBackground.vue'
import componentSource from '@/components/login/DentalParallaxBackground.vue?raw'
import tokens from '@/design-system/tokens'

const nativeMatchMedia = window.matchMedia

const LAYER_NAMES = ['far', 'mid', 'near']

// Perceived-contrast band each layer must land in, so the scene stays visible
// without darkening the light clinical page.
const CONTRAST_BANDS = {
  far: [0.09, 0.2],
  mid: [0.3, 0.55],
  near: [0.45, 0.9]
}

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

function cssRule(selector) {
  const match = componentSource.match(new RegExp(`^${selector}\\s*\\{([^}]*)\\}`, 'm'))
  if (!match) throw new Error(`no CSS rule for ${selector}`)
  return match[1]
}

function layerDeclarations(name) {
  const rule = cssRule(`\\.dental-parallax-${name}`)
  const color = rule.match(/color:\s*var\(--color-([a-z]+)-(\d+)\)/)
  const opacity = rule.match(/opacity:\s*([\d.]+)/)
  return {
    ramp: color?.[1],
    step: color?.[2],
    opacity: opacity ? Number(opacity[1]) : 1,
    amplitude: Number(rule.match(/--parallax-amplitude:\s*(\d+)px/)?.[1] ?? 0)
  }
}

function layerMarkup(name) {
  const anchor = componentSource.indexOf(`data-layer="${name}"`)
  if (anchor === -1) throw new Error(`no markup for layer ${name}`)
  return componentSource.slice(
    componentSource.lastIndexOf('<svg', anchor),
    componentSource.indexOf('</svg>', anchor)
  )
}

function strongestElementOpacity(name) {
  // The layer rule carries the fog alpha; each mark inside the layer may damp
  // it further. The strongest declared mark is the layer's visibility ceiling,
  // and the flat fill it composites from (the blur can only soften it).
  const declared = [...layerMarkup(name).matchAll(/opacity="([\d.]+)"/g)].map(match =>
    Number(match[1])
  )
  return declared.length ? Math.max(...declared) : 1
}

function toLinear(channel) {
  const value = channel / 255
  return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
}

function toChannels(hex) {
  return hex
    .slice(1)
    .match(/../g)
    .map(part => parseInt(part, 16))
}

function luminance(hex) {
  const [r, g, b] = toChannels(hex)
  return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b)
}

function contrastRatio(a, b) {
  const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x)
  return (high + 0.05) / (low + 0.05)
}

function overBackground(hex, alpha, background) {
  const foreground = toChannels(hex)
  const base = toChannels(background)
  return `#${foreground
    .map((channel, index) =>
      Math.round(channel * alpha + base[index] * (1 - alpha))
        .toString(16)
        .padStart(2, '0')
    )
    .join('')}`
}

function perceivedContrast(fg, alpha) {
  // The scene paints over a 160deg gradient between both canvas endpoints, so
  // every pixel sits between these two backgrounds.
  return [tokens.colors.background.canvas, tokens.colors.accent[50]]
    .map(background => contrastRatio(overBackground(fg, alpha, background), background) - 1)
    .sort((a, b) => a - b)
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

describe('DentalParallaxBackground visibility budget', () => {
  it('carries per-layer travel large enough to be perceptible', () => {
    expect(LAYER_NAMES.map(name => layerDeclarations(name).amplitude)).toEqual([10, 22, 36])
  })

  it('overscans every layer past the largest amplitude so no edge shows', () => {
    const rule = cssRule('\\.dental-parallax-layer')
    const inset = Number(rule.match(/inset:\s*-(\d+)px/)?.[1] ?? 0)
    const grow = Number(rule.match(/calc\(100%\s*\+\s*(\d+)px\)/)?.[1] ?? 0)
    const largest = Math.max(...LAYER_NAMES.map(name => layerDeclarations(name).amplitude))

    expect(inset).toBeGreaterThanOrEqual(largest)
    expect(grow).toBeGreaterThanOrEqual(2 * largest)
  })

  it('deepens the token ramp from the far layer to the near layer', () => {
    const steps = LAYER_NAMES.map(name => Number(layerDeclarations(name).step))

    expect(steps).toEqual([...steps].sort((a, b) => a - b))
    expect(new Set(steps).size).toBe(LAYER_NAMES.length)
  })

  it('puts the strongest dental silhouette in the half the card leaves free', () => {
    // The form card anchors left on desktop, so the mid layer must carry at
    // least two teeth with the biggest one in the right half.
    const teeth = [...layerMarkup('mid').matchAll(/translate\((\d+) (\d+)\) scale\((\d+)\)/g)].map(
      ([, x, , scale]) => ({ x: Number(x), scale: Number(scale) })
    )

    expect(teeth.length).toBeGreaterThanOrEqual(2)
    const largest = teeth.reduce((widest, tooth) => {
      return tooth.scale > widest.scale ? tooth : widest
    })
    expect(largest.x).toBeGreaterThanOrEqual(720)
  })

  it('keeps each layer inside its perceived-contrast band over the canvas gradient', () => {
    LAYER_NAMES.forEach(name => {
      const { ramp, step, opacity } = layerDeclarations(name)
      const [floor, ceiling] = CONTRAST_BANDS[name]
      const alpha = opacity * strongestElementOpacity(name)
      const ratios = perceivedContrast(tokens.colors[ramp][step], alpha)

      expect(
        ratios[0] >= floor,
        `${name} layer is too faint: ${ratios[0].toFixed(3)} contrast (floor ${floor})`
      ).toBe(true)
      expect(
        ratios[1] <= ceiling,
        `${name} layer is too heavy: ${ratios[1].toFixed(3)} contrast (ceiling ${ceiling})`
      ).toBe(true)
    })
  })
})
