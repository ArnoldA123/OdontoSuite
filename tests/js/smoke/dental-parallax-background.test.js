import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DentalParallaxBackground from '@/components/login/DentalParallaxBackground.vue'
import componentSource from '@/components/login/DentalParallaxBackground.vue?raw'
import loginPageSource from '@/modules/auth/LoginPage.vue?raw'
import tokens from '@/design-system/tokens'

const nativeMatchMedia = window.matchMedia

const LAYER_NAMES = ['far', 'mid', 'near']

// Marks position themselves in percentages of the 1440x900 design frame the
// scene is composed on, and size themselves in viewport width units, so a
// width in vw over 100 is the fraction of the viewport a mark covers.
const SCENE_WIDTH = 1440
const SCENE_HEIGHT = 900

// The desktop form card anchors left (padding-left clamp(56px, 14vw, 220px)
// plus a 26rem card), so the strong tiers stay clear of that band and the
// deep tier carries the scene there instead.
const CARD_KEEP_OUT = { left: 190, right: 660, top: 40, bottom: 860 }

const LOGIN_ASSETS = {
  tooth: '/images/login/tooth-1.png',
  sparkleTooth: '/images/login/tooth-2.png',
  toothbrush: '/images/login/toothbrush.png'
}

// The shipped tooth PNGs paint a near-flat light gray body (#dedede, channel
// 222). The visibility model pushes that channel through the per-tier
// brightness filter and the layer opacity, then composites over the canvas
// gradient endpoints.
const TOOTH_BASE_CHANNEL = 222

// Perceived-contrast band each layer must land in (contrastRatio - 1). The
// far tier stays the faintest but above 12% so no mark disappears.
const CONTRAST_BANDS = {
  far: [0.12, 0.3],
  mid: [0.25, 0.55],
  near: [0.4, 1]
}

function mockMedia({ reducedMotion = false, reducedTransparency = false } = {}) {
  const matchMedia = vi.fn(query => ({
    matches: query.includes('prefers-reduced-transparency')
      ? reducedTransparency
      : query.includes('prefers-reduced-motion')
        ? reducedMotion
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

function block(source, anchor) {
  const open = source.indexOf('{', anchor)
  if (anchor === -1 || open === -1) return null

  let depth = 0
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1
    if (source[index] === '}') {
      depth -= 1
      if (depth === 0) return source.slice(open + 1, index)
    }
  }
  throw new Error(`unbalanced block at ${anchor}`)
}

function mediaBlock(query, source = componentSource) {
  const inner = block(source, source.indexOf(`@media (${query})`))
  if (inner === null) throw new Error(`no @media (${query}) block`)
  return inner
}

function reducedMotionBlock() {
  return mediaBlock('prefers-reduced-motion: reduce')
}

function reducedTransparencyBlock(source = componentSource) {
  return mediaBlock('prefers-reduced-transparency: reduce', source)
}

function keyframesBlock(name) {
  const inner = block(componentSource, componentSource.indexOf(`@keyframes ${name}`))
  if (inner === null) throw new Error(`no @keyframes ${name}`)
  return inner
}

function layerMarkup(name) {
  const anchor = componentSource.indexOf(`data-layer="${name}"`)
  if (anchor === -1) throw new Error(`no markup for layer ${name}`)

  const next = LAYER_NAMES.map(other =>
    componentSource.indexOf(`data-layer="${other}"`, anchor + 1)
  )
    .filter(index => index > anchor)
    .sort((a, b) => a - b)[0]

  return componentSource.slice(anchor, next ?? componentSource.indexOf('</template>', anchor))
}

function tagAttributes(tag) {
  const attributes = {}
  for (const [, key, value] of tag.matchAll(/([a-zA-Z:-]+)="([^"]*)"/g)) {
    attributes[key] = value
  }
  return attributes
}

// Every mark is an <img> whose inline style declares the center (--mark-x /
// --mark-y as design-frame percentages), the viewport-relative width
// (--mark-w in vw) and the rotation (--mark-r in deg).
function marks(name) {
  return [...layerMarkup(name).matchAll(/<img\b[^>]*>/g)].map(([tag]) => {
    const attributes = tagAttributes(tag)
    const style = attributes.style ?? ''
    const x = style.match(/--mark-x:\s*(-?[\d.]+)%/)
    const y = style.match(/--mark-y:\s*(-?[\d.]+)%/)
    const w = style.match(/--mark-w:\s*(-?[\d.]+)vw/)
    const r = style.match(/--mark-r:\s*(-?[\d.]+)deg/)

    if (!x || !y || !w || !r) throw new Error(`unparsable mark style: ${style}`)

    return {
      src: attributes.src,
      className: attributes.class ?? '',
      width: Number(w[1]) / 100,
      rotate: Number(r[1]),
      centerX: (Number(x[1]) / 100) * SCENE_WIDTH,
      centerY: (Number(y[1]) / 100) * SCENE_HEIGHT
    }
  })
}

function toothMarks(name) {
  return marks(name).filter(mark => !mark.className.includes('toothbrush'))
}

function brushMarks(name) {
  return marks(name).filter(mark => mark.className.includes('toothbrush'))
}

function driftClock(rule, name) {
  const names = cssRule(
    `\\.dental-parallax\\.has-drift\\s+\\.dental-parallax-${name}\\s+\\.dental-parallax-drift`
  )
  return {
    name: names.match(/animation-name:\s*([a-z-]+)/)?.[1],
    duration: Number(rule.match(/--dental-drift-duration:\s*([\d.]+)s/)?.[1] ?? NaN),
    delay: Number(rule.match(/--dental-drift-delay:\s*(-?[\d.]+)s/)?.[1] ?? NaN)
  }
}

function layerDeclarations(name) {
  const rule = cssRule(`\\.dental-parallax-${name}`)
  const opacity = rule.match(/opacity:\s*([\d.]+)/)
  const brightness = rule.match(/--mark-brightness:\s*([\d.]+)/)
  return {
    opacity: opacity ? Number(opacity[1]) : 1,
    brightness: brightness ? Number(brightness[1]) : 1,
    amplitude: Number(rule.match(/--parallax-amplitude:\s*(\d+)px/)?.[1] ?? 0),
    drift: driftClock(rule, name)
  }
}

function driftStops(name) {
  return [
    ...keyframesBlock(layerDeclarations(name).drift.name).matchAll(/([\d.]+)%\s*\{([^}]*)\}/g)
  ].map(([, percent, body]) => {
    const translate = body.match(/translate3d\((-?[\d.]+)(?:px)?,\s*(-?[\d.]+)(?:px)?,\s*0\)/)
    if (!translate) throw new Error(`unparsable drift stop at ${percent}%: ${body.trim()}`)
    return { percent: Number(percent), x: Number(translate[1]), y: Number(translate[2]) }
  })
}

function driftTravel(name) {
  return Math.max(...driftStops(name).map(stop => Math.max(Math.abs(stop.x), Math.abs(stop.y))))
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

function grayHex(channel) {
  const value = Math.max(0, Math.min(255, Math.round(channel)))
    .toString(16)
    .padStart(2, '0')
  return `#${value}${value}${value}`
}

function markPerceivedContrast(brightness, opacity) {
  // The scene paints over a 160deg gradient between both canvas endpoints, so
  // every pixel sits between these two backgrounds.
  const painted = grayHex(TOOTH_BASE_CHANNEL * brightness)
  return [tokens.colors.background.canvas, tokens.colors.accent[50]]
    .map(background => contrastRatio(overBackground(painted, opacity, background), background) - 1)
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
    expect(reducedMotionBlock()).toMatch(/transform: none/)
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

describe('DentalParallaxBackground art assets', () => {
  it('retires the inline vector tooth art for real image marks', () => {
    expect(componentSource).not.toMatch(/<svg\b/)
    expect(componentSource).not.toMatch(/<symbol\b/)
    expect(componentSource).not.toMatch(/<use\b/)
    expect(componentSource).not.toContain('dental-parallax-tooth-')

    LAYER_NAMES.forEach(name => {
      expect(marks(name).length, `${name} layer must carry image marks`).toBeGreaterThanOrEqual(2)
      marks(name).forEach(mark => expect(mark.src).toMatch(/^\/images\/login\//))
    })
  })

  it('mounts every parsed mark as a real image element', () => {
    const wrapper = mount(DentalParallaxBackground)
    const images = wrapper.findAll('img')
    const parsed = LAYER_NAMES.flatMap(name => marks(name))

    expect(images).toHaveLength(parsed.length)
    expect(images.every(image => image.attributes('alt') === '')).toBe(true)
    expect(new Set(images.map(image => image.attributes('src')))).toEqual(
      new Set(Object.values(LOGIN_ASSETS))
    )

    wrapper.unmount()
  })

  it('references all three shipped login PNGs through /images/login/', () => {
    const srcs = LAYER_NAMES.flatMap(name => marks(name).map(mark => mark.src))
    const unique = [...new Set(srcs)].sort()

    expect(unique).toEqual(Object.values(LOGIN_ASSETS).sort())
  })

  it('mixes the plain tooth, the sparkle tooth and the toothbrush across the tiers', () => {
    LAYER_NAMES.forEach(name => {
      const assets = new Set(marks(name).map(mark => mark.src))
      expect(assets.size, `${name} layer must mix assets`).toBeGreaterThanOrEqual(2)
    })

    const brushes = LAYER_NAMES.flatMap(name => brushMarks(name))
    expect(brushes.length).toBe(1)
    expect(brushes[0].src).toBe(LOGIN_ASSETS.toothbrush)
  })

  it('positions every mark with the center, size and rotation style contract', () => {
    LAYER_NAMES.forEach(name => {
      marks(name).forEach(mark => {
        expect(Number.isFinite(mark.centerX)).toBe(true)
        expect(Number.isFinite(mark.centerY)).toBe(true)
        expect(mark.width).toBeGreaterThan(0)
        expect(Number.isFinite(mark.rotate)).toBe(true)
      })
    })
  })

  it('drives each mark layout from the inline custom properties', () => {
    const rule = cssRule('\\.dental-parallax-mark')

    expect(rule).toMatch(/left:\s*var\(--mark-x\)/)
    expect(rule).toMatch(/top:\s*var\(--mark-y\)/)
    expect(rule).toMatch(/width:\s*var\(--mark-w\)/)
    expect(rule).toMatch(/rotate\(var\(--mark-r/)
    expect(rule).toMatch(/filter:\s*brightness\(var\(--mark-brightness/)
  })
})

describe('DentalParallaxBackground ambient drift', () => {
  it('runs the drift loop on the mounted scene while motion is allowed', async () => {
    mockMedia({ reducedMotion: false })
    const wrapper = mount(DentalParallaxBackground)
    await flushPromises()

    expect(wrapper.classes()).toContain('has-drift')
    expect(componentSource).toMatch(
      /\.dental-parallax\.has-drift\s+\.dental-parallax-drift\s*\{[^}]*animation-duration:\s*var\(--dental-drift-duration\)/
    )
    expect(componentSource).toMatch(
      /\.dental-parallax\.has-drift\s+\.dental-parallax-drift\s*\{[^}]*animation-iteration-count:\s*infinite/
    )

    wrapper.unmount()
  })

  it('names each keyframe literally, so the scoped-style rename rewrites name and reference together', () => {
    // A keyframe name that only travels through a custom property escapes the
    // scoped-style hash: the @keyframes rule gets renamed and the animation
    // never resolves. Every name stays literal in an animation-name.
    const references = [...componentSource.matchAll(/animation-name:\s*([a-z-]+)/g)].map(
      ([, value]) => value
    )

    expect(componentSource).not.toMatch(/animation-name:\s*var\(/)
    LAYER_NAMES.forEach(name => {
      expect(componentSource).toContain(`@keyframes dental-parallax-drift-${name}`)
      expect(references).toContain(`dental-parallax-drift-${name}`)
    })
  })

  it('stays fully static under prefers-reduced-motion: reduce', async () => {
    mockMedia({ reducedMotion: true })
    const wrapper = mount(DentalParallaxBackground)
    await flushPromises()

    expect(wrapper.classes()).not.toContain('has-drift')

    wrapper.unmount()
  })

  it('kills every drift loop in the reduced-motion CSS block', () => {
    const inner = reducedMotionBlock()

    expect(inner).toMatch(/\.dental-parallax-drift\s*\{[^}]*animation:\s*none/)
    expect(inner).toMatch(/\.dental-parallax-drift\s*\{[^}]*transform:\s*none/)
  })

  it('nestles the art in a drift wrapper inside each pointer-transformed layer', () => {
    expect(componentSource).toMatch(/\.dental-parallax-drift\s*\{[^}]*position:\s*absolute/)
    expect(componentSource).toMatch(/\.dental-parallax-art\s*\{[^}]*width:\s*100%/)

    LAYER_NAMES.forEach(name => {
      const layer = componentSource.indexOf(`data-layer="${name}"`)
      const drift = componentSource.indexOf('dental-parallax-drift', layer)
      const art = componentSource.indexOf('dental-parallax-art', layer)

      expect(drift, `${name} layer must own a drift wrapper`).toBeGreaterThan(layer)
      expect(art, `${name} art must sit inside the drift wrapper`).toBeGreaterThan(drift)
    })
  })

  it('gives every tier its own slow clock and start phase', () => {
    const clocks = LAYER_NAMES.map(name => layerDeclarations(name).drift)

    expect(new Set(clocks.map(clock => clock.name)).size).toBe(LAYER_NAMES.length)
    expect(new Set(clocks.map(clock => clock.duration)).size).toBe(LAYER_NAMES.length)
    expect(new Set(clocks.map(clock => clock.delay)).size).toBe(LAYER_NAMES.length)

    clocks.forEach(clock => {
      expect(clock.duration).toBeGreaterThanOrEqual(30)
      expect(clock.duration).toBeLessThanOrEqual(60)
      expect(clock.delay).toBeGreaterThanOrEqual(0)
      expect(clock.delay).toBeLessThan(clock.duration)
      expect(keyframesBlock(clock.name)).toBeTruthy()
    })
  })

  it('keeps the drift travel small and widest for the nearest tier', () => {
    const travels = LAYER_NAMES.map(name => {
      expect(driftStops(name).length).toBeGreaterThanOrEqual(3)
      return driftTravel(name)
    })

    travels.forEach(travel => {
      expect(travel).toBeGreaterThanOrEqual(10)
      expect(travel).toBeLessThanOrEqual(25)
    })
    expect(travels[0]).toBeLessThan(travels[1])
    expect(travels[1]).toBeLessThan(travels[2])
  })

  it('animates transform only, so the loops never trigger layout or paint work', () => {
    LAYER_NAMES.forEach(name => {
      const declarations = [
        ...keyframesBlock(layerDeclarations(name).drift.name).matchAll(
          /([a-z-]+)\s*:\s*([^;{}]+);/g
        )
      ]

      expect(declarations.length).toBeGreaterThanOrEqual(3)
      declarations.forEach(([, property]) => expect(property).toBe('transform'))
    })
  })

  it('returns every loop to its origin so the loop never jumps', () => {
    LAYER_NAMES.forEach(name => {
      const stops = driftStops(name)

      expect(stops[0].percent).toBe(0)
      expect(stops[stops.length - 1].percent).toBe(100)
      expect([stops[0].x, stops[0].y]).toEqual([0, 0])
      expect([stops[stops.length - 1].x, stops[stops.length - 1].y]).toEqual([0, 0])
    })
  })

  it('leaves will-change off the drift wrapper', () => {
    expect(componentSource).not.toMatch(/\.dental-parallax-drift\s*\{[^}]*will-change/)
  })
})

describe('DentalParallaxBackground visibility budget', () => {
  it('carries per-layer travel large enough to be perceptible', () => {
    expect(LAYER_NAMES.map(name => layerDeclarations(name).amplitude)).toEqual([10, 22, 36])
  })

  it('overscans every layer past the largest pointer offset plus drift', () => {
    const rule = cssRule('\\.dental-parallax-layer')
    const inset = Number(rule.match(/inset:\s*-(\d+)px/)?.[1] ?? 0)
    const grow = Number(rule.match(/calc\(100%\s*\+\s*(\d+)px\)/)?.[1] ?? 0)
    const largest = Math.max(...LAYER_NAMES.map(name => layerDeclarations(name).amplitude))
    const drift = Math.max(...LAYER_NAMES.map(driftTravel))

    expect(inset).toBeGreaterThanOrEqual(largest + drift)
    expect(grow).toBeGreaterThanOrEqual(2 * (largest + drift))
  })

  it('draws every tooth inside the normal-tooth scale budget', () => {
    const shares = LAYER_NAMES.flatMap(name => toothMarks(name).map(mark => mark.width))

    expect(shares.length).toBeGreaterThanOrEqual(10)
    shares.forEach(share => {
      expect(share).toBeLessThanOrEqual(0.09)
      expect(share).toBeGreaterThanOrEqual(0.025)
    })
  })

  it('scales the toothbrush one step above the tooth budget', () => {
    const brushes = LAYER_NAMES.flatMap(name => brushMarks(name))

    expect(brushes.length).toBeGreaterThanOrEqual(1)
    brushes.forEach(mark => {
      expect(mark.width).toBeGreaterThan(0.09)
      expect(mark.width).toBeLessThanOrEqual(0.13)
    })
  })

  it('keeps the depth order: the far tier is the smallest and the near tier the largest', () => {
    const widest = name => Math.max(...toothMarks(name).map(mark => mark.width))

    expect(widest('far')).toBeLessThan(widest('mid'))
    expect(widest('mid')).toBeLessThan(widest('near'))
  })

  it('lands the near-tier hero teeth in the normal-tooth band of the viewport', () => {
    const heroes = toothMarks('near')

    expect(heroes.length).toBeGreaterThanOrEqual(2)
    heroes.forEach(mark => {
      expect(
        mark.width >= 0.07 && mark.width <= 0.09,
        `${mark.src} hero covers ${(mark.width * 100).toFixed(1)}% of the viewport`
      ).toBe(true)
    })
  })

  it('spreads the deep tier across both halves so the scene breathes everywhere', () => {
    const far = toothMarks('far')

    expect(far.length).toBeGreaterThanOrEqual(6)
    expect(far.filter(mark => mark.centerX < SCENE_WIDTH / 2).length).toBeGreaterThanOrEqual(2)
    expect(far.filter(mark => mark.centerX >= SCENE_WIDTH / 2).length).toBeGreaterThanOrEqual(2)
  })

  it('puts the largest tooth in the half the card leaves free', () => {
    const marksOnTiers = LAYER_NAMES.flatMap(name => toothMarks(name))
    const largest = marksOnTiers.reduce((widest, mark) =>
      mark.width > widest.width ? mark : widest
    )

    expect(largest.centerX).toBeGreaterThanOrEqual(SCENE_WIDTH / 2)
  })

  it('keeps the strong tiers out from under the left-anchored form card', () => {
    ;['mid', 'near'].forEach(name => {
      marks(name).forEach(mark => {
        const behindCard =
          mark.centerX > CARD_KEEP_OUT.left &&
          mark.centerX < CARD_KEEP_OUT.right &&
          mark.centerY > CARD_KEEP_OUT.top &&
          mark.centerY < CARD_KEEP_OUT.bottom

        expect(
          behindCard,
          `${name} mark at (${mark.centerX}, ${mark.centerY}) sits under the form card`
        ).toBe(false)
      })
    })
  })

  it('deepens the image treatment from the far tier to the near tier', () => {
    const brightness = LAYER_NAMES.map(name => layerDeclarations(name).brightness)
    const opacities = LAYER_NAMES.map(name => layerDeclarations(name).opacity)

    expect(new Set(brightness).size).toBe(LAYER_NAMES.length)
    expect(brightness[0]).toBeGreaterThan(brightness[1])
    expect(brightness[1]).toBeGreaterThan(brightness[2])
    expect(opacities[0]).toBeLessThan(opacities[1])
    expect(opacities[1]).toBeLessThan(opacities[2])
  })

  it('harmonizes the intrinsically black toothbrush instead of pasting it in', () => {
    const rule = cssRule('\\.dental-parallax-toothbrush')
    const opacity = Number(rule.match(/opacity:\s*([\d.]+)/)?.[1] ?? 1)

    expect(opacity).toBeGreaterThanOrEqual(0.25)
    expect(opacity).toBeLessThanOrEqual(0.6)
  })

  it('keeps the warm sparkle accents from flattening to black', () => {
    const rule = cssRule('\\.dental-parallax-sparkle')

    expect(rule).toMatch(/brightness\(var\(--mark-brightness/)
    expect(rule).toMatch(/saturate\(/)
    expect(rule).toMatch(/drop-shadow\(/)
    expect(rule).toMatch(/rgba\(255,\s*171,\s*73/)
    expect(componentSource).not.toMatch(/grayscale\(/)
  })

  it('keeps each layer clearly perceptible over the canvas gradient', () => {
    LAYER_NAMES.forEach(name => {
      const { brightness, opacity } = layerDeclarations(name)
      const [floor, ceiling] = CONTRAST_BANDS[name]
      const ratios = markPerceivedContrast(brightness, opacity)

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

describe('DentalParallaxBackground reduced transparency', () => {
  it('keeps the three layers mounted and visible under prefers-reduced-transparency: reduce', () => {
    mockMedia({ reducedTransparency: true })
    const wrapper = mount(DentalParallaxBackground)

    const layers = wrapper.findAll('[data-layer]')
    expect(layers.map(layer => layer.attributes('data-layer'))).toEqual(['far', 'mid', 'near'])
    layers.forEach(layer => {
      expect(window.getComputedStyle(layer.element).display).not.toBe('none')
      expect(layer.element.style.display).not.toBe('none')
    })
    expect(wrapper.element.getAttribute('aria-hidden')).toBe('true')

    wrapper.unmount()
  })

  it('never hides the layers in its reduced-transparency CSS', () => {
    const inner = reducedTransparencyBlock()

    expect(inner).not.toMatch(/display\s*:\s*none/)
    expect(inner).not.toMatch(/visibility\s*:\s*hidden/)
  })

  it('raises every layer to solid opacity and keeps the image treatment', () => {
    const inner = reducedTransparencyBlock()

    expect(inner).toMatch(/\.dental-parallax-layer\s*\{[^}]*opacity\s*:\s*1/)
    expect(inner).not.toMatch(/filter\s*:\s*none/)
    expect(inner).not.toMatch(/--mark-brightness/)
    expect(inner).not.toMatch(/opacity\s*:\s*0?\.\d/)
  })

  it('keeps every tier populated with image marks in solid mode', () => {
    LAYER_NAMES.forEach(name => {
      expect(marks(name).length).toBeGreaterThanOrEqual(2)
      expect(marks(name).every(mark => mark.src.startsWith('/images/login/'))).toBe(true)
    })
  })

  it('keeps every tier clearly visible in solid mode', () => {
    LAYER_NAMES.forEach(name => {
      const { brightness } = layerDeclarations(name)
      expect(markPerceivedContrast(brightness, 1)[0]).toBeGreaterThanOrEqual(0.5)
    })
  })

  it('keeps the login backdrop visible under reduced transparency', () => {
    const inner = reducedTransparencyBlock(loginPageSource)
    const backdrop = inner.match(/\.login-backdrop\s*\{([^}]*)\}/)

    expect(
      backdrop,
      'LoginPage.vue must keep a .login-backdrop rule in its reduced-transparency block'
    ).not.toBeNull()
    expect(backdrop[1]).not.toMatch(/display\s*:\s*none/)
    expect(backdrop[1]).toMatch(/display\s*:\s*block/)
  })
})
