<template>
  <AppLayout>
    <!-- Header Section -->
    <PageHeader
      title="Tipos de Cita"
      subtitle="Gestiona los tipos de citas disponibles"
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
        <UiButton @click="showNewTypeModal = true">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
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
            @input="searchTypes"
            placeholder="Buscar tipos de cita por nombre o descripción..."
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
            @change="filterTypes"
            class="w-48 px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
          >
            <option value="">Todos los estados</option>
            <option value="active">Activos</option>
            <option value="inactive">Inactivos</option>
          </select>
        </div>
      </div>
    </UiCard>

    <!-- Types List -->
    <UiCard variant="glass" class="overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent"></div>
        <p class="mt-2 text-theme-secondary">Cargando tipos de cita...</p>
      </div>

      <div v-else-if="types.length === 0" class="p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
        </svg>
        <p class="mt-2 text-theme-secondary">No se encontraron tipos de cita</p>
      </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-theme">
              <thead class="bg-theme-surface">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Tipo de Cita
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Duración
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Precio
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Color
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
                    <div class="text-sm text-theme-primary">S/ {{ type.price || '0.00' }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div
                        class="w-4 h-4 rounded-full mr-2"
                        :style="{ backgroundColor: type.color }"
                      ></div>
                      <span class="text-sm text-theme-secondary">{{ type.color }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      :class="type.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                    >
                      {{ type.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-2">
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="viewDetail(type)"
                        class="text-accent hover:text-accent-hover"
                      >
                        Ver Detalle
                      </UiButton>
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="editType(type)"
                        class="text-accent hover:text-primary-800"
                      >
                        Editar
                      </UiButton>
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="deleteType(type)"
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

    <!-- New Type Modal -->
    <div
      v-if="showNewTypeModal"
      class="fixed inset-0 bg-black bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showNewTypeModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Nuevo Tipo de Cita</h3>
          <form @submit.prevent="createType">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary">Nombre del Tipo</label>
                <input
                  v-model="newType.name"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Descripción</label>
                <textarea
                  v-model="newType.description"
                  rows="3"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                ></textarea>
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
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Precio (S/)</label>
                <input
                  v-model="newType.price"
                  type="number"
                  min="0"
                  step="0.01"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Color</label>
                <div class="flex items-center space-x-2">
                  <input
                    v-model="newType.color"
                    type="color"
                    class="w-12 h-8 border border-theme rounded"
                  />
                  <input
                    v-model="newType.color"
                    type="text"
                    placeholder="#0066CC"
                    class="flex-1 px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                  />
                </div>
              </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
              <button
                type="button"
                @click="showNewTypeModal = false"
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

    <!-- Edit Type Modal -->
    <div
      v-if="showEditTypeModal"
      class="fixed inset-0 bg-black bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showEditTypeModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Editar Tipo de Cita</h3>
          <form @submit.prevent="updateType">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary">Nombre</label>
                <input
                  v-model="editingType.name"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Descripción</label>
                <textarea
                  v-model="editingType.description"
                  rows="3"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                ></textarea>
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
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Precio</label>
                <input
                  v-model="editingType.price"
                  type="number"
                  min="0"
                  step="0.01"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Color</label>
                <input
                  v-model="editingType.color"
                  type="color"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Estado</label>
                <select
                  v-model="editingType.is_active"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                >
                  <option :value="true">Activo</option>
                  <option :value="false">Inactivo</option>
                </select>
              </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
              <button
                type="button"
                @click="showEditTypeModal = false"
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

    <!-- View Type Modal -->
    <div
      v-if="showViewTypeModal"
      class="fixed inset-0 bg-black bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showViewTypeModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Ver Tipo de Cita</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-theme-primary">Nombre</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingType?.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Descripción</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingType?.description || 'Sin descripción' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Duración</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingType?.duration_minutes }} minutos</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Precio</label>
              <p class="mt-1 text-sm text-theme-primary">S/ {{ viewingType?.price }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Color</label>
              <div class="mt-1 flex items-center">
                <div
                  class="w-6 h-6 rounded border border-theme mr-2"
                  :style="{ backgroundColor: viewingType?.color }"
                ></div>
                <span class="text-sm text-theme-primary">{{ viewingType?.color }}</span>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Estado</label>
              <p class="mt-1 text-sm text-theme-primary">
                <span :class="viewingType?.is_active ? 'text-green-600' : 'text-red-600'">
                  {{ viewingType?.is_active ? 'Activo' : 'Inactivo' }}
                </span>
              </p>
            </div>
          </div>
          <div class="flex justify-end mt-6">
            <button
              @click="showViewTypeModal = false"
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
import { useErrorHandler } from '../../composables/useErrorHandler'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiCard from '../../components/ui/Card.vue'

export default {
  name: 'AppointmentTypesPage',
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
    const { handleError } = useErrorHandler()

    const loading = ref(false)
    const creating = ref(false)
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

    const loadTypes = async () => {
      loading.value = true
      try {
        const response = await get('/api/appointment-types')
        types.value = response?.data || []

        if (types.value.length === 0) {
          toast.warning('No se encontraron tipos de cita')
        }
      } catch (error) {
        toast.error('Error al cargar los tipos de cita. Por favor, recarga la página.')
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
        const response = await get(`/api/appointment-types/search?q=${encodeURIComponent(searchQuery.value)}`)
        types.value = response.data
      } catch (error) {
      } finally {
        loading.value = false
      }
    }

    const filterTypes = () => {
      // Implement client-side filtering or API call
      loadTypes()
    }

    const createType = async () => {
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
        loadTypes()
      } catch (error) {
      } finally {
        creating.value = false
      }
    }

    const editType = (type) => {
      editingType.value = { ...type }
      showEditTypeModal.value = true
    }

    const viewType = (type) => {
      viewingType.value = type
      showViewTypeModal.value = true
    }

    const viewDetail = (type) => {
      router.push(`/appointment-types/${type.id}`)
    }

    const updateType = async () => {
      try {
        await put(`/api/appointment-types/${editingType.value.id}`, editingType.value)
        showEditTypeModal.value = false
        editingType.value = null
        loadTypes()
        toast.success('Tipo de cita actualizado exitosamente')
      } catch (error) {
        handleError(error, 'Error al actualizar el tipo de cita')
      }
    }

    const deleteType = async (type) => {
      const ok = await confirm({
        title: 'Eliminar tipo de cita',
        message: `¿Estás seguro de que quieres eliminar el tipo de cita ${type.name}?`,
        confirmText: 'Eliminar',
        variant: 'danger',
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
      router.push('/dashboard')
    }

    onMounted(() => {
      loadTypes()
    })

    return {
      loading,
      creating,
      types,
      searchQuery,
      statusFilter,
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
      goBack
    }
  }
}
</script>
