<template>
  <div data-specialty-records-panel class="space-y-4">
    <p class="text-xs text-theme-secondary" data-specialty-records-scope>
      Los registros de especialidad resumen todo el expediente del paciente, no una historia
      concreta.
    </p>

    <LoadingSpinner
      v-if="loading"
      class="p-8"
      size="md"
      text="Cargando registros de especialidad..."
    />

    <UiSectionEmpty
      v-else-if="error"
      data-state="error-specialty-records"
      title="No pudimos cargar los registros de especialidad"
      :description="error"
    >
      <UiButton variant="secondary" size="sm" data-specialty-records-retry @click="emit('retry')">
        Reintentar
      </UiButton>
    </UiSectionEmpty>

    <UiSectionEmpty
      v-else-if="records.length === 0"
      data-state="empty-specialty-records"
      title="Sin registros de especialidad"
      description="Este paciente todavía no tiene datos clínicos estructurados de ninguna especialidad."
    />

    <ul v-else class="space-y-3" data-specialty-records-list>
      <li
        v-for="record in records"
        :key="`${record.specialtyKey}-${record.id}`"
        data-specialty-record-row
        class="rounded-[var(--radius-control)] bg-canvas p-4"
        style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p
              class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
              data-specialty-record-type
            >
              {{ record.specialtyLabel }}
            </p>
            <p class="mt-1 truncate text-sm font-semibold text-label" data-specialty-record-summary>
              {{ summaryOf(record) }}
            </p>
            <p
              v-if="record.procedure"
              class="mt-1 truncate text-xs text-theme-secondary"
              data-specialty-record-procedure
            >
              {{ procedureLabel(record.procedure) }}
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <span
              v-if="linksOf(record).encounter"
              class="rounded-full px-2 py-0.5 text-xs text-theme-secondary tabular-nums"
              style="border: 1px solid var(--color-hairline)"
              data-specialty-record-encounter
            >
              {{ linksOf(record).encounter.label }}
            </span>
            <span
              v-if="linksOf(record).plan"
              class="rounded-full px-2 py-0.5 text-xs text-theme-secondary tabular-nums"
              style="border: 1px solid var(--color-hairline)"
              data-specialty-record-plan
            >
              {{ linksOf(record).plan.label }}
            </span>
            <RouterLink
              v-if="recordLink(record)"
              :to="recordLink(record)"
              class="text-xs font-medium text-accent hover:underline"
              data-specialty-record-open
            >
              Abrir registro
            </RouterLink>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import UiButton from '@/components/ui/Button.vue'
import UiSectionEmpty from '@/components/ui/UiSectionEmpty.vue'
import {
  describeSpecialtyRecord,
  flattenSpecialtyRecords,
  recordLinks,
  specialtyRecordLink
} from '@/modules/specialty-records/specialtyRecordMeta'

/**
 * Slice 1 of «Registros de especialidad integrados a la atención y la
 * historia»: the read-only tab of the patient workspace.
 *
 * The structured clinical data of the five specialty tables is clinical record,
 * so it belongs next to the evolutions and not only inside its own module. The
 * tab is a reader: it never mounts the specialty form, and the write contract
 * stays where it already lives.
 */
const props = defineProps({
  grouped: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['retry'])

const records = computed(() => flattenSpecialtyRecords(props.grouped))

const summaryOf = record => describeSpecialtyRecord(record) || 'Sin datos clínicos capturados'

const procedureLabel = procedure =>
  [procedure.code ?? '', procedure.name ?? ''].filter(Boolean).join(' · ')

const linksOf = record => recordLinks(record)

const recordLink = record => specialtyRecordLink(record.specialtyKey, record)
</script>
