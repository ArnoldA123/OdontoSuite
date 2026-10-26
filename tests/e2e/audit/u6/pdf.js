// Plan #13 (issue #369, U6) — the PDF reader of the budget walkthrough.
//
// The machine has no `pdftotext`, `mutool`, `qpdf` or Python `pypdf` (U6 L6), so
// the bytes the API answers are fetched through the SPA's own authenticated door,
// written under the evidence directory, and parsed here: the Flate streams are
// inflated with `zlib` and the text operators are read in document order. A stream
// that does not inflate, or a money row that cannot be located, is a reason —
// never an invented comparison and never a fallback to the JSON.

import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname } from 'node:path'
import { inflateRawSync, inflateSync } from 'node:zlib'

import { BASE_URL } from '../harness.js'

import { cents, evidencePath, parseCurrency } from './helpers.js'

// --- PDF: bytes from the API, text from zlib --------------------------------

/**
 * Fetches the PDF through the SPA's own authenticated door and keeps the bytes.
 *
 * `page.evaluate` runs the fetch inside the browser so the request carries the
 * same origin and headers the download button uses; the byte array travels back
 * and is written under the evidence directory, where the parser reads it.
 */
export const fetchQuotationPdf = async (page, session, id, options = {}) => {
  const url = `${BASE_URL}/api/quotations/${id}/pdf`
  const relPath = options.relPath ?? `pdf/quotation-${id}.pdf`

  try {
    const fetched = await page.evaluate(
      async ({ target, token }) => {
        const response = await fetch(target, {
          headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf' }
        })
        const buffer = await response.arrayBuffer()

        return {
          status: response.status,
          contentType: response.headers.get('content-type'),
          disposition: response.headers.get('content-disposition'),
          bytes: Array.from(new Uint8Array(buffer))
        }
      },
      { target: url, token: session.token }
    )

    const buffer = Buffer.from(fetched.bytes)
    const absolute = evidencePath(relPath)
    mkdirSync(dirname(absolute), { recursive: true })
    writeFileSync(absolute, buffer)

    return {
      ok: fetched.status === 200 && fetched.contentType?.includes('pdf'),
      url,
      status: fetched.status,
      contentType: fetched.contentType,
      disposition: fetched.disposition,
      bytes: buffer.length,
      path: relPath
    }
  } catch (thrown) {
    return {
      ok: false,
      url,
      status: null,
      reason: String(thrown?.message ?? thrown).slice(0, 300)
    }
  }
}

const decodePdfString = raw =>
  raw.replace(/\\([nrtbf()\\]|[0-7]{1,3})/g, (_, escape) => {
    const simple = { n: '\n', r: '\r', t: '\t', b: '\b', f: '\f', '(': '(', ')': ')', '\\': '\\' }
    if (simple[escape] !== undefined) return simple[escape]

    return String.fromCharCode(Number.parseInt(escape, 8))
  })

/**
 * Locates `stream … endstream` chunks and inflates the Flate ones, then reads
 * the text operands (`(…) Tj` and `[…] TJ`) in document order.
 */
export const extractPdfOperands = buffer => {
  const raw = buffer.toString('latin1')
  const streams = []
  let index = 0

  while (index < raw.length) {
    const start = raw.indexOf('stream', index)
    if (start === -1) break

    let dataStart = start + 'stream'.length
    if (raw[dataStart] === '\r') dataStart += 1
    if (raw[dataStart] === '\n') dataStart += 1

    const end = raw.indexOf('endstream', dataStart)
    if (end === -1) break

    let dataEnd = end
    if (raw[dataEnd - 1] === '\n') dataEnd -= 1
    if (raw[dataEnd - 1] === '\r') dataEnd -= 1

    streams.push(raw.slice(dataStart, dataEnd))
    index = end + 'endstream'.length
  }

  const operands = []
  const failures = []
  let inflated = 0

  for (const [position, chunk] of streams.entries()) {
    const bytes = Buffer.from(chunk, 'latin1')
    let text = null

    for (const attempt of ['flate', 'raw']) {
      try {
        text = (attempt === 'flate' ? inflateSync(bytes) : inflateRawSync(bytes)).toString('latin1')
        break
      } catch {
        // The next strategy is tried; an image stream fails both.
      }
    }

    if (text === null) {
      failures.push({ stream: position, bytes: bytes.length, reason: 'did not inflate' })
      continue
    }

    inflated += 1

    const strings = text.match(/\((?:\\[\s\S]|[^\\()])*\)/g) ?? []
    for (const literal of strings) {
      operands.push(decodePdfString(literal.slice(1, -1)))
    }
  }

  return { streams: streams.length, inflated, failures, operands }
}

const LABELS = [
  { key: 'subtotal', test: /^subtotal:?$/i },
  { key: 'discount', test: /^descuento/i },
  { key: 'base', test: /^base imponible:?$/i },
  { key: 'igv', test: /^igv\b/i },
  { key: 'total', test: /^total:?$/i }
]

/**
 * The money rows of the printed quotation, in cents.
 *
 * The totals table is the only block that names `Subtotal:`/`Base imponible:`, so
 * the search starts there and never reads the items table header.
 */
export const pdfMoneyRows = operands => {
  const list = Array.isArray(operands) ? operands : []
  const matches = []

  for (const label of LABELS) {
    const indices = []
    for (let position = 0; position < list.length; position += 1) {
      if (label.test.test(list[position].trim())) indices.push(position)
    }
    matches.push({ ...label, indices })
  }

  const subtotalIndex = matches.find(row => row.key === 'subtotal')?.indices[0] ?? -1
  if (subtotalIndex === -1) {
    return { ok: false, reason: 'the totals table was not located in the PDF text', rows: {} }
  }

  const amountAt = position => {
    const own = list[position]?.match(/S\/\s*([\d.,]+)/)
    if (own) {
      return { raw: list[position], value: parseCurrency(own[0]) }
    }

    for (let offset = 1; offset <= 3; offset += 1) {
      const candidate = list[position + offset]
      const match = candidate?.match(/S\/\s*([\d.,]+)/)
      if (match) {
        return { raw: `${list[position]} ${candidate}`.trim(), value: parseCurrency(match[0]) }
      }
    }

    return null
  }

  const rows = {}
  const missing = []

  for (const row of matches) {
    const candidates =
      row.key === 'total' ? row.indices.filter(position => position > subtotalIndex) : row.indices

    if (row.key !== 'subtotal' && row.key !== 'discount' && row.indices.length === 0) {
      missing.push(row.key)
      continue
    }

    let found = null
    for (const position of candidates) {
      // `total` also names the items table header; the first row after the
      // subtotal that carries an amount is the totals one.
      if (row.key === 'total' && position < subtotalIndex) continue
      const amount = amountAt(position)
      if (amount) {
        found = { key: row.key, label: list[position], ...amount, cents: cents(amount.value) }
        break
      }
    }

    if (found) rows[row.key] = found
    else if (!['base', 'igv'].includes(row.key) || row.indices.length > 0) missing.push(row.key)
  }

  if (!rows.subtotal || !rows.total) {
    return {
      ok: false,
      reason: `the totals table was located but ${missing.join(', ') || 'its amounts'} could not be read`,
      rows,
      missing,
      window: list.slice(Math.max(0, subtotalIndex - 4), subtotalIndex + 16)
    }
  }

  return {
    ok: true,
    rows,
    missing,
    values: {
      subtotal: rows.subtotal?.value ?? null,
      discount: rows.discount?.value ?? null,
      base: rows.base?.value ?? null,
      igv: rows.igv?.value ?? null,
      total: rows.total?.value ?? null
    },
    window: list.slice(Math.max(0, subtotalIndex - 4), subtotalIndex + 16)
  }
}
