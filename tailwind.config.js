/** @type {import('tailwindcss').Config} */
// OdontoSuite Tailwind Config — re-sourced from tokens.js (bugfix-2026-08 slice 06).
// The tokens module is the single source of truth; tailwind consumes the palette
// here. The CI guard `tests/Unit/DesignSystem/TokensModuleTest.php` keeps both in
// parity, so any drift fails the test suite.
import tokens, {
  colors as tokenColors,
  spacing as tokenSpacing,
  radius as tokenRadius,
  fontFamily as tokenFontFamily,
  fontSize as tokenFontSize,
  shadow as tokenShadow,
  motion as tokenMotion
} from './resources/js/design-system/tokens.js'

// Convert Tailwind fontSize tuple form (already [size, {lineHeight}]) — pass through.
const fontSize = { ...tokenFontSize.fontSize }
const fontFamily = { ...tokenFontFamily.fontFamily }

// boxShadow from tokens uses semantic names; map to Tailwind keys.
const boxShadow = {
  ...tokenShadow,
  subtle: tokenShadow.subtle,
  soft: tokenShadow.soft,
  medium: tokenShadow.medium,
  large: tokenShadow.large,
  elevated: tokenShadow.elevated,
  glass: tokenShadow.glass,
  'glass-inset': 'inset 0 1px 0 0 rgba(255, 255, 255, 0.05)'
}

// motion/glass/ease — tokens for motion timing functions and backdrop blur.
const transitionTimingFunction = {
  'bounce-soft': 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
  'ease-ios': 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
}

// PR1 (ui-premium-microdetail-2026-08) — transitionDuration reads from
// `tokens.motion.duration` so the utilities `duration-fast`,
// `duration-normal`, and `duration-slow` resolve to the canonical
// 120ms/200ms/320ms ramp. The ramp is the single source of truth; the
// build script emits `--motion-duration-*` CSS vars from the same data.
const transitionDuration = {
  ...tokenMotion.duration
}

export default {
  content: ['./resources/**/*.blade.php', './resources/**/*.js', './resources/**/*.vue'],
  theme: {
    extend: {
      colors: {
        // Source-of-truth: tokens.colors — primary/neutral/success/warning/error/info.
        ...tokenColors
      },
      zIndex: {
        dropdown: 1000,
        sticky: 1020,
        fixed: 1030,
        modalBackdrop: 1040,
        modal: 1050,
        popover: 1060,
        tooltip: 1070
      },
      fontFamily,
      fontSize,
      spacing: {
        ...tokenSpacing,
        // Keep canonical Tailwind 0–96px aliases for compatibility.
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
        24: '96px'
      },
      borderRadius: tokenRadius,
      animation: {
        'fade-in': 'fadeIn 0.3s ease-out',
        'fade-out': 'fadeOut 0.2s ease-in',
        'slide-up': 'slideUp 0.3s ease-out',
        'slide-down': 'slideDown 0.3s ease-out',
        'slide-left': 'slideLeft 0.3s ease-out',
        'slide-right': 'slideRight 0.3s ease-out',
        'scale-in': 'scaleIn 0.2s ease-out',
        'scale-out': 'scaleOut 0.2s ease-in',
        'bounce-subtle': 'bounceSubtle 0.6s ease-out',
        'pulse-subtle': 'pulseSubtle 2s ease-in-out infinite',
        ripple: 'ripple 0.6s ease-out'
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' }
        },
        fadeOut: {
          '0%': { opacity: '1' },
          '100%': { opacity: '0' }
        },
        slideUp: {
          '0%': { transform: 'translateY(20px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' }
        },
        slideDown: {
          '0%': { transform: 'translateY(-20px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' }
        },
        slideLeft: {
          '0%': { transform: 'translateX(20px)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' }
        },
        slideRight: {
          '0%': { transform: 'translateX(-20px)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' }
        },
        scaleIn: {
          '0%': { transform: 'scale(0.95)', opacity: '0' },
          '100%': { transform: 'scale(1)', opacity: '1' }
        },
        scaleOut: {
          '0%': { transform: 'scale(1)', opacity: '1' },
          '100%': { transform: 'scale(0.95)', opacity: '0' }
        },
        bounceSubtle: {
          '0%, 20%, 50%, 80%, 100%': { transform: 'translateY(0)' },
          '40%': { transform: 'translateY(-4px)' },
          '60%': { transform: 'translateY(-2px)' }
        },
        pulseSubtle: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.8' }
        },
        ripple: {
          '0%': { transform: 'scale(0)', opacity: '0.6' },
          '100%': { transform: 'scale(4)', opacity: '0' }
        }
      },
      backdropBlur: {
        xs: '2px',
        sm: '4px',
        DEFAULT: '8px',
        md: '12px',
        lg: '16px',
        xl: '24px',
        '2xl': '40px',
        '3xl': '64px'
      },
      boxShadow,
      transitionTimingFunction,
      transitionDuration
    }
  },
  plugins: [
    function ({ addUtilities }) {
      // Registrar clases de tema para que funcionen con @apply
      addUtilities({
        // Colores de fondo temáticos
        '.bg-theme-background': {
          'background-color': 'var(--color-background)'
        },
        '.bg-theme-secondary': {
          'background-color': 'var(--color-background-secondary)'
        },
        '.bg-theme': {
          'background-color': 'var(--color-border)'
        },
        '.bg-theme-surface': {
          'background-color': 'var(--color-surface)'
        },
        '.bg-theme-surface-elevated': {
          'background-color': 'var(--color-surface-elevated)'
        },
        '.hover\\:bg-theme-surface:hover': {
          'background-color': 'var(--color-surface)'
        },
        '.hover\\:bg-theme-surface-elevated:hover': {
          'background-color': 'var(--color-surface-elevated)'
        },
        // Colores de texto temáticos
        '.text-theme-primary': {
          color: 'var(--color-text-primary)'
        },
        '.text-theme-secondary': {
          color: 'var(--color-text-secondary)'
        },
        '.text-theme-tertiary': {
          color: 'var(--color-text-tertiary)'
        },
        '.text-theme-accent': {
          color: 'var(--color-accent)'
        },
        '.hover\\:text-theme-primary:hover': {
          color: 'var(--color-text-primary)'
        },
        // Colores de borde temáticos
        '.border-theme': {
          'border-color': 'var(--color-border)'
        },
        '.border-theme-light': {
          'border-color': 'var(--color-border-light)'
        },
        '.border-theme-strong': {
          'border-color': 'var(--color-border-strong)'
        },
        // Divide (divisores entre elementos)
        '.divide-theme > :not([hidden]) ~ :not([hidden])': {
          'border-color': 'var(--color-border)'
        },
        // Ring (anillos de enfoque)
        '.ring-theme': {
          '--tw-ring-color': 'var(--color-border)'
        },
        // Acento - botones y enlaces
        '.bg-accent': {
          'background-color': 'var(--color-accent)'
        },
        '.bg-accent-hover': {
          'background-color': 'var(--color-accent-hover)'
        },
        '.bg-accent-active': {
          'background-color': 'var(--color-accent-active)'
        },
        '.hover\\:bg-accent-hover:hover': {
          'background-color': 'var(--color-accent-hover)'
        },
        '.text-accent': {
          color: 'var(--color-accent)'
        },
        '.border-accent': {
          'border-color': 'var(--color-accent)'
        },
        '.focus\\:border-accent:focus': {
          'border-color': 'var(--color-accent)'
        },
        '.focus-visible\\:outline-accent:focus-visible': {
          'outline-color': 'var(--color-accent)'
        },
        // Gradientes con accent
        '.bg-gradient-accent': {
          background:
            'linear-gradient(to bottom right, var(--color-accent), var(--color-accent-hover))'
        },
        // Estados semánticos
        '.bg-success-badge': {
          'background-color': 'var(--color-success-bg)',
          color: 'var(--color-success-text)'
        },
        '.bg-warning-badge': {
          'background-color': 'var(--color-warning-bg)',
          color: 'var(--color-warning-text)'
        },
        '.bg-danger-badge': {
          'background-color': 'var(--color-danger-bg)',
          color: 'var(--color-danger-text)'
        },
        // Hover lift sutil estilo Apple (-1px translateY + shadow-medium)
        '.hover-lift': {
          transition: 'transform 150ms ease-out, box-shadow 150ms ease-out'
        },
        '.hover-lift:hover': {
          transform: 'translateY(-1px)',
          'box-shadow': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)'
        }
      })
    }
  ]
}

// Reference tokens so the import is preserved even if destructured values
// are not all consumed (keeps Vite/esbuild happy with tree-shaking).
void tokens
