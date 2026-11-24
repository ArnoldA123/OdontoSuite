import { describe, it, expect, vi } from 'vitest'
import { NotificationService } from '../../../resources/js/services/NotificationService.js'

describe('quotation notifications', () => {
  it('maps quotation.rejected as a warning that names the patient, the amount and the reason', () => {
    const notification = NotificationService.convertEventToNotification('quotation.rejected', {
      quotation: {
        total_amount: 1200,
        patient: { full_name: 'Ana Pérez' }
      },
      reason: 'Precio alto'
    })

    expect(notification.title).toBe('Presupuesto rechazado')
    expect(notification.type).toBe('warning')
    expect(notification.category).toBe('quotations')
    expect(notification.sound).toBe(true)
    expect(notification.message).toContain('Ana Pérez')
    expect(notification.message).toMatch(/S\/\s1,200\.00/)
    expect(notification.message).toContain('Precio alto')
  })

  it('maps a rejection without a reason without leaving a dangling label', () => {
    const notification = NotificationService.convertEventToNotification('quotation.rejected', {
      quotation: { total_amount: 1200, patient: { full_name: 'Ana Pérez' } }
    })

    expect(notification.title).toBe('Presupuesto rechazado')
    expect(notification.message).toContain('Ana Pérez')
    expect(notification.message).not.toContain('Motivo')
  })

  it('keeps routing quotation.created through the quotation mapping', () => {
    const notification = NotificationService.convertEventToNotification('quotation.created', {
      quotation: { total_amount: 500, patient: { full_name: 'Ana Pérez' } }
    })

    expect(notification.title).toBe('Nuevo presupuesto')
    expect(notification.type).toBe('info')
    expect(notification.category).toBe('quotations')
  })

  it('announces the send as its own event (#422)', () => {
    const notification = NotificationService.convertEventToNotification('quotation.sent', {
      quotation: { total_amount: 1400, patient: { full_name: 'Ana Pérez' } }
    })

    expect(notification.title).toBe('Presupuesto enviado')
    expect(notification.type).toBe('info')
    expect(notification.category).toBe('quotations')
    expect(notification.message).toContain('Ana Pérez')
    expect(notification.message).toMatch(/S\/\s1,400\.00/)
  })
})

// citas-privacidad (T1): el sobre difundido de una cita es operativo —`id`,
// `user_id`, `status`, `scheduled_at`, `updated_at`— y no trae paciente, así
// que la copia es neutral por tipo y ninguna inventa un nombre.
describe('appointment notifications', () => {
  const operationalPayload = () => ({
    appointment: {
      id: 42,
      user_id: 8,
      status: 'scheduled',
      scheduled_at: '2026-12-03T09:00:00+00:00',
      updated_at: '2026-12-03T08:00:00+00:00'
    }
  })

  it('maps a minimal payload without patient to neutral copy', () => {
    const notification = NotificationService.convertEventToNotification(
      'appointment.created',
      operationalPayload()
    )

    expect(notification.title).toBe('Nueva cita agregada')
    expect(notification.type).toBe('info')
    expect(notification.category).toBe('appointments')
    expect(notification.message).toBe('Cita creada en la agenda.')
    expect(notification.message).not.toContain('Paciente')
    expect(notification.message).not.toContain('undefined')
  })

  it('keeps neutral copy for every appointment event', () => {
    const titles = {
      'appointment.created': 'Nueva cita agregada',
      'appointment.updated': 'Cita actualizada',
      'appointment.deleted': 'Cita eliminada',
      'appointment.completed': 'Cita completada',
      'appointment.checked_in': 'Llegada registrada'
    }

    for (const [eventName, title] of Object.entries(titles)) {
      const notification = NotificationService.convertEventToNotification(
        eventName,
        operationalPayload()
      )

      expect(notification, eventName).not.toBeNull()
      expect(notification.title, eventName).toBe(title)
      expect(notification.message, eventName).not.toContain('Paciente')
      expect(notification.message, eventName).not.toContain('undefined')
    }
  })

  it('routes the created notification with the appointment id', () => {
    const push = vi.fn()
    window.router = { push }

    const notification = NotificationService.convertEventToNotification(
      'appointment.created',
      operationalPayload()
    )
    notification.action()

    expect(push).toHaveBeenCalledWith({ path: '/calendar', query: { appointment: 42 } })

    delete window.router
  })

  it('carries a stable key per event and entity so two layers do not stack', () => {
    const created = NotificationService.convertEventToNotification(
      'appointment.created',
      operationalPayload()
    )
    const updated = NotificationService.convertEventToNotification(
      'appointment.updated',
      operationalPayload()
    )
    const deleted = NotificationService.convertEventToNotification('appointment.deleted', {
      appointment_id: 42,
      user_id: 8
    })

    expect(created.key).toBe('appointment.created:42')
    expect(updated.key).toBe('appointment.updated:42')
    expect(deleted.key).toBe('appointment.deleted:42')
  })
})
