// Plan #13 (issue #367, U4) — measurement helpers shared by the patients rows.
//
// Pure browser/API readers only: no `test()` is registered here, so `--grep
// @U4` selects exactly the rows the session modules declare. Everything returns
// observed values; a probe that cannot observe throws or returns an explicit
// null so the caller can record `NO PROBADO` with a reason.

import { BASE_URL } from '../harness.js'

export const API_TIMEOUT = 20000
export const ACTION_TIMEOUT = 15000
export const LOAD_TIMEOUT = 20000
export const DESKTOP = { width: 1440, height: 900 }
export const MOBILE = { width: 390, height: 844 }
export const PATIENTS_ROUTE = '/patients'
export const THRESHOLD = 80

export const round2 = value => Math.round((Number(value) || 0) * 100) / 100

export const messageOf = body => {
  if (typeof body?.message === 'string') return body.message
  if (typeof body?.error?.message === 'string') return body.error.message
  if (typeof body?.meta?.message === 'string') return body.meta.message
  return null
}

/**
 * One authenticated API call. Network and parse failures become a row with
 * `status: null`, never an exception: an unreachable probe is evidence.
 */
export const call = async (request, session, method, path, data) => {
  const options = {
    method,
    headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' },
    timeout: API_TIMEOUT
  }

  if (data !== undefined) options.data = data

  try {
    const response = await request.fetch(`${BASE_URL}${path}`, options)
    const body = await response.json().catch(() => null)

    return {
      method,
      path,
      status: response.status(),
      ok: response.ok(),
      message: response.ok() ? null : messageOf(body),
      body
    }
  } catch (thrown) {
    return {
      method,
      path,
      status: null,
      ok: false,
      message: String(thrown?.message ?? thrown),
      body: null
    }
  }
}

export const localDay = () => {
  const now = new Date()
  const pad = value => String(value).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

export const monthStart = () => {
  const now = new Date()
  const pad = value => String(value).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`
}

export const addDays = (day, days) => {
  const [year, month, date] = day.split('-').map(Number)
  const next = new Date(year, month - 1, date + days)
  const pad = value => String(value).padStart(2, '0')
  return `${next.getFullYear()}-${pad(next.getMonth() + 1)}-${pad(next.getDate())}`
}

// The toast host is the only assertive live region in the shell; the KPI
// figures also carry `aria-live="polite"`, so the broader selector mixed the
// numbers into the toast evidence.
export const toastText = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[aria-live="assertive"]'))
      .map(node => node.innerText.trim())
      .filter(Boolean)
      .join(' | ')
  )

export const waitForToast = async (page, expected, timeout = 8000) => {
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

  return toastText(page)
}

export const parseCurrency = value => {
  const cleaned = String(value ?? '').replace(/[^\d,.-]/g, '')
  const amount = Number.parseFloat(cleaned.replace(/,/g, ''))
  return Number.isFinite(amount) ? amount : null
}

/** Collects every request the page issues, for "did it send one?" questions. */
export const requestTracker = page => {
  const requests = []
  page.on('request', request =>
    requests.push({ method: request.method(), url: request.url(), at: Date.now() })
  )
  return requests
}

export const requestsTo = (requests, method, pathname) =>
  requests.filter(entry => {
    if (entry.method !== method) return false
    try {
      return new URL(entry.url).pathname === pathname
    } catch {
      return false
    }
  })

/**
 * Moves instrument-induced failures out of `unexpected` with their reason: a
 * probe that submits an invalid body or forces a 500 is exercising a state, not
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

export const paramsOf = response => {
  try {
    return Object.fromEntries(new URL(response.url()).searchParams.entries())
  } catch {
    return {}
  }
}

/** Wait for one GET/POST on a pathname; predicate narrows by query params. */
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

/** Delay matching routes so a loading state is observable instead of inferred. */
export const delayRoute = async (page, matcher, delayMs = 700) => {
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

export const settleList = async page => {
  await page
    .locator('[data-patients-hero]')
    .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
    .catch(() => {})
  await page
    .locator('.loading-spinner')
    .waitFor({ state: 'hidden', timeout: LOAD_TIMEOUT })
    .catch(() => {})
}

export const settleDetail = async page => {
  await page
    .locator('[data-patient-detail-hero]')
    .waitFor({ state: 'visible', timeout: LOAD_TIMEOUT })
    .catch(() => {})
}

export const readPatientsSnapshot = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim()
    const visible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const rows = Array.from(document.querySelectorAll('[data-patient-row]')).filter(visible)
    const filterCard = document.querySelector('section[data-patients-block="directory"]')
    const selects = Array.from(filterCard?.querySelectorAll('button[aria-haspopup="true"]') ?? [])

    return {
      url: window.location.pathname,
      h1: {
        count: document.querySelectorAll('h1').length,
        texts: Array.from(document.querySelectorAll('h1')).map(text)
      },
      hero: Boolean(document.querySelector('[data-patients-hero]')),
      stats: Object.fromEntries(
        Array.from(document.querySelectorAll('[data-stat]')).map(node => [
          node.getAttribute('data-stat'),
          text(node)
        ])
      ),
      rows: rows.map(node => {
        const flat = text(node).replace(/\s+/g, ' ')
        const match = flat.match(/ID:\s*(\d+)/)
        return { id: match ? Number(match[1]) : null, text: flat.slice(0, 220) }
      }),
      tableVisible: Array.from(document.querySelectorAll('[data-patient-surface="table"]')).some(
        visible
      ),
      cardsVisible: Array.from(document.querySelectorAll('[data-patient-surface="cards"]')).some(
        visible
      ),
      empty: Boolean(document.querySelector('[data-state="empty-patients"]')),
      emptyText: text(document.querySelector('[data-state="empty-patients"]')),
      error: Boolean(document.querySelector('[data-state="error-patients"]')),
      errorText: text(document.querySelector('[data-state="error-patients"]')),
      retry: Boolean(document.querySelector('[data-retry-patients]')),
      loading: Boolean(document.querySelector('.loading-spinner')),
      loadingText: text(document.querySelector('.loading-spinner-container')),
      skeletons: document.querySelectorAll('[class*="skeleton"]').length,
      search: document.querySelector('input[placeholder^="Buscar por nombre"]')?.value ?? null,
      selects: selects.map(node => text(node)),
      resetControl: Boolean(
        filterCard?.querySelector('div.flex.flex-wrap.gap-3 > button:not([aria-haspopup])')
      ),
      pagination: text(document.querySelector('.pagination')),
      newPatient: Boolean(document.querySelector('[data-new-patient-button]')),
      heroActions: Array.from(document.querySelectorAll('[data-hero-actions] button')).map(node =>
        text(node).replace(/\s+/g, ' ')
      ),
      rowActions: rows[0]
        ? Array.from(rows[0].querySelectorAll('button'))
            .map(node => text(node).replace(/\s+/g, ' '))
            .filter(Boolean)
        : [],
      toast: Array.from(document.querySelectorAll('[aria-live]'))
        .map(text)
        .filter(Boolean)
        .join(' | ')
    }
  })

/**
 * Opens the `UiSelect` whose option list contains `optionLabel` and picks it.
 * The list page has no per-filter hook, so the option text is the identifier.
 */
export const chooseSelect = async (
  page,
  optionLabel,
  scope = 'section[data-patients-block="directory"]'
) => {
  const triggers = page.locator(`${scope} button[aria-haspopup="true"]`)
  const total = await triggers.count()

  for (let index = 0; index < total; index += 1) {
    const trigger = triggers.nth(index)

    if (!(await trigger.isVisible().catch(() => false))) continue
    if (
      !(await trigger
        .click({ timeout: 5000 })
        .then(() => true)
        .catch(() => false))
    )
      continue

    const listbox = page.locator('[role="listbox"]:visible').first()
    const opened = await listbox
      .waitFor({ state: 'visible', timeout: 3000 })
      .then(() => true)
      .catch(() => false)

    if (!opened) {
      await page.keyboard.press('Escape').catch(() => {})
      continue
    }

    const options = listbox.locator('button')
    const texts = (await options.allInnerTexts()).map(value => value.trim())
    const optionIndex = texts.findIndex(value => value === optionLabel)

    if (optionIndex !== -1) {
      await options.nth(optionIndex).click({ timeout: 5000 })
      return { ok: true, triggerIndex: index, selected: optionLabel, options: texts }
    }

    await page.keyboard.press('Escape').catch(() => {})
    await listbox.waitFor({ state: 'hidden', timeout: 2000 }).catch(() => {})
  }

  return { ok: false, reason: `option "${optionLabel}" not found`, triggers: total }
}

export const clickResetFilters = async page => {
  const reset = page
    .locator(
      'section[data-patients-block="directory"] div.flex.flex-wrap.gap-3 > button:not([aria-haspopup])'
    )
    .first()

  if (!(await reset.isVisible().catch(() => false))) {
    return { ok: false, reason: 'reset control not visible' }
  }

  const descriptor = await reset.evaluate(node => ({
    text: (node.innerText ?? '').trim(),
    ariaLabel: node.getAttribute('aria-label'),
    title: node.getAttribute('title'),
    hasIcon: Boolean(node.querySelector('svg'))
  }))

  await reset.click({ timeout: 5000 }).catch(() => {})
  return { ok: true, ...descriptor }
}

export const rowFor = (page, marker) =>
  page.locator('[data-patient-row]:visible').filter({ hasText: marker }).first()

export const readPanel = (page, state) =>
  page.evaluate(target => {
    const text = node => (node?.innerText ?? '').trim()
    const panel = document.querySelector(`[data-patient-tab-panel="${target}"]`)

    if (!panel) return null

    const empty = panel.querySelector('[data-state^="empty-"]')
    const error = panel.querySelector('[data-state^="error-"]')
    const spinner = panel.querySelector('.loading-spinner-container')

    return {
      state: panel.getAttribute('data-patient-tab-panel'),
      loading: Boolean(spinner),
      loadingText: text(spinner),
      emptyState: empty?.getAttribute('data-state') ?? null,
      emptyText: text(empty),
      errorState: error?.getAttribute('data-state') ?? null,
      errorText: text(error),
      rows: panel.querySelectorAll('tbody tr').length,
      contentLength: text(panel).length,
      text: text(panel).replace(/\s+/g, ' ').slice(0, 240)
    }
  }, state)

export const readDetailSnapshot = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim()
    const visible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const tabs = Array.from(document.querySelectorAll('[role="tab"]'))
      .filter(visible)
      .map(node => ({
        id: node.id.replace(/^tab-/, ''),
        label: text(node).replace(/\s+/g, ' '),
        selected: node.getAttribute('aria-selected') === 'true'
      }))
    const hero = document.querySelector('[data-patient-detail-hero]')

    return {
      url: window.location.pathname,
      h1: {
        count: document.querySelectorAll('h1').length,
        texts: Array.from(document.querySelectorAll('h1')).map(text)
      },
      hero: Boolean(hero),
      title: text(hero?.querySelector('.page-title')),
      identity: {
        document: text(hero?.querySelector('[data-patient-identity="document"]')),
        age: text(hero?.querySelector('[data-patient-identity="age"]')),
        status: text(hero?.querySelector('[data-patient-identity="status"]'))
      },
      tabs,
      tabCount: tabs.length,
      activeTab: tabs.find(tab => tab.selected)?.id ?? null,
      buttons: {
        edit: Boolean(document.querySelector('[data-edit-patient-button]')),
        export: Boolean(document.querySelector('[data-export-button]')),
        charge: Boolean(document.querySelector('[data-charge-patient-button]'))
      },
      toast: Array.from(document.querySelectorAll('[aria-live]'))
        .map(text)
        .filter(Boolean)
        .join(' | ')
    }
  })

export const readStatement = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim()
    const readValues = attr =>
      Object.fromEntries(
        Array.from(document.querySelectorAll(`[${attr}]`)).map(node => [
          node.getAttribute(attr),
          text(node)
        ])
      )
    const entries = Array.from(document.querySelectorAll('[data-statement-table] tbody tr')).map(
      row => {
        const cells = {}
        row.querySelectorAll('[data-statement-entry]').forEach(node => {
          cells[node.getAttribute('data-statement-entry')] = text(node)
        })
        return cells
      }
    )

    return {
      present: Boolean(document.querySelector('[data-patient-statement]')),
      period: {
        from: document.querySelector('[data-statement-date-from] input')?.value ?? null,
        to: document.querySelector('[data-statement-date-to] input')?.value ?? null
      },
      kpis: readValues('data-statement-kpi-value'),
      summary: readValues('data-statement-summary-value'),
      entries,
      loading: Boolean(document.querySelector('[data-statement-loading]')),
      loadingText: text(document.querySelector('[data-statement-loading]')),
      emptyState:
        document.querySelector('[data-patient-statement] [data-state="empty-cuenta"]') !== null,
      errorState:
        document.querySelector('[data-patient-statement] [data-state="error-cuenta"]') !== null,
      retry: Boolean(document.querySelector('[data-statement-retry]')),
      chargeButton: Boolean(document.querySelector('[data-statement-charge]'))
    }
  })

/** The statement arithmetic of #287, over the API payload. */
export const statementMath = payload => {
  const summary = payload?.summary ?? {}
  const opening = Number(payload?.opening_balance ?? 0)
  const closing = Number(payload?.closing_balance ?? 0)
  const entries = Array.isArray(payload?.entries) ? payload.entries : []
  const walk = entries.reduce((total, entry) => total + Number(entry?.effect ?? 0), opening)
  const formula =
    opening +
    Number(summary.charges ?? 0) -
    Number(summary.discounts ?? 0) -
    Number(summary.payments ?? 0) +
    Number(summary.refunds ?? 0) -
    Number(summary.write_offs ?? 0) +
    Number(summary.adjustments ?? 0)

  return {
    opening: round2(opening),
    closing: round2(closing),
    walk: round2(walk),
    formula: round2(formula),
    entries: entries.length,
    matches:
      Math.abs(round2(walk) - round2(closing)) < 0.005 &&
      Math.abs(round2(formula) - round2(closing)) < 0.005
  }
}
