<template>
  <UiModal
    :model-value="modelValue"
    title="Recuperar contraseña"
    size="md"
    :closable="!loading"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <form class="space-y-5" @submit.prevent="handleSubmit">
      <!-- Instructions -->
      <p class="text-sm leading-relaxed text-ink-700">
        Ingresa el correo electrónico asociado a tu cuenta. Si existe, te enviaremos un enlace para
        restablecer tu contraseña.
      </p>

      <!-- Email Field -->
      <UiInput
        v-model="email"
        label="Correo electrónico"
        type="email"
        placeholder="tu@ejemplo.com"
        required
        autocomplete="email"
        :disabled="loading"
        :error="errors.email"
        @blur="validateEmail"
      >
        <template #prefix>
          <svg
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.75"
              d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
        </template>
      </UiInput>

      <!-- Success Message -->
      <div v-if="success" class="success-panel" role="status" aria-live="polite">
        <svg
          class="success-icon"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.75"
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <div class="success-body">
          <p class="success-title">
            {{ successMessage }}
          </p>
          <p v-if="lastSubmittedEmail" class="success-detail">
            Enviamos las instrucciones a
            <span class="success-email">{{ lastSubmittedEmail }}</span>
            .
          </p>
          <p v-if="!showResetLink" class="success-cta">
            <button
              type="button"
              class="reset-link"
              @click="emit('request-reset', lastSubmittedEmail)"
            >
              ¿Ya tienes el código? Restablecer contraseña
            </button>
          </p>
        </div>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="error-panel" role="alert" aria-live="polite">
        <svg
          class="error-icon"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.75"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <p class="error-text">
          {{ error }}
        </p>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-2">
        <UiButton
          v-if="!success"
          type="button"
          variant="secondary"
          :disabled="loading"
          @click="handleClose"
        >
          Cancelar
        </UiButton>
        <UiButton v-if="!success" type="submit" :loading="loading" :disabled="loading">
          Enviar enlace
        </UiButton>
        <UiButton v-if="success" type="button" @click="handleClose">
Cerrar
</UiButton>
      </div>
    </form>
  </UiModal>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { useApi } from '@/composables/useApi'
import UiModal from '@/components/ui/Modal.vue'
import UiInput from '@/components/ui/Input.vue'
import UiButton from '@/components/ui/Button.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'success', 'request-reset'])

const { post } = useApi()

// State
const loading = ref(false)
const email = ref('')
const error = ref('')
const success = ref(false)
const successMessage = ref('')
const lastSubmittedEmail = ref('')
const showResetLink = ref(false)
const errors = reactive({
  email: ''
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

// Handlers
const handleSubmit = async () => {
  error.value = ''
  success.value = false

  if (!validateEmail()) {
    return
  }

  loading.value = true
  lastSubmittedEmail.value = email.value

  try {
    const response = await post('/api/auth/forgot-password', {
      email: email.value
    })

    success.value = true
    successMessage.value =
      response.data?.message ||
      'Si existe una cuenta con ese correo electrónico, hemos enviado un enlace de recuperación.'

    // Surface a "open reset modal" CTA only when the API explicitly returns a
    // dev reset_token. In production this branch never fires because the
    // server omits reset_token from the public response.
    showResetLink.value = !!(response.data?.reset_token || response.data?.debug?.token)

    emit('success', {
      email: email.value
    })
  } catch (err) {
    if (err.response?.data?.errors?.email) {
      errors.email = err.response.data.errors.email[0]
    } else if (err.response?.data?.message) {
      error.value = err.response.data.message
    } else {
      error.value = 'Error al enviar el enlace de recuperación. Por favor, intenta nuevamente.'
    }
  } finally {
    loading.value = false
  }
}

const handleClose = () => {
  email.value = ''
  error.value = ''
  success.value = false
  successMessage.value = ''
  lastSubmittedEmail.value = ''
  showResetLink.value = false
  errors.email = ''
  emit('update:modelValue', false)
}

// Watch for modal close
watch(
  () => props.modelValue,
  newValue => {
    if (!newValue) {
      email.value = ''
      error.value = ''
      success.value = false
      successMessage.value = ''
      lastSubmittedEmail.value = ''
      showResetLink.value = false
      errors.email = ''
    }
  }
)
</script>

<style scoped>
.text-ink-700 {
  color: var(--color-label-secondary-label);
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

.success-body {
  @apply flex-1 flex flex-col gap-1;
}

.success-title {
  @apply text-sm font-medium leading-snug;
  color: var(--color-success-700);
}

.success-detail {
  @apply text-sm leading-snug;
  color: var(--color-label-secondary-label);
}

.success-email {
  @apply font-medium;
  color: var(--color-label-label);
}

.success-cta {
  @apply text-sm;
  color: var(--color-label-secondary-label);
}

.reset-link {
  background: transparent;
  border: none;
  padding: 0;
  cursor: pointer;
  color: var(--color-accent);
  transition: color 200ms ease-out;
}

.reset-link:hover {
  color: var(--color-accent-active);
  text-decoration: underline;
}

.reset-link:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
  border-radius: 2px;
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
  .success-title,
  .success-detail,
  .error-text {
    color: var(--color-label-label);
  }
}
</style>
