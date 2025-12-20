/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        // Sistema de colores minimalista inspirado en iCloud
        // Tema claro único
        primary: {
          50: '#e6f0ff',   // Backgrounds suaves - tema claro
          100: '#cce1ff',
          200: '#99c3ff',
          300: '#66a5ff',
          400: '#3387ff',
          500: '#0066CC',  // Acción principal - tema claro (iCloud azul)
          600: '#0052a3',  // Hover - tema claro
          700: '#003d7a',  // Active - tema claro
          800: '#002952',
          900: '#001429',
        },
        // Neutrales - Base minimalista iCloud
        neutral: {
          50: '#FFFFFF',   // Background principal - tema claro (iCloud blanco)
          100: '#F5F5F7',  // Background secundario - tarjetas (iCloud gris claro)
          200: '#e5e5e7',  // Borders sutiles
          300: '#d2d2d7',
          400: '#a3a3a8',
          500: '#86868B',  // Texto secundario (iCloud gris medio)
          600: '#525252',
          700: '#404040',
          800: '#262626',
          900: '#1D1D1F',  // Texto principal - tema claro (iCloud negro suave)
        },
        // Colores semánticos
        success: {
          50: '#f0fdf4',
          100: '#dcfce7',
          500: '#10b981',
          600: '#059669',
          700: '#047857',
        },
        warning: {
          50: '#fffbeb',
          100: '#fef3c7',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
        },
        error: {
          50: '#fef2f2',
          100: '#fee2e2',
          500: '#ef4444',
          600: '#dc2626',
          700: '#b91c1c',
        },
        info: {
          50: '#e6f0ff',
          100: '#cce1ff',
          500: '#0066CC',
          600: '#0052a3',
          700: '#003d7a',
        }
      },
      zIndex: {
        dropdown: 1000,
        sticky: 1020,
        fixed: 1030,
        modalBackdrop: 1040,
        modal: 1050,
        popover: 1060,
        tooltip: 1070,
      },
      fontFamily: {
        'sans': ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
      },
      fontSize: {
        'xs': ['11px', { lineHeight: '16px' }],     // Labels pequeños
        'sm': ['13px', { lineHeight: '18px' }],         // Body secundario
        'base': ['15px', { lineHeight: '22px' }],      // Body principal
        'lg': ['17px', { lineHeight: '24px' }],        // Subtítulos
        'xl': ['20px', { lineHeight: '28px' }],        // Títulos h3
        '2xl': ['24px', { lineHeight: '32px' }],       // Títulos h2
        '3xl': ['28px', { lineHeight: '36px' }],       // Títulos h1
        '4xl': ['34px', { lineHeight: '40px' }],       // Display
      },
      spacing: {
        // Sistema de espaciado de 8px
        '0': '0px',
        '1': '4px',    // Micro
        '2': '8px',    // Pequeño
        '3': '12px',   // Compacto
        '4': '16px',   // Base
        '5': '20px',   // Confortable
        '6': '24px',   // Amplio
        '8': '32px',   // Sección
        '12': '48px',  // Separación mayor
        '16': '64px',  // Hero sections
      },
      borderRadius: {
        'none': '0',
        'sm': '4px',
        'DEFAULT': '8px',
        'md': '10px',   // Inputs
        'lg': '12px',   // Buttons
        'xl': '16px',   // Cards
        '2xl': '20px',
        '3xl': '24px',
        'full': '9999px',
      },
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
        'ripple': 'ripple 0.6s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        fadeOut: {
          '0%': { opacity: '1' },
          '100%': { opacity: '0' },
        },
        slideUp: {
          '0%': { transform: 'translateY(20px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        slideDown: {
          '0%': { transform: 'translateY(-20px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        slideLeft: {
          '0%': { transform: 'translateX(20px)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' },
        },
        slideRight: {
          '0%': { transform: 'translateX(-20px)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' },
        },
        scaleIn: {
          '0%': { transform: 'scale(0.95)', opacity: '0' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
        scaleOut: {
          '0%': { transform: 'scale(1)', opacity: '1' },
          '100%': { transform: 'scale(0.95)', opacity: '0' },
        },
        bounceSubtle: {
          '0%, 20%, 50%, 80%, 100%': { transform: 'translateY(0)' },
          '40%': { transform: 'translateY(-4px)' },
          '60%': { transform: 'translateY(-2px)' },
        },
        pulseSubtle: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.8' },
        },
        ripple: {
          '0%': { transform: 'scale(0)', opacity: '0.6' },
          '100%': { transform: 'scale(4)', opacity: '0' },
        },
      },
      backdropBlur: {
        'xs': '2px',
        'sm': '4px',
        'DEFAULT': '8px',
        'md': '12px',
        'lg': '16px',
        'xl': '24px',
        '2xl': '40px',
        '3xl': '64px',
      },
      boxShadow: {
        'glass': '0 8px 32px 0 rgba(31, 38, 135, 0.37)',
        'glass-inset': 'inset 0 1px 0 0 rgba(255, 255, 255, 0.05)',
        'subtle': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
        'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
        'medium': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
        'large': '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
        'elevated': '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
      },
      transitionTimingFunction: {
        'bounce-soft': 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        'ease-ios': 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
      },
    },
  },
  plugins: [
    function({ addUtilities }) {
      // Registrar clases de tema para que funcionen con @apply
      addUtilities({
        // Colores de fondo temáticos
        '.bg-theme-background': {
          'background-color': 'var(--color-background)',
        },
        '.bg-theme-secondary': {
          'background-color': 'var(--color-background-secondary)',
        },
        '.bg-theme': {
          'background-color': 'var(--color-border)',
        },
        '.bg-theme-surface': {
          'background-color': 'var(--color-surface)',
        },
        '.bg-theme-surface-elevated': {
          'background-color': 'var(--color-surface-elevated)',
        },
        '.hover\\:bg-theme-surface:hover': {
          'background-color': 'var(--color-surface)',
        },
        '.hover\\:bg-theme-surface-elevated:hover': {
          'background-color': 'var(--color-surface-elevated)',
        },
        // Colores de texto temáticos
        '.text-theme-primary': {
          'color': 'var(--color-text-primary)',
        },
        '.text-theme-secondary': {
          'color': 'var(--color-text-secondary)',
        },
        '.text-theme-tertiary': {
          'color': 'var(--color-text-tertiary)',
        },
        '.text-theme-accent': {
          'color': 'var(--color-accent)',
        },
        '.hover\\:text-theme-primary:hover': {
          'color': 'var(--color-text-primary)',
        },
        // Colores de borde temáticos
        '.border-theme': {
          'border-color': 'var(--color-border)',
        },
        '.border-theme-light': {
          'border-color': 'var(--color-border-light)',
        },
        '.border-theme-strong': {
          'border-color': 'var(--color-border-strong)',
        },
        // Divide (divisores entre elementos)
        '.divide-theme > :not([hidden]) ~ :not([hidden])': {
          'border-color': 'var(--color-border)',
        },
        // Ring (anillos de enfoque)
        '.ring-theme': {
          '--tw-ring-color': 'var(--color-border)',
        },
        // Acento - botones y enlaces
        '.bg-accent': {
          'background-color': 'var(--color-accent)',
        },
        '.bg-accent-hover': {
          'background-color': 'var(--color-accent-hover)',
        },
        '.bg-accent-active': {
          'background-color': 'var(--color-accent-active)',
        },
        '.hover\\:bg-accent-hover:hover': {
          'background-color': 'var(--color-accent-hover)',
        },
        '.text-accent': {
          'color': 'var(--color-accent)',
        },
        '.border-accent': {
          'border-color': 'var(--color-accent)',
        },
        '.focus\\:border-accent:focus': {
          'border-color': 'var(--color-accent)',
        },
        '.focus-visible\\:outline-accent:focus-visible': {
          'outline-color': 'var(--color-accent)',
        },
        // Gradientes con accent
        '.bg-gradient-accent': {
          'background': 'linear-gradient(to bottom right, var(--color-accent), var(--color-accent-hover))',
        },
        // Estados semánticos
        '.bg-success-badge': {
          'background-color': 'var(--color-success-bg)',
          'color': 'var(--color-success-text)',
        },
        '.bg-warning-badge': {
          'background-color': 'var(--color-warning-bg)',
          'color': 'var(--color-warning-text)',
        },
        '.bg-danger-badge': {
          'background-color': 'var(--color-danger-bg)',
          'color': 'var(--color-danger-text)',
        },
      });
    },
  ],
}



