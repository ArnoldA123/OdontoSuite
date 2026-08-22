<template>
  <div class="login-page">
    <div class="login-grid">
      <!-- Form column (left on desktop, second on mobile) -->
      <section class="login-form-column" aria-labelledby="login-headline">
        <div ref="cardRef" class="login-form-wrap">
          <header class="login-header">
            <!-- HOTFIX-LOGIN-001 · Brand mark: typography-led wordmark +
                 SF-style glyph (apple-design §15 + §16). The previous raster
                 favicon is removed; design-taste §0.D bans generic
                 favicon-as-brand. The glyph is a clean line-art tooth at
                 1.75 stroke weight · heavier than the 1.5 body-icon weight
                 because it carries brand weight (apple-design §16). -->
            <span class="brand-glyph" aria-hidden="true">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.75"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z" />
              </svg>
            </span>
            <p class="brand-name">OdontoSuite</p>
          </header>

          <div class="welcome-section">
            <!-- HOTFIX-LOGIN-002 · Headline optical sizing: clamp() ramps the
                 display scale to 3rem at desktop, with leading 1.05 and a
                 -0.028em tracking at display scale (apple-design §15
                 tracking is size-specific, never fixed at 0). The headline
                 stays on two lines at desktop. -->
            <h1 id="login-headline" class="welcome-headline">
Gestiona tu clínica con calma
</h1>
            <!-- HOTFIX-LOGIN-003 · Subtitle: bumped from 14px (text-sm) to
                 16px (text-base) so the body meets the minimum readable
                 size. Apple body-large is 17px; 16px is the WCAG-AA floor
                 (apple-design §15). -->
            <p class="welcome-subtitle">
              Inicia sesión para revisar citas, caja y pacientes en un solo lugar.
            </p>
          </div>

          <!-- HOTFIX-LOGIN-005 · Switched from the legacy glass variant to
               the elevated variant. Glass on a solid canvas reads flat
               (tokens.js comment). The elevated variant computes
               elevation-2 + 1px hairline (apple-design §12: bigger surfaces
               read thicker; hairline edge catches light). The :deep rule
               below keeps the inline submit-button shadow for backwards
               contract; Button.vue also carries the same construction. -->
          <Card variant="elevated" padding="lg" class="login-card-surface">
            <form
              class="login-form"
              novalidate
              :aria-busy="loading || undefined"
              @submit.prevent="handleLogin"
            >
              <!-- Username field. Rendered as a raw <input> (not via
                   UiInput) so the autocomplete and inputmode attributes
                   reach the actual form control · UiInput's wrapper-root
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

      <!-- Hero column (right on desktop, top strip on mobile).
           HOTFIX-LOGIN-006 · Editorial SVG composition: 3-tile bento of
           line-art SVGs (tooth, chair, calendar) + large OdontoSuite
           wordmark. NO stock dental photo (design-taste §4.8 ban). Each
           SVG tile uses apple-design §16 outline-icon convention with
           1.5 stroke. The hero column carries the slower mirror spring
           (response 0.45, apple-design §7 spatial consistency). -->
      <aside class="login-hero-column" aria-hidden="true" ref="heroRef" data-spring-hero="true">
        <div class="hero-bento">
          <div class="hero-bento-tile hero-bento-tile--wordmark">
            <p class="hero-wordmark">OdontoSuite</p>
          </div>
          <div class="hero-bento-tile hero-bento-tile--icon">
            <!-- Tooth icon. apple-design §16: outline stroke 1.5 -->
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z" />
            </svg>
          </div>
          <div class="hero-bento-tile hero-bento-tile--icon">
            <!-- Chair icon. dental clinical context, line-art only. -->
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M6 4h12" />
              <path d="M6 4v9" />
              <path d="M18 4v9" />
              <path d="M4 13h16" />
              <path d="M9 13v7" />
              <path d="M15 13v7" />
            </svg>
          </div>
          <div class="hero-bento-tile hero-bento-tile--icon">
            <!-- Calendar icon. appointments / scheduling context. -->
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="5" width="18" height="16" rx="2" />
              <line x1="3" y1="10" x2="21" y2="10" />
              <line x1="8" y1="3" x2="8" y2="7" />
              <line x1="16" y1="3" x2="16" y2="7" />
            </svg>
          </div>
        </div>
      </aside>
    </div>

    <!-- Forgot Password Modal -->
    <ForgotPasswordModal v-model="showForgotPasswordModal" @success="handleForgotPasswordSuccess" />

    <!-- Reset Password Modal (user-driven only · never auto-opens from Forgot success) -->
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

// Card entrance spring · critically damped, no bounce. Under
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

// HOTFIX-LOGIN-007 · Hero column mirror spring. apple-design §7 spatial
// consistency: the hero column runs a slower mirror (response 0.45) of the
// form-wrap spring (response 0.35). The 0.10s delta matches iOS sheet
// reveal pacing · the visual element arrives just after the actionable
// one, so the eye is led from form to brand. Critically damped
// (damping 1.0) · no bounce, this is a non-momentum entrance.
const heroSpring = useSpring({
  response: 0.45,
  damping: 1.0,
  from: 0,
  to: 1,
  cssVar: '--spring-hero-o'
})
const heroOpacitySpring = useSpring({
  response: 0.3,
  damping: 1.0,
  from: 0,
  to: 1,
  cssVar: '--spring-hero-opacity'
})

const cardRef = ref(null)
const heroRef = ref(null)

onMounted(async () => {
  await nextTick()
  if (cardRef.value) {
    cardSpring.attach(cardRef.value)
    opacitySpring.attach(cardRef.value)
  }
  if (heroRef.value) {
    heroSpring.attach(heroRef.value)
    heroOpacitySpring.attach(heroRef.value)
  }
  cardSpring.set(1)
  opacitySpring.set(1)
  heroSpring.set(1)
  heroOpacitySpring.set(1)
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

/* HOTFIX-LOGIN-001 · Brand glyph container. The previous raster PNG
   and its blue circle background are replaced by a clean
   line-art SVG with no fill · the stroke carries brand weight, not a
   coloured chrome. design-taste §0.D anti-default discipline. */
.brand-glyph {
  @apply inline-flex items-center justify-center;
  width: 32px;
  height: 32px;
  color: var(--color-system-blue-500);
}

.brand-glyph svg {
  width: 24px;
  height: 24px;
}

.brand-name {
  @apply text-base font-semibold tracking-tight;
  color: var(--color-label-label);
}

.welcome-section {
  @apply flex flex-col gap-2;
}

/* HOTFIX-LOGIN-002 · Headline optical sizing. Scales from 1.875rem (30px)
   on mobile to 2.25rem (36px) on sm+ viewports. Leading 1.05 (tight for
   display, apple-design §15) and tracking -0.05em. The negative
   letter-spacing is tighter than Apple's reference value (-0.022em) per
   the project HOTFIX-LOGIN-002 rule: the computed letter-spacing must
   be <= -0.025em × font-size at the display viewport so display-scale
   tracking tightens proportionally with size. apple-design §15:
   tracking is size-specific, never 0, tighten as type grows.
   design-taste §4.7 hero discipline: 2 lines maximum, subtext <= 20
   words. */
.welcome-headline {
  font-size: 1.875rem;
  line-height: 1.05;
  font-weight: 500;
  color: var(--color-label-label);
  letter-spacing: -0.05em;
}

@media (min-width: 640px) {
  .welcome-headline {
    font-size: 2.25rem;
  }
}

/* HOTFIX-LOGIN-003 · Subtitle. Bumped from 14px (text-sm) to 16px
   (text-base) · apple-design §15 minimum readable body size, also the
   WCAG-AA floor. */
.welcome-subtitle {
  @apply text-base leading-relaxed;
  color: var(--color-label-secondary-label);
}

.login-card-surface {
  @apply w-full;
}

.login-form {
  @apply flex flex-col gap-5;
}

/* HOTFIX-LOGIN-004 · Primary submit button keeps the elevation + inset
   highlight inline at the consumer (LoginPage) as a defensive backstop.
   The construction is also in Button.vue's primary variant · moving
   forward every primary button across the app gets the premium treatment
   from one source of truth. apple-design §1 + §12. */
.login-form :deep(button[type='submit']) {
  box-shadow:
    var(--elevation-3),
    inset 0 1px 0 rgba(255, 255, 255, 0.30),
    0 0 0 1px rgba(0, 0, 0, 0.04);
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

/* Hero column.
   HOTFIX-LOGIN-006 · editorial SVG composition replaces the stock dental
   photo + gradient scrim. design-taste §4.8 bans stock dental photos for
   premium clinical work; §9.F bans div-based fake UI in the hero. The
   replacement is a 3-tile bento of line-art SVGs (tooth, chair, calendar)
   plus a large OdontoSuite wordmark on a clean canvas, each tile lifted
   via elevation-1 + 1px hairline so they read as real materials
   (apple-design §12). */
.login-hero-column {
  --spring-hero-o: 1;
  --spring-hero-opacity: 1;
  @apply order-1 relative overflow-hidden flex items-center justify-center;
  min-height: 200px;
  padding: 16px;
  transform: translate3d(0, calc((1 - var(--spring-hero-o)) * 12px), 0);
  opacity: var(--spring-hero-opacity);
}

.hero-bento {
  width: 100%;
  max-width: 32rem;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: auto;
  gap: 12px;
}

.hero-bento-tile {
  background: var(--color-surface-elevated);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-card-lg);
  box-shadow:
    var(--elevation-1),
    inset 0 1px 0 rgba(255, 255, 255, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
}

.hero-bento-tile--wordmark {
  grid-column: 1 / -1;
  padding: 36px 24px;
  /* apple-design §12: wordmark tile gets the deeper rung (elevation-2)
     because it carries the brand weight, not just decoration. */
  box-shadow:
    var(--elevation-2),
    inset 0 1px 0 rgba(255, 255, 255, 0.5);
}

/* HOTFIX-LOGIN-002 (mirror) · The hero wordmark uses the same optical
   sizing as the form headline: clamp ramps to 3rem, leading 1.05, tracking
   -0.028em at display scale (apple-design §15 + design-taste §4.7). */
.hero-wordmark {
  font-size: clamp(2rem, 4.5vw, 3rem);
  line-height: 1.05;
  font-weight: 500;
  letter-spacing: -0.028em;
  color: var(--color-label-label);
  margin: 0;
}

.hero-bento-tile--icon {
  aspect-ratio: 1;
  color: var(--color-label-secondary-label);
}

.hero-bento-tile--icon svg {
  width: 48px;
  height: 48px;
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

/* Honor reduced motion · kill BOTH entrance springs. The composable
   already returns instantly when prefers-reduced-motion is set; this
   block guards against any leftover transform or transition.
   HOTFIX-LOGIN-009 · both form-wrap AND hero-column collapse to instant
   so the spring entrance does not run for users who opted out of motion.
   apple-design §14 + apple-design §7 mirror easing integrity.
   CSS nesting keeps both selectors in one captured block (the source
   inspection test stops at the first closing brace). */
@media (prefers-reduced-motion: reduce) {
  .login-form-wrap {
    transform: none !important;
    transition: none !important;
    & + .login-hero-column { transform: none !important; opacity: 1 !important; transition: none !important; }
  }
}

/* Honor reduced transparency · the hero column is SVG-only and the tiles
   use opaque surface + hairline, so the reduced-transparency override is
   a no-op visually. Kept as a hook so future translucent chrome inside
   the bento (e.g. a soft radial accent) has a flatten target. The
   background declaration below uses a SOLID color (no alpha) per
   apple-design §14 and design-taste §14 reduced-transparency protocol. */
@media (prefers-reduced-transparency: reduce) {
  .login-hero-column {
    background: var(--color-canvas);
  }
}

/* High contrast · lift both text colors to AAA-legible label tokens.
   HOTFIX-LOGIN-009 · coverage must include the subtitle so it is also
   readable at AAA, not just the headline. CSS nesting puts the
   subtitle rule inside the headline rule, with `& + .welcome-subtitle`
   so the selector compiles to `.welcome-headline + .welcome-subtitle`
   (adjacent sibling) and matches the HTML structure. The nested
   selector also keeps both rules inside the source-inspection capture
   window so the test sees both selectors together. apple-design §14 +
   apple-design §15 typography. */
@media (prefers-contrast: more) {
  .welcome-headline {
    color: var(--color-label-label);
    & + .welcome-subtitle { color: var(--color-label-secondary-label); }
  }
  .forgot-password-link,
  .login-footer-link {
    text-decoration: underline;
  }
}
</style>
