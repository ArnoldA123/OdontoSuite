<template>
  <!-- HOTFIX-LOGIN-010 · PR-apply ui-login-refinement-dental-split-2026-09
       Outer card shell wraps the editorial split grid. Rounded-32px utility
       + 24/64/8 hairline shadow on the outer card per design Decision D8.
       On mobile the shell collapses to full-bleed so the form keeps the
       primary focus. -->
  <div class="login-page">
    <div class="login-page-shell">
      <div class="login-split-card rounded-[32px]">
        <div class="login-grid">
          <!-- Form column (left on desktop, second on mobile).
               Phase 2.5 · form Card polymorphism wraps the <form> in a
               TransitionGroup so the success state crossfades into the
               mini-summary. The MiniSummary lives inside the same group so
               the crossfade uses the same enter/leave contract as the rest
               of the page. -->
          <section class="login-form-column" aria-labelledby="login-headline">
            <div ref="cardRef" class="login-form-wrap">
              <header class="login-header">
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
                <h1 id="login-headline" class="welcome-headline">
                  Gestiona tu clínica con calma
                </h1>
                <p class="welcome-subtitle">
                  Inicia sesión para revisar citas, caja y pacientes en un solo lugar.
                </p>
              </div>

              <Card variant="elevated" padding="lg" class="login-card-surface">
                <!-- Phase 2.5 · form Card polymorphism. The TransitionGroup
                     crossfades between the <form> and the login-mini-summary
                     branch on success state. -->
                <TransitionGroup
                  name="form-card-morph"
                  tag="div"
                  class="login-form-morph"
                >
                  <form
                    v-if="state !== 'success'"
                    key="form"
                    class="login-form"
                    novalidate
                    :aria-busy="loading || undefined"
                    @submit.prevent="handleLogin"
                  >
                    <div class="field">
                      <label class="field-label" for="login-username">Usuario</label>
                      <div class="field-input-wrap">
                        <span class="field-prefix" aria-hidden="true">
                          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                    <div class="field">
                      <label class="field-label" for="login-password">Contraseña</label>
                      <div class="field-input-wrap">
                        <span class="field-prefix" aria-hidden="true">
                          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                         v-show on the shape-morph state. The magnetic
                         composable reads state === 'idle' || state ===
                         'error' to attach listeners only when the button
                         is in an interactive state. -->
                    <UiButton
                      ref="submitRef"
                      type="submit"
                      variant="primary"
                      size="lg"
                      :loading="state === 'authenticating'"
                      :disabled="state === 'success' || state === 'authenticating'"
                      :full-width="true"
                      data-magnetic="true"
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
              </Card>

              <!-- Phase 2.1 · footer row · Términos + Contacta al administrador.
                   The links sit BELOW the form Card but INSIDE the outer
                   split-card shell so the white surface carries them. -->
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
                >¿No tienes cuenta? Contacta al administrador</a>
              </div>

              <p class="login-footer-note">
                © {{ currentYear }} OdontoSuite. Sistema de gestión dental.
                <a href="mailto:soporte@odontosuite.local" class="login-footer-link">Soporte</a>
              </p>
            </div>
          </section>

          <!-- Phase 2.2 · Hero column. The right column carries the dental
               Pexels still with 3 floating overlay cards populated from
               real seeder data. The @error handler swaps to a static SVG
               placeholder (D6 in design.md). The hero column is `position:
 relative` and the overlays use absolute positioning per design D5. -->
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
              <!-- Phase 2.2 · SVG fallback (D6). Static tooth glyph in
                   systemBlue-500 over a soft gradient. Reserved aspect
                   ratio so the layout never collapses while loading. -->
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

              <!-- Phase 2.3 · Overlay Card 1 — Pacientes activos.
                   Top-right of the hero column. -->
              <div
                v-motion="overlayMotion(0)"
                class="login-overlay-card login-overlay-card--top"
              >
                <p class="login-overlay-eyebrow">Pacientes activos</p>
                <p class="login-overlay-figure">
                  {{ formatThousands(stats.totalPatients) }}
                </p>
              </div>

              <!-- Phase 2.3 · Overlay Card 2 — Citas hoy. Mid-left of the
                   hero column. -->
              <div
                v-motion="overlayMotion(1)"
                class="login-overlay-card login-overlay-card--mid"
              >
                <p class="login-overlay-eyebrow">Citas hoy</p>
                <ul v-if="appointments.length > 0" class="login-overlay-list">
                  <li
                    v-for="(apt, idx) in appointments.slice(0, 3)"
                    :key="idx"
                    class="login-overlay-list-row"
                  >
                    <span class="login-overlay-list-name">{{ apt.patientName }}</span>
                    <span class="login-overlay-list-time">{{ formatTime(apt.scheduledAt) }}</span>
                  </li>
                </ul>
                <p v-else class="login-overlay-empty">Sin datos para mostrar</p>
              </div>

              <!-- Phase 2.3 · Overlay Card 3 — Equipo. Bottom-right of the
                   hero column. -->
              <div
                v-motion="overlayMotion(2)"
                class="login-overlay-card login-overlay-card--bottom"
              >
                <p class="login-overlay-eyebrow">Equipo</p>
                <div class="login-overlay-avatars">
                  <span
                    v-for="(member, idx) in activeUsers.slice(0, 3)"
                    :key="idx"
                    class="login-overlay-avatar"
                    :title="member.name"
                  >{{ initialsOf(member.name) }}</span>
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
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useSpring } from '@/composables/useSpring'
import { useAuth } from '@/composables/useAuth'
import { useApi } from '@/composables/useApi'
import { useShapeMorph } from '@/composables/useShapeMorph'
import { useReducedMotion } from '@/composables/useReducedMotion'
import Card from '@/components/ui/Card.vue'
import ForgotPasswordModal from './ForgotPasswordModal.vue'
import ResetPasswordModal from './ResetPasswordModal.vue'
import UiButton from '@/components/ui/Button.vue'

const router = useRouter()
const { login } = useAuth()
const api = useApi()

// Phase 2.4 · polymorphic submit state machine.
const { state, transition, cancel } = useShapeMorph({ initial: 'idle', dwellMs: 200 })

// Phase 2.6 · reactive reduced-motion detector.
const prefersReducedMotion = useReducedMotion()

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

// Phase 2.2 · image fallback state.
const imageFailed = ref(false)

// Phase 2.3 · live overlay data. Fetched on mount, fire-and-forget per
// design Decision D7. Each card has its own empty state — never breaks
// the layout.
const stats = reactive({
  totalPatients: 0
})
const appointments = ref([])
const activeUsers = ref([])

const currentYear = new Date().getFullYear()

const form = reactive({
  username: '',
  password: '',
  remember: false
})

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
const submitRef = ref(null)

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

function formatTime(iso) {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    const hh = String(d.getHours()).padStart(2, '0')
    const mm = String(d.getMinutes()).padStart(2, '0')
    return `${hh}:${mm}`
  } catch (_e) {
    return ''
  }
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

  // Phase 2.3 · fetch overlay data (fire-and-forget per design D7).
  void fetchStats()
  void fetchAppointments()
  void fetchActiveUsers()
})

async function fetchStats() {
  try {
    const response = await api.get('/api/dashboard/stats')
    const data = response?.data || response || {}
    stats.totalPatients = Number(data.total_patients ?? data.totalPatients ?? 0)
  } catch (_e) {
    stats.totalPatients = 0
  }
}

async function fetchAppointments() {
  try {
    const response = await api.get('/api/dashboard/appointments-today?per_page=3')
    const payload = response?.data ?? response ?? []
    const list = Array.isArray(payload) ? payload : payload.data ?? []
    appointments.value = list.slice(0, 3).map((apt) => ({
      patientName: apt?.patient?.name || apt?.patient_name || apt?.name || 'Paciente',
      scheduledAt: apt?.scheduled_at || apt?.start || apt?.time || ''
    }))
  } catch (_e) {
    appointments.value = []
  }
}

async function fetchActiveUsers() {
  try {
    const response = await api.get('/api/users/active?per_page=3')
    const payload = response?.data ?? response ?? []
    const list = Array.isArray(payload) ? payload : payload.data ?? []
    activeUsers.value = list.slice(0, 3).map((u) => ({
      name: u?.name || u?.full_name || ''
    }))
  } catch (_e) {
    activeUsers.value = []
  }
}

// Phase 2.2 · image fallback handler (D6).
function onImageError() {
  imageFailed.value = true
}

// Validation (unchanged from the PR3 baseline).
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

// Phase 2.4 · handleLogin rewired to drive the shape-morph state machine.
// Cycle:
//   click → validating (200ms dwell) → authenticating → success (600ms) →
//   /dashboard. Non-2xx → error (220ms) → idle via cancel().
const handleLogin = async () => {
  if (!validateForm()) return
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
    if (transition('error')) {
      setTimeout(() => {
        cancel()
      }, 220)
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
/* Slice 12 / fix-viewport-fit: the outer page + shell + card now constrain
   themselves to the viewport instead of growing with content. The form
   column gets internal overflow so it can scroll if the form is taller
   than the available height. The hero column gets overflow:hidden so the
   image never reflows on resize. */
.login-page {
  @apply min-h-[100dvh] w-full flex items-stretch justify-center;
  background: var(--color-canvas);
  /* `height: 100dvh` clamps the page to the dynamic viewport (avoids the
     iOS Safari URL-bar reflow); `min-height` is a fallback for older
     browsers that do not support dvh. */
  height: 100dvh;
  min-height: 100vh;
  overflow: hidden;
}

.login-page-shell {
  @apply w-full flex items-stretch justify-center;
  /* Reduced from clamp(16px, 4vw, 48px) to clamp(12px, 2vw, 24px) — the
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
  /* The card now grows to fill the shell (which fills the viewport) but
     never exceeds `viewport - shell padding`. Without this cap the card
     grew to 902px on a 900px viewport, causing a 98px vertical overflow. */
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

.login-form-column {
  @apply order-2 flex items-center justify-center px-5 py-8 sm:px-8;
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

.welcome-subtitle {
  @apply text-base leading-relaxed;
  color: var(--color-label-secondary-label);
}

.login-card-surface {
  @apply w-full;
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
  border-radius: 9999px;
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
  @apply inline-flex items-center justify-center px-4 py-2 rounded-xl text-sm font-medium;
  background: var(--color-system-blue-500);
  color: var(--color-background-system-background);
  border: none;
  cursor: pointer;
  transition: background-color 200ms ease-out;
}

.login-mini-cta:hover {
  background: var(--color-system-blue-600);
}

/* Phase 2.1 · footer row. */
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

.login-footer-note {
  @apply text-xs text-center;
  color: var(--color-label-tertiary-label);
}

/* Phase 2.2 · hero column. The hero carries the dental image with 3
   floating overlay cards (D5). The hero-column is `position: relative`
   so the absolute overlays anchor correctly. `min-height: 0` and
   `overflow: hidden` prevent the image from forcing a grid reflow. */
.login-hero-column {
  --spring-hero-o: 1;
  --spring-hero-opacity: 1;
  @apply order-1 relative overflow-hidden flex items-stretch justify-stretch;
  min-height: 220px;
  padding: 0;
  transform: translate3d(0, calc((1 - var(--spring-hero-o)) * 12px), 0);
  opacity: var(--spring-hero-opacity);
}

.login-hero {
  position: relative;
  width: 100%;
  min-height: 100%;
  background: var(--color-system-gray-50);
  overflow: hidden;
  border-radius: var(--radius-card-lg);
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

/* Phase 2.3 · overlay cards. Per design D5: top-right, mid-left,
   bottom-right. Backdrop-filter blur with white-85% surface. */
.login-overlay-card {
  position: absolute;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border: 1px solid var(--color-hairline);
  border-radius: 16px;
  padding: 12px 14px;
  min-width: 140px;
  box-shadow:
    0 8px 24px rgba(60, 60, 67, 0.05),
    0 1px 2px rgba(60, 60, 67, 0.55);
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
  min-width: 200px;
}

.login-overlay-card--bottom {
  bottom: 24px;
  right: 24px;
}

.login-overlay-eyebrow {
  @apply text-[10px] uppercase tracking-wider font-semibold;
  color: var(--color-label-secondary-label);
  margin: 0 0 4px;
}

.login-overlay-figure {
  @apply text-2xl font-semibold leading-none;
  color: var(--color-label-label);
  margin: 0;
}

.login-overlay-list {
  @apply flex flex-col gap-1 m-0 p-0 list-none;
}

.login-overlay-list-row {
  @apply flex items-center justify-between gap-2 text-xs;
}

.login-overlay-list-name {
  color: var(--color-label-label);
}

.login-overlay-list-time {
  color: var(--color-label-secondary-label);
  font-variant-numeric: tabular-nums;
}

.login-overlay-empty {
  @apply text-xs m-0;
  color: var(--color-label-tertiary-label);
}

.login-overlay-avatars {
  @apply flex items-center gap-2;
}

.login-overlay-avatar {
  width: 24px;
  height: 24px;
  border-radius: 9999px;
  background: var(--color-system-blue-500);
  color: var(--color-background-system-background);
  @apply inline-flex items-center justify-center text-[10px] font-semibold;
}

/* Tablet and up: form first column, hero second. */
@media (min-width: 768px) {
  .login-grid {
    grid-template-columns: 5fr 7fr;
  }
  .login-form-column {
    @apply order-1 px-10 py-10;
  }
  .login-hero-column {
    @apply order-2;
    /* Slice 12 / fix-viewport-fit: removed `min-height: 100dvh` — that rule
       was the root cause of the 98px vertical overflow on 1440x900. The
       column now sizes to the grid row, which is constrained by the
       card's `max-height: calc(100dvh - 48px)`. `min-height: 0` keeps the
       grid from forcing the column to its content size. */
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
    padding: 20px;
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

/* Honor reduced motion. Both entrance springs AND every polymorphic
   crossfade collapse to instant. */
@media (prefers-reduced-motion: reduce) {
  .login-split-card,
  .login-overlay-card,
  .login-submit-shape,
  .login-mini-summary {
    animation: none !important;
    transition: none !important;
    transform: none !important;
  }
  .login-form-wrap {
    transform: none !important;
    transition: none !important;
  }
  .login-hero-column {
    transform: none !important;
    opacity: 1 !important;
    transition: none !important;
  }
  .form-card-morph-enter-active,
  .form-card-morph-leave-active {
    transition: none !important;
  }
}

/* Honor reduced transparency · the outer card flattens to an opaque
   surface and the overlay cards drop the backdrop blur. */
@media (prefers-reduced-transparency: reduce) {
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

/* High contrast · lift both text colors to AAA-legible label tokens. */
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