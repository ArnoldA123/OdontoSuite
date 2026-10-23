import { computed } from 'vue'
import { useAuth } from './useAuth'

/**
 * The SPA never repeats the authorization rule (#280).
 *
 * `App\Authorization\Permissions` is the single source of truth: login and
 * /api/auth/me return the effective permission list for the authenticated
 * role, and every flag below is a membership check on that list. Adding a
 * permission here without adding it to the PHP catalog does nothing.
 */
const PERMISSION_BY_KEY = {
  // Patients
  createPatient: 'create.patient',
  deletePatient: 'delete.patient',
  editPatient: 'update.patient',
  viewPatient: 'view.patient',
  exportPatient: 'export.patient',

  // Appointments and calendar
  createAppointment: 'manage.appointment',
  editAppointment: 'manage.appointment',
  deleteAppointment: 'manage.appointment',
  viewAppointment: 'manage.appointment',
  viewCalendar: 'manage.appointment',
  manageSchedule: 'manage.appointment',
  checkInAppointment: 'check_in.appointment',
  completeConsultation: 'complete.appointment',
  // F1 (#281): the professional's desk and the two custody doors it opens.
  viewMyDay: 'view.my_day',
  startConsultation: 'start.consultation',
  announcePatient: 'announce.patient',

  // F4 (#310) / F5 (#316): the continuity queue and the prosthetic laboratory.
  // Reading each desk is dispatch work; writing it belongs to the desk that
  // answers for it, so the two grants stay separate.
  viewContinuity: 'view.continuity',
  manageContinuity: 'manage.continuity',
  viewLabCase: 'view.lab_case',
  manageLabCase: 'manage.lab_case',

  // Reports and business intelligence
  viewReports: 'view.report',
  exportData: 'export.report',

  // System configuration
  manageUsers: 'manage.user',
  manageAppointmentTypes: 'manage.appointment_type',
  manageEnvironments: 'manage.dental_chair',
  manageBranches: 'manage.branch',
  manageConfig: 'manage.config',
  viewAuditLogs: 'view.audit_log',

  // Procedure catalog
  manageProcedureCatalog: 'manage.procedure_catalog',
  viewProcedureCatalog: 'view.procedure_catalog',

  // Reminders
  manageReminders: 'manage.reminder',

  // Cash register (#55/#191: reception operates the register and collects)
  openCashRegister: 'open.cash_register',
  closeCashRegister: 'close.cash_register',
  viewCashRegister: 'view.cash_register',
  manageCashRegister: 'manage.cash_register',
  collectPendingPayments: 'pay.pending_payment',
  createTransaction: 'create.transaction',
  createMovement: 'manage.cash_movement',
  voidTransaction: 'void.transaction',
  applyDiscount: 'apply.transaction_discount',
  applyLargeDiscount: 'apply.large_transaction_discount',
  viewCashReports: 'view.cash_report',
  exportCashData: 'export.cash_report',

  // Treatment plans
  createTreatmentPlan: 'manage.treatment_plan',
  editTreatmentPlan: 'manage.treatment_plan',
  viewTreatmentPlan: 'manage.treatment_plan',
  deleteTreatmentPlan: 'manage.treatment_plan',
  changeTreatmentPlanStatus: 'manage.treatment_plan',
  duplicateTreatmentPlan: 'manage.treatment_plan',

  // Quotations
  createQuotation: 'manage.quotation',
  editQuotation: 'manage.quotation',
  viewQuotation: 'view.quotation',
  deleteQuotation: 'manage.quotation',
  approveQuotation: 'manage.quotation',
  rejectQuotation: 'manage.quotation',
  downloadQuotationPDF: 'view.quotation',

  // Medical records
  createMedicalRecord: 'manage.medical_record',
  editMedicalRecord: 'manage.medical_record',
  viewMedicalRecord: 'manage.medical_record',
  addEvolution: 'manage.evolution',
  uploadAttachment: 'manage.attachment',
  deleteAttachment: 'manage.attachment',

  // Specialty records
  createSpecialtyRecord: 'manage.specialty_record',
  editSpecialtyRecord: 'manage.specialty_record',
  viewSpecialtyRecord: 'manage.specialty_record',
  deleteSpecialtyRecord: 'manage.specialty_record',

  // AI assistance
  'ai-analysis.analyze': 'create.ai_analysis',
  'ai-analysis.view': 'view.ai_analysis',
  'ai-analysis.review': 'review.ai_analysis',
  'ai-analysis.void': 'void.ai_analysis',
  'ai-analysis.stats': 'view.ai_analysis'
}

export function usePermissions() {
  const { user } = useAuth()

  // Guard against a null user and against a session stored before the
  // permissions payload existed: both resolve to an empty set.
  const granted = computed(() => {
    const permissions = user.value?.permissions
    return new Set(Array.isArray(permissions) ? permissions : [])
  })

  const has = permission => granted.value.has(permission)

  const can = Object.fromEntries(
    Object.entries(PERMISSION_BY_KEY).map(([key, permission]) => [
      key,
      computed(() => has(permission))
    ])
  )

  // Role helpers describe identity, not authorization. UI gates must use
  // `can.*`; these stay for copy that names the role.
  const isAdministrador = computed(() => user.value?.role === 'administrador')
  const isRecepcionista = computed(() => user.value?.role === 'recepcionista')
  const isClinical = computed(() =>
    ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'].includes(user.value?.role)
  )
  const isFinanzas = computed(() => user.value?.role === 'finanzas')

  const hasAnyPermission = permissions => permissions.some(permission => can[permission]?.value)

  const hasAllPermissions = permissions => permissions.every(permission => can[permission]?.value)

  return {
    can,
    isAdministrador,
    isRecepcionista,
    isClinical,
    isFinanzas,
    hasAnyPermission,
    hasAllPermissions
  }
}
