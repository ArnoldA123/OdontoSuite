import { describe, expect, it } from 'vitest'

import { isLedgerRetained } from '../../../tests/e2e/audit/u9/refusals.js'

// #501 S6: the U9 teardown may not call an externally-referenced payment method
// an undeclared leftover. The API refuses the delete with 409 and names the
// transactions; the ledger is immutable (ADR-0015), so the row is declared.
describe('isLedgerRetained reads the ledger refusal (#501 S6)', () => {
  it('accepts the 409 whose message names the transactions', () => {
    expect(
      isLedgerRetained({
        status: 409,
        body: {
          message:
            'No se puede eliminar: el metodo tiene 2 transaccion(es) registrada(s). Desactivalo en su lugar.'
        }
      })
    ).toBe(true)
  })

  it('refuses every other status, including a 409 without the ledger reason', () => {
    expect(isLedgerRetained({ status: 409, body: { message: 'El metodo esta en uso.' } })).toBe(
      false
    )
    expect(
      isLedgerRetained({
        status: 403,
        body: { message: 'el metodo tiene 1 transaccion(es) registrada(s)' }
      })
    ).toBe(false)
    expect(
      isLedgerRetained({ status: 500, body: { error: 'transaccion(es) registrada(s)' } })
    ).toBe(false)
    expect(isLedgerRetained({ status: 200, body: { message: 'Metodo de pago eliminado' } })).toBe(
      false
    )
  })

  it('refuses a response without a status or a body', () => {
    expect(isLedgerRetained({})).toBe(false)
    expect(isLedgerRetained({ status: 409 })).toBe(false)
    expect(isLedgerRetained(null)).toBe(false)
    expect(isLedgerRetained(undefined)).toBe(false)
  })
})
