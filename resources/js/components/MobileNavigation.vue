<template>
  <div class="lg:hidden">
    <!-- Mobile menu button -->
    <button
      @click="isOpen = !isOpen"
      class="inline-flex items-center justify-center p-2 rounded-md text-theme-secondary hover:text-theme-primary hover:bg-theme-surface focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500"
      aria-expanded="false"
    >
      <span class="sr-only">Abrir menú principal</span>
      <!-- Hamburger icon -->
      <svg
        v-if="!isOpen"
        class="block h-6 w-6"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
      <!-- Close icon -->
      <svg
        v-else
        class="block h-6 w-6"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <!-- Mobile menu overlay -->
    <Transition
      enter-active-class="transition-opacity ease-linear duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity ease-linear duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="isOpen" class="mobile-nav">
        <div class="mobile-nav-overlay" @click="isOpen = false"></div>
        <div class="mobile-nav-panel">
          <div class="flex items-center justify-between h-16 px-4 border-b border-theme">
            <div class="flex items-center">
              <img class="h-8 w-auto" src="/favicon.ico" alt="OdontoSuite" />
              <span class="ml-2 text-lg font-semibold text-theme-primary">OdontoSuite</span>
            </div>
            <button
              @click="isOpen = false"
              class="p-2 rounded-md text-theme-secondary hover:text-theme-primary hover:bg-theme-surface"
            >
              <span class="sr-only">Cerrar menú</span>
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="flex-1 px-2 py-4 space-y-1">
            <nav class="space-y-1">
              <router-link
                v-for="item in navigationItems"
                :key="item.name"
                :to="item.href"
                @click="isOpen = false"
                :class="[
                  'group flex items-center px-2 py-2 text-base font-medium rounded-md transition-colors',
                  $route.path === item.href
                    ? 'bg-primary-100 text-primary-800'
                    : 'text-theme-secondary hover:bg-theme-surface hover:text-theme-primary'
                ]"
              >
                <component
                  :is="item.icon"
                  :class="[
                    'mr-4 h-6 w-6 flex-shrink-0',
                    $route.path === item.href ? 'text-accent' : 'text-theme-secondary group-hover:text-theme-primary'
                  ]"
                />
                {{ item.name }}
              </router-link>
            </nav>
          </div>

          <!-- User menu -->
          <div class="border-t border-theme p-4">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-8 w-8 rounded-full bg-accent flex items-center justify-center">
                  <span class="text-sm font-medium text-white">
                    {{ user?.name?.charAt(0) || 'U' }}
                  </span>
                </div>
              </div>
              <div class="ml-3">
                <p class="text-base font-medium text-theme-primary">{{ user?.name || 'Usuario' }}</p>
                <p class="text-sm font-medium text-theme-secondary">{{ user?.role || 'Usuario' }}</p>
              </div>
            </div>
            <div class="mt-3 space-y-1">
              <button
                @click="handleLogout"
                class="block w-full text-left px-2 py-2 text-base font-medium text-theme-secondary hover:bg-theme-surface hover:text-theme-primary rounded-md"
              >
                Cerrar sesión
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

export default {
  name: 'MobileNavigation',
  setup() {
    const router = useRouter()
    const { user, logout } = useAuth()
    const isOpen = ref(false)

    const navigationItems = [
      {
        name: 'Dashboard',
        href: '/dashboard',
        icon: 'svg'
      },
      {
        name: 'Citas',
        href: '/appointments',
        icon: 'svg'
      },
      {
        name: 'Pacientes',
        href: '/patients',
        icon: 'svg'
      },
      {
        name: 'Reportes',
        href: '/reports',
        icon: 'svg'
      },
      {
        name: 'Business Intelligence',
        href: '/business-intelligence',
        icon: 'svg'
      }
    ]

    const handleLogout = async () => {
      try {
        await logout()
        router.push('/login')
      } catch (error) {
      }
    }

    return {
      isOpen,
      navigationItems,
      user,
      handleLogout
    }
  }
}
</script>
