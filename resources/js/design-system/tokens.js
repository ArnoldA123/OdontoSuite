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
 *   - colors      iOS 13+ system colors + background + label + separator + fill.
 *   - spacing     4px-grid spacing scale.
 *   - radius      iOS radius scale (none/sm/md/ios/modal/full).
 *   - typography  System font (sans only); per-step letterSpacing for SF.
 *   - shadow      Box-shadow elevation tokens (pure-black rgba).
 *   - breakpoint  Responsive breakpoints (sm/md/lg/xl/2xl).
 *   - motion      Spring/response/damping/easing tokens (useSpring consumer).
 *
 * Conventions:
 *   - All hex values are 6-digit lowercase (#rrggbb) — matches tailwind config.
 *   - `terracotta` / `cream` / `clinicalTeal` / `info` are kept as
 *     DEPRECATED ALIAS keys so the 17 un-migrated modules' Tailwind classes
 *     keep resolving (e.g. `bg-cream-50` -> `bg-systemGray-50`,
 *     `bg-terracotta-500` -> `bg-systemBlue-500`). Do NOT add new consumers.
 *   - `info` re-keys to systemBlue (iOS convention: blue = info).
 *   - Serif surface is REMOVED entirely (system font only, design
 *     Decision 6). System font has zero FOUT risk — no replacement
 *     composable ships.
 *   - The design system is light-only (no dark-mode media query).
 *
 * Audit trail:
 *   - FF-004 — file was referenced in AGENTS.md §2 but did not exist.
 *   - UXT-001/002 — info/neutral/motion/glass/ease tokens documented.
 *   - PR2-2026-08-10 — terracotta/cream/ink/clinicalTeal ramps (retired).
 *   - PR3-2026-08-10 (this change, ui-refresh-apple-clinical-2026-08) —
 *     iOS 13+ system color ramps, background/label/separator/fill ramps,
 *     radius.ios/radius.modal, serif dropped, deprecated aliases kept,
 *     pure-black shadow ramp, white-on-white chrome.
 */

const tokens = {
  colors: {
    // iOS 13+ system color ramps. Spec-required steps are 50/100/500/600/700;
    // 200/300/400 added so Tailwind generates all utility classes
    // (`border-systemGreen-200`, `ring-systemBlue-400`, etc.) the existing
    // call sites consume.
    systemBlue: {
      50: '#e5f1ff',
      100: '#cce4ff',
      200: '#99c8ff',
      300: '#66adff',
      400: '#3392ff',
      500: '#007aff',
      600: '#0062cc',
      700: '#004999'
    },
    systemRed: {
      50: '#ffebea',
      100: '#ffd9d7',
      200: '#ffb8b3',
      300: '#ff9890',
      400: '#ff776c',
      500: '#ff3b30',
      600: '#d70015',
      700: '#a50e10'
    },
    systemOrange: {
      50: '#fff1e5',
      100: '#ffe2c7',
      200: '#ffd09d',
      300: '#ffbd73',
      400: '#ffab49',
      500: '#ff9500',
      600: '#c93400',
      700: '#9a2700'
    },
    systemYellow: {
      50: '#fff9d6',
      100: '#fff1ad',
      200: '#ffe57f',
      300: '#ffd84f',
      400: '#ffd11f',
      500: '#ffcc00',
      600: '#a57000',
      700: '#7a5200'
    },
    systemGreen: {
      50: '#e8f5e9',
      100: '#cdedcf',
      200: '#a4d8a8',
      300: '#7dc285',
      400: '#56ac63',
      500: '#34c759',
      600: '#248a3d',
      700: '#1a6530'
    },
    systemIndigo: {
      50: '#efe9ff',
      100: '#dfd2ff',
      200: '#c8b6ff',
      300: '#b09aff',
      400: '#997eff',
      500: '#5856d6',
      600: '#3f3dab',
      700: '#2d2b80'
    },
    systemPurple: {
      50: '#f6e9ff',
      100: '#ecd2ff',
      200: '#dfaeff',
      300: '#d189ff',
      400: '#c365ff',
      500: '#af52de',
      600: '#7a38a1',
      700: '#55276f'
    },
    systemPink: {
      50: '#ffe9f0',
      100: '#ffd2dd',
      200: '#ffa9b8',
      300: '#ff7f93',
      400: '#ff556e',
      500: '#ff2d55',
      600: '#c30039',
      700: '#8e0028'
    },
    systemGray: {
      50: '#f2f2f7',
      100: '#e5e5ea',
      200: '#d1d1d6',
      300: '#c7c7cc',
      400: '#aeaeb2',
      500: '#8e8e93',
      600: '#636366',
      700: '#3a3a3c'
    },

    // iOS background ramp (UIKit: systemBackground etc.).
    background: {
      systemBackground: '#ffffff',
      secondaryBackground: '#f2f2f7',
      tertiaryBackground: '#ffffff',
      groupedBackground: '#f2f2f7',
      // PR1 (ui-premium-microdetail-2026-08) — alias of secondaryBackground
      // for the canvas/surface separation on the three exemplar screens.
      // `systemBackground` MUST stay `#FFFFFF` (consumed by all 20 modules;
      // mutating it would repaint the whole app).
      canvas: '#f2f2f7'
    },

    // iOS label ramp (UIKit: label etc.).
    label: {
      label: '#000000',
      secondaryLabel: '#3c3c43',
      tertiaryLabel: 'rgba(60, 60, 67, 0.30)',
      quaternaryLabel: 'rgba(60, 60, 67, 0.18)'
    },

    // iOS hairline separator + opaque variants.
    separator: {
      separator: '#c6c6c8'
    },

    // PR1 (ui-premium-microdetail-2026-08) — hairline alpha-border token.
    // iOS separator opacity (R2 ruling). Emitted by build-tokens-css.mjs as
    // `--color-hairline`. The hex-parity test only scans `#RRGGBB` literals,
    // so the rgba value passes through cleanly.
    border: {
      hairline: 'rgba(60, 60, 67, 0.12)'
    },

    // iOS system fill (opaque-ish overlays for grouped rows).
    fill: {
      systemFill: 'rgba(120, 120, 128, 0.20)',
      secondarySystemFill: 'rgba(120, 120, 128, 0.16)',
      tertiarySystemFill: 'rgba(118, 118, 128, 0.12)'
    },

    // Deprecated alias keys — kept so the 17 un-migrated modules' Tailwind
    // classes keep resolving without churn. Do NOT add new consumers.
    cream: {
      50: '#f2f2f7', // -> systemGray-50
      100: '#e5e5ea', // -> systemGray-100
      200: '#d1d1d6' // -> systemGray-200
    },
    terracotta: {
      500: '#007aff', // -> systemBlue-500
      600: '#0062cc' // -> systemBlue-600
    },
    clinicalTeal: {
      50: '#e5f1ff', // -> systemBlue-50
      500: '#007aff', // -> systemBlue-500
      600: '#0062cc' // -> systemBlue-600
    },
    info: {
      500: '#007aff' // -> systemBlue-500 (iOS convention: blue = info)
    },
    // Deprecated alias for terracotta (kept for the 17 un-migrated
    // modules' bg-primary-* Tailwind classes; do NOT add new consumers).
    primary: {
      50: '#e5f1ff', // -> systemBlue-50
      100: '#cce4ff', // -> systemBlue-100
      200: '#99c8ff', // -> systemBlue-200
      300: '#66adff', // -> systemBlue-300
      400: '#3392ff', // -> systemBlue-400
      500: '#007aff', // -> systemBlue-500
      600: '#0062cc', // -> systemBlue-600
      700: '#004999', // -> systemBlue-700
      800: '#003066', // -> systemBlue-800
      900: '#001833' // -> systemBlue-900
    },
    // Deprecated alias for systemGray (kept for the 17 un-migrated
    // modules' bg-neutral-* Tailwind classes; do NOT add new consumers).
    neutral: {
      50: '#ffffff', // pure white (systemBackground)
      100: '#f2f2f7', // -> systemGray-50
      200: '#e5e5ea', // -> systemGray-100
      500: '#8e8e93', // -> systemGray-500
      700: '#3a3a3c', // -> systemGray-700
      900: '#1d1d1f' // near-black (label / display)
    },
    // Deprecated semantic aliases — kept so bg-success-* / bg-warning-* /
    // bg-error-* Tailwind classes resolve for the 17 un-migrated modules.
    success: {
      50: '#e8f5e9', // -> systemGreen-50
      100: '#cdedcf', // -> systemGreen-100
      300: '#a4d8a8', // -> systemGreen-200 ish
      500: '#34c759', // -> systemGreen-500
      600: '#248a3d', // -> systemGreen-600
      700: '#1a6530', // -> systemGreen-700
      900: '#0f4520' // darker green for legacy contrast
    },
    warning: {
      50: '#fff9d6', // -> systemYellow-50
      100: '#fff1ad', // -> systemYellow-100
      300: '#ffe07a', // -> systemYellow-200 ish
      500: '#ffcc00', // -> systemYellow-500
      600: '#a57000', // -> systemYellow-600
      700: '#7a5200', // -> systemYellow-700
      900: '#4d3300' // darker yellow
    },
    error: {
      50: '#ffebea', // -> systemRed-50
      100: '#ffd9d7', // -> systemRed-100
      300: '#ffaaa6', // -> systemRed-200 ish
      500: '#ff3b30', // -> systemRed-500
      600: '#d70015', // -> systemRed-600
      700: '#a50e10', // -> systemRed-700
      900: '#5a0608' // darker red
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
    sm: '4px', // small chips
    md: '8px', // inputs (slight inset)
    ios: '10px', // cards, buttons, status chips (iOS standard)
    modal: '14px', // Modal, Sheet, bottom pickers (iOS standard)
    full: '9999px', // pills
    // PR1 (ui-premium-microdetail-2026-08) — nested radius rhythm.
    // cardLg is for the outer card surface (KPI cards, hero photo);
    // control is for the inner interactive element (input, button).
    // The JS key is camelCase; the build script's toKebab() converts it to
    // CSS kebab-case automatically (`--radius-card-lg`).
    cardLg: '16px',
    control: '8px'
    // lg/2xl/3xl removed — see Decision 3.
  },
  typography: {
    fontFamily: {
      // System font only. No serif. No fallback chain.
      sans: [
        '-apple-system',
        'BlinkMacSystemFont',
        'Segoe UI',
        'Roboto',
        'Helvetica Neue',
        'Arial',
        'sans-serif'
      ]
      // serif: REMOVED (system font only per Decision 6).
    },
    // Per-step tracking tuned for SF / system font. Less aggressive
    // negative tracking than the previous serif table because SF's
    // advance widths are tighter; body is `0`, large display goes to
    // `-0.022em` only.
    fontSize: {
      xs: ['12px', { lineHeight: '16px', letterSpacing: '0' }],
      sm: ['13px', { lineHeight: '18px', letterSpacing: '0' }],
      base: ['15px', { lineHeight: '22px', letterSpacing: '0' }],
      lg: ['17px', { lineHeight: '24px', letterSpacing: '0' }],
      xl: ['20px', { lineHeight: '28px', letterSpacing: '-0.01em' }],
      '2xl': ['24px', { lineHeight: '32px', letterSpacing: '-0.015em' }],
      '3xl': ['30px', { lineHeight: '36px', letterSpacing: '-0.02em' }],
      '4xl': ['36px', { lineHeight: '40px', letterSpacing: '-0.022em' }],
      display: ['48px', { lineHeight: '48px', letterSpacing: '-0.022em' }],
      hero: ['64px', { lineHeight: '64px', letterSpacing: '-0.022em' }]
      // font-optical-sizing REMOVED — system font has no opsz axis.
    }
  },
  shadow: {
    // Pure-black rgba (Decision 5). No warm-black tints.
    subtle: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
    soft: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
    medium: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
    large: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
    elevated: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
    glass: '0 8px 32px 0 rgba(0, 0, 0, 0.18)'
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
    // PR1 (ui-premium-microdetail-2026-08) — `motion.dampingBounce = 0.8` is
    // DELIBERATELY UNCONSUMED. Nothing in this slice is a momentum-driven
    // gesture (no drag/flick/swipe). Apple's guidance: bounce on a non-
    // momentum entrance is wrong; honest choice = no consumer.
    dampingBounce: 0.8,
    stiffness: 1.0,
    // PR1 (ui-premium-microdetail-2026-08) — exactly three duration keys.
    // `instant` (0ms) and `spring` (label-only) are DEAD tokens and dropped.
    duration: {
      fast: '120ms',
      normal: '200ms',
      slow: '320ms'
    },
    easings: {
      standard: 'cubic-bezier(0.4, 0.0, 0.2, 1)',
      decel: 'cubic-bezier(0.0, 0.0, 0.2, 1)',
      accel: 'cubic-bezier(0.4, 0.0, 1, 1)',
      ios: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
    }
  },

  // PR1 (ui-premium-microdetail-2026-08) — composed focus-ring token.
  // Parts (width / color / alpha / offset) are emitted individually so
  // consumers can compose their own colours (e.g. error states); the
  // generator also emits the composed `--focus-ring-default`.
  focusRing: {
    width: '3px',
    color: '#007AFF', // systemBlue-500
    alpha: 0.2,
    offset: '2px'
  },

  // PR1 (ui-premium-microdetail-2026-08) — font features for tabular nums.
  // The value is a valid CSS `font-feature-settings` declaration; the literal
  // Tailwind utility name `tabular-nums` is NOT a valid value. Emitted by the
  // build script as `--font-features-tabular-nums`.
  // `proportionalNums` is dropped — no consumer in this slice.
  fontFeatures: {
    tabularNums: '"tnum" 1, "lnum" 1'
  },

  // PR1 (ui-premium-microdetail-2026-08) — tinted, layered elevation ramp.
  // Five rungs using the iOS label/separator hue family `rgba(60, 60, 67, α)`.
  // Rungs 2..4 are two layers per Apple's rule that bigger surfaces read
  // thicker (stronger blur AND a deeper shadow). NO rung uses `rgba(0,0,0,α)`
  // — that was the cheap-looking defect being fixed.
  elevation: {
    0: 'none',
    1: '0 1px 3px rgba(60, 60, 67, 0.04)',
    2: '0 2px 8px rgba(60, 60, 67, 0.06), 0 1px 2px rgba(60, 60, 67, 0.04)',
    3: '0 8px 16px rgba(60, 60, 67, 0.08), 0 2px 6px rgba(60, 60, 67, 0.06)',
    4: '0 16px 24px rgba(60, 60, 67, 0.12), 0 4px 8px rgba(60, 60, 67, 0.08)'
  },

  // PR4 (ui-premium-microdetail-2026-08) — topbar control tokens. The
  // previous topbar mixed three optical weights (WS dot at 2 px diameter
  // inside an 8 px pill, BellIcon stroke 2.0, Avatar at 32 px). The
  // defect: a single row of three controls reading as three different
  // fonts. The fix: pin icon size to 20 px (SF's `text-xl`) and the
  // outline stroke to 1.5 (Apple's outline-icon convention) so the three
  // controls share one optical weight.
  topbar: {
    iconSize: '20px',
    iconWeight: 1.5,
    control: '20px', // dot diameter + bell glyph cap (one shared size class)
    controlLg: '32px' // avatar diameter (one shared size class for the Lg slot)
  }
}

export default tokens

const {
  colors,
  spacing,
  radius,
  typography,
  shadow,
  breakpoint,
  motion,
  focusRing,
  fontFeatures,
  elevation,
  topbar
} = tokens
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
  motion,
  focusRing,
  fontFeatures,
  elevation,
  topbar
}
