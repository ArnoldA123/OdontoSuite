<template>
  <UiModal
    data-create-patient
    model-value
    size="lg"
    class="z-[60]"
    title="Nuevo paciente"
    @close="emit('close')"
  >
    <form novalidate @submit.prevent="handleSubmit">
      <div class="space-y-4">
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
    </form>

    <template #footer>
      <UiButton
        variant="secondary"
        type="button"
        :disabled="loading"
        data-create-patient-cancel
        @click="emit('close')"
      >
        Cancelar
      </UiButton>
      <UiButton
        variant="primary"
        type="button"
        :loading="loading"
        data-create-patient-submit
        @click="handleSubmit"
      >
        Crear paciente
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import UiModal from '@/components/ui/Modal.vue'
import { ExclamationCircleIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'created'])

const { post } = useApi()
const toast = useToast()

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

onMounted(async () => {
  await nextTick()
  firstField.value?.querySelector('input')?.focus()
})
</script>
