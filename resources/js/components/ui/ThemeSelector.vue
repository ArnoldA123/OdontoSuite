<template>
  <!-- Compact mode: 3 buttons inline -->
  <div v-if="compact" class="flex gap-1">
    <button
      v-for="option in getThemeOptions()"
      :key="option.value"
      @click="setTheme(option.value)"
      :class="getCompactButtonClasses(option.value)"
      :aria-label="option.description"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path v-if="option.icon === 'sun'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        <path v-else-if="option.icon === 'moon'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
      </svg>
      <span class="text-xs">{{ option.label }}</span>
    </button>
  </div>

  <!-- Normal mode: Dropdown -->
  <div v-else class="relative" ref="containerRef">
    <!-- Desktop: Dropdown normal -->
    <div v-if="!isMobile" class="relative">
      <!-- Trigger button -->
      <button
        ref="triggerRef"
        :class="triggerClasses"
        :aria-label="`Cambiar tema. Actual: ${getThemeLabel()}`"
        :aria-expanded="isOpen"
        @click="toggleDropdown"
        @keydown.escape="closeDropdown"
      >
        <!-- Theme icon -->
        <div class="flex items-center gap-2">
        <svg
          v-if="getThemeIcon() === 'sun'"
          class="w-4 h-4"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>

        <svg
          v-else-if="getThemeIcon() === 'moon'"
          class="w-4 h-4"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>

        <svg
          v-else
          class="w-4 h-4"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>

        <span class="hidden sm:inline">{{ getThemeLabel() }}</span>
      </div>

      <!-- Dropdown arrow -->
      <svg
        class="w-4 h-4 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Dropdown menu -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0"
    >
      <div
        ref="dropdownRef"
        v-if="isOpen"
        :class="dropdownClasses"
        :style="position"
        role="menu"
        aria-orientation="vertical"
      >
        <div class="py-1">
          <button
            v-for="option in getThemeOptions()"
            :key="option.value"
            :class="optionClasses(option.value)"
            :aria-label="option.description"
            @click="() => selectTheme(option.value)"
          >
            <div class="flex items-center gap-3">
              <!-- Option icon -->
              <div class="flex-shrink-0">
                <svg
                  v-if="option.icon === 'sun'"
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>

                <svg
                  v-else-if="option.icon === 'moon'"
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>

                <svg
                  v-else
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>

              <!-- Option content -->
              <div class="flex-1 text-left">
                <div class="font-medium text-theme-primary">
                  {{ option.label }}
                </div>
                <div class="text-sm text-theme-secondary">
                  {{ option.description }}
                </div>
              </div>

              <!-- Check icon -->
              <div v-if="theme === option.value" class="flex-shrink-0">
                <svg class="w-4 h-4 text-accent" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </button>
        </div>
      </div>
    </Transition>
    </div>

    <!-- Mobile: Sheet full-screen -->
    <UiSheet
      v-else
      v-model="isOpen"
      position="bottom"
      title="Seleccionar tema"
    >
      <div class="space-y-2 p-4">
        <button
          v-for="option in getThemeOptions()"
          :key="option.value"
          @click="selectTheme(option.value)"
          :class="getMobileOptionClasses(option.value)"
          class="w-full p-4 rounded-lg text-left flex items-center gap-3"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path v-if="option.icon === 'sun'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            <path v-else-if="option.icon === 'moon'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <div class="flex-1">
            <div class="font-medium text-theme-primary">{{ option.label }}</div>
            <div class="text-sm text-theme-secondary">{{ option.description }}</div>
          </div>
          <svg v-if="theme === option.value" class="w-5 h-5 text-accent" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </UiSheet>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useTheme } from '@/composables/useTheme'
import { useDropdownPosition } from '@/composables/useDropdownPosition'
import UiSheet from './Sheet.vue'

const props = defineProps({
  compact: {
    type: Boolean,
    default: false
  }
})

const { theme, setTheme, getThemeIcon, getThemeLabel, getThemeOptions } = useTheme()

// Dropdown positioning
const triggerRef = ref(null)
const dropdownRef = ref(null)
const { position, isMobile, isOpen, openDropdown, closeDropdown, toggleDropdown } = useDropdownPosition(triggerRef, dropdownRef)

const containerRef = ref(null)

const triggerClasses = computed(() => [
  'flex items-center gap-2 px-3 py-2 text-sm font-medium',
  'text-theme-primary',
  'bg-theme-surface-elevated',
  'border border-theme',
  'rounded-lg shadow-sm',
  'hover:bg-theme-surface',
  'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
  'transition-colors duration-200',
  'z-dropdown'
])

const dropdownClasses = computed(() => [
  'w-64',
  'bg-theme-surface-elevated',
  'border border-theme',
  'rounded-lg shadow-lg',
  'z-dropdown',
  'origin-top-right'
])

const optionClasses = (optionValue) => [
  'w-full px-4 py-3 text-left',
  'hover:bg-theme-surface',
  'focus:bg-theme-surface',
  'focus:outline-none',
  'transition-colors duration-150',
  'first:rounded-t-lg last:rounded-b-lg',
  theme.value === optionValue ? 'bg-primary-50' : ''
]

const getCompactButtonClasses = (optionValue) => [
  'flex flex-col items-center gap-1 px-2 py-2 text-xs font-medium',
  'text-theme-secondary',
  'bg-theme-surface',
  'rounded-md',
  'hover:bg-theme-surface-elevated',
  'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
  'transition-colors duration-200',
  'flex-1',
  theme.value === optionValue ? 'bg-primary-100 text-primary-700' : ''
]

const getMobileOptionClasses = (optionValue) => [
  'w-full p-4 rounded-lg text-left flex items-center gap-3',
  'hover:bg-theme-surface',
  'focus:bg-theme-surface',
  'focus:outline-none',
  'transition-colors duration-150',
  theme.value === optionValue ? 'bg-primary-50' : ''
]

const selectTheme = (themeValue) => {
  setTheme(themeValue)
  closeDropdown()
}




</script>

<style scoped>
/* Custom scrollbar for dropdown */
.dropdown-menu::-webkit-scrollbar {
  width: 6px;
}

.dropdown-menu::-webkit-scrollbar-track {
  background: transparent;
}

.dropdown-menu::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}


/* Focus styles for accessibility */
button:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* Smooth transitions */
* {
  transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
}
</style>
