<template>
  <Modal
    :model-value="show"
    title="Abrir Sesión de Caja"
    size="md"
    @update:model-value="$emit('close')"
    @close="$emit('close')"
  >
    <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Sucursal -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Sucursal <span class="text-red-500">*</span>
          </label>
          <select
            v-model="formData.branch_id"
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm bg-theme-surface-elevated text-theme-primary"
            :disabled="loading"
          >
            <option value="">Seleccionar sucursal</option>
            <option
              v-for="branch in branches"
              :key="branch.id"
              :value="branch.id"
            >
              {{ branch.name }}
            </option>
          </select>
        </div>

        <!-- Monto de Apertura -->
        <div>
          <CurrencyInput
            v-model="formData.opening_amount"
            label="Monto de Apertura"
            placeholder="0.00"
            :required="true"
            :min="0"
            :precision="2"
            :error="errors.opening_amount"
          />
        </div>

        <!-- Notas de Apertura -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Notas de Apertura
          </label>
          <textarea
            v-model="formData.opening_notes"
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm bg-theme-surface-elevated text-theme-primary placeholder-theme-secondary"
            :disabled="loading"
            rows="3"
            placeholder="Notas adicionales sobre la apertura de caja..."
            maxlength="500"
          ></textarea>
          <p class="mt-1 text-sm text-theme-secondary">
            {{ formData.opening_notes?.length || 0 }}/500 caracteres
          </p>
        </div>

        <!-- Resumen -->
        <div class="bg-primary-50 border border-primary-200 rounded-lg p-4">
          <h3 class="text-sm font-semibold text-primary-900 mb-2">Resumen de Apertura</h3>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-primary-700">Sucursal:</span>
              <span class="font-medium text-primary-900">
                {{ selectedBranch?.name || 'No seleccionada' }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-primary-700">Monto inicial:</span>
              <span class="font-medium text-primary-900">
                {{ formatCurrency(formData.opening_amount) }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-primary-700">Usuario:</span>
              <span class="font-medium text-primary-900">{{ currentUser?.name }}</span>
            </div>
          </div>
        </div>
    </form>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <Button
          type="button"
          variant="secondary"
          @click="$emit('close')"
          :disabled="loading"
        >
          Cancelar
        </Button>
        <Button
          type="submit"
          variant="primary"
          :loading="loading"
          :disabled="!canSubmit"
          @click="handleSubmit"
        >
          <BanknotesIcon class="w-4 h-4 mr-2" />
          Abrir Caja
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import { useCashRegister } from '@/composables/useCashRegister'
import { useApi, useAuth } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { BanknotesIcon } from '@heroicons/vue/24/outline'

// Configurar herencia de atributos
defineOptions({
  inheritAttrs: false
})

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'success'])

const { openSession } = useCashRegister()
const { get } = useApi()
const { user: currentUser } = useAuth()
const toast = useToast()

// Estado
const loading = ref(false)
const errors = ref({})
const branches = ref([])

// Formulario
const formData = ref({
  branch_id: '',
  opening_amount: 0,
  opening_notes: ''
})

// Validación simple
const validateForm = () => {
  if (!formData.value.branch_id) {
    errors.value.branch_id = 'La sucursal es requerida'
    return false
  }
  if (formData.value.opening_amount < 0) {
    errors.value.opening_amount = 'El monto de apertura debe ser mayor o igual a 0'
    return false
  }
  return true
}

// Computed
const selectedBranch = computed(() => {
  return branches.value.find(b => b.id === formData.value.branch_id)
})

const canSubmit = computed(() => {
  return formData.value.branch_id &&
         formData.value.opening_amount >= 0 &&
         !loading.value
})


// Métodos
const loadBranches = async () => {
  try {
    const response = await get('/api/branches')
    branches.value = response.data || []
  } catch (error) {
    console.error('Error al cargar sucursales:', error)
  }
}

const handleSubmit = async () => {
  loading.value = true
  errors.value = {}

  // Validar formulario
  if (!validateForm()) {
    loading.value = false
    return
  }

  try {
    console.log('Enviando datos:', {
      branch_id: formData.value.branch_id,
      opening_amount: formData.value.opening_amount,
      opening_notes: formData.value.opening_notes
    })

    const result = await openSession({
      branch_id: formData.value.branch_id,
      opening_amount: formData.value.opening_amount,
      opening_notes: formData.value.opening_notes
    })

    // Notificación de éxito
    const selectedBranch = branches.value.find(b => b.id === formData.value.branch_id)
    toast.success(
      `Caja abierta exitosamente\n` +
      `Monto inicial: S/ ${formData.value.opening_amount}\n` +
      `Sucursal: ${selectedBranch?.name || 'N/A'}`,
      {
        duration: 5000,
        title: '✓ Caja Abierta'
      }
    )

    emit('success', result)
    emit('close')

    // Los eventos WebSocket se manejan automáticamente desde el backend
  } catch (error) {
    console.error('Error completo:', error)
    console.error('Error response:', error.response?.data)

    // Notificación de error
    const errorMsg = error.response?.data?.message || 'Error al abrir la caja'
    const errorDetails = error.response?.data?.errors
    let details = ''
    if (errorDetails) {
      details = '\n' + Object.values(errorDetails).flat().join('\n')
    }

    toast.error(
      errorMsg + details,
      {
        duration: 8000,
        title: '✗ Error al Abrir Caja'
      }
    )

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else if (error.response?.data?.message) {
      errors.value = { general: error.response.data.message }
    } else {
      errors.value = { general: error.message || 'Error al abrir la sesión de caja' }
    }
  } finally {
    loading.value = false
  }
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount || 0)
}

// Watch
watch(() => props.show, (newValue) => {
  if (newValue) {
    // Reset form when modal opens
    formData.value = {
      branch_id: '',
      opening_amount: 0,
      opening_notes: ''
    }
    errors.value = {}
  }
})

// Lifecycle
onMounted(() => {
  loadBranches()
})
</script>

