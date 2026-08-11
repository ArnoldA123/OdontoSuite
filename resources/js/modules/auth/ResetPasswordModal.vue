<template>
  <UiModal
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    title="Restablecer contraseña"
    size="md"
    :closable="!loading"
  >
    <form @submit.prevent="handleSubmit" class="space-y-5">
      <p class="text-sm leading-relaxed instructions">
        Ingresa tu nueva contraseña. Asegúrate de que tenga al menos 8 caracteres.
      </p>

      <!-- Email Field -->
      <UiInput
        v-model="email"
        label="Correo electrónico"
        type="email"
        placeholder="tu@ejemplo.com"
        required
        autocomplete="email"
        :disabled="loading || !!initialEmail"
        :error="errors.email"
        @blur="validateEmail"
      >
        <template #prefix>
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </template>
      </UiInput>

      <!-- Password Field -->
      <UiInput
        v-model="password"
        label="Nueva contraseña"
        :type="showPassword ? 'text' : 'password'"
        placeholder="Mínimo 8 caracteres"
        required
        autocomplete="new-password"
        :disabled="loading"
        :error="errors.password"
        @blur="validatePassword"
      >
        <template #prefix>
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </template>
        <template #suffix>
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="password-toggle"
            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            :aria-pressed="showPassword"
          >
            <svg v-if="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </template>
      </UiInput>

      <!-- Confirm Password Field -->
      <UiInput
        v-model="passwordConfirmation"
        label="Confirmar contraseña"
        :type="showPasswordConfirmation ? 'text' : 'password'"
        placeholder="Confirma tu contraseña"
        required
        autocomplete="new-password"
        :disabled="loading"
        :error="errors.passwordConfirmation"
        @blur="validatePasswordConfirmation"
      >
        <template #prefix>
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </template>
        <template #suffix>
          <button
            type="button"
            @click="showPasswordConfirmation = !showPasswordConfirmation"
            class="password-toggle"
            :aria-label="showPasswordConfirmation ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            :aria-pressed="showPasswordConfirmation"
          >
            <svg v-if="showPasswordConfirmation" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </template>
      </UiInput>

      <!-- Success Message -->
      <div
        v-if="success"
        class="success-panel"
        role="status"
        aria-live="polite"
      >
        <svg class="success-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="success-text">{{ successMessage }}</p>
      </div>

      <!-- Error Message -->
      <div
        v-if="error"
        class="error-panel"
        role="alert"
        aria-live="polite"
      >
        <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="error-text">{{ error }}</p>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-2">
        <UiButton
          v-if="!success"
          type="button"
          variant="secondary"
          @click="handleClose"
          :disabled="loading"
        >
          Cancelar
        </UiButton>
        <UiButton
          v-if="!success"
          type="submit"
          :loading="loading"
          :disabled="loading"
        >
          Restablecer contraseña
        </UiButton>
        <UiButton
          v-if="success"
          type="button"
          @click="handleClose"
        >
          Cerrar
        </UiButton>
      </div>
    </form>
  </UiModal>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import UiModal from '@/components/ui/Modal.vue'
import UiInput from '@/components/ui/Input.vue'
import UiButton from '@/components/ui/Button.vue'

// The Reset modal is now user-driven only (never auto-opens from the Forgot
// success state). The dev-only reset_token field has been removed from the UI
// surface — the API contract (POST /api/auth/reset-password) still accepts an
// optional token, but the modal no longer renders an input for it. Callers
// that need to seed the token programmatically can pass it via the `token`
// prop, which is forwarded in the request body. The `:token="..."` prop is
// retained for backwards compatibility with any consumer that still emits it,
// but it never renders an input.
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  email: {
    type: String,
    default: ''
  },
  token: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const { post } = useApi()

// State
const loading = ref(false)
const email = ref(props.email || '')
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const error = ref('')
const success = ref(false)
const successMessage = ref('')
const errors = reactive({
  email: '',
  password: '',
  passwordConfirmation: ''
})

const initialEmail = ref(props.email || '')

onMounted(() => {
  if (props.email) {
    email.value = props.email
  }
})

// Validation
const validateEmail = () => {
  errors.email = ''

  if (!email.value.trim()) {
    errors.email = 'El correo electrónico es requerido'
    return false
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(email.value)) {
    errors.email = 'Ingresa un correo electrónico válido'
    return false
  }

  return true
}

const validatePassword = () => {
  errors.password = ''

  if (!password.value) {
    errors.password = 'La contraseña es requerida'
    return false
  }

  if (password.value.length < 8) {
    errors.password = 'La contraseña debe tener al menos 8 caracteres'
    return false
  }

  return true
}

const validatePasswordConfirmation = () => {
  errors.passwordConfirmation = ''

  if (!passwordConfirmation.value) {
    errors.passwordConfirmation = 'Por favor confirma tu contraseña'
    return false
  }

  if (password.value !== passwordConfirmation.value) {
    errors.passwordConfirmation = 'Las contraseñas no coinciden'
    return false
  }

  return true
}

const validateForm = () => {
  let isValid = true

  if (!validateEmail()) {
    isValid = false
  }

  if (!validatePassword()) {
    isValid = false
  }

  if (!validatePasswordConfirmation()) {
    isValid = false
  }

  return isValid
}

// Handlers
const handleSubmit = async () => {
  error.value = ''
  success.value = false

  Object.keys(errors).forEach(key => (errors[key] = ''))

  if (!validateForm()) {
    return
  }

  loading.value = true

  try {
    // The token is optional in the API. If a caller passed one via props
    // (e.g. a dev-only handoff), forward it; otherwise omit. The token is
    // never displayed in the UI.
    const payload = {
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value
    }
    if (props.token) {
      payload.token = props.token
    }

    const response = await post('/api/auth/reset-password', payload)

    success.value = true
    successMessage.value =
      response.data?.message ||
      'Contraseña restablecida exitosamente. Puedes iniciar sesión con tu nueva contraseña.'

    emit('success', {
      email: email.value
    })
  } catch (err) {
    if (err.response?.data?.errors) {
      const serverErrors = err.response.data.errors
      if (serverErrors.email) {
        errors.email = Array.isArray(serverErrors.email) ? serverErrors.email[0] : serverErrors.email
      }
      if (serverErrors.password) {
        errors.password = Array.isArray(serverErrors.password) ? serverErrors.password[0] : serverErrors.password
      }
    } else if (err.response?.data?.message) {
      error.value = err.response.data.message
    } else {
      error.value = 'Error al restablecer la contraseña. Por favor, intenta nuevamente.'
    }
  } finally {
    loading.value = false
  }
}

const handleClose = () => {
  email.value = props.email || ''
  password.value = ''
  passwordConfirmation.value = ''
  error.value = ''
  success.value = false
  successMessage.value = ''
  Object.keys(errors).forEach(key => (errors[key] = ''))
  emit('update:modelValue', false)
}

watch(() => props.email, (newValue) => {
  if (newValue) {
    email.value = newValue
    initialEmail.value = newValue
  }
})

watch(() => props.modelValue, (newValue) => {
  if (!newValue) {
    password.value = ''
    passwordConfirmation.value = ''
    error.value = ''
    success.value = false
    successMessage.value = ''
    errors.password = ''
    errors.passwordConfirmation = ''
  }
})
</script>

<style scoped>
.instructions {
  color: var(--color-label-secondary-label);
}

.password-toggle {
  @apply p-1 focus:outline-none rounded-md;
  color: var(--color-label-secondary-label);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: color 200ms ease-out, background-color 200ms ease-out;
}

.password-toggle:hover,
.password-toggle:focus-visible {
  color: var(--color-label-label);
  background: var(--color-cream-200);
}

.password-toggle:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.success-panel {
  @apply flex items-start gap-3 p-4 rounded-xl;
  background: var(--color-success-50);
  border: 1px solid var(--color-success-100);
}

.success-icon {
  @apply w-5 h-5 flex-shrink-0 mt-0.5;
  color: var(--color-success-700);
}

.success-text {
  @apply text-sm font-medium leading-snug;
  color: var(--color-success-700);
}

.error-panel {
  @apply flex items-start gap-3 p-4 rounded-xl;
  background: var(--color-error-50);
  border: 1px solid var(--color-error-100);
}

.error-icon {
  @apply w-5 h-5 flex-shrink-0 mt-0.5;
  color: var(--color-error-600);
}

.error-text {
  @apply text-sm leading-snug;
  color: var(--color-error-700);
}

@media (prefers-contrast: more) {
  .success-text,
  .error-text {
    color: var(--color-label-label);
  }
}
</style>