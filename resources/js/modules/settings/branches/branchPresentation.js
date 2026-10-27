import { formatDate } from '../../../composables/useFormatters'

export const getBranchStatusVariant = isActive => (isActive ? 'success' : 'neutral')

export const getBranchStatusLabel = isActive => (isActive ? 'Activa' : 'Inactiva')

export const formatBranchLocation = branch => {
  if (!branch?.city) return 'Sin ciudad registrada'

  return branch.state ? `${branch.city}, ${branch.state}` : branch.city
}

export const formatBranchCoordinates = branch => {
  if (!branch?.latitude || !branch?.longitude) return 'No registradas'

  return `${branch.latitude}, ${branch.longitude}`
}

export const formatBranchDate = value =>
  formatDate(value, { day: '2-digit', month: 'short', year: 'numeric' }) || 'No registrada'
