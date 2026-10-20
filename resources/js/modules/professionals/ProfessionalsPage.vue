<template>
  <AppLayout>
    <!--
      Hero band (D4): same anatomy as the dashboard hero (brand lockup +
      DentalMotifLayer variant="hero" + canvas surface with hairline and
      elevation rung). One primary CTA lives here; the caption "Gestión de
      profesionales" stays owned by AppLayout and is never repeated below.
    -->
    <header
      data-professionals-hero
      class="relative overflow-hidden p-6 md:p-8 mb-6"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <DentalMotifLayer variant="hero" />
      <div class="relative flex items-center justify-between gap-3">
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
        <div class="flex items-center gap-3">
          <UiButton @click="openCreateModal">
            <template #icon-left>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                />
              </svg>
            </template>
            Nuevo Profesional
          </UiButton>
        </div>
      </div>
      <p class="relative mt-3 text-4xl font-semibold text-label">Profesionales</p>
      <p class="relative mt-2 text-base leading-relaxed text-theme-secondary">
        Odontólogos, implantólogos, técnicos dentales y asistentes del equipo clínico.
      </p>
    </header>

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
            {{ teamKpis.total }}
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
            {{ teamKpis.active }}
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
            {{ teamKpis.inactive }}
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
            {{ teamKpis.specialties }}
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

      <div data-team-filters class="mb-4">
        <UiCard variant="glass">
          <div class="flex flex-col lg:flex-row gap-4">
            <div class="flex-1">
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
            <div class="flex items-end gap-3">
              <UiSelect
                v-model="specialtyFilter"
                data-professionals-specialty-filter
                :options="specialtyFilterOptions"
                placeholder="Todas las especialidades"
                class="w-64"
              />
              <UiButton
                v-if="hasActiveFilters"
                variant="secondary"
                data-team-clear-filters
                @click="clearFilters"
              >
                Limpiar filtros
              </UiButton>
            </div>
          </div>
        </UiCard>
      </div>

      <UiCard variant="glass" class="overflow-hidden">
        <div v-if="loading" class="p-8 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />
          <p class="mt-2 text-theme-secondary">Cargando profesionales...</p>
        </div>

        <div v-else-if="showEmptyTeam" class="p-8">
          <UiSectionEmpty
            title="Sin profesionales registrados"
            description="Aún no hay nadie en el equipo clínico."
          />
        </div>

        <div v-else-if="showEmptyResults" class="p-8">
          <UiSectionEmpty
            title="Sin resultados"
            description="Ningún profesional coincide con los filtros activos."
          >
            <UiButton variant="secondary" size="sm" data-team-clear-filters @click="clearFilters">
              Limpiar filtros
            </UiButton>
          </UiSectionEmpty>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-theme">
            <thead class="bg-theme-surface">
              <tr>
                <th
                  class="px-3 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                >
                  Profesional
                </th>
                <th
                  class="px-3 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                >
                  Especialidad
                </th>
                <th
                  class="px-3 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                >
                  Rol
                </th>
                <th
                  class="px-3 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                >
                  Matrícula
                </th>
                <th
                  class="px-3 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                >
                  Sucursal
                </th>
                <th
                  class="px-3 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                >
                  Estado
                </th>
                <th
                  class="px-3 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
                >
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="bg-[color:var(--color-surface-elevated)] divide-y divide-theme">
              <tr
                v-for="professional in professionals"
                :key="professional.id"
                data-team-row
                class="hover:bg-theme-surface"
              >
                <td class="px-3 py-4">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                      <div
                        class="h-10 w-10 rounded-full bg-theme-surface flex items-center justify-center"
                        style="border: 1px solid var(--color-hairline)"
                      >
                        <span class="text-sm font-medium text-accent">
                          {{ professional.name.charAt(0) }}
                        </span>
                      </div>
                    </div>
                    <div class="ml-4 min-w-0 w-[220px]">
                      <div
                        class="text-sm font-medium text-[color:var(--color-text-primary)] truncate"
                      >
                        {{ professional.name }}
                      </div>
                      <div class="text-sm text-theme-secondary truncate">
                        {{ secondaryLine(professional) }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-4 whitespace-nowrap">
                  <UiBadge variant="primary" size="sm" shape="pill">
                    {{ getSpecialtyText(professional.specialty) }}
                  </UiBadge>
                </td>
                <td class="px-3 py-4 whitespace-nowrap">
                  <UiBadge variant="neutral" size="sm" shape="pill">
                    {{ roleLabel(professional.role) }}
                  </UiBadge>
                </td>
                <td
                  class="px-3 py-4 whitespace-nowrap text-sm text-[color:var(--color-text-primary)]"
                >
                  {{ professionalLicense(professional) }}
                </td>
                <td
                  class="px-3 py-4 whitespace-nowrap text-sm text-[color:var(--color-text-primary)]"
                >
                  {{ branchName(professional) }}
                </td>
                <td class="px-3 py-4 whitespace-nowrap">
                  <UiBadge
                    :variant="professional.is_active ? 'success' : 'error'"
                    size="sm"
                    shape="pill"
                  >
                    {{ professional.is_active ? 'Activo' : 'Inactivo' }}
                  </UiBadge>
                </td>
                <td class="px-3 py-4 text-sm font-medium">
                  <div class="flex flex-wrap gap-1.5">
                    <UiButton
                      variant="ghost"
                      size="sm"
                      class="text-accent hover:text-accent-hover"
                      @click="viewDetail(professional)"
                    >
                      Ver Detalle
                    </UiButton>
                    <UiButton
                      variant="ghost"
                      size="sm"
                      class="text-accent hover:text-primary-800"
                      @click="editProfessional(professional)"
                    >
                      Editar
                    </UiButton>
                    <UiButton
                      variant="ghost"
                      size="sm"
                      class="text-red-600 hover:text-red-900"
                      @click="deleteProfessional(professional)"
                    >
                      Eliminar
                    </UiButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UiCard>
    </section>
  </AppLayout>

  <!-- New Professional Modal -->
  <UiModal v-model="showNewProfessionalModal" title="Nuevo Profesional" size="md">
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
      <UiButton type="button" :loading="creating" @click="createProfessional">Crear</UiButton>
    </template>
  </UiModal>

  <!-- Edit Professional Modal -->
  <UiModal v-model="showEditProfessionalModal" title="Editar Profesional" size="md">
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
      <UiButton type="button" :loading="updating" @click="updateProfessional">Actualizar</UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useErrorHandler } from '../../composables/useErrorHandler'
import { useConfirm } from '../../composables/useConfirm'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiCard from '../../components/ui/Card.vue'
import UiModal from '../../components/ui/Modal.vue'
import DentalMotifLayer from '../../components/ui/DentalMotifLayer.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
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

const loading = ref(false)
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
  try {
    const response = await get(`/api/users?role=${encodeURIComponent(CLINICAL_ROLES.join(','))}`)
    team.value = response?.data || []
  } catch (error) {
    handleError(error, 'Error al cargar los profesionales. Por favor, recarga la página.')
    team.value = []
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
      handleError(error, 'Error al eliminar el profesional')
    }
  }
}

onMounted(() => {
  loadBranches()
  loadProfessionals()
})
</script>
