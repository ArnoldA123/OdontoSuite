<template>
  <div class="space-y-4">
    <!-- Loading while creating preference -->
    <div v-if="step === 'creating'" class="py-8 text-center">
      <LoadingSpinner size="md" />
      <p class="mt-3 text-sm text-theme-secondary">Preparando pago con Mercado Pago...</p>
    </div>

    <!-- Error -->
    <div v-else-if="step === 'error'" class="py-8 text-center">
      <p class="text-sm text-red-600 mb-3">{{ errorMessage }}</p>
      <UiButton variant="secondary" size="sm" @click="$emit('close')">
        Volver
      </UiButton>
    </div>

    <!-- Payment brick container -->
    <div v-else-if="step === 'ready'">
      <div
        :id="containerId"
        ref="brickContainer"
        class="min-h-[300px]"
      ></div>
      <div class="flex justify-center mt-4">
        <UiButton variant="ghost" size="sm" @click="handleCancel">
          Cancelar y volver
        </UiButton>
      </div>
    </div>

    <!-- Processing after payment submit -->
    <div v-else-if="step === 'processing'" class="py-8 text-center">
      <LoadingSpinner size="md" />
      <p class="mt-3 text-sm text-theme-secondary">Procesando pago...</p>
    </div>

    <!-- Success -->
    <div v-else-if="step === 'success'" class="py-8 text-center">
      <div class="w-16 h-16 mx-auto bg-success-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-theme-primary mb-2">Pago registrado</h3>
      <p class="text-sm text-theme-secondary mb-4">
        S/ {{ formatAmount(amount) }} - {{ description }}
      </p>
      <UiButton @click="handleDone">
        Continuar
      </UiButton>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useMercadoPago } from '../../../composables/useMercadoPago'
import LoadingSpinner from '../../../components/ui/LoadingSpinner.vue'
import UiButton from '../../../components/ui/Button.vue'

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

const formatAmount = (val) => {
  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(val || 0)
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
        onError: (error) => {
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
