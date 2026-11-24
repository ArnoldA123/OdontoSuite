<template>
  <UiModal model-value :title="title" :size="size" @close="emit('close')">
    <div class="space-y-6 bg-canvas" data-catalog-form>
      <div
        v-if="error"
        role="alert"
        data-catalog-form-error
        class="flex items-start gap-3 rounded-[var(--radius-panel)] border border-hairline bg-systemRed-50 p-4"
      >
        <ExclamationTriangleIcon
          class="h-5 w-5 flex-shrink-0 text-systemRed-600"
          aria-hidden="true"
        />
        <p class="text-sm text-theme-primary">{{ error }}</p>
      </div>

      <slot />
    </div>

    <template #footer>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="submitting"
        data-modal-cancel
        data-catalog-form-cancel
        @click="emit('close')"
      >
        Cancelar
      </UiButton>
      <UiButton
        type="button"
        :loading="submitting"
        data-modal-submit
        data-catalog-form-submit
        @click="emit('submit')"
      >
        {{ submitLabel }}
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import UiButton from '../ui/Button.vue'
import UiModal from '../ui/Modal.vue'

defineProps({
  title: { type: String, required: true },
  submitLabel: { type: String, required: true },
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
  size: {
    type: String,
    default: 'lg',
    validator: value => ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', 'full'].includes(value)
  }
})

const emit = defineEmits(['close', 'submit'])
</script>
