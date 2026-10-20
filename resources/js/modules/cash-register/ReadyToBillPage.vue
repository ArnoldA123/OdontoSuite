<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { ArrowPathIcon, ExclamationTriangleIcon, UserIcon } from '@heroicons/vue/24/outline'
import AppLayout from '@/components/layout/AppLayout.vue'
import UiButton from '@/components/ui/Button.vue'
import UiDataTable from '@/components/ui/DataTable.vue'
import UiFilterBar from '@/components/ui/FilterBar.vue'
import UiInput from '@/components/ui/Input.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiPagination from '@/components/ui/Pagination.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { formatCurrency } from '@/composables/useFormatters'

const { get, post } = useApi()
const toast = useToast()

const columns = [
  { key: 'completed_at', label: 'Fecha' },
  { key: 'patient', label: 'Paciente' },
  { key: 'appointment_type', label: 'Tipo' },
  { key: 'final_amount', label: 'Monto' },
  { key: 'paid_amount', label: 'Pagado' },
  { key: 'balance', label: 'Saldo' },
  { key: 'has_quotation', label: 'Cotización' }
]

const DEFAULT_META = { current_page: 1, last_page: 1, per_page: 20, total: 0 }

const items = ref([])
const loading = ref(false)
const error = ref('')
const meta = ref({ ...DEFAULT_META })
const currentPage = ref(1)
const filters = ref({ from: '', to: '' })
const patientFilter = ref(null)
const patientDraft = ref(null)
const patientPickerOpen = ref(false)

const previewOpen = ref(false)
const previewAppointmentId = ref(null)
const previewData = ref(null)
const previewLoading = ref(false)
const previewError = ref('')
const generatingId = ref(null)

let filterTimer = null

const totalPages = computed(() => Number(meta.value?.last_page ?? 1))

const apiErrorMessage = (err, fallback) =>
  err?.response?.data?.error?.message || err?.response?.data?.message || fallback

const fetchRows = async () => {
  loading.value = true
  error.value = ''

  const params = { page: currentPage.value }
  if (filters.value.from) params.from = filters.value.from
  if (filters.value.to) params.to = filters.value.to
  if (patientFilter.value?.id) params.patient_id = patientFilter.value.id

  try {
    const response = await get('/api/appointments/ready-to-bill', { params })

    items.value = response?.data ?? []
    meta.value = response?.meta ?? { ...DEFAULT_META }
    currentPage.value = Number(meta.value.current_page || 1)
  } catch (err) {
    items.value = []
    meta.value = { ...DEFAULT_META }
    currentPage.value = 1
    error.value = apiErrorMessage(err, 'No se pudieron cargar las citas por cobrar.')
  } finally {
    loading.value = false
  }
}

const handlePageChange = page => {
  currentPage.value = page
  fetchRows()
}

const clearFilters = () => {
  filters.value = { from: '', to: '' }
  patientFilter.value = null
  currentPage.value = 1
  fetchRows()
}

const patientFilterLabel = computed(() => {
  const patient = patientFilter.value
  if (!patient) return 'Todos los pacientes'

  return (
    [patient.first_name, patient.last_name].filter(Boolean).join(' ') ||
    patient.name ||
    'Paciente seleccionado'
  )
})

const openPatientPicker = () => {
  patientDraft.value = patientFilter.value
  patientPickerOpen.value = true
}

const applyPatientFilter = () => {
  patientFilter.value = patientDraft.value
  patientPickerOpen.value = false
  currentPage.value = 1
  fetchRows()
}

const clearPatientFilter = () => {
  patientFilter.value = null
  currentPage.value = 1
  fetchRows()
}

const loadPreview = async () => {
  if (!previewAppointmentId.value) return

  previewLoading.value = true
  previewError.value = ''
  previewData.value = null

  try {
    const response = await get(`/api/appointments/${previewAppointmentId.value}/payment-preview`)
    previewData.value = response?.data ?? null
  } catch (err) {
    previewError.value = apiErrorMessage(err, 'No se pudo cargar el desglose de pago.')
  } finally {
    previewLoading.value = false
  }
}

const openPreview = row => {
  previewAppointmentId.value = row.id
  previewOpen.value = true
  loadPreview()
}

const closePreview = () => {
  previewOpen.value = false
}

const resetPreview = () => {
  previewAppointmentId.value = null
  previewData.value = null
  previewError.value = ''
  previewLoading.value = false
}

const generateQuotation = async row => {
  if (generatingId.value !== null) return

  generatingId.value = row.id

  try {
    const response = await post(`/api/appointments/${row.id}/generate-quotation`, {})
    const number = response?.data?.quotation_number
    toast.success(number ? `Cotización ${number} generada` : 'Cotización generada')
    await fetchRows()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'No se pudo generar la cotización.'))
  } finally {
    generatingId.value = null
  }
}

const patientName = row =>
  [row.patient?.first_name, row.patient?.last_name].filter(Boolean).join(' ') || '—'

const formatDate = value => {
  if (!value) return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'

  return date.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
}

watch(
  filters,
  () => {
    if (filterTimer) clearTimeout(filterTimer)

    filterTimer = setTimeout(() => {
      filterTimer = null
      currentPage.value = 1
      fetchRows()
    }, 300)
  },
  { deep: true }
)

watch(previewOpen, open => {
  if (!open) resetPreview()
})

onMounted(fetchRows)

onBeforeUnmount(() => {
  if (filterTimer) clearTimeout(filterTimer)
})
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <header
        data-rtb-hero
        class="p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p
              class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
              data-hero-eyebrow
            >
              Cobros
            </p>
            <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>
              Listos para facturar
            </h2>
            <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
              Citas completadas con saldo pendiente y su estado de cotización
            </p>
          </div>
          <div class="flex flex-shrink-0 items-center gap-3">
            <UiButton
              variant="primary"
              data-hero-action="refresh"
              :loading="loading"
              @click="fetchRows"
            >
              <template #icon-left>
                <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
              </template>
              Actualizar
            </UiButton>
          </div>
        </div>
      </header>

      <UiFilterBar :columns="4" data-rtb-filters>
        <UiInput v-model="filters.from" type="date" label="Desde" data-filter="from" />
        <UiInput v-model="filters.to" type="date" label="Hasta" data-filter="to" />
        <div class="flex items-end gap-2">
          <UiButton
            variant="secondary"
            class="w-full"
            data-filter-patient
            @click="openPatientPicker"
          >
            <template #icon-left>
              <UserIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            {{ patientFilterLabel }}
          </UiButton>
          <UiButton
            v-if="patientFilter"
            variant="ghost"
            size="sm"
            data-filter-patient-clear
            @click="clearPatientFilter"
          >
            Quitar
          </UiButton>
        </div>
        <div class="flex items-end">
          <UiButton variant="secondary" class="w-full" data-filter-clear @click="clearFilters">
            Limpiar filtros
          </UiButton>
        </div>
      </UiFilterBar>

      <div
        v-if="error"
        role="alert"
        class="rounded-ios border border-hairline bg-theme-surface-elevated p-6"
        data-rtb-error
      >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
          <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-systemRed-600" aria-hidden="true" />
          <div class="flex-1">
            <p class="text-sm font-medium text-label">No se pudieron cargar las citas por cobrar</p>
            <p class="mt-1 text-sm text-theme-secondary" data-rtb-error-message>{{ error }}</p>
          </div>
          <UiButton variant="secondary" size="sm" data-rtb-retry @click="fetchRows">
            <template #icon-left>
              <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Reintentar
          </UiButton>
        </div>
      </div>

      <div v-else>
        <UiDataTable
          :data="items"
          :columns="columns"
          :loading="loading"
          :pagination="false"
          :searchable="false"
          :show-header="false"
          row-key="id"
          empty-title="No hay citas por cobrar"
          empty-description="Las citas completadas con saldo pendiente aparecerán aquí."
          data-rtb-table
        >
          <template #cell-completed_at="{ row }">
            <span class="tabular-nums text-theme-primary">{{ formatDate(row.completed_at) }}</span>
          </template>

          <template #cell-patient="{ row }">
            <p class="text-sm font-medium text-label">{{ patientName(row) }}</p>
            <p class="text-xs text-theme-secondary">{{ row.patient?.document_number || '—' }}</p>
          </template>

          <template #cell-final_amount="{ row }">
            <span class="text-sm font-medium tabular-nums text-label">
              {{ formatCurrency(row.final_amount) }}
            </span>
          </template>

          <template #cell-paid_amount="{ row }">
            <span class="text-sm tabular-nums text-systemGreen-600">
              {{ formatCurrency(row.paid_amount) }}
            </span>
          </template>

          <template #cell-balance="{ row }">
            <span class="text-sm font-medium tabular-nums text-systemRed-600">
              {{ formatCurrency(row.balance) }}
            </span>
          </template>

          <template #cell-has_quotation="{ row }">
            <UiStatusBadge
              :variant="row.has_quotation ? 'success' : 'neutral'"
              :label="row.has_quotation ? 'Generada' : 'Pendiente'"
              size="sm"
            />
          </template>

          <template #actions="{ row }">
            <UiButton
              variant="secondary"
              size="xs"
              :data-preview-action="row.id"
              @click="openPreview(row)"
            >
              Desglose
            </UiButton>
            <UiButton
              v-if="!row.has_quotation"
              variant="primary"
              size="xs"
              :loading="generatingId === row.id"
              :disabled="generatingId !== null"
              :data-quotation-action="row.id"
              @click="generateQuotation(row)"
            >
              Generar cotización
            </UiButton>
          </template>
        </UiDataTable>

        <div v-if="totalPages > 1" class="mt-4" data-rtb-pagination>
          <UiPagination
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="Number(meta.total || 0)"
            :per-page="Number(meta.per_page || 20)"
            @page-change="handlePageChange"
          />
        </div>
      </div>
    </div>
  </AppLayout>

  <UiModal v-model="previewOpen" title="Desglose de pago" size="lg" @close="closePreview">
    <LoadingSpinner v-if="previewLoading" size="md" text="Cargando desglose..." />

    <div
      v-else-if="previewError"
      role="alert"
      class="rounded-ios border border-hairline bg-theme-surface p-6"
      data-preview-error
    >
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
        <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-systemRed-600" aria-hidden="true" />
        <div class="flex-1">
          <p class="text-sm font-medium text-label">No se pudo cargar el desglose</p>
          <p class="mt-1 text-sm text-theme-secondary">{{ previewError }}</p>
        </div>
        <UiButton variant="secondary" size="sm" data-preview-retry @click="loadPreview">
          <template #icon-left>
            <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
          </template>
          Reintentar
        </UiButton>
      </div>
    </div>

    <div v-else-if="previewData" class="space-y-4">
      <div class="rounded-ios border border-hairline bg-theme-surface p-3">
        <div class="text-xs text-theme-secondary">Paciente</div>
        <div class="font-medium">{{ patientName(previewData.appointment) }}</div>
        <div class="mt-1 text-xs text-theme-secondary">
          Cita #{{ previewData.appointment?.id }} ·
          {{ formatDate(previewData.appointment?.completed_at) }}
        </div>
      </div>

      <div>
        <h3 class="mb-2 text-sm font-semibold text-theme-primary">Items del plan</h3>
        <div v-if="previewData.items?.length === 0" class="text-sm text-theme-secondary">
          Sin items (monto fijo por tipo de cita).
        </div>
        <ul v-else class="space-y-1 text-sm">
          <li
            v-for="item in previewData.items"
            :key="item.id"
            class="flex justify-between rounded-ios border border-hairline bg-theme-surface p-2"
          >
            <span>
              {{ item.procedure_name }}
              <span class="text-theme-secondary">× {{ item.quantity }}</span>
            </span>
            <span class="font-medium tabular-nums">{{ formatCurrency(item.total_cost) }}</span>
          </li>
        </ul>
      </div>

      <div class="space-y-1 border-t border-hairline pt-3 text-sm">
        <div class="flex justify-between">
          <span>Subtotal</span>
          <span class="tabular-nums">{{ formatCurrency(previewData.subtotal) }}</span>
        </div>
        <div class="flex justify-between font-semibold">
          <span>Total a cobrar</span>
          <span class="tabular-nums">{{ formatCurrency(previewData.final_amount) }}</span>
        </div>
        <div class="flex justify-between text-systemGreen-600">
          <span>Pagado</span>
          <span class="tabular-nums">{{ formatCurrency(previewData.paid_amount) }}</span>
        </div>
        <div class="flex justify-between font-semibold text-systemRed-600">
          <span>Saldo</span>
          <span class="tabular-nums">{{ formatCurrency(previewData.balance) }}</span>
        </div>
      </div>

      <div v-if="previewData.payments?.length" class="border-t border-hairline pt-3">
        <h3 class="mb-2 text-sm font-semibold text-theme-primary">Pagos aplicados</h3>
        <ul class="space-y-1 text-sm">
          <li
            v-for="payment in previewData.payments"
            :key="payment.id"
            class="flex justify-between text-theme-secondary"
          >
            <span>{{ payment.transaction_number }} · {{ payment.payment_method || '—' }}</span>
            <span class="tabular-nums">{{ formatCurrency(payment.amount) }}</span>
          </li>
        </ul>
      </div>

      <div v-if="previewData.quotations?.length" class="border-t border-hairline pt-3">
        <h3 class="mb-2 text-sm font-semibold text-theme-primary">Cotizaciones</h3>
        <ul class="space-y-1 text-sm">
          <li
            v-for="quotation in previewData.quotations"
            :key="quotation.id"
            class="flex justify-between"
          >
            <span>
              {{ quotation.quotation_number }}
              <span class="text-xs text-theme-secondary">({{ quotation.status }})</span>
            </span>
            <span class="tabular-nums">{{ formatCurrency(quotation.total_amount) }}</span>
          </li>
        </ul>
      </div>
    </div>
  </UiModal>

  <UiModal v-model="patientPickerOpen" title="Filtrar por paciente" size="md">
    <PatientSelector v-model="patientDraft" />
    <template #footer>
      <UiButton variant="secondary" data-patient-cancel @click="patientPickerOpen = false">
        Cancelar
      </UiButton>
      <UiButton variant="primary" data-patient-apply @click="applyPatientFilter">
        Aplicar filtro
      </UiButton>
    </template>
  </UiModal>
</template>
