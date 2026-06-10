<template>
  <AppLayout>
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">
            {{ procedure?.name || 'Cargando...' }}
          </h1>
          <p v-if="procedure" class="text-theme-secondary font-mono text-sm">
            {{ procedure.code }}
          </p>
        </div>
        <div class="flex gap-3">
          <UiButton variant="secondary" class="flex items-center gap-2" @click="goBack">
            <svg
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
            Volver
          </UiButton>
          <UiButton v-if="procedure" class="flex items-center gap-2" @click="goEdit">
            <svg
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
            Editar
          </UiButton>
        </div>
      </div>
    </div>

    <div v-if="loading" class="p-8 text-center">
      <LoadingSpinner />
    </div>

    <div v-else-if="procedure" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Columna principal: datos del procedimiento -->
      <div class="lg:col-span-2 space-y-6">
        <UiCard variant="glass">
          <div class="flex items-start justify-between mb-4">
            <h2 class="text-lg font-semibold text-theme-primary">Información general</h2>
            <UiBadge :variant="procedure.is_active ? 'success' : 'error'">
              {{ procedure.is_active ? 'Activo' : 'Inactivo' }}
            </UiBadge>
          </div>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <dt class="text-xs uppercase text-theme-secondary">Código</dt>
              <dd class="mt-1 font-mono text-sm text-theme-primary">
                {{ procedure.code }}
              </dd>
            </div>
            <div>
              <dt class="text-xs uppercase text-theme-secondary">Especialidad</dt>
              <dd class="mt-1 text-sm text-theme-primary">
                <span v-if="procedure.specialty_name">{{ procedure.specialty_name }}</span>
                <span v-else class="text-theme-secondary">— Sin especialidad —</span>
              </dd>
            </div>
            <div>
              <dt class="text-xs uppercase text-theme-secondary">Costo por defecto</dt>
              <dd class="mt-1 text-sm text-theme-primary">
                S/ {{ Number(procedure.default_cost).toFixed(2) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs uppercase text-theme-secondary">Duración por defecto</dt>
              <dd class="mt-1 text-sm text-theme-primary">
                {{ procedure.default_duration_minutes }} min
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-xs uppercase text-theme-secondary">Descripción</dt>
              <dd class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ procedure.description || 'Sin descripción' }}
              </dd>
            </div>
          </dl>
        </UiCard>

        <UiCard variant="glass">
          <h2 class="text-lg font-semibold text-theme-primary mb-4">Requisitos y materiales</h2>
          <dl class="space-y-4">
            <div v-if="procedure.materials_needed_list && procedure.materials_needed_list.length">
              <dt class="text-xs uppercase text-theme-secondary">Materiales necesarios</dt>
              <dd class="mt-2 flex flex-wrap gap-2">
                <span
                  v-for="(m, i) in procedure.materials_needed_list"
                  :key="i"
                  class="px-2 py-1 text-xs rounded-full bg-blue-50 text-blue-800"
                >
                  {{ m }}
                </span>
              </dd>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <dt class="text-xs uppercase text-theme-secondary">Requiere anestesia</dt>
                <dd class="mt-1 text-sm text-theme-primary">
                  {{ procedure.requires_anesthesia ? 'Sí' : 'No' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs uppercase text-theme-secondary">Requiere radiografías</dt>
                <dd class="mt-1 text-sm text-theme-primary">
                  {{ procedure.requires_radiographs ? 'Sí' : 'No' }}
                </dd>
              </div>
            </div>
            <div v-if="procedure.contraindications">
              <dt class="text-xs uppercase text-theme-secondary">Contraindicaciones</dt>
              <dd class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ procedure.contraindications }}
              </dd>
            </div>
            <div v-if="procedure.post_procedure_care">
              <dt class="text-xs uppercase text-theme-secondary">Cuidados post-procedimiento</dt>
              <dd class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ procedure.post_procedure_care }}
              </dd>
            </div>
          </dl>
        </UiCard>
      </div>

      <!-- Columna lateral: audit log -->
      <div>
        <UiCard variant="glass">
          <h2 class="text-lg font-semibold text-theme-primary mb-4">Historial</h2>
          <div v-if="loadingAudit" class="py-4 text-center">
            <LoadingSpinner />
          </div>
          <div v-else-if="!auditLogs.length" class="py-4 text-center text-sm text-theme-secondary">
            Sin movimientos registrados
          </div>
          <ul v-else class="space-y-3">
            <li
              v-for="log in auditLogs"
              :key="log.id"
              class="border-l-2 pl-3 py-1"
              :class="logClass(log.action)"
            >
              <p class="text-sm font-medium text-theme-primary">
                {{ auditActionLabel(log.action) }}
              </p>
              <p class="text-xs text-theme-secondary">
                {{ log.user?.name || 'Sistema' }} · {{ formatDate(log.created_at) }}
              </p>
            </li>
          </ul>
        </UiCard>
      </div>
    </div>

    <ProcedureCatalogFormModal
      v-if="showEdit"
      :procedure="procedure"
      :specialties="specialties"
      @close="showEdit = false"
      @saved="onSaved"
    />
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import { useSpecialties } from '../../composables/useSpecialties'
import { useToast } from '../../composables/useToast'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiBadge from '../../components/ui/Badge.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'
import ProcedureCatalogFormModal from './ProcedureCatalogFormModal.vue'

const route = useRoute()
const router = useRouter()
const { get } = useApi()
const toast = useToast()
const { getProcedure, currentProcedure, loading } = useProcedureCatalog()
const { specialties, getSpecialties } = useSpecialties()

const procedure = ref(null)
const auditLogs = ref([])
const loadingAudit = ref(false)
const showEdit = ref(false)

const auditActionLabel = action => {
  const map = {
    procedure_catalog_created: 'Procedimiento creado',
    procedure_catalog_updated: 'Procedimiento actualizado',
    procedure_catalog_deactivated: 'Procedimiento desactivado'
  }
  return map[action] || action
}

const logClass = action => {
  if (action?.includes('created')) return 'border-green-400'
  if (action?.includes('deactivated')) return 'border-red-400'
  if (action?.includes('updated')) return 'border-yellow-400'
  return 'border-gray-300'
}

const formatDate = iso => {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
}

const loadAudit = async id => {
  loadingAudit.value = true
  try {
    const response = await get(
      `/api/audit-logs?model_type=${encodeURIComponent('App\\\\Models\\\\ProcedureCatalog')}&auditable_id=${id}`
    )
    auditLogs.value = response.data?.data || []
  } catch (err) {
    auditLogs.value = []
  } finally {
    loadingAudit.value = false
  }
}

const goBack = () => router.push('/procedure-catalog')
const goEdit = () => {
  showEdit.value = true
}
const onSaved = () => {
  showEdit.value = false
  load()
}

const load = async () => {
  const { id } = route.params
  try {
    const p = await getProcedure(id)
    procedure.value = p
    loadAudit(id)
  } catch (err) {
    toast.error('No se pudo cargar el procedimiento')
    goBack()
  }
}

watch(currentProcedure, val => {
  if (val && val.id === Number(route.params.id)) procedure.value = val
})

onMounted(async () => {
  await getSpecialties(true)
  load()
})
</script>
