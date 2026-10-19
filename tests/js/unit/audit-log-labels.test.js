import { describe, it, expect } from 'vitest'

import { useAuditLogs } from '../../../resources/js/composables/useAuditLogs'

// Appointment-type audit vocabulary (2026-10-19 audit, T2 follow-on). The
// controller now records the catalog flags in its audit snapshots, so the
// shared summary must render them with a readable label and Sí/No values
// instead of the raw snake_case key and `true` / `false`.
const { getChangesSummary } = useAuditLogs()

const summary = (oldValues, newValues) => getChangesSummary({ old_values: oldValues, new_values: newValues })

describe('useAuditLogs.getChangesSummary appointment-type fields', () => {
  it('labels and localizes the catalog flags', () => {
    const changes = summary(
      { requires_confirmation: false, requires_materials: true, is_consultation_mode: false },
      { requires_confirmation: true, requires_materials: false, is_consultation_mode: true }
    )

    expect(changes.requires_confirmation).toEqual({
      field: 'Requiere confirmación',
      old: 'No',
      new: 'Sí'
    })
    expect(changes.requires_materials).toEqual({
      field: 'Requiere materiales',
      old: 'Sí',
      new: 'No'
    })
    expect(changes.is_consultation_mode).toEqual({
      field: 'Modo consulta',
      old: 'No',
      new: 'Sí'
    })
  })

  it('labels the default procedure reference', () => {
    expect(
      summary(
        { default_procedure_catalog_id: null },
        { default_procedure_catalog_id: 12 }
      ).default_procedure_catalog_id
    ).toEqual({
      field: 'Procedimiento por defecto',
      old: '(vacío)',
      new: '12'
    })
  })

  it('keeps the existing price and duration formatting', () => {
    const changes = summary(
      { price: 100, default_duration_minutes: 30 },
      { price: 150, default_duration_minutes: 45 }
    )

    expect(changes.price).toEqual({ field: 'Precio', old: 'S/ 100.00', new: 'S/ 150.00' })
    expect(changes.default_duration_minutes).toEqual({
      field: 'Duración (minutos)',
      old: '30 minutos',
      new: '45 minutos'
    })
  })
})
