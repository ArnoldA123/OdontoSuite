<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar procedimiento' : 'Nuevo procedimiento'"
    size="xl"
    @close="emit('close')"
  >
    <div class="space-y-6">
      <section data-form-group="identity">
        <h3 class="text-base font-semibold text-label">Identificación</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Código, nombre y especialidad con los que se reconocerá el procedimiento.
        </p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <UiInput
              v-model="form.code"
              label="Código *"
              placeholder="ej. ORTO-BRACKETS"
              :disabled="isEdit"
              :error="errors.code"
              class="w-full"
            />
            <p v-if="isEdit" class="mt-1 text-xs text-theme-secondary">
              El código no puede modificarse.
            </p>
          </div>
          <UiInput v-model="form.name" label="Nombre *" :error="errors.name" class="w-full" />
        </div>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">Descripción</label>
          <UiTextarea v-model="form.description" :rows="3" class="w-full" />
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-theme-primary">Especialidad</label>
            <UiSelect
              v-model="form.specialty_id"
              :options="specialtyOptions"
              placeholder="Sin especialidad"
              label="Especialidad"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-theme-primary">Estado</label>
            <UiSelect
              v-model="form.is_active"
              :options="activeOptions"
              placeholder="Seleccionar estado"
              label="Estado"
            />
          </div>
        </div>
      </section>

      <section data-form-group="pricing">
        <h3 class="text-base font-semibold text-label">Valor y duración</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Valores por defecto que se aplicarán al agendar o presupuestar.
        </p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <UiInput
            v-model.number="form.default_cost"
            label="Costo por defecto (S/) *"
            type="number"
            step="0.01"
            min="0"
            :error="errors.default_cost"
            class="w-full"
          />
          <UiInput
            v-model.number="form.default_duration_minutes"
            label="Duración por defecto (min) *"
            type="number"
            step="5"
            min="5"
            max="480"
            :error="errors.default_duration_minutes"
            class="w-full"
          />
        </div>
      </section>

      <section data-form-group="clinical">
        <h3 class="text-base font-semibold text-label">Preparación clínica</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Requisitos, materiales y cuidados que el equipo clínico debe conocer.
        </p>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Requisitos previos
          </label>
          <UiTextarea v-model="form.requirements" :rows="2" class="w-full" />
        </div>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Materiales necesarios
          </label>
          <UiTextarea
            v-model="form.materials_needed"
            :rows="2"
            placeholder="Separar por comas. Ej: resina, adhesivo, matriz"
            class="w-full"
          />
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-6">
          <label class="flex items-center gap-2 text-sm text-theme-primary">
            <input
              v-model="form.requires_anesthesia"
              type="checkbox"
              class="h-4 w-4 rounded border-theme text-accent focus:ring-accent"
            />
            Requiere anestesia
          </label>
          <label class="flex items-center gap-2 text-sm text-theme-primary">
            <input
              v-model="form.requires_radiographs"
              type="checkbox"
              class="h-4 w-4 rounded border-theme text-accent focus:ring-accent"
            />
            Requiere radiografías
          </label>
        </div>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Contraindicaciones
          </label>
          <UiTextarea v-model="form.contraindications" :rows="2" class="w-full" />
        </div>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Cuidados post-procedimiento
          </label>
          <UiTextarea v-model="form.post_procedure_care" :rows="2" class="w-full" />
        </div>
      </section>

      <p v-if="formError" class="text-sm text-systemRed-600" data-form-error>{{ formError }}</p>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton variant="secondary" :disabled="saving" @click="emit('close')">Cancelar</UiButton>
        <UiButton :disabled="saving" :loading="saving" data-form-submit @click="onSubmit">
          {{ isEdit ? 'Actualizar' : 'Crear' }}
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import { useToast } from '../../composables/useToast'
import UiModal from '../../components/ui/Modal.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'

const props = defineProps({
  procedure: { type: Object, default: null },
  specialties: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'saved'])

const { createProcedure, updateProcedure } = useProcedureCatalog()
const toast = useToast()

const isEdit = computed(() => Boolean(props.procedure))
const saving = ref(false)
const errors = ref({})
const formError = ref('')

const activeOptions = [
  { value: true, label: 'Activo' },
  { value: false, label: 'Inactivo' }
]

const specialtyOptions = computed(() => [
  { value: null, label: 'Sin especialidad' },
  ...props.specialties.map(specialty => ({ value: specialty.id, label: specialty.name }))
])

const getEmptyForm = () => ({
  code: '',
  name: '',
  description: '',
  specialty_id: null,
  default_cost: 0,
  default_duration_minutes: 30,
  requirements: '',
  materials_needed: '',
  requires_anesthesia: false,
  requires_radiographs: false,
  contraindications: '',
  post_procedure_care: '',
  is_active: true
})

const form = ref(getEmptyForm())

onMounted(() => {
  if (!props.procedure) return
  form.value = {
    code: props.procedure.code,
    name: props.procedure.name,
    description: props.procedure.description || '',
    specialty_id: props.procedure.specialty_id ?? null,
    default_cost: Number(props.procedure.default_cost ?? 0),
    default_duration_minutes: Number(props.procedure.default_duration_minutes ?? 30),
    requirements: props.procedure.requirements || '',
    materials_needed: props.procedure.materials_needed || '',
    requires_anesthesia: Boolean(props.procedure.requires_anesthesia),
    requires_radiographs: Boolean(props.procedure.requires_radiographs),
    contraindications: props.procedure.contraindications || '',
    post_procedure_care: props.procedure.post_procedure_care || '',
    is_active: Boolean(props.procedure.is_active)
  }
})

const validate = () => {
  const nextErrors = {}
  if (!isEdit.value && !form.value.code?.trim()) nextErrors.code = 'Requerido'
  if (!form.value.name?.trim()) nextErrors.name = 'Requerido'
  if (
    form.value.default_cost === null ||
    form.value.default_cost === undefined ||
    form.value.default_cost < 0
  ) {
    nextErrors.default_cost = 'Debe ser mayor o igual a 0'
  }
  if (!form.value.default_duration_minutes || form.value.default_duration_minutes < 5) {
    nextErrors.default_duration_minutes = 'Mínimo 5 min'
  }
  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

const onSubmit = async () => {
  if (saving.value) return
  formError.value = ''
  if (!validate()) return
  saving.value = true
  try {
    const payload = { ...form.value }
    let result
    if (isEdit.value) {
      delete payload.code
      result = await updateProcedure(props.procedure.id, payload)
    } else {
      result = await createProcedure(payload)
    }
    emit('saved', result)
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(error.response.data.errors).map(([field, messages]) => [field, messages[0]])
      )
      formError.value = 'Revisa los campos marcados.'
    } else {
      formError.value = error.response?.data?.message || 'No se pudo guardar el procedimiento'
      toast.error(formError.value)
    }
  } finally {
    saving.value = false
  }
}
</script>
