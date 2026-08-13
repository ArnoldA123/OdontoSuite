<template>
  <div class="treatment-plan-selector">
    <!-- Campo de búsqueda -->
    <div class="mb-4">
      <label class="block text-sm font-medium text-theme-primary mb-1">Plan de Tratamiento</label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <MagnifyingGlassIcon class="h-5 w-5 text-theme-secondary" />
        </div>
        <input
          v-model="searchQuery"
          type="text"
          class="block w-full pl-10 pr-3 py-2 border border-theme rounded-ios focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          placeholder="Buscar plan de tratamiento..."
          @input="handleSearch"
        />
      </div>
    </div>

    <!-- Lista de planes de tratamiento -->
    <div v-if="isLoading" class="flex justify-center py-4">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
    </div>

    <div v-else-if="filteredPlans.length === 0 && !isLoading" class="text-center py-4">
      <DocumentTextIcon class="w-8 h-8 text-theme-secondary mx-auto mb-2" />
      <p class="text-sm text-theme-secondary">
        {{ searchQuery ? 'No se encontraron planes' : 'No hay planes de tratamiento disponibles' }}
      </p>
    </div>

    <div v-else class="max-h-48 overflow-y-auto border border-theme rounded-ios">
      <div
        v-for="plan in filteredPlans"
        :key="plan.id"
        class="flex items-center p-3 border-b border-theme hover:bg-theme-surface cursor-pointer"
        :class="{ 'bg-primary-50 border-primary-200': selectedPlan?.id === plan.id }"
        @click="selectPlan(plan)"
      >
        <!-- Icono del plan -->
        <div
          class="flex-shrink-0 w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center"
        >
          <DocumentTextIcon class="w-4 h-4 text-primary-600" />
        </div>

        <!-- Información del plan -->
        <div class="ml-3 flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-theme-primary truncate">
              {{ plan.title || 'Plan de Tratamiento' }}
            </p>
            <span class="text-xs text-theme-secondary">{{ formatDate(plan.created_at) }}</span>
          </div>
          <div class="flex items-center space-x-3 text-xs text-theme-secondary">
            <span v-if="plan.patient" class="flex items-center">
              <UserIcon class="w-3 h-3 mr-1" />
              {{ plan.patient.first_name }} {{ plan.patient.last_name }}
            </span>
            <span class="flex items-center">
              <CurrencyDollarIcon class="w-3 h-3 mr-1" />
              S/ {{ formatCurrency(plan.total_amount || 0) }}
            </span>
          </div>
          <div v-if="plan.description" class="mt-1">
            <p class="text-xs text-theme-secondary line-clamp-1">
              {{ plan.description }}
            </p>
          </div>
        </div>

        <!-- Indicador de selección -->
        <div v-if="selectedPlan?.id === plan.id" class="flex-shrink-0 ml-2">
          <CheckIcon class="w-4 h-4 text-primary-600" />
        </div>
      </div>
    </div>

    <!-- Información del plan seleccionado -->
    <div v-if="selectedPlan" class="mt-3 p-3 bg-primary-50 border border-primary-200 rounded-ios">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-primary-900">
            Plan seleccionado: {{ selectedPlan.title || 'Plan de Tratamiento' }}
          </p>
          <p class="text-xs text-primary-700">
            Total: S/ {{ formatCurrency(selectedPlan.total_amount || 0) }}
          </p>
        </div>
        <button class="text-primary-600 hover:text-primary-800" @click="clearSelection">
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import {
  MagnifyingGlassIcon,
  DocumentTextIcon,
  UserIcon,
  CurrencyDollarIcon,
  CheckIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'

const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'plan-selected'])

const { get } = useApi()

const plans = ref([])
const selectedPlan = ref(props.modelValue)
const searchQuery = ref('')
const isLoading = ref(false)

const filteredPlans = computed(() => {
  if (!searchQuery.value) {
    return plans.value.slice(0, 10) // Mostrar solo los primeros 10 si no hay búsqueda
  }

  const query = searchQuery.value.toLowerCase()
  return plans.value.filter(plan => {
    const title = plan.title?.toLowerCase() || ''
    const description = plan.description?.toLowerCase() || ''
    const patientName = plan.patient ?
      `${plan.patient.first_name} ${plan.patient.last_name}`.toLowerCase() :
      ''

    return title.includes(query) || description.includes(query) || patientName.includes(query)
  })
})

const handleSearch = () => {
  // Debounce search if needed
  if (searchQuery.value.length >= 2) {
    searchPlans()
  }
}

const searchPlans = async () => {
  if (!searchQuery.value) {
    await loadPlans()
    return
  }

  isLoading.value = true
  try {
    const response = await get('/api/treatment-plans/search', {
      params: { q: searchQuery.value }
    })
    plans.value = response.data || []
  } catch (error) {
    // Fallback to local filtering
    await loadPlans()
  } finally {
    isLoading.value = false
  }
}

const loadPlans = async () => {
  isLoading.value = true
  try {
    const response = await get('/api/treatment-plans')
    plans.value = response.data || []
  } catch (error) {
    plans.value = []
  } finally {
    isLoading.value = false
  }
}

const selectPlan = plan => {
  if (props.disabled) return

  selectedPlan.value = plan
  emit('update:modelValue', plan)
  emit('plan-selected', plan)
}

const clearSelection = () => {
  selectedPlan.value = null
  emit('update:modelValue', null)
}

const formatDate = date => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('es-PE')
}

const formatCurrency = amount => {
  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

// Watch for external changes
watch(
  () => props.modelValue,
  newValue => {
    selectedPlan.value = newValue
  }
)

// Load plans on mount
onMounted(() => {
  loadPlans()
})
</script>

<style scoped>
.treatment-plan-selector {
  @apply space-y-3;
}
</style>
