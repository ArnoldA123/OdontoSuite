<template>
  <AppLayout>
    <div class="space-y-8" data-lab-cases>
      <!--
        Hero band in the dashboard's surface vocabulary: canvas fill, hairline,
        panel radius and the elevation-2 rung. The page owns its visible title
        (h2, not h1), so the topbar keeps its heading for the outline only.
      -->
      <header
        data-lab-cases-hero
        class="p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Laboratorio
            </p>
            <h2 class="mt-2 text-4xl font-semibold text-label" data-lab-cases-title>
              Laboratorio protésico
            </h2>
            <p class="mt-2 text-sm text-theme-secondary">
              Órdenes de trabajo del taller: qué se envió, a quién se prometió y en qué fase está.
            </p>
          </div>

          <UiButton variant="secondary" :disabled="loading" data-lab-cases-refresh @click="load">
            <template #icon-left>
              <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Actualizar
          </UiButton>
        </div>
      </header>

      <UiFilterBar :columns="4" data-lab-cases-filters>
        <div>
          <label for="lab-cases-status" class="mb-1 block text-sm font-medium text-theme-primary">
            Fase
          </label>
          <UiSelect
            id="lab-cases-status"
            :model-value="filters.status"
            :options="statusOptions"
            data-lab-cases-status
            @update:model-value="value => setFilter({ status: value })"
          />
        </div>

        <div>
          <label
            for="lab-cases-technician"
            class="mb-1 block text-sm font-medium text-theme-primary"
          >
            Técnico
          </label>
          <UiSelect
            id="lab-cases-technician"
            :model-value="filters.technician_id"
            :options="technicianOptions"
            data-lab-cases-technician
            @update:model-value="value => setFilter({ technician_id: value })"
          />
        </div>

        <div class="flex items-end">
          <label
            class="flex items-center gap-2 py-3 text-sm font-medium text-theme-primary"
            for="lab-cases-overdue"
          >
            <input
              id="lab-cases-overdue"
              type="checkbox"
              class="h-4 w-4 rounded border-theme"
              :checked="filters.overdue"
              data-lab-cases-overdue
              @change="onOverdueChange"
            />
            Solo vencidos
          </label>
        </div>

        <div class="flex items-end">
          <UiButton
            variant="secondary"
            class="w-full"
            :disabled="!hasFilters"
            data-lab-cases-clear
            @click="clearFilters"
          >
            <template #icon-left>
              <XMarkIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Limpiar filtros
          </UiButton>
        </div>
      </UiFilterBar>

      <!--
        Refresh failure. The composable keeps the last page mounted, so this
        block reports the stale state and offers the retry instead of clearing
        the desk mid-work.
      -->
      <div
        v-if="error"
        data-lab-cases-error
        role="alert"
        class="flex flex-wrap items-center gap-4 rounded-ios bg-systemRed-50 p-4"
        style="border: 1px solid var(--color-hairline)"
      >
        <ExclamationTriangleIcon
          class="h-8 w-8 flex-shrink-0 text-systemRed-600"
          aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-theme-primary">No pudimos cargar los casos</p>
          <p class="mt-1 text-sm text-theme-secondary" data-lab-cases-error-message>{{ error }}</p>
        </div>
        <UiButton variant="primary" size="sm" data-lab-cases-retry @click="load">
          Reintentar
        </UiButton>
      </div>

      <UiSkeleton
        v-if="showSkeleton"
        variant="list"
        animation="wave"
        aria-label="Cargando casos de laboratorio"
        data-lab-cases-loading
      />

      <UiEmptyState
        v-else-if="!error && cases.length === 0"
        data-lab-cases-empty
        title="Sin casos de laboratorio"
        description="Ningún caso coincide con los filtros aplicados."
      >
        <template #action>
          <UiButton variant="secondary" data-lab-cases-empty-clear @click="clearFilters">
            Limpiar filtros
          </UiButton>
        </template>
      </UiEmptyState>

      <template v-else-if="cases.length">
        <UiDataTable
          :data="cases"
          :columns="columns"
          :searchable="false"
          :pagination="false"
          :show-header="false"
          row-key="id"
          data-lab-cases-table
        >
          <template #cell-patient="{ row }">
            <div
              class="text-sm font-medium text-theme-primary"
              data-lab-case-patient
              :data-lab-case-row="row.id"
            >
              {{ patientFullName(row.patient) }}
            </div>
          </template>

          <template #cell-work="{ row }">
            <span class="text-sm text-theme-primary" data-lab-case-work>{{ row.work }}</span>
          </template>

          <template #cell-dental_piece="{ row }">
            <span class="text-sm text-theme-primary tabular-nums">
              {{ pieceLabel(row.dental_piece) }}
            </span>
          </template>

          <template #cell-promised_at="{ row }">
            <span class="text-sm text-theme-primary tabular-nums">
              {{ formatDate(row.promised_at) }}
            </span>
          </template>

          <template #cell-technician="{ row }">
            <span class="text-sm text-theme-primary">{{ row.technician?.name || '—' }}</span>
          </template>

          <template #cell-status="{ row }">
            <div class="flex items-center gap-2">
              <UiStatusBadge
                :variant="phaseBadgeVariant(row.status)"
                :label="phaseLabel(row.status)"
                size="sm"
                show-dot
                data-lab-case-status
              />
              <span
                v-if="isOverdue(row)"
                class="text-xs font-medium text-systemRed-600"
                data-lab-case-overdue
              >
                Vencido
              </span>
            </div>
          </template>

          <template #actions="{ row }">
            <UiButton size="sm" variant="secondary" data-lab-case-open @click.stop="openCase(row)">
              Ver
            </UiButton>
          </template>
        </UiDataTable>

        <UiPagination
          v-if="meta.last_page > 1"
          :current-page="meta.current_page"
          :total-pages="meta.last_page"
          :total="meta.total"
          :per-page="meta.per_page"
          @page-change="goToPage"
        />
      </template>
    </div>

    <LabCaseDetailModal
      v-model="detailOpen"
      :lab-case="selectedCase"
      :can-manage="canManage"
      @updated="onCaseUpdated"
    />
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ArrowPathIcon, ExclamationTriangleIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiFilterBar from '../../components/ui/FilterBar.vue'
import UiPagination from '../../components/ui/Pagination.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import LabCaseDetailModal from './LabCaseDetailModal.vue'
import {
  PHASES,
  formatDate,
  isOverdue,
  patientFullName,
  phaseBadgeVariant,
  phaseLabel,
  pieceLabel
} from './labCaseMeta'
import { useLabCases } from '../../composables/useLabCases'
import { usePermissions } from '../../composables/usePermissions'

const {
  cases,
  meta,
  filters,
  loading,
  error,
  load,
  setFilter,
  goToPage,
  loadOne,
  subscribe,
  unsubscribe
} = useLabCases()

const { can } = usePermissions()

const canManage = computed(() => Boolean(can.manageLabCase?.value))

const detailOpen = ref(false)
const selectedCase = ref(null)

const columns = [
  { key: 'patient', label: 'Paciente' },
  { key: 'work', label: 'Trabajo' },
  { key: 'dental_piece', label: 'Pieza' },
  { key: 'promised_at', label: 'Prometido' },
  { key: 'technician', label: 'Técnico' },
  { key: 'status', label: 'Fase' }
]

const statusOptions = [
  { label: 'Todas las fases', value: '' },
  ...PHASES.map(phase => ({ label: phaseLabel(phase), value: phase }))
]

const technicianOptions = [
  { label: 'Todos los técnicos', value: '' },
  { label: 'Mis casos', value: 'me' }
]

const hasFilters = computed(() =>
  Boolean(filters.value.status || filters.value.technician_id || filters.value.overdue)
)

const showSkeleton = computed(() => loading.value && cases.value.length === 0)

const onOverdueChange = event => setFilter({ overdue: event.target.checked })

const clearFilters = () => setFilter({ status: '', technician_id: '', overdue: false })

// The row already carries its relations, so the detail opens instantly with it
// and refreshes from the API behind the modal.
const openCase = async row => {
  selectedCase.value = row
  detailOpen.value = true

  try {
    const fresh = await loadOne(row.id)
    if (fresh) selectedCase.value = fresh
  } catch (thrown) {
    // The list row is a valid snapshot: keep it instead of closing the modal.
  }
}

const onCaseUpdated = async fresh => {
  if (fresh) selectedCase.value = fresh
  await load()
}

onMounted(async () => {
  await load()
  subscribe()
})

onUnmounted(() => {
  unsubscribe()
})
</script>
