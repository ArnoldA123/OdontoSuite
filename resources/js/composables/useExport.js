import { ref } from 'vue'

export function useExport() {
  const exporting = ref(false)

  /**
   * Export data to Excel format
   */
  const exportToExcel = async (data, filename = 'report') => {
    try {
      exporting.value = true

      // Create workbook
      const XLSX = await import('xlsx')
      const ws = XLSX.utils.json_to_sheet(data)
      const wb = XLSX.utils.book_new()
      XLSX.utils.book_append_sheet(wb, ws, 'Reporte')

      // Generate and download
      XLSX.writeFile(wb, `${filename}_${new Date().toISOString().split('T')[0]}.xlsx`)

      return true
    } catch (error) {
      console.error('Error exporting to Excel:', error)
      throw error
    } finally {
      exporting.value = false
    }
  }

  /**
   * Export data to CSV format
   */
  const exportToCsv = (data, filename = 'report') => {
    try {
      if (!data || data.length === 0) {
        throw new Error('No data to export')
      }

      // Get headers from first object
      const headers = Object.keys(data[0])

      // Create CSV content
      const csvContent = [
        headers.join(','),
        ...data.map(row =>
          headers.map(header => {
            const value = row[header]
            // Escape values that contain commas or quotes
            if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
              return `"${value.replace(/"/g, '""')}"`
            }
            return value
          }).join(',')
        )
      ].join('\n')

      // Create and download file
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
      const link = document.createElement('a')
      const url = URL.createObjectURL(blob)
      link.setAttribute('href', url)
      link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.csv`)
      link.style.visibility = 'hidden'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)

      return true
    } catch (error) {
      console.error('Error exporting to CSV:', error)
      throw error
    }
  }

  /**
   * Export data to PDF format
   */
  const exportToPdf = async (data, filename = 'report', title = 'Reporte') => {
    try {
      exporting.value = true

      const { jsPDF } = await import('jspdf')
      const doc = new jsPDF()

      // Add title
      doc.setFontSize(16)
      doc.text(title, 20, 20)

      // Add date
      doc.setFontSize(10)
      doc.text(`Generado el: ${new Date().toLocaleDateString('es-ES')}`, 20, 30)

      // Add data as table
      if (data && data.length > 0) {
        const headers = Object.keys(data[0])
        const rows = data.map(row => Object.values(row))

        // Simple table implementation
        let y = 50
        const pageHeight = doc.internal.pageSize.height
        const lineHeight = 7

        // Headers
        doc.setFontSize(10)
        doc.setFont(undefined, 'bold')
        headers.forEach((header, index) => {
          doc.text(header, 20 + (index * 40), y)
        })

        y += lineHeight

        // Data rows
        doc.setFont(undefined, 'normal')
        rows.forEach((row, rowIndex) => {
          if (y > pageHeight - 20) {
            doc.addPage()
            y = 20
          }

          row.forEach((cell, cellIndex) => {
            const cellValue = String(cell || '').substring(0, 15) // Truncate long values
            doc.text(cellValue, 20 + (cellIndex * 40), y)
          })

          y += lineHeight
        })
      }

      // Save the PDF
      doc.save(`${filename}_${new Date().toISOString().split('T')[0]}.pdf`)

      return true
    } catch (error) {
      console.error('Error exporting to PDF:', error)
      throw error
    } finally {
      exporting.value = false
    }
  }

  /**
   * Export chart as image
   */
  const exportChartAsImage = async (chartElement, filename = 'chart') => {
    try {
      exporting.value = true

      const { default: html2canvas } = await import('html2canvas')

      const canvas = await html2canvas(chartElement, {
        backgroundColor: '#ffffff',
        scale: 2
      })

      // Convert to blob and download
      canvas.toBlob((blob) => {
        const link = document.createElement('a')
        const url = URL.createObjectURL(blob)
        link.setAttribute('href', url)
        link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.png`)
        link.style.visibility = 'hidden'
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        URL.revokeObjectURL(url)
      })

      return true
    } catch (error) {
      console.error('Error exporting chart:', error)
      throw error
    } finally {
      exporting.value = false
    }
  }

  /**
   * Format data for export
   */
  const formatDataForExport = (data, columns) => {
    if (!data || !columns) return data

    return data.map(row => {
      const formattedRow = {}
      columns.forEach(column => {
        const value = row[column.key]
        formattedRow[column.label] = value
      })
      return formattedRow
    })
  }

  return {
    exporting,
    exportToExcel,
    exportToCsv,
    exportToPdf,
    exportChartAsImage,
    formatDataForExport
  }
}
