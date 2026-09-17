<template>
  <!-- Login page · editorial split (Phase 2.1 / HOTFIX-LOGIN-010).
       Premium craft pass: the form rests directly on the panel. The
       elevated card wrapper that used to sit inside this panel is gone, so
       the surface hierarchy comes from the panel itself instead of a second
       bordered, shadowed box stacked on the first. -->
  <div class="login-page">
    <div class="login-page-shell">
      <div class="login-split-card rounded-[var(--radius-shell)]">
        <div class="login-grid">
          <!-- Form column (left on desktop, second on mobile).
               Phase 2.5 · the TransitionGroup crossfades the <form> into the
               mini-summary; both live in the same group so the crossfade
               uses one enter/leave contract. -->
          <section class="login-form-column" aria-labelledby="login-headline">
            <div ref="cardRef" class="login-form-wrap">
              <!-- Wordmark chip: a hairline pill anchoring the top-left so
                   the brand reads as a mark instead of competing with the
                   H1 below it. -->
              <header class="login-header">
                <p class="brand-chip">
                  <!-- Brand glyph morph (recovered PR2): the tooth hands over
                       to a check when the auth state reaches `success`. Both
                       glyphs stay mounted in one grid cell and cross-fade
                       (opacity + scale) so no path-command parity between
                       the two `d` attributes is required. The chip markup is
                       unchanged; only its inner glyph became polymorphic. -->
                  <span class="brand-glyph" aria-hidden="true">
                    <svg
                      class="brand-glyph-tooth"
                      :class="{ 'is-hidden': state === 'success' }"
                      width="18"
                      height="18"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.75"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <path d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z" />
                    </svg>
                    <svg
                      class="brand-glyph-check"
                      :class="{ 'is-visible': state === 'success' }"
                      width="18"
                      height="18"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2.1"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <path d="M5 12 L10 17 L19 7" />
                    </svg>
                  </span>
                  <span class="brand-name">OdontoSuite</span>
                </p>
              </header>

              <div class="welcome-section">
                <h1 id="login-headline" class="welcome-headline">
                  Gestiona tu clínica con calma
                </h1>
                <p class="welcome-subtitle">
                  Inicia sesión para revisar citas, caja y pacientes en un solo lugar.
                </p>
              </div>

              <!-- Phase 2.5 · form → mini-summary polymorphism. No Card
                   wrapper: the fields are the panel's own content. -->
              <TransitionGroup name="form-card-morph" tag="div" class="login-form-morph">
                <form
                  v-if="state !== 'success'"
                  key="form"
                  class="login-form"
                  novalidate
                  :aria-busy="loading || undefined"
                  @submit.prevent="handleLogin"
                >
                  <div class="field login-field-stagger" :style="{ '--field-index': 0 }">
                    <label class="field-label" for="login-username">Usuario</label>
                    <div class="field-input-wrap">
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
                        :aria-invalid="!!fieldErrors.username"
                        :aria-describedby="
                          fieldErrors.username ? 'login-username-error' : 'login-username-hint'
                        "
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
                        :disabled="loading"
                        :aria-invalid="!!fieldErrors.password"
                        :aria-describedby="
                          fieldErrors.password ? 'login-password-error' : 'login-password-hint'
                        "
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
                        tabindex="-1"
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
                              d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
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
                  </div>

                  <div class="form-options login-field-stagger" :style="{ '--field-index': 2 }">
                    <label class="remember-me">
                      <input
                        v-model="form.remember"
                        type="checkbox"
                        class="checkbox-input"
                        :disabled="loading"
                      />
                      <!-- Drawn checkbox: the native control keeps the state,
                           the keyboard reach and the accessibility tree, but
                           paints nothing. The visible box and the tick are
                           drawn here so the check can be *drawn* on
                           stroke-dashoffset instead of snapping on. -->
                      <span class="checkbox-box" aria-hidden="true">
                        <svg
                          class="checkbox-tick"
                          viewBox="0 0 16 16"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2.2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        >
                          <path d="M3.5 8.5 L6.5 11.5 L12.5 5" />
                        </svg>
                      </span>
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

                  <div v-if="error && state !== 'error'" class="auth-error" role="alert" aria-live="polite">
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

                  <!-- Phase 2.4 · polymorphic submit button. Single
                       <UiButton> with 5 inner <span> children gated by
                       v-show on the shape-morph state.
                       Recovered PR2 · the wrapping div carries the magnet
                       ref: useMagneticHover attaches its two springs to THIS
                       element and writes --spring-magnet-x/y on it, and the
                       button inherits the custom properties (they are
                       inherited by default) so Button.vue's additive
                       [data-magnetic='true'] rule can consume them. The
                       wrapper is also the shake target, which keeps the
                       shake off the button's own transform state machine. -->
                  <div
                    ref="submitRef"
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
                      :full-width="true"
                      :data-magnetic="magnetEnabled"
                      data-state="shape-morph"
                      class="login-submit-shape"
                    >
                      <span
                        v-show="state === 'idle'"
                        v-motion="morphMotion"
                        class="login-submit-stage"
                      >Iniciar sesión</span>
                      <span
                        v-show="state === 'validating'"
                        v-motion="morphMotion"
                        class="login-submit-stage"
                      >
                        <span class="login-submit-dot" aria-hidden="true" />
                        Validando
                      </span>
                      <span
                        v-show="state === 'authenticating'"
                        v-motion="morphMotion"
                        class="login-submit-stage"
                      >
                        <span class="login-submit-dot" aria-hidden="true" />
                        Autenticando
                      </span>
                      <span
                        v-show="state === 'success'"
                        v-motion="morphMotion"
                        class="login-submit-stage"
                      >
                        <svg class="login-submit-check" viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M5 12 L10 17 L19 7" />
                        </svg>
                        Listo
                      </span>
                      <span
                        v-show="state === 'error'"
                        v-motion="morphMotion"
                        class="login-submit-stage"
                      >Reintentar</span>
                    </UiButton>
                  </div>
                </form>

                <!-- Phase 2.5 · MiniSummary on success state. Avatar with
                     initials, role label, and a manual "Ir al dashboard"
                     button as a defensive fallback if the router.push
                     timed out. -->
                <div
                  v-else
                  key="summary"
                  class="login-mini-summary"
                  role="status"
                  aria-live="polite"
                >
                  <div class="login-mini-avatar" aria-hidden="true">
                    {{ miniSummaryInitials }}
                  </div>
                  <p class="login-mini-name">{{ miniSummaryName }}</p>
                  <p v-if="miniSummaryRole" class="login-mini-role">
                    {{ miniSummaryRole }}
                  </p>
                  <button
                    type="button"
                    class="login-mini-cta"
                    @click="router.push('/dashboard')"
                  >Ir al dashboard</button>
                </div>
              </TransitionGroup>

              <!-- Footer · ONE row: the legal link and the support link. The
                   brand already sits at the top of this column, so the old
                   copyright note repeating it was noise. -->
              <div class="login-footer">
                <a
                  href="/terminos"
                  target="_blank"
                  rel="noopener"
                  class="login-footer-link"
                >Términos y Condiciones</a>
                <a
                  href="mailto:admin@odontosuite.local"
                  class="login-footer-link"
                >Contacta al administrador</a>
              </div>
            </div>
          </section>

          <!-- Phase 2.2 · Hero column. The right column carries the dental
               still with 3 floating overlay widgets. The @error handler
               swaps to a static SVG placeholder (D6 in design.md). The hero
               column is `position: relative` and the overlays use absolute
               positioning per design D5. -->
          <aside class="login-hero-column" aria-hidden="true" ref="heroRef" data-spring-hero="true">
            <div class="login-hero">
              <img
                v-if="!imageFailed"
                src="/images/ui/login-hero.jpg"
                alt="Interior de una clínica dental moderna"
                loading="lazy"
                decoding="async"
                class="login-hero-image"
                @error="onImageError"
              />
              <!-- Phase 2.2 · SVG fallback (D6). Static tooth glyph over a
                   soft gradient. Reserved aspect ratio so the layout never
                   collapses while loading. -->
              <div v-else class="login-hero-fallback" aria-hidden="true">
                <svg
                  width="96"
                  height="96"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="login-hero-fallback-glyph"
                >
                  <path d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z" />
                </svg>
              </div>

              <!-- Phase 2.3 · Overlay widget 1, curated sample. Top-right. -->
              <div
                v-motion="overlayMotion(0)"
                class="login-overlay-card login-overlay-card--top"
              >
                <p class="login-overlay-label">Pacientes activos</p>
                <p class="login-overlay-figure">
                  {{ formatThousands(LOGIN_SAMPLE.activePatients) }}
                </p>
              </div>

              <!-- Phase 2.3 · Overlay widget 2, curated sample. Mid-left. -->
              <div
                v-motion="overlayMotion(1)"
                class="login-overlay-card login-overlay-card--mid"
              >
                <p class="login-overlay-label">Agenda de hoy</p>
                <ul class="login-overlay-list">
                  <li
                    v-for="slot in LOGIN_SAMPLE.agenda"
                    :key="slot.time"
                    class="login-overlay-list-row"
                  >
                    <span class="login-overlay-list-time">{{ slot.time }}</span>
                    <span class="login-overlay-list-procedure">{{ slot.procedure }}</span>
                  </li>
                </ul>
              </div>

              <!-- Phase 2.3 · Overlay widget 3, curated sample. Bottom-right. -->
              <div
                v-motion="overlayMotion(2)"
                class="login-overlay-card login-overlay-card--bottom"
              >
                <p class="login-overlay-label">Equipo</p>
                <div class="login-overlay-avatars">
                  <span
                    v-for="initials in LOGIN_SAMPLE.team"
                    :key="initials"
                    class="login-overlay-avatar"
                  >{{ initials }}</span>
                </div>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </div>

    <ForgotPasswordModal v-model="showForgotPasswordModal" @success="handleForgotPasswordSuccess" />
    <ResetPasswordModal
      v-model="showResetPasswordModal"
      :email="resetEmail"
      @success="handleResetPasswordSuccess"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useSpring } from '@/composables/useSpring'
import { useAuth } from '@/composables/useAuth'
import { useShapeMorph } from '@/composables/useShapeMorph'
import { useReducedMotion } from '@/composables/useReducedMotion'
import { useMagneticHover } from '@/composables/useMagneticHover'
import { useFieldValidation } from '@/composables/useFieldValidation'
import ForgotPasswordModal from './ForgotPasswordModal.vue'
import ResetPasswordModal from './ResetPasswordModal.vue'
import UiButton from '@/components/ui/Button.vue'

const router = useRouter()
const { login } = useAuth()

// Phase 2.4 · polymorphic submit state machine.
const { state, transition, release } = useShapeMorph({ initial: 'idle', dwellMs: 200 })

// Phase 2.6 · reactive reduced-motion detector.
const prefersReducedMotion = useReducedMotion()

// State
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const showForgotPasswordModal = ref(false)
const showResetPasswordModal = ref(false)
const resetEmail = ref('')

// Phase 2.2 · image fallback state.
const imageFailed = ref(false)

// Phase 2.3 · hero widget content. The login is a PUBLIC screen: the three
// widgets used to fetch dashboard stats, today's appointments and the active
// user list on mount, and every one of those endpoints answers 401 for a
// guest, which rendered "PACIENTES ACTIVOS 0" and an empty avatar row. The
// widgets now render one fixed curated sample instead: zero requests, no
// empty state, never broken. Aggregate only by design (numbers, procedure
// types, times) and never a patient name, because a public screen showing
// names reads as a data leak.
const LOGIN_SAMPLE = Object.freeze({
  activePatients: 1284,
  agenda: Object.freeze([
    { time: '09:30', procedure: 'Limpieza' },
    { time: '11:00', procedure: 'Endodoncia' },
    { time: '15:45', procedure: 'Control de ortodoncia' }
  ]),
  team: Object.freeze(['AM', 'JR', 'CS'])
})

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
const {
  errors: validationErrors,
  successes: validationSuccesses,
  validateField,
  validateAll
} = useFieldValidation(
  form,
  {
    username: [(value) => (value && value.trim() ? null : 'El usuario es requerido')],
    password: [(value) => (value && value.trim() ? null : 'La contraseña es requerida')]
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
const miniSummaryRole = computed(() => {
  const role = successUser.value?.role
  if (!role) return ''
  const map = {
    admin: 'Administrador',
    doctor: 'Odontólogo',
    receptionist: 'Recepción',
    assistant: 'Asistente'
  }
  return map[role] || role
})

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

// HOTFIX-LOGIN-007 · Hero column mirror spring.
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
// Recovered PR2 · the magnet binds to the wrapper div that surrounds the
// submit UiButton, not to the component ref (a component ref does not
// expose its root element). The springs write --spring-magnet-x/y on the
// wrapper and the button inherits the custom properties, which is what
// Button.vue's additive [data-magnetic='true'] rule consumes.
const submitRef = ref(null)

// `magnetEnabled` keeps the magnet live only while the button is actually
// interactive (idle / error). Button.vue only paints the magnetic transform
// under [data-magnetic='true'], so a disabled or morphing button never gets
// it. `useMagneticHover` additionally short-circuits by itself on
// prefers-reduced-motion and on coarse pointers.
const magnetEnabled = computed(() => state.value === 'idle' || state.value === 'error')

useMagneticHover(submitRef, { response: 0.35, damping: 0.7, maxDistanceFactor: 0.4 })

// Recovered PR2 · 220ms failure shake. `shakeTrigger` is a one-shot class
// toggle; re-arming happens on the next frame so a second failed attempt
// restarts the animation instead of reusing the finished one.
const SHAKE_MS = 220
const shakeTrigger = ref(false)
let shakeTimer = null
let shakeRaf = null

function triggerShake() {
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

// Phase 2.6 + D9 · motion variants per element. Reduced-motion collapses
// every transition to opacity-only.
function morphMotion() {
  return prefersReducedMotion.value
    ? {
        initial: { opacity: 0 },
        enter: { opacity: 1, transition: { duration: 150 } },
        leave: { opacity: 0, transition: { duration: 100 } }
      }
    : {
        initial: { opacity: 0, y: -4 },
        enter: { opacity: 1, y: 0, transition: { duration: 200 } },
        leave: { opacity: 0, y: 4, transition: { duration: 150 } }
      }
}

function overlayMotion(index) {
  return prefersReducedMotion.value
    ? {
        initial: { opacity: 0 },
        enter: { opacity: 1, transition: { duration: 200, delay: 100 + index * 80 } }
      }
    : {
        initial: { opacity: 0, y: 8 },
        enter: {
          opacity: 1,
          y: 0,
          transition: { duration: 320, delay: 100 + index * 80, ease: [0.16, 1, 0.3, 1] }
        }
      }
}

function initialsOf(name) {
  if (!name) return '··'
  const parts = String(name).trim().split(/\s+/).slice(0, 2)
  return parts.map((p) => p.charAt(0).toUpperCase()).join('') || '··'
}

function formatThousands(n) {
  const value = Number(n || 0)
  return new Intl.NumberFormat('es-PE').format(value)
}

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

// Phase 2.2 · image fallback handler (D6).
function onImageError() {
  imageFailed.value = true
}

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
  await new Promise((resolve) => setTimeout(resolve, 220))

  if (!transition('authenticating')) {
    return
  }
  loading.value = true

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
  } finally {
    loading.value = false
  }
}

const handleForgotPasswordSuccess = (data) => {
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
/* Nested radius rhythm (roughly 1.4x per step): shell 32px (the shell/panel/
   control ladder lives in the shared tokens since Slice A3, so the login no
   longer owns a parallel scale) → panel/widget 22px → control 12px → pill
   (--radius-full). The previous 32/16/8 ladder jumped 4x from widget to
   control and read as carelessness. */
.login-page {
  --login-radius-panel: var(--radius-panel);
  --login-radius-control: var(--radius-control);
  @apply min-h-[100dvh] w-full flex items-stretch justify-center;
  position: relative;
  background: var(--color-canvas);
  /* `height: 100dvh` clamps the page to the dynamic viewport (avoids the
     iOS Safari URL-bar reflow); `min-height` is a fallback for older
     browsers that do not support dvh. */
  height: 100dvh;
  min-height: 100vh;
  overflow: hidden;
}

/* Atmosphere: the flat canvas becomes a lit field. One soft radial that
   opens white at the centre and falls to the canvas tone at the edges, plus
   one very faint accent wash in the upper right. Static by design (a slow
   loop is banned by apple-design §14), so there is no motion path to
   collapse under prefers-reduced-motion; it IS switched off under
   prefers-reduced-transparency below. If `color-mix` is unsupported the
   declaration is dropped and the flat canvas above remains. */
.login-page::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background:
    radial-gradient(
      60% 55% at 82% 6%,
      color-mix(in srgb, var(--color-accent) 4%, transparent) 0%,
      transparent 70%
    ),
    radial-gradient(
      circle at 50% 28%,
      var(--color-background-system-background) 0%,
      var(--color-canvas) 74%
    );
}

.login-page-shell {
  @apply w-full flex items-stretch justify-center;
  position: relative;
  z-index: 1;
  /* Reduced from clamp(16px, 4vw, 48px) to clamp(12px, 2vw, 24px): the
     previous max(48px) added 96px of vertical padding on desktop, pushing
     the card past the viewport. */
  padding: clamp(12px, 2vw, 24px);
  min-height: 0;
}

.login-split-card {
  position: relative;
  display: grid;
  width: 100%;
  max-width: 1180px;
  /* The card grows to fill the shell (which fills the viewport) but never
     exceeds `viewport - shell padding`. Without this cap the card grew to
     902px on a 900px viewport, causing a 98px vertical overflow. */
  height: 100%;
  max-height: calc(100dvh - clamp(24px, 4vw, 48px));
  background: var(--color-background-system-background);
  border: 1px solid var(--color-hairline);
  box-shadow:
    0 24px 64px rgba(0, 0, 0, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.5);
  overflow: hidden;
}

.login-grid {
  @apply grid w-full;
  grid-template-columns: 1fr;
  /* `minmax(0, 1fr)` lets the row shrink below its content size, which is
     the prerequisite for the form column's `overflow-y: auto` to fire. */
  grid-template-rows: minmax(0, 1fr);
  min-height: 0;
  height: 100%;
}

/* The panel owns the form's inset. This used to be the inner card's
   `padding="lg"`; with that wrapper gone the panel's own padding is the
   single source of breathing room around the fields. */
.login-form-column {
  @apply order-2 flex items-center justify-center px-6 py-10 sm:px-8;
  /* The form column scrolls internally if the form is taller than the
     available height. `min-height: 0` is required for the scroll to fire
     inside a CSS grid cell. */
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
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

/* Wordmark chip: hairline-bordered pill, 18px glyph + name. */
.brand-chip {
  @apply inline-flex items-center gap-2;
  padding: 6px 14px 6px 10px;
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-full);
  background: var(--color-background-system-background);
}

.brand-glyph {
  @apply inline-grid place-items-center;
  width: 22px;
  height: 22px;
  color: var(--color-system-blue-500);
}

/* Both glyphs share one grid cell so the cross-fade is a stack and never a
   layout shift. The tooth hands over to the check on `success`. */
.brand-glyph svg {
  grid-area: 1 / 1;
  width: 18px;
  height: 18px;
  transition:
    opacity 300ms var(--motion-easing-ios),
    transform 300ms var(--motion-easing-ios);
}

.brand-glyph-tooth.is-hidden {
  opacity: 0;
  transform: scale(0.6) rotate(18deg);
}

.brand-glyph-check {
  opacity: 0;
  transform: scale(0.6) rotate(-18deg);
}

.brand-glyph-check.is-visible {
  opacity: 1;
  transform: scale(1) rotate(0deg);
}

.brand-name {
  @apply text-sm font-semibold tracking-tight;
  color: var(--color-label-label);
}

.welcome-section {
  @apply flex flex-col gap-2;
}

/* The display step comes from the token, not from this rule: text-4xl
   resolves to tokens.js typography.fontSize['4xl'] (36px / 40px line-height /
   -0.022em tracking). The previous rule hardcoded weight 500 and -0.05em
   tracking, so the login was disobeying its own type scale. */
.welcome-headline {
  @apply text-4xl font-semibold;
  letter-spacing: -0.022em;
  color: var(--color-label-label);
}

.welcome-subtitle {
  @apply text-base leading-relaxed;
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
  transition: opacity 220ms ease-out, transform 220ms ease-out;
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
  @apply flex flex-col gap-5;
}

/* HOTFIX-LOGIN-004 · primary submit button keeps the elevation + inset
   highlight inline at the consumer (LoginPage) as a defensive backstop. */
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
  @apply relative inline-flex items-center gap-2 cursor-pointer select-none;
}

/* Recordarme · the native control keeps the state, the keyboard reach and
   the accessibility tree, but paints nothing. The box and the tick below
   are the visible surface, which is what lets the tick be *drawn*. */
.checkbox-input {
  position: absolute;
  width: 18px;
  height: 18px;
  margin: 0;
  opacity: 0;
  pointer-events: none;
}

.checkbox-box {
  @apply inline-flex flex-shrink-0 items-center justify-center;
  width: 18px;
  height: 18px;
  border: 1.5px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-canvas);
  color: var(--color-background-system-background);
  transition:
    background-color var(--motion-duration-normal) var(--motion-easing-ios),
    border-color var(--motion-duration-normal) var(--motion-easing-ios);
}

.checkbox-input:checked ~ .checkbox-box {
  background: var(--color-accent);
  border-color: var(--color-accent);
}

.checkbox-input:focus-visible ~ .checkbox-box {
  box-shadow: var(--focus-ring-default);
}

/* The tick is drawn, not swapped: stroke-dashoffset runs 14 → 0 when the
   native input is checked. */
.checkbox-tick {
  width: 12px;
  height: 12px;
  stroke-dasharray: 14;
  stroke-dashoffset: 14;
  transition: stroke-dashoffset var(--motion-duration-normal) var(--motion-easing-ios);
}

.checkbox-input:checked ~ .checkbox-box .checkbox-tick {
  stroke-dashoffset: 0;
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
  background: var(--color-cream-200);
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
  @apply flex flex-col gap-1.5;
}

.field-label {
  @apply text-sm font-medium select-none;
  color: var(--color-label-label);
}

.field-input-wrap {
  @apply relative flex items-center;
}

/* Filled fields: no adornment icon, no border at rest, canvas fill, 12px
   radius, 52px tall. The visible label names the field, so no placeholder
   duplicates it. The focus ring is the verified accent ring token. */
.field-input {
  @apply block w-full text-base;
  background: var(--color-canvas);
  border: 1px solid transparent;
  border-radius: var(--login-radius-control);
  color: var(--color-label-label);
  padding: 14px 52px 14px 16px;
  min-height: 52px;
  transition:
    background-color var(--motion-duration-normal) var(--motion-easing-ios),
    border-color var(--motion-duration-normal) var(--motion-easing-ios),
    box-shadow var(--motion-duration-normal) var(--motion-easing-ios);
}

.field-input::placeholder {
  color: var(--color-label-tertiary-label);
}

.field-input:hover:not(:disabled) {
  background: var(--color-cream-100);
}

.field-input:focus {
  outline: none;
  border-color: var(--color-accent);
  box-shadow: var(--focus-ring-default);
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

/* Recovered PR2 · the submit wrapper is the magnet + shake target. The
   magnet writes --spring-magnet-x/y here and the button inherits them
   (custom properties are inherited by default), which is what Button.vue's
   additive [data-magnetic='true'] rule consumes. The shake lives here so it
   never fights the button's own transform states. */
.login-submit-wrap {
  @apply relative;
}

.login-submit-wrap.is-shaking {
  animation: login-submit-shake 220ms ease-in-out;
}

@keyframes login-submit-shake {
  0% { transform: translateX(0); }
  15% { transform: translateX(-6px); }
  30% { transform: translateX(6px); }
  45% { transform: translateX(-4px); }
  60% { transform: translateX(4px); }
  75% { transform: translateX(-2px); }
  100% { transform: translateX(0); }
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
  0%, 100% { opacity: 0.35; transform: scale(0.85); }
  50%      { opacity: 1;    transform: scale(1.05); }
}

@keyframes login-submit-check-draw {
  to { stroke-dashoffset: 0; }
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
  background: var(--color-system-blue-500);
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
  background: var(--color-system-blue-500);
  color: var(--color-background-system-background);
  border: none;
  cursor: pointer;
  transition: background-color 200ms ease-out;
}

.login-mini-cta:hover {
  background: var(--color-system-blue-600);
}

/* Single footer row: legal + support. */
.login-footer {
  @apply flex flex-wrap items-center justify-between gap-2 text-xs;
  color: var(--color-label-tertiary-label);
}

.login-footer-link {
  color: var(--color-accent);
  transition: color 200ms ease-out;
}

.login-footer-link:hover {
  color: var(--color-accent-active);
  text-decoration: underline;
}

/* Phase 2.2 · hero column. The hero carries the dental image with 3
   floating overlay widgets (D5). The hero-column is `position: relative`
   so the absolute overlays anchor correctly. `min-height: 0` and
   `overflow: hidden` prevent the image from forcing a grid reflow.
   The 16px padding is the inset that stops the hero from butting against
   the panel's top, right and bottom edges: that gap is what reads as care. */
.login-hero-column {
  --spring-hero-o: 1;
  --spring-hero-opacity: 1;
  @apply order-1 relative overflow-hidden flex items-stretch justify-stretch;
  min-height: 220px;
  padding: 16px;
  transform: translate3d(0, calc((1 - var(--spring-hero-o)) * 12px), 0);
  opacity: var(--spring-hero-opacity);
}

.login-hero {
  position: relative;
  width: 100%;
  min-height: 100%;
  background: var(--color-system-gray-50);
  overflow: hidden;
  border-radius: var(--login-radius-panel);
}

.login-hero-image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.login-hero-fallback {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background:
    linear-gradient(135deg, var(--color-system-gray-50), var(--color-system-blue-50));
  color: var(--color-system-blue-500);
}

.login-hero-fallback-glyph {
  width: clamp(64px, 12vw, 96px);
  height: clamp(64px, 12vw, 96px);
}

/* Phase 2.3 · overlay widgets. Per design D5: top-right, mid-left,
   bottom-right. Per-panel radius, a soft tokenised two-layer elevation and
   a luminous top edge so the glass reads as a material. The old
   `0 1px 2px rgba(60, 60, 67, 0.55)` second layer was a 55%-opacity typo
   that drew a dirty dark edge around every widget. */
.login-overlay-card {
  position: absolute;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border: 1px solid var(--color-hairline);
  border-radius: var(--login-radius-panel);
  padding: 14px 16px;
  box-shadow:
    var(--elevation-2),
    inset 0 1px 0 rgba(255, 255, 255, 0.4);
  color: var(--color-label-label);
  z-index: 2;
}

.login-overlay-card--top {
  top: 24px;
  right: 24px;
}

.login-overlay-card--mid {
  top: 50%;
  left: 24px;
  transform: translateY(-50%);
}

.login-overlay-card--bottom {
  bottom: 24px;
  right: 24px;
}

/* Sentence case at 12px, medium weight, secondary label colour. Capitalised
   eyebrows with wide tracking are the anti-pattern this project banned. */
.login-overlay-label {
  @apply text-xs font-medium;
  color: var(--color-label-secondary-label);
  margin: 0 0 6px;
}

.login-overlay-figure {
  @apply text-2xl font-semibold leading-none;
  color: var(--color-label-label);
  margin: 0;
}

.login-overlay-list {
  @apply flex flex-col gap-1 m-0 p-0 list-none;
}

/* Fixed first column so the times line up across the three rows instead of
   drifting with `justify-content: space-between`. */
.login-overlay-list-row {
  @apply grid items-baseline gap-3 text-xs;
  grid-template-columns: 3.25rem 1fr;
}

.login-overlay-list-time {
  color: var(--color-label-secondary-label);
  font-variant-numeric: tabular-nums;
}

.login-overlay-list-procedure {
  color: var(--color-label-label);
}

.login-overlay-avatars {
  @apply flex items-center gap-2;
}

.login-overlay-avatar {
  width: 24px;
  height: 24px;
  border-radius: var(--radius-full);
  background: var(--color-system-blue-500);
  color: var(--color-background-system-background);
  @apply inline-flex items-center justify-center text-xs font-semibold;
}

/* Tablet and up: form first column, hero second. */
@media (min-width: 768px) {
  .login-grid {
    grid-template-columns: 5fr 7fr;
  }
  .login-form-column {
    @apply order-1 px-12 py-12;
  }
  .login-hero-column {
    @apply order-2;
    /* Slice 12 / fix-viewport-fit: removed `min-height: 100dvh`, which was
       the root cause of the 98px vertical overflow on 1440x900. The column
       now sizes to the grid row, which is constrained by the card's
       `max-height: calc(100dvh - 48px)`. `min-height: 0` keeps the grid
       from forcing the column to its content size. */
    min-height: 0;
  }
  .login-form-wrap {
    max-width: 28rem;
  }
}

/* Mobile (below 768px): single column, hero fixed at 240px, form takes the
   remaining height and scrolls internally. The page itself never scrolls. */
@media (max-width: 767px) {
  .login-page { padding: 0; }
  .login-page-shell { padding: 0; max-width: 100%; }
  .login-split-card {
    border-radius: 0;
    box-shadow: none;
    border-left: 0;
    border-right: 0;
    max-height: 100dvh;
  }
  /* Switch the grid from a 1-column / 1-row layout to a 1-column /
     2-row layout: hero on top (240px), form below (1fr). Without this
     the form and the hero would render in the same row and visually
     overlap. */
  .login-grid {
    grid-template-columns: 1fr;
    grid-template-rows: 240px minmax(0, 1fr);
  }
  .login-hero-column {
    height: 240px;
    min-height: 240px;
    order: 1;
  }
  .login-form-column {
    padding: 24px;
    order: 2;
    /* Mobile: top-align the form so it does not visually overlap with the
       hero's bottom edge. Centering the form inside a column that is
       shorter than the natural content height pushes the form's first
       row upward into the hero's z-stack region. */
    align-items: flex-start;
    justify-content: flex-start;
  }
  .login-form-wrap {
    /* Mobile: drop the gap to 16px to keep the form compact and the
       content's vertical footprint smaller. */
    gap: 16px;
  }
}

/* Honor reduced motion. Both entrance springs collapse in the FIRST rule of
   this block, then the polymorphic crossfades and the widget entrance. */
@media (prefers-reduced-motion: reduce) {
  .login-form-wrap,
  .login-hero-column {
    transform: none !important;
    opacity: 1 !important;
    transition: none !important;
  }

  .login-split-card,
  .login-overlay-card,
  .login-submit-shape,
  .login-mini-summary {
    animation: none !important;
    transition: none !important;
    transform: none !important;
  }

  /* The mid widget keeps its own centering transform: the collapse above
     must kill the entrance, never the position. */
  .login-overlay-card--mid {
    transform: translateY(-50%) !important;
  }

  /* Recovered PR2 · collapse for every new motion path. The movement goes,
     the state stays legible: opacity still carries which glyph is active
     and the success mark / tick simply arrive already drawn. Positioning
     transforms (the -50% centering) are deliberately NOT reset; the same
     exception the mid widget above documents. */
  .login-field-stagger,
  .login-submit-wrap.is-shaking,
  .field-error--animated,
  .field-success-mark {
    animation: none !important;
  }

  .brand-glyph svg,
  .password-toggle-glyph,
  .checkbox-tick {
    transition: none !important;
    transform: none !important;
  }

  .brand-glyph-check {
    transform: none !important;
  }

  .checkbox-box {
    transition: none !important;
  }

  .form-card-morph-enter-active,
  .form-card-morph-leave-active {
    transition: none !important;
  }
}

/* Honor reduced transparency · the atmosphere layer is switched off, the
   outer card flattens to an opaque surface and the overlay widgets drop the
   backdrop blur. */
@media (prefers-reduced-transparency: reduce) {
  .login-page::before {
    background: none;
  }
  .login-split-card {
    background: var(--color-background-system-background);
    box-shadow: none;
  }
  .login-overlay-card {
    background: var(--color-background-system-background);
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
  }
}

/* High contrast · lift both text colors to AAA-legible label tokens, and
   give the recovered validation surfaces a stronger edge. */
@media (prefers-contrast: more) {
  .welcome-headline {
    color: var(--color-label-label);
    & + .welcome-subtitle { color: var(--color-label-secondary-label); }
  }
  .forgot-password-link,
  .login-footer-link {
    text-decoration: underline;
  }
  .checkbox-box {
    border-color: var(--color-label-secondary-label);
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
