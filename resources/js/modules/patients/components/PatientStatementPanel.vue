<template>
  <div data-patient-statement class="space-y-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div class="flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-44" data-statement-date-from>
          <UiInput v-model="dateFrom" type="date" label="Desde" />
        </div>
        <div class="w-full sm:w-44" data-statement-date-to>
          <UiInput v-model="dateTo" type="date" label="Hasta" />
        </div>
        <UiButton variant="secondary" data-statement-apply :disabled="loading" @click="load">
          Aplicar
        </UiButton>
      </div>
      <UiButton
        v-if="canManageCharge"
        variant="secondary"
        data-statement-charge
        @click="emit('charge')"
      >
        <template #icon-left>
          <PlusIcon class="w-4 h-4" aria-hidden="true" />
        </template>
        Nuevo cargo
      </UiButton>
    </div>

    <div v-if="loading" data-statement-loading aria-busy="true">
      <LoadingSpinner size="lg" text="Cargando estado de cuenta..." class="py-8" />
    </div>

    <UiSectionEmpty
      v-else-if="error"
      data-statement-error
      data-state="error-cuenta"
      title="No pudimos cargar el estado de cuenta"
      description="Revisa tu conexión e inténtalo de nuevo."
    >
      <UiButton variant="secondary" data-statement-retry @click="load">Reintentar</UiButton>
    </UiSectionEmpty>

    <template v-else>
      <section
        aria-label="Saldos del período"
        data-statement-kpis
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
      >
        <div
          v-for="card in kpis"
          :key="card.key"
          :data-statement-kpi="card.key"
          class="rounded-ios p-4"
          style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">{{ card.label }}</p>
          <p
            class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
            :data-statement-kpi-value="card.key"
          >
            {{ formatCurrency(card.amount) }}
          </p>
        </div>
      </section>

      <section
        data-statement-summary
        class="rounded-ios p-4"
        style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
      >
        <h3 class="text-sm font-semibold text-theme-primary">Resumen del período</h3>
        <dl class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="row in summaryRows"
            :key="row.key"
            class="flex items-baseline justify-between gap-3"
            :data-statement-summary-row="row.key"
          >
            <dt class="text-xs text-theme-secondary">{{ row.label }}</dt>
            <dd
              class="text-sm font-medium text-theme-primary tabular-nums"
              :data-statement-summary-value="row.key"
            >
              {{ formatCurrency(row.amount) }}
            </dd>
          </div>
        </dl>
      </section>

      <section data-statement-entries class="space-y-3">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
          <h3 class="text-sm font-semibold text-theme-primary">Movimientos</h3>
          <p class="text-xs text-theme-secondary">
            Cargos, pagos, devoluciones y ajustes del período
          </p>
        </div>

        <UiDataTable
          v-if="entryRows.length > 0"
          :data="entryRows"
          :columns="columns"
          :searchable="false"
          :pagination="false"
          :show-header="false"
          row-key="row_key"
          data-statement-table
        >
          <template #cell-date="{ row }">
            <span class="text-sm text-theme-primary tabular-nums" data-statement-entry="date">
              {{ formatEntryDate(row.date) }}
            </span>
          </template>

          <template #cell-reference="{ row }">
            <span class="text-sm text-theme-primary tabular-nums" data-statement-entry="reference">
              {{ row.reference || '—' }}
            </span>
          </template>

          <template #cell-description="{ row }">
            <span class="text-sm text-theme-primary" data-statement-entry="description">
              {{ row.description || '—' }}
            </span>
          </template>

          <template #cell-kind="{ row }">
            <UiStatusBadge
              :variant="statementKindVariant(row.kind)"
              :label="statementKindLabel(row.kind)"
              size="sm"
              data-statement-entry="kind"
            />
          </template>

          <template #cell-amount="{ row }">
            <span class="text-sm text-theme-primary tabular-nums" data-statement-entry="amount">
              {{ formatCurrency(row.amount) }}
            </span>
          </template>

          <template #cell-effect="{ row }">
            <span
              class="text-sm tabular-nums"
              :class="Number(row.effect) < 0 ? 'text-systemGreen-700' : 'text-theme-primary'"
              data-statement-entry="effect"
            >
              {{ formatCurrency(row.effect) }}
            </span>
          </template>

          <template #cell-balance="{ row }">
            <span
              class="text-sm font-medium text-theme-primary tabular-nums"
              data-statement-entry="balance"
            >
              {{ formatCurrency(row.balance) }}
            </span>
          </template>
        </UiDataTable>

        <UiSectionEmpty
          v-else
          data-statement-empty
          data-state="empty-cuenta"
          title="Sin movimientos en el período"
          description="No hay cargos, pagos ni ajustes del libro para estas fechas."
        />
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import UiButton from '../../../components/ui/Button.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiDataTable from '../../../components/ui/DataTable.vue'
import UiSectionEmpty from '../../../components/ui/UiSectionEmpty.vue'
import UiStatusBadge from '../../../components/ui/StatusBadge.vue'
import LoadingSpinner from '../../../components/ui/LoadingSpinner.vue'
import { useApi } from '../../../composables/useApi'
import { formatCurrency, formatDate } from '../../../composables/useFormatters'
import {
  statementKindLabel,
  statementKindVariant,
  statementSummaryRows
} from '../patientStatementMeta'

const props = defineProps({
  patient: { type: Object, required: true },
  canManageCharge: { type: Boolean, default: false }
})

const emit = defineEmits(['charge'])

const { get } = useApi()

const pad = value => String(value).padStart(2, '0')

// Dates, not instants: the API reads both ends inclusive, so the default
// period is the current month through today.
const dateStamp = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

const todayStamp = () => dateStamp(new Date())
const monthStartStamp = () => {
  const today = new Date()
  return dateStamp(new Date(today.getFullYear(), today.getMonth(), 1))
}

const dateFrom = ref(monthStartStamp())
const dateTo = ref(todayStamp())

const statement = ref(null)
const loading = ref(false)
const error = ref(false)

const summary = computed(() => statement.value?.summary ?? {})

const kpis = computed(() => [
  {
    key: 'opening',
    label: 'Saldo de apertura',
    amount: Number(statement.value?.opening_balance ?? 0) || 0
  },
  {
    key: 'charges',
    label: 'Cargos',
    amount: Number(summary.value.charges ?? 0) || 0
  },
  {
    key: 'payments',
    label: 'Pagos',
    amount: Number(summary.value.payments ?? 0) || 0
  },
  {
    key: 'closing',
    label: 'Saldo de cierre',
    amount: Number(statement.value?.closing_balance ?? 0) || 0
  }
])

const summaryRows = computed(() => statementSummaryRows(summary.value))

// The entry has no id of its own: two lines of the same payment (the payment
// and its discount) share the transaction, and the reference disambiguates.
const entryRows = computed(() => {
  const entries = Array.isArray(statement.value?.entries) ? statement.value.entries : []
  return entries.map((entry, index) => ({
    ...entry,
    row_key: `${index}-${entry.reference ?? entry.kind ?? 'entry'}`
  }))
})

const columns = [
  { key: 'date', label: 'Fecha' },
  { key: 'reference', label: 'Referencia' },
  { key: 'description', label: 'Descripción' },
  { key: 'kind', label: 'Tipo' },
  { key: 'amount', label: 'Importe' },
  { key: 'effect', label: 'Efecto' },
  { key: 'balance', label: 'Saldo' }
]

const formatEntryDate = value => formatDate(value) || '—'

const load = async () => {
  if (!props.patient?.id) return

  loading.value = true
  error.value = false

  try {
    const response = await get(
      `/api/patients/${props.patient.id}/statement?date_from=${dateFrom.value}&date_to=${dateTo.value}`
    )
    statement.value = response?.data ?? null
  } catch (thrown) {
    error.value = true
    statement.value = null
  } finally {
    loading.value = false
  }
}

const reload = () => load()

watch(
  () => props.patient?.id,
  (id, previous) => {
    if (id && id !== previous) load()
  }
)

onMounted(load)

defineExpose({ reload })
</script>
