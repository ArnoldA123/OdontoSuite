/**
 * #434 — the procedure code is `varchar(20)`; the API declares the same limit.
 * The duplicate action must propose a code that fits the column instead of
 * sending a 21-character code that the database answers with a 500.
 */
export const PROCEDURE_CODE_MAX_LENGTH = 20

const COPY_SUFFIX = '-COPIA'

export const duplicateProcedureCode = code => {
  const base = String(code ?? '')
    .trim()
    .replace(/-+$/, '')

  if (base === '') return 'COPIA'

  const room = PROCEDURE_CODE_MAX_LENGTH - COPY_SUFFIX.length
  const head = base.slice(0, room).replace(/-+$/, '')

  return head === '' ? 'COPIA' : `${head}${COPY_SUFFIX}`
}
