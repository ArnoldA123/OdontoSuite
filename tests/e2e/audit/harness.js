// Plan #13 (issue #363, U0) — layers 2 and 3: browser audit harness.
//
// Plain Node ESM on purpose: the manifest, the role matrix, the failure
// classification and the dialog contract are pure functions the CLI wrapper can
// exercise without launching a browser. Only `primeSession`, `capture`, the
// probes and the diagnostics touch a Playwright Page, and they receive it as an
// argument instead of importing it.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, isAbsolute, resolve } from 'node:path'

import { buildManifest } from '../../../scripts/audit/ui-manifest.mjs'

export const BASE_URL = process.env.BROWSER_PASS_BASE || 'http://localhost:8000'

// Every seeded user shares this password (`RoleBasedUsersSeeder`); CREDENTIALS.md
// is the tracked document the credentials test keeps in sync with the seeder.
export const PASSWORD = 'password123'

export const ROLE_USERNAMES = Object.freeze({
  administrador: 'elizabet',
  recepcionista: 'recepcionista_test',
  odontologo: 'ever_odontologo',
  implantologo: 'implantologo_test',
  tecnico_dental: 'tecnico_test',
  asistente: 'asistente_test',
  finanzas: 'finanzas_test'
})

const ROLE_ORDER = Object.keys(ROLE_USERNAMES)

export const EVIDENCE_DIR = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U0'

// A surface whose shell renders fewer characters than this is treated as blank.
// The map exists for measured exceptions: a route that legitimately renders less
// gets its own entry instead of lowering the default for everyone.
export const CONTENT_THRESHOLDS = {}

const DENIED_STATUSES = new Set([401, 403, 404])

// Layer 2 allows a declared allowlist of HTTP failures. The session shell
// subscribes to every private channel it can name, and the channel authorizer
// grants them by role, so a non-clinical role gets a 403 for the clinical
// channels on every surface. The baseline records it as finding F1 of U0 (the
// surface asks for a channel it cannot authorize); it is not a page defect and
// it is reported, not hidden: the row keeps the channel and the pass counts it.
export const EXPECTED_DENIALS = [
  {
    endpoint: /\/api\/broadcasting\/auth$/,
    statuses: [403],
    reason: 'session shell subscribes to channels outside the role (U0 F1)'
  }
]
const EDGE_TOLERANCE = 8
const DIALOG_KINDS = new Set(['create', 'open', 'edit', 'action'])
const PREFERRED_TRIGGER_LABEL = /nuevo|nueva|agregar|crear|abrir|ver|editar|registrar|detalle/i

// Evidence paths are declared relative to EVIDENCE_DIR; an absolute path is
// honoured as-is so a caller can point outside the run directory on purpose.
const absoluteEvidencePath = relPath =>
  isAbsolute(relPath) ? relPath : resolve(process.cwd(), EVIDENCE_DIR, relPath)

const round = value => Math.round(value * 10) / 10

// `/patients/:id` -> `patients-id`, `/` -> `root`,
// `/:pathMatch(.*)*` -> `pathmatch`, `/settings/branches/:id` -> `settings-branches-id`
export const slugFor = route => {
  const slug = route
    .replace(/\([^)]*\)/g, ' ')
    .replace(/:/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

  return slug || 'root'
}

// AUDIT_MANIFEST may be a written manifest path (the CLI form) or JSON text, so
// the wrapper can hand the same artefact to the spec and to the summarizer.
export const loadManifest = () => {
  const source = process.env.AUDIT_MANIFEST

  if (!source) return buildManifest()

  const trimmed = source.trim()
  const candidate = isAbsolute(trimmed) ? trimmed : resolve(process.cwd(), trimmed)

  if (existsSync(candidate)) return JSON.parse(readFileSync(candidate, 'utf8'))

  return JSON.parse(trimmed)
}

// Mirrors `applyFilter` in scripts/audit/ui-manifest.mjs so the spec and the
// manifest CLI select the same surfaces for the same scope.
export const applyScope = (manifest, scope = 'all') => {
  const surfaces = manifest?.surfaces ?? []

  if (!scope || scope === 'all') return surfaces

  if (scope.startsWith('route:')) {
    const route = scope.slice('route:'.length)
    return surfaces.filter(surface => surface.route === route)
  }

  if (scope.startsWith('module:')) {
    const name = scope.slice('module:'.length).replace(/^\/+|\/+$/g, '')
    return surfaces.filter(
      surface =>
        surface.route === `/${name}` ||
        surface.route.startsWith(`/${name}/`) ||
        surface.page.includes(`/modules/${name}/`)
    )
  }

  throw new Error(`unknown AUDIT_SCOPE "${scope}" (expected all, module:<name> or route:<path>)`)
}

// The desk owner is the first visible non-admin role: the menu entry exists for
// them, so they are the realistic operator. The negative role is a visible
// surface rendered for someone who cannot read it — a render and shell check,
// never an assertion that the API succeeds.
export const rolePlans = (surface, maxRoles = 3) => {
  const visible = surface?.roles ?? []

  if (visible.length === 1 && visible[0] === 'anonymous') {
    return [{ role: 'anonymous', expectation: 'anonymous' }]
  }

  const plans = []
  const push = (role, expectation) => {
    if (!role || plans.some(plan => plan.role === role)) return
    plans.push({ role, expectation })
  }

  push(visible.find(role => role !== 'administrador') ?? 'administrador', 'owner')

  if (visible.includes('administrador')) push('administrador', 'admin')

  const negative = ROLE_ORDER.find(role => !visible.includes(role))
  if (negative) push(negative, 'negative')

  return plans.slice(0, maxRoles)
}

// The login endpoint throttles 3 attempts/min per username and locks 10 min after
// 5 failures, so a run authenticates once per role. The promise is memoized (not
// just the result) so two helpers asking for the same role share one request.
const sessions = new Map()

export const loginAs = async (request, role) => {
  if (sessions.has(role)) return sessions.get(role)

  const username = ROLE_USERNAMES[role]
  if (!username) throw new Error(`no seeded user for role "${role}" (see CREDENTIALS.md)`)

  const pending = (async () => {
    const response = await request.post(`${BASE_URL}/api/auth/login`, {
      data: { username, password: PASSWORD }
    })

    if (!response.ok()) {
      const body = await response.text().catch(() => '')
      throw new Error(
        `login failed for ${role} (${username}): HTTP ${response.status()} ${body}`.trim()
      )
    }

    const body = await response.json()
    const token = body?.data?.token
    const user = body?.data?.user

    if (!token || !user) {
      throw new Error(`login for ${role} returned no token/user: ${JSON.stringify(body)}`)
    }

    return { role, token, user }
  })()

  sessions.set(role, pending)
  return pending
}

// The SPA reads its session from localStorage before the router guards run, so
// the token must be written before the first document script executes. Init
// scripts also run in every frame, including opaque ones where localStorage is
// denied; a frame without storage is not a surface failure.
export const primeSession = async (page, session) => {
  await page.addInitScript(
    state => {
      try {
        localStorage.setItem('auth_token', state.token)
        localStorage.setItem('user', JSON.stringify(state.user))
      } catch {
        // Opaque frame created by a widget; the app frame seeds the session.
      }
    },
    { token: session.token, user: session.user }
  )
}

// Two frames are enough for the responsive layout to reflow after a resize.
export const settleLayout = page =>
  page.evaluate(
    () =>
      new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve())))
  )

// A surface is ready when its content clears the threshold. The wait is bounded
// so a genuinely blank surface still gets measured and classified.
export const settleSurface = async (page, threshold, timeout = 12000) => {
  await page
    .waitForFunction(
      value => {
        const surface = document.querySelector('main') ?? document.querySelector('#app')
        return (surface?.innerText.trim().length ?? 0) > value
      },
      threshold,
      { timeout, polling: 150 }
    )
    .catch(() => {})

  await settleLayout(page)
}

// Same shape as tests/e2e/smoke.spec.js so the wrapper reads one vocabulary.
// A request that never got a response is kept in failedRequests with status null.
export const collectDiagnostics = page => {
  const consoleErrors = []
  const pageErrors = []
  const failedRequests = []

  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text())
  })

  page.on('pageerror', error => {
    pageErrors.push(error.message)
  })

  page.on('response', response => {
    if (response.status() < 400) return

    const failure = { status: response.status(), url: response.url() }

    // The channel name is the only body detail the evidence needs: it names
    // which subscription the authorizer denied.
    if (response.url().endsWith('/api/broadcasting/auth')) {
      const body = response.request().postDataJSON?.()
      if (body?.channel_name) failure.channel = body.channel_name
    }

    failedRequests.push(failure)
  })

  page.on('requestfailed', request => {
    failedRequests.push({
      status: null,
      url: request.url(),
      failure: request.failure()?.errorText || 'request failed'
    })
  })

  return { consoleErrors, pageErrors, failedRequests }
}

// For a negative plan the 401/403/404 responses — and the requests that never
// completed because the API denied them — are the expected outcome of rendering
// a surface to a role without its permission. Anything else stays a defect
// unless EXPECTED_DENIALS declares it.
export const classifyFailures = (failures, expectation) => {
  const expected = []
  const unexpected = []

  for (const failure of failures ?? []) {
    const declared = EXPECTED_DENIALS.find(
      entry => entry.endpoint.test(failure.url ?? '') && entry.statuses.includes(failure.status)
    )
    const isDenial =
      expectation === 'negative' && (failure.status == null || DENIED_STATUSES.has(failure.status))

    if (isDenial) expected.push(failure)
    else if (declared) expected.push({ ...failure, reason: declared.reason })
    else unexpected.push(failure)
  }

  return { expected, unexpected }
}

export const contentLength = page =>
  page.evaluate(() => {
    // Authenticated surfaces render inside AppLayout's <main>; the auth page is
    // the one screen without the shell, so it measures the app root instead.
    const surface = document.querySelector('main') ?? document.querySelector('#app')
    return surface?.innerText.trim().length ?? 0
  })

export const hasHorizontalOverflow = page =>
  page.evaluate(() => ({
    overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    scrollWidth: document.documentElement.scrollWidth,
    innerWidth: window.innerWidth
  }))

// Captures are always explicit: playwright.config.js keeps `only-on-failure`, so
// without this call a passing surface leaves no visual record.
export const capture = async (page, filePath) => {
  const absolute = absoluteEvidencePath(filePath)
  mkdirSync(dirname(absolute), { recursive: true })
  await page.screenshot({ path: absolute, fullPage: true })
  return filePath
}

export const writeEvidence = (relPath, payload) => {
  const absolute = absoluteEvidencePath(relPath)
  mkdirSync(dirname(absolute), { recursive: true })
  writeFileSync(absolute, `${JSON.stringify(payload, null, 2)}\n`)
  return relPath
}

export const readTabLabels = async page => {
  const tabs = page.locator('[role="tab"]:visible')
  const count = await tabs.count()
  const labels = []

  for (let index = 0; index < count; index += 1) {
    const label = (
      await tabs
        .nth(index)
        .innerText()
        .catch(() => '')
    ).trim()

    if (label) labels.push(label)
  }

  return labels
}

const dialogCheck = (id, ok, observed, expected) => ({ id, ok, observed, expected })

const describeDialog = dialog =>
  dialog.evaluate(element => {
    const labelledby = element.getAttribute('aria-labelledby')
    const labelTarget = labelledby ? document.getElementById(labelledby) : null
    const ariaLabel = element.getAttribute('aria-label') ?? ''
    const heading = element.querySelector('h1, h2, h3')

    return {
      name: ((labelTarget?.textContent ?? '') || ariaLabel).trim(),
      title: (heading?.textContent ?? '').trim(),
      role: element.getAttribute('role')
    }
  })

/**
 * Layer 3 contract over the currently open dialog.
 *
 * Contract violations are evidence, never exceptions: a page that fails every
 * check returns `{ checks }` with `ok: false` rows. The only throw is an
 * infrastructure error (no dialog, no bounding box) where the probe cannot
 * observe anything at all.
 *
 * `escape-closes` runs last because it closes the dialog.
 */
export const assertDialogContract = async (page, options = {}) => {
  const dialog = options.dialog ?? page.locator('[role="dialog"]:visible').last()

  if ((await dialog.count()) === 0) {
    throw new Error('dialog contract: no visible [role="dialog"]')
  }

  const box = await dialog.boundingBox()
  if (!box) throw new Error('dialog contract: the dialog has no bounding box')

  const viewport = await page.evaluate(() => ({
    width: window.innerWidth,
    height: window.innerHeight
  }))

  const measured = {
    x: round(box.x),
    y: round(box.y),
    width: round(box.width),
    height: round(box.height)
  }

  const centerOffset = round(Math.abs(measured.x + measured.width / 2 - viewport.width / 2))
  const touchesBottom = Math.abs(measured.y + measured.height - viewport.height) < EDGE_TOLERANCE
  const touchesTop = Math.abs(measured.y) < EDGE_TOLERANCE
  const fullHeight = Math.abs(measured.height - viewport.height) < EDGE_TOLERANCE
  const edgeDocked = touchesBottom || (touchesTop && fullHeight)
  const rightDocked = Math.abs(measured.x + measured.width - viewport.width) < EDGE_TOLERANCE
  const centered = centerOffset < EDGE_TOLERANCE && !edgeDocked

  const descriptor = await describeDialog(dialog)
  const checks = []

  if (centered) checks.push(dialogCheck('centered', true, centerOffset, '<8px off center'))
  else if (edgeDocked) {
    checks.push(dialogCheck('centered', true, 'not-applicable', '<8px off center'))
  } else checks.push(dialogCheck('centered', false, centerOffset, '<8px off center'))

  if (rightDocked) checks.push(dialogCheck('docked', true, 'right', 'docked to a viewport edge'))
  else if (touchesBottom) {
    checks.push(dialogCheck('docked', true, 'bottom', 'docked to a viewport edge'))
  } else if (centered) {
    checks.push(dialogCheck('docked', true, 'not-applicable', 'docked to a viewport edge'))
  } else {
    checks.push(
      dialogCheck('docked', false, `${measured.x},${measured.width}`, 'docked to a viewport edge')
    )
  }

  const headings = await page.evaluate(() => document.querySelectorAll('h1').length)
  checks.push(dialogCheck('single-h1', headings === 1, headings, 1))

  const focusInside = () =>
    dialog.evaluate(
      element => element === document.activeElement || element.contains(document.activeElement)
    )

  const focusedOnOpen = await focusInside()
  let focusedAfterTabs = focusedOnOpen

  for (let press = 0; press < 3; press += 1) {
    await page.keyboard.press('Tab')
    focusedAfterTabs = await focusInside()
  }

  checks.push(
    dialogCheck(
      'focus-trapped',
      focusedOnOpen && focusedAfterTabs,
      focusedAfterTabs ? 'inside' : 'outside',
      'focus stays inside the dialog'
    )
  )

  const scroll = await page.evaluate(() => ({
    inline: document.body.style.overflow,
    computed: getComputedStyle(document.body).overflow
  }))

  const scrollLocked = scroll.inline === 'hidden' || scroll.computed === 'hidden'
  checks.push(
    dialogCheck(
      'scroll-locked',
      scrollLocked,
      scroll.inline || scroll.computed || 'visible',
      'hidden'
    )
  )

  checks.push(
    dialogCheck(
      'accessible-name',
      Boolean(descriptor.name),
      descriptor.name || '',
      'non-empty accessible name'
    )
  )

  let escapeClosed = true
  try {
    await page.keyboard.press('Escape')
    await dialog.waitFor({ state: 'hidden', timeout: 2000 })
  } catch {
    escapeClosed = false
  }

  checks.push(
    dialogCheck('escape-closes', escapeClosed, escapeClosed ? 'closed' : 'still open', 'closes')
  )

  return { dialog: { ...descriptor, box: measured, viewport }, checks }
}

// Only triggers the manifest could classify and that carry a stable hook are
// candidates: an unlabelled control cannot be clicked by the audit without
// inventing a selector. Preferred labels are the usual dialog openers; the rest
// keep manifest order so the pick stays deterministic.
export const dialogTriggers = (surface, limit = 2) => {
  const candidates = (surface?.actions ?? []).filter(
    action => action.selector && DIALOG_KINDS.has(action.kind)
  )
  const preferred = candidates.filter(action => PREFERRED_TRIGGER_LABEL.test(action.label ?? ''))
  const rest = candidates.filter(action => !PREFERRED_TRIGGER_LABEL.test(action.label ?? ''))
  const picked = []
  const seen = new Set()

  for (const action of [...preferred, ...rest]) {
    if (picked.length >= limit) break
    if (seen.has(action.selector)) continue
    seen.add(action.selector)
    picked.push(action)
  }

  return picked
}

const dismissDialog = async page => {
  try {
    const dialog = page.locator('[role="dialog"]:visible').last()
    if ((await dialog.count()) === 0) return
    await page.keyboard.press('Escape')
    await dialog.waitFor({ state: 'hidden', timeout: 2000 })
  } catch {
    // Best effort: cleanup never replaces the probe's evidence.
  }
}

/**
 * Clicks a manifest action and waits up to 1 s for a dialog. Returns the locator
 * when one opens, null otherwise (not visible, disabled, navigates, no dialog).
 * A probe that fails after opening never leaves the dialog behind.
 */
export const openDialog = async (page, action, timeout = 1000) => {
  const trigger = page.locator(action.selector).first()

  try {
    if (!(await trigger.isVisible()) || !(await trigger.isEnabled())) return null
    await trigger.click({ timeout })
  } catch {
    return null
  }

  const dialog = page.locator('[role="dialog"]:visible').last()

  try {
    await dialog.waitFor({ state: 'visible', timeout })
    return dialog
  } catch {
    await dismissDialog(page)
    return null
  }
}
