<template>
  <UiModal
    :model-value="modelValue"
    :title="title"
    size="sm"
    :persistent="loading"
    :close-on-backdrop="!loading"
    :close-on-escape="!loading"
    role="alertdialog"
    @update:model-value="handleUpdate"
  >
    <div class="confirm-body">
      <div v-if="variant === 'danger'" class="confirm-icon" aria-hidden="true">
        <ExclamationTriangleIcon class="w-6 h-6" />
      </div>
      <p :id="messageId" class="confirm-message">
        {{ message }}
      </p>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-3 w-full">
        <UiButton variant="secondary" size="md" :disabled="loading" @click="handleCancel">
          {{ cancelText }}
        </UiButton>
        <UiButton :variant="confirmVariant" size="md" :loading="loading" @click="handleConfirm">
          {{ confirmText }}
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import UiModal from './Modal.vue'
import UiButton from './Button.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    required: true
  },
  message: {
    type: String,
    required: true
  },
  confirmText: {
    type: String,
    default: 'Confirmar'
  },
  cancelText: {
    type: String,
    default: 'Cancelar'
  },
  variant: {
    type: String,
    default: 'default',
    validator: value => ['default', 'danger'].includes(value)
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const messageId = ref(`confirm-message-${Math.random().toString(36).slice(2, 11)}`)

const confirmVariant = computed(() => (props.variant === 'danger' ? 'danger' : 'primary'))

const handleUpdate = value => {
  if (props.loading) return
  emit('update:modelValue', value)
}

const handleConfirm = () => {
  if (props.loading) return
  emit('confirm')
}

const handleCancel = () => {
  if (props.loading) return
  emit('cancel')
  emit('update:modelValue', false)
}
</script>

<style scoped>
.confirm-body {
  @apply flex items-start gap-4;
}

.confirm-icon {
  @apply flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full;
  background-color: var(--color-danger-light);
  color: var(--color-danger-dark);
}

.confirm-message {
  @apply text-base flex-1;
  color: var(--color-text-primary);
  line-height: 1.5;
}
</style>
