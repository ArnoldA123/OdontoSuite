<template>
  <div class="login-page">
    <div class="login-grid">
      <!-- Form column (left on desktop, second on mobile) -->
      <section class="login-form-column" aria-labelledby="login-headline">
        <div ref="cardRef" class="login-form-wrap">
          <header class="login-header">
            <div class="brand-mark" aria-hidden="true">
              <img src="/images/easy_dent.png" alt="" class="brand-mark-img" >
            </div>
            <p class="brand-name">OdontoSuite</p>
          </header>

          <div class="welcome-section">
            <h1 id="login-headline" class="welcome-headline">
Gestiona tu clínica con calma
</h1>
            <p class="welcome-subtitle">
              Inicia sesión para revisar citas, caja y pacientes en un solo lugar.
            </p>
          </div>

          <Card variant="glass" padding="lg" class="login-card-surface">
            <form
              class="login-form"
              novalidate
              :aria-busy="loading || undefined"
              @submit.prevent="handleLogin"
            >
              <!-- Username field. Rendered as a raw <input> (not via
                   UiInput) so the autocomplete and inputmode attributes
                   reach the actual form control — UiInput's wrapper-root
                   pattern would consume them as fall-through attrs. -->
              <div class="field">
                <label class="field-label" for="login-username">Usuario</label>
                <div class="field-input-wrap">
                  <span class="field-prefix" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.75"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                      />
                    </svg>
                  </span>
                  <input
                    id="login-username"
                    ref="usernameInput"
                    v-model="form.username"
                    type="text"
                    name="username"
                    autocomplete="username"
                    inputmode="text"
                    spellcheck="false"
                    autocapitalize="off"
                    required
                    :disabled="loading"
                    :aria-invalid="!!errors.username"
                    :aria-describedby="
                      errors.username ? 'login-username-error' : 'login-username-hint'
                    "
                    class="field-input"
                    placeholder="usuario"
                  />
                </div>
                <p v-if="errors.username" id="login-username-error" class="field-error">
                  {{ errors.username }}
                </p>
              </div>

              <!-- Password field. Same pattern as username so the
                   autocomplete=current-password attr reaches the input. -->
              <div class="field">
                <label class="field-label" for="login-password">Contraseña</label>
                <div class="field-input-wrap">
                  <span class="field-prefix" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.75"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                      />
                    </svg>
                  </span>
                  <input
                    id="login-password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    autocomplete="current-password"
                    required
                    :disabled="loading"
                    :aria-invalid="!!errors.password"
                    :aria-describedby="
                      errors.password ? 'login-password-error' : 'login-password-hint'
                    "
                    class="field-input"
                    placeholder="Mínimo 8 caracteres"
                  />
                  <button
                    type="button"
                    class="password-toggle"
                    :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    :aria-pressed="showPassword"
                    tabindex="-1"
                    @click="showPassword = !showPassword"
                  >
                    <svg
                      v-if="showPassword"
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
                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                      />
                    </svg>
                    <svg
                      v-else
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
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.75"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                      />
                    </svg>
                  </button>
                </div>
                <p v-if="errors.password" id="login-password-error" class="field-error">
                  {{ errors.password }}
                </p>
              </div>

              <!-- Remember + forgot -->
              <div class="form-options">
                <label class="remember-me">
                  <input
                    v-model="form.remember"
                    type="checkbox"
                    class="checkbox-input"
                    :disabled="loading"
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

              <!-- Auth failure: inline aria-live, never a toast -->
              <div v-if="error" class="auth-error" role="alert" aria-live="polite">
                <svg
                  class="auth-error-icon"
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
                <p class="auth-error-text">
                  {{ error }}
                </p>
              </div>

              <UiButton
                type="submit"
                variant="primary"
                size="lg"
                :loading="loading"
                :disabled="loading"
                :full-width="true"
              >
                <span v-if="!loading">Iniciar sesión</span>
              </UiButton>
            </form>
          </Card>

          <p class="login-footer-note">
            © {{ currentYear }} OdontoSuite. Sistema de gestión dental.
            <a href="mailto:soporte@odontosuite.local" class="login-footer-link">Soporte</a>
          </p>
        </div>
      </section>

      <!-- Hero column (right on desktop, top strip on mobile) -->
      <aside class="login-hero-column" aria-hidden="true">
        <div class="hero-overlay" />
        <img
          src="/images/ui/login-hero.jpg"
          alt=""
          class="hero-image"
          loading="lazy"
          decoding="async"
        />
        <div class="hero-caption">
          <p class="hero-caption-eyebrow">OdontoSuite</p>
          <p class="hero-caption-title">Una consola clínica sobria, hecha para el consultorio.</p>
        </div>
      </aside>
    </div>

    <!-- Forgot Password Modal -->
    <ForgotPasswordModal v-model="showForgotPasswordModal" @success="handleForgotPasswordSuccess" />

    <!-- Reset Password Modal (user-driven only — never auto-opens from Forgot success) -->
    <ResetPasswordModal
      v-model="showResetPasswordModal"
      :email="resetEmail"
      @success="handleResetPasswordSuccess"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useSpring } from '@/composables/useSpring'
import { useAuth } from '@/composables/useAuth'
import Card from '@/components/ui/Card.vue'
import ForgotPasswordModal from './ForgotPasswordModal.vue'
import ResetPasswordModal from './ResetPasswordModal.vue'
import UiButton from '@/components/ui/Button.vue'

const router = useRouter()
const { login } = useAuth()

// State
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const showForgotPasswordModal = ref(false)
const showResetPasswordModal = ref(false)
const resetEmail = ref('')
const errors = reactive({
  username: '',
  password: ''
})

const currentYear = new Date().getFullYear()

const form = reactive({
  username: '',
  password: '',
  remember: false
})

// Card entrance spring — critically damped, no bounce. Under
// prefers-reduced-motion the spring writes the target instantly and only
// the opacity ref cross-fades; see useSpring.js contract.
const cardSpring = useSpring({
  response: 0.35,
  damping: 1.0,
  from: 0,
  to: 1,
  cssVar: '--spring-card-o'
})
const opacitySpring = useSpring({
  response: 0.2,
  damping: 1.0,
  from: 0,
  to: 1,
  cssVar: '--spring-card-opacity'
})

const cardRef = ref(null)

onMounted(async () => {
  await nextTick()
  if (cardRef.value) {
    cardSpring.attach(cardRef.value)
    opacitySpring.attach(cardRef.value)
  }
  cardSpring.set(1)
  opacitySpring.set(1)
})

// Validation
const validateField = field => {
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

const handleForgotPasswordSuccess = data => {
  // The dev-only reset_token is no longer surfaced in the UI. The Forgot
  // modal emits `email` only; the user clicks a separate link to open
  // ResetPasswordModal. The API surface still includes reset_token for
  // tests that exercise it.
  showForgotPasswordModal.value = false
  if (data?.email) {
    resetEmail.value = data.email
  }
}

const handleResetPasswordSuccess = () => {
  showResetPasswordModal.value = false
  resetEmail.value = ''
}
</script>

<style scoped>
.login-page {
  @apply min-h-[100dvh] w-full flex items-stretch justify-center;
  background: var(--color-canvas);
}

.login-grid {
  @apply grid w-full;
  grid-template-columns: 1fr;
}

/* Mobile-first: form first, hero as a short band above the form.
   On md+ the hero column slides to the right and takes ~58% of the
   viewport per editorial-split spec. */
.login-form-column {
  @apply order-2 flex items-center justify-center px-5 py-10 sm:px-8;
}

.login-form-wrap {
  --spring-card-o: 1;
  --spring-card-opacity: 1;
  @apply relative w-full max-w-md flex flex-col gap-7;
  transform: translate3d(0, calc((1 - var(--spring-card-o)) * 12px), 0);
  opacity: var(--spring-card-opacity);
}

.login-header {
  @apply flex items-center gap-3;
}

.brand-mark {
  @apply inline-flex items-center justify-center w-11 h-11 rounded-full;
  background: var(--color-terracotta-500);
  box-shadow: var(--shadow-soft);
}

.brand-mark-img {
  @apply h-6 w-6 object-contain;
  filter: brightness(0) invert(1);
}

.brand-name {
  @apply text-base font-semibold tracking-tight;
  color: var(--color-label-label);
}

.welcome-section {
  @apply flex flex-col gap-2;
}

.welcome-headline {
  @apply text-3xl sm:text-4xl font-medium leading-[1.1];
  color: var(--color-label-label);
  letter-spacing: -0.022em;
}

.welcome-subtitle {
  @apply text-sm leading-relaxed;
  color: var(--color-label-secondary-label);
}

.login-card-surface {
  @apply w-full;
}

.login-form {
  @apply flex flex-col gap-5;
}

.login-form :deep(button[type='submit']) {
  box-shadow:
    var(--elevation-3),
    inset 0 1px 0 rgba(255, 255, 255, 0.3);
}

.form-options {
  @apply flex flex-wrap items-center justify-between gap-2;
}

.remember-me {
  @apply inline-flex items-center gap-2 cursor-pointer select-none;
}

.checkbox-input {
  @apply h-4 w-4 rounded;
  accent-color: var(--color-accent);
  border-color: var(--color-border);
}

.checkbox-label {
  @apply text-sm;
  color: var(--color-label-secondary-label);
}

.forgot-password-link {
  @apply text-sm underline-offset-4 focus:outline-none;
  color: var(--color-accent);
  background: transparent;
  border: none;
  padding: 0;
  cursor: pointer;
  transition:
    color 200ms ease-out,
    text-decoration-color 200ms ease-out;
}

.forgot-password-link:hover {
  color: var(--color-accent-active);
  text-decoration: underline;
}

.password-toggle {
  @apply absolute p-1 focus:outline-none rounded-md;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-label-secondary-label);
  background: transparent;
  border: none;
  cursor: pointer;
  transition:
    color 200ms ease-out,
    background-color 200ms ease-out;
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

.field {
  @apply flex flex-col gap-1.5;
}

.field-label {
  @apply text-sm font-medium select-none;
  color: var(--color-label-label);
}

.field-input-wrap {
  @apply relative flex items-center;
}

.field-prefix {
  @apply absolute left-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none;
  color: var(--color-label-secondary-label);
}

.field-input {
  @apply block w-full text-base;
  background: var(--color-background-system-background);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-control);
  color: var(--color-label-label);
  padding: 14px 44px 14px 40px;
  min-height: 52px;
  transition:
    border-color var(--motion-duration-normal) var(--motion-easing-ios),
    box-shadow var(--motion-duration-normal) var(--motion-easing-ios),
    background-color var(--motion-duration-normal) var(--motion-easing-ios);
}

.field-input::placeholder {
  color: var(--color-label-tertiary-label);
}

.field-input:hover:not(:disabled) {
  border-color: var(--color-label-tertiary-label);
}

.field-input:focus {
  outline: none;
  border-color: var(--color-accent);
  box-shadow: 0 0 0 3px var(--color-accent-light);
  background: var(--color-cream-50);
}

.field-input:disabled {
  background: var(--color-cream-100);
  cursor: not-allowed;
  opacity: 0.7;
}

.field-input[aria-invalid='true'] {
  border-color: var(--color-error-500);
}

.field-input[aria-invalid='true']:focus {
  border-color: var(--color-error-500);
  box-shadow: 0 0 0 3px var(--color-error-50);
}

.field-error {
  @apply text-xs leading-snug;
  color: var(--color-error-700);
}

.field-hint {
  @apply text-xs leading-snug;
  color: var(--color-label-secondary-label);
}

.auth-error {
  @apply flex items-start gap-2 p-3 rounded-xl;
  background: var(--color-error-50);
  border: 1px solid var(--color-error-100);
}

.auth-error-icon {
  @apply w-5 h-5 flex-shrink-0 mt-0.5;
  color: var(--color-error-600);
}

.auth-error-text {
  @apply text-sm leading-snug;
  color: var(--color-error-700);
}

.login-footer-note {
  @apply text-xs text-center;
  color: var(--color-label-tertiary-label);
}

.login-footer-link {
  color: var(--color-accent);
  transition: color 200ms ease-out;
}

.login-footer-link:hover {
  color: var(--color-accent-active);
}

/* Hero column */
.login-hero-column {
  @apply order-1 relative overflow-hidden;
  min-height: 200px;
}

.hero-image {
  @apply absolute inset-0 w-full h-full object-cover;
  border-radius: var(--radius-card-lg);
}

.hero-overlay {
  position: absolute;
  inset: 0;
  border-radius: var(--radius-card-lg);
  background: linear-gradient(180deg, rgba(60, 60, 67, 0.05) 0%, rgba(60, 60, 67, 0.55) 100%);
  z-index: 1;
}

.hero-caption {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 20px;
  z-index: 2;
  color: var(--color-cream-50);
}

.hero-caption-eyebrow {
  @apply text-xs uppercase tracking-[0.18em] mb-2;
  color: var(--color-system-gray-50);
  opacity: 1;
}

.hero-caption-title {
  @apply text-lg leading-snug;
  letter-spacing: -0.01em;
}

/* Tablet and up: form first column, hero second, hero ~58% wide */
@media (min-width: 768px) {
  .login-grid {
    grid-template-columns: 1fr 1fr;
  }
  .login-form-column {
    @apply order-1 px-10 py-12;
  }
  .login-hero-column {
    @apply order-2;
    min-height: 100dvh;
  }
  .login-form-wrap {
    max-width: 28rem;
  }
}

@media (min-width: 1024px) {
  .login-grid {
    grid-template-columns: 5fr 7fr;
  }
}

/* Honor reduced motion — kill the entrance spring. The composable
   already returns instantly when prefers-reduced-motion is set; this
   block guards against any leftover transform or transition. */
@media (prefers-reduced-motion: reduce) {
  .login-form-wrap {
    transform: none !important;
    transition: none !important;
  }
}

/* Honor reduced transparency — flatten the hero overlay. */
@media (prefers-reduced-transparency: reduce) {
  .hero-overlay {
    background: rgb(31 27 23 / 0.45);
  }
}

/* High contrast — lift the headline against the cream surface. */
@media (prefers-contrast: more) {
  .welcome-headline {
    color: var(--color-label-label);
  }
  .welcome-subtitle {
    color: var(--color-label-secondary-label);
  }
  .forgot-password-link,
  .login-footer-link {
    text-decoration: underline;
  }
}
</style>
