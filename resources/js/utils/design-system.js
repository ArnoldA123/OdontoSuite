/**
 * Design System Utilities - OdontoSuite
 * Utilidades para el sistema de diseño minimalista
 */

// Constantes del sistema de diseño
export const DESIGN_TOKENS = {
  colors: {
    primary: {
      50: '#e6f0ff',
      100: '#cce1ff',
      500: '#0066CC',
      600: '#0052a3',
      700: '#003d7a',
    },
    neutral: {
      50: '#fafafa',
      100: '#f5f5f5',
      200: '#e5e5e5',
      600: '#525252',
      900: '#171717',
    },
    success: '#10b981',
    warning: '#f59e0b',
    error: '#ef4444',
    info: '#0066CC',
  },
  spacing: {
    1: '4px',
    2: '8px',
    3: '12px',
    4: '16px',
    5: '20px',
    6: '24px',
    8: '32px',
    12: '48px',
    16: '64px',
  },
  fontSize: {
    xs: '11px',
    sm: '13px',
    base: '15px',
    lg: '17px',
    xl: '20px',
    '2xl': '24px',
    '3xl': '28px',
    '4xl': '34px',
  },
  borderRadius: {
    sm: '4px',
    md: '8px',
    lg: '12px',
    xl: '16px',
    '2xl': '20px',
    full: '9999px',
  },
  shadows: {
    subtle: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
    soft: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
    medium: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
    large: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
    elevated: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
  },
  transitions: {
    fast: '150ms ease-out',
    normal: '200ms ease-out',
    slow: '300ms ease-out',
    bounce: '200ms cubic-bezier(0.68, -0.55, 0.265, 1.55)',
    ios: '200ms cubic-bezier(0.25, 0.46, 0.45, 0.94)',
  },
  zIndex: {
    dropdown: 1000,
    sticky: 1020,
    fixed: 1030,
    modalBackdrop: 1040,
    modal: 1050,
    popover: 1060,
    tooltip: 1070,
    toast: 1080,
  },
}

// Utilidades para generar clases CSS dinámicamente
export const generateClasses = {
  // Generar clases de espaciado
  spacing: (property, value) => {
    const spacingMap = {
      p: 'padding',
      pt: 'padding-top',
      pr: 'padding-right',
      pb: 'padding-bottom',
      pl: 'padding-left',
      px: 'padding-left,padding-right',
      py: 'padding-top,padding-bottom',
      m: 'margin',
      mt: 'margin-top',
      mr: 'margin-right',
      mb: 'margin-bottom',
      ml: 'margin-left',
      mx: 'margin-left,margin-right',
      my: 'margin-top,margin-bottom',
    }

    const properties = spacingMap[property]?.split(',') || [property]
    const spacingValue = DESIGN_TOKENS.spacing[value] || value

    return properties.reduce((acc, prop) => {
      acc[prop] = spacingValue
      return acc
    }, {})
  },

  // Generar clases de colores
  color: (variant, shade = 500) => {
    const colorMap = {
      primary: DESIGN_TOKENS.colors.primary,
      neutral: DESIGN_TOKENS.colors.neutral,
      success: DESIGN_TOKENS.colors.success,
      warning: DESIGN_TOKENS.colors.warning,
      error: DESIGN_TOKENS.colors.error,
      info: DESIGN_TOKENS.colors.info,
    }

    const color = colorMap[variant]
    if (typeof color === 'string') return color
    return color[shade] || color[500]
  },

  // Generar clases de sombra
  shadow: (level) => {
    const shadowMap = {
      subtle: DESIGN_TOKENS.shadows.subtle,
      soft: DESIGN_TOKENS.shadows.soft,
      medium: DESIGN_TOKENS.shadows.medium,
      large: DESIGN_TOKENS.shadows.large,
      elevated: DESIGN_TOKENS.shadows.elevated,
    }

    return shadowMap[level] || DESIGN_TOKENS.shadows.soft
  },
}

// Utilidades para componentes
export const componentUtils = {
  // Generar props para botones
  getButtonProps: (variant = 'primary', size = 'md') => {
    const variants = {
      primary: {
        bg: 'bg-primary-500',
        hover: 'hover:bg-primary-600',
        text: 'text-white',
        border: 'border-transparent',
      },
      secondary: {
        bg: 'bg-transparent',
        hover: 'hover:bg-neutral-100',
        text: 'text-neutral-900',
        border: 'border-neutral-200',
      },
      ghost: {
        bg: 'bg-transparent',
        hover: 'hover:bg-neutral-100',
        text: 'text-neutral-600',
        border: 'border-transparent',
      },
      danger: {
        bg: 'bg-error-500',
        hover: 'hover:bg-error-600',
        text: 'text-white',
        border: 'border-transparent',
      },
    }

    const sizes = {
      sm: 'px-3 py-1.5 text-sm',
      md: 'px-4 py-2 text-base',
      lg: 'px-6 py-3 text-lg',
      xl: 'px-8 py-4 text-xl',
    }

    return {
      ...variants[variant],
      size: sizes[size],
      base: 'inline-flex items-center justify-center rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed',
    }
  },

  // Generar props para inputs
  getInputProps: (variant = 'default', size = 'md') => {
    const variants = {
      default: {
        border: 'border-neutral-200',
        focus: 'focus:border-primary-500 focus:ring-primary-500',
        bg: 'bg-white',
      },
      error: {
        border: 'border-error-500',
        focus: 'focus:border-error-500 focus:ring-error-500',
        bg: 'bg-white',
      },
      success: {
        border: 'border-success-500',
        focus: 'focus:border-success-500 focus:ring-success-500',
        bg: 'bg-white',
      },
    }

    const sizes = {
      sm: 'h-8 px-3 text-sm',
      md: 'h-11 px-4 text-base',
      lg: 'h-12 px-4 text-lg',
    }

    return {
      ...variants[variant],
      size: sizes[size],
      base: 'w-full rounded-md border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:opacity-50 disabled:cursor-not-allowed',
    }
  },

  // Generar props para cards
  getCardProps: (variant = 'default', padding = 'md') => {
    const variants = {
      default: {
        bg: 'bg-white',
        border: 'border-neutral-200',
        shadow: 'shadow-soft',
      },
      glass: {
        bg: 'bg-white/50',
        border: 'border-white/20',
        shadow: 'shadow-glass',
        backdrop: 'backdrop-blur-md',
      },
      flat: {
        bg: 'bg-white',
        border: 'border-neutral-200',
        shadow: 'shadow-none',
      },
    }

    const paddings = {
      sm: 'p-4',
      md: 'p-6',
      lg: 'p-8',
    }

    return {
      ...variants[variant],
      padding: paddings[padding],
      base: 'rounded-xl transition-all duration-200',
    }
  },
}

// Utilidades para animaciones
export const animationUtils = {
  // Generar clases de animación
  getAnimationClasses: (type, duration = 'normal') => {
    const animations = {
      fadeIn: 'animate-fade-in',
      slideUp: 'animate-slide-up',
      slideDown: 'animate-slide-down',
      scaleIn: 'animate-scale-in',
      bounce: 'animate-bounce-subtle',
      pulse: 'animate-pulse-subtle',
    }

    const durations = {
      fast: 'duration-150',
      normal: 'duration-200',
      slow: 'duration-300',
    }

    return `${animations[type]} ${durations[duration]}`
  },

  // Generar transiciones
  getTransitionClasses: (properties = 'all', duration = 'normal') => {
    const durations = {
      fast: 'duration-150',
      normal: 'duration-200',
      slow: 'duration-300',
    }

    const easings = {
      ease: 'ease-out',
      bounce: 'ease-bounce-soft',
      ios: 'ease-ios',
    }

    return `transition-${properties} ${durations[duration]} ${easings.ease}`
  },
}

// Utilidades para responsive
export const responsiveUtils = {
  // Breakpoints
  breakpoints: {
    sm: '640px',
    md: '768px',
    lg: '1024px',
    xl: '1280px',
    '2xl': '1536px',
  },

  // Generar clases responsive
  getResponsiveClasses: (baseClasses, responsiveClasses = {}) => {
    let classes = baseClasses

    Object.entries(responsiveClasses).forEach(([breakpoint, classes]) => {
      classes += ` ${breakpoint}:${classes}`
    })

    return classes
  },
}

// Utilidades para accesibilidad
export const accessibilityUtils = {
  // Generar ARIA labels
  getAriaLabel: (action, context = '') => {
    const labels = {
      close: 'Cerrar',
      open: 'Abrir',
      edit: 'Editar',
      delete: 'Eliminar',
      save: 'Guardar',
      cancel: 'Cancelar',
      submit: 'Enviar',
      search: 'Buscar',
      filter: 'Filtrar',
      sort: 'Ordenar',
      expand: 'Expandir',
      collapse: 'Colapsar',
    }

    const baseLabel = labels[action] || action
    return context ? `${baseLabel} ${context}` : baseLabel
  },

  // Generar clases de focus
  getFocusClasses: (variant = 'default') => {
    const focusVariants = {
      default: 'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
      error: 'focus:outline-none focus:ring-2 focus:ring-error-500 focus:ring-offset-2',
      success: 'focus:outline-none focus:ring-2 focus:ring-success-500 focus:ring-offset-2',
    }

    return focusVariants[variant]
  },
}

// Utilidades para estados
export const stateUtils = {
  // Generar clases de estado
  getStateClasses: (state, variant = 'default') => {
    const stateClasses = {
      loading: {
        default: 'opacity-50 cursor-wait',
        button: 'opacity-50 cursor-wait pointer-events-none',
        input: 'opacity-50 cursor-wait',
      },
      disabled: {
        default: 'opacity-50 cursor-not-allowed pointer-events-none',
        button: 'opacity-50 cursor-not-allowed pointer-events-none',
        input: 'opacity-50 cursor-not-allowed',
      },
      error: {
        default: 'border-error-500 text-error-600',
        input: 'border-error-500 focus:ring-error-500',
        button: 'bg-error-500 hover:bg-error-600',
      },
      success: {
        default: 'border-success-500 text-success-600',
        input: 'border-success-500 focus:ring-success-500',
        button: 'bg-success-500 hover:bg-success-600',
      },
    }

    return stateClasses[state]?.[variant] || ''
  },
}

// Exportar todas las utilidades
export default {
  DESIGN_TOKENS,
  generateClasses,
  componentUtils,
  animationUtils,
  responsiveUtils,
  accessibilityUtils,
  stateUtils,
}
