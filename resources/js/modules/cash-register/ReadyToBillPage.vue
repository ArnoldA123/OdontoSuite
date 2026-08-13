<script setup>
import { ref, onMounted, watch } from 'vue'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { formatCurrency } from '../../composables/useFormatters'
import UiModal from '@/components/ui/Modal.vue'
import UiButton from '@/components/ui/Button.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'

const { get, post } = useApi()
const toast = useToast()

const items = ref([])
const loading = ref(false)
const meta = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1 })
const filters = ref({ from: '', to: '', patient_id: '' })
const previewOpen = ref(false)
const previewData = ref(null)
const previewLoading = ref(false)

const fetchList = async (page = 1) => {
  loading.value = true
  try {
    const params = { page, ...filters.value }
    Object.keys(params).forEach(k => {
      if (!params[k]) delete params[k]
    })
    const response = await get('/api/appointments/ready-to-bill', { params })
    items.value = response?.data ?? []
    meta.value = response?.meta ?? meta.value
  } catch (error) {
    toast.error('No se pudo cargar la lista de citas por cobrar')
  } finally {
    loading.value = false
  }
}

const openPreview = async appointment => {
  previewOpen.value = true
  previewData.value = null
  previewLoading.value = true
  try {
    const response = await get(`/api/appointments/${appointment.id}/payment-preview`)
    previewData.value = response?.data ?? null
  } catch (error) {
    toast.error('No se pudo cargar el desglose')
  } finally {
    previewLoading.value = false
  }
}

const closePreview = () => {
  previewOpen.value = false
  previewData.value = null
}

const generateQuotation = async appointment => {
  try {
    const response = await post(`/api/appointments/${appointment.id}/generate-quotation`, {})
    toast.success(`Cotización ${response?.data?.quotation_number || ''} generada`)
    await fetchList(meta.value.current_page)
  } catch (error) {
    toast.error('No se pudo generar la cotización')
  }
}

// formatCurrency is imported from useFormatters (PR-pagos-01 canonicalization).
const formatDate = iso => {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(() => fetchList())
watch(filters, () => fetchList(1), { deep: true })
</script>

<template>
  <div class="space-y-5 bg-canvas">
    <header class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-theme-primary">Citas por cobrar</h1>
        <p class="text-sm text-theme-secondary mt-1">
          Citas completadas con monto pendiente de pago
        </p>
      </div>
      <UiButton variant="secondary" size="sm" @click="fetchList(meta.current_page)">
        Refrescar
      </UiButton>
    </header>

    <div class="flex flex-wrap items-end gap-3 p-4 bg-canvas rounded-xl border border-hairline">
      <div>
        <label class="block text-xs text-theme-secondary mb-1">Desde</label>
        <input
          v-model="filters.from"
          type="date"
          class="p-2 rounded border border-hairline bg-systemBackground"
        />
      </div>
      <div>
        <label class="block text-xs text-theme-secondary mb-1">Hasta</label>
        <input
          v-model="filters.to"
          type="date"
          class="p-2 rounded border border-hairline bg-systemBackground"
        />
      </div>
      <div>
        <label class="block text-xs text-theme-secondary mb-1">ID Paciente</label>
        <input
          v-model.number="filters.patient_id"
          type="number"
          min="1"
          class="p-2 rounded border border-hairline bg-systemBackground"
        />
      </div>
    </div>

    <div v-if="loading" class="text-center py-10 text-theme-secondary">Cargando…</div>
    <div v-else-if="items.length === 0" class="text-center py-10 text-theme-secondary">
      No hay citas pendientes de cobro.
    </div>
    <div v-else class="overflow-x-auto bg-canvas rounded-xl border border-hairline">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-theme-secondary border-b border-hairline">
            <th class="p-3" scope="col">#</th>
            <th class="p-3" scope="col">Paciente</th>
            <th class="p-3" scope="col">Tipo</th>
            <th class="p-3" scope="col">Completada</th>
            <th class="p-3 text-right" scope="col">Monto</th>
            <th class="p-3 text-right" scope="col">Pagado</th>
            <th class="p-3 text-right" scope="col">Saldo</th>
            <th class="p-3" scope="col">Cotización</th>
            <th class="p-3" scope="col" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in items"
            :key="row.id"
            class="border-b border-hairline hover:bg-systemBackground"
          >
            <td class="p-3 font-mono">#{{ row.id }}</td>
            <td class="p-3">{{ row.patient?.first_name }} {{ row.patient?.last_name }}</td>
            <td class="p-3">
              {{ row.appointment_type || '—' }}
            </td>
            <td class="p-3 text-theme-secondary">
              {{ formatDate(row.completed_at) }}
            </td>
            <td
              class="p-3 text-right font-medium tabular-nums"
              :aria-label="`${formatCurrency(row.final_amount)} soles`"
            >
              {{ formatCurrency(row.final_amount) }}
            </td>
            <td
              class="p-3 text-right text-systemGreen-600 tabular-nums"
              :aria-label="`${formatCurrency(row.paid_amount)} soles`"
            >
              {{ formatCurrency(row.paid_amount) }}
            </td>
            <td
              class="p-3 text-right text-systemRed-600 font-medium tabular-nums"
              :aria-label="`${formatCurrency(row.balance)} soles`"
            >
              {{ formatCurrency(row.balance) }}
            </td>
            <td class="p-3">
              <UiStatusBadge v-if="row.has_quotation" variant="success" label="Sí"
size="sm" />
              <UiStatusBadge v-else variant="neutral" label="No"
size="sm" />
            </td>
            <td class="p-3 space-x-1 whitespace-nowrap">
              <UiButton variant="secondary" size="xs" @click="openPreview(row)">
Desglose
</UiButton>
              <UiButton
                v-if="!row.has_quotation"
                variant="primary"
                size="xs"
                @click="generateQuotation(row)"
              >
                Generar cotización
              </UiButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="flex items-center justify-center gap-2 text-sm">
      <UiButton
        variant="ghost"
        size="sm"
        :disabled="meta.current_page <= 1"
        @click="fetchList(meta.current_page - 1)"
      >
        ←
      </UiButton>
      <span class="text-theme-secondary">
        Página {{ meta.current_page }} / {{ meta.last_page }} · {{ meta.total }} citas
      </span>
      <UiButton
        variant="ghost"
        size="sm"
        :disabled="meta.current_page >= meta.last_page"
        @click="fetchList(meta.current_page + 1)"
      >
        →
      </UiButton>
    </div>

    <UiModal v-model="previewOpen" size="lg" title="Desglose de pago" @close="closePreview">
      <div v-if="previewLoading" class="p-10 text-center text-theme-secondary">Cargando…</div>
      <div v-else-if="previewData" class="space-y-4">
        <div class="p-3 rounded-lg bg-canvas border border-hairline">
          <div class="text-xs text-theme-secondary">Paciente</div>
          <div class="font-medium">
            {{ previewData.appointment.patient?.first_name }}
            {{ previewData.appointment.patient?.last_name }}
          </div>
          <div class="text-xs text-theme-secondary mt-1">
            Cita #{{ previewData.appointment.id }} ·
            {{ formatDate(previewData.appointment.completed_at) }}
          </div>
        </div>

        <div>
          <h3 class="text-sm font-semibold text-theme-primary mb-2">Items del plan</h3>
          <div v-if="previewData.items.length === 0" class="text-sm text-theme-secondary">
            Sin items (monto fijo por tipo de cita).
          </div>
          <ul v-else class="space-y-1 text-sm">
            <li
              v-for="i in previewData.items"
              :key="i.id"
              class="flex justify-between p-2 rounded bg-canvas border border-hairline"
            >
              <span>
                {{ i.procedure_name }}
                <span class="text-theme-secondary">× {{ i.quantity }}</span>
              </span>
              <span
                class="font-medium tabular-nums"
                :aria-label="`${formatCurrency(i.total_cost)} soles`"
              >
                {{ formatCurrency(i.total_cost) }}
              </span>
            </li>
          </ul>
        </div>

        <div class="border-t border-hairline pt-3 space-y-1 text-sm">
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
            <span
              class="tabular-nums"
              :aria-label="`${formatCurrency(previewData.paid_amount)} soles`"
            >
              {{ formatCurrency(previewData.paid_amount) }}
            </span>
          </div>
          <div class="flex justify-between text-systemRed-600 font-semibold">
            <span>Saldo</span>
            <span class="tabular-nums" :aria-label="`${formatCurrency(previewData.balance)} soles`">
              {{ formatCurrency(previewData.balance) }}
            </span>
          </div>
        </div>

        <div v-if="previewData.payments.length" class="border-t border-hairline pt-3">
          <h3 class="text-sm font-semibold text-theme-primary mb-2">Pagos aplicados</h3>
          <ul class="text-sm space-y-1">
            <li
              v-for="p in previewData.payments"
              :key="p.id"
              class="flex justify-between text-theme-secondary"
            >
              <span>{{ p.transaction_number }} · {{ p.payment_method || '—' }}</span>
              <span class="tabular-nums">{{ formatCurrency(p.amount) }}</span>
            </li>
          </ul>
        </div>

        <div v-if="previewData.quotations.length" class="border-t border-hairline pt-3">
          <h3 class="text-sm font-semibold text-theme-primary mb-2">Cotizaciones</h3>
          <ul class="text-sm space-y-1">
            <li v-for="q in previewData.quotations" :key="q.id" class="flex justify-between">
              <span>
                {{ q.quotation_number }}
                <span class="text-xs text-theme-secondary">({{ q.status }})</span>
              </span>
              <span class="tabular-nums">{{ formatCurrency(q.total_amount) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </UiModal>
  </div>
</template>
