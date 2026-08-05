<template>
  <AppLayout>
    <!-- Header Section -->
    <PageHeader
      title="Ambientes"
      subtitle="Gestiona los ambientes y consultorios"
      class="mb-6"
    >
      <template #actions>
        <UiButton
          variant="secondary"
          @click="goBack"
        >
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </template>
          Volver
        </UiButton>
        <UiButton @click="showNewEnvironmentModal = true">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
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
            @input="searchEnvironments"
            placeholder="Buscar ambientes por nombre o descripción..."
            class="w-full"
          >
            <template #prefix>
              <svg class="w-5 h-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </UiInput>
        </div>
        <div class="flex gap-3">
          <select
            v-model="statusFilter"
            @change="filterEnvironments"
            class="w-48 px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
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
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent"></div>
        <p class="mt-2 text-theme-secondary">Cargando ambientes...</p>
      </div>

      <div v-else-if="environments.length === 0" class="p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
        <p class="mt-2 text-theme-secondary">No se encontraron ambientes</p>
      </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-theme">
              <thead class="bg-theme-surface">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Ambiente
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Descripción
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Equipamiento
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Estado
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody class="bg-theme-surface-elevated divide-y divide-theme">
                <tr v-for="environment in environments" :key="environment.id" class="hover:bg-theme-surface">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center">
                          <span class="text-sm font-medium text-accent">
                            {{ environment.name.charAt(0) }}
                          </span>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-theme-primary">
                          {{ environment.name }}
                        </div>
                        <div class="text-sm text-theme-secondary">
                          ID: {{ environment.id }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm text-theme-primary">{{ environment.description || 'Sin descripción' }}</div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm text-theme-primary">{{ environment.equipment || 'Sin equipamiento' }}</div>
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
                        @click="viewDetail(environment)"
                        class="text-accent hover:text-accent-hover"
                      >
                        Ver Detalle
                      </UiButton>
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="editEnvironment(environment)"
                        class="text-accent hover:text-primary-800"
                      >
                        Editar
                      </UiButton>
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="deleteEnvironment(environment)"
                        class="text-red-600 hover:text-red-900"
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
    <div
      v-if="showNewEnvironmentModal"
      class="fixed inset-0 bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showNewEnvironmentModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Nuevo Ambiente</h3>
          <form @submit.prevent="createEnvironment">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary">Nombre del Ambiente</label>
                <input
                  v-model="newEnvironment.name"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Descripción</label>
                <textarea
                  v-model="newEnvironment.description"
                  rows="3"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                ></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Equipamiento</label>
                <textarea
                  v-model="newEnvironment.equipment"
                  rows="2"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                ></textarea>
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
            </div>
            <div class="flex justify-end space-x-3 mt-6">
              <button
                type="button"
                @click="showNewEnvironmentModal = false"
                class="px-4 py-2 text-sm font-medium text-theme-primary bg-theme-surface hover:bg-theme-surface-elevated rounded-md"
              >
                Cancelar
              </button>
              <button
                type="submit"
                :disabled="creating"
                class="px-4 py-2 text-sm font-medium text-white bg-accent hover:bg-accent-hover rounded-md disabled:opacity-50"
              >
                {{ creating ? 'Creando...' : 'Crear' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Environment Modal -->
    <div
      v-if="showEditEnvironmentModal"
      class="fixed inset-0 bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showEditEnvironmentModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Editar Ambiente</h3>
          <form @submit.prevent="updateEnvironment">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary">Nombre</label>
                <input
                  v-model="editingEnvironment.name"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Código</label>
                <input
                  v-model="editingEnvironment.code"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Descripción</label>
                <textarea
                  v-model="editingEnvironment.description"
                  rows="3"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                ></textarea>
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
            </div>
            <div class="flex justify-end space-x-3 mt-6">
              <button
                type="button"
                @click="showEditEnvironmentModal = false"
                class="px-4 py-2 text-sm font-medium text-theme-primary bg-theme-surface hover:bg-theme-surface-elevated rounded-md"
              >
                Cancelar
              </button>
              <button
                type="submit"
                class="px-4 py-2 text-sm font-medium text-white bg-accent hover:bg-accent-hover rounded-md"
              >
                Actualizar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- View Environment Modal -->
    <div
      v-if="showViewEnvironmentModal"
      class="fixed inset-0 bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showViewEnvironmentModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Ver Ambiente</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-theme-primary">Nombre</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingEnvironment?.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Código</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingEnvironment?.code }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Descripción</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingEnvironment?.description || 'Sin descripción' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Estado</label>
              <p class="mt-1 text-sm text-theme-primary">
                <span :class="getStatusColor(viewingEnvironment?.status)" class="px-2 py-1 rounded-full text-xs">
                  {{ getStatusText(viewingEnvironment?.status) }}
                </span>
              </p>
            </div>
          </div>
          <div class="flex justify-end mt-6">
            <button
              @click="showViewEnvironmentModal = false"
              class="px-4 py-2 text-sm font-medium text-theme-primary bg-theme-surface hover:bg-theme-surface-elevated rounded-md"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiCard from '../../components/ui/Card.vue'

export default {
  name: 'EnvironmentsPage',
  components: {
    AppLayout,
    UiButton,
    UiInput,
    UiSelect,
    UiCard
  },
  setup() {
    const router = useRouter()
    const { get, post, put, delete: remove } = useApi()
    const toast = useToast()

    const loading = ref(false)
    const creating = ref(false)
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
          toast.warning('No se encontraron ambientes')
        }
      } catch (error) {
        toast.error('Error al cargar los ambientes. Por favor, recarga la página.')
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
        const response = await get(`/api/dental-chairs/search?q=${encodeURIComponent(searchQuery.value)}`)
        environments.value = response.data
      } catch (error) {
      } finally {
        loading.value = false
      }
    }

    const filterEnvironments = () => {
      // Implement client-side filtering or API call
      loadEnvironments()
    }

    const createEnvironment = async () => {
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
        loadEnvironments()
      } catch (error) {
      } finally {
        creating.value = false
      }
    }

    const editEnvironment = (environment) => {
      editingEnvironment.value = { ...environment }
      showEditEnvironmentModal.value = true
    }

    const viewEnvironment = (environment) => {
      viewingEnvironment.value = environment
      showViewEnvironmentModal.value = true
    }

    const viewDetail = (environment) => {
      router.push(`/environments/${environment.id}`)
    }

    const updateEnvironment = async () => {
      try {
        await put(`/api/dental-chairs/${editingEnvironment.value.id}`, editingEnvironment.value)
        showEditEnvironmentModal.value = false
        editingEnvironment.value = null
        loadEnvironments()
        alert('Ambiente actualizado exitosamente')
      } catch (error) {
        alert('Error al actualizar el ambiente')
      }
    }

    const deleteEnvironment = async (environment) => {
      const ok = await confirm({
        title: 'Eliminar ambiente',
        message: `¿Estás seguro de que quieres eliminar el ambiente ${environment.name}?`,
        confirmText: 'Eliminar',
        variant: 'danger',
      })
      if (ok) {
        try {
          await remove(`/api/dental-chairs/${environment.id}`)
          loadEnvironments()
          alert('Ambiente eliminado exitosamente')
        } catch (error) {
          alert('Error al eliminar el ambiente')
        }
      }
    }

    const getStatusColor = (status) => {
      const colors = {
        active: 'bg-success-100 text-success-700',
        inactive: 'bg-theme-surface text-theme-primary',
        maintenance: 'bg-warning-100 text-warning-700'
      }
      return colors[status] || 'bg-theme-surface text-theme-primary'
    }

    const getStatusText = (status) => {
      const texts = {
        active: 'Activo',
        inactive: 'Inactivo',
        maintenance: 'Mantenimiento'
      }
      return texts[status] || status
    }

    const goBack = () => {
      router.push('/dashboard')
    }

    onMounted(() => {
      loadEnvironments()
    })

    return {
      loading,
      creating,
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
