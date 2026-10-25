// Plan #13 (issue #368, U5) — measurement helpers shared by the money rows.
//
// Plain readers and small drivers: no `test()` is registered here, so
// `--grep @U5` selects exactly the rows the session modules declare. Everything
// returns observed values; a probe that cannot observe returns an explicit
// `null`/`{ ok: false, reason }` so the caller records `NO PROBADO` instead of
// throwing.
//
// The evidence this session writes lives in `U5_EVIDENCE_DIR`; `.atl/` is
// gitignored, and every JSON carries `generatedAt` plus the `git rev-parse HEAD`
// of the instrument (see `stamp`).

import { execFileSync } from 'node:child_process'

import { BASE_URL, capture, settleLayout, writeEvidence } from '../harness.js'

export const U5_EVIDENCE_DIR = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U5'

export const CASH_ROUTE = '/cash-register'
export const RTB_ROUTE = '/cash-register/ready-to-bill'

export const DESKTOP = { width: 1440, height: 900 }
export const MOBILE = { width: 390, height: 844 }

export const THRESHOLD = 120
export const API_TIMEOUT = 25000
export const ACTION_TIMEOUT = 20000
export const LOAD_TIMEOUT = 25000
export const TOAST_TIMEOUT = 10000

// Money comparison. The API rounds to cents; anything below half a cent is the
// same number.
export const TOLERANCE = 0.005

export const audit = (filePath, payload) =>
  writeEvidence(filePath, payload, { dir: U5_EVIDENCE_DIR })
export const shot = (page, filePath) => capture(page, filePath, { dir: U5_EVIDENCE_DIR })

export const round2 = value => Math.round((Number(value) || 0) * 100) / 100

export const revision = () => {
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
  session: 'U5',
  generatedAt: new Date().toISOString(),
  revision: REVISION,
  base: BASE_URL
})

export const localDay = (date = new Date()) => {
  const pad = value => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export const addDays = (day, days) => {
  const [year, month, date] = day.split('-').map(Number)
  const next = new Date(year, month - 1, date + days)
  return localDay(next)
}

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
      body: null,
      bytes: null
    }
  }
}

// --- reading the screen -----------------------------------------------------

export const visible = node => {
  const box = node?.getBoundingClientRect()
  return Boolean(box && box.width > 0 && box.height > 0)
}

export const text = node => (node?.innerText ?? '').trim()

/** `S/ 1,234.50` -> 1234.5; `N/D` -> null. */
export const parseCurrency = value => {
  const raw = String(value ?? '').trim()
  if (!raw || /N\/D/i.test(raw)) return null

  const cleaned = raw.replace(/[^\d,.-]/g, '')
  if (!cleaned) return null

  const amount = Number.parseFloat(cleaned.replace(/,/g, ''))
  return Number.isFinite(amount) ? amount : null
}

export const hero = page =>
  page.evaluate(() => {
    const isVisible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const read = node => (node?.innerText ?? '').trim()
    const root =
      document.querySelector('[data-caja-hero]') ?? document.querySelector('[data-rtb-hero]')
    const action = name => {
      const node = document.querySelector(`[data-hero-action="${name}"]`)
      return node
        ? { present: true, visible: isVisible(node), disabled: node.disabled === true }
        : { present: false }
    }

    return {
      present: Boolean(root),
      eyebrow: read(document.querySelector('[data-hero-eyebrow]')),
      title: read(document.querySelector('[data-hero-title]')),
      caption: read(document.querySelector('[data-hero-caption]')),
      state: read(document.querySelector('[data-hero-state]')),
      stateVariant:
        document.querySelector('[data-hero-state]')?.getAttribute('data-variant') ?? null,
      actions: {
        open: action('open'),
        close: action('close'),
        deposit: action('deposit'),
        refresh: action('refresh')
      },
      error: read(document.querySelector('[data-caja-error]')),
      errorVisible: isVisible(document.querySelector('[data-caja-error]')),
      retry: Boolean(document.querySelector('[data-caja-retry]')),
      rtbError: read(document.querySelector('[data-rtb-error]')),
      rtbErrorVisible: isVisible(document.querySelector('[data-rtb-error]')),
      rtbRetry: Boolean(document.querySelector('[data-rtb-retry]'))
    }
  })

export const kpi = page =>
  page.evaluate(() =>
    Object.fromEntries(
      Array.from(document.querySelectorAll('[data-kpi-value]')).map(node => [
        node.getAttribute('data-kpi-value'),
        (node.innerText ?? '').trim()
      ])
    )
  )

export const rtbKpis = page =>
  page.evaluate(() =>
    Object.fromEntries(
      Array.from(document.querySelectorAll('[data-report-kpi]')).map(node => [
        node.getAttribute('data-report-kpi'),
        (node.innerText ?? '').trim()
      ])
    )
  )

/**
 * Loading affordances of the current paint: the S1 hypothesis reads this.
 *
 * `spinners` counts the `LoadingSpinner` primitive (`.loading-spinner-container`)
 * outside the data tables; a table's own "Cargando..." row is counted apart
 * (`tableSpinners`) because it is a table affordance, not the surface's answer to
 * "the session and the balances are still unknown".
 */
export const loadingAffordance = page =>
  page.evaluate(() => {
    const cells = Array.from(document.querySelectorAll('.data-table-cell-loading'))
    const tableSpinners = cells.length
    const all = Array.from(document.querySelectorAll('.loading-spinner-container, .spinner-ring'))
    const insideTables = all.filter(node => node.closest('.data-table-container, table'))
    const spinners = all.length - insideTables.length
    const skeletons = document.querySelectorAll('[class*="skeleton"], [data-skeleton]').length
    const loadingText = cells
      .map(node => (node.innerText ?? '').trim())
      .filter(Boolean)
      .join(' | ')

    return { spinners, skeletons, tableSpinners, loadingCells: tableSpinners, loadingText }
  })

export const toastText = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[aria-live="assertive"]'))
      .map(node => (node.innerText ?? '').trim())
      .filter(Boolean)
      .join(' | ')
  )

export const waitForToast = async (page, expected, timeout = TOAST_TIMEOUT) => {
  if (expected) {
    await page
      .waitForFunction(
        message =>
          Array.from(document.querySelectorAll('[aria-live="assertive"]')).some(node =>
            node.innerText.includes(message)
          ),
        expected,
        { timeout, polling: 100 }
      )
      .catch(() => {})
  } else {
    await page
      .waitForFunction(
        () =>
          Array.from(document.querySelectorAll('[aria-live="assertive"]')).some(
            node => node.innerText.trim().length > 0
          ),
        undefined,
        { timeout, polling: 100 }
      )
      .catch(() => {})
  }

  return toastText(page)
}

// --- drivers ----------------------------------------------------------------

/** Waits for the money surface to have painted its first real frame. */
export const settleCash = async (page, root = '[data-caja-hero]') => {
  await page
    .locator(root)
    .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
    .catch(() => {})
  await page
    .waitForFunction(() => !document.querySelector('.data-table-cell-loading'), undefined, {
      timeout: LOAD_TIMEOUT,
      polling: 120
    })
    .catch(() => {})
  await settleLayout(page)
}

/**
 * Opens a `UiSelect` (`button[aria-haspopup]` + `[role="listbox"]` option
 * buttons) and picks the option whose text matches.
 *
 * Exact match first; the shipped labels carry suffixes (`Sede Norte (SC-NOR-01)`),
 * so a unique prefix is accepted and reported. Anything else is a refusal.
 */
export const openSelect = async (page, trigger, optionText, options = {}) => {
  const locator = typeof trigger === 'string' ? page.locator(trigger).first() : trigger
  const timeout = options.timeout ?? 6000
  let forced = false

  try {
    if (!(await locator.isVisible())) return { ok: false, reason: 'trigger not visible' }
    if (!(await locator.isEnabled())) return { ok: false, reason: 'trigger disabled' }
    await locator.scrollIntoViewIfNeeded({ timeout }).catch(() => {})
    await locator.click({ timeout })
  } catch (thrown) {
    // A dropdown that stayed open covers the next trigger. Escape would close
    // the dialog too, so the trigger is clicked without actionability checks and
    // the workaround is reported with the measurement.
    try {
      await locator.scrollIntoViewIfNeeded({ timeout: 3000 }).catch(() => {})
      await locator.click({ timeout: 5000, force: true })
      forced = true
    } catch {
      return {
        ok: false,
        reason: `trigger not clickable: ${String(thrown?.message ?? thrown).slice(0, 200)}`
      }
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

  // Reading the catalog: the trigger toggles the list closed again, so the
  // dialog under the dropdown stays open.
  if (options.probeOnly === true || optionText === undefined) {
    await locator.click({ timeout: 3000, force: true }).catch(() => {})
    const closed = await listbox
      .waitFor({ state: 'hidden', timeout: 3000 })
      .then(() => true)
      .catch(() => false)

    return { ok: true, options: labels, probeOnly: true, listboxStuck: closed === false }
  }

  const wanted = String(optionText).trim()

  const exact = labels.findIndex(label => label === wanted)
  const prefix = labels.findIndex(label => label.startsWith(wanted))
  const contains = labels.findIndex(label => label.includes(wanted))
  const index = exact !== -1 ? exact : prefix !== -1 ? prefix : contains
  const match =
    exact !== -1 ? 'exact' : prefix !== -1 ? 'prefix' : contains !== -1 ? 'contains' : null

  if (index === -1) {
    // Escape would close the dialog under the dropdown, so the trigger toggles
    // the list shut instead.
    await locator.click({ timeout: 3000, force: true }).catch(() => {})
    await listbox.waitFor({ state: 'hidden', timeout: 2000 }).catch(() => {})
    return { ok: false, reason: `option "${wanted}" not found`, options: labels }
  }

  await optionButtons.nth(index).click({ timeout })
  const closed = await listbox
    .waitFor({ state: 'hidden', timeout: 3000 })
    .then(() => true)
    .catch(() => false)

  return {
    ok: true,
    selected: labels[index],
    match,
    options: labels,
    forced,
    listboxStuck: closed === false
  }
}

/** Picks the option of the select that is already open. */
export const chooseOpenOption = async (page, optionText) => {
  const listbox = page.locator('[role="listbox"]:visible').first()
  const optionButtons = listbox.locator('button')
  const labels = (await optionButtons.allInnerTexts()).map(value => value.trim())
  const index = labels.findIndex(label => label === String(optionText).trim())

  if (index === -1)
    return { ok: false, reason: `option "${optionText}" not found`, options: labels }

  await optionButtons.nth(index).click({ timeout: 5000 })
  await listbox.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})
  return { ok: true, selected: labels[index], options: labels }
}

/** Clicks the `[role="tab"]` whose visible label is `label`. */
export const openTab = async (page, label) => {
  const tabs = page.locator('[role="tab"]:visible')
  const total = await tabs.count()
  const labels = []

  for (let index = 0; index < total; index += 1) {
    const tab = tabs.nth(index)
    const tabLabel = (await tab.innerText({ timeout: 4000 }).catch(() => ''))
      .replace(/\s+/g, ' ')
      .trim()
    labels.push(tabLabel)

    if (tabLabel === label) {
      await tab.click({ timeout: 8000 })
      await settleLayout(page)
      return { ok: true, labels, index }
    }
  }

  return { ok: false, reason: `tab "${label}" not found`, labels }
}

/**
 * Types into a `CurrencyInput`. The component formats on blur and shows the raw
 * number while focused, so the probe focuses first and never blurs before the
 * model has been updated.
 */
export const fillMoney = async (page, scope, value) => {
  const input = page.locator(`${scope} input`).first()

  if (!(await input.isVisible().catch(() => false))) {
    return { ok: false, reason: `no amount input inside ${scope}` }
  }

  await input.click({ timeout: 5000 }).catch(() => {})
  await input.press('ControlOrMeta+a').catch(() => {})
  await input.fill(String(value), { timeout: 5000 }).catch(() => {})
  await settleLayout(page)

  return { ok: true, value: await input.inputValue({ timeout: 4000 }).catch(() => null) }
}

export const fillInput = async (page, scope, value) => {
  const input = page.locator(`${scope} input, ${scope} textarea`).first()

  if (!(await input.isVisible().catch(() => false))) {
    return { ok: false, reason: `no input inside ${scope}` }
  }

  await input.fill(String(value), { timeout: 8000 }).catch(() => {})
  await settleLayout(page)

  return { ok: true, value: await input.inputValue({ timeout: 4000 }).catch(() => null) }
}

/** Reads the visible field error a modal paints under its control. */
export const fieldErrors = (page, scope) =>
  page.evaluate(selector => {
    const root = document.querySelector(selector)
    if (!root) return null

    return Array.from(root.querySelectorAll('p, span'))
      .map(node => (node.innerText ?? '').trim())
      .filter(line =>
        /requerid|inválid|no puede|supera|exige|debe|no es válido|obligatori/i.test(line)
      )
  }, scope)

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
      data: request.postData() ?? null,
      at: Date.now(),
      status: null,
      contentType: null
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
    entry.contentType = response.headers()['content-type'] ?? null
  })

  return entries
}

export const callsTo = (entries, method, path, predicate = () => true) =>
  entries.filter(entry => entry.method === method && entry.path === path && predicate(entry))

export const lastCall = (entries, method, path, predicate = () => true) =>
  callsTo(entries, method, path, predicate).at(-1) ?? null

/** Waits for one request/response pair on a pathname. */
export const waitForCall = (
  page,
  method,
  pathname,
  predicate = () => true,
  timeout = ACTION_TIMEOUT
) =>
  page
    .waitForResponse(
      response => {
        if (response.request().method() !== method) return false

        try {
          const url = new URL(response.url())
          if (url.pathname !== pathname) return false
          return predicate(url.searchParams, url)
        } catch {
          return false
        }
      },
      { timeout }
    )
    .catch(() => null)

export const paramsOf = response => {
  try {
    return Object.fromEntries(new URL(response.url()).searchParams.entries())
  } catch {
    return {}
  }
}

/** Delays matching routes so a loading state is observable, not inferred. */
export const delayRoute = async (page, matcher, delayMs = 900) => {
  let delay = delayMs

  const handler = async route => {
    if (delay > 0) await new Promise(resolve => setTimeout(resolve, delay))
    await route.continue().catch(() => {})
  }

  await page.route(matcher, handler)

  return {
    stop: async () => {
      delay = 0
      await page.unroute(matcher, handler).catch(() => {})
    }
  }
}

/** Answers matching routes with a fixed status instead of the API. */
export const forceStatus = async (page, matcher, options = {}) => {
  const status = options.status ?? 500
  const payload = options.body ?? {
    message: options.message ?? 'AUDIT-U5 forced failure'
  }

  const handler = route =>
    route.fulfill({
      status,
      contentType: 'application/json',
      body: JSON.stringify(payload)
    })

  await page.route(matcher, handler)

  return {
    stop: async () => {
      await page.unroute(matcher, handler).catch(() => {})
    }
  }
}

/**
 * Moves instrument-induced failures out of `unexpected` with their reason: a
 * probe that forces a 500 or submits an invalid body is exercising a state, not
 * measuring a surface defect.
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

// --- API snapshots ----------------------------------------------------------

const summaryNumbers = summary => ({
  id: summary?.session?.id ?? null,
  status: summary?.session?.status ?? null,
  userId: summary?.session?.user_id ?? null,
  branchId: summary?.session?.branch_id ?? null,
  openingNotes: summary?.session?.opening_notes ?? null,
  opening: round2(summary?.opening_amount),
  income: round2(summary?.total_income),
  expenses: round2(summary?.total_expenses),
  cashIn: round2(summary?.cash_in),
  cashOut: round2(summary?.cash_out),
  netflow: round2(summary?.net_flow),
  expected: round2(summary?.expected_amount),
  transactions: Number(summary?.transactions_count ?? 0),
  movements: Number(summary?.movements_count ?? 0)
})

/** `GET /api/cash-register/current` straight from the API. */
export const summaryFromApi = (request, session) =>
  call(request, session, 'GET', '/api/cash-register/current')

/**
 * The drawer as the API reports it: the current session when there is one, or
 * the given session id (a closed one, for the post-close snapshot), plus the
 * deposits of that session.
 */
export const sessionSnapshot = async (request, session, options = {}) => {
  const sessionId = options.sessionId ?? null
  const response = sessionId
    ? await call(request, session, 'GET', `/api/cash-register/sessions/${sessionId}`)
    : await summaryFromApi(request, session)

  const summary = sessionId ? response.body?.data : response.body?.data?.summary
  const numbers = summaryNumbers(summary)
  const drawerId = numbers.id ?? sessionId

  let deposits = { count: 0, total: 0, status: null }

  if (drawerId) {
    const list = await call(
      request,
      session,
      'GET',
      `/api/cash-register/deposits?per_page=100&cash_register_session_id=${drawerId}`
    )
    const rows = Array.isArray(list.body?.data) ? list.body.data : []
    deposits = {
      count: rows.length,
      total: round2(rows.reduce((sum, row) => sum + Number(row?.amount ?? 0), 0)),
      status: list.status
    }
  }

  return {
    ...numbers,
    deposits,
    actorId: session.user?.id ?? null,
    ownedByActor: numbers.userId === null ? null : numbers.userId === (session.user?.id ?? null),
    requestStatus: response.status,
    at: new Date().toISOString()
  }
}

/**
 * Pure arithmetic: what each ledger field moved between two snapshots, against
 * what the step promised. A field the caller does not name is not judged.
 */
export const expectDelta = (before, after, expected = {}) => {
  const fields = {
    opening: 'opening',
    income: 'income',
    expenses: 'expenses',
    cashIn: 'cashIn',
    cashOut: 'cashOut',
    netflow: 'netflow',
    expected: 'expected',
    transactions: 'transactions',
    movements: 'movements'
  }

  const deltas = {}
  const mismatches = []

  for (const [key, field] of Object.entries(fields)) {
    if (!Object.hasOwn(expected, key)) continue

    const from = Number(before?.[field] ?? 0)
    const to = Number(after?.[field] ?? 0)
    const delta = round2(to - from)
    const wanted = round2(expected[key])
    const ok = Math.abs(delta - wanted) < TOLERANCE

    deltas[key] = { field, from, to, delta, expected: wanted, ok }
    if (!ok) mismatches.push({ field: key, delta, expected: wanted })
  }

  return {
    formula: 'after - before',
    matches: mismatches.length === 0,
    deltas,
    mismatches,
    expected
  }
}

/** `opening + cash_in - cash_out == expected`, the drawer's own equation. */
export const drawerFormula = snapshot => {
  if (!snapshot || snapshot.id === null) return { ok: null, reason: 'no session' }

  const computed = round2(snapshot.opening + snapshot.cashIn - snapshot.cashOut)

  return {
    ok: Math.abs(computed - Number(snapshot.expected ?? 0)) < TOLERANCE,
    computed,
    expected: snapshot.expected,
    formula: 'opening + cashIn - cashOut'
  }
}

// --- table / surface readers ------------------------------------------------

const readTable = (page, selector) =>
  page.evaluate(tableSelector => {
    const isVisible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const read = node => (node?.innerText ?? '').trim()
    const root = document.querySelector(tableSelector)

    if (!root) return null

    const rows = Array.from(root.querySelectorAll('tbody tr'))
    const loadingRow = rows.find(row => row.querySelector('.data-table-cell-loading'))
    const emptyRow = rows.find(row => row.querySelector('.data-table-cell-empty'))
    const dataRows = rows.filter(
      row => !row.querySelector('.data-table-cell-loading, .data-table-cell-empty')
    )

    return {
      present: true,
      loading: Boolean(loadingRow),
      loadingText: read(loadingRow),
      empty: Boolean(emptyRow),
      emptyText: read(emptyRow),
      rowCount: dataRows.length,
      rows: dataRows.map(row => {
        const cells = Array.from(row.querySelectorAll('td')).map(cell =>
          read(cell).replace(/\s+/g, ' ')
        )
        const actions = Array.from(row.querySelectorAll('button')).map(button => ({
          label: read(button).replace(/\s+/g, ' '),
          action: button.getAttribute('data-row-action'),
          pending: button.getAttribute('data-pay-pending'),
          preview: button.getAttribute('data-preview-action'),
          quotation: button.getAttribute('data-quotation-action'),
          disabled: button.disabled === true,
          visible: isVisible(button)
        }))

        return { cells, actions, text: read(row).replace(/\s+/g, ' ') }
      }),
      pagination: read(document.querySelector('.pagination')) || null,
      scroll: {
        affordance: Boolean(root.querySelector('[data-table-scroll-affordance]')),
        state: root.querySelector('[data-table-scroll]')?.getAttribute('data-table-scroll') ?? null
      }
    }
  }, selector)

export const readTransactionRows = page => readTable(page, '[data-transaction-table]')

export const readMovementRows = page => readTable(page, '[data-movement-table]')

export const readSessionRows = page => readTable(page, '[data-session-table]')

export const readPendingRows = page => readTable(page, '[data-pending-payments-table]')

export const readRtbTable = page => readTable(page, '[data-rtb-table]')

export const movementTotals = page =>
  page.evaluate(() =>
    Object.fromEntries(
      Array.from(document.querySelectorAll('[data-movement-total]')).map(node => [
        node.getAttribute('data-movement-total'),
        (node.querySelector('[data-movement-total-value]')?.innerText ?? '').trim()
      ])
    )
  )

/** The period report the Reports tab paints. */
export const readReport = page =>
  page.evaluate(() => {
    const read = node => (node?.innerText ?? '').trim()
    const isVisible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const values = attribute =>
      Object.fromEntries(
        Array.from(document.querySelectorAll(`[${attribute}]`)).map(node => [
          node.getAttribute(attribute),
          read(node)
        ])
      )

    const table = key => {
      const root = document.querySelector(`[data-report-table="${key}"]`)
      if (!root) return null

      return Array.from(root.querySelectorAll('tbody tr'))
        .filter(row => !row.querySelector('.data-table-cell-loading, .data-table-cell-empty'))
        .map(row =>
          Array.from(row.querySelectorAll('td'))
            .map(cell => read(cell).replace(/\s+/g, ' '))
            .filter(Boolean)
        )
    }

    return {
      present: Boolean(document.querySelector('[data-report-start]')),
      period: {
        start: document.querySelector('[data-report-start] input')?.value ?? null,
        end: document.querySelector('[data-report-end] input')?.value ?? null
      },
      presets: Array.from(document.querySelectorAll('[data-preset]')).map(node => ({
        label: read(node),
        days: node.getAttribute('data-preset')
      })),
      kpis: values('data-report-kpi'),
      reconciliation: {
        cash: values('data-reconciliation-value').cash ?? null,
        electronic: values('data-reconciliation-value').electronic ?? null,
        deposits: values('data-reconciliation-value').deposits ?? null,
        pending: values('data-reconciliation-value').pending ?? null,
        values: values('data-reconciliation-value'),
        counts: values('data-reconciliation-count')
      },
      tables: {
        paymentMethods: table('payment-methods'),
        daily: table('daily'),
        branches: table('summary-branches'),
        users: table('summary-users')
      },
      loading: Boolean(document.querySelector('.data-table-cell-loading')),
      error: read(document.querySelector('[data-report-error]')),
      errorVisible: isVisible(document.querySelector('[data-report-error]')),
      retry: Boolean(document.querySelector('[data-report-retry]')),
      exports: Array.from(document.querySelectorAll('[data-export]')).map(node => ({
        format: node.getAttribute('data-export'),
        label: read(node)
      })),
      exportError: read(document.querySelector('[data-export-error]'))
    }
  })

/** The settlement of a promise: `OK`, `DEFECTO`, `NO-OP`, `CONFUSO`, `NO PROBADO`. */
export const VERDICTS = Object.freeze(['OK', 'DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO'])

export const isNotOk = verdict => ['DEFECTO', 'NO-OP', 'CONFUSO'].includes(verdict)

/** `formatCurrency`-shaped text for the assertions' messages. */
export const moneyText = value => `S/ ${round2(value).toFixed(2)}`
