<template>
  <AppLayout>
    <!-- Header Section -->
    <!-- bg-canvas pinned on the page header (DLR-R-001); AppLayout also
         paints canvas for this route (canvasRoutes list). -->
    <PageHeader
      title="Tipos de Cita"
      subtitle="Gestiona los tipos de citas disponibles"
      class="bg-canvas mb-6"
    >
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
        <UiButton @click="showNewTypeModal = true">
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
          Nuevo Tipo
        </UiButton>
      </template>
    </PageHeader>

    <!-- Search and Filters -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar tipos de cita por nombre o descripción..."
            class="w-full"
            @input="searchTypes"
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
            :options="statusFilterOptions"
            placeholder="Todos los estados"
            class="w-48"
            @change="filterTypes"
          />
        </div>
      </div>
    </UiCard>

    <!-- Types List -->
    <UiCard variant="glass" class="overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />
        <p class="mt-2 text-theme-secondary">Cargando tipos de cita...</p>
      </div>

      <div v-else-if="types.length === 0" class="p-8 text-center">
        <svg
          class="mx-auto h-12 w-12 text-theme-secondary"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
          />
        </svg>
        <p class="mt-2 text-theme-secondary">No se encontraron tipos de cita</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-hairline">
          <thead class="bg-theme-surface">
            <tr>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Tipo de Cita
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Duración
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Precio
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Color
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
          <tbody class="bg-theme-surface-elevated divide-y divide-hairline">
            <tr v-for="type in types" :key="type.id" class="hover:bg-theme-surface">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div
                      class="h-10 w-10 rounded-full flex items-center justify-center"
                      :style="{ backgroundColor: type.color }"
                    >
                      <span class="text-sm font-medium text-white">
                        {{ type.name.charAt(0) }}
                      </span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-theme-primary">
                      {{ type.name }}
                    </div>
                    <div class="text-sm text-theme-secondary">
                      {{ type.description || 'Sin descripción' }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-theme-primary">{{ type.duration_minutes }} min</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div
                  class="text-sm text-theme-primary tabular-nums"
                  :aria-label="`Precio ${formatCurrency(type.price)}`"
                >
                  {{ formatCurrency(type.price) }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="w-4 h-4 rounded-full mr-2" :style="{ backgroundColor: type.color }" />
                  <span class="text-sm text-theme-secondary">{{ type.color }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <UiStatusBadge
                  :variant="type.is_active ? 'success' : 'neutral'"
                  :label="type.is_active ? 'Activo' : 'Inactivo'"
                  size="sm"
                />
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-systemBlue-600 hover:text-systemBlue-700"
                    @click="viewDetail(type)"
                  >
                    Ver Detalle
                  </UiButton>
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-systemBlue-600 hover:text-systemBlue-700"
                    @click="editType(type)"
                  >
                    Editar
                  </UiButton>
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-systemRed-600 hover:text-systemRed-700"
                    @click="deleteType(type)"
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

  <!-- New Type Modal -->
  <UiModal v-model="showNewTypeModal" title="Nuevo Tipo de Cita" size="md">
    <form id="form-new-type" class="space-y-4" @submit.prevent="createType">
      <div>
        <label class="block text-sm font-medium text-theme-primary">Nombre del Tipo</label>
        <input
          v-model="newType.name"
          type="text"
          required
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Descripción</label>
        <textarea
          v-model="newType.description"
          rows="3"
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Duración (minutos)</label>
        <input
          v-model="newType.duration_minutes"
          type="number"
          min="15"
          max="480"
          step="15"
          required
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Precio (S/)</label>
        <input
          v-model="newType.price"
          type="number"
          min="0"
          step="0.01"
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Color</label>
        <div class="flex items-center space-x-2">
          <input
            v-model="newType.color"
            type="color"
            class="w-12 h-8 border border-hairline rounded"
          >
          <input
            v-model="newType.color"
            type="text"
            placeholder="#0066CC"
            class="flex-1 px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
          />
        </div>
      </div>
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="creating" @click="showNewTypeModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" :loading="creating" @click="createType">Crear</UiButton>
    </template>
  </UiModal>

  <!-- Edit Type Modal -->
  <UiModal v-model="showEditTypeModal" title="Editar Tipo de Cita" size="md">
    <form v-if="editingType" id="form-edit-type" class="space-y-4" @submit.prevent="updateType">
      <div>
        <label class="block text-sm font-medium text-theme-primary">Nombre</label>
        <input
          v-model="editingType.name"
          type="text"
          required
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Descripción</label>
        <textarea
          v-model="editingType.description"
          rows="3"
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Duración (minutos)</label>
        <input
          v-model="editingType.duration_minutes"
          type="number"
          min="15"
          max="480"
          step="15"
          required
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Precio</label>
        <input
          v-model="editingType.price"
          type="number"
          min="0"
          step="0.01"
          required
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Color</label>
        <input
          v-model="editingType.color"
          type="color"
          required
          class="mt-1 block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary"
        />
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
      <UiButton type="submit" :loading="updating" @click="updateType">Actualizar</UiButton>
    </template>
  </UiModal>

  <!-- View Type Modal -->
  <UiModal v-model="showViewTypeModal" title="Ver Tipo de Cita" size="md">
    <div v-if="viewingType" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-theme-primary">Nombre</label>
        <p class="mt-1 text-sm text-theme-primary">
          {{ viewingType.name }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Descripción</label>
        <p class="mt-1 text-sm text-theme-primary">
          {{ viewingType.description || 'Sin descripción' }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Duración</label>
        <p class="mt-1 text-sm text-theme-primary">{{ viewingType.duration_minutes }} minutos</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Precio</label>
        <p class="mt-1 text-sm text-theme-primary tabular-nums">
          {{ formatCurrency(viewingType.price) }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Color</label>
        <div class="mt-1 flex items-center">
          <div
            class="w-6 h-6 rounded border border-hairline mr-2"
            :style="{ backgroundColor: viewingType.color }"
          />
          <span class="text-sm text-theme-primary">{{ viewingType.color }}</span>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Estado</label>
        <p class="mt-1 text-sm text-theme-primary">
          <UiStatusBadge
            :variant="viewingType.is_active ? 'success' : 'neutral'"
            :label="viewingType.is_active ? 'Activo' : 'Inactivo'"
            size="sm"
          />
        </p>
      </div>
    </div>
    <template #footer>
      <UiButton variant="secondary" @click="showViewTypeModal = false">Cerrar</UiButton>
    </template>
  </UiModal>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'
import { useErrorHandler } from '../../composables/useErrorHandler'
import { formatCurrency } from '../../composables/useFormatters'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiCard from '../../components/ui/Card.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'

export default {
  name: 'AppointmentTypesPage',
  components: {
    AppLayout,
    UiButton,
    UiInput,
    UiSelect,
    UiCard,
    UiModal,
    UiEmptyState,
    UiStatusBadge
  },
  setup() {
    const router = useRouter()
    const { get, post, put, delete: remove } = useApi()
    const toast = useToast()
    const { handleError } = useErrorHandler()

    const loading = ref(false)
    const creating = ref(false)
    const updating = ref(false)
    const types = ref([])
    const searchQuery = ref('')
    const statusFilter = ref('')
    const showNewTypeModal = ref(false)
    const showEditTypeModal = ref(false)
    const showViewTypeModal = ref(false)
    const editingType = ref(null)
    const viewingType = ref(null)

    const newType = ref({
      name: '',
      description: '',
      duration_minutes: 60,
      price: 0,
      color: '#0066CC',
      is_active: true
    })

    // PR-citas-04 — <UiSelect> options for the filter bar + edit modal state toggle.
    const statusFilterOptions = computed(() => [
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

    const loadTypes = async () => {
      loading.value = true
      try {
        const response = await get('/api/appointment-types')
        types.value = response?.data || []

        if (types.value.length === 0) {
          toast.info('Aún no hay tipos de cita registrados')
        }
      } catch (error) {
        handleError(error, 'Error al cargar los tipos de cita. Por favor, recarga la página.')
        types.value = []
      } finally {
        loading.value = false
      }
    }

    const searchTypes = async () => {
      if (searchQuery.value.length < 2) {
        loadTypes()
        return
      }

      loading.value = true
      try {
        const response = await get(
          `/api/appointment-types/search?q=${encodeURIComponent(searchQuery.value)}`
        )
        types.value = response.data
      } catch (error) {
        handleError(error, 'Error al buscar tipos de cita')
      } finally {
        loading.value = false
      }
    }

    const filterTypes = () => {
      // Implement client-side filtering or API call
      loadTypes()
    }

    const createType = async () => {
      if (creating.value) return
      creating.value = true
      try {
        await post('/api/appointment-types', newType.value)
        showNewTypeModal.value = false
        newType.value = {
          name: '',
          description: '',
          duration_minutes: 60,
          price: 0,
          color: '#0066CC',
          is_active: true
        }
        toast.success('Tipo de cita creado exitosamente')
        loadTypes()
      } catch (error) {
        handleError(error, 'Error al crear el tipo de cita')
      } finally {
        creating.value = false
      }
    }

    const editType = type => {
      editingType.value = { ...type }
      showEditTypeModal.value = true
    }

    const viewType = type => {
      viewingType.value = type
      showViewTypeModal.value = true
    }

    const viewDetail = type => {
      router.push(`/appointment-types/${type.id}`)
    }

    const updateType = async () => {
      if (updating.value) return
      updating.value = true
      try {
        await put(`/api/appointment-types/${editingType.value.id}`, editingType.value)
        showEditTypeModal.value = false
        editingType.value = null
        loadTypes()
        toast.success('Tipo de cita actualizado exitosamente')
      } catch (error) {
        handleError(error, 'Error al actualizar el tipo de cita')
      } finally {
        updating.value = false
      }
    }

    const deleteType = async type => {
      const ok = await confirm({
        title: 'Eliminar tipo de cita',
        message: `¿Estás seguro de que quieres eliminar el tipo de cita ${type.name}?`,
        confirmText: 'Eliminar',
        variant: 'danger'
      })
      if (ok) {
        try {
          await remove(`/api/appointment-types/${type.id}`)
          loadTypes()
          toast.success('Tipo de cita eliminado exitosamente')
        } catch (error) {
          handleError(error, 'Error al eliminar el tipo de cita')
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
      types,
      searchQuery,
      statusFilter,
      statusFilterOptions,
      isActiveOptions,
      editingTypeIsActive,
      showNewTypeModal,
      showEditTypeModal,
      showViewTypeModal,
      newType,
      editingType,
      viewingType,
      loadTypes,
      searchTypes,
      filterTypes,
      createType,
      editType,
      viewType,
      viewDetail,
      updateType,
      deleteType,
      goBack,
      formatCurrency
    }
  }
}
</script>
