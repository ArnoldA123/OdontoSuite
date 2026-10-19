<template>
  <AppLayout>
    <header
      data-stats-hero
      class="mb-6 p-6 md:p-8"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <button
        type="button"
        class="inline-flex items-center gap-1 text-sm font-medium text-theme-secondary hover:text-theme-primary"
        @click="goBack"
      >
        <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
        Catálogo de Procedimientos
      </button>
      <div class="mt-3 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Catálogo</p>
          <h1 class="mt-2 text-4xl font-semibold text-label" data-hero-title>
            Estadísticas de Procedimientos
          </h1>
          <p class="mt-2 text-sm text-theme-secondary">
            Uso del catálogo por especialidad y procedimientos más frecuentes
          </p>
          <p class="mt-1 text-xs text-theme-secondary">Visible para: Administrador, Finanzas</p>
        </div>
        <div class="flex flex-shrink-0 items-center gap-3">
          <UiButton variant="secondary" @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
        </div>
      </div>
    </header>

    <UiCard variant="glass" class="mb-6">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <UiInput v-model="filters.from" label="Desde" type="date" />
        <UiInput v-model="filters.to" label="Hasta" type="date" />
        <UiInput v-model.number="filters.limit" label="Top N" type="number" min="1" max="50" />
        <div class="flex items-end">
          <UiButton class="w-full" :disabled="loading" :loading="loading" @click="loadStats">
            Aplicar filtros
          </UiButton>
        </div>
      </div>
    </UiCard>

    <div v-if="loading" data-stats-loading class="space-y-6" aria-busy="true">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <UiSkeleton v-for="card in 3" :key="card" variant="card" />
      </div>
      <UiSkeleton variant="list" />
      <UiSkeleton variant="list" />
    </div>

    <div
      v-else-if="error"
      data-stats-error
      role="alert"
      class="rounded-ios bg-systemRed-50 p-10 text-center"
      style="border: 1px solid var(--color-hairline)"
    >
      <ExclamationTriangleIcon
        class="mx-auto h-12 w-12 mb-4 text-systemRed-600"
        aria-hidden="true"
      />
      <p class="text-base font-medium text-theme-primary">
        No pudimos cargar las estadísticas del catálogo
      </p>
      <p class="mt-1 max-w-md mx-auto text-sm text-theme-secondary">{{ error }}</p>
      <div class="mt-6">
        <UiButton variant="primary" size="md" data-retry-stats @click="loadStats">
          Reintentar
        </UiButton>
      </div>
    </div>

    <template v-else>
      <section aria-label="Resumen del catálogo" class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <UiCard
          variant="glass"
          padding="sm"
          data-stat-card="total-procedures"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Total procedimientos</p>
          <p class="mt-2 text-3xl font-bold text-label tabular-nums leading-none">
            {{ stats?.catalog?.total ?? 0 }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">En el catálogo</p>
        </UiCard>
        <UiCard
          variant="glass"
          padding="sm"
          data-stat-card="active"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Activos</p>
          <p class="mt-2 text-3xl font-bold text-systemGreen-600 tabular-nums leading-none">
            {{ stats?.catalog?.active ?? 0 }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">Disponibles para uso clínico</p>
        </UiCard>
        <UiCard
          variant="glass"
          padding="sm"
          data-stat-card="inactive"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Inactivos</p>
          <p class="mt-2 text-3xl font-bold text-theme-secondary tabular-nums leading-none">
            {{ stats?.catalog?.inactive ?? 0 }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">Fuera del flujo clínico</p>
        </UiCard>
      </section>

      <UiCard variant="glass" class="mb-6 overflow-hidden">
        <div class="border-b border-hairline p-6">
          <h2 class="text-2xl font-semibold text-label">
            Top {{ stats?.top_procedures?.length || 0 }} procedimientos más usados
          </h2>
          <p class="mt-1 text-sm text-theme-secondary">
            Del {{ formatDate(stats?.period?.from) }} al {{ formatDate(stats?.period?.to) }}
          </p>
        </div>
        <UiEmptyState
          v-if="!stats?.top_procedures?.length"
          title="Sin datos"
          description="No hay datos para el período seleccionado."
        />
        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[640px] divide-y divide-hairline">
            <thead class="bg-theme-surface">
              <tr>
                <th
                  class="w-12 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-theme-secondary"
                >
                  #
                </th>
                <th
                  class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-theme-secondary"
                >
                  Código
                </th>
                <th
                  class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-theme-secondary"
                >
                  Nombre
                </th>
                <th
                  class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-theme-secondary"
                >
                  Usos
                </th>
                <th
                  class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-theme-secondary"
                >
                  Cantidad
                </th>
                <th
                  class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-theme-secondary"
                >
                  Ingresos
                </th>
              </tr>
            </thead>
            <tbody class="bg-theme-surface-elevated divide-y divide-hairline">
              <tr
                v-for="(procedure, index) in stats.top_procedures"
                :key="procedure.procedure_id"
                data-top-row
              >
                <td class="px-4 py-3 text-sm tabular-nums text-theme-secondary">{{ index + 1 }}</td>
                <td class="px-4 py-3 font-mono text-xs text-theme-secondary">
                  {{ procedure.code }}
                </td>
                <td class="px-4 py-3 text-sm font-medium text-theme-primary">
                  {{ procedure.name }}
                </td>
                <td class="px-4 py-3 text-right text-sm tabular-nums text-theme-primary">
                  {{ procedure.usage_count }}
                </td>
                <td class="px-4 py-3 text-right text-sm tabular-nums text-theme-primary">
                  {{ procedure.total_quantity }}
                </td>
                <td class="px-4 py-3 text-right text-sm tabular-nums text-theme-primary">
                  {{ formatPENLabel(procedure.total_revenue) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UiCard>

      <UiCard variant="glass">
        <div class="border-b border-hairline p-6">
          <h2 class="text-2xl font-semibold text-label">Distribución por especialidad</h2>
          <p class="mt-1 text-sm text-theme-secondary">Uso clínico agrupado por especialidad</p>
        </div>
        <div class="p-6">
          <UiEmptyState
            v-if="!stats?.by_specialty?.length"
            title="Sin datos"
            description="No hay datos para el período seleccionado."
          />
          <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="specialty in stats.by_specialty"
              :key="specialty.specialty_id || 'without-specialty'"
              data-specialty-card
              class="rounded-panel border border-hairline bg-theme-surface-elevated p-4"
            >
              <p class="text-sm text-theme-secondary">
                {{ specialty.specialty_name || 'Sin especialidad' }}
              </p>
              <p class="mt-1 text-2xl font-bold text-label tabular-nums">
                {{ specialty.usage_count }} usos
              </p>
              <p class="mt-1 text-sm text-theme-secondary tabular-nums">
                {{ formatPENLabel(specialty.total_revenue) }}
              </p>
            </div>
          </div>
        </div>
      </UiCard>
    </template>
  </AppLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeftIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'
import { formatPENLabel } from '@/composables/useFormatters'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'

const router = useRouter()
const { get } = useApi()

const filters = ref({
  from: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  to: new Date().toISOString().split('T')[0],
  limit: 10
})

const stats = ref(null)
const loading = ref(false)
const error = ref(null)

const formatDate = value => {
  if (!value) return 'sin fecha'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('es-PE')
}

const buildQuery = () => {
  const params = new URLSearchParams()
  if (filters.value.from) params.append('from', filters.value.from)
  if (filters.value.to) params.append('to', filters.value.to)
  if (filters.value.limit) params.append('limit', filters.value.limit)
  return params.toString()
}

const loadStats = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await get(`/api/admin/procedure-stats?${buildQuery()}`)
    stats.value = response.data || null
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al cargar las estadísticas del catálogo'
    stats.value = null
  } finally {
    loading.value = false
  }
}

const goBack = () => router.push('/procedure-catalog')

onMounted(loadStats)
</script>
