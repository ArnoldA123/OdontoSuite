export const SPECIALTY_LABELS = {
  general: 'Odontología General',
  orthodontics: 'Ortodoncia',
  endodontics: 'Endodoncia',
  periodontics: 'Periodoncia',
  oral_surgery: 'Cirugía Oral',
  pediatric: 'Odontopediatría',
  prosthodontics: 'Prótesis Dental',
  cosmetic: 'Odontología Estética'
}

export const SPECIALTY_OPTIONS = Object.entries(SPECIALTY_LABELS).map(([value, label]) => ({
  value,
  label
}))

export const CLINICAL_ROLES = ['odontologo', 'implantologo', 'tecnico_dental', 'asistente']

export const ROLE_LABELS = {
  odontologo: 'Odontólogo',
  implantologo: 'Implantólogo',
  tecnico_dental: 'Técnico Dental',
  asistente: 'Asistente'
}
