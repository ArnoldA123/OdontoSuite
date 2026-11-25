import { describe, expect, it } from 'vitest'
import {
  describeSpecialtyRecord,
  flattenSpecialtyRecords,
  recordLinks,
  recordsForPlan,
  specialtyRecordLink
} from '../../../resources/js/modules/specialty-records/specialtyRecordMeta'

// Slice 1 of «Registros de especialidad integrados a la atención y la
// historia»: the vocabulary the readers outside the specialty module share.
// The grouped `/patient/{id}/all` payload is flattened once, and the two links
// a record may declare are translated to the routes the SPA already answers.

const implant = {
  id: 41,
  patient_id: 7,
  treatment_plan_item_id: 9,
  procedure_id: 12,
  dental_piece_id: 3,
  dental_piece: { fdi_number: '16', name: 'Primer molar sup. derecho' },
  implant_brand: 'Straumann',
  implant_model: 'BLT',
  implant_diameter: '4.1',
  placement_date: '2026-03-04',
  created_at: '2026-03-04T10:15:00.000000Z',
  clinical_encounter_id: 55,
  clinical_encounter: { id: 55 },
  treatment_plan_item: { id: 9, treatment_plan_id: 3, procedure_catalog_id: 12 },
  procedure: { id: 12, code: 'IMPL-16', name: 'Implante unitario' }
}

const ortho = {
  id: 42,
  patient_id: 7,
  treatment_type: 'brackets',
  appliance_type: 'metálicos',
  treatment_start_date: '2026-02-01',
  created_at: '2026-02-01T09:00:00.000000Z',
  clinical_encounter_id: null,
  treatment_plan_item_id: null
}

describe('flattenSpecialtyRecords', () => {
  it('flattens the five groups and keeps the specialty of every row', () => {
    const rows = flattenSpecialtyRecords({
      implantologia: [implant],
      ortodoncia: [ortho],
      endodoncia: [],
      rehabilitacion: null,
      cirugia_oral: undefined
    })

    expect(rows).toHaveLength(2)
    expect(rows.map(row => row.specialtyKey).sort()).toEqual(['implantologia', 'ortodoncia'])
    expect(rows.find(row => row.id === 41).specialtyLabel).toBe('Implantología')
    expect(rows.find(row => row.id === 42).specialtyLabel).toBe('Ortodoncia')
  })

  it('sorts newest first and tolerates an empty or unexpected payload', () => {
    const rows = flattenSpecialtyRecords({ implantologia: [implant], ortodoncia: [ortho] })

    expect(rows.map(row => row.id)).toEqual([41, 42])
    expect(flattenSpecialtyRecords(null)).toEqual([])
    expect(flattenSpecialtyRecords('nope')).toEqual([])
    expect(flattenSpecialtyRecords({})).toEqual([])
  })
})

describe('recordLinks', () => {
  it('resolves the act to the patient history and the plan line to its plan', () => {
    const links = recordLinks(implant)

    expect(links.encounter).toMatchObject({
      id: 55,
      label: 'Atención #55',
      to: { path: '/medical-records', query: { patient_id: '7' } }
    })
    expect(links.plan).toMatchObject({
      id: 9,
      planId: 3,
      label: 'Plan #3',
      to: { path: '/treatment-plans', query: { patient_id: '7', plan_id: '3' } }
    })
  })

  it('returns no links for a manual record', () => {
    expect(recordLinks(ortho)).toEqual({ encounter: null, plan: null })
    expect(recordLinks(null)).toEqual({ encounter: null, plan: null })
  })

  it('keeps the act label even when the patient is unknown', () => {
    const links = recordLinks({ ...implant, patient_id: null, patient: null })

    expect(links.encounter.label).toBe('Atención #55')
    expect(links.encounter.to).toBeNull()
  })
})

describe('specialtyRecordLink', () => {
  it('builds the deep link the specialty page answers', () => {
    expect(specialtyRecordLink('implantologia', implant)).toEqual({
      path: '/specialty-records',
      query: { patient_id: '7', specialty: 'implantologia', record_id: '41' }
    })
  })

  it('refuses an unknown specialty or a record without keys', () => {
    expect(specialtyRecordLink('protesis', implant)).toBeNull()
    expect(specialtyRecordLink('implantologia', { ...implant, id: null })).toBeNull()
    expect(specialtyRecordLink('implantologia', { ...implant, patient_id: null })).toBeNull()
  })
})

describe('recordsForPlan', () => {
  const items = [
    { id: 9, procedure_catalog_id: 12 },
    { id: 10, procedure_catalog_id: null }
  ]

  it('keeps the records linked to a line and the ones naming its procedure', () => {
    const byLine = { ...ortho, id: 43, treatment_plan_item_id: 10 }
    const byProcedure = { ...ortho, id: 44, procedure_id: 12, treatment_plan_item_id: null }
    const unrelated = { ...ortho, id: 45, procedure_id: 99, treatment_plan_item_id: null }

    const linked = recordsForPlan([byLine, byProcedure, unrelated], items)

    expect(linked.map(record => record.id)).toEqual([43, 44])
  })

  it('returns nothing when the plan has no lines to match', () => {
    expect(recordsForPlan([implant], [])).toEqual([])
    expect(recordsForPlan([implant], null)).toEqual([])
  })
})

describe('describeSpecialtyRecord', () => {
  it('summarizes the structured data instead of the SOAP narrative', () => {
    expect(describeSpecialtyRecord({ ...implant, specialtyKey: 'implantologia' })).toContain(
      'Straumann'
    )
    expect(describeSpecialtyRecord({ ...ortho, specialtyKey: 'ortodoncia' })).toContain('brackets')
  })

  it('returns an empty line for an unknown specialty', () => {
    expect(describeSpecialtyRecord({ ...implant, specialtyKey: 'protesis' })).toBe('')
    expect(describeSpecialtyRecord(null)).toBe('')
  })
})
