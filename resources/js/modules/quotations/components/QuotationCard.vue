<template>
  <div class="quotation-card">
    <div class="card-header">
      <div class="flex justify-between items-start">
        <div>
          <h3 class="quotation-title">{{ quotation.quotation_number }}</h3>
          <p class="quotation-date">{{ formatDate(quotation.quotation_date) }}</p>
        </div>
        <QuotationStatusBadge :status="quotation.status" />
      </div>
    </div>

    <div class="card-body">
      <div class="patient-info">
        <div class="flex items-center space-x-2">
          <UserIcon class="w-4 h-4 text-theme-secondary" />
          <span class="text-sm text-theme-primary">{{ quotation.patient?.first_name }} {{ quotation.patient?.last_name }}</span>
        </div>
      </div>

      <div class="quotation-details">
        <div class="detail-row">
          <span class="detail-label">Items:</span>
          <span class="detail-value">{{ quotation.items?.length || 0 }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Subtotal:</span>
          <span class="detail-value">S/ {{ formatPrice(quotation.subtotal) }}</span>
        </div>
        <div v-if="quotation.discount_amount > 0" class="detail-row">
          <span class="detail-label">Descuento:</span>
          <span class="detail-value text-danger">- S/ {{ formatPrice(quotation.discount_amount) }}</span>
        </div>
        <div v-if="quotation.tax_amount > 0" class="detail-row">
          <span class="detail-label">IGV:</span>
          <span class="detail-value">S/ {{ formatPrice(quotation.tax_amount) }}</span>
        </div>
        <div class="detail-row total">
          <span class="detail-label">Total:</span>
          <span class="detail-value font-semibold text-accent">S/ {{ formatPrice(quotation.total_amount) }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Válido hasta:</span>
          <span class="detail-value" :class="isExpired ? 'text-danger' : 'text-theme-secondary'">
            {{ formatDate(quotation.valid_until) }}
            <span v-if="isExpired" class="text-xs">(Expirado)</span>
          </span>
        </div>
      </div>

      <div v-if="quotation.notes" class="quotation-notes">
        <p class="text-sm text-theme-secondary line-clamp-2">{{ quotation.notes }}</p>
      </div>
    </div>

    <div class="card-footer">
      <div class="flex justify-between items-center">
        <div class="flex space-x-2">
          <button
            @click="$emit('view', quotation)"
            class="btn btn-sm btn-outline"
            title="Ver detalles"
          >
            <EyeIcon class="w-4 h-4" />
          </button>
          <button
            v-if="canEdit"
            @click="$emit('edit', quotation)"
            class="btn btn-sm btn-outline"
            title="Editar"
          >
            <PencilIcon class="w-4 h-4" />
          </button>
          <button
            @click="$emit('download', quotation)"
            class="btn btn-sm btn-outline"
            title="Descargar PDF"
          >
            <ArrowDownTrayIcon class="w-4 h-4" />
          </button>
        </div>

        <div class="flex space-x-2">
          <button
            v-if="canApprove"
            @click="$emit('approve', quotation)"
            class="btn btn-sm btn-success"
            title="Aprobar"
          >
            <CheckIcon class="w-4 h-4" />
          </button>
          <button
            v-if="canReject"
            @click="$emit('reject', quotation)"
            class="btn btn-sm btn-danger"
            title="Rechazar"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <button
            v-if="canDelete"
            @click="confirmDelete"
            class="btn btn-sm btn-danger"
            title="Eliminar"
          >
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import QuotationStatusBadge from './QuotationStatusBadge.vue'
import {
  UserIcon,
  EyeIcon,
  PencilIcon,
  ArrowDownTrayIcon,
  CheckIcon,
  XMarkIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  quotation: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['view', 'edit', 'approve', 'reject', 'download', 'delete'])

// Composables
const { user } = useAuth()

// Computed
const canEdit = computed(() => {
  return quotation.value.status === 'draft' && (
    user.value?.role === 'administrador' ||
    user.value?.role === 'finanzas' ||
    user.value?.role === 'odontologo'
  )
})

const canApprove = computed(() => {
  return quotation.value.status === 'sent' && (
    user.value?.role === 'administrador' ||
    user.value?.role === 'finanzas'
  )
})

const canReject = computed(() => {
  return quotation.value.status === 'sent' && (
    user.value?.role === 'administrador' ||
    user.value?.role === 'finanzas'
  )
})

const canDelete = computed(() => {
  return quotation.value.status === 'draft' && (
    user.value?.role === 'administrador' ||
    user.value?.role === 'finanzas'
  )
})

const isExpired = computed(() => {
  return new Date(quotation.value.valid_until) < new Date()
})

// Métodos
const formatPrice = (price) => {
  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price || 0)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const confirmDelete = () => {
  if (confirm('¿Estás seguro de que quieres eliminar este presupuesto?')) {
    emit('delete', quotation.value.id)
  }
}
</script>

<style scoped>
.quotation-card {
  @apply bg-theme-surface-elevated rounded-lg border border-theme shadow-sm hover-lift transition-shadow;
}

.card-header {
  @apply p-4 border-b border-theme;
}

.quotation-title {
  @apply text-lg font-semibold text-theme-primary mb-1;
}

.quotation-date {
  @apply text-sm text-theme-secondary;
}

.card-body {
  @apply p-4;
}

.patient-info {
  @apply mb-4;
}

.quotation-details {
  @apply space-y-2 mb-4;
}

.detail-row {
  @apply flex justify-between items-center py-1;
}

.detail-row.total {
  @apply border-t border-theme pt-2 font-semibold;
}

.detail-label {
  @apply text-sm text-theme-secondary;
}

.detail-value {
  @apply text-sm text-theme-primary;
}

.quotation-notes {
  @apply mt-4 p-3 bg-theme-surface rounded-lg;
}

.card-footer {
  @apply p-4 border-t border-theme;
}

.btn {
  @apply inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md transition-colors;
}

.btn-sm {
  @apply px-2 py-1 text-xs;
}

.btn-outline {
  @apply border-theme text-theme-primary bg-theme-surface-elevated hover:bg-theme-surface;
}

.btn-success {
  @apply bg-success-badge hover:opacity-80;
}

.btn-danger {
  @apply bg-danger-badge hover:opacity-80;
}
</style>
