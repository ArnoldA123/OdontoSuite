<template>
  <div :class="containerClasses" :aria-label="ariaLabel">
    <div
      v-for="(item, index) in items"
      :key="index"
      :class="getItemClasses(item)"
      :style="getItemStyle(item)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'text',
    validator: (value) => ['text', 'rectangular', 'circular', 'card', 'table', 'list'].includes(value)
  },
  width: {
    type: [String, Number],
    default: '100%'
  },
  height: {
    type: [String, Number],
    default: '1rem'
  },
  count: {
    type: Number,
    default: 1
  },
  animation: {
    type: String,
    default: 'pulse',
    validator: (value) => ['pulse', 'wave', 'none'].includes(value)
  },
  rounded: {
    type: Boolean,
    default: true
  },
  ariaLabel: {
    type: String,
    default: 'Cargando contenido...'
  }
})

const containerClasses = computed(() => [
  'skeleton-container',
  'space-y-2'
])

const items = computed(() => {
  const count = props.count
  const variants = {
    text: [
      { width: '100%', height: '1rem' },
      { width: '80%', height: '1rem' },
      { width: '60%', height: '1rem' }
    ],
    rectangular: [
      { width: '100%', height: '200px' }
    ],
    circular: [
      { width: '60px', height: '60px', borderRadius: '50%' }
    ],
    card: [
      { width: '100%', height: '200px' },
      { width: '100%', height: '1rem' },
      { width: '80%', height: '1rem' },
      { width: '60%', height: '1rem' }
    ],
    table: [
      { width: '100%', height: '1rem' },
      { width: '100%', height: '1rem' },
      { width: '100%', height: '1rem' },
      { width: '100%', height: '1rem' }
    ],
    list: [
      { width: '100%', height: '1rem' },
      { width: '100%', height: '1rem' },
      { width: '100%', height: '1rem' }
    ]
  }

  const baseItems = variants[props.variant] || [{ width: '100%', height: '1rem' }]
  return Array(props.count).fill(null).map((_, index) => ({
    ...baseItems[index % baseItems.length],
    id: index
  }))
})

const getItemClasses = (item) => [
  'skeleton-item',
  'bg-theme-surface',
  props.rounded ? 'rounded' : 'rounded-none',
  props.animation === 'pulse' ? 'animate-pulse' : '',
  props.animation === 'wave' ? 'skeleton-wave' : ''
]

const getItemStyle = (item) => ({
  width: typeof item.width === 'number' ? `${item.width}px` : item.width,
  height: typeof item.height === 'number' ? `${item.height}px` : item.height,
  borderRadius: item.borderRadius || (props.rounded ? '0.375rem' : '0')
})
</script>

<style scoped>
.skeleton-container {
  @apply w-full;
}

.skeleton-item {
  @apply block;
}

.skeleton-wave {
  background: linear-gradient(
    90deg,
    var(--color-surface) 25%,
    var(--color-background-secondary) 50%,
    var(--color-surface) 75%
  );
  background-size: 200% 100%;
  animation: skeleton-wave 1.5s infinite;
}


@keyframes skeleton-wave {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

/* Accessibility */
.skeleton-container[aria-label] {
  role: status;
  aria-live: polite;
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .skeleton-item {
    animation: none;
  }

  .skeleton-wave {
    animation: none;
    background: var(--color-surface);
  }

}
</style>




















