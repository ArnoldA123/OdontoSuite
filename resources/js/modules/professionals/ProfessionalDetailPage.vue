<template>
  <AppLayout>
    <!--
      Hero band (D10): same anatomy and surface tokens as the list hero. The
      Breadcrumbs primitive owns list > detail wayfinding, so no back action
      button exists here.
    -->
    <header
      data-professional-detail-hero
      class="relative overflow-hidden p-6 md:p-8 mb-6"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <DentalMotifLayer variant="hero" />
      <div class="relative">
        <Breadcrumbs
          :items="breadcrumbs"
          :show-home="false"
          size="sm"
          variant="minimal"
          class="mb-3"
        />
        <p class="inline-flex items-center gap-2">
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
            style="color: var(--color-accent-500)"
            aria-hidden="true"
          >
            <path
              d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z"
            />
          </svg>
          <span class="text-sm font-semibold tracking-tight text-label">OdontoSuite</span>
        </p>
        <h1 class="mt-3 text-4xl font-semibold text-label">
          {{ professional?.name || (loadError ? 'No disponible' : 'Cargando...') }}
        </h1>
        <p class="mt-2 text-base leading-relaxed text-theme-secondary">{{ heroCaption }}</p>
      </div>
    </header>

    <!--
      Detail block (D10): tabs keep their hierarchy through a tiered block
      heading; the facts rows live on card surface tokens.
    -->
    <section data-professional-detail>
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
          <h2 class="text-xl font-semibold text-label">{{ activeTabMeta.name }}</h2>
          <p class="mt-1 text-sm text-theme-secondary">{{ activeTabMeta.caption }}</p>
        </div>
        <nav
          class="flex space-x-8 border-b border-[color:var(--color-border)]"
          aria-label="Secciones del profesional"
        >
          <button
            v-for="tab in tabs"
            :key="tab.id"
            class="py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            :class="[
              activeTab === tab.id
                ? 'border-accent text-accent'
                : 'border-transparent text-theme-secondary hover:text-[color:var(--color-text-primary)] hover:border-[color:var(--color-border)]'
            ]"
            @click="activeTab = tab.id"
          >
            <component :is="tab.icon" class="w-4 h-4 inline mr-2" />
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <!-- Datos del profesional -->
      <UiCard v-if="activeTab === 'data'" variant="glass">
        <div v-if="!professional && loadError" class="p-8">
          <UiSectionEmpty
            title="No se pudo cargar el profesional"
            description="Revisa la conexión e intenta de nuevo."
          />
        </div>
        <div v-else-if="!professional" class="p-8 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />
          <p class="mt-2 text-theme-secondary">Cargando profesional...</p>
        </div>
        <div v-else data-professional-facts class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
          <div v-for="row in factRows" :key="row.key" :data-fact-row="row.key" class="min-w-0">
            <p class="text-xs font-medium text-theme-secondary uppercase tracking-wider">
              {{ row.label }}
            </p>
            <UiBadge
              v-if="row.badge"
              :variant="row.badge.variant"
              size="sm"
              shape="pill"
              class="mt-1.5"
            >
              {{ row.badge.text }}
            </UiBadge>
            <p v-else class="mt-1.5 text-sm text-[color:var(--color-text-primary)]">
              {{ row.value }}
            </p>
          </div>
        </div>
      </UiCard>

      <!-- Historial de auditoría -->
      <UiCard v-else variant="glass">
        <div v-if="auditLogsLoading" class="p-8 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />
          <p class="mt-2 text-theme-secondary">Cargando historial de auditoría...</p>
        </div>
        <div v-else-if="auditLogs.length === 0" class="p-4">
          <UiSectionEmpty
            title="No hay historial de auditoría"
            description="Este profesional no tiene registros de auditoría."
          />
        </div>
        <div v-else class="space-y-4">
          <div
            v-for="log in auditLogs"
            :key="log.id"
            class="border border-[color:var(--color-border)] rounded-lg p-4 hover:bg-theme-surface transition-colors"
          >
            <div class="flex items-center gap-2 mb-2">
              <UiBadge :variant="getAuditActionVariant(log.action)">
                {{ formatAction(log.action) }}
              </UiBadge>
              <span class="text-sm text-theme-secondary">
                por {{ log.user?.name || 'Sistema' }}
              </span>
            </div>
            <p class="text-sm text-theme-secondary mb-2">{{ formatDate(log.created_at) }}</p>
            <div v-if="getChangesSummary(log)" class="mt-2 text-sm">
              <p class="font-medium text-[color:var(--color-text-primary)] mb-1">
                Cambios realizados:
              </p>
              <div class="text-theme-secondary space-y-1">
                <div
                  v-for="(change, field) in getChangesSummary(log)"
                  :key="field"
                  class="pl-2 border-l-2 border-[color:var(--color-border)]"
                >
                  <p class="font-medium text-[color:var(--color-text-primary)]">
                    {{ change.field }}:
                  </p>
                  <p class="text-xs">
                    De:
                    <span class="text-red-500">{{ change.old }}</span>
                  </p>
                  <p class="text-xs">
                    A:
                    <span class="text-green-500">{{ change.new }}</span>
                  </p>
                </div>
              </div>
            </div>
            <div
              v-else-if="log.action === 'user_created'"
              class="mt-2 text-sm text-theme-secondary"
            >
              Profesional creado en el sistema.
            </div>
            <div
              v-else-if="log.action === 'user_deleted'"
              class="mt-2 text-sm text-theme-secondary"
            >
              Profesional eliminado del sistema.
            </div>
          </div>
        </div>
      </UiCard>
    </section>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { UserIcon, ClockIcon } from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useAuditLogs } from '../../composables/useAuditLogs'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiCard from '../../components/ui/Card.vue'
import UiBadge from '../../components/ui/Badge.vue'
import Breadcrumbs from '../../components/ui/Breadcrumbs.vue'
import DentalMotifLayer from '../../components/ui/DentalMotifLayer.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
import { SPECIALTY_LABELS, ROLE_LABELS } from './professionals.constants'

const route = useRoute()
const { get } = useApi()
const toast = useToast()
const {
  loading: auditLogsLoading,
  auditLogs,
  getUserAuditLogs,
  formatAction,
  getChangesSummary
} = useAuditLogs()

const professional = ref(null)
const loadError = ref(false)
const activeTab = ref('data')
const branchesById = ref({})

const tabs = [
  {
    id: 'data',
    name: 'Datos',
    caption: 'Contacto, rol, especialidad, matrícula y sucursal.',
    icon: UserIcon
  },
  { id: 'audit', name: 'Historial', caption: 'Cambios registrados en la cuenta.', icon: ClockIcon }
]

const activeTabMeta = computed(() => tabs.find(tab => tab.id === activeTab.value) ?? tabs[0])

const breadcrumbs = computed(() => [
  { to: '/professionals', label: 'Profesionales' },
  { label: professional.value?.name || 'Detalle' }
])

const heroCaption = computed(() => {
  if (professional.value) return `Ficha del equipo clínico · ID ${professional.value.id}`
  return loadError.value ? 'No se pudo cargar la ficha' : 'Cargando ficha'
})

const roleLabel = role => ROLE_LABELS[role] || role || 'N/D'

const getSpecialtyText = specialty => SPECIALTY_LABELS[specialty] || specialty || 'N/D'

const notFoundText = value => {
  const text = typeof value === 'string' ? value.trim() : ''
  return text || 'N/D'
}

const professionalLicense = pro => notFoundText(pro.professional_license)

const branchName = pro => {
  const branchId = pro.branch_id
  if (branchId === null || branchId === undefined || branchId === '') return 'Sin sucursal'
  return branchesById.value[branchId] || 'N/D'
}

const factRows = computed(() => {
  const pro = professional.value
  if (!pro) return []
  return [
    { key: 'name', label: 'Nombre', value: notFoundText(pro.name) },
    { key: 'username', label: 'Usuario', value: notFoundText(pro.username) },
    { key: 'email', label: 'Email', value: notFoundText(pro.email) },
    { key: 'phone', label: 'Teléfono', value: notFoundText(pro.phone) },
    { key: 'role', label: 'Rol', badge: { variant: 'neutral', text: roleLabel(pro.role) } },
    {
      key: 'specialty',
      label: 'Especialidad',
      badge: { variant: 'primary', text: getSpecialtyText(pro.specialty) }
    },
    { key: 'license', label: 'Matrícula', value: professionalLicense(pro) },
    { key: 'branch', label: 'Sucursal', value: branchName(pro) },
    {
      key: 'state',
      label: 'Estado',
      badge: {
        variant: pro.is_active ? 'success' : 'error',
        text: pro.is_active ? 'Activo' : 'Inactivo'
      }
    }
  ]
})

const formatDate = date => {
  if (!date) return 'N/D'
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
  return 'default'
}

const loadProfessional = async () => {
  loadError.value = false
  try {
    const response = await get(`/api/users/${route.params.id}`)
    professional.value = response.data
  } catch (error) {
    professional.value = null
    loadError.value = true
    toast.error('Error al cargar el profesional')
  }
}

const loadBranches = async () => {
  try {
    const response = await get('/api/branches/active')
    const list = response?.data || []
    branchesById.value = Object.fromEntries(list.map(branch => [branch.id, branch.name]))
  } catch {
    branchesById.value = {}
  }
}

const loadAuditLogs = async () => {
  if (!professional.value) return
  try {
    await getUserAuditLogs(professional.value.id)
  } catch (error) {
    // La auditoría es informativa, el detalle sigue visible sin ella
  }
}

watch(activeTab, newTab => {
  if (newTab === 'audit' && professional.value) {
    loadAuditLogs()
  }
})

onMounted(async () => {
  await Promise.all([loadProfessional(), loadBranches()])
  if (activeTab.value === 'audit' && professional.value) {
    loadAuditLogs()
  }
})
</script>
