<template>
  <AppLayout>
    <div class="space-y-6">
        <!-- Filters Section -->
        <UiCard variant="glass">
          <template #header>
            <h2 class="text-lg font-semibold text-theme-primary">Filtros de Reporte</h2>
          </template>
            <div class="space-y-6">
              <!-- First Row -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Date Range -->
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-2">Rango de Fechas</label>
                <div class="flex space-x-2">
                  <UiInput
                    v-model="filters.startDate"
                    type="date"
                    class="flex-1"
                  />
                  <UiInput
                    v-model="filters.endDate"
                    type="date"
                    class="flex-1"
                  />
                </div>
              </div>

              <!-- Professional Filter -->
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-2">
                  Profesional ({{ professionals.length }})
                </label>
                <select
                  v-model="filters.professionalId"
                  class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                >
                  <option value="">Todos los profesionales</option>
                  <option v-for="professional in professionals" :key="professional.id" :value="professional.id">
                    {{ professional.name }}
                  </option>
                </select>
                <div class="text-xs text-theme-secondary mt-1">
                  Debug: {{ professionals.length }} profesionales cargados
                </div>
              </div>
              </div>

              <!-- Second Row -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Environment Filter -->
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-2">
                    Ambiente ({{ environments.length }})
                  </label>
                  <select
                    v-model="filters.environmentId"
                    class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                  >
                    <option value="">Todos los ambientes</option>
                    <option v-for="environment in environments" :key="environment.id" :value="environment.id">
                      {{ environment.name }}
                    </option>
                  </select>
                  <div class="text-xs text-theme-secondary mt-1">
                    Debug: {{ environments.length }} ambientes cargados
                  </div>
                </div>

                <!-- Report Type -->
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-2">Tipo de Reporte</label>
                  <select
                    v-model="selectedReport"
                    @change="loadReport"
                    class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                  >
                    <option value="dashboard">Dashboard General</option>
                    <option value="appointments">Reporte de Citas</option>
                    <option value="patients">Reporte de Pacientes</option>
                    <option value="professionals">Reporte de Profesionales</option>
                    <option value="revenue">Reporte de Ingresos</option>
                    <option value="utilization">Utilización de Ambientes</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between items-center mt-6">
              <div class="flex space-x-3">
                <UiButton
                  @click="applyFilters"
                  variant="primary"
                >
                  <template #icon-left>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                  </template>
                  Aplicar Filtros
                </UiButton>
                <UiButton
                  @click="resetFilters"
                  variant="secondary"
                >
                  Limpiar
                </UiButton>
              </div>

              <!-- Export Buttons -->
              <div class="flex space-x-2">
                <UiButton
                  @click="exportReport('excel')"
                  :disabled="loading"
                  variant="success"
                  size="sm"
                >
                  <template #icon-left>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                  </template>
                  Excel
                </UiButton>
                <UiButton
                  @click="exportReport('csv')"
                  :disabled="loading"
                  variant="primary"
                  size="sm"
                >
                  <template #icon-left>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                  </template>
                  CSV
                </UiButton>
                <UiButton
                  @click="exportReport('pdf')"
                  :disabled="loading"
                  variant="danger"
                  size="sm"
                >
                  <template #icon-left>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                  </template>
                  PDF
                </UiButton>
              </div>
            </div>
        </UiCard>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
          <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="ml-3 text-theme-secondary">Cargando reporte...</span>
        </div>

        <!-- Dashboard Content -->
        <div v-else>
          <!-- Dashboard General -->
          <div v-if="selectedReport === 'dashboard'" class="space-y-6">
            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <UiCard variant="elevated">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                      </svg>
                    </div>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-theme-secondary">Total Citas</p>
                    <p class="text-2xl font-semibold text-theme-primary">{{ dashboardData.totalAppointments || 0 }}</p>
                  </div>
                </div>
              </UiCard>

              <UiCard variant="elevated">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                      </svg>
                    </div>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-theme-secondary">Total Pacientes</p>
                    <p class="text-2xl font-semibold text-theme-primary">{{ dashboardData.totalPatients || 0 }}</p>
                  </div>
                </div>
              </UiCard>

              <UiCard variant="elevated">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                      </svg>
                    </div>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-theme-secondary">Ingresos Totales</p>
                    <p class="text-2xl font-semibold text-theme-primary">S/ {{ dashboardData.totalRevenue || 0 }}</p>
                  </div>
                </div>
              </UiCard>

              <UiCard variant="elevated">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                      </svg>
                    </div>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-theme-secondary">Tasa de Ocupación</p>
                    <p class="text-2xl font-semibold text-theme-primary">{{ dashboardData.occupancyRate || 0 }}%</p>
                  </div>
                </div>
              </UiCard>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Appointments Chart -->
              <UiCard variant="elevated">
                <h3 class="text-lg font-semibold text-theme-primary mb-4">Citas por Día</h3>
                <div class="h-64">
                  <canvas ref="appointmentsChart"></canvas>
                </div>
              </UiCard>

              <!-- Revenue Chart -->
              <UiCard variant="elevated">
                <h3 class="text-lg font-semibold text-theme-primary mb-4">Ingresos por Mes</h3>
                <div class="h-64">
                  <canvas ref="revenueChart"></canvas>
                </div>
              </UiCard>
            </div>

            <!-- Professional Performance -->
            <UiCard variant="elevated">
              <h3 class="text-lg font-semibold text-theme-primary mb-4">Rendimiento por Profesional</h3>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-theme">
                  <thead class="bg-theme-surface">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">Profesional</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">Citas</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">Ingresos</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">Promedio por Cita</th>
                    </tr>
                  </thead>
                  <tbody class="bg-theme-surface-elevated divide-y divide-theme">
                    <tr v-if="dashboardData.professionalPerformance.length === 0">
                      <td colspan="4" class="px-6 py-4 text-center text-sm text-theme-secondary">
                        No hay datos disponibles
                      </td>
                    </tr>
                    <tr v-for="professional in dashboardData.professionalPerformance" :key="professional.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-theme-primary">
                        {{ professional.name }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-secondary">
                        {{ professional.appointments }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-secondary">
                        S/ {{ professional.revenue }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-secondary">
                        S/ {{ professional.averagePerAppointment }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </UiCard>
          </div>

          <!-- Specific Reports -->
          <div v-else class="bg-theme-surface-elevated rounded-2xl shadow-xl p-6">
            <h3 class="text-lg font-semibold text-theme-primary mb-4">{{ getReportTitle() }}</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-theme">
                <thead class="bg-theme-surface">
                  <tr>
                    <th v-for="column in reportColumns" :key="column.key" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                      {{ column.label }}
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-theme-surface-elevated divide-y divide-theme">
                  <tr v-if="reportData.length === 0">
                    <td :colspan="reportColumns.length" class="px-6 py-4 text-center text-sm text-theme-secondary">
                      No hay datos disponibles
                    </td>
                  </tr>
                  <tr v-for="(row, index) in reportData" :key="index">
                    <td v-for="column in reportColumns" :key="column.key" class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                      {{ row[column.key] }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useErrorHandler } from '../../composables/useErrorHandler'
import { useEcho } from '../../composables/useEcho'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiCard from '../../components/ui/Card.vue'

export default {
  name: 'BusinessIntelligencePage',
  components: {
    AppLayout,
    UiButton,
    UiInput,
    UiCard
  },
  setup() {
    const router = useRouter()
    const { user, logout: authLogout, get } = useApi()
    const toast = useToast()
    const { channel, echo } = useEcho()

    const loading = ref(false)
    const selectedReport = ref('dashboard')
    const professionals = ref([])
    const environments = ref([])
    const dashboardData = ref({
      totalAppointments: 0,
      totalPatients: 0,
      totalRevenue: 0,
      occupancyRate: 0,
      appointmentsByDay: [],
      revenueByMonth: [],
      professionalPerformance: []
    })
    const reportData = ref([])
    const reportColumns = ref([])

    const filters = reactive({
      startDate: '',
      endDate: '',
      professionalId: '',
      environmentId: ''
    })

    const appointmentsChart = ref(null)
    const revenueChart = ref(null)
    const appointmentsChartInstance = ref(null)
    const revenueChartInstance = ref(null)

    // WebSocket subscriptions
    let dashboardChannel = null

    const loadInitialData = async () => {
      try {
        // Set default date range (last 30 days to next 60 days)
        const today = new Date()
        const startDate = new Date()
        const endDate = new Date()
        startDate.setDate(today.getDate() - 30)
        endDate.setDate(today.getDate() + 60)

        filters.startDate = startDate.toISOString().split('T')[0]
        filters.endDate = endDate.toISOString().split('T')[0]

        // Load professionals and environments
        const [professionalsRes, environmentsRes] = await Promise.all([
          get('/api/users/active'),
          get('/api/dental-chairs/active')
        ])

        professionals.value = professionalsRes?.data || []
        environments.value = environmentsRes?.data || []

        // Verificar si hay datos vacíos y notificar
        if (professionals.value.length === 0) {
          toast.warning('No se encontraron profesionales activos')
        }
        if (environments.value.length === 0) {
          toast.warning('No se encontraron ambientes activos')
        }

        // Force reactivity update
        await nextTick()

        // Load dashboard data
        await loadDashboardData()
      } catch (error) {
        console.error('Error loading initial data:', error)
        toast.error('Error al cargar los datos iniciales. Por favor, recarga la página.')
        professionals.value = []
        environments.value = []
      }
    }

    const loadDashboardData = async () => {
      loading.value = true
      try {
        const response = await get('/api/reports/dashboard', {
          params: {
            start_date: filters.startDate,
            end_date: filters.endDate,
            professional_id: filters.professionalId,
            environment_id: filters.environmentId
          }
        })

        console.log('Dashboard response:', response)
        console.log('Dashboard response.data:', response.data)
        console.log('Dashboard response.data keys:', Object.keys(response.data || {}))

        dashboardData.value = {
          totalAppointments: response.data?.totalAppointments || 0,
          totalPatients: response.data?.totalPatients || 0,
          totalRevenue: response.data?.totalRevenue || 0,
          occupancyRate: response.data?.occupancyRate || 0,
          appointmentsByDay: response.data?.appointmentsByDay || [],
          revenueByMonth: response.data?.revenueByMonth || [],
          professionalPerformance: response.data?.professionalPerformance || []
        }

        console.log('Dashboard data set:', dashboardData.value)

        // Create charts after data is loaded
        await nextTick()
        // Wait a bit more for DOM to be fully rendered
        setTimeout(async () => {
          console.log('About to create charts, appointmentsChart:', appointmentsChart.value)
          console.log('About to create charts, revenueChart:', revenueChart.value)
          await createCharts()
        }, 100)
      } catch (error) {
        console.error('Error loading dashboard data:', error)
        toast.error('Error al cargar los datos del dashboard. Por favor, intenta nuevamente.')
      } finally {
        loading.value = false
      }
    }

    const loadReport = async () => {
      if (selectedReport.value === 'dashboard') {
        await loadDashboardData()
        return
      }

      loading.value = true
      try {
        const response = await get(`/api/reports/${selectedReport.value}`, {
          params: {
            start_date: filters.startDate,
            end_date: filters.endDate,
            professional_id: filters.professionalId,
            environment_id: filters.environmentId
          }
        })

        console.log('Report response:', response)
        reportData.value = response.data?.data || []
        reportColumns.value = response.data?.columns || []
      } catch (error) {
        console.error('Error loading report:', error)
      } finally {
        loading.value = false
      }
    }

    const createCharts = async () => {
      try {
        console.log('Creating charts...')
        console.log('Appointments chart element:', appointmentsChart.value)
        console.log('Revenue chart element:', revenueChart.value)
        console.log('Dashboard data:', dashboardData.value)

        // Check if elements exist
        if (!appointmentsChart.value) {
          console.error('Appointments chart element not found')
          console.log('Available refs:', { appointmentsChart: appointmentsChart.value, revenueChart: revenueChart.value })
          return
        }
        if (!revenueChart.value) {
          console.error('Revenue chart element not found')
          console.log('Available refs:', { appointmentsChart: appointmentsChart.value, revenueChart: revenueChart.value })
          return
        }

        // Destroy existing charts if they exist
        if (appointmentsChartInstance.value) {
          try {
            appointmentsChartInstance.value.destroy()
          } catch (e) {
            console.warn('Error destroying appointments chart:', e)
          }
          appointmentsChartInstance.value = null // Nullificar después de destruir
        }
        if (revenueChartInstance.value) {
          try {
            revenueChartInstance.value.destroy()
          } catch (e) {
            console.warn('Error destroying revenue chart:', e)
          }
          revenueChartInstance.value = null // Nullificar después de destruir
        }

        // Load Chart.js dynamically
        console.log('Loading Chart.js...')
        const { Chart, registerables } = await import('chart.js')
        Chart.register(...registerables)
        console.log('Chart.js loaded successfully')

        // Verificar que el canvas esté disponible antes de crear chart
        if (!appointmentsChart.value || !appointmentsChart.value.getContext) {
          console.error('Appointments chart canvas not available')
          return
        }

        // Appointments Chart
        if (dashboardData.value.appointmentsByDay && dashboardData.value.appointmentsByDay.length > 0) {
          console.log('Creating appointments chart with data:', dashboardData.value.appointmentsByDay)
          try {
            const appointmentsCtx = appointmentsChart.value.getContext('2d')
            if (!appointmentsCtx) {
              console.error('Appointments chart 2D context is null')
            } else {
              appointmentsChartInstance.value = new Chart(appointmentsCtx, {
            type: 'line',
            data: {
              labels: dashboardData.value.appointmentsByDay.map(item => item.date),
              datasets: [{
                label: 'Citas',
                data: dashboardData.value.appointmentsByDay.map(item => item.count),
                borderColor: 'rgb(99, 102, 241)',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                tension: 0.4
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                y: {
                  beginAtZero: true
                }
              }
            }
              })
            }
          } catch (error) {
            console.error('Error creating appointments chart:', error)
          }
        } else {
          console.log('Skipping appointments chart - no data or element')
        }

        // Verificar que el canvas esté disponible antes de crear chart
        if (!revenueChart.value || !revenueChart.value.getContext) {
          console.error('Revenue chart canvas not available')
          return
        }

        // Revenue Chart
        if (dashboardData.value.revenueByMonth && dashboardData.value.revenueByMonth.length > 0) {
          console.log('Creating revenue chart with data:', dashboardData.value.revenueByMonth)
          try {
            const revenueCtx = revenueChart.value.getContext('2d')
            if (!revenueCtx) {
              console.error('Revenue chart 2D context is null')
            } else {
              revenueChartInstance.value = new Chart(revenueCtx, {
            type: 'bar',
            data: {
              labels: dashboardData.value.revenueByMonth.map(item => item.month),
              datasets: [{
                label: 'Ingresos (S/)',
                data: dashboardData.value.revenueByMonth.map(item => item.revenue),
                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                borderColor: 'rgb(34, 197, 94)',
                borderWidth: 1
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                y: {
                  beginAtZero: true
                }
              }
            }
              })
            }
          } catch (error) {
            console.error('Error creating revenue chart:', error)
          }
        } else {
          console.log('Skipping revenue chart - no data or element')
        }
      } catch (error) {
        console.error('Error creating charts:', error)
        // Continue without charts if Chart.js fails to load
      }
    }

    const applyFilters = async () => {
      await loadReport()
      // Recreate charts after loading new data
      await nextTick()
      setTimeout(async () => {
        await createCharts()
      }, 100)
    }

    const resetFilters = async () => {
      // Reset to default date range
      const today = new Date()
      const startDate = new Date()
      const endDate = new Date()
      startDate.setDate(today.getDate() - 30)
      endDate.setDate(today.getDate() + 60)

      filters.startDate = startDate.toISOString().split('T')[0]
      filters.endDate = endDate.toISOString().split('T')[0]
      filters.professionalId = ''
      filters.environmentId = ''

      await loadReport()
      // Recreate charts after loading new data
      await nextTick()
      setTimeout(async () => {
        await createCharts()
      }, 100)
    }

    const exportReport = async (format) => {
      try {
        const token = localStorage.getItem('auth_token')
        const params = new URLSearchParams({
          format: format,
          start_date: filters.startDate,
          end_date: filters.endDate,
          professional_id: filters.professionalId || '',
          environment_id: filters.environmentId || ''
        })

        const response = await fetch(`/api/reports/${selectedReport.value}/export?${params}`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        })

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }

        // Get filename from Content-Disposition header
        const contentDisposition = response.headers.get('Content-Disposition')
        let filename = `${selectedReport.value}_${new Date().toISOString().split('T')[0]}.${format}`

        if (contentDisposition) {
          const filenameMatch = contentDisposition.match(/filename="(.+)"/)
          if (filenameMatch) {
            filename = filenameMatch[1]
          }
        }

        // Create download link
        const blob = await response.blob()
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = filename
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
      } catch (error) {
        console.error('Error exporting report:', error)
        const { handleError } = useErrorHandler()
        handleError(error, 'Error al exportar el reporte')
      }
    }

    const getMimeType = (format) => {
      const mimeTypes = {
        excel: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        csv: 'text/csv',
        pdf: 'application/pdf'
      }
      return mimeTypes[format] || 'application/octet-stream'
    }

    const getReportTitle = () => {
      const titles = {
        appointments: 'Reporte de Citas',
        patients: 'Reporte de Pacientes',
        professionals: 'Reporte de Profesionales',
        revenue: 'Reporte de Ingresos',
        utilization: 'Utilización de Ambientes'
      }
      return titles[selectedReport.value] || 'Reporte'
    }


    onMounted(async () => {
      console.log('Component mounted, user:', user)
      console.log('Component mounted, user.value:', user?.value)

      // Check if user exists in localStorage as fallback
      const localUser = localStorage.getItem('user')
      console.log('Local user:', localUser)

      if (!user || !user.value) {
        if (!localUser) {
          console.log('No user found, redirecting to login')
          router.push('/login')
          return
        }
        console.log('Using local user, loading initial data')
      } else {
        console.log('User found, loading initial data')
      }

      await loadInitialData()

      // Suscribirse a canales WebSocket para actualizaciones en tiempo real
      try {
        dashboardChannel = channel('dashboard-updates')
        if (dashboardChannel) {
          dashboardChannel
            .listen('.dashboard.stats-updated', async (e) => {
              console.log('Dashboard stats updated via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.appointment.created', async (e) => {
              console.log('Appointment created via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.appointment.updated', async (e) => {
              console.log('Appointment updated via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.appointment.deleted', async (e) => {
              console.log('Appointment deleted via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.patient.created', async (e) => {
              console.log('Patient created via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.patient.updated', async (e) => {
              console.log('Patient updated via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.patient.deleted', async (e) => {
              console.log('Patient deleted via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.user.created', async (e) => {
              console.log('User created via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.user.updated', async (e) => {
              console.log('User updated via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.transaction.created', async (e) => {
              console.log('Transaction created via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
            .listen('.transaction.updated', async (e) => {
              console.log('Transaction updated via WebSocket', e)
              if (selectedReport.value === 'dashboard') {
                await loadDashboardData()
              }
            })
        }
      } catch (error) {
        console.error('Error setting up WebSocket subscriptions:', error)
      }
    })

    onUnmounted(() => {
      // Destruir charts antes de desmontar
      if (appointmentsChartInstance.value) {
        try {
          appointmentsChartInstance.value.destroy()
        } catch (e) {
          console.warn('Error destroying appointments chart on unmount:', e)
        }
        appointmentsChartInstance.value = null
      }
      if (revenueChartInstance.value) {
        try {
          revenueChartInstance.value.destroy()
        } catch (e) {
          console.warn('Error destroying revenue chart on unmount:', e)
        }
        revenueChartInstance.value = null
      }

      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('dashboard-updates')
        } catch (e) {
          console.error('Error leaving dashboard channel:', e)
        }
      }
    })

    return {
      user,
      loading,
      selectedReport,
      professionals,
      environments,
      dashboardData,
      reportData,
      reportColumns,
      filters,
      appointmentsChart,
      revenueChart,
      loadReport,
      applyFilters,
      resetFilters,
      exportReport,
      getReportTitle
    }
  }
}
</script>

<style scoped>
/* Estilos adicionales para el diseño iOS/iCloud */
.shadow-xl {
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.transition-colors {
  transition-property: color, background-color, border-color;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 200ms;
}
</style>
