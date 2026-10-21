/**
 * Single source of truth for the specialty-records vocabulary: the five
 * Spanish keys the API accepts, the real Eloquent columns behind each one, the
 * status enums validated by StoreSpecialtyRecordRequest, and the role → tabs
 * matrix the page has always applied. Nothing else in the module repeats these
 * labels, keys or limits.
 */

import {
  BeakerIcon,
  CogIcon,
  CpuChipIcon,
  ScissorsIcon,
  WrenchScrewdriverIcon
} from '@heroicons/vue/24/outline'

const MONTH_LABELS = Object.freeze([
  'ene',
  'feb',
  'mar',
  'abr',
  'may',
  'jun',
  'jul',
  'ago',
  'set',
  'oct',
  'nov',
  'dic'
])

const DATE_KEY = /^(\d{4})-(\d{2})-(\d{2})/
const TIME_KEY = /T(\d{2}:\d{2})/

const textField = (key, label, options = {}) => ({ key, label, type: 'text', ...options })

const numberField = (key, label, options = {}) => ({ key, label, type: 'number', ...options })

const dateField = (key, label, options = {}) => ({ key, label, type: 'date', ...options })

const noteField = (key, label, options = {}) => ({
  key,
  label,
  type: 'textarea',
  section: 'notes',
  ...options
})

const selectOptions = labels =>
  Object.freeze(Object.entries(labels).map(([value, label]) => ({ value, label })))

const selectField = (key, label, labels, options = {}) => ({
  key,
  label,
  type: 'select',
  options: selectOptions(labels),
  ...options
})

const DENTAL_PIECE_FIELD = Object.freeze({
  key: 'dental_piece_id',
  label: 'Pieza dental',
  type: 'dental-piece',
  required: true
})

const PROCEDURE_FIELD = Object.freeze({
  key: 'procedure_id',
  label: 'Procedimiento',
  type: 'procedure',
  section: 'procedure'
})

const IMPLANTOLOGY_STATUS_LABELS = Object.freeze({
  placed: 'Colocado',
  healing: 'En cicatrización',
  loaded: 'Cargado',
  failed: 'Fallido',
  removed: 'Retirado'
})

const ORTHODONTICS_PHASE_LABELS = Object.freeze({
  initial: 'Inicial',
  active: 'Activa',
  retention: 'Retención',
  completed: 'Completada'
})

const ENDODONTICS_STATUS_LABELS = Object.freeze({
  in_progress: 'En tratamiento',
  completed: 'Completado',
  failed: 'Fallido',
  retreatment: 'Retratamiento'
})

const PROSTHESIS_TYPE_LABELS = Object.freeze({
  crown: 'Corona',
  bridge: 'Puente',
  partial: 'Prótesis parcial',
  complete: 'Prótesis completa',
  implant_prosthesis: 'Prótesis sobre implante'
})

const REHABILITATION_STATUS_LABELS = Object.freeze({
  impression: 'Impresión',
  laboratory: 'En laboratorio',
  try_in: 'En prueba',
  delivered: 'Entregada',
  cemented: 'Cementada',
  failed: 'Fallida'
})

const ORAL_SURGERY_STATUS_LABELS = Object.freeze({
  scheduled: 'Programada',
  in_progress: 'En curso',
  completed: 'Completada',
  cancelled: 'Cancelada',
  complications: 'Con complicaciones'
})

const IMPLANTOLOGY_FIELDS = [
  PROCEDURE_FIELD,
  DENTAL_PIECE_FIELD,
  textField('implant_brand', 'Marca', { required: true, max: 100 }),
  textField('implant_model', 'Modelo', { required: true, max: 100 }),
  textField('implant_diameter', 'Diámetro (mm)', { required: true, max: 20 }),
  textField('implant_length', 'Longitud (mm)', { required: true, max: 20 }),
  textField('batch_number', 'Lote', { required: true, max: 50 }),
  textField('serial_number', 'Serie', { max: 50 }),
  dateField('placement_date', 'Fecha de colocación', { required: true }),
  dateField('healing_date', 'Fecha de cicatrización'),
  dateField('loading_date', 'Fecha de carga'),
  selectField('status', 'Estado', IMPLANTOLOGY_STATUS_LABELS),
  numberField('torque_value', 'Torque (Ncm)', { min: 0, max: 999.99, step: 0.01 }),
  noteField('surgical_notes', 'Notas quirúrgicas', { max: 2000 }),
  noteField('post_surgical_notes', 'Notas postoperatorias', { max: 2000 }),
  noteField('complications', 'Complicaciones', { max: 1000 }),
  noteField('follow_up_notes', 'Notas de seguimiento', { max: 1000 })
]

const ORTHODONTICS_FIELDS = [
  PROCEDURE_FIELD,
  textField('treatment_type', 'Tipo de tratamiento', { required: true, max: 50 }),
  textField('appliance_type', 'Tipo de aparatología', { max: 100 }),
  dateField('treatment_start_date', 'Inicio del tratamiento', { required: true }),
  dateField('estimated_completion_date', 'Fin estimado'),
  dateField('actual_completion_date', 'Fin real'),
  selectField('treatment_phase', 'Fase', ORTHODONTICS_PHASE_LABELS),
  textField('treatment_objectives', 'Objetivos', { max: 2000 }),
  noteField('current_notes', 'Notas actuales', { max: 2000 }),
  noteField('activation_notes', 'Notas de activación', { max: 2000 }),
  noteField('progress_notes', 'Notas de progreso', { max: 2000 }),
  noteField('complications', 'Complicaciones', { max: 1000 }),
  noteField('retention_plan', 'Plan de retención', { max: 1000 })
]

const ENDODONTICS_FIELDS = [
  PROCEDURE_FIELD,
  DENTAL_PIECE_FIELD,
  textField('tooth_number', 'Diente', { required: true, max: 10 }),
  numberField('canal_count', 'Conductos', { required: true, min: 1, max: 4, step: 1 }),
  textField('working_length_method', 'Longitud de trabajo', { max: 50 }),
  textField('pulp_diagnosis', 'Diagnóstico pulpar', { max: 500 }),
  textField('periapical_diagnosis', 'Diagnóstico periapical', { max: 500 }),
  selectField('treatment_status', 'Estado', ENDODONTICS_STATUS_LABELS),
  dateField('treatment_completion_date', 'Fecha de alta'),
  textField('anesthesia_used', 'Anestesia', { max: 200 }),
  textField('obturation_technique', 'Técnica de obturación', { max: 200 }),
  textField('obturation_materials', 'Materiales de obturación', { max: 500 }),
  textField('treatment_plan', 'Plan de tratamiento', { max: 1000 }),
  noteField('canal_preparation_notes', 'Preparación de conductos', { max: 1000 }),
  noteField('irrigation_protocol', 'Protocolo de irrigación', { max: 1000 }),
  noteField('complications', 'Complicaciones', { max: 1000 }),
  noteField('follow_up_notes', 'Notas de seguimiento', { max: 1000 })
]

const REHABILITATION_FIELDS = [
  PROCEDURE_FIELD,
  DENTAL_PIECE_FIELD,
  selectField('prosthesis_type', 'Tipo de prótesis', PROSTHESIS_TYPE_LABELS, { required: true }),
  textField('material_type', 'Material', { required: true, max: 50 }),
  textField('shade_selection', 'Color', { max: 200 }),
  textField('laboratory_name', 'Laboratorio', { max: 100 }),
  textField('laboratory_contact', 'Contacto del laboratorio', { max: 100 }),
  dateField('impression_date', 'Fecha de impresión'),
  dateField('try_in_date', 'Fecha de prueba'),
  dateField('delivery_date', 'Fecha de entrega'),
  dateField('cementation_date', 'Fecha de cementado'),
  selectField('status', 'Estado', REHABILITATION_STATUS_LABELS),
  noteField('impression_notes', 'Notas de impresión', { max: 1000 }),
  noteField('try_in_notes', 'Notas de prueba', { max: 1000 }),
  noteField('adjustment_notes', 'Notas de ajuste', { max: 1000 }),
  noteField('cementation_notes', 'Notas de cementado', { max: 1000 }),
  noteField('patient_satisfaction', 'Satisfacción del paciente', { max: 1000 }),
  noteField('follow_up_notes', 'Notas de seguimiento', { max: 1000 })
]

const ORAL_SURGERY_FIELDS = [
  PROCEDURE_FIELD,
  textField('procedure_type', 'Tipo de procedimiento', { required: true, max: 50 }),
  textField('surgery_site', 'Sitio quirúrgico', { required: true, max: 100 }),
  dateField('surgery_date', 'Fecha de la cirugía'),
  numberField('surgery_duration_minutes', 'Duración (min)', { min: 1, max: 480, step: 1 }),
  textField('anesthesia_type', 'Anestesia', { max: 200 }),
  textField('surgical_technique', 'Técnica quirúrgica', { max: 2000 }),
  textField('sutures_used', 'Sutura utilizada', { max: 100 }),
  numberField('suture_count', 'Puntos de sutura', { min: 0, max: 50, step: 1 }),
  textField('hemostasis_method', 'Control de sangrado', { max: 200 }),
  dateField('follow_up_date', 'Fecha de seguimiento'),
  selectField('status', 'Estado', ORAL_SURGERY_STATUS_LABELS),
  noteField('surgical_notes', 'Notas quirúrgicas', { max: 2000 }),
  noteField('post_surgical_instructions', 'Indicaciones postoperatorias', { max: 2000 }),
  noteField('medications_prescribed', 'Medicación indicada', { max: 1000 }),
  noteField('recovery_notes', 'Notas de recuperación', { max: 2000 }),
  noteField('complications', 'Complicaciones', { max: 1000 }),
  noteField('follow_up_notes', 'Notas de seguimiento', { max: 1000 })
]

export const SPECIALTY_RECORD_DEFINITIONS = Object.freeze([
  {
    key: 'implantologia',
    label: 'Implantología',
    icon: WrenchScrewdriverIcon,
    roles: Object.freeze(['administrador', 'implantologo']),
    dateField: 'placement_date',
    statusField: 'status',
    statusLabels: IMPLANTOLOGY_STATUS_LABELS,
    statusVariants: Object.freeze({
      placed: 'info',
      healing: 'warning',
      loaded: 'success',
      failed: 'error',
      removed: 'neutral'
    }),
    progressStatuses: Object.freeze(['placed', 'healing']),
    completedStatuses: Object.freeze(['loaded']),
    summary: Object.freeze([
      'dental_piece_id',
      'implant_brand',
      'implant_model',
      'implant_diameter'
    ]),
    detailSections: Object.freeze([
      {
        id: 'implant',
        label: 'Implante',
        fields: Object.freeze([
          'procedure_id',
          'dental_piece_id',
          'implant_brand',
          'implant_model',
          'implant_diameter',
          'implant_length',
          'batch_number',
          'serial_number'
        ])
      },
      {
        id: 'timeline',
        label: 'Fechas',
        fields: Object.freeze(['placement_date', 'healing_date', 'loading_date'])
      },
      { id: 'measurements', label: 'Mediciones', fields: Object.freeze(['torque_value']) },
      {
        id: 'notes',
        label: 'Notas',
        fields: Object.freeze([
          'surgical_notes',
          'post_surgical_notes',
          'complications',
          'follow_up_notes'
        ])
      }
    ]),
    formFields: Object.freeze(IMPLANTOLOGY_FIELDS)
  },
  {
    key: 'ortodoncia',
    label: 'Ortodoncia',
    icon: BeakerIcon,
    roles: Object.freeze(['administrador', 'odontologo']),
    dateField: 'treatment_start_date',
    statusField: 'treatment_phase',
    statusLabels: ORTHODONTICS_PHASE_LABELS,
    statusVariants: Object.freeze({
      initial: 'info',
      active: 'warning',
      retention: 'info',
      completed: 'success'
    }),
    progressStatuses: Object.freeze(['initial', 'active']),
    completedStatuses: Object.freeze(['completed']),
    summary: Object.freeze(['treatment_type', 'appliance_type']),
    detailSections: Object.freeze([
      {
        id: 'treatment',
        label: 'Tratamiento',
        fields: Object.freeze([
          'procedure_id',
          'treatment_type',
          'appliance_type',
          'treatment_phase'
        ])
      },
      {
        id: 'timeline',
        label: 'Fechas',
        fields: Object.freeze([
          'treatment_start_date',
          'estimated_completion_date',
          'actual_completion_date'
        ])
      },
      {
        id: 'notes',
        label: 'Notas',
        fields: Object.freeze([
          'treatment_objectives',
          'current_notes',
          'activation_notes',
          'progress_notes',
          'complications',
          'retention_plan'
        ])
      }
    ]),
    formFields: Object.freeze(ORTHODONTICS_FIELDS)
  },
  {
    key: 'endodoncia',
    label: 'Endodoncia',
    icon: CpuChipIcon,
    roles: Object.freeze(['administrador', 'odontologo']),
    dateField: 'treatment_completion_date',
    statusField: 'treatment_status',
    statusLabels: ENDODONTICS_STATUS_LABELS,
    statusVariants: Object.freeze({
      in_progress: 'warning',
      completed: 'success',
      failed: 'error',
      retreatment: 'info'
    }),
    progressStatuses: Object.freeze(['in_progress']),
    completedStatuses: Object.freeze(['completed']),
    summary: Object.freeze([
      'dental_piece_id',
      'tooth_number',
      'canal_count',
      'obturation_materials'
    ]),
    detailSections: Object.freeze([
      {
        id: 'diagnosis',
        label: 'Diagnóstico',
        fields: Object.freeze([
          'procedure_id',
          'dental_piece_id',
          'tooth_number',
          'canal_count',
          'working_length_method',
          'pulp_diagnosis',
          'periapical_diagnosis',
          'anesthesia_used'
        ])
      },
      {
        id: 'procedure',
        label: 'Procedimiento',
        fields: Object.freeze([
          'treatment_plan',
          'obturation_technique',
          'obturation_materials',
          'treatment_completion_date'
        ])
      },
      {
        id: 'notes',
        label: 'Notas',
        fields: Object.freeze([
          'canal_preparation_notes',
          'irrigation_protocol',
          'complications',
          'follow_up_notes'
        ])
      }
    ]),
    formFields: Object.freeze(ENDODONTICS_FIELDS)
  },
  {
    key: 'rehabilitacion',
    label: 'Rehabilitación',
    icon: CogIcon,
    roles: Object.freeze(['administrador', 'odontologo']),
    dateField: 'cementation_date',
    statusField: 'status',
    statusLabels: REHABILITATION_STATUS_LABELS,
    statusVariants: Object.freeze({
      impression: 'info',
      laboratory: 'info',
      try_in: 'warning',
      delivered: 'success',
      cemented: 'success',
      failed: 'error'
    }),
    progressStatuses: Object.freeze(['impression', 'laboratory', 'try_in']),
    completedStatuses: Object.freeze(['delivered', 'cemented']),
    summary: Object.freeze([
      'dental_piece_id',
      'prosthesis_type',
      'material_type',
      'laboratory_name'
    ]),
    detailSections: Object.freeze([
      {
        id: 'restoration',
        label: 'Restauración',
        fields: Object.freeze([
          'procedure_id',
          'dental_piece_id',
          'prosthesis_type',
          'material_type',
          'shade_selection'
        ])
      },
      {
        id: 'laboratory',
        label: 'Laboratorio',
        fields: Object.freeze(['laboratory_name', 'laboratory_contact'])
      },
      {
        id: 'timeline',
        label: 'Fechas',
        fields: Object.freeze([
          'impression_date',
          'try_in_date',
          'delivery_date',
          'cementation_date'
        ])
      },
      {
        id: 'notes',
        label: 'Notas',
        fields: Object.freeze([
          'impression_notes',
          'try_in_notes',
          'adjustment_notes',
          'cementation_notes',
          'patient_satisfaction',
          'follow_up_notes'
        ])
      }
    ]),
    formFields: Object.freeze(REHABILITATION_FIELDS)
  },
  {
    key: 'cirugia_oral',
    label: 'Cirugía Oral',
    icon: ScissorsIcon,
    roles: Object.freeze(['administrador', 'odontologo']),
    dateField: 'surgery_date',
    statusField: 'status',
    statusLabels: ORAL_SURGERY_STATUS_LABELS,
    statusVariants: Object.freeze({
      scheduled: 'info',
      in_progress: 'warning',
      completed: 'success',
      cancelled: 'neutral',
      complications: 'error'
    }),
    progressStatuses: Object.freeze(['scheduled', 'in_progress']),
    completedStatuses: Object.freeze(['completed']),
    summary: Object.freeze(['procedure_type', 'surgery_site', 'anesthesia_type', 'suture_count']),
    detailSections: Object.freeze([
      {
        id: 'procedure',
        label: 'Procedimiento',
        fields: Object.freeze([
          'procedure_id',
          'procedure_type',
          'surgery_site',
          'surgery_date',
          'surgical_technique',
          'anesthesia_type',
          'surgery_duration_minutes',
          'hemostasis_method'
        ])
      },
      {
        id: 'sutures',
        label: 'Suturas',
        fields: Object.freeze(['sutures_used', 'suture_count'])
      },
      {
        id: 'follow-up',
        label: 'Seguimiento',
        fields: Object.freeze(['follow_up_date'])
      },
      {
        id: 'notes',
        label: 'Notas',
        fields: Object.freeze([
          'surgical_notes',
          'post_surgical_instructions',
          'medications_prescribed',
          'recovery_notes',
          'complications',
          'follow_up_notes'
        ])
      }
    ]),
    formFields: Object.freeze(ORAL_SURGERY_FIELDS)
  }
])

const DEFINITIONS_BY_KEY = new Map(
  SPECIALTY_RECORD_DEFINITIONS.map(definition => [definition.key, definition])
)

const FIELDS_BY_KEY = new Map(
  SPECIALTY_RECORD_DEFINITIONS.map(definition => [
    definition.key,
    new Map(
      [...definition.formFields, ...(definition.extraFields ?? [])].map(field => [field.key, field])
    )
  ])
)

export function getSpecialtyDefinition(specialty) {
  return DEFINITIONS_BY_KEY.get(specialty) ?? null
}

export function definitionsForRole(role) {
  return SPECIALTY_RECORD_DEFINITIONS.filter(definition => definition.roles.includes(role))
}

export function statusLabel(specialty, status) {
  const definition = getSpecialtyDefinition(specialty)

  if (!definition || !status) return '—'

  return definition.statusLabels[status] ?? String(status)
}

export function statusVariant(specialty, status) {
  const definition = getSpecialtyDefinition(specialty)

  if (!definition || !status) return 'neutral'

  return definition.statusVariants[status] ?? 'neutral'
}

export function countInGroup(records) {
  return Array.isArray(records) ? records.length : 0
}

export function latestRecordDate(records) {
  if (!Array.isArray(records)) return null

  return records.reduce((latest, record) => {
    const stamp = record?.created_at

    if (!stamp) return latest
    if (!latest) return stamp

    return Date.parse(stamp) > Date.parse(latest) ? stamp : latest
  }, null)
}

/**
 * Reads the calendar date from an API date or timestamp. Date casts arrive as
 * UTC midnight (`2026-10-20T00:00:00.000000Z`); converting that to a local
 * Date would render the previous day west of Greenwich.
 */
export function formatRecordDate(value) {
  if (value instanceof Date) {
    if (Number.isNaN(value.getTime())) return '—'
    const day = String(value.getDate()).padStart(2, '0')

    return `${day} ${MONTH_LABELS[value.getMonth()]} ${value.getFullYear()}`
  }

  const match = String(value ?? '').match(DATE_KEY)

  if (!match) return '—'

  return `${match[3]} ${MONTH_LABELS[Number(match[2]) - 1]} ${match[1]}`
}

export function formatRecordDateTime(value) {
  const date = formatRecordDate(value)

  if (date === '—') return date

  const time = String(value).match(TIME_KEY)

  return time ? `${date} ${time[1]}` : date
}

export function dateInputValue(value) {
  const match = String(value ?? '').match(DATE_KEY)

  return match ? `${match[1]}-${match[2]}-${match[3]}` : ''
}

function resolveField(definition, key) {
  return FIELDS_BY_KEY.get(definition.key)?.get(key) ?? null
}

function formatFieldValue(field, value, record) {
  if (field.type === 'dental-piece') {
    const piece = record?.dentalPiece

    if (!piece) return ''

    return [piece.fdi_number ? `#${piece.fdi_number}` : '', piece.name ?? '']
      .filter(Boolean)
      .join(' · ')
  }

  if (field.type === 'procedure') {
    const procedure = record?.procedure

    if (!procedure) return ''

    return [procedure.code ?? '', procedure.name ?? ''].filter(Boolean).join(' · ')
  }

  if (value === null || value === undefined) return ''

  if (field.type === 'date') {
    const formatted = formatRecordDate(value)

    return formatted === '—' ? '' : formatted
  }

  if (field.type === 'select') {
    const option = field.options?.find(entry => entry.value === String(value))

    return option?.label ?? String(value)
  }

  if (typeof value === 'object') return ''

  return String(value).trim()
}

const entries = (definition, keys, record) =>
  keys
    .map(key => resolveField(definition, key))
    .filter(Boolean)
    .map(field => ({
      key: field.key,
      label: field.label,
      value: formatFieldValue(field, record[field.key], record)
    }))
    .filter(entry => entry.value !== '')

export function summaryFields(definition, record) {
  if (!definition || !record) return []

  return entries(definition, definition.summary, record)
}

export function detailSections(definition, record) {
  if (!definition || !record) return []

  return definition.detailSections
    .map(section => ({
      id: section.id,
      label: section.label,
      fields: entries(definition, section.fields, record)
    }))
    .filter(section => section.fields.length > 0)
}

export function primaryDate(definition, record) {
  if (!definition || !record) return null

  const field = resolveField(definition, definition.dateField)

  if (!field) return null

  const value = formatFieldValue(field, record[field.key], record)

  return value ? { key: field.key, label: field.label, value } : null
}

export function formFields(definition, section) {
  if (!definition) return []

  if (!section) return definition.formFields

  return definition.formFields.filter(field => (field.section ?? 'clinical') === section)
}
