import { describe, it, expect } from 'vitest'
import { effectScope } from 'vue'

// S3 — the composable bridge the canon names (`useFieldValidation`): the 422
// entries paint the fields the form declares, instead of only a toast.
import { useFieldValidation } from '../../../resources/js/composables/useFieldValidation.js'

const run = (form, rules) => {
  const scope = effectScope()
  const validation = scope.run(() => useFieldValidation(form, rules))

  return { validation, scope }
}

describe('useFieldValidation.applyServerErrors', () => {
  it('paints the API message on its declared field and clears its success flag', () => {
    const { validation, scope } = run({ name: '' }, { name: [() => null] })

    const mapped = validation.applyServerErrors({
      name: ['Ya existe un paciente con ese documento.']
    })

    expect(mapped).toEqual({ name: 'Ya existe un paciente con ese documento.' })
    expect(validation.errors.name).toBe('Ya existe un paciente con ese documento.')
    expect(validation.successes.name).toBe(false)

    scope.stop()
  })

  it('ignores a key no declared rule can paint and collapses a nested key', () => {
    const { validation, scope } = run({ items: [] }, { items: [() => null] })

    validation.applyServerErrors({
      'items.0.description': ['Falta la descripción.'],
      status: ['Estado inválido.']
    })

    expect(validation.errors.items).toBe('Falta la descripción.')
    expect(validation.errors.status).toBeUndefined()

    scope.stop()
  })
})
