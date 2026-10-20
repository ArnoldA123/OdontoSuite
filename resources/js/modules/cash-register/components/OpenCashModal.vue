<template>
  <UiModal
    :model-value="modelValue"
    title="Abrir sesión de caja"
    size="md"
    @update:model-value="handleModelValue"
  >
    <form
      id="open-cash-form"
      data-open-cash-form
      class="space-y-4 bg-canvas"
      @submit.prevent="handleSubmit"
    >
      <div
        v-if="generalError"
        role="alert"
        data-open-cash-error
        class="rounded-ios border border-systemRed-200 bg-systemRed-50 p-3 text-sm text-systemRed-700"
      >
        {{ generalError }}
      </div>

      <UiStatusBadge variant="info" label="Apertura de caja" size="md" />

      <div v-if="loadingBranches" data-branches-loading>
        <UiLoadingSpinner size="md" variant="primary" text="Cargando sucursales..." />
      </div>

      <div
        v-else-if="branchesError"
        role="alert"
        data-branch-error
        class="rounded-ios border border-systemRed-200 bg-systemRed-50 p-3"
      >
        <p class="text-sm text-systemRed-700">{{ branchesError }}</p>
        <UiButton
          variant="secondary"
          size="sm"
          class="mt-3"
          data-branch-retry
          @click="loadBranches"
        >
          <template #icon-left>
            <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
          </template>
          Reintentar
        </UiButton>
      </div>

      <EmptyState
        v-else-if="!branches.length"
        :icon="BuildingOfficeIcon"
        title="No hay sucursales registradas"
        description="Para abrir caja necesitas al menos una sucursal activa en el sistema."
        :action-text="canManageBranches ? 'Ir a configuración de sucursales' : ''"
        action-variant="primary"
        @action="goToBranchesSettings"
      />

      <div v-else data-modal-field="branch_id">
        <UiSelect
          v-model="form.branch_id"
          label="Sucursal"
          :options="branchOptions"
          placeholder="Seleccionar sucursal"
          :error="fieldError('branch_id')"
          :disabled="loading"
        />
        <p
          v-if="fieldError('branch_id')"
          data-field-error="branch_id"
          class="mt-1 text-sm text-systemRed-600"
        >
          {{ fieldError('branch_id') }}
        </p>
      </div>

      <div data-modal-amount>
        <CurrencyInput
          v-model="form.opening_amount"
          label="Monto de apertura"
          placeholder="0.00"
          required
          :disabled="loading"
          :error="fieldError('opening_amount')"
          help="Efectivo con el que inicia la sesión"
        />
      </div>

      <div>
        <label for="open-cash-notes" class="mb-1 block text-sm font-medium text-theme-primary">
          Notas de apertura
        </label>
        <UiTextarea
          id="open-cash-notes"
          :model-value="form.opening_notes"
          :rows="3"
          placeholder="Notas adicionales sobre la apertura de caja..."
          :disabled="loading"
          @update:model-value="updateOpeningNotes"
        />
        <p class="mt-1 text-sm text-theme-secondary">
          {{ form.opening_notes.length }}/500 caracteres
        </p>
        <p v-if="fieldError('opening_notes')" class="mt-1 text-sm text-systemRed-600">
          {{ fieldError('opening_notes') }}
        </p>
      </div>

      <UiCard v-if="branches.length" variant="flat" padding="md">
        <h3 class="mb-2 text-sm font-semibold text-theme-primary">Resumen de apertura</h3>
        <div class="space-y-1 text-sm">
          <div class="flex justify-between gap-3">
            <span class="text-theme-secondary">Sucursal</span>
            <span class="font-medium text-theme-primary">
              {{ selectedBranch?.name || 'No seleccionada' }}
            </span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-theme-secondary">Monto inicial</span>
            <span class="font-medium text-theme-primary tabular-nums">
              {{ formatCurrency(form.opening_amount) }}
            </span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-theme-secondary">Usuario</span>
            <span class="font-medium text-theme-primary">{{ currentUser?.name }}</span>
          </div>
        </div>
      </UiCard>
    </form>

    <template #footer>
      <UiButton variant="secondary" :disabled="loading" data-modal-cancel @click="close">
        Cancelar
      </UiButton>
      <UiButton
        variant="primary"
        :loading="loading"
        :disabled="!canSubmit"
        data-modal-submit
        @click="handleSubmit"
      >
        <template #icon-left>
          <BanknotesIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Abrir caja
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowPathIcon, BanknotesIcon, BuildingOfficeIcon } from '@heroicons/vue/24/outline'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiLoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import { useApi } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { useCashRegister } from '@/composables/useCashRegister'
import { usePermissions } from '@/composables/usePermissions'
import { formatCurrency } from '@/composables/useFormatters'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const { get } = useApi()
const { openSession } = useCashRegister()
const { user: currentUser } = useAuth()
const { isAdministrador } = usePermissions()
const router = useRouter()

const loading = ref(false)
const loadingBranches = ref(false)
const errors = ref({})
const branches = ref([])
const branchesError = ref('')

const form = ref({
  branch_id: null,
  opening_amount: 0,
  opening_notes: ''
})

const fieldError = field => {
  const value = errors.value[field]
  if (!value) return ''
  return Array.isArray(value) ? value[0] : String(value)
}

const generalError = computed(() => fieldError('general'))

const selectedBranch = computed(() =>
  branches.value.find(branch => branch.id === form.value.branch_id)
)

const branchOptions = computed(() =>
  branches.value.map(branch => ({
    value: branch.id,
    label: `${branch.name} (${branch.code})`,
    description: branch.city || ''
  }))
)

const canManageBranches = computed(() => isAdministrador.value)

const canSubmit = computed(
  () => Boolean(form.value.branch_id) && Number(form.value.opening_amount) >= 0 && !loading.value
)

const handleModelValue = value => emit('update:modelValue', value)

const updateOpeningNotes = value => {
  form.value.opening_notes = value.slice(0, 500)
}

const close = () => emit('update:modelValue', false)

const loadBranches = async () => {
  loadingBranches.value = true
  branchesError.value = ''

  try {
    const response = await get('/api/branches/active')
    branches.value = response.data || []
  } catch (error) {
    branches.value = []
    branchesError.value =
      error.response?.data?.message ||
      'No se pudieron cargar las sucursales. Verifica tu conexión e intenta de nuevo.'
  } finally {
    loadingBranches.value = false
  }
}

const goToBranchesSettings = () => {
  close()
  router.push('/settings/branches')
}

const resetForm = () => {
  form.value = {
    branch_id: null,
    opening_amount: 0,
    opening_notes: ''
  }
  errors.value = {}
}

const validate = () => {
  const next = {}

  if (!form.value.branch_id) {
    next.branch_id = 'Selecciona la sucursal'
  }

  if (Number(form.value.opening_amount) < 0) {
    next.opening_amount = 'El monto de apertura debe ser mayor o igual a 0'
  }

  errors.value = next
  return Object.keys(next).length === 0
}

const handleError = error => {
  const data = error.response?.data || {}
  const next = {}

  Object.entries(data.errors || {}).forEach(([key, value]) => {
    next[key] = Array.isArray(value) ? value[0] : String(value)
  })

  next.general =
    next.general || data.message || error.message || 'No se pudo abrir la sesión de caja'
  errors.value = next
}

const handleSubmit = async () => {
  if (loading.value) return

  errors.value = {}
  if (!validate()) return

  loading.value = true

  try {
    const result = await openSession({
      branch_id: form.value.branch_id,
      opening_amount: Number(form.value.opening_amount),
      opening_notes: form.value.opening_notes.trim() || null
    })

    emit('success', result)
    close()
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

watch(
  () => props.modelValue,
  value => {
    if (!value) return

    resetForm()

    if (!branches.value.length && !loadingBranches.value) {
      loadBranches()
    }
  }
)

onMounted(loadBranches)
</script>
