// Plan #13 (issue #372, U9) — the refusals a teardown reads as a declared state.
//
// `DELETE /api/payment-methods/{id}` answers 409 and names the transactions when
// the ledger already references the row. The ledger is immutable (ADR-0015), so
// the session cannot delete what it created: the row is a declared residue with
// its reason, not an undeclared leftover (#501 S6).
//
// The predicate is pure and imports nothing, so a vitest unit pins the
// classification without Playwright.

/** Whether the API refused the delete because the transaction ledger owns it. */
export const isLedgerRetained = response =>
  response?.status === 409 && /transaccion/i.test(String(response?.body?.message ?? ''))
