<template>
  <div data-medical-record-stats class="space-y-6">
    <LoadingSpinner v-if="loading" class="p-8" size="md" text="Cargando estadísticas..." />

    <UiSectionEmpty
      v-else-if="!hasAnyData"
      data-state="empty-stats"
      motif
      title="Sin actividad en esta historia"
      description="Cuando se registren evoluciones, adjuntos o seguimientos, su detalle aparecerá aquí."
    />

    <template v-else>
      <!-- Last evolution: the newest entry the backend aggregates by date. -->
      <section data-stats-block="last-evolution" class="space-y-3">
        <div class="flex items-baseline gap-3">
          <h2 class="text-lg font-semibold text-label">Última evolución</h2>
          <p class="text-sm text-theme-secondary">La entrada más reciente de la historia</p>
        </div>

        <div v-if="lastEvolution" class="flex flex-col gap-2">
          <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-medium text-label" data-last-evolution-date>
              {{ formatDate(lastEvolution.evolution_date) }}
            </p>
            <UiBadge
              v-if="lastEvolution.specialty"
              variant="neutral"
              size="sm"
              shape="pill"
              data-last-evolution-specialty
            >
              {{ specialtyLabel(lastEvolution.specialty) }}
            </UiBadge>
          </div>
          <p
            v-if="assessmentExcerpt"
            class="text-sm leading-relaxed text-theme-secondary"
            data-last-evolution-assessment
          >
            {{ assessmentExcerpt }}
          </p>
          <p v-else class="text-sm text-theme-secondary">
            La evolución más reciente no registra evaluación.
          </p>
        </div>

        <p v-else class="text-sm text-theme-secondary" data-last-evolution-empty>
          Sin evoluciones registradas en esta historia.
        </p>
      </section>

      <!-- Specialties: codes from the payload, never a count next to the chip. -->
      <section data-stats-block="specialties" class="space-y-3 border-t border-hairline pt-5">
        <div class="flex items-baseline gap-3">
          <h2 class="text-lg font-semibold text-label">Especialidades</h2>
          <p class="text-sm text-theme-secondary">Las que aparecen en las evoluciones</p>
        </div>

        <div v-if="specialtyChips.length > 0" class="flex flex-wrap gap-2" data-specialty-chips>
          <UiBadge
            v-for="specialty in specialtyChips"
            :key="specialty.code"
            variant="neutral"
            size="sm"
            shape="pill"
            :data-specialty-chip="specialty.code"
          >
            {{ specialty.label }}
          </UiBadge>
        </div>
        <p v-else class="text-sm text-theme-secondary">
          Sin especialidades registradas en las evoluciones.
        </p>
      </section>

      <!-- Attachment distribution: proportional bars, one per real category. -->
      <section
        data-stats-block="attachment-categories"
        class="space-y-3 border-t border-hairline pt-5"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-lg font-semibold text-label">Distribución de adjuntos</h2>
          <p class="text-sm text-theme-secondary">Cuántos archivos hay por categoría</p>
        </div>

        <ul v-if="attachmentBars.length > 0" class="flex flex-col gap-3" data-attachment-bars>
          <li
            v-for="bar in attachmentBars"
            :key="bar.category"
            class="flex flex-col gap-1.5"
            :data-attachment-category="bar.category"
          >
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-sm font-medium text-label">{{ bar.label }}</span>
              <span class="text-xs text-theme-secondary tabular-nums">{{ bar.count }}</span>
            </div>
            <div
              class="h-2 w-full overflow-hidden rounded-full bg-theme-surface"
              aria-hidden="true"
            >
              <div class="h-2 rounded-full bg-accent-500" :style="{ width: bar.width }" />
            </div>
          </li>
        </ul>
        <p v-else class="text-sm text-theme-secondary">
          Sin adjuntos registrados en esta historia.
        </p>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import LoadingSpinner from '../../../components/ui/LoadingSpinner.vue'
import UiBadge from '../../../components/ui/Badge.vue'
import UiSectionEmpty from '../../../components/ui/UiSectionEmpty.vue'

const props = defineProps({
  stats: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const SPECIALTY_LABELS = {
  odontologia_general: 'Odontología general',
  implantologia: 'Implantología',
  ortodoncia: 'Ortodoncia',
  endodoncia: 'Endodoncia',
  periodoncia: 'Periodoncia',
  cirugia_oral: 'Cirugía oral',
  rehabilitacion: 'Rehabilitación',
  odontopediatria: 'Odontopediatría'
}

const CATEGORY_LABELS = {
  radiografia: 'Radiografía',
  foto_clinica: 'Foto clínica',
  documento: 'Documento',
  otro: 'Otro'
}

const totalRecords = computed(() => Number(props.stats?.total_records) || 0)
const totalEvolutions = computed(() => Number(props.stats?.total_evolutions) || 0)
const totalAttachments = computed(() => Number(props.stats?.total_attachments) || 0)
const pendingFollowUps = computed(() => Number(props.stats?.pending_follow_ups) || 0)

const hasAnyData = computed(
  () =>
    totalRecords.value > 0 ||
    totalEvolutions.value > 0 ||
    totalAttachments.value > 0 ||
    pendingFollowUps.value > 0
)

const lastEvolution = computed(() => props.stats?.last_evolution || null)

const assessmentExcerpt = computed(() => {
  const assessment = String(lastEvolution.value?.assessment || '').trim()

  if (!assessment) return ''

  return assessment.length > 160 ? `${assessment.slice(0, 157).trimEnd()}...` : assessment
})

const specialtyLabel = code => SPECIALTY_LABELS[code] || String(code)

const categoryLabel = category => CATEGORY_LABELS[category] || String(category)

const specialtyChips = computed(() => {
  const codes = Array.isArray(props.stats?.specialties_involved)
    ? props.stats.specialties_involved
    : []
  const seen = new Set()

  return codes
    .filter(code => typeof code === 'string' && code.trim() !== '')
    .filter(code => {
      if (seen.has(code)) return false
      seen.add(code)

      return true
    })
    .map(code => ({ code, label: specialtyLabel(code) }))
    .sort((a, b) => a.label.localeCompare(b.label, 'es'))
})

const attachmentBars = computed(() => {
  const categories = props.stats?.attachment_categories

  if (!categories || typeof categories !== 'object') return []

  const entries = Object.entries(categories)
    .map(([category, count]) => ({ category, count: Number(count) || 0 }))
    .filter(entry => entry.count > 0)
    .sort((a, b) => b.count - a.count || a.category.localeCompare(b.category))
  const max = entries.reduce((highest, entry) => Math.max(highest, entry.count), 0)

  return entries.map(entry => ({
    ...entry,
    label: categoryLabel(entry.category),
    width: max > 0 ? `${Math.round((entry.count / max) * 100)}%` : '0%'
  }))
})

// Date-only payloads (YYYY-MM-DD) are read from their parts so a UTC parse
// never shifts the day by one in a negative-offset timezone.
const formatDate = value => {
  if (!value) return 'Sin fecha'

  const parts = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)

  if (!parts) return String(value)

  const parsed = new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]))

  return parsed.toLocaleDateString('es-ES', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  })
}
</script>
