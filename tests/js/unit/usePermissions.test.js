import { describe, expect, it, vi } from 'vitest'

// #280: the composable reads `useAuth().user`; the ref is swapped per case.
const authState = vi.hoisted(() => ({ ref: null }))

vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { ref } = await import('vue')
  const user = ref(null)
  authState.ref = user
  return { useAuth: () => ({ user }) }
})

import { usePermissions } from '../../../resources/js/composables/usePermissions.js'

describe('usePermissions derives every flag from the permission payload (#280)', () => {
  it('gates reception out of the consultation close but keeps check-in', () => {
    authState.ref.value = {
      role: 'recepcionista',
      permissions: [
        'view.patient',
        'manage.appointment',
        'check_in.appointment',
        'view.cash_register'
      ]
    }

    const { can } = usePermissions()

    expect(can.checkInAppointment.value).toBe(true)
    expect(can.viewPatient.value).toBe(true)
    expect(can.viewCashRegister.value).toBe(true)
    expect(can.completeConsultation.value).toBe(false)
    expect(can.deletePatient.value).toBe(false)
    expect(can.voidTransaction.value).toBe(false)
  })

  it('lets a clinical role close the consultation', () => {
    authState.ref.value = {
      role: 'odontologo',
      permissions: ['complete.appointment', 'manage.medical_record']
    }

    const { can } = usePermissions()

    expect(can.completeConsultation.value).toBe(true)
    expect(can.createMedicalRecord.value).toBe(true)
    expect(can.manageUsers.value).toBe(false)
  })

  it('fails closed when a stored session has no permission list', () => {
    authState.ref.value = { role: 'administrador' }

    const { can, hasAnyPermission, hasAllPermissions } = usePermissions()

    expect(can.completeConsultation.value).toBe(false)
    expect(can.manageUsers.value).toBe(false)
    expect(hasAnyPermission(['manageUsers', 'viewPatient'])).toBe(false)
    expect(hasAllPermissions(['manageUsers'])).toBe(false)
  })

  it('combines flags for hasAnyPermission / hasAllPermissions', () => {
    authState.ref.value = {
      role: 'finanzas',
      permissions: ['view.cash_report', 'export.cash_report', 'view.dashboard']
    }

    const { hasAnyPermission, hasAllPermissions } = usePermissions()

    expect(hasAnyPermission(['viewCashReports', 'manageUsers'])).toBe(true)
    expect(hasAllPermissions(['viewCashReports', 'exportCashData'])).toBe(true)
    expect(hasAllPermissions(['viewCashReports', 'manageUsers'])).toBe(false)
  })
})
