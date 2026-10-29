// Plan #13 (issue #369, U6) — measurement helpers shared by the money rows.
//
// Plain readers and small drivers: no `test()` is registered here, so
// `--grep @U6` selects exactly the rows the session modules declare. Every
// reader returns what it observed; a probe that cannot observe returns an
// explicit `null` or `{ ok: false, reason }` so the caller records `NO PROBADO`
// instead of throwing.
//
// The PDF reader is part of this file on purpose: the machine has no
// `pdftotext`, `mutool`, `qpdf` or Python `pypdf` (U6 L6), so the bytes the API
// answers are inflated with `zlib` and the text operators are read here. A
// stream that does not inflate, or a money row that cannot be located, is a
// reason — never an invented comparison and never a fallback to the JSON.
//
// Evidence this session writes lives in `U6_EVIDENCE_DIR`; `.atl/` is
// gitignored and every JSON carries `generatedAt` plus the instrument revision.

import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { isAbsolute, resolve } from 'node:path'

import { BASE_URL, capture, settleLayout, writeEvidence } from '../harness.js'

export const U6_EVIDENCE_DIR = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U6'

export const QUOTATIONS_ROUTE = '/quotations'
export const PLANS_ROUTE = '/treatment-plans'

export const DESKTOP = { width: 1440, height: 900 }
export const MOBILE = { width: 390, height: 844 }

export const THRESHOLD = 120
export const API_TIMEOUT = 25000
export const LOAD_TIMEOUT = 25000
export const ACTION_TIMEOUT = 20000
export const TOAST_TIMEOUT = 10000
export const DIALOG_TIMEOUT = 4000

// Money comparison. The API and the PDF round to cents; one cent of tolerance
// absorbs the half-cent of a decomposed rate.
export const CENT_TOLERANCE = 1

export const audit = (filePath, payload) =>
  writeEvidence(filePath, payload, { dir: U6_EVIDENCE_DIR })
export const shot = (page, filePath) => capture(page, filePath, { dir: U6_EVIDENCE_DIR })

export const evidencePath = relPath =>
  isAbsolute(relPath) ? relPath : resolve(process.cwd(), U6_EVIDENCE_DIR, relPath)

/** Reads a file the session wrote under its own evidence directory. */
export const readEvidenceFile = relPath => readFileSync(evidencePath(relPath))

export const round2 = value => Math.round((Number(value) || 0) * 100) / 100

export const cents = value => Math.round((Number(value) || 0) * 100)

export const localDay = (date = new Date()) => {
  const pad = value => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export const addDaysLocal = (day, days) => {
  const [year, month, date] = day.split('-').map(Number)
  return localDay(new Date(year, month - 1, date + days))
}

export const sleep = ms => new Promise(resolve => setTimeout(resolve, ms))

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
  session: 'U6',
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

// --- money readers ----------------------------------------------------------

const MONEY = /(-)?\s*S\/\s*([\d.,]+)/

/** `S/ 1,234.56` -> 1234.56; `N/D`/`—` -> null. A leading minus is kept. */
export const parseCurrency = value => {
  const raw = String(value ?? '')
    .replace(/\u00a0/g, ' ')
    .trim()
  if (!raw || /N\/D|^—$|^-$/i.test(raw)) return null

  const match = raw.match(MONEY)
  const cleaned = (match ? `${match[1] ?? ''}${match[2]}` : raw.replace(/[^\d,.-]/g, ''))
    .replace(/,/g, '')
    .trim()

  if (!cleaned || cleaned === '-' || cleaned === '.') return null

  const amount = Number.parseFloat(cleaned)
  return Number.isFinite(amount) ? amount : null
}

export const moneyText = value => `S/ ${round2(value).toFixed(2)}`

/**
 * `base + igv == total` compared in cents.
 *
 * A missing operand is `NO PROBADO`, not a pass: the caller records the reason.
 */
export const moneyIdentity = ({ base, igv, total, tolerance = CENT_TOLERANCE } = {}) => {
  const missing = Object.entries({ base, igv, total })
    .filter(([, value]) => value === null || value === undefined || !Number.isFinite(value))
    .map(([key]) => key)

  if (missing.length) {
    return {
      ok: false,
      reason: `missing operands: ${missing.join(', ')}`,
      formula: 'base + igv = total',
      base,
      igv,
      total
    }
  }

  const baseCents = cents(base)
  const igvCents = cents(igv)
  const totalCents = cents(total)
  const diff = baseCents + igvCents - totalCents

  return {
    ok: Math.abs(diff) <= tolerance,
    formula: 'base + igv = total (cents)',
    baseCents,
    igvCents,
    totalCents,
    diffCents: diff,
    tolerance,
    base,
    igv,
    total
  }
}

/** `subtotal - discount == total`, the shape the create form paints. */
export const grossIdentity = ({ subtotal, discount, total }) => {
  const computed = cents(subtotal) - cents(Math.abs(discount ?? 0))

  return {
    ok: computed === cents(total),
    formula: 'subtotal - discount = total (cents)',
    subtotalCents: cents(subtotal),
    discountCents: cents(Math.abs(discount ?? 0)),
    computedCents: computed,
    totalCents: cents(total),
    diffCents: computed - cents(total)
  }
}

/**
 * The IGV the preview must show when it decomposes `total` at `rate` — the
 * same arithmetic `App\Support\QuotationTax::breakDown` does.
 */
export const expectedIgv = (total, rate) => {
  const net = round2(total)
  const percentage = Number(rate)
  if (!Number.isFinite(percentage) || percentage <= 0) return 0

  return round2(net - round2(net / (1 + percentage / 100)))
}

/** Reads every `dt`/`dd` pair of a root, keeping the visible text. */
export const readPairs = (page, selector) =>
  page.evaluate(root => {
    const rootNode = document.querySelector(root)
    if (!rootNode) return null

    return Array.from(rootNode.querySelectorAll('dl > div')).map(row => {
      const label = (row.querySelector('dt')?.innerText ?? '').trim()
      const valueNode = row.querySelector('dd')
      const value = (valueNode?.innerText ?? '').trim()

      return {
        label: label.replace(/\s+/g, ' '),
        value: value.replace(/\s+/g, ' '),
        hook:
          valueNode?.getAttribute('data-detail-subtotal') !== null
            ? 'data-detail-subtotal'
            : valueNode?.getAttribute('data-detail-taxable-base') !== null
              ? 'data-detail-taxable-base'
              : null
      }
    })
  }, selector)

/** The live summary of the create/edit form (`data-form-summary`). */
export const readFormSummary = page =>
  page.evaluate(() => {
    const read = selector => (document.querySelector(selector)?.innerText ?? '').trim()
    const root = document.querySelector('[data-form-summary]')

    if (!root) return null

    return {
      subtotal: read('[data-summary-subtotal]'),
      discount: read('[data-summary-discount]'),
      igv: read('[data-summary-tax]'),
      total: read('[data-summary-total]'),
      rate: document.querySelector('[data-field="tax_percentage"] input')?.value ?? '',
      ratePlaceholder: true
    }
  })

/**
 * The costs block of the quotation detail: the only screen that names the base
 * imponible, so this is where `base + igv = total` is read.
 */
export const readDetailCosts = async page => {
  const pairs = await readPairs(page, '[data-detail-section="costs"]')
  const byLabel = label => (pairs ?? []).find(row => new RegExp(label, 'i').test(row.label)) ?? null

  const subtotal = byLabel('^Subtotal')
  const discount = byLabel('^Descuento')
  const base = byLabel('^Base imponible')
  const igv = byLabel('^IGV')
  const total = byLabel('^Total')

  return {
    present: pairs !== null,
    pairs: pairs ?? [],
    values: {
      listPrice: parseCurrency(subtotal?.value),
      discount: parseCurrency(discount?.value),
      base: parseCurrency(base?.value),
      igv: parseCurrency(igv?.value),
      total: parseCurrency(total?.value)
    },
    raw: {
      subtotal: subtotal?.value ?? null,
      discount: discount?.value ?? null,
      base: base?.value ?? null,
      igv: igv?.value ?? null,
      total: total?.value ?? null
    }
  }
}

// --- quotation / plan readers ----------------------------------------------

/** The rows of `[data-quotations-table]`, with the actions each row exposes. */
export const readQuotationRows = page =>
  page.evaluate(() => {
    const isVisible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const read = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const root = document.querySelector('[data-quotations-table]')

    if (!root) {
      return {
        present: false,
        loading: Boolean(document.querySelector('[data-quotations-loading]')),
        error: Boolean(document.querySelector('[data-quotations-error]')),
        empty: Boolean(document.querySelector('[data-quotations-empty]')),
        rows: []
      }
    }

    const rows = Array.from(root.querySelectorAll('tbody tr')).filter(
      row => !row.querySelector('.data-table-cell-loading, .data-table-cell-empty')
    )

    return {
      present: true,
      loading: Boolean(document.querySelector('[data-quotations-loading]')),
      error: Boolean(document.querySelector('[data-quotations-error]')),
      empty: Boolean(document.querySelector('[data-quotations-empty]')),
      emptyText: read(document.querySelector('[data-quotations-empty]')),
      rows: rows.map(row => ({
        number: read(row.querySelector('[data-row-number]')),
        date: read(row.querySelector('[data-row-date]')),
        patient: read(row.querySelector('[data-row-patient]')),
        total: read(row.querySelector('[data-row-total]')),
        totalCents: (() => {
          const match = read(row.querySelector('[data-row-total]')).match(/S\/\s*([\d.,]+)/)
          return match ? Math.round(Number.parseFloat(match[1].replace(/,/g, '')) * 100) : null
        })(),
        status: read(row.querySelector('[data-row-status]')),
        expiry: read(row.querySelector('[data-row-expiry]')) || null,
        text: read(row),
        actions: Array.from(row.querySelectorAll('[data-row-action]')).map(button => ({
          action: button.getAttribute('data-row-action'),
          label: read(button),
          visible: isVisible(button),
          disabled: button.disabled === true
        }))
      }))
    }
  })

/** The cards of the plan list, with the footer actions each one renders. */
export const readPlanCards = page =>
  page.evaluate(() => {
    const isVisible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const read = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const STATUSES = ['Borrador', 'Propuesto', 'Aprobado', 'En Progreso', 'Completado', 'Cancelado']
    const cards = Array.from(document.querySelectorAll('[data-plan-card]'))

    return {
      present: cards.length > 0,
      count: cards.length,
      empty: Boolean(document.querySelector('[data-state="empty"]')),
      error: Boolean(document.querySelector('[data-state="error"]')),
      errorText: read(document.querySelector('[data-state="error"]')),
      skeleton: Boolean(document.querySelector('[aria-busy="true"]')),
      cards: cards.map(card => {
        const header = read(card.querySelector('header'))
        const status = STATUSES.find(label => header.includes(label)) ?? null
        const totalText = read(card.querySelector('[data-plan-cost]'))
        const match = totalText.match(/S\/\s*([\d.,]+)/)

        return {
          title: read(card.querySelector('[data-plan-title]')),
          patient: read(card.querySelector('[data-plan-patient]')),
          status,
          items: read(card.querySelector('[data-plan-items]')),
          total: totalText,
          totalCents: match
            ? Math.round(Number.parseFloat(match[1].replace(/,/g, '')) * 100)
            : null,
          overdue: Boolean(card.querySelector('[data-plan-overdue]')),
          progress: Boolean(card.querySelector('[data-plan-progress]')),
          actions: Array.from(card.querySelectorAll('[data-action], [data-status-toggle]')).map(
            button => ({
              action: button.getAttribute('data-action') ?? 'status-toggle',
              label: (button.getAttribute('aria-label') ?? '').trim() || read(button),
              visible: isVisible(button),
              disabled: button.disabled === true
            })
          )
        }
      })
    }
  })

/** The plan detail body: items table, totals and the sections it paints. */
export const readPlanDetail = page =>
  page.evaluate(() => {
    const read = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const body = document.querySelector('[data-detail-body]')

    if (!body) return null

    // The header lives in the modal's own header slot, outside the body div.
    const dialog = body.closest('[role="dialog"]') ?? body
    const rows = Array.from(body.querySelectorAll('[data-detail-item]')).map(row => {
      const cells = Array.from(row.querySelectorAll('td')).map(cell => read(cell))

      return { cells, text: read(row) }
    })

    const totalText = read(body.querySelector('[data-detail-total]'))
    const match = totalText.match(/S\/\s*([\d.,]+)/)

    return {
      title: read(dialog.querySelector('[data-detail-title]')),
      number: read(dialog.querySelector('[data-detail-number]')),
      items: rows,
      itemCount: rows.length,
      emptyText: read(body.querySelector('[data-detail-empty="procedures"]')),
      total: totalText,
      totalCents: match ? Math.round(Number.parseFloat(match[1].replace(/,/g, '')) * 100) : null,
      sections: Array.from(body.querySelectorAll('[data-detail-section]')).map(node =>
        node.getAttribute('data-detail-section')
      ),
      flags: Array.from(body.querySelectorAll('[data-flag]')).map(node =>
        node.getAttribute('data-flag')
      ),
      quotations: Array.from(body.querySelectorAll('[data-detail-quotation]')).length,
      text: read(body).slice(0, 4000)
    }
  })

/**
 * The visible dialogs, top last. `hook` names the consumer's data attribute on
 * the backdrop, `role` the node the contract measures.
 */
export const dialogStack = page =>
  page.evaluate(() => {
    const isVisible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 0 && box.height > 0)
    }
    const hooks = [
      'data-plan-detail',
      'data-plan-modal',
      'data-create-patient',
      'data-quotation-detail',
      'data-quotation-form',
      'data-quotation-approval',
      'data-quotation-reject'
    ]

    return Array.from(document.querySelectorAll('[role="dialog"], [role="alertdialog"]'))
      .filter(isVisible)
      .map(node => {
        const backdrop = node.closest(
          '[data-plan-detail], [data-plan-modal], [data-create-patient]'
        )
        const inner = node.querySelector(
          '[data-quotation-detail], [data-quotation-form], [data-quotation-approval], [data-quotation-reject]'
        )
        const own = hooks.find(hook => node.hasAttribute(hook))
        const hook =
          hooks.find(hook => backdrop?.hasAttribute(hook)) ??
          (inner ? hooks.find(hook => inner.hasAttribute(hook)) : own) ??
          null

        return {
          hook,
          role: node.getAttribute('role'),
          heading: (node.querySelector('h2, h1')?.innerText ?? '').trim().replace(/\s+/g, ' '),
          text: (node.innerText ?? '').trim().replace(/\s+/g, ' ').slice(0, 200)
        }
      })
  })

// --- drivers ----------------------------------------------------------------

export const settleQuotations = async (page, timeout = LOAD_TIMEOUT) => {
  await page
    .locator('[data-quotations-hero]')
    .waitFor({ state: 'visible', timeout })
    .catch(() => {})
  await page
    .waitForFunction(() => !document.querySelector('[data-quotations-loading]'), undefined, {
      timeout,
      polling: 120
    })
    .catch(() => {})
  await settleLayout(page)
}

export const settlePlans = async (page, timeout = LOAD_TIMEOUT) => {
  await page
    .locator('[data-treatment-plans-hero]')
    .waitFor({ state: 'visible', timeout })
    .catch(() => {})
  await page
    .waitForFunction(() => !document.querySelector('[aria-busy="true"]'), undefined, {
      timeout,
      polling: 120
    })
    .catch(() => {})
  await page
    .waitForFunction(() => !document.querySelector('.data-table-cell-loading'), undefined, {
      timeout,
      polling: 120
    })
    .catch(() => {})
  await settleLayout(page)
}

/**
 * Opens a `UiSelect` (`button[aria-haspopup]` + `[role="listbox"]` option
 * buttons) and picks the option whose text matches.
 *
 * Exact match first; the shipped labels carry suffixes, so a unique prefix is
 * accepted and reported. Anything else is a refusal.
 */
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
    // Escape closes the top overlay of the shared stack, so pressing it on a
    // select that lives inside a dialog destroys the form the caller is filling
    // and the next action waits for a control that no longer exists (#501: the
    // plans walk burned its 900 s budget this way). Only a select on the page
    // itself is dismissed here.
    const insideDialog = await locator
      .evaluate(node => Boolean(node.closest('[role="dialog"]')))
      .catch(() => false)

    if (!insideDialog) await page.keyboard.press('Escape').catch(() => {})

    return {
      ok: false,
      reason: insideDialog
        ? 'listbox did not open inside a dialog: the form was left as the user would have it'
        : 'listbox did not open'
    }
  }

  const optionButtons = listbox.locator('button')
  const labels = (await optionButtons.allInnerTexts()).map(value => value.trim())

  if (options.probeOnly === true || optionText === undefined) {
    await locator.click({ timeout: 3000, force: true }).catch(() => {})
    await listbox.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})
    return { ok: true, options: labels, probeOnly: true }
  }

  const wanted = String(optionText).trim()
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

/** Alert lines painted inside a root (the local validation of a form). */
export const alertsIn = (page, selector) =>
  page.evaluate(sel => {
    const root = document.querySelector(sel)
    if (!root) return null

    return Array.from(root.querySelectorAll('[role="alert"], p'))
      .map(node => (node.innerText ?? '').trim().replace(/\s+/g, ' '))
      .filter(line => line.length > 0 && line.length < 300)
  }, selector)

export const toastText = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]'))
      .map(node => (node.innerText ?? '').trim())
      .filter(Boolean)
      .join(' | ')
  )

export const waitForToast = async (page, expected, timeout = TOAST_TIMEOUT) => {
  const matcher = expected ?? null

  await page
    .waitForFunction(
      needle =>
        Array.from(document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]')).some(
          node => (needle ? node.innerText.includes(needle) : node.innerText.trim().length > 0)
        ),
      matcher,
      { timeout, polling: 100 }
    )
    .catch(() => {})

  return toastText(page)
}

export const textContains = async (page, needle) =>
  page.evaluate(value => (document.body.innerText ?? '').includes(value), String(needle))

export const closeTopDialog = async page => {
  const dialog = page.locator('[role="dialog"]:visible').last()

  if (!(await dialog.count().catch(() => 0))) return { ok: true, closed: false }
  if (!(await dialog.isVisible().catch(() => false))) return { ok: true, closed: false }

  await page.keyboard.press('Escape').catch(() => {})
  const closed = await dialog
    .waitFor({ state: 'hidden', timeout: DIALOG_TIMEOUT })
    .then(() => true)
    .catch(() => false)

  return { ok: closed, closed }
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

/** Waits for one request/response pair on a pathname pattern. */
export const waitForCall = (
  page,
  method,
  pattern,
  predicate = () => true,
  timeout = ACTION_TIMEOUT
) => {
  const regex = pattern instanceof RegExp ? pattern : new RegExp(`${pattern}$`)

  return page
    .waitForResponse(
      response => {
        if (response.request().method() !== method) return false
        try {
          const url = new URL(response.url())
          if (!regex.test(url.pathname)) return false
          return predicate(url.searchParams, url)
        } catch {
          return false
        }
      },
      { timeout }
    )
    .catch(() => null)
}

/** Delays matching routes so a loading state is observable, not inferred. */
export const delayRoute = async (page, matcher, delayMs = 900) => {
  let delay = delayMs

  const handler = async route => {
    if (delay > 0) await sleep(delay)
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
  const payload = options.body ?? { message: options.message ?? 'AUDIT-U6 forced failure' }

  const handler = route =>
    route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(payload) })

  await page.route(matcher, handler)

  return {
    stop: async () => {
      await page.unroute(matcher, handler).catch(() => {})
    }
  }
}

/**
 * Moves instrument-induced failures out of `unexpected` with their reason: a
 * probe that forces a 500 or submits a body the API must refuse is exercising
 * a state, not measuring a surface defect.
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
