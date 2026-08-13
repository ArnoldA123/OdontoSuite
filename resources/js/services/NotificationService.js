/**
 * Servicio para convertir eventos WebSocket en notificaciones
 */
export class NotificationService {
  /**
   * Mapear evento de cita a notificación
   */
  static mapAppointmentEvent(eventName, data) {
    const appointment = data.appointment || data
    const patientName = appointment.patient?.full_name || appointment.patient?.name || 'Paciente'

    const mappings = {
      'appointment.created': {
        title: 'Nueva cita programada',
        message: `Cita creada para ${patientName} el ${this.formatDate(appointment.scheduled_at)}`,
        type: 'info',
        category: 'appointments',
        action: () => {
          // Navegar a la cita si es necesario
          if (window.router) {
            window.router.push(`/calendar?date=${appointment.scheduled_at}`)
          }
        }
      },
      'appointment.updated': {
        title: 'Cita actualizada',
        message: `La cita de ${patientName} ha sido modificada`,
        type: 'info',
        category: 'appointments'
      },
      'appointment.deleted': {
        title: 'Cita cancelada',
        message: `La cita de ${patientName} ha sido cancelada`,
        type: 'warning',
        category: 'appointments'
      }
    }

    return mappings[eventName] || null
  }

  /**
   * Mapear evento de paciente a notificación
   */
  static mapPatientEvent(eventName, data) {
    const patient = data.patient || data
    const patientName = patient.full_name || `${patient.first_name} ${patient.last_name}`

    const mappings = {
      'patient.created': {
        title: 'Nuevo paciente registrado',
        message: `${patientName} ha sido registrado en el sistema`,
        type: 'success',
        category: 'patients'
      },
      'patient.updated': {
        title: 'Paciente actualizado',
        message: `Los datos de ${patientName} han sido actualizados`,
        type: 'info',
        category: 'patients'
      },
      'patient.deleted': {
        title: 'Paciente eliminado',
        message: `${patientName} ha sido eliminado del sistema`,
        type: 'warning',
        category: 'patients'
      }
    }

    return mappings[eventName] || null
  }

  /**
   * Mapear evento de plan de tratamiento a notificación
   */
  static mapTreatmentPlanEvent(eventName, data) {
    const plan = data.treatment_plan || data
    const patientName = plan.patient?.full_name || plan.patient?.name || 'Paciente'

    const mappings = {
      'treatment-plan.created': {
        title: 'Nuevo plan de tratamiento',
        message: `Plan "${plan.title}" creado para ${patientName}`,
        type: 'success',
        category: 'treatment-plans',
        action: () => {
          if (window.router && plan.id) {
            window.router.push(`/treatment-plans/${plan.id}`)
          }
        }
      },
      'treatment-plan.updated': {
        title: 'Plan de tratamiento actualizado',
        message: `El plan "${plan.title}" de ${patientName} ha sido actualizado`,
        type: 'info',
        category: 'treatment-plans'
      },
      'treatment-plan.deleted': {
        title: 'Plan de tratamiento eliminado',
        message: `El plan de ${patientName} ha sido eliminado`,
        type: 'warning',
        category: 'treatment-plans'
      }
    }

    return mappings[eventName] || null
  }

  /**
   * Mapear evento de presupuesto a notificación
   */
  static mapQuotationEvent(eventName, data) {
    const quotation = data.quotation || data
    const patientName = quotation.patient?.full_name || quotation.patient?.name || 'Paciente'
    const amount = this.formatCurrency(quotation.total_amount || 0)

    const mappings = {
      'quotation.created': {
        title: 'Nuevo presupuesto',
        message: `Presupuesto de ${amount} creado para ${patientName}`,
        type: 'info',
        category: 'quotations'
      },
      'quotation.updated': {
        title: 'Presupuesto actualizado',
        message: `El presupuesto de ${patientName} ha sido modificado`,
        type: 'info',
        category: 'quotations'
      },
      'quotation.approved': {
        title: 'Presupuesto aprobado',
        message: `${patientName} ha aprobado el presupuesto de ${amount}`,
        type: 'success',
        category: 'quotations',
        sound: true
      }
    }

    return mappings[eventName] || null
  }

  /**
   * Mapear evento de historia clínica a notificación
   */
  static mapMedicalRecordEvent(eventName, data) {
    const record =
      data.medical_record ||
      data.evolution?.medical_record ||
      data.attachment?.medical_record ||
      data
    const patientName = record.patient?.full_name || record.patient?.name || 'Paciente'

    const mappings = {
      'medical-record.created': {
        title: 'Nueva historia clínica',
        message: `Historia clínica creada para ${patientName}`,
        type: 'info',
        category: 'medical-records'
      },
      'medical-record.updated': {
        title: 'Historia clínica actualizada',
        message: `La historia clínica de ${patientName} ha sido actualizada`,
        type: 'info',
        category: 'medical-records'
      },
      'clinical-evolution.created': {
        title: 'Nueva evolución clínica',
        message: `Nueva evolución agregada a la historia de ${patientName}`,
        type: 'info',
        category: 'medical-records'
      },
      'clinical-attachment.created': {
        title: 'Nuevo adjunto',
        message: `Nuevo archivo adjunto a la historia de ${patientName}`,
        type: 'info',
        category: 'medical-records'
      }
    }

    return mappings[eventName] || null
  }

  /**
   * Mapear evento de registro de especialidad a notificación
   */
  static mapSpecialtyRecordEvent(eventName, data) {
    const record = data.record || data
    const patientName = record.patient?.full_name || record.patient?.name || 'Paciente'
    const specialty = this.getSpecialtyLabel(data.specialty || record.specialty_type)

    const mappings = {
      'specialty-record.created': {
        title: `Nuevo registro de ${specialty}`,
        message: `Registro de ${specialty} creado para ${patientName}`,
        type: 'info',
        category: 'medical-records'
      },
      'specialty-record.updated': {
        title: `Registro de ${specialty} actualizado`,
        message: `El registro de ${specialty} de ${patientName} ha sido actualizado`,
        type: 'info',
        category: 'medical-records'
      }
    }

    return mappings[eventName] || null
  }

  /**
   * Mapear evento de pago a notificación
   */
  static mapPaymentEvent(eventName, data) {
    const transaction = data.transaction || data
    const patientName = transaction.patient?.full_name || transaction.patient?.name || 'Paciente'
    const amount = this.formatCurrency(transaction.amount || 0)

    const mappings = {
      'payment.registered': {
        title: 'Pago registrado',
        message: `Pago de ${amount} registrado para ${patientName}`,
        type: 'success',
        category: 'payments',
        sound: true
      },
      'cash-session.opened': {
        title: 'Sesión de caja abierta',
        message: 'Se ha abierto una nueva sesión de caja',
        type: 'info',
        category: 'payments'
      },
      'cash-session.closed': {
        title: 'Sesión de caja cerrada',
        message: 'La sesión de caja ha sido cerrada',
        type: 'info',
        category: 'payments'
      }
    }

    return mappings[eventName] || null
  }

  /**
   * Convertir evento WebSocket a notificación
   */
  static convertEventToNotification(eventName, data) {
    // Determinar el tipo de evento y mapear
    if (eventName.includes('appointment')) {
      return this.mapAppointmentEvent(eventName, data)
    }
    if (eventName.includes('patient')) {
      return this.mapPatientEvent(eventName, data)
    }
    if (eventName.includes('treatment-plan')) {
      return this.mapTreatmentPlanEvent(eventName, data)
    }
    if (eventName.includes('quotation')) {
      return this.mapQuotationEvent(eventName, data)
    }
    if (eventName.includes('medical-record') || eventName.includes('clinical')) {
      return this.mapMedicalRecordEvent(eventName, data)
    }
    if (eventName.includes('specialty-record')) {
      return this.mapSpecialtyRecordEvent(eventName, data)
    }
    if (eventName.includes('payment') || eventName.includes('cash-session')) {
      return this.mapPaymentEvent(eventName, data)
    }

    // Notificación genérica
    return {
      title: 'Notificación',
      message: 'Se ha producido un evento en el sistema',
      type: 'info',
      category: 'system'
    }
  }

  /**
   * Formatear fecha
   */
  static formatDate(dateString) {
    if (!dateString) return ''
    const date = new Date(dateString)
    return date.toLocaleDateString('es-ES', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  /**
   * Formatear moneda
   */
  static formatCurrency(amount) {
    return new Intl.NumberFormat('es-PE', {
      style: 'currency',
      currency: 'PEN'
    }).format(amount)
  }

  /**
   * Obtener etiqueta de especialidad
   */
  static getSpecialtyLabel(specialty) {
    const labels = {
      implantologia: 'Implantología',
      ortodoncia: 'Ortodoncia',
      endodoncia: 'Endodoncia',
      rehabilitacion: 'Rehabilitación',
      cirugia_oral: 'Cirugía Oral'
    }
    return labels[specialty] || specialty
  }
}
