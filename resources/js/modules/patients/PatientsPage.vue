<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Hero band: the login surface vocabulary + the page's single primary CTA. -->
      <header
        ref="heroSection"
        data-patients-hero
        data-reveal="hero"
        class="relative overflow-hidden p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
        :style="revealStyle('--spring-patients-hero-o')"
      >
        <DentalMotifLayer variant="hero" data-hero-motif />
        <div class="relative flex items-center justify-between gap-3">
          <!-- Brand lockup: tooth glyph + wordmark on accent-500. -->
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
          <div class="flex items-center gap-3" data-hero-actions>
            <UiButton variant="ghost" size="sm" data-back-button @click="goBack">
              <template #icon-left>
                <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Volver
            </UiButton>
            <UiButton
              v-if="can.createPatient?.value"
              variant="primary"
              size="sm"
              data-new-patient-button
              @click="openPatientForm('create')"
            >
              <template #icon-left>
                <PlusIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Nuevo paciente
            </UiButton>
          </div>
        </div>
        <h1 class="page-title relative mt-3 text-4xl font-semibold text-label">Pacientes</h1>
        <p class="relative mt-2 text-base leading-relaxed text-theme-secondary">
          Gestiona la información de tus pacientes
        </p>
      </header>

      <!-- Summary block: dashboard KPI anatomy (non-interactive). -->
      <section
        ref="summarySection"
        data-patients-block="summary"
        data-reveal="summary"
        class="space-y-4"
        :style="revealStyle('--spring-patients-summary-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-xl font-semibold text-label">Resumen de pacientes</h2>
          <p class="text-sm text-theme-secondary">Los totales de la ficha de pacientes</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <UiCard
            variant="glass"
            padding="sm"
            data-stat-card="total-patients"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Total Pacientes
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  aria-live="polite"
                  data-stat="total-patients"
                >
                  {{ totalPatients }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="total-patients"
                >
                  Activos e inactivos
                </p>
              </div>
            </div>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-stat-card="active-patients"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Activos
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  aria-live="polite"
                  data-stat="active-patients"
                >
                  {{ activePatients }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="active-patients"
                >
                  Disponibles para atender
                </p>
              </div>
            </div>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-stat-card="inactive-patients"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Inactivos
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  aria-live="polite"
                  data-stat="inactive-patients"
                >
                  {{ inactivePatients }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="inactive-patients"
                >
                  No disponibles para atender
                </p>
              </div>
            </div>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-stat-card="filtered-patients"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Filtrados
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  aria-live="polite"
                  data-stat="filtered-patients"
                >
                  {{ filteredPatients.length }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="filtered-patients"
                >
                  Resultados en esta página
                </p>
              </div>
            </div>
          </UiCard>
        </div>
      </section>

      <!-- Directory block: the list protagonists and their controls. -->
      <section
        ref="directorySection"
        data-patients-block="directory"
        data-reveal="directory"
        class="space-y-4"
        :style="revealStyle('--spring-patients-directory-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Directorio de pacientes</h2>
          <p class="text-sm text-theme-secondary">Busca, filtra y gestiona cada ficha</p>
        </div>

        <!-- Search and Filters -->
        <UiCard variant="glass">
          <div class="flex flex-col lg:flex-row gap-4">
            <div class="flex-1">
              <UiInput
                v-model="searchQuery"
                placeholder="Buscar por nombre, DNI, teléfono o email..."
                class="w-full"
                @input="searchPatients"
              >
                <template #prefix>
                  <MagnifyingGlassIcon class="w-5 h-5 text-theme-secondary" aria-hidden="true" />
                </template>
              </UiInput>
            </div>
            <div class="flex flex-wrap gap-3">
              <UiSelect
                v-if="branchOptions.length > 0"
                v-model="branchFilter"
                :options="branchOptions"
                placeholder="Todas las sucursales"
                size="sm"
                class="min-w-[160px]"
                @update:model-value="loadPatients(1)"
              />
              <UiSelect
                v-model="statusFilter"
                :options="statusFilterOptions"
                size="sm"
                class="min-w-[140px]"
                @update:model-value="loadPatients(1)"
              />
              <UiSelect
                v-model="sortFilter"
                :options="sortFilterOptions"
                size="sm"
                class="min-w-[170px]"
                @update:model-value="loadPatients(1)"
              />
              <UiSelect
                v-model="perPageFilter"
                :options="perPageOptions"
                size="sm"
                class="min-w-[120px]"
                @update:model-value="loadPatients(1)"
              />
              <UiButton variant="secondary" class="px-3" @click="resetFilters">
                <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
              </UiButton>
            </div>
          </div>
        </UiCard>

        <!-- Patients List -->
        <UiCard variant="glass" class="overflow-hidden">
          <!-- Unified states: loading, inline error (retry), empty (the hero owns the CTA). -->
          <LoadingSpinner v-if="loading" class="p-12" size="lg" text="Cargando pacientes..." />

          <!-- Error State -->
          <UiSectionEmpty
            v-else-if="loadError"
            data-state="error-patients"
            title="No pudimos cargar los pacientes"
            description="Revisa tu conexión e inténtalo de nuevo."
          >
            <UiButton variant="secondary" size="sm" data-retry-patients @click="loadPatients(1)">
              Reintentar
            </UiButton>
          </UiSectionEmpty>

          <!-- Empty State -->
          <UiSectionEmpty
            v-else-if="filteredPatients.length === 0"
            data-state="empty-patients"
            title="No se encontraron pacientes"
            description="Intenta ajustar los filtros de búsqueda"
          />

          <!-- Desktop Table View -->
          <div v-else data-patient-surface="table" class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-[color:var(--color-hairline)]">
              <thead class="bg-theme-surface">
                <tr>
                  <th
                    class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                  >
                    Paciente
                  </th>
                  <th
                    class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                  >
                    Contacto
                  </th>
                  <th
                    class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                  >
                    Fecha de Nacimiento
                  </th>
                  <th
                    class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                  >
                    Edad
                  </th>
                  <th
                    class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                  >
                    Estado
                  </th>
                  <th
                    class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                  >
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody class="bg-theme-surface-elevated divide-y divide-[color:var(--color-hairline)]">
                <PatientListRow
                  v-for="patient in filteredPatients"
                  :key="patient.id"
                  variant="table"
                  :patient="patient"
                  :can-edit="can.editPatient?.value"
                  :can-delete="can.deletePatient?.value"
                  @view="viewPatient"
                  @edit="editPatient"
                  @delete="deletePatient"
                />
              </tbody>
            </table>
          </div>

          <!-- Mobile Card View -->
          <div
            v-if="!loading && !loadError && filteredPatients.length > 0"
            data-patient-surface="cards"
            class="lg:hidden space-y-4 p-4"
          >
            <PatientListRow
              v-for="patient in filteredPatients"
              :key="patient.id"
              variant="card"
              :patient="patient"
              :can-edit="can.editPatient?.value"
              :can-delete="can.deletePatient?.value"
              @view="viewPatient"
              @edit="editPatient"
              @delete="deletePatient"
            />
          </div>

          <!-- Pagination Controls -->
          <div
            v-if="!loading && !loadError && paginationMeta.last_page > 1"
            class="p-4 border-t border-hairline"
          >
            <Pagination
              :current-page="paginationMeta.current_page"
              :total-pages="paginationMeta.last_page"
              :total="paginationMeta.total"
              :per-page="paginationMeta.per_page"
              @page-change="handlePageChange"
            />
          </div>
        </UiCard>
      </section>
    </div>

    <!-- Shared create/edit patient form -->
    <PatientFormModal
      v-if="showPatientFormModal"
      :mode="patientFormMode"
      :patient="patientFormPayload"
      @close="closePatientForm"
      @saved="handlePatientSaved"
    />
  </AppLayout>
</template>

<script>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'
import { useEcho } from '../../composables/useEcho'
import { useSpring } from '../../composables/useSpring'
import { prefersReducedMotion } from '../../composables/useSpringMath'
import AppLayout from '../../components/layout/AppLayout.vue'
import DentalMotifLayer from '../../components/ui/DentalMotifLayer.vue'
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
import Pagination from '../../components/ui/Pagination.vue'
import PatientFormModal from './PatientFormModal.vue'
import PatientListRow from './PatientListRow.vue'

export default {
  name: 'PatientsPage',
  components: {
    AppLayout,
    ArrowLeftIcon,
    ArrowPathIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    DentalMotifLayer,
    UiCard,
    UiButton,
    UiInput,
    UiSelect,
    UiSectionEmpty,
    Pagination,
    PatientFormModal,
    PatientListRow
  },
  setup() {
    const router = useRouter()
    const { get, delete: del } = useApi()
    const { can } = usePermissions()
    const toast = useToast()
    const { confirm } = useConfirm()
    const { channel, echo } = useEcho()

    // can ya es un objeto con propiedades computed, usarlo directamente

    const loading = ref(false)
    const loadError = ref(false)
    const patients = ref([])
    const filteredPatients = ref([])
    const paginationMeta = ref({
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0
    })
    const searchQuery = ref('')
    const statusFilter = ref('')
    const statusFilterOptions = [
      { value: '', label: 'Todos' },
      { value: 'active', label: 'Activos' },
      { value: 'inactive', label: 'Inactivos' }
    ]
    const branchFilter = ref('')
    const branchOptions = ref([])
    const sortFilter = ref('last_name-asc')
    const sortFilterOptions = [
      { value: 'last_name-asc', label: 'Apellidos A-Z' },
      { value: 'last_name-desc', label: 'Apellidos Z-A' },
      { value: 'first_name-asc', label: 'Nombre A-Z' },
      { value: 'created_at-desc', label: 'Más recientes' }
    ]
    const perPageFilter = ref(15)
    const perPageOptions = [
      { value: 15, label: '15 por página' },
      { value: 30, label: '30 por página' },
      { value: 50, label: '50 por página' }
    ]
    const showPatientFormModal = ref(false)
    const patientFormMode = ref('create')
    const patientFormPayload = ref(null)

    const openPatientForm = (mode, patient = null) => {
      patientFormMode.value = mode
      patientFormPayload.value = patient ? { ...patient } : null
      showPatientFormModal.value = true
    }

    const closePatientForm = () => {
      showPatientFormModal.value = false
      patientFormPayload.value = null
    }

    // Almacenar totales de activos/inactivos desde el backend
    const activeCount = ref(0)
    const inactiveCount = ref(0)

    const totalPatients = computed(() => {
      // Si hay filtro aplicado, mostrar el total filtrado
      // Si no hay filtro, mostrar el total general (activos + inactivos)
      if (statusFilter.value === 'active') {
        return paginationMeta.value?.total || 0
      } else if (statusFilter.value === 'inactive') {
        return paginationMeta.value?.total || 0
      }
      // Sin filtro: mostrar total general
      return (activeCount.value || 0) + (inactiveCount.value || 0)
    })

    const activePatients = computed(() => {
      // Si estamos filtrando por activos, mostrar el total filtrado
      if (statusFilter.value === 'active') {
        return paginationMeta.value?.total || 0
      }
      // Si estamos filtrando por inactivos, mostrar 0
      if (statusFilter.value === 'inactive') {
        return 0
      }
      // Sin filtro: mostrar el total de activos desde el backend
      return activeCount.value || 0
    })

    const inactivePatients = computed(() => {
      // Si estamos filtrando por inactivos, mostrar el total filtrado
      if (statusFilter.value === 'inactive') {
        return paginationMeta.value?.total || 0
      }
      // Si estamos filtrando por activos, mostrar 0
      if (statusFilter.value === 'active') {
        return 0
      }
      // Sin filtro: mostrar el total de inactivos desde el backend
      return inactiveCount.value || 0
    })

    const loadPatients = async (page = 1) => {
      loading.value = true
      loadError.value = false
      try {
        // Construir parámetros de query
        const params = new URLSearchParams()
        params.append('page', page.toString())

        // Solo agregar filtro de estado si el usuario lo ha seleccionado explícitamente
        if (statusFilter.value === 'active') {
          params.append('active', 'true')
        } else if (statusFilter.value === 'inactive') {
          params.append('active', 'false')
        }
        // Si statusFilter.value está vacío o es 'all', no se envía el parámetro (muestra todos)

        // Agregar búsqueda si existe
        if (searchQuery.value) {
          params.append('search', searchQuery.value)
        }

        params.append('per_page', perPageFilter.value.toString())

        if (branchFilter.value) {
          params.append('branch_id', branchFilter.value)
        }

        const [sort, direction] = sortFilter.value.split('-')
        params.append('sort', sort)
        params.append('direction', direction)

        const response = await get(`/api/patients?${params.toString()}`)
        patients.value = response?.data || []
        filteredPatients.value = response?.data || []

        // Guardar metadatos de paginación
        if (response?.meta) {
          paginationMeta.value = {
            current_page: response.meta.current_page || 1,
            last_page: response.meta.last_page || 1,
            per_page: response.meta.per_page || 15,
            total: response.meta.total || 0
          }

          // Guardar totales de activos e inactivos desde el backend
          if (response.meta.active_count !== undefined) {
            activeCount.value = response.meta.active_count
          }
          if (response.meta.inactive_count !== undefined) {
            inactiveCount.value = response.meta.inactive_count
          }
        }

        if (patients.value.length === 0 && paginationMeta.value.total === 0) {
          toast.warning('No se encontraron pacientes')
        }
      } catch (error) {
        loadError.value = true
        toast.error('Error al cargar los pacientes. Por favor, recarga la página.')
        patients.value = []
        filteredPatients.value = []
        paginationMeta.value = {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 0
        }
      } finally {
        loading.value = false
      }

      // One-shot section entrance on the first successful content render;
      // a failed load keeps it armed for the retry.
      if (!entrancePlayed && !loadError.value) {
        await nextTick()
        playEntrance()
      }
    }

    let searchDebounceTimer = null

    const searchPatients = () => {
      if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer)
      }
      searchDebounceTimer = setTimeout(() => {
        searchDebounceTimer = null
        loadPatients(1)
      }, 300)
    }

    const filterPatients = () => {
      // Ya no filtramos en el cliente, el servidor lo hace
      // Solo actualizamos filteredPatients con los datos del servidor
      filteredPatients.value = patients.value
    }

    const resetFilters = () => {
      searchQuery.value = ''
      statusFilter.value = ''
      branchFilter.value = ''
      perPageFilter.value = 15
      sortFilter.value = 'last_name-asc'
      // Recargar desde el servidor sin filtros
      loadPatients(1)
    }

    const handlePageChange = (page) => {
      loadPatients(page)
    }

    const loadBranches = async () => {
      try {
        const response = await get('/api/branches/active')
        branchOptions.value = (response?.data || []).map(branch => ({
          value: String(branch.id),
          label: branch.name
        }))
      } catch (error) {
        branchOptions.value = []
      }
    }

    const addPatientToList = patientData => {
      let shouldAdd = true

      if (statusFilter.value === 'active' && !patientData.is_active) {
        shouldAdd = false
      } else if (statusFilter.value === 'inactive' && patientData.is_active) {
        shouldAdd = false
      }

      if (shouldAdd && searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        const matchesSearch =
          (patientData.first_name || '').toLowerCase().includes(query) ||
          (patientData.last_name || '').toLowerCase().includes(query) ||
          (patientData.email || '').toLowerCase().includes(query) ||
          (patientData.phone || '').includes(query) ||
          (patientData.document_number || '').toLowerCase().includes(query)

        if (!matchesSearch) {
          shouldAdd = false
        }
      }

      if (shouldAdd) {
        const insertIndex = patients.value.findIndex(p => {
          const newLastName = (patientData.last_name || '').toLowerCase()
          const currentLastName = (p.last_name || '').toLowerCase()
          return (
            currentLastName > newLastName ||
            (currentLastName === newLastName &&
              (p.first_name || '').toLowerCase() > (patientData.first_name || '').toLowerCase())
          )
        })

        if (insertIndex === -1) {
          patients.value.push(patientData)
        } else {
          patients.value.splice(insertIndex, 0, patientData)
        }

        filteredPatients.value = patients.value
      }

      if (paginationMeta.value.total !== undefined) {
        paginationMeta.value.total += 1
      }
    }

    const handlePatientSaved = async ({ mode, patient }) => {
      if (mode === 'edit') {
        await loadPatients()

        toast.success(`Paciente actualizado: ${patient.first_name} ${patient.last_name}`, {
          duration: 5000,
          title: '✓ Paciente Actualizado'
        })
        return
      }

      addPatientToList(patient)

      toast.success(
        `Paciente registrado: ${patient.first_name} ${patient.last_name}\n` +
        `Email: ${patient.email}\n` +
        `Teléfono: ${patient.phone}`,
        {
          duration: 5000,
          title: '✓ Paciente Creado'
        }
      )
    }

    const editPatient = patient => {
      openPatientForm('edit', patient)
    }

    const viewPatient = (patient) => {
      router.push(`/patients/${patient.id}`)
    }

    const deletePatient = async (patient) => {
      const ok = await confirm({
        title: 'Eliminar paciente',
        message: `¿Estás seguro de que quieres eliminar a ${patient.first_name} ${patient.last_name}?`,
        confirmText: 'Eliminar',
        variant: 'danger',
      })
      if (ok) {
        try {
          await del(`/api/patients/${patient.id}`)

          // Recargar la página actual para asegurar que el paciente se elimine de la lista
          // y que la paginación se actualice correctamente desde el servidor
          await loadPatients(paginationMeta.value.current_page)

          // Notificación de éxito
          toast.success(
            `Paciente eliminado: ${patient.first_name} ${patient.last_name}`,
            {
              duration: 4000,
              title: '✓ Paciente Eliminado'
            }
          )
        } catch (error) {
          // Notificación de error
          const errorMessage = error.response?.data?.message
            || error.response?.data?.errors?.patient?.[0]
            || 'No se pudo eliminar el paciente. Puede tener citas asociadas.'
          toast.error(
            errorMessage,
            {
              duration: 8000,
              title: '✗ Error al Eliminar'
            }
          )
        }
      }
    }

    const formatDate = (date) => {
      if (!date) return 'N/A'
      return new Date(date).toLocaleDateString('es-ES')
    }

    const goBack = () => {
      router.back()
    }

    // One-shot staggered entrance (60ms) per section, mirroring the
    // dashboard pattern. revealStyle() renders the FINAL state whenever no
    // spring is attached (reduced motion, or a section mounting later).
    const heroSpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patients-hero-o'
    })
    const summarySpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patients-summary-o'
    })
    const directorySpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patients-directory-o'
    })

    const heroSection = ref(null)
    const summarySection = ref(null)
    const directorySection = ref(null)

    const revealStyle = cssVar => ({
      opacity: `var(${cssVar}, 1)`,
      transform: `translateY(calc((1 - var(${cssVar}, 1)) * 8px))`
    })

    let entrancePlayed = false
    const playEntrance = () => {
      if (entrancePlayed) return
      entrancePlayed = true
      if (prefersReducedMotion()) return

      if (heroSection.value) heroSpring.attach(heroSection.value)
      if (summarySection.value) summarySpring.attach(summarySection.value)
      if (directorySection.value) directorySpring.attach(directorySection.value)

      setTimeout(() => heroSpring.set(1), 0)
      setTimeout(() => summarySpring.set(1), 60)
      setTimeout(() => directorySpring.set(1), 120)
    }

    // WebSocket subscriptions
    let patientsChannel = null

    onMounted(() => {
      loadPatients()
      loadBranches()

      // Suscribirse a canales WebSocket para actualizaciones en tiempo real
      try {
        patientsChannel = channel('patients')
        if (patientsChannel) {
          patientsChannel
            .listen('.patient.created', async (e) => {
              const newPatient = e.patient

              if (newPatient) {
                addPatientToList(newPatient)
              }

              toast.success('Nuevo paciente agregado')
            })
            .listen('.patient.updated', async (e) => {
              // Actualizar el paciente en la lista si existe
              const index = patients.value.findIndex(p => p.id === e.patient.id)
              if (index !== -1) {
                patients.value[index] = e.patient
                filterPatients() // Re-aplicar filtros
              } else {
                await loadPatients()
              }
              toast.success('Paciente actualizado')
            })
            .listen('.patient.deleted', async (e) => {
              // Remover el paciente de la lista
              patients.value = patients.value.filter(p => p.id !== e.patient_id)
              filteredPatients.value = filteredPatients.value.filter(p => p.id !== e.patient_id)
              toast.success('Paciente eliminado')
            })
        }
      } catch (error) {
        // Sin WebSocket la lista sigue operativa vía fetch inicial
      }
    })

    onUnmounted(() => {
      if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer)
      }

      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('patients')
        } catch (e) {
          // El canal ya estaba cerrado, nada que limpiar
        }
      }
    })

    return {
      can,
      loading,
      loadError,
      patients,
      filteredPatients,
      searchQuery,
      statusFilter,
      statusFilterOptions,
      branchFilter,
      branchOptions,
      sortFilter,
      sortFilterOptions,
      perPageFilter,
      perPageOptions,
      showPatientFormModal,
      patientFormMode,
      patientFormPayload,
      totalPatients,
      activePatients,
      inactivePatients,
      paginationMeta,
      heroSection,
      summarySection,
      directorySection,
      revealStyle,
      loadPatients,
      handlePageChange,
      searchPatients,
      filterPatients,
      resetFilters,
      openPatientForm,
      closePatientForm,
      handlePatientSaved,
      editPatient,
      viewPatient,
      deletePatient,
      formatDate,
      goBack
    }
  }
}
</script>

