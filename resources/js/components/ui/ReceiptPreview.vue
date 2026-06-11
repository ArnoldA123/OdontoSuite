<template>
  <div class="receipt-preview">
    <!-- Modal -->
    <Modal
      :show="show"
      :title="'Vista Previa del Comprobante'"
      size="lg"
      @close="$emit('close')"
    >
      <div class="receipt-content">
        <!-- Header -->
        <div class="receipt-header text-center mb-6">
          <h1 class="text-2xl font-bold text-theme-primary">{{ clinic.name }}</h1>
          <p class="text-sm text-theme-secondary">{{ clinic.address }}</p>
          <p class="text-sm text-theme-secondary">Tel: {{ clinic.phone }} | RUC: {{ clinic.ruc }}</p>
        </div>

        <!-- Receipt Info -->
        <div class="receipt-info mb-6">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-theme-secondary">N° Comprobante:</p>
              <p class="font-semibold">{{ receiptData.receipt_number }}</p>
            </div>
            <div>
              <p class="text-sm text-theme-secondary">Fecha:</p>
              <p class="font-semibold">{{ receiptData.date }}</p>
            </div>
          </div>
        </div>

        <!-- Patient Info -->
        <div class="patient-info mb-6">
          <h3 class="text-lg font-semibold mb-2">Datos del Paciente</h3>
          <div class="bg-theme-surface p-4 rounded-lg">
            <p class="font-medium">{{ transaction.patient?.name }} {{ transaction.patient?.last_name }}</p>
            <p class="text-sm text-theme-secondary">{{ transaction.patient?.email }}</p>
            <p class="text-sm text-theme-secondary">{{ transaction.patient?.phone }}</p>
          </div>
        </div>

        <!-- Transaction Details -->
        <div class="transaction-details mb-6">
          <h3 class="text-lg font-semibold mb-2">Detalle de la Transacción</h3>
          <div class="bg-theme-surface-elevated border border-theme rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-theme-surface border-b border-theme">
              <div class="flex justify-between items-center">
                <span class="font-medium">Descripción</span>
                <span class="font-medium">Monto</span>
              </div>
            </div>
            <div class="px-4 py-3 border-b">
              <div class="flex justify-between items-center">
                <span>{{ transaction.description }}</span>
                <span class="font-semibold">{{ formatCurrency(transaction.amount) }}</span>
              </div>
            </div>

            <!-- Subtotal -->
            <div v-if="transaction.subtotal" class="px-4 py-2 bg-theme-surface">
              <div class="flex justify-between">
                <span>Subtotal:</span>
                <span>{{ formatCurrency(transaction.subtotal) }}</span>
              </div>
            </div>

            <!-- Discount -->
            <div v-if="transaction.discount_amount > 0" class="px-4 py-2 bg-theme-surface">
              <div class="flex justify-between">
                <span>Descuento:</span>
                <span class="text-red-600">-{{ formatCurrency(transaction.discount_amount) }}</span>
              </div>
            </div>

            <!-- Tax -->
            <div v-if="transaction.tax_amount > 0" class="px-4 py-2 bg-theme-surface">
              <div class="flex justify-between">
                <span>IGV (18%):</span>
                <span>{{ formatCurrency(transaction.tax_amount) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Info -->
        <div class="payment-info mb-6">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-theme-secondary">Método de Pago:</p>
              <p class="font-semibold">{{ transaction.payment_method?.name || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-theme-secondary">Referencia:</p>
              <p class="font-semibold">{{ transaction.reference_number || 'N/A' }}</p>
            </div>
          </div>
        </div>

        <!-- Total -->
        <div class="total-section mb-6">
          <div class="bg-primary-50 border-2 border-primary-200 rounded-lg p-4">
            <div class="flex justify-between items-center">
              <span class="text-xl font-bold text-primary-900">TOTAL A PAGAR:</span>
              <span class="text-2xl font-bold text-primary-900">{{ formatCurrency(transaction.amount) }}</span>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="transaction.notes" class="notes mb-6">
          <h3 class="text-lg font-semibold mb-2">Notas</h3>
          <div class="bg-theme-surface p-4 rounded-lg">
            <p class="text-sm">{{ transaction.notes }}</p>
          </div>
        </div>

        <!-- QR Code (placeholder) -->
        <div class="qr-section text-center mb-6">
          <div class="inline-block bg-theme-surface p-4 rounded-lg">
            <div class="w-24 h-24 bg-theme-surface-elevated rounded flex items-center justify-center">
              <span class="text-xs text-theme-secondary">QR Code</span>
            </div>
          </div>
          <p class="text-xs text-theme-secondary mt-2">Código QR para verificación</p>
        </div>
      </div>

      <!-- Actions -->
      <template #footer>
        <div class="flex justify-end space-x-3">
          <Button
            variant="secondary"
            @click="$emit('close')"
          >
            Cerrar
          </Button>
          <Button
            variant="primary"
            @click="handlePrint"
            :loading="printing"
          >
            <PrinterIcon class="w-4 h-4 mr-2" />
            Imprimir
          </Button>
          <Button
            variant="primary"
            @click="handleDownload"
            :loading="downloading"
          >
            <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
            Descargar PDF
          </Button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Modal from './Modal.vue'
import Button from './Button.vue'
import { PrinterIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  transaction: {
    type: Object,
    required: true
  },
  clinic: {
    type: Object,
    default: () => ({
      name: 'OdontoSuite',
      address: 'Dirección de la clínica',
      phone: 'Teléfono de la clínica',
      ruc: 'RUC de la clínica'
    })
  }
})

const emit = defineEmits(['close', 'print', 'download'])

const printing = ref(false)
const downloading = ref(false)

// Computed properties
const receiptData = computed(() => ({
  receipt_number: props.transaction.transaction_number || 'N/A',
  date: new Date(props.transaction.created_at).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}))

// Methods
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount || 0)
}

const handlePrint = async () => {
  printing.value = true

  try {
    // Crear ventana de impresión
    const printWindow = window.open('', '_blank')

    // Generar HTML para impresión
    const printContent = generatePrintHTML()

    printWindow.document.write(printContent)
    printWindow.document.close()

    // Esperar a que se cargue el contenido
    await new Promise(resolve => {
      printWindow.onload = resolve
    })

    // Imprimir
    printWindow.print()

    emit('print', props.transaction)
  } catch (error) {
  } finally {
    printing.value = false
  }
}

const handleDownload = async () => {
  downloading.value = true

  try {
    // Aquí se implementaría la descarga del PDF
    // Por ahora solo emitimos el evento
    emit('download', props.transaction)

    // Simular descarga
    await new Promise(resolve => setTimeout(resolve, 1000))
  } catch (error) {
  } finally {
    downloading.value = false
  }
}

const generatePrintHTML = () => {
  return `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Comprobante - ${props.transaction.transaction_number}</title>
      <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 18px; margin: 0; }
        .header p { margin: 2px 0; font-size: 10px; }
        .info { margin-bottom: 15px; }
        .info div { display: inline-block; width: 48%; }
        .patient { background: var(--color-background-secondary); padding: 10px; margin-bottom: 15px; }
        .details { border: 1px solid var(--color-border); }
        .details-header { background: var(--color-surface); padding: 8px; font-weight: bold; }
        .details-row { padding: 8px; border-bottom: 1px solid var(--color-border-light); }
        .total { background: var(--color-primary-light); padding: 10px; font-weight: bold; text-align: center; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; }
        @media print { body { margin: 0; } }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>${props.clinic.name}</h1>
        <p>${props.clinic.address}</p>
        <p>Tel: ${props.clinic.phone} | RUC: ${props.clinic.ruc}</p>
      </div>

      <div class="info">
        <div><strong>N° Comprobante:</strong> ${receiptData.value.receipt_number}</div>
        <div><strong>Fecha:</strong> ${receiptData.value.date}</div>
      </div>

      <div class="patient">
        <strong>Paciente:</strong> ${props.transaction.patient?.name} ${props.transaction.patient?.last_name}<br>
        <strong>Email:</strong> ${props.transaction.patient?.email}<br>
        <strong>Teléfono:</strong> ${props.transaction.patient?.phone}
      </div>

      <div class="details">
        <div class="details-header">
          <span>Descripción</span>
          <span style="float: right;">Monto</span>
        </div>
        <div class="details-row">
          <span>${props.transaction.description}</span>
          <span style="float: right;">${formatCurrency(props.transaction.amount)}</span>
        </div>
      </div>

      <div class="total">
        TOTAL A PAGAR: ${formatCurrency(props.transaction.amount)}
      </div>

      <div class="footer">
        <p>Gracias por su preferencia</p>
        <p>Este comprobante es válido para efectos fiscales</p>
      </div>
    </body>
    </html>
  `
}
</script>

<style scoped>
.receipt-content {
  max-width: 100%;
  margin: 0 auto;
}

.receipt-content h1,
.receipt-content h2,
.receipt-content h3 {
  color: var(--color-text-primary);
}

.receipt-content .bg-theme-surface {
  background-color: var(--color-surface);
}

.receipt-content .bg-primary-50 {
  background-color: var(--color-primary-50);
}

.receipt-content .border-primary-200 {
  border-color: var(--color-primary-200);
}

.receipt-content .text-primary-900 {
  color: var(--color-primary-900);
}
</style>

