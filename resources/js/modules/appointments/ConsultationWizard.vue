<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-2 sm:p-4 z-[100]"
      @click.self="handleClose"
    >
      <div class="bg-theme-surface-elevated rounded-2xl shadow-2xl w-full max-w-5xl max-h-[95vh] flex flex-col">
        <!-- Header -->
        <div class="p-5 border-b border-theme flex items-center justify-between">
          <div>
            <h2 class="text-xl font-semibold text-theme-primary">Expediente de Cita</h2>
            <p v-if="appointment" class="text-sm text-theme-secondary mt-1">
              {{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}
              · {{ appointment.user?.name }}
              · {{ formatDateTime(appointment.scheduled_at) }}
            </p>
          </div>
          <button
            @click="handleClose"
            class="text-theme-secondary hover:text-theme-primary transition-colors"
            aria-label="Cerrar"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Stepper -->
        <div class="px-5 py-3 border-b border-theme overflow-x-auto">
          <div class="flex items-center gap-1 min-w-max">
            <button
              v-for="(step, idx) in steps"
              :key="step.id"
              @click="currentStep = step.id"
              :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                currentStep === step.id
                  ? 'bg-accent text-white'
                  : 'text-theme-secondary hover:bg-theme-surface',
              ]"
            >
              <span class="w-5 h-5 rounded-full bg-white bg-opacity-20 text-xs flex items-center justify-center">{{ idx + 1 }}</span>
              {{ step.label }}
            </button>
          </div>
        </div>

        <!-- Loading state -->
        <div v-if="contextLoading" class="flex-1 flex items-center justify-center p-12">
          <div class="text-center">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-accent mx-auto mb-3"></div>
            <p class="text-theme-secondary text-sm">Cargando contexto clínico…</p>
          </div>
        </div>

        <!-- Content -->
        <div v-else class="flex-1 overflow-y-auto p-5 space-y-5">
          <!-- PASO 1: Modo de consulta -->
          <section v-if="currentStep === 'mode'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">¿Qué vas a hacer en esta cita?</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <button
                v-for="opt in modeOptions"
                :key="opt.value"
                @click="selectMode(opt.value)"
                :class="[
                  'p-4 rounded-xl border-2 text-left transition-all',
                  payload.mode === opt.value
                    ? 'border-accent bg-accent bg-opacity-5'
                    : 'border-theme hover:border-accent',
                ]"
              >
                <div class="text-2xl mb-2">{{ opt.icon }}</div>
                <div class="font-semibold text-theme-primary">{{ opt.label }}</div>
                <div class="text-xs text-theme-secondary mt-1">{{ opt.description }}</div>
              </button>
            </div>

            <!-- Plan selector (solo en plan_session) -->
            <div v-if="payload.mode === 'plan_session'" class="mt-4 p-4 bg-theme-surface rounded-xl">
              <label class="block text-sm font-medium text-theme-primary mb-2">Plan a avanzar</label>
              <select v-model="payload.treatment_plan.id" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated">
                <option :value="null">-- Selecciona un plan --</option>
                <option v-for="plan in activePlans" :key="plan.id" :value="plan.id">
                  {{ plan.plan_number }} · {{ plan.title }} ({{ plan.progress?.completed_items }}/{{ plan.progress?.total_items }})
                </option>
              </select>

              <div v-if="selectedPlan" class="mt-3 space-y-2">
                <label class="text-sm font-medium text-theme-secondary">Items a marcar como ejecutados hoy</label>
                <div
                  v-for="item in selectedPlan.items.filter(i => i.status !== 'completed')"
                  :key="item.id"
                  class="flex items-center gap-3 p-2 rounded-lg border border-theme"
                >
                  <input
                    type="checkbox"
                    :value="item.id"
                    v-model="executedItemIds"
                    class="w-4 h-4"
                  />
                  <div class="flex-1">
                    <div class="text-sm font-medium text-theme-primary">{{ item.procedure_name }}</div>
                    <div class="text-xs text-theme-secondary">Fase {{ item.phase_number }} · S/ {{ item.unit_cost }} · Status: {{ item.status }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Plan nuevo (ejecución o consulta con propuesta) -->
            <div v-if="payload.mode === 'execution' || (payload.mode === 'consultation' && payload.treatment_plan.as_proposed)" class="mt-4 p-4 bg-theme-surface rounded-xl space-y-3">
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">Título del plan</label>
                <input v-model="payload.treatment_plan.title" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated" placeholder="Ej: Rehabilitación cuadrante inferior" />
              </div>
              <div v-if="payload.mode === 'consultation'" class="flex items-center gap-2">
                <input type="checkbox" v-model="payload.treatment_plan.as_proposed" id="as_proposed" class="w-4 h-4" />
                <label for="as_proposed" class="text-sm text-theme-primary">Guardar como propuesta (no ejecutado aún)</label>
              </div>
            </div>
          </section>

          <!-- PASO 2: Evolución SOAP -->
          <section v-if="currentStep === 'evolution'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Evolución clínica (SOAP) <span class="text-red-500">*</span></h3>
            <p class="text-sm text-theme-secondary">Los 4 campos son obligatorios para cerrar la consulta.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">S — Subjetivo <span class="text-red-500">*</span></label>
                <textarea v-model="payload.evolution.subjective" rows="3" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated" placeholder="Lo que el paciente refiere"></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">O — Objetivo <span class="text-red-500">*</span></label>
                <textarea v-model="payload.evolution.objective" rows="3" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated" placeholder="Hallazgos clínicos, signos vitales, examen"></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">A — Assessment <span class="text-red-500">*</span></label>
                <textarea v-model="payload.evolution.assessment" rows="3" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated" placeholder="Diagnóstico, impresión clínica"></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">P — Plan <span class="text-red-500">*</span></label>
                <textarea v-model="payload.evolution.plan" rows="3" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated" placeholder="Tratamiento a seguir, próximas acciones"></textarea>
              </div>
            </div>

            <details class="mt-2">
              <summary class="cursor-pointer text-sm font-medium text-theme-secondary">Campos opcionales</summary>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-1">Procedimientos realizados</label>
                  <textarea v-model="payload.evolution.procedures_performed" rows="2" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-1">Materiales utilizados</label>
                  <textarea v-model="payload.evolution.materials_used" rows="2" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-1">Prescripciones</label>
                  <textarea v-model="payload.evolution.prescriptions" rows="2" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-1">Recomendaciones</label>
                  <textarea v-model="payload.evolution.recommendations" rows="2" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated"></textarea>
                </div>
              </div>
            </details>
          </section>

          <!-- PASO 3: Procedimientos / Plan -->
          <section v-if="currentStep === 'procedures'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Procedimientos</h3>
            <p v-if="payload.mode === 'consultation' && !payload.treatment_plan.as_proposed" class="text-sm text-theme-secondary">
              Esta cita es de evaluación. Si necesitas proponer un plan, marca "Guardar como propuesta" en el paso 1.
            </p>

            <div v-if="payload.mode === 'execution' || (payload.mode === 'consultation' && payload.treatment_plan.as_proposed)">
              <div class="space-y-2">
                <div
                  v-for="(item, idx) in payload.treatment_plan.items"
                  :key="idx"
                  class="p-3 border border-theme rounded-lg space-y-2"
                >
                  <div class="relative">
                    <label class="block text-xs text-theme-secondary mb-1">Procedimiento (catálogo)</label>
                    <input
                      v-model="item.procedure_name"
                      @input="onProcedureNameInput(idx, $event.target.value)"
                      @focus="onProcedureNameInput(idx, item.procedure_name)"
                      @blur="closeCatalogResults(idx)"
                      :placeholder="'Buscar en catálogo (código o nombre)…'"
                      autocomplete="off"
                      class="w-full p-2 rounded border border-theme bg-theme-surface-elevated"
                    />
                    <ul
                      v-if="catalogResults[idx] && catalogResults[idx].length"
                      class="absolute z-10 left-0 right-0 mt-1 bg-theme-surface-elevated border border-theme rounded-lg shadow-lg max-h-56 overflow-y-auto"
                    >
                      <li
                        v-for="opt in catalogResults[idx]"
                        :key="opt.id"
                        @mousedown.prevent="selectProcedure(idx, opt)"
                        class="px-3 py-2 hover:bg-accent hover:bg-opacity-10 cursor-pointer"
                      >
                        <div class="text-sm font-medium text-theme-primary">{{ opt.label }}</div>
                        <div class="text-xs text-theme-secondary">
                          {{ opt.specialty || 'general' }} · S/ {{ opt.default_cost }} · {{ opt.default_duration_minutes || '—' }} min
                        </div>
                      </li>
                    </ul>
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                    <input v-model="item.specialty" placeholder="Especialidad" class="p-2 rounded border border-theme bg-theme-surface-elevated" />
                    <input v-model.number="item.unit_cost" type="number" step="0.01" placeholder="Costo unit." class="p-2 rounded border border-theme bg-theme-surface-elevated" />
                    <input v-model.number="item.quantity" type="number" min="1" placeholder="Cantidad" class="p-2 rounded border border-theme bg-theme-surface-elevated" />
                    <input v-model.number="item.estimated_duration_minutes" type="number" min="5" placeholder="Duración (min)" class="p-2 rounded border border-theme bg-theme-surface-elevated" />
                  </div>
                  <div v-if="item.materials_required && item.materials_required.length" class="text-xs text-theme-secondary">
                    Materiales sugeridos: {{ item.materials_required.join(', ') }}
                  </div>
                  <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-xs text-theme-secondary">
                      <input type="checkbox" v-model="item.requires_anesthesia" class="w-3 h-3" />
                      Requiere anestesia
                    </label>
                    <button @click="removeItem(idx)" class="text-xs text-red-500 hover:underline">Quitar item</button>
                  </div>
                </div>
              </div>
              <button @click="addItem" class="mt-3 px-3 py-1.5 text-sm bg-accent text-white rounded-lg hover:bg-accent-dark">
                + Agregar procedimiento
              </button>
            </div>
          </section>

          <!-- PASO 4: Materiales -->
          <section v-if="currentStep === 'materials'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Materiales e insumos</h3>

            <div v-if="payload.mode === 'consultation'" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
              Esta cita es de evaluación. Si no se consumieron materiales, marca "Saltar".
            </div>

            <div v-if="requiresMaterials" class="p-3 bg-primary-50 border border-primary-200 rounded-lg text-sm text-primary-700">
              El tipo de cita "{{ appointmentType?.name }}" requiere registrar materiales.
            </div>

            <label class="flex items-center gap-2 text-sm text-theme-primary">
              <input type="checkbox" v-model="payload.skip_materials" class="w-4 h-4" />
              No se usaron materiales en esta cita
            </label>

            <div v-if="!payload.skip_materials">
              <div class="space-y-2">
                <div
                  v-for="(mat, idx) in payload.materials"
                  :key="idx"
                  class="p-3 border border-theme rounded-lg grid grid-cols-1 md:grid-cols-4 gap-2"
                >
                  <input v-model.number="mat.product_id" type="number" placeholder="ID Producto" class="p-2 rounded border border-theme bg-theme-surface-elevated" />
                  <input v-model.number="mat.quantity_used" type="number" step="0.01" placeholder="Cantidad" class="p-2 rounded border border-theme bg-theme-surface-elevated" />
                  <input v-model.number="mat.unit_cost" type="number" step="0.01" placeholder="Costo unit." class="p-2 rounded border border-theme bg-theme-surface-elevated" />
                  <button @click="removeMaterial(idx)" class="text-xs text-red-500 hover:underline">Quitar</button>
                </div>
              </div>
              <button @click="addMaterial" class="mt-3 px-3 py-1.5 text-sm bg-accent text-white rounded-lg hover:bg-accent-dark">
                + Agregar material
              </button>
            </div>
          </section>

          <!-- PASO 5: Adjuntos -->
          <section v-if="currentStep === 'attachments'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Archivos adjuntos</h3>
            <p class="text-sm text-theme-secondary">Radiografías, fotos clínicas, documentos. Máx 10MB por archivo.</p>
            <div class="space-y-2">
              <div
                v-for="(att, idx) in payload.attachments"
                :key="idx"
                class="p-3 border border-theme rounded-lg flex items-center gap-3"
              >
                <input type="file" @change="onFileSelected(idx, $event)" accept="image/*,application/pdf" class="flex-1 text-sm" />
                <input v-model="att.category" placeholder="Categoría" list="categories" class="p-2 rounded border border-theme bg-theme-surface-elevated" />
                <input v-model="att.description" placeholder="Descripción" class="flex-1 p-2 rounded border border-theme bg-theme-surface-elevated" />
                <button @click="removeAttachment(idx)" class="text-xs text-red-500 hover:underline">Quitar</button>
              </div>
            </div>
            <datalist id="categories">
              <option value="radiografia"></option>
              <option value="foto_clinica"></option>
              <option value="documento"></option>
              <option value="consentimiento"></option>
              <option value="otro"></option>
            </datalist>
            <button @click="addAttachment" class="px-3 py-1.5 text-sm bg-accent text-white rounded-lg hover:bg-accent-dark">
              + Agregar archivo
            </button>
          </section>

          <!-- PASO 6: Próxima cita -->
          <section v-if="currentStep === 'next'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Próxima cita (opcional)</h3>
            <p class="text-sm text-theme-secondary">Agenda una cita de seguimiento si aplica.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">Fecha y hora</label>
                <input v-model="payload.next_appointment.scheduled_at" type="datetime-local" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated" />
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">Duración (min)</label>
                <input v-model.number="payload.next_appointment.duration_minutes" type="number" min="15" step="15" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Notas</label>
              <textarea v-model="payload.next_appointment.notes" rows="2" class="w-full p-2 rounded-lg border border-theme bg-theme-surface-elevated"></textarea>
            </div>
          </section>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-theme flex items-center justify-between">
          <button
            @click="prevStep"
            :disabled="currentStepIndex === 0"
            class="px-4 py-2 text-sm font-medium text-theme-secondary hover:text-theme-primary disabled:opacity-30"
          >
            ← Anterior
          </button>
          <div class="flex items-center gap-2">
            <button
              @click="handleClose"
              class="px-4 py-2 text-sm font-medium text-theme-secondary hover:text-theme-primary"
            >
              Cancelar
            </button>
            <button
              v-if="!isLastStep"
              @click="nextStep"
              class="px-4 py-2 text-sm font-medium bg-theme-surface border border-theme rounded-lg hover:bg-theme-surface-elevated"
            >
              Siguiente →
            </button>
            <button
              v-else
              @click="handleSubmit"
              :disabled="!canSubmit || submitting"
              class="px-5 py-2 text-sm font-semibold bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
            >
              {{ submitting ? 'Completando…' : '✓ Completar consulta' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue'
import { useConsultation } from '../../composables/useConsultation'
import { useApi } from '../../composables/useApi'

const props = defineProps({
  appointment: { type: Object, default: null },
})
const emit = defineEmits(['completed', 'close'])

const {
  isOpen,
  context,
  contextLoading,
  submitting,
  loadContext,
  submit,
  close: closeComposable,
} = useConsultation()

const { get: apiGet } = useApi()

const modeOptions = [
  { value: 'consultation', label: 'Consulta / Evaluación', icon: '🩺', description: 'No ejecuto procedimientos hoy. Puede generar plan propuesto.' },
  { value: 'execution', label: 'Ejecutar procedimiento', icon: '🦷', description: 'Crea un plan con 1 item ejecutado y completa la cita.' },
  { value: 'plan_session', label: 'Avanzar plan existente', icon: '📋', description: 'Selecciona un plan del paciente y marca los items de hoy.' },
]

const steps = [
  { id: 'mode', label: 'Modo' },
  { id: 'evolution', label: 'Evolución' },
  { id: 'procedures', label: 'Procedimientos' },
  { id: 'materials', label: 'Materiales' },
  { id: 'attachments', label: 'Adjuntos' },
  { id: 'next', label: 'Próxima cita' },
]

const currentStep = ref('mode')
const executedItemIds = ref([])
const catalogResults = reactive({})
const catalogSearchTimers = reactive({})

const payload = ref({
  mode: 'consultation',
  skip_materials: false,
  evolution: {
    subjective: '',
    objective: '',
    assessment: '',
    plan: '',
    procedures_performed: '',
    materials_used: '',
    prescriptions: '',
    recommendations: '',
    next_appointment_notes: '',
    requires_follow_up: false,
    follow_up_date: null,
  },
  odontogram: [],
  treatment_plan: {
    id: null,
    create_new: true,
    title: '',
    as_proposed: false,
    items: [],
  },
  materials: [],
  attachments: [],
  next_appointment: {
    scheduled_at: '',
    duration_minutes: 30,
    notes: '',
  },
})

const currentStepIndex = computed(() => steps.findIndex(s => s.id === currentStep.value))
const isLastStep = computed(() => currentStepIndex.value === steps.length - 1)

const activePlans = computed(() => context.value?.active_plans ?? [])
const selectedPlan = computed(() => activePlans.value.find(p => p.id === payload.value.treatment_plan.id))
const requiresMaterials = computed(() => context.value?.requires_materials ?? false)
const appointmentType = computed(() => context.value?.appointment_type ?? null)

const canSubmit = computed(() => {
  const e = payload.value.evolution
  return (
    payload.value.mode &&
    e.subjective?.trim() &&
    e.objective?.trim() &&
    e.assessment?.trim() &&
    e.plan?.trim() &&
    (!requiresMaterials.value || payload.value.skip_materials || payload.value.materials.length > 0) &&
    (payload.value.mode !== 'plan_session' || payload.value.treatment_plan.id)
  )
})

watch(() => props.appointment, (newAppt) => {
  if (newAppt?.id) {
    loadContext(newAppt.id)
  }
}, { immediate: true })

watch(selectedPlan, (plan) => {
  if (!plan) return
  payload.value.treatment_plan.items = plan.items
    .filter(i => executedItemIds.value.includes(i.id))
    .map(i => ({ ...i, status: 'completed' }))
}, { deep: true })

const selectMode = (mode) => {
  payload.value.mode = mode
  if (mode === 'consultation') {
    payload.value.treatment_plan.as_proposed = false
  }
}

const addItem = () => {
  payload.value.treatment_plan.items.push({
    procedure_catalog_id: null,
    procedure_name: '',
    specialty: '',
    unit_cost: 0,
    quantity: 1,
    phase_number: 1,
    estimated_duration_minutes: null,
    materials_required: [],
    requires_anesthesia: false,
  })
}

const removeItem = (idx) => {
  delete catalogResults[idx]
  delete catalogSearchTimers[idx]
  payload.value.treatment_plan.items.splice(idx, 1)
}

const onProcedureNameInput = (idx, value) => {
  if (catalogSearchTimers[idx]) {
    clearTimeout(catalogSearchTimers[idx])
  }
  const term = (value ?? '').trim()
  if (term.length < 2) {
    catalogResults[idx] = []
    return
  }
  catalogSearchTimers[idx] = setTimeout(async () => {
    try {
      const response = await apiGet('/api/procedure-catalog/search', { params: { q: term, limit: 10 } })
      catalogResults[idx] = response?.data ?? []
    } catch (error) {
      catalogResults[idx] = []
    }
  }, 250)
}

const selectProcedure = (idx, opt) => {
  const item = payload.value.treatment_plan.items[idx]
  if (!item) return
  item.procedure_catalog_id = opt.id
  item.procedure_name = opt.name
  item.specialty = opt.specialty ?? item.specialty
  item.unit_cost = opt.default_cost ?? item.unit_cost
  item.estimated_duration_minutes = opt.default_duration_minutes ?? item.estimated_duration_minutes
  item.materials_required = opt.materials_needed_list ?? item.materials_required ?? []
  item.requires_anesthesia = opt.requires_anesthesia ?? item.requires_anesthesia
  catalogResults[idx] = []
}

const closeCatalogResults = (idx) => {
  setTimeout(() => {
    catalogResults[idx] = []
  }, 150)
}

const addMaterial = () => {
  payload.value.materials.push({ product_id: null, quantity_used: 1, unit_cost: 0 })
}

const removeMaterial = (idx) => {
  payload.value.materials.splice(idx, 1)
}

const addAttachment = () => {
  payload.value.attachments.push({ file: null, category: 'foto_clinica', description: '', is_private: false })
}

const removeAttachment = (idx) => {
  payload.value.attachments.splice(idx, 1)
}

const onFileSelected = (idx, event) => {
  const file = event.target.files?.[0]
  if (file) {
    payload.value.attachments[idx].file = file
  }
}

const nextStep = () => {
  const idx = currentStepIndex.value
  if (idx < steps.length - 1) {
    currentStep.value = steps[idx + 1].id
  }
}

const prevStep = () => {
  const idx = currentStepIndex.value
  if (idx > 0) {
    currentStep.value = steps[idx - 1].id
  }
}

const handleClose = () => {
  closeComposable()
  emit('close')
}

const handleSubmit = async () => {
  if (!canSubmit.value) return
  try {
    const result = await submit(payload.value)
    emit('completed', {
      appointment: result?.data ?? null,
      quotation: result?.quotation ?? null,
      quotation_generated: !!result?.meta?.quotation_generated,
    })
    handleClose()
  } catch (e) {
  }
}

const formatDateTime = (iso) => {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
}
</script>
