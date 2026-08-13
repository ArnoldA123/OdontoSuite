<template>
  <div class="modal-overlay" @click="handleClose">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <div>
          <h2 class="modal-title">
            {{ isEdit ? 'Editar Plan' : 'Nuevo Plan' }}
            <span v-if="isEdit && form.plan_number" class="modal-plan-number">
              {{ form.plan_number }}
            </span>
          </h2>
          <p class="modal-subtitle">
            {{ isEdit ? 'Modifica los datos del plan' : 'Crea un plan de tratamiento paso a paso' }}
          </p>
        </div>
        <button class="modal-close" aria-label="Cerrar" @click="handleClose">
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <form class="modal-body" @submit.prevent="handleSubmit">
        <!-- Paciente y título (lo más importante) -->
        <div class="form-section">
          <div class="form-group">
            <PatientSelector
              v-model="selectedPatient"
              :error="errors.patient_id"
              @patient-selected="handlePatientChange"
              @create-patient="openCreatePatient"
            />
          </div>

          <div class="form-group">
            <label class="form-label">
              Título del plan
              <span class="req">*</span>
            </label>
            <input
              v-model="form.title"
              type="text"
              class="form-input"
              ref="titleInput"
              :class="{ 'has-error': errors.title }"
              placeholder="Ej: Rehabilitación completa superior"
            />
            <p v-if="errors.title" class="form-error">
              {{ errors.title }}
            </p>
          </div>

          <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea
              v-model="form.description"
              class="form-textarea"
              rows="2"
              placeholder="Descripción general del plan (opcional)..."
            />
          </div>
        </div>

        <!-- Configuración -->
        <details class="form-section details">
          <summary class="section-header cursor-pointer">
            <h3 class="section-title">Configuración y fechas</h3>
            <ChevronDownIcon class="w-4 h-4 chev" />
          </summary>

          <div class="details-body">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Duración estimada (semanas)</label>
                <input
                  v-model.number="form.estimated_duration_weeks"
                  type="number"
                  class="form-input"
                  min="1"
                  max="104"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Fecha de inicio</label>
                <input v-model="form.start_date" type="date" class="form-input">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Fecha de fin</label>
                <input v-model="form.end_date" type="date" class="form-input">
              </div>
              <div class="form-group">
                <label class="form-label">N° de fases</label>
                <input
                  v-model.number="form.phases_count"
                  type="number"
                  class="form-input"
                  min="1"
                  max="10"
                />
              </div>
            </div>

            <div class="form-row">
              <label class="check-pill">
                <input v-model="form.requires_anesthesia" type="checkbox">
                <span>Requiere anestesia</span>
              </label>
              <label class="check-pill">
                <input v-model="form.is_urgent" type="checkbox">
                <span class="urgent">Urgente</span>
              </label>
            </div>
          </div>
        </details>

        <!-- Procedimientos -->
        <details class="form-section details" open>
          <summary class="section-header cursor-pointer">
            <h3 class="section-title">
              Procedimientos
              <span class="badge-count">{{ form.items.length }}</span>
            </h3>
            <ChevronDownIcon class="w-4 h-4 chev" />
          </summary>

          <div class="details-body">
            <div class="procedures-list">
              <div v-for="(item, index) in form.items" :key="index" class="procedure-item">
                <div class="procedure-index">
                  {{ index + 1 }}
                </div>
                <div class="procedure-content">
                  <input
                    v-model="item.procedure_name"
                    type="text"
                    class="form-input"
                    :class="{ 'has-error': errors[`items.${index}.procedure_name`] }"
                    placeholder="Nombre del procedimiento (ej: Limpieza, Corona, Endodoncia)"
                  />
                  <p v-if="errors[`items.${index}.procedure_name`]" class="form-error">
                    {{ errors[`items.${index}.procedure_name`] }}
                  </p>
                  <input
                    v-model="item.description"
                    type="text"
                    class="form-input form-input-sm"
                    placeholder="Descripción breve (opcional)"
                  />
                  <div class="procedure-details">
                    <input
                      v-model.number="item.quantity"
                      type="number"
                      class="form-input"
                      placeholder="Cant."
                      min="0.01"
                      step="0.01"
                    />
                    <div class="input-prefix">
                      <span>S/</span>
                      <input
                        v-model.number="item.unit_cost"
                        type="number"
                        class="form-input"
                        :class="{ 'has-error': errors[`items.${index}.unit_cost`] }"
                        placeholder="Precio"
                        min="0"
                        step="0.01"
                      />
                    </div>
                    <input
                      v-model.number="item.phase_number"
                      type="number"
                      class="form-input"
                      placeholder="Fase"
                      min="1"
                    />
                  </div>
                  <p v-if="errors[`items.${index}.unit_cost`]" class="form-error">
                    {{ errors[`items.${index}.unit_cost`] }}
                  </p>
                </div>
                <div class="procedure-side">
                  <div class="procedure-total">
                    S/ {{ formatPrice(item.quantity * item.unit_cost) }}
                  </div>
                  <button
                    type="button"
                    class="procedure-remove"
                    :disabled="form.items.length === 1"
                    @click="removeItem(index)"
                    title="Quitar"
                    aria-label="Quitar procedimiento"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <button type="button" class="btn btn-outline btn-sm" @click="addItem">
              <PlusIcon class="w-4 h-4 mr-1" />
              Agregar procedimiento
            </button>
          </div>
        </details>

        <!-- Notas -->
        <details class="form-section details">
          <summary class="section-header cursor-pointer">
            <h3 class="section-title">Notas</h3>
            <ChevronDownIcon class="w-4 h-4 chev" />
          </summary>

          <div class="details-body">
            <div class="form-group">
              <label class="form-label">Notas internas (equipo)</label>
              <textarea
                v-model="form.notes"
                class="form-textarea"
                rows="2"
                placeholder="Notas para el equipo médico..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Notas para el paciente</label>
              <textarea
                v-model="form.patient_notes"
                class="form-textarea"
                rows="2"
                placeholder="Información que verá el paciente..."
              />
            </div>
          </div>
        </details>

        <!-- Mostrar errores del backend que no son de campo -->
        <div v-if="errors._general" class="general-error">
          <ExclamationCircleIcon class="w-5 h-5" />
          <span>{{ errors._general }}</span>
        </div>
      </form>

      <!-- Footer sticky SIEMPRE visible con totales en vivo -->
      <div class="modal-footer-sticky">
        <div class="cost-summary">
          <div class="cost-row">
            <span>Items</span>
            <span>{{ form.items.length }}</span>
          </div>
          <div class="cost-row">
            <span>Subtotal</span>
            <span>S/ {{ formatPrice(subtotal) }}</span>
          </div>
          <div class="cost-row total">
            <span>Total</span>
            <span>S/ {{ formatPrice(finalCost) }}</span>
          </div>
        </div>
        <div class="footer-actions">
          <button type="button" class="btn btn-outline" @click="handleClose" :disabled="loading">
            Cancelar
          </button>
          <button type="submit" class="btn btn-primary" @click="handleSubmit" :disabled="loading">
            <span v-if="loading" class="spinner" />
            {{ isEdit ? 'Actualizar' : 'Crear' }} plan
          </button>
        </div>
      </div>
    </div>

    <!-- Modal/drawer para crear paciente inline -->
    <CreatePatientInline
      v-if="showCreatePatient"
      @close="showCreatePatient = false"
      @created="onPatientCreated"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useTreatmentPlans } from '@/composables/useTreatmentPlans'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import CreatePatientInline from './CreatePatientInline.vue'
import {
  XMarkIcon,
  PlusIcon,
  TrashIcon,
  ChevronDownIcon,
  ExclamationCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  plan: { type: Object, default: null },
  isEdit: { type: Boolean, default: false }
})

const emit = defineEmits(['close', 'saved'])

const { createPlan, updatePlan, loading } = useTreatmentPlans()
const toast = useToast()

const selectedPatient = ref(null)
const titleInput = ref(null)
const isDirty = ref(false)
const showCreatePatient = ref(false)

const form = ref({
  plan_number: '',
  patient_id: '',
  title: '',
  description: '',
  estimated_duration_weeks: null,
  start_date: '',
  end_date: '',
  phases_count: 1,
  requires_anesthesia: false,
  is_urgent: false,
  notes: '',
  patient_notes: '',
  items: []
})

const errors = ref({})

const subtotal = computed(() =>
  form.value.items.reduce(
    (acc, item) => acc + (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0),
    0
  )
)
const finalCost = computed(() => subtotal.value)

const handlePatientChange = patient => {
  selectedPatient.value = patient
  form.value.patient_id = patient?.id || ''
  isDirty.value = true
}

const openCreatePatient = () => {
  showCreatePatient.value = true
}

const onPatientCreated = newPatient => {
  showCreatePatient.value = false
  selectedPatient.value = newPatient
  form.value.patient_id = newPatient.id
  toast.success('Paciente creado y seleccionado')
}

const addItem = () => {
  form.value.items.push({
    procedure_name: '',
    description: '',
    quantity: 1,
    unit_cost: 0,
    phase_number: form.value.items.length + 1
  })
  isDirty.value = true
}

const removeItem = index => {
  if (form.value.items.length === 1) return
  form.value.items.splice(index, 1)
  isDirty.value = true
}

const handleSubmit = async () => {
  errors.value = {}

  // Validaciones locales
  if (!form.value.patient_id) {
    errors.value.patient_id = 'Selecciona un paciente'
  }
  if (!form.value.title?.trim()) {
    errors.value.title = 'El título es obligatorio'
  }
  if (form.value.items.length === 0) {
    errors.value._general = 'Agrega al menos un procedimiento'
  }
  if (Object.keys(errors.value).length > 0) return

  // Limpiar items vacíos
  const cleanedItems = form.value.items
    .filter(i => (i.procedure_name || '').trim() !== '' || (i.description || '').trim() !== '')
    .map(item => ({
      procedure_name: (item.procedure_name || '').trim() || (item.description || '').trim(),
      description: (item.description || '').trim() || null,
      quantity: Number(item.quantity) || 1,
      unit_cost: Number(item.unit_cost) || 0,
      phase_number: Number(item.phase_number) || 1,
      specialty: item.specialty || null,
      procedure_catalog_id: item.procedure_catalog_id || null,
      dental_piece_id: item.dental_piece_id || null,
      category: item.category || null
    }))

  if (cleanedItems.length === 0) {
    errors.value._general = 'Agrega al menos un procedimiento con nombre'
    return
  }

  const data = {
    patient_id: Number(form.value.patient_id),
    title: form.value.title.trim(),
    description: form.value.description?.trim() || null,
    estimated_duration_weeks: form.value.estimated_duration_weeks || null,
    start_date: form.value.start_date || null,
    end_date: form.value.end_date || null,
    phases: form.value.phases_count
      ? Array.from({ length: Number(form.value.phases_count) }, (_, i) => `Fase ${i + 1}`)
      : null,
    requires_anesthesia: !!form.value.requires_anesthesia,
    is_urgent: !!form.value.is_urgent,
    notes: form.value.notes?.trim() || null,
    patient_notes: form.value.patient_notes?.trim() || null,
    items: cleanedItems
  }

  try {
    if (props.isEdit) {
      await updatePlan(props.plan.id, data)
    } else {
      await createPlan(data)
    }
    isDirty.value = false
    emit('saved')
  } catch (err) {
    handleSaveError(err)
  }
}

const handleSaveError = err => {
  const data = err.response?.data

  if (data?.errors && typeof data.errors === 'object') {
    // Errores por campo del backend
    Object.assign(errors.value, data.errors)
    const firstField = Object.keys(data.errors)[0]
    const firstMsg = data.errors[firstField]?.[0]
    errors.value._general = firstMsg ? `${firstField}: ${firstMsg}` : 'Revisa los campos marcados'
  } else if (data?.message) {
    errors.value._general = data.message
  } else if (err.status === 422) {
    errors.value._general = 'Datos inválidos. Revisa los campos.'
  } else if (err.status === 500) {
    errors.value._general = 'Error del servidor. Intenta de nuevo o contacta soporte.'
  } else {
    errors.value._general = 'Error desconocido al guardar el plan'
  }
}

const handleClose = async () => {
  if (isDirty.value) {
    const ok = await confirm({
      title: 'Cambios sin guardar',
      message: 'Tienes cambios sin guardar. ¿Cerrar de todos modos?',
      confirmText: 'Cerrar sin guardar',
      variant: 'danger'
    })
    if (!ok) return
  }
  emit('close')
}

const formatPrice = price =>
  new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price || 0)

const emptyItem = () => ({
  procedure_name: '',
  description: '',
  quantity: 1,
  unit_cost: 0,
  phase_number: 1
})

const initializeForm = () => {
  if (props.plan) {
    const items =
      props.plan.items?.length > 0
        ? props.plan.items.map((i) => ({
          procedure_name: i.procedure_name || '',
          description: i.procedure_description || '',
            quantity: i.quantity || 1,
          unit_cost: i.unit_cost || 0,
          phase_number: i.phase_number || 1,
          specialty: i.specialty,
          procedure_catalog_id: i.procedure_catalog_id,
            dental_piece_id: i.dental_piece_id,
          category: i.category
          }))
        : [emptyItem()]

    form.value = {
      plan_number: props.plan.plan_number || '',
      patient_id: props.plan.patient_id || '',
      title: props.plan.title || '',
      description: props.plan.description || '',
      estimated_duration_weeks: props.plan.estimated_duration_weeks || null,
      start_date: props.plan.start_date || '',
      end_date: props.plan.end_date || '',
      phases_count: Array.isArray(props.plan.phases) ? props.plan.phases.length : 1,
      requires_anesthesia: !!props.plan.requires_anesthesia,
      is_urgent: !!props.plan.is_urgent,
      notes: props.plan.notes || '',
      patient_notes: props.plan.patient_notes || '',
      items
    }

    if (props.plan.patient) {
      selectedPatient.value = {
        id: props.plan.patient.id,
        first_name: props.plan.patient.first_name,
        last_name: props.plan.patient.last_name,
        dni: props.plan.patient.document_number,
        phone: props.plan.patient.phone,
        email: props.plan.patient.email,
        age: null
      }
    }
  } else {
    form.value = {
      plan_number: '',
      patient_id: '',
      title: '',
      description: '',
      estimated_duration_weeks: null,
      start_date: '',
      end_date: '',
      phases_count: 1,
      requires_anesthesia: false,
      is_urgent: false,
      notes: '',
      patient_notes: '',
      items: [emptyItem()]
    }
    selectedPatient.value = null
  }
  isDirty.value = false
  errors.value = {}
}

watch(
  () => form.value,
  () => {
    isDirty.value = true
  },
  { deep: true }
)

watch(
  () => props.plan,
  () => initializeForm(),
  { immediate: true }
)

onMounted(async () => {
  initializeForm()
  await nextTick()
  titleInput.value?.focus()
})
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 z-50 flex items-center justify-center p-4;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  @apply rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col;
  background-color: var(--color-surface-elevated);
}

.modal-header {
  @apply flex justify-between items-start p-5 border-b border-theme;
}

.modal-title {
  @apply text-xl font-semibold flex items-center gap-2;
  color: var(--color-text-primary);
}

.modal-plan-number {
  @apply text-xs px-2 py-0.5 rounded font-mono;
  background-color: var(--color-surface);
  color: var(--color-text-secondary);
}

.modal-subtitle {
  @apply text-sm mt-0.5;
  color: var(--color-text-secondary);
}

.modal-close {
  @apply p-1 rounded transition-colors;
  color: var(--color-text-secondary);
}
.modal-close:hover {
  color: var(--color-text-primary);
}

.modal-body {
  @apply flex-1 overflow-y-auto p-5 space-y-4;
}

.form-section {
  @apply space-y-3;
}

.section-header {
  @apply flex items-center justify-between;
}

.section-title {
  @apply text-sm font-semibold uppercase tracking-wider flex items-center gap-2;
  color: var(--color-text-secondary);
}

.badge-count {
  @apply text-xs px-1.5 py-0.5 rounded-full;
  background-color: rgb(219 234 254);
  color: rgb(29 78 216);
}

.chev {
  color: var(--color-text-secondary);
}

.details summary {
  list-style: none;
}
.details summary::-webkit-details-marker {
  display: none;
}

.details-body {
  @apply pt-3 space-y-3;
}

.form-row {
  @apply flex gap-3 flex-wrap;
}

.form-group {
  @apply space-y-1 flex-1;
}

.form-label {
  @apply block text-sm font-medium;
  color: var(--color-text-primary);
}

.req {
  color: rgb(239 68 68);
}

.form-input {
  @apply w-full px-3 py-2 text-sm border border-theme rounded-md focus:ring-2 focus:ring-primary-500 focus:border-transparent;
  background-color: var(--color-surface-elevated);
  color: var(--color-text-primary);
}

.form-input-sm {
  @apply text-xs;
}

.form-input.has-error {
  border-color: rgb(239 68 68);
}

.form-textarea {
  @apply w-full px-3 py-2 text-sm border border-theme rounded-md focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none;
  background-color: var(--color-surface-elevated);
  color: var(--color-text-primary);
}

.form-error {
  @apply text-xs;
  color: rgb(239 68 68);
}

.general-error {
  @apply flex items-center gap-2 p-3 rounded-md text-sm;
  background-color: rgb(254 226 226);
  color: rgb(185 28 28);
}

.check-pill {
  @apply inline-flex items-center gap-2 px-3 py-2 rounded-md border border-theme cursor-pointer text-sm;
}
.check-pill input {
  @apply rounded;
}
.check-pill .urgent {
  color: rgb(220 38 38);
  font-weight: 600;
}

.procedures-list {
  @apply space-y-2;
}

.procedure-item {
  @apply flex gap-2 p-3 rounded-lg border border-theme;
  background-color: var(--color-surface);
}

.procedure-index {
  @apply flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold shrink-0;
  background-color: rgb(219 234 254);
  color: rgb(29 78 216);
}

.procedure-content {
  @apply flex-1 space-y-2 min-w-0;
}

.procedure-details {
  @apply grid grid-cols-3 gap-2;
}

.input-prefix {
  @apply relative flex items-center;
}
.input-prefix span {
  @apply absolute left-2 text-xs;
  color: var(--color-text-secondary);
}
.input-prefix input {
  @apply pl-7;
}

.procedure-side {
  @apply flex flex-col items-end justify-between gap-2 shrink-0;
}

.procedure-total {
  @apply text-sm font-semibold;
  color: rgb(37 99 235);
}

.procedure-remove {
  @apply p-1 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed;
  color: rgb(220 38 38);
}
.procedure-remove:hover {
  background-color: rgb(254 226 226);
}

.modal-footer-sticky {
  @apply flex items-center justify-between gap-4 p-4 border-t border-theme;
  background-color: var(--color-surface-elevated);
}

.cost-summary {
  @apply text-sm space-y-0.5;
}

.cost-row {
  @apply flex justify-between gap-6;
  color: var(--color-text-secondary);
}

.cost-row.total {
  @apply text-lg font-bold pt-1 border-t border-theme;
  color: var(--color-text-primary);
}

.footer-actions {
  @apply flex gap-2 shrink-0;
}

.btn {
  @apply inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed;
}
.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}
.btn-outline {
  @apply border border-theme bg-theme-surface-elevated;
  color: var(--color-text-primary);
}
.btn-outline:hover {
  background-color: var(--color-surface);
}
.btn-sm {
  @apply px-2.5 py-1.5 text-xs;
}

.spinner {
  @apply inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
