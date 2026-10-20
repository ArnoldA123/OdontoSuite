import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { colors, radius } from '../../../resources/js/design-system/tokens.js'
import tailwindConfig from '../../../tailwind.config.js'

// #221 — the design-token utilities are the vocabulary every redesigned module
// speaks (`text-label`, `border-hairline`, `bg-canvas`, `rounded-ios`, ...).
// Tailwind only emits a utility when it can derive it from the theme OR when a
// plugin declares it. The nested token keys (`colors.label.label`,
// `colors.border.hairline`, `colors.background.canvas`) derive the long form
// (`text-label-label`), so a hand-written `text-label` is silently inert unless
// the plugin declares it. This guard fails on any token-shaped class used by
// the UI surface that neither the theme nor the plugin can generate.
//
// Scope: `resources/js/modules/**` (the 20 redesigned modules) and
// `resources/js/components/**` (the shared primitives and chrome they ride).
// Tailwind core utilities that do not derive from our tokens are out of scope:
// this guard answers "is this token class real?", not "is this class valid
// Tailwind?".

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..')
const SCANNED_DIRS = [join(ROOT, 'resources/js/modules'), join(ROOT, 'resources/js/components')]

// Prefixes whose suffix names a color token. Longest first so `border-t-*`
// is read as a side-color utility before the bare `border` color prefix.
const COLOR_PREFIXES = [
  'ring-offset',
  'border-t',
  'border-b',
  'border-l',
  'border-r',
  'text',
  'bg',
  'border',
  'divide',
  'ring',
  'outline',
  'from',
  'to',
  'via',
  'fill',
  'stroke',
  'decoration'
]

const RADIUS_PREFIXES = [
  'rounded-tl',
  'rounded-tr',
  'rounded-bl',
  'rounded-br',
  'rounded-t',
  'rounded-b',
  'rounded-l',
  'rounded-r',
  'rounded'
]

const COLOR_RAMPS = Object.keys(colors)
const COLOR_STEPS = new Set()
for (const ramp of Object.values(colors)) {
  if (ramp && typeof ramp === 'object') {
    for (const step of Object.keys(ramp)) COLOR_STEPS.add(step)
  }
}
const RADIUS_KEYS = Object.keys(radius)

function collectPluginUtilities() {
  const utilities = new Map()
  for (const plugin of tailwindConfig.plugins ?? []) {
    if (typeof plugin !== 'function') continue
    plugin({
      addUtilities: declarations => {
        for (const [selector, rules] of Object.entries(declarations)) {
          utilities.set(normalizeSelector(selector), rules)
        }
      }
    })
  }
  return utilities
}

// `.hover\:bg-theme-surface:hover` -> `hover:bg-theme-surface`; then the
// variant-aware comparison strips the variant.
function normalizeSelector(selector) {
  return selector
    .replace(/^\./, '')
    .split(/[\s>+~]/)[0]
    .replace(/\\:/g, ':')
    .split(':')
    .slice(0, 2)
    .join(':')
    .replace(/\\/g, '')
}

const PLUGIN_UTILITIES = collectPluginUtilities()
const PLUGIN_CLASSES = new Set(PLUGIN_UTILITIES.keys())

function themeUtilities() {
  const classes = new Set()

  for (const [ramp, value] of Object.entries(colors)) {
    const names = []
    if (value && typeof value === 'object') {
      for (const step of Object.keys(value)) names.push(`${ramp}-${step}`)
    } else {
      names.push(ramp)
    }
    for (const prefix of COLOR_PREFIXES) {
      for (const name of names) classes.add(`${prefix}-${name}`)
    }
  }

  for (const prefix of RADIUS_PREFIXES) {
    for (const key of RADIUS_KEYS) classes.add(`${prefix}-${key}`)
  }
  classes.add('rounded')

  return classes
}

const THEME_CLASSES = themeUtilities()

function walk(dir) {
  const files = []
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    if (statSync(full).isDirectory()) {
      files.push(...walk(full))
    } else if (entry.endsWith('.vue')) {
      files.push(full)
    }
  }
  return files
}

// Direct class-shaped tokens (variants included) instead of parsing quoted
// strings: Vue templates mix quote styles inside bindings (`:class="{...}"`),
// and a naive string split returns fragments that end mid-token. The
// lookbehind keeps the match from starting inside an identifier.
const ALL_PREFIXES = [...COLOR_PREFIXES, ...RADIUS_PREFIXES].sort((a, b) => b.length - a.length)
const CLASS_TOKEN = new RegExp(
  `(?<![\\w-])((?:[\\w[\\]&>=.-]+:)*(?:${ALL_PREFIXES.join('|')})-[A-Za-z][\\w-]*)`,
  'g'
)

function classTokens(source) {
  return new Set([...source.matchAll(CLASS_TOKEN)].map(match => match[1]))
}

function baseUtility(token) {
  // Variants (`hover:`, `focus-visible:`, `md:`, `[&>...]:`) leave the last
  // segment as the utility. Important (`!`) and color opacity (`/50`) are
  // modifiers, not part of the generated class name.
  let base = token.split(':').at(-1)
  base = base.replace(/^!/, '')
  base = base.split('/')[0]
  return base
}

function splitPrefix(base, prefixes) {
  for (const prefix of prefixes) {
    if (base === prefix) return { prefix, suffix: '' }
    if (base.startsWith(`${prefix}-`)) {
      return { prefix, suffix: base.slice(prefix.length + 1) }
    }
  }
  return null
}

// Returns the token family for a class that *should* be derivable from our
// design tokens, or null when the class is outside the token vocabulary.
function tokenFamily(base) {
  if (!base || base.includes('[') || base.includes('${') || base.includes('{')) return null

  const color = splitPrefix(base, COLOR_PREFIXES)
  if (color && color.suffix) {
    const first = color.suffix.split('-')[0]
    if (COLOR_RAMPS.includes(first) || COLOR_STEPS.has(first)) {
      return { kind: 'color', candidate: `${color.prefix}-${color.suffix}` }
    }
  }

  const rounded = splitPrefix(base, RADIUS_PREFIXES)
  if (rounded && rounded.suffix) {
    const first = rounded.suffix.split('-')[0]
    if (RADIUS_KEYS.includes(first)) {
      return { kind: 'radius', candidate: `${rounded.prefix}-${rounded.suffix}` }
    }
  }

  return null
}

describe('design-token utilities', () => {
  it('declares the canonical surface utilities the modules speak', () => {
    for (const utility of ['text-label', 'border-hairline', 'bg-canvas']) {
      expect(PLUGIN_CLASSES.has(utility), `plugin must declare .${utility}`).toBe(true)
    }
    expect(THEME_CLASSES.has('rounded-ios'), 'theme must derive .rounded-ios').toBe(true)
  })

  it('maps every canonical token utility to the generated CSS variable', () => {
    const expected = {
      'bg-canvas': { 'background-color': 'var(--color-canvas)' },
      'text-label': { color: 'var(--color-label-label)' },
      'border-hairline': { 'border-color': 'var(--color-hairline)' },
      'divide-hairline': { 'border-color': 'var(--color-hairline)' },
      'bg-systemBackground': {
        'background-color': 'var(--color-background-system-background)'
      },
      'text-systemBackground': { color: 'var(--color-background-system-background)' },
      'border-separator': { 'border-color': 'var(--color-separator-separator)' },
      'ring-accent': { '--tw-ring-color': 'var(--color-accent)' },
      'text-accent-hover': { color: 'var(--color-accent-hover)' }
    }

    for (const [utility, declaration] of Object.entries(expected)) {
      expect(PLUGIN_UTILITIES.get(utility), `.${utility} must be declared`).toEqual(declaration)
    }
  })

  it('every token-shaped class used by the UI surface is generable', () => {
    const used = new Map()

    for (const dir of SCANNED_DIRS) {
      for (const file of walk(dir)) {
        const source = readFileSync(file, 'utf8')
        for (const token of classTokens(source)) {
          const base = baseUtility(token)
          const full = token.replace(/^!/, '').split('/')[0]
          const family = tokenFamily(base)
          if (!family) continue
          // The plugin may declare a variant-specific class (`focus-visible:
          // outline-accent`); the full token is then real even when the bare
          // utility is not declared. Variant-capable plugin utilities match
          // through the base name.
          if (PLUGIN_CLASSES.has(full) || PLUGIN_CLASSES.has(base)) continue
          if (THEME_CLASSES.has(base)) continue
          const relative = file.replace(`${ROOT}/`, '')
          if (!used.has(family.candidate)) used.set(family.candidate, new Set())
          used.get(family.candidate).add(relative)
        }
      }
    }

    const report = [...used.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .map(
        ([utility, files]) =>
          `  .${utility} (${files.size} file(s)): ${[...files].sort().join(', ')}`
      )
      .join('\n')

    expect(
      used.size,
      `Token-shaped utilities that Tailwind cannot generate from the design tokens:\n${report}`
    ).toBe(0)
  })
})
