<template>
  <div class="inline-overlay" @click="handleClose">
    <div class="inline-content" @click.stop>
      <div class="inline-header">
        <h3 class="inline-title">Nuevo paciente</h3>
        <button @click="handleClose" class="modal-close" aria-label="Cerrar">
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="inline-body">
        <div class="grid grid-cols-2 gap-3">
          <div class="form-group col-span-2">
            <label class="form-label">Nombres <span class="req">*</span></label>
            <input
              v-model="form.first_name"
              type="text"
              class="form-input"
              :class="{ 'has-error': errors.first_name }"
              placeholder="Ej: Juan Carlos"
              ref="firstInput"
            />
            <p v-if="errors.first_name" class="form-error">{{ errors.first_name }}</p>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label">Apellidos <span class="req">*</span></label>
            <input
              v-model="form.last_name"
              type="text"
              class="form-input"
              :class="{ 'has-error': errors.last_name }"
              placeholder="Ej: Pérez Gómez"
            />
            <p v-if="errors.last_name" class="form-error">{{ errors.last_name }}</p>
          </div>

          <div class="form-group">
            <label class="form-label">DNI / Documento</label>
            <input
              v-model="form.document_number"
              type="text"
              class="form-input"
              placeholder="Opcional"
              maxlength="20"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input
              v-model="form.phone"
              type="tel"
              class="form-input"
              placeholder="+51 999 999 999"
            />
          </div>

          <div class="form-group col-span-2">
            <label class="form-label">Email</label>
            <input
              v-model="form.email"
              type="email"
              class="form-input"
              :class="{ 'has-error': errors.email }"
              placeholder="opcional@ejemplo.com"
            />
            <p v-if="errors.email" class="form-error">{{ errors.email }}</p>
          </div>

          <div class="form-group col-span-2">
            <label class="form-label">Fecha de nacimiento</label>
            <input v-model="form.birth_date" type="date" class="form-input" />
          </div>
        </div>

        <div v-if="generalError" class="general-error mt-3">
          <ExclamationCircleIcon class="w-5 h-5" />
          <span>{{ generalError }}</span>
        </div>
      </form>

      <div class="inline-footer">
        <button type="button" @click="handleClose" class="btn btn-outline" :disabled="loading">
          Cancelar
        </button>
        <button
          type="submit"
          @click="handleSubmit"
          class="btn btn-primary"
          :disabled="loading"
        >
          <span v-if="loading" class="spinner"></span>
          Crear paciente
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { XMarkIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'created'])

const { post } = useApi()
const toast = useToast()
const loading = ref(false)
const generalError = ref('')
const errors = ref({})
const firstInput = ref(null)

const form = ref({
  first_name: '',
  last_name: '',
  document_number: '',
  phone: '',
  email: '',
  birth_date: '',
})

const handleSubmit = async () => {
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
    const newPatient = response.data
    toast.success('Paciente creado exitosamente')
    emit('created', newPatient)
  } catch (err) {
    handleError(err)
  } finally {
    loading.value = false
  }
}

const handleError = (err) => {
  const data = err.response?.data
  if (data?.errors) {
    Object.assign(errors.value, data.errors)
    const firstField = Object.keys(data.errors)[0]
    generalError.value = data.errors[firstField]?.[0] || 'Revisa los campos'
  } else if (data?.message) {
    generalError.value = data.message
  } else {
    generalError.value = 'Error al crear paciente'
  }
}

const handleClose = () => emit('close')

onMounted(async () => {
  await nextTick()
  firstInput.value?.focus()
})
</script>

<style scoped>
.inline-overlay {
  @apply fixed inset-0 z-[60] flex items-center justify-center p-4;
  background-color: rgba(0, 0, 0, 0.6);
}

.inline-content {
  @apply rounded-lg shadow-2xl max-w-md w-full flex flex-col overflow-hidden;
  background-color: var(--color-surface-elevated);
}

.inline-header {
  @apply flex items-center justify-between p-4 border-b border-theme;
}

.inline-title {
  @apply text-lg font-semibold;
  color: var(--color-text-primary);
}

.modal-close {
  @apply p-1 rounded transition-colors;
  color: var(--color-text-secondary);
}
.modal-close:hover {
  color: var(--color-text-primary);
}

.inline-body {
  @apply p-4;
}

.form-group {
  @apply space-y-1;
}

.form-label {
  @apply block text-sm font-medium;
  color: var(--color-text-primary);
}

.req {
  color: rgb(239 68 68);
}

.form-input {
  @apply w-full px-3 py-2 text-sm border border-theme rounded-md focus:ring-2 focus:ring-primary-500 focus:border-transparent;
  background-color: var(--color-surface-elevated);
  color: var(--color-text-primary);
}

.form-input.has-error {
  border-color: rgb(239 68 68);
}

.form-error {
  @apply text-xs;
  color: rgb(239 68 68);
}

.general-error {
  @apply flex items-center gap-2 p-3 rounded-md text-sm;
  background-color: rgb(254 226 226);
  color: rgb(185 28 28);
}

.inline-footer {
  @apply flex justify-end gap-2 p-4 border-t border-theme;
}

.btn {
  @apply inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed;
}
.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}
.btn-outline {
  @apply border border-theme;
  color: var(--color-text-primary);
  background-color: var(--color-surface-elevated);
}
.btn-outline:hover {
  background-color: var(--color-surface);
}

.spinner {
  @apply inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
