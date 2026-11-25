import { formatCurrency } from '@/composables/useFormatters'

/**
 * Servicio para convertir eventos WebSocket en notificaciones
 */
export class NotificationService {
  /**
   * Clave estable de una notificación: el hecho (evento) y la entidad que lo
   * produjo. Dos capas que publican el mismo evento comparten clave y, por
   * tanto, una sola fila en la pila de avisos.
   *
   * El sobre de la difusión no es uniforme —el alta y el cambio llevan el
   * objeto en `appointment`, el borrado lleva `appointment_id` suelto—, así
   * que la entidad se resuelve con la misma tolerancia que el resto del
   * módulo.
   */
  static notificationKey(eventName, data) {
    const entity =
      data?.appointment ??
      data?.patient ??
      data?.treatment_plan ??
      data?.quotation ??
      data?.medical_record ??
      data?.evolution ??
      data?.attachment ??
      data?.addendum ??
      data?.specialty_record ??
      data?.record ??
      data?.transaction ??
      data?.payment ??
      data?.cash_session ??
      data
    const id = entity?.id ?? data?.appointment_id ?? null

    // Sin id no hay hecho identificable: se deja sin clave para no colapsar
    // eventos distintos bajo el mismo nombre.
    return id === null || id === undefined || id === '' ? null : `${eventName}:${id}`
  }

  /**
   * Mapear evento de cita a notificación.
   *
   * citas-privacidad (T1): la difusión de una cita lleva sólo lo operativo
   * (`id`, `user_id`, `status`, `scheduled_at`, `updated_at`), así que la copia
   * es neutral por tipo y nunca nombra a un paciente.
   */
  static mapAppointmentEvent(eventName, data) {
    const appointment = data.appointment || data

    const mappings = {
      'appointment.created': {
        title: 'Nueva cita agregada',
        message: 'Cita creada en la agenda.',
        type: 'info',
        category: 'appointments',
        action: () => {
          if (window.router && appointment.id) {
            window.router.push({ path: '/calendar', query: { appointment: appointment.id } })
          }
        }
      },
      'appointment.updated': {
        title: 'Cita actualizada',
        message: 'La cita ha sido modificada.',
        type: 'info',
        category: 'appointments'
      },
      'appointment.deleted': {
        title: 'Cita eliminada',
        message: 'La cita fue eliminada de la agenda.',
        type: 'warning',
        category: 'appointments'
      },
      'appointment.completed': {
        title: 'Cita completada',
        message: 'La cita ha sido completada.',
        type: 'success',
        category: 'appointments'
      },
      'appointment.checked_in': {
        title: 'Llegada registrada',
        message: 'La llegada quedó registrada en la agenda.',
        type: 'info',
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
    const amount = formatCurrency(quotation.total_amount || 0)

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
      // #422: the act of sending is its own notification, not a generic
      // "updated": the patient decision is now reachable from the screen.
      'quotation.sent': {
        title: 'Presupuesto enviado',
        message: `El presupuesto de ${patientName} por ${amount} se envió al paciente`,
        type: 'info',
        category: 'quotations'
      },
      'quotation.approved': {
        title: 'Presupuesto aprobado',
        message: `${patientName} ha aprobado el presupuesto de ${amount}`,
        type: 'success',
        category: 'quotations',
        sound: true
      },
      'quotation.rejected': {
        title: 'Presupuesto rechazado',
        message: data.reason
          ? `${patientName} ha rechazado el presupuesto de ${amount}. Motivo: ${data.reason}`
          : `${patientName} ha rechazado el presupuesto de ${amount}`,
        type: 'warning',
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
      'clinical-addendum.created': {
        title: 'Addendum registrado',
        message: `Se agregó un addendum a la historia clínica de ${patientName}`,
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
    const amount = formatCurrency(transaction.amount || 0)

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
    let notification = null

    // Determinar el tipo de evento y mapear
    if (eventName.includes('appointment')) {
      notification = this.mapAppointmentEvent(eventName, data)
    } else if (eventName.includes('patient')) {
      notification = this.mapPatientEvent(eventName, data)
    } else if (eventName.includes('treatment-plan')) {
      notification = this.mapTreatmentPlanEvent(eventName, data)
    } else if (eventName.includes('quotation')) {
      notification = this.mapQuotationEvent(eventName, data)
    } else if (eventName.includes('medical-record') || eventName.includes('clinical')) {
      notification = this.mapMedicalRecordEvent(eventName, data)
    } else if (eventName.includes('specialty-record')) {
      notification = this.mapSpecialtyRecordEvent(eventName, data)
    } else if (eventName.includes('payment') || eventName.includes('cash-session')) {
      notification = this.mapPaymentEvent(eventName, data)
    }

    // Notificación genérica
    if (!notification) {
      notification = {
        title: 'Notificación',
        message: 'Se ha producido un evento en el sistema',
        type: 'info',
        category: 'system'
      }
    }

    // La clave viaja con el aviso para que el almacén deduplique y reinicie
    // su temporizador en vez de apilar una copia del mismo hecho.
    notification.key = this.notificationKey(eventName, data)

    return notification
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
