<template>
  <!-- Login page · full-bleed parallax backdrop with a floating form card.
       The decorative scene owns the viewport; the form rides on its own
       elevated surface over it. Phase 2.5 · the TransitionGroup crossfades
       the <form> into the mini-summary; both live in the same group so the
       crossfade uses one enter/leave contract. -->
  <div class="login-page">
    <div ref="backdropRef" class="login-backdrop" aria-hidden="true" data-spring-backdrop="true">
      <DentalParallaxBackground />
    </div>

    <main class="login-stage">
      <div class="login-shell">
        <!-- Brand lockup: tooth glyph + wordmark, no chip chrome. It sits on the
             field above the card, not inside it. -->
        <header class="login-header">
          <p class="brand-lockup">
            <span class="brand-name">OdontoSuite</span>
          </p>
        </header>

        <section class="login-card" role="region" aria-labelledby="login-headline" tabindex="0">
          <div ref="cardRef" class="login-form-wrap">
            <div class="welcome-section">
              <h1 id="login-headline" class="welcome-headline">Iniciar sesión</h1>
              <p class="welcome-subtitle">Ingresa con la cuenta que te asignó tu clínica.</p>
            </div>

            <p class="sr-only" role="status" aria-live="polite">{{ validationAnnouncement }}</p>

            <!-- #382: /reset-password without token or email used to fall back to
               this same card with no explanation. The route now says what the
               link is missing and offers the door that sends a new one. -->
            <div
              v-if="resetLinkMissing && state !== 'success'"
              class="reset-link-notice"
              data-reset-link-missing
            >
              <p class="reset-link-notice-text">
                Este enlace para restablecer la contraseña no trae un código válido. Solicita uno
                nuevo para continuar.
              </p>
              <button
                type="button"
                class="forgot-password-link"
                data-reset-link-request
                @click="showForgotPasswordModal = true"
              >
                Solicitar un enlace nuevo
              </button>
            </div>

            <!-- Phase 2.5 · form → mini-summary polymorphism. No Card
               wrapper: the fields are the card's own content. -->
            <TransitionGroup name="form-card-morph" tag="div" class="login-form-morph">
              <form
                v-if="state !== 'success'"
                key="form"
                class="login-form"
                novalidate
                :aria-busy="isBusy || undefined"
                @submit.prevent="handleLogin"
              >
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
                  <p class="auth-error-text">{{ error }}</p>
                </div>

                <div class="field login-field-stagger" :style="{ '--field-index': 0 }">
                  <label class="field-label" for="login-username">Correo electrónico</label>
                  <div class="field-input-wrap">
                    <input
                      id="login-username"
                      v-model="form.username"
                      type="text"
                      name="username"
                      autocomplete="username"
                      inputmode="text"
                      spellcheck="false"
                      autocapitalize="off"
                      required
                      :disabled="isBusy"
                      :aria-invalid="!!fieldErrors.username"
                      :aria-describedby="fieldDescribedBy.username"
                      class="field-input"
                      :class="{ 'has-success': fieldSuccesses.username }"
                      @blur="onFieldBlur('username')"
                      @input="onFieldInput('username')"
                    />
                    <!-- Success checkmark. `aria-hidden` because the
                           programmatic signal is `aria-invalid="false"`;
                           announcing "valid" on every keystroke would be
                           noise for a screen-reader user. -->
                    <span
                      v-if="fieldSuccesses.username"
                      class="field-success-mark"
                      aria-hidden="true"
                    >
                      <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      >
                        <path d="M5 12 L10 17 L19 7" />
                      </svg>
                    </span>
                  </div>
                  <p
                    v-if="fieldErrors.username"
                    id="login-username-error"
                    class="field-error field-error--animated"
                  >
                    {{ fieldErrors.username }}
                  </p>
                  <p
                    v-else-if="fieldSuccesses.username"
                    id="login-username-success"
                    class="sr-only"
                  >
                    Usuario válido
                  </p>
                </div>

                <div class="field login-field-stagger" :style="{ '--field-index': 1 }">
                  <label class="field-label" for="login-password">Contraseña</label>
                  <div class="field-input-wrap">
                    <input
                      id="login-password"
                      v-model="form.password"
                      :type="showPassword ? 'text' : 'password'"
                      name="password"
                      autocomplete="current-password"
                      required
                      :disabled="isBusy"
                      :aria-invalid="!!fieldErrors.password"
                      :aria-describedby="fieldDescribedBy.password"
                      class="field-input"
                      :class="{ 'has-success': fieldSuccesses.password }"
                      @blur="onFieldBlur('password')"
                      @input="onFieldInput('password')"
                    />
                    <!-- Success mark sits LEFT of the reveal toggle
                           (right: 52px) so the two never overlap. -->
                    <span
                      v-if="fieldSuccesses.password"
                      class="field-success-mark field-success-mark--before-toggle"
                      aria-hidden="true"
                    >
                      <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      >
                        <path d="M5 12 L10 17 L19 7" />
                      </svg>
                    </span>
                    <!-- Password reveal morph (recovered PR2): both eye
                           glyphs stay mounted and cross-fade + scale instead
                           of the abrupt v-if swap. `aria-label` /
                           `aria-pressed` on the button remain the
                           programmatic contract; the glyphs stay
                           `aria-hidden`. -->
                    <button
                      type="button"
                      class="password-toggle"
                      :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                      :aria-pressed="showPassword"
                      @click="showPassword = !showPassword"
                    >
                      <span class="password-toggle-glyphs" aria-hidden="true">
                        <svg
                          class="password-toggle-glyph"
                          :class="{ 'is-active': showPassword }"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.75"
                            d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                          />
                        </svg>
                        <svg
                          class="password-toggle-glyph"
                          :class="{ 'is-active': !showPassword }"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
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
                      </span>
                    </button>
                  </div>
                  <p
                    v-if="fieldErrors.password"
                    id="login-password-error"
                    class="field-error field-error--animated"
                  >
                    {{ fieldErrors.password }}
                  </p>
                  <p
                    v-else-if="fieldSuccesses.password"
                    id="login-password-success"
                    class="sr-only"
                  >
                    Contraseña válida
                  </p>
                </div>

                <div class="form-options login-field-stagger" :style="{ '--field-index': 2 }">
                  <button
                    type="button"
                    class="forgot-password-link"
                    @click="showForgotPasswordModal = true"
                  >
                    ¿Olvidaste tu contraseña?
                  </button>
                </div>

                <!-- Phase 2.4 · polymorphic submit button. Single
                       <UiButton> with 5 inner <span> children gated by
                       v-show on the shape-morph state. The wrapper is the
                       shake target, which keeps the shake off the button's
                       own transform state machine. -->
                <div
                  class="login-submit-wrap login-field-stagger"
                  :class="{ 'is-shaking': shakeTrigger }"
                  :style="{ '--field-index': 3 }"
                >
                  <UiButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    :loading="state === 'authenticating'"
                    :disabled="state === 'success' || state === 'authenticating'"
                    full-width
                    data-state="shape-morph"
                    class="login-submit-shape"
                  >
                    <span v-show="state === 'idle'" class="login-submit-stage">Entrar</span>
                    <span v-show="state === 'validating'" class="login-submit-stage">
                      <span class="login-submit-dot" aria-hidden="true" />
                      Validando
                    </span>
                    <span v-show="state === 'authenticating'" class="login-submit-stage">
                      <span class="login-submit-dot" aria-hidden="true" />
                      Autenticando
                    </span>
                    <span v-show="state === 'success'" class="login-submit-stage">
                      <svg class="login-submit-check" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 12 L10 17 L19 7" />
                      </svg>
                      Listo
                    </span>
                    <span v-show="state === 'error'" class="login-submit-stage">Reintentar</span>
                  </UiButton>
                </div>
              </form>

              <!-- Phase 2.5 · MiniSummary on success state. Avatar with
                     initials, role label, and a manual "Ir al dashboard"
                     button as a defensive fallback if the router.push
                     timed out. -->
              <div v-else key="summary" class="login-mini-summary" role="status" aria-live="polite">
                <div class="login-mini-avatar" aria-hidden="true">
                  {{ miniSummaryInitials }}
                </div>
                <p class="login-mini-name">{{ miniSummaryName }}</p>
                <p v-if="miniSummaryRole" class="login-mini-role">
                  {{ miniSummaryRole }}
                </p>
                <button type="button" class="login-mini-cta" @click="router.push('/dashboard')">
                  Ir al dashboard
                </button>
              </div>
            </TransitionGroup>

            <div class="login-divider"><span>o usa una cuenta externa</span></div>

            <div class="login-sso" role="group" aria-label="Acceso con cuentas externas">
              <button
                type="button"
                class="login-sso-btn"
                data-sso="google"
                aria-label="Continuar con Google"
              >
                <SsoProviderMark provider="google" />
                <span>Google</span>
              </button>
              <button
                type="button"
                class="login-sso-btn"
                data-sso="microsoft"
                aria-label="Continuar con Microsoft"
              >
                <SsoProviderMark provider="microsoft" />
                <span>Microsoft</span>
              </button>
            </div>

            <p class="login-contact">
              ¿Aún no tienes cuenta?
              <a href="mailto:admin@odontosuite.local" class="login-contact-link">
                Contacta con el administrador
              </a>
            </p>
          </div>
        </section>

        <!-- Footer · the legal note and the support address, on the field
             below the card: two centred lines of running text, not a toolbar. -->
        <p class="login-legal">
          Al entrar aceptas los
          <a href="/terminos" target="_blank" rel="noopener" class="login-legal-link">
            Términos del servicio
          </a>
          y la
          <a href="/privacidad" target="_blank" rel="noopener" class="login-legal-link">
            Política de privacidad
          </a>
          vigentes.
        </p>
        <p class="login-legal">
          Soporte técnico:
          <a href="mailto:soporte@odontosuite.pe" class="login-legal-link">
            soporte@odontosuite.pe
          </a>
        </p>
      </div>
    </main>

    <ForgotPasswordModal
      v-model="showForgotPasswordModal"
      @success="handleForgotPasswordSuccess"
      @request-reset="handleForgotPasswordResetRequest"
    />
    <ResetPasswordModal
      v-model="showResetPasswordModal"
      :email="resetEmail"
      :token="resetToken"
      @success="handleResetPasswordSuccess"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useSpring } from '@/composables/useSpring'
import { useAuth } from '@/composables/useAuth'
import { useShapeMorph } from '@/composables/useShapeMorph'
import { useReducedMotion } from '@/composables/useReducedMotion'
import { useFieldValidation } from '@/composables/useFieldValidation'
import { roleLabel } from './roleLabels'
import ForgotPasswordModal from './ForgotPasswordModal.vue'
import ResetPasswordModal from './ResetPasswordModal.vue'
import UiButton from '@/components/ui/Button.vue'
import DentalParallaxBackground from '@/components/login/DentalParallaxBackground.vue'
import SsoProviderMark from '@/components/login/SsoProviderMark.vue'

const router = useRouter()
const route = useRoute()
const { login } = useAuth()

// Phase 2.4 · polymorphic submit state machine.
const { state, transition, release } = useShapeMorph({ initial: 'idle', dwellMs: 200 })

const isBusy = computed(() => state.value === 'validating' || state.value === 'authenticating')

// Phase 2.6 · reactive reduced-motion detector.
const prefersReducedMotion = useReducedMotion()

// State
const error = ref('')
const showPassword = ref(false)
const showForgotPasswordModal = ref(false)
const showResetPasswordModal = ref(false)
const resetEmail = ref('')
const resetToken = ref('')

function openResetFromRoute() {
  if (route.path !== '/reset-password') return
  const email = typeof route.query.email === 'string' ? route.query.email : ''
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  if (!email && !token) return
  resetEmail.value = email
  resetToken.value = token
  showResetPasswordModal.value = true
}

// #382: the mailed link is the only door that opens the dialog. Without token
// and email the route is still the reset surface, so it names the missing piece
// instead of looking like a plain /login.
const resetLinkMissing = computed(() => {
  if (route.path !== '/reset-password') return false
  const email = typeof route.query.email === 'string' ? route.query.email : ''
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  return !email && !token
})

onMounted(openResetFromRoute)

const form = reactive({
  username: '',
  password: '',
  remember: false
})

// Live validation (recovered PR2 · `ui-login-premium-motion-2026-08`).
// `useFieldValidation` owns the rule state machine: it runs the rules on
// `@blur` and re-runs them 250ms after the last change. Its internal watch
// re-validates EVERY declared field whenever any of them changes, so
// `touched` gates *display* only: without it, the moment the user typed one
// character into "Usuario" the empty "Contraseña" field would scold them
// before they ever reached it. Submitting force-reveals every field.
const MIN_USERNAME_LENGTH = 3
const MIN_PASSWORD_LENGTH = 8

const {
  errors: validationErrors,
  successes: validationSuccesses,
  validateField,
  validateAll
} = useFieldValidation(
  form,
  {
    username: [
      value => (value && value.trim() ? null : 'El usuario es requerido'),
      value => (value.trim().length >= MIN_USERNAME_LENGTH ? null : 'Mínimo 3 caracteres')
    ],
    password: [
      value => (value && value.trim() ? null : 'La contraseña es requerida'),
      value => (value.length >= MIN_PASSWORD_LENGTH ? null : 'Mínimo 8 caracteres')
    ]
  },
  { idleMs: 250 }
)

const touched = reactive({ username: false, password: false })

const fieldErrors = computed(() => ({
  username: touched.username ? validationErrors.username || '' : '',
  password: touched.password ? validationErrors.password || '' : ''
}))

const fieldSuccesses = computed(() => ({
  username: touched.username && validationSuccesses.username === true,
  password: touched.password && validationSuccesses.password === true
}))

const fieldDescribedBy = computed(() => {
  const describedBy = {}
  for (const field of ['username', 'password']) {
    if (fieldErrors.value[field]) {
      describedBy[field] = `login-${field}-error`
    } else if (fieldSuccesses.value[field]) {
      describedBy[field] = `login-${field}-success`
    }
  }
  return describedBy
})

const validationAnnouncement = computed(() =>
  fieldSuccesses.value.username && fieldSuccesses.value.password
    ? 'Usuario y contraseña válidos'
    : ''
)

function onFieldBlur(field) {
  touched[field] = true
  validateField(field)
}

function onFieldInput(field) {
  touched[field] = true
}

// Phase 2.5 · mini-summary bindings (success state).
const successUser = ref(null)
const miniSummaryInitials = computed(() => initialsOf(successUser.value?.name || ''))
const miniSummaryName = computed(() => successUser.value?.name || 'Sesión iniciada')
const miniSummaryRole = computed(() => roleLabel(successUser.value?.role))

// Card entrance spring.
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

// HOTFIX-LOGIN-007 · Backdrop mirror spring.
const backdropSpring = useSpring({
  response: 0.45,
  damping: 1.0,
  from: 0,
  to: 1,
  cssVar: '--spring-backdrop-o'
})
const backdropOpacitySpring = useSpring({
  response: 0.3,
  damping: 1.0,
  from: 0,
  to: 1,
  cssVar: '--spring-backdrop-opacity'
})

const cardRef = ref(null)
const backdropRef = ref(null)

// Recovered PR2 · 220ms failure shake. `shakeTrigger` is a one-shot class
// toggle; re-arming happens on the next frame so a second failed attempt
// restarts the animation instead of reusing the finished one.
const SHAKE_MS = 220
const shakeTrigger = ref(false)
let shakeTimer = null
let shakeRaf = null

function triggerShake() {
  if (prefersReducedMotion.value) return
  if (shakeRaf) cancelAnimationFrame(shakeRaf)
  if (shakeTimer) clearTimeout(shakeTimer)
  shakeTrigger.value = false
  shakeRaf = requestAnimationFrame(() => {
    shakeRaf = null
    shakeTrigger.value = true
    shakeTimer = setTimeout(() => {
      shakeTimer = null
      shakeTrigger.value = false
    }, SHAKE_MS)
  })
}

onUnmounted(() => {
  if (shakeRaf) cancelAnimationFrame(shakeRaf)
  if (shakeTimer) clearTimeout(shakeTimer)
})

function initialsOf(name) {
  if (!name) return '··'
  const parts = String(name).trim().split(/\s+/).slice(0, 2)
  return parts.map(p => p.charAt(0).toUpperCase()).join('') || '··'
}

onMounted(async () => {
  await nextTick()
  // Motion budget: under reduced motion the springs are never attached, so
  // the CSS fallbacks (--spring-*-o: 1) hold the final static layout.
  if (prefersReducedMotion.value) return
  if (cardRef.value) {
    cardSpring.attach(cardRef.value)
    opacitySpring.attach(cardRef.value)
  }
  if (backdropRef.value) {
    backdropSpring.attach(backdropRef.value)
    backdropOpacitySpring.attach(backdropRef.value)
  }
  cardSpring.set(1)
  opacitySpring.set(1)
  backdropSpring.set(1)
  backdropOpacitySpring.set(1)
})

// Validation (recovered PR2): the hand-rolled `errors` reactive plus
// `validateField` / `validateForm` pair was dead code: nothing called it and
// the inputs had no `@blur`. `useFieldValidation` above replaces all three.

// Phase 2.4 · handleLogin rewired to drive the shape-morph state machine.
// Cycle:
//   click → validating (200ms dwell) → authenticating → success (600ms) →
//   /dashboard. Non-2xx → error + 220ms shake → idle.
const handleLogin = async () => {
  if (!validateAll()) {
    // Submitting is the explicit "reveal everything" gesture.
    touched.username = true
    touched.password = true
    return
  }
  error.value = ''

  if (!transition('validating')) {
    return
  }

  // 200ms dwell so the validating label reads cleanly even if the API
  // responds instantly. The state machine itself blocks
  // `validating → authenticating` until the dwell elapses.
  await new Promise(resolve => setTimeout(resolve, 220))

  if (!transition('authenticating')) {
    return
  }

  try {
    const response = await login(form)
    if (!response) {
      throw new Error('No response from login')
    }
    const userPayload =
      response?.user || response?.data?.user || JSON.parse(localStorage.getItem('user') || 'null')
    successUser.value = userPayload

    if (transition('success')) {
      setTimeout(() => {
        router.push('/dashboard')
      }, 600)
    }
  } catch (err) {
    let msg = 'Credenciales incorrectas. Verifica tu usuario y contraseña.'
    if (err?.response?.data?.errors) {
      msg = Object.values(err.response.data.errors).flat().join(', ')
    } else if (err?.response?.data?.message) {
      msg = err.response.data.message
    } else if (err?.response) {
      msg = `[HTTP ${err.response.status}] ${err.response.statusText || 'Error del servidor'}`
    } else if (err?.message) {
      msg = `${err.name || 'Error'}: ${err.message}`
    }
    error.value = msg
    // The shake is the failure feedback, so it fires on EVERY failed
    // attempt and is deliberately outside the state-machine guard.
    triggerShake()
    if (transition('error')) {
      // Hand the button back to `idle` once the shake has played.
      //
      // `error` is terminal, so a submit landing there used to leave the page
      // dead: `validateTransition` rejects every outbound transition and
      // `cancel()` refuses terminal states, so a second submit was a silent
      // no-op until the user reloaded.
      //
      // `release()` is the machine's own door out. It checks that the current
      // state really is terminal, schedules the exit, and cancels that schedule
      // on unmount and on any new transition. Assigning `state.value = 'idle'`
      // here instead, which is what this used to do, bypasses every rule in
      // `shapeMorphMath` and leaves the machine desynchronised. Two reviewers
      // independently flagged that write (R4-001 resilience, R3-004
      // reliability), and they were right.
      release(SHAKE_MS)
    }
  }
}

const handleForgotPasswordSuccess = data => {
  if (data?.email) {
    resetEmail.value = data.email
  }
}

const handleForgotPasswordResetRequest = email => {
  showForgotPasswordModal.value = false
  resetEmail.value = email || ''
  resetToken.value = ''
  showResetPasswordModal.value = true
}

const handleResetPasswordSuccess = () => {
  showResetPasswordModal.value = false
}

watch(
  () => showResetPasswordModal.value,
  isOpen => {
    if (isOpen) return
    resetEmail.value = ''
    resetToken.value = ''
    if (route.path === '/reset-password' && (route.query.token || route.query.email)) {
      router.replace({ path: '/reset-password', query: {} })
    }
  }
)
</script>

<style scoped>
/* Nested radius rhythm (roughly 1.4x per step): shell 32px (the shell/panel/
   control ladder lives in the shared tokens since Slice A3, so the login no
   longer owns a parallel scale) → panel 22px → control 12px → pill
   (--radius-full). */
.login-page {
  --login-radius-panel: var(--radius-panel);
  --login-radius-control: var(--radius-control);
  @apply relative min-h-[100dvh] w-full;
  background: var(--color-accent-50);
  overflow-x: hidden;
}

/* Full-bleed decorative layer. The parallax scene paints the viewport and
   the entrance spring mirrors the card, so both surfaces arrive together. */
.login-backdrop {
  --spring-backdrop-o: 1;
  --spring-backdrop-opacity: 1;
  position: fixed;
  inset: 0;
  z-index: 0;
  overflow: hidden;
  pointer-events: none;
  transform: translate3d(0, calc((1 - var(--spring-backdrop-o)) * 12px), 0);
  opacity: var(--spring-backdrop-opacity);
}

/* The stage centres the card and owns the safe spacing around it. The card
   stays centred at every viewport, so the scene distributes around it in the
   top and bottom bands and on both flanks. */
.login-stage {
  @apply relative flex items-center justify-center;
  z-index: 1;
  min-height: 100dvh;
  padding: clamp(16px, 4vw, 56px);
}

/* One centred column on the field: the brand, the card and the two footer
   doors. The brand and the legal note ride the pastel surface, never the card. */
.login-shell {
  @apply flex w-full flex-col items-center;
}

/* Floating flat card. It carries no glass and no border chrome beyond the
   hairline: the separation from the pastel field is the shadow, and the shadow
   is a stack of two rungs (a near one over a far one) rather than a single
   soft drop, which is what reads as lift instead of a smudge. The card owns the
   form's inset and scrolls internally when the form is taller than the
   viewport. */
.login-card {
  position: relative;
  width: 100%;
  max-width: 25.25rem;
  max-height: calc(100dvh - 2 * clamp(16px, 4vw, 56px) - 108px);
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  padding: 32px 32px 30px;
  background: var(--color-background-system-background);
  border: 1px solid var(--color-hairline);
  border-radius: var(--login-radius-panel);
  box-shadow: var(--elevation-1), var(--elevation-3);
  transition: box-shadow var(--motion-duration-slow) var(--motion-easing-decel);
}

/* The surface answers the form: while a field holds focus the card rides one
   rung higher on each layer, so the active surface is the top one without
   anything moving on the page. */
.login-card:focus-within {
  box-shadow: var(--elevation-2), var(--elevation-4);
}

.login-card:focus-visible {
  outline: var(--focus-ring-width) solid var(--focus-ring-color);
  outline-offset: var(--focus-ring-offset);
}

.login-form-wrap {
  --spring-card-o: 1;
  --spring-card-opacity: 1;
  @apply relative w-full flex flex-col gap-5;
  transform: translate3d(0, calc((1 - var(--spring-card-o)) * 12px), 0);
  opacity: var(--spring-card-opacity);
}

.login-header {
  @apply flex items-center justify-center;
  margin-bottom: clamp(18px, 3.2vh, 28px);
}

/* The wordmark is the whole lockup on this screen: no chip, no glyph. */
.brand-lockup {
  @apply inline-flex items-center;
}

.brand-name {
  @apply text-xl font-semibold;
  letter-spacing: -0.019em;
  color: var(--color-label-label);
}

.welcome-section {
  @apply flex flex-col gap-1.5;
}

/* The display step comes from the token, not from this rule: text-4xl
   resolves to tokens.js typography.fontSize['4xl'] (36px / 40px line-height /
   -0.022em tracking). The previous rule hardcoded weight 500 and -0.05em
   tracking, so the login was disobeying its own type scale. */
.welcome-headline {
  @apply text-3xl font-semibold;
  letter-spacing: -0.022em;
  color: var(--color-label-label);
}

.welcome-subtitle {
  @apply text-[13px] leading-[18px];
  color: var(--color-label-secondary-label);
}

.login-form-morph {
  @apply relative;
}

/* Phase 2.5 · form-card-morph crossfade. The TransitionGroup tag is a
   <div>; the active child is the <form> OR the mini-summary. The
   leave/enter rules below drive the opacity + translate crossfade. */
.form-card-morph-enter-active,
.form-card-morph-leave-active {
  transition:
    opacity 220ms ease-out,
    transform 220ms ease-out;
}
.form-card-morph-enter-from,
.form-card-morph-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
.form-card-morph-enter-to,
.form-card-morph-leave-from {
  opacity: 1;
  transform: translateY(0);
}

.login-form {
  @apply flex flex-col gap-4;
}

/* The submit takes the field's own geometry (52px, 10px radius) and the
   artboard's gradient, so the primary action reads as the same material as the
   controls it submits. */
.login-form :deep(button[type='submit']) {
  height: 52px;
  font-size: 0.875rem;
  font-weight: 600;
  letter-spacing: 0.002em;
  background-image: linear-gradient(180deg, var(--color-accent) 0%, var(--color-accent-hover) 100%);
  box-shadow: var(--elevation-3);
}

.login-form :deep(button[type='submit']:hover:not(:disabled)) {
  background-image: linear-gradient(
    180deg,
    var(--color-accent-hover) 0%,
    var(--color-accent-active) 100%
  );
}

.form-options {
  @apply flex flex-wrap items-center justify-end gap-2;
  margin-top: 11px;
}

.forgot-password-link {
  @apply text-[12.5px] leading-5 font-semibold underline underline-offset-4 focus:outline-none;
  text-decoration-color: var(--color-accent-200);
  color: var(--color-accent);
  background: transparent;
  border: none;
  padding: 0;
  cursor: pointer;
  transition:
    color 200ms ease-out,
    text-decoration-color 200ms ease-out;
}

/* #382: the reset route without a usable link, naming what is missing
   instead of rendering a mute login form. */
.reset-link-notice {
  @apply mb-5 flex flex-col items-start gap-2;
  padding: 12px 14px;
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-control);
  background: var(--color-canvas);
}

.reset-link-notice-text {
  @apply text-sm leading-relaxed;
  color: var(--color-label-label);
}

.forgot-password-link:hover {
  color: var(--color-accent-active);
  text-decoration-color: var(--color-accent);
  text-decoration: underline;
}

.password-toggle {
  @apply absolute inline-flex items-center justify-center focus:outline-none;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 32px;
  height: 32px;
  border-radius: var(--radius-full);
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
  background: var(--color-system-gray-100);
}

.password-toggle:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* Password reveal morph (recovered PR2) · both eye glyphs stay mounted in
   one 20px box and cross-fade + scale, instead of the abrupt v-if swap. */
.password-toggle-glyphs {
  @apply relative inline-flex items-center justify-center;
  width: 20px;
  height: 20px;
}

.password-toggle-glyph {
  position: absolute;
  inset: 0;
  width: 20px;
  height: 20px;
  opacity: 0;
  transform: scale(0.72) rotate(-12deg);
  transition:
    opacity var(--motion-duration-normal) var(--motion-easing-ios),
    transform var(--motion-duration-normal) var(--motion-easing-ios);
}

.password-toggle-glyph.is-active {
  opacity: 1;
  transform: scale(1) rotate(0deg);
}

.field {
  @apply flex flex-col gap-[7px];
}

.field-label {
  @apply text-[13px] leading-[18px] font-medium select-none;
  color: var(--color-label-label);
}

.field-input-wrap {
  @apply relative flex items-center;
}

/* Outlined fields: white fill and a visible hairline, so the control reads as a
   box on the white card instead of a grey patch. 52px tall, 10px radius, and
   the visible label names the field, so no placeholder duplicates it. */
.field-input {
  @apply block w-full text-base;
  background: var(--color-background-system-background);
  border: 1px solid var(--color-system-gray-500);
  border-radius: var(--radius-ios);
  color: var(--color-label-label);
  padding: 12px 52px 12px 15px;
  height: 52px;
  transition:
    background-color var(--motion-duration-normal) var(--motion-easing-ios),
    border-color var(--motion-duration-normal) var(--motion-easing-ios),
    box-shadow var(--motion-duration-normal) var(--motion-easing-ios);
}

.field-input::placeholder {
  color: var(--color-label-tertiary-label);
}

.field-input:hover:not(:disabled) {
  border-color: var(--color-system-gray-700);
}

.field-input:focus {
  outline: none;
  border-color: var(--color-accent);
  box-shadow: var(--focus-ring-default);
}

.field-input:disabled {
  background: var(--color-system-gray-100);
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

/* Live validation (recovered PR2) · the error message slides down and fades
   in rather than appearing in a single frame. */
.field-error--animated {
  animation: field-error-slide-in var(--motion-duration-normal) var(--motion-easing-ios);
}

@keyframes field-error-slide-in {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Live validation (recovered PR2) · the success checkmark. It is decorative:
   the programmatic signal is `aria-invalid="false"` on the input, so
   announcing "valid" on every keystroke would only add noise. The base state
   is the visible one and the animation supplies the entrance, which keeps
   the reduced-motion collapse honest (kill the animation, keep the mark). */
.field-success-mark {
  @apply absolute inline-flex items-center justify-center pointer-events-none;
  right: 16px;
  top: 50%;
  width: 22px;
  height: 22px;
  border-radius: var(--radius-full);
  color: var(--color-system-green-500);
  background: var(--color-system-green-50);
  opacity: 1;
  transform: translateY(-50%) scale(1);
  animation: field-success-mark-in var(--motion-duration-normal) var(--motion-easing-ios) both;
}

.field-success-mark svg {
  width: 14px;
  height: 14px;
}

/* The password field already spends its right edge on the reveal toggle. */
.field-success-mark--before-toggle {
  right: 52px;
}

@keyframes field-success-mark-in {
  from {
    opacity: 0;
    transform: translateY(-50%) scale(0);
  }
  to {
    opacity: 1;
    transform: translateY(-50%) scale(1);
  }
}

.field-input.has-success {
  border-color: var(--color-system-green-500);
}

.field-input.has-success:focus {
  border-color: var(--color-system-green-500);
  box-shadow: 0 0 0 3px var(--color-system-green-50);
}

/* Per-field entrance stagger (recovered PR2) · the fields used to appear in
   one frame. Each block carries --field-index and starts 60ms after the
   previous one; `backwards` keeps the from-state during the delay so the
   finished layout never flashes first. */
.login-field-stagger {
  animation: login-field-enter 260ms var(--motion-easing-ios) backwards;
  animation-delay: calc(var(--field-index, 0) * 60ms);
}

@keyframes login-field-enter {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.field-hint {
  @apply text-xs leading-snug;
  color: var(--color-label-secondary-label);
}

.auth-error {
  @apply flex items-start gap-2 p-3;
  border-radius: var(--login-radius-control);
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

/* The submit wrapper is the shake target, so the shake never fights the
   button's own transform states. */
.login-submit-wrap {
  @apply relative;
}

.login-submit-wrap.is-shaking {
  animation: login-submit-shake 220ms ease-in-out;
}

@keyframes login-submit-shake {
  0% {
    transform: translateX(0);
  }
  15% {
    transform: translateX(-6px);
  }
  30% {
    transform: translateX(6px);
  }
  45% {
    transform: translateX(-4px);
  }
  60% {
    transform: translateX(4px);
  }
  75% {
    transform: translateX(-2px);
  }
  100% {
    transform: translateX(0);
  }
}

/* Phase 2.4 · submit polymorphic stages. The button keeps a single
   min-width so the crossfade never reflows. */
.login-submit-shape {
  min-width: 220px;
  position: relative;
}

.login-submit-shape :deep(.login-submit-stage) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  white-space: nowrap;
}

.login-submit-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 9999px;
  background: currentColor;
  margin-right: 0.5rem;
  animation: login-submit-pulse 900ms ease-in-out infinite;
}

.login-submit-check {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 24;
  stroke-dashoffset: 24;
  animation: login-submit-check-draw 220ms ease-out forwards;
}

@keyframes login-submit-pulse {
  0%,
  100% {
    opacity: 0.35;
    transform: scale(0.85);
  }
  50% {
    opacity: 1;
    transform: scale(1.05);
  }
}

@keyframes login-submit-check-draw {
  to {
    stroke-dashoffset: 0;
  }
}

/* Phase 2.5 · mini-summary on success. */
.login-mini-summary {
  @apply flex flex-col items-center gap-3 py-6;
  text-align: center;
}

.login-mini-avatar {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-full);
  background: var(--color-accent-500);
  color: var(--color-background-system-background);
  @apply flex items-center justify-center text-sm font-semibold;
}

.login-mini-name {
  @apply text-base font-medium;
  color: var(--color-label-label);
}

.login-mini-role {
  @apply text-xs;
  color: var(--color-label-secondary-label);
}

.login-mini-cta {
  @apply inline-flex items-center justify-center px-4 py-2 text-sm font-medium;
  border-radius: var(--login-radius-control);
  background: var(--color-accent-500);
  color: var(--color-background-system-background);
  border: none;
  cursor: pointer;
  transition: background-color 200ms ease-out;
}

.login-mini-cta:hover {
  background: var(--color-accent-600);
}

/* Contact block inside the card, and the legal note below it: the artboard's
   two registers. The card keeps the door that belongs to it ("no tienes
   cuenta"), the field keeps the fine print. */
/* Divider and federated access: a hairline rule that names what the two doors
   below are for, then the two brand buttons at the field's own 10px radius. */
.login-divider {
  @apply flex items-center gap-3 text-[12px] leading-[18px];
  color: var(--color-label-secondary-label);
}

.login-divider::before,
.login-divider::after {
  content: '';
  flex: 1 1 0;
  height: 1px;
  background: var(--color-hairline);
}

.login-sso {
  @apply grid grid-cols-2 gap-[10px];
}

/* Same interaction contract as the primitive's buttons: the five animated
   properties with the primitive's own durations and curves, the -1px lift on
   hover and the press on pointer-down. Only the paint differs (transparent
   border, no gradient), because these are second-level doors. */
.login-sso-btn {
  @apply inline-flex items-center justify-center gap-[9px] font-semibold whitespace-nowrap;
  height: 48px;
  padding: 0 10px;
  font-size: 13px;
  line-height: 18px;
  color: var(--color-label-label);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-ios);
  background: transparent;
  cursor: pointer;
  transition:
    background-color var(--motion-duration-normal) ease-out,
    border-color var(--motion-duration-normal) ease-out,
    color var(--motion-duration-normal) ease-out,
    box-shadow var(--motion-duration-normal) ease-out,
    transform var(--motion-duration-fast) var(--motion-easing-ios);
}

.login-sso-btn:hover {
  border-color: var(--color-system-gray-500);
  background: var(--color-canvas);
  transform: translateY(-1px);
}

.login-sso-btn:active {
  transform: translateY(1px);
}

.login-contact {
  @apply text-center text-[13px] leading-[18px];
  color: var(--color-label-secondary-label);
}

.login-contact-link {
  @apply block font-semibold;
  color: var(--color-accent);
}

.login-contact-link:hover {
  text-decoration: underline;
}

.login-legal {
  width: min(404px, 100%);
  margin-top: 16px;
  font-size: 11.5px;
  line-height: 17px;
  text-align: center;
  text-wrap: balance;
  color: var(--color-label-secondary-label);
}

.login-legal + .login-legal {
  margin-top: 6px;
}

.login-legal-link {
  @apply font-semibold underline underline-offset-[3px];
  text-decoration-color: var(--color-accent-200);
  color: var(--color-accent);
}

.login-legal-link:hover {
  text-decoration-color: var(--color-accent);
}

/* Mobile: the card takes the safe width and the gaps tighten so the form
   stays readable above the decorative scene. */
@media (max-width: 767px) {
  .login-stage {
    padding: 12px;
  }
  .login-card {
    max-height: calc(100dvh - 24px);
    padding: 24px 20px;
  }
  .login-form-wrap {
    gap: 16px;
  }
}

/* Honor reduced motion. Both entrance springs collapse in the FIRST rule of
   this block, then the polymorphic crossfades. */
@media (prefers-reduced-motion: reduce) {
  .login-form-wrap,
  .login-backdrop {
    transform: none !important;
    opacity: 1 !important;
    transition: none !important;
  }

  .login-card,
  .login-submit-shape,
  .login-mini-summary {
    animation: none !important;
    transition: none !important;
    transform: none !important;
  }

  /* Recovered PR2 · collapse for every new motion path. The movement goes,
     the state stays legible: opacity still carries which glyph is active
     and the success mark / tick simply arrive already drawn. Positioning
     transforms (the -50% centering on the password toggle) are deliberately
     NOT reset. */
  .login-field-stagger,
  .login-submit-wrap.is-shaking,
  .field-error--animated,
  .field-success-mark,
  .login-submit-dot,
  .login-submit-check {
    animation: none !important;
  }

  .login-submit-check {
    stroke-dashoffset: 0;
  }

  .password-toggle-glyph {
    transition: none !important;
    transform: none !important;
  }

  .form-card-morph-enter-active,
  .form-card-morph-leave-active {
    transition: none !important;
  }
}

/* Honor reduced transparency: the card flattens to an opaque surface and
   the decorative scene STAYS visible: its layers repaint from solid tints
   (DentalParallaxBackground) instead of disappearing. */
@media (prefers-reduced-transparency: reduce) {
  .login-page {
    background: var(--color-canvas);
  }
  .login-backdrop {
    display: block;
  }
  .login-card {
    background: var(--color-background-system-background);
    box-shadow: none;
  }
}

/* High contrast · lift both text colors to AAA-legible label tokens, and
   give the recovered validation surfaces a stronger edge. */
@media (prefers-contrast: more) {
  .welcome-headline {
    color: var(--color-label-label);
    & + .welcome-subtitle {
      color: var(--color-label-secondary-label);
    }
  }
  .forgot-password-link,
  .login-contact-link,
  .login-legal-link {
    text-decoration: underline;
  }
  .field-success-mark {
    color: var(--color-system-green-700);
    border: 1px solid var(--color-system-green-700);
  }
  .field-input.has-success {
    border-color: var(--color-system-green-700);
  }
}
</style>
