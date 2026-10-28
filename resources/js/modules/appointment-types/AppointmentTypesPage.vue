<template>
  <AppLayout>
    <!-- Hero band (D1): the dashboard's surface recipe (hairline + radius-panel
         + elevation rung over the canvas base) with eyebrow, display headline,
         caption and the primary action right-aligned in the band. -->
    <header
      data-appointment-types-hero
      class="mb-6 p-6 md:p-8"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <p
            class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
            data-hero-eyebrow
          >
            Catálogo
          </p>
          <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>Tipos de Cita</h2>
          <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
            Configura duración, precio y color de cada tipo de cita
          </p>
        </div>
        <div class="flex flex-shrink-0 items-center gap-3">
          <UiButton variant="secondary" @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
          <!-- #439: the write doors are the ones the route guards with
               `manage.appointment_type`; a reader keeps list, filters, KPIs
               and tabs. -->
          <UiButton v-if="canManageAppointmentTypes" data-hero-action @click="openNewTypeModal">
            <template #icon-left>
              <PlusIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Nuevo tipo
          </UiButton>
        </div>
      </div>
    </header>

    <!-- KPI strip (D2): four cards over the SAME full catalog the table
         renders, so the numbers cannot drift from the list. While the load
         fails they read "N/D" instead of a fabricated zero. -->
    <section
      aria-label="Resumen del catálogo"
      class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
    >
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="total"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Tipos de cita</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="total"
        >
          {{ kpiTotalDisplay }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="total">En el catálogo</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="active"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Activos</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="active"
        >
          {{ kpiActiveDisplay }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="active">En el catálogo</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="inactive"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Inactivos</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="inactive"
        >
          {{ kpiInactiveDisplay }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="inactive">En el catálogo</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="avg-duration"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Duración media</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="avg-duration"
        >
          {{ kpiAvgDurationDisplay }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="avg-duration">
          Promedio del catálogo
        </p>
      </UiCard>
    </section>

    <!-- Filters (D6): one `status=all` load, instant client-side filtering. -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
        <div class="w-full flex-1">
          <UiInput
            v-model="searchQuery"
            data-type-search
            placeholder="Buscar tipos de cita por nombre o descripción..."
            class="w-full"
          >
            <template #prefix>
              <MagnifyingGlassIcon class="w-5 h-5 text-theme-secondary" aria-hidden="true" />
            </template>
          </UiInput>
        </div>
        <div class="flex items-center gap-3">
          <UiSelect
            v-model="statusFilter"
            :options="statusFilterOptions"
            placeholder="Todos los estados"
            class="w-48"
            data-status-filter
          />
          <p class="whitespace-nowrap text-xs text-theme-secondary" data-filter-count>
            {{ filterCountText }}
          </p>
        </div>
      </div>
    </UiCard>

    <!-- List block (D3 section grammar + D4 table + D7 states). -->
    <section aria-label="Tipos de cita" class="space-y-4">
      <div class="flex items-baseline gap-3">
        <h2 class="text-2xl font-semibold text-label">Todos los tipos</h2>
        <p class="text-sm text-theme-secondary">Busca, filtra y gestiona cada tipo del catálogo</p>
      </div>

      <UiCard variant="glass" class="overflow-hidden">
        <div v-if="loading" class="p-8 text-center">
          <LoadingSpinner class="inline-block" text="Cargando tipos de cita..." />
        </div>

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
          <p class="text-base font-medium text-theme-primary">
            No pudimos cargar los tipos de cita
          </p>
          <p class="mt-1 max-w-md mx-auto text-sm text-theme-secondary">
            Revisa tu conexión e inténtalo de nuevo. Si el problema continúa, vuelve a intentarlo en
            unos minutos.
          </p>
          <div class="mt-6">
            <UiButton variant="primary" size="md" data-retry-types @click="loadTypes">
              Reintentar
            </UiButton>
          </div>
        </div>

        <UiEmptyState
          v-else-if="filteredTypes.length === 0"
          :title="emptyStateTitle"
          :description="emptyStateDescription"
        />

        <!--
          #453 (U11, list): the catalog list rides `UiDataTable`; the table
          scrolls inside itself (with the affordance) and every row keeps its
          hooks and its three actions. The endpoint returns the whole catalog
          in one `status=all` read, so there is no server page to paginate.
        -->
        <UiDataTable
          v-else
          :data="filteredTypes"
          :columns="tableColumns"
          :searchable="false"
          :show-header="false"
          :pagination="false"
          row-key="id"
          table-class="min-w-[960px]"
          :row-attributes="typeRowAttributes"
          data-types-table
        >
          <template #cell-name="{ row }">
            <div class="flex min-w-0 items-center gap-2">
              <span
                class="h-2.5 w-2.5 flex-shrink-0 rounded-full"
                data-type-swatch
                :style="{ backgroundColor: row.color }"
                aria-hidden="true"
              />
              <span class="truncate text-sm font-medium text-theme-primary" :title="row.name">
                {{ row.name }}
              </span>
            </div>
          </template>
          <template #cell-description="{ row }">
            <div
              class="w-full truncate text-sm text-theme-secondary"
              :title="row.description || 'Sin descripción'"
            >
              {{ row.description || 'Sin descripción' }}
            </div>
          </template>
          <template #cell-duration="{ row }">
            <span class="whitespace-nowrap text-sm tabular-nums text-theme-primary">
              {{ row.default_duration_minutes }} min
            </span>
          </template>
          <template #cell-price="{ row }">
            <div
              class="whitespace-nowrap text-sm tabular-nums text-theme-primary"
              :aria-label="`Precio ${formatTypePrice(row.price)}`"
            >
              {{ formatTypePrice(row.price) }}
            </div>
          </template>
          <template #cell-status="{ row }">
            <UiStatusBadge
              :variant="row.is_active ? 'success' : 'neutral'"
              :label="row.is_active ? 'Activo' : 'Inactivo'"
              size="sm"
            />
          </template>
          <template #actions="{ row }">
            <div class="flex items-center gap-1">
              <UiButton
                variant="ghost"
                size="sm"
                class="text-systemBlue-600 hover:text-systemBlue-700"
                data-row-action="detail"
                @click="viewDetail(row)"
              >
                Ver Detalle
              </UiButton>
              <UiButton
                v-if="canManageAppointmentTypes"
                variant="ghost"
                size="sm"
                class="text-systemBlue-600 hover:text-systemBlue-700"
                data-row-action="edit"
                title="Editar tipo"
                aria-label="Editar tipo"
                @click="editType(row)"
              >
                <PencilIcon class="h-4 w-4" aria-hidden="true" />
              </UiButton>
              <UiButton
                v-if="canManageAppointmentTypes"
                variant="ghost"
                size="sm"
                class="text-systemRed-600 hover:text-systemRed-700"
                data-row-action="delete"
                title="Eliminar tipo"
                aria-label="Eliminar tipo"
                @click="deleteType(row)"
              >
                <TrashIcon class="h-4 w-4" aria-hidden="true" />
              </UiButton>
            </div>
          </template>
        </UiDataTable>
      </UiCard>
    </section>
  </AppLayout>

  <!-- New Type Modal -->
  <UiModal v-model="showNewTypeModal" title="Nuevo Tipo de Cita" size="md">
    <!-- #440: `novalidate` hands the rejection to the page, so the empty
         submit paints its field messages instead of a native bubble. -->
    <form id="form-new-type" class="space-y-4" novalidate @submit.prevent="createType">
      <UiInput
        v-model="newType.name"
        label="Nombre del Tipo"
        type="text"
        required
        :error="createErrors.name"
      />
      <UiTextarea v-model="newType.description" label="Descripción" :rows="3" />
      <UiInput
        v-model="newType.duration_minutes"
        label="Duración (minutos)"
        type="number"
        min="15"
        max="480"
        step="15"
        required
        :error="createErrors.duration_minutes"
      />
      <UiInput v-model="newType.price" label="Precio (S/)" type="number" min="0" step="0.01" />
      <div>
        <label class="block text-sm font-medium text-theme-primary">Requiere confirmación</label>
        <UiSelect
          v-model="newType.requires_confirmation"
          :options="booleanOptions"
          data-flag-select="requires_confirmation"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Modo consulta</label>
        <UiSelect
          v-model="newType.is_consultation_mode"
          :options="booleanOptions"
          data-flag-select="is_consultation_mode"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Color</label>
        <div class="mt-1 flex items-center gap-2">
          <input
            v-model="newType.color"
            type="color"
            aria-label="Color del tipo de cita"
            class="h-8 w-12 rounded border border-hairline"
          />
          <UiInput
            v-model="newType.color"
            label="Código hex"
            type="text"
            :placeholder="DEFAULT_TYPE_COLOR"
            class="flex-1"
          />
        </div>
      </div>
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="creating" @click="showNewTypeModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" form="form-new-type" :loading="creating">
        Guardar tipo de cita
      </UiButton>
    </template>
  </UiModal>

  <!-- Edit Type Modal -->
  <UiModal v-model="showEditTypeModal" title="Editar Tipo de Cita" size="md">
    <form
      v-if="editingType"
      id="form-edit-type"
      class="space-y-4"
      novalidate
      @submit.prevent="updateType"
    >
      <UiInput
        v-model="editingType.name"
        label="Nombre"
        type="text"
        required
        :error="editErrors.name"
      />
      <UiTextarea v-model="editingType.description" label="Descripción" :rows="3" />
      <UiInput
        v-model="editingType.duration_minutes"
        label="Duración (minutos)"
        type="number"
        min="15"
        max="480"
        step="15"
        required
        :error="editErrors.duration_minutes"
      />
      <UiInput
        v-model="editingType.price"
        label="Precio"
        type="number"
        min="0"
        step="0.01"
        required
      />
      <div>
        <label class="block text-sm font-medium text-theme-primary">Requiere confirmación</label>
        <UiSelect
          v-model="editingType.requires_confirmation"
          :options="booleanOptions"
          data-flag-select="requires_confirmation"
        />
      </div>
      <!-- #444: `requires_materials` lo deriva la API de la receta del
           procedimiento por defecto (#295). Se declara, no se ofrece. -->
      <div>
        <p class="text-sm font-medium text-theme-primary" data-derived-field="requires_materials">
          Requiere materiales: {{ editingType.requires_materials ? 'Sí' : 'No' }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">
          Derivado de la receta del procedimiento por defecto.
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Modo consulta</label>
        <UiSelect
          v-model="editingType.is_consultation_mode"
          :options="booleanOptions"
          data-flag-select="is_consultation_mode"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Color</label>
        <div class="mt-1 flex items-center gap-2">
          <input
            v-model="editingType.color"
            type="color"
            aria-label="Color del tipo de cita"
            class="h-8 w-12 rounded border border-hairline"
          />
          <UiInput
            v-model="editingType.color"
            label="Código hex"
            type="text"
            :placeholder="DEFAULT_TYPE_COLOR"
            class="flex-1"
          />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Estado</label>
        <UiSelect
          v-model="editingTypeIsActive"
          :options="isActiveOptions"
          placeholder="Seleccionar estado"
        />
      </div>
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="updating" @click="showEditTypeModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" form="form-edit-type" :loading="updating">Actualizar</UiButton>
    </template>
  </UiModal>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon,
  PencilIcon,
  PlusIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'
import { useErrorHandler } from '../../composables/useErrorHandler'
import { usePermissions } from '../../composables/usePermissions'
import { normalizeValidationErrors } from '../../composables/validationErrors'
import { formatCurrency } from '../../composables/useFormatters'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
// A new appointment type starts on the brand accent, read from the token so
// the next palette move carries it along.
import { colors } from '../../design-system/tokens.js'

const DEFAULT_TYPE_COLOR = colors.accent[500]
import UiTextarea from '../../components/ui/UiTextarea.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'

export default {
  name: 'AppointmentTypesPage',
  components: {
    AppLayout,
    ArrowLeftIcon,
    ExclamationTriangleIcon,
    MagnifyingGlassIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
    UiDataTable,
    UiButton,
    UiInput,
    UiSelect,
    UiCard,
    UiModal,
    UiEmptyState,
    UiTextarea,
    UiStatusBadge
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { get, post, put, delete: remove } = useApi()
    const toast = useToast()
    const { confirm } = useConfirm()
    const { handleError } = useErrorHandler()
    // #439: every write door of this catalog declares the permission its route
    // demands (`manage.appointment_type`), so a reader never sees a 403 door.
    const { can } = usePermissions()
    const canManageAppointmentTypes = computed(() => can.manageAppointmentTypes.value)

    const loading = ref(false)
    const creating = ref(false)
    const updating = ref(false)
    const loadError = ref(false)
    const types = ref([])
    const searchQuery = ref('')
    const statusFilter = ref('all')
    const showNewTypeModal = ref(false)
    const showEditTypeModal = ref(false)
    const editingType = ref(null)
    // #440: the rejected form says what is missing next to the field, so both
    // write modals own their field-error map (local rule or server 422).
    const createErrors = ref({})
    const editErrors = ref({})

    // #444: `requires_materials` does not live here: the API derives it from
    // the default procedure's recipe (#295), so the form neither offers nor
    // sends it.
    const emptyNewType = () => ({
      name: '',
      description: '',
      duration_minutes: 60,
      price: 0,
      requires_confirmation: true,
      is_consultation_mode: false,
      color: DEFAULT_TYPE_COLOR,
      is_active: true
    })

    const newType = ref(emptyNewType())

    const booleanOptions = [
      { value: true, label: 'Sí' },
      { value: false, label: 'No' }
    ]

    const formatTypePrice = price =>
      price === null || price === undefined ? 'No especificado' : formatCurrency(price)

    const statusFilterOptions = computed(() => [
      { value: 'all', label: 'Todos' },
      { value: 'active', label: 'Activos' },
      { value: 'inactive', label: 'Inactivos' }
    ])

    const isActiveOptions = computed(() => [
      { value: true, label: 'Activo' },
      { value: false, label: 'Inactivo' }
    ])

    // Two-way binding bridge for the edit modal's `is_active` <UiSelect>.
    const editingTypeIsActive = computed({
      get: () => editingType.value?.is_active,
      set: value => {
        if (editingType.value) {
          editingType.value.is_active = value
        }
      }
    })

    // #453 (U11, list): the catalog list renders through `UiDataTable`. The
    // endpoint answers the whole catalog in one `status=all` read, so the
    // primitive never paginates: the page filters client-side and keeps its
    // own `N de M` counter.
    const tableColumns = [
      { key: 'name', label: 'Nombre' },
      { key: 'description', label: 'Descripción' },
      { key: 'duration', label: 'Duración' },
      { key: 'price', label: 'Precio' },
      { key: 'status', label: 'Estado' }
    ]

    const typeRowAttributes = row => ({ 'data-type-row': String(row.id) })

    // D6: one `status=all` load; search + status filter the same array
    // client-side so the list and its KPIs share a single source of truth.
    const filteredTypes = computed(() => {
      const query = searchQuery.value.trim().toLowerCase()
      return types.value.filter(type => {
        if (statusFilter.value === 'active' && !type.is_active) return false
        if (statusFilter.value === 'inactive' && type.is_active) return false
        if (query) {
          const haystack = `${type.name || ''} ${type.description || ''}`.toLowerCase()
          if (!haystack.includes(query)) return false
        }
        return true
      })
    })

    const filterCountText = computed(
      () => `Mostrando ${filteredTypes.value.length} de ${types.value.length}`
    )

    const kpiTotalDisplay = computed(() => (loadError.value ? 'N/D' : String(types.value.length)))

    const kpiActiveDisplay = computed(() =>
      loadError.value ? 'N/D' : String(types.value.filter(type => type.is_active).length)
    )

    const kpiInactiveDisplay = computed(() =>
      loadError.value ? 'N/D' : String(types.value.filter(type => !type.is_active).length)
    )

    const kpiAvgDurationDisplay = computed(() => {
      if (loadError.value || types.value.length === 0) return 'N/D'
      const total = types.value.reduce(
        (sum, type) => sum + (Number(type.default_duration_minutes) || 0),
        0
      )
      return `${Math.round(total / types.value.length)} min`
    })

    const emptyStateTitle = computed(() => {
      if (searchQuery.value.trim()) return 'Sin resultados'
      if (statusFilter.value === 'active') return 'No hay tipos de cita activos'
      if (statusFilter.value === 'inactive') return 'No hay tipos de cita inactivos'
      return 'No se encontraron tipos de cita'
    })

    const emptyStateDescription = computed(() => {
      const query = searchQuery.value.trim()
      if (query)
        return `Ningún tipo de cita coincide con ${query}. Prueba con otro término de búsqueda.`
      if (statusFilter.value === 'active') return 'Todos los tipos del catálogo están inactivos.'
      if (statusFilter.value === 'inactive') return 'Todos los tipos del catálogo están activos.'
      return 'Crea el primer tipo de cita para empezar.'
    })

    // Deep link from the detail hero (`?edit=<id>`): open the shared edit
    // modal for that type once the catalog is loaded, then clear the
    // parameter so a re-render does not reopen it.
    const openEditFromQuery = () => {
      const editId = route.query.edit
      if (!editId) return
      const match = types.value.find(type => String(type.id) === String(editId))
      if (match) editType(match)
      router.replace({ query: { ...route.query, edit: undefined } })
    }

    const loadTypes = async () => {
      loading.value = true
      try {
        const response = await get('/api/appointment-types?status=all')
        types.value = response?.data || []
        loadError.value = false

        if (types.value.length === 0) {
          toast.info('Aún no hay tipos de cita registrados')
        }

        openEditFromQuery()
      } catch (error) {
        loadError.value = true
        types.value = []
        handleError(error, 'Error al cargar los tipos de cita. Por favor, recarga la página.')
      } finally {
        loading.value = false
      }
    }

    // #440: the page validates the two rules the API demands (a name and a
    // 15-480 minute duration) so an empty submit paints its message instead of
    // reaching the network. The server keeps deciding; a 422 is mapped onto
    // the same fields by `applyServerFieldErrors`.
    const durationError = value => {
      const raw = value === null || value === undefined ? '' : String(value).trim()
      const minutes = Number(raw)
      if (raw === '' || Number.isNaN(minutes) || minutes < 15 || minutes > 480) {
        return 'La duración debe estar entre 15 y 480 minutos.'
      }
      return ''
    }

    const validateForm = (source, errorsRef) => {
      const errors = {}
      if (!String(source?.name ?? '').trim()) {
        errors.name = 'El nombre es obligatorio.'
      }
      const duration = durationError(source?.duration_minutes)
      if (duration) errors.duration_minutes = duration

      errorsRef.value = errors
      return Object.keys(errors).length === 0
    }

    // The 422 lands on its field; the toast is only for a rejection without a
    // field to paint.
    const applyServerFieldErrors = (error, errorsRef, fallbackMessage) => {
      const fieldErrors = normalizeValidationErrors(error?.response?.data?.errors)
      errorsRef.value = fieldErrors
      if (Object.keys(fieldErrors).length === 0) {
        handleError(error, fallbackMessage)
      }
    }

    // #444: `requires_materials` is derived from the default procedure's
    // recipe, so it is neither offered nor written.
    const writePayload = source => {
      const payload = { ...source }
      delete payload.requires_materials
      return payload
    }

    const createType = async () => {
      if (creating.value) return
      if (!validateForm(newType.value, createErrors)) return
      creating.value = true
      try {
        await post('/api/appointment-types', newType.value)
        showNewTypeModal.value = false
        newType.value = emptyNewType()
        createErrors.value = {}
        toast.success('Tipo de cita creado exitosamente')
        loadTypes()
      } catch (error) {
        applyServerFieldErrors(error, createErrors, 'Error al crear el tipo de cita')
      } finally {
        creating.value = false
      }
    }

    const editType = type => {
      // The API read field is `default_duration_minutes`, but the write
      // payload (storeRules) takes `editingType.duration_minutes`: initialize
      // it from the record so the edit form is pre-filled and a save without
      // retyping keeps the duration.
      const storedColor = /^#[0-9A-Fa-f]{6}$/.test(type.color) ? type.color : DEFAULT_TYPE_COLOR
      editingType.value = {
        ...type,
        color: storedColor,
        duration_minutes: type.default_duration_minutes
      }
      editErrors.value = {}
      showEditTypeModal.value = true
    }

    const openNewTypeModal = () => {
      createErrors.value = {}
      showNewTypeModal.value = true
    }

    const viewDetail = type => {
      router.push(`/appointment-types/${type.id}`)
    }

    const updateType = async () => {
      if (updating.value) return
      if (!validateForm(editingType.value, editErrors)) return
      updating.value = true
      try {
        await put(`/api/appointment-types/${editingType.value.id}`, writePayload(editingType.value))
        showEditTypeModal.value = false
        editingType.value = null
        editErrors.value = {}
        loadTypes()
        toast.success('Tipo de cita actualizado exitosamente')
      } catch (error) {
        applyServerFieldErrors(error, editErrors, 'Error al actualizar el tipo de cita')
      } finally {
        updating.value = false
      }
    }

    const deleteType = async type => {
      const ok = await confirm({
        title: 'Eliminar tipo de cita',
        message: `¿Estás seguro de que quieres eliminar el tipo de cita ${type.name}? Si tiene citas registradas no se podrá eliminar: desactívalo en su lugar.`,
        confirmText: 'Eliminar',
        variant: 'danger'
      })
      if (ok) {
        try {
          await remove(`/api/appointment-types/${type.id}`)
          loadTypes()
          toast.success('Tipo de cita eliminado exitosamente')
        } catch (error) {
          // No custom message: a 409 carries the actionable reason (the type
          // still has appointments) and must reach the operator verbatim.
          handleError(error)
        }
      }
    }

    const goBack = () => {
      router.back()
    }

    onMounted(() => {
      loadTypes()
    })

    return {
      loading,
      creating,
      updating,
      loadError,
      types,
      searchQuery,
      statusFilter,
      statusFilterOptions,
      isActiveOptions,
      editingTypeIsActive,
      filteredTypes,
      tableColumns,
      typeRowAttributes,
      filterCountText,
      kpiTotalDisplay,
      kpiActiveDisplay,
      kpiInactiveDisplay,
      kpiAvgDurationDisplay,
      emptyStateTitle,
      emptyStateDescription,
      showNewTypeModal,
      showEditTypeModal,
      openNewTypeModal,
      canManageAppointmentTypes,
      createErrors,
      editErrors,
      newType,
      editingType,
      booleanOptions,
      DEFAULT_TYPE_COLOR,
      loadTypes,
      createType,
      editType,
      viewDetail,
      updateType,
      deleteType,
      goBack,
      formatCurrency,
      formatTypePrice
    }
  }
}
</script>
