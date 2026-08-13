<template>
  <div class="quotation-detail">
    <div class="detail-header">
      <div class="detail-title">
        <h2 class="text-xl font-semibold text-theme-primary">
          Presupuesto #{{ quotation.quotation_number }}
        </h2>
        <div class="detail-meta">
          <span class="status-badge" :class="getStatusClass(quotation.status)">
            {{ getStatusLabel(quotation.status) }}
          </span>
          <span class="text-sm text-theme-secondary">
            {{ formatDate(quotation.created_at) }}
          </span>
        </div>
      </div>
      <button class="close-button" @click="$emit('close')">
        <XMarkIcon class="w-6 h-6" />
      </button>
    </div>

    <div class="detail-content">
      <!-- Información del paciente -->
      <div class="detail-section">
        <h3 class="section-title">Información del Paciente</h3>
        <div class="patient-info">
          <div class="info-item">
            <span class="info-label">Nombre:</span>
            <span class="info-value">
              {{ quotation.patient?.first_name }} {{ quotation.patient?.last_name }}
            </span>
          </div>
          <div v-if="quotation.patient?.dni" class="info-item">
            <span class="info-label">DNI:</span>
            <span class="info-value">{{ quotation.patient.dni }}</span>
          </div>
          <div v-if="quotation.patient?.phone" class="info-item">
            <span class="info-label">Teléfono:</span>
            <span class="info-value">{{ quotation.patient.phone }}</span>
          </div>
        </div>
      </div>

      <!-- Información del presupuesto -->
      <div class="detail-section">
        <h3 class="section-title">Detalles del Presupuesto</h3>
        <div class="quotation-info">
          <div class="info-item">
            <span class="info-label">Título:</span>
            <span class="info-value">{{ quotation.title }}</span>
          </div>
          <div v-if="quotation.description" class="info-item">
            <span class="info-label">Descripción:</span>
            <span class="info-value">{{ quotation.description }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Válido hasta:</span>
            <span class="info-value">{{ formatDate(quotation.valid_until) }}</span>
          </div>
        </div>
      </div>

      <!-- Items del presupuesto -->
      <div v-if="quotation.items && quotation.items.length > 0" class="detail-section">
        <h3 class="section-title">Procedimientos</h3>
        <div class="items-list">
          <div v-for="item in quotation.items" :key="item.id" class="item-row">
            <div class="item-description">
              <span class="item-name">{{ item.description }}</span>
              <span v-if="item.category" class="item-category">{{ item.category }}</span>
            </div>
            <div class="item-quantity">
              {{ item.quantity }}
            </div>
            <div class="item-price">S/ {{ formatPrice(item.unit_price) }}</div>
            <div class="item-total">S/ {{ formatPrice(item.quantity * item.unit_price) }}</div>
          </div>
        </div>
      </div>

      <!-- Resumen de costos -->
      <div class="detail-section">
        <h3 class="section-title">Resumen de Costos</h3>
        <div class="cost-summary">
          <div class="cost-row">
            <span>Subtotal:</span>
            <span>S/ {{ formatPrice(quotation.subtotal || 0) }}</span>
          </div>
          <div v-if="quotation.discount_amount > 0" class="cost-row">
            <span>Descuento:</span>
            <span>- S/ {{ formatPrice(quotation.discount_amount) }}</span>
          </div>
          <div class="cost-row total">
            <span>Total:</span>
            <span>S/ {{ formatPrice(quotation.total_amount || 0) }}</span>
          </div>
        </div>
      </div>

      <!-- Notas -->
      <div v-if="quotation.notes" class="detail-section">
        <h3 class="section-title">Notas</h3>
        <div class="notes-content">
          {{ quotation.notes }}
        </div>
      </div>
    </div>

    <div class="detail-footer">
      <div class="footer-actions">
        <button v-if="canEdit" class="btn btn-outline" @click="$emit('edit', quotation)">
          <PencilIcon class="w-4 h-4 mr-2" />
          Editar
        </button>
        <button class="btn btn-primary" :disabled="loading" @click="downloadPDF">
          <DocumentArrowDownIcon class="w-4 h-4 mr-2" />
          Descargar PDF
        </button>
        <button
          v-if="canApprove"
          class="btn btn-success"
          :disabled="loading || quotation.status === 'approved'"
          @click="approveQuotation"
        >
          <CheckIcon class="w-4 h-4 mr-2" />
          Aprobar
        </button>
        <button
          v-if="canReject"
          class="btn btn-danger"
          :disabled="loading || quotation.status === 'rejected'"
          @click="rejectQuotation"
        >
          <XMarkIcon class="w-4 h-4 mr-2" />
          Rechazar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useQuotations } from '@/composables/useQuotations'
import { usePermissions } from '@/composables/usePermissions'
import { XMarkIcon, PencilIcon, DocumentArrowDownIcon, CheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  quotation: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'edit'])

const { downloadPDF, approveQuotation, rejectQuotation, loading } = useQuotations()
const { can } = usePermissions()

// Computed
const canEdit = computed(() => {
  return can('quotations.update') && ['draft', 'sent'].includes(props.quotation.status)
})

const canApprove = computed(() => {
  return can('quotations.approve') && props.quotation.status === 'sent'
})

const canReject = computed(() => {
  return can('quotations.reject') && props.quotation.status === 'sent'
})

// Métodos
const formatDate = date => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatPrice = price => {
  return Number(price).toFixed(2)
}

const getStatusClass = status => {
  const classes = {
    draft: 'status-draft',
    sent: 'status-sent',
    approved: 'status-approved',
    rejected: 'status-rejected'
  }
  return classes[status] || 'status-draft'
}

const getStatusLabel = status => {
  const labels = {
    draft: 'Borrador',
    sent: 'Enviado',
    approved: 'Aprobado',
    rejected: 'Rechazado'
  }
  return labels[status] || 'Desconocido'
}

const handleDownloadPDF = async () => {
  try {
    await downloadPDF(props.quotation.id)
  } catch (err) {}
}

const handleApprove = async () => {
  try {
    await approveQuotation(props.quotation.id)
    emit('close')
  } catch (err) {}
}

const handleReject = async () => {
  const reason = prompt('Motivo del rechazo:')
  if (reason) {
    try {
      await rejectQuotation(props.quotation.id, reason)
      emit('close')
    } catch (err) {}
  }
}
</script>

<style scoped>
.quotation-detail {
  @apply bg-theme-surface-elevated rounded-lg shadow-lg max-w-4xl mx-auto;
}

.detail-header {
  @apply flex items-center justify-between p-6 border-b border-theme;
}

.detail-title h2 {
  @apply text-xl font-semibold text-theme-primary;
}

.detail-meta {
  @apply flex items-center space-x-3 mt-2;
}

.status-badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.status-draft {
  @apply bg-theme-surface text-theme-secondary;
}

.status-sent {
  @apply bg-primary-50 text-primary-700;
}

.status-approved {
  @apply bg-success-badge;
}

.status-rejected {
  @apply bg-danger-badge;
}

.close-button {
  @apply p-2 text-theme-secondary hover:text-theme-primary transition-colors;
}

.detail-content {
  @apply p-6 space-y-6;
}

.detail-section {
  @apply space-y-3;
}

.section-title {
  @apply text-lg font-medium text-theme-primary;
}

.patient-info,
.quotation-info {
  @apply space-y-2;
}

.info-item {
  @apply flex justify-between items-center py-1;
}

.info-label {
  @apply text-sm font-medium text-theme-secondary;
}

.info-value {
  @apply text-sm text-theme-primary;
}

.items-list {
  @apply space-y-2;
}

.item-row {
  @apply flex items-center py-2 px-3 bg-theme-surface rounded-lg;
}

.item-description {
  @apply flex-1;
}

.item-name {
  @apply text-sm font-medium text-theme-primary;
}

.item-category {
  @apply text-xs text-theme-secondary ml-2;
}

.item-quantity,
.item-price,
.item-total {
  @apply text-sm text-theme-primary w-20 text-center;
}

.cost-summary {
  @apply space-y-2;
}

.cost-row {
  @apply flex justify-between items-center py-1;
}

.cost-row.total {
  @apply font-semibold text-lg border-t border-theme pt-2;
}

.notes-content {
  @apply text-sm text-theme-primary bg-theme-surface p-3 rounded-lg;
}

.detail-footer {
  @apply p-6 border-t border-theme;
}

.footer-actions {
  @apply flex items-center justify-end space-x-3;
}

.btn {
  @apply inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors;
}

.btn-outline {
  @apply border border-theme text-theme-primary hover:bg-theme-surface;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}

.btn-success {
  @apply bg-success-600 text-white hover:bg-success-700;
}

.btn-danger {
  @apply bg-error-600 text-white hover:bg-error-700;
}
</style>
