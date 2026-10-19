export const SPECIALTY_LABELS = {
  general: 'Odontología General',
  orthodontics: 'Ortodoncia',
  endodontics: 'Endodoncia',
  periodontics: 'Periodoncia',
  oral_surgery: 'Cirugía Oral',
  pediatric: 'Odontopediatría',
  prosthodontics: 'Prótesis Dental',
  cosmetic: 'Odontología Estética',
  implantology: 'Implantología',
  dental_technician: 'Técnico Dental',
  assistant: 'Asistente',
  finance: 'Finanzas'
}

const SELECTABLE_SPECIALTY_CODES = [
  'general',
  'implantology',
  'orthodontics',
  'endodontics',
  'periodontics',
  'oral_surgery',
  'pediatric',
  'prosthodontics',
  'cosmetic'
]

export const SPECIALTY_OPTIONS = SELECTABLE_SPECIALTY_CODES.map(value => ({
  value,
  label: SPECIALTY_LABELS[value]
}))

export const CLINICAL_ROLES = ['odontologo', 'implantologo', 'tecnico_dental', 'asistente']

export const ROLE_LABELS = {
  odontologo: 'Odontólogo',
  implantologo: 'Implantólogo',
  tecnico_dental: 'Técnico Dental',
  asistente: 'Asistente'
}
