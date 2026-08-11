<template>
  <div class="select-container" ref="containerRef">
    <!-- Trigger button -->
    <button
      :class="triggerClasses"
        :disabled="disabled"
      :aria-expanded="isOpen"
      :aria-haspopup="true"
      @click="toggleDropdown"
      @keydown.escape="closeDropdown"
      @keydown.enter.prevent="toggleDropdown"
      @keydown.space.prevent="toggleDropdown"
    >
      <div class="flex items-center justify-between w-full">
        <!-- Selected value or placeholder -->
        <div class="flex items-center gap-2 min-w-0 flex-1">
          <span v-if="selectedOption" class="truncate text-theme-primary">
            {{ selectedOption.label }}
          </span>
          <span v-else class="text-theme-secondary">
            {{ placeholder }}
          </span>
        </div>

        <!-- Dropdown arrow -->
        <svg
          class="w-4 h-4 text-theme-secondary transition-transform duration-200 flex-shrink-0"
          :class="{ 'rotate-180': isOpen }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
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
        v-if="isOpen"
        :class="dropdownClasses"
        role="listbox"
        :aria-label="`Opciones de ${label || 'selección'}`"
      >
        <!-- Search input -->
        <div v-if="searchable" class="p-3 border-b border-theme">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar..."
            size="sm"
            @click.stop
          >
            <template #prefix>
              <svg class="w-4 h-4 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </UiInput>
        </div>

        <!-- Options list -->
        <div class="max-h-60 overflow-y-auto custom-scrollbar">
          <!-- Grouped options -->
          <template v-if="groupedOptions.length > 0">
            <div
              v-for="group in groupedOptions"
              :key="group.label"
              class="select-option-group"
            >
              <div class="select-option-group-header">
                {{ group.label }}
              </div>
              <div class="select-option-group-options">
                <button
                  v-for="option in group.options"
                  :key="option.value"
                  :class="getOptionClasses(option)"
                  :aria-selected="isSelected(option)"
                  @click="selectOption(option)"
                  @keydown.enter.prevent="selectOption(option)"
                  @keydown.space.prevent="selectOption(option)"
                >
                  <div class="flex items-center gap-3">
                    <!-- Checkbox for multiple selection -->
                    <div v-if="multiple" class="flex-shrink-0">
                      <UiInput
                        :model-value="isSelected(option)"
                        type="checkbox"
                        size="sm"
                        @click.stop
                      />
                    </div>

                    <!-- Option icon -->
                    <div v-if="option.icon" class="flex-shrink-0">
                      <component :is="option.icon" class="w-4 h-4" />
                    </div>

                    <!-- Option content -->
                    <div class="flex-1 min-w-0">
                      <div class="font-medium text-theme-primary truncate">
                        {{ option.label }}
                      </div>
                      <div v-if="option.description" class="text-sm text-theme-secondary truncate">
                        {{ option.description }}
                      </div>
                    </div>

                    <!-- Check icon for single selection -->
                    <div v-if="!multiple && isSelected(option)" class="flex-shrink-0">
                      <svg class="w-4 h-4 text-accent" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                      </svg>
                    </div>
                  </div>
                </button>
              </div>
            </div>
          </template>

          <!-- Regular options -->
          <template v-else>
            <button
              v-for="option in filteredOptions"
              :key="option.value"
              :class="getOptionClasses(option)"
              :aria-selected="isSelected(option)"
              @click="selectOption(option)"
              @keydown.enter.prevent="selectOption(option)"
              @keydown.space.prevent="selectOption(option)"
            >
              <div class="flex items-center gap-3">
                <!-- Checkbox for multiple selection -->
                <div v-if="multiple" class="flex-shrink-0">
                  <UiInput
                    :model-value="isSelected(option)"
                    type="checkbox"
                    size="sm"
                    @click.stop
                  />
                </div>

                <!-- Option icon -->
                <div v-if="option.icon" class="flex-shrink-0">
                  <component :is="option.icon" class="w-4 h-4" />
                </div>

                <!-- Option content -->
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-theme-primary truncate">
                    {{ option.label }}
                  </div>
                  <div v-if="option.description" class="text-sm text-theme-secondary truncate">
                    {{ option.description }}
                  </div>
                </div>

                <!-- Check icon for single selection -->
                <div v-if="!multiple && isSelected(option)" class="flex-shrink-0">
                  <svg class="w-4 h-4 text-accent" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
              </div>
            </button>
          </template>

          <!-- Empty state -->
          <div v-if="filteredOptions.length === 0" class="p-4 text-center text-theme-secondary">
            No se encontraron opciones
          </div>
        </div>

        <!-- Clear button -->
        <div v-if="clearable && hasSelection" class="p-3 border-t border-theme">
          <UiButton
            variant="ghost"
            size="sm"
            @click="clearSelection"
            class="w-full"
          >
            <template #icon-left>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </template>
            Limpiar selección
          </UiButton>
      </div>
    </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import UiInput from './Input.vue'
import UiButton from './Button.vue'

const props = defineProps({
  modelValue: {
    type: [String, Number, Array],
    default: () => null
  },
  options: {
    type: Array,
    required: true
  },
  placeholder: {
    type: String,
    default: 'Seleccionar...'
  },
  label: String,
  disabled: {
    type: Boolean,
    default: false
  },
  error: String,
  hint: String,
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'filled', 'outlined'].includes(value)
  },
  multiple: {
    type: Boolean,
    default: false
  },
  searchable: {
    type: Boolean,
    default: false
  },
  clearable: {
    type: Boolean,
    default: false
  },
  grouped: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'focus', 'blur'])

// State
const isOpen = ref(false)
const searchQuery = ref('')
const containerRef = ref(null)

// Computed
const selectedOption = computed(() => {
  if (props.multiple) return null

  const value = props.modelValue
  return props.options?.find(option => option.value === value) || null
})

const selectedOptions = computed(() => {
  if (!props.multiple) return []

  const values = Array.isArray(props.modelValue) ? props.modelValue : []
  return props.options?.filter(option => values.includes(option.value)) || []
})

const filteredOptions = computed(() => {
  if (!props.searchable || !searchQuery.value) return props.options || []

  const query = searchQuery.value.toLowerCase()
  return props.options?.filter(option =>
    option.label.toLowerCase().includes(query) ||
    (option.description && option.description.toLowerCase().includes(query))
  ) || []
})

const groupedOptions = computed(() => {
  if (!props.grouped) return []

  const groups = {}
  filteredOptions.value.forEach(option => {
    const group = option.group || 'Sin categoría'
    if (!groups[group]) {
      groups[group] = { label: group, options: [] }
    }
    groups[group].options.push(option)
  })

  return Object.values(groups)
})

const hasSelection = computed(() => {
  if (props.multiple) {
    return Array.isArray(props.modelValue) && props.modelValue.length > 0
  }
  return props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== ''
})

const triggerClasses = computed(() => {
  const base = [
    'relative w-full text-left transition-all duration-200',
    'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    'text-theme-primary'
  ]

  // Size variants
  const sizes = {
    sm: 'px-3 py-2 text-sm rounded-md min-h-[36px]',
    md: 'px-4 py-3 text-base rounded-ios min-h-[44px]',
    lg: 'px-5 py-4 text-lg rounded-ios min-h-[52px]'
  }

  // Variant styles
  const variants = {
    default: 'bg-theme-surface-elevated border border-theme focus:border-accent',
    filled: 'bg-theme-surface border border-theme focus:bg-theme-surface-elevated focus:border-accent',
    outlined: 'bg-transparent border-2 border-theme focus:border-accent'
  }

  // State styles
  const state = props.error
    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
    : variants[props.variant]

  return [
    ...base,
    sizes[props.size],
    state
  ].join(' ')
})

const dropdownClasses = computed(() => [
  'absolute z-dropdown mt-1 w-full',
  'bg-theme-surface-elevated',
  'border border-theme',
  'rounded-ios shadow-lg',
  'origin-top-left',
  'dropdown-position'
])

const getOptionClasses = (option) => [
  'w-full text-left px-4 py-3 transition-colors duration-150',
  'hover:bg-theme-surface',
  'focus:bg-theme-surface',
  'focus:outline-none',
  isSelected(option) ? 'bg-primary-50' : ''
]

// Methods
const isSelected = (option) => {
  if (props.multiple) {
    return Array.isArray(props.modelValue) && props.modelValue.includes(option.value)
  }
  return props.modelValue === option.value
}

const selectOption = (option) => {
  if (props.multiple) {
    const currentValues = Array.isArray(props.modelValue) ? [...props.modelValue] : []
    const index = currentValues.indexOf(option.value)

    if (index > -1) {
      currentValues.splice(index, 1)
    } else {
      currentValues.push(option.value)
    }

    emit('update:modelValue', currentValues)
    emit('change', currentValues)
  } else {
    emit('update:modelValue', option.value)
    emit('change', option.value)
    closeDropdown()
  }
}

const clearSelection = () => {
  const newValue = props.multiple ? [] : null
  emit('update:modelValue', newValue)
  emit('change', newValue)
  closeDropdown()
}

const toggleDropdown = () => {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    emit('focus')
  } else {
    emit('blur')
  }
}

const closeDropdown = () => {
  isOpen.value = false
  searchQuery.value = ''
  emit('blur')
}

const handleClickOutside = (event) => {
  if (containerRef.value && !containerRef.value.contains(event.target)) {
    closeDropdown()
  }
}

// Lifecycle
onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.select-container {
  @apply relative;
}

.select-option-group {
  @apply border-b border-theme last:border-b-0;
}

.select-option-group-header {
  @apply px-4 py-2 text-xs font-medium text-theme-secondary uppercase tracking-wider bg-theme-surface;
}

.select-option-group-options {
  @apply divide-y divide-theme;
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
