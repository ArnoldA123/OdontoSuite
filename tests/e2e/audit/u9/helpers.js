// Plan #13 (issue #372, U9) — measurement helpers shared by the admin session rows.
//
// Plain readers and small drivers: no `test()` is registered here, so
// `--grep @U9` selects exactly the rows the session modules declare. Every
// reader returns what it observed; a reader that cannot observe a node returns
// `null` or an explicit reason so the caller records `NO PROBADO` instead of
// throwing.
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

export const SESSION = 'U9'

export const EVIDENCE = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U9'

export const BASE = process.env.BROWSER_PASS_BASE || 'http://localhost:8000'

export const DESKTOP = { width: 1440, height: 900 }
export const MOBILE = { width: 390, height: 844 }

// Smallest rendered-content volume a surface has to clear to count as painted.
export const THRESHOLD = 80

export const DIALOG_SELECTOR = '[role="dialog"]:visible, [role="alertdialog"]:visible'

// The four roles the layer-5 matrix measures: administration (owner of every
// ABM), reception (the transfer requester), clinical and finance.
export const ROLES = Object.freeze(['administrador', 'recepcionista', 'odontologo', 'finanzas'])

// The labels S5 counts as a write affordance on a surface that must be read-only
// for the role being measured.
export const WRITE_AFFORDANCE =
  /nuevo|nueva|crear|editar|eliminar|archivar|reactivar|guardar|fijar|retirar|aprobar|rechazar|enviar solicitud/i

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

/** Real tokens for the four matrix roles, in `ROLES` order. */
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
      hasText: /guardar|crear|actualizar|fijar|enviar|aprobar|rechazar|aceptar|confirmar/i
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
    const roots = new Set()

    for (const closer of document.querySelectorAll('button[aria-label="Cerrar notificación"]')) {
      const root = closer.parentElement?.parentElement
      if (root) roots.add(root)
    }

    for (const live of document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]')) {
      if (live.querySelector('button[aria-label="Cerrar notificación"]')) roots.add(live)
    }

    return Array.from(roots)
      .map(node => (node.innerText ?? '').trim().replace(/\s+/g, ' '))
      .filter(Boolean)
      .join(' | ')
  })

/**
 * The inline error the page paints in its own block or field.
 *
 * The pages name their hooks per module (`data-types-error`,
 * `data-branch-detail-error`, `data-state="error"`), so the scan reads the hook
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

/** Dismisses the toasts a probe triggered so they do not cover the next control. */
export const dismissToasts = async page => {
  const closers = page.locator('button[aria-label="Cerrar notificación"]:visible')
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
