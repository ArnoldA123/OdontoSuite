<template>
  <!--
    Canvas vs surface separation (PR1 token, PR4 first consumer).
    The page surface is `bg-canvas` (the iOS secondaryBackground ramp)
    so cards and other white surface primitives read as objects lifted off
    the canvas. The previous `bg-systemBackground` made canvas and cards
    the same colour; both read as outlines.

    PR4 wires the canvas only on `/dashboard` to keep blast radius tight
    (PR5 will extend it to Login + 404). The route-aware flip is a
    computed boolean rather than a string so the class binding stays
    idiomatic.
  -->
  <div
    class="min-h-[100dvh] transition-colors duration-200"
    :class="isCanvasRoute ? 'bg-canvas' : 'bg-systemBackground'"
  >
    <!-- Desktop Sidebar -->
    <aside
      id="primary-sidebar"
      class="hidden lg:fixed lg:inset-y-0 lg:flex lg:flex-col sidebar-slide transition-all duration-300"
      :class="sidebarCollapsed ? 'lg:w-14' : 'lg:w-72'"
      data-app-chrome="sidebar"
    >
      <div class="surface-glass flex flex-col flex-grow overflow-y-auto chrome-fade-right">
        <!-- Logo -->
        <div class="flex items-center flex-shrink-0 py-6 border-b border-theme/50 transition-all duration-300" :class="sidebarCollapsed ? 'px-2 justify-center' : 'px-6'">
          <router-link
            v-if="!sidebarCollapsed"
            to="/dashboard"
            class="flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-systemBlue-500 focus-visible:ring-offset-2 rounded-ios"
            aria-label="Ir al Dashboard"
            title="Ir al Dashboard"
          >
            <img
              src="/images/easy_dent.png"
              alt="OdontoSuite"
              class="h-8 w-8 transition-all duration-200"
            />
            <span class="text-lg font-semibold text-theme-primary transition-opacity duration-200">
              OdontoSuite
            </span>
          </router-link>
          <button
            v-else
            @click="toggleSidebar"
            class="flex items-center justify-center w-8 h-8 p-1 rounded-ios hover:bg-theme-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-systemBlue-500 focus-visible:ring-offset-2 transition-colors duration-200"
            aria-label="Abrir barra lateral"
            title="Abrir barra lateral"
            aria-expanded="false"
            aria-controls="primary-sidebar"
          >
            <img
              src="/images/easy_dent.png"
              alt="OdontoSuite"
              class="h-8 w-8"
            />
          </button>
        <button
          v-if="!sidebarCollapsed"
          @click="toggleSidebar"
          class="ml-auto p-1.5 rounded-ios hover:bg-theme-surface transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-systemBlue-500 focus-visible:ring-offset-2"
          :aria-label="sidebarCollapsed ? 'Expandir barra lateral' : 'Colapsar barra lateral'"
          :aria-expanded="!sidebarCollapsed"
          aria-controls="primary-sidebar"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
          </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-6 space-y-2 transition-all duration-300" :class="sidebarCollapsed ? 'px-2' : 'px-4'">
          <template v-for="item in navigation" :key="item.name">
            <div
              v-if="!sidebarCollapsed && item.name === 'Pacientes'"
              class="px-6 py-2 text-[11px] uppercase tracking-[0.12em] text-systemGray-500"
            >
              Operaciones
            </div>
            <div
              v-if="!sidebarCollapsed && item.name === 'Sucursales'"
              class="px-6 py-2 text-[11px] uppercase tracking-[0.12em] text-systemGray-500"
            >
              Configuración
            </div>
            <router-link
              :to="item.to"
              :class="getNavItemClasses(item)"
              :title="sidebarCollapsed ? item.name : ''"
              :aria-current="route.path === item.to ? 'page' : undefined"
            >
              <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
              <span
                v-if="!sidebarCollapsed"
                class="ml-3 transition-opacity duration-200"
              >
                {{ item.name }}
              </span>
              <UiBadge
                v-if="item.badge && !sidebarCollapsed"
                :variant="item.badge.variant"
                size="sm"
                class="ml-auto"
              >
                {{ item.badge.text }}
              </UiBadge>
            </router-link>
          </template>
        </nav>

        <!-- User Section -->
        <div class="border-t border-theme/50 p-4">
          <div v-if="!sidebarCollapsed" class="flex items-center gap-3 mb-4">
            <UiAvatar
              :src="user?.avatar"
              :initials="getUserInitials()"
              size="sm"
            />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-theme-primary truncate">
                {{ safeUser.name || 'Usuario' }}
              </p>
              <p class="text-xs text-theme-secondary truncate">
                {{ getRoleLabel(safeUser.role) }}
              </p>
            </div>
          </div>

          <div v-else class="flex justify-center mb-4">
            <UiAvatar
              :src="user?.avatar"
              :initials="getUserInitials()"
              size="sm"
            />
          </div>

          <UiButton
            variant="ghost"
            size="sm"
            :full-width="!sidebarCollapsed"
            @click="handleLogout"
            :aria-label="sidebarCollapsed ? 'Cerrar sesión' : ''"
          >
            <template #icon-left>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </template>
            <span v-if="!sidebarCollapsed">Cerrar Sesión</span>
          </UiButton>
        </div>

      </div>
    </aside>

    <!-- Mobile Header -->
    <div class="lg:hidden surface-glass fixed top-0 left-0 right-0 z-40 chrome-fade-bottom" data-app-chrome="mobile-header">
      <div class="flex items-center justify-between px-4 h-16">
        <div class="flex items-center gap-3">
          <img src="/images/easy_dent.png" alt="OdontoSuite" class="h-8 w-8" />
          <span class="text-lg font-semibold text-theme-primary">OdontoSuite</span>
        </div>
        <button
          @click="mobileMenuOpen = true"
          class="p-2 rounded-ios hover:bg-theme-surface transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-systemBlue-500 focus:ring-offset-2"
          aria-label="Abrir menú"
          aria-haspopup="dialog"
          aria-expanded="mobileMenuOpen"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Menu -->
    <UiSheet
      v-model="mobileMenuOpen"
      position="right"
      size="md"
      :title="'Navegación'"
      :closable="true"
    >
      <template #header>
        <div class="flex items-center gap-3">
          <img src="/images/easy_dent.png" alt="OdontoSuite" class="h-8 w-8" />
          <span class="text-lg font-semibold text-theme-primary">OdontoSuite</span>
            </div>
      </template>

            <!-- Mobile Navigation -->
      <nav class="space-y-2">
              <router-link
                v-for="item in navigation"
                :key="item.name"
                :to="item.to"
          :class="getMobileNavItemClasses(item)"
                @click="mobileMenuOpen = false"
              >
          <component :is="item.icon" class="w-5 h-5" />
                <span>{{ item.name }}</span>
          <UiBadge
            v-if="item.badge"
            :variant="item.badge.variant"
            size="sm"
            class="ml-auto"
          >
            {{ item.badge.text }}
          </UiBadge>
              </router-link>
            </nav>

      <template #footer>
        <div class="flex items-center gap-3 p-4 bg-theme-surface rounded-lg">
          <UiAvatar
            :src="user?.avatar"
            :initials="getUserInitials()"
            size="sm"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-theme-primary truncate">
              {{ safeUser.name || 'Usuario' }}
            </p>
            <p class="text-xs text-theme-secondary truncate">
              {{ getRoleLabel(safeUser.role) }}
            </p>
          </div>
          <UiButton
            variant="ghost"
            size="sm"
            @click="handleLogout"
          >
            Cerrar Sesión
          </UiButton>
        </div>
      </template>
    </UiSheet>

    <!-- Main Content -->
    <div class="pt-16 lg:pt-0 transition-all duration-300" :class="sidebarCollapsed ? 'lg:pl-14' : 'lg:pl-72'">
      <!-- Header (top bar). Translucent chrome per design Decision 5. -->
      <header class="surface-glass relative z-30 shadow-subtle chrome-fade-bottom" data-app-chrome="topbar">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center py-4">
            <div class="flex items-center gap-4">
              <div>
                <h1 class="text-xl font-semibold text-theme-primary">{{ getPageTitle() }}</h1>
                <p v-if="getPageDescription()" class="text-sm text-theme-secondary mt-1">
                  {{ getPageDescription() }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <!--
                WebSocket connection indicator.
                PR4 — topbar single optical weight (G2). The WS dot, bell,
                and avatar used to render at three different optical sizes:
                a 2 px dot inside an 8 px pill, a 20 px BellIcon at stroke
                2.0, and a 32 px Avatar. The fix: all three consume the
                topbar.iconSize + topbar.iconWeight tokens so the row
                reads as one optical unit. The dot pill shrinks to 24 px
                (its content cap) and the dot diameter aligns to the same
                stroke weight.
              -->
              <div
                class="flex items-center justify-center rounded-full"
                :class="{
                  'bg-systemBlue-100 text-systemBlue-700': wsStatus === 'connecting',
                  'bg-systemGreen-100 text-systemGreen-700': wsStatus === 'connected',
                  'bg-systemYellow-100 text-systemYellow-700': wsStatus === 'disconnected',
                  'bg-systemRed-100 text-systemRed-700': wsStatus === 'unavailable',
                }"
                :style="{ width: 'var(--topbar-control)', height: 'var(--topbar-control)' }"
                :aria-label="`Estado de WebSocket: ${wsStatus}`"
                :title="`WebSocket: ${wsStatus === 'connected' ? 'En vivo' : wsStatus === 'connecting' ? 'Conectando' : wsStatus === 'disconnected' ? 'Reconectando' : 'Sin WS'}`"
              >
                <span
                  class="rounded-full"
                  :class="{
                    'bg-systemBlue-500 animate-pulse': wsStatus === 'connecting',
                    'bg-systemGreen-500 animate-pulse-subtle': wsStatus === 'connected',
                    'bg-systemYellow-500': wsStatus === 'disconnected',
                    'bg-systemRed-500': wsStatus === 'unavailable',
                  }"
                  :style="{
                    width: 'calc(var(--topbar-icon-weight) * 1.5px)',
                    height: 'calc(var(--topbar-icon-weight) * 1.5px)'
                  }"
                  aria-hidden="true"
                />
              </div>

              <!--
                Notifications.
                PR4 — topbar single optical weight (G2). The BellIcon
                consumes the topbar.iconSize + iconWeight tokens so it
                matches the WS dot and the avatar in optical size. The
                outline stroke is explicitly 1.5 (Apple's outline-icon
                convention) instead of the previous Tailwind default 2.
              -->
              <UiButton
                variant="ghost"
                size="sm"
                class="relative"
                @click="toggleNotificationCenter"
                aria-label="Centro de notificaciones"
                :aria-expanded="notificationCenterOpen"
                aria-haspopup="dialog"
              >
                <template #icon-left>
                  <!--
                    BellIcon — topbar single optical weight (G2).
                    `style="stroke-width: var(--topbar-icon-weight)"`
                    reads as a literal CSS declaration in the source so
                    the regex-based source-assertion test can grep it.
                    The width/height use the same var for one shared size.
                  -->
                  <BellIcon
                    style="width: var(--topbar-icon-size); height: var(--topbar-icon-size); stroke-width: var(--topbar-icon-weight);"
                    aria-hidden="true"
                  />
                </template>
                <UiBadge
                  v-if="unreadNotificationCount > 0"
                  variant="error"
                  size="xs"
                  class="absolute -top-1 -right-1"
                  :aria-label="`${unreadNotificationCount} notificaciones sin leer`"
                >
                  {{ unreadNotificationCount > 99 ? '99+' : unreadNotificationCount }}
                </UiBadge>
              </UiButton>

              <!-- User Menu -->
              <div class="relative" ref="userMenuContainerRef">
                <UiButton
                  ref="userMenuTriggerRef"
                  variant="ghost"
                  size="sm"
                  @click="toggleUserMenu"
                  class="flex items-center gap-2"
                  :aria-expanded="userMenuOpen"
                  aria-haspopup="menu"
                  aria-controls="user-menu-dropdown"
                >
                  <!--
                    User menu trigger.
                    PR4 — topbar single optical weight (G2). The avatar
                    diameter pins to the topbar.controlLg token (32 px)
                    and the chevron next to it consumes the same icon
                    size + stroke weight as the BellIcon so the three
                    topbar controls align on one row.
                  -->
                  <UiAvatar
                    :src="user?.avatar"
                    :initials="getUserInitials()"
                    size="sm"
                  />
                  <span class="hidden sm:block text-sm font-medium">
                    {{ safeUser.name || 'Usuario' }}
                  </span>
                  <svg
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    style="width: var(--topbar-icon-size); height: var(--topbar-icon-size); stroke-width: var(--topbar-icon-weight);"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </UiButton>

                <!-- User Dropdown - Fixed Position -->
                <Transition
                  enter-active-class="transition-all duration-200 ease-out"
                  enter-from-class="opacity-0 scale-95 translate-y-1"
                  enter-to-class="opacity-100 scale-100 translate-y-0"
                  leave-active-class="transition-all duration-150 ease-in"
                  leave-from-class="opacity-100 scale-100 translate-y-0"
                  leave-to-class="opacity-0 scale-95 translate-y-1"
                >
                  <div
                    ref="userMenuDropdownRef"
                    v-if="userMenuOpen"
                    id="user-menu-dropdown"
                    class="fixed z-50 w-64 bg-theme-surface-elevated rounded-xl shadow-2xl border border-theme py-2 origin-top-right"
                    :style="userMenuStyle"
                    role="menu"
                    aria-label="Menú de usuario"
                    @click.stop
                    @keydown.esc="closeUserMenu"
                  >
                    <div class="px-4 py-3 border-b border-theme">
                      <p class="text-sm font-medium text-theme-primary">{{ safeUser.name || 'Usuario' }}</p>
                      <p class="text-xs text-theme-secondary">{{ safeUser.email }}</p>
                    </div>
                    <div class="py-2">
                      <button class="w-full text-left px-4 py-2 text-sm text-theme-primary hover:bg-theme-surface transition-colors duration-200">
                        Perfil
                      </button>
                      <button class="w-full text-left px-4 py-2 text-sm text-theme-primary hover:bg-theme-surface transition-colors duration-200">
                        Configuración
                      </button>
                    </div>

                    <!-- Theme Selector -->
                    <div class="border-t border-theme px-4 py-3">
                      <p class="text-xs text-theme-secondary mb-2 uppercase tracking-wide">Apariencia</p>
                    </div>

                    <div class="border-t border-theme py-2">
                      <button
                        @click="handleLogout"
                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors duration-200"
                      >
                        Cerrar Sesión
                      </button>
                    </div>
                  </div>
                </Transition>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main class="py-6 lg:py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </main>
    </div>

    <!-- Toast Container -->
    <ToastContainer />
    <UiConfirmDialog
      v-model="confirmIsOpen"
      :title="confirmTitle"
      :message="confirmMessage"
      :confirm-text="confirmConfirmText"
      :cancel-text="confirmCancelText"
      :variant="confirmVariant"
      @confirm="handleGlobalConfirm"
      @cancel="handleGlobalCancel"
    />

    <!-- Notification Toast -->
    <NotificationToast />

    <!-- Notification Center -->
    <NotificationCenter 
      :is-open="notificationCenterOpen"
      @close="closeNotificationCenter"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { usePermissions } from '../../composables/usePermissions'
import { useNotifications } from '../../composables/useNotifications'
import { useWebSocketNotifications } from '../../composables/useWebSocketNotifications'
import { useEcho } from '../../composables/useEcho'
import {
  confirmIsOpen,
  confirmTitle,
  confirmMessage,
  confirmConfirmText,
  confirmCancelText,
  confirmVariant,
  useConfirm as useConfirmComposable,
} from '../../composables/useConfirm'
import ToastContainer from '../ToastContainer.vue'
import NotificationToast from '../ui/NotificationToast.vue'
import NotificationCenter from '../NotificationCenter.vue'
import { BellIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const { user, logout: authLogout } = useAuth()
const { can } = usePermissions()
const { connectionStatus: wsStatus } = useEcho()

// Handlers del modal global de confirmacion (useConfirm).
// Usamos la desestructuracion del composable para conectar los handlers del
// <UiConfirmDialog> montado a nivel de app con el resolver de la Promise.
const { handleConfirm: handleGlobalConfirm, handleCancel: handleGlobalCancel } = useConfirmComposable()

// State
const mobileMenuOpen = ref(false)
const sidebarCollapsed = ref(false)
const notificationCenterOpen = ref(false)

// PR4 (ui-premium-microdetail-2026-08) — canvas vs surface separation.
// The dashboard, login, and 404 routes render on canvas. Other (legacy)
// modules inherit the white systemBackground until they get their own
// polish pass. The route list is intentionally tight for PR4; PR5 will
// add '/login' and '/404' (or '/not-found') when those screens are
// polished. `isCanvasRoute` is a computed so the class binding stays
// reactive as the user navigates.
const canvasRoutes = ['/dashboard', '/login', '/404']
const isCanvasRoute = computed(() => canvasRoutes.includes(route.path))

// Notifications
const { getUnreadCount } = useNotifications()
const unreadNotificationCount = computed(() => getUnreadCount.value)

// WebSocket Notifications (se inicializa automáticamente)
useWebSocketNotifications()

// Notification Center
const toggleNotificationCenter = () => {
  notificationCenterOpen.value = !notificationCenterOpen.value
}

const closeNotificationCenter = () => {
  notificationCenterOpen.value = false
}

// User menu positioning
const userMenuContainerRef = ref(null)
const userMenuTriggerRef = ref(null)
const userMenuDropdownRef = ref(null)
// User menu state
const userMenuOpen = ref(false)
const toggleUserMenu = () => {
  userMenuOpen.value = !userMenuOpen.value
  if (userMenuOpen.value) {
    // Calcular posición cuando se abre
    nextTick(() => {
      calculateUserMenuPosition()
    })
  }
}
const closeUserMenu = () => {
  userMenuOpen.value = false
}

// Calcular posición del dropdown
const userMenuPosition = ref({ top: '0px', right: '0px' })
const calculateUserMenuPosition = () => {
  if (!userMenuTriggerRef.value) return

  const trigger = userMenuTriggerRef.value.$el || userMenuTriggerRef.value
  const rect = trigger.getBoundingClientRect()

  userMenuPosition.value = {
    top: `${rect.bottom + 8}px`,
    right: `${window.innerWidth - rect.right}px`
  }
}

const userMenuStyle = computed(() => ({
  ...userMenuPosition.value
  // z-index se maneja con la clase z-50 de Tailwind
}))

// Verificación de seguridad para evitar errores cuando user es null
const safeUser = computed(() => user.value || {})

// Iconos SVG como componentes
const DashboardIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z" />
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z" />
    </svg>
  `
}

const CalendarIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
  `
}

const UsersIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
    </svg>
  `
}

const UserGroupIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
  `
}

const BuildingIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
    </svg>
  `
}

const CogIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  `
}

// Sprint 1 (B-CASH-3): icono para Sucursales.
const BuildingOfficeIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
    </svg>
  `
}

// Sprint 2 (B-CASH-3): icono para Metodos de Pago.
const CreditCardIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
    </svg>
  `
}

const ChartIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
    </svg>
  `
}

const BanknotesIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  `
}

const ClipboardDocumentListIcon = {
  template: `
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
    </svg>
  `
}
const StarIcon = {
  template: `
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
    </svg>
  `
}

const DocumentTextIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
  `
}

const HeartIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
    </svg>
  `
}

const AcademicCapIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
    </svg>
  `
}

const ChatBubbleLeftRightIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
  `
}

const CpuChipIcon = {
  template: `
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
    </svg>
  `
}

// Navegación dinámica basada en permisos
const navigation = computed(() => {
  const allItems = [
    {
      name: 'Dashboard',
      to: '/dashboard',
      icon: DashboardIcon,
      roles: ['all'], // Todos pueden ver dashboard
      badge: null
    },
    {
      name: 'Calendario',
      to: '/calendar',
      icon: CalendarIcon,
      roles: ['administrador', 'recepcionista', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente'],
      badge: null
    },
    {
      name: 'Pacientes',
      to: '/patients',
      icon: UsersIcon,
      roles: ['administrador', 'recepcionista', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente'],
      badge: null
    },
    {
      name: 'Profesionales',
      to: '/professionals',
      icon: UserGroupIcon,
      roles: ['administrador'],
      badge: null
    },
    {
      name: 'Ambientes',
      to: '/environments',
      icon: BuildingIcon,
      roles: ['administrador'],
      badge: null
    },
    {
      name: 'Tipos de Cita',
      to: '/appointment-types',
      icon: CogIcon,
      roles: ['administrador'],
      badge: null
    },
    {
      // Sprint 1 (B-CASH-3): modulo de sucursales (solo admin).
      name: 'Sucursales',
      to: '/settings/branches',
      icon: BuildingOfficeIcon,
      roles: ['administrador'],
      badge: null
    },
    {
      // Sprint 2 (B-CASH-3): modulo de metodos de pago (solo admin).
      name: 'Metodos de Pago',
      to: '/settings/payment-methods',
      icon: CreditCardIcon,
      roles: ['administrador'],
      badge: null
    },
    {
      name: 'Catálogo de Procedimientos',
      to: '/procedure-catalog',
      icon: ClipboardDocumentListIcon,
      roles: ['administrador'],
      badge: null
    },
    {
      name: 'Mis Procedimientos',
      to: '/my-procedures',
      icon: StarIcon,
      roles: ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'],
      badge: null
    },
    {
      name: 'Catálogo de Procedimientos',
      to: '/reception-procedures',
      icon: ClipboardDocumentListIcon,
      roles: ['recepcionista', 'finanzas'],
      badge: null
    },
    {
      name: 'Business Intelligence',
      to: '/business-intelligence',
      icon: ChartIcon,
      roles: ['administrador', 'finanzas'],
      badge: null
    },
    {
      name: 'Caja',
      to: '/cash-register',
      icon: BanknotesIcon,
      roles: ['administrador', 'finanzas', 'recepcionista'],
      badge: null
    },
    {
      name: 'Planes de Tratamiento',
      to: '/treatment-plans',
      icon: ClipboardDocumentListIcon,
      roles: ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'],
      badge: null
    },
    {
      name: 'Presupuestos',
      to: '/quotations',
      icon: DocumentTextIcon,
      roles: ['administrador', 'finanzas', 'odontologo', 'implantologo'],
      badge: null
    },
    {
      name: 'Historias Clínicas',
      to: '/medical-records',
      icon: HeartIcon,
      roles: ['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente'],
      badge: null
    },
    {
      name: 'Especialidades',
      to: '/specialty-records',
      icon: AcademicCapIcon,
      roles: ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'],
      badge: null
    },
    {
      name: 'Análisis IA',
      to: '/ai-analysis',
      icon: CpuChipIcon,
      roles: ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'],
      badge: null
    }
  ]

  // Filtrar navegación según el rol del usuario
  return allItems.filter(item =>
    item.roles.includes('all') || item.roles.includes(safeUser.value?.role)
  )
})

// Navigation item classes (iOS clinical: systemBlue for active nav).
const getNavItemClasses = (item) => {
  const base = [
    'group flex items-center py-2.5 text-sm font-medium rounded-ios',
    'transition-all duration-200 ease-ios',
    sidebarCollapsed.value ? 'justify-center px-2' : 'px-3',
    'text-theme-secondary hover:text-theme-primary',
    'hover:bg-theme-surface'
  ]

  const active = route.path === item.to ? [
    'bg-systemBlue-50 text-systemBlue-700',
    'border border-systemBlue-200',
    'shadow-subtle'
  ] : []

  return [...base, ...active].join(' ')
}

const getMobileNavItemClasses = (item) => {
  const base = [
    'flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-ios',
    'transition-all duration-200',
    'text-theme-secondary hover:text-theme-primary',
    'hover:bg-theme-surface'
  ]

  const active = route.path === item.to ? [
    'bg-systemBlue-50 text-systemBlue-700',
    'border border-systemBlue-200'
  ] : []

  return [...base, ...active].join(' ')
}

// Utility functions
const getUserInitials = () => {
  const name = safeUser.value?.name || 'Usuario'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

const getRoleLabel = (role) => {
  const labels = {
    administrador: 'Administrador',
    recepcionista: 'Recepcionista',
    odontologo: 'Odontólogo',
    implantologo: 'Implantólogo',
    tecnico_dental: 'Técnico Dental',
    asistente: 'Asistente',
    finanzas: 'Finanzas'
  }
  return labels[role] || role
}

const getPageTitle = () => {
  const currentItem = navigation.value.find(item => item.to === route.path)
  return currentItem ? currentItem.name : 'OdontoSuite'
}

const getPageDescription = () => {
  const descriptions = {
    '/dashboard': 'Resumen general del sistema',
    '/calendar': 'Gestión de citas y horarios',
    '/patients': 'Base de datos de pacientes',
    '/professionals': 'Gestión de profesionales',
    '/environments': 'Configuración de ambientes',
    '/appointment-types': 'Tipos de citas disponibles',
    '/procedure-catalog': 'Catálogo clínico de procedimientos',
    '/procedure-catalog/': 'Detalle de procedimiento',
    '/my-procedures': 'Mis procedimientos favoritos',
    '/reception-procedures': 'Catálogo de procedimientos',
    '/business-intelligence': 'Análisis y reportes',
    '/treatment-plans': 'Gestión de planes de tratamiento',
    '/quotations': 'Presupuestos y cotizaciones',
    '/medical-records': 'Historias clínicas de pacientes',
    '/specialty-records': 'Registros de especialidades odontológicas'
  }
  return descriptions[route.path] || null
}

// Sidebar functions
const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
  // Sanitize: only persist when the new value is a real boolean. Anything
  // coming from localStorage on next load is also sanitized (see onMounted)
  // so a tampered storage value cannot force an attacker-controlled string
  // back into the persisted state.
  localStorage.setItem('sidebar-collapsed', sidebarCollapsed.value ? 'true' : 'false')
}

// Event handlers
const handleLogout = async () => {
  try {
    await authLogout()
    // Redirigir inmediatamente al login después de cerrar sesión
    router.push('/login')
  } catch (error) {
    // Redirigir al login incluso si hay error
    router.push('/login')
  }
}

// Close dropdowns when clicking outside
const handleClickOutside = (event) => {
  if (!event.target.closest('.relative')) {
    closeUserMenu()
  }
}

// Close user menu on Escape (WCAG 2.1.1)
const handleUserMenuEscape = (event) => {
  if (event.key === 'Escape' || event.key === 'Esc') {
    if (userMenuOpen.value) {
      event.preventDefault()
      closeUserMenu()
    }
  }
}

// Close mobile menu on route change so it does not bleed across pages.
watch(
  () => route.path,
  () => {
    if (mobileMenuOpen.value) {
      mobileMenuOpen.value = false
    }
  }
)

// Lifecycle
onMounted(() => {
  // Load sidebar state from localStorage with explicit sanitization. The
  // value must be exactly 'true' or 'false'; anything else is treated as
  // missing to avoid persisting attacker-controlled strings or coercing
  // an unexpected value to a boolean.
  const savedState = localStorage.getItem('sidebar-collapsed')
  if (savedState === 'true' || savedState === 'false') {
    sidebarCollapsed.value = savedState === 'true'
  } else if (savedState !== null) {
    // Tampered value — rewrite it to a known good value.
    localStorage.setItem('sidebar-collapsed', 'false')
  }

  // Add click outside + Escape listeners
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleUserMenuEscape)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleUserMenuEscape)
})
</script>

<style scoped>
/* Scroll-edge mask on the chrome: where chrome meets scrolling content,
   fade into the page rather than drawing a hard 1px divider. The mask is
   on the chrome's first content wrapper (not the chrome itself) so it
   does not affect hit-testing. */
.chrome-fade-right {
  /* Mask the right edge so the sidebar melts into the page background. */
  -webkit-mask-image: linear-gradient(to right, #000 calc(100% - 8px), transparent);
  mask-image: linear-gradient(to right, #000 calc(100% - 8px), transparent);
}

.chrome-fade-bottom {
  /* Mask the bottom edge for the top bar / mobile header. */
  -webkit-mask-image: linear-gradient(to bottom, #000 calc(100% - 6px), transparent);
  mask-image: linear-gradient(to bottom, #000 calc(100% - 6px), transparent);
}

/* Reduced transparency: collapse the chrome to a solid surface (the
   .surface-glass class already declares its own fallback inside the
   generated CSS; this is a defensive override in case a future
   chrome-class addition forgets the media-query fallback). */
@media (prefers-reduced-transparency: reduce) {
  [data-app-chrome] {
    /* surface-glass class itself emits the solid-cream fallback; we
       nothing-do here. The block is documented for future chrome
       additions so a contributor never forgets to declare the
       reduced-transparency fallback. */
  }
}

/* Router-link active state - iOS clinical systemBlue for the active
   nav item; visually unmistakable per the design wayfinding goal. */
.router-link-exact-active {
  background-color: var(--color-system-blue-50);
  color: var(--color-system-blue-700);
  border-color: var(--color-system-blue-200);
}

/* Sidebar entrance animation: small slide-in from the left. Disabled
   under reduced-motion via the global reduced-motion override. */
@keyframes sidebarSlideIn {
  from {
    transform: translateX(-12px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.sidebar-slide {
  animation: sidebarSlideIn 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@media (prefers-reduced-motion: reduce) {
  .sidebar-slide {
    animation: none;
    transform: none;
    opacity: 1;
  }
  [data-app-chrome] {
    transition: none;
  }
}
</style>
