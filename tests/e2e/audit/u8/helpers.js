// Plan #13 (issue #371, U8) — measurement helpers shared by the catalog rows.
//
// Plain readers and small drivers: no `test()` is registered here, so
// `--grep @U8` selects exactly the rows the session modules declare. Every
// reader returns what it observed; a reader that cannot observe a node returns
// `null` or an explicit reason so the caller records `NO PROBADO` instead of
// throwing.
//
// Evidence this session writes lives in `U8_EVIDENCE_DIR`; `.atl/` is
// gitignored and every JSON carries `generatedAt` plus the instrument revision.
// The catalogue CSV is parsed from the blob the browser builds, never from a
// file written to disk.

import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { isAbsolute, resolve } from 'node:path'

import { BASE_URL, capture, settleLayout, writeEvidence } from '../harness.js'

export const U8_EVIDENCE_DIR = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U8'

export const CATALOG_ROUTE = '/procedure-catalog'
export const STATS_ROUTE = '/procedure-stats'
export const MY_PROCEDURES_ROUTE = '/my-procedures'
export const RECEPTION_ROUTE = '/reception-procedures'
export const QUOTATIONS_ROUTE = '/quotations'

export const DESKTOP = { width: 1440, height: 900 }
export const MOBILE = { width: 390, height: 844 }

export const THRESHOLD = 120
export const API_TIMEOUT = 30000
export const LOAD_TIMEOUT = 25000
export const ACTION_TIMEOUT = 20000
export const TOAST_TIMEOUT = 10000
export const DIALOG_TIMEOUT = 6000

export const DIALOG_SELECTOR = '[role="dialog"]:visible, [role="alertdialog"]:visible'

// The one seeded code the issue names for the picker defect (#416). It exists in
// the seeded catalogue (id 5) and lands outside the picker's first page.
export const WIDE_CATALOG_CODE = 'REST-COMP-1S'

export const audit = (filePath, payload) =>
  writeEvidence(filePath, payload, { dir: U8_EVIDENCE_DIR })
export const shot = (page, filePath) => capture(page, filePath, { dir: U8_EVIDENCE_DIR })

export const evidencePath = relPath =>
  isAbsolute(relPath) ? relPath : resolve(process.cwd(), U8_EVIDENCE_DIR, relPath)

export const readEvidenceFile = relPath => readFileSync(evidencePath(relPath), 'utf8')

export const sleep = ms => new Promise(resolve => setTimeout(resolve, ms))

/** Local calendar day; the API compares dates locally, not in UTC. */
export const localDay = (date = new Date()) => {
  const pad = value => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export const addDaysLocal = (day, days) => {
  const [year, month, date] = day.split('-').map(Number)
  return localDay(new Date(year, month - 1, date + days))
}

const revision = () => {
  try {
    return execFileSync('git', ['rev-parse', 'HEAD'], {
      cwd: process.cwd(),
      encoding: 'utf8'
    }).trim()
  } catch {
    return null
  }
}

const REVISION = revision()

/** Metadata every evidence file carries. */
export const stamp = () => ({
  session: 'U8',
  generatedAt: new Date().toISOString(),
  revision: REVISION,
  base: BASE_URL
})

export const messageOf = body => {
  if (typeof body?.message === 'string') return body.message
  if (typeof body?.error?.message === 'string') return body.error.message
  if (typeof body?.error === 'string') return body.error
  if (typeof body?.meta?.message === 'string') return body.meta.message
  return null
}

/**
 * One authenticated API call.
 *
 * A network or parse failure becomes a row with `status: null`, never an
 * exception: an unreachable probe is evidence.
 */
export const call = async (request, session, method, path, data, extraHeaders = {}) => {
  const options = {
    method,
    headers: {
      Authorization: `Bearer ${session.token}`,
      Accept: 'application/json',
      ...(data !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...extraHeaders
    },
    timeout: API_TIMEOUT
  }

  if (data !== undefined) options.data = data

  try {
    const response = await request.fetch(`${BASE_URL}${path}`, options)
    const contentType = response.headers()['content-type'] ?? ''
    const isJson = contentType.includes('json')
    const body = isJson ? await response.json().catch(() => null) : null
    const text = isJson ? null : await response.text().catch(() => '')

    return {
      method,
      path,
      status: response.status(),
      ok: response.ok(),
      contentType,
      message: response.ok() ? null : messageOf(body),
      errors: body?.errors ?? null,
      body,
      bytes: text === null ? null : text.length
    }
  } catch (thrown) {
    return {
      method,
      path,
      status: null,
      ok: false,
      contentType: null,
      message: String(thrown?.message ?? thrown),
      errors: null,
      body: null,
      bytes: null
    }
  }
}

/** Multipart through Playwright's own file payload; no bytes touch the disk. */
export const callUpload = async (
  request,
  session,
  path,
  { fields = {}, file, fileField = 'file' }
) => {
  const multipart = {}

  for (const [key, value] of Object.entries(fields)) {
    multipart[key] = String(value)
  }

  if (file) {
    multipart[fileField] = { name: file.name, mimeType: file.mimeType, buffer: file.buffer }
  }

  try {
    const response = await request.fetch(`${BASE_URL}${path}`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' },
      multipart,
      timeout: API_TIMEOUT
    })
    const contentType = response.headers()['content-type'] ?? ''
    const body = contentType.includes('json') ? await response.json().catch(() => null) : null

    return {
      method: 'POST',
      path,
      status: response.status(),
      ok: response.ok(),
      contentType,
      message: response.ok() ? null : messageOf(body),
      errors: body?.errors ?? null,
      body,
      bytes: null
    }
  } catch (thrown) {
    return {
      method: 'POST',
      path,
      status: null,
      ok: false,
      contentType: null,
      message: String(thrown?.message ?? thrown),
      errors: null,
      body: null,
      bytes: null
    }
  }
}

/**
 * Signs in a username that is not in the harness role map.
 *
 * The session owns one branch-scoped administrator (`audit_u8_sede`) because
 * `ProcedurePriceResolver::currentBranchId()` reads the actor's sede: with the
 * seeded users (all `branch_id` NULL) the branch tariff can never be observed.
 */
export const loginCredential = async (request, username, password = 'password123') => {
  const response = await request.post(`${BASE_URL}/api/auth/login`, {
    data: { username, password }
  })

  if (!response.ok()) {
    const body = await response.text().catch(() => '')
    throw new Error(`login failed for ${username}: HTTP ${response.status()} ${body}`.trim())
  }

  const body = await response.json()
  const token = body?.data?.token
  const user = body?.data?.user

  if (!token || !user) {
    throw new Error(`login for ${username} returned no token/user: ${JSON.stringify(body)}`)
  }

  return { role: user.role, token, user, username }
}

// --- UI hooks ---------------------------------------------------------------
//
// One table of selectors so the rows and the walks cannot drift apart. Every
// entry is a hook the page renders today; a hook that disappears makes its row
// `NO PROBADO` with the selector that was missing.

export const UI = Object.freeze({
  catalog: {
    hero: '[data-procedure-catalog-hero]',
    title: '[data-hero-title]',
    caption: '[data-hero-caption]',
    create: '[data-hero-action]',
    import: '[data-hero-import]',
    rows: '[data-procedure-row]',
    search: '[data-procedure-search] input',
    specialty: '[data-specialty-filter]',
    status: '[data-status-filter]',
    filterCount: '[data-filter-count]',
    error: '[data-procedures-error]',
    retry: '[data-retry-procedures]',
    kpi: key => `[data-kpi-value="${key}"]`,
    rowAction: action => `[data-row-action="${action}"]`
  },
  detail: {
    hero: '[data-detail-hero]',
    title: '[data-detail-title]',
    caption: '[data-detail-caption]',
    crumb: '[data-detail-crumb]',
    status: '[data-detail-status]',
    edit: '[data-detail-action="edit"]',
    field: key => `[data-detail-field="${key}"]`,
    group: key => `[data-detail-group="${key}"]`,
    externalCodes: '[data-external-code-row]',
    externalCode: '[data-external-code-code]',
    externalEmpty: '[data-external-codes-empty]',
    auditRows: '[data-audit-row]',
    versionRows: '[data-version-row]',
    tariff: '[data-branch-tariff]',
    tariffTabs: '[data-tariff-table]',
    // `UiDataTable` marks rows and cells with classes, not attributes.
    tariffRows: '[data-tariff-table] tbody tr',
    tariffAdd: '[data-tariff-add]',
    tariffEmpty: '[data-tariff-empty]',
    tariffEmptyAdd: '[data-tariff-empty-add]',
    tariffPrice: '[data-tariff-price]',
    tariffListPrice: '[data-tariff-list-price]',
    tariffValidity: '[data-tariff-validity]',
    tariffStatus: '[data-tariff-status]',
    tariffEdit: '[data-tariff-edit]',
    tariffRemove: '[data-tariff-remove]',
    tariffSubmit: '[data-tariff-submit]',
    tariffCancel: '[data-tariff-cancel]',
    tariffFormError: '[data-tariff-form-error]',
    tariffBranch: '[data-tariff-branch]'
  },
  stats: {
    hero: '[data-procedure-stats-hero]',
    title: '[data-hero-title]',
    caption: '[data-hero-caption]',
    roleDisclosure: '[data-role-disclosure]',
    back: '[data-hero-back]',
    exportCsv: '[data-export-csv]',
    refresh: '[data-hero-refresh]',
    periodApplied: '[data-period-applied]',
    preset: id => `[data-preset="${id}"]`,
    from: '[data-filters-from] input',
    to: '[data-filters-to] input',
    limit: '[data-filters-limit]',
    apply: '[data-filters-apply]',
    error: '[data-state="error"]',
    retry: '[data-retry]',
    kpi: key => `[data-kpi-value="${key}"]`,
    top: '[data-top-procedures]',
    topTable: '[data-top-procedures-table]',
    topRows: `${'[data-top-procedures-table]'} tbody tr`,
    specialty: '[data-specialty-distribution]',
    specialtyRows: '[data-specialty-row]',
    specialtyBar: '[data-specialty-bar]',
    specialtyShare: '[data-specialty-share]',
    charts: '[data-charts]',
    chart: key => `[data-chart="${key}"]`,
    empty: '[data-period-empty]',
    widen: '[data-widen-period]'
  },
  favorites: {
    hero: '[data-my-procedures-hero]',
    title: '[data-hero-title]',
    count: '[data-favorites-count]',
    error: '[data-my-procedures-error]',
    favorites: '[data-favorite-row]',
    favoritesList: '[data-favorites-list]',
    suggestions: '[data-suggestion-row]',
    exploreRows: '[data-explorer-row]',
    exploreSearch: '[data-explore-search] input',
    exploreError: '[data-explorer-error]',
    moveUp: 'button[aria-label="Subir favorito"]',
    moveDown: 'button[aria-label="Bajar favorito"]',
    remove: 'button[aria-label="Quitar de favoritos"]'
  },
  reception: {
    hero: '[data-reception-hero]',
    title: '[data-hero-title]',
    cards: '[data-procedure-card]',
    search: '[data-reception-search] input',
    error: '[data-reception-error]',
    retry: '[data-retry-reception]',
    kpi: key => `[data-kpi-card="${key}"]`
  },
  quotations: {
    hero: '[data-quotations-hero]',
    create: '[data-hero-action="create"]',
    form: '[data-quotation-form="create"]',
    items: '[data-form-section="items"]'
  }
})

/**
 * Console errors with a declared, environmental cause.
 *
 * Reverb is not part of the audit environment: every surface boots Echo, the
 * socket retry fails and the browser logs it. It is the same fact U0 recorded,
 * it is not caused by the catalogue, and it is reported (never hidden): the row
 * keeps the message and the aggregate counts it separately.
 */
export const EXPECTED_CONSOLE = [
  {
    pattern: /WebSocket connection to .*(8081|\/app\/).*(failed|ERR_CONNECTION_REFUSED)/i,
    reason: 'Reverb is not running in the audit environment (U0 shell fact)'
  },
  {
    pattern: /Failed to load resource: net::ERR_CONNECTION_REFUSED/i,
    reason: 'the WebSocket handshake of the same Reverb socket'
  }
]

/**
 * Console errors, classified.
 *
 * A negative row provokes its own 4xx: the browser logs every failed response
 * and that log is the expected outcome of rendering a surface to a role without
 * its permission (layer 2's declared allowlist).
 */
export const classifyConsole = (errors, expectation = 'admin', declared = []) => {
  const expected = []
  const unexpected = []

  for (const message of errors ?? []) {
    const environment = EXPECTED_CONSOLE.find(entry => entry.pattern.test(message))
    const instrument = declared.find(entry => entry.pattern.test(message ?? ''))
    const denial =
      expectation === 'negative' && /Failed to load resource: .*40[13]/.test(message ?? '')

    if (environment) expected.push({ message, reason: environment.reason })
    else if (denial) expected.push({ message, reason: 'the negative row provokes its own 403/401' })
    else if (instrument) expected.push({ message, reason: instrument.reason })
    else unexpected.push({ message })
  }

  return { expected, unexpected }
}

// --- settling ---------------------------------------------------------------

export const settle = async (page, selector, timeout = LOAD_TIMEOUT) => {
  await page
    .locator(selector)
    .first()
    .waitFor({ state: 'visible', timeout })
    .catch(() => {})
  await settleLayout(page)
}

/** `/procedure-catalog`: hero, KPIs and either rows, empty state or error. */
export const settleCatalog = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, UI.catalog.hero, timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-procedure-row]') ||
          document.querySelector('[data-procedures-error]') ||
          document.querySelector(
            '[data-procedure-catalog-hero] ~ * [class*="empty"], [data-procedure-catalog-hero] + * + section button'
          )
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await page
    .waitForFunction(
      () => !/Cargando procedimientos/.test(document.querySelector('main')?.innerText ?? ''),
      undefined,
      { timeout: 8000, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

/** `/procedure-catalog/:id`: hero title and the tab strip. */
export const settleCatalogDetail = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, UI.detail.hero, timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-detail-title]') ||
          document.querySelector('[data-detail-error]') ||
          document.querySelector('[role="tab"]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

/** `/procedure-stats`: hero, filters and one of the three result states. */
export const settleStats = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, UI.stats.hero, timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-top-procedures-table]') ||
          document.querySelector('[data-period-empty]') ||
          document.querySelector('[data-state="error"]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

/** `/my-procedures`: hero plus the favorites block. */
export const settleFavorites = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, UI.favorites.hero, timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-my-procedures-error]') ||
          document.querySelector('[data-favorites-count]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await page
    .waitForFunction(
      () => !/Cargando favoritos/.test(document.querySelector('main')?.innerText ?? ''),
      undefined,
      { timeout: 8000, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

/** `/reception-procedures`: hero and either cards, empty state or error. */
export const settleReception = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, UI.reception.hero, timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-procedure-card]') ||
          document.querySelector('[data-reception-error]') ||
          /Sin resultados/.test(document.querySelector('main')?.innerText ?? '')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await page
    .waitForFunction(
      () => !/Cargando procedimientos/.test(document.querySelector('main')?.innerText ?? ''),
      undefined,
      { timeout: 8000, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

/** `/quotations` with the create dialog open: the picker's read surface. */
export const settleQuotations = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, UI.quotations.hero, timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-quotations-table]') ||
          document.querySelector('[data-quotations-empty]') ||
          document.querySelector('[data-quotations-error]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

// --- DOM readers ------------------------------------------------------------

const READ_HELPER = `
  const text = node => (node?.innerText ?? '').trim().replace(/\\s+/g, ' ')
  const visible = node => {
    const box = node?.getBoundingClientRect()
    return Boolean(box && box.width > 0 && box.height > 0)
  }
  const attr = (node, name) => (node?.getAttribute ? node.getAttribute(name) : null)
  const has = node => Boolean(node)
  const state = selector => {
    const node = document.querySelector(selector)
    return node ? { present: true, text: text(node), visible: visible(node) } : null
  }
  const number = value => {
    const cleaned = String(value ?? '').replace(/[^0-9.,-]/g, '').replace(/,/g, '')
    const parsed = Number(cleaned)
    return Number.isFinite(parsed) ? parsed : null
  }
`

const read = (page, fn) => page.evaluate(`(() => {${READ_HELPER}; ${fn}})()`)

/** Shell facts: title ownership, the hero title and the sidebar links. */
export const readShell = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 2 && box.height > 2)
    }
    const topbar = document.querySelector('[data-app-chrome="topbar"]')

    return {
      h1: {
        count: document.querySelectorAll('h1').length,
        texts: Array.from(document.querySelectorAll('h1')).map(text),
        visibleTexts: Array.from(document.querySelectorAll('h1')).filter(visible).map(text)
      },
      topbar: {
        owner: topbar?.getAttribute('data-topbar-title-owner') ?? null,
        context: text(document.querySelector('[data-topbar-context]')) || null
      },
      hero: {
        title: text(document.querySelector('[data-hero-title]')) || null,
        detailTitle: text(document.querySelector('[data-detail-title]')) || null,
        eyebrow: text(document.querySelector('[data-hero-eyebrow]')) || null,
        caption:
          text(document.querySelector('[data-hero-caption]')) ||
          text(document.querySelector('[data-detail-caption]')) ||
          null
      },
      sidebar: Array.from(document.querySelectorAll('#primary-sidebar a[href]')).map(link => ({
        href: link.getAttribute('href'),
        label: text(link)
      }))
    }
  })

/** The catalog index: KPIs, rows, filters and pagination. */
export const readCatalog = page =>
  read(
    page,
    `
    const kpi = key => text(document.querySelector('[data-kpi-value="' + key + '"]'))
    const rows = Array.from(document.querySelectorAll('[data-procedure-row]')).map(row => {
      const cells = Array.from(row.querySelectorAll('td')).map(text)
      return {
        code: cells[0] ?? null,
        name: text(row.querySelector('[data-row-action="detail"]')),
        specialty: cells[2] ?? null,
        duration: cells[3] ?? null,
        cost: cells[4] ?? null,
        status: cells[5] ?? null,
        text: text(row),
        actions: Array.from(row.querySelectorAll('[data-row-action]')).map(button => ({
          action: attr(button, 'data-row-action'),
          label: attr(button, 'aria-label') || text(button),
          visible: visible(button)
        }))
      }
    })
    const pages = Array.from(
      document.querySelectorAll('main nav button, main nav a, main [class*="pagination"] button')
    )
      .map(node => text(node))
      .filter(Boolean)
    const scrollable = Array.from(document.querySelectorAll('main .overflow-x-auto')).map(node => ({
      clientWidth: node.clientWidth,
      scrollWidth: node.scrollWidth,
      affordance: Boolean(node.querySelector('[data-table-scroll-affordance]')),
      hook: attr(node, 'data-table-scroll')
    }))
    return {
      h1: Array.from(document.querySelectorAll('h1')).map(text),
      title: text(document.querySelector('[data-hero-title]')),
      caption: text(document.querySelector('[data-hero-caption]')),
      kpis: {
        total: kpi('total'),
        active: kpi('active'),
        inactive: kpi('inactive'),
        avgDuration: kpi('avg-duration'),
        avgCost: kpi('avg-cost')
      },
      search: {
        present: has(document.querySelector('[data-procedure-search] input')),
        value: document.querySelector('[data-procedure-search] input')?.value ?? null,
        placeholder:
          document.querySelector('[data-procedure-search] input')?.getAttribute('placeholder') ??
          null
      },
      specialty: text(document.querySelector('[data-specialty-filter]')),
      status: text(document.querySelector('[data-status-filter]')),
      filterCount: text(document.querySelector('[data-filter-count]')),
      rows,
      rowCount: rows.length,
      firstCode: rows[0]?.code ?? null,
      lastCode: rows[rows.length - 1]?.code ?? null,
      pagination: { present: pages.length > 0, labels: pages.slice(0, 12) },
      error: state('[data-procedures-error]'),
      retry: has(document.querySelector('[data-retry-procedures]')),
      empty: text(document.querySelector('main'))?.match(/Sin resultados|No hay procedimientos/)?.[0] ?? null,
      createVisible: Boolean(
        document.querySelector('[data-hero-action]') && visible(document.querySelector('[data-hero-action]'))
      ),
      importVisible: Boolean(
        document.querySelector('[data-hero-import]') && visible(document.querySelector('[data-hero-import]'))
      ),
      statsVisible: Boolean(
        Array.from(document.querySelectorAll('button')).find(button => text(button) === 'Estadísticas' && visible(button))
      ),
      rowActions: [...new Set(rows.flatMap(row => row.actions.map(action => action.action)))],
      tables: {
        native: document.querySelectorAll('main table.min-w-\\\\[880px\\\\]').length,
        dataTable: document.querySelectorAll('main [data-table-scroll]').length
      },
      scrollable
    }
  `
  )

/** The catalog detail: hero, the four tab labels and the Datos surface. */
export const readDetail = page =>
  read(
    page,
    `
    const tabs = Array.from(document.querySelectorAll('[role="tab"]')).map(tab => ({
      id: attr(tab, 'id'),
      label: (tab.innerText ?? '').trim(),
      selected: attr(tab, 'aria-selected') === 'true',
      visible: visible(tab)
    }))
    const field = key => text(document.querySelector('[data-detail-field="' + key + '"]'))
    return {
      title: text(document.querySelector('[data-detail-title]')),
      caption: text(document.querySelector('[data-detail-caption]')),
      status: text(document.querySelector('[data-detail-status]')),
      crumb: text(document.querySelector('[data-detail-crumb]')),
      tabs,
      activeTab: tabs.find(tab => tab.selected)?.label ?? null,
      fields: {
        code: field('code'),
        specialty: field('specialty'),
        cost: field('cost'),
        duration: field('duration'),
        siteType: field('site_type')
      },
      groups: Array.from(document.querySelectorAll('[data-detail-group]')).map(group =>
        attr(group, 'data-detail-group')
      ),
      externalCodes: Array.from(document.querySelectorAll('[data-external-code-row]')).map(row => ({
        system: text(row.querySelector('[class*="inline-flex"]')),
        code: text(row.querySelector('[data-external-code-code]')),
        text: text(row)
      })),
      externalEmpty: state('[data-external-codes-empty]'),
      editVisible: Boolean(
        document.querySelector('[data-detail-action="edit"]') &&
          visible(document.querySelector('[data-detail-action="edit"]'))
      ),
      error: state('[data-detail-error]'),
      audit: {
        rows: document.querySelectorAll('[data-audit-row]').length,
        empty: text(document.querySelector('main'))?.includes('No hay historial de auditoría') ?? null,
        errorText: (document.querySelector('main')?.innerText ?? '').includes(
          'No pudimos cargar el historial'
        )
      },
      versions: {
        rows: document.querySelectorAll('[data-version-row]').length,
        empty: text(document.querySelector('main'))?.includes('No hay versiones registradas') ?? null,
        errorText: (document.querySelector('main')?.innerText ?? '').includes(
          'No pudimos cargar el historial de versiones'
        )
      },
      materialRecipe: {
        rendered: /receta|insumo/i.test(document.querySelector('main')?.innerText ?? ''),
        groups: Array.from(document.querySelectorAll('[data-detail-group]')).map(group =>
          attr(group, 'data-detail-group')
        )
      }
    }
  `
  )

/** The branch tariff panel of the detail page. */
export const readTariff = page =>
  read(
    page,
    `
    const rows = Array.from(document.querySelectorAll('[data-tariff-table] tbody tr')).map(
      row => ({
        price: text(row.querySelector('[data-tariff-price]')),
        listPrice: text(row.querySelector('[data-tariff-list-price]')),
        validity: text(row.querySelector('[data-tariff-validity]')),
        status: text(row.querySelector('[data-tariff-status]')),
        text: text(row),
        actions: Array.from(row.querySelectorAll('button')).map(button => ({
          label: attr(button, 'aria-label') || text(button),
          visible: visible(button)
        }))
      })
    )
    return {
      present: has(document.querySelector('[data-branch-tariff]')),
      branch: text(document.querySelector('[data-tariff-branch]')),
      addDisabled: document.querySelector('[data-tariff-add]')?.disabled ?? null,
      loading: has(document.querySelector('[data-tariff-loading]')),
      error: state('[data-tariff-error]'),
      empty: state('[data-tariff-empty]'),
      noBranches: state('[data-tariff-no-branches]'),
      rows,
      rowCount: rows.length,
      table: has(document.querySelector('[data-tariff-table]'))
    }
  `
  )

/** The stats page: KPIs, top table, distribution, charts and period. */
export const readStats = page =>
  read(
    page,
    `
    const kpi = key => text(document.querySelector('[data-kpi-value="' + key + '"]'))
    const topRows = Array.from(
      document.querySelectorAll('[data-top-procedures-table] tbody tr')
    ).map(row => {
      const cells = Array.from(row.querySelectorAll('td')).map(text)
      return {
        rank: cells[0] ?? null,
        code: cells[1] ?? null,
        name: cells[2] ?? null,
        usage: cells[3] ?? null,
        quantity: cells[4] ?? null,
        revenue: cells[5] ?? null,
        text: text(row)
      }
    })
    const specialtyRows = Array.from(document.querySelectorAll('[data-specialty-row]')).map(row => {
      const badge = row.querySelector('[data-specialty-share]')
      const bar = row.querySelector('[data-specialty-bar]')
      const lines = Array.from(row.querySelectorAll('p')).map(text)
      return {
        name: lines[0] ?? null,
        usageLine: lines[1] ?? null,
        share: text(badge),
        barPercent: attr(bar, 'data-specialty-bar-percent'),
        barWidth: bar ? Math.round(bar.getBoundingClientRect().width) : null,
        text: text(row)
      }
    })
    const canvas = key => {
      const node = document.querySelector('[data-chart="' + key + '"]')
      if (!node) return null
      return {
        width: node.width,
        height: node.height,
        clientWidth: node.clientWidth,
        clientHeight: node.clientHeight
      }
    }
    const selectedPreset =
      Array.from(document.querySelectorAll('[data-preset]')).find(
        button => attr(button, 'aria-pressed') === 'true'
      ) ?? null
    return {
      title: text(document.querySelector('[data-hero-title]')),
      caption: text(document.querySelector('[data-hero-caption]')),
      roleDisclosure: text(document.querySelector('[data-role-disclosure]')),
      periodApplied: text(document.querySelector('[data-period-applied]')),
      presets: Array.from(document.querySelectorAll('[data-preset]')).map(button => ({
        id: attr(button, 'data-preset'),
        label: text(button),
        pressed: attr(button, 'aria-pressed') === 'true'
      })),
      selectedPreset: selectedPreset ? attr(selectedPreset, 'data-preset') : null,
      from: document.querySelector('[data-filters-from] input')?.value ?? null,
      to: document.querySelector('[data-filters-to] input')?.value ?? null,
      limit: text(document.querySelector('[data-filters-limit]')),
      limitOptions: [],
      applyDisabled: document.querySelector('[data-filters-apply]')?.disabled ?? null,
      applyLabel: text(document.querySelector('[data-filters-apply]')),
      rangeError:
        Array.from(document.querySelectorAll('p, span')).find(node =>
          /fecha final no puede ser anterior/i.test(text(node))
        )?.innerText.trim() ?? null,
      kpis: {
        total: kpi('total'),
        active: kpi('active'),
        inactive: kpi('inactive'),
        periodUsage: kpi('period-usage')
      },
      topRows,
      topCount: topRows.length,
      topHeading:
        Array.from(document.querySelectorAll('h2'))
          .map(text)
          .find(label => /más usados/i.test(label)) ?? null,
      specialtyRows,
      specialtyCount: specialtyRows.length,
      charts: { usage: canvas('usage'), specialty: canvas('specialty') },
      empty: state('[data-period-empty]'),
      emptyActions: Array.from(document.querySelectorAll('[data-widen-period]')).map(text),
      error: state('[data-state="error"]'),
      retry: has(document.querySelector('[data-retry]')),
      exportDisabled: document.querySelector('[data-export-csv]')?.disabled ?? null,
      scroll: {
        dataTable: document.querySelectorAll('[data-top-procedures-table] [data-table-scroll]').length,
        affordance: Boolean(
          document.querySelector('[data-top-procedures-table] [data-table-scroll-affordance]')
        ),
        scrollWidth:
          document.querySelector('[data-top-procedures-table] [data-table-scroll-viewport]')
            ?.scrollWidth ?? null,
        clientWidth:
          document.querySelector('[data-top-procedures-table] [data-table-scroll-viewport]')
            ?.clientWidth ?? null
      }
    }
  `
  )

/** `/my-procedures`: favorites, suggestions and the explorer. */
export const readFavorites = page =>
  read(
    page,
    `
    const favoriteRows = Array.from(document.querySelectorAll('[data-favorite-row]')).map((row, index) => {
      const lines = Array.from(row.querySelectorAll('p')).map(text)
      const spans = Array.from(row.querySelectorAll('span'))
      return {
        index,
        // The first span is the position badge; the code is the second one.
        code: text(spans[1] ?? spans[0]),
        name: lines[0] ?? null,
        meta: lines[1] ?? null,
        upDisabled: document.querySelectorAll('[aria-label="Subir favorito"]')[index]?.disabled ?? null,
        downDisabled:
          document.querySelectorAll('[aria-label="Bajar favorito"]')[index]?.disabled ?? null,
        remove: Boolean(document.querySelectorAll('[aria-label="Quitar de favoritos"]')[index])
      }
    })
    return {
      title: text(document.querySelector('[data-hero-title]')),
      count: text(document.querySelector('[data-favorites-count]')),
      favorites: favoriteRows,
      favoriteCodes: favoriteRows.map(row => row.code),
      favoritesSection: has(document.querySelector('[data-favorites-section]')),
      favoritesEmpty: text(document.querySelector('[data-favorites-section]'))?.match(/Sin favoritos/)?.[0] ?? null,
      suggestions: Array.from(document.querySelectorAll('[data-suggestion-row]')).map(row => ({
        code: text(row.querySelector('p')),
        name: text(row.querySelectorAll('p')[1]),
        button: text(row.querySelector('button'))
      })),
      explorer: {
        search: has(document.querySelector('[data-explore-search] input')),
        value: document.querySelector('[data-explore-search] input')?.value ?? null,
        rows: Array.from(document.querySelectorAll('[data-explorer-row]')).map(row => ({
          code: text(row.querySelector('span')),
          text: text(row),
          favorited: /Favorito/.test(text(row.querySelector('[class*="inline-flex"]')) ?? ''),
          button: text(row.querySelector('button'))
        })),
        error: state('[data-explorer-error]'),
        empty: text(document.querySelector('[data-explorer-section]'))?.match(/Sin resultados/)?.[0] ?? null,
        raw: text(document.querySelector('[data-explorer-section]'))?.slice(0, 200) ?? null
      },
      error: state('[data-my-procedures-error]'),
      errorRetry: text(document.querySelector('[data-my-procedures-error] button')),
      writeControls: Array.from(document.querySelectorAll('main button'))
        .map(button => text(button) || attr(button, 'aria-label'))
        .filter(label => /crear|editar|eliminar|nuevo|desactivar|importar/i.test(label ?? '')),
      backToPanel: Array.from(document.querySelectorAll('button')).map(text).includes('Volver al panel')
    }
  `
  )

/** `/reception-procedures`: cards, filters and the write-control census. */
export const readReception = page =>
  read(
    page,
    `
    const cards = Array.from(document.querySelectorAll('[data-procedure-card]')).map(card => ({
      code: text(card.querySelector('[class*="inline-flex"]')),
      text: text(card),
      name: text(card.querySelector('h3')),
      price: text(card.querySelector('[class*="text-systemBlue"]'))
    }))
    const kpi = key => text(document.querySelector('[data-kpi-card="' + key + '"]'))
    return {
      title: text(document.querySelector('[data-hero-title]')),
      search: {
        present: has(document.querySelector('[data-reception-search] input')),
        value: document.querySelector('[data-reception-search] input')?.value ?? null,
        placeholder:
          document.querySelector('[data-reception-search] input')?.getAttribute('placeholder') ?? null
      },
      kpis: {
        total: kpi('total'),
        avgPrice: kpi('avg-price'),
        avgDuration: kpi('avg-duration')
      },
      cards,
      cardCount: cards.length,
      codes: cards.map(card => card.code),
      error: state('[data-reception-error]'),
      retry: has(document.querySelector('[data-retry-reception]')),
      empty: text(document.querySelector('main'))?.match(/Sin resultados/)?.[0] ?? null,
      buttons: Array.from(document.querySelectorAll('main button')).map(button => ({
        label: text(button) || attr(button, 'aria-label'),
        aria: attr(button, 'aria-label'),
        visible: visible(button)
      })),
      writeControls: Array.from(document.querySelectorAll('main button'))
        .map(button => text(button) || attr(button, 'aria-label') || '')
        .filter(label => /crear|editar|eliminar|desactivar|importar|nuevo|duplicar|favorit/i.test(label)),
      forms: document.querySelectorAll('main form input:not([type="search"]), main form textarea').length,
      pagination: Array.from(document.querySelectorAll('nav button')).length
    }
  `
  )

/**
 * The shared `ProcedureCatalogPicker` inside the quotations create dialog.
 *
 * The picker has no data hooks of its own: the search field is the only input
 * with its placeholder and the loaded page is the list of buttons between the
 * field and the pagination.
 */
export const readPicker = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const dialog = document.querySelector('[role="dialog"]')
    if (!dialog) return null
    const input = dialog.querySelector('input[placeholder="Buscar por nombre o código..."]')
    const scope = input?.closest('div.space-y-3') ?? null
    const rows = scope
      ? Array.from(scope.querySelectorAll('button[type="button"]')).map(button => {
          const [metaNode] = button.querySelectorAll('div.opacity-70')
          return {
            text: text(button),
            code: text(button.querySelector('span.font-mono')),
            name: text(button.querySelector('.truncate')),
            meta: text(metaNode)
          }
        })
      : []

    return {
      dialog: text(dialog.querySelector('h1, h2')).slice(0, 80),
      search: {
        present: Boolean(input),
        value: input?.value ?? null,
        placeholder: input?.getAttribute('placeholder') ?? null
      },
      rows,
      rowCount: rows.length,
      codes: rows.map(row => row.code),
      emptyText: scope ? (/Sin resultados/.test(text(scope)) ? 'Sin resultados' : null) : null,
      pagination: scope ? Array.from(scope.querySelectorAll('nav button, button')).length : 0
    }
  })

/** Every visible dialog, top last, with its measured box. */
export const dialogStack = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    return Array.from(document.querySelectorAll('[role="dialog"], [role="alertdialog"]'))
      .filter(node => {
        const box = node.getBoundingClientRect()
        return box.width > 0 && box.height > 0
      })
      .map(node => {
        const box = node.getBoundingClientRect()
        return {
          role: node.getAttribute('role'),
          heading: text(node.querySelector('h1, h2, h3')),
          hooks: [
            'data-quotation-form',
            'data-tariff-form-error',
            'data-import-dropzone',
            'data-form-submit',
            'data-tariff-submit'
          ].filter(hook => node.querySelector(`[${hook}]`) || node.hasAttribute(hook)),
          box: {
            x: Math.round(box.x),
            y: Math.round(box.y),
            width: Math.round(box.width),
            height: Math.round(box.height)
          },
          text: text(node).slice(0, 160)
        }
      })
  })

// --- drivers ----------------------------------------------------------------

export const closeTop = async page => {
  const dialog = page.locator(DIALOG_SELECTOR).last()

  if (!(await dialog.count().catch(() => 0))) return { ok: true, closed: false }
  if (!(await dialog.isVisible().catch(() => false))) return { ok: true, closed: false }

  await page.keyboard.press('Escape').catch(() => {})
  const closed = await dialog
    .waitFor({ state: 'hidden', timeout: DIALOG_TIMEOUT })
    .then(() => true)
    .catch(() => false)

  return { ok: closed, closed }
}

export const closeAllDialogs = async page => {
  for (let attempt = 0; attempt < 4; attempt += 1) {
    const { closed } = await closeTop(page)
    if (!closed) break
  }
}

export const TOAST_SELECTOR =
  '[role="status"], [role="alert"], [aria-live="assertive"], [aria-live="polite"]'

export const dismissToasts = async page => {
  const dismissed = []
  // The success toast does not always declare `aria-live`, so the close button
  // is addressed by its own label: a toast that stays covers the hero actions
  // the next step has to click.
  const closers = page.locator(
    'button[aria-label="Cerrar notificación"]:visible, [aria-live] button[aria-label]:visible'
  )

  for (let attempt = 0; attempt < 8; attempt += 1) {
    if ((await closers.count().catch(() => 0)) === 0) break

    const button = closers.first()
    const label = await button.getAttribute('aria-label').catch(() => null)
    await button.click({ timeout: 2000 }).catch(() => {})
    dismissed.push(label)
    await page.waitForTimeout(120)
  }

  return dismissed
}

/**
 * The text of the visible toasts only.
 *
 * `UiStatusBadge` also renders `role="status"`, so a role-based read collected
 * every badge of the table it was measuring. The toast root is the element that
 * carries its own close button.
 */
export const toastText = page =>
  page.evaluate(() => {
    const roots = new Set()

    for (const closer of document.querySelectorAll('button[aria-label="Cerrar notificación"]')) {
      const root = closer.parentElement?.parentElement
      if (root) roots.add(root)
    }

    for (const live of document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]')) {
      if (live.querySelector('button[aria-label="Cerrar notificación"]')) roots.add(live)
    }

    for (const content of document.querySelectorAll('.toast-title, .toast-message')) {
      const root = content.parentElement?.parentElement
      if (root) roots.add(root)
    }

    return Array.from(roots)
      .map(node => (node.innerText ?? '').trim().replace(/\s+/g, ' '))
      .filter(Boolean)
      .join(' | ')
  })

export const waitForToast = async (page, expected, timeout = TOAST_TIMEOUT) => {
  const needle = expected ?? null

  await page
    .waitForFunction(
      value =>
        Array.from(document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]')).some(
          node => (value ? node.innerText.includes(value) : node.innerText.trim().length > 0)
        ),
      needle,
      { timeout, polling: 100 }
    )
    .catch(() => {})

  return toastText(page)
}

/** Opens a `UiSelect` and picks the option whose text matches. */
export const openSelect = async (page, trigger, optionText, options = {}) => {
  const locator = typeof trigger === 'string' ? page.locator(trigger).first() : trigger
  const timeout = options.timeout ?? 8000

  try {
    if (!(await locator.isVisible())) return { ok: false, reason: 'trigger not visible' }
    if (!(await locator.isEnabled())) return { ok: false, reason: 'trigger disabled' }
    await locator.scrollIntoViewIfNeeded({ timeout }).catch(() => {})
    await locator.click({ timeout })
  } catch (thrown) {
    return {
      ok: false,
      reason: `trigger not clickable: ${String(thrown?.message ?? thrown).slice(0, 200)}`
    }
  }

  const listbox = page.locator('[role="listbox"]:visible').first()
  const opened = await listbox
    .waitFor({ state: 'visible', timeout })
    .then(() => true)
    .catch(() => false)

  if (!opened) {
    await page.keyboard.press('Escape').catch(() => {})
    return { ok: false, reason: 'listbox did not open' }
  }

  const optionButtons = listbox.locator('button')
  const labels = (await optionButtons.allInnerTexts()).map(value => value.trim())

  if (options.probeOnly === true) {
    await locator.click({ timeout: 3000, force: true }).catch(() => {})
    await listbox.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})
    return { ok: true, options: labels, probeOnly: true }
  }

  const wanted = String(optionText ?? '').trim()
  const exact = labels.findIndex(label => label === wanted)
  const prefix = labels.findIndex(label => label.startsWith(wanted))
  const contains = labels.findIndex(label => label.includes(wanted))
  const index = exact !== -1 ? exact : prefix !== -1 ? prefix : contains
  const match =
    exact !== -1 ? 'exact' : prefix !== -1 ? 'prefix' : contains !== -1 ? 'contains' : null

  if (index === -1) {
    await locator.click({ timeout: 3000, force: true }).catch(() => {})
    await listbox.waitFor({ state: 'hidden', timeout: 2000 }).catch(() => {})
    return { ok: false, reason: `option "${wanted}" not found`, options: labels }
  }

  await optionButtons.nth(index).click({ timeout })
  await listbox.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})

  return { ok: true, selected: labels[index], match, options: labels }
}

export const fillControl = async (page, selector, value) => {
  const input = page.locator(selector).first()

  if (!(await input.isVisible().catch(() => false))) {
    return { ok: false, reason: `no visible control at ${selector}` }
  }

  await input.click({ timeout: 6000 }).catch(() => {})
  await input.press('ControlOrMeta+a').catch(() => {})
  await input.fill(String(value), { timeout: 8000 }).catch(() => {})
  await settleLayout(page)

  return { ok: true, value: await input.inputValue({ timeout: 4000 }).catch(() => null) }
}

export const fieldText = (page, selector) =>
  page.evaluate(sel => {
    const node = document.querySelector(sel)
    if (!node) return null

    return (node.innerText ?? '').trim().replace(/\s+/g, ' ')
  }, selector)

export const exists = (page, selector) =>
  page
    .locator(selector)
    .count()
    .then(count => count > 0)

/** A table row's cells, as text, for a given table root. */
export const tableRows = (page, root) =>
  page.evaluate(sel => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const table = document.querySelector(sel)
    if (!table) return null

    return Array.from(table.querySelectorAll('tbody tr')).map(row =>
      Array.from(row.querySelectorAll('td')).map(text)
    )
  }, root)

/** Clicks every `[role="tab"]` and reads what each one paints. */
export const sweepTabs = async (page, readSurface) => {
  const tabs = await page.evaluate(() =>
    Array.from(document.querySelectorAll('[role="tab"]')).map(tab => ({
      id: tab.getAttribute('id'),
      label: (tab.innerText ?? '').trim()
    }))
  )

  const surfaces = []

  for (const [index, tab] of tabs.entries()) {
    const locator = page.locator('[role="tab"]').nth(index)
    const clicked = await locator
      .click({ timeout: 8000 })
      .then(() => true)
      .catch(() => false)

    await settleLayout(page)
    await page.waitForTimeout(250)
    const data = clicked ? await readSurface(page) : null
    surfaces.push({ id: tab.id, label: tab.label, clicked, data })
  }

  if (tabs.length > 0) {
    await page
      .locator('[role="tab"]')
      .first()
      .click({ timeout: 5000 })
      .catch(() => {})
    await settleLayout(page)
  }

  return { tabs, surfaces }
}

// --- CSV --------------------------------------------------------------------

/** RFC 4180 reader for the catalogue export (BOM, CRLF, quoted fields). */
export const parseCsv = text => {
  const clean = String(text ?? '').replace(/^\uFEFF/, '')
  const rows = []
  let row = []
  let field = ''
  let quoted = false

  for (let index = 0; index < clean.length; index += 1) {
    const char = clean[index]

    if (quoted) {
      if (char === '"' && clean[index + 1] === '"') {
        field += '"'
        index += 1
      } else if (char === '"') {
        quoted = false
      } else {
        field += char
      }

      continue
    }

    if (char === '"') {
      quoted = true
    } else if (char === ',') {
      row.push(field)
      field = ''
    } else if (char === '\r') {
      continue
    } else if (char === '\n') {
      row.push(field)
      rows.push(row)
      row = []
      field = ''
    } else {
      field += char
    }
  }

  if (field !== '' || row.length > 0) {
    row.push(field)
    rows.push(row)
  }

  return rows.filter(entry => entry.some(cell => cell !== ''))
}

/** Money and counts as rendered by the SPA, as numbers. */
export const toNumber = value => {
  if (value === null || value === undefined) return null

  const cleaned = String(value)
    .replace(/[^\d.,-]/g, '')
    .replace(/,/g, '')
  const parsed = Number(cleaned)

  return Number.isFinite(parsed) ? parsed : null
}

/**
 * Reads the CSV the export button builds, without letting the browser write a
 * file: the blob the page hands to `URL.createObjectURL` is read as text.
 */
export const captureCsvExport = async (page, trigger, timeout = ACTION_TIMEOUT) => {
  await page.evaluate(() => {
    window.__auditU8Csv = null
    if (window.__auditU8CsvHooked) return

    const original = URL.createObjectURL.bind(URL)
    URL.createObjectURL = blob => {
      try {
        blob.text().then(text => {
          window.__auditU8Csv = text
        })
      } catch {
        // A non-blob URL is not the export; nothing to read.
      }

      return original(blob)
    }
    window.__auditU8CsvHooked = true
  })

  const button = page.locator(trigger).first()

  if (!(await button.isVisible().catch(() => false))) {
    return { ok: false, reason: `no visible export control at ${trigger}` }
  }

  if (await button.isDisabled().catch(() => false)) {
    return { ok: false, reason: 'the export control is disabled' }
  }

  const download = page
    .waitForEvent('download', { timeout })
    .then(event => event.suggestedFilename())
    .catch(() => null)

  await button.click({ timeout }).catch(() => {})
  const filename = await download

  const text = await page
    .waitForFunction(() => typeof window.__auditU8Csv === 'string', undefined, {
      timeout,
      polling: 100
    })
    .then(() => page.evaluate(() => window.__auditU8Csv))
    .catch(() => null)

  return { ok: Boolean(text), filename, text }
}

// --- request instrumentation ------------------------------------------------

const pathOf = url => {
  try {
    return new URL(url).pathname
  } catch {
    return url
  }
}

/** Every request the page issues, with the status once it answers. */
export const watchRequests = page => {
  const entries = []

  page.on('request', request => {
    if (!request.url().includes('/api/')) return
    entries.push({
      method: request.method(),
      url: request.url(),
      path: pathOf(request.url()),
      query: (() => {
        try {
          return Object.fromEntries(new URL(request.url()).searchParams.entries())
        } catch {
          return {}
        }
      })(),
      data: request.postData() ?? null,
      at: Date.now(),
      status: null
    })
  })

  page.on('response', response => {
    if (!response.url().includes('/api/')) return
    const path = pathOf(response.url())
    const method = response.request().method()
    const entry = [...entries]
      .reverse()
      .find(row => row.path === path && row.method === method && row.status === null)

    if (!entry) return
    entry.status = response.status()
  })

  return entries
}

export const callsTo = (entries, method, path, predicate = () => true) =>
  entries.filter(entry => entry.method === method && entry.path === path && predicate(entry))

/**
 * Moves instrument-induced failures out of `unexpected` with their reason: a
 * probe that forces a state or submits a body the API must refuse is exercising
 * a contract, not measuring a surface defect.
 */
export const markExpected = (failedRequests, predicate, reason) => {
  const moved = (failedRequests.unexpected ?? []).filter(predicate)
  failedRequests.unexpected = (failedRequests.unexpected ?? []).filter(
    failure => !predicate(failure)
  )
  failedRequests.expected = [
    ...(failedRequests.expected ?? []),
    ...moved.map(failure => ({ ...failure, reason }))
  ]
  return failedRequests
}

export const VERDICTS = Object.freeze(['OK', 'DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO'])

export const isNotOk = verdict => ['DEFECTO', 'NO-OP', 'CONFUSO'].includes(verdict)

/**
 * The step recorder: one entry per action, with the capture, the verdict, the
 * request status and the observed detail. A probe that throws becomes
 * `NO PROBADO` with its reason instead of ending the walkthrough.
 */
export const recorder = (page, evidence, state) => {
  let index = 0

  return async (id, action, expected, run) => {
    index += 1
    const sequence = String(index).padStart(2, '0')
    const entry = {
      step: `${sequence}-${id}`,
      id,
      action,
      expected,
      obtained: null,
      verdict: 'NO PROBADO',
      reason: null,
      requestStatus: null,
      request: null,
      shot: null,
      detail: null,
      extra: null
    }

    state.currentStep = id
    const before = state.pageErrors.length
    const startedAt = Date.now()
    let broken = false

    await dismissToasts(page).catch(() => {})

    try {
      const result = (await run(entry)) ?? {}
      Object.assign(entry, result)
    } catch (thrown) {
      broken = true
      entry.verdict = 'NO PROBADO'
      entry.reason = `probe error: ${String(thrown?.message ?? thrown).slice(0, 300)}`
    }

    entry.elapsedMs = Date.now() - startedAt
    entry.pageErrors = state.pageErrors.slice(before).map(error => error.message)

    // A probe that died mid-form leaves its dialog over the page; closing it
    // here keeps the next step measurable instead of cascading a timeout.
    if (broken) await closeAllDialogs(page).catch(() => {})

    try {
      entry.shot = await shot(page, `${evidence.shotDir}/${entry.step}.png`)
    } catch {
      entry.shot = null
    }

    evidence.steps.push(entry)

    if (isNotOk(entry.verdict)) {
      evidence.findings.push({
        step: entry.step,
        action: entry.action,
        expected: entry.expected,
        obtained: entry.obtained,
        verdict: entry.verdict,
        requestStatus: entry.requestStatus,
        detail: entry.detail ?? entry.reason ?? null,
        shot: entry.shot
      })
    }

    return entry
  }
}

/** The last non-GET call the page issued, as the step's request evidence. */
export const lastWrite = entries => {
  const writes = entries.filter(entry => entry.method !== 'GET')
  const last = writes.at(-1)

  return last
    ? { method: last.method, path: last.path, status: last.status, data: last.data }
    : null
}

/** The aggregate every walk JSON carries. */
export const walkTotals = evidence => ({
  steps: evidence.steps.length,
  ok: evidence.steps.filter(entry => entry.verdict === 'OK').length,
  defect: evidence.steps.filter(entry => entry.verdict === 'DEFECTO').length,
  noop: evidence.steps.filter(entry => entry.verdict === 'NO-OP').length,
  confusing: evidence.steps.filter(entry => entry.verdict === 'CONFUSO').length,
  notProbed: evidence.steps.filter(entry => entry.verdict === 'NO PROBADO').length,
  findings: evidence.findings.length
})
