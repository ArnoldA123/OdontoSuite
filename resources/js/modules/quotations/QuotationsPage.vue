<template>
  <AppLayout>
    <header
      data-quotations-hero
      class="mb-6 border border-hairline p-6 md:p-8"
      style="
        background: var(--color-canvas);
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
            Operación
          </p>
          <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>Presupuestos</h2>
          <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
            Emite, envía y da seguimiento a los presupuestos de tus pacientes
          </p>
        </div>
        <div class="flex flex-shrink-0 items-center gap-3">
          <UiButton variant="secondary" @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
          <UiButton variant="primary" data-hero-action="create" @click="openCreate">
            <template #icon-left>
              <PlusIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Nuevo presupuesto
          </UiButton>
        </div>
      </div>
    </header>

    <section
      aria-label="Resumen de presupuestos"
      class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
    >
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="total"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Total</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="total"
        >
          {{ kpiTotal }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Presupuestos registrados</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="pending"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Pendientes</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="pending"
        >
          {{ kpiPending }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="pending">
          {{ pendingCaption }}
        </p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="approved"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Aprobados</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="approved"
        >
          {{ kpiApproved }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Con decisión del paciente</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="approved-total"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Monto aprobado</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="approved-total"
        >
          {{ kpiApprovedTotal }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Total aceptado por pacientes</p>
      </UiCard>
    </section>

    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
        <div class="min-w-0 flex-1">
          <UiInput
            v-model="filters.q"
            data-quotations-search
            label="Buscar"
            placeholder="Número, paciente o documento..."
            class="w-full"
            @input="onSearchInput"
          >
            <template #prefix>
              <MagnifyingGlassIcon class="h-5 w-5 text-theme-secondary" aria-hidden="true" />
            </template>
          </UiInput>
        </div>
        <div class="w-full lg:w-56">
          <UiSelect
            v-model="filters.status"
            data-status-filter
            label="Estado"
            :options="STATUS_FILTER_OPTIONS"
            placeholder="Todos los estados"
            @change="applyFilters"
          />
        </div>
        <div class="grid grid-cols-2 gap-3 lg:w-[22rem]">
          <UiInput
            v-model="filters.date_from"
            type="date"
            label="Desde"
            class="min-w-0"
            data-date-from
          />
          <UiInput
            v-model="filters.date_to"
            type="date"
            label="Hasta"
            class="min-w-0"
            data-date-to
          />
        </div>
      </div>

      <div
        v-if="hasActiveFilters || patientFilter"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-hairline pt-4"
      >
        <p class="text-sm text-theme-secondary" data-filters-summary>
          <span v-if="patientFilter">Mostrando los presupuestos de un paciente.</span>
          <span v-else>Filtros aplicados sobre la lista.</span>
        </p>
        <UiButton variant="ghost" size="sm" data-filters-clear @click="clearFilters">
          Limpiar filtros
        </UiButton>
      </div>
    </UiCard>

    <section aria-label="Presupuestos" class="space-y-4">
      <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-3">
        <h2 class="text-2xl font-semibold text-label">Todos los presupuestos</h2>
        <p class="text-sm text-theme-secondary">Consulta, filtra y gestiona cada presupuesto</p>
      </div>

      <div data-quotations-list>
        <div v-if="loading" data-quotations-loading>
          <UiSkeleton variant="table" animation="wave" aria-label="Cargando presupuestos..." />
        </div>

        <div
          v-else-if="loadError"
          data-quotations-error
          role="alert"
          class="flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-panel)] border border-hairline bg-systemRed-50 p-5"
        >
          <div class="flex min-w-0 items-center gap-3">
            <ExclamationTriangleIcon
              class="h-6 w-6 flex-shrink-0 text-systemRed-600"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <p class="text-sm font-medium text-theme-primary">
                No pudimos cargar los presupuestos
              </p>
              <p class="text-sm text-theme-secondary">Revisa tu conexión y vuelve a intentarlo.</p>
            </div>
          </div>
          <UiButton variant="primary" size="sm" data-quotations-retry @click="refresh">
            Reintentar
          </UiButton>
        </div>

        <div v-else-if="quotations.length === 0" data-quotations-empty>
          <UiEmptyState
            :title="emptyStateTitle"
            :description="emptyStateDescription"
            :icon="DocumentTextIcon"
          >
            <template #action>
              <UiButton v-if="hasActiveFilters" variant="secondary" @click="clearFilters">
                Limpiar filtros
              </UiButton>
              <UiButton v-else @click="openCreate">Nuevo presupuesto</UiButton>
            </template>
          </UiEmptyState>
        </div>

        <UiDataTable
          v-else
          :data="quotations"
          :columns="columns"
          :searchable="false"
          :show-header="false"
          :pagination="false"
          data-quotations-table
        >
          <template #cell-number="{ row }">
            <div class="min-w-0">
              <p class="font-mono text-sm font-medium text-theme-primary" data-row-number>
                {{ row.quotation_number }}
              </p>
              <p class="text-xs text-theme-secondary" data-row-date>
                {{ formatQuotationDate(row.quotation_date) }}
              </p>
            </div>
          </template>

          <template #cell-patient="{ row }">
            <div class="min-w-0">
              <p class="text-sm text-theme-primary" data-row-patient>
                {{ row.patient?.first_name }} {{ row.patient?.last_name }}
              </p>
              <p v-if="row.patient?.document_number" class="font-mono text-xs text-theme-secondary">
                {{ row.patient.document_number }}
              </p>
            </div>
          </template>

          <template #cell-valid_until="{ row }">
            <div class="flex flex-col gap-1">
              <span class="text-sm text-theme-primary">
                {{ formatQuotationDate(row.valid_until) }}
              </span>
              <UiStatusBadge
                v-if="expiryBadge(row)"
                :variant="expiryBadge(row).variant"
                :label="expiryBadge(row).label"
                size="sm"
                data-row-expiry
              />
            </div>
          </template>

          <template #cell-total="{ row }">
            <span class="tabular-nums text-sm font-medium text-label" data-row-total>
              {{ formatCurrency(row.total_amount) }}
            </span>
          </template>

          <template #cell-status="{ row }">
            <UiStatusBadge
              :variant="getStatusMeta(row.status).variant"
              :label="getStatusMeta(row.status).label"
              size="sm"
              data-row-status
            />
          </template>

          <template #actions="{ row }">
            <UiButton
              variant="ghost"
              size="sm"
              class="text-systemBlue-600 hover:text-systemBlue-700"
              data-row-action="view"
              @click="openDetail(row)"
            >
              Ver
            </UiButton>
            <UiButton
              v-if="canEditQuotation(row, role)"
              variant="ghost"
              size="sm"
              class="text-systemBlue-600 hover:text-systemBlue-700"
              data-row-action="edit"
              @click="openEdit(row)"
            >
              Editar
            </UiButton>
            <UiButton
              v-if="canApproveQuotation(row, role)"
              variant="ghost"
              size="sm"
              class="text-systemGreen-700"
              data-row-action="approve"
              @click="openApproval(row)"
            >
              Aprobar
            </UiButton>
            <UiButton
              v-if="canRejectQuotation(row, role)"
              variant="ghost"
              size="sm"
              class="text-systemRed-700"
              data-row-action="reject"
              @click="openReject(row)"
            >
              Rechazar
            </UiButton>
            <UiButton
              v-if="canDownloadQuotation(role)"
              variant="ghost"
              size="sm"
              :disabled="downloading"
              data-row-action="pdf"
              @click="downloadQuotation(row)"
            >
              PDF
            </UiButton>
            <UiButton
              v-if="canDeleteQuotation(row, role)"
              variant="ghost"
              size="sm"
              class="text-systemRed-700"
              data-row-action="delete"
              @click="removeQuotation(row)"
            >
              Eliminar
            </UiButton>
          </template>
        </UiDataTable>
      </div>

      <div v-if="quotations.length > 0 && totalPages > 1" class="pagination-section">
        <Pagination
          :current-page="currentPage"
          :total-pages="totalPages"
          @page-change="handlePageChange"
        />
      </div>
    </section>
  </AppLayout>

  <QuotationModal
    v-if="showForm"
    :quotation="editingQuotation"
    :is-edit="isEdit"
    @close="closeForm"
    @saved="handleSaved"
  />

  <QuotationDetail
    v-if="showDetail && detailQuotation"
    :quotation="detailQuotation"
    @close="closeDetail"
    @edit="startEditFromDetail"
    @approve="startApprovalFromDetail"
    @reject="startRejectFromDetail"
    @download="downloadQuotation"
  />

  <QuotationApprovalModal
    v-if="showApproval && actionQuotation"
    :quotation="actionQuotation"
    @close="showApproval = false"
    @approved="handleApproved"
  />

  <QuotationRejectModal
    v-if="showReject && actionQuotation"
    :quotation="actionQuotation"
    @close="showReject = false"
    @rejected="handleRejected"
  />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import { formatCurrency } from '@/composables/useFormatters'
import { useApi } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { useConfirm } from '@/composables/useConfirm'
import { useEcho } from '@/composables/useEcho'
import { useQuotations } from '@/composables/useQuotations'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/components/layout/AppLayout.vue'
import Pagination from '@/components/ui/Pagination.vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiDataTable from '@/components/ui/DataTable.vue'
import UiEmptyState from '@/components/ui/EmptyState.vue'
import UiInput from '@/components/ui/Input.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiSkeleton from '@/components/ui/Skeleton.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import QuotationApprovalModal from './components/QuotationApprovalModal.vue'
import QuotationDetail from './components/QuotationDetail.vue'
import QuotationModal from './components/QuotationModal.vue'
import QuotationRejectModal from './components/QuotationRejectModal.vue'
import {
  STATUS_FILTER_OPTIONS,
  canApproveQuotation,
  canDeleteQuotation,
  canDownloadQuotation,
  canEditQuotation,
  canRejectQuotation,
  expiryBadge,
  formatQuotationDate,
  getStatusMeta
} from './quotationMeta'

const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { user } = useAuth()
const { channel, echo } = useEcho()
const {
  quotations,
  loading,
  downloading,
  totalPages,
  currentPage,
  getQuotations,
  deleteQuotation,
  downloadPDF
} = useQuotations()

const role = computed(() => user.value?.role)

const columns = [
  { key: 'number', label: 'Presupuesto' },
  { key: 'patient', label: 'Paciente' },
  { key: 'valid_until', label: 'Vigencia' },
  { key: 'total', label: 'Total' },
  { key: 'status', label: 'Estado' }
]

const filters = ref({
  q: '',
  status: '',
  date_from: '',
  date_to: ''
})

const patientFilter = ref(route.query.patient_id ? String(route.query.patient_id) : '')

const loadError = ref(false)
const summary = ref(null)
const summaryLoading = ref(false)
const summaryError = ref(false)

const showForm = ref(false)
const isEdit = ref(false)
const editingQuotation = ref(null)

const showDetail = ref(false)
const detailQuotation = ref(null)

const showApproval = ref(false)
const showReject = ref(false)
const actionQuotation = ref(null)

let searchTimer = null
let quotationsChannel = null

const hasActiveFilters = computed(
  () =>
    filters.value.q.trim() !== '' ||
    filters.value.status !== '' ||
    filters.value.date_from !== '' ||
    filters.value.date_to !== ''
)

const kpisUnavailable = computed(() => summaryLoading.value || summaryError.value)

const kpiTotal = computed(() =>
  kpisUnavailable.value || !summary.value ? 'N/D' : String(summary.value.total)
)

const kpiPending = computed(() =>
  kpisUnavailable.value || !summary.value ? 'N/D' : String(summary.value.pending)
)

const kpiApproved = computed(() =>
  kpisUnavailable.value || !summary.value
    ? 'N/D'
    : String(summary.value.by_status?.approved?.count ?? 0)
)

const kpiApprovedTotal = computed(() =>
  kpisUnavailable.value || !summary.value ? 'N/D' : formatCurrency(summary.value.approved_total)
)

const pendingCaption = computed(() => {
  if (kpisUnavailable.value || !summary.value) return 'Esperando decisión del paciente'
  if (summary.value.expiring_soon > 0) {
    return `${summary.value.expiring_soon} por vencer esta semana`
  }

  return 'Esperando decisión del paciente'
})

const emptyStateTitle = computed(() =>
  hasActiveFilters.value || patientFilter.value
    ? 'No se encontraron presupuestos'
    : 'Aún no hay presupuestos'
)

const emptyStateDescription = computed(() =>
  hasActiveFilters.value || patientFilter.value
    ? 'Prueba con otro término de búsqueda o quita los filtros aplicados.'
    : 'Crea el primer presupuesto para empezar a cotizar tratamientos.'
)

const activeFilters = () => {
  const params = { ...filters.value }
  if (patientFilter.value) params.patient_id = patientFilter.value

  return params
}

const loadQuotations = async (additional = {}) => {
  loadError.value = false
  try {
    await getQuotations({ ...activeFilters(), ...additional })
  } catch {
    loadError.value = true
  }
}

const loadSummary = async () => {
  summaryLoading.value = true
  summaryError.value = false
  try {
    const response = await api.get('/api/quotations/summary')
    summary.value = response?.data || null
  } catch {
    summary.value = null
    summaryError.value = true
  } finally {
    summaryLoading.value = false
  }
}

const refresh = () => Promise.all([loadQuotations(), loadSummary()])

const onSearchInput = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadQuotations(), 350)
}

const applyFilters = () => loadQuotations()

const clearFilters = () => {
  filters.value = { q: '', status: '', date_from: '', date_to: '' }
  if (patientFilter.value) {
    patientFilter.value = ''
    router.replace({ query: {} })
  }
  loadQuotations()
}

const handlePageChange = page => loadQuotations({ page })

const openCreate = () => {
  editingQuotation.value = null
  isEdit.value = false
  showForm.value = true
}

const openEdit = quotation => {
  editingQuotation.value = quotation
  isEdit.value = true
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  isEdit.value = false
  editingQuotation.value = null
}

const handleSaved = async persisted => {
  const wasEditing = isEdit.value
  const number = persisted?.quotation_number
  closeForm()
  await refresh()
  toast.success(
    number
      ? `Presupuesto ${number} ${wasEditing ? 'actualizado' : 'creado'}`
      : `Presupuesto ${wasEditing ? 'actualizado' : 'creado'}`
  )
}

const openDetail = async quotation => {
  detailQuotation.value = quotation
  showDetail.value = true

  try {
    const response = await api.get(`/api/quotations/${quotation.id}`)
    detailQuotation.value = response?.data || detailQuotation.value
  } catch {
    // The list payload already carries patient, items and totals.
  }
}

const closeDetail = () => {
  showDetail.value = false
  detailQuotation.value = null
}

const openApproval = quotation => {
  actionQuotation.value = quotation
  showApproval.value = true
}

const openReject = quotation => {
  actionQuotation.value = quotation
  showReject.value = true
}

const startEditFromDetail = quotation => {
  closeDetail()
  openEdit(quotation)
}

const startApprovalFromDetail = quotation => {
  closeDetail()
  openApproval(quotation)
}

const startRejectFromDetail = quotation => {
  closeDetail()
  openReject(quotation)
}

const handleApproved = async () => {
  showApproval.value = false
  actionQuotation.value = null
  await refresh()
  toast.success('Presupuesto aprobado')
}

const handleRejected = async () => {
  showReject.value = false
  actionQuotation.value = null
  await refresh()
  toast.success('Presupuesto rechazado')
}

const downloadQuotation = async quotation => {
  try {
    await downloadPDF(quotation.id, `presupuesto_${quotation.quotation_number}.pdf`)
  } catch {
    toast.error('No pudimos descargar el presupuesto')
  }
}

const removeQuotation = async quotation => {
  const ok = await confirm({
    title: 'Eliminar presupuesto',
    message: `¿Eliminar el presupuesto ${quotation.quotation_number}? Esta acción no se puede deshacer.`,
    confirmText: 'Eliminar',
    variant: 'danger'
  })
  if (!ok) return

  try {
    await deleteQuotation(quotation.id)
    await refresh()
    toast.success(`Presupuesto ${quotation.quotation_number} eliminado`)
  } catch {
    toast.error('No pudimos eliminar el presupuesto')
  }
}

const goBack = () => router.back()

onMounted(() => {
  refresh()

  try {
    quotationsChannel = channel('quotations')
    if (quotationsChannel) {
      quotationsChannel
        .listen('.quotation.created', async () => {
          await refresh()
          toast.success('Nuevo presupuesto creado')
        })
        .listen('.quotation.updated', async () => {
          await refresh()
        })
        .listen('.quotation.approved', async () => {
          await refresh()
          toast.success('Presupuesto aprobado', { duration: 6000 })
        })
    }
  } catch {
    // Without WebSocket the list still works through the initial fetch.
  }
})

onBeforeUnmount(() => {
  clearTimeout(searchTimer)

  if (echo) {
    try {
      echo.leave('quotations')
    } catch {
      // The channel was already closed; nothing to clean up.
    }
  }
})
</script>
