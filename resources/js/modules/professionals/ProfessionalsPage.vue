<template>
  <AppLayout>
    <!-- Header Section -->
    <PageHeader title="Profesionales" subtitle="Gestiona el equipo médico" class="mb-6">
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
        <UiButton @click="showNewProfessionalModal = true">
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
            placeholder="Buscar profesionales por nombre, especialidad o email..."
            class="w-full"
            @input="searchProfessionals"
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
            v-model="specialtyFilter"
            :options="specialtyOptions"
            placeholder="Todas las especialidades"
            class="w-64"
            @change="filterProfessionals"
          />
        </div>
      </div>
    </UiCard>

    <!-- Professionals List -->
    <UiCard variant="glass" class="overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent" />
        <p class="mt-2 text-theme-secondary">Cargando profesionales...</p>
      </div>

      <div v-else-if="professionals.length === 0" class="p-8 text-center">
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
            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
          />
        </svg>
        <p class="mt-2 text-theme-secondary">No se encontraron profesionales</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-theme">
          <thead class="bg-theme-surface">
            <tr>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Profesional
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Especialidad
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Contacto
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
          <tbody class="bg-[color:var(--color-surface-elevated)] divide-y divide-theme">
            <tr
              v-for="professional in professionals"
              :key="professional.id"
              class="hover:bg-theme-surface"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div
                      class="h-10 w-10 rounded-full bg-[color:var(--color-primary-100)] flex items-center justify-center"
                    >
                      <span class="text-sm font-medium text-accent">
                        {{ professional.name.charAt(0) }}
                      </span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-[color:var(--color-text-primary)]">
                      {{ professional.name }}
                    </div>
                    <div class="text-sm text-theme-secondary">
                      {{ professional.username }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <UiBadge variant="primary" size="sm" shape="pill">
                  {{ getSpecialtyText(professional.specialty) }}
                </UiBadge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-[color:var(--color-text-primary)]">
                  {{ professional.email }}
                </div>
                <div class="text-sm text-theme-secondary">
                  {{ professional.phone || 'Sin teléfono' }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <UiBadge
                  :variant="professional.is_active ? 'success' : 'error'"
                  size="sm"
                  shape="pill"
                >
                  {{ professional.is_active ? 'Activo' : 'Inactivo' }}
                </UiBadge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
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
  </AppLayout>

  <!-- New Professional Modal -->
  <UiModal v-model="showNewProfessionalModal" title="Nuevo Profesional" size="md">
    <form id="form-new-prof" class="space-y-4" @submit.prevent="createProfessional">
      <UiInput v-model="newProfessional.name" label="Nombre Completo" type="text" required />
      <UiInput v-model="newProfessional.username" label="Usuario" type="text" required />
      <UiInput v-model="newProfessional.email" label="Email" type="email" required />
      <UiInput v-model="newProfessional.phone" label="Teléfono" type="tel" />
      <UiSelect
        v-model="newProfessional.specialty"
        :options="specialtyOptions"
        label="Especialidad"
        placeholder="Seleccionar especialidad"
        required
      />
      <UiInput v-model="newProfessional.password" label="Contraseña" type="password" required />
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="creating" @click="showNewProfessionalModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" :loading="creating" @click="createProfessional">Crear</UiButton>
    </template>
  </UiModal>

  <!-- Edit Professional Modal -->
  <UiModal v-model="showEditProfessionalModal" title="Editar Profesional" size="md">
    <form
      v-if="editingProfessional"
      id="form-edit-prof"
      class="space-y-4"
      @submit.prevent="updateProfessional"
    >
      <UiInput v-model="editingProfessional.name" label="Nombre" type="text" required />
      <UiInput v-model="editingProfessional.username" label="Usuario" type="text" required />
      <UiInput v-model="editingProfessional.email" label="Email" type="email" required />
      <UiInput v-model="editingProfessional.phone" label="Teléfono" type="tel" />
      <UiSelect
        v-model="editingProfessional.specialty"
        :options="specialtyOptions"
        label="Especialidad"
        placeholder="Seleccionar especialidad"
      />
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="updating" @click="showEditProfessionalModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" :loading="updating" @click="updateProfessional">Actualizar</UiButton>
    </template>
  </UiModal>

  <!-- View Professional Modal -->
  <UiModal v-model="showViewProfessionalModal" title="Ver Profesional" size="md">
    <div v-if="viewingProfessional" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-[color:var(--color-text-primary)]">
          Nombre
        </label>
        <p class="mt-1 text-sm text-[color:var(--color-text-primary)]">
          {{ viewingProfessional.name }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-[color:var(--color-text-primary)]">
          Usuario
        </label>
        <p class="mt-1 text-sm text-[color:var(--color-text-primary)]">
          {{ viewingProfessional.username }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-[color:var(--color-text-primary)]">
          Email
        </label>
        <p class="mt-1 text-sm text-[color:var(--color-text-primary)]">
          {{ viewingProfessional.email }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-[color:var(--color-text-primary)]">
          Teléfono
        </label>
        <p class="mt-1 text-sm text-[color:var(--color-text-primary)]">
          {{ viewingProfessional.phone || 'Sin teléfono' }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-[color:var(--color-text-primary)]">
          Especialidad
        </label>
        <p class="mt-1 text-sm text-[color:var(--color-text-primary)]">
          {{ getSpecialtyText(viewingProfessional.specialty) }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-[color:var(--color-text-primary)]">
          Estado
        </label>
        <p class="mt-1 text-sm text-[color:var(--color-text-primary)]">
          <span :class="viewingProfessional.is_active ? 'text-success-700' : 'text-error-700'">
            {{ viewingProfessional.is_active ? 'Activo' : 'Inactivo' }}
          </span>
        </p>
      </div>
    </div>
    <template #footer>
      <UiButton variant="secondary" @click="showViewProfessionalModal = false">Cerrar</UiButton>
    </template>
  </UiModal>
</template>

<script>
import { ref, onMounted } from 'vue'
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
import { SPECIALTY_LABELS, SPECIALTY_OPTIONS } from './professionals.constants'

export default {
  name: 'ProfessionalsPage',
  components: {
    AppLayout,
    UiButton,
    UiInput,
    UiSelect,
    UiBadge,
    UiCard,
    UiModal
  },
  setup() {
    const router = useRouter()
    const { get, post, put, delete: remove } = useApi()
    const toast = useToast()
    const { handleError } = useErrorHandler()
    const { confirm } = useConfirm()

    const loading = ref(false)
    const creating = ref(false)
    const updating = ref(false)
    const professionals = ref([])
    const searchQuery = ref('')
    const specialtyFilter = ref('')
    const specialtyOptions = SPECIALTY_OPTIONS
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
          toast.info('Aún no hay profesionales registrados')
        }
      } catch (error) {
        handleError(error, 'Error al cargar los profesionales. Por favor, recarga la página.')
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
        const response = await get(
          `/api/users/search?q=${encodeURIComponent(searchQuery.value)}&role=odontologo`
        )
        professionals.value = response.data
      } catch (error) {
        handleError(error, 'Error al buscar profesionales')
      } finally {
        loading.value = false
      }
    }

    const filterProfessionals = () => {
      // Implement client-side filtering or API call
      loadProfessionals()
    }

    const createProfessional = async () => {
      if (creating.value) return
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
        toast.success('Profesional creado exitosamente')
        loadProfessionals()
      } catch (error) {
        handleError(error, 'Error al crear el profesional')
      } finally {
        creating.value = false
      }
    }

    const editProfessional = professional => {
      editingProfessional.value = { ...professional }
      showEditProfessionalModal.value = true
    }

    const viewProfessional = professional => {
      viewingProfessional.value = professional
      showViewProfessionalModal.value = true
    }

    const viewDetail = professional => {
      router.push(`/professionals/${professional.id}`)
    }

    const updateProfessional = async () => {
      if (updating.value) return
      updating.value = true
      try {
        await put(`/api/users/${editingProfessional.value.id}`, editingProfessional.value)
        showEditProfessionalModal.value = false
        editingProfessional.value = null
        loadProfessionals()
        toast.success('Profesional actualizado exitosamente')
      } catch (error) {
        handleError(error, 'Error al actualizar el profesional')
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

    const getSpecialtyText = specialty => {
      return SPECIALTY_LABELS[specialty] || specialty
    }

    const goBack = () => {
      router.back()
    }

    onMounted(() => {
      loadProfessionals()
    })

    return {
      loading,
      creating,
      updating,
      professionals,
      searchQuery,
      specialtyFilter,
      specialtyOptions,
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
