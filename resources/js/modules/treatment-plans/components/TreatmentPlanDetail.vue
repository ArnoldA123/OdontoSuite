<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <div>
          <h2 class="modal-title">{{ plan.title }}</h2>
          <p class="modal-subtitle">{{ plan.plan_number }}</p>
        </div>
        <div class="flex items-center space-x-2">
          <PlanStatusBadge :status="plan.status" />
          <button @click="closeModal" class="modal-close">
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>
      </div>

      <div class="modal-body">
        <div class="detail-grid">
          <!-- Información del paciente -->
          <div class="detail-section">
            <h3 class="section-title">Información del Paciente</h3>
            <div class="patient-info">
              <div class="info-row">
                <span class="info-label">Nombre:</span>
                <span class="info-value">{{ plan.patient?.first_name }} {{ plan.patient?.last_name }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ plan.patient?.email || 'No especificado' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Teléfono:</span>
                <span class="info-value">{{ plan.patient?.phone || 'No especificado' }}</span>
              </div>
            </div>
          </div>

          <!-- Detalles del plan -->
          <div class="detail-section">
            <h3 class="section-title">Detalles del Plan</h3>
            <div class="plan-details">
              <div class="info-row">
                <span class="info-label">Descripción:</span>
                <span class="info-value">{{ plan.description || 'Sin descripción' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Duración estimada:</span>
                <span class="info-value">{{ plan.estimated_duration_weeks }} semanas</span>
              </div>
              <div class="info-row">
                <span class="info-label">Fecha de inicio:</span>
                <span class="info-value">{{ formatDate(plan.start_date) }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Fecha de fin:</span>
                <span class="info-value">{{ formatDate(plan.end_date) }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Fases:</span>
                <span class="info-value">{{ plan.phases || 'No especificadas' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Requiere anestesia:</span>
                <span class="info-value">
                  <span :class="plan.requires_anesthesia ? 'text-red-600' : 'text-green-600'">
                    {{ plan.requires_anesthesia ? 'Sí' : 'No' }}
                  </span>
                </span>
              </div>
              <div class="info-row">
                <span class="info-label">Tratamiento urgente:</span>
                <span class="info-value">
                  <span :class="plan.is_urgent ? 'text-red-600' : 'text-green-600'">
                    {{ plan.is_urgent ? 'Sí' : 'No' }}
                  </span>
                </span>
              </div>
            </div>
          </div>

          <!-- Procedimientos -->
          <div class="detail-section">
            <h3 class="section-title">Procedimientos</h3>
            <div v-if="plan.items?.length > 0" class="procedures-table">
              <table class="w-full">
                <thead>
                  <tr class="table-header">
                    <th class="text-left">Descripción</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in plan.items" :key="item.id" class="table-row">
                    <td class="table-cell">
                      <div class="font-medium">{{ item.description }}</div>
                      <div v-if="item.category" class="text-sm text-theme-secondary">{{ item.category }}</div>
                    </td>
                    <td class="table-cell text-center">{{ item.quantity }}</td>
                    <td class="table-cell text-right">S/ {{ formatPrice(item.unit_cost) }}</td>
                    <td class="table-cell text-right font-medium">S/ {{ formatPrice(item.quantity * item.unit_cost) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="table-footer">
                    <td colspan="3" class="table-cell font-semibold">Total:</td>
                    <td class="table-cell text-right font-bold text-primary-600">S/ {{ formatPrice(plan.final_cost) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div v-else class="empty-state">
              <p class="text-theme-secondary">No hay procedimientos en este plan</p>
            </div>
          </div>

          <!-- Notas -->
          <div v-if="plan.notes || plan.patient_notes" class="detail-section">
            <h3 class="section-title">Notas</h3>
            <div class="notes-content">
              <div v-if="plan.notes" class="note-item">
                <h4 class="note-title">Notas internas:</h4>
                <p class="note-text">{{ plan.notes }}</p>
              </div>
              <div v-if="plan.patient_notes" class="note-item">
                <h4 class="note-title">Notas para el paciente:</h4>
                <p class="note-text">{{ plan.patient_notes }}</p>
              </div>
            </div>
          </div>

          <!-- Presupuestos asociados -->
          <div v-if="plan.quotations?.length > 0" class="detail-section">
            <h3 class="section-title">Presupuestos Asociados</h3>
            <div class="quotations-list">
              <div
                v-for="quotation in plan.quotations"
                :key="quotation.id"
                class="quotation-item"
              >
                <div class="quotation-info">
                  <div class="quotation-number">{{ quotation.quotation_number }}</div>
                  <div class="quotation-date">{{ formatDate(quotation.quotation_date) }}</div>
                </div>
                <div class="quotation-amount">S/ {{ formatPrice(quotation.total_amount) }}</div>
                <QuotationStatusBadge :status="quotation.status" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <div class="flex justify-between items-center">
          <div class="text-sm text-theme-secondary">
            Creado el {{ formatDateTime(plan.created_at) }}
            por {{ plan.created_by?.first_name }} {{ plan.created_by?.last_name }}
          </div>
          <div class="flex space-x-3">
            <button @click="$emit('edit', plan)" class="btn btn-outline">
              <PencilIcon class="w-4 h-4 mr-1" />
              Editar
            </button>
            <button @click="$emit('duplicate', plan)" class="btn btn-secondary">
              <DocumentDuplicateIcon class="w-4 h-4 mr-1" />
              Duplicar
            </button>
            <button @click="closeModal" class="btn btn-primary">
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { PencilIcon, DocumentDuplicateIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import PlanStatusBadge from './PlanStatusBadge.vue'
import QuotationStatusBadge from '@/modules/quotations/components/QuotationStatusBadge.vue'

const props = defineProps({
  plan: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'edit', 'duplicate'])

// Métodos
const closeModal = () => {
  emit('close')
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price || 0)
}

const formatDate = (date) => {
  if (!date) return 'No especificada'
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (date) => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.modal-content {
  @apply bg-theme-surface-elevated rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col;
}

.modal-header {
  @apply flex justify-between items-center p-6 border-b border-theme;
}

.modal-title {
  @apply text-xl font-semibold text-theme-primary;
}

.modal-subtitle {
  @apply text-sm text-theme-secondary;
}

.modal-close {
  @apply text-theme-secondary hover:text-theme-primary transition-colors;
}

.modal-body {
  @apply flex-1 overflow-y-auto p-6;
}

.detail-grid {
  @apply space-y-6;
}

.detail-section {
  @apply space-y-4;
}

.section-title {
  @apply text-lg font-medium text-theme-primary border-b border-theme pb-2;
}

.patient-info,
.plan-details {
  @apply space-y-2;
}

.info-row {
  @apply flex justify-between items-center py-1;
}

.info-label {
  @apply text-sm font-medium text-theme-secondary;
}

.info-value {
  @apply text-sm text-theme-primary;
}

.procedures-table {
  @apply overflow-x-auto;
}

.table-header {
  @apply bg-theme-surface border-b border-theme;
}

.table-header th {
  @apply px-4 py-3 text-xs font-medium text-theme-secondary uppercase tracking-wider;
}

.table-row {
  @apply border-b border-theme;
}

.table-row:hover {
  @apply bg-theme-surface;
}

.table-cell {
  @apply px-4 py-3 text-sm;
}

.table-footer {
  @apply bg-theme-surface font-semibold;
}

.empty-state {
  @apply text-center py-8;
}

.notes-content {
  @apply space-y-4;
}

.note-item {
  @apply p-4 bg-theme-surface rounded-lg;
}

.note-title {
  @apply text-sm font-medium text-theme-primary mb-2;
}

.note-text {
  @apply text-sm text-theme-secondary whitespace-pre-wrap;
}

.quotations-list {
  @apply space-y-3;
}

.quotation-item {
  @apply flex items-center justify-between p-3 border border-theme rounded-lg;
}

.quotation-info {
  @apply flex-1;
}

.quotation-number {
  @apply font-medium text-theme-primary;
}

.quotation-date {
  @apply text-sm text-theme-secondary;
}

.quotation-amount {
  @apply font-semibold text-primary-600 mr-3;
}

.modal-footer {
  @apply p-6 border-t border-theme;
}

.btn {
  @apply inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md transition-colors;
}

.btn-outline {
  @apply border-theme text-theme-primary bg-theme-surface-elevated hover:bg-theme-surface;
}

.btn-secondary {
  @apply bg-theme-surface text-theme-primary hover:bg-theme-surface-elevated;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}
</style>
