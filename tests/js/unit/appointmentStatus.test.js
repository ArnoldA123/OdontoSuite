import { describe, it, expect } from 'vitest'
import {
  APPOINTMENT_STATUSES,
  APPOINTMENT_STATUS_KEYS,
  APPOINTMENT_CHIP_BASE_CLASSES,
  statusLabel,
  statusBadgeVariant,
  statusBadgeProps,
  statusChipClasses,
  statusToneClasses
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

// Variant validator of components/ui/StatusBadge.vue (stricter of the two
// badge primitives: Badge.vue accepts these plus 'default'/'primary').
const STATUS_BADGE_VARIANTS = ['success', 'warning', 'error', 'info', 'neutral']

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
      expect(typeof entry.chip, key).toBe('string')
      expect(entry.chip.length, key).toBeGreaterThan(0)
      expect(typeof entry.tone, key).toBe('string')
      expect(entry.tone.length, key).toBeGreaterThan(0)
    }
  })

  it('keeps the exact Spanish labels currently shown', () => {
    expect(statusLabel('scheduled')).toBe('Programada')
    expect(statusLabel('confirmed')).toBe('Confirmada')
    expect(statusLabel('arrived')).toBe('En espera')
    expect(statusLabel('in_consultation')).toBe('En Consulta')
    expect(statusLabel('completed')).toBe('Completada')
    expect(statusLabel('cancelled')).toBe('Cancelada')
    expect(statusLabel('no_show')).toBe('No se presentó')
    expect(statusLabel('rescheduled')).toBe('Reprogramada')
  })

  it('keeps the exact badge variants and dot flags currently shown', () => {
    expect(statusBadgeProps('scheduled')).toEqual({ variant: 'info', showDot: true })
    expect(statusBadgeProps('confirmed')).toEqual({ variant: 'success', showDot: true })
    expect(statusBadgeProps('arrived')).toEqual({ variant: 'success', showDot: true })
    expect(statusBadgeProps('in_consultation')).toEqual({ variant: 'warning', showDot: true })
    expect(statusBadgeProps('completed')).toEqual({ variant: 'neutral', showDot: true })
    expect(statusBadgeProps('cancelled')).toEqual({ variant: 'error', showDot: true })
    expect(statusBadgeProps('no_show')).toEqual({ variant: 'neutral', showDot: true })
    expect(statusBadgeProps('rescheduled')).toEqual({ variant: 'warning', showDot: false })

    for (const key of APPOINTMENT_STATUS_KEYS) {
      expect(STATUS_BADGE_VARIANTS, key).toContain(statusBadgeVariant(key))
    }
    expect(statusBadgeVariant('scheduled')).toBe('info')
    expect(statusBadgeVariant('rescheduled')).toBe('warning')
  })

  it('keeps the exact chip class tokens currently shown', () => {
    // T8b defect 2: the base string changed from
    // 'bg-theme-surface-elevated border border-hairline' — a base background
    // + border colour fought the registry tint in the cascade (all chips
    // computed white). Base is shape-only now; the tint lives in `chip`.
    expect(APPOINTMENT_CHIP_BASE_CLASSES).toBe('border')
    expect(statusChipClasses('scheduled')).toBe('border-accent-200 bg-accent-50')
    expect(statusChipClasses('confirmed')).toBe('border-systemGreen-200 bg-systemGreen-50')
    expect(statusChipClasses('arrived')).toBe('border-systemGreen-200 bg-systemGreen-50')
    expect(statusChipClasses('in_consultation')).toBe('border-systemYellow-200 bg-systemYellow-50')
    expect(statusChipClasses('completed')).toBe('border-hairline bg-theme-surface')
    expect(statusChipClasses('cancelled')).toBe('border-systemRed-200 bg-systemRed-50')
    expect(statusChipClasses('no_show')).toBe('border-systemYellow-200 bg-systemYellow-50')
    expect(statusChipClasses('rescheduled')).toBe('border-accent-200 bg-accent-50')
  })

  it('keeps the exact tone class tokens currently shown', () => {
    expect(statusToneClasses('scheduled')).toBe('bg-accent-50 text-accent-700')
    expect(statusToneClasses('confirmed')).toBe('bg-systemGreen-50 text-systemGreen-700')
    expect(statusToneClasses('arrived')).toBe('bg-systemGreen-50 text-systemGreen-700')
    expect(statusToneClasses('in_consultation')).toBe('bg-systemYellow-50 text-systemYellow-700')
    expect(statusToneClasses('completed')).toBe('bg-systemGray-100 text-systemGray-700')
    expect(statusToneClasses('cancelled')).toBe('bg-systemRed-50 text-systemRed-700')
    expect(statusToneClasses('no_show')).toBe('bg-systemYellow-50 text-systemYellow-700')
    expect(statusToneClasses('rescheduled')).toBe('bg-accent-50 text-accent-700')
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

  it('keeps the neutral completed entry on the theme surface tint', () => {
    expect(statusChipClasses('completed')).toBe('border-hairline bg-theme-surface')
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
    expect(statusBadgeVariant('voided')).toBe('info')
    expect(statusBadgeProps('voided')).toEqual({ variant: 'info', showDot: true })
    expect(statusBadgeProps(undefined)).toEqual({ variant: 'info', showDot: true })
    expect(statusChipClasses('voided')).toBe('border-accent-200 bg-accent-50')
    expect(statusChipClasses(undefined)).toBe('border-accent-200 bg-accent-50')
    expect(statusToneClasses('voided')).toBe('bg-accent-50 text-accent-700')
    expect(statusToneClasses(null)).toBe('bg-accent-50 text-accent-700')
  })

  it('ignores prototype-inherited keys such as constructor and toString', () => {
    expect(statusLabel('constructor')).toBe('constructor')
    expect(statusLabel('toString')).toBe('toString')
    expect(statusLabel('__proto__')).toBe('__proto__')
    expect(statusBadgeProps('constructor')).toEqual({ variant: 'info', showDot: true })
    expect(statusChipClasses('toString')).toBe('border-accent-200 bg-accent-50')
    expect(statusToneClasses('__proto__')).toBe('bg-accent-50 text-accent-700')
  })
})
