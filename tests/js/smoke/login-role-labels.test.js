import { describe, it, expect } from 'vitest'
import { roleLabel } from '@/modules/auth/roleLabels'

describe('roleLabel', () => {
  it.each([
    ['administrador', 'Administrador'],
    ['recepcionista', 'Recepcionista'],
    ['odontologo', 'Odontólogo'],
    ['implantologo', 'Implantólogo'],
    ['tecnico_dental', 'Técnico dental'],
    ['asistente', 'Asistente'],
    ['finanzas', 'Finanzas']
  ])('maps %s to %s', (code, label) => {
    expect(roleLabel(code)).toBe(label)
  })

  it('falls back to the raw code for unknown roles', () => {
    expect(roleLabel('superadmin')).toBe('superadmin')
  })

  it('returns an empty string when there is no role', () => {
    expect(roleLabel(undefined)).toBe('')
    expect(roleLabel('')).toBe('')
  })
})
