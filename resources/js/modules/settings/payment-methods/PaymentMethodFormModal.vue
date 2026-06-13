<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar metodo de pago' : 'Nuevo metodo de pago'"
    size="lg"
    @close="emit('close')"
  >
    <form class="space-y-4" @submit.prevent="onSubmit">
      <!-- Codigo y nombre -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Codigo <span class="text-red-500">*</span>
          </label>
          <UiInput
            v-model="form.code"
            :disabled="isEdit || isSystem"
            placeholder="ej. cash, credito, yape"
            :error="errors.code"
            class="w-full"
          />
          <p v-if="isEdit" class="text-xs text-theme-secondary mt-1">
            El codigo no puede modificarse.
          </p>
          <p v-if="isSystem" class="text-xs text-amber-600 mt-1">
            Metodo del sistema: no editable.
          </p>
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Nombre visible <span class="text-red-500">*</span>
          </label>
          <UiInput
            v-model="form.name"
            :disabled="isSystem"
            placeholder="ej. Tarjeta de credito"
            :error="errors.name"
            class="w-full"
          />
        </div>
      </div>

      <!-- Descripcion -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">
          Descripcion
        </label>
        <UiTextarea
          v-model="form.description"
          :disabled="isSystem"
          :rows="2"
          placeholder="Notas sobre este metodo de pago"
          class="w-full"
        />
      </div>

      <!-- Comision + Requiere autorizacion -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Comision (%)
          </label>
          <UiInput
            v-model.number="form.commission_percentage"
            :disabled="isSystem"
            type="number"
            step="0.01"
            min="0"
            max="100"
            placeholder="0"
            :error="errors.commission_percentage"
            class="w-full"
          />
          <p class="text-xs text-theme-secondary mt-1">
            Se agrega al subtotal del cobro (visible para el paciente).
          </p>
        </div>
        <div class="flex items-start gap-3 pt-6">
          <input
            id="requires_authorization"
            v-model="form.requires_authorization"
            :disabled="isSystem"
            type="checkbox"
            class="w-4 h-4 mt-1 text-accent border-theme rounded focus:ring-accent"
          >
          <div>
            <label for="requires_authorization" class="text-sm text-theme-primary font-medium">
              Requiere autorizacion
            </label>
            <p class="text-xs text-theme-secondary">
              Si se requiere voucher o codigo de operacion.
            </p>
          </div>
        </div>
      </div>

      <!-- Pasarela de pago (preparado para Sprint 3) -->
      <div v-if="!isSystem || form.gateway_type" class="border-t border-theme pt-4 mt-4">
        <h4 class="text-sm font-semibold text-theme-primary mb-3">Pasarela de pago (opcional)</h4>
        <p class="text-xs text-theme-secondary mb-3">
          Configura Mercado Pago u otra pasarela para cobrar en linea. El cobro manual siempre estara disponible sin pasarela.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-theme-primary mb-1">
              Pasarela
            </label>
            <select
              v-model="form.gateway_type"
              :disabled="isSystem"
              class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
            >
              <option value="manual">Manual (sin pasarela)</option>
              <option value="mercadopago">Mercado Pago</option>
            </select>
          </div>
          <div v-if="form.gateway_type === 'mercadopago'" class="space-y-2">
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">
                Access Token
              </label>
              <UiInput
                v-model="gatewayConfig.access_token"
                type="password"
                placeholder="TEST-... o APP_USR-..."
                class="w-full"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">
                Public Key
              </label>
              <UiInput
                v-model="gatewayConfig.public_key"
                placeholder="TEST-... o APP_USR-..."
                class="w-full"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Estado (solo visible en edit) -->
      <div v-if="isEdit" class="border-t border-theme pt-4 mt-4">
        <label class="block text-sm font-medium text-theme-primary mb-1">
          Estado
        </label>
        <select
          v-model="form.is_active"
          :disabled="isSystem"
          class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        >
          <option :value="true">Activo</option>
          <option :value="false">Inactivo</option>
        </select>
        <p v-if="isSystem" class="text-xs text-amber-600 mt-1">
          Los metodos del sistema no pueden desactivarse desde esta UI.
        </p>
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton variant="secondary" :disabled="saving" @click="emit('close')">
          Cancelar
        </UiButton>
        <UiButton v-if="!isSystem" :disabled="saving" @click="onSubmit">
          {{ saving ? 'Guardando...' : isEdit ? 'Actualizar' : 'Crear' }}
        </UiButton>
        <UiButton v-else variant="secondary" disabled>
          Metodo del sistema
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { usePaymentMethods } from '../../../composables/usePaymentMethods'
import { useToast } from '../../../composables/useToast'
import UiModal from '../../../components/ui/Modal.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'

const props = defineProps({
  method: { type: Object, default: null }
})

const emit = defineEmits(['close', 'saved'])

const { createMethod, updateMethod } = usePaymentMethods()
const toast = useToast()

const isEdit = computed(() => !!props.method)
const isSystem = computed(() => !!props.method?.is_system)

const gatewayConfig = reactive({
  access_token: '',
  public_key: ''
})

const getEmptyForm = () => ({
  code: '',
  name: '',
  description: '',
  gateway_type: 'manual',
  gateway_config: null,
  commission_percentage: 0,
  requires_authorization: false,
  allows_change: true,
  is_active: true,
  is_system: false
})

const form = ref(getEmptyForm())
const errors = ref({})
const saving = ref(false)

onMounted(() => {
  if (props.method) {
    form.value = {
      code: props.method.code || '',
      name: props.method.name || '',
      description: props.method.description || '',
      gateway_type: props.method.gateway_type || 'manual',
      gateway_config: null, // se setea via gatewayConfig reactive
      commission_percentage: props.method.commission_percentage ?? 0,
      requires_authorization: !!props.method.requires_authorization,
      allows_change: props.method.allows_change ?? true,
      is_active: !!props.method.is_active,
      is_system: !!props.method.is_system
    }
    // Pre-llenar credenciales si existen (solo si tiene has_gateway_config)
    if (props.method.has_gateway_config && props.method.gateway_type === 'mercadopago') {
      // No podemos desencriptar en el frontend; mostramos placeholders
      gatewayConfig.access_token = ''
      gatewayConfig.public_key = ''
    }
  }
})

const validate = () => {
  const e = {}
  if (!isEdit.value && !form.value.code?.trim()) e.code = 'Requerido'
  if (!form.value.name?.trim()) e.name = 'Requerido'
  if (form.value.commission_percentage < 0) e.commission_percentage = 'Debe ser >= 0'
  errors.value = e
  return Object.keys(e).length === 0
}

const onSubmit = async () => {
  if (!validate()) return
  saving.value = true
  try {
    const payload = { ...form.value }

    // Preparar gateway_config si se llenaron credenciales
    if (payload.gateway_type === 'mercadopago' && gatewayConfig.access_token) {
      payload.gateway_config = {
        access_token: gatewayConfig.access_token,
        public_key: gatewayConfig.public_key
      }
    }

    // En edicion, no enviamos el code (inmutable)
    if (isEdit.value) {
      delete payload.code
      delete payload.is_system // no editable via API
    }

    let result
    if (isEdit.value) {
      result = await updateMethod(props.method.id, payload)
    } else {
      result = await createMethod(payload)
    }
    emit('saved', result)
  } catch (err) {
    if (err.response?.data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(err.response.data.errors).map(([k, v]) => [k, v[0]])
      )
    } else {
      toast.error(err.response?.data?.message || 'Error al guardar el metodo de pago')
    }
  } finally {
    saving.value = false
  }
}
</script>
