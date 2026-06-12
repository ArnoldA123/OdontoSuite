<template>
  <AppLayout>
    <PageHeader
      :title="professional?.name || 'Cargando...'"
      :subtitle="professional ? `ID: ${professional.id} | ${professional.email || ''} | ${professional.phone || ''}` : ''"
      :breadcrumbs="[{ to: '/professionals', label: 'Profesionales' }]"
      class="mb-6"
    >
      <template #actions>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </template>
          Volver
        </UiButton>
      </template>
    </PageHeader>

    <!-- Professional Info Card -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex items-center gap-4">
        <div class="h-16 w-16 rounded-xl bg-gradient-accent flex items-center justify-center">
          <span class="text-xl font-semibold text-white">
            {{ professional?.name?.charAt(0) }}
          </span>
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-theme-primary">
            {{ professional?.name }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2 text-sm text-theme-secondary">
            <div>
              <span class="font-medium">Email:</span> {{ professional?.email }}
            </div>
            <div>
              <span class="font-medium">Teléfono:</span> {{ professional?.phone }}
            </div>
            <div>
              <span class="font-medium">Especialidad:</span> {{ getSpecialtyText(professional?.specialty) }}
            </div>
          </div>
        </div>
        <div class="text-right">
          <UiBadge :variant="professional?.is_active ? 'success' : 'error'">
            {{ professional?.is_active ? 'Activo' : 'Inactivo' }}
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
      <!-- Datos del Profesional -->
      <div v-if="activeTab === 'data'" class="space-y-6">
        <UiCard variant="glass">
          <h3 class="text-lg font-semibold text-theme-primary mb-4">Información Personal</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Nombre Completo</label>
              <p class="text-theme-primary">{{ professional?.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Usuario</label>
              <p class="text-theme-primary">{{ professional?.username }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Email</label>
              <p class="text-theme-primary">{{ professional?.email }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Teléfono</label>
              <p class="text-theme-primary">{{ professional?.phone || 'No especificado' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Especialidad</label>
              <p class="text-theme-primary">{{ getSpecialtyText(professional?.specialty) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Rol</label>
              <p class="text-theme-primary">{{ getRoleText(professional?.role) }}</p>
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
            <p class="text-theme-secondary">Este profesional no tiene registros de auditoría.</p>
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
                  <div v-else-if="log.action === 'user_created'" class="mt-2 text-sm text-theme-secondary">
                    Profesional creado en el sistema.
                  </div>
                  <div v-else-if="log.action === 'user_deleted'" class="mt-2 text-sm text-theme-secondary">
                    Profesional eliminado del sistema.
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
  name: 'ProfessionalDetailPage',
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
      getUserAuditLogs, 
      formatAction, 
      getChangesSummary 
    } = useAuditLogs()

    // State
    const professional = ref(null)
    const activeTab = ref('data')

    // Icon components
    const UserIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
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
        icon: UserIcon
      },
      {
        id: 'audit',
        name: 'Historial',
        icon: ClockIcon
      }
    ]

    // Methods
    const loadProfessional = async () => {
      try {
        const response = await get(`/api/users/${route.params.id}`)
        professional.value = response.data
      } catch (error) {
        toast.error('Error al cargar el profesional')
      }
    }

    const loadAuditLogs = async () => {
      if (!professional.value) return
      try {
        await getUserAuditLogs(professional.value.id)
      } catch (error) {
      }
    }

    const getSpecialtyText = (specialty) => {
      const specialties = {
        general: 'Odontología General',
        orthodontics: 'Ortodoncia',
        endodontics: 'Endodoncia',
        periodontics: 'Periodoncia',
        oral_surgery: 'Cirugía Oral',
        pediatric: 'Odontopediatría',
        prosthodontics: 'Prótesis Dental',
        cosmetic: 'Odontología Estética'
      }
      return specialties[specialty] || specialty || 'No especificada'
    }

    const getRoleText = (role) => {
      const roles = {
        admin: 'Administrador',
        administrador: 'Administrador',
        recepcion: 'Recepcionista',
        recepcionista: 'Recepcionista',
        odontologo: 'Odontólogo'
      }
      return roles[role] || role || 'No especificado'
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
      router.push('/professionals')
    }

    // Watch for tab changes
    watch(activeTab, (newTab) => {
      if (newTab === 'audit' && professional.value) {
        loadAuditLogs()
      }
    })

    // Lifecycle
    onMounted(async () => {
      await loadProfessional()
      if (activeTab.value === 'audit' && professional.value) {
        loadAuditLogs()
      }
    })

    return {
      professional,
      activeTab,
      tabs,
      auditLogsLoading,
      auditLogs,
      loadProfessional,
      loadAuditLogs,
      getSpecialtyText,
      getRoleText,
      formatDate,
      formatAction,
      getChangesSummary,
      getAuditActionVariant,
      goBack
    }
  }
}
</script>

