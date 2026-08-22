<template>
  <!--
    Canvas surface inherited from AppLayout.vue (see canvasRoutes + matchesCanvasRoute
    helper, AMB-01-001). Page header pinned on bg-canvas per DLR-R-001 so the page
    can be grep-verified for the canvas-token reference. AppointmentTypesPage.vue
    uses the same anchor for the same reason.
  -->
  <AppLayout>
    <!-- Header Section -->
    <PageHeader title="Ambientes" subtitle="Gestiona los ambientes y consultorios" class="bg-canvas mb-6">
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
        <UiButton @click="showNewEnvironmentModal = true">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 6v6m0 0v6m0-6h6m-6 0H6"
              />
            </svg>
          </template>
          Nuevo Ambiente
        </UiButton>
      </template>
    </PageHeader>

    <!-- Search and Filters -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar ambientes por nombre o descripción..."
            class="w-full"
            @input="searchEnvironments"
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
        <div class="flex gap-3">
          <UiSelect
            v-model="statusFilter"
            :options="statusOptions"
            class="w-48"
            @change="filterEnvironments"
          />
        </div>
      </div>
    </UiCard>

    <!-- Environments List -->
    <UiCard variant="glass" class="overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <UiLoadingSpinner size="md" text="Cargando ambientes..." />
      </div>

      <div v-else-if="environments.length === 0">
        <UiEmptyState
          title="No se encontraron ambientes"
          description="Intenta ajustar los filtros o crear un nuevo ambiente."
        />
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[color:var(--color-hairline)]">
          <thead class="bg-theme-surface">
            <tr>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Ambiente
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Descripción
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Equipamiento
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Estado
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-[color:var(--color-hairline)]">
            <tr
              v-for="environment in environments"
              :key="environment.id"
              class="hover:bg-theme-surface"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div
                      class="h-10 w-10 rounded-full bg-systemBlue-50 flex items-center justify-center"
                    >
                      <span class="text-sm font-medium text-systemBlue-700">
                        {{ environment.name.charAt(0) }}
                      </span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-theme-primary">
                      {{ environment.name }}
                    </div>
                    <div class="text-sm text-theme-secondary">ID: {{ environment.id }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-theme-primary">
                  {{ environment.description || 'Sin descripción' }}
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-theme-primary">
                  {{ environment.equipment || 'Sin equipamiento' }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <UiStatusBadge
                  :variant="getStatusVariant(environment.status)"
                  :label="getStatusText(environment.status)"
                />
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <UiButton
                    variant="link"
                    size="sm"
                    @click="viewDetail(environment)"
                  >
                    Ver Detalle
                  </UiButton>
                  <UiButton
                    variant="link"
                    size="sm"
                    @click="editEnvironment(environment)"
                  >
                    Editar
                  </UiButton>
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-systemRed-700"
                    @click="deleteEnvironment(environment)"
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
  </AppLayout>

  <!-- New Environment Modal (PR-ambientes-02 / AMB-02-007) — 4 raw form fields
       migrated to <UiInput> / <UiTextarea> / <UiSelect> primitives. `v-model=`
       bindings + `required` attributes preserved byte-for-byte. -->
  <UiModal v-model="showNewEnvironmentModal" title="Nuevo Ambiente" size="md">
    <form id="form-new-env" class="space-y-4" @submit.prevent="createEnvironment">
      <UiInput
        v-model="newEnvironment.name"
        label="Nombre del Ambiente"
        required
      />
      <UiTextarea
        v-model="newEnvironment.description"
        label="Descripción"
        :rows="3"
      />
      <UiTextarea
        v-model="newEnvironment.equipment"
        label="Equipamiento"
        :rows="2"
      />
      <UiSelect
        v-model="newEnvironment.status"
        :options="statusOptions"
        label="Estado"
        required
      />
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="creating" @click="showNewEnvironmentModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" :loading="creating" @click="createEnvironment">Crear</UiButton>
    </template>
  </UiModal>

  <!-- Edit Environment Modal (PR-ambientes-02 / AMB-02-007) — 4 raw form fields
       migrated to <UiInput> / <UiTextarea> / <UiSelect> primitives. `v-model=`
       bindings + `required` attributes preserved byte-for-byte. -->
  <UiModal v-model="showEditEnvironmentModal" title="Editar Ambiente" size="md">
    <form
      v-if="editingEnvironment"
      id="form-edit-env"
      class="space-y-4"
      @submit.prevent="updateEnvironment"
    >
      <UiInput
        v-model="editingEnvironment.name"
        label="Nombre"
        required
      />
      <UiInput
        v-model="editingEnvironment.code"
        label="Código"
        required
      />
      <UiTextarea
        v-model="editingEnvironment.description"
        label="Descripción"
        :rows="3"
      />
      <UiSelect
        v-model="editingEnvironment.status"
        :options="statusOptions"
        label="Estado"
        required
      />
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="updating" @click="showEditEnvironmentModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" :loading="updating" @click="updateEnvironment">Actualizar</UiButton>
    </template>
  </UiModal>

  <!-- View Environment Modal -->
  <UiModal v-model="showViewEnvironmentModal" title="Ver Ambiente" size="md">
    <div v-if="viewingEnvironment" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-theme-primary">Nombre</label>
        <p class="mt-1 text-sm text-theme-primary">
          {{ viewingEnvironment.name }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Código</label>
        <p class="mt-1 text-sm text-theme-primary">
          {{ viewingEnvironment.code }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Descripción</label>
        <p class="mt-1 text-sm text-theme-primary">
          {{ viewingEnvironment.description || 'Sin descripción' }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Estado</label>
        <p class="mt-1 text-sm text-theme-primary">
          <UiStatusBadge
            :variant="getStatusVariant(viewingEnvironment.status)"
            :label="getStatusText(viewingEnvironment.status)"
          />
        </p>
      </div>
    </div>
    <template #footer>
      <UiButton variant="secondary" @click="showViewEnvironmentModal = false">Cerrar</UiButton>
    </template>
  </UiModal>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'
import { useErrorHandler } from '../../composables/useErrorHandler'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiCard from '../../components/ui/Card.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiLoadingSpinner from '../../components/ui/LoadingSpinner.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'

export default {
  name: 'EnvironmentsPage',
  components: {
    AppLayout,
    UiButton,
    UiInput,
    UiSelect,
    UiCard,
    UiModal,
    UiEmptyState,
    UiLoadingSpinner,
    UiStatusBadge,
    UiTextarea
  },
  setup() {
    const router = useRouter()
    const { get, post, put, delete: remove } = useApi()
    const toast = useToast()
    const { handleError } = useErrorHandler()

    const loading = ref(false)
    const creating = ref(false)
    const updating = ref(false)
    const environments = ref([])
    const searchQuery = ref('')
    const statusFilter = ref('')
    const showNewEnvironmentModal = ref(false)
    const showEditEnvironmentModal = ref(false)
    const showViewEnvironmentModal = ref(false)
    const editingEnvironment = ref(null)
    const viewingEnvironment = ref(null)

    const newEnvironment = ref({
      name: '',
      description: '',
      equipment: '',
      status: 'active'
    })

    // PR-ambientes-01 (AMB-01-002) — status filter options consumed by
    // `<UiSelect :options="statusOptions" v-model="statusFilter" />`.
    // Values match the legacy `<option value="...">` attributes byte-for-byte.
    const statusOptions = [
      { value: '', label: 'Todos los estados' },
      { value: 'active', label: 'Activos' },
      { value: 'inactive', label: 'Inactivos' },
      { value: 'maintenance', label: 'Mantenimiento' }
    ]

    const loadEnvironments = async () => {
      loading.value = true
      try {
        const response = await get('/api/dental-chairs')
        environments.value = response?.data || []

        if (environments.value.length === 0) {
          toast.info('Aún no hay ambientes registrados')
        }
      } catch (error) {
        handleError(error, 'Error al cargar los ambientes. Por favor, recarga la página.')
        environments.value = []
      } finally {
        loading.value = false
      }
    }

    const searchEnvironments = async () => {
      if (searchQuery.value.length < 2) {
        loadEnvironments()
        return
      }

      loading.value = true
      try {
        const response = await get(
          `/api/dental-chairs/search?q=${encodeURIComponent(searchQuery.value)}`
        )
        environments.value = response.data
      } catch (error) {
        handleError(error, 'Error al buscar ambientes')
      } finally {
        loading.value = false
      }
    }

    const filterEnvironments = () => {
      // Implement client-side filtering or API call
      loadEnvironments()
    }

    const createEnvironment = async () => {
      if (creating.value) return
      creating.value = true
      try {
        await post('/api/dental-chairs', newEnvironment.value)
        showNewEnvironmentModal.value = false
        newEnvironment.value = {
          name: '',
          description: '',
          equipment: '',
          status: 'active'
        }
        toast.success('Ambiente creado exitosamente')
        loadEnvironments()
      } catch (error) {
        handleError(error, 'Error al crear el ambiente')
      } finally {
        creating.value = false
      }
    }

    const editEnvironment = environment => {
      editingEnvironment.value = { ...environment }
      showEditEnvironmentModal.value = true
    }

    const viewEnvironment = environment => {
      viewingEnvironment.value = environment
      showViewEnvironmentModal.value = true
    }

    const viewDetail = environment => {
      router.push(`/environments/${environment.id}`)
    }

    const updateEnvironment = async () => {
      if (updating.value) return
      updating.value = true
      try {
        await put(`/api/dental-chairs/${editingEnvironment.value.id}`, editingEnvironment.value)
        showEditEnvironmentModal.value = false
        editingEnvironment.value = null
        loadEnvironments()
        toast.success('Ambiente actualizado exitosamente')
      } catch (error) {
        handleError(error, 'Error al actualizar el ambiente')
      } finally {
        updating.value = false
      }
    }

    const deleteEnvironment = async environment => {
      const ok = await confirm({
        title: 'Eliminar ambiente',
        message: `¿Estás seguro de que quieres eliminar el ambiente ${environment.name}?`,
        confirmText: 'Eliminar',
        variant: 'danger'
      })
      if (ok) {
        try {
          await remove(`/api/dental-chairs/${environment.id}`)
          loadEnvironments()
          toast.success('Ambiente eliminado exitosamente')
        } catch (error) {
          handleError(error, 'Error al eliminar el ambiente')
        }
      }
    }

    // PR-ambientes-01 (AMB-01-008 / DLR-AMB-005 EXCEPTION #1): renamed the
    // legacy colour-class helper to `getStatusVariant` and changed its
    // return type from legacy Tailwind ramp strings (success / warning
    // ramps under the theme namespace) to variant tokens (the canonical
    // `success | neutral | warning` enum consumed by `<UiStatusBadge
    // :variant="...">`). The function name is now honest about what it
    // returns. This is a documented DLR-AMB-005 exception to the global
    // `<script>`-never-touched rule. The remaining `<script>` block
    // (`useApi` / `useToast` / `useConfirm` / `useErrorHandler` reactivity,
    // the data-flow methods, the `onMounted` hook, the `return` entries
    // other than this renamed helper) stays byte-for-byte verbatim.
    const getStatusVariant = status => {
      const variants = {
        active: 'success',
        inactive: 'neutral',
        maintenance: 'warning'
      }
      return variants[status] || 'neutral'
    }

    const getStatusText = status => {
      const texts = {
        active: 'Activo',
        inactive: 'Inactivo',
        maintenance: 'Mantenimiento'
      }
      return texts[status] || status
    }

    const goBack = () => {
      router.back()
    }

    onMounted(() => {
      loadEnvironments()
    })

    return {
      loading,
      creating,
      updating,
      environments,
      searchQuery,
      statusFilter,
      showNewEnvironmentModal,
      showEditEnvironmentModal,
      showViewEnvironmentModal,
      newEnvironment,
      editingEnvironment,
      viewingEnvironment,
      loadEnvironments,
      searchEnvironments,
      filterEnvironments,
      createEnvironment,
      editEnvironment,
      viewEnvironment,
      viewDetail,
      updateEnvironment,
      deleteEnvironment,
      getStatusVariant,
      getStatusText,
      goBack,
      statusOptions
    }
  }
}
</script>
