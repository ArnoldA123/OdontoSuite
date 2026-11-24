<template>
  <AppLayout>
    <PageHeader :kicker="kicker" :title="title" :subtitle="subtitle" class="mb-6">
      <template v-if="$slots.actions" #actions>
        <slot name="actions" />
      </template>
    </PageHeader>

    <section
      v-if="$slots.kpis"
      :aria-label="kpiLabel || `Resumen de ${title}`"
      data-catalog-kpis
      :class="kpiClasses"
      class="mb-6"
    >
      <slot name="kpis" />
    </section>

    <div v-if="$slots.filters" data-catalog-filters class="mb-6">
      <slot name="filters" />
    </div>

    <section :aria-label="listLabel || title" data-catalog-list class="space-y-4">
      <slot name="heading" />

      <div data-catalog-content :data-catalog-state="state" :class="contentClass">
        <slot v-if="state === 'loading'" name="loading">
          <UiSkeleton variant="table" animation="wave" :aria-label="loadingLabel" />
        </slot>
        <template v-else-if="state === 'error'">
          <slot name="error" />
        </template>
        <template v-else-if="state === 'empty'">
          <slot name="empty" />
        </template>
        <template v-else>
          <slot name="table" />
        </template>
      </div>
    </section>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import AppLayout from '../layout/AppLayout.vue'
import PageHeader from '../layout/PageHeader.vue'
import UiSkeleton from '../ui/Skeleton.vue'

const props = defineProps({
  kicker: { type: String, default: '' },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  listLabel: { type: String, default: '' },
  kpiLabel: { type: String, default: '' },
  kpiColumns: {
    type: Number,
    default: 4,
    validator: value => [2, 3, 4, 5, 6].includes(value)
  },
  state: {
    type: String,
    default: 'ready',
    validator: value => ['ready', 'loading', 'error', 'empty'].includes(value)
  },
  loadingLabel: { type: String, default: 'Cargando contenido...' },
  contentClass: { type: String, default: '' }
})

const KPI_COLUMNS = {
  2: 'grid-cols-1 sm:grid-cols-2',
  3: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
  4: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
  5: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
  6: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6'
}

const kpiClasses = computed(() => ['grid gap-4', KPI_COLUMNS[props.kpiColumns]])
</script>
