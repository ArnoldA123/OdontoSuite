import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DentalParallaxBackground from '@/components/login/DentalParallaxBackground.vue'
import componentSource from '@/components/login/DentalParallaxBackground.vue?raw'
import loginPageSource from '@/modules/auth/LoginPage.vue?raw'
import tokens from '@/design-system/tokens'

const nativeMatchMedia = window.matchMedia

const LAYER_NAMES = ['far', 'mid', 'near']

// The art is authored in this viewBox, so a width in scene units over
// SCENE_WIDTH is the fraction of the viewport the mark covers.
const SCENE_WIDTH = 1440

// The desktop form card anchors left (padding-left clamp(56px, 14vw, 220px)
// plus a 26rem card), so the strong tiers stay clear of that band and the
// deep tier carries the scene there instead.
const CARD_KEEP_OUT = { left: 190, right: 660, top: 40, bottom: 860 }

const TOOTH_SHAPE_IDS = ['incisor', 'molar', 'premolar']

// Perceived-contrast band each layer must land in, so the scene stays visible
// without darkening the light clinical page.
const CONTRAST_BANDS = {
  far: [0.09, 0.2],
  mid: [0.3, 0.55],
  near: [0.45, 0.9]
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

function tierRule(block, name) {
  const match = block.match(new RegExp(`\\.dental-parallax-${name}\\s*\\{([^}]*)\\}`))
  if (!match) throw new Error(`no .dental-parallax-${name} rule in the reduced-transparency block`)
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
    amplitude: Number(rule.match(/--parallax-amplitude:\s*(\d+)px/)?.[1] ?? 0),
    drift: driftClock(rule, name)
  }
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

function layerMarkup(name) {
  const anchor = componentSource.indexOf(`data-layer="${name}"`)
  if (anchor === -1) throw new Error(`no markup for layer ${name}`)

  const start = componentSource.indexOf('<svg', anchor)
  return componentSource
    .slice(start, componentSource.indexOf('</svg>', start))
    .replace(/<!--[\s\S]*?-->/g, '')
}

function tagAttributes(tag) {
  const attributes = {}
  for (const [, key, value] of tag.matchAll(/([a-zA-Z:-]+)="([^"]*)"/g)) {
    attributes[key] = value
  }
  return attributes
}

// Every tooth is a `use` mark on a shared `dental-parallax-tooth-*` shape, and
// the transform is `translate(center) rotate(angle) scale(size)`, so a mark's
// painted width is its declared frame width times its scale.
function toothMarks(name) {
  return [...layerMarkup(name).matchAll(/<use\b[\s\S]*?\/>/g)].map(([tag]) => {
    const attributes = tagAttributes(tag)
    const transform = (attributes.transform ?? '').match(
      /translate\((-?[\d.]+) (-?[\d.]+)\)(?:\s+rotate\((-?[\d.]+)\))?\s+scale\(([\d.]+)\)/
    )
    if (!transform) throw new Error(`unparsable tooth transform: ${attributes.transform}`)

    return {
      shape: (attributes.href ?? '').replace('#dental-parallax-tooth-', ''),
      frameX: Number(attributes.x),
      frameY: Number(attributes.y),
      frameWidth: Number(attributes.width),
      frameHeight: Number(attributes.height),
      centerX: Number(transform[1]),
      centerY: Number(transform[2]),
      rotate: Number(transform[3] ?? 0),
      scale: Number(transform[4]),
      width: Number(attributes.width) * Number(transform[4])
    }
  })
}

function toothViewportShare(mark) {
  return mark.width / SCENE_WIDTH
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

function toothFrames() {
  const frames = {}
  for (const [, id, viewBox] of componentSource.matchAll(
    /<symbol\s+id="dental-parallax-tooth-([a-z]+)"\s+viewBox="([^"]*)"/g
  )) {
    const [minX, minY, width, height] = viewBox.split(/\s+/).map(Number)
    frames[id] = { minX, minY, width, height }
  }
  return frames
}

function paintedAttributes() {
  return [...componentSource.matchAll(/\s(fill|stroke|color)="([^"]*)"/g)].map(
    ([, , value]) => value
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

  it('deepens the token ramp from the far layer to the near layer', () => {
    const steps = LAYER_NAMES.map(name => Number(layerDeclarations(name).step))

    expect(steps).toEqual([...steps].sort((a, b) => a - b))
    expect(new Set(steps).size).toBe(LAYER_NAMES.length)
  })

  it('draws every tooth inside a normal-tooth scale budget', () => {
    // The excess this replaces: one silhouette spanning ~23% of the viewport.
    const shares = LAYER_NAMES.flatMap(name => toothMarks(name).map(toothViewportShare))

    expect(shares.length).toBeGreaterThanOrEqual(10)
    shares.forEach(share => {
      expect(share).toBeLessThanOrEqual(0.13)
      expect(share).toBeGreaterThanOrEqual(0.02)
    })
  })

  it('reuses three crowned-and-rooted tooth shapes across the tiers', () => {
    const frames = toothFrames()

    expect(Object.keys(frames).sort()).toEqual(TOOTH_SHAPE_IDS)
    LAYER_NAMES.forEach(name => {
      const shapes = new Set(toothMarks(name).map(mark => mark.shape))
      expect(shapes.size, `${name} layer must mix shapes`).toBeGreaterThanOrEqual(2)
    })
  })

  it('frames every tooth mark with the frame its shared shape declares', () => {
    const frames = toothFrames()

    LAYER_NAMES.forEach(name => {
      const marks = toothMarks(name)
      expect(marks.length, `${name} layer must carry teeth`).toBeGreaterThanOrEqual(2)

      marks.forEach(mark => {
        const frame = frames[mark.shape]
        expect(frame, `${mark.shape} must be a shared shape`).toBeTruthy()
        expect(mark.frameX).toBe(frame.minX)
        expect(mark.frameY).toBe(frame.minY)
        expect(mark.frameWidth).toBe(frame.width)
        expect(mark.frameHeight).toBe(frame.height)
      })
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
      const share = toothViewportShare(mark)
      expect(
        share >= 0.07 && share <= 0.13,
        `${mark.shape} hero covers ${(share * 100).toFixed(1)}% of the viewport`
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
    const marks = LAYER_NAMES.flatMap(name => toothMarks(name))
    const largest = marks.reduce((widest, mark) => (mark.width > widest.width ? mark : widest))

    expect(largest.centerX).toBeGreaterThanOrEqual(SCENE_WIDTH / 2)
  })

  it('keeps the strong tiers out from under the left-anchored form card', () => {
    ;['mid', 'near'].forEach(name => {
      toothMarks(name).forEach(mark => {
        const behindCard =
          mark.centerX > CARD_KEEP_OUT.left &&
          mark.centerX < CARD_KEEP_OUT.right &&
          mark.centerY > CARD_KEEP_OUT.top &&
          mark.centerY < CARD_KEEP_OUT.bottom

        expect(
          behindCard,
          `${name} tooth at (${mark.centerX}, ${mark.centerY}) sits under the form card`
        ).toBe(false)
      })
    })
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

  it('repaints every tier from solid token tints at full opacity', () => {
    const inner = reducedTransparencyBlock()

    expect(inner).toMatch(/\.dental-parallax-layer\s*\{[^}]*opacity\s*:\s*1/)
    expect(inner).toMatch(/\.dental-parallax-layer\s+\[opacity\]\s*\{[^}]*opacity\s*:\s*1/)
    expect(inner).not.toMatch(/opacity\s*:\s*0?\.\d/)
    expect(inner).not.toMatch(/rgba?\s*\(/)

    const steps = LAYER_NAMES.map(name => {
      const step = tierRule(inner, name).match(/color\s*:\s*var\(--color-accent-(\d+)\)/)?.[1]
      expect(step, `${name} must repaint from a solid accent token`).toBeTruthy()
      return Number(step)
    })

    expect(new Set(steps).size).toBe(LAYER_NAMES.length)
    expect(steps).toEqual([...steps].sort((a, b) => a - b))
  })

  it('paints the redesigned marks from the tier tint alone', () => {
    // Solid mode neutralizes the `opacity` presentation attribute; a
    // fill-opacity / stroke-opacity mark would slip past that rule, so the
    // scene never declares one.
    expect(componentSource).not.toMatch(/(fill|stroke)-opacity/)

    const painted = paintedAttributes()
    expect(painted.length).toBeGreaterThan(0)
    painted.forEach(value => {
      const tokenised = /^var\(--color-[a-z0-9-]+\)$/.test(value)
      expect(
        ['currentColor', 'none'].includes(value) || tokenised,
        `unexpected paint value: ${value}`
      ).toBe(true)
    })
  })

  it('reaches every tier with the new shapes in solid mode', () => {
    const inner = reducedTransparencyBlock()

    LAYER_NAMES.forEach(name => {
      expect(toothMarks(name).length).toBeGreaterThanOrEqual(2)
      expect(tierRule(inner, name)).toMatch(/--color-accent-\d+/)
    })
    expect(inner).toMatch(/\[opacity\]\s*\{[^}]*opacity\s*:\s*1/)
  })

  it('keeps every tier above its visibility floor in solid mode', () => {
    const floors = { far: 0.09, mid: 0.3, near: 0.45 }
    const inner = reducedTransparencyBlock()
    const contrasts = LAYER_NAMES.map(name => {
      const step = tierRule(inner, name).match(/--color-accent-(\d+)/)[1]
      return perceivedContrast(tokens.colors.accent[step], 1)[0]
    })

    LAYER_NAMES.forEach((name, index) => {
      expect(
        contrasts[index] >= floors[name],
        `${name} solid tint is too faint: ${contrasts[index].toFixed(3)} contrast (floor ${floors[name]})`
      ).toBe(true)
    })
    expect(contrasts[0]).toBeLessThan(contrasts[1])
    expect(contrasts[1]).toBeLessThan(contrasts[2])
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
