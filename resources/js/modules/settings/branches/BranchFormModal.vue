<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar sucursal' : 'Nueva sucursal'"
    size="lg"
    @close="emit('close')"
  >
    <div class="space-y-6">
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Identificación
        </legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div data-field="code">
            <UiInput
              v-model="form.code"
              label="Código"
              placeholder="SC-LIM-01"
              :disabled="isEdit"
              :error="errors.code"
              :hint="isEdit ? 'El código no se puede modificar una vez creado.' : ''"
            />
          </div>
          <div data-field="name">
            <UiInput
              v-model="form.name"
              label="Nombre de la sucursal"
              placeholder="Sede Central Lima"
              :error="errors.name"
            />
          </div>
        </div>
      </fieldset>

      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Ubicación
        </legend>
        <div data-field="address">
          <UiInput
            v-model="form.address"
            label="Dirección"
            placeholder="Av. / Jr. / Calle, número, distrito"
            :error="errors.address"
          />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div data-field="city">
            <UiInput v-model="form.city" label="Ciudad" placeholder="Lima" :error="errors.city" />
          </div>
          <div data-field="state">
            <UiInput
              v-model="form.state"
              label="Departamento"
              placeholder="Lima"
              :error="errors.state"
            />
          </div>
          <div data-field="country">
            <UiInput
              v-model="form.country"
              label="País"
              placeholder="Perú"
              :error="errors.country"
            />
          </div>
          <div data-field="postal_code">
            <UiInput
              v-model="form.postal_code"
              label="Código postal"
              placeholder="15046"
              :error="errors.postal_code"
            />
          </div>
        </div>
      </fieldset>

      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Contacto
        </legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div data-field="phone">
            <UiInput
              v-model="form.phone"
              label="Teléfono"
              placeholder="+51 1 426-0001"
              :error="errors.phone"
            />
          </div>
          <div data-field="email">
            <UiInput
              v-model="form.email"
              label="Correo electrónico"
              type="email"
              placeholder="sucursal@odontosuite.pe"
              :error="errors.email"
            />
          </div>
        </div>
      </fieldset>

      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Operación
        </legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div data-field="timezone">
            <UiInput
              v-model="form.timezone"
              label="Zona horaria"
              placeholder="America/Lima"
              :error="errors.timezone"
            />
          </div>
          <div data-field="is_active">
            <UiSelect
              v-model="statusValue"
              :options="statusOptions"
              label="Estado"
              placeholder="Seleccionar estado"
            />
            <p class="mt-1 text-xs text-theme-secondary">
              Las sucursales inactivas no aparecen en los selectores de sede ni de apertura de caja.
            </p>
          </div>
        </div>
      </fieldset>

      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Descripción
        </legend>
        <div data-field="description">
          <UiTextarea
            v-model="form.description"
            :rows="3"
            aria-label="Descripción"
            placeholder="Notas internas sobre esta sede (opcional)"
          />
        </div>
      </fieldset>

      <p
        v-if="generalError"
        role="alert"
        class="rounded-[var(--radius-panel)] p-3 text-sm text-systemRed-700 bg-systemRed-50"
        style="border: 1px solid var(--color-hairline)"
      >
        {{ generalError }}
      </p>
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
        {{ isEdit ? 'Actualizar' : 'Crear' }}
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useBranches } from '../../../composables/useBranches'
import UiModal from '../../../components/ui/Modal.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiSelect from '../../../components/ui/Select.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'

const props = defineProps({
  branch: { type: Object, default: null }
})

const emit = defineEmits(['close', 'saved'])

const { createBranch, updateBranch } = useBranches()

const isEdit = computed(() => !!props.branch)

const EMPTY_FORM = {
  code: '',
  name: '',
  address: '',
  city: '',
  state: '',
  country: 'Perú',
  postal_code: '',
  phone: '',
  email: '',
  timezone: 'America/Lima',
  description: '',
  is_active: true
}

const statusOptions = [
  { value: 'active', label: 'Activa' },
  { value: 'inactive', label: 'Inactiva' }
]

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const toForm = branch =>
  branch
    ? {
        code: branch.code || '',
        name: branch.name || '',
        address: branch.address || '',
        city: branch.city || '',
        state: branch.state || '',
        country: branch.country || 'Perú',
        postal_code: branch.postal_code || '',
        phone: branch.phone || '',
        email: branch.email || '',
        timezone: branch.timezone || 'America/Lima',
        description: branch.description || '',
        is_active: !!branch.is_active
      }
    : { ...EMPTY_FORM }

const form = ref(toForm(props.branch))
const errors = ref({})
const generalError = ref('')
const saving = ref(false)

const statusValue = computed({
  get: () => (form.value.is_active ? 'active' : 'inactive'),
  set: value => {
    form.value.is_active = value === 'active'
  }
})

const validate = () => {
  const next = {}
  const code = form.value.code.trim()

  if (!isEdit.value) {
    if (!code) next.code = 'Requerido'
    else if (code.length > 10) next.code = 'Máximo 10 caracteres'
  }

  if (!form.value.name.trim()) next.name = 'Requerido'
  if (!form.value.city.trim()) next.city = 'Requerido'
  if (form.value.postal_code.trim().length > 10) next.postal_code = 'Máximo 10 caracteres'
  if (form.value.phone.trim().length > 20) next.phone = 'Máximo 20 caracteres'

  const email = form.value.email.trim()
  if (email && !EMAIL_PATTERN.test(email)) next.email = 'Email inválido'

  errors.value = next
  return Object.keys(next).length === 0
}

const buildPayload = () => {
  const payload = {
    name: form.value.name.trim(),
    address: form.value.address.trim(),
    city: form.value.city.trim(),
    state: form.value.state.trim(),
    country: form.value.country.trim() || 'Perú',
    postal_code: form.value.postal_code.trim(),
    phone: form.value.phone.trim(),
    email: form.value.email.trim(),
    timezone: form.value.timezone.trim() || 'America/Lima',
    description: form.value.description.trim(),
    is_active: !!form.value.is_active
  }
  if (!isEdit.value) payload.code = form.value.code.trim()
  return payload
}

const onSubmit = async () => {
  if (saving.value) return
  generalError.value = ''
  if (!validate()) return
  saving.value = true
  try {
    const saved = isEdit.value
      ? await updateBranch(props.branch.id, buildPayload())
      : await createBranch(buildPayload())
    emit('saved', saved)
  } catch (error) {
    const fieldErrors = error?.response?.data?.errors
    if (fieldErrors) {
      errors.value = Object.fromEntries(
        Object.entries(fieldErrors).map(([key, value]) => [
          key,
          Array.isArray(value) ? value[0] : String(value)
        ])
      )
    } else {
      generalError.value = error?.response?.data?.message || 'No se pudo guardar la sucursal.'
    }
  } finally {
    saving.value = false
  }
}
</script>
