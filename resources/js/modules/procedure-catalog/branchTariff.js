import { formatCurrency, formatDate } from '../../composables/useFormatters'

export const TARIFF_STATUSES = ['vigente', 'futuro', 'vencido', 'inactivo']

export const TARIFF_STATUS_LABELS = {
  vigente: 'Vigente',
  futuro: 'Futuro',
  vencido: 'Vencido',
  inactivo: 'Inactivo'
}

export const TARIFF_STATUS_VARIANTS = {
  vigente: 'success',
  futuro: 'info',
  vencido: 'warning',
  inactivo: 'neutral'
}

const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})/

export const dateOnly = value => {
  if (!value) return ''

  if (typeof value === 'string') {
    const match = value.match(ISO_DATE)
    if (match) return `${match[1]}-${match[2]}-${match[3]}`
  }

  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const isInactive = row => row?.is_active === false || row?.is_active === 0 || row?.is_active === '0'

export const tariffStatus = (row, referenceDate = new Date()) => {
  if (!row || isInactive(row)) return 'inactivo'

  const from = dateOnly(row?.valid_from)
  const to = dateOnly(row?.valid_to)
  const reference = dateOnly(referenceDate)

  if (from && reference && from > reference) return 'futuro'
  if (to && reference && to < reference) return 'vencido'

  return 'vigente'
}

export const tariffStatusLabel = status => TARIFF_STATUS_LABELS[status] ?? status ?? ''

export const tariffStatusVariant = status => TARIFF_STATUS_VARIANTS[status] ?? 'neutral'

export const formatTariffPrice = value => formatCurrency(value)

export const formatTariffDate = value => formatDate(dateOnly(value))

export const formatValidityWindow = (validFrom, validTo) => {
  const from = formatTariffDate(validFrom)
  const to = formatTariffDate(validTo)

  if (from && to) return `${from} — ${to}`
  if (from) return `Desde ${from}`
  if (to) return `Hasta ${to}`

  return 'Sin límite'
}
