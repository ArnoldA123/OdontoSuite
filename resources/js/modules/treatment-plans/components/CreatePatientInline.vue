<template>
  <div
    data-create-patient
    class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm sm:items-center"
    @click.self="handleClose"
  >
    <div
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-[var(--radius-panel)]"
      style="
        background: var(--color-surface-elevated);
        border: 1px solid var(--color-hairline);
        box-shadow: var(--elevation-2);
      "
      @click.stop
    >
      <header
        class="flex items-start justify-between gap-4 p-5"
        style="border-bottom: 1px solid var(--color-hairline)"
      >
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Paciente</p>
          <h3 :id="titleId" class="mt-1 text-xl font-semibold text-label">Nuevo paciente</h3>
        </div>

        <UiButton variant="icon" size="sm" aria-label="Cerrar" @click="handleClose">
          <XMarkIcon class="h-5 w-5" aria-hidden="true" />
        </UiButton>
      </header>

      <form class="flex min-h-0 flex-1 flex-col" novalidate @submit.prevent="handleSubmit">
        <div class="flex-1 space-y-4 overflow-y-auto p-5" style="background: var(--color-canvas)">
          <div ref="firstField" data-inline-field="first_name">
            <UiInput
              v-model="form.first_name"
              label="Nombres"
              required
              placeholder="Ej: Juan Carlos"
              :error="errors.first_name"
            />
          </div>

          <div data-inline-field="last_name">
            <UiInput
              v-model="form.last_name"
              label="Apellidos"
              required
              placeholder="Ej: Pérez Gómez"
              :error="errors.last_name"
            />
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div data-inline-field="document_number">
              <UiInput
                v-model="form.document_number"
                label="DNI / Documento"
                placeholder="Opcional"
                maxlength="20"
              />
            </div>

            <div data-inline-field="phone">
              <UiInput
                v-model="form.phone"
                type="tel"
                label="Teléfono"
                placeholder="+51 999 999 999"
              />
            </div>
          </div>

          <div data-inline-field="email">
            <UiInput
              v-model="form.email"
              type="email"
              label="Email"
              placeholder="opcional@ejemplo.com"
              :error="errors.email"
            />
          </div>

          <div data-inline-field="birth_date">
            <UiInput v-model="form.birth_date" type="date" label="Fecha de nacimiento" />
          </div>

          <div
            v-if="generalError"
            role="alert"
            class="flex items-center gap-3 rounded-[var(--radius-panel)] bg-systemRed-50 p-4 text-sm text-systemRed-700"
            style="border: 1px solid var(--color-hairline)"
          >
            <ExclamationCircleIcon class="h-5 w-5 flex-shrink-0" aria-hidden="true" />
            <span>{{ generalError }}</span>
          </div>
        </div>

        <footer
          class="flex items-center justify-end gap-2 p-4"
          style="
            background: var(--color-surface-elevated);
            border-top: 1px solid var(--color-hairline);
          "
        >
          <UiButton
            variant="secondary"
            type="button"
            :disabled="loading"
            data-create-patient-cancel
            @click="handleClose"
          >
            Cancelar
          </UiButton>
          <UiButton variant="primary" type="submit" :loading="loading" data-create-patient-submit>
            Crear paciente
          </UiButton>
        </footer>
      </form>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import { ExclamationCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'created'])

const { post } = useApi()
const toast = useToast()

const titleId = `create-patient-inline-title-${Math.random().toString(36).slice(2, 8)}`

const loading = ref(false)
const generalError = ref('')
const errors = ref({})
const firstField = ref(null)

const form = ref({
  first_name: '',
  last_name: '',
  document_number: '',
  phone: '',
  email: '',
  birth_date: ''
})

const firstMessage = value => (Array.isArray(value) ? value[0] : value)

const handleSubmit = async () => {
  if (loading.value) return

  errors.value = {}
  generalError.value = ''

  if (!form.value.first_name.trim()) {
    errors.value.first_name = 'Los nombres son obligatorios'
  }
  if (!form.value.last_name.trim()) {
    errors.value.last_name = 'Los apellidos son obligatorios'
  }
  if (form.value.email && !/^\S+@\S+\.\S+$/.test(form.value.email)) {
    errors.value.email = 'Email no válido'
  }
  if (Object.keys(errors.value).length > 0) return

  loading.value = true
  try {
    const response = await post('/api/patients', form.value)
    toast.success('Paciente creado exitosamente')
    emit('created', response.data)
  } catch (err) {
    handleError(err)
  } finally {
    loading.value = false
  }
}

const handleError = err => {
  const data = err?.response?.data

  if (data?.errors && typeof data.errors === 'object') {
    const fieldErrors = {}
    Object.entries(data.errors).forEach(([field, value]) => {
      fieldErrors[field] = firstMessage(value)
    })

    const firstKey = Object.keys(fieldErrors)[0]
    errors.value = fieldErrors
    generalError.value = firstKey ? fieldErrors[firstKey] : 'Revisa los campos'
  } else if (data?.message) {
    generalError.value = data.message
  } else {
    generalError.value = 'Error al crear paciente'
  }
}

const handleClose = () => emit('close')

onMounted(async () => {
  await nextTick()
  firstField.value?.querySelector('input')?.focus()
})
</script>
