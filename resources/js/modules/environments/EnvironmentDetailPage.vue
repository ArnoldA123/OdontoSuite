<template>
  <AppLayout>
    <!-- Header Section -->
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">
            {{ environment?.name }}
          </h1>
          <p class="text-theme-secondary">
            ID: {{ environment?.id }} | Código: {{ environment?.code }}
          </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
          <UiButton
            variant="secondary"
            @click="goBack"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver
          </UiButton>
        </div>
      </div>
    </div>

    <!-- Environment Info Card -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex items-center gap-4">
        <div class="h-16 w-16 rounded-xl bg-gradient-accent flex items-center justify-center">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-theme-primary">
            {{ environment?.name }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2 text-sm text-theme-secondary">
            <div>
              <span class="font-medium">Código:</span> {{ environment?.code }}
            </div>
            <div>
              <span class="font-medium">Estado:</span> {{ getStatusText(environment?.status) }}
            </div>
            <div>
              <span class="font-medium">Activo:</span> {{ environment?.is_active ? 'Sí' : 'No' }}
            </div>
          </div>
        </div>
        <div class="text-right">
          <UiBadge :variant="environment?.is_active ? 'success' : 'error'">
            {{ environment?.is_active ? 'Activo' : 'Inactivo' }}
          </UiBadge>
        </div>
      </div>
    </UiCard>

    <!-- Tabs Navigation -->
    <div class="mb-6">
      <nav class="flex space-x-8 border-b border-theme">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
            activeTab === tab.id
              ? 'border-accent text-accent'
              : 'border-transparent text-theme-secondary hover:text-theme-primary hover:border-theme'
          ]"
        >
          <component :is="tab.icon" class="w-4 h-4 inline mr-2" />
          {{ tab.name }}
        </button>
      </nav>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
      <!-- Datos del Ambiente -->
      <div v-if="activeTab === 'data'" class="space-y-6">
        <UiCard variant="glass">
          <h3 class="text-lg font-semibold text-theme-primary mb-4">Información del Ambiente</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Nombre</label>
              <p class="text-theme-primary">{{ environment?.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Código</label>
              <p class="text-theme-primary">{{ environment?.code }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
              <p class="text-theme-primary">{{ getStatusText(environment?.status) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Activo</label>
              <p class="text-theme-primary">{{ environment?.is_active ? 'Sí' : 'No' }}</p>
            </div>
            <div v-if="environment?.description" class="md:col-span-2">
              <label class="block text-sm font-medium text-theme-primary mb-1">Descripción</label>
              <p class="text-theme-primary">{{ environment?.description }}</p>
            </div>
            <div v-if="environment?.equipment" class="md:col-span-2">
              <label class="block text-sm font-medium text-theme-primary mb-1">Equipamiento</label>
              <p class="text-theme-primary">{{ environment?.equipment }}</p>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Historial de Auditoría -->
      <div v-if="activeTab === 'audit'" class="space-y-6">
        <UiCard variant="glass">
          <h3 class="text-lg font-semibold text-theme-primary mb-4">Historial de Auditoría</h3>
          <div v-if="auditLogsLoading" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600"></div>
            <p class="mt-2 text-theme-secondary">Cargando historial de auditoría...</p>
          </div>
          <div v-else-if="auditLogs.length === 0" class="p-8 text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-theme-surface to-theme-surface-elevated rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <svg class="w-8 h-8 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-theme-primary mb-2">No hay historial de auditoría</h3>
            <p class="text-theme-secondary">Este ambiente no tiene registros de auditoría.</p>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="log in auditLogs"
              :key="log.id"
              class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
            >
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <UiBadge :variant="getAuditActionVariant(log.action)">
                      {{ formatAction(log.action) }}
                    </UiBadge>
                    <span class="text-sm text-theme-secondary">por {{ log.user?.name || 'Sistema' }}</span>
                  </div>
                  <p class="text-sm text-theme-secondary mb-2">{{ formatDate(log.created_at) }}</p>
                  <div v-if="log.old_values && log.new_values" class="mt-2 text-sm">
                    <p class="font-medium text-theme-primary mb-1">Cambios realizados:</p>
                    <div v-if="getChangesSummary(log) && Object.keys(getChangesSummary(log)).length > 0" class="text-theme-secondary space-y-1">
                      <div v-for="(change, field) in getChangesSummary(log)" :key="field" class="pl-2 border-l-2 border-theme">
                        <p class="font-medium text-theme-primary">{{ change.field }}:</p>
                        <p class="text-xs">De: <span class="text-red-500">{{ change.old }}</span></p>
                        <p class="text-xs">A: <span class="text-green-500">{{ change.new }}</span></p>
                      </div>
                    </div>
                    <div v-else class="text-theme-secondary italic">
                      Sin cambios registrados
                    </div>
                  </div>
                  <div v-else-if="log.action === 'dental_chair_created'" class="mt-2 text-sm text-theme-secondary">
                    Ambiente creado en el sistema.
                  </div>
                  <div v-else-if="log.action === 'dental_chair_deleted'" class="mt-2 text-sm text-theme-secondary">
                    Ambiente eliminado del sistema.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </UiCard>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useAuditLogs } from '../../composables/useAuditLogs'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import UiBadge from '../../components/ui/Badge.vue'

export default {
  name: 'EnvironmentDetailPage',
  components: {
    AppLayout,
    UiCard,
    UiButton,
    UiBadge
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { get } = useApi()
    const toast = useToast()
    const { 
      loading: auditLogsLoading, 
      auditLogs, 
      getDentalChairAuditLogs, 
      formatAction, 
      getChangesSummary 
    } = useAuditLogs()

    // State
    const environment = ref(null)
    const activeTab = ref('data')

    // Icon components
    const BuildingIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
      `
    }

    const ClockIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      `
    }

    // Tabs configuration
    const tabs = [
      {
        id: 'data',
        name: 'Datos',
        icon: BuildingIcon
      },
      {
        id: 'audit',
        name: 'Historial',
        icon: ClockIcon
      }
    ]

    // Methods
    const loadEnvironment = async () => {
      try {
        const response = await get(`/api/dental-chairs/${route.params.id}`)
        environment.value = response.data
      } catch (error) {
        toast.error('Error al cargar el ambiente')
      }
    }

    const loadAuditLogs = async () => {
      if (!environment.value) return
      try {
        await getDentalChairAuditLogs(environment.value.id)
      } catch (error) {
      }
    }

    const getStatusText = (status) => {
      const statuses = {
        active: 'Activo',
        inactive: 'Inactivo',
        maintenance: 'Mantenimiento'
      }
      return statuses[status] || status || 'No especificado'
    }

    const formatDate = (date) => {
      if (!date) return 'No especificada'
      try {
        return new Date(date).toLocaleDateString('es-ES')
      } catch {
        return date
      }
    }

    const getAuditActionVariant = (action) => {
      if (action.includes('created')) return 'success'
      if (action.includes('updated')) return 'warning'
      if (action.includes('deleted')) return 'error'
      return 'secondary'
    }

    const goBack = () => {
      router.push('/environments')
    }

    // Watch for tab changes
    watch(activeTab, (newTab) => {
      if (newTab === 'audit' && environment.value) {
        loadAuditLogs()
      }
    })

    // Lifecycle
    onMounted(async () => {
      await loadEnvironment()
      if (activeTab.value === 'audit' && environment.value) {
        loadAuditLogs()
      }
    })

    return {
      environment,
      activeTab,
      tabs,
      auditLogsLoading,
      auditLogs,
      loadEnvironment,
      loadAuditLogs,
      getStatusText,
      formatDate,
      formatAction,
      getChangesSummary,
      getAuditActionVariant,
      goBack
    }
  }
}
</script>

