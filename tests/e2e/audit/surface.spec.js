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
  slugFor,
  writeEvidence
} from './harness.js'

const OUT = process.env.AUDIT_OUT || 'U0'
const SCOPE = process.env.AUDIT_SCOPE || 'all'

const manifest = loadManifest()
const surfaces = applyScope(manifest, SCOPE)

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const CATCH_ALL_SEGMENT = ':pathMatch'
const CATCH_ALL_PROBE = '/audit-catch-all'
const DEFAULT_THRESHOLD = 80
const NAVIGATION_TIMEOUT = 15000
const DIALOG_TIMEOUT = 2000

test.use({ viewport: DESKTOP })

const sessions = new Map()
const samples = new Map()

const isCatchAll = surface => surface.route.includes(CATCH_ALL_SEGMENT)

const thresholdFor = route => CONTENT_THRESHOLDS[route] ?? DEFAULT_THRESHOLD

const hrefFor = (surface, id) => {
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

        evidence.contentLength = await contentLength(page)
        evidence.rendered = evidence.contentLength > thresholdFor(surface.route)

        const heading = (
          await page
            .locator('h1')
            .first()
            .innerText()
            .catch(() => '')
        ).trim()

        evidence.notFound = !isCatchAll(surface) && /página no encontrada/i.test(heading)
        evidence.tabs = await readTabLabels(page)

        evidence.shots.push(await capture(page, `shots/${slug}-${plan.role}-desktop.png`))

        await page.setViewportSize(MOBILE)
        // The responsive layout settles after the resize; the bounded wait keeps
        // the measurement from reading the desktop layout mid-reflow.
        await page.waitForTimeout(200)

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

      expect(evidence.httpStatus, `HTTP status for ${evidence.href}`).toBe(200)
      expect(
        evidence.rendered,
        `content length ${evidence.contentLength} above ${thresholdFor(surface.route)}`
      ).toBe(true)
      if (!isCatchAll(surface)) {
        expect(evidence.notFound, 'rendered the not-found page').toBe(false)
      }
      expect(evidence.pageErrors, 'page errors').toEqual([])
      expect(evidence.failedRequests.unexpected, 'unexpected failed requests').toEqual([])
      expect(evidence.horizontalOverflow, 'horizontal overflow at 390px').toBe(false)
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
