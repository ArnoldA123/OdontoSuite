import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import Component from '@/components/login/DentalParallaxBackground.vue'
import source from '@/components/login/DentalParallaxBackground.vue?raw'

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

// The tile contract: [glyph, left, top, size, rotation] per mark, measured in
// design px over a 1440 x 900 tile. Parsed straight from the component so the
// budgets below describe the data the scene actually paints.
function parsePlanes() {
  return ['FAR', 'MID', 'NEAR'].map(id => {
    const start = source.indexOf(`const ${id}_MARKS = [`)
    const body = source.slice(start, source.indexOf('\n]', start))
    const marks = [
      ...body.matchAll(/\[(TOOTH|BRUSH),\s*([\d.]+),\s*([\d.]+),\s*([\d.]+),\s*(-?[\d.]+)\]/g)
    ].map(match => ({
      glyph: match[1].toLowerCase(),
      x: Number(match[2]),
      y: Number(match[3]),
      size: Number(match[4]),
      rotation: Number(match[5])
    }))
    return { id: id.toLowerCase(), marks }
  })
}

afterEach(() => {
  window.matchMedia = nativeMatchMedia
  globalThis.matchMedia = nativeMatchMedia
  vi.restoreAllMocks()
})

describe('DentalParallaxBackground surface', () => {
  it('paints one flat pastel field, never a gradient', () => {
    expect(source).toContain('background: var(--color-accent-50)')
    expect(source).not.toContain('linear-gradient')
    expect(source).not.toMatch(/rgba?\(/)
    expect(source).not.toMatch(/#[0-9a-fA-F]{3,8}\b/)
  })

  it('stays a decorative, non-interactive scene', () => {
    const wrapper = mount(Component)
    expect(wrapper.element.getAttribute('aria-hidden')).toBe('true')
    expect(wrapper.classes()).toContain('dental-parallax')
    wrapper.unmount()
  })

  it('keeps the login backdrop below the words it sits behind', () => {
    expect(source).toContain('pointer-events: none')
  })
})

describe('DentalParallaxBackground planes', () => {
  it('stacks three planes far, mid and near', () => {
    const wrapper = mount(Component)
    const layers = wrapper.findAll('[data-layer]')
    expect(layers.map(layer => layer.attributes('data-layer'))).toEqual(['far', 'mid', 'near'])
    wrapper.unmount()
  })

  it('gives each plane its own slow clock, the nearest the fastest', () => {
    const wrapper = mount(Component)
    const durations = wrapper
      .findAll('[data-layer]')
      .map(layer => parseFloat(layer.element.style.getPropertyValue('--drift-duration')))
    expect(durations).toEqual([200, 135, 90])
    wrapper.unmount()
  })

  it('drifts linearly and forever, so the field never pauses or eases', () => {
    expect(source).toContain('animation-timing-function: linear')
    expect(source).toContain('animation-iteration-count: infinite')
    expect(source).toContain('@keyframes dental-parallax-drift')
  })
})

describe('DentalParallaxBackground loop closure', () => {
  it('repeats the tile twice per plane so the loop has a relay to hand over to', () => {
    const wrapper = mount(Component)
    for (const layer of wrapper.findAll('[data-layer]')) {
      expect(layer.findAll('.dental-parallax-tile')).toHaveLength(2)
    }
    wrapper.unmount()
  })

  it('offsets the relay tile by exactly one tile height', () => {
    expect(source).toMatch(/\.dental-parallax-tile--relay\s*\{[^}]*top:\s*var\(--drift-tile-h\)/s)
  })

  it('travels exactly one tile height, so the seam never lands', () => {
    expect(source).toMatch(
      /@keyframes dental-parallax-drift\s*\{[\s\S]*?to\s*\{[^}]*translate3d\(\s*0,\s*calc\(-1 \* var\(--drift-tile-h\)\),\s*0\s*\)/s
    )
  })

  it('never lets the tile be shorter than the window it must cover', () => {
    expect(source).toMatch(/--drift-tile-h:\s*max\(900px,\s*100lvh\)/)
  })

  it('animates transform only, so the loop never triggers layout or paint', () => {
    const keyframes = source.slice(source.indexOf('@keyframes dental-parallax-drift'))
    const body = keyframes.slice(0, keyframes.indexOf('}'))
    expect(keyframes).toContain('translate3d')
    expect(body).not.toMatch(/\b(left|top|width|height|margin|padding):/)
  })
})

describe('DentalParallaxBackground marks', () => {
  it('draws both assets as alpha masks painted in the accent ramp, never as images', () => {
    expect(source).toContain('mask-image: var(--glyph)')
    expect(source).toContain("url('/images/login/tooth-1.png')")
    expect(source).toContain("url('/images/login/toothbrush.png')")
    expect(source).not.toContain('<img')
  })

  it('steps the ink down the ramp: far palest, near saturated, brush one more', () => {
    expect(source).toMatch(
      /\.dental-parallax-plane--far \.dental-parallax-mark\s*\{[^}]*--color-accent-100/s
    )
    expect(source).toMatch(
      /\.dental-parallax-plane--near \.dental-parallax-mark\s*\{[^}]*--color-accent-200/s
    )
    expect(source).toMatch(/\.dental-parallax-mark--brush\s*\{[^}]*--color-accent-300/s)
  })

  it('never fakes depth with transparency', () => {
    expect(source).not.toMatch(/^\s*opacity:/m)
    expect(source).not.toMatch(/brightness\(/)
  })

  it('keeps every mark inside the 1440 x 900 tile', () => {
    const planes = parsePlanes()
    expect(planes).toHaveLength(3)
    for (const plane of planes) {
      expect(plane.marks.length).toBeGreaterThanOrEqual(9)
      for (const mark of plane.marks) {
        expect(mark.x).toBeGreaterThanOrEqual(0)
        expect(mark.x).toBeLessThanOrEqual(1440)
        expect(mark.y).toBeGreaterThanOrEqual(0)
        expect(mark.y).toBeLessThanOrEqual(900)
      }
    }
  })

  it('puts both assets in every plane so no plane reads as one glyph', () => {
    for (const plane of parsePlanes()) {
      const glyphs = new Set(plane.marks.map(mark => mark.glyph))
      expect([...glyphs].sort()).toEqual(['brush', 'tooth'])
    }
  })

  it('keeps the tooth smaller than the brush and both small enough to stay ambient', () => {
    for (const plane of parsePlanes()) {
      const teeth = plane.marks.filter(mark => mark.glyph === 'tooth')
      for (const tooth of teeth) {
        expect(tooth.size).toBeLessThanOrEqual(44)
        expect(tooth.size).toBeGreaterThanOrEqual(11)
      }
    }
  })

  it('spreads every plane across the left, middle and right thirds', () => {
    for (const plane of parsePlanes()) {
      const thirds = new Set(plane.marks.map(mark => Math.min(2, Math.floor(mark.x / 480))))
      expect([...thirds].sort()).toEqual([0, 1, 2])
    }
  })

  it('varies sizes and rotations instead of stamping one recipe', () => {
    for (const plane of parsePlanes()) {
      const rotations = new Set(plane.marks.map(mark => mark.rotation))
      expect(rotations.size).toBeGreaterThan(1)
      expect(Math.max(...plane.marks.map(mark => Math.abs(mark.rotation)))).toBeGreaterThan(15)
    }
  })

  it('positions each mark from its own centre-free box, never a shared offset', () => {
    expect(source).toMatch(/left:\s*`\$\{x\}px`/)
    expect(source).toMatch(/top:\s*`\$\{y\}px`/)
    expect(source).toContain('transform: `rotate(${rotation}deg)`')
  })
})

describe('DentalParallaxBackground immobility under reduced motion', () => {
  it('never starts the drift for a reduced-motion user', async () => {
    mockMedia({ reducedMotion: true })
    const wrapper = mount(Component)
    await nextTick()
    expect(wrapper.classes()).not.toContain('has-drift')
    wrapper.unmount()
  })

  it('paints the field and every mark statically for that user', () => {
    mockMedia({ reducedMotion: true })
    const wrapper = mount(Component)
    expect(wrapper.findAll('.dental-parallax-mark').length).toBeGreaterThan(0)
    wrapper.unmount()
  })

  it('runs the drift when motion is allowed', () => {
    mockMedia({ reducedMotion: false })
    const wrapper = mount(Component)
    expect(wrapper.classes()).toContain('has-drift')
    wrapper.unmount()
  })

  it('kills the loop in the reduced-motion CSS block too, for a live flip', () => {
    const block = source.slice(source.indexOf('@media (prefers-reduced-motion: reduce)'))
    expect(block).toContain('animation: none')
    expect(block).toContain('transform: none')
  })

  it('follows no pointer and reads no layout: the scene attaches no listener', () => {
    mockMedia({ reducedMotion: false })
    const addSpy = vi.spyOn(window, 'addEventListener')
    const documentSpy = vi.spyOn(document, 'addEventListener')

    const wrapper = mount(Component)
    const types = [...addSpy.mock.calls, ...documentSpy.mock.calls].map(([type]) => type)

    expect(types).not.toContain('pointermove')
    expect(types).not.toContain('mousemove')
    expect(types).not.toContain('scroll')
    expect(wrapper.element.style.getPropertyValue('--parallax-x')).toBe('')
    wrapper.unmount()
  })

  it('carries no pointer machinery of its own', () => {
    expect(source).not.toContain('usePointerParallax')
    expect(source).not.toContain('--parallax')
    expect(source).not.toContain('requestAnimationFrame')
  })
})
