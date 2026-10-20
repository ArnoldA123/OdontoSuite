<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar presupuesto' : 'Nuevo presupuesto'"
    size="lg"
    @close="emit('close')"
  >
    <div class="space-y-6 bg-canvas" :data-quotation-form="isEdit ? 'edit' : 'create'">
      <p
        v-if="submitError"
        class="rounded-ios border border-hairline bg-theme-surface px-4 py-3 text-sm text-error-500"
        role="alert"
        data-submit-error
      >
        {{ submitError }}
      </p>

      <fieldset class="space-y-4" data-form-section="patient">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Paciente y vigencia
        </legend>

        <div data-field="patient">
          <PatientSelector
            v-model="patient"
            :disabled="isEdit"
            @patient-selected="handlePatientSelected"
          />
          <p v-if="patientError" class="mt-2 text-xs text-error-500" role="alert">
            {{ patientError }}
          </p>
        </div>

        <div data-field="treatment_plan">
          <TreatmentPlanSelector
            v-model="plan"
            :disabled="isEdit"
            @plan-selected="handlePlanSelected"
          />
        </div>

        <p v-if="isEdit" class="text-xs text-theme-secondary" data-readonly-hint>
          El paciente y el plan de origen no se modifican en un presupuesto existente.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div data-field="quotation_date">
            <UiInput
              v-model="quotationDate"
              label="Fecha de emisión"
              type="date"
              :disabled="saving"
              :error="errors.quotation_date"
            />
          </div>
          <div data-field="valid_until">
            <UiInput
              v-model="validUntil"
              label="Vigencia hasta"
              type="date"
              :disabled="saving"
              :error="errors.valid_until"
            />
          </div>
        </div>
      </fieldset>

      <fieldset class="space-y-4" data-form-section="items">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Procedimientos
        </legend>

        <p v-if="itemsError" class="text-xs text-error-500" role="alert">{{ itemsError }}</p>

        <div class="space-y-4">
          <div
            v-for="(item, index) in items"
            :key="index"
            class="space-y-3 rounded-lg border border-hairline p-4"
            data-item-row
          >
            <div class="flex items-start gap-3">
              <div class="min-w-0 flex-1">
                <UiInput
                  v-model="item.description"
                  label="Descripción"
                  placeholder="ej. Limpieza dental"
                  :disabled="saving"
                />
              </div>
              <UiButton
                type="button"
                variant="icon"
                size="sm"
                class="mt-7"
                :aria-label="removeLabel(item)"
                :disabled="saving"
                @click="removeItem(index)"
              >
                <TrashIcon class="h-4 w-4" />
              </UiButton>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <UiInput
                v-model.number="item.quantity"
                label="Cantidad"
                type="number"
                min="1"
                step="1"
                :disabled="saving"
              />
              <UiInput
                v-model.number="item.unit_price"
                label="Precio unitario"
                type="number"
                min="0"
                step="0.01"
                :disabled="saving"
              />
              <div>
                <p class="block text-sm font-medium text-theme-primary mb-2">Total</p>
                <p class="text-base font-semibold text-theme-primary tabular-nums" data-item-total>
                  {{ formatCurrency(rowTotal(item)) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div>
          <UiButton
            type="button"
            variant="secondary"
            size="sm"
            :disabled="saving"
            data-add-item
            @click="addItem"
          >
            <template #icon-left>
              <PlusIcon class="h-4 w-4" />
            </template>
            Agregar procedimiento
          </UiButton>
        </div>
      </fieldset>

      <fieldset class="space-y-4" data-form-section="adjustments">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Descuento e IGV
        </legend>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div data-field="discount_percentage">
            <UiInput
              v-model.number="discountPercentage"
              label="Descuento (%)"
              type="number"
              min="0"
              max="100"
              step="0.01"
              hint="Si es mayor a 0, reemplaza al descuento en soles."
              :disabled="saving"
              :error="errors.discount_percentage"
            />
          </div>
          <div data-field="discount_amount">
            <UiInput
              v-model.number="discountAmount"
              label="Descuento (S/)"
              type="number"
              min="0"
              step="0.01"
              hint="Se aplica solo cuando el descuento en % es 0."
              :disabled="saving"
              :error="errors.discount_amount"
            />
          </div>
          <div data-field="tax_percentage">
            <UiInput
              v-model.number="taxPercentage"
              label="IGV (%)"
              type="number"
              min="0"
              max="100"
              step="0.01"
              hint="Se calcula sobre el subtotal después del descuento."
              :disabled="saving"
              :error="errors.tax_percentage"
            />
          </div>
        </div>

        <p class="text-xs text-theme-secondary" data-adjustments-caption>
          El servidor recalcula el descuento y el IGV al guardar.
        </p>
      </fieldset>

      <section
        class="space-y-3 rounded-lg border border-hairline bg-theme-surface p-4"
        data-form-summary
        aria-label="Resumen del presupuesto"
      >
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">Resumen</h3>
        <dl class="space-y-2 text-sm">
          <div class="flex items-center justify-between">
            <dt class="text-theme-secondary">Subtotal</dt>
            <dd class="font-medium text-theme-primary tabular-nums" data-summary-subtotal>
              {{ formatCurrency(subtotal) }}
            </dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-theme-secondary">Descuento</dt>
            <dd class="font-medium text-theme-primary tabular-nums" data-summary-discount>
              {{ formatCurrency(-discountTotal) }}
            </dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-theme-secondary">IGV</dt>
            <dd class="font-medium text-theme-primary tabular-nums" data-summary-tax>
              {{ formatCurrency(taxTotal) }}
            </dd>
          </div>
          <div class="flex items-center justify-between border-t border-hairline pt-2">
            <dt class="font-semibold text-theme-primary">Total</dt>
            <dd class="text-base font-semibold text-theme-primary tabular-nums" data-summary-total>
              {{ formatCurrency(total) }}
            </dd>
          </div>
        </dl>
      </section>

      <fieldset class="space-y-4" data-form-section="terms">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Términos y condiciones
        </legend>

        <div data-field="terms_conditions" role="group" aria-labelledby="quotation-terms-label">
          <p id="quotation-terms-label" class="block text-sm font-medium text-theme-primary mb-2">
            Términos y condiciones
          </p>
          <RichTextEditor
            v-model="termsConditions"
            placeholder="Términos y condiciones del presupuesto"
            :show-char-count="false"
            :disabled="saving"
          />
          <p v-if="errors.terms_conditions" class="mt-1 text-xs text-error-500" role="alert">
            {{ errors.terms_conditions }}
          </p>
        </div>

        <div data-field="notes">
          <p class="block text-sm font-medium text-theme-primary mb-2">Notas adicionales</p>
          <UiTextarea
            v-model="notes"
            :rows="3"
            aria-label="Notas adicionales"
            placeholder="Notas adicionales del presupuesto"
            :disabled="saving"
            :error="Boolean(errors.notes)"
          />
          <p v-if="errors.notes" class="mt-1 text-xs text-error-500" role="alert">
            {{ errors.notes }}
          </p>
        </div>
      </fieldset>
    </div>

    <template #footer>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="saving"
        data-form-action="cancel"
        @click="emit('close')"
      >
        Cancelar
      </UiButton>
      <UiButton type="button" :loading="saving" data-form-action="submit" @click="onSubmit">
        {{ isEdit ? 'Guardar cambios' : 'Crear presupuesto' }}
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useQuotations } from '../../../composables/useQuotations'
import { formatCurrency } from '../../../composables/useFormatters'
import { calendarDateKey } from '../quotationMeta'
import PatientSelector from '../../../components/ui/PatientSelector.vue'
import TreatmentPlanSelector from '../../../components/ui/TreatmentPlanSelector.vue'
import RichTextEditor from '../../../components/ui/RichTextEditor.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiModal from '../../../components/ui/Modal.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  quotation: { type: Object, default: null },
  isEdit: { type: Boolean, default: false }
})

const emit = defineEmits(['close', 'saved'])

const { createQuotation, updateQuotation } = useQuotations()

const patient = ref(null)
const plan = ref(null)
const quotationDate = ref('')
const validUntil = ref('')
const items = ref([])
const discountPercentage = ref(0)
const discountAmount = ref(0)
const taxPercentage = ref(0)
const termsConditions = ref('')
const notes = ref('')
const errors = ref({})
const submitError = ref('')
const saving = ref(false)

const toNumber = value => {
  const numeric = Number(value)
  return Number.isFinite(numeric) ? numeric : 0
}

const rowTotal = item => toNumber(item?.quantity) * toNumber(item?.unit_price)

const itemDescription = item => String(item?.description ?? '').trim()

const createEmptyItem = () => ({ description: '', quantity: 1, unit_price: 0 })

const dateKeyWithOffset = days => {
  const date = new Date()
  date.setDate(date.getDate() + days)
  return calendarDateKey(date)
}

const removeLabel = item => `Quitar ${itemDescription(item) || 'procedimiento'}`

const subtotal = computed(() => items.value.reduce((total, item) => total + rowTotal(item), 0))

const discountTotal = computed(() => {
  const percentage = toNumber(discountPercentage.value)
  if (percentage > 0) return subtotal.value * (percentage / 100)
  return toNumber(discountAmount.value)
})

const taxTotal = computed(
  () => (subtotal.value - discountTotal.value) * (toNumber(taxPercentage.value) / 100)
)

const total = computed(() => subtotal.value - discountTotal.value + taxTotal.value)

const patientError = computed(() => errors.value.patient_id || errors.value.patient || '')

const itemsError = computed(() => {
  if (errors.value.items) return errors.value.items
  const key = Object.keys(errors.value).find(name => name.startsWith('items.'))
  return key ? errors.value[key] : ''
})

const buildItemsPayload = () =>
  items.value
    .filter(item => itemDescription(item))
    .map(item => ({
      description: itemDescription(item),
      quantity: toNumber(item.quantity),
      unit_price: toNumber(item.unit_price)
    }))

const normalizeServerErrors = rawErrors => {
  if (!rawErrors || typeof rawErrors !== 'object') return {}
  return Object.fromEntries(
    Object.entries(rawErrors).map(([field, message]) => [
      field,
      Array.isArray(message) ? message[0] : message
    ])
  )
}

const validate = () => {
  const nextErrors = {}

  if (!props.isEdit && !patient.value?.id) {
    nextErrors.patient_id = 'Selecciona un paciente.'
  }

  if (!buildItemsPayload().length) {
    nextErrors.items = 'Agrega al menos un procedimiento.'
  }

  if (quotationDate.value && validUntil.value && validUntil.value < quotationDate.value) {
    nextErrors.valid_until = 'La vigencia no puede terminar antes de la fecha de emisión.'
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

const buildPayload = () => {
  const payload = {
    quotation_date: quotationDate.value || null,
    valid_until: validUntil.value || null,
    subtotal: subtotal.value,
    discount_percentage: toNumber(discountPercentage.value),
    discount_amount: toNumber(discountAmount.value),
    tax_percentage: toNumber(taxPercentage.value),
    terms_conditions: termsConditions.value,
    notes: notes.value,
    items: buildItemsPayload()
  }

  if (props.isEdit) return payload

  return {
    patient_id: patient.value.id,
    treatment_plan_id: plan.value?.id ?? null,
    ...payload
  }
}

const onSubmit = async () => {
  if (saving.value) return

  submitError.value = ''
  if (!validate()) return

  saving.value = true
  try {
    const payload = buildPayload()
    const persisted = props.isEdit
      ? await updateQuotation(props.quotation.id, payload)
      : await createQuotation(payload)
    emit('saved', persisted)
  } catch (error) {
    errors.value = normalizeServerErrors(error.response?.data?.errors)
    submitError.value = error.response?.data?.message || 'No pudimos guardar el presupuesto.'
  } finally {
    saving.value = false
  }
}

const addItem = () => {
  items.value.push(createEmptyItem())
}

const removeItem = index => {
  items.value.splice(index, 1)
}

const handlePatientSelected = () => {
  errors.value.patient_id = ''
}

const handlePlanSelected = selectedPlan => {
  if (props.isEdit) return
  const planItems = Array.isArray(selectedPlan?.items) ? selectedPlan.items : []
  if (!planItems.length) return

  items.value = planItems.map(planItem => ({
    description: planItem.procedure_name || planItem.description || '',
    quantity: planItem.quantity,
    unit_price: planItem.unit_cost || planItem.unit_price || 0
  }))
}

const initialize = () => {
  errors.value = {}
  submitError.value = ''

  if (!props.quotation) {
    patient.value = null
    plan.value = null
    quotationDate.value = dateKeyWithOffset(0)
    validUntil.value = dateKeyWithOffset(30)
    items.value = [createEmptyItem()]
    discountPercentage.value = 0
    discountAmount.value = 0
    taxPercentage.value = 0
    termsConditions.value = ''
    notes.value = ''
    return
  }

  const current = props.quotation
  patient.value = current.patient || null
  plan.value = current.treatment_plan || null
  quotationDate.value = calendarDateKey(current.quotation_date) || dateKeyWithOffset(0)
  validUntil.value = calendarDateKey(current.valid_until) || dateKeyWithOffset(30)
  items.value = (Array.isArray(current.items) ? current.items : []).map(item => ({
    description: item.item_name || item.item_description || '',
    quantity: toNumber(item.quantity),
    unit_price: toNumber(item.unit_price)
  }))
  if (!items.value.length) items.value = [createEmptyItem()]
  discountPercentage.value = toNumber(current.discount_percentage)
  discountAmount.value = toNumber(current.discount_amount)
  taxPercentage.value = toNumber(current.tax_percentage)
  termsConditions.value = current.terms_conditions || ''
  notes.value = current.notes || ''
}

watch(() => props.quotation, initialize, { immediate: true })
</script>
