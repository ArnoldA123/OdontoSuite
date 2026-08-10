<template>
  <div :class="avatarClasses" :data-size="size" :data-variant="variant">
    <!-- Image avatar -->
    <img
      v-if="src && !imageError"
      :src="src"
      :alt="alt"
      :class="imageClasses"
      @error="handleImageError"
      @load="handleImageLoad"
    />

    <!-- Fallback content -->
    <div v-else-if="!loading" :class="fallbackClasses">
      <slot name="fallback">
        <span v-if="initials" class="avatar-initials">{{ initials }}</span>
        <svg v-else class="avatar-icon" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
      </slot>
    </div>

    <!-- Loading state -->
    <div v-if="loading" :class="loadingClasses">
      <div class="avatar-skeleton"></div>
    </div>

    <!-- Status indicator -->
    <div v-if="status" :class="statusClasses" :data-status="status"></div>

    <!-- Online indicator -->
    <div v-if="online !== null" :class="onlineClasses" :data-online="online"></div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  src: String,
  alt: String,
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl', '2xl'].includes(value)
  },
  variant: {
    type: String,
    default: 'circle',
    validator: (value) => ['circle', 'square', 'rounded'].includes(value)
  },
  initials: String,
  status: {
    type: String,
    validator: (value) => !value || ['online', 'offline', 'away', 'busy'].includes(value)
  },
  online: { type: Boolean, default: null },
  loading: { type: Boolean, default: false },
  clickable: { type: Boolean, default: false }
})

const emit = defineEmits(['click', 'load', 'error'])

// Image error state
const imageError = ref(false)

const avatarClasses = computed(() => {
  const base = [
    'relative inline-flex items-center justify-center',
    'bg-theme-surface text-theme-secondary',
    'overflow-hidden select-none',
    'transition-all duration-200'
  ]

  // Size variants
  const sizes = {
    xs: 'w-6 h-6 text-xs',
    sm: 'w-8 h-8 text-sm',
    md: 'w-10 h-10 text-base',
    lg: 'w-12 h-12 text-lg',
    xl: 'w-16 h-16 text-xl',
    '2xl': 'w-20 h-20 text-2xl'
  }

  // Shape variants
  const shapes = {
    circle: 'rounded-full',
    square: 'rounded-md',
    rounded: 'rounded-lg'
  }

  // Interactive states
  const interactive = []
  if (props.clickable) {
    interactive.push('cursor-pointer hover:scale-105 active:scale-95')
  }

  return [
    ...base,
    sizes[props.size],
    shapes[props.variant],
    ...interactive
  ].join(' ')
})

const imageClasses = computed(() => [
  'w-full h-full object-cover',
  props.variant === 'circle' ? 'rounded-full' : '',
  props.variant === 'square' ? 'rounded-md' : '',
  props.variant === 'rounded' ? 'rounded-lg' : ''
])

const fallbackClasses = computed(() => [
  'w-full h-full flex items-center justify-center',
  'bg-theme-surface',
  'text-theme-secondary font-medium'
])

const loadingClasses = computed(() => [
  'w-full h-full flex items-center justify-center',
  'bg-theme-surface'
])

const statusClasses = computed(() => {
  const base = [
    'absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white',
    'transition-all duration-200'
  ]

  const statusColors = {
    online: 'bg-green-500',
    offline: 'bg-theme-secondary',
    away: 'bg-yellow-500',
    busy: 'bg-red-500'
  }

  return [
    ...base,
    statusColors[props.status]
  ].join(' ')
})

const onlineClasses = computed(() => [
  'absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white',
    props.online ? 'bg-green-500' : 'bg-theme-secondary'
])

// Event handlers
const handleImageError = () => {
  imageError.value = true
  emit('error')
}

const handleImageLoad = () => {
  imageError.value = false
  emit('load')
}
</script>

<style scoped>
.avatar-initials {
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.avatar-icon {
  width: 60%;
  height: 60%;
  opacity: 0.6;
}

.avatar-skeleton {
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.4),
    transparent
  );
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

/* Status indicator animations */
[data-status="online"] {
  animation: pulse 2s infinite;
}

[data-status="away"] {
  animation: pulse 1s infinite;
}

[data-status="busy"] {
  animation: pulse 0.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Hover effects */
.avatar:hover {
  box-shadow: var(--shadow-soft);
}

.avatar:active {
  transform: scale(0.95);
}

/* Focus styles for clickable avatars */
.avatar:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* Size-specific adjustments */
[data-size="xs"] {
  min-width: 24px;
  min-height: 24px;
}

[data-size="sm"] {
  min-width: 32px;
  min-height: 32px;
}

[data-size="md"] {
  min-width: 40px;
  min-height: 40px;
}

[data-size="lg"] {
  min-width: 48px;
  min-height: 48px;
}

[data-size="xl"] {
  min-width: 64px;
  min-height: 64px;
}

[data-size="2xl"] {
  min-width: 80px;
  min-height: 80px;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .avatar {
    min-width: 32px;
    min-height: 32px;
  }
}
</style>
