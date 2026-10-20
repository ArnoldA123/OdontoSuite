<template>
  <AppLayout>
    <div v-if="loading" data-branch-detail-loading>
      <UiSkeleton variant="card" animation="wave" aria-label="Cargando sucursal..." />
    </div>

    <div
      v-else-if="loadError"
      data-branch-detail-error
      class="flex items-center justify-between flex-wrap gap-4 rounded-[var(--radius-panel)] p-5 bg-systemRed-50"
      style="border: 1px solid var(--color-hairline)"
    >
      <div class="flex items-center gap-3 min-w-0">
        <ExclamationTriangleIcon
          class="flex-shrink-0 w-6 h-6 text-systemRed-600"
          aria-hidden="true"
        />
        <div class="min-w-0">
          <p class="text-sm font-medium text-theme-primary">No pudimos cargar la sucursal</p>
          <p class="text-sm text-theme-secondary">Revisa tu conexión y vuelve a intentarlo.</p>
        </div>
      </div>
      <UiButton variant="primary" size="sm" data-retry-branch-detail @click="loadBranch">
        Reintentar
      </UiButton>
    </div>

    <template v-else-if="branch">
      <header
        data-branch-detail-hero
        class="mb-6 p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p
              class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
              data-hero-eyebrow
            >
              Sucursal
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
              <h2 class="text-4xl font-semibold text-label" data-hero-title>{{ branch.name }}</h2>
              <UiStatusBadge
                data-branch-status
                :variant="getBranchStatusVariant(branch.is_active)"
                :label="getBranchStatusLabel(branch.is_active)"
              />
            </div>
            <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
              Código {{ branch.code }}
            </p>
          </div>
          <div class="flex flex-shrink-0 items-center gap-3">
            <UiButton data-branch-detail-edit @click="goEdit">
              <template #icon-left>
                <PencilSquareIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Editar
            </UiButton>
            <UiButton variant="secondary" data-detail-back @click="goBack">
              <template #icon-left>
                <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Volver
            </UiButton>
          </div>
        </div>
      </header>

      <UiCard variant="glass" padding="sm" :style="panelStyle" data-branch-summary>
        <div class="flex items-start gap-4">
          <div
            class="h-16 w-16 rounded-[var(--radius-card-lg)] bg-systemBlue-50 flex items-center justify-center"
          >
            <BuildingOffice2Icon class="w-8 h-8 text-systemBlue-700" aria-hidden="true" />
          </div>
          <div class="flex-1 min-w-0">
            <h2 class="text-lg font-semibold text-theme-primary">{{ branch.name }}</h2>
            <p class="mt-1 text-sm text-theme-secondary">{{ formatBranchLocation(branch) }}</p>

            <section class="mt-6">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Ubicación
              </h3>
              <dl class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6">
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Dirección</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="address">
                    {{ branch.address || 'No registrada' }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Ciudad</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="city">
                    {{ branch.city || 'No registrada' }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Departamento</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="state">
                    {{ branch.state || 'No registrado' }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">País</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="country">
                    {{ branch.country || 'No registrado' }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Código postal</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="postal_code">
                    {{ branch.postal_code || 'No registrado' }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Coordenadas</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="coordinates">
                    {{ formatBranchCoordinates(branch) }}
                  </dd>
                </div>
              </dl>
            </section>

            <section class="mt-6">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Contacto
              </h3>
              <dl class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6">
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Teléfono</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="phone">
                    {{ branch.phone || 'No registrado' }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Correo electrónico</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="email">
                    {{ branch.email || 'No registrado' }}
                  </dd>
                </div>
              </dl>
            </section>

            <section class="mt-6">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Registro y operación
              </h3>
              <dl class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6">
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Creada</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="created_at">
                    {{ formatBranchDate(branch.created_at) }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Actualizada</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="updated_at">
                    {{ formatBranchDate(branch.updated_at) }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3">
                  <dt class="text-xs font-medium text-theme-secondary">Zona horaria</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="timezone">
                    {{ branch.timezone || 'No registrada' }}
                  </dd>
                </div>
                <div class="border-t border-[color:var(--color-hairline)] py-3 md:col-span-2">
                  <dt class="text-xs font-medium text-theme-secondary">Descripción</dt>
                  <dd class="mt-1 text-sm text-theme-primary" data-detail-field="description">
                    {{ branch.description || 'Sin descripción' }}
                  </dd>
                </div>
              </dl>
            </section>
          </div>
        </div>
      </UiCard>
    </template>
  </AppLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  BuildingOffice2Icon,
  ExclamationTriangleIcon,
  PencilSquareIcon
} from '@heroicons/vue/24/outline'
import { useBranches } from '../../../composables/useBranches'
import {
  formatBranchCoordinates,
  formatBranchDate,
  formatBranchLocation,
  getBranchStatusLabel,
  getBranchStatusVariant
} from './branchPresentation'
import AppLayout from '../../../components/layout/AppLayout.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiCard from '../../../components/ui/Card.vue'
import UiSkeleton from '../../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../../components/ui/StatusBadge.vue'

const route = useRoute()
const router = useRouter()
const { getBranch } = useBranches()

const branch = ref(null)
const loading = ref(true)
const loadError = ref(false)

const panelStyle = {
  borderColor: 'var(--color-hairline)',
  borderRadius: 'var(--radius-panel)',
  boxShadow: 'var(--elevation-2)'
}

const loadBranch = async () => {
  loading.value = true
  loadError.value = false
  try {
    branch.value = await getBranch(route.params.id)
    if (!branch.value) {
      loadError.value = true
    }
  } catch {
    branch.value = null
    loadError.value = true
  } finally {
    loading.value = false
  }
}

const goBack = () => {
  router.push('/settings/branches')
}

const goEdit = () => {
  const id = branch.value?.id
  if (id === null || id === undefined) {
    router.push('/settings/branches')
    return
  }
  router.push({ path: '/settings/branches', query: { edit: String(id) } })
}

onMounted(() => {
  loadBranch()
})
</script>
