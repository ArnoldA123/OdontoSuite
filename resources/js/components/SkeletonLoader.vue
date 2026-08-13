<template>
  <div class="animate-pulse">
    <!-- Card skeleton -->
    <div v-if="type === 'card'" class="bg-theme-surface-elevated rounded-lg shadow p-6">
      <div class="flex items-center space-x-4">
        <div class="rounded-full bg-theme-surface h-12 w-12" />
        <div class="flex-1 space-y-2">
          <div class="h-4 bg-theme-surface rounded w-3/4" />
          <div class="h-3 bg-theme-surface rounded w-1/2" />
        </div>
      </div>
      <div class="mt-4 space-y-2">
        <div class="h-3 bg-theme-surface rounded" />
        <div class="h-3 bg-theme-surface rounded w-5/6" />
      </div>
    </div>

    <!-- Table skeleton -->
    <div
      v-else-if="type === 'table'"
      class="bg-theme-surface-elevated shadow overflow-hidden sm:rounded-md"
    >
      <div class="px-4 py-5 sm:p-6">
        <div class="space-y-3">
          <div v-for="i in rows" :key="i" class="flex items-center space-x-4">
            <div class="rounded-full bg-theme-surface h-8 w-8" />
            <div class="flex-1 space-y-2">
              <div class="h-4 bg-theme-surface rounded w-1/4" />
              <div class="h-3 bg-theme-surface rounded w-1/3" />
            </div>
            <div class="h-4 bg-theme-surface rounded w-20" />
          </div>
        </div>
      </div>
    </div>

    <!-- List skeleton -->
    <div v-else-if="type === 'list'" class="space-y-3">
      <div v-for="i in rows" :key="i" class="flex items-center space-x-3">
        <div class="rounded-full bg-theme-surface h-10 w-10" />
        <div class="flex-1 space-y-2">
          <div class="h-4 bg-theme-surface rounded w-2/3" />
          <div class="h-3 bg-theme-surface rounded w-1/2" />
        </div>
        <div class="h-4 bg-theme-surface rounded w-16" />
      </div>
    </div>

    <!-- Calendar skeleton -->
    <div v-else-if="type === 'calendar'" class="bg-theme-surface-elevated rounded-lg shadow">
      <div class="p-4 border-b">
        <div class="h-6 bg-theme-surface rounded w-1/3" />
      </div>
      <div class="p-4">
        <div class="grid grid-cols-7 gap-2 mb-4">
          <div v-for="i in 7" :key="i" class="h-8 bg-theme-surface rounded" />
        </div>
        <div class="grid grid-cols-7 gap-2">
          <div v-for="i in 35" :key="i" class="h-20 bg-theme-surface rounded" />
        </div>
      </div>
    </div>

    <!-- Dashboard stats skeleton -->
    <div v-else-if="type === 'stats'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div v-for="i in 4" :key="i" class="bg-theme-surface-elevated rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="rounded-full bg-theme-surface h-12 w-12" />
          <div class="ml-4 flex-1">
            <div class="h-4 bg-theme-surface rounded w-20 mb-2" />
            <div class="h-6 bg-theme-surface rounded w-16" />
          </div>
        </div>
      </div>
    </div>

    <!-- Form skeleton -->
    <div v-else-if="type === 'form'" class="bg-theme-surface-elevated rounded-lg shadow p-6">
      <div class="space-y-6">
        <div v-for="i in fields" :key="i" class="space-y-2">
          <div class="h-4 bg-theme-surface rounded w-1/4" />
          <div class="h-10 bg-theme-surface rounded" />
        </div>
        <div class="flex space-x-4">
          <div class="h-10 bg-theme-surface rounded w-24" />
          <div class="h-10 bg-theme-surface rounded w-24" />
        </div>
      </div>
    </div>

    <!-- Custom skeleton -->
    <div v-else class="space-y-3">
      <div
        v-for="i in rows"
        :key="i"
        class="h-4 bg-theme-surface rounded"
        :style="{ width: widths[i % widths.length] }"
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'SkeletonLoader',
  props: {
    type: {
      type: String,
      default: 'custom',
      validator: value =>
        ['card', 'table', 'list', 'calendar', 'stats', 'form', 'custom'].includes(value)
    },
    rows: {
      type: Number,
      default: 3
    },
    fields: {
      type: Number,
      default: 4
    },
    widths: {
      type: Array,
      default: () => ['100%', '80%', '60%', '90%', '70%']
    }
  }
}
</script>
