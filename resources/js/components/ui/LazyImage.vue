<template>
  <div :class="containerClasses" :style="containerStyle">
    <img
      v-if="loaded"
      :src="src"
      :alt="alt"
      :class="imageClasses"
      @load="handleLoad"
      @error="handleError"
    />
    <div
      v-else
      :class="placeholderClasses"
      :aria-label="loadingText"
    >
      <LoadingSpinner v-if="showSpinner" size="sm" />
      <div v-else class="w-full h-full bg-theme-surface rounded animate-pulse" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import LoadingSpinner from './LoadingSpinner.vue'

const props = defineProps({
  src: {
    type: String,
    required: true
  },
  alt: {
    type: String,
    default: ''
  },
  width: {
    type: [String, Number],
    default: 'auto'
  },
  height: {
    type: [String, Number],
    default: 'auto'
  },
  lazy: {
    type: Boolean,
    default: true
  },
  placeholder: {
    type: String,
    default: ''
  },
  showSpinner: {
    type: Boolean,
    default: false
  },
  loadingText: {
    type: String,
    default: 'Cargando imagen...'
  },
  errorText: {
    type: String,
    default: 'Error al cargar imagen'
  },
  rounded: {
    type: Boolean,
    default: false
  },
  objectFit: {
    type: String,
    default: 'cover',
    validator: (value) => ['cover', 'contain', 'fill', 'none', 'scale-down'].includes(value)
  }
})

const emit = defineEmits(['load', 'error'])

const loaded = ref(false)
const error = ref(false)
const observer = ref(null)

const containerClasses = computed(() => [
  'lazy-image-container',
  'relative overflow-hidden',
  props.rounded ? 'rounded-ios' : ''
])

const containerStyle = computed(() => ({
  width: typeof props.width === 'number' ? `${props.width}px` : props.width,
  height: typeof props.height === 'number' ? `${props.height}px` : props.height
}))

const imageClasses = computed(() => [
  'lazy-image',
  'w-full h-full',
  `object-${props.objectFit}`,
  'transition-opacity duration-300',
  loaded.value ? 'opacity-100' : 'opacity-0'
])

const placeholderClasses = computed(() => [
  'lazy-image-placeholder',
  'absolute inset-0 flex items-center justify-center',
  'bg-theme-surface',
  'text-theme-secondary',
  'text-sm'
])

const handleLoad = () => {
  loaded.value = true
  emit('load')
}

const handleError = () => {
  error.value = true
  emit('error')
}

const setupIntersectionObserver = () => {
  if (!props.lazy || !window.IntersectionObserver) {
    loaded.value = true
    return
  }

  observer.value = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          loaded.value = true
          observer.value?.disconnect()
        }
      })
    },
    {
      rootMargin: '50px'
    }
  )

  observer.value.observe(document.querySelector('.lazy-image-container'))
}

onMounted(() => {
  if (props.lazy) {
    setupIntersectionObserver()
  } else {
    loaded.value = true
  }
})

onUnmounted(() => {
  if (observer.value) {
    observer.value.disconnect()
  }
})
</script>

<style scoped>
.lazy-image-container {
  position: relative;
}

.lazy-image {
  display: block;
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .lazy-image {
    transition: none;
  }
}
</style>




















