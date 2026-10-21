import { describe, it, expect } from 'vitest'
import {
  SPECIALTY_RECORD_DEFINITIONS,
  countInGroup,
  definitionsForRole,
  detailSections,
  formFields,
  formatRecordDate,
  formatRecordDateTime,
  getSpecialtyDefinition,
  latestRecordDate,
  primaryDate,
  statusLabel,
  statusVariant,
  summaryFields
} from '../../../resources/js/modules/specialty-records/specialtyRecordMeta.js'

const BADGE_VARIANTS = ['success', 'warning', 'error', 'info', 'neutral']

const REQUIRED_FIELDS = {
  implantologia: [
    'dental_piece_id',
    'implant_brand',
    'implant_model',
    'implant_diameter',
    'implant_length',
    'batch_number',
    'placement_date'
  ],
  ortodoncia: ['treatment_type', 'treatment_start_date'],
  endodoncia: ['dental_piece_id', 'tooth_number', 'canal_count'],
  rehabilitacion: ['dental_piece_id', 'prosthesis_type', 'material_type'],
  cirugia_oral: ['procedure_type', 'surgery_site']
}

describe('specialty records vocabulary', () => {
  it('declares the five Spanish keys the API accepts, in order', () => {
    expect(SPECIALTY_RECORD_DEFINITIONS.map(definition => definition.key)).toEqual([
      'implantologia',
      'ortodoncia',
      'endodoncia',
      'rehabilitacion',
      'cirugia_oral'
    ])
    expect(getSpecialtyDefinition('endodoncia').label).toBe('Endodoncia')
    expect(getSpecialtyDefinition('implantology')).toBeNull()
    expect(getSpecialtyDefinition(undefined)).toBeNull()
  })

  it('mirrors the role matrix the page has always applied', () => {
    expect(definitionsForRole('administrador').map(definition => definition.key)).toEqual([
      'implantologia',
      'ortodoncia',
      'endodoncia',
      'rehabilitacion',
      'cirugia_oral'
    ])
    expect(definitionsForRole('implantologo').map(definition => definition.key)).toEqual([
      'implantologia'
    ])
    expect(definitionsForRole('odontologo').map(definition => definition.key)).toEqual([
      'ortodoncia',
      'endodoncia',
      'rehabilitacion',
      'cirugia_oral'
    ])
    expect(definitionsForRole('tecnico_dental')).toEqual([])
    expect(definitionsForRole(undefined)).toEqual([])
  })

  it('marks exactly the fields StoreSpecialtyRecordRequest requires', () => {
    Object.entries(REQUIRED_FIELDS).forEach(([key, expected]) => {
      const required = formFields(getSpecialtyDefinition(key))
        .filter(field => field.required)
        .map(field => field.key)

      expect(required).toEqual(expected)
    })
  })

  it('keeps every note field within the backend length limits', () => {
    SPECIALTY_RECORD_DEFINITIONS.forEach(definition => {
      formFields(definition)
        .filter(field => field.type === 'textarea')
        .forEach(field => {
          expect(field.section).toBe('notes')
          expect(field.max).toBeLessThanOrEqual(2000)
        })
    })
  })

  it('splits the form fields into clinical, procedure and notes sections', () => {
    SPECIALTY_RECORD_DEFINITIONS.forEach(definition => {
      expect(formFields(definition).map(field => field.key)).toHaveLength(
        formFields(definition, 'clinical').length +
          formFields(definition, 'procedure').length +
          formFields(definition, 'notes').length
      )
      expect(formFields(definition, 'procedure').map(field => field.key)).toEqual(['procedure_id'])
    })

    const definition = getSpecialtyDefinition('implantologia')

    expect(formFields(definition, 'notes').map(field => field.key)).toEqual([
      'surgical_notes',
      'post_surgical_notes',
      'complications',
      'follow_up_notes'
    ])
    expect(formFields(null)).toEqual([])
    expect(formFields(definition, 'unknown')).toEqual([])
  })

  it('offers the optional catalog procedure on every specialty', () => {
    SPECIALTY_RECORD_DEFINITIONS.forEach(definition => {
      const field = formFields(definition).find(entry => entry.key === 'procedure_id')

      expect(field).toEqual({
        key: 'procedure_id',
        label: 'Procedimiento',
        type: 'procedure',
        section: 'procedure'
      })
      expect(field.required).toBeUndefined()
    })
  })

  it('never exposes a column the schema does not have', () => {
    const rejected = [
      'restoration_type',
      'color_shade',
      'occlusion_type',
      'laboratory_notes',
      'incision_type',
      'suture_material',
      'bleeding_control',
      'suture_removal_date'
    ]

    SPECIALTY_RECORD_DEFINITIONS.forEach(definition => {
      const keys = formFields(definition).map(field => field.key)

      rejected.forEach(key => {
        expect(keys).not.toContain(key)
      })
    })
  })

  it('labels and colours every status of each specialty', () => {
    SPECIALTY_RECORD_DEFINITIONS.forEach(definition => {
      const statuses = Object.keys(definition.statusLabels)

      expect(statuses.length).toBeGreaterThan(0)
      statuses.forEach(status => {
        expect(statusLabel(definition.key, status)).toBe(definition.statusLabels[status])
        expect(BADGE_VARIANTS).toContain(statusVariant(definition.key, status))
      })

      expect(statusVariant(definition.key, 'not-a-status')).toBe('neutral')
      expect(statusLabel(definition.key, 'not-a-status')).toBe('not-a-status')
      expect(statusLabel(definition.key, null)).toBe('—')
      expect(statusVariant('unknown', 'placed')).toBe('neutral')
    })
  })

  it('groups progress and completed statuses inside the declared enums', () => {
    SPECIALTY_RECORD_DEFINITIONS.forEach(definition => {
      const statuses = Object.keys(definition.statusLabels)

      definition.progressStatuses.concat(definition.completedStatuses).forEach(status => {
        expect(statuses).toContain(status)
      })
      expect(
        definition.progressStatuses.some(status => definition.completedStatuses.includes(status))
      ).toBe(false)
    })
  })

  it('counts records in a group and tolerates missing payloads', () => {
    expect(countInGroup([{ id: 1 }, { id: 2 }])).toBe(2)
    expect(countInGroup([])).toBe(0)
    expect(countInGroup(undefined)).toBe(0)
    expect(countInGroup(null)).toBe(0)
    expect(countInGroup({ length: 4 })).toBe(0)
  })

  it('picks the newest created_at of the group', () => {
    expect(
      latestRecordDate([
        { id: 1, created_at: '2026-03-04T10:15:00.000000Z' },
        { id: 2, created_at: '2026-05-20T08:00:00.000000Z' },
        { id: 3, created_at: null }
      ])
    ).toBe('2026-05-20T08:00:00.000000Z')
    expect(latestRecordDate([{ id: 1 }])).toBeNull()
    expect(latestRecordDate([])).toBeNull()
    expect(latestRecordDate(null)).toBeNull()
  })

  it('formats API dates without shifting the calendar day', () => {
    expect(formatRecordDate('2026-10-20T00:00:00.000000Z')).toBe('20 oct 2026')
    expect(formatRecordDate('2026-03-04')).toBe('04 mar 2026')
    expect(formatRecordDate('2026-09-01')).toBe('01 set 2026')
    expect(formatRecordDate(null)).toBe('—')
    expect(formatRecordDate('')).toBe('—')
    expect(formatRecordDate('not-a-date')).toBe('—')
  })

  it('formats date-times with the API time when present', () => {
    expect(formatRecordDateTime('2026-10-20T14:05:00.000000Z')).toBe('20 oct 2026 14:05')
    expect(formatRecordDateTime('2026-10-20')).toBe('20 oct 2026')
    expect(formatRecordDateTime(null)).toBe('—')
  })

  it('summarises a record with its non-empty real columns only', () => {
    const definition = getSpecialtyDefinition('implantologia')
    const summary = summaryFields(definition, {
      implant_brand: 'Straumann',
      implant_model: 'BLT',
      implant_diameter: null,
      implant_length: '',
      created_at: '2026-03-04T10:15:00.000000Z'
    })

    expect(summary.map(entry => entry.key)).toEqual(['implant_brand', 'implant_model'])
    expect(summary[0]).toEqual({ key: 'implant_brand', label: 'Marca', value: 'Straumann' })
    expect(summaryFields(definition, null)).toEqual([])
    expect(summaryFields(null, {})).toEqual([])
  })

  it('drops empty fields and empty sections from the detail', () => {
    const definition = getSpecialtyDefinition('cirugia_oral')
    const sections = detailSections(definition, {
      procedure_type: 'Extracción',
      follow_up_date: '2026-04-05T00:00:00.000000Z',
      status: 'completed',
      surgery_duration_minutes: null,
      complications: 'Inflamación leve'
    })

    expect(sections.map(section => section.id)).toEqual(['procedure', 'follow-up', 'notes'])

    const procedure = sections[0].fields
    expect(procedure[0]).toEqual({
      key: 'procedure_type',
      label: 'Tipo de procedimiento',
      value: 'Extracción'
    })
    expect(procedure.map(entry => entry.key)).not.toContain('surgery_duration_minutes')

    const followUp = sections
      .find(section => section.id === 'follow-up')
      .fields.find(entry => entry.key === 'follow_up_date')

    expect(followUp.value).toBe('05 abr 2026')
  })

  it('translates select values to their Spanish labels in the detail', () => {
    const sections = detailSections(getSpecialtyDefinition('ortodoncia'), {
      treatment_type: 'Alineadores',
      treatment_phase: 'active'
    })
    const phase = sections[0].fields.find(entry => entry.key === 'treatment_phase')

    expect(phase.value).toBe('Activa')
    expect(statusLabel('rehabilitacion', 'laboratory')).toBe('En laboratorio')
  })

  it('resolves the primary clinical date of each specialty', () => {
    expect(
      primaryDate(getSpecialtyDefinition('implantologia'), { placement_date: '2026-03-04' })
    ).toEqual({ key: 'placement_date', label: 'Fecha de colocación', value: '04 mar 2026' })
    expect(primaryDate(getSpecialtyDefinition('ortodoncia'), {})).toBeNull()
    expect(primaryDate(null, {})).toBeNull()
  })

  it('uses the surgery date as the primary date of oral surgery', () => {
    expect(
      primaryDate(getSpecialtyDefinition('cirugia_oral'), { surgery_date: '2026-05-04' })
    ).toEqual({ key: 'surgery_date', label: 'Fecha de la cirugía', value: '04 may 2026' })
  })

  it('lists the surgery date right after the surgical site in form and detail', () => {
    const definition = getSpecialtyDefinition('cirugia_oral')
    const formKeys = formFields(definition).map(field => field.key)

    expect(formKeys.indexOf('surgery_date')).toBe(formKeys.indexOf('surgery_site') + 1)

    const procedure = detailSections(definition, {
      surgery_site: 'Tercer molar',
      surgery_date: '2026-05-04'
    }).find(section => section.id === 'procedure')
    const detailKeys = procedure.fields.map(field => field.key)

    expect(detailKeys.indexOf('surgery_date')).toBe(detailKeys.indexOf('surgery_site') + 1)
    expect(procedure.fields.find(field => field.key === 'surgery_date')).toEqual({
      key: 'surgery_date',
      label: 'Fecha de la cirugía',
      value: '04 may 2026'
    })
  })

  it('shows the try-in date between impression and delivery in rehabilitation', () => {
    const definition = getSpecialtyDefinition('rehabilitacion')
    const formKeys = formFields(definition).map(field => field.key)

    expect(formKeys.indexOf('try_in_date')).toBe(formKeys.indexOf('impression_date') + 1)

    const onlyTryIn = detailSections(definition, {
      try_in_date: '2026-06-18T00:00:00.000000Z'
    }).find(section => section.id === 'timeline')

    expect(onlyTryIn.fields).toEqual([
      { key: 'try_in_date', label: 'Fecha de prueba', value: '18 jun 2026' }
    ])

    const timeline = detailSections(definition, {
      impression_date: '2026-06-10',
      try_in_date: '2026-06-18',
      delivery_date: '2026-06-25'
    }).find(section => section.id === 'timeline')

    expect(timeline.fields.map(field => field.key)).toEqual([
      'impression_date',
      'try_in_date',
      'delivery_date'
    ])
    expect(primaryDate(definition, { try_in_date: '2026-06-18' })).toBeNull()
    expect(primaryDate(definition, { cementation_date: '2026-07-01' })).toEqual({
      key: 'cementation_date',
      label: 'Fecha de cementado',
      value: '01 jul 2026'
    })
  })

  it('renders the linked catalog procedure as code and name', () => {
    const sections = detailSections(getSpecialtyDefinition('cirugia_oral'), {
      surgery_site: 'Tercer molar',
      procedure_id: 12,
      procedure: { id: 12, code: 'EXT-01', name: 'Extracción simple' }
    })
    const procedure = sections
      .find(section => section.id === 'procedure')
      .fields.find(field => field.key === 'procedure_id')

    expect(procedure).toEqual({
      key: 'procedure_id',
      label: 'Procedimiento',
      value: 'EXT-01 · Extracción simple'
    })
  })

  it('tolerates an incomplete or absent catalog procedure relation', () => {
    const definition = getSpecialtyDefinition('endodoncia')
    const byName = detailSections(definition, {
      procedure: { id: 9, code: null, name: 'Conductometría' }
    })

    expect(byName[0].fields.find(field => field.key === 'procedure_id').value).toBe(
      'Conductometría'
    )
    expect(detailSections(definition, { procedure: null })).toEqual([])
    expect(detailSections(definition, {})).toEqual([])
  })
})

describe('specialty record relations', () => {
  it('renders the dental piece the API serializes as dental_piece', () => {
    const sections = detailSections(getSpecialtyDefinition('endodoncia'), {
      tooth_number: '16',
      dental_piece_id: 4,
      dental_piece: { id: 4, fdi_number: '16', name: 'Primer molar superior derecho' }
    })
    const piece = sections
      .find(section => section.id === 'diagnosis')
      .fields.find(field => field.key === 'dental_piece_id')

    expect(piece.value).toBe('#16 · Primer molar superior derecho')
  })
})
