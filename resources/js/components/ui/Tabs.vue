<template>
  <div class="tabs-container">
    <!-- Tab list -->
    <div :class="tabListClasses" role="tablist" :aria-label="ariaLabel">
      <button
        v-for="(tab, index) in tabs"
        :id="`tab-${tab.id}`"
        :key="tab.id"
        :class="getTabClasses(tab, index)"
        :aria-selected="activeTab === tab.id"
        :aria-controls="`panel-${tab.id}`"
        :tabindex="activeTab === tab.id ? 0 : -1"
        role="tab"
        @click="setActiveTab(tab.id)"
        @keydown.left="navigateTabs('left')"
        @keydown.right="navigateTabs('right')"
        @keydown.home.prevent="setActiveTab(tabs[0].id)"
        @keydown.end.prevent="setActiveTab(tabs[tabs.length - 1].id)"
      >
        <div class="flex items-center gap-2">
          <!-- Tab icon -->
          <div v-if="tab.icon" class="flex-shrink-0">
            <component :is="tab.icon" class="w-4 h-4" />
          </div>

          <!-- Tab label -->
          <span class="truncate">{{ tab.label }}</span>

          <!-- Tab badge -->
          <UiBadge
            v-if="tab.badge"
            :variant="tab.badgeVariant || 'primary'"
            :size="tab.badgeSize || 'xs'"
          >
            {{ tab.badge }}
          </UiBadge>
        </div>

        <!-- Active indicator -->
        <div v-if="activeTab === tab.id" :class="indicatorClasses" />
      </button>
    </div>

    <!-- Tab panels -->
    <div class="tabs-content">
      <div
        v-for="tab in tabs"
        v-show="activeTab === tab.id"
        :id="`panel-${tab.id}`"
        :key="`panel-${tab.id}`"
        :class="getPanelClasses(tab)"
        :aria-labelledby="`tab-${tab.id}`"
        role="tabpanel"
      >
        <slot :name="tab.id" :tab="tab" :is-active="activeTab === tab.id">
          {{ tab.content }}
        </slot>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import UiBadge from './Badge.vue'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: null
  },
  tabs: {
    type: Array,
    required: true,
    validator: tabs => {
      return tabs.every(
        tab => tab && typeof tab.id !== 'undefined' && typeof tab.label === 'string'
      )
    }
  },
  variant: {
    type: String,
    default: 'default',
    validator: value => ['default', 'pills', 'underline', 'cards'].includes(value)
  },
  orientation: {
    type: String,
    default: 'horizontal',
    validator: value => ['horizontal', 'vertical'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: value => ['sm', 'md', 'lg'].includes(value)
  },
  fullWidth: {
    type: Boolean,
    default: false
  },
  ariaLabel: {
    type: String,
    default: 'Tabs'
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// State
const activeTab = ref(props.modelValue || props.tabs[0]?.id)

// Computed
const tabListClasses = computed(() => {
  const base = [
    'flex',
    props.orientation === 'vertical' ? 'flex-col' : 'flex-row',
    'border-b border-theme',
    props.orientation === 'vertical' ? 'border-b-0 border-r' : '',
    'mb-6'
  ]

  const variants = {
    default: [],
    pills: ['bg-theme-surface rounded-ios p-1'],
    underline: [],
    cards: ['bg-theme-surface rounded-ios p-1']
  }

  return [...base, ...variants[props.variant]].join(' ')
})

const indicatorClasses = computed(() => {
  const base = ['absolute', 'bg-systemBlue-500']

  if (props.orientation === 'vertical') {
    return [...base, 'left-0 top-0 bottom-0 w-0.5'].join(' ')
  }

  return [...base, 'bottom-0 left-0 right-0 h-0.5'].join(' ')
})

// Methods
const getTabClasses = (tab, index) => {
  const base = [
    'relative flex items-center justify-center',
    'px-4 py-2 text-sm font-medium',
    'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
    'disabled:opacity-50 disabled:cursor-not-allowed'
  ]

  // Size variants
  const sizes = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base'
  }

  // Orientation
  const orientation = props.orientation === 'vertical' ? 'w-full justify-start' : ''

  // Full width
  const fullWidth = props.fullWidth ? 'flex-1' : ''

  // Variant styles
  const variants = {
    default: [
      'text-theme-secondary',
      'hover:text-theme-primary',
      activeTab.value === tab.id ? 'text-systemBlue-600' : ''
    ],
    pills: [
      'rounded-md',
      'text-theme-secondary',
      'hover:text-theme-primary hover:bg-theme-surface',
      activeTab.value === tab.id ? 'bg-theme-surface-elevated text-systemBlue-600 shadow-sm' : ''
    ],
    underline: [
      'text-theme-secondary',
      'hover:text-theme-primary',
      'border-b-2 border-transparent',
      activeTab.value === tab.id
        ? 'text-systemBlue-600 border-systemBlue-500'
        : 'hover:border-theme'
    ],
    cards: [
      'rounded-md',
      'text-theme-secondary',
      'hover:text-theme-primary',
      activeTab.value === tab.id
        ? 'bg-theme-surface-elevated text-systemBlue-600 shadow-sm'
        : 'hover:bg-theme-surface'
    ]
  }

  return [...base, sizes[props.size], orientation, fullWidth, ...variants[props.variant]].join(' ')
}

const getPanelClasses = tab => {
  return ['tabs-panel', 'focus:outline-none']
}

const setActiveTab = tabId => {
  if (activeTab.value !== tabId) {
    activeTab.value = tabId
    emit('update:modelValue', tabId)
    emit('change', tabId)
  }
}

const navigateTabs = direction => {
  const currentIndex = props.tabs.findIndex(tab => tab.id === activeTab.value)
  let newIndex

  if (direction === 'left') {
    newIndex = currentIndex > 0 ? currentIndex - 1 : props.tabs.length - 1
  } else {
    newIndex = currentIndex < props.tabs.length - 1 ? currentIndex + 1 : 0
  }

  setActiveTab(props.tabs[newIndex].id)
}

// Watch for external changes
watch(
  () => props.modelValue,
  newValue => {
    if (newValue !== activeTab.value) {
      activeTab.value = newValue
    }
  }
)

watch(
  () => props.tabs,
  newTabs => {
    if (newTabs.length > 0 && !newTabs.find(tab => tab.id === activeTab.value)) {
      activeTab.value = newTabs[0].id
    }
  }
)
</script>

<style scoped>
.tabs-container {
  @apply w-full;
}

.tabs-content {
  @apply relative;
}

.tabs-panel {
  @apply w-full;
}

/* Focus styles for accessibility */
button:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* Scoped transitions — replaces the removed global `* { transition }` rule
   from PR1. Tabs only animate color / background-color / border-color. */
button {
  transition:
    color 200ms ease-out,
    background-color 200ms ease-out,
    border-color 200ms ease-out;
}

.indicator {
  transition: all 200ms ease-out;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .tabs-container {
    @apply overflow-x-auto;
  }

  .tabs-container::-webkit-scrollbar {
    @apply hidden;
  }
}
</style>
