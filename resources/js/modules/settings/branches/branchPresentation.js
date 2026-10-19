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

export const formatBranchDate = value => {
  if (!value) return 'No registrada'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'No registrada'

  return date.toLocaleDateString('es-PE', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  })
}
