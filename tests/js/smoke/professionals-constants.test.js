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
  it('pairs every SPECIALTY_OPTIONS entry with the label of the same key', () => {
    expect(SPECIALTY_OPTIONS).toHaveLength(Object.keys(SPECIALTY_LABELS).length)
    for (const option of SPECIALTY_OPTIONS) {
      expect(option).toEqual({ value: option.value, label: SPECIALTY_LABELS[option.value] })
    }
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
