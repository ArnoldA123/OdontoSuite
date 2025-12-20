import { ref, computed, onMounted } from 'vue'

const THEME_KEY = 'odontosuite-theme'
const THEMES = {
  LIGHT: 'light'
}

// Estado global del tema - siempre claro
const currentTheme = ref(THEMES.LIGHT)
const isDark = ref(false)

// Aplicar tema al DOM - siempre claro
const applyTheme = () => {
  if (typeof document === 'undefined') return

  const root = document.documentElement
  // Asegurar que no haya clase dark
  root.classList.remove('dark')
  isDark.value = false
}

// Cargar tema guardado - siempre claro
const loadSavedTheme = () => {
  // Forzar siempre tema claro
  currentTheme.value = THEMES.LIGHT
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(THEME_KEY, THEMES.LIGHT)
  }
  applyTheme()
}

export function useTheme() {
  // Computed properties
  const theme = computed(() => currentTheme.value)
  const isDarkMode = computed(() => false) // Siempre false
  const isLightMode = computed(() => true) // Siempre true

  // Cambiar tema - siempre ignora y mantiene claro
  const setTheme = (newTheme) => {
    // Ignorar cualquier intento de cambiar el tema
    currentTheme.value = THEMES.LIGHT
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(THEME_KEY, THEMES.LIGHT)
    }
    applyTheme()
  }

  // Obtener ícono del tema actual - siempre sol
  const getThemeIcon = () => 'sun'

  // Obtener etiqueta del tema actual - siempre claro
  const getThemeLabel = () => 'Claro'

  // Obtener opciones de tema para dropdown - solo claro
  const getThemeOptions = () => [
    {
      value: THEMES.LIGHT,
      label: 'Claro',
      icon: 'sun',
      description: 'Tema claro'
    }
  ]

  // Inicializar tema al montar - siempre claro
  onMounted(() => {
    loadSavedTheme()
  })

  return {
    // Estado
    theme,
    isDarkMode,
    isLightMode,

    // Métodos
    setTheme,

    // Utilidades
    getThemeIcon,
    getThemeLabel,
    getThemeOptions,

    // Constantes
    THEMES
  }
}
