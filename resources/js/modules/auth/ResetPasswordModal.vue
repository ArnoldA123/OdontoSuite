<template>
  <UiModal
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    title="Restablecer Contraseña"
    size="md"
    :closable="!loading"
  >
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Instructions -->
      <div class="text-sm text-theme-secondary">
        Ingresa tu nueva contraseña. Asegúrate de que tenga al menos 8 caracteres.
      </div>

      <!-- Token Field (hidden in production, visible in development) -->
      <div v-if="!tokenFromUrl" class="space-y-2">
        <UiInput
          v-model="token"
          label="Token de Recuperación"
          type="text"
          placeholder="Ingresa el token de recuperación"
          required
          :disabled="loading"
          :error="errors.token"
        >
          <template #prefix>
            <svg class="h-5 w-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
          </template>
        </UiInput>
      </div>

      <!-- Email Field -->
      <div class="space-y-2">
        <UiInput
          v-model="email"
          label="Correo Electrónico"
          type="email"
          placeholder="tu@ejemplo.com"
          required
          :disabled="loading || !!initialEmail"
          :error="errors.email"
          @blur="validateEmail"
        >
          <template #prefix>
            <svg class="h-5 w-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          </template>
        </UiInput>
      </div>

      <!-- Password Field -->
      <div class="space-y-2">
        <UiInput
          v-model="password"
          label="Nueva Contraseña"
          :type="showPassword ? 'text' : 'password'"
          placeholder="Mínimo 8 caracteres"
          required
          :disabled="loading"
          :error="errors.password"
          @blur="validatePassword"
        >
          <template #prefix>
            <svg class="h-5 w-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </template>
          <template #suffix>
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="text-theme-secondary hover:text-theme-primary transition-colors duration-200 p-1"
              :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            >
              <svg v-if="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
              </svg>
              <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </template>
        </UiInput>
      </div>

      <!-- Confirm Password Field -->
      <div class="space-y-2">
        <UiInput
          v-model="passwordConfirmation"
          label="Confirmar Contraseña"
          :type="showPasswordConfirmation ? 'text' : 'password'"
          placeholder="Confirma tu contraseña"
          required
          :disabled="loading"
          :error="errors.passwordConfirmation"
          @blur="validatePasswordConfirmation"
        >
          <template #prefix>
            <svg class="h-5 w-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </template>
          <template #suffix>
            <button
              type="button"
              @click="showPasswordConfirmation = !showPasswordConfirmation"
              class="text-theme-secondary hover:text-theme-primary transition-colors duration-200 p-1"
              :aria-label="showPasswordConfirmation ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            >
              <svg v-if="showPasswordConfirmation" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
              </svg>
              <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </template>
        </UiInput>
      </div>

      <!-- Success Message -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div v-if="success" class="p-4 bg-green-50 border border-green-200 rounded-xl">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-medium text-success-700">
              {{ successMessage }}
            </p>
          </div>
        </div>
      </Transition>

      <!-- Error Message -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-xl">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-error-700">{{ error }}</p>
          </div>
        </div>
      </Transition>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4">
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
          Restablecer Contraseña
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
import { useApi } from '../../composables/useApi'
import UiModal from '../../components/ui/Modal.vue'
import UiInput from '../../components/ui/Input.vue'
import UiButton from '../../components/ui/Button.vue'

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
const token = ref(props.token || '')
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const error = ref('')
const success = ref(false)
const successMessage = ref('')
const errors = reactive({
  email: '',
  token: '',
  password: '',
  passwordConfirmation: ''
})

const initialEmail = ref(props.email || '')
const tokenFromUrl = ref(!!props.token)

// Set initial values
onMounted(() => {
  if (props.email) {
    email.value = props.email
  }
  if (props.token) {
    token.value = props.token
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
  
  if (!tokenFromUrl.value && !token.value) {
    errors.token = 'El token de recuperación es requerido'
    isValid = false
  }
  
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
  
  // Clear errors
  Object.keys(errors).forEach(key => errors[key] = '')
  
  if (!validateForm()) {
    return
  }

  loading.value = true

  try {
    const response = await post('/api/auth/reset-password', {
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value
    })

    success.value = true
    successMessage.value = response.data?.message || 'Contraseña restablecida exitosamente. Puedes iniciar sesión con tu nueva contraseña.'
    
    emit('success', {
      email: email.value
    })
  } catch (err) {
    
    if (err.response?.data?.errors) {
      const serverErrors = err.response.data.errors
      if (serverErrors.email) {
        errors.email = Array.isArray(serverErrors.email) ? serverErrors.email[0] : serverErrors.email
      }
      if (serverErrors.token) {
        errors.token = Array.isArray(serverErrors.token) ? serverErrors.token[0] : serverErrors.token
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
  // Reset form
  email.value = props.email || ''
  token.value = props.token || ''
  password.value = ''
  passwordConfirmation.value = ''
  error.value = ''
  success.value = false
  successMessage.value = ''
  Object.keys(errors).forEach(key => errors[key] = '')
  emit('update:modelValue', false)
}

// Watch for prop changes
watch(() => props.email, (newValue) => {
  if (newValue) {
    email.value = newValue
    initialEmail.value = newValue
  }
})

watch(() => props.token, (newValue) => {
  if (newValue) {
    token.value = newValue
    tokenFromUrl.value = true
  }
})

// Watch for modal close
watch(() => props.modelValue, (newValue) => {
  if (!newValue) {
    // Reset when modal closes (but keep email and token if provided)
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
/* Component styles */
</style>

