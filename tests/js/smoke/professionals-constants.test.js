import { describe, it, expect } from 'vitest'
import {
  SPECIALTY_LABELS,
  SPECIALTY_OPTIONS,
  CLINICAL_ROLES,
  ROLE_LABELS
} from '../../../resources/js/modules/professionals/professionals.constants'

// T6 (RELIABILITY-002): the shared vocabulary consumed by both professionals
// pages, pinned at module level. ProfessionalsAppShellTest guards the source
// contract (the exports exist and the copy is frozen); these tests pin the
// value/label pairing itself, so an inversion cannot ship behind a passing
// source guard.
describe('professionals shared vocabulary contract', () => {
  it('labels every stored specialty code and keeps role codes out of the selectable options', () => {
    for (const option of SPECIALTY_OPTIONS) {
      expect(option).toEqual({ value: option.value, label: SPECIALTY_LABELS[option.value] })
      expect(option.label).toBeTruthy()
    }

    for (const code of [
      'general',
      'orthodontics',
      'implantology',
      'dental_technician',
      'assistant',
      'finance'
    ]) {
      expect(SPECIALTY_LABELS[code]).toBeTruthy()
    }

    const selectableValues = SPECIALTY_OPTIONS.map(option => option.value)
    expect(selectableValues).not.toContain('dental_technician')
    expect(selectableValues).not.toContain('assistant')
    expect(selectableValues).not.toContain('finance')
  })

  it('keeps ROLE_LABELS covering every clinical role with its frozen copy', () => {
    expect(ROLE_LABELS).toMatchObject({
      odontologo: 'Odontólogo',
      implantologo: 'Implantólogo',
      tecnico_dental: 'Técnico Dental',
      asistente: 'Asistente'
    })
    for (const role of CLINICAL_ROLES) {
      expect(ROLE_LABELS[role]).toBeTruthy()
    }
  })
})
