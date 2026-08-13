<template>
  <div class="space-y-4 bg-canvas">
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-1"
      mode="out-in"
    >
      <!-- Loading while creating preference (Apple motion wash) -->
      <div v-if="step === 'creating'" key="creating" class="py-8 text-center">
        <LoadingSpinner
          size="md"
          variant="primary"
          text="Preparando pago con Mercado Pago..."
          aria-label="Preparando pago con Mercado Pago"
        />
        <UiStatusBadge variant="info" size="sm" label="Procesando"
class="mt-3" />
      </div>

      <!-- Error (Apple motion wash) -->
      <div v-else-if="step === 'error'" key="error" class="py-8 text-center">
        <UiStatusBadge
          variant="error"
          :label="errorMessage || 'Error al procesar el pago'"
          class="mb-3"
        />
        <UiButton variant="secondary" size="sm" @click="$emit('close')">
Volver
</UiButton>
      </div>

      <!-- Payment brick container (no transition — brick owns its mount) -->
      <div v-else-if="step === 'ready'" key="ready">
        <div :id="containerId" ref="brickContainer" class="min-h-[300px]" />
        <div class="flex justify-center mt-4">
          <UiButton variant="ghost" size="sm" @click="handleCancel">
Cancelar y volver
</UiButton>
        </div>
      </div>

      <!-- Processing after payment submit (Apple motion wash) -->
      <div v-else-if="step === 'processing'" key="processing" class="py-8 text-center">
        <LoadingSpinner
          size="md"
          variant="primary"
          text="Procesando pago..."
          aria-label="Procesando pago con Mercado Pago"
        />
        <UiStatusBadge variant="info" size="md" label="Procesando"
class="mt-3" />
      </div>

      <!-- Success (Apple motion wash) -->
      <div v-else-if="step === 'success'" key="success" class="py-8 text-center">
        <div
          class="w-16 h-16 mx-auto bg-systemGreen-100 rounded-full flex items-center justify-center mb-4"
        >
          <svg
            class="w-8 h-8 text-systemGreen-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-theme-primary mb-2">Pago registrado</h3>
        <p class="text-sm text-theme-secondary mb-4">
          {{ formatCurrency(amount) }} - {{ description }}
        </p>
        <UiButton @click="handleDone">
Continuar
</UiButton>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useMercadoPago } from '../../../composables/useMercadoPago'
import LoadingSpinner from '../../../components/ui/LoadingSpinner.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiStatusBadge from '../../../components/ui/StatusBadge.vue'
import { formatCurrency } from '../../../composables/useFormatters'

const props = defineProps({
  transactionId: { type: Number, required: true },
  amount: { type: Number, required: true },
  description: { type: String, default: '' },
  publicKey: { type: String, default: '' }
})

const emit = defineEmits(['close', 'success'])

const { createPreference, createBrick, unmount } = useMercadoPago()

const containerId = `mp-brick-${props.transactionId}`
const step = ref('creating')
const errorMessage = ref('')
let brickController = null

const handleCancel = () => {
  unmount(containerId)
  emit('close')
}

const handleDone = () => {
  emit('close')
  emit('success')
}

onMounted(async () => {
  await nextTick()
  try {
    // 1. Crear preferencia en el backend
    step.value = 'creating'
    const preference = await createPreference(props.transactionId)

    // 2. Inicializar brick
    step.value = 'ready'
    await nextTick()

    brickController = await createBrick(
      preference.id,
      props.publicKey || preference.public_key,
      containerId,
      {
        onReady: () => {
          // Brick cargado
        },
        onSubmit: () => {
          step.value = 'processing'
        },
        onError: error => {
          step.value = 'error'
          errorMessage.value = 'Error al procesar el pago con Mercado Pago'
        }
      }
    )
  } catch (error) {
    step.value = 'error'
    errorMessage.value = error.message || 'Error al iniciar pago con Mercado Pago'
  }
})

onUnmounted(() => {
  if (brickController) {
    try {
      brickController.unmount()
    } catch (e) {
      // ignore cleanup errors
    }
  }
  unmount(containerId)
})
</script>
