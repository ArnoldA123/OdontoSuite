<template>
  <div class="medical-record-stats">
    <div class="stats-header">
      <h3 class="stats-title">Estadísticas de Historia Clínica</h3>
    </div>

    <div class="stats-grid">
      <!-- Total de evoluciones -->
      <div class="stat-card">
        <div class="stat-icon">
          <ClockIcon class="w-8 h-8 text-accent" />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total_evolutions || 0 }}</div>
          <div class="stat-label">Evoluciones</div>
        </div>
      </div>

      <!-- Archivos adjuntos -->
      <div class="stat-card">
        <div class="stat-icon">
          <DocumentIcon class="w-8 h-8 text-green-600" />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total_attachments || 0 }}</div>
          <div class="stat-label">Archivos</div>
        </div>
      </div>

      <!-- Última actualización -->
      <div class="stat-card">
        <div class="stat-icon">
          <CalendarIcon class="w-8 h-8 text-purple-600" />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ formatLastUpdate(stats.last_updated) }}</div>
          <div class="stat-label">Última actualización</div>
        </div>
      </div>

      <!-- Días desde creación -->
      <div class="stat-card">
        <div class="stat-icon">
          <CalendarDaysIcon class="w-8 h-8 text-orange-600" />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.days_since_creation || 0 }}</div>
          <div class="stat-label">Días activo</div>
        </div>
      </div>
    </div>

    <!-- Gráfico simple de evoluciones por mes -->
    <div class="chart-section" v-if="stats.evolutions_by_month && stats.evolutions_by_month.length > 0">
      <h4 class="chart-title">Evoluciones por Mes</h4>
      <div class="chart-container">
        <div class="chart-bars">
          <div
            v-for="(month, index) in stats.evolutions_by_month"
            :key="index"
            class="chart-bar"
            :style="{ height: `${(month.count / maxEvolutions) * 100}%` }"
          >
            <div class="bar-value">{{ month.count }}</div>
            <div class="bar-label">{{ month.month }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resumen de archivos por tipo -->
    <div class="files-summary" v-if="stats.attachments_by_type && stats.attachments_by_type.length > 0">
      <h4 class="summary-title">Archivos por Tipo</h4>
      <div class="files-grid">
        <div
          v-for="type in stats.attachments_by_type"
          :key="type.type"
          class="file-type-item"
        >
          <div class="file-type-icon">
            <DocumentIcon v-if="type.type === 'document'" class="w-5 h-5" />
            <PhotoIcon v-else-if="type.type === 'image'" class="w-5 h-5" />
            <DocumentTextIcon v-else-if="type.type === 'xray'" class="w-5 h-5" />
            <DocumentIcon v-else class="w-5 h-5" />
          </div>
          <div class="file-type-info">
            <div class="file-type-name">{{ getFileTypeLabel(type.type) }}</div>
            <div class="file-type-count">{{ type.count }} archivos</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Actividad reciente -->
    <div class="recent-activity" v-if="stats.recent_activity && stats.recent_activity.length > 0">
      <h4 class="activity-title">Actividad Reciente</h4>
      <div class="activity-list">
        <div
          v-for="activity in stats.recent_activity"
          :key="activity.id"
          class="activity-item"
        >
          <div class="activity-icon">
            <ClockIcon v-if="activity.type === 'evolution'" class="w-4 h-4" />
            <DocumentIcon v-else-if="activity.type === 'attachment'" class="w-4 h-4" />
            <PencilIcon v-else class="w-4 h-4" />
          </div>
          <div class="activity-content">
            <div class="activity-description">{{ activity.description }}</div>
            <div class="activity-date">{{ formatDate(activity.created_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  ClockIcon,
  DocumentIcon,
  CalendarIcon,
  CalendarDaysIcon,
  PhotoIcon,
  DocumentTextIcon,
  PencilIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({})
  }
})

const maxEvolutions = computed(() => {
  if (!props.stats.evolutions_by_month) return 1
  return Math.max(...props.stats.evolutions_by_month.map(m => m.count), 1)
})

const getFileTypeLabel = (type) => {
  const labels = {
    image: 'Imágenes',
    document: 'Documentos',
    xray: 'Radiografías',
    other: 'Otros'
  }
  return labels[type] || 'Archivos'
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatLastUpdate = (date) => {
  if (!date) return 'N/A'
  const now = new Date()
  const lastUpdate = new Date(date)
  const diffTime = Math.abs(now - lastUpdate)
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays === 1) return 'Ayer'
  if (diffDays < 7) return `${diffDays} días`
  if (diffDays < 30) return `${Math.ceil(diffDays / 7)} semanas`
  return `${Math.ceil(diffDays / 30)} meses`
}
</script>

<style scoped>
.medical-record-stats {
  @apply space-y-6;
}

.stats-header {
  @apply border-b border-theme pb-4;
}

.stats-title {
  @apply text-lg font-medium text-theme-primary;
}

.stats-grid {
  @apply grid grid-cols-2 lg:grid-cols-4 gap-4;
}

.stat-card {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-4 flex items-center space-x-3;
}

.stat-icon {
  @apply flex-shrink-0;
}

.stat-content {
  @apply flex-1 min-w-0;
}

.stat-value {
  @apply text-2xl font-bold text-theme-primary;
}

.stat-label {
  @apply text-sm text-theme-secondary;
}

.chart-section {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-6;
}

.chart-title {
  @apply text-lg font-medium text-theme-primary mb-4;
}

.chart-container {
  @apply h-32;
}

.chart-bars {
  @apply flex items-end justify-between h-full space-x-2;
}

.chart-bar {
  @apply bg-primary-100 rounded-t flex-1 flex flex-col items-center justify-end relative;
  min-height: 20px;
}

.bar-value {
  @apply text-xs font-medium text-primary-700 mb-1;
}

.bar-label {
  @apply text-xs text-theme-secondary;
}

.files-summary {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-6;
}

.summary-title {
  @apply text-lg font-medium text-theme-primary mb-4;
}

.files-grid {
  @apply grid grid-cols-1 sm:grid-cols-2 gap-3;
}

.file-type-item {
  @apply flex items-center space-x-3 p-3 bg-theme-surface rounded-lg;
}

.file-type-icon {
  @apply flex-shrink-0 text-theme-secondary;
}

.file-type-info {
  @apply flex-1 min-w-0;
}

.file-type-name {
  @apply text-sm font-medium text-theme-primary;
}

.file-type-count {
  @apply text-xs text-theme-secondary;
}

.recent-activity {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-6;
}

.activity-title {
  @apply text-lg font-medium text-theme-primary mb-4;
}

.activity-list {
  @apply space-y-3;
}

.activity-item {
  @apply flex items-start space-x-3 p-3 bg-theme-surface rounded-lg;
}

.activity-icon {
  @apply flex-shrink-0 text-theme-secondary mt-0.5;
}

.activity-content {
  @apply flex-1 min-w-0;
}

.activity-description {
  @apply text-sm text-theme-primary;
}

.activity-date {
  @apply text-xs text-theme-secondary mt-1;
}
</style>
