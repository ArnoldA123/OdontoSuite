<template>
  <AppLayout>
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">Test de Componentes</h1>
          <p class="text-theme-secondary">Prueba todos los componentes del sistema de diseño</p>
        </div>
        <div class="flex gap-3">
          <UiButton variant="secondary" @click="goBack" class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver
          </UiButton>
        </div>
      </div>
    </div>

    <!-- Theme Test Section -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Sistema de Temas</h2>
      </template>
      <div class="space-y-4">
        <p class="text-theme-secondary">
          Prueba el selector de temas en diferentes ubicaciones:
        </p>
        <div class="flex flex-wrap gap-4">
          <div class="flex items-center gap-2">
            <span class="text-sm text-theme-secondary">En Header:</span>
            <UiThemeSelector />
          </div>
          <div class="flex items-center gap-2">
            <span class="text-sm text-theme-secondary">En Sidebar:</span>
            <UiThemeSelector />
          </div>
        </div>
      </div>
    </UiCard>

    <!-- Buttons Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Botones</h2>
      </template>
      <div class="space-y-6">
        <!-- Variants -->
        <div>
          <h3 class="text-lg font-medium text-theme-primary mb-3">Variantes</h3>
          <div class="flex flex-wrap gap-3">
            <UiButton variant="primary">Primary</UiButton>
            <UiButton variant="secondary">Secondary</UiButton>
            <UiButton variant="ghost">Ghost</UiButton>
            <UiButton variant="danger">Danger</UiButton>
            <UiButton variant="success">Success</UiButton>
            <UiButton variant="warning">Warning</UiButton>
            <UiButton variant="icon">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
            </UiButton>
          </div>
        </div>

        <!-- Sizes -->
        <div>
          <h3 class="text-lg font-medium text-theme-primary mb-3">Tamaños</h3>
          <div class="flex flex-wrap items-center gap-3">
            <UiButton size="xs">Extra Small</UiButton>
            <UiButton size="sm">Small</UiButton>
            <UiButton size="md">Medium</UiButton>
            <UiButton size="lg">Large</UiButton>
            <UiButton size="xl">Extra Large</UiButton>
          </div>
        </div>

        <!-- States -->
        <div>
          <h3 class="text-lg font-medium text-theme-primary mb-3">Estados</h3>
          <div class="flex flex-wrap gap-3">
            <UiButton>Normal</UiButton>
            <UiButton disabled>Disabled</UiButton>
            <UiButton loading>Loading</UiButton>
            <UiButton ripple>Con Ripple</UiButton>
          </div>
        </div>
      </div>
    </UiCard>

    <!-- Inputs Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Inputs</h2>
      </template>
      <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <UiInput
            v-model="testInput"
            label="Input Normal"
            placeholder="Escribe algo..."
          />
          <UiInput
            v-model="testInput2"
            label="Input con Error"
            placeholder="Este tiene error"
            :error="true"
            helper="Este campo es requerido"
          />
          <UiInput
            v-model="testInput3"
            label="Input con Éxito"
            placeholder="Este está bien"
            :success="true"
            helper="Perfecto!"
          />
          <UiInput
            v-model="testInput4"
            label="Input con Icono"
            placeholder="Buscar..."
          >
            <template #prefix>
              <svg class="w-5 h-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </UiInput>
        </div>
      </div>
    </UiCard>

    <!-- Cards Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Cards</h2>
      </template>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <UiCard variant="default">
          <h3 class="font-semibold text-theme-primary mb-2">Card Default</h3>
          <p class="text-theme-secondary">Contenido de la card</p>
        </UiCard>

        <UiCard variant="glass">
          <h3 class="font-semibold text-theme-primary mb-2">Card Glass</h3>
          <p class="text-theme-secondary">Efecto glass</p>
        </UiCard>

        <UiCard variant="flat">
          <h3 class="font-semibold text-theme-primary mb-2">Card Flat</h3>
          <p class="text-theme-secondary">Sin sombra</p>
        </UiCard>

        <UiCard variant="elevated">
          <h3 class="font-semibold text-theme-primary mb-2">Card Elevated</h3>
          <p class="text-theme-secondary">Con elevación</p>
        </UiCard>

        <UiCard variant="outlined">
          <h3 class="font-semibold text-theme-primary mb-2">Card Outlined</h3>
          <p class="text-theme-secondary">Solo borde</p>
        </UiCard>

        <UiCard variant="glass" hover>
          <h3 class="font-semibold text-theme-primary mb-2">Card Hover</h3>
          <p class="text-theme-secondary">Con efecto hover</p>
        </UiCard>
      </div>
    </UiCard>

    <!-- Badges Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Badges</h2>
      </template>
      <div class="flex flex-wrap gap-3">
        <UiBadge variant="default">Default</UiBadge>
        <UiBadge variant="primary">Primary</UiBadge>
        <UiBadge variant="success">Success</UiBadge>
        <UiBadge variant="warning">Warning</UiBadge>
        <UiBadge variant="error">Error</UiBadge>
        <UiBadge variant="info">Info</UiBadge>
        <UiBadge variant="neutral">Neutral</UiBadge>
      </div>
    </UiCard>

    <!-- Avatars Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Avatars</h2>
      </template>
      <div class="flex flex-wrap gap-4">
        <UiAvatar name="Juan Pérez" email="juan@example.com" size="xs" />
        <UiAvatar name="María García" email="maria@example.com" size="sm" />
        <UiAvatar name="Carlos López" email="carlos@example.com" size="md" />
        <UiAvatar name="Ana Martínez" email="ana@example.com" size="lg" />
        <UiAvatar name="Luis Rodríguez" email="luis@example.com" size="xl" />
      </div>
    </UiCard>

    <!-- Loading States Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Estados de Carga</h2>
      </template>
      <div class="space-y-6">
        <div>
          <h3 class="text-lg font-medium text-theme-primary mb-3">Loading Spinners</h3>
          <div class="flex flex-wrap gap-4">
            <LoadingSpinner size="xs" text="Cargando..." />
            <LoadingSpinner size="sm" text="Cargando..." />
            <LoadingSpinner size="md" text="Cargando..." />
            <LoadingSpinner size="lg" text="Cargando..." />
            <LoadingSpinner size="xl" text="Cargando..." />
          </div>
        </div>

        <div>
          <h3 class="text-lg font-medium text-theme-primary mb-3">Skeleton Loaders</h3>
          <div class="space-y-3">
            <Skeleton variant="text" :count="3" />
            <Skeleton variant="card" :count="2" />
            <Skeleton variant="table" :count="4" />
          </div>
        </div>
      </div>
    </UiCard>

    <!-- DataTable Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">DataTable</h2>
      </template>
      <DataTable
        :data="testData"
        :columns="testColumns"
        :searchable="true"
        :sortable="true"
        :selectable="true"
        :pagination="true"
        :page-size="5"
      />
    </UiCard>

    <!-- Tabs Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Tabs</h2>
      </template>
      <div class="space-y-6">
        <div>
          <h3 class="text-lg font-medium text-theme-primary mb-3">Default Tabs</h3>
          <Tabs :tabs="testTabs" />
        </div>

        <div>
          <h3 class="text-lg font-medium text-theme-primary mb-3">Pills Tabs</h3>
          <Tabs :tabs="testTabs" variant="pills" />
        </div>
      </div>
    </UiCard>

    <!-- Empty State Test -->
    <UiCard variant="glass" class="mb-6">
      <template #header>
        <h2 class="text-xl font-semibold text-theme-primary">Empty State</h2>
      </template>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <EmptyState
          title="Sin datos"
          description="No hay información para mostrar"
          icon="inbox"
        />
        <EmptyState
          title="Error de conexión"
          description="No se pudo conectar con el servidor"
          icon="wifi-off"
          action-text="Reintentar"
        />
      </div>
    </UiCard>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiCard from '../../components/ui/Card.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiAvatar from '../../components/ui/Avatar.vue'
import UiThemeSelector from '../../components/ui/ThemeSelector.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'
import Skeleton from '../../components/ui/Skeleton.vue'
import DataTable from '../../components/ui/DataTable.vue'
import Tabs from '../../components/ui/Tabs.vue'
import EmptyState from '../../components/ui/EmptyState.vue'

const router = useRouter()

// Test data
const testInput = ref('')
const testInput2 = ref('')
const testInput3 = ref('')
const testInput4 = ref('')

const testData = ref([
  { id: 1, name: 'Juan Pérez', email: 'juan@example.com', role: 'Admin' },
  { id: 2, name: 'María García', email: 'maria@example.com', role: 'User' },
  { id: 3, name: 'Carlos López', email: 'carlos@example.com', role: 'User' },
  { id: 4, name: 'Ana Martínez', email: 'ana@example.com', role: 'User' },
  { id: 5, name: 'Luis Rodríguez', email: 'luis@example.com', role: 'User' }
])

const testColumns = ref([
  { key: 'name', label: 'Nombre', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'role', label: 'Rol', sortable: true }
])

const testTabs = ref([
  { id: 'tab1', label: 'Tab 1', content: 'Contenido del Tab 1' },
  { id: 'tab2', label: 'Tab 2', content: 'Contenido del Tab 2' },
  { id: 'tab3', label: 'Tab 3', content: 'Contenido del Tab 3' }
])

const goBack = () => {
  router.go(-1)
}
</script>




















