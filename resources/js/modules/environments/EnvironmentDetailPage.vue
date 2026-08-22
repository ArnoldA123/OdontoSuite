<template>
  <AppLayout>
    <PageHeader
      :title="environment?.name || 'Cargando...'"
      :subtitle="environment ? `ID: ${environment.id} | Código: ${environment.code}` : ''"
      :breadcrumbs="[{ to: '/environments', label: 'Ambientes' }]"
      class="bg-canvas mb-6"
    >
      <template #actions>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
          </template>
          Volver
        </UiButton>
      </template>
    </PageHeader>

    <!-- Environment Info Card -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex items-center gap-4">
        <!-- PR-ambientes-02 / AMB-02-004 — the header avatar was a forbidden
             gradient utility; replaced with a flat systemBlue ramp + token
             radius (mirrors `PatientsPage` row-avatar precedent). -->
        <div class="h-16 w-16 rounded-[var(--radius-card-lg)] bg-systemBlue-50 flex items-center justify-center">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
            />
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-theme-primary">
            {{ environment?.name }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2 text-sm text-theme-secondary">
            <div>
              <span class="font-medium">Código:</span>
              {{ environment?.code }}
            </div>
            <div>
              <span class="font-medium">Estado:</span>
              {{ getStatusText(environment?.status) }}
            </div>
            <div>
              <span class="font-medium">Activo:</span>
              {{ environment?.is_active ? 'Sí' : 'No' }}
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

    <!-- Tabs Navigation (PR-ambientes-02 / AMB-02-003) — `<UiTabs>` primitive.
         The active indicator + tab labels + click handlers stay byte-for-byte;
         the hand-rolled step strip + per-button legacy active indicator
         are gone. The `tab.label` field name is the canonical UiTabs contract
         (`Tabs.vue` validator requires `id` + `label`); the display strings
         ("Datos" / "Historial") are preserved byte-for-byte. -->
    <div class="mb-6">
      <UiTabs v-model="activeTab" :tabs="tabs" />
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
              <p class="text-theme-primary">
                {{ environment?.name }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Código</label>
              <p class="text-theme-primary">
                {{ environment?.code }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
              <p class="text-theme-primary">
                {{ getStatusText(environment?.status) }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Activo</label>
              <p class="text-theme-primary">
                {{ environment?.is_active ? 'Sí' : 'No' }}
              </p>
            </div>
            <div v-if="environment?.description" class="md:col-span-2">
              <label class="block text-sm font-medium text-theme-primary mb-1">Descripción</label>
              <p class="text-theme-primary">
                {{ environment?.description }}
              </p>
            </div>
            <div v-if="environment?.equipment" class="md:col-span-2">
              <label class="block text-sm font-medium text-theme-primary mb-1">Equipamiento</label>
              <p class="text-theme-primary">
                {{ environment?.equipment }}
              </p>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Historial de Auditoría -->
      <div v-if="activeTab === 'audit'" class="space-y-6">
        <UiCard variant="glass">
          <h3 class="text-lg font-semibold text-theme-primary mb-4">Historial de Auditoría</h3>
          <div v-if="auditLogsLoading" class="p-8 text-center">
            <div
              class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600"
            />
            <p class="mt-2 text-theme-secondary">Cargando historial de auditoría...</p>
          </div>
          <div v-else-if="auditLogs.length === 0">
            <UiEmptyState
              title="No hay historial de auditoría"
              description="Los cambios en este ambiente aparecerán aquí."
            />
          </div>
          <div v-else class="space-y-4">
            <!-- PR-ambientes-02 / AMB-02-006 — hand-rolled audit-item wrapper
                 replaced with `<UiCard variant="glass">` so the audit log item
                 consumes the canonical card primitive + token hover chrome. -->
            <UiCard
              v-for="log in auditLogs"
              :key="log.id"
              variant="glass"
            >
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <UiBadge :variant="getAuditActionVariant(log.action)">
                      {{ formatAction(log.action) }}
                    </UiBadge>
                    <span class="text-sm text-theme-secondary">
                      por {{ log.user?.name || 'Sistema' }}
                    </span>
                  </div>
                  <p class="text-sm text-theme-secondary mb-2">
                    {{ formatDate(log.created_at) }}
                  </p>
                  <div v-if="log.old_values && log.new_values" class="mt-2 text-sm">
                    <p class="font-medium text-theme-primary mb-1">Cambios realizados:</p>
                    <div
                      v-if="
                        getChangesSummary(log) && Object.keys(getChangesSummary(log)).length > 0
                      "
                      class="text-theme-secondary space-y-1"
                    >
                      <div
                        v-for="(change, field) in getChangesSummary(log)"
                        :key="field"
                        class="pl-2 border-l-2 border-[color:var(--color-hairline)]"
                      >
                        <p class="font-medium text-theme-primary">{{ change.field }}:</p>
                        <p class="text-xs">
                          De:
                          <!-- PR-ambientes-02 / AMB-02-008 — `text-red-500` replaced
                               with the tokenised `text-systemRed-600` ramp (the
                               canvas-readable shade per the design system). -->
                          <span class="text-systemRed-600">{{ change.old }}</span>
                        </p>
                        <p class="text-xs">
                          A:
                          <span class="text-systemGreen-600">{{ change.new }}</span>
                        </p>
                      </div>
                    </div>
                    <div v-else class="text-theme-secondary italic">
Sin cambios registrados
</div>
                  </div>
                  <div
                    v-else-if="log.action === 'dental_chair_created'"
                    class="mt-2 text-sm text-theme-secondary"
                  >
                    Ambiente creado en el sistema.
                  </div>
                  <div
                    v-else-if="log.action === 'dental_chair_deleted'"
                    class="mt-2 text-sm text-theme-secondary"
                  >
                    Ambiente eliminado del sistema.
                  </div>
                </div>
              </div>
            </UiCard>
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
import UiTabs from '../../components/ui/Tabs.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'

export default {
  name: 'EnvironmentDetailPage',
  components: {
    AppLayout,
    UiCard,
    UiButton,
    UiBadge,
    UiTabs,
    UiEmptyState
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

    // Tabs configuration (PR-ambientes-02 / AMB-02-003): the `name` field
    // was renamed to `label` to satisfy `<UiTabs>`'s data contract
    // (`Tabs.vue` validator: `id` + `label` are required; display strings
    // "Datos" / "Historial" are preserved byte-for-byte).
    const tabs = [
      {
        id: 'data',
        label: 'Datos',
        icon: BuildingIcon
      },
      {
        id: 'audit',
        label: 'Historial',
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
      } catch (error) {}
    }

    const getStatusText = status => {
      const statuses = {
        active: 'Activo',
        inactive: 'Inactivo',
        maintenance: 'Mantenimiento'
      }
      return statuses[status] || status || 'No especificado'
    }

    const formatDate = date => {
      if (!date) return 'No especificada'
      try {
        return new Date(date).toLocaleDateString('es-ES')
      } catch {
        return date
      }
    }

    const getAuditActionVariant = action => {
      if (action.includes('created')) return 'success'
      if (action.includes('updated')) return 'warning'
      if (action.includes('deleted')) return 'error'
      return 'neutral'
    }

    const goBack = () => {
      router.push('/environments')
    }

    // Watch for tab changes
    watch(activeTab, newTab => {
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
