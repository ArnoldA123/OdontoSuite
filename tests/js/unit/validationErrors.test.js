import { describe, it, expect } from 'vitest'

// S3 — the Feedback axis canon: a 422 lands on its field or in its dialog with
// the reason the API published. Controllers send a generic `message` and put
// the rule in `errors`, so the helper has to prefer the rule.
import {
  firstMessage,
  normalizeValidationErrors,
  isGenericValidationMessage,
  pickValidationMessage,
  collapseFieldKey,
  mapFieldErrors
} from '../../../resources/js/composables/validationErrors.js'

describe('validationErrors', () => {
  it('collapses an array entry to its first message and drops empties', () => {
    expect(
      normalizeValidationErrors({
        status: ['Primer motivo.', 'Segundo motivo.'],
        items: 'Un solo motivo.',
        empty: []
      })
    ).toEqual({ status: 'Primer motivo.', items: 'Un solo motivo.' })
  })

  it('replaces a generic message with the reason the API published in errors', () => {
    expect(
      pickValidationMessage(
        {
          message: 'Error de validación',
          errors: {
            status: ['Solo se puede modificar un presupuesto en borrador, enviado o visto.']
          }
        },
        'No pudimos guardar el presupuesto.'
      )
    ).toBe('Solo se puede modificar un presupuesto en borrador, enviado o visto.')
  })

  it('keeps a specific server message even when errors are present', () => {
    expect(
      pickValidationMessage({
        message: 'No se puede completar el plan: 2 procedimiento(s) siguen sin ejecutar.',
        errors: { status: ['otra cosa'] }
      })
    ).toBe('No se puede completar el plan: 2 procedimiento(s) siguen sin ejecutar.')
  })

  it('falls back to the first error, then to the caller fallback', () => {
    expect(pickValidationMessage({ errors: { name: ['Nombre repetido.'] } }, 'fallback')).toBe(
      'Nombre repetido.'
    )
    expect(pickValidationMessage(null, 'fallback')).toBe('fallback')
  })

  it('recognizes the generic messages case-insensitively', () => {
    expect(isGenericValidationMessage('  ERROR DE VALIDACIÓN ')).toBe(true)
    expect(isGenericValidationMessage('The given data was invalid.')).toBe(true)
    expect(isGenericValidationMessage('Motivo concreto.')).toBe(false)
    expect(firstMessage(['uno', 'dos'])).toBe('uno')
  })

  it('collapses a nested server key onto the declared field', () => {
    expect(
      mapFieldErrors(
        {
          'items.0.description': ['Falta la descripción.'],
          patient_id: 'Selecciona un paciente.',
          status: ['Estado inválido.']
        },
        ['items', 'patient_id']
      )
    ).toEqual({
      items: 'Falta la descripción.',
      patient_id: 'Selecciona un paciente.'
    })

    expect(collapseFieldKey('items.0.description', ['items', 'patient_id'])).toBe('items')
    expect(collapseFieldKey('patient_id', ['items', 'patient_id'])).toBe('patient_id')
    expect(collapseFieldKey('status', ['items'])).toBe('status')
  })
})
