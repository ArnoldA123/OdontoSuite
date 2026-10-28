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

      <AccountingLockNotice
        v-if="lockBlocked"
        v-model="lockReason"
        :message="lockMessage"
        :error="lockReasonError"
        :loading="saving"
        @retry="retryWithOverride"
      />

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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div data-field="payer_id">
            <UiSelect
              v-model="payerId"
              :options="payerOptions"
              label="Convenio"
              placeholder="Sin convenio"
              :disabled="saving"
              :error="errors.payer_id"
            />
          </div>
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
              <div data-item-price>
                <UiInput
                  v-model.number="item.unit_price"
                  label="Precio unitario"
                  type="number"
                  min="0"
                  step="0.01"
                  :disabled="saving || isCatalogLine(item)"
                />
                <p
                  v-if="isCatalogLine(item)"
                  class="mt-1 text-xs text-theme-secondary"
                  data-item-price-source
                >
                  {{ priceSourceCopy(item) }}
                </p>
              </div>
              <div>
                <p class="block text-sm font-medium text-theme-primary mb-2">Total</p>
                <p class="text-base font-semibold text-theme-primary tabular-nums" data-item-total>
                  {{ formatCurrency(rowTotal(item)) }}
                </p>
              </div>
            </div>

            <!-- #293 S1/S2: the line may name a catalog procedure and the site
                 the procedure applies to. The description stays editable. -->
            <div class="space-y-3 border-t border-hairline pt-3" :data-item-extra="index">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-medium text-theme-secondary">
                  Catálogo y sitio de la atención
                </p>
                <UiButton
                  type="button"
                  variant="ghost"
                  size="sm"
                  :disabled="saving"
                  :data-item-catalog-toggle="index"
                  @click="toggleCatalog(item)"
                >
                  {{ catalogItem === item ? 'Ocultar catálogo' : 'Elegir del catálogo' }}
                </UiButton>
              </div>

              <p
                v-if="item.procedure_catalog_id"
                class="text-xs text-theme-secondary"
                :data-item-catalog="index"
              >
                Procedimiento del catálogo vinculado. El precio lo fija el tarifario; la descripción
                sigue siendo editable.
              </p>

              <div
                v-if="catalogItem === item"
                class="rounded-lg border border-hairline p-2"
                :data-item-catalog-panel="index"
              >
                <ProcedureCatalogPicker
                  :model-value="item.procedure_catalog_id"
                  @select="procedure => onCatalogPick(item, procedure)"
                />
              </div>

              <ProcedureSiteFields
                :site-type="item.site_type"
                :model-value="siteOf(item)"
                :disabled="saving"
                @update:model-value="site => applySite(item, site)"
              />
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
              hint="Incluido en el precio: se desglosa del total con descuento. Vacío aplica la tasa configurada."
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
          <div v-if="taxTotal > 0" class="flex items-center justify-between">
            <dt class="text-theme-secondary">Base imponible</dt>
            <dd class="font-medium text-theme-primary tabular-nums" data-summary-taxable-base>
              {{ formatCurrency(taxableBase) }}
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
import { computed, onMounted, ref, watch } from 'vue'
import { useQuotations } from '../../../composables/useQuotations'
import { useApi } from '../../../composables/useApi'
import { useAccountingLock } from '../../../composables/useAccountingLock'
import { formatCurrency } from '../../../composables/useFormatters'
import {
  normalizeValidationErrors,
  pickValidationMessage
} from '../../../composables/validationErrors'
import { calendarDateKey } from '../quotationMeta'
import {
  FALLBACK_TAX_PERCENTAGE,
  breakDown,
  effectiveTaxRate,
  priceSourceLabel
} from '../quotationPricing'
import {
  requiredSiteField,
  siteRequirementMessage,
  siteTypeOf
} from '../../procedure-catalog/procedureSite'
import PatientSelector from '../../../components/ui/PatientSelector.vue'
import AccountingLockNotice from '../../../components/money/AccountingLockNotice.vue'
import ProcedureCatalogPicker from '../../../components/procedures/ProcedureCatalogPicker.vue'
import ProcedureSiteFields from '../../../components/procedures/ProcedureSiteFields.vue'
import TreatmentPlanSelector from '../../../components/ui/TreatmentPlanSelector.vue'
import RichTextEditor from '../../../components/ui/RichTextEditor.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiModal from '../../../components/ui/Modal.vue'
import UiSelect from '../../../components/ui/Select.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  quotation: { type: Object, default: null },
  isEdit: { type: Boolean, default: false }
})

const emit = defineEmits(['close', 'saved'])

const { createQuotation, updateQuotation } = useQuotations()
const { get } = useApi()
const {
  blocked: lockBlocked,
  message: lockMessage,
  reason: lockReason,
  reasonError: lockReasonError,
  reset: resetLock,
  detect: detectLock,
  applyValidationErrors: applyLockValidationErrors,
  authorize: authorizeLock
} = useAccountingLock()

const patient = ref(null)
const plan = ref(null)
const quotationDate = ref('')
const validUntil = ref('')
const payerId = ref(null)
const payers = ref([])
const items = ref([])
const discountPercentage = ref(0)
const discountAmount = ref(0)
const taxPercentage = ref(null)
const termsConditions = ref('')
const notes = ref('')
const errors = ref({})
const submitError = ref('')
const saving = ref(false)

// #414 S1: the pricing context the API resolved for this estimate — the billing
// policy rate plus the tariff of each catalog line. It only paints numbers; the
// writer remains the authority and freezes them when the document is saved.
const pricingContext = ref(null)

const toNumber = value => {
  const numeric = Number(value)
  return Number.isFinite(numeric) ? numeric : 0
}

const rowTotal = item => toNumber(item?.quantity) * toNumber(item?.unit_price)

// #414 S1: a line that names a catalog procedure carries no price of its own —
// the server discards it and freezes the resolved tariff — so the form shows the
// resolved number with its origin instead of offering it as editable.
const isCatalogLine = item => Boolean(item?.procedure_catalog_id)

const priceSourceCopy = item => {
  const label = priceSourceLabel(item?.price_source)

  return label ? `Precio del tarifario: ${label}` : 'Precio del tarifario'
}

const itemDescription = item => String(item?.description ?? '').trim()

const payerOptions = computed(() => [
  { value: null, label: 'Sin convenio' },
  ...payers.value.map(payer => ({ value: payer.id, label: payer.name }))
])

// #293 S1: the catalog picker and the site fields are optional per line, so the
// panel opens on demand and the free-text description keeps working.
const catalogItem = ref(null)

const toggleCatalog = item => {
  catalogItem.value = catalogItem.value === item ? null : item
}

const siteOf = item => ({
  dental_piece_id: item.dental_piece_id ?? null,
  tooth_surface_id: item.tooth_surface_id ?? null,
  site_code: item.site_code ?? null
})

const applySite = (item, site) => {
  item.dental_piece_id = site?.dental_piece_id ?? null
  item.tooth_surface_id = site?.tooth_surface_id ?? null
  item.site_code = site?.site_code ?? null
}

const onCatalogPick = (item, procedure) => {
  if (!procedure) return

  const previousRequirement = requiredSiteField(item.site_type)

  item.procedure_catalog_id = procedure.id
  item.description = procedure.name
  item.unit_price = Number(procedure.default_cost ?? 0)
  item.price_source = null
  item.site_type = procedure.site_type ?? 'none'

  if (requiredSiteField(item.site_type) !== previousRequirement) {
    item.dental_piece_id = null
    item.tooth_surface_id = null
    item.site_code = null
  }

  catalogItem.value = null
  refreshPricing()
}

const catalogProcedureIds = () => [
  ...new Set(
    items.value
      .map(item => Number(item.procedure_catalog_id))
      .filter(id => Number.isInteger(id) && id > 0)
  )
]

const applyResolvedPrices = context => {
  const prices = context?.prices ?? {}

  items.value.forEach(item => {
    const id = Number(item.procedure_catalog_id)
    if (!Number.isInteger(id) || id <= 0) return

    const resolved = prices[id] ?? prices[String(id)]
    if (!resolved) return

    item.unit_price = toNumber(resolved.unit_price)
    item.price_source = resolved.source ?? null
  })
}

let pricingRequest = 0

// #414 S1: the summary is the server's arithmetic, so the form asks the API for
// the tariff of every catalog line on this estimate's issue date. When the
// context cannot be read the form keeps the numbers it already paints and the
// billing default: a pricing read never blocks the quotation.
const refreshPricing = async () => {
  // A free-text-only estimate has no tariff to resolve, but it still reads the
  // policy rate the server will apply: the form never invents that number.
  const procedureIds = catalogProcedureIds()
  const request = ++pricingRequest

  try {
    const response = await get('/api/quotations/pricing', {
      params: {
        procedure_ids: procedureIds,
        payer_id: payerId.value || null,
        quotation_date: quotationDate.value || null,
        treatment_plan_id: plan.value?.id ?? null
      }
    })

    // A slower response never overwrites a newer one.
    if (request !== pricingRequest) return

    pricingContext.value = response?.data ?? null
    applyResolvedPrices(pricingContext.value)
  } catch {
    // Keeps the numbers already on screen and the policy fallback.
  }
}

const loadPayers = async () => {
  try {
    const response = await get('/api/payers/active')
    payers.value = Array.isArray(response?.data) ? response.data : []
  } catch {
    payers.value = []
  }
}

// A site-requiring procedure without its site is stopped locally; the API stays
// the authority and its 422 is still shown.
const missingSiteMessage = () => {
  const missing = items.value.find(item => {
    const field = requiredSiteField(item.site_type)
    return field !== null && !item[field]
  })

  if (!missing) return ''

  return siteRequirementMessage(
    itemDescription(missing) || missing.procedure_name,
    missing.site_type
  )
}

const createEmptyItem = () => ({
  description: '',
  quantity: 1,
  unit_price: 0,
  procedure_catalog_id: null,
  site_type: 'none',
  dental_piece_id: null,
  tooth_surface_id: null,
  site_code: null
})

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

// The rate the document will be written with: an untouched field is not a zero,
// it is the billing policy the API published.
const policyTaxRate = computed(() => {
  const declared = pricingContext.value?.tax_percentage

  if (declared === null || declared === undefined || declared === '') {
    return FALLBACK_TAX_PERCENTAGE
  }

  const rate = Number(declared)

  return Number.isFinite(rate) ? rate : FALLBACK_TAX_PERCENTAGE
})

// #289/#414: the price already includes the IGV, so the preview decomposes the
// rate out of the net amount exactly like App\Support\QuotationTax does.
const breakdown = computed(() =>
  breakDown(
    subtotal.value,
    discountTotal.value,
    effectiveTaxRate(taxPercentage.value, policyTaxRate.value)
  )
)

const taxableBase = computed(() => breakdown.value.subtotal)
const taxTotal = computed(() => breakdown.value.taxAmount)
const total = computed(() => breakdown.value.totalAmount)

const patientError = computed(() => errors.value.patient_id || errors.value.patient || '')

const itemsError = computed(() => {
  if (errors.value.items) return errors.value.items
  const key = Object.keys(errors.value).find(name => name.startsWith('items.'))
  return key ? errors.value[key] : ''
})

const buildItemsPayload = () =>
  items.value
    .filter(item => itemDescription(item))
    .map(item => {
      const payload = {
        description: itemDescription(item),
        quantity: toNumber(item.quantity),
        unit_price: toNumber(item.unit_price)
      }

      // #293 S2: the line names the catalog procedure and its site only when it
      // declares them; a free-text line keeps the payload it always had.
      if (item.procedure_catalog_id) payload.procedure_catalog_id = item.procedure_catalog_id
      if (item.dental_piece_id) payload.dental_piece_id = item.dental_piece_id
      if (item.tooth_surface_id) payload.tooth_surface_id = item.tooth_surface_id
      if (item.site_code) payload.site_code = item.site_code

      return payload
    })

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

  const siteMessage = missingSiteMessage()
  if (siteMessage) {
    nextErrors._site = siteMessage
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

const buildPayload = () => {
  const payload = {
    quotation_date: quotationDate.value || null,
    valid_until: validUntil.value || null,
    payer_id: payerId.value || null,
    subtotal: subtotal.value,
    discount_percentage: toNumber(discountPercentage.value),
    discount_amount: toNumber(discountAmount.value),
    terms_conditions: termsConditions.value,
    notes: notes.value,
    items: buildItemsPayload()
  }

  // #289: an untouched rate is not declared, so the server applies the billing
  // policy instead of the form inventing a 0.
  if (taxPercentage.value !== null && taxPercentage.value !== '') {
    payload.tax_percentage = toNumber(taxPercentage.value)
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
  if (!validate()) {
    submitError.value = errors.value._site || ''
    return
  }

  await performSubmit(null)
}

const performSubmit = async override => {
  saving.value = true
  try {
    const payload = { ...buildPayload(), ...override }
    const persisted = props.isEdit
      ? await updateQuotation(props.quotation.id, payload)
      : await createQuotation(payload)
    resetLock()
    emit('saved', persisted)
  } catch (error) {
    const data = error.response?.data

    if (detectLock(error)) return
    if (applyLockValidationErrors(data?.errors)) return

    errors.value = normalizeValidationErrors(data?.errors)
    submitError.value = pickValidationMessage(data, 'No pudimos guardar el presupuesto.')
  } finally {
    saving.value = false
  }
}

const retryWithOverride = async () => {
  const override = authorizeLock()
  if (!override) return

  submitError.value = ''
  await performSubmit(override)
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
    unit_price: planItem.unit_cost || planItem.unit_price || 0,
    procedure_catalog_id: planItem.procedure_catalog_id ?? null,
    price_source: planItem.price_source ?? null,
    // The plan line declares the site its procedure demanded, so the estimate
    // inherits it and keeps the same applicability.
    site_type: siteTypeOf(planItem),
    dental_piece_id: planItem.dental_piece_id ?? null,
    tooth_surface_id: planItem.tooth_surface_id ?? null,
    site_code: planItem.site_code ?? null
  }))
}

const initialize = () => {
  errors.value = {}
  submitError.value = ''
  resetLock()

  if (!props.quotation) {
    patient.value = null
    plan.value = null
    quotationDate.value = dateKeyWithOffset(0)
    validUntil.value = dateKeyWithOffset(30)
    payerId.value = null
    items.value = [createEmptyItem()]
    discountPercentage.value = 0
    discountAmount.value = 0
    taxPercentage.value = null
    termsConditions.value = ''
    notes.value = ''
    return
  }

  const current = props.quotation
  patient.value = current.patient || null
  plan.value = current.treatment_plan || null
  quotationDate.value = calendarDateKey(current.quotation_date) || dateKeyWithOffset(0)
  validUntil.value = calendarDateKey(current.valid_until) || dateKeyWithOffset(30)
  payerId.value = current.payer_id ?? null
  items.value = (Array.isArray(current.items) ? current.items : []).map(item => ({
    description: item.item_name || item.item_description || item.description || '',
    quantity: toNumber(item.quantity),
    unit_price: toNumber(item.unit_price),
    procedure_catalog_id: item.procedure_catalog_id ?? null,
    price_source: item.price_source ?? null,
    site_type: siteTypeOf(item),
    dental_piece_id: item.dental_piece_id ?? null,
    tooth_surface_id: item.tooth_surface_id ?? null,
    site_code: item.site_code ?? null
  }))
  if (!items.value.length) items.value = [createEmptyItem()]
  discountPercentage.value = toNumber(current.discount_percentage)
  discountAmount.value = toNumber(current.discount_amount)
  // An untouched rate stays untouched: an absent one is the billing policy, not
  // an exempt document (#414 S1).
  taxPercentage.value =
    current.tax_percentage === null ||
    current.tax_percentage === undefined ||
    current.tax_percentage === ''
      ? null
      : toNumber(current.tax_percentage)
  termsConditions.value = current.terms_conditions || ''
  notes.value = current.notes || ''
}

watch(() => props.quotation, initialize, { immediate: true })

// #414 S1: the resolved tariff changes with the convenio, the issue date, the
// plan and the procedure of each catalog line.
watch(payerId, refreshPricing)
watch(quotationDate, refreshPricing)
watch(() => plan.value?.id, refreshPricing)

onMounted(() => {
  loadPayers()
  refreshPricing()
})
</script>
