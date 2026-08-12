<template>
  <AppLayout>
    <!-- Header Section -->
    <PageHeader
      title="Pacientes"
      subtitle="Gestiona la información de tus pacientes"
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
        <UiButton
          v-if="can.createPatient?.value"
          @click="showNewPatientModal = true"
        >
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
          </template>
          Nuevo Paciente
        </UiButton>
      </template>
    </PageHeader>

    <!-- Search and Filters -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            @input="searchPatients"
            placeholder="Buscar por nombre, DNI, teléfono o email..."
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
          <UiSelect
            v-model="statusFilter"
            :options="statusFilterOptions"
            size="sm"
            class="min-w-[140px]"
            @update:model-value="loadPatients(1)"
          />
          <UiButton
            variant="secondary"
            @click="resetFilters"
            class="px-3"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </UiButton>
        </div>
      </div>
    </UiCard>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <UiCard variant="glass" clickable>
        <div class="text-center">
          <div class="w-12 h-12 bg-gradient-accent rounded-xl mx-auto mb-3 flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
            </svg>
          </div>
          <div class="text-2xl font-bold text-theme-primary mb-1" style="font-feature-settings: var(--font-features-tabular-nums)">{{ totalPatients }}</div>
          <div class="text-sm text-theme-secondary">Total Pacientes</div>
        </div>
      </UiCard>

      <UiCard variant="glass" clickable>
        <div class="text-center">
          <div class="w-12 h-12 bg-gradient-to-br from-success-500 to-success-600 rounded-xl mx-auto mb-3 flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="text-2xl font-bold text-theme-primary mb-1" style="font-feature-settings: var(--font-features-tabular-nums)">{{ activePatients }}</div>
          <div class="text-sm text-theme-secondary">Activos</div>
        </div>
      </UiCard>

      <UiCard variant="glass" clickable>
        <div class="text-center">
          <div class="w-12 h-12 bg-gradient-to-br from-warning-500 to-warning-600 rounded-xl mx-auto mb-3 flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          <div class="text-2xl font-bold text-theme-primary mb-1" style="font-feature-settings: var(--font-features-tabular-nums)">{{ inactivePatients }}</div>
          <div class="text-sm text-theme-secondary">Inactivos</div>
        </div>
      </UiCard>

      <UiCard variant="glass" clickable>
        <div class="text-center">
          <div class="w-12 h-12 bg-gradient-accent rounded-xl mx-auto mb-3 flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="text-2xl font-bold text-theme-primary mb-1" style="font-feature-settings: var(--font-features-tabular-nums)">{{ filteredPatients.length }}</div>
          <div class="text-sm text-theme-secondary">Filtrados</div>
        </div>
      </UiCard>
    </div>

    <!-- Patients List -->
    <UiCard variant="glass" class="overflow-hidden">
      <!-- Loading State -->
      <LoadingSpinner v-if="loading" class="p-12" size="lg" text="Cargando pacientes..." />

      <!-- Empty State -->
      <EmptyState
        v-else-if="filteredPatients.length === 0"
        class="p-12"
        title="No se encontraron pacientes"
        description="Intenta ajustar los filtros de búsqueda"
        action-label="Limpiar filtros"
        @action="resetFilters"
      />

      <!-- Desktop Table View -->
      <div v-else class="hidden lg:block overflow-x-auto">
        <table class="min-w-full divide-y divide-[color:var(--color-hairline)]">
          <thead class="bg-theme-surface">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Paciente
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Contacto
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Fecha de Nacimiento
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Edad
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Estado
              </th>
              <th class="px-6 py-4 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-[color:var(--color-hairline)]">
            <tr v-for="patient in filteredPatients" :key="patient.id" class="hover:bg-theme-surface transition-colors">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-12 w-12">
                    <div class="h-12 w-12 rounded-xl bg-gradient-accent flex items-center justify-center">
                      <span class="text-sm font-semibold text-white">
                        {{ patient.first_name.charAt(0) }}{{ patient.last_name.charAt(0) }}
                      </span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-semibold text-theme-primary">
                      {{ patient.first_name }} {{ patient.last_name }}
                    </div>
                    <div class="text-sm text-theme-secondary" style="font-feature-settings: var(--font-features-tabular-nums)">
                      ID: {{ patient.id }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-theme-primary">{{ patient.phone }}</div>
                <div class="text-sm text-theme-secondary">{{ patient.email }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ formatDate(patient.birth_date) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary" style="font-feature-settings: var(--font-features-tabular-nums)">
                {{ patient.age != null ? `${patient.age} años` : '—' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="patient.is_active ? 'bg-systemGreen-50 text-systemGreen-700' : 'bg-systemRed-50 text-systemRed-700'"
                  class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full"
                >
                  {{ patient.is_active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    @click="viewPatient(patient)"
                    class="text-systemBlue-600 hover:text-systemBlue-700"
                  >
                    Ver
                  </UiButton>
                  <UiButton
                    v-if="can.updatePatient?.value"
                    variant="ghost"
                    size="sm"
                    @click="editPatient(patient)"
                    class="text-systemGreen-700 hover:opacity-80"
                  >
                    Editar
                  </UiButton>
                  <UiButton
                    v-if="can.deletePatient?.value"
                    variant="ghost"
                    size="sm"
                    @click="deletePatient(patient)"
                    class="text-systemRed-700 hover:opacity-80"
                  >
                    Eliminar
                  </UiButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card View -->
      <div v-if="!loading && filteredPatients.length > 0" class="lg:hidden space-y-4 p-4">
        <div
          v-for="patient in filteredPatients"
          :key="patient.id"
          class="bg-theme-surface-elevated rounded-2xl border border-hairline p-4 hover:shadow-lg transition-all duration-200"
        >
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center">
              <div class="h-12 w-12 rounded-xl bg-gradient-accent flex items-center justify-center mr-3">
                <span class="text-sm font-semibold text-white">
                  {{ patient.first_name.charAt(0) }}{{ patient.last_name.charAt(0) }}
                </span>
              </div>
              <div>
                <div class="text-lg font-semibold text-theme-primary">
                  {{ patient.first_name }} {{ patient.last_name }}
                </div>
                <div class="text-sm text-theme-secondary" style="font-feature-settings: var(--font-features-tabular-nums)">
                  ID: {{ patient.id }}
                </div>
              </div>
            </div>
            <span
              :class="patient.is_active ? 'bg-systemGreen-50 text-systemGreen-700' : 'bg-systemRed-50 text-systemRed-700'"
              class="px-3 py-1 text-xs font-semibold rounded-full"
            >
              {{ patient.is_active ? 'Activo' : 'Inactivo' }}
            </span>
          </div>

          <div class="space-y-2 mb-4">
            <div class="flex items-center text-sm text-theme-secondary">
              <svg class="w-4 h-4 mr-2 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              {{ patient.phone }}
            </div>
            <div class="flex items-center text-sm text-theme-secondary">
              <svg class="w-4 h-4 mr-2 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              {{ patient.email }}
            </div>
            <div class="flex items-center text-sm text-theme-secondary">
              <svg class="w-4 h-4 mr-2 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              {{ formatDate(patient.birth_date) }}
            </div>
            <div class="flex items-center text-sm text-theme-secondary">
              <svg class="w-4 h-4 mr-2 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="font-medium text-theme-primary mr-1">Edad:</span>
              <span style="font-feature-settings: var(--font-features-tabular-nums)">{{ patient.age != null ? `${patient.age} años` : '—' }}</span>
            </div>
          </div>

          <div class="flex gap-2">
            <UiButton
              variant="ghost"
              size="sm"
              @click="viewPatient(patient)"
              class="flex-1 text-systemBlue-600 hover:text-systemBlue-700"
            >
              Ver
            </UiButton>
            <UiButton
              v-if="can.updatePatient?.value"
              variant="ghost"
              size="sm"
              @click="editPatient(patient)"
              class="flex-1 text-systemGreen-700 hover:opacity-80"
            >
              Editar
            </UiButton>
            <UiButton
              v-if="can.deletePatient?.value"
              variant="ghost"
              size="sm"
              @click="deletePatient(patient)"
              class="flex-1 text-systemRed-700 hover:opacity-80"
            >
              Eliminar
            </UiButton>
          </div>
        </div>
      </div>

      <!-- Pagination Controls -->
      <div v-if="!loading && paginationMeta.last_page > 1" class="p-4 border-t border-hairline">
        <Pagination
          :current-page="paginationMeta.current_page"
          :total-pages="paginationMeta.last_page"
          :total="paginationMeta.total"
          :per-page="paginationMeta.per_page"
          @page-change="handlePageChange"
        />
      </div>
    </UiCard>

    <!-- New Patient Modal -->
    <div
      v-if="showNewPatientModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="showNewPatientModal = false"
    >
      <div class="bg-theme-surface-elevated rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-theme">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-theme-primary">Nuevo Paciente</h2>
            <button
              @click="showNewPatientModal = false"
              class="text-theme-secondary hover:text-theme-primary transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="createPatient" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="newPatient.first_name"
                label="Nombre"
                placeholder="Ingresa el nombre"
                required
              />
              <UiInput
                v-model="newPatient.last_name"
                label="Apellido"
                placeholder="Ingresa el apellido"
                required
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="newPatient.document_number"
                label="DNI"
                placeholder="Ingresa el DNI"
              />
              <UiInput
                v-model="newPatient.email"
                label="Email"
                type="email"
                placeholder="correo@ejemplo.com"
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="newPatient.phone"
                label="Teléfono"
                placeholder="+51 999 999 999"
              />
              <UiInput
                v-model="newPatient.birth_date"
                label="Fecha de Nacimiento"
                type="date"
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-2">
                  Género
                </label>
                <UiSelect
                  v-model="newPatient.gender"
                  :options="genderOptions"
                  placeholder="Seleccionar"
                  class="w-full"
                />
              </div>
            </div>
            <UiInput
              v-model="newPatient.address"
              label="Dirección"
              placeholder="Ingresa la dirección"
            />
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="newPatient.emergency_contact_name"
                label="Contacto de Emergencia"
                placeholder="Nombre del contacto"
              />
              <UiInput
                v-model="newPatient.emergency_contact_phone"
                label="Teléfono de Emergencia"
                placeholder="+51 999 999 999"
              />
            </div>
            <UiInput
              v-model="newPatient.medical_history"
              label="Historial Médico"
              placeholder="Alergias, condiciones médicas, etc."
              type="textarea"
            />
            <UiInput
              v-model="newPatient.allergies"
              label="Alergias"
              placeholder="Lista de alergias conocidas"
              type="textarea"
            />
            <UiInput
              v-model="newPatient.notes"
              label="Notas"
              placeholder="Notas adicionales"
              type="textarea"
            />
            <div class="flex justify-end gap-3 pt-4">
              <UiButton
                type="button"
                variant="secondary"
                @click="showNewPatientModal = false"
              >
                Cancelar
              </UiButton>
              <UiButton
                type="submit"
                :loading="creating"
              >
                Crear Paciente
              </UiButton>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Patient Modal -->
    <div
      v-if="showEditPatientModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="showEditPatientModal = false"
    >
      <div class="bg-theme-surface-elevated rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-theme">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-theme-primary">Editar Paciente</h2>
            <button
              @click="showEditPatientModal = false; resetEditPatient()"
              class="text-theme-secondary hover:text-theme-primary transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="updatePatient" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="editPatientData.first_name"
                label="Nombre"
                placeholder="Ingresa el nombre"
                required
              />
              <UiInput
                v-model="editPatientData.last_name"
                label="Apellido"
                placeholder="Ingresa el apellido"
                required
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="editPatientData.document_number"
                label="DNI"
                placeholder="Ingresa el DNI"
              />
              <UiInput
                v-model="editPatientData.email"
                label="Email"
                type="email"
                placeholder="correo@ejemplo.com"
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="editPatientData.phone"
                label="Teléfono"
                placeholder="+51 999 999 999"
              />
              <UiInput
                v-model="editPatientData.birth_date"
                label="Fecha de Nacimiento"
                type="date"
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-2">
                  Género
                </label>
                <UiSelect
                  v-model="editPatientData.gender"
                  :options="genderOptions"
                  placeholder="Seleccionar"
                  class="w-full"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-2">
                  Estado
                </label>
                <UiSelect
                  v-model="editPatientData.is_active"
                  :options="statusOptions"
                  placeholder="Seleccionar"
                  class="w-full"
                />
              </div>
            </div>
            <UiInput
              v-model="editPatientData.address"
              label="Dirección"
              placeholder="Ingresa la dirección"
            />
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="editPatientData.emergency_contact_name"
                label="Contacto de Emergencia"
                placeholder="Nombre del contacto"
              />
              <UiInput
                v-model="editPatientData.emergency_contact_phone"
                label="Teléfono de Emergencia"
                placeholder="+51 999 999 999"
              />
            </div>
            <UiInput
              v-model="editPatientData.medical_history"
              label="Historial Médico"
              placeholder="Alergias, condiciones médicas, etc."
              type="textarea"
            />
            <UiInput
              v-model="editPatientData.allergies"
              label="Alergias"
              placeholder="Lista de alergias conocidas"
              type="textarea"
            />
            <UiInput
              v-model="editPatientData.notes"
              label="Notas"
              placeholder="Notas adicionales"
              type="textarea"
            />
            <div class="flex justify-end gap-3 pt-4">
              <UiButton
                type="button"
                variant="secondary"
                @click="showEditPatientModal = false; resetEditPatient()"
              >
                Cancelar
              </UiButton>
              <UiButton
                type="submit"
                :loading="updating"
              >
                Actualizar Paciente
              </UiButton>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { useEcho } from '../../composables/useEcho'
import { useConfirm } from '../../composables/useConfirm'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import Pagination from '../../components/ui/Pagination.vue'

export default {
  name: 'PatientsPage',
  components: {
    AppLayout,
    UiCard,
    UiButton,
    UiInput,
    UiSelect,
    Pagination
  },
  setup() {
    const router = useRouter()
    const { get, post, put, delete: del } = useApi()
    const { can } = usePermissions()
    const toast = useToast()
    const { channel, echo } = useEcho()

    // can ya es un objeto con propiedades computed, usarlo directamente

    const loading = ref(false)
    const creating = ref(false)
    const updating = ref(false)
    const patients = ref([])
    const filteredPatients = ref([])
    const paginationMeta = ref({
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0
    })
    const searchQuery = ref('')
    const statusFilter = ref('')
    const statusFilterOptions = [
      { value: '', label: 'Todos' },
      { value: 'active', label: 'Activos' },
      { value: 'inactive', label: 'Inactivos' }
    ]
    const genderOptions = [
      { value: '', label: 'Seleccionar' },
      { value: 'male', label: 'Masculino' },
      { value: 'female', label: 'Femenino' },
      { value: 'other', label: 'Otro' }
    ]
    const statusOptions = [
      { value: true, label: 'Activo' },
      { value: false, label: 'Inactivo' }
    ]
    const showNewPatientModal = ref(false)
    const showEditPatientModal = ref(false)
    const editingPatientId = ref(null)

    const newPatient = ref({
      first_name: '',
      last_name: '',
      document_number: '',
      email: '',
      phone: '',
      birth_date: '',
      gender: '',
      address: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      medical_history: '',
      allergies: '',
      notes: ''
    })

    const editPatientData = ref({
      first_name: '',
      last_name: '',
      document_number: '',
      email: '',
      phone: '',
      birth_date: '',
      gender: '',
      address: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      medical_history: '',
      allergies: '',
      notes: '',
      is_active: true
    })

    // Almacenar totales de activos/inactivos desde el backend
    const activeCount = ref(0)
    const inactiveCount = ref(0)

    const totalPatients = computed(() => {
      // Si hay filtro aplicado, mostrar el total filtrado
      // Si no hay filtro, mostrar el total general (activos + inactivos)
      if (statusFilter.value === 'active') {
        return paginationMeta.value?.total || 0
      } else if (statusFilter.value === 'inactive') {
        return paginationMeta.value?.total || 0
      }
      // Sin filtro: mostrar total general
      return (activeCount.value || 0) + (inactiveCount.value || 0)
    })

    const activePatients = computed(() => {
      // Si estamos filtrando por activos, mostrar el total filtrado
      if (statusFilter.value === 'active') {
        return paginationMeta.value?.total || 0
      }
      // Si estamos filtrando por inactivos, mostrar 0
      if (statusFilter.value === 'inactive') {
        return 0
      }
      // Sin filtro: mostrar el total de activos desde el backend
      return activeCount.value || 0
    })

    const inactivePatients = computed(() => {
      // Si estamos filtrando por inactivos, mostrar el total filtrado
      if (statusFilter.value === 'inactive') {
        return paginationMeta.value?.total || 0
      }
      // Si estamos filtrando por activos, mostrar 0
      if (statusFilter.value === 'active') {
        return 0
      }
      // Sin filtro: mostrar el total de inactivos desde el backend
      return inactiveCount.value || 0
    })

    const loadPatients = async (page = 1) => {
      loading.value = true
      try {
        // Construir parámetros de query
        const params = new URLSearchParams()
        params.append('page', page.toString())

        // Solo agregar filtro de estado si el usuario lo ha seleccionado explícitamente
        if (statusFilter.value === 'active') {
          params.append('active', 'true')
        } else if (statusFilter.value === 'inactive') {
          params.append('active', 'false')
        }
        // Si statusFilter.value está vacío o es 'all', no se envía el parámetro (muestra todos)

        // Agregar búsqueda si existe
        if (searchQuery.value) {
          params.append('search', searchQuery.value)
        }

        const response = await get(`/api/patients?${params.toString()}`)
        patients.value = response?.data || []
        filteredPatients.value = response?.data || []

        // Guardar metadatos de paginación
        if (response?.meta) {
          paginationMeta.value = {
            current_page: response.meta.current_page || 1,
            last_page: response.meta.last_page || 1,
            per_page: response.meta.per_page || 15,
            total: response.meta.total || 0
          }

          // Guardar totales de activos e inactivos desde el backend
          if (response.meta.active_count !== undefined) {
            activeCount.value = response.meta.active_count
          }
          if (response.meta.inactive_count !== undefined) {
            inactiveCount.value = response.meta.inactive_count
          }
        }

        if (patients.value.length === 0 && paginationMeta.value.total === 0) {
          toast.warning('No se encontraron pacientes')
        }
      } catch (error) {
        toast.error('Error al cargar los pacientes. Por favor, recarga la página.')
        patients.value = []
        filteredPatients.value = []
        paginationMeta.value = {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 0
        }
      } finally {
        loading.value = false
      }
    }

    const searchPatients = () => {
      // Recargar desde el servidor con los nuevos filtros
      loadPatients(1)
    }

    const filterPatients = () => {
      // Ya no filtramos en el cliente, el servidor lo hace
      // Solo actualizamos filteredPatients con los datos del servidor
      filteredPatients.value = patients.value
    }

    const resetFilters = () => {
      searchQuery.value = ''
      statusFilter.value = ''
      // Recargar desde el servidor sin filtros
      loadPatients(1)
    }

    const handlePageChange = (page) => {
      loadPatients(page)
    }

    const createPatient = async () => {
      creating.value = true
      try {
        const response = await post('/api/patients', newPatient.value)
        const newPatientData = response?.data

        if (newPatientData) {
          // Verificar si el paciente cumple los filtros actuales
          let shouldAdd = true

          // Verificar filtro de estado
          if (statusFilter.value === 'active' && !newPatientData.is_active) {
            shouldAdd = false
          } else if (statusFilter.value === 'inactive' && newPatientData.is_active) {
            shouldAdd = false
          }

          // Verificar búsqueda
          if (shouldAdd && searchQuery.value) {
            const query = searchQuery.value.toLowerCase()
            const matchesSearch =
              (newPatientData.first_name || '').toLowerCase().includes(query) ||
              (newPatientData.last_name || '').toLowerCase().includes(query) ||
              (newPatientData.email || '').toLowerCase().includes(query) ||
              (newPatientData.phone || '').includes(query) ||
              (newPatientData.document_number || '').toLowerCase().includes(query)

            if (!matchesSearch) {
              shouldAdd = false
            }
          }

          if (shouldAdd) {
            // Agregar el paciente directamente a la lista si cumple los filtros
            // Como se ordena por apellido, insertarlo en la posición correcta
            const insertIndex = patients.value.findIndex(p => {
              const newLastName = (newPatientData.last_name || '').toLowerCase()
              const currentLastName = (p.last_name || '').toLowerCase()
              return currentLastName > newLastName ||
                     (currentLastName === newLastName && (p.first_name || '').toLowerCase() > (newPatientData.first_name || '').toLowerCase())
            })

            if (insertIndex === -1) {
              // Agregar al final si no se encuentra posición
              patients.value.push(newPatientData)
            } else {
              // Insertar en la posición correcta
              patients.value.splice(insertIndex, 0, newPatientData)
            }

            // Actualizar filteredPatients
            filteredPatients.value = patients.value
          }

          // Actualizar el total de pacientes (siempre, independientemente de si se muestra)
          if (paginationMeta.value.total !== undefined) {
            paginationMeta.value.total += 1
          }
        }

        showNewPatientModal.value = false
        resetNewPatient()

        // Notificación de éxito
        toast.success(
          `Paciente registrado: ${newPatient.value.first_name} ${newPatient.value.last_name}\n` +
          `Email: ${newPatient.value.email}\n` +
          `Teléfono: ${newPatient.value.phone}`,
          {
            duration: 5000,
            title: '✓ Paciente Creado'
          }
        )
      } catch (error) {
        // Notificación de error
        const errorMsg = error.response?.data?.message || 'Error al crear paciente'
        const errors = error.response?.data?.errors
        let details = ''
        if (errors) {
          details = '\n' + Object.values(errors).flat().join('\n')
        }
        toast.error(
          errorMsg + details,
          {
            duration: 8000,
            title: '✗ Error al Crear Paciente'
          }
        )
      } finally {
        creating.value = false
      }
    }

    const editPatient = (patient) => {
      editingPatientId.value = patient.id
      editPatientData.value = {
        first_name: patient.first_name || '',
        last_name: patient.last_name || '',
        document_number: patient.document_number || '',
        email: patient.email || '',
        phone: patient.phone || '',
        birth_date: patient.birth_date ? patient.birth_date.split('T')[0] : '',
        gender: patient.gender || '',
        address: patient.address || '',
        emergency_contact_name: patient.emergency_contact_name || '',
        emergency_contact_phone: patient.emergency_contact_phone || '',
        medical_history: patient.medical_history || '',
        allergies: patient.allergies || '',
        notes: patient.notes || '',
        is_active: patient.is_active !== undefined ? patient.is_active : true
      }
      showEditPatientModal.value = true
    }

    const updatePatient = async () => {
      if (!editingPatientId.value) return

      updating.value = true
      try {
        const response = await put(`/api/patients/${editingPatientId.value}`, editPatientData.value)
        await loadPatients()
        showEditPatientModal.value = false
        resetEditPatient()

        toast.success(
          `Paciente actualizado: ${editPatientData.value.first_name} ${editPatientData.value.last_name}`,
          {
            duration: 5000,
            title: '✓ Paciente Actualizado'
          }
        )
      } catch (error) {
        const errorMsg = error.response?.data?.message || 'Error al actualizar paciente'
        const errors = error.response?.data?.errors
        let details = ''
        if (errors) {
          details = '\n' + Object.values(errors).flat().join('\n')
        }
        toast.error(
          errorMsg + details,
          {
            duration: 8000,
            title: '✗ Error al Actualizar Paciente'
          }
        )
      } finally {
        updating.value = false
      }
    }

    const resetEditPatient = () => {
      editingPatientId.value = null
      editPatientData.value = {
        first_name: '',
        last_name: '',
        document_number: '',
        email: '',
        phone: '',
        birth_date: '',
        gender: '',
        address: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        medical_history: '',
        allergies: '',
        notes: '',
        is_active: true
      }
    }

    const viewPatient = (patient) => {
      router.push(`/patients/${patient.id}`)
    }

    const deletePatient = async (patient) => {
      const ok = await confirm({
        title: 'Eliminar paciente',
        message: `¿Estás seguro de que quieres eliminar a ${patient.first_name} ${patient.last_name}?`,
        confirmText: 'Eliminar',
        variant: 'danger',
      })
      if (ok) {
        try {
          await del(`/api/patients/${patient.id}`)

          // Recargar la página actual para asegurar que el paciente se elimine de la lista
          // y que la paginación se actualice correctamente desde el servidor
          await loadPatients(paginationMeta.value.current_page)

          // Notificación de éxito
          toast.success(
            `Paciente eliminado: ${patient.first_name} ${patient.last_name}`,
            {
              duration: 4000,
              title: '✓ Paciente Eliminado'
            }
          )
        } catch (error) {
          // Notificación de error
          const errorMessage = error.response?.data?.message
            || error.response?.data?.errors?.patient?.[0]
            || 'No se pudo eliminar el paciente. Puede tener citas asociadas.'
          toast.error(
            errorMessage,
            {
              duration: 8000,
              title: '✗ Error al Eliminar'
            }
          )
        }
      }
    }

    const resetNewPatient = () => {
      newPatient.value = {
        first_name: '',
        last_name: '',
        document_number: '',
        email: '',
        phone: '',
        birth_date: '',
        gender: '',
        address: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        medical_history: '',
        allergies: '',
        notes: ''
      }
    }

    const formatDate = (date) => {
      if (!date) return 'N/A'
      return new Date(date).toLocaleDateString('es-ES')
    }

    const goBack = () => {
      router.back()
    }

    // WebSocket subscriptions
    let patientsChannel = null

    onMounted(() => {
      loadPatients()

      // Suscribirse a canales WebSocket para actualizaciones en tiempo real
      try {
        patientsChannel = channel('patients')
        if (patientsChannel) {
          patientsChannel
            .listen('.patient.created', async (e) => {
              const newPatient = e.patient

              // Agregar el paciente directamente a la lista si cumple los filtros
              if (newPatient) {
                // Verificar si cumple los filtros actuales
                let shouldAdd = true

                if (statusFilter.value === 'active' && !newPatient.is_active) {
                  shouldAdd = false
                } else if (statusFilter.value === 'inactive' && newPatient.is_active) {
                  shouldAdd = false
                }

                if (searchQuery.value) {
                  const query = searchQuery.value.toLowerCase()
                  const matchesSearch =
                    (newPatient.first_name || '').toLowerCase().includes(query) ||
                    (newPatient.last_name || '').toLowerCase().includes(query) ||
                    (newPatient.email || '').toLowerCase().includes(query) ||
                    (newPatient.phone || '').includes(query) ||
                    (newPatient.document_number || '').toLowerCase().includes(query)

                  if (!matchesSearch) {
                    shouldAdd = false
                  }
                }

                if (shouldAdd) {
                  // Insertar en la posición correcta (ordenado por apellido)
                  const insertIndex = patients.value.findIndex(p => {
                    const newLastName = (newPatient.last_name || '').toLowerCase()
                    const currentLastName = (p.last_name || '').toLowerCase()
                    return currentLastName > newLastName ||
                           (currentLastName === newLastName && (p.first_name || '').toLowerCase() > (newPatient.first_name || '').toLowerCase())
                  })

                  if (insertIndex === -1) {
                    patients.value.push(newPatient)
                  } else {
                    patients.value.splice(insertIndex, 0, newPatient)
                  }

                  // Actualizar filteredPatients
                  filteredPatients.value = patients.value
                }

                // Actualizar el total
                if (paginationMeta.value.total !== undefined) {
                  paginationMeta.value.total += 1
                }
              }

              toast.success('Nuevo paciente agregado')
            })
            .listen('.patient.updated', async (e) => {
              // Actualizar el paciente en la lista si existe
              const index = patients.value.findIndex(p => p.id === e.patient.id)
              if (index !== -1) {
                patients.value[index] = e.patient
                filterPatients() // Re-aplicar filtros
              } else {
                await loadPatients()
              }
              toast.success('Paciente actualizado')
            })
            .listen('.patient.deleted', async (e) => {
              // Remover el paciente de la lista
              patients.value = patients.value.filter(p => p.id !== e.patient_id)
              filteredPatients.value = filteredPatients.value.filter(p => p.id !== e.patient_id)
              toast.success('Paciente eliminado')
            })
        }
      } catch (error) {
      }
    })

    onUnmounted(() => {
      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('patients')
        } catch (e) {
        }
      }
    })

    return {
      can,
      loading,
      creating,
      updating,
      patients,
      filteredPatients,
      searchQuery,
      statusFilter,
      showNewPatientModal,
      showEditPatientModal,
      newPatient,
      editPatientData,
      totalPatients,
      activePatients,
      inactivePatients,
      paginationMeta,
      loadPatients,
      handlePageChange,
      searchPatients,
      filterPatients,
      resetFilters,
      createPatient,
      editPatient,
      updatePatient,
      resetEditPatient,
      viewPatient,
      deletePatient,
      formatDate,
      goBack
    }
  }
}
</script>

