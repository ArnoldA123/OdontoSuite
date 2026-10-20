export const STATUS_FILTER_OPTIONS = Object.freeze([
  { value: '', label: 'Todos los estados' },
  { value: 'true', label: 'Activas' },
  { value: 'false', label: 'Inactivas' }
])

export const STATUS_FORM_OPTIONS = Object.freeze([
  { value: 'active', label: 'Activa' },
  { value: 'inactive', label: 'Inactiva' }
])

export const CODE_FORMAT = /^[a-z0-9]+(?:_[a-z0-9]+)*$/

export const CODE_HINT = 'Identificador corto en minúsculas. Por ejemplo: cirugia_oral.'

export const CODE_EDIT_HINT =
  'El código no puede modificarse: el catálogo de procedimientos y la importación CSV lo referencian.'

export const DELETE_CONTRACT_NOTICE =
  'Las especialidades no se eliminan: se desactivan. El catálogo de procedimientos y los profesionales las referencian.'

export function getStatusLabel(isActive) {
  return isActive ? 'Activa' : 'Inactiva'
}

export function getStatusVariant(isActive) {
  return isActive ? 'success' : 'neutral'
}

function countsAs(value) {
  const numeric = Number(value ?? 0)

  if (!Number.isFinite(numeric) || numeric <= 0) return 0

  return Math.trunc(numeric)
}

export function countProcedures(specialty) {
  return countsAs(specialty?.procedures_count)
}

export function countProfessionals(specialty) {
  return countsAs(specialty?.professionals_count)
}

export function formatCount(count, singular, plural) {
  return `${count} ${count === 1 ? singular : plural}`
}

export function getUsageSummary(specialty) {
  const procedures = formatCount(countProcedures(specialty), 'procedimiento', 'procedimientos')
  const professionals = formatCount(countProfessionals(specialty), 'profesional', 'profesionales')

  return `${procedures} · ${professionals}`
}
