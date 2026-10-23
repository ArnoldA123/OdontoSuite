<template>
  <div v-if="canManageBranchPrice" data-branch-tariff>
    <UiCard variant="glass">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
          <h2 class="text-2xl font-semibold text-label">Tarifario por sede</h2>
          <p class="mt-1 text-sm text-theme-secondary">
            Precio de este procedimiento en cada sede, con su ventana de vigencia.
          </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div class="w-full sm:w-64" data-tariff-branch>
            <UiSelect
              v-model="branchId"
              :options="branchOptions"
              label="Sede"
              placeholder="Seleccionar sede"
              :disabled="branchesLoading || branchOptions.length === 0"
            />
          </div>
          <UiButton data-tariff-add :disabled="!branchId" @click="openCreate">
            <template #icon-left>
              <PlusIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Nuevo precio
          </UiButton>
        </div>
      </div>
    </UiCard>

    <UiCard variant="glass" class="mt-4" data-tariff-content>
      <div v-if="loading" data-tariff-loading aria-busy="true">
        <UiSkeleton
          variant="table"
          animation="wave"
          aria-label="Cargando tarifario de la sede..."
        />
      </div>

      <div v-else-if="loadError" role="alert" data-tariff-error class="py-8 text-center">
        <ExclamationTriangleIcon class="mx-auto h-10 w-10 text-systemRed-600" aria-hidden="true" />
        <p class="mt-3 text-sm font-medium text-theme-primary">No pudimos cargar el tarifario</p>
        <p class="mt-1 text-sm text-theme-secondary">Revisa tu conexión e inténtalo de nuevo.</p>
        <div class="mt-4">
          <UiButton variant="secondary" data-tariff-retry @click="retry">Reintentar</UiButton>
        </div>
      </div>

      <UiEmptyState
        v-else-if="branches.length === 0"
        data-tariff-no-branches
        title="Sin sedes disponibles"
        description="No hay sedes activas para configurar un tarifario."
      />

      <UiEmptyState
        v-else-if="rows.length === 0"
        data-tariff-empty
        title="Sin precios para esta sede"
        description="Este procedimiento todavía usa el precio de lista en la sede seleccionada."
      >
        <template #action>
          <UiButton data-tariff-empty-add @click="openCreate">Nuevo precio</UiButton>
        </template>
      </UiEmptyState>

      <UiDataTable
        v-else
        :data="rows"
        :columns="columns"
        :searchable="false"
        :show-header="false"
        :pagination="false"
        data-tariff-table
      >
        <template #cell-price="{ row }">
          <p class="text-sm font-semibold text-label tabular-nums" data-tariff-price>
            {{ formatTariffPrice(row.price) }}
          </p>
        </template>
        <template #cell-list_price="{ row }">
          <p class="text-sm text-theme-secondary tabular-nums" data-tariff-list-price>
            {{ formatTariffPrice(row.list_price) }}
          </p>
        </template>
        <template #cell-validity="{ row }">
          <p class="text-sm text-theme-primary" data-tariff-validity>
            {{ formatValidityWindow(row.valid_from, row.valid_to) }}
          </p>
        </template>
        <template #cell-status="{ row }">
          <UiStatusBadge
            :variant="tariffStatusVariant(statusOf(row))"
            :label="tariffStatusLabel(statusOf(row))"
            size="sm"
            data-tariff-status
          />
        </template>
        <template #actions="{ row }">
          <UiButton
            variant="ghost"
            size="sm"
            aria-label="Editar precio de sede"
            data-tariff-edit
            @click="openEdit(row)"
          >
            <template #icon-left>
              <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
            </template>
          </UiButton>
          <UiButton
            variant="ghost"
            size="sm"
            aria-label="Retirar precio de sede"
            data-tariff-remove
            @click="askRemove(row)"
          >
            <template #icon-left>
              <TrashIcon class="h-4 w-4" aria-hidden="true" />
            </template>
          </UiButton>
        </template>
      </UiDataTable>
    </UiCard>

    <UiModal
      v-model="showForm"
      :title="editingRow ? 'Editar precio de sede' : 'Nuevo precio de sede'"
      size="lg"
    >
      <div class="space-y-4">
        <div data-field="price">
          <CurrencyInput
            v-model="form.price"
            label="Precio de sede *"
            :error="formErrors.price"
            required
          />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div data-field="valid_from">
            <UiInput v-model="form.valid_from" type="date" label="Vigente desde" />
          </div>
          <div data-field="valid_to">
            <UiInput v-model="form.valid_to" type="date" label="Vigente hasta" />
          </div>
        </div>
        <p class="text-xs text-theme-secondary">
          Déjalo vacío para una vigencia abierta. Sin fecha final el precio rige hasta el próximo
          cambio.
        </p>

        <div data-field="is_active">
          <UiSelect v-model="activeValue" :options="activeOptions" label="Estado" />
        </div>

        <p v-if="formError" role="alert" data-tariff-form-error class="text-sm text-systemRed-600">
          {{ formError }}
        </p>
      </div>

      <template #footer>
        <UiButton
          type="button"
          variant="secondary"
          :disabled="saving"
          data-tariff-cancel
          @click="closeForm"
        >
          Cancelar
        </UiButton>
        <UiButton type="button" :loading="saving" data-tariff-submit @click="submitForm">
          {{ editingRow ? 'Actualizar' : 'Guardar' }}
        </UiButton>
      </template>
    </UiModal>

    <UiConfirmDialog
      v-model="showDelete"
      title="Retirar precio de sede"
      :message="deleteMessage"
      confirm-text="Retirar"
      variant="danger"
      :loading="deleting"
      data-tariff-delete
      @confirm="confirmRemove"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
  ExclamationTriangleIcon,
  PencilSquareIcon,
  PlusIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import {
  dateOnly,
  formatTariffPrice,
  formatValidityWindow,
  tariffStatus,
  tariffStatusLabel,
  tariffStatusVariant
} from './branchTariff'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import CurrencyInput from '../../components/ui/CurrencyInput.vue'

const props = defineProps({
  procedureId: { type: [Number, String], required: true }
})

const { get, put, delete: del } = useApi()
const { can } = usePermissions()
const toast = useToast()

const canManageBranchPrice = computed(() => can.manageBranchPrice.value)

const branches = ref([])
const branchesLoading = ref(false)
const branchesError = ref(false)
const branchId = ref(null)

const rows = ref([])
const rowsLoading = ref(false)
const rowsError = ref(false)

const loading = computed(() => branchesLoading.value || rowsLoading.value)
const loadError = computed(() => branchesError.value || rowsError.value)

const branchOptions = computed(() =>
  branches.value.map(branch => ({ value: branch.id, label: branch.name }))
)

const columns = [
  { key: 'price', label: 'Precio de sede' },
  { key: 'list_price', label: 'Precio de lista' },
  { key: 'validity', label: 'Vigencia' },
  { key: 'status', label: 'Estado' }
]

const showForm = ref(false)
const editingRow = ref(null)
const form = ref(emptyForm())
const formErrors = ref({})
const formError = ref('')
const saving = ref(false)

const showDelete = ref(false)
const deletingRow = ref(null)
const deleting = ref(false)

const activeOptions = [
  { value: 'active', label: 'Activo' },
  { value: 'inactive', label: 'Inactivo' }
]

const activeValue = computed({
  get: () => (form.value.is_active ? 'active' : 'inactive'),
  set: value => {
    form.value.is_active = value === 'active'
  }
})

const deleteMessage = computed(() => {
  const price = deletingRow.value ? formatTariffPrice(deletingRow.value.price) : 'este precio'
  return `¿Retirar ${price} del tarifario de la sede? Esta acción no se puede deshacer.`
})

function emptyForm() {
  return {
    price: 0,
    valid_from: dateOnly(new Date()),
    valid_to: '',
    is_active: true
  }
}

const statusOf = row => tariffStatus(row)

const loadBranches = async () => {
  branchesLoading.value = true
  branchesError.value = false

  try {
    const response = await get('/api/branches/active')
    branches.value = (response.data || []).filter(branch => branch.is_active !== false)

    if (!branchId.value && branches.value.length > 0) {
      branchId.value = branches.value[0].id
    }
  } catch {
    branches.value = []
    branchesError.value = true
  } finally {
    branchesLoading.value = false
  }
}

const loadPrices = async () => {
  if (!branchId.value) return

  const requestedBranch = branchId.value
  rowsLoading.value = true
  rowsError.value = false

  try {
    const response = await get(`/api/branches/${requestedBranch}/procedure-prices`, {
      params: { procedure_id: props.procedureId }
    })
    if (requestedBranch === branchId.value) {
      rows.value = response.data || []
    }
  } catch {
    if (requestedBranch === branchId.value) {
      rows.value = []
      rowsError.value = true
    }
  } finally {
    if (requestedBranch === branchId.value) {
      rowsLoading.value = false
    }
  }
}

const retry = () => {
  if (branches.value.length === 0) {
    loadBranches()
    return
  }
  loadPrices()
}

const openCreate = () => {
  editingRow.value = null
  form.value = emptyForm()
  formErrors.value = {}
  formError.value = ''
  showForm.value = true
}

const openEdit = row => {
  editingRow.value = row
  form.value = {
    price: Number(row.price) || 0,
    valid_from: row.valid_from || '',
    valid_to: row.valid_to || '',
    is_active: row.is_active !== false
  }
  formErrors.value = {}
  formError.value = ''
  showForm.value = true
}

const closeForm = () => {
  if (saving.value) return
  showForm.value = false
}

const validate = () => {
  const errors = {}
  const price = Number(form.value.price)

  if (!Number.isFinite(price) || price < 0) {
    errors.price = 'Ingresa un importe válido.'
  }

  if (form.value.valid_from && form.value.valid_to && form.value.valid_to < form.value.valid_from) {
    errors.valid_to = 'La vigencia final no puede ser anterior al inicio.'
  }

  formErrors.value = errors
  return Object.keys(errors).length === 0
}

const submitForm = async () => {
  if (saving.value || !branchId.value) return

  formError.value = ''
  if (!validate()) return

  saving.value = true

  try {
    await put(`/api/branches/${branchId.value}/procedure-prices`, {
      items: [
        {
          procedure_catalog_id: props.procedureId,
          price: Number(form.value.price),
          valid_from: form.value.valid_from || null,
          valid_to: form.value.valid_to || null,
          is_active: Boolean(form.value.is_active)
        }
      ]
    })

    showForm.value = false
    toast.success('Precio de sede guardado')
    await loadPrices()
  } catch (error) {
    formError.value = error?.response?.data?.message || 'No se pudo guardar el precio de la sede.'
  } finally {
    saving.value = false
  }
}

const askRemove = row => {
  deletingRow.value = row
  showDelete.value = true
}

const confirmRemove = async () => {
  if (deleting.value || !deletingRow.value || !branchId.value) return

  deleting.value = true

  try {
    await del(`/api/branches/${branchId.value}/procedure-prices/${deletingRow.value.id}`)
    showDelete.value = false
    deletingRow.value = null
    toast.success('Precio de sede retirado')
    await loadPrices()
  } catch (error) {
    showDelete.value = false
    toast.error(error?.response?.data?.message || 'No se pudo retirar el precio de la sede.')
  } finally {
    deleting.value = false
  }
}

watch(branchId, value => {
  if (value) loadPrices()
})

onMounted(() => {
  if (!canManageBranchPrice.value) return
  loadBranches()
})
</script>
