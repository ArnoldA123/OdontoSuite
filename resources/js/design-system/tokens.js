/**
 * OdontoSuite Design Tokens — Single Source of Truth
 * ====================================================
 *
 * This module is the canonical design-token surface for the entire frontend.
 * `tailwind.config.js` MUST source its palette from this module, and
 * `scripts/build-tokens-css.mjs` emits `resources/css/tokens.generated.css`
 * from this same module so the build and runtime never drift.
 *
 * Token surfaces:
 *   - colors      Semantic + brand + state palettes (Tailwind 50-900 scale).
 *   - spacing     4px-grid spacing scale.
 *   - radius      Border-radius scale (none, sm, md, lg, full).
 *   - typography  Font family + size scale (Newsreader serif, system sans).
 *   - shadow      Box-shadow elevation tokens.
 *   - breakpoint  Responsive breakpoints (sm/md/lg/xl/2xl).
 *   - motion      Spring/response/damping/easing tokens (useSpring consumer).
 *
 * Conventions:
 *   - All hex values are 6-digit lowercase (#rrggbb) — matches tailwind config.
 *   - `info` ramp was removed (folded into `clinicalTeal` per PR2 spec).
 *   - The `primary` ramp was renamed to `terracotta`; `primary` is kept as a
 *     deprecated alias so the 17 un-migrated modules keep their class names
 *     resolving until PR3 retires them.
 *   - The neutral scale is the iCloud minimalist grayscale (50–900).
 *   - Serif surface is `Newsreader`, self-hosted variable OFL woff2 in
 *     public/fonts/, no Google Fonts CDN.
 *
 * Audit trail:
 *   - FF-004 — file was referenced in AGENTS.md §2 but did not exist.
 *   - UXT-001/002 — info/neutral/motion/glass/ease tokens now declared
 *     AND documented.
 *   - PR2-2026-08-10 — new ramps (terracotta/cream/ink/clinicalTeal), serif
 *     family, per-step tracking, motion section.
 */

const tokens = {
  colors: {
    // Brand accent — terracotta. Apple-inspired warm hue, NOT iCloud blue.
    // CTA bg, link, focus ring, badge accent, button border on cream-50.
    terracotta: {
      50: '#fbeee7',
      100: '#f4d9c7',
      200: '#e9b89e',
      300: '#dd9775',
      400: '#d27a52',
      500: '#c96442',
      600: '#b05432',
      700: '#8c3f25',
      800: '#652c1b',
      900: '#3f1a11'
    },
    // Deprecated alias — kept so existing `bg-primary-*` classes resolve
    // until PR3 retires them. Do NOT add new consumers of `primary`.
    primary: {
      50: '#fbeee7',
      100: '#f4d9c7',
      200: '#e9b89e',
      300: '#dd9775',
      400: '#d27a52',
      500: '#c96442',
      600: '#b05432',
      700: '#8c3f25',
      800: '#652c1b',
      900: '#3f1a11'
    },
    // Surface ramp — warm cream. Page bg = 50, card surface = 100,
    // divider = 200, decorative = 300.
    cream: {
      50: '#faf9f7',
      100: '#f2efe9',
      200: '#e8e3d8',
      300: '#d8d1c0'
    },
    // Text ramp. Body uses 800 on cream-50 (~13.6:1, AAA). Display uses
    // 900. Borders use 200 (subtle) and 300 (strong).
    ink: {
      50: '#f7f5f2',
      100: '#dad5cd',
      200: '#b0a99d',
      300: '#847c6e',
      500: '#5a5247',
      600: '#423a30',
      700: '#2a2622',
      800: '#1f1b17',
      900: '#14110e'
    },
    // Medical-state semantics: appointment-confirmed, in-consultation,
    // prescription-sent. Never body. `info` was folded into this ramp.
    clinicalTeal: {
      50: '#e6f2f2',
      100: '#c8e0e0',
      300: '#74b4b4',
      500: '#2c7a7b',
      600: '#226466',
      700: '#1a4f51'
    },
    // Kept for the 17 un-migrated modules that reference neutral.
    neutral: {
      50: '#ffffff',
      100: '#f5f5f7',
      200: '#e5e5e7',
      300: '#d2d2d7',
      400: '#a3a3a8',
      500: '#86868b',
      600: '#525252',
      700: '#404040',
      800: '#262626',
      900: '#1d1d1f'
    },
    success: {
      50: '#f0fdf4',
      100: '#dcfce7',
      300: '#86efac',
      500: '#10b981',
      600: '#059669',
      700: '#047857',
      900: '#14532d'
    },
    warning: {
      50: '#fffbeb',
      100: '#fef3c7',
      300: '#fcd34d',
      500: '#f59e0b',
      600: '#d97706',
      700: '#b45309',
      900: '#78350f'
    },
    error: {
      50: '#fef2f2',
      100: '#fee2e2',
      300: '#fca5a5',
      500: '#ef4444',
      600: '#dc2626',
      700: '#b91c1c',
      900: '#7f1d1d'
    }
  },
  spacing: {
    0: '0px',
    1: '4px',
    2: '8px',
    3: '12px',
    4: '16px',
    5: '20px',
    6: '24px',
    8: '32px',
    12: '48px',
    16: '64px',
    24: '96px',
    32: '128px',
    48: '192px'
  },
  radius: {
    none: '0',
    sm: '4px',
    DEFAULT: '8px',
    md: '10px',
    lg: '12px',
    xl: '16px',
    '2xl': '20px',
    '3xl': '24px',
    full: '9999px'
  },
  typography: {
    fontFamily: {
      sans: [
        '-apple-system',
        'BlinkMacSystemFont',
        'Segoe UI',
        'Roboto',
        'Helvetica Neue',
        'Arial',
        'sans-serif'
      ],
      // Newsreader self-hosted (variable, opsz + wght axes), OFL.
      // No Google Fonts CDN. Fallback chain matches Newsreader's
      // metrics at opsz=16: same x-height, similar advance widths.
      serif: ['Newsreader', 'ui-serif', 'New York', 'Georgia', 'serif']
    },
    // Per-step tracking table (design Decision 3). Negative tracking on
    // large display; positive tracking on small text. The generator emits
    // these as CSS rules so Tailwind class names like .text-display carry
    // the right tracking automatically.
    fontSize: {
      xs: ['12px', { lineHeight: '16px', letterSpacing: '0.01em' }],
      sm: ['13px', { lineHeight: '18px', letterSpacing: '0' }],
      base: ['15px', { lineHeight: '22px', letterSpacing: '0' }],
      lg: ['17px', { lineHeight: '24px', letterSpacing: '0' }],
      xl: ['20px', { lineHeight: '28px', letterSpacing: '-0.01em' }],
      '2xl': ['24px', { lineHeight: '32px', letterSpacing: '-0.015em' }],
      '3xl': ['30px', { lineHeight: '36px', letterSpacing: '-0.02em' }],
      '4xl': ['36px', { lineHeight: '40px', letterSpacing: '-0.025em' }],
      display: ['48px', { lineHeight: '48px', letterSpacing: '-0.03em' }],
      hero: ['64px', { lineHeight: '64px', letterSpacing: '-0.035em' }]
    }
  },
  shadow: {
    subtle: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
    soft: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
    medium: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
    large: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
    elevated: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
    glass: '0 8px 32px 0 rgba(31, 38, 135, 0.37)'
  },
  breakpoint: {
    sm: '640px',
    md: '768px',
    lg: '1024px',
    xl: '1280px',
    '2xl': '1536px'
  },
  // Motion tokens — consumed by useSpring / useSpring2D and by the
  // generator (which emits --motion-* CSS vars). Apple "response" is the
  // seconds-to-target; damping = 1.0 is critically damped (no overshoot);
  // damping < 1.0 (e.g. 0.8) is underdamped (one bounce).
  motion: {
    response: 0.35,
    damping: 1.0,
    dampingBounce: 0.8,
    stiffness: 1.0,
    easings: {
      standard: 'cubic-bezier(0.4, 0.0, 0.2, 1)',
      decel: 'cubic-bezier(0.0, 0.0, 0.2, 1)',
      accel: 'cubic-bezier(0.4, 0.0, 1, 1)',
      ios: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
    }
  }
}

export default tokens

const { colors, spacing, radius, typography, shadow, breakpoint, motion } = tokens
const { fontFamily, fontSize } = typography

export {
  colors,
  spacing,
  radius,
  typography,
  fontFamily,
  fontSize,
  shadow,
  breakpoint,
  motion
}
