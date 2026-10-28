import { describe, it, expect } from 'vitest'
import {
  PROCEDURE_CODE_MAX_LENGTH,
  duplicateProcedureCode
} from '../../../resources/js/modules/procedure-catalog/procedureCode'

// #434 — duplicating a procedure proposed `${code}-COPIA`. A code close to the
// column limit (varchar(20)) produced a 21+ character proposal and the API
// answered 500. The proposal must always fit the column it is copied into.
describe('procedure code copy (#434)', () => {
  it('keeps the column limit as the single contract', () => {
    expect(PROCEDURE_CODE_MAX_LENGTH).toBe(20)
  })

  it('appends COPIA when the original leaves room', () => {
    expect(duplicateProcedureCode('ENDO-001')).toBe('ENDO-001-COPIA')
  })

  it('truncates the original so the proposal fits in the column', () => {
    const proposal = duplicateProcedureCode('AUDIT-U8-REST-COMP-SIT')
    expect(proposal.length).toBeLessThanOrEqual(20)
    expect(proposal.endsWith('-COPIA')).toBe(true)
    expect(proposal).toBe('AUDIT-U8-REST-COPIA')
  })

  it('handles a full-length original and trailing separators', () => {
    expect(duplicateProcedureCode('12345678901234567890')).toBe('12345678901234-COPIA')
    expect(duplicateProcedureCode('REST-COMP-')).toBe('REST-COMP-COPIA')
  })

  it('does not start with a separator when the original is empty', () => {
    expect(duplicateProcedureCode('')).toBe('COPIA')
    expect(duplicateProcedureCode(null)).toBe('COPIA')
  })
})
