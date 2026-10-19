const CSV_HEADER = ['seccion', 'codigo', 'nombre', 'usos', 'cantidad', 'ingresos']
const BOM = '\uFEFF'
const MAX_TEXT_LENGTH = 200

const escapeText = value => {
  const text = String(value ?? '').slice(0, MAX_TEXT_LENGTH)
  const guarded = /^[=+\-@\t\r]/.test(text) ? `'${text}` : text
  return /[",\r\n]/.test(guarded) ? `"${guarded.replace(/"/g, '""')}"` : guarded
}

const csvCount = value => {
  const number = Number(value)
  return Number.isFinite(number) ? String(number) : '0'
}

const csvMoney = value => {
  const number = Number(value)
  return (Number.isFinite(number) ? number : 0).toFixed(2)
}

export const buildProcedureStatsCsv = ({ topProcedures = [], bySpecialty = [] } = {}) => {
  const rows = [CSV_HEADER]

  topProcedures.forEach(row => {
    rows.push([
      'procedimiento',
      escapeText(row.code),
      escapeText(row.name),
      csvCount(row.usage_count),
      csvCount(row.total_quantity),
      csvMoney(row.total_revenue)
    ])
  })

  bySpecialty.forEach(row => {
    rows.push([
      'especialidad',
      escapeText(row.specialty_code),
      escapeText(row.specialty_name || 'Sin especialidad'),
      csvCount(row.usage_count),
      '',
      csvMoney(row.total_revenue)
    ])
  })

  return `${BOM}${rows.map(row => row.join(',')).join('\r\n')}\r\n`
}

export const buildProcedureStatsFilename = (from, to) => `estadisticas-catalogo-${from}_${to}.csv`

export const downloadCsvFile = (filename, content) => {
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = filename
  link.rel = 'noopener'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
