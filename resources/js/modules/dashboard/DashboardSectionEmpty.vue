<template>
  <UiSectionEmpty :title="title" :description="description">
    <template v-if="$slots.icon" #icon>
      <slot name="icon" />
    </template>
    <template v-if="$slots.default" #default>
      <slot />
    </template>
  </UiSectionEmpty>
</template>

<script setup>
import UiSectionEmpty from '@/components/ui/UiSectionEmpty.vue'

// WU3 / D9 - dashboard entry of the shared empty-state pattern. The panel
// anatomy (canvas + hairline, motif box, icon, copy) lives in UiSectionEmpty;
// slots forward only when provided so the primitive keeps its own defaults.
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' }
})
</script>
