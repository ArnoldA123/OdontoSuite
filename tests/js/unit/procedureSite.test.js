import { describe, it, expect } from 'vitest'
import {
  SITE_TYPES,
  SITE_TYPE_LABELS,
  SITE_TYPE_OPTIONS,
  QUADRANTS,
  ARCHES,
  requiredSiteField,
  siteRequirementMessage,
  siteSummary,
  siteTypeOf
} from '../../../resources/js/modules/procedure-catalog/procedureSite'

const PIECE = {
  id: 9,
  fdi_number: '16',
  name: 'Primer Molar Superior Derecho',
  surfaces: [
    { id: 1, surface_code: 'D', surface_name: 'Distal', abbreviation: 'D' },
    { id: 2, surface_code: 'O', surface_name: 'Oclusal', abbreviation: 'O' }
  ]
}

describe('procedureSite vocabulary', () => {
  it('names the five applicability values the catalog stores', () => {
    expect(SITE_TYPES).toEqual(['none', 'tooth', 'surface', 'quadrant', 'arch'])
    expect(SITE_TYPE_LABELS).toEqual({
      none: 'Sitio independiente',
      tooth: 'Pieza dental',
      surface: 'Superficie dental',
      quadrant: 'Cuadrante',
      arch: 'Arcada'
    })
    expect(SITE_TYPE_OPTIONS).toHaveLength(5)
    expect(SITE_TYPE_OPTIONS[0]).toEqual({ value: 'none', label: 'Sitio independiente' })
  })

  it('mirrors the quadrant and arch vocabulary of the backend rule', () => {
    expect(QUADRANTS.map(option => option.value)).toEqual([
      'superior_derecho',
      'superior_izquierdo',
      'inferior_derecho',
      'inferior_izquierdo'
    ])
    expect(ARCHES.map(option => option.value)).toEqual(['superior', 'inferior'])
    expect(QUADRANTS[0].label).toBe('Cuadrante superior derecho')
    expect(ARCHES[1].label).toBe('Arcada inferior')
  })
})

describe('requiredSiteField', () => {
  it('names the column each applicability demands from the line', () => {
    expect(requiredSiteField('tooth')).toBe('dental_piece_id')
    expect(requiredSiteField('surface')).toBe('tooth_surface_id')
    expect(requiredSiteField('quadrant')).toBe('site_code')
    expect(requiredSiteField('arch')).toBe('site_code')
    expect(requiredSiteField('none')).toBeNull()
  })

  it('treats an unknown or missing applicability as no requirement', () => {
    expect(requiredSiteField(undefined)).toBeNull()
    expect(requiredSiteField('diente')).toBeNull()
  })
})

describe('siteSummary', () => {
  it('summarizes a tooth', () => {
    expect(siteSummary({ dental_piece_id: 9 }, PIECE)).toBe('Pieza 16')
  })

  it('summarizes a surface with the piece and the surface name', () => {
    expect(siteSummary({ dental_piece_id: 9, tooth_surface_id: 2 }, PIECE)).toBe(
      'Pieza 16 — Oclusal'
    )
  })

  it('summarizes a quadrant', () => {
    expect(siteSummary({ site_code: 'superior_derecho' })).toBe('Cuadrante superior derecho')
  })

  it('summarizes an arch', () => {
    expect(siteSummary({ site_code: 'inferior' })).toBe('Arcada inferior')
  })

  it('stays empty when the line declares no site', () => {
    expect(siteSummary(null)).toBe('')
    expect(siteSummary({})).toBe('')
    expect(siteSummary({ dental_piece_id: null, tooth_surface_id: null, site_code: null })).toBe('')
  })
})

describe('siteTypeOf', () => {
  it('names the applicability a declared site implies', () => {
    expect(siteTypeOf({ dental_piece_id: 9 })).toBe('tooth')
    expect(siteTypeOf({ dental_piece_id: 9, tooth_surface_id: 2 })).toBe('surface')
    expect(siteTypeOf({ site_code: 'inferior_derecho' })).toBe('quadrant')
    expect(siteTypeOf({ site_code: 'superior' })).toBe('arch')
    expect(siteTypeOf({})).toBe('none')
    expect(siteTypeOf(null)).toBe('none')
  })
})

describe('siteRequirementMessage', () => {
  it('names the missing site in Spanish and stays silent without a requirement', () => {
    expect(siteRequirementMessage('Obturación composite', 'tooth')).toBe(
      'El procedimiento «Obturación composite» se realiza sobre una pieza dental: selecciona la pieza.'
    )
    expect(siteRequirementMessage('Sellado', 'surface')).toContain('la superficie')
    expect(siteRequirementMessage('Raspaje', 'quadrant')).toBe(
      'El procedimiento «Raspaje» se realiza sobre un cuadrante: selecciona el cuadrante.'
    )
    expect(siteRequirementMessage('Prótesis', 'arch')).toBe(
      'El procedimiento «Prótesis» se realiza sobre una arcada: selecciona la arcada.'
    )
    expect(siteRequirementMessage('Limpieza', 'none')).toBe('')
  })
})
