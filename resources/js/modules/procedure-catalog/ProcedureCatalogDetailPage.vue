<template>
  <AppLayout>
    <header
      data-detail-hero
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
        data-detail-crumb
        class="inline-flex items-center gap-1 text-sm font-medium text-theme-secondary hover:text-theme-primary"
        @click="goBack"
      >
        <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
        Catálogo de Procedimientos
      </button>

      <div class="mt-3 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
            Procedimiento
          </p>
          <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 v-if="procedure" class="text-4xl font-semibold text-label" data-detail-title>
              {{ procedure.name }}
            </h1>
            <div
              v-else
              class="h-9 w-72 max-w-full rounded border border-hairline bg-systemGray-100"
              aria-hidden="true"
            />
            <UiStatusBadge
              v-if="procedure"
              :variant="procedure.is_active ? 'success' : 'neutral'"
              :label="procedure.is_active ? 'Activo' : 'Inactivo'"
              data-detail-status
            />
          </div>
          <p v-if="procedure" class="mt-2 text-sm text-theme-secondary" data-detail-caption>
            <span class="font-mono">{{ procedure.code }}</span>
            <span class="mx-2">·</span>
            {{ procedure.specialty_name || 'Sin especialidad' }}
          </p>
          <p v-else-if="loadError" class="mt-2 text-sm text-theme-secondary">
            Sin datos del procedimiento.
          </p>
        </div>
        <div v-if="procedure" class="flex flex-shrink-0 items-center gap-3">
          <UiButton data-detail-action="edit" @click="goEdit">
            <template #icon-left>
              <PencilSquareIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Editar
          </UiButton>
          <UiButton variant="secondary" data-detail-action="back" @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
        </div>
      </div>
    </header>

    <div v-if="loading" data-detail-skeleton class="space-y-6" aria-busy="true">
      <div
        class="p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="h-3 w-24 rounded border border-hairline bg-systemGray-100" />
        <div class="mt-3 h-9 w-72 max-w-full rounded border border-hairline bg-systemGray-100" />
        <div class="mt-3 h-4 w-48 rounded border border-hairline bg-systemGray-100" />
      </div>
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div
          v-for="group in 3"
          :key="group"
          class="p-6"
          style="
            background: var(--color-canvas);
            border: 1px solid var(--color-hairline);
            border-radius: var(--radius-panel);
          "
        >
          <div class="h-6 w-40 rounded border border-hairline bg-systemGray-100" />
          <div class="mt-2 h-4 w-56 rounded border border-hairline bg-systemGray-100" />
          <div class="mt-6 space-y-3">
            <div class="h-4 w-full rounded border border-hairline bg-systemGray-100" />
            <div class="h-4 w-2/3 rounded border border-hairline bg-systemGray-100" />
            <div class="h-4 w-1/2 rounded border border-hairline bg-systemGray-100" />
          </div>
        </div>
      </div>
    </div>

    <div
      v-else-if="loadError"
      data-detail-error
      role="alert"
      class="rounded-ios bg-systemRed-50 p-10 text-center"
      style="border: 1px solid var(--color-hairline)"
    >
      <ExclamationTriangleIcon
        class="mx-auto h-12 w-12 mb-4 text-systemRed-600"
        aria-hidden="true"
      />
      <p class="text-base font-medium text-theme-primary">No pudimos cargar el procedimiento</p>
      <p class="mt-1 max-w-md mx-auto text-sm text-theme-secondary">
        Revisa tu conexión e inténtalo de nuevo. Si el problema continúa, vuelve a intentarlo en
        unos minutos.
      </p>
      <div class="mt-6">
        <UiButton variant="primary" size="md" data-retry-detail @click="loadProcedure">
          Reintentar
        </UiButton>
      </div>
    </div>

    <UiTabs v-else-if="procedure" v-model="activeTab" :tabs="tabs">
      <template #data>
        <div class="space-y-6">
          <UiCard variant="glass" data-detail-group="general">
            <h2 class="text-2xl font-semibold text-label">Información general</h2>
            <p class="mt-1 text-sm text-theme-secondary">
              Identidad, tarifa y duración del procedimiento
            </p>
            <dl class="mt-6 space-y-4">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Código
                  </dt>
                  <dd class="mt-1 font-mono text-sm text-label" data-detail-field="code">
                    {{ procedure.code }}
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Especialidad
                  </dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="specialty">
                    {{ procedure.specialty_name || 'Sin especialidad' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Costo por defecto
                  </dt>
                  <dd
                    class="mt-1 text-2xl font-semibold text-label tabular-nums"
                    data-detail-field="cost"
                  >
                    {{ formatCurrency(procedure.default_cost) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Duración por defecto
                  </dt>
                  <dd
                    class="mt-1 text-sm text-theme-primary tabular-nums"
                    data-detail-field="duration"
                  >
                    {{ procedure.default_duration_minutes }} min
                  </dd>
                </div>
                <!-- #293 S1: what the procedure applies to. -->
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Aplicabilidad
                  </dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="site_type">
                    {{ siteTypeLabel }}
                  </dd>
                </div>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                  Descripción
                </dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-theme-primary">
                  {{ procedure.description || 'Sin descripción registrada' }}
                </dd>
              </div>
            </dl>
          </UiCard>

          <!-- #293 S7: equivalencias con el nomenclador público. Read-only. -->
          <UiCard variant="glass" data-detail-group="external-codes">
            <h2 class="text-2xl font-semibold text-label">Equivalencias</h2>
            <p class="mt-1 text-sm text-theme-secondary">
              Códigos del nomenclador público con los que se reconoce este procedimiento
            </p>

            <div
              v-if="externalCodes.length"
              class="mt-6 divide-y divide-hairline"
              data-external-codes-list
            >
              <article
                v-for="(externalCode, index) in externalCodes"
                :key="`${externalCode.system}-${externalCode.code}-${index}`"
                data-external-code-row
                class="flex flex-wrap items-baseline gap-x-4 gap-y-1 py-3 first:pt-0 last:pb-0"
              >
                <UiStatusBadge
                  variant="info"
                  size="sm"
                  :label="externalSystemLabel(externalCode.system)"
                />
                <p class="font-mono text-sm text-label" data-external-code-code>
                  {{ externalCode.code }}
                </p>
                <p v-if="externalCode.description" class="text-sm text-theme-secondary">
                  {{ externalCode.description }}
                </p>
              </article>
            </div>

            <p v-else class="mt-6 text-sm text-theme-secondary" data-external-codes-empty>
              Este procedimiento no declara equivalencias con el nomenclador público.
            </p>
          </UiCard>

          <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <UiCard variant="glass" data-detail-group="preparation">
              <h2 class="text-2xl font-semibold text-label">Preparación</h2>
              <p class="mt-1 text-sm text-theme-secondary">
                Requisitos y materiales para realizar el procedimiento
              </p>
              <dl class="mt-6 space-y-4">
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Requisitos previos
                  </dt>
                  <dd class="mt-1 whitespace-pre-line text-sm text-theme-primary">
                    {{ procedure.requirements || 'Sin requisitos registrados' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Materiales necesarios
                  </dt>
                  <dd class="mt-2 flex flex-wrap gap-2">
                    <UiStatusBadge
                      v-for="(material, index) in procedure.materials_needed_list || []"
                      :key="index"
                      variant="info"
                      :label="material"
                      size="sm"
                    />
                    <span
                      v-if="!procedure.materials_needed_list?.length"
                      class="text-sm text-theme-secondary"
                    >
                      Sin materiales registrados
                    </span>
                  </dd>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                      Requiere anestesia
                    </dt>
                    <dd class="mt-1 text-sm text-theme-primary">
                      {{ procedure.requires_anesthesia ? 'Sí' : 'No' }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                      Requiere radiografías
                    </dt>
                    <dd class="mt-1 text-sm text-theme-primary">
                      {{ procedure.requires_radiographs ? 'Sí' : 'No' }}
                    </dd>
                  </div>
                </div>
              </dl>
            </UiCard>

            <UiCard variant="glass" data-detail-group="care">
              <h2 class="text-2xl font-semibold text-label">Cuidados</h2>
              <p class="mt-1 text-sm text-theme-secondary">
                Contraindicaciones y cuidados posteriores
              </p>
              <dl class="mt-6 space-y-4">
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Contraindicaciones
                  </dt>
                  <dd class="mt-1 whitespace-pre-line text-sm text-theme-primary">
                    {{ procedure.contraindications || 'Sin contraindicaciones registradas' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Cuidados post-procedimiento
                  </dt>
                  <dd class="mt-1 whitespace-pre-line text-sm text-theme-primary">
                    {{ procedure.post_procedure_care || 'Sin cuidados registrados' }}
                  </dd>
                </div>
              </dl>
            </UiCard>
          </div>
        </div>
      </template>

      <template #audit>
        <UiCard variant="glass">
          <h2 class="text-2xl font-semibold text-label">Historial de auditoría</h2>
          <p class="mt-1 text-sm text-theme-secondary">
            Cambios registrados sobre este procedimiento
          </p>

          <div v-if="auditLoading" class="py-8">
            <LoadingSpinner text="Cargando historial de auditoría..." />
          </div>
          <div v-else-if="auditError" class="py-8 text-center">
            <p class="text-sm text-theme-secondary">No pudimos cargar el historial de auditoría.</p>
            <div class="mt-4">
              <UiButton variant="secondary" size="sm" @click="loadAudit">Reintentar</UiButton>
            </div>
          </div>
          <UiEmptyState
            v-else-if="auditLogs.length === 0"
            title="No hay historial de auditoría"
            description="Este procedimiento no tiene registros de auditoría."
          />
          <div v-else class="mt-6 divide-y divide-hairline" data-audit-list>
            <article
              v-for="log in auditLogs"
              :key="log.id"
              data-audit-row
              class="py-5 first:pt-0 last:pb-0"
            >
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="flex items-center gap-2">
                  <UiStatusBadge
                    :variant="auditActionVariant(log.action)"
                    :label="auditActionLabel(log.action)"
                    size="sm"
                  />
                  <span class="text-sm text-theme-secondary">
                    por {{ log.user?.name || 'Sistema' }}
                  </span>
                </div>
                <p class="text-sm text-theme-secondary">{{ formatDate(log.created_at) }}</p>
              </div>
              <div v-if="auditChanges(log).length" class="mt-3 space-y-2">
                <div
                  v-for="change in auditChanges(log)"
                  :key="change.field"
                  class="border-l-2 border-hairline pl-3"
                >
                  <p class="text-sm font-medium text-label">{{ change.field }}</p>
                  <p class="mt-1 text-xs text-theme-secondary">
                    De:
                    <span class="text-systemRed-600">{{ change.old }}</span>
                  </p>
                  <p class="text-xs text-theme-secondary">
                    A:
                    <span class="text-systemGreen-600">{{ change.new }}</span>
                  </p>
                </div>
              </div>
            </article>
          </div>
        </UiCard>
      </template>

      <template #versions>
        <UiCard variant="glass">
          <h2 class="text-2xl font-semibold text-label">Historial de versiones</h2>
          <p class="mt-1 text-sm text-theme-secondary">
            Snapshot de los cambios de tarifa, nombre y estado
          </p>

          <div v-if="versionsLoading" class="py-8">
            <LoadingSpinner text="Cargando historial de versiones..." />
          </div>
          <div v-else-if="versionsError" class="py-8 text-center">
            <p class="text-sm text-theme-secondary">No pudimos cargar el historial de versiones.</p>
            <div class="mt-4">
              <UiButton variant="secondary" size="sm" @click="loadVersions">Reintentar</UiButton>
            </div>
          </div>
          <UiEmptyState
            v-else-if="versions.length === 0"
            title="No hay versiones registradas"
            description="Los cambios de este procedimiento todavía no generaron snapshots."
          />
          <div v-else class="mt-6 divide-y divide-hairline" data-versions-list>
            <article
              v-for="version in versions"
              :key="version.id"
              data-version-row
              class="py-5 first:pt-0 last:pb-0"
            >
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="flex items-center gap-2">
                  <UiStatusBadge
                    :variant="versionVariant(version.change_type)"
                    :label="versionLabel(version.change_type)"
                    size="sm"
                  />
                  <span class="text-sm text-theme-secondary">
                    por {{ version.changed_by?.name || 'Sistema' }}
                  </span>
                </div>
                <p class="text-sm text-theme-secondary">{{ formatDate(version.changed_at) }}</p>
              </div>
              <div v-if="changedFieldEntries(version).length" class="mt-3 space-y-1">
                <p
                  v-for="entry in changedFieldEntries(version)"
                  :key="entry.field"
                  class="text-sm text-theme-secondary"
                >
                  <span class="font-medium text-label">{{ entry.field }}:</span>
                  {{ entry.value }}
                </p>
              </div>
              <p v-else class="mt-3 text-sm text-theme-secondary">Sin campos modificados.</p>
            </article>
          </div>
        </UiCard>
      </template>
    </UiTabs>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ClockIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  PencilSquareIcon,
  Squares2X2Icon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useAuditLogs } from '../../composables/useAuditLogs'
import { useToast } from '../../composables/useToast'
import { formatCurrency } from '../../composables/useFormatters'
import { SITE_TYPE_LABELS } from './procedureSite'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiTabs from '../../components/ui/Tabs.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'

const route = useRoute()
const router = useRouter()
const { get } = useApi()
const toast = useToast()
const { getAllAuditLogs } = useAuditLogs()

const procedure = ref(null)
const loading = ref(true)
const loadError = ref(false)
const activeTab = ref('data')

// #293 S1/S7: the declared applicability and the public nomenclator codes are
// part of what the catalog says about a procedure.
const siteTypeLabel = computed(
  () => SITE_TYPE_LABELS[procedure.value?.site_type ?? 'none'] ?? SITE_TYPE_LABELS.none
)

const externalCodes = computed(() =>
  Array.isArray(procedure.value?.external_codes) ? procedure.value.external_codes : []
)

const externalSystemLabels = {
  minsa_rm550_2023: 'MINSA RM 550-2023',
  sis: 'SIS'
}

const externalSystemLabel = system => externalSystemLabels[system] ?? system

const auditLogs = ref([])
const auditLoading = ref(false)
const auditError = ref(false)
const auditLoaded = ref(false)

const versions = ref([])
const versionsLoading = ref(false)
const versionsError = ref(false)
const versionsLoaded = ref(false)

const tabs = [
  { id: 'data', label: 'Datos', icon: Squares2X2Icon },
  { id: 'audit', label: 'Historial', icon: ClockIcon },
  { id: 'versions', label: 'Versiones', icon: DocumentTextIcon }
]

const formatDate = value => {
  if (!value) return 'Fecha no registrada'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
}

const auditActionLabel = action => {
  const labels = {
    procedure_catalog_created: 'Creado',
    procedure_catalog_updated: 'Actualizado',
    procedure_catalog_deactivated: 'Desactivado'
  }
  return labels[action] || action
}

const auditActionVariant = action => {
  if (action?.includes('created')) return 'success'
  if (action?.includes('deactivated')) return 'neutral'
  if (action?.includes('updated')) return 'warning'
  return 'info'
}

const auditChanges = log => {
  if (!log.old_values || !log.new_values) return []
  const oldValues = typeof log.old_values === 'string' ? JSON.parse(log.old_values) : log.old_values
  const newValues = typeof log.new_values === 'string' ? JSON.parse(log.new_values) : log.new_values
  return Object.keys(newValues)
    .filter(field => String(oldValues[field] ?? '') !== String(newValues[field] ?? ''))
    .map(field => ({
      field,
      old: oldValues[field] ?? '—',
      new: newValues[field] ?? '—'
    }))
}

const versionLabel = changeType => {
  const labels = { created: 'Creado', updated: 'Actualizado', deactivated: 'Desactivado' }
  return labels[changeType] || changeType
}

const versionVariant = changeType => {
  if (changeType === 'created') return 'success'
  if (changeType === 'deactivated') return 'neutral'
  return 'warning'
}

const changedFieldEntries = version =>
  Object.entries(version.changed_fields || {}).map(([field, value]) => ({
    field,
    value: value === null || value === '' ? '—' : String(value)
  }))

const loadProcedure = async () => {
  loading.value = true
  loadError.value = false
  try {
    const response = await get(`/api/procedure-catalog/${route.params.id}`)
    procedure.value = response.data || null
    loadError.value = !procedure.value
  } catch {
    procedure.value = null
    loadError.value = true
  } finally {
    loading.value = false
  }
}

const loadAudit = async () => {
  if (!procedure.value) return
  auditLoading.value = true
  auditError.value = false
  try {
    const logs = await getAllAuditLogs({
      model_type: 'App\\\\Models\\\\ProcedureCatalog',
      auditable_id: procedure.value.id,
      per_page: 50
    })
    auditLogs.value = logs || []
    auditLoaded.value = true
  } catch {
    auditError.value = true
  } finally {
    auditLoading.value = false
  }
}

const loadVersions = async () => {
  if (!procedure.value) return
  versionsLoading.value = true
  versionsError.value = false
  try {
    const response = await get(`/api/procedure-catalog/${procedure.value.id}/versions`)
    versions.value = response.data || []
    versionsLoaded.value = true
  } catch {
    versionsError.value = true
  } finally {
    versionsLoading.value = false
  }
}

const loadActiveTab = () => {
  if (activeTab.value === 'audit' && !auditLoaded.value && !auditLoading.value) loadAudit()
  if (activeTab.value === 'versions' && !versionsLoaded.value && !versionsLoading.value) {
    loadVersions()
  }
}

watch(activeTab, loadActiveTab)

const goBack = () => router.push('/procedure-catalog')

const goEdit = () => {
  const id = procedure.value?.id
  if (id === null || id === undefined) {
    router.push('/procedure-catalog')
    return
  }
  router.push({ path: '/procedure-catalog', query: { edit: String(id) } })
}

onMounted(async () => {
  await loadProcedure()
  if (!procedure.value) toast.error('No se pudo cargar el procedimiento')
})
</script>
