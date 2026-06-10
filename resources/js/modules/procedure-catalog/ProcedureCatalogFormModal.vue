<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar procedimiento' : 'Nuevo procedimiento'"
    size="xl"
    @close="emit('close')"
  >
    <form class="space-y-4" @submit.prevent="onSubmit">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Código *</label>
          <UiInput
            v-model="form.code"
            :disabled="isEdit"
            placeholder="ej. ORTO-BRACKETS"
            :error="errors.code"
            class="w-full"
          />
          <p v-if="isEdit" class="text-xs text-theme-secondary mt-1">
            El código no puede modificarse
          </p>
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Nombre *</label>
          <UiInput v-model="form.name" :error="errors.name" class="w-full" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">Descripción</label>
        <UiTextarea v-model="form.description" :rows="3" class="w-full" />
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Especialidad</label>
          <select
            v-model="form.specialty_id"
            class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
          >
            <option :value="null">— Sin especialidad —</option>
            <option v-for="s in specialties" :key="s.id" :value="s.id">
              {{ s.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
          <select
            v-model="form.is_active"
            class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
          >
            <option :value="true">Activo</option>
            <option :value="false">Inactivo</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Costo por defecto (S/) *
          </label>
          <UiInput
            v-model.number="form.default_cost"
            type="number"
            step="0.01"
            min="0"
            :error="errors.default_cost"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Duración por defecto (min) *
          </label>
          <UiInput
            v-model.number="form.default_duration_minutes"
            type="number"
            step="5"
            min="5"
            max="600"
            :error="errors.default_duration_minutes"
            class="w-full"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex items-center gap-2">
          <input
            id="requires_anesthesia"
            v-model="form.requires_anesthesia"
            type="checkbox"
            class="w-4 h-4 text-accent border-theme rounded focus:ring-accent"
          >
          <label for="requires_anesthesia" class="text-sm text-theme-primary">
            Requiere anestesia
          </label>
        </div>
        <div class="flex items-center gap-2">
          <input
            id="requires_radiographs"
            v-model="form.requires_radiographs"
            type="checkbox"
            class="w-4 h-4 text-accent border-theme rounded focus:ring-accent"
          >
          <label for="requires_radiographs" class="text-sm text-theme-primary">
            Requiere radiografías
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">
          Materiales necesarios
        </label>
        <UiTextarea
          v-model="form.materials_needed"
          :rows="2"
          placeholder="Separar por comas. Ej: resina, adhesivo, matriz"
          class="w-full"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">Contraindicaciones</label>
        <UiTextarea v-model="form.contraindications" :rows="2" class="w-full" />
      </div>

      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">
          Cuidados post-procedimiento
        </label>
        <UiTextarea v-model="form.post_procedure_care" :rows="2" class="w-full" />
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton variant="secondary" :disabled="saving" @click="emit('close')">Cancelar</UiButton>
        <UiButton :disabled="saving" @click="onSubmit">
          {{ saving ? 'Guardando...' : isEdit ? 'Actualizar' : 'Crear' }}
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import { useToast } from '../../composables/useToast'
import UiModal from '../../components/ui/Modal.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'

const props = defineProps({
  procedure: { type: Object, default: null },
  specialties: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'saved'])

const { createProcedure, updateProcedure } = useProcedureCatalog()
const toast = useToast()

const isEdit = computed(() => !!props.procedure)

const getEmptyForm = () => ({
  code: '',
  name: '',
  description: '',
  specialty_id: null,
  default_cost: 0,
  default_duration_minutes: 30,
  materials_needed: '',
  requires_anesthesia: false,
  requires_radiographs: false,
  contraindications: '',
  post_procedure_care: '',
  is_active: true
})

const form = ref(getEmptyForm())
const errors = ref({})
const saving = ref(false)

onMounted(() => {
  if (props.procedure) {
    form.value = {
      code: props.procedure.code,
      name: props.procedure.name,
      description: props.procedure.description || '',
      specialty_id: props.procedure.specialty_id || null,
      default_cost: props.procedure.default_cost,
      default_duration_minutes: props.procedure.default_duration_minutes,
      materials_needed: props.procedure.materials_needed || '',
      requires_anesthesia: !!props.procedure.requires_anesthesia,
      requires_radiographs: !!props.procedure.requires_radiographs,
      contraindications: props.procedure.contraindications || '',
      post_procedure_care: props.procedure.post_procedure_care || '',
      is_active: !!props.procedure.is_active
    }
  }
})

const validate = () => {
  const e = {}
  if (!isEdit.value && !form.value.code?.trim()) e.code = 'Requerido'
  if (!form.value.name?.trim()) e.name = 'Requerido'
  if (
    form.value.default_cost === null ||
    form.value.default_cost === undefined ||
    form.value.default_cost < 0
  ) {
    e.default_cost = 'Debe ser >= 0'
  }
  if (!form.value.default_duration_minutes || form.value.default_duration_minutes < 5) {
    e.default_duration_minutes = 'Mínimo 5 min'
  }
  errors.value = e
  return Object.keys(e).length === 0
}

const onSubmit = async () => {
  if (!validate()) return
  saving.value = true
  try {
    let result
    if (isEdit.value) {
      const { code: _code, ...payload } = form.value
      result = await updateProcedure(props.procedure.id, payload)
    } else {
      result = await createProcedure(form.value)
    }
    emit('saved', result)
  } catch (err) {
    if (err.response?.data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(err.response.data.errors).map(([k, v]) => [k, v[0]])
      )
    } else {
      toast.error(err.response?.data?.message || 'Error al guardar el procedimiento')
    }
  } finally {
    saving.value = false
  }
}
</script>
