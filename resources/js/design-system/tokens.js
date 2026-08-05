/**
 * OdontoSuite Design Tokens — Single Source of Truth
 * ====================================================
 *
 * This module is the canonical design-token surface for the entire frontend.
 * `tailwind.config.js` MUST source its palette from this module so any token
 * change cascades to the build.
 *
 * Token surfaces:
 *   - colors      Semantic + brand + state palettes (Tailwind 50-900 scale).
 *   - spacing     4px-grid spacing scale.
 *   - radius      Border-radius scale (none, sm, md, lg, full).
 *   - typography  Font family + size scale (Apple/iCloud-inspired).
 *   - shadow      Box-shadow elevation tokens.
 *   - breakpoint  Responsive breakpoints (sm/md/lg/xl/2xl).
 *
 * Conventions:
 *   - All hex values are 6-digit lowercase (#rrggbb) — matches tailwind config.
 *   - Semantic state palettes (success/warning/error/info) MUST include
 *     50/100/500/600/700 so Tailwind's `/<opacity>` modifier works
 *     (e.g. `bg-success-500/80`).
 *   - The neutral scale is the iCloud minimalist grayscale (50–900).
 *
 * Audit trail:
 *   - FF-004 — file was referenced in AGENTS.md §2 but did not exist.
 *   - UXT-001/002 — info/neutral/motion/glass/ease tokens now declared
 *     AND documented (audit log lives in design-system/audit.md).
 *
 * Last updated: 2026-08-05 (bugfix-2026-08 slice 06).
 */

const tokens = {
  colors: {
    primary: {
      50: '#e6f0ff',
      100: '#cce1ff',
      200: '#99c3ff',
      300: '#66a5ff',
      400: '#3387ff',
      500: '#0066cc',
      600: '#0052a3',
      700: '#003d7a',
      800: '#002952',
      900: '#001429'
    },
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
    },
    info: {
      50: '#e6f0ff',
      100: '#cce1ff',
      300: '#99c3ff',
      500: '#0066cc',
      600: '#0052a3',
      700: '#003d7a',
      900: '#001429'
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
      ]
    },
    fontSize: {
      xs: ['11px', { lineHeight: '16px' }],
      sm: ['13px', { lineHeight: '18px' }],
      base: ['15px', { lineHeight: '22px' }],
      lg: ['17px', { lineHeight: '24px' }],
      xl: ['20px', { lineHeight: '28px' }],
      '2xl': ['24px', { lineHeight: '32px' }],
      '3xl': ['28px', { lineHeight: '36px' }],
      '4xl': ['34px', { lineHeight: '40px' }]
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
  }
}

export default tokens

const { colors, spacing, radius, typography, shadow, breakpoint } = tokens
const { fontFamily, fontSize } = typography

export {
  colors,
  spacing,
  radius,
  typography,
  fontFamily,
  fontSize,
  shadow,
  breakpoint
}
