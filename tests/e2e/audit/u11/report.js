// Plan #13 (issue #374, U11) — the session report: the 36×12 consistency matrix,
// the raw divergences, the proposed canon and the gate.
//
// This row does not measure. Every line comes from an artefact a browser row or
// the static pass already wrote: `manifest.json`, `static.json`, `render/*.json`
// and `primitives/*.json`. A missing render row is `NO PROBADO` — never filled
// with a guess — and it is the only structural reason the gate turns red.
//
// Verdicts (documented in `consistency-matrix.md`):
//   OK  the canon holds on this route
//   DIV the route diverges from the canon
//   N/A the axis does not apply to the route (no dialog, no filters, no
//       transactional feedback channel)
//   ACE the divergence is declared acceptable in `acceptances.json`
// A `DIV` is the deliverable, not a gate failure: the session reports the class
// of divergence so the triage can publish one issue per class.
//
// Usage: node tests/e2e/audit/u11/report.js [--out=<dir>] [--matrix=<file.md>]

import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, isAbsolute, resolve } from 'node:path'

const SESSION = 'U11'
const ISSUE = '#374'
const BASE = process.env.BROWSER_PASS_BASE || 'http://localhost:8000'
const SCOPE = process.env.AUDIT_SCOPE || 'all'

const AXES = Object.freeze([
  'dialog',
  'list',
  'title',
  'buttons',
  'states',
  'feedback',
  'filters',
  'badges',
  'format',
  'responsive',
  'density',
  'repetition'
])

const AXIS_LABELS = Object.freeze({
  dialog: 'Diálogo',
  list: 'Lista',
  title: 'Título',
  buttons: 'Botonera',
  states: 'Estados',
  feedback: 'Feedback',
  filters: 'Filtros',
  badges: 'Badges',
  format: 'Formato',
  responsive: 'Responsive',
  density: 'Densidad',
  repetition: 'Repetición'
})

const CANON = Object.freeze({
  dialog: {
    primitive: 'UiModal (Modal.vue)',
    reference: '/settings/payment-methods',
    why: 'Un solo lenguaje de ventana: centrado, foco atrapado, scroll bloqueado y Escape gestionados por la primitiva. El Sheet queda reservado al flujo largo que el canon declare.'
  },
  list: {
    primitive: 'UiDataTable (DataTable.vue) + Pagination',
    reference: '/settings/payment-methods',
    why: 'El scroll vive dentro de la tabla, con affordance, paginación y estados de carga/vacío compartidos.'
  },
  title: {
    primitive: 'un <h1> del shell y <h2 data-hero-title> en la página',
    reference: '/settings/accounting-locks',
    why: 'El shell conserva el h1 del documento (visible o sr-only) y el hero de la página titula con h2.'
  },
  buttons: {
    primitive: 'UiButton + vocabulario “Nuevo/Nueva <sustantivo>” y un verbo propio por envío',
    reference: '/patients',
    why: 'Los mismos verbos para la misma acción evitan que el usuario reaprenda cada módulo.'
  },
  states: {
    primitive: 'UiSkeleton (o LoadingSpinner) + UiEmptyState',
    reference: '/settings/accounting-locks',
    why: 'La carga se ve como esqueleto y el vacío como primitiva, no como un div con texto.'
  },
  feedback: {
    primitive: ':error en el campo (UiInput / useFieldValidation)',
    reference: '/cash-register',
    why: 'El 422 cae en el campo que lo provocó; el toast queda para la acción sin superficie.'
  },
  filters: {
    primitive: 'UiFilterBar (FilterBar.vue) con limpiar',
    reference: '/business-intelligence',
    why: 'Una barra de filtros con reset explícito, en vez de controles sueltos por página.'
  },
  badges: {
    primitive: 'UiStatusBadge (StatusBadge.vue)',
    reference: '/treatment-plans',
    why: 'Un solo badge genérico (variant + label) y una sola etiqueta por estado de dominio; UiStatusPill queda como alias deprecado.'
  },
  format: {
    primitive: 'useFormatters.formatCurrency + un helper de fecha compartido',
    reference: '/cash-register',
    why: 'Hoy la moneda tiene helper compartido y la fecha larga no: cada página usa toLocale*/Intl, y conviven es-ES y es-PE con dos relojes.'
  },
  responsive: {
    primitive: 'UiDataTable con scroll interno y sin desborde de documento',
    reference: '/medical-records',
    why: 'A 390 px el documento no se desplaza en horizontal; la tabla resuelve su propio ancho.'
  },
  density: {
    primitive: 'hero band (data-hero-title / data-hero-caption) con título ≤ 6 palabras',
    reference: '/settings/accounting-locks',
    why: 'El control manda: el título y su subtítulo no compiten con la tarea.'
  },
  repetition: {
    primitive: 'KPI strip + UiDataTable como fuentes únicas del dato',
    reference: '/settings/branches',
    why: 'El mismo dato no se publica dos veces en la misma vista sin una función distinta.'
  }
})

const QUESTIONS = Object.freeze({
  dialog:
    '¿El modal está centrado en todos los módulos? ¿Alguno abre al costado (Sheet) o con overlay propio?',
  list: '¿UiDataTable o <table> propio? ¿Misma paginación, mismo affordance, mismo vacío?',
  title: '¿Un solo h1? ¿El topbar oculta el suyo donde la página ya titula?',
  buttons: '¿Mismos verbos para la misma acción (Nuevo/Guardar/Cancelar/Eliminar)?',
  states: '¿Hay placeholder de carga y EmptyState declarado en todas?',
  feedback: '¿El 422 cae inline en el campo o sólo se avisa con toast?',
  filters: '¿Hay UiFilterBar con limpiar, o controles sueltos sin reset?',
  badges: '¿Un solo StatusPill/StatusBadge o una insignia propia con class*badge?',
  format: '¿Una sola forma de moneda y un helper de fecha compartido?',
  responsive: '¿Sin desborde horizontal de documento a 390 px?',
  density: '¿Hero ≤ 6 palabras y subtítulo ≤ 30?',
  repetition: '¿El mismo dato repetido dos veces en la misma vista?'
})

const RULES = Object.freeze([
  '`dialog`: `modal` en todas las rutas que abren diálogo → OK; `sheet` o overlay propio → DIV con la ruta y la primitiva; sin diálogo → N/A.',
  '`list`: `UiDataTable` o ninguna tabla → OK; `<table>` propio en la página de lista → DIV (una tabla dentro de un modal de detalle no decide el eje).',
  '`title`: exactamente un `h1` en el documento → OK; 0 o más de 1 → DIV.',
  '`buttons`: los disparadores de alta y los envíos dentro del vocabulario canónico (`Nuevo/Nueva <sustantivo>`, verbo propio por envío) → OK; `Crear`/`Agregar`/`Registrar` para un alta, o `Aceptar`/`Confirmar` sin objeto en un envío → DIV, con el label y la ruta. `Cancelar`/`Volver`/`Cerrar` no deciden el eje: dependen del contexto de la ventana.',
  '`states`: placeholder de carga observado (skeleton o spinner durante la carga) y vacío declarado cuando la ruta publica una lista → OK; falta cualquiera → DIV.',
  '`feedback`: inline (`:error`/`formErrors`) → OK; sólo toast en una ruta con escrituras → DIV; ruta de sólo lectura, sin canal → N/A.',
  '`filters`: sin controles ni acciones de filtro → N/A; `UiFilterBar` con reset → OK; controles o acciones sin reset, o sin `UiFilterBar` → DIV.',
  '`badges`: `UiStatusBadge` → OK; `UiStatusPill` (alias de dominio deprecado) o una insignia propia con `class*badge` → DIV.',
  '`format`: a lo sumo una forma de moneda y un helper de fecha compartido → OK; más de una forma de moneda, fecha formateada por página (`toLocale*`/`Intl.`), o el módulo de formato legacy para moneda → DIV. Sin moneda ni fechas renderizadas → N/A.',
  '`responsive`: sin desbordamiento de documento a 390 px → OK; con desbordamiento → DIV.',
  '`density`: `heroWords` ≤ 6 y `captionWords` ≤ 30 → OK; fuera → DIV; sin hero band → N/A (el título lo lleva el topbar).',
  '`repetition`: `count === 0` → OK; `> 0` → DIV.',
  'Convenciones: `N/A` declara que el eje no aplica a la ruta; `NO PROBADO` que no hay fila de render; `ACE` exige una entrada en `acceptances.json` (`{axis, route, reason}`) y el instrumento no la inventa.'
])

// --- readers ----------------------------------------------------------------

const parseOut = argv => {
  const flag = argv.find(argument => argument.startsWith('--out='))
  return flag ? flag.slice('--out='.length) : null
}

const parseMatrix = argv => {
  const flag = argv.find(argument => argument.startsWith('--matrix='))
  return flag ? flag.slice('--matrix='.length) : null
}

const OUT =
  parseOut(process.argv.slice(2)) || process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U11'
const MATRIX_COPY = parseMatrix(process.argv.slice(2))

const evidencePath = relPath =>
  isAbsolute(relPath) ? relPath : resolve(process.cwd(), OUT, relPath)

const readJson = relPath => {
  try {
    return JSON.parse(readFileSync(evidencePath(relPath), 'utf8'))
  } catch {
    return null
  }
}

const readDir = dir => {
  try {
    return readdirSync(evidencePath(dir))
  } catch {
    return []
  }
}

const writeJson = (relPath, payload) => {
  const absolute = evidencePath(relPath)
  mkdirSync(dirname(absolute), { recursive: true })
  writeFileSync(absolute, `${JSON.stringify(payload, null, 2)}\n`)
}

const writeText = (relPath, text) => {
  const absolute = isAbsolute(relPath) ? relPath : resolve(process.cwd(), relPath)
  mkdirSync(dirname(absolute), { recursive: true })
  writeFileSync(absolute, text)
}

// --- shape helpers ----------------------------------------------------------

const slugFor = route =>
  route
    .replace(/\([^)]*\)/g, ' ')
    .replace(/:/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'root'

const rolePlans = surface => {
  const visible = surface?.roles ?? []

  if (visible.length === 1 && visible[0] === 'anonymous') return [{ role: 'anonymous' }]

  const first = visible.find(role => role !== 'administrador') ?? 'administrador'

  return [{ role: first }]
}

const unique = values => [
  ...new Set((values ?? []).filter(value => value !== null && value !== undefined))
]

const clip = (value, max = 260) => {
  const text = String(value ?? '')
    .replace(/\s+/g, ' ')
    .trim()

  return text.length > max ? `${text.slice(0, max - 1)}…` : text
}

const escapeCell = value => clip(value, 320).replace(/\|/g, '\\|').replace(/\r?\n/g, ' ')

const mdTable = (headers, rows) => {
  const lines = [
    `| ${headers.map(header => escapeCell(header)).join(' | ')} |`,
    `|${headers.map(() => ' --- ').join('|')}|`
  ]

  for (const row of rows) lines.push(`| ${row.map(cell => escapeCell(cell)).join(' | ')} |`)

  return lines.join('\n')
}

// The shape collapses digit runs (2/6/1999 and 02/06/1999 are one format); a
// different separator or order stays a second shape.
const shapeOf = value => String(value ?? '').replace(/\d+/g, '#')

const countOf = (list, predicate) => (list ?? []).filter(predicate).length

// --- evidence ---------------------------------------------------------------

const manifest = readJson('manifest.json') ?? { surfaces: [] }
const staticArtifact = readJson('static.json')
const staticRows = new Map((staticArtifact?.rows ?? []).map(row => [row.route, row]))
const renderRows = new Map(
  readDir('render')
    .filter(name => name.endsWith('.json'))
    .map(name => [`render/${name}`, readJson(`render/${name}`)])
    .filter(([, payload]) => payload?.route)
    .map(([file, payload]) => [payload.route, { file, ...payload }])
)
const primitiveRows = new Map(
  readDir('primitives')
    .filter(name => name.endsWith('.json'))
    .map(name => [`primitives/${name}`, readJson(`primitives/${name}`)])
    .filter(([, payload]) => payload?.route)
    .map(([file, payload]) => [payload.route, { file, ...payload }])
)
const acceptances = readJson('acceptances.json') ?? []
const owners = readJson('owners.json') ?? {}

// The owner of an axis is either an issue the triage published or the written
// acceptance this session declared. `owners.json` is written by the session (it
// is evidence, not code): `{"dialog":"#391","list":"#451"}`.
const ownerFor = axis => (typeof owners[axis] === 'string' ? owners[axis] : ISSUE)

const acceptanceFor = (axis, route) =>
  (Array.isArray(acceptances) ? acceptances : []).find(
    entry => entry.axis === axis && (!entry.route || entry.route === route)
  ) ?? null

const navLabels = new Map((manifest.navigation ?? []).map(entry => [entry.to, entry.name]))

const routes = manifest.surfaces.map(surface => {
  const [plan] = rolePlans(surface)
  const slug = slugFor(surface.route)
  const expectedFile = `render/${slug}-${plan.role}.json`

  return {
    route: surface.route,
    label: navLabels.get(surface.route) ?? surface.name,
    role: plan.role,
    slug,
    surface,
    expectedFile,
    render: renderRows.get(surface.route) ?? null,
    primitives: primitiveRows.get(surface.route) ?? null,
    static: staticRows.get(surface.route) ?? null
  }
})

// --- verdicts ---------------------------------------------------------------

const loadingPresent = render =>
  Boolean(render?.loadingProbe?.skeleton) ||
  Boolean(render?.loadingProbe?.spinner) ||
  (render?.axes?.loading?.skeleton ?? 0) > 0 ||
  (render?.axes?.loading?.spinner ?? 0) > 0

const evaluate = (axis, context) => {
  const { staticRow, render, primitives, surface } = context
  const staticAxes = staticRow?.axes ?? null
  const measured = render?.axes ?? {}

  if (!render) {
    return {
      observed: 'sin fila de render',
      verdict: 'NO PROBADO',
      evidence: `falta ${context.expectedFile}`
    }
  }

  if (axis === 'title') {
    const count = measured.title?.h1Count ?? 0
    return {
      observed: `${count} h1 (topbar: ${measured.title?.topbarOwner ?? 'sin marca'})`,
      verdict: count === 1 ? 'OK' : 'DIV',
      evidence: `${context.render.file}#title h1Texts=${JSON.stringify(measured.title?.h1Texts ?? [])}`
    }
  }

  if (axis === 'responsive') {
    const overflow = render.horizontalOverflow ?? render.viewports?.mobile?.overflow ?? null
    return {
      observed: `390 px: scrollWidth ${render.viewports?.mobile?.scrollWidth ?? '—'} / ${render.viewports?.mobile?.innerWidth ?? '—'}`,
      verdict: overflow === false ? 'OK' : 'DIV',
      evidence: `${context.render.file}#viewports.mobile`
    }
  }

  if (axis === 'density') {
    const hero = measured.density?.heroWords ?? 0
    const caption = measured.density?.captionWords ?? 0
    const total = measured.density?.totalWords ?? 0

    if (hero === 0) {
      return {
        observed: `sin hero band (${total} palabras en la vista)`,
        verdict: 'N/A',
        evidence: `${context.render.file}#density`
      }
    }

    return {
      observed: `hero ${hero} palabras, subtítulo ${caption}`,
      verdict: hero <= 6 && caption <= 30 ? 'OK' : 'DIV',
      evidence: `${context.render.file}#density`
    }
  }

  if (axis === 'repetition') {
    const repeated = measured.repetition?.count ?? 0
    return {
      observed: `${repeated} grupo(s) repetido(s)`,
      verdict: repeated === 0 ? 'OK' : 'DIV',
      evidence: `${context.render.file}#repetition ${JSON.stringify(measured.repetition?.examples ?? [])}`
    }
  }

  if (!staticAxes) {
    return {
      observed: 'sin fila estática',
      verdict: 'NO PROBADO',
      evidence: 'falta static.json para esta ruta'
    }
  }

  if (axis === 'dialog') {
    const { dialog } = staticAxes
    const opens = dialog.manifestPrimitives.length > 0 || dialog.ownOverlay

    if (!opens) {
      return { observed: 'sin diálogo', verdict: 'N/A', evidence: `${context.staticRow.page}` }
    }

    const measuredDialogs = primitives?.dialogs ?? []
    const evidence = `${context.staticRow.page}; primitivas=${JSON.stringify(dialog.manifestPrimitives)}; imports=${JSON.stringify(dialog.imports)}; medidos=${JSON.stringify(
      measuredDialogs.map(row => ({
        primitive: row.primitive,
        outcome: row.outcome,
        centered: row.centered,
        docked: row.docked
      }))
    )}`

    if (dialog.ownOverlay) {
      return {
        observed: 'overlay propio (role="dialog" o fixed inset-0)',
        verdict: 'DIV',
        evidence
      }
    }

    if (dialog.imports.includes('Sheet') || dialog.manifestPrimitives.includes('sheet')) {
      return { observed: 'abre un Sheet anclado', verdict: 'DIV', evidence }
    }

    const modalOnly =
      dialog.manifestPrimitives.length > 0 &&
      dialog.manifestPrimitives.every(primitive => primitive === 'modal')

    return {
      observed: modalOnly
        ? 'UiModal centrado'
        : `primitivas ${JSON.stringify(dialog.manifestPrimitives)}`,
      verdict: modalOnly ? 'OK' : 'DIV',
      evidence
    }
  }

  if (axis === 'list') {
    const { list } = staticAxes

    if (list.dataTable) {
      return {
        observed: `UiDataTable (${measured.list?.tables ?? 0} tabla(s), ${measured.list?.pagination ?? 0} paginación)`,
        verdict: 'OK',
        evidence: `${context.staticRow.page}#list`
      }
    }

    const own = list.pageTables ?? list.rawTables

    if (own > 0) {
      return {
        observed: `${own} <table> propio(s) en la página`,
        verdict: 'DIV',
        evidence: `${context.staticRow.page}#list pageTables=${own}; paginación medida=${measured.list?.pagination ?? 0}, affordance=${measured.list?.scrollAffordance ?? 0}`
      }
    }

    return { observed: 'sin tabla', verdict: 'OK', evidence: `${context.staticRow.page}#list` }
  }

  if (axis === 'buttons') {
    const groups = staticAxes.buttons
    // Creation triggers and the submit of a create/edit dialog. `Cancelar`,
    // `Volver` and `Cerrar` are context verbs — aborting a form, leaving a view
    // and closing a panel are different acts — so they are recorded, not judged.
    const createAlternatives = (groups.create ?? []).filter(label =>
      /^(Crear|Agregar|Registrar)\b/.test(label)
    )
    const submitGenerics = (groups.submit ?? []).filter(label =>
      /^(Aceptar|Confirmar|OK)$/.test(label)
    )
    const wrong = unique([...createAlternatives, ...submitGenerics])
    const labels = unique([
      ...(groups.create ?? []),
      ...(groups.submit ?? []),
      ...(groups.cancel ?? []),
      ...(groups.delete ?? [])
    ])

    return {
      observed: wrong.length ? `verbos alternativos: ${wrong.join(', ')}` : 'vocabulario canónico',
      verdict: wrong.length ? 'DIV' : 'OK',
      evidence: `${context.staticRow.page}#buttons labels=${JSON.stringify(labels)}`
    }
  }

  if (axis === 'states') {
    const loading = loadingPresent(render)
    const publishesList = Boolean(
      staticAxes.list.dataTable ||
      (staticAxes.list.pageTables ?? 0) > 0 ||
      (measured.list?.tables ?? 0) > 0
    )
    const empty = Boolean(staticAxes.states.empty) || (measured.list?.emptyState ?? 0) > 0
    const missing = [loading ? null : 'carga', publishesList && !empty ? 'vacío' : null].filter(
      Boolean
    )

    return {
      observed: `carga ${loading ? 'medida' : 'ausente'}, vacío ${
        publishesList ? (empty ? 'declarado' : 'ausente') : 'no aplica'
      }`,
      verdict: missing.length ? 'DIV' : 'OK',
      evidence: `${context.staticRow.page}#states; ${context.render.file}#loadingProbe=${JSON.stringify(
        render.loadingProbe
      )}`
    }
  }

  if (axis === 'feedback') {
    if (staticAxes.feedback.inline) {
      return {
        observed: 'inline (:error / formErrors)',
        verdict: 'OK',
        evidence: `${context.staticRow.page}#feedback inline=true`
      }
    }

    const writes = (surface.actions ?? []).some(action =>
      ['create', 'edit', 'submit', 'delete'].includes(action.kind)
    )

    if (staticAxes.feedback.toast) {
      return {
        observed: 'sólo toast',
        verdict: writes ? 'DIV' : 'N/A',
        evidence: `${context.staticRow.page}#feedback toast=true inline=false writes=${writes}`
      }
    }

    return {
      observed: 'sin canal de feedback',
      verdict: 'N/A',
      evidence: `${context.staticRow.page}#feedback`
    }
  }

  if (axis === 'filters') {
    const filterActions = (surface.actions ?? []).filter(action => action.kind === 'filter')
    const hasFilters =
      Boolean(staticAxes.filters.filterBar) ||
      filterActions.length > 0 ||
      (measured.filters?.searchRegions ?? 0) > 0 ||
      (measured.filters?.anyControls ?? 0) > 0

    if (!hasFilters) {
      return {
        observed: 'sin filtros',
        verdict: 'N/A',
        evidence: `${context.staticRow.page}#filters`
      }
    }

    const reset =
      staticAxes.filters.filterBar &&
      (staticAxes.filters.resetLabels.length > 0 ||
        (measured.filters?.resetLabels ?? []).length > 0)

    return {
      observed: `filterBar=${Boolean(staticAxes.filters.filterBar)}, reset=${JSON.stringify(
        staticAxes.filters.resetLabels
      )}, controles=${measured.filters?.anyControls ?? 0}, acciones=${filterActions.length}`,
      verdict: reset ? 'OK' : 'DIV',
      evidence: `${context.staticRow.page}#filters; ${context.render.file}#filters=${JSON.stringify(
        measured.filters
      )}`
    }
  }

  if (axis === 'badges') {
    const { badges } = staticAxes

    if (badges.statusPill) {
      return {
        observed: 'UiStatusPill (alias de dominio deprecado)',
        verdict: 'DIV',
        evidence: `${context.staticRow.page}#badges statusPill=true statusBadge=${badges.statusBadge}`
      }
    }

    return {
      observed: `statusBadge=${badges.statusBadge}, ownBadge=${badges.ownBadge}`,
      verdict: badges.ownBadge && !badges.statusBadge ? 'DIV' : 'OK',
      evidence: `${context.staticRow.page}#badges; ${context.render.file}#badges=${JSON.stringify(
        measured.badges
      )}`
    }
  }

  if (axis === 'format') {
    const currencyShapes = unique((measured.format?.currency ?? []).map(shapeOf))
    const dateShapes = unique((measured.format?.dates ?? []).map(shapeOf))
    const rendersDates = (measured.format?.dates ?? []).length > 0
    const rendersMoney = (measured.format?.currency ?? []).length > 0
    const perPageDate = (staticAxes.format.locale || staticAxes.format.intl) && rendersDates
    const legacyMoney = staticAxes.format.legacyModule && rendersMoney
    const diverges =
      currencyShapes.length > 1 || dateShapes.length > 1 || perPageDate || legacyMoney

    if (!rendersMoney && !rendersDates && !staticAxes.format.locale && !staticAxes.format.intl) {
      return {
        observed: 'sin fecha ni moneda renderizada',
        verdict: 'N/A',
        evidence: `${context.staticRow.page}#format`
      }
    }

    return {
      observed: `moneda ${JSON.stringify(currencyShapes)}, fechas ${JSON.stringify(dateShapes)}, toLocale/Intl=${perPageDate}, módulo legacy=${Boolean(legacyMoney)}`,
      verdict: diverges ? 'DIV' : 'OK',
      evidence: `${context.staticRow.page}#format; ${context.render.file}#format=${JSON.stringify(
        measured.format
      )}`
    }
  }

  return { observed: 'eje no evaluado', verdict: 'NO PROBADO', evidence: 'evaluador ausente' }
}

const applyAcceptance = (axis, route, cell) => {
  if (cell.verdict !== 'DIV') return cell

  const declared = acceptanceFor(axis, route)

  if (!declared) return cell

  return {
    ...cell,
    verdict: 'ACE',
    evidence: `${cell.evidence}; aceptación: ${clip(declared.reason ?? 'sin motivo declarado')}`
  }
}

const matrixRoutes = routes.map(row => {
  const axes = {}

  for (const axis of AXES) {
    const cell = evaluate(axis, {
      surface: row.surface,
      staticRow: row.static,
      render: row.render,
      primitives: row.primitives,
      expectedFile: row.expectedFile
    })

    axes[axis] = applyAcceptance(axis, row.route, cell)
  }

  return { route: row.route, label: row.label, role: row.role, axes }
})

const buildAxisSummary = () => {
  const summary = {}

  for (const axis of AXES) {
    const cells = matrixRoutes.map(row => row.axes[axis])
    const ok = countOf(cells, cell => cell.verdict === 'OK')
    const div = countOf(cells, cell => cell.verdict === 'DIV')
    const ace = countOf(cells, cell => cell.verdict === 'ACE')
    const na = countOf(cells, cell => cell.verdict === 'N/A')
    const noProbado = countOf(cells, cell => cell.verdict === 'NO PROBADO')
    const measured = cells.length - noProbado

    let verdict = 'NO PROBADO'

    if (measured > 0) {
      if (div + ace > 0) verdict = 'DIV'
      else if (ok > 0) verdict = 'OK'
      else verdict = 'N/A'
    }

    summary[axis] = { verdict, ok, div, ace, na, noProbado, measured }
  }

  return summary
}

const expectedRoutes = () => {
  if (!SCOPE || SCOPE === 'all') return routes

  if (SCOPE.startsWith('route:')) {
    const route = SCOPE.slice('route:'.length)
    return routes.filter(row => row.route === route)
  }

  if (SCOPE.startsWith('module:')) {
    const modules = SCOPE.slice('module:'.length)
      .split(',')
      .map(name => name.trim())
      .filter(Boolean)

    return routes.filter(row =>
      modules.some(name => row.route === `/${name}` || row.route.startsWith(`/${name}/`))
    )
  }

  return routes
}

const axisSummary = buildAxisSummary()
const expected = expectedRoutes()
const missing = expected.filter(row => !row.render)
const rendered = routes.filter(row => row.render)
const pageErrors = routes.flatMap(row => {
  const errors = []

  for (const source of [row.render, row.primitives]) {
    if (!source) continue

    for (const error of source.pageErrors ?? []) {
      errors.push({ file: source.file, route: row.route, error: String(error) })
    }
  }

  return errors
})
const unexpectedRequests = routes.flatMap(row =>
  (row.render?.failedRequests?.unexpected ?? []).map(failure => ({
    file: row.render.file,
    route: row.route,
    status: failure.status ?? null,
    url: failure.url ?? null
  }))
)

// A 403 the product already owns is measured and declared, never hidden: the
// entry names the route, the endpoint and the issue that tracks it. `denials.json`
// is session evidence (`[{"route":"/x","endpoint":"/api/y","status":403,"reason":"#N"}]`).
const denials = readJson('denials.json') ?? []
const declaredRequests = []
const unexpectedOnly = []

for (const failure of unexpectedRequests) {
  const declared = (Array.isArray(denials) ? denials : []).find(
    entry =>
      entry.route === failure.route &&
      (!entry.endpoint || String(failure.url ?? '').includes(entry.endpoint)) &&
      (!entry.status || entry.status === failure.status)
  )

  if (declared) declaredRequests.push({ ...failure, reason: declared.reason })
  else unexpectedOnly.push(failure)
}
const missingVerdicts = AXES.filter(axis => axisSummary[axis].verdict === 'NO PROBADO')

const gate = {
  session: SESSION,
  revision: manifest.revision ?? staticArtifact?.revision ?? null,
  ok: false,
  reasons: [],
  pageErrors,
  unexpectedRequests: unexpectedOnly,
  declaredRequests,
  surfaces: { total: expected.length, measured: rendered.length, missing: missing.length },
  axisVerdicts: axisSummary
}

if (pageErrors.length) gate.reasons.push(`${pageErrors.length} pageerror(s)`)
if (missing.length) {
  gate.reasons.push(
    `${missing.length} fila(s) de render esperada(s) sin evidencia: ${missing
      .map(row => row.expectedFile)
      .slice(0, 6)
      .join(', ')}${missing.length > 6 ? '…' : ''}`
  )
}
if (missingVerdicts.length) gate.reasons.push(`eje(s) sin veredicto: ${missingVerdicts.join(', ')}`)
if (unexpectedOnly.length) {
  gate.reasons.push(`${unexpectedOnly.length} request(s) HTTP inesperado(s)`)
}

gate.ok = gate.reasons.length === 0

const findings = []

for (const axis of AXES) {
  for (const row of matrixRoutes) {
    const cell = row.axes[axis]

    if (cell.verdict !== 'DIV') continue

    findings.push({
      axis,
      route: row.route,
      expected: `${CANON[axis].primitive} (${CANON[axis].reference})`,
      observed: cell.observed,
      evidence: cell.evidence
    })
  }
}

const findingsRaw = findings.map((finding, index) => ({
  id: `F${String(index + 1).padStart(2, '0')}`,
  ...finding
}))

const canon = Object.fromEntries(
  AXES.map(axis => [
    axis,
    {
      question: QUESTIONS[axis],
      primitive: CANON[axis].primitive,
      reference: CANON[axis].reference,
      why: CANON[axis].why,
      owner: axisSummary[axis].verdict === 'DIV' ? ownerFor(axis) : 'aceptación/no aplica'
    }
  ])
)

const generatedAt = new Date().toISOString()

const matrixJson = {
  session: SESSION,
  revision: manifest.revision ?? staticArtifact?.revision ?? null,
  base: BASE,
  generatedAt,
  routes: matrixRoutes.map(row => {
    const source = routes.find(candidate => candidate.route === row.route)

    return {
      route: row.route,
      label: row.label,
      role: row.role,
      page: source?.static?.page ?? null,
      renderFile: source?.render?.file ?? null,
      shots: source?.render?.shots ?? [],
      axes: Object.fromEntries(
        AXES.map(axis => [
          axis,
          {
            observed: row.axes[axis].observed,
            verdict: row.axes[axis].verdict,
            evidence: row.axes[axis].evidence
          }
        ])
      )
    }
  }),
  axisSummary,
  canon
}

// --- markdown ---------------------------------------------------------------

const markdownLines = [
  '# U11 — Matriz de consistencia transversal (36 rutas × 12 ejes)',
  '',
  `- **Revisión**: \`${matrixJson.revision ?? 'desconocida'}\``,
  `- **Base**: ${BASE}`,
  `- **Fecha**: ${generatedAt}`,
  `- **Generado por**: \`tests/e2e/audit/u11/report.js\` sobre \`manifest.json\`, \`static.json\`, \`render/*.json\` y \`primitives/*.json\``,
  `- **Alcance del gate**: \`AUDIT_SCOPE=${SCOPE}\` (${expected.length} superficie(s) esperada(s))`,
  `- **Códigos**: \`OK\` canon · \`DIV\` divergencia · \`N/A\` no aplica · \`ACE\` aceptación declarada · \`NO PROBADO\` sin fila de render`,
  '',
  '## Matriz',
  '',
  mdTable(
    ['Ruta', 'Rol', ...AXES.map(axis => AXIS_LABELS[axis])],
    matrixRoutes.map(row => [
      `\`${row.route}\``,
      row.role,
      ...AXES.map(axis => row.axes[axis].verdict)
    ])
  ),
  '',
  '## Reglas de veredicto por eje',
  '',
  ...RULES.map(rule => `- ${rule}`),
  '',
  '## Veredicto por eje',
  ''
]

for (const [index, axis] of AXES.entries()) {
  const summary = axisSummary[axis]
  const divergent = matrixRoutes.filter(row => row.axes[axis].verdict === 'DIV')
  const accepted = matrixRoutes.filter(row => row.axes[axis].verdict === 'ACE')
  const measured = matrixRoutes.filter(
    row => row.axes[axis].verdict !== 'NO PROBADO' && row.axes[axis].verdict !== 'N/A'
  )

  markdownLines.push(
    `### ${index + 1}. ${AXIS_LABELS[axis]} (\`${axis}\`)`,
    '',
    `**Pregunta**: ${QUESTIONS[axis]}`,
    '',
    `**Canon propuesto**: \`${CANON[axis].primitive}\` — módulo de referencia \`${CANON[axis].reference}\`.`,
    '',
    `**Por qué**: ${CANON[axis].why}`,
    '',
    `**Rutas divergentes**: ${
      divergent.length
        ? divergent
            .map(row => `\`${row.route}\` (${clip(row.axes[axis].observed, 140)})`)
            .join(' · ')
        : 'ninguna'
    }`,
    ''
  )

  if (accepted.length) {
    markdownLines.push(
      `**Aceptaciones declaradas**: ${accepted
        .map(row => `\`${row.route}\` (${clip(row.axes[axis].evidence, 160)})`)
        .join(' · ')}`,
      ''
    )
  }

  markdownLines.push(
    `**Medido**: ${measured.length}/${matrixRoutes.length} ruta(s); OK ${summary.ok}, DIV ${summary.div}, ACE ${summary.ace}, N/A ${summary.na}, NO PROBADO ${summary.noProbado}.`,
    '',
    `**Veredicto del eje**: ${summary.verdict}`,
    '',
    `**Dueño**: ${
      summary.verdict === 'DIV'
        ? `${ownerFor(axis)} (el issue por clase agrupa los casos de este eje; la sesión no cierra sus propios issues)`
        : 'aceptación/no aplica (sin divergencia pendiente)'
    }`,
    ''
  )
}

const evidenceRows = matrixRoutes.map(row => {
  const source = routes.find(candidate => candidate.route === row.route)
  const shots = source?.render?.shots ?? []

  return [
    `\`${row.route}\``,
    source?.static?.page ?? '—',
    source?.render?.file ?? '—',
    shots.length ? shots.map(shot => `\`${shot}\``).join(' · ') : '—'
  ]
})

markdownLines.push(
  '## Evidencia por ruta',
  '',
  mdTable(['Ruta', 'Módulo', 'Fila de render', 'Capturas'], evidenceRows),
  ''
)

markdownLines.push(
  '## Canon propuesto',
  '',
  mdTable(
    ['Eje', 'Primitiva/helper que gana', 'Módulo de referencia', 'Por qué'],
    AXES.map(axis => [
      AXIS_LABELS[axis],
      CANON[axis].primitive,
      CANON[axis].reference,
      CANON[axis].why
    ])
  ),
  ''
)

const markdown = `${markdownLines.join('\n')}\n`
const matrixMarkdown = `${markdown}`

writeJson('consistency-matrix.json', matrixJson)
writeJson('findings.raw.json', {
  session: SESSION,
  revision: matrixJson.revision,
  generatedAt,
  findings: findingsRaw
})
writeJson('gate.json', gate)
writeText(evidencePath('consistency-matrix.md'), matrixMarkdown)

if (MATRIX_COPY) writeText(MATRIX_COPY, matrixMarkdown)

process.stdout.write(
  `${SESSION} report: ${matrixRoutes.length} routes, ${findingsRaw.length} finding(s), gate ${gate.ok ? 'OK' : 'FAIL'} (${gate.reasons.length} reason(s))\n`
)
