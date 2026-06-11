<template>
  <AppLayout>
    <!-- Header Section -->
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">
            {{ patient?.first_name }} {{ patient?.last_name }}
          </h1>
          <p class="text-theme-secondary">
            ID: {{ patient?.id }} | {{ patient?.email }} | {{ patient?.phone }}
          </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
          <UiButton
            variant="secondary"
            @click="goBack"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver
          </UiButton>
          <UiButton
            @click="exportPatientFile"
            :disabled="exporting"
            class="flex items-center gap-2"
            variant="secondary"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            {{ exporting ? 'Exportando...' : 'Exportar Ficha' }}
          </UiButton>
          <UiButton
            v-if="can.editPatient?.value"
            @click="editPatient"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Editar
          </UiButton>
        </div>
      </div>
    </div>

    <!-- Patient Info Card -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex items-center gap-4">
        <div class="h-16 w-16 rounded-xl bg-gradient-accent flex items-center justify-center">
          <span class="text-xl font-semibold text-white">
            {{ patient?.first_name?.charAt(0) }}{{ patient?.last_name?.charAt(0) }}
          </span>
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-theme-primary">
            {{ patient?.first_name }} {{ patient?.last_name }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2 text-sm text-theme-secondary">
            <div>
              <span class="font-medium">Email:</span> {{ patient?.email }}
            </div>
            <div>
              <span class="font-medium">Teléfono:</span> {{ patient?.phone }}
            </div>
            <div>
              <span class="font-medium">Fecha de Nacimiento:</span> {{ formatDate(patient?.birth_date) }}
            </div>
          </div>
        </div>
        <div class="text-right">
          <UiBadge :variant="patient?.is_active ? 'success' : 'error'">
            {{ patient?.is_active ? 'Activo' : 'Inactivo' }}
          </UiBadge>
        </div>
      </div>
    </UiCard>

    <!-- Tabs Navigation -->
    <div class="mb-6">
      <nav class="flex space-x-8 border-b border-theme">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
            activeTab === tab.id
              ? 'border-accent text-accent'
              : 'border-transparent text-theme-secondary hover:text-theme-primary hover:border-theme'
          ]"
        >
          <component :is="tab.icon" class="w-4 h-4 inline mr-2" />
          {{ tab.name }}
        </button>
      </nav>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
      <!-- Datos del Paciente -->
      <div v-if="activeTab === 'data'" class="space-y-6">
        <UiCard variant="glass">
          <h3 class="text-lg font-semibold text-theme-primary mb-4">Información Personal</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Nombre Completo</label>
              <p class="text-theme-primary">{{ patient?.first_name }} {{ patient?.last_name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Email</label>
              <p class="text-theme-primary">{{ patient?.email }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Teléfono</label>
              <p class="text-theme-primary">{{ patient?.phone }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Fecha de Nacimiento</label>
              <p class="text-theme-primary">{{ formatDate(patient?.birth_date) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Género</label>
              <p class="text-theme-primary">{{ patient?.gender }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Dirección</label>
              <p class="text-theme-primary">{{ patient?.address || 'No especificada' }}</p>
            </div>
          </div>
        </UiCard>

        <UiCard variant="glass" v-if="patient?.medical_history">
          <h3 class="text-lg font-semibold text-theme-primary mb-4">Historial Médico</h3>
          <p class="text-theme-primary">{{ patient.medical_history }}</p>
        </UiCard>

        <UiCard variant="glass" v-if="patient?.notes">
          <h3 class="text-lg font-semibold text-theme-primary mb-4">Notas</h3>
          <p class="text-theme-primary">{{ patient.notes }}</p>
        </UiCard>
      </div>

      <!-- Planes de Tratamiento -->
      <div v-if="activeTab === 'treatment-plans'" class="space-y-6">
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-semibold text-theme-primary">Planes de Tratamiento</h3>
          <UiButton
            v-if="can.createTreatmentPlan?.value"
            @click="createTreatmentPlan"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Nuevo Plan
          </UiButton>
        </div>

        <UiCard variant="glass">
          <div v-if="treatmentPlansLoading" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600"></div>
            <p class="mt-2 text-theme-secondary">Cargando planes...</p>
          </div>
          <div v-else-if="treatmentPlans.length === 0" class="p-8 text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-theme-surface to-theme-surface-elevated rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <svg class="w-8 h-8 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-theme-primary mb-2">No hay planes de tratamiento</h3>
            <p class="text-theme-secondary mb-4">Este paciente no tiene planes de tratamiento registrados</p>
            <UiButton
              v-if="can.createTreatmentPlan?.value"
              @click="createTreatmentPlan"
              class="flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Crear Primer Plan
            </UiButton>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="plan in treatmentPlans"
              :key="plan.id"
              class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
            >
              <div class="flex justify-between items-start">
                <div>
                  <h4 class="font-semibold text-theme-primary">{{ plan.title }}</h4>
                  <p class="text-sm text-theme-secondary mt-1">{{ plan.description }}</p>
                  <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                    <span>Estado: <UiBadge :variant="getStatusVariant(plan.status)">{{ plan.status }}</UiBadge></span>
                    <span>Costo: S/ {{ plan.total_cost }}</span>
                    <span>Fecha: {{ formatDate(plan.created_at) }}</span>
                  </div>
                </div>
                <div class="flex gap-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    @click="viewTreatmentPlan(plan.id)"
                  >
                    Ver
                  </UiButton>
                  <UiButton
                    v-if="can.editTreatmentPlan?.value"
                    variant="ghost"
                    size="sm"
                    @click="editTreatmentPlan(plan.id)"
                  >
                    Editar
                  </UiButton>
                </div>
              </div>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Presupuestos -->
      <div v-if="activeTab === 'quotations'" class="space-y-6">
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-semibold text-theme-primary">Presupuestos</h3>
          <UiButton
            v-if="can.createQuotation?.value"
            @click="createQuotation"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Nuevo Presupuesto
          </UiButton>
        </div>

        <UiCard variant="glass">
          <div v-if="quotationsLoading" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600"></div>
            <p class="mt-2 text-theme-secondary">Cargando presupuestos...</p>
          </div>
          <div v-else-if="quotations.length === 0" class="p-8 text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-theme-surface to-theme-surface-elevated rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <svg class="w-8 h-8 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-theme-primary mb-2">No hay presupuestos</h3>
            <p class="text-theme-secondary mb-4">Este paciente no tiene presupuestos registrados</p>
            <UiButton
              v-if="can.createQuotation?.value"
              @click="createQuotation"
              class="flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Crear Primer Presupuesto
            </UiButton>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="quotation in quotations"
              :key="quotation.id"
              class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
            >
              <div class="flex justify-between items-start">
                <div>
                  <h4 class="font-semibold text-theme-primary">{{ quotation.quotation_number }}</h4>
                  <p class="text-sm text-theme-secondary mt-1">{{ quotation.notes }}</p>
                  <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                    <span>Estado: <UiBadge :variant="getQuotationStatusVariant(quotation.status)">{{ quotation.status }}</UiBadge></span>
                    <span>Total: S/ {{ quotation.total_amount }}</span>
                    <span>Fecha: {{ formatDate(quotation.quotation_date) }}</span>
                  </div>
                </div>
                <div class="flex gap-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    @click="viewQuotation(quotation.id)"
                  >
                    Ver
                  </UiButton>
                  <UiButton
                    v-if="can.downloadQuotationPDF?.value"
                    variant="ghost"
                    size="sm"
                    @click="downloadQuotationPDF(quotation.id)"
                  >
                    PDF
                  </UiButton>
                </div>
              </div>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Historia Clínica -->
      <div v-if="activeTab === 'medical-records'" class="space-y-6">
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-semibold text-theme-primary">Historia Clínica</h3>
          <UiButton
            v-if="can.createMedicalRecord?.value"
            @click="createMedicalRecord"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Nueva Historia
          </UiButton>
        </div>

        <UiCard variant="glass">
          <div v-if="medicalRecordsLoading" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600"></div>
            <p class="mt-2 text-theme-secondary">Cargando historias clínicas...</p>
          </div>
          <div v-else-if="medicalRecords.length === 0" class="p-8 text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-theme-surface to-theme-surface-elevated rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <svg class="w-8 h-8 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-theme-primary mb-2">No hay historias clínicas</h3>
            <p class="text-theme-secondary mb-4">Este paciente no tiene historias clínicas registradas</p>
            <UiButton
              v-if="can.createMedicalRecord?.value"
              @click="createMedicalRecord"
              class="flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Crear Primera Historia
            </UiButton>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="record in medicalRecords"
              :key="record.id"
              class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
            >
              <div class="flex justify-between items-start">
                <div>
                  <h4 class="font-semibold text-theme-primary">{{ record.record_number || `HC-${record.id}` }}</h4>
                  <p class="text-sm text-theme-secondary mt-1">{{ record.chief_complaint }}</p>
                  <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                    <span>Primera visita: {{ formatDate(record.first_visit_date) }}</span>
                    <span>Estado: <UiBadge :variant="record.is_active ? 'success' : 'error'">{{ record.is_active ? 'Activa' : 'Inactiva' }}</UiBadge></span>
                  </div>
                </div>
                <div class="flex gap-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    @click="viewMedicalRecord(record.id)"
                  >
                    Ver
                  </UiButton>
                  <UiButton
                    v-if="can.editMedicalRecord?.value"
                    variant="ghost"
                    size="sm"
                    @click="editMedicalRecord(record.id)"
                  >
                    Editar
                  </UiButton>
                </div>
              </div>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Especialidades -->
      <div v-if="activeTab === 'specialties'" class="space-y-6">
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-semibold text-theme-primary">Registros de Especialidades</h3>
          <UiButton
            v-if="can.createSpecialtyRecord?.value"
            @click="createSpecialtyRecord"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Nuevo Registro
          </UiButton>
        </div>

        <UiCard variant="glass">
          <div v-if="specialtyRecordsLoading" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600"></div>
            <p class="mt-2 text-theme-secondary">Cargando registros de especialidades...</p>
          </div>
          <div v-else-if="specialtyRecords.length === 0" class="p-8 text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-theme-surface to-theme-surface-elevated rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <svg class="w-8 h-8 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-theme-primary mb-2">No hay registros de especialidades</h3>
            <p class="text-theme-secondary mb-4">Este paciente no tiene registros de especialidades</p>
            <UiButton
              v-if="can.createSpecialtyRecord?.value"
              @click="createSpecialtyRecord"
              class="flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Crear Primer Registro
            </UiButton>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="record in specialtyRecords"
              :key="record.id"
              class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
            >
              <div class="flex justify-between items-start">
                <div>
                  <h4 class="font-semibold text-theme-primary">{{ getSpecialtyName(record.specialty_type) }}</h4>
                  <p class="text-sm text-theme-secondary mt-1">{{ record.description || 'Sin descripción' }}</p>
                  <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                    <span>Fecha: {{ formatDate(record.created_at) }}</span>
                    <span>Especialidad: {{ getSpecialtyName(record.specialty_type) }}</span>
                  </div>
                </div>
                <div class="flex gap-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    @click="viewSpecialtyRecord(record.id, record.specialty_type)"
                  >
                    Ver
                  </UiButton>
                  <UiButton
                    v-if="can.editSpecialtyRecord?.value"
                    variant="ghost"
                    size="sm"
                    @click="editSpecialtyRecord(record.id, record.specialty_type)"
                  >
                    Editar
                  </UiButton>
                </div>
              </div>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Historial de Auditoría -->
      <div v-if="activeTab === 'audit'" class="space-y-6">
        <UiCard variant="glass">
          <h3 class="text-lg font-semibold text-theme-primary mb-4">Historial de Auditoría</h3>
          <div v-if="auditLogsLoading" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-600"></div>
            <p class="mt-2 text-theme-secondary">Cargando historial de auditoría...</p>
          </div>
          <div v-else-if="auditLogs.length === 0" class="p-8 text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-theme-surface to-theme-surface-elevated rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <svg class="w-8 h-8 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-theme-primary mb-2">No hay historial de auditoría</h3>
            <p class="text-theme-secondary">Este paciente no tiene registros de auditoría.</p>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="log in auditLogs"
              :key="log.id"
              class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
            >
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <UiBadge :variant="getAuditActionVariant(log.action)">
                      {{ formatAction(log.action) }}
                    </UiBadge>
                    <span class="text-sm text-theme-secondary">por {{ log.user?.name || 'Sistema' }}</span>
                  </div>
                  <p class="text-sm text-theme-secondary mb-2">{{ formatDate(log.created_at) }}</p>
                  <div v-if="log.old_values && log.new_values" class="mt-2 text-sm">
                    <p class="font-medium text-theme-primary mb-1">Cambios realizados:</p>
                    <div v-if="getChangesSummary(log) && Object.keys(getChangesSummary(log)).length > 0" class="text-theme-secondary space-y-1">
                      <div v-for="(change, field) in getChangesSummary(log)" :key="field" class="pl-2 border-l-2 border-theme">
                        <p class="font-medium text-theme-primary">{{ change.field }}:</p>
                        <p class="text-xs">De: <span class="text-red-500">{{ change.old }}</span></p>
                        <p class="text-xs">A: <span class="text-green-500">{{ change.new }}</span></p>
                      </div>
                    </div>
                    <div v-else class="text-theme-secondary italic">
                      Sin cambios registrados
                    </div>
                  </div>
                  <div v-else-if="log.action === 'patient_created'" class="mt-2 text-sm text-theme-secondary">
                    Paciente creado en el sistema.
                  </div>
                  <div v-else-if="log.action === 'patient_deleted'" class="mt-2 text-sm text-theme-secondary">
                    Paciente eliminado del sistema.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </UiCard>
      </div>
    </div>

    <!-- Edit Patient Modal -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="cancelEdit"
    >
      <div class="bg-theme-surface-elevated rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-theme">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-theme-primary">Editar Paciente</h2>
            <button
              @click="cancelEdit"
              class="text-theme-secondary hover:text-theme-primary transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="updatePatient" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="editPatientData.first_name"
                label="Nombre"
                placeholder="Ingresa el nombre"
                required
              />
              <UiInput
                v-model="editPatientData.last_name"
                label="Apellido"
                placeholder="Ingresa el apellido"
                required
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="editPatientData.document_number"
                label="DNI"
                placeholder="Ingresa el DNI"
              />
              <UiInput
                v-model="editPatientData.email"
                label="Email"
                type="email"
                placeholder="correo@ejemplo.com"
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="editPatientData.phone"
                label="Teléfono"
                placeholder="+51 999 999 999"
              />
              <UiInput
                v-model="editPatientData.birth_date"
                label="Fecha de Nacimiento"
                type="date"
              />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-2">
                  Género
                </label>
                <select
                  v-model="editPatientData.gender"
                  class="w-full px-4 py-3 border border-theme rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200 bg-theme-surface-elevated text-theme-primary"
                >
                  <option value="">Seleccionar</option>
                  <option value="male">Masculino</option>
                  <option value="female">Femenino</option>
                  <option value="other">Otro</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-2">
                  Estado
                </label>
                <select
                  v-model="editPatientData.is_active"
                  class="w-full px-4 py-3 border border-theme rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200 bg-theme-surface-elevated text-theme-primary"
                >
                  <option :value="true">Activo</option>
                  <option :value="false">Inactivo</option>
                </select>
              </div>
            </div>
            <UiInput
              v-model="editPatientData.address"
              label="Dirección"
              placeholder="Ingresa la dirección"
            />
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="editPatientData.emergency_contact_name"
                label="Contacto de Emergencia"
                placeholder="Nombre del contacto"
              />
              <UiInput
                v-model="editPatientData.emergency_contact_phone"
                label="Teléfono de Emergencia"
                placeholder="+51 999 999 999"
              />
            </div>
            <UiInput
              v-model="editPatientData.medical_history"
              label="Historial Médico"
              placeholder="Alergias, condiciones médicas, etc."
              type="textarea"
            />
            <UiInput
              v-model="editPatientData.allergies"
              label="Alergias"
              placeholder="Lista de alergias conocidas"
              type="textarea"
            />
            <UiInput
              v-model="editPatientData.notes"
              label="Notas"
              placeholder="Notas adicionales"
              type="textarea"
            />
            <div class="flex justify-end gap-3 pt-4">
              <UiButton
                type="button"
                variant="secondary"
                @click="cancelEdit"
              >
                Cancelar
              </UiButton>
              <UiButton
                type="submit"
                :loading="updating"
              >
                Actualizar Paciente
              </UiButton>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { useEcho } from '../../composables/useEcho'
import { useAuditLogs } from '../../composables/useAuditLogs'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import UiBadge from '../../components/ui/Badge.vue'

export default {
  name: 'PatientDetailPage',
  components: {
    AppLayout,
    UiCard,
    UiButton,
    UiBadge
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { get } = useApi()
    const { can } = usePermissions()
    const toast = useToast()
    const { channel, echo } = useEcho()
    const {
      loading: auditLogsLoading,
      auditLogs,
      getPatientAuditLogs,
      formatAction,
      getChangesSummary
    } = useAuditLogs()

    // State
    const patient = ref(null)
    const activeTab = ref('data')
    const treatmentPlans = ref([])
    const quotations = ref([])
    const medicalRecords = ref([])
    const specialtyRecords = ref([])
    const treatmentPlansLoading = ref(false)
    const quotationsLoading = ref(false)
    const medicalRecordsLoading = ref(false)
    const specialtyRecordsLoading = ref(false)
    const exporting = ref(false)
    const editing = ref(false)
    const updating = ref(false)
    const showEditModal = ref(false)

    const editPatientData = ref({
      first_name: '',
      last_name: '',
      document_number: '',
      email: '',
      phone: '',
      birth_date: '',
      gender: '',
      address: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      medical_history: '',
      allergies: '',
      notes: '',
      is_active: true
    })

    // Icon components
    const UserIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
      `
    }

    const ClipboardDocumentListIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 3 3 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.933.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
        </svg>
      `
    }

    const ClockIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      `
    }

    const DocumentTextIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      `
    }

    const HeartIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
      `
    }

    const AcademicCapIcon = {
      template: `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
        </svg>
      `
    }

    // Tabs configuration (must be after all icon definitions)
    const tabs = [
      {
        id: 'data',
        name: 'Datos',
        icon: UserIcon
      },
      {
        id: 'treatment-plans',
        name: 'Planes',
        icon: ClipboardDocumentListIcon
      },
      {
        id: 'quotations',
        name: 'Presupuestos',
        icon: DocumentTextIcon
      },
      {
        id: 'medical-records',
        name: 'Historia Clínica',
        icon: HeartIcon
      },
      {
        id: 'specialties',
        name: 'Especialidades',
        icon: AcademicCapIcon
      },
      {
        id: 'audit',
        name: 'Historial',
        icon: ClockIcon
      }
    ]

    // Methods
    const loadPatient = async () => {
      try {
        const response = await get(`/api/patients/${route.params.id}`)
        patient.value = response.data
      } catch (error) {
        toast.error('Error al cargar el paciente')
      }
    }

    const loadTreatmentPlans = async () => {
      if (!patient.value) return
      treatmentPlansLoading.value = true
      try {
        const response = await get(`/api/treatment-plans?patient_id=${patient.value.id}`)
        treatmentPlans.value = response.data
      } catch (error) {
      } finally {
        treatmentPlansLoading.value = false
      }
    }

    const loadQuotations = async () => {
      if (!patient.value) return
      quotationsLoading.value = true
      try {
        const response = await get(`/api/quotations?patient_id=${patient.value.id}`)
        quotations.value = response.data
      } catch (error) {
      } finally {
        quotationsLoading.value = false
      }
    }

    const loadMedicalRecords = async () => {
      if (!patient.value) return
      medicalRecordsLoading.value = true
      try {
        const response = await get(`/api/medical-records?patient_id=${patient.value.id}`)
        medicalRecords.value = response.data
      } catch (error) {
      } finally {
        medicalRecordsLoading.value = false
      }
    }

    const loadSpecialtyRecords = async () => {
      if (!patient.value) return
      specialtyRecordsLoading.value = true
      try {
        const response = await get(`/api/specialty-records/patient/${patient.value.id}/all`)
        specialtyRecords.value = response.data
      } catch (error) {
      } finally {
        specialtyRecordsLoading.value = false
      }
    }

    const loadAuditLogs = async () => {
      if (!patient.value) return
      await getPatientAuditLogs(patient.value.id)
    }

    const formatDate = (date) => {
      if (!date) return 'No especificada'
      return new Date(date).toLocaleDateString('es-ES')
    }

    const getStatusVariant = (status) => {
      const variants = {
        draft: 'secondary',
        pending: 'warning',
        approved: 'success',
        in_progress: 'primary',
        completed: 'success',
        cancelled: 'error'
      }
      return variants[status] || 'secondary'
    }

    const getQuotationStatusVariant = (status) => {
      const variants = {
        pending: 'warning',
        approved: 'success',
        rejected: 'error',
        expired: 'secondary'
      }
      return variants[status] || 'secondary'
    }

    const getSpecialtyName = (specialty) => {
      const names = {
        implantology: 'Implantología',
        orthodontics: 'Ortodoncia',
        endodontics: 'Endodoncia',
        rehabilitation: 'Rehabilitación',
        oral_surgery: 'Cirugía Oral'
      }
      return names[specialty] || specialty
    }

    const getAuditActionVariant = (action) => {
      const variants = {
        'patient_created': 'success',
        'patient_updated': 'warning',
        'patient_deleted': 'error'
      }
      return variants[action] || 'secondary'
    }

    // Navigation methods
    const goBack = () => {
      router.push('/patients')
    }

    const editPatient = () => {
      if (!patient.value) return

      editing.value = true
      editPatientData.value = {
        first_name: patient.value.first_name || '',
        last_name: patient.value.last_name || '',
        document_number: patient.value.document_number || '',
        email: patient.value.email || '',
        phone: patient.value.phone || '',
        birth_date: patient.value.birth_date ? patient.value.birth_date.split('T')[0] : '',
        gender: patient.value.gender || '',
        address: patient.value.address || '',
        emergency_contact_name: patient.value.emergency_contact_name || '',
        emergency_contact_phone: patient.value.emergency_contact_phone || '',
        medical_history: patient.value.medical_history || '',
        allergies: patient.value.allergies || '',
        notes: patient.value.notes || '',
        is_active: patient.value.is_active !== undefined ? patient.value.is_active : true
      }
      showEditModal.value = true
    }

    const updatePatient = async () => {
      if (!patient.value) return

      updating.value = true
      try {
        const { put } = useApi()
        await put(`/api/patients/${patient.value.id}`, editPatientData.value)
        await loadPatient()
        showEditModal.value = false
        editing.value = false

        toast.success('Paciente actualizado exitosamente')
      } catch (error) {
        const errorMsg = error.response?.data?.message || 'Error al actualizar paciente'
        const errors = error.response?.data?.errors
        let details = ''
        if (errors) {
          details = '\n' + Object.values(errors).flat().join('\n')
        }
        toast.error(errorMsg + details)
      } finally {
        updating.value = false
      }
    }

    const cancelEdit = () => {
      showEditModal.value = false
      editing.value = false
      editPatientData.value = {
        first_name: '',
        last_name: '',
        document_number: '',
        email: '',
        phone: '',
        birth_date: '',
        gender: '',
        address: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        medical_history: '',
        allergies: '',
        notes: '',
        is_active: true
      }
    }

    const exportPatientFile = async (format = 'pdf') => {
      if (!patient.value) return

      // Ensure format is a valid string
      const exportFormat = (format && typeof format === 'string') ? format.toLowerCase() : 'pdf'

      if (exportFormat !== 'pdf' && exportFormat !== 'zip') {
        toast.error('Formato de exportación no válido. Use PDF o ZIP.')
        return
      }

      try {
        exporting.value = true
        const token = localStorage.getItem('auth_token')
        // Use the same base URL as useApi
        const baseUrl = import.meta.env.VITE_APP_URL || window.location.origin
        const url = `${baseUrl}/api/patients/${patient.value.id}/export?format=${exportFormat}`

        const response = await fetch(url, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': exportFormat === 'pdf' ? 'application/pdf' : 'application/zip'
          }
        })

        if (!response.ok) {
          const errorText = await response.text().catch(() => 'Error desconocido')
          throw new Error(errorText || 'Error al exportar ficha del paciente')
        }

        const blob = await response.blob()
        const downloadUrl = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = downloadUrl
        link.download = `ficha_paciente_${patient.value.id}_${new Date().toISOString().split('T')[0]}.${exportFormat}`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(downloadUrl)

        toast.success(`Ficha exportada exitosamente como ${exportFormat.toUpperCase()}`)
      } catch (error) {
        toast.error('Error al exportar ficha del paciente')
      } finally {
        exporting.value = false
      }
    }

    const createTreatmentPlan = () => {
      router.push(`/treatment-plans?patient_id=${patient.value.id}`)
    }

    const viewTreatmentPlan = (id) => {
      router.push(`/treatment-plans/${id}`)
    }

    const editTreatmentPlan = (id) => {
      router.push(`/treatment-plans/${id}?edit=true`)
    }

    const createQuotation = () => {
      router.push(`/quotations?patient_id=${patient.value.id}`)
    }

    const viewQuotation = (id) => {
      router.push(`/quotations/${id}`)
    }

    const downloadQuotationPDF = (id) => {
      window.open(`/api/quotations/${id}/pdf`, '_blank')
    }

    const createMedicalRecord = () => {
      router.push(`/medical-records?patient_id=${patient.value.id}`)
    }

    const viewMedicalRecord = (id) => {
      router.push(`/medical-records/${id}`)
    }

    const editMedicalRecord = (id) => {
      router.push(`/medical-records/${id}?edit=true`)
    }

    const createSpecialtyRecord = () => {
      router.push(`/specialty-records?patient_id=${patient.value.id}`)
    }

    const viewSpecialtyRecord = (id, specialty) => {
      router.push(`/specialty-records/${id}?specialty=${specialty}`)
    }

    const editSpecialtyRecord = (id, specialty) => {
      router.push(`/specialty-records/${id}?edit=true&specialty=${specialty}`)
    }

    // WebSocket subscriptions
    let patientsChannel = null
    let treatmentPlansChannel = null
    let quotationsChannel = null
    let medicalRecordsChannel = null
    let specialtyRecordsChannel = null

    // Watch for tab changes to load data
    watch(activeTab, (newTab) => {
      if (newTab === 'audit' && patient.value) {
        loadAuditLogs()
      }
    })

    // Lifecycle
    onMounted(async () => {
      await loadPatient()
      if (patient.value) {
        await Promise.all([
          loadTreatmentPlans(),
          loadQuotations(),
          loadMedicalRecords(),
          loadSpecialtyRecords()
        ])

        // Load audit logs if audit tab is active
        if (activeTab.value === 'audit') {
          loadAuditLogs()
        }

        // Suscribirse a canales WebSocket para actualizaciones en tiempo real
        try {
          const patientId = patient.value.id

          // Canal de pacientes
          patientsChannel = channel('patients')
          if (patientsChannel) {
            patientsChannel
              .listen('.patient.updated', async (e) => {
                if (e.patient.id === patientId) {
                  patient.value = e.patient
                  toast.success('Datos del paciente actualizados')
                }
              })
          }

          // Canal de planes de tratamiento
          treatmentPlansChannel = channel('treatment-plans')
          if (treatmentPlansChannel) {
            treatmentPlansChannel
              .listen('.treatment-plan.created', async (e) => {
                if (e.treatment_plan.patient_id === patientId) {
                  await loadTreatmentPlans()
                  if (activeTab.value === 'treatment-plans') {
                    toast.success('Nuevo plan de tratamiento creado')
                  }
                }
              })
              .listen('.treatment-plan.updated', async (e) => {
                if (e.treatment_plan.patient_id === patientId) {
                  const index = treatmentPlans.value.findIndex(p => p.id === e.treatment_plan.id)
                  if (index !== -1) {
                    treatmentPlans.value[index] = e.treatment_plan
                  } else {
                    await loadTreatmentPlans()
                  }
                }
              })
              .listen('.treatment-plan.deleted', async (e) => {
                if (e.patient_id === patientId) {
                  treatmentPlans.value = treatmentPlans.value.filter(p => p.id !== e.treatment_plan_id)
                }
              })
          }

          // Canal de presupuestos
          quotationsChannel = channel('quotations')
          if (quotationsChannel) {
            quotationsChannel
              .listen('.quotation.created', async (e) => {
                if (e.quotation.patient_id === patientId) {
                  await loadQuotations()
                  if (activeTab.value === 'quotations') {
                    toast.success('Nuevo presupuesto creado')
                  }
                }
              })
              .listen('.quotation.updated', async (e) => {
                if (e.quotation.patient_id === patientId) {
                  const index = quotations.value.findIndex(q => q.id === e.quotation.id)
                  if (index !== -1) {
                    quotations.value[index] = e.quotation
                  } else {
                    await loadQuotations()
                  }
                }
              })
              .listen('.quotation.approved', async (e) => {
                if (e.quotation.patient_id === patientId) {
                  const index = quotations.value.findIndex(q => q.id === e.quotation.id)
                  if (index !== -1) {
                    quotations.value[index] = e.quotation
                  } else {
                    await loadQuotations()
                  }
                  if (activeTab.value === 'quotations') {
                    toast.success('Presupuesto aprobado')
                  }
                }
              })
          }

          // Canal de historias clínicas
          medicalRecordsChannel = channel('medical-records')
          if (medicalRecordsChannel) {
            medicalRecordsChannel
              .listen('.medical-record.created', async (e) => {
                if (e.medical_record.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nueva historia clínica creada')
                  }
                }
              })
              .listen('.medical-record.updated', async (e) => {
                if (e.medical_record.patient_id === patientId) {
                  const index = medicalRecords.value.findIndex(r => r.id === e.medical_record.id)
                  if (index !== -1) {
                    medicalRecords.value[index] = e.medical_record
                  } else {
                    await loadMedicalRecords()
                  }
                }
              })
              .listen('.clinical-evolution.created', async (e) => {
                if (e.evolution.medical_record?.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nueva evolución clínica agregada')
                  }
                }
              })
              .listen('.clinical-attachment.created', async (e) => {
                if (e.attachment.medical_record?.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nuevo adjunto agregado')
                  }
                }
              })
          }

          // Canal de registros de especialidades
          specialtyRecordsChannel = channel('specialty-records')
          if (specialtyRecordsChannel) {
            specialtyRecordsChannel
              .listen('.specialty-record.created', async (e) => {
                if (e.record.patient_id === patientId) {
                  await loadSpecialtyRecords()
                  if (activeTab.value === 'specialties') {
                    toast.success('Nuevo registro de especialidad creado')
                  }
                }
              })
              .listen('.specialty-record.updated', async (e) => {
                if (e.record.patient_id === patientId) {
                  const index = specialtyRecords.value.findIndex(r => r.id === e.record.id)
                  if (index !== -1) {
                    specialtyRecords.value[index] = e.record
                  } else {
                    await loadSpecialtyRecords()
                  }
                }
              })
          }
        } catch (error) {
        }
      }
    })

    onUnmounted(() => {
      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('patients')
          echo.leave('treatment-plans')
          echo.leave('quotations')
          echo.leave('medical-records')
          echo.leave('specialty-records')
        } catch (e) {
        }
      }
    })

    return {
      patient,
      activeTab,
      tabs,
      treatmentPlans,
      quotations,
      medicalRecords,
      specialtyRecords,
      treatmentPlansLoading,
      quotationsLoading,
      medicalRecordsLoading,
      specialtyRecordsLoading,
      exporting,
      editing,
      updating,
      showEditModal,
      editPatientData,
      can,
      formatDate,
      getStatusVariant,
      getQuotationStatusVariant,
      getSpecialtyName,
      getAuditActionVariant,
      goBack,
      editPatient,
      updatePatient,
      cancelEdit,
      exportPatientFile,
      auditLogs,
      auditLogsLoading,
      formatAction,
      getChangesSummary,
      createTreatmentPlan,
      viewTreatmentPlan,
      editTreatmentPlan,
      createQuotation,
      viewQuotation,
      downloadQuotationPDF,
      createMedicalRecord,
      viewMedicalRecord,
      editMedicalRecord,
      createSpecialtyRecord,
      viewSpecialtyRecord,
      editSpecialtyRecord
    }
  }
}
</script>

<style scoped>
.tab-content {
  min-height: 400px;
}
</style>
