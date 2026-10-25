// Plan #13 (issue #363, U0) — layers 2 and 3 driven by the static manifest.
//
// Layer 2 renders every surface for the roles that make it meaningful and keeps
// screenshots plus a diagnostics row; layer 3 opens up to two dialog triggers and
// records the primitive contract. Both write evidence even when they skip, so the
// CLI wrapper can classify all surfaces of a run.

import { test, expect } from 'playwright/test'

import {
  BASE_URL,
  CONTENT_THRESHOLDS,
  applyScope,
  assertDialogContract,
  capture,
  classifyFailures,
  collectDiagnostics,
  contentLength,
  dialogTriggers,
  hasHorizontalOverflow,
  loadManifest,
  loginAs,
  openDialog,
  primeSession,
  readTabLabels,
  rolePlans,
  settleSurface,
  slugFor,
  writeEvidence
} from './harness.js'

// U1 (issue #364) audits the session and the shell, not a page module: its rows
// live in their own modules and are registered here so that
// `--grep @U1` selects them from this spec.
import './u1/shell.js'
import './u1/login.js'
import './u1/recovery.js'

// U2 (issue #365) audits the landing surface and the professional's desk: the
// rows live in their own modules so `--grep @U2` selects both.
import './u2/dashboard.js'
import './u2/my-day.js'

// U3 (issue #366) audits the agenda and the consultation close: the fixtures and
// the three row modules live together so `--grep @U3` selects the session.
import './u3/fixtures.js'
import './u3/calendar.js'
import './u3/wizard.js'
import './u3/api-matrix.js'

const OUT = process.env.AUDIT_OUT || 'U0'
const SCOPE = process.env.AUDIT_SCOPE || 'all'

const manifest = loadManifest()

// The shell is a declared surface, not a route: the session suite measures it
// (one row per role, plus the mobile sheet and the global confirm), so the
// generic route loops below would only navigate to a path that does not exist
// and overwrite that evidence with the same file names.
const renderable = surface => surface.kind !== 'shell'
const surfaces = applyScope(manifest, SCOPE).filter(renderable)

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const CATCH_ALL_SEGMENT = ':pathMatch'
const CATCH_ALL_PROBE = '/audit-catch-all'
const DEFAULT_THRESHOLD = 80
const NAVIGATION_TIMEOUT = 15000
const DIALOG_TIMEOUT = 2000
// A surface with the permission renders its content; a negative role stops at
// the denial state, which is legitimately short. Waiting the full window there
// doubles the mobile reload and burns the test budget.
const SETTLE_TIMEOUT = 12000
const NEGATIVE_SETTLE_TIMEOUT = 3000

test.use({ viewport: DESKTOP })

const sessions = new Map()
const samples = new Map()

const isCatchAll = surface => surface.route.includes(CATCH_ALL_SEGMENT)

const thresholdFor = route => CONTENT_THRESHOLDS[route] ?? DEFAULT_THRESHOLD

const hrefFor = (surface, id) => {
  if (surface.href) return surface.href

  if (surface.sample && id != null) {
    const segments = surface.route.split('/')
    for (let index = segments.length - 1; index >= 0; index -= 1) {
      if (segments[index].startsWith(':')) {
        segments[index] = String(id)
        break
      }
    }
    return segments.join('/')
  }

  if (isCatchAll(surface)) return CATCH_ALL_PROBE

  return surface.route
}

// Detail surfaces are audited against a real row. The audit never creates data:
// when the endpoint has none, the surface is excluded with a reason.
const resolveSample = async (request, surface) => {
  const { endpoint, idField } = surface.sample

  if (!samples.has(endpoint)) {
    const session = await sessions.get('administrador')
    const response = await request.get(`${BASE_URL}${endpoint}`, {
      headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' }
    })

    let id = null

    if (response.ok()) {
      const body = await response.json().catch(() => null)
      const payload = body?.data
      const rows = Array.isArray(payload) ? payload : payload?.data
      const first = Array.isArray(rows) ? rows[0] : null
      id = first?.[idField] ?? null
    }

    samples.set(endpoint, id)
  }

  return samples.get(endpoint)
}

const closeDialog = async page => {
  try {
    const dialog = page.locator('[role="dialog"]:visible').last()
    if ((await dialog.count()) === 0) return
    await page.keyboard.press('Escape')
    await dialog.waitFor({ state: 'hidden', timeout: DIALOG_TIMEOUT })
  } catch {
    // The probe owns the evidence; cleanup stays best effort.
  }
}

test.beforeAll(async ({ request }) => {
  const roles = new Set()

  for (const surface of surfaces) {
    for (const plan of rolePlans(surface)) roles.add(plan.role)
  }

  if (surfaces.some(surface => surface.sample)) roles.add('administrador')
  roles.delete('anonymous')

  for (const role of roles) sessions.set(role, await loginAs(request, role))

  for (const surface of surfaces) {
    if (surface.sample) await resolveSample(request, surface)
  }

  writeEvidence('run.json', {
    out: OUT,
    scope: SCOPE,
    revision: manifest.revision,
    surfaces: surfaces.length,
    roles: [...roles].sort()
  })
})

// --- layer 2: render per surface × role -------------------------------------

for (const surface of surfaces) {
  for (const plan of rolePlans(surface)) {
    test(`renders ${surface.route} as ${plan.role}`, async ({ page }) => {
      const slug = slugFor(surface.route)
      const diagnostics = collectDiagnostics(page)
      const evidence = {
        route: surface.route,
        slug,
        role: plan.role,
        expectation: plan.expectation,
        href: null,
        url: null,
        httpStatus: null,
        rendered: false,
        contentLength: 0,
        notFound: false,
        horizontalOverflow: null,
        viewports: { desktop: { ...DESKTOP } },
        shots: [],
        consoleErrors: diagnostics.consoleErrors,
        pageErrors: diagnostics.pageErrors,
        failedRequests: { expected: [], unexpected: [] },
        tabs: []
      }

      try {
        const sampleId = surface.sample ? (samples.get(surface.sample.endpoint) ?? null) : null

        if (surface.sample && sampleId == null) {
          evidence.reason = 'no-sample-record'
          test.skip(true, 'no-sample-record')
        }

        const href = hrefFor(surface, sampleId)
        evidence.href = href

        if (plan.expectation !== 'anonymous') {
          await primeSession(page, sessions.get(plan.role))
        }

        const response = await page.goto(href)
        evidence.httpStatus = response?.status() ?? null
        evidence.url = page.url()

        await page
          .locator('main')
          .waitFor({ state: 'visible', timeout: NAVIGATION_TIMEOUT })
          .catch(() => {})

        // The SPA paints the shell before the page payload lands; skeleton blocks
        // carry no text, so measuring on first paint classifies a loading page as
        // blank. Wait for the surface content, bounded, then measure.
        await settleSurface(
          page,
          thresholdFor(surface.route),
          plan.expectation === 'negative' ? NEGATIVE_SETTLE_TIMEOUT : SETTLE_TIMEOUT
        )

        evidence.contentLength = await contentLength(page)
        evidence.rendered = evidence.contentLength > thresholdFor(surface.route)

        // The negative role can land on a denial state without any heading; a
        // locator read would auto-wait for one and burn the test budget.
        const heading = await page
          .evaluate(() => document.querySelector('h1')?.innerText ?? '')
          .catch(() => '')

        evidence.notFound = !isCatchAll(surface) && /página no encontrada/i.test(heading)
        evidence.tabs = await readTabLabels(page)

        evidence.shots.push(await capture(page, `shots/${slug}-${plan.role}-desktop.png`))

        await page.setViewportSize(MOBILE)
        // A viewport resized after the desktop paint keeps widths that were
        // computed at 1440 (tables, charts, grids), which invents an overflow.
        // The honest mobile measurement is a document loaded at 390.
        await page.reload()
        await settleSurface(
          page,
          thresholdFor(surface.route),
          plan.expectation === 'negative' ? NEGATIVE_SETTLE_TIMEOUT : SETTLE_TIMEOUT
        )

        const overflow = await hasHorizontalOverflow(page)
        evidence.horizontalOverflow = overflow.overflow
        evidence.viewports.mobile = {
          ...MOBILE,
          scrollWidth: overflow.scrollWidth,
          innerWidth: overflow.innerWidth
        }

        evidence.shots.push(await capture(page, `shots/${slug}-${plan.role}-mobile.png`))

        evidence.failedRequests = classifyFailures(diagnostics.failedRequests, plan.expectation)
      } finally {
        writeEvidence(`render/${slug}-${plan.role}.json`, evidence)
      }

      // The probe records the classification; `ui-pass` decides the verdict.
      // Only the instrument itself is asserted here: both captures exist and the
      // surface raised no page error (the issue's zero-pageerror criterion).
      expect(evidence.shots, `captures for ${evidence.href}`).toHaveLength(2)
      expect(evidence.pageErrors, 'page errors').toEqual([])
    })
  }
}

// --- layer 3: dialog primitive contract -------------------------------------

for (const surface of surfaces) {
  test(`dialog contract ${surface.route}`, async ({ page }) => {
    const slug = slugFor(surface.route)
    const [owner] = rolePlans(surface)
    const diagnostics = collectDiagnostics(page)
    const evidence = {
      route: surface.route,
      slug,
      role: owner?.role ?? null,
      opened: 0,
      dialogs: [],
      pageErrors: [],
      consoleErrors: diagnostics.consoleErrors
    }

    try {
      if (!owner || owner.expectation === 'anonymous') {
        evidence.reason = 'anonymous'
        test.skip(true, 'anonymous surface')
      }

      const sampleId = surface.sample ? (samples.get(surface.sample.endpoint) ?? null) : null

      if (surface.sample && sampleId == null) {
        evidence.reason = 'no-sample-record'
        test.skip(true, 'no-sample-record')
      }

      await primeSession(page, sessions.get(owner.role))
      await page.goto(hrefFor(surface, sampleId))
      await page
        .locator('main')
        .waitFor({ state: 'visible', timeout: NAVIGATION_TIMEOUT })
        .catch(() => {})

      await settleSurface(page, thresholdFor(surface.route), SETTLE_TIMEOUT)

      const length = await contentLength(page)

      if (length <= thresholdFor(surface.route)) {
        evidence.reason = 'not-rendered'
        evidence.contentLength = length
        test.skip(true, 'not rendered')
      }

      const errorsBeforeProbe = diagnostics.pageErrors.length

      for (const action of dialogTriggers(surface)) {
        const row = {
          trigger: { label: action.label, kind: action.kind, selector: action.selector },
          dialog: null,
          checks: []
        }

        try {
          const dialog = await openDialog(page, action)

          if (dialog) {
            evidence.opened += 1
            const contract = await assertDialogContract(page, { dialog })
            row.dialog = contract.dialog
            row.checks = contract.checks
          } else {
            row.outcome = 'no-dialog'
          }
        } catch (error) {
          row.outcome = 'probe-error'
          row.error = error.message
        } finally {
          await closeDialog(page)
        }

        evidence.dialogs.push(row)
      }

      evidence.pageErrors = diagnostics.pageErrors.slice(errorsBeforeProbe)
    } finally {
      writeEvidence(`primitives/${slug}.json`, evidence)
    }

    expect(evidence.pageErrors, 'page errors during the dialog probe').toEqual([])
  })
}
