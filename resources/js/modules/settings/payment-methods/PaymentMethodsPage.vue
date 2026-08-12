<template>
  <AppLayout>
    <PageHeader
      title="Metodos de Pago"
      subtitle="Gestiona los metodos de pago aceptados en la clinica"
      class="mb-6"
    >
      <template #actions>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </template>
          Volver
        </UiButton>
        <UiButton @click="openCreate">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
          </template>
          Nuevo Metodo
        </UiButton>
      </template>
    </PageHeader>

    <!-- Contadores — the page content sits on the canvas surface (DLR-R-001);
         AppLayout paints it for the route, this row pins the token locally. -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 bg-canvas">
      <UiCard variant="glass">
        <div class="text-center">
          <p class="text-sm font-medium text-theme-secondary">Total</p>
          <p class="text-3xl font-bold text-theme-primary tabular-nums">{{ methods.length }}</p>
        </div>
      </UiCard>
      <UiCard variant="glass">
        <div class="text-center">
          <p class="text-sm font-medium text-theme-secondary">Del sistema</p>
          <p class="text-3xl font-bold text-systemBlue-600 tabular-nums">
            {{ systemMethods.length }}
          </p>
        </div>
      </UiCard>
      <UiCard variant="glass">
        <div class="text-center">
          <p class="text-sm font-medium text-theme-secondary">Custom</p>
          <p class="text-3xl font-bold text-systemGreen-600 tabular-nums">
            {{ customMethods.length }}
          </p>
        </div>
      </UiCard>
    </div>

    <!-- Filtros -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar por nombre, codigo o descripcion..."
            class="w-full"
            @input="onSearch"
          >
            <template #prefix>
              <svg class="w-5 h-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </UiInput>
        </div>
        <div class="flex gap-3">
          <select
            v-model="statusFilter"
            class="w-40 px-3 py-2 border border-hairline rounded-lg bg-theme-surface-elevated text-theme-primary"
            @change="onFilter"
          >
            <option value="">Todos</option>
            <option value="true">Activos</option>
            <option value="false">Inactivos</option>
          </select>
        </div>
      </div>
    </UiCard>

    <!-- Loading -->
    <div v-if="loading && methods.length === 0" class="flex justify-center py-12">
      <LoadingSpinner size="lg" />
    </div>

    <!-- Empty state -->
    <UiCard v-else-if="!hasMethods" variant="glass" class="text-center py-12">
      <EmptyState
        :icon="CreditCardIcon"
        title="No hay metodos de pago registrados"
        description="Agrega metodos de pago para poder registrar cobros en caja."
        action-text="Nuevo Metodo"
        @action="openCreate"
      />
    </UiCard>

    <!-- Tabla -->
    <UiCard v-else variant="glass">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-hairline">
              <th scope="col" class="text-left py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider">
                Codigo
              </th>
              <th scope="col" class="text-left py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider">
                Nombre
              </th>
              <th scope="col" class="text-left py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider">
                Pasarela
              </th>
              <th scope="col" class="text-center py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider">
                Comision
              </th>
              <th scope="col" class="text-center py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider">
                Tipo
              </th>
              <th scope="col" class="text-center py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider">
                Estado
              </th>
              <th scope="col" class="text-right py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="m in methods"
              :key="m.id"
              class="border-b border-hairline hover:bg-theme-surface/30 transition-colors"
            >
              <td class="py-3 px-4">
                <span class="font-mono text-sm text-theme-primary">{{ m.code }}</span>
              </td>
              <td class="py-3 px-4">
                <div class="text-sm font-medium text-theme-primary">{{ m.name }}</div>
                <div v-if="m.description" class="text-xs text-theme-secondary mt-0.5">
                  {{ m.description }}
                </div>
              </td>
              <td class="py-3 px-4">
                <span v-if="m.gateway_type && m.gateway_type !== 'manual'" class="text-xs font-medium text-systemBlue-600">
                  {{ m.gateway_type }}
                  <span v-if="m.has_gateway_config" class="text-systemGreen-600" title="Credenciales configuradas"> (configurado)</span>
                </span>
                <span v-else class="text-xs text-theme-secondary">Manual</span>
              </td>
              <td class="py-3 px-4 text-center text-sm text-theme-primary tabular-nums">
                {{ m.commission_percentage ?? 0 }}%
              </td>
              <td class="py-3 px-4 text-center">
                <UiStatusBadge
                  :variant="m.is_system ? 'warning' : 'neutral'"
                  :label="m.is_system ? 'Sistema' : 'Custom'"
                  size="sm"
                />
              </td>
              <td class="py-3 px-4 text-center">
                <UiStatusBadge
                  :variant="m.is_active ? 'success' : 'neutral'"
                  :label="m.is_active ? 'Activo' : 'Inactivo'"
                  size="sm"
                />
              </td>
              <td class="py-3 px-4">
                <div class="flex justify-end gap-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-systemBlue-600 hover:text-systemBlue-700"
                    @click="openEdit(m)"
                  >
                    Editar
                  </UiButton>
                  <UiButton
                    v-if="m.is_active && !m.is_system"
                    variant="ghost"
                    size="sm"
                    class="text-systemRed-600 hover:text-systemRed-700"
                    @click="confirmDeactivate(m)"
                  >
                    Desactivar
                  </UiButton>
                  <UiButton
                    v-if="!m.is_active && !m.is_system"
                    variant="ghost"
                    size="sm"
                    class="text-systemGreen-600 hover:text-systemGreen-700"
                    @click="confirmActivate(m)"
                  >
                    Activar
                  </UiButton>
                  <UiButton
                    v-if="!m.is_system && !m.transactions_count"
                    variant="ghost"
                    size="sm"
                    class="text-systemRed-600 hover:text-systemRed-700"
                    @click="confirmDelete(m)"
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

    <!-- Modal de crear/editar -->
    <PaymentMethodFormModal
      v-if="showForm"
      :method="editingMethod"
      @close="closeForm"
      @saved="onSaved"
    />

    <!-- Confirm deactivate -->
    <UiModal v-model="showDeactivateConfirm" title="Desactivar metodo" size="sm">
      <p class="text-theme-primary">
        ¿Desactivar el metodo <strong>{{ methodToToggle?.name }}</strong>?
        No aparecera en los dropdowns de cobro.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <UiButton variant="secondary" @click="showDeactivateConfirm = false">Cancelar</UiButton>
          <UiButton variant="danger" :disabled="toggling" @click="doDeactivate">Desactivar</UiButton>
        </div>
      </template>
    </UiModal>

    <!-- Confirm activate -->
    <UiModal v-model="showActivateConfirm" title="Activar metodo" size="sm">
      <p class="text-theme-primary">
        ¿Activar el metodo <strong>{{ methodToToggle?.name }}</strong>?
        Volvera a estar disponible en los dropdowns de cobro.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <UiButton variant="secondary" @click="showActivateConfirm = false">Cancelar</UiButton>
          <UiButton :disabled="toggling" @click="doActivate">Activar</UiButton>
        </div>
      </template>
    </UiModal>

    <!-- Confirm delete -->
    <UiModal v-model="showDeleteConfirm" title="Eliminar metodo" size="sm">
      <p class="text-theme-primary">
        ¿Eliminar permanentemente el metodo <strong>{{ methodToDelete?.name }}</strong>?
        Esta accion no se puede deshacer.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <UiButton variant="secondary" @click="showDeleteConfirm = false">Cancelar</UiButton>
          <UiButton variant="danger" :disabled="deleting" @click="doDelete">Eliminar</UiButton>
        </div>
      </template>
    </UiModal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { usePaymentMethods } from '../../../composables/usePaymentMethods'
import { useToast } from '../../../composables/useToast'
import AppLayout from '../../../components/layout/AppLayout.vue'
import PageHeader from '../../../components/layout/PageHeader.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiCard from '../../../components/ui/Card.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiStatusBadge from '../../../components/ui/StatusBadge.vue'
import UiModal from '../../../components/ui/Modal.vue'
import EmptyState from '../../../components/ui/EmptyState.vue'
import LoadingSpinner from '../../../components/ui/LoadingSpinner.vue'
import PaymentMethodFormModal from './PaymentMethodFormModal.vue'
import { CreditCardIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const toast = useToast()
const {
  methods,
  loading,
  error,
  hasMethods,
  systemMethods,
  customMethods,
  getMethods,
  toggleActive,
  deleteMethod,
  clearError
} = usePaymentMethods('/api/payment-methods')

const searchQuery = ref('')
const statusFilter = ref('')
const showForm = ref(false)
const editingMethod = ref(null)
const showDeactivateConfirm = ref(false)
const showActivateConfirm = ref(false)
const showDeleteConfirm = ref(false)
const methodToToggle = ref(null)
const methodToDelete = ref(null)
const toggling = ref(false)
const deleting = ref(false)

let searchTimeout = null

const load = () =>
  getMethods({
    q: searchQuery.value || undefined,
    is_active: statusFilter.value === '' ? undefined : statusFilter.value
  })

const onSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 350)
}

const onFilter = load

const openCreate = () => {
  editingMethod.value = null
  showForm.value = true
}

const openEdit = method => {
  editingMethod.value = { ...method }
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingMethod.value = null
}

const onSaved = method => {
  closeForm()
  toast.success(
    editingMethod.value
      ? `Metodo "${method.name}" actualizado`
      : `Metodo "${method.name}" creado`
  )
  load()
}

const confirmDeactivate = method => {
  methodToToggle.value = method
  showDeactivateConfirm.value = true
}

const confirmActivate = method => {
  methodToToggle.value = method
  showActivateConfirm.value = true
}

const confirmDelete = method => {
  methodToDelete.value = method
  showDeleteConfirm.value = true
}

const doDeactivate = async () => {
  if (!methodToToggle.value) return
  toggling.value = true
  try {
    await toggleActive(methodToToggle.value)
    toast.success(`Metodo "${methodToToggle.value.name}" desactivado`)
    showDeactivateConfirm.value = false
    methodToToggle.value = null
  } catch (err) {
    toast.error('No se pudo desactivar')
  } finally {
    toggling.value = false
  }
}

const doActivate = async () => {
  if (!methodToToggle.value) return
  toggling.value = true
  try {
    await toggleActive(methodToToggle.value)
    toast.success(`Metodo "${methodToToggle.value.name}" activado`)
    showActivateConfirm.value = false
    methodToToggle.value = null
  } catch (err) {
    toast.error('No se pudo activar')
  } finally {
    toggling.value = false
  }
}

const doDelete = async () => {
  if (!methodToDelete.value) return
  deleting.value = true
  try {
    await deleteMethod(methodToDelete.value.id)
    toast.success(`Metodo "${methodToDelete.value.name}" eliminado`)
    showDeleteConfirm.value = false
    methodToDelete.value = null
  } catch (err) {
    toast.error(err.response?.data?.message || 'No se pudo eliminar el metodo')
  } finally {
    deleting.value = false
  }
}

const goBack = () => router.push('/dashboard')

watch(error, newError => {
  if (newError) {
    toast.error(newError)
    clearError()
  }
})

onMounted(load)
</script>
