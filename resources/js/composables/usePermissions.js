import { computed } from 'vue'
import { useAuth } from './useApi'

export function usePermissions() {
  const { user } = useAuth()

  // Verificación de seguridad para evitar errores cuando user es null
  const safeUser = computed(() => user.value || {})

  const can = {
    // Gestión de pacientes
    createPatient: computed(() => ['administrador', 'recepcionista'].includes(safeUser.value?.role)),
    deletePatient: computed(() => safeUser.value?.role === 'administrador'),
    editPatient: computed(() => [
      'administrador',
      'recepcionista',
      'odontologo',
      'implantologo',
      'tecnico_dental',
      'asistente'
    ].includes(safeUser.value?.role)),
    viewPatient: computed(() => true), // Todos pueden ver pacientes

    // Gestión de citas
    createAppointment: computed(() => !['finanzas'].includes(safeUser.value?.role)),
    editAppointment: computed(() => !['finanzas'].includes(safeUser.value?.role)),
    deleteAppointment: computed(() => ['administrador', 'recepcionista'].includes(safeUser.value?.role)),
    viewAppointment: computed(() => !['finanzas'].includes(safeUser.value?.role)),

    // Reportes y Business Intelligence
    viewReports: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),
    exportData: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),

    // Configuración del sistema
    manageUsers: computed(() => safeUser.value?.role === 'administrador'),
    manageAppointmentTypes: computed(() => safeUser.value?.role === 'administrador'),
    manageEnvironments: computed(() => safeUser.value?.role === 'administrador'),
    manageConfig: computed(() => safeUser.value?.role === 'administrador'),
    viewAuditLogs: computed(() => safeUser.value?.role === 'administrador'),

    // Calendario y agenda
    viewCalendar: computed(() => !['finanzas'].includes(safeUser.value?.role)),
    manageSchedule: computed(() => !['finanzas'].includes(safeUser.value?.role)),

    // Recordatorios
    manageReminders: computed(() => !['finanzas'].includes(safeUser.value?.role)),

    // Lista de espera
    manageWaitingList: computed(() => !['finanzas'].includes(safeUser.value?.role)),

    // Gestión de caja
    openCashRegister: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),
    closeCashRegister: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),
    viewCashRegister: computed(() => ['administrador', 'finanzas', 'recepcionista'].includes(safeUser.value?.role)),
    manageCashRegister: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),
    createTransaction: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),
    voidTransaction: computed(() => safeUser.value?.role === 'administrador'),
    applyDiscount: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),
    applyLargeDiscount: computed(() => safeUser.value?.role === 'administrador'),
    viewCashReports: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),
    exportCashData: computed(() => ['administrador', 'finanzas'].includes(safeUser.value?.role)),

    // Planes de tratamiento
    createTreatmentPlan: computed(() => isClinical.value || isAdministrador.value),
    editTreatmentPlan: computed(() => isClinical.value || isAdministrador.value),
    viewTreatmentPlan: computed(() => isClinical.value || isAdministrador.value),
    deleteTreatmentPlan: computed(() => isClinical.value || isAdministrador.value),
    changeTreatmentPlanStatus: computed(() => isClinical.value || isAdministrador.value),
    duplicateTreatmentPlan: computed(() => isClinical.value || isAdministrador.value),

    // Presupuestos
    createQuotation: computed(() => isClinical.value || isFinanzas.value || isAdministrador.value),
    editQuotation: computed(() => isClinical.value || isFinanzas.value || isAdministrador.value),
    viewQuotation: computed(() => isClinical.value || isFinanzas.value || isAdministrador.value),
    deleteQuotation: computed(() => isFinanzas.value || isAdministrador.value),
    approveQuotation: computed(() => isFinanzas.value || isAdministrador.value),
    rejectQuotation: computed(() => isFinanzas.value || isAdministrador.value),
    downloadQuotationPDF: computed(() => isClinical.value || isFinanzas.value || isAdministrador.value),

    // Historias clínicas
    createMedicalRecord: computed(() => isClinical.value || isAdministrador.value),
    editMedicalRecord: computed(() => isClinical.value || isAdministrador.value),
    viewMedicalRecord: computed(() => isClinical.value || isAdministrador.value),
    deleteMedicalRecord: computed(() => isClinical.value || isAdministrador.value),
    addEvolution: computed(() => isClinical.value || isAdministrador.value),
    editEvolution: computed(() => isClinical.value || isAdministrador.value),
    deleteEvolution: computed(() => isClinical.value || isAdministrador.value),
    uploadAttachment: computed(() => isClinical.value || isAdministrador.value),
    deleteAttachment: computed(() => isClinical.value || isAdministrador.value),

    // Registros de especialidades
    createSpecialtyRecord: computed(() => {
      const specialty = safeUser.value?.specialty
      return isClinical.value || isAdministrador.value ||
             ['implantologia', 'ortodoncia', 'endodoncia', 'rehabilitacion', 'cirugia_oral'].includes(specialty)
    }),
    editSpecialtyRecord: computed(() => {
      const specialty = safeUser.value?.specialty
      return isClinical.value || isAdministrador.value ||
             ['implantologia', 'ortodoncia', 'endodoncia', 'rehabilitacion', 'cirugia_oral'].includes(specialty)
    }),
    viewSpecialtyRecord: computed(() => isClinical.value || isAdministrador.value),
    deleteSpecialtyRecord: computed(() => {
      const specialty = safeUser.value?.specialty
      return isClinical.value || isAdministrador.value ||
             ['implantologia', 'ortodoncia', 'endodoncia', 'rehabilitacion', 'cirugia_oral'].includes(specialty)
    }),

    // Interconsultas
    createInterconsultation: computed(() => isClinical.value || isAdministrador.value),
    viewInterconsultation: computed(() => isClinical.value || isAdministrador.value),
    respondInterconsultation: computed(() => isClinical.value || isAdministrador.value),
    completeInterconsultation: computed(() => isClinical.value || isAdministrador.value),
    deleteInterconsultation: computed(() => isClinical.value || isAdministrador.value),

    // IA Asistiva - Solo odontólogos y especialistas
    'ai-analysis.analyze': computed(() => ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'].includes(safeUser.value?.role)),
    'ai-analysis.view': computed(() => ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'].includes(safeUser.value?.role)),
    'ai-analysis.review': computed(() => ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'].includes(safeUser.value?.role)),
    'ai-analysis.delete': computed(() => ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'].includes(safeUser.value?.role)),
    'ai-analysis.stats': computed(() => ['administrador', 'odontologo', 'implantologo', 'tecnico_dental'].includes(safeUser.value?.role))
  }

  // Métodos de conveniencia para roles específicos
  const isAdministrador = computed(() => safeUser.value?.role === 'administrador')
  const isRecepcionista = computed(() => safeUser.value?.role === 'recepcionista')
  const isClinical = computed(() => [
    'odontologo',
    'implantologo',
    'tecnico_dental',
    'asistente'
  ].includes(safeUser.value?.role))
  const isFinanzas = computed(() => safeUser.value?.role === 'finanzas')

  // Método para verificar múltiples permisos
  const hasAnyPermission = (permissions) => {
    return permissions.some(permission => can[permission]?.value)
  }

  // Método para verificar todos los permisos
  const hasAllPermissions = (permissions) => {
    return permissions.every(permission => can[permission]?.value)
  }

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
