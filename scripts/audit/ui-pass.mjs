#!/usr/bin/env node
// Plan #13 (issue #363, U0) — layer 4: turn the browser evidence into a verdict.
//
// The spec in tests/e2e/audit/surface.spec.js writes one JSON file per surface
// and role (`render/`) plus one per dialog contract (`primitives/`). This CLI
// runs that pass against a listening app, classifies every manifest surface
// against the exit criterion, and writes the gallery a human reads.
//
// Classification, aggregation and the gallery are pure functions: the unit test
// feeds them fixtures without a browser, an app or evidence on disk. Only the
// CLI block touches the filesystem, the environment or a child process.

import { mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

import { CONTENT_THRESHOLDS, slugFor } from '../../tests/e2e/audit/harness.js'
import { ROOT } from './manifest/text.mjs'
import { buildManifest } from './ui-manifest.mjs'

const DEFAULT_OUT = '.atl/qa-evidence/audit/ui/U0'
const DEFAULT_BASE = 'http://localhost:8000'
const PREFLIGHT_TIMEOUT_MS = 10000

// The layer-2 floor lives in tests/e2e/audit/surface.spec.js (`DEFAULT_THRESHOLD`);
// the per-route exceptions live in the harness. Mirrored here only to word the
// "content is too short" fact, never to decide a verdict.
const DEFAULT_CONTENT_THRESHOLD = 80

// The role plan the spec renders: desk owner, administrator, a negative role and
// the one anonymous surface. Gallery figures follow that order, then viewport.
const ROLE_PLAN_ORDER = ['owner', 'admin', 'negative', 'anonymous']
const VIEWPORT_ORDER = ['desktop', 'mobile']

// --- pure helpers -----------------------------------------------------------

const asArray = value => (Array.isArray(value) ? value.filter(item => item != null) : [])

const unique = values => [...new Set(values)]

const hasReason = row => typeof row?.reason === 'string' && row.reason.length > 0

/**
 * Classifies one manifest surface from the render evidence rows recorded for it.
 *
 * A row with a `reason` was skipped before it exercised the surface (for example
 * `no-sample-record`), so it carries no diagnostics: a surface whose rows were
 * all skipped is `excluded`, and a mixed set is judged by the rows that ran.
 * Contract violations and console errors are evidence, not gate inputs here.
 */
export const classifySurface = (surface, rows) => {
  const route = surface?.route ?? ''
  const evidence = asArray(rows)

  const classified = {
    route,
    slug: slugFor(route),
    status: 'missing',
    reason: 'sin evidencia',
    roles: [...asArray(surface?.roles)],
    pageErrors: [],
    consoleErrors: [],
    unexpectedRequests: [],
    expectedRequests: [],
    shots: []
  }

  if (evidence.length === 0) return classified

  const skipped = evidence.filter(hasReason)
  const live = evidence.filter(row => !hasReason(row))

  if (live.length === 0) {
    return { ...classified, status: 'excluded', reason: skipped[0].reason }
  }

  const pageErrors = unique(live.flatMap(row => asArray(row.pageErrors).map(String)))
  const consoleErrors = unique(live.flatMap(row => asArray(row.consoleErrors).map(String)))
  const unexpectedRequests = live.flatMap(row =>
    asArray(row.failedRequests?.unexpected).map(entry => ({
      status: entry?.status ?? null,
      url: entry?.url ?? ''
    }))
  )
  // Declared allowlist (layer 2): the 403s the instrument already explains are
  // reported as diagnostics, never as the surface's verdict.
  const expectedRequests = live.flatMap(row =>
    asArray(row.failedRequests?.expected).map(entry => ({
      status: entry?.status ?? null,
      url: entry?.url ?? '',
      channel: entry?.channel ?? null,
      reason: entry?.reason ?? null
    }))
  )
  const shots = unique(live.flatMap(row => asArray(row.shots).map(String)))

  const threshold = CONTENT_THRESHOLDS[route] ?? DEFAULT_CONTENT_THRESHOLD
  const facts = []
  const record = fact => {
    if (!facts.includes(fact)) facts.push(fact)
  }

  for (const row of live) {
    for (const error of asArray(row.pageErrors)) record(`pageerror: ${String(error)}`)

    if (row.httpStatus !== 200) record(`http ${row.httpStatus ?? 'sin respuesta'}`)

    if (row.rendered !== true) {
      record(
        Number.isFinite(row.contentLength)
          ? `contenido ${row.contentLength} ≤ ${threshold}`
          : 'contenido no medido'
      )
    }

    if (row.horizontalOverflow === true) {
      const scrollWidth = row.viewports?.mobile?.scrollWidth
      record(`overflow ${Number.isFinite(scrollWidth) ? scrollWidth : '?'}px`)
    }

    for (const request of asArray(row.failedRequests?.unexpected)) {
      record(`solicitud ${request?.status ?? 'fallida'}`)
    }
  }

  return {
    ...classified,
    status: facts.length === 0 ? 'renders' : 'not-renders',
    reason: facts.length === 0 ? null : facts.join(' · '),
    pageErrors,
    consoleErrors,
    unexpectedRequests,
    expectedRequests,
    shots
  }
}

const unclassifiedActionCount = manifest => {
  const declared = manifest?.summary?.unclassifiedActions
  if (Number.isFinite(declared)) return declared

  return asArray(manifest?.surfaces)
    .flatMap(surface => asArray(surface?.actions))
    .filter(action => action?.kind === 'unclassified').length
}

/**
 * Computes the exit criterion from the classified rows.
 *
 * The gate fails on the facts the issue names: a surface without a
 * classification, an unclassified button, or a page error. A surface that does
 * not render is a classified finding, not an instrument failure — U0 does not
 * fix product code. The one exception is a platform defect: when more than half
 * the scope does not render, the plan's abort rule applies and the run fails.
 * Excluded surfaces are allowed because each one carries its motive, and
 * primitive-contract violations are reported without gating the run.
 */
export const gate = (rows, counts = {}) => {
  const classified = asArray(rows)
  const unclassified = counts.unclassifiedActions ?? 0
  const missing = classified.filter(row => row?.status === 'missing').length
  const notRendering = classified.filter(row => row?.status === 'not-renders').length
  const pageErrors = classified.reduce((total, row) => total + asArray(row?.pageErrors).length, 0)
  const extraPageErrors = asArray(counts.extraPageErrors)

  const reasons = []
  const findings = []

  for (const row of classified) {
    for (const error of asArray(row?.pageErrors)) reasons.push(`pageerror: ${row.route} · ${error}`)
    if (row?.status === 'not-renders') findings.push(`no renderiza: ${row.route} — ${row.reason}`)
    if (row?.status === 'missing') reasons.push(`sin evidencia: ${row?.route}`)
  }

  // A page error during the dialog probe is still a page error of the run.
  for (const entry of extraPageErrors) {
    reasons.push(`pageerror: ${entry?.route ?? '?'} · ${entry?.error ?? ''}`)
  }

  if (unclassified > 0) reasons.push(`acciones sin clasificar: ${unclassified}`)

  const platformDefect = notRendering > Math.floor(classified.length / 2)

  if (platformDefect) {
    reasons.push(
      `defecto de plataforma: ${notRendering}/${classified.length} superficies no renderizan`
    )
  }

  return {
    ok:
      unclassified === 0 &&
      missing === 0 &&
      pageErrors === 0 &&
      extraPageErrors.length === 0 &&
      !platformDefect,
    reasons,
    findings
  }
}

/**
 * Aggregates every surface into the verdict the CLI prints.
 *
 * Console errors and unexpected requests are counted for the report but never
 * gate the run: they are the diagnostic payload of a surface that rendered.
 */
export const aggregate = (manifest, renderRows, primitiveRows) => {
  const surfaces = asArray(manifest?.surfaces)
  const evidence = asArray(renderRows)
  const primitives = asArray(primitiveRows)

  const rows = surfaces.map(surface =>
    classifySurface(
      surface,
      evidence.filter(row => row?.route === surface.route)
    )
  )

  const dialogRows = primitives.flatMap(entry => asArray(entry?.dialogs))
  const primitivePageErrors = primitives.flatMap(entry =>
    asArray(entry?.pageErrors).map(error => ({ route: entry?.route ?? '?', error: String(error) }))
  )
  const unclassifiedActions = unclassifiedActionCount(manifest)

  const counts = {
    surfaces: rows.length,
    rendering: rows.filter(row => row.status === 'renders').length,
    notRendering: rows.filter(row => row.status === 'not-renders').length,
    excluded: rows.filter(row => row.status === 'excluded').length,
    missing: rows.filter(row => row.status === 'missing').length,
    pageErrors:
      rows.reduce((total, row) => total + row.pageErrors.length, 0) + primitivePageErrors.length,
    primitivePageErrors: primitivePageErrors.length,
    extraPageErrors: primitivePageErrors,
    consoleErrors: rows.reduce((total, row) => total + row.consoleErrors.length, 0),
    unexpectedRequests: rows.reduce((total, row) => total + row.unexpectedRequests.length, 0),
    expectedRequests: rows.reduce((total, row) => total + row.expectedRequests.length, 0),
    unclassifiedActions,
    // A dialog counts as probed only when it opened and the contract observed it.
    dialogsProbed: dialogRows.filter(row => row?.dialog).length,
    contractFailures: dialogRows.reduce(
      (total, row) => total + asArray(row?.checks).filter(check => check?.ok === false).length,
      0
    )
  }

  return { rows, counts, gate: gate(rows, counts) }
}

// --- gallery ----------------------------------------------------------------

const HTML_ESCAPES = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;'
}

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => HTML_ESCAPES[ch])

const planRank = row => {
  const index = ROLE_PLAN_ORDER.indexOf(row?.expectation ?? row?.plan)
  return index === -1 ? ROLE_PLAN_ORDER.length : index
}

const viewportOf = (shotPath, index) => {
  const match = String(shotPath).match(/-(desktop|mobile)(?:\.[a-z]+)?$/i)
  if (match) return match[1].toLowerCase()
  return index % 2 === 0 ? 'desktop' : 'mobile'
}

const shotFigures = (row, report) => {
  const rawRows = asArray(report?.renderRows).filter(entry => entry?.route === row.route)

  if (rawRows.length === 0) {
    return asArray(row.shots).map((shotPath, index) => ({
      path: shotPath,
      role: asArray(row.roles)[0] ?? '—',
      viewport: viewportOf(shotPath, index)
    }))
  }

  return [...rawRows]
    .sort((left, right) => planRank(left) - planRank(right))
    .flatMap(entry =>
      asArray(entry.shots)
        .map((shotPath, index) => ({
          path: shotPath,
          role: entry.role ?? '—',
          viewport: viewportOf(shotPath, index)
        }))
        .sort(
          (left, right) =>
            VIEWPORT_ORDER.indexOf(left.viewport) - VIEWPORT_ORDER.indexOf(right.viewport)
        )
    )
}

const statusCell = row =>
  `<td class="status ${escapeHtml(row.status)}">${escapeHtml(row.status)}</td>`

const failedChecks = row =>
  asArray(row?.checks)
    .filter(check => check?.ok === false)
    .map(check => `${check.id}: ${check.observed} (esperado ${check.expected})`)

const dialogTable = dialogRows => {
  if (dialogRows.length === 0) return '<p class="empty">Sin diálogos probados.</p>'

  const rows = dialogRows.map(row => {
    const dialog = row?.dialog ? row.dialog.name || row.dialog.title || '—' : (row?.outcome ?? '—')
    const failures = failedChecks(row)
    return (
      '<tr>' +
      `<td>${escapeHtml(row?.trigger?.label ?? '—')}</td>` +
      `<td>${escapeHtml(dialog)}</td>` +
      `<td>${failures.length === 0 ? 'ok' : escapeHtml(failures.join('; '))}</td>` +
      '</tr>'
    )
  })

  return (
    '<table class="dialogs"><thead><tr><th>disparador</th><th>diálogo</th>' +
    `<th>checks fallidos</th></tr></thead><tbody>${rows.join('')}</tbody></table>`
  )
}

const figureBlock = (route, figure) => {
  const caption = `${figure.role} · ${figure.viewport}`
  const alt = `${route} · ${caption}`

  return `<figure><img loading="lazy" src="${escapeHtml(figure.path)}" alt="${escapeHtml(alt)}"><figcaption>${escapeHtml(caption)}</figcaption></figure>`
}

const summaryBlock = (counts, gate) => {
  const entries = [
    ['superficies', counts.surfaces],
    ['renderizan', counts.rendering],
    ['no renderizan', counts.notRendering],
    ['excluidas', counts.excluded],
    ['sin evidencia', counts.missing],
    ['pageerror', counts.pageErrors],
    ['console.error', counts.consoleErrors],
    ['HTTP ≥ 400', counts.unexpectedRequests],
    ['solicitudes esperadas (allowlist)', counts.expectedRequests],
    ['acciones sin clasificar', counts.unclassifiedActions],
    ['diálogos probados', counts.dialogsProbed],
    ['filas de contrato fallidas', counts.contractFailures]
  ]

  const items = entries.map(
    ([label, value]) => `<li>${escapeHtml(label)}: ${escapeHtml(value)}</li>`
  )
  const reasons = asArray(gate.reasons)
  const reasonItems =
    reasons.length === 0
      ? ''
      : `<ul class="reasons">${reasons.map(reason => `<li>${escapeHtml(reason)}</li>`).join('')}</ul>`

  const verdict = `<p class="gate ${gate.ok ? 'ok' : 'fail'}">gate: ${gate.ok ? 'OK' : 'FAIL'}</p>`

  return `<ul class="counts">${items.join('')}</ul>${verdict}${reasonItems}`
}

const GALLERY_CSS = `
:root { color-scheme: light; }
* { box-sizing: border-box; }
body { margin: 0; padding: 32px; font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #1f2933; background: #f7f8fa; }
h1 { margin: 0 0 4px; font-size: 24px; }
h2 { margin: 0 0 8px; font-size: 18px; }
code { font-family: ui-monospace, "SFMono-Regular", Menlo, monospace; font-size: 13px; }
.meta { margin: 0 0 24px; color: #52606d; }
.summary { margin-bottom: 32px; }
.counts { display: flex; flex-wrap: wrap; gap: 8px 24px; padding: 0; margin: 0 0 12px; list-style: none; }
.gate { display: inline-block; margin: 0 0 8px; padding: 4px 12px; border-radius: 999px; font-weight: 700; }
.gate.ok { color: #03543f; background: #def7ec; }
.gate.fail { color: #771d1d; background: #fde8e8; }
.reasons { margin: 0; padding-left: 20px; color: #771d1d; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; background: #fff; }
th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e4e7eb; vertical-align: top; }
th { background: #eef1f5; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
.surface { margin-bottom: 32px; padding: 20px; background: #fff; border: 1px solid #e4e7eb; border-radius: 8px; }
.badge { display: inline-block; margin-left: 8px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.badge.renders, .status.renders { color: #03543f; background: #def7ec; }
.badge.not-renders, .status.not-renders { color: #771d1d; background: #fde8e8; }
.badge.excluded, .status.excluded { color: #8e4b10; background: #fdf6b2; }
.badge.missing, .status.missing { color: #323f4b; background: #e4e7eb; }
.status { font-weight: 700; }
.roles { margin: 0 0 12px; color: #52606d; }
.shots { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px; }
figure { margin: 0; padding: 8px; background: #f7f8fa; border: 1px solid #e4e7eb; border-radius: 8px; }
figure img { display: block; width: 100%; height: auto; border: 1px solid #e4e7eb; background: #fff; }
figcaption { margin-top: 6px; font-size: 12px; color: #52606d; }
details { margin-top: 8px; }
summary { cursor: pointer; font-weight: 700; }
.empty { margin: 0; color: #52606d; }
`

/**
 * Builds the self-contained gallery: inline CSS, no external assets, every value
 * escaped. `generatedAt` arrives in the report so this stays deterministic.
 */
export const buildGallery = (manifest, report) => {
  const surfaces = asArray(manifest?.surfaces)
  const rows = asArray(report?.rows)
  const rowByRoute = new Map(rows.map(row => [row.route, row]))
  const primitiveRows = asArray(report?.primitiveRows)
  const counts = report?.counts ?? {}
  const gate = report?.gate ?? { ok: false, reasons: ['sin veredicto'] }

  const tableRows = rows
    .map(
      row =>
        `<tr><td><code>${escapeHtml(row.route)}</code></td><td>${escapeHtml(
          asArray(row.roles).join(', ') || '—'
        )}</td>${statusCell(row)}<td>${row.pageErrors.length}</td><td>${row.consoleErrors.length}</td><td>${row.unexpectedRequests.length}</td><td>${row.shots.length}</td></tr>`
    )
    .join('')

  const table = `<table class="rows">
<thead><tr><th>ruta</th><th>roles</th><th>estado</th><th>pageerror</th><th>console</th><th>HTTP ≥ 400</th><th>capturas</th></tr></thead>
<tbody>${tableRows}</tbody>
</table>`

  const sections = surfaces.map(surface => {
    const row = rowByRoute.get(surface.route) ?? classifySurface(surface, [])
    const figures = shotFigures(row, report)
    const shots =
      figures.length === 0
        ? `<p class="empty">${escapeHtml(row.reason ?? 'sin capturas')}</p>`
        : `<div class="shots">${figures
            .map(figure => figureBlock(surface.route, figure))
            .join('')}</div>`

    const dialogs = asArray(primitiveRows.find(entry => entry?.route === surface.route)?.dialogs)
    const roles = escapeHtml(asArray(row.roles).join(', ') || '—')

    return `<section class="surface" data-route="${escapeHtml(surface.route)}">
<h2><code>${escapeHtml(surface.route)}</code> <span class="badge ${escapeHtml(row.status)}">${escapeHtml(row.status)}</span></h2>
<p class="roles">roles: ${roles}</p>
${shots}
<details><summary>Contrato de diálogos (${dialogs.length})</summary>${dialogTable(dialogs)}</details>
</section>`
  })

  return `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auditoría de superficies — U0</title>
<style>${GALLERY_CSS}</style>
</head>
<body>
<h1>Auditoría de superficies — U0</h1>
<p class="meta">revisión <code>${escapeHtml(report?.revision ?? '—')}</code> · base <code>${escapeHtml(
    report?.base ?? '—'
  )}</code> · ${escapeHtml(report?.generatedAt ?? '—')} · alcance <code>${escapeHtml(
    report?.scope ?? 'all'
  )}</code></p>
<div class="summary">
${summaryBlock(counts, gate)}
</div>
<h2>Resumen por ruta</h2>
${table}
<h2>Superficies</h2>
${sections.join('\n')}
</body>
</html>
`
}

// --- CLI --------------------------------------------------------------------

const USAGE = `Usage: node scripts/audit/ui-pass.mjs [options]

Runs the browser audit pass, classifies every manifest surface and writes the
evidence report plus the gallery index.html.

Options:
  --all                every screen surface (default)
  --module=<name>      keep surfaces whose page or route belongs to the module
  --route=<path>       keep a single router path (e.g. --route=/patients)
  --out=dir            evidence directory (default ${DEFAULT_OUT})
  --base=url           app origin (default ${DEFAULT_BASE})
  --no-run             skip the preflight and Playwright; rebuild from evidence
  --help               show this help`

const parseArgv = argv => {
  const options = { out: DEFAULT_OUT, base: DEFAULT_BASE, run: true, all: true, help: false }

  const valueOf = (arg, name) => (arg.includes('=') ? arg.slice(name.length + 1) : null)

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i]

    if (arg === '--help' || arg === '-h') options.help = true
    else if (arg === '--all') options.all = true
    else if (arg === '--no-run') options.run = false
    else if (arg === '--module' || arg.startsWith('--module=')) {
      options.module = valueOf(arg, '--module') ?? argv[(i += 1)]
      if (!options.module) return { error: 'missing value for --module' }
    } else if (arg === '--route' || arg.startsWith('--route=')) {
      options.route = valueOf(arg, '--route') ?? argv[(i += 1)]
      if (!options.route) return { error: 'missing value for --route' }
    } else if (arg === '--out' || arg.startsWith('--out=')) {
      options.out = valueOf(arg, '--out') ?? argv[(i += 1)]
      if (!options.out) return { error: 'missing value for --out' }
    } else if (arg === '--base' || arg.startsWith('--base=')) {
      options.base = valueOf(arg, '--base') ?? argv[(i += 1)]
      if (!options.base) return { error: 'missing value for --base' }
    } else {
      return { error: `unknown argument: ${arg}` }
    }
  }

  if (options.module && options.route) return { error: 'use either --module or --route' }

  return { options }
}

const scopeOf = options =>
  options.route ? `route:${options.route}` : options.module ? `module:${options.module}` : 'all'

// A listening app answers /login with the SPA shell. A network error or a 5xx is
// an environment failure (exit 2), never an audit finding.
const probeApp = async base => {
  try {
    const response = await globalThis.fetch(`${base}/login`, {
      redirect: 'manual',
      signal: AbortSignal.timeout(PREFLIGHT_TIMEOUT_MS)
    })

    if (response.status >= 500) return { ok: false, reason: `HTTP ${response.status}` }
    return { ok: true, status: response.status }
  } catch (error) {
    return { ok: false, reason: error.message }
  }
}

const readEvidence = directory => {
  let names = []

  try {
    names = readdirSync(directory).sort()
  } catch {
    return []
  }

  const rows = []

  for (const name of names) {
    if (!name.endsWith('.json')) continue

    try {
      rows.push(JSON.parse(readFileSync(join(directory, name), 'utf8')))
    } catch (error) {
      console.warn(`  ! no se pudo leer ${name}: ${error.message}`)
    }
  }

  return rows
}

const printReport = (rows, counts, gate) => {
  console.log('route | roles | status | pageerrors | console | http≥400')

  for (const row of rows) {
    console.log(
      `${row.route} | ${row.roles.join(',')} | ${row.status} | ${row.pageErrors.length} | ` +
        `${row.consoleErrors.length} | ${row.unexpectedRequests.length}`
    )
  }

  console.log('')
  console.log(
    `${counts.rendering}/${counts.surfaces} renderizan · ${counts.excluded} excluidas · ` +
      `${counts.missing} sin evidencia`
  )
  console.log(`pageerror: ${counts.pageErrors}`)
  console.log(`solicitudes esperadas (allowlist): ${counts.expectedRequests}`)
  console.log(`acciones sin clasificar: ${counts.unclassifiedActions}`)
  console.log(
    `contrato de primitivas: ${counts.dialogsProbed} diálogos, ${counts.contractFailures} filas fallidas`
  )
  console.log(
    gate.ok
      ? 'gate: OK'
      : `gate: FAIL — ${gate.reasons.length > 0 ? gate.reasons.join('; ') : 'sin motivos'}`
  )

  for (const finding of asArray(gate.findings)) console.log(`hallazgo: ${finding}`)
}

const main = async () => {
  const parsed = parseArgv(process.argv.slice(2))

  if (parsed.error) {
    console.error(parsed.error)
    console.error('')
    console.error(USAGE)
    process.exitCode = 2
    return
  }

  const { options } = parsed

  if (options.help) {
    console.log(USAGE)
    return
  }

  const outDir = resolve(ROOT, options.out)
  const base = options.base.replace(/\/+$/, '')
  const scope = scopeOf(options)

  mkdirSync(outDir, { recursive: true })

  const manifest = buildManifest({ module: options.module, route: options.route })
  writeFileSync(join(outDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`)

  let playwrightExit = null

  if (options.run) {
    const preflight = await probeApp(base)

    if (!preflight.ok) {
      console.error(`app no responde en ${base}: ${preflight.reason}`)
      process.exitCode = 2
      return
    }

    const result = spawnSync(
      'pnpm',
      ['exec', 'playwright', 'test', 'tests/e2e/audit/surface.spec.js'],
      {
        cwd: ROOT,
        stdio: 'inherit',
        env: {
          ...process.env,
          AUDIT_MANIFEST: join(outDir, 'manifest.json'),
          AUDIT_OUT: outDir,
          AUDIT_SCOPE: 'all',
          BROWSER_PASS_BASE: base
        }
      }
    )

    if (result.error) console.error(`playwright: no se pudo ejecutar (${result.error.message})`)
    playwrightExit = result.status ?? 1
  }

  const renderRows = readEvidence(join(outDir, 'render'))
  const primitiveRows = readEvidence(join(outDir, 'primitives'))
  const { rows, counts, gate } = aggregate(manifest, renderRows, primitiveRows)
  const generatedAt = new Date().toISOString()

  const run = {
    revision: manifest.revision,
    base,
    scope,
    generatedAt,
    counts,
    gate,
    playwrightExit
  }

  writeFileSync(join(outDir, 'render.json'), `${JSON.stringify(rows, null, 2)}\n`)
  writeFileSync(join(outDir, 'primitives.json'), `${JSON.stringify(primitiveRows, null, 2)}\n`)
  writeFileSync(join(outDir, 'run.json'), `${JSON.stringify(run, null, 2)}\n`)
  writeFileSync(
    join(outDir, 'index.html'),
    buildGallery(manifest, { ...run, rows, renderRows, primitiveRows })
  )

  printReport(rows, counts, gate)

  const passed = gate.ok && (!options.run || playwrightExit === 0)
  process.exitCode = passed ? 0 : 1
}

if (process.argv[1] === fileURLToPath(import.meta.url)) main()
