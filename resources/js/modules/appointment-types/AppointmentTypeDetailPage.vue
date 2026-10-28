<template>
  <AppLayout>
    <!-- Hero band (D1): the list hero surface recipe (canvas base + hairline
         + radius-panel + elevation-2) with breadcrumb, eyebrow, display
         headline, status pill and right-aligned actions. -->
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
        Tipos de Cita
      </button>

      <div class="mt-3 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <p
            class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
            data-detail-eyebrow
          >
            Catálogo
          </p>
          <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 v-if="appointmentType" class="text-4xl font-semibold text-label" data-detail-title>
              {{ appointmentType.name }}
            </h1>
            <div
              v-else
              class="h-9 w-72 max-w-full rounded border border-hairline bg-systemGray-100"
              data-detail-title-placeholder
              aria-hidden="true"
            />
            <span v-if="appointmentType" data-detail-status>
              <UiStatusBadge
                :variant="appointmentType.is_active ? 'success' : 'neutral'"
                :label="appointmentType.is_active ? 'Activo' : 'Inactivo'"
              />
            </span>
          </div>
          <p v-if="appointmentType" class="mt-2 text-sm text-theme-secondary" data-detail-caption>
            {{ appointmentType.description || 'Sin descripción registrada' }}
          </p>
          <p v-else-if="loadError" class="mt-2 text-sm text-theme-secondary" data-detail-caption>
            Sin datos del tipo de cita.
          </p>
          <div
            v-else
            class="mt-2 h-4 w-48 rounded border border-hairline bg-systemGray-100"
            aria-hidden="true"
          />
        </div>
        <div class="flex flex-shrink-0 items-center gap-3" data-detail-actions>
          <!-- #439: «Editar» es la puerta de `manage.appointment_type`; quien
               sólo lee el catálogo conserva la ficha y su historial. -->
          <UiButton v-if="canManageAppointmentTypes" data-detail-action="edit" @click="goEdit">
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

    <!-- Skeleton (D4): mirrors the final shape (hero + info groups) with
         neutral placeholder blocks over hairline borders. No shimmer. -->
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
          data-detail-skeleton-group
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

    <!-- Error surface (D4): the same inline surface the list page uses. -->
    <div
      v-else-if="loadError"
      data-types-error
      role="alert"
      class="rounded-ios bg-systemRed-50 p-10 text-center"
      style="border: 1px solid var(--color-hairline)"
    >
      <ExclamationTriangleIcon
        class="mx-auto h-12 w-12 mb-4 text-systemRed-600"
        aria-hidden="true"
      />
      <p class="text-base font-medium text-theme-primary">No pudimos cargar el tipo de cita</p>
      <p class="mt-1 max-w-md mx-auto text-sm text-theme-secondary">
        Revisa tu conexión e inténtalo de nuevo. Si el problema continúa, vuelve a intentarlo en
        unos minutos.
      </p>
      <div class="mt-6">
        <UiButton variant="primary" size="md" data-retry-detail @click="loadAppointmentType">
          Reintentar
        </UiButton>
      </div>
    </div>

    <UiTabs v-else v-model="activeTab" :tabs="tabs">
      <!-- Sectioned info grid (D2): each group lives in ONE glass UiCard with
           the dashboard heading grammar (h2 + caption). -->
      <template #data>
        <div class="space-y-6">
          <UiCard variant="glass" data-detail-group="general">
            <h2 class="text-2xl font-semibold text-label">Información general</h2>
            <p class="mt-1 text-sm text-theme-secondary">
              Identidad y descripción del tipo de cita
            </p>
            <dl class="mt-6 space-y-4">
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                  Nombre
                </dt>
                <dd class="mt-1 text-base font-medium text-label">
                  {{ appointmentType?.name }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                  Descripción
                </dt>
                <dd class="mt-1 text-sm text-theme-primary">
                  {{ appointmentType?.description || 'Sin descripción registrada' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                  Color
                </dt>
                <dd class="mt-2 flex items-center gap-2">
                  <span
                    class="h-6 w-6 rounded border border-hairline"
                    data-detail-swatch
                    :style="{ backgroundColor: appointmentType?.color }"
                    aria-hidden="true"
                  />
                  <span class="text-sm text-theme-primary" data-detail-field="color">
                    {{ appointmentType?.color }}
                  </span>
                </dd>
              </div>
            </dl>
          </UiCard>

          <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <UiCard variant="glass" data-detail-group="operation">
              <h2 class="text-2xl font-semibold text-label">Operación</h2>
              <p class="mt-1 text-sm text-theme-secondary">
                Reglas que este tipo aporta a la agenda
              </p>
              <dl class="mt-6 space-y-4">
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Duración por defecto
                  </dt>
                  <dd
                    class="mt-1 text-sm text-theme-primary tabular-nums"
                    data-detail-field="duration"
                  >
                    {{ appointmentType?.default_duration_minutes }} min
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Requiere confirmación
                  </dt>
                  <dd
                    class="mt-1 text-sm text-theme-primary"
                    data-detail-field="requires-confirmation"
                  >
                    {{ appointmentType?.requires_confirmation ? 'Sí' : 'No' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Requiere materiales
                  </dt>
                  <dd
                    class="mt-1 text-sm text-theme-primary"
                    data-detail-field="requires-materials"
                  >
                    {{ appointmentType?.requires_materials ? 'Sí' : 'No' }}
                  </dd>
                  <!-- #444: el valor lo deriva la API de la receta del
                       procedimiento por defecto (#295); la ficha lo declara. -->
                  <p
                    class="mt-1 text-xs text-theme-secondary"
                    data-detail-derived="requires-materials"
                  >
                    Derivado de la receta del procedimiento por defecto.
                  </p>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Modo consulta
                  </dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="consultation-mode">
                    {{ appointmentType?.is_consultation_mode ? 'Sí' : 'No' }}
                  </dd>
                </div>
              </dl>
            </UiCard>

            <UiCard variant="glass" data-detail-group="price">
              <h2 class="text-2xl font-semibold text-label">Precio</h2>
              <p class="mt-1 text-sm text-theme-secondary">Tarifa y procedimiento por defecto</p>
              <dl class="mt-6 space-y-4">
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Precio
                  </dt>
                  <dd
                    class="mt-1 text-2xl font-semibold text-label tabular-nums"
                    data-detail-field="price"
                  >
                    {{ formatPrice(appointmentType?.price) }}
                  </dd>
                </div>
                <div v-if="appointmentType?.default_procedure_catalog_id">
                  <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    Procedimiento por defecto
                  </dt>
                  <dd class="mt-1">
                    <RouterLink
                      data-detail-procedure-link
                      class="text-sm font-medium text-systemBlue-600 hover:text-systemBlue-700"
                      :to="`/procedure-catalog/${appointmentType.default_procedure_catalog_id}`"
                    >
                      Ver procedimiento en el catálogo
                    </RouterLink>
                  </dd>
                </div>
              </dl>
            </UiCard>
          </div>
        </div>
      </template>

      <!-- Audit tab (D3): hairline-divided rows inside ONE UiCard. -->
      <template #audit>
        <UiCard variant="glass">
          <h2 class="text-2xl font-semibold text-label">Historial de auditoría</h2>
          <p class="mt-1 text-sm text-theme-secondary">
            Cambios registrados sobre este tipo de cita
          </p>

          <div v-if="auditLogsLoading" class="py-8">
            <LoadingSpinner text="Cargando historial de auditoría..." />
          </div>

          <div v-else-if="auditLogs.length === 0" class="py-8">
            <UiEmptyState
              title="No hay historial de auditoría"
              description="Este tipo de cita no tiene registros de auditoría."
            />
          </div>

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
                    :variant="getAuditActionVariant(log.action)"
                    :label="formatAction(log.action)"
                    size="sm"
                  />
                  <span class="text-sm text-theme-secondary" data-audit-actor>
                    por {{ log.user?.name || 'Sistema' }}
                  </span>
                </div>
                <p class="text-sm text-theme-secondary" data-audit-date>
                  {{ formatAuditDate(log.created_at) }}
                </p>
              </div>

              <p
                v-if="log.action === 'appointment_type_created'"
                class="mt-3 text-sm text-theme-secondary"
              >
                Tipo de cita creado en el sistema.
              </p>
              <p
                v-else-if="log.action === 'appointment_type_deleted'"
                class="mt-3 text-sm text-theme-secondary"
              >
                Tipo de cita eliminado del sistema.
              </p>
              <div v-else-if="log.old_values && log.new_values" class="mt-3">
                <p class="text-sm font-medium text-theme-primary">Cambios realizados:</p>
                <div
                  v-if="getChangesSummary(log) && Object.keys(getChangesSummary(log)).length > 0"
                  class="mt-2 space-y-2"
                >
                  <div
                    v-for="(change, field) in getChangesSummary(log)"
                    :key="field"
                    class="pl-3 border-l-2 border-hairline"
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
                <p v-else class="mt-1 text-sm text-theme-secondary">Sin cambios registrados</p>
              </div>
            </article>
          </div>
        </UiCard>
      </template>
    </UiTabs>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  CalendarIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  PencilSquareIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useAuditLogs } from '../../composables/useAuditLogs'
import { usePermissions } from '../../composables/usePermissions'
import { formatCurrency, formatDate } from '../../composables/useFormatters'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiTabs from '../../components/ui/Tabs.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'

export default {
  name: 'AppointmentTypeDetailPage',
  components: {
    AppLayout,
    ArrowLeftIcon,
    ExclamationTriangleIcon,
    PencilSquareIcon,
    UiCard,
    UiButton,
    UiStatusBadge,
    UiTabs,
    UiEmptyState
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { get } = useApi()
    const toast = useToast()
    // #439: «Editar» sólo se pinta con el permiso que exige la ruta de
    // escritura del catálogo (`manage.appointment_type`).
    const { can } = usePermissions()
    const canManageAppointmentTypes = computed(() => can.manageAppointmentTypes.value)
    const {
      loading: auditLogsLoading,
      auditLogs,
      getAppointmentTypeAuditLogs,
      formatAction,
      getChangesSummary
    } = useAuditLogs()

    // State
    const appointmentType = ref(null)
    const loading = ref(true)
    const loadError = ref(false)
    const activeTab = ref('data')

    // Tabs configuration: `{ id: 'data', label: ... }` literal shape is pinned
    // by AppointmentTypesAppShellTest (TIPOS-02-001 / Tabs.vue validator).
    const tabs = [
      {
        id: 'data',
        label: 'Datos',
        icon: CalendarIcon
      },
      {
        id: 'audit',
        label: 'Historial',
        icon: ClockIcon
      }
    ]

    // Methods
    const loadAppointmentType = async () => {
      loading.value = true
      try {
        const response = await get(`/api/appointment-types/${route.params.id}`)
        appointmentType.value = response.data
        loadError.value = false
      } catch (error) {
        appointmentType.value = null
        loadError.value = true
        toast.error('Error al cargar el tipo de cita')
      } finally {
        loading.value = false
      }
    }

    const loadAuditLogs = async () => {
      if (!appointmentType.value) return
      try {
        await getAppointmentTypeAuditLogs(appointmentType.value.id)
      } catch (error) {
        // La auditoría es informativa, el detalle sigue visible sin ella
      }
    }

    // PR-citas-04: thin wrapper around the canonical `formatCurrency` from
    // `useFormatters.js` that preserves the legacy "No especificado" fallback
    // for null/undefined prices. The actual money formatting delegates to the
    // canonical helper (PAGOS-MNY-002 / CITAS-AT-001).
    const formatPrice = price => {
      if (price === null || price === undefined) return 'No especificado'
      return formatCurrency(price)
    }

    const formatAuditDate = value => formatDate(value) || 'No especificada'

    const getAuditActionVariant = action => {
      if (action.includes('created')) return 'success'
      if (action.includes('updated')) return 'warning'
      if (action.includes('deleted')) return 'error'
      return 'neutral'
    }

    const goBack = () => {
      router.push('/appointment-types')
    }

    // D1 `Editar` deep-links into the shared edit modal on the list page
    // (`?edit=<id>`), so the module keeps ONE edit form.
    const goEdit = () => {
      const id = appointmentType.value?.id
      if (id === null || id === undefined) {
        router.push('/appointment-types')
        return
      }
      router.push({ path: '/appointment-types', query: { edit: String(id) } })
    }

    // Watch for tab changes
    watch(activeTab, newTab => {
      if (newTab === 'audit' && appointmentType.value) {
        loadAuditLogs()
      }
    })

    // Lifecycle
    onMounted(async () => {
      await loadAppointmentType()
      if (activeTab.value === 'audit' && appointmentType.value) {
        loadAuditLogs()
      }
    })

    return {
      appointmentType,
      loading,
      loadError,
      activeTab,
      tabs,
      auditLogsLoading,
      auditLogs,
      loadAppointmentType,
      loadAuditLogs,
      formatPrice,
      formatCurrency,
      formatAuditDate,
      formatAction,
      getChangesSummary,
      getAuditActionVariant,
      goBack,
      goEdit,
      canManageAppointmentTypes
    }
  }
}
</script>
