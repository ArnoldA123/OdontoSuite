<template>
  <AppLayout class="bg-canvas">
    <!-- Page header (EC-003) — replaces the legacy page-level <h1> defect -->
    <PageHeader
      title="Estadísticas de Procedimientos"
      subtitle="Uso del catálogo por especialidad y procedimientos más frecuentes"
    />

    <!-- Role disclosure (EC-008) — inline, small; not a full-width banner -->
    <div class="text-xs text-theme-secondary mb-4">
      Visible para: Administrador, Finanzas
    </div>

    <!-- Filtros -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">Filtros</h2>
      </template>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">Desde</label>
          <UiInput v-model="filters.from" type="date" />
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">Hasta</label>
          <UiInput v-model="filters.to" type="date" />
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">Top N</label>
          <UiInput v-model.number="filters.limit" type="number" min="1"
max="50" />
        </div>
        <div class="flex items-end">
          <UiButton class="w-full" :disabled="loading" @click="loadStats">
            {{ loading ? 'Cargando...' : 'Aplicar filtros' }}
          </UiButton>
        </div>
      </div>
    </UiCard>

    <!-- Loading state (EC-004) — 3 card skeletons + 6 list skeletons -->
    <template v-if="loading">
      <div class="space-y-6" aria-busy="true" aria-live="polite">
        <section aria-label="Cargando resumen">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <UiSkeleton variant="card" />
            <UiSkeleton variant="card" />
            <UiSkeleton variant="card" />
          </div>
        </section>
        <section aria-label="Cargando tabla y distribución">
          <UiSkeleton variant="list" />
          <UiSkeleton variant="list" />
          <UiSkeleton variant="list" />
          <UiSkeleton variant="list" />
          <UiSkeleton variant="list" />
          <UiSkeleton variant="list" />
        </section>
      </div>
    </template>

    <!-- Resumen del catalogo (EC-002 KPI anatomy) -->
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <UiCard
        variant="glass"
        data-stat-card="total-procedures"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <div class="min-w-0 flex-1">
          <div class="h-4 flex items-center">
            <p class="text-[11px] font-medium text-theme-secondary uppercase whitespace-nowrap">
              Total procedimientos
            </p>
          </div>
          <div class="h-12 flex items-center">
            <p
              class="text-5xl font-bold text-label tabular-nums leading-none"
              style="font-feature-settings: var(--font-features-tabular-nums)"
            >
              {{ stats?.catalog?.total ?? 0 }}
            </p>
          </div>
          <div class="h-6 min-h-[24px]" />
          <div class="h-4 flex items-center">
            <p class="text-xs text-theme-secondary truncate">
              {{ stats?.catalog?.total ?? 0 }} en el catálogo
            </p>
          </div>
        </div>
      </UiCard>
      <UiCard
        variant="glass"
        data-stat-card="active"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <div class="min-w-0 flex-1">
          <div class="h-4 flex items-center">
            <p class="text-[11px] font-medium text-theme-secondary uppercase whitespace-nowrap">
              Activos
            </p>
          </div>
          <div class="h-12 flex items-center">
            <p
              class="text-5xl font-bold text-systemGreen-600 tabular-nums leading-none"
              style="font-feature-settings: var(--font-features-tabular-nums)"
            >
              {{ stats?.catalog?.active ?? 0 }}
            </p>
          </div>
          <div class="h-6 min-h-[24px]" />
          <div class="h-4 flex items-center">
            <p class="text-xs text-theme-secondary truncate">
              disponibles para uso clínico
            </p>
          </div>
        </div>
      </UiCard>
      <UiCard
        variant="glass"
        data-stat-card="inactive"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <div class="min-w-0 flex-1">
          <div class="h-4 flex items-center">
            <p class="text-[11px] font-medium text-theme-secondary uppercase whitespace-nowrap">
              Inactivos
            </p>
          </div>
          <div class="h-12 flex items-center">
            <p
              class="text-5xl font-bold text-label tabular-nums leading-none"
              style="font-feature-settings: var(--font-features-tabular-nums)"
            >
              {{ stats?.catalog?.inactive ?? 0 }}
            </p>
          </div>
          <div class="h-6 min-h-[24px]" />
          <div class="h-4 flex items-center">
            <p class="text-xs text-theme-secondary truncate">
              fuera del flujo clínico
            </p>
          </div>
        </div>
      </UiCard>
    </div>

    <!-- Top procedimientos -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">
          Top {{ stats?.top_procedures?.length || 0 }} procedimientos más usados
        </h2>
      </template>
      <UiEmptyState
        v-if="!stats?.top_procedures?.length"
        title="Sin datos"
        description="No hay datos para el período seleccionado."
      />
      <table v-else class="w-full">
        <thead class="text-left text-sm text-theme-secondary border-b border-[color:var(--color-hairline)]">
          <tr>
            <th class="py-2">#</th>
            <th class="py-2">Código</th>
            <th class="py-2">Nombre</th>
            <th class="py-2 text-right">Usos</th>
            <th class="py-2 text-right">Cantidad total</th>
            <th class="py-2 text-right">Ingresos S/</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(proc, idx) in stats.top_procedures"
            :key="proc.procedure_id"
            class="border-b border-[color:var(--color-hairline)]"
          >
            <td class="py-2 text-theme-secondary">
              {{ idx + 1 }}
            </td>
            <td class="py-2 font-mono text-theme-primary">
              {{ proc.code }}
            </td>
            <td class="py-2 text-theme-primary">
              {{ proc.name }}
            </td>
            <td
              class="py-2 text-right text-theme-primary tabular-nums"
              style="font-feature-settings: var(--font-features-tabular-nums)"
            >
              {{ proc.usage_count }}
            </td>
            <td
              class="py-2 text-right text-theme-primary tabular-nums"
              style="font-feature-settings: var(--font-features-tabular-nums)"
            >
              {{ proc.total_quantity }}
            </td>
            <td
              class="py-2 text-right text-theme-primary tabular-nums"
              style="font-feature-settings: var(--font-features-tabular-nums)"
            >
              {{ formatPENLabel(proc.total_revenue) }}
            </td>
          </tr>
        </tbody>
      </table>
    </UiCard>

    <!-- Por especialidad -->
    <UiCard variant="glass">
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">Distribución por especialidad</h2>
      </template>
      <UiEmptyState
        v-if="!stats?.by_specialty?.length"
        title="Sin datos"
        description="No hay datos para el período seleccionado."
      />
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="spec in stats.by_specialty"
          :key="spec.specialty_id || 'sin'"
          class="p-4 rounded-[var(--radius-control)] border border-[color:var(--color-hairline)] bg-theme-surface-elevated"
        >
          <p class="text-sm text-theme-secondary">
            {{ spec.specialty_name || 'Sin especialidad' }}
          </p>
          <p
            class="text-2xl font-bold text-theme-primary mt-1 tabular-nums"
            style="font-feature-settings: var(--font-features-tabular-nums)"
          >{{ spec.usage_count }} usos</p>
          <p
            class="text-sm text-theme-secondary mt-1 tabular-nums"
            style="font-feature-settings: var(--font-features-tabular-nums)"
          >{{ formatPENLabel(spec.total_revenue) }}</p>
        </div>
      </div>
    </UiCard>

    <!-- Error banner (EC-006 — systemRed ramp) -->
    <div
      v-if="error"
      class="mt-4 p-4 bg-systemRed-50 border border-systemRed-200 rounded-lg text-systemRed-700"
    >
      {{ error }}
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import { formatPENLabel } from '@/composables/useFormatters'

const { get } = useApi()

const filters = ref({
  from: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  to: new Date().toISOString().split('T')[0],
  limit: 10
})

const stats = ref(null)
const loading = ref(false)
const error = ref(null)

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
    error.value = err.response?.data?.message || 'Error al cargar estadísticas'
    stats.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadStats()
})
</script>
