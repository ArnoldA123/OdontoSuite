<template>
  <AppLayout>
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">
            Estadísticas de Procedimientos
          </h1>
          <p class="text-theme-secondary">
            Uso del catálogo por especialidad y procedimientos más frecuentes
          </p>
        </div>
        <UiButton variant="secondary" class="flex items-center gap-2" @click="goBack">
          Volver
        </UiButton>
      </div>
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
          <UiInput v-model.number="filters.limit" type="number" min="1" max="50" />
        </div>
        <div class="flex items-end">
          <UiButton class="w-full" @click="loadStats" :disabled="loading">
            {{ loading ? 'Cargando...' : 'Aplicar filtros' }}
          </UiButton>
        </div>
      </div>
    </UiCard>

    <!-- Resumen del catalogo -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <UiCard variant="glass">
        <template #header>
          <h3 class="text-sm font-medium text-theme-secondary">Total procedimientos</h3>
        </template>
        <p class="text-3xl font-bold text-theme-primary">{{ stats?.catalog?.total ?? 0 }}</p>
      </UiCard>
      <UiCard variant="glass">
        <template #header>
          <h3 class="text-sm font-medium text-theme-secondary">Activos</h3>
        </template>
        <p class="text-3xl font-bold text-green-600">{{ stats?.catalog?.active ?? 0 }}</p>
      </UiCard>
      <UiCard variant="glass">
        <template #header>
          <h3 class="text-sm font-medium text-theme-secondary">Inactivos</h3>
        </template>
        <p class="text-3xl font-bold text-theme-secondary">{{ stats?.catalog?.inactive ?? 0 }}</p>
      </UiCard>
    </div>

    <!-- Top procedimientos -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">Top {{ stats?.top_procedures?.length || 0 }} procedimientos más usados</h2>
      </template>
      <div v-if="!stats?.top_procedures?.length" class="text-center py-8 text-theme-secondary">
        Sin datos para el período seleccionado.
      </div>
      <table v-else class="w-full">
        <thead class="text-left text-sm text-theme-secondary border-b border-theme">
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
          <tr v-for="(proc, idx) in stats.top_procedures" :key="proc.procedure_id" class="border-b border-theme">
            <td class="py-2 text-theme-secondary">{{ idx + 1 }}</td>
            <td class="py-2 font-mono text-theme-primary">{{ proc.code }}</td>
            <td class="py-2 text-theme-primary">{{ proc.name }}</td>
            <td class="py-2 text-right text-theme-primary">{{ proc.usage_count }}</td>
            <td class="py-2 text-right text-theme-primary">{{ proc.total_quantity }}</td>
            <td class="py-2 text-right text-theme-primary">{{ proc.total_revenue.toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
    </UiCard>

    <!-- Por especialidad -->
    <UiCard variant="glass">
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">Distribución por especialidad</h2>
      </template>
      <div v-if="!stats?.by_specialty?.length" class="text-center py-8 text-theme-secondary">
        Sin datos para el período seleccionado.
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="spec in stats.by_specialty"
          :key="spec.specialty_id || 'sin'"
          class="p-4 rounded-lg border border-theme bg-theme-surface-elevated"
        >
          <p class="text-sm text-theme-secondary">{{ spec.specialty_name || 'Sin especialidad' }}</p>
          <p class="text-2xl font-bold text-theme-primary mt-1">{{ spec.usage_count }} usos</p>
          <p class="text-sm text-theme-secondary mt-1">S/ {{ spec.total_revenue.toFixed(2) }}</p>
        </div>
      </div>
    </UiCard>

    <div v-if="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
      {{ error }}
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'

const router = useRouter()
const { get } = useApi()

const filters = ref({
  from: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  to: new Date().toISOString().split('T')[0],
  limit: 10,
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

const goBack = () => router.push('/business-intelligence')

onMounted(() => {
  loadStats()
})
</script>
