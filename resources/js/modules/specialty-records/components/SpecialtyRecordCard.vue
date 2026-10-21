<template>
  <UiCard
    variant="glass"
    padding="sm"
    class="bg-canvas h-full"
    data-specialty-record-card
    :data-specialty="definition.key"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
          {{ definition.label }}
        </p>
        <p class="mt-1 text-sm font-semibold text-label" data-record-title>
          {{ title }}
        </p>
      </div>
      <UiStatusBadge v-if="statusText" :variant="badgeVariant" :label="statusText" size="sm" />
    </div>

    <p v-if="date" class="mt-2 text-xs text-theme-secondary tabular-nums" data-record-date>
      {{ date.label }}: {{ date.value }}
    </p>

    <dl v-if="summary.length > 0" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div v-for="entry in summary" :key="entry.key" :data-summary-field="entry.key">
        <dt class="text-xs text-theme-secondary">{{ entry.label }}</dt>
        <dd class="mt-0.5 break-words text-sm text-theme-primary tabular-nums">
          {{ entry.value }}
        </dd>
      </div>
    </dl>

    <p v-else class="mt-4 text-sm text-theme-secondary">
      Este registro todavía no tiene datos clínicos.
    </p>

    <div
      class="mt-4 flex flex-wrap items-center justify-between gap-2 pt-3"
      style="border-top: 1px solid var(--color-hairline)"
    >
      <p class="text-xs text-theme-secondary tabular-nums" data-record-created>
        Creado {{ createdLabel }}
      </p>
      <div class="flex items-center gap-1">
        <UiButton variant="ghost" size="sm" data-record-action="view" @click="emit('view')">
          Ver
        </UiButton>
        <UiButton
          v-if="canEdit"
          variant="ghost"
          size="sm"
          class="text-systemBlue-600"
          data-record-action="edit"
          @click="emit('edit')"
        >
          Editar
        </UiButton>
        <UiButton
          v-if="canDelete"
          variant="ghost"
          size="sm"
          class="text-systemRed-700"
          data-record-action="delete"
          @click="emit('delete')"
        >
          Eliminar
        </UiButton>
      </div>
    </div>
  </UiCard>
</template>

<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import {
  formatRecordDate,
  primaryDate,
  statusLabel,
  statusVariant,
  summaryFields
} from '../specialtyRecordMeta'

const props = defineProps({
  record: {
    type: Object,
    required: true
  },
  definition: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['view', 'edit', 'delete'])

const { can } = usePermissions()

const canEdit = computed(() => can.editSpecialtyRecord?.value ?? false)
const canDelete = computed(() => can.deleteSpecialtyRecord?.value ?? false)

const summary = computed(() => summaryFields(props.definition, props.record))
const date = computed(() => primaryDate(props.definition, props.record))

const title = computed(() => {
  const first = summary.value[0]

  return first ? first.value : props.definition.label
})

const statusText = computed(() => {
  const status = props.record?.[props.definition.statusField]

  return status ? statusLabel(props.definition.key, status) : ''
})

const badgeVariant = computed(() =>
  statusVariant(props.definition.key, props.record?.[props.definition.statusField])
)

const createdLabel = computed(() => formatRecordDate(props.record?.created_at))
</script>
