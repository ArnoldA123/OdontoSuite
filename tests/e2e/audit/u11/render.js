// Plan #13 (issue #374, U11) — layer 2: one row per surface for the role that
// owns it, measured with the delayed first API call that exposes the loading
// placeholder.
//
// The surface is rendered at 1440×900, measured, captured, and then reloaded at
// 390×844 before the mobile capture (never resize-then-measure: a resized
// document keeps widths computed at 1440 and invents an overflow). Detail
// surfaces are reached through a real row of their endpoint; when the endpoint
// has none the row is skipped with `no-sample-record`, never invented.
//
// The row asserts only what the instrument owns: both captures exist and the
// surface raised no page error. A divergence is evidence for the matrix, not a
// red suite.

import { test, expect } from 'playwright/test'

import {
  BASE_URL,
  applyScope,
  capture,
  classifyFailures,
  collectDiagnostics,
  contentLength,
  hasHorizontalOverflow,
  loadManifest,
  loginAs,
  primeSession,
  rolePlans,
  settleSurface,
  slugFor,
  writeEvidence
} from '../harness.js'

import { measureAxes } from './measure.js'

const OUT = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U11'
const SCOPE = process.env.AUDIT_SCOPE || 'all'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const THRESHOLD = 80
const SETTLE_TIMEOUT = 12000
const NAVIGATION_TIMEOUT = 15000
const CATCH_ALL_SEGMENT = ':pathMatch'
const CATCH_ALL_PROBE = '/audit-catch-all'

test.use({ viewport: DESKTOP })

const manifest = loadManifest()
const surfaces = applyScope(manifest, SCOPE)
const navLabels = new Map((manifest.navigation ?? []).map(entry => [entry.to, entry.name]))
const labelFor = surface => navLabels.get(surface.route) ?? surface.name

const isCatchAll = surface => surface.route.includes(CATCH_ALL_SEGMENT)

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

  // `/:pathMatch(.*)*` is a matcher, not a URL: the probe path renders the same
  // not-found surface the catch-all owns.
  if (isCatchAll(surface)) return CATCH_ALL_PROBE

  return surface.route
}

// The audit never creates data: the sample is the first row the endpoint
// already has, read with the administration token.
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

// The placeholder is painted while the payload is in flight, and it carries no
// text, so a settled measurement sees the content, never the skeleton. Delaying
// the API window opens it: every `/api/` response is held, so the page cannot
// skip its loading frame just because this machine answers in 20 ms. The
// interception is removed before settling; a handler already sleeping finishes.
const API_DELAY_MS = 1500

const delayApiCalls = page => {
  page.route('**/api/**', async route => {
    await new Promise(resolveDelay => setTimeout(resolveDelay, API_DELAY_MS))

    try {
      await route.continue()
    } catch (error) {
      // A continue that fails means the held request never reached the app, so
      // the row would blame the product for a probe condition. Say it out loud
      // in the job log instead of discarding it.
      console.warn(`[audit] delayed request could not continue: ${String(error)}`)
    }
  })
}

const sampleLoading = page =>
  page.evaluate(() => {
    const container = document.querySelector('main') ?? document.querySelector('#app')
    const visible = selector =>
      Array.from(document.querySelectorAll(selector)).some(node => {
        const box = node.getBoundingClientRect()
        return box.width > 0 && box.height > 0
      })

    return {
      skeleton: visible('.skeleton-container'),
      spinner: visible('.loading-spinner-container'),
      visibleText: (container?.innerText ?? '').trim().length
    }
  })

// A surface paints its shell first, then remounts when the session bootstrap
// resolves: at 250 ms the shell alone already clears the text threshold, and a
// single sample would classify the later skeleton frame as the finished page
// (measured on `/dashboard`, which swaps to twelve skeletons at ~1.2 s and
// paints its content at ~3 s). The placeholder is therefore sampled during the
// whole loading window, and the settled measurement waits for the surface to be
// quiet — text above the threshold and no loading primitive on screen.
const LOADING_WINDOW_MS = 2800
const LOADING_STEP_MS = 100

const watchLoading = async page => {
  const deadline = Date.now() + LOADING_WINDOW_MS
  let skeleton = false
  let spinner = false

  while (Date.now() < deadline) {
    const sample = await sampleLoading(page).catch(() => null)

    if (sample) {
      skeleton = skeleton || sample.skeleton
      spinner = spinner || sample.spinner
    }

    if (skeleton && spinner) break

    await page.waitForTimeout(LOADING_STEP_MS)
  }

  return { skeleton, spinner }
}

const settleQuiet = async (page, threshold, timeout = SETTLE_TIMEOUT) => {
  const quiet = await page
    .waitForFunction(
      value => {
        const surface = document.querySelector('main') ?? document.querySelector('#app')
        const text = (surface?.innerText ?? '').trim().length
        const busy = document.querySelectorAll(
          '.skeleton-container, .loading-spinner-container'
        ).length

        return text > value && busy === 0
      },
      threshold,
      { timeout, polling: 150 }
    )
    .then(() => true)
    .catch(() => false)

  await settleSurface(page, threshold, Math.min(timeout, 2000))

  return quiet
}

for (const surface of surfaces) {
  const [owner] = rolePlans(surface)
  const slug = slugFor(surface.route)

  test(`@U11 · capa 2 — ${labelFor(surface)}: ejes transversales como ${owner.role}`, async ({
    page,
    request
  }) => {
    test.setTimeout(180000)

    const diagnostics = collectDiagnostics(page)
    const evidence = {
      route: surface.route,
      slug,
      role: owner.role,
      href: null,
      url: null,
      httpStatus: null,
      contentLength: 0,
      rendered: false,
      measured: false,
      settled: false,
      mobileSettled: false,
      loadingProbe: null,
      horizontalOverflow: null,
      pageErrors: [],
      consoleErrors: [],
      failedRequests: [],
      viewports: { desktop: { ...DESKTOP } },
      shots: [],
      axes: null
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

      delayApiCalls(page)

      const response = await page.goto(href, { waitUntil: 'domcontentloaded' })
      evidence.httpStatus = response?.status() ?? null
      evidence.url = page.url()

      const probe = await watchLoading(page)

      await page.unroute('**/api/**')

      await page
        .locator('main')
        .waitFor({ state: 'visible', timeout: NAVIGATION_TIMEOUT })
        .catch(() => {})

      evidence.settled = await settleQuiet(page, THRESHOLD, SETTLE_TIMEOUT)

      const settledProbe = await sampleLoading(page)

      evidence.loadingProbe = {
        skeleton: probe.skeleton || settledProbe.skeleton,
        spinner: probe.spinner || settledProbe.spinner,
        visibleText: settledProbe.visibleText
      }

      evidence.axes = await measureAxes(page)
      evidence.contentLength = await contentLength(page)
      evidence.rendered = evidence.contentLength > THRESHOLD

      const desktopOverflow = await hasHorizontalOverflow(page)
      evidence.viewports.desktop = {
        ...DESKTOP,
        scrollWidth: desktopOverflow.scrollWidth,
        innerWidth: desktopOverflow.innerWidth
      }

      evidence.shots.push(
        await capture(page, `shots/${slug}-${owner.role}-desktop.png`, { dir: OUT })
      )

      await page.setViewportSize(MOBILE)
      await page.reload()
      evidence.mobileSettled = await settleQuiet(page, THRESHOLD, SETTLE_TIMEOUT)

      const mobileOverflow = await hasHorizontalOverflow(page)
      evidence.horizontalOverflow = mobileOverflow.overflow
      evidence.viewports.mobile = {
        ...MOBILE,
        scrollWidth: mobileOverflow.scrollWidth,
        innerWidth: mobileOverflow.innerWidth,
        overflow: mobileOverflow.overflow
      }

      evidence.shots.push(
        await capture(page, `shots/${slug}-${owner.role}-mobile.png`, { dir: OUT })
      )

      // The row is evidence only after both viewports were captured and the
      // axes were read. `finally` writes the file even when the probe skips or
      // fails (so the reason is recorded), and the report keys on this flag:
      // an existing file is not a measurement.
      evidence.measured = true
    } finally {
      evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
      evidence.consoleErrors = diagnostics.consoleErrors
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, owner.expectation)

      // `finally` runs while an assertion failure may already be in flight: a
      // write error here would replace it and hide what the probe found. The
      // missing file is still visible — the report marks the row NO PROBADO.
      try {
        writeEvidence(`render/${slug}-${owner.role}.json`, evidence, { dir: OUT })
      } catch (error) {
        console.error(
          `[audit] could not write render evidence for ${surface.route}: ${String(error)}`
        )
      }
    }

    if (evidence.measured) {
      expect(evidence.shots, `captures of ${surface.route}`).toHaveLength(2)
      expect(evidence.pageErrors, `page errors of ${surface.route}`).toEqual([])
    }
  })
}
