/**
 * Smoke test for resources/js/design-system/tokens.js
 * (bugfix-2026-08 / slice 06 / T-06.14)
 *
 * Run: `node tests/visual/tokens.smoke.mjs`
 * CI gate: tests/Unit/DesignSystem/TokensModuleTest.php (PHPUnit) loads the
 * same module via Node import and asserts parity with tailwind.config.js.
 *
 * This standalone smoke test is for manual verification and for the doc-link
 * check that confirms the AGENTS.md reference resolves to a real module.
 */

import {
  colors,
  spacing,
  radius,
  typography,
  shadow,
  breakpoint
} from '../../resources/js/design-system/tokens.js'

const assert = (condition, message) => {
  if (!condition) {
    console.error(`✗ ${message}`)
    process.exit(1)
  }
  console.log(`✓ ${message}`)
}

console.log('=== tokens.smoke.mjs ===')

// Surface check.
assert(typeof colors === 'object' && colors !== null, 'colors is exported')
assert(typeof spacing === 'object' && spacing !== null, 'spacing is exported')
assert(typeof radius === 'object' && radius !== null, 'radius is exported')
assert(typeof typography === 'object' && typography !== null, 'typography is exported')
assert(typeof shadow === 'object' && shadow !== null, 'shadow is exported')
assert(typeof breakpoint === 'object' && breakpoint !== null, 'breakpoint is exported')

// Semantic state palette.
for (const state of ['primary', 'neutral', 'success', 'warning', 'error', 'info']) {
  assert(colors[state] !== undefined, `colors.${state} exists`)
}

// Success/warning/error include 50/100/500/600/700 steps.
for (const state of ['success', 'warning', 'error']) {
  for (const step of ['50', '100', '500', '600', '700']) {
    assert(
      typeof colors[state][step] === 'string' && /^#[0-9a-f]{6}$/.test(colors[state][step]),
      `colors.${state}.${step} = ${colors[state][step]} (valid hex)`
    )
  }
}

// Spacing canonical steps.
assert(spacing[4] === '16px', `spacing.4 = "16px" (got ${spacing[4]})`)
assert(spacing[8] === '32px', `spacing.8 = "32px" (got ${spacing[8]})`)

// Radius canonical scale.
assert(radius.sm === '4px', `radius.sm = "4px"`)
assert(radius.full === '9999px', `radius.full = "9999px"`)

// Typography font family includes iCloud stack.
assert(
  Array.isArray(typography.fontFamily.sans) &&
    typography.fontFamily.sans.includes('-apple-system'),
  'typography.fontFamily.sans contains -apple-system'
)

// Shadow scale.
for (const key of ['subtle', 'soft', 'medium', 'large', 'elevated', 'glass']) {
  assert(typeof shadow[key] === 'string' && shadow[key].length > 0, `shadow.${key} defined`)
}

// Breakpoint scale.
assert(breakpoint.md === '768px', `breakpoint.md = "768px"`)
assert(breakpoint.lg === '1024px', `breakpoint.lg = "1024px"`)

console.log('=== ALL SMOKE CHECKS PASSED ===')
