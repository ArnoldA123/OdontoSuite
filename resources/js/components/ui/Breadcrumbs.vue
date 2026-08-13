<template>
  <nav :class="breadcrumbsClasses" :aria-label="ariaLabel">
    <ol class="flex items-center space-x-1">
      <!-- Home/Start item -->
      <li v-if="showHome" class="flex items-center">
        <router-link :to="homeTo" :class="getBreadcrumbClasses(true, true)" :aria-label="homeLabel">
          <component :is="homeIcon" class="w-4 h-4" />
          <span class="sr-only">{{ homeLabel }}</span>
        </router-link>
      </li>

      <!-- Separator after home -->
      <li v-if="showHome && items.length > 0" class="flex items-center">
        <component :is="separator" class="w-4 h-4 text-systemGray-500" />
      </li>

      <!-- Breadcrumb items -->
      <li v-for="(item, index) in visibleItems" :key="item.id || index" class="flex items-center">
        <!-- Item content -->
        <component
          :is="item.to ? 'router-link' : 'span'"
          :to="item.to"
          :class="getBreadcrumbClasses(index === visibleItems.length - 1, !!item.to)"
          :aria-current="index === visibleItems.length - 1 ? 'page' : undefined"
        >
          <!-- Item icon -->
          <component :is="item.icon" v-if="item.icon" class="w-4 h-4 flex-shrink-0" />

          <!-- Item label -->
          <span class="truncate">{{ item.label }}</span>

          <!-- Item badge -->
          <UiBadge
            v-if="item.badge"
            :variant="item.badgeVariant || 'primary'"
            :size="item.badgeSize || 'xs'"
            class="ml-2"
          >
            {{ item.badge }}
          </UiBadge>
        </component>

        <!-- Separator (not after last item) -->
        <component
          :is="separator"
          v-if="index < visibleItems.length - 1"
          class="w-4 h-4 text-systemGray-500 ml-2"
        />
      </li>

      <!-- Collapsed items indicator -->
      <li v-if="hasCollapsedItems" class="flex items-center">
        <UiButton
          variant="ghost"
          size="sm"
          :aria-label="`Mostrar ${collapsedItems.length} elementos ocultos`"
          @click="toggleCollapsed"
        >
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"
              />
            </svg>
          </template>
        </UiButton>
      </li>
    </ol>

    <!-- Collapsed items dropdown -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0"
    >
      <div v-if="showCollapsedDropdown" :class="dropdownClasses">
        <div class="py-1">
          <component
            :is="item.to ? 'router-link' : 'button'"
            v-for="(item, index) in collapsedItems"
            :key="item.id || index"
            :to="item.to"
            :class="getDropdownItemClasses(!!item.to)"
            @click="item.to ? null : item.onClick?.()"
          >
            <div class="flex items-center gap-3">
              <!-- Item icon -->
              <component :is="item.icon" v-if="item.icon" class="w-4 h-4 flex-shrink-0" />

              <!-- Item content -->
              <div class="flex-1 min-w-0">
                <div class="font-medium text-theme-primary truncate">
                  {{ item.label }}
                </div>
                <div v-if="item.description" class="text-sm text-theme-secondary truncate">
                  {{ item.description }}
                </div>
              </div>
            </div>
          </component>
        </div>
      </div>
    </Transition>
  </nav>
</template>

<script setup>
import { ref, computed } from 'vue'
import UiButton from './Button.vue'
import UiBadge from './Badge.vue'

const props = defineProps({
  items: {
    type: Array,
    required: true,
    validator: items => {
      return items.every(item => item && typeof item.label === 'string')
    }
  },
  separator: {
    type: [String, Object],
    default: 'svg'
  },
  maxItems: {
    type: Number,
    default: 5
  },
  showHome: {
    type: Boolean,
    default: true
  },
  homeTo: {
    type: [String, Object],
    default: '/'
  },
  homeLabel: {
    type: String,
    default: 'Inicio'
  },
  homeIcon: {
    type: [String, Object],
    default: 'svg'
  },
  size: {
    type: String,
    default: 'md',
    validator: value => ['sm', 'md', 'lg'].includes(value)
  },
  variant: {
    type: String,
    default: 'default',
    validator: value => ['default', 'minimal', 'solid'].includes(value)
  },
  ariaLabel: {
    type: String,
    default: 'Navegación'
  }
})

// State
const showCollapsedDropdown = ref(false)

// Computed
const visibleItems = computed(() => {
  if (props.items.length <= props.maxItems) {
    return props.items
  }

  const start = Math.max(0, props.items.length - props.maxItems + 1)
  return props.items.slice(start)
})

const collapsedItems = computed(() => {
  if (props.items.length <= props.maxItems) {
    return []
  }

  const end = props.items.length - props.maxItems + 1
  return props.items.slice(0, end)
})

const hasCollapsedItems = computed(() => collapsedItems.value.length > 0)

const breadcrumbsClasses = computed(() => {
  const base = ['breadcrumbs']

  const sizes = {
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-lg'
  }

  const variants = {
    default: 'text-theme-secondary',
    minimal: 'text-theme-secondary',
    solid: 'bg-theme-surface px-4 py-2 rounded-ios'
  }

  return [...base, sizes[props.size], variants[props.variant]].join(' ')
})

const dropdownClasses = computed(() => [
  'absolute top-full left-0 mt-2 w-64',
  'bg-theme-surface-elevated',
  'border border-theme',
  'rounded-ios shadow-lg',
  'z-dropdown'
])

// Methods
const getBreadcrumbClasses = (isLast, isLink) => {
  const base = [
    'flex items-center gap-2',
    'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
    'rounded-md'
  ]

  if (isLast) {
    return [...base, 'text-theme-primary font-medium', 'cursor-default'].join(' ')
  }

  if (isLink) {
    return [...base, 'text-theme-secondary', 'hover:text-theme-primary', 'cursor-pointer'].join(' ')
  }

  return [...base, 'text-theme-secondary', 'cursor-default'].join(' ')
}

const getDropdownItemClasses = isLink => {
  const base = [
    'w-full text-left px-4 py-3',
    'hover:bg-theme-surface',
    'focus:bg-theme-surface',
    'focus:outline-none',
    'rounded-md'
  ]

  if (isLink) {
    return [...base, 'text-theme-primary', 'hover:text-theme-primary'].join(' ')
  }

  return [...base, 'text-theme-secondary', 'hover:text-theme-primary'].join(' ')
}

const toggleCollapsed = () => {
  showCollapsedDropdown.value = !showCollapsedDropdown.value
}

// Default separator component
const defaultSeparator = {
  template: `
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
    </svg>
  `
}

// Default home icon component
const defaultHomeIcon = {
  template: `
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    </svg>
  `
}

// Use default components if not provided
const separator = computed(() => (props.separator === 'svg' ? defaultSeparator : props.separator))
const homeIcon = computed(() => (props.homeIcon === 'svg' ? defaultHomeIcon : props.homeIcon))
</script>

<style scoped>
.breadcrumbs {
  @apply relative;
}

/* Focus styles for accessibility */
a:focus-visible,
button:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* Scoped transitions — replaces the removed global `* { transition }` rule
   from PR1. The breadcrumbs only animate color / background-color /
   border-color. */
a,
button {
  transition:
    color 200ms ease-out,
    background-color 200ms ease-out,
    border-color 200ms ease-out;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .breadcrumbs {
    @apply overflow-x-auto;
  }

  .breadcrumbs::-webkit-scrollbar {
    @apply hidden;
  }
}
</style>
