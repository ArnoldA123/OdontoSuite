import { ref } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import { formatCurrency, formatDate } from './useFormatters'

export function useAuditLogs() {
  const { get } = useApi()
  const toast = useToast()
  const loading = ref(false)
  const auditLogs = ref([])

  /**
   * Get audit logs for a patient
   *
   * #399: a reader that paints its own error state needs the failure, not a
   * silent empty list. `throwOnError` opts into that; every other caller keeps
   * the previous contract (toast + empty list).
   */
  const getPatientAuditLogs = async (patientId, options = {}) => {
    try {
      loading.value = true
      const response = await get(`/api/audit-logs/patient/${patientId}`)
      // The endpoint returns { data: [...], meta: {...} }
      auditLogs.value = response.data || []
      return auditLogs.value
    } catch (error) {
      if (options.throwOnError) throw error
      toast.error('Error al cargar historial de auditoría')
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Get all audit logs with filters
   */
  const getAllAuditLogs = async (filters = {}) => {
    try {
      loading.value = true
      const queryParams = new URLSearchParams(filters).toString()
      const response = await get(`/api/audit-logs?${queryParams}`)
      return response.data || []
    } catch (error) {
      toast.error('Error al cargar historial de auditoría')
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Get a specific audit log
   */
  const getAuditLog = async logId => {
    try {
      loading.value = true
      const response = await get(`/api/audit-logs/${logId}`)
      return response.data
    } catch (error) {
      toast.error('Error al cargar registro de auditoría')
      return null
    } finally {
      loading.value = false
    }
  }

  /**
   * Get audit logs for a user
   */
  const getUserAuditLogs = async userId => {
    try {
      loading.value = true
      const response = await get(`/api/audit-logs/user/${userId}`)
      auditLogs.value = response.data || []
      return auditLogs.value
    } catch (error) {
      toast.error('Error al cargar historial de auditoría')
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Get audit logs for a dental chair
   */
  const getDentalChairAuditLogs = async chairId => {
    try {
      loading.value = true
      const response = await get(`/api/audit-logs/dental-chair/${chairId}`)
      auditLogs.value = response.data || []
      return auditLogs.value
    } catch (error) {
      toast.error('Error al cargar historial de auditoría')
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Get audit logs for an appointment type
   */
  const getAppointmentTypeAuditLogs = async typeId => {
    try {
      loading.value = true
      const response = await get(`/api/audit-logs/appointment-type/${typeId}`)
      auditLogs.value = response.data || []
      return auditLogs.value
    } catch (error) {
      toast.error('Error al cargar historial de auditoría')
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Format audit log action for display
   */
  const formatAction = action => {
    const actions = {
      patient_created: 'Paciente Creado',
      patient_updated: 'Paciente Actualizado',
      patient_deleted: 'Paciente Eliminado',
      user_created: 'Profesional Creado',
      user_updated: 'Profesional Actualizado',
      user_deleted: 'Profesional Eliminado',
      dental_chair_created: 'Ambiente Creado',
      dental_chair_updated: 'Ambiente Actualizado',
      dental_chair_deleted: 'Ambiente Eliminado',
      appointment_type_created: 'Tipo de Cita Creado',
      appointment_type_updated: 'Tipo de Cita Actualizado',
      appointment_type_deleted: 'Tipo de Cita Eliminado',
      appointment_created: 'Cita Creada',
      appointment_updated: 'Cita Actualizada',
      appointment_deleted: 'Cita Eliminada'
    }
    return actions[action] || action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
  }

  /**
   * Get changes summary from audit log
   */
  const getChangesSummary = log => {
    const oldValues = log.old_values || {}
    const newValues = log.new_values || {}

    if (!log.new_values || Object.keys(oldValues).length === 0) {
      return null
    }

    // Campos a ignorar (no son relevantes para mostrar)
    const ignoredFields = ['id', 'created_at', 'updated_at', 'branch_id', 'dni']

    // Mapeo de nombres de campos a etiquetas legibles
    const fieldLabels = {
      // Patient fields
      first_name: 'Nombre',
      last_name: 'Apellido',
      document_number: 'DNI',
      document: 'Documento',
      email: 'Email',
      phone: 'Teléfono',
      birth_date: 'Fecha de Nacimiento',
      gender: 'Género',
      address: 'Dirección',
      emergency_contact_name: 'Contacto de Emergencia',
      emergency_contact_phone: 'Teléfono de Emergencia',
      medical_history: 'Historial Médico',
      allergies: 'Alergias',
      notes: 'Notas',
      is_active: 'Estado',
      other_medical_info: 'Información Médica Adicional',
      // User fields
      name: 'Nombre',
      username: 'Usuario',
      specialty: 'Especialidad',
      role: 'Rol',
      // DentalChair fields
      code: 'Código',
      description: 'Descripción',
      equipment: 'Equipamiento',
      status: 'Estado',
      // AppointmentType fields
      default_duration_minutes: 'Duración (minutos)',
      price: 'Precio',
      color: 'Color',
      requires_confirmation: 'Requiere confirmación',
      requires_materials: 'Requiere materiales',
      is_consultation_mode: 'Modo consulta',
      default_procedure_catalog_id: 'Procedimiento por defecto'
    }

    // Boolean appointment-type flags: rendered as Sí/No instead of the raw
    // `true` / `false` the snapshot stores.
    const booleanFlagFields = [
      'requires_confirmation',
      'requires_materials',
      'is_consultation_mode'
    ]

    const changes = {}

    // Comparar todos los campos
    const allKeys = new Set([...Object.keys(oldValues), ...Object.keys(newValues)])

    allKeys.forEach(key => {
      // Ignorar campos no relevantes
      if (ignoredFields.includes(key)) {
        return
      }

      const oldVal = oldValues[key]
      const newVal = newValues[key]

      // Formatear valores según el tipo
      let oldValueStr = ''
      let newValueStr = ''

      if (oldVal === null || oldVal === undefined || oldVal === '') {
        oldValueStr = '(vacío)'
      } else if (key === 'birth_date' && oldVal) {
        oldValueStr = formatDate(oldVal) || String(oldVal)
      } else if (key === 'is_active') {
        oldValueStr = oldVal ? 'Activo' : 'Inactivo'
      } else if (booleanFlagFields.includes(key)) {
        oldValueStr = oldVal ? 'Sí' : 'No'
      } else if (key === 'gender') {
        const genderMap = { male: 'Masculino', female: 'Femenino', other: 'Otro' }
        oldValueStr = genderMap[oldVal] || oldVal
      } else if (key === 'specialty') {
        const specialtyMap = {
          general: 'Odontología General',
          orthodontics: 'Ortodoncia',
          endodontics: 'Endodoncia',
          periodontics: 'Periodoncia',
          oral_surgery: 'Cirugía Oral',
          pediatric: 'Odontopediatría',
          prosthodontics: 'Prótesis Dental',
          cosmetic: 'Odontología Estética',
          implantology: 'Implantología',
          dental_technician: 'Técnico Dental',
          assistant: 'Asistente',
          finance: 'Finanzas'
        }
        oldValueStr = specialtyMap[oldVal] || oldVal
      } else if (key === 'status') {
        const statusMap = { active: 'Activo', inactive: 'Inactivo', maintenance: 'Mantenimiento' }
        oldValueStr = statusMap[oldVal] || oldVal
      } else if (key === 'role') {
        const roleMap = {
          admin: 'Administrador',
          administrador: 'Administrador',
          recepcion: 'Recepcionista',
          recepcionista: 'Recepcionista',
          odontologo: 'Odontólogo',
          implantologo: 'Implantólogo',
          tecnico_dental: 'Técnico Dental',
          asistente: 'Asistente',
          finanzas: 'Finanzas'
        }
        oldValueStr = roleMap[oldVal] || oldVal
      } else if (key === 'price') {
        oldValueStr = typeof oldVal === 'number' ? formatCurrency(oldVal) : String(oldVal)
      } else if (key === 'default_duration_minutes') {
        oldValueStr = `${oldVal} minutos`
      } else {
        oldValueStr = String(oldVal)
      }

      if (newVal === null || newVal === undefined || newVal === '') {
        newValueStr = '(vacío)'
      } else if (key === 'birth_date' && newVal) {
        newValueStr = formatDate(newVal) || String(newVal)
      } else if (key === 'is_active') {
        newValueStr = newVal ? 'Activo' : 'Inactivo'
      } else if (booleanFlagFields.includes(key)) {
        newValueStr = newVal ? 'Sí' : 'No'
      } else if (key === 'gender') {
        const genderMap = { male: 'Masculino', female: 'Femenino', other: 'Otro' }
        newValueStr = genderMap[newVal] || newVal
      } else if (key === 'specialty') {
        const specialtyMap = {
          general: 'Odontología General',
          orthodontics: 'Ortodoncia',
          endodontics: 'Endodoncia',
          periodontics: 'Periodoncia',
          oral_surgery: 'Cirugía Oral',
          pediatric: 'Odontopediatría',
          prosthodontics: 'Prótesis Dental',
          cosmetic: 'Odontología Estética',
          implantology: 'Implantología',
          dental_technician: 'Técnico Dental',
          assistant: 'Asistente',
          finance: 'Finanzas'
        }
        newValueStr = specialtyMap[newVal] || newVal
      } else if (key === 'status') {
        const statusMap = { active: 'Activo', inactive: 'Inactivo', maintenance: 'Mantenimiento' }
        newValueStr = statusMap[newVal] || newVal
      } else if (key === 'role') {
        const roleMap = {
          admin: 'Administrador',
          administrador: 'Administrador',
          recepcion: 'Recepcionista',
          recepcionista: 'Recepcionista',
          odontologo: 'Odontólogo',
          implantologo: 'Implantólogo',
          tecnico_dental: 'Técnico Dental',
          asistente: 'Asistente',
          finanzas: 'Finanzas'
        }
        newValueStr = roleMap[newVal] || newVal
      } else if (key === 'price') {
        newValueStr = typeof newVal === 'number' ? formatCurrency(newVal) : String(newVal)
      } else if (key === 'default_duration_minutes') {
        newValueStr = `${newVal} minutos`
      } else {
        newValueStr = String(newVal)
      }

      // Comparar valores formateados
      if (oldValueStr !== newValueStr) {
        changes[key] = {
          field: fieldLabels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
          old: oldValueStr,
          new: newValueStr
        }
      }
    })

    return changes
  }

  return {
    loading,
    auditLogs,
    getPatientAuditLogs,
    getUserAuditLogs,
    getDentalChairAuditLogs,
    getAppointmentTypeAuditLogs,
    getAllAuditLogs,
    getAuditLog,
    formatAction,
    getChangesSummary
  }
}
