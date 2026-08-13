<template>
  <div :class="containerClasses">
    <!-- Illustration/Icon -->
    <div class="empty-state-icon">
      <div v-if="illustration" class="empty-state-illustration">
        <img
          v-if="typeof illustration === 'string'"
          :src="illustration"
          :alt="title"
          class="w-full h-full object-contain"
        />
        <component :is="illustration" v-else class="w-full h-full" />
      </div>

      <div v-else-if="icon" class="empty-state-icon-container">
        <component :is="icon" class="w-16 h-16" />
      </div>

      <!-- Default icon if none provided -->
      <div v-else class="empty-state-icon-container">
        <svg class="w-16 h-16" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1"
            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 009.586 13H7"
          />
        </svg>
      </div>
    </div>

    <!-- Content -->
    <div class="empty-state-content">
      <!-- Title -->
      <h3 v-if="title" class="empty-state-title">
        {{ title }}
      </h3>

      <!-- Description -->
      <p v-if="description" class="empty-state-description">
        {{ description }}
      </p>

      <!-- Action button -->
      <div v-if="$slots.action || actionText" class="empty-state-action">
        <slot name="action">
          <UiButton
            v-if="actionText"
            :variant="actionVariant"
            :size="actionSize"
            @click="handleAction"
          >
            {{ actionText }}
          </UiButton>
        </slot>
      </div>

      <!-- Additional content -->
      <div v-if="$slots.default" class="empty-state-additional">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import UiButton from './Button.vue'

const props = defineProps({
  title: {
    type: String,
    default: ''
  },
  description: {
    type: String,
    default: ''
  },
  icon: {
    type: [String, Object],
    default: null
  },
  illustration: {
    type: [String, Object],
    default: null
  },
  actionText: {
    type: String,
    default: ''
  },
  actionVariant: {
    type: String,
    default: 'primary',
    validator: value =>
      ['primary', 'secondary', 'ghost', 'danger', 'success', 'warning'].includes(value)
  },
  actionSize: {
    type: String,
    default: 'md',
    validator: value => ['sm', 'md', 'lg'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: value => ['sm', 'md', 'lg', 'xl'].includes(value)
  },
  variant: {
    type: String,
    default: 'default',
    validator: value => ['default', 'minimal', 'centered'].includes(value)
  },
  centered: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['action'])

// Computed
const containerClasses = computed(() => {
  const base = [
    'empty-state',
    'flex flex-col',
    props.centered ? 'items-center text-center' : 'items-start text-left'
  ]

  const sizes = {
    sm: 'py-8 px-4',
    md: 'py-12 px-6',
    lg: 'py-16 px-8',
    xl: 'py-20 px-10'
  }

  const variants = {
    default: 'bg-theme-surface-elevated rounded-ios border border-theme',
    minimal: 'bg-transparent',
    centered: 'bg-theme-surface rounded-ios'
  }

  return [...base, sizes[props.size], variants[props.variant]].join(' ')
})

// Methods
const handleAction = () => {
  emit('action')
}
</script>

<style scoped>
.empty-state {
  @apply w-full;
}

.empty-state-icon {
  @apply mb-6;
}

.empty-state-illustration {
  @apply w-32 h-32 mx-auto;
}

.empty-state-icon-container {
  @apply w-16 h-16 mx-auto text-theme-secondary;
}

.empty-state-content {
  @apply space-y-4;
}

.empty-state-title {
  @apply text-lg font-semibold text-theme-primary;
}

.empty-state-description {
  @apply text-sm text-theme-secondary max-w-md;
}

.empty-state-action {
  @apply pt-2;
}

.empty-state-additional {
  @apply pt-4 border-t border-theme;
}

/* Size variants */
.empty-state[data-size='sm'] .empty-state-title {
  @apply text-base;
}

.empty-state[data-size='sm'] .empty-state-description {
  @apply text-xs;
}

.empty-state[data-size='lg'] .empty-state-title {
  @apply text-xl;
}

.empty-state[data-size='lg'] .empty-state-description {
  @apply text-base;
}

.empty-state[data-size='xl'] .empty-state-title {
  @apply text-2xl;
}

.empty-state[data-size='xl'] .empty-state-description {
  @apply text-lg;
}

/* Variant styles */
.empty-state[data-variant='minimal'] {
  @apply border-0 bg-transparent;
}

.empty-state[data-variant='centered'] {
  @apply shadow-sm;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .empty-state {
    @apply py-8 px-4;
  }

  .empty-state-illustration {
    @apply w-24 h-24;
  }

  .empty-state-icon-container {
    @apply w-12 h-12;
  }
}
</style>
