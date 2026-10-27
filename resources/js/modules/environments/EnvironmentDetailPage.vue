<template>
  <AppLayout>
    <PageHeader
      :title="environment?.name || 'Detalle del ambiente'"
      :subtitle="environment ? `Código: ${environment.code}` : ''"
      :breadcrumbs="[{ to: '/environments', label: 'Ambientes' }]"
      class="bg-canvas mb-6"
    >
      <template #actions>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Volver
        </UiButton>
      </template>
    </PageHeader>

    <UiCard
      v-if="loading"
      variant="glass"
      padding="sm"
      :style="panelStyle"
      data-environment-detail-loading
    >
      <UiSkeleton variant="card" animation="wave" aria-label="Cargando ambiente..." />
    </UiCard>

    <div
      v-else-if="loadError"
      data-environment-detail-error
      class="flex items-center justify-between flex-wrap gap-4 rounded-[var(--radius-panel)] p-5 bg-systemRed-50"
      style="border: 1px solid var(--color-hairline)"
    >
      <div class="flex items-center gap-3 min-w-0">
        <ExclamationTriangleIcon
          class="flex-shrink-0 w-6 h-6 text-systemRed-600"
          aria-hidden="true"
        />
        <div class="min-w-0">
          <p class="text-sm font-medium text-theme-primary">No pudimos cargar el ambiente</p>
          <p class="text-sm text-theme-secondary">{{ detailErrorMessage }}</p>
        </div>
      </div>
      <UiButton variant="primary" size="sm" @click="loadEnvironment">Reintentar</UiButton>
    </div>

    <template v-else-if="environment">
      <!-- Summary card: flat systemBlue avatar (AMB-02-004) with the tint's
           foreground, shared status vocabulary badge, hairline definition rows. -->
      <UiCard
        variant="glass"
        padding="sm"
        class="mb-6"
        :style="panelStyle"
        data-environment-summary
      >
        <div class="flex items-start gap-4">
          <div
            class="h-16 w-16 rounded-[var(--radius-card-lg)] bg-systemBlue-50 flex items-center justify-center"
          >
            <BuildingOffice2Icon class="w-8 h-8 text-systemBlue-700" aria-hidden="true" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
              <h2 class="text-lg font-semibold text-theme-primary">{{ environment.name }}</h2>
              <UiStatusBadge
                :variant="getStatusVariant(environment.status)"
                :label="getStatusText(environment.status)"
              />
            </div>
            <p class="mt-1 text-sm text-theme-secondary">Código: {{ environment.code }}</p>
            <dl class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-x-6">
              <div class="border-t border-[color:var(--color-hairline)] py-3">
                <dt class="text-xs font-medium text-theme-secondary">Equipamiento</dt>
                <dd class="mt-1 text-sm text-theme-primary">
                  {{ environment.equipment || 'Sin equipamiento' }}
                </dd>
              </div>
              <div class="border-t border-[color:var(--color-hairline)] py-3">
                <dt class="text-xs font-medium text-theme-secondary">Descripción</dt>
                <dd class="mt-1 text-sm text-theme-primary">
                  {{ environment.description || 'Sin descripción' }}
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </UiCard>

      <UiTabs v-model="activeTab" :tabs="tabs">
        <template #data>
          <UiCard variant="glass" padding="sm" :style="panelStyle" data-environment-info>
            <div class="space-y-6">
              <section>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                  Identificación
                </h3>
                <dl class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6">
                  <div class="border-t border-[color:var(--color-hairline)] py-3">
                    <dt class="text-xs font-medium text-theme-secondary">Nombre</dt>
                    <dd class="mt-1 text-sm text-theme-primary">{{ environment.name }}</dd>
                  </div>
                  <div class="border-t border-[color:var(--color-hairline)] py-3">
                    <dt class="text-xs font-medium text-theme-secondary">Código</dt>
                    <dd class="mt-1 text-sm text-theme-primary">{{ environment.code }}</dd>
                  </div>
                </dl>
              </section>
              <section>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                  Operación
                </h3>
                <dl class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6">
                  <div class="border-t border-[color:var(--color-hairline)] py-3">
                    <dt class="text-xs font-medium text-theme-secondary">Estado</dt>
                    <dd class="mt-1">
                      <UiStatusBadge
                        :variant="getStatusVariant(environment.status)"
                        :label="getStatusText(environment.status)"
                      />
                    </dd>
                  </div>
                  <div class="border-t border-[color:var(--color-hairline)] py-3">
                    <dt class="text-xs font-medium text-theme-secondary">Activo</dt>
                    <dd class="mt-1 text-sm text-theme-primary">
                      {{ environment.is_active ? 'Sí' : 'No' }}
                    </dd>
                  </div>
                </dl>
              </section>
              <section>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                  Descripción y equipamiento
                </h3>
                <dl class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6">
                  <div class="border-t border-[color:var(--color-hairline)] py-3">
                    <dt class="text-xs font-medium text-theme-secondary">Descripción</dt>
                    <dd class="mt-1 text-sm text-theme-primary">
                      {{ environment.description || 'Sin descripción' }}
                    </dd>
                  </div>
                  <div class="border-t border-[color:var(--color-hairline)] py-3">
                    <dt class="text-xs font-medium text-theme-secondary">Equipamiento</dt>
                    <dd class="mt-1 text-sm text-theme-primary">
                      {{ environment.equipment || 'Sin equipamiento' }}
                    </dd>
                  </div>
                </dl>
              </section>
            </div>
          </UiCard>
        </template>

        <template #audit>
          <UiCard variant="glass" padding="sm" :style="panelStyle" data-environment-audit>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
              Historial de auditoría
            </h3>

            <div v-if="auditLoading" class="mt-4" data-environment-audit-loading>
              <UiSkeleton
                variant="list"
                animation="wave"
                aria-label="Cargando historial de auditoría..."
              />
            </div>

            <div
              v-else-if="auditError"
              data-environment-audit-error
              class="mt-4 flex items-center justify-between flex-wrap gap-4 rounded-[var(--radius-panel)] p-5 bg-systemRed-50"
              style="border: 1px solid var(--color-hairline)"
            >
              <div class="flex items-center gap-3 min-w-0">
                <ExclamationTriangleIcon
                  class="flex-shrink-0 w-6 h-6 text-systemRed-600"
                  aria-hidden="true"
                />
                <div class="min-w-0">
                  <p class="text-sm font-medium text-theme-primary">
                    No pudimos cargar el historial de auditoría
                  </p>
                  <p class="text-sm text-theme-secondary">
                    {{ auditErrorMessage }}
                  </p>
                </div>
              </div>
              <UiButton variant="primary" size="sm" @click="loadAuditLogs">Reintentar</UiButton>
            </div>

            <div v-else-if="auditLogs.length === 0" class="mt-4" data-environment-audit-empty>
              <UiEmptyState
                title="No hay historial de auditoría"
                description="Los cambios realizados en este ambiente quedarán registrados aquí."
              />
            </div>

            <!-- Flat hairline-divided entries (AMB-02-006 re-anchor): no nested
                 card chrome inside the panel; flat systemBlue avatar per the
                 AMB-02-004 contract. Each entry precomputes its own diff so the
                 summary helper runs once per row (S7). -->
            <ul v-else class="mt-2 divide-y divide-[color:var(--color-hairline)]">
              <li
                v-for="entry in auditEntries"
                :key="entry.log.id"
                class="py-4 flex items-start gap-3"
              >
                <div
                  class="flex-shrink-0 h-10 w-10 rounded-full bg-systemBlue-50 flex items-center justify-center"
                >
                  <span class="text-sm font-medium text-systemBlue-700">
                    {{ (entry.log.user?.name || 'Sistema').charAt(0) }}
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <UiBadge :variant="entry.actionVariant" size="sm">
                      {{ entry.actionLabel }}
                    </UiBadge>
                    <span class="text-sm text-theme-secondary">
                      por {{ entry.log.user?.name || 'Sistema' }}
                    </span>
                    <span class="text-xs text-theme-secondary">
                      {{ formatAuditDate(entry.log.created_at) }}
                    </span>
                  </div>
                  <div v-if="entry.changes.length > 0" class="mt-2 text-sm">
                    <div class="space-y-2">
                      <div
                        v-for="change in entry.changes"
                        :key="change.key"
                        class="pl-2 border-l-2 border-[color:var(--color-hairline)]"
                      >
                        <p class="text-xs font-medium text-theme-primary">{{ change.field }}</p>
                        <p class="text-xs text-theme-secondary">
                          De:
                          <span class="text-systemRed-600">{{ change.old }}</span>
                        </p>
                        <p class="text-xs text-theme-secondary">
                          A:
                          <span class="text-systemGreen-600">{{ change.new }}</span>
                        </p>
                      </div>
                    </div>
                  </div>
                  <p
                    v-else-if="entry.log.action === 'dental_chair_created'"
                    class="mt-2 text-sm text-theme-secondary"
                  >
                    Ambiente creado en el sistema.
                  </p>
                  <p
                    v-else-if="entry.log.action === 'dental_chair_deleted'"
                    class="mt-2 text-sm text-theme-secondary"
                  >
                    Ambiente eliminado del sistema.
                  </p>
                  <p v-else class="mt-2 text-xs text-theme-secondary italic">
                    Sin cambios registrados
                  </p>
                </div>
              </li>
            </ul>
          </UiCard>
        </template>
      </UiTabs>
    </template>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  BuildingOffice2Icon,
  ClockIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import { formatDate } from '../../composables/useFormatters'
import { useApi } from '../../composables/useApi'
import { useAuditLogs } from '../../composables/useAuditLogs'
import { getStatusText, getStatusVariant } from './environmentStatus'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiTabs from '../../components/ui/Tabs.vue'

const route = useRoute()
const router = useRouter()
const { get } = useApi()
const { formatAction, getChangesSummary } = useAuditLogs()

const environment = ref(null)
const loading = ref(false)
const loadError = ref(false)
const detailErrorMessage = ref('')
const auditLogs = ref([])
const auditLoading = ref(false)
const auditError = ref(false)
const auditErrorMessage = ref('')
const activeTab = ref('data')

const tabs = [
  { id: 'data', label: 'Información', icon: BuildingOffice2Icon },
  { id: 'audit', label: 'Auditoría', icon: ClockIcon }
]

// Panel chrome rung shared with the T2 list: hairline edge, --radius-panel
// shape, --elevation-2 shadow.
const panelStyle = {
  borderColor: 'var(--color-hairline)',
  borderRadius: 'var(--radius-panel)',
  boxShadow: 'var(--elevation-2)'
}

const CONNECTION_ERROR_MESSAGE = 'Revisa tu conexión y vuelve a intentarlo.'
const SERVER_ERROR_MESSAGE = 'Ocurrió un error en el servidor. Intenta nuevamente.'

// Errors arrive either as a useApi throw (`{ status, response }`) or as a plain
// Error when the request never completed, so both carriers are read.
const requestStatus = error => error?.status ?? error?.response?.status

const detailErrorMessageFor = error => {
  const status = requestStatus(error)
  if (status === 404) return 'El ambiente no existe.'
  if (status === 403) return 'No tienes permisos para ver este ambiente.'
  if (typeof status === 'number' && status >= 500) return SERVER_ERROR_MESSAGE
  return CONNECTION_ERROR_MESSAGE
}

const auditErrorMessageFor = error => {
  const status = requestStatus(error)
  if (status === 403) return 'No tienes permisos para ver la auditoría.'
  if (status === 404) return 'El ambiente no existe.'
  if (typeof status === 'number' && status >= 500) return SERVER_ERROR_MESSAGE
  return CONNECTION_ERROR_MESSAGE
}

const loadEnvironment = async () => {
  loading.value = true
  loadError.value = false
  detailErrorMessage.value = ''
  try {
    const response = await get(`/api/dental-chairs/${route.params.id}`)
    environment.value = response?.data || null
    if (!environment.value) {
      loadError.value = true
      detailErrorMessage.value = CONNECTION_ERROR_MESSAGE
    }
  } catch (error) {
    environment.value = null
    loadError.value = true
    detailErrorMessage.value = detailErrorMessageFor(error)
  } finally {
    loading.value = false
  }
}

// The audit list fetches through useApi directly: useAuditLogs swallows fetch
// failures and this page renders a retryable error state instead of only a toast.
const loadAuditLogs = async () => {
  if (!environment.value) return
  auditLoading.value = true
  auditError.value = false
  auditErrorMessage.value = ''
  try {
    const response = await get(`/api/audit-logs/dental-chair/${environment.value.id}`)
    auditLogs.value = response?.data || []
  } catch (error) {
    auditLogs.value = []
    auditError.value = true
    auditErrorMessage.value = auditErrorMessageFor(error)
  } finally {
    auditLoading.value = false
  }
}

const formatAuditDate = value => formatDate(value) || 'Fecha no disponible'

const getAuditActionVariant = action => {
  const normalized = typeof action === 'string' ? action : ''
  if (normalized.includes('created')) return 'success'
  if (normalized.includes('updated')) return 'warning'
  if (normalized.includes('deleted')) return 'error'
  return 'neutral'
}

const formatAuditAction = action =>
  formatAction(typeof action === 'string' ? action : '') || 'Acción desconocida'

// The API serializes audit values as PHP arrays: created rows carry
// `old_values: []` and deleted rows `new_values: []`. Empty arrays are truthy
// objects in JS, so a record only counts when it is a non-null object with at
// least one own key; otherwise the row is not a field-level diff.
const hasRecordedValues = record =>
  record !== null && typeof record === 'object' && Object.keys(record).length > 0

const auditEntryChanges = log => {
  if (!hasRecordedValues(log?.old_values) || !hasRecordedValues(log?.new_values)) return []
  const changes = getChangesSummary(log)
  if (!changes) return []
  return Object.entries(changes).map(([key, change]) => ({ key, ...change }))
}

const auditEntries = computed(() =>
  auditLogs.value.map(log => ({
    log,
    changes: auditEntryChanges(log),
    actionLabel: formatAuditAction(log.action),
    actionVariant: getAuditActionVariant(log.action)
  }))
)

const goBack = () => {
  router.push('/environments')
}

watch(activeTab, newTab => {
  if (newTab === 'audit' && environment.value) {
    loadAuditLogs()
  }
})

onMounted(async () => {
  await loadEnvironment()
  if (activeTab.value === 'audit' && environment.value) {
    loadAuditLogs()
  }
})
</script>
