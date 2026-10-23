import { describe, it, expect } from 'vitest'
import {
  PHASES,
  PHASE_LABELS,
  formatDate,
  formatDateTime,
  isOverdue,
  nextPhases,
  patientFullName,
  phaseBadgeVariant,
  phaseLabel,
  pieceLabel
} from '../../../resources/js/modules/lab-cases/labCaseMeta.js'

// F5 (#316) — pure presentation rules of the prosthetic laboratory desk: the
// phase vocabulary, the forward-only door, the overdue rule and the date
// shapes the table and the detail print.

describe('labCaseMeta phase vocabulary', () => {
  it('keeps the machine order the API validates', () => {
    expect(PHASES).toEqual(['in_preparation', 'in_laboratory', 'try_in', 'delivered', 'placed'])
  })

  it('labels every phase in Spanish', () => {
    expect(PHASE_LABELS).toEqual({
      in_preparation: 'En preparación',
      in_laboratory: 'En laboratorio',
      try_in: 'Prueba en boca',
      delivered: 'Recibido del taller',
      placed: 'Colocado'
    })
    expect(phaseLabel('delivered')).toBe('Recibido del taller')
    expect(phaseLabel(undefined)).toBe('—')
  })

  it('maps each phase to its badge variant', () => {
    expect(phaseBadgeVariant('placed')).toBe('success')
    expect(phaseBadgeVariant('delivered')).toBe('info')
    expect(phaseBadgeVariant('try_in')).toBe('warning')
    expect(phaseBadgeVariant('in_preparation')).toBe('neutral')
    expect(phaseBadgeVariant('in_laboratory')).toBe('neutral')
    expect(phaseBadgeVariant('legacy')).toBe('neutral')
  })
})

describe('labCaseMeta nextPhases', () => {
  it('offers only what comes after the current phase', () => {
    expect(nextPhases('in_preparation')).toEqual(['in_laboratory', 'try_in', 'delivered', 'placed'])
    expect(nextPhases('in_laboratory')).toEqual(['try_in', 'delivered', 'placed'])
    expect(nextPhases('try_in')).toEqual(['delivered', 'placed'])
  })

  it('collapses the door after the workshop delivered the case', () => {
    expect(nextPhases('delivered')).toEqual(['placed'])
  })

  it('has nothing left to offer once the case is placed', () => {
    expect(nextPhases('placed')).toEqual([])
  })

  it('offers the whole machine for an unknown status', () => {
    expect(nextPhases('unknown')).toEqual(PHASES)
    expect(nextPhases(undefined)).toEqual(PHASES)
  })
})

describe('labCaseMeta isOverdue', () => {
  const today = new Date(2026, 9, 20)

  it('flags a case promised before today', () => {
    expect(isOverdue({ promised_at: '2026-10-19', status: 'in_laboratory' }, today)).toBe(true)
  })

  it('leaves a case promised today or later alone', () => {
    expect(isOverdue({ promised_at: '2026-10-20', status: 'in_laboratory' }, today)).toBe(false)
    expect(isOverdue({ promised_at: '2026-11-02', status: 'in_laboratory' }, today)).toBe(false)
  })

  it('stops counting once the workshop delivered or the piece is placed', () => {
    expect(isOverdue({ promised_at: '2026-10-01', status: 'delivered' }, today)).toBe(false)
    expect(isOverdue({ promised_at: '2026-10-01', status: 'placed' }, today)).toBe(false)
  })

  it('answers false without a promise date', () => {
    expect(isOverdue({ promised_at: null, status: 'try_in' }, today)).toBe(false)
    expect(isOverdue(null, today)).toBe(false)
  })
})

describe('labCaseMeta formatting', () => {
  it('prints a short Spanish date and falls back to an em dash', () => {
    expect(formatDate('2026-10-20')).toBe('20 oct 2026')
    expect(formatDate('2026-11-02')).toBe('02 nov 2026')
    expect(formatDate(null)).toBe('—')
    expect(formatDate('')).toBe('—')
    expect(formatDate('not-a-date')).toBe('—')
  })

  it('prints the delivery and placement instants with their time', () => {
    expect(formatDateTime('2026-11-02T09:00:00.000000Z')).toBe('02 nov 2026, 09:00')
    expect(formatDateTime('2026-11-02 09:00:00')).toBe('02 nov 2026, 09:00')
    expect(formatDateTime(null)).toBe('—')
    expect(formatDateTime('')).toBe('—')
  })

  it('composes the patient name and falls back when the payload omits it', () => {
    expect(patientFullName({ first_name: 'Ana', last_name: 'Torres' })).toBe('Ana Torres')
    expect(patientFullName({ full_name: 'Luis Rojas' })).toBe('Luis Rojas')
    expect(patientFullName(null)).toBe('—')
    expect(patientFullName({})).toBe('—')
  })

  it('prints the FDI number of the piece', () => {
    expect(pieceLabel({ id: 4, fdi_number: '16' })).toBe('16')
    expect(pieceLabel({ id: 4, fdi_number: 21 })).toBe('21')
    expect(pieceLabel({ id: 4, fdi_number: null })).toBe('—')
    expect(pieceLabel(null)).toBe('—')
  })
})
