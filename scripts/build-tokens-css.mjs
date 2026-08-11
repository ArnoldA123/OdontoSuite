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
 *   1. @font-face for Newsreader (self-hosted, OFL, opsz+wght axes)
 *   2. One :root block with --color-terracotta-*, --color-cream-*,
 *      --color-ink-*, --color-clinical-teal-*, --color-success-*,
 *      --color-warning-*, --color-error-*, --color-neutral-*,
 *      --color-primary-* (deprecated alias to terracotta).
 *   3. Semantic aliases: --color-accent, --color-surface-elevated,
 *      --color-text-*, --color-border-*, --glass-*, --shadow-*,
 *      --font-serif, --font-sans.
 *   4. Motion vars: --motion-response-default, --motion-damping-*,
 *      --motion-easing-*.
 *   5. One @media (prefers-reduced-transparency: reduce) block.
 *   6. One @media (prefers-contrast: more) block.
 *   7. One .surface-glass class (chrome-only Liquid-Glass approximation).
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

const { default: tokens, colors, spacing, radius, shadow, motion } = await import(pathToFileURL(tokensModulePath).href)

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
/* 1. @font-face — Newsreader, self-hosted variable (opsz + wght)    */
/* ------------------------------------------------------------------ */

emitSection('@font-face — Newsreader variable (OFL, self-hosted, no CDN)')
push('@font-face {')
push('  font-family: "Newsreader";')
push('  font-style: normal;')
push('  font-weight: 100 900;')
push('  font-display: swap;')
push('  font-optical-sizing: auto;')
push('  src: url("/fonts/newsreader-latin.woff2") format("woff2");')
push('  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;')
push('}')

/* ------------------------------------------------------------------ */
/* 2. :root — ramp variables                                          */
/* ------------------------------------------------------------------ */

emitSection(':root — token ramps and aliases')
push(':root {')

// Color ramps.
// Token keys are camelCase in JS (`clinicalTeal`) but CSS custom properties
// are kebab-case by convention (`--color-clinical-teal-500`). Emitting the
// camelCase form produced a variable no alias could reference, which silently
// broke `--color-info`. Convert once, here, so the two can never disagree.
const toKebab = (s) => s.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase()

for (const rampName of Object.keys(colors)) {
  const ramp = colors[rampName]
  const cssName = toKebab(rampName)
  push(`  /* ${rampName} */`)
  for (const step of Object.keys(ramp)) {
    push(`  --color-${cssName}-${step}: ${ramp[step]};`)
  }
  push('')
}

// Deprecated alias: --color-primary-* -> terracotta (kept for the 17
// un-migrated modules until PR3 retires them).
push('  /* primary (deprecated alias for terracotta) */')
for (const step of Object.keys(colors.terracotta)) {
  push(`  --color-primary-${step}: ${colors.terracotta[step]};`)
}
push('')

// Semantic aliases.
push('  /* semantic aliases */')
push('  --color-accent: var(--color-terracotta-500);')
push('  --color-accent-hover: var(--color-terracotta-600);')
push('  --color-accent-active: var(--color-terracotta-700);')
push('  --color-accent-light: var(--color-terracotta-50);')
push('  --color-primary: var(--color-terracotta-500);')
push('  --color-primary-hover: var(--color-terracotta-600);')
push('  --color-primary-active: var(--color-terracotta-700);')
push('  --color-primary-light: var(--color-terracotta-50);')
push('  --color-primary-dark: var(--color-terracotta-700);')
push('')

push('  --color-background: var(--color-cream-50);')
push('  --color-background-secondary: var(--color-cream-100);')
push('  --color-surface: var(--color-cream-100);')
push('  --color-surface-elevated: var(--color-cream-50);')
push('')

push('  --color-text-primary: var(--color-ink-800);')
push('  --color-text-secondary: var(--color-ink-500);')
push('  --color-text-tertiary: var(--color-ink-300);')
push('  --color-text-inverse: var(--color-cream-50);')
push('')

push('  --color-border: var(--color-ink-200);')
push('  --color-border-light: var(--color-ink-100);')
push('  --color-border-strong: var(--color-ink-300);')
push('')

push('  --color-info: var(--color-clinical-teal-500);')
push('  --color-info-light: var(--color-clinical-teal-50);')
push('  --color-info-dark: var(--color-clinical-teal-700);')
push('')

push('  --color-success-bg: var(--color-success-50);')
push('  --color-success-text: var(--color-success-700);')
push('  --color-success-light: var(--color-success-50);')
push('  --color-success-dark: var(--color-success-700);')
push('')

push('  --color-warning-bg: var(--color-warning-50);')
push('  --color-warning-text: var(--color-warning-700);')
push('  --color-warning-light: var(--color-warning-50);')
push('  --color-warning-dark: var(--color-warning-700);')
push('')

push('  --color-danger: var(--color-error-500);')
push('  --color-danger-bg: var(--color-error-50);')
push('  --color-danger-text: var(--color-error-700);')
push('  --color-danger-light: var(--color-error-50);')
push('  --color-danger-dark: var(--color-error-700);')
push('')

push('  /* glass effect (chrome only) */')
push('  --glass-bg: rgba(250, 249, 247, 0.72);')
push('  --glass-border: rgba(255, 255, 255, 0.22);')
push('  --glass-backdrop: blur(20px) saturate(180%) contrast(1.04);')
push('')

push('  /* shadows */')
push('  --shadow-sm: 0 1px 2px 0 rgba(20, 17, 14, 0.05);')
push('  --shadow-md: 0 4px 6px -1px rgba(20, 17, 14, 0.10), 0 2px 4px -1px rgba(20, 17, 14, 0.06);')
push('  --shadow-lg: 0 10px 15px -3px rgba(20, 17, 14, 0.10), 0 4px 6px -2px rgba(20, 17, 14, 0.05);')
push('  --shadow-xl: 0 20px 25px -5px rgba(20, 17, 14, 0.10), 0 10px 10px -5px rgba(20, 17, 14, 0.04);')
push('  --shadow-glass: 0 8px 32px 0 rgba(31, 38, 135, 0.20);')
push('')

push('  /* font stacks */')
push('  --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;')
push('  --font-serif: Newsreader, ui-serif, "New York", Georgia, serif;')
push('')

// Spacing aliases — kept for the few surviving consumers (Card.vue, etc.)
// that reference `var(--spacing-4)` directly instead of using Tailwind
// utility classes.
push('  /* spacing */')
for (const step of Object.keys(spacing)) {
  push(`  --spacing-${step}: ${spacing[step]};`)
}
push('')

// Radius aliases — used by welcome.blade.php and a few utility classes.
push('  /* radius */')
for (const name of Object.keys(radius)) {
  push(`  --radius-${name}: ${radius[name]};`)
}
push('')

// Shadow aliases — used by Avatar/Card/Badge/Toast primitive components
// that reference `var(--shadow-medium)` etc. directly.
push('  /* shadow */')
for (const name of Object.keys(shadow)) {
  push(`  --shadow-${name}: ${shadow[name]};`)
}
push('')

// LoadingSpinner reads --spinner-color at runtime. Default = terracotta
// accent. Components can override locally.
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
push('  --motion-easing-standard: cubic-bezier(0.4, 0.0, 0.2, 1);')
push('  --motion-easing-decel: cubic-bezier(0.0, 0.0, 0.2, 1);')
push('  --motion-easing-accel: cubic-bezier(0.4, 0.0, 1, 1);')
push('  --motion-easing-ios: cubic-bezier(0.25, 0.46, 0.45, 0.94);')

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
push('  background: linear-gradient(135deg, rgb(250 249 247 / 0.78), rgb(250 249 247 / 0.62));')
push('  backdrop-filter: blur(20px) saturate(180%) contrast(1.04);')
push('  -webkit-backdrop-filter: blur(20px) saturate(180%) contrast(1.04);')
push('  border-right: 1px solid rgb(31 27 23 / 0.06);')
push('  box-shadow:')
push('    inset 0 1px 0 rgb(255 255 255 / 0.55),')
push('    inset 0 -1px 0 rgb(31 27 23 / 0.04),')
push('    0 18px 40px -16px rgb(31 27 23 / 0.10);')
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
push('    background: var(--color-cream-100);')
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
push('    --color-text-primary: var(--color-ink-900);')
push('    --color-border: var(--color-ink-700);')
push('    --color-border-strong: var(--color-ink-900);')
push('  }')
push('}')

/* ------------------------------------------------------------------ */
/* write file (trailing newline so editors do not flag EOF)          */
/* ------------------------------------------------------------------ */

mkdirSync(dirname(outputCssPath), { recursive: true })
const out = lines.join('\n') + '\n'
writeFileSync(outputCssPath, out, 'utf8')

console.log(`[build-tokens-css] wrote ${outputCssPath} (${out.length} bytes)`)
