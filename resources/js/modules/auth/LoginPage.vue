<template>
  <div class="login-page">
    <!-- Background Abstract Shapes -->
    <div class="login-background">
      <div class="shape shape-1"></div>
      <div class="shape shape-2"></div>
      <div class="shape shape-3"></div>
    </div>

    <!-- Login Container -->
    <div class="login-container">
      <!-- Logo and Brand -->
      <div class="login-header">
        <div class="logo-container">
          <img src="/images/easy_dent.png" alt="OdontoSuite" class="logo-image" />
        </div>
        <h1 class="brand-name">OdontoSuite</h1>
      </div>

      <!-- Welcome Message -->
      <div class="welcome-section">
        <h2 class="welcome-title">Bienvenido de nuevo</h2>
        <p class="welcome-subtitle">Inicia sesión en tu cuenta de OdontoSuite.</p>
      </div>

      <!-- Login Card -->
      <LoginCard>
        <form @submit.prevent="handleLogin" class="login-form">
          <!-- Username Field -->
          <div class="form-group">
            <UiInput
              v-model="form.username"
              label="Usuario"
              type="text"
              required
              size="lg"
              :error="errors.username"
              hint="Ingresa tu nombre de usuario"
              @blur="validateField('username')"
              class="login-input"
            >
              <template #prefix>
                <svg class="h-5 w-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
              </template>
            </UiInput>
          </div>

          <!-- Password Field -->
          <div class="form-group">
            <UiInput
              v-model="form.password"
              label="Contraseña"
              :type="showPassword ? 'text' : 'password'"
              required
              size="lg"
              :error="errors.password"
              hint="Ingresa tu contraseña"
              @blur="validateField('password')"
              class="login-input"
            >
              <template #prefix>
                <svg class="h-5 w-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
              </template>
              <template #suffix>
                <button
                  type="button"
                  @click.stop="showPassword = !showPassword"
                  class="password-toggle"
                  :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                >
                  <svg v-if="showPassword" class="h-5 w-5 text-theme-secondary hover:text-theme-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                  </svg>
                  <svg v-else class="h-5 w-5 text-theme-secondary hover:text-theme-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                  </svg>
                </button>
              </template>
            </UiInput>
          </div>

          <!-- Remember Me & Forgot Password -->
          <div class="form-options">
            <label class="remember-me group">
              <input
                v-model="form.remember"
                type="checkbox"
                class="checkbox-input"
              />
              <span class="checkbox-label">Recordarme</span>
            </label>
            <button
              type="button"
              class="forgot-password-link"
              @click="showForgotPasswordModal = true"
            >
              ¿Olvidaste tu contraseña?
            </button>
          </div>

          <!-- Error Message -->
          <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
          >
            <div v-if="error" class="error-message">
              <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="error-text">{{ error }}</p>
            </div>
          </Transition>

          <!-- Login Button -->
          <UiButton
            type="submit"
            variant="primary"
            size="lg"
            :loading="loading"
            :full-width="true"
            class="login-button"
          >
            <span v-if="!loading">Iniciar Sesión</span>
          </UiButton>
        </form>
      </LoginCard>

      <!-- Footer -->
      <div class="login-footer">
        <p class="copyright">
          © 2023 OdontoSuite. Todos los derechos reservados. Versión 2.0 - Sistema de gestión dental
        </p>
        <a
          href="mailto:soporte@odontosuite.local"
          class="help-center-link"
        >
          Centro Ayuda
        </a>
      </div>
    </div>

    <!-- Forgot Password Modal -->
    <ForgotPasswordModal
      v-model="showForgotPasswordModal"
      @success="handleForgotPasswordSuccess"
    />

    <!-- Reset Password Modal -->
    <ResetPasswordModal
      v-model="showResetPasswordModal"
      :email="resetEmail"
      :token="resetToken"
      @success="handleResetPasswordSuccess"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import LoginCard from '../../components/auth/LoginCard.vue'
import ForgotPasswordModal from './ForgotPasswordModal.vue'
import ResetPasswordModal from './ResetPasswordModal.vue'
import UiInput from '../../components/ui/Input.vue'
import UiButton from '../../components/ui/Button.vue'

const router = useRouter()
const { login } = useAuth()

// State
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const showForgotPasswordModal = ref(false)
const showResetPasswordModal = ref(false)
const resetEmail = ref('')
const resetToken = ref('')
const errors = reactive({
  username: '',
  password: ''
})

// Form data
const form = reactive({
  username: '',
  password: '',
  remember: false
})

// Validation
const validateField = (field) => {
  errors[field] = ''
  
  if (field === 'username' && !form.username.trim()) {
    errors.username = 'El usuario es requerido'
  }
  
  if (field === 'password' && !form.password.trim()) {
    errors.password = 'La contraseña es requerida'
  }
}

const validateForm = () => {
  let isValid = true
  
  // Clear previous errors
  errors.username = ''
  errors.password = ''
  
  if (!form.username.trim()) {
    errors.username = 'El usuario es requerido'
    isValid = false
  }
  
  if (!form.password.trim()) {
    errors.password = 'La contraseña es requerida'
    isValid = false
  }
  
  return isValid
}

// Event handlers
const handleLogin = async () => {
  if (!validateForm()) {
    return
  }
  
  loading.value = true
  error.value = ''
  
  try {
    const response = await login(form)
    
    if (response) {
      router.push('/dashboard')
    }
  } catch (err) {
    console.error('[Login] handler error:', err)
    if (err.response?.data?.errors) {
      const serverErrors = err.response.data.errors
      error.value = Object.values(serverErrors).flat().join(', ')
    } else if (err.response?.data?.message) {
      error.value = err.response.data.message
    } else if (err.response) {
      error.value = `[HTTP ${err.response.status}] ${err.response.statusText || 'Error del servidor'}`
    } else if (err.message) {
      error.value = `${err.name || 'Error'}: ${err.message}`
    } else {
      error.value = 'Credenciales incorrectas. Verifica tu usuario y contraseña.'
    }
  } finally {
    loading.value = false
  }
}

const handleForgotPasswordSuccess = (data) => {
  showForgotPasswordModal.value = false
  
  // If token is provided (development), show reset modal
  if (data?.token) {
    resetEmail.value = data.email
    resetToken.value = data.token
    showResetPasswordModal.value = true
  }
}

const handleResetPasswordSuccess = () => {
  showResetPasswordModal.value = false
  resetEmail.value = ''
  resetToken.value = ''
  // Optionally redirect to login or show success message
}
</script>

<style scoped>
.login-page {
  @apply min-h-screen flex items-center justify-center p-4 relative overflow-hidden;
  background: linear-gradient(135deg, #e3f2fd 0%, #fff3e0 100%);
}

/* Background Abstract Shapes */
.login-background {
  @apply absolute inset-0 overflow-hidden pointer-events-none;
}

.shape {
  @apply absolute rounded-full opacity-40 blur-3xl;
  animation: float 20s ease-in-out infinite;
}

.shape-1 {
  @apply w-96 h-96;
  background: linear-gradient(135deg, #64b5f6 0%, #42a5f5 100%);
  top: -10%;
  right: -5%;
  animation-delay: 0s;
}

.shape-2 {
  @apply w-80 h-80;
  background: linear-gradient(135deg, #ffb74d 0%, #ffa726 100%);
  bottom: -10%;
  left: -5%;
  animation-delay: 2s;
}

.shape-3 {
  @apply w-72 h-72;
  background: linear-gradient(135deg, #81c784 0%, #66bb6a 100%);
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  animation-delay: 4s;
}

@keyframes float {
  0%, 100% {
    transform: translate(0, 0) scale(1);
  }
  33% {
    transform: translate(30px, -30px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
}

/* Login Container */
.login-container {
  @apply relative w-full max-w-md z-10;
  animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Header - Logo and Brand */
.login-header {
  @apply text-center mb-8;
}

.logo-container {
  @apply inline-flex items-center justify-center w-20 h-20 rounded-full mb-4;
  background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
  box-shadow: 0 8px 24px rgba(33, 150, 243, 0.3);
  animation: logoPulse 2s ease-in-out infinite;
}

@keyframes logoPulse {
  0%, 100% {
    transform: scale(1);
    box-shadow: 0 8px 24px rgba(33, 150, 243, 0.3);
  }
  50% {
    transform: scale(1.05);
    box-shadow: 0 12px 32px rgba(33, 150, 243, 0.4);
  }
}

.logo-image {
  @apply h-12 w-auto object-contain;
  filter: brightness(0) invert(1);
}

.brand-name {
  @apply text-3xl font-bold mb-2;
  color: var(--color-text-primary);
  letter-spacing: -0.02em;
}

/* Welcome Section */
.welcome-section {
  @apply text-center mb-8;
}

.welcome-title {
  @apply text-2xl font-semibold mb-2;
  color: var(--color-text-primary);
}

.welcome-subtitle {
  @apply text-sm;
  color: var(--color-text-secondary);
}

/* Login Form */
.login-form {
  @apply space-y-5;
}

.form-group {
  @apply space-y-2;
}

.login-input {
  @apply w-full;
}

.password-toggle {
  @apply p-1 transition-colors duration-200 focus:outline-none;
}

/* Form Options */
.form-options {
  @apply flex items-center justify-between;
}

.remember-me {
  @apply flex items-center cursor-pointer;
}

.checkbox-input {
  @apply h-4 w-4 focus:ring-primary-500 rounded transition-colors duration-200;
  color: var(--color-accent);
  border-color: var(--color-border);
  accent-color: var(--color-accent);
}

.checkbox-label {
  @apply ml-2 text-sm transition-colors duration-200;
  color: var(--color-text-primary);
}

.checkbox-label.group-hover {
  color: var(--color-text-primary);
}

.forgot-password-link {
  @apply text-sm transition-colors duration-200 focus:outline-none;
  color: var(--color-accent);
}

.forgot-password-link:hover {
  color: var(--color-primary-700);
}

/* Error Message */
.error-message {
  @apply p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3;
}

.error-icon {
  @apply w-5 h-5 text-red-500 flex-shrink-0 mt-0.5;
}

.error-text {
  @apply text-sm text-error-700;
}

/* Login Button */
.login-button {
  @apply mt-6 !important;
  background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-primary-hover) 100%) !important;
  box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3) !important;
  transition: all 0.3s ease !important;
  border: none !important;
}

.login-button:hover:not(:disabled) {
  background: linear-gradient(135deg, var(--color-primary-hover) 0%, var(--color-primary-active) 100%) !important;
  box-shadow: 0 6px 16px rgba(0, 102, 204, 0.4) !important;
  transform: translateY(-2px) !important;
}

.login-button:active:not(:disabled) {
  transform: translateY(0) !important;
}

/* Footer */
.login-footer {
  @apply text-center mt-8 space-y-2;
}

.copyright {
  @apply text-xs;
  color: var(--color-text-secondary);
  line-height: 1.5;
}

.help-center-link {
  @apply text-sm transition-colors duration-200 focus:outline-none;
  color: var(--color-accent);
}

.help-center-link:hover {
  color: var(--color-primary-700);
}

/* Responsive */
@media (max-width: 640px) {
  .login-container {
    @apply max-w-full;
  }
  
  .logo-container {
    @apply w-16 h-16;
  }
  
  .logo-image {
    @apply h-10;
  }
  
  .brand-name {
    @apply text-2xl;
  }
  
  .welcome-title {
    @apply text-xl;
  }
}
</style>
