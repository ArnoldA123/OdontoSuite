// Plan #13 (issue #373, U10) — measurement helpers shared by the session rows.
//
// Plain readers and small drivers: no `test()` is registered here, so
// `--grep @U10` selects exactly the rows the session modules declare. Every
// reader returns what it observed; a reader that cannot observe a node returns
// `null` or an explicit reason so the caller records `NO PROBADO` instead of
// throwing.
//
// The session covers three desks — Business Intelligence, continuity and the
// prosthetic laboratory — so the helpers of layer 4 are joined here by the thin
// readers the BI surface needs (report selector, date range, apply, table,
// download and the route interception used to force the error state). They
// observe and never assert: a contract violation is evidence, not an exception.
//
// Evidence this session writes lives in `EVIDENCE`; `.atl/` is gitignored and
// every JSON carries `generatedAt` plus the instrument revision. The API helpers
// share one `APIRequestContext` so `login()` reuses the harness memoizer instead
// of opening a second session per row (the login endpoint throttles per user).

import { execFileSync } from 'node:child_process'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, isAbsolute, resolve } from 'node:path'

import { expect, request as apiRequest } from 'playwright/test'

import { loginAs, rolePlans, settleLayout } from '../harness.js'

export const SESSION = 'U10'

export const EVIDENCE = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U10'

export const BASE = process.env.BROWSER_PASS_BASE || 'http://localhost:8000'

export const DESKTOP = { width: 1440, height: 900 }
export const MOBILE = { width: 390, height: 844 }

// Smallest rendered-content volume a surface has to clear to count as painted.
export const THRESHOLD = 80

export const DIALOG_SELECTOR = '[role="dialog"]:visible, [role="alertdialog"]:visible'

// The seven seeded roles the layer-5 matrix measures. The three surfaces of the
// session split them: BI belongs to administration and finance, continuity and
// the laboratory to the appointment desks, and each surface has a role that
// cannot read it at all.
export const ROLES = Object.freeze([
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
])

// The labels S5 counts as a write affordance on a surface that must be
// read-only for the role being measured. The three BI exports are the money
// surface's write-shaped affordance: a role without `export.report` may press
// them and the API refuses, which is exactly what the row records.
export const WRITE_AFFORDANCE =
  /nuevo|nueva|crear|editar|eliminar|agendar|descartar|programar|guardar|pasar a|excel|csv|pdf/i

// The BI surface: the report select lives inside the filters card, the two date
// inputs and the apply button are its own hooks, and the report table is the
// `UiDataTable` that received `data-report-table` (so the hook sits on its root
// container, not on the `<table>`).
export const BI = Object.freeze({
  reportSelect: '[data-filters-report]',
  picker: '[data-filters-report] > button',
  from: '[data-filters-from]',
  to: '[data-filters-to]',
  apply: '[data-filters-apply]',
  reset: '[data-filters-reset]',
  presets: '[data-preset]',
  branch: '[data-filters-branch]',
  professional: '[data-filters-professional]',
  environment: '[data-filters-environment]',
  table: '[data-report-table]',
  error: '[data-state="error"]',
  empty: '[data-report-empty]',
  period: '[data-period-applied]',
  dimensions: '[data-revenue-dimension]',
  kpis: '[data-bi-kpis]'
})

// The endpoint pattern every report read (and the export) answers through.
export const REPORT_PATH = /\/api\/reports\//

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

/** The commit the instrument was measured at, read once at import time. */
export const RUN_REVISION = revision()

/** Metadata every evidence file carries. */
export const stamp = () => ({
  session: SESSION,
  revision: RUN_REVISION,
  base: BASE,
  generatedAt: new Date().toISOString()
})

export const evidencePath = relPath =>
  isAbsolute(relPath) ? relPath : resolve(process.cwd(), EVIDENCE, relPath)

export const writeEvidence = (relPath, payload) => {
  const absolute = evidencePath(relPath)
  mkdirSync(dirname(absolute), { recursive: true })
  writeFileSync(absolute, `${JSON.stringify(payload, null, 2)}\n`)
  return relPath
}

export const readEvidence = relPath => {
  try {
    return JSON.parse(readFileSync(evidencePath(relPath), 'utf8'))
  } catch {
    return null
  }
}

export const hasEvidence = relPath => existsSync(evidencePath(relPath))

/** Captures are always explicit: the config keeps `only-on-failure`. */
export const shot = async (page, relPath) => {
  const absolute = evidencePath(relPath)
  mkdirSync(dirname(absolute), { recursive: true })
  await page.screenshot({ path: absolute, fullPage: true })
  return relPath
}

/** Writes `<name>.json` with the session stamp merged under the payload. */
export const audit = (name, payload = {}) =>
  writeEvidence(`${name}.json`, { ...stamp(), ...payload })

const fileSlug = value =>
  String(value)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'step'

/** Captures one walkthrough step and returns its repository-relative path. */
export const captureStep = (page, slug, name) =>
  shot(page, `${fileSlug(slug)}/${fileSlug(name)}.png`)

/** `rolePlans` from the harness, re-exported so every row derives the plan once. */
export const rolePlanFor = (surface, maxRoles = 3) => rolePlans(surface, maxRoles)

/** The layer-1 manifest, when the run wrote one next to the evidence. */
export const loadSessionManifest = () => {
  const configured = process.env.AUDIT_MANIFEST
  const candidates = [configured, evidencePath('manifest.json')].filter(Boolean)

  for (const candidate of candidates) {
    const absolute = isAbsolute(candidate) ? candidate : resolve(process.cwd(), candidate)

    try {
      if (existsSync(absolute)) return JSON.parse(readFileSync(absolute, 'utf8'))
    } catch {
      // A malformed manifest is reported by the caller as an absent one.
    }
  }

  return null
}

// --- API --------------------------------------------------------------------

let client = null

/** The one `APIRequestContext` the session shares (tokens, throttle, failures). */
export const apiClient = async () => {
  client ??= await apiRequest.newContext({
    baseURL: BASE,
    extraHTTPHeaders: { Accept: 'application/json' }
  })

  return client
}

const sessions = new Map()

/** Authenticates a role once per worker; `loginAs` owns the memoized token. */
export const login = role => {
  if (!sessions.has(role)) {
    sessions.set(
      role,
      apiClient().then(context => loginAs(context, role))
    )
  }

  return sessions.get(role)
}

/** Real tokens for the seven matrix roles, in `ROLES` order. */
export const roleSessions = async () => {
  const pairs = []

  for (const role of ROLES) pairs.push([role, await login(role)])

  return Object.fromEntries(pairs)
}

/**
 * One authenticated API call. A network or parse failure becomes
 * `{ status: null, body: null }`, never an exception: an unreachable probe is
 * evidence, not a crashed test.
 */
export const api = async (session, method, path, body) => {
  const context = await apiClient()

  try {
    const response = await context.fetch(path, {
      method,
      headers: {
        Authorization: `Bearer ${session.token}`,
        Accept: 'application/json',
        ...(body !== undefined ? { 'Content-Type': 'application/json' } : {})
      },
      data: body,
      timeout: 30000
    })
    const contentType = response.headers()['content-type'] ?? ''
    const parsed = contentType.includes('json')
      ? await response.json().catch(() => null)
      : await response.text().catch(() => null)

    return { status: response.status(), body: parsed }
  } catch {
    return { status: null, body: null }
  }
}

/** Asserts an observed status against one or more acceptable values. */
export const expectStatus = (result, expected, note = 'status') => {
  const statuses = Array.isArray(expected) ? expected : [expected]
  expect(statuses, `${note} (observed ${result.status})`).toContain(result.status)
  return result
}

// --- UI ---------------------------------------------------------------------

/** Waits for the shell and two layout frames; never for a payload. */
export const settle = async (page, timeout = 6000) => {
  await page
    .locator('main')
    .waitFor({ state: 'visible', timeout })
    .catch(() => {})

  // The loading placeholder clears the content threshold on its own, so a
  // measurement taken before it disappears records the skeleton as the surface.
  // The detail pages call it "Cargando..." with no module name.
  await page
    .waitForFunction(
      () => !/\bCargando\b/i.test(document.querySelector('main')?.innerText ?? ''),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})

  // The list surfaces paint a text-free skeleton before their payload lands
  // (`data-<module>-loading` or `aria-busy`). Neither clears the content
  // threshold, so the row waits for the marker itself.
  await page
    .waitForFunction(
      () => {
        const visible = node => {
          const box = node.getBoundingClientRect()
          return box.width > 0 && box.height > 0
        }

        return !Array.from(document.querySelectorAll('main *')).some(node => {
          if (!visible(node)) return false
          if (node.getAttribute('aria-busy') === 'true') return true

          return Array.from(node.attributes).some(attribute =>
            /^data-.*loading$/.test(attribute.name)
          )
        })
      },
      undefined,
      { timeout, polling: 150 }
    )
    .catch(() => {})

  await settleLayout(page)
}

/** Navigates to a surface, waits for `main` and settles the paint. */
export const gotoSurface = async (page, href, timeout = 20000) => {
  const response = await page.goto(href, { waitUntil: 'domcontentloaded', timeout })
  await settle(page)

  return { status: response?.status() ?? null, url: page.url() }
}

export const rowCount = (page, selector) => page.locator(selector).count()

/** Visible text of every matching node, normalized, empty entries dropped. */
export const visibleTexts = (page, selector) =>
  page.evaluate(sel => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      const box = node.getBoundingClientRect()
      return box.width > 0 && box.height > 0
    }

    return Array.from(document.querySelectorAll(sel)).filter(visible).map(text).filter(Boolean)
  }, selector)

/** Clicks the first visible enabled control whose text contains `label`. */
export const clickByLabel = async (page, label, timeout = 8000) => {
  const candidates = page.locator('button, a, [role="button"]').filter({ hasText: label })

  try {
    const total = await candidates.count()

    for (let index = 0; index < total; index += 1) {
      const node = candidates.nth(index)
      if (!(await node.isVisible().catch(() => false))) continue
      if (!(await node.isEnabled().catch(() => false))) continue
      await node.click({ timeout })
      await settleLayout(page)
      return { ok: true, label, index }
    }
  } catch (thrown) {
    return { ok: false, label, reason: String(thrown?.message ?? thrown).slice(0, 200) }
  }

  return { ok: false, label, reason: 'no visible enabled control carries that label' }
}

/**
 * Fills a control by its label (UiInput renders `label[for]`), falling back to
 * the placeholder so a field without a floating label is still addressable.
 */
export const fillField = async (page, label, value) => {
  const byLabel = page.getByLabel(label, { exact: false })
  const labelCount = await byLabel.count().catch(() => 0)

  for (let index = 0; index < labelCount; index += 1) {
    const node = byLabel.nth(index)

    if (!(await node.isVisible().catch(() => false))) continue

    // `getByLabel` also matches an `aria-label` on a plain node: a formatted
    // cell can carry one, and filling it would silently leave the real field
    // empty. Only form controls are addressable.
    const tag = await node.evaluate(element => element.tagName.toLowerCase()).catch(() => '')

    if (!['input', 'textarea', 'select'].includes(tag)) continue

    await node.fill(String(value)).catch(() => {})
    await settleLayout(page)
    return { ok: true, label, value: await node.inputValue().catch(() => null), via: 'label' }
  }

  const byPlaceholder = page.getByPlaceholder(label, { exact: false })
  const placeholderCount = await byPlaceholder.count().catch(() => 0)

  for (let index = 0; index < placeholderCount; index += 1) {
    const node = byPlaceholder.nth(index)

    if (!(await node.isVisible().catch(() => false))) continue

    await node.fill(String(value)).catch(() => {})
    await settleLayout(page)
    return { ok: true, label, value: await node.inputValue().catch(() => null), via: 'placeholder' }
  }

  return { ok: false, label, reason: 'no visible labelled control' }
}

/** The submit control of the open dialog: a submit button, else a primary label. */
export const submitDialog = async page => {
  const dialog = page.locator(DIALOG_SELECTOR).last()

  if (!(await dialog.count().catch(() => 0))) {
    return { ok: false, reason: 'no visible dialog' }
  }

  const byType = dialog.locator('button[type="submit"]:visible').first()

  if (await byType.count().catch(() => 0)) {
    const label = ((await byType.innerText().catch(() => '')) || '').trim()
    await byType.click({ timeout: 8000 }).catch(() => {})
    return { ok: true, label, via: 'submit' }
  }

  const byLabel = dialog
    .locator('button:visible')
    .filter({
      hasText:
        /guardar|crear|actualizar|fijar|enviar|aprobar|rechazar|aceptar|confirmar|agendar|descartar/i
    })
    .last()

  if (await byLabel.count().catch(() => 0)) {
    const label = ((await byLabel.innerText().catch(() => '')) || '').trim()
    await byLabel.click({ timeout: 8000 }).catch(() => {})
    return { ok: true, label, via: 'label' }
  }

  return { ok: false, reason: 'the dialog has no submit affordance' }
}

/** Opens the first label whose click paints a dialog; returns it, or a reason. */
export const openDialogBy = async (page, labels, timeout = 3000) => {
  for (const label of labels ?? []) {
    const trigger = await clickByLabel(page, label)

    if (!trigger.ok) continue

    const dialog = page.locator(DIALOG_SELECTOR).last()
    const opened = await dialog
      .waitFor({ state: 'visible', timeout })
      .then(() => true)
      .catch(() => false)

    if (opened) return { label, dialog, trigger }
  }

  return { label: null, dialog: null, reason: 'no supplied label opened a visible dialog' }
}

/** Clicks a button of the open confirmation whose text matches `text`. */
export const confirmDialog = async (page, text, timeout = 8000) => {
  const dialog = page.locator('[role="alertdialog"]:visible').last()

  if (!(await dialog.count().catch(() => 0))) {
    return { ok: false, reason: 'no confirmation dialog is open' }
  }

  const dialogText = ((await dialog.innerText().catch(() => '')) || '').trim().replace(/\s+/g, ' ')
  const buttons = dialog.locator('button:visible')
  const total = await buttons.count().catch(() => 0)
  const wanted = new RegExp(text, 'i')

  for (let index = 0; index < total; index += 1) {
    const button = buttons.nth(index)
    const label = ((await button.innerText().catch(() => '')) || '').trim()
    if (!wanted.test(label)) continue

    await button.click({ timeout })
    await dialog.waitFor({ state: 'hidden', timeout }).catch(() => {})
    return { ok: true, label, dialogText }
  }

  return { ok: false, reason: `no confirmation button matches "${text}"`, dialogText }
}

/**
 * The text of the visible toasts only.
 *
 * `UiStatusBadge` also renders `role="status"`, so the toast root is the element
 * that carries its own close button instead of every live region on the page.
 */
export const toastText = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const messages = []

    // `Toast.vue`, the design-system primitive: its closer names itself.
    const roots = new Set()

    for (const closer of document.querySelectorAll('button[aria-label="Cerrar notificación"]')) {
      const root = closer.parentElement?.parentElement
      if (root) roots.add(root)
    }

    for (const live of document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]')) {
      if (live.querySelector('button[aria-label="Cerrar notificación"]')) roots.add(live)
    }

    for (const root of roots) messages.push(text(root))

    // `ToastContainer.vue` renders the `useToast()` queue: one card per toast,
    // closed by a button whose accessible name is an `sr-only` «Cerrar», so it
    // carries no `aria-label` to address. Only its paragraphs are the message.
    for (const card of document.querySelectorAll('.pointer-events-auto.max-w-sm')) {
      const content = Array.from(card.querySelectorAll('p')).map(text).filter(Boolean).join(' ')

      if (content) messages.push(content)
    }

    // `NotificationToast.vue` renders the `useNotifications()` queue.
    for (const item of document.querySelectorAll('.notification-item')) {
      const content = text(item.querySelector('.notification-message')) || text(item)
      if (content) messages.push(content)
    }

    // Without these roots the session could not observe the feedback any of the
    // nine pages under audit raises.
    return [...new Set(messages.filter(Boolean))].join(' | ')
  })

/**
 * The inline error the page paints in its own block or field.
 *
 * The pages name their hooks per module (`data-continuity-error`,
 * `data-lab-cases-error`, `data-state="error"`), so the scan reads the hook
 * itself instead of a fixed list: the evidence keeps which node answered.
 */
export const inlineError = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      const box = node.getBoundingClientRect()
      return box.width > 0 && box.height > 0
    }
    const nodes = document.querySelectorAll('main *, [role="dialog"] *')
    const found = []
    let inspected = 0

    for (const node of nodes) {
      inspected += 1
      if (inspected > 6000) break
      if (!visible(node)) continue

      const hook = Array.from(node.attributes).find(
        attribute =>
          /^data-.*error/.test(attribute.name) ||
          (attribute.name === 'data-state' && /error/i.test(attribute.value))
      )

      if (!hook) continue

      found.push({ hook: `${hook.name}="${hook.value}"`, text: text(node) })
    }

    return found.length
      ? {
          text: found
            .map(entry => entry.text)
            .filter(Boolean)
            .join(' | '),
          hooks: found
        }
      : { text: null, hooks: [] }
  })

export const tableHeaders = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('main thead th'))
      .map(node => (node.innerText ?? '').trim().replace(/\s+/g, ' '))
      .filter(Boolean)
  )

export const overflow = page =>
  page.evaluate(() => ({
    overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    scrollWidth: document.documentElement.scrollWidth,
    innerWidth: window.innerWidth
  }))

/** `{ count, texts }` of the document's `h1`s: layer 3 asks for exactly one. */
export const headings = page =>
  page.evaluate(() => {
    const text = node => (node.innerText ?? '').trim().replace(/\s+/g, ' ')
    const nodes = Array.from(document.querySelectorAll('h1'))

    return { count: nodes.length, texts: nodes.map(text).filter(Boolean) }
  })

export const headingCount = page => headings(page).then(result => result.count)

/** Visible write affordances, labelled by text, aria-label or title. */
export const writeAffordances = page =>
  page.evaluate(source => {
    const pattern = new RegExp(source, 'i')
    const visible = node => {
      const box = node.getBoundingClientRect()
      return box.width > 0 && box.height > 0
    }
    const nodes = document.querySelectorAll(
      'main button, main a, [role="dialog"] button, [role="alertdialog"] button'
    )
    const labels = []

    for (const node of nodes) {
      if (!visible(node)) continue

      const label = (
        (node.innerText ?? '') ||
        node.getAttribute('aria-label') ||
        node.getAttribute('title') ||
        ''
      )
        .trim()
        .replace(/\s+/g, ' ')

      if (!label || !pattern.test(label)) continue
      if (!labels.some(entry => entry.label === label)) labels.push({ label })
    }

    return labels
  }, WRITE_AFFORDANCE.source)

// --- Business Intelligence readers -------------------------------------------

/**
 * Opens the report `UiSelect` and picks the option whose text equals `label`.
 *
 * The trigger is the primitive's own `<button>` inside the page's hook (the
 * `label` prop of `UiSelect` is not rendered, so the control has no accessible
 * name to address); the option is matched by exact text first, then by
 * containment, exactly like `pickOption`. Selection fires the page's own
 * `@change` → `applyFilters`, so nothing is asserted here: the caller measures
 * the request that follows.
 */
export const selectReport = async (page, label, timeout = 8000) => {
  const picked = await pickOption(page, BI.picker, label, timeout)
  const shown = (
    (await page
      .locator(BI.picker)
      .first()
      .innerText()
      .catch(() => '')) || ''
  )
    .trim()
    .replace(/\s+/g, ' ')

  if (!picked.ok) {
    return { ok: false, reason: picked.reason ?? 'the report was not selected', label }
  }

  return {
    ok: shown === String(label).trim(),
    reason: shown === String(label).trim() ? null : `the trigger shows "${shown}"`,
    label,
    options: picked.options ?? []
  }
}

/**
 * Writes the two date inputs of the filters card.
 *
 * `UiInput` renders `<input type="date">`, whose value is the `YYYY-MM-DD` the
 * page already speaks; the reader returns the value the input actually holds so
 * a rejected fill is visible in the evidence.
 */
export const setDateRange = async (page, from, to) => {
  const write = async (container, value) => {
    const input = page.locator(`${container} input`).first()

    if (!(await input.count().catch(() => 0))) {
      return { ok: false, value: null, reason: `no input inside ${container}` }
    }

    await input.fill(String(value)).catch(() => {})
    await settleLayout(page)

    return { ok: true, value: await input.inputValue().catch(() => null) }
  }

  const fromWrite = await write(BI.from, from)
  const toWrite = await write(BI.to, to)
  const ok = fromWrite.value === String(from) && toWrite.value === String(to)

  return {
    ok,
    from: fromWrite.value,
    to: toWrite.value,
    reason: ok ? null : (fromWrite.reason ?? toWrite.reason ?? 'the inputs hold another value')
  }
}

/**
 * Presses `[data-filters-apply]` and waits for the report request it triggers.
 *
 * A disabled button is a measured state, not an error: the page disables it
 * while a range is invalid or a request is in flight.
 */
export const applyFilters = async (page, timeout = 20000) => {
  const button = page.locator(BI.apply).first()

  if (!(await button.count().catch(() => 0))) {
    return { ok: false, reason: 'the filter card has no apply control', status: null }
  }

  if (!(await button.isEnabled().catch(() => false))) {
    return { ok: false, reason: 'the apply control is disabled', status: null }
  }

  const result = await respondTo(
    page,
    { method: 'GET', path: REPORT_PATH },
    () => button.click({ timeout: 8000 }),
    timeout
  )

  return {
    ok: result.status !== null,
    reason: result.status === null ? 'no report request settled' : null,
    status: result.status,
    path: result.path,
    message: result.body?.message ?? null
  }
}

/** The visible report table as arrays of trimmed cell texts. */
export const reportRows = page =>
  page.evaluate(selector => {
    const text = node => (node.innerText ?? '').trim().replace(/\s+/g, ' ')

    return Array.from(document.querySelectorAll(`${selector} tbody tr`))
      .map(row => Array.from(row.querySelectorAll('th, td')).map(text))
      .filter(cells => cells.some(Boolean))
  }, BI.table)

/** The visible header labels of the report table. */
export const reportHeaders = page =>
  page.evaluate(
    selector =>
      Array.from(document.querySelectorAll(`${selector} thead th`))
        .map(node => (node.innerText ?? '').trim().replace(/\s+/g, ' '))
        .filter(Boolean),
    BI.table
  )

/**
 * Clicks an export trigger and returns the file the browser received.
 *
 * `body` is the downloaded file decoded as UTF-8 (CSV and the SpreadsheetML
 * `.xls` are text; a PDF decodes to noise, which is why `bytes` travels next to
 * it). `status` is the export response when the browser exposed it, so a door
 * that answered 500 before the file is visible in the row.
 */
export const waitDownload = async (page, triggerSelector, timeout = 20000) => {
  const trigger = page.locator(triggerSelector).first()

  if (!(await trigger.count().catch(() => 0))) {
    return { ok: false, reason: 'no download trigger', filename: null, body: null, status: null }
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
      reason: clicked.ok ? 'the trigger produced no download event' : clicked.reason,
      filename: null,
      body: null,
      bytes: null,
      status
    }
  }

  const localPath = await download.path().catch(() => null)
  let body = null
  let bytes = null

  if (localPath) {
    try {
      const buffer = readFileSync(localPath)
      bytes = buffer.length
      body = buffer.toString('utf8').slice(0, 8000)
    } catch {
      body = null
    }
  }

  return {
    ok: Boolean(localPath),
    reason: localPath ? null : 'the download has no local path',
    filename: download.suggestedFilename(),
    body,
    bytes,
    status,
    failure: await download.failure().catch(() => null)
  }
}

const reportRoutes = new WeakMap()

/**
 * Forces the report read to answer `status` so the error state can be measured.
 *
 * Playwright's `page.route` fulfils the request before the app sees it, which is
 * the only way to paint `DashboardSectionError` deterministically. The pattern
 * is kept per page so `releaseReport` can lift exactly what was installed.
 */
export const interceptReport = async (page, pattern = REPORT_PATH, status = 500) => {
  const routePattern = pattern ?? REPORT_PATH

  await page.route(routePattern, route =>
    route.fulfill({
      status,
      contentType: 'application/json',
      body: JSON.stringify({ message: `AUDIT-U10 forced HTTP ${status}`, forced: true })
    })
  )

  const installed = reportRoutes.get(page) ?? []
  installed.push(routePattern)
  reportRoutes.set(page, installed)

  return { ok: true, pattern: String(routePattern), status }
}

/** Lifts every interception `interceptReport` installed on this page. */
export const releaseReport = async page => {
  const installed = reportRoutes.get(page) ?? []

  for (const pattern of installed) {
    await page.unroute(pattern).catch(() => {})
  }

  reportRoutes.delete(page)

  return { ok: true, released: installed.length }
}

// --- diagnostics ------------------------------------------------------------

/**
 * Console errors with a declared, environmental cause.
 *
 * Reverb is not part of the audit environment: every surface boots Echo, the
 * socket retry fails and the browser logs it. It is reported (never hidden) and
 * counted separately from the surface's own errors.
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

/** Console errors, classified; a negative row provokes its own 401/403. */
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

/**
 * Dismisses the toasts a probe triggered so they do not cover the next control.
 *
 * The three toast roots of the shell are addressed: the primitive's labelled
 * closer, the `useToast()` card's sr-only closer and the `useNotifications()`
 * item, which removes itself on click.
 */
export const dismissToasts = async page => {
  const closers = page.locator(
    'button[aria-label="Cerrar notificación"]:visible, .pointer-events-auto.max-w-sm button:visible, .notification-item:visible'
  )
  let dismissed = 0

  for (let attempt = 0; attempt < 8; attempt += 1) {
    if ((await closers.count().catch(() => 0)) === 0) break
    await closers
      .first()
      .click({ timeout: 2000 })
      .catch(() => {})
    dismissed += 1
    await page.waitForTimeout(100)
  }

  return dismissed
}

/** Closes the top dialog (Escape first, footer Cancel as the fallback). */
export const closeDialog = async page => {
  const dialog = page.locator(DIALOG_SELECTOR).last()

  if (!(await dialog.count().catch(() => 0))) return { ok: true, closed: false }
  if (!(await dialog.isVisible().catch(() => false))) return { ok: true, closed: false }

  await page.keyboard.press('Escape').catch(() => {})

  let closed = await dialog
    .waitFor({ state: 'hidden', timeout: 3000 })
    .then(() => true)
    .catch(() => false)

  if (!closed) {
    const cancel = dialog
      .locator('button:visible')
      .filter({ hasText: /cancelar/i })
      .first()
    await cancel.click({ timeout: 3000 }).catch(() => {})
    closed = await dialog
      .waitFor({ state: 'hidden', timeout: 3000 })
      .then(() => true)
      .catch(() => false)
  }

  return { ok: closed, closed }
}

/** Every visible dialog, bottom first, with its measured box. */
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
          box: {
            x: Math.round(box.x),
            y: Math.round(box.y),
            width: Math.round(box.width),
            height: Math.round(box.height)
          }
        }
      })
  })

// --- layer 4: the functional walks ------------------------------------------

/** The five layer-4 verdicts, in the plan's order. */
export const VERDICTS = Object.freeze(['OK', 'DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO'])

/**
 * The page's own `/api/` traffic, paired request → response status.
 *
 * The walkthrough reports `request` as observed, not inferred: a step reads this
 * log instead of reconstructing the call it thinks the page made. A response is
 * paired with the oldest unanswered request of the same method and path, which
 * is the one that resolved (a report read issues several in flight).
 */
export const watchRequests = page => {
  const entries = []

  page.on('request', request => {
    if (!request.url().includes('/api/')) return

    let url
    try {
      url = new URL(request.url())
    } catch {
      return
    }

    entries.push({
      method: request.method(),
      path: url.pathname,
      query: Object.fromEntries(url.searchParams.entries()),
      status: null
    })
  })

  page.on('response', response => {
    if (!response.url().includes('/api/')) return

    let path
    try {
      path = new URL(response.url()).pathname
    } catch {
      return
    }

    const method = response.request().method()
    const entry = entries.find(
      row => row.path === path && row.method === method && row.status === null
    )

    if (entry) entry.status = response.status()
  })

  return entries
}

/** The last matching entry of the page's traffic, or `null`. */
export const lastCall = (entries, method, path) =>
  [...(entries ?? [])].reverse().find(entry => entry.method === method && entry.path === path) ??
  null

const matchesCall = (match, method, pathname, query) => {
  if (match.method && match.method !== method) return false
  if (match.path instanceof RegExp) {
    if (!match.path.test(pathname)) return false
  } else if (match.path && match.path !== pathname) return false
  if (match.query) {
    for (const [key, value] of Object.entries(match.query)) {
      if (query[key] !== value) return false
    }
  }
  return true
}

/**
 * Runs a UI action and returns the response it triggered.
 *
 * A response that never lands is `{ status: null }`: the step records "no
 * request", never an exception. The body is parsed when the API answered JSON so
 * the step can quote the message the surface received.
 */
export const respondTo = async (page, match, action, timeout = 15000) => {
  const pending = page
    .waitForResponse(
      candidate => {
        if (!candidate.url().includes('/api/')) return false

        try {
          const url = new URL(candidate.url())
          return matchesCall(
            match,
            candidate.request().method(),
            url.pathname,
            Object.fromEntries(url.searchParams.entries())
          )
        } catch {
          return false
        }
      },
      { timeout }
    )
    .catch(() => null)

  const acted = await Promise.resolve()
    .then(action)
    .then(
      () => ({ ok: true }),
      thrown => ({ ok: false, reason: String(thrown?.message ?? thrown).slice(0, 200) })
    )
  const response = await pending

  if (!response)
    return { acted, status: null, body: null, method: match.method ?? null, path: null }

  const contentType = response.headers()['content-type'] ?? ''
  const body = contentType.includes('json') ? await response.json().catch(() => null) : null

  return {
    acted,
    status: response.status(),
    body,
    method: response.request().method(),
    path: new URL(response.url()).pathname
  }
}

/** The request evidence of a `respondTo` result, in the walk's declared shape. */
export const requestOf = result =>
  result && result.status !== null
    ? { method: result.method, path: result.path, status: result.status }
    : null

/**
 * The step recorder of a walkthrough.
 *
 * One entry per declared action, with its capture; the step id carries its own
 * sequence (`01-list`). A probe that throws is recorded as `NO PROBADO` with the
 * reason instead of ending the walk: the run's hard assertions are "no pageerror"
 * and "every declared step recorded", so a defect stays evidence.
 */
export const walkStep = (page, evidence) => {
  let index = 0

  return async (id, action, expected, run) => {
    index += 1
    const stepId = `${String(index).padStart(2, '0')}-${id}`
    const entry = {
      id: stepId,
      action,
      expected,
      obtained: null,
      verdict: 'NO PROBADO',
      layer: 4,
      origin: 'MEDIDO',
      request: null,
      shot: null,
      notes: ''
    }

    let broken = false

    await dismissToasts(page).catch(() => {})

    try {
      const result = (await run(entry)) ?? {}
      Object.assign(entry, result)
    } catch (thrown) {
      broken = true
      entry.notes = `probe error: ${String(thrown?.message ?? thrown).slice(0, 300)}`
    }

    // The declared fields are the session contract: a probe may fill them, never
    // reshape them.
    entry.id = stepId
    entry.action = action
    entry.expected = expected
    entry.layer = 4
    entry.origin = 'MEDIDO'
    entry.request ??= null
    entry.notes ??= ''

    if (!VERDICTS.includes(entry.verdict)) {
      entry.notes = `${entry.notes} · veredicto inválido «${entry.verdict}» declarado por el paso`
      entry.verdict = 'NO PROBADO'
    }

    // A probe that died mid-form leaves its dialog over the page; closing it here
    // keeps the next step measurable instead of cascading a timeout.
    if (broken) await closeDialog(page).catch(() => {})

    try {
      entry.shot = await shot(page, `shots/walk/${evidence.slug}/${stepId}.png`)
    } catch {
      entry.shot = null
    }

    evidence.steps.push(entry)
    return entry
  }
}

/** The `counts` block every walk carries. */
export const walkCounts = steps => {
  const count = verdict => (steps ?? []).filter(entry => entry.verdict === verdict).length

  return {
    total: (steps ?? []).length,
    ok: count('OK'),
    defecto: count('DEFECTO'),
    noOp: count('NO-OP'),
    confuso: count('CONFUSO'),
    noProbado: count('NO PROBADO')
  }
}

/** Which list primitive the surface mounted, and whether it advertises scroll. */
export const tablePrimitive = page =>
  page.evaluate(() => {
    const box = node =>
      node ? { clientWidth: node.clientWidth, scrollWidth: node.scrollWidth } : null
    const dataTables = document.querySelectorAll('main .data-table-container').length
    const tables = document.querySelectorAll('main table').length
    const affordance = document.querySelector('main [data-table-scroll-affordance]')
    const scroll = document.querySelector('main [data-table-scroll]')
    const overflow = document.querySelector('main .overflow-x-auto')

    return {
      primitive: dataTables > 0 ? 'UiDataTable' : tables > 0 ? 'tabla propia <table>' : 'sin tabla',
      dataTables,
      tables,
      handRolled: Math.max(0, tables - dataTables),
      scrollAffordance: Boolean(affordance),
      scrollHint: (affordance?.innerText ?? '').trim().replace(/\s+/g, ' '),
      dataTableScroll: box(scroll),
      overflowWrapper: box(overflow)
    }
  })

/** Visible field-level errors: the dialog's own messages and the invalid fields. */
export const fieldErrors = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      const box = node.getBoundingClientRect()
      return box.width > 0 && box.height > 0
    }
    const dialogs = Array.from(
      document.querySelectorAll('[role="dialog"], [role="alertdialog"]')
    ).filter(visible)
    const root = dialogs.at(-1) ?? document.querySelector('main')

    if (!root) return { messages: [], invalid: [], nativeInvalid: [] }

    const labelOf = node => {
      const id = node.getAttribute('id')
      const explicit = id ? root.querySelector(`label[for="${id}"]`) : null

      return (
        text(explicit) ||
        node.getAttribute('aria-label') ||
        node.getAttribute('placeholder') ||
        node.getAttribute('name') ||
        '(sin nombre)'
      )
    }

    return {
      messages: Array.from(root.querySelectorAll('.helper-text p, p.text-error-500, [data-error]'))
        .filter(visible)
        .map(text)
        .filter(Boolean),
      invalid: Array.from(root.querySelectorAll('[aria-invalid="true"]'))
        .filter(visible)
        .map(labelOf),
      nativeInvalid: Array.from(
        root.querySelectorAll('input:invalid, textarea:invalid, select:invalid')
      )
        .filter(visible)
        .map(labelOf)
    }
  })

/**
 * Opens a `UiSelect` trigger and clicks the option whose label matches.
 *
 * The primitive renders no `label` element, so a select is addressed by its
 * trigger (the page's `data-*` hook) and the option by its text.
 */
export const pickOption = async (page, trigger, optionText, timeout = 8000) => {
  const locator = typeof trigger === 'string' ? page.locator(trigger).first() : trigger

  try {
    if (!(await locator.isVisible().catch(() => false))) {
      return { ok: false, reason: 'trigger not visible' }
    }
    if (!(await locator.isEnabled().catch(() => false))) {
      return { ok: false, reason: 'trigger disabled' }
    }
    await locator.click({ timeout })
  } catch (thrown) {
    return {
      ok: false,
      reason: `trigger not clickable: ${String(thrown?.message ?? thrown).slice(0, 200)}`
    }
  }

  const listbox = page.locator('[role="listbox"]:visible').last()
  const opened = await listbox
    .waitFor({ state: 'visible', timeout })
    .then(() => true)
    .catch(() => false)

  if (!opened) return { ok: false, reason: 'listbox did not open' }

  const options = listbox.locator('button')
  const labels = (await options.allInnerTexts().catch(() => [])).map(value => value.trim())
  const wanted = String(optionText ?? '').trim()
  const exact = labels.findIndex(label => label === wanted)
  const partial = labels.findIndex(label => label.includes(wanted))
  const index = exact !== -1 ? exact : partial

  if (index === -1) {
    await page.keyboard.press('Escape').catch(() => {})
    return { ok: false, reason: `option "${wanted}" not found`, options: labels }
  }

  await options
    .nth(index)
    .click({ timeout })
    .catch(() => {})
  await listbox.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})

  return {
    ok: true,
    selected: labels[index],
    options: labels,
    match: exact !== -1 ? 'exact' : 'contains'
  }
}

/**
 * Opens a `UiSelect` trigger and returns the option labels it published.
 *
 * `pickOption` chooses one option; a walkthrough that has to prove the catalogue
 * offers exactly the rows the API published needs the list itself, not a choice.
 * The listbox is closed with Escape so the trigger stays addressable.
 */
export const readSelectOptions = async (page, trigger, timeout = 8000) => {
  const locator = typeof trigger === 'string' ? page.locator(trigger).first() : trigger

  try {
    if (!(await locator.isVisible().catch(() => false))) {
      return { ok: false, reason: 'trigger not visible' }
    }
    if (!(await locator.isEnabled().catch(() => false))) {
      return { ok: false, reason: 'trigger disabled' }
    }
    await locator.click({ timeout })
  } catch (thrown) {
    return {
      ok: false,
      reason: `trigger not clickable: ${String(thrown?.message ?? thrown).slice(0, 200)}`
    }
  }

  const listbox = page.locator('[role="listbox"]:visible').last()
  const opened = await listbox
    .waitFor({ state: 'visible', timeout })
    .then(() => true)
    .catch(() => false)

  if (!opened) return { ok: false, reason: 'listbox did not open' }

  const options = (
    await listbox
      .locator('button')
      .allInnerTexts()
      .catch(() => [])
  )
    .map(label => label.trim().replace(/\s+/g, ' '))
    .filter(Boolean)

  await page.keyboard.press('Escape').catch(() => {})
  await listbox.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})

  return { ok: true, options }
}

/**
 * Moves instrument-induced HTTP failures out of `unexpected`.
 *
 * A probe that presses a door the API must refuse, or forces a load error to
 * measure the retry state, is exercising a contract: the failure is reported with
 * its reason instead of polluting the surface's diagnostics.
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
