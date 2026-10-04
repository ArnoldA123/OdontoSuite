import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DentalParallaxBackground from '@/components/login/DentalParallaxBackground.vue'
import componentSource from '@/components/login/DentalParallaxBackground.vue?raw'
import loginPageSource from '@/modules/auth/LoginPage.vue?raw'
import tokens from '@/design-system/tokens'

const nativeMatchMedia = window.matchMedia

const LAYER_NAMES = ['far', 'mid', 'near']

// Marks position themselves in percentages of the overscanned layer frame and
// size themselves in viewport width units, so a width in vw over 100 is the
// fraction of the viewport a mark covers.
const SCENE_WIDTH = 1440
const SCENE_HEIGHT = 900
const DESKTOP_VIEWPORT = { width: SCENE_WIDTH, height: SCENE_HEIGHT }

// The card is centered at every viewport now: 26rem wide (416px) in the 900px
// tall frame, so the free zones are the top band, the bottom band and both
// flanks. The interior is the card inset past its border: a mark may peek
// behind the edge, never over the fields.
const DESKTOP_CARD = { left: 512, right: 928, top: 130, bottom: 770 }
const DESKTOP_INTERIOR = { left: 544, right: 896, top: 162, bottom: 738 }

// The user asked for roughly half the previous widths: teeth sit at 1.8-4.5%
// of the viewport (heroes at most 4.5%, typical teeth 1.8-3.5%), and the
// toothbrush may lead the largest tooth but never pass 6%.
const TOOTH_MIN = 0.018
const TOOTH_MAX = 0.045
const TYPICAL_TOOTH_MAX = 0.035
const BRUSH_MAX = 0.06
const BRUSH_LEAD = 1.35

// Only the plain tooth and the toothbrush were ever requested: the sparkle
// tooth (tooth-2.png) and its warm glow are retired.
const LOGIN_ASSETS = {
  tooth: '/images/login/tooth-1.png',
  toothbrush: '/images/login/toothbrush.png'
}

const MARK_MIX = { min: 12, max: 15, minBrushes: 5 }

// The shipped tooth PNG paints a near-flat light gray body (#dedede, channel
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
// --mark-y as frame percentages), the viewport-relative width (--mark-w in vw)
// and the rotation (--mark-r in deg). The optional --mark-sm-* set is the
// small-screen override the max-width: 640px block consumes; it stays optional
// here so the desktop contract keeps parsing on its own.
function marks(name) {
  return [...layerMarkup(name).matchAll(/<img\b[^>]*>/g)].map(([tag]) => {
    const attributes = tagAttributes(tag)
    const style = attributes.style ?? ''
    const read = pattern => {
      const match = style.match(pattern)
      return match ? Number(match[1]) : null
    }

    const x = read(/--mark-x:\s*(-?[\d.]+)%/)
    const y = read(/--mark-y:\s*(-?[\d.]+)%/)
    const w = read(/--mark-w:\s*(-?[\d.]+)vw/)
    const r = read(/--mark-r:\s*(-?[\d.]+)deg/)

    if (x === null || y === null || w === null || r === null) {
      throw new Error(`unparsable mark style: ${style}`)
    }

    const smX = read(/--mark-sm-x:\s*(-?[\d.]+)%/)
    const smY = read(/--mark-sm-y:\s*(-?[\d.]+)%/)
    const smW = read(/--mark-sm-w:\s*(-?[\d.]+)vw/)
    const smR = read(/--mark-sm-r:\s*(-?[\d.]+)deg/)

    return {
      layer: name,
      src: attributes.src,
      className: attributes.class ?? '',
      width: w / 100,
      rotate: r,
      position: { x: x / 100, y: y / 100 },
      sm:
        smX === null || smY === null || smW === null || smR === null
          ? null
          : { x: smX / 100, y: smY / 100, width: smW / 100, rotate: smR }
    }
  })
}

function toothMarks(name) {
  return marks(name).filter(mark => !mark.className.includes('toothbrush'))
}

function brushMarks(name) {
  return marks(name).filter(mark => mark.className.includes('toothbrush'))
}

function allMarks() {
  return LAYER_NAMES.flatMap(name => marks(name))
}

function allToothMarks() {
  return LAYER_NAMES.flatMap(name => toothMarks(name))
}

function allBrushMarks() {
  return LAYER_NAMES.flatMap(name => brushMarks(name))
}

// The layers overscan the viewport, so a mark percentage runs over the layer
// frame (viewport + 2 x overscan), not the raw viewport. The rotated bounding
// box is the mark's spatial footprint, because every asset is square-ish and
// rotates in place.
function layerOverscan() {
  const rule = cssRule('\\.dental-parallax-layer')
  const inset = rule.match(/inset:\s*-(\d+)px/)
  const grow = rule.match(/calc\(100%\s*\+\s*(\d+)px\)/)
  if (!inset || !grow) throw new Error('unparsable layer overscan')
  return { inset: Number(inset[1]), grow: Number(grow[1]) }
}

function markRect(mark, viewport, slot = 'base') {
  if (slot === 'sm' && !mark.sm) {
    throw new Error(`${mark.src} lacks the --mark-sm-* slot`)
  }

  const { inset, grow } = layerOverscan()
  const frame = { width: viewport.width + grow, height: viewport.height + grow }
  const placement = slot === 'sm' ? mark.sm : mark.position
  const width = slot === 'sm' ? mark.sm.width : mark.width
  const rotate = slot === 'sm' ? mark.sm.rotate : mark.rotate

  const centerX = -inset + placement.x * frame.width
  const centerY = -inset + placement.y * frame.height
  const size = width * viewport.width
  const radians = (rotate * Math.PI) / 180
  const half = (size / 2) * (Math.abs(Math.cos(radians)) + Math.abs(Math.sin(radians)))

  return {
    left: centerX - half,
    right: centerX + half,
    top: centerY - half,
    bottom: centerY + half,
    centerX,
    centerY,
    size
  }
}

function viewportRect(viewport) {
  return { left: 0, top: 0, right: viewport.width, bottom: viewport.height }
}

function rectArea(rect) {
  return (rect.right - rect.left) * (rect.bottom - rect.top)
}

function overlapArea(a, b) {
  const width = Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left))
  const height = Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top))
  return width * height
}

function visibleShare(mark, viewport, slot = 'base') {
  const rect = markRect(mark, viewport, slot)
  return overlapArea(rect, viewportRect(viewport)) / rectArea(rect)
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
    const parsed = allMarks()

    expect(images).toHaveLength(parsed.length)
    expect(images.every(image => image.attributes('alt') === '')).toBe(true)
    expect(new Set(images.map(image => image.attributes('src')))).toEqual(
      new Set(Object.values(LOGIN_ASSETS))
    )

    wrapper.unmount()
  })

  it('ships only the plain tooth and the toothbrush', () => {
    const srcs = allMarks().map(mark => mark.src)
    const unique = [...new Set(srcs)].sort()

    expect(unique).toEqual(Object.values(LOGIN_ASSETS).sort())
    expect(componentSource).not.toContain('tooth-2')
    expect(componentSource).not.toContain('dental-parallax-sparkle')
    expect(componentSource).not.toMatch(/sparkle/i)
    expect(componentSource).not.toMatch(/drop-shadow/)
    expect(componentSource).not.toMatch(/rgba\(/)
  })

  it('positions every mark with the center, size and rotation style contract', () => {
    allMarks().forEach(mark => {
      expect(Number.isFinite(mark.position.x)).toBe(true)
      expect(Number.isFinite(mark.position.y)).toBe(true)
      expect(mark.width).toBeGreaterThan(0)
      expect(Number.isFinite(mark.rotate)).toBe(true)
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

describe('DentalParallaxBackground mark mix', () => {
  it('mixes at least five toothbrush marks with plain teeth', () => {
    const all = allMarks()

    expect(all.length).toBeGreaterThanOrEqual(MARK_MIX.min)
    expect(all.length).toBeLessThanOrEqual(MARK_MIX.max)
    expect(allBrushMarks().length).toBeGreaterThanOrEqual(MARK_MIX.minBrushes)
    expect(allToothMarks().length).toBeGreaterThanOrEqual(MARK_MIX.minBrushes)
    expect(new Set(all.map(mark => mark.src)).size).toBe(2)
  })

  it('puts both assets in every tier so no tier reads as one asset only', () => {
    LAYER_NAMES.forEach(name => {
      expect(brushMarks(name).length, `${name} tier needs toothbrushes`).toBeGreaterThanOrEqual(1)
      expect(toothMarks(name).length, `${name} tier needs plain teeth`).toBeGreaterThanOrEqual(1)
    })
  })

  it('varies sizes and rotations instead of stamping one recipe', () => {
    const widths = new Set(allMarks().map(mark => mark.width))
    const rotations = new Set(allMarks().map(mark => mark.rotate))

    expect(widths.size).toBeGreaterThanOrEqual(6)
    expect(rotations.size).toBeGreaterThanOrEqual(8)
  })

  it('spreads both assets across the canvas thirds', () => {
    const rects = allMarks().map(mark => ({
      mark,
      rect: markRect(mark, DESKTOP_VIEWPORT)
    }))

    const thirds = [
      [0, SCENE_WIDTH / 3],
      [SCENE_WIDTH / 3, (2 * SCENE_WIDTH) / 3],
      [(2 * SCENE_WIDTH) / 3, SCENE_WIDTH]
    ]

    thirds.forEach(([left, right]) => {
      const inThird = rects.filter(({ rect }) => rect.centerX >= left && rect.centerX < right)
      const brushes = inThird.filter(({ mark }) => mark.className.includes('toothbrush'))
      const teeth = inThird.filter(({ mark }) => !mark.className.includes('toothbrush'))

      expect(brushes.length, `no toothbrush in the ${left}-${right} third`).toBeGreaterThanOrEqual(
        1
      )
      expect(teeth.length, `no plain tooth in the ${left}-${right} third`).toBeGreaterThanOrEqual(1)
    })
  })
})

describe('DentalParallaxBackground size budget', () => {
  it('draws every tooth inside the reduced scale budget', () => {
    const teeth = allToothMarks()

    expect(teeth.length).toBeGreaterThanOrEqual(7)
    teeth.forEach(mark => {
      expect(mark.width).toBeLessThanOrEqual(TOOTH_MAX)
      expect(mark.width).toBeGreaterThanOrEqual(TOOTH_MIN)
    })
  })

  it('keeps far and mid teeth typical and the near heroes under 4.5%', () => {
    ;['far', 'mid'].forEach(name => {
      toothMarks(name).forEach(mark => {
        expect(mark.width, `${name} mark ${mark.src}`).toBeLessThanOrEqual(TYPICAL_TOOTH_MAX)
      })
    })

    const heroes = toothMarks('near')
    expect(heroes.length).toBeGreaterThanOrEqual(2)
    heroes.forEach(mark => {
      expect(mark.width).toBeGreaterThanOrEqual(0.034)
      expect(mark.width).toBeLessThanOrEqual(TOOTH_MAX)
    })
  })

  it('caps the toothbrush just above the largest tooth', () => {
    LAYER_NAMES.forEach(name => {
      const largest = Math.max(...toothMarks(name).map(mark => mark.width))
      brushMarks(name).forEach(mark => {
        expect(mark.width, `${name} brush ${mark.src}`).toBeGreaterThan(largest)
        expect(mark.width).toBeLessThanOrEqual(largest * BRUSH_LEAD)
        expect(mark.width).toBeLessThanOrEqual(BRUSH_MAX)
      })
    })
  })

  it('keeps the depth order: the far tier is the smallest and the near tier the largest', () => {
    const widest = name => Math.max(...toothMarks(name).map(mark => mark.width))

    expect(widest('far')).toBeLessThan(widest('mid'))
    expect(widest('mid')).toBeLessThan(widest('near'))
  })

  it('keeps the small-screen overrides inside the same budget', () => {
    LAYER_NAMES.forEach(name => {
      const largestTooth = Math.max(...toothMarks(name).map(mark => mark.sm.width))

      toothMarks(name).forEach(mark => {
        expect(mark.sm.width).toBeGreaterThanOrEqual(TOOTH_MIN)
        expect(mark.sm.width).toBeLessThanOrEqual(TOOTH_MAX)
      })

      brushMarks(name).forEach(mark => {
        expect(mark.sm.width).toBeGreaterThanOrEqual(largestTooth)
        expect(mark.sm.width).toBeLessThanOrEqual(largestTooth * BRUSH_LEAD)
        expect(mark.sm.width).toBeLessThanOrEqual(BRUSH_MAX)
      })
    })
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

describe('DentalParallaxBackground centered card composition', () => {
  it('keeps every mark clear of the centered card interior', () => {
    allMarks().forEach(mark => {
      const rect = markRect(mark, DESKTOP_VIEWPORT)
      const covered = overlapArea(rect, DESKTOP_INTERIOR)

      expect(
        covered,
        `${mark.layer} mark ${mark.src} at (${rect.centerX.toFixed(0)}, ${rect.centerY.toFixed(0)}) covers the form interior`
      ).toBe(0)
    })
  })

  it('never centres a mark inside the centered card band', () => {
    allMarks().forEach(mark => {
      const { centerX, centerY } = markRect(mark, DESKTOP_VIEWPORT)
      const behindCard =
        centerX > DESKTOP_CARD.left &&
        centerX < DESKTOP_CARD.right &&
        centerY > DESKTOP_CARD.top &&
        centerY < DESKTOP_CARD.bottom

      expect(behindCard, `${mark.layer} mark sits under the centered card`).toBe(false)
    })
  })

  it('distributes marks on both flanks plus the top and bottom bands', () => {
    const rects = allMarks().map(mark => markRect(mark, DESKTOP_VIEWPORT))

    expect(rects.filter(rect => rect.centerX < DESKTOP_CARD.left).length).toBeGreaterThanOrEqual(3)
    expect(rects.filter(rect => rect.centerX > DESKTOP_CARD.right).length).toBeGreaterThanOrEqual(3)
    expect(rects.filter(rect => rect.bottom <= DESKTOP_CARD.top).length).toBeGreaterThanOrEqual(2)
    expect(rects.filter(rect => rect.top >= DESKTOP_CARD.bottom).length).toBeGreaterThanOrEqual(2)
  })

  it('spreads the deep tier across both halves so the scene breathes everywhere', () => {
    const far = toothMarks('far')

    expect(far.length).toBeGreaterThanOrEqual(3)
    expect(
      far.filter(mark => markRect(mark, DESKTOP_VIEWPORT).centerX < SCENE_WIDTH / 2).length
    ).toBeGreaterThanOrEqual(1)
    expect(
      far.filter(mark => markRect(mark, DESKTOP_VIEWPORT).centerX >= SCENE_WIDTH / 2).length
    ).toBeGreaterThanOrEqual(1)
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

describe('DentalParallaxBackground small-screen composition', () => {
  const VIEWPORT = { width: 390, height: 844 }

  // The centered card envelope on the reference viewport, inset past the card
  // border: side-edge peeks behind the border are allowed, marks over the
  // fields are not.
  const CARD_KEEP_OUT = { left: 36, right: 354, top: 120, bottom: 724 }

  const smallScreenRects = () =>
    allMarks().map(mark => ({ mark, rect: markRect(mark, VIEWPORT, 'sm') }))

  const visibleRects = () =>
    smallScreenRects().filter(
      ({ rect }) => overlapArea(rect, viewportRect(VIEWPORT)) / rectArea(rect) >= 0.75
    )

  it('declares the small-screen coordinate set on every mark', () => {
    allMarks().forEach(mark => {
      expect(mark.sm, `${mark.layer} mark ${mark.src} lacks a --mark-sm-* set`).not.toBeNull()
      expect(mark.sm.width).toBeGreaterThan(0)
      expect(Number.isFinite(mark.sm.rotate)).toBe(true)
    })
  })

  it('rebinds the mark layout in the small-screen block so the inline custom properties stay fallbacks', () => {
    expect(componentSource).toContain('@media (max-width: 640px)')

    const rule = mediaBlock('max-width: 640px').match(/\.dental-parallax-mark\s*\{([^}]*)\}/)?.[1]
    expect(rule, 'the small-screen block must retarget the mark layout').toBeTruthy()

    // The override rebinds the stylesheet-owned layout properties, never the
    // inline custom properties, so no cascade trick (or !important) is load
    // bearing: the media block simply wins the layout declaration.
    expect(rule).toMatch(/left:\s*var\(--mark-sm-x,\s*var\(--mark-x\)\)/)
    expect(rule).toMatch(/top:\s*var\(--mark-sm-y,\s*var\(--mark-y\)\)/)
    expect(rule).toMatch(/width:\s*var\(--mark-sm-w,\s*var\(--mark-w\)\)/)
    expect(rule).toMatch(/rotate\(var\(--mark-sm-r,\s*var\(--mark-r,\s*0deg\)\)\)/)
    expect(rule).not.toMatch(/!important/)

    // Source order is what lets the block win over the base mark rule.
    expect(componentSource.indexOf('@media (max-width: 640px)')).toBeGreaterThan(
      componentSource.indexOf('left: var(--mark-x)')
    )

    // The override is consumed in exactly one place, so desktop and tablet
    // cannot pick it up.
    expect(componentSource.match(/var\(--mark-sm-x/g)).toHaveLength(1)
    expect(componentSource.match(/var\(--mark-sm-y/g)).toHaveLength(1)
    expect(componentSource.match(/var\(--mark-sm-w/g)).toHaveLength(1)
    expect(componentSource.match(/var\(--mark-sm-r/g)).toHaveLength(1)
  })

  it('composes at least twelve visible marks around the small-screen card', () => {
    const visible = visibleRects()

    expect(visible.length).toBeGreaterThanOrEqual(12)

    LAYER_NAMES.forEach(name => {
      expect(
        visible.filter(({ mark }) => mark.layer === name).length,
        `${name} tier contributes no visible small-screen mark`
      ).toBeGreaterThanOrEqual(1)
    })
  })

  it('spreads the small-screen marks into the top band, the bottom band and both side edges', () => {
    const visible = visibleRects().map(({ rect }) => rect)

    const topBand = visible.filter(rect => rect.bottom <= CARD_KEEP_OUT.top)
    const bottomBand = visible.filter(rect => rect.top >= CARD_KEEP_OUT.bottom)
    const leftEdge = visible.filter(rect => rect.left <= 0)
    const rightEdge = visible.filter(rect => rect.right >= VIEWPORT.width)

    expect(topBand.length).toBeGreaterThanOrEqual(3)
    expect(bottomBand.length).toBeGreaterThanOrEqual(3)
    expect(leftEdge.length).toBeGreaterThanOrEqual(1)
    expect(rightEdge.length).toBeGreaterThanOrEqual(1)
  })

  it('keeps every small-screen mark off the form card interactive band', () => {
    smallScreenRects().forEach(({ mark, rect }) => {
      expect(
        overlapArea(rect, CARD_KEEP_OUT),
        `${mark.layer} mark ${mark.src} overlaps the small-screen card band`
      ).toBe(0)
    })
  })

  it('keeps every small-screen toothbrush fully visible and just above the largest tooth', () => {
    const brushes = allBrushMarks()
    expect(brushes.length).toBeGreaterThanOrEqual(5)

    const largestBrush = Math.max(...brushes.map(mark => mark.sm.width))
    const largestTooth = Math.max(...allToothMarks().map(mark => mark.sm.width))

    brushes.forEach(mark => {
      expect(
        visibleShare(mark, VIEWPORT, 'sm'),
        `${mark.src} is clipped on small screens`
      ).toBeGreaterThanOrEqual(0.75)
      expect(mark.sm.width).toBeLessThanOrEqual(BRUSH_MAX)
    })

    // The biggest brush may lead the biggest tooth by a step, never past the
    // cap the user set (roughly 5-6% of the viewport, not the old 26vw).
    expect(largestBrush).toBeGreaterThanOrEqual(largestTooth)
    expect(largestBrush).toBeLessThanOrEqual(largestTooth * BRUSH_LEAD)
  })

  it('keeps the clipping chain that makes horizontal overflow impossible', () => {
    const scene = cssRule('\\.dental-parallax')

    expect(scene).toMatch(/position:\s*absolute/)
    expect(scene).toMatch(/overflow:\s*hidden/)
    expect(loginPageSource).toMatch(/\.login-page\s*\{[^}]*overflow-x:\s*hidden/)
    expect(loginPageSource).toMatch(/\.login-backdrop\s*\{[^}]*position:\s*fixed/)
    expect(loginPageSource).toMatch(/\.login-backdrop\s*\{[^}]*overflow:\s*hidden/)
  })
})
