<template>
  <UiModal
    data-plan-modal
    model-value
    size="4xl"
    :labelledby="titleId"
    :before-close="confirmClose"
    @close="emit('close')"
  >
    <template #header>
      <div class="flex w-full items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
            Plan de tratamiento
          </p>
          <h2 :id="titleId" data-plan-title class="mt-1 text-2xl font-semibold text-label">
            {{ isEdit ? 'Editar Plan' : 'Nuevo Plan' }}
            <span
              v-if="isEdit && form.plan_number"
              class="ml-2 align-middle font-mono text-xs font-normal text-theme-secondary"
            >
              {{ form.plan_number }}
            </span>
          </h2>
          <p class="mt-1 text-sm text-theme-secondary">
            {{ isEdit ? 'Modifica los datos del plan' : 'Crea un plan de tratamiento paso a paso' }}
          </p>
        </div>

        <UiButton
          variant="icon"
          size="sm"
          aria-label="Cerrar plan"
          data-plan-close
          @click="requestClose"
        >
          <XMarkIcon class="h-5 w-5" aria-hidden="true" />
        </UiButton>
      </div>
    </template>

    <form id="treatment-plan-form" class="space-y-4" novalidate @submit.prevent="handleSubmit">
      <!-- Patient + core plan identity -->
      <UiCard variant="glass" padding="md">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Paciente y plan
        </h3>

        <div class="mt-4 space-y-4">
          <div data-plan-field="patient">
            <PatientSelector
              v-model="selectedPatient"
              @patient-selected="handlePatientChange"
              @create-patient="openCreatePatient"
            />
            <p
              v-if="errors.patient_id"
              role="alert"
              class="mt-2 text-sm font-medium text-systemRed-600"
              data-plan-error="patient_id"
            >
              {{ errors.patient_id }}
            </p>
          </div>

          <div ref="titleField" data-plan-field="title">
            <UiInput
              v-model="form.title"
              label="Título del plan"
              required
              placeholder="Ej: Rehabilitación completa superior"
              :error="errors.title"
            />
          </div>

          <div data-plan-field="description">
            <label class="mb-2 block text-sm font-medium text-theme-primary" for="plan-description">
              Descripción
            </label>
            <UiTextarea
              id="plan-description"
              v-model="form.description"
              :rows="2"
              placeholder="Descripción general del plan (opcional)..."
            />
          </div>
        </div>
      </UiCard>

      <!-- Scheduling + flags -->
      <UiCard variant="glass" padding="md">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Configuración y fechas
        </h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <UiInput
            v-model="form.estimated_duration_weeks"
            data-plan-field="estimated_duration_weeks"
            type="number"
            label="Duración estimada (semanas)"
            min="1"
            max="104"
          />
          <UiInput
            v-model="form.start_date"
            data-plan-field="start_date"
            type="date"
            label="Fecha de inicio"
          />
          <UiInput
            v-model="form.end_date"
            data-plan-field="end_date"
            type="date"
            label="Fecha de fin"
          />
          <UiInput
            v-model="form.phases_count"
            data-plan-field="phases_count"
            type="number"
            label="N° de fases"
            min="1"
            max="10"
          />
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label
            class="flex cursor-pointer items-center gap-3 rounded-[var(--radius-control)] px-3 py-2.5"
            style="
              border: 1px solid var(--color-hairline);
              background: var(--color-surface-elevated);
            "
          >
            <input
              v-model="form.requires_anesthesia"
              type="checkbox"
              class="h-4 w-4"
              style="accent-color: var(--color-accent)"
            />
            <span class="text-sm font-medium text-theme-primary">Requiere anestesia</span>
          </label>

          <label
            class="flex cursor-pointer items-center gap-3 rounded-[var(--radius-control)] px-3 py-2.5"
            style="
              border: 1px solid var(--color-hairline);
              background: var(--color-surface-elevated);
            "
          >
            <input
              v-model="form.is_urgent"
              type="checkbox"
              class="h-4 w-4"
              style="accent-color: var(--color-system-red-500)"
            />
            <span class="text-sm font-semibold text-systemRed-600">Urgente</span>
          </label>
        </div>
      </UiCard>

      <!-- Procedures editor -->
      <UiCard variant="glass" padding="md">
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
            Procedimientos
          </h3>
          <span
            class="inline-flex min-w-7 items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums text-theme-secondary"
            style="border: 1px solid var(--color-hairline)"
            data-plan-item-count
          >
            {{ form.items.length }}
          </span>
        </div>

        <div class="mt-4 space-y-3">
          <div
            v-for="(item, index) in form.items"
            :key="index"
            data-plan-item
            class="rounded-[var(--radius-control)] p-3"
            style="
              border: 1px solid var(--color-hairline);
              background: var(--color-surface-elevated);
            "
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex min-w-0 items-center gap-2">
                <span
                  class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums text-theme-secondary"
                  style="border: 1px solid var(--color-hairline)"
                >
                  {{ index + 1 }}
                </span>
                <span class="truncate text-sm font-medium text-theme-primary">
                  {{ item.procedure_name || 'Nuevo procedimiento' }}
                </span>
              </div>

              <div class="flex flex-shrink-0 items-center gap-3">
                <span
                  class="text-sm font-semibold tabular-nums text-label"
                  :data-plan-item-total="index"
                >
                  {{ formatCurrency(lineTotal(item)) }}
                </span>
                <UiButton
                  variant="icon"
                  size="xs"
                  aria-label="Quitar procedimiento"
                  :disabled="form.items.length === 1"
                  @click="removeItem(index)"
                >
                  <TrashIcon class="h-4 w-4" aria-hidden="true" />
                </UiButton>
              </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
              <div class="md:col-span-3" data-plan-item-field="name">
                <UiInput
                  v-model="item.procedure_name"
                  size="sm"
                  label="Procedimiento"
                  placeholder="Ej: Limpieza, Corona, Endodoncia"
                  :error="errors[`items.${index}.procedure_name`]"
                />
              </div>

              <div class="md:col-span-3" data-plan-item-field="description">
                <UiInput
                  v-model="item.description"
                  size="sm"
                  label="Descripción breve (opcional)"
                  placeholder="Detalle del procedimiento"
                />
              </div>

              <div data-plan-item-field="quantity">
                <UiInput
                  v-model="item.quantity"
                  size="sm"
                  type="number"
                  label="Cantidad"
                  min="0.01"
                  step="0.01"
                />
              </div>

              <div data-plan-item-field="unit_cost">
                <UiInput
                  v-model="item.unit_cost"
                  size="sm"
                  type="number"
                  label="Precio unitario"
                  min="0"
                  step="0.01"
                  :error="errors[`items.${index}.unit_cost`]"
                >
                  <template #prefix>
                    <span class="text-xs text-theme-secondary">S/</span>
                  </template>
                </UiInput>
              </div>

              <div data-plan-item-field="phase_number">
                <UiInput v-model="item.phase_number" size="sm" type="number" label="Fase" min="1" />
              </div>
            </div>
          </div>
        </div>

        <UiButton variant="secondary" size="sm" class="mt-4" @click="addItem">
          <template #icon-left>
            <PlusIcon class="h-4 w-4" aria-hidden="true" />
          </template>
          Agregar procedimiento
        </UiButton>
      </UiCard>

      <!-- Notes -->
      <UiCard variant="glass" padding="md">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">Notas</h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="mb-2 block text-sm font-medium text-theme-primary" for="plan-notes">
              Notas internas (equipo)
            </label>
            <UiTextarea
              id="plan-notes"
              v-model="form.notes"
              :rows="3"
              placeholder="Notas para el equipo médico..."
            />
          </div>

          <div>
            <label
              class="mb-2 block text-sm font-medium text-theme-primary"
              for="plan-patient-notes"
            >
              Notas para el paciente
            </label>
            <UiTextarea
              id="plan-patient-notes"
              v-model="form.patient_notes"
              :rows="3"
              placeholder="Información que verá el paciente..."
            />
          </div>
        </div>
      </UiCard>

      <!-- Backend errors without a field -->
      <div
        v-if="errors._general"
        role="alert"
        class="flex items-center gap-3 rounded-[var(--radius-panel)] bg-systemRed-50 p-4 text-sm text-systemRed-700"
        style="border: 1px solid var(--color-hairline)"
      >
        <ExclamationCircleIcon class="h-5 w-5 flex-shrink-0" aria-hidden="true" />
        <span>{{ errors._general }}</span>
      </div>
    </form>

    <!-- Pinned footer: live totals + actions -->
    <template #footer>
      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <dl class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm">
          <div class="flex items-baseline gap-2">
            <dt class="text-theme-secondary">Items</dt>
            <dd class="font-medium tabular-nums text-label">{{ form.items.length }}</dd>
          </div>
          <div class="flex items-baseline gap-2">
            <dt class="text-theme-secondary">Subtotal</dt>
            <dd class="font-medium tabular-nums text-label" data-plan-subtotal>
              {{ formatCurrency(subtotal) }}
            </dd>
          </div>
          <div class="flex items-baseline gap-2">
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Total</dt>
            <dd class="text-lg font-bold tabular-nums text-label" data-plan-total>
              {{ formatCurrency(finalCost) }}
            </dd>
          </div>
        </dl>

        <div class="flex items-center gap-2">
          <UiButton
            variant="secondary"
            type="button"
            :disabled="loading"
            data-plan-cancel
            @click="requestClose"
          >
            Cancelar
          </UiButton>
          <UiButton
            variant="primary"
            type="submit"
            form="treatment-plan-form"
            :loading="loading"
            data-plan-submit
          >
            {{ isEdit ? 'Actualizar' : 'Crear' }} plan
          </UiButton>
        </div>
      </div>
    </template>
  </UiModal>

  <CreatePatientInline
    v-if="showCreatePatient"
    @close="showCreatePatient = false"
    @created="onPatientCreated"
  />
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useTreatmentPlans } from '@/composables/useTreatmentPlans'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { formatCurrency } from '@/composables/useFormatters'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiInput from '@/components/ui/Input.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import CreatePatientInline from './CreatePatientInline.vue'
import { ExclamationCircleIcon, PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  plan: { type: Object, default: null },
  isEdit: { type: Boolean, default: false }
})

const emit = defineEmits(['close', 'saved'])

const { createPlan, updatePlan, loading } = useTreatmentPlans()
const { confirm } = useConfirm()
const toast = useToast()

const titleId = `treatment-plan-modal-title-${Math.random().toString(36).slice(2, 8)}`

const titleField = ref(null)
const selectedPatient = ref(null)
const showCreatePatient = ref(false)

/**
 * Dirty tracking compares the current form against the snapshot taken right
 * after initialization. A deep watcher would flag the modal dirty on mount
 * (the initialize pass itself mutates `form`), which turned the close guard
 * into a prompt even when the user touched nothing.
 */
const baseline = ref('')

const emptyItem = () => ({
  procedure_name: '',
  description: '',
  quantity: 1,
  unit_cost: 0,
  phase_number: 1
})

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

const snapshot = () => JSON.stringify(form.value)
const isDirty = computed(() => snapshot() !== baseline.value)

const lineTotal = item => (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0)

const subtotal = computed(() => form.value.items.reduce((acc, item) => acc + lineTotal(item), 0))
const finalCost = computed(() => subtotal.value)

const handlePatientChange = patient => {
  selectedPatient.value = patient
  form.value.patient_id = patient?.id || ''
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
    ...emptyItem(),
    phase_number: form.value.items.length + 1
  })
}

const removeItem = index => {
  if (form.value.items.length === 1) return
  form.value.items.splice(index, 1)
}

const toNumberOrNull = value => {
  const numeric = Number(value)
  return Number.isFinite(numeric) && numeric > 0 ? numeric : null
}

const handleSubmit = async () => {
  if (loading.value) return

  errors.value = {}

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

  const phasesCount = Number(form.value.phases_count)

  const data = {
    patient_id: Number(form.value.patient_id),
    title: form.value.title.trim(),
    description: form.value.description?.trim() || null,
    estimated_duration_weeks: toNumberOrNull(form.value.estimated_duration_weeks),
    start_date: form.value.start_date || null,
    end_date: form.value.end_date || null,
    phases:
      Number.isFinite(phasesCount) && phasesCount > 0
        ? Array.from({ length: phasesCount }, (_, i) => `Fase ${i + 1}`)
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
    baseline.value = snapshot()
    emit('saved')
  } catch (err) {
    handleSaveError(err)
  }
}

const firstMessage = value => (Array.isArray(value) ? value[0] : value)

const handleSaveError = err => {
  const data = err?.response?.data

  if (data?.errors && typeof data.errors === 'object') {
    const fieldErrors = {}
    Object.entries(data.errors).forEach(([field, value]) => {
      fieldErrors[field] = firstMessage(value)
    })

    const firstField = Object.keys(fieldErrors)[0]
    errors.value = {
      ...fieldErrors,
      _general: firstField
        ? `${firstField}: ${fieldErrors[firstField]}`
        : 'Revisa los campos marcados'
    }
  } else if (data?.message) {
    errors.value = { _general: data.message }
  } else if (err?.status === 422) {
    errors.value = { _general: 'Datos inválidos. Revisa los campos.' }
  } else if (err?.status === 500) {
    errors.value = {
      _general: 'Error del servidor. Intenta de nuevo o contacta soporte.'
    }
  } else {
    errors.value = { _general: 'Error desconocido al guardar el plan' }
  }
}

/**
 * UiModal owns Escape, the backdrop and the close button, so the dirty guard
 * moved to `before-close`: returning `false` vetoes the close. The inline
 * patient dialog and an in-flight save always veto too, matching the old
 * document-level handler.
 */
const confirmClose = async () => {
  if (showCreatePatient.value) return false
  if (loading.value) return false
  if (!isDirty.value) return true

  return confirm({
    title: 'Cambios sin guardar',
    message: 'Tienes cambios sin guardar. ¿Cerrar de todos modos?',
    confirmText: 'Cerrar sin guardar',
    variant: 'danger'
  })
}

/**
 * The custom header replaces UiModal's own header, so its close button lives
 * here. It delegates to the same guard before reporting the close the modal
 * would otherwise emit itself.
 */
const requestClose = async () => {
  const allowed = await confirmClose()
  if (allowed === false) return
  emit('close')
}

const initializeForm = () => {
  if (props.plan) {
    const items =
      props.plan.items?.length > 0
        ? props.plan.items.map(i => ({
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

  errors.value = {}
  baseline.value = snapshot()
}

watch(() => props.plan, initializeForm, { immediate: true })

onMounted(async () => {
  await nextTick()
  titleField.value?.querySelector('input')?.focus()
})
</script>
