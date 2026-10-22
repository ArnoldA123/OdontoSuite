<template>
  <UiCard
    variant="glass"
    padding="none"
    data-medical-record-card
    :data-record-id="record.id"
    class="overflow-hidden"
    style="
      border: 1px solid var(--color-hairline);
      border-radius: var(--radius-panel);
      box-shadow: var(--elevation-2);
    "
  >
    <div class="flex flex-col gap-4 p-5 md:flex-row md:items-start md:justify-between">
      <div class="min-w-0 space-y-2">
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-base font-semibold text-label tabular-nums" data-record-number>
            {{ recordNumberLabel }}
          </p>
          <StatusBadge :variant="statusVariant" size="sm" show-dot data-record-status>
            {{ statusLabel }}
          </StatusBadge>
        </div>

        <p class="text-xs text-theme-secondary">
          Primera visita
          <span class="tabular-nums" data-first-visit>{{ firstVisitLabel }}</span>
          <template v-if="createdByLabel">
            <span aria-hidden="true">·</span>
            <span data-created-by>Registrada por {{ createdByLabel }}</span>
          </template>
        </p>

        <p class="text-sm text-theme-primary" data-chief-complaint>
          <span class="text-theme-secondary">Motivo de consulta:</span>
          {{ chiefComplaintLabel }}
        </p>

        <p v-if="diagnosisLabel" class="text-sm text-theme-primary" data-diagnosis>
          <span class="text-theme-secondary">Diagnóstico:</span>
          {{ diagnosisLabel }}
        </p>
      </div>

      <div class="flex flex-col gap-3 md:flex-shrink-0 md:items-end">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-theme-secondary">
          <span class="tabular-nums" data-evolutions-count>{{ evolutionsCount }} evoluciones</span>
          <span class="tabular-nums" data-attachments-count>{{ attachmentsCount }} adjuntos</span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <UiButton
            v-if="canView"
            variant="ghost"
            size="sm"
            data-record-action="view"
            @click="emit('view', record)"
          >
            <template #icon-left>
              <EyeIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Ver
          </UiButton>
          <UiButton
            v-if="canEdit"
            variant="ghost"
            size="sm"
            data-record-action="edit"
            @click="emit('edit', record)"
          >
            <template #icon-left>
              <PencilIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Editar
          </UiButton>
        </div>
      </div>
    </div>
  </UiCard>
</template>

<script setup>
import { computed } from 'vue'
import { EyeIcon, PencilIcon } from '@heroicons/vue/24/outline'
import UiCard from '@/components/ui/Card.vue'
import UiButton from '@/components/ui/Button.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { usePermissions } from '@/composables/usePermissions'
import { formatDateOnly } from '@/utils/formatters'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['view', 'edit'])

const { can } = usePermissions()

// `can` is an object of computed refs (decision D7), never a function.
const canView = computed(() => can.viewMedicalRecord?.value ?? false)
const canEdit = computed(() => can.editMedicalRecord?.value ?? false)

const recordNumberLabel = computed(() => props.record.record_number || 'Sin número asignado')
const firstVisitLabel = computed(() => formatDateOnly(props.record.first_visit_date) || 'Sin fecha')
const createdByLabel = computed(() => props.record.createdBy?.name || '')
const chiefComplaintLabel = computed(
  () => props.record.chief_complaint || 'Sin motivo de consulta registrado'
)
const diagnosisLabel = computed(() => props.record.diagnosis || '')

// The index ships `evolutions_count` / `attachments_count`; the relations are
// not eager-loaded there, so their arrays can never be counted client-side.
const evolutionsCount = computed(() => Number(props.record.evolutions_count ?? 0))
const attachmentsCount = computed(() => Number(props.record.attachments_count ?? 0))

const statusLabel = computed(() => (props.record.is_active ? 'Activa' : 'Inactiva'))
const statusVariant = computed(() => (props.record.is_active ? 'success' : 'neutral'))
</script>
