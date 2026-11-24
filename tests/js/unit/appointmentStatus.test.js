import { describe, it, expect } from 'vitest'
import {
  APPOINTMENT_STATUSES,
  APPOINTMENT_STATUS_KEYS,
  APPOINTMENT_CHIP_BASE_CLASSES,
  APPOINTMENT_RAIL_STAGES,
  APPOINTMENT_STAGE_TIME_FIELDS,
  APPOINTMENT_TERMINAL_OUTCOMES,
  isTerminalOutcome,
  railStep,
  statusLabel,
  statusBadgeVariant,
  statusBadgeProps,
  statusChipClasses,
  statusIsActive,
  statusRailClasses,
  statusToneClasses,
  statusBlockTitleClasses,
  statusBlockMetaClasses
} from '../../../resources/js/modules/appointments/appointmentStatus.js'

// Statuses the API can return: the ones accepted by
// AppointmentController@updateStatus and the appointment FormRequests
// (Store/UpdateAppointmentRequest), plus `arrived` — written by
// POST /appointments/{id}/check-in since #281, never by the generic PATCH.
const API_STATUS_KEYS = [
  'scheduled',
  'confirmed',
  'arrived',
  'in_consultation',
  'completed',
  'cancelled',
  'no_show',
  'rescheduled'
]

// Legal DB enum value kept by 2025_10_14_123001_fix_appointments_status_enum;
// the app writes `in_consultation` but rows serialized by the API may still
// carry it.
const LEGACY_DB_STATUS_KEYS = ['in_progress']

// Variant validator of components/ui/StatusBadge.vue.
const STATUS_BADGE_VARIANTS = [
  'success',
  'warning',
  'error',
  'info',
  'neutral',
  'indigo',
  'purple',
  'orange'
]

describe('appointmentStatus registry', () => {
  it('covers every status the API can return', () => {
    for (const key of [...API_STATUS_KEYS, ...LEGACY_DB_STATUS_KEYS]) {
      expect(APPOINTMENT_STATUSES[key], `missing registry entry for "${key}"`).toBeDefined()
    }
    expect([...APPOINTMENT_STATUS_KEYS].sort()).toEqual(
      [...API_STATUS_KEYS, ...LEGACY_DB_STATUS_KEYS].sort()
    )
  })

  it('maps every status to label, variant and class tokens with no undefined', () => {
    for (const key of APPOINTMENT_STATUS_KEYS) {
      const entry = APPOINTMENT_STATUSES[key]
      expect(typeof entry.label, key).toBe('string')
      expect(entry.label.length, key).toBeGreaterThan(0)
      expect(STATUS_BADGE_VARIANTS, key).toContain(entry.badgeVariant)
      expect(typeof entry.showDot, key).toBe('boolean')
      expect(typeof entry.active, key).toBe('boolean')
      expect(typeof entry.chip, key).toBe('string')
      expect(entry.chip.length, key).toBeGreaterThan(0)
      expect(typeof entry.tone, key).toBe('string')
      expect(entry.tone.length, key).toBeGreaterThan(0)
      expect(typeof entry.rail.node, key).toBe('string')
      expect(typeof entry.rail.dot, key).toBe('string')
      expect(typeof entry.rail.label, key).toBe('string')
      for (const token of Object.values(entry.rail)) {
        expect(token.length, `${key} rail token`).toBeGreaterThan(0)
      }
    }
  })

  it('keeps the exact Spanish labels currently shown', () => {
    expect(statusLabel('scheduled')).toBe('Programada')
    expect(statusLabel('confirmed')).toBe('Confirmada')
    expect(statusLabel('arrived')).toBe('En espera')
    expect(statusLabel('in_consultation')).toBe('En consulta')
    expect(statusLabel('completed')).toBe('Completada')
    expect(statusLabel('cancelled')).toBe('Cancelada')
    expect(statusLabel('no_show')).toBe('No se presentó')
    expect(statusLabel('rescheduled')).toBe('Reprogramada')
  })

  it('gives every status its own badge variant, dot and pulse flags', () => {
    expect(statusBadgeProps('scheduled')).toEqual({ variant: 'neutral', showDot: true })
    expect(statusBadgeProps('confirmed')).toEqual({ variant: 'info', showDot: true })
    expect(statusBadgeProps('arrived')).toEqual({ variant: 'indigo', showDot: true })
    expect(statusBadgeProps('in_consultation')).toEqual({ variant: 'warning', showDot: true })
    expect(statusBadgeProps('completed')).toEqual({ variant: 'success', showDot: true })
    expect(statusBadgeProps('cancelled')).toEqual({ variant: 'error', showDot: true })
    expect(statusBadgeProps('no_show')).toEqual({ variant: 'orange', showDot: true })
    expect(statusBadgeProps('rescheduled')).toEqual({ variant: 'purple', showDot: false })

    for (const key of APPOINTMENT_STATUS_KEYS) {
      expect(STATUS_BADGE_VARIANTS, key).toContain(statusBadgeVariant(key))
    }
    expect(statusBadgeVariant('scheduled')).toBe('neutral')
    expect(statusBadgeVariant('rescheduled')).toBe('purple')

    // S3 — only the two states that ask for an action are active; the rest
    // keep the soft tint and a still dot.
    expect(statusIsActive('arrived')).toBe(true)
    expect(statusIsActive('in_consultation')).toBe(true)
    expect(statusIsActive('in_progress')).toBe(true)
    for (const key of [
      'scheduled',
      'confirmed',
      'completed',
      'cancelled',
      'no_show',
      'rescheduled'
    ]) {
      expect(statusIsActive(key), key).toBe(false)
    }
    expect(statusIsActive('voided')).toBe(false)
  })

  it('keeps the exact chip class tokens currently shown', () => {
    // T8b defect 2: the base string changed from
    // 'bg-theme-surface-elevated border border-hairline' — a base background
    // + border colour fought the registry tint in the cascade (all chips
    // computed white). Base is shape-only now; the tint lives in `chip`.
    expect(APPOINTMENT_CHIP_BASE_CLASSES).toBe('border')
    expect(statusChipClasses('scheduled')).toBe('border-systemGray-200 bg-systemGray-50')
    expect(statusChipClasses('confirmed')).toBe('border-systemSteel-200 bg-systemSteel-50')
    expect(statusChipClasses('arrived')).toBe('border-systemIndigo-200 bg-systemIndigo-50')
    expect(statusChipClasses('in_consultation')).toBe('border-systemYellow-200 bg-systemYellow-50')
    expect(statusChipClasses('completed')).toBe('border-systemGreen-200 bg-systemGreen-50')
    expect(statusChipClasses('cancelled')).toBe('border-systemRed-200 bg-systemRed-50')
    expect(statusChipClasses('no_show')).toBe(
      'border-dashed border-systemOrange-200 bg-systemOrange-50'
    )
    expect(statusChipClasses('rescheduled')).toBe('border-systemPurple-200 bg-systemPurple-50')
  })

  it('keeps the exact tone class tokens currently shown', () => {
    expect(statusToneClasses('scheduled')).toBe('bg-systemGray-50 text-systemGray-700')
    expect(statusToneClasses('confirmed')).toBe('bg-systemSteel-50 text-systemSteel-700')
    expect(statusToneClasses('arrived')).toBe('bg-systemIndigo-50 text-systemIndigo-700')
    expect(statusToneClasses('in_consultation')).toBe('bg-systemYellow-50 text-systemYellow-700')
    expect(statusToneClasses('completed')).toBe('bg-systemGreen-50 text-systemGreen-700')
    expect(statusToneClasses('cancelled')).toBe('bg-systemRed-50 text-systemRed-700')
    expect(statusToneClasses('no_show')).toBe('bg-systemOrange-50 text-systemOrange-700')
    expect(statusToneClasses('rescheduled')).toBe('bg-systemPurple-50 text-systemPurple-700')
  })

  it('keeps the brand accent out of every status identity', () => {
    // S3: the accent green belongs to the primary action, never to a state.
    for (const key of APPOINTMENT_STATUS_KEYS) {
      const entry = APPOINTMENT_STATUSES[key]
      const identity = [entry.chip, entry.tone, ...Object.values(entry.rail)].join(' ')
      expect(identity, key).not.toMatch(/accent/)
      expect(statusRailClasses(key).dot, key).not.toMatch(/accent/)
    }
  })

  it('gives the four former-green statuses four distinct identities', () => {
    const formerGreen = ['scheduled', 'confirmed', 'arrived', 'rescheduled']
    const chips = formerGreen.map(statusChipClasses)

    expect(new Set(chips).size).toBe(formerGreen.length)
    for (const key of formerGreen) {
      expect(statusChipClasses(key), key).not.toMatch(/accent|systemGreen/)
    }
  })
})

// S3 — En consulta is the only state that pulses, and the legacy `in_progress`
// enum travels with the same presentation as `in_consultation`.
describe('in_progress aliases in_consultation', () => {
  it('presents both DB keys with the same identity', () => {
    expect(statusLabel('in_progress')).toBe(statusLabel('in_consultation'))
    expect(statusBadgeProps('in_progress')).toEqual(statusBadgeProps('in_consultation'))
    expect(statusChipClasses('in_progress')).toBe(statusChipClasses('in_consultation'))
    expect(statusToneClasses('in_progress')).toBe(statusToneClasses('in_consultation'))
    expect(statusRailClasses('in_progress')).toEqual(statusRailClasses('in_consultation'))
    expect(statusIsActive('in_progress')).toBe(statusIsActive('in_consultation'))
  })
})

// S2 — the rail vocabulary: five ordered stages, the outcomes that leave the
// rail, and the step index of every status.
describe('appointment rail vocabulary', () => {
  it('orders the five live stages', () => {
    expect(APPOINTMENT_RAIL_STAGES.map(stage => stage.key)).toEqual([
      'scheduled',
      'confirmed',
      'arrived',
      'in_consultation',
      'completed'
    ])
    expect(APPOINTMENT_RAIL_STAGES.map(stage => stage.label)).toEqual([
      'Programada',
      'Confirmada',
      'En espera',
      'En consulta',
      'Completada'
    ])
  })

  it('declares only the outcomes that replace the rail', () => {
    expect([...APPOINTMENT_TERMINAL_OUTCOMES].sort()).toEqual([
      'cancelled',
      'no_show',
      'rescheduled'
    ])
    for (const key of APPOINTMENT_TERMINAL_OUTCOMES) {
      expect(isTerminalOutcome(key), key).toBe(true)
    }
    for (const stage of APPOINTMENT_RAIL_STAGES) {
      expect(isTerminalOutcome(stage.key), stage.key).toBe(false)
    }
    expect(isTerminalOutcome('completed')).toBe(false)
    expect(isTerminalOutcome('in_progress')).toBe(false)
    expect(isTerminalOutcome('voided')).toBe(false)
  })

  it('answers the current step of every status', () => {
    expect(railStep('scheduled')).toBe(0)
    expect(railStep('confirmed')).toBe(1)
    expect(railStep('arrived')).toBe(2)
    expect(railStep('in_consultation')).toBe(3)
    expect(railStep('in_progress')).toBe(3)
    expect(railStep('completed')).toBe(4)
    for (const outcome of APPOINTMENT_TERMINAL_OUTCOMES) {
      expect(railStep(outcome), outcome).toBe(-1)
    }
    // An unknown status falls back with the registry's scheduled entry.
    expect(railStep('voided')).toBe(0)
  })

  it('takes stage times only from fields the API publishes', () => {
    const publishedFields = ['created_at', 'confirmed_at', 'checked_in_at', 'completed_at']

    expect(Object.keys(APPOINTMENT_STAGE_TIME_FIELDS).sort()).toEqual(
      APPOINTMENT_RAIL_STAGES.map(stage => stage.key).sort()
    )
    for (const [stage, field] of Object.entries(APPOINTMENT_STAGE_TIME_FIELDS)) {
      if (field === null) continue
      expect(publishedFields, `${stage} must read a published field`).toContain(field)
    }
    // The consultation start has no published field: no time is invented for it.
    expect(APPOINTMENT_STAGE_TIME_FIELDS.in_consultation).toBeNull()
  })
})

// T8b defect 2 — chip anatomy contract: the base classes carry SHAPE only
// (no background, no border colour) and the registry status classes carry
// the tint (background + border colour). With both layers setting
// background-color/border-color, the CSS cascade decides by stylesheet
// order and the status tint lost in the real browser (every chip computed
// to rgb(255,255,255)).
describe('appointment chip anatomy contract (T8b defect 2)', () => {
  const baseTokens = APPOINTMENT_CHIP_BASE_CLASSES.split(/\s+/).filter(Boolean)

  it('keeps base classes shape-only: no bg-* and no border-colour tokens', () => {
    expect(baseTokens.length).toBeGreaterThan(0)
    for (const token of baseTokens) {
      expect(token.startsWith('bg-'), `base token "${token}" must not set a background`).toBe(false)
      const isBorderColorToken = token.startsWith('border-') && token !== 'border-radius'
      expect(isBorderColorToken, `base token "${token}" must not set a border colour`).toBe(false)
    }
  })

  it('provides the full tint (background + border colour) from every status entry', () => {
    for (const key of APPOINTMENT_STATUS_KEYS) {
      const tokens = statusChipClasses(key).split(/\s+/).filter(Boolean)
      expect(
        tokens.some(token => token.startsWith('bg-')),
        `status "${key}" must set its own background`
      ).toBe(true)
      expect(
        tokens.some(token => token.startsWith('border-')),
        `status "${key}" must set its own border colour`
      ).toBe(true)
    }
  })

  it('keeps the cancelled-no-show chip dashed', () => {
    expect(statusChipClasses('no_show').split(/\s+/)).toContain('border-dashed')
    expect(statusChipClasses('cancelled').split(/\s+/)).not.toContain('border-dashed')
  })
})

describe('day board block copy (T4)', () => {
  // El tablero del día marca la cancelación en el título (tachado) y baja el
  // contraste de la línea secundaria de una cita completada.
  it('tacha la cita cancelada y deja el resto con el label primario', () => {
    expect(statusBlockTitleClasses('cancelled')).toContain('line-through')
    expect(statusBlockTitleClasses('cancelled')).toContain('text-systemRed-700')
    expect(statusBlockTitleClasses('scheduled')).toBe('text-theme-primary')
    expect(statusBlockTitleClasses('completed')).toBe('text-theme-primary')
  })

  it('atenúa el texto secundario de una cita completada', () => {
    expect(statusBlockMetaClasses('completed')).toBe('text-systemGray-600')
    expect(statusBlockMetaClasses('confirmed')).toBe('text-theme-secondary')
  })
})

describe('unknown status fallback', () => {
  it('passes unknown string statuses through in labels', () => {
    expect(statusLabel('voided')).toBe('voided')
    expect(statusLabel('bogus_status')).toBe('bogus_status')
  })

  it('returns an empty label for missing or non-string statuses', () => {
    expect(statusLabel('')).toBe('')
    expect(statusLabel(undefined)).toBe('')
    expect(statusLabel(null)).toBe('')
    expect(statusLabel(42)).toBe('')
    expect(statusLabel({})).toBe('')
  })

  it('falls back to the scheduled presentation for badges and classes', () => {
    expect(statusBadgeVariant('voided')).toBe('neutral')
    expect(statusBadgeProps('voided')).toEqual({ variant: 'neutral', showDot: true })
    expect(statusBadgeProps(undefined)).toEqual({ variant: 'neutral', showDot: true })
    expect(statusChipClasses('voided')).toBe('border-systemGray-200 bg-systemGray-50')
    expect(statusChipClasses(undefined)).toBe('border-systemGray-200 bg-systemGray-50')
    expect(statusToneClasses('voided')).toBe('bg-systemGray-50 text-systemGray-700')
    expect(statusToneClasses(null)).toBe('bg-systemGray-50 text-systemGray-700')
    expect(statusIsActive('voided')).toBe(false)
  })

  it('ignores prototype-inherited keys such as constructor and toString', () => {
    expect(statusLabel('constructor')).toBe('constructor')
    expect(statusLabel('toString')).toBe('toString')
    expect(statusLabel('__proto__')).toBe('__proto__')
    expect(statusBadgeProps('constructor')).toEqual({ variant: 'neutral', showDot: true })
    expect(statusChipClasses('toString')).toBe('border-systemGray-200 bg-systemGray-50')
    expect(statusToneClasses('__proto__')).toBe('bg-systemGray-50 text-systemGray-700')
  })
})
