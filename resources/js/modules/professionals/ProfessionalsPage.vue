<template>
  <AppLayout>
    <!--
      Canonical page header: one component, one anatomy. Kicker names the
      surface, the primary CTA and the shared update action sit on the right.
    -->
    <PageHeader
      kicker="Profesionales"
      title="Profesionales"
      subtitle="Odontólogos, implantólogos, técnicos y asistentes"
      class="mb-6"
    >
      <template #actions>
        <UiButton
          variant="secondary"
          data-refresh-professionals
          :disabled="loading"
          @click="loadProfessionals"
        >
          <template #icon-left>
            <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Actualizar
        </UiButton>
        <UiButton v-if="can.manageUsers.value" data-new-professional @click="openCreateModal">
          <template #icon-left>
            <PlusIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Nuevo profesional
        </UiButton>
      </template>
    </PageHeader>

    <!--
      Team KPI strip (D3): four counters derived from the same collection the
      table renders. Number + one scope caption each; no comparison chips and
      no reserved blank slots (honesty rule inherited from the dashboard).
    -->
    <div data-team-kpis class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <UiCard
        variant="glass"
        padding="sm"
        data-stat-card="team-total"
        class="relative h-full flex flex-col"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
            Total equipo
          </p>
          <p
            class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
            data-stat="team-total"
          >
            {{ kpiValue(teamKpis.total) }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary line-clamp-2" data-kpi-caption="team-total">
            Equipo clínico completo
          </p>
        </div>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-stat-card="team-active"
        class="relative h-full flex flex-col"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
            Activos
          </p>
          <p
            class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
            data-stat="team-active"
          >
            {{ kpiValue(teamKpis.active) }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary line-clamp-2" data-kpi-caption="team-active">
            Con cuenta habilitada
          </p>
        </div>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-stat-card="team-inactive"
        class="relative h-full flex flex-col"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
            Inactivos
          </p>
          <p
            class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
            data-stat="team-inactive"
          >
            {{ kpiValue(teamKpis.inactive) }}
          </p>
          <p
            class="mt-1 text-xs text-theme-secondary line-clamp-2"
            data-kpi-caption="team-inactive"
          >
            Sin cuenta habilitada
          </p>
        </div>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-stat-card="team-specialties"
        class="relative h-full flex flex-col"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
            Especialidades representadas
          </p>
          <p
            class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
            data-stat="team-specialties"
          >
            {{ kpiValue(teamKpis.specialties) }}
          </p>
          <p
            class="mt-1 text-xs text-theme-secondary line-clamp-2"
            data-kpi-caption="team-specialties"
          >
            Distintas con al menos un clínico
          </p>
        </div>
      </UiCard>
    </div>

    <!--
      Team list (D5): the list is the protagonist surface. One block heading
      tiered under the hero display owns the section; the results caption and
      the filter bar belong to this block.
    -->
    <section data-team-list>
      <div class="mb-4">
        <h2 class="text-xl font-semibold text-label">Equipo</h2>
        <p class="mt-1 text-sm text-theme-secondary" data-team-results>{{ resultsCaption }}</p>
      </div>

      <UiFilterBar :columns="4" class="mb-4" data-team-filters>
        <div class="sm:col-span-2">
          <UiInput
            v-model="searchQuery"
            data-professionals-search
            placeholder="Buscar profesionales por nombre, especialidad o email..."
            class="w-full"
          >
            <template #prefix>
              <svg
                class="w-5 h-5 text-theme-secondary"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
              </svg>
            </template>
          </UiInput>
        </div>
        <div>
          <label
            for="professionals-filter-specialty"
            class="mb-1 block text-xs font-medium text-theme-secondary"
          >
            Especialidad
          </label>
          <UiSelect
            id="professionals-filter-specialty"
            v-model="specialtyFilter"
            data-professionals-specialty-filter
            :options="specialtyFilterOptions"
            placeholder="Todas las especialidades"
            class="w-full"
          />
        </div>
        <div class="flex items-end">
          <UiButton
            v-if="hasActiveFilters"
            variant="secondary"
            class="w-full"
            data-team-clear-filters
            @click="clearFilters"
          >
            Limpiar filtros
          </UiButton>
        </div>
      </UiFilterBar>

      <UiCard variant="glass" class="overflow-hidden">
        <div v-if="loading" data-team-loading class="p-6 md:p-8">
          <UiSkeleton variant="table" animation="wave" aria-label="Cargando profesionales..." />
        </div>

        <div v-else-if="loadError" class="p-6 md:p-8">
          <div
            data-team-error
            role="alert"
            class="flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-panel)] border border-hairline bg-systemRed-50 p-5"
          >
            <div class="flex min-w-0 items-center gap-3">
              <svg
                class="h-6 w-6 flex-shrink-0 text-systemRed-600"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.051 3.378c.866-1.5 3.032-1.5 3.898 0l7.354 12.748zM12 15.75h.007v.008H12v-.008z"
                />
              </svg>
              <div class="min-w-0">
                <p class="text-sm font-medium text-label">No pudimos cargar los profesionales</p>
                <p class="text-sm text-theme-secondary">
                  Revisa tu conexión y vuelve a intentarlo.
                </p>
              </div>
            </div>
            <UiButton variant="secondary" size="sm" data-team-retry @click="loadProfessionals">
              Reintentar
            </UiButton>
          </div>
        </div>

        <div v-else-if="showEmptyTeam" class="p-8">
          <UiEmptyState
            title="Sin profesionales registrados"
            description="Aún no hay nadie en el equipo clínico."
          />
        </div>

        <div v-else-if="showEmptyResults" class="p-8">
          <UiEmptyState
            title="Sin resultados"
            description="Ningún profesional coincide con los filtros activos."
          >
            <template #action>
              <UiButton variant="secondary" size="sm" data-team-clear-filters @click="clearFilters">
                Limpiar filtros
              </UiButton>
            </template>
          </UiEmptyState>
        </div>

        <!--
          #453 (U11, list): the team list rides `UiDataTable`, so the
          horizontal scroll lives inside the table (with its affordance)
          instead of opening a second axis on the page. The row hook travels
          through `rowAttributes` and every cell keeps its content.
        -->
        <UiDataTable
          v-else
          :data="professionals"
          :columns="teamColumns"
          :searchable="false"
          :show-header="false"
          :pagination="false"
          row-key="id"
          table-class="min-w-[960px]"
          :row-attributes="teamRowAttributes"
          data-team-table
        >
          <template #cell-name="{ row }">
            <div class="flex items-center">
              <div class="min-w-0">
                <div class="text-sm font-medium text-[color:var(--color-text-primary)] truncate">
                  {{ row.name }}
                </div>
                <div class="text-xs text-theme-secondary truncate">
                  {{ secondaryLine(row) }}
                </div>
              </div>
            </div>
          </template>
          <template #cell-specialty="{ row }">
            <UiBadge variant="primary" size="sm" shape="pill">
              {{ getSpecialtyText(row.specialty) }}
            </UiBadge>
          </template>
          <template #cell-role="{ row }">
            <UiBadge variant="neutral" size="sm" shape="pill">
              {{ roleLabel(row.role) }}
            </UiBadge>
          </template>
          <template #cell-license="{ row }">
            <span class="whitespace-nowrap text-sm text-[color:var(--color-text-primary)]">
              {{ professionalLicense(row) }}
            </span>
          </template>
          <template #cell-branch="{ row }">
            <span class="whitespace-nowrap text-sm text-[color:var(--color-text-primary)]">
              {{ branchName(row) }}
            </span>
          </template>
          <template #cell-status="{ row }">
            <UiStatusBadge
              :variant="rowStatusVariant(row.is_active)"
              :label="row.is_active ? 'Activo' : 'Inactivo'"
              size="sm"
            />
          </template>
          <template #actions="{ row }">
            <div class="flex flex-nowrap items-center gap-1.5">
              <UiButton
                variant="ghost"
                size="sm"
                :class="ROW_ACTION_PALETTE.view.classes"
                @click="viewDetail(row)"
              >
                Ver ficha
              </UiButton>
              <UiButton
                v-if="can.manageUsers.value"
                variant="ghost"
                size="sm"
                :class="ROW_ACTION_PALETTE.edit.classes"
                @click="editProfessional(row)"
              >
                Editar
              </UiButton>
              <UiButton
                v-if="can.manageUsers.value"
                variant="ghost"
                size="sm"
                :class="ROW_ACTION_PALETTE.delete.classes"
                @click="deleteProfessional(row)"
              >
                Eliminar
              </UiButton>
            </div>
          </template>
        </UiDataTable>
      </UiCard>
    </section>
  </AppLayout>

  <!-- New Professional Modal -->
  <UiModal v-model="showNewProfessionalModal" title="Nuevo profesional" size="md">
    <form
      id="form-new-prof"
      class="space-y-4"
      @submit.prevent="createProfessional"
      @keydown.enter.prevent="onFormEnter($event, createProfessional)"
    >
      <UiInput
        v-model="newProfessional.name"
        data-professionals-form-name
        label="Nombre Completo"
        type="text"
        required
        :error="createErrors.name"
      />
      <UiInput v-model="newProfessional.username" label="Usuario" type="text" required />
      <UiInput
        v-model="newProfessional.email"
        data-professionals-form-email
        label="Email"
        type="email"
        required
        :error="createErrors.email"
      />
      <UiInput v-model="newProfessional.phone" label="Teléfono" type="tel" />
      <UiInput
        v-model="newProfessional.professional_license"
        data-professionals-form-license
        label="Matrícula"
        type="text"
        :error="createErrors.professional_license"
      />
      <UiSelect
        v-model="newProfessional.specialty"
        :options="specialtyOptions"
        label="Especialidad"
        placeholder="Seleccionar especialidad"
        required
      />
      <UiSelect
        v-model="newProfessional.role"
        data-professionals-role
        :options="roleOptions"
        label="Rol"
        placeholder="Seleccionar rol"
        required
        :error="createErrors.role"
      />
      <UiSelect
        v-model="newProfessional.branch_id"
        data-professionals-form-branch
        :options="branchOptions"
        label="Sucursal"
        placeholder="Sin sucursal"
        :error="createErrors.branch_id"
      />
      <UiSelect
        v-model="newProfessional.is_active"
        data-professionals-form-status
        :options="statusOptions"
        label="Estado"
        :error="createErrors.is_active"
      />
      <UiInput v-model="newProfessional.password" label="Contraseña" type="password" required />
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="creating" @click="showNewProfessionalModal = false">
        Cancelar
      </UiButton>
      <UiButton
        type="button"
        data-professionals-save-create
        :loading="creating"
        @click="createProfessional"
      >
        Guardar profesional
      </UiButton>
    </template>
  </UiModal>

  <!-- Edit Professional Modal -->
  <UiModal v-model="showEditProfessionalModal" title="Editar profesional" size="md">
    <form
      v-if="editingProfessional"
      id="form-edit-prof"
      class="space-y-4"
      @submit.prevent="updateProfessional"
      @keydown.enter.prevent="onFormEnter($event, updateProfessional)"
    >
      <UiInput
        v-model="editingProfessional.name"
        data-professionals-edit-name
        label="Nombre"
        type="text"
        required
        :error="editErrors.name"
      />
      <UiInput v-model="editingProfessional.username" label="Usuario" type="text" required />
      <UiInput
        v-model="editingProfessional.email"
        data-professionals-edit-email
        label="Email"
        type="email"
        required
        :error="editErrors.email"
      />
      <UiInput v-model="editingProfessional.phone" label="Teléfono" type="tel" />
      <UiInput
        v-model="editingProfessional.professional_license"
        data-professionals-edit-license
        label="Matrícula"
        type="text"
        :error="editErrors.professional_license"
      />
      <UiSelect
        v-model="editingProfessional.specialty"
        :options="editSpecialtyOptions"
        label="Especialidad"
        placeholder="Seleccionar especialidad"
      />
      <UiSelect
        v-model="editingProfessional.role"
        data-professionals-role-edit
        :options="roleOptions"
        label="Rol"
        placeholder="Seleccionar rol"
        required
        :error="editErrors.role"
      />
      <UiSelect
        v-model="editingProfessional.branch_id"
        data-professionals-edit-branch
        :options="branchOptions"
        label="Sucursal"
        placeholder="Sin sucursal"
        :error="editErrors.branch_id"
      />
      <UiSelect
        v-model="editingProfessional.is_active"
        data-professionals-edit-status
        :options="statusOptions"
        label="Estado"
        :error="editErrors.is_active"
      />
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="updating" @click="showEditProfessionalModal = false">
        Cancelar
      </UiButton>
      <UiButton
        type="button"
        data-professionals-save-edit
        :loading="updating"
        @click="updateProfessional"
      >
        Actualizar
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowPathIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useErrorHandler } from '../../composables/useErrorHandler'
import { useConfirm } from '../../composables/useConfirm'
import { usePermissions } from '../../composables/usePermissions'
import { ROW_ACTION_PALETTE, rowStatusVariant } from '../../design-system/rowActionPalette'
import AppLayout from '../../components/layout/AppLayout.vue'
import PageHeader from '../../components/layout/PageHeader.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiFilterBar from '../../components/ui/FilterBar.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import {
  SPECIALTY_LABELS,
  SPECIALTY_OPTIONS,
  CLINICAL_ROLES,
  ROLE_LABELS
} from './professionals.constants'

const router = useRouter()
const { get, post, put, delete: remove } = useApi()
const toast = useToast()
const { handleError } = useErrorHandler()
const { confirm } = useConfirm()
const { can } = usePermissions()

// #452 (U11, states): the first frame is the placeholder, not an empty team.
const loading = ref(true)
const loadError = ref(false)
const creating = ref(false)
const updating = ref(false)
const team = ref([])
const branchesById = ref({})
const searchQuery = ref('')
const specialtyFilter = ref('')
const specialtyOptions = SPECIALTY_OPTIONS
const showNewProfessionalModal = ref(false)
const showEditProfessionalModal = ref(false)
const editingProfessional = ref(null)
const createErrors = ref({})
const editErrors = ref({})

const emptyProfessional = () => ({
  name: '',
  username: '',
  email: '',
  phone: '',
  professional_license: '',
  specialty: '',
  role: 'odontologo',
  branch_id: null,
  is_active: true,
  password: ''
})

const newProfessional = ref(emptyProfessional())

const statusOptions = [
  { value: true, label: 'Activo' },
  { value: false, label: 'Inactivo' }
]

const getSpecialtyText = specialty => {
  return SPECIALTY_LABELS[specialty] || specialty
}

const specialtyFilterOptions = computed(() => {
  const options = [...SPECIALTY_OPTIONS]
  const known = new Set(SPECIALTY_OPTIONS.map(option => option.value))
  for (const member of team.value) {
    const specialty = typeof member.specialty === 'string' ? member.specialty.trim() : ''
    if (!specialty || known.has(specialty)) continue
    known.add(specialty)
    options.push({ value: specialty, label: getSpecialtyText(specialty) })
  }
  return options
})

const editSpecialtyOptions = computed(() => {
  const current = editingProfessional.value?.specialty
  if (!current) return SPECIALTY_OPTIONS
  if (SPECIALTY_OPTIONS.some(option => option.value === current)) return SPECIALTY_OPTIONS
  return [...SPECIALTY_OPTIONS, { value: current, label: getSpecialtyText(current) }]
})

const roleOptions = CLINICAL_ROLES.map(role => ({ value: role, label: ROLE_LABELS[role] || role }))

const branchOptions = computed(() =>
  Object.entries(branchesById.value).map(([id, name]) => ({ value: Number(id), label: name }))
)

const roleLabel = role => ROLE_LABELS[role] || role || 'N/D'

const professionalLicense = professional => {
  const license =
    typeof professional.professional_license === 'string'
      ? professional.professional_license.trim()
      : ''
  return license || 'N/D'
}

const branchName = professional => {
  const branchId = professional.branch_id
  if (branchId === null || branchId === undefined || branchId === '') return 'Sin sucursal'
  return branchesById.value[branchId] || 'N/D'
}

const secondaryLine = professional => {
  const parts = [professional.username, professional.email].filter(Boolean)
  return parts.length > 0 ? parts.join(' · ') : 'N/D'
}

// #441: a failed read is not an empty team, so the four counters declare the
// missing data instead of reporting zeros.
const kpiValue = value => (loadError.value ? 'N/D' : value)

const teamKpis = computed(() => {
  let active = 0
  const specialties = new Set()
  for (const member of team.value) {
    if (member.is_active) active += 1
    const specialty = typeof member.specialty === 'string' ? member.specialty.trim() : ''
    if (specialty) specialties.add(specialty)
  }
  return {
    total: team.value.length,
    active,
    inactive: team.value.length - active,
    specialties: specialties.size
  }
})

const professionals = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  return team.value.filter(member => {
    const specialty = typeof member.specialty === 'string' ? member.specialty : ''
    if (specialtyFilter.value && specialty !== specialtyFilter.value) return false
    if (!query) return true
    return [
      member.name,
      member.username,
      member.email,
      specialty,
      getSpecialtyText(specialty)
    ].some(value =>
      String(value ?? '')
        .toLowerCase()
        .includes(query)
    )
  })
})

// #453: the columns of the team list. The API returns the whole clinical team
// in one read (no pagination), so the primitive renders it as-is. Matrícula and
// Sucursal left the list: every row rendered the same "N/D" and "Sin sucursal",
// and the two columns pushed the row actions out of the viewport.
const teamColumns = [
  { key: 'name', label: 'Profesional' },
  { key: 'specialty', label: 'Especialidad' },
  { key: 'role', label: 'Rol' },
  { key: 'status', label: 'Estado' }
]

const teamRowAttributes = row => ({ 'data-team-row': String(row.id) })

const hasActiveFilters = computed(() => Boolean(searchQuery.value.trim() || specialtyFilter.value))

const showEmptyTeam = computed(() => !loading.value && team.value.length === 0)

const showEmptyResults = computed(
  () => !loading.value && team.value.length > 0 && professionals.value.length === 0
)

const resultsCaption = computed(() => {
  const shown = professionals.value.length
  const total = team.value.length
  return hasActiveFilters.value ? `${shown} de ${total} profesionales` : `${total} profesionales`
})

const clearFilters = () => {
  searchQuery.value = ''
  specialtyFilter.value = ''
}

const applyServerErrors = (error, errors) => {
  const fieldErrors = error?.response?.data?.errors
  if (!fieldErrors || typeof fieldErrors !== 'object') return false

  errors.value = Object.fromEntries(
    Object.entries(fieldErrors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : String(messages)
    ])
  )
  return true
}

const onFormEnter = (event, submit) => {
  if (event.target?.closest?.('.select-container')) return
  submit()
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const validateProfessionalForm = (form, errors) => {
  const next = {}
  const name = String(form?.name ?? '').trim()
  const email = String(form?.email ?? '').trim()
  if (!name) next.name = 'El nombre es obligatorio.'
  if (!email) {
    next.email = 'El email es obligatorio.'
  } else if (!EMAIL_PATTERN.test(email)) {
    next.email = 'Ingresa un email válido.'
  }
  if (!form?.role) next.role = 'Selecciona un rol.'
  errors.value = next
  return Object.keys(next).length === 0
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

const loadProfessionals = async () => {
  loading.value = true
  loadError.value = false
  try {
    const response = await get(`/api/users?role=${encodeURIComponent(CLINICAL_ROLES.join(','))}`)
    team.value = response?.data || []
    loadError.value = false
  } catch (error) {
    handleError(error, 'Error al cargar los profesionales. Por favor, recarga la página.')
    team.value = []
    loadError.value = true
  } finally {
    loading.value = false
  }
}

const createProfessional = async () => {
  if (creating.value) return
  if (!validateProfessionalForm(newProfessional.value, createErrors)) return
  creating.value = true
  try {
    await post('/api/users', { ...newProfessional.value })
    showNewProfessionalModal.value = false
    newProfessional.value = emptyProfessional()
    createErrors.value = {}
    toast.success('Profesional creado exitosamente')
    loadProfessionals()
  } catch (error) {
    if (!applyServerErrors(error, createErrors)) {
      handleError(error, 'Error al crear el profesional')
    }
  } finally {
    creating.value = false
  }
}

const openCreateModal = () => {
  createErrors.value = {}
  showNewProfessionalModal.value = true
}

const editProfessional = professional => {
  editingProfessional.value = {
    ...professional,
    professional_license: professional.professional_license || '',
    branch_id: professional.branch_id ?? null,
    is_active: Boolean(professional.is_active)
  }
  editErrors.value = {}
  showEditProfessionalModal.value = true
}

const viewDetail = professional => {
  router.push(`/professionals/${professional.id}`)
}

const updateProfessional = async () => {
  if (updating.value) return
  if (!editingProfessional.value) return
  if (!validateProfessionalForm(editingProfessional.value, editErrors)) return
  updating.value = true
  try {
    await put(`/api/users/${editingProfessional.value.id}`, editingProfessional.value)
    showEditProfessionalModal.value = false
    editingProfessional.value = null
    loadProfessionals()
    toast.success('Profesional actualizado exitosamente')
  } catch (error) {
    if (!applyServerErrors(error, editErrors)) {
      handleError(error, 'Error al actualizar el profesional')
    }
  } finally {
    updating.value = false
  }
}

const deleteProfessional = async professional => {
  const ok = await confirm({
    title: 'Eliminar profesional',
    message: `¿Estás seguro de que quieres eliminar a ${professional.name}?`,
    confirmText: 'Eliminar',
    variant: 'danger'
  })
  if (ok) {
    try {
      await remove(`/api/users/${professional.id}`)
      loadProfessionals()
      toast.success('Profesional eliminado exitosamente')
    } catch (error) {
      handleError(error)
    }
  }
}

onMounted(() => {
  loadBranches()
  loadProfessionals()
})
</script>
