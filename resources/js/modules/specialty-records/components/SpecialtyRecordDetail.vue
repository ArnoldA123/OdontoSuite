<template>
  <UiModal model-value :title="title" size="lg" @close="emit('close')">
    <div class="space-y-6 bg-canvas text-theme-primary" data-specialty-record-detail>
      <div
        class="flex flex-wrap items-start justify-between gap-3 rounded-[var(--radius-panel)] p-4"
        style="border: 1px solid var(--color-hairline); background: var(--color-canvas)"
      >
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
            {{ definition?.label }}
          </p>
          <p class="mt-1 truncate text-sm font-semibold text-label" data-detail-patient>
            {{ patientName }}
          </p>
          <p v-if="date" class="mt-1 text-xs text-theme-secondary tabular-nums" data-detail-date>
            {{ date.label }}: {{ date.value }}
          </p>
        </div>
        <UiStatusBadge v-if="statusText" :variant="badgeVariant" :label="statusText" size="sm" />
      </div>

      <section
        v-for="section in sections"
        :key="section.id"
        :data-detail-section="section.id"
        class="space-y-3"
      >
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          {{ section.label }}
        </h3>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div v-for="entry in section.fields" :key="entry.key" :data-detail-field="entry.key">
            <dt class="text-xs text-theme-secondary">{{ entry.label }}</dt>
            <dd class="mt-0.5 break-words text-sm text-theme-primary">{{ entry.value }}</dd>
          </div>
        </dl>
      </section>

      <p v-if="sections.length === 0" class="text-sm text-theme-secondary">
        Este registro todavía no tiene datos clínicos capturados.
      </p>

      <p class="text-xs text-theme-secondary tabular-nums" data-detail-created>
        Creado {{ createdLabel }}
      </p>
    </div>

    <template #footer>
      <UiButton variant="secondary" data-detail-close @click="emit('close')">Cerrar</UiButton>
      <UiButton v-if="canEdit" data-detail-edit @click="emit('edit')">Editar</UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import UiButton from '@/components/ui/Button.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import {
  detailSections,
  formatRecordDate,
  getSpecialtyDefinition,
  primaryDate,
  statusLabel,
  statusVariant
} from '../specialtyRecordMeta'

const props = defineProps({
  record: {
    type: Object,
    required: true
  },
  specialty: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'edit'])

const { can } = usePermissions()

const canEdit = computed(() => can.editSpecialtyRecord?.value ?? false)

const definition = computed(() => getSpecialtyDefinition(props.specialty))

const title = computed(() =>
  definition.value ? `Registro de ${definition.value.label}` : 'Registro de especialidad'
)

const patientName = computed(() => {
  const patient = props.record?.patient

  if (!patient) return 'Paciente sin datos'

  return `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim()
})

const date = computed(() => primaryDate(definition.value, props.record))
const sections = computed(() => detailSections(definition.value, props.record))

const statusText = computed(() => {
  const status = props.record?.[definition.value?.statusField]

  return status ? statusLabel(props.specialty, status) : ''
})

const badgeVariant = computed(() =>
  statusVariant(props.specialty, props.record?.[definition.value?.statusField])
)

const createdLabel = computed(() => formatRecordDate(props.record?.created_at))
</script>
