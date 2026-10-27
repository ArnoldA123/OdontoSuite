// Plan #13 (issue #374, U11) — layer 3: the dialog axis, measured on the
// primitive the surface actually opens.
//
// One row per surface that declares a dialog in the layer-1 manifest or whose
// own sources paint an overlay outside `UiModal` (the static pass names them).
// Up to two manifest triggers are opened with the harness contract — centered or
// docked, one `h1`, focus trapped, scroll locked, accessible name, Escape
// closes — and the measured facts are stored, never asserted as product quality.
//
// A surface whose triggers carry no selector cannot be opened without inventing
// one: it is `no-dialog` with the manifest primitive, which is the honest
// observation and the evidence the matrix reads.

import { test, expect } from 'playwright/test'

import {
  BASE_URL,
  applyScope,
  assertDialogContract,
  collectDiagnostics,
  dialogTriggers,
  loadManifest,
  loginAs,
  openDialog,
  primeSession,
  rolePlans,
  settleSurface,
  slugFor,
  writeEvidence
} from '../harness.js'

import { buildStaticAxes } from './static.js'

const OUT = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U11'
const SCOPE = process.env.AUDIT_SCOPE || 'all'
const THRESHOLD = 80
const SETTLE_TIMEOUT = 12000
const NAVIGATION_TIMEOUT = 15000
const CATCH_ALL_SEGMENT = ':pathMatch'
const CATCH_ALL_PROBE = '/audit-catch-all'

test.use({ viewport: { width: 1440, height: 900 } })

const manifest = loadManifest()
const staticRows = new Map(buildStaticAxes(manifest).rows.map(row => [row.route, row]))
const navLabels = new Map((manifest.navigation ?? []).map(entry => [entry.to, entry.name]))
const labelFor = surface => navLabels.get(surface.route) ?? surface.name

const surfaces = applyScope(manifest, SCOPE).filter(
  surface => surface.dialogs.length > 0 || staticRows.get(surface.route)?.axes.dialog.ownOverlay
)

// The sample row is addressed by replacing the last dynamic segment of the
// route (`/patients/:id` -> `/patients/7`); a route without one keeps its path.
const withSampleId = (route, id) => {
  const segments = route.split('/')

  for (let index = segments.length - 1; index >= 0; index -= 1) {
    if (segments[index].startsWith(':')) {
      segments[index] = String(id)
      break
    }
  }

  return segments.join('/')
}

// One administration login per worker. The sample lookup used to log in again
// for every surface, so a 36-route pass paid 36 extra logins against the login
// throttle and a 429 surfaced as a false "no-sample-record" skip.
let sampleToken = null
const sampleSessionToken = async request => {
  if (!sampleToken) {
    const session = await loginAs(request, 'administrador')
    sampleToken = session.token
  }

  return sampleToken
}

const hrefFor = (surface, id) => {
  if (surface.href) return surface.href
  if (surface.sample && id != null) return withSampleId(surface.route, id)
  if (surface.route.includes(CATCH_ALL_SEGMENT)) return CATCH_ALL_PROBE

  return surface.route
}

const resolveSample = async (request, surface) => {
  const { endpoint, idField } = surface.sample
  const token = await sampleSessionToken(request)

  const response = await request.get(`${BASE_URL}${endpoint}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' }
  })

  if (!response.ok()) return null

  const body = await response.json().catch(() => null)
  const payload = body?.data
  const rows = Array.isArray(payload) ? payload : payload?.data

  return rows?.[0]?.[idField] ?? null
}

const closeDialog = async page => {
  try {
    const dialog = page.locator('[role="dialog"]:visible, [role="alertdialog"]:visible').last()

    if ((await dialog.count()) === 0) return

    await page.keyboard.press('Escape')
    await dialog.waitFor({ state: 'hidden', timeout: 2000 })
  } catch {
    // Cleanup never replaces the probe's evidence.
  }
}

const checkOk = (checks, id) => checks.find(check => check.id === id)?.ok ?? null

for (const surface of surfaces) {
  const [owner] = rolePlans(surface)
  const slug = slugFor(surface.route)
  const declared = surface.dialogs[0]?.primitive ?? 'unknown'

  test(`@U11 · capa 3 — ${labelFor(surface)}: eje de diálogo`, async ({ page, request }) => {
    test.setTimeout(180000)

    const diagnostics = collectDiagnostics(page)
    const evidence = {
      route: surface.route,
      slug,
      role: owner.role,
      primitive: declared,
      outcome: null,
      dialogs: [],
      pageErrors: [],
      consoleErrors: []
    }

    try {
      const session = owner.expectation === 'anonymous' ? null : await loginAs(request, owner.role)
      const sampleId = surface.sample ? await resolveSample(request, surface) : null

      if (surface.sample && sampleId == null) {
        evidence.reason = 'no-sample-record'
        test.skip(true, 'no-sample-record')
      }

      const href = hrefFor(surface, sampleId)
      evidence.href = href

      if (session) await primeSession(page, session)

      await page.goto(href, { waitUntil: 'domcontentloaded' })
      await page
        .locator('main')
        .waitFor({ state: 'visible', timeout: NAVIGATION_TIMEOUT })
        .catch(() => {})

      await settleSurface(page, THRESHOLD, SETTLE_TIMEOUT)

      const triggers = dialogTriggers(surface, 2)

      if (triggers.length === 0) evidence.outcome = 'no-dialog'

      for (const [index, action] of triggers.entries()) {
        const row = {
          trigger: { label: action.label, kind: action.kind, selector: action.selector },
          primitive: surface.dialogs[index]?.primitive ?? 'unknown',
          centered: null,
          docked: null,
          name: null,
          checks: [],
          outcome: 'no-dialog'
        }

        try {
          const dialog = await openDialog(page, action)

          if (dialog) {
            const contract = await assertDialogContract(page, { dialog })
            row.outcome = 'measured'
            row.centered = checkOk(contract.checks, 'centered')
            row.docked = checkOk(contract.checks, 'docked')
            row.name = contract.dialog.name
            row.checks = contract.checks
          }
        } catch (error) {
          row.outcome = 'probe-error'
          row.error = String(error?.message ?? error).slice(0, 300)
        } finally {
          await closeDialog(page)
          evidence.dialogs.push(row)
        }
      }
    } finally {
      evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
      evidence.consoleErrors = diagnostics.consoleErrors
      writeEvidence(`primitives/${slug}.json`, evidence, { dir: OUT })
    }

    expect(evidence.pageErrors, `page errors of ${surface.route}`).toEqual([])
  })
}
