/**
 * Shared status vocabulary for environments (dental chairs).
 * Single source of truth consumed by the list page and (T4) the detail page:
 * `active | inactive | maintenance` maps to a StatusBadge variant + a Spanish label.
 */
export const STATUS_VARIANT = {
  active: 'success',
  inactive: 'neutral',
  maintenance: 'warning'
}

export const STATUS_LABEL = {
  active: 'Activo',
  inactive: 'Inactivo',
  maintenance: 'Mantenimiento'
}

export const getStatusVariant = status => STATUS_VARIANT[status] || 'neutral'

export const getStatusText = status => STATUS_LABEL[status] || status
