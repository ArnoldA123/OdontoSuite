#!/usr/bin/env node
/**
 * build-tokens-css.mjs — emits resources/css/tokens.generated.css from
 * resources/js/design-system/tokens.js. The generator is the only durable
 * answer to token drift (design Decision 1): tokens.js is the single
 * source of truth; the build pipeline and the runtime never disagree on a
 * hex value.
 *
 * Output layout (single file, top-to-bottom):
 *
 *   1. (no @font-face — system font only per Decision 6)
 *   2. One :root block with --color-systemBlue-*, --color-systemRed-*,
 *      --color-systemGray-*, --color-background-*, --color-label-*,
 *      --color-separator-*, --color-fill-* + the iOS semantic aliases
 *      (--color-cream-*, --color-terracotta-*, --color-clinical-teal-*,
 *      --color-info-*) for the 17 un-migrated modules.
 *   3. Semantic aliases: --color-accent (systemBlue-500),
 *      --color-surface (systemBackground / secondaryBackground),
 *      --color-text-* (label ramp), --color-border (separator),
 *      --color-success-* / --color-warning-* / --color-danger-*.
 *   4. Glass tokens (--glass-bg / --glass-border / --glass-backdrop).
 *   5. Shadows using rgba(0, 0, 0, ...) (pure black, Decision 5).
 *   6. Motion vars: --motion-response-default, --motion-damping-*,
 *      --motion-easing-*.
 *   7. .surface-glass class (chrome-only Liquid-Glass approximation,
 *      white-on-white rgba).
 *   8. @media (prefers-reduced-transparency: reduce) block.
 *   9. @media (prefers-contrast: more) block.
 *
 * Run with: `node scripts/build-tokens-css.mjs` or `pnpm tokens:build`.
 *
 * Idempotency: running the script twice produces a byte-identical output.
 * Do not hand-edit the generated file.
 */

import { writeFileSync, mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { pathToFileURL } from 'node:url'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const projectRoot = resolve(__dirname, '..')

const tokensModulePath = resolve(projectRoot, 'resources/js/design-system/tokens.js')
const outputCssPath = resolve(projectRoot, 'resources/css/tokens.generated.css')

const { default: tokens, colors, spacing, radius, shadow, motion, focusRing, fontFeatures, elevation, topbar } = await import(pathToFileURL(tokensModulePath).href)

/* ------------------------------------------------------------------ */
/* helpers                                                            */
/* ------------------------------------------------------------------ */

const lines = []
const push = (s = '') => lines.push(s)

function emitSection(title) {
  push('')
  push(`/* ${title} */`)
}

/* ------------------------------------------------------------------ */
/* 1. (skipped) @font-face — system font only (Decision 6)         */
/* ------------------------------------------------------------------ */

/* ------------------------------------------------------------------ */
/* 2. :root — ramp variables                                          */
/* ------------------------------------------------------------------ */

emitSection(':root — token ramps and aliases (iOS 13+ clinical)')
push(':root {')

// Color ramps. Token keys are camelCase in JS (`systemBlue`) but CSS
// custom properties are kebab-case by convention (`--color-system-blue-500`).
// Convert once, here, so the two can never disagree.
const toKebab = (s) => s.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase()

for (const rampName of Object.keys(colors)) {
  // PR1 (ui-premium-microdetail-2026-08): the `border` ramp is not a real
  // ramp — it exists only to anchor `tokens.colors.border.hairline`. Emit
  // that value as the semantic alias `--color-hairline` below; skipping it
  // here prevents the doubled `--color-border-hairline` and the
  // `--color-hairline-hairline` regression the design flagged.
  if (rampName === 'border') {
    continue
  }
  const ramp = colors[rampName]
  const cssName = toKebab(rampName)
  push(`  /* ${rampName} */`)
  for (const step of Object.keys(ramp)) {
    push(`  --color-${cssName}-${toKebab(step)}: ${ramp[step]};`)
  }
  push('')
}

// Semantic aliases (Decision 5: revalue to iOS clinical).
push('  /* semantic aliases (iOS clinical) */')
push('  --color-accent: var(--color-system-blue-500);')
push('  --color-accent-hover: var(--color-system-blue-600);')
push('  --color-accent-active: var(--color-system-blue-700);')
push('  --color-accent-light: var(--color-system-blue-50);')
push('  --color-primary: var(--color-system-blue-500);')
push('  --color-primary-hover: var(--color-system-blue-600);')
push('  --color-primary-active: var(--color-system-blue-700);')
push('  --color-primary-light: var(--color-system-blue-50);')
push('  --color-primary-dark: var(--color-system-blue-700);')
push('')

push('  --color-background: var(--color-background-system-background);')
push('  --color-background-secondary: var(--color-background-secondary-background);')
push('  --color-surface: var(--color-background-secondary-background);')
push('  --color-surface-elevated: var(--color-background-system-background);')
push('')
// PR1 (ui-premium-microdetail-2026-08) — semantic alias for the canvas
// token. `tokens.colors.background.canvas` is iterated by the colors loop
// above and emitted as `--color-background-canvas`; this alias gives
// consumers a stable public name (`var(--color-canvas)`).
push('  --color-canvas: var(--color-background-canvas);')
push('')

push('  --color-text-primary: var(--color-label-label);')
push('  --color-text-secondary: var(--color-label-secondary-label);')
push('  --color-text-tertiary: var(--color-label-tertiary-label);')
push('  --color-text-inverse: var(--color-background-system-background);')
push('')

push('  --color-border: var(--color-separator-separator);')
push('  --color-border-light: var(--color-system-gray-100);')
push('  --color-border-strong: var(--color-system-gray-500);')
push('')
// PR1 (ui-premium-microdetail-2026-08) — hairline alpha-border semantic
// alias. The colors loop skips `border` (above), so this is the only
// public declaration of the hairline. Emitted as `rgba(60, 60, 67, 0.12)`
// (iOS separator opacity).
push('  --color-hairline: rgba(60, 60, 67, 0.12);')
push('')

push('  --color-info: var(--color-info-500);')
push('  --color-info-light: var(--color-system-blue-50);')
push('  --color-info-dark: var(--color-system-blue-700);')
push('')

push('  --color-success-bg: var(--color-system-green-50);')
push('  --color-success-text: var(--color-system-green-700);')
push('  --color-success-light: var(--color-system-green-50);')
push('  --color-success-dark: var(--color-system-green-700);')
push('')

push('  --color-warning-bg: var(--color-system-yellow-50);')
push('  --color-warning-text: var(--color-system-yellow-700);')
push('  --color-warning-light: var(--color-system-yellow-50);')
push('  --color-warning-dark: var(--color-system-yellow-700);')
push('')

push('  --color-danger: var(--color-system-red-500);')
push('  --color-danger-bg: var(--color-system-red-50);')
push('  --color-danger-text: var(--color-system-red-700);')
push('  --color-danger-light: var(--color-system-red-50);')
push('  --color-danger-dark: var(--color-system-red-700);')
push('')

push('  /* glass effect (chrome only) */')
push('  --glass-bg: rgba(255, 255, 255, 0.78);')
push('  --glass-border: rgba(255, 255, 255, 0.22);')
push('  --glass-backdrop: blur(20px) saturate(180%) contrast(1.04);')
push('')

push('  /* shadows (pure-black rgba per Decision 5) */')
push('  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);')
push('  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.10), 0 2px 4px -1px rgba(0, 0, 0, 0.06);')
push('  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.10), 0 4px 6px -2px rgba(0, 0, 0, 0.05);')
push('  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.10), 0 10px 10px -5px rgba(0, 0, 0, 0.04);')
push('  --shadow-glass: 0 8px 32px 0 rgba(0, 0, 0, 0.20);')
push('')

push('  /* font stacks (system font only) */')
push('  --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;')
push('')

// Spacing aliases — kept for the few surviving consumers (Card.vue, etc.)
// that reference `var(--spacing-4)` directly instead of using Tailwind
// utility classes.
push('  /* spacing */')
for (const step of Object.keys(spacing)) {
  push(`  --spacing-${step}: ${spacing[step]};`)
}
push('')

// Radius aliases — iOS clinical scale. Apply toKebab() so camelCase JS
// keys (e.g. `cardLg`) become kebab-case CSS names (`--radius-card-lg`).
// The previous radii were all single-word, so toKebab was a no-op; PR1
// (ui-premium-microdetail-2026-08) introduces `cardLg`.
push('  /* radius */')
for (const name of Object.keys(radius)) {
  push(`  --radius-${toKebab(name)}: ${radius[name]};`)
}
push('')

// Shadow aliases — used by Avatar/Card/Badge/Toast primitive components
// that reference `var(--shadow-medium)` etc. directly.
push('  /* shadow */')
for (const name of Object.keys(shadow)) {
  push(`  --shadow-${name}: ${shadow[name]};`)
}
push('')

// LoadingSpinner reads --spinner-color at runtime. Default = systemBlue-500.
push('  /* spinner color (default for LoadingSpinner primitive) */')
push('  --spinner-color: var(--color-accent);')
push('')

// Transition aliases — surviving consumers reference --transition-fast,
// --transition-normal, --transition-slow (used in ReceiptPreview etc.).
push('  /* transition timing */')
push('  --transition-fast: 150ms ease-out;')
push('  --transition-normal: 200ms ease-out;')
push('  --transition-slow: 300ms ease-out;')
push('')

/* ------------------------------------------------------------------ */
/* 3. Motion vars                                                     */
/* ------------------------------------------------------------------ */

push('  /* motion */')
push(`  --motion-response-default: ${motion.response}s;`)
push(`  --motion-damping-default: ${motion.damping};`)
push(`  --motion-damping-bounce: ${motion.dampingBounce};`)
push(`  --motion-stiffness-default: ${motion.stiffness};`)
// PR1 (ui-premium-microdetail-2026-08) — motion.duration ramp (exactly three
// keys). `instant` and `spring` were dropped (dead tokens). The ramp emits
// `--motion-duration-fast|normal|slow`.
push('  /* motion.duration (PR1) */')
for (const step of Object.keys(motion.duration)) {
  push(`  --motion-duration-${step}: ${motion.duration[step]};`)
}
push('  --motion-easing-standard: cubic-bezier(0.4, 0.0, 0.2, 1);')
push('  --motion-easing-decel: cubic-bezier(0.0, 0.0, 0.2, 1);')
push('  --motion-easing-accel: cubic-bezier(0.4, 0.0, 1, 1);')
push('  --motion-easing-ios: cubic-bezier(0.25, 0.46, 0.45, 0.94);')
push('')

// PR1 (ui-premium-microdetail-2026-08) — tinted, layered elevation ramp
// using the iOS label/separator hue family `rgba(60, 60, 67, α)`. Rungs
// 2..4 are two layers per Apple rule that bigger surfaces read thicker.
// NO rung uses `rgba(0,0,0,α)` — that was the cheap-looking defect being
// fixed.
push('  /* elevation (PR1) */')
for (const rung of Object.keys(elevation)) {
  push(`  --elevation-${rung}: ${elevation[rung]};`)
}
push('')

// PR1 (ui-premium-microdetail-2026-08) — composed focus ring. Emit parts
// first (so consumers can compose their own colours, e.g. error states),
// then the composed `--focus-ring-default` shorthand.
push('  /* focus ring (PR1) */')
push(`  --focus-ring-width: ${focusRing.width};`)
// `focusRing.color` is the hex form `#007AFF`; the shorthand below uses
// rgba() to apply the alpha slot. The colour var keeps the hex for parity
// with `tokens.colors.systemBlue[500]`; both forms are accepted by the
// generated-CSS test.
push(`  --focus-ring-color: ${focusRing.color};`)
push(`  --focus-ring-alpha: ${focusRing.alpha};`)
push(`  --focus-ring-offset: ${focusRing.offset};`)
push(`  --focus-ring-default: 0 0 0 var(--focus-ring-width) rgba(0, 122, 255, var(--focus-ring-alpha));`)
push('')

// PR1 (ui-premium-microdetail-2026-08) — font features for tabular nums.
// The value is a valid CSS `font-feature-settings` declaration; the literal
// Tailwind utility name `tabular-nums` is NOT a valid value.
push('  /* font features (PR1) */')
push(`  --font-features-tabular-nums: ${fontFeatures.tabularNums};`)

// PR4 (ui-premium-microdetail-2026-08) — topbar control tokens. The WS
// dot, bell, and avatar consume the same size + weight so the row reads
// as one optical unit (defect 10 fix).
push('')
push('  /* topbar (PR4) */')
push(`  --topbar-icon-size: ${topbar.iconSize};`)
push(`  --topbar-icon-weight: ${topbar.iconWeight};`)
push(`  --topbar-control: ${topbar.control};`)
push(`  --topbar-control-lg: ${topbar.controlLg};`)

push('}')

/* ------------------------------------------------------------------ */
/* 4. .surface-glass — chrome-only Liquid-Glass approximation        */
/* ------------------------------------------------------------------ */

emitSection('Liquid-Glass chrome class — web approximation (chrome only)')
push('/* Web approximation, not official Apple Liquid Glass. Used only in')
push('   AppLayout sidebar, AppLayout top bar, and the Sheet wrapper around')
push('   the mobile menu. Data cards (Card.vue variant="glass") are opaque. */')
push('.surface-glass {')
push('  position: relative;')
push('  isolation: isolate;')
push('  overflow: hidden;')
push('  background: linear-gradient(135deg, rgb(255 255 255 / 0.78), rgb(255 255 255 / 0.62));')
push('  backdrop-filter: blur(20px) saturate(180%) contrast(1.04);')
push('  -webkit-backdrop-filter: blur(20px) saturate(180%) contrast(1.04);')
push('  border-right: 1px solid rgb(0 0 0 / 0.06);')
push('  box-shadow:')
push('    inset 0 1px 0 rgb(255 255 255 / 0.55),')
push('    inset 0 -1px 0 rgb(0 0 0 / 0.04),')
push('    0 18px 40px -16px rgb(0 0 0 / 0.10);')
push('}')
push('.surface-glass::after {')
push('  content: "";')
push('  position: absolute;')
push('  inset: 0;')
push('  pointer-events: none;')
push('  border-radius: inherit;')
push('  border: 1px solid rgb(255 255 255 / 0.18);')
push('  mix-blend-mode: overlay;')
push('}')

/* ------------------------------------------------------------------ */
/* 5. @media (prefers-reduced-transparency: reduce)                  */
/* ------------------------------------------------------------------ */

emitSection('prefers-reduced-transparency — chrome collapses to solid')
push('@media (prefers-reduced-transparency: reduce) {')
push('  .surface-glass {')
push('    background: var(--color-background-system-background);')
push('    backdrop-filter: none;')
push('    -webkit-backdrop-filter: none;')
push('    box-shadow: none;')
push('  }')
push('  .surface-glass::after {')
push('    display: none;')
push('  }')
push('}')

/* ------------------------------------------------------------------ */
/* 6. @media (prefers-contrast: more)                                 */
/* ------------------------------------------------------------------ */

emitSection('prefers-contrast: more — text and borders lift')
push('@media (prefers-contrast: more) {')
push('  :root {')
push('    --color-text-primary: var(--color-label-label);')
push('    --color-border: var(--color-label-secondary-label);')
push('    --color-border-strong: var(--color-label-label);')
push('  }')
push('}')

/* ------------------------------------------------------------------ */
/* write file (trailing newline so editors do not flag EOF)          */
/* ------------------------------------------------------------------ */

mkdirSync(dirname(outputCssPath), { recursive: true })
const out = lines.join('\n') + '\n'
writeFileSync(outputCssPath, out, 'utf8')

console.log(`[build-tokens-css] wrote ${outputCssPath} (${out.length} bytes)`)
