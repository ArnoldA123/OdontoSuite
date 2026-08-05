<template>
  <UiModal
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    title="Recuperar Contraseña"
    size="md"
    :closable="!loading"
  >
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Instructions -->
      <div class="text-sm text-theme-secondary">
        Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
      </div>

      <!-- Email Field -->
      <div class="space-y-2">
        <UiInput
          v-model="email"
          label="Correo Electrónico"
          type="email"
          placeholder="tu@ejemplo.com"
          required
          :disabled="loading"
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
            <div class="flex-1">
              <p class="text-sm font-medium text-success-700">
                {{ successMessage }}
              </p>
              <p v-if="resetToken" class="text-xs text-green-600 mt-1">
                Token de prueba (solo desarrollo): {{ resetToken }}
              </p>
            </div>
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
          Enviar Enlace
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
import { ref, reactive, watch } from 'vue'
import { useApi } from '../../composables/useApi'
import UiModal from '../../components/ui/Modal.vue'
import UiInput from '../../components/ui/Input.vue'
import UiButton from '../../components/ui/Button.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const { post } = useApi()

// State
const loading = ref(false)
const email = ref('')
const error = ref('')
const success = ref(false)
const successMessage = ref('')
const resetToken = ref(null)
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
  resetToken.value = null

  if (!validateEmail()) {
    return
  }

  loading.value = true

  try {
    const response = await post('/api/auth/forgot-password', {
      email: email.value
    })

    success.value = true
    successMessage.value = response.data?.message || 'Si existe una cuenta con ese correo electrónico, hemos enviado un enlace de recuperación.'
    
    // Store token if provided (development only)
    if (response.data?.reset_token) {
      resetToken.value = response.data.reset_token
      emit('success', {
        email: email.value,
        token: response.data.reset_token
      })
    } else {
      emit('success', {
        email: email.value
      })
    }
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
  // Reset form
  email.value = ''
  error.value = ''
  success.value = false
  successMessage.value = ''
  resetToken.value = null
  errors.email = ''
  emit('update:modelValue', false)
}

// Watch for modal close
watch(() => props.modelValue, (newValue) => {
  if (!newValue) {
    // Reset when modal closes
    email.value = ''
    error.value = ''
    success.value = false
    successMessage.value = ''
    resetToken.value = null
    errors.email = ''
  }
})
</script>

<style scoped>
/* Component styles */
</style>

