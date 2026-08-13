<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4 z-[100]"
      @click.self="handleClose"
    >
      <div
        class="bg-canvas rounded-2xl shadow-2xl w-full max-w-5xl max-h-[95vh] flex flex-col border border-hairline"
      >
        <!-- Header -->
        <div class="p-5 border-b border-hairline flex items-center justify-between">
          <div>
            <h2 class="text-xl font-semibold text-theme-primary">Expediente de Cita</h2>
            <p v-if="appointment" class="text-sm text-theme-secondary mt-1">
              {{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }} ·
              {{ appointment.user?.name }} · {{ formatDateTime(appointment.scheduled_at) }}
            </p>
          </div>
          <button
            class="text-theme-secondary hover:text-theme-primary transition-colors"
            aria-label="Cerrar"
            @click="handleClose"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        <!-- Stepper — UiTabs primitive (CITAS-WIZ-001) -->
        <div class="px-5 py-3 border-b border-hairline overflow-x-auto">
          <UiTabs
            v-model="currentStep"
            :tabs="tabsForUiTabs"
            variant="pills"
            aria-label="Pasos de la consulta"
          />
        </div>

        <!-- Loading state -->
        <div v-if="contextLoading" class="flex-1 flex items-center justify-center p-12">
          <UiLoadingSpinner size="lg" variant="primary" text="Cargando contexto clínico…" />
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
                class="p-4 rounded-xl border-2 text-left transition-all"
                :class="[
                  payload.mode === opt.value
                    ? 'border-systemBlue-500 bg-systemBlue-50'
                    : 'border-hairline hover:border-systemBlue-500'
                ]"
                @click="selectMode(opt.value)"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="text-2xl">
                    {{ opt.icon }}
                  </div>
                  <UiStatusBadge
                    v-if="payload.mode === opt.value"
                    :variant="modeBadgeVariant(opt.value)"
                    :label="opt.label"
                    size="sm"
                    :show-dot="true"
                  />
                </div>
                <div class="font-semibold text-theme-primary">
                  {{ opt.label }}
                </div>
                <div class="text-xs text-theme-secondary mt-1">
                  {{ opt.description }}
                </div>
              </button>
            </div>

            <!-- Plan selector (solo en plan_session) -->
            <div
              v-if="payload.mode === 'plan_session'"
              class="mt-4 p-4 bg-theme-surface rounded-xl"
            >
              <UiSelect
                v-model="payload.treatment_plan.id"
                label="Plan a avanzar"
                :options="planOptions"
                placeholder="-- Selecciona un plan --"
              />

              <div v-if="selectedPlan" class="mt-3 space-y-2">
                <label class="text-sm font-medium text-theme-secondary">
                  Items a marcar como ejecutados hoy
                </label>
                <div
                  v-for="item in selectedPlan.items.filter(i => i.status !== 'completed')"
                  :key="item.id"
                  class="flex items-center gap-3 p-2 rounded-lg border-hairline"
                >
                  <input
                    v-model="executedItemIds"
                    type="checkbox"
                    :value="item.id"
                    class="w-4 h-4"
                  />
                  <div class="flex-1">
                    <div class="text-sm font-medium text-theme-primary">
                      {{ item.procedure_name }}
                    </div>
                    <div class="text-xs text-theme-secondary">
                      Fase {{ item.phase_number }} · S/ {{ item.unit_cost }} · Status:
                      {{ item.status }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Plan nuevo (ejecución o consulta con propuesta) -->
            <div
              v-if="
                payload.mode === 'execution' ||
                  (payload.mode === 'consultation' && payload.treatment_plan.as_proposed)
              "
              class="mt-4 p-4 bg-theme-surface rounded-xl space-y-3"
            >
              <div>
                <label
                  for="cw-plan-title"
                  class="block text-sm font-medium text-theme-primary mb-1"
                >
                  Título del plan
                </label>
                <input
                  id="cw-plan-title"
                  v-model="payload.treatment_plan.title"
                  class="w-full p-2 rounded-lg border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                  placeholder="Ej: Rehabilitación cuadrante inferior"
                />
              </div>
              <div v-if="payload.mode === 'consultation'" class="flex items-center gap-2">
                <input
                  id="as_proposed"
                  v-model="payload.treatment_plan.as_proposed"
                  type="checkbox"
                  class="w-4 h-4"
                />
                <label for="as_proposed" class="text-sm text-theme-primary">
                  Guardar como propuesta (no ejecutado aún)
                </label>
              </div>
            </div>
          </section>

          <!-- PASO 2: Evolución SOAP -->
          <section v-if="currentStep === 'evolution'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Evolución clínica (SOAP)</h3>
            <p class="text-sm text-theme-secondary">
              Los 4 campos son obligatorios para cerrar la consulta.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="cw-soap-s" class="block text-sm font-medium text-theme-primary mb-1">
                  S — Subjetivo
                </label>
                <UiTextarea
                  id="cw-soap-s"
                  v-model="payload.evolution.subjective"
                  :rows="3"
                  required
                  placeholder="Lo que el paciente refiere"
                />
              </div>
              <div>
                <label for="cw-soap-o" class="block text-sm font-medium text-theme-primary mb-1">
                  O — Objetivo
                </label>
                <UiTextarea
                  id="cw-soap-o"
                  v-model="payload.evolution.objective"
                  :rows="3"
                  required
                  placeholder="Hallazgos clínicos, signos vitales, examen"
                />
              </div>
              <div>
                <label for="cw-soap-a" class="block text-sm font-medium text-theme-primary mb-1">
                  A — Assessment
                </label>
                <UiTextarea
                  id="cw-soap-a"
                  v-model="payload.evolution.assessment"
                  :rows="3"
                  required
                  placeholder="Diagnóstico, impresión clínica"
                />
              </div>
              <div>
                <label for="cw-soap-p" class="block text-sm font-medium text-theme-primary mb-1">
                  P — Plan
                </label>
                <UiTextarea
                  id="cw-soap-p"
                  v-model="payload.evolution.plan"
                  :rows="3"
                  required
                  placeholder="Tratamiento a seguir, próximas acciones"
                />
              </div>
            </div>

            <details class="mt-2">
              <summary class="cursor-pointer text-sm font-medium text-theme-secondary">
                Campos opcionales
              </summary>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-1">
                    Procedimientos realizados
                  </label>
                  <UiTextarea v-model="payload.evolution.procedures_performed" :rows="2" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-1">
                    Materiales utilizados
                  </label>
                  <UiTextarea v-model="payload.evolution.materials_used" :rows="2" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-1">
                    Prescripciones
                  </label>
                  <UiTextarea v-model="payload.evolution.prescriptions" :rows="2" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-theme-primary mb-1">
                    Recomendaciones
                  </label>
                  <UiTextarea v-model="payload.evolution.recommendations" :rows="2" />
                </div>
              </div>
            </details>
          </section>

          <!-- PASO 3: Procedimientos / Plan -->
          <section v-if="currentStep === 'procedures'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Procedimientos</h3>
            <p
              v-if="payload.mode === 'consultation' && !payload.treatment_plan.as_proposed"
              class="text-sm text-theme-secondary"
            >
              Esta cita es de evaluación. Si necesitas proponer un plan, marca "Guardar como
              propuesta" en el paso 1.
            </p>

            <div
              v-if="
                payload.mode === 'execution' ||
                  (payload.mode === 'consultation' && payload.treatment_plan.as_proposed)
              "
            >
              <div class="space-y-2">
                <div
                  v-for="(item, idx) in payload.treatment_plan.items"
                  :key="idx"
                  class="p-3 border-hairline rounded-lg space-y-2"
                >
                  <div class="relative">
                    <label class="block text-xs text-theme-secondary mb-1">
                      Procedimiento (catálogo)
                    </label>
                    <UiInput
                      v-model="item.procedure_name"
                      type="search"
                      placeholder="Buscar en catálogo (código o nombre)…"
                      @update:model-value="onProcedureNameInput(idx, $event)"
                      @focus="onProcedureNameInput(idx, item.procedure_name)"
                      @blur="closeCatalogResults(idx)"
                    />
                    <ul
                      v-if="catalogResults[idx] && catalogResults[idx].length"
                      class="absolute z-10 left-0 right-0 mt-1 bg-theme-surface-elevated border-hairline rounded-lg shadow-lg max-h-56 overflow-y-auto"
                    >
                      <li
                        v-for="opt in catalogResults[idx]"
                        :key="opt.id"
                        class="px-3 py-2 hover:bg-systemBlue-50 cursor-pointer"
                        @mousedown.prevent="selectProcedure(idx, opt)"
                      >
                        <div class="text-sm font-medium text-theme-primary">
                          {{ opt.label }}
                        </div>
                        <div class="text-xs text-theme-secondary">
                          {{ opt.specialty || 'general' }} · S/ {{ opt.default_cost }} ·
                          {{ opt.default_duration_minutes || '—' }} min
                        </div>
                      </li>
                    </ul>
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                    <input
                      v-model="item.specialty"
                      placeholder="Especialidad"
                      class="p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                    />
                    <input
                      v-model.number="item.unit_cost"
                      type="number"
                      step="0.01"
                      placeholder="Costo unit."
                      class="p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                      style="font-feature-settings: var(--font-features-tabular-nums)"
                    />
                    <input
                      v-model.number="item.quantity"
                      type="number"
                      min="1"
                      placeholder="Cantidad"
                      class="p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                      style="font-feature-settings: var(--font-features-tabular-nums)"
                    />
                    <input
                      v-model.number="item.estimated_duration_minutes"
                      type="number"
                      min="5"
                      placeholder="Duración (min)"
                      class="p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                      style="font-feature-settings: var(--font-features-tabular-nums)"
                    />
                  </div>
                  <div
                    v-if="item.materials_required && item.materials_required.length"
                    class="text-xs text-theme-secondary"
                  >
                    Materiales sugeridos: {{ item.materials_required.join(', ') }}
                  </div>
                  <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-xs text-theme-secondary">
                      <input v-model="item.requires_anesthesia" type="checkbox" class="w-3 h-3" >
                      Requiere anestesia
                    </label>
                    <button
                      class="text-xs text-systemRed-600 hover:underline"
                      @click="removeItem(idx)"
                    >
                      Quitar item
                    </button>
                  </div>
                </div>
              </div>
              <UiButton variant="secondary" size="sm" class="mt-3" @click="addItem">
                + Agregar procedimiento
              </UiButton>
            </div>
          </section>

          <!-- PASO 4: Materiales -->
          <section v-if="currentStep === 'materials'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Materiales e insumos</h3>

            <div
              v-if="payload.mode === 'consultation'"
              class="p-3 bg-systemYellow-50 border border-systemYellow-200 rounded-lg text-sm text-systemYellow-700"
            >
              Esta cita es de evaluación. Si no se consumieron materiales, marca "Saltar".
            </div>

            <div
              v-if="requiresMaterials"
              class="p-3 bg-systemBlue-50 border border-systemBlue-200 rounded-lg text-sm text-systemBlue-700"
            >
              El tipo de cita "{{ appointmentType?.name }}" requiere registrar materiales.
            </div>

            <label class="flex items-center gap-2 text-sm text-theme-primary">
              <input v-model="payload.skip_materials" type="checkbox" class="w-4 h-4" >
              No se usaron materiales en esta cita
            </label>

            <div v-if="!payload.skip_materials">
              <div class="space-y-2">
                <div
                  v-for="(mat, idx) in payload.materials"
                  :key="idx"
                  class="p-3 border-hairline rounded-lg grid grid-cols-1 md:grid-cols-12 gap-2 relative"
                >
                  <div class="md:col-span-5 relative">
                    <input
                      v-model="mat._label"
                      type="text"
                      placeholder="Buscar producto por nombre o código"
                      class="w-full p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                      autocomplete="off"
                      @input="onProductSearchInput(idx, $event.target.value)"
                      @focus="onProductSearchInput(idx, mat._label)"
                      @blur="closeProductResults(idx)"
                    />
                    <div
                      v-if="productResults[idx] && productResults[idx].length"
                      class="absolute z-10 left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-lg border-hairline bg-theme-surface-elevated shadow-lg"
                    >
                      <button
                        v-for="opt in productResults[idx]"
                        :key="opt.id"
                        type="button"
                        class="w-full text-left px-3 py-2 hover:bg-theme-surface text-sm flex justify-between gap-2"
                        @mousedown.prevent="selectProduct(idx, opt)"
                      >
                        <span>
                          <span class="font-medium">{{ opt.name }}</span>
                          <span v-if="opt.code" class="text-xs text-theme-secondary ml-1">
                            ({{ opt.code }})
                          </span>
                        </span>
                        <span class="text-xs text-theme-secondary whitespace-nowrap">
                          {{ opt.unit || '' }}
                          <span v-if="opt.cost_price">· S/ {{ opt.cost_price }}</span>
                        </span>
                      </button>
                    </div>
                    <p
                      v-if="mat.product_id && mat._label"
                      class="mt-1 text-xs text-theme-secondary"
                    >
                      ID: {{ mat.product_id }} · {{ mat._label }}
                    </p>
                  </div>
                  <input
                    v-model.number="mat.quantity_used"
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="Cantidad"
                    class="md:col-span-2 p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                    style="font-feature-settings: var(--font-features-tabular-nums)"
                  />
                  <input
                    v-model.number="mat.unit_cost"
                    type="number"
                    step="0.01"
                    placeholder="Costo unit."
                    class="md:col-span-3 p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                    style="font-feature-settings: var(--font-features-tabular-nums)"
                  />
                  <button
                    class="md:col-span-2 text-xs text-systemRed-600 hover:underline"
                    @click="removeMaterial(idx)"
                  >
                    Quitar
                  </button>
                </div>
              </div>
              <UiButton variant="secondary" size="sm" class="mt-3" @click="addMaterial">
                + Agregar material
              </UiButton>
            </div>
          </section>

          <!-- PASO 5: Adjuntos -->
          <section v-if="currentStep === 'attachments'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Archivos adjuntos</h3>
            <p class="text-sm text-theme-secondary">
              Radiografías, fotos clínicas, documentos. Máx 10MB por archivo.
            </p>
            <div class="space-y-2">
              <div
                v-for="(att, idx) in payload.attachments"
                :key="idx"
                class="p-3 border-hairline rounded-lg flex items-center gap-3"
              >
                <input
                  type="file"
                  accept="image/*,application/pdf"
                  class="flex-1 text-sm"
                  @change="onFileSelected(idx, $event)"
                />
                <input
                  v-model="att.category"
                  placeholder="Categoría"
                  list="categories"
                  class="p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                />
                <input
                  v-model="att.description"
                  placeholder="Descripción"
                  class="flex-1 p-2 rounded border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                />
                <button
                  class="text-xs text-systemRed-600 hover:underline"
                  @click="removeAttachment(idx)"
                >
                  Quitar
                </button>
              </div>
            </div>
            <datalist id="categories">
              <option value="radiografia" />
              <option value="foto_clinica" />
              <option value="documento" />
              <option value="consentimiento" />
              <option value="otro" />
            </datalist>
            <UiButton variant="secondary" size="sm" @click="addAttachment">
              + Agregar archivo
            </UiButton>
          </section>

          <!-- PASO 6: Próxima cita -->
          <section v-if="currentStep === 'next'" class="space-y-4">
            <h3 class="text-lg font-semibold text-theme-primary">Próxima cita (opcional)</h3>
            <p class="text-sm text-theme-secondary">Agenda una cita de seguimiento si aplica.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label for="cw-next-date" class="block text-sm font-medium text-theme-primary mb-1">
                  Fecha y hora
                </label>
                <input
                  id="cw-next-date"
                  v-model="payload.next_appointment.scheduled_at"
                  type="datetime-local"
                  class="w-full p-2 rounded-lg border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                />
              </div>
              <div>
                <label
                  for="cw-next-duration"
                  class="block text-sm font-medium text-theme-primary mb-1"
                >
                  Duración (min)
                </label>
                <input
                  id="cw-next-duration"
                  v-model.number="payload.next_appointment.duration_minutes"
                  type="number"
                  min="15"
                  step="15"
                  class="w-full p-2 rounded-lg border-hairline bg-theme-surface-elevated focus:outline-none focus:ring-2 focus:ring-systemBlue-500"
                  style="font-feature-settings: var(--font-features-tabular-nums)"
                />
              </div>
            </div>
            <div>
              <label for="cw-next-notes" class="block text-sm font-medium text-theme-primary mb-1">
                Notas
              </label>
              <UiTextarea id="cw-next-notes" v-model="payload.next_appointment.notes" :rows="2" />
            </div>
          </section>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-hairline flex items-center justify-between">
          <UiButton variant="ghost" size="sm" :disabled="currentStepIndex === 0"
@click="prevStep">
            ← Anterior
          </UiButton>
          <div class="flex items-center gap-2">
            <UiButton variant="ghost" size="sm" @click="handleClose">Cancelar</UiButton>
            <UiButton v-if="!isLastStep" variant="secondary" size="sm"
@click="nextStep">
              Siguiente →
            </UiButton>
            <UiButton
              v-else
              variant="primary"
              size="md"
              :disabled="!canSubmit || submitting"
              :loading="submitting"
              @click="handleSubmit"
            >
              ✓ Completar consulta
            </UiButton>
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
import UiTabs from '@/components/ui/Tabs.vue'
import UiInput from '@/components/ui/Input.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiButton from '@/components/ui/Button.vue'
import UiLoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const props = defineProps({
  appointment: { type: Object, default: null }
})
const emit = defineEmits(['completed', 'close'])

const {
  isOpen,
  context,
  contextLoading,
  submitting,
  loadContext,
  submit,
  close: closeComposable
} = useConsultation()

const { get: apiGet } = useApi()

const modeOptions = [
  {
    value: 'consultation',
    label: 'Consulta / Evaluación',
    icon: '🩺',
    description: 'No ejecuto procedimientos hoy. Puede generar plan propuesto.'
  },
  {
    value: 'execution',
    label: 'Ejecutar procedimiento',
    icon: '🦷',
    description: 'Crea un plan con 1 item ejecutado y completa la cita.'
  },
  {
    value: 'plan_session',
    label: 'Avanzar plan existente',
    icon: '📋',
    description: 'Selecciona un plan del paciente y marca los items de hoy.'
  }
]

const steps = [
  { id: 'mode', label: 'Modo' },
  { id: 'evolution', label: 'Evolución' },
  { id: 'procedures', label: 'Procedimientos' },
  { id: 'materials', label: 'Materiales' },
  { id: 'attachments', label: 'Adjuntos' },
  { id: 'next', label: 'Próxima cita' }
]

// Additive: maps wizard step id+label into the UiTabs tab shape, with a
// numbered prefix so the clinician keeps the visual ordinal from the legacy
// hand-built step strip. Pure derivation; does not touch the existing
// `steps` array or the `currentStep` ref binding (CITAS-CON-001).
const tabsForUiTabs = computed(() =>
  steps.map((step, idx) => ({
    id: step.id,
    label: `${idx + 1}. ${step.label}`
  }))
)

// Additive: maps the 3 wizard modes to the UiStatusBadge variant ramp
// (per design §2.7: info = evaluative, success = executable, warning =
// advancing an existing plan). Pure mapping; consumed only by the
// mode-card chip in step 1.
const modeBadgeVariant = mode => {
  if (mode === 'consultation') return 'info'
  if (mode === 'execution') return 'success'
  return 'warning'
}

const currentStep = ref('mode')
const executedItemIds = ref([])
const catalogResults = reactive({})
const catalogSearchTimers = reactive({})
const productResults = reactive({})
const productSearchTimers = reactive({})

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
    follow_up_date: null
  },
  odontogram: [],
  treatment_plan: {
    id: null,
    create_new: true,
    title: '',
    as_proposed: false,
    items: []
  },
  materials: [],
  attachments: [],
  next_appointment: {
    scheduled_at: '',
    duration_minutes: 30,
    notes: ''
  }
})

const currentStepIndex = computed(() => steps.findIndex(s => s.id === currentStep.value))
const isLastStep = computed(() => currentStepIndex.value === steps.length - 1)

const activePlans = computed(() => context.value?.active_plans ?? [])
const selectedPlan = computed(() =>
  activePlans.value.find(p => p.id === payload.value.treatment_plan.id)
)

// Additive: maps `activePlans` into the {value,label} shape consumed by
// <UiSelect>. Pure derivation; does not touch the existing `activePlans`
// computed or the `payload.treatment_plan.id` binding (CITAS-CON-001).
const planOptions = computed(() =>
  activePlans.value.map(p => ({
    value: p.id,
    label: `${p.plan_number} · ${p.title} (${p.progress?.completed_items ?? 0}/${p.progress?.total_items ?? 0})`
  }))
)
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
    (!requiresMaterials.value ||
      payload.value.skip_materials ||
      payload.value.materials.length > 0) &&
    (payload.value.mode !== 'plan_session' || payload.value.treatment_plan.id)
  )
})

watch(
  () => props.appointment,
  newAppt => {
    if (newAppt?.id) {
      loadContext(newAppt.id)
    }
  },
  { immediate: true }
)

watch(
  selectedPlan,
  plan => {
    if (!plan) return
    payload.value.treatment_plan.items = plan.items
      .filter(i => executedItemIds.value.includes(i.id))
      .map(i => ({ ...i, status: 'completed' }))
  },
  { deep: true }
)

const selectMode = mode => {
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
    requires_anesthesia: false
  })
}

const removeItem = idx => {
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
      const response = await apiGet('/api/procedure-catalog/search', {
        params: { q: term, limit: 10 }
      })
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

const closeCatalogResults = idx => {
  setTimeout(() => {
    catalogResults[idx] = []
  }, 150)
}

const addMaterial = () => {
  payload.value.materials.push({
    product_id: null,
    _label: '',
    quantity_used: 1,
    unit_cost: 0
  })
}

const removeMaterial = idx => {
  if (productSearchTimers[idx]) {
    clearTimeout(productSearchTimers[idx])
    delete productSearchTimers[idx]
  }
  delete productResults[idx]
  payload.value.materials.splice(idx, 1)
}

const onProductSearchInput = (idx, value) => {
  if (productSearchTimers[idx]) {
    clearTimeout(productSearchTimers[idx])
  }
  const term = (value ?? '').trim()
  // Si el usuario edita el label, des-sincronizamos product_id para forzar
  // a re-seleccionar. Asi evitamos enviar un product_id con un label que
  // no le corresponde (caso que rompia la consulta con FK 1452).
  const mat = payload.value.materials[idx]
  if (mat && mat.product_id) {
    mat.product_id = null
  }
  if (term.length < 2) {
    productResults[idx] = []
    return
  }
  productSearchTimers[idx] = setTimeout(async () => {
    try {
      const response = await apiGet('/api/products/search', { params: { q: term, limit: 10 } })
      productResults[idx] = response?.data ?? []
    } catch (error) {
      productResults[idx] = []
    }
  }, 250)
}

const selectProduct = (idx, opt) => {
  const mat = payload.value.materials[idx]
  if (!mat) return
  mat.product_id = opt.id
  mat._label = opt.name
  if (opt.cost_price != null) {
    mat.unit_cost = opt.cost_price
  }
  productResults[idx] = []
}

const closeProductResults = idx => {
  setTimeout(() => {
    productResults[idx] = []
  }, 150)
}

const addAttachment = () => {
  payload.value.attachments.push({
    file: null,
    category: 'foto_clinica',
    description: '',
    is_private: false
  })
}

const removeAttachment = idx => {
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
      quotation_generated: !!result?.meta?.quotation_generated
    })
    handleClose()
  } catch (e) {}
}

const formatDateTime = iso => {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
}
</script>
