<template>
  <AppLayout>
    <!-- Header Section -->
    <PageHeader title="Ambientes" subtitle="Gestiona los ambientes y consultorios" class="mb-6">
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
          <select
            v-model="statusFilter"
            class="w-48 px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
            @change="filterEnvironments"
          >
            <option value="">Todos los estados</option>
            <option value="active">Activos</option>
            <option value="inactive">Inactivos</option>
            <option value="maintenance">Mantenimiento</option>
          </select>
        </div>
      </div>
    </UiCard>

    <!-- Environments List -->
    <UiCard variant="glass" class="overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />
        <p class="mt-2 text-theme-secondary">Cargando ambientes...</p>
      </div>

      <div v-else-if="environments.length === 0" class="p-8 text-center">
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
            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
          />
        </svg>
        <p class="mt-2 text-theme-secondary">No se encontraron ambientes</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-theme">
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
          <tbody class="bg-theme-surface-elevated divide-y divide-theme">
            <tr
              v-for="environment in environments"
              :key="environment.id"
              class="hover:bg-theme-surface"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div
                      class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center"
                    >
                      <span class="text-sm font-medium text-accent">
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
                <span
                  :class="getStatusColor(environment.status)"
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                >
                  {{ getStatusText(environment.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-accent hover:text-accent-hover"
                    @click="viewDetail(environment)"
                  >
                    Ver Detalle
                  </UiButton>
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-accent hover:text-primary-800"
                    @click="editEnvironment(environment)"
                  >
                    Editar
                  </UiButton>
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-red-600 hover:text-red-900"
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

  <!-- New Environment Modal -->
  <UiModal v-model="showNewEnvironmentModal" title="Nuevo Ambiente" size="md">
    <form id="form-new-env" class="space-y-4" @submit.prevent="createEnvironment">
      <div>
        <label class="block text-sm font-medium text-theme-primary">Nombre del Ambiente</label>
        <input
          v-model="newEnvironment.name"
          type="text"
          required
          class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Descripción</label>
        <textarea
          v-model="newEnvironment.description"
          rows="3"
          class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Equipamiento</label>
        <textarea
          v-model="newEnvironment.equipment"
          rows="2"
          class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Estado</label>
        <select
          v-model="newEnvironment.status"
          required
          class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        >
          <option value="active">Activo</option>
          <option value="inactive">Inactivo</option>
          <option value="maintenance">Mantenimiento</option>
        </select>
      </div>
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="creating" @click="showNewEnvironmentModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" :loading="creating" @click="createEnvironment">Crear</UiButton>
    </template>
  </UiModal>

  <!-- Edit Environment Modal -->
  <UiModal v-model="showEditEnvironmentModal" title="Editar Ambiente" size="md">
    <form
      v-if="editingEnvironment"
      id="form-edit-env"
      class="space-y-4"
      @submit.prevent="updateEnvironment"
    >
      <div>
        <label class="block text-sm font-medium text-theme-primary">Nombre</label>
        <input
          v-model="editingEnvironment.name"
          type="text"
          required
          class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Código</label>
        <input
          v-model="editingEnvironment.code"
          type="text"
          required
          class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Descripción</label>
        <textarea
          v-model="editingEnvironment.description"
          rows="3"
          class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Estado</label>
        <select
          v-model="editingEnvironment.status"
          required
          class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        >
          <option value="active">Activo</option>
          <option value="inactive">Inactivo</option>
          <option value="maintenance">Mantenimiento</option>
        </select>
      </div>
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
          <span
            :class="getStatusColor(viewingEnvironment.status)"
            class="px-2 py-1 rounded-full text-xs"
          >
            {{ getStatusText(viewingEnvironment.status) }}
          </span>
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

export default {
  name: 'EnvironmentsPage',
  components: {
    AppLayout,
    UiButton,
    UiInput,
    UiSelect,
    UiCard,
    UiModal,
    UiEmptyState
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

    const getStatusColor = status => {
      const colors = {
        active: 'bg-success-100 text-success-700',
        inactive: 'bg-theme-surface text-theme-primary',
        maintenance: 'bg-warning-100 text-warning-700'
      }
      return colors[status] || 'bg-theme-surface text-theme-primary'
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
      getStatusColor,
      getStatusText,
      goBack
    }
  }
}
</script>
