// Plan #13 (issue #373, U10) — the session report: the layer-2 aggregate, the
// BI report × state matrix, the export comparison, the continuity and laboratory
// walkthroughs, the raw findings, the gate, the UX rubric and the gallery.
//
// This row does not measure. Every line it prints comes from a JSON an earlier
// row wrote: the layer-2 files under `render/` and the layer-4 walks
// (`walk-bi*.json`, `walk-continuity.json`, `walk-lab.json`). It never opens a
// browser, never calls the API and never touches the database.
//
// A missing artefact never crashes the generator: it becomes `NO PROBADO` in the
// walkthrough and a named reason in the gate. The report is deterministic — no
// wall clock, no locale formatting — so a second run over the same evidence
// writes the same bytes.
//
// Severity, root class and issue number stay out of `findings.raw.json`: grouping
// and publishing are the triage's job (plan §8.1), never the instrument's.

import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs'

import { expect, test } from 'playwright/test'

import { EVIDENCE, evidencePath, readEvidence, writeEvidence } from './helpers.js'

const SESSION = 'U10'

const ISSUE = '#373'

const ARTEFACTS = Object.freeze([
  'render.json',
  'walkthrough.md',
  'findings.raw.json',
  'gate.json',
  'ux-rubric.md',
  'index.html'
])

const VERDICTS = Object.freeze(['OK', 'DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO'])

// The seven reports of the surface, in the order issue #373 names them (the
// selector publishes `Utilización` before `Consolidado`, so the matrix order is
// the issue's, never the widget's).
const BI_REPORTS = Object.freeze([
  { key: 'dashboard', label: 'Dashboard General' },
  { key: 'appointments', label: 'Reporte de Citas' },
  { key: 'patients', label: 'Reporte de Pacientes' },
  { key: 'professionals', label: 'Reporte de Profesionales' },
  { key: 'revenue', label: 'Reporte de Ingresos' },
  { key: 'consolidated', label: 'Consolidado por Sede' },
  { key: 'utilization', label: 'Utilización de Ambientes' }
])

const BI_STATES = Object.freeze([
  { key: 'data', label: 'datos' },
  { key: 'empty', label: 'vacío' },
  { key: 'error', label: 'error' },
  { key: 'export', label: 'export vs pantalla' }
])

// The three desks, in the order the session declares them. `key` matches the
// layer-2 slug so the render rows and the walk findings meet in the same section.
const SURFACES = Object.freeze([
  {
    key: 'business-intelligence',
    label: 'Business Intelligence',
    route: '/business-intelligence'
  },
  { key: 'continuity', label: 'Continuidad', route: '/continuity' },
  { key: 'lab-cases', label: 'Laboratorio protésico', route: '/lab-cases' }
])

// Continuity: the points issue #373 asks the walkthrough to show. The matchers
// read the walk's own action text; a walk that names a step differently leaves
// its point `NO PROBADO` and the full step table below still publishes what was
// measured.
const CONTINUITY_TOPICS = Object.freeze([
  {
    label: 'Poblaciones de la cola',
    expected:
      'La cola separa recalls vencidos y solicitudes pendientes, cada una con su procedencia y su conteo.',
    match: /cola|poblaci|m[eé]tric|recall/i
  },
  {
    label: 'Puerta de agendar — éxito',
    expected:
      'Agendar materializa la solicitud en una cita, la saca de la cola y la pantalla lo confirma.',
    match: /agendar|programar|materializ/i,
    exclude: /conflicto/i
  },
  {
    label: 'Puerta de agendar — conflicto',
    expected:
      'Un conflicto de agenda deja la solicitud pendiente y la pantalla explica el motivo sin inventar la cita.',
    match: /conflicto/i
  },
  {
    label: 'Puerta de descartar — sin motivo',
    expected: 'El motivo es obligatorio: el envío vacío se bloquea y el error cae en su campo.',
    match: /descartar/i,
    blank: true
  },
  {
    label: 'Puerta de descartar — con motivo',
    expected: 'La solicitud sale de la cola y el motivo queda registrado en la fila.',
    match: /descartar/i,
    blank: false
  },
  {
    label: 'Rol de solo lectura',
    expected:
      'Un clínico lee la cola y no recibe las acciones de gestión (agendar/descartar) ni el 403 de la API.',
    match: /solo lectura|s[oó]lo lectura|rol de lectura|sin permiso|403|permiso|cl[ií]nic/i
  },
  {
    label: 'Contrato del diálogo',
    expected:
      'El diálogo está centrado, cierra con Escape, atrapa el foco, conserva un solo h1 y publica etiqueta accesible.',
    match: /di[aá]logo|contrato|modal/i
  }
])

// Laboratory: the same contract, with the phase machine and the API probe.
const LAB_TOPICS = Object.freeze([
  {
    label: 'Lista y filtros',
    expected:
      'La lista filtra por fase, por técnico y por vencido, y el conteo responde al filtro.',
    match: /filtro|vencid/i
  },
  {
    label: 'Puerta de fase — fases ofrecidas',
    expected: 'La puerta ofrece sólo las fases posteriores a la actual.',
    match: /fase/i,
    offered: true
  },
  {
    label: 'Puerta de fase — avance',
    expected: 'El caso avanza de fase y el mensaje del servicio cae en su campo o en su aviso.',
    match: /avanz|avanza|pasar a|siguiente/i
  },
  {
    label: 'Puerta de fase — retroceso forzado',
    expected:
      'La máquina no retrocede: la fase anterior responde 422 y el mensaje nombra la fase actual y la pedida.',
    match: /retroced|retroceso|atr[aá]s|anterior/i
  },
  {
    label: 'Caso colocado',
    expected: 'El caso en `placed` se registra y la superficie lo pinta sin notificar.',
    match: /placed|colocad/i
  },
  {
    label: 'Rol de solo lectura',
    expected: 'Recepción ve los casos y no escribe: no hay alta ni edición a su alcance.',
    match: /solo lectura|s[oó]lo lectura|403|permiso|recepcion/i
  },
  {
    label: 'Alta y edición — presencia o ausencia declarada',
    expected:
      'El alta y la edición se abren desde la página o desde la ficha del paciente, o su ausencia se declara.',
    match: /alta|edici|formulario|crear|nuevo/i
  },
  {
    label: 'Sonda API — caso de otro técnico',
    expected:
      'Un caso asignado a otro técnico responde 403/404 en la API y la superficie no ofrece su edición.',
    match: /otro t[eé]cnico|t[eé]cnico ajeno|otro|sonda|api/i
  },
  {
    label: 'Contrato del diálogo de detalle',
    expected:
      'El detalle está centrado, cierra con Escape, atrapa el foco, conserva un solo h1 y publica etiqueta accesible.',
    match: /di[aá]logo|detalle|contrato|modal/i
  }
])

const WALKS = Object.freeze([
  {
    key: 'business-intelligence',
    slug: 'bi',
    file: 'walk-bi.json',
    surface: '/business-intelligence',
    label: 'Business Intelligence',
    expected:
      'Los siete reportes miden datos, vacío, error y exportación, y la exportación coincide con la pantalla.'
  },
  {
    key: 'continuity',
    slug: 'continuity',
    file: 'walk-continuity.json',
    surface: '/continuity',
    label: 'Continuidad',
    expected:
      'La cola se resuelve: agendar materializa la solicitud, el conflicto la deja pendiente, descartar exige motivo y el rol de lectura no gestiona.',
    topics: CONTINUITY_TOPICS
  },
  {
    key: 'lab-cases',
    slug: 'lab',
    file: 'walk-lab.json',
    surface: '/lab-cases',
    label: 'Laboratorio protésico',
    expected:
      'La fase sólo avanza, el retroceso responde 422 en su campo, el caso colocado se registra y la sonda de otro técnico se niega.',
    topics: LAB_TOPICS
  }
])

// Plan #13 §5, verbatim. The evaluators below only return `2` when the evidence
// covers the whole criterion; a partially measured criterion is `1` with the
// unmeasured part named as `NO MEDIDO`.
const RUBRIC = Object.freeze([
  {
    id: 1,
    title: 'Jerarquía de títulos (un `h1` visible, hero de página, topbar sin duplicarlo)',
    evaluate: criterionHeadings
  },
  {
    id: 2,
    title:
      'Densidad de texto (palabras del hero/subtítulo y párrafos antes del primer control; ≤20 palabras = 2, ≥40 = 0)',
    evaluate: criterionDensity
  },
  {
    id: 3,
    title: 'Información repetida (mismo dato dos veces en la misma vista sin función distinta)',
    evaluate: criterionRepetition
  },
  {
    id: 4,
    title:
      'Acciones con efecto (toda acción visible hace lo que promete; el deshabilitado explica por qué)',
    evaluate: criterionActions
  },
  {
    id: 5,
    title:
      'Estados completos (carga con skeleton, vacío con CTA, error con reintento; falta uno = 0)',
    evaluate: criterionStates
  },
  {
    id: 6,
    title:
      'Primitivas (`UiButton`, `UiModal`, `UiDataTable`, `UiEmptyState`, `UiTabs`; sin overlay propio)',
    evaluate: criterionPrimitives
  },
  {
    id: 7,
    title:
      'Feedback (toast para acción sin superficie, error inline en el campo, confirmación en destructivo)',
    evaluate: criterionFeedback
  },
  {
    id: 8,
    title: 'Navegación (volver, breadcrumb, cierre de modal, filtros conservados, deep link)',
    evaluate: criterionNavigation
  },
  {
    id: 9,
    title:
      'Accesibilidad (foco, `aria-label`, teclado Esc/Enter/Tab, contraste, `prefers-reduced-motion`)',
    evaluate: criterionAccessibility
  },
  {
    id: 10,
    title:
      'Responsive (390 px: sin solapamiento, sin scroll horizontal de documento, tabla con affordance)',
    evaluate: criterionResponsive
  },
  {
    id: 11,
    title: 'Formato de datos (fecha, hora, moneda y estado con un solo formato y un solo badge)',
    evaluate: criterionFormats
  },
  {
    id: 12,
    title:
      'Honestidad de la superficie (nada visible que la API no soporte; nada soportado sin superficie declarado como follow-up)',
    evaluate: criterionHonesty
  }
])

// --- readers ----------------------------------------------------------------

const readJson = relPath => readEvidence(relPath)

const has = relPath => existsSync(evidencePath(relPath))

const readDirJson = dir => {
  let names = []

  try {
    names = readdirSync(evidencePath(dir))
  } catch {
    return []
  }

  return names
    .filter(name => name.endsWith('.json'))
    .sort()
    .map(name => ({ name: `${dir}/${name}`, payload: readJson(`${dir}/${name}`) }))
}

const clip = (value, max = 260) => {
  const text = String(value ?? '')
    .replace(/\s+/g, ' ')
    .trim()

  return text.length > max ? `${text.slice(0, max - 1)}…` : text
}

const unique = values => [...new Set((values ?? []).filter(value => value !== null))]

const countOf = (list, predicate) => (list ?? []).filter(predicate).length

const sumOf = (list, pick) => (list ?? []).reduce((total, item) => total + (pick(item) ?? 0), 0)

const countByVerdict = steps =>
  Object.fromEntries(
    VERDICTS.map(verdict => [verdict, countOf(steps, step => step.verdict === verdict)])
  )

const countVerdicts = steps => ({
  total: (steps ?? []).length,
  ok: countOf(steps, step => step.verdict === 'OK'),
  defecto: countOf(steps, step => step.verdict === 'DEFECTO'),
  noOp: countOf(steps, step => step.verdict === 'NO-OP'),
  confuso: countOf(steps, step => step.verdict === 'CONFUSO'),
  noProbado: countOf(steps, step => step.verdict === 'NO PROBADO')
})

const earliest = values => [...(values ?? [])].filter(Boolean).sort()[0] ?? null

const latest = values => [...(values ?? [])].filter(Boolean).sort().at(-1) ?? null

const pathOf = value => {
  if (!value) return null

  try {
    return new URL(value, 'http://localhost').pathname
  } catch {
    return String(value)
  }
}

const escapeHtml = value =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')

const mdCell = (value, max = 320) => clip(value, max).replace(/\|/g, '\\|').replace(/\r?\n/g, ' ')

const mdTable = (headers, rows) => {
  const lines = [
    `| ${headers.map(header => mdCell(header, 80)).join(' | ')} |`,
    `|${headers.map(() => ' --- ').join('|')}|`
  ]

  for (const row of rows) lines.push(`| ${row.map(cell => mdCell(cell)).join(' | ')} |`)

  return lines.join('\n')
}

const NO_MEDIDO = reason => ({ score: 1, justification: `NO MEDIDO — ${reason}` })

const isMeasured = result => !String(result.justification).startsWith('NO MEDIDO')

// --- evidence diagnostics ---------------------------------------------------

const pageErrorsOf = (walk, file) =>
  (walk?.pageErrors ?? []).map(entry => {
    if (typeof entry === 'string') return { file, block: null, error: entry }

    return {
      file: entry.file ?? file,
      block: entry.block ?? null,
      error: entry.error ?? String(entry)
    }
  })

const failureList = (walk, key, file) =>
  (walk?.failedRequests?.[key] ?? []).map(entry => {
    if (typeof entry === 'string') return { file, block: null, status: null, url: entry }

    return {
      file: entry.file ?? file,
      block: entry.block ?? null,
      status: entry.status ?? null,
      url: entry.url ?? entry.path ?? null
    }
  })

const consoleList = (walk, key, file) =>
  (walk?.consoleClassified?.[key] ?? []).map(entry => {
    if (typeof entry === 'string') return { file, block: null, message: entry, reason: null }

    return {
      file: entry.file ?? file,
      block: entry.block ?? null,
      message: entry.message ?? String(entry),
      reason: entry.reason ?? null
    }
  })

const stepFile = (step, declared) => {
  if (step?.file) return step.file
  if (step?.block) return `${declared.file.replace(/\.json$/, '')}-${step.block}.json`

  return declared.file
}

/** Steps whose own evidence declares a forced HTTP status (the error block). */
const forcedSteps = (walk, declared) =>
  (walk?.steps ?? []).flatMap(step => {
    const { intercepted, applied } = step.detail ?? {}
    const status = intercepted?.status ?? null
    const path = applied?.path ?? step.request?.path ?? null

    if (!status || intercepted?.ok === false || !path) return []

    return [
      { status, path, step: step.id, block: step.block ?? null, file: stepFile(step, declared) }
    ]
  })

// --- layer 2 ----------------------------------------------------------------

const loadRender = () => {
  const entries = readDirJson('render')
  const rows = []
  const malformed = []

  for (const entry of entries) {
    if (!entry.payload || typeof entry.payload !== 'object') {
      malformed.push(entry.name)
      continue
    }

    rows.push({ ...entry.payload, evidenceFile: entry.name })
  }

  return { files: entries.length, rows, malformed }
}

const renderAnomalies = rows => {
  const anomalies = []

  const push = (row, kind, verdict, fact, extra = {}) =>
    anomalies.push({
      file: row.evidenceFile,
      route: row.route,
      slug: row.slug,
      label: row.label,
      role: row.role,
      expectation: row.expectation,
      kind,
      verdict,
      fact,
      shot: (row.shots ?? [])[0] ?? null,
      request: extra.request ?? null
    })

  for (const row of rows) {
    const pageErrors = row.pageErrors ?? []
    const unexpectedRequests = row.failedRequests?.unexpected ?? []
    const unexpectedConsole = row.consoleClassified?.unexpected ?? []
    const headingCount = row.headings?.count ?? row.headingCount ?? null

    if (typeof row.rendered !== 'boolean' || typeof row.contentLength !== 'number') {
      push(
        row,
        'unmeasured',
        'NO PROBADO',
        `la fila no publica rendered/contentLength (rendered ${row.rendered ?? '—'}, contentLength ${row.contentLength ?? '—'})`
      )
    } else if (row.expectation !== 'negative' && row.rendered !== true) {
      push(
        row,
        'not-rendered',
        'DEFECTO',
        `no renderiza: contentLength ${row.contentLength} ≤ umbral ${row.threshold ?? '—'}`
      )
    }

    if (pageErrors.length) {
      push(
        row,
        'pageerror',
        'DEFECTO',
        `${pageErrors.length} pageerror: ${clip(pageErrors[0], 120)}`
      )
    }

    if (unexpectedRequests.length) {
      push(
        row,
        'unexpected-http',
        'DEFECTO',
        `${unexpectedRequests.length} HTTP ≥ 400: ${unexpectedRequests
          .map(failure => `${failure.status ?? '—'} ${pathOf(failure.url)}`)
          .join(', ')}`
      )
    }

    if (unexpectedConsole.length) {
      push(
        row,
        'unexpected-console',
        'DEFECTO',
        `${unexpectedConsole.length} console.error no clasificado: ${clip(unexpectedConsole[0]?.message ?? unexpectedConsole[0], 120)}`
      )
    }

    if (row.horizontalOverflow === true) {
      push(
        row,
        'overflow',
        'DEFECTO',
        `documento ${row.viewports?.mobile?.scrollWidth ?? '—'} px > viewport ${row.viewports?.mobile?.innerWidth ?? '—'} px`
      )
    }

    if (headingCount === null) {
      push(row, 'heading-unmeasured', 'NO PROBADO', 'la fila no publica el conteo de `h1`')
    } else if (headingCount !== 1) {
      push(row, 'heading', 'DEFECTO', `${headingCount} \`h1\` visibles`)
    }

    const affordances = row.denial?.writeAffordances ?? []

    if (row.expectation === 'negative' && affordances.length) {
      const door = row.denial?.checked?.writeDoor ?? null

      push(
        row,
        'negative-affordance',
        'DEFECTO',
        `ofrece ${affordances.map(entry => `«${entry.label}»`).join(', ')} a un rol cuya puerta ${
          door ? `${door.method} ${door.path}` : '—'
        } responde ${door?.status ?? '—'}`,
        { request: door }
      )
    }
  }

  return anomalies
}

const renderRowSummary = row => ({
  evidenceFile: row.evidenceFile,
  route: row.route,
  slug: row.slug,
  label: row.label,
  role: row.role,
  expectation: row.expectation,
  href: row.href ?? null,
  httpStatus: row.httpStatus ?? null,
  rendered: row.rendered === true,
  measured: typeof row.rendered === 'boolean' && typeof row.contentLength === 'number',
  contentLength: row.contentLength ?? null,
  threshold: row.threshold ?? null,
  headingCount: row.headings?.count ?? row.headingCount ?? null,
  headings: row.headings?.texts ?? null,
  tableHeaders: row.tableHeaders ?? [],
  rows: row.rows ?? 0,
  rowTexts: row.rowTexts ?? [],
  horizontalOverflow: row.horizontalOverflow ?? null,
  mobile: row.viewports?.mobile ?? null,
  shots: row.shots ?? [],
  pageErrors: (row.pageErrors ?? []).length,
  consoleErrors: (row.consoleErrors ?? []).length,
  unexpectedConsole: (row.consoleClassified?.unexpected ?? []).length,
  unexpectedRequests: (row.failedRequests?.unexpected ?? []).length,
  expectedRequests: (row.failedRequests?.expected ?? []).length,
  writeAffordances: (row.denial?.writeAffordances ?? []).map(entry => entry.label),
  writeDoor: row.denial?.checked?.writeDoor ?? null
})

const renderAggregate = ({ files, rows, malformed }, meta) => {
  const requiredRows = rows.filter(
    row => row.expectation === 'admin' || row.expectation === 'owner'
  )
  const surfaces = {}

  for (const row of rows) {
    surfaces[row.slug] ??= {
      route: row.route,
      label: row.label,
      rows: 0,
      required: 0,
      requiredRendered: 0,
      rendered: 0,
      overflow: 0,
      pageErrors: 0,
      unexpectedRequests: 0
    }

    const surface = surfaces[row.slug]
    surface.rows += 1

    if (row.expectation === 'admin' || row.expectation === 'owner') {
      surface.required += 1
      if (row.rendered === true) surface.requiredRendered += 1
    }

    if (row.rendered === true) surface.rendered += 1
    if (row.horizontalOverflow === true) surface.overflow += 1
    surface.pageErrors += (row.pageErrors ?? []).length
    surface.unexpectedRequests += (row.failedRequests?.unexpected ?? []).length
  }

  return {
    ...meta,
    stage: 'layer-2',
    files,
    malformed,
    rowsCount: rows.length,
    routes: unique(rows.map(row => row.route)),
    roles: unique(rows.map(row => row.role)),
    surfaces,
    totals: {
      rows: rows.length,
      files,
      measured: countOf(rows, row => renderRowSummary(row).measured),
      rendered: countOf(rows, row => row.rendered === true),
      requiredRows: requiredRows.length,
      requiredRendered: countOf(requiredRows, row => row.rendered === true),
      negativeRows: countOf(rows, row => row.expectation === 'negative'),
      negativeRowsWithWriteAffordances: countOf(
        rows,
        row => row.expectation === 'negative' && (row.denial?.writeAffordances ?? []).length > 0
      ),
      overflow: countOf(rows, row => row.horizontalOverflow === true),
      singleHeading: countOf(rows, row => (row.headings?.count ?? row.headingCount) === 1),
      pageErrors: sumOf(rows, row => (row.pageErrors ?? []).length),
      consoleErrors: sumOf(rows, row => (row.consoleErrors ?? []).length),
      unexpectedConsole: sumOf(rows, row => (row.consoleClassified?.unexpected ?? []).length),
      unexpectedRequests: sumOf(rows, row => (row.failedRequests?.unexpected ?? []).length),
      expectedRequests: sumOf(rows, row => (row.failedRequests?.expected ?? []).length)
    },
    anomalies: renderAnomalies(rows),
    failures: rows.flatMap(row =>
      (row.failedRequests?.unexpected ?? []).map(entry => ({
        file: row.evidenceFile,
        route: row.route,
        role: row.role,
        status: entry.status ?? null,
        url: entry.url ?? null
      }))
    ),
    rows: rows.map(renderRowSummary)
  }
}

// --- layer 4 ----------------------------------------------------------------

const mergeWalkBlocks = (declared, blocks) => {
  const steps = blocks.flatMap(entry =>
    entry.walk.steps.map(step => ({
      ...step,
      block: step.block ?? entry.block,
      file: step.file ?? entry.file
    }))
  )
  const first = blocks[0].walk

  return {
    session: SESSION,
    revision: first.revision ?? null,
    base: first.base ?? null,
    generatedAt: latest(blocks.map(entry => entry.walk.generatedAt)),
    surface: declared.surface,
    slug: declared.slug,
    test: `${declared.label} — bloques unidos por el informe`,
    steps,
    counts: countVerdicts(steps),
    pageErrors: blocks.flatMap(entry => pageErrorsOf(entry.walk, entry.file)),
    consoleErrors: blocks.flatMap(entry =>
      (entry.walk.consoleErrors ?? []).map(message => ({ file: entry.file, message }))
    ),
    consoleClassified: {
      expected: blocks.flatMap(entry => consoleList(entry.walk, 'expected', entry.file)),
      unexpected: blocks.flatMap(entry => consoleList(entry.walk, 'unexpected', entry.file))
    },
    failedRequests: {
      expected: blocks.flatMap(entry => failureList(entry.walk, 'expected', entry.file)),
      unexpected: blocks.flatMap(entry => failureList(entry.walk, 'unexpected', entry.file))
    },
    blocks: blocks.map(entry => ({
      block: entry.block,
      file: entry.file,
      present: true,
      steps: entry.walk.steps.length,
      counts: countVerdicts(entry.walk.steps)
    })),
    sourceFile: `${declared.file.replace(/\.json$/, '')}-*.json`,
    merged: true
  }
}

const loadWalk = declared => {
  const direct = readJson(declared.file)

  if (direct?.steps?.length) return { ...direct, sourceFile: declared.file, merged: false }

  let names = []

  try {
    names = readdirSync(evidencePath('.'))
  } catch {
    names = []
  }

  const base = declared.file.replace(/\.json$/, '')
  const blocks = names
    .filter(name => name.startsWith(`${base}-`) && name.endsWith('.json'))
    .sort()
    .map(name => ({
      block: name.slice(base.length + 1, -'.json'.length),
      file: name,
      walk: readJson(name)
    }))
    .filter(entry => entry.walk?.steps?.length)

  if (!blocks.length) return null

  return mergeWalkBlocks(declared, blocks)
}

const loadWalks = () => WALKS.map(declared => ({ declared, walk: loadWalk(declared) }))

const evidenceMeta = (renderRows, walkEntries) => {
  const objects = [...renderRows, ...walkEntries.map(entry => entry.walk).filter(Boolean)]
  const revision = objects.find(entry => entry.revision)?.revision ?? null
  const base = objects.find(entry => entry.base)?.base ?? null
  const dates = objects.map(entry => entry.generatedAt).filter(Boolean)

  return {
    session: SESSION,
    revision,
    base,
    evidenceFrom: earliest(dates),
    evidenceThrough: latest(dates)
  }
}

const walkStats = entry => {
  const { declared, walk } = entry
  const steps = walk?.steps ?? []

  return {
    file: declared.file,
    present: Boolean(walk),
    steps: steps.length,
    counts: countVerdicts(steps),
    pageErrors: pageErrorsOf(walk, declared.file).length,
    unexpectedRequests: failureList(walk, 'unexpected', declared.file).length,
    unexpectedConsole: consoleList(walk, 'unexpected', declared.file).length
  }
}

// --- BI matrix --------------------------------------------------------------

const biStep = (walk, reportKey, stateKey) => {
  const steps = walk?.steps ?? []

  if (stateKey === 'export' && reportKey === 'consolidated') {
    return (
      steps.find(
        step => step.block === 'export' && /consolidated/i.test(`${step.id} ${step.action}`)
      ) ?? null
    )
  }

  return steps.find(step => step.block === stateKey && step.detail?.report === reportKey) ?? null
}

const biLine = (step, stateKey, reportKey) => {
  const detail = step.detail ?? {}
  const screen = detail.screen ?? detail

  if (stateKey === 'data') {
    const headers = detail.headers ?? screen.headers ?? []
    const rowCount = detail.rowCount ?? screen.dataRows ?? (screen.rows ?? []).length
    const total = detail.total ?? screen.total ?? null
    const kpis = detail.kpis ?? screen.kpis ?? []
    const hooks = detail.hooks ?? []
    const dimensions = detail.dimensions ?? screen.dimension ?? null
    const parts = [
      `GET ${step.request?.status ?? '—'}`,
      `${rowCount} fila(s)${total !== null ? ` de ${total}` : ''}`,
      `${headers.length} cabecera(s)`
    ]

    if (dimensions?.selected) parts.push(`corte «${dimensions.selected}»`)
    if (detail.consolidated?.summary) {
      parts.push(
        `consolidado neto ${detail.consolidated.summary.net ?? '—'} · devoluciones ${detail.consolidated.summary.refunds ?? '—'}`
      )
    }
    if (detail.consolidatedBranch?.dataRows !== undefined) {
      parts.push(`${detail.consolidatedBranch.dataRows} fila(s) de sede`)
    }
    if (kpis.length)
      parts.push(
        kpis
          .slice(0, 4)
          .map(kpi => `${kpi.label} ${kpi.value}`)
          .join(' / ')
      )
    if (hooks.length) parts.push(`hooks [${hooks.join(', ')}]`)

    return parts.join(' · ')
  }

  if (stateKey === 'empty') {
    const { empty } = detail

    if (empty?.title) {
      return `vacío «${empty.title}» · «${empty.description ?? '—'}» · acción «${empty.action ?? '—'}»`
    }

    const kpis = detail.kpis ?? screen.kpis ?? []

    return `vacío ausente · ${detail.rowCount ?? screen.dataRows ?? 0} fila(s)${
      kpis.length
        ? ` · KPI ${kpis
            .slice(0, 4)
            .map(kpi => `${kpi.label} ${kpi.value}`)
            .join(' / ')}`
        : ''
    }`
  }

  if (stateKey === 'error') {
    const block = detail.errorBlock

    return `lectura forzada ${detail.applied?.status ?? '—'} · bloque «${block?.title ?? 'ausente'}» · «Reintentar» ${
      block?.retry ? 'ofrecido' : 'ausente'
    } · reintento ${detail.retried?.status ?? 'sin petición'}`
  }

  if (reportKey === 'consolidated') {
    return `no se exporta · ${(detail.buttons ?? []).length} botón(es) deshabilitado(s) · aviso «${clip(
      detail.hint,
      90
    )}»`
  }

  const formats = detail.exports ?? []
  const dataRows = screen.dataRows ?? null
  const total = screen.total ?? null
  const bits = formats.map(item => {
    const rows = item.match?.rows
    const headers =
      item.match?.headers === true
        ? 'cabeceras ok'
        : item.match?.headers === false
          ? 'cabeceras ✗'
          : 'cabeceras —'

    return `${item.format} ${item.status ?? '—'} ${headers} ${
      rows === true
        ? 'filas ok'
        : rows === false
          ? 'filas ✗'
          : item.parser === 'pdf'
            ? 'binario'
            : 'filas ?'
    }`
  })

  return `pantalla ${dataRows ?? '—'}${total !== null ? ` de ${total}` : ''} fila(s) · ${bits.join(' · ')}`
}

const biCell = (entry, report, state) => {
  const { declared, walk } = entry

  if (!walk) {
    return {
      verdict: 'NO PROBADO',
      line: `el artefacto ${declared.file} no existe`,
      step: null,
      file: declared.file
    }
  }

  const step = biStep(walk, report.key, state.key)

  if (!step) {
    return {
      verdict: 'NO PROBADO',
      line: `el recorrido no declara el estado «${state.label}» de este reporte`,
      step: null,
      file: declared.file
    }
  }

  return {
    verdict: step.verdict,
    line: biLine(step, state.key, report.key),
    step: step.id,
    file: stepFile(step, declared)
  }
}

const matchLabel = value =>
  value === true ? 'coinciden' : value === false ? 'divergen' : 'no comparable'

const exportVerdict = item => {
  if (item.ok !== true || item.status !== 200 || item.extensionOk !== true) return 'DEFECTO'
  if (item.parser === 'pdf') return item.pdf?.magic === true ? 'OK' : 'DEFECTO'

  const match = item.match ?? {}

  return match.headers !== false && match.rows === true && match.sample === true ? 'OK' : 'DEFECTO'
}

const biExportRows = entry => {
  const { declared, walk } = entry
  const rows = []

  for (const report of BI_REPORTS) {
    const step = biStep(walk, report.key, 'export')
    const evidence = step ? `${stepFile(step, declared)}#${step.id}` : declared.file

    if (report.key === 'consolidated') {
      rows.push({
        report: report.label,
        format: '— (no se exporta)',
        headers: '—',
        rows: '—',
        sample: '—',
        verdict: step?.verdict ?? 'NO PROBADO',
        evidence,
        note: step
          ? biLine(step, 'export', report.key)
          : 'el recorrido no mide la exportación del consolidado'
      })
      continue
    }

    if (!step?.detail?.exports?.length) {
      rows.push({
        report: report.label,
        format: '—',
        headers: '—',
        rows: '—',
        sample: '—',
        verdict: 'NO PROBADO',
        evidence,
        note: 'el recorrido no publica las tres descargas'
      })
      continue
    }

    for (const item of step.detail.exports) {
      rows.push({
        report: report.label,
        format: item.format ?? '—',
        headers: `pantalla ${(item.screenHeaders ?? []).length} · archivo ${(item.exportHeaders ?? []).length} · ${matchLabel(item.match?.headers)}`,
        rows: `pantalla ${item.screenRows ?? '—'}${item.screenTotal !== null && item.screenTotal !== undefined ? ` de ${item.screenTotal}` : ''} · archivo ${item.exportRows ?? '—'}${item.exportRowsTotal !== null && item.exportRowsTotal !== undefined ? ` de ${item.exportRowsTotal}` : ''} · ${matchLabel(item.match?.rows)}`,
        sample: item.sample
          ? `pantalla «${item.sample.screen ?? '—'}» · archivo «${item.sample.export ?? '—'}»`
          : 'no comparable (binario)',
        verdict: exportVerdict(item),
        evidence,
        note: clip((item.notes ?? []).join('; '), 200)
      })
    }
  }

  return rows
}

const biLegitDifferences = entry => {
  const { declared, walk } = entry
  const bullets = []

  for (const report of BI_REPORTS) {
    const step = biStep(walk, report.key, 'export')

    if (!step?.detail?.exports?.length) continue

    const text = step.detail.exports.find(item => item.parser !== 'pdf')
    const screen = step.detail.screen ?? {}

    if (!text) continue

    if (
      text.screenTotal !== null &&
      text.screenTotal !== undefined &&
      text.screenRows !== null &&
      text.screenTotal > text.screenRows
    ) {
      bullets.push(
        `**Paginación** — ${report.label}: la pantalla muestra ${screen.dataRows} de ${text.screenTotal} filas y el archivo lleva ${text.exportRows}${
          text.exportRowsTotal !== null && text.exportRowsTotal !== undefined
            ? ` de ${text.exportRowsTotal}`
            : ''
        }; el pie de la pantalla publica el total y el archivo lleva la población. Evidencia: \`${stepFile(step, declared)}#${step.id}\`.`
      )
    }

    if (report.key === 'revenue' && screen.dimension) {
      const options = walk?.steps?.find(item => item.id === '06-data-revenue')?.detail?.dimensions
        ?.options

      bullets.push(
        `**Los cinco cortes de ingresos** — ${report.label}: la pantalla pinta el corte «${screen.dimension}» (${text.screenRows} fila) y el archivo lleva ${text.exportRows} filas; el selector declara ${
          options?.length ?? '—'
        } cortes de la misma plata y el export los suma todos. Evidencia: \`${stepFile(step, declared)}#${step.id}\`.`
      )
    }

    const dashboardNote = report.key === 'dashboard' ? (text.notes ?? []).join('; ') : ''

    if (dashboardNote) {
      bullets.push(
        `**Dashboard sin tabla** — ${report.label}: ${clip(dashboardNote, 220)}. Evidencia: \`${stepFile(step, declared)}#${step.id}\`.`
      )
    }
  }

  const pdf = (walk?.steps ?? [])
    .flatMap(step => (step.detail?.exports ?? []).map(item => ({ item, step })))
    .find(({ item }) => item.parser === 'pdf' && item.pdf?.magic)

  if (pdf) {
    const source = pdf.item.filename ? `\`${pdf.item.filename}\`` : 'el archivo'

    bullets.push(
      `**PDF binario** — ${source} (${pdf.item.bytes ?? '—'} B) empieza con la firma \`%PDF\`; DomPDF no expone cabeceras ni celdas, así que la comparación de contenido se limita a CSV y Excel. Evidencia: \`${stepFile(pdf.step, entry.declared)}#${pdf.step.id}\`.`
    )
  }

  return bullets
}

const biMatrix = entry => ({
  present: Boolean(entry.walk),
  file: entry.declared.file,
  stepCount: (entry.walk?.steps ?? []).length,
  rows: BI_REPORTS.map(report => ({
    key: report.key,
    report: report.label,
    cells: Object.fromEntries(BI_STATES.map(state => [state.key, biCell(entry, report, state)]))
  })),
  exportRows: biExportRows(entry),
  legit: biLegitDifferences(entry)
})

// --- dialogues, findings and the gate ---------------------------------------

const collectDialogs = walkEntries => {
  const rows = []
  const seen = new Set()

  const visit = (node, meta) => {
    if (!node || typeof node !== 'object') return

    if (Array.isArray(node)) {
      for (const item of node) visit(item, meta)
      return
    }

    if (Array.isArray(node.checks)) {
      for (const check of node.checks) {
        if (!check || typeof check !== 'object' || !check.id) continue

        const key = `${meta.file}|${meta.step}|${check.id}`

        if (seen.has(key)) continue
        seen.add(key)
        rows.push({
          file: meta.file,
          step: meta.step,
          id: check.id,
          ok: check.ok === true,
          observed: check.observed ?? null,
          expected: check.expected ?? null
        })
      }
    }

    for (const value of Object.values(node)) visit(value, meta)
  }

  for (const entry of walkEntries) {
    if (!entry.walk) continue

    for (const step of entry.walk.steps ?? []) {
      visit(step.detail, { file: stepFile(step, entry.declared), step: step.id })
    }

    visit(entry.walk.dialogs, { file: entry.declared.file, step: 'dialogs' })
  }

  const groups = new Set(rows.map(row => `${row.file}|${row.step}`))
  const failures = rows.filter(row => !row.ok)

  return {
    rows,
    probed: groups.size,
    checks: rows.length,
    failures: failures.length,
    failuresByCheck: Object.fromEntries(
      unique(failures.map(row => row.id)).map(id => [id, countOf(failures, row => row.id === id)])
    ),
    failing: failures
  }
}

/** The dialog checks whose evidence belongs to one surface's own walk files. */
const filterDialogs = (dialogs, declared, walk) => {
  const files = new Set([
    declared.file,
    ...(walk?.steps ?? []).map(step => stepFile(step, declared))
  ])
  const rows = dialogs.rows.filter(row => files.has(row.file))
  const groups = new Set(rows.map(row => `${row.file}|${row.step}`))
  const failing = rows.filter(row => !row.ok)

  return {
    rows,
    probed: groups.size,
    checks: rows.length,
    failures: failing.length,
    failuresByCheck: Object.fromEntries(
      unique(failing.map(row => row.id)).map(id => [id, countOf(failing, row => row.id === id)])
    ),
    failing
  }
}

const missingRenderRows = rows => {
  const missing = []

  for (const surface of SURFACES) {
    const surfaceRows = rows.filter(row => row.slug === surface.key)

    if (!surfaceRows.length) {
      missing.push({ ...surface, expectation: 'admin y owner' })
      continue
    }

    for (const expectation of ['admin', 'owner']) {
      if (!surfaceRows.some(row => row.expectation === expectation)) {
        missing.push({ ...surface, expectation })
      }
    }
  }

  return missing
}

const buildFindings = ({ walkEntries, anomalies, missing }) => {
  const findings = []
  const bySurface = Object.fromEntries(SURFACES.map(surface => [surface.key, []]))
  const next = () => `U10-F${String(findings.length + 1).padStart(2, '0')}`

  const push = (surfaceKey, finding) => {
    findings.push({ id: next(), ...finding })
    if (bySurface[surfaceKey]) bySurface[surfaceKey].push(findings.at(-1))
  }

  for (const entry of walkEntries) {
    const { declared, walk } = entry

    if (!walk) {
      push(declared.key, {
        surface: `${declared.surface} (${declared.label})`,
        action: `Ejecutar el recorrido de ${declared.label}`,
        expected: declared.expected,
        obtained: `el artefacto ${declared.file} no existe: la sesión no publica ninguna medición de este escritorio`,
        verdict: 'NO PROBADO',
        layer: 4,
        origin: 'NO VERIFICADO',
        request: null,
        shot: null,
        evidenceFile: declared.file,
        block: null,
        step: null
      })
      continue
    }

    for (const step of walk.steps ?? []) {
      if (step.verdict === 'OK') continue

      push(declared.key, {
        surface: `${declared.surface} (${declared.label})`,
        action: step.action ?? step.id,
        expected: step.expected ?? null,
        obtained: step.obtained ?? step.notes ?? null,
        verdict: step.verdict,
        layer: step.layer ?? 4,
        origin: step.origin ?? 'MEDIDO',
        request: step.request ?? null,
        shot: step.shot ?? null,
        evidenceFile: stepFile(step, declared),
        block: step.block ?? null,
        step: step.id ?? null
      })
    }
  }

  for (const anomaly of anomalies) {
    push(anomaly.slug, {
      surface: `${anomaly.route} (capa 2, rol ${anomaly.role})`,
      action: `Renderizar la superficie con el rol ${anomaly.role}`,
      expected:
        'La fila de capa 2 mide el render sin anomalías: pinta, un solo `h1`, sin pageerror ni HTTP ≥ 400 inesperado, sin desbordamiento y sin ofrecer escritura a un rol excluido',
      obtained: `capa 2 · ${anomaly.kind}: ${anomaly.fact}`,
      verdict: anomaly.verdict,
      layer: 2,
      origin: 'MEDIDO',
      request: anomaly.request ?? null,
      shot: anomaly.shot ?? null,
      evidenceFile: anomaly.file,
      block: 'render',
      step: null
    })
  }

  for (const miss of missing) {
    push(miss.key, {
      surface: `${miss.route} (capa 2)`,
      action: `Medir la capa 2 con expectation=${miss.expectation}`,
      expected:
        'Cada superficie tiene su fila de administración y su fila del rol dueño, ambas renderizadas',
      obtained: `no existe \`render/render-${miss.key}-*.json\` con expectation=${miss.expectation}`,
      verdict: 'NO PROBADO',
      layer: 2,
      origin: 'NO VERIFICADO',
      request: null,
      shot: null,
      evidenceFile: `render/render-${miss.key}-*.json`,
      block: 'render',
      step: null
    })
  }

  return { findings, bySurface }
}

const buildGate = ({ meta, render, walkEntries, dialogs, findings }) => {
  const reasons = []
  const walkPageErrors = walkEntries.flatMap(entry =>
    pageErrorsOf(entry.walk, entry.declared.file).map(error => ({
      ...error,
      file: error.file ?? entry.declared.file
    }))
  )
  const instrumentInduced = []
  const unexplained = [...render.failures]

  for (const entry of walkEntries) {
    if (!entry.walk) continue

    const forced = forcedSteps(entry.walk, entry.declared)

    for (const failure of failureList(entry.walk, 'unexpected', entry.declared.file)) {
      const path = pathOf(failure.url)
      const match = forced.find(item => item.status === failure.status && item.path === path)

      if (match) {
        instrumentInduced.push({
          ...failure,
          reason: `el paso ${match.step} declara ${match.status} forzado en ${match.path} (detail.intercepted)`
        })
      } else {
        unexplained.push(failure)
      }
    }
  }

  const instrumentInducedConsole = []
  const unexpectedConsole = render.rows
    .filter(row => row.unexpectedConsole > 0)
    .map(row => ({
      file: row.evidenceFile,
      block: 'render',
      message: `${row.unexpectedConsole} console.error no clasificado(s)`
    }))

  for (const entry of walkEntries) {
    if (!entry.walk) continue

    const forced = forcedSteps(entry.walk, entry.declared)

    for (const item of consoleList(entry.walk, 'unexpected', entry.declared.file)) {
      const status = Number(/status of (\d{3})/i.exec(item.message)?.[1] ?? 0) || null
      const match = status
        ? forced.find(step => step.status === status && (!item.block || step.block === item.block))
        : null

      if (match) {
        instrumentInducedConsole.push({
          ...item,
          reason: `console.error del ${status} forzado por el paso ${match.step} de ${match.file}`
        })
      } else {
        unexpectedConsole.push(item)
      }
    }
  }

  const steps = walkEntries.flatMap(entry =>
    (entry.walk?.steps ?? []).map(step => ({
      file: entry.declared.file,
      id: step.id,
      verdict: step.verdict ?? null
    }))
  )
  const unclassified = steps.filter(step => !VERDICTS.includes(step.verdict))
  const surfaces = SURFACES.map(surface => {
    const rows = render.rows.filter(row => row.slug === surface.key)
    const admin = rows.find(row => row.expectation === 'admin') ?? null
    const owner = rows.find(row => row.expectation === 'owner') ?? null

    return {
      key: surface.key,
      route: surface.route,
      label: surface.label,
      rows: rows.length,
      admin: admin?.rendered === true,
      owner: owner?.rendered === true,
      files: rows.map(row => row.evidenceFile)
    }
  })
  const renderedSurfaces = surfaces.filter(surface => surface.admin && surface.owner).length

  for (const surface of surfaces) {
    if (!surface.rows) {
      reasons.push(
        `capa 2 sin filas para ${surface.route}: no existe render/render-${surface.key}-*.json`
      )
      continue
    }

    const admin = render.rows.find(row => row.slug === surface.key && row.expectation === 'admin')
    const owner = render.rows.find(row => row.slug === surface.key && row.expectation === 'owner')

    if (!admin) {
      reasons.push(
        `capa 2 sin fila de administración para ${surface.route}: no existe render/render-${surface.key}-*.json con expectation=admin`
      )
    } else if (admin.rendered !== true) {
      reasons.push(
        `capa 2: ${admin.evidenceFile} no renderiza con administración (contentLength ${admin.contentLength ?? '—'})`
      )
    }

    if (!owner) {
      reasons.push(
        `capa 2 sin fila del rol dueño para ${surface.route}: no existe render/render-${surface.key}-*.json con expectation=owner`
      )
    } else if (owner.rendered !== true) {
      reasons.push(
        `capa 2: ${owner.evidenceFile} no renderiza con su rol dueño (contentLength ${owner.contentLength ?? '—'})`
      )
    }
  }

  const unmeasured = render.rows.filter(row => row.measured !== true)

  for (const row of unmeasured) {
    reasons.push(`capa 2: ${row.evidenceFile} no publica rendered/contentLength`)
  }

  if (walkPageErrors.length) {
    const files = unique(walkPageErrors.map(error => error.file))

    reasons.push(`pageerror(s) en las caminatas: ${walkPageErrors.length} (${files.join(', ')})`)
  }

  for (const row of render.rows) {
    if (row.pageErrors > 0) {
      reasons.push(`pageerror(s) de capa 2: ${row.pageErrors} en ${row.evidenceFile}`)
    }
  }

  for (const failure of unexplained) {
    reasons.push(
      `HTTP ≥ 400 no declarado: ${failure.status ?? '—'} ${pathOf(failure.url)} en ${failure.file ?? 'la sesión'}`
    )
  }

  if (unexpectedConsole.length) {
    const detail = unexpectedConsole
      .slice(0, 5)
      .map(item => `${clip(item.message, 100)} (${item.file ?? 'consola'})`)

    reasons.push(
      `console.error no clasificado: ${unexpectedConsole.length} (${detail.join(' · ')})`
    )
  }

  for (const entry of walkEntries) {
    if (!entry.walk) {
      reasons.push(
        `recorrido ausente: ${entry.declared.file} — la sesión no publica la medición de ${entry.declared.label}; sus puntos quedan NO PROBADO`
      )
      continue
    }

    if (!(entry.walk.steps ?? []).length) {
      reasons.push(
        `recorrido vacío: ${entry.declared.file} no declara ningún paso de ${entry.declared.label}`
      )
    }
  }

  if (unclassified.length) {
    reasons.push(
      `acciones sin veredicto del vocabulario: ${unclassified.map(step => `${step.file}#${step.id}`).join(', ')}`
    )
  }

  if (dialogs.failures > 0) {
    const files = unique(dialogs.failing.map(row => row.file))

    reasons.push(
      `contrato de diálogo: ${dialogs.failures} de ${dialogs.checks} comprobación(es) fallan (${files.join(', ')}): ${JSON.stringify(dialogs.failuresByCheck)}`
    )
  }

  const byVerdict = countByVerdict(steps)
  const findingsByVerdict = Object.fromEntries(
    ['DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO'].map(verdict => [
      verdict,
      countOf(findings, finding => finding.verdict === verdict)
    ])
  )

  return {
    ...meta,
    stage: 'gate',
    session: SESSION,
    issue: ISSUE,
    ok: reasons.length === 0,
    reasons,
    pageErrors: render.totals.pageErrors + walkPageErrors.length,
    pageErrorFiles: [
      ...render.rows.filter(row => row.pageErrors > 0).map(row => row.evidenceFile),
      ...unique(walkPageErrors.map(error => error.file))
    ],
    unexpectedRequests: unexplained.length,
    unexpectedRequestRows: unexplained,
    instrumentInducedRequests: instrumentInduced,
    console: {
      unexpected: unexpectedConsole.length,
      unexpectedRows: unexpectedConsole,
      instrumentInduced: instrumentInducedConsole
    },
    surfaces: {
      rendered: renderedSurfaces,
      total: surfaces.length,
      rows: {
        required: render.totals.requiredRows,
        rendered: render.totals.requiredRendered,
        measured: render.totals.rows,
        allRendered: render.totals.rendered
      },
      detail: surfaces
    },
    actions: {
      classified: steps.length - unclassified.length,
      total: steps.length,
      unclassified: unclassified.length,
      byVerdict,
      perWalk: Object.fromEntries(walkEntries.map(entry => [entry.declared.file, walkStats(entry)]))
    },
    dialogs: {
      probed: dialogs.probed,
      failures: dialogs.failures,
      checks: dialogs.checks,
      failuresByCheck: dialogs.failuresByCheck
    },
    counts: {
      byVerdict,
      renderAnomalies: render.anomalies.length,
      findings: { total: findings.length, byVerdict: findingsByVerdict }
    },
    walks: walkEntries.map(walkStats),
    render: {
      files: render.files,
      rows: render.totals.rows,
      measured: render.totals.measured,
      rendered: render.totals.rendered,
      overflow: render.totals.overflow,
      singleHeading: render.totals.singleHeading,
      negativeRows: render.totals.negativeRows,
      negativeRowsWithWriteAffordances: render.totals.negativeRowsWithWriteAffordances
    },
    artefacts: {}
  }
}

// --- rubric evaluators ------------------------------------------------------

const heroOf = ctx => ctx.steps.map(step => step.detail?.hero).find(hero => hero?.title) ?? null

const DATE_SHAPES = Object.freeze([
  { name: 'dd/mm/yyyy', pattern: /\b\d{1,2}\/\d{1,2}\/\d{4}\b/ },
  {
    name: 'd mmm yyyy',
    pattern:
      /\b\d{1,2}\s+(?:ene|feb|mar|abr|may|jun|jul|ago|sept?|oct|nov|dic)[a-zé]*\.?\s+\d{4}\b/i
  },
  { name: 'yyyy-mm-dd', pattern: /\b\d{4}-\d{2}-\d{2}\b/ }
])

const dateShapesOf = text =>
  DATE_SHAPES.filter(shape => shape.pattern.test(String(text))).map(shape => shape.name)

// A detail may publish its rows as a list of cell arrays or as a bucketed
// object (`{ recalls: [...], requests: [...] }`), so every read is guarded: the
// generator aggregates shapes it does not own.
const asRows = value => {
  if (Array.isArray(value)) return value
  if (value && typeof value === 'object') {
    return Object.values(value).flatMap(entry => (Array.isArray(entry) ? entry : []))
  }

  return []
}

const textRowsOf = ctx => [
  ...ctx.render.flatMap(row => row.rowTexts ?? []),
  ...ctx.steps.flatMap(step => {
    const detail = step.detail ?? {}

    return [
      ...asRows(detail.rows),
      ...asRows(detail.screen?.rows),
      ...asRows(detail.consolidatedBranch?.rows)
    ]
  })
]

function criterionHeadings(ctx) {
  if (!ctx.render.length) return NO_MEDIDO(`no hay filas de capa 2 de ${ctx.surface}`)

  const bad = ctx.render.filter(row => (row.headings?.count ?? row.headingCount ?? null) !== 1)

  if (bad.length) {
    return {
      score: 0,
      justification: `${bad.length} fila(s) no miden un solo h1: ${bad
        .map(
          row => `${row.evidenceFile} → ${row.headings?.count ?? row.headingCount ?? 'sin dato'}`
        )
        .join(', ')}`
    }
  }

  const hero = heroOf(ctx)

  return {
    score: hero ? 2 : 1,
    justification: `un solo h1 en ${ctx.render.length} fila(s) (${ctx.render
      .map(row => row.evidenceFile)
      .join(
        ', '
      )})${hero ? `; hero «${hero.title}» («${hero.caption ?? '—'}»)` : '; hero NO MEDIDO'}`
  }
}

function criterionDensity(ctx) {
  const hero = heroOf(ctx)

  if (!hero) return NO_MEDIDO('el recorrido no publica el hero de la página')

  const words = `${hero.title ?? ''} ${hero.caption ?? ''}`.split(/\s+/).filter(Boolean).length
  const score = words <= 20 ? 2 : words >= 40 ? 0 : 1

  return {
    score,
    justification: `hero «${hero.title}» + subtítulo «${hero.caption ?? '—'}» = ${words} palabra(s) (≤20 = 2, ≥40 = 0)`
  }
}

function criterionRepetition() {
  return NO_MEDIDO(
    'la sesión no compara el mismo dato dos veces en la misma vista; las filas repetidas de una tabla no son el criterio'
  )
}

function criterionActions(ctx) {
  if (!ctx.steps.length) return NO_MEDIDO(`no hay recorrido de ${ctx.surface}`)

  const noOp = ctx.steps.filter(step => step.verdict === 'NO-OP')
  const wrong = ctx.steps.filter(step => step.verdict === 'DEFECTO' || step.verdict === 'CONFUSO')
  const ok = ctx.steps.filter(step => step.verdict === 'OK')

  if (noOp.length) {
    return {
      score: 0,
      justification: `${noOp.length} acción(es) sin efecto: ${noOp
        .map(step => `${step.id} «${step.action}»`)
        .join(' · ')}`
    }
  }

  if (wrong.length) {
    return {
      score: 1,
      justification: `${ok.length}/${ctx.steps.length} acciones con efecto declarado en ${ctx.walkFile}; ${wrong.length} con veredicto distinto: ${wrong
        .map(step => `${step.id} ${step.verdict}`)
        .join(', ')}`
    }
  }

  return {
    score: 2,
    justification: `${ctx.steps.length}/${ctx.steps.length} acciones con veredicto OK en ${ctx.walkFile}`
  }
}

function criterionStates(ctx) {
  const reportEmpty = step => ctx.key === 'bi' && Boolean(step.detail?.report)
  const errorSteps = ctx.steps.filter(
    step =>
      (step.block === 'error' && (ctx.key !== 'bi' || reportEmpty(step))) ||
      (!step.block && /\b500\b|reintent/i.test(`${step.action} ${step.expected}`))
  )
  const emptySteps = ctx.steps.filter(
    step =>
      (step.block === 'empty' && (ctx.key !== 'bi' || reportEmpty(step))) ||
      (!step.block && /vac[ií]o|sin datos/i.test(`${step.action} ${step.expected}`))
  )
  const loadingSteps = ctx.steps.filter(step =>
    /skeleton|spinner|cargando|loading/i.test(`${step.action} ${step.expected}`)
  )

  if (!errorSteps.length && !emptySteps.length) {
    return NO_MEDIDO('el recorrido no mide los estados de carga, vacío y error')
  }

  const errorOk = countOf(errorSteps, step => step.verdict === 'OK')
  const emptyOk = countOf(emptySteps, step => step.verdict === 'OK')
  const failingEmpty = emptySteps.filter(step => step.verdict !== 'OK')
  const failingError = errorSteps.filter(step => step.verdict !== 'OK')

  if (failingError.length) {
    return {
      score: 0,
      justification: `el error no se recupera en ${failingError.length}/${errorSteps.length} pasos: ${failingError
        .map(step => `${step.id} ${step.verdict}`)
        .join(', ')}`
    }
  }

  if (failingEmpty.length) {
    return {
      score: 0,
      justification: `el vacío sólo es propio en ${emptyOk}/${emptySteps.length}: ${failingEmpty
        .map(step => `${step.id} ${step.verdict}`)
        .join(', ')}; el error sí (${errorOk}/${errorSteps.length}) y la carga ${
        loadingSteps.length ? `se mide en ${loadingSteps.length} paso(s)` : 'NO MEDIDA'
      }`
    }
  }

  return {
    score: emptySteps.length && errorSteps.length ? 2 : 1,
    justification: `vacío ${emptyOk}/${emptySteps.length} · error ${errorOk}/${errorSteps.length} · carga ${
      loadingSteps.length ? `${loadingSteps.length} paso(s)` : 'NO MEDIDA'
    }`
  }
}

function criterionPrimitives(ctx) {
  const checks = ctx.dialogs.rows.filter(row =>
    /primitive|overlay|uimodal|uibutton|uidatatable|uiempty|uitabs/i.test(row.id)
  )

  if (!checks.length) {
    return NO_MEDIDO(
      'el recorrido no publica la comprobación de primitiva/overlay; el manifiesto declara un modal pero no su implementación'
    )
  }

  const failing = checks.filter(row => !row.ok)

  if (failing.length) {
    return {
      score: 0,
      justification: `${failing.length} comprobación(es) de primitiva fallan: ${failing
        .map(row => `${row.step}#${row.id}`)
        .join(', ')}`
    }
  }

  return {
    score: 2,
    justification: `${checks.length} comprobación(es) verdes: ${unique(checks.map(row => row.id)).join(', ')}`
  }
}

function criterionFeedback(ctx) {
  const channels = {
    inline: ctx.steps.filter(step => {
      const block = step.detail?.errorBlock

      return block?.retry === true
    }),
    toast: ctx.steps.filter(step => /toast/i.test(JSON.stringify(step.detail ?? {}))),
    field: ctx.steps.filter(
      step =>
        Array.isArray(step.detail?.fieldErrors) ||
        Array.isArray(step.detail?.errors) ||
        step.detail?.fieldErrors?.length > 0
    ),
    confirm: ctx.steps.filter(step =>
      /confirmaci|confirm\.|dialogText|alertdialog/i.test(JSON.stringify(step.detail ?? {}))
    )
  }
  const measuredChannels = Object.entries(channels).filter(([, steps]) => steps.length)

  if (!measuredChannels.length) {
    return NO_MEDIDO('el recorrido no publica toast, error de campo, confirmación ni error inline')
  }

  const failed = measuredChannels.filter(([, steps]) => steps.some(step => step.verdict !== 'OK'))

  if (failed.length) {
    return {
      score: 0,
      justification: `canales medidos con veredicto distinto de OK: ${failed
        .map(([name, steps]) => `${name} (${steps.map(step => step.id).join(', ')})`)
        .join(' · ')}`
    }
  }

  const missing = Object.keys(channels).filter(name => !channels[name].length)

  if (missing.length) {
    return {
      score: 1,
      justification: `medido: ${measuredChannels
        .map(([name, steps]) => `${name} (${steps.map(step => step.id).join(', ')})`)
        .join(', ')}; NO MEDIDO: ${missing.join(', ')}`
    }
  }

  return {
    score: 2,
    justification: `los cuatro canales se miden verdes: ${measuredChannels
      .map(([name, steps]) => `${name} ${steps.length}`)
      .join(' · ')}`
  }
}

function criterionNavigation(ctx) {
  const steps = ctx.steps.filter(step =>
    /volver|regres|breadcrumb|cerrar|deep link|enlace directo|filtros conservados/i.test(
      `${step.id} ${step.action} ${step.expected}`
    )
  )

  if (!steps.length) {
    return NO_MEDIDO(
      'el recorrido no mide volver, breadcrumb, cierre de modal, filtros conservados ni deep link'
    )
  }

  const failing = steps.filter(step => step.verdict !== 'OK')

  if (failing.length) {
    return {
      score: 0,
      justification: `${failing.length} paso(s) de navegación con veredicto distinto de OK: ${failing
        .map(step => `${step.id} ${step.verdict}`)
        .join(', ')}`
    }
  }

  return {
    score: 1,
    justification: `${steps.length} paso(s) de navegación OK (${steps
      .map(step => step.id)
      .join(', ')}); el resto del criterio NO MEDIDO`
  }
}

function criterionAccessibility(ctx) {
  const checks = ctx.dialogs.rows.filter(row =>
    /escape|focus|aria|label|tab|keyboard|trap|contrast|motion/i.test(row.id)
  )

  if (!checks.length) {
    return NO_MEDIDO(
      'el recorrido no publica las comprobaciones de teclado, foco o etiqueta accesible'
    )
  }

  // The walks publish the dialog contract inside the step, not only through the
  // aggregated rows: a check that failed there is a failure here.
  const stepFailures = ctx.steps.flatMap(step =>
    (step.detail?.contract?.failed ?? [])
      .filter(id => /escape|focus|aria|label|tab|keyboard|trap|contrast|motion/i.test(id))
      .map(id => ({ step: step.id, id, ok: false }))
  )
  const failing = [...checks.filter(row => !row.ok), ...stepFailures]

  if (failing.length) {
    return {
      score: 0,
      justification: `${failing.length} comprobación(es) de accesibilidad fallan: ${failing
        .map(row => `${row.step}#${row.id}`)
        .join(', ')}`
    }
  }

  const ids = unique(checks.map(row => row.id))
  const full = ids.length >= 3 && ids.some(id => /contrast|motion/i.test(id))

  return {
    score: full ? 2 : 1,
    justification: `${checks.length} comprobación(es) verdes [${ids.join(', ')}]${
      full ? '' : '; contraste y prefers-reduced-motion NO MEDIDO'
    }`
  }
}

function criterionResponsive(ctx) {
  if (!ctx.render.length) return NO_MEDIDO(`no hay filas de capa 2 de ${ctx.surface}`)

  const overflowing = ctx.render.filter(
    row =>
      row.horizontalOverflow === true ||
      (row.mobile?.scrollWidth ?? 0) > (row.mobile?.innerWidth ?? 0) + 1
  )

  if (overflowing.length) {
    return {
      score: 0,
      justification: `${overflowing.length} fila(s) desbordan a 390 px: ${overflowing
        .map(
          row =>
            `${row.evidenceFile} (${row.mobile?.scrollWidth ?? '—'}/${row.mobile?.innerWidth ?? '—'})`
        )
        .join(', ')}`
    }
  }

  const walkOverflow = ctx.steps.map(step => step.detail?.pageOverflow).find(Boolean)
  const affordance = ctx.steps
    .map(step => step.detail?.table ?? step.detail?.primitive)
    .find(item => item && 'scrollAffordance' in item)

  return {
    score: 1,
    justification: `sin scroll horizontal de documento a 390 px en ${ctx.render.length} fila(s)${
      walkOverflow
        ? ` y en el paso que recarga a 390 (${walkOverflow.scrollWidth}/${walkOverflow.innerWidth})`
        : ''
    }; ${affordance ? `tabla con affordance medido (${affordance.scrollAffordance})` : 'solapamiento y affordance de tabla NO MEDIDO'}`
  }
}

function criterionFormats(ctx) {
  const texts = textRowsOf(ctx).map(text => String(text))
  const shapes = unique(texts.flatMap(dateShapesOf))

  if (!shapes.length) return NO_MEDIDO('las filas medidas no publican fechas')

  if (shapes.length > 1) {
    return {
      score: 0,
      justification: `dos formatos de fecha en la misma superficie: ${shapes.join(', ')} (${ctx.render
        .map(row => row.evidenceFile)
        .join(', ')})`
    }
  }

  const money = unique(texts.flatMap(text => text.match(/S\/\s?[\d.,]+/g) ?? []))

  return {
    score: 1,
    justification: `fechas en un solo formato «${shapes[0]}» en ${texts.length} texto(s)${
      money.length ? `; moneda ${money.slice(0, 3).join(' / ')}` : ''
    }; el badge de estado único NO MEDIDO`
  }
}

function criterionHonesty(ctx) {
  const negative = ctx.render.filter(row => row.expectation === 'negative')
  const offending = negative.filter(row => (row.writeAffordances ?? []).length > 0)

  if (offending.length) {
    return {
      score: 0,
      justification: `${offending.length} fila(s) negativa(s) ofrecen controles que la API rechaza: ${offending
        .map(
          row =>
            `${row.evidenceFile} → ${JSON.stringify(row.writeAffordances)}${
              row.writeDoor
                ? ` · puerta ${row.writeDoor.method} ${row.writeDoor.path} → ${row.writeDoor.status ?? '—'}`
                : ''
            }`
        )
        .join(' · ')}`
    }
  }

  if (!negative.length) {
    return NO_MEDIDO('la capa 2 no mide la puerta negativa de esta superficie')
  }

  return {
    score: 1,
    justification: `la fila negativa (${negative.map(row => `${row.role}: ${row.evidenceFile}`).join(', ')}) no ofrece controles que la API rechace; el contrato completo (nada soportado sin superficie) NO MEDIDO`
  }
}

// --- rubric assembly --------------------------------------------------------

const SURFACE_COLUMNS = Object.freeze([
  { key: 'bi', label: 'BI' },
  { key: 'continuity', label: 'Continuidad' },
  { key: 'lab', label: 'Laboratorio' }
])

const buildRubric = contexts => {
  const criteria = RUBRIC.map(criterion => {
    const scores = {}

    for (const column of SURFACE_COLUMNS) {
      const ctx = contexts[column.key]
      const result = criterion.evaluate(ctx)

      scores[column.key] = { ...result, measured: isMeasured(result) }
    }

    const entries = Object.entries(scores)
    const measuredEntries = entries.filter(([, value]) => value.measured)
    let session

    if (!measuredEntries.length) {
      session = {
        score: 1,
        measured: false,
        justification: `NO MEDIDO — ninguna superficie midió «${criterion.title}»`
      }
    } else {
      const worst = Math.min(...entries.map(([, value]) => value.score))
      const detail = entries
        .map(
          ([key, value]) =>
            `${SURFACE_COLUMNS.find(column => column.key === key)?.label ?? key} ${value.score}`
        )
        .join(' · ')
      const unmeasured = entries
        .filter(([, value]) => !value.measured)
        .map(([key]) => SURFACE_COLUMNS.find(column => column.key === key)?.label ?? key)
      const zeros = entries
        .filter(([, value]) => value.score === 0)
        .map(([key]) => SURFACE_COLUMNS.find(column => column.key === key)?.label ?? key)
      const extras = []

      if (unmeasured.length) extras.push(`NO MEDIDO en ${unmeasured.join(', ')}`)
      if (zeros.length) extras.push(`el 0 lo fija ${zeros.join(', ')}`)

      if (criterion.id === 11) {
        const shapes = unique(
          SURFACE_COLUMNS.flatMap(column => contexts[column.key].dateShapes ?? [])
        )

        if (shapes.length > 1) {
          extras.push(
            `entre superficies se miden ${shapes.length} formatos: ${shapes.join(' frente a ')}`
          )
        }
      }

      session = {
        score: worst,
        measured: true,
        justification: `${detail}${extras.length ? `; ${extras.join('; ')}` : ''}`
      }
    }

    return { id: criterion.id, title: criterion.title, scores, session }
  })

  const totals = Object.fromEntries([
    ...SURFACE_COLUMNS.map(column => [
      column.key,
      criteria.reduce((total, criterion) => total + criterion.scores[column.key].score, 0)
    ]),
    ['session', criteria.reduce((total, criterion) => total + criterion.session.score, 0)]
  ])

  return { columns: SURFACE_COLUMNS, criteria, totals }
}

// --- markdown ---------------------------------------------------------------

const walkEntryFor = (walkEntries, key) => walkEntries.find(entry => entry.declared.key === key)

const headerSection = ({ meta, render, walkEntries, gate }) => {
  const lines = [
    `# U10 — BI, continuidad y laboratorio (${ISSUE})`,
    '',
    `- **Sesión**: \`${SESSION}\` · **Issue**: \`${ISSUE}\` (plan #13, capa 6)`,
    `- **Revisión medida**: \`${meta.revision ?? 'desconocida'}\` · **Base**: \`${meta.base ?? 'desconocida'}\``,
    `- **Fecha de la evidencia**: ${meta.evidenceFrom ?? '—'} → ${meta.evidenceThrough ?? '—'}`,
    `- **Superficies**: ${SURFACES.map(surface => `\`${surface.route}\` (${surface.label})`).join(' · ')}`,
    `- **Roles medidos en capa 2**: ${unique(render.rows.map(row => row.role)).join(', ')}`,
    `- **Evidencia cruda**: \`${EVIDENCE}/\``,
    '',
    `Generado por \`tests/e2e/audit/u10/report.js\`. No mide: agrega los \`render/render-*.json\` y los recorridos que las filas de capa 2 y 4 escribieron.`,
    '',
    '> Veredictos de capa 4 (plan §4): `OK` · `DEFECTO` · `NO-OP` · `CONFUSO` · `NO PROBADO`.',
    '',
    '| Superficie | Ruta | Filas de capa 2 | Recorrido | Pasos |',
    '| --- | --- | --- | --- | --- |'
  ]

  for (const surface of SURFACES) {
    const rows = render.rows.filter(row => row.slug === surface.key)
    const entry = walkEntryFor(walkEntries, surface.key)
    const stats = entry ? walkStats(entry) : { present: false, steps: 0 }

    lines.push(
      `| ${surface.label} | \`${surface.route}\` | ${rows.length} | ${
        stats.present ? `\`${entry.declared.file}\`` : 'NO PROBADO'
      } | ${stats.steps} |`
    )
  }

  lines.push('')
  lines.push(
    `Filas de capa 2: ${render.totals.rows} · renderizan ${render.totals.rendered} · una sola \`h1\` ${render.totals.singleHeading} · sin desbordamiento a 390 px ${render.totals.rows - render.totals.overflow}/${render.totals.rows} · ${render.totals.pageErrors} pageerror · ${render.totals.unexpectedRequests} HTTP ≥ 400 inesperado.`
  )
  lines.push(
    `Fallos instrumentales declarados por el propio recorrido: ${gate.instrumentInducedRequests.length} HTTP ≥ 400 (\`walk-bi-error.json\`, \`detail.intercepted\`) y ${gate.console.instrumentInduced.length} console.error del 500 forzado; la puerta no los cuenta como defecto de la superficie y los publica en \`gate.json\`.`
  )

  return lines
}

const biSection = (bi, gate) => {
  const lines = [
    '',
    '## Matriz BI: reporte × estado',
    '',
    `Fuente: \`${bi.file}\` (${bi.stepCount} paso(s) medidos). Cada celda publica el veredicto del paso y su medición; un estado que el recorrido no declara queda \`NO PROBADO\`.`,
    ''
  ]

  lines.push(
    mdTable(
      ['Reporte', ...BI_STATES.map(state => state.label)],
      bi.rows.map(row => [
        row.report,
        ...BI_STATES.map(
          state => `**${row.cells[state.key].verdict}** — ${row.cells[state.key].line}`
        )
      ])
    )
  )

  lines.push('', '### Exportación frente a pantalla', '')
  lines.push(
    mdTable(
      [
        'Reporte',
        'Formato',
        'Cabeceras',
        'Filas (pantalla / archivo)',
        'Muestra',
        'Veredicto',
        'Evidencia'
      ],
      bi.exportRows.map(row => [
        row.report,
        row.format,
        row.headers,
        row.rows,
        row.sample,
        row.verdict,
        `${row.evidence}${row.note ? ` · ${row.note}` : ''}`
      ])
    )
  )

  lines.push('', '### Hechos que difieren legítimamente', '')

  if (bi.legit.length) {
    for (const bullet of bi.legit) lines.push(`- ${bullet}`)
  } else {
    lines.push('- El recorrido no publica diferencias legítimas que declarar.')
  }

  lines.push(
    '',
    `Puerta de la sesión: **${gate.ok ? 'OK' : 'NO CUMPLE'}** (los motivos están al final).`
  )

  return lines
}

const topicMatches = (topic, steps) =>
  steps.filter(step => {
    const text = `${step.id} ${step.action}`

    if (topic.match && !topic.match.test(text)) return false
    if (topic.exclude && topic.exclude.test(text)) return false

    if (topic.offered === true && !/ofrec|disponibl|posterior|opci|solo/i.test(text)) {
      return false
    }

    if (
      topic.blank === true &&
      !/(vac[ií]o|sin motivo|blanco|obligatori|vac)/i.test(`${text} ${step.expected ?? ''}`)
    ) {
      return false
    }

    if (
      topic.blank === false &&
      /(vac[ií]o|sin motivo|blanco|obligatori)/i.test(`${text} ${step.expected ?? ''}`)
    ) {
      return false
    }

    return true
  })

const WORST_VERDICT = Object.freeze(['DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO', 'OK'])

const worstVerdict = verdicts =>
  WORST_VERDICT.find(verdict => verdicts.includes(verdict)) ?? 'NO PROBADO'

const walkSection = (entry, title, dialogs) => {
  const { declared, walk } = entry
  const lines = ['', `## ${title} — recorrido`, '']

  if (!walk) {
    lines.push(
      `**NO PROBADO** — el artefacto \`${declared.file}\` no existe: la sesión no publica la medición de ${declared.label}. Sus puntos de criterio quedan \`NO PROBADO\` y la puerta lo nombra como motivo.`,
      ''
    )
  } else {
    const stats = walkStats(entry)
    const { counts } = stats

    lines.push(
      `Artefacto: \`${declared.file}\` · ${stats.steps} paso(s) · ${counts.ok} OK · ${counts.defecto} DEFECTO · ${counts.noOp} NO-OP · ${counts.confuso} CONFUSO · ${counts.noProbado} NO PROBADO · ${stats.pageErrors} pageerror.`,
      ''
    )
  }

  const steps = walk?.steps ?? []
  const topics = declared.topics ?? []

  if (topics.length) {
    lines.push(
      mdTable(
        ['Punto del criterio', 'Esperado', 'Veredicto', 'Evidencia medida'],
        topics.map(topic => {
          if (!walk) {
            return [
              topic.label,
              topic.expected,
              'NO PROBADO',
              `\`${declared.file}\` no existe: no hay paso que lo mida`
            ]
          }

          const matched = topicMatches(topic, steps)

          if (!matched.length) {
            return [
              topic.label,
              topic.expected,
              'NO PROBADO',
              `el recorrido no declara un paso que mida «${topic.label}»; pasos medidos: ${steps.length}`
            ]
          }

          const verdict = worstVerdict(matched.map(step => step.verdict))
          const evidence = matched
            .map(
              step => `\`${step.id}\` ${step.verdict}: ${clip(step.obtained ?? step.notes, 180)}`
            )
            .join(' · ')

          return [topic.label, topic.expected, verdict, evidence]
        })
      )
    )
  }

  if (steps.length) {
    lines.push('', '### Pasos medidos', '')
    lines.push(
      mdTable(
        ['#', 'Acción', 'Esperado', 'Obtenido', 'Veredicto', 'Evidencia'],
        steps.map(step => [
          step.id,
          step.action,
          step.expected,
          step.obtained ?? step.notes ?? '—',
          step.verdict,
          `\`${stepFile(step, declared)}#${step.id}\`${step.shot ? ` · \`${step.shot}\`` : ''}`
        ])
      )
    )
  }

  const dialogEvidence = filterDialogs(dialogs, declared, walk)

  lines.push('', '### Contrato del diálogo', '')

  if (!dialogEvidence.checks) {
    lines.push(
      'NO PROBADO — el recorrido no publica comprobaciones del contrato del diálogo para esta superficie (0 de 0).'
    )
  } else {
    lines.push(
      mdTable(
        ['Comprobación', 'Resultado', 'Observado', 'Evidencia'],
        dialogEvidence.rows.map(row => [
          row.id,
          row.ok ? 'OK' : 'DEFECTO',
          row.observed ?? '—',
          `\`${row.file}#${row.step}\``
        ])
      )
    )
  }

  return lines
}

const surfaceSubSections = ({ findingsBySurface, walkEntries, render }) => {
  const lines = ['', '## Superficies — filas con veredicto distinto de OK', '']

  for (const surface of SURFACES) {
    const rows = findingsBySurface[surface.key] ?? []
    const entry = walkEntryFor(walkEntries, surface.key)
    const stats = entry ? walkStats(entry) : null

    lines.push(`### ${surface.label} — \`${surface.route}\``, '')
    lines.push(
      `Capa 2: ${render.rows.filter(row => row.slug === surface.key).length} fila(s) · capa 4: ${
        stats?.present ? `${stats.steps} paso(s) en \`${entry.declared.file}\`` : 'sin recorrido'
      }.`,
      ''
    )

    if (!rows.length) {
      lines.push('Sin filas con veredicto distinto de OK.', '')
      continue
    }

    lines.push(
      mdTable(
        ['Fila', 'Acción', 'Veredicto', 'Evidencia', 'Captura'],
        rows.map(finding => [
          `\`${finding.evidenceFile}${finding.step ? `#${finding.step}` : ''}\``,
          finding.action,
          finding.verdict,
          finding.obtained ?? '—',
          finding.shot ? `\`${finding.shot}\`` : '—'
        ])
      )
    )
    lines.push('')
  }

  return lines
}

const s5Rows = ({ bi, walkEntries, gate, rubric }) => {
  const cellsOf = state => bi.rows.map(row => row.cells[state])
  const dataCells = cellsOf('data')
  const emptyCells = cellsOf('empty')
  const errorCells = cellsOf('error')
  const countIn = (list, verdict) => countOf(list, cell => cell.verdict === verdict)
  const emptyFailing = bi.rows.filter(row => row.cells.empty.verdict !== 'OK')
  const dataFailing = bi.rows.filter(row => row.cells.data.verdict !== 'OK')
  const errorFailing = bi.rows.filter(row => row.cells.error.verdict !== 'OK')
  const statesOk = !emptyFailing.length && !dataFailing.length && !errorFailing.length

  const exportByReport = {}

  for (const row of bi.exportRows) {
    if (row.format === '—' || row.format === '— (no se exporta)') continue
    exportByReport[row.report] ??= []
    exportByReport[row.report].push(row.verdict)
  }

  const exportable = Object.entries(exportByReport)
  const exportOk = exportable.filter(([, verdicts]) => verdicts.every(verdict => verdict === 'OK'))
  const exportFailing = exportable.filter(([, verdicts]) =>
    verdicts.some(verdict => verdict !== 'OK')
  )
  const continuity = walkEntryFor(walkEntries, 'continuity')
  const lab = walkEntryFor(walkEntries, 'lab-cases')
  const conflictStep = (continuity?.walk?.steps ?? []).find(step =>
    /conflicto/i.test(`${step.id} ${step.action}`)
  )
  const backwardStep = (lab?.walk?.steps ?? []).find(step =>
    /retroced|retroceso|anterior/i.test(`${step.id} ${step.action}`)
  )
  const fieldStep = (lab?.walk?.steps ?? []).find(step =>
    /campo|mensaje/i.test(`${step.id} ${step.action} ${step.expected}`)
  )
  const pages = []

  if (!continuity?.walk) {
    pages.push({
      verdict: 'Parcial',
      measurement: '`walk-continuity.json` no existe: el conflicto de agenda queda `NO PROBADO`'
    })
  } else {
    const verdict = conflictStep ? conflictStep.verdict : 'NO PROBADO'

    pages.push({
      verdict: verdict === 'OK' ? 'Cumple' : verdict === 'NO PROBADO' ? 'Parcial' : 'No cumple',
      measurement: conflictStep
        ? `\`${conflictStep.id}\` ${verdict}: ${clip(conflictStep.obtained ?? conflictStep.notes, 220)}`
        : 'el recorrido no declara un paso de conflicto'
    })
  }

  if (!lab?.walk) {
    pages.push({
      verdict: 'Parcial',
      measurement: '`walk-lab.json` no existe: la fase y su mensaje quedan `NO PROBADO`'
    })
  } else {
    const failing = [backwardStep, fieldStep].filter(step => step && step.verdict !== 'OK')
    const measured = [backwardStep, fieldStep].filter(Boolean)

    pages.push({
      verdict: failing.length ? 'No cumple' : measured.length === 2 ? 'Cumple' : 'Parcial',
      measurement: measured.length
        ? measured
            .map(
              step => `\`${step.id}\` ${step.verdict}: ${clip(step.obtained ?? step.notes, 160)}`
            )
            .join(' · ')
        : 'el recorrido no declara los pasos de retroceso y de mensaje en el campo'
    })
  }

  const statesMeasurement = `datos ${countIn(dataCells, 'OK')}/7 OK · vacío ${countIn(emptyCells, 'OK')}/7 OK · error ${countIn(errorCells, 'OK')}/7 OK; ${emptyFailing.length} reporte(s) sin estado vacío propio (${emptyFailing.map(row => row.report).join(', ') || 'ninguno'})`

  return [
    [
      'Cada reporte tiene sus tres estados (datos, vacío, error)',
      statesOk ? 'Cumple' : 'No cumple',
      statesMeasurement
    ],
    [
      'El export coincide con la pantalla',
      exportFailing.length === 0 ? 'Cumple' : 'No cumple',
      `${exportOk.length}/${exportable.length} reportes exportables con las tres descargas coincidentes; ${exportFailing.length} con cabeceras o filas divergentes (${exportFailing.map(([report]) => report).join(', ') || 'ninguno'}); el consolidado no se exporta y lo declara (${(bi.exportRows.find(row => row.format === '— (no se exporta)') ?? {}).verdict ?? 'NO PROBADO'})`
    ],
    [
      'El conflicto de agenda deja la solicitud pendiente y lo dice',
      pages[0].verdict,
      pages[0].measurement
    ],
    [
      'La fase de laboratorio no retrocede y el mensaje cae en su campo',
      pages[1].verdict,
      pages[1].measurement
    ],
    [
      'Cero `pageerror`',
      gate.pageErrors === 0 ? 'Cumple' : 'No cumple',
      `${gate.pageErrors} pageerror en ${gate.render.rows} fila(s) de capa 2 y ${walkEntries.filter(entry => entry.walk).length} recorrido(s) presentes`
    ],
    [
      'Rúbrica puntuada',
      'Cumple',
      `${rubric.criteria.length} criterio(s) × ${rubric.columns.length + 1} columna(s) = ${
        rubric.criteria.length * (rubric.columns.length + 1)
      } puntuaciones, cada una con su medición o su \`NO MEDIDO\` (ver \`ux-rubric.md\`)`
    ]
  ]
}

const criteriaSection = rows => {
  const lines = ['', '## Criterios de salida (#373 S5)', '']

  lines.push(mdTable(['Criterio', 'Veredicto', 'Medición'], rows))
  lines.push('')

  return lines
}

const buildWalkthrough = ({
  meta,
  render,
  walkEntries,
  bi,
  findingsBySurface,
  gate,
  rubric,
  dialogs
}) => {
  const lines = [...headerSection({ meta, render, walkEntries, gate }), ...biSection(bi, gate)]

  lines.push(...walkSection(walkEntryFor(walkEntries, 'continuity'), 'Continuidad', dialogs))
  lines.push(...walkSection(walkEntryFor(walkEntries, 'lab-cases'), 'Laboratorio', dialogs))
  lines.push(...surfaceSubSections({ findingsBySurface, walkEntries, render }))

  if (gate.reasons.length) {
    lines.push('', '## Motivos de la puerta', '')

    for (const reason of gate.reasons) lines.push(`- ${reason}`)
    lines.push('')
  }

  lines.push(...criteriaSection(s5Rows({ bi, walkEntries, gate, rubric })))

  return `${lines.join('\n')}\n`
}

const buildRubricMarkdown = rubric => {
  const lines = [
    `# Rúbrica UX/UI — U10 ${ISSUE}`,
    '',
    'Plan #13 §5: `0` ausente · `1` parcial o inconsistente · `2` canon. Puntuación por superficie primaria (BI, continuidad, laboratorio) y una vez para la sesión.',
    '',
    '| # | Criterio | BI | Continuidad | Laboratorio | Sesión |',
    '| --- | --- | --- | --- | --- | --- |'
  ]

  for (const criterion of rubric.criteria) {
    lines.push(
      `| ${criterion.id} | ${criterion.title} | ${criterion.scores.bi.score} | ${criterion.scores.continuity.score} | ${criterion.scores.lab.score} | ${criterion.session.score} |`
    )
  }

  lines.push(
    `| — | **Total** | **${rubric.totals.bi}/24** | **${rubric.totals.continuity}/24** | **${rubric.totals.lab}/24** | **${rubric.totals.session}/24** |`,
    '',
    '## Justificaciones',
    ''
  )

  for (const criterion of rubric.criteria) {
    lines.push(`### ${criterion.id} · ${criterion.title}`, '')

    for (const column of [...rubric.columns, { key: 'session', label: 'Sesión' }]) {
      const value = column.key === 'session' ? criterion.session : criterion.scores[column.key]

      lines.push(`- **${column.label} — ${value.score}**: ${value.justification}`)
    }

    lines.push('')
  }

  const noMedido = []

  for (const criterion of rubric.criteria) {
    for (const column of [...rubric.columns, { key: 'session', label: 'Sesión' }]) {
      const value = column.key === 'session' ? criterion.session : criterion.scores[column.key]

      if (!isMeasured(value))
        noMedido.push([`${criterion.id}`, criterion.title, column.label, value.justification])
    }
  }

  lines.push('## NO MEDIDO', '')

  if (noMedido.length) {
    lines.push(mdTable(['#', 'Criterio', 'Superficie', 'Motivo'], noMedido))
  } else {
    lines.push('La sesión midió los doce criterios en las tres superficies.')
  }

  lines.push('', '## Puntuaciones defendibles', '')
  lines.push(
    'Una puntuación es defendible si su justificación nombra el campo del JSON del que sale. Las que empiezan por `NO MEDIDO` no son una estimación: la sesión no midió ese criterio y la puntuación `1` es la convención de la rúbrica para «no probado», no una nota de calidad.'
  )

  return `${lines.join('\n')}\n`
}

// --- gallery ----------------------------------------------------------------

const collectShots = (dir = '.') => {
  const found = []

  const walk = relPath => {
    let entries = []

    try {
      entries = readdirSync(evidencePath(relPath), { withFileTypes: true })
    } catch {
      return
    }

    for (const entry of entries.sort((left, right) => left.name.localeCompare(right.name))) {
      const child = relPath === '.' ? entry.name : `${relPath}/${entry.name}`

      if (entry.isDirectory()) walk(child)
      else if (entry.name.endsWith('.png')) found.push(child)
    }
  }

  walk(dir)

  return found
}

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

const buildGalleryHtml = ({ meta, render, walkEntries, gate, findings }) => {
  const captions = new Map()

  for (const row of render.rows) {
    for (const shot of row.shots ?? []) {
      captions.set(
        shot,
        `${row.route} · ${row.role} · ${row.rendered ? 'renderiza' : 'no renderiza'}`
      )
    }
  }

  for (const entry of walkEntries) {
    for (const step of entry.walk?.steps ?? []) {
      if (step.shot) captions.set(step.shot, `${step.id} · ${step.verdict}`)

      // The walks also declare an explicit `capture` next to the step shot (the
      // report, state or export screen). It lives outside `shots/walk/`, so
      // without this entry the gallery would call it "capa 2".
      const capture = step.detail?.capture

      if (typeof capture === 'string' && capture.endsWith('.png')) {
        captions.set(capture, `${step.id} · ${step.verdict} (captura declarada)`)
      }
    }
  }

  const shots = collectShots('.')
  const renderShotSet = new Set(render.rows.flatMap(row => row.shots ?? []))
  const renderShots = shots.filter(shot => renderShotSet.has(shot))
  const walkShots = shots.filter(shot => !renderShotSet.has(shot))
  const findingsRows = findings
    .map(
      finding =>
        `<tr><td>${escapeHtml(finding.id)}</td><td><code>${escapeHtml(finding.surface)}</code></td><td>${escapeHtml(
          finding.action
        )}</td><td>${escapeHtml(finding.verdict)}</td><td>${escapeHtml(finding.layer)}</td><td><code>${escapeHtml(
          finding.evidenceFile
        )}</code></td></tr>`
    )
    .join('')

  return `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>U10 — BI, continuidad y laboratorio (${ISSUE})</title>
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
<h1>U10 — BI, continuidad y laboratorio (${ISSUE})</h1>
<p class="meta">revisión <code>${escapeHtml(meta.revision ?? '—')}</code> · base <code>${escapeHtml(
    meta.base ?? '—'
  )}</code> · evidencia ${escapeHtml(meta.evidenceFrom ?? '—')} → ${escapeHtml(
    meta.evidenceThrough ?? '—'
  )} · crudo en <code>${escapeHtml(EVIDENCE)}</code></p>
<div class="summary">
<div class="card"><h3>Puerta de la sesión</h3><p class="${
    gate.ok ? 'gate-ok' : 'gate-fail'
  }">${gate.ok ? 'OK' : 'NO CUMPLE'}</p><p>${gate.reasons.length} motivo(s)</p></div>
<div class="card"><h3>Capa 2 — render</h3><p>${gate.render.rows} fila(s) · ${
    gate.render.rendered
  } renderizan</p><p>${gate.pageErrors} pageerror · ${
    gate.unexpectedRequests
  } HTTP ≥ 400 no declarado</p><p>${
    gate.render.negativeRowsWithWriteAffordances
  } fila(s) negativa(s) con escritura ofrecida</p></div>
<div class="card"><h3>Capa 4 — recorridos</h3><p>${
    walkEntries.filter(entry => entry.walk).length
  } de ${walkEntries.length} recorrido(s)</p><p>${gate.actions.classified}/${
    gate.actions.total
  } acciones clasificadas</p><p>${findings.length} hallazgo(s) crudo(s)</p></div>
<div class="card"><h3>Diálogos</h3><p>${gate.dialogs.probed} diálogo(s) probado(s)</p><p>${
    gate.dialogs.failures
  } incumplimiento(s) de contrato</p></div>
</div>
<h2>Filas con veredicto distinto de OK</h2>
${
  findings.length
    ? `<table><thead><tr><th>id</th><th>superficie</th><th>acción</th><th>veredicto</th><th>capa</th><th>evidencia</th></tr></thead><tbody>${findingsRows}</tbody></table>`
    : '<p class="meta">Sin hallazgos crudos.</p>'
}
${galleryBlock(`Capa 2 — render (${renderShots.length})`, renderShots, shot => captions.get(shot) ?? 'capa 2')}
${galleryBlock(`Recorridos — capturas de los pasos (${walkShots.length})`, walkShots, shot => captions.get(shot) ?? 'capa 4')}
</body>
</html>
`
}

// --- the report -------------------------------------------------------------

test('@U10 · capa 6 — informe de la sesión', async () => {
  test.setTimeout(120000)

  const source = loadRender()
  const walkEntries = loadWalks()
  const meta = evidenceMeta(source.rows, walkEntries)
  const render = renderAggregate(source, meta)
  const bi = biMatrix(walkEntryFor(walkEntries, 'business-intelligence'))
  const dialogs = collectDialogs(walkEntries)
  const missing = missingRenderRows(source.rows)
  const { findings, bySurface } = buildFindings({
    walkEntries,
    anomalies: render.anomalies,
    missing
  })
  const gate = buildGate({ meta, render, walkEntries, dialogs, findings })
  const contexts = Object.fromEntries(
    [
      ['bi', 'business-intelligence'],
      ['continuity', 'continuity'],
      ['lab', 'lab-cases']
    ].map(([column, key]) => {
      const entry = walkEntryFor(walkEntries, key)
      const rows = render.rows.filter(row => row.slug === key)
      const walk = entry?.walk ?? null
      const ctx = {
        key: column,
        label: entry?.declared.label ?? key,
        surface: entry?.declared.surface ?? key,
        walkFile: entry?.declared.file ?? 'sin recorrido',
        walkPresent: Boolean(walk),
        render: rows,
        steps: walk?.steps ?? [],
        dialogs: filterDialogs(dialogs, entry.declared, walk)
      }

      ctx.dateShapes = unique(textRowsOf(ctx).flatMap(dateShapesOf))

      return [column, ctx]
    })
  )
  const rubric = buildRubric(contexts)

  const findingsPayload = {
    ...meta,
    stage: 'layer-4',
    session: SESSION,
    issue: ISSUE,
    plan: 'docs/mejoras/13-programa-auditoria-ui-funcional-2026-10.md §4 capas 2 y 4, §8.1',
    method:
      'una fila por paso con veredicto DEFECTO/NO-OP/CONFUSO/NO PROBADO y por anomalía de capa 2; severidad, clase raíz e issue no son del instrumento',
    totals: {
      findings: findings.length,
      byVerdict: Object.fromEntries(
        ['DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO'].map(verdict => [
          verdict,
          countOf(findings, finding => finding.verdict === verdict)
        ])
      ),
      byLayer: Object.fromEntries(
        [2, 4].map(layer => [layer, countOf(findings, finding => finding.layer === layer)])
      )
    },
    findings
  }

  const walkthrough = buildWalkthrough({
    meta,
    render,
    walkEntries,
    bi,
    findingsBySurface: bySurface,
    gate,
    rubric,
    dialogs
  })
  const rubricMarkdown = buildRubricMarkdown(rubric)
  const html = buildGalleryHtml({ meta, render, walkEntries, gate, findings })

  // The five human artefacts first; `gate.json` last, because it certifies their
  // existence and the gallery it names is already on disk.
  writeEvidence('render.json', render)
  writeFileSync(evidencePath('walkthrough.md'), walkthrough)
  writeEvidence('findings.raw.json', findingsPayload)
  writeFileSync(evidencePath('ux-rubric.md'), rubricMarkdown)
  writeFileSync(evidencePath('index.html'), html)

  gate.artefacts = Object.fromEntries(
    ARTEFACTS.map(name => [name, name === 'gate.json' || existsSync(evidencePath(name))])
  )

  writeEvidence('gate.json', gate)

  // --- the instrument's own assertions ------------------------------------
  // The gate verdict is the session's measurement, not this row's contract: a
  // missing walk must publish `NO CUMPLE` in `gate.json` and stay green here, so
  // the generator is never mistaken for the defect it reports.
  for (const name of ARTEFACTS) {
    expect(has(name), `artefacto ${name}`).toBe(true)
  }

  const writtenGate = JSON.parse(readFileSync(evidencePath('gate.json'), 'utf8'))
  const writtenRubric = readFileSync(evidencePath('ux-rubric.md'), 'utf8')

  expect(render.rowsCount, 'filas de capa 2 agregadas').toBe(source.files - source.malformed.length)
  expect(bi.rows, 'reportes de la matriz BI').toHaveLength(7)

  for (const row of bi.rows) {
    for (const state of BI_STATES) {
      expect(VERDICTS, `${row.report} · ${state.label}`).toContain(row.cells[state.key].verdict)
    }
  }

  const steps = walkEntries.flatMap(entry => entry.walk?.steps ?? [])

  expect(gate.actions.total, 'acciones declaradas por los recorridos').toBe(steps.length)
  expect(gate.actions.classified, 'acciones clasificadas').toBe(steps.length)
  expect(gate.ok, 'la puerta coincide con sus motivos').toBe(gate.reasons.length === 0)
  expect(writtenGate.ok, 'la puerta escrita').toBe(gate.ok)
  expect(writtenGate.artefacts, 'artefactos certificados por la puerta').toEqual(gate.artefacts)
  expect(rubric.criteria, 'criterios de la rúbrica').toHaveLength(12)
  expect(rubric.columns, 'columnas por superficie').toHaveLength(3)
  expect(writtenRubric.match(/^### \d+ · /gm) ?? [], 'justificaciones de la rúbrica').toHaveLength(
    12
  )
})
