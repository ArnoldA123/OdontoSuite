<template>
  <AppLayout>
    <!-- Header Section -->
    <PageHeader
      title="Profesionales"
      subtitle="Gestiona el equipo médico"
      class="mb-6"
    >
      <template #actions>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </template>
          Volver
        </UiButton>
        <UiButton @click="showNewProfessionalModal = true">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
          </template>
          Nuevo Profesional
        </UiButton>
      </template>
    </PageHeader>

    <!-- Search and Filters -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            @input="searchProfessionals"
            placeholder="Buscar profesionales por nombre, especialidad o email..."
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
            v-model="specialtyFilter"
            @change="filterProfessionals"
            class="w-64 px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
          >
            <option value="">Todas las especialidades</option>
            <option value="general">Odontología General</option>
            <option value="orthodontics">Ortodoncia</option>
            <option value="endodontics">Endodoncia</option>
            <option value="periodontics">Periodoncia</option>
            <option value="oral_surgery">Cirugía Oral</option>
            <option value="pediatric">Odontopediatría</option>
            <option value="prosthodontics">Prótesis Dental</option>
            <option value="cosmetic">Odontología Estética</option>
          </select>
        </div>
      </div>
    </UiCard>

    <!-- Professionals List -->
    <UiCard variant="glass" class="overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent"></div>
        <p class="mt-2 text-theme-secondary">Cargando profesionales...</p>
      </div>

      <div v-else-if="professionals.length === 0" class="p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
        </svg>
        <p class="mt-2 text-theme-secondary">No se encontraron profesionales</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-theme">
          <thead class="bg-theme-surface">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Profesional
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Especialidad
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                    Contacto
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
                <tr v-for="professional in professionals" :key="professional.id" class="hover:bg-theme-surface">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center">
                          <span class="text-sm font-medium text-accent">
                            {{ professional.name.charAt(0) }}
                          </span>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-theme-primary">
                          {{ professional.name }}
                        </div>
                        <div class="text-sm text-theme-secondary">
                          {{ professional.username }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-primary-100 text-primary-800">
                      {{ getSpecialtyText(professional.specialty) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-theme-primary">{{ professional.email }}</div>
                    <div class="text-sm text-theme-secondary">{{ professional.phone || 'Sin teléfono' }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      :class="professional.is_active ? 'bg-success-100 text-success-700' : 'bg-error-100 text-error-700'"
                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                    >
                      {{ professional.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-2">
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="viewDetail(professional)"
                        class="text-accent hover:text-accent-hover"
                      >
                        Ver Detalle
                      </UiButton>
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="editProfessional(professional)"
                        class="text-accent hover:text-primary-800"
                      >
                        Editar
                      </UiButton>
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="deleteProfessional(professional)"
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

    <!-- New Professional Modal -->
    <div
      v-if="showNewProfessionalModal"
      class="fixed inset-0 bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showNewProfessionalModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Nuevo Profesional</h3>
          <form @submit.prevent="createProfessional">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary">Nombre Completo</label>
                <input
                  v-model="newProfessional.name"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Usuario</label>
                <input
                  v-model="newProfessional.username"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Email</label>
                <input
                  v-model="newProfessional.email"
                  type="email"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Teléfono</label>
                <input
                  v-model="newProfessional.phone"
                  type="tel"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Especialidad</label>
                <select
                  v-model="newProfessional.specialty"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                >
                  <option value="">Seleccionar especialidad</option>
                  <option value="general">Odontología General</option>
                  <option value="orthodontics">Ortodoncia</option>
                  <option value="endodontics">Endodoncia</option>
                  <option value="periodontics">Periodoncia</option>
                  <option value="oral_surgery">Cirugía Oral</option>
                  <option value="pediatric">Odontopediatría</option>
                  <option value="prosthodontics">Prótesis Dental</option>
                  <option value="cosmetic">Odontología Estética</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Contraseña</label>
                <input
                  v-model="newProfessional.password"
                  type="password"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
              <button
                type="button"
                @click="showNewProfessionalModal = false"
                class="px-4 py-2 text-sm font-medium text-theme-primary bg-theme-surface hover:bg-theme-surface-elevated rounded-md"
              >
                Cancelar
              </button>
              <button
                type="submit"
                :disabled="creating"
                class="px-4 py-2 text-sm font-medium text-white bg-success-600 hover:bg-success-700 rounded-md disabled:opacity-50"
              >
                {{ creating ? 'Creando...' : 'Crear' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Professional Modal -->
    <div
      v-if="showEditProfessionalModal"
      class="fixed inset-0 bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showEditProfessionalModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Editar Profesional</h3>
          <form @submit.prevent="updateProfessional">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary">Nombre</label>
                <input
                  v-model="editingProfessional.name"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Usuario</label>
                <input
                  v-model="editingProfessional.username"
                  type="text"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Email</label>
                <input
                  v-model="editingProfessional.email"
                  type="email"
                  required
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Teléfono</label>
                <input
                  v-model="editingProfessional.phone"
                  type="tel"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary">Especialidad</label>
                <select
                  v-model="editingProfessional.specialty"
                  class="mt-1 block w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
                >
                  <option value="">Seleccionar especialidad</option>
                  <option value="general">Odontología General</option>
                  <option value="orthodontics">Ortodoncia</option>
                  <option value="endodontics">Endodoncia</option>
                  <option value="periodontics">Periodoncia</option>
                  <option value="oral_surgery">Cirugía Oral</option>
                  <option value="pediatric">Odontopediatría</option>
                  <option value="prosthodontics">Prótesis Dental</option>
                  <option value="cosmetic">Odontología Estética</option>
                </select>
              </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
              <button
                type="button"
                @click="showEditProfessionalModal = false"
                class="px-4 py-2 text-sm font-medium text-theme-primary bg-theme-surface hover:bg-theme-surface-elevated rounded-md"
              >
                Cancelar
              </button>
              <button
                type="submit"
                class="px-4 py-2 text-sm font-medium text-white bg-success-600 hover:bg-success-700 rounded-md"
              >
                Actualizar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- View Professional Modal -->
    <div
      v-if="showViewProfessionalModal"
      class="fixed inset-0 bg-black/50 overflow-y-auto h-full w-full z-50"
      @click="showViewProfessionalModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-theme-surface-elevated"
        @click.stop
      >
        <div class="mt-3">
          <h3 class="text-lg font-medium text-theme-primary mb-4">Ver Profesional</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-theme-primary">Nombre</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingProfessional?.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Usuario</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingProfessional?.username }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Email</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingProfessional?.email }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Teléfono</label>
              <p class="mt-1 text-sm text-theme-primary">{{ viewingProfessional?.phone || 'Sin teléfono' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Especialidad</label>
              <p class="mt-1 text-sm text-theme-primary">{{ getSpecialtyText(viewingProfessional?.specialty) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary">Estado</label>
              <p class="mt-1 text-sm text-theme-primary">
                <span :class="viewingProfessional?.is_active ? 'text-green-600' : 'text-red-600'">
                  {{ viewingProfessional?.is_active ? 'Activo' : 'Inactivo' }}
                </span>
              </p>
            </div>
          </div>
          <div class="flex justify-end mt-6">
            <button
              @click="showViewProfessionalModal = false"
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
  name: 'ProfessionalsPage',
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
    const professionals = ref([])
    const searchQuery = ref('')
    const specialtyFilter = ref('')
    const showNewProfessionalModal = ref(false)
    const showEditProfessionalModal = ref(false)
    const showViewProfessionalModal = ref(false)
    const editingProfessional = ref(null)
    const viewingProfessional = ref(null)

    const newProfessional = ref({
      name: '',
      username: '',
      email: '',
      phone: '',
      specialty: '',
      password: ''
    })

    const loadProfessionals = async () => {
      loading.value = true
      try {
        const response = await get('/api/users?role=odontologo')
        professionals.value = response?.data || []

        if (professionals.value.length === 0) {
          toast.warning('No se encontraron profesionales')
        }
      } catch (error) {
        toast.error('Error al cargar los profesionales. Por favor, recarga la página.')
        professionals.value = []
      } finally {
        loading.value = false
      }
    }

    const searchProfessionals = async () => {
      if (searchQuery.value.length < 2) {
        loadProfessionals()
        return
      }

      loading.value = true
      try {
        const response = await get(`/api/users/search?q=${encodeURIComponent(searchQuery.value)}&role=odontologo`)
        professionals.value = response.data
      } catch (error) {
      } finally {
        loading.value = false
      }
    }

    const filterProfessionals = () => {
      // Implement client-side filtering or API call
      loadProfessionals()
    }

    const createProfessional = async () => {
      creating.value = true
      try {
        const professionalData = {
          ...newProfessional.value,
          role: 'odontologo'
        }
        await post('/api/users', professionalData)
        showNewProfessionalModal.value = false
        newProfessional.value = {
          name: '',
          username: '',
          email: '',
          phone: '',
          specialty: '',
          password: ''
        }
        loadProfessionals()
      } catch (error) {
      } finally {
        creating.value = false
      }
    }

    const editProfessional = (professional) => {
      editingProfessional.value = { ...professional }
      showEditProfessionalModal.value = true
    }

    const viewProfessional = (professional) => {
      viewingProfessional.value = professional
      showViewProfessionalModal.value = true
    }

    const viewDetail = (professional) => {
      router.push(`/professionals/${professional.id}`)
    }

    const updateProfessional = async () => {
      try {
        await put(`/api/users/${editingProfessional.value.id}`, editingProfessional.value)
        showEditProfessionalModal.value = false
        editingProfessional.value = null
        loadProfessionals()
        alert('Profesional actualizado exitosamente')
      } catch (error) {
        alert('Error al actualizar el profesional')
      }
    }

    const deleteProfessional = async (professional) => {
      const ok = await confirm({
        title: 'Eliminar profesional',
        message: `¿Estás seguro de que quieres eliminar a ${professional.name}?`,
        confirmText: 'Eliminar',
        variant: 'danger',
      })
      if (ok) {
        try {
          await remove(`/api/users/${professional.id}`)
          loadProfessionals()
          alert('Profesional eliminado exitosamente')
        } catch (error) {
          alert('Error al eliminar el profesional')
        }
      }
    }

    const getSpecialtyText = (specialty) => {
      const specialties = {
        general: 'Odontología General',
        orthodontics: 'Ortodoncia',
        endodontics: 'Endodoncia',
        periodontics: 'Periodoncia',
        oral_surgery: 'Cirugía Oral',
        pediatric: 'Odontopediatría',
        prosthodontics: 'Prótesis Dental',
        cosmetic: 'Odontología Estética'
      }
      return specialties[specialty] || specialty
    }

    const goBack = () => {
      router.push('/dashboard')
    }

    onMounted(() => {
      loadProfessionals()
    })

    return {
      loading,
      creating,
      professionals,
      searchQuery,
      specialtyFilter,
      showNewProfessionalModal,
      showEditProfessionalModal,
      showViewProfessionalModal,
      newProfessional,
      editingProfessional,
      viewingProfessional,
      loadProfessionals,
      searchProfessionals,
      filterProfessionals,
      createProfessional,
      editProfessional,
      viewProfessional,
      viewDetail,
      updateProfessional,
      deleteProfessional,
      getSpecialtyText,
      goBack
    }
  }
}
</script>
