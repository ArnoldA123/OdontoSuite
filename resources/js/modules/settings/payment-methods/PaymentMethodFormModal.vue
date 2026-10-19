<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar método de pago' : 'Nuevo método de pago'"
    size="lg"
    @close="emit('close')"
  >
    <div class="space-y-6 bg-canvas" :data-payment-method-form="isEdit ? 'edit' : 'create'">
      <fieldset class="space-y-4" data-form-section="identification">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Identificación
        </legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div data-field="code">
            <UiInput
              v-model="form.code"
              label="Código"
              required
              :disabled="isEdit || saving"
              placeholder="ej. yape"
              :hint="codeHint"
              :error="errors.code"
              class="w-full"
            />
            <UiStatusBadge
              v-if="isSystem"
              variant="warning"
              label="Sistema"
              size="sm"
              class="mt-2"
            />
          </div>
          <div data-field="name">
            <UiInput
              v-model="form.name"
              label="Nombre visible"
              required
              :disabled="saving"
              placeholder="ej. Yape"
              :error="errors.name"
              class="w-full"
            />
          </div>
        </div>
      </fieldset>

      <fieldset class="space-y-4" data-form-section="billing">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Cobro
        </legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div data-field="commission_percentage">
            <UiInput
              v-model.number="form.commission_percentage"
              label="Comisión (%)"
              type="number"
              step="0.01"
              min="0"
              max="100"
              :disabled="saving"
              hint="Se agrega al subtotal del cobro (visible para el paciente)"
              :error="errors.commission_percentage"
              class="w-full"
            />
          </div>
          <div data-field="status">
            <p class="block text-sm font-medium text-theme-primary mb-1">Estado</p>
            <UiSelect
              v-model="statusValue"
              :options="STATUS_FORM_OPTIONS"
              label="Estado"
              placeholder="Seleccionar estado"
              :disabled="saving"
              :error="errors.is_active"
            />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex items-start gap-3" data-field="requires_authorization">
            <input
              id="requires_authorization"
              v-model="form.requires_authorization"
              type="checkbox"
              :disabled="saving"
              class="w-4 h-4 mt-1 text-systemBlue-600 border-hairline rounded"
            />
            <div>
              <label for="requires_authorization" class="text-sm font-medium text-theme-primary">
                Requiere autorización
              </label>
              <p class="text-xs text-theme-secondary">
                Si se requiere voucher o código de operación.
              </p>
            </div>
          </div>
          <div class="flex items-start gap-3" data-field="allows_change">
            <input
              id="allows_change"
              v-model="form.allows_change"
              type="checkbox"
              :disabled="saving"
              class="w-4 h-4 mt-1 text-systemBlue-600 border-hairline rounded"
            />
            <div>
              <label for="allows_change" class="text-sm font-medium text-theme-primary">
                Permite cambio
              </label>
              <p class="text-xs text-theme-secondary">
                Si el cobro puede cambiar de método de pago después de registrarse.
              </p>
            </div>
          </div>
        </div>
        <p v-if="isSystem" class="text-xs text-theme-secondary" data-system-notice>
          Los métodos del sistema se pueden desactivar, pero no eliminar.
        </p>
      </fieldset>

      <fieldset class="space-y-4" data-form-section="gateway">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Pasarela
        </legend>
        <div data-field="gateway_type">
          <p class="block text-sm font-medium text-theme-primary mb-1">Tipo de pasarela</p>
          <UiSelect
            v-model="form.gateway_type"
            :options="GATEWAY_TYPE_OPTIONS"
            label="Tipo de pasarela"
            placeholder="Seleccionar pasarela"
            :disabled="saving"
            :error="errors.gateway_type"
          />
          <p class="text-xs text-theme-secondary mt-1">
            El cobro manual siempre está disponible sin pasarela.
          </p>
        </div>
        <div
          v-if="gatewayEnabled"
          class="space-y-4 rounded-lg border border-hairline p-4"
          data-redacted="true"
        >
          <div data-field="access_token">
            <UiInput
              v-model="gatewayConfig.access_token"
              type="password"
              label="Access Token"
              autocomplete="off"
              :disabled="saving"
              :placeholder="credentialPlaceholder"
              class="w-full"
            />
          </div>
          <div data-field="public_key">
            <UiInput
              v-model="gatewayConfig.public_key"
              label="Public Key"
              autocomplete="off"
              :disabled="saving"
              :placeholder="credentialPlaceholder"
              class="w-full"
            />
          </div>
          <p class="text-xs text-theme-secondary">
            Se guardan cifradas y no se vuelven a mostrar. Ingresa el Access Token para
            configurarlas o actualizarlas.
          </p>
          <p v-if="errors.credentials" class="text-xs text-error-500" data-credential-error>
            {{ errors.credentials }}
          </p>
        </div>
      </fieldset>

      <fieldset class="space-y-3" data-form-section="description">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Descripción
        </legend>
        <div data-field="description">
          <UiTextarea
            v-model="form.description"
            :rows="3"
            aria-label="Descripción"
            placeholder="Notas sobre este método de pago"
            :disabled="saving"
          />
          <p v-if="errors.description" class="text-xs text-error-500">
            {{ errors.description }}
          </p>
        </div>
      </fieldset>
    </div>

    <template #footer>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="saving"
        data-modal-cancel
        @click="emit('close')"
      >
        Cancelar
      </UiButton>
      <UiButton type="button" :loading="saving" data-modal-submit @click="onSubmit">
        {{ isEdit ? 'Actualizar método' : 'Crear método' }}
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { usePaymentMethods } from '../../../composables/usePaymentMethods'
import { useToast } from '../../../composables/useToast'
import { GATEWAY_TYPE_OPTIONS, STATUS_FORM_OPTIONS, hasGateway } from './paymentMethodMeta'
import UiButton from '../../../components/ui/Button.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiModal from '../../../components/ui/Modal.vue'
import UiSelect from '../../../components/ui/Select.vue'
import UiStatusBadge from '../../../components/ui/StatusBadge.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'

const props = defineProps({
  method: { type: Object, default: null }
})

const emit = defineEmits(['close', 'saved'])

const { createMethod, updateMethod } = usePaymentMethods()
const toast = useToast()

const isEdit = computed(() => Boolean(props.method))
const isSystem = computed(() => Boolean(props.method?.is_system))
const hasStoredCredentials = computed(() => Boolean(props.method?.has_gateway_config))

const commissionFrom = method => {
  const value = Number(method?.commission_percentage ?? 0)
  return Number.isFinite(value) ? value : 0
}

const createFormState = method => ({
  code: method?.code || '',
  name: method?.name || '',
  description: method?.description || '',
  gateway_type: method?.gateway_type || 'manual',
  commission_percentage: commissionFrom(method),
  requires_authorization: Boolean(method?.requires_authorization),
  allows_change: method ? Boolean(method.allows_change) : true,
  is_active: method ? Boolean(method.is_active) : true
})

const form = reactive(createFormState(props.method))
const gatewayConfig = reactive({
  access_token: '',
  public_key: ''
})
const errors = ref({})
const saving = ref(false)

const gatewayEnabled = computed(() => hasGateway(form.gateway_type))

const statusValue = computed({
  get: () => (form.is_active ? 'active' : 'inactive'),
  set: value => {
    form.is_active = value === 'active'
  }
})

const codeHint = computed(() =>
  isEdit.value
    ? 'El código no puede modificarse'
    : 'Identificador corto en minúsculas, sin espacios. Por ejemplo: yape.'
)

const credentialPlaceholder = computed(() =>
  hasStoredCredentials.value ? '••••••' : 'TEST-... o APP_USR-...'
)

const commissionValue = () => {
  const raw = form.commission_percentage
  if (raw === '' || raw === null || raw === undefined) return 0
  return Number(raw)
}

const validate = () => {
  const nextErrors = {}

  if (!isEdit.value && !form.code.trim()) {
    nextErrors.code = 'Ingresa el código del método.'
  }
  if (!form.name.trim()) {
    nextErrors.name = 'Ingresa el nombre del método.'
  }

  const commission = commissionValue()
  if (!Number.isFinite(commission) || commission < 0 || commission > 100) {
    nextErrors.commission_percentage = 'Ingresa una comisión entre 0 y 100.'
  }

  if (
    form.gateway_type === 'mercadopago' &&
    gatewayConfig.public_key.trim() &&
    !gatewayConfig.access_token.trim()
  ) {
    nextErrors.credentials = 'Ingresa el Access Token para actualizar las credenciales.'
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

const buildPayload = () => {
  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    gateway_type: form.gateway_type,
    commission_percentage: commissionValue(),
    requires_authorization: form.requires_authorization,
    allows_change: form.allows_change,
    is_active: form.is_active
  }

  if (!isEdit.value) {
    payload.code = form.code.trim()
  }

  if (form.gateway_type === 'mercadopago' && gatewayConfig.access_token.trim()) {
    payload.gateway_config = {
      access_token: gatewayConfig.access_token.trim(),
      public_key: gatewayConfig.public_key.trim() || null
    }
  }

  return payload
}

const applyServerErrors = error => {
  const responseErrors = error.response?.data?.errors

  if (responseErrors && typeof responseErrors === 'object') {
    const mapped = Object.fromEntries(
      Object.entries(responseErrors).map(([field, messages]) => [
        field,
        Array.isArray(messages) ? messages[0] : messages
      ])
    )
    const credentialKey = Object.keys(mapped).find(field => field.startsWith('gateway_config'))
    if (!mapped.credentials && credentialKey) {
      mapped.credentials = mapped[credentialKey]
    }
    errors.value = mapped
    return
  }

  toast.error(error.response?.data?.message || 'No se pudo guardar el método de pago.')
}

const onSubmit = async () => {
  if (saving.value) return
  if (!validate()) return

  saving.value = true
  try {
    const payload = buildPayload()
    const result = isEdit.value
      ? await updateMethod(props.method.id, payload)
      : await createMethod(payload)
    emit('saved', result)
  } catch (error) {
    applyServerErrors(error)
  } finally {
    saving.value = false
  }
}
</script>
