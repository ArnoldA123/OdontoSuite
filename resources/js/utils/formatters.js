/**
 * Utilidades para formatear datos
 */

/**
 * Formatear moneda en soles peruanos
 * @param {number} amount - Cantidad a formatear
 * @returns {string} - Cantidad formateada como moneda
 */
export const formatCurrency = amount => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount || 0)
}

/**
 * Formatear fecha completa con hora
 * @param {string|Date} date - Fecha a formatear
 * @returns {string} - Fecha formateada
 */
export const formatDate = date => {
  return new Intl.DateTimeFormat('es-PE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date(date))
}

/**
 * Formatear fecha corta (solo fecha)
 * @param {string|Date} date - Fecha a formatear
 * @returns {string} - Fecha formateada
 */
export const formatDateShort = date => {
  return new Intl.DateTimeFormat('es-PE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).format(new Date(date))
}

/**
 * Formatear una fecha sin hora evitando el desfase de zona horaria.
 * Las fechas `YYYY-MM-DD` se construyen con sus componentes locales; un ISO
 * datetime usa su prefijo `YYYY-MM-DD` sin pasar por `new Date('YYYY-MM-DD')`.
 * @param {string|Date} value - Fecha a formatear
 * @returns {string} - Fecha formateada o cadena vacía si falta o es inválida
 */
export const formatDateOnly = value => {
  if (value === null || value === undefined || value === '') return ''

  let date

  if (value instanceof Date) {
    date = value
  } else if (typeof value === 'string') {
    const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})/)

    if (dateOnly) {
      const year = Number(dateOnly[1])
      const month = Number(dateOnly[2])
      const day = Number(dateOnly[3])
      date = new Date(year, month - 1, day)
      if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return ''
      }
    } else {
      date = new Date(value)
    }
  } else {
    return ''
  }

  if (Number.isNaN(date.getTime())) return ''

  return date.toLocaleDateString('es-ES')
}

/**
 * Formatear hora
 * @param {string|Date} date - Fecha a formatear
 * @returns {string} - Hora formateada
 */
export const formatTime = date => {
  return new Intl.DateTimeFormat('es-PE', {
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date(date))
}

/**
 * Formatear nombre completo
 * @param {string} firstName - Nombre
 * @param {string} lastName - Apellido
 * @returns {string} - Nombre completo
 */
export const formatFullName = (firstName, lastName) => {
  return `${firstName || ''} ${lastName || ''}`.trim()
}

/**
 * Formatear número de documento
 * @param {string} documentNumber - Número de documento
 * @returns {string} - Documento formateado
 */
export const formatDocumentNumber = documentNumber => {
  if (!documentNumber) return 'N/A'
  return documentNumber.toString().replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3')
}

/**
 * Formatear teléfono
 * @param {string} phone - Número de teléfono
 * @returns {string} - Teléfono formateado
 */
export const formatPhone = phone => {
  if (!phone) return 'N/A'
  const cleaned = phone.replace(/\D/g, '')
  if (cleaned.length === 9) {
    return cleaned.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3')
  }
  return phone
}

/**
 * Formatear porcentaje
 * @param {number} value - Valor a formatear
 * @param {number} decimals - Número de decimales
 * @returns {string} - Porcentaje formateado
 */
export const formatPercentage = (value, decimals = 2) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'percent',
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  }).format(value / 100)
}

/**
 * Formatear número con separadores de miles
 * @param {number} value - Valor a formatear
 * @returns {string} - Número formateado
 */
export const formatNumber = value => {
  return new Intl.NumberFormat('es-PE').format(value || 0)
}
