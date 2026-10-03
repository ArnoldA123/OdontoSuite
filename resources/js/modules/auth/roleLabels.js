const ROLE_LABELS = Object.freeze({
  administrador: 'Administrador',
  recepcionista: 'Recepcionista',
  odontologo: 'Odontólogo',
  implantologo: 'Implantólogo',
  tecnico_dental: 'Técnico dental',
  asistente: 'Asistente',
  finanzas: 'Finanzas'
})

export function roleLabel(role) {
  if (!role) return ''
  return ROLE_LABELS[role] || role
}
