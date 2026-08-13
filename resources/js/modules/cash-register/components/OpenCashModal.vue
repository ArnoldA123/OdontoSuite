<template>
  <Modal
    :model-value="show"
    title="Abrir Sesion de Caja"
    size="md"
    @update:model-value="$emit('close')"
    @close="$emit('close')"
  >
    <form class="space-y-4 bg-canvas" @submit.prevent="handleSubmit">
      <!-- Estado: Apertura de Caja -->
      <UiStatusBadge variant="info" label="Apertura de Caja" size="md" />
      <!-- Sucursal -->
      <div v-if="!loadingBranches && branches.length > 0">
        <label class="block text-sm font-medium text-theme-primary mb-1">
          Sucursal
          <span class="text-red-500">*</span>
        </label>
        <UiSelect
          v-model="formData.branch_id"
          :options="branchOptions"
          placeholder="Seleccionar sucursal"
          size="md"
          searchable
          :error="errors.branch_id"
          :disabled="loading"
        />
      </div>

      <!-- Empty state: no hay sucursales cargadas -->
      <div v-else-if="!loadingBranches && branches.length === 0">
        <EmptyState
          :icon="BuildingOfficeIcon"
          title="No hay sucursales registradas"
          description="Para abrir caja necesitas al menos una sucursal activa en el sistema."
          :action-text="canManageBranches ? 'Ir a Configuracion de Sucursales' : ''"
          action-variant="primary"
          @action="goToBranchesSettings"
        />
      </div>

      <!-- Loading state -->
      <UiLoadingSpinner
        v-else
        size="md"
        variant="primary"
        text="Cargando sucursales..."
        aria-label="Cargando sucursales"
      />

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
          input-class="tabular-nums"
        />
      </div>

      <!-- Notas de Apertura -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">Notas de Apertura</label>
        <textarea
          v-model="formData.opening_notes"
          class="block w-full px-3 py-2 border border-hairline rounded-md shadow-sm sm:text-sm bg-theme-surface-elevated text-theme-primary placeholder-theme-secondary"
          :disabled="loading"
          rows="3"
          placeholder="Notas adicionales sobre la apertura de caja..."
          maxlength="500"
        />
        <p class="mt-1 text-sm text-theme-secondary">
          {{ formData.opening_notes?.length || 0 }}/500 caracteres
        </p>
      </div>

      <!-- Resumen -->
      <UiCard v-if="branches.length > 0" variant="flat" padding="md">
        <h3 class="text-sm font-semibold text-theme-primary mb-2">Resumen de Apertura</h3>
        <div class="space-y-1 text-sm">
          <div class="flex justify-between">
            <span class="text-theme-secondary">Sucursal:</span>
            <span class="font-medium text-theme-primary">
              {{ selectedBranch?.name || 'No seleccionada' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-theme-secondary">Monto inicial:</span>
            <span
              class="font-medium text-theme-primary tabular-nums"
              :aria-label="`${formData.opening_amount || 0} soles`"
            >
              {{ formatCurrency(formData.opening_amount) }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-theme-secondary">Usuario:</span>
            <span class="font-medium text-theme-primary">{{ currentUser?.name }}</span>
          </div>
        </div>
      </UiCard>
    </form>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <Button
type="button"
variant="secondary" :disabled="loading" @click="$emit('close')"
>
          Cancelar
        </Button>
        <Button
          v-if="branches.length > 0"
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
import EmptyState from '@/components/ui/EmptyState.vue'
import UiCard from '@/components/ui/Card.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiLoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import { useCashRegister } from '@/composables/useCashRegister'
import { useApi } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { usePermissions } from '@/composables/usePermissions'
import { formatCurrency } from '@/composables/useFormatters'
import { useRouter } from 'vue-router'
import { BanknotesIcon, BuildingOfficeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'success'])

// Configurar herencia de atributos
defineOptions({
  inheritAttrs: false
})

const { openSession } = useCashRegister()
const { get } = useApi()
const { user: currentUser } = useAuth()
const toast = useToast()
const { isAdministrador } = usePermissions()
const router = useRouter()

// Estado
const loading = ref(false)
const loadingBranches = ref(false)
const errors = ref({})
const branches = ref([])

// Formulario
const formData = ref({
  branch_id: '',
  opening_amount: 0,
  opening_notes: ''
})

// Validacion simple
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
  return formData.value.branch_id && formData.value.opening_amount >= 0 && !loading.value
})

const canManageBranches = computed(() => isAdministrador.value)

// Sprint 4: transformar branches al formato de UiSelect
const branchOptions = computed(() =>
  branches.value.map(b => ({
    value: b.id,
    label: `${b.name} (${b.code})`,
    description: b.city || ''
  }))
)

// Metodos
const loadBranches = async () => {
  loadingBranches.value = true
  try {
    // Sprint 1: usamos /branches/active (endpoint publico para todos los
    // autenticados) en lugar de /branches, que esta protegido por
    // role:administrador para uso admin en /settings/branches.
    const response = await get('/api/branches/active')
    branches.value = response.data || []
  } catch (error) {
    toast.error('No se pudieron cargar las sucursales. Verifica tu conexion e intenta de nuevo.')
    branches.value = []
  } finally {
    loadingBranches.value = false
  }
}

const goToBranchesSettings = () => {
  emit('close')
  router.push('/settings/branches')
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
    const result = await openSession({
      branch_id: formData.value.branch_id,
      opening_amount: formData.value.opening_amount,
      opening_notes: formData.value.opening_notes
    })

    // Notificacion de exito
    const selectedBranch = branches.value.find(b => b.id === formData.value.branch_id)
    toast.success(
      'Caja abierta exitosamente\n' +
        `Monto inicial: S/ ${formData.value.opening_amount}\n` +
        `Sucursal: ${selectedBranch?.name || 'N/A'}`,
      {
        duration: 5000,
        title: 'Caja Abierta'
      }
    )

    emit('success', result)
    emit('close')

    // Los eventos WebSocket se manejan automaticamente desde el backend
  } catch (error) {
    // Notificacion de error
    const errorMsg = error.response?.data?.message || 'Error al abrir la caja'
    const errorDetails = error.response?.data?.errors
    let details = ''
    if (errorDetails) {
      details = `\n${Object.values(errorDetails).flat().join('\n')}`
    }

    toast.error(errorMsg + details, {
      duration: 8000,
      title: 'Error al Abrir Caja'
    })

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else if (error.response?.data?.message) {
      errors.value = { general: error.response.data.message }
    } else {
      errors.value = { general: error.message || 'Error al abrir la sesion de caja' }
    }
  } finally {
    loading.value = false
  }
}

// formatCurrency imported from useFormatters (PR-pagos-01 canonicalization).

// Watch
watch(
  () => props.show,
  newValue => {
    if (newValue) {
      // Reset form when modal opens
      formData.value = {
        branch_id: '',
        opening_amount: 0,
        opening_notes: ''
      }
      errors.value = {}
    }
  }
)

// Lifecycle
onMounted(() => {
  loadBranches()
})
</script>
