<template>
  <div class="patient-selector">
    <!-- Campo de búsqueda -->
    <div class="mb-4">
      <label class="block text-sm font-medium text-theme-primary mb-1">Paciente</label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <MagnifyingGlassIcon class="h-5 w-5 text-theme-secondary" />
        </div>
        <input
          v-model="searchQuery"
          type="text"
          class="block w-full pl-10 pr-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          placeholder="Buscar paciente por nombre, DNI o teléfono..."
          @input="handleSearch"
        />
      </div>
    </div>

    <!-- Lista de pacientes -->
    <div v-if="isLoading" class="flex justify-center py-4">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <div v-else-if="filteredPatients.length === 0 && !isLoading" class="text-center py-4">
      <UserIcon class="w-8 h-8 text-theme-secondary mx-auto mb-2" />
      <p class="text-sm text-theme-secondary">
        {{ searchQuery ? 'No se encontraron pacientes' : 'No hay pacientes registrados' }}
      </p>
    </div>

    <div v-else class="max-h-48 overflow-y-auto border border-theme rounded-lg">
      <div
        v-for="patient in filteredPatients"
        :key="patient.id"
        @click="selectPatient(patient)"
        class="flex items-center p-3 border-b border-theme hover:bg-theme-surface cursor-pointer"
        :class="{ 'bg-primary-50 border-primary-200': selectedPatient?.id === patient.id }"
      >
        <!-- Avatar del paciente -->
        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center">
          <UserIcon class="w-4 h-4 text-primary-600" />
        </div>

        <!-- Información del paciente -->
        <div class="ml-3 flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-theme-primary truncate">
              {{ patient.first_name }} {{ patient.last_name }}
            </p>
            <span class="text-xs text-theme-secondary">{{ patient.age || 'N/A' }} años</span>
          </div>
          <div class="flex items-center space-x-3 text-xs text-theme-secondary">
            <span v-if="patient.dni" class="flex items-center">
              <IdentificationIcon class="w-3 h-3 mr-1" />
              {{ patient.dni }}
            </span>
            <span v-if="patient.phone" class="flex items-center">
              <PhoneIcon class="w-3 h-3 mr-1" />
              {{ patient.phone }}
            </span>
          </div>
        </div>

        <!-- Indicador de selección -->
        <div v-if="selectedPatient?.id === patient.id" class="flex-shrink-0 ml-2">
          <CheckIcon class="w-4 h-4 text-primary-600" />
        </div>
      </div>
    </div>

    <!-- Botón para crear nuevo paciente -->
    <div v-if="!searchQuery || filteredPatients.length === 0" class="mt-3">
      <button
        @click="createNewPatient"
        class="w-full flex items-center justify-center px-3 py-2 border border-dashed border-theme rounded-lg text-sm font-medium text-theme-primary hover:bg-theme-surface hover:border-primary-300 transition-all duration-200"
      >
        <PlusIcon class="w-4 h-4 mr-2" />
        Crear nuevo paciente
      </button>
    </div>

    <!-- Información del paciente seleccionado -->
    <div v-if="selectedPatient" class="mt-3 p-3 bg-primary-50 border border-primary-200 rounded-lg">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-primary-900">
            Paciente seleccionado: {{ selectedPatient.first_name }} {{ selectedPatient.last_name }}
          </p>
          <p class="text-xs text-primary-700">
            {{ selectedPatient.dni ? `DNI: ${selectedPatient.dni}` : 'Sin DNI' }}
          </p>
        </div>
        <button
          @click="clearSelection"
          class="text-primary-600 hover:text-primary-800"
        >
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
  UserIcon,
  IdentificationIcon,
  PhoneIcon,
  CheckIcon,
  PlusIcon,
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

const emit = defineEmits(['update:modelValue', 'patient-selected', 'create-patient'])

const { get } = useApi()

const patients = ref([])
const selectedPatient = ref(props.modelValue)
const searchQuery = ref('')
const isLoading = ref(false)

const filteredPatients = computed(() => {
  if (!searchQuery.value) {
    return patients.value.slice(0, 10) // Mostrar solo los primeros 10 si no hay búsqueda
  }

  const query = searchQuery.value.toLowerCase()
  return patients.value.filter(patient => {
    const fullName = `${patient.first_name} ${patient.last_name}`.toLowerCase()
    const dni = patient.dni?.toLowerCase() || ''
    const phone = patient.phone?.toLowerCase() || ''

    return fullName.includes(query) ||
           dni.includes(query) ||
           phone.includes(query)
  })
})

const handleSearch = () => {
  // Debounce search if needed
  if (searchQuery.value.length >= 2) {
    searchPatients()
  }
}

const searchPatients = async () => {
  if (!searchQuery.value) {
    await loadPatients()
    return
  }

  isLoading.value = true
  try {
    const response = await get('/api/patients/search', {
      params: { q: searchQuery.value }
    })
    patients.value = response.data || []
  } catch (error) {
    // Fallback to local filtering
    await loadPatients()
  } finally {
    isLoading.value = false
  }
}

const loadPatients = async () => {
  isLoading.value = true
  try {
    const response = await get('/api/patients')
    patients.value = response.data || []
  } catch (error) {
    patients.value = []
  } finally {
    isLoading.value = false
  }
}

const selectPatient = (patient) => {
  if (props.disabled) return

  selectedPatient.value = patient
  emit('update:modelValue', patient)
  emit('patient-selected', patient)
}

const clearSelection = () => {
  selectedPatient.value = null
  emit('update:modelValue', null)
}

const createNewPatient = () => {
  emit('create-patient')
}

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  selectedPatient.value = newValue
})

// Load patients on mount
onMounted(() => {
  loadPatients()
})
</script>

<style scoped>
.patient-selector {
  @apply space-y-3;
}
</style>
