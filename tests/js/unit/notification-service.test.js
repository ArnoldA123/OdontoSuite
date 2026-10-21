import { describe, it, expect } from 'vitest'
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
})
