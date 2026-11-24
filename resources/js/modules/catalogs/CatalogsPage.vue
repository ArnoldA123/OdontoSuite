<template>
  <AppLayout>
    <PageHeader
      kicker="Administración"
      title="Catálogos"
      subtitle="Los maestros que ordenan la agenda, la clínica y la facturación"
      class="mb-6"
    />

    <section aria-label="Catálogos" data-catalogs-hub class="space-y-4">
      <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-3">
        <h2 class="text-2xl font-semibold text-label">Catálogos disponibles</h2>
        <p class="text-sm text-theme-secondary">Tres maestros, tres responsabilidades</p>
      </div>

      <div v-if="catalogs.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <RouterLink
          v-for="catalog in catalogs"
          :key="catalog.key"
          :to="catalog.to"
          :data-catalog-card="catalog.key"
          :aria-label="`Abrir ${catalog.name}`"
          class="block rounded-ios focus:outline-none focus-visible:ring-2 focus-visible:ring-systemBlue-500 focus-visible:ring-offset-2"
        >
          <UiCard
            variant="flat"
            hover
            padding="sm"
            class="h-full"
            :style="{
              border: '1px solid var(--color-hairline)',
              boxShadow: 'var(--elevation-2)'
            }"
          >
            <div class="flex items-start gap-3">
              <span
                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-ios bg-systemBlue-50 text-systemBlue-700"
                aria-hidden="true"
              >
                <component :is="catalog.icon" class="h-5 w-5" />
              </span>
              <div class="min-w-0">
                <p class="text-base font-semibold text-label" data-catalog-card-title>
                  {{ catalog.name }}
                </p>
                <p class="mt-1 text-sm text-theme-secondary" data-catalog-card-description>
                  {{ catalog.description }}
                </p>
              </div>
            </div>
            <div class="mt-4 flex items-center justify-between gap-2">
              <p class="text-sm font-medium text-theme-secondary" data-catalog-card-count>
                {{ countLabel(catalog.key) }}
              </p>
              <p class="flex items-center gap-1 text-sm font-medium text-systemBlue-700">
                Abrir
                <ArrowRightIcon class="h-4 w-4" aria-hidden="true" />
              </p>
            </div>
          </UiCard>
        </RouterLink>
      </div>

      <div v-else data-catalogs-empty>
        <UiEmptyState
          title="No tienes catálogos a tu cargo"
          description="Solo administración gestiona los catálogos maestros."
          :icon="RectangleGroupIcon"
        />
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import {
  ArrowRightIcon,
  CalendarDaysIcon,
  ClipboardDocumentListIcon,
  RectangleGroupIcon,
  TagIcon
} from '@heroicons/vue/24/outline'
import { computed, onMounted, reactive } from 'vue'
import { useApi } from '../../composables/useApi'
import { usePermissions } from '../../composables/usePermissions'
import AppLayout from '../../components/layout/AppLayout.vue'
import PageHeader from '../../components/layout/PageHeader.vue'
import UiCard from '../../components/ui/Card.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'

const { get } = useApi()
const { can } = usePermissions()

const CATALOGS = [
  {
    key: 'specialties',
    to: '/specialties',
    name: 'Especialidades',
    description: 'Clasificador que ordena procedimientos y profesionales.',
    icon: TagIcon,
    permission: 'manageSpecialties',
    countEndpoint: '/api/specialties',
    countFrom: response => response?.meta?.kpis?.active
  },
  {
    key: 'appointment-types',
    to: '/appointment-types',
    name: 'Tipos de cita',
    description: 'Plantillas de agenda con duración, precio y confirmación.',
    icon: CalendarDaysIcon,
    permission: 'manageAppointmentTypes',
    countEndpoint: '/api/appointment-types',
    countFrom: response => response?.meta?.total
  },
  {
    key: 'procedures',
    to: '/procedure-catalog',
    name: 'Procedimientos',
    description: 'Actos clínicos y facturables con sitio, tarifario y materiales.',
    icon: ClipboardDocumentListIcon,
    permission: 'manageProcedureCatalog',
    countEndpoint: '/api/procedure-catalog/summary',
    countFrom: response => response?.data?.active
  }
]

const counts = reactive({})

const catalogs = computed(() => CATALOGS.filter(catalog => can[catalog.permission]?.value === true))

const normalizeCount = value => {
  if (value === null || value === undefined || value === '') return null

  const numeric = Number(value)

  return Number.isFinite(numeric) && numeric >= 0 ? Math.trunc(numeric) : null
}

const countLabel = key => {
  const count = normalizeCount(counts[key])

  if (count === null) return 'Activos: —'

  return `Activos: ${count}`
}

const loadCounts = async () => {
  await Promise.all(
    catalogs.value.map(async catalog => {
      try {
        const response = await get(catalog.countEndpoint)
        counts[catalog.key] = normalizeCount(catalog.countFrom(response))
      } catch {
        counts[catalog.key] = null
      }
    })
  )
}

onMounted(loadCounts)
</script>
