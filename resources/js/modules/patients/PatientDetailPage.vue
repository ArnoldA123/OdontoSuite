<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Hero band: the login surface vocabulary + the page's primary edit CTA. -->
      <header
        ref="heroSection"
        data-patient-detail-hero
        data-reveal="hero"
        class="relative overflow-hidden p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
        :style="revealStyle('--spring-patient-detail-hero-o')"
      >
        <DentalMotifLayer variant="hero" data-hero-motif />
        <div class="relative flex flex-wrap items-center justify-between gap-3">
          <Breadcrumbs :items="breadcrumbs" :show-home="false" size="sm" variant="minimal" />
          <div
            class="flex flex-wrap w-full sm:w-auto items-center justify-end gap-2 sm:gap-3"
            data-hero-actions
          >
            <UiButton
              v-if="can.exportPatient?.value"
              variant="secondary"
              size="sm"
              class="flex-none whitespace-nowrap"
              data-export-button
              :disabled="exporting"
              @click="exportPatientFile"
            >
              <template #icon-left>
                <ArrowDownTrayIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Exportar
            </UiButton>
            <UiButton
              v-if="can.managePatientCharge?.value"
              variant="secondary"
              size="sm"
              class="flex-none whitespace-nowrap"
              data-charge-patient-button
              @click="openChargeModal"
            >
              <template #icon-left>
                <BanknotesIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Registrar cargo
            </UiButton>
            <UiButton
              v-if="can.editPatient?.value"
              variant="primary"
              size="sm"
              class="flex-none whitespace-nowrap"
              data-edit-patient-button
              @click="editPatient"
            >
              <template #icon-left>
                <PencilSquareIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Editar
            </UiButton>
          </div>
        </div>
        <p class="page-title relative mt-3 text-4xl font-semibold text-label">
          {{ patient?.first_name }} {{ patient?.last_name }}
        </p>
        <p
          class="relative mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-base text-theme-secondary"
        >
          <span
            v-if="patient?.document_number"
            data-patient-identity="document"
            class="tabular-nums"
          >
            DNI {{ patient.document_number }}
          </span>
          <span v-if="patientAge !== null" data-patient-identity="age" class="tabular-nums">
            {{ patientAge }} años
          </span>
          <UiBadge
            v-if="patient"
            data-patient-identity="status"
            :variant="patient.is_active ? 'success' : 'error'"
          >
            {{ patient.is_active ? 'Activo' : 'Inactivo' }}
          </UiBadge>
        </p>
      </header>

      <!-- Summary block: the contact band that follows the hero. -->
      <section
        ref="summarySection"
        data-patient-detail-block="summary"
        data-reveal="summary"
        class="space-y-4"
        :style="revealStyle('--spring-patient-detail-summary-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-xl font-semibold text-label">Resumen del paciente</h2>
          <p class="text-sm text-theme-secondary">Contacto directo de la ficha</p>
        </div>
        <UiCard variant="glass">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <p class="text-xs font-medium text-theme-secondary uppercase">Email</p>
              <p class="mt-1 text-sm text-label">
                {{ patient?.email || 'No registrado' }}
              </p>
            </div>
            <div>
              <p class="text-xs font-medium text-theme-secondary uppercase">Teléfono</p>
              <p class="mt-1 text-sm text-label tabular-nums">
                {{ patient?.phone || 'No registrado' }}
              </p>
            </div>
            <div>
              <p class="text-xs font-medium text-theme-secondary uppercase">Fecha de Nacimiento</p>
              <p class="mt-1 text-sm text-label tabular-nums">
                {{ formatDate(patient?.birth_date) }}
              </p>
            </div>
          </div>
        </UiCard>
      </section>

      <!-- Work block: the tabbed clinical record (page protagonist). -->
      <section
        ref="workSection"
        data-patient-detail-block="work"
        data-reveal="work"
        class="space-y-4"
        :style="revealStyle('--spring-patient-detail-work-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Expediente clínico</h2>
          <p class="text-sm text-theme-secondary">
            Planes, presupuestos, historias y auditoría del paciente
          </p>
        </div>

        <UiTabs
          v-model="activeTab"
          :tabs="tabs.map(t => ({ id: t.id, label: t.name, icon: t.icon }))"
          class="min-h-[400px]"
        >
          <template #data>
            <!-- Datos del Paciente -->
            <PatientTabPanel
              state="data"
              :loading="patientLoading"
              loading-text="Cargando paciente..."
              :error="patientError"
              error-title="No pudimos cargar el paciente"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              @retry="loadPatient"
            >
              <div class="space-y-6">
                <div>
                  <h3 class="text-lg font-semibold text-theme-primary mb-4">
                    Información Personal
                  </h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">
                        Nombre Completo
                      </p>
                      <p class="text-theme-primary">
                        {{ patient?.first_name }} {{ patient?.last_name }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">Email</p>
                      <p class="text-theme-primary">
                        {{ patient?.email }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">Teléfono</p>
                      <p class="text-theme-primary">
                        {{ patient?.phone }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">
                        Fecha de Nacimiento
                      </p>
                      <p class="text-theme-primary">
                        {{ formatDate(patient?.birth_date) }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">Género</p>
                      <p class="text-theme-primary">
                        {{ genderLabel(patient?.gender) }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">Dirección</p>
                      <p class="text-theme-primary">
                        {{ patient?.address || 'No especificada' }}
                      </p>
                    </div>
                  </div>
                </div>
                <div v-if="patient?.medical_history">
                  <h3 class="text-lg font-semibold text-theme-primary mb-4">Historial Médico</h3>
                  <p class="text-theme-primary">
                    {{ patient.medical_history }}
                  </p>
                </div>
                <div v-if="patient?.notes">
                  <h3 class="text-lg font-semibold text-theme-primary mb-4">Notas</h3>
                  <p class="text-theme-primary">
                    {{ patient.notes }}
                  </p>
                </div>
              </div>
            </PatientTabPanel>
          </template>

          <template #treatment-plans>
            <!-- Planes de Tratamiento -->
            <PatientTabPanel
              state="treatment-plans"
              :loading="treatmentPlansLoading"
              loading-text="Cargando planes..."
              :error="treatmentPlansError"
              error-title="No pudimos cargar los planes"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="treatmentPlans.length === 0"
              empty-title="No hay planes de tratamiento"
              empty-description="Este paciente no tiene planes de tratamiento registrados"
              @retry="loadTreatmentPlans"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">Planes de Tratamiento</h3>
                <UiButton
                  v-if="can.createTreatmentPlan?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createTreatmentPlan"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nuevo Plan
                </UiButton>
              </template>
              <template #empty-action>
                <UiButton
                  v-if="can.createTreatmentPlan?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createTreatmentPlan"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Crear Primer Plan
                </UiButton>
              </template>
              <div class="space-y-4">
                <div
                  v-for="plan in treatmentPlans"
                  :key="plan.id"
                  class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
                >
                  <div class="flex justify-between items-start">
                    <div>
                      <h4 class="font-semibold text-theme-primary">
                        {{ plan.title }}
                      </h4>
                      <p class="text-sm text-theme-secondary mt-1">
                        {{ plan.description }}
                      </p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                        <span>
                          Estado:
                          <UiBadge :variant="getStatusVariant(plan.status)">
                            {{ plan.status }}
                          </UiBadge>
                        </span>
                        <span>Costo: {{ formatCurrency(plan.total_cost) }}</span>
                        <span>Fecha: {{ formatDate(plan.created_at) }}</span>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <UiButton variant="ghost" size="sm" @click="viewTreatmentPlan(plan.id)">
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
            </PatientTabPanel>
          </template>

          <template #quotations>
            <!-- Presupuestos -->
            <PatientTabPanel
              state="quotations"
              :loading="quotationsLoading"
              loading-text="Cargando presupuestos..."
              :error="quotationsError"
              error-title="No pudimos cargar los presupuestos"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="quotations.length === 0"
              empty-title="No hay presupuestos"
              empty-description="Este paciente no tiene presupuestos registrados"
              @retry="loadQuotations"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">Presupuestos</h3>
                <UiButton
                  v-if="can.createQuotation?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createQuotation"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nuevo Presupuesto
                </UiButton>
              </template>
              <template #empty-action>
                <UiButton
                  v-if="can.createQuotation?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createQuotation"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Crear Primer Presupuesto
                </UiButton>
              </template>
              <div class="space-y-4">
                <div
                  v-for="quotation in quotations"
                  :key="quotation.id"
                  class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
                >
                  <div class="flex justify-between items-start">
                    <div>
                      <h4 class="font-semibold text-theme-primary">
                        {{ quotation.quotation_number }}
                      </h4>
                      <p class="text-sm text-theme-secondary mt-1">
                        {{ quotation.notes }}
                      </p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                        <span class="inline-flex items-center gap-2">
                          Estado:
                          <UiStatusBadge
                            :variant="getStatusMeta(quotation.status).variant"
                            :label="getStatusMeta(quotation.status).label"
                            size="sm"
                          />
                        </span>
                        <span>Total: {{ formatCurrency(quotation.total_amount) }}</span>
                        <span>Fecha: {{ formatDate(quotation.quotation_date) }}</span>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <UiButton variant="ghost" size="sm" @click="viewQuotation(quotation.id)">
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
            </PatientTabPanel>
          </template>

          <template #medical-records>
            <!-- Historia Clínica -->
            <PatientTabPanel
              state="medical-records"
              :loading="medicalRecordsLoading"
              loading-text="Cargando historias clínicas..."
              :error="medicalRecordsError"
              error-title="No pudimos cargar las historias clínicas"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="medicalRecords.length === 0"
              empty-title="No hay historias clínicas"
              empty-description="Este paciente no tiene historias clínicas registradas"
              @retry="loadMedicalRecords"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">Historia Clínica</h3>
                <UiButton
                  v-if="can.createMedicalRecord?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createMedicalRecord"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nueva Historia
                </UiButton>
              </template>
              <template #empty-action>
                <UiButton
                  v-if="can.createMedicalRecord?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createMedicalRecord"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Crear Primera Historia
                </UiButton>
              </template>
              <div class="space-y-4">
                <div
                  v-for="record in medicalRecords"
                  :key="record.id"
                  class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
                >
                  <div class="flex justify-between items-start">
                    <div>
                      <h4 class="font-semibold text-theme-primary">
                        {{ record.record_number || `HC-${record.id}` }}
                      </h4>
                      <p class="text-sm text-theme-secondary mt-1">
                        {{ record.chief_complaint }}
                      </p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                        <span>Primera visita: {{ formatDate(record.first_visit_date) }}</span>
                        <span>
                          Estado:
                          <UiBadge :variant="record.is_active ? 'success' : 'error'">
                            {{ record.is_active ? 'Activa' : 'Inactiva' }}
                          </UiBadge>
                        </span>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <UiButton variant="ghost" size="sm" @click="viewMedicalRecord(record.id)">
                        Ver
                      </UiButton>
                    </div>
                  </div>
                </div>
              </div>
            </PatientTabPanel>
          </template>

          <template #specialties>
            <!-- Especialidades -->
            <PatientTabPanel
              state="specialties"
              :loading="specialtyRecordsLoading"
              loading-text="Cargando registros de especialidades..."
              :error="specialtyRecordsError"
              error-title="No pudimos cargar los registros de especialidades"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="specialtyRecords.length === 0"
              empty-title="No hay registros de especialidades"
              empty-description="Este paciente no tiene registros de especialidades"
              @retry="loadSpecialtyRecords"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">
                  Registros de Especialidades
                </h3>
                <UiButton
                  v-if="can.createSpecialtyRecord?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createSpecialtyRecord"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nuevo Registro
                </UiButton>
              </template>
              <template #empty-action>
                <UiButton
                  v-if="can.createSpecialtyRecord?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createSpecialtyRecord"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Crear Primer Registro
                </UiButton>
              </template>
              <div class="space-y-4">
                <div
                  v-for="record in specialtyRecords"
                  :key="record.id"
                  :data-specialty-record-id="record.id"
                  class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
                >
                  <div class="flex justify-between items-start">
                    <div>
                      <h4 class="font-semibold text-theme-primary">
                        {{ getSpecialtyName(record.specialty_type) }}
                      </h4>
                      <p class="text-sm text-theme-secondary mt-1">
                        {{ record.description || 'Sin descripción' }}
                      </p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                        <span>Registro #{{ record.id }}</span>
                        <span>Fecha: {{ formatDate(record.created_at) }}</span>
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
            </PatientTabPanel>
          </template>

          <template #laboratorio>
            <!-- Laboratorio protésico (F5 #316): las órdenes de trabajo del
                 taller son una entidad propia y se leen aquí sin entrar al
                 acto clínico. -->
            <PatientTabPanel
              state="laboratorio"
              data-patient-lab-cases
              :loading="labCasesLoading"
              loading-text="Cargando casos de laboratorio..."
              :error="labCasesError"
              error-title="No pudimos cargar los casos de laboratorio"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="labCases.length === 0"
              empty-title="Sin casos de laboratorio"
              empty-description="Este paciente no tiene órdenes de trabajo del taller."
              @retry="loadLabCases"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">Laboratorio protésico</h3>
                <UiButton
                  v-if="can.manageLabCase?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  data-patient-lab-case-new
                  @click="createLabCase"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nuevo caso
                </UiButton>
              </template>
              <template #empty-action>
                <p data-patient-lab-cases-empty class="text-xs text-theme-secondary">
                  Las órdenes de trabajo del taller aparecerán aquí.
                </p>
              </template>

              <UiDataTable
                :data="labCases"
                :columns="labCaseColumns"
                :searchable="false"
                :pagination="false"
                :show-header="false"
                row-key="id"
                data-patient-lab-cases-table
              >
                <template #cell-work="{ row }">
                  <span class="text-sm font-medium text-theme-primary" data-patient-lab-case-work>
                    {{ row.work }}
                  </span>
                </template>

                <template #cell-dental_piece="{ row }">
                  <span class="text-sm text-theme-primary tabular-nums">
                    {{ pieceLabel(row.dental_piece) }}
                  </span>
                </template>

                <template #cell-promised_at="{ row }">
                  <span class="text-sm text-theme-primary tabular-nums">
                    {{ formatCaseDate(row.promised_at) }}
                  </span>
                </template>

                <template #cell-technician="{ row }">
                  <span class="text-sm text-theme-primary">{{ row.technician?.name || '—' }}</span>
                </template>

                <template #cell-status="{ row }">
                  <UiStatusBadge
                    :variant="phaseBadgeVariant(row.status)"
                    :label="phaseLabel(row.status)"
                    size="sm"
                    data-patient-lab-case-status
                  />
                </template>

                <template #actions="{ row }">
                  <UiButton
                    v-if="can.manageLabCase?.value"
                    variant="secondary"
                    size="sm"
                    data-patient-lab-case-edit
                    @click.stop="editLabCase(row)"
                  >
                    Editar
                  </UiButton>
                </template>
              </UiDataTable>
            </PatientTabPanel>
          </template>

          <template #cuenta>
            <!-- Cuenta del paciente (#320): el libro que el cierre y el
                 mostrador escriben, con el período y sus saldos. La tab sólo
                 existe con `view.patient_statement`; el alta manual de un
                 cargo se abre con `manage.patient_charge`. -->
            <PatientTabPanel state="cuenta">
              <PatientStatementPanel
                v-if="patient"
                ref="statementPanel"
                :patient="patient"
                :can-manage-charge="Boolean(can.managePatientCharge?.value)"
                @charge="openChargeModal"
              />
            </PatientTabPanel>
          </template>

          <template #audit>
            <!-- Historial de Auditoría -->
            <PatientTabPanel
              state="audit"
              :loading="auditLogsLoading"
              loading-text="Cargando historial de auditoría..."
              :error="auditLogsError"
              error-title="No pudimos cargar el historial de auditoría"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="auditLogs.length === 0"
              empty-title="No hay historial de auditoría"
              empty-description="Este paciente no tiene registros de auditoría."
              @retry="loadAuditLogs"
            >
              <div class="space-y-4">
                <div>
                  <h3 class="text-lg font-semibold text-theme-primary mb-4">
                    Historial de Auditoría
                  </h3>
                  <UiCard
                    v-for="log in auditLogs"
                    :key="log.id"
                    variant="glass"
                    class="hover:bg-theme-surface transition-colors"
                  >
                    <div class="flex justify-between items-start">
                      <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                          <UiBadge variant="info">
                            {{ formatAction(log.action) }}
                          </UiBadge>
                          <span class="text-sm text-theme-secondary">
                            por {{ log.user?.name || 'Sistema' }}
                          </span>
                        </div>
                        <p class="text-sm text-theme-secondary mb-2">
                          {{ formatDate(log.created_at) }}
                        </p>
                        <div v-if="log.old_values && log.new_values" class="mt-2 text-sm">
                          <p class="font-medium text-theme-primary mb-1">Cambios realizados:</p>
                          <div
                            v-if="
                              getChangesSummary(log) &&
                              Object.keys(getChangesSummary(log)).length > 0
                            "
                            class="text-theme-secondary space-y-1"
                          >
                            <div
                              v-for="(change, field) in getChangesSummary(log)"
                              :key="field"
                              class="pl-2 border-l-2 border-hairline"
                            >
                              <p class="font-medium text-theme-primary">{{ change.field }}:</p>
                              <p class="text-xs">
                                De:
                                <span class="text-red-500">{{ change.old }}</span>
                              </p>
                              <p class="text-xs">
                                A:
                                <span class="text-green-500">{{ change.new }}</span>
                              </p>
                            </div>
                          </div>
                          <div v-else class="text-theme-secondary italic">
                            Sin cambios registrados
                          </div>
                        </div>
                        <div
                          v-else-if="log.action === 'patient_created'"
                          class="mt-2 text-sm text-theme-secondary"
                        >
                          Paciente creado en el sistema.
                        </div>
                        <div
                          v-else-if="log.action === 'patient_deleted'"
                          class="mt-2 text-sm text-theme-secondary"
                        >
                          Paciente eliminado del sistema.
                        </div>
                      </div>
                    </div>
                  </UiCard>
                </div>
              </div>
            </PatientTabPanel>
          </template>
        </UiTabs>
      </section>
    </div>

    <!-- Laboratorio protésico (F5 #316): alta y edición del caso -->
    <LabCaseFormModal
      v-model="labCaseFormOpen"
      :lab-case="labCaseEditing"
      :patient-id="patient?.id ?? null"
      :patient-name="patientFullNameLabel"
      @saved="handleLabCaseSaved"
    />

    <!-- Cuenta del paciente (#320): el cargo manual del mostrador. -->
    <PatientChargeModal v-model="chargeModalOpen" :patient="patient" @success="handleChargeSaved" />

    <!-- Edit Patient Modal -->
    <PatientFormModal
      v-if="showEditModal"
      mode="edit"
      :patient="patient"
      @close="cancelEdit"
      @saved="handlePatientSaved"
    />
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  AcademicCapIcon,
  ArrowDownTrayIcon,
  BanknotesIcon,
  BeakerIcon,
  ClipboardDocumentListIcon,
  ClockIcon,
  DocumentTextIcon,
  HeartIcon,
  PencilSquareIcon,
  PlusIcon,
  UserIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { useEcho } from '../../composables/useEcho'
import { useAuditLogs } from '../../composables/useAuditLogs'
import { useSpring } from '../../composables/useSpring'
import { prefersReducedMotion } from '../../composables/useSpringMath'
import { formatCurrency, formatDate as formatCanonicalDate } from '../../composables/useFormatters'
import { getStatusMeta } from '../quotations/quotationMeta'
import AppLayout from '../../components/layout/AppLayout.vue'
import Breadcrumbs from '../../components/ui/Breadcrumbs.vue'
import DentalMotifLayer from '../../components/ui/DentalMotifLayer.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiTabs from '../../components/ui/Tabs.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import PatientFormModal from './PatientFormModal.vue'
import PatientTabPanel from './PatientTabPanel.vue'
import PatientStatementPanel from './components/PatientStatementPanel.vue'
import PatientChargeModal from './components/PatientChargeModal.vue'
import LabCaseFormModal from '../lab-cases/LabCaseFormModal.vue'
import {
  formatDate as formatCaseDate,
  phaseBadgeVariant,
  phaseLabel,
  pieceLabel
} from '../lab-cases/labCaseMeta'

export default {
  name: 'PatientDetailPage',
  components: {
    AppLayout,
    ArrowDownTrayIcon,
    BanknotesIcon,
    PencilSquareIcon,
    PlusIcon,
    Breadcrumbs,
    DentalMotifLayer,
    UiCard,
    UiButton,
    UiBadge,
    UiStatusBadge,
    UiTabs,
    UiDataTable,
    PatientFormModal,
    PatientTabPanel,
    PatientStatementPanel,
    PatientChargeModal,
    LabCaseFormModal
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { get, download, normalizeError } = useApi()
    const { can } = usePermissions()
    const toast = useToast()
    const { privateChannel, echo } = useEcho()
    const {
      loading: auditLogsLoading,
      auditLogs,
      getPatientAuditLogs,
      formatAction,
      getChangesSummary
    } = useAuditLogs()

    // State
    const patient = ref(null)
    const patientLoading = ref(true)
    const patientError = ref(false)
    const activeTab = ref('data')
    const treatmentPlans = ref([])
    const quotations = ref([])
    const medicalRecords = ref([])
    const specialtyRecords = ref([])
    const labCases = ref([])
    const treatmentPlansLoading = ref(false)
    const quotationsLoading = ref(false)
    const medicalRecordsLoading = ref(false)
    const specialtyRecordsLoading = ref(false)
    const labCasesLoading = ref(false)
    const treatmentPlansError = ref(false)
    const quotationsError = ref(false)
    const medicalRecordsError = ref(false)
    const auditLogsError = ref(false)
    const specialtyRecordsError = ref(false)
    const labCasesError = ref(false)
    const exporting = ref(false)
    const showEditModal = ref(false)
    const labCaseFormOpen = ref(false)
    const labCaseEditing = ref(null)
    const chargeModalOpen = ref(false)
    const statementPanel = ref(null)

    const breadcrumbs = [{ to: '/patients', label: 'Pacientes' }]

    const patientAge = computed(() => {
      const current = patient.value
      if (!current) return null
      if (typeof current.age === 'number') return current.age
      if (!current.birth_date) return null
      const birth = new Date(current.birth_date)
      if (Number.isNaN(birth.getTime())) return null
      const diff = Date.now() - birth.getTime()
      return Math.max(0, Math.floor(diff / (365.25 * 24 * 60 * 60 * 1000)))
    })

    // F5 (#316): las pestañas clínicas conservan su definición. La del taller y
    // la de auditoría se ordenan aparte porque la primera depende del permiso.
    // #399: cada tab se ofrece sólo al escritorio que puede leer su endpoint;
    // el gate vive en `visibleTabs` y la carga lo respeta, así que una tab
    // oculta no dispara su petición ni abre su canal de difusión.
    const clinicalTabs = [
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
      }
    ]

    const laboratorioTab = {
      id: 'laboratorio',
      name: 'Laboratorio',
      icon: BeakerIcon
    }

    // #320: el libro del paciente es superficie de caja, no de clínica; su tab
    // se ordena junto a la del taller, antes de la auditoría.
    const cuentaTab = {
      id: 'cuenta',
      name: 'Cuenta',
      icon: BanknotesIcon
    }

    const auditTab = {
      id: 'audit',
      name: 'Historial',
      icon: ClockIcon
    }

    const tabs = [...clinicalTabs, auditTab]

    // #313 (decision D1): el registro de especialidad es un acto clínico con
    // narrativa quirúrgica, así que su tab sólo se ofrece al escritorio que lo
    // lee (administración y clínicos). Sin el permiso tampoco se consulta ni se
    // abre el canal de difusión.
    //
    // F5 (#316): la pestaña del taller es trabajo de despacho y sigue la misma
    // regla con `view.lab_case`; se ordena antes de la auditoría.
    //
    // #320 (D1): la cuenta del paciente es superficie de caja y se ordena entre
    // el taller y la auditoría, con `view.patient_statement` como puerta.
    const insertBeforeAudit = (list, tab) => {
      const auditIndex = list.findIndex(item => item.id === 'audit')
      return auditIndex === -1
        ? [...list, tab]
        : [...list.slice(0, auditIndex), tab, ...list.slice(auditIndex)]
    }

    const TAB_GATES = {
      'treatment-plans': () => can.viewTreatmentPlan?.value,
      quotations: () => can.viewQuotation?.value,
      'medical-records': () => can.viewMedicalRecord?.value,
      specialties: () => can.viewSpecialtyRecord?.value,
      audit: () => can.viewAuditLogs?.value
    }

    const visibleTabs = computed(() => {
      let visible = tabs.filter(tab => {
        const gate = TAB_GATES[tab.id]
        return gate ? Boolean(gate()) : true
      })
      if (can.viewLabCase?.value) visible = insertBeforeAudit(visible, laboratorioTab)
      if (can.viewPatientStatement?.value) visible = insertBeforeAudit(visible, cuentaTab)
      return visible
    })

    const labCaseColumns = [
      { key: 'work', label: 'Trabajo' },
      { key: 'dental_piece', label: 'Pieza' },
      { key: 'promised_at', label: 'Prometido' },
      { key: 'technician', label: 'Técnico' },
      { key: 'status', label: 'Fase' }
    ]

    const patientFullNameLabel = computed(() =>
      [patient.value?.first_name, patient.value?.last_name].filter(Boolean).join(' ')
    )

    // Methods
    const loadPatient = async () => {
      patientLoading.value = true
      patientError.value = false
      try {
        const response = await get(`/api/patients/${route.params.id}`)
        patient.value = response.data
      } catch (error) {
        patientError.value = true
        toast.error('Error al cargar el paciente')
      } finally {
        patientLoading.value = false
      }
    }

    const loadTreatmentPlans = async () => {
      if (!patient.value || !can.viewTreatmentPlan?.value) return
      treatmentPlansLoading.value = true
      treatmentPlansError.value = false
      try {
        const response = await get(`/api/treatment-plans?patient_id=${patient.value.id}`)
        treatmentPlans.value = response.data
      } catch (error) {
        treatmentPlansError.value = true
      } finally {
        treatmentPlansLoading.value = false
      }
    }

    const loadQuotations = async () => {
      if (!patient.value || !can.viewQuotation?.value) return
      quotationsLoading.value = true
      quotationsError.value = false
      try {
        const response = await get(`/api/quotations?patient_id=${patient.value.id}`)
        quotations.value = response.data
      } catch (error) {
        quotationsError.value = true
      } finally {
        quotationsLoading.value = false
      }
    }

    const loadMedicalRecords = async () => {
      if (!patient.value || !can.viewMedicalRecord?.value) return
      medicalRecordsLoading.value = true
      medicalRecordsError.value = false
      try {
        const response = await get(`/api/medical-records?patient_id=${patient.value.id}`)
        medicalRecords.value = response.data
      } catch (error) {
        medicalRecordsError.value = true
      } finally {
        medicalRecordsLoading.value = false
      }
    }

    const loadSpecialtyRecords = async () => {
      if (!patient.value || !can.viewSpecialtyRecord?.value) return
      specialtyRecordsLoading.value = true
      specialtyRecordsError.value = false
      try {
        const response = await get(`/api/specialty-records/patient/${patient.value.id}/all`)
        specialtyRecords.value = flattenSpecialtyRecords(response.data)
      } catch (error) {
        specialtyRecordsError.value = true
      } finally {
        specialtyRecordsLoading.value = false
      }
    }

    const loadLabCases = async () => {
      if (!patient.value) return
      labCasesLoading.value = true
      labCasesError.value = false
      try {
        const response = await get(`/api/lab-cases?patient_id=${patient.value.id}&per_page=50`)
        labCases.value = response?.data ?? []
      } catch (error) {
        labCasesError.value = true
      } finally {
        labCasesLoading.value = false
      }
    }

    const createLabCase = () => {
      labCaseEditing.value = null
      labCaseFormOpen.value = true
    }

    const editLabCase = row => {
      labCaseEditing.value = row
      labCaseFormOpen.value = true
    }

    const handleLabCaseSaved = async () => {
      labCaseFormOpen.value = false
      await loadLabCases()
    }

    // #320: el cargo manual se abre desde el hero y desde la tab Cuenta; al
    // aceptarse, el libro se relee sin recargar la ficha.
    const openChargeModal = () => {
      chargeModalOpen.value = true
    }

    const handleChargeSaved = async () => {
      chargeModalOpen.value = false
      toast.success('Cargo registrado exitosamente')
      await statementPanel.value?.reload()
    }

    const loadAuditLogs = async () => {
      if (!patient.value || !can.viewAuditLogs?.value) return
      auditLogsError.value = false
      try {
        await getPatientAuditLogs(patient.value.id, { throwOnError: true })
      } catch (error) {
        // #399: el 403 o el 500 se pintan como error con reintento; un vacío
        // falso haría creer que el paciente no tiene historial.
        auditLogsError.value = true
      }
    }

    const formatDate = date => {
      return formatCanonicalDate(date) || 'No especificada'
    }

    const genderLabel = gender => {
      const labels = {
        male: 'Masculino',
        female: 'Femenino',
        other: 'Otro'
      }
      return labels[gender] || 'No especificado'
    }

    const getStatusVariant = status => {
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

    const getSpecialtyName = specialty => {
      const names = {
        implantologia: 'Implantología',
        ortodoncia: 'Ortodoncia',
        endodoncia: 'Endodoncia',
        rehabilitacion: 'Rehabilitación',
        cirugia_oral: 'Cirugía Oral',
        implantology: 'Implantología',
        orthodontics: 'Ortodoncia',
        endodontics: 'Endodoncia',
        rehabilitation: 'Rehabilitación',
        oral_surgery: 'Cirugía Oral'
      }
      return names[specialty] || specialty || 'Especialidad'
    }

    /**
     * #400: `GET /api/specialty-records/patient/{id}/all` answers one group
     * per specialty, not a list. Flattening it here keeps the five groups from
     * rendering as five ghost cards and gives each row the group it came from.
     */
    const flattenSpecialtyRecords = payload => {
      if (Array.isArray(payload)) return payload
      if (!payload || typeof payload !== 'object') return []

      return Object.entries(payload).flatMap(([specialty, records]) =>
        (Array.isArray(records) ? records : []).map(record => ({
          ...record,
          specialty_type: specialty
        }))
      )
    }

    const getAuditActionVariant = action => {
      const variants = {
        patient_created: 'success',
        patient_updated: 'warning',
        patient_deleted: 'error'
      }
      return variants[action] || 'secondary'
    }

    const editPatient = () => {
      if (!patient.value) return
      showEditModal.value = true
    }

    const cancelEdit = () => {
      showEditModal.value = false
    }

    const handlePatientSaved = async () => {
      await loadPatient()
      toast.success('Paciente actualizado exitosamente')
    }

    const exportPatientFile = async (format = 'pdf') => {
      if (!patient.value) return

      // Ensure format is a valid string
      const exportFormat = format && typeof format === 'string' ? format.toLowerCase() : 'pdf'

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
            Authorization: `Bearer ${token}`,
            Accept: exportFormat === 'pdf' ? 'application/pdf' : 'application/zip'
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

    const viewTreatmentPlan = id => {
      router.push(`/treatment-plans/${id}`)
    }

    const editTreatmentPlan = id => {
      router.push(`/treatment-plans/${id}?edit=true`)
    }

    const createQuotation = () => {
      router.push(`/quotations?patient_id=${patient.value.id}`)
    }

    const viewQuotation = id => {
      router.push({ path: '/quotations', query: { open: String(id) } })
    }

    const downloadQuotationPDF = async id => {
      try {
        const { blob, filename } = await download(`/api/quotations/${id}/pdf`, {
          fallbackFilename: `presupuesto_${id}.pdf`
        })
        const downloadUrl = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = downloadUrl
        link.download = filename
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(downloadUrl)
      } catch (error) {
        toast.error(normalizeError(error, 'No pudimos descargar el presupuesto'))
      }
    }

    const createMedicalRecord = () => {
      router.push(`/medical-records?patient_id=${patient.value.id}`)
    }

    const viewMedicalRecord = id => {
      router.push(`/medical-records/${id}`)
    }

    const createSpecialtyRecord = () => {
      router.push(`/specialty-records?patient_id=${patient.value.id}`)
    }

    const viewSpecialtyRecord = (id, specialty) => {
      router.push(
        `/specialty-records?patient_id=${patient.value.id}&specialty=${specialty}&record_id=${id}`
      )
    }

    const editSpecialtyRecord = (id, specialty) => {
      router.push(
        `/specialty-records?patient_id=${patient.value.id}&specialty=${specialty}&record_id=${id}&edit=true`
      )
    }

    // One-shot staggered entrance (60ms) per section, mirroring the
    // dashboard pattern. revealStyle() renders the FINAL state whenever no
    // spring is attached (reduced motion, or a section mounting later).
    const heroSpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patient-detail-hero-o'
    })
    const summarySpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patient-detail-summary-o'
    })
    const workSpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patient-detail-work-o'
    })

    const heroSection = ref(null)
    const summarySection = ref(null)
    const workSection = ref(null)

    const revealStyle = cssVar => ({
      opacity: `var(${cssVar}, 1)`,
      transform: `translateY(calc((1 - var(${cssVar}, 1)) * 8px))`
    })

    let entrancePlayed = false
    const playEntrance = () => {
      if (entrancePlayed) return
      entrancePlayed = true
      if (prefersReducedMotion()) return

      if (heroSection.value) heroSpring.attach(heroSection.value)
      if (summarySection.value) summarySpring.attach(summarySection.value)
      if (workSection.value) workSpring.attach(workSection.value)

      setTimeout(() => heroSpring.set(1), 0)
      setTimeout(() => summarySpring.set(1), 60)
      setTimeout(() => workSpring.set(1), 120)
    }

    // WebSocket subscriptions
    let patientsChannel = null
    let treatmentPlansChannel = null
    let quotationsChannel = null
    let medicalRecordsChannel = null
    let specialtyRecordsChannel = null

    // Watch for tab changes to load data
    watch(activeTab, newTab => {
      if (newTab === 'audit' && patient.value && can.viewAuditLogs?.value) {
        loadAuditLogs()
      }
      if (newTab === 'laboratorio' && patient.value) {
        loadLabCases()
      }
    })

    // Lifecycle
    onMounted(async () => {
      await loadPatient()
      if (patient.value) {
        await nextTick()
        playEntrance()

        const loads = []
        if (can.viewTreatmentPlan?.value) loads.push(loadTreatmentPlans())
        if (can.viewQuotation?.value) loads.push(loadQuotations())
        if (can.viewMedicalRecord?.value) loads.push(loadMedicalRecords())
        if (can.viewSpecialtyRecord?.value) loads.push(loadSpecialtyRecords())
        await Promise.all(loads)

        // Load audit logs if audit tab is active
        if (activeTab.value === 'audit' && can.viewAuditLogs?.value) {
          loadAuditLogs()
        }

        // Suscribirse a canales WebSocket para actualizaciones en tiempo real
        try {
          const patientId = patient.value.id

          // Canal de pacientes
          patientsChannel = privateChannel('patients')
          if (patientsChannel) {
            patientsChannel.listen('.patient.updated', async e => {
              if (e.patient.id === patientId) {
                patient.value = e.patient
                toast.success('Datos del paciente actualizados')
              }
            })
          }

          // Canal de planes de tratamiento. #399: sin `manage.treatment_plan`
          // el endpoint responde 403, así que el canal tampoco se abre.
          if (can.viewTreatmentPlan?.value)
            treatmentPlansChannel = privateChannel('treatment-plans')
          if (treatmentPlansChannel) {
            treatmentPlansChannel
              .listen('.treatment-plan.created', async e => {
                if (e.treatment_plan.patient_id === patientId) {
                  await loadTreatmentPlans()
                  if (activeTab.value === 'treatment-plans') {
                    toast.success('Nuevo plan de tratamiento creado')
                  }
                }
              })
              .listen('.treatment-plan.updated', async e => {
                if (e.treatment_plan.patient_id === patientId) {
                  const index = treatmentPlans.value.findIndex(p => p.id === e.treatment_plan.id)
                  if (index !== -1) {
                    treatmentPlans.value[index] = e.treatment_plan
                  } else {
                    await loadTreatmentPlans()
                  }
                }
              })
              .listen('.treatment-plan.deleted', async e => {
                if (e.patient_id === patientId) {
                  treatmentPlans.value = treatmentPlans.value.filter(
                    p => p.id !== e.treatment_plan_id
                  )
                }
              })
          }

          // Canal de presupuestos. #399: el escritorio que no lee
          // presupuestos tampoco abre su canal.
          if (can.viewQuotation?.value) quotationsChannel = privateChannel('quotations')
          if (quotationsChannel) {
            quotationsChannel
              .listen('.quotation.created', async e => {
                if (e.quotation.patient_id === patientId) {
                  await loadQuotations()
                  if (activeTab.value === 'quotations') {
                    toast.success('Nuevo presupuesto creado')
                  }
                }
              })
              .listen('.quotation.updated', async e => {
                if (e.quotation.patient_id === patientId) {
                  const index = quotations.value.findIndex(q => q.id === e.quotation.id)
                  if (index !== -1) {
                    quotations.value[index] = e.quotation
                  } else {
                    await loadQuotations()
                  }
                }
              })
              .listen('.quotation.approved', async e => {
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
              .listen('.quotation.rejected', async e => {
                if (e.quotation.patient_id === patientId) {
                  const index = quotations.value.findIndex(q => q.id === e.quotation.id)
                  if (index !== -1) {
                    quotations.value[index] = e.quotation
                  } else {
                    await loadQuotations()
                  }
                  if (activeTab.value === 'quotations') {
                    toast.warning('Presupuesto rechazado')
                  }
                }
              })
          }

          // Canal de historias clínicas. #399: sólo lo lee el escritorio
          // clínico que puede pedir su endpoint.
          if (can.viewMedicalRecord?.value)
            medicalRecordsChannel = privateChannel('medical-records')
          if (medicalRecordsChannel) {
            medicalRecordsChannel
              .listen('.medical-record.created', async e => {
                if (e.medical_record.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nueva historia clínica creada')
                  }
                }
              })
              .listen('.clinical-evolution.created', async e => {
                if (e.evolution.medical_record?.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nueva evolución clínica agregada')
                  }
                }
              })
              .listen('.clinical-attachment.created', async e => {
                if (e.attachment.medical_record?.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nuevo adjunto agregado')
                  }
                }
              })
          }

          // Canal de registros de especialidades. #313: sin `manage.specialty_record`
          // el registro no se lee y el canal no se abre.
          if (can.viewSpecialtyRecord?.value) {
            specialtyRecordsChannel = privateChannel('specialty-records')
            if (specialtyRecordsChannel) {
              specialtyRecordsChannel
                .listen('.specialty-record.created', async e => {
                  if (e.record.patient_id === patientId) {
                    await loadSpecialtyRecords()
                    if (activeTab.value === 'specialties') {
                      toast.success('Nuevo registro de especialidad creado')
                    }
                  }
                })
                .listen('.specialty-record.updated', async e => {
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
          }
        } catch (error) {
          // Sin WebSocket la ficha sigue operativa vía fetch inicial
        }
      }
    })

    onUnmounted(() => {
      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('private-patients')
          echo.leave('private-treatment-plans')
          echo.leave('private-quotations')
          echo.leave('private-medical-records')
          echo.leave('private-specialty-records')
        } catch (e) {
          // Los canales ya estaban cerrados, nada que limpiar
        }
      }
    })

    return {
      patient,
      patientLoading,
      patientError,
      patientAge,
      breadcrumbs,
      activeTab,
      tabs: visibleTabs,
      treatmentPlans,
      quotations,
      medicalRecords,
      specialtyRecords,
      labCases,
      labCaseColumns,
      labCasesLoading,
      labCasesError,
      labCaseFormOpen,
      labCaseEditing,
      patientFullNameLabel,
      loadLabCases,
      createLabCase,
      editLabCase,
      handleLabCaseSaved,
      statementPanel,
      chargeModalOpen,
      openChargeModal,
      handleChargeSaved,
      formatCaseDate,
      phaseBadgeVariant,
      phaseLabel,
      pieceLabel,
      treatmentPlansLoading,
      quotationsLoading,
      medicalRecordsLoading,
      specialtyRecordsLoading,
      treatmentPlansError,
      quotationsError,
      medicalRecordsError,
      specialtyRecordsError,
      exporting,
      showEditModal,
      can,
      loadPatient,
      loadTreatmentPlans,
      loadQuotations,
      loadMedicalRecords,
      loadSpecialtyRecords,
      loadAuditLogs,
      formatDate,
      formatCurrency,
      genderLabel,
      getStatusVariant,
      getStatusMeta,
      getSpecialtyName,
      getAuditActionVariant,
      editPatient,
      cancelEdit,
      handlePatientSaved,
      exportPatientFile,
      auditLogs,
      auditLogsLoading,
      auditLogsError,
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
      createSpecialtyRecord,
      viewSpecialtyRecord,
      editSpecialtyRecord,
      heroSection,
      summarySection,
      workSection,
      revealStyle
    }
  }
}
</script>
