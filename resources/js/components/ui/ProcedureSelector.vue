<template>
  <div class="procedure-selector">
    <div class="relative">
      <input
        v-model="searchQuery"
        type="text"
        :placeholder="placeholder"
        class="w-full px-4 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
        @focus="showDropdown = true"
        @blur="handleBlur"
        @keydown.enter.prevent="selectFirstResult"
        @keydown.arrow-down.prevent="highlightNext"
        @keydown.arrow-up.prevent="highlightPrevious"
        @keydown.escape="showDropdown = false"
      />

      <!-- Dropdown -->
      <div
        v-if="showDropdown && filteredProcedures.length > 0"
        class="absolute z-50 w-full mt-1 bg-theme-surface-elevated border border-theme rounded-lg shadow-lg max-h-60 overflow-y-auto"
      >
        <div
          v-for="(procedure, index) in filteredProcedures"
          :key="procedure.id"
          :class="[
            'px-4 py-3 cursor-pointer border-b border-theme last:border-b-0',
            highlightedIndex === index ? 'bg-primary-50 text-primary-700' : 'hover:bg-theme-surface'
          ]"
          @click="selectProcedure(procedure)"
          @mouseenter="highlightedIndex = index"
        >
          <div class="flex justify-between items-center">
            <div>
              <div class="font-medium text-theme-primary">{{ procedure.name }}</div>
              <div class="text-sm text-theme-secondary">{{ procedure.description }}</div>
            </div>
            <div class="text-right">
              <div class="font-medium text-primary-600">S/ {{ formatPrice(procedure.price) }}</div>
              <div class="text-xs text-theme-secondary">{{ procedure.category }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- No results -->
      <div
        v-if="showDropdown && filteredProcedures.length === 0 && searchQuery"
        class="absolute z-50 w-full mt-1 bg-theme-surface-elevated border border-theme rounded-lg shadow-lg"
      >
        <div class="px-4 py-3 text-theme-secondary text-center">
          No se encontraron procedimientos
        </div>
      </div>
    </div>

    <!-- Selected procedure -->
    <div v-if="selectedProcedure" class="mt-3 p-3 bg-primary-50 border border-primary-200 rounded-lg">
      <div class="flex justify-between items-center">
        <div>
          <div class="font-medium text-primary-900">{{ selectedProcedure.name }}</div>
          <div class="text-sm text-primary-700">{{ selectedProcedure.description }}</div>
        </div>
        <div class="flex items-center space-x-2">
          <div class="text-right">
            <div class="font-medium text-primary-600">S/ {{ formatPrice(selectedProcedure.price) }}</div>
            <div class="text-xs text-primary-500">{{ selectedProcedure.category }}</div>
          </div>
          <button
            @click="clearSelection"
            class="text-primary-500 hover:text-primary-700 transition-colors"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  },
  placeholder: {
    type: String,
    default: 'Buscar procedimiento...'
  },
  procedures: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'select'])

// Estado reactivo
const searchQuery = ref('')
const showDropdown = ref(false)
const highlightedIndex = ref(-1)
const selectedProcedure = ref(props.modelValue)

// Computed
const filteredProcedures = computed(() => {
  if (!searchQuery.value) return props.procedures.slice(0, 10)

  return props.procedures.filter(procedure =>
    procedure.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    procedure.description.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    procedure.category.toLowerCase().includes(searchQuery.value.toLowerCase())
  ).slice(0, 10)
})

// Métodos
const selectProcedure = (procedure) => {
  selectedProcedure.value = procedure
  searchQuery.value = procedure.name
  showDropdown.value = false
  highlightedIndex.value = -1
  emit('update:modelValue', procedure)
  emit('select', procedure)
}

const clearSelection = () => {
  selectedProcedure.value = null
  searchQuery.value = ''
  showDropdown.value = false
  highlightedIndex.value = -1
  emit('update:modelValue', null)
  emit('select', null)
}

const selectFirstResult = () => {
  if (filteredProcedures.value.length > 0) {
    selectProcedure(filteredProcedures.value[0])
  }
}

const highlightNext = () => {
  if (highlightedIndex.value < filteredProcedures.value.length - 1) {
    highlightedIndex.value++
  }
}

const highlightPrevious = () => {
  if (highlightedIndex.value > 0) {
    highlightedIndex.value--
  }
}

const handleBlur = () => {
  // Delay para permitir clicks en dropdown
  setTimeout(() => {
    showDropdown.value = false
    highlightedIndex.value = -1
  }, 150)
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price)
}

// Watchers
watch(() => props.modelValue, (newValue) => {
  selectedProcedure.value = newValue
  if (newValue) {
    searchQuery.value = newValue.name
  } else {
    searchQuery.value = ''
  }
})

watch(searchQuery, () => {
  highlightedIndex.value = -1
})

// Lifecycle
onMounted(() => {
  if (props.modelValue) {
    selectedProcedure.value = props.modelValue
    searchQuery.value = props.modelValue.name
  }
})
</script>
