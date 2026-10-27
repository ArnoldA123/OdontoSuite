// Plan #13 (issue #372, U9) — the session's report: the two consolidated
// layers, the walkthrough tables, the raw findings, the gate and the gallery.
//
// This row does not measure. Every number it prints comes from a JSON an earlier
// row wrote: the layer-2 rows in `render/`, the layer-3 files in `primitives/`
// and the eight walkthroughs in `walk-<slug>.json`. The generator fails with the
// missing path when an artefact it cites does not exist, so a partial run cannot
// publish a report that looks complete.
//
// Severity, root class and issue number stay `null`: grouping and publishing are
// the triage's job (plan §8.1), never the instrument's.

import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'

import { test, expect } from 'playwright/test'

import { EVIDENCE, evidencePath, stamp, writeEvidence } from './helpers.js'

const SESSION = 'U9'

const ISSUE = '#372'

// `render.json` is a name every session shares: U5 and U6 register an `afterAll`
// that rewrites it in whatever `AUDIT_OUT` names, even when their own tests were
// filtered out. The report keeps its copy under `render-session.json` and
// re-writes both shared names from a hook declared last, so the numbers a reader
// finds there are this session's.
const REPORTED = (globalThis.__AUDIT_U9_REPORT__ ??= { render: null, primitives: null })

// The walked surfaces, in the order the session declares them. `surface` is what
// the walk JSON publishes; `label` is the Spanish name the report prints.
const WALKS = [
  {
    slug: 'professionals',
    file: 'walk-professionals.json',
    surface: '/professionals',
    label: 'Profesionales'
  },
  {
    slug: 'environments',
    file: 'walk-environments.json',
    surface: '/environments',
    label: 'Ambientes'
  },
  {
    slug: 'appointment-types',
    file: 'walk-appointment-types.json',
    surface: '/appointment-types',
    label: 'Tipos de cita'
  },
  {
    slug: 'specialties',
    file: 'walk-specialties.json',
    surface: '/specialties',
    label: 'Especialidades'
  },
  {
    slug: 'branches',
    file: 'walk-branches.json',
    surface: '/settings/branches',
    label: 'Sucursales'
  },
  {
    slug: 'payment-methods',
    file: 'walk-payment-methods.json',
    surface: '/settings/payment-methods',
    label: 'Métodos de pago'
  },
  {
    slug: 'accounting-locks',
    file: 'walk-accounting-locks.json',
    surface: '/settings/accounting-locks',
    label: 'Bloqueo contable'
  },
  {
    slug: 'transfers',
    file: 'walk-transfers.json',
    surface: '/branch-transfers',
    label: 'Traslados de sede'
  }
]

const FINDING_VERDICTS = ['DEFECTO', 'NO-OP', 'CONFUSO']

const readJson = relPath => JSON.parse(readFileSync(evidencePath(relPath), 'utf8'))
const has = relPath => existsSync(evidencePath(relPath))

/** Reads every JSON an earlier row wrote into a directory of the session. */
const readDir = dir => {
  try {
    return readdirSync(evidencePath(dir))
      .filter(name => name.endsWith('.json'))
      .sort()
      .map(name => readJson(`${dir}/${name}`))
  } catch {
    return []
  }
}

/** Every `.png` under a directory, sorted, as evidence-relative paths. */
const collectShots = dir => {
  const found = []

  const walk = relPath => {
    let names = []

    try {
      names = readdirSync(evidencePath(relPath), { withFileTypes: true })
    } catch {
      return
    }

    for (const entry of names.sort((left, right) => left.name.localeCompare(right.name))) {
      const child = `${relPath}/${entry.name}`
      if (entry.isDirectory()) walk(child)
      else if (entry.name.endsWith('.png')) found.push(child)
    }
  }

  walk(dir)

  return found
}

const escapeHtml = value =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')

const escapeCell = value =>
  String(value ?? '')
    .replace(/\|/g, '\\|')
    .replace(/\s+/g, ' ')
    .slice(0, 320)

const countOf = (list, predicate) => (list ?? []).filter(predicate).length

// --- layer-2 aggregate ------------------------------------------------------

/**
 * The layer-2 aggregate, built from the per-row files.
 *
 * Each `render/render-<slug>-<role>.json` is one surface for one role; the totals
 * the issue names (rendered, overflow, single heading, page errors, console
 * errors, unexpected and expected HTTP, negative rows with write affordances) are
 * sums over those rows, never re-measured.
 */
const renderAggregate = rows => {
  const mobileOf = row => row.viewports?.mobile ?? null

  return {
    ...stamp(),
    stage: 'layer-2',
    rowsCount: rows.length,
    routes: [...new Set(rows.map(row => row.route))],
    roles: [...new Set(rows.map(row => row.role))],
    totals: {
      rows: rows.length,
      rendered: countOf(rows, row => row.rendered === true),
      overflow: countOf(rows, row => row.horizontalOverflow === true),
      singleHeading: countOf(rows, row => (row.headings?.count ?? row.headingCount) === 1),
      pageErrors: rows.reduce((total, row) => total + (row.pageErrors ?? []).length, 0),
      consoleErrors: rows.reduce((total, row) => total + (row.consoleErrors ?? []).length, 0),
      unexpectedConsole: rows.reduce(
        (total, row) => total + (row.consoleClassified?.unexpected ?? []).length,
        0
      ),
      unexpectedRequests: rows.reduce(
        (total, row) => total + (row.failedRequests?.unexpected ?? []).length,
        0
      ),
      expectedRequests: rows.reduce(
        (total, row) => total + (row.failedRequests?.expected ?? []).length,
        0
      ),
      negativeRows: countOf(rows, row => row.expectation === 'negative'),
      negativeRowsWithWriteAffordances: countOf(
        rows,
        row => row.expectation === 'negative' && (row.denial?.writeAffordances ?? []).length > 0
      )
    },
    rows: rows.map(row => ({
      route: row.route,
      slug: row.slug,
      role: row.role,
      expectation: row.expectation,
      href: row.href,
      httpStatus: row.httpStatus,
      rendered: row.rendered,
      contentLength: row.contentLength,
      headingCount: row.headings?.count ?? row.headingCount ?? null,
      headings: row.headings?.texts ?? null,
      horizontalOverflow: row.horizontalOverflow,
      mobile: mobileOf(row)
        ? { scrollWidth: mobileOf(row).scrollWidth, innerWidth: mobileOf(row).innerWidth }
        : null,
      shots: row.shots ?? [],
      pageErrors: (row.pageErrors ?? []).length,
      consoleErrors: (row.consoleErrors ?? []).length,
      unexpectedRequests: (row.failedRequests?.unexpected ?? []).length,
      expectedRequests: (row.failedRequests?.expected ?? []).length,
      writeAffordances: (row.denial?.writeAffordances ?? []).map(entry => entry.label),
      writeDoor: row.denial?.checked?.writeDoor ?? null
    }))
  }
}

// --- layer-3 aggregate ------------------------------------------------------

/**
 * The layer-3 aggregate: the dialogs each surface opened and the contract checks
 * whose `pass` is false, counted by check id.
 */
const primitivesAggregate = files => {
  const dialogs = []

  for (const file of files) {
    for (const dialog of file.dialogs ?? []) {
      const checks = dialog.checks ?? []

      dialogs.push({
        route: file.route,
        slug: file.slug,
        role: file.role ?? null,
        trigger: dialog.trigger ?? null,
        title: dialog.dialog?.name ?? null,
        outcome: dialog.outcome ?? (checks.length ? 'measured' : 'not-probed'),
        checks: checks.map(check => ({
          id: check.id,
          ok: check.ok === true,
          observed: check.observed ?? null,
          expected: check.expected ?? null
        })),
        failures: checks.filter(check => check.ok !== true).map(check => check.id)
      })
    }
  }

  const byCheck = {}

  for (const dialog of dialogs) {
    for (const id of dialog.failures) byCheck[id] = (byCheck[id] ?? 0) + 1
  }

  return {
    ...stamp(),
    stage: 'layer-3',
    files: files.length,
    totals: {
      files: files.length,
      declared: files.reduce((total, file) => total + (file.dialogs ?? []).length, 0),
      probed: dialogs.filter(dialog => dialog.outcome === 'measured').length,
      unprobed: dialogs.filter(dialog => dialog.outcome !== 'measured').length,
      checks: dialogs.reduce((total, dialog) => total + dialog.checks.length, 0),
      contractFailures: dialogs.reduce((total, dialog) => total + dialog.failures.length, 0),
      pageErrors: files.reduce((total, file) => total + (file.pageErrors ?? []).length, 0)
    },
    contractFailuresByCheck: byCheck,
    pageErrors: files.flatMap(file =>
      (file.pageErrors ?? []).map(error => ({ route: file.route, error: String(error) }))
    ),
    dialogs
  }
}

// --- findings ---------------------------------------------------------------

let findingSequence = 0

const nextFindingId = () => `U9-F${String((findingSequence += 1)).padStart(2, '0')}`

/**
 * The rows the triage starts from: every walk action that is not `OK` and every
 * negative render row that still offers a write the API refuses.
 *
 * `severity`, `group` and `issue` are deliberately null: grouping by root class
 * and publishing issues are the triage's decisions (plan §8.1), not the
 * instrument's.
 */
const buildFindings = (walkEntries, renderRows) => {
  const findings = []

  for (const { walk, declared } of walkEntries) {
    for (const entry of walk.steps ?? []) {
      if (!FINDING_VERDICTS.includes(entry.verdict)) continue

      findings.push({
        id: nextFindingId(),
        surface: `${walk.surface} (${declared.label})`,
        action: entry.action,
        expected: entry.expected,
        obtained: entry.obtained ?? entry.notes ?? null,
        layer: entry.layer ?? 4,
        origin: entry.origin ?? 'MEDIDO',
        verdict: entry.verdict,
        severity: null,
        group: null,
        issue: null,
        shot: entry.shot ?? null,
        request: entry.request ?? null,
        walk: declared.file,
        step: entry.id,
        notes: entry.notes ?? null
      })
    }
  }

  for (const row of renderRows) {
    const affordances = row.writeAffordances ?? []
    if (row.expectation !== 'negative' || affordances.length === 0) continue

    const door = row.writeDoor ?? null

    findings.push({
      id: nextFindingId(),
      surface: `${row.route} (capa 2, rol ${row.role})`,
      action: 'Renderizar la superficie con el rol excluido',
      expected: 'La fila negativa no ofrece controles de escritura que la API rechaza con 403',
      obtained: `capa 2 · controles de escritura visibles ${JSON.stringify(affordances)} · puerta ${door ? `${door.method} ${door.path} → ${door.status ?? 'sin respuesta'}` : 'sin puerta declarada'}`,
      layer: 2,
      origin: 'MEDIDO',
      verdict: 'DEFECTO',
      severity: null,
      group: null,
      issue: null,
      shot: (row.shots ?? [])[0] ?? null,
      request: door ? { method: door.method, path: door.path, status: door.status ?? null } : null,
      walk: null,
      step: null,
      notes: `fila de capa 2: render/render-${row.slug}-${row.role}.json`
    })
  }

  return findings
}

// --- gallery ----------------------------------------------------------------

const galleryBlock = (title, shots, captionOf) => {
  if (!shots.length) return ''

  const figures = shots
    .map(shot => {
      const caption = captionOf(shot)

      return `<figure><a href="${escapeHtml(shot)}"><img loading="lazy" src="${escapeHtml(
        shot
      )}" alt="${escapeHtml(caption)}"></a><figcaption><code>${escapeHtml(
        shot
      )}</code>${caption ? ` · ${escapeHtml(caption)}` : ''}</figcaption></figure>`
    })
    .join('')

  return `<section class="surface"><h2>${escapeHtml(title)} <span class="badge">${
    shots.length
  }</span></h2><div class="shots">${figures}</div></section>`
}

const buildGalleryHtml = ({ render, primitives, walks, gate, findings, revision, base }) => {
  const renderShots = (render.rows ?? []).flatMap(row =>
    (row.shots ?? []).map(shot => ({
      shot,
      caption: `${row.route} · ${row.role} · ${row.rendered ? 'renderiza' : 'no renderiza'}`
    }))
  )
  const renderCaptions = Object.fromEntries(renderShots.map(item => [item.shot, item.caption]))
  const primitiveShots = collectShots('primitives')

  const walkSections = walks.map(({ walk, declared }) => {
    const shots = collectShots(`shots/walk/${declared.slug}`)
    const steps = walk?.steps ?? []

    return galleryBlock(
      `${declared.label} — \`${declared.surface}\` (${steps.length} pasos)`,
      shots,
      shot => {
        const name = shot.split('/').pop() ?? ''
        const id = name.replace(/\.png$/, '')
        const step = steps.find(entry => entry.id === id || id.startsWith(`${entry.id}-`))

        return step ? `${step.id} · ${step.verdict}` : 'captura del paso'
      }
    )
  })

  const totals = render.totals ?? {}
  const prim = primitives.totals ?? {}

  return `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>U9 — Administración y configuración (${ISSUE})</title>
<style>
:root { color-scheme: light }
body { margin: 0; padding: 24px; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f6f7f9; color: #111827 }
h1 { margin: 0 0 8px; font-size: 22px }
h2 { margin: 32px 0 12px; font-size: 16px }
p.meta, li { color: #4b5563; font-size: 13px }
.summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin: 16px 0 8px }
.card { padding: 12px 14px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px }
.card h3 { margin: 0 0 6px; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; color: #6b7280 }
.card p { margin: 2px 0; font-size: 13px }
.gate-ok { color: #03543f; font-weight: 700 }
.gate-fail { color: #9b1c1c; font-weight: 700 }
.surface { margin-bottom: 28px; padding: 16px 18px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px }
.badge { display: inline-block; margin-left: 8px; padding: 2px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 700 }
.shots { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px }
figure { margin: 0; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px }
figure img { display: block; width: 100%; height: auto; border: 1px solid #e5e7eb; background: #fff; border-radius: 6px }
figcaption { margin-top: 6px; font-size: 12px; color: #4b5563; word-break: break-word }
code { font-size: 12px }
table { width: 100%; border-collapse: collapse; margin: 8px 0 0; background: #fff }
th, td { padding: 7px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 13px; vertical-align: top }
th { background: #eef1f5; font-size: 11px; text-transform: uppercase; letter-spacing: .04em }
</style>
</head>
<body>
<h1>U9 — Administración y configuración (${ISSUE})</h1>
<p class="meta">revisión <code>${escapeHtml(revision ?? '—')}</code> · base <code>${escapeHtml(
    base ?? '—'
  )}</code> · generado ${escapeHtml(new Date().toISOString())} · evidencia cruda en <code>${escapeHtml(
    EVIDENCE
  )}</code></p>
<div class="summary">
<div class="card"><h3>Puerta de la sesión</h3><p class="${
    gate.ok ? 'gate-ok' : 'gate-fail'
  }">${gate.ok ? 'OK' : 'NO CUMPLE'}</p><p>${gate.reasons.length} motivo(s)</p></div>
<div class="card"><h3>Capa 2 — render</h3><p>${totals.rows ?? 0} fila(s) · ${
    totals.rendered ?? 0
  } renderizan</p><p>${totals.pageErrors ?? 0} pageerror · ${totals.unexpectedRequests ?? 0} HTTP ≥ 400</p><p>${
    totals.negativeRowsWithWriteAffordances ?? 0
  } fila(s) negativa(s) con escritura ofrecida</p></div>
<div class="card"><h3>Capa 3 — primitivas</h3><p>${prim.probed ?? 0} de ${
    prim.declared ?? 0
  } diálogo(s) medidas</p><p>${prim.contractFailures ?? 0} incumplimiento(s) de contrato</p></div>
<div class="card"><h3>Capa 4 — caminatas</h3><p>${walks.length} superficie(s) caminada(s)</p><p>${walks.reduce(
    (total, entry) => total + (entry.walk?.steps ?? []).length,
    0
  )} acción(es) · ${findings.length} hallazgo(s) crudo(s)</p></div>
</div>
<h2>Pasos con veredicto distinto de OK</h2>
${
  findings.length
    ? `<table><thead><tr><th>id</th><th>superficie</th><th>acción</th><th>veredicto</th><th>capa</th></tr></thead><tbody>${findings
        .map(
          finding =>
            `<tr><td>${escapeHtml(finding.id)}</td><td><code>${escapeHtml(finding.surface)}</code></td><td>${escapeHtml(
              finding.action
            )}</td><td>${escapeHtml(finding.verdict)}</td><td>${escapeHtml(finding.layer)}</td></tr>`
        )
        .join('')}</tbody></table>`
    : '<p class="meta">Sin hallazgos crudos.</p>'
}
${galleryBlock(
  `Render — desktop y móvil (${renderShots.length})`,
  renderShots.map(item => item.shot),
  shot => renderCaptions[shot] ?? ''
)}
${galleryBlock(`Primitivas — diálogos medidos (${primitiveShots.length})`, primitiveShots, () => 'capa 3')}
${walkSections.join('\n')}
</body>
</html>
`
}

// --- the report -------------------------------------------------------------

test('@U9 informe de la sesión — capas consolidadas, recorrido, puerta y galería', async () => {
  test.setTimeout(120000)

  // --- the artefacts this report cites ------------------------------------
  const required = [
    'fixtures.json',
    ...WALKS.map(walk => walk.file),
    ...['render', 'primitives'].flatMap(dir => (existsSync(evidencePath(dir)) ? [] : [dir]))
  ]

  const missing = required.filter(relPath => !has(relPath))

  expect(missing, `artefactos que el informe cita y no existen: ${missing.join(', ')}`).toEqual([])

  const fixtures = has('fixtures.json') ? readJson('fixtures.json') : null
  const render = renderAggregate(readDir('render'))
  const primitives = primitivesAggregate(readDir('primitives'))

  const walkEntries = WALKS.map(declared => ({
    walk: has(declared.file) ? readJson(declared.file) : null,
    declared
  }))

  const absent = walkEntries.filter(entry => entry.walk === null).map(entry => entry.declared.file)

  expect(absent, `caminatas ausentes: ${absent.join(', ')}`).toEqual([])

  const findings = buildFindings(walkEntries, render.rows)

  // --- the two consolidated layer files -----------------------------------
  // `writeEvidence` takes the exact file name: `audit()` appends `.json`, and
  // these four artefacts are cited by that name.
  writeEvidence('render.json', render)
  writeEvidence('render-session.json', render)
  writeEvidence('primitives.json', primitives)
  writeEvidence('primitives-session.json', primitives)

  REPORTED.render = render
  REPORTED.primitives = primitives

  // --- the gate ------------------------------------------------------------
  const walkPageErrors = walkEntries.reduce(
    (total, entry) => total + (entry.walk?.pageErrors ?? []).length,
    0
  )
  const walkUnexpected = walkEntries.reduce(
    (total, entry) => total + (entry.walk?.failedRequests?.unexpected ?? []).length,
    0
  )
  const walkExpected = walkEntries.reduce(
    (total, entry) => total + (entry.walk?.failedRequests?.expected ?? []).length,
    0
  )
  const walkConsoleUnexpected = walkEntries.reduce(
    (total, entry) => total + (entry.walk?.consoleClassified?.unexpected ?? []).length,
    0
  )

  const verdicts = ['OK', 'DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO']
  const actions = walkEntries.flatMap(entry =>
    (entry.walk?.steps ?? []).map(step => ({
      walk: entry.declared.file,
      id: step.id,
      verdict: step.verdict ?? null
    }))
  )
  const unclassified = actions.filter(action => !verdicts.includes(action.verdict))

  const writeAffordanceRows = render.rows
    .filter(row => row.expectation === 'negative' && (row.writeAffordances ?? []).length > 0)
    .map(row => ({
      route: row.route,
      role: row.role,
      affordances: row.writeAffordances,
      door: row.writeDoor,
      file: `render/render-${row.slug}-${row.role}.json`
    }))

  const pageErrors = {
    render: render.totals.pageErrors,
    primitives: primitives.totals.pageErrors,
    walks: walkPageErrors
  }
  const requests = {
    renderUnexpected: render.totals.unexpectedRequests,
    walkUnexpected,
    total: render.totals.unexpectedRequests + walkUnexpected
  }

  const reasons = []

  if (missing.length) reasons.push(`artefactos ausentes: ${missing.join(', ')}`)
  if (absent.length) reasons.push(`caminatas ausentes: ${absent.join(', ')}`)
  if (pageErrors.render + pageErrors.primitives + pageErrors.walks > 0) {
    reasons.push(
      `pageerror(s): capa 2 ${pageErrors.render} · capa 3 ${pageErrors.primitives} · caminatas ${pageErrors.walks}`
    )
  }
  if (unclassified.length) {
    reasons.push(
      `acciones sin clasificar: ${unclassified.map(action => `${action.walk}#${action.id}`).join(', ')}`
    )
  }
  if (primitives.totals.contractFailures > 0) {
    reasons.push(
      `contratos de primitiva incumplidos: ${primitives.totals.contractFailures} (${JSON.stringify(
        primitives.contractFailuresByCheck
      )})`
    )
  }
  if (requests.total > 0) {
    reasons.push(`HTTP ≥ 400 no declarados: ${requests.total} (${JSON.stringify(requests)})`)
  }
  if (walkConsoleUnexpected > 0) {
    reasons.push(`console.error no clasificados en las caminatas: ${walkConsoleUnexpected}`)
  }

  const gate = {
    ...stamp(),
    stage: 'gate',
    session: SESSION,
    issue: ISSUE,
    ok: reasons.length === 0,
    reasons,
    artefacts: Object.fromEntries(
      [...WALKS.map(walk => walk.file), 'fixtures.json'].map(file => [file, has(file)])
    ),
    layers: {
      renderFiles: render.rowsCount,
      primitiveFiles: primitives.files,
      walkFiles: walkEntries.filter(entry => entry.walk !== null).length
    },
    pageErrors: {
      ...pageErrors,
      total: pageErrors.render + pageErrors.primitives + pageErrors.walks
    },
    console: {
      renderUnexpected: render.totals.unexpectedConsole,
      walks: walkConsoleUnexpected,
      total: render.totals.unexpectedConsole + walkConsoleUnexpected
    },
    requests: {
      ...requests,
      expected: render.totals.expectedRequests + walkExpected
    },
    actions: {
      total: actions.length,
      classified: actions.length - unclassified.length,
      unclassified: unclassified.length,
      byVerdict: Object.fromEntries(
        verdicts.map(verdict => [verdict, countOf(actions, action => action.verdict === verdict)])
      ),
      perWalk: Object.fromEntries(
        walkEntries.map(entry => [
          entry.declared.file,
          entry.walk?.counts ?? { total: (entry.walk?.steps ?? []).length }
        ])
      )
    },
    dialogs: {
      declared: primitives.totals.declared,
      probed: primitives.totals.probed,
      unprobed: primitives.totals.unprobed,
      contractFailures: primitives.totals.contractFailures,
      contractFailuresByCheck: primitives.contractFailuresByCheck
    },
    render: {
      rows: render.totals.rows,
      rendered: render.totals.rendered,
      overflow: render.totals.overflow,
      singleHeading: render.totals.singleHeading,
      negativeRows: render.totals.negativeRows,
      negativeRowsWithWriteAffordances: render.totals.negativeRowsWithWriteAffordances
    },
    writeAffordances: {
      count: writeAffordanceRows.length,
      rows: writeAffordanceRows
    },
    findings: {
      total: findings.length,
      byVerdict: Object.fromEntries(
        FINDING_VERDICTS.map(verdict => [
          verdict,
          countOf(findings, finding => finding.verdict === verdict)
        ])
      ),
      fromWalks: countOf(findings, finding => finding.layer === 4),
      fromRender: countOf(findings, finding => finding.layer === 2)
    }
  }

  writeEvidence('gate.json', gate)

  // --- findings.raw.json ---------------------------------------------------
  writeEvidence('findings.raw.json', {
    ...stamp(),
    stage: 'layer-4',
    session: SESSION,
    issue: ISSUE,
    plan: 'docs/mejoras/13-programa-auditoria-ui-funcional-2026-10.md §4 capa 4, §8.1',
    method:
      'una fila por acción con veredicto DEFECTO/NO-OP/CONFUSO y por fila negativa de capa 2 que aún ofrece escritura; severidad, clase raíz e issue quedan nulos para el triage',
    totals: {
      findings: findings.length,
      byVerdict: Object.fromEntries(
        FINDING_VERDICTS.map(verdict => [
          verdict,
          countOf(findings, finding => finding.verdict === verdict)
        ])
      ),
      byLayer: [2, 3, 4, 5, 6].map(layer => [
        layer,
        countOf(findings, finding => finding.layer === layer)
      ])
    },
    ungrouped:
      'severity, group e issue son null a propósito: el triage agrupa por clase raíz (plan §8.1)',
    findings
  })

  // --- walkthrough.md ------------------------------------------------------
  const lines = [
    '# U9 — Administración y configuración (#372)',
    '',
    `Generado por \`tests/e2e/audit/u9/report.js\` el ${new Date().toISOString()} sobre la revisión ${fixtures?.revision ?? 'desconocida'}.`,
    '',
    `Base: \`${fixtures?.base ?? 'desconocida'}\` · evidencia cruda en \`${EVIDENCE}/\`.`,
    '',
    'Criterio de salida (#372): los ocho ABM se completan con datos propios sin callejón; ninguna acción destructiva o inmutable se ofrece donde la API la prohíbe; el 409 de solicitud abierta y el 403 de decisión propia se explican en pantalla; cero `pageerror`; rúbrica puntuada.',
    '',
    `Puerta: **${gate.ok ? 'OK' : 'NO CUMPLE'}** · ${gate.pageErrors.total} pageerror(s) · ${gate.actions.classified}/${gate.actions.total} acciones clasificadas · ${gate.dialogs.probed}/${gate.dialogs.declared} diálogos medidos · ${gate.dialogs.contractFailures} incumplimiento(s) de contrato · ${gate.requests.total} HTTP ≥ 400 no declarados · ${gate.writeAffordances.count} fila(s) negativa(s) con escritura ofrecida.`,
    ''
  ]

  if (gate.reasons.length) {
    lines.push('## Motivos de la puerta', '')
    for (const reason of gate.reasons) lines.push(`- ${reason}`)
    lines.push('')
  }

  for (const { walk, declared } of walkEntries) {
    if (!walk) continue

    const counts = walk.counts ?? {}
    const surface = walk.surface ?? declared.surface

    lines.push(`## ${declared.label} — \`${surface}\``, '')
    lines.push(
      `Resumen: ${counts.total ?? (walk.steps ?? []).length} acción(es) · ${counts.ok ?? 0} OK · ${
        counts.defecto ?? 0
      } DEFECTO · ${counts.noOp ?? 0} NO-OP · ${counts.confuso ?? 0} CONFUSO · ${
        counts.noProbado ?? 0
      } NO PROBADO · ${(walk.pageErrors ?? []).length} pageerror · ${(walk.failedRequests?.unexpected ?? []).length} HTTP ≥ 400 no declarado`,
      ''
    )
    lines.push('| # | acción | esperado | obtenido | veredicto |')
    lines.push('| --- | --- | --- | --- | --- |')

    for (const entry of walk.steps ?? []) {
      lines.push(
        `| ${escapeCell(entry.id)} | ${escapeCell(entry.action)} | ${escapeCell(entry.expected)} | ${escapeCell(entry.obtained ?? entry.notes)} | ${escapeCell(entry.verdict)} |`
      )
    }

    lines.push('')
  }

  writeFileSync(evidencePath('walkthrough.md'), `${lines.join('\n')}\n`)

  // --- index.html ----------------------------------------------------------
  writeFileSync(
    evidencePath('index.html'),
    buildGalleryHtml({
      render: { rows: render.rows, totals: render.totals },
      primitives,
      walks: walkEntries,
      gate,
      findings,
      revision: fixtures?.revision ?? null,
      base: fixtures?.base ?? null
    })
  )

  // --- the instrument's own assertions ------------------------------------
  expect(findings.length, 'hallazgos crudos del recorrido y de la capa 2').toBeGreaterThan(0)
  expect(gate.writeAffordances.count, 'filas negativas de la capa 2 con escritura ofrecida').toBe(
    render.totals.negativeRowsWithWriteAffordances
  )
  expect(gate.pageErrors.total, 'page errors de la sesión').toBe(0)
  expect(gate.actions.unclassified, 'acciones sin clasificar').toBe(0)
  expect(gate.dialogs.contractFailures, 'incumplimientos del contrato de primitivas').toBe(0)
  expect(gate.requests.total, 'HTTP ≥ 400 no declarados').toBe(0)
  expect(gate.ok, `la puerta de la sesión: ${gate.reasons.join(' · ')}`).toBe(true)
  expect(has('walkthrough.md'), 'walkthrough.md').toBe(true)
  expect(has('index.html'), 'index.html').toBe(true)
})

test.afterAll(async () => {
  if (!REPORTED.render || !REPORTED.primitives) return

  writeEvidence('render.json', REPORTED.render)
  writeEvidence('primitives.json', REPORTED.primitives)
})
