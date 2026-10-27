<template>
  <article
    data-plan-card
    class="flex cursor-pointer flex-col overflow-hidden rounded-[var(--radius-panel)] bg-theme-surface-elevated hover-lift"
    :class="{ 'opacity-70': plan.status === 'cancelled' }"
    :style="surfaceStyle"
    :aria-label="`Plan ${plan.plan_number}: ${plan.title}`"
    @click="emit('view', plan)"
  >
    <header
      class="flex items-start justify-between gap-3 p-4"
      :style="{ borderBottom: '1px solid var(--color-hairline)' }"
    >
      <div class="min-w-0">
        <h3 class="truncate text-lg font-semibold text-label" data-plan-title>
          {{ plan.title }}
        </h3>
        <p class="mt-0.5 truncate font-mono text-xs tabular-nums text-theme-secondary">
          {{ plan.plan_number }}
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-1.5">
        <span
          v-if="plan.is_overdue"
          data-plan-overdue
          class="inline-flex items-center gap-1 rounded-[var(--radius-control)] bg-systemRed-50 px-2 py-0.5 text-xs font-medium text-systemRed-600"
        >
          <ExclamationTriangleIcon class="h-3 w-3" aria-hidden="true" />
          Vencido
        </span>
        <PlanStatusBadge :status="plan.status" />
      </div>
    </header>

    <div class="flex-1 space-y-3 p-4">
      <div class="flex items-center gap-2">
        <UserIcon class="h-4 w-4 shrink-0 text-theme-secondary" aria-hidden="true" />
        <span class="truncate text-sm text-theme-primary" data-plan-patient>
          {{ patientName }}
        </span>
      </div>

      <dl class="space-y-2">
        <div class="flex items-center justify-between gap-3 text-sm">
          <dt class="text-theme-secondary">Procedimientos</dt>
          <dd class="tabular-nums text-theme-primary" data-plan-items>{{ totalItems }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
          <dt class="text-theme-secondary">Costo total</dt>
          <dd class="font-semibold tabular-nums text-theme-primary" data-plan-cost>
            {{ formatCurrency(plan.final_cost) }}
          </dd>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
          <dt class="text-theme-secondary">Duración estimada</dt>
          <dd class="tabular-nums text-theme-primary">
            {{ plan.estimated_duration_weeks || '—' }} semanas
          </dd>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
          <dt class="text-theme-secondary">Creado</dt>
          <dd class="tabular-nums text-theme-primary">{{ formatDate(plan.created_at) }}</dd>
        </div>
      </dl>

      <div v-if="progress && totalItems > 0" class="space-y-1.5" data-plan-progress>
        <UiProgressBar :value="progressPercentage" :show-label="false" label="Progreso del plan" />
        <p
          class="flex items-center justify-between gap-2 text-xs tabular-nums text-theme-secondary"
        >
          <span>{{ progress.completed_items }}/{{ progress.total_items }} completados</span>
          <span>{{ progressPercentage }}%</span>
        </p>
      </div>

      <p
        v-if="plan.notes"
        class="line-clamp-2 rounded-[var(--radius-control)] bg-canvas p-2.5 text-sm text-theme-secondary"
        data-plan-notes
      >
        {{ plan.notes }}
      </p>
    </div>

    <footer
      class="flex items-center justify-between gap-2 p-3"
      :style="{ borderTop: '1px solid var(--color-hairline)' }"
      @click.stop
    >
      <div class="flex items-center gap-1">
        <UiButton
          variant="ghost"
          size="xs"
          data-action="view"
          title="Ver detalles"
          aria-label="Ver detalles"
          @click="emit('view', plan)"
        >
          <template #icon-left>
            <EyeIcon class="h-4 w-4" aria-hidden="true" />
          </template>
        </UiButton>
        <UiButton
          variant="ghost"
          size="xs"
          data-action="edit"
          title="Editar"
          aria-label="Editar"
          :disabled="!canEdit"
          @click="emit('edit', plan)"
        >
          <template #icon-left>
            <PencilIcon class="h-4 w-4" aria-hidden="true" />
          </template>
        </UiButton>
        <UiButton
          variant="ghost"
          size="xs"
          data-action="duplicate"
          title="Duplicar"
          aria-label="Duplicar"
          @click="emit('duplicate', plan)"
        >
          <template #icon-left>
            <DocumentDuplicateIcon class="h-4 w-4" aria-hidden="true" />
          </template>
        </UiButton>
      </div>

      <div class="relative flex items-center gap-1">
        <UiButton
          v-if="canChangeStatus && availableStatuses.length"
          variant="ghost"
          size="xs"
          data-status-toggle
          :title="`Cambiar estado (actual: ${plan.status})`"
          aria-label="Cambiar estado"
          aria-haspopup="menu"
          :aria-expanded="showStatusMenu"
          @click="showStatusMenu = !showStatusMenu"
        >
          <template #icon-left>
            <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
          </template>
        </UiButton>
        <UiButton
          variant="ghost"
          size="xs"
          data-action="delete"
          class="hover:text-systemRed-600"
          title="Eliminar"
          aria-label="Eliminar"
          :disabled="!canDelete"
          @click="confirmDelete"
        >
          <template #icon-left>
            <TrashIcon class="h-4 w-4" aria-hidden="true" />
          </template>
        </UiButton>

        <div
          v-if="showStatusMenu"
          data-status-menu
          class="absolute bottom-full right-0 z-20 mb-2 min-w-[180px] overflow-hidden rounded-[var(--radius-control)] bg-theme-surface-elevated p-1"
          role="menu"
          :style="menuStyle"
          @click.stop
        >
          <button
            v-for="status in availableStatuses"
            :key="status.value"
            type="button"
            role="menuitem"
            :data-status-option="status.value"
            class="w-full rounded-[var(--radius-control)] px-3 py-2 text-left text-sm text-theme-primary hover:bg-theme-surface"
            @click="changeStatus(status.value)"
          >
            {{ status.label }}
          </button>
        </div>
      </div>
    </footer>
  </article>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useConfirm } from '@/composables/useConfirm'
import { formatCurrency, formatDate as formatCanonicalDate } from '@/composables/useFormatters'
import UiButton from '@/components/ui/Button.vue'
import UiProgressBar from '@/components/ui/ProgressBar.vue'
import PlanStatusBadge from './PlanStatusBadge.vue'
import {
  UserIcon,
  EyeIcon,
  PencilIcon,
  DocumentDuplicateIcon,
  ArrowPathIcon,
  TrashIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  plan: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['view', 'edit', 'duplicate', 'changeStatus', 'delete'])

const { user } = useAuth()
const { confirm } = useConfirm()
const showStatusMenu = ref(false)

const surfaceStyle = {
  border: '1px solid var(--color-hairline)',
  boxShadow: 'var(--elevation-2)'
}

const menuStyle = {
  border: '1px solid var(--color-hairline)',
  boxShadow: 'var(--elevation-2)'
}

const canChangeStatus = computed(() =>
  ['administrador', 'odontologo', 'implantologo'].includes(user.value?.role)
)

const canEdit = computed(() => {
  if (['administrador', 'odontologo', 'implantologo'].includes(user.value?.role)) {
    return true
  }
  return ['draft', 'proposed'].includes(props.plan.status)
})

const canDelete = computed(() => {
  if (!['administrador'].includes(user.value?.role)) return false
  return props.plan.status === 'draft'
})

const patientName = computed(() => {
  const name = [props.plan.patient?.first_name, props.plan.patient?.last_name]
    .filter(Boolean)
    .join(' ')

  return name || 'Sin paciente'
})

const totalItems = computed(() => props.plan.progress?.total_items ?? props.plan.items?.length ?? 0)
const progress = computed(() => props.plan.progress ?? null)
const progressPercentage = computed(() => Number(progress.value?.progress_percentage ?? 0))

const availableStatuses = computed(() => {
  const map = {
    draft: [
      { value: 'proposed', label: 'Propuesto' },
      { value: 'cancelled', label: 'Cancelado' }
    ],
    proposed: [
      { value: 'approved', label: 'Aprobado' },
      { value: 'cancelled', label: 'Cancelado' }
    ],
    approved: [
      { value: 'in_progress', label: 'En Progreso' },
      { value: 'cancelled', label: 'Cancelado' }
    ],
    in_progress: [
      { value: 'completed', label: 'Completado' },
      { value: 'cancelled', label: 'Cancelado' }
    ]
  }
  return map[props.plan.status] || []
})

// issue 451: canonical short date; the visible fallback stays at the call site.
const formatDate = date =>
  formatCanonicalDate(date, { year: 'numeric', month: 'short', day: 'numeric' }) || '—'

const changeStatus = status => {
  showStatusMenu.value = false
  emit('changeStatus', props.plan.id, status)
}

const confirmDelete = async () => {
  const ok = await confirm({
    title: 'Eliminar plan de tratamiento',
    message: `¿Eliminar el plan "${props.plan.title}" (${props.plan.plan_number})?\n\nEsta acción no se puede deshacer.`,
    confirmText: 'Eliminar',
    variant: 'danger'
  })
  if (ok) emit('delete', props.plan.id)
}

const handleClickOutside = e => {
  if (
    showStatusMenu.value &&
    !e.target.closest('[data-status-menu]') &&
    !e.target.closest('[data-status-toggle]')
  ) {
    showStatusMenu.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>
