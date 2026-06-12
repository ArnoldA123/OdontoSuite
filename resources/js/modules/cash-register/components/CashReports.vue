<template>
  <div class="cash-reports">
    <!-- Filtros de Reporte -->
    <div class="mb-6">
      <div class="bg-theme-surface-elevated p-6 rounded-lg shadow-sm border border-theme">
        <h3 class="text-lg font-semibold text-theme-primary mb-4">Filtros de Reporte</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-theme-primary mb-1">Fecha Desde</label>
            <input
              v-model="filters.date_from"
              type="date"
              class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-theme-primary mb-1">Fecha Hasta</label>
            <input
              v-model="filters.date_to"
              type="date"
              class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-theme-primary mb-1">Tipo de Reporte</label>
            <select
              v-model="filters.report_type"
              class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm"
            >
              <option value="daily">Reporte Diario</option>
              <option value="period">Reporte por Período</option>
              <option value="summary">Resumen Ejecutivo</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end mt-4">
          <Button
            variant="primary"
            @click="generateReport"
            :loading="loading"
          >
            <ChartBarIcon class="w-4 h-4 mr-2" />
            Generar Reporte
          </Button>
        </div>
      </div>
    </div>

    <!-- Resumen de Caja -->
    <div v-if="summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div class="bg-theme-surface-elevated p-6 rounded-lg shadow-sm border border-theme">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
              <BanknotesIcon class="w-5 h-5 text-accent" />
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-theme-secondary">Sesiones</p>
            <p class="text-2xl font-bold text-theme-primary">{{ summary.sessions_count || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="bg-theme-surface-elevated p-6 rounded-lg shadow-sm border border-theme">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
              <ArrowUpIcon class="w-5 h-5 text-green-600" />
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-theme-secondary">Total Ingresos</p>
            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(summary.total_income) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-theme-surface-elevated p-6 rounded-lg shadow-sm border border-theme">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
              <ArrowDownIcon class="w-5 h-5 text-red-600" />
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-theme-secondary">Total Egresos</p>
            <p class="text-2xl font-bold text-red-600">{{ formatCurrency(summary.total_expenses) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-theme-surface-elevated p-6 rounded-lg shadow-sm border border-theme">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-8 h-8 bg-primary-50 rounded-lg flex items-center justify-center">
              <ExclamationTriangleIcon class="w-5 h-5 text-accent" />
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-theme-secondary">Diferencias</p>
            <p class="text-2xl font-bold text-accent">{{ formatCurrency(summary.total_difference) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Gráficos -->
    <div v-if="summary" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Gráfico por Método de Pago -->
      <div class="bg-theme-surface-elevated p-6 rounded-lg shadow-sm border border-theme">
        <h3 class="text-lg font-semibold text-theme-primary mb-4">Ingresos por Método de Pago</h3>
        <div class="space-y-3">
          <div
            v-for="method in summary.by_payment_method"
            :key="method.payment_method"
            class="flex items-center justify-between"
          >
            <div class="flex items-center">
              <div class="w-3 h-3 rounded-full bg-primary-500 mr-3"></div>
              <span class="text-sm font-medium text-theme-primary">{{ method.payment_method }}</span>
            </div>
            <div class="text-right">
              <div class="text-sm font-semibold text-theme-primary">{{ formatCurrency(method.total) }}</div>
              <div class="text-xs text-theme-secondary">{{ method.count }} transacciones</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Gráfico por Hora -->
      <div class="bg-theme-surface-elevated p-6 rounded-lg shadow-sm border border-theme">
        <h3 class="text-lg font-semibold text-theme-primary mb-4">Ingresos por Hora</h3>
        <div class="space-y-2">
          <div
            v-for="hour in summary.by_hour"
            :key="hour.hour"
            class="flex items-center justify-between"
          >
            <span class="text-sm text-theme-secondary">{{ hour.hour }}:00</span>
            <div class="flex items-center">
              <div class="w-32 bg-theme-surface rounded-full mr-3">
                <div
                  class="bg-primary-500 h-2 rounded-full"
                  :style="{ width: getPercentage(hour.total, summary.by_hour) + '%' }"
                ></div>
              </div>
              <span class="text-sm font-medium text-theme-primary">{{ formatCurrency(hour.total) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla de Sesiones -->
    <div v-if="summary && summary.sessions" class="bg-theme-surface-elevated shadow-sm rounded-lg overflow-hidden">
      <div class="px-6 py-4 border-b border-theme">
        <h3 class="text-lg font-semibold text-theme-primary">Sesiones del Período</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-theme">
          <thead class="bg-theme-surface">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Sesión
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Usuario
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Apertura
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Cierre
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Diferencia
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Estado
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-theme">
            <tr
              v-for="session in summary.sessions"
              :key="session.id"
              class="hover:bg-theme-surface"
            >
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-theme-primary">
                #{{ session.id }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ session.user?.name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ formatCurrency(session.opening_amount) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ formatCurrency(session.closing_amount) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="text-sm font-medium"
                  :class="session.difference_amount === 0 ? 'text-green-600' :
                         session.difference_amount > 0 ? 'text-accent' : 'text-red-600'"
                >
                  {{ session.difference_amount === 0 ? 'Conforme' :
                     session.difference_amount > 0 ? '+' : '' }}{{ formatCurrency(session.difference_amount) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="session.status === 'open' ? 'bg-green-100 text-green-800' : 'bg-theme-surface text-theme-secondary'"
                >
                  {{ session.status === 'open' ? 'Abierta' : 'Cerrada' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Acciones de Exportación -->
    <div class="mt-6 flex justify-end space-x-3">
      <Button
        variant="secondary"
        @click="exportToExcel"
        :loading="exporting"
      >
        <DocumentArrowDownIcon class="w-4 h-4 mr-2" />
        Exportar Excel
      </Button>

      <Button
        variant="secondary"
        @click="exportToPDF"
        :loading="exporting"
      >
        <DocumentArrowDownIcon class="w-4 h-4 mr-2" />
        Exportar PDF
      </Button>

      <Button
        variant="primary"
        @click="printReport"
        :loading="exporting"
      >
        <PrinterIcon class="w-4 h-4 mr-2" />
        Imprimir
      </Button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Button from '@/components/ui/Button.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import {
  ChartBarIcon,
  BanknotesIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  ExclamationTriangleIcon,
  DocumentArrowDownIcon,
  PrinterIcon
} from '@heroicons/vue/24/outline'

const { post } = useApi()
const toast = useToast()

const props = defineProps({
  summary: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['export'])

// Estado
const filters = ref({
  date_from: new Date().toISOString().split('T')[0],
  date_to: new Date().toISOString().split('T')[0],
  report_type: 'daily'
})

const exporting = ref(false)

// Métodos
const generateReport = () => {
  emit('export', filters.value)
}

const exportToExcel = async () => {
  exporting.value = true
  try {
    const token = localStorage.getItem('auth_token')
    const response = await fetch('/api/cash-register/reports/export/excel', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      },
      body: JSON.stringify({
        start_date: filters.value.date_from,
        end_date: filters.value.date_to,
        report_type: filters.value.report_type
      })
    })

    if (!response.ok) {
      throw new Error('Error al exportar reporte')
    }

    // Descargar archivo
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `reporte-caja-${filters.value.date_from}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    toast.success('Reporte exportado a Excel exitosamente')
  } catch (error) {
    toast.error('Error al exportar reporte a Excel')
  } finally {
    exporting.value = false
  }
}

const exportToPDF = async () => {
  exporting.value = true
  try {
    const token = localStorage.getItem('auth_token')
    const response = await fetch('/api/cash-register/reports/export/pdf', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/pdf'
      },
      body: JSON.stringify({
        start_date: filters.value.date_from,
        end_date: filters.value.date_to,
        report_type: filters.value.report_type
      })
    })

    if (!response.ok) {
      throw new Error('Error al exportar reporte')
    }

    // Descargar archivo
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `reporte-caja-${filters.value.date_from}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    toast.success('Reporte exportado a PDF exitosamente')
  } catch (error) {
    toast.error('Error al exportar reporte a PDF')
  } finally {
    exporting.value = false
  }
}

const printReport = () => {
  window.print()
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount || 0)
}

const getPercentage = (value, array) => {
  const max = Math.max(...array.map(item => item.total))
  return max > 0 ? (value / max) * 100 : 0
}
</script>
