// Plan #13 (issue #374, U11) — layer 1: the twelve consistency axes read off the
// page source, without a browser.
//
// Every field is a presence flag or a count derived from file text. The import
// graph is the only traversal: it starts at `surface.page`, follows relative and
// `@/` specifiers inside `resources/js`, and stops at the three shared trees
// (`components/ui`, `components/layout`, `composables`). Those are registered as
// imports but never expanded, because the shell and the primitives would make
// every page inherit every axis.
//
// `node tests/e2e/audit/u11/static.js --out=<file>` writes the same object the
// exporter returns.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve, sep } from 'node:path'
import { pathToFileURL } from 'node:url'

export const SESSION = 'U11'

export const EVIDENCE = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U11'

const SOURCE_ROOT = 'resources/js'
const RESOLVABLE = ['.vue', '.js']

// The shared trees the shell and the primitives live in: imported, never
// expanded, or every surface would inherit the whole design system.
const EXCLUDED_PREFIXES = [
  `${SOURCE_ROOT}/components/ui/`,
  `${SOURCE_ROOT}/components/layout/`,
  `${SOURCE_ROOT}/composables/`
]

// Axis keys, in the matrix order the session declares.
export const AXES = Object.freeze([
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

const toPosix = path => path.split(sep).join('/')

// Same normalisation as the harness `slugFor`, so static rows and browser rows
// name the same evidence file.
const slugFor = route =>
  route
    .replace(/\([^)]*\)/g, ' ')
    .replace(/:/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'root'

const unique = values => [...new Set(values.filter(value => value !== null && value !== ''))]

const count = (list, predicate) => list.filter(predicate).length

const specifierBase = source => {
  const cleaned = String(source ?? '')
    .replace(/[?#].*$/, '')
    .split('/')
    .filter(Boolean)
    .pop()

  return (cleaned ?? '').replace(/\.(vue|js)$/, '')
}

const parseImports = content => {
  const entries = []
  const defaultNames = new Map()

  for (const match of content.matchAll(/import\s+([A-Za-z_$][\w$]*)\s*from\s*['"]([^'"]+)['"]/g)) {
    defaultNames.set(match[2], match[1])
  }

  const sources = [
    ...content.matchAll(/\bfrom\s+['"]([^'"]+)['"]/g),
    ...content.matchAll(/\bimport\s*\(\s*['"]([^'"]+)['"]\s*\)/g)
  ].map(match => match[1])

  for (const source of unique(sources)) {
    entries.push({ source, name: defaultNames.get(source) ?? null })
  }

  return entries
}

const isExcluded = path => EXCLUDED_PREFIXES.some(prefix => path.startsWith(prefix))

const resolveSpecifier = (fromFile, source) => {
  let base = null

  if (source.startsWith('@/')) base = `${SOURCE_ROOT}/${source.slice(2)}`
  else if (source.startsWith('.')) base = toPosix(join(dirname(fromFile), source))
  else return null

  if (!base.startsWith(`${SOURCE_ROOT}/`)) return null

  const candidates = [base]

  if (!RESOLVABLE.some(extension => base.endsWith(extension))) {
    for (const extension of RESOLVABLE) candidates.push(`${base}${extension}`)
    for (const extension of RESOLVABLE) candidates.push(`${base}/index${extension}`)
  }

  for (const candidate of candidates) {
    if (!candidate.startsWith(`${SOURCE_ROOT}/`)) continue
    if (existsSync(resolve(process.cwd(), candidate))) return candidate
  }

  return null
}

const scan = page => {
  const files = []
  const imports = []
  const queue = [toPosix(page)]
  const visited = new Set()

  while (queue.length) {
    const file = queue.shift()
    if (visited.has(file)) continue

    visited.add(file)

    const absolute = resolve(process.cwd(), file)
    if (!existsSync(absolute)) continue

    const content = readFileSync(absolute, 'utf8')
    files.push({ path: file, content })

    for (const entry of parseImports(content)) {
      const resolved = resolveSpecifier(file, entry.source)
      const record = {
        from: file,
        source: entry.source,
        name: entry.name,
        specifier: specifierBase(entry.source),
        resolved,
        expanded: Boolean(resolved) && !isExcluded(resolved)
      }

      imports.push(record)

      if (record.expanded && !visited.has(resolved)) queue.push(resolved)
    }
  }

  return { files, imports }
}

const buildAxes = (surface, files, imports) => {
  const text = files.map(file => file.content).join('\n')
  const specifiers = unique(imports.map(imported => imported.specifier))
  const hasImport = name => specifiers.includes(name)
  const actions = surface.actions ?? []
  const dialogs = surface.dialogs ?? []

  const labels = unique(
    actions
      .filter(action => action.labelSource !== 'binding')
      .map(action => String(action.label ?? '').trim())
      .filter(label => label && !/[./]/.test(label))
  )

  const grouped = { create: [], submit: [], cancel: [], delete: [] }

  for (const action of actions) {
    if (!(action.kind in grouped)) continue
    if (action.labelSource === 'binding') continue

    const label = String(action.label ?? '').trim()
    if (!label || /[./]/.test(label)) continue

    grouped[action.kind].push(label)
  }

  const filterActions = actions.filter(action => action.kind === 'filter')
  const resetLabels = unique(
    filterActions
      .map(action => action.label)
      .filter(label => /limpiar|restablecer|borrar/i.test(label))
  )

  return {
    dialog: {
      imports: ['Modal', 'Sheet', 'ConfirmDialog'].filter(hasImport),
      ownOverlay: /role="dialog"|fixed inset-0/.test(text),
      manifestPrimitives: unique(dialogs.map(dialog => dialog.primitive ?? null))
    },
    list: {
      dataTable: hasImport('DataTable') || /<DataTable\b/.test(text),
      rawTables: (text.match(/<table\b/g) ?? []).length,
      // The list axis asks about the page's own list, not about a `<table>`
      // inside a detail modal (`TreatmentPlanDetail` lays out the versioned plan
      // that way and the list itself is a kanban of cards). Only the page-level
      // files decide the verdict.
      pageTables: files
        .filter(file => /Page\.vue$/.test(file.path) || /List\.vue$/.test(file.path))
        .reduce((total, file) => total + (file.content.match(/<table\b/g) ?? []).length, 0),
      moduleFiles: files
        .filter(file => file.path.startsWith(`${SOURCE_ROOT}/modules/`))
        .map(file => file.path)
    },
    states: {
      skeleton: hasImport('Skeleton') || /<Skeleton\b|<UiSkeleton\b/.test(text),
      spinner: hasImport('LoadingSpinner') || /<LoadingSpinner\b|<UiLoadingSpinner\b/.test(text),
      empty:
        hasImport('EmptyState') ||
        hasImport('UiSectionEmpty') ||
        /<EmptyState\b|<UiEmptyState\b|<UiSectionEmpty\b/.test(text),
      retry: /Reintentar/.test(text)
    },
    feedback: {
      toast: hasImport('useToast') || /useToast\(/.test(text),
      inline: /:error\b|formErrors|errors\[/.test(text)
    },
    filters: {
      filterBar: hasImport('FilterBar') || /<FilterBar\b|<UiFilterBar\b/.test(text),
      resetLabels
    },
    badges: {
      statusPill: hasImport('StatusPill') || /<StatusPill\b|<UiStatusPill\b/.test(text),
      statusBadge: hasImport('StatusBadge') || /<StatusBadge\b|<UiStatusBadge\b/.test(text),
      ownBadge: /class="[^"]*badge/i.test(text)
    },
    format: {
      currencyHelper: /useFormatters|formatCurrency|formatPENLabel/.test(text),
      intl: /Intl\./.test(text),
      locale: /toLocaleDateString|toLocaleTimeString|toLocaleString/.test(text),
      toFixed: /\.toFixed\(/.test(text),
      // `resources/js/utils/formatters.js` is the pre-canonical module: it declares
      // its own `Intl.NumberFormat('es-PE', { currency: 'PEN' })` and answers
      // `S/ NaN` for a non-numeric amount where `useFormatters` answers `S/ 0.00`.
      legacyModule: files.some(file => file.path === `${SOURCE_ROOT}/utils/formatters.js`)
    },
    buttons: { labels, ...grouped }
  }
}

const summarise = rows => {
  const axes = input => rows.map(row => row.axes[input])

  return {
    session: SESSION,
    surfaces: rows.length,
    scannedFiles: rows.reduce((total, row) => total + row.files.length, 0),
    imports: rows.reduce((total, row) => total + row.imports.length, 0),
    excludedImports: rows.reduce(
      (total, row) => total + count(row.imports, imported => !imported.expanded),
      0
    ),
    unresolvedImports: rows.reduce(
      (total, row) => total + count(row.imports, imported => !imported.resolved),
      0
    ),
    byAxis: {
      dialog: {
        modalImported: count(axes('dialog'), axis => axis.imports.includes('Modal')),
        sheetImported: count(axes('dialog'), axis => axis.imports.includes('Sheet')),
        ownOverlay: count(axes('dialog'), axis => axis.ownOverlay),
        declaredDialogs: rows.reduce(
          (total, row) => total + row.axes.dialog.manifestPrimitives.length,
          0
        )
      },
      list: {
        dataTable: count(axes('list'), axis => axis.dataTable),
        rawTableFiles: count(axes('list'), axis => axis.rawTables > 0),
        rawTables: rows.reduce((total, row) => total + row.axes.list.rawTables, 0),
        pageTables: rows.reduce((total, row) => total + row.axes.list.pageTables, 0)
      },
      buttons: {
        labels: rows.reduce((total, row) => total + row.axes.buttons.labels.length, 0),
        create: rows.reduce((total, row) => total + row.axes.buttons.create.length, 0),
        submit: rows.reduce((total, row) => total + row.axes.buttons.submit.length, 0),
        cancel: rows.reduce((total, row) => total + row.axes.buttons.cancel.length, 0),
        delete: rows.reduce((total, row) => total + row.axes.buttons.delete.length, 0)
      },
      states: {
        skeleton: count(axes('states'), axis => axis.skeleton),
        spinner: count(axes('states'), axis => axis.spinner),
        empty: count(axes('states'), axis => axis.empty),
        retry: count(axes('states'), axis => axis.retry)
      },
      feedback: {
        toast: count(axes('feedback'), axis => axis.toast),
        inline: count(axes('feedback'), axis => axis.inline)
      },
      filters: {
        filterBar: count(axes('filters'), axis => axis.filterBar),
        withReset: count(axes('filters'), axis => axis.resetLabels.length > 0)
      },
      badges: {
        statusPill: count(axes('badges'), axis => axis.statusPill),
        statusBadge: count(axes('badges'), axis => axis.statusBadge),
        ownBadge: count(axes('badges'), axis => axis.ownBadge)
      },
      format: {
        currencyHelper: count(axes('format'), axis => axis.currencyHelper),
        intl: count(axes('format'), axis => axis.intl),
        locale: count(axes('format'), axis => axis.locale),
        toFixed: count(axes('format'), axis => axis.toFixed)
      }
    }
  }
}

/**
 * Builds the static axes of every surface of the layer-1 manifest.
 *
 * @param {{revision?: string, surfaces?: Array<object>}} manifest
 * @returns {{ session: string, revision: string|null, rows: Array<object>, summary: object }}
 */
export const buildStaticAxes = manifest => {
  const rows = (manifest?.surfaces ?? []).map(surface => {
    const { files, imports } = scan(surface.page)

    return {
      route: surface.route,
      slug: slugFor(surface.route),
      page: surface.page,
      files: files.map(file => file.path),
      imports,
      axes: buildAxes(surface, files, imports)
    }
  })

  return {
    session: SESSION,
    revision: manifest?.revision ?? null,
    rows,
    summary: summarise(rows)
  }
}

// --- CLI --------------------------------------------------------------------

const parseOut = argv => {
  const flag = argv.find(argument => argument.startsWith('--out='))
  return flag ? flag.slice('--out='.length) : null
}

const loadManifest = outDir => {
  const configured = process.env.AUDIT_MANIFEST

  if (configured) {
    const absolute = resolve(process.cwd(), configured)

    if (existsSync(absolute)) return JSON.parse(readFileSync(absolute, 'utf8'))

    return JSON.parse(configured)
  }

  const written = resolve(process.cwd(), outDir, 'manifest.json')

  if (existsSync(written)) return JSON.parse(readFileSync(written, 'utf8'))

  throw new Error(`no manifest at ${written}; pass --out or AUDIT_MANIFEST`)
}

const main = () => {
  const out = parseOut(process.argv.slice(2))
  const payload = buildStaticAxes(loadManifest(process.env.AUDIT_OUT || EVIDENCE))

  if (out) {
    const absolute = resolve(process.cwd(), out)
    mkdirSync(dirname(absolute), { recursive: true })
    writeFileSync(absolute, `${JSON.stringify(payload, null, 2)}\n`)
  }

  const { summary } = payload
  process.stdout.write(
    `${SESSION} static: ${summary.surfaces} surfaces, ${summary.scannedFiles} files, ` +
      `${summary.imports} imports (${summary.excludedImports} not expanded)\n`
  )
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) main()
