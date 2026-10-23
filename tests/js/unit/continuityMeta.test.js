import { describe, it, expect } from 'vitest'

// F4 (#310) — the continuity desk's pure presentation helpers. `compliancePercent`
// only formats what the API publishes: the rate belongs to the backend.
import {
  compliancePercent,
  defaultRecallSlot,
  formatDate,
  intervalLabel,
  kindLabel,
  originLabel,
  patientFullName,
  preferredWindow,
  toApiDateTime,
  toDateTimeLocal
} from '../../../resources/js/modules/continuity/continuityMeta'

describe('continuityMeta patientFullName', () => {
  it('prefers the API full name and composes the parts otherwise', () => {
    expect(patientFullName({ full_name: 'Ana Torres', first_name: 'Ignored' })).toBe('Ana Torres')
    expect(patientFullName({ first_name: 'Luis', last_name: 'Rojas' })).toBe('Luis Rojas')
    expect(patientFullName({ first_name: 'Luis' })).toBe('Luis')
    expect(patientFullName(null)).toBe('Paciente')
    expect(patientFullName({})).toBe('Paciente')
  })
})

describe('continuityMeta intervalLabel', () => {
  it('names the three supported intervals and falls back to the raw count', () => {
    expect(intervalLabel(3)).toBe('3 meses')
    expect(intervalLabel(6)).toBe('6 meses')
    expect(intervalLabel(12)).toBe('12 meses')
    expect(intervalLabel(18)).toBe('18 meses')
    expect(intervalLabel(null)).toBe('—')
  })
})

describe('continuityMeta originLabel', () => {
  it('names the origin in precedence order', () => {
    expect(originLabel({ treatment_plan_id: 5 })).toBe('Plan')
    expect(originLabel({ clinical_encounter_id: 3 })).toBe('Atención')
    expect(originLabel({ appointment_id: 9 })).toBe('Cita')
    expect(originLabel({ treatment_plan_id: 5, clinical_encounter_id: 3 })).toBe('Plan')
    expect(originLabel({})).toBe('—')
  })
})

describe('continuityMeta kindLabel', () => {
  it('translates the three kinds and keeps an unknown one verbatim', () => {
    expect(kindLabel('next_session')).toBe('Próxima sesión')
    expect(kindLabel('control')).toBe('Control')
    expect(kindLabel('recall')).toBe('Recall')
    expect(kindLabel('other')).toBe('other')
    expect(kindLabel(null)).toBe('—')
  })
})

describe('continuityMeta preferredWindow', () => {
  it('renders a same-day range with the end time only', () => {
    expect(preferredWindow('2026-11-02T09:00:00', '2026-11-02T09:30:00')).toBe(
      '02 nov 2026, 09:00 – 09:30'
    )
  })

  it('repeats the date when the range crosses midnight', () => {
    expect(preferredWindow('2026-11-02T22:00:00', '2026-11-03T01:00:00')).toBe(
      '02 nov 2026, 22:00 – 03 nov 2026, 01:00'
    )
  })

  it('keeps the start only when there is no end', () => {
    expect(preferredWindow('2026-11-02T09:00:00', null)).toBe('02 nov 2026, 09:00')
    expect(preferredWindow('2026-11-02T09:00:00', '')).toBe('02 nov 2026, 09:00')
  })

  it('collapses to a dash with no endpoints at all', () => {
    expect(preferredWindow(null, null)).toBe('—')
    expect(preferredWindow('', '')).toBe('—')
  })
})

describe('continuityMeta compliancePercent', () => {
  it('formats the API rate and never derives it from counts', () => {
    expect(compliancePercent(0.75)).toBe('75%')
    expect(compliancePercent(1)).toBe('100%')
    expect(compliancePercent(0)).toBe('0%')
    expect(compliancePercent(0.6667)).toBe('67%')
  })

  it('shows a dash when the API has no denominator', () => {
    expect(compliancePercent(null)).toBe('—')
    expect(compliancePercent(undefined)).toBe('—')
    expect(compliancePercent('')).toBe('—')
  })
})

describe('continuityMeta formatDate', () => {
  it('renders the day the API named, not the UTC day', () => {
    expect(formatDate('2026-10-20')).toBe('20 oct 2026')
    expect(formatDate('2026-11-02T09:00:00')).toBe('02 nov 2026')
  })

  it('shows a dash for an empty value', () => {
    expect(formatDate(null)).toBe('—')
    expect(formatDate('')).toBe('—')
  })
})

describe('continuityMeta schedule transport', () => {
  it('slices the naive preferred window into the datetime-local shape', () => {
    expect(toDateTimeLocal('2026-11-02T09:00:00')).toBe('2026-11-02T09:00')
    expect(toDateTimeLocal('2026-11-02 09:00:00')).toBe('2026-11-02T09:00')
    expect(toDateTimeLocal('2026-11-02T09:00')).toBe('2026-11-02T09:00')
  })

  it('converts an offset timestamp to the session wall clock', () => {
    const local = new Date('2026-11-02T14:00:00Z')
    const hours = String(local.getHours()).padStart(2, '0')
    expect(toDateTimeLocal('2026-11-02T14:00:00Z')).toBe(`2026-11-02T${hours}:00`)
  })

  it('shows nothing for an empty or unparseable value', () => {
    expect(toDateTimeLocal(null)).toBe('')
    expect(toDateTimeLocal('')).toBe('')
    expect(toDateTimeLocal('mañana')).toBe('')
  })

  it('hands the API the seconds the doors validate', () => {
    expect(toApiDateTime('2026-11-02T09:00')).toBe('2026-11-02 09:00:00')
    expect(toApiDateTime('2026-11-02T09:00:42')).toBe('2026-11-02 09:00:42')
    expect(toApiDateTime('')).toBe('')
    expect(toApiDateTime(null)).toBe('')
  })
})

describe('continuityMeta defaultRecallSlot', () => {
  const now = new Date(2026, 9, 23, 14, 0, 0)

  it('offers the due date at 09:00 when that instant is still ahead', () => {
    expect(defaultRecallSlot('2026-12-01', now)).toBe('2026-12-01T09:00')
  })

  it('moves an already past due date to tomorrow at 09:00', () => {
    expect(defaultRecallSlot('2026-10-01', now)).toBe('2026-10-24T09:00')
    expect(defaultRecallSlot('2026-10-23', now)).toBe('2026-10-24T09:00')
    expect(defaultRecallSlot(null, now)).toBe('2026-10-24T09:00')
  })
})
