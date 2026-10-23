<template>
  <div
    role="alert"
    data-accounting-lock-notice
    class="space-y-3 rounded-ios border border-systemYellow-200 bg-systemYellow-50 px-4 py-3 text-sm text-systemYellow-700"
  >
    <p data-accounting-lock-message class="font-medium">{{ message }}</p>

    <template v-if="canOverride">
      <div data-accounting-lock-reason>
        <UiInput
          :model-value="modelValue"
          label="Motivo de la autorización"
          placeholder="Por qué se modifica un registro bloqueado"
          :hint="`Queda registrado en la auditoría (mínimo ${minLength} caracteres).`"
          :error="error"
          :disabled="loading"
          @update:model-value="value => emit('update:modelValue', value)"
        />
        <p data-accounting-lock-counter class="mt-1 text-xs text-theme-secondary">
          {{ length }}/{{ minLength }} caracteres
        </p>
      </div>

      <div class="flex justify-end">
        <UiButton
          type="button"
          variant="primary"
          size="sm"
          data-accounting-lock-retry
          :loading="loading"
          :disabled="loading"
          @click="emit('retry')"
        >
          Autorizar y reintentar
        </UiButton>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import { ACCOUNTING_LOCK_REASON_MIN_LENGTH } from '@/composables/useAccountingLock'
import { usePermissions } from '@/composables/usePermissions'

const props = defineProps({
  message: { type: String, required: true },
  modelValue: { type: String, default: '' },
  error: { type: String, default: '' },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'retry'])

const { can } = usePermissions()

const minLength = ACCOUNTING_LOCK_REASON_MIN_LENGTH

const canOverride = computed(() => can.overrideAccountingLock.value)

const length = computed(() => props.modelValue.trim().length)
</script>
