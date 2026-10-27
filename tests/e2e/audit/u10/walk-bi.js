// Plan #13 (issue #373, U10) — layer 4: Business Intelligence, report by report.
//
// The surface publishes seven reports behind one filter card: six tabular reads
// and the consolidated cut, plus a dashboard that paints a KPI strip and charts
// instead of a table. For each of them the walk measures what the issue #373
// matrix (S1/S3/S5) asks:
//
//   1. DATA   which hooks paint with the default 30-day range, the visible
//             headers, the first three rows (or the KPI values) and the total row
//             count behind the pagination;
//   2. EMPTY  a period with no activity (2019-01-01 … 2019-01-31): does the
//             surface say what to do — `[data-report-empty]` with its title, its
//             description and `[data-widen-period]` — or does it paint rows and
//             values that look like a report with activity?
//   3. ERROR  a forced 500 on the report read, the `[data-state="error"]` block
//             it paints, its `[data-retry]` control and the recovery;
//   4. EXPORT the CSV, XLS and PDF against the screen: header labels, data row
//             count, one concrete value present in both, and the response
//             status. `consolidated` is measured as NOT exportable;
//   5. RANGE  the 7/30/90 presets, the three filters and the reset, each with the
//             request it re-issues;
//   6. WIDTH  the dashboard at 390×844: zero horizontal document overflow and the
//             two canvases painted;
//   7. SEDE   the consolidated sections, and the branch selector that only
//             exists for group scope (every seeded user has `branch_id` null).
//
// Six rows, one per measured block, each writing its own `walk-bi-*.json`; the
// last one joins the six into `walk-bi.json` so a generator that expects one
// artefact per surface finds it. `finanzas` is the desk owner and the only role
// the walk logs in as — a refusal is recorded, never thrown.
//
// `DEFECTO` / `NO-OP` / `CONFUSO` / `NO PROBADO` are evidence, never a thrown
// failure: the hard assertions of every row are that no `pageerror` was raised
// and that each declared step recorded its capture.
//
// Two readers are local on purpose:
//
//   * `readFile` keeps the WHOLE downloaded file, because `waitDownload` stores
//     the first 8000 characters and the appointments export carries more than a
//     hundred rows — the row comparison the matrix asks for needs the full
//     matrix. The PDF still goes through `waitDownload`: its bytes are not text,
//     so its contract (status, filename, size, magic) is all there is to read.
//   * `tableSnapshot` reads the professional, branch and professional-consolidated
//     tables, which the session's `reportHeaders`/`reportRows` do not address.

import { readFileSync } from 'node:fs'

import { expect, test } from 'playwright/test'

import { classifyFailures, collectDiagnostics, primeSession } from '../harness.js'

import { ensureFixtures } from './fixtures.js'

import {
  DESKTOP,
  MOBILE,
  REPORT_PATH,
  SESSION,
  api,
  applyFilters,
  audit,
  classifyConsole,
  closeDialog,
  dismissToasts,
  gotoSurface,
  interceptReport,
  login,
  markExpected,
  overflow,
  pickOption,
  readEvidence,
  readSelectOptions,
  releaseReport,
  reportHeaders,
  reportRows,
  requestOf,
  respondTo,
  selectReport,
  setDateRange,
  settle,
  shot,
  waitDownload,
  walkCounts,
  walkStep,
  watchRequests
} from './helpers.js'

const SURFACE = '/business-intelligence'
const SLUG = 'business-intelligence'
const ROLE = 'finanzas'

// The seven reports of the selector, in the order `reportOptions` declares them
// (the consolidated cut is sixth, the chair utilization last — the first run of
// this walk had them swapped, which made the catalogue comparison disagree with
// a selector that was publishing exactly the right labels).
// `kind` is what the surface is expected to paint: the dashboard has KPIs and
// charts, the consolidated report has three sections and no export, the other
// four are tabular reads (`revenue` is tabular too but cuts the same money five
// ways, so its export carries more rows than the screen).
const REPORTS_UNDER_TEST = Object.freeze([
  { value: 'dashboard', label: 'Dashboard General', kind: 'dashboard' },
  { value: 'appointments', label: 'Reporte de Citas', kind: 'table' },
  { value: 'patients', label: 'Reporte de Pacientes', kind: 'table' },
  { value: 'professionals', label: 'Reporte de Profesionales', kind: 'table' },
  { value: 'revenue', label: 'Reporte de Ingresos', kind: 'revenue' },
  { value: 'consolidated', label: 'Consolidado por Sede', kind: 'consolidated' },
  { value: 'utilization', label: 'Utilización de Ambientes', kind: 'table' }
])

const EXPORTABLE = REPORTS_UNDER_TEST.filter(report => report.kind !== 'consolidated')

const FORMATS = Object.freeze([
  { format: 'excel', hook: '[data-export-excel]', extension: 'xls', parser: 'xls' },
  { format: 'csv', hook: '[data-export-csv]', extension: 'csv', parser: 'csv' },
  { format: 'pdf', hook: '[data-export-pdf]', extension: 'pdf', parser: 'pdf' }
])

// A window with no activity at all: the seeded appointments live in 2025 and the
// fixtures of the session write today, so 2019 is empty by construction.
const EMPTY_RANGE = Object.freeze({ from: '2019-01-01', to: '2019-01-31' })

const PRESETS = Object.freeze([
  { id: '7d', label: '7 días' },
  { id: '30d', label: '30 días' },
  { id: '90d', label: '90 días' }
])

const VERDICT_VOCABULARY = ['OK', 'DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO']

// The 500 the error block is measured with is instrument-induced: the browser
// logs it as a failed resource, and the row that provokes it declares it.
const DECLARED_CONSOLE = [
  {
    pattern: /Failed to load resource: .*500/,
    reason: 'el paso de error provoca un 500 en /api/reports/ para medir el bloque y su reintento'
  }
]

const REPORT_FAILURE = /\/api\/reports\//

// --- small readers ----------------------------------------------------------

const flat = value =>
  String(value ?? '')
    .trim()
    .replace(/\s+/g, ' ')

const clipped = (value, length = 600) => {
  const text = String(value ?? '')

  return text.length > length ? `${text.slice(0, length)}…` : text
}

const humanDate = value =>
  String(value ?? '')
    .split('-')
    .reverse()
    .join('/')

const escapeHtml = value =>
  String(value ?? '')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&amp;/g, '&')

/** Rows of a CSV as `fputcsv` writes them (quotes, doubled quotes, commas). */
const parseCsv = body => {
  const rows = []
  let row = []
  let cell = ''
  let quoted = false

  for (let index = 0; index < body.length; index += 1) {
    const char = body[index]

    if (quoted) {
      if (char !== '"') {
        cell += char
        continue
      }

      if (body[index + 1] === '"') {
        cell += '"'
        index += 1
      } else {
        quoted = false
      }

      continue
    }

    if (char === '"') {
      quoted = true
      continue
    }

    if (char === ',') {
      row.push(cell)
      cell = ''
      continue
    }

    if (char === '\n') {
      row.push(cell)
      rows.push(row)
      row = []
      cell = ''
      continue
    }

    if (char !== '\r') cell += char
  }

  if (cell !== '' || row.length > 0) {
    row.push(cell)
    rows.push(row)
  }

  return rows.filter(cells => cells.some(value => String(value).trim() !== ''))
}

/** Rows of the SpreadsheetML `.xls` (the `<tr><td>` matrix of the export). */
const parseXls = body => {
  const rows = []
  const rowPattern = /<tr>([\s\S]*?)<\/tr>/g
  let match = rowPattern.exec(body)

  while (match !== null) {
    const cells = []
    const cellPattern = /<td>([\s\S]*?)<\/td>/g
    let cell = cellPattern.exec(match[1])

    while (cell !== null) {
      cells.push(flat(escapeHtml(cell[1])))
      cell = cellPattern.exec(match[1])
    }

    if (cells.length > 0) rows.push(cells)
    match = rowPattern.exec(body)
  }

  return rows
}

/** `S/ 1,234.50` and `1234.5` are the same money; `—` is not a number. */
const numberLike = value => {
  const digits = String(value ?? '').replace(/[^\d.-]/g, '')
  if (digits === '' || digits === '-' || digits === '.') return null

  const parsed = Number(digits)

  return Number.isFinite(parsed) ? parsed : null
}

/**
 * A header label as the surface and the file print it.
 *
 * `UiDataTable` renders its headings with `text-transform: uppercase`, so the
 * screen says "PACIENTE" while the export says "Paciente". The comparison is
 * case- and accent-insensitive: the label is the same word, the case is the
 * stylesheet's.
 */
const normalizeLabel = value =>
  flat(value)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()

const sameLabels = (left, right) =>
  JSON.stringify((left ?? []).map(normalizeLabel)) ===
  JSON.stringify((right ?? []).map(normalizeLabel))

/** Same members, order-independent: the selector publishes a catalogue. */
const sameSet = (left, right) => {
  const one = [...new Set((left ?? []).map(flat))].sort()
  const other = [...new Set((right ?? []).map(flat))].sort()

  return JSON.stringify(one) === JSON.stringify(other)
}

/** Same members in the same order: a drift the evidence records, not a defect. */
const sameOrder = (left, right) =>
  JSON.stringify((left ?? []).map(flat)) === JSON.stringify((right ?? []).map(flat))

const sameValue = (left, right) => {
  const leftNumber = numberLike(left)
  const rightNumber = numberLike(right)

  if (leftNumber !== null && rightNumber !== null) return leftNumber === rightNumber

  return flat(left) !== '' && flat(left) === flat(right)
}

/**
 * Headers, rows and the published total of any table of the surface.
 *
 * The pagination footer is the only place the surface says how many rows the
 * payload carried, so `total` reads it instead of assuming the ten the table
 * paints. A row whose cells do not line up with the header count is the
 * primitive's empty state, not a data row.
 */
const tableSnapshot = (page, selector) =>
  page.evaluate(sel => {
    const node = document.querySelector(sel)
    if (!node) return null

    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')
    const headers = Array.from(node.querySelectorAll('thead th'))
      .map(header => text(header.innerText))
      .filter(Boolean)
    const rows = Array.from(node.querySelectorAll('tbody tr'))
      .map(row => Array.from(row.querySelectorAll('th, td')).map(cell => text(cell.innerText)))
      .filter(cells => cells.some(Boolean))
    const totals = /Mostrando\s+\d+-\d+\s+de\s+(\d+)\s+elementos/i.exec(text(node.innerText))

    return {
      headers,
      rows,
      total: totals ? Number(totals[1]) : null,
      dataRows: rows.filter(cells => headers.length > 0 && cells.length === headers.length).length
    }
  }, selector)

/** The export triggers with their disabled state and their hint. */
const exportButtons = page =>
  page.evaluate(
    hooks => {
      const text = value => (value ?? '').trim().replace(/\s+/g, ' ')

      return hooks.map(hook => {
        const node = document.querySelector(hook.hook)
        const box = node?.getBoundingClientRect()

        return {
          format: hook.format,
          present: Boolean(node),
          visible: Boolean(box && box.width > 0 && box.height > 0),
          disabled: node ? node.disabled === true || node.hasAttribute('disabled') : null,
          title: node ? text(node.getAttribute('title')) || null : null,
          label: node ? text(node.innerText) || null : null
        }
      })
    },
    FORMATS.map(item => ({ format: item.format, hook: item.hook }))
  )

/**
 * Every hook of the surface, read in one pass.
 *
 * A hook that is not painted is `null`, never `false`: the evidence then says
 * "the surface did not paint it" instead of "the surface painted an empty one".
 */
const biState = page =>
  page.evaluate(() => {
    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      if (!node) return false
      const box = node.getBoundingClientRect()

      return box.width > 0 && box.height > 0
    }
    const one = selector => {
      const node = document.querySelector(selector)

      return visible(node) ? node : null
    }

    // `[data-revenue-summary]` and `[data-consolidated-summary]` name both the
    // section and each of its values, so the pair read is scoped to the nested
    // nodes and keyed by the attribute value.
    const pairs = attribute => {
      const root = one(`[${attribute}]`)
      if (!root) return null

      return Object.fromEntries(
        Array.from(root.querySelectorAll(`[${attribute}] [${attribute}]`)).map(node => [
          node.getAttribute(attribute),
          text(node.innerText)
        ])
      )
    }

    const empty = one('[data-report-empty]')
    const error = one('[data-state="error"]')
    const consolidated = one('[data-consolidated]')
    const dimensions = one('[data-revenue-dimensions]')

    return {
      hero: {
        title: text(one('[data-hero-title]')?.innerText),
        caption: text(one('[data-hero-caption]')?.innerText),
        refresh: Boolean(one('[data-hero-refresh]'))
      },
      period: text(one('[data-period-applied]')?.innerText) || null,
      branch: text(one('[data-branch-applied]')?.innerText) || null,
      exportHint: text(one('[data-export-unavailable]')?.innerText) || null,
      loading: Boolean(one('[data-bi-loading]')),
      kpis: Boolean(one('[data-bi-kpis]')),
      kpiValues: one('[data-bi-kpis]')
        ? Array.from(document.querySelectorAll('[data-kpi-card]')).map(card => ({
            key: card.getAttribute('data-kpi-card'),
            label: text(card.querySelector('p')?.innerText),
            value: text(card.querySelector('[data-kpi-value]')?.innerText) || null,
            caption: text(card.querySelector('[data-kpi-caption]')?.innerText) || null
          }))
        : null,
      charts: one('[data-bi-charts]')
        ? {
            canvases: Array.from(document.querySelectorAll('[data-bi-charts] canvas')).map(
              canvas => {
                const box = canvas.getBoundingClientRect()

                return {
                  hook: canvas.getAttribute('data-chart'),
                  width: Math.round(box.width),
                  height: Math.round(box.height)
                }
              }
            ),
            cards: Array.from(document.querySelectorAll('[data-bi-charts] [data-chart-card]')).map(
              node => node.getAttribute('data-chart-card')
            )
          }
        : null,
      revenueSummary: pairs('data-revenue-summary'),
      professionalTable: Boolean(one('[data-professional-performance]')),
      reportSection: Boolean(one('[data-report-table-section]')),
      empty: empty
        ? {
            title: text(empty.querySelector('h3')?.innerText) || null,
            description: text(empty.querySelector('.empty-state-description')?.innerText) || null,
            action: text(empty.querySelector('[data-widen-period]')?.innerText) || null,
            text: text(empty.innerText)
          }
        : null,
      consolidated: consolidated
        ? {
            period:
              text(consolidated.querySelector('[data-consolidated-period]')?.innerText) || null,
            summary: pairs('data-consolidated-summary'),
            hasBranch: Boolean(consolidated.querySelector('[data-consolidated-by-branch]')),
            hasProfessional: Boolean(
              consolidated.querySelector('[data-consolidated-by-professional]')
            )
          }
        : null,
      error: error
        ? {
            title: text(error.querySelector('p')?.innerText) || null,
            text: text(error.innerText),
            retry: Boolean(error.querySelector('[data-retry]'))
          }
        : null,
      dimensions: dimensions
        ? {
            selected:
              text(
                dimensions.querySelector('[data-revenue-dimension][aria-pressed="true"]')?.innerText
              ) || null,
            options: Array.from(dimensions.querySelectorAll('[data-revenue-dimension]')).map(node =>
              text(node.innerText)
            )
          }
        : null
    }
  })

/** The report table of the screen: the session's readers plus its pagination. */
const screenTable = async page => {
  const headers = await reportHeaders(page)
  const rows = await reportRows(page)
  const total = await page.evaluate(() => {
    const node = document.querySelector('[data-report-table]')
    if (!node) return null

    const match = /Mostrando\s+\d+-\d+\s+de\s+(\d+)\s+elementos/i.exec(
      (node.innerText ?? '').replace(/\s+/g, ' ')
    )

    return match ? Number(match[1]) : null
  })

  return {
    headers,
    rows,
    total,
    dataRows: rows.filter(cells => headers.length > 0 && cells.length === headers.length).length
  }
}

/** Everything a step of the walk compares against the export. */
const snapshot = async page => {
  const paint = await awaitPainted(page, 10000)
  const state = await biState(page)
  const table = await screenTable(page)
  const professional = await tableSnapshot(page, '[data-professional-table]')
  const consolidatedBranch = await tableSnapshot(page, '[data-consolidated-branch-table]')
  const consolidatedProfessional = await tableSnapshot(
    page,
    '[data-consolidated-professional-table]'
  )
  const buttons = await exportButtons(page)

  return {
    paint,
    hooks: {
      kpis: state.kpis,
      kpiCards: state.kpiValues?.length ?? 0,
      charts: Boolean(state.charts),
      canvases: state.charts?.canvases.length ?? 0,
      revenueSummary: Boolean(state.revenueSummary),
      reportTable: table.headers.length > 0,
      professionalPerformance: state.professionalTable,
      empty: Boolean(state.empty),
      consolidated: Boolean(state.consolidated),
      error: Boolean(state.error)
    },
    period: state.period,
    branch: state.branch,
    exportHint: state.exportHint,
    kpis: state.kpiValues,
    revenue: state.revenueSummary,
    dimensions: state.dimensions,
    headers: table.headers,
    rows: table.rows.slice(0, 3),
    rowCount: table.rows.length,
    total: table.total,
    dataRows: table.dataRows,
    professional,
    empty: state.empty,
    consolidated: state.consolidated,
    consolidatedBranch,
    consolidatedProfessional,
    error: state.error,
    buttons
  }
}

const measured = hooks => {
  const painted = Object.entries(hooks ?? {})
    .filter(([, value]) => value === true)
    .map(([key]) => key)

  return painted.length > 0 ? painted : ['(ninguno)']
}

/** The label of a preset from its id, so the applied label can be checked. */
const presetLabel = id => PRESETS.find(preset => preset.id === id)?.label ?? null

/** The last report read of the page's own traffic, as the walk declares it. */
const lastReportRead = entries =>
  [...(entries ?? [])]
    .reverse()
    .find(entry => entry.method === 'GET' && REPORT_PATH.test(entry.path)) ?? null

/** The last report read whose query carried `parameter` with a value. */
const lastReportReadWith = (entries, parameter) =>
  [...(entries ?? [])]
    .reverse()
    .find(
      entry =>
        entry.method === 'GET' &&
        REPORT_PATH.test(entry.path) &&
        String(entry.query?.[parameter] ?? '') !== ''
    ) ?? null

/**
 * The request evidence of an apply, read from the page's own traffic.
 *
 * `applyFilters` reports the status and the path but not the method, so the
 * observation comes first and the helper's report is only the fallback.
 */
const appliedRequest = (entries, result) => {
  const observed = requestOf(lastReportRead(entries))

  if (observed) return observed

  return result && result.status !== null
    ? { method: 'GET', path: result.path, status: result.status }
    : null
}

// --- downloads --------------------------------------------------------------
const awaitEnabled = async (page, selector, timeout = 15000) => {
  const started = Date.now()

  while (Date.now() - started < timeout) {
    if (
      await page
        .locator(selector)
        .first()
        .isEnabled()
        .catch(() => false)
    )
      return true
    await page.waitForTimeout(150)
  }

  return false
}

/**
 * The download plus its complete text.
 *
 * The reading contract is `waitDownload`'s (download event, export response
 * status, suggested filename), but the file is kept whole: a report of 100+
 * appointments overflows the 8000 characters that helper stores, and the row
 * comparison would then be measuring the cut instead of the export.
 */
const readFile = async (page, selector, timeout = 25000) => {
  const trigger = page.locator(selector).first()

  if (!(await trigger.count().catch(() => 0))) {
    return {
      ok: false,
      reason: 'no download trigger',
      filename: null,
      text: null,
      bytes: null,
      status: null
    }
  }

  await dismissToasts(page).catch(() => {})

  const downloadPromise = page.waitForEvent('download', { timeout }).catch(() => null)
  const responsePromise = page
    .waitForResponse(
      candidate => {
        try {
          return REPORT_PATH.test(new URL(candidate.url()).pathname)
        } catch {
          return false
        }
      },
      { timeout }
    )
    .catch(() => null)

  const clicked = await trigger
    .click({ timeout: 8000 })
    .then(() => ({ ok: true }))
    .catch(thrown => ({ ok: false, reason: String(thrown?.message ?? thrown).slice(0, 200) }))

  const download = await downloadPromise
  const response = await responsePromise
  const status = response?.status() ?? null

  if (!download) {
    return {
      ok: false,
      reason: clicked.ok ? 'el botón no produjo evento de descarga' : clicked.reason,
      filename: null,
      text: null,
      bytes: null,
      status
    }
  }

  const localPath = await download.path().catch(() => null)
  const buffer = localPath ? readFileSync(localPath) : null

  return {
    ok: Boolean(buffer),
    reason: buffer ? null : 'la descarga no dejó archivo local',
    filename: download.suggestedFilename(),
    text: buffer ? buffer.toString('utf8') : null,
    bytes: buffer ? buffer.length : null,
    status,
    failure: await download.failure().catch(() => null)
  }
}

/**
 * One export measured against the screen.
 *
 * `screen` is the snapshot taken before the download: an export does not
 * repaint anything, so both sides describe the same request.
 */
const measureExport = async (page, { report, format, screen }) => {
  const enabled = await awaitEnabled(page, format.hook)

  if (!enabled) {
    return {
      format: format.format,
      parser: format.parser,
      ok: false,
      reason: 'el botón de exportación siguió deshabilitado',
      filename: null,
      status: null,
      bytes: null,
      screenHeaders: screen.headers,
      exportHeaders: null,
      screenRows: screen.dataRows,
      screenTotal: screen.total,
      exportRows: null,
      exportRowsTotal: null,
      sample: null,
      match: { headers: null, rows: null, sample: null },
      notes: []
    }
  }

  const download =
    format.parser === 'pdf'
      ? await waitDownload(page, format.hook)
      : await readFile(page, format.hook)

  const body = format.parser === 'pdf' ? download.body : download.text
  const matrix =
    format.parser === 'csv'
      ? parseCsv(body ?? '')
      : format.parser === 'xls'
        ? parseXls(body ?? '')
        : []
  const exportHeaders = matrix.length > 0 ? matrix[0] : []
  const exportBody = matrix.slice(1)
  const isSummary = row => /^Resumen del período/i.test(flat(row?.[0]))
  const summaryRow = report.kind === 'revenue' ? (exportBody.find(isSummary) ?? null) : null
  const dataRows = exportBody.filter(row => !isSummary(row))

  const comparison = {
    format: format.format,
    parser: format.parser,
    ok: download.ok === true,
    reason: download.reason ?? null,
    filename: download.filename,
    status: download.status,
    bytes: download.bytes,
    screenHeaders: screen.headers,
    exportHeaders,
    screenRows: screen.dataRows,
    screenTotal: screen.total,
    exportRows: format.parser === 'pdf' ? null : dataRows.length,
    exportRowsTotal: format.parser === 'pdf' ? null : dataRows.length,
    extensionOk: String(download.filename ?? '')
      .toLowerCase()
      .endsWith(`.${format.extension}`),
    screenDimension: screen.dimensions?.selected ?? null,
    sample: null,
    match: { headers: null, rows: null, sample: null },
    notes: []
  }

  if (format.parser === 'pdf') {
    comparison.pdf = { magic: String(body ?? '').startsWith('%PDF'), bytes: download.bytes }
    comparison.notes.push(
      'el PDF es binario comprimido por DomPDF: no expone cabeceras ni celdas, así que la comparación de contenido se limita a su firma y su tamaño'
    )

    return comparison
  }

  const complete = comparison.exportRows !== null

  // `screenVisible` is what the page paints; `screenTotal` is what the page says
  // it has (the pagination footer). A comparison against the visible page alone
  // would call a 100-row export with a 10-row page a mismatch, when the screen is
  // telling the reader about all 100.
  comparison.screenVisible = screen.dataRows
  comparison.screenTotal = screen.total ?? screen.dataRows
  comparison.screenPaged = screen.total !== null && screen.total !== screen.dataRows

  // --- headers: the export labels against the on-screen labels -------------
  if (report.kind === 'dashboard') {
    const concepts = dataRows.filter(row => flat(row[0]) === 'Resumen').map(row => flat(row[1]))
    const onScreen = (screen.kpis ?? []).map(kpi => kpi.label)

    comparison.screenHeaders = onScreen
    comparison.concepts = {
      export: concepts,
      screen: onScreen,
      matched: concepts.filter(concept =>
        onScreen.some(label => normalizeLabel(label) === normalizeLabel(concept))
      )
    }
    comparison.notes.push(
      'el dashboard no pinta tabla: se comparan los conceptos del bloque «Resumen» del export contra las etiquetas de la tira de KPI'
    )
  } else {
    comparison.match.headers = sameLabels(exportHeaders, screen.headers)
    comparison.headerDrift =
      comparison.match.headers === false
        ? (screen.headers ?? [])
            .map((label, index) => ({ screen: label, export: exportHeaders[index] ?? null }))
            .filter(pair => normalizeLabel(pair.screen) !== normalizeLabel(pair.export))
        : []
  }

  // --- rows: what the screen says it has against what the file carries -----
  if (report.kind === 'revenue') {
    const dimension = screen.dimensions?.selected ?? null
    const cutRows = dataRows.filter(row => flat(row[0]) === flat(dimension))
    const byCut = {}

    for (const row of dataRows) {
      const cut = flat(row[0])
      byCut[cut] = (byCut[cut] ?? 0) + 1
    }

    comparison.byCut = byCut
    comparison.cutsInExport = Object.keys(byCut).length
    comparison.dimensionOnScreen = dimension
    comparison.screenRows = cutRows.length > 0 ? (screen.total ?? screen.dataRows) : screen.dataRows
    comparison.match.rows = complete ? cutRows.length === comparison.screenRows : null
    comparison.notes.push(
      `el export trae los ${Object.keys(byCut).length} cortes de la misma plata (${dataRows.length} filas más la fila de resumen); la pantalla muestra sólo el corte «${dimension ?? '—'}» (${comparison.screenRows ?? '—'} filas) y la diferencia es la medida, no un defecto`
    )
  } else if (report.kind === 'dashboard') {
    const performanceRows = dataRows.filter(row => flat(row[0]) === 'Rendimiento por profesional')
    const visible = screen.professional?.dataRows ?? null

    comparison.screenVisible = visible
    comparison.screenRows = visible
    comparison.screenTotal = visible
    comparison.exportRows = performanceRows.length
    comparison.sectionRows = dataRows.length
    comparison.match.rows = visible === null ? null : performanceRows.length === visible
    comparison.notes.push(
      `el export aplana el dashboard en Sección/Concepto/Valor (${dataRows.length} filas); la única sección con una fila por entidad es «Rendimiento por profesional» (${performanceRows.length}) frente a la tabla de la pantalla (${visible ?? '—'})`
    )
  } else {
    comparison.screenRows = comparison.screenTotal
    comparison.match.rows = complete ? dataRows.length === comparison.screenTotal : null

    if (comparison.screenPaged) {
      comparison.notes.push(
        `la pantalla pagina: muestra ${screen.dataRows} de ${screen.total} filas y el pie publica el total, que es la población con la que se compara el archivo`
      )
    }
  }

  // --- one concrete value that lives on both sides -------------------------
  if (report.kind === 'revenue') {
    comparison.sample = {
      kind: 'revenue.summary.total_revenue',
      screen: screen.revenue?.revenue ?? null,
      export: summaryRow ? summaryRow[3] : null
    }
  } else if (report.kind === 'dashboard') {
    const appointments = (screen.kpis ?? []).find(kpi => kpi.key === 'appointments') ?? null
    const exportRow = dataRows.find(row => flat(row[1]) === 'Citas del período') ?? null

    comparison.sample = {
      kind: 'dashboard.kpi.appointments',
      screen: appointments?.value ?? null,
      export: exportRow ? exportRow[2] : null
    }
  } else {
    comparison.sample = {
      kind: 'first-row.first-cell',
      screen: screen.rows?.[0]?.[0] ?? null,
      export: dataRows[0]?.[0] ?? null
    }
  }

  comparison.match.sample = sameValue(comparison.sample.screen, comparison.sample.export)

  return comparison
}

// --- walk scaffolding -------------------------------------------------------

const beginWalk = async (page, { slug, title, file }) => {
  const fixtures = await ensureFixtures().catch(thrown => ({
    ok: false,
    reason: `fixtures: ${String(thrown?.message ?? thrown).slice(0, 200)}`
  }))
  const session = await login(ROLE)
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug,
    test: title,
    role: ROLE,
    reports: REPORTS_UNDER_TEST.map(report => report.label),
    steps: [],
    counts: null,
    fixtures: {
      ok: fixtures.ok === true,
      reason: fixtures.reason ?? null,
      token: fixtures.token ?? null,
      day: fixtures.day ?? null
    },
    pageErrors: [],
    consoleErrors: [],
    consoleClassified: null,
    failedRequests: null,
    traffic: null,
    cleanup: []
  }

  return {
    fixtures,
    session,
    diagnostics,
    requests,
    evidence,
    step: walkStep(page, evidence),
    file
  }
}

/**
 * Closes a row: diagnostics, declared failures and the evidence file.
 *
 * `options` travels as the third argument, not inside the walk object: the first
 * run of this walk passed it there, the destructuring above ignored it, and the
 * seven instrument-induced 500s were published as `failedRequests.unexpected`.
 */
const endWalk = async (page, walk, options = {}) => {
  const { requests, diagnostics, evidence, file } = walk
  const { declared = null, declaredFailure = null } = options

  await dismissToasts(page).catch(() => {})
  await closeDialog(page).catch(() => {})

  evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
  evidence.consoleErrors = diagnostics.consoleErrors
  evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors, ROLE, declared ?? [])
  evidence.failedRequests = classifyFailures(diagnostics.failedRequests, ROLE)

  if (declaredFailure) {
    markExpected(evidence.failedRequests, declaredFailure.predicate, declaredFailure.reason)
  }

  const reportCalls = (requests ?? []).filter(entry => REPORT_PATH.test(entry.path))

  evidence.traffic = {
    api: (requests ?? []).length,
    reportReads: reportCalls.filter(entry => entry.method === 'GET').length,
    reportStatuses: reportCalls.map(
      entry => `${entry.method} ${entry.path} → ${entry.status ?? 'sin respuesta'}`
    ),
    withoutStatus: reportCalls.filter(entry => entry.status === null).length
  }
  evidence.counts = walkCounts(evidence.steps)

  // `audit()` owns the extension: the declared name is the file the merged row reads.
  audit(String(file).replace(/\.json$/, ''), evidence)

  return evidence
}

/** The common tail of every row: page errors, declared steps and captures. */
const expectWalk = (evidence, ids) => {
  expect(evidence.pageErrors, `page errors de ${evidence.slug}`).toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    `pasos declarados de ${evidence.slug}`
  ).toEqual(ids)
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    `cada paso de ${evidence.slug} deja su captura`
  ).toBe(true)
  expect(
    evidence.steps.every(entry => VERDICT_VOCABULARY.includes(entry.verdict)),
    `veredictos de ${evidence.slug}`
  ).toBe(true)
}

// The four hooks the surface paints a report into (the render module's
// `REPORT_PAINTED`), plus the consolidated section, which is the one report that
// paints none of them.
const PAINTED =
  '[data-bi-kpis], [data-report-table-section], [data-report-empty], [data-state="error"], [data-consolidated]'

/**
 * Waits, bounded, for one of the report hooks to be on screen.
 *
 * Presence alone is not proof that the payload landed: the KPI strip paints
 * before the first read resolves and shows the empty dashboard — that is how the
 * first run of this walk measured `CITAS 0 / PACIENTES 0`. `enterSurface` and
 * `openReport` wait for the response as well; this wait covers the render that
 * follows it, and `snapshot()` calls it so no step reads a half-painted surface.
 */
const awaitPainted = async (page, timeout = 12000) => {
  const started = Date.now()
  const painted = await page
    .locator(PAINTED)
    .first()
    .waitFor({ state: 'visible', timeout })
    .then(() => true)
    .catch(() => false)

  return { painted, waited: Date.now() - started }
}

/**
 * Enters the surface and leaves it painted with the default report.
 *
 * The response listener is armed before the navigation so the first read cannot
 * be missed, and the two waits that follow are what make the first measurement
 * of every row the payload instead of the pre-load zeros.
 */
const enterSurface = async (page, session, viewport = DESKTOP) => {
  await page.setViewportSize(viewport)
  await primeSession(page, session)

  const pending = page
    .waitForResponse(
      candidate => {
        try {
          return REPORT_PATH.test(new URL(candidate.url()).pathname)
        } catch {
          return false
        }
      },
      { timeout: 25000 }
    )
    .catch(() => null)

  const navigation = await gotoSurface(page, SURFACE)
  const report = await pending
  const paint = await awaitPainted(page)

  await settle(page)

  return { navigation, status: report?.status() ?? null, paint }
}

/**
 * Waits for the apply control to be clickable.
 *
 * `applyFilters` returns `{ ok: false, reason: 'the apply control is disabled' }`
 * without retrying, and the control is disabled while a read is in flight — the
 * first run of this walk lost its 2019 range to that window.
 */
const awaitApplyEnabled = async (page, timeout = 15000) => {
  const button = page.locator('[data-filters-apply]').first()
  const started = Date.now()

  while (Date.now() - started < timeout) {
    if (await button.isEnabled().catch(() => false))
      return { ok: true, waited: Date.now() - started }
    await page.waitForTimeout(120)
  }

  return { ok: false, waited: Date.now() - started }
}

/** The visible text of the three filter triggers, as the surface prints it. */
const selectTriggers = page =>
  page.evaluate(() => {
    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')
    const read = hook => {
      const node = document.querySelector(`${hook} > button`)

      return node ? text(node.innerText) || null : null
    }

    return {
      professional: read('[data-filters-professional]'),
      environment: read('[data-filters-environment]'),
      branch: read('[data-filters-branch]')
    }
  })

/** Selects a report and waits for the read it re-issues. */
const openReport = async (page, label) => {
  const picked = { value: null }
  const request = await respondTo(
    page,
    { method: 'GET', path: REPORT_PATH },
    async () => {
      picked.value = await selectReport(page, label)
    },
    25000
  )

  await settle(page)

  return { picked: picked.value, request }
}

// --- row 1: DATA ------------------------------------------------------------

test('@U10 · capa 4 — BI: los siete reportes pintan datos', async ({ page }) => {
  test.setTimeout(300000)

  const walk = await beginWalk(page, {
    slug: 'bi-data',
    title: 'los siete reportes pintan datos',
    file: 'walk-bi-data.json'
  })
  const { evidence, step, session } = walk

  page.setDefaultTimeout(8000)

  try {
    const entered = await enterSurface(page, session)

    evidence.entry = {
      viewport: { width: DESKTOP.width, height: DESKTOP.height },
      status: entered.status,
      paint: entered.paint,
      navigation: entered.navigation
    }

    await step(
      'open',
      'Abrir /business-intelligence como finanzas',
      'La superficie pinta su cabecera, el rango por omisión de 30 días, el selector con los siete reportes, el selector de sede del alcance de grupo y las tres exportaciones habilitadas',
      async entry => {
        const state = await biState(page)
        const options = await readSelectOptions(page, '[data-filters-report] > button')
        const buttons = await exportButtons(page)
        const from = await page.locator('[data-filters-from] input').first().inputValue()
        const to = await page.locator('[data-filters-to] input').first().inputValue()
        const branch = await page.locator('[data-filters-branch]').count()
        const labels = REPORTS_UNDER_TEST.map(report => report.label)
        const optionsMatch = sameSet(options.options ?? [], labels)
        const optionsOrder = sameOrder(options.options ?? [], labels)
        const exportsEnabled = buttons.every(
          button => button.present === true && button.disabled === false
        )
        const hooks = measured({
          kpis: state.kpis,
          charts: Boolean(state.charts),
          professionalPerformance: state.professionalTable
        })
        const rangeHonest =
          new Date(`${from}T00:00:00`) < new Date(`${to}T00:00:00`) &&
          String(state.period ?? '').includes(humanDate(to))

        // The KPI strip against the same period's API answer: the pre-load paint
        // of the dashboard shows zeros, and only the two numbers side by side
        // tell that strip apart from a period with no activity.
        const appointmentsKpi = (state.kpiValues ?? []).find(kpi => kpi.key === 'appointments')
        const probe = await api(
          session,
          'GET',
          `/api/reports/dashboard?start_date=${from}&end_date=${to}`
        )
        const apiAppointments = probe.body?.data?.totalAppointments ?? null
        const kpiAgrees = sameValue(appointmentsKpi?.value ?? null, apiAppointments)

        entry.obtained = `hero «${state.hero.title}» («${state.hero.caption}») · período «${state.period ?? '—'}» · rango ${from || '—'} … ${to || '—'} · selector de reportes ${JSON.stringify(options.options ?? [])} (mismo conjunto ${optionsMatch}, mismo orden ${optionsOrder}) · selector de sede ${branch > 0 ? 'presente (alcance de grupo)' : 'ausente'} · exportaciones ${JSON.stringify(buttons.map(button => `${button.label}:${button.disabled === false ? 'habilitada' : 'deshabilitada'}`))} · hooks pintados ${JSON.stringify(hooks)} · KPI «Citas» ${appointmentsKpi?.value ?? '—'} frente a la API ${apiAppointments ?? '—'} (HTTP ${probe.status}) → ${kpiAgrees ? 'coincide' : 'discrepa'} · canvas ${(state.charts?.canvases ?? []).length}`

        entry.request = requestOf(lastReportRead(walk.requests))
        entry.detail = {
          hero: state.hero,
          period: state.period,
          range: { from, to, honest: rangeHonest },
          options: options.options ?? [],
          optionsMatch,
          optionsOrder,
          branch: branch > 0,
          buttons,
          kpis: state.kpiValues,
          charts: state.charts,
          kpiProbe: {
            status: probe.status,
            totalAppointments: apiAppointments,
            kpi: appointmentsKpi?.value ?? null,
            agrees: kpiAgrees
          },
          entry: evidence.entry
        }

        if (!optionsMatch) {
          entry.verdict = 'DEFECTO'
          entry.notes = `el selector no publica los siete reportes: ${JSON.stringify(options.options ?? [])}`
        } else if (!exportsEnabled || branch === 0 || !state.period || !rangeHonest) {
          entry.verdict = 'DEFECTO'
          entry.notes =
            'la superficie no ofrece el selector de sede del alcance de grupo, no habilita las exportaciones o no declara el rango por omisión'
        } else if (!state.kpis || !kpiAgrees) {
          entry.verdict = 'DEFECTO'
          entry.notes = `la tira de KPI no coincide con la API para el mismo período: pantalla ${appointmentsKpi?.value ?? '—'} frente a ${apiAppointments ?? '—'}`
        } else {
          entry.verdict = 'OK'
          entry.notes = optionsOrder
            ? ''
            : 'el orden del selector no es el de la lista declarada por la caminata'
        }

        await shot(page, 'shots/bi-open-data.png')

        return entry
      }
    )

    for (const report of REPORTS_UNDER_TEST) {
      await step(
        `data-${report.value}`,
        `Elegir «${report.label}» y medir el estado de datos del rango por omisión`,
        'El reporte responde 200 y pinta su bloque propio (KPI, gráficos, resumen, tabla o consolidado) con cabeceras y filas medibles',
        async entry => {
          const opened = await openReport(page, report.label)
          const state = await snapshot(page)
          const hooks = measured(state.hooks)
          const paintedData =
            report.kind === 'dashboard'
              ? state.hooks.kpis
              : report.kind === 'consolidated'
                ? state.hooks.consolidated
                : state.hooks.reportTable
          const headers =
            report.kind === 'consolidated'
              ? (state.consolidatedBranch?.headers ?? [])
              : state.headers
          const rows =
            report.kind === 'consolidated'
              ? (state.consolidatedBranch?.rows ?? []).slice(0, 3)
              : state.rows
          const { empty } = state

          entry.obtained = `${report.label} · selección ${opened.picked?.ok ? 'aplicada' : `fallida (${opened.picked?.reason ?? 'sin motivo'})`} · GET ${opened.request.status ?? 'sin respuesta'} · hooks ${JSON.stringify(hooks)} · cabeceras ${JSON.stringify(headers.slice(0, 6))}${headers.length > 6 ? ` (+${headers.length - 6})` : ''} · filas visibles ${state.rowCount}${state.total !== null ? ` de ${state.total}` : ''} · primeras ${JSON.stringify(rows)}${report.kind === 'dashboard' ? ` · KPI ${JSON.stringify((state.kpis ?? []).map(kpi => `${kpi.label} ${kpi.value}`))}` : ''}${report.kind === 'revenue' ? ` · resumen ${JSON.stringify(state.revenue)} · corte «${state.dimensions?.selected ?? '—'}»` : ''}${empty ? ` · vacío pintado en su lugar («${empty.title ?? '—'}»)` : ''}`

          entry.request = requestOf(opened.request)
          entry.detail = {
            report: report.value,
            kind: report.kind,
            picked: opened.picked,
            hooks,
            headers,
            rows,
            rowCount: state.rowCount,
            total: state.total,
            kpis: state.kpis,
            revenue: state.revenue,
            dimensions: state.dimensions,
            empty,
            consolidated: state.consolidated,
            consolidatedBranch: state.consolidatedBranch
          }

          await shot(page, `shots/bi-${report.value}-data.png`)

          if (opened.request.status !== 200) {
            entry.verdict = 'DEFECTO'
            entry.notes = 'la lectura del reporte no responde 200'
          } else if (paintedData) {
            const headersOk = report.kind === 'dashboard' ? true : headers.length > 0
            entry.verdict = headersOk ? 'OK' : 'DEFECTO'
            entry.notes = headersOk ? '' : 'el reporte pinta su tabla sin cabeceras medibles'
          } else if (empty) {
            entry.verdict = 'NO PROBADO'
            entry.notes =
              'el rango por omisión no devuelve filas para este reporte: el estado medido es el vacío y queda registrado en walk-bi-empty.json'
          } else {
            entry.verdict = 'DEFECTO'
            entry.notes = 'el reporte responde 200 y no pinta ni datos ni un vacío declarado'
          }

          return entry
        }
      )
    }
  } finally {
    await endWalk(page, walk)
  }

  expectWalk(evidence, [
    '01-open',
    '02-data-dashboard',
    '03-data-appointments',
    '04-data-patients',
    '05-data-professionals',
    '06-data-revenue',
    '07-data-consolidated',
    '08-data-utilization'
  ])
})

// --- row 2: EMPTY -----------------------------------------------------------

test('@U10 · capa 4 — BI: el vacío de un período sin actividad', async ({ page }) => {
  test.setTimeout(300000)

  const walk = await beginWalk(page, {
    slug: 'bi-empty',
    title: 'el vacío de un período sin actividad',
    file: 'walk-bi-empty.json'
  })
  const { evidence, step, session } = walk

  page.setDefaultTimeout(8000)

  try {
    const entered = await enterSurface(page, session)

    evidence.entry = {
      viewport: { width: DESKTOP.width, height: DESKTOP.height },
      status: entered.status,
      paint: entered.paint,
      navigation: entered.navigation
    }

    await step(
      'range-2019',
      `Fijar el rango ${EMPTY_RANGE.from} … ${EMPTY_RANGE.to} y aplicarlo`,
      'El período aplicado pasa a «rango manual» con las dos fechas escritas y la lectura responde 200',
      async entry => {
        const written = await setDateRange(page, EMPTY_RANGE.from, EMPTY_RANGE.to)
        const enabled = await awaitApplyEnabled(page)
        const applied = await applyFilters(page)
        const state = await biState(page)
        const from = await page.locator('[data-filters-from] input').first().inputValue()
        const to = await page.locator('[data-filters-to] input').first().inputValue()
        const labelHonest =
          String(state.period ?? '').includes(humanDate(EMPTY_RANGE.from)) &&
          String(state.period ?? '').includes(humanDate(EMPTY_RANGE.to)) &&
          /rango manual/i.test(String(state.period ?? ''))

        entry.obtained = `rango escrito ${written.from} … ${written.to} (ok ${written.ok}) · «Aplicar» habilitado tras ${enabled.waited} ms · inputs ${from} … ${to} · GET ${applied.status ?? 'sin respuesta'}${applied.reason ? ` (${applied.reason})` : ''} · período aplicado «${state.period ?? '—'}»`
        entry.request = appliedRequest(walk.requests, applied)
        entry.detail = { written, enabled, applied, from, to, period: state.period, labelHonest }
        entry.verdict =
          written.ok &&
          applied.status === 200 &&
          labelHonest &&
          from === EMPTY_RANGE.from &&
          to === EMPTY_RANGE.to
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el rango escrito no llega al período aplicado o la lectura no responde 200'

        return entry
      }
    )

    for (const report of REPORTS_UNDER_TEST) {
      await step(
        `empty-${report.value}`,
        `Elegir «${report.label}» con el período sin actividad y leer el estado vacío`,
        'El reporte explica que no hay datos: `[data-report-empty]` con título, descripción y la acción de ampliar el período',
        async entry => {
          const opened = await openReport(page, report.label)
          const state = await snapshot(page)
          const hooks = measured(state.hooks)
          const { empty } = state
          const painted = empty
            ? Boolean(empty.title) && Boolean(empty.description) && Boolean(empty.action)
            : false

          entry.obtained = `${report.label} · GET ${opened.request.status ?? 'sin respuesta'} · hooks ${JSON.stringify(hooks)} · vacío ${empty ? `título «${empty.title ?? '—'}», descripción «${empty.description ?? '—'}», acción «${empty.action ?? '—'}»` : 'ausente'} · filas ${state.rowCount}${state.total !== null ? ` de ${state.total}` : ''} · ${report.kind === 'dashboard' ? `KPI ${JSON.stringify((state.kpis ?? []).map(kpi => `${kpi.label} ${kpi.value}`))}` : `cabeceras ${JSON.stringify(state.headers.slice(0, 5))}`}${state.consolidated ? ` · resumen del consolidado ${JSON.stringify(state.consolidated.summary ?? {})}` : ''}`

          entry.request = requestOf(opened.request)
          entry.detail = {
            report: report.value,
            kind: report.kind,
            hooks,
            empty,
            rowCount: state.rowCount,
            total: state.total,
            kpis: state.kpis,
            headers: state.headers,
            rows: state.rows,
            revenue: state.revenue,
            consolidated: state.consolidated,
            branchRows: state.consolidatedBranch,
            professionalRows: state.consolidatedProfessional
          }

          await shot(page, `shots/bi-${report.value}-empty.png`)

          if (opened.request.status !== 200) {
            entry.verdict = 'DEFECTO'
            entry.notes = 'la lectura del reporte no responde 200 con el período sin actividad'
          } else if (painted) {
            entry.verdict = 'OK'
            entry.notes = ''
          } else if (empty) {
            entry.verdict = 'DEFECTO'
            entry.notes =
              'el vacío aparece pero no publica las tres piezas: título, descripción y la acción que propone'
          } else if (state.hooks.kpis || state.hooks.reportTable || state.hooks.consolidated) {
            entry.verdict = 'CONFUSO'
            entry.notes =
              'el período sin actividad no se distingue de uno con actividad: la superficie pinta su bloque de datos sin declarar que no hay nada en el rango'
          } else {
            entry.verdict = 'DEFECTO'
            entry.notes = 'el período sin actividad no pinta ni datos ni un vacío declarado'
          }

          return entry
        }
      )
    }

    await step(
      'widen',
      'En «Reporte de Ingresos» sin datos, pulsar la acción de ampliar el período',
      'La acción aplica el preset de 90 días, el período aplicado lo declara y el reporte vuelve a pintar datos',
      async entry => {
        const opened = await openReport(page, 'Reporte de Ingresos')
        const before = await snapshot(page)
        const action = page.locator('[data-widen-period]').first()
        const offered = await action.count()

        if (!offered) {
          entry.obtained = `«Reporte de Ingresos» con el período 2019: el vacío ${before.empty ? 'aparece' : 'no aparece'} y no ofrece \`[data-widen-period]\``
          entry.request = requestOf(opened.request)
          entry.detail = { offered: false, before: { hooks: before.hooks, period: before.period } }
          entry.verdict = 'NO PROBADO'
          entry.notes =
            'no hay acción de ampliar el período que medir: el reporte no pintó el vacío con su acción'

          return entry
        }

        const label = flat(await action.innerText().catch(() => ''))
        const request = await respondTo(page, { method: 'GET', path: REPORT_PATH }, () =>
          action.click({ timeout: 8000 })
        )

        await settle(page)

        const after = await snapshot(page)
        const preset = presetLabel('90d')
        const labelHonest = String(after.period ?? '').includes(preset)
        const dataBack = Boolean(
          after.hooks.reportTable || after.hooks.kpis || after.hooks.consolidated
        )

        entry.obtained = `acción «${label}» · GET ${request.status ?? 'sin respuesta'} · período antes «${before.period ?? '—'}» y después «${after.period ?? '—'}» · hooks después ${JSON.stringify(measured(after.hooks))} · filas ${after.rowCount}${after.total !== null ? ` de ${after.total}` : ''}`
        entry.request = requestOf(request)
        entry.detail = {
          label,
          offered: true,
          preset,
          labelHonest,
          dataBack,
          before: { hooks: before.hooks, period: before.period },
          after: { hooks: after.hooks, period: after.period, rowCount: after.rowCount }
        }
        entry.verdict = request.status === 200 && labelHonest && dataBack ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la acción del vacío no aplica el período que anuncia o el reporte sigue sin datos después de ampliarlo'

        return entry
      }
    )
  } finally {
    await endWalk(page, walk)
  }

  expectWalk(evidence, [
    '01-range-2019',
    '02-empty-dashboard',
    '03-empty-appointments',
    '04-empty-patients',
    '05-empty-professionals',
    '06-empty-revenue',
    '07-empty-consolidated',
    '08-empty-utilization',
    '09-widen'
  ])
})

// --- row 3: ERROR -----------------------------------------------------------

test('@U10 · capa 4 — BI: el 500 del reporte y su reintento', async ({ page }) => {
  test.setTimeout(300000)

  const walk = await beginWalk(page, {
    slug: 'bi-error',
    title: 'el 500 del reporte y su reintento',
    file: 'walk-bi-error.json'
  })
  const { evidence, step, session } = walk

  page.setDefaultTimeout(8000)

  try {
    const entered = await enterSurface(page, session)

    evidence.entry = {
      viewport: { width: DESKTOP.width, height: DESKTOP.height },
      status: entered.status,
      paint: entered.paint,
      navigation: entered.navigation
    }

    for (const report of REPORTS_UNDER_TEST) {
      await step(
        `error-${report.value}`,
        `Forzar un 500 en «${report.label}» y reintentar`,
        'El bloque de error se pinta con su título y su descripción, ofrece «Reintentar» y el reintento recupera el reporte',
        async entry => {
          const opened = await openReport(page, report.label)
          const before = await snapshot(page)
          const intercepted = await interceptReport(page, REPORT_PATH, 500)

          const enabled = await awaitApplyEnabled(page)
          const applied = await applyFilters(page)
          await settle(page)

          const failed = await snapshot(page)
          const errorBlock = failed.error
          const capture = await shot(page, `shots/bi-${report.value}-error.png`)

          await releaseReport(page)

          let retried = null
          let recovered = null

          if (errorBlock?.retry) {
            const retry = page.locator('[data-state="error"] [data-retry]').first()

            retried = await respondTo(page, { method: 'GET', path: REPORT_PATH }, () =>
              retry.click({ timeout: 8000 })
            )
            await settle(page)
            recovered = await snapshot(page)
          }

          const dataBack = recovered
            ? Boolean(
                recovered.hooks.reportTable ||
                recovered.hooks.kpis ||
                recovered.hooks.consolidated ||
                recovered.hooks.empty
              )
            : false

          entry.obtained = `${report.label} · lectura previa ${opened.request.status ?? '—'} · 500 forzado ${applied.status ?? 'sin respuesta'} «${clipped(applied.message, 120)}» · bloque de error ${errorBlock ? `«${clipped(errorBlock.text, 180)}»` : 'ausente'} · «Reintentar» ${errorBlock?.retry ? 'ofrecido' : 'ausente'} · reintento ${retried?.status ?? 'sin petición'} · error tras reintentar ${recovered?.error ? 'visible' : 'limpio'} · hooks ${JSON.stringify(measured(recovered?.hooks ?? {}))}`

          entry.request = appliedRequest(walk.requests, applied)
          entry.detail = {
            report: report.value,
            kind: report.kind,
            intercepted,
            enabled,
            applied,
            errorBlock,
            capture,
            retried: requestOf(retried),
            before: { hooks: before.hooks, period: before.period },
            after: recovered ? { hooks: recovered.hooks, error: recovered.error } : null
          }

          if (intercepted.ok !== true) {
            entry.verdict = 'NO PROBADO'
            entry.notes = `no se pudo interceptar la lectura del reporte: ${intercepted.reason ?? 'motivo desconocido'}`
          } else if (applied.status !== 500) {
            entry.verdict = 'DEFECTO'
            entry.notes =
              'el 500 forzado no llegó a la superficie: la lectura no respondió el status interceptado'
          } else if (errorBlock && errorBlock.retry && retried?.status === 200 && dataBack) {
            entry.verdict = 'OK'
            entry.notes = ''
          } else if (errorBlock) {
            entry.verdict = 'DEFECTO'
            entry.notes =
              'el bloque de error no ofrece «Reintentar», el reintento no responde 200 o el reporte no vuelve a pintar su bloque'
          } else {
            entry.verdict = 'DEFECTO'
            entry.notes =
              'un 500 en la lectura no pinta el bloque de error de la superficie: no hay aviso ni reintento'
          }

          return entry
        }
      )
    }
  } finally {
    await releaseReport(page).catch(() => {})

    await endWalk(page, walk, {
      declared: DECLARED_CONSOLE,
      declaredFailure: {
        predicate: failure => failure.status === 500 && REPORT_FAILURE.test(failure.url ?? ''),
        reason:
          'el paso de error provoca un 500 en /api/reports/ para medir el bloque y su reintento'
      }
    })
  }

  expectWalk(evidence, [
    '01-error-dashboard',
    '02-error-appointments',
    '03-error-patients',
    '04-error-professionals',
    '05-error-revenue',
    '06-error-consolidated',
    '07-error-utilization'
  ])
})

// --- row 4: EXPORT vs SCREEN ------------------------------------------------

test('@U10 · capa 4 — BI: la exportación contra la pantalla', async ({ page }) => {
  test.setTimeout(420000)

  const walk = await beginWalk(page, {
    slug: 'bi-export',
    title: 'la exportación contra la pantalla',
    file: 'walk-bi-export.json'
  })
  const { evidence, step, session } = walk

  page.setDefaultTimeout(8000)

  try {
    const entered = await enterSurface(page, session)

    evidence.entry = {
      viewport: { width: DESKTOP.width, height: DESKTOP.height },
      status: entered.status,
      paint: entered.paint,
      navigation: entered.navigation
    }

    for (const report of EXPORTABLE) {
      await step(
        `export-${report.value}`,
        `Descargar CSV, Excel y PDF de «${report.label}» y compararlos con la pantalla`,
        'Las tres descargas responden 200, las cabeceras y al menos un valor coinciden con la pantalla y el número de filas se explica (paginación o corte de ingresos)',
        async entry => {
          const opened = await openReport(page, report.label)
          const screen = await snapshot(page)
          const formats = []

          for (const format of FORMATS) {
            formats.push(await measureExport(page, { report, format, screen }))
            await settle(page)
          }

          const text = formats.filter(item => item.parser !== 'pdf')
          const pdf = formats.find(item => item.parser === 'pdf') ?? null
          const everyDownload = formats.every(item => item.ok === true)
          const everyStatus = formats.every(item => item.status === 200)
          const textOk = text.every(
            item =>
              item.match.headers !== false && item.match.rows === true && item.match.sample === true
          )
          const pdfOk = pdf ? pdf.ok === true && pdf.pdf?.magic === true : false
          const extensionsOk = formats.every(item => item.extensionOk === true)
          const comparable = formats.some(item => item.match.rows !== null)

          // One readable sentence per mismatch, naming the format and both
          // sides: a DEFECTO has to say which value disagrees, not that "the
          // comparison failed".
          const mismatches = text.flatMap(item => {
            const found = []

            if (item.match.headers === false) {
              found.push(
                `${item.format}: cabeceras distintas (pantalla ${JSON.stringify(item.screenHeaders)} frente a archivo ${JSON.stringify(item.exportHeaders)}${item.headerDrift?.length ? `, ${item.headerDrift.length} etiqueta(s) divergentes` : ''})`
              )
            }
            if (item.match.rows === false) {
              found.push(
                `${item.format}: filas distintas (archivo ${item.exportRows ?? '—'} frente a la pantalla ${item.screenRows ?? '—'})`
              )
            }
            if (item.match.sample === false) {
              found.push(
                `${item.format}: la muestra no coincide (pantalla «${item.sample?.screen ?? '—'}» frente a archivo «${item.sample?.export ?? '—'}»)`
              )
            }

            return found
          })

          entry.obtained = `${report.label} · pantalla ${screen.headers.length > 0 ? `${screen.headers.length} cabecera(s) ${JSON.stringify(screen.headers.slice(0, 5))}` : `${(screen.kpis ?? []).length} KPI`}, ${screen.dataRows} fila(s) visibles${screen.total !== null ? ` de ${screen.total}` : ''}${screen.dimensions?.selected ? `, corte «${screen.dimensions.selected}»` : ''} · ${formats.map(item => `${item.format}: ${item.status ?? 'sin respuesta'} «${item.filename ?? '—'}» ${item.bytes ?? '—'} B, cabeceras ${item.exportHeaders?.length ?? '—'}, filas ${item.exportRows ?? '—'} de ${item.exportRowsTotal ?? '—'} (pantalla ${item.screenRows ?? '—'}), coincidencia ${JSON.stringify(item.match)}${item.notes.length > 0 ? ` — ${item.notes.join('; ')}` : ''}`).join(' · ')}`

          entry.request = requestOf(opened.request)
          entry.detail = {
            report: report.value,
            kind: report.kind,
            screen: {
              headers: screen.headers,
              rows: screen.rows,
              dataRows: screen.dataRows,
              total: screen.total,
              dimension: screen.dimensions?.selected ?? null,
              kpis: screen.kpis,
              revenue: screen.revenue,
              professionalRows: screen.professional?.dataRows ?? null
            },
            exports: formats,
            differences: {
              exportRowsTotal: text[0]?.exportRowsTotal ?? null,
              exportRowsCompared: text[0]?.exportRows ?? null,
              screenTotal: text[0]?.screenTotal ?? null,
              screenVisible: text[0]?.screenVisible ?? screen.dataRows,
              screenPaged: text[0]?.screenPaged ?? false,
              cutsInExport: text[0]?.cutsInExport ?? null,
              dimensionOnScreen: screen.dimensions?.selected ?? null
            },
            mismatches,
            summary: { everyDownload, everyStatus, textOk, pdfOk, extensionsOk, comparable }
          }

          entry.verdict =
            everyDownload && everyStatus && textOk && pdfOk && extensionsOk && comparable
              ? 'OK'
              : 'DEFECTO'
          entry.notes =
            entry.verdict === 'OK'
              ? [
                  report.kind === 'revenue'
                    ? `el export trae los ${text[0]?.cutsInExport ?? '—'} cortes de la misma plata: sólo el corte visible «${screen.dimensions?.selected ?? '—'}» se compara fila a fila`
                    : '',
                  report.kind === 'dashboard'
                    ? `el export aplana el dashboard en ${text[0]?.exportRowsTotal ?? '—'} filas de Sección/Concepto/Valor: la comparación fila a fila es la sección «Rendimiento por profesional» (${text[0]?.exportRows ?? '—'}) frente a la tabla de la pantalla`
                    : '',
                  text[0]?.screenPaged
                    ? `la pantalla pagina (${screen.dataRows} visibles de ${screen.total}) y el archivo lleva las ${text[0]?.exportRowsTotal ?? '—'} filas de la población`
                    : ''
                ]
                  .filter(Boolean)
                  .join('; ')
              : mismatches.length > 0
                ? mismatches.join(' · ')
                : !everyDownload || !everyStatus
                  ? 'alguna de las tres descargas no se completó o no respondió 200'
                  : !pdfOk
                    ? 'el PDF descargado no empieza con la firma %PDF'
                    : !extensionsOk
                      ? 'alguna descarga no lleva la extensión del formato que pidió'
                      : 'la comparación no pudo establecerse: ningún formato publicó filas comparables'

          return entry
        }
      )
    }

    await step(
      'export-consolidated',
      'Elegir «Consolidado por Sede» y comprobar que no se exporta',
      'Los tres botones de exportación están deshabilitados y la cabecera explica que el consolidado no se exporta',
      async entry => {
        const opened = await openReport(page, 'Consolidado por Sede')
        const state = await snapshot(page)
        const { exportHint: hint, buttons } = state
        const allDisabled =
          buttons.length === 3 && buttons.every(button => button.disabled === true)
        const labelled = buttons.every(button => Boolean(button.title))

        entry.obtained = `GET ${opened.request.status ?? 'sin respuesta'} · botones ${JSON.stringify(buttons.map(button => `${button.label ?? button.format}:${button.disabled === true ? 'deshabilitado' : 'habilitado'} (title ${button.title ? 'sí' : 'no'})`))} · aviso «${clipped(hint, 180) || 'ausente'}»`
        entry.request = requestOf(opened.request)
        entry.detail = { buttons, hint, allDisabled, labelled }
        entry.verdict =
          opened.request.status === 200 && allDisabled && labelled && Boolean(hint)
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el consolidado ofrece una exportación que la API no publica, o no explica por qué no se descarga'

        await shot(page, 'shots/bi-consolidated-export.png')

        return entry
      }
    )
  } finally {
    await endWalk(page, walk)
  }

  expectWalk(evidence, [
    '01-export-dashboard',
    '02-export-appointments',
    '03-export-patients',
    '04-export-professionals',
    '05-export-revenue',
    '06-export-utilization',
    '07-export-consolidated'
  ])
})

// --- row 5: RANGE and FILTERS ----------------------------------------------

test('@U10 · capa 4 — BI: presets, filtros y rango aplicado', async ({ page }) => {
  test.setTimeout(300000)

  const walk = await beginWalk(page, {
    slug: 'bi-range',
    title: 'presets, filtros y rango aplicado',
    file: 'walk-bi-range.json'
  })
  const { evidence, step, session } = walk

  page.setDefaultTimeout(8000)

  try {
    const entered = await enterSurface(page, session)

    evidence.entry = {
      viewport: { width: DESKTOP.width, height: DESKTOP.height },
      status: entered.status,
      paint: entered.paint,
      navigation: entered.navigation
    }

    const opened = await openReport(page, 'Reporte de Citas')
    const baseline = await snapshot(page)

    // The populations the three filters publish, read once: the labels are what
    // the primitive needs to select them, and the list itself is evidence.
    const professionalOptions = await readSelectOptions(
      page,
      '[data-filters-professional] > button'
    )
    const environmentOptions = await readSelectOptions(page, '[data-filters-environment] > button')
    const branchOptions = await readSelectOptions(page, '[data-filters-branch] > button')

    const firstChoice = options =>
      (options.options ?? []).find(label => !/^tod[oa]s/i.test(label)) ?? null

    // What each trigger prints for the value it holds. The page's "todos" option
    // is the first of each list, so the trigger has one honest answer while no
    // filter is applied — and it is measured before anything narrows the report,
    // which is what makes the reset step's reading attributable.
    const filtersAt = (triggers, appliedValues) =>
      [
        {
          id: 'professional',
          parameter: 'professional_id',
          trigger: triggers.professional,
          appliedOption: (professionalOptions.options ?? [])[0] ?? null,
          appliedValue: appliedValues.professional_id ?? null
        },
        {
          id: 'environment',
          parameter: 'environment_id',
          trigger: triggers.environment,
          appliedOption: (environmentOptions.options ?? [])[0] ?? null,
          appliedValue: appliedValues.environment_id ?? null
        },
        {
          id: 'branch',
          parameter: 'branch_id',
          trigger: triggers.branch,
          appliedOption: (branchOptions.options ?? [])[0] ?? null,
          appliedValue: appliedValues.branch_id ?? null
        }
      ].map(filter => ({ ...filter, matches: filter.trigger === filter.appliedOption }))

    const baselineTriggers = filtersAt(await selectTriggers(page), {})

    await step(
      'baseline',
      'Abrir «Reporte de Citas» con el rango por omisión y leer las opciones de los tres filtros',
      'La superficie aplica 30 días, la tabla publica filas y cada filtro ofrece su población con la opción «todos» delante',
      async entry => {
        const state = await snapshot(page)
        const complete = [professionalOptions, environmentOptions, branchOptions].every(
          options => options.ok === true && (options.options ?? []).length > 1
        )

        entry.obtained = `período «${state.period ?? '—'}» · GET ${opened.request.status ?? 'sin respuesta'} · ${state.dataRows} fila(s) visibles${state.total !== null ? ` de ${state.total}` : ''} · profesionales ${JSON.stringify((professionalOptions.options ?? []).slice(0, 4))} · ambientes ${JSON.stringify((environmentOptions.options ?? []).slice(0, 4))} · sedes ${JSON.stringify((branchOptions.options ?? []).slice(0, 4))}`
        entry.request = requestOf(opened.request)
        entry.detail = {
          period: state.period,
          dataRows: state.dataRows,
          total: state.total,
          triggers: baselineTriggers,
          baseline,
          professionalOptions,
          environmentOptions,
          branchOptions
        }
        entry.verdict =
          opened.request.status === 200 && /30 días/.test(String(state.period)) && complete
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el rango por omisión no es de 30 días o alguno de los tres filtros no publica su población'

        return entry
      }
    )

    for (const preset of PRESETS) {
      await step(
        `preset-${preset.id}`,
        `Pulsar el preset «${preset.label}»`,
        'El período aplicado nombra ese preset, los dos campos de fecha lo acompañan y la lectura se re-issue',
        async entry => {
          const before = await snapshot(page)
          const request = await respondTo(page, { method: 'GET', path: REPORT_PATH }, () =>
            page.locator(`[data-preset="${preset.id}"]`).first().click({ timeout: 8000 })
          )

          await settle(page)

          const after = await snapshot(page)
          const from = await page.locator('[data-filters-from] input').first().inputValue()
          const to = await page.locator('[data-filters-to] input').first().inputValue()
          const honest =
            String(after.period ?? '').includes(preset.label) &&
            String(after.period ?? '').includes(humanDate(from))

          entry.obtained = `período antes «${before.period ?? '—'}» · GET ${request.status ?? 'sin respuesta'} · período después «${after.period ?? '—'}» · inputs ${from} … ${to} · filas ${after.dataRows}${after.total !== null ? ` de ${after.total}` : ''}`
          entry.request = requestOf(request)
          entry.detail = {
            preset: preset.id,
            label: preset.label,
            before: before.period,
            after: after.period,
            from,
            to,
            honest
          }
          entry.verdict = request.status === 200 && honest ? 'OK' : 'DEFECTO'
          entry.notes =
            entry.verdict === 'OK'
              ? ''
              : 'el preset no re-issue la lectura o el período aplicado no nombra las fechas que deja en los campos'

          return entry
        }
      )
    }

    const filters = [
      {
        id: 'professional',
        title: 'profesional',
        selector: '[data-filters-professional] > button',
        parameter: 'professional_id',
        label: firstChoice(professionalOptions)
      },
      {
        id: 'environment',
        title: 'ambiente',
        selector: '[data-filters-environment] > button',
        parameter: 'environment_id',
        label: firstChoice(environmentOptions)
      },
      {
        id: 'branch',
        title: 'sede',
        selector: '[data-filters-branch] > button',
        parameter: 'branch_id',
        label: firstChoice(branchOptions)
      }
    ]

    for (const filter of filters) {
      await step(
        `filter-${filter.id}`,
        `Elegir «${filter.label ?? 'la primera opción'}» en el filtro de ${filter.title} y aplicar`,
        'La lectura lleva el parámetro del filtro, la población no crece y, en la sede, la cabecera publica la sede aplicada',
        async entry => {
          const before = await snapshot(page)

          if (!filter.label) {
            entry.obtained = `el filtro de ${filter.title} no publicó ninguna opción más allá de «todos»`
            entry.detail = { filter }
            entry.verdict = 'NO PROBADO'
            entry.notes = 'sin población que elegir: el selector sólo ofrece la opción vacía'

            return entry
          }

          const picked = await pickOption(page, filter.selector, filter.label)

          if (!picked.ok) {
            entry.obtained = `no se pudo elegir «${filter.label}» en el filtro de ${filter.title}: ${picked.reason ?? 'sin motivo'}`
            entry.detail = { filter, picked }
            entry.verdict = 'NO PROBADO'
            entry.notes = 'el selector no aceptó la opción publicada'

            return entry
          }

          const enabled = await awaitApplyEnabled(page)
          const applied = await applyFilters(page, 25000)
          await settle(page)

          const sent = lastReportReadWith(walk.requests, filter.parameter)
          const after = await snapshot(page)
          const triggers = await selectTriggers(page)
          const trigger = triggers[filter.id] ?? null
          const carries = String(sent?.query?.[filter.parameter] ?? '') !== ''
          const labelOk =
            filter.id === 'branch'
              ? Boolean(after.branch) && String(after.branch).includes(filter.label)
              : true
          const triggerReflects = trigger === filter.label
          const narrowed = (after.total ?? after.dataRows) <= (before.total ?? before.dataRows)

          entry.obtained = `«${filter.label}» elegido (${picked.match}) · GET ${applied.status ?? 'sin respuesta'} · petición con ${filter.parameter}=«${sent?.query?.[filter.parameter] ?? '—'}» · trigger «${trigger ?? '—'}» ${triggerReflects ? 'refleja' : 'no refleja'} el valor · filas ${before.dataRows}${before.total !== null ? ` de ${before.total}` : ''} → ${after.dataRows}${after.total !== null ? ` de ${after.total}` : ''} · sede aplicada «${after.branch ?? '—'}» · período «${after.period ?? '—'}»`
          entry.request = appliedRequest(walk.requests, applied)
          entry.detail = {
            filter,
            picked,
            applied,
            enabled,
            carries,
            trigger,
            triggerReflects,
            sent: sent ? { path: sent.path, query: sent.query, status: sent.status } : null,
            before: { dataRows: before.dataRows, total: before.total, branch: before.branch },
            after: {
              dataRows: after.dataRows,
              total: after.total,
              branch: after.branch,
              empty: Boolean(after.empty)
            },
            labelOk,
            narrowed
          }
          entry.verdict =
            applied.status === 200 && carries && labelOk && narrowed && triggerReflects
              ? 'OK'
              : 'DEFECTO'
          entry.notes =
            entry.verdict === 'OK'
              ? after.empty
                ? 'el filtro deja el reporte sin filas: el vacío es el estado medido (walk-bi-empty.json)'
                : ''
              : !carries
                ? 'la lectura no lleva el parámetro del filtro elegido'
                : !triggerReflects
                  ? `el selector no refleja el valor elegido: el trigger imprime «${trigger ?? '—'}» y se aplicó «${filter.label}»`
                  : filter.id === 'branch' && !labelOk
                    ? 'la sede elegida no se publica en la cabecera del período'
                    : 'la población del reporte crece al aplicar un filtro'

          return entry
        }
      )
    }

    await step(
      'reset',
      'Pulsar «Limpiar filtros»',
      'La superficie vuelve al rango por omisión de 30 días, los tres filtros quedan en «todos», la sede aplicada desaparece y la lectura se re-issue sin filtros',
      async entry => {
        const before = await snapshot(page)
        const request = await respondTo(page, { method: 'GET', path: REPORT_PATH }, () =>
          page.locator('[data-filters-reset]').first().click({ timeout: 8000 })
        )

        await settle(page)

        const after = await snapshot(page)
        const sent = lastReportRead(walk.requests)
        const from = await page.locator('[data-filters-from] input').first().inputValue()
        const to = await page.locator('[data-filters-to] input').first().inputValue()
        const query = sent?.query ?? {}

        // Per select: what the trigger prints and which option the request
        // actually applied. A trigger that prints the placeholder while the read
        // goes out with "no filter" is the gap; recording both sides is what lets
        // triage name it without re-running the walk.
        const filters = filtersAt(await selectTriggers(page), query)
        const cleared =
          String(query.professional_id ?? '') === '' &&
          String(query.environment_id ?? '') === '' &&
          query.branch_id === undefined
        const periodOk = /30 días/.test(String(after.period))
        const branchOk = !after.branch
        const triggersOk = filters.every(filter => filter.matches)
        const honest = periodOk && branchOk && triggersOk && cleared

        entry.obtained = `período antes «${before.period ?? '—'}» (sede «${before.branch ?? '—'}») · GET ${request.status ?? 'sin respuesta'} · período después «${after.period ?? '—'}» · inputs ${from} … ${to} · filtros ${JSON.stringify(filters.map(filter => `${filter.id}: «${filter.trigger ?? '—'}» (aplicado ${JSON.stringify(filter.appliedValue)} → «${filter.appliedOption ?? '—'}»)${filter.matches ? '' : ' ✗'} · arranque «${(baselineTriggers.find(item => item.id === filter.id) ?? {}).trigger ?? '—'}»`))} · sede aplicada «${after.branch ?? '—'}» · consulta ${JSON.stringify(query)} · filas ${after.dataRows}${after.total !== null ? ` de ${after.total}` : ''}`
        entry.request = requestOf(request)
        entry.detail = {
          before: { period: before.period, branch: before.branch },
          after: { period: after.period, branch: after.branch, dataRows: after.dataRows },
          from,
          to,
          filters,
          baselineTriggers,
          cleared,
          periodOk,
          branchOk,
          triggersOk,
          honest,
          sent: sent ? { path: sent.path, query: sent.query, status: sent.status } : null
        }
        entry.verdict = request.status === 200 && honest ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : !periodOk
              ? 'el reinicio no devuelve el período por omisión de 30 días'
              : !cleared
                ? 'la lectura posterior al reinicio sigue llevando un filtro aplicado'
                : !branchOk
                  ? 'la sede aplicada sobrevive al reinicio'
                  : `los selectores de profesional y ambiente no reflejan el valor «todos»: ${filters
                      .filter(item => !item.matches)
                      .map(
                        item =>
                          `${item.id} imprime «${item.trigger ?? '—'}» mientras la opción aplicada es «${item.appliedOption ?? '—'}» (valor ${JSON.stringify(item.appliedValue)})`
                      )
                      .join(
                        ' y '
                      )}; el selector de sede, cuyo valor vacío es null, sí imprime «${filters.find(item => item.id === 'branch')?.trigger ?? '—'}». La condición ya está en el arranque (${
                      baselineTriggers
                        .filter(item => !item.matches)
                        .map(item => `${item.id} «${item.trigger}»`)
                        .join(', ') || 'no'
                    }), así que el reinicio la deja ver, no la introduce`

        return entry
      }
    )
  } finally {
    await endWalk(page, walk)
  }

  expectWalk(evidence, [
    '01-baseline',
    '02-preset-7d',
    '03-preset-30d',
    '04-preset-90d',
    '05-filter-professional',
    '06-filter-environment',
    '07-filter-branch',
    '08-reset'
  ])
})

// --- row 6: CONSOLIDATED and 390 px ---------------------------------------

test('@U10 · capa 4 — BI: consolidado por sede y gráficos a 390 px', async ({ page }) => {
  test.setTimeout(240000)

  const walk = await beginWalk(page, {
    slug: 'bi-mobile',
    title: 'consolidado por sede y gráficos a 390 px',
    file: 'walk-bi-mobile.json'
  })
  const { evidence, step, session } = walk

  page.setDefaultTimeout(8000)

  try {
    const entered = await enterSurface(page, session)

    evidence.entry = {
      viewport: { width: DESKTOP.width, height: DESKTOP.height },
      status: entered.status,
      paint: entered.paint,
      navigation: entered.navigation
    }

    await step(
      'consolidated',
      'Elegir «Consolidado por Sede» y leer sus tres secciones',
      'El consolidado pinta su período, su resumen (neto, devoluciones, atenciones pagadas), la tabla por sede y la tabla por profesional, y el selector de sede existe porque el alcance es de grupo',
      async entry => {
        const opened = await openReport(page, 'Consolidado por Sede')
        const state = await snapshot(page)
        const summary = state.consolidated?.summary ?? {}
        const branchTable = state.consolidatedBranch
        const professionalTable = state.consolidatedProfessional
        const branchSelector = await page.locator('[data-filters-branch]').count()
        const values = Object.values(summary).filter(Boolean)

        entry.obtained = `GET ${opened.request.status ?? 'sin respuesta'} · período «${state.consolidated?.period ?? '—'}» · resumen ${JSON.stringify(summary)} · por sede ${branchTable?.dataRows ?? 0} fila(s), cabeceras ${JSON.stringify(branchTable?.headers ?? [])} · por profesional ${professionalTable?.dataRows ?? 0} fila(s), cabeceras ${JSON.stringify(professionalTable?.headers ?? [])} · selector de sede ${branchSelector > 0 ? 'presente' : 'ausente'} · exportaciones ${JSON.stringify(state.buttons.map(button => `${button.label ?? button.format}:${button.disabled === true ? 'deshabilitado' : 'habilitado'}`))}`
        entry.request = requestOf(opened.request)
        entry.detail = {
          period: state.consolidated?.period ?? null,
          summary,
          branchTable,
          professionalTable,
          branchSelector,
          buttons: state.buttons
        }
        entry.verdict =
          opened.request.status === 200 &&
          state.consolidated?.hasBranch === true &&
          state.consolidated?.hasProfessional === true &&
          Boolean(state.consolidated?.period) &&
          values.length === 3 &&
          (branchTable?.headers.length ?? 0) > 0 &&
          (professionalTable?.headers.length ?? 0) > 0 &&
          branchSelector > 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el consolidado no publica sus tres secciones con cabeceras y valores, o el selector de sede falta a un alcance de grupo'

        await shot(page, 'shots/bi-consolidated.png')

        return entry
      }
    )

    await step(
      'charts-390',
      'Recargar el dashboard a 390×844 y medir el desbordamiento y los gráficos',
      'El documento no desborda en horizontal, los dos gráficos del dashboard pintan su canvas y la tira de KPI sigue visible',
      async entry => {
        await page.setViewportSize(MOBILE)
        await gotoSurface(page, SURFACE)

        // Chart.js is a dynamic import: the canvas exists as soon as the series
        // are non-empty, but the instance is created a tick later. The wait is
        // bounded so a chart that never draws is still measured as not painted.
        await page
          .waitForFunction(
            () =>
              Array.from(document.querySelectorAll('[data-bi-charts] canvas')).every(
                canvas => canvas.getBoundingClientRect().height > 0
              ),
            undefined,
            { timeout: 6000, polling: 150 }
          )
          .catch(() => {})
        await page.waitForTimeout(700)

        const state = await biState(page)
        const pageOverflow = await overflow(page)
        const canvases = state.charts?.canvases ?? []
        const painted = canvases.filter(canvas => canvas.width > 0 && canvas.height > 0)
        const capture = await shot(page, 'shots/bi-charts-390.png')

        entry.obtained = `viewport ${MOBILE.width}×${MOBILE.height} · scrollWidth ${pageOverflow.scrollWidth} vs innerWidth ${pageOverflow.innerWidth} → desborde ${pageOverflow.overflow} · tarjetas ${JSON.stringify(state.charts?.cards ?? [])} · canvas ${JSON.stringify(canvases)} · KPI ${(state.kpiValues ?? []).length} · hooks ${JSON.stringify(measured({ kpis: state.kpis, charts: Boolean(state.charts) }))}`
        entry.request = null
        entry.detail = {
          viewport: MOBILE,
          pageOverflow,
          cards: state.charts?.cards ?? [],
          canvases,
          painted: painted.length,
          kpis: state.kpiValues,
          capture
        }
        entry.verdict =
          pageOverflow.overflow === false &&
          Boolean(state.charts) &&
          painted.length === canvases.length
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : pageOverflow.overflow
              ? `el documento desborda en horizontal a ${MOBILE.width} px (scrollWidth ${pageOverflow.scrollWidth})`
              : state.charts
                ? `alguno de los ${canvases.length} gráfico(s) del dashboard no pintó su canvas`
                : 'el dashboard no pintó su bloque de gráficos a 390 px'

        return entry
      }
    )
  } finally {
    await endWalk(page, walk)
  }

  expectWalk(evidence, ['01-consolidated', '02-charts-390'])
})

// --- row 7: the merged artefact -------------------------------------------

test('@U10 · capa 4 — BI: informe del recorrido', async () => {
  test.setTimeout(60000)

  const BLOCKS = [
    { block: 'data', file: 'walk-bi-data.json' },
    { block: 'empty', file: 'walk-bi-empty.json' },
    { block: 'error', file: 'walk-bi-error.json' },
    { block: 'export', file: 'walk-bi-export.json' },
    { block: 'range', file: 'walk-bi-range.json' },
    { block: 'mobile', file: 'walk-bi-mobile.json' }
  ]

  // This row does not measure: it joins the six artefacts so a generator that
  // expects one `walk-bi.json` per surface finds the same steps the blocks
  // wrote, each one labelled with the block that produced it.
  const read = BLOCKS.map(declared => ({ ...declared, walk: readEvidence(declared.file) }))
  const missing = read.filter(entry => entry.walk === null).map(entry => entry.file)

  const steps = read.flatMap(entry =>
    (entry.walk?.steps ?? []).map(step => ({ ...step, block: entry.block, file: entry.file }))
  )

  const merged = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks Business Intelligence',
    role: ROLE,
    reports: REPORTS_UNDER_TEST.map(report => report.label),
    blocks: read.map(entry => ({
      block: entry.block,
      file: entry.file,
      present: entry.walk !== null,
      slug: entry.walk?.slug ?? null,
      steps: (entry.walk?.steps ?? []).length,
      counts: entry.walk?.counts ?? null
    })),
    missing,
    steps,
    counts: walkCounts(steps),
    pageErrors: read.flatMap(entry =>
      (entry.walk?.pageErrors ?? []).map(error => ({ block: entry.block, error }))
    ),
    consoleErrors: read.flatMap(entry =>
      (entry.walk?.consoleErrors ?? []).map(message => ({ block: entry.block, message }))
    ),
    consoleClassified: {
      expected: read.flatMap(entry =>
        (entry.walk?.consoleClassified?.expected ?? []).map(item => ({
          block: entry.block,
          ...item
        }))
      ),
      unexpected: read.flatMap(entry =>
        (entry.walk?.consoleClassified?.unexpected ?? []).map(item => ({
          block: entry.block,
          ...item
        }))
      )
    },
    failedRequests: {
      expected: read.flatMap(entry =>
        (entry.walk?.failedRequests?.expected ?? []).map(item => ({ block: entry.block, ...item }))
      ),
      unexpected: read.flatMap(entry =>
        (entry.walk?.failedRequests?.unexpected ?? []).map(item => ({
          block: entry.block,
          ...item
        }))
      )
    },
    fixtures: read.find(entry => entry.walk?.fixtures)?.walk?.fixtures ?? null
  }

  audit('walk-bi', merged)

  expect(missing, `bloques del recorrido de BI ausentes: ${missing.join(', ')}`).toEqual([])
  expect(merged.steps.length, 'acciones del recorrido de BI').toBeGreaterThan(0)
  expect(
    merged.steps.every(step => VERDICT_VOCABULARY.includes(step.verdict)),
    'veredictos de las acciones del recorrido de BI'
  ).toBe(true)
})
